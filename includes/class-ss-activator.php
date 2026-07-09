<?php
/**
 * کلاس فعال‌سازی افزونه پشتیبانی هوشمند.
 *
 * این کلاس مسئول ساخت جداول اختصاصی دیتابیس افزونه هنگام فعال‌سازی
 * است. از تابع استاندارد dbDelta وردپرس استفاده می‌شود تا در صورت
 * آپدیت افزونه، ساختار جداول به صورت امن به‌روزرسانی شود بدون از دست
 * رفتن داده‌های موجود.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SS_Activator {

	/**
	 * متد اصلی فعال‌سازی - از register_activation_hook فراخوانی می‌شود.
	 *
	 * @return void
	 */
	public static function activate() {

		// ساخت تمام جداول اختصاصی افزونه
		self::create_tables();

		// ذخیره نسخه اسکیمای دیتابیس جهت آپگریدهای بعدی
		update_option( 'ss_db_version', SS_DB_VERSION );

		// زمان‌بندی کرون‌جاب پاکسازی فایل‌ها در صورتی که قبلاً زمان‌بندی نشده باشد
		if ( ! wp_next_scheduled( 'ss_cleanup_old_files' ) ) {
			wp_schedule_event( time(), 'ss_fifteen_days', 'ss_cleanup_old_files' );
		}

		// پاکسازی کش قوانین بازنویسی (Rewrite Rules) در صورت نیاز آینده به Permalink اختصاصی
		flush_rewrite_rules();
	}

	/**
	 * بررسی و به‌روزرسانی خودکار ساختار دیتابیس در صورت آپدیت افزونه.
	 *
	 * register_activation_hook فقط زمانی اجرا می‌شود که افزونه از حالت
	 * غیرفعال به فعال سوئیچ شود؛ اگر فایل‌های افزونه صرفاً جایگزین شوند
	 * (بدون غیرفعال/فعال‌سازی دستی توسط کاربر)، جداول قدیمی به‌روزرسانی
	 * نمی‌شوند. این متد در هر بارگذاری افزونه (plugins_loaded) نسخه
	 * ذخیره‌شده در دیتابیس را با نسخه فعلی کد مقایسه می‌کند و در صورت
	 * اختلاف، dbDelta را دوباره (به‌صورت امن و بدون از دست دادن داده‌های
	 * موجود) اجرا می‌کند.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'ss_db_version', '' );

		if ( SS_DB_VERSION === $installed_version ) {
			return;
		}

		self::create_tables();

		update_option( 'ss_db_version', SS_DB_VERSION );
	}

	/**
	 * ساخت جداول دیتابیس با استفاده از dbDelta.
	 *
	 * توجه: دستورات SQL باید دقیقاً با فرمت مورد نیاز dbDelta نوشته شوند
	 * (دو فاصله بعد از PRIMARY KEY، حروف بزرگ برای KEY و... ) تا این تابع
	 * به درستی عمل کند.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		// فراخوانی فایل مورد نیاز dbDelta در صورت عدم بارگذاری قبلی
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$table_conversations    = SS_Database::table_conversations();
		$table_messages         = SS_Database::table_messages();
		$table_tickets          = SS_Database::table_tickets();
		$table_ticket_messages  = SS_Database::table_ticket_messages();
		$table_attachments      = SS_Database::table_attachments();
		$table_knowledge        = SS_Database::table_knowledge();
		$table_faqs             = SS_Database::table_faqs();
		$table_canned_responses = SS_Database::table_canned_responses();

		/**
		 * جدول مکالمات چت زنده.
		 * هر ردیف یک نشست گفتگو بین یک کاربر (لاگین‌شده یا مهمان) و پشتیبانی است.
		 */
		$sql_conversations = "CREATE TABLE {$table_conversations} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_uid VARCHAR(64) NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			guest_name VARCHAR(191) NOT NULL DEFAULT '',
			guest_phone VARCHAR(32) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'unanswered',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY conversation_uid (conversation_uid),
			KEY status (status),
			KEY user_id (user_id)
		) {$charset_collate};";

		/**
		 * جدول پیام‌های چت زنده.
		 * هر ردیف یک پیام منفرد در یک مکالمه است.
		 */
		$sql_messages = "CREATE TABLE {$table_messages} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT(20) UNSIGNED NOT NULL,
			sender_type VARCHAR(10) NOT NULL DEFAULT 'user',
			message TEXT NOT NULL,
			attachment_url VARCHAR(500) NOT NULL DEFAULT '',
			reply_to_text VARCHAR(500) NOT NULL DEFAULT '',
			is_read TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id)
		) {$charset_collate};";

		/**
		 * جدول تیکت‌های پشتیبانی.
		 * هر ردیف اطلاعات کلی یک تیکت ثبت‌شده توسط کاربر است.
		 */
		$sql_tickets = "CREATE TABLE {$table_tickets} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tracking_code VARCHAR(20) NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			first_name VARCHAR(191) NOT NULL DEFAULT '',
			last_name VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(32) NOT NULL DEFAULT '',
			subject VARCHAR(255) NOT NULL DEFAULT '',
			department VARCHAR(50) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tracking_code (tracking_code),
			KEY phone (phone),
			KEY status (status)
		) {$charset_collate};";

		/**
		 * جدول پیام‌های تیکت.
		 * تاریخچه کامل گفتگوی مربوط به هر تیکت را نگه می‌دارد.
		 */
		$sql_ticket_messages = "CREATE TABLE {$table_ticket_messages} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT(20) UNSIGNED NOT NULL,
			sender_type VARCHAR(10) NOT NULL DEFAULT 'user',
			message TEXT NOT NULL,
			attachment_url VARCHAR(500) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id)
		) {$charset_collate};";

		/**
		 * جدول مدیریت فایل‌های آپلودشده.
		 * برای پیگیری و پاکسازی خودکار فایل‌های قدیمی توسط WP-Cron استفاده می‌شود.
		 */
		$sql_attachments = "CREATE TABLE {$table_attachments} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			file_path VARCHAR(500) NOT NULL,
			file_url VARCHAR(500) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		/**
		 * جدول نالج گراف - مغز متفکر هوش مصنوعی.
		 * هر گره شامل یک عنوان، متن دانش پردازش‌شده و برچسب‌های جستجو است.
		 */
		$sql_knowledge = "CREATE TABLE {$table_knowledge} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			content TEXT NOT NULL,
			tags VARCHAR(500) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		/**
		 * جدول سوالات متداول (FAQ) نمایش‌داده‌شده در ویجت فرانت‌اند.
		 */
		$sql_faqs = "CREATE TABLE {$table_faqs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			question VARCHAR(255) NOT NULL,
			answer TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		/**
		 * جدول پیام‌های آماده (Canned Responses) جهت استفاده سریع اپراتور در چت زنده.
		 */
		$sql_canned_responses = "CREATE TABLE {$table_canned_responses} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			message TEXT NOT NULL,
			category VARCHAR(100) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		// اجرای dbDelta برای هر جدول به صورت جداگانه
		dbDelta( $sql_conversations );
		dbDelta( $sql_messages );
		dbDelta( $sql_tickets );
		dbDelta( $sql_ticket_messages );
		dbDelta( $sql_attachments );
		dbDelta( $sql_knowledge );
		dbDelta( $sql_faqs );
		dbDelta( $sql_canned_responses );
	}
}
