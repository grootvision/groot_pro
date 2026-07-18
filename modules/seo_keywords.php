<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — کلمات کلیدی سئو
 *  جمع‌آوری کلمه کلیدی فوکوس تمام نوشته‌ها/صفحات از افزونه‌های
 *  محبوب سئو (Yoast / RankMath / AIOSEO / SEOPress) در یک جدول
 *  واحد + خروجی CSV + نمایش نویسنده، تاریخ و کلمات تکراری
 * ==========================================================
 */
define( 'GV_SEO_NONCE', 'gv_seo_nonce_action' );

/**
 * افزونه‌های سئوی پشتیبانی‌شده و متاکی مربوط به کلمه کلیدی فوکوس هرکدام
 */
function gv_seo_supported_meta_keys() {
	return array(
		'_yoast_wpseo_focuskw'        => 'Yoast SEO',
		'rank_math_focus_keyword'     => 'Rank Math',
		'_aioseo_keyphrases'          => 'All in One SEO',
		'_aioseop_keywords'           => 'All in One SEO (نسخه قدیمی)',
		'_seopress_analysis_target_kw' => 'SEOPress',
	);
}

/**
 * متاکی‌هایی که افزونه‌های سئو برای علامت‌گذاری «کرنر استون» (محتوای ستون فقرات سایت)
 * استفاده می‌کنند. هر افزونه‌ای که این مقدار را روی «فعال» ثبت کند، آن محتوا
 * به‌عنوان کرنر استون شناخته می‌شود.
 */
function gv_seo_supported_cornerstone_meta_keys() {
	return array(
		'_yoast_wpseo_is_cornerstone' => 'Yoast SEO',
		'rank_math_pillar_content'    => 'Rank Math',
	);
}

/* ==========================================================================
   منوی مدیریت
   ========================================================================== */

add_action( 'admin_menu', 'gv_seo_admin_menu' );
function gv_seo_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'کلمات کلیدی سئو | Groot Vision',
		'🔑 کلمات کلیدی سئو',
		'manage_options',
		'gv-seo-keywords',
		'gv_seo_render_admin_page'
	);
}

/* ==========================================================================
   شمارش کلیک / بازدید محتوا
   ========================================================================== */

add_action( 'template_redirect', 'gv_seo_track_click' );
function gv_seo_track_click() {
	if ( is_admin() || ! is_singular() ) { return; }
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }

	$post_id = get_queried_object_id();
	if ( ! $post_id ) { return; }

	// جلوگیری ساده از شمارش ربات‌های شناخته‌شده
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
	if ( '' !== $ua && preg_match( '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit/i', $ua ) ) { return; }

	$count = (int) get_post_meta( $post_id, '_gv_seo_click_count', true );
	update_post_meta( $post_id, '_gv_seo_click_count', $count + 1 );
}

/**
 * تعداد کلیک/بازدید ثبت‌شده برای یک محتوا
 */
function gv_seo_get_click_count( $post_id ) {
	return (int) get_post_meta( $post_id, '_gv_seo_click_count', true );
}

/* ==========================================================================
   استخراج داده از دیتابیس
   ========================================================================== */

/**
 * تمام رکوردهای کلمه کلیدی موجود در سایت را برمی‌گرداند.
 * هر ردیف شامل: post_id, title, post_type, author, date, edit_link, view_link, plugin_label, keyword
 */
function gv_seo_collect_all_keywords() {
	global $wpdb;

	$meta_keys = array_keys( gv_seo_supported_meta_keys() );
	if ( empty( $meta_keys ) ) { return array(); }

	$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

	$sql = "
		SELECT p.ID, p.post_title, p.post_type, p.post_author, p.post_date, pm.meta_key, pm.meta_value
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
		WHERE pm.meta_key IN ($placeholders)
		AND pm.meta_value != ''
		AND p.post_status = 'publish'
		AND p.post_type NOT IN ('revision','attachment','nav_menu_item')
		ORDER BY p.post_type ASC, p.post_title ASC
	";

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $meta_keys ) ); // phpcs:ignore

	$plugin_labels = gv_seo_supported_meta_keys();
	$results       = array();

	foreach ( (array) $rows as $row ) {
		$keywords = gv_seo_extract_keywords_from_value( $row->meta_key, $row->meta_value );
		if ( empty( $keywords ) ) { continue; }

		foreach ( $keywords as $keyword ) {
			$keyword = trim( $keyword );
			if ( '' === $keyword ) { continue; }

			$results[] = array(
				'post_id'       => (int) $row->ID,
				'title'         => $row->post_title,
				'post_type'     => $row->post_type,
				'author'        => get_the_author_meta( 'display_name', $row->post_author ),
				'date'          => $row->post_date,
				'edit_link'     => get_edit_post_link( $row->ID, 'raw' ),
				'view_link'     => get_permalink( $row->ID ),
				'plugin_label'  => isset( $plugin_labels[ $row->meta_key ] ) ? $plugin_labels[ $row->meta_key ] : $row->meta_key,
				'keyword'       => $keyword,
				'clicks'        => gv_seo_get_click_count( $row->ID ),
				'is_cornerstone'=> (bool) gv_seo_is_cornerstone_post( $row->ID ),
			);
		}
	}

	return $results;
}

/**
 * مقدار خام ذخیره‌شده در دیتابیس (که فرمتش بسته به افزونه فرق دارد) را
 * به یک آرایه از کلمات کلیدی متنی تبدیل می‌کند.
 */
function gv_seo_extract_keywords_from_value( $meta_key, $raw_value ) {
	// افزونه All in One SEO جدید، به‌صورت JSON ذخیره می‌کند
	if ( '_aioseo_keyphrases' === $meta_key ) {
		$decoded = json_decode( $raw_value, true );
		$out     = array();
		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['focus']['keyphrase'] ) ) {
				$out[] = $decoded['focus']['keyphrase'];
			}
			if ( ! empty( $decoded['additional'] ) && is_array( $decoded['additional'] ) ) {
				foreach ( $decoded['additional'] as $extra ) {
					if ( ! empty( $extra['keyphrase'] ) ) { $out[] = $extra['keyphrase']; }
				}
			}
		}
		return $out;
	}

	// بقیه افزونه‌ها معمولاً متن ساده یا چند کلمه با کاما هستند
	$parts = explode( ',', $raw_value );
	return array_map( 'trim', $parts );
}

/**
 * لیست کلمات کلیدی به‌همراه تعداد تکرارشان در کل سایت
 */
function gv_seo_aggregate_keyword_frequency( $rows ) {
	$freq = array();
	foreach ( $rows as $row ) {
		$k = mb_strtolower( trim( $row['keyword'] ) );
		if ( '' === $k ) { continue; }
		if ( ! isset( $freq[ $k ] ) ) {
			$freq[ $k ] = array( 'keyword' => $row['keyword'], 'count' => 0 );
		}
		$freq[ $k ]['count']++;
	}
	uasort( $freq, function ( $a, $b ) { return $b['count'] <=> $a['count']; } );
	return $freq;
}

/**
 * فقط کلماتی که بیش از یک بار روی محتواهای مختلف استفاده شده‌اند
 * (یعنی کلمه کلیدی تکراری/دوباره‌کاری‌شده) به همراه لیست محتواهای مربوطه.
 */
function gv_seo_aggregate_duplicate_keywords( $rows ) {
	$grouped = array();

	foreach ( $rows as $row ) {
		$k = mb_strtolower( trim( $row['keyword'] ) );
		if ( '' === $k ) { continue; }

		if ( ! isset( $grouped[ $k ] ) ) {
			$grouped[ $k ] = array(
				'keyword' => $row['keyword'],
				'posts'   => array(),
			);
		}

		// جلوگیری از تکرار همان محتوا برای همان کلمه (مثلاً چند افزونه هم‌زمان فعال)
		$already = false;
		foreach ( $grouped[ $k ]['posts'] as $p ) {
			if ( $p['post_id'] === $row['post_id'] ) { $already = true; break; }
		}
		if ( ! $already ) {
			$grouped[ $k ]['posts'][] = array(
				'post_id'   => $row['post_id'],
				'title'     => $row['title'],
				'post_type' => $row['post_type'],
				'author'    => $row['author'],
				'date'      => $row['date'],
				'edit_link' => $row['edit_link'],
				'view_link' => $row['view_link'],
			);
		}
	}

	// فقط کلماتی که روی حداقل ۲ محتوای متفاوت استفاده شده‌اند
	$duplicates = array_filter( $grouped, function ( $item ) {
		return count( $item['posts'] ) > 1;
	} );

	uasort( $duplicates, function ( $a, $b ) {
		return count( $b['posts'] ) <=> count( $a['posts'] );
	} );

	return $duplicates;
}

/**
 * بررسی می‌کند آیا یک محتوا در هیچ‌کدام از افزونه‌های سئوی نصب‌شده به‌عنوان
 * «کرنر استون» علامت خورده است یا نه. در صورت مثبت بودن، نام افزونه را برمی‌گرداند.
 */
function gv_seo_is_cornerstone_post( $post_id ) {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) { return $cache[ $post_id ]; }

	$truthy = array( '1', 'on', 'yes', 'true' );
	foreach ( gv_seo_supported_cornerstone_meta_keys() as $meta_key => $label ) {
		$val = get_post_meta( $post_id, $meta_key, true );
		if ( in_array( strtolower( trim( (string) $val ) ), $truthy, true ) ) {
			$cache[ $post_id ] = $label;
			return $label;
		}
	}

	$cache[ $post_id ] = false;
	return false;
}

/**
 * کلمات کلیدی ثبت‌شده برای یک محتوای مشخص (از هر افزونه سئوی نصب‌شده)
 */
function gv_seo_get_post_keywords( $post_id ) {
	$out = array();
	foreach ( array_keys( gv_seo_supported_meta_keys() ) as $meta_key ) {
		$val = get_post_meta( $post_id, $meta_key, true );
		if ( '' === $val ) { continue; }
		foreach ( gv_seo_extract_keywords_from_value( $meta_key, $val ) as $k ) {
			$k = trim( $k );
			if ( '' !== $k ) { $out[] = $k; }
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * تمام محتواهای منتشرشده‌ای که به‌عنوان کرنر استون علامت خورده‌اند،
 * به‌همراه تعداد کلیک و کلمات کلیدی‌شان (برای سنجش قدرت واقعی هر کرنر استون)
 */
function gv_seo_collect_cornerstone_posts() {
	global $wpdb;

	$meta_keys = array_keys( gv_seo_supported_cornerstone_meta_keys() );
	if ( empty( $meta_keys ) ) { return array(); }

	$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

	$sql = "
		SELECT p.ID, p.post_title, p.post_type, p.post_author, p.post_date, pm.meta_key, pm.meta_value
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
		WHERE pm.meta_key IN ($placeholders)
		AND p.post_status = 'publish'
		AND p.post_type NOT IN ('revision','attachment','nav_menu_item')
	";

	$raw    = $wpdb->get_results( $wpdb->prepare( $sql, $meta_keys ) ); // phpcs:ignore
	$labels = gv_seo_supported_cornerstone_meta_keys();
	$truthy = array( '1', 'on', 'yes', 'true' );
	$posts  = array();

	foreach ( (array) $raw as $r ) {
		$val = strtolower( trim( (string) $r->meta_value ) );
		if ( ! in_array( $val, $truthy, true ) ) { continue; }
		if ( isset( $posts[ $r->ID ] ) ) { continue; } // قبلاً توسط افزونه دیگری علامت خورده

		$posts[ $r->ID ] = array(
			'post_id'      => (int) $r->ID,
			'title'        => $r->post_title,
			'post_type'    => $r->post_type,
			'author'       => get_the_author_meta( 'display_name', $r->post_author ),
			'date'         => $r->post_date,
			'edit_link'    => get_edit_post_link( $r->ID, 'raw' ),
			'view_link'    => get_permalink( $r->ID ),
			'plugin_label' => isset( $labels[ $r->meta_key ] ) ? $labels[ $r->meta_key ] : $r->meta_key,
			'clicks'       => gv_seo_get_click_count( $r->ID ),
		);
	}

	foreach ( $posts as $id => &$p ) {
		$p['keywords']      = gv_seo_get_post_keywords( $id );
		$p['keyword_count'] = count( $p['keywords'] );
	}
	unset( $p );

	uasort( $posts, function ( $a, $b ) { return $b['clicks'] <=> $a['clicks']; } );

	return $posts;
}

/* ==========================================================================
   خروجی CSV
   ========================================================================== */

add_action( 'admin_post_gv_seo_export_csv', 'gv_seo_export_csv' );
function gv_seo_export_csv() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SEO_NONCE );

	$rows = gv_seo_collect_all_keywords();

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=groot-vision-seo-keywords-' . gmdate( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	// BOM برای نمایش صحیح حروف فارسی در اکسل
	fwrite( $output, "\xEF\xBB\xBF" );

	fputcsv( $output, array( 'عنوان محتوا', 'نوع محتوا', 'نویسنده', 'تاریخ انتشار', 'کلمه کلیدی', 'افزونه سئو', 'کرنر استون', 'تعداد کلیک', 'لینک انتشار' ) );

	foreach ( $rows as $row ) {
		fputcsv( $output, array(
			$row['title'],
			$row['post_type'],
			$row['author'],
			mysql2date( 'Y-m-d', $row['date'] ),
			$row['keyword'],
			$row['plugin_label'],
			$row['is_cornerstone'] ? 'بله' : 'خیر',
			$row['clicks'],
			$row['view_link'],
		) );
	}

	fclose( $output );
	exit;
}

/* ==========================================================================
   صفحه مدیریت
   ========================================================================== */

function gv_seo_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$rows        = gv_seo_collect_all_keywords();
	$frequency   = gv_seo_aggregate_keyword_frequency( $rows );
	$duplicates  = gv_seo_aggregate_duplicate_keywords( $rows );
	$cornerstone = gv_seo_collect_cornerstone_posts();
	$tab         = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'list';
	?>
	<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif;">
		<style>
			.gvseo-header{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,#b45309,#f59e0b);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;box-shadow:0 6px 20px rgba(0,0,0,.12);flex-wrap:wrap;gap:12px;}
			.gvseo-header h1{margin:0;font-size:20px;color:#fff;}
			.gvseo-header span{opacity:.9;font-size:12.5px;}
			.gvseo-btn-export{background:#fff;color:#b45309;font-weight:800;padding:10px 20px;border-radius:10px;text-decoration:none;font-size:13px;white-space:nowrap;}
			.gvseo-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap;}
			.gvseo-tab-btn{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:9px 18px;cursor:pointer;font-size:13px;font-family:inherit;font-weight:600;text-decoration:none;color:#1e293b;}
			.gvseo-tab-btn.is-active{background:#b45309;color:#fff;border-color:#b45309;}
			.gvseo-stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;max-width:1100px;}
			@media(max-width:900px){.gvseo-stat-cards{grid-template-columns:1fr 1fr;}}
			@media(max-width:600px){.gvseo-stat-cards{grid-template-columns:1fr;}}
			.gvseo-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px;}
			.gvseo-stat b{display:block;font-size:26px;color:#b45309;}
			.gvseo-stat.is-warning b{color:#dc2626;}
			.gvseo-stat span{font-size:13px;color:#64748b;}
			.gvseo-table-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;max-width:1100px;}
			.gvseo-table{width:100%;border-collapse:collapse;}
			.gvseo-table th{background:#fef3c7;color:#78350f;padding:10px 14px;text-align:right;font-size:12.5px;white-space:nowrap;}
			.gvseo-table td{padding:9px 14px;border-top:1px solid #f1f5f9;font-size:12.5px;}
			.gvseo-table code{direction:ltr;display:inline-block;background:#f1f5f9;color:#334155;padding:2px 8px;border-radius:6px;font-size:11.5px;}
			.gvseo-tag{background:#eef2ff;color:#3730a3;font-size:11px;padding:2px 8px;border-radius:10px;white-space:nowrap;}
			.gvseo-empty{padding:30px;text-align:center;color:#94a3b8;font-size:13px;}
			.gvseo-freq-bar{background:#fef3c7;border-radius:6px;height:8px;overflow:hidden;margin-top:4px;}
			.gvseo-freq-bar i{display:block;height:100%;background:#f59e0b;}
			.gvseo-dup-block{border-top:1px solid #f1f5f9;padding:14px 16px;}
			.gvseo-dup-block:first-child{border-top:none;}
			.gvseo-dup-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;}
			.gvseo-dup-head b{font-size:14px;color:#1e293b;}
			.gvseo-dup-count{background:#fee2e2;color:#b91c1c;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:20px;}
			.gvseo-dup-posts{width:100%;border-collapse:collapse;background:#fafafa;border-radius:8px;overflow:hidden;}
			.gvseo-dup-posts th{background:#f1f5f9;color:#475569;padding:7px 12px;font-size:11.5px;text-align:right;}
			.gvseo-dup-posts td{padding:7px 12px;font-size:11.5px;border-top:1px solid #eef0f2;}
			.gvseo-cs-badge{background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap;}
			.gvseo-cs-none{color:#cbd5e1;font-size:11px;}
			.gvseo-click{font-weight:700;color:#0f766e;}
			.gvseo-sortable thead th{cursor:pointer;user-select:none;position:relative;}
			.gvseo-sortable thead th:hover{background:#fde8b0;}
			.gvseo-sortable thead th.no-sort{cursor:default;}
			.gvseo-sortable thead th.no-sort:hover{background:#fef3c7;}
			.gvseo-sort-arrow{font-size:10px;margin-right:4px;opacity:.7;}
			.gvseo-hint{font-size:11px;color:#94a3b8;margin:0 0 10px;}
		</style>

		<div class="gvseo-header">
			<div>
				<h1>🔑 کلمات کلیدی سئو — Groot Vision</h1>
				<span>تمام کلمات کلیدی فوکوس نوشته‌ها و صفحات سایت، یک‌جا و آماده خروجی</span>
			</div>
			<a class="gvseo-btn-export" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_seo_export_csv' ), GV_SEO_NONCE ) ); ?>">📥 دانلود خروجی CSV</a>
		</div>

		<div class="gvseo-stat-cards">
			<div class="gvseo-stat"><b><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></b><span>کلمه کلیدی ثبت‌شده روی محتوا</span></div>
			<div class="gvseo-stat"><b><?php echo esc_html( number_format_i18n( count( $frequency ) ) ); ?></b><span>کلمه کلیدی یکتا در کل سایت</span></div>
			<div class="gvseo-stat"><b><?php echo esc_html( number_format_i18n( count( array_unique( wp_list_pluck( $rows, 'post_id' ) ) ) ) ); ?></b><span>محتوای دارای کلمه کلیدی</span></div>
			<div class="gvseo-stat is-warning"><b><?php echo esc_html( number_format_i18n( count( $duplicates ) ) ); ?></b><span>کلمه کلیدی تکراری بین چند محتوا</span></div>
			<div class="gvseo-stat"><b><?php echo esc_html( number_format_i18n( count( $cornerstone ) ) ); ?></b><span>محتوای کرنر استون</span></div>
		</div>

		<div class="gvseo-tabs">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-seo-keywords&tab=list' ) ); ?>" class="gvseo-tab-btn <?php echo 'list' === $tab ? 'is-active' : ''; ?>">📋 لیست کامل بر اساس محتوا</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-seo-keywords&tab=unique' ) ); ?>" class="gvseo-tab-btn <?php echo 'unique' === $tab ? 'is-active' : ''; ?>">📊 کلمات کلیدی یکتا و تکرار</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-seo-keywords&tab=duplicate' ) ); ?>" class="gvseo-tab-btn <?php echo 'duplicate' === $tab ? 'is-active' : ''; ?>">⚠️ کلمات کلیدی تکراری</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-seo-keywords&tab=cornerstone' ) ); ?>" class="gvseo-tab-btn <?php echo 'cornerstone' === $tab ? 'is-active' : ''; ?>">🏛️ محتوای کرنر استون</a>
		</div>

		<?php if ( 'unique' === $tab ) : ?>

			<div class="gvseo-table-wrap">
				<?php if ( empty( $frequency ) ) : ?>
					<div class="gvseo-empty">هنوز هیچ کلمه کلیدی‌ای در سایت ثبت نشده است.</div>
				<?php else :
					$max_count = max( wp_list_pluck( $frequency, 'count' ) );
					?>
					<p class="gvseo-hint">روی سرستون‌ها کلیک کنید تا جدول بر اساس آن مرتب شود.</p>
					<table class="gvseo-table gvseo-sortable">
						<thead><tr><th data-sort-type="text">کلمه کلیدی</th><th data-sort-type="number">تعداد تکرار</th><th class="no-sort" style="width:200px;">نمودار</th></tr></thead>
						<tbody>
						<?php foreach ( $frequency as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item['keyword'] ); ?></td>
								<td data-sort-value="<?php echo esc_attr( $item['count'] ); ?>"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></td>
								<td>
									<div class="gvseo-freq-bar"><i style="width:<?php echo esc_attr( round( ( $item['count'] / max( 1, $max_count ) ) * 100 ) ); ?>%;"></i></div>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		<?php elseif ( 'duplicate' === $tab ) : ?>

			<div class="gvseo-table-wrap">
				<?php if ( empty( $duplicates ) ) : ?>
					<div class="gvseo-empty">هیچ کلمه کلیدی تکراری‌ای پیدا نشد؛ همه کلمات کلیدی سایت یکتا هستند. 👌</div>
				<?php else : ?>
					<?php foreach ( $duplicates as $item ) : ?>
						<div class="gvseo-dup-block">
							<div class="gvseo-dup-head">
								<b><?php echo esc_html( $item['keyword'] ); ?></b>
								<span class="gvseo-dup-count"><?php echo esc_html( number_format_i18n( count( $item['posts'] ) ) ); ?> محتوا از این کلمه استفاده کرده‌اند</span>
							</div>
							<table class="gvseo-dup-posts gvseo-sortable">
								<thead>
									<tr>
										<th data-sort-type="text">عنوان</th>
										<th data-sort-type="text">نوع محتوا</th>
										<th data-sort-type="text">نویسنده</th>
										<th data-sort-type="date">تاریخ انتشار</th>
										<th class="no-sort">لینک</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $item['posts'] as $p ) : ?>
										<tr>
											<td><a href="<?php echo esc_url( $p['edit_link'] ); ?>"><?php echo esc_html( $p['title'] ); ?></a></td>
											<td><span class="gvseo-tag"><?php echo esc_html( $p['post_type'] ); ?></span></td>
											<td><?php echo esc_html( $p['author'] ); ?></td>
											<td data-sort-value="<?php echo esc_attr( mysql2date( 'U', $p['date'] ) ); ?>"><?php echo esc_html( mysql2date( 'Y/m/d', $p['date'] ) ); ?></td>
											<td><a href="<?php echo esc_url( $p['view_link'] ); ?>" target="_blank" rel="noopener">مشاهده ↗</a></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

		<?php elseif ( 'cornerstone' === $tab ) : ?>

			<div class="gvseo-table-wrap">
				<?php if ( empty( $cornerstone ) ) : ?>
					<div class="gvseo-empty">
						هیچ محتوایی به‌عنوان کرنر استون علامت‌گذاری نشده است.
						<br><br>
						کرنر استون یعنی مهم‌ترین و جامع‌ترین محتواهای سایت که ستون فقرات استراتژی سئو هستند و باید بیشترین لینک داخلی را به سمت خودشان بگیرند. این علامت‌گذاری معمولاً در بخش ادیت هر نوشته، در کادر تنظیمات Yoast SEO (گزینه «Cornerstone content») یا Rank Math (گزینه «Pillar Content») قابل فعال‌سازی است.
					</div>
				<?php else : ?>
					<p class="gvseo-hint">روی سرستون‌ها کلیک کنید تا جدول بر اساس آن مرتب شود. کرنر استون‌هایی با کلیک کم یا کلمه کلیدی ضعیف، اولویت بازبینی دارند.</p>
					<table class="gvseo-table gvseo-sortable">
						<thead>
							<tr>
								<th data-sort-type="text">عنوان</th>
								<th data-sort-type="text">نوع محتوا</th>
								<th data-sort-type="text">نویسنده</th>
								<th data-sort-type="date">تاریخ انتشار</th>
								<th data-sort-type="text">افزونه سئو</th>
								<th data-sort-type="number">تعداد کلمه کلیدی</th>
								<th data-sort-type="number">تعداد کلیک</th>
								<th class="no-sort">لینک</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $cornerstone as $c ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $c['edit_link'] ); ?>"><?php echo esc_html( $c['title'] ); ?></a></td>
								<td><span class="gvseo-tag"><?php echo esc_html( $c['post_type'] ); ?></span></td>
								<td><?php echo esc_html( $c['author'] ); ?></td>
								<td data-sort-value="<?php echo esc_attr( mysql2date( 'U', $c['date'] ) ); ?>"><?php echo esc_html( mysql2date( 'Y/m/d', $c['date'] ) ); ?></td>
								<td><?php echo esc_html( $c['plugin_label'] ); ?></td>
								<td data-sort-value="<?php echo esc_attr( $c['keyword_count'] ); ?>">
									<?php echo $c['keyword_count'] ? esc_html( implode( '، ', $c['keywords'] ) ) : '<span class="gvseo-cs-none">بدون کلمه کلیدی</span>'; ?>
								</td>
								<td class="gvseo-click" data-sort-value="<?php echo esc_attr( $c['clicks'] ); ?>"><?php echo esc_html( number_format_i18n( $c['clicks'] ) ); ?></td>
								<td><a href="<?php echo esc_url( $c['view_link'] ); ?>" target="_blank" rel="noopener">مشاهده ↗</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		<?php else : ?>

			<div class="gvseo-table-wrap">
				<?php if ( empty( $rows ) ) : ?>
					<div class="gvseo-empty">
						هیچ کلمه کلیدی‌ای پیدا نشد. مطمئن شوید حداقل یکی از افزونه‌های سئوی زیر روی سایت نصب است و برای محتواها کلمه کلیدی فوکوس تعیین شده:
						<br><br>
						<?php foreach ( gv_seo_supported_meta_keys() as $label ) : ?>
							<span class="gvseo-tag"><?php echo esc_html( $label ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="gvseo-hint">روی سرستون‌ها کلیک کنید تا جدول بر اساس آن مرتب شود.</p>
					<table class="gvseo-table gvseo-sortable">
						<thead>
							<tr>
								<th data-sort-type="text">عنوان</th>
								<th data-sort-type="text">نوع محتوا</th>
								<th data-sort-type="text">نویسنده</th>
								<th data-sort-type="date">تاریخ انتشار</th>
								<th data-sort-type="text">کلمه کلیدی</th>
								<th data-sort-type="text">افزونه سئو</th>
								<th data-sort-type="text">کرنر استون</th>
								<th data-sort-type="number">تعداد کلیک</th>
								<th class="no-sort">لینک</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $row['edit_link'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
								<td><span class="gvseo-tag"><?php echo esc_html( $row['post_type'] ); ?></span></td>
								<td><?php echo esc_html( $row['author'] ); ?></td>
								<td data-sort-value="<?php echo esc_attr( mysql2date( 'U', $row['date'] ) ); ?>"><?php echo esc_html( mysql2date( 'Y/m/d', $row['date'] ) ); ?></td>
								<td><code><?php echo esc_html( $row['keyword'] ); ?></code></td>
								<td><?php echo esc_html( $row['plugin_label'] ); ?></td>
								<td>
									<?php if ( $row['is_cornerstone'] ) : ?>
										<span class="gvseo-cs-badge">🏛️ کرنر استون</span>
									<?php else : ?>
										<span class="gvseo-cs-none">—</span>
									<?php endif; ?>
								</td>
								<td class="gvseo-click" data-sort-value="<?php echo esc_attr( $row['clicks'] ); ?>"><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td>
								<td><a href="<?php echo esc_url( $row['view_link'] ); ?>" target="_blank" rel="noopener">مشاهده ↗</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		<?php endif; ?>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong> | اینستاگرام: grootvision</p>
	</div>

	<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.gvseo-sortable' ).forEach( function ( table ) {
			var headerRow = table.querySelector( 'thead tr' );
			if ( ! headerRow ) { return; }
			var ths = Array.prototype.slice.call( headerRow.children );

			ths.forEach( function ( th, index ) {
				if ( th.classList.contains( 'no-sort' ) ) { return; }

				th.addEventListener( 'click', function () {
					var tbody = table.querySelector( 'tbody' );
					if ( ! tbody ) { return; }
					var rows = Array.prototype.slice.call( tbody.querySelectorAll( 'tr' ) );
					var ascending = th.getAttribute( 'data-sort-dir' ) !== 'asc';

					ths.forEach( function ( t ) {
						t.removeAttribute( 'data-sort-dir' );
						var arrow = t.querySelector( '.gvseo-sort-arrow' );
						if ( arrow ) { arrow.remove(); }
					} );
					th.setAttribute( 'data-sort-dir', ascending ? 'asc' : 'desc' );

					var type = th.getAttribute( 'data-sort-type' ) || 'text';

					rows.sort( function ( rowA, rowB ) {
						var cellA = rowA.children[ index ];
						var cellB = rowB.children[ index ];
						var valA = cellA.getAttribute( 'data-sort-value' );
						var valB = cellB.getAttribute( 'data-sort-value' );
						if ( null === valA ) { valA = cellA.textContent.trim(); }
						if ( null === valB ) { valB = cellB.textContent.trim(); }

						if ( 'number' === type ) {
							valA = parseFloat( valA ) || 0;
							valB = parseFloat( valB ) || 0;
							return ascending ? valA - valB : valB - valA;
						}

						if ( 'date' === type ) {
							valA = parseInt( valA, 10 ) || 0;
							valB = parseInt( valB, 10 ) || 0;
							return ascending ? valA - valB : valB - valA;
						}

						return ascending ? valA.localeCompare( valB, 'fa' ) : valB.localeCompare( valA, 'fa' );
					} );

					rows.forEach( function ( row ) { tbody.appendChild( row ); } );

					var arrow = document.createElement( 'span' );
					arrow.className = 'gvseo-sort-arrow';
					arrow.textContent = ascending ? '▲' : '▼';
					th.appendChild( arrow );
				} );
			} );
		} );
	} );
	</script>
	<?php
}