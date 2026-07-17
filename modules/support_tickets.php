<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — سیستم تیکت پشتیبانی
 *  ------------------------------------------------------------
 *  - ثبت تیکت توسط کاربر (مهمان یا عضو سایت) از طریق شورت‌کد [gv_tickets]
 *  - پیگیری کامل تیکت (تاریخچه پیام‌ها) از طرف کاربر
 *  - پاسخ‌دهی و مدیریت کامل تیکت‌ها از پیشخوان وردپرس
 *  - اعلان ایمیلی خودکار:
 *      • به مدیر سایت: هنگام ثبت تیکت جدید و پاسخ جدید از کاربر
 *      • به کاربر: روی همان ایمیلی که هنگام ثبت تیکت وارد کرده،
 *        هنگام ثبت تیکت (تاییدیه) و هر بار که ادمین پاسخ می‌دهد
 *  داده‌ها در دو جدول اختصاصی دیتابیس ذخیره می‌شوند.
 * ==========================================================
 */

define( 'GV_ST_OPT', 'gv_support_tickets_settings' );
define( 'GV_ST_NONCE', 'gv_st_nonce_action' );
define( 'GV_ST_DB_VERSION', '1.0' );
define( 'GV_ST_PAGE_SLUG', 'gv-support-tickets' );

/* ==========================================================================
   ۰) تنظیمات پیش‌فرض
   ========================================================================== */
function gv_st_default_settings() {
	return array(
		'enabled'                 => 1,
		'admin_email'             => get_option( 'admin_email' ),
		'from_name'               => get_bloginfo( 'name' ) ?: 'پشتیبانی سایت',
		'page_id'                 => 0,
		'notify_admin_new_ticket' => 1,
		'notify_admin_new_reply'  => 1,
		'allow_attachments'       => 1,
		'max_attachment_mb'       => 5,
	);
}

function gv_st_get_settings() {
	return wp_parse_args( get_option( GV_ST_OPT, array() ), gv_st_default_settings() );
}

/* ==========================================================================
   ۱) ساخت جداول دیتابیس
   ========================================================================== */
add_action( 'plugins_loaded', 'gv_st_maybe_install_db' );
function gv_st_maybe_install_db() {
	if ( get_option( 'gv_st_db_version' ) === GV_ST_DB_VERSION ) { return; }

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$t_tickets = $wpdb->prefix . 'gv_tickets';
	$t_replies = $wpdb->prefix . 'gv_ticket_replies';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$t_tickets} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		ticket_key VARCHAR(40) NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		name VARCHAR(191) NOT NULL,
		email VARCHAR(191) NOT NULL,
		subject VARCHAR(255) NOT NULL,
		priority VARCHAR(20) NOT NULL DEFAULT 'normal',
		status VARCHAR(20) NOT NULL DEFAULT 'open',
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY ticket_key (ticket_key),
		KEY user_id (user_id),
		KEY email (email),
		KEY status (status)
	) {$charset_collate};" );

	dbDelta( "CREATE TABLE {$t_replies} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		ticket_id BIGINT UNSIGNED NOT NULL,
		sender_type VARCHAR(10) NOT NULL DEFAULT 'user',
		sender_name VARCHAR(191) NOT NULL,
		message LONGTEXT NOT NULL,
		attachment_url TEXT NULL,
		attachment_name VARCHAR(255) NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY ticket_id (ticket_id)
	) {$charset_collate};" );

	update_option( 'gv_st_db_version', GV_ST_DB_VERSION );
}

/* ==========================================================================
   ۲) توابع کمکی
   ========================================================================== */
function gv_st_generate_key() {
	return substr( wp_generate_password( 24, false, false ), 0, 20 );
}

function gv_st_status_label( $status ) {
	$labels = array(
		'open'     => 'در انتظار پاسخ',
		'answered' => 'پاسخ داده‌شده',
		'closed'   => 'بسته‌شده',
	);
	return $labels[ $status ] ?? $status;
}

function gv_st_status_color( $status ) {
	$colors = array(
		'open'     => '#b45309',
		'answered' => '#15803d',
		'closed'   => '#64748b',
	);
	return $colors[ $status ] ?? '#64748b';
}

function gv_st_priority_label( $priority ) {
	$labels = array(
		'low'    => 'کم',
		'normal' => 'عادی',
		'high'   => 'فوری',
	);
	return $labels[ $priority ] ?? $priority;
}

function gv_st_get_ticket_by_key( $key ) {
	global $wpdb;
	$key = sanitize_text_field( $key );
	if ( empty( $key ) ) { return null; }
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_tickets WHERE ticket_key = %s", $key
	) );
}

function gv_st_get_ticket_by_id( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_tickets WHERE id = %d", intval( $id )
	) );
}

function gv_st_get_replies( $ticket_id ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_ticket_replies WHERE ticket_id = %d ORDER BY created_at ASC", intval( $ticket_id )
	) );
}

function gv_st_get_ticket_page_url( $key = '' ) {
	$s = gv_st_get_settings();
	if ( ! empty( $s['page_id'] ) && get_post_status( $s['page_id'] ) ) {
		$url = get_permalink( $s['page_id'] );
	} else {
		$url = home_url( '/' );
	}
	if ( $key ) {
		$url = add_query_arg( 'gv_key', $key, $url );
	}
	return $url;
}

/* آپلود فایل پیوست (اختیاری) */
function gv_st_handle_attachment_upload( $field = 'gv_st_attachment' ) {
	$s = gv_st_get_settings();
	if ( empty( $s['allow_attachments'] ) ) { return array( '', '' ); }
	if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) { return array( '', '' ); }

	$max_bytes = max( 1, intval( $s['max_attachment_mb'] ) ) * MB_IN_BYTES;
	if ( $_FILES[ $field ]['size'] > $max_bytes ) {
		return array( '', '' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$allowed_mimes = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
		'pdf'          => 'application/pdf',
		'zip'          => 'application/zip',
	);

	add_filter( 'upload_mimes', function( $mimes ) use ( $allowed_mimes ) { return $allowed_mimes; } );
	$upload = wp_handle_upload( $_FILES[ $field ], array( 'test_form' => false, 'mimes' => $allowed_mimes ) );
	remove_all_filters( 'upload_mimes' );

	if ( isset( $upload['url'] ) && empty( $upload['error'] ) ) {
		return array( esc_url_raw( $upload['url'] ), sanitize_file_name( $_FILES[ $field ]['name'] ) );
	}
	return array( '', '' );
}

/* ==========================================================================
   ۳) ارسال ایمیل
   ========================================================================== */
function gv_st_send_mail( $to, $subject, $body_html ) {
	$s = gv_st_get_settings();
	add_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( ! empty( $s['from_name'] ) ) {
		$headers[] = 'From: ' . $s['from_name'] . ' <' . get_option( 'admin_email' ) . '>';
	}
	$html = '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;background:#f4f6f5;padding:24px;">
		<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
			<div style="background:linear-gradient(120deg,#0e4037,#145c4d);padding:20px 26px;color:#fff;">
				<h2 style="margin:0;font-size:17px;">' . esc_html( $s['from_name'] ) . '</h2>
			</div>
			<div style="padding:24px 26px;color:#1f2937;font-size:14px;line-height:2;">' . $body_html . '</div>
			<div style="padding:14px 26px;background:#f9fafb;color:#9ca3af;font-size:11.5px;text-align:center;">این ایمیل به‌صورت خودکار از سیستم تیکت پشتیبانی ارسال شده است.</div>
		</div>
	</div>';
	wp_mail( $to, $subject, $html, $headers );
	remove_filter( 'wp_mail_content_type', '__return_true' );
}

function gv_st_notify_admin_new_ticket( $ticket ) {
	$s = gv_st_get_settings();
	if ( empty( $s['notify_admin_new_ticket'] ) || empty( $s['admin_email'] ) ) { return; }
	$url = admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=view&id=' . $ticket->id );
	$body = '<p><b>یک تیکت جدید ثبت شد:</b></p>
		<p>موضوع: ' . esc_html( $ticket->subject ) . '<br>
		نام: ' . esc_html( $ticket->name ) . '<br>
		ایمیل: ' . esc_html( $ticket->email ) . '<br>
		اولویت: ' . esc_html( gv_st_priority_label( $ticket->priority ) ) . '</p>
		<p><a href="' . esc_url( $url ) . '" style="background:#0e4037;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">مشاهده و پاسخ در پیشخوان</a></p>';
	gv_st_send_mail( $s['admin_email'], 'تیکت جدید: ' . $ticket->subject, $body );
}

function gv_st_notify_admin_new_reply( $ticket ) {
	$s = gv_st_get_settings();
	if ( empty( $s['notify_admin_new_reply'] ) || empty( $s['admin_email'] ) ) { return; }
	$url = admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=view&id=' . $ticket->id );
	$body = '<p><b>' . esc_html( $ticket->name ) . '</b> یک پیام جدید در تیکت «' . esc_html( $ticket->subject ) . '» ارسال کرد.</p>
		<p><a href="' . esc_url( $url ) . '" style="background:#0e4037;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">مشاهده و پاسخ در پیشخوان</a></p>';
	gv_st_send_mail( $s['admin_email'], 'پاسخ جدید کاربر در تیکت #' . $ticket->id, $body );
}

function gv_st_notify_customer_ticket_created( $ticket ) {
	$url = gv_st_get_ticket_page_url( $ticket->ticket_key );
	$body = '<p>سلام ' . esc_html( $ticket->name ) . ' عزیز،</p>
		<p>تیکت شما با موضوع «' . esc_html( $ticket->subject ) . '» با موفقیت ثبت شد و در اسرع وقت بررسی می‌شود.</p>
		<p>کد پیگیری تیکت شما: <b style="direction:ltr;display:inline-block;">' . esc_html( $ticket->ticket_key ) . '</b></p>
		<p><a href="' . esc_url( $url ) . '" style="background:#0e4037;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">مشاهده و پیگیری تیکت</a></p>';
	gv_st_send_mail( $ticket->email, 'تیکت شما ثبت شد: ' . $ticket->subject, $body );
}

function gv_st_notify_customer_new_reply( $ticket, $message ) {
	$url = gv_st_get_ticket_page_url( $ticket->ticket_key );
	$body = '<p>سلام ' . esc_html( $ticket->name ) . ' عزیز،</p>
		<p>پاسخ جدیدی برای تیکت شما با موضوع «' . esc_html( $ticket->subject ) . '» ثبت شد:</p>
		<div style="background:#f4f6f5;border-radius:10px;padding:14px 16px;margin:12px 0;">' . nl2br( esc_html( $message ) ) . '</div>
		<p><a href="' . esc_url( $url ) . '" style="background:#0e4037;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">مشاهده کامل و پاسخ</a></p>';
	gv_st_send_mail( $ticket->email, 'پاسخ جدید برای تیکت شما: ' . $ticket->subject, $body );
}

/* ==========================================================================
   ۴) پردازش فرم‌های سمت کاربر (فرانت‌اند)
   ========================================================================== */
add_action( 'admin_post_gv_st_submit_ticket', 'gv_st_handle_submit_ticket' );
add_action( 'admin_post_nopriv_gv_st_submit_ticket', 'gv_st_handle_submit_ticket' );
function gv_st_handle_submit_ticket() {
	if ( ! isset( $_POST['gv_st_nonce'] ) || ! wp_verify_nonce( $_POST['gv_st_nonce'], GV_ST_NONCE ) ) {
		wp_die( 'درخواست نامعتبر است.' );
	}
	// هانی‌پات ضد اسپم — این فیلد باید همیشه خالی باشد
	if ( ! empty( $_POST['gv_st_hp'] ) ) {
		wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}

	$s = gv_st_get_settings();
	if ( empty( $s['enabled'] ) ) { wp_die( 'سیستم تیکت در حال حاضر غیرفعال است.' ); }

	$name    = sanitize_text_field( $_POST['gv_st_name'] ?? '' );
	$email   = sanitize_email( $_POST['gv_st_email'] ?? '' );
	$subject = sanitize_text_field( $_POST['gv_st_subject'] ?? '' );
	$message = sanitize_textarea_field( $_POST['gv_st_message'] ?? '' );
	$priority = in_array( $_POST['gv_st_priority'] ?? 'normal', array( 'low', 'normal', 'high' ), true ) ? $_POST['gv_st_priority'] : 'normal';

	if ( is_user_logged_in() ) {
		$u = wp_get_current_user();
		$name  = $name ?: $u->display_name;
		$email = $u->user_email;
	}

	$redirect = wp_get_referer() ?: gv_st_get_ticket_page_url();

	if ( empty( $name ) || empty( $subject ) || empty( $message ) || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'gv_st_error', 'fields', $redirect ) );
		exit;
	}

	global $wpdb;
	$now = current_time( 'mysql' );
	$key = gv_st_generate_key();

	$wpdb->insert( $wpdb->prefix . 'gv_tickets', array(
		'ticket_key' => $key,
		'user_id'    => get_current_user_id(),
		'name'       => $name,
		'email'      => $email,
		'subject'    => $subject,
		'priority'   => $priority,
		'status'     => 'open',
		'created_at' => $now,
		'updated_at' => $now,
	) );
	$ticket_id = $wpdb->insert_id;

	list( $att_url, $att_name ) = gv_st_handle_attachment_upload();

	$wpdb->insert( $wpdb->prefix . 'gv_ticket_replies', array(
		'ticket_id'       => $ticket_id,
		'sender_type'     => 'user',
		'sender_name'     => $name,
		'message'         => $message,
		'attachment_url'  => $att_url,
		'attachment_name' => $att_name,
		'created_at'      => $now,
	) );

	$ticket = gv_st_get_ticket_by_id( $ticket_id );
	gv_st_notify_admin_new_ticket( $ticket );
	gv_st_notify_customer_ticket_created( $ticket );

	wp_safe_redirect( gv_st_get_ticket_page_url( $key ) . '&gv_st_msg=created' );
	exit;
}

add_action( 'admin_post_gv_st_submit_reply', 'gv_st_handle_submit_reply' );
add_action( 'admin_post_nopriv_gv_st_submit_reply', 'gv_st_handle_submit_reply' );
function gv_st_handle_submit_reply() {
	if ( ! isset( $_POST['gv_st_nonce'] ) || ! wp_verify_nonce( $_POST['gv_st_nonce'], GV_ST_NONCE ) ) {
		wp_die( 'درخواست نامعتبر است.' );
	}
	if ( ! empty( $_POST['gv_st_hp'] ) ) { wp_die( 'درخواست نامعتبر است.' ); }

	$key     = sanitize_text_field( $_POST['gv_st_key'] ?? '' );
	$message = sanitize_textarea_field( $_POST['gv_st_message'] ?? '' );
	$ticket  = gv_st_get_ticket_by_key( $key );

	if ( ! $ticket ) { wp_die( 'تیکت یافت نشد.' ); }
	if ( 'closed' === $ticket->status ) {
		wp_safe_redirect( gv_st_get_ticket_page_url( $key ) . '&gv_st_error=closed' );
		exit;
	}
	if ( empty( $message ) ) {
		wp_safe_redirect( gv_st_get_ticket_page_url( $key ) . '&gv_st_error=fields' );
		exit;
	}

	$sender_name = $ticket->name;
	if ( is_user_logged_in() && intval( $ticket->user_id ) === get_current_user_id() ) {
		$sender_name = wp_get_current_user()->display_name;
	}

	global $wpdb;
	$now = current_time( 'mysql' );
	list( $att_url, $att_name ) = gv_st_handle_attachment_upload();

	$wpdb->insert( $wpdb->prefix . 'gv_ticket_replies', array(
		'ticket_id'       => $ticket->id,
		'sender_type'     => 'user',
		'sender_name'     => $sender_name,
		'message'         => $message,
		'attachment_url'  => $att_url,
		'attachment_name' => $att_name,
		'created_at'      => $now,
	) );
	$wpdb->update( $wpdb->prefix . 'gv_tickets', array( 'status' => 'open', 'updated_at' => $now ), array( 'id' => $ticket->id ) );

	gv_st_notify_admin_new_reply( $ticket );

	wp_safe_redirect( gv_st_get_ticket_page_url( $key ) . '&gv_st_msg=replied#gv-st-thread' );
	exit;
}

add_action( 'admin_post_gv_st_customer_close', 'gv_st_handle_customer_close' );
add_action( 'admin_post_nopriv_gv_st_customer_close', 'gv_st_handle_customer_close' );
function gv_st_handle_customer_close() {
	if ( ! isset( $_POST['gv_st_nonce'] ) || ! wp_verify_nonce( $_POST['gv_st_nonce'], GV_ST_NONCE ) ) {
		wp_die( 'درخواست نامعتبر است.' );
	}
	$key    = sanitize_text_field( $_POST['gv_st_key'] ?? '' );
	$ticket = gv_st_get_ticket_by_key( $key );
	if ( ! $ticket ) { wp_die( 'تیکت یافت نشد.' ); }

	global $wpdb;
	$wpdb->update( $wpdb->prefix . 'gv_tickets', array( 'status' => 'closed', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $ticket->id ) );

	wp_safe_redirect( gv_st_get_ticket_page_url( $key ) . '&gv_st_msg=closed' );
	exit;
}

/* ==========================================================================
   ۵) شورت‌کد فرانت‌اند [gv_tickets]
   ========================================================================== */
add_shortcode( 'gv_tickets', 'gv_st_render_shortcode' );
function gv_st_render_shortcode() {
	$s = gv_st_get_settings();
	if ( empty( $s['enabled'] ) ) {
		return '<p>سیستم تیکت در حال حاضر غیرفعال است.</p>';
	}

	ob_start();
	?>
	<style>
		.gvst-box{font-family:'Vazirmatn',Tahoma,sans-serif;max-width:760px;margin:0 auto;direction:rtl;text-align:right;}
		.gvst-tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
		.gvst-tab-btn{background:#f1f5f4;border:1px solid #e2e8f0;border-radius:10px;padding:10px 20px;font-size:13.5px;font-weight:700;cursor:pointer;color:#0f172a;}
		.gvst-tab-btn.is-active{background:#0e4037;color:#fff;border-color:#0e4037;}
		.gvst-panel{display:none;}
		.gvst-panel.is-active{display:block;}
		.gvst-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,.03);}
		.gvst-field{margin-bottom:14px;}
		.gvst-field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px;}
		.gvst-field input[type=text],.gvst-field input[type=email],.gvst-field select,.gvst-field textarea,.gvst-field input[type=file]{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:9px;font-family:inherit;font-size:13.5px;box-sizing:border-box;}
		.gvst-field textarea{min-height:120px;resize:vertical;}
		.gvst-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
		@media(max-width:600px){.gvst-row{grid-template-columns:1fr;}}
		.gvst-btn{background:#0e4037;color:#fff !important;border:none;padding:11px 26px;border-radius:10px;font-weight:700;cursor:pointer;font-size:13.5px;text-decoration:none;display:inline-block;}
		.gvst-btn-outline{background:#fff;color:#0e4037 !important;border:1px solid #0e4037;}
		.gvst-btn-danger{background:#b91c1c;}
		.gvst-hp{position:absolute;left:-9999px;top:-9999px;}
		.gvst-notice{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;}
		.gvst-notice-success{background:#dcfce7;color:#166534;}
		.gvst-notice-error{background:#fee2e2;color:#991b1b;}
		.gvst-list-item{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:10px;flex-wrap:wrap;gap:8px;}
		.gvst-badge{font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;color:#fff;white-space:nowrap;}
		.gvst-msg{padding:14px 16px;border-radius:12px;margin-bottom:12px;max-width:88%;font-size:13.5px;line-height:1.9;}
		.gvst-msg-user{background:#f1f5f4;margin-left:auto;}
		.gvst-msg-admin{background:#eff6ff;margin-right:auto;}
		.gvst-msg-meta{font-size:11px;color:#64748b;margin-bottom:6px;font-weight:700;}
		.gvst-att{display:inline-block;margin-top:8px;font-size:12px;}
		.gvst-thread{max-height:520px;overflow-y:auto;padding:6px 4px;margin-bottom:16px;}
	</style>

	<div class="gvst-box">
		<?php
		$view_key = isset( $_GET['gv_key'] ) ? sanitize_text_field( $_GET['gv_key'] ) : '';

		if ( isset( $_GET['gv_st_error'] ) ) {
			$err = $_GET['gv_st_error'];
			$msg = 'fields' === $err ? 'لطفاً همه‌ی فیلدهای ضروری را صحیح پر کنید.' : ( 'closed' === $err ? 'این تیکت بسته شده و امکان ارسال پیام جدید وجود ندارد.' : 'خطایی رخ داد.' );
			echo '<div class="gvst-notice gvst-notice-error">' . esc_html( $msg ) . '</div>';
		}
		if ( isset( $_GET['gv_st_msg'] ) ) {
			$m = $_GET['gv_st_msg'];
			$map = array( 'created' => 'تیکت شما با موفقیت ثبت شد. کد پیگیری به ایمیل شما ارسال شد.', 'replied' => 'پیام شما ثبت شد.', 'closed' => 'تیکت بسته شد.' );
			if ( isset( $map[ $m ] ) ) { echo '<div class="gvst-notice gvst-notice-success">' . esc_html( $map[ $m ] ) . '</div>'; }
		}

		if ( $view_key ) {
			gv_st_render_thread_view( $view_key );
		} else {
			gv_st_render_tabs_view();
		}
		?>
	</div>
	<script>
	(function(){
		var btns = document.querySelectorAll('.gvst-tab-btn');
		btns.forEach(function(b){
			b.addEventListener('click', function(){
				document.querySelectorAll('.gvst-tab-btn').forEach(function(x){ x.classList.remove('is-active'); });
				document.querySelectorAll('.gvst-panel').forEach(function(x){ x.classList.remove('is-active'); });
				b.classList.add('is-active');
				document.getElementById(b.dataset.target).classList.add('is-active');
			});
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}

function gv_st_render_tabs_view() {
	$logged_in = is_user_logged_in();
	?>
	<div class="gvst-tabs">
		<button type="button" class="gvst-tab-btn is-active" data-target="gvst-panel-new">🎫 ثبت تیکت جدید</button>
		<button type="button" class="gvst-tab-btn" data-target="gvst-panel-list"><?php echo $logged_in ? '📋 تیکت‌های من' : '🔍 پیگیری تیکت'; ?></button>
	</div>

	<div id="gvst-panel-new" class="gvst-panel is-active">
		<?php gv_st_render_new_ticket_form(); ?>
	</div>

	<div id="gvst-panel-list" class="gvst-panel">
		<?php echo $logged_in ? gv_st_render_my_tickets_list() : gv_st_render_tracking_form(); ?>
	</div>
	<?php
}

function gv_st_render_new_ticket_form() {
	$u = is_user_logged_in() ? wp_get_current_user() : null;
	?>
	<div class="gvst-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="gv_st_submit_ticket">
			<input type="text" name="gv_st_hp" class="gvst-hp" tabindex="-1" autocomplete="off">
			<?php wp_nonce_field( GV_ST_NONCE, 'gv_st_nonce' ); ?>

			<div class="gvst-row">
				<div class="gvst-field">
					<label>نام و نام خانوادگی</label>
					<input type="text" name="gv_st_name" required value="<?php echo $u ? esc_attr( $u->display_name ) : ''; ?>" <?php echo $u ? 'readonly' : ''; ?>>
				</div>
				<div class="gvst-field">
					<label>ایمیل (پاسخ‌ها به همین ایمیل ارسال می‌شود)</label>
					<input type="email" name="gv_st_email" required value="<?php echo $u ? esc_attr( $u->user_email ) : ''; ?>" <?php echo $u ? 'readonly' : ''; ?>>
				</div>
			</div>
			<div class="gvst-row">
				<div class="gvst-field">
					<label>موضوع تیکت</label>
					<input type="text" name="gv_st_subject" required>
				</div>
				<div class="gvst-field">
					<label>اولویت</label>
					<select name="gv_st_priority">
						<option value="normal">عادی</option>
						<option value="low">کم</option>
						<option value="high">فوری</option>
					</select>
				</div>
			</div>
			<div class="gvst-field">
				<label>توضیحات</label>
				<textarea name="gv_st_message" required placeholder="مشکل یا سوال خود را کامل شرح دهید..."></textarea>
			</div>
			<?php $s = gv_st_get_settings(); if ( ! empty( $s['allow_attachments'] ) ) : ?>
			<div class="gvst-field">
				<label>پیوست (اختیاری — عکس، PDF یا زیپ، حداکثر <?php echo esc_html( $s['max_attachment_mb'] ); ?> مگابایت)</label>
				<input type="file" name="gv_st_attachment">
			</div>
			<?php endif; ?>
			<button type="submit" class="gvst-btn">ارسال تیکت</button>
		</form>
	</div>
	<?php
}

function gv_st_render_tracking_form() {
	?>
	<div class="gvst-card">
		<p style="font-size:13px;color:#64748b;margin-top:0;">کد پیگیری‌ای که هنگام ثبت تیکت دریافت کرده‌اید (یا در ایمیل تاییدیه ارسال شده) را وارد کنید.</p>
		<form method="get" action="">
			<div class="gvst-field">
				<label>کد پیگیری تیکت</label>
				<input type="text" name="gv_key" required placeholder="مثلا: aB3xZ9k...">
			</div>
			<button type="submit" class="gvst-btn">مشاهده تیکت</button>
		</form>
	</div>
	<?php
}

function gv_st_render_my_tickets_list() {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_tickets WHERE user_id = %d ORDER BY updated_at DESC", get_current_user_id()
	) );
	ob_start();
	echo '<div class="gvst-card">';
	if ( empty( $rows ) ) {
		echo '<p style="color:#94a3b8;">تا الان هیچ تیکتی ثبت نکرده‌اید.</p>';
	} else {
		foreach ( $rows as $t ) {
			$url = gv_st_get_ticket_page_url( $t->ticket_key );
			echo '<div class="gvst-list-item">
				<div><b>' . esc_html( $t->subject ) . '</b><br><span style="font-size:11.5px;color:#94a3b8;">آخرین بروزرسانی: ' . esc_html( $t->updated_at ) . '</span></div>
				<div style="display:flex;align-items:center;gap:10px;">
					<span class="gvst-badge" style="background:' . esc_attr( gv_st_status_color( $t->status ) ) . '">' . esc_html( gv_st_status_label( $t->status ) ) . '</span>
					<a href="' . esc_url( $url ) . '" class="gvst-btn gvst-btn-outline">مشاهده</a>
				</div>
			</div>';
		}
	}
	echo '</div>';
	return ob_get_clean();
}

function gv_st_render_thread_view( $key ) {
	$ticket = gv_st_get_ticket_by_key( $key );
	if ( ! $ticket ) {
		echo '<div class="gvst-notice gvst-notice-error">تیکتی با این کد پیگیری یافت نشد.</div>';
		echo '<a href="' . esc_url( remove_query_arg( array( 'gv_key', 'gv_st_msg', 'gv_st_error' ) ) ) . '" class="gvst-btn gvst-btn-outline">بازگشت</a>';
		return;
	}
	$replies = gv_st_get_replies( $ticket->id );
	?>
	<div class="gvst-card" id="gv-st-thread">
		<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
			<div>
				<h3 style="margin:0 0 6px;"><?php echo esc_html( $ticket->subject ); ?></h3>
				<span style="font-size:12px;color:#94a3b8;">کد پیگیری: <bdi style="direction:ltr;"><?php echo esc_html( $ticket->ticket_key ); ?></bdi></span>
			</div>
			<span class="gvst-badge" style="background:<?php echo esc_attr( gv_st_status_color( $ticket->status ) ); ?>"><?php echo esc_html( gv_st_status_label( $ticket->status ) ); ?></span>
		</div>

		<div class="gvst-thread">
			<?php foreach ( $replies as $r ) :
				$is_admin = 'admin' === $r->sender_type;
				?>
				<div class="gvst-msg <?php echo $is_admin ? 'gvst-msg-admin' : 'gvst-msg-user'; ?>">
					<div class="gvst-msg-meta"><?php echo $is_admin ? '🛠️ ' : '👤 '; ?><?php echo esc_html( $r->sender_name ); ?> — <?php echo esc_html( $r->created_at ); ?></div>
					<div><?php echo nl2br( esc_html( $r->message ) ); ?></div>
					<?php if ( $r->attachment_url ) : ?>
						<div class="gvst-att">📎 <a href="<?php echo esc_url( $r->attachment_url ); ?>" target="_blank"><?php echo esc_html( $r->attachment_name ?: 'پیوست' ); ?></a></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( 'closed' !== $ticket->status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-top:10px;">
				<input type="hidden" name="action" value="gv_st_submit_reply">
				<input type="hidden" name="gv_st_key" value="<?php echo esc_attr( $ticket->ticket_key ); ?>">
				<input type="text" name="gv_st_hp" class="gvst-hp" tabindex="-1" autocomplete="off">
				<?php wp_nonce_field( GV_ST_NONCE, 'gv_st_nonce' ); ?>
				<div class="gvst-field">
					<label>پاسخ شما</label>
					<textarea name="gv_st_message" required placeholder="پیام خود را بنویسید..."></textarea>
				</div>
				<?php $s = gv_st_get_settings(); if ( ! empty( $s['allow_attachments'] ) ) : ?>
				<div class="gvst-field">
					<label>پیوست (اختیاری)</label>
					<input type="file" name="gv_st_attachment">
				</div>
				<?php endif; ?>
				<div style="display:flex;gap:10px;flex-wrap:wrap;">
					<button type="submit" class="gvst-btn">ارسال پاسخ</button>
				</div>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;" onsubmit="return confirm('آیا از بستن این تیکت مطمئن هستید؟');">
				<input type="hidden" name="action" value="gv_st_customer_close">
				<input type="hidden" name="gv_st_key" value="<?php echo esc_attr( $ticket->ticket_key ); ?>">
				<?php wp_nonce_field( GV_ST_NONCE, 'gv_st_nonce' ); ?>
				<button type="submit" class="gvst-btn gvst-btn-outline">بستن این تیکت</button>
			</form>
		<?php else : ?>
			<div class="gvst-notice" style="background:#f1f5f9;color:#475569;">این تیکت بسته شده است.</div>
		<?php endif; ?>
	</div>
	<?php
}

/* ==========================================================================
   ۶) منوی مدیریت + پردازش فرم‌های ادمین
   ========================================================================== */
add_action( 'admin_menu', 'gv_st_admin_menu' );
function gv_st_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'تیکت پشتیبانی | Groot Vision',
		'🎫 تیکت پشتیبانی',
		'manage_options',
		GV_ST_PAGE_SLUG,
		'gv_st_render_admin_page'
	);
}

add_action( 'admin_post_gv_st_save_settings', 'gv_st_save_settings' );
function gv_st_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_ST_NONCE );

	$settings = array(
		'enabled'                 => isset( $_POST['enabled'] ) ? 1 : 0,
		'admin_email'             => is_email( $_POST['admin_email'] ?? '' ) ? sanitize_email( $_POST['admin_email'] ) : get_option( 'admin_email' ),
		'from_name'               => sanitize_text_field( $_POST['from_name'] ?? '' ),
		'page_id'                 => intval( $_POST['page_id'] ?? 0 ),
		'notify_admin_new_ticket' => isset( $_POST['notify_admin_new_ticket'] ) ? 1 : 0,
		'notify_admin_new_reply'  => isset( $_POST['notify_admin_new_reply'] ) ? 1 : 0,
		'allow_attachments'       => isset( $_POST['allow_attachments'] ) ? 1 : 0,
		'max_attachment_mb'       => max( 1, min( 50, intval( $_POST['max_attachment_mb'] ?? 5 ) ) ),
	);
	update_option( GV_ST_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=settings&updated=1' ) );
	exit;
}

add_action( 'admin_post_gv_st_admin_reply', 'gv_st_handle_admin_reply' );
function gv_st_handle_admin_reply() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_ST_NONCE );

	$ticket_id = intval( $_POST['ticket_id'] ?? 0 );
	$message   = sanitize_textarea_field( $_POST['message'] ?? '' );
	$new_status = in_array( $_POST['status'] ?? 'answered', array( 'open', 'answered', 'closed' ), true ) ? $_POST['status'] : 'answered';
	$ticket = gv_st_get_ticket_by_id( $ticket_id );
	if ( ! $ticket ) { wp_die( 'تیکت یافت نشد.' ); }

	global $wpdb;
	$now = current_time( 'mysql' );

	if ( ! empty( $message ) ) {
		list( $att_url, $att_name ) = gv_st_handle_attachment_upload();
		$admin = wp_get_current_user();
		$wpdb->insert( $wpdb->prefix . 'gv_ticket_replies', array(
			'ticket_id'       => $ticket->id,
			'sender_type'     => 'admin',
			'sender_name'     => $admin->display_name ?: 'پشتیبانی',
			'message'         => $message,
			'attachment_url'  => $att_url,
			'attachment_name' => $att_name,
			'created_at'      => $now,
		) );
		gv_st_notify_customer_new_reply( $ticket, $message );
	}

	$wpdb->update( $wpdb->prefix . 'gv_tickets', array( 'status' => $new_status, 'updated_at' => $now ), array( 'id' => $ticket->id ) );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=view&id=' . $ticket->id . '&updated=1' ) );
	exit;
}

add_action( 'admin_post_gv_st_delete_ticket', 'gv_st_handle_delete_ticket' );
function gv_st_handle_delete_ticket() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_ST_NONCE );
	global $wpdb;
	$id = intval( $_POST['ticket_id'] ?? 0 );
	$wpdb->delete( $wpdb->prefix . 'gv_ticket_replies', array( 'ticket_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'gv_tickets', array( 'id' => $id ) );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&deleted=1' ) );
	exit;
}

/* ==========================================================================
   ۷) رندر صفحه مدیریت
   ========================================================================== */
function gv_st_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$s   = gv_st_get_settings();
	$tab = $_GET['tab'] ?? 'list';
	?>
	<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif; max-width:1100px;">
		<style>
			.gvsta-header{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,#0e4037,#145c4d);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;flex-wrap:wrap;gap:12px;}
			.gvsta-header h1{margin:0;font-size:20px;color:#fff;}
			.gvsta-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap;}
			.gvsta-tab-btn{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:600;text-decoration:none;color:#1e293b;}
			.gvsta-tab-btn.is-active{background:#0e4037;color:#fff;border-color:#0e4037;}
			.gvsta-stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;}
			@media(max-width:900px){.gvsta-stat-cards{grid-template-columns:repeat(2,1fr);}}
			.gvsta-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px;}
			.gvsta-stat b{display:block;font-size:24px;color:#0e4037;}
			.gvsta-stat span{font-size:12.5px;color:#64748b;}
			.gvsta-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin-bottom:18px;}
			.gvsta-card h2{margin-top:0;font-size:15px;}
			.gvsta-table{width:100%;border-collapse:collapse;}
			.gvsta-table th{background:#f0fdf4;color:#0e4037;padding:9px 12px;text-align:right;font-size:12px;}
			.gvsta-table td{padding:10px 12px;border-top:1px solid #f1f5f9;font-size:12.5px;}
			.gvsta-badge{font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;color:#fff;white-space:nowrap;}
			.gvsta-field{margin-bottom:14px;}
			.gvsta-field label{font-weight:700;font-size:13px;display:block;margin-bottom:5px;}
			.gvsta-field input[type=text],.gvsta-field input[type=email],.gvsta-field input[type=number],.gvsta-field select,.gvsta-field textarea{width:100%;max-width:420px;padding:8px 10px;border-radius:8px;border:1px solid #d1d5db;}
			.gvsta-btn{background:#111827;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;}
			.gvsta-btn-danger{background:#b91c1c;}
			.gvsta-msg{padding:14px 16px;border-radius:12px;margin-bottom:12px;max-width:85%;font-size:13px;line-height:1.9;}
			.gvsta-msg-user{background:#f1f5f4;}
			.gvsta-msg-admin{background:#eff6ff;margin-right:auto;}
			.gvsta-msg-meta{font-size:11px;color:#64748b;margin-bottom:6px;font-weight:700;}
		</style>

		<div class="gvsta-header">
			<h1>🎫 مدیریت تیکت‌های پشتیبانی</h1>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>با موفقیت ذخیره شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تیکت حذف شد.</p></div><?php endif; ?>
		<?php if ( empty( $s['enabled'] ) ) : ?><div class="notice notice-warning"><p>سیستم تیکت در حال حاضر غیرفعال است. از تب «تنظیمات» آن را فعال کنید.</p></div><?php endif; ?>

		<div class="gvsta-tabs">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=list' ) ); ?>" class="gvsta-tab-btn <?php echo 'view' !== $tab && 'settings' !== $tab ? 'is-active' : ''; ?>">📋 لیست تیکت‌ها</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=settings' ) ); ?>" class="gvsta-tab-btn <?php echo 'settings' === $tab ? 'is-active' : ''; ?>">⚙️ تنظیمات</a>
		</div>

		<?php
		if ( 'view' === $tab ) {
			gv_st_render_admin_view_ticket( intval( $_GET['id'] ?? 0 ) );
		} elseif ( 'settings' === $tab ) {
			gv_st_render_admin_settings( $s );
		} else {
			gv_st_render_admin_list();
		}
		?>
		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p>
	</div>
	<?php
}

function gv_st_render_admin_list() {
	global $wpdb;
	$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
	$search        = sanitize_text_field( $_GET['s'] ?? '' );
	$paged         = max( 1, intval( $_GET['paged'] ?? 1 ) );
	$per_page      = 20;
	$offset        = ( $paged - 1 ) * $per_page;

	$where = array( '1=1' );
	$args  = array();
	if ( $status_filter ) { $where[] = 'status = %s'; $args[] = $status_filter; }
	if ( $search ) {
		$where[] = '(subject LIKE %s OR name LIKE %s OR email LIKE %s)';
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$args[] = $like; $args[] = $like; $args[] = $like;
	}
	$where_sql = implode( ' AND ', $where );

	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}gv_tickets WHERE {$where_sql}", $args ) );

	$counts = $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$wpdb->prefix}gv_tickets GROUP BY status", OBJECT_K );
	$open_c     = isset( $counts['open'] ) ? (int) $counts['open']->c : 0;
	$answered_c = isset( $counts['answered'] ) ? (int) $counts['answered']->c : 0;
	$closed_c   = isset( $counts['closed'] ) ? (int) $counts['closed']->c : 0;

	$query_args = array_merge( $args, array( $per_page, $offset ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_tickets WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d", $query_args
	) );
	?>
	<div class="gvsta-stat-cards">
		<div class="gvsta-stat"><b><?php echo esc_html( $open_c + $answered_c + $closed_c ); ?></b><span>کل تیکت‌ها</span></div>
		<div class="gvsta-stat"><b style="color:#b45309;"><?php echo esc_html( $open_c ); ?></b><span>در انتظار پاسخ</span></div>
		<div class="gvsta-stat"><b style="color:#15803d;"><?php echo esc_html( $answered_c ); ?></b><span>پاسخ داده‌شده</span></div>
		<div class="gvsta-stat"><b style="color:#64748b;"><?php echo esc_html( $closed_c ); ?></b><span>بسته‌شده</span></div>
	</div>

	<div class="gvsta-card">
		<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
			<input type="hidden" name="page" value="<?php echo esc_attr( GV_ST_PAGE_SLUG ); ?>">
			<select name="status">
				<option value="">همه وضعیت‌ها</option>
				<option value="open" <?php selected( $status_filter, 'open' ); ?>>در انتظار پاسخ</option>
				<option value="answered" <?php selected( $status_filter, 'answered' ); ?>>پاسخ داده‌شده</option>
				<option value="closed" <?php selected( $status_filter, 'closed' ); ?>>بسته‌شده</option>
			</select>
			<input type="text" name="s" placeholder="جستجو در موضوع، نام یا ایمیل..." value="<?php echo esc_attr( $search ); ?>" style="min-width:220px;">
			<button type="submit" class="gvsta-btn">فیلتر</button>
		</form>

		<?php if ( empty( $rows ) ) : ?>
			<p style="color:#94a3b8;">تیکتی یافت نشد.</p>
		<?php else : ?>
			<table class="gvsta-table">
				<thead><tr><th>موضوع</th><th>نام / ایمیل</th><th>اولویت</th><th>وضعیت</th><th>آخرین بروزرسانی</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $t ) : ?>
					<tr>
						<td><?php echo esc_html( $t->subject ); ?></td>
						<td><?php echo esc_html( $t->name ); ?><br><span style="color:#94a3b8;direction:ltr;display:inline-block;"><?php echo esc_html( $t->email ); ?></span></td>
						<td><?php echo esc_html( gv_st_priority_label( $t->priority ) ); ?></td>
						<td><span class="gvsta-badge" style="background:<?php echo esc_attr( gv_st_status_color( $t->status ) ); ?>"><?php echo esc_html( gv_st_status_label( $t->status ) ); ?></span></td>
						<td><?php echo esc_html( $t->updated_at ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=view&id=' . $t->id ) ); ?>" class="gvsta-btn">مشاهده</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$total_pages = (int) ceil( $total / $per_page );
			if ( $total_pages > 1 ) {
				echo '<div style="margin-top:14px;display:flex;gap:6px;">';
				for ( $p = 1; $p <= $total_pages; $p++ ) {
					$url = add_query_arg( array( 'paged' => $p ) );
					echo '<a href="' . esc_url( $url ) . '" class="gvsta-btn" style="' . ( $p === $paged ? '' : 'background:#e2e8f0;color:#1e293b !important;' ) . '">' . esc_html( $p ) . '</a>';
				}
				echo '</div>';
			}
			?>
		<?php endif; ?>
	</div>
	<?php
}

function gv_st_render_admin_view_ticket( $id ) {
	$ticket = gv_st_get_ticket_by_id( $id );
	if ( ! $ticket ) {
		echo '<div class="gvsta-card"><p>تیکت یافت نشد.</p></div>';
		return;
	}
	$replies = gv_st_get_replies( $ticket->id );
	?>
	<div class="gvsta-card">
		<div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
			<div>
				<h2 style="margin:0 0 6px;"><?php echo esc_html( $ticket->subject ); ?></h2>
				<span style="font-size:12.5px;color:#64748b;"><?php echo esc_html( $ticket->name ); ?> — <bdi style="direction:ltr;"><?php echo esc_html( $ticket->email ); ?></bdi> — اولویت: <?php echo esc_html( gv_st_priority_label( $ticket->priority ) ); ?></span>
			</div>
			<span class="gvsta-badge" style="background:<?php echo esc_attr( gv_st_status_color( $ticket->status ) ); ?>;height:fit-content;"><?php echo esc_html( gv_st_status_label( $ticket->status ) ); ?></span>
		</div>

		<div style="max-height:460px;overflow-y:auto;margin-bottom:18px;padding:6px;">
			<?php foreach ( $replies as $r ) :
				$is_admin = 'admin' === $r->sender_type;
				?>
				<div class="gvsta-msg <?php echo $is_admin ? 'gvsta-msg-admin' : 'gvsta-msg-user'; ?>">
					<div class="gvsta-msg-meta"><?php echo $is_admin ? '🛠️ ' : '👤 '; ?><?php echo esc_html( $r->sender_name ); ?> — <?php echo esc_html( $r->created_at ); ?></div>
					<div><?php echo nl2br( esc_html( $r->message ) ); ?></div>
					<?php if ( $r->attachment_url ) : ?>
						<div style="margin-top:8px;font-size:12px;">📎 <a href="<?php echo esc_url( $r->attachment_url ); ?>" target="_blank"><?php echo esc_html( $r->attachment_name ?: 'پیوست' ); ?></a></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="gv_st_admin_reply">
			<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
			<?php wp_nonce_field( GV_ST_NONCE ); ?>
			<div class="gvsta-field">
				<label>پاسخ به مشتری (ایمیل به آدرس ثبت‌شده ارسال می‌شود)</label>
				<textarea name="message" rows="5" style="max-width:100%;"></textarea>
			</div>
			<?php $s = gv_st_get_settings(); if ( ! empty( $s['allow_attachments'] ) ) : ?>
			<div class="gvsta-field">
				<label>پیوست (اختیاری)</label>
				<input type="file" name="gv_st_attachment">
			</div>
			<?php endif; ?>
			<div class="gvsta-field">
				<label>وضعیت تیکت پس از این عملیات</label>
				<select name="status">
					<option value="answered" selected>پاسخ داده‌شده</option>
					<option value="open">باز نگه‌دار (در انتظار پاسخ)</option>
					<option value="closed">بستن تیکت</option>
				</select>
			</div>
			<button type="submit" class="gvsta-btn">💾 ثبت و ارسال</button>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('این تیکت برای همیشه حذف شود؟');" style="margin-top:12px;">
			<input type="hidden" name="action" value="gv_st_delete_ticket">
			<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
			<?php wp_nonce_field( GV_ST_NONCE ); ?>
			<button type="submit" class="gvsta-btn gvsta-btn-danger">🗑️ حذف تیکت</button>
		</form>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG ) ); ?>" style="display:inline-block;margin-top:12px;">← بازگشت به لیست</a>
	</div>
	<?php
}

function gv_st_render_admin_settings( $s ) {
	$pages = get_pages( array( 'post_status' => 'publish' ) );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="gv_st_save_settings">
		<?php wp_nonce_field( GV_ST_NONCE ); ?>
		<div class="gvsta-card">
			<h2>روشن/خاموش</h2>
			<div class="gvsta-field"><label><input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>> فعال‌سازی سیستم تیکت</label></div>
		</div>

		<div class="gvsta-card">
			<h2>صفحه نمایش فرم تیکت</h2>
			<p style="font-size:12.5px;color:#64748b;">ابتدا یک صفحه (Page) بسازید (مثلاً «پشتیبانی») و کد <code>[gv_tickets]</code> را داخل آن قرار دهید، سپس همان صفحه را از لیست زیر انتخاب کنید تا لینک‌های داخل ایمیل‌ها درست کار کنند.</p>
			<div class="gvsta-field">
				<label>صفحه تیکت</label>
				<select name="page_id">
					<option value="0">— انتخاب کنید —</option>
					<?php foreach ( $pages as $p ) : ?>
						<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $s['page_id'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="gvsta-card">
			<h2>اعلان‌های ایمیلی</h2>
			<div class="gvsta-field">
				<label>ایمیل مدیر برای دریافت اعلان تیکت‌های جدید</label>
				<input type="email" name="admin_email" value="<?php echo esc_attr( $s['admin_email'] ); ?>">
			</div>
			<div class="gvsta-field">
				<label>نام فرستنده ایمیل‌ها</label>
				<input type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>">
			</div>
			<div class="gvsta-field"><label><input type="checkbox" name="notify_admin_new_ticket" <?php checked( $s['notify_admin_new_ticket'], 1 ); ?>> اطلاع‌رسانی به مدیر هنگام ثبت تیکت جدید</label></div>
			<div class="gvsta-field"><label><input type="checkbox" name="notify_admin_new_reply" <?php checked( $s['notify_admin_new_reply'], 1 ); ?>> اطلاع‌رسانی به مدیر هنگام پاسخ جدید کاربر</label></div>
			<p style="font-size:12px;color:#94a3b8;">توجه: پاسخ ادمین همیشه به‌صورت خودکار به ایمیلی که کاربر هنگام ثبت تیکت وارد کرده ارسال می‌شود.</p>
		</div>

		<div class="gvsta-card">
			<h2>پیوست فایل</h2>
			<div class="gvsta-field"><label><input type="checkbox" name="allow_attachments" <?php checked( $s['allow_attachments'], 1 ); ?>> اجازه‌ی آپلود پیوست (عکس، PDF، زیپ)</label></div>
			<div class="gvsta-field">
				<label>حداکثر حجم پیوست (مگابایت)</label>
				<input type="number" name="max_attachment_mb" min="1" max="50" value="<?php echo esc_attr( $s['max_attachment_mb'] ); ?>" style="max-width:120px;">
			</div>
		</div>

		<button type="submit" class="gvsta-btn">💾 ذخیره تنظیمات</button>
	</form>
	<?php
}