<?php
/**
 * Groot Vision — ماژول مدیریت سطح دسترسی (Access Manager)
 *
 * ساخت نقش (سطح کاربری)، تخصیص نقش به کاربر، بستن دسترسی‌ها برای هر نقش یا هر کاربر
 * (منو، نوار ادمین، ویجت‌ها، متاباکس‌ها، قابلیت‌ها، محتوا، آدرس‌ها) با دو حالت «قفل» و «مخفی»،
 * پیش‌نمایش به‌جای کاربر، نقش موقت، پنهان‌سازی کاربر (Stealth)، گزارش تلاش‌های ممنوع، ورود/خروج JSON.
 *
 * این فایل را با require_once از groot-vision-tools.php لود کنید.
 *
 * حالت اضطراری: اگر قفل شدید، در wp-config.php این خط را اضافه کنید تا همه‌ی محدودیت‌ها خاموش شود:
 *   define( 'GV_AC_DISABLE', true );
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'GVAC_VERSION' ) ) {
	return; // جلوگیری از لود دوباره
}

define( 'GVAC_VERSION', '1.0.0' );
define( 'GVAC_DB_VERSION', '1.0.0' );
define( 'GVAC_DIR', plugin_dir_path( __FILE__ ) );
define( 'GVAC_URL', plugin_dir_url( __FILE__ ) );
define( 'GVAC_MENU_SLUG', 'gv-access-manager' );

// هر فایل یک مسئولیت دارد؛ برای ویرایش، فقط فایل مربوطه را باز کنید.
$gvac_files = array(
	'helpers',  // تنظیمات، ذخیره‌ی قوانین، ادغام قوانین، لاگ
	'db',       // جدول لاگ، نصب و ارتقا
	'catalog',  // فهرست منوها، نوار ادمین، ویجت‌ها، متاباکس‌ها و قابلیت‌ها
	'engine',   // اجرای محدودیت‌ها (منو، قابلیت، آدرس، محتوا، ریدایرکت، پیش‌نمایش)
	'stealth',  // پنهان‌سازی کاربر و پنهان‌سازی خود افزونه
	'roles',    // ساخت/ویرایش/حذف نقش، تخصیص نقش به کاربر، نقش موقت
	'ajax',     // همه‌ی درخواست‌های AJAX پنل
	'views',    // منوی ادمین و صفحه‌ی پنل
);
foreach ( $gvac_files as $gvac_file ) {
	require_once GVAC_DIR . 'includes/' . $gvac_file . '.php';
}
unset( $gvac_files, $gvac_file );

add_action( 'init', 'gvac_maybe_upgrade', 5 );
