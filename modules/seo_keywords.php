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
				'post_id'      => (int) $row->ID,
				'title'        => $row->post_title,
				'post_type'    => $row->post_type,
				'author'       => get_the_author_meta( 'display_name', $row->post_author ),
				'date'         => $row->post_date,
				'edit_link'    => get_edit_post_link( $row->ID, 'raw' ),
				'view_link'    => get_permalink( $row->ID ),
				'plugin_label' => isset( $plugin_labels[ $row->meta_key ] ) ? $plugin_labels[ $row->meta_key ] : $row->meta_key,
				'keyword'      => $keyword,
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

	fputcsv( $output, array( 'عنوان محتوا', 'نوع محتوا', 'نویسنده', 'تاریخ انتشار', 'کلمه کلیدی', 'افزونه سئو', 'لینک انتشار' ) );

	foreach ( $rows as $row ) {
		fputcsv( $output, array(
			$row['title'],
			$row['post_type'],
			$row['author'],
			mysql2date( 'Y-m-d', $row['date'] ),
			$row['keyword'],
			$row['plugin_label'],
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

	$rows       = gv_seo_collect_all_keywords();
	$frequency  = gv_seo_aggregate_keyword_frequency( $rows );
	$duplicates = gv_seo_aggregate_duplicate_keywords( $rows );
	$tab        = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'list';
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
		</div>

		<div class="gvseo-tabs">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-seo-keywords&tab=list' ) ); ?>" class="gvseo-tab-btn <?php echo 'list' === $tab ? 'is-active' : ''; ?>">📋 لیست کامل بر اساس محتوا</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-seo-keywords&tab=unique' ) ); ?>" class="gvseo-tab-btn <?php echo 'unique' === $tab ? 'is-active' : ''; ?>">📊 کلمات کلیدی یکتا و تکرار</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-seo-keywords&tab=duplicate' ) ); ?>" class="gvseo-tab-btn <?php echo 'duplicate' === $tab ? 'is-active' : ''; ?>">⚠️ کلمات کلیدی تکراری</a>
		</div>

		<?php if ( 'unique' === $tab ) : ?>

			<div class="gvseo-table-wrap">
				<?php if ( empty( $frequency ) ) : ?>
					<div class="gvseo-empty">هنوز هیچ کلمه کلیدی‌ای در سایت ثبت نشده است.</div>
				<?php else :
					$max_count = max( wp_list_pluck( $frequency, 'count' ) );
					?>
					<table class="gvseo-table">
						<thead><tr><th>کلمه کلیدی</th><th>تعداد تکرار</th><th style="width:200px;">نمودار</th></tr></thead>
						<tbody>
						<?php foreach ( $frequency as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item['keyword'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></td>
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
							<table class="gvseo-dup-posts">
								<thead>
									<tr>
										<th>عنوان</th>
										<th>نوع محتوا</th>
										<th>نویسنده</th>
										<th>تاریخ انتشار</th>
										<th>لینک</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $item['posts'] as $p ) : ?>
										<tr>
											<td><a href="<?php echo esc_url( $p['edit_link'] ); ?>"><?php echo esc_html( $p['title'] ); ?></a></td>
											<td><span class="gvseo-tag"><?php echo esc_html( $p['post_type'] ); ?></span></td>
											<td><?php echo esc_html( $p['author'] ); ?></td>
											<td><?php echo esc_html( mysql2date( 'Y/m/d', $p['date'] ) ); ?></td>
											<td><a href="<?php echo esc_url( $p['view_link'] ); ?>" target="_blank" rel="noopener">مشاهده ↗</a></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>
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
					<table class="gvseo-table">
						<thead>
							<tr>
								<th>عنوان</th>
								<th>نوع محتوا</th>
								<th>نویسنده</th>
								<th>تاریخ انتشار</th>
								<th>کلمه کلیدی</th>
								<th>افزونه سئو</th>
								<th>لینک</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $row['edit_link'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
								<td><span class="gvseo-tag"><?php echo esc_html( $row['post_type'] ); ?></span></td>
								<td><?php echo esc_html( $row['author'] ); ?></td>
								<td><?php echo esc_html( mysql2date( 'Y/m/d', $row['date'] ) ); ?></td>
								<td><code><?php echo esc_html( $row['keyword'] ); ?></code></td>
								<td><?php echo esc_html( $row['plugin_label'] ); ?></td>
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
	<?php
}