<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — بازطراحی محیط پیشخوان وردپرس (Admin Theme)
 *  ------------------------------------------------------------
 *  پنل ساده‌ی پیش‌فرض وردپرس را به یک محیط شیک، مینیمال و حرفه‌ای
 *  تبدیل می‌کند: ۳ تم آماده (مینیمال روشن / تیره حرفه‌ای / بنفش مدرن)
 *  + امکان نمایش/عدم‌نمایش برخی گزینه‌های منوی پیشخوان و نوار بالا.
 *  هیچ داده‌ای دستکاری نمی‌شود؛ فقط ظاهر پیشخوان (CSS) و مخفی/نمایان
 *  بودن چند آیتم منو تغییر می‌کند.
 * ==========================================================
 */

define( 'GV_AT_OPT', 'gv_admin_theme_settings' );
define( 'GV_AT_PAGE_SLUG', 'gv-admin-theme' );
define( 'GV_AT_NONCE', 'gv_at_nonce_action' );

/* ==========================================================================
   ۱) تم‌های آماده
   ========================================================================== */
function gv_at_get_themes() {
	return array(
		'minimal' => array(
			'label' => 'مینیمال روشن',
			'desc'  => 'تمیز، روشن و بی‌حاشیه؛ مناسب کار روزمره',
			'swatch'=> array( '#ffffff', '#4f46e5', '#f4f5f7' ),
			'vars'  => array(
				'bg'                => '#f4f5f7',
				'sidebar-bg'        => '#ffffff',
				'sidebar-text'      => '#4b5566',
				'sidebar-active-bg' => '#eef2ff',
				'sidebar-active-tx' => '#4f46e5',
				'topbar-bg'         => '#ffffff',
				'topbar-text'       => '#3f4657',
				'accent'            => '#4f46e5',
				'accent-hover'      => '#4338ca',
				'border'            => '#e5e7eb',
				'radius'            => '10px',
				'shadow'            => '0 1px 3px rgba(17,24,39,.07)',
				'card-bg'           => '#ffffff',
				'text'              => '#1f2430',
				'text-soft'         => '#6b7280',
				'table-head'        => '#f9fafb',
			),
		),
		'dark' => array(
			'label' => 'تیره حرفه‌ای',
			'desc'  => 'محیط تیره و کم‌خستگی برای چشم، حس‌وحال حرفه‌ای',
			'swatch'=> array( '#12141a', '#3b82f6', '#181b22' ),
			'vars'  => array(
				'bg'                => '#12141a',
				'sidebar-bg'        => '#0d0f14',
				'sidebar-text'      => '#c6cad3',
				'sidebar-active-bg' => '#1f2430',
				'sidebar-active-tx' => '#60a5fa',
				'topbar-bg'         => '#0d0f14',
				'topbar-text'       => '#c6cad3',
				'accent'            => '#3b82f6',
				'accent-hover'      => '#2563eb',
				'border'            => '#242832',
				'radius'            => '10px',
				'shadow'            => '0 2px 8px rgba(0,0,0,.35)',
				'card-bg'           => '#181b22',
				'text'              => '#e5e7eb',
				'text-soft'         => '#9ca3af',
				'table-head'        => '#1c1f27',
			),
		),
		'violet' => array(
			'label' => 'بنفش مدرن',
			'desc'  => 'رنگی، گردتر و امروزی؛ مناسب برندهای خلاقانه',
			'swatch'=> array( '#1e1533', '#7c3aed', '#f7f5fc' ),
			'vars'  => array(
				'bg'                => '#f7f5fc',
				'sidebar-bg'        => '#1e1533',
				'sidebar-text'      => '#c9bfe8',
				'sidebar-active-bg' => '#7c3aed',
				'sidebar-active-tx' => '#ffffff',
				'topbar-bg'         => '#1e1533',
				'topbar-text'       => '#e4defa',
				'accent'            => '#7c3aed',
				'accent-hover'      => '#6d28d9',
				'border'            => '#e6defc',
				'radius'            => '14px',
				'shadow'            => '0 6px 18px rgba(124,58,237,.14)',
				'card-bg'           => '#ffffff',
				'text'              => '#241a3d',
				'text-soft'         => '#6b6180',
				'table-head'        => '#f1ecfc',
			),
		),
	);
}

/* ==========================================================================
   ۲) آیتم‌های قابل مخفی‌کردن — منوی اصلی پیشخوان
   ========================================================================== */
function gv_at_get_menu_toggles() {
	$items = array(
		'edit.php'                  => 'نوشته‌ها',
		'upload.php'                => 'رسانه',
		'edit.php?post_type=page'   => 'برگه‌ها',
		'edit-comments.php'         => 'دیدگاه‌ها',
		'themes.php'                => 'ظاهر (Appearance)',
		'plugins.php'               => 'افزونه‌ها',
		'users.php'                 => 'کاربران',
		'tools.php'                 => 'ابزارها',
		'options-general.php'       => 'تنظیمات',
	);
	if ( class_exists( 'WooCommerce' ) ) {
		$items['woocommerce']                   = 'ووکامرس';
		$items['edit.php?post_type=product']    = 'محصولات';
	}
	return $items;
}

/** آیتم‌های قابل مخفی‌کردن روی نوار بالای ادمین (Admin Bar) */
function gv_at_get_adminbar_toggles() {
	return array(
		'wp-logo'     => 'لوگوی وردپرس (گوشه راست)',
		'updates'     => 'آیکون به‌روزرسانی‌ها',
		'comments'    => 'آیکون دیدگاه‌ها',
		'new-content' => 'دکمه «جدید +»',
	);
}

/* ==========================================================================
   ۳) تنظیمات پیش‌فرض / خواندن تنظیمات ذخیره‌شده
   ========================================================================== */
function gv_at_default_settings() {
	return array(
		'enabled'          => 0,
		'theme'            => 'minimal',
		'hidden_menu'      => array(),
		'hidden_adminbar'  => array(),
	);
}

function gv_at_get_settings() {
	$saved = get_option( GV_AT_OPT, array() );
	if ( ! is_array( $saved ) ) { $saved = array(); }
	$settings = wp_parse_args( $saved, gv_at_default_settings() );
	if ( ! is_array( $settings['hidden_menu'] ) )     { $settings['hidden_menu'] = array(); }
	if ( ! is_array( $settings['hidden_adminbar'] ) ) { $settings['hidden_adminbar'] = array(); }
	if ( ! isset( gv_at_get_themes()[ $settings['theme'] ] ) ) { $settings['theme'] = 'minimal'; }
	return $settings;
}

/* ==========================================================================
   ۴) ثبت زیرمنوی تنظیمات (زیر منوی اصلی گروت ویژن)
   ========================================================================== */
add_action( 'admin_menu', 'gv_at_register_menu', 10 );
function gv_at_register_menu() {
	if ( ! defined( 'GV_HUB_SLUG' ) ) { return; }
	add_submenu_page(
		GV_HUB_SLUG,
		'تم محیط پیشخوان',
		'🎨 تم پیشخوان',
		'manage_options',
		GV_AT_PAGE_SLUG,
		'gv_at_render_page'
	);
}

/* ==========================================================================
   ۵) پردازش فرم ذخیره تنظیمات
   ========================================================================== */
function gv_at_handle_save() {
	if ( ! isset( $_POST['gv_at_save'] ) ) { return null; }
	if ( ! current_user_can( 'manage_options' ) ) { return null; }
	check_admin_referer( GV_AT_NONCE, 'gv_at_nonce' );

	$themes       = gv_at_get_themes();
	$menu_items   = gv_at_get_menu_toggles();
	$bar_items    = gv_at_get_adminbar_toggles();

	$theme = isset( $_POST['gvt_theme'] ) ? sanitize_key( wp_unslash( $_POST['gvt_theme'] ) ) : 'minimal';
	if ( ! isset( $themes[ $theme ] ) ) { $theme = 'minimal'; }

	$hidden_menu = array();
	if ( ! empty( $_POST['gvt_hidden_menu'] ) && is_array( $_POST['gvt_hidden_menu'] ) ) {
		foreach ( wp_unslash( $_POST['gvt_hidden_menu'] ) as $slug ) {
			if ( isset( $menu_items[ $slug ] ) ) { $hidden_menu[] = $slug; }
		}
	}

	$hidden_bar = array();
	if ( ! empty( $_POST['gvt_hidden_bar'] ) && is_array( $_POST['gvt_hidden_bar'] ) ) {
		foreach ( wp_unslash( $_POST['gvt_hidden_bar'] ) as $id ) {
			if ( isset( $bar_items[ $id ] ) ) { $hidden_bar[] = $id; }
		}
	}

	$settings = array(
		'enabled'         => isset( $_POST['gvt_enabled'] ) ? 1 : 0,
		'theme'           => $theme,
		'hidden_menu'     => $hidden_menu,
		'hidden_adminbar' => $hidden_bar,
	);

	update_option( GV_AT_OPT, $settings );
	return true;
}

/* ==========================================================================
   ۶) صفحه‌ی تنظیمات
   ========================================================================== */
function gv_at_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$saved_ok = gv_at_handle_save();
	$settings = gv_at_get_settings();
	$themes   = gv_at_get_themes();
	$menu_items = gv_at_get_menu_toggles();
	$bar_items  = gv_at_get_adminbar_toggles();
	?>
	<div class="wrap gv-at-wrap" dir="rtl" style="max-width:920px;">
		<h1 style="font-size:20px;">🎨 تم محیط پیشخوان</h1>
		<p style="color:#555;">ظاهر پیشخوان وردپرس را برای همه‌ی کاربران این سایت شیک‌تر و مینیمال‌تر کنید و تعیین کنید چه گزینه‌هایی از منو دیده شوند.</p>

		<?php if ( true === $saved_ok ) : ?>
			<div class="notice notice-success"><p>تنظیمات تم پیشخوان ذخیره شد.</p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( GV_AT_NONCE, 'gv_at_nonce' ); ?>

			<div style="background:#fff;border:1px solid #e2e2e2;border-radius:12px;padding:18px 22px;margin-bottom:16px;">
				<label style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:14px;">
					<input type="checkbox" name="gvt_enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?> />
					فعال‌سازی تم اختصاصی پیشخوان
				</label>
				<p style="color:#777;font-size:12.5px;margin:6px 0 0;">در صورت خاموش بودن، پیشخوان به ظاهر پیش‌فرض وردپرس برمی‌گردد.</p>
			</div>

			<div style="background:#fff;border:1px solid #e2e2e2;border-radius:12px;padding:18px 22px;margin-bottom:16px;">
				<h2 style="font-size:15px;margin-top:0;">انتخاب تم</h2>
				<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-top:12px;">
					<?php foreach ( $themes as $key => $t ) : ?>
						<label style="cursor:pointer;border:2px solid <?php echo ( $settings['theme'] === $key ) ? '#4f46e5' : '#e5e7eb'; ?>;border-radius:12px;padding:14px;display:block;">
							<input type="radio" name="gvt_theme" value="<?php echo esc_attr( $key ); ?>" <?php checked( $settings['theme'], $key ); ?> style="margin-inline-end:6px;" />
							<strong style="font-size:13px;"><?php echo esc_html( $t['label'] ); ?></strong>
							<div style="display:flex;gap:4px;margin:10px 0 8px;">
								<?php foreach ( $t['swatch'] as $c ) : ?>
									<span style="width:26px;height:26px;border-radius:50%;background:<?php echo esc_attr( $c ); ?>;border:1px solid rgba(0,0,0,.08);display:inline-block;"></span>
								<?php endforeach; ?>
							</div>
							<span style="font-size:11.5px;color:#777;"><?php echo esc_html( $t['desc'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div style="background:#fff;border:1px solid #e2e2e2;border-radius:12px;padding:18px 22px;margin-bottom:16px;">
				<h2 style="font-size:15px;margin-top:0;">نمایش/عدم‌نمایش گزینه‌های منو</h2>
				<p style="color:#777;font-size:12.5px;">تیک هر گزینه یعنی از منوی پیشخوان مخفی شود (فقط ظاهری است؛ دسترسی و داده‌ها حذف نمی‌شود).</p>
				<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:10px;">
					<?php foreach ( $menu_items as $slug => $label ) : ?>
						<label style="display:flex;align-items:center;gap:6px;font-size:13px;">
							<input type="checkbox" name="gvt_hidden_menu[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $settings['hidden_menu'], true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div style="background:#fff;border:1px solid #e2e2e2;border-radius:12px;padding:18px 22px;margin-bottom:16px;">
				<h2 style="font-size:15px;margin-top:0;">نمایش/عدم‌نمایش نوار بالای پیشخوان</h2>
				<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:10px;">
					<?php foreach ( $bar_items as $id => $label ) : ?>
						<label style="display:flex;align-items:center;gap:6px;font-size:13px;">
							<input type="checkbox" name="gvt_hidden_bar[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $settings['hidden_adminbar'], true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<p><button type="submit" name="gv_at_save" value="1" class="button button-primary">ذخیره تنظیمات</button></p>
		</form>
	</div>
	<?php
}

/* ==========================================================================
   ۷) مخفی‌کردن آیتم‌های منوی انتخاب‌شده (واقعی، نه فقط بصری —
      چون این آیتم‌ها بخش پیش‌فرض خود وردپرس هستند و مشکل parent_file
      مثل زیرمنوهای خود افزونه اینجا وجود ندارد)
   ========================================================================== */
add_action( 'admin_menu', 'gv_at_apply_menu_visibility', 999 );
function gv_at_apply_menu_visibility() {
	$settings = gv_at_get_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['hidden_menu'] ) ) { return; }

	foreach ( $settings['hidden_menu'] as $slug ) {
		remove_menu_page( $slug );
	}
}

/* ==========================================================================
   ۸) خروجی CSS تم انتخابی + مخفی‌کردن آیتم‌های نوار بالا
   ========================================================================== */
add_action( 'admin_head', 'gv_at_output_css' );
function gv_at_output_css() {
	$settings = gv_at_get_settings();
	if ( empty( $settings['enabled'] ) ) { return; }

	$themes = gv_at_get_themes();
	$vars   = $themes[ $settings['theme'] ]['vars'];
	?>
	<style id="gv-admin-theme-css">
	:root{
		<?php foreach ( $vars as $name => $val ) : ?>
		--gvt-<?php echo esc_html( $name ); ?>: <?php echo esc_html( $val ); ?>;
		<?php endforeach; ?>
	}

	/* ---- پس‌زمینه کلی ---- */
	body.wp-admin, #wpwrap, #wpbody, #wpbody-content{ background:var(--gvt-bg) !important; }
	body.wp-admin{ color:var(--gvt-text); }
	body.wp-admin *{ box-sizing:border-box; }

	/* ---- منوی کناری ---- */
	#adminmenumain, #adminmenuback, #adminmenuwrap, #adminmenu, #adminmenu .wp-submenu{
		background:var(--gvt-sidebar-bg) !important; border-inline-end:1px solid var(--gvt-border);
	}
	#adminmenu a{ color:var(--gvt-sidebar-text) !important; }
	#adminmenu div.wp-menu-image:before{ color:var(--gvt-sidebar-text) !important; opacity:.85; }
	#adminmenu li.menu-top{ margin:2px 8px; border-radius:8px; overflow:hidden; }
	#adminmenu li.menu-top:hover, #adminmenu li.opensub > a.menu-top, #adminmenu li > a.menu-top:focus{
		background:var(--gvt-sidebar-active-bg) !important;
	}
	#adminmenu li.wp-has-current-submenu > a.menu-top,
	#adminmenu li.current > a.menu-top,
	#adminmenu li.wp-has-current-submenu.opensub > a.menu-top{
		background:var(--gvt-sidebar-active-bg) !important; border-radius:8px;
	}
	#adminmenu li.wp-has-current-submenu > a.menu-top div.wp-menu-image:before,
	#adminmenu li.current > a.menu-top div.wp-menu-image:before,
	#adminmenu li.wp-has-current-submenu > a.menu-top,
	#adminmenu li.current > a.menu-top{
		color:var(--gvt-sidebar-active-tx) !important;
	}
	#adminmenu .wp-submenu{ border-radius:8px; box-shadow:var(--gvt-shadow); }
	#adminmenu .wp-submenu a{ color:var(--gvt-sidebar-text) !important; }
	#adminmenu .wp-submenu a:hover, #adminmenu .wp-submenu li.current a{ color:var(--gvt-sidebar-active-tx) !important; }
	#collapse-menu{ color:var(--gvt-sidebar-text) !important; }
	#adminmenu li.wp-menu-separator{ background:transparent; }

	/* ---- نوار بالای پیشخوان ---- */
	#wpadminbar{ background:var(--gvt-topbar-bg) !important; }
	#wpadminbar .ab-item, #wpadminbar a.ab-item, #wpadminbar > #wp-toolbar span.ab-label, #wpadminbar > #wp-toolbar span.noticon{
		color:var(--gvt-topbar-text) !important;
	}
	#wpadminbar .ab-top-menu > li:hover > .ab-item, #wpadminbar.nojq .quicklinks .ab-top-menu > li > .ab-item:focus{
		background:rgba(127,127,127,.12) !important;
	}

	/* ---- کارت‌ها / پست‌باکس‌ها ---- */
	.postbox, .stuffbox{
		background:var(--gvt-card-bg) !important; border:1px solid var(--gvt-border) !important;
		border-radius:var(--gvt-radius) !important; box-shadow:var(--gvt-shadow) !important;
	}
	.postbox .postbox-header{ border-bottom:1px solid var(--gvt-border); }
	.postbox .hndle, .postbox h2.hndle{ color:var(--gvt-text); }

	/* ---- عنوان صفحات ---- */
	.wrap h1, .wrap h1.wp-heading-inline{ color:var(--gvt-text); font-weight:800; }
	.wrap{ color:var(--gvt-text); }

	/* ---- دکمه‌ها ---- */
	.button, .button-secondary{
		border-radius:8px !important; border-color:var(--gvt-border) !important; box-shadow:none !important;
	}
	.button-primary{
		background:var(--gvt-accent) !important; border-color:var(--gvt-accent) !important;
		border-radius:8px !important; box-shadow:none !important; text-shadow:none !important;
	}
	.button-primary:hover, .button-primary:focus{ background:var(--gvt-accent-hover) !important; border-color:var(--gvt-accent-hover) !important; }

	/* ---- جدول‌ها ---- */
	table.wp-list-table{ border-radius:var(--gvt-radius); overflow:hidden; border:1px solid var(--gvt-border); background:var(--gvt-card-bg); }
	table.wp-list-table thead th, table.wp-list-table thead td{ background:var(--gvt-table-head); color:var(--gvt-text); }
	table.wp-list-table tbody tr:hover{ background:var(--gvt-sidebar-active-bg); }

	/* ---- فیلدهای فرم ---- */
	input[type=text], input[type=search], input[type=password], input[type=email], input[type=url],
	input[type=number], select, textarea{
		border-radius:8px !important; border-color:var(--gvt-border) !important; background:var(--gvt-card-bg) !important; color:var(--gvt-text) !important;
	}
	input:focus, select:focus, textarea:focus{ border-color:var(--gvt-accent) !important; box-shadow:0 0 0 1px var(--gvt-accent) !important; }

	/* ---- نوتیس‌ها ---- */
	.notice, div.updated, div.error{
		border-radius:8px; border-inline-start-width:4px; box-shadow:var(--gvt-shadow); background:var(--gvt-card-bg);
	}

	/* ---- فوتر پیشخوان ---- */
	#wpfooter{ color:var(--gvt-text-soft); }

	<?php if ( ! empty( $settings['hidden_adminbar'] ) ) : ?>
	/* ---- مخفی‌کردن آیتم‌های انتخابی نوار بالا ---- */
	<?php foreach ( $settings['hidden_adminbar'] as $id ) : ?>
	#wp-admin-bar-<?php echo esc_html( $id ); ?>{ display:none !important; }
	<?php endforeach; ?>
	<?php endif; ?>
	</style>
	<?php
}
