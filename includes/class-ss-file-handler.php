<?php
/**
 * کلاس مدیریت آپلود امن فایل‌های افزونه پشتیبانی هوشمند.
 *
 * تمام فایل‌های ارسالی کاربران (چه در چت زنده و چه در فرم تیکت) از این
 * کلاس عبور می‌کنند. اعتبارسنجی نوع فایل (بر اساس MIME واقعی، نه فقط
 * پسوند) و حجم فایل به صورت سخت‌گیرانه انجام می‌شود.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SS_File_Handler {

	/**
	 * حداکثر حجم مجاز آپلود بر حسب بایت (۵ مگابایت طبق مستندات پروژه).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 5242880; // 5 * 1024 * 1024

	/**
	 * لیست پسوندهای مجاز آپلود.
	 *
	 * @var array
	 */
	private static $allowed_extensions = array( 'jpg', 'jpeg', 'png', 'pdf' );

	/**
	 * لیست انواع MIME مجاز متناظر با پسوندهای بالا جهت اعتبارسنجی واقعی محتوای فایل.
	 *
	 * @var array
	 */
	private static $allowed_mime_types = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'pdf'  => 'application/pdf',
	);

	/**
	 * پردازش و ذخیره امن یک فایل آپلودشده.
	 *
	 * @param array $file یکی از عناصر آرایه سراسری $_FILES.
	 * @return array|WP_Error آرایه شامل url و path در صورت موفقیت، یا WP_Error در خطا.
	 */
	public static function handle_upload( $file ) {

		// بررسی وجود خطای آپلود در سطح PHP
		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'ss_upload_error', 'خطا در دریافت فایل. لطفاً دوباره تلاش کنید.' );
		}

		// بررسی حجم فایل قبل از هرگونه پردازش دیگر
		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'ss_file_too_large', 'حجم فایل نباید بیشتر از ۵ مگابایت باشد.' );
		}

		// استخراج پسوند فایل از نام اصلی و تبدیل به حروف کوچک
		$file_name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		// بررسی مجاز بودن پسوند فایل
		if ( ! in_array( $extension, self::$allowed_extensions, true ) ) {
			return new WP_Error( 'ss_invalid_extension', 'فرمت فایل مجاز نیست. فقط jpg، jpeg، png و pdf پذیرفته می‌شود.' );
		}

		// بررسی واقعی نوع محتوای فایل (نه فقط اعتماد به پسوند) با استفاده از wp_check_filetype_and_ext
		$file_type_check = wp_check_filetype_and_ext( $file['tmp_name'], $file_name );

		if ( empty( $file_type_check['ext'] ) || empty( $file_type_check['type'] ) ) {
			return new WP_Error( 'ss_invalid_mime', 'نوع فایل قابل شناسایی نیست یا امن نمی‌باشد.' );
		}

		$expected_mime = isset( self::$allowed_mime_types[ $extension ] ) ? self::$allowed_mime_types[ $extension ] : '';

		if ( $file_type_check['type'] !== $expected_mime ) {
			return new WP_Error( 'ss_mime_mismatch', 'محتوای فایل با پسوند آن مطابقت ندارد.' );
		}

		// آماده‌سازی پوشه امن اختصاصی افزونه در uploads
		$upload_dir_info = self::get_upload_dir();

		if ( is_wp_error( $upload_dir_info ) ) {
			return $upload_dir_info;
		}

		// ساخت یک نام فایل یکتا و امن جهت جلوگیری از افشای نام اصلی یا برخورد فایل‌ها
		$unique_name = wp_unique_filename( $upload_dir_info['path'], uniqid( 'ss_', true ) . '.' . $extension );
		$destination = trailingslashit( $upload_dir_info['path'] ) . $unique_name;

		// انتقال فایل از مسیر موقت به مقصد نهایی به صورت امن
		$moved = self::move_uploaded_file( $file['tmp_name'], $destination );

		if ( ! $moved ) {
			return new WP_Error( 'ss_move_failed', 'ذخیره‌سازی فایل با خطا مواجه شد.' );
		}

		// تنظیم مجوز دسترسی مناسب برای فایل جهت جلوگیری از اجرای غیرمجاز
		chmod( $destination, 0644 );

		$file_url = trailingslashit( $upload_dir_info['url'] ) . $unique_name;

		// ثبت فایل در جدول مدیریت جهت پاکسازی خودکار توسط WP-Cron
		SS_Database::register_attachment( $destination, $file_url );

		return array(
			'path' => $destination,
			'url'  => $file_url,
		);
	}

	/**
	 * انتقال فایل آپلودشده با اولویت استفاده از توابع فایل‌سیستم وردپرس.
	 *
	 * @param string $tmp_name مسیر موقت فایل.
	 * @param string $destination مسیر مقصد نهایی.
	 * @return bool
	 */
	private static function move_uploaded_file( $tmp_name, $destination ) {
		global $wp_filesystem;

		// اطمینان از بارگذاری WP_Filesystem جهت عملیات امن روی فایل
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( is_uploaded_file( $tmp_name ) ) {
			return move_uploaded_file( $tmp_name, $destination );
		}

		// حالت پشتیبان برای مواردی که فایل از طریق آپلود استاندارد HTTP نیامده (کمتر رخ می‌دهد)
		return copy( $tmp_name, $destination );
	}

	/**
	 * دریافت (و در صورت نیاز ساخت) مسیر و URL پوشه امن اختصاصی افزونه.
	 *
	 * پوشه در wp-content/uploads/smart-support-content ساخته می‌شود و
	 * یک فایل index.php خالی و .htaccess جهت جلوگیری از اجرای اسکریپت
	 * و لیست شدن فایل‌ها در آن قرار می‌گیرد.
	 *
	 * @return array|WP_Error آرایه شامل path و url، یا WP_Error در خطا.
	 */
	public static function get_upload_dir() {
		$wp_upload_dir = wp_upload_dir();

		if ( ! empty( $wp_upload_dir['error'] ) ) {
			return new WP_Error( 'ss_upload_dir_error', 'پوشه آپلود وردپرس در دسترس نیست.' );
		}

		$base_path = trailingslashit( $wp_upload_dir['basedir'] ) . 'smart-support-content';
		$base_url  = trailingslashit( $wp_upload_dir['baseurl'] ) . 'smart-support-content';

		if ( ! file_exists( $base_path ) ) {
			wp_mkdir_p( $base_path );
		}

		// ساخت فایل index.php خالی جهت جلوگیری از نمایش لیست فایل‌ها در سرورهایی که Directory Listing فعال دارند
		$index_file = trailingslashit( $base_path ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		// ساخت .htaccess جهت غیرفعال کردن اجرای فایل‌های PHP در این پوشه (محافظت لایه دوم امنیتی)
		$htaccess_file = trailingslashit( $base_path ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "php_flag engine off\n<FilesMatch \"\\.(php|php3|php4|php5|phtml)$\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>\n";
			file_put_contents( $htaccess_file, $htaccess_content );
		}

		return array(
			'path' => $base_path,
			'url'  => $base_url,
		);
	}
}
