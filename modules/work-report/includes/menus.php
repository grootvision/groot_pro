<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* دسترسی: مدیر کل وردپرس همیشه همه‌ی دسترسی‌های ماژول را دارد            */
/* ------------------------------------------------------------------ */
add_filter( 'user_has_cap', function ( $all ) {
	if ( ! empty( $all['manage_options'] ) ) {
		$all['gvwr_submit']      = true;
		$all['gvwr_manage']      = true;
		$all['gvwr_view_client'] = true;
	}
	return $all;
} );

/* ------------------------------------------------------------------ */
/* فایل‌های CSS/JS                                                     */
/* ------------------------------------------------------------------ */
function gvwr_enqueue_assets( $front = false ) {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	wp_enqueue_style( 'gvwr', GVWR_URL . 'assets/css/wr.css', array(), GVWR_VERSION );
	wp_enqueue_script( 'gvwr-chart', GVWR_URL . 'assets/js/chart.umd.js', array(), '4.4.4', true );
	wp_enqueue_script( 'gvwr', GVWR_URL . 'assets/js/wr.js', array( 'gvwr-chart' ), GVWR_VERSION, true );
	wp_localize_script( 'gvwr', 'GVWR', array(
		'ajax'   => admin_url( 'admin-ajax.php' ),
		'nonce'  => wp_create_nonce( 'gvwr' ),
		'today'  => gvwr_today(),
		'jalali' => gvwr_is_jalali(),
		'front'  => (bool) $front,
	) );
}

add_action( 'admin_enqueue_scripts', function () {
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
	if ( GVWR_MENU_SLUG === $page || 0 === strpos( $page, 'gv-wr-' ) ) {
		gvwr_enqueue_assets( false );
	}
} );

/* ------------------------------------------------------------------ */
/* منوی ادمین                                                          */
/* ------------------------------------------------------------------ */
function gvwr_menu_url( $page = '' ) {
	return admin_url( 'admin.php?page=' . ( $page ? $page : GVWR_MENU_SLUG ) );
}

function gvwr_render_page( $view, $title, $desc = '' ) {
	$status = isset( $_GET['gvwr_status'] ) ? sanitize_key( $_GET['gvwr_status'] ) : ''; // phpcs:ignore
	?>
	<div class="wrap gvwr-wrap">
		<div class="gvwr-app" dir="rtl">
			<div class="gvwr-pagehead">
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( $desc ) : ?><p><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</div>
			<div class="gvwr-view" data-view="<?php echo esc_attr( $view ); ?>" data-autoload="1" data-status="<?php echo esc_attr( $status ); ?>">
				<div class="gvwr-loading">در حال بارگذاری…</div>
			</div>
		</div>
		<div id="gvwr-toasts" class="gvwr-toasts" dir="rtl"></div>
	</div>
	<?php
}

add_action( 'admin_menu', 'gvwr_admin_menu' );
function gvwr_admin_menu() {
	if ( ! gvwr_can_submit() ) {
		return;
	}
	$mgr    = gvwr_can_manage();
	$n      = $mgr ? gvwr_pending_count() : gvwr_rejected_count( get_current_user_id() );
	$badge  = $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '';
	$page   = function ( $view, $title, $desc = '' ) {
		return function () use ( $view, $title, $desc ) {
			gvwr_render_page( $view, $title, $desc );
		};
	};
	$slug = GVWR_MENU_SLUG;

	if ( $mgr ) {
		add_menu_page( 'گزارش کار', 'گزارش کار' . $badge, 'gvwr_submit', $slug, $page( 'mgr_overview', 'داشبورد گزارش کار', 'نمای کلی عملکرد تیم' ), 'dashicons-chart-area', 58 );
		add_submenu_page( $slug, 'داشبورد', 'داشبورد', 'gvwr_manage', $slug, $page( 'mgr_overview', 'داشبورد گزارش کار', 'نمای کلی عملکرد تیم' ) );
		add_submenu_page( $slug, 'همه‌ی گزارش‌ها', 'همه‌ی گزارش‌ها' . $badge, 'gvwr_manage', 'gv-wr-reports', $page( 'mgr_reports', 'همه‌ی گزارش‌ها', 'بررسی، تأیید یا رد گزارش‌ها و خروجی' ) );
		add_submenu_page( $slug, 'مقایسه‌ی کارمندان', 'مقایسه و تحلیل', 'gvwr_manage', 'gv-wr-compare', $page( 'mgr_compare', 'مقایسه‌ی کارمندان', 'عملکرد کارمندان در کنار هم' ) );
		add_submenu_page( $slug, 'تقویم', 'تقویم فعالیت', 'gvwr_submit', 'gv-wr-cal', $page( 'calendar', 'تقویم فعالیت' ) );
		add_submenu_page( $slug, 'کلمات کلیدی', 'کلمات کلیدی و رتبه', 'gvwr_submit', 'gv-wr-kw', $page( 'keywords', 'کلمات کلیدی و رتبه', 'فهرست کلمات سایت و تاریخچه‌ی رتبه‌ها' ) );
		add_submenu_page( $slug, 'ثبت گزارش من', 'ثبت گزارش من', 'gvwr_submit', 'gv-wr-new', $page( 'emp_form', 'ثبت گزارش' ) );
		add_submenu_page( $slug, 'افراد', 'کارمندان و دسترسی', 'gvwr_manage', 'gv-wr-people', $page( 'people', 'کارمندان و دسترسی‌ها', 'کارمند، مدیر و کارفرما را به یوزر وردپرس وصل کنید' ) );
		add_submenu_page( $slug, 'دسته‌بندی‌ها', 'دسته‌بندی کارها', 'gvwr_manage', 'gv-wr-cats', $page( 'categories', 'دسته‌بندی کارها' ) );
		add_submenu_page( $slug, 'تنظیمات', 'تنظیمات', 'gvwr_manage', 'gv-wr-settings', $page( 'settings', 'تنظیمات گزارش کار' ) );
	} else {
		add_menu_page( 'گزارش کار', 'گزارش کار' . $badge, 'gvwr_submit', $slug, $page( 'emp_form', 'ثبت گزارش کار', 'گزارش کارهای انجام‌شده را سریع ثبت کنید' ), 'dashicons-edit-page', 58 );
		add_submenu_page( $slug, 'ثبت گزارش', 'ثبت گزارش', 'gvwr_submit', $slug, $page( 'emp_form', 'ثبت گزارش کار', 'گزارش کارهای انجام‌شده را سریع ثبت کنید' ) );
		add_submenu_page( $slug, 'گزارش‌های من', 'گزارش‌های من' . $badge, 'gvwr_submit', 'gv-wr-my', $page( 'my_reports', 'گزارش‌های من' ) );
		add_submenu_page( $slug, 'تقویم', 'تقویم من', 'gvwr_submit', 'gv-wr-cal', $page( 'calendar', 'تقویم فعالیت من' ) );
		add_submenu_page( $slug, 'کلمات کلیدی', 'کلمات کلیدی و رتبه', 'gvwr_submit', 'gv-wr-kw', $page( 'keywords', 'کلمات کلیدی و رتبه', 'رتبه‌ی جدید کلمات را ثبت کنید' ) );
	}
	if ( $mgr ) { // مدیر هم «گزارش‌های من» نیاز دارد
		add_submenu_page( $slug, 'گزارش‌های من', 'گزارش‌های من', 'gvwr_submit', 'gv-wr-my', $page( 'my_reports', 'گزارش‌های من' ) );
	}
}

/* ------------------------------------------------------------------ */
/* اعلان در نوار ادمین (گزارش جدید برای مدیر / گزارش ردشده برای کارمند)   */
/* ------------------------------------------------------------------ */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! is_user_logged_in() || ! gvwr_can_submit() ) {
		return;
	}
	$mgr = gvwr_can_manage();
	$n   = $mgr ? gvwr_pending_count() : gvwr_rejected_count( get_current_user_id() );
	if ( ! $n ) {
		return;
	}
	$bar->add_node( array(
		'id'    => 'gvwr-notice',
		'title' => '🔔 ' . ( $mgr ? gvwr_fa( $n ) . ' گزارش جدید' : gvwr_fa( $n ) . ' گزارش نیازمند اصلاح' ),
		'href'  => $mgr ? gvwr_menu_url( 'gv-wr-reports' ) . '&gvwr_status=pending' : gvwr_menu_url( 'gv-wr-my' ) . '&gvwr_status=rejected',
	) );
}, 90 );

/* ------------------------------------------------------------------ */
/* ریدایرکت بعد از ورود + بستن wp-admin برای کارفرما                    */
/* ------------------------------------------------------------------ */
add_filter( 'login_redirect', function ( $to, $req, $user ) {
	if ( ! gvwr_opt( 'login_redirect' ) || ! $user || is_wp_error( $user ) || ! $user->exists() ) {
		return $to;
	}
	$role = gvwr_user_role( $user );
	if ( in_array( $role, array( 'gvwr_employee', 'gvwr_manager' ), true ) ) {
		return gvwr_menu_url();
	}
	if ( 'gvwr_client' === $role ) {
		$pid = (int) gvwr_opt( 'client_page_id' );
		return $pid ? get_permalink( $pid ) : home_url( '/' );
	}
	return $to;
}, 20, 3 );

add_action( 'admin_init', function () {
	if ( gvwr_is_client_only() && ! wp_doing_ajax() ) {
		$pid = (int) gvwr_opt( 'client_page_id' );
		wp_safe_redirect( $pid ? get_permalink( $pid ) : home_url( '/' ) );
		exit;
	}
} );

add_filter( 'show_admin_bar', function ( $show ) {
	return gvwr_is_client_only() ? false : $show;
} );

/* ------------------------------------------------------------------ */
/* کارت داشبورد افزونه‌ی اصلی (Groot Vision)                            */
/* در admin.php داشبورد، تابع gvwr_dashboard_card() را صدا بزنید.          */
/* ------------------------------------------------------------------ */
function gvwr_dashboard_card( $echo = true ) {
	if ( ! gvwr_can_submit() ) {
		return '';
	}
	global $wpdb;
	$today = gvwr_today();
	$cnt   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . gvwr_table( 'items' ) . ' WHERE work_date=%s', $today ) );
	$pend  = gvwr_pending_count();
	$h     = '<a href="' . esc_url( gvwr_menu_url() ) . '" class="gvwr-hubcard" style="display:flex;gap:14px;align-items:center;padding:18px 20px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;text-decoration:none;color:#0f172a;direction:rtl">'
		. '<span style="font-size:30px;line-height:1">📋</span>'
		. '<span style="display:flex;flex-direction:column;gap:3px"><b style="font-size:15px">گزارش کار</b>'
		. '<small style="color:#64748b">امروز ' . esc_html( gvwr_fa( $cnt ) ) . ' گزارش' . ( gvwr_can_manage() && $pend ? ' · ' . esc_html( gvwr_fa( $pend ) ) . ' در انتظار تأیید' : '' ) . '</small></span></a>';
	if ( $echo ) {
		echo $h; // phpcs:ignore
		return '';
	}
	return $h;
}
