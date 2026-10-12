<?php
/**
 * Groot Vision — ماژول گزارش کار (Work Report)
 *
 * ثبت سریع گزارش کار کارمندان، تأیید مدیر، گزارش مخصوص کارفرما با شورت‌کد
 * [groot_work_report]، کلمات کلیدی و تاریخچه‌ی رتبه، نمودار، تقویم، Excel و PDF.
 *
 * این فایل را با require_once از groot-vision-tools.php لود کنید.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'GVWR_VERSION' ) ) {
	return; // جلوگیری از لود دوباره
}

define( 'GVWR_VERSION', '1.0.0' );
define( 'GVWR_DB_VERSION', '1.0.0' );
define( 'GVWR_DIR', plugin_dir_path( __FILE__ ) );
define( 'GVWR_URL', plugin_dir_url( __FILE__ ) );
define( 'GVWR_MENU_SLUG', 'gv-work-report' );

// هر فایل یک مسئولیت دارد؛ برای ویرایش، فقط فایل مربوطه را باز کنید.
$gvwr_files = array(
	'helpers', // تنظیمات، تاریخ شمسی، بازه‌های زمانی
	'db',      // جدول‌ها و داده‌های اولیه
	'roles',   // نقش‌ها و دسترسی‌ها
	'data',    // کوئری‌ها، آمار، کلمات کلیدی و رتبه
	'xlsx',    // خواندن/نوشتن Excel و CSV
	'views',          // ابزارهای مشترک UI + فرم ثبت گزارش + گزارش‌های من
	'views-manager',  // داشبورد مدیر، همه‌ی گزارش‌ها، مقایسه، تقویم
	'views-keywords', // کلمات کلیدی و رتبه
	'views-admin',    // افراد، دسته‌بندی‌ها، تنظیمات
	'ajax',    // همه‌ی درخواست‌های AJAX
	'client',  // شورت‌کد و گزارش کارفرما
	'menus',   // منوی ادمین، اعلان‌ها، ریدایرکت‌ها
);
foreach ( $gvwr_files as $gvwr_file ) {
	require_once GVWR_DIR . 'includes/' . $gvwr_file . '.php';
}
unset( $gvwr_files, $gvwr_file );

add_action( 'init', 'gvwr_maybe_upgrade', 5 );

add_filter( 'gv_hub_employee_users', function ( $users ) {
	$all = array();
	foreach ( $users as $u ) { $all[ $u->ID ] = $u; }
	foreach ( get_users( array( 'role__in' => array( 'gvwr_employee', 'gvwr_manager' ) ) ) as $u ) { $all[ $u->ID ] = $u; }
	return array_values( $all );
} );
add_filter( 'gv_hub_employee_reports_count', function ( $c, $uid ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . gvwr_table( 'items' ) . ' WHERE user_id=%d', $uid ) );
}, 10, 2 );
add_filter( 'gv_hub_employee_last_report', function ( $d, $uid ) {
	global $wpdb;
	$last = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(work_date) FROM ' . gvwr_table( 'items' ) . ' WHERE user_id=%d', $uid ) );
	return $last ? gvwr_date( $last, 'long' ) : $d;
}, 10, 2 );