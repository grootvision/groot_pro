<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * همه‌ی صفحه‌ها «view» هستند: تابعی که ['html'=>..., 'charts'=>[...]] برمی‌گرداند.
 * JS با AJAX (op=view) آن‌ها را لود می‌کند و نمودارها را رسم می‌کند.
 * برای ویرایش ظاهر هر صفحه، فقط تابع gvwr_view_* همان صفحه را عوض کنید.
 */

/* ------------------------------------------------------------------ */
/* ابزارهای مشترک UI                                                   */
/* ------------------------------------------------------------------ */

function gvwr_state( $src = null ) {
	$s      = null === $src ? $_POST : $src; // phpcs:ignore
	$period = isset( $s['period'] ) ? sanitize_key( $s['period'] ) : 'month';
	return array(
		'period'  => in_array( $period, array( 'day', 'week', 'month', 'year', 'all' ), true ) ? $period : 'month',
		'anchor'  => isset( $s['anchor'] ) && gvwr_valid_date( $s['anchor'] ) ? $s['anchor'] : gvwr_today(),
		'user'    => isset( $s['user'] ) ? (int) $s['user'] : 0,
		'cat'     => isset( $s['cat'] ) ? (int) $s['cat'] : 0,
		'project' => isset( $s['project'] ) ? (int) $s['project'] : 0,
		'status'  => isset( $s['status'] ) ? sanitize_key( $s['status'] ) : '',
		'q'       => isset( $s['q'] ) ? sanitize_text_field( wp_unslash( $s['q'] ) ) : '',
		'page'    => isset( $s['page'] ) ? max( 1, (int) $s['page'] ) : 1,
	);
}

function gvwr_state_filters( $st, $rg ) {
	$f = array( 'from' => $rg['from'], 'to' => $rg['to'] );
	if ( $st['user'] ) {
		$f['user_id'] = $st['user'];
	}
	if ( $st['cat'] ) {
		$f['category_id'] = $st['cat'];
	}
	if ( $st['project'] ) {
		$f['project_id'] = $st['project'];
	}
	if ( $st['status'] ) {
		$f['status'] = $st['status'];
	}
	if ( '' !== $st['q'] ) {
		$f['q'] = $st['q'];
	}
	return $f;
}

function gvwr_cat_options( $selected = 0 ) {
	$groups = array();
	foreach ( gvwr_categories() as $c ) {
		$groups[ $c->grp ? $c->grp : 'سایر' ][] = $c;
	}
	$h = '';
	foreach ( $groups as $g => $list ) {
		$h .= '<optgroup label="' . esc_attr( $g ) . '">';
		foreach ( $list as $c ) {
			$h .= '<option value="' . (int) $c->id . '"' . selected( (int) $selected, (int) $c->id, false ) . '>' . esc_html( trim( $c->icon . ' ' . $c->name ) ) . '</option>';
		}
		$h .= '</optgroup>';
	}
	return $h;
}

function gvwr_badge( $status ) {
	$n = array( 'pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده' );
	return '<span class="gvwr-badge st-' . esc_attr( $status ) . '">' . esc_html( isset( $n[ $status ] ) ? $n[ $status ] : $status ) . '</span>';
}

function gvwr_kpi( $label, $value, $sub = '', $tone = '' ) {
	return '<div class="gvwr-kpi ' . esc_attr( $tone ) . '"><span class="gvwr-kpi-l">' . esc_html( $label ) . '</span><b class="gvwr-kpi-v">' . $value . '</b>' . ( $sub ? '<span class="gvwr-kpi-s">' . $sub . '</span>' : '' ) . '</div>';
}

/** درصد تغییر نسبت به دوره‌ی قبل */
function gvwr_delta_html( $cur, $prev ) {
	if ( ! $prev ) {
		return $cur ? '<span class="gvwr-up">جدید</span>' : '';
	}
	$p   = round( ( $cur - $prev ) / $prev * 100 );
	$cls = $p > 0 ? 'gvwr-up' : ( $p < 0 ? 'gvwr-down' : 'gvwr-same' );
	$ic  = $p > 0 ? '▲' : ( $p < 0 ? '▼' : '=' );
	return '<span class="' . $cls . '">' . $ic . ' ' . gvwr_fa( abs( $p ) ) . '٪ نسبت به دوره‌ی قبل</span>';
}

function gvwr_chart_box( &$charts, $id, $type, $labels, $datasets, $opts = array(), $h = 260 ) {
	$charts[] = array( 'id' => $id, 'type' => $type, 'labels' => $labels, 'datasets' => $datasets, 'options' => $opts );
	return '<div class="gvwr-chart" style="height:' . (int) $h . 'px"><canvas id="' . esc_attr( $id ) . '"></canvas></div>';
}

function gvwr_empty( $msg ) {
	return '<div class="gvwr-empty">' . esc_html( $msg ) . '</div>';
}

function gvwr_pager( $total, $page, $per ) {
	$pages = (int) ceil( $total / $per );
	if ( $pages < 2 ) {
		return '';
	}
	$h = '<div class="gvwr-pager">';
	for ( $i = 1; $i <= $pages; $i++ ) {
		if ( $pages > 9 && abs( $i - $page ) > 2 && 1 !== $i && $i !== $pages ) {
			continue;
		}
		$h .= '<button type="button" class="gvwr-pg' . ( $i === $page ? ' on' : '' ) . '" data-act="page" data-page="' . $i . '">' . gvwr_fa( $i ) . '</button>';
	}
	return $h . '</div>';
}

/** نوار فیلتر مشترک */
function gvwr_filter_bar( $st, $rg, $o = array() ) {
	$o = wp_parse_args( $o, array( 'users' => false, 'cats' => true, 'status' => false, 'search' => false, 'projects' => true, 'project_list' => null, 'periods' => array( 'day', 'week', 'month', 'year', 'all' ) ) );
	ob_start();
	?>
	<div class="gvwr-filters">
		<div class="gvwr-seg" role="tablist">
			<?php foreach ( $o['periods'] as $p ) : ?>
				<button type="button" class="<?php echo $st['period'] === $p ? 'on' : ''; ?>" data-act="period" data-period="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( gvwr_period_name( $p ) ); ?></button>
			<?php endforeach; ?>
		</div>
		<input type="hidden" data-f="period" value="<?php echo esc_attr( $st['period'] ); ?>">
		<input type="hidden" data-f="anchor" value="<?php echo esc_attr( $rg['anchor'] ); ?>">
		<?php if ( 'all' !== $st['period'] ) : ?>
			<div class="gvwr-nav">
				<button type="button" class="gvwr-ib" data-act="nav" data-anchor="<?php echo esc_attr( $rg['prev'] ); ?>" title="قبلی">›</button>
				<span class="gvwr-nav-l"><?php echo esc_html( $rg['label'] ); ?></span>
				<button type="button" class="gvwr-ib" data-act="nav" data-anchor="<?php echo esc_attr( $rg['next'] ); ?>" <?php disabled( '' === $rg['next'] ); ?> title="بعدی">‹</button>
			</div>
		<?php else : ?>
			<span class="gvwr-nav-l"><?php echo esc_html( $rg['label'] ); ?></span>
		<?php endif; ?>

		<?php if ( $o['users'] ) : ?>
			<select data-f="user" class="gvwr-sel"><option value="0">همه‌ی کارمندان</option>
				<?php foreach ( gvwr_employees() as $e ) : ?>
					<option value="<?php echo (int) $e->user_id; ?>" <?php selected( $st['user'], (int) $e->user_id ); ?>><?php echo esc_html( $e->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<?php if ( $o['cats'] ) : ?>
			<select data-f="cat" class="gvwr-sel"><option value="0">همه‌ی دسته‌بندی‌ها</option><?php echo gvwr_cat_options( $st['cat'] ); // phpcs:ignore ?></select>
		<?php endif; ?>
		<?php
		$plist = is_array( $o['project_list'] ) ? $o['project_list'] : gvwr_projects();
		if ( $o['projects'] && count( $plist ) > 1 ) :
			?>
			<select data-f="project" class="gvwr-sel"><option value="0">همه‌ی پروژه‌ها</option>
				<?php foreach ( $plist as $p ) : ?>
					<option value="<?php echo (int) $p->id; ?>" <?php selected( $st['project'], (int) $p->id ); ?>><?php echo esc_html( $p->name ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<?php if ( $o['status'] ) : ?>
			<select data-f="status" class="gvwr-sel">
				<option value="">همه‌ی وضعیت‌ها</option>
				<option value="pending" <?php selected( $st['status'], 'pending' ); ?>>در انتظار تأیید</option>
				<option value="approved" <?php selected( $st['status'], 'approved' ); ?>>تأیید شده</option>
				<option value="rejected" <?php selected( $st['status'], 'rejected' ); ?>>رد شده</option>
			</select>
		<?php endif; ?>
		<?php if ( $o['search'] ) : ?>
			<input type="search" data-f="q" class="gvwr-sel gvwr-search" placeholder="جستجو در توضیحات…" value="<?php echo esc_attr( $st['q'] ); ?>">
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function gvwr_shots_html( $csv ) {
	$h = '';
	foreach ( array_filter( array_map( 'intval', explode( ',', (string) $csv ) ) ) as $id ) {
		$full = wp_get_attachment_image_url( $id, 'full' );
		$th   = wp_get_attachment_image_url( $id, 'thumbnail' );
		if ( $full && $th ) {
			$h .= '<a class="gvwr-shot" href="' . esc_url( $full ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $th ) . '" alt="اسکرین‌شات" loading="lazy"></a>';
		}
	}
	return $h ? '<div class="gvwr-shots-row">' . $h . '</div>' : '';
}

/** کارت یک گزارش. $mode: mine | mgr */
function gvwr_item_html( $r, $mode = 'mine' ) {
	$mgr    = 'mgr' === $mode;
	$locked = ! $mgr && 'approved' === $r->status && ! gvwr_opt( 'allow_edit_approved' );
	ob_start();
	?>
	<div class="gvwr-item<?php echo $mgr ? ' sel' : ''; ?>" data-id="<?php echo (int) $r->id; ?>">
		<?php if ( $mgr ) : ?><label class="gvwr-chk"><input type="checkbox" data-sel value="<?php echo (int) $r->id; ?>"></label><?php endif; ?>
		<div class="gvwr-item-cat" style="--c:<?php echo esc_attr( $r->cat_color ? $r->cat_color : '#0f766e' ); ?>"><?php echo esc_html( $r->cat_icon ); ?><span><?php echo esc_html( $r->cat_name ); ?></span></div>
		<div class="gvwr-item-body">
			<p><?php echo nl2br( esc_html( $r->description ) ); ?></p>
			<?php if ( $r->url ) : ?><a class="gvwr-link" dir="ltr" href="<?php echo esc_url( $r->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $r->url, PHP_URL_HOST ) . wp_parse_url( $r->url, PHP_URL_PATH ) ); ?></a><?php endif; ?>
			<?php echo gvwr_shots_html( $r->screenshots ); // phpcs:ignore ?>
			<?php if ( 'rejected' === $r->status && $r->manager_note ) : ?><div class="gvwr-note">پیام مدیر: <?php echo esc_html( $r->manager_note ); ?></div><?php endif; ?>
		</div>
		<div class="gvwr-item-meta">
			<?php if ( $mgr ) : ?><span class="gvwr-who"><?php echo esc_html( $r->display_name ); ?></span><?php endif; ?>
			<span><?php echo esc_html( gvwr_date( $r->work_date ) ); ?></span>
			<?php if ( $r->qty > 1 ) : ?><span title="تعداد">× <?php echo esc_html( gvwr_fa( $r->qty ) ); ?></span><?php endif; ?>
			<?php if ( $r->start_time || $r->end_time ) : ?><span class="gvwr-time" dir="ltr"><?php echo esc_html( gvwr_time( $r->start_time ) . ' – ' . gvwr_time( $r->end_time ) ); ?></span><?php endif; ?>
			<?php echo gvwr_badge( $r->status ); // phpcs:ignore ?>
			<div class="gvwr-acts">
				<?php if ( $mgr && 'approved' !== $r->status ) : ?><button type="button" class="gvwr-ib ok" data-act="approve" data-id="<?php echo (int) $r->id; ?>" title="تأیید">✓</button><?php endif; ?>
				<?php if ( $mgr && 'rejected' !== $r->status ) : ?><button type="button" class="gvwr-ib no" data-act="reject-form" data-id="<?php echo (int) $r->id; ?>" title="رد">✕</button><?php endif; ?>
				<?php if ( ! $locked ) : ?>
					<button type="button" class="gvwr-ib" data-act="item-edit" data-id="<?php echo (int) $r->id; ?>" title="ویرایش">✎</button>
					<button type="button" class="gvwr-ib no" data-act="item-del" data-id="<?php echo (int) $r->id; ?>" title="حذف">🗑</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** فیلدهای یک ردیف گزارش (هم فرم سریع، هم پنجره‌ی ویرایش) */
function gvwr_row_html( $it = null ) {
	$shots = $it ? array_filter( array_map( 'intval', explode( ',', (string) $it->screenshots ) ) ) : array();
	$more  = $it && ( $it->url || $it->start_time || $it->end_time || $shots );
	ob_start();
	?>
	<div class="gvwr-row">
		<div class="gvwr-row-main">
			<select data-k="cat" aria-label="دسته‌بندی"><?php echo gvwr_cat_options( $it ? $it->category_id : 0 ); // phpcs:ignore ?></select>
			<textarea data-k="desc" rows="1" placeholder="چه کاری انجام دادید؟"><?php echo $it ? esc_textarea( $it->description ) : ''; ?></textarea>
			<input type="number" data-k="qty" min="1" max="999" value="<?php echo $it ? (int) $it->qty : 1; ?>" title="تعداد (مثلاً چند مقاله)" aria-label="تعداد">
			<button type="button" class="gvwr-ib" data-act="row-more" title="لینک، ساعت، اسکرین‌شات">⋯</button>
			<button type="button" class="gvwr-ib add" data-act="row-add" title="افزودن ردیف جدید">+</button>
			<button type="button" class="gvwr-ib no" data-act="row-del" title="حذف ردیف">×</button>
		</div>
		<div class="gvwr-row-more" <?php echo $more ? '' : 'hidden'; ?>>
			<input type="url" data-k="url" dir="ltr" placeholder="لینک صفحه‌ی مرتبط (اختیاری)" value="<?php echo $it ? esc_attr( $it->url ) : ''; ?>">
			<label>شروع <input type="time" data-k="start" value="<?php echo $it ? esc_attr( substr( (string) $it->start_time, 0, 5 ) ) : ''; ?>"></label>
			<label>پایان <input type="time" data-k="end" value="<?php echo $it ? esc_attr( substr( (string) $it->end_time, 0, 5 ) ) : ''; ?>"></label>
			<span class="gvwr-hint">ساعت‌ها اختیاری‌اند و فقط برای مدیر شرکت دیده می‌شوند، نه کارفرما.</span>
			<div class="gvwr-shots">
				<button type="button" class="gvwr-btn ghost" data-act="shot-pick">📷 اسکرین‌شات</button>
				<span class="gvwr-hint">یا تصویر را با Ctrl+V در همین ردیف بچسبانید.</span>
				<input type="file" accept="image/*" multiple hidden data-k="file">
				<input type="hidden" data-k="shots" value="<?php echo esc_attr( implode( ',', $shots ) ); ?>">
				<div class="gvwr-thumbs">
					<?php foreach ( $shots as $id ) : ?>
						<span class="gvwr-th" data-id="<?php echo (int) $id; ?>"><img src="<?php echo esc_url( wp_get_attachment_image_url( $id, 'thumbnail' ) ); ?>" alt=""><button type="button" data-act="shot-del">×</button></span>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** [kind, trend labels, values] برای نمودار روند */
function gvwr_trend( $by_day, $rg ) {
	$labels = array();
	$vals   = array();
	if ( in_array( $rg['period'], array( 'day', 'week', 'month' ), true ) ) {
		for ( $d = $rg['from']; $d <= $rg['to']; $d = gvwr_add_days( $d, 1 ) ) {
			$labels[] = gvwr_date( $d, 'short' );
			$vals[]   = isset( $by_day[ $d ] ) ? $by_day[ $d ]['qty'] : 0;
		}
		return array( $labels, $vals );
	}
	$b = array();
	foreach ( $by_day as $d => $v ) {
		$p = array_map( 'intval', explode( '-', $d ) );
		if ( gvwr_is_jalali() ) {
			$j = gvwr_g2j( $p[0], $p[1], $p[2] );
			$k = sprintf( '%04d-%02d', $j[0], $j[1] );
		} else {
			$k = sprintf( '%04d-%02d', $p[0], $p[1] );
		}
		$b[ $k ] = ( isset( $b[ $k ] ) ? $b[ $k ] : 0 ) + $v['qty'];
	}
	ksort( $b );
	foreach ( $b as $k => $v ) {
		list( $y, $m ) = explode( '-', $k );
		$labels[]      = gvwr_is_jalali() ? gvwr_jmonth( (int) $m ) . ' ' . gvwr_fa( (int) $y ) : $y . '/' . $m;
		$vals[]        = $v;
	}
	return array( $labels, $vals );
}

/* ------------------------------------------------------------------ */
/* ۱) فرم ثبت سریع گزارش (کارمند)                                      */
/* ------------------------------------------------------------------ */

function gvwr_view_emp_form( $st ) {
	$uid   = get_current_user_id();
	$today = gvwr_today();
	$tf    = array( 'from' => $today, 'to' => $today, 'user_id' => $uid );
	$items = gvwr_items( $tf, 50 );
	$rej   = gvwr_rejected_count( $uid );
	$projs = gvwr_projects_for_user( get_current_user_id() );
	ob_start();
	?>
	<?php if ( $rej ) : ?>
		<div class="gvwr-alert warn">⚠️ <?php echo esc_html( gvwr_fa( $rej ) ); ?> گزارش شما توسط مدیر رد شده است. <a href="<?php echo esc_url( admin_url( 'admin.php?page=gv-wr-my&gvwr_status=rejected' ) ); ?>">مشاهده و اصلاح</a></div>
	<?php endif; ?>
	<div class="gvwr-card gvwr-formcard">
		<div class="gvwr-formhead">
			<div>
				<h3>گزارش کار امروز</h3>
				<p class="gvwr-muted">هر کار را انتخاب و توضیح دهید. با Enter یا + ردیف بعدی باز می‌شود. ردیف‌های خالی نادیده گرفته می‌شوند.</p>
			</div>
			<div class="gvwr-formmeta">
				<label>تاریخ
					<input type="date" id="gvwr-date" data-k="date" value="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( $today ); ?>">
					<span class="gvwr-jlabel" id="gvwr-jlabel"></span>
				</label>
				<?php if ( count( $projs ) > 1 ) : ?>
					<label>پروژه
						<select id="gvwr-project"><?php foreach ( $projs as $p ) : ?><option value="<?php echo (int) $p->id; ?>"><?php echo esc_html( $p->name ); ?></option><?php endforeach; ?></select>
					</label>
				<?php endif; ?>
			</div>
		</div>
		<div id="gvwr-rows" class="gvwr-rows"></div>
		<template id="gvwr-row-tpl"><?php echo gvwr_row_html(); // phpcs:ignore ?></template>
		<div class="gvwr-formfoot">
			<button type="button" class="gvwr-btn ghost" data-act="row-add-end">+ ردیف جدید</button>
			<button type="button" class="gvwr-btn primary lg" data-act="submit-items">ثبت گزارش‌ها</button>
		</div>
	</div>

	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>ثبت‌شده‌های امروز</h3><span class="gvwr-muted"><?php echo esc_html( gvwr_fa( count( $items ) ) ); ?> مورد</span></div>
		<?php
		if ( $items ) {
			foreach ( $items as $r ) {
				echo gvwr_item_html( $r, 'mine' ); // phpcs:ignore
			}
		} else {
			echo gvwr_empty( 'امروز هنوز گزارشی ثبت نکرده‌اید.' ); // phpcs:ignore
		}
		?>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}

/* ------------------------------------------------------------------ */
/* ۲) گزارش‌های من (کارمند)                                            */
/* ------------------------------------------------------------------ */

function gvwr_view_my_reports( $st ) {
	$uid = get_current_user_id();
	$rg  = gvwr_range( $st['period'], $st['anchor'] );
	$f   = gvwr_state_filters( $st, $rg );
	$f['user_id'] = $uid;
	$per   = 20;
	$total = gvwr_items_count( $f );
	$items = gvwr_items( $f, $per, ( $st['page'] - 1 ) * $per );
	$s     = gvwr_stats( $f );
	ob_start();
	echo gvwr_filter_bar( $st, $rg, array( 'status' => true, 'search' => true ) ); // phpcs:ignore
	?>
	<div class="gvwr-kpis">
		<?php
		echo gvwr_kpi( 'کل کارها', gvwr_n( $s['qty'] ), gvwr_fa( $s['items'] ) . ' ردیف گزارش' ); // phpcs:ignore
		echo gvwr_kpi( 'روزهای فعالیت', gvwr_n( $s['days'] ) ); // phpcs:ignore
		echo gvwr_kpi( 'تأیید شده', gvwr_n( $s['approved'] ), '', 'good' ); // phpcs:ignore
		echo gvwr_kpi( 'در انتظار / ردشده', gvwr_n( $s['pending'] ) . ' / ' . gvwr_n( $s['rejected'] ), '', $s['rejected'] ? 'warn' : '' ); // phpcs:ignore
		?>
	</div>
	<div class="gvwr-card">
		<?php
		if ( $items ) {
			foreach ( $items as $r ) {
				echo gvwr_item_html( $r, 'mine' ); // phpcs:ignore
			}
			echo gvwr_pager( $total, $st['page'], $per ); // phpcs:ignore
		} else {
			echo gvwr_empty( 'گزارشی در این بازه پیدا نشد.' ); // phpcs:ignore
		}
		?>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}
