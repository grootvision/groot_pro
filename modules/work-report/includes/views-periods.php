<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * گزارش‌های دوره‌ای (ماهانه): خلاصه‌ی عملکرد، رتبه‌ی کلمات همان دوره، ریز فعالیت‌ها، رشد صفحات و گام بعدی.
 * گزارش‌های ماه‌های گذشته‌ی بخش قدیمی «گزارش عملکرد سئو» اینجا نگهداری و نمایش داده می‌شوند.
 * مدیر همه را می‌بیند؛ کارفرما فقط گزارش‌های منتشرشده‌ی خودش را، بدون ساعت کار.
 */

function gvwr_period( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gvwr_table( 'periods' ) . ' WHERE id=%d', (int) $id ) );
}

/** فهرست گزارش‌های دوره‌ای. $a: status, client_user_id, project_id, limit */
function gvwr_periods( $a = array() ) {
	global $wpdb;
	$w   = array( '1=1' );
	$arg = array();
	if ( ! empty( $a['status'] ) ) {
		$w[]   = 'p.status=%s';
		$arg[] = $a['status'];
	}
	if ( ! empty( $a['client_user_id'] ) ) {
		$w[]   = 'p.client_user_id=%d';
		$arg[] = (int) $a['client_user_id'];
	}
	if ( ! empty( $a['project_id'] ) ) {
		$w[]   = 'p.project_id=%d';
		$arg[] = (int) $a['project_id'];
	}
	$sql = 'SELECT p.*, pr.name AS project_name,
		(SELECT COUNT(*) FROM ' . gvwr_table( 'period_keywords' ) . ' k WHERE k.period_id=p.id) AS kw_n,
		(SELECT COUNT(*) FROM ' . gvwr_table( 'items' ) . ' i WHERE i.period_id=p.id) AS item_n,
		(SELECT COUNT(*) FROM ' . gvwr_table( 'period_growth' ) . ' g WHERE g.period_id=p.id) AS growth_n
		FROM ' . gvwr_table( 'periods' ) . ' p LEFT JOIN ' . gvwr_table( 'projects' ) . ' pr ON pr.id=p.project_id
		WHERE ' . implode( ' AND ', $w ) . ' ORDER BY p.period_end DESC, p.id DESC';
	if ( ! empty( $a['limit'] ) ) {
		$sql .= ' LIMIT ' . (int) $a['limit'];
	}
	return $wpdb->get_results( gvwr_prep( $sql, $arg ) );
}

/** عنوان گزارش برای نمایش (فقط از روی بازه ساخته می‌شود تا نام کارمند یا متن آزاد هرگز به کارفرما نرسد) */
function gvwr_period_label( $p ) {
	$e = (string) $p->period_end;
	if ( ! gvwr_valid_date( $e ) ) {
		return 'گزارش عملکرد';
	}
	if ( gvwr_is_jalali() ) {
		$x = array_map( 'intval', explode( '-', $e ) );
		$j = gvwr_g2j( $x[0], $x[1], $x[2] );
		return 'گزارش ' . gvwr_jmonth( $j[1] ) . ' ' . gvwr_fa( $j[0] );
	}
	return 'گزارش ' . gmdate( 'F Y', strtotime( $e . ' 12:00:00 UTC' ) );
}

function gvwr_period_visibility( $p ) {
	$def = array( 'show_summary' => 1, 'show_keywords' => 1, 'show_tasks' => 1, 'show_growth' => 1, 'show_hours' => 0, 'show_next_steps' => 1 );
	$v   = json_decode( (string) $p->visibility, true );
	return is_array( $v ) ? array_merge( $def, $v ) : $def;
}

/** وضعیت تغییر رتبه‌ی یک کلمه در یک دوره */
function gvwr_period_rank_delta( $prev, $curr ) {
	$prev = (int) $prev;
	$curr = (int) $curr;
	if ( $curr <= 0 ) {
		return array( 'c' => 'new', 't' => 'بدون رتبه', 'v' => 0 );
	}
	if ( $prev <= 0 ) {
		return array( 'c' => 'new', 't' => 'ورود به رتبه‌ی ' . gvwr_fa( $curr ), 'v' => 0 );
	}
	if ( $curr < $prev ) {
		return array( 'c' => 'up', 't' => '▲ ' . gvwr_fa( $prev - $curr ), 'v' => $prev - $curr );
	}
	if ( $curr > $prev ) {
		return array( 'c' => 'down', 't' => '▼ ' . gvwr_fa( $curr - $prev ), 'v' => $prev - $curr );
	}
	return array( 'c' => 'same', 't' => 'بدون تغییر', 'v' => 0 );
}

/**
 * جزئیات یک گزارش دوره‌ای.
 * $admin = true: همه‌ی بخش‌ها (حتی مخفی‌شده از کارفرما) + ساعت کار + نام کارمند.
 */
function gvwr_period_detail_html( $p, $admin = false ) {
	global $wpdb;
	$vis   = gvwr_period_visibility( $p );
	$can   = function ( $k ) use ( $vis, $admin ) {
		return $admin || ! empty( $vis[ $k ] );
	};
	$flag  = function ( $k ) use ( $vis, $admin ) {
		return ( $admin && empty( $vis[ $k ] ) ) ? ' <span class="gvwr-badge st-rejected">مخفی از کارفرما</span>' : '';
	};
	$kws   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . gvwr_table( 'period_keywords' ) . ' WHERE period_id=%d ORDER BY sort_order ASC, id ASC', $p->id ) );
	$grow  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . gvwr_table( 'period_growth' ) . ' WHERE period_id=%d ORDER BY id ASC', $p->id ) );
	$items = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT i.*, c.name AS cat_name, c.color AS cat_color, c.icon AS cat_icon, u.display_name FROM ' . gvwr_table( 'items' ) . ' i LEFT JOIN ' . gvwr_table( 'categories' ) . ' c ON c.id=i.category_id LEFT JOIN ' . $wpdb->users . ' u ON u.ID=i.user_id WHERE i.period_id=%d ' . ( $admin ? '' : "AND i.status='approved' " ) . 'ORDER BY i.work_date ASC, i.id ASC',
			$p->id
		)
	);
	$proj  = gvwr_project( $p->project_id );
	$minutes = 0;
	foreach ( $items as $r ) {
		$minutes += (int) $r->duration_min;
	}
	$h  = '<div class="gvwr-period">';
	$h .= '<h3>🧾 ' . esc_html( $admin && $p->title ? $p->title : gvwr_period_label( $p ) ) . '</h3>';
	$h .= '<p class="gvwr-muted">بازه‌ی گزارش: <b>' . esc_html( gvwr_date( $p->period_start, 'long' ) . ' تا ' . gvwr_date( $p->period_end, 'long' ) ) . '</b>' . ( $admin && $proj ? ' · پروژه: <b>' . esc_html( $proj->name ) . '</b>' : '' ) . ( $admin && $p->client_name ? ' · مشتری: <b>' . esc_html( $p->client_name ) . '</b>' : '' ) . '</p>';
	$h .= '<div class="gvwr-kpis gvwr-kpis-sm">';
	$h .= gvwr_kpi( 'کلمه‌ی کلیدی رصدشده', gvwr_n( count( $kws ) ) );
	$h .= gvwr_kpi( 'فعالیت ثبت‌شده', gvwr_n( count( $items ) ) );
	$h .= gvwr_kpi( 'صفحه در حال رشد', gvwr_n( count( $grow ) ) );
	if ( $admin ) {
		$h .= gvwr_kpi( 'ساعت کار', '<span dir="ltr">' . gvwr_minutes( $minutes ? $minutes : (int) round( $p->hours_spent * 60 ) ) . '</span>', 'فقط برای مدیر' );
	}
	$h .= '</div>';

	if ( $can( 'show_summary' ) && ( $p->summary || $admin ) ) {
		$h .= '<div class="gvwr-psec"><h4>📋 خلاصه‌ی عملکرد' . $flag( 'show_summary' ) . '</h4>' . ( $p->summary ? '<div class="gvwr-prose">' . wp_kses_post( wpautop( $p->summary ) ) . '</div>' : '<p class="gvwr-muted">خلاصه‌ای ثبت نشده است.</p>' ) . '</div>';
	}

	if ( $can( 'show_keywords' ) && ( $kws || $admin ) ) {
		$h .= '<div class="gvwr-psec"><h4>🔑 کلمات کلیدی و تغییر رتبه' . $flag( 'show_keywords' ) . '</h4>';
		if ( $kws ) {
			$h .= '<div class="gvwr-scroll"><table class="gvwr-table"><thead><tr><th>کلمه‌ی کلیدی</th><th>موتور جستجو</th><th>رتبه‌ی قبلی</th><th>رتبه‌ی فعلی</th><th>تغییر</th><th>توضیح</th></tr></thead><tbody>';
			foreach ( $kws as $k ) {
				$d  = gvwr_period_rank_delta( $k->prev_rank, $k->curr_rank );
				$h .= '<tr><td><b>' . esc_html( $k->keyword ) . '</b>' . ( $k->page_url ? '<br><a class="gvwr-link" dir="ltr" href="' . esc_url( $k->page_url ) . '" target="_blank" rel="noopener">مشاهده صفحه ↗</a>' : '' ) . '</td><td>' . esc_html( $k->search_engine ) . '</td><td>' . ( $k->prev_rank > 0 ? esc_html( gvwr_fa( $k->prev_rank ) ) : '—' ) . '</td><td><b>' . ( $k->curr_rank > 0 ? esc_html( gvwr_fa( $k->curr_rank ) ) : '—' ) . '</b></td><td><span class="gvwr-rk ' . esc_attr( $d['c'] ) . '">' . esc_html( $d['t'] ) . '</span></td><td>' . esc_html( $k->note ) . '</td></tr>';
			}
			$h .= '</tbody></table></div>';
		} else {
			$h .= '<p class="gvwr-muted">کلمه‌ای برای این گزارش ثبت نشده است.</p>';
		}
		$h .= '</div>';
	}

	if ( $can( 'show_tasks' ) && ( $items || $admin ) ) {
		$h .= '<div class="gvwr-psec"><h4>🗂️ ریز فعالیت‌ها و محتوای تولیدشده' . $flag( 'show_tasks' ) . '</h4>';
		if ( $items ) {
			$h .= '<div class="gvwr-scroll"><table class="gvwr-table"><thead><tr><th>نوع فعالیت</th><th>شرح</th><th>تاریخ</th>' . ( $admin ? '<th>کارمند</th><th>ساعت</th>' : '' ) . '<th>لینک</th></tr></thead><tbody>';
			foreach ( $items as $r ) {
				$col = $r->cat_color ? $r->cat_color : '#64748b';
				$h  .= '<tr><td><span class="gvwr-badge" style="background:' . esc_attr( $col ) . '1a;color:' . esc_attr( $col ) . '">' . esc_html( trim( $r->cat_icon . ' ' . $r->cat_name ) ) . '</span></td><td>' . nl2br( esc_html( $r->description ) ) . '</td><td>' . esc_html( gvwr_date( $r->work_date ) ) . '</td>' . ( $admin ? '<td>' . esc_html( $r->display_name ) . '</td><td dir="ltr">' . esc_html( gvwr_minutes( $r->duration_min ) ) . '</td>' : '' ) . '<td>' . ( $r->url ? '<a class="gvwr-link" href="' . esc_url( $r->url ) . '" target="_blank" rel="noopener">مشاهده ↗</a>' : '—' ) . '</td></tr>';
			}
			$h .= '</tbody></table></div>';
		} else {
			$h .= '<p class="gvwr-muted">فعالیتی برای این گزارش ثبت نشده است.</p>';
		}
		$h .= '</div>';
	}

	if ( $can( 'show_growth' ) && ( $grow || $admin ) ) {
		$h .= '<div class="gvwr-psec"><h4>📈 رشد صفحات' . $flag( 'show_growth' ) . '</h4>';
		if ( $grow ) {
			$h .= '<div class="gvwr-scroll"><table class="gvwr-table"><thead><tr><th>صفحه</th><th>شاخص</th><th>قبل</th><th>بعد</th><th>توضیح</th></tr></thead><tbody>';
			foreach ( $grow as $g ) {
				$h .= '<tr><td>' . esc_html( $g->page_title ? $g->page_title : '—' ) . ( $g->page_url ? '<br><a class="gvwr-link" dir="ltr" href="' . esc_url( $g->page_url ) . '" target="_blank" rel="noopener">مشاهده ↗</a>' : '' ) . '</td><td>' . esc_html( $g->metric_label ) . '</td><td>' . esc_html( $g->before_value ) . '</td><td><b class="gvwr-up">' . esc_html( $g->after_value ) . '</b></td><td>' . esc_html( $g->note ) . '</td></tr>';
			}
			$h .= '</tbody></table></div>';
		} else {
			$h .= '<p class="gvwr-muted">ردیف رشدی برای این گزارش ثبت نشده است.</p>';
		}
		$h .= '</div>';
	}

	if ( $can( 'show_next_steps' ) && ( $p->next_steps || $admin ) ) {
		$h .= '<div class="gvwr-psec"><h4>🚀 برنامه‌ی گام بعدی' . $flag( 'show_next_steps' ) . '</h4>' . ( $p->next_steps ? '<div class="gvwr-prose">' . wp_kses_post( wpautop( $p->next_steps ) ) . '</div>' : '<p class="gvwr-muted">برنامه‌ای ثبت نشده است.</p>' ) . '</div>';
	}
	return $h . '</div>';
}

/** پنجره‌ی جزئیات (برای مدیر و کارفرما) — دسترسی در اینجا بررسی می‌شود */
function gvwr_period_modal( $id ) {
	$p = gvwr_period( $id );
	if ( ! $p ) {
		return null;
	}
	if ( gvwr_can_manage() ) {
		return gvwr_period_detail_html( $p, true );
	}
	if ( 'published' !== $p->status || ! gvwr_can_client() || (int) $p->client_user_id !== get_current_user_id() ) {
		return null;
	}
	return gvwr_period_detail_html( $p, false );
}

/** صفحه‌ی مدیر: همه‌ی گزارش‌های دوره‌ای */
function gvwr_view_periods( $st ) {
	$a = array();
	if ( $st['project'] ) {
		$a['project_id'] = $st['project'];
	}
	if ( in_array( $st['status'], array( 'draft', 'published' ), true ) ) {
		$a['status'] = $st['status'];
	}
	$rows  = gvwr_periods( $a );
	$projs = gvwr_projects();
	ob_start();
	?>
	<div class="gvwr-filters">
		<?php if ( count( $projs ) > 1 ) : ?>
			<select data-f="project" class="gvwr-sel"><option value="0">همه‌ی پروژه‌ها</option>
				<?php foreach ( $projs as $p ) : ?><option value="<?php echo (int) $p->id; ?>" <?php selected( $st['project'], (int) $p->id ); ?>><?php echo esc_html( $p->name ); ?></option><?php endforeach; ?>
			</select>
		<?php endif; ?>
		<select data-f="status" class="gvwr-sel">
			<option value="">همه‌ی وضعیت‌ها</option>
			<option value="published" <?php selected( $st['status'], 'published' ); ?>>منتشرشده</option>
			<option value="draft" <?php selected( $st['status'], 'draft' ); ?>>پیش‌نویس</option>
		</select>
		<span class="gvwr-muted"><?php echo esc_html( gvwr_fa( count( $rows ) ) ); ?> گزارش</span>
	</div>
	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>گزارش‌های دوره‌ای</h3><span class="gvwr-muted">گزارش‌های ماهانه‌ی ثبت‌شده (از جمله گزارش‌های قبلی). کارفرما فقط گزارش‌های «منتشرشده» را می‌بیند.</span></div>
		<?php if ( $rows ) : ?>
			<div class="gvwr-scroll">
				<table class="gvwr-table">
					<thead><tr><th>گزارش</th><th>پروژه / مشتری</th><th>بازه</th><th>کلمات</th><th>فعالیت‌ها</th><th>رشد صفحات</th><th>ساعت</th><th>وضعیت</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr data-id="<?php echo (int) $r->id; ?>">
							<td><b><?php echo esc_html( gvwr_period_label( $r ) ); ?></b></td>
							<td><?php echo esc_html( $r->project_name ? $r->project_name : '—' ); ?><?php echo $r->client_name ? '<br><span class="gvwr-muted">' . esc_html( $r->client_name ) . '</span>' : ''; // phpcs:ignore ?></td>
							<td><?php echo esc_html( gvwr_date( $r->period_start, 'short' ) . ' ← ' . gvwr_date( $r->period_end, 'short' ) ); ?></td>
							<td><?php echo esc_html( gvwr_n( $r->kw_n ) ); ?></td>
							<td><?php echo esc_html( gvwr_n( $r->item_n ) ); ?></td>
							<td><?php echo esc_html( gvwr_n( $r->growth_n ) ); ?></td>
							<td dir="ltr"><?php echo esc_html( $r->hours_spent > 0 ? gvwr_fa( round( $r->hours_spent, 1 ) ) : '—' ); ?></td>
							<td><?php echo 'published' === $r->status ? '<span class="gvwr-badge st-approved">منتشرشده</span>' : '<span class="gvwr-badge st-pending">پیش‌نویس</span>'; // phpcs:ignore ?></td>
							<td class="gvwr-acts">
								<button type="button" class="gvwr-btn ghost sm" data-act="period-view" data-id="<?php echo (int) $r->id; ?>">مشاهده</button>
								<button type="button" class="gvwr-ib" data-act="period-toggle" data-id="<?php echo (int) $r->id; ?>" title="<?php echo 'published' === $r->status ? 'برگرداندن به پیش‌نویس' : 'انتشار برای کارفرما'; ?>"><?php echo 'published' === $r->status ? '⏸' : '📤'; ?></button>
								<button type="button" class="gvwr-ib no" data-act="period-del" data-id="<?php echo (int) $r->id; ?>" title="حذف">🗑</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<?php echo gvwr_empty( 'هنوز گزارش دوره‌ای‌ای ثبت نشده است.' ); // phpcs:ignore ?>
		<?php endif; ?>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}

function gvwr_delete_period( $id ) {
	global $wpdb;
	$id = (int) $id;
	$wpdb->delete( gvwr_table( 'periods' ), array( 'id' => $id ) );
	$wpdb->delete( gvwr_table( 'period_keywords' ), array( 'period_id' => $id ) );
	$wpdb->delete( gvwr_table( 'period_growth' ), array( 'period_id' => $id ) );
	$wpdb->update( gvwr_table( 'items' ), array( 'period_id' => 0 ), array( 'period_id' => $id ) ); // ردیف‌های کار حفظ می‌شوند
}
