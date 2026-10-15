<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* ۳) داشبورد مدیر                                                     */
/* ------------------------------------------------------------------ */

function gvwr_view_mgr_overview( $st ) {
	$rg   = gvwr_range( $st['period'], $st['anchor'] );
	$f    = gvwr_state_filters( $st, $rg );
	$s    = gvwr_stats( $f, true );
	$prg  = gvwr_range( $st['period'], $rg['prev'] );
	$pf   = $f;
	$pf['from'] = $prg['from'];
	$pf['to']   = $prg['to'];
	$ps   = 'all' === $st['period'] ? array( 'qty' => 0 ) : gvwr_stats( $pf );
	$cats = gvwr_cat_map();
	$charts = array();
	ob_start();
	echo gvwr_filter_bar( $st, $rg, array( 'users' => true, 'search' => false ) ); // phpcs:ignore
	?>
	<div class="gvwr-kpis">
		<?php
		echo gvwr_kpi( 'کل کارهای انجام‌شده', gvwr_n( $s['qty'] ), 'all' === $st['period'] ? '' : gvwr_delta_html( $s['qty'], $ps['qty'] ) ); // phpcs:ignore
		echo gvwr_kpi( 'ردیف‌های گزارش', gvwr_n( $s['items'] ), gvwr_fa( $s['days'] ) . ' روز فعالیت' ); // phpcs:ignore
		echo gvwr_kpi( 'کارمندان فعال', gvwr_n( count( $s['by_user'] ) ) ); // phpcs:ignore
		echo gvwr_kpi( 'ساعت ثبت‌شده', '<span dir="ltr">' . gvwr_minutes( $s['minutes'] ) . '</span>', 'فقط ردیف‌هایی که ساعت دارند' ); // phpcs:ignore
		echo gvwr_kpi( 'در انتظار تأیید', gvwr_n( $s['pending'] ), $s['pending'] ? '<a href="' . esc_url( admin_url( 'admin.php?page=gv-wr-reports&gvwr_status=pending' ) ) . '">بررسی</a>' : '', $s['pending'] ? 'warn' : '' ); // phpcs:ignore
		?>
	</div>

	<?php if ( ! $s['items'] ) : ?>
		<div class="gvwr-card"><?php echo gvwr_empty( 'در این بازه گزارشی ثبت نشده است.' ); // phpcs:ignore ?></div>
	<?php else : ?>
		<div class="gvwr-grid2">
			<div class="gvwr-card">
				<div class="gvwr-cardhead"><h3>روند فعالیت</h3></div>
				<?php
				list( $tl, $tv ) = gvwr_trend( $s['by_day'], $rg );
				echo gvwr_chart_box( $charts, 'c-trend', 'bar', $tl, array( array( 'label' => 'تعداد کار', 'data' => $tv, 'backgroundColor' => '#0f766e', 'borderRadius' => 4 ) ), array( 'plugins' => array( 'legend' => array( 'display' => false ) ) ) ); // phpcs:ignore
				?>
			</div>
			<div class="gvwr-card">
				<div class="gvwr-cardhead"><h3>سهم دسته‌بندی‌ها</h3></div>
				<?php
				$cl = array();
				$cv = array();
				$cc = array();
				foreach ( $s['by_cat'] as $cid => $v ) {
					$cl[] = isset( $cats[ $cid ] ) ? $cats[ $cid ]->name : 'نامشخص';
					$cv[] = $v['qty'];
					$cc[] = isset( $cats[ $cid ] ) ? $cats[ $cid ]->color : '#94a3b8';
				}
				echo gvwr_chart_box( $charts, 'c-cats', 'doughnut', $cl, array( array( 'data' => $cv, 'backgroundColor' => $cc, 'borderWidth' => 0 ) ), array( 'plugins' => array( 'legend' => array( 'position' => 'bottom' ) ) ) ); // phpcs:ignore
				?>
			</div>
		</div>
		<div class="gvwr-card">
			<div class="gvwr-cardhead"><h3>عملکرد کارمندان</h3><a class="gvwr-btn ghost sm" href="<?php echo esc_url( admin_url( 'admin.php?page=gv-wr-compare' ) ); ?>">مقایسه‌ی کامل</a></div>
			<?php
			$ul = array();
			$uv = array();
			foreach ( $s['by_user'] as $uid => $v ) {
				$ul[] = gvwr_employee_name( $uid );
				$uv[] = $v['qty'];
			}
			echo gvwr_chart_box( $charts, 'c-users', 'bar', $ul, array( array( 'label' => 'تعداد کار', 'data' => $uv, 'backgroundColor' => '#2563eb', 'borderRadius' => 4 ) ), array( 'indexAxis' => 'y', 'plugins' => array( 'legend' => array( 'display' => false ) ) ), max( 160, 44 * count( $ul ) + 40 ) ); // phpcs:ignore
			?>
		</div>
	<?php endif; ?>

	<?php
	$pend = gvwr_items( array( 'status' => 'pending' ), 6 );
	if ( $pend ) :
		?>
		<div class="gvwr-card">
			<div class="gvwr-cardhead"><h3>آخرین گزارش‌های در انتظار تأیید</h3><a class="gvwr-btn ghost sm" href="<?php echo esc_url( admin_url( 'admin.php?page=gv-wr-reports&gvwr_status=pending' ) ); ?>">همه</a></div>
			<?php
			foreach ( $pend as $r ) {
				echo gvwr_item_html( $r, 'mgr' ); // phpcs:ignore
			}
			?>
		</div>
	<?php endif; ?>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => $charts );
}

/* ------------------------------------------------------------------ */
/* ۴) همه‌ی گزارش‌ها + تأیید/رد                                        */
/* ------------------------------------------------------------------ */

function gvwr_view_mgr_reports( $st ) {
	if ( ! isset( $_POST['status'] ) && isset( $_GET['gvwr_status'] ) ) { // phpcs:ignore
		$st['status'] = sanitize_key( $_GET['gvwr_status'] ); // phpcs:ignore
	}
	$rg    = gvwr_range( $st['period'], $st['anchor'] );
	$f     = gvwr_state_filters( $st, $rg );
	$per   = 25;
	$total = gvwr_items_count( $f );
	$items = gvwr_items( $f, $per, ( $st['page'] - 1 ) * $per );
	$s     = gvwr_stats( $f );
	$ex    = array( 'period' => $st['period'], 'anchor' => $rg['anchor'], 'user' => $st['user'], 'cat' => $st['cat'], 'project' => $st['project'] );
	ob_start();
	echo gvwr_filter_bar( $st, $rg, array( 'users' => true, 'status' => true, 'search' => true ) ); // phpcs:ignore
	?>
	<div class="gvwr-bulk">
		<label class="gvwr-chk"><input type="checkbox" data-act="sel-all"> انتخاب همه</label>
		<button type="button" class="gvwr-btn ok sm" data-act="bulk-approve">✓ تأیید انتخاب‌شده‌ها</button>
		<span class="gvwr-muted"><?php echo esc_html( gvwr_fa( $total ) ); ?> ردیف · <?php echo esc_html( gvwr_fa( $s['qty'] ) ); ?> کار</span>
		<span class="gvwr-spacer"></span>
		<a class="gvwr-btn ghost sm" href="<?php echo esc_url( gvwr_export_url( 'items', $ex + array( 'format' => 'xlsx' ) ) ); ?>">⬇ Excel</a>
		<a class="gvwr-btn ghost sm" href="<?php echo esc_url( gvwr_export_url( 'items', $ex + array( 'format' => 'csv' ) ) ); ?>">CSV</a>
		<button type="button" class="gvwr-btn ghost sm" data-act="print">🖨 چاپ / PDF</button>
	</div>
	<div class="gvwr-card">
		<?php
		if ( $items ) {
			foreach ( $items as $r ) {
				echo gvwr_item_html( $r, 'mgr' ); // phpcs:ignore
			}
			echo gvwr_pager( $total, $st['page'], $per ); // phpcs:ignore
		} else {
			echo gvwr_empty( 'گزارشی با این فیلترها پیدا نشد.' ); // phpcs:ignore
		}
		?>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}

/* ------------------------------------------------------------------ */
/* ۵) مقایسه‌ی کارمندان                                                */
/* ------------------------------------------------------------------ */

function gvwr_view_mgr_compare( $st ) {
	$rg   = gvwr_range( $st['period'], $st['anchor'] );
	$f    = gvwr_state_filters( $st, $rg );
	$s    = gvwr_stats( $f, true );
	$cats = gvwr_cat_map();
	$charts = array();
	// گروه‌بندی دسته‌ها (SEO / محتوا / ...)
	$grp_of = array();
	foreach ( $cats as $id => $c ) {
		$grp_of[ $id ] = $c->grp ? $c->grp : 'سایر';
	}
	$groups = array_values( array_unique( array_values( $grp_of ) ) );
	ob_start();
	echo gvwr_filter_bar( $st, $rg, array( 'cats' => false ) ); // phpcs:ignore
	if ( empty( $s['by_user'] ) ) {
		echo '<div class="gvwr-card">' . gvwr_empty( 'در این بازه داده‌ای برای مقایسه نیست.' ) . '</div>'; // phpcs:ignore
		return array( 'html' => ob_get_clean(), 'charts' => array() );
	}
	$days_in = max( 1, (int) ( ( strtotime( min( $rg['to'], gvwr_today() ) ) - strtotime( $rg['from'] ) ) / DAY_IN_SECONDS ) + 1 );
	$best    = key( $s['by_user'] );
	$super   = gvwr_is_super();
	$rates   = $super ? gvwr_employee_rates() : array();
	?>
	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>جدول مقایسه</h3><span class="gvwr-muted">مرتب‌شده بر اساس تعداد کار</span></div>
		<div class="gvwr-scroll">
			<table class="gvwr-table">
				<thead><tr><th>#</th><th>کارمند</th><th>کل کار</th><th>ردیف گزارش</th><th>روز فعال</th><th>میانگین/روز فعال</th><th>ساعت ثبت‌شده</th><?php if ( $super ) : ?><th>نرخ ساعتی</th><th>حقوق تقریبی (تومان)</th><?php endif; ?><th>تأیید شده</th><th>ردشده</th><?php foreach ( $groups as $g ) : ?><th><?php echo esc_html( $g ); ?></th><?php endforeach; ?></tr></thead>
				<tbody>
				<?php
				$i = 0;
				foreach ( $s['by_user'] as $uid => $v ) :
					$i++;
					$gv = array_fill_keys( $groups, 0 );
					foreach ( isset( $s['by_user_cat'][ $uid ] ) ? $s['by_user_cat'][ $uid ] : array() as $cid => $q ) {
						$gk        = isset( $grp_of[ $cid ] ) ? $grp_of[ $cid ] : 'سایر';
						$gv[ $gk ] = ( isset( $gv[ $gk ] ) ? $gv[ $gk ] : 0 ) + $q;
					}
					?>
					<tr>
						<td><?php echo $uid === $best ? '🏆' : esc_html( gvwr_fa( $i ) ); ?></td>
						<td><b><?php echo esc_html( gvwr_employee_name( $uid ) ); ?></b></td>
						<td><b><?php echo esc_html( gvwr_n( $v['qty'] ) ); ?></b></td>
						<td><?php echo esc_html( gvwr_n( $v['items'] ) ); ?></td>
						<td><?php echo esc_html( gvwr_n( $v['days'] ) ); ?> <span class="gvwr-muted">از <?php echo esc_html( gvwr_fa( $days_in ) ); ?></span></td>
						<td><?php echo esc_html( gvwr_n( $v['days'] ? $v['qty'] / $v['days'] : 0, 1 ) ); ?></td>
						<td dir="ltr"><?php echo esc_html( gvwr_minutes( $v['minutes'] ) ); ?></td>
						<?php if ( $super ) : $rt = isset( $rates[ $uid ] ) ? (int) $rates[ $uid ] : 0; ?><td><?php echo $rt ? esc_html( gvwr_n( $rt ) ) : '—'; ?></td><td><?php echo $rt ? esc_html( gvwr_n( round( $v['minutes'] / 60 * $rt ) ) ) : '—'; ?></td><?php endif; ?>
						<td><?php echo esc_html( gvwr_fa( $v['items'] ? round( $v['approved'] / $v['items'] * 100 ) : 0 ) ); ?>٪</td>
						<td><?php echo esc_html( gvwr_n( $v['rejected'] ) ); ?></td>
						<?php foreach ( $groups as $g ) : ?><td><?php echo esc_html( gvwr_n( $gv[ $g ] ) ); ?></td><?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="gvwr-grid2">
		<div class="gvwr-card">
			<div class="gvwr-cardhead"><h3>کارمندان به تفکیک نوع کار</h3></div>
			<?php
			$labels = array();
			foreach ( array_keys( $s['by_user'] ) as $uid ) {
				$labels[] = gvwr_employee_name( $uid );
			}
			$ds = array();
			foreach ( $groups as $gi => $g ) {
				$data = array();
				foreach ( array_keys( $s['by_user'] ) as $uid ) {
					$sum = 0;
					foreach ( isset( $s['by_user_cat'][ $uid ] ) ? $s['by_user_cat'][ $uid ] : array() as $cid => $q ) {
						if ( ( isset( $grp_of[ $cid ] ) ? $grp_of[ $cid ] : 'سایر' ) === $g ) {
							$sum += $q;
						}
					}
					$data[] = $sum;
				}
				$ds[] = array( 'label' => $g, 'data' => $data, 'backgroundColor' => gvwr_palette( $gi ), 'borderRadius' => 3 );
			}
			echo gvwr_chart_box( $charts, 'c-cmp', 'bar', $labels, $ds, array( 'scales' => array( 'x' => array( 'stacked' => true ), 'y' => array( 'stacked' => true ) ), 'plugins' => array( 'legend' => array( 'position' => 'bottom' ) ) ), 300 ); // phpcs:ignore
			?>
		</div>
		<div class="gvwr-card">
			<div class="gvwr-cardhead"><h3>سهم هر کارمند از کل کارها</h3></div>
			<?php
			$vals = array();
			$cols = array();
			$k    = 0;
			foreach ( $s['by_user'] as $v ) {
				$vals[] = $v['qty'];
				$cols[] = gvwr_palette( $k++ );
			}
			echo gvwr_chart_box( $charts, 'c-share', 'doughnut', $labels, array( array( 'data' => $vals, 'backgroundColor' => $cols, 'borderWidth' => 0 ) ), array( 'plugins' => array( 'legend' => array( 'position' => 'bottom' ) ) ), 300 ); // phpcs:ignore
			?>
		</div>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => $charts );
}

/* ------------------------------------------------------------------ */
/* ۶) تقویم فعالیت                                                     */
/* ------------------------------------------------------------------ */

function gvwr_view_calendar( $st ) {
	$st['period'] = 'month';
	if ( ! gvwr_can_manage() ) {
		$st['user'] = get_current_user_id();
	}
	$rg = gvwr_range( 'month', $st['anchor'] );
	$f  = gvwr_state_filters( $st, $rg );
	$s  = gvwr_stats( $f );
	$max = 1;
	foreach ( $s['by_day'] as $v ) {
		$max = max( $max, $v['qty'] );
	}
	$first_w = (int) gmdate( 'w', strtotime( $rg['from'] . ' 12:00:00 UTC' ) );
	$off     = ( $first_w + 1 ) % 7; // شنبه = ستون اول
	$names   = array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه' );
	ob_start();
	echo gvwr_filter_bar( $st, $rg, array( 'users' => gvwr_can_manage(), 'periods' => array( 'month' ) ) ); // phpcs:ignore
	?>
	<div class="gvwr-card">
		<div class="gvwr-cal">
			<?php foreach ( $names as $n ) : ?><div class="gvwr-cal-h"><?php echo esc_html( $n ); ?></div><?php endforeach; ?>
			<?php for ( $i = 0; $i < $off; $i++ ) : ?><div class="gvwr-cal-c empty"></div><?php endfor; ?>
			<?php
			for ( $d = $rg['from']; $d <= $rg['to']; $d = gvwr_add_days( $d, 1 ) ) :
				$v  = isset( $s['by_day'][ $d ] ) ? $s['by_day'][ $d ] : null;
				$lv = $v ? max( 1, (int) ceil( $v['qty'] / $max * 4 ) ) : 0;
				$dd = gvwr_date( $d, 'short' );
				?>
				<button type="button" class="gvwr-cal-c lv<?php echo (int) $lv; ?><?php echo $d === gvwr_today() ? ' today' : ''; ?>" data-act="day" data-date="<?php echo esc_attr( $d ); ?>">
					<span class="gvwr-cal-d"><?php echo esc_html( preg_replace( '/^.*\//', '', $dd ) ); ?></span>
					<?php if ( $v ) : ?><span class="gvwr-cal-n"><?php echo esc_html( gvwr_fa( $v['qty'] ) ); ?></span><?php endif; ?>
				</button>
			<?php endfor; ?>
		</div>
		<p class="gvwr-muted gvwr-cal-key">عدد هر روز = تعداد کارهای ثبت‌شده. روی روز بزنید تا فهرستش را ببینید.</p>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}

/** فهرست کارهای یک روز (برای پنجره‌ی تقویم) */
function gvwr_day_items_html( $date, $user ) {
	$f = array( 'from' => $date, 'to' => $date );
	if ( ! gvwr_can_manage() ) {
		$f['user_id'] = get_current_user_id();
	} elseif ( $user ) {
		$f['user_id'] = (int) $user;
	}
	$items = gvwr_items( $f, 100, 0, 'ASC' );
	$h     = '<h3>' . esc_html( gvwr_date( $date, 'long' ) ) . '</h3>';
	if ( ! $items ) {
		return $h . gvwr_empty( 'برای این روز گزارشی ثبت نشده است.' );
	}
	foreach ( $items as $r ) {
		$h .= gvwr_item_html( $r, gvwr_can_manage() ? 'mgr' : 'mine' );
	}
	return $h;
}
