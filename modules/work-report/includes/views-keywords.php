<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gvwr_rank_badge( $g ) {
	switch ( $g['dir'] ) {
		case 'up':
			return '<span class="gvwr-rk up">▲ ' . gvwr_fa( $g['delta'] ) . '</span>';
		case 'down':
			return '<span class="gvwr-rk down">▼ ' . gvwr_fa( abs( $g['delta'] ) ) . '</span>';
		case 'same':
			return '<span class="gvwr-rk same">بدون تغییر</span>';
		case 'new':
			return '<span class="gvwr-rk new">اولین ثبت</span>';
	}
	return '<span class="gvwr-muted">—</span>';
}

/* ------------------------------------------------------------------ */
/* ۷) کلمات کلیدی و ثبت رتبه                                           */
/* ------------------------------------------------------------------ */

function gvwr_view_keywords( $st ) {
	$project = $st['project'] ? $st['project'] : gvwr_default_project_id();
	$per     = 100;
	$all     = gvwr_keywords( $project, true, $st['q'] );
	$total   = count( $all );
	$kws     = array_slice( $all, ( $st['page'] - 1 ) * $per, $per );
	$ser     = gvwr_rank_series( wp_list_pluck( $kws, 'id' ), '', 8 );
	$mgr     = gvwr_can_manage();
	$can_add = $mgr || gvwr_opt( 'employees_add_keywords' );
	$projs   = gvwr_projects();
	ob_start();
	?>
	<div class="gvwr-filters">
		<?php if ( count( $projs ) > 1 ) : ?>
			<select data-f="project" class="gvwr-sel"><?php foreach ( $projs as $p ) : ?><option value="<?php echo (int) $p->id; ?>" <?php selected( $project, (int) $p->id ); ?>><?php echo esc_html( $p->name ); ?></option><?php endforeach; ?></select>
		<?php endif; ?>
		<input type="search" data-f="q" class="gvwr-sel gvwr-search" placeholder="جستجوی کلمه…" value="<?php echo esc_attr( $st['q'] ); ?>">
		<span class="gvwr-muted"><?php echo esc_html( gvwr_fa( $total ) ); ?> کلمه</span>
		<span class="gvwr-spacer"></span>
		<a class="gvwr-btn ghost sm" href="<?php echo esc_url( gvwr_export_url( 'keywords', array( 'format' => 'xlsx' ) ) ); ?>">⬇ خروجی Excel</a>
	</div>

	<?php if ( $can_add ) : ?>
		<div class="gvwr-card">
			<div class="gvwr-cardhead"><h3>افزودن کلمه‌ی کلیدی</h3></div>
			<div class="gvwr-kwadd">
				<input type="text" id="gvwr-kw-new" placeholder="کلمه‌ی کلیدی">
				<input type="url" id="gvwr-kw-url" dir="ltr" placeholder="صفحه‌ی هدف (اختیاری)">
				<button type="button" class="gvwr-btn primary" data-act="kw-add" data-project="<?php echo (int) $project; ?>">افزودن</button>
			</div>
			<div class="gvwr-import">
				<b>یا فهرست را از Excel / CSV وارد کنید</b>
				<span class="gvwr-muted">ستون اول: کلمه · ستون دوم (اختیاری): صفحه‌ی هدف · ستون سوم (اختیاری): رتبه‌ی فعلی</span>
				<input type="file" id="gvwr-kw-file" accept=".xlsx,.csv,.txt">
				<button type="button" class="gvwr-btn ghost" data-act="kw-import" data-project="<?php echo (int) $project; ?>">وارد کردن فایل</button>
				<a href="<?php echo esc_url( gvwr_export_url( 'keywords_template', array( 'format' => 'xlsx' ) ) ); ?>">دانلود فایل نمونه</a>
			</div>
		</div>
	<?php endif; ?>

	<div class="gvwr-card">
		<div class="gvwr-rankbar">
			<div>
				<h3>ثبت رتبه‌های جدید</h3>
				<span class="gvwr-muted">رتبه‌ی جدید را کنار هر کلمه بنویسید (فقط کلمات پر‌شده ثبت می‌شوند) و یک‌جا ذخیره کنید.</span>
			</div>
			<label>تاریخ رتبه
				<input type="date" id="gvwr-rank-date" value="<?php echo esc_attr( gvwr_today() ); ?>" max="<?php echo esc_attr( gvwr_today() ); ?>">
			</label>
			<button type="button" class="gvwr-btn primary lg" data-act="rank-save">ثبت رتبه‌ها</button>
		</div>
		<?php if ( $kws ) : ?>
			<div class="gvwr-scroll">
				<table class="gvwr-table gvwr-kwtable">
					<thead><tr><th>کلمه‌ی کلیدی</th><th>رتبه‌ی قبلی</th><th>رتبه‌ی فعلی</th><th>تغییر</th><th>روند</th><th>رتبه‌ی جدید</th><th></th></tr></thead>
					<tbody>
					<?php
					foreach ( $kws as $k ) :
						$sr = isset( $ser[ $k->id ] ) ? $ser[ $k->id ] : array();
						$g  = gvwr_growth( $sr );
						?>
						<tr data-kw="<?php echo (int) $k->id; ?>">
							<td><b><?php echo esc_html( $k->keyword ); ?></b><?php if ( $k->target_url ) : ?><br><a class="gvwr-link" dir="ltr" href="<?php echo esc_url( $k->target_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $k->target_url, PHP_URL_PATH ) ? wp_parse_url( $k->target_url, PHP_URL_PATH ) : $k->target_url ); ?></a><?php endif; ?></td>
							<td><?php echo null !== $g['prev'] ? esc_html( gvwr_fa( $g['prev'] ) ) : '—'; ?></td>
							<td><b><?php echo null !== $g['last'] ? esc_html( gvwr_fa( $g['last'] ) ) : '—'; ?></b><?php if ( $sr ) : ?><br><span class="gvwr-muted"><?php echo esc_html( gvwr_date( $sr[0][0], 'short' ) ); ?></span><?php endif; ?></td>
							<td><?php echo gvwr_rank_badge( $g ); // phpcs:ignore ?></td>
							<td><?php echo gvwr_sparkline( $sr ); // phpcs:ignore ?></td>
							<td><input type="number" class="gvwr-rankin" min="1" max="9999" inputmode="numeric" data-rank="<?php echo (int) $k->id; ?>" placeholder="—"></td>
							<td class="gvwr-acts">
								<button type="button" class="gvwr-ib" data-act="kw-history" data-id="<?php echo (int) $k->id; ?>" title="تاریخچه‌ی رتبه">📈</button>
								<?php if ( $mgr ) : ?><button type="button" class="gvwr-ib no" data-act="kw-del" data-id="<?php echo (int) $k->id; ?>" title="حذف کلمه">🗑</button><?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php echo gvwr_pager( $total, $st['page'], $per ); // phpcs:ignore ?>
		<?php else : ?>
			<?php echo gvwr_empty( 'هنوز کلمه‌ی کلیدی‌ای وارد نشده است.' ); // phpcs:ignore ?>
		<?php endif; ?>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}

/** تاریخچه‌ی رتبه‌ی یک کلمه (پنجره) */
function gvwr_kw_history( $id ) {
	global $wpdb;
	$k = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gvwr_table( 'keywords' ) . ' WHERE id=%d', $id ) );
	if ( ! $k ) {
		return array( 'html' => gvwr_empty( 'کلمه پیدا نشد.' ), 'charts' => array() );
	}
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT rank_date, rank_pos, user_id FROM ' . gvwr_table( 'ranks' ) . ' WHERE keyword_id=%d ORDER BY rank_date DESC LIMIT 60', $id ) );
	$charts = array();
	$h      = '<h3>' . esc_html( $k->keyword ) . '</h3>';
	if ( ! $rows ) {
		return array( 'html' => $h . gvwr_empty( 'هنوز رتبه‌ای ثبت نشده است.' ), 'charts' => array() );
	}
	$asc = array_reverse( $rows );
	$h  .= gvwr_chart_box( $charts, 'c-kwh', 'line', array_map( function ( $r ) { return gvwr_date( $r->rank_date, 'short' ); }, $asc ), array( array( 'label' => 'رتبه', 'data' => array_map( function ( $r ) { return (int) $r->rank_pos; }, $asc ), 'borderColor' => '#0f766e', 'backgroundColor' => 'rgba(15,118,110,.12)', 'fill' => true, 'tension' => .3, 'pointRadius' => 3 ) ), array( 'scales' => array( 'y' => array( 'reverse' => true, 'title' => array( 'display' => true, 'text' => 'رتبه (کمتر = بهتر)' ) ) ), 'plugins' => array( 'legend' => array( 'display' => false ) ) ), 220 );
	$h  .= '<div class="gvwr-scroll"><table class="gvwr-table"><thead><tr><th>تاریخ</th><th>رتبه</th><th>تغییر</th><th>ثبت‌کننده</th></tr></thead><tbody>';
	foreach ( $rows as $i => $r ) {
		$prev = isset( $rows[ $i + 1 ] ) ? (int) $rows[ $i + 1 ]->rank_pos : null;
		$g    = null === $prev ? array( 'dir' => 'new' ) : gvwr_growth( array( array( '', (int) $r->rank_pos ), array( '', $prev ) ) );
		$h   .= '<tr><td>' . esc_html( gvwr_date( $r->rank_date ) ) . '</td><td><b>' . esc_html( gvwr_fa( $r->rank_pos ) ) . '</b></td><td>' . gvwr_rank_badge( $g ) . '</td><td>' . esc_html( gvwr_can_manage() ? gvwr_employee_name( $r->user_id ) : '' ) . '</td></tr>';
	}
	return array( 'html' => $h . '</tbody></table></div>', 'charts' => $charts );
}

/** ورود فهرست کلمات از فایل */
function gvwr_import_keywords( $path, $name, $project ) {
	$rows = gvwr_read_table( $path, $name );
	if ( ! $rows ) {
		return new WP_Error( 'file', 'فایل خوانده نشد یا خالی است.' );
	}
	$first = isset( $rows[0][0] ) ? mb_strtolower( $rows[0][0] ) : '';
	if ( in_array( $first, array( 'کلمه کلیدی', 'کلمه‌ی کلیدی', 'کلمه', 'keyword', 'keywords' ), true ) ) {
		array_shift( $rows );
	}
	$added = 0;
	$dupe  = 0;
	$ranks = array();
	foreach ( array_slice( $rows, 0, 5000 ) as $r ) {
		$kw = isset( $r[0] ) ? $r[0] : '';
		if ( '' === trim( $kw ) ) {
			continue;
		}
		$id = gvwr_kw_add( $kw, isset( $r[1] ) ? $r[1] : '', $project );
		if ( $id ) {
			$added++;
			if ( isset( $r[2] ) && (int) $r[2] > 0 ) {
				$ranks[ $id ] = $r[2];
			}
		} else {
			$dupe++;
		}
	}
	if ( $ranks ) {
		gvwr_save_ranks( $ranks, gvwr_today(), get_current_user_id() );
	}
	return array( 'added' => $added, 'dupe' => $dupe );
}
