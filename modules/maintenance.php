<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * ==========================================================
 *  Groot Vision — حالت تعمیر و نگهداری سایت (نسخه ۳ — ری‌دیزاین کامل)
 *  ------------------------------------------------------------
 *  همان قابلیت‌های نسخه‌ی قبل (زمان‌بندی خودکار/دستی، لیست سفید IP،
 *  لینک پیش‌نمایش، رنگ‌بندی، فرم اطلاع‌رسانی ایمیلی، CSS اختصاصی، ...)
 *  فقط با یک ظاهر کاملاً متفاوت: تم «کارگاه/بلوپرینت» به‌جای اسطرلاب
 *  طلایی-فیروزه‌ای — نوار راه‌راه احتیاط، کارت‌های مربعی با حاشیه‌ی
 *  ضخیم و سایه‌ی افست (سبک نئوبروتالیست)، بک‌گراند شبکه‌ی نقشه‌ی فنی.
 * ==========================================================
 */

define( 'WPMC_OPT', 'wpmc_options' );
define( 'WPMC_SUBS_OPT', 'wpmc_subscribers' );
define( 'WPMC_NONCE', 'wpmc_save_action' );

/* ==========================================================================
   1) پیش‌فرض‌ها و پریست‌های رنگی (تم جدید: کارگاه/بلوپرینت)
   ========================================================================== */
function wpmc_color_presets() {
	return array(
		/* ---------- سبک «کارگاه/بلوپرینت» (نئوبروتالیست) ---------- */
		'hazard' => array(
			'style' => 'brutalist',
			'label' => 'راه‌راه احتیاط (نارنجی/زرد/مشکی)',
			'color_primary'   => '#FF6B35',
			'color_secondary' => '#FFD23F',
			'color_accent'    => '#111111',
			'color_bg1'       => '#FFFBEA',
			'color_bg2'       => '#FFF0C2',
		),
		'blueprint' => array(
			'style' => 'brutalist',
			'label' => 'بلوپرینت مهندسی (آبی/سفید/مشکی)',
			'color_primary'   => '#2563EB',
			'color_secondary' => '#38BDF8',
			'color_accent'    => '#0B1220',
			'color_bg1'       => '#EAF2FF',
			'color_bg2'       => '#D3E6FF',
		),
		'punk' => array(
			'style' => 'brutalist',
			'label' => 'پانک رنگی (صورتی/لیمویی/مشکی)',
			'color_primary'   => '#FF2D75',
			'color_secondary' => '#C6FF00',
			'color_accent'    => '#111111',
			'color_bg1'       => '#FFF1F6',
			'color_bg2'       => '#FFE1EC',
		),

		/* ---------- سبک «ترمینال/فضایی» ---------- */
		'matrix' => array(
			'style' => 'terminal',
			'label' => 'ماتریکس (سبز نئون روی مشکی)',
			'color_primary'   => '#00FF9C',
			'color_secondary' => '#0AFFEF',
			'color_accent'    => '#0A0E14',
			'color_bg1'       => '#05070A',
			'color_bg2'       => '#0D1420',
		),
		'cyberpunk' => array(
			'style' => 'terminal',
			'label' => 'سایبرپانک (صورتی نئون/فیروزه‌ای)',
			'color_primary'   => '#FF2E88',
			'color_secondary' => '#00E5FF',
			'color_accent'    => '#0A0616',
			'color_bg1'       => '#0B0414',
			'color_bg2'       => '#170B28',
		),
		'nebula' => array(
			'style' => 'terminal',
			'label' => 'سحابی فضایی (بنفش/آبی کهکشانی)',
			'color_primary'   => '#8B5CF6',
			'color_secondary' => '#38BDF8',
			'color_accent'    => '#0B0B1A',
			'color_bg1'       => '#07070F',
			'color_bg2'       => '#130B26',
		),
	);
}

function wpmc_default_options() {
	$preset = wpmc_color_presets()['hazard'];
	return array_merge( array(
		'enabled'          => 0,
		'title'            => 'در حال ساخته‌شدنیم!',
		'description'      => 'داریم یه چیز خفن‌تر می‌سازیم 🛠️ یکم دیگه صبر کن، به‌زودی با ظاهری جدید و تجربه‌ای بهتر برمی‌گردیم.',
		'badge_text'       => 'در حال ساخت',
		'show_timer'       => 1,
		'end_datetime'     => '',
		'timer_label'      => 'شمارش معکوس تا بازگشایی',
		'timer_sentence'   => 'سایت تا {days} روز و {hours} ساعت و {minutes} دقیقه دیگر دوباره باز می‌شود!',
		'progress_label'   => 'پیشرفت بروزرسانی',
		'progress_percent' => 70,
		'feature1'         => 'امن و مطمئن',
		'feature2'         => 'سریع‌تر از قبل',
		'feature3'         => 'ظاهری تازه',
		'footer_text'      => 'در حال بروزرسانی سایت هستیم',
		'color_preset'     => 'hazard',
		'theme_style'      => 'brutalist', // brutalist | terminal

		// امکانات
		'schedule_mode'    => 'manual', // manual | auto
		'start_datetime'   => '',
		'ip_whitelist'     => '',
		'preview_token'    => '',
		'email_capture'    => 1,
		'custom_css'       => '',
		'seo_noindex'      => 1,
	), $preset );
}

function wpmc_get_options() {
	$opts = get_option( WPMC_OPT, array() );
	return wp_parse_args( $opts, wpmc_default_options() );
}

/* ==========================================================================
   2) ثبت زیرمنو در هاب گروت ویژن
   ========================================================================== */
add_action( 'admin_menu', 'wpmc_add_admin_menu' );
function wpmc_add_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'حالت تعمیر سایت | Groot Vision',
		'🛠️ حالت تعمیر',
		'manage_options',
		'wpmc-maintenance',
		'wpmc_render_admin_page'
	);
}

add_action( 'admin_enqueue_scripts', 'wpmc_admin_assets' );
function wpmc_admin_assets( $hook ) {
	if ( strpos( $hook, 'wpmc-maintenance' ) === false ) return;
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );
}

/* ==========================================================================
   3) پردازش ذخیره تنظیمات + خروجی CSV مشترکین
   ========================================================================== */
add_action( 'admin_post_wpmc_export_subscribers', 'wpmc_export_subscribers' );
function wpmc_export_subscribers() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی ندارید.' );
	check_admin_referer( WPMC_NONCE );

	$subs = get_option( WPMC_SUBS_OPT, array() );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=maintenance-subscribers.csv' );
	echo "\xEF\xBB\xBF"; // BOM برای نمایش صحیح فارسی در اکسل
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'ایمیل', 'تاریخ ثبت' ) );
	foreach ( $subs as $row ) {
		fputcsv( $out, array( $row['email'], $row['date'] ) );
	}
	fclose( $out );
	exit;
}

add_action( 'admin_post_wpmc_clear_subscribers', 'wpmc_clear_subscribers' );
function wpmc_clear_subscribers() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی ندارید.' );
	check_admin_referer( WPMC_NONCE );
	update_option( WPMC_SUBS_OPT, array() );
	wp_safe_redirect( admin_url( 'admin.php?page=wpmc-maintenance&cleared=1' ) );
	exit;
}

/** ثبت ایمیل بازدیدکننده از فرم «اطلاع‌رسانی هنگام بازگشایی» */
add_action( 'admin_post_nopriv_wpmc_subscribe', 'wpmc_handle_subscribe' );
add_action( 'admin_post_wpmc_subscribe', 'wpmc_handle_subscribe' );
function wpmc_handle_subscribe() {
	$email = sanitize_email( wp_unslash( $_POST['wpmc_email'] ?? '' ) );
	$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( is_email( $email ) && isset( $_POST['wpmc_subscribe_nonce'] ) && wp_verify_nonce( $_POST['wpmc_subscribe_nonce'], 'wpmc_subscribe' ) ) {
		$subs = get_option( WPMC_SUBS_OPT, array() );
		$exists = false;
		foreach ( $subs as $row ) { if ( strtolower( $row['email'] ) === strtolower( $email ) ) { $exists = true; break; } }
		if ( ! $exists ) {
			$subs[] = array( 'email' => $email, 'date' => current_time( 'mysql' ) );
			if ( count( $subs ) > 10000 ) { $subs = array_slice( $subs, -10000 ); }
			update_option( WPMC_SUBS_OPT, $subs );
		}
		$redirect = add_query_arg( 'wpmc_sub', 'ok', $redirect );
	} else {
		$redirect = add_query_arg( 'wpmc_sub', 'err', $redirect );
	}
	wp_safe_redirect( $redirect );
	exit;
}

function wpmc_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$date_error = false;
	$notice = '';

	if ( isset( $_POST['wpmc_save'] ) && check_admin_referer( WPMC_NONCE, 'wpmc_nonce' ) ) {
		$d = wpmc_default_options();

		$raw_date = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
		$raw_time = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) );
		$raw_datetime = '';
		if ( $raw_date !== '' ) {
			$raw_datetime = $raw_date . 'T' . ( $raw_time !== '' ? $raw_time : '00:00' );
			if ( strtotime( $raw_datetime ) === false ) { $date_error = true; $raw_datetime = ''; }
		} elseif ( $raw_time !== '' ) {
			$date_error = true;
		}

		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$start_time = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) );
		$raw_start = '';
		if ( $start_date !== '' ) {
			$raw_start = $start_date . 'T' . ( $start_time !== '' ? $start_time : '00:00' );
			if ( strtotime( $raw_start ) === false ) { $raw_start = ''; }
		}

		// لیست سفید IP: هر خط یک آی‌پی، پاکسازی و اعتبارسنجی
		$ip_lines = preg_split( '/[\r\n]+/', wp_unslash( $_POST['ip_whitelist'] ?? '' ) );
		$ip_clean = array();
		foreach ( $ip_lines as $ip ) {
			$ip = trim( $ip );
			if ( $ip !== '' && ( filter_var( $ip, FILTER_VALIDATE_IP ) || $ip === '' ) ) { $ip_clean[] = $ip; }
		}

		$color_preset = sanitize_key( $_POST['color_preset'] ?? 'hazard' );
		$presets = wpmc_color_presets();
		if ( 'custom' === $color_preset ) {
			$colors = array(
				'color_primary'   => sanitize_hex_color( $_POST['color_primary'] ?? '' ) ?: $d['color_primary'],
				'color_secondary' => sanitize_hex_color( $_POST['color_secondary'] ?? '' ) ?: $d['color_secondary'],
				'color_accent'    => sanitize_hex_color( $_POST['color_accent'] ?? '' ) ?: $d['color_accent'],
				'color_bg1'       => sanitize_hex_color( $_POST['color_bg1'] ?? '' ) ?: $d['color_bg1'],
				'color_bg2'       => sanitize_hex_color( $_POST['color_bg2'] ?? '' ) ?: $d['color_bg2'],
			);
		} elseif ( isset( $presets[ $color_preset ] ) ) {
			$colors = $presets[ $color_preset ];
			unset( $colors['label'], $colors['style'] );
		} else {
			$color_preset = 'hazard';
			$colors = $presets['hazard'];
			unset( $colors['label'], $colors['style'] );
		}

		$new = array_merge( array(
			'enabled'          => isset( $_POST['enabled'] ) ? 1 : 0,
			'show_timer'       => isset( $_POST['show_timer'] ) ? 1 : 0,
			'title'            => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'      => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'badge_text'       => sanitize_text_field( wp_unslash( $_POST['badge_text'] ?? '' ) ),
			'end_datetime'     => $raw_datetime,
			'timer_label'      => sanitize_text_field( wp_unslash( $_POST['timer_label'] ?? '' ) ),
			'timer_sentence'   => sanitize_text_field( wp_unslash( $_POST['timer_sentence'] ?? '' ) ),
			'progress_label'   => sanitize_text_field( wp_unslash( $_POST['progress_label'] ?? '' ) ),
			'progress_percent' => max( 0, min( 100, intval( $_POST['progress_percent'] ?? 70 ) ) ),
			'feature1'         => sanitize_text_field( wp_unslash( $_POST['feature1'] ?? '' ) ),
			'feature2'         => sanitize_text_field( wp_unslash( $_POST['feature2'] ?? '' ) ),
			'feature3'         => sanitize_text_field( wp_unslash( $_POST['feature3'] ?? '' ) ),
			'footer_text'      => sanitize_text_field( wp_unslash( $_POST['footer_text'] ?? '' ) ),
			'color_preset'     => $color_preset,
			'theme_style'      => ( ( $_POST['theme_style'] ?? 'brutalist' ) === 'terminal' ) ? 'terminal' : 'brutalist',

			'schedule_mode'    => ( ( $_POST['schedule_mode'] ?? 'manual' ) === 'auto' ) ? 'auto' : 'manual',
			'start_datetime'   => $raw_start,
			'ip_whitelist'     => implode( "\n", $ip_clean ),
			'email_capture'    => isset( $_POST['email_capture'] ) ? 1 : 0,
			'custom_css'       => wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ?? '' ) ),
			'seo_noindex'      => isset( $_POST['seo_noindex'] ) ? 1 : 0,
		), $colors );

		// توکن پیش‌نمایش دائمی است؛ فقط اگر خالی بود یا کاربر دکمه‌ی «ساخت لینک جدید» را زده، بازسازی شود
		$existing = wpmc_get_options();
		$new['preview_token'] = $existing['preview_token'];
		if ( empty( $new['preview_token'] ) || isset( $_POST['wpmc_regen_token'] ) ) {
			$new['preview_token'] = wp_generate_password( 20, false, false );
		}

		update_option( WPMC_OPT, $new );

		if ( $date_error ) {
			$notice = '<div class="notice notice-error is-dismissible"><p>⚠️ تاریخ/ساعتی که وارد کردید قابل ثبت نبود، بنابراین ذخیره نشد. لطفاً هم باکس تاریخ و هم باکس ساعت را از روی انتخابگر مرورگر پر کنید.</p></div>';
		} else {
			$notice = '<div class="notice notice-success is-dismissible"><p>تنظیمات با موفقیت ذخیره شد ✅</p></div>';
		}
	}

	if ( isset( $_GET['cleared'] ) ) { $notice = '<div class="notice notice-success is-dismissible"><p>لیست مشترکین پاک شد.</p></div>'; }

	$o = wpmc_get_options();
	$presets = wpmc_color_presets();
	$subs_count = count( get_option( WPMC_SUBS_OPT, array() ) );

	$end_date_val = ''; $end_time_val = '';
	if ( ! empty( $o['end_datetime'] ) && strpos( $o['end_datetime'], 'T' ) !== false ) {
		list( $end_date_val, $end_time_val ) = explode( 'T', $o['end_datetime'], 2 );
	}
	$start_date_val = ''; $start_time_val = '';
	if ( ! empty( $o['start_datetime'] ) && strpos( $o['start_datetime'], 'T' ) !== false ) {
		list( $start_date_val, $start_time_val ) = explode( 'T', $o['start_datetime'], 2 );
	}

	$preview_url = ! empty( $o['preview_token'] ) ? add_query_arg( 'gv_preview', $o['preview_token'], home_url( '/' ) ) : '';
	$status_badge = wpmc_is_active_now( $o )
		? '<span class="wpmc-status-pill on">🟠 هم‌اکنون فعال است</span>'
		: '<span class="wpmc-status-pill off">⚪ هم‌اکنون غیرفعال است</span>';
	?>
	<div class="wrap" dir="rtl" style="font-family:'Vazirmatn',Tahoma,sans-serif; max-width:1000px;">
		<style>
			/* ============ پنل مدیریت — تم کارگاه/بلوپرینت نئوبروتالیست ============ */
			#wpmc-admin-root{ --ink:#111111; --paper:#FFFBEA; --panel:#FFFFFF; --hazard:#FF6B35; --hazard-2:#FFD23F; }
			#wpmc-admin-root{ background:var(--paper); padding:4px; }
			#wpmc-admin-root .wpmc-tape{
				height:22px; margin:0 0 20px; border:3px solid var(--ink);
				background:repeating-linear-gradient(-45deg,var(--hazard-2) 0 18px,var(--ink) 18px 22px);
				box-shadow:5px 5px 0 var(--ink);
			}
			.wpmc-header{
				background:var(--panel); color:var(--ink); padding:24px 28px; margin:0 0 24px;
				border:3px solid var(--ink); box-shadow:8px 8px 0 var(--ink);
				display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
			}
			.wpmc-header h1{ margin:0; font-size:22px; color:var(--ink); font-weight:900; }
			.wpmc-header p{ margin:8px 0 0; font-size:13px; color:#555; }
			.wpmc-status-pill{ padding:8px 16px; border:2.5px solid var(--ink); font-size:12.5px; font-weight:800; box-shadow:3px 3px 0 var(--ink); }
			.wpmc-status-pill.on{ background:var(--hazard); color:var(--ink); }
			.wpmc-status-pill.off{ background:#eee; color:#555; }

			.wpmc-card{
				background:var(--panel); border:3px solid var(--ink); box-shadow:6px 6px 0 var(--ink);
				padding:24px; margin-bottom:24px;
			}
			.wpmc-card h2{ margin-top:0; font-size:15.5px; color:var(--ink); font-weight:900; display:flex; align-items:center; gap:8px; }
			.wpmc-card h2::before{ content:""; width:10px; height:10px; background:var(--hazard); border:2px solid var(--ink); display:inline-block; }
			.wpmc-card .description{ color:#666; }
			#wpmc-admin-root input[type=text],#wpmc-admin-root input[type=date],#wpmc-admin-root input[type=time],#wpmc-admin-root input[type=number],#wpmc-admin-root textarea{
				border:2.5px solid var(--ink) !important; border-radius:0 !important; box-shadow:none !important;
			}
			#wpmc-admin-root input[type=text]:focus,#wpmc-admin-root textarea:focus{ border-color:var(--hazard) !important; box-shadow:3px 3px 0 var(--ink) !important; }

			.wpmc-style-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
			@media(max-width:700px){ .wpmc-style-grid{ grid-template-columns:1fr; } }
			.wpmc-style-card{ border:2.5px solid var(--ink); background:#fff; padding:14px; cursor:pointer; text-align:center; display:block; }
			.wpmc-style-card.active{ background:var(--hazard-2); box-shadow:5px 5px 0 var(--ink); }
			.wpmc-style-card b{ display:block; font-size:13px; color:var(--ink); margin-bottom:4px; }
			.wpmc-style-card span{ font-size:11px; color:#555; }
			.wpmc-style-preview{ height:64px; border:2px solid var(--ink); margin-bottom:10px; position:relative; overflow:hidden; }
			.wpmc-style-preview-brutalist{ background:#FFFBEA; }
			.wpmc-sp-tape{ position:absolute; top:0; left:0; right:0; height:10px; background:repeating-linear-gradient(-45deg,#FFD23F 0 6px,#111 6px 8px); }
			.wpmc-sp-box{ position:absolute; bottom:8px; right:10px; left:10px; height:28px; background:#fff; border:2px solid #111; box-shadow:3px 3px 0 #111; }
			.wpmc-style-preview-terminal{ background:#05070A; }
			.wpmc-sp-dot{ position:absolute; top:6px; width:6px; height:6px; border-radius:50%; }
			.wpmc-sp-dot.r{ right:8px; background:#ff5f56; } .wpmc-sp-dot.y{ right:18px; background:#ffbd2e; } .wpmc-sp-dot.g{ right:28px; background:#27c93f; }
			.wpmc-sp-line{ position:absolute; right:8px; left:8px; top:22px; height:4px; background:#00FF9C; opacity:.85; box-shadow:0 0 6px #00FF9C; }
			.wpmc-sp-line.short{ top:32px; width:55%; background:#0AFFEF; box-shadow:0 0 6px #0AFFEF; }

			.wpmc-preset-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px; }
			.wpmc-preset{ border:2.5px solid var(--ink); padding:12px; cursor:pointer; text-align:center; background:#fff; }
			.wpmc-preset.active{ background:var(--hazard-2); box-shadow:4px 4px 0 var(--ink); }
			.wpmc-preset-swatch{ height:30px; border:2px solid var(--ink); margin-bottom:8px; }
			.wpmc-preset span{ font-size:11.5px; font-weight:800; color:var(--ink); }

			.wpmc-copybox{
				display:flex; gap:8px; align-items:center; background:#fff; border:2.5px solid var(--ink);
				padding:10px 14px; font-family:monospace; direction:ltr; text-align:left; font-size:12.5px; overflow-x:auto;
			}
			.wpmc-btn-mini{
				background:var(--ink); color:#fff !important; border:2.5px solid var(--ink); padding:7px 16px;
				font-size:12px; font-weight:800; cursor:pointer; text-decoration:none; display:inline-block;
				box-shadow:3px 3px 0 var(--hazard); transition:transform .1s ease, box-shadow .1s ease;
			}
			.wpmc-btn-mini:hover{ transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--hazard); color:#fff !important; }
			.wpmc-btn-mini:active{ transform:translate(0,0); box-shadow:0 0 0 var(--hazard); }
			#wpmc-admin-root .button-primary{
				background:var(--hazard) !important; border:2.5px solid var(--ink) !important; color:var(--ink) !important;
				border-radius:0 !important; font-weight:900 !important; text-shadow:none !important; box-shadow:4px 4px 0 var(--ink) !important;
				padding:6px 18px !important; height:auto !important; transition:transform .1s ease, box-shadow .1s ease;
			}
			#wpmc-admin-root .button-primary:hover{ transform:translate(-2px,-2px); box-shadow:6px 6px 0 var(--ink) !important; }
			@media(max-width:782px){ .wpmc-preset-grid{ grid-template-columns:1fr; } }
		</style>

		<div id="wpmc-admin-root">
		<div class="wpmc-tape" aria-hidden="true"></div>

		<div class="wpmc-header">
			<div>
				<h1>🛠️ حالت تعمیر و نگهداری سایت</h1>
				<p>صفحه تعمیر سایت را فعال/غیرفعال، شخصی‌سازی و زمان‌بندی کنید.</p>
			</div>
			<?php echo $status_badge; ?>
		</div>

		<?php echo $notice; ?>

		<form method="post" id="wpmc-form">
			<?php wp_nonce_field( WPMC_NONCE, 'wpmc_nonce' ); ?>

			<div class="wpmc-card">
				<h2>وضعیت و زمان‌بندی</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">حالت روشن/خاموش کردن</th>
						<td>
							<label style="margin-inline-end:22px;"><input type="radio" name="schedule_mode" value="manual" <?php checked( $o['schedule_mode'], 'manual' ); ?>> دستی (با تیک زیر کنترل می‌شود)</label>
							<label><input type="radio" name="schedule_mode" value="auto" <?php checked( $o['schedule_mode'], 'auto' ); ?>> خودکار (بر اساس تاریخ شروع و پایان)</label>
						</td>
					</tr>
					<tr class="wpmc-manual-row">
						<th scope="row">حالت تعمیر (دستی)</th>
						<td>
							<label style="font-size:15px;">
								<input type="checkbox" name="enabled" value="1" <?php checked( $o['enabled'], 1 ); ?> />
								فعال باشد (فقط وقتی حالت «دستی» انتخاب شده باشد اثر دارد)
							</label>
						</td>
					</tr>
					<tr class="wpmc-auto-row">
						<th scope="row">تاریخ و ساعت شروع تعمیر</th>
						<td>
							<input type="date" name="start_date" dir="ltr" style="direction:ltr;text-align:left;" value="<?php echo esc_attr( $start_date_val ); ?>" />
							<input type="time" name="start_time" dir="ltr" style="direction:ltr;text-align:left;" value="<?php echo esc_attr( $start_time_val ); ?>" />
							<p class="description">اگر خالی بگذارید، از همین الان در نظر گرفته می‌شود.</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="wpmc-card">
				<h2>متن‌های صفحه</h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="badge_text">متن بج بالای صفحه</label></th><td><input type="text" id="badge_text" name="badge_text" class="regular-text" value="<?php echo esc_attr( $o['badge_text'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="title">عنوان اصلی</label></th><td><input type="text" id="title" name="title" class="regular-text" value="<?php echo esc_attr( $o['title'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="description">توضیحات</label></th><td><textarea id="description" name="description" class="large-text" rows="3"><?php echo esc_textarea( $o['description'] ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="feature1">ویژگی اول</label></th><td><input type="text" id="feature1" name="feature1" class="regular-text" value="<?php echo esc_attr( $o['feature1'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="feature2">ویژگی دوم</label></th><td><input type="text" id="feature2" name="feature2" class="regular-text" value="<?php echo esc_attr( $o['feature2'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="feature3">ویژگی سوم</label></th><td><input type="text" id="feature3" name="feature3" class="regular-text" value="<?php echo esc_attr( $o['feature3'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="footer_text">متن پایین صفحه</label></th><td><input type="text" id="footer_text" name="footer_text" class="regular-text" value="<?php echo esc_attr( $o['footer_text'] ); ?>" /></td></tr>
				</table>
			</div>

			<div class="wpmc-card">
				<h2>تایمر و پیشرفت</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">نمایش تایمر</th>
						<td><label style="font-size:15px;"><input type="checkbox" name="show_timer" value="1" <?php checked( $o['show_timer'], 1 ); ?> /> تایمر شمارش معکوس نمایش داده شود</label></td>
					</tr>
					<tr>
						<th scope="row"><label for="end_date">تاریخ بازگشایی سایت</label></th>
						<td>
							<input type="date" id="end_date" name="end_date" dir="ltr" style="direction:ltr;text-align:left;" value="<?php echo esc_attr( $end_date_val ); ?>" />
							<p class="description">روی باکس کلیک کنید تا تقویم مرورگر باز شود.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="end_time">ساعت بازگشایی سایت</label></th>
						<td>
							<input type="time" id="end_time" name="end_time" dir="ltr" style="direction:ltr;text-align:left;" value="<?php echo esc_attr( $end_time_val ); ?>" />
							<p class="description">اگر تاریخ را خالی بگذارید، به‌صورت پیش‌فرض ۲ روز دیگر در نظر گرفته می‌شود.</p>
						</td>
					</tr>
					<tr><th scope="row"><label for="timer_label">برچسب بالای تایمر</label></th><td><input type="text" id="timer_label" name="timer_label" class="regular-text" value="<?php echo esc_attr( $o['timer_label'] ); ?>" /></td></tr>
					<tr>
						<th scope="row"><label for="timer_sentence">جمله‌ی تایمر</label></th>
						<td><input type="text" id="timer_sentence" name="timer_sentence" class="large-text" value="<?php echo esc_attr( $o['timer_sentence'] ); ?>" /><p class="description">از {days}، {hours} و {minutes} استفاده کنید.</p></td>
					</tr>
					<tr><th scope="row"><label for="progress_label">برچسب نوار پیشرفت</label></th><td><input type="text" id="progress_label" name="progress_label" class="regular-text" value="<?php echo esc_attr( $o['progress_label'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="progress_percent">درصد پیشرفت</label></th><td><input type="number" min="0" max="100" id="progress_percent" name="progress_percent" value="<?php echo esc_attr( $o['progress_percent'] ); ?>" style="width:80px;" /> %</td></tr>
				</table>
			</div>

			<div class="wpmc-card">
				<h2>🎭 استایل کلی نمایش</h2>
				<p class="description" style="margin-top:-4px;">دو ظاهر کاملاً متفاوت برای صفحه‌ی تعمیر — با انتخاب هرکدام، پیش‌فرض‌های رنگی مخصوص همان استایل پایین‌تر نشان داده می‌شود.</p>
				<div class="wpmc-style-grid">
					<label class="wpmc-style-card <?php echo $o['theme_style'] === 'brutalist' ? 'active' : ''; ?>" data-style="brutalist">
						<input type="radio" name="theme_style" value="brutalist" <?php checked( $o['theme_style'], 'brutalist' ); ?> style="display:none;">
						<div class="wpmc-style-preview wpmc-style-preview-brutalist">
							<span class="wpmc-sp-tape"></span>
							<span class="wpmc-sp-box"></span>
						</div>
						<b>🛠️ کارگاه / بلوپرینت</b>
						<span>حاشیه‌ی ضخیم، سایه‌ی افست، راه‌راه هشدار</span>
					</label>
					<label class="wpmc-style-card <?php echo $o['theme_style'] === 'terminal' ? 'active' : ''; ?>" data-style="terminal">
						<input type="radio" name="theme_style" value="terminal" <?php checked( $o['theme_style'], 'terminal' ); ?> style="display:none;">
						<div class="wpmc-style-preview wpmc-style-preview-terminal">
							<span class="wpmc-sp-dot r"></span><span class="wpmc-sp-dot y"></span><span class="wpmc-sp-dot g"></span>
							<span class="wpmc-sp-line"></span><span class="wpmc-sp-line short"></span>
						</div>
						<b>👾 ترمینال / فضایی</b>
						<span>مانیتور مشکی، نئون سبز، حس کدنویسی و فضا</span>
					</label>
				</div>
			</div>

			<div class="wpmc-card">
				<h2>🎨 رنگ‌بندی</h2>
				<div class="wpmc-preset-grid">
					<?php foreach ( $presets as $key => $p ) : ?>
						<label class="wpmc-preset <?php echo $o['color_preset'] === $key ? 'active' : ''; ?>" data-style="<?php echo esc_attr( $p['style'] ); ?>">
							<input type="radio" name="color_preset" value="<?php echo esc_attr( $key ); ?>" <?php checked( $o['color_preset'], $key ); ?> style="display:none;">
							<div class="wpmc-preset-swatch" style="background:linear-gradient(90deg, <?php echo esc_attr( $p['color_primary'] ); ?>, <?php echo esc_attr( $p['color_secondary'] ); ?>, <?php echo esc_attr( $p['color_accent'] ); ?>);"></div>
							<span><?php echo esc_html( $p['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
					<label class="wpmc-preset <?php echo $o['color_preset'] === 'custom' ? 'active' : ''; ?>" data-style="any">
						<input type="radio" name="color_preset" value="custom" <?php checked( $o['color_preset'], 'custom' ); ?> style="display:none;">
						<div class="wpmc-preset-swatch" style="background:repeating-linear-gradient(45deg,#e2e8f0,#e2e8f0 6px,#cbd5e1 6px,#cbd5e1 12px);"></div>
						<span>دلخواه (پایین انتخاب کنید)</span>
					</label>
				</div>
				<table class="form-table" role="presentation" id="wpmc-custom-colors">
					<tr><th scope="row"><label for="color_primary">رنگ اصلی (نوار راه‌راه)</label></th><td><input type="text" id="color_primary" name="color_primary" class="wpmc-color-field" value="<?php echo esc_attr( $o['color_primary'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="color_secondary">رنگ ثانویه</label></th><td><input type="text" id="color_secondary" name="color_secondary" class="wpmc-color-field" value="<?php echo esc_attr( $o['color_secondary'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="color_accent">رنگ مرکب/حاشیه‌ها</label></th><td><input type="text" id="color_accent" name="color_accent" class="wpmc-color-field" value="<?php echo esc_attr( $o['color_accent'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="color_bg1">پس‌زمینه کاغذی</label></th><td><input type="text" id="color_bg1" name="color_bg1" class="wpmc-color-field" value="<?php echo esc_attr( $o['color_bg1'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="color_bg2">پس‌زمینه شبکه</label></th><td><input type="text" id="color_bg2" name="color_bg2" class="wpmc-color-field" value="<?php echo esc_attr( $o['color_bg2'] ); ?>" /></td></tr>
				</table>
			</div>

			<div class="wpmc-card">
				<h2>🔑 دسترسی هنگام فعال بودن حالت تعمیر</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">لینک پیش‌نمایش برای مشتری/تیم فنی</th>
						<td>
							<?php if ( $preview_url ) : ?>
							<div class="wpmc-copybox"><span id="wpmc-preview-url"><?php echo esc_html( $preview_url ); ?></span></div>
							<p class="description">هرکس این لینک را باز کند و روی مرورگرش کوکی بگیرد، تا ۷ روز سایت را بدون حالت تعمیر می‌بیند — بدون نیاز به عضویت در سایت.</p>
							<?php endif; ?>
							<button type="submit" name="wpmc_save" value="1" onclick="document.getElementById('wpmc_regen_token_field').value='1';" class="wpmc-btn-mini">🔄 ساخت لینک جدید (لینک قبلی از کار می‌افتد)</button>
							<input type="hidden" name="wpmc_regen_token" id="wpmc_regen_token_field" value="">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_whitelist">لیست سفید IP</label></th>
						<td>
							<textarea id="ip_whitelist" name="ip_whitelist" class="large-text" rows="3" placeholder="هر خط یک IP، مثلاً:&#10;1.2.3.4"><?php echo esc_textarea( $o['ip_whitelist'] ); ?></textarea>
							<p class="description">این IPها همیشه سایت را بدون حالت تعمیر می‌بینند. آی‌پی فعلی شما: <code><?php echo esc_html( wpmc_get_visitor_ip() ); ?></code></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="wpmc-card">
				<h2>📬 ثبت‌نام ایمیلی برای اطلاع بازگشایی</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">فعال باشد</th>
						<td><label><input type="checkbox" name="email_capture" <?php checked( $o['email_capture'], 1 ); ?>> فرم «به من ایمیل بزن وقتی سایت باز شد» زیر تایمر نمایش داده شود</label></td>
					</tr>
					<tr>
						<th scope="row">مشترکین ثبت‌شده</th>
						<td>
							<p><strong><?php echo esc_html( $subs_count ); ?></strong> ایمیل ثبت شده است.</p>
							<a class="wpmc-btn-mini" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpmc_export_subscribers' ), WPMC_NONCE ) ); ?>">⬇️ خروجی CSV</a>
							<a class="wpmc-btn-mini" style="background:#b91c1c;box-shadow:3px 3px 0 var(--ink);" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpmc_clear_subscribers' ), WPMC_NONCE ) ); ?>" onclick="return confirm('همه مشترکین پاک شوند؟');">🗑️ پاک کردن لیست</a>
						</td>
					</tr>
				</table>
			</div>

			<div class="wpmc-card">
				<h2>⚙️ سئو و پیشرفته</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">noindex برای موتورهای جستجو</th>
						<td><label><input type="checkbox" name="seo_noindex" <?php checked( $o['seo_noindex'], 1 ); ?>> در حین تعمیر، به گوگل بگو صفحه را ایندکس نکند (توصیه‌شده)</label></td>
					</tr>
					<tr>
						<th scope="row"><label for="custom_css">CSS اختصاصی</label></th>
						<td><textarea id="custom_css" name="custom_css" class="large-text code" rows="5" placeholder=".wpmc-title{ font-size: 50px; }"><?php echo esc_textarea( $o['custom_css'] ); ?></textarea></td>
					</tr>
				</table>
			</div>

			<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">👁️ پیش‌نمایش سایت را در تب جدید ببینید</a> (چون شما مدیر هستید همیشه سایت عادی را می‌بینید؛ برای دیدن صفحه‌ی تعمیر از حالت ناشناس مرورگر استفاده کنید)</p>

			<?php submit_button( 'ذخیره تنظیمات', 'primary', 'wpmc_save' ); ?>
		</form>

		<div class="wpmc-tape" style="margin:30px 0 16px;" aria-hidden="true"></div>
		<p style="text-align:center; color:#555; font-size:13px; font-weight:700;">
			ساخته شده توسط علیرضا رحمتی | اینستاگرام: <a href="https://instagram.com/grootvision" target="_blank" style="color:var(--ink);">grootvision</a> | تلگرام: <a href="https://t.me/grootvision" target="_blank" style="color:var(--ink);">grootvision</a>
		</p>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($){
		$('.wpmc-color-field').wpColorPicker();

		function toggleScheduleRows(){
			var mode = $('input[name=schedule_mode]:checked').val();
			$('.wpmc-manual-row').toggle(mode === 'manual');
			$('.wpmc-auto-row').toggle(mode === 'auto');
		}
		toggleScheduleRows();
		$('input[name=schedule_mode]').on('change', toggleScheduleRows);

		function toggleCustomColors(){
			$('#wpmc-custom-colors').toggle($('input[name=color_preset]:checked').val() === 'custom');
		}
		toggleCustomColors();
		$('input[name=color_preset]').on('change', function(){
			$('.wpmc-preset').removeClass('active');
			$(this).closest('.wpmc-preset').addClass('active');
			toggleCustomColors();
		});

		/* فیلتر پیش‌فرض‌های رنگی بر اساس استایل انتخاب‌شده */
		function filterPresetsByStyle(){
			var style = $('input[name=theme_style]:checked').val();
			$('.wpmc-preset[data-style]').each(function(){
				var pStyle = $(this).data('style');
				$(this).toggle(pStyle === style || pStyle === 'any');
			});
		}
		filterPresetsByStyle();
		$('input[name=theme_style]').on('change', function(){
			$('.wpmc-style-card').removeClass('active');
			$(this).closest('.wpmc-style-card').addClass('active');
			filterPresetsByStyle();
			// اگر پریست فعلی متعلق به استایل جدید نیست، اولین پریست همان استایل را انتخاب کن
			var style = $(this).val();
			var current = $('input[name=color_preset]:checked').closest('.wpmc-preset').data('style');
			if (current !== style && current !== 'any') {
				var firstMatch = $('.wpmc-preset[data-style="' + style + '"]:first');
				if (firstMatch.length) {
					firstMatch.find('input[name=color_preset]').prop('checked', true).trigger('change');
				}
			}
		});

		var url = $('#wpmc-preview-url').text();
		if(url){
			$('#wpmc-preview-url').after('<button type="button" class="wpmc-btn-mini" style="margin-inline-start:8px;" id="wpmc-copy-btn">کپی</button>');
			$('#wpmc-copy-btn').on('click', function(){
				navigator.clipboard.writeText(url);
				$(this).text('کپی شد ✓');
			});
		}
	});
	</script>
	<?php
}

function wpmc_get_visitor_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/**
 * آیا با توجه به حالت دستی/خودکار، حالت تعمیر همین الان باید فعال باشد؟
 */
function wpmc_is_active_now( $o ) {
	if ( 'auto' === $o['schedule_mode'] ) {
		$start = ! empty( $o['start_datetime'] ) ? wpmc_resolve_end_timestamp( $o['start_datetime'] ) : 0;
		$end   = ! empty( $o['end_datetime'] ) ? wpmc_resolve_end_timestamp( $o['end_datetime'] ) : 0;
		$now   = time();
		if ( $start && $now < $start ) return false;
		if ( $end && $now > $end ) return false;
		return true;
	}
	return ! empty( $o['enabled'] );
}

/* ==========================================================================
   4) نمایش صفحه تعمیر در سمت سایت
   ========================================================================== */
add_action( 'template_redirect', 'wpmc_maybe_show_maintenance_page' );
function wpmc_maybe_show_maintenance_page() {

	$o = wpmc_get_options();

	if ( ! wpmc_is_active_now( $o ) ) return;

	// مدیر سایت و درخواست‌های ادمین/اجاکس از حالت تعمیر مستثنی هستند
	if ( current_user_can( 'manage_options' ) ) return;
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
	if ( is_admin() ) return;

	// لینک پیش‌نمایش: اگر توکن معتبر باشد، کوکی ۷ روزه ست کن و بگذر
	if ( ! empty( $o['preview_token'] ) && isset( $_GET['gv_preview'] ) && hash_equals( $o['preview_token'], sanitize_text_field( wp_unslash( $_GET['gv_preview'] ) ) ) ) {
		if ( ! headers_sent() ) {
			setcookie( 'gv_maintenance_preview', $o['preview_token'], time() + 7 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
		}
		return;
	}
	if ( ! empty( $o['preview_token'] ) && isset( $_COOKIE['gv_maintenance_preview'] ) && hash_equals( $o['preview_token'], $_COOKIE['gv_maintenance_preview'] ) ) {
		return;
	}

	// لیست سفید IP
	if ( ! empty( $o['ip_whitelist'] ) ) {
		$visitor_ip = wpmc_get_visitor_ip();
		$whitelist  = array_filter( array_map( 'trim', explode( "\n", $o['ip_whitelist'] ) ) );
		if ( in_array( $visitor_ip, $whitelist, true ) ) return;
	}

	status_header( 503 );
	header( 'Retry-After: 3600' );

	$end_ts = 0;
	if ( ! empty( $o['show_timer'] ) ) {
		$end_ts = wpmc_resolve_end_timestamp( $o['end_datetime'] );
	}

	wpmc_render_maintenance_html( $o, $end_ts );
	exit;
}

/**
 * تبدیل مقدار فیلد «تاریخ و ساعت» به یک timestamp قابل‌اتکا،
 * با در نظر گرفتن منطقه‌ی زمانیِ ثبت‌شده در پیشخوان ← تنظیمات ← عمومی.
 */
function wpmc_resolve_end_timestamp( $raw_datetime ) {
	$fallback = time() + 2 * DAY_IN_SECONDS;
	if ( empty( $raw_datetime ) ) return $fallback;
	try {
		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$dt = new DateTime( $raw_datetime, $tz );
		return $dt->getTimestamp();
	} catch ( Exception $e ) {
		return $fallback;
	}
}

function wpmc_render_maintenance_html( $o, $end_ts ) {
	if ( 'terminal' === ( $o['theme_style'] ?? 'brutalist' ) ) {
		wpmc_render_terminal_theme( $o, $end_ts );
		return;
	}
	wpmc_render_brutalist_theme( $o, $end_ts );
}

function wpmc_render_brutalist_theme( $o, $end_ts ) {
	$site_name = get_bloginfo( 'name' );
	$primary   = esc_attr( $o['color_primary'] );
	$secondary = esc_attr( $o['color_secondary'] );
	$ink       = esc_attr( $o['color_accent'] ?? '#111111' );
	$bg1       = esc_attr( $o['color_bg1'] );
	$bg2       = esc_attr( $o['color_bg2'] );
	$favicon   = get_site_icon_url();
	$sub_nonce = wp_create_nonce( 'wpmc_subscribe' );
	?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ( ! empty( $o['seo_noindex'] ) ) : ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<title><?php echo esc_html( $o['title'] . ' — ' . $site_name ); ?></title>
<?php if ( $favicon ) : ?><link rel="icon" href="<?php echo esc_url( $favicon ); ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=vazirmatn:400,600,700,800,900|jetbrains-mono:500,700,800" rel="stylesheet">
<style>
	:root{
		--wpmc-primary: <?php echo $primary; ?>;
		--wpmc-secondary: <?php echo $secondary; ?>;
		--wpmc-ink: <?php echo $ink; ?>;
		--wpmc-bg1: <?php echo $bg1; ?>;
		--wpmc-bg2: <?php echo $bg2; ?>;
		--wpmc-paper: #FFFFFF;
	}
	*{ box-sizing:border-box; }
	html,body{ margin:0; padding:0; min-height:100vh; }
	body{
		position:relative; display:flex; flex-direction:column; min-height:100vh;
		font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
		color: var(--wpmc-ink);
		background:
			linear-gradient(var(--wpmc-bg2) 1.5px, transparent 1.5px) 0 0 / 34px 34px,
			linear-gradient(90deg, var(--wpmc-bg2) 1.5px, transparent 1.5px) 0 0 / 34px 34px,
			var(--wpmc-bg1);
	}
	.wpmc-mono{ font-family:'JetBrains Mono', ui-monospace, monospace; }

	/* ---------- نوار راه‌راه احتیاط بالا/پایین ---------- */
	.wpmc-tape-bar{
		height:26px; flex-shrink:0; position:relative; overflow:hidden;
		background:repeating-linear-gradient(-45deg, var(--wpmc-secondary) 0 20px, var(--wpmc-ink) 20px 24px);
		border-bottom:3px solid var(--wpmc-ink);
	}
	.wpmc-tape-bar.bottom{ border-bottom:none; border-top:3px solid var(--wpmc-ink); margin-top:auto; }
	.wpmc-tape-bar span{
		position:absolute; top:50%; right:24px; transform:translateY(-50%);
		background:var(--wpmc-paper); border:2px solid var(--wpmc-ink); padding:1px 12px;
		font-size:10.5px; font-weight:800; letter-spacing:1px; white-space:nowrap;
	}

	.wpmc-main{ flex:1; display:flex; align-items:center; justify-content:center; padding:36px 16px; }
	.wpmc-wrap{ width:100%; max-width:660px; }

	.wpmc-card{
		position:relative; background:var(--wpmc-paper); border:4px solid var(--wpmc-ink);
		box-shadow:12px 12px 0 var(--wpmc-ink);
		padding:44px 36px 30px; text-align:center;
		animation: wpmc-rise .55s cubic-bezier(.2,.8,.2,1);
	}
	@keyframes wpmc-rise{ from{ opacity:0; transform:translate(-6px,-6px); } to{ opacity:1; transform:translate(0,0); } }
	.wpmc-corner-bolt{ position:absolute; width:12px; height:12px; border-radius:50%; background:var(--wpmc-ink); }
	.wpmc-corner-bolt.tl{ top:12px; right:12px; } .wpmc-corner-bolt.tr{ top:12px; left:12px; }
	.wpmc-corner-bolt.bl{ bottom:12px; right:12px; } .wpmc-corner-bolt.br{ bottom:12px; left:12px; }

	.wpmc-badge{
		display:inline-flex; align-items:center; gap:8px;
		background:var(--wpmc-secondary); border:2.5px solid var(--wpmc-ink);
		padding:6px 16px; font-size:12.5px; font-weight:800; color:var(--wpmc-ink);
		box-shadow:3px 3px 0 var(--wpmc-ink); margin-bottom:26px; transform:rotate(-1.5deg);
	}

	/* ---------- آیکن چرخ‌دنده متحرک ---------- */
	.wpmc-gear-wrap{ width:104px; height:104px; margin:0 auto 22px; position:relative; }
	.wpmc-gear{ width:100%; height:100%; animation: wpmc-spin 9s linear infinite; }
	.wpmc-gear.small{ position:absolute; width:46px; height:46px; bottom:-6px; left:-14px; animation: wpmc-spin-rev 6s linear infinite; }
	@keyframes wpmc-spin{ from{ transform:rotate(0deg); } to{ transform:rotate(360deg); } }
	@keyframes wpmc-spin-rev{ from{ transform:rotate(0deg); } to{ transform:rotate(-360deg); } }

	.wpmc-title{ font-size:clamp(24px,4.2vw,36px); font-weight:900; margin:0 0 14px; line-height:1.5; }
	.wpmc-title mark{
		background:var(--wpmc-primary); color:var(--wpmc-ink); padding:2px 8px;
		box-decoration-break:clone; -webkit-box-decoration-break:clone;
	}
	.wpmc-desc{ font-size:15.5px; line-height:2; color:#333; max-width:520px; margin:0 auto 26px; font-weight:500; }

	.wpmc-sentence{
		display:inline-block; max-width:100%; background:var(--wpmc-bg2);
		border:2.5px solid var(--wpmc-ink); padding:12px 20px; font-size:15px; font-weight:700; line-height:1.9;
		margin-bottom:24px; box-shadow:4px 4px 0 var(--wpmc-ink);
	}
	.wpmc-sentence b{ color:var(--wpmc-ink); font-size:17px; font-family:'JetBrains Mono',monospace; }
	.wpmc-timer-label{ font-size:12px; font-weight:800; color:#555; margin-bottom:12px; letter-spacing:1px; text-transform:uppercase; }

	.wpmc-countdown{ display:flex; flex-direction:row-reverse; gap:12px; justify-content:center; flex-wrap:wrap; margin-bottom:32px; }
	.wpmc-countdown .box{
		background:var(--wpmc-paper); border:3px solid var(--wpmc-ink); box-shadow:4px 4px 0 var(--wpmc-ink);
		padding:14px 8px 10px; min-width:78px;
	}
	.wpmc-countdown .num{ font-size:30px; font-weight:800; display:block; font-family:'JetBrains Mono',monospace; color:var(--wpmc-ink); }
	.wpmc-countdown .num.flip{ animation: wpmc-flip .4s ease; }
	@keyframes wpmc-flip{ 0%{ transform:scale(1); } 40%{ transform:scale(.8) rotate(-4deg); opacity:.5; } 100%{ transform:scale(1) rotate(0); opacity:1; } }
	.wpmc-countdown .label{ font-size:10.5px; font-weight:700; color:#666; margin-top:6px; display:block; }
	.wpmc-countdown .sep{ align-self:center; font-size:22px; font-weight:900; color:var(--wpmc-ink); opacity:.35; font-family:'JetBrains Mono',monospace; }

	.wpmc-progress-wrap{ max-width:420px; margin:0 auto 30px; }
	.wpmc-progress-top{ display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:#555; margin-bottom:8px; }
	.wpmc-progress-top b{ color:var(--wpmc-ink); font-family:'JetBrains Mono',monospace; }
	.wpmc-progress{ width:100%; height:16px; border:2.5px solid var(--wpmc-ink); background:var(--wpmc-paper); overflow:hidden; }
	.wpmc-progress-bar{
		height:100%; background:repeating-linear-gradient(-45deg, var(--wpmc-primary) 0 10px, var(--wpmc-secondary) 10px 20px);
		background-size:200% 100%; animation: wpmc-stripes 1.4s linear infinite;
	}
	@keyframes wpmc-stripes{ from{ background-position:0 0; } to{ background-position:-28px 0; } }

	.wpmc-features{ display:flex; justify-content:center; gap:10px; flex-wrap:wrap; margin-bottom:28px; }
	.wpmc-feature{
		display:flex; align-items:center; gap:7px; background:var(--wpmc-paper);
		border:2.5px solid var(--wpmc-ink); padding:8px 14px; font-size:12.5px; font-weight:700; color:var(--wpmc-ink);
		box-shadow:3px 3px 0 var(--wpmc-ink);
	}
	.wpmc-feature svg{ width:16px; height:16px; flex-shrink:0; color:var(--wpmc-primary); }

	.wpmc-subscribe{ max-width:420px; margin:0 auto 28px; }
	.wpmc-subscribe-label{ font-size:12.5px; font-weight:700; color:#555; margin-bottom:10px; }
	.wpmc-subscribe-row{ display:flex; gap:0; }
	.wpmc-subscribe-row input[type=email]{
		flex:1; padding:12px 16px; border:2.5px solid var(--wpmc-ink); border-left:none;
		background:var(--wpmc-paper); color:var(--wpmc-ink); font-family:inherit; font-size:14px; outline:none;
	}
	.wpmc-subscribe-row input[type=email]::placeholder{ color:#999; }
	.wpmc-subscribe-row button{
		background:var(--wpmc-ink); color:var(--wpmc-paper); font-weight:800;
		border:2.5px solid var(--wpmc-ink); padding:0 20px; cursor:pointer; font-family:inherit; font-size:13.5px; white-space:nowrap;
	}
	.wpmc-subscribe-row button:hover{ background:var(--wpmc-primary); color:var(--wpmc-ink); }
	.wpmc-subscribe-msg{ margin-top:10px; font-size:12.5px; font-weight:700; }
	.wpmc-subscribe-msg.ok{ color:#166534; }
	.wpmc-subscribe-msg.err{ color:#b91c1c; }

	.wpmc-social{ display:flex; justify-content:center; gap:10px; margin-bottom:22px; }
	.wpmc-social a{
		width:42px; height:42px; display:flex; align-items:center; justify-content:center;
		background:var(--wpmc-paper); border:2.5px solid var(--wpmc-ink); color:var(--wpmc-ink); text-decoration:none;
		box-shadow:3px 3px 0 var(--wpmc-ink); transition:transform .12s ease, box-shadow .12s ease;
	}
	.wpmc-social a:hover{ transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--wpmc-ink); background:var(--wpmc-primary); }
	.wpmc-social svg{ width:19px; height:19px; }
	.wpmc-footer{ font-size:12.5px; font-weight:700; color:#555; }

	@media (max-width:560px){
		.wpmc-card{ padding:32px 18px 22px; box-shadow:7px 7px 0 var(--wpmc-ink); }
		.wpmc-countdown{ gap:7px; }
		.wpmc-countdown .box{ min-width:calc(25% - 7px); padding:10px 3px 8px; }
		.wpmc-countdown .num{ font-size:20px; }
		.wpmc-countdown .sep{ display:none; }
		.wpmc-title{ font-size:22px; }
		.wpmc-desc{ font-size:14px; }
		.wpmc-sentence{ font-size:13px; padding:10px 14px; }
		.wpmc-features{ gap:7px; }
		.wpmc-feature{ font-size:11.5px; padding:6px 10px; }
		.wpmc-subscribe-row{ flex-direction:column; }
		.wpmc-subscribe-row input[type=email]{ border-left:2.5px solid var(--wpmc-ink); border-bottom:none; }
		.wpmc-tape-bar span{ right:10px; font-size:9px; }
	}
	@media (prefers-reduced-motion: reduce){
		*{ animation-duration:.001ms !important; animation-iteration-count:1 !important; }
	}

	<?php if ( ! empty( $o['custom_css'] ) ) { echo $o['custom_css']; } ?>
</style>
</head>
<body>
	<div class="wpmc-tape-bar top"><span><?php echo esc_html( $o['badge_text'] ? $o['badge_text'] : 'در حال تعمیر' ); ?></span></div>

	<div class="wpmc-main">
		<div class="wpmc-wrap">
			<div class="wpmc-card">
				<span class="wpmc-corner-bolt tl"></span><span class="wpmc-corner-bolt tr"></span>
				<span class="wpmc-corner-bolt bl"></span><span class="wpmc-corner-bolt br"></span>

				<?php if ( ! empty( $o['badge_text'] ) ) : ?>
				<div class="wpmc-badge">🔧 <?php echo esc_html( $o['badge_text'] ); ?></div>
				<?php endif; ?>

				<div class="wpmc-gear-wrap" aria-hidden="true">
					<svg class="wpmc-gear" viewBox="0 0 100 100">
						<g fill="none" stroke="var(--wpmc-ink)" stroke-width="4" stroke-linejoin="round">
							<path d="M50 8 L58 8 L60 20 L70 24 L80 16 L88 24 L80 34 L84 44 L96 46 L96 54 L84 56 L80 66 L88 76 L80 84 L70 76 L60 80 L58 92 L50 92 L42 92 L40 80 L30 76 L20 84 L12 76 L20 66 L16 56 L4 54 L4 46 L16 44 L20 34 L12 24 L20 16 L30 24 L40 20 L42 8 Z" fill="var(--wpmc-secondary)"/>
							<circle cx="50" cy="50" r="16" fill="var(--wpmc-paper)"/>
						</g>
					</svg>
					<svg class="wpmc-gear small" viewBox="0 0 100 100">
						<g fill="none" stroke="var(--wpmc-ink)" stroke-width="5" stroke-linejoin="round">
							<path d="M50 8 L58 8 L60 20 L70 24 L80 16 L88 24 L80 34 L84 44 L96 46 L96 54 L84 56 L80 66 L88 76 L80 84 L70 76 L60 80 L58 92 L50 92 L42 92 L40 80 L30 76 L20 84 L12 76 L20 66 L16 56 L4 54 L4 46 L16 44 L20 34 L12 24 L20 16 L30 24 L40 20 L42 8 Z" fill="var(--wpmc-primary)"/>
							<circle cx="50" cy="50" r="16" fill="var(--wpmc-paper)"/>
						</g>
					</svg>
				</div>

				<h1 class="wpmc-title"><mark><?php echo esc_html( $o['title'] ); ?></mark></h1>
				<p class="wpmc-desc"><?php echo nl2br( esc_html( $o['description'] ) ); ?></p>

				<?php if ( $end_ts ) : ?>
				<div class="wpmc-sentence" id="wpmc-sentence" data-template="<?php echo esc_attr( $o['timer_sentence'] ); ?>"><?php echo esc_html( $o['timer_sentence'] ); ?></div>
				<div class="wpmc-timer-label"><?php echo esc_html( $o['timer_label'] ); ?></div>
				<div class="wpmc-countdown" id="wpmc-countdown">
					<div class="box"><span class="num" id="wpmc-d">۰۰</span><span class="label">روز</span></div>
					<div class="sep">:</div>
					<div class="box"><span class="num" id="wpmc-h">۰۰</span><span class="label">ساعت</span></div>
					<div class="sep">:</div>
					<div class="box"><span class="num" id="wpmc-m">۰۰</span><span class="label">دقیقه</span></div>
					<div class="sep">:</div>
					<div class="box"><span class="num" id="wpmc-s">۰۰</span><span class="label">ثانیه</span></div>
				</div>
				<?php endif; ?>

				<div class="wpmc-progress-wrap">
					<div class="wpmc-progress-top"><span><?php echo esc_html( $o['progress_label'] ); ?></span><b><?php echo esc_html( wpmc_to_fa_digits( $o['progress_percent'] ) ); ?>٪</b></div>
					<div class="wpmc-progress"><div class="wpmc-progress-bar" style="width:<?php echo (int) $o['progress_percent']; ?>%;"></div></div>
				</div>

				<?php if ( $o['feature1'] || $o['feature2'] || $o['feature3'] ) : ?>
				<div class="wpmc-features">
					<?php if ( $o['feature1'] ) : ?><div class="wpmc-feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg> <?php echo esc_html( $o['feature1'] ); ?></div><?php endif; ?>
					<?php if ( $o['feature2'] ) : ?><div class="wpmc-feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2 3 14h7l-1 8 10-12h-7z"/></svg> <?php echo esc_html( $o['feature2'] ); ?></div><?php endif; ?>
					<?php if ( $o['feature3'] ) : ?><div class="wpmc-feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18"/></svg> <?php echo esc_html( $o['feature3'] ); ?></div><?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $o['email_capture'] ) ) : ?>
				<div class="wpmc-subscribe">
					<div class="wpmc-subscribe-label">📬 دوست دارید همان لحظه‌ی بازگشایی سایت با ایمیل باخبر شوید؟</div>
					<form class="wpmc-subscribe-row" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpmc_subscribe">
						<input type="hidden" name="wpmc_subscribe_nonce" value="<?php echo esc_attr( $sub_nonce ); ?>">
						<input type="email" name="wpmc_email" placeholder="ایمیل شما" required>
						<button type="submit">اطلاع بده</button>
					</form>
					<?php if ( isset( $_GET['wpmc_sub'] ) && 'ok' === $_GET['wpmc_sub'] ) : ?>
						<div class="wpmc-subscribe-msg ok">✓ ثبت شد! هنگام بازگشایی به شما خبر می‌دهیم.</div>
					<?php elseif ( isset( $_GET['wpmc_sub'] ) && 'err' === $_GET['wpmc_sub'] ) : ?>
						<div class="wpmc-subscribe-msg err">✕ ایمیل معتبر نبود، دوباره تلاش کنید.</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="wpmc-social">
					<a href="https://instagram.com/grootvision" target="_blank" rel="noopener" aria-label="اینستاگرام">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
					</a>
					<a href="https://t.me/grootvision" target="_blank" rel="noopener" aria-label="تلگرام">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/></svg>
					</a>
				</div>

				<div class="wpmc-footer"><?php echo esc_html( $o['footer_text'] ); ?></div>
			</div>
		</div>
	</div>

	<div class="wpmc-tape-bar bottom"><span dir="ltr" class="wpmc-mono">GROOT VISION</span></div>

	<?php if ( $end_ts ) : ?>
	<script>
	(function(){
		var target = <?php echo (int) $end_ts * 1000; ?>;
		var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
		function toFa(num){ return String(num).replace(/[0-9]/g, function(d){ return fa[d]; }); }
		function pad(num){ return String(num).padStart(2,'0'); }
		var prev = { d:null, h:null, m:null, s:null };
		function setVal(id, val, key){
			var el = document.getElementById(id);
			var faVal = toFa(pad(val));
			if(prev[key] !== faVal){
				el.textContent = faVal;
				el.classList.remove('flip');
				void el.offsetWidth;
				el.classList.add('flip');
				prev[key] = faVal;
			}
		}
		var sentenceEl = document.getElementById('wpmc-sentence');
		var template = sentenceEl ? sentenceEl.getAttribute('data-template') : '';
		function tick(){
			var now = Date.now();
			var diff = Math.max(0, target - now);
			var d = Math.floor(diff / (1000*60*60*24));
			var h = Math.floor((diff / (1000*60*60)) % 24);
			var m = Math.floor((diff / (1000*60)) % 60);
			var s = Math.floor((diff / 1000) % 60);
			setVal('wpmc-d', d, 'd');
			setVal('wpmc-h', h, 'h');
			setVal('wpmc-m', m, 'm');
			setVal('wpmc-s', s, 's');
			if( sentenceEl && template ){
				var text = template
					.replace('{days}', '<b>' + toFa(d) + '</b>')
					.replace('{hours}', '<b>' + toFa(h) + '</b>')
					.replace('{minutes}', '<b>' + toFa(m) + '</b>');
				sentenceEl.innerHTML = text;
			}
			if( diff <= 0 ){ clearInterval(timer); }
		}
		tick();
		var timer = setInterval(tick, 1000);
	})();
	</script>
	<?php endif; ?>
</body>
</html>
	<?php
}

/**
 * تم دوم: ترمینال/فضایی — پنجره‌ی ترمینال شناور روی فضای پرستاره،
 * فونت مونو، رنگ نئون، افکت اسکن‌لاین و مکان‌نمای چشمک‌زن.
 */
function wpmc_render_terminal_theme( $o, $end_ts ) {
	$site_name = get_bloginfo( 'name' );
	$primary   = esc_attr( $o['color_primary'] );
	$secondary = esc_attr( $o['color_secondary'] );
	$ink       = esc_attr( $o['color_accent'] ?? '#0A0E14' );
	$bg1       = esc_attr( $o['color_bg1'] );
	$bg2       = esc_attr( $o['color_bg2'] );
	$favicon   = get_site_icon_url();
	$sub_nonce = wp_create_nonce( 'wpmc_subscribe' );
	$host      = wp_parse_url( home_url(), PHP_URL_HOST );
	?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ( ! empty( $o['seo_noindex'] ) ) : ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<title><?php echo esc_html( $o['title'] . ' — ' . $site_name ); ?></title>
<?php if ( $favicon ) : ?><link rel="icon" href="<?php echo esc_url( $favicon ); ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=vazirmatn:400,600,700,800,900|jetbrains-mono:400,500,600,700,800" rel="stylesheet">
<style>
	:root{
		--wpmc-primary: <?php echo $primary; ?>;
		--wpmc-secondary: <?php echo $secondary; ?>;
		--wpmc-ink: <?php echo $ink; ?>;
		--wpmc-bg1: <?php echo $bg1; ?>;
		--wpmc-bg2: <?php echo $bg2; ?>;
	}
	*{ box-sizing:border-box; }
	html,body{ margin:0; padding:0; min-height:100vh; }
	body{
		position:relative; display:flex; align-items:center; justify-content:center; min-height:100vh;
		padding:28px 16px; overflow-x:hidden;
		font-family:'JetBrains Mono', ui-monospace, monospace;
		color: var(--wpmc-primary);
		background:
			radial-gradient(ellipse at 20% 15%, color-mix(in srgb, var(--wpmc-secondary) 20%, transparent) 0%, transparent 45%),
			radial-gradient(ellipse at 82% 78%, color-mix(in srgb, var(--wpmc-primary) 16%, transparent) 0%, transparent 50%),
			var(--wpmc-bg1);
	}
	.wpmc-fa{ font-family:'Vazirmatn', Tahoma, sans-serif; }

	/* ---------- فضای پرستاره ---------- */
	.wpmc-stars, .wpmc-stars::after{
		content:""; position:fixed; inset:-50%; z-index:0; pointer-events:none;
		background-image:
			radial-gradient(1.6px 1.6px at 20px 30px, #fff, transparent),
			radial-gradient(1.2px 1.2px at 90px 120px, #fff, transparent),
			radial-gradient(1.8px 1.8px at 160px 60px, #fff, transparent),
			radial-gradient(1.2px 1.2px at 220px 180px, #fff, transparent),
			radial-gradient(1.5px 1.5px at 260px 40px, #fff, transparent),
			radial-gradient(1.2px 1.2px at 320px 140px, #fff, transparent);
		background-repeat:repeat; background-size:360px 220px;
		opacity:.5; animation: wpmc-drift 90s linear infinite;
	}
	.wpmc-stars::after{ background-position:120px 90px; opacity:.3; animation-duration:130s; animation-direction:reverse; }
	@keyframes wpmc-drift{ from{ transform:translate(0,0); } to{ transform:translate(-360px,-220px); } }

	/* ---------- افکت اسکن‌لاین روی کل صفحه ---------- */
	.wpmc-scanlines{
		position:fixed; inset:0; z-index:5; pointer-events:none; opacity:.15;
		background:repeating-linear-gradient(0deg, #000 0 1px, transparent 1px 3px);
		mix-blend-mode:overlay;
	}

	.wpmc-wrap{ position:relative; z-index:3; width:100%; max-width:640px; }

	/* ---------- پنجره‌ی ترمینال ---------- */
	.wpmc-term{
		background: color-mix(in srgb, var(--wpmc-bg2) 88%, black);
		border:1px solid color-mix(in srgb, var(--wpmc-primary) 35%, transparent);
		border-radius:12px; overflow:hidden;
		box-shadow: 0 0 0 1px color-mix(in srgb, var(--wpmc-primary) 12%, transparent), 0 30px 80px rgba(0,0,0,.6), 0 0 60px color-mix(in srgb, var(--wpmc-primary) 18%, transparent);
		animation: wpmc-boot .6s ease;
	}
	@keyframes wpmc-boot{ from{ opacity:0; transform:scale(.97) translateY(10px); } to{ opacity:1; transform:scale(1) translateY(0); } }
	.wpmc-term-bar{
		display:flex; align-items:center; gap:8px; padding:11px 14px;
		background:color-mix(in srgb, var(--wpmc-bg2) 96%, black);
		border-bottom:1px solid color-mix(in srgb, var(--wpmc-primary) 20%, transparent);
	}
	.wpmc-term-dot{ width:11px; height:11px; border-radius:50%; }
	.wpmc-term-dot.r{ background:#ff5f56; } .wpmc-term-dot.y{ background:#ffbd2e; } .wpmc-term-dot.g{ background:#27c93f; }
	.wpmc-term-path{ margin-inline-start:10px; font-size:11.5px; color:color-mix(in srgb, var(--wpmc-primary) 65%, transparent); direction:ltr; unicode-bidi:plaintext; }
	.wpmc-term-body{ padding:34px 30px 28px; text-align:center; }

	.wpmc-prompt{ font-size:12px; color:color-mix(in srgb, var(--wpmc-primary) 60%, transparent); margin-bottom:18px; text-align:right; direction:ltr; unicode-bidi:plaintext; }
	.wpmc-prompt b{ color:var(--wpmc-secondary); }

	.wpmc-badge{
		display:inline-flex; align-items:center; gap:8px;
		border:1px solid var(--wpmc-primary); color:var(--wpmc-primary);
		padding:5px 14px; border-radius:20px; font-size:11.5px; font-weight:700; letter-spacing:.5px;
		text-shadow:0 0 8px color-mix(in srgb, var(--wpmc-primary) 70%, transparent);
		box-shadow: inset 0 0 12px color-mix(in srgb, var(--wpmc-primary) 15%, transparent);
		margin-bottom:24px;
	}
	.wpmc-badge .dot{ width:7px; height:7px; border-radius:50%; background:var(--wpmc-primary); box-shadow:0 0 8px var(--wpmc-primary); animation: wpmc-blink 1.4s ease infinite; }
	@keyframes wpmc-blink{ 0%,100%{ opacity:1; } 50%{ opacity:.25; } }

	.wpmc-title{
		font-size:clamp(22px,4vw,32px); font-weight:800; margin:0 0 14px; line-height:1.6;
		color:#fff; text-shadow:0 0 18px color-mix(in srgb, var(--wpmc-primary) 60%, transparent);
	}
	.wpmc-title .cursor{ display:inline-block; width:.5em; background:var(--wpmc-secondary); box-shadow:0 0 10px var(--wpmc-secondary); animation:wpmc-blink 1s steps(1) infinite; margin-inline-start:4px; }
	.wpmc-desc{ font-size:14.5px; line-height:2.1; color:color-mix(in srgb, var(--wpmc-primary) 75%, white 15%); max-width:480px; margin:0 auto 26px; }

	.wpmc-sentence{
		display:inline-block; max-width:100%;
		border:1px dashed color-mix(in srgb, var(--wpmc-secondary) 55%, transparent);
		background:color-mix(in srgb, var(--wpmc-secondary) 6%, transparent);
		border-radius:8px; padding:12px 20px; font-size:13.5px; line-height:2; margin-bottom:22px; color:color-mix(in srgb, var(--wpmc-primary) 85%, white 10%);
	}
	.wpmc-sentence b{ color:var(--wpmc-secondary); font-weight:800; text-shadow:0 0 10px color-mix(in srgb, var(--wpmc-secondary) 65%, transparent); }
	.wpmc-timer-label{ font-size:10.5px; color:color-mix(in srgb, var(--wpmc-primary) 55%, transparent); margin-bottom:12px; letter-spacing:2px; text-transform:uppercase; }

	.wpmc-countdown{ display:flex; flex-direction:row-reverse; gap:10px; justify-content:center; flex-wrap:wrap; margin-bottom:30px; }
	.wpmc-countdown .box{
		border:1px solid color-mix(in srgb, var(--wpmc-primary) 40%, transparent); border-radius:8px;
		background:color-mix(in srgb, var(--wpmc-primary) 6%, transparent);
		padding:14px 6px 10px; min-width:70px;
		box-shadow: inset 0 0 14px color-mix(in srgb, var(--wpmc-primary) 12%, transparent);
	}
	.wpmc-countdown .num{ font-size:26px; font-weight:800; display:block; color:#fff; text-shadow:0 0 14px color-mix(in srgb, var(--wpmc-primary) 75%, transparent); }
	.wpmc-countdown .num.flip{ animation: wpmc-glitch .35s ease; }
	@keyframes wpmc-glitch{ 0%{ opacity:1; } 30%{ opacity:.3; transform:translateX(2px); text-shadow:2px 0 var(--wpmc-secondary),-2px 0 var(--wpmc-primary); } 100%{ opacity:1; transform:translateX(0); } }
	.wpmc-countdown .label{ font-size:9.5px; color:color-mix(in srgb, var(--wpmc-primary) 55%, transparent); margin-top:6px; display:block; letter-spacing:1px; }
	.wpmc-countdown .sep{ align-self:center; font-size:20px; color:var(--wpmc-primary); opacity:.4; }

	.wpmc-progress-wrap{ max-width:420px; margin:0 auto 30px; text-align:right; direction:ltr; }
	.wpmc-progress-top{ display:flex; justify-content:space-between; font-size:11px; color:color-mix(in srgb, var(--wpmc-primary) 60%, transparent); margin-bottom:8px; }
	.wpmc-progress-top b{ color:var(--wpmc-secondary); }
	.wpmc-progress{ width:100%; height:6px; border-radius:6px; background:color-mix(in srgb, var(--wpmc-primary) 12%, transparent); overflow:hidden; }
	.wpmc-progress-bar{ height:100%; border-radius:6px; background:linear-gradient(90deg, var(--wpmc-primary), var(--wpmc-secondary)); box-shadow:0 0 12px color-mix(in srgb, var(--wpmc-primary) 70%, transparent); position:relative; overflow:hidden; }
	.wpmc-progress-bar::after{ content:""; position:absolute; inset:0; background:linear-gradient(90deg,transparent,rgba(255,255,255,.6),transparent); width:35%; animation:wpmc-shimmer 2s linear infinite; }
	@keyframes wpmc-shimmer{ from{ transform:translateX(-140%); } to{ transform:translateX(340%); } }

	.wpmc-features{ display:flex; justify-content:center; gap:9px; flex-wrap:wrap; margin-bottom:26px; direction:ltr; }
	.wpmc-feature{
		display:flex; align-items:center; gap:7px;
		border:1px solid color-mix(in srgb, var(--wpmc-primary) 30%, transparent); border-radius:6px;
		padding:8px 13px; font-size:11.5px; color:color-mix(in srgb, var(--wpmc-primary) 85%, white 10%);
		background:color-mix(in srgb, var(--wpmc-primary) 5%, transparent);
	}
	.wpmc-feature::before{ content:"›"; color:var(--wpmc-secondary); font-weight:800; }

	.wpmc-subscribe{ max-width:420px; margin:0 auto 26px; direction:ltr; text-align:left; }
	.wpmc-subscribe-label{ font-size:11px; color:color-mix(in srgb, var(--wpmc-primary) 55%, transparent); margin-bottom:9px; unicode-bidi:plaintext; }
	.wpmc-subscribe-row{ display:flex; gap:0; border:1px solid color-mix(in srgb, var(--wpmc-primary) 45%, transparent); border-radius:6px; overflow:hidden; }
	.wpmc-subscribe-row input[type=email]{
		flex:1; padding:11px 14px; border:none; background:transparent; color:#fff; font-family:inherit; font-size:12.5px; outline:none;
	}
	.wpmc-subscribe-row input[type=email]::placeholder{ color:color-mix(in srgb, var(--wpmc-primary) 40%, transparent); }
	.wpmc-subscribe-row button{
		background:var(--wpmc-primary); color:var(--wpmc-bg1); font-weight:800; border:none; padding:0 18px; cursor:pointer; font-family:inherit; font-size:12px;
	}
	.wpmc-subscribe-row button:hover{ background:var(--wpmc-secondary); }
	.wpmc-subscribe-msg{ margin-top:9px; font-size:11.5px; unicode-bidi:plaintext; }
	.wpmc-subscribe-msg.ok{ color:#4ade80; } .wpmc-subscribe-msg.err{ color:#fca5a5; }

	.wpmc-social{ display:flex; justify-content:center; gap:10px; margin-bottom:20px; }
	.wpmc-social a{
		width:38px; height:38px; display:flex; align-items:center; justify-content:center; border-radius:6px;
		border:1px solid color-mix(in srgb, var(--wpmc-primary) 35%, transparent); color:var(--wpmc-primary); text-decoration:none;
		transition:all .2s ease;
	}
	.wpmc-social a:hover{ background:var(--wpmc-primary); color:var(--wpmc-bg1); box-shadow:0 0 16px color-mix(in srgb, var(--wpmc-primary) 60%, transparent); }
	.wpmc-social svg{ width:17px; height:17px; }

	.wpmc-footer{ font-size:11px; color:color-mix(in srgb, var(--wpmc-primary) 50%, transparent); direction:ltr; unicode-bidi:plaintext; }
	.wpmc-footer .cursor{ display:inline-block; width:.55em; background:var(--wpmc-primary); animation:wpmc-blink 1s steps(1) infinite; margin-inline-start:3px; vertical-align:-2px; height:1em; }

	@media (max-width:560px){
		.wpmc-term-body{ padding:26px 18px 22px; }
		.wpmc-countdown{ gap:6px; }
		.wpmc-countdown .box{ min-width:calc(25% - 6px); padding:10px 3px 8px; }
		.wpmc-countdown .num{ font-size:19px; }
		.wpmc-countdown .sep{ display:none; }
		.wpmc-title{ font-size:19px; }
		.wpmc-desc{ font-size:13px; }
		.wpmc-subscribe-row{ flex-direction:column; }
	}
	@media (prefers-reduced-motion: reduce){ *{ animation-duration:.001ms !important; animation-iteration-count:1 !important; } }

	<?php if ( ! empty( $o['custom_css'] ) ) { echo $o['custom_css']; } ?>
</style>
</head>
<body>
	<div class="wpmc-stars" aria-hidden="true"></div>
	<div class="wpmc-scanlines" aria-hidden="true"></div>

	<div class="wpmc-wrap">
		<div class="wpmc-term">
			<div class="wpmc-term-bar">
				<span class="wpmc-term-dot r"></span><span class="wpmc-term-dot y"></span><span class="wpmc-term-dot g"></span>
				<span class="wpmc-term-path">visitor@<?php echo esc_html( $host ); ?>: ~/status</span>
			</div>
			<div class="wpmc-term-body">
				<div class="wpmc-prompt"><?php echo esc_html( $host ); ?>/status <b>--watch</b></div>

				<?php if ( ! empty( $o['badge_text'] ) ) : ?>
				<div class="wpmc-badge"><span class="dot"></span> <?php echo esc_html( strtoupper( $o['badge_text'] ) ); ?></div>
				<?php endif; ?>

				<h1 class="wpmc-title wpmc-fa"><?php echo esc_html( $o['title'] ); ?><span class="cursor">&nbsp;</span></h1>
				<p class="wpmc-desc wpmc-fa"><?php echo nl2br( esc_html( $o['description'] ) ); ?></p>

				<?php if ( $end_ts ) : ?>
				<div class="wpmc-sentence wpmc-fa" id="wpmc-sentence" data-template="<?php echo esc_attr( $o['timer_sentence'] ); ?>"><?php echo esc_html( $o['timer_sentence'] ); ?></div>
				<div class="wpmc-timer-label wpmc-fa"><?php echo esc_html( $o['timer_label'] ); ?></div>
				<div class="wpmc-countdown" id="wpmc-countdown">
					<div class="box"><span class="num" id="wpmc-d">00</span><span class="label">DAY</span></div>
					<div class="sep">:</div>
					<div class="box"><span class="num" id="wpmc-h">00</span><span class="label">HR</span></div>
					<div class="sep">:</div>
					<div class="box"><span class="num" id="wpmc-m">00</span><span class="label">MIN</span></div>
					<div class="sep">:</div>
					<div class="box"><span class="num" id="wpmc-s">00</span><span class="label">SEC</span></div>
				</div>
				<?php endif; ?>

				<div class="wpmc-progress-wrap">
					<div class="wpmc-progress-top wpmc-fa"><span><?php echo esc_html( $o['progress_label'] ); ?></span><b><?php echo (int) $o['progress_percent']; ?>%</b></div>
					<div class="wpmc-progress"><div class="wpmc-progress-bar" style="width:<?php echo (int) $o['progress_percent']; ?>%;"></div></div>
				</div>

				<?php if ( $o['feature1'] || $o['feature2'] || $o['feature3'] ) : ?>
				<div class="wpmc-features wpmc-fa">
					<?php if ( $o['feature1'] ) : ?><div class="wpmc-feature"><?php echo esc_html( $o['feature1'] ); ?></div><?php endif; ?>
					<?php if ( $o['feature2'] ) : ?><div class="wpmc-feature"><?php echo esc_html( $o['feature2'] ); ?></div><?php endif; ?>
					<?php if ( $o['feature3'] ) : ?><div class="wpmc-feature"><?php echo esc_html( $o['feature3'] ); ?></div><?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $o['email_capture'] ) ) : ?>
				<div class="wpmc-subscribe">
					<div class="wpmc-subscribe-label"># notify --email-me-on-relaunch</div>
					<form class="wpmc-subscribe-row" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpmc_subscribe">
						<input type="hidden" name="wpmc_subscribe_nonce" value="<?php echo esc_attr( $sub_nonce ); ?>">
						<input type="email" name="wpmc_email" placeholder="you@email.com" required>
						<button type="submit">notify_me()</button>
					</form>
					<?php if ( isset( $_GET['wpmc_sub'] ) && 'ok' === $_GET['wpmc_sub'] ) : ?>
						<div class="wpmc-subscribe-msg ok">// saved — we'll ping you at relaunch.</div>
					<?php elseif ( isset( $_GET['wpmc_sub'] ) && 'err' === $_GET['wpmc_sub'] ) : ?>
						<div class="wpmc-subscribe-msg err">// error: invalid email, try again.</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="wpmc-social">
					<a href="https://instagram.com/grootvision" target="_blank" rel="noopener" aria-label="اینستاگرام">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
					</a>
					<a href="https://t.me/grootvision" target="_blank" rel="noopener" aria-label="تلگرام">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/></svg>
					</a>
				</div>

				<div class="wpmc-footer">&gt; <?php echo esc_html( $o['footer_text'] ); ?><span class="cursor"></span></div>
			</div>
		</div>
	</div>

	<?php if ( $end_ts ) : ?>
	<script>
	(function(){
		var target = <?php echo (int) $end_ts * 1000; ?>;
		function pad(num){ return String(num).padStart(2,'0'); }
		var prev = { d:null, h:null, m:null, s:null };
		function setVal(id, val, key){
			var el = document.getElementById(id);
			var v = pad(val);
			if(prev[key] !== v){
				el.textContent = v;
				el.classList.remove('flip');
				void el.offsetWidth;
				el.classList.add('flip');
				prev[key] = v;
			}
		}
		var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
		function toFa(num){ return String(num).replace(/[0-9]/g, function(d){ return fa[d]; }); }
		var sentenceEl = document.getElementById('wpmc-sentence');
		var template = sentenceEl ? sentenceEl.getAttribute('data-template') : '';
		function tick(){
			var now = Date.now();
			var diff = Math.max(0, target - now);
			var d = Math.floor(diff / (1000*60*60*24));
			var h = Math.floor((diff / (1000*60*60)) % 24);
			var m = Math.floor((diff / (1000*60)) % 60);
			var s = Math.floor((diff / 1000) % 60);
			setVal('wpmc-d', d, 'd');
			setVal('wpmc-h', h, 'h');
			setVal('wpmc-m', m, 'm');
			setVal('wpmc-s', s, 's');
			if( sentenceEl && template ){
				var text = template
					.replace('{days}', '<b>' + toFa(d) + '</b>')
					.replace('{hours}', '<b>' + toFa(h) + '</b>')
					.replace('{minutes}', '<b>' + toFa(m) + '</b>');
				sentenceEl.innerHTML = text;
			}
			if( diff <= 0 ){ clearInterval(timer); }
		}
		tick();
		var timer = setInterval(tick, 1000);
	})();
	</script>
	<?php endif; ?>
</body>
</html>
	<?php
}

/** تبدیل اعداد انگلیسی به فارسی برای نمایش در صفحه. */
function wpmc_to_fa_digits( $num ) {
	$fa = array( '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' );
	return preg_replace_callback( '/[0-9]/', function( $m ) use ( $fa ) {
		return $fa[ (int) $m[0] ];
	}, (string) $num );
}