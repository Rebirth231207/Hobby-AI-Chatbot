<?php
/**
 * کلاس پیشخوان مدیریت افزونه پشتیبانی هوشمند.
 *
 * این کلاس مسئول ثبت منوی اصلی افزونه، بارگذاری فایل‌های CSS/JS
 * اختصاصی پنل ادمین و رندر ساختار HTML پنل SPA سه‌ستونه است. تمام
 * تعامل‌ها (جابجایی بین بخش‌ها، ارسال پیام، CRUD) با جاوااسکریپت خالص
 * و REST API انجام می‌شود.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SS_Admin {

	/**
	 * ثبت هوک‌های مربوط به پیشخوان مدیریت.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * ثبت منوی اصلی افزونه در پیشخوان وردپرس.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			'پشتیبانی هوشمند',
			'پشتیبانی هوشمند',
			'manage_options', // قابلیت لازم جهت دسترسی - فقط مدیران کل سایت
			'smart-support',
			array( $this, 'render_dashboard_page' ),
			'dashicons-format-chat',
			26
		);
	}

	/**
	 * بارگذاری فایل‌های CSS و JS اختصاصی پنل ادمین، فقط در صفحه خود افزونه.
	 *
	 * @param string $hook نام صفحه فعلی پیشخوان جهت بررسی محدوده بارگذاری.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {

		// بارگذاری فایل‌ها فقط در صفحه اختصاصی افزونه، جهت جلوگیری از تداخل با سایر صفحات پیشخوان
		if ( 'toplevel_page_smart-support' !== $hook ) {
			return;
		}

		// بارگذاری فونت وزیر متن جهت هماهنگی کامل با فرانت‌اند
		wp_enqueue_style(
			'ss-vazirmatn-font',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'ss-admin-style',
			SS_PLUGIN_URL . 'admin/css/admin.css',
			array( 'ss-vazirmatn-font' ),
			SS_VERSION
		);

		wp_enqueue_media(); // جهت استفاده از رسانه وردپرس (آپلود لوگو و آیکون‌ها)

		wp_enqueue_script(
			'ss-admin-script',
			SS_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			SS_VERSION,
			true
		);

		wp_localize_script(
			'ss-admin-script',
			'SS_Admin_Data',
			array(
				'rest_url' => esc_url_raw( rest_url( 'smart-support/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * رندر ساختار HTML پنل مدیریت SPA سه‌ستونه.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {

		// بررسی سطح دسترسی کاربر قبل از نمایش هرگونه داده مدیریتی
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.' );
		}

		$template_path = SS_PLUGIN_DIR . 'admin/templates/dashboard.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}
}

