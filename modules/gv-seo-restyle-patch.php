<?php
/**
 * ==========================================================================
 *  Groot Vision SEO Reports — پچ «اعضای تیم» (فقط همین یک بخش، هیچ‌جای
 *  دیگه‌ی ظاهر افزونه دست‌نخورده می‌مونه: دکمه‌ها، تب‌ها، جدول‌ها همون قبلی‌ان)
 * --------------------------------------------------------------------------
 *  نحوه نصب (۳ قدم، هیچ‌کدام دیتابیس را دست نمی‌زند):
 *
 *  ۱) این فایل را در همون پوشه‌ای که فایل اصلی افزونه هست ذخیره کن، با نام
 *     gv-seo-restyle-patch.php  و در همون خط اول فایل اصلی (بعد از
 *     `if ( ! defined( 'ABSPATH' ) ) { exit; }`) این خط را اضافه کن:
 *         require_once __DIR__ . '/gv-seo-restyle-patch.php';
 *
 *  ۲) در تابع gv_sr_admin_styles() فایل اصلی، بلافاصله زیر خط
 *         <style>
 *     این خط را اضافه کن (این فقط ظاهر کارت‌های اعضای تیم رو می‌سازه،
 *     روی بقیه‌ی افزونه هیچ اثری نداره):
 *         <?php gv_sr_restyle_css(); ?>
 *
 *  ۳) در تابع gv_sr_render_team_tab() فایل اصلی، دقیقاً بعد از خط
 *         echo '</div>';
 *     (همونی که زیر خط `echo '<span class="gvsr-hint-inline">کارمندان به
 *     این بخش دسترسی ندارند.</span>';` میاد) یک خط اضافه کن:
 *         gv_sr_render_team_showcase( gv_sr_get_employees( true ) );
 *
 *  همین! کارت‌های پروژه و بقیه‌ی ظاهر افزونه دست‌نخورده باقی می‌مونن.
 * ==========================================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ==========================================================================
   ۱) آواتار — دایره‌ی رنگی با حروف اول اسم (چون آپلود عکس نداریم)
   ========================================================================== */
function gv_sr_avatar_palette() {
	return array( '#0f766e', '#2563eb', '#7c3aed', '#db2777', '#ea580c', '#059669', '#4f46e5', '#0891b2', '#b45309', '#dc2626' );
}

function gv_sr_avatar_color( $seed ) {
	$palette = gv_sr_avatar_palette();
	$idx     = abs( crc32( (string) $seed ) ) % count( $palette );
	return $palette[ $idx ];
}

function gv_sr_initials( $name ) {
	$name  = trim( (string) $name );
	if ( '' === $name ) { return '؟'; }
	$parts = preg_split( '/\s+/u', $name );
	$first = mb_substr( $parts[0], 0, 1 );
	$last  = isset( $parts[1] ) ? mb_substr( $parts[1], 0, 1 ) : '';
	return $first . $last;
}

/** یک آواتار دایره‌ای رنگی؛ $online=true یعنی نقطه‌ی سبز فعال بگذار */
function gv_sr_avatar_html( $name, $size = 40, $online = null ) {
	$color = esc_attr( gv_sr_avatar_color( $name ) );
	$init  = esc_html( gv_sr_initials( $name ) );
	$font  = round( $size * 0.38 );
	$html  = '<span class="gvsr-avatar" style="--gvsr-av-size:' . (int) $size . 'px;--gvsr-av-color:' . $color . ';--gvsr-av-font:' . $font . 'px;" title="' . esc_attr( $name ) . '">';
	$html .= '<span class="gvsr-avatar-inner">' . $init . '</span>';
	if ( null !== $online ) {
		$html .= '<span class="gvsr-avatar-dot ' . ( $online ? 'is-online' : 'is-offline' ) . '"></span>';
	}
	$html .= '</span>';
	return $html;
}

/** چند آواتار روی هم (استک)؛ $names آرایه‌ای از نام‌ها */
function gv_sr_avatar_stack_html( $names, $size = 32, $max = 4 ) {
	$names = array_values( array_filter( $names ) );
	if ( empty( $names ) ) { return '<span class="gvsr-cs-none">بدون عضو</span>'; }

	$shown   = array_slice( $names, 0, $max );
	$extra   = count( $names ) - count( $shown );
	$html    = '<span class="gvsr-avatar-stack">';
	foreach ( $shown as $n ) {
		$html .= gv_sr_avatar_html( $n, $size );
	}
	if ( $extra > 0 ) {
		$html .= '<span class="gvsr-avatar gvsr-avatar-more" style="--gvsr-av-size:' . (int) $size . 'px;--gvsr-av-font:' . round( $size * 0.34 ) . 'px;"><span class="gvsr-avatar-inner">+' . esc_html( gv_sr_fa_digits( $extra ) ) . '</span></span>';
	}
	$html .= '</span>';
	return $html;
}

/* ==========================================================================
   ۲) کارت پروژه — شبیه کارت‌های «بازطراحی وبسایت / اپلیکیشن موبایل»
   ========================================================================== */
function gv_sr_render_projects_grid( $projects ) {
	if ( empty( $projects ) ) { return; }

	$progress_colors = array(
		'green'  => '#16a34a',
		'yellow' => '#d97706',
		'red'    => '#dc2626',
	);

	echo '<div class="gvsr-project-cards">';
	foreach ( $projects as $p ) {
		$members     = gv_sr_get_project_members( $p->id );
		$member_names = wp_list_pluck( $members, 'employee_name' );
		$status_map   = gv_sr_project_statuses();
		$prio_map     = gv_sr_project_priorities();
		$status_info  = isset( $status_map[ $p->status ] ) ? $status_map[ $p->status ] : array( 'label' => $p->status, 'color' => '#64748b' );
		$prio_info    = isset( $prio_map[ $p->priority ] ) ? $prio_map[ $p->priority ] : array( 'label' => $p->priority, 'color' => '#64748b' );
		$bar_color    = isset( $progress_colors[ $p->health ] ) ? $progress_colors[ $p->health ] : '#111827';
		$prio_icon    = 'urgent' === $p->priority ? '🔴' : ( 'high' === $p->priority ? '🟠' : ( 'low' === $p->priority ? '🟢' : '🟡' ) );

		echo '<a class="gvsr-pcard" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects&view=' . $p->id ) ) . '">';
		echo   '<div class="gvsr-pcard-top">';
		echo     '<span class="gvsr-pcard-title">' . esc_html( $p->title ) . '</span>';
		echo     '<span class="gvsr-pill" style="background:' . esc_attr( $prio_info['color'] ) . '1a;color:' . esc_attr( $prio_info['color'] ) . ';">' . esc_html( $prio_icon . ' ' . $prio_info['label'] ) . '</span>';
		echo   '</div>';
		echo   '<div class="gvsr-pcard-client">' . esc_html( $p->client_name ) . '</div>';

		echo   '<div class="gvsr-progress-row">';
		echo     '<div class="gvsr-progress-track"><div class="gvsr-progress-fill" style="width:' . (int) $p->progress . '%;background:' . esc_attr( $bar_color ) . ';"></div></div>';
		echo     '<span class="gvsr-progress-pct">' . esc_html( gv_sr_fa_digits( $p->progress ) ) . '٪</span>';
		echo   '</div>';

		echo   '<div class="gvsr-pcard-bottom">';
		echo     '<span class="gvsr-pill gvsr-pill-status" style="background:' . esc_attr( $status_info['color'] ) . '1a;color:' . esc_attr( $status_info['color'] ) . ';">' . esc_html( $status_info['label'] ) . '</span>';
		echo     gv_sr_avatar_stack_html( $member_names, 26, 4 ); // phpcs:ignore
		echo   '</div>';
		echo '</a>';
	}
	echo '</div>';
}

/* ==========================================================================
   ۳) نمایشگاه «اعضای تیم» — شبکه‌ای از کارت‌های آواتاردار
   ========================================================================== */
function gv_sr_render_team_showcase( $employees ) {
	if ( empty( $employees ) ) { return; }
	?>
	<div class="gvsr-report-card gvsr-team-showcase">
		<h3>🧑‍💼 اعضای تیم <span class="gvsr-toggle-count">(<?php echo esc_html( number_format_i18n( count( $employees ) ) ); ?> نفر)</span></h3>
		<div class="gvsr-team-grid">
			<?php foreach ( $employees as $e ) :
				$active_timer = gv_sr_get_active_timer( $e->id );
				$hours_total  = gv_sr_employee_total_hours( $e->id );
				$edit_url     = admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&edit_emp=' . $e->id );
				$export_url   = wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_export_team_timesheet&employee_id=' . $e->id ), GV_SR_NONCE );
				?>
				<div class="gvsr-team-card">
					<?php echo gv_sr_avatar_html( $e->name, 56, (bool) $active_timer ); // phpcs:ignore ?>
					<div class="gvsr-team-card-name"><?php echo esc_html( $e->name ); ?></div>
					<div class="gvsr-team-card-sub">
						<?php echo (int) $e->active === 1 ? '<span class="gvsr-dot-label"><i class="gvsr-dot-green"></i> فعال</span>' : '<span class="gvsr-dot-label"><i class="gvsr-dot-gray"></i> غیرفعال</span>'; ?>
						<?php if ( $active_timer ) : ?> · <span style="color:#16a34a;font-weight:700;">در حال کار</span><?php endif; ?>
					</div>
					<div class="gvsr-team-card-hours"><?php echo esc_html( gv_sr_fa_digits( $hours_total ) ); ?> ساعت کارکرد کل</div>
					<div class="gvsr-team-card-actions">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="gvsr-icon-btn" title="ویرایش کارمند">✏️</a>
						<a href="<?php echo esc_url( $export_url ); ?>" class="gvsr-icon-btn" title="خروجی اکسل کارکرد">📥</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&employee_id=' . $e->id ) ); ?>" class="gvsr-icon-btn gvsr-icon-btn-accent" title="مشاهده کارکرد">👁️</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/* ==========================================================================
   ۴) CSS — فقط برای آواتارها و کارت‌های «اعضای تیم» (به هیچ کلاس عمومی
   دیگه‌ای دست نمی‌زنه؛ کارت‌های پروژه هم این‌جا تعریف شده ولی تا وقتی
   gv_sr_render_projects_grid() صدا زده نشه، هیچ‌جا نمایش داده نمی‌شه)
   ========================================================================== */
function gv_sr_restyle_css() {
	?>
	/* ------------------------------------------------------------------
	   توجه: این استایل‌ها فقط روی کلاس‌های gvsr-avatar* / gvsr-team-*
	   اثر دارن (یعنی فقط ظاهر بخش «اعضای تیم»). هیچ کلاس عمومی افزونه
	   (دکمه، تب، جدول، هدر و ...) این‌جا override نشده.
	   ------------------------------------------------------------------ */

	/* ---- آواتار ---- */
	.gvsr-avatar{
		position:relative; display:inline-flex; align-items:center; justify-content:center;
		width:var(--gvsr-av-size); height:var(--gvsr-av-size); border-radius:50%;
		background:var(--gvsr-av-color); color:#fff; font-weight:800; font-size:var(--gvsr-av-font);
		border:2px solid #fff; box-shadow:0 2px 6px rgba(17,24,39,.14); flex:0 0 auto;
	}
	.gvsr-avatar-inner{line-height:1;}
	.gvsr-avatar-dot{position:absolute; bottom:-1px; inset-inline-end:-1px; width:28%; height:28%; min-width:8px; min-height:8px; border-radius:50%; border:2px solid #fff;}
	.gvsr-avatar-dot.is-online{background:var(--gv-green);}
	.gvsr-avatar-dot.is-offline{background:#cbd5e1;}
	.gvsr-avatar-stack{display:inline-flex; align-items:center;}
	.gvsr-avatar-stack .gvsr-avatar{margin-inline-start:-10px;}
	.gvsr-avatar-stack .gvsr-avatar:first-child{margin-inline-start:0;}
	.gvsr-avatar-more{background:#e5e7eb!important; color:#374151!important;}

	/* ---- کارت‌های پروژه ---- */
	.gvsr-project-cards{display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:14px; max-width:1100px; margin-bottom:18px;}
	.gvsr-pcard{
		display:block; background:var(--gv-surface); border:1px solid var(--gv-border); border-radius:var(--gv-radius-lg);
		padding:16px 16px 14px; box-shadow:var(--gv-shadow); text-decoration:none; color:inherit; transition:transform .12s ease, box-shadow .12s ease;
	}
	.gvsr-pcard:hover{transform:translateY(-2px); box-shadow:var(--gv-shadow-lift); color:inherit;}
	.gvsr-pcard-top{display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:6px;}
	.gvsr-pcard-title{font-size:13.5px; font-weight:800; color:var(--gv-ink); line-height:1.5;}
	.gvsr-pcard-client{font-size:11.3px; color:var(--gv-ink-soft); margin-bottom:14px;}
	.gvsr-pill{display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:999px; font-size:10.3px; font-weight:800; white-space:nowrap;}
	.gvsr-progress-row{display:flex; align-items:center; gap:8px; margin-bottom:14px;}
	.gvsr-progress-track{flex:1; height:7px; background:#eef0f3; border-radius:999px; overflow:hidden;}
	.gvsr-progress-fill{height:100%; border-radius:999px; transition:width .2s ease;}
	.gvsr-progress-pct{font-size:10.8px; font-weight:800; color:var(--gv-ink-soft); min-width:30px; text-align:left;}
	.gvsr-pcard-bottom{display:flex; align-items:center; justify-content:space-between;}

	/* ---- نمایشگاه اعضای تیم ---- */
	.gvsr-team-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px;}
	.gvsr-team-card{
		background:#fbfcfd; border:1px solid var(--gv-border); border-radius:var(--gv-radius-md);
		padding:18px 12px 14px; text-align:center; transition:box-shadow .12s ease, transform .12s ease;
	}
	.gvsr-team-card:hover{box-shadow:var(--gv-shadow-lift); transform:translateY(-2px); background:#fff;}
	.gvsr-team-card .gvsr-avatar{margin:0 auto 10px;}
	.gvsr-team-card-name{font-size:12.6px; font-weight:800; color:var(--gv-ink); margin-bottom:3px;}
	.gvsr-team-card-sub{font-size:10.6px; color:var(--gv-ink-soft); margin-bottom:6px;}
	.gvsr-team-card-hours{font-size:10.3px; color:var(--gv-muted); margin-bottom:12px;}
	.gvsr-dot-label{display:inline-flex; align-items:center; gap:4px;}
	.gvsr-dot-green,.gvsr-dot-gray{display:inline-block; width:7px; height:7px; border-radius:50%;}
	.gvsr-dot-green{background:var(--gv-green);}
	.gvsr-dot-gray{background:#cbd5e1;}
	.gvsr-team-card-actions{display:flex; align-items:center; justify-content:center; gap:6px;}
	.gvsr-icon-btn{
		display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:50%;
		background:#fff; border:1px solid var(--gv-border); text-decoration:none; font-size:13px; box-shadow:0 1px 2px rgba(17,24,39,.05);
		transition:transform .1s ease, background .12s ease;
	}
	.gvsr-icon-btn:hover{transform:translateY(-1px); background:#f3f4f6;}
	.gvsr-icon-btn-accent{background:var(--gv-green); border-color:var(--gv-green);}
	.gvsr-icon-btn-accent:hover{background:#128a3e;}
	<?php
}