<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* زیرمنوی «سطح دسترسی» زیر منوی اصلی گروت ویژن (مثل بقیه‌ی ابزارها) */
add_action( 'admin_menu', 'gvac_register_menu', 20 );
function gvac_register_menu() {
	if ( ! defined( 'GV_HUB_SLUG' ) ) {
		return;
	}
	add_submenu_page(
		GV_HUB_SLUG,
		'مدیریت سطح دسترسی',
		'🛡️ سطح دسترسی',
		'gvac_manage',
		GVAC_MENU_SLUG,
		'gvac_render_page'
	);
}

add_action( 'admin_enqueue_scripts', function () {
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
	if ( GVAC_MENU_SLUG !== $page ) {
		return;
	}
	wp_enqueue_style( 'gvac', GVAC_URL . 'assets/css/ac.css', array(), GVAC_VERSION );
	wp_enqueue_script( 'gvac', GVAC_URL . 'assets/js/ac.js', array(), GVAC_VERSION, true );
	wp_localize_script( 'gvac', 'GVAC', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'gvac' ),
		'tab'   => isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard', // phpcs:ignore
		'self'  => GVAC_MENU_SLUG,
		'ver'   => GVAC_VERSION,
		'home'  => home_url( '/' ),
	) );
} );

function gvac_render_page() {
	if ( ! current_user_can( 'gvac_manage' ) ) {
		return;
	}
	?>
	<div class="wrap gvac-wrap">
		<div id="gvac-app" class="gvac-app" dir="rtl">
			<div class="gvac-boot">در حال بارگذاری پنل دسترسی…</div>
		</div>
		<div id="gvac-toasts" class="gvac-toasts" dir="rtl" aria-live="polite"></div>
		<noscript>برای استفاده از این پنل، جاوااسکریپت مرورگر باید فعال باشد.</noscript>
	</div>
	<?php
}

/** کارت داشبورد افزونه‌ی اصلی: در admin.php با gvac_dashboard_card() صدا بزنید. */
function gvac_dashboard_card( $echo = true ) {
	if ( ! current_user_can( 'gvac_manage' ) ) {
		return '';
	}
	$n  = count( gvac_roles_list() );
	$st = count( gvac_stealth_all() );
	$h  = '<a href="' . esc_url( gvac_menu_url() ) . '" class="gvac-hubcard" style="display:flex;gap:14px;align-items:center;padding:18px 20px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;text-decoration:none;color:#0f172a;direction:rtl">'
		. '<span style="font-size:30px;line-height:1">🛡️</span>'
		. '<span style="display:flex;flex-direction:column;gap:3px"><b style="font-size:15px">سطح دسترسی کاربران</b>'
		. '<small style="color:#64748b">' . esc_html( gvac_fa( $n ) ) . ' نقش' . ( $st ? ' · ' . esc_html( gvac_fa( $st ) ) . ' کاربر پنهان' : '' ) . '</small></span></a>';
	if ( $echo ) {
		echo $h; // phpcs:ignore
		return '';
	}
	return $h;
}
