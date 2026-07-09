<?php
/**
 * کلاس فرانت‌اند عمومی افزونه پشتیبانی هوشمند.
 *
 * این کلاس مسئول بارگذاری فایل‌های CSS/JS ویجت چت در سمت کاربر و
 * رندر کردن HTML ویجت در فوتر سایت است. ظاهر و ساختار HTML/CSS دقیقاً
 * همان چیزی است که در Front.zip تحویل داده شده و هیچ تغییری در آن
 * اعمال نشده است.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SS_Public {

	/**
	 * ثبت هوک‌های مربوط به فرانت‌اند عمومی سایت.
	 *
	 * @return void
	 */
	public function init_hooks() {

		// بارگذاری فایل‌های CSS و JS اختصاصی ویجت در فرانت سایت
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// درج HTML ویجت چت درست قبل از بسته شدن تگ </body> در تمام صفحات سایت
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
	}

	/**
	 * بارگذاری فایل‌های استایل و اسکریپت ویجت چت.
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		// عدم بارگذاری در پیشخوان مدیریت (این کلاس فقط برای سمت کاربر است)
		if ( is_admin() ) {
			return;
		}

		// بارگذاری فونت اختصاصی وزیر متن از گوگل فونت (استفاده‌شده در استایل اصلی ویجت)
		wp_enqueue_style(
			'ss-vazirmatn-font',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
			array(),
			null
		);

		// بارگذاری استایل اصلی ویجت (بدون تغییر نسبت به فایل تحویلی)
		wp_enqueue_style(
			'ss-widget-style',
			SS_PLUGIN_URL . 'public/css/style.css',
			array( 'ss-vazirmatn-font' ),
			SS_VERSION
		);

		// بارگذاری اسکریپت اصلی ویجت (نسخه متصل‌شده به REST API واقعی)
		wp_enqueue_script(
			'ss-widget-script',
			SS_PLUGIN_URL . 'public/js/script.js',
			array(),
			SS_VERSION,
			true // بارگذاری در پایین صفحه (footer) جهت بهبود سرعت رندر
		);

		// تزریق داده‌های اتصال (آدرس REST API، Nonce و متن‌های داینامیک) به جاوااسکریپت
		wp_localize_script(
			'ss-widget-script',
			'SS_Data',
			array(
				'rest_url'         => esc_url_raw( rest_url( 'smart-support/v1' ) ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'is_logged_in'     => is_user_logged_in(),
				'current_user_name' => is_user_logged_in() ? wp_get_current_user()->display_name : '',
			)
		);
	}

	/**
	 * رندر HTML ویجت چت در فوتر تمام صفحات سایت.
	 *
	 * @return void
	 */
	public function render_widget() {

		if ( is_admin() ) {
			return;
		}

		// بارگذاری فایل قالب HTML ویجت (بدون هیچ تغییری نسبت به Front.zip)
		$template_path = SS_PLUGIN_DIR . 'public/templates/widget.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}
}
