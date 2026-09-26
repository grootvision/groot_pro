<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — آنالیز سئو و سرعت کل سایت (SEO Site Audit)
 *  ------------------------------------------------------------
 *  این ماژول کل سایت (نوشته‌ها، برگه‌ها، محصولات و هر پست‌تایپ
 *  دیگری که مدیر انتخاب کند) را صفحه‌به‌صفحه دانلود و آنالیز
 *  می‌کند و برای هرچیزی که ممکن است به سئو یا سرعت آسیب بزند،
 *  یک «مشکل» با آدرس دقیقِ همان صفحه ثبت می‌کند:
 *
 *    - نبود یا تکرار H1
 *    - تعداد کلمات کم محتوا
 *    - نبود/کوتاه یا بلند بودن تگ Title
 *    - نبود/کوتاه یا بلند بودن متا دیسکریپشن
 *    - آدرس (URL) غیر سئوپسند (حروف بزرگ، آندرلاین، پارامتر ?p=،
 *      فاصله، کاراکتر عجیب، طول زیاد)
 *    - noindex ناخواسته / نبود لینک canonical
 *    - تصاویر بدون متن جایگزین (Alt)
 *    - عنوان/توضیحات تکراری بین چند صفحه
 *    - سرعت لود خودِ صفحه (نسبت به آستانه‌ی تعیین‌شده)
 *    - لینک‌های داخلی خراب (۴۰۴ و ...) با ذکر اینکه در کدام صفحه است
 *    - فایل‌های CSS/JS/تصویر کند یا سنگین، همراه با نام افزونه یا
 *      قالبی که آن فایل متعلق به آن است (تا دقیقاً معلوم شود کدام
 *      افزونه باعث کندی سایت شده)
 *
 *  اسکن کل سایت ممکن است چند دقیقه طول بکشد، پس به‌صورت دسته‌ای
 *  (batch) و از طریق AJAX انجام می‌شود؛ صفحه‌ی مدیریت با یک نوار
 *  پیشرفت زنده وضعیت را نشان می‌دهد و می‌توان هر زمان متوقفش کرد.
 * ==========================================================
 */

define( 'GV_AUDIT_OPT',          'gv_seo_audit_settings' );
define( 'GV_AUDIT_STATE_OPT',    'gv_seo_audit_state' );      // وضعیت لحظه‌ای اسکن (صف، فاز، پیشرفت)
define( 'GV_AUDIT_DB_VERSION',   '1.0' );
define( 'GV_AUDIT_NONCE',        'gv_audit_nonce_action' );
define( 'GV_AUDIT_PAGE_SLUG',    'gv-seo-audit' );

/* ==========================================================================
   ۰) تنظیمات پیش‌فرض
   ========================================================================== */
function gv_audit_default_settings() {
	return array(
		'post_types'        => array( 'post', 'page' ),
		'max_pages'         => 300,
		'batch_pages'       => 3,
		'batch_links'       => 8,
		'batch_assets'      => 6,
		'min_words'         => 300,
		'title_min'         => 30,
		'title_max'         => 60,
		'desc_min'          => 70,
		'desc_max'          => 160,
		'speed_warn'        => 2.0,
		'speed_error'       => 4.0,
		'asset_time_warn'   => 1.0,
		'asset_size_warn_kb'=> 500,
		'check_broken_links'=> 1,
		'check_assets'      => 1,
		'exclude_contains'  => '',
	);
}
function gv_audit_get_settings() {
	$s = wp_parse_args( get_option( GV_AUDIT_OPT, array() ), gv_audit_default_settings() );
	if ( ! is_array( $s['post_types'] ) ) { $s['post_types'] = array( 'post', 'page' ); }
	return $s;
}

/* ==========================================================================
   ۱) جدول دیتابیس مشکلات یافته‌شده
   ========================================================================== */
add_action( 'plugins_loaded', 'gv_audit_maybe_install_db' );
function gv_audit_maybe_install_db() {
	if ( get_option( 'gv_audit_db_version' ) === GV_AUDIT_DB_VERSION ) { return; }

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table = $wpdb->prefix . 'gv_seo_audit_issues';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		post_title VARCHAR(255) NOT NULL DEFAULT '',
		url VARCHAR(500) NOT NULL DEFAULT '',
		category VARCHAR(40) NOT NULL DEFAULT '',
		severity VARCHAR(10) NOT NULL DEFAULT 'notice',
		message TEXT NULL,
		detail TEXT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY post_id (post_id),
		KEY category (category),
		KEY severity (severity)
	) {$charset_collate};" );

	update_option( 'gv_audit_db_version', GV_AUDIT_DB_VERSION );
}
function gv_audit_table() {
	global $wpdb;
	return $wpdb->prefix . 'gv_seo_audit_issues';
}

/* ==========================================================================
   ۲) وضعیت اسکن (ذخیره در یک آپشن، autoload خاموش)
   ========================================================================== */
function gv_audit_default_state() {
	return array(
		'status'          => 'idle', // idle | running | done | stopped
		'phase'           => '',     // pages | links | assets
		'started_at'      => 0,
		'finished_at'     => 0,
		'queue_pages'     => array(),
		'total_pages'     => 0,
		'done_pages'      => 0,
		'links'           => array(), // url => [ 'sources'=>[], 'checked'=>bool, 'status'=>int|null ]
		'assets'          => array(), // url => [ 'type'=>, 'sources'=>[], 'checked'=>bool, 'time'=>, 'size'=> ]
		'scanned_titles'  => array(), // برای پیدا کردن عنوان/توضیحات تکراری
		'scanned_descs'   => array(),
		'summary'         => array(
			'pages'    => 0,
			'errors'   => 0,
			'warnings' => 0,
			'notices'  => 0,
			'avg_speed'=> 0,
		),
		'permalinks_plain_warned' => false,
	);
}
function gv_audit_get_state() {
	$state = get_option( GV_AUDIT_STATE_OPT, null );
	if ( ! is_array( $state ) ) { return gv_audit_default_state(); }
	return wp_parse_args( $state, gv_audit_default_state() );
}
function gv_audit_save_state( $state ) {
	if ( false === get_option( GV_AUDIT_STATE_OPT, false ) ) {
		add_option( GV_AUDIT_STATE_OPT, $state, '', 'no' );
	} else {
		update_option( GV_AUDIT_STATE_OPT, $state );
	}
}

/* ==========================================================================
   ۳) منوی مدیریت
   ========================================================================== */
add_action( 'admin_menu', 'gv_audit_admin_menu' );
function gv_audit_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'آنالیز سئو و سرعت سایت | Groot Vision',
		'🩺 آنالیز سئو سایت',
		'manage_options',
		GV_AUDIT_PAGE_SLUG,
		'gv_audit_render_admin_page'
	);
}

/* ==========================================================================
   ۴) ذخیره تنظیمات
   ========================================================================== */
add_action( 'admin_post_gv_audit_save_settings', 'gv_audit_save_settings' );
function gv_audit_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_AUDIT_NONCE );

	$post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) )
		: array( 'post', 'page' );

	$settings = array(
		'post_types'         => ! empty( $post_types ) ? $post_types : array( 'post', 'page' ),
		'max_pages'          => max( 5, min( 3000, intval( $_POST['max_pages'] ?? 300 ) ) ),
		'batch_pages'        => max( 1, min( 10, intval( $_POST['batch_pages'] ?? 3 ) ) ),
		'batch_links'        => max( 1, min( 30, intval( $_POST['batch_links'] ?? 8 ) ) ),
		'batch_assets'       => max( 1, min( 30, intval( $_POST['batch_assets'] ?? 6 ) ) ),
		'min_words'          => max( 50, intval( $_POST['min_words'] ?? 300 ) ),
		'title_min'          => max( 10, intval( $_POST['title_min'] ?? 30 ) ),
		'title_max'          => max( 20, intval( $_POST['title_max'] ?? 60 ) ),
		'desc_min'           => max( 20, intval( $_POST['desc_min'] ?? 70 ) ),
		'desc_max'           => max( 40, intval( $_POST['desc_max'] ?? 160 ) ),
		'speed_warn'         => max( 0.2, floatval( $_POST['speed_warn'] ?? 2.0 ) ),
		'speed_error'        => max( 0.5, floatval( $_POST['speed_error'] ?? 4.0 ) ),
		'asset_time_warn'    => max( 0.2, floatval( $_POST['asset_time_warn'] ?? 1.0 ) ),
		'asset_size_warn_kb' => max( 20, intval( $_POST['asset_size_warn_kb'] ?? 500 ) ),
		'check_broken_links' => isset( $_POST['check_broken_links'] ) ? 1 : 0,
		'check_assets'       => isset( $_POST['check_assets'] ) ? 1 : 0,
		'exclude_contains'   => isset( $_POST['exclude_contains'] ) ? sanitize_textarea_field( wp_unslash( $_POST['exclude_contains'] ) ) : '',
	);

	update_option( GV_AUDIT_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_AUDIT_PAGE_SLUG . '&tab=settings&updated=1' ) );
	exit;
}

/* ==========================================================================
   ۵) ساخت صف صفحات برای اسکن
   ========================================================================== */
function gv_audit_scannable_post_types() {
	$all = get_post_types( array( 'public' => true ), 'objects' );
	unset( $all['attachment'] );
	return $all;
}

function gv_audit_build_queue( $settings ) {
	$queue   = array();
	$exclude = array_filter( array_map( 'trim', explode( "\n", (string) $settings['exclude_contains'] ) ) );

	// صفحه‌ی اصلی سایت (اگر نمایش «آخرین نوشته‌ها» باشد، جزو هیچ پست‌تایپی محسوب نمی‌شود)
	if ( 'posts' === get_option( 'show_on_front' ) ) {
		$queue[] = array( 'post_id' => 0, 'url' => home_url( '/' ), 'title' => get_bloginfo( 'name' ) . ' (صفحه اصلی)', 'post_type' => 'front_page' );
	}

	$q = new WP_Query( array(
		'post_type'      => array_values( $settings['post_types'] ),
		'post_status'    => 'publish',
		'posts_per_page' => intval( $settings['max_pages'] ),
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );

	foreach ( $q->posts as $post_id ) {
		$url = get_permalink( $post_id );
		if ( ! $url ) { continue; }

		$skip = false;
		foreach ( $exclude as $needle ) {
			if ( '' !== $needle && false !== strpos( $url, $needle ) ) { $skip = true; break; }
		}
		if ( $skip ) { continue; }

		$queue[] = array(
			'post_id'   => $post_id,
			'url'       => $url,
			'title'     => get_the_title( $post_id ),
			'post_type' => get_post_type( $post_id ),
		);
	}

	return $queue;
}

/* ==========================================================================
   ۶) اسکن یک صفحه (دانلود + آنالیز HTML)
   ========================================================================== */
function gv_audit_resolve_url( $base, $href ) {
	$href = trim( (string) $href );
	if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) || 0 === strpos( $href, 'javascript:' ) ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $href ) ) { return $href; }
	if ( 0 === strpos( $href, '//' ) ) { return ( is_ssl() ? 'https:' : 'http:' ) . $href; }

	$base_parts = wp_parse_url( $base );
	$scheme = $base_parts['scheme'] ?? 'https';
	$host   = $base_parts['host'] ?? '';
	if ( '' === $host ) { return ''; }

	if ( 0 === strpos( $href, '/' ) ) {
		return $scheme . '://' . $host . $href;
	}
	// مسیر نسبی
	$base_path = isset( $base_parts['path'] ) ? preg_replace( '#/[^/]*$#', '/', $base_parts['path'] ) : '/';
	return $scheme . '://' . $host . $base_path . $href;
}

function gv_audit_is_internal( $url ) {
	$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$url_host  = wp_parse_url( $url, PHP_URL_HOST );
	return $url_host && $home_host && strtolower( $url_host ) === strtolower( $home_host );
}

/** آدرس افزونه/قالب مسئولِ یک فایل استاتیک را تشخیص می‌دهد. */
function gv_audit_asset_owner( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $path, $m ) ) {
		return 'افزونه: ' . $m[1];
	}
	if ( preg_match( '#/wp-content/themes/([^/]+)/#', $path, $m ) ) {
		return 'قالب: ' . $m[1];
	}
	if ( preg_match( '#/wp-includes/#', $path ) ) { return 'هسته وردپرس'; }
	if ( strpos( $path, '/wp-content/uploads/' ) !== false ) { return 'فایل آپلودی (رسانه)'; }
	if ( ! gv_audit_is_internal( $url ) ) { return 'منبع خارجی/CDN'; }
	return 'نامشخص';
}

function gv_audit_check_url_structure( $url ) {
	$issues = array();
	$parsed = wp_parse_url( $url );
	$path   = isset( $parsed['path'] ) ? rawurldecode( $parsed['path'] ) : '/';
	$query  = $parsed['query'] ?? '';

	if ( $query && preg_match( '/(^|&)(p|page_id|product_id|cat)=/', $query ) ) {
		$issues[] = array( 'category' => 'url', 'severity' => 'error', 'message' => 'آدرس این صفحه به‌صورت سئوپسند نیست (شامل پارامتر شناسه مثل ?p= است)؛ در تنظیمات وردپرس بخش «پیوندهای یکتا» را روی یک ساختار مبتنی بر نام تنظیم کنید.' );
	}
	if ( preg_match( '/[A-Z]/', $path ) ) {
		$issues[] = array( 'category' => 'url', 'severity' => 'warning', 'message' => 'آدرس صفحه شامل حروف بزرگ انگلیسی است؛ بهتر است آدرس‌ها همیشه با حروف کوچک باشند.' );
	}
	if ( strpos( $path, '_' ) !== false ) {
		$issues[] = array( 'category' => 'url', 'severity' => 'notice', 'message' => 'آدرس صفحه از آندرلاین (_) استفاده کرده؛ گوگل خط‌تیره (-) را برای جداکردن کلمات بهتر تشخیص می‌دهد.' );
	}
	if ( strpos( $path, ' ' ) !== false || strpos( $path, '%20' ) !== false ) {
		$issues[] = array( 'category' => 'url', 'severity' => 'warning', 'message' => 'آدرس صفحه شامل فاصله (space) است.' );
	}
	if ( mb_strlen( $path ) > 90 ) {
		$issues[] = array( 'category' => 'url', 'severity' => 'notice', 'message' => 'آدرس صفحه نسبتاً طولانی است (' . mb_strlen( $path ) . ' کاراکتر)؛ آدرس کوتاه‌تر معمولاً بهتر است.' );
	}
	if ( preg_match( '/[^\p{L}\p{N}\-\/]/u', preg_replace( '/^\/|\/$/', '', $path ) ) ) {
		$issues[] = array( 'category' => 'url', 'severity' => 'notice', 'message' => 'آدرس صفحه شامل کاراکتر غیرمعمول (غیر از حروف/عدد/خط‌تیره) است.' );
	}
	return $issues;
}

/**
 * یک URL را دانلود و کامل آنالیز می‌کند.
 * خروجی: array( 'issues'=>[], 'word_count'=>, 'title'=>, 'title_len'=>, 'desc'=>, 'load_time'=>, 'links'=>[], 'assets'=>[], 'h1_count'=>, 'score'=> )
 */
function gv_audit_scan_page( $item, $settings, &$state ) {
	$url     = $item['url'];
	$post_id = $item['post_id'];
	$title_p = $item['title'];

	$issues = gv_audit_check_url_structure( $url );

	$start = microtime( true );
	$resp  = wp_remote_get( $url, array(
		'timeout'     => 20,
		'redirection' => 3,
		'sslverify'   => false,
		'user-agent'  => 'GrootVisionSEOAudit/1.0 (+' . home_url() . ')',
	) );
	$elapsed = round( microtime( true ) - $start, 3 );

	if ( is_wp_error( $resp ) ) {
		$issues[] = array( 'category' => 'fetch', 'severity' => 'error', 'message' => 'این صفحه قابل دریافت نبود: ' . $resp->get_error_message() );
		return array( 'issues' => $issues, 'load_time' => $elapsed, 'word_count' => 0, 'title' => '', 'h1_count' => 0, 'links' => array(), 'assets' => array(), 'score' => 0 );
	}

	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	$size_kb = round( strlen( $body ) / 1024, 1 );

	if ( $code >= 400 ) {
		$issues[] = array( 'category' => 'fetch', 'severity' => 'error', 'message' => "کد پاسخ این صفحه {$code} است (خطا)." );
	}

	// سرعت لود
	if ( $elapsed >= $settings['speed_error'] ) {
		$issues[] = array( 'category' => 'speed', 'severity' => 'error', 'message' => "لود این صفحه {$elapsed} ثانیه طول کشید؛ خیلی کند است و مستقیماً روی سئو و تجربه‌ی کاربر اثر منفی می‌گذارد." );
	} elseif ( $elapsed >= $settings['speed_warn'] ) {
		$issues[] = array( 'category' => 'speed', 'severity' => 'warning', 'message' => "لود این صفحه {$elapsed} ثانیه طول کشید؛ بهتر است به زیر {$settings['speed_warn']} ثانیه برسد." );
	}
	if ( $size_kb > 3000 ) {
		$issues[] = array( 'category' => 'speed', 'severity' => 'warning', 'message' => "حجم HTML این صفحه حدود {$size_kb} کیلوبایت است؛ حجم بالا لود صفحه را کند می‌کند." );
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $body );
	libxml_clear_errors();

	$word_count = 0; $h1_count = 0; $page_title = ''; $title_len = 0; $desc = ''; $links = array(); $assets = array();

	if ( $loaded ) {
		$xpath = new DOMXPath( $dom );

		// عنوان (Title Tag)
		$title_nodes = $xpath->query( '//title' );
		if ( $title_nodes->length > 0 ) {
			$page_title = trim( $title_nodes->item( 0 )->textContent );
			$title_len  = mb_strlen( $page_title );
		}
		if ( '' === $page_title ) {
			$issues[] = array( 'category' => 'title_tag', 'severity' => 'error', 'message' => 'این صفحه هیچ تگ Title ندارد.' );
		} elseif ( $title_len < $settings['title_min'] ) {
			$issues[] = array( 'category' => 'title_tag', 'severity' => 'warning', 'message' => "طول تگ Title ({$title_len} کاراکتر) کوتاه‌تر از حد پیشنهادی ({$settings['title_min']} تا {$settings['title_max']}) است.", 'detail' => $page_title );
		} elseif ( $title_len > $settings['title_max'] ) {
			$issues[] = array( 'category' => 'title_tag', 'severity' => 'warning', 'message' => "طول تگ Title ({$title_len} کاراکتر) بلندتر از حد پیشنهادی ({$settings['title_min']} تا {$settings['title_max']}) است و ممکن است در گوگل بریده شود.", 'detail' => $page_title );
		}

		// متا دیسکریپشن
		$desc_nodes = $xpath->query( '//meta[translate(@name,"DESCRIPTION","description")="description"]/@content' );
		if ( $desc_nodes->length > 0 ) {
			$desc = trim( $desc_nodes->item( 0 )->nodeValue );
		}
		$desc_len = mb_strlen( $desc );
		if ( '' === $desc ) {
			$issues[] = array( 'category' => 'meta_desc', 'severity' => 'error', 'message' => 'این صفحه متا توضیحات (Meta Description) ندارد.' );
		} elseif ( $desc_len < $settings['desc_min'] ) {
			$issues[] = array( 'category' => 'meta_desc', 'severity' => 'warning', 'message' => "طول متا توضیحات ({$desc_len} کاراکتر) کوتاه‌تر از حد پیشنهادی ({$settings['desc_min']} تا {$settings['desc_max']}) است.", 'detail' => $desc );
		} elseif ( $desc_len > $settings['desc_max'] ) {
			$issues[] = array( 'category' => 'meta_desc', 'severity' => 'warning', 'message' => "طول متا توضیحات ({$desc_len} کاراکتر) بلندتر از حد پیشنهادی است و ممکن است در نتایج گوگل بریده شود.", 'detail' => $desc );
		}

		// Canonical
		$canon = $xpath->query( '//link[@rel="canonical"]/@href' );
		if ( 0 === $canon->length ) {
			$issues[] = array( 'category' => 'canonical', 'severity' => 'notice', 'message' => 'لینک Canonical برای این صفحه تنظیم نشده است.' );
		}

		// Robots noindex
		$robots = $xpath->query( '//meta[translate(@name,"ROBOTS","robots")="robots"]/@content' );
		if ( $robots->length > 0 && false !== stripos( $robots->item( 0 )->nodeValue, 'noindex' ) ) {
			$issues[] = array( 'category' => 'robots', 'severity' => 'notice', 'message' => 'این صفحه با noindex علامت‌گذاری شده و گوگل آن را در نتایج نشان نمی‌دهد (اگر عمدی نیست، بررسی کنید).' );
		}

		// H1
		$h1_nodes = $xpath->query( '//h1[not(ancestor::header) and not(ancestor::nav) and not(ancestor::footer)]' );
		$h1_count = $h1_nodes->length;
		if ( 0 === $h1_count ) {
			$issues[] = array( 'category' => 'h1', 'severity' => 'error', 'message' => 'این صفحه هیچ تگ H1 ندارد.' );
		} elseif ( $h1_count > 1 ) {
			$sample = array();
			foreach ( $h1_nodes as $n ) { $sample[] = trim( $n->textContent ); }
			$issues[] = array( 'category' => 'h1', 'severity' => 'warning', 'message' => "این صفحه {$h1_count} تگ H1 دارد؛ باید فقط یک H1 در هر صفحه باشد.", 'detail' => implode( ' | ', array_slice( $sample, 0, 5 ) ) );
		}

		// تعداد کلمات محتوا
		$content_node = null;
		foreach ( array( '//article', '//main', '//*[@role="main"]', '//*[contains(@class,"entry-content")]', '//*[contains(@class,"post-content")]', '//*[@id="content"]', '//body' ) as $q ) {
			$nodes = $xpath->query( $q );
			if ( $nodes->length > 0 ) { $content_node = $nodes->item( 0 ); break; }
		}
		if ( $content_node ) {
			$text_nodes = $xpath->query( './/text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::nav) and not(ancestor::header) and not(ancestor::footer) and not(ancestor::aside) and not(ancestor::form)]', $content_node );
			$full_text = '';
			foreach ( $text_nodes as $t ) { $full_text .= ' ' . $t->nodeValue; }
			$words = preg_split( '/\s+/u', trim( $full_text ) );
			$words = array_filter( $words, function( $w ) { return '' !== trim( $w ); } );
			$word_count = count( $words );
		}
		if ( $word_count < $settings['min_words'] ) {
			$issues[] = array( 'category' => 'words', 'severity' => 'warning', 'message' => "این صفحه فقط حدود {$word_count} کلمه محتوا دارد؛ حداقل پیشنهادی {$settings['min_words']} کلمه است." );
		}

		// تصاویر بدون Alt
		$img_nodes = $xpath->query( '//img' );
		$missing_alt = array();
		foreach ( $img_nodes as $img ) {
			$alt = $img->getAttribute( 'alt' );
			if ( '' === trim( $alt ) ) {
				$src = $img->getAttribute( 'src' );
				if ( count( $missing_alt ) < 5 ) { $missing_alt[] = $src; }
			}
		}
		if ( ! empty( $missing_alt ) ) {
			$issues[] = array(
				'category' => 'images',
				'severity' => 'warning',
				'message'  => count( $missing_alt ) . ( $img_nodes->length > count( $missing_alt ) ? '+' : '' ) . ' تصویر بدون متن جایگزین (Alt) در این صفحه پیدا شد.',
				'detail'   => implode( "\n", $missing_alt ),
			);
		}

		// لینک‌های داخلی این صفحه (برای فاز بعدیِ چک لینک خراب)
		if ( $settings['check_broken_links'] ) {
			$a_nodes = $xpath->query( '//a[@href]/@href' );
			foreach ( $a_nodes as $href_attr ) {
				$abs = gv_audit_resolve_url( $url, $href_attr->nodeValue );
				if ( '' === $abs || ! gv_audit_is_internal( $abs ) ) { continue; }
				$abs = strtok( $abs, '#' ); // حذف anchor
				if ( '' === $abs ) { continue; }
				$links[] = $abs;
			}
		}

		// فایل‌های استاتیک این صفحه (برای فاز بعدیِ چک سرعت assetها)
		if ( $settings['check_assets'] ) {
			foreach ( array(
				'//link[@rel="stylesheet"]/@href' => 'css',
				'//script[@src]/@src'             => 'js',
			) as $q => $type ) {
				foreach ( $xpath->query( $q ) as $attr ) {
					$abs = gv_audit_resolve_url( $url, $attr->nodeValue );
					if ( '' !== $abs ) { $assets[] = array( 'url' => $abs, 'type' => $type ); }
				}
			}
		}
	} else {
		$issues[] = array( 'category' => 'fetch', 'severity' => 'error', 'message' => 'ساختار HTML این صفحه قابل تحلیل نبود.' );
	}

	// عنوان/توضیحات تکراری بین صفحات
	if ( '' !== $page_title ) {
		$key = mb_strtolower( trim( $page_title ) );
		if ( isset( $state['scanned_titles'][ $key ] ) ) {
			$issues[] = array( 'category' => 'duplicate', 'severity' => 'warning', 'message' => 'تگ Title این صفحه با صفحه‌ی دیگری یکسان است: ' . $state['scanned_titles'][ $key ] );
		} else {
			$state['scanned_titles'][ $key ] = $url;
		}
	}
	if ( '' !== $desc ) {
		$key = mb_strtolower( trim( $desc ) );
		if ( isset( $state['scanned_descs'][ $key ] ) ) {
			$issues[] = array( 'category' => 'duplicate', 'severity' => 'notice', 'message' => 'متا توضیحات این صفحه با صفحه‌ی دیگری یکسان است: ' . $state['scanned_descs'][ $key ] );
		} else {
			$state['scanned_descs'][ $key ] = $url;
		}
	}

	// امتیاز ساده‌ی هر صفحه از ۱۰۰
	$score = 100;
	foreach ( $issues as $iss ) {
		$score -= ( 'error' === $iss['severity'] ) ? 12 : ( ( 'warning' === $iss['severity'] ) ? 5 : 1 );
	}
	$score = max( 0, $score );

	return array(
		'issues'     => $issues,
		'load_time'  => $elapsed,
		'word_count' => $word_count,
		'title'      => $page_title,
		'h1_count'   => $h1_count,
		'links'      => $links,
		'assets'     => $assets,
		'score'      => $score,
	);
}

/* ==========================================================================
   ۷) ثبت مشکلات در دیتابیس
   ========================================================================== */
function gv_audit_insert_issue( $post_id, $post_title, $url, $issue ) {
	global $wpdb;
	$wpdb->insert(
		gv_audit_table(),
		array(
			'post_id'    => (int) $post_id,
			'post_title' => wp_strip_all_tags( $post_title ),
			'url'        => esc_url_raw( $url ),
			'category'   => sanitize_key( $issue['category'] ),
			'severity'   => sanitize_key( $issue['severity'] ),
			'message'    => wp_strip_all_tags( $issue['message'] ),
			'detail'     => isset( $issue['detail'] ) ? wp_strip_all_tags( $issue['detail'] ) : '',
			'created_at' => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
}

/* ==========================================================================
   ۸) AJAX: شروع اسکن
   ========================================================================== */
add_action( 'wp_ajax_gv_audit_start', 'gv_audit_ajax_start' );
function gv_audit_ajax_start() {
	check_ajax_referer( GV_AUDIT_NONCE, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) ); }

	global $wpdb;
	$wpdb->query( 'TRUNCATE TABLE ' . gv_audit_table() ); // شروع تازه

	$settings = gv_audit_get_settings();
	$queue    = gv_audit_build_queue( $settings );

	$state = gv_audit_default_state();
	$state['status']      = 'running';
	$state['phase']       = 'pages';
	$state['started_at']  = time();
	$state['queue_pages'] = $queue;
	$state['total_pages'] = count( $queue );
	gv_audit_save_state( $state );

	wp_send_json_success( array( 'total' => count( $queue ) ) );
}

/* ==========================================================================
   ۹) AJAX: توقف / پاک‌سازی
   ========================================================================== */
add_action( 'wp_ajax_gv_audit_stop', 'gv_audit_ajax_stop' );
function gv_audit_ajax_stop() {
	check_ajax_referer( GV_AUDIT_NONCE, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
	$state = gv_audit_get_state();
	$state['status'] = 'stopped';
	gv_audit_save_state( $state );
	wp_send_json_success();
}

/* ==========================================================================
   ۱۰) AJAX: پردازش یک دسته (batch tick) — قلب سیستم اسکن
   ========================================================================== */
add_action( 'wp_ajax_gv_audit_tick', 'gv_audit_ajax_tick' );
function gv_audit_ajax_tick() {
	check_ajax_referer( GV_AUDIT_NONCE, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) ); }

	@set_time_limit( 90 );
	$settings = gv_audit_get_settings();
	$state    = gv_audit_get_state();

	if ( 'running' !== $state['status'] ) {
		wp_send_json_success( array( 'status' => $state['status'], 'phase' => $state['phase'] ) );
	}

	if ( 'pages' === $state['phase'] ) {
		$batch = array_splice( $state['queue_pages'], 0, $settings['batch_pages'] );

		foreach ( $batch as $item ) {
			$result = gv_audit_scan_page( $item, $settings, $state );

			foreach ( $result['issues'] as $issue ) {
				gv_audit_insert_issue( $item['post_id'], $item['title'], $item['url'], $issue );
				'error' === $issue['severity'] ? $state['summary']['errors']++ : ( 'warning' === $issue['severity'] ? $state['summary']['warnings']++ : $state['summary']['notices']++ );
			}

			foreach ( $result['links'] as $link ) {
				if ( ! isset( $state['links'][ $link ] ) ) {
					$state['links'][ $link ] = array( 'sources' => array(), 'checked' => false, 'status' => null );
				}
				if ( count( $state['links'][ $link ]['sources'] ) < 5 ) {
					$state['links'][ $link ]['sources'][] = array( 'post_id' => $item['post_id'], 'title' => $item['title'], 'url' => $item['url'] );
				}
			}
			foreach ( $result['assets'] as $asset ) {
				$au = $asset['url'];
				if ( ! isset( $state['assets'][ $au ] ) ) {
					$state['assets'][ $au ] = array( 'type' => $asset['type'], 'sources' => array(), 'checked' => false, 'time' => null, 'size' => null );
				}
				if ( count( $state['assets'][ $au ]['sources'] ) < 5 ) {
					$state['assets'][ $au ]['sources'][] = array( 'post_id' => $item['post_id'], 'title' => $item['title'], 'url' => $item['url'] );
				}
			}

			$state['done_pages']++;
			$state['summary']['pages']++;
			$state['summary']['avg_speed'] = round( ( ( $state['summary']['avg_speed'] * ( $state['summary']['pages'] - 1 ) ) + $result['load_time'] ) / $state['summary']['pages'], 3 );
		}

		if ( empty( $state['queue_pages'] ) ) {
			$state['phase'] = $settings['check_broken_links'] ? 'links' : ( $settings['check_assets'] ? 'assets' : 'finish' );
		}

	} elseif ( 'links' === $state['phase'] ) {
		$pending = array();
		foreach ( $state['links'] as $u => $info ) {
			if ( ! $info['checked'] ) { $pending[] = $u; }
			if ( count( $pending ) >= $settings['batch_links'] ) { break; }
		}

		foreach ( $pending as $u ) {
			$r = wp_remote_head( $u, array( 'timeout' => 12, 'redirection' => 3, 'sslverify' => false ) );
			$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
			if ( 0 === $code || $code >= 400 ) {
				// بعضی سرورها HEAD را رد می‌کنند؛ یک بار دیگر با GET امتحان می‌کنیم
				$r2 = wp_remote_get( $u, array( 'timeout' => 12, 'redirection' => 3, 'sslverify' => false ) );
				$code = is_wp_error( $r2 ) ? 0 : wp_remote_retrieve_response_code( $r2 );
			}
			$state['links'][ $u ]['checked'] = true;
			$state['links'][ $u ]['status']  = $code;

			if ( 0 === $code || $code >= 400 ) {
				foreach ( $state['links'][ $u ]['sources'] as $src ) {
					$issue = array(
						'category' => 'broken_link',
						'severity' => 'error',
						'message'  => 'یک لینک داخلی خراب در این صفحه پیدا شد (کد پاسخ: ' . ( $code ?: 'بدون پاسخ' ) . ').',
						'detail'   => $u,
					);
					gv_audit_insert_issue( $src['post_id'], $src['title'], $src['url'], $issue );
					$state['summary']['errors']++;
				}
			}
		}

		$still_pending = false;
		foreach ( $state['links'] as $info ) { if ( ! $info['checked'] ) { $still_pending = true; break; } }
		if ( ! $still_pending ) {
			$state['phase'] = $settings['check_assets'] ? 'assets' : 'finish';
		}

	} elseif ( 'assets' === $state['phase'] ) {
		$pending = array();
		foreach ( $state['assets'] as $u => $info ) {
			if ( ! $info['checked'] ) { $pending[] = $u; }
			if ( count( $pending ) >= $settings['batch_assets'] ) { break; }
		}

		foreach ( $pending as $u ) {
			$t0 = microtime( true );
			$r  = wp_remote_get( $u, array( 'timeout' => 15, 'redirection' => 2, 'sslverify' => false, 'limit_response_size' => 6000000 ) );
			$et = round( microtime( true ) - $t0, 3 );

			$state['assets'][ $u ]['checked'] = true;

			if ( is_wp_error( $r ) ) {
				foreach ( $state['assets'][ $u ]['sources'] as $src ) {
					$issue = array(
						'category' => 'asset',
						'severity' => 'warning',
						'message'  => 'یک فایل (' . strtoupper( $state['assets'][ $u ]['type'] ) . ') در این صفحه بارگذاری نشد: ' . gv_audit_asset_owner( $u ),
						'detail'   => $u,
					);
					gv_audit_insert_issue( $src['post_id'], $src['title'], $src['url'], $issue );
					$state['summary']['warnings']++;
				}
				continue;
			}

			$len = wp_remote_retrieve_header( $r, 'content-length' );
			$size_kb = $len ? round( intval( $len ) / 1024, 1 ) : round( strlen( wp_remote_retrieve_body( $r ) ) / 1024, 1 );

			$state['assets'][ $u ]['time'] = $et;
			$state['assets'][ $u ]['size'] = $size_kb;

			$is_slow  = $et >= $settings['asset_time_warn'];
			$is_heavy = $size_kb >= $settings['asset_size_warn_kb'];

			if ( $is_slow || $is_heavy ) {
				$owner = gv_audit_asset_owner( $u );
				$reasons = array();
				if ( $is_slow )  { $reasons[] = "زمان لود {$et} ثانیه"; }
				if ( $is_heavy ) { $reasons[] = "حجم {$size_kb} کیلوبایت"; }

				foreach ( $state['assets'][ $u ]['sources'] as $src ) {
					$issue = array(
						'category' => 'asset',
						'severity' => 'warning',
						'message'  => 'فایل ' . strtoupper( $state['assets'][ $u ]['type'] ) . " کند/سنگین در این صفحه لود می‌شود ({$owner}) — " . implode( '، ', $reasons ) . '.',
						'detail'   => $u,
					);
					gv_audit_insert_issue( $src['post_id'], $src['title'], $src['url'], $issue );
					$state['summary']['warnings']++;
				}
			}
		}

		$still_pending = false;
		foreach ( $state['assets'] as $info ) { if ( ! $info['checked'] ) { $still_pending = true; break; } }
		if ( ! $still_pending ) { $state['phase'] = 'finish'; }
	}

	if ( 'finish' === $state['phase'] ) {
		$state['status']      = 'done';
		$state['finished_at'] = time();
	}

	gv_audit_save_state( $state );

	wp_send_json_success( array(
		'status'      => $state['status'],
		'phase'       => $state['phase'],
		'done_pages'  => $state['done_pages'],
		'total_pages' => $state['total_pages'],
		'links_total'   => count( $state['links'] ),
		'links_checked' => count( array_filter( $state['links'], function( $i ) { return $i['checked']; } ) ),
		'assets_total'   => count( $state['assets'] ),
		'assets_checked' => count( array_filter( $state['assets'], function( $i ) { return $i['checked']; } ) ),
		'summary'     => $state['summary'],
	) );
}

/* ==========================================================================
   ۱۱) توابع کمکی برای نمایش
   ========================================================================== */
function gv_audit_get_issues( $args = array() ) {
	global $wpdb;
	$where = array( '1=1' );
	$params = array();

	if ( ! empty( $args['severity'] ) ) { $where[] = 'severity = %s'; $params[] = $args['severity']; }
	if ( ! empty( $args['category'] ) ) { $where[] = 'category = %s'; $params[] = $args['category']; }
	if ( ! empty( $args['search'] ) )   { $where[] = '(post_title LIKE %s OR url LIKE %s OR message LIKE %s)'; $like = '%' . $wpdb->esc_like( $args['search'] ) . '%'; $params[] = $like; $params[] = $like; $params[] = $like; }

	$sql = 'SELECT * FROM ' . gv_audit_table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY FIELD(severity,"error","warning","notice"), id DESC';
	$limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : 200;
	$sql .= $wpdb->prepare( ' LIMIT %d', $limit );

	if ( ! empty( $params ) ) {
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore
	}
	return $wpdb->get_results( $sql );
}

function gv_audit_count_by( $field ) {
	global $wpdb;
	$field = in_array( $field, array( 'severity', 'category' ), true ) ? $field : 'severity';
	return $wpdb->get_results( "SELECT {$field} AS k, COUNT(*) AS c FROM " . gv_audit_table() . " GROUP BY {$field} ORDER BY c DESC" );
}

function gv_audit_category_label( $cat ) {
	$labels = array(
		'h1'          => 'تگ H1',
		'words'       => 'تعداد کلمات',
		'title_tag'   => 'تگ Title',
		'meta_desc'   => 'متا دیسکریپشن',
		'url'         => 'ساختار آدرس',
		'images'      => 'تصاویر (Alt)',
		'canonical'   => 'Canonical',
		'robots'      => 'ایندکس/نوایندکس',
		'speed'       => 'سرعت صفحه',
		'broken_link' => 'لینک خراب',
		'asset'       => 'فایل کند/سنگین',
		'duplicate'   => 'محتوای تکراری',
		'fetch'       => 'خطای دریافت صفحه',
	);
	return $labels[ $cat ] ?? $cat;
}
function gv_audit_severity_label( $sev ) {
	$labels = array( 'error' => 'خطا', 'warning' => 'هشدار', 'notice' => 'توجه' );
	return $labels[ $sev ] ?? $sev;
}
function gv_audit_severity_color( $sev ) {
	$colors = array( 'error' => '#dc2626', 'warning' => '#d97706', 'notice' => '#2563eb' );
	return $colors[ $sev ] ?? '#64748b';
}

/* ==========================================================================
   ۱۲) رندر صفحه‌ی مدیریت
   ========================================================================== */
function gv_audit_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$settings = gv_audit_get_settings();
	$state    = gv_audit_get_state();
	$tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

	$sev_counts = array( 'error' => 0, 'warning' => 0, 'notice' => 0 );
	foreach ( gv_audit_count_by( 'severity' ) as $row ) { $sev_counts[ $row->k ] = (int) $row->c; }
	$total_issues = array_sum( $sev_counts );

	global $wpdb;
	$scanned_pages_count = (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT url) FROM ' . gv_audit_table() );
	?>
	<div class="wrap gvaudit-wrap" dir="rtl">
		<h1 style="font-size:20px;">🩺 آنالیز سئو و سرعت سایت</h1>
		<p style="color:#555;max-width:760px;">این ابزار کل سایت را صفحه‌به‌صفحه اسکن می‌کند و هرچیزی که ممکن است به سئو یا سرعت آسیب بزند (H1، تعداد کلمات، متا تگ‌ها، آدرس سئوپسند، لینک خراب، فایل کند) را با آدرس دقیق همان صفحه گزارش می‌دهد.</p>

		<h2 class="nav-tab-wrapper">
			<a href="?page=<?php echo esc_attr( GV_AUDIT_PAGE_SLUG ); ?>&tab=dashboard" class="nav-tab <?php echo 'dashboard' === $tab ? 'nav-tab-active' : ''; ?>">📊 داشبورد اسکن</a>
			<a href="?page=<?php echo esc_attr( GV_AUDIT_PAGE_SLUG ); ?>&tab=issues" class="nav-tab <?php echo 'issues' === $tab ? 'nav-tab-active' : ''; ?>">⚠️ لیست مشکلات (<?php echo (int) $total_issues; ?>)</a>
			<a href="?page=<?php echo esc_attr( GV_AUDIT_PAGE_SLUG ); ?>&tab=settings" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">⚙️ تنظیمات</a>
		</h2>

		<style>
			.gvaudit-cards{display:flex;gap:14px;flex-wrap:wrap;margin:18px 0;}
			.gvaudit-card{background:#fff;border:1px solid #e2e2e2;border-radius:12px;padding:16px 20px;min-width:150px;flex:1;}
			.gvaudit-card b{font-size:26px;display:block;}
			.gvaudit-progress-wrap{background:#eef0f2;border-radius:8px;height:22px;overflow:hidden;max-width:640px;margin:10px 0;}
			.gvaudit-progress-bar{background:linear-gradient(90deg,#16a34a,#22c55e);height:100%;width:0%;transition:width .3s;}
			.gvaudit-table{width:100%;border-collapse:collapse;background:#fff;}
			.gvaudit-table th,.gvaudit-table td{padding:9px 10px;border-bottom:1px solid #eee;font-size:13px;text-align:right;vertical-align:top;}
			.gvaudit-badge{display:inline-block;padding:2px 9px;border-radius:20px;color:#fff;font-size:11.5px;}
			.gvaudit-filters{margin:14px 0;display:flex;gap:8px;flex-wrap:wrap;}
			.gvaudit-note{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-size:13px;color:#1e3a8a;max-width:760px;}
		</style>

		<?php if ( 'dashboard' === $tab ) : ?>
			<div class="gvaudit-cards">
				<div class="gvaudit-card"><b><?php echo esc_html( $scanned_pages_count ); ?></b>صفحه بررسی‌شده</div>
				<div class="gvaudit-card" style="border-color:#fecaca;"><b style="color:#dc2626;"><?php echo esc_html( $sev_counts['error'] ); ?></b>خطا</div>
				<div class="gvaudit-card" style="border-color:#fde68a;"><b style="color:#d97706;"><?php echo esc_html( $sev_counts['warning'] ); ?></b>هشدار</div>
				<div class="gvaudit-card" style="border-color:#bfdbfe;"><b style="color:#2563eb;"><?php echo esc_html( $sev_counts['notice'] ); ?></b>موارد قابل توجه</div>
				<div class="gvaudit-card"><b><?php echo esc_html( $state['summary']['avg_speed'] ?: '—' ); ?>s</b>میانگین سرعت لود</div>
			</div>

			<div id="gvaudit-status-box" class="gvaudit-note" style="margin-bottom:14px;">
				<?php if ( 'running' === $state['status'] ) : ?>
					اسکن در حال اجراست… (فاز فعلی: <span id="gvaudit-phase-label"><?php echo esc_html( gv_audit_phase_label( $state['phase'] ) ); ?></span>)
				<?php elseif ( 'done' === $state['status'] ) : ?>
					آخرین اسکن در <?php echo esc_html( date_i18n( 'Y/m/d H:i', $state['finished_at'] ) ); ?> تمام شد.
				<?php else : ?>
					هنوز اسکنی اجرا نشده یا متوقف شده است. روی «شروع اسکن کامل سایت» بزنید.
				<?php endif; ?>
			</div>

			<div class="gvaudit-progress-wrap"><div class="gvaudit-progress-bar" id="gvaudit-progress-bar"></div></div>
			<p id="gvaudit-progress-text" style="font-size:13px;color:#555;"></p>

			<p>
				<button type="button" class="button button-primary button-hero" id="gvaudit-start-btn">🚀 شروع اسکن کامل سایت</button>
				<button type="button" class="button" id="gvaudit-stop-btn" style="display:none;">⏹ توقف اسکن</button>
			</p>

			<p class="description">حداکثر <?php echo esc_html( $settings['max_pages'] ); ?> صفحه از پست‌تایپ‌های انتخاب‌شده در تنظیمات بررسی می‌شود. برای تغییر، به تب «تنظیمات» بروید.</p>

		<?php elseif ( 'issues' === $tab ) : ?>
			<?php
			$f_sev = isset( $_GET['sev'] ) ? sanitize_key( $_GET['sev'] ) : '';
			$f_cat = isset( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : '';
			$f_q   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$rows  = gv_audit_get_issues( array( 'severity' => $f_sev, 'category' => $f_cat, 'search' => $f_q, 'limit' => 500 ) );
			?>
			<form method="get" class="gvaudit-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( GV_AUDIT_PAGE_SLUG ); ?>">
				<input type="hidden" name="tab" value="issues">
				<select name="sev">
					<option value="">همه شدت‌ها</option>
					<option value="error" <?php selected( $f_sev, 'error' ); ?>>فقط خطا</option>
					<option value="warning" <?php selected( $f_sev, 'warning' ); ?>>فقط هشدار</option>
					<option value="notice" <?php selected( $f_sev, 'notice' ); ?>>فقط توجه</option>
				</select>
				<select name="cat">
					<option value="">همه دسته‌ها</option>
					<?php foreach ( gv_audit_count_by( 'category' ) as $row ) : ?>
						<option value="<?php echo esc_attr( $row->k ); ?>" <?php selected( $f_cat, $row->k ); ?>><?php echo esc_html( gv_audit_category_label( $row->k ) ); ?> (<?php echo (int) $row->c; ?>)</option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="s" value="<?php echo esc_attr( $f_q ); ?>" placeholder="جستجو در عنوان/آدرس/پیام…" class="regular-text">
				<button class="button">فیلتر</button>
			</form>

			<table class="gvaudit-table">
				<thead><tr><th>شدت</th><th>دسته</th><th>صفحه</th><th>مشکل</th></tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="4">هیچ موردی یافت نشد.</td></tr>
				<?php else : foreach ( $rows as $r ) : ?>
					<tr>
						<td><span class="gvaudit-badge" style="background:<?php echo esc_attr( gv_audit_severity_color( $r->severity ) ); ?>;"><?php echo esc_html( gv_audit_severity_label( $r->severity ) ); ?></span></td>
						<td><?php echo esc_html( gv_audit_category_label( $r->category ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( $r->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r->post_title ?: $r->url ); ?></a><br>
							<code style="font-size:11px;color:#888;"><?php echo esc_html( $r->url ); ?></code>
							<?php if ( $r->post_id ) : ?> — <a href="<?php echo esc_url( get_edit_post_link( $r->post_id ) ); ?>">ویرایش</a><?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $r->message ); ?>
							<?php if ( $r->detail ) : ?><br><code style="font-size:11.5px;color:#555;white-space:pre-line;"><?php echo esc_html( wp_trim_words( $r->detail, 30 ) ); ?></code><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

		<?php elseif ( 'settings' === $tab ) : ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p>تنظیمات ذخیره شد.</p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;">
				<input type="hidden" name="action" value="gv_audit_save_settings">
				<?php wp_nonce_field( GV_AUDIT_NONCE ); ?>

				<div class="gvaudit-card" style="margin-bottom:14px;">
					<h2>پست‌تایپ‌های بررسی‌شونده</h2>
					<?php foreach ( gv_audit_scannable_post_types() as $pt ) : ?>
						<label style="display:inline-block;margin:4px 14px 4px 0;">
							<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $settings['post_types'], true ) ); ?>>
							<?php echo esc_html( $pt->labels->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="gvaudit-card" style="margin-bottom:14px;">
					<h2>محدودیت‌ها و دسته‌بندی اسکن</h2>
					<p><label>حداکثر تعداد صفحه در هر اسکن: <input type="number" name="max_pages" value="<?php echo esc_attr( $settings['max_pages'] ); ?>" min="5" max="3000"></label></p>
					<p><label>حداقل تعداد کلمه‌ی قابل‌قبول برای یک صفحه: <input type="number" name="min_words" value="<?php echo esc_attr( $settings['min_words'] ); ?>"></label></p>
					<p><label><input type="checkbox" name="check_broken_links" <?php checked( $settings['check_broken_links'], 1 ); ?>> بررسی لینک‌های داخلی خراب</label></p>
					<p><label><input type="checkbox" name="check_assets" <?php checked( $settings['check_assets'], 1 ); ?>> بررسی سرعت/حجم فایل‌های CSS و JS (تشخیص افزونه مقصر)</label></p>
				</div>

				<div class="gvaudit-card" style="margin-bottom:14px;">
					<h2>آستانه‌های تگ‌ها</h2>
					<p><label>طول Title (حداقل/حداکثر): <input type="number" name="title_min" value="<?php echo esc_attr( $settings['title_min'] ); ?>" style="width:70px;"> — <input type="number" name="title_max" value="<?php echo esc_attr( $settings['title_max'] ); ?>" style="width:70px;"></label></p>
					<p><label>طول متا توضیحات (حداقل/حداکثر): <input type="number" name="desc_min" value="<?php echo esc_attr( $settings['desc_min'] ); ?>" style="width:70px;"> — <input type="number" name="desc_max" value="<?php echo esc_attr( $settings['desc_max'] ); ?>" style="width:70px;"></label></p>
				</div>

				<div class="gvaudit-card" style="margin-bottom:14px;">
					<h2>آستانه‌های سرعت</h2>
					<p><label>هشدار کندی صفحه از (ثانیه): <input type="number" step="0.1" name="speed_warn" value="<?php echo esc_attr( $settings['speed_warn'] ); ?>"></label></p>
					<p><label>خطای کندی صفحه از (ثانیه): <input type="number" step="0.1" name="speed_error" value="<?php echo esc_attr( $settings['speed_error'] ); ?>"></label></p>
					<p><label>هشدار کندی فایل CSS/JS از (ثانیه): <input type="number" step="0.1" name="asset_time_warn" value="<?php echo esc_attr( $settings['asset_time_warn'] ); ?>"></label></p>
					<p><label>هشدار سنگینی فایل CSS/JS از (کیلوبایت): <input type="number" name="asset_size_warn_kb" value="<?php echo esc_attr( $settings['asset_size_warn_kb'] ); ?>"></label></p>
				</div>

				<div class="gvaudit-card" style="margin-bottom:14px;">
					<h2>حذف آدرس‌های خاص از اسکن</h2>
					<p class="description">هر خط یک بخش از آدرس که باید نادیده گرفته شود (مثلاً /basket/ یا /cart/)</p>
					<textarea name="exclude_contains" rows="4" style="width:100%;"><?php echo esc_textarea( $settings['exclude_contains'] ); ?></textarea>
				</div>

				<p><button class="button button-primary">ذخیره تنظیمات</button></p>
			</form>
		<?php endif; ?>
	</div>

	<script>
	(function(){
		var startBtn = document.getElementById('gvaudit-start-btn');
		var stopBtn  = document.getElementById('gvaudit-stop-btn');
		var bar      = document.getElementById('gvaudit-progress-bar');
		var text     = document.getElementById('gvaudit-progress-text');
		if (!startBtn) { return; }

		var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce   = <?php echo wp_json_encode( wp_create_nonce( GV_AUDIT_NONCE ) ); ?>;
		var running = false;

		function post(action, extra) {
			var body = new URLSearchParams();
			body.append('action', action);
			body.append('nonce', nonce);
			if (extra) { for (var k in extra) { body.append(k, extra[k]); } }
			return fetch(ajaxUrl, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() }).then(function(r){ return r.json(); });
		}

		function phaseLabel(p) {
			return { pages:'اسکن صفحات', links:'بررسی لینک‌های خراب', assets:'بررسی سرعت فایل‌ها', finish:'پایان' }[p] || p;
		}

		function tick() {
			if (!running) { return; }
			post('gv_audit_tick').then(function(res){
				if (!res || !res.success) { running = false; return; }
				var d = res.data;
				var pct = 0;
				if (d.phase === 'pages') {
					pct = d.total_pages ? Math.round((d.done_pages / d.total_pages) * 60) : 0;
					text.textContent = 'اسکن صفحات: ' + d.done_pages + ' / ' + d.total_pages;
				} else if (d.phase === 'links') {
					pct = 60 + (d.links_total ? Math.round((d.links_checked / d.links_total) * 20) : 20);
					text.textContent = 'بررسی لینک‌ها: ' + d.links_checked + ' / ' + d.links_total;
				} else if (d.phase === 'assets') {
					pct = 80 + (d.assets_total ? Math.round((d.assets_checked / d.assets_total) * 20) : 20);
					text.textContent = 'بررسی فایل‌های CSS/JS: ' + d.assets_checked + ' / ' + d.assets_total;
				} else {
					pct = 100;
				}
				bar.style.width = Math.min(100, pct) + '%';

				if (d.status === 'done' || d.status === 'stopped') {
					running = false;
					startBtn.style.display = '';
					stopBtn.style.display = 'none';
					text.textContent += ' — تمام شد. برای دیدن نتایج به تب «لیست مشکلات» بروید.';
					return;
				}
				setTimeout(tick, 400);
			}).catch(function(){ setTimeout(tick, 1500); });
		}

		startBtn.addEventListener('click', function(){
			if (!confirm('اسکن کامل سایت ممکن است چند دقیقه طول بکشد. شروع شود؟')) { return; }
			startBtn.style.display = 'none';
			stopBtn.style.display = '';
			bar.style.width = '0%';
			post('gv_audit_start').then(function(res){
				if (res && res.success) {
					running = true;
					tick();
				} else {
					alert('خطا در شروع اسکن.');
					startBtn.style.display = '';
					stopBtn.style.display = 'none';
				}
			});
		});

		stopBtn.addEventListener('click', function(){
			running = false;
			post('gv_audit_stop').then(function(){
				startBtn.style.display = '';
				stopBtn.style.display = 'none';
				text.textContent = 'اسکن متوقف شد.';
			});
		});

		<?php if ( 'running' === $state['status'] ) : ?>
			running = true;
			startBtn.style.display = 'none';
			stopBtn.style.display = '';
			tick();
		<?php endif; ?>
	})();
	</script>
	<?php
}

function gv_audit_phase_label( $phase ) {
	$labels = array( 'pages' => 'اسکن صفحات', 'links' => 'بررسی لینک‌های خراب', 'assets' => 'بررسی سرعت فایل‌ها', 'finish' => 'پایان' );
	return $labels[ $phase ] ?? $phase;
}
