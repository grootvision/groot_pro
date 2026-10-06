<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — آمار بازدید و رفتار کاربران (نسخه ۲)
 *  - ثبت نشست‌ها (Sessions)، بازدید صفحات، کلیک‌ها، لینک‌های خروجی و دانلودها
 *  - تشخیص منبع ورودی: گوگل/بینگ/…، شبکه‌های اجتماعی، سایت‌های ارجاع‌دهنده،
 *    مستقیم، ایمیل، تبلیغات و کمپین‌های UTM
 *  - مدت‌زمان واقعی حضور، عمق اسکرول، نرخ پرش، مسیر حرکت بین صفحات
 *  - دستگاه، مرورگر، سیستم‌عامل، کشور (با Cloudflare)، زبان
 *  - کاربران آنلاین، گزارش‌های مقایسه‌ای، خروجی CSV، گزارش چاپی و ایمیل زمان‌بندی‌شده
 *  - بدون کوکی؛ IP فقط به‌صورت هش ذخیره می‌شود
 * ==========================================================
 */
define( 'GV_VA_OPT', 'gv_visitor_analytics_settings' );
define( 'GV_VA_NONCE', 'gv_va_nonce_action' );
define( 'GV_VA_DB_VERSION', '2.0' );

/* ==========================================================================
   ۰) توابع کمکی عمومی
   ========================================================================== */

function gv_va_default_settings() {
	return array(
		'enabled'        => 0,
		'track_clicks'   => 1,
		'track_time'     => 1,
		'track_scroll'   => 1,
		'track_path'     => 1,
		'ignore_admins'  => 1,
		'ignore_bots'    => 1,
		'exclude_ips'    => '',
		'retention_days' => 180,
		'report_freq'    => 'off', // off | daily | weekly | monthly
		'report_email'   => '',
	);
}

function gv_va_get_settings() {
	return wp_parse_args( get_option( GV_VA_OPT, array() ), gv_va_default_settings() );
}

function gv_va_t( $name ) { global $wpdb; return $wpdb->prefix . 'gv_' . $name; }

function gv_va_fa( $s ) {
	return strtr( (string) $s, array(
		'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
		'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', ',' => '٬', '.' => '٫',
	) );
}
function gv_va_n( $n, $d = 0 ) { return gv_va_fa( number_format( (float) $n, $d, '.', ',' ) ); }
function gv_va_pct( $a, $b ) { return $b > 0 ? gv_va_n( $a / $b * 100, 1 ) . '٪' : '—'; }
function gv_va_dur( $s ) {
	$s = (int) round( $s );
	if ( $s <= 0 ) { return '—'; }
	return gv_va_fa( sprintf( '%02d:%02d', intdiv( $s, 60 ), $s % 60 ) );
}

/* تبدیل تاریخ میلادی به شمسی */
function gv_va_g2j( $gy, $gm, $gd ) {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
	$days %= 12053;
	$jy   += 4 * (int) ( $days / 1461 );
	$days %= 1461;
	if ( $days > 365 ) { $jy += (int) ( ( $days - 1 ) / 365 ); $days = ( $days - 1 ) % 365; }
	if ( $days < 186 ) { $jm = 1 + (int) ( $days / 31 ); $jd = 1 + ( $days % 31 ); }
	else { $jm = 7 + (int) ( ( $days - 186 ) / 30 ); $jd = 1 + ( ( $days - 186 ) % 30 ); }
	return array( $jy, $jm, $jd );
}
function gv_va_jdate( $ymd, $with_year = false ) {
	$m = array( '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	$p = explode( '-', substr( $ymd, 0, 10 ) );
	if ( count( $p ) < 3 ) { return $ymd; }
	list( $jy, $jm, $jd ) = gv_va_g2j( (int) $p[0], (int) $p[1], (int) $p[2] );
	return gv_va_fa( $jd . ' ' . $m[ $jm ] . ( $with_year ? ' ' . $jy : '' ) );
}
function gv_va_jdt( $dt ) { return gv_va_jdate( $dt, true ) . ' — ' . gv_va_fa( substr( $dt, 11, 5 ) ); }

function gv_va_host( $url ) {
	$h = strtolower( (string) parse_url( (string) $url, PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $h );
}
function gv_va_path( $url ) {
	$p = parse_url( (string) $url, PHP_URL_PATH );
	$p = $p ? urldecode( $p ) : '/';
	return mb_substr( wp_strip_all_tags( $p ), 0, 200 );
}

function gv_va_types() {
	return array(
		'organic'  => array( 'موتورهای جستجو', '#16a34a' ),
		'direct'   => array( 'مستقیم', '#2563eb' ),
		'social'   => array( 'شبکه‌های اجتماعی', '#db2777' ),
		'referral' => array( 'ارجاع از سایت‌ها', '#d97706' ),
		'paid'     => array( 'تبلیغات پولی', '#dc2626' ),
		'email'    => array( 'ایمیل', '#7c3aed' ),
	);
}
function gv_va_badge( $type ) {
	$t = gv_va_types();
	$l = isset( $t[ $type ] ) ? $t[ $type ] : array( $type, '#64748b' );
	return '<span class="gvva-badge" style="--c:' . esc_attr( $l[1] ) . '">' . esc_html( $l[0] ) . '</span>';
}
function gv_va_flag( $cc ) {
	$cc = strtoupper( (string) $cc );
	if ( ! preg_match( '/^[A-Z]{2}$/', $cc ) || ! function_exists( 'mb_chr' ) ) { return esc_html( $cc ?: '—' ); }
	return mb_chr( 0x1F1E6 + ord( $cc[0] ) - 65 ) . mb_chr( 0x1F1E6 + ord( $cc[1] ) - 65 ) . ' ' . esc_html( $cc );
}

/* ==========================================================================
   ۱) ساخت/به‌روزرسانی جداول دیتابیس
   ========================================================================== */

add_action( 'plugins_loaded', 'gv_va_maybe_install_db' );
function gv_va_maybe_install_db() {
	if ( get_option( 'gv_va_db_version' ) === GV_VA_DB_VERSION ) { return; }

	global $wpdb;
	$cc = $wpdb->get_charset_collate();
	$ts = gv_va_t( 'sessions' );
	$tv = gv_va_t( 'visits' );
	$te = gv_va_t( 'events' );
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$ts} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id VARCHAR(40) NOT NULL,
		visitor_id VARCHAR(40) NOT NULL,
		started_at DATETIME NOT NULL,
		last_seen DATETIME NOT NULL,
		duration INT UNSIGNED NOT NULL DEFAULT 0,
		pages INT UNSIGNED NOT NULL DEFAULT 1,
		is_new TINYINT(1) NOT NULL DEFAULT 1,
		landing_url TEXT NULL,
		landing_path VARCHAR(255) NULL,
		exit_path VARCHAR(255) NULL,
		referrer TEXT NULL,
		source_type VARCHAR(20) NOT NULL DEFAULT 'direct',
		source_name VARCHAR(100) NULL,
		source_domain VARCHAR(191) NULL,
		utm_source VARCHAR(100) NULL,
		utm_medium VARCHAR(100) NULL,
		utm_campaign VARCHAR(150) NULL,
		device VARCHAR(20) NULL,
		browser VARCHAR(30) NULL,
		os VARCHAR(30) NULL,
		country VARCHAR(5) NULL,
		lang VARCHAR(10) NULL,
		screen VARCHAR(15) NULL,
		ip_hash VARCHAR(64) NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY session_id (session_id),
		KEY started_at (started_at),
		KEY visitor_id (visitor_id),
		KEY source_type (source_type)
	) {$cc};" );

	dbDelta( "CREATE TABLE {$tv} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id VARCHAR(40) NOT NULL,
		pv_key VARCHAR(40) NULL,
		page_url TEXT NOT NULL,
		page_path VARCHAR(255) NULL,
		page_title VARCHAR(255) NULL,
		prev_path VARCHAR(255) NULL,
		referrer TEXT NULL,
		entry_time DATETIME NOT NULL,
		last_seen DATETIME NOT NULL,
		time_spent INT UNSIGNED NOT NULL DEFAULT 0,
		scroll_depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
		device VARCHAR(20) NULL,
		ip_hash VARCHAR(64) NULL,
		PRIMARY KEY  (id),
		KEY session_id (session_id),
		KEY pv_key (pv_key),
		KEY entry_time (entry_time)
	) {$cc};" );

	dbDelta( "CREATE TABLE {$te} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id VARCHAR(40) NOT NULL,
		event_type VARCHAR(20) NOT NULL,
		page_url TEXT NOT NULL,
		page_path VARCHAR(255) NULL,
		target_text VARCHAR(255) NULL,
		target_href TEXT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY session_id (session_id),
		KEY event_type (event_type),
		KEY created_at (created_at)
	) {$cc};" );

	update_option( 'gv_va_db_version', GV_VA_DB_VERSION );
}

/* ==========================================================================
   ۲) تشخیص منبع ورودی، دستگاه، مرورگر، ربات
   ========================================================================== */

function gv_va_classify_source( $referrer, $landing ) {
	$out = array( 'type' => 'direct', 'name' => 'مستقیم', 'domain' => '', 'utm_source' => '', 'utm_medium' => '', 'utm_campaign' => '' );

	$q  = array();
	$qs = parse_url( (string) $landing, PHP_URL_QUERY );
	if ( $qs ) { parse_str( $qs, $q ); }
	$g = function ( $k ) use ( $q ) {
		return ( isset( $q[ $k ] ) && is_string( $q[ $k ] ) ) ? sanitize_text_field( mb_substr( $q[ $k ], 0, 100 ) ) : '';
	};
	$out['utm_source']   = $g( 'utm_source' );
	$out['utm_medium']   = $g( 'utm_medium' );
	$out['utm_campaign'] = $g( 'utm_campaign' );

	$host = gv_va_host( $referrer );
	if ( $host === gv_va_host( home_url() ) ) { $host = ''; }
	$out['domain'] = $host;

	// regex هاست، نام نمایشی، نوع
	$rules = array(
		array( '/^mail\.google\.com$/', 'Gmail', 'email' ),
		array( '/outlook\.(live|office|office365)\.com$|^mail\.|webmail/', 'ایمیل', 'email' ),
		array( '/(^|\.)google\.[a-z.]+$|googlequicksearchbox/', 'Google', 'organic' ),
		array( '/(^|\.)bing\.com$/', 'Bing', 'organic' ),
		array( '/(^|\.)yahoo\.com$|search\.yahoo/', 'Yahoo', 'organic' ),
		array( '/duckduckgo\.com$/', 'DuckDuckGo', 'organic' ),
		array( '/(^|\.)yandex\.[a-z.]+$/', 'Yandex', 'organic' ),
		array( '/(^|\.)baidu\.com$/', 'Baidu', 'organic' ),
		array( '/ecosia\.org$/', 'Ecosia', 'organic' ),
		array( '/search\.brave\.com$/', 'Brave Search', 'organic' ),
		array( '/startpage\.com$|qwant\.com$|naver\.com$|seznam\.cz$/', 'موتور جستجوی دیگر', 'organic' ),
		array( '/facebook\.com$|(^|\.)fb\.com$|(^|\.)fb\.me$|fbapp/', 'Facebook', 'social' ),
		array( '/instagram/', 'Instagram', 'social' ),
		array( '/(^|\.)t\.co$|twitter\.com$|(^|\.)x\.com$/', 'X (Twitter)', 'social' ),
		array( '/linkedin\.com$|lnkd\.in$/', 'LinkedIn', 'social' ),
		array( '/telegram|(^|\.)t\.me$/', 'Telegram', 'social' ),
		array( '/whatsapp|(^|\.)wa\.me$/', 'WhatsApp', 'social' ),
		array( '/youtube\.com$|youtu\.be$/', 'YouTube', 'social' ),
		array( '/pinterest\.[a-z.]+$|(^|\.)pin\.it$/', 'Pinterest', 'social' ),
		array( '/reddit\.com$/', 'Reddit', 'social' ),
		array( '/tiktok\.com$/', 'TikTok', 'social' ),
		array( '/aparat\.com$/', 'آپارات', 'social' ),
		array( '/eitaa\.com$/', 'ایتا', 'social' ),
		array( '/rubika\.ir$/', 'روبیکا', 'social' ),
		array( '/(^|\.)bale\.ai$|(^|\.)ble\.ir$/', 'بله', 'social' ),
	);
	$matched = false;
	if ( $host ) {
		foreach ( $rules as $r ) {
			if ( preg_match( $r[0], $host ) ) { $out['name'] = $r[1]; $out['type'] = $r[2]; $matched = true; break; }
		}
		if ( ! $matched ) { $out['type'] = 'referral'; $out['name'] = $host; }
	}

	// پارامترهای UTM و تبلیغات
	$m = strtolower( $out['utm_medium'] );
	if ( $m ) {
		if ( preg_match( '/^(cpc|ppc|paid|paidsearch|display|cpm|ads?|banner|retargeting)/', $m ) ) { $out['type'] = 'paid'; }
		elseif ( preg_match( '/mail|newsletter|sms/', $m ) ) { $out['type'] = 'email'; }
		elseif ( preg_match( '/social|^sm$/', $m ) ) { $out['type'] = 'social'; }
		elseif ( 'organic' === $m ) { $out['type'] = 'organic'; }
		elseif ( 'referral' === $m ) { $out['type'] = 'referral'; }
	}
	if ( $out['utm_source'] && ( ! $matched || 'paid' === $out['type'] ) ) {
		$out['name'] = $out['utm_source'];
		if ( ! $m && ! $host ) { $out['type'] = 'referral'; }
	}
	if ( ! empty( $q['gclid'] ) || ! empty( $q['gbraid'] ) || ! empty( $q['wbraid'] ) ) {
		$out['type'] = 'paid';
		$out['name'] = 'Google Ads';
	}
	return $out;
}

function gv_va_is_bot( $ua ) {
	if ( '' === $ua ) { return true; }
	return (bool) preg_match( '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|bingpreview|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|curl|wget|python|java\/|httpclient|preview|scrapy|axios|go-http/i', $ua );
}

function gv_va_parse_ua( $ua ) {
	$device = 'desktop';
	if ( preg_match( '/tablet|ipad/i', $ua ) ) { $device = 'tablet'; }
	elseif ( preg_match( '/mobile|android|iphone|ipod/i', $ua ) ) { $device = 'mobile'; }

	if ( preg_match( '/Edg(e|A|iOS)?\//', $ua ) ) { $browser = 'Edge'; }
	elseif ( preg_match( '/OPR\/|Opera/', $ua ) ) { $browser = 'Opera'; }
	elseif ( preg_match( '/SamsungBrowser/', $ua ) ) { $browser = 'Samsung Internet'; }
	elseif ( preg_match( '/Firefox|FxiOS/', $ua ) ) { $browser = 'Firefox'; }
	elseif ( preg_match( '/Chrome|CriOS/', $ua ) ) { $browser = 'Chrome'; }
	elseif ( preg_match( '/Safari/', $ua ) ) { $browser = 'Safari'; }
	elseif ( preg_match( '/MSIE|Trident/', $ua ) ) { $browser = 'IE'; }
	else { $browser = 'سایر'; }

	if ( preg_match( '/Windows/', $ua ) ) { $os = 'Windows'; }
	elseif ( preg_match( '/Android/', $ua ) ) { $os = 'Android'; }
	elseif ( preg_match( '/iPhone|iPad|iPod/', $ua ) ) { $os = 'iOS'; }
	elseif ( preg_match( '/Mac OS X|Macintosh/', $ua ) ) { $os = 'macOS'; }
	elseif ( preg_match( '/Linux|X11/', $ua ) ) { $os = 'Linux'; }
	else { $os = 'سایر'; }

	return array( $device, $browser, $os );
}

function gv_va_client_ip() {
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) { return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ); }
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/* ==========================================================================
   ۳) اسکریپت ردیابی سمت کاربر
   ========================================================================== */

add_action( 'wp_footer', 'gv_va_output_tracking_script', 99 );
function gv_va_output_tracking_script() {
	if ( is_admin() || is_customize_preview() || is_feed() ) { return; }
	$s = gv_va_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }
	if ( ! empty( $s['ignore_admins'] ) && current_user_can( 'manage_options' ) ) { return; }

	$title = wp_strip_all_tags( wp_get_document_title() );
	$cfg   = array(
		'endpoint' => esc_url_raw( rest_url( 'gv-va/v1/track' ) ),
		'clicks'   => (bool) $s['track_clicks'],
		'time'     => (bool) $s['track_time'],
		'scroll'   => (bool) $s['track_scroll'],
		'title'    => $title,
		'timeout'  => 1800,
	);
	?>
	<script>
	(function(){
		var C = <?php echo wp_json_encode( $cfg ); ?>;
		function st(k, v){ try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch(e){ return null; } }
		function rnd(){ return Date.now().toString(36) + Math.random().toString(36).slice(2, 10); }

		var vid = st('gv_vid'); if (!vid) { vid = 'v_' + rnd(); st('gv_vid', vid); }
		var now = Date.now(), sid = st('gv_sid'), last = parseInt(st('gv_last') || '0', 10);
		if (!sid || now - last > C.timeout * 1000) { sid = 's_' + rnd(); st('gv_sid', sid); }
		st('gv_last', now);
		var pv = 'p_' + rnd();

		function send(sub, data){
			data = data || {};
			data.sub = sub; data.sid = sid; data.vid = vid; data.pv = pv;
			data.url = location.href;
			var body = new URLSearchParams(data).toString();
			st('gv_last', Date.now());
			try {
				if (navigator.sendBeacon) {
					if (navigator.sendBeacon(C.endpoint, new Blob([body], { type: 'application/x-www-form-urlencoded' }))) return;
				}
				fetch(C.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, keepalive: true }).catch(function(){});
			} catch(e){}
		}

		send('pageview', {
			ref: document.referrer || '',
			title: C.title,
			lang: (navigator.language || '').slice(0, 10),
			screen: screen.width + 'x' + screen.height
		});

		if (C.clicks) {
			document.addEventListener('click', function(e){
				var el = e.target.closest ? e.target.closest('a, button, input[type=submit]') : null;
				if (!el) return;
				var txt = (el.innerText || el.value || el.getAttribute('aria-label') || el.title || '').replace(/\s+/g, ' ').trim().slice(0, 100);
				send('click', { text: txt, href: el.href || '' });
			}, { passive: true, capture: true });
		}

		var maxScroll = 0;
		if (C.scroll) {
			window.addEventListener('scroll', function(){
				var h = document.documentElement, b = document.body;
				var total = Math.max(h.scrollHeight, b ? b.scrollHeight : 0) - h.clientHeight;
				var pct = total > 0 ? Math.round((window.pageYOffset || h.scrollTop) / total * 100) : 100;
				if (pct > maxScroll) maxScroll = Math.min(100, pct);
			}, { passive: true });
		}

		if (C.time || C.scroll) {
			var visible = document.visibilityState !== 'hidden', t0 = Date.now(), total = 0;
			function flush(){
				if (visible) { total += Date.now() - t0; }
				send('timespent', { seconds: Math.round(total / 1000), scroll: maxScroll });
			}
			document.addEventListener('visibilitychange', function(){
				if (document.visibilityState === 'hidden') { flush(); visible = false; }
				else { visible = true; t0 = Date.now(); }
			});
			window.addEventListener('pagehide', function(){ if (visible) { flush(); visible = false; } });
		}
	})();
	</script>
	<?php
}

/* ==========================================================================
   ۴) دریافت داده در سمت سرور (REST — سازگار با کش، بدون nonce)
   ========================================================================== */

add_action( 'rest_api_init', function () {
	register_rest_route( 'gv-va/v1', '/track', array(
		'methods'             => 'POST',
		'callback'            => 'gv_va_handle_track',
		'permission_callback' => '__return_true',
	) );
} );

function gv_va_handle_track( WP_REST_Request $req ) {
	$ok = rest_ensure_response( array( 'ok' => 1 ) );
	$s  = gv_va_get_settings();
	if ( empty( $s['enabled'] ) ) { return $ok; }

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 300 ) : '';
	if ( ! empty( $s['ignore_bots'] ) && gv_va_is_bot( $ua ) ) { return $ok; }

	$ip = gv_va_client_ip();
	if ( ! empty( $s['exclude_ips'] ) ) {
		$list = preg_split( '/[\s,]+/', $s['exclude_ips'], -1, PREG_SPLIT_NO_EMPTY );
		if ( in_array( $ip, $list, true ) ) { return $ok; }
	}
	$ip_hash = hash( 'sha256', $ip . wp_salt() );

	// محدودیت ساده نرخ (جلوگیری از اسپم)
	$rl_key = 'gv_va_rl_' . substr( $ip_hash, 0, 16 );
	$hits   = (int) get_transient( $rl_key );
	if ( $hits > 240 ) { return $ok; }
	set_transient( $rl_key, $hits + 1, 60 );

	$p   = $req->get_params();
	$get = function ( $k, $max = 255 ) use ( $p ) {
		return ( isset( $p[ $k ] ) && is_scalar( $p[ $k ] ) ) ? mb_substr( (string) $p[ $k ], 0, $max ) : '';
	};

	$sid = substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', $get( 'sid', 40 ) ), 0, 40 );
	$vid = substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', $get( 'vid', 40 ) ), 0, 40 );
	$pv  = substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', $get( 'pv', 40 ) ), 0, 40 );
	$url = esc_url_raw( $get( 'url', 2000 ) );
	$sub = sanitize_key( $get( 'sub', 20 ) );

	if ( ! $sid || ! $url || gv_va_host( $url ) !== gv_va_host( home_url() ) ) { return $ok; }

	global $wpdb;
	$ts   = gv_va_t( 'sessions' );
	$tv   = gv_va_t( 'visits' );
	$te   = gv_va_t( 'events' );
	$now  = current_time( 'mysql' );
	$path = gv_va_path( $url );

	if ( 'pageview' === $sub ) {
		$ref   = esc_url_raw( $get( 'ref', 2000 ) );
		$title = sanitize_text_field( $get( 'title', 255 ) );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$ts} WHERE session_id = %s", $sid ) );

		if ( ! $exists ) {
			$cls = gv_va_classify_source( $ref, $url );
			list( $device, $browser, $os ) = gv_va_parse_ua( $ua );
			$is_new  = ( $vid && ! $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$ts} WHERE visitor_id = %s LIMIT 1", $vid ) ) ) ? 1 : 0;
			$country = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? strtoupper( substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ), 0, 2 ) ) : '';
			if ( in_array( $country, array( 'XX', 'T1' ), true ) ) { $country = ''; }

			$inserted = $wpdb->insert( $ts, array(
				'session_id'    => $sid,
				'visitor_id'    => $vid ?: $sid,
				'started_at'    => $now,
				'last_seen'     => $now,
				'duration'      => 0,
				'pages'         => 1,
				'is_new'        => $is_new,
				'landing_url'   => $url,
				'landing_path'  => $path,
				'exit_path'     => $path,
				'referrer'      => $ref,
				'source_type'   => $cls['type'],
				'source_name'   => $cls['name'],
				'source_domain' => $cls['domain'],
				'utm_source'    => $cls['utm_source'],
				'utm_medium'    => $cls['utm_medium'],
				'utm_campaign'  => $cls['utm_campaign'],
				'device'        => $device,
				'browser'       => $browser,
				'os'            => $os,
				'country'       => $country,
				'lang'          => sanitize_text_field( $get( 'lang', 10 ) ),
				'screen'        => sanitize_text_field( $get( 'screen', 15 ) ),
				'ip_hash'       => $ip_hash,
			) );
			$prev = '';
			if ( false === $inserted ) { $exists = true; } // رقابت هم‌زمان دو تب
		}
		if ( $exists ) {
			$prev = $wpdb->get_var( $wpdb->prepare( "SELECT page_path FROM {$tv} WHERE session_id = %s ORDER BY id DESC LIMIT 1", $sid ) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$ts} SET pages = pages + 1, last_seen = %s, exit_path = %s, duration = TIMESTAMPDIFF(SECOND, started_at, %s) WHERE session_id = %s",
				$now, $path, $now, $sid
			) );
		}

		list( $device ) = gv_va_parse_ua( $ua );
		$wpdb->insert( $tv, array(
			'session_id'  => $sid,
			'pv_key'      => $pv,
			'page_url'    => $url,
			'page_path'   => $path,
			'page_title'  => $title,
			'prev_path'   => ( ! empty( $s['track_path'] ) && $prev ) ? $prev : '',
			'referrer'    => $ref,
			'entry_time'  => $now,
			'last_seen'   => $now,
			'time_spent'  => 0,
			'scroll_depth' => 0,
			'device'      => $device,
			'ip_hash'     => $ip_hash,
		) );
		return $ok;
	}

	// سایر رویدادها فقط برای نشست موجود
	if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$ts} WHERE session_id = %s", $sid ) ) ) { return $ok; }

	if ( 'click' === $sub && ! empty( $s['track_clicks'] ) ) {
		$href = esc_url_raw( $get( 'href', 2000 ) );
		$type = 'click';
		if ( preg_match( '#^tel:#i', $get( 'href', 20 ) ) ) { $type = 'tel'; }
		elseif ( preg_match( '#^mailto:#i', $get( 'href', 20 ) ) ) { $type = 'mailto'; }
		elseif ( $href ) {
			$h = gv_va_host( $href );
			if ( preg_match( '/\.(pdf|zip|rar|7z|docx?|xlsx?|pptx?|mp3|mp4|apk|exe|csv)$/i', (string) parse_url( $href, PHP_URL_PATH ) ) ) { $type = 'download'; }
			elseif ( $h && $h !== gv_va_host( home_url() ) ) { $type = 'outbound'; }
		}
		$wpdb->insert( $te, array(
			'session_id'  => $sid,
			'event_type'  => $type,
			'page_url'    => $url,
			'page_path'   => $path,
			'target_text' => sanitize_text_field( $get( 'text', 100 ) ),
			'target_href' => $href ?: $get( 'href', 200 ),
			'created_at'  => $now,
		) );
	} elseif ( 'timespent' === $sub ) {
		$seconds = max( 0, min( 7200, intval( $get( 'seconds', 6 ) ) ) );
		$scroll  = max( 0, min( 100, intval( $get( 'scroll', 4 ) ) ) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$tv} SET time_spent = %d, scroll_depth = %d, last_seen = %s WHERE pv_key = %s AND session_id = %s",
			$seconds, $scroll, $now, $pv, $sid
		) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$ts} SET last_seen = %s, duration = TIMESTAMPDIFF(SECOND, started_at, %s) WHERE session_id = %s",
			$now, $now, $sid
		) );
	}
	return $ok;
}

/* ==========================================================================
   ۵) پاکسازی خودکار و گزارش زمان‌بندی‌شده
   ========================================================================== */

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'gv_va_daily_cleanup' ) ) { wp_schedule_event( time() + 600, 'daily', 'gv_va_daily_cleanup' ); }
} );

add_action( 'gv_va_daily_cleanup', 'gv_va_run_cleanup' );
function gv_va_run_cleanup() {
	$s = gv_va_get_settings();
	if ( ! empty( $s['retention_days'] ) ) {
		global $wpdb;
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( (int) $s['retention_days'] * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . gv_va_t( 'sessions' ) . " WHERE started_at < %s", $cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . gv_va_t( 'visits' ) . " WHERE entry_time < %s", $cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . gv_va_t( 'events' ) . " WHERE created_at < %s", $cutoff ) );
	}
	// ارسال گزارش ایمیلی
	$freq = $s['report_freq'];
	$days = array( 'daily' => 1, 'weekly' => 7, 'monthly' => 30 );
	if ( isset( $days[ $freq ] ) ) {
		$last = (int) get_option( 'gv_va_last_report', 0 );
		if ( time() - $last >= $days[ $freq ] * DAY_IN_SECONDS - 3600 ) {
			gv_va_send_report( $days[ $freq ] );
			update_option( 'gv_va_last_report', time(), false );
		}
	}
}

function gv_va_send_report( $days ) {
	$s  = gv_va_get_settings();
	$to = is_email( $s['report_email'] ) ? $s['report_email'] : get_option( 'admin_email' );
	$tz = current_time( 'timestamp' );
	$t  = gmdate( 'Y-m-d', $tz - DAY_IN_SECONDS );
	$f  = gmdate( 'Y-m-d', $tz - $days * DAY_IN_SECONDS );
	$html = gv_va_report_html( $f, $t );
	return wp_mail( $to, 'گزارش آمار بازدید — ' . get_bloginfo( 'name' ), $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

function gv_va_report_html( $f, $t ) {
	$k  = gv_va_kpis( $f, $t );
	$src = array_slice( gv_va_q_sources( $f, $t ), 0, 8 );
	$pg  = array_slice( gv_va_q_pages( $f, $t ), 0, 8 );
	$td  = 'style="padding:8px 12px;border-bottom:1px solid #eee;font-size:13px"';
	$h   = '<div dir="rtl" style="font-family:Tahoma,sans-serif;max-width:640px;margin:auto;color:#1e293b">';
	$h  .= '<h2 style="background:#1e3a8a;color:#fff;padding:18px;border-radius:12px;margin:0 0 16px">گزارش آمار بازدید<br><small style="font-weight:400">' . esc_html( gv_va_jdate( $f, true ) . ' تا ' . gv_va_jdate( $t, true ) ) . '</small></h2>';
	$h  .= '<table width="100%" cellspacing="0"><tr>';
	foreach ( array( 'بازدید صفحه' => gv_va_n( $k['pageviews'] ), 'نشست' => gv_va_n( $k['sessions'] ), 'بازدیدکننده' => gv_va_n( $k['visitors'] ), 'نرخ پرش' => gv_va_pct( $k['bounces'], $k['sessions'] ) ) as $l => $v ) {
		$h .= '<td align="center" style="padding:12px;background:#eff6ff;border:4px solid #fff;border-radius:10px"><b style="font-size:20px;color:#2563eb">' . esc_html( $v ) . '</b><br><small>' . esc_html( $l ) . '</small></td>';
	}
	$h .= '</tr></table><h3>منابع ورودی</h3><table width="100%" cellspacing="0">';
	foreach ( $src as $r ) {
		$h .= '<tr><td ' . $td . '>' . esc_html( $r->source_name ) . '</td><td ' . $td . '>' . esc_html( gv_va_types()[ $r->source_type ][0] ?? $r->source_type ) . '</td><td ' . $td . '>' . esc_html( gv_va_n( $r->sessions ) ) . '</td></tr>';
	}
	$h .= '</table><h3>پربازدیدترین صفحات</h3><table width="100%" cellspacing="0">';
	foreach ( $pg as $r ) {
		$h .= '<tr><td ' . $td . '>' . esc_html( $r->title ?: $r->page_path ) . '</td><td ' . $td . '>' . esc_html( gv_va_n( $r->views ) ) . '</td></tr>';
	}
	$h .= '</table></div>';
	return $h;
}

/* ==========================================================================
   ۶) کوئری‌های گزارش
   ========================================================================== */

function gv_va_range() {
	static $R = null;
	if ( null !== $R ) { return $R; }
	$today = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
	$key   = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '7';
	$day   = function ( $d, $n ) { return gmdate( 'Y-m-d', strtotime( $d . ' UTC' ) + $n * DAY_IN_SECONDS ); };

	if ( 'today' === $key ) { $f = $t = $today; }
	elseif ( 'yesterday' === $key ) { $f = $t = $day( $today, -1 ); }
	elseif ( 'custom' === $key && isset( $_GET['from'], $_GET['to'] )
		&& preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ) ) {
		$f = sanitize_text_field( $_GET['from'] );
		$t = sanitize_text_field( $_GET['to'] );
		if ( $f > $t ) { list( $f, $t ) = array( $t, $f ); }
	} else {
		$n = in_array( (int) $key, array( 7, 30, 90, 365 ), true ) ? (int) $key : 7;
		$key = (string) $n;
		$t = $today;
		$f = $day( $today, -( $n - 1 ) );
	}
	$days = (int) ( ( strtotime( $t . ' UTC' ) - strtotime( $f . ' UTC' ) ) / DAY_IN_SECONDS ) + 1;
	$days = max( 1, min( 732, $days ) );
	$pto  = $day( $f, -1 );
	$pfrom = $day( $pto, -( $days - 1 ) );
	$R = array( 'key' => $key, 'from' => $f, 'to' => $t, 'pfrom' => $pfrom, 'pto' => $pto, 'days' => $days );
	return $R;
}

function gv_va_url( $tab, $extra = array() ) {
	$R = gv_va_range();
	$a = array( 'page' => 'gv-visitor-analytics', 'tab' => $tab, 'range' => $R['key'] );
	if ( 'custom' === $R['key'] ) { $a['from'] = $R['from']; $a['to'] = $R['to']; }
	return esc_url( add_query_arg( array_merge( $a, $extra ), admin_url( 'admin.php' ) ) );
}

function gv_va_kpis( $f, $t ) {
	global $wpdb;
	$ts = gv_va_t( 'sessions' ); $tv = gv_va_t( 'visits' ); $te = gv_va_t( 'events' );
	$a = $f . ' 00:00:00'; $b = $t . ' 23:59:59';
	$r = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) s, COUNT(DISTINCT visitor_id) v, COALESCE(SUM(is_new),0) n, COALESCE(SUM(pages=1),0) b, AVG(NULLIF(duration,0)) d
		 FROM {$ts} WHERE started_at BETWEEN %s AND %s", $a, $b ) );
	$pv = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tv} WHERE entry_time BETWEEN %s AND %s", $a, $b ) );
	$cl = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$te} WHERE event_type <> 'pageview' AND created_at BETWEEN %s AND %s", $a, $b ) );
	return array(
		'pageviews' => $pv, 'sessions' => (int) $r->s, 'visitors' => (int) $r->v, 'new' => (int) $r->n,
		'bounces' => (int) $r->b, 'bounce' => $r->s ? $r->b / $r->s * 100 : 0, 'avg' => (float) $r->d,
		'pps' => $r->s ? $pv / $r->s : 0, 'clicks' => $cl,
	);
}

function gv_va_daily( $R ) {
	global $wpdb;
	$a = $R['from'] . ' 00:00:00'; $b = $R['to'] . ' 23:59:59';
	$s = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(started_at) d, COUNT(*) c, COUNT(DISTINCT visitor_id) v FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s GROUP BY d", $a, $b ), OBJECT_K );
	$v = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(entry_time) d, COUNT(*) c FROM " . gv_va_t( 'visits' ) . " WHERE entry_time BETWEEN %s AND %s GROUP BY d", $a, $b ), OBJECT_K );
	$labels = $pv = $se = $vi = array();
	$ts = strtotime( $R['from'] . ' UTC' );
	for ( $i = 0; $i < $R['days']; $i++ ) {
		$d = gmdate( 'Y-m-d', $ts + $i * DAY_IN_SECONDS );
		$labels[] = gv_va_jdate( $d );
		$pv[] = isset( $v[ $d ] ) ? (int) $v[ $d ]->c : 0;
		$se[] = isset( $s[ $d ] ) ? (int) $s[ $d ]->c : 0;
		$vi[] = isset( $s[ $d ] ) ? (int) $s[ $d ]->v : 0;
	}
	return array( $labels, $pv, $se, $vi );
}

function gv_va_q_types( $f, $t ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT source_type, COUNT(*) sessions, COUNT(DISTINCT visitor_id) visitors, SUM(pages=1) bounces, AVG(NULLIF(duration,0)) avg_d, SUM(pages) pv
		 FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s GROUP BY source_type ORDER BY sessions DESC", $f . ' 00:00:00', $t . ' 23:59:59' ) );
}

function gv_va_q_sources( $f, $t, $type = '' ) {
	global $wpdb;
	$where = 'started_at BETWEEN %s AND %s';
	$args  = array( $f . ' 00:00:00', $t . ' 23:59:59' );
	if ( $type ) { $where .= ' AND source_type = %s'; $args[] = $type; }
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT source_type, source_name, MAX(source_domain) domain, COUNT(*) sessions, COUNT(DISTINCT visitor_id) visitors, COALESCE(SUM(is_new),0) new_v, SUM(pages=1) bounces, AVG(NULLIF(duration,0)) avg_d, SUM(pages) pv
		 FROM " . gv_va_t( 'sessions' ) . " WHERE {$where} GROUP BY source_type, source_name ORDER BY sessions DESC LIMIT 100", $args ) );
}

function gv_va_q_pages( $f, $t ) {
	global $wpdb;
	$a = $f . ' 00:00:00'; $b = $t . ' 23:59:59';
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT page_path, MAX(page_title) title, COUNT(*) views, COUNT(DISTINCT session_id) uniq, AVG(NULLIF(time_spent,0)) avg_t, AVG(NULLIF(scroll_depth,0)) avg_s
		 FROM " . gv_va_t( 'visits' ) . " WHERE entry_time BETWEEN %s AND %s AND page_path IS NOT NULL GROUP BY page_path ORDER BY views DESC LIMIT 200", $a, $b ) );
	$ent = $wpdb->get_results( $wpdb->prepare( "SELECT landing_path p, COUNT(*) c, SUM(pages=1) b FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s GROUP BY landing_path", $a, $b ), OBJECT_K );
	$ext = $wpdb->get_results( $wpdb->prepare( "SELECT exit_path p, COUNT(*) c FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s GROUP BY exit_path", $a, $b ), OBJECT_K );
	foreach ( $rows as $r ) {
		$r->entrances = isset( $ent[ $r->page_path ] ) ? (int) $ent[ $r->page_path ]->c : 0;
		$r->bounces   = isset( $ent[ $r->page_path ] ) ? (int) $ent[ $r->page_path ]->b : 0;
		$r->exits     = isset( $ext[ $r->page_path ] ) ? (int) $ext[ $r->page_path ]->c : 0;
	}
	return $rows;
}

function gv_va_q_clicks( $f, $t, $type = '' ) {
	global $wpdb;
	$where = "event_type <> 'pageview' AND created_at BETWEEN %s AND %s AND (target_text <> '' OR target_href <> '')";
	$args  = array( $f . ' 00:00:00', $t . ' 23:59:59' );
	if ( $type ) { $where .= ' AND event_type = %s'; $args[] = $type; }
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT event_type, target_text, target_href, COUNT(*) clicks, COUNT(DISTINCT session_id) sessions, MAX(page_path) page_path
		 FROM " . gv_va_t( 'events' ) . " WHERE {$where} GROUP BY event_type, target_text, target_href ORDER BY clicks DESC LIMIT 100", $args ) );
}

function gv_va_q_flows( $f, $t ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT prev_path, page_path, COUNT(*) c FROM " . gv_va_t( 'visits' ) . "
		 WHERE entry_time BETWEEN %s AND %s AND prev_path <> '' AND prev_path <> page_path GROUP BY prev_path, page_path ORDER BY c DESC LIMIT 50",
		$f . ' 00:00:00', $t . ' 23:59:59' ) );
}

function gv_va_group( $col, $f, $t, $limit = 10 ) {
	global $wpdb;
	if ( ! in_array( $col, array( 'device', 'browser', 'os', 'country', 'lang', 'screen' ), true ) ) { return array(); }
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT {$col} k, COUNT(*) c FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s AND {$col} IS NOT NULL AND {$col} <> '' GROUP BY k ORDER BY c DESC LIMIT %d",
		$f . ' 00:00:00', $t . ' 23:59:59', $limit ) );
}

/* ==========================================================================
   ۷) کامپوننت‌های نمایشی
   ========================================================================== */

function gv_va_delta( $cur, $prev, $invert = false ) {
	if ( ! $prev ) { return '<span class="gvva-delta gvva-flat">—</span>'; }
	$p = ( $cur - $prev ) / $prev * 100;
	if ( abs( $p ) < 0.05 ) { return '<span class="gvva-delta gvva-flat">۰٪</span>'; }
	$good = $invert ? $p < 0 : $p > 0;
	return '<span class="gvva-delta ' . ( $good ? 'gvva-up' : 'gvva-down' ) . '">' . ( $p > 0 ? '▲ ' : '▼ ' ) . gv_va_n( abs( $p ), 1 ) . '٪</span>';
}

function gv_va_svg_chart( $labels, $series ) {
	$w = 900; $h = 270; $pl = 46; $pr = 14; $pt = 16; $pb = 34;
	$max = 1;
	foreach ( $series as $s ) { $max = max( $max, max( $s['data'] ?: array( 0 ) ) ); }
	$step = pow( 10, floor( log10( $max ) ) );
	$max  = max( 4, ceil( $max / $step ) * $step );
	$n  = count( $labels );
	$iw = $w - $pl - $pr; $ih = $h - $pt - $pb;
	$x  = function ( $i ) use ( $n, $pl, $iw ) { return $pl + ( $n > 1 ? $iw * $i / ( $n - 1 ) : $iw / 2 ); };
	$y  = function ( $v ) use ( $pt, $ih, $max ) { return $pt + $ih - $ih * $v / $max; };

	$o = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="gvva-chart" xmlns="http://www.w3.org/2000/svg" direction="ltr">';
	for ( $g = 0; $g <= 4; $g++ ) {
		$v = $max * $g / 4;
		$o .= '<line x1="' . $pl . '" x2="' . ( $w - $pr ) . '" y1="' . $y( $v ) . '" y2="' . $y( $v ) . '" stroke="#e8ecf4" stroke-dasharray="3 4"/>';
		$o .= '<text x="' . ( $pl - 8 ) . '" y="' . ( $y( $v ) + 4 ) . '" text-anchor="end" font-size="11" fill="#94a3b8">' . esc_html( gv_va_n( $v ) ) . '</text>';
	}
	$every = max( 1, (int) ceil( $n / 8 ) );
	for ( $i = 0; $i < $n; $i += $every ) {
		$o .= '<text x="' . $x( $i ) . '" y="' . ( $h - 10 ) . '" text-anchor="middle" font-size="11" fill="#94a3b8">' . esc_html( $labels[ $i ] ) . '</text>';
	}
	foreach ( $series as $si => $s ) {
		$pts = array();
		foreach ( $s['data'] as $i => $v ) { $pts[] = round( $x( $i ), 1 ) . ',' . round( $y( $v ), 1 ); }
		if ( ! $pts ) { continue; }
		if ( 0 === $si && $n > 1 ) {
			$o .= '<polygon points="' . $x( 0 ) . ',' . ( $pt + $ih ) . ' ' . implode( ' ', $pts ) . ' ' . $x( $n - 1 ) . ',' . ( $pt + $ih ) . '" fill="' . esc_attr( $s['color'] ) . '" opacity=".08"/>';
		}
		$o .= '<polyline points="' . implode( ' ', $pts ) . '" fill="none" stroke="' . esc_attr( $s['color'] ) . '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';
		if ( $n <= 45 ) {
			foreach ( $s['data'] as $i => $v ) {
				$o .= '<circle cx="' . round( $x( $i ), 1 ) . '" cy="' . round( $y( $v ), 1 ) . '" r="3.4" fill="#fff" stroke="' . esc_attr( $s['color'] ) . '" stroke-width="2"><title>' . esc_html( $s['name'] . ' — ' . $labels[ $i ] . ': ' . gv_va_n( $v ) ) . '</title></circle>';
			}
		}
	}
	return $o . '</svg>';
}

/* لیست میله‌ای: $rows = array( array(label_html, count) ) */
function gv_va_bars( $rows, $color = '#2563eb' ) {
	$tot = 0; $max = 1;
	foreach ( $rows as $r ) { $tot += $r[1]; $max = max( $max, $r[1] ); }
	if ( ! $rows ) { return '<p class="gvva-empty">داده‌ای یافت نشد.</p>'; }
	$o = '<div class="gvva-bars">';
	foreach ( $rows as $r ) {
		$o .= '<div class="gvva-bar-row"><div class="gvva-bar-label">' . $r[0] . '</div><div class="gvva-bar-track"><i style="width:' . round( $r[1] / $max * 100, 1 ) . '%;background:' . esc_attr( $color ) . '"></i></div><div class="gvva-bar-val">' . esc_html( gv_va_n( $r[1] ) ) . ' <small>' . esc_html( $tot ? gv_va_n( $r[1] / $tot * 100, 0 ) . '٪' : '' ) . '</small></div></div>';
	}
	return $o . '</div>';
}

function gv_va_table( $heads, $rows, $empty = 'داده‌ای یافت نشد.' ) {
	if ( ! $rows ) { return '<p class="gvva-empty">' . esc_html( $empty ) . '</p>'; }
	$o = '<div class="gvva-table-wrap"><table class="gvva-table"><thead><tr>';
	foreach ( $heads as $h ) { $o .= '<th>' . esc_html( $h ) . '</th>'; }
	$o .= '</tr></thead><tbody>';
	foreach ( $rows as $r ) { $o .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>'; }
	return $o . '</tbody></table></div>';
}

function gv_va_page_link( $path, $title = '' ) {
	$label = $title ?: $path;
	return '<a href="' . esc_url( home_url( $path ) ) . '" target="_blank" rel="noopener" title="' . esc_attr( $path ) . '">' . esc_html( mb_strimwidth( $label, 0, 70, '…' ) ) . '</a><div class="gvva-sub">' . esc_html( mb_strimwidth( $path, 0, 70, '…' ) ) . '</div>';
}

function gv_va_event_label( $t ) {
	$l = array( 'click' => 'کلیک', 'outbound' => 'لینک خروجی', 'download' => 'دانلود', 'tel' => 'تماس تلفنی', 'mailto' => 'ایمیل' );
	return isset( $l[ $t ] ) ? $l[ $t ] : $t;
}

/* ==========================================================================
   ۸) منو، تنظیمات، پاکسازی دستی، خروجی CSV، ارسال تست
   ========================================================================== */

add_action( 'admin_menu', 'gv_va_admin_menu' );
function gv_va_admin_menu() {
	add_submenu_page( 'groot-vision-hub', 'آمار بازدید | Groot Vision', '📈 آمار بازدید', 'manage_options', 'gv-visitor-analytics', 'gv_va_render_admin_page' );
}

add_action( 'admin_post_gv_va_save_settings', 'gv_va_save_settings' );
function gv_va_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_VA_NONCE );
	$freq = isset( $_POST['report_freq'] ) ? sanitize_key( $_POST['report_freq'] ) : 'off';
	$settings = array(
		'enabled'        => isset( $_POST['enabled'] ) ? 1 : 0,
		'track_clicks'   => isset( $_POST['track_clicks'] ) ? 1 : 0,
		'track_time'     => isset( $_POST['track_time'] ) ? 1 : 0,
		'track_scroll'   => isset( $_POST['track_scroll'] ) ? 1 : 0,
		'track_path'     => isset( $_POST['track_path'] ) ? 1 : 0,
		'ignore_admins'  => isset( $_POST['ignore_admins'] ) ? 1 : 0,
		'ignore_bots'    => isset( $_POST['ignore_bots'] ) ? 1 : 0,
		'exclude_ips'    => sanitize_textarea_field( wp_unslash( $_POST['exclude_ips'] ?? '' ) ),
		'retention_days' => max( 0, min( 1095, intval( $_POST['retention_days'] ?? 180 ) ) ),
		'report_freq'    => in_array( $freq, array( 'off', 'daily', 'weekly', 'monthly' ), true ) ? $freq : 'off',
		'report_email'   => sanitize_email( wp_unslash( $_POST['report_email'] ?? '' ) ),
	);
	update_option( GV_VA_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=gv-visitor-analytics&tab=settings&updated=1' ) );
	exit;
}

add_action( 'admin_post_gv_va_clear_data', 'gv_va_clear_data' );
function gv_va_clear_data() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_VA_NONCE );
	global $wpdb;
	foreach ( array( 'sessions', 'visits', 'events' ) as $t ) { $wpdb->query( 'TRUNCATE TABLE ' . gv_va_t( $t ) ); }
	wp_safe_redirect( admin_url( 'admin.php?page=gv-visitor-analytics&tab=settings&cleared=1' ) );
	exit;
}

add_action( 'admin_post_gv_va_send_test', 'gv_va_send_test' );
function gv_va_send_test() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_VA_NONCE );
	$sent = gv_va_send_report( 7 );
	wp_safe_redirect( admin_url( 'admin.php?page=gv-visitor-analytics&tab=reports&mail=' . ( $sent ? 'ok' : 'fail' ) ) );
	exit;
}

function gv_va_csv_safe( $v ) {
	$v = (string) $v;
	return ( '' !== $v && strpos( '=+-@', $v[0] ) !== false ) ? "'" . $v : $v;
}

add_action( 'admin_post_gv_va_export', 'gv_va_export' );
function gv_va_export() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( 'gv_va_export' );
	global $wpdb;
	$R  = gv_va_range();
	$f  = $R['from']; $t = $R['to'];
	$ds = isset( $_GET['dataset'] ) ? sanitize_key( $_GET['dataset'] ) : 'sessions';
	$types = gv_va_types();
	$head = array(); $rows = array();

	if ( 'sources' === $ds ) {
		$head = array( 'نوع منبع', 'منبع', 'دامنه', 'نشست', 'بازدیدکننده', 'کاربر جدید', 'نرخ پرش %', 'میانگین مدت (ثانیه)', 'بازدید صفحه' );
		foreach ( gv_va_q_sources( $f, $t ) as $r ) {
			$rows[] = array( $types[ $r->source_type ][0] ?? $r->source_type, $r->source_name, $r->domain, $r->sessions, $r->visitors, $r->new_v, $r->sessions ? round( $r->bounces / $r->sessions * 100, 1 ) : 0, round( $r->avg_d ), $r->pv );
		}
	} elseif ( 'pages' === $ds ) {
		$head = array( 'مسیر', 'عنوان', 'بازدید', 'بازدید یکتا', 'میانگین زمان (ثانیه)', 'میانگین اسکرول %', 'ورودی', 'خروجی', 'پرش' );
		foreach ( gv_va_q_pages( $f, $t ) as $r ) {
			$rows[] = array( $r->page_path, $r->title, $r->views, $r->uniq, round( $r->avg_t ), round( $r->avg_s ), $r->entrances, $r->exits, $r->bounces );
		}
	} elseif ( 'clicks' === $ds ) {
		$head = array( 'نوع', 'متن', 'لینک', 'صفحه', 'تعداد', 'نشست‌ها' );
		foreach ( gv_va_q_clicks( $f, $t ) as $r ) {
			$rows[] = array( gv_va_event_label( $r->event_type ), $r->target_text, $r->target_href, $r->page_path, $r->clicks, $r->sessions );
		}
	} elseif ( 'flows' === $ds ) {
		$head = array( 'از صفحه', 'به صفحه', 'تعداد' );
		foreach ( gv_va_q_flows( $f, $t ) as $r ) { $rows[] = array( $r->prev_path, $r->page_path, $r->c ); }
	} else {
		$ds   = 'sessions';
		$head = array( 'شناسه نشست', 'شروع', 'مدت (ثانیه)', 'تعداد صفحه', 'کاربر جدید', 'صفحه فرود', 'صفحه خروج', 'نوع منبع', 'منبع', 'ارجاع‌دهنده', 'UTM منبع', 'UTM مدیوم', 'UTM کمپین', 'دستگاه', 'مرورگر', 'سیستم‌عامل', 'کشور', 'زبان' );
		$res  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s ORDER BY started_at DESC LIMIT 50000", $f . ' 00:00:00', $t . ' 23:59:59' ) );
		foreach ( $res as $r ) {
			$rows[] = array( $r->session_id, $r->started_at, $r->duration, $r->pages, $r->is_new ? 'بله' : 'خیر', $r->landing_path, $r->exit_path, $types[ $r->source_type ][0] ?? $r->source_type, $r->source_name, $r->referrer, $r->utm_source, $r->utm_medium, $r->utm_campaign, $r->device, $r->browser, $r->os, $r->country, $r->lang );
		}
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="gv-' . $ds . '-' . $f . '_' . $t . '.csv"' );
	echo "\xEF\xBB\xBF";
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, $head );
	foreach ( $rows as $r ) { fputcsv( $out, array_map( 'gv_va_csv_safe', $r ) ); }
	fclose( $out );
	exit;
}

/* ==========================================================================
   ۹) صفحه مدیریت
   ========================================================================== */

function gv_va_css() { ?>
	<style>
		.gvva{--p:#2563eb;--ink:#0f172a;--mut:#64748b;--line:#e8ecf4;--bg:#f4f6fb;font-family:Vazirmatn,Tahoma,sans-serif;max-width:1240px;color:var(--ink);}
		.gvva *{box-sizing:border-box;}
		.gvva a{text-decoration:none;color:var(--p);}
		.gvva-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:linear-gradient(125deg,#0f172a 0%,#1e3a8a 55%,#2563eb 100%);color:#fff;padding:26px 30px;border-radius:20px;margin:18px 0 16px;box-shadow:0 12px 30px -12px rgba(30,58,138,.55);position:relative;overflow:hidden;}
		.gvva-hero:before{content:"";position:absolute;left:-60px;top:-80px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.07);}
		.gvva-hero h1{margin:0 0 4px;font-size:21px;color:#fff;font-weight:800;}
		.gvva-hero p{margin:0;opacity:.8;font-size:12.5px;}
		.gvva-range{display:flex;gap:8px;align-items:center;flex-wrap:wrap;position:relative;}
		.gvva-range select,.gvva-range input[type=date]{border:0;border-radius:10px;padding:8px 12px;font-size:12.5px;background:rgba(255,255,255,.95);min-height:36px;}
		.gvva-range button{background:#fff;color:#1e3a8a;border:0;border-radius:10px;padding:8px 18px;font-weight:700;cursor:pointer;min-height:36px;}
		.gvva-tabs{display:flex;gap:6px;margin-bottom:18px;overflow-x:auto;padding:5px;background:#fff;border:1px solid var(--line);border-radius:14px;}
		.gvva-tab{white-space:nowrap;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;color:var(--mut)!important;transition:.15s;}
		.gvva-tab:hover{background:var(--bg);color:var(--ink)!important;}
		.gvva-tab.is-active{background:var(--p);color:#fff!important;box-shadow:0 6px 14px -6px rgba(37,99,235,.7);}
		.gvva-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px;}
		.gvva-kpi{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px 18px;position:relative;overflow:hidden;}
		.gvva-kpi:before{content:"";position:absolute;right:0;top:0;bottom:0;width:4px;background:var(--c);}
		.gvva-kpi span{display:block;font-size:12px;color:var(--mut);margin-bottom:6px;}
		.gvva-kpi b{font-size:25px;font-weight:800;color:var(--ink);}
		.gvva-delta{font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;margin-right:6px;vertical-align:middle;}
		.gvva-up{background:#dcfce7;color:#15803d;}.gvva-down{background:#fee2e2;color:#b91c1c;}.gvva-flat{background:#f1f5f9;color:#64748b;}
		.gvva-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
		.gvva-grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;}
		@media(max-width:1000px){.gvva-kpis{grid-template-columns:repeat(2,1fr);}.gvva-grid2,.gvva-grid3{grid-template-columns:1fr;}}
		.gvva-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px 22px;margin-bottom:16px;}
		.gvva-grid2 .gvva-card,.gvva-grid3 .gvva-card{margin-bottom:0;}
		.gvva-card h2{margin:0 0 14px;font-size:14.5px;font-weight:800;display:flex;align-items:center;justify-content:space-between;gap:8px;}
		.gvva-card h2 small{font-weight:500;color:var(--mut);font-size:11.5px;}
		.gvva-legend{display:flex;gap:16px;font-size:12px;color:var(--mut);margin-bottom:6px;flex-wrap:wrap;}
		.gvva-legend i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-left:6px;vertical-align:middle;}
		.gvva-chart{width:100%;height:auto;direction:ltr;}
		.gvva-table-wrap{overflow-x:auto;}
		.gvva-table{width:100%;border-collapse:collapse;}
		.gvva-table th{background:#f8fafc;color:var(--mut);padding:10px 12px;text-align:right;font-size:11.5px;font-weight:700;white-space:nowrap;}
		.gvva-table td{padding:10px 12px;border-top:1px solid #f1f5f9;font-size:12.5px;vertical-align:middle;}
		.gvva-table tbody tr:hover{background:#fafcff;}
		.gvva-sub{font-size:11px;color:#94a3b8;direction:ltr;text-align:right;}
		.gvva-badge{display:inline-block;background:color-mix(in srgb,var(--c) 12%,#fff);color:var(--c);border:1px solid color-mix(in srgb,var(--c) 30%,#fff);padding:2px 10px;border-radius:20px;font-size:11.5px;font-weight:700;white-space:nowrap;}
		.gvva-empty{color:#94a3b8;text-align:center;padding:22px 0;margin:0;font-size:12.5px;}
		.gvva-bars{display:flex;flex-direction:column;gap:10px;}
		.gvva-bar-row{display:grid;grid-template-columns:minmax(90px,34%) 1fr 76px;gap:10px;align-items:center;font-size:12.5px;}
		.gvva-bar-track{height:9px;background:#f1f5f9;border-radius:9px;overflow:hidden;}
		.gvva-bar-track i{display:block;height:100%;border-radius:9px;}
		.gvva-bar-val{text-align:left;font-weight:700;direction:rtl;}.gvva-bar-val small{color:var(--mut);font-weight:500;}
		.gvva-stack{display:flex;height:16px;border-radius:10px;overflow:hidden;background:#f1f5f9;margin-bottom:14px;}
		.gvva-stack i{display:block;height:100%;}
		.gvva-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
		.gvva-chip{padding:6px 14px;border-radius:20px;background:#fff;border:1px solid var(--line);font-size:12px;font-weight:600;color:var(--mut)!important;}
		.gvva-chip.is-active{background:var(--ink);border-color:var(--ink);color:#fff!important;}
		.gvva-big{display:flex;align-items:center;gap:14px;}
		.gvva-big b{font-size:44px;color:#16a34a;line-height:1;}
		.gvva-pulse{width:12px;height:12px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.6);animation:gvvap 1.6s infinite;}
		@keyframes gvvap{70%{box-shadow:0 0 0 12px rgba(34,197,94,0);}100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}}
		.gvva-path{display:flex;flex-wrap:wrap;gap:5px;align-items:center;font-size:12px;}
		.gvva-step{background:#eff6ff;color:#1e3a8a;padding:3px 10px;border-radius:8px;}
		.gvva-arrow{color:#94a3b8;}
		.gvva details summary{cursor:pointer;color:var(--p);font-size:12px;font-weight:600;}
		.gvva-btn{display:inline-block;background:var(--ink);color:#fff!important;border:0;padding:10px 20px;border-radius:11px;font-weight:700;cursor:pointer;font-size:13px;}
		.gvva-btn.alt{background:#fff;color:var(--ink)!important;border:1px solid var(--line);}
		.gvva-btn.danger{background:#b91c1c;}
		.gvva-export{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;}
		.gvva-export a{display:block;background:var(--bg);border:1px solid var(--line);border-radius:14px;padding:16px;color:var(--ink);font-weight:700;font-size:13px;}
		.gvva-export a small{display:block;color:var(--mut);font-weight:500;margin-top:4px;}
		.gvva-export a:hover{border-color:var(--p);background:#fff;}
		.gvva-field{margin-bottom:14px;}.gvva-field label{font-weight:700;font-size:13px;display:block;margin-bottom:6px;}
		.gvva-field input[type=number],.gvva-field input[type=email],.gvva-field select,.gvva-field textarea{padding:8px 12px;border-radius:10px;border:1px solid #d1d5db;min-width:240px;}
		.gvva-field small{display:block;color:var(--mut);margin-top:4px;}
		.gvva-note{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:12px 16px;font-size:12.5px;margin-bottom:16px;line-height:1.9;}
		@media print{#adminmenumain,#adminmenuback,#adminmenuwrap,#wpadminbar,#wpfooter,.gvva-noprint,.gvva-tabs,.gvva-range{display:none!important;}#wpcontent{margin:0!important;padding:0!important;}.gvva-card,.gvva-kpi{break-inside:avoid;}}
	</style>
<?php }

function gv_va_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s   = gv_va_get_settings();
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
	$R   = gv_va_range();

	$tabs = array(
		'overview' => '📊 نمای کلی', 'sources' => '🌐 منابع ورودی', 'pages' => '📄 صفحات',
		'behavior' => '🧭 رفتار کاربران', 'audience' => '👥 مخاطبان', 'realtime' => '🟢 آنلاین',
		'sessions' => '🕘 بازدیدها', 'reports' => '🧾 گزارش‌ها', 'settings' => '⚙️ تنظیمات',
	);
	if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'overview'; }
	$ranges = array( 'today' => 'امروز', 'yesterday' => 'دیروز', '7' => '۷ روز اخیر', '30' => '۳۰ روز اخیر', '90' => '۹۰ روز اخیر', '365' => '۱ سال اخیر', 'custom' => 'بازه دلخواه' );

	echo '<div class="wrap gvva" dir="rtl">';
	gv_va_css();
	?>
	<div class="gvva-hero">
		<div>
			<h1>📈 آمار بازدید و رفتار کاربران</h1>
			<p><?php echo esc_html( gv_va_jdate( $R['from'], true ) . ( $R['from'] !== $R['to'] ? '  تا  ' . gv_va_jdate( $R['to'], true ) : '' ) ); ?> — مقایسه با <?php echo esc_html( gv_va_fa( $R['days'] ) ); ?> روز قبل‌تر</p>
		</div>
		<?php if ( ! in_array( $tab, array( 'settings', 'realtime' ), true ) ) : ?>
		<form class="gvva-range gvva-noprint" method="get">
			<input type="hidden" name="page" value="gv-visitor-analytics">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<select name="range" id="gvva-range-sel">
				<?php foreach ( $ranges as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $R['key'], (string) $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
			</select>
			<span id="gvva-custom" style="display:<?php echo 'custom' === $R['key'] ? 'inline-flex' : 'none'; ?>;gap:6px;">
				<input type="date" name="from" value="<?php echo esc_attr( $R['from'] ); ?>">
				<input type="date" name="to" value="<?php echo esc_attr( $R['to'] ); ?>">
			</span>
			<button type="submit">اعمال</button>
		</form>
		<script>
			(function(){var s=document.getElementById('gvva-range-sel'),c=document.getElementById('gvva-custom');
			s.addEventListener('change',function(){if(s.value==='custom'){c.style.display='inline-flex';}else{s.form.submit();}});})();
		</script>
		<?php endif; ?>
	</div>

	<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div><?php endif; ?>
	<?php if ( isset( $_GET['cleared'] ) ) : ?><div class="notice notice-success is-dismissible"><p>داده‌های آماری پاک شد.</p></div><?php endif; ?>
	<?php if ( isset( $_GET['mail'] ) ) : ?><div class="notice notice-<?php echo 'ok' === $_GET['mail'] ? 'success' : 'error'; ?> is-dismissible"><p><?php echo 'ok' === $_GET['mail'] ? 'گزارش آزمایشی ارسال شد.' : 'ارسال ایمیل ناموفق بود؛ تنظیمات ایمیل (SMTP) سایت را بررسی کنید.'; ?></p></div><?php endif; ?>
	<?php if ( empty( $s['enabled'] ) ) : ?>
		<div class="gvva-note">⚠️ ردیابی در حال حاضر <b>غیرفعال</b> است. برای شروع ثبت آمار، از تب «تنظیمات» آن را فعال کنید.</div>
	<?php endif; ?>

	<nav class="gvva-tabs gvva-noprint">
		<?php foreach ( $tabs as $k => $l ) : ?>
			<a class="gvva-tab <?php echo $k === $tab ? 'is-active' : ''; ?>" href="<?php echo gv_va_url( $k ); ?>"><?php echo esc_html( $l ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php

	$fn = 'gv_va_tab_' . $tab;
	$fn( $R, $s );

	echo '<p style="font-size:11.5px;color:#94a3b8;text-align:center;margin-top:26px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p></div>';
}

/* ------------------------------ نمای کلی ------------------------------ */
function gv_va_tab_overview( $R ) {
	$cur  = gv_va_kpis( $R['from'], $R['to'] );
	$prev = gv_va_kpis( $R['pfrom'], $R['pto'] );
	$cards = array(
		array( 'بازدید صفحه', 'pageviews', '#2563eb', false, 'n' ),
		array( 'نشست (ورود)', 'sessions', '#7c3aed', false, 'n' ),
		array( 'بازدیدکننده یکتا', 'visitors', '#0891b2', false, 'n' ),
		array( 'بازدیدکننده جدید', 'new', '#16a34a', false, 'n' ),
		array( 'نرخ پرش', 'bounce', '#dc2626', true, 'p' ),
		array( 'میانگین مدت نشست', 'avg', '#d97706', false, 'd' ),
		array( 'صفحه در هر نشست', 'pps', '#db2777', false, 'f' ),
		array( 'کلیک ثبت‌شده', 'clicks', '#0d9488', false, 'n' ),
	);
	echo '<div class="gvva-kpis">';
	foreach ( $cards as $c ) {
		$v = $cur[ $c[1] ];
		$disp = 'p' === $c[4] ? gv_va_n( $v, 1 ) . '٪' : ( 'd' === $c[4] ? gv_va_dur( $v ) : ( 'f' === $c[4] ? gv_va_n( $v, 1 ) : gv_va_n( $v ) ) );
		echo '<div class="gvva-kpi" style="--c:' . esc_attr( $c[2] ) . '"><span>' . esc_html( $c[0] ) . '</span><b>' . esc_html( $disp ) . '</b>' . gv_va_delta( $v, $prev[ $c[1] ], $c[3] ) . '</div>';
	}
	echo '</div>';

	list( $labels, $pv, $se, $vi ) = gv_va_daily( $R );
	echo '<div class="gvva-card"><h2>روند بازدید روزانه</h2><div class="gvva-legend"><span><i style="background:#2563eb"></i>بازدید صفحه</span><span><i style="background:#7c3aed"></i>نشست</span><span><i style="background:#0891b2"></i>بازدیدکننده</span></div>';
	echo gv_va_svg_chart( $labels, array(
		array( 'name' => 'بازدید صفحه', 'color' => '#2563eb', 'data' => $pv ),
		array( 'name' => 'نشست', 'color' => '#7c3aed', 'data' => $se ),
		array( 'name' => 'بازدیدکننده', 'color' => '#0891b2', 'data' => $vi ),
	) ) . '</div>';

	// نوع منابع
	$types = gv_va_q_types( $R['from'], $R['to'] );
	$tt = 0; foreach ( $types as $r ) { $tt += $r->sessions; }
	$tm = gv_va_types();
	echo '<div class="gvva-grid2"><div class="gvva-card"><h2>ورودی‌ها از کجا آمده‌اند؟ <small><a href="' . gv_va_url( 'sources' ) . '">جزئیات ←</a></small></h2>';
	if ( $tt ) {
		echo '<div class="gvva-stack">';
		foreach ( $types as $r ) { echo '<i style="width:' . round( $r->sessions / $tt * 100, 2 ) . '%;background:' . esc_attr( $tm[ $r->source_type ][1] ?? '#64748b' ) . '"></i>'; }
		echo '</div>';
		$rows = array();
		foreach ( $types as $r ) { $rows[] = array( gv_va_badge( $r->source_type ), $r->sessions ); }
		echo gv_va_bars( $rows );
	} else { echo '<p class="gvva-empty">داده‌ای یافت نشد.</p>'; }
	echo '</div>';

	$src  = array_slice( gv_va_q_sources( $R['from'], $R['to'] ), 0, 8 );
	$rows = array();
	foreach ( $src as $r ) { $rows[] = array( esc_html( $r->source_name ) . ' ' . gv_va_badge( $r->source_type ), (int) $r->sessions ); }
	echo '<div class="gvva-card"><h2>برترین منابع ورودی</h2>' . gv_va_bars( $rows, '#7c3aed' ) . '</div></div>';

	$pages = array_slice( gv_va_q_pages( $R['from'], $R['to'] ), 0, 8 );
	$rows  = array();
	foreach ( $pages as $r ) { $rows[] = array( gv_va_page_link( $r->page_path, $r->title ), esc_html( gv_va_n( $r->views ) ), esc_html( gv_va_dur( $r->avg_t ) ) ); }
	echo '<div class="gvva-grid2"><div class="gvva-card"><h2>پربازدیدترین صفحات <small><a href="' . gv_va_url( 'pages' ) . '">همه صفحات ←</a></small></h2>' . gv_va_table( array( 'صفحه', 'بازدید', 'میانگین زمان' ), $rows ) . '</div>';

	$dev = array();
	$dn  = array( 'mobile' => '📱 موبایل', 'desktop' => '🖥️ دسکتاپ', 'tablet' => '📟 تبلت' );
	foreach ( gv_va_group( 'device', $R['from'], $R['to'] ) as $r ) { $dev[] = array( esc_html( $dn[ $r->k ] ?? $r->k ), (int) $r->c ); }
	echo '<div class="gvva-card"><h2>دستگاه‌ها <small><a href="' . gv_va_url( 'audience' ) . '">مخاطبان ←</a></small></h2>' . gv_va_bars( $dev, '#0891b2' ) . '</div></div>';
}

/* ----------------------------- منابع ورودی ----------------------------- */
function gv_va_tab_sources( $R ) {
	global $wpdb;
	$tm   = gv_va_types();
	$ftype = isset( $_GET['stype'] ) && isset( $tm[ sanitize_key( $_GET['stype'] ) ] ) ? sanitize_key( $_GET['stype'] ) : '';

	$types = gv_va_q_types( $R['from'], $R['to'] );
	$tt = 0; foreach ( $types as $r ) { $tt += $r->sessions; }

	echo '<div class="gvva-card"><h2>سهم هر نوع منبع از ورودی‌ها <small>مجموع ' . esc_html( gv_va_n( $tt ) ) . ' نشست</small></h2>';
	if ( $tt ) {
		echo '<div class="gvva-stack">';
		foreach ( $types as $r ) { echo '<i title="' . esc_attr( $tm[ $r->source_type ][0] ?? '' ) . '" style="width:' . round( $r->sessions / $tt * 100, 2 ) . '%;background:' . esc_attr( $tm[ $r->source_type ][1] ?? '#64748b' ) . '"></i>'; }
		echo '</div>';
	}
	$rows = array();
	foreach ( $types as $r ) {
		$rows[] = array( gv_va_badge( $r->source_type ), esc_html( gv_va_n( $r->sessions ) ), esc_html( gv_va_pct( $r->sessions, $tt ) ), esc_html( gv_va_n( $r->visitors ) ), esc_html( gv_va_pct( $r->bounces, $r->sessions ) ), esc_html( gv_va_dur( $r->avg_d ) ), esc_html( gv_va_n( $r->sessions ? $r->pv / $r->sessions : 0, 1 ) ) );
	}
	echo gv_va_table( array( 'نوع منبع', 'نشست', 'سهم', 'بازدیدکننده', 'نرخ پرش', 'میانگین مدت', 'صفحه/نشست' ), $rows ) . '</div>';

	echo '<div class="gvva-chips gvva-noprint"><a class="gvva-chip ' . ( $ftype ? '' : 'is-active' ) . '" href="' . gv_va_url( 'sources' ) . '">همه منابع</a>';
	foreach ( $tm as $k => $v ) { echo '<a class="gvva-chip ' . ( $ftype === $k ? 'is-active' : '' ) . '" href="' . gv_va_url( 'sources', array( 'stype' => $k ) ) . '">' . esc_html( $v[0] ) . '</a>'; }
	echo '</div>';

	$rows = array();
	foreach ( gv_va_q_sources( $R['from'], $R['to'], $ftype ) as $r ) {
		$dom = $r->domain ? '<div class="gvva-sub">' . esc_html( $r->domain ) . '</div>' : '';
		$rows[] = array( '<b>' . esc_html( $r->source_name ?: '—' ) . '</b>' . $dom, gv_va_badge( $r->source_type ), esc_html( gv_va_n( $r->sessions ) ), esc_html( gv_va_n( $r->visitors ) ), esc_html( gv_va_n( $r->new_v ) ), esc_html( gv_va_pct( $r->bounces, $r->sessions ) ), esc_html( gv_va_dur( $r->avg_d ) ), esc_html( gv_va_n( $r->pv ) ) );
	}
	echo '<div class="gvva-card"><h2>منبع دقیق ورودی‌ها <small>گوگل، بینگ، اینستاگرام، تلگرام، سایت‌های دیگر و …</small></h2>' . gv_va_table( array( 'منبع', 'نوع', 'نشست', 'بازدیدکننده', 'کاربر جدید', 'نرخ پرش', 'میانگین مدت', 'بازدید صفحه' ), $rows ) . '</div>';

	$camp = $wpdb->get_results( $wpdb->prepare(
		"SELECT utm_campaign, utm_source, utm_medium, COUNT(*) c, SUM(pages=1) b FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s AND utm_campaign <> '' GROUP BY utm_campaign, utm_source, utm_medium ORDER BY c DESC LIMIT 50",
		$R['from'] . ' 00:00:00', $R['to'] . ' 23:59:59' ) );
	$rows = array();
	foreach ( $camp as $r ) { $rows[] = array( esc_html( $r->utm_campaign ), esc_html( $r->utm_source ), esc_html( $r->utm_medium ), esc_html( gv_va_n( $r->c ) ), esc_html( gv_va_pct( $r->b, $r->c ) ) ); }
	echo '<div class="gvva-card"><h2>کمپین‌ها (UTM)</h2>' . gv_va_table( array( 'کمپین', 'utm_source', 'utm_medium', 'نشست', 'نرخ پرش' ), $rows, 'کمپینی ثبت نشده. به لینک‌های تبلیغاتی خود پارامتر ?utm_source=…&utm_medium=…&utm_campaign=… اضافه کنید.' ) . '</div>';

	$last = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s" . ( $ftype ? ' AND source_type = %s' : '' ) . " ORDER BY started_at DESC LIMIT 25",
		$ftype ? array( $R['from'] . ' 00:00:00', $R['to'] . ' 23:59:59', $ftype ) : array( $R['from'] . ' 00:00:00', $R['to'] . ' 23:59:59' ) ) );
	$rows = array();
	foreach ( $last as $r ) {
		$ref = $r->referrer ? '<a href="' . esc_url( $r->referrer ) . '" target="_blank" rel="noopener noreferrer nofollow">' . esc_html( mb_strimwidth( urldecode( $r->referrer ), 0, 60, '…' ) ) . '</a>' : '<span class="gvva-sub">بدون ارجاع‌دهنده</span>';
		$rows[] = array( esc_html( gv_va_jdt( $r->started_at ) ), gv_va_badge( $r->source_type ) . ' ' . esc_html( $r->source_name ), $ref, gv_va_page_link( (string) $r->landing_path ) );
	}
	echo '<div class="gvva-card"><h2>آخرین ورودی‌ها (آدرس دقیق ارجاع‌دهنده)</h2>' . gv_va_table( array( 'زمان', 'منبع', 'آدرس ارجاع‌دهنده', 'صفحه فرود' ), $rows ) . '</div>';

	echo '<div class="gvva-note">ℹ️ گوگل عبارت جستجوی کاربران را به سایت‌ها نمی‌دهد (Not Provided)؛ برای دیدن عبارات جستجو و رتبه‌ها باید Google Search Console را هم به سایت متصل کنید. این ماژول مشخص می‌کند چه تعداد بازدید از گوگل و کدام صفحه آمده است.</div>';
}

/* -------------------------------- صفحات -------------------------------- */
function gv_va_tab_pages( $R ) {
	$rows = array();
	foreach ( gv_va_q_pages( $R['from'], $R['to'] ) as $r ) {
		$rows[] = array(
			gv_va_page_link( $r->page_path, $r->title ), esc_html( gv_va_n( $r->views ) ), esc_html( gv_va_n( $r->uniq ) ), esc_html( gv_va_dur( $r->avg_t ) ),
			esc_html( $r->avg_s ? gv_va_n( $r->avg_s ) . '٪' : '—' ), esc_html( gv_va_n( $r->entrances ) ), esc_html( gv_va_n( $r->exits ) ),
			esc_html( gv_va_pct( $r->bounces, $r->entrances ) ),
		);
	}
	echo '<div class="gvva-card"><h2>عملکرد صفحات <small><a href="' . esc_url( gv_va_export_url( 'pages' ) ) . '">⬇ CSV</a></small></h2>' . gv_va_table( array( 'صفحه', 'بازدید', 'بازدید یکتا', 'میانگین زمان', 'میانگین اسکرول', 'ورودی (فرود)', 'خروجی', 'نرخ پرش' ), $rows ) . '</div>';
}

/* ----------------------------- رفتار کاربران ----------------------------- */
function gv_va_tab_behavior( $R ) {
	$ev = isset( $_GET['ev'] ) ? sanitize_key( $_GET['ev'] ) : '';
	$labels = array( 'click' => 'کلیک', 'outbound' => 'لینک خروجی', 'download' => 'دانلود', 'tel' => 'تماس تلفنی', 'mailto' => 'ایمیل' );
	if ( ! isset( $labels[ $ev ] ) ) { $ev = ''; }

	$rows = array();
	foreach ( gv_va_q_flows( $R['from'], $R['to'] ) as $r ) {
		$rows[] = array( '<span class="gvva-step">' . esc_html( mb_strimwidth( $r->prev_path, 0, 55, '…' ) ) . '</span>', '<span class="gvva-arrow">←</span>', '<span class="gvva-step">' . esc_html( mb_strimwidth( $r->page_path, 0, 55, '…' ) ) . '</span>', '<b>' . esc_html( gv_va_n( $r->c ) ) . '</b>' );
	}
	echo '<div class="gvva-card"><h2>مسیر حرکت بین صفحات <small>کاربران از کدام صفحه به کدام صفحه رفته‌اند</small></h2>' . gv_va_table( array( 'از صفحه', '', 'به صفحه', 'تعداد' ), $rows ) . '</div>';

	echo '<div class="gvva-chips gvva-noprint"><a class="gvva-chip ' . ( $ev ? '' : 'is-active' ) . '" href="' . gv_va_url( 'behavior' ) . '">همه رویدادها</a>';
	foreach ( $labels as $k => $l ) { echo '<a class="gvva-chip ' . ( $ev === $k ? 'is-active' : '' ) . '" href="' . gv_va_url( 'behavior', array( 'ev' => $k ) ) . '">' . esc_html( $l ) . '</a>'; }
	echo '</div>';

	$rows = array();
	foreach ( gv_va_q_clicks( $R['from'], $R['to'], $ev ) as $r ) {
		$href = $r->target_href ? '<a href="' . esc_url( $r->target_href ) . '" target="_blank" rel="noopener noreferrer nofollow">' . esc_html( mb_strimwidth( urldecode( $r->target_href ), 0, 60, '…' ) ) . '</a>' : '—';
		$rows[] = array( esc_html( $r->target_text ?: '—' ), $href, esc_html( gv_va_event_label( $r->event_type ) ), '<span class="gvva-sub">' . esc_html( $r->page_path ) . '</span>', '<b>' . esc_html( gv_va_n( $r->clicks ) ) . '</b>', esc_html( gv_va_n( $r->sessions ) ) );
	}
	echo '<div class="gvva-card"><h2>پرکلیک‌ترین لینک‌ها و دکمه‌ها <small><a href="' . esc_url( gv_va_export_url( 'clicks' ) ) . '">⬇ CSV</a></small></h2>' . gv_va_table( array( 'متن', 'لینک', 'نوع', 'صفحه', 'کلیک', 'نشست' ), $rows ) . '</div>';
}

/* -------------------------------- مخاطبان -------------------------------- */
function gv_va_tab_audience( $R ) {
	global $wpdb;
	$f = $R['from']; $t = $R['to'];
	$a = $f . ' 00:00:00'; $b = $t . ' 23:59:59';
	$dn = array( 'mobile' => '📱 موبایل', 'desktop' => '🖥️ دسکتاپ', 'tablet' => '📟 تبلت' );
	$mk = function ( $col, $map = array() ) use ( $f, $t ) {
		$o = array();
		foreach ( gv_va_group( $col, $f, $t ) as $r ) {
			$lbl = 'country' === $col ? gv_va_flag( $r->k ) : esc_html( $map[ $r->k ] ?? $r->k );
			$o[] = array( $lbl, (int) $r->c );
		}
		return $o;
	};
	echo '<div class="gvva-grid3">';
	echo '<div class="gvva-card"><h2>دستگاه</h2>' . gv_va_bars( $mk( 'device', $dn ), '#2563eb' ) . '</div>';
	echo '<div class="gvva-card"><h2>مرورگر</h2>' . gv_va_bars( $mk( 'browser' ), '#7c3aed' ) . '</div>';
	echo '<div class="gvva-card"><h2>سیستم‌عامل</h2>' . gv_va_bars( $mk( 'os' ), '#0891b2' ) . '</div></div>';

	$nv = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(is_new=1),0) n, COALESCE(SUM(is_new=0),0) r FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s", $a, $b ) );
	echo '<div class="gvva-grid3">';
	echo '<div class="gvva-card"><h2>کاربر جدید و بازگشتی <small>بر اساس نشست</small></h2>' . gv_va_bars( array( array( 'کاربر جدید', (int) $nv->n ), array( 'کاربر بازگشتی', (int) $nv->r ) ), '#16a34a' ) . '</div>';
	echo '<div class="gvva-card"><h2>کشور <small>نیازمند Cloudflare</small></h2>' . gv_va_bars( $mk( 'country' ), '#d97706' ) . '</div>';
	echo '<div class="gvva-card"><h2>زبان مرورگر</h2>' . gv_va_bars( $mk( 'lang' ), '#db2777' ) . '</div></div>';

	// ساعت‌های روز
	$hrs = $wpdb->get_results( $wpdb->prepare( "SELECT HOUR(started_at) h, COUNT(*) c FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s GROUP BY h", $a, $b ), OBJECT_K );
	$lab = $dat = array();
	for ( $h = 0; $h < 24; $h++ ) { $lab[] = gv_va_fa( $h ); $dat[] = isset( $hrs[ $h ] ) ? (int) $hrs[ $h ]->c : 0; }
	echo '<div class="gvva-card"><h2>ساعت‌های پربازدید در طول روز</h2>' . gv_va_svg_chart( $lab, array( array( 'name' => 'نشست', 'color' => '#16a34a', 'data' => $dat ) ) ) . '</div>';

	// روزهای هفته
	$wd = $wpdb->get_results( $wpdb->prepare( "SELECT DAYOFWEEK(started_at) d, COUNT(*) c FROM " . gv_va_t( 'sessions' ) . " WHERE started_at BETWEEN %s AND %s GROUP BY d", $a, $b ), OBJECT_K );
	$names = array( 7 => 'شنبه', 1 => 'یکشنبه', 2 => 'دوشنبه', 3 => 'سه‌شنبه', 4 => 'چهارشنبه', 5 => 'پنجشنبه', 6 => 'جمعه' );
	$rows = array();
	foreach ( $names as $k => $n ) { $rows[] = array( esc_html( $n ), isset( $wd[ $k ] ) ? (int) $wd[ $k ]->c : 0 ); }
	echo '<div class="gvva-card"><h2>روزهای هفته</h2>' . gv_va_bars( $rows, '#2563eb' ) . '</div>';
}

/* ------------------------------ کاربران آنلاین ------------------------------ */
function gv_va_tab_realtime( $R ) {
	global $wpdb;
	$since = wp_date( 'Y-m-d H:i:s', time() - 300 );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . gv_va_t( 'sessions' ) . " WHERE last_seen >= %s ORDER BY last_seen DESC LIMIT 100", $since ) );
	$t = array();
	foreach ( $rows as $r ) {
		$t[] = array( gv_va_page_link( (string) $r->exit_path ), gv_va_badge( $r->source_type ) . ' ' . esc_html( $r->source_name ), esc_html( $r->device . ' / ' . $r->browser ), gv_va_flag( $r->country ), esc_html( gv_va_fa( substr( $r->last_seen, 11, 8 ) ) ) );
	}
	echo '<div class="gvva-card"><div class="gvva-big"><span class="gvva-pulse"></span><b>' . esc_html( gv_va_n( count( $rows ) ) ) . '</b><div><strong>کاربر آنلاین</strong><br><small style="color:#64748b">فعال در ۵ دقیقه اخیر — هر ۳۰ ثانیه خودکار به‌روز می‌شود</small></div></div></div>';
	echo '<div class="gvva-card"><h2>در حال بازدید</h2>' . gv_va_table( array( 'صفحه فعلی', 'منبع ورود', 'دستگاه / مرورگر', 'کشور', 'آخرین فعالیت' ), $t, 'هم‌اکنون کاربری آنلاین نیست.' ) . '</div>';
	echo '<script>setTimeout(function(){location.reload();},30000);</script>';
}

/* ------------------------------ لیست بازدیدها ------------------------------ */
function gv_va_tab_sessions( $R ) {
	global $wpdb;
	$tm    = gv_va_types();
	$ftype = isset( $_GET['stype'] ) && isset( $tm[ sanitize_key( $_GET['stype'] ) ] ) ? sanitize_key( $_GET['stype'] ) : '';
	$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
	$per   = 30;
	$where = 'started_at BETWEEN %s AND %s';
	$args  = array( $R['from'] . ' 00:00:00', $R['to'] . ' 23:59:59' );
	if ( $ftype ) { $where .= ' AND source_type = %s'; $args[] = $ftype; }

	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . gv_va_t( 'sessions' ) . " WHERE {$where}", $args ) );
	$list  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . gv_va_t( 'sessions' ) . " WHERE {$where} ORDER BY started_at DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $paged - 1 ) * $per ) ) ) );

	$pathmap = array();
	if ( $list ) {
		$ids = wp_list_pluck( $list, 'session_id' );
		$ph  = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
		$vs  = $wpdb->get_results( $wpdb->prepare( "SELECT session_id, page_path, page_title, time_spent FROM " . gv_va_t( 'visits' ) . " WHERE session_id IN ({$ph}) ORDER BY id ASC", $ids ) );
		foreach ( $vs as $v ) { $pathmap[ $v->session_id ][] = $v; }
	}

	echo '<div class="gvva-chips gvva-noprint"><a class="gvva-chip ' . ( $ftype ? '' : 'is-active' ) . '" href="' . gv_va_url( 'sessions' ) . '">همه</a>';
	foreach ( $tm as $k => $v ) { echo '<a class="gvva-chip ' . ( $ftype === $k ? 'is-active' : '' ) . '" href="' . gv_va_url( 'sessions', array( 'stype' => $k ) ) . '">' . esc_html( $v[0] ) . '</a>'; }
	echo '</div>';

	$rows = array();
	foreach ( $list as $r ) {
		$steps = '';
		if ( ! empty( $pathmap[ $r->session_id ] ) ) {
			$steps = '<details><summary>مسیر (' . esc_html( gv_va_fa( count( $pathmap[ $r->session_id ] ) ) ) . ' صفحه)</summary><div class="gvva-path" style="margin-top:8px">';
			foreach ( $pathmap[ $r->session_id ] as $i => $v ) {
				if ( $i ) { $steps .= '<span class="gvva-arrow">←</span>'; }
				$steps .= '<span class="gvva-step" title="' . esc_attr( $v->page_path ) . '">' . esc_html( mb_strimwidth( $v->page_title ?: $v->page_path, 0, 40, '…' ) ) . ' <small>' . esc_html( gv_va_dur( $v->time_spent ) ) . '</small></span>';
			}
			$steps .= '</div></details>';
		}
		$rows[] = array(
			esc_html( gv_va_jdt( $r->started_at ) ), gv_va_badge( $r->source_type ) . '<div class="gvva-sub" style="text-align:right">' . esc_html( $r->source_name ) . '</div>',
			gv_va_page_link( (string) $r->landing_path ), esc_html( gv_va_n( $r->pages ) ), esc_html( gv_va_dur( $r->duration ) ),
			esc_html( $r->device . ' / ' . $r->browser . ' / ' . $r->os ), gv_va_flag( $r->country ), ( $r->is_new ? '🆕' : '🔁' ), $steps,
		);
	}
	echo '<div class="gvva-card"><h2>بازدیدها (نشست‌ها) <small>' . esc_html( gv_va_n( $total ) ) . ' مورد</small></h2>' . gv_va_table( array( 'زمان', 'منبع', 'صفحه فرود', 'صفحه', 'مدت', 'دستگاه', 'کشور', 'جدید/بازگشتی', 'مسیر' ), $rows ) . '</div>';

	$pages = (int) ceil( $total / $per );
	if ( $pages > 1 ) {
		echo '<div class="gvva-chips gvva-noprint">';
		for ( $i = max( 1, $paged - 3 ); $i <= min( $pages, $paged + 3 ); $i++ ) {
			echo '<a class="gvva-chip ' . ( $i === $paged ? 'is-active' : '' ) . '" href="' . gv_va_url( 'sessions', array( 'paged' => $i, 'stype' => $ftype ) ) . '">' . esc_html( gv_va_fa( $i ) ) . '</a>';
		}
		echo '</div>';
	}
}

/* -------------------------------- گزارش‌ها -------------------------------- */
function gv_va_export_url( $dataset ) {
	$R = gv_va_range();
	$a = array( 'action' => 'gv_va_export', 'dataset' => $dataset, 'range' => $R['key'], 'from' => $R['from'], 'to' => $R['to'] );
	return wp_nonce_url( add_query_arg( $a, admin_url( 'admin-post.php' ) ), 'gv_va_export' );
}

function gv_va_tab_reports( $R, $s ) {
	$cur  = gv_va_kpis( $R['from'], $R['to'] );
	$prev = gv_va_kpis( $R['pfrom'], $R['pto'] );

	echo '<div class="gvva-card"><h2>خلاصه گزارش <small>' . esc_html( gv_va_jdate( $R['from'], true ) . ' تا ' . gv_va_jdate( $R['to'], true ) ) . '</small></h2>';
	$rows = array();
	$def  = array(
		array( 'بازدید صفحه', 'pageviews', 'n', false ), array( 'نشست', 'sessions', 'n', false ), array( 'بازدیدکننده یکتا', 'visitors', 'n', false ),
		array( 'بازدیدکننده جدید', 'new', 'n', false ), array( 'نرخ پرش', 'bounce', 'p', true ), array( 'میانگین مدت نشست', 'avg', 'd', false ),
		array( 'صفحه در هر نشست', 'pps', 'f', false ), array( 'کلیک ثبت‌شده', 'clicks', 'n', false ),
	);
	foreach ( $def as $d ) {
		$fmt = function ( $v ) use ( $d ) { return 'p' === $d[2] ? gv_va_n( $v, 1 ) . '٪' : ( 'd' === $d[2] ? gv_va_dur( $v ) : ( 'f' === $d[2] ? gv_va_n( $v, 1 ) : gv_va_n( $v ) ) ); };
		$rows[] = array( esc_html( $d[0] ), '<b>' . esc_html( $fmt( $cur[ $d[1] ] ) ) . '</b>', esc_html( $fmt( $prev[ $d[1] ] ) ), gv_va_delta( $cur[ $d[1] ], $prev[ $d[1] ], $d[3] ) );
	}
	echo gv_va_table( array( 'شاخص', 'این بازه', 'بازه قبل', 'تغییر' ), $rows );
	echo '<p class="gvva-noprint" style="margin-bottom:0"><button class="gvva-btn" onclick="window.print()">🖨️ چاپ / ذخیره PDF</button></p></div>';

	echo '<div class="gvva-card gvva-noprint"><h2>خروجی CSV (سازگار با Excel) <small>برای بازه انتخاب‌شده در بالا</small></h2><div class="gvva-export">';
	$ex = array(
		'sessions' => array( '🕘 همه نشست‌ها', 'یک ردیف برای هر ورود با منبع، دستگاه و …' ),
		'sources'  => array( '🌐 منابع ورودی', 'گوگل، شبکه‌های اجتماعی، ارجاعی و …' ),
		'pages'    => array( '📄 عملکرد صفحات', 'بازدید، زمان، اسکرول، ورودی و خروجی' ),
		'clicks'   => array( '🖱️ کلیک‌ها و لینک‌ها', 'کلیک، دانلود، لینک خروجی، تماس' ),
		'flows'    => array( '🧭 مسیر بین صفحات', 'از کدام صفحه به کدام صفحه' ),
	);
	foreach ( $ex as $k => $v ) { echo '<a href="' . esc_url( gv_va_export_url( $k ) ) . '">' . esc_html( $v[0] ) . '<small>' . esc_html( $v[1] ) . '</small></a>'; }
	echo '</div></div>';

	echo '<div class="gvva-card gvva-noprint"><h2>گزارش ایمیلی خودکار</h2><p style="color:#64748b;margin-top:0;font-size:12.5px;">وضعیت فعلی: <b>' . esc_html( array( 'off' => 'غیرفعال', 'daily' => 'روزانه', 'weekly' => 'هفتگی', 'monthly' => 'ماهانه' )[ $s['report_freq'] ] ?? 'غیرفعال' ) . '</b> — تنظیم در تب «تنظیمات».</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gv_va_send_test">' . wp_nonce_field( GV_VA_NONCE, '_wpnonce', true, false ) . '<button class="gvva-btn alt" type="submit">✉️ ارسال گزارش ۷ روز اخیر همین حالا</button></form></div>';
}

/* -------------------------------- تنظیمات -------------------------------- */
function gv_va_tab_settings( $R, $s ) {
	$cb = function ( $name, $label, $hint = '' ) use ( $s ) {
		echo '<div class="gvva-field"><label><input type="checkbox" name="' . esc_attr( $name ) . '" ' . checked( $s[ $name ], 1, false ) . '> ' . esc_html( $label ) . '</label>' . ( $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</div>';
	};
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gv_va_save_settings">' . wp_nonce_field( GV_VA_NONCE, '_wpnonce', true, false );

	echo '<div class="gvva-card"><h2>ثبت آمار</h2>';
	$cb( 'enabled', 'فعال‌سازی کلی ردیابی آمار' );
	$cb( 'track_clicks', 'ثبت کلیک‌ها، لینک‌های خروجی، دانلودها و تماس‌ها' );
	$cb( 'track_time', 'ثبت مدت‌زمان واقعی حضور (فقط زمان باز بودن تب)' );
	$cb( 'track_scroll', 'ثبت عمق اسکرول صفحات' );
	$cb( 'track_path', 'ثبت مسیر حرکت بین صفحات' );
	echo '</div><div class="gvva-card"><h2>فیلتر ترافیک</h2>';
	$cb( 'ignore_admins', 'بازدید مدیران سایت ثبت نشود' );
	$cb( 'ignore_bots', 'ترافیک ربات‌ها و خزنده‌ها ثبت نشود', 'ربات‌های شناخته‌شده (Googlebot، ابزارهای تست سرعت و …) نادیده گرفته می‌شوند.' );
	echo '<div class="gvva-field"><label>IPهای مستثنی (هر خط یا با کاما جدا شود)</label><textarea name="exclude_ips" rows="3" style="direction:ltr">' . esc_textarea( $s['exclude_ips'] ) . '</textarea><small>آی‌پی دفتر یا خودتان را اینجا بگذارید تا در آمار نیاید.</small></div></div>';

	echo '<div class="gvva-card"><h2>نگه‌داری داده‌ها</h2><div class="gvva-field"><label>حذف خودکار داده‌های قدیمی‌تر از (روز) — عدد ۰ یعنی هرگز</label><input type="number" name="retention_days" min="0" max="1095" value="' . esc_attr( $s['retention_days'] ) . '"></div></div>';

	echo '<div class="gvva-card"><h2>گزارش ایمیلی زمان‌بندی‌شده</h2><div class="gvva-field"><label>دوره ارسال</label><select name="report_freq">';
	foreach ( array( 'off' => 'غیرفعال', 'daily' => 'روزانه', 'weekly' => 'هفتگی', 'monthly' => 'ماهانه' ) as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['report_freq'], $k, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select></div><div class="gvva-field"><label>ایمیل گیرنده</label><input type="email" name="report_email" value="' . esc_attr( $s['report_email'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" style="direction:ltr"><small>اگر خالی بماند به ایمیل مدیر سایت ارسال می‌شود.</small></div></div>';

	echo '<button type="submit" class="gvva-btn">💾 ذخیره تنظیمات</button></form>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'تمام داده‌های آماری برای همیشه پاک شوند؟\');" style="margin-top:14px;"><input type="hidden" name="action" value="gv_va_clear_data">' . wp_nonce_field( GV_VA_NONCE, '_wpnonce', true, false ) . '<button type="submit" class="gvva-btn danger">🗑️ پاک‌سازی کامل داده‌های آماری</button></form>';

	echo '<div class="gvva-note" style="margin-top:18px">🔒 حریم خصوصی: این ماژول از کوکی استفاده نمی‌کند، آدرس IP فقط به‌صورت هش یک‌طرفه ذخیره می‌شود و داده‌ها روی دیتابیس خود سایت می‌مانند. اگر از افزونه کش استفاده می‌کنید نیازی به تنظیم خاصی نیست؛ ثبت آمار از طریق REST API انجام می‌شود.</div>';
}