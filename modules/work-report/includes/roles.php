<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * نقش‌ها:
 *  gvwr_employee — کارمند: فقط گزارش‌های خودش را می‌بیند
 *  gvwr_manager  — مدیر گزارش کار (مدیر شرکت): همه‌ی گزارش‌ها، تأیید، مقایسه، تنظیمات
 *  gvwr_client   — کارفرما: فقط صفحه‌ی گزارش (شورت‌کد)
 * مدیر کل وردپرس (administrator) به‌صورت خودکار همه‌ی دسترسی‌ها را دارد.
 */
function gvwr_roles_install() {
	add_role( 'gvwr_employee', 'کارمند (گزارش کار)', array( 'read' => true, 'gvwr_submit' => true ) );
	add_role( 'gvwr_manager', 'مدیر گزارش کار', array( 'read' => true, 'gvwr_submit' => true, 'gvwr_manage' => true, 'gvwr_view_client' => true ) );
	add_role( 'gvwr_client', 'کارفرما (مشاهده‌ی گزارش)', array( 'read' => true, 'gvwr_view_client' => true ) );

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( array( 'gvwr_submit', 'gvwr_manage', 'gvwr_view_client' ) as $cap ) {
			$admin->add_cap( $cap );
		}
	}
}

function gvwr_role_labels() {
	return array(
		'gvwr_employee' => 'کارمند',
		'gvwr_manager'  => 'مدیر گزارش کار',
		'gvwr_client'   => 'کارفرما',
	);
}

/** نقش گزارش‌کاری کاربر (اولین مورد) */
function gvwr_user_role( $user ) {
	foreach ( array_keys( gvwr_role_labels() ) as $r ) {
		if ( in_array( $r, (array) $user->roles, true ) ) {
			return $r;
		}
	}
	return '';
}

/** کاربری که فقط «کارفرما» است (برای مسدود کردن wp-admin) */
function gvwr_is_client_only( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	return in_array( 'gvwr_client', (array) $user->roles, true ) && count( $user->roles ) === 1;
}
