<?php
/**
 * Plugin Name:       پشتیبانی هوشمند
 * Plugin URI:        https://hobbytech.example.com
 * Description:       افزونه جامع پشتیبانی هوشمند و تیکتینگ وردپرس با چت زنده، سیستم تیکت و آماده اتصال به هوش مصنوعی.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Mohammad Hadi Salimi
 * Text Domain:       smart-support
 * Domain Path:       /languages
 *
 * توجه: این افزونه به صورت مطلق با PHP 7.4 سازگار است. از ساختارهای شیءگرایی
 * سنتی (کلاسیک) استفاده می‌شود و هیچ‌کدام از ویژگی‌های اختصاصی PHP 8+
 * (مانند Arrow Functions پیشرفته، Match Expressions یا Named Arguments) در
 * این پروژه به کار نرفته است.
 */

// جلوگیری از دسترسی مستقیم به فایل - یک اصل امنیتی پایه در تمام فایل‌های افزونه
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------------
 * تعریف ثابت‌های سراسری افزونه (Plugin Constants)
 * -----------------------------------------------------------------------
 * این ثابت‌ها در سراسر افزونه برای مسیردهی امن به فایل‌ها، URLها و نسخه
 * استفاده می‌شوند تا از هاردکد کردن مسیر جلوگیری شود.
 */

// نسخه فعلی افزونه - برای کش بستینگ فایل‌های CSS/JS و کنترل آپگرید دیتابیس
if ( ! defined( 'SS_VERSION' ) ) {
	define( 'SS_VERSION', '1.1.0' );
}

// مسیر مطلق پوشه اصلی افزونه روی سرور (با اسلش انتهایی)
if ( ! defined( 'SS_PLUGIN_DIR' ) ) {
	define( 'SS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// آدرس URL پوشه اصلی افزونه (با اسلش انتهایی) جهت لود فایل‌های استاتیک
if ( ! defined( 'SS_PLUGIN_URL' ) ) {
	define( 'SS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// مسیر کامل فایل اصلی افزونه - جهت استفاده در register_activation_hook و امثالهم
if ( ! defined( 'SS_PLUGIN_FILE' ) ) {
	define( 'SS_PLUGIN_FILE', __FILE__ );
}

// نام پیشوند اختصاصی جداول دیتابیس افزونه جهت جلوگیری از تداخل با سایر افزونه‌ها
if ( ! defined( 'SS_DB_PREFIX' ) ) {
	define( 'SS_DB_PREFIX', 'ss_' );
}

// شماره نسخه اسکیمای دیتابیس - برای مدیریت آپگریدهای بعدی ساختار جداول
if ( ! defined( 'SS_DB_VERSION' ) ) {
	define( 'SS_DB_VERSION', '1.1.0' );
}

/**
 * -----------------------------------------------------------------------
 * بارگذاری فایل‌های کلاس اصلی افزونه (Dependency Loading)
 * -----------------------------------------------------------------------
 * هر کلاس در فایل اختصاصی خودش قرار دارد تا معماری کاملاً ماژولار و
 * قابل نگهداری باشد. ترتیب بارگذاری اهمیت دارد چون کلاس‌های بعدی از
 * کلاس‌های قبلی استفاده می‌کنند.
 */

// کلاس مدیریت دیتابیس - تمام کوئری‌های اختصاصی افزونه از این کلاس عبور می‌کنند
require_once SS_PLUGIN_DIR . 'includes/class-ss-database.php';

// کلاس مدیریت تنظیمات افزونه (ظاهری، هوش مصنوعی، منطق پاسخ‌دهی)
require_once SS_PLUGIN_DIR . 'includes/class-ss-settings.php';

// کلاس فعال‌سازی - مسئول ساخت جداول دیتابیس هنگام فعال شدن افزونه
require_once SS_PLUGIN_DIR . 'includes/class-ss-activator.php';

// کلاس مدیریت آپلود امن فایل‌ها (تیکت‌ها و پیوست‌های چت)
require_once SS_PLUGIN_DIR . 'includes/class-ss-file-handler.php';

// کلاس کنترلر REST API - تمام Endpointهای چت و تیکت اینجا ثبت می‌شوند
require_once SS_PLUGIN_DIR . 'includes/class-ss-rest-controller.php';

// کلاس کرون‌جاب - وظیفه پاکسازی خودکار فایل‌های قدیمی هر ۱۵ روز
require_once SS_PLUGIN_DIR . 'includes/class-ss-cron.php';

// کلاس فرانت‌اند عمومی - مسئول لود ویجت چت در سایت و enqueue استایل/اسکریپت
require_once SS_PLUGIN_DIR . 'public/class-ss-public.php';

// کلاس پیشخوان مدیریت - مسئول نمایش منوی ادمین (در این فاز حداقلی)
require_once SS_PLUGIN_DIR . 'admin/class-ss-admin.php';

/**
 * -----------------------------------------------------------------------
 * ثبت هوک‌های فعال‌سازی و غیرفعال‌سازی افزونه
 * -----------------------------------------------------------------------
 * طبق استاندارد وردپرس، این هوک‌ها باید در سطح اصلی فایل و خارج از هر
 * هوک دیگری ثبت شوند تا به درستی توسط هسته وردپرس شناسایی شوند.
 */
register_activation_hook( SS_PLUGIN_FILE, array( 'SS_Activator', 'activate' ) );
register_deactivation_hook( SS_PLUGIN_FILE, array( 'SS_Cron', 'deactivate' ) );

/**
 * تابع اجرای اصلی افزونه.
 *
 * این تابع بعد از بارگذاری تمام پلاگین‌ها (plugins_loaded) اجرا می‌شود تا
 * اطمینان حاصل شود تمام توابع و کلاس‌های مورد نیاز وردپرس (از جمله
 * WooCommerce در صورت فعال بودن) از قبل در دسترس هستند.
 *
 * @return void
 */
function ss_run_plugin() {

	// بررسی و به‌روزرسانی خودکار ساختار دیتابیس در صورت آپدیت افزونه بدون فعال‌سازی مجدد دستی
	SS_Activator::maybe_upgrade();

	// راه‌اندازی کلاس فرانت‌اند عمومی (enqueue استایل/اسکریپت و رندر ویجت)
	$public = new SS_Public();
	$public->init_hooks();

	// راه‌اندازی کلاس پیشخوان مدیریت (فقط در محیط ادمین اجرا می‌شود)
	if ( is_admin() ) {
		$admin = new SS_Admin();
		$admin->init_hooks();
	}

	// راه‌اندازی کنترلر REST API (مسیرهای چت و تیکت)
	$rest_controller = new SS_REST_Controller();
	add_action( 'rest_api_init', array( $rest_controller, 'register_routes' ) );

	// راه‌اندازی کرون‌جاب پاکسازی خودکار فایل‌ها
	$cron = new SS_Cron();
	$cron->init_hooks();
}
add_action( 'plugins_loaded', 'ss_run_plugin' );
