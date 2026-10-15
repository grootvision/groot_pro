<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** اگر نسخه‌ی دیتابیس تغییر کرده، جدول‌ها و داده‌های اولیه را بساز. */
function gvac_maybe_upgrade() {
	if ( get_option( 'gvac_db_version' ) === GVAC_DB_VERSION ) {
		return;
	}
	gvac_install();
	update_option( 'gvac_db_version', GVAC_DB_VERSION );
}

function gvac_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta( 'CREATE TABLE ' . gvac_table( 'log' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type varchar(12) NOT NULL DEFAULT 'change',
  action varchar(60) NOT NULL DEFAULT '',
  subject varchar(120) NOT NULL DEFAULT '',
  details text NULL,
  ip varchar(45) NOT NULL DEFAULT '',
  url varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY type_date (type,created_at),
  KEY user_id (user_id)
) $c;" );

	// مدیر فعلی (و مدیری که ایمیل سایت مال اوست) به‌صورت پیش‌فرض «مصون» می‌شوند
	if ( false === get_option( 'gvac_immune', false ) ) {
		$ids = array();
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			$ids[] = get_current_user_id();
		}
		$owner = get_user_by( 'email', get_option( 'admin_email' ) );
		if ( $owner && user_can( $owner, 'manage_options' ) ) {
			$ids[] = (int) $owner->ID;
		}
		if ( ! $ids ) {
			$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
			$ids    = array_map( 'intval', $admins );
		}
		update_option( 'gvac_immune', array_values( array_unique( $ids ) ), false );
	}

	$s = gvac_opt();
	if ( empty( $s['recovery_key'] ) ) {
		$s['recovery_key'] = wp_generate_password( 32, false );
		update_option( 'gvac_settings', $s, true );
	}
}

/* ------------------------------------------------------------------ */
/* کرون: پاکسازی لاگ + انقضای نقش موقت                                  */
/* ------------------------------------------------------------------ */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'gvac_hourly' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'gvac_hourly' );
	}
} );

add_action( 'gvac_hourly', function () {
	global $wpdb;
	$days = max( 7, (int) gvac_opt( 'log_days' ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . gvac_table( 'log' ) . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ) );
	if ( function_exists( 'gvac_expire_temp_roles' ) ) {
		gvac_expire_temp_roles();
	}
} );

/** آخرین ورود کاربر (برای نمایش در لیست کاربران) */
add_action( 'wp_login', function ( $login, $user ) {
	update_user_meta( $user->ID, 'gvac_last_login', time() );
}, 10, 2 );
