<?php
/**
 * کلاس مدیریت زمان‌بندی WP-Cron برای پاکسازی خودکار فایل‌های قدیمی.
 *
 * طبق مستندات پروژه، هر ۱۵ روز یکبار فایل‌های آپلودشده قدیمی به صورت
 * خودکار از سرور حذف می‌شوند تا فضای هاست اشباع نشود.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SS_Cron {

	/**
	 * تعداد روزهایی که یک فایل باید نگهداری شود قبل از پاکسازی خودکار.
	 *
	 * @var int
	 */
	const RETENTION_DAYS = 15;

	/**
	 * ثبت هوک‌های مربوط به کرون‌جاب.
	 *
	 * @return void
	 */
	public function init_hooks() {

		// افزودن بازه زمانی سفارشی ۱۵ روزه به لیست بازه‌های استاندارد WP-Cron
		add_filter( 'cron_schedules', array( $this, 'register_custom_schedule' ) );

		// اتصال تابع پاکسازی به رویداد زمان‌بندی‌شده
		add_action( 'ss_cleanup_old_files', array( $this, 'cleanup_old_files' ) );
	}

	/**
	 * افزودن بازه زمانی «هر ۱۵ روز» به بازه‌های موجود WP-Cron.
	 *
	 * @param array $schedules آرایه بازه‌های موجود.
	 * @return array آرایه به‌روزرسانی‌شده بازه‌ها.
	 */
	public function register_custom_schedule( $schedules ) {
		$schedules['ss_fifteen_days'] = array(
			'interval' => 15 * DAY_IN_SECONDS,
			'display'  => 'هر ۱۵ روز یکبار (پشتیبانی هوشمند)',
		);

		return $schedules;
	}

	/**
	 * اجرای عملیات پاکسازی: حذف فایل‌های قدیمی‌تر از ۱۵ روز از سرور و دیتابیس.
	 *
	 * این تابع Idempotent است؛ یعنی اجرای چندباره آن مشکلی ایجاد نمی‌کند
	 * چون فقط فایل‌هایی که واقعاً از بازه تعیین‌شده قدیمی‌تر هستند حذف می‌شوند.
	 *
	 * @return void
	 */
	public function cleanup_old_files() {
		$old_attachments = SS_Database::get_old_attachments( self::RETENTION_DAYS );

		if ( empty( $old_attachments ) ) {
			return;
		}

		foreach ( $old_attachments as $attachment ) {
			// حذف فیزیکی فایل از سرور در صورت وجود
			if ( file_exists( $attachment->file_path ) ) {
				wp_delete_file( $attachment->file_path );
			}

			// حذف رکورد از جدول مدیریت فایل‌ها پس از پاکسازی موفق
			SS_Database::delete_attachment_record( $attachment->id );
		}
	}

	/**
	 * لغو زمان‌بندی کرون‌جاب هنگام غیرفعال‌سازی افزونه.
	 *
	 * از register_deactivation_hook در فایل اصلی افزونه فراخوانی می‌شود.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'ss_cleanup_old_files' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ss_cleanup_old_files' );
		}
	}
}
