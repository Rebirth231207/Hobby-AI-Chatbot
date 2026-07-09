<?php
/**
 * فایل پاکسازی هنگام حذف کامل افزونه پشتیبانی هوشمند.
 *
 * این فایل فقط زمانی اجرا می‌شود که افزونه از طریق پیشخوان وردپرس به
 * طور کامل حذف (Delete) شود، نه صرفاً غیرفعال (Deactivate). طبق
 * استاندارد وردپرس، ابتدا ثابت WP_UNINSTALL_PLUGIN بررسی می‌شود تا از
 * اجرای مستقیم و ناخواسته این فایل جلوگیری شود.
 *
 * @package SmartSupport
 */

// جلوگیری از اجرای مستقیم این فایل خارج از فرآیند حذف رسمی وردپرس
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// نام کامل جداول اختصاصی افزونه جهت حذف
$tables = array(
	$wpdb->prefix . 'ss_conversations',
	$wpdb->prefix . 'ss_messages',
	$wpdb->prefix . 'ss_tickets',
	$wpdb->prefix . 'ss_ticket_messages',
	$wpdb->prefix . 'ss_attachments',
	$wpdb->prefix . 'ss_knowledge',
	$wpdb->prefix . 'ss_faqs',
	$wpdb->prefix . 'ss_canned_responses',
);

// حذف امن هر جدول در صورت وجود
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

// حذف گزینه‌های ذخیره‌شده افزونه از جدول options
delete_option( 'ss_db_version' );
delete_option( 'ss_settings_ui' );
delete_option( 'ss_settings_ai' );
delete_option( 'ss_settings_logic' );

// لغو زمان‌بندی کرون‌جاب پاکسازی فایل‌ها در صورت وجود
$timestamp = wp_next_scheduled( 'ss_cleanup_old_files' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'ss_cleanup_old_files' );
}
