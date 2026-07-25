<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — مینی CRM و کمپین هدفمند مشتریان
 * ----------------------------------------------------------
 *  مشتری‌ها بر اساس رفتار خریدشان (تعداد سفارش، تاریخ آخرین
 *  خرید) به‌صورت خودکار در یکی از این دسته‌ها قرار می‌گیرند:
 *    - loyal    : خریدار وفادار (چند سفارشی و اخیر)
 *    - inactive : مدت‌هاست خرید نکرده
 *    - one_time : فقط یک‌بار خرید کرده
 *    - regular  : چند سفارشی ولی هنوز به آستانه‌ی وفادار نرسیده
 *    - none     : ثبت‌نام کرده ولی هنوز خریدی نداشته
 *  مدیر می‌تواند برای هر دسته پیامک/ایمیل هدفمند بفرستد.
 * ==========================================================
 */

define( 'GV_CRM_OPT', 'gv_mini_crm_settings' );
define( 'GV_CRM_NONCE', 'gv_crm_nonce_action' );
define( 'GV_CRM_DB_VERSION', '1.0' );
define( 'GV_CRM_PAGE_SLUG', 'gv-mini-crm' );

function gv_crm_default_settings() {
	return array(
		'enabled'          => 0,
		'loyal_min_orders' => 3,
		'inactive_days'    => 60,
		'sms_provider'     => 'kavenegar', // kavenegar | webhook | off
		'kavenegar_key'    => '',
		'kavenegar_sender' => '',
		'webhook_url'      => '',
	);
}
function gv_crm_get_settings() {
	return wp_parse_args( get_option( GV_CRM_OPT, array() ), gv_crm_default_settings() );
}

function gv_crm_segments_meta() {
	return array(
		'loyal'    => array( 'label' => '👑 خریدار وفادار', 'color' => '#16a34a' ),
		'inactive' => array( 'label' => '💤 مدت‌هاست خرید نکرده', 'color' => '#b45309' ),
		'one_time' => array( 'label' => '🙋 فقط یک‌بار خرید کرده', 'color' => '#2563eb' ),
		'regular'  => array( 'label' => '🛍️ مشتری معمولی', 'color' => '#64748b' ),
		'none'     => array( 'label' => '🆕 بدون خرید', 'color' => '#94a3b8' ),
	);
}

/* ==========================================================
   جدول تاریخچه‌ی کمپین‌ها
   ========================================================== */
function gv_crm_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'gv_crm_campaign_log';
}
add_action( 'admin_init', 'gv_crm_maybe_create_table' );
function gv_crm_maybe_create_table() {
	if ( get_option( 'gv_crm_db_version' ) === GV_CRM_DB_VERSION ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = gv_crm_table_name();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		campaign_title VARCHAR(190) NOT NULL DEFAULT '',
		segment VARCHAR(30) NOT NULL DEFAULT '',
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		channel VARCHAR(20) NOT NULL DEFAULT '',
		target_value VARCHAR(190) NOT NULL DEFAULT '',
		status VARCHAR(20) NOT NULL DEFAULT '',
		error_message TEXT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY segment (segment),
		KEY user_id (user_id)
	) {$charset};";
	dbDelta( $sql );
	update_option( 'gv_crm_db_version', GV_CRM_DB_VERSION );
}

/* ==========================================================
   محاسبه‌ی آمار خرید هر مشتری از سفارش‌های ووکامرس
   ========================================================== */
function gv_crm_get_customer_stats( $user_id ) {
	$stats = array( 'count' => 0, 'last_ts' => 0, 'first_ts' => 0, 'total_spent' => 0.0 );
	if ( ! function_exists( 'wc_get_orders' ) ) { return $stats; }

	$orders = wc_get_orders( array(
		'customer_id' => $user_id,
		'status'      => array( 'wc-completed', 'wc-processing' ),
		'limit'       => -1,
		'orderby'     => 'date',
		'order'       => 'ASC',
		'return'      => 'objects',
	) );

	if ( empty( $orders ) ) { return $stats; }

	$stats['count'] = count( $orders );
	foreach ( $orders as $order ) {
		$ts = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
		if ( ! $stats['first_ts'] || $ts < $stats['first_ts'] ) { $stats['first_ts'] = $ts; }
		if ( $ts > $stats['last_ts'] ) { $stats['last_ts'] = $ts; }
		$stats['total_spent'] += (float) $order->get_total();
	}
	return $stats;
}

function gv_crm_compute_segment( $stats, $settings ) {
	if ( 0 === $stats['count'] ) { return 'none'; }

	$days_since_last = $stats['last_ts'] ? ( time() - $stats['last_ts'] ) / DAY_IN_SECONDS : 9999;

	if ( $days_since_last >= (int) $settings['inactive_days'] ) {
		return 'inactive';
	}
	if ( $stats['count'] >= (int) $settings['loyal_min_orders'] ) {
		return 'loyal';
	}
	if ( 1 === $stats['count'] ) {
		return 'one_time';
	}
	return 'regular';
}

/* ==========================================================
   بازمحاسبه‌ی همه‌ی مشتری‌ها (کش در قالب متای کاربر برای سرعت)
   ========================================================== */
function gv_crm_recalculate_all() {
	$settings = gv_crm_get_settings();
	$users = get_users( array(
		'role__in' => array( 'customer', 'subscriber' ),
		'fields'   => 'ID',
	) );

	foreach ( $users as $user_id ) {
		$stats   = gv_crm_get_customer_stats( $user_id );
		$segment = gv_crm_compute_segment( $stats, $settings );

		update_user_meta( $user_id, 'gv_crm_segment', $segment );
		update_user_meta( $user_id, 'gv_crm_order_count', $stats['count'] );
		update_user_meta( $user_id, 'gv_crm_last_order_ts', $stats['last_ts'] );
		update_user_meta( $user_id, 'gv_crm_total_spent', $stats['total_spent'] );
	}
	update_option( 'gv_crm_last_recalc', time() );
	return count( $users );
}

add_action( 'admin_init', 'gv_crm_schedule_cron' );
function gv_crm_schedule_cron() {
	if ( ! wp_next_scheduled( 'gv_crm_daily_recalc' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'gv_crm_daily_recalc' );
	}
}
add_action( 'gv_crm_daily_recalc', 'gv_crm_recalculate_all' );

/* ==========================================================
   ارسال پیامک — پشتیبانی از کاوه‌نگار به‌صورت آماده + وب‌هوک سفارشی
   برای هر پنل دیگر (ملی‌پیامک، فراز و ...) کافی است آدرس وب‌هوک
   خودتان را در تنظیمات وارد کنید یا فیلتر gv_crm_send_sms_override
   را قلاب کنید.
   ========================================================== */
function gv_crm_send_sms( $mobile, $message ) {
	$settings = gv_crm_get_settings();

	$override = apply_filters( 'gv_crm_send_sms_override', null, $mobile, $message, $settings );
	if ( null !== $override ) { return $override; }

	if ( 'off' === $settings['sms_provider'] ) {
		return new WP_Error( 'gv_crm_sms_disabled', 'ارسال پیامک غیرفعال است.' );
	}

	if ( 'kavenegar' === $settings['sms_provider'] ) {
		if ( empty( $settings['kavenegar_key'] ) ) {
			return new WP_Error( 'gv_crm_sms_no_key', 'API Key کاوه‌نگار وارد نشده است.' );
		}
		$url = sprintf(
			'https://api.kavenegar.com/v1/%s/sms/send.json',
			rawurlencode( $settings['kavenegar_key'] )
		);
		$args = array(
			'receptor' => $mobile,
			'message'  => $message,
		);
		if ( ! empty( $settings['kavenegar_sender'] ) ) {
			$args['sender'] = $settings['kavenegar_sender'];
		}
		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => $args,
		) );
		if ( is_wp_error( $response ) ) { return $response; }
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) { return true; }
		return new WP_Error( 'gv_crm_sms_failed', 'خطای کاوه‌نگار (کد ' . $code . ')' );
	}

	if ( 'webhook' === $settings['sms_provider'] ) {
		if ( empty( $settings['webhook_url'] ) ) {
			return new WP_Error( 'gv_crm_sms_no_webhook', 'آدرس وب‌هوک وارد نشده است.' );
		}
		$response = wp_remote_post( $settings['webhook_url'], array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'mobile' => $mobile, 'message' => $message ) ),
		) );
		if ( is_wp_error( $response ) ) { return $response; }
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) { return true; }
		return new WP_Error( 'gv_crm_sms_failed', 'خطای وب‌هوک (کد ' . $code . ')' );
	}

	return new WP_Error( 'gv_crm_sms_unknown_provider', 'سرویس پیامکی نامعتبر است.' );
}

function gv_crm_replace_placeholders( $text, $user ) {
	$first = get_user_meta( $user->ID, 'first_name', true );
	$phone = get_user_meta( $user->ID, 'billing_phone', true );
	$map = array(
		'{name}'       => $user->display_name,
		'{first_name}' => $first ? $first : $user->display_name,
		'{email}'      => $user->user_email,
		'{phone}'      => $phone,
	);
	return strtr( $text, $map );
}

/* ==========================================================
   منوی مدیریت
   ========================================================== */
add_action( 'admin_menu', 'gv_crm_admin_menu' );
function gv_crm_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'مینی CRM و کمپین مشتریان | Groot Vision',
		'👥 CRM و کمپین مشتریان',
		'manage_options',
		GV_CRM_PAGE_SLUG,
		'gv_crm_render_admin_page'
	);
}

add_action( 'admin_post_gv_crm_save_settings', 'gv_crm_save_settings' );
function gv_crm_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_CRM_NONCE );

	$settings = array(
		'enabled'          => isset( $_POST['enabled'] ) ? 1 : 0,
		'loyal_min_orders' => max( 2, intval( $_POST['loyal_min_orders'] ?? 3 ) ),
		'inactive_days'    => max( 7, intval( $_POST['inactive_days'] ?? 60 ) ),
		'sms_provider'     => in_array( $_POST['sms_provider'] ?? '', array( 'kavenegar', 'webhook', 'off' ), true ) ? $_POST['sms_provider'] : 'off',
		'kavenegar_key'    => sanitize_text_field( $_POST['kavenegar_key'] ?? '' ),
		'kavenegar_sender' => sanitize_text_field( $_POST['kavenegar_sender'] ?? '' ),
		'webhook_url'      => esc_url_raw( $_POST['webhook_url'] ?? '' ),
	);
	update_option( GV_CRM_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_CRM_PAGE_SLUG . '&updated=1' ) );
	exit;
}

add_action( 'admin_post_gv_crm_recalc_now', 'gv_crm_recalc_now_handler' );
function gv_crm_recalc_now_handler() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_CRM_NONCE );
	@set_time_limit( 0 );
	$count = gv_crm_recalculate_all();
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_CRM_PAGE_SLUG . '&recalced=' . intval( $count ) ) );
	exit;
}

add_action( 'admin_post_gv_crm_send_campaign', 'gv_crm_send_campaign_handler' );
function gv_crm_send_campaign_handler() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_CRM_NONCE );

	$segment  = sanitize_key( $_POST['segment'] ?? '' );
	$channels = isset( $_POST['channels'] ) ? array_map( 'sanitize_key', (array) $_POST['channels'] ) : array();
	$subject  = sanitize_text_field( $_POST['subject'] ?? '' );
	$message  = sanitize_textarea_field( $_POST['message'] ?? '' );
	$title    = sanitize_text_field( $_POST['campaign_title'] ?? 'کمپین بدون عنوان' );
	$selected_ids = isset( $_POST['user_ids'] ) ? array_map( 'intval', (array) $_POST['user_ids'] ) : array();

	$segments_meta = gv_crm_segments_meta();
	if ( empty( $message ) || empty( $channels ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . GV_CRM_PAGE_SLUG . '&segment=' . $segment . '&camp_error=1' ) );
		exit;
	}

	// تعیین کاربران هدف
	if ( ! empty( $selected_ids ) ) {
		$user_ids = $selected_ids;
	} else {
		$user_ids = get_users( array(
			'meta_key'   => 'gv_crm_segment',
			'meta_value' => $segment,
			'fields'     => 'ID',
		) );
	}

	global $wpdb;
	$table = gv_crm_table_name();
	$sent_ok = 0; $sent_fail = 0;

	foreach ( $user_ids as $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) { continue; }
		$personal_msg = gv_crm_replace_placeholders( $message, $user );

		if ( in_array( 'email', $channels, true ) && is_email( $user->user_email ) ) {
			$sent = wp_mail( $user->user_email, $subject ? $subject : $title, wpautop( $personal_msg ) );
			$wpdb->insert( $table, array(
				'campaign_title' => $title,
				'segment'        => $segment,
				'user_id'        => $user_id,
				'channel'        => 'email',
				'target_value'   => $user->user_email,
				'status'         => $sent ? 'sent' : 'failed',
				'error_message'  => $sent ? '' : 'wp_mail failed',
				'created_at'     => current_time( 'mysql' ),
			) );
			$sent ? $sent_ok++ : $sent_fail++;
		}

		if ( in_array( 'sms', $channels, true ) ) {
			$phone = get_user_meta( $user_id, 'billing_phone', true );
			if ( $phone ) {
				$result = gv_crm_send_sms( $phone, $personal_msg );
				$ok = ( true === $result );
				$wpdb->insert( $table, array(
					'campaign_title' => $title,
					'segment'        => $segment,
					'user_id'        => $user_id,
					'channel'        => 'sms',
					'target_value'   => $phone,
					'status'         => $ok ? 'sent' : 'failed',
					'error_message'  => $ok ? '' : ( is_wp_error( $result ) ? $result->get_error_message() : 'unknown' ),
					'created_at'     => current_time( 'mysql' ),
				) );
				$ok ? $sent_ok++ : $sent_fail++;
			}
		}
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_CRM_PAGE_SLUG . '&segment=' . $segment . '&camp_ok=' . $sent_ok . '&camp_fail=' . $sent_fail ) );
	exit;
}

/* ==========================================================
   صفحه‌ی مدیریت
   ========================================================== */
function gv_crm_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$settings      = gv_crm_get_settings();
	$segments_meta = gv_crm_segments_meta();
	$active_tab    = isset( $_GET['segment'] ) && isset( $segments_meta[ $_GET['segment'] ] ) ? sanitize_key( $_GET['segment'] ) : 'loyal';
	$last_recalc   = get_option( 'gv_crm_last_recalc' );

	// شمارش هر دسته
	$counts = array();
	foreach ( $segments_meta as $key => $meta ) {
		$q = new WP_User_Query( array(
			'meta_key'   => 'gv_crm_segment',
			'meta_value' => $key,
			'fields'     => 'ID',
			'number'     => 1,
			'count_total'=> true,
		) );
		$counts[ $key ] = $q->get_total();
	}

	// لیست مشتری‌های همان تب فعال
	$paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
	$query = new WP_User_Query( array(
		'meta_key'   => 'gv_crm_segment',
		'meta_value' => $active_tab,
		'number'     => 25,
		'paged'      => $paged,
		'orderby'    => 'meta_value_num',
		'meta_query' => array(
			array( 'key' => 'gv_crm_last_order_ts', 'compare' => 'EXISTS' ),
		),
	) );
	$customers  = $query->get_results();
	$total_rows = $query->get_total();
	?>
	<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif; max-width:1150px;">
		<style>
			.gvcrm-header{background:linear-gradient(120deg,#9f1239,#7c3aed);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;}
			.gvcrm-header h1{margin:0;font-size:20px;color:#fff;}
			.gvcrm-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin-bottom:18px;}
			.gvcrm-card h2{margin-top:0;font-size:15px;}
			.gvcrm-field{margin-bottom:14px;}
			.gvcrm-field label{display:block;font-weight:700;font-size:13px;margin-bottom:5px;color:#334155;}
			.gvcrm-field input[type=text],.gvcrm-field input[type=number],.gvcrm-field select,.gvcrm-field textarea{width:100%;max-width:420px;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;}
			.gvcrm-field textarea{max-width:100%;min-height:100px;}
			.gvcrm-btn{background:#111827;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;}
			.gvcrm-btn-outline{background:#fff;color:#111827 !important;border:1px solid #d1d5db;padding:9px 18px;border-radius:10px;font-weight:600;cursor:pointer;}
			.gvcrm-segments{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:8px;}
			@media(max-width:900px){.gvcrm-segments{grid-template-columns:repeat(2,1fr);}}
			.gvcrm-seg-card{border:2px solid #e5e7eb;border-radius:12px;padding:14px;text-align:center;text-decoration:none;display:block;color:#0f172a;}
			.gvcrm-seg-card.is-active{border-color:#0e4037;box-shadow:0 0 0 3px rgba(14,64,55,.12);}
			.gvcrm-seg-card b{display:block;font-size:22px;margin-top:6px;}
			table.gvcrm-table{width:100%;border-collapse:collapse;font-size:12.5px;}
			table.gvcrm-table th, table.gvcrm-table td{border:1px solid #e5e7eb;padding:8px 10px;text-align:right;}
			table.gvcrm-table th{background:#f8fafc;}
			.gvcrm-badge{display:inline-block;padding:2px 9px;border-radius:20px;color:#fff;font-size:11px;font-weight:700;}
			.gvcrm-note{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;font-size:12px;color:#1e40af;margin-bottom:14px;}
		</style>

		<div class="gvcrm-header"><h1>👥 مینی CRM و کمپین هدفمند مشتریان</h1></div>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['recalced'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>دسته‌بندی <?php echo intval( $_GET['recalced'] ); ?> مشتری بازمحاسبه شد.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['camp_ok'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>کمپین ارسال شد — موفق: <?php echo intval( $_GET['camp_ok'] ); ?> | ناموفق: <?php echo intval( $_GET['camp_fail'] ?? 0 ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['camp_error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>متن پیام یا کانال ارسال را انتخاب کنید.</p></div>
		<?php endif; ?>

		<div class="gvcrm-card">
			<h2>⚙️ تنظیمات دسته‌بندی و سرویس پیامک</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gv_crm_save_settings">
				<?php wp_nonce_field( GV_CRM_NONCE ); ?>
				<div class="gvcrm-field"><label><input type="checkbox" name="enabled" <?php checked( $settings['enabled'], 1 ); ?>> فعال‌سازی مینی CRM</label></div>
				<div class="gvcrm-field"><label>حداقل تعداد سفارش برای «خریدار وفادار»</label><input type="number" name="loyal_min_orders" min="2" value="<?php echo esc_attr( $settings['loyal_min_orders'] ); ?>"></div>
				<div class="gvcrm-field"><label>بعد از چند روز بدون خرید، «مدت‌هاست خرید نکرده» محسوب شود</label><input type="number" name="inactive_days" min="7" value="<?php echo esc_attr( $settings['inactive_days'] ); ?>"></div>
				<div class="gvcrm-field"><label>سرویس ارسال پیامک</label>
					<select name="sms_provider" id="gvcrm-sms-provider">
						<option value="off" <?php selected( $settings['sms_provider'], 'off' ); ?>>غیرفعال (فقط ایمیل)</option>
						<option value="kavenegar" <?php selected( $settings['sms_provider'], 'kavenegar' ); ?>>کاوه‌نگار</option>
						<option value="webhook" <?php selected( $settings['sms_provider'], 'webhook' ); ?>>وب‌هوک سفارشی (ملی‌پیامک / فراز و ...)</option>
					</select>
				</div>
				<div class="gvcrm-field"><label>Kavenegar API Key</label><input type="text" name="kavenegar_key" value="<?php echo esc_attr( $settings['kavenegar_key'] ); ?>" dir="ltr"></div>
				<div class="gvcrm-field"><label>Kavenegar Sender (اختیاری)</label><input type="text" name="kavenegar_sender" value="<?php echo esc_attr( $settings['kavenegar_sender'] ); ?>" dir="ltr"></div>
				<div class="gvcrm-field"><label>آدرس وب‌هوک سفارشی (POST با JSON شامل mobile و message)</label><input type="text" name="webhook_url" value="<?php echo esc_attr( $settings['webhook_url'] ); ?>" dir="ltr"></div>
				<button type="submit" class="gvcrm-btn">💾 ذخیره تنظیمات</button>
			</form>
		</div>

		<div class="gvcrm-card">
			<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
				<h2 style="margin:0;">📊 دسته‌بندی مشتری‌ها</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gv_crm_recalc_now">
					<?php wp_nonce_field( GV_CRM_NONCE ); ?>
					<button type="submit" class="gvcrm-btn-outline">🔄 بازمحاسبه الان</button>
				</form>
			</div>
			<p style="font-size:11.5px;color:#94a3b8;">
				<?php echo $last_recalc ? 'آخرین بازمحاسبه: ' . esc_html( date_i18n( 'Y-m-d H:i', $last_recalc ) ) : 'هنوز بازمحاسبه نشده — روی «بازمحاسبه الان» بزنید.'; ?>
				(همچنین هر شب به‌صورت خودکار اجرا می‌شود)
			</p>

			<div class="gvcrm-segments">
				<?php foreach ( $segments_meta as $key => $meta ) :
					$url = admin_url( 'admin.php?page=' . GV_CRM_PAGE_SLUG . '&segment=' . $key );
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="gvcrm-seg-card <?php echo $active_tab === $key ? 'is-active' : ''; ?>" style="border-color:<?php echo $active_tab === $key ? esc_attr( $meta['color'] ) : '#e5e7eb'; ?>;">
						<span><?php echo esc_html( $meta['label'] ); ?></span>
						<b style="color:<?php echo esc_attr( $meta['color'] ); ?>;"><?php echo esc_html( $counts[ $key ] ); ?></b>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="gvcrm-card">
			<h2>لیست مشتری‌ها — دسته: <?php echo esc_html( $segments_meta[ $active_tab ]['label'] ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="gvcrm-campaign-form">
				<input type="hidden" name="action" value="gv_crm_send_campaign">
				<input type="hidden" name="segment" value="<?php echo esc_attr( $active_tab ); ?>">
				<?php wp_nonce_field( GV_CRM_NONCE ); ?>

				<table class="gvcrm-table">
					<tr>
						<th style="width:26px;"><input type="checkbox" id="gvcrm-select-all"></th>
						<th>نام</th><th>ایمیل</th><th>موبایل</th><th>تعداد سفارش</th><th>آخرین خرید</th>
					</tr>
					<?php if ( $customers ) : foreach ( $customers as $c ) :
						$phone = get_user_meta( $c->ID, 'billing_phone', true );
						$order_count = get_user_meta( $c->ID, 'gv_crm_order_count', true );
						$last_ts = get_user_meta( $c->ID, 'gv_crm_last_order_ts', true );
						?>
						<tr>
							<td><input type="checkbox" name="user_ids[]" value="<?php echo esc_attr( $c->ID ); ?>" class="gvcrm-row-check"></td>
							<td><?php echo esc_html( $c->display_name ); ?></td>
							<td><?php echo esc_html( $c->user_email ); ?></td>
							<td dir="ltr"><?php echo esc_html( $phone ? $phone : '—' ); ?></td>
							<td><?php echo esc_html( $order_count ? $order_count : 0 ); ?></td>
							<td><?php echo esc_html( $last_ts ? date_i18n( 'Y-m-d', $last_ts ) : '—' ); ?></td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="6">مشتری‌ای در این دسته یافت نشد. اگر تازه ماژول را فعال کرده‌اید، ابتدا «بازمحاسبه الان» را بزنید.</td></tr>
					<?php endif; ?>
				</table>
				<p style="font-size:11px;color:#94a3b8;">جمع کل این دسته: <?php echo esc_html( $total_rows ); ?> نفر — اگر هیچ گزینه‌ای تیک نخورد، پیام به «کل این دسته» ارسال می‌شود؛ اگر چند نفر را تیک بزنید، فقط برای همان‌ها ارسال می‌شود.</p>

				<h2 style="margin-top:26px;">✉️ ارسال پیام هدفمند</h2>
				<div class="gvcrm-field"><label>عنوان کمپین (فقط برای تاریخچه‌ی داخلی)</label><input type="text" name="campaign_title" placeholder="مثلاً: تخفیف بازگشت مشتری"></div>
				<div class="gvcrm-field">
					<label>کانال ارسال</label>
					<label style="display:inline-block;margin-left:16px;font-weight:400;"><input type="checkbox" name="channels[]" value="email" checked> ایمیل</label>
					<label style="display:inline-block;font-weight:400;"><input type="checkbox" name="channels[]" value="sms"> پیامک</label>
				</div>
				<div class="gvcrm-field"><label>موضوع ایمیل (اختیاری)</label><input type="text" name="subject" placeholder="دلمون برات تنگ شده!"></div>
				<div class="gvcrm-field">
					<label>متن پیام</label>
					<textarea name="message" placeholder="سلام {first_name} عزیز، دلمون برات تنگ شده! با کد تخفیف ۲۰٪ برگرد و خرید کن."></textarea>
					<small style="color:#94a3b8;">می‌توانید از {name}، {first_name}، {email}، {phone} در متن استفاده کنید.</small>
				</div>
				<button type="submit" class="gvcrm-btn">🚀 ارسال کمپین</button>
			</form>
		</div>

		<div class="gvcrm-card">
			<h2>🕘 تاریخچه‌ی آخرین ارسال‌ها</h2>
			<?php
			global $wpdb;
			$table = gv_crm_table_name();
			$logs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 20" );
			?>
			<table class="gvcrm-table">
				<tr><th>تاریخ</th><th>کمپین</th><th>دسته</th><th>کانال</th><th>مقصد</th><th>وضعیت</th></tr>
				<?php if ( $logs ) : foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log->created_at ); ?></td>
						<td><?php echo esc_html( $log->campaign_title ); ?></td>
						<td><?php echo esc_html( isset( $segments_meta[ $log->segment ] ) ? $segments_meta[ $log->segment ]['label'] : $log->segment ); ?></td>
						<td><?php echo esc_html( 'sms' === $log->channel ? 'پیامک' : 'ایمیل' ); ?></td>
						<td dir="ltr"><?php echo esc_html( $log->target_value ); ?></td>
						<td>
							<?php if ( 'sent' === $log->status ) : ?>
								<span class="gvcrm-badge" style="background:#16a34a;">موفق</span>
							<?php else : ?>
								<span class="gvcrm-badge" style="background:#dc2626;" title="<?php echo esc_attr( $log->error_message ); ?>">ناموفق</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="6">هنوز کمپینی ارسال نشده است.</td></tr>
				<?php endif; ?>
			</table>
		</div>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p>
	</div>
	<script>
		document.getElementById('gvcrm-select-all')?.addEventListener('change', function(){
			document.querySelectorAll('.gvcrm-row-check').forEach(function(cb){ cb.checked = document.getElementById('gvcrm-select-all').checked; });
		});
	</script>
	<?php
}
