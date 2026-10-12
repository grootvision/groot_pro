<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * گزارش کارفرما — شورت‌کد [groot_work_report]
 * حریم خصوصی: نام کارمند، ساعت شروع/پایان، پیام مدیر و وضعیت داخلی هرگز به این صفحه نمی‌رسد.
 */
add_shortcode( 'groot_work_report', 'gvwr_shortcode' );

function gvwr_shortcode( $atts ) {
	$a = shortcode_atts( array( 'period' => 'month', 'project' => 0 ), $atts, 'groot_work_report' );
	gvwr_enqueue_assets( true );

	if ( ! is_user_logged_in() ) {
		ob_start();
		echo '<div class="gvwr-app gvwr-client gvwr-login" dir="rtl"><div class="gvwr-card"><h3>ورود به گزارش عملکرد</h3><p class="gvwr-muted">برای مشاهده‌ی گزارش، با حساب کاربری خود وارد شوید.</p>';
		wp_login_form( array( 'redirect' => get_permalink(), 'label_username' => 'نام کاربری یا ایمیل', 'label_password' => 'رمز عبور', 'label_remember' => 'مرا به خاطر بسپار', 'label_log_in' => 'ورود' ) );
		echo '</div></div>';
		return ob_get_clean();
	}
	if ( ! gvwr_can_client() ) {
		return '<div class="gvwr-app gvwr-client" dir="rtl"><div class="gvwr-card">' . gvwr_empty( 'حساب شما به این گزارش دسترسی ندارد. با مدیر پروژه تماس بگیرید.' ) . '</div></div>';
	}
	$period = in_array( $a['period'], array( 'day', 'week', 'month', 'year', 'all' ), true ) ? $a['period'] : 'month';
	return '<div class="gvwr-app gvwr-client" dir="rtl"><div class="gvwr-view" data-view="client" data-autoload="1" data-period="' . esc_attr( $period ) . '" data-project="' . (int) $a['project'] . '"><div class="gvwr-loading">در حال آماده‌سازی گزارش…</div></div></div>';
}

/** برگه‌ی کارفرما در موتورهای جستجو ایندکس نشود */
add_filter( 'wp_robots', function ( $robots ) {
	$pid = (int) gvwr_opt( 'client_page_id' );
	if ( $pid && is_page( $pid ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

function gvwr_client_item( $r ) {
	ob_start();
	?>
	<div class="gvwr-citem">
		<div class="gvwr-citem-d"><?php echo esc_html( gvwr_date( $r->work_date, 'long' ) ); ?></div>
		<div class="gvwr-citem-b">
			<p><?php echo nl2br( esc_html( $r->description ) ); ?><?php echo $r->qty > 1 ? ' <span class="gvwr-qty">× ' . esc_html( gvwr_fa( $r->qty ) ) . '</span>' : ''; // phpcs:ignore ?></p>
			<?php if ( $r->url ) : ?><a class="gvwr-link" dir="ltr" href="<?php echo esc_url( $r->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $r->url, PHP_URL_HOST ) . wp_parse_url( $r->url, PHP_URL_PATH ) ); ?></a><?php endif; ?>
			<?php echo gvwr_opt( 'client_show_shots' ) ? gvwr_shots_html( $r->screenshots ) : ''; // phpcs:ignore ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function gvwr_view_client( $st ) {
	$rg = gvwr_range( $st['period'], $st['anchor'] );
	$f  = array( 'from' => $rg['from'], 'to' => $rg['to'] );
	if ( $st['cat'] ) {
		$f['category_id'] = $st['cat'];
	}
	if ( $st['project'] ) {
		$f['project_id'] = $st['project'];
	}
	if ( gvwr_opt( 'require_approval' ) ) {
		$f['status'] = 'approved';
	}
	$s   = gvwr_stats( $f, false );
	$pf  = $f;
	$prg = gvwr_range( $st['period'], $rg['prev'] );
	$pf['from'] = $prg['from'];
	$pf['to']   = $prg['to'];
	$ps  = 'all' === $st['period'] ? array( 'qty' => 0 ) : gvwr_stats( $pf, false );
	$cats = gvwr_cat_map();
	$charts = array();

	// کلمات کلیدی: دو رتبه‌ی آخر تا پایان بازه
	$kw_rows = array();
	$up      = 0;
	$down    = 0;
	if ( gvwr_opt( 'client_show_keywords' ) ) {
		$kws = gvwr_keywords( $st['project'] ? $st['project'] : gvwr_default_project_id(), true );
		$ser = gvwr_rank_series( wp_list_pluck( $kws, 'id' ), min( $rg['to'], gvwr_today() ), 6 );
		foreach ( $kws as $k ) {
			if ( empty( $ser[ $k->id ] ) ) {
				continue;
			}
			$g = gvwr_growth( $ser[ $k->id ] );
			$g['kw']  = $k->keyword;
			$g['ser'] = $ser[ $k->id ];
			$kw_rows[] = $g;
			if ( 'up' === $g['dir'] ) {
				$up++;
			} elseif ( 'down' === $g['dir'] ) {
				$down++;
			}
		}
		usort( $kw_rows, function ( $a, $b ) {
			return $b['delta'] <=> $a['delta'];
		} );
	}

	ob_start();
	?>
	<div class="gvwr-chead">
		<div>
			<h2><?php echo esc_html( gvwr_opt( 'client_title' ) ); ?></h2>
			<p><?php echo esc_html( gvwr_site_name() ); ?> · <?php echo esc_html( $rg['label'] ); ?></p>
		</div>
		<div class="gvwr-chead-r">
			<?php if ( gvwr_opt( 'company_name' ) ) : ?><span class="gvwr-muted">تهیه‌شده توسط <?php echo esc_html( gvwr_opt( 'company_name' ) ); ?></span><?php endif; ?>
			<button type="button" class="gvwr-btn ghost sm gvwr-noprint" data-act="print">🖨 دریافت PDF</button>
		</div>
	</div>
	<div class="gvwr-noprint"><?php echo gvwr_filter_bar( $st, $rg, array( 'projects' => false ) ); // phpcs:ignore ?></div>

	<div class="gvwr-kpis">
		<?php
		echo gvwr_kpi( 'کارهای انجام‌شده', gvwr_n( $s['qty'] ), 'all' === $st['period'] ? '' : gvwr_delta_html( $s['qty'], $ps['qty'] ) ); // phpcs:ignore
		echo gvwr_kpi( 'روزهای فعالیت', gvwr_n( $s['days'] ) ); // phpcs:ignore
		echo gvwr_kpi( 'حوزه‌های کاری', gvwr_n( count( $s['by_cat'] ) ) ); // phpcs:ignore
		if ( $kw_rows ) {
			echo gvwr_kpi( 'کلمات بهبودیافته', gvwr_n( $up ), $down ? gvwr_fa( $down ) . ' کلمه افت داشته' : 'بدون افت', 'good' ); // phpcs:ignore
		}
		?>
	</div>

	<?php if ( ! $s['items'] ) : ?>
		<div class="gvwr-card"><?php echo gvwr_empty( 'در این بازه گزارشی برای نمایش وجود ندارد.' ); // phpcs:ignore ?></div>
	<?php else : ?>
		<div class="gvwr-grid2">
			<div class="gvwr-card">
				<div class="gvwr-cardhead"><h3>روند فعالیت</h3></div>
				<?php
				list( $tl, $tv ) = gvwr_trend( $s['by_day'], $rg );
				echo gvwr_chart_box( $charts, 'cl-trend', 'bar', $tl, array( array( 'label' => 'کارهای انجام‌شده', 'data' => $tv, 'backgroundColor' => '#0f766e', 'borderRadius' => 4 ) ), array( 'plugins' => array( 'legend' => array( 'display' => false ) ) ) ); // phpcs:ignore
				?>
			</div>
			<div class="gvwr-card">
				<div class="gvwr-cardhead"><h3>توزیع کارها</h3></div>
				<?php
				$cl = array();
				$cv = array();
				$cc = array();
				foreach ( $s['by_cat'] as $cid => $v ) {
					$cl[] = isset( $cats[ $cid ] ) ? $cats[ $cid ]->name : 'سایر';
					$cv[] = $v['qty'];
					$cc[] = isset( $cats[ $cid ] ) ? $cats[ $cid ]->color : '#94a3b8';
				}
				echo gvwr_chart_box( $charts, 'cl-cats', 'doughnut', $cl, array( array( 'data' => $cv, 'backgroundColor' => $cc, 'borderWidth' => 0 ) ), array( 'plugins' => array( 'legend' => array( 'position' => 'bottom' ) ) ) ); // phpcs:ignore
				?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $kw_rows ) : ?>
		<div class="gvwr-card">
			<div class="gvwr-cardhead"><h3>وضعیت رتبه‌ی کلمات کلیدی</h3><span class="gvwr-muted">مقایسه‌ی دو رتبه‌ی آخر ثبت‌شده</span></div>
			<?php
			$chg = array_values( array_filter( $kw_rows, function ( $g ) {
				return 0 !== $g['delta'];
			} ) );
			usort( $chg, function ( $a, $b ) {
				return abs( $b['delta'] ) <=> abs( $a['delta'] );
			} );
			$chg = array_slice( $chg, 0, 15 );
			if ( $chg ) {
				echo gvwr_chart_box( $charts, 'cl-rank', 'bar', wp_list_pluck( $chg, 'kw' ), array( array( 'label' => 'تغییر رتبه', 'data' => wp_list_pluck( $chg, 'delta' ), 'backgroundColor' => array_map( function ( $g ) { return $g['delta'] > 0 ? '#059669' : '#dc2626'; }, $chg ), 'borderRadius' => 4 ) ), array( 'indexAxis' => 'y', 'plugins' => array( 'legend' => array( 'display' => false ) ) ), max( 180, 34 * count( $chg ) + 50 ) ); // phpcs:ignore
				echo '<p class="gvwr-muted">سبز = بهبود رتبه (به صفحه‌ی بالاتر رفته)، قرمز = افت رتبه.</p>';
			}
			?>
			<div class="gvwr-scroll">
				<table class="gvwr-table">
					<thead><tr><th>کلمه‌ی کلیدی</th><th>رتبه‌ی قبلی</th><th>رتبه‌ی فعلی</th><th>تغییر</th><th>روند</th></tr></thead>
					<tbody>
					<?php foreach ( $kw_rows as $g ) : ?>
						<tr>
							<td><b><?php echo esc_html( $g['kw'] ); ?></b></td>
							<td><?php echo null !== $g['prev'] ? esc_html( gvwr_fa( $g['prev'] ) ) : '—'; ?></td>
							<td><b><?php echo esc_html( gvwr_fa( $g['last'] ) ); ?></b></td>
							<td><?php echo gvwr_rank_badge( $g ); // phpcs:ignore ?></td>
							<td><?php echo gvwr_sparkline( $g['ser'] ); // phpcs:ignore ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>

	<?php
	if ( $s['items'] ) :
		$items = gvwr_items( $f, 600, 0, 'DESC' );
		$by    = array();
		foreach ( $items as $r ) {
			$by[ (int) $r->category_id ][] = $r;
		}
		uasort( $by, function ( $a, $b ) {
			return count( $b ) <=> count( $a );
		} );
		?>
		<div class="gvwr-card">
			<div class="gvwr-cardhead"><h3>جزئیات کارهای انجام‌شده</h3><span class="gvwr-muted">به تفکیک دسته‌بندی</span></div>
			<?php
			foreach ( $by as $cid => $list ) :
				$c   = isset( $cats[ $cid ] ) ? $cats[ $cid ] : null;
				$sum = 0;
				foreach ( $list as $r ) {
					$sum += (int) $r->qty;
				}
				?>
				<details class="gvwr-cgroup" <?php echo count( $by ) <= 3 ? 'open' : ''; ?>>
					<summary style="--c:<?php echo esc_attr( $c ? $c->color : '#94a3b8' ); ?>"><span class="gvwr-dot"></span><?php echo esc_html( $c ? trim( $c->icon . ' ' . $c->name ) : 'سایر' ); ?><em><?php echo esc_html( gvwr_fa( $sum ) ); ?> کار</em></summary>
					<?php
					foreach ( $list as $r ) {
						echo gvwr_client_item( $r ); // phpcs:ignore
					}
					?>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<p class="gvwr-foot gvwr-muted">تاریخ تهیه‌ی گزارش: <?php echo esc_html( gvwr_date( gvwr_today(), 'long' ) ); ?></p>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => $charts );
}
