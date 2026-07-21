<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — سیستم تیکت پشتیبانی (نسخه چت‌محور و حرفه‌ای)
 *  ------------------------------------------------------------
 *  - فضای دو ستونه شبیه اپ‌های چت: لیست تیکت‌ها در کنار + پنجره گفتگو
 *  - دسته‌بندی تیکت‌ها (قابل مدیریت از تنظیمات)
 *  - تخصیص هر تیکت به یک کارشناس (کاربر ادمین/کارشناس سایت)
 *  - بج تعداد تیکتِ خوانده‌نشده روی منوی پیشخوان + اندپوینت AJAX آماده
 *    برای گسترش به نوتیفیکیشن واقعی (push/تلگرام/پیامک و ...)
 *  - همه‌چیز دیگر (ایمیل خودکار، پیوست، جدول‌های دیتابیس) مثل قبل هست
 * ==========================================================
 */

define( 'GV_ST_OPT', 'gv_support_tickets_settings' );
define( 'GV_ST_NONCE', 'gv_st_nonce_action' );
define( 'GV_ST_DB_VERSION', '1.1' );
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
		'show_agent_to_customer'  => 1, // نام کارشناس مسئول به کاربر نمایش داده شود
		'agent_roles'             => array( 'administrator' ), // نقش‌هایی که به‌عنوان کارشناس در لیست تخصیص نمایش داده می‌شوند
		'categories'              => gv_st_default_categories(),
		'admin_badge_enabled'     => 1, // بج تعداد تیکت خوانده‌نشده روی منوی پیشخوان
		'admin_poll_enabled'      => 1, // بررسی خودکار (AJAX) تیکت جدید در پیشخوان
		'admin_poll_interval'     => 25, // ثانیه
	);
}

function gv_st_default_categories() {
	return array(
		array( 'slug' => 'general',   'label' => 'عمومی' ),
		array( 'slug' => 'billing',   'label' => 'مالی و پرداخت' ),
		array( 'slug' => 'technical', 'label' => 'فنی و مشکلات سایت' ),
	);
}

function gv_st_get_settings() {
	$s = wp_parse_args( get_option( GV_ST_OPT, array() ), gv_st_default_settings() );
	if ( empty( $s['categories'] ) || ! is_array( $s['categories'] ) ) {
		$s['categories'] = gv_st_default_categories();
	}
	if ( empty( $s['agent_roles'] ) || ! is_array( $s['agent_roles'] ) ) {
		$s['agent_roles'] = array( 'administrator' );
	}
	return $s;
}

/* ==========================================================================
   ۱) ساخت / بروزرسانی جداول دیتابیس
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
		category VARCHAR(60) NOT NULL DEFAULT 'general',
		assigned_to BIGINT UNSIGNED NOT NULL DEFAULT 0,
		priority VARCHAR(20) NOT NULL DEFAULT 'normal',
		status VARCHAR(20) NOT NULL DEFAULT 'open',
		is_read_admin TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY ticket_key (ticket_key),
		KEY user_id (user_id),
		KEY email (email),
		KEY status (status),
		KEY category (category),
		KEY assigned_to (assigned_to)
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

function gv_st_priority_color( $priority ) {
	$colors = array(
		'low'    => '#64748b',
		'normal' => '#2563eb',
		'high'   => '#dc2626',
	);
	return $colors[ $priority ] ?? '#2563eb';
}

/** برچسب فارسی یک دسته بر اساس اسلاگ آن */
function gv_st_category_label( $slug ) {
	$s = gv_st_get_settings();
	foreach ( $s['categories'] as $c ) {
		if ( $c['slug'] === $slug ) { return $c['label']; }
	}
	return $slug ?: '—';
}

/** لیست کاربرانی که می‌توانند به‌عنوان «کارشناس» تیکت به آن‌ها تخصیص داده شود */
function gv_st_get_agents() {
	$s = gv_st_get_settings();
	$roles = ! empty( $s['agent_roles'] ) ? $s['agent_roles'] : array( 'administrator' );
	return get_users( array( 'role__in' => $roles, 'orderby' => 'display_name' ) );
}

function gv_st_get_agent_name( $user_id ) {
	if ( empty( $user_id ) ) { return ''; }
	$u = get_userdata( $user_id );
	return $u ? $u->display_name : '';
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

/** آخرین پیام یک تیکت — برای نمایش پیش‌نمایش در لیست چت‌محور */
function gv_st_get_last_message( $ticket_id ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare(
		"SELECT message FROM {$wpdb->prefix}gv_ticket_replies WHERE ticket_id = %d ORDER BY created_at DESC LIMIT 1", intval( $ticket_id )
	) );
}

function gv_st_get_ticket_page_url( $key = '' ) {
	$s = gv_st_get_settings();
	if ( ! empty( $s['page_id'] ) && get_post_status( $s['page_id'] ) ) {
		$url = get_permalink( $s['page_id'] );
	} elseif ( function_exists( 'wc_get_account_endpoint_url' ) ) {
		$url = wc_get_account_endpoint_url( 'tickets' );
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

/** تعداد تیکت‌های خوانده‌نشده توسط ادمین — پایه‌ی بج نوتیفیکیشن */
function gv_st_get_unread_count() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gv_tickets WHERE is_read_admin = 0" );
}

function gv_st_mark_ticket_read( $ticket_id ) {
	global $wpdb;
	$wpdb->update( $wpdb->prefix . 'gv_tickets', array( 'is_read_admin' => 1 ), array( 'id' => intval( $ticket_id ) ) );
}

function gv_st_mark_ticket_unread( $ticket_id ) {
	global $wpdb;
	$wpdb->update( $wpdb->prefix . 'gv_tickets', array( 'is_read_admin' => 0 ), array( 'id' => intval( $ticket_id ) ) );
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
		دسته‌بندی: ' . esc_html( gv_st_category_label( $ticket->category ) ) . '<br>
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

	$valid_categories = wp_list_pluck( $s['categories'], 'slug' );
	$category = in_array( $_POST['gv_st_category'] ?? '', $valid_categories, true ) ? $_POST['gv_st_category'] : ( $valid_categories[0] ?? 'general' );

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
		'ticket_key'    => $key,
		'user_id'       => get_current_user_id(),
		'name'          => $name,
		'email'         => $email,
		'subject'       => $subject,
		'category'      => $category,
		'assigned_to'   => 0,
		'priority'      => $priority,
		'status'        => 'open',
		'is_read_admin' => 0,
		'created_at'    => $now,
		'updated_at'    => $now,
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

	/**
	 * نقطه‌ی اتصال برای گسترش نوتیفیکیشن تیکت جدید در آینده
	 * (مثلاً ارسال به تلگرام، پیامک، یا نوتیفیکیشن push در پیشخوان).
	 * فقط کافیست به این اکشن هوک بزنید:
	 * add_action( 'gv_st_new_ticket', function( $ticket ) { ... } );
	 */
	do_action( 'gv_st_new_ticket', $ticket );

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
	$wpdb->update( $wpdb->prefix . 'gv_tickets', array( 'status' => 'open', 'is_read_admin' => 0, 'updated_at' => $now ), array( 'id' => $ticket->id ) );

	do_action( 'gv_st_new_reply', $ticket, $message, 'user' );

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
   ۵) شورت‌کد فرانت‌اند [gv_tickets] — فضای چت‌محور دو ستونه
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
		.gvst-box, .gvst-box *{ box-sizing:border-box; }
		.gvst-box{
			--gv-primary:#0e6b53;
			--gv-primary-dark:#0a4a3a;
			--gv-ink:#1b2430;
			--gv-muted:#6b7684;
			--gv-border:#e6e9ee;
			--gv-bg-soft:#f6f8f7;
			font-family:'Vazirmatn','Yekan Bakh',Tahoma,Arial,sans-serif !important;
			max-width:1180px;margin:0 auto;direction:rtl;text-align:right;
			color:var(--gv-ink) !important;
			background:transparent;
			line-height:1.9;
		}
		.gvst-box h1,.gvst-box h2,.gvst-box h3,.gvst-box h4,
		.gvst-box p,.gvst-box span,.gvst-box div,.gvst-box label,.gvst-box li,.gvst-box a,.gvst-box button{
			color:var(--gv-ink) !important;
		}

		.gvst-notice{padding:13px 18px;border-radius:12px;font-size:13px;margin-bottom:16px;font-weight:600;}
		.gvst-notice-success{background:#e3f8ec !important;color:#146c3e !important;}
		.gvst-notice-error{background:#fdeaea !important;color:#9c2b23 !important;}

		/* ---------- فضای اصلی: دو ستون ---------- */
		.gvst-app{
			display:grid;grid-template-columns:360px 1fr;
			border:1px solid var(--gv-border) !important;border-radius:20px;overflow:hidden;
			min-height:680px;background:#fff !important;box-shadow:0 10px 34px rgba(15,23,42,.06);
		}
		@media(max-width:900px){ .gvst-app{ grid-template-columns:1fr; min-height:0; } }

		/* ستون کناری: لیست تیکت‌ها */
		.gvst-side{
			background:var(--gv-bg-soft) !important;border-left:1px solid var(--gv-border) !important;
			display:flex;flex-direction:column;max-height:680px;
		}
		@media(max-width:900px){ .gvst-side{ max-height:none; border-left:none;border-bottom:1px solid var(--gv-border) !important; } }
		.gvst-side-head{ padding:20px 20px 14px; }
		.gvst-side-head h3{ margin:0 0 14px;font-size:16px; }
		.gvst-newbtn{
			width:100%;background:var(--gv-primary) !important;color:#fff !important;border:none;
			padding:13px 16px;border-radius:12px;font-weight:700;cursor:pointer;font-size:13.5px;
			display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s ease;
		}
		.gvst-newbtn:hover{ background:var(--gv-primary-dark) !important; }
		.gvst-side-filters{ display:flex;gap:8px;padding:0 20px 14px;flex-wrap:wrap; }
		.gvst-side-filters select{
			flex:1;min-width:100px;padding:8px 10px;border:1px solid var(--gv-border) !important;border-radius:9px;
			font-size:12px;background:#fff !important;color:var(--gv-ink) !important;
		}
		.gvst-side-list{ flex:1;overflow-y:auto;padding:0 12px 16px; }
		.gvst-ticket-item{
			display:block;text-decoration:none !important;padding:14px 14px;border-radius:14px;margin-bottom:8px;
			background:#fff !important;border:1px solid var(--gv-border) !important;transition:all .15s ease;
		}
		.gvst-ticket-item:hover{ border-color:var(--gv-primary) !important; }
		.gvst-ticket-item.is-active{ background:var(--gv-primary) !important;border-color:var(--gv-primary) !important; }
		.gvst-ticket-item.is-active *{ color:#fff !important; }
		.gvst-ti-top{ display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:5px; }
		.gvst-ti-subject{ font-weight:700;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
		.gvst-ti-time{ font-size:10.5px;color:var(--gv-muted) !important;white-space:nowrap; }
		.gvst-ticket-item.is-active .gvst-ti-time{ color:rgba(255,255,255,.8) !important; }
		.gvst-ti-preview{ font-size:12px;color:var(--gv-muted) !important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px; }
		.gvst-ticket-item.is-active .gvst-ti-preview{ color:rgba(255,255,255,.85) !important; }
		.gvst-ti-tags{ display:flex;gap:6px;flex-wrap:wrap; }
		.gvst-chip{
			font-size:10px;font-weight:700;padding:3px 9px;border-radius:20px;color:#fff !important;white-space:nowrap;
		}
		.gvst-chip-outline{ background:#fff !important;border:1px solid var(--gv-border) !important;color:var(--gv-muted) !important; }
		.gvst-ticket-item.is-active .gvst-chip-outline{ background:rgba(255,255,255,.15) !important;border-color:rgba(255,255,255,.4) !important;color:#fff !important; }
		.gvst-empty{ color:var(--gv-muted) !important;font-size:13px;text-align:center;padding:30px 16px; }

		/* ستون اصلی: گفتگو / فرم ثبت تیکت */
		.gvst-main{ display:flex;flex-direction:column;max-height:680px; }
		@media(max-width:900px){ .gvst-main{ max-height:none; } }
		.gvst-main-empty{ flex:1;display:flex;align-items:center;justify-content:center;color:var(--gv-muted) !important;font-size:14px;padding:40px; text-align:center;}

		.gvst-chat-head{
			padding:18px 24px;border-bottom:1px solid var(--gv-border) !important;
			display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;
		}
		.gvst-chat-head h2{ margin:0 0 8px;font-size:16.5px; }
		.gvst-chat-meta{ display:flex;gap:8px;flex-wrap:wrap;align-items:center; }
		.gvst-chat-meta span{ font-size:11.5px;color:var(--gv-muted) !important; }

		.gvst-thread{ flex:1;overflow-y:auto;padding:22px 24px; }
		.gvst-msg-row{ display:flex;margin-bottom:16px; }
		.gvst-msg-row.is-user{ justify-content:flex-end; }
		.gvst-msg-row.is-admin{ justify-content:flex-start; }
		.gvst-msg{ max-width:72%;padding:13px 17px;border-radius:16px;font-size:13.5px;line-height:1.95; }
		.gvst-msg-row.is-user .gvst-msg{ background:var(--gv-primary) !important;color:#fff !important;border-bottom-left-radius:4px; }
		.gvst-msg-row.is-user .gvst-msg *{ color:#fff !important; }
		.gvst-msg-row.is-admin .gvst-msg{ background:var(--gv-bg-soft) !important;border:1px solid var(--gv-border) !important;border-bottom-right-radius:4px; }
		.gvst-msg-meta{ font-size:10.5px;margin-bottom:6px;font-weight:700;opacity:.85; }
		.gvst-att{ display:inline-block;margin-top:9px;font-size:11.5px; }
		.gvst-att a{ font-weight:700;text-decoration:underline; }

		.gvst-composer{ border-top:1px solid var(--gv-border) !important;padding:16px 20px; }
		.gvst-composer form{ display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap; }
		.gvst-composer textarea{
			flex:1;min-width:200px;padding:12px 14px;border:1px solid var(--gv-border) !important;border-radius:14px;
			font-family:inherit !important;font-size:13.5px;background:#fff !important;color:var(--gv-ink) !important;
			resize:none;min-height:48px;max-height:120px;
		}
		.gvst-composer textarea:focus{ outline:none;border-color:var(--gv-primary) !important;box-shadow:0 0 0 3px rgba(14,107,83,.12); }
		.gvst-composer .gvst-file-label{
			display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:12px;
			border:1px solid var(--gv-border) !important;cursor:pointer;font-size:16px;background:#fff !important;flex-shrink:0;
		}
		.gvst-composer input[type=file]{ display:none; }
		.gvst-send-btn{
			background:var(--gv-primary) !important;color:#fff !important;border:none;width:44px;height:44px;border-radius:12px;
			cursor:pointer;font-size:16px;flex-shrink:0;transition:background .15s ease;
		}
		.gvst-send-btn:hover{ background:var(--gv-primary-dark) !important; }
		.gvst-closed-note{ padding:14px 18px;text-align:center;font-size:12.5px;color:var(--gv-muted) !important;background:var(--gv-bg-soft) !important;border-radius:12px; }
		.gvst-close-link{ font-size:11.5px;color:#c0342a !important;text-decoration:underline;cursor:pointer;background:none;border:none;padding:0; }

		/* فرم ثبت تیکت جدید و فرم پیگیری مهمان */
		.gvst-card{ background:#fff !important;border:1px solid var(--gv-border) !important;border-radius:16px;padding:26px; }
		.gvst-field{ margin-bottom:16px; }
		.gvst-field label{ display:block;font-weight:700;font-size:13px;margin-bottom:7px; }
		.gvst-field input[type=text], .gvst-field input[type=email], .gvst-field select, .gvst-field textarea, .gvst-field input[type=file]{
			width:100%;padding:11px 14px;border:1px solid #d7dce2 !important;border-radius:10px;
			font-family:inherit !important;font-size:13.5px;background:#fff !important;color:var(--gv-ink) !important;
		}
		.gvst-field input:focus,.gvst-field select:focus,.gvst-field textarea:focus{ outline:none;border-color:var(--gv-primary) !important;box-shadow:0 0 0 3px rgba(14,107,83,.12); }
		.gvst-field input[readonly]{ background:var(--gv-bg-soft) !important;color:var(--gv-muted) !important; }
		.gvst-field textarea{ min-height:120px;resize:vertical; }
		.gvst-row3{ display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px; }
		@media(max-width:700px){ .gvst-row3{ grid-template-columns:1fr; } }
		.gvst-btn{
			background:var(--gv-primary) !important;color:#fff !important;border:none;
			padding:12px 28px;border-radius:11px;font-weight:700;cursor:pointer;font-size:13.5px;
			text-decoration:none;display:inline-block;
		}
		.gvst-btn:hover{ background:var(--gv-primary-dark) !important; }
		.gvst-hp{ position:absolute;left:-9999px;top:-9999px; }
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

		gv_st_render_app( $view_key );
		?>
	</div>
	<?php
	return ob_get_clean();
}

/** رندر اصلیِ فضای دو ستونه: سایدبار لیست تیکت‌ها + پنل اصلی گفتگو/فرم */
function gv_st_render_app( $view_key ) {
	$logged_in = is_user_logged_in();

	// لیست تیکت‌های قابل‌نمایش در سایدبار: فقط برای کاربران عضو (چون مهمان کد پیگیری جدا دارد)
	global $wpdb;
	$my_tickets = array();
	if ( $logged_in ) {
		$my_tickets = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gv_tickets WHERE user_id = %d ORDER BY updated_at DESC", get_current_user_id()
		) );
	} elseif ( $view_key ) {
		// کاربر مهمان با کد پیگیری وارد شده — همان یک تیکت به‌صورت لیست تک‌آیتمی نمایش داده می‌شود
		$t = gv_st_get_ticket_by_key( $view_key );
		if ( $t ) { $my_tickets = array( $t ); }
	}
	?>
	<div class="gvst-app">
		<div class="gvst-side">
			<div class="gvst-side-head">
				<h3>🎫 تیکت‌های پشتیبانی</h3>
				<button type="button" class="gvst-newbtn" onclick="document.getElementById('gvst-new-panel').style.display='flex';document.querySelectorAll('.gvst-ticket-item').forEach(function(x){x.classList.remove('is-active');});">
					➕ ثبت تیکت جدید
				</button>
			</div>
			<div class="gvst-side-list">
				<?php if ( empty( $my_tickets ) ) : ?>
					<p class="gvst-empty"><?php echo $logged_in ? 'هنوز تیکتی ثبت نکرده‌اید.' : 'برای مشاهده‌ی تیکت قبلی، از فرم «پیگیری تیکت» کد پیگیری خود را وارد کنید.'; ?></p>
					<?php if ( ! $logged_in ) : gv_st_render_tracking_mini_form(); endif; ?>
				<?php else : ?>
					<?php foreach ( $my_tickets as $t ) :
						$is_active = ( $t->ticket_key === $view_key );
						$preview   = gv_st_get_last_message( $t->id );
						$preview   = $preview ? mb_substr( wp_strip_all_tags( $preview ), 0, 46 ) . ( mb_strlen( $preview ) > 46 ? '…' : '' ) : '';
						?>
						<a href="<?php echo esc_url( gv_st_get_ticket_page_url( $t->ticket_key ) ); ?>" class="gvst-ticket-item <?php echo $is_active ? 'is-active' : ''; ?>">
							<div class="gvst-ti-top">
								<span class="gvst-ti-subject"><?php echo esc_html( $t->subject ); ?></span>
								<span class="gvst-ti-time"><?php echo esc_html( mysql2date( 'Y/m/d', $t->updated_at ) ); ?></span>
							</div>
							<?php if ( $preview ) : ?><div class="gvst-ti-preview"><?php echo esc_html( $preview ); ?></div><?php endif; ?>
							<div class="gvst-ti-tags">
								<span class="gvst-chip" style="background:<?php echo esc_attr( gv_st_status_color( $t->status ) ); ?>"><?php echo esc_html( gv_st_status_label( $t->status ) ); ?></span>
								<span class="gvst-chip gvst-chip-outline"><?php echo esc_html( gv_st_category_label( $t->category ) ); ?></span>
							</div>
						</a>
					<?php endforeach; ?>
					<?php if ( ! $logged_in ) : gv_st_render_tracking_mini_form(); endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="gvst-main">
			<?php if ( $view_key ) : ?>
				<?php gv_st_render_thread_view( $view_key ); ?>
			<?php else : ?>
				<div id="gvst-new-panel" style="display:flex;flex:1;align-items:center;justify-content:center;padding:24px;">
					<div style="width:100%;max-width:560px;">
						<?php gv_st_render_new_ticket_form(); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

function gv_st_render_tracking_mini_form() {
	?>
	<form method="get" action="" style="padding:10px 6px 4px;">
		<div class="gvst-field" style="margin-bottom:8px;">
			<label style="font-size:11.5px;">پیگیری با کد تیکت (مهمان)</label>
			<input type="text" name="gv_key" required placeholder="کد پیگیری..." style="padding:9px 12px;font-size:12px;">
		</div>
		<button type="submit" class="gvst-btn" style="width:100%;padding:9px;font-size:12.5px;">مشاهده</button>
	</form>
	<?php
}

function gv_st_render_new_ticket_form() {
	$u = is_user_logged_in() ? wp_get_current_user() : null;
	$s = gv_st_get_settings();
	?>
	<div class="gvst-card">
		<h3 style="margin:0 0 18px;">ثبت تیکت جدید</h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="gv_st_submit_ticket">
			<input type="text" name="gv_st_hp" class="gvst-hp" tabindex="-1" autocomplete="off">
			<?php wp_nonce_field( GV_ST_NONCE, 'gv_st_nonce' ); ?>

			<div class="gvst-field">
				<label>نام و نام خانوادگی</label>
				<input type="text" name="gv_st_name" required value="<?php echo $u ? esc_attr( $u->display_name ) : ''; ?>" <?php echo $u ? 'readonly' : ''; ?>>
			</div>
			<div class="gvst-field">
				<label>ایمیل (پاسخ‌ها به همین ایمیل ارسال می‌شود)</label>
				<input type="email" name="gv_st_email" required value="<?php echo $u ? esc_attr( $u->user_email ) : ''; ?>" <?php echo $u ? 'readonly' : ''; ?>>
			</div>
			<div class="gvst-field">
				<label>موضوع تیکت</label>
				<input type="text" name="gv_st_subject" required>
			</div>
			<div class="gvst-row3">
				<div class="gvst-field">
					<label>دسته‌بندی</label>
					<select name="gv_st_category">
						<?php foreach ( $s['categories'] as $c ) : ?>
							<option value="<?php echo esc_attr( $c['slug'] ); ?>"><?php echo esc_html( $c['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="gvst-field">
					<label>اولویت</label>
					<select name="gv_st_priority">
						<option value="normal">عادی</option>
						<option value="low">کم</option>
						<option value="high">فوری</option>
					</select>
				</div>
				<?php if ( ! empty( $s['allow_attachments'] ) ) : ?>
				<div class="gvst-field">
					<label>پیوست (اختیاری)</label>
					<input type="file" name="gv_st_attachment">
				</div>
				<?php endif; ?>
			</div>
			<div class="gvst-field">
				<label>توضیحات</label>
				<textarea name="gv_st_message" required placeholder="مشکل یا سوال خود را کامل شرح دهید..."></textarea>
			</div>
			<button type="submit" class="gvst-btn">ارسال تیکت</button>
		</form>
	</div>
	<?php
}

function gv_st_render_thread_view( $key ) {
	$ticket = gv_st_get_ticket_by_key( $key );
	if ( ! $ticket ) {
		echo '<div class="gvst-main-empty">تیکتی با این کد پیگیری یافت نشد.</div>';
		return;
	}
	$replies = gv_st_get_replies( $ticket->id );
	$s       = gv_st_get_settings();
	$agent   = ! empty( $s['show_agent_to_customer'] ) ? gv_st_get_agent_name( $ticket->assigned_to ) : '';
	?>
	<div class="gvst-chat-head" id="gv-st-thread">
		<div>
			<h2><?php echo esc_html( $ticket->subject ); ?></h2>
			<div class="gvst-chat-meta">
				<span class="gvst-chip" style="background:<?php echo esc_attr( gv_st_status_color( $ticket->status ) ); ?>"><?php echo esc_html( gv_st_status_label( $ticket->status ) ); ?></span>
				<span class="gvst-chip gvst-chip-outline"><?php echo esc_html( gv_st_category_label( $ticket->category ) ); ?></span>
				<span>کد پیگیری: <bdi style="direction:ltr;"><?php echo esc_html( $ticket->ticket_key ); ?></bdi></span>
				<?php if ( $agent ) : ?><span>👤 کارشناس مسئول: <b><?php echo esc_html( $agent ); ?></b></span><?php endif; ?>
			</div>
		</div>
		<?php if ( 'closed' !== $ticket->status ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('آیا از بستن این تیکت مطمئن هستید؟');">
			<input type="hidden" name="action" value="gv_st_customer_close">
			<input type="hidden" name="gv_st_key" value="<?php echo esc_attr( $ticket->ticket_key ); ?>">
			<?php wp_nonce_field( GV_ST_NONCE, 'gv_st_nonce' ); ?>
			<button type="submit" class="gvst-close-link">بستن تیکت</button>
		</form>
		<?php endif; ?>
	</div>

	<div class="gvst-thread" id="gvst-thread-scroll">
		<?php foreach ( $replies as $r ) :
			$is_admin = 'admin' === $r->sender_type;
			?>
			<div class="gvst-msg-row <?php echo $is_admin ? 'is-admin' : 'is-user'; ?>">
				<div class="gvst-msg">
					<div class="gvst-msg-meta"><?php echo $is_admin ? '🛠️ ' : '👤 '; ?><?php echo esc_html( $r->sender_name ); ?> — <?php echo esc_html( mysql2date( 'Y/m/d H:i', $r->created_at ) ); ?></div>
					<div><?php echo nl2br( esc_html( $r->message ) ); ?></div>
					<?php if ( $r->attachment_url ) : ?>
						<div class="gvst-att">📎 <a href="<?php echo esc_url( $r->attachment_url ); ?>" target="_blank"><?php echo esc_html( $r->attachment_name ?: 'پیوست' ); ?></a></div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="gvst-composer">
		<?php if ( 'closed' !== $ticket->status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="gv_st_submit_reply">
				<input type="hidden" name="gv_st_key" value="<?php echo esc_attr( $ticket->ticket_key ); ?>">
				<input type="text" name="gv_st_hp" class="gvst-hp" tabindex="-1" autocomplete="off">
				<?php wp_nonce_field( GV_ST_NONCE, 'gv_st_nonce' ); ?>
				<?php $s = gv_st_get_settings(); if ( ! empty( $s['allow_attachments'] ) ) : ?>
				<label class="gvst-file-label" title="پیوست فایل">
					📎<input type="file" name="gv_st_attachment">
				</label>
				<?php endif; ?>
				<textarea name="gv_st_message" required placeholder="پیام خود را بنویسید..." onkeydown="if(event.keyCode===13 && !event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
				<button type="submit" class="gvst-send-btn" title="ارسال">➤</button>
			</form>
		<?php else : ?>
			<div class="gvst-closed-note">این تیکت بسته شده است و امکان ارسال پیام جدید وجود ندارد.</div>
		<?php endif; ?>
	</div>
	<script>
	(function(){
		var box = document.getElementById('gvst-thread-scroll');
		if (box) { box.scrollTop = box.scrollHeight; }
	})();
	</script>
	<?php
}

/* ==========================================================================
   ۶) منوی مدیریت + بج تعداد خوانده‌نشده + پردازش فرم‌های ادمین
   ========================================================================== */
add_action( 'admin_menu', 'gv_st_admin_menu' );
function gv_st_admin_menu() {
	$s     = gv_st_get_settings();
	$title = '🎫 تیکت پشتیبانی';
	if ( ! empty( $s['admin_badge_enabled'] ) ) {
		$unread = gv_st_get_unread_count();
		if ( $unread > 0 ) {
			$title .= ' <span class="awaiting-mod count-' . intval( $unread ) . '"><span class="pending-count">' . intval( $unread ) . '</span></span>';
		}
	}
	add_submenu_page(
		'groot-vision-hub',
		'تیکت پشتیبانی | Groot Vision',
		$title,
		'manage_options',
		GV_ST_PAGE_SLUG,
		'gv_st_render_admin_page'
	);
}

/**
 * اندپوینت AJAX برای بررسی دوره‌ای تیکت‌های جدید در پیشخوان.
 * زیرساختِ آماده برای گسترش به نوتیفیکیشن واقعی (push، تلگرام، پیامک و ...):
 * فقط کافیست به هوک‌های gv_st_new_ticket / gv_st_new_reply متصل شوید،
 * یا خروجی این اندپوینت را در سرویس دلخواه خودتان مصرف کنید.
 */
add_action( 'wp_ajax_gv_st_poll_notifications', 'gv_st_ajax_poll_notifications' );
function gv_st_ajax_poll_notifications() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'msg' => 'no-access' ), 403 ); }
	check_ajax_referer( 'gv_st_poll', 'nonce' );

	global $wpdb;
	$unread = gv_st_get_unread_count();
	$latest = $wpdb->get_results(
		"SELECT id, subject, name, status, created_at FROM {$wpdb->prefix}gv_tickets WHERE is_read_admin = 0 ORDER BY created_at DESC LIMIT 5"
	);
	wp_send_json_success( array(
		'unread' => $unread,
		'latest' => array_map( function( $t ) {
			return array(
				'id'      => (int) $t->id,
				'subject' => $t->subject,
				'name'    => $t->name,
				'url'     => admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=view&id=' . $t->id ),
			);
		}, $latest ),
	) );
}

/** اسکریپت بررسی خودکار در همه‌ی صفحات پیشخوان (بج زنده + عنوان تب مرورگر) */
add_action( 'admin_enqueue_scripts', 'gv_st_admin_poll_script' );
function gv_st_admin_poll_script() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = gv_st_get_settings();
	if ( empty( $s['admin_poll_enabled'] ) ) { return; }

	$interval = max( 10, intval( $s['admin_poll_interval'] ) ) * 1000;
	$ajax_url = admin_url( 'admin-ajax.php' );
	$nonce    = wp_create_nonce( 'gv_st_poll' );
	$page_url = admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG );

	wp_add_inline_script( 'jquery-core', "
	(function(){
		var gvLastUnread = null;
		var origTitle = document.title;
		function gvPoll(){
			var xhr = new XMLHttpRequest();
			xhr.open('POST', " . wp_json_encode( $ajax_url ) . ", true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.onload = function(){
				if (xhr.status !== 200) { return; }
				try {
					var res = JSON.parse(xhr.responseText);
					if (!res.success) { return; }
					var unread = res.data.unread;
					// بج کنار منوی پیشخوان
					document.querySelectorAll('a[href*=\"page=" . GV_ST_PAGE_SLUG . "\"] .awaiting-mod, a[href*=\"page=" . GV_ST_PAGE_SLUG . "\"] .update-plugins').forEach(function(el){ el.remove(); });
					var menuLink = document.querySelector('#toplevel_page_groot-vision-hub a[href*=\"page=" . GV_ST_PAGE_SLUG . "\"], .wp-submenu a[href*=\"page=" . GV_ST_PAGE_SLUG . "\"]');
					if (menuLink && unread > 0) {
						var span = document.createElement('span');
						span.className = 'awaiting-mod count-' + unread;
						span.innerHTML = '<span class=\"pending-count\">' + unread + '</span>';
						menuLink.appendChild(span);
					}
					// عنوان تب مرورگر
					document.title = unread > 0 ? ('(' + unread + ') ' + origTitle) : origTitle;
					// نوتیفیکیشن مرورگر در صورت افزایش تعداد نسبت به بار قبل (پایه‌ی آماده برای گسترش)
					if (gvLastUnread !== null && unread > gvLastUnread && 'Notification' in window) {
						if (Notification.permission === 'granted') {
							new Notification('تیکت پشتیبانی جدید', { body: 'یک تیکت جدید ثبت شد.', tag: 'gv-st-ticket' });
						}
					}
					gvLastUnread = unread;
				} catch(e) {}
			};
			xhr.send('action=gv_st_poll_notifications&nonce=" . esc_js( $nonce ) . "');
		}
		if ('Notification' in window && Notification.permission === 'default') {
			// درخواست اجازه‌ی نوتیفیکیشن فقط داخل صفحه‌ی خودِ تیکت‌ها
			if (location.href.indexOf('page=" . GV_ST_PAGE_SLUG . "') !== -1) {
				Notification.requestPermission();
			}
		}
		gvPoll();
		setInterval(gvPoll, " . intval( $interval ) . ");
	})();
	" );
}

add_action( 'admin_post_gv_st_save_settings', 'gv_st_save_settings' );
function gv_st_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_ST_NONCE );

	$categories = array();
	if ( isset( $_POST['cat_label'] ) && is_array( $_POST['cat_label'] ) ) {
		foreach ( $_POST['cat_label'] as $idx => $label ) {
			$label = sanitize_text_field( wp_unslash( $label ) );
			if ( '' === $label ) { continue; }
			$slug = isset( $_POST['cat_slug'][ $idx ] ) && $_POST['cat_slug'][ $idx ] ? sanitize_key( $_POST['cat_slug'][ $idx ] ) : sanitize_key( $label . '-' . $idx );
			$categories[] = array( 'slug' => $slug, 'label' => $label );
		}
	}
	if ( empty( $categories ) ) { $categories = gv_st_default_categories(); }

	$agent_roles = array();
	if ( isset( $_POST['agent_roles'] ) && is_array( $_POST['agent_roles'] ) ) {
		foreach ( $_POST['agent_roles'] as $r ) { $agent_roles[] = sanitize_key( $r ); }
	}
	if ( empty( $agent_roles ) ) { $agent_roles = array( 'administrator' ); }

	$settings = array(
		'enabled'                 => isset( $_POST['enabled'] ) ? 1 : 0,
		'admin_email'             => is_email( $_POST['admin_email'] ?? '' ) ? sanitize_email( $_POST['admin_email'] ) : get_option( 'admin_email' ),
		'from_name'               => sanitize_text_field( $_POST['from_name'] ?? '' ),
		'page_id'                 => intval( $_POST['page_id'] ?? 0 ),
		'notify_admin_new_ticket' => isset( $_POST['notify_admin_new_ticket'] ) ? 1 : 0,
		'notify_admin_new_reply'  => isset( $_POST['notify_admin_new_reply'] ) ? 1 : 0,
		'allow_attachments'       => isset( $_POST['allow_attachments'] ) ? 1 : 0,
		'max_attachment_mb'       => max( 1, min( 50, intval( $_POST['max_attachment_mb'] ?? 5 ) ) ),
		'show_agent_to_customer'  => isset( $_POST['show_agent_to_customer'] ) ? 1 : 0,
		'agent_roles'             => $agent_roles,
		'categories'              => $categories,
		'admin_badge_enabled'     => isset( $_POST['admin_badge_enabled'] ) ? 1 : 0,
		'admin_poll_enabled'      => isset( $_POST['admin_poll_enabled'] ) ? 1 : 0,
		'admin_poll_interval'     => max( 10, min( 300, intval( $_POST['admin_poll_interval'] ?? 25 ) ) ),
	);
	update_option( GV_ST_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=settings&updated=1' ) );
	exit;
}

/** پاسخ ادمین به تیکت (+ در صورت نیاز تغییر وضعیت) */
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
		do_action( 'gv_st_new_reply', $ticket, $message, 'admin' );
		gv_st_notify_customer_new_reply( $ticket, $message );
	}

	$wpdb->update( $wpdb->prefix . 'gv_tickets', array( 'status' => $new_status, 'is_read_admin' => 1, 'updated_at' => $now ), array( 'id' => $ticket->id ) );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG . '&tab=view&id=' . $ticket->id . '&updated=1' ) );
	exit;
}

/** تغییر سریع دسته‌بندی / کارشناس مسئول یک تیکت (بدون نیاز به پیام) */
add_action( 'admin_post_gv_st_update_meta', 'gv_st_handle_update_meta' );
function gv_st_handle_update_meta() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_ST_NONCE );

	$ticket_id = intval( $_POST['ticket_id'] ?? 0 );
	$ticket = gv_st_get_ticket_by_id( $ticket_id );
	if ( ! $ticket ) { wp_die( 'تیکت یافت نشد.' ); }

	$s = gv_st_get_settings();
	$valid_categories = wp_list_pluck( $s['categories'], 'slug' );
	$category    = in_array( $_POST['category'] ?? '', $valid_categories, true ) ? $_POST['category'] : $ticket->category;
	$assigned_to = intval( $_POST['assigned_to'] ?? 0 );

	global $wpdb;
	$wpdb->update( $wpdb->prefix . 'gv_tickets', array(
		'category'    => $category,
		'assigned_to' => $assigned_to,
		'updated_at'  => current_time( 'mysql' ),
	), array( 'id' => $ticket->id ) );

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
	$s   = gv_st_get_settings();
	$tab = $_GET['tab'] ?? 'list';
	?>
	<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif; max-width:1200px;">
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
			.gvsta-table tr.is-unread td{ font-weight:700;background:#fbfdfc; }
			.gvsta-badge{font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;color:#fff;white-space:nowrap;}
			.gvsta-chip-outline{ font-size:10.5px;font-weight:700;padding:3px 10px;border-radius:20px;border:1px solid #d1d5db;color:#475569;white-space:nowrap; }
			.gvsta-field{margin-bottom:14px;}
			.gvsta-field label{font-weight:700;font-size:13px;display:block;margin-bottom:5px;}
			.gvsta-field input[type=text],.gvsta-field input[type=email],.gvsta-field input[type=number],.gvsta-field select,.gvsta-field textarea{width:100%;max-width:420px;padding:8px 10px;border-radius:8px;border:1px solid #d1d5db;}
			.gvsta-btn{background:#111827;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;}
			.gvsta-btn-danger{background:#b91c1c;}
			.gvsta-view-grid{ display:grid;grid-template-columns:1fr 300px;gap:18px; }
			@media(max-width:960px){ .gvsta-view-grid{ grid-template-columns:1fr; } }
			.gvsta-chat-wrap{ display:flex;flex-direction:column;height:600px; }
			.gvsta-chat-scroll{ flex:1;overflow-y:auto;padding:6px;margin-bottom:14px; }
			.gvsta-msg{padding:14px 16px;border-radius:12px;margin-bottom:12px;max-width:82%;font-size:13px;line-height:1.9;}
			.gvsta-msg-user{background:#f1f5f4;}
			.gvsta-msg-admin{background:#eff6ff;margin-right:auto;}
			.gvsta-msg-meta{font-size:11px;color:#64748b;margin-bottom:6px;font-weight:700;}
			.gvsta-side-card{ background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:14px; }
			.gvsta-side-card h4{ margin:0 0 12px;font-size:13px; }
			.gvsta-catrow{ display:flex;gap:8px;align-items:center;margin-bottom:8px; }
			.gvsta-catrow input[type=text]{ flex:1;padding:7px 9px;border-radius:7px;border:1px solid #d1d5db; }
			.gvsta-role-check{ display:inline-flex;align-items:center;gap:5px;margin-left:14px;font-size:12.5px; }
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
	$status_filter   = sanitize_text_field( $_GET['status'] ?? '' );
	$category_filter = sanitize_text_field( $_GET['category'] ?? '' );
	$agent_filter    = intval( $_GET['agent'] ?? 0 );
	$search          = sanitize_text_field( $_GET['s'] ?? '' );
	$paged           = max( 1, intval( $_GET['paged'] ?? 1 ) );
	$per_page        = 20;
	$offset          = ( $paged - 1 ) * $per_page;

	$where = array( '1=1' );
	$args  = array();
	if ( $status_filter )   { $where[] = 'status = %s';      $args[] = $status_filter; }
	if ( $category_filter ) { $where[] = 'category = %s';    $args[] = $category_filter; }
	if ( $agent_filter )    { $where[] = 'assigned_to = %d'; $args[] = $agent_filter; }
	if ( $search ) {
		$where[] = '(subject LIKE %s OR name LIKE %s OR email LIKE %s)';
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$args[] = $like; $args[] = $like; $args[] = $like;
	}
	$where_sql = implode( ' AND ', $where );

	$total_query = $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}gv_tickets WHERE {$where_sql}", $args ) : "SELECT COUNT(*) FROM {$wpdb->prefix}gv_tickets WHERE {$where_sql}";
	$total = (int) $wpdb->get_var( $total_query );

	$counts = $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$wpdb->prefix}gv_tickets GROUP BY status", OBJECT_K );
	$open_c     = isset( $counts['open'] ) ? (int) $counts['open']->c : 0;
	$answered_c = isset( $counts['answered'] ) ? (int) $counts['answered']->c : 0;
	$closed_c   = isset( $counts['closed'] ) ? (int) $counts['closed']->c : 0;

	$query_args = array_merge( $args, array( $per_page, $offset ) );
	$list_query = "SELECT * FROM {$wpdb->prefix}gv_tickets WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
	$rows = $wpdb->get_results( $wpdb->prepare( $list_query, $query_args ) );

	$s      = gv_st_get_settings();
	$agents = gv_st_get_agents();
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
			<select name="category">
				<option value="">همه دسته‌ها</option>
				<?php foreach ( $s['categories'] as $c ) : ?>
					<option value="<?php echo esc_attr( $c['slug'] ); ?>" <?php selected( $category_filter, $c['slug'] ); ?>><?php echo esc_html( $c['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="agent">
				<option value="">همه کارشناسان</option>
				<option value="-1" <?php selected( $agent_filter, -1 ); ?>>تخصیص‌نیافته</option>
				<?php foreach ( $agents as $a ) : ?>
					<option value="<?php echo esc_attr( $a->ID ); ?>" <?php selected( $agent_filter, $a->ID ); ?>><?php echo esc_html( $a->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="s" placeholder="جستجو در موضوع، نام یا ایمیل..." value="<?php echo esc_attr( $search ); ?>" style="min-width:220px;">
			<button type="submit" class="gvsta-btn">فیلتر</button>
		</form>

		<?php if ( empty( $rows ) ) : ?>
			<p style="color:#94a3b8;">تیکتی یافت نشد.</p>
		<?php else : ?>
			<table class="gvsta-table">
				<thead><tr><th></th><th>موضوع</th><th>دسته</th><th>نام / ایمیل</th><th>کارشناس</th><th>اولویت</th><th>وضعیت</th><th>آخرین بروزرسانی</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $t ) : ?>
					<tr class="<?php echo empty( $t->is_read_admin ) ? 'is-unread' : ''; ?>">
						<td><?php echo empty( $t->is_read_admin ) ? '🔵' : ''; ?></td>
						<td><?php echo esc_html( $t->subject ); ?></td>
						<td><span class="gvsta-chip-outline"><?php echo esc_html( gv_st_category_label( $t->category ) ); ?></span></td>
						<td><?php echo esc_html( $t->name ); ?><br><span style="color:#94a3b8;direction:ltr;display:inline-block;"><?php echo esc_html( $t->email ); ?></span></td>
						<td><?php echo esc_html( gv_st_get_agent_name( $t->assigned_to ) ?: '—' ); ?></td>
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
	// همینکه ادمین تیکت را باز کرد، به‌عنوان «خوانده‌شده» علامت بخورد
	if ( empty( $ticket->is_read_admin ) ) {
		gv_st_mark_ticket_read( $ticket->id );
		$ticket = gv_st_get_ticket_by_id( $id );
	}
	$replies = gv_st_get_replies( $ticket->id );
	$s       = gv_st_get_settings();
	$agents  = gv_st_get_agents();
	?>
	<div class="gvsta-view-grid">
		<div class="gvsta-card gvsta-chat-wrap">
			<div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
				<div>
					<h2 style="margin:0 0 6px;"><?php echo esc_html( $ticket->subject ); ?></h2>
					<span style="font-size:12.5px;color:#64748b;"><?php echo esc_html( $ticket->name ); ?> — <bdi style="direction:ltr;"><?php echo esc_html( $ticket->email ); ?></bdi> — اولویت: <?php echo esc_html( gv_st_priority_label( $ticket->priority ) ); ?></span>
				</div>
				<span class="gvsta-badge" style="background:<?php echo esc_attr( gv_st_status_color( $ticket->status ) ); ?>;height:fit-content;"><?php echo esc_html( gv_st_status_label( $ticket->status ) ); ?></span>
			</div>

			<div class="gvsta-chat-scroll">
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
					<textarea name="message" rows="4" style="max-width:100%;"></textarea>
				</div>
				<?php if ( ! empty( $s['allow_attachments'] ) ) : ?>
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
		</div>

		<div>
			<div class="gvsta-side-card">
				<h4>دسته‌بندی و تخصیص</h4>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gv_st_update_meta">
					<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
					<?php wp_nonce_field( GV_ST_NONCE ); ?>
					<div class="gvsta-field">
						<label>دسته‌بندی</label>
						<select name="category" style="max-width:100%;">
							<?php foreach ( $s['categories'] as $c ) : ?>
								<option value="<?php echo esc_attr( $c['slug'] ); ?>" <?php selected( $ticket->category, $c['slug'] ); ?>><?php echo esc_html( $c['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gvsta-field">
						<label>کارشناس مسئول</label>
						<select name="assigned_to" style="max-width:100%;">
							<option value="0">— تخصیص‌نیافته —</option>
							<?php foreach ( $agents as $a ) : ?>
								<option value="<?php echo esc_attr( $a->ID ); ?>" <?php selected( (int) $ticket->assigned_to, $a->ID ); ?>><?php echo esc_html( $a->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<button type="submit" class="gvsta-btn" style="width:100%;">به‌روزرسانی</button>
				</form>
			</div>

			<div class="gvsta-side-card">
				<h4>عملیات</h4>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('این تیکت برای همیشه حذف شود؟');">
					<input type="hidden" name="action" value="gv_st_delete_ticket">
					<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
					<?php wp_nonce_field( GV_ST_NONCE ); ?>
					<button type="submit" class="gvsta-btn gvsta-btn-danger" style="width:100%;">🗑️ حذف تیکت</button>
				</form>
			</div>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG ) ); ?>" style="display:inline-block;">← بازگشت به لیست</a>
		</div>
	</div>
	<?php
}

function gv_st_render_admin_settings( $s ) {
	$pages     = get_pages( array( 'post_status' => 'publish' ) );
	$all_roles = wp_roles()->get_names();
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
			<h2>دسته‌بندی تیکت‌ها</h2>
			<p style="font-size:12.5px;color:#64748b;">این دسته‌ها هم در فرم ثبت تیکت به کاربر نمایش داده می‌شوند و هم برای فیلتر و مدیریت بهتر در پیشخوان استفاده می‌شوند.</p>
			<div id="gvsta-cat-wrap">
				<?php foreach ( $s['categories'] as $c ) : ?>
				<div class="gvsta-catrow">
					<input type="hidden" name="cat_slug[]" value="<?php echo esc_attr( $c['slug'] ); ?>">
					<input type="text" name="cat_label[]" value="<?php echo esc_attr( $c['label'] ); ?>" placeholder="نام دسته">
					<button type="button" class="button gvsta-cat-remove">🗑️</button>
				</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="gvsta-cat-add">➕ افزودن دسته جدید</button>
		</div>

		<div class="gvsta-card">
			<h2>کارشناسان و تخصیص تیکت</h2>
			<p style="font-size:12.5px;color:#64748b;">کاربرانی با این نقش‌ها در لیست «کارشناس مسئول» برای تخصیص تیکت نمایش داده می‌شوند.</p>
			<div class="gvsta-field">
				<?php foreach ( $all_roles as $role_key => $role_label ) : ?>
					<label class="gvsta-role-check">
						<input type="checkbox" name="agent_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $s['agent_roles'], true ) ); ?>>
						<?php echo esc_html( $role_label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="gvsta-field"><label><input type="checkbox" name="show_agent_to_customer" <?php checked( $s['show_agent_to_customer'], 1 ); ?>> نمایش نام کارشناس مسئول به خودِ کاربر در صفحه‌ی تیکت</label></div>
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
			<div class="gvsta-field"><label><input type="checkbox" name="notify_admin_new_ticket" <?php checked( $s['notify_admin_new_ticket'], 1 ); ?>> اطلاع‌رسانی ایمیلی به مدیر هنگام ثبت تیکت جدید</label></div>
			<div class="gvsta-field"><label><input type="checkbox" name="notify_admin_new_reply" <?php checked( $s['notify_admin_new_reply'], 1 ); ?>> اطلاع‌رسانی ایمیلی به مدیر هنگام پاسخ جدید کاربر</label></div>
			<p style="font-size:12px;color:#94a3b8;">توجه: پاسخ ادمین همیشه به‌صورت خودکار به ایمیلی که کاربر هنگام ثبت تیکت وارد کرده ارسال می‌شود.</p>
		</div>

		<div class="gvsta-card">
			<h2>نوتیفیکیشن داخل پیشخوان</h2>
			<div class="gvsta-field"><label><input type="checkbox" name="admin_badge_enabled" <?php checked( $s['admin_badge_enabled'], 1 ); ?>> نمایش بج تعداد تیکتِ خوانده‌نشده روی منوی پیشخوان</label></div>
			<div class="gvsta-field"><label><input type="checkbox" name="admin_poll_enabled" <?php checked( $s['admin_poll_enabled'], 1 ); ?>> بررسی خودکار تیکت جدید در پس‌زمینه (بدون نیاز به رفرش صفحه) + نوتیفیکیشن مرورگر</label></div>
			<div class="gvsta-field">
				<label>فاصله‌ی بررسی خودکار (ثانیه)</label>
				<input type="number" name="admin_poll_interval" min="10" max="300" value="<?php echo esc_attr( $s['admin_poll_interval'] ); ?>" style="max-width:120px;">
			</div>
			<p style="font-size:12px;color:#94a3b8;">این بخش زیرساخت آماده برای گسترش به سرویس‌های نوتیفیکیشن دیگر (تلگرام، پیامک، Push واقعی) است — کافیست به هوک‌های <code>gv_st_new_ticket</code> و <code>gv_st_new_reply</code> متصل شوید.</p>
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
	<script>
	(function(){
		document.getElementById('gvsta-cat-add').addEventListener('click', function(){
			var row = document.createElement('div');
			row.className = 'gvsta-catrow';
			row.innerHTML = '<input type="hidden" name="cat_slug[]" value=""><input type="text" name="cat_label[]" placeholder="نام دسته"><button type="button" class="button gvsta-cat-remove">🗑️</button>';
			document.getElementById('gvsta-cat-wrap').appendChild(row);
		});
		document.getElementById('gvsta-cat-wrap').addEventListener('click', function(e){
			if (e.target.classList.contains('gvsta-cat-remove')) {
				e.target.closest('.gvsta-catrow').remove();
			}
		});
	})();
	</script>
	<?php
}

/* ==========================================================================
   ۸) افزودن تب «تیکت‌های پشتیبانی» به داشبورد «حساب کاربری من» ووکامرس
   ========================================================================== */
add_action( 'init', 'gv_st_add_account_endpoint' );
function gv_st_add_account_endpoint() {
	add_rewrite_endpoint( 'tickets', EP_ROOT | EP_PAGES );
}

add_filter( 'query_vars', 'gv_st_add_account_query_var' );
function gv_st_add_account_query_var( $vars ) {
	$vars[] = 'tickets';
	return $vars;
}

add_action( 'init', 'gv_st_maybe_flush_rewrite_rules', 999 );
function gv_st_maybe_flush_rewrite_rules() {
	if ( ! get_option( 'gv_st_rewrite_flushed_v1' ) ) {
		flush_rewrite_rules();
		update_option( 'gv_st_rewrite_flushed_v1', 1 );
	}
}

add_filter( 'woocommerce_account_menu_items', 'gv_st_add_account_menu_item' );
function gv_st_add_account_menu_item( $items ) {
	$s = gv_st_get_settings();
	if ( empty( $s['enabled'] ) ) {
		return $items;
	}

	$new_items = array();
	$inserted  = false;

	foreach ( $items as $key => $label ) {
		if ( 'customer-logout' === $key && ! $inserted ) {
			$new_items['tickets'] = 'تیکت‌های پشتیبانی';
			$inserted = true;
		}
		$new_items[ $key ] = $label;
	}
	if ( ! $inserted ) {
		$new_items['tickets'] = 'تیکت‌های پشتیبانی';
	}

	return $new_items;
}

add_filter( 'woocommerce_endpoint_tickets_title', 'gv_st_account_endpoint_title' );
function gv_st_account_endpoint_title( $title ) {
	return 'تیکت‌های پشتیبانی';
}

add_action( 'woocommerce_account_tickets_endpoint', 'gv_st_render_account_endpoint_content' );
function gv_st_render_account_endpoint_content() {
	echo '<div class="gvst-account-wrap">';
	echo gv_st_render_shortcode();
	echo '</div>';
}