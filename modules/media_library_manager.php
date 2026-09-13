<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — مدیریت فایل‌های چندرسانه‌ای
 *  ------------------------------------------------------------
 *  چه کاری می‌کند:
 *  ۱) تمام عکس‌ها، ویدیوها و فایل‌های آپلودشده در کتابخانه رسانه
 *     وردپرس را در یک صفحه خوشگل و مدیریت‌پذیر لیست می‌کند.
 *  ۲) فایل‌هایی که هیچ‌جای سایت استفاده نشده‌اند را مشخص می‌کند
 *     (نه تصویر شاخص، نه داخل محتوا، نه در سازنده صفحه/ویجت/قالب).
 *  ۳) فایل‌های استفاده‌شده را همراه با «کجا استفاده شده‌اند» نشان می‌دهد
 *     (لینک مستقیم به همان نوشته/صفحه برای ویرایش یا مشاهده).
 *  ۴) حجم هر فایل را نشان می‌دهد و کل جدول از نظر حجم، نام، نوع،
 *     وضعیت استفاده و تاریخ آپلود قابل سورت و فیلتر و جستجوست.
 *
 *  نکته فنی: اسکن کل سایت (پیدا کردن محل استفاده هر فایل) کمی زمان‌بر
 *  است، به همین دلیل نتیجه در یک transient کش می‌شود و فقط با زدن
 *  دکمه «اسکن مجدد» یا بعد از ۲۴ ساعت، دوباره محاسبه می‌شود.
 * ==========================================================
 */

define( 'GV_MLM_PAGE_SLUG', 'gv-media-manager' );
define( 'GV_MLM_TRANSIENT', 'gv_mlm_scan_data' );
define( 'GV_MLM_NONCE', 'gv_mlm_nonce_action' );

/* ==========================================================================
   ۱) منوی مدیریت (زیرمنوی هاب گروت ویژن)
   ========================================================================== */
add_action( 'admin_menu', 'gv_mlm_admin_menu' );
function gv_mlm_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'مدیریت فایل‌های چندرسانه‌ای | Groot Vision',
		'🖼️ مدیریت رسانه‌ها',
		'manage_options',
		GV_MLM_PAGE_SLUG,
		'gv_mlm_render_admin_page'
	);
}

/* ==========================================================================
   ۲) کمک‌تابع‌ها
   ========================================================================== */

/**
 * حجم بایت را به فرمت خوانا (کیلوبایت/مگابایت/گیگابایت) تبدیل می‌کند.
 */
function gv_mlm_format_size( $bytes ) {
	$bytes = (int) $bytes;
	if ( $bytes <= 0 ) { return '—'; }

	$units = array( 'بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت' );
	$i     = 0;
	$value = $bytes;
	while ( $value >= 1024 && $i < count( $units ) - 1 ) {
		$value /= 1024;
		$i++;
	}
	return number_format_i18n( $value, ( $i > 0 ? 2 : 0 ) ) . ' ' . $units[ $i ];
}

/**
 * گروه‌بندی نوع فایل بر اساس mime‌type برای فیلتر و آیکون.
 */
function gv_mlm_mime_group( $mime ) {
	if ( 0 === strpos( (string) $mime, 'image/' ) ) { return 'image'; }
	if ( 0 === strpos( (string) $mime, 'video/' ) ) { return 'video'; }
	if ( 0 === strpos( (string) $mime, 'audio/' ) ) { return 'audio'; }
	if ( in_array( $mime, array( 'application/pdf' ), true ) ) { return 'document'; }
	if ( 0 === strpos( (string) $mime, 'application/' ) || 0 === strpos( (string) $mime, 'text/' ) ) { return 'document'; }
	return 'other';
}

function gv_mlm_type_label( $group ) {
	$labels = array(
		'image'    => 'تصویر',
		'video'    => 'ویدیو',
		'audio'    => 'صوت',
		'document' => 'سند',
		'other'    => 'سایر',
	);
	return isset( $labels[ $group ] ) ? $labels[ $group ] : 'سایر';
}

function gv_mlm_type_icon( $group ) {
	$icons = array(
		'image'    => '🖼️',
		'video'    => '🎬',
		'audio'    => '🎵',
		'document' => '📄',
		'other'    => '📁',
	);
	return isset( $icons[ $group ] ) ? $icons[ $group ] : '📁';
}

/* ==========================================================================
   ۳) خواندن تمام رسانه‌های سایت از دیتابیس
   ========================================================================== */
function gv_mlm_get_all_attachments() {
	global $wpdb;

	$posts = $wpdb->get_results(
		"SELECT ID, post_title, post_date, post_mime_type
		 FROM {$wpdb->posts}
		 WHERE post_type = 'attachment'
		 ORDER BY post_date DESC"
	);

	$items = array();
	foreach ( $posts as $p ) {
		$file_path = get_attached_file( $p->ID );
		$exists    = $file_path && file_exists( $file_path );
		$size      = $exists ? (int) filesize( $file_path ) : 0;

		$attached_meta = get_post_meta( $p->ID, '_wp_attached_file', true );
		$filename      = $attached_meta ? basename( $attached_meta ) : basename( (string) $file_path );
		$group         = gv_mlm_mime_group( $p->post_mime_type );

		$thumb = ( 'image' === $group ) ? wp_get_attachment_image_url( $p->ID, 'thumbnail' ) : '';

		$items[ (int) $p->ID ] = array(
			'id'          => (int) $p->ID,
			'title'       => $p->post_title ? $p->post_title : $filename,
			'filename'    => $filename,
			'mime'        => $p->post_mime_type,
			'type_group'  => $group,
			'size'        => $size,
			'file_exists' => $exists,
			'date'        => $p->post_date,
			'timestamp'   => mysql2date( 'U', $p->post_date ),
			'url'         => wp_get_attachment_url( $p->ID ),
			'thumb'       => $thumb,
			'edit_link'   => get_edit_post_link( $p->ID, 'raw' ),
		);
	}
	return $items;
}

/* ==========================================================================
   ۴) پیدا کردن محل استفاده‌ی هر فایل در سایت
   ========================================================================== */
function gv_mlm_find_usage_for_attachment( $attachment_id, $filename ) {
	global $wpdb;
	$usages = array();

	/* ۱) تصویر شاخص نوشته/صفحه/محصول */
	$featured = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.ID, p.post_title
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = %d
		 AND p.post_status NOT IN ('trash','auto-draft')
		 LIMIT 30",
		$attachment_id
	) );
	foreach ( $featured as $row ) {
		$usages[] = array(
			'label'     => 'تصویر شاخص',
			'title'     => $row->post_title ? $row->post_title : '(بدون عنوان)',
			'edit_link' => get_edit_post_link( $row->ID, 'raw' ),
			'view_link' => get_permalink( $row->ID ),
		);
	}

	/* ۲) پیوست مستقیم به یک نوشته (post_parent خود فایل) */
	$parent_id = wp_get_post_parent_id( $attachment_id );
	if ( $parent_id ) {
		$parent = get_post( $parent_id );
		if ( $parent && 'trash' !== $parent->post_status ) {
			$usages[] = array(
				'label'     => 'پیوست به نوشته',
				'title'     => $parent->post_title ? $parent->post_title : '(بدون عنوان)',
				'edit_link' => get_edit_post_link( $parent->ID, 'raw' ),
				'view_link' => get_permalink( $parent->ID ),
			);
		}
	}

	if ( $filename ) {
		$like = '%' . $wpdb->esc_like( $filename ) . '%';

		/* ۳) داخل متن نوشته‌ها/صفحات/محصولات */
		$content_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title FROM {$wpdb->posts}
			 WHERE post_content LIKE %s
			 AND post_type NOT IN ('revision','attachment','nav_menu_item')
			 AND post_status NOT IN ('trash','auto-draft')
			 LIMIT 30",
			$like
		) );
		foreach ( $content_rows as $row ) {
			$usages[] = array(
				'label'     => 'داخل محتوای نوشته',
				'title'     => $row->post_title ? $row->post_title : '(بدون عنوان)',
				'edit_link' => get_edit_post_link( $row->ID, 'raw' ),
				'view_link' => get_permalink( $row->ID ),
			);
		}

		/* ۴) فیلدهای سفارشی، سازنده صفحه (المنتور/وردپرس‌بیکری)، گالری ووکامرس، ACF و ... */
		$meta_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.post_id, p.post_title FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_value LIKE %s
			 AND pm.meta_key NOT IN ('_thumbnail_id','_wp_attached_file','_wp_attachment_metadata')
			 AND p.post_status NOT IN ('trash','auto-draft')
			 LIMIT 30",
			$like
		) );
		foreach ( $meta_rows as $row ) {
			$usages[] = array(
				'label'     => 'فیلد سفارشی / سازنده صفحه',
				'title'     => $row->post_title ? $row->post_title : '(بدون عنوان)',
				'edit_link' => get_edit_post_link( $row->post_id, 'raw' ),
				'view_link' => get_permalink( $row->post_id ),
			);
		}

		/* ۵) ویجت‌های سایت */
		$widget_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE %s AND option_value LIKE %s
			 LIMIT 10",
			$wpdb->esc_like( 'widget_' ) . '%',
			$like
		) );
		foreach ( $widget_rows as $row ) {
			$usages[] = array(
				'label'     => 'ویجت سایت',
				'title'     => $row->option_name,
				'edit_link' => admin_url( 'widgets.php' ),
				'view_link' => '',
			);
		}

		/* ۶) شخصی‌سازی قالب (لوگو، بک‌گراند، آیکون سایت و ...) */
		$theme_mod_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE %s AND option_value LIKE %s
			 LIMIT 5",
			$wpdb->esc_like( 'theme_mods_' ) . '%',
			$like
		) );
		foreach ( $theme_mod_rows as $row ) {
			$usages[] = array(
				'label'     => 'شخصی‌سازی قالب (Customizer)',
				'title'     => 'تنظیمات ظاهری قالب',
				'edit_link' => admin_url( 'customize.php' ),
				'view_link' => '',
			);
		}
	}

	/* حذف موارد کاملاً تکراری (مثلاً هم در محتوا و هم در متا پیدا شده باشد) */
	$unique = array();
	$seen   = array();
	foreach ( $usages as $u ) {
		$key = $u['label'] . '|' . $u['title'] . '|' . $u['edit_link'];
		if ( isset( $seen[ $key ] ) ) { continue; }
		$seen[ $key ] = true;
		$unique[]     = $u;
	}

	return $unique;
}

/* ==========================================================================
   ۵) اجرای اسکن کامل + کش کردن نتیجه
   ========================================================================== */
function gv_mlm_run_full_scan() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore
	}

	$attachments = gv_mlm_get_all_attachments();

	$items = array();
	foreach ( $attachments as $att ) {
		$usages           = gv_mlm_find_usage_for_attachment( $att['id'], $att['filename'] );
		$att['usages']    = $usages;
		$att['is_used']   = ! empty( $usages );
		$items[]          = $att;
	}

	$data = array(
		'scanned_at' => current_time( 'timestamp' ), // phpcs:ignore
		'items'      => $items,
	);

	set_transient( GV_MLM_TRANSIENT, $data, DAY_IN_SECONDS );
	return $data;
}

/* ==========================================================================
   ۶) اکشن AJAX دکمه «اسکن مجدد»
   ========================================================================== */
add_action( 'wp_ajax_gv_mlm_rescan', 'gv_mlm_ajax_rescan' );
function gv_mlm_ajax_rescan() {
	check_ajax_referer( GV_MLM_NONCE, 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'شما اجازه‌ی این کار را ندارید.' ) );
	}

	$data = gv_mlm_run_full_scan();

	wp_send_json_success( array(
		'count'      => count( $data['items'] ),
		'scanned_at' => date_i18n( 'Y/m/d H:i', $data['scanned_at'] ),
	) );
}

/* ==========================================================================
   ۷) صفحه‌ی مدیریت
   ========================================================================== */
function gv_mlm_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$cached = get_transient( GV_MLM_TRANSIENT );
	if ( false === $cached || ! isset( $cached['items'] ) ) {
		$cached = gv_mlm_run_full_scan();
	}

	$items      = $cached['items'];
	$scanned_at = isset( $cached['scanned_at'] ) ? date_i18n( 'Y/m/d H:i', $cached['scanned_at'] ) : '';

	$total_count  = count( $items );
	$total_size   = 0;
	$used_count   = 0;
	$unused_count = 0;
	$unused_size  = 0;
	$type_counts  = array( 'image' => 0, 'video' => 0, 'audio' => 0, 'document' => 0, 'other' => 0 );

	foreach ( $items as $it ) {
		$total_size += $it['size'];
		$type_counts[ $it['type_group'] ] = ( isset( $type_counts[ $it['type_group'] ] ) ? $type_counts[ $it['type_group'] ] : 0 ) + 1;
		if ( ! empty( $it['is_used'] ) ) {
			$used_count++;
		} else {
			$unused_count++;
			$unused_size += $it['size'];
		}
	}
	?>
	<div class="wrap gvmlm-wrap" dir="rtl" id="gv-mlm-wrap">
		<style>
			.gvmlm-wrap{max-width:1280px;}
			.gvmlm-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin:18px 0 22px;}
			.gvmlm-head h1{font-size:20px;margin:0;display:flex;align-items:center;gap:8px;}
			.gvmlm-scan-info{font-size:12.5px;color:#6b7280;}
			.gvmlm-btn{display:inline-flex;align-items:center;gap:6px;background:#4338ca;color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer;transition:.15s;}
			.gvmlm-btn:hover{background:#3730a3;}
			.gvmlm-btn:disabled{opacity:.6;cursor:progress;}
			.gvmlm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;}
			.gvmlm-stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;}
			.gvmlm-stat b{display:block;font-size:20px;margin-bottom:4px;}
			.gvmlm-stat span{font-size:12.5px;color:#6b7280;}
			.gvmlm-stat.is-warn b{color:#b45309;}
			.gvmlm-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px 14px;margin-bottom:16px;}
			.gvmlm-search{flex:1;min-width:180px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;}
			.gvmlm-chip{border:1px solid #d1d5db;background:#f9fafb;border-radius:20px;padding:6px 14px;font-size:12.5px;cursor:pointer;color:#374151;}
			.gvmlm-chip.is-active{background:#4338ca;border-color:#4338ca;color:#fff;}
			.gvmlm-sort{padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:12.5px;}
			.gvmlm-table-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;}
			table.gvmlm-table{width:100%;border-collapse:collapse;font-size:13px;}
			table.gvmlm-table th{background:#f9fafb;text-align:right;padding:10px 12px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;}
			table.gvmlm-table td{padding:10px 12px;border-bottom:1px solid #f1f1f1;vertical-align:middle;}
			table.gvmlm-table th.gvmlm-sortable{cursor:pointer;user-select:none;}
			table.gvmlm-table th.gvmlm-sortable:hover{color:#4338ca;}
			.gvmlm-thumb{width:44px;height:44px;border-radius:8px;object-fit:cover;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:20px;}
			.gvmlm-fname{font-size:11.5px;color:#9ca3af;direction:ltr;text-align:right;display:block;}
			.gvmlm-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11.5px;background:#eef2ff;color:#4338ca;}
			.gvmlm-badge.used{background:#dcfce7;color:#15803d;}
			.gvmlm-badge.unused{background:#fee2e2;color:#b91c1c;}
			.gvmlm-usage-toggle{font-size:11.5px;color:#4338ca;cursor:pointer;background:none;border:none;padding:0;margin-inline-start:6px;text-decoration:underline;}
			.gvmlm-usage-list{margin:6px 0 0;padding-inline-start:16px;font-size:12px;color:#4b5563;}
			.gvmlm-usage-list li{margin-bottom:3px;}
			.gvmlm-empty{padding:40px;text-align:center;color:#6b7280;}
			.gvmlm-missing{color:#b91c1c;font-size:11px;}
			.gvmlm-actions a{margin-inline-end:8px;font-size:12px;}
		</style>

		<div class="gvmlm-head">
			<h1>🖼️ مدیریت فایل‌های چندرسانه‌ای</h1>
			<div>
				<span class="gvmlm-scan-info" id="gv-mlm-scan-info">آخرین اسکن: <?php echo esc_html( $scanned_at ); ?></span>
				<button type="button" class="gvmlm-btn" id="gv-mlm-rescan-btn">🔄 اسکن مجدد کل سایت</button>
			</div>
		</div>

		<div class="gvmlm-stats">
			<div class="gvmlm-stat"><b><?php echo esc_html( number_format_i18n( $total_count ) ); ?></b><span>کل فایل‌های رسانه</span></div>
			<div class="gvmlm-stat"><b><?php echo esc_html( gv_mlm_format_size( $total_size ) ); ?></b><span>حجم کل کتابخانه رسانه</span></div>
			<div class="gvmlm-stat"><b style="color:#15803d;"><?php echo esc_html( number_format_i18n( $used_count ) ); ?></b><span>فایل استفاده‌شده در سایت</span></div>
			<div class="gvmlm-stat is-warn"><b><?php echo esc_html( number_format_i18n( $unused_count ) ); ?></b><span>فایل بدون استفاده در سایت</span></div>
			<div class="gvmlm-stat is-warn"><b><?php echo esc_html( gv_mlm_format_size( $unused_size ) ); ?></b><span>حجم قابل آزادسازی</span></div>
		</div>

		<div class="gvmlm-toolbar">
			<input type="text" class="gvmlm-search" id="gv-mlm-search" placeholder="جستجو در نام فایل...">

			<button type="button" class="gvmlm-chip is-active" data-filter-status="all">همه (<?php echo esc_html( $total_count ); ?>)</button>
			<button type="button" class="gvmlm-chip" data-filter-status="used">استفاده‌شده (<?php echo esc_html( $used_count ); ?>)</button>
			<button type="button" class="gvmlm-chip" data-filter-status="unused">بدون استفاده (<?php echo esc_html( $unused_count ); ?>)</button>

			<button type="button" class="gvmlm-chip is-active" data-filter-type="all">همه انواع</button>
			<?php foreach ( $type_counts as $group => $count ) : if ( ! $count ) { continue; } ?>
				<button type="button" class="gvmlm-chip" data-filter-type="<?php echo esc_attr( $group ); ?>"><?php echo esc_html( gv_mlm_type_icon( $group ) . ' ' . gv_mlm_type_label( $group ) . ' (' . $count . ')' ); ?></button>
			<?php endforeach; ?>

			<select class="gvmlm-sort" id="gv-mlm-sort">
				<option value="date-desc">جدیدترین</option>
				<option value="date-asc">قدیمی‌ترین</option>
				<option value="size-desc">بیشترین حجم</option>
				<option value="size-asc">کمترین حجم</option>
				<option value="name-asc">نام (الفبا)</option>
			</select>
		</div>

		<div class="gvmlm-table-card">
			<?php if ( empty( $items ) ) : ?>
				<div class="gvmlm-empty">هیچ فایل رسانه‌ای در سایت پیدا نشد.</div>
			<?php else : ?>
				<table class="gvmlm-table" id="gv-mlm-table">
					<thead>
						<tr>
							<th></th>
							<th>نام فایل</th>
							<th>نوع</th>
							<th class="gvmlm-sortable" data-sort="size">حجم</th>
							<th>وضعیت استفاده</th>
							<th class="gvmlm-sortable" data-sort="date">تاریخ آپلود</th>
							<th>عملیات</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $items as $it ) : ?>
						<tr
							data-name="<?php echo esc_attr( mb_strtolower( $it['title'] . ' ' . $it['filename'] ) ); ?>"
							data-type="<?php echo esc_attr( $it['type_group'] ); ?>"
							data-status="<?php echo esc_attr( $it['is_used'] ? 'used' : 'unused' ); ?>"
							data-size="<?php echo esc_attr( $it['size'] ); ?>"
							data-date="<?php echo esc_attr( $it['timestamp'] ); ?>"
						>
							<td>
								<?php if ( $it['thumb'] ) : ?>
									<img class="gvmlm-thumb" src="<?php echo esc_url( $it['thumb'] ); ?>" alt="">
								<?php else : ?>
									<span class="gvmlm-thumb"><?php echo esc_html( gv_mlm_type_icon( $it['type_group'] ) ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $it['title'] ); ?>
								<span class="gvmlm-fname"><?php echo esc_html( $it['filename'] ); ?></span>
								<?php if ( ! $it['file_exists'] ) : ?>
									<span class="gvmlm-missing">⚠️ فایل روی هاست پیدا نشد</span>
								<?php endif; ?>
							</td>
							<td><span class="gvmlm-badge"><?php echo esc_html( gv_mlm_type_icon( $it['type_group'] ) . ' ' . gv_mlm_type_label( $it['type_group'] ) ); ?></span></td>
							<td><?php echo esc_html( gv_mlm_format_size( $it['size'] ) ); ?></td>
							<td>
								<?php if ( $it['is_used'] ) : ?>
									<span class="gvmlm-badge used">استفاده‌شده</span>
									<details>
										<summary class="gvmlm-usage-toggle" style="display:inline;"><?php echo esc_html( count( $it['usages'] ) ); ?> محل استفاده</summary>
										<ul class="gvmlm-usage-list">
											<?php foreach ( $it['usages'] as $u ) : ?>
												<li>
													<b><?php echo esc_html( $u['label'] ); ?>:</b>
													<?php if ( ! empty( $u['edit_link'] ) ) : ?>
														<a href="<?php echo esc_url( $u['edit_link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $u['title'] ); ?></a>
													<?php else : ?>
														<?php echo esc_html( $u['title'] ); ?>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php else : ?>
									<span class="gvmlm-badge unused">بدون استفاده</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( mysql2date( 'Y/m/d', $it['date'] ) ); ?></td>
							<td class="gvmlm-actions">
								<a href="<?php echo esc_url( $it['url'] ); ?>" target="_blank" rel="noopener">مشاهده فایل ↗</a>
								<a href="<?php echo esc_url( $it['edit_link'] ); ?>" target="_blank" rel="noopener">ویرایش</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="gvmlm-empty" id="gv-mlm-empty-filtered" hidden>هیچ فایلی با این فیلتر/جستجو پیدا نشد.</div>
			<?php endif; ?>
		</div>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong> | اینستاگرام: grootvision</p>
	</div>

	<script>
	(function () {
		var GV_MLM_AJAX = {
			url:   <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			nonce: <?php echo wp_json_encode( wp_create_nonce( GV_MLM_NONCE ) ); ?>
		};

		var wrap        = document.getElementById('gv-mlm-wrap');
		if (!wrap) { return; }

		var table       = document.getElementById('gv-mlm-table');
		var rows         = table ? Array.prototype.slice.call(table.querySelectorAll('tbody tr')) : [];
		var searchInput = document.getElementById('gv-mlm-search');
		var sortSelect  = document.getElementById('gv-mlm-sort');
		var emptyBox    = document.getElementById('gv-mlm-empty-filtered');
		var statusChips = Array.prototype.slice.call(wrap.querySelectorAll('[data-filter-status]'));
		var typeChips   = Array.prototype.slice.call(wrap.querySelectorAll('[data-filter-type]'));

		var activeStatus = 'all';
		var activeType   = 'all';

		function applyFilters() {
			var term = (searchInput.value || '').trim().toLowerCase();
			var visibleCount = 0;

			rows.forEach(function (row) {
				var matchesSearch = !term || row.getAttribute('data-name').indexOf(term) !== -1;
				var matchesStatus = activeStatus === 'all' || row.getAttribute('data-status') === activeStatus;
				var matchesType   = activeType === 'all' || row.getAttribute('data-type') === activeType;
				var visible = matchesSearch && matchesStatus && matchesType;
				row.hidden = !visible;
				if (visible) { visibleCount++; }
			});

			if (emptyBox) { emptyBox.hidden = visibleCount !== 0; }
		}

		function applySort() {
			if (!table) { return; }
			var tbody = table.querySelector('tbody');
			var value = sortSelect.value;
			var parts = value.split('-');
			var key = parts[0];
			var dir = parts[1];

			rows.sort(function (a, b) {
				var va, vb;
				if (key === 'name') {
					va = a.getAttribute('data-name'); vb = b.getAttribute('data-name');
					return dir === 'asc' ? va.localeCompare(vb, 'fa') : vb.localeCompare(va, 'fa');
				}
				va = parseFloat(a.getAttribute('data-' + key)) || 0;
				vb = parseFloat(b.getAttribute('data-' + key)) || 0;
				return dir === 'asc' ? va - vb : vb - va;
			});

			rows.forEach(function (row) { tbody.appendChild(row); });
		}

		searchInput.addEventListener('input', applyFilters);
		sortSelect.addEventListener('change', applySort);

		statusChips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				statusChips.forEach(function (c) { c.classList.remove('is-active'); });
				chip.classList.add('is-active');
				activeStatus = chip.getAttribute('data-filter-status');
				applyFilters();
			});
		});

		typeChips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				typeChips.forEach(function (c) { c.classList.remove('is-active'); });
				chip.classList.add('is-active');
				activeType = chip.getAttribute('data-filter-type');
				applyFilters();
			});
		});

		var rescanBtn = document.getElementById('gv-mlm-rescan-btn');
		if (rescanBtn) {
			rescanBtn.addEventListener('click', function () {
				rescanBtn.disabled = true;
				rescanBtn.textContent = '⏳ در حال اسکن سایت...';

				var body = new URLSearchParams();
				body.append('action', 'gv_mlm_rescan');
				body.append('nonce', GV_MLM_AJAX.nonce);

				fetch(GV_MLM_AJAX.url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				})
				.then(function (res) { return res.json(); })
				.then(function (json) {
					if (json && json.success) {
						window.location.reload();
					} else {
						rescanBtn.disabled = false;
						rescanBtn.textContent = '🔄 اسکن مجدد کل سایت';
						window.alert((json && json.data && json.data.message) ? json.data.message : 'خطا در اسکن. دوباره تلاش کنید.');
					}
				})
				.catch(function () {
					rescanBtn.disabled = false;
					rescanBtn.textContent = '🔄 اسکن مجدد کل سایت';
					window.alert('خطا در ارتباط با سرور. دوباره تلاش کنید.');
				});
			});
		}
	})();
	</script>
	<?php
}
