<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — بازطراحی محیط پیشخوان وردپرس (Admin Theme)
 *  ------------------------------------------------------------
 *  پنل ساده‌ی پیش‌فرض وردپرس را به یک محیط شیک، مینیمال و حرفه‌ای
 *  تبدیل می‌کند: تم‌های آماده + امکان نمایش/عدم‌نمایش برخی گزینه‌های
 *  منوی پیشخوان و نوار بالا.
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
				'sidebar-text'      => '#3f4657',
				'sidebar-active-bg' => '#eef2ff',
				'sidebar-active-tx' => '#4338ca',
				'topbar-bg'         => '#ffffff',
				'topbar-text'       => '#2b3140',
				'accent'            => '#4f46e5',
				'accent-hover'      => '#4338ca',
				'border'            => '#e5e7eb',
				'radius'            => '10px',
				'shadow'            => '0 1px 3px rgba(17,24,39,.07)',
				'card-bg'           => '#ffffff',
				'text'              => '#1f2430',
				'text-soft'         => '#565f70',
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
				'sidebar-text'      => '#d3d7e0',
				'sidebar-active-bg' => '#1f2430',
				'sidebar-active-tx' => '#7cb0fb',
				'topbar-bg'         => '#0d0f14',
				'topbar-text'       => '#d3d7e0',
				'accent'            => '#2563eb',
				'accent-hover'      => '#1d4ed8',
				'border'            => '#2a2f3b',
				'radius'            => '10px',
				'shadow'            => '0 2px 8px rgba(0,0,0,.35)',
				'card-bg'           => '#181b22',
				'text'              => '#f1f2f5',
				'text-soft'         => '#aeb4c2',
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
				'sidebar-text'      => '#d9d0f0',
				'sidebar-active-bg' => '#7c3aed',
				'sidebar-active-tx' => '#ffffff',
				'topbar-bg'         => '#1e1533',
				'topbar-text'       => '#ece7fa',
				'accent'            => '#7c3aed',
				'accent-hover'      => '#6d28d9',
				'border'            => '#e0d6fa',
				'radius'            => '14px',
				'shadow'            => '0 6px 18px rgba(124,58,237,.14)',
				'card-bg'           => '#ffffff',
				'text'              => '#241a3d',
				'text-soft'         => '#5c5273',
				'table-head'        => '#f1ecfc',
			),
		),
		'sweetpop' => array(
			'label' => 'صورتی شیرین (دخترونه)',
			'desc'  => 'پاستیلی، گرد و پر از قلب و پاپیون؛ حس‌وحال دخترونه و بامزه',
			'swatch'=> array( '#fff0f6', '#ff5c9d', '#ffffff' ),
			'pattern' => 'sweetpop',
			'vars'  => array(
				'bg'                => '#fff0f6',
				'sidebar-bg'        => '#ffffff',
				'sidebar-text'      => '#8a3f61',
				'sidebar-active-bg' => '#ffd6e8',
				'sidebar-active-tx' => '#d6316f',
				'topbar-bg'         => '#ff8fbe',
				'topbar-text'       => '#4a1329',
				'accent'            => '#c2255c',
				'accent-hover'      => '#d6316f',
				'border'            => '#ffd0e4',
				'radius'            => '18px',
				'shadow'            => '0 6px 18px rgba(255,92,157,.18)',
				'card-bg'           => '#ffffff',
				'text'              => '#4a1329',
				'text-soft'         => '#8a4a63',
				'table-head'        => '#ffeaf3',
			),
		),
		'nova' => array(
			'label' => 'نووا (پسرونه)',
			'desc'  => 'تیره، پرانرژی و فضایی؛ با آذرخش و ستاره و حس گیمینگ',
			'swatch'=> array( '#0a0e1a', '#22e0ff', '#101728' ),
			'pattern' => 'nova',
			'vars'  => array(
				'bg'                => '#0a0e1a',
				'sidebar-bg'        => '#0c1120',
				'sidebar-text'      => '#a9c3e6',
				'sidebar-active-bg' => '#132038',
				'sidebar-active-tx' => '#22e0ff',
				'topbar-bg'         => '#0c1120',
				'topbar-text'       => '#c3d6f0',
				'accent'            => '#0e7490',
				'accent-hover'      => '#0b5b70',
				'border'            => '#1c2740',
				'radius'            => '10px',
				'shadow'            => '0 0 0 1px rgba(34,224,255,.08), 0 4px 18px rgba(0,0,0,.5)',
				'card-bg'           => '#101728',
				'text'              => '#e9f1fb',
				'text-soft'         => '#9db2cf',
				'table-head'        => '#151d31',
			),
		),
	);
}

/* ==========================================================================
   ۲) آیتم‌های قابل مخفی‌کردن — منوی اصلی پیشخوان
   ------------------------------------------------------------
   این لیست به‌صورت پویا از global $menu وردپرس ساخته می‌شود؛ یعنی
   هر آیتم منوی سطح‌بالایی که در این سایت ثبت شده (اصلی وردپرس،
   ووکامرس، یا هر افزونه‌ی دیگر) به‌طور خودکار شناسایی و به لیست
   نمایش/عدم‌نمایش اضافه می‌شود.
   ========================================================================== */
function gv_at_get_menu_toggles() {
	global $menu;

	$items = array();
	if ( empty( $menu ) || ! is_array( $menu ) ) {
		return $items;
	}

	foreach ( $menu as $item ) {
		if ( empty( $item[2] ) ) { continue; }
		$slug = $item[2];

		// جداکننده‌های بصری منو را رد کن
		if ( 0 === strpos( $slug, 'separator' ) ) { continue; }

		// منوی اصلی خودِ Groot Vision را از لیست مخفی‌سازی کنار بگذار
		// تا کاربر امکان دسترسی مجدد به تنظیمات را از دست ندهد
		if ( defined( 'GV_HUB_SLUG' ) && $slug === GV_HUB_SLUG ) { continue; }

		$label = isset( $item[0] ) ? $item[0] : $slug;
		$label = wp_strip_all_tags( $label );
		$label = preg_replace( '/\s*\(\d+\)\s*$/', '', $label ); // حذف اعداد نوتیفیکیشن انتهای عنوان
		$label = trim( $label );
		if ( '' === $label ) { $label = $slug; }

		$items[ $slug ] = $label;
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

/** توضیح کوتاه برای آیتم‌های شناخته‌شده‌ی هسته‌ی وردپرس؛ برای بقیه توضیح عمومی ساخته می‌شود */
function gv_at_get_menu_descriptions() {
	return array(
		'index.php'                => 'نمای کلی و آمار سریع سایت شما',
		'edit.php'                 => 'مدیریت، ویرایش و انتشار نوشته‌های وبلاگ',
		'upload.php'               => 'کتابخانه‌ی تصاویر، ویدیو و فایل‌های رسانه‌ای',
		'edit.php?post_type=page'  => 'ساخت و ویرایش برگه‌های ثابت سایت',
		'edit-comments.php'        => 'بررسی، تأیید یا حذف دیدگاه‌های کاربران',
		'themes.php'               => 'قالب، ظاهر و شخصی‌سازی نمای سایت',
		'plugins.php'              => 'نصب، فعال یا غیرفعال‌سازی افزونه‌ها',
		'users.php'                => 'مدیریت کاربران، نقش‌ها و دسترسی‌ها',
		'tools.php'                => 'ابزارهای درون‌ریزی، برون‌بری و نگهداری سایت',
		'options-general.php'      => 'تنظیمات کلی، زبان و آدرس سایت',
		'woocommerce'              => 'مدیریت فروشگاه، سفارش‌ها و گزارش‌های ووکامرس',
		'edit.php?post_type=product' => 'افزودن و ویرایش محصولات فروشگاه',
	);
}

/**
 * لیست کامل و غنی‌شده‌ی آیتم‌های منوی اصلی پیشخوان برای نمایش
 * به‌صورت کارت (آیکون + عنوان + توضیح + تعداد زیرمنو) در بخش دسترسی سریع.
 */
function gv_at_get_menu_quick_access() {
	global $menu, $submenu;

	$descriptions = gv_at_get_menu_descriptions();
	$list = array();

	if ( empty( $menu ) || ! is_array( $menu ) ) { return $list; }

	foreach ( $menu as $item ) {
		if ( empty( $item[2] ) ) { continue; }
		$slug = $item[2];
		if ( 0 === strpos( $slug, 'separator' ) ) { continue; }
		if ( defined( 'GV_HUB_SLUG' ) && $slug === GV_HUB_SLUG ) { continue; }

		$label = wp_strip_all_tags( isset( $item[0] ) ? $item[0] : $slug );
		$label = trim( preg_replace( '/\s*\(\d+\)\s*$/', '', $label ) );
		if ( '' === $label ) { $label = $slug; }

		$icon_raw = isset( $item[6] ) ? $item[6] : '';
		$icon = array( 'type' => 'fallback', 'value' => '' );
		if ( 0 === strpos( $icon_raw, 'dashicons-' ) ) {
			$icon = array( 'type' => 'dashicon', 'value' => $icon_raw );
		} elseif ( 0 === strpos( $icon_raw, 'data:image' ) || 0 === strpos( $icon_raw, 'http' ) ) {
			$icon = array( 'type' => 'image', 'value' => $icon_raw );
		} elseif ( 'div' === $icon_raw && 'index.php' === $slug ) {
			$icon = array( 'type' => 'dashicon', 'value' => 'dashicons-dashboard' );
		}

		$href = ( false !== strpos( $slug, '.php' ) )
			? admin_url( $slug )
			: admin_url( 'admin.php?page=' . $slug );

		$sub_count = 0;
		if ( isset( $submenu[ $slug ] ) && is_array( $submenu[ $slug ] ) ) {
			$sub_count = count( $submenu[ $slug ] );
		}

		$desc = isset( $descriptions[ $slug ] ) ? $descriptions[ $slug ] : 'دسترسی سریع به بخش ' . $label;

		$list[] = array(
			'slug'      => $slug,
			'label'     => $label,
			'desc'      => $desc,
			'icon'      => $icon,
			'href'      => $href,
			'sub_count' => $sub_count,
		);
	}

	return $list;
}


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

	$saved_ok   = gv_at_handle_save();
	$settings   = gv_at_get_settings();
	$themes     = gv_at_get_themes();
	$menu_items = gv_at_get_menu_toggles();
	$bar_items  = gv_at_get_adminbar_toggles();
	?>
	<div class="gvat-wrap wrap" dir="rtl">
		<div class="gvat-header">
			<div class="gvat-header-icon">🎨</div>
			<div>
				<h1>تم محیط پیشخوان</h1>
				<p>ظاهر پیشخوان وردپرس را برای همه‌ی کاربران این سایت شیک‌تر و مینیمال‌تر کنید و تعیین کنید چه گزینه‌هایی از منو دیده شوند.</p>
			</div>
		</div>

		<?php if ( true === $saved_ok ) : ?>
			<div class="gvat-toast">✅ تنظیمات تم پیشخوان ذخیره شد.</div>
		<?php endif; ?>

		<div class="gvat-toast" style="background:#eef2ff;border-color:#c7d2fe;color:#3730a3;">
			💡 کارت‌های «دسترسی سریع به بخش‌های پیشخوان» را همین حالا در پیشخوان اصلی وردپرس (صفحه‌ی نخست) هم می‌بینید.
		</div>

		<form method="post" class="gvat-form">
			<?php wp_nonce_field( GV_AT_NONCE, 'gv_at_nonce' ); ?>

			<section class="gvat-card gvat-toggle-card">
				<label class="gvat-switch-row">
					<span class="gvat-switch">
						<input type="checkbox" name="gvt_enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?> />
						<span class="gvat-switch-track"><span class="gvat-switch-thumb"></span></span>
					</span>
					<span class="gvat-switch-label">
						<strong>فعال‌سازی تم اختصاصی پیشخوان</strong>
						<small>در صورت خاموش بودن، پیشخوان به ظاهر پیش‌فرض وردپرس برمی‌گردد.</small>
					</span>
				</label>
			</section>

			<section class="gvat-card">
				<h2 class="gvat-card-title">انتخاب تم</h2>
				<div class="gvat-theme-grid">
					<?php foreach ( $themes as $key => $t ) :
						$is_active = ( $settings['theme'] === $key );
						$pattern   = isset( $t['pattern'] ) ? $t['pattern'] : '';
						?>
						<label class="gvat-theme-card gvat-pattern-<?php echo esc_attr( $pattern ? $pattern : 'none' ); ?> <?php echo $is_active ? 'is-active' : ''; ?>">
							<input type="radio" name="gvt_theme" value="<?php echo esc_attr( $key ); ?>" <?php checked( $settings['theme'], $key ); ?> />
							<span class="gvat-theme-preview" style="background:<?php echo esc_attr( $t['vars']['bg'] ); ?>;">
								<span class="gvat-theme-preview-bar" style="background:<?php echo esc_attr( $t['vars']['sidebar-bg'] ); ?>;"></span>
								<span class="gvat-theme-preview-body">
									<span class="gvat-dot" style="background:<?php echo esc_attr( $t['vars']['accent'] ); ?>;"></span>
									<span class="gvat-line" style="background:<?php echo esc_attr( $t['vars']['border'] ); ?>;"></span>
									<span class="gvat-line short" style="background:<?php echo esc_attr( $t['vars']['border'] ); ?>;"></span>
								</span>
								<?php if ( 'sweetpop' === $pattern ) : ?>
									<span class="gvat-emoji-float">🎀💗✨</span>
								<?php elseif ( 'nova' === $pattern ) : ?>
									<span class="gvat-emoji-float">⚡🚀✦</span>
								<?php endif; ?>
							</span>
							<span class="gvat-theme-meta">
								<strong><?php echo esc_html( $t['label'] ); ?></strong>
								<span class="gvat-swatches">
									<?php foreach ( $t['swatch'] as $c ) : ?>
										<i style="background:<?php echo esc_attr( $c ); ?>;"></i>
									<?php endforeach; ?>
								</span>
								<small><?php echo esc_html( $t['desc'] ); ?></small>
							</span>
							<span class="gvat-check">✓</span>
						</label>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="gvat-card">
				<h2 class="gvat-card-title">نمایش / عدم‌نمایش گزینه‌های منو</h2>
				<p class="gvat-card-sub">تیک هر گزینه یعنی از منوی پیشخوان مخفی شود (فقط ظاهری است؛ دسترسی و داده‌ها حذف نمی‌شود).</p>
				<div class="gvat-check-grid">
					<?php foreach ( $menu_items as $slug => $label ) : ?>
						<label class="gvat-check-pill">
							<input type="checkbox" name="gvt_hidden_menu[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $settings['hidden_menu'], true ) ); ?> />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="gvat-card">
				<h2 class="gvat-card-title">نمایش / عدم‌نمایش نوار بالای پیشخوان</h2>
				<div class="gvat-check-grid">
					<?php foreach ( $bar_items as $id => $label ) : ?>
						<label class="gvat-check-pill">
							<input type="checkbox" name="gvt_hidden_bar[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $settings['hidden_adminbar'], true ) ); ?> />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</section>

			<div class="gvat-save-bar">
				<button type="submit" name="gv_at_save" value="1" class="button button-primary gvat-save-btn">ذخیره تنظیمات</button>
			</div>
		</form>
	</div>

	<style>
	.gvat-wrap{ max-width:960px; }
	.gvat-wrap *{ box-sizing:border-box; }
	.gvat-header{ display:flex; align-items:flex-start; gap:14px; margin:18px 0 22px; }
	.gvat-header-icon{ font-size:30px; line-height:1; background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:10px 12px; box-shadow:0 1px 3px rgba(17,24,39,.06); }
	.gvat-header h1{ font-size:21px; margin:0 0 4px; padding:0; }
	.gvat-header p{ margin:0; color:#6b7280; font-size:13px; max-width:560px; }

	.gvat-toast{ background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px; }

	.gvat-card{ background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:20px 22px; margin-bottom:16px; box-shadow:0 1px 2px rgba(17,24,39,.04); }
	.gvat-card-title{ font-size:14.5px; margin:0 0 4px; padding:0; font-weight:700; }
	.gvat-card-sub{ color:#6b7280; font-size:12.5px; margin:0 0 12px; }

	.gvat-switch-row{ display:flex; align-items:center; gap:12px; cursor:pointer; }
	.gvat-switch{ position:relative; display:inline-block; }
	.gvat-switch input{ position:absolute; opacity:0; width:0; height:0; }
	.gvat-switch-track{ width:42px; height:24px; background:#d1d5db; border-radius:999px; display:block; position:relative; transition:background .15s ease; }
	.gvat-switch-thumb{ width:18px; height:18px; background:#fff; border-radius:50%; position:absolute; top:3px; right:3px; transition:right .15s ease; box-shadow:0 1px 2px rgba(0,0,0,.25); }
	.gvat-switch input:checked + .gvat-switch-track{ background:#4f46e5; }
	.gvat-switch input:checked + .gvat-switch-track .gvat-switch-thumb{ right:21px; }
	.gvat-switch-label strong{ display:block; font-size:14px; }
	.gvat-switch-label small{ display:block; color:#6b7280; font-size:12px; margin-top:2px; }

	.gvat-theme-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:16px; margin-top:6px; }
	.gvat-theme-card{ position:relative; display:block; cursor:pointer; border:2px solid #e5e7eb; border-radius:16px; overflow:hidden; background:#fafafa; transition:border-color .15s ease, transform .15s ease; }
	.gvat-theme-card:hover{ transform:translateY(-2px); }
	.gvat-theme-card input{ position:absolute; opacity:0; width:0; height:0; }
	.gvat-theme-card.is-active{ border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,.12); }
	.gvat-theme-preview{ height:96px; position:relative; display:flex; }
	.gvat-theme-preview-bar{ width:28%; height:100%; }
	.gvat-theme-preview-body{ flex:1; padding:12px; display:flex; flex-direction:column; gap:6px; }
	.gvat-dot{ width:14px; height:14px; border-radius:50%; }
	.gvat-line{ height:6px; border-radius:4px; width:80%; }
	.gvat-line.short{ width:50%; }
	.gvat-emoji-float{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:15px; letter-spacing:6px; opacity:.5; pointer-events:none; }
	.gvat-theme-meta{ display:block; padding:12px 14px 14px; }
	.gvat-theme-meta strong{ display:block; font-size:13px; margin-bottom:6px; }
	.gvat-swatches{ display:flex; gap:4px; margin-bottom:6px; }
	.gvat-swatches i{ width:18px; height:18px; border-radius:50%; display:inline-block; border:1px solid rgba(0,0,0,.08); }
	.gvat-theme-meta small{ color:#777; font-size:11px; display:block; }
	.gvat-check{ position:absolute; top:10px; left:10px; width:22px; height:22px; border-radius:50%; background:#4f46e5; color:#fff; font-size:12px; display:flex; align-items:center; justify-content:center; opacity:0; transform:scale(.6); transition:all .15s ease; pointer-events:none; z-index:2; }
	.gvat-theme-card.is-active .gvat-check,
	.gvat-theme-card input:checked ~ .gvat-check{ opacity:1; transform:scale(1); }
	.gvat-theme-card:has(input:checked){ border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,.12); }

	.gvat-check-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:10px; }
	.gvat-check-pill{ display:flex; align-items:center; gap:8px; font-size:13px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:9px 12px; cursor:pointer; transition:border-color .15s ease, background .15s ease; }
	.gvat-check-pill:hover{ border-color:#c7d2fe; background:#f5f6ff; }
	.gvat-check-pill input{ accent-color:#4f46e5; width:15px; height:15px; }

	.gvat-save-bar{ position:sticky; bottom:0; padding:14px 0 4px; display:flex; justify-content:flex-end; background:linear-gradient(to top,#f0f0f1 60%,transparent); }
	.gvat-save-btn{ border-radius:10px !important; padding:6px 22px !important; height:auto !important; font-size:13.5px !important; }
	</style>

	<script>
	(function(){
		var cards = document.querySelectorAll('.gvat-theme-card');
		cards.forEach(function(card){
			var radio = card.querySelector('input[type="radio"]');
			if ( ! radio ) { return; }
			radio.addEventListener('change', function(){
				cards.forEach(function(c){ c.classList.remove('is-active'); });
				card.classList.add('is-active');
			});
		});
	})();
	</script>
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

	$themes  = gv_at_get_themes();
	$theme   = $themes[ $settings['theme'] ];
	$vars    = $theme['vars'];
	$pattern = isset( $theme['pattern'] ) ? $theme['pattern'] : '';
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
	#adminmenumain, #adminmenuback, #adminmenuwrap, #adminmenu{
		background:var(--gvt-sidebar-bg) !important; border-inline-end:1px solid var(--gvt-border);
	}
	#adminmenu a{ color:var(--gvt-sidebar-text) !important; }
	#adminmenu div.wp-menu-image:before{ color:var(--gvt-sidebar-text) !important; opacity:.9; }

	/* شمارنده‌های اعلان (دایره‌ی قرمز) همیشه سفید-روی-قرمز بمانند، مستقل از رنگ متن تم */
	#adminmenu .update-plugins .update-count,
	#adminmenu .update-plugins .plugin-count,
	#adminmenu .awaiting-mod,
	#adminmenu .update-plugins{
		color:#ffffff !important;
	}

	/* پیل گرد دور هر آیتم — روی خودِ لینک اعمال می‌شود، نه روی li، تا مگامنوی هاور کلیپ نشود */
	#adminmenu li.menu-top{ margin:2px 8px; }
	#adminmenu li.menu-top > a.menu-top{ border-radius:8px; transition:background .12s ease; }
	#adminmenu li.menu-top:hover > a.menu-top,
	#adminmenu li.opensub > a.menu-top,
	#adminmenu li > a.menu-top:focus{
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

	/* ---- زیرمنوی شناور (مگامنو) — همان که با هاور روی هر آیتم باز می‌شود ---- */
	#adminmenu .wp-submenu{
		background:var(--gvt-card-bg) !important;
		border:1px solid var(--gvt-border) !important;
		border-radius:12px !important;
		box-shadow:0 10px 30px rgba(0,0,0,.16), var(--gvt-shadow) !important;
		padding:8px !important;
		min-width:200px;
		max-width:260px;
	}
	#adminmenu .wp-submenu:before{ display:none; } /* حذف فلش پیش‌فرض که با کارت گرد هماهنگ نیست */
	#adminmenu .wp-submenu .wp-submenu-head{
		color:var(--gvt-text) !important; font-weight:700; opacity:.9;
		padding:6px 10px 8px !important; border-bottom:1px solid var(--gvt-border); margin-bottom:4px;
	}
	#adminmenu .wp-submenu li{ margin:0; }
	#adminmenu .wp-submenu a{
		color:var(--gvt-text-soft) !important;
		border-radius:7px !important;
		padding:7px 10px !important;
		transition:background .12s ease, color .12s ease, padding-inline-start .12s ease;
	}
	#adminmenu .wp-submenu a:hover,
	#adminmenu .wp-submenu a:focus{
		background:var(--gvt-sidebar-active-bg) !important;
		color:var(--gvt-sidebar-active-tx) !important;
		padding-inline-start:14px !important;
	}
	#adminmenu .wp-submenu li.current a,
	#adminmenu .wp-submenu li.current a:hover{
		color:var(--gvt-accent) !important; font-weight:600;
	}

	/* زیرمنوی بخشِ فعال/جاری وقتی سایدبار جمع (folded/آیکونی) نیست: باید داخل سایدبار
	   و در جریان عادی صفحه بمونه، نه به‌صورت کارت شناور روی محتوای اصلی */
	body:not(.folded) #adminmenu li.wp-has-current-submenu > .wp-submenu{
		position:relative !important;
		inset-inline-start:0 !important;
		right:auto !important; left:auto !important;
		top:auto !important;
		width:auto !important;
		min-width:0 !important;
		max-width:none !important;
		margin:2px 8px 6px !important;
		box-shadow:none !important;
	}
	#collapse-menu{ color:var(--gvt-sidebar-text) !important; }
	#adminmenu li.wp-menu-separator{ background:transparent; }

	/* ---- نوار بالای پیشخوان ---- */
	#wpadminbar{ background:var(--gvt-topbar-bg) !important; }
	#wpadminbar .ab-top-menu > li > .ab-item, #wpadminbar > #wp-toolbar span.ab-label, #wpadminbar > #wp-toolbar span.noticon{
		color:var(--gvt-topbar-text) !important;
	}
	#wpadminbar .ab-top-menu > li:hover > .ab-item, #wpadminbar.nojq .quicklinks .ab-top-menu > li > .ab-item:focus{
		background:rgba(127,127,127,.14) !important;
	}

	/* زیرمنوهای هاوری نوار بالا (مثل «سلام، ...» یا «جدید +») — پس‌زمینه‌ی خودشان
	   جدا از نوار اصلی تنظیم می‌شود تا رنگ متن همیشه با پس‌زمینه‌ی زیرش تضاد کافی داشته باشد.
	   توجه: بدون overflow:hidden، چون این نوار می‌تونه چند سطح زیرمنوی تودرتو هم داشته
	   باشه (مثلاً زیرِ برخی آیتم‌ها) و overflow:hidden اون سطح‌های عمیق‌تر رو قطع می‌کرد. */
	#wpadminbar .ab-sub-wrapper{
		background:var(--gvt-card-bg) !important;
		border:1px solid var(--gvt-border) !important;
		border-radius:10px !important;
		box-shadow:0 10px 26px rgba(0,0,0,.18) !important;
		padding:6px !important;
		z-index:99999;
	}
	#wpadminbar .ab-sub-wrapper .ab-submenu{ background:transparent !important; padding:0 !important; }
	#wpadminbar .ab-submenu .ab-item{
		color:var(--gvt-text) !important;
		border-radius:6px !important;
	}
	#wpadminbar .ab-submenu li .ab-item:hover,
	#wpadminbar .ab-submenu li .ab-item:focus{
		background:var(--gvt-sidebar-active-bg) !important;
		color:var(--gvt-sidebar-active-tx) !important;
	}
	#wpadminbar .ab-submenu .ab-item .ab-label,
	#wpadminbar .ab-submenu .ab-item .username{
		color:inherit !important;
	}
	/* زیرمنوی سطح دوم (تودرتو) کمی جمع‌تر تا از لبه‌ی صفحه بیرون نزنه */
	#wpadminbar .ab-submenu .ab-sub-wrapper{ margin-inline-start:2px; }

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

	<?php if ( 'sweetpop' === $pattern ) : ?>
	/* ---- الگوی تزئینی تم صورتی شیرین: قلب و ستاره‌ی نقطه‌چین روی پس‌زمینه سایدبار ---- */
	#adminmenuback, #adminmenuwrap{
		background-image: radial-gradient(circle at 18% 12%, rgba(255,92,157,.10) 0, transparent 40%),
		                   radial-gradient(circle at 82% 88%, rgba(255,92,157,.10) 0, transparent 40%) !important;
	}
	#wpadminbar{ background-image: linear-gradient(90deg, rgba(255,255,255,.10), rgba(255,255,255,0) 30%) !important; }
	.wrap h1:before{ content:"✨ "; }
	<?php elseif ( 'nova' === $pattern ) : ?>
	/* ---- الگوی تزئینی تم نووا: نقاط ستاره‌مانند روی سایدبار تیره ---- */
	#adminmenuback, #adminmenuwrap{
		background-image: radial-gradient(1px 1px at 10% 20%, rgba(34,224,255,.55) 0, transparent 60%),
		                   radial-gradient(1px 1px at 30% 70%, rgba(34,224,255,.4) 0, transparent 60%),
		                   radial-gradient(1px 1px at 60% 30%, rgba(34,224,255,.5) 0, transparent 60%),
		                   radial-gradient(1px 1px at 85% 80%, rgba(34,224,255,.45) 0, transparent 60%) !important;
	}
	#wpadminbar{ box-shadow: inset 0 -1px 0 rgba(34,224,255,.15) !important; }
	.wrap h1:before{ content:"⚡ "; }
	<?php endif; ?>

	<?php if ( ! empty( $settings['hidden_adminbar'] ) ) : ?>
	/* ---- مخفی‌کردن آیتم‌های انتخابی نوار بالا ---- */
	<?php foreach ( $settings['hidden_adminbar'] as $id ) : ?>
	#wp-admin-bar-<?php echo esc_html( $id ); ?>{ display:none !important; }
	<?php endforeach; ?>
	<?php endif; ?>

	/* ---- هماهنگ‌سازی برندِ گروت پرو با رنگ تم فعال ---- */
	#wpadminbar #wp-admin-bar-gv-pro-hub > .ab-item{
		color:var(--gvt-topbar-text) !important;
	}
	#wpadminbar #wp-admin-bar-gv-pro-hub:hover > .ab-item{
		background:var(--gvt-accent) !important; color:#fff !important;
	}
	#wpadminbar #wp-admin-bar-gv-pro-hub:hover .gvat-brand-badge{
		background:rgba(255,255,255,.22);
	}
	</style>
	<?php
}

/* ==========================================================================
   ۹) ویجت «دسترسی سریع به بخش‌های پیشخوان» — روی پیشخوان اصلی خود وردپرس
   ------------------------------------------------------------
   به‌جای زیرصفحه‌ی تنظیمات افزونه، این کارت‌ها مستقیماً به صفحه‌ی اصلی
   پیشخوان (index.php) اضافه می‌شوند تا اولین چیزی باشند که کاربر بعد از
   ورود می‌بیند؛ درست مثل ویجت‌های پیش‌فرض «در یک نگاه» وردپرس.
   ========================================================================== */
add_action( 'wp_dashboard_setup', 'gv_at_register_dashboard_widget' );
function gv_at_register_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	wp_add_dashboard_widget(
		'gv_at_quick_access_widget',
		'🚀 دسترسی سریع به بخش‌های پیشخوان',
		'gv_at_render_dashboard_widget'
	);

	// این ویجت را به بالای همه‌ی ویجت‌های پیش‌فرض پیشخوان منتقل کن
	global $wp_meta_boxes;
	if ( empty( $wp_meta_boxes['dashboard']['normal']['core'] ) ) { return; }
	$normal_core = $wp_meta_boxes['dashboard']['normal']['core'];
	if ( isset( $normal_core['gv_at_quick_access_widget'] ) ) {
		$widget = array( 'gv_at_quick_access_widget' => $normal_core['gv_at_quick_access_widget'] );
		unset( $normal_core['gv_at_quick_access_widget'] );
		$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $widget, $normal_core );
	}
}

/** خروجی محتوای ویجت دسترسی سریع در پیشخوان اصلی وردپرس */
function gv_at_render_dashboard_widget() {
	$items = gv_at_get_menu_quick_access();
	if ( empty( $items ) ) {
		echo '<p style="color:#6b7280;">در حال حاضر آیتمی برای نمایش وجود ندارد.</p>';
		return;
	}
	?>
	<div class="gvat-qa-grid">
		<?php foreach ( $items as $qa ) : ?>
			<a class="gvat-qa-card" href="<?php echo esc_url( $qa['href'] ); ?>">
				<span class="gvat-qa-icon">
					<?php if ( 'dashicon' === $qa['icon']['type'] ) : ?>
						<span class="dashicons <?php echo esc_attr( $qa['icon']['value'] ); ?>"></span>
					<?php elseif ( 'image' === $qa['icon']['type'] ) : ?>
						<img src="<?php echo esc_url( $qa['icon']['value'] ); ?>" alt="" />
					<?php else : ?>
						<span class="gvat-qa-icon-fallback"><?php echo esc_html( mb_substr( $qa['label'], 0, 1 ) ); ?></span>
					<?php endif; ?>
				</span>
				<span class="gvat-qa-body">
					<strong><?php echo esc_html( $qa['label'] ); ?></strong>
					<small><?php echo esc_html( $qa['desc'] ); ?></small>
					<?php if ( $qa['sub_count'] > 0 ) : ?>
						<span class="gvat-qa-badge"><?php echo esc_html( $qa['sub_count'] ); ?> زیربخش</span>
					<?php endif; ?>
				</span>
				<span class="gvat-qa-arrow">←</span>
			</a>
		<?php endforeach; ?>
	</div>
	<style>
	#gv_at_quick_access_widget .inside{ margin:0; padding:0 12px 12px; }
	#gv_at_quick_access_widget .gvat-qa-grid{
		display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:10px; direction:rtl;
	}
	#gv_at_quick_access_widget .gvat-qa-card{
		display:flex; align-items:flex-start; gap:10px; text-decoration:none;
		background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px;
		transition:border-color .15s ease, transform .15s ease, box-shadow .15s ease;
	}
	#gv_at_quick_access_widget .gvat-qa-card:hover{
		border-color:#4f46e5; transform:translateY(-2px); box-shadow:0 8px 16px rgba(79,70,229,.12);
	}
	#gv_at_quick_access_widget .gvat-qa-icon{
		flex:0 0 auto; width:36px; height:36px; border-radius:9px;
		background:#eef2ff; color:#4338ca; display:flex; align-items:center; justify-content:center;
		font-size:16px; overflow:hidden;
	}
	#gv_at_quick_access_widget .gvat-qa-icon .dashicons{ width:18px; height:18px; font-size:18px; }
	#gv_at_quick_access_widget .gvat-qa-icon img{ width:20px; height:20px; object-fit:contain; }
	#gv_at_quick_access_widget .gvat-qa-icon-fallback{ font-weight:800; font-size:14px; }
	#gv_at_quick_access_widget .gvat-qa-body{ display:flex; flex-direction:column; gap:2px; min-width:0; flex:1; }
	#gv_at_quick_access_widget .gvat-qa-body strong{ font-size:13px; color:#1f2430; }
	#gv_at_quick_access_widget .gvat-qa-body small{ font-size:11px; color:#6b7280; line-height:1.5; }
	#gv_at_quick_access_widget .gvat-qa-badge{
		align-self:flex-start; margin-top:4px; font-size:10px; background:#eef2ff; color:#4338ca;
		border-radius:999px; padding:2px 8px;
	}
	#gv_at_quick_access_widget .gvat-qa-arrow{ flex:0 0 auto; color:#9ca3af; font-size:13px; align-self:center; }
	#gv_at_quick_access_widget .gvat-qa-card:hover .gvat-qa-arrow{ color:#4f46e5; }
	</style>
	<?php
}

/* ==========================================================================
   ۱۰) افزودن برند «گروت پرو» (لوگو + نام) به نوار بالای پیشخوان
   ------------------------------------------------------------
   یک آیتم ثابت در نوار بالای وردپرس اضافه می‌کند تا از هر صفحه‌ای
   با یک کلیک به هاب اصلی افزونه دسترسی سریع وجود داشته باشد.
   این بخش مستقل از فعال/غیرفعال بودن تم پیشخوان همیشه کار می‌کند.
   ========================================================================== */
add_action( 'admin_bar_menu', 'gv_at_add_brand_to_adminbar', 5 );
function gv_at_add_brand_to_adminbar( $wp_admin_bar ) {
	if ( ! defined( 'GV_HUB_SLUG' ) ) { return; }
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$logo = '<svg width="16" height="16" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
		. '<circle cx="16" cy="16" r="16" fill="currentColor" opacity=".18"/>'
		. '<path d="M16 5.5c5.8 0 10.5 4.7 10.5 10.5S21.8 26.5 16 26.5 5.5 21.8 5.5 16 10.2 5.5 16 5.5Z" fill="none" stroke="currentColor" stroke-width="2"/>'
		. '<path d="M11 17.2c0-3.4 2.3-6.1 5.3-6.1 2 0 3.7 1.1 4.6 2.8h-3v1.8h5.4v-5.4h-1.8v2.1c-1.3-1.9-3.3-3.1-5.6-3.1-4.1 0-7.4 3.5-7.4 7.9 0 4.4 3.3 7.9 7.4 7.9 2.9 0 5.5-1.8 6.7-4.5l-1.9-.8c-.9 2-2.7 3.3-4.8 3.3-3 0-5.9-2.7-5.9-6.9Z" fill="currentColor"/>'
		. '</svg>';

	$title = '<span class="gvat-brand-badge">' . $logo . '</span><span class="gvat-brand-name">Groot Pro</span>';

	$wp_admin_bar->add_node( array(
		'id'    => 'gv-pro-hub',
		'title' => $title,
		'href'  => admin_url( 'admin.php?page=' . GV_HUB_SLUG ),
		'meta'  => array(
			'class' => 'gvat-brand-node',
			'title' => 'رفتن به داشبورد گروت پرو',
		),
	) );

	// دسترسی سریع به تم پیشخوان از همان زیرمنو
	$wp_admin_bar->add_node( array(
		'id'     => 'gv-pro-hub-theme',
		'parent' => 'gv-pro-hub',
		'title'  => '🎨 تم پیشخوان',
		'href'   => admin_url( 'admin.php?page=' . GV_AT_PAGE_SLUG ),
	) );
}

/** استایل ثابت برند در نوار بالا — مستقل از فعال بودن تم اختصاصی */
add_action( 'admin_head', 'gv_at_brand_adminbar_css' );
function gv_at_brand_adminbar_css() {
	if ( ! defined( 'GV_HUB_SLUG' ) ) { return; }
	?>
	<style id="gv-brand-adminbar-css">
	#wpadminbar #wp-admin-bar-gv-pro-hub > .ab-item{
		display:flex !important; align-items:center; gap:6px; font-weight:600;
	}
	#wpadminbar .gvat-brand-badge{
		display:inline-flex; align-items:center; justify-content:center;
		width:22px; height:22px; border-radius:6px;
		background:rgba(255,255,255,.14); transition:background .12s ease;
	}
	#wpadminbar .gvat-brand-badge svg{ display:block; }
	#wpadminbar .gvat-brand-name{ letter-spacing:.2px; }
	#wpadminbar #wp-admin-bar-gv-pro-hub-theme > .ab-item{ display:flex; align-items:center; gap:4px; }
	</style>
	<?php
}