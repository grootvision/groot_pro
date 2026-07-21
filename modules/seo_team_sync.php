<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — همگام‌سازی چندسایتی کارکرد تیم سئو
 *  ------------------------------------------------------------
 *  این ماژول کاملاً اختیاری و مستقل از منطق اصلی «گزارش سئوی مشتری»
 *  است و از طریق اکشن‌هوک‌های gv_sr_after_save_timelog و
 *  gv_sr_team_tab_extra به آن متصل می‌شود؛ اگر غیرفعال باشد هیچ
 *  تأثیری روی عملکرد فعلی افزونه ندارد.
 *
 *  هر سایتی که این افزونه را نصب کند می‌تواند مستقل تصمیم بگیرد:
 *   - «سایت مبدأ (Node)»: کارکرد کارمندانش را به یک هاب مرکزیِ
 *     دلخواه (هر آدرسی، حتی متعلق به یک شخص دیگر) ارسال کند.
 *   - «هاب مرکزی (Hub)»: از سایت‌های مبدأ دیگر داده دریافت کند و
 *     گزارش یکپارچه‌ی همه‌ی سایت‌ها + حقوق ترکیبی را نمایش دهد.
 *   - هر دو، یا هیچ‌کدام (پیش‌فرض: غیرفعال / کاملاً مستقل).
 *  تطبیق کارمند بین سایت‌های مختلف با «کد کارمندی مشترک» (global_code
 *  در جدول gv_sr_employees) انجام می‌شود.
 * ==========================================================
 */

define( 'GV_SYNC_DB_VERSION', '1.0' );
define( 'GV_SYNC_NONCE',      'gv_sync_nonce_action' );
define( 'GV_SYNC_REST_NS',    'gv-sr/v1' );

/* ==========================================================================
   ۰) نصب جداول دیتابیس مخصوص هاب (فارغ از این‌که این سایت هاب باشد یا نه؛
      بی‌ضرر و سبک است و در صورت فعال‌سازی بعدیِ حالت هاب، آماده است)
   ========================================================================== */
add_action( 'plugins_loaded', 'gv_sync_maybe_install_db' );
function gv_sync_maybe_install_db() {
	if ( get_option( 'gv_sync_db_version' ) === GV_SYNC_DB_VERSION ) { return; }

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$t_nodes = $wpdb->prefix . 'gv_sync_hub_nodes';
	dbDelta( "CREATE TABLE {$t_nodes} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		label VARCHAR(191) NOT NULL,
		api_key VARCHAR(64) NOT NULL,
		active TINYINT UNSIGNED NOT NULL DEFAULT 1,
		last_seen_at DATETIME NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY api_key (api_key)
	) {$charset_collate};" );

	$t_logs = $wpdb->prefix . 'gv_sync_remote_logs';
	dbDelta( "CREATE TABLE {$t_logs} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		node_id BIGINT UNSIGNED NOT NULL,
		site_label VARCHAR(191) NOT NULL,
		emp_code VARCHAR(40) NOT NULL,
		emp_name VARCHAR(191) NOT NULL,
		work_date DATE NOT NULL,
		entry_mode VARCHAR(10) NOT NULL DEFAULT 'manual',
		start_time VARCHAR(5) NULL,
		end_time VARCHAR(5) NULL,
		hours DECIMAL(5,2) NOT NULL DEFAULT 0,
		client_name VARCHAR(191) NULL,
		note VARCHAR(500) NULL,
		synced_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY node_id (node_id),
		KEY emp_code (emp_code),
		KEY work_date (work_date)
	) {$charset_collate};" );

	$t_rates = $wpdb->prefix . 'gv_sync_hub_rates';
	dbDelta( "CREATE TABLE {$t_rates} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		emp_code VARCHAR(40) NOT NULL,
		employee_name VARCHAR(191) NULL,
		hourly_rate BIGINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY emp_code (emp_code)
	) {$charset_collate};" );

	update_option( 'gv_sync_db_version', GV_SYNC_DB_VERSION );
}

/* ==========================================================================
   ۱) تنظیمات «سایت مبدأ» (Node) — این سایت داده‌اش را کجا بفرستد
   ========================================================================== */
function gv_sync_get_node_settings() {
	$defaults = array(
		'enabled'           => 0,
		'hub_endpoint'      => '',
		'api_key'           => '',
		'site_label'        => get_bloginfo( 'name' ),
		'last_sync_at'      => '',
		'last_sync_status'  => '',
		'last_sync_message' => '',
	);
	$saved = get_option( 'gv_sync_node_settings', array() );
	if ( ! is_array( $saved ) ) { $saved = array(); }
	return wp_parse_args( $saved, $defaults );
}
function gv_sync_save_node_settings( $data ) {
	$current = gv_sync_get_node_settings();
	$current['enabled']      = empty( $data['enabled'] ) ? 0 : 1;
	$current['hub_endpoint'] = esc_url_raw( trim( (string) $data['hub_endpoint'] ) );
	$current['api_key']      = sanitize_text_field( trim( (string) $data['api_key'] ) );
	$current['site_label']   = sanitize_text_field( trim( (string) $data['site_label'] ) ) ?: get_bloginfo( 'name' );
	update_option( 'gv_sync_node_settings', $current );
}
function gv_sync_update_node_sync_result( $status, $message = '' ) {
	$current                     = gv_sync_get_node_settings();
	$current['last_sync_at']     = current_time( 'mysql' );
	$current['last_sync_status'] = $status;
	$current['last_sync_message'] = sanitize_text_field( $message );
	update_option( 'gv_sync_node_settings', $current );
}

/* ==========================================================================
   ۲) تنظیمات «هاب مرکزی» — مدیریت سایت‌های مبدأ مجاز و نرخ ساعتی ترکیبی
   ========================================================================== */
function gv_sync_hub_is_enabled() {
	return (bool) get_option( 'gv_sync_hub_enabled', 0 );
}
function gv_sync_get_hub_nodes() {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sync_hub_nodes';
	return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY created_at DESC" ); // phpcs:ignore
}
function gv_sync_get_hub_node_by_key( $api_key ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sync_hub_nodes';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE api_key = %s AND active = 1", $api_key ) ); // phpcs:ignore
}
function gv_sync_add_hub_node( $label ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sync_hub_nodes';
	$api_key = wp_generate_password( 40, false, false );
	$wpdb->insert( $t, array( // phpcs:ignore
		'label'      => sanitize_text_field( $label ),
		'api_key'    => $api_key,
		'active'     => 1,
		'created_at' => current_time( 'mysql' ),
	) );
	return $api_key;
}
function gv_sync_toggle_hub_node( $node_id, $active ) {
	global $wpdb;
	$wpdb->update( $wpdb->prefix . 'gv_sync_hub_nodes', array( 'active' => $active ? 1 : 0 ), array( 'id' => (int) $node_id ) ); // phpcs:ignore
}
function gv_sync_delete_hub_node( $node_id ) {
	global $wpdb;
	$node_id = (int) $node_id;
	$wpdb->delete( $wpdb->prefix . 'gv_sync_hub_nodes', array( 'id' => $node_id ) ); // phpcs:ignore
	$wpdb->delete( $wpdb->prefix . 'gv_sync_remote_logs', array( 'node_id' => $node_id ) ); // phpcs:ignore
}

/** نرخ ساعتی ترکیبی هاب برای یک کد کارمندی (مستقل از نرخ محلی هر سایت) */
function gv_sync_get_hub_rate( $emp_code ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sync_hub_rates';
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE emp_code = %s", $emp_code ) ); // phpcs:ignore
	return $row ? (int) $row->hourly_rate : 0;
}
function gv_sync_save_hub_rate( $emp_code, $employee_name, $rate ) {
	global $wpdb;
	$t   = $wpdb->prefix . 'gv_sync_hub_rates';
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$t} WHERE emp_code = %s", $emp_code ) ); // phpcs:ignore
	if ( $row ) {
		$wpdb->update( $t, array( 'hourly_rate' => max( 0, (int) $rate ), 'employee_name' => $employee_name ), array( 'id' => $row->id ) ); // phpcs:ignore
	} else {
		$wpdb->insert( $t, array( // phpcs:ignore
			'emp_code'      => $emp_code,
			'employee_name' => $employee_name,
			'hourly_rate'   => max( 0, (int) $rate ),
		) );
	}
}

/* ==========================================================================
   ۳) رکوردهای دریافتی از سایت‌های مبدأ + تجمیع محلی/راه‌دور
   ========================================================================== */
function gv_sync_get_remote_logs( $emp_code = '', $date_from = '', $date_to = '' ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sync_remote_logs';

	$where  = array( '1=1' );
	$params = array();
	if ( '' !== $emp_code ) { $where[] = 'emp_code = %s'; $params[] = $emp_code; }
	if ( '' !== $date_from ) { $where[] = 'work_date >= %s'; $params[] = $date_from; }
	if ( '' !== $date_to )   { $where[] = 'work_date <= %s'; $params[] = $date_to; }

	$sql = "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . ' ORDER BY work_date ASC';
	return ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore
}

/**
 * فهرست یکتای کدهای کارمندی که در هاب دیده شده‌اند (چه محلی، چه از سایت‌های دیگر)
 * به همراه نام نمایشی هرکدام.
 */
function gv_sync_get_all_known_employees( $date_from, $date_to ) {
	$out = array(); // emp_code => name

	// کارمندان محلی همین سایت (اگر هاب خودش هم کارمند دارد)
	foreach ( gv_sr_get_employees() as $e ) {
		if ( ! empty( $e->global_code ) ) { $out[ $e->global_code ] = $e->name; }
	}

	// کارمندان دیده‌شده در رکوردهای دریافتی از سایت‌های دیگر
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sync_remote_logs';
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT emp_code, emp_name FROM {$t} WHERE work_date BETWEEN %s AND %s", $date_from, $date_to ) ); // phpcs:ignore
	foreach ( $rows as $r ) {
		if ( ! isset( $out[ $r->emp_code ] ) ) { $out[ $r->emp_code ] = $r->emp_name; }
	}
	return $out;
}

/** جمع ساعت یک کارمند (بر اساس کد مشترک) در همه‌ی سایت‌ها (محلی + راه‌دور)، به‌همراه ریز به تفکیک سایت */
function gv_sync_employee_combined_hours( $emp_code, $date_from, $date_to ) {
	$per_site = array(); // site_label => hours
	$total    = 0.0;

	$local_emp = gv_sr_get_employee_by_code( $emp_code );
	if ( $local_emp ) {
		$local_hours = gv_sr_employee_total_hours( $local_emp->id, $date_from, $date_to );
		if ( $local_hours > 0 ) {
			$per_site[ 'این سایت (' . get_bloginfo( 'name' ) . ')' ] = $local_hours;
			$total += $local_hours;
		}
	}

	global $wpdb;
	$t = $wpdb->prefix . 'gv_sync_remote_logs';
	$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
		"SELECT site_label, SUM(hours) AS h FROM {$t} WHERE emp_code = %s AND work_date BETWEEN %s AND %s GROUP BY site_label",
		$emp_code, $date_from, $date_to
	) );
	foreach ( $rows as $r ) {
		$per_site[ $r->site_label ] = ( isset( $per_site[ $r->site_label ] ) ? $per_site[ $r->site_label ] : 0 ) + (float) $r->h;
		$total += (float) $r->h;
	}

	return array( 'total' => round( $total, 2 ), 'per_site' => $per_site );
}

/* ==========================================================================
   ۴) ارسال خودکار از سایت مبدأ به هاب (اسنپ‌شاتِ کامل هر کارمند)
   ========================================================================== */
add_action( 'gv_sr_after_save_timelog', 'gv_sync_schedule_push' );
function gv_sync_schedule_push( $employee_id ) {
	$settings = gv_sync_get_node_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['hub_endpoint'] ) || empty( $settings['api_key'] ) ) { return; }
	// ارسال را به انتهای اجرای درخواست موکول می‌کنیم تا کارمند منتظر پاسخ شبکه نماند.
	add_action( 'shutdown', function () use ( $employee_id ) {
		gv_sync_push_employee_snapshot( $employee_id );
	} );
}

function gv_sync_push_employee_snapshot( $employee_id ) {
	$settings = gv_sync_get_node_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['hub_endpoint'] ) || empty( $settings['api_key'] ) ) { return false; }

	$emp = gv_sr_get_employee( $employee_id );
	if ( ! $emp || empty( $emp->global_code ) ) { return false; }

	$window_from = gmdate( 'Y-m-d', strtotime( '-120 days' ) );
	$window_to   = gmdate( 'Y-m-d', strtotime( '+7 days' ) );
	$logs        = gv_sr_get_timelogs( array( 'employee_id' => $employee_id, 'date_from' => $window_from, 'date_to' => $window_to ) );

	$payload_logs = array();
	foreach ( $logs as $l ) {
		$payload_logs[] = array(
			'work_date'   => $l->work_date,
			'entry_mode'  => $l->entry_mode,
			'start_time'  => $l->start_time,
			'end_time'    => $l->end_time,
			'hours'       => (float) $l->hours,
			'client_name' => $l->client_name,
			'note'        => $l->note,
		);
	}

	$body = array(
		'api_key'     => $settings['api_key'],
		'site_label'  => $settings['site_label'],
		'emp_code'    => $emp->global_code,
		'emp_name'    => $emp->name,
		'window_from' => $window_from,
		'window_to'   => $window_to,
		'logs'        => $payload_logs,
	);

	$response = wp_remote_post( $settings['hub_endpoint'], array(
		'timeout' => 12,
		'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body'    => wp_json_encode( $body ),
	) );

	if ( is_wp_error( $response ) ) {
		gv_sync_update_node_sync_result( 'error', $response->get_error_message() );
		return false;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( $code >= 200 && $code < 300 ) {
		gv_sync_update_node_sync_result( 'ok', 'آخرین ارسال موفق بود.' );
		return true;
	}
	gv_sync_update_node_sync_result( 'error', 'هاب پاسخ ' . $code . ' برگرداند: ' . wp_remote_retrieve_body( $response ) );
	return false;
}

/** ارسال دستی/فوریِ همه‌ی کارمندان شناخته‌شده‌ی محلی (برای دکمه «همگام‌سازی الان») */
function gv_sync_push_all_local_employees() {
	$ok = 0; $fail = 0;
	foreach ( gv_sr_get_employees( true ) as $e ) {
		if ( empty( $e->global_code ) ) { continue; }
		if ( gv_sync_push_employee_snapshot( $e->id ) ) { $ok++; } else { $fail++; }
	}
	return array( 'ok' => $ok, 'fail' => $fail );
}

/* ==========================================================================
   ۵) زمان‌بندی همگام‌سازی خودکار (Cron) — به‌عنوان پشتیبان مطمئن
   ========================================================================== */
add_action( 'init', 'gv_sync_maybe_schedule_cron' );
function gv_sync_maybe_schedule_cron() {
	$settings    = gv_sync_get_node_settings();
	$should_run  = ! empty( $settings['enabled'] );
	$is_scheduled = wp_next_scheduled( 'gv_sync_cron_push' );

	if ( $should_run && ! $is_scheduled ) {
		wp_schedule_event( time() + 300, 'twicedaily', 'gv_sync_cron_push' );
	} elseif ( ! $should_run && $is_scheduled ) {
		wp_clear_scheduled_hook( 'gv_sync_cron_push' );
	}
}
add_action( 'gv_sync_cron_push', 'gv_sync_push_all_local_employees' );

/* ==========================================================================
   ۶) دریافت‌کننده (سمت هاب) — REST API
   ========================================================================== */
add_action( 'rest_api_init', 'gv_sync_register_rest_route' );
function gv_sync_register_rest_route() {
	register_rest_route( GV_SYNC_REST_NS, '/sync', array(
		'methods'             => 'POST',
		'callback'            => 'gv_sync_handle_incoming',
		'permission_callback' => '__return_true', // احراز هویت با api_key داخل بدنه‌ی درخواست انجام می‌شود
	) );
}
function gv_sync_handle_incoming( WP_REST_Request $request ) {
	if ( ! gv_sync_hub_is_enabled() ) {
		return new WP_REST_Response( array( 'success' => false, 'message' => 'این سایت به‌عنوان هاب مرکزی فعال نیست.' ), 403 );
	}

	$data = $request->get_json_params();
	if ( ! is_array( $data ) || empty( $data['api_key'] ) ) {
		return new WP_REST_Response( array( 'success' => false, 'message' => 'داده نامعتبر است.' ), 400 );
	}

	$node = gv_sync_get_hub_node_by_key( sanitize_text_field( $data['api_key'] ) );
	if ( ! $node ) {
		return new WP_REST_Response( array( 'success' => false, 'message' => 'کلید API نامعتبر یا غیرفعال است.' ), 401 );
	}

	$emp_code    = isset( $data['emp_code'] ) ? gv_sr_sanitize_employee_code( $data['emp_code'] ) : '';
	$emp_name    = isset( $data['emp_name'] ) ? sanitize_text_field( $data['emp_name'] ) : '';
	$site_label  = isset( $data['site_label'] ) ? sanitize_text_field( $data['site_label'] ) : $node->label;
	$window_from = isset( $data['window_from'] ) ? sanitize_text_field( $data['window_from'] ) : gmdate( 'Y-m-d', strtotime( '-120 days' ) );
	$window_to   = isset( $data['window_to'] ) ? sanitize_text_field( $data['window_to'] ) : gmdate( 'Y-m-d', strtotime( '+7 days' ) );
	$logs        = isset( $data['logs'] ) && is_array( $data['logs'] ) ? $data['logs'] : array();

	if ( '' === $emp_code ) {
		return new WP_REST_Response( array( 'success' => false, 'message' => 'کد کارمندی ارسال نشده است.' ), 400 );
	}

	global $wpdb;
	$t = $wpdb->prefix . 'gv_sync_remote_logs';

	// جایگزینی کامل بازه‌ی ارسالی برای این کارمند از این سایت (خوداصلاح‌گر در برابر ویرایش/حذف در مبدأ)
	$wpdb->query( $wpdb->prepare( // phpcs:ignore
		"DELETE FROM {$t} WHERE node_id = %d AND emp_code = %s AND work_date BETWEEN %s AND %s",
		$node->id, $emp_code, $window_from, $window_to
	) );

	$inserted = 0;
	foreach ( $logs as $l ) {
		if ( empty( $l['work_date'] ) ) { continue; }
		$wpdb->insert( $t, array( // phpcs:ignore
			'node_id'     => $node->id,
			'site_label'  => $site_label,
			'emp_code'    => $emp_code,
			'emp_name'    => $emp_name,
			'work_date'   => sanitize_text_field( $l['work_date'] ),
			'entry_mode'  => isset( $l['entry_mode'] ) && 'range' === $l['entry_mode'] ? 'range' : 'manual',
			'start_time'  => isset( $l['start_time'] ) ? sanitize_text_field( $l['start_time'] ) : null,
			'end_time'    => isset( $l['end_time'] ) ? sanitize_text_field( $l['end_time'] ) : null,
			'hours'       => isset( $l['hours'] ) ? (float) $l['hours'] : 0,
			'client_name' => isset( $l['client_name'] ) ? sanitize_text_field( $l['client_name'] ) : '',
			'note'        => isset( $l['note'] ) ? sanitize_text_field( $l['note'] ) : '',
			'synced_at'   => current_time( 'mysql' ),
		) );
		$inserted++;
	}

	$wpdb->update( $wpdb->prefix . 'gv_sync_hub_nodes', array( 'last_seen_at' => current_time( 'mysql' ) ), array( 'id' => $node->id ) ); // phpcs:ignore

	return new WP_REST_Response( array( 'success' => true, 'inserted' => $inserted ), 200 );
}

/* ==========================================================================
   ۷) اکشن‌های ادمین (ذخیره تنظیمات، مدیریت نودها، نرخ ترکیبی)
   ========================================================================== */
add_action( 'admin_post_gv_sync_save_node_settings', 'gv_sync_handle_save_node_settings' );
function gv_sync_handle_save_node_settings() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SYNC_NONCE );

	gv_sync_save_node_settings( array(
		'enabled'      => isset( $_POST['enabled'] ) ? 1 : 0,
		'hub_endpoint' => isset( $_POST['hub_endpoint'] ) ? wp_unslash( $_POST['hub_endpoint'] ) : '',
		'api_key'      => isset( $_POST['api_key'] ) ? wp_unslash( $_POST['api_key'] ) : '',
		'site_label'   => isset( $_POST['site_label'] ) ? wp_unslash( $_POST['site_label'] ) : '',
	) );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&node_saved=1' ) );
	exit;
}

add_action( 'admin_post_gv_sync_manual_push', 'gv_sync_handle_manual_push' );
function gv_sync_handle_manual_push() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SYNC_NONCE );

	$result = gv_sync_push_all_local_employees();
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&pushed=' . $result['ok'] . '&push_fail=' . $result['fail'] ) );
	exit;
}

add_action( 'admin_post_gv_sync_toggle_hub', 'gv_sync_handle_toggle_hub' );
function gv_sync_handle_toggle_hub() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SYNC_NONCE );
	update_option( 'gv_sync_hub_enabled', isset( $_POST['hub_enabled'] ) ? 1 : 0 );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&hub_toggled=1' ) );
	exit;
}

add_action( 'admin_post_gv_sync_add_node', 'gv_sync_handle_add_node' );
function gv_sync_handle_add_node() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SYNC_NONCE );
	$label = isset( $_POST['node_label'] ) ? wp_unslash( $_POST['node_label'] ) : '';
	if ( '' !== trim( $label ) ) { gv_sync_add_hub_node( $label ); }
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&node_added=1' ) );
	exit;
}

add_action( 'admin_post_gv_sync_toggle_node', 'gv_sync_handle_toggle_node' );
function gv_sync_handle_toggle_node() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SYNC_NONCE );
	$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$active = isset( $_GET['active'] ) ? (int) $_GET['active'] : 1;
	if ( $id > 0 ) { gv_sync_toggle_hub_node( $id, $active ); }
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team' ) );
	exit;
}

add_action( 'admin_post_gv_sync_delete_node', 'gv_sync_handle_delete_node' );
function gv_sync_handle_delete_node() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SYNC_NONCE );
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( $id > 0 ) { gv_sync_delete_hub_node( $id ); }
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&node_deleted=1' ) );
	exit;
}

add_action( 'admin_post_gv_sync_save_rate', 'gv_sync_handle_save_rate' );
function gv_sync_handle_save_rate() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SYNC_NONCE );
	$emp_code = isset( $_POST['emp_code'] ) ? gv_sr_sanitize_employee_code( $_POST['emp_code'] ) : '';
	$name     = isset( $_POST['employee_name'] ) ? wp_unslash( $_POST['employee_name'] ) : '';
	$rate     = isset( $_POST['hourly_rate'] ) ? (int) $_POST['hourly_rate'] : 0;
	if ( '' !== $emp_code ) { gv_sync_save_hub_rate( $emp_code, $name, $rate ); }
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&rate_saved=1' ) );
	exit;
}

/* ==========================================================================
   ۸) خروجی CSV گزارش یکپارچه‌ی همه‌ی سایت‌ها (ویژه هاب)
   ========================================================================== */
add_action( 'admin_post_gv_sync_export_hub_csv', 'gv_sync_handle_export_hub_csv' );
function gv_sync_handle_export_hub_csv() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$from = isset( $_GET['from'] ) ? sanitize_text_field( $_GET['from'] ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
	$to   = isset( $_GET['to'] ) ? sanitize_text_field( $_GET['to'] ) : gmdate( 'Y-m-d' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=combined-timesheet-all-sites-' . gmdate( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'کد کارمندی', 'نام کارمند', 'سایت', 'جمع ساعت در این بازه', 'نرخ ساعتی ترکیبی', 'مبلغ قابل‌پرداخت' ) );

	$employees = gv_sync_get_all_known_employees( $from, $to );
	foreach ( $employees as $code => $name ) {
		$combined = gv_sync_employee_combined_hours( $code, $from, $to );
		$rate     = gv_sync_get_hub_rate( $code );
		if ( empty( $combined['per_site'] ) ) { continue; }
		foreach ( $combined['per_site'] as $site => $hours ) {
			fputcsv( $output, array( $code, $name, $site, $hours, $rate, '' ) );
		}
		fputcsv( $output, array( $code, $name, 'جمع کل', $combined['total'], $rate, $rate > 0 ? round( $combined['total'] * $rate ) : '' ) );
		fputcsv( $output, array() );
	}
	fclose( $output );
	exit;
}

/* ==========================================================================
   ۹) رابط کاربری — به تب «مدیریت تیم» متصل می‌شود
   ========================================================================== */
add_action( 'gv_sr_team_tab_extra', 'gv_sync_render_team_tab_extra', 10, 2 );
function gv_sync_render_team_tab_extra( $from, $to ) {

	if ( isset( $_GET['node_saved'] ) ) { echo '<div class="gvsr-notice">تنظیمات اتصال به هاب ذخیره شد.</div>'; }
	if ( isset( $_GET['pushed'] ) ) {
		echo '<div class="gvsr-notice">همگام‌سازی انجام شد — ' . esc_html( gv_sr_fa_digits( (int) $_GET['pushed'] ) ) . ' کارمند با موفقیت ارسال شد' . ( ! empty( $_GET['push_fail'] ) && (int) $_GET['push_fail'] > 0 ? '، ' . esc_html( gv_sr_fa_digits( (int) $_GET['push_fail'] ) ) . ' مورد ناموفق' : '' ) . '.</div>';
	}
	if ( isset( $_GET['hub_toggled'] ) ) { echo '<div class="gvsr-notice">وضعیت هاب مرکزی تغییر کرد.</div>'; }
	if ( isset( $_GET['node_added'] ) ) { echo '<div class="gvsr-notice">سایت مبدأ جدید اضافه شد؛ کلید API آن را در جدول پایین کپی کنید.</div>'; }
	if ( isset( $_GET['node_deleted'] ) ) { echo '<div class="gvsr-notice">سایت مبدأ حذف شد.</div>'; }
	if ( isset( $_GET['rate_saved'] ) ) { echo '<div class="gvsr-notice">نرخ ساعتی ترکیبی ذخیره شد.</div>'; }

	gv_sync_render_node_section();
	gv_sync_render_hub_section( $from, $to );
}

/* ---------------- بخش «سایت مبدأ»: ارسال کارکرد این سایت به یک هاب دلخواه ---------------- */
function gv_sync_render_node_section() {
	$s = gv_sync_get_node_settings();
	?>
	<div class="gvsr-report-card gvsr-node-box">
		<h3>🔗 اتصال این سایت به یک هاب مرکزی (اختیاری)</h3>
		<p class="gvsr-hint-inline">
			اگر این کارمندان روی چند سایت دیگر هم که همین افزونه را دارند کار می‌کنند و می‌خواهید کارکردشان یک‌جا در یک هاب مرکزی جمع شود،
			آدرس و کلید API آن هاب را این‌جا وارد کنید. این تنظیم کاملاً اختیاری است و اگر خالی/غیرفعال بماند، افزونه دقیقاً مثل قبل و مستقل کار می‌کند.
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-grid-2">
			<?php wp_nonce_field( GV_SYNC_NONCE ); ?>
			<input type="hidden" name="action" value="gv_sync_save_node_settings">
			<label class="gvsr-vis-item" style="grid-column:1/-1;">
				<input type="checkbox" name="enabled" <?php checked( ! empty( $s['enabled'] ) ); ?>> ارسال خودکار کارکرد این سایت به هاب مرکزی فعال باشد
			</label>
			<label>آدرس کامل اندپوینت هاب (از مدیر هاب بگیرید)
				<input type="url" name="hub_endpoint" value="<?php echo esc_attr( $s['hub_endpoint'] ); ?>" placeholder="https://hub-example.com/wp-json/gv-sr/v1/sync">
			</label>
			<label>کلید API (از مدیر هاب بگیرید)
				<input type="text" name="api_key" value="<?php echo esc_attr( $s['api_key'] ); ?>">
			</label>
			<label>نام این سایت (برای نمایش در گزارش هاب)
				<input type="text" name="site_label" value="<?php echo esc_attr( $s['site_label'] ); ?>">
			</label>
			<div style="align-self:flex-end;"><button type="submit" class="gvsr-btn-export">💾 ذخیره تنظیمات اتصال</button></div>
		</form>

		<?php if ( ! empty( $s['enabled'] ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
				<?php wp_nonce_field( GV_SYNC_NONCE ); ?>
				<input type="hidden" name="action" value="gv_sync_manual_push">
				<button type="submit" class="gvsr-btn-ghost">🔄 همگام‌سازی دستی الان</button>
				<?php if ( ! empty( $s['last_sync_at'] ) ) : ?>
					<span class="gvsr-sync-badge <?php echo 'ok' === $s['last_sync_status'] ? 'ok' : 'bad'; ?>">
						آخرین وضعیت: <?php echo 'ok' === $s['last_sync_status'] ? 'موفق' : 'ناموفق'; ?> — <?php echo esc_html( gv_sr_jalali_numeric( substr( $s['last_sync_at'], 0, 10 ) ) ); ?>
					</span>
					<?php if ( 'ok' !== $s['last_sync_status'] && ! empty( $s['last_sync_message'] ) ) : ?>
						<div class="gvsr-hint" style="color:#b91c1c;"><?php echo esc_html( $s['last_sync_message'] ); ?></div>
					<?php endif; ?>
				<?php endif; ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/* ---------------- بخش «هاب مرکزی»: دریافت از سایت‌های دیگر + گزارش یکپارچه ---------------- */
function gv_sync_render_hub_section( $from, $to ) {
	$hub_enabled = gv_sync_hub_is_enabled();
	?>
	<div class="gvsr-report-card gvsr-hub-box">
		<h3>🌐 استفاده از این سایت به‌عنوان هاب مرکزی (اختیاری)</h3>
		<p class="gvsr-hint-inline">با فعال کردن این گزینه، این سایت می‌تواند کارکرد سایر سایت‌های شما (که این افزونه را دارند و به این‌جا وصل شده‌اند) را دریافت و به‌صورت یکپارچه نمایش دهد.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( GV_SYNC_NONCE ); ?>
			<input type="hidden" name="action" value="gv_sync_toggle_hub">
			<label class="gvsr-vis-item">
				<input type="checkbox" name="hub_enabled" onchange="this.form.submit();" <?php checked( $hub_enabled ); ?>> این سایت هاب مرکزی باشد
			</label>
			<noscript><button type="submit" class="gvsr-btn-ghost">ذخیره</button></noscript>
		</form>

		<?php if ( ! $hub_enabled ) : ?>
			<p class="gvsr-hint">این گزینه را فقط روی همان یک سایتی که می‌خواهید محل جمع‌بندی نهایی باشد فعال کنید؛ نیازی نیست همه‌ی سایت‌ها هاب باشند.</p>
			<?php return; ?>
		<?php endif; ?>

		<div class="gvsr-notice" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe;">
			آدرس اندپوینتی که باید در تنظیمات «اتصال به هاب» سایر سایت‌ها وارد شود:
			<code style="direction:ltr;display:inline-block;"><?php echo esc_html( rest_url( GV_SYNC_REST_NS . '/sync' ) ); ?></code>
		</div>

		<h4 style="font-size:13px;color:#1e293b;margin:16px 0 10px;">➕ افزودن سایت مبدأ جدید</h4>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-grid-2">
			<?php wp_nonce_field( GV_SYNC_NONCE ); ?>
			<input type="hidden" name="action" value="gv_sync_add_node">
			<label>نام سایت (فقط برای شناسایی خودتان)
				<input type="text" name="node_label" required placeholder="مثلاً: فروشگاه نمونه">
			</label>
			<div style="align-self:flex-end;"><button type="submit" class="gvsr-btn-export">➕ افزودن و ساخت کلید API</button></div>
		</form>

		<?php $nodes = gv_sync_get_hub_nodes(); ?>
		<div class="gvsr-table-wrap" style="max-width:100%;margin-top:14px;">
			<?php if ( empty( $nodes ) ) : ?>
				<div class="gvsr-empty">هنوز سایت مبدأیی اضافه نکرده‌اید.</div>
			<?php else : ?>
				<table class="gvsr-table">
					<thead><tr><th>نام سایت</th><th>کلید API</th><th>وضعیت</th><th>آخرین اتصال</th><th>عملیات</th></tr></thead>
					<tbody>
					<?php foreach ( $nodes as $n ) : ?>
						<tr>
							<td><b><?php echo esc_html( $n->label ); ?></b></td>
							<td><code style="direction:ltr;font-size:11px;"><?php echo esc_html( $n->api_key ); ?></code></td>
							<td><?php echo (int) $n->active === 1 ? '<span class="gvsr-badge gvsr-badge-green">فعال</span>' : '<span class="gvsr-badge gvsr-badge-gray">غیرفعال</span>'; ?></td>
							<td><?php echo $n->last_seen_at ? esc_html( gv_sr_jalali_numeric( substr( $n->last_seen_at, 0, 10 ) ) ) : '—'; ?></td>
							<td class="gvsr-row-actions">
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_sync_toggle_node&id=' . $n->id . '&active=' . ( (int) $n->active === 1 ? 0 : 1 ) ), GV_SYNC_NONCE ) ); ?>"><?php echo (int) $n->active === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی'; ?></a>
								<a class="gvsr-danger" onclick="return confirm('این سایت مبدأ و تمام داده‌های دریافتی‌اش حذف شود؟');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_sync_delete_node&id=' . $n->id ), GV_SYNC_NONCE ) ); ?>">حذف</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<?php
	/* ---------------- گزارش یکپارچه‌ی همه‌ی سایت‌ها ---------------- */
	$employees   = gv_sync_get_all_known_employees( $from, $to );
	$grand_total = 0.0;
	$grand_pay   = 0;
	$rows        = array();
	foreach ( $employees as $code => $name ) {
		$combined = gv_sync_employee_combined_hours( $code, $from, $to );
		if ( empty( $combined['per_site'] ) ) { continue; }
		$rate = gv_sync_get_hub_rate( $code );
		$pay  = $rate > 0 ? round( $combined['total'] * $rate ) : 0;
		$rows[] = array( 'code' => $code, 'name' => $name, 'combined' => $combined, 'rate' => $rate, 'pay' => $pay );
		$grand_total += $combined['total'];
		$grand_pay   += $pay;
	}
	$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=gv_sync_export_hub_csv&from=' . rawurlencode( $from ) . '&to=' . rawurlencode( $to ) ), GV_SR_NONCE );
	?>
	<div class="gvsr-report-card">
		<h3>🧮 گزارش یکپارچه‌ی کارکرد و حقوق — همه‌ی سایت‌ها
			<a class="gvsr-btn-ghost" style="margin-inline-start:auto;font-size:11.5px;" href="<?php echo esc_url( $export_url ); ?>">📥 خروجی اکسل ترکیبی (CSV)</a>
		</h3>
		<p class="gvsr-hint">این بازه، همان بازه‌ی انتخاب‌شده در بالای صفحه (پیش‌فرض: ماه شمسی جاری) است. نرخ ساعتی این جدول مستقل از نرخ محلی هر سایت است و فقط همین‌جا تنظیم می‌شود.</p>
		<?php if ( empty( $rows ) ) : ?>
			<div class="gvsr-chart-empty">هنوز داده‌ای از هیچ سایتی در این بازه دریافت نشده.</div>
		<?php else : ?>
			<div class="gvsr-kpi-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:14px;">
				<div class="gvsr-kpi"><b><?php echo esc_html( gv_sr_fa_digits( round( $grand_total, 2 ) ) ); ?></b><span>جمع ساعت همه‌ی کارمندان در همه‌ی سایت‌ها</span></div>
				<div class="gvsr-kpi"><b><?php echo esc_html( gv_sr_fa_digits( number_format_i18n( $grand_pay ) ) ); ?></b><span>مجموع حقوق ترکیبی (تومان)</span></div>
			</div>
			<?php foreach ( $rows as $r ) : ?>
				<details class="gvsr-emp-accordion">
					<summary><?php echo esc_html( $r['name'] ); ?> (<?php echo esc_html( $r['code'] ); ?>) — <?php echo esc_html( gv_sr_fa_digits( $r['combined']['total'] ) ); ?> ساعت در مجموع
						<?php if ( $r['pay'] > 0 ) : ?> / <?php echo esc_html( gv_sr_fa_digits( number_format_i18n( $r['pay'] ) ) ); ?> تومان<?php endif; ?>
					</summary>
					<div class="gvsr-table-wrap" style="max-width:100%;margin:10px 0;">
						<table class="gvsr-table">
							<thead><tr><th>سایت</th><th>ساعت</th></tr></thead>
							<tbody>
							<?php foreach ( $r['combined']['per_site'] as $site => $hours ) : ?>
								<tr><td><?php echo esc_html( $site ); ?></td><td><?php echo esc_html( gv_sr_fa_digits( $hours ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-grid-4" style="align-items:end;">
						<?php wp_nonce_field( GV_SYNC_NONCE ); ?>
						<input type="hidden" name="action" value="gv_sync_save_rate">
						<input type="hidden" name="emp_code" value="<?php echo esc_attr( $r['code'] ); ?>">
						<input type="hidden" name="employee_name" value="<?php echo esc_attr( $r['name'] ); ?>">
						<label>نرخ ساعتی ترکیبی این کارمند (تومان)
							<input type="number" min="0" step="1000" name="hourly_rate" value="<?php echo esc_attr( $r['rate'] ); ?>">
						</label>
						<button type="submit" class="gvsr-btn-ghost">💾 ذخیره نرخ</button>
					</form>
				</details>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}
