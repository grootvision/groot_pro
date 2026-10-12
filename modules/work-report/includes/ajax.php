<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * همه‌ی درخواست‌های AJAX از یک نقطه وارد می‌شوند: action=gvwr&op=...
 * هر op دسترسی خودش را چک می‌کند. برای افزودن قابلیت جدید، یک op به $map اضافه کنید.
 */
add_action( 'wp_ajax_gvwr', 'gvwr_ajax' );

function gvwr_fail( $msg, $code = 400 ) {
	wp_send_json_error( array( 'msg' => $msg ), $code );
}

function gvwr_need( $what ) {
	$ok = 'manage' === $what ? gvwr_can_manage() : ( 'client' === $what ? gvwr_can_client() : gvwr_can_submit() );
	if ( ! $ok ) {
		gvwr_fail( 'دسترسی ندارید.', 403 );
	}
}

function gvwr_post_json( $key ) {
	$d = isset( $_POST[ $key ] ) ? json_decode( wp_unslash( $_POST[ $key ] ), true ) : null; // phpcs:ignore
	return is_array( $d ) ? $d : array();
}

function gvwr_ajax() {
	check_ajax_referer( 'gvwr', 'nonce' );
	$op  = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	$map = array(
		'view'          => 'gvwr_op_view',
		'save_items'    => 'gvwr_op_save_items',
		'item_form'     => 'gvwr_op_item_form',
		'update_item'   => 'gvwr_op_update_item',
		'delete_item'   => 'gvwr_op_delete_item',
		'set_status'    => 'gvwr_op_set_status',
		'reject_form'   => 'gvwr_op_reject_form',
		'day_items'     => 'gvwr_op_day_items',
		'upload'        => 'gvwr_op_upload',
		'kw_add'        => 'gvwr_op_kw_add',
		'kw_import'     => 'gvwr_op_kw_import',
		'kw_delete'     => 'gvwr_op_kw_delete',
		'kw_history'    => 'gvwr_op_kw_history',
		'save_ranks'    => 'gvwr_op_save_ranks',
		'person_add'    => 'gvwr_op_person_add',
		'person_remove' => 'gvwr_op_person_remove',
		'emp_update'    => 'gvwr_op_emp_update',
		'cat_save'      => 'gvwr_op_cat_save',
		'cat_delete'    => 'gvwr_op_cat_delete',
		'settings_save' => 'gvwr_op_settings_save',
		'make_page'     => 'gvwr_op_make_page',
		'proj_save'     => 'gvwr_op_proj_save',
		'proj_delete'   => 'gvwr_op_proj_delete',
	);
	if ( ! isset( $map[ $op ] ) ) {
		gvwr_fail( 'درخواست نامعتبر است.' );
	}
	call_user_func( $map[ $op ] );
	wp_send_json_success();
}

/* ---------------------------- نمایش صفحه‌ها ---------------------------- */

function gvwr_op_view() {
	$views = array(
		'emp_form'     => array( 'submit', 'gvwr_view_emp_form' ),
		'my_reports'   => array( 'submit', 'gvwr_view_my_reports' ),
		'calendar'     => array( 'submit', 'gvwr_view_calendar' ),
		'keywords'     => array( 'submit', 'gvwr_view_keywords' ),
		'mgr_overview' => array( 'manage', 'gvwr_view_mgr_overview' ),
		'mgr_reports'  => array( 'manage', 'gvwr_view_mgr_reports' ),
		'mgr_compare'  => array( 'manage', 'gvwr_view_mgr_compare' ),
		'people'       => array( 'manage', 'gvwr_view_people' ),
		'categories'   => array( 'manage', 'gvwr_view_categories' ),
		'settings'     => array( 'manage', 'gvwr_view_settings' ),
		'client'       => array( 'client', 'gvwr_view_client' ),
	);
	$v = isset( $_POST['view'] ) ? sanitize_key( $_POST['view'] ) : '';
	if ( ! isset( $views[ $v ] ) ) {
		gvwr_fail( 'صفحه پیدا نشد.' );
	}
	gvwr_need( $views[ $v ][0] );
	$st = gvwr_state();
	if ( 'my_reports' === $v && ! isset( $_POST['status'] ) && isset( $_POST['init_status'] ) ) {
		$st['status'] = sanitize_key( $_POST['init_status'] );
	}
	wp_send_json_success( call_user_func( $views[ $v ][1], $st ) );
}

/* ------------------------------ گزارش‌ها ------------------------------ */

function gvwr_op_save_items() {
	gvwr_need( 'submit' );
	$rows = gvwr_post_json( 'items' );
	if ( ! $rows ) {
		gvwr_fail( 'هیچ ردیفی برای ثبت وجود ندارد.' );
	}
	$date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : gvwr_today();
	$proj = isset( $_POST['project'] ) ? (int) $_POST['project'] : 0;
	$n    = gvwr_insert_items( array_slice( $rows, 0, 60 ), $date, $proj, get_current_user_id() );
	if ( is_wp_error( $n ) ) {
		gvwr_fail( $n->get_error_message() );
	}
	wp_send_json_success( array( 'count' => $n, 'msg' => gvwr_fa( $n ) . ' گزارش ثبت شد.' ) );
}

function gvwr_op_item_form() {
	gvwr_need( 'submit' );
	$it = gvwr_item( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
	if ( ! $it || ( ! gvwr_can_manage() && (int) $it->user_id !== get_current_user_id() ) ) {
		gvwr_fail( 'گزارش پیدا نشد.' );
	}
	$h  = '<h3>ویرایش گزارش</h3><div class="gvwr-editrow" data-id="' . (int) $it->id . '">';
	$h .= '<label>تاریخ <input type="date" data-k="date" value="' . esc_attr( $it->work_date ) . '" max="' . esc_attr( gvwr_today() ) . '"></label>';
	$h .= gvwr_row_html( $it ) . '</div>';
	$h .= '<div class="gvwr-formfoot"><button type="button" class="gvwr-btn primary" data-act="item-update">ذخیره‌ی تغییرات</button></div>';
	wp_send_json_success( array( 'html' => $h ) );
}

function gvwr_op_update_item() {
	gvwr_need( 'submit' );
	$d = gvwr_post_json( 'item' );
	$r = gvwr_update_item( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0, $d, get_current_user_id() );
	if ( is_wp_error( $r ) ) {
		gvwr_fail( $r->get_error_message() );
	}
	wp_send_json_success( array( 'msg' => 'تغییرات ذخیره شد.' ) );
}

function gvwr_op_delete_item() {
	gvwr_need( 'submit' );
	$r = gvwr_delete_item( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0, get_current_user_id() );
	if ( is_wp_error( $r ) ) {
		gvwr_fail( $r->get_error_message() );
	}
	wp_send_json_success( array( 'msg' => 'حذف شد.' ) );
}

function gvwr_op_set_status() {
	gvwr_need( 'manage' );
	$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
	$st  = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
	$n   = gvwr_set_status( $ids, $st, isset( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : '' ); // phpcs:ignore
	wp_send_json_success( array( 'msg' => gvwr_fa( $n ) . ' گزارش به‌روز شد.' ) );
}

function gvwr_op_reject_form() {
	gvwr_need( 'manage' );
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$h  = '<h3>رد گزارش</h3><p class="gvwr-muted">دلیل را بنویسید تا کارمند بتواند اصلاح کند.</p>';
	$h .= '<textarea id="gvwr-reject-note" rows="3" class="gvwr-full" placeholder="مثلاً: لینک صفحه ناقص است."></textarea>';
	$h .= '<div class="gvwr-formfoot"><button type="button" class="gvwr-btn danger" data-act="reject-do" data-id="' . $id . '">رد گزارش</button></div>';
	wp_send_json_success( array( 'html' => $h ) );
}

function gvwr_op_day_items() {
	gvwr_need( 'submit' );
	$d = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
	if ( ! gvwr_valid_date( $d ) ) {
		gvwr_fail( 'تاریخ نامعتبر است.' );
	}
	wp_send_json_success( array( 'html' => gvwr_day_items_html( $d, isset( $_POST['user'] ) ? (int) $_POST['user'] : 0 ) ) );
}

/** آپلود اسکرین‌شات (کارمندان بدون دسترسی به کتابخانه‌ی رسانه هم می‌توانند) */
function gvwr_op_upload() {
	gvwr_need( 'submit' );
	if ( empty( $_FILES['file'] ) ) {
		gvwr_fail( 'فایلی دریافت نشد.' );
	}
	$check = wp_check_filetype_and_ext( $_FILES['file']['tmp_name'], $_FILES['file']['name'] ); // phpcs:ignore
	if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) ) {
		gvwr_fail( 'فقط فایل تصویر مجاز است.' );
	}
	if ( $_FILES['file']['size'] > 8 * MB_IN_BYTES ) { // phpcs:ignore
		gvwr_fail( 'حجم تصویر بیش از ۸ مگابایت است.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$id = media_handle_upload( 'file', 0, array( 'post_title' => 'گزارش کار — اسکرین‌شات' ), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' ) ) );
	if ( is_wp_error( $id ) ) {
		gvwr_fail( $id->get_error_message() );
	}
	wp_send_json_success( array( 'id' => $id, 'url' => wp_get_attachment_image_url( $id, 'thumbnail' ) ) );
}

/* --------------------------- کلمات کلیدی و رتبه --------------------------- */

function gvwr_can_add_keywords() {
	return gvwr_can_manage() || gvwr_opt( 'employees_add_keywords' );
}

function gvwr_op_kw_add() {
	gvwr_need( 'submit' );
	if ( ! gvwr_can_add_keywords() ) {
		gvwr_fail( 'افزودن کلمه فقط برای مدیر مجاز است.', 403 );
	}
	$id = gvwr_kw_add( isset( $_POST['keyword'] ) ? wp_unslash( $_POST['keyword'] ) : '', isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : '', isset( $_POST['project'] ) ? (int) $_POST['project'] : 0 ); // phpcs:ignore
	if ( ! $id ) {
		gvwr_fail( 'کلمه خالی یا تکراری است.' );
	}
	wp_send_json_success( array( 'msg' => 'کلمه اضافه شد.' ) );
}

function gvwr_op_kw_import() {
	gvwr_need( 'submit' );
	if ( ! gvwr_can_add_keywords() ) {
		gvwr_fail( 'دسترسی ندارید.', 403 );
	}
	if ( empty( $_FILES['file']['tmp_name'] ) ) {
		gvwr_fail( 'فایلی انتخاب نشده است.' );
	}
	$ext = strtolower( pathinfo( $_FILES['file']['name'], PATHINFO_EXTENSION ) ); // phpcs:ignore
	if ( ! in_array( $ext, array( 'xlsx', 'csv', 'txt' ), true ) ) {
		gvwr_fail( 'فقط فایل xlsx یا csv مجاز است.' );
	}
	$r = gvwr_import_keywords( $_FILES['file']['tmp_name'], $_FILES['file']['name'], isset( $_POST['project'] ) ? (int) $_POST['project'] : 0 ); // phpcs:ignore
	if ( is_wp_error( $r ) ) {
		gvwr_fail( $r->get_error_message() );
	}
	wp_send_json_success( array( 'msg' => gvwr_fa( $r['added'] ) . ' کلمه اضافه شد' . ( $r['dupe'] ? ' (' . gvwr_fa( $r['dupe'] ) . ' تکراری رد شد)' : '' ) . '.' ) );
}

function gvwr_op_kw_delete() {
	gvwr_need( 'manage' );
	global $wpdb;
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$wpdb->delete( gvwr_table( 'keywords' ), array( 'id' => $id ) );
	$wpdb->delete( gvwr_table( 'ranks' ), array( 'keyword_id' => $id ) );
	wp_send_json_success( array( 'msg' => 'کلمه حذف شد.' ) );
}

function gvwr_op_kw_history() {
	gvwr_need( 'submit' );
	$r = gvwr_kw_history( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
	wp_send_json_success( $r );
}

function gvwr_op_save_ranks() {
	gvwr_need( 'submit' );
	$n = gvwr_save_ranks( gvwr_post_json( 'ranks' ), isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '', get_current_user_id() );
	if ( is_wp_error( $n ) ) {
		gvwr_fail( $n->get_error_message() );
	}
	if ( ! $n ) {
		gvwr_fail( 'هیچ رتبه‌ای وارد نشده است.' );
	}
	wp_send_json_success( array( 'msg' => gvwr_fa( $n ) . ' رتبه ثبت شد.' ) );
}

/* ----------------------------- افراد و نقش‌ها ---------------------------- */

function gvwr_op_person_add() {
	gvwr_need( 'manage' );
	global $wpdb;
	$role = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
	if ( ! array_key_exists( $role, gvwr_role_labels() ) ) {
		gvwr_fail( 'نقش نامعتبر است.' );
	}
	$uid = isset( $_POST['user'] ) ? (int) $_POST['user'] : 0;
	if ( ! $uid ) {
		$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : ''; // phpcs:ignore
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore
		if ( '' === $login || ! is_email( $email ) ) {
			gvwr_fail( 'یوزر موجود را انتخاب کنید یا نام کاربری و ایمیل معتبر بدهید.' );
		}
		$uid = wp_insert_user( array( 'user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password( 16 ), 'display_name' => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : $login, 'role' => $role ) ); // phpcs:ignore
		if ( is_wp_error( $uid ) ) {
			gvwr_fail( $uid->get_error_message() );
		}
		wp_new_user_notification( $uid, null, 'user' );
	}
	$u = get_userdata( $uid );
	if ( ! $u || in_array( 'administrator', (array) $u->roles, true ) ) {
		gvwr_fail( 'این کاربر قابل تغییر نیست.' );
	}
	foreach ( array_keys( gvwr_role_labels() ) as $r ) {
		$u->remove_role( $r );
	}
	$u->add_role( $role );
	if ( 'gvwr_employee' === $role ) {
		$T = gvwr_table( 'employees' );
		$wpdb->query( $wpdb->prepare( "INSERT INTO $T (user_id,job_title,active,created_at) VALUES (%d,%s,1,%s) ON DUPLICATE KEY UPDATE job_title=VALUES(job_title), active=1", $uid, isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '', current_time( 'mysql' ) ) ); // phpcs:ignore
	}
	wp_send_json_success( array( 'msg' => 'فرد اضافه شد.' ) );
}

function gvwr_op_person_remove() {
	gvwr_need( 'manage' );
	$u = get_userdata( isset( $_POST['user'] ) ? (int) $_POST['user'] : 0 );
	if ( $u && ! in_array( 'administrator', (array) $u->roles, true ) ) {
		foreach ( array_keys( gvwr_role_labels() ) as $r ) {
			$u->remove_role( $r );
		}
		if ( ! $u->roles ) {
			$u->add_role( 'subscriber' );
		}
	}
	wp_send_json_success( array( 'msg' => 'دسترسی گزارش‌کار حذف شد. گزارش‌های قبلی او حفظ می‌شود.' ) );
}

function gvwr_op_emp_update() {
	gvwr_need( 'manage' );
	global $wpdb;
	$data = array();
	if ( isset( $_POST['title'] ) ) {
		$data['job_title'] = sanitize_text_field( wp_unslash( $_POST['title'] ) ); // phpcs:ignore
	}
	if ( isset( $_POST['active'] ) ) {
		$data['active'] = (int) (bool) $_POST['active'];
	}
	if ( $data ) {
		$wpdb->update( gvwr_table( 'employees' ), $data, array( 'user_id' => (int) $_POST['user'] ) ); // phpcs:ignore
	}
	wp_send_json_success( array( 'msg' => 'ذخیره شد.' ) );
}

/* ------------------------- دسته‌بندی، تنظیمات، پروژه ------------------------ */

function gvwr_op_cat_save() {
	gvwr_need( 'manage' );
	global $wpdb;
	$d    = gvwr_post_json( 'cat' );
	$name = isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : '';
	if ( '' === $name ) {
		gvwr_fail( 'نام دسته‌بندی را بنویسید.' );
	}
	$row = array(
		'name'       => $name,
		'grp'        => isset( $d['grp'] ) ? sanitize_text_field( $d['grp'] ) : '',
		'icon'       => isset( $d['icon'] ) ? mb_substr( sanitize_text_field( $d['icon'] ), 0, 4 ) : '',
		'color'      => isset( $d['color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $d['color'] ) ? $d['color'] : '#0f766e',
		'sort_order' => isset( $d['sort'] ) ? (int) $d['sort'] : 0,
		'active'     => empty( $d['active'] ) ? 0 : 1,
	);
	$id = isset( $d['id'] ) ? (int) $d['id'] : 0;
	if ( $id ) {
		$wpdb->update( gvwr_table( 'categories' ), $row, array( 'id' => $id ) );
	} else {
		$wpdb->insert( gvwr_table( 'categories' ), $row );
	}
	wp_send_json_success( array( 'msg' => 'ذخیره شد.', 'reload' => true ) );
}

function gvwr_op_cat_delete() {
	gvwr_need( 'manage' );
	global $wpdb;
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$n  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . gvwr_table( 'items' ) . ' WHERE category_id=%d', $id ) );
	if ( $n ) {
		$wpdb->update( gvwr_table( 'categories' ), array( 'active' => 0 ), array( 'id' => $id ) );
		wp_send_json_success( array( 'msg' => 'این دسته گزارش دارد؛ به‌جای حذف، غیرفعال شد.', 'reload' => true ) );
	}
	$wpdb->delete( gvwr_table( 'categories' ), array( 'id' => $id ) );
	wp_send_json_success( array( 'msg' => 'حذف شد.', 'reload' => true ) );
}

function gvwr_op_settings_save() {
	gvwr_need( 'manage' );
	$in  = gvwr_post_json( 'settings' );
	$def = gvwr_defaults();
	$o   = get_option( 'gvwr_settings', array() );
	foreach ( $def as $k => $v ) {
		if ( 'client_page_id' === $k || ! array_key_exists( $k, $in ) ) {
			continue;
		}
		if ( 'calendar' === $k ) {
			$o[ $k ] = 'gregorian' === $in[ $k ] ? 'gregorian' : 'jalali';
		} elseif ( is_int( $v ) ) {
			$o[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		} else {
			$o[ $k ] = sanitize_text_field( $in[ $k ] );
		}
	}
	update_option( 'gvwr_settings', $o );
	wp_send_json_success( array( 'msg' => 'تنظیمات ذخیره شد.' ) );
}

function gvwr_op_make_page() {
	gvwr_need( 'manage' );
	$id = gvwr_create_client_page();
	if ( ! $id ) {
		gvwr_fail( 'ساخت برگه ناموفق بود.' );
	}
	wp_send_json_success( array( 'msg' => 'برگه ساخته شد.', 'reload' => true ) );
}

function gvwr_op_proj_save() {
	gvwr_need( 'manage' );
	global $wpdb;
	$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : ''; // phpcs:ignore
	if ( '' === $name ) {
		gvwr_fail( 'نام را بنویسید.' );
	}
	if ( $id ) {
		$wpdb->update( gvwr_table( 'projects' ), array( 'name' => $name ), array( 'id' => $id ) );
	} else {
		$wpdb->insert( gvwr_table( 'projects' ), array( 'name' => $name, 'is_default' => 0 ) );
	}
	wp_send_json_success( array( 'msg' => 'ذخیره شد.', 'reload' => true ) );
}

function gvwr_op_proj_delete() {
	gvwr_need( 'manage' );
	global $wpdb;
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$n  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . gvwr_table( 'items' ) . ' WHERE project_id=%d', $id ) );
	if ( $n || $id === gvwr_default_project_id() ) {
		gvwr_fail( 'این پروژه گزارش دارد یا پروژه‌ی پیش‌فرض است و حذف نمی‌شود.' );
	}
	$wpdb->delete( gvwr_table( 'projects' ), array( 'id' => $id ) );
	wp_send_json_success( array( 'msg' => 'حذف شد.', 'reload' => true ) );
}
