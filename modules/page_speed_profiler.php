<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — پروفایلر سرعت صفحه
 *  ------------------------------------------------------------
 *  هر بار که یک صفحه لود می‌شود (طبق تنظیمات: فرانت / پیشخوان)،
 *  زمان سپری‌شده در مراحل مختلف بوت‌شدن وردپرس را اندازه می‌گیرد:
 *    ۱) قبل از افزونه‌ها (هسته وردپرس + بارگذاری فایل افزونه‌ها)
 *    ۲) راه‌اندازی اولیه افزونه‌ها (هوک plugins_loaded)
 *    ۳) راه‌اندازی قالب (after_setup_theme)
 *    ۴) هوک init (ثبت پست‌تایپ/شورت‌کد/API و ...)
 *    ۵) پردازش درخواست و کوئری اصلی
 *    ۶) رندر خروجی نهایی
 *  علاوه بر آن، کوئری‌های کند دیتابیس و درخواست‌های خروجی (HTTP)
 *  کند را هم شناسایی و به افزونه/قالب مسبب نسبت می‌دهد، و در
 *  یک لاگ چرخشی ذخیره می‌کند تا مدیر سایت بفهمد کندی از کجاست.
 *  اگر «حالت عمیق» فعال باشد، با ردیابی سبک‌وزن هوک‌های وردپرس
 *  (فیلتر all) تخمین می‌زند کدام افزونه بیشترین زمان اجرا را
 *  مصرف کرده است — فقط برای زمانی که در حال بررسی مشکل هستید،
 *  چون هزینه‌ی پردازشی بیشتری دارد.
 * ==========================================================
 */

define( 'GV_SPD_OPT',       'gv_speed_profiler_settings' );
define( 'GV_SPD_LOG_OPT',   'gv_speed_profiler_log' );
define( 'GV_SPD_NONCE',     'gv_spd_nonce_action' );
define( 'GV_SPD_PAGE_SLUG', 'gv-speed-profiler' );

/* همین اول، قبل از هر هوکی، ساعت مرجع شروع درخواست را می‌گیریم
   (REQUEST_TIME_FLOAT را خودِ وب‌سرور/پی‌اچ‌پی قبل از اجرای هر
   کد وردپرسی تنظیم می‌کند، پس دقیق‌ترین نقطه شروع است). */
$GLOBALS['gv_spd_boot_ref'] = isset( $_SERVER['REQUEST_TIME_FLOAT'] )
	? (float) $_SERVER['REQUEST_TIME_FLOAT']
	: microtime( true );

/* ==========================================================================
   ۱) مقادیر پیش‌فرض و تنظیمات
   ========================================================================== */

function gv_spd_default_settings() {
	return array(
		'enabled'              => 1,
		'track_frontend'       => 1,
		'track_admin'          => 0,
		'track_ajax_rest_cron' => 0,
		'threshold'            => 1.5,  // ثانیه؛ فقط لودهای کندتر از این مقدار لاگ می‌شوند
		'query_threshold'      => 0.05, // ثانیه؛ آستانه کند بودن یک کوئری
		'http_threshold'       => 1.0,  // ثانیه؛ آستانه کند بودن یک درخواست خروجی
		'sample_rate'          => 100,  // درصد؛ چند درصد از بازدیدهای واجد شرایط بررسی شوند
		'deep_mode'            => 0,    // ردیابی هوک‌به‌هوک برای رتبه‌بندی افزونه‌ها (سنگین‌تر)
		'deep_mode_admin_only' => 1,    // حالت عمیق فقط وقتی خودِ مدیر سایت لاگین است اجرا شود
		'max_log_entries'      => 30,
	);
}

function gv_spd_get_settings() {
	return wp_parse_args( get_option( GV_SPD_OPT, array() ), gv_spd_default_settings() );
}

/* ==========================================================================
   ۲) منوی مدیریت
   ========================================================================== */

add_action( 'admin_menu', 'gv_spd_admin_menu' );
function gv_spd_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'پروفایلر سرعت صفحه | Groot Vision',
		'🐢 پروفایلر سرعت',
		'manage_options',
		GV_SPD_PAGE_SLUG,
		'gv_spd_render_admin_page'
	);
}

add_action( 'admin_enqueue_scripts', 'gv_spd_admin_assets' );
function gv_spd_admin_assets( $hook ) {
	if ( strpos( $hook, GV_SPD_PAGE_SLUG ) === false ) { return; }
	wp_add_inline_script( 'jquery-core', "jQuery(function($){
		$('.gvspd-tab-btn').on('click', function(e){
			e.preventDefault();
			var target = $(this).data('tab');
			$('.gvspd-tab-btn').removeClass('is-active');
			$(this).addClass('is-active');
			$('.gvspd-tab-panel').removeClass('is-active').hide();
			$('#gvspd-tab-' + target).addClass('is-active').show();
			if (history.replaceState) {
				var url = new URL(window.location.href);
				url.searchParams.set('tab', target);
				history.replaceState(null, '', url);
			}
		});
		$('.gvspd-log-row').on('click', function(){
			$(this).next('.gvspd-log-detail').toggle();
			$(this).find('.gvspd-expand-arrow').toggleClass('is-open');
		});
	});" );
}

/* ==========================================================================
   ۳) ذخیره/پاک‌سازی تنظیمات
   ========================================================================== */

add_action( 'admin_post_gv_spd_save_settings', 'gv_spd_save_settings' );
function gv_spd_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SPD_NONCE );

	$settings = array(
		'enabled'              => isset( $_POST['enabled'] ) ? 1 : 0,
		'track_frontend'       => isset( $_POST['track_frontend'] ) ? 1 : 0,
		'track_admin'          => isset( $_POST['track_admin'] ) ? 1 : 0,
		'track_ajax_rest_cron' => isset( $_POST['track_ajax_rest_cron'] ) ? 1 : 0,
		'threshold'            => max( 0.2, floatval( $_POST['threshold'] ?? 1.5 ) ),
		'query_threshold'      => max( 0.01, floatval( $_POST['query_threshold'] ?? 0.05 ) ),
		'http_threshold'       => max( 0.1, floatval( $_POST['http_threshold'] ?? 1.0 ) ),
		'sample_rate'          => max( 1, min( 100, intval( $_POST['sample_rate'] ?? 100 ) ) ),
		'deep_mode'            => isset( $_POST['deep_mode'] ) ? 1 : 0,
		'deep_mode_admin_only' => isset( $_POST['deep_mode_admin_only'] ) ? 1 : 0,
		'max_log_entries'      => max( 5, min( 200, intval( $_POST['max_log_entries'] ?? 30 ) ) ),
	);

	update_option( GV_SPD_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SPD_PAGE_SLUG . '&tab=settings&updated=1' ) );
	exit;
}

add_action( 'admin_post_gv_spd_clear_log', 'gv_spd_clear_log' );
function gv_spd_clear_log() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SPD_NONCE );
	delete_option( GV_SPD_LOG_OPT );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SPD_PAGE_SLUG . '&tab=log&cleared=1' ) );
	exit;
}

/* ==========================================================================
   ۴) شروع پروفایلینگ (تصمیم‌گیری + علامت‌گذاری مراحل بوت)
   ========================================================================== */

add_action( 'plugins_loaded', 'gv_spd_bootstrap', -99999 );
function gv_spd_bootstrap() {
	$GLOBALS['gv_spd_marks'] = array( 'plugins_loaded' => microtime( true ) );

	$s = gv_spd_get_settings();
	$GLOBALS['gv_spd_settings'] = $s;

	if ( ! gv_spd_should_profile( $s ) ) {
		$GLOBALS['gv_spd_profiling'] = false;
		return;
	}
	$GLOBALS['gv_spd_profiling'] = true;

	global $wpdb;
	$GLOBALS['gv_spd_query_offset'] = ! empty( $wpdb->queries ) ? count( $wpdb->queries ) : 0;
	$wpdb->save_queries = true; // از همین لحظه به بعد، زمان دقیق هر کوئری ثبت می‌شود

	add_action( 'after_setup_theme', function () { $GLOBALS['gv_spd_marks']['after_setup_theme'] = microtime( true ); }, -99999 );
	add_action( 'init', function () { $GLOBALS['gv_spd_marks']['init'] = microtime( true ); }, -99999 );
	add_action( 'init', 'gv_spd_maybe_start_deep_mode', -99998 );
	add_action( 'wp_loaded', function () { $GLOBALS['gv_spd_marks']['wp_loaded'] = microtime( true ); }, -99999 );
	add_action( 'template_redirect', function () { $GLOBALS['gv_spd_marks']['template_redirect'] = microtime( true ); }, -99999 );
	add_action( 'admin_init', function () {
		if ( ! isset( $GLOBALS['gv_spd_marks']['template_redirect'] ) ) { $GLOBALS['gv_spd_marks']['template_redirect'] = microtime( true ); }
	}, -99999 );
	add_action( 'rest_api_init', function () {
		if ( ! isset( $GLOBALS['gv_spd_marks']['template_redirect'] ) ) { $GLOBALS['gv_spd_marks']['template_redirect'] = microtime( true ); }
	}, -99999 );

	add_filter( 'query', 'gv_spd_capture_query_owner', 999999 );
	add_filter( 'http_request_args', 'gv_spd_http_start', 5, 2 );
	add_action( 'http_api_debug', 'gv_spd_http_end', 10, 5 );

	add_action( 'shutdown', 'gv_spd_on_shutdown', 999999 );
}

function gv_spd_should_profile( $s ) {
	if ( empty( $s['enabled'] ) ) { return false; }
	if ( defined( 'WP_CLI' ) && WP_CLI ) { return false; }
	if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		if ( empty( $s['track_ajax_rest_cron'] ) ) { return false; }
	} elseif ( is_admin() ) {
		if ( empty( $s['track_admin'] ) ) { return false; }
	} else {
		if ( empty( $s['track_frontend'] ) ) { return false; }
	}

	$rate = isset( $s['sample_rate'] ) ? max( 1, min( 100, intval( $s['sample_rate'] ) ) ) : 100;
	if ( $rate < 100 && wp_rand( 1, 100 ) > $rate ) { return false; }

	return true;
}

/**
 * حالت عمیق: از هوک همه‌منظوره «all» برای تخمین زمان مصرفی هر هوک استفاده می‌کند.
 * چون این هوک برای هر اکشن/فیلتر وردپرس (می‌تواند هزاران بار در یک صفحه) اجرا
 * می‌شود، هزینه‌ی پردازشی محسوسی دارد؛ به همین دلیل پیش‌فرض خاموش است و
 * می‌توانید آن را فقط برای زمانی که خودتان لاگین هستید فعال نگه دارید.
 */
function gv_spd_maybe_start_deep_mode() {
	if ( empty( $GLOBALS['gv_spd_profiling'] ) ) { return; }
	$s = $GLOBALS['gv_spd_settings'];
	if ( empty( $s['deep_mode'] ) ) { return; }

	$allowed = empty( $s['deep_mode_admin_only'] ) || current_user_can( 'manage_options' );
	if ( ! $allowed ) { return; }

	$GLOBALS['gv_spd_deep_active'] = true;
	add_action( 'all', 'gv_spd_deep_on_all' );
}

function gv_spd_deep_on_all() {
	if ( empty( $GLOBALS['gv_spd_deep_active'] ) ) { return; }

	$now = microtime( true );
	if ( isset( $GLOBALS['gv_spd_deep_last_mark'] ) ) {
		$delta = $now - $GLOBALS['gv_spd_deep_last_mark'];
		$owner_key = $GLOBALS['gv_spd_deep_last_owner'];
		if ( ! isset( $GLOBALS['gv_spd_deep_totals'][ $owner_key ] ) ) { $GLOBALS['gv_spd_deep_totals'][ $owner_key ] = 0.0; }
		$GLOBALS['gv_spd_deep_totals'][ $owner_key ] += $delta;
	}

	$hook = current_filter();
	$GLOBALS['gv_spd_deep_last_owner'] = gv_spd_deep_resolve_hook_owner( $hook );
	$GLOBALS['gv_spd_deep_last_mark']  = $now;

	// ضامن ایمنی: اگر تعداد فراخوانی هوک‌ها خیلی زیاد شد (صفحات سنگین)، ردیابی را متوقف کن
	$GLOBALS['gv_spd_deep_hook_calls'] = ( $GLOBALS['gv_spd_deep_hook_calls'] ?? 0 ) + 1;
	if ( $GLOBALS['gv_spd_deep_hook_calls'] > 20000 ) {
		remove_action( 'all', 'gv_spd_deep_on_all' );
		$GLOBALS['gv_spd_deep_active']    = false;
		$GLOBALS['gv_spd_deep_truncated'] = true;
	}
}

/** مالکِ (افزونه/قالب/هسته) یک نام هوک را تشخیص می‌دهد؛ نتیجه به‌ازای هر نام هوک کش می‌شود. */
function gv_spd_deep_resolve_hook_owner( $hook ) {
	static $cache = array();
	if ( isset( $cache[ $hook ] ) ) { return $cache[ $hook ]; }

	global $wp_filter;
	$owner_key = 'core';

	if ( isset( $wp_filter[ $hook ] ) && is_object( $wp_filter[ $hook ] ) && ! empty( $wp_filter[ $hook ]->callbacks ) ) {
		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$file = gv_spd_callable_file( $cb['function'] );
				if ( ! $file ) { continue; }
				$owner = gv_spd_path_to_owner( $file );
				if ( $owner ) {
					$owner_key = $owner['type'] . ':' . $owner['slug'];
					break 2;
				}
			}
		}
	}

	$cache[ $hook ] = $owner_key;
	return $owner_key;
}

/* ==========================================================================
   ۵) ردیابی کوئری‌های کند دیتابیس
   ========================================================================== */

function gv_spd_capture_query_owner( $sql ) {
	if ( ! empty( $GLOBALS['gv_spd_profiling'] ) ) {
		$GLOBALS['gv_spd_query_owners'][] = gv_spd_resolve_caller_owner( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 ) ); // phpcs:ignore
	}
	return $sql;
}

/* ==========================================================================
   ۶) ردیابی درخواست‌های خروجی (HTTP) کند
   ========================================================================== */

function gv_spd_http_start( $args, $url ) {
	if ( empty( $GLOBALS['gv_spd_profiling'] ) ) { return $args; }
	$GLOBALS['gv_spd_http_starts'][ $url ][] = array(
		'time'  => microtime( true ),
		'owner' => gv_spd_resolve_caller_owner( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 ) ), // phpcs:ignore
	);
	return $args;
}

function gv_spd_http_end( $response, $context, $class, $args, $url ) {
	if ( empty( $GLOBALS['gv_spd_profiling'] ) ) { return; }
	if ( empty( $GLOBALS['gv_spd_http_starts'][ $url ] ) ) { return; }

	$start   = array_shift( $GLOBALS['gv_spd_http_starts'][ $url ] );
	$elapsed = microtime( true ) - $start['time'];

	if ( ! isset( $GLOBALS['gv_spd_http_log'] ) ) { $GLOBALS['gv_spd_http_log'] = array(); }
	$GLOBALS['gv_spd_http_log'][] = array(
		'url'     => $url,
		'host'    => (string) wp_parse_url( $url, PHP_URL_HOST ),
		'elapsed' => $elapsed,
		'owner'   => $start['owner'],
	);
}

/* ==========================================================================
   ۷) تشخیص مالکِ یک فایل (افزونه / قالب / MU-Plugin / هسته)
   ========================================================================== */

function gv_spd_path_to_owner( $file ) {
	static $plugin_dir = null, $theme_root = null, $mu_dir = null, $abspath_admin = null, $abspath_includes = null;
	if ( null === $plugin_dir ) {
		$plugin_dir       = wp_normalize_path( WP_PLUGIN_DIR );
		$theme_root       = wp_normalize_path( get_theme_root() );
		$mu_dir           = defined( 'WPMU_PLUGIN_DIR' ) ? wp_normalize_path( WPMU_PLUGIN_DIR ) : '';
		$abspath_admin    = wp_normalize_path( ABSPATH . 'wp-admin' );
		$abspath_includes = wp_normalize_path( ABSPATH . 'wp-includes' );
	}

	$file = wp_normalize_path( $file );

	if ( $plugin_dir && 0 === strpos( $file, $plugin_dir . '/' ) ) {
		$rel    = substr( $file, strlen( $plugin_dir ) + 1 );
		$folder = strstr( $rel, '/', true );
		return array( 'type' => 'plugin', 'slug' => $folder ?: $rel );
	}
	if ( $mu_dir && 0 === strpos( $file, $mu_dir . '/' ) ) {
		return array( 'type' => 'mu-plugin', 'slug' => basename( $file ) );
	}
	if ( $theme_root && 0 === strpos( $file, $theme_root . '/' ) ) {
		$rel    = substr( $file, strlen( $theme_root ) + 1 );
		$folder = strstr( $rel, '/', true );
		return array( 'type' => 'theme', 'slug' => $folder ?: $rel );
	}
	if ( 0 === strpos( $file, $abspath_admin ) || 0 === strpos( $file, $abspath_includes ) ) {
		return null; // هسته؛ ادامه بده به دنبال یک فریم مشخص‌تر بگرد
	}
	return null;
}

/** فایل تعریف‌کننده‌ی یک تابع/متد/کلوژر را با Reflection برمی‌گرداند. */
function gv_spd_callable_file( $function ) {
	try {
		if ( is_string( $function ) && false !== strpos( $function, '::' ) ) {
			list( $class, $method ) = explode( '::', $function, 2 );
			$ref = new ReflectionMethod( $class, $method );
		} elseif ( is_array( $function ) && 2 === count( $function ) ) {
			$ref = new ReflectionMethod( $function[0], $function[1] );
		} elseif ( $function instanceof Closure ) {
			$ref = new ReflectionFunction( $function );
		} elseif ( is_string( $function ) && function_exists( $function ) ) {
			$ref = new ReflectionFunction( $function );
		} elseif ( is_object( $function ) && method_exists( $function, '__invoke' ) ) {
			$ref = new ReflectionMethod( $function, '__invoke' );
		} else {
			return null;
		}
		$file = $ref->getFileName();
		return $file ?: null;
	} catch ( Throwable $e ) {
		return null;
	}
}

/** از روی یک بک‌تریس، اولین فریمِ متعلق به افزونه/قالب را پیدا می‌کند (نزدیک‌ترین مقصر). */
function gv_spd_resolve_caller_owner( $trace ) {
	static $file_cache = array();
	foreach ( $trace as $frame ) {
		if ( empty( $frame['file'] ) ) { continue; }
		$file = wp_normalize_path( $frame['file'] );
		if ( ! array_key_exists( $file, $file_cache ) ) {
			$file_cache[ $file ] = gv_spd_path_to_owner( $file );
		}
		if ( $file_cache[ $file ] ) { return $file_cache[ $file ]; }
	}
	return array( 'type' => 'core', 'slug' => '' );
}

/* ==========================================================================
   ۸) پایان درخواست: جمع‌بندی، تشخیص مقصر و ثبت در لاگ
   ========================================================================== */

function gv_spd_on_shutdown() {
	if ( empty( $GLOBALS['gv_spd_profiling'] ) ) { return; }

	$s     = $GLOBALS['gv_spd_settings'];
	$end   = microtime( true );
	$total = $end - $GLOBALS['gv_spd_boot_ref'];

	if ( $total < floatval( $s['threshold'] ) ) { return; } // فقط لودهای کند ثبت می‌شوند

	$segments = gv_spd_compute_segments( $GLOBALS['gv_spd_boot_ref'], $GLOBALS['gv_spd_marks'], $end );

	/* ---- کوئری‌های کند ---- */
	global $wpdb;
	$slow_queries     = array();
	$total_query_time = 0.0;
	$query_count      = 0;
	$offset           = isset( $GLOBALS['gv_spd_query_offset'] ) ? $GLOBALS['gv_spd_query_offset'] : 0;

	if ( ! empty( $wpdb->queries ) ) {
		$query_count = count( $wpdb->queries );
		foreach ( $wpdb->queries as $i => $q ) {
			$dur = isset( $q[1] ) ? (float) $q[1] : 0.0;
			$total_query_time += $dur;
			if ( $dur >= floatval( $s['query_threshold'] ) ) {
				$owner_index = $i - $offset;
				$owner = ( $owner_index >= 0 && isset( $GLOBALS['gv_spd_query_owners'][ $owner_index ] ) )
					? $GLOBALS['gv_spd_query_owners'][ $owner_index ]
					: array( 'type' => 'core', 'slug' => '' );
				$slow_queries[] = array(
					'sql'   => gv_spd_truncate_sql( $q[0] ),
					'time'  => round( $dur, 4 ),
					'owner' => $owner,
				);
			}
		}
	}
	usort( $slow_queries, function ( $a, $b ) { return $b['time'] <=> $a['time']; } );
	$slow_queries = array_slice( $slow_queries, 0, 10 );

	/* ---- درخواست‌های خروجی کند ---- */
	$http_log  = $GLOBALS['gv_spd_http_log'] ?? array();
	$http_time = 0.0;
	foreach ( $http_log as $h ) { $http_time += $h['elapsed']; }
	$slow_http = array_values( array_filter( $http_log, function ( $h ) use ( $s ) {
		return $h['elapsed'] >= floatval( $s['http_threshold'] );
	} ) );
	usort( $slow_http, function ( $a, $b ) { return $b['elapsed'] <=> $a['elapsed']; } );
	$slow_http = array_slice( $slow_http, 0, 10 );

	/* ---- رتبه‌بندی هوک‌ها (فقط اگر حالت عمیق فعال بوده) ---- */
	$deep_ranking = array();
	if ( ! empty( $GLOBALS['gv_spd_deep_totals'] ) ) {
		$deep_ranking = $GLOBALS['gv_spd_deep_totals'];
		arsort( $deep_ranking );
		$deep_ranking = array_slice( $deep_ranking, 0, 8, true );
		foreach ( $deep_ranking as $k => $v ) { $deep_ranking[ $k ] = round( $v, 4 ); }
	}

	$entry = array(
		'time'         => time(),
		'url'          => gv_spd_current_url(),
		'is_admin'     => is_admin() ? 1 : 0,
		'total'        => round( $total, 3 ),
		'segments'     => $segments,
		'query_count'  => $query_count,
		'query_time'   => round( $total_query_time, 3 ),
		'slow_queries' => $slow_queries,
		'http_count'   => count( $http_log ),
		'http_time'    => round( $http_time, 3 ),
		'slow_http'    => $slow_http,
		'memory_peak'  => memory_get_peak_usage( true ),
		'plugin_count' => count( (array) get_option( 'active_plugins', array() ) ),
		'deep_active'  => ! empty( $GLOBALS['gv_spd_deep_active'] ) || ! empty( $GLOBALS['gv_spd_deep_truncated'] ),
		'deep_ranking' => $deep_ranking,
	);
	$entry['culprit'] = gv_spd_guess_culprit( $entry );

	gv_spd_append_log( $entry, intval( $s['max_log_entries'] ) );
}

function gv_spd_truncate_sql( $sql ) {
	$sql = preg_replace( '/\s+/', ' ', trim( (string) $sql ) );
	return ( strlen( $sql ) > 220 ) ? ( substr( $sql, 0, 220 ) . ' …' ) : $sql;
}

function gv_spd_current_url() {
	$scheme = is_ssl() ? 'https://' : 'http://';
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return $scheme . $host . $uri;
}

/** زمانِ سپری‌شده در هر مرحله‌ی بوت را از روی نقاط علامت‌گذاری‌شده محاسبه می‌کند. */
function gv_spd_compute_segments( $boot, $marks, $end ) {
	$labels = array(
		'preboot'        => 'قبل از افزونه‌ها (هسته وردپرس + بارگذاری فایل‌های افزونه‌ها)',
		'plugins_loaded' => 'راه‌اندازی اولیه افزونه‌ها',
		'theme_setup'    => 'راه‌اندازی قالب',
		'init'           => 'هوک init (ثبت پست‌تایپ / شورت‌کد / API)',
		'main_query'     => 'پردازش درخواست و کوئری اصلی',
		'render'         => 'رندر خروجی نهایی',
	);
	$mark_keys = array( 'plugins_loaded', 'after_setup_theme', 'init', 'wp_loaded', 'template_redirect' );

	$points = array( $boot );
	$last   = $boot;
	foreach ( $mark_keys as $k ) {
		$val    = isset( $marks[ $k ] ) ? $marks[ $k ] : $last;
		$val    = max( $val, $last ); // یکنواخت نگه‌داشتن ترتیب زمانی
		$points[] = $val;
		$last   = $val;
	}
	$points[] = max( $end, $last );

	$seg_keys = array_keys( $labels );
	$segments = array();
	for ( $i = 0; $i < count( $seg_keys ); $i++ ) {
		$segments[ $seg_keys[ $i ] ] = array(
			'label' => $labels[ $seg_keys[ $i ] ],
			'time'  => round( $points[ $i + 1 ] - $points[ $i ], 4 ),
		);
	}
	return $segments;
}

/* ==========================================================================
   ۹) تشخیص مقصر احتمالی (پیشنهاد به زبان ساده)
   ========================================================================== */

function gv_spd_guess_culprit( $e ) {
	$reasons = array();
	$total   = max( $e['total'], 0.001 );

	$seg_messages = array(
		'preboot'        => 'بیشترین زمان صرف بارگذاری هسته وردپرس و فایل‌های افزونه‌ها شد؛ این معمولاً یعنی هاست کند است یا تعداد افزونه‌های فعال زیاد است.',
		'plugins_loaded' => 'بیشترین زمان در مرحلهٔ راه‌اندازی اولیهٔ افزونه‌ها صرف شد؛ یکی از افزونه‌ها همین ابتدای کار وقت زیادی می‌گیرد.',
		'theme_setup'    => 'بیشترین زمان صرف بارگذاری و راه‌اندازی قالب سایت شد.',
		'init'           => 'بیشترین زمان در هوک init صرف شد؛ معمولاً یعنی یکی از افزونه‌ها یا قالب هنگام ثبت پست‌تایپ/تاکسونومی/اتصال به API کار سنگینی انجام می‌دهد.',
		'main_query'     => 'بیشترین زمان صرف پردازش کوئری اصلی صفحه شد.',
		'render'         => 'بیشترین زمان صرف رندر قالب، ویجت‌ها و شورت‌کدها هنگام نمایش صفحه شد.',
	);

	$seg_vals = array();
	foreach ( $e['segments'] as $key => $seg ) { $seg_vals[ $key ] = $seg['time']; }
	arsort( $seg_vals );
	$top_key = array_key_first( $seg_vals );
	$top_pct = round( ( $seg_vals[ $top_key ] / $total ) * 100 );
	if ( $top_pct >= 30 && isset( $seg_messages[ $top_key ] ) ) {
		$reasons[] = array( 'pct' => $top_pct, 'text' => '⏱ ' . $top_pct . '٪ از زمان: ' . $seg_messages[ $top_key ] );
	}

	if ( ! empty( $e['slow_queries'] ) ) {
		$q   = $e['slow_queries'][0];
		$who = gv_spd_owner_label( $q['owner'] );
		$reasons[] = array( 'pct' => null, 'text' => '🗄 یک کوئری دیتابیس کند (' . $q['time'] . ' ثانیه) شناسایی شد که به ' . $who . ' مربوط است.' );
	}

	if ( ! empty( $e['slow_http'] ) ) {
		$h   = $e['slow_http'][0];
		$who = gv_spd_owner_label( $h['owner'] );
		$reasons[] = array( 'pct' => null, 'text' => '🌐 درخواست خروجی به «' . $h['host'] . '» ' . round( $h['elapsed'], 2 ) . ' ثانیه طول کشید (توسط ' . $who . ')؛ این معمولاً مشکل سرویسِ بیرونی است، نه خود سایت.' );
	}

	if ( ! empty( $e['deep_ranking'] ) ) {
		$first_key = array_key_first( $e['deep_ranking'] );
		$first_val = $e['deep_ranking'][ $first_key ];
		if ( ( $first_val / $total ) > 0.15 ) {
			$reasons[] = array(
				'pct'  => round( ( $first_val / $total ) * 100 ),
				'text' => '🔬 در حالت عمیق، بیشترین زمان اجرای هوک‌ها مربوط به ' . gv_spd_owner_label_from_key( $first_key ) . ' بود.',
			);
		}
	}

	if ( empty( $reasons ) ) {
		$reasons[] = array( 'pct' => null, 'text' => 'زمان بین چند بخش مختلف پخش شده و یک عامل غالب مشخص نیست. برای تشخیص دقیق‌تر، «حالت عمیق» را از تب تنظیمات فعال کنید.' );
	}

	return $reasons;
}

function gv_spd_owner_label( $owner ) {
	if ( empty( $owner ) || empty( $owner['type'] ) || 'core' === $owner['type'] ) { return 'هسته وردپرس'; }
	if ( 'plugin' === $owner['type'] ) { return 'افزونهٔ «' . gv_spd_plugin_pretty_name( $owner['slug'] ) . '»'; }
	if ( 'theme' === $owner['type'] ) { return 'قالب سایت («' . $owner['slug'] . '»)'; }
	if ( 'mu-plugin' === $owner['type'] ) { return 'MU-Plugin («' . $owner['slug'] . '»)'; }
	return 'نامشخص';
}

function gv_spd_owner_label_from_key( $key ) {
	if ( 'core' === $key || '' === $key ) { return 'هسته وردپرس'; }
	$parts = explode( ':', $key, 2 );
	return gv_spd_owner_label( array( 'type' => $parts[0], 'slug' => $parts[1] ?? '' ) );
}

function gv_spd_plugin_pretty_name( $slug ) {
	static $map = null;
	if ( null === $map ) {
		if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$map = array();
		foreach ( get_plugins() as $path => $data ) {
			$folder = strstr( $path, '/', true );
			$map[ $folder ?: $path ] = $data['Name'];
		}
	}
	return isset( $map[ $slug ] ) ? $map[ $slug ] : $slug;
}

/* ==========================================================================
   ۱۰) ذخیره‌سازی لاگ چرخشی
   ========================================================================== */

function gv_spd_append_log( $entry, $max = 30 ) {
	$log = get_option( GV_SPD_LOG_OPT, array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	array_unshift( $log, $entry );
	$max = max( 5, $max );
	if ( count( $log ) > $max ) { $log = array_slice( $log, 0, $max ); }
	update_option( GV_SPD_LOG_OPT, $log, false );
}

/* ==========================================================================
   ۱۱) صفحه مدیریت
   ========================================================================== */

function gv_spd_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$s    = gv_spd_get_settings();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'log';
	$log  = get_option( GV_SPD_LOG_OPT, array() );
	if ( ! is_array( $log ) ) { $log = array(); }

	$count_entries = count( $log );
	$avg_total     = $count_entries ? array_sum( array_column( $log, 'total' ) ) / $count_entries : 0;
	$worst         = $count_entries ? max( array_column( $log, 'total' ) ) : 0;
	?>
	<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif;">

		<style>
			.gvspd-header{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,#7c2d12,#ea580c);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;box-shadow:0 6px 20px rgba(0,0,0,.12);}
			.gvspd-header h1{margin:0;font-size:20px;color:#fff;}
			.gvspd-header span{opacity:.85;font-size:12.5px;}
			.gvspd-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap;}
			.gvspd-tab-btn{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:9px 18px;cursor:pointer;font-size:13px;font-family:inherit;font-weight:600;transition:.15s;}
			.gvspd-tab-btn.is-active{background:#7c2d12;color:#fff;border-color:#7c2d12;}
			.gvspd-tab-panel{display:none;}
			.gvspd-tab-panel.is-active{display:block;}
			.gvspd-card{background:#fff;border:1px solid #e2e4e7;border-radius:12px;padding:20px 22px;margin-bottom:18px;box-shadow:0 1px 3px rgba(0,0,0,.04);max-width:980px;}
			.gvspd-card h2{margin-top:0;font-size:15px;display:flex;align-items:center;gap:8px;border-bottom:1px solid #f0f0f1;padding-bottom:10px;}
			.gvspd-hint{color:#666;font-size:12px;margin-top:6px;line-height:1.9;}
			.gvspd-toggle-row{display:flex;align-items:center;gap:8px;background:#f6f7f7;padding:10px 14px;border-radius:8px;margin-bottom:10px;}
			.gvspd-toggle-row label{margin:0;font-weight:600;font-size:13px;}
			.gvspd-field-row{margin-bottom:14px;max-width:420px;}
			.gvspd-field-row label{display:block;font-weight:600;font-size:12.5px;margin-bottom:5px;}
			.gvspd-field-row input[type=number]{width:120px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;}
			.gvspd-note{background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:12px 16px;border-radius:10px;font-size:12.5px;line-height:1.9;margin-bottom:18px;max-width:980px;}
			.gvspd-note.gvspd-note-blue{background:#eff6ff;border-color:#93c5fd;color:#1e40af;}
			.gvspd-stat-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;max-width:980px;}
			.gvspd-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px;}
			.gvspd-stat b{display:block;font-size:24px;color:#ea580c;}
			.gvspd-stat span{font-size:12.5px;color:#64748b;}
			.gvspd-log-table{width:100%;max-width:980px;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;}
			.gvspd-log-table th{padding:10px 12px;text-align:right;font-size:12px;background:#f8fafc;color:#475569;}
			.gvspd-log-row{cursor:pointer;border-top:1px solid #f1f5f9;}
			.gvspd-log-row:hover{background:#fafafa;}
			.gvspd-log-row td{padding:10px 12px;font-size:12.5px;vertical-align:middle;}
			.gvspd-log-row code{direction:ltr;display:inline-block;font-size:11.5px;color:#334155;}
			.gvspd-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;color:#fff;}
			.gvspd-expand-arrow{display:inline-block;transition:.15s;color:#94a3b8;}
			.gvspd-expand-arrow.is-open{transform:rotate(90deg);}
			.gvspd-log-detail{display:none;background:#f9fafb;padding:18px 22px;border-top:1px solid #f1f5f9;}
			.gvspd-log-detail.force-open{display:table-row;}
			.gvspd-seg-bar-wrap{display:flex;height:26px;border-radius:8px;overflow:hidden;margin:10px 0 6px;max-width:820px;}
			.gvspd-seg-bar{height:100%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10.5px;overflow:hidden;white-space:nowrap;}
			.gvspd-seg-legend{display:flex;flex-wrap:wrap;gap:10px 18px;font-size:11.5px;color:#475569;margin-bottom:16px;}
			.gvspd-seg-legend span.dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-left:5px;}
			.gvspd-culprit{background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;padding:12px 16px;border-radius:10px;font-size:12.5px;line-height:2;margin-bottom:16px;}
			.gvspd-mini-table{width:100%;border-collapse:collapse;font-size:11.5px;margin-bottom:14px;}
			.gvspd-mini-table th,.gvspd-mini-table td{padding:6px 8px;text-align:right;border-bottom:1px solid #eef1f4;}
			.gvspd-mini-table th{color:#64748b;}
			.gvspd-mini-table code{direction:ltr;display:inline-block;background:#fef2f2;color:#991b1b;padding:2px 7px;border-radius:6px;}
			.gvspd-empty{padding:40px;text-align:center;color:#94a3b8;background:#fff;border:1px dashed #e2e4e7;border-radius:12px;max-width:980px;}
			.gvspd-btn-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:8px 16px;font-size:12.5px;cursor:pointer;font-weight:600;}
		</style>

		<div class="gvspd-header">
			<h1>🐢 پروفایلر سرعت صفحه — Groot Vision</h1>
			<span>تشخیص خودکار اینکه کندی هر بارگذاری صفحه از کجاست: افزونه، قالب، دیتابیس، سرویس بیرونی یا هاست</span>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>✅ تنظیمات با موفقیت ذخیره شد.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['cleared'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>🧹 لاگ پاک شد.</p></div>
		<?php endif; ?>

		<div class="gvspd-tabs">
			<button type="button" class="gvspd-tab-btn <?php echo 'log' === $tab ? 'is-active' : ''; ?>" data-tab="log">📋 لاگ صفحات کند</button>
			<button type="button" class="gvspd-tab-btn <?php echo 'settings' === $tab ? 'is-active' : ''; ?>" data-tab="settings">⚙️ تنظیمات</button>
		</div>

		<!-- تب لاگ -->
		<div class="gvspd-tab-panel <?php echo 'log' === $tab ? 'is-active' : ''; ?>" id="gvspd-tab-log" <?php echo 'log' === $tab ? '' : 'style="display:none;"'; ?>>

			<?php if ( empty( $s['enabled'] ) ) : ?>
				<div class="gvspd-note">⚠️ پروفایلر در حال حاضر از تب «تنظیمات» غیرفعال است؛ لاگ جدیدی ثبت نمی‌شود.</div>
			<?php endif; ?>

			<div class="gvspd-stat-cards">
				<div class="gvspd-stat"><b><?php echo (int) $count_entries; ?></b><span>تعداد لودهای کند ثبت‌شده</span></div>
				<div class="gvspd-stat"><b><?php echo esc_html( number_format_i18n( $avg_total, 2 ) ); ?>s</b><span>میانگین زمان لودهای ثبت‌شده</span></div>
				<div class="gvspd-stat"><b><?php echo esc_html( number_format_i18n( $worst, 2 ) ); ?>s</b><span>کندترین لود ثبت‌شده</span></div>
			</div>

			<?php if ( empty( $log ) ) : ?>
				<div class="gvspd-empty">
					هنوز هیچ لود کندی ثبت نشده 🎉<br>
					<span style="font-size:12px;">به‌محض اینکه یک بازدید کندتر از آستانه‌ی تعیین‌شده رخ دهد، اینجا نمایش داده می‌شود.</span>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px;">
					<input type="hidden" name="action" value="gv_spd_clear_log">
					<?php wp_nonce_field( GV_SPD_NONCE ); ?>
					<button type="submit" class="gvspd-btn-danger" onclick="return confirm('لاگ کامل پاک شود؟');">🧹 پاک کردن کل لاگ</button>
				</form>

				<table class="gvspd-log-table">
					<thead>
						<tr>
							<th style="width:24px;"></th>
							<th>زمان</th>
							<th>آدرس صفحه</th>
							<th>کل زمان</th>
							<th>کوئری‌ها</th>
							<th>خروجی HTTP</th>
							<th>مقصر محتمل</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $log as $entry ) :
						$sev_color = $entry['total'] >= ( 2 * floatval( $s['threshold'] ) ) ? '#dc2626' : '#f59e0b';
						?>
						<tr class="gvspd-log-row">
							<td><span class="gvspd-expand-arrow">▶</span></td>
							<td><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $entry['time'] ) ); ?></td>
							<td><code><?php echo esc_html( gv_spd_short_url( $entry['url'] ) ); ?></code><?php echo ! empty( $entry['is_admin'] ) ? ' <span style="color:#94a3b8;">(پیشخوان)</span>' : ''; ?></td>
							<td><span class="gvspd-badge" style="background:<?php echo esc_attr( $sev_color ); ?>;"><?php echo esc_html( $entry['total'] ); ?>s</span></td>
							<td><?php echo (int) $entry['query_count']; ?> (<?php echo esc_html( $entry['query_time'] ); ?>s)</td>
							<td><?php echo (int) $entry['http_count']; ?> (<?php echo esc_html( $entry['http_time'] ); ?>s)</td>
							<td style="max-width:260px;"><?php echo esc_html( gv_spd_first_reason_short( $entry['culprit'] ) ); ?></td>
						</tr>
						<tr class="gvspd-log-detail">
							<td colspan="7">
								<div class="gvspd-culprit">
									<?php foreach ( $entry['culprit'] as $r ) : ?>
										<div><?php echo esc_html( $r['text'] ); ?></div>
									<?php endforeach; ?>
								</div>

								<strong style="font-size:12.5px;">تفکیک زمانی مراحل بارگذاری:</strong>
								<?php echo gv_spd_render_segment_bar( $entry['segments'], $entry['total'] ); // phpcs:ignore ?>

								<?php if ( ! empty( $entry['slow_queries'] ) ) : ?>
									<strong style="font-size:12.5px;">کوئری‌های کند دیتابیس:</strong>
									<table class="gvspd-mini-table">
										<thead><tr><th>زمان</th><th>مسئول</th><th>کوئری</th></tr></thead>
										<tbody>
										<?php foreach ( $entry['slow_queries'] as $q ) : ?>
											<tr><td><?php echo esc_html( $q['time'] ); ?>s</td><td><?php echo esc_html( gv_spd_owner_label( $q['owner'] ) ); ?></td><td><code><?php echo esc_html( $q['sql'] ); ?></code></td></tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>

								<?php if ( ! empty( $entry['slow_http'] ) ) : ?>
									<strong style="font-size:12.5px;">درخواست‌های خروجی کند:</strong>
									<table class="gvspd-mini-table">
										<thead><tr><th>زمان</th><th>مسئول</th><th>مقصد</th></tr></thead>
										<tbody>
										<?php foreach ( $entry['slow_http'] as $h ) : ?>
											<tr><td><?php echo esc_html( round( $h['elapsed'], 3 ) ); ?>s</td><td><?php echo esc_html( gv_spd_owner_label( $h['owner'] ) ); ?></td><td><code><?php echo esc_html( $h['host'] ); ?></code></td></tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>

								<?php if ( ! empty( $entry['deep_ranking'] ) ) : ?>
									<strong style="font-size:12.5px;">رتبه‌بندی زمان اجرای هوک‌ها (حالت عمیق):</strong>
									<table class="gvspd-mini-table">
										<thead><tr><th>زمان</th><th>مسئول</th></tr></thead>
										<tbody>
										<?php foreach ( $entry['deep_ranking'] as $key => $val ) : ?>
											<tr><td><?php echo esc_html( $val ); ?>s</td><td><?php echo esc_html( gv_spd_owner_label_from_key( $key ) ); ?></td></tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>

								<p class="gvspd-hint" style="margin-top:6px;">
									حافظه‌ی پیک: <?php echo esc_html( size_format( $entry['memory_peak'] ) ); ?> —
									تعداد افزونه‌های فعال سایت: <?php echo (int) $entry['plugin_count']; ?> —
									<?php echo ! empty( $entry['deep_active'] ) ? 'حالت عمیق فعال بود' : 'حالت عمیق فعال نبود (برای تخمین دقیق‌تر افزونه مقصر، آن را از تنظیمات روشن کنید)'; ?>
								</p>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- تب تنظیمات -->
		<div class="gvspd-tab-panel <?php echo 'settings' === $tab ? 'is-active' : ''; ?>" id="gvspd-tab-settings" <?php echo 'settings' === $tab ? '' : 'style="display:none;"'; ?>>

			<div class="gvspd-note gvspd-note-blue">
				💡 این ابزار در پس‌زمینه‌ی بازدیدهای واقعی کاربران کار می‌کند (نه یک تست مصنوعی)، فقط زمانی که یک صفحه کندتر از «آستانهٔ ثبت لاگ» لود شود، جزئیات آن ذخیره می‌شود. «حالت عمیق» دقیق‌تر است ولی سربار پردازشی بیشتری دارد؛ توصیه می‌شود فقط هنگام بررسی یک مشکل خاص، و ترجیحاً فقط برای خودِ مدیر سایت، روشن شود.
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gv_spd_save_settings">
				<?php wp_nonce_field( GV_SPD_NONCE ); ?>

				<div class="gvspd-card">
					<h2>وضعیت کلی</h2>
					<div class="gvspd-toggle-row">
						<input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>>
						<label>فعال‌سازی پروفایلر سرعت صفحه</label>
					</div>
					<div class="gvspd-toggle-row">
						<input type="checkbox" name="track_frontend" <?php checked( $s['track_frontend'], 1 ); ?>>
						<label>بررسی صفحات فرانت (بازدید‌کنندگان سایت)</label>
					</div>
					<div class="gvspd-toggle-row">
						<input type="checkbox" name="track_admin" <?php checked( $s['track_admin'], 1 ); ?>>
						<label>بررسی صفحات پیشخوان مدیریت</label>
					</div>
					<div class="gvspd-toggle-row">
						<input type="checkbox" name="track_ajax_rest_cron" <?php checked( $s['track_ajax_rest_cron'], 1 ); ?>>
						<label>بررسی درخواست‌های AJAX / REST API / Cron</label>
					</div>
				</div>

				<div class="gvspd-card">
					<h2>آستانه‌ها</h2>
					<div class="gvspd-field-row">
						<label>یک بارگذاری صفحه از چند ثانیه به بعد «کند» و در لاگ ثبت شود؟</label>
						<input type="number" step="0.1" min="0.2" name="threshold" value="<?php echo esc_attr( $s['threshold'] ); ?>"> ثانیه
					</div>
					<div class="gvspd-field-row">
						<label>یک کوئری دیتابیس از چند ثانیه به بعد «کند» حساب شود؟</label>
						<input type="number" step="0.01" min="0.01" name="query_threshold" value="<?php echo esc_attr( $s['query_threshold'] ); ?>"> ثانیه
					</div>
					<div class="gvspd-field-row">
						<label>یک درخواست خروجی (HTTP) از چند ثانیه به بعد «کند» حساب شود؟</label>
						<input type="number" step="0.1" min="0.1" name="http_threshold" value="<?php echo esc_attr( $s['http_threshold'] ); ?>"> ثانیه
					</div>
					<div class="gvspd-field-row">
						<label>حداکثر تعداد رکورد نگه‌داشته‌شده در لاگ</label>
						<input type="number" step="1" min="5" max="200" name="max_log_entries" value="<?php echo esc_attr( $s['max_log_entries'] ); ?>">
					</div>
				</div>

				<div class="gvspd-card">
					<h2>نرخ نمونه‌برداری</h2>
					<p class="gvspd-hint">اگر سایت پربازدید است، بررسی همه بازدیدها می‌تواند کمی سربار اضافه کند. با کاهش این درصد، فقط بخشی از بازدیدهای واجد شرایط بررسی می‌شوند (نتیجه همچنان آماری قابل‌اعتماد خواهد بود).</p>
					<div class="gvspd-field-row">
						<label>درصد بازدیدهایی که بررسی شوند</label>
						<input type="number" step="1" min="1" max="100" name="sample_rate" value="<?php echo esc_attr( $s['sample_rate'] ); ?>">٪
					</div>
				</div>

				<div class="gvspd-card">
					<h2>حالت عمیق (رتبه‌بندی افزونه‌ها)</h2>
					<div class="gvspd-note" style="margin-bottom:14px;">⚠️ حالت عمیق با ردیابی تک‌تک هوک‌های وردپرس، تخمین می‌زند کدام افزونه بیشترین زمان پردازش را مصرف کرده؛ دقیق‌تر است ولی سرعت خودِ همان بازدید را کمی کندتر می‌کند. برای استفادهٔ روزمره لازم نیست روشن باشد؛ فقط برای عیب‌یابی یک مشکل خاص آن را موقتاً فعال کنید.</div>
					<div class="gvspd-toggle-row">
						<input type="checkbox" name="deep_mode" <?php checked( $s['deep_mode'], 1 ); ?>>
						<label>فعال‌سازی حالت عمیق</label>
					</div>
					<div class="gvspd-toggle-row">
						<input type="checkbox" name="deep_mode_admin_only" <?php checked( $s['deep_mode_admin_only'], 1 ); ?>>
						<label>حالت عمیق فقط وقتی خودم (مدیر سایت) لاگین هستم اجرا شود، نه برای بازدیدکننده‌های عادی</label>
					</div>
				</div>

				<p><button type="submit" class="button button-primary">ذخیره تنظیمات</button></p>
			</form>
		</div>

		<p class="gvsec-credit" style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">Groot Vision Tools — پروفایلر سرعت صفحه</p>
	</div>
	<?php
}

function gv_spd_short_url( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	$out = $path ?: '/';
	if ( $query ) { $out .= '?' . ( strlen( $query ) > 40 ? substr( $query, 0, 40 ) . '…' : $query ); }
	return $out;
}

function gv_spd_first_reason_short( $reasons ) {
	if ( empty( $reasons ) ) { return '—'; }
	$text = $reasons[0]['text'];
	return ( strlen( $text ) > 90 ) ? ( substr( $text, 0, 90 ) . '…' ) : $text;
}

/** نوار رنگی افقی که سهم هر مرحله از کل زمان را نشان می‌دهد. */
function gv_spd_render_segment_bar( $segments, $total ) {
	$colors = array(
		'preboot'        => '#64748b',
		'plugins_loaded' => '#ea580c',
		'theme_setup'    => '#7c3aed',
		'init'           => '#0891b2',
		'main_query'     => '#16a34a',
		'render'         => '#dc2626',
	);
	$total = max( $total, 0.001 );

	$bar    = '<div class="gvspd-seg-bar-wrap">';
	$legend = '<div class="gvspd-seg-legend">';
	foreach ( $segments as $key => $seg ) {
		$pct = max( 0, round( ( $seg['time'] / $total ) * 100, 1 ) );
		if ( $pct <= 0 ) { continue; }
		$color = $colors[ $key ] ?? '#94a3b8';
		$bar   .= '<div class="gvspd-seg-bar" style="width:' . esc_attr( $pct ) . '%;background:' . esc_attr( $color ) . ';" title="' . esc_attr( $seg['label'] . ' — ' . $seg['time'] . 's' ) . '">' . ( $pct >= 8 ? esc_html( $pct . '٪' ) : '' ) . '</div>';
		$legend .= '<div><span class="dot" style="background:' . esc_attr( $color ) . ';"></span>' . esc_html( $seg['label'] ) . ' (' . esc_html( $seg['time'] ) . 's)</div>';
	}
	$bar    .= '</div>';
	$legend .= '</div>';

	return $bar . $legend;
}