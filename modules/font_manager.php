<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — مدیریت فونت سایت (نسخه ۲.۱ — اصلاح‌شده)
 *  ------------------------------------------------------------
 *  رفع باگ اصلی: نام فونت فارسی (مثلاً «وزیرمتن») با sanitize_title
 *  به رشته‌ی %d9%88... تبدیل می‌شد و بعد در ذخیره‌ی تنظیمات
 *  sanitize_key علامت % را حذف می‌کرد → فونت انتخاب‌شده پیدا
 *  نمی‌شد و خالی ذخیره می‌شد → هیچ CSS ای روی سایت اعمال نمی‌شد.
 *  حالا اسلاگ همیشه انگلیسی/ASCII است (و فونت‌های قبلی خودکار
 *  مهاجرت داده می‌شوند).
 * ==========================================================
 */
define( 'GV_FONT_OPT', 'gv_font_manager_settings' );
define( 'GV_FONT_LIST_OPT', 'gv_font_manager_library' );
define( 'GV_FONT_NONCE', 'gv_font_nonce_action' );

/** پوشه‌ای که فونت‌های آپلودی داخلش ذخیره می‌شوند (داخل wp-content/uploads) */
function gv_font_upload_dir() {
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . 'gv-fonts';
	$url    = trailingslashit( $upload['baseurl'] ) . 'gv-fonts';
	if ( ! file_exists( $dir ) ) { wp_mkdir_p( $dir ); }
	return array( 'dir' => $dir, 'url' => $url );
}

/** ساخت اسلاگ ASCII امن؛ برای نام‌های فارسی از هش استفاده می‌کند */
function gv_font_make_slug( $name ) {
	$slug = strtolower( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', remove_accents( $name ) ) );
	$slug = trim( $slug, '-_' );
	if ( '' === $slug || ! preg_match( '/[a-z0-9]/', $slug ) ) {
		$slug = 'f' . substr( md5( $name ), 0, 8 );
	}
	return $slug;
}

/** مهاجرت یک‌باره‌ی فونت‌های قبلی که اسلاگ نامعتبر (%d9...) دارند */
add_action( 'init', 'gv_font_migrate_slugs', 5 );
function gv_font_migrate_slugs() {
	if ( get_option( 'gv_font_slug_migrated_v2' ) ) { return; }

	$library = get_option( GV_FONT_LIST_OPT, array() );
	if ( ! is_array( $library ) ) { $library = array(); }
	$paths = gv_font_upload_dir();
	$new   = array();
	$map   = array();

	foreach ( $library as $slug => $font ) {
		if ( preg_match( '/^[a-z0-9_-]+$/', (string) $slug ) ) { $new[ $slug ] = $font; continue; }

		$ns = 'f' . substr( md5( (string) $slug ), 0, 8 );
		while ( isset( $new[ $ns ] ) || isset( $library[ $ns ] ) ) { $ns .= 'x'; }

		if ( isset( $font['files'] ) && is_array( $font['files'] ) ) {
			foreach ( $font['files'] as $i => $f ) {
				$old = trailingslashit( $paths['dir'] ) . $f['file'];
				$ext = strtolower( pathinfo( $f['file'], PATHINFO_EXTENSION ) );
				$nn  = $ns . '-' . (int) $f['weight'] . ( 'italic' === $f['style'] ? 'i' : '' ) . '-' . strtolower( wp_generate_password( 4, false, false ) ) . '.' . $ext;
				if ( file_exists( $old ) && @rename( $old, trailingslashit( $paths['dir'] ) . $nn ) ) {
					$font['files'][ $i ]['file'] = $nn;
				}
			}
		}
		$new[ $ns ]  = $font;
		$map[ $slug ] = $ns;
	}

	if ( ! empty( $map ) ) {
		update_option( GV_FONT_LIST_OPT, $new );
		$s = get_option( GV_FONT_OPT, array() );
		if ( ! empty( $s['roles'] ) && is_array( $s['roles'] ) ) {
			foreach ( $s['roles'] as $k => $r ) {
				if ( ! empty( $r['font'] ) && isset( $map[ $r['font'] ] ) ) { $s['roles'][ $k ]['font'] = $map[ $r['font'] ]; }
			}
			update_option( GV_FONT_OPT, $s );
		}
	}
	update_option( 'gv_font_slug_migrated_v2', 1 );
}

/** نقش‌های تایپوگرافی سایت — هر کدام می‌توانند فونت اختصاصی خودشان را داشته باشند */
function gv_font_get_roles() {
	return array(
		'body'    => array(
			'label'    => 'متن اصلی سایت (بدنه)',
			'desc'     => 'پاراگراف‌ها، متن‌های عمومی، فرم‌ها',
			'selector' => 'body, p, li, span, a, div, td, th, strong, b, em, small, blockquote, input, textarea, select, label',
			'weight_selector' => 'body, p, li, input, textarea, select, label',
			'weight'   => 400,
		),
		'heading' => array(
			'label'    => 'سربرگ‌ها (h1 تا h6)',
			'desc'     => 'عنوان‌ها با مقیاس تایپوگرافی خودکار بزرگ می‌شوند',
			'selector' => 'h1, h2, h3, h4, h5, h6, h1 a, h2 a, h3 a, h4 a, h5 a, h6 a, .entry-title, .entry-title a',
			'weight'   => 700,
		),
		'menu'    => array(
			'label'    => 'منوی سایت',
			'desc'     => 'آیتم‌های منوی اصلی و ناوبری',
			'selector' => 'nav a, .menu a, .menu-item a, .nav-menu a, #main-menu a, .main-navigation a, .elementor-nav-menu a',
			'weight'   => 500,
		),
		'button'  => array(
			'label'    => 'دکمه‌ها',
			'desc'     => 'دکمه‌های سایت، فروشگاه و فرم‌ها',
			'selector' => 'button, .button, .btn, input[type="submit"], input[type="button"], .wp-block-button__link, .elementor-button',
			'weight'   => 600,
		),
		'logo'    => array(
			'label'    => 'عنوان سایت / لوگوی متنی',
			'desc'     => 'در صورتی که لوگوی سایت شما متنی باشد',
			'selector' => '.site-title, .site-title a, .site-branding .site-title, .custom-logo-link + .site-title',
			'weight'   => 800,
		),
	);
}

function gv_font_default_settings() {
	$defaults = array(
		'enabled'   => 0,
		'base_size' => 16,
		'scale'     => 1.25,
		'icon_exclude' => '',
		'roles'     => array(),
	);
	foreach ( gv_font_get_roles() as $key => $role ) {
		$defaults['roles'][ $key ] = array(
			'font'   => '',
			'weight' => $role['weight'],
		);
	}
	return $defaults;
}

function gv_font_get_settings() {
	$saved    = get_option( GV_FONT_OPT, array() );
	if ( ! is_array( $saved ) ) { $saved = array(); }
	$defaults = gv_font_default_settings();

	if ( ! empty( $saved['active_font'] ) && empty( $saved['roles']['body']['font'] ) ) {
		$saved['roles']['body']['font'] = $saved['active_font'];
	}

	$merged = wp_parse_args( $saved, $defaults );
	foreach ( $defaults['roles'] as $key => $role_defaults ) {
		$merged['roles'][ $key ] = wp_parse_args(
			isset( $saved['roles'][ $key ] ) && is_array( $saved['roles'][ $key ] ) ? $saved['roles'][ $key ] : array(),
			$role_defaults
		);
	}
	return $merged;
}

/** کتابخانه فونت‌های آپلودشده: slug => { name, files: [ {weight,style,file}, ... ] } */
function gv_font_get_library() {
	$library = get_option( GV_FONT_LIST_OPT, array() );
	if ( ! is_array( $library ) ) { $library = array(); }

	foreach ( $library as $slug => &$font ) {
		if ( ! isset( $font['files'] ) ) {
			$files = array();
			if ( ! empty( $font['regular'] ) ) { $files[] = array( 'weight' => 400, 'style' => 'normal', 'file' => $font['regular'] ); }
			if ( ! empty( $font['bold'] ) )    { $files[] = array( 'weight' => 700, 'style' => 'normal', 'file' => $font['bold'] ); }
			if ( ! empty( $font['italic'] ) )  { $files[] = array( 'weight' => 400, 'style' => 'italic', 'file' => $font['italic'] ); }
			$font['files'] = $files;
		}
	}
	unset( $font );
	return $library;
}

/** نزدیک‌ترین فایلِ یک وزن دلخواه */
function gv_font_closest_weight_file( $files, $target_weight, $style = 'normal' ) {
	if ( empty( $files ) ) { return null; }
	$best = null;
	$best_diff = 99999;
	foreach ( $files as $f ) {
		if ( $f['style'] !== $style ) { continue; }
		$diff = abs( (int) $f['weight'] - (int) $target_weight );
		if ( $diff < $best_diff ) { $best_diff = $diff; $best = $f; }
	}
	if ( ! $best && $style === 'italic' ) {
		return gv_font_closest_weight_file( $files, $target_weight, 'normal' );
	}
	return $best;
}

/* ==========================================================================
   منوی مدیریت
   ========================================================================== */

add_action( 'admin_menu', 'gv_font_admin_menu' );
function gv_font_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'مدیریت فونت سایت | Groot Vision',
		'🔤 مدیریت فونت',
		'manage_options',
		'gv-font-manager',
		'gv_font_render_admin_page'
	);
}

/* ---- افزودن فونت جدید (یا وزن جدید) ---- */
add_action( 'admin_post_gv_font_upload', 'gv_font_handle_upload' );
function gv_font_handle_upload() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_FONT_NONCE );

	$name = sanitize_text_field( wp_unslash( $_POST['font_name'] ?? '' ) );
	if ( empty( $name ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=gv-font-manager&error=name' ) );
		exit;
	}

	$library = gv_font_get_library();

	// اگر قبلاً فونتی با همین نام ثبت شده، از همان اسلاگ استفاده کن
	$slug = '';
	foreach ( $library as $k => $f ) {
		if ( isset( $f['name'] ) && $f['name'] === $name ) { $slug = $k; break; }
	}
	if ( '' === $slug ) { $slug = gv_font_make_slug( $name ); }

	$paths = gv_font_upload_dir();

	$allowed_ext     = array( 'woff2', 'woff', 'ttf', 'otf' );
	$allowed_weights = array( 100, 200, 300, 400, 500, 600, 700, 800, 900 );

	$weights = isset( $_POST['file_weight'] ) ? (array) $_POST['file_weight'] : array();
	$styles  = isset( $_POST['file_style'] ) ? (array) $_POST['file_style'] : array();
	$files   = isset( $_FILES['font_files'] ) ? $_FILES['font_files'] : array();

	$new_files = array();

	if ( ! empty( $files['name'] ) && is_array( $files['name'] ) ) {
		foreach ( $files['name'] as $i => $orig_name ) {
			if ( empty( $orig_name ) ) { continue; }
			if ( ! empty( $files['error'][ $i ] ) ) { continue; }

			$ext = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed_ext, true ) ) { continue; }

			$weight = isset( $weights[ $i ] ) ? intval( $weights[ $i ] ) : 400;
			if ( ! in_array( $weight, $allowed_weights, true ) ) { $weight = 400; }
			$style = ( isset( $styles[ $i ] ) && $styles[ $i ] === 'italic' ) ? 'italic' : 'normal';

			$dest_name = $slug . '-' . $weight . ( $style === 'italic' ? 'i' : '' ) . '-' . strtolower( wp_generate_password( 4, false, false ) ) . '.' . $ext;
			$dest_path = trailingslashit( $paths['dir'] ) . $dest_name;

			if ( move_uploaded_file( $files['tmp_name'][ $i ], $dest_path ) ) {
				$new_files[] = array( 'weight' => $weight, 'style' => $style, 'file' => $dest_name );
			}
		}
	}

	if ( empty( $new_files ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=gv-font-manager&error=file' ) );
		exit;
	}

	if ( isset( $library[ $slug ] ) ) {
		foreach ( $new_files as $nf ) {
			$replaced = false;
			foreach ( $library[ $slug ]['files'] as &$existing ) {
				if ( (int) $existing['weight'] === (int) $nf['weight'] && $existing['style'] === $nf['style'] ) {
					$old = trailingslashit( $paths['dir'] ) . $existing['file'];
					if ( file_exists( $old ) ) { @unlink( $old ); }
					$existing = $nf;
					$replaced = true;
					break;
				}
			}
			unset( $existing );
			if ( ! $replaced ) { $library[ $slug ]['files'][] = $nf; }
		}
	} else {
		$library[ $slug ] = array( 'name' => $name, 'files' => $new_files );
	}

	update_option( GV_FONT_LIST_OPT, $library );

	wp_safe_redirect( admin_url( 'admin.php?page=gv-font-manager&uploaded=1' ) );
	exit;
}

add_action( 'admin_post_gv_font_delete', 'gv_font_handle_delete' );
function gv_font_handle_delete() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_FONT_NONCE );

	$slug    = gv_font_request_slug();
	$library = gv_font_get_library();
	if ( '' === $slug || ! isset( $library[ $slug ] ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=gv-font-manager&error=notfound' ) );
		exit;
	}

	$paths = gv_font_upload_dir();
	foreach ( $library[ $slug ]['files'] as $f ) {
		$path = trailingslashit( $paths['dir'] ) . $f['file'];
		if ( file_exists( $path ) ) { @unlink( $path ); }
	}
	unset( $library[ $slug ] );
	update_option( GV_FONT_LIST_OPT, $library );

	$s = gv_font_get_settings();
	$changed = false;
	foreach ( $s['roles'] as $key => $role ) {
		if ( $role['font'] === $slug ) { $s['roles'][ $key ]['font'] = ''; $changed = true; }
	}
	if ( $changed ) { update_option( GV_FONT_OPT, $s ); }

	wp_safe_redirect( admin_url( 'admin.php?page=gv-font-manager&deleted=1' ) );
	exit;
}

/** حذف فقط یک وزن خاص از یک فونت */
add_action( 'admin_post_gv_font_delete_weight', 'gv_font_handle_delete_weight' );
function gv_font_handle_delete_weight() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_FONT_NONCE );

	$slug   = gv_font_request_slug();
	$weight = intval( $_REQUEST['weight'] ?? 0 );
	$style  = ( ( $_REQUEST['style'] ?? 'normal' ) === 'italic' ) ? 'italic' : 'normal';

	$library = gv_font_get_library();
	if ( isset( $library[ $slug ] ) ) {
		$paths = gv_font_upload_dir();
		foreach ( $library[ $slug ]['files'] as $i => $f ) {
			if ( (int) $f['weight'] === $weight && $f['style'] === $style ) {
				$path = trailingslashit( $paths['dir'] ) . $f['file'];
				if ( file_exists( $path ) ) { @unlink( $path ); }
				unset( $library[ $slug ]['files'][ $i ] );
				$library[ $slug ]['files'] = array_values( $library[ $slug ]['files'] );
				break;
			}
		}
		if ( empty( $library[ $slug ]['files'] ) ) {
			unset( $library[ $slug ] );
			$s = gv_font_get_settings();
			foreach ( $s['roles'] as $key => $role ) {
				if ( $role['font'] === $slug ) { $s['roles'][ $key ]['font'] = ''; }
			}
			update_option( GV_FONT_OPT, $s );
		}
		update_option( GV_FONT_LIST_OPT, $library );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=gv-font-manager&deleted=1' ) );
	exit;
}

add_action( 'admin_post_gv_font_save_settings', 'gv_font_save_settings' );
function gv_font_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_FONT_NONCE );

	$library = gv_font_get_library();
	$roles   = gv_font_get_roles();

	$settings = array(
		'enabled'   => isset( $_POST['enabled'] ) ? 1 : 0,
		'base_size' => max( 12, min( 22, intval( $_POST['base_size'] ?? 16 ) ) ),
		'scale'     => max( 1.05, min( 1.6, floatval( $_POST['scale'] ?? 1.25 ) ) ),
		'icon_exclude' => gv_font_clean_icon_selectors( wp_unslash( $_POST['icon_exclude'] ?? '' ) ),
		'roles'     => array(),
	);

	foreach ( $roles as $key => $role_def ) {
		$font = sanitize_key( wp_unslash( $_POST[ 'role_font_' . $key ] ?? '' ) );
		if ( '' !== $font && ! isset( $library[ $font ] ) ) { $font = ''; }

		$weight = intval( $_POST[ 'role_weight_' . $key ] ?? $role_def['weight'] );
		if ( ! in_array( $weight, array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ), true ) ) {
			$weight = $role_def['weight'];
		}

		$settings['roles'][ $key ] = array( 'font' => $font, 'weight' => $weight );
	}

	update_option( GV_FONT_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=gv-font-manager&updated=1' ) );
	exit;
}

/* ==========================================================================
   صفحه مدیریت
   ========================================================================== */

function gv_font_weight_label( $w ) {
	$labels = array(
		100 => 'خیلی نازک (100)', 200 => 'نازک (200)', 300 => 'سبک (300)',
		400 => 'معمولی (400)', 500 => 'متوسط (500)', 600 => 'نیمه‌ضخیم (600)',
		700 => 'ضخیم (700)', 800 => 'خیلی‌ضخیم (800)', 900 => 'سیاه (900)',
	);
	return $labels[ (int) $w ] ?? ( $w . '' );
}

function gv_font_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s       = gv_font_get_settings();
	$library = gv_font_get_library();
	$roles   = gv_font_get_roles();
	$paths   = gv_font_upload_dir();
	$used_any = false;
	foreach ( $s['roles'] as $rv ) { if ( ! empty( $rv['font'] ) && isset( $library[ $rv['font'] ] ) ) { $used_any = true; } }
	?>
	<div class="wrap" dir="rtl" style="font-family: 'Vazirmatn', Tahoma, sans-serif; max-width:1080px;">
		<style>
			.gvfont-header{background:linear-gradient(120deg,#0e4037,#145c4d);color:#fff;padding:26px 30px;border-radius:16px;margin:20px 0 28px;box-shadow:0 10px 30px rgba(14,64,55,.3);}
			.gvfont-header h1{margin:0;font-size:22px;color:#fff;display:flex;align-items:center;gap:10px;}
			.gvfont-header p{margin:8px 0 0;font-size:13px;color:#cbd5e1;}
			.gvfont-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,.03);}
			.gvfont-card h2{margin-top:0;font-size:16px;color:#0f172a;display:flex;align-items:center;gap:8px;}
			.gvfont-field{margin-bottom:14px;}
			.gvfont-field label{display:block;font-weight:700;font-size:13px;margin-bottom:5px;color:#334155;}
			.gvfont-field input[type=text],.gvfont-field input[type=file],.gvfont-field input[type=number],.gvfont-field select{width:100%;max-width:380px;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;}
			.gvfont-btn{background:#0e4037;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;font-size:13.5px;}
			.gvfont-btn.secondary{background:#f1f5f9;color:#334155 !important;}
			.gvfont-lib-item{border:1px solid #e2e8f0;border-radius:14px;margin-bottom:14px;overflow:hidden;}
			.gvfont-lib-item.is-used{border-color:#0e4037;box-shadow:0 0 0 2px rgba(14,64,55,.08);}
			.gvfont-lib-item-top{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#f8fafc;}
			.gvfont-lib-name{font-weight:800;font-size:14.5px;color:#0f172a;}
			.gvfont-lib-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;}
			.gvfont-tag{font-size:10.5px;background:#e2e8f0;color:#475569;padding:3px 9px;border-radius:20px;display:inline-flex;align-items:center;gap:5px;}
			.gvfont-tag a{color:#b91c1c;text-decoration:none;}
			.gvfont-del{color:#b91c1c;text-decoration:none;font-size:12.5px;font-weight:600;}
			.gvfont-preview{padding:16px 18px;border-top:1px dashed #e2e8f0;font-size:20px;color:#0f172a;line-height:1.9;}
			.gvfont-role-row{display:grid;grid-template-columns:1.2fr 1.4fr 1fr;gap:14px;align-items:end;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-bottom:12px;}
			.gvfont-role-row .role-info b{display:block;font-size:13.5px;color:#0f172a;}
			.gvfont-role-row .role-info span{font-size:11.5px;color:#94a3b8;}
			.gvfont-filerow{display:grid;grid-template-columns:1fr 1fr 1.4fr auto;gap:10px;align-items:center;margin-bottom:8px;}
			#gvfont-filerows input[type=file]{max-width:none;}
			.gvfont-addrow{font-size:12.5px;color:#0e4037;font-weight:700;cursor:pointer;background:none;border:1px dashed #0e4037;border-radius:8px;padding:6px 12px;}
			@media(max-width:782px){ .gvfont-role-row, .gvfont-filerow{ grid-template-columns:1fr; } }
		</style>

		<div class="gvfont-header">
			<h1>🔤 مدیریت فونت سایت</h1>
			<p>فونت دلخواه را برای هر بخش از سایت (متن، سربرگ، منو، دکمه، لوگو) جداگانه انتخاب کنید.</p>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
			<?php if ( empty( $s['enabled'] ) ) : ?>
				<div class="notice notice-warning"><p>⚠️ تیک «اعمال این تنظیمات روی کل سایت» فعال نیست؛ تا فعال نکنید چیزی روی سایت تغییر نمی‌کند.</p></div>
			<?php elseif ( ! $used_any ) : ?>
				<div class="notice notice-warning"><p>⚠️ برای هیچ بخشی فونتی انتخاب نشده است. حداقل برای «متن اصلی سایت» یک فونت انتخاب کنید.</p></div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( isset( $_GET['uploaded'] ) ) : ?><div class="notice notice-success is-dismissible"><p>فونت با موفقیت اضافه شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p>مورد حذف شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['error'] ) && 'notfound' === $_GET['error'] ) : ?>
			<div class="notice notice-error is-dismissible"><p>خطا: این فونت در کتابخانه پیدا نشد. صفحه را یک‌بار با Ctrl+F5 رفرش کنید و دوباره امتحان کنید.</p></div>
		<?php elseif ( isset( $_GET['error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>خطا: نام فونت یا فایل معتبر ارسال نشد (فرمت‌های مجاز: woff2, woff, ttf, otf).</p></div>
		<?php endif; ?>

		<div class="gvfont-card">
			<h2>➕ افزودن فونت / وزن جدید به کتابخانه</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="gv_font_upload">
				<?php wp_nonce_field( GV_FONT_NONCE ); ?>
				<div class="gvfont-field">
					<label>نام فونت</label>
					<input type="text" name="font_name" placeholder="مثلاً: وزیرمتن، ایران‌یکان، دیره">
					<small style="color:#94a3b8;">اگر نام فونتی که قبلاً اضافه کرده‌اید را دوباره وارد کنید، فایل‌های جدید به‌عنوان وزن‌های اضافه به همان فونت افزوده می‌شوند.</small>
				</div>

				<label style="display:block;font-weight:700;font-size:13px;margin-bottom:8px;color:#334155;">فایل‌های فونت (هر فایل = یک وزن مشخص)</label>
				<div id="gvfont-filerows">
					<div class="gvfont-filerow">
						<select name="file_weight[]">
							<?php foreach ( array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ) as $w ) : ?>
								<option value="<?php echo (int) $w; ?>" <?php selected( $w, 400 ); ?>><?php echo esc_html( gv_font_weight_label( $w ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="file_style[]">
							<option value="normal">حالت عادی</option>
							<option value="italic">کج (Italic)</option>
						</select>
						<input type="file" name="font_files[]" accept=".woff2,.woff,.ttf,.otf">
						<span></span>
					</div>
				</div>
				<button type="button" class="gvfont-addrow" onclick="gvFontAddRow()">➕ افزودن وزن دیگر</button>
				<div style="margin-top:18px;">
					<button type="submit" class="gvfont-btn">📤 آپلود</button>
				</div>
			</form>
			<script>
			function gvFontAddRow(){
				var wrap = document.getElementById('gvfont-filerows');
				var row = wrap.firstElementChild.cloneNode(true);
				row.querySelectorAll('input[type=file]').forEach(function(i){ i.value=''; });
				var del = document.createElement('button');
				del.type = 'button';
				del.textContent = '✕';
				del.className = 'gvfont-btn secondary';
				del.style.padding = '6px 12px';
				del.onclick = function(){ row.remove(); };
				row.lastElementChild.replaceWith(del);
				wrap.appendChild(row);
			}
			</script>
		</div>

		<div class="gvfont-card">
			<h2>📚 کتابخانه فونت‌ها</h2>
			<?php if ( empty( $library ) ) : ?>
				<p style="color:#94a3b8;">هنوز فونتی اضافه نکرده‌اید.</p>
			<?php else : ?>
				<?php
				$used_slugs = wp_list_pluck( $s['roles'], 'font' );
				foreach ( $library as $slug => $font ) :
					$is_used      = in_array( $slug, $used_slugs, true );
					$regular_file = gv_font_closest_weight_file( $font['files'], 400, 'normal' );
					?>
					<div class="gvfont-lib-item <?php echo $is_used ? 'is-used' : ''; ?>">
						<div class="gvfont-lib-item-top">
							<div>
								<div class="gvfont-lib-name"><?php echo esc_html( $font['name'] ); ?> <?php echo $is_used ? '<span style="color:#0e4037;font-size:11px;">(در حال استفاده)</span>' : ''; ?></div>
								<div class="gvfont-lib-tags">
									<?php foreach ( $font['files'] as $f ) : ?>
										<span class="gvfont-tag">
											<?php echo esc_html( gv_font_weight_label( $f['weight'] ) . ( $f['style'] === 'italic' ? ' کج' : '' ) ); ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_font_delete_weight&slug=' . rawurlencode( $slug ) . '&weight=' . (int) $f['weight'] . '&style=' . $f['style'] ), GV_FONT_NONCE ) ); ?>" onclick="return confirm('این وزن حذف شود؟');">✕</a>
										</span>
									<?php endforeach; ?>
								</div>
							</div>
							<a class="gvfont-del" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_font_delete&slug=' . rawurlencode( $slug ) ), GV_FONT_NONCE ) ); ?>" onclick="return confirm('کل این فونت (همه وزن‌ها) حذف شود؟');">🗑️ حذف کامل</a>
						</div>
						<?php if ( $regular_file ) : ?>
						<div class="gvfont-preview" style="font-family:'GVPreview-<?php echo esc_attr( $slug ); ?>', Tahoma, sans-serif;">
							<style>
								@font-face{ font-family:'GVPreview-<?php echo esc_attr( $slug ); ?>'; src:url('<?php echo esc_url( trailingslashit( $paths['url'] ) . $regular_file['file'] ); ?>') format('<?php echo esc_attr( gv_font_format( $regular_file['file'] ) ); ?>'); font-display:swap; }
							</style>
							سلام! این یک متن پیش‌نمایش برای «<?php echo esc_html( $font['name'] ); ?>» است — Aa 123
						</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $library ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gv_font_save_settings">
			<?php wp_nonce_field( GV_FONT_NONCE ); ?>

			<div class="gvfont-card">
				<h2>🧩 فونت هر بخش سایت</h2>
				<p style="color:#64748b;font-size:12.5px;margin-top:-6px;">برای هر بخش، یک فونت و وزن از کتابخانه انتخاب کنید. اگر «وراثت از متن اصلی» بگذارید، همان فونت بدنه استفاده می‌شود.</p>

				<?php foreach ( $roles as $key => $role ) : $rv = $s['roles'][ $key ]; ?>
					<div class="gvfont-role-row">
						<div class="role-info">
							<b><?php echo esc_html( $role['label'] ); ?></b>
							<span><?php echo esc_html( $role['desc'] ); ?></span>
						</div>
						<div>
							<label style="font-size:12px;font-weight:700;color:#334155;">فونت</label>
							<select name="role_font_<?php echo esc_attr( $key ); ?>" style="width:100%;">
								<option value=""><?php echo $key === 'body' ? '— فونت پیش‌فرض تم —' : '— وراثت از متن اصلی —'; ?></option>
								<?php foreach ( $library as $slug => $font ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $rv['font'], $slug ); ?>><?php echo esc_html( $font['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label style="font-size:12px;font-weight:700;color:#334155;">وزن</label>
							<select name="role_weight_<?php echo esc_attr( $key ); ?>" style="width:100%;">
								<?php foreach ( array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ) as $w ) : ?>
									<option value="<?php echo (int) $w; ?>" <?php selected( (int) $rv['weight'], $w ); ?>><?php echo esc_html( gv_font_weight_label( $w ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="gvfont-card">
				<h2>⚙️ تنظیمات عمومی</h2>
				<div class="gvfont-field">
					<label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'], 1 ); ?>> اعمال این تنظیمات روی کل سایت</label>
				</div>
				<div class="gvfont-field">
					<label>سایز پایه متن بدنه سایت (پیکسل)</label>
					<input type="number" name="base_size" min="12" max="22" value="<?php echo esc_attr( $s['base_size'] ); ?>">
				</div>
				<div class="gvfont-field">
					<label>ضریب بزرگ‌شدن سربرگ‌ها (h1 تا h6) — پیش‌فرض ۱.۲۵ توصیه می‌شود</label>
					<input type="number" step="0.01" name="scale" min="1.05" max="1.6" value="<?php echo esc_attr( $s['scale'] ); ?>">
					<small style="display:block;color:#94a3b8;margin-top:4px;">با این عدد، اندازه h1 تا h6 به‌صورت خودکار و متناسب محاسبه می‌شود؛ نیازی به تنظیم دستی هر سربرگ نیست.</small>
				</div>
				<div class="gvfont-field">
					<label>سلکتور آیکن‌های اضافی که نباید تغییر کنند (اختیاری)</label>
					<textarea name="icon_exclude" rows="2" style="width:100%;max-width:380px;direction:ltr;" placeholder=".my-social-icon, .tg-icon"><?php echo esc_textarea( $s['icon_exclude'] ); ?></textarea>
					<small style="display:block;color:#94a3b8;margin-top:4px;">آیکن‌های رایج (Font Awesome، Elementor، Dashicons و ...) خودکار کنار گذاشته می‌شوند. اگر آیکنی هنوز خراب است، کلاس CSS آن را اینجا بنویسید و با کاما جدا کنید.</small>
				</div>
				<button type="submit" class="gvfont-btn">💾 ذخیره و اعمال</button>
			</div>
		</form>
		<?php endif; ?>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">
			نکته: برای بهترین نتیجه، حتماً فرمت <code>woff2</code> آپلود کنید. اگر بعد از ذخیره تغییری نمی‌بینید، کش سایت/افزونه کش و مرورگر را پاک کنید.
		</p>
	</div>
	<?php
}

/* ==========================================================================
   خروجی CSS در سمت سایت
   ========================================================================== */

// اولویت بالا تا بعد از استایل‌های تم و صفحه‌ساز چاپ شود
add_action( 'wp_head', 'gv_font_output_css', 999 );
function gv_font_output_css() {
	$s = gv_font_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }

	$library = gv_font_get_library();
	$roles   = gv_font_get_roles();
	$paths   = gv_font_upload_dir();

	$body_font = $s['roles']['body']['font'];

	$used_fonts = array();
	foreach ( $s['roles'] as $key => $rv ) {
		$font = $rv['font'];
		if ( '' === $font && 'body' !== $key ) { $font = $body_font; }
		if ( '' !== $font && isset( $library[ $font ] ) && ! empty( $library[ $font ]['files'] ) ) { $used_fonts[ $font ] = true; }
	}

	if ( empty( $used_fonts ) ) { return; }

	$base  = (float) $s['base_size'];
	$scale = (float) $s['scale'];
	$sizes = array(
		'h1' => round( $base * pow( $scale, 5 ), 1 ),
		'h2' => round( $base * pow( $scale, 4 ), 1 ),
		'h3' => round( $base * pow( $scale, 3 ), 1 ),
		'h4' => round( $base * pow( $scale, 2 ), 1 ),
		'h5' => round( $base * pow( $scale, 1 ), 1 ),
		'h6' => round( $base * 1.05, 1 ),
	);

	$css = '';
	$ex  = gv_font_icon_exclusions( isset( $s['icon_exclude'] ) ? $s['icon_exclude'] : '' );

	foreach ( $used_fonts as $slug => $x ) {
		$family = 'GVFont-' . $slug;
		foreach ( $library[ $slug ]['files'] as $f ) {
			$css .= "@font-face{font-family:'" . $family . "';src:url('" . esc_url_raw( trailingslashit( $paths['url'] ) . $f['file'] ) . "') format('" . gv_font_format( $f['file'] ) . "');font-weight:" . (int) $f['weight'] . ';font-style:' . ( 'italic' === $f['style'] ? 'italic' : 'normal' ) . ";font-display:swap;}\n";
		}
	}

	$css .= 'body{font-size:' . $base . "px;}\n";

	foreach ( $roles as $key => $role_def ) {
		$rv   = $s['roles'][ $key ];
		$font = $rv['font'];
		if ( '' === $font && 'body' !== $key ) { $font = $body_font; }
		if ( '' === $font || ! isset( $library[ $font ] ) ) { continue; }

		$family = 'GVFont-' . $font;
		$stack  = "'" . $family . "', -apple-system, Tahoma, sans-serif";

		// فونت
		$css .= gv_font_scope( $role_def['selector'], $ex ) . '{font-family:' . $stack . " !important;}\n";

		// وزن (برای بدنه فقط روی عناصر اصلی تا bold داخل متن خراب نشود)
		$weight_sel = isset( $role_def['weight_selector'] ) ? $role_def['weight_selector'] : $role_def['selector'];
		$css .= gv_font_scope( $weight_sel, $ex ) . '{font-weight:' . (int) $rv['weight'] . " !important;}\n";

		if ( 'body' === $key ) {
			$css .= "strong, b{font-weight:700 !important;}\n";
		}
		if ( 'heading' === $key ) {
			$css .= gv_font_scope( $role_def['selector'], $ex ) . "{line-height:1.4;}\n";
		}
	}

	foreach ( $sizes as $tag => $size ) {
		$css .= $tag . '{font-size:' . $size . "px !important;}\n";
	}

	// نکته: آیکون‌ها با :not(...) از همه‌ی قوانین بالا کنار گذاشته شده‌اند،
	// پس هیچ قانون اضافه‌ای روی آن‌ها اعمال نمی‌شود و فونت اصلی خودشان حفظ می‌شود.

	echo '<style id="gv-font-manager-css">' . "\n" . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** پیدا کردن اسلاگ فونت از درخواست (GET یا POST) به‌صورت مقاوم */
function gv_font_request_slug() {
	$raw = isset( $_REQUEST['slug'] ) ? wp_unslash( $_REQUEST['slug'] ) : '';
	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( '' === $raw ) { return ''; }
	$library = gv_font_get_library();

	if ( isset( $library[ $raw ] ) ) { return (string) $raw; }
	$dec = rawurldecode( $raw );
	if ( isset( $library[ $dec ] ) ) { return (string) $dec; }
	$key = sanitize_key( $raw );
	if ( isset( $library[ $key ] ) ) { return (string) $key; }
	foreach ( $library as $s => $f ) {
		if ( isset( $f['name'] ) && $f['name'] === $raw ) { return (string) $s; }
	}
	return '';
}

/** پاک‌سازی لیست سلکتورهای آیکن اضافی که کاربر وارد می‌کند (فقط سلکتور ساده) */
function gv_font_clean_icon_selectors( $str ) {
	$out = array();
	foreach ( preg_split( '/[,\n\r]+/', (string) $str ) as $sel ) {
		$sel = trim( $sel );
		if ( '' !== $sel && preg_match( '/^[a-zA-Z0-9_\-\.\#\[\]=\"\'\*\^\$~:]+$/', $sel ) ) { $out[] = $sel; }
	}
	return implode( ', ', $out );
}

/** رشته‌ی :not(...) برای کنار گذاشتن آیکن‌ها از تغییر فونت */
function gv_font_icon_exclusions( $extra = '' ) {
	$list = array(
		'i', '.fa', '.fas', '.far', '.fab', '.fal', '.fad', '.fat', '.icon', '.bi',
		'[class^="fa-"]', '[class*=" fa-"]',
		'[class^="icon-"]', '[class*=" icon-"]', '[class^="icon_"]', '[class*=" icon_"]',
		'[class^="eicon"]', '[class*=" eicon"]',
		'[class^="ti-"]', '[class*=" ti-"]', '[class^="bi-"]', '[class*=" bi-"]',
		'[class^="ri-"]', '[class*=" ri-"]', '[class^="uil"]', '[class*=" uil-"]',
		'[class^="dashicons"]', '[class*=" dashicons"]',
		'.material-icons', '.material-symbols-outlined',
		'[class*="glyphicon"]', '[class*="icomoon"]', '[class*="lni"]', '[class*="remixicon"]',
	);
	if ( $extra ) {
		foreach ( explode( ',', $extra ) as $e ) { $e = trim( $e ); if ( '' !== $e ) { $list[] = $e; } }
	}
	$out = '';
	foreach ( $list as $l ) { $out .= ':not(' . $l . ')'; }
	return $out;
}

/** افزودن شرط‌های حذف آیکن به انتهای هر سلکتور از یک لیست */
function gv_font_scope( $selectors, $ex ) {
	$parts = array();
	foreach ( explode( ',', $selectors ) as $p ) {
		$p = trim( $p );
		if ( '' !== $p ) { $parts[] = $p . $ex; }
	}
	return implode( ', ', $parts );
}

function gv_font_format( $filename ) {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$map = array( 'woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype' );
	return $map[ $ext ] ?? 'woff2';
}