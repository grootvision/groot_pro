<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * فهرست آیتم‌هایی که می‌شود محدود کرد.
 * منو و نوار ادمین هر بار که یک مدیر (بدون محدودیت) صفحه‌ای را باز می‌کند به‌روز می‌شوند.
 * ویجت‌ها و متاباکس‌ها با دکمه‌ی «اسکن» (یا بازدید از همان صفحه) یاد گرفته می‌شوند.
 */

function gvac_clean_title( $t ) {
	$t = preg_replace( '#<span[^>]*>.*?</span>#su', '', (string) $t );
	$t = wp_strip_all_tags( $t );
	$t = preg_replace( '/\s*\(\d+\)\s*$/u', '', $t );
	return trim( html_entity_decode( $t, ENT_QUOTES, 'UTF-8' ) );
}

/** این درخواست می‌تواند فهرست کامل را به‌روز کند؟ (مدیر کامل، بدون پیش‌نمایش و بدون محدودیت) */
function gvac_can_snapshot() {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || gvac_preview_for( get_current_user_id() ) ) {
		return false;
	}
	return gvac_count_rules( gvac_effective_rules( get_current_user_id() ) ) === 0;
}

/* ---------------------------- منوها ---------------------------- */

add_action( 'admin_menu', 'gvac_snapshot_menus', 9998 );
function gvac_snapshot_menus() {
	global $menu, $submenu;
	if ( empty( $menu ) || ! gvac_can_snapshot() ) {
		return;
	}
	$list = array();
	foreach ( $menu as $m ) {
		if ( empty( $m[2] ) || 0 === strpos( $m[2], 'separator' ) ) {
			continue;
		}
		$slug  = $m[2];
		$title = gvac_clean_title( isset( $m[0] ) ? $m[0] : '' );
		if ( '' === $title ) {
			continue;
		}
		$icon = isset( $m[6] ) ? (string) $m[6] : '';
		if ( 0 === strpos( $icon, 'data:' ) ) {
			$icon = ''; // آیکن‌های base64 را ذخیره نمی‌کنیم
		}
		$kids = array();
		if ( ! empty( $submenu[ $slug ] ) ) {
			foreach ( $submenu[ $slug ] as $s ) {
				if ( empty( $s[2] ) ) {
					continue;
				}
				$st = gvac_clean_title( $s[0] );
				if ( '' !== $st ) {
					$kids[] = array( 'key' => $slug . '|' . $s[2], 'slug' => $s[2], 'title' => $st );
				}
			}
		}
		$list[] = array( 'key' => $slug, 'slug' => $slug, 'title' => $title, 'icon' => $icon, 'children' => $kids );
	}
	if ( get_option( 'gvac_cat_menus' ) !== $list ) {
		update_option( 'gvac_cat_menus', $list, false );
	}
}

function gvac_catalog_menus() {
	$l = get_option( 'gvac_cat_menus', array() );
	return is_array( $l ) ? $l : array();
}

/* -------------------------- نوار ادمین -------------------------- */

add_action( 'admin_bar_menu', 'gvac_snapshot_adminbar', 9998 );
function gvac_snapshot_adminbar( $bar ) {
	if ( ! gvac_can_snapshot() ) {
		return;
	}
	$list = array();
	foreach ( $bar->get_nodes() as $n ) {
		if ( in_array( $n->id, array( 'top-secondary', 'menu-toggle', 'gvac-preview' ), true ) || 0 === strpos( $n->id, 'gvac-' ) ) {
			continue;
		}
		$t = gvac_clean_title( (string) $n->title );
		if ( '' === $t ) {
			$t = $n->id;
		}
		$list[ $n->id ] = array( 'id' => $n->id, 'title' => mb_substr( $t, 0, 60 ), 'parent' => (string) $n->parent );
	}
	$old = get_option( 'gvac_cat_adminbar', array() );
	if ( is_array( $old ) ) {
		$list = $list + $old; // موارد قبلاً دیده‌شده (مثلاً فقط در سایت) حفظ شوند
	}
	if ( $old !== $list ) {
		update_option( 'gvac_cat_adminbar', $list, false );
	}
}

function gvac_catalog_adminbar() {
	$l = get_option( 'gvac_cat_adminbar', array() );
	return is_array( $l ) ? array_values( $l ) : array();
}

/* ------------------------ ویجت‌های پیشخوان ------------------------ */

function gvac_core_widgets() {
	return array(
		'welcome_panel'        => 'پنل خوش‌آمدگویی',
		'dashboard_site_health' => 'وضعیت سلامت سایت',
		'dashboard_right_now'  => 'در یک نگاه',
		'dashboard_activity'   => 'فعالیت',
		'dashboard_quick_press' => 'پیش‌نویس سریع',
		'dashboard_primary'    => 'رویدادها و اخبار وردپرس',
	);
}

function gvac_catalog_widgets() {
	$out = array();
	foreach ( gvac_core_widgets() as $id => $t ) {
		$out[ $id ] = $t;
	}
	$learned = get_option( 'gvac_cat_widgets', array() );
	if ( is_array( $learned ) ) {
		foreach ( $learned as $id => $t ) {
			if ( ! isset( $out[ $id ] ) ) {
				$out[ $id ] = $t;
			}
		}
	}
	$res = array();
	foreach ( $out as $id => $t ) {
		$res[] = array( 'id' => $id, 'title' => $t );
	}
	return $res;
}

add_action( 'wp_dashboard_setup', 'gvac_learn_widgets', 9998 );
function gvac_learn_widgets() {
	global $wp_meta_boxes;
	if ( ! gvac_can_snapshot() || empty( $wp_meta_boxes['dashboard'] ) ) {
		return;
	}
	$found = array();
	foreach ( $wp_meta_boxes['dashboard'] as $ctx ) {
		foreach ( (array) $ctx as $prio ) {
			foreach ( (array) $prio as $id => $box ) {
				if ( $box && ! empty( $box['title'] ) ) {
					$found[ $id ] = mb_substr( gvac_clean_title( $box['title'] ), 0, 80 );
				}
			}
		}
	}
	$old = get_option( 'gvac_cat_widgets', array() );
	$new = $found + ( is_array( $old ) ? $old : array() );
	if ( $new !== $old ) {
		update_option( 'gvac_cat_widgets', $new, false );
	}
}

/* ---------------------------- متاباکس‌ها ---------------------------- */

function gvac_core_metaboxes() {
	$common = array(
		'postcustom'        => 'زمینه‌های سفارشی',
		'commentstatusdiv'  => 'گفتگو (اجازه‌ی دیدگاه)',
		'commentsdiv'       => 'دیدگاه‌ها',
		'slugdiv'           => 'نامک',
		'authordiv'         => 'نویسنده',
		'postimagediv'      => 'تصویر شاخص',
		'revisionsdiv'      => 'نسخه‌ها',
	);
	return array(
		'post' => $common + array(
			'postexcerpt'    => 'چکیده',
			'trackbacksdiv'  => 'ارسال بک‌لینک',
			'tagsdiv-post_tag' => 'برچسب‌ها',
			'categorydiv'    => 'دسته‌ها',
			'formatdiv'      => 'فرمت نوشته',
		),
		'page' => $common + array(
			'pageparentdiv'  => 'ویژگی‌های برگه (والد و قالب)',
		),
	);
}

function gvac_catalog_metaboxes() {
	$all = gvac_core_metaboxes();
	$learned = get_option( 'gvac_cat_metaboxes', array() );
	if ( is_array( $learned ) ) {
		foreach ( $learned as $pt => $boxes ) {
			$all[ $pt ] = ( isset( $all[ $pt ] ) ? $all[ $pt ] : array() ) + (array) $boxes;
		}
	}
	$res = array();
	foreach ( $all as $pt => $boxes ) {
		$o = get_post_type_object( $pt );
		$res[] = array(
			'post_type' => $pt,
			'label'     => $o ? $o->labels->name : $pt,
			'boxes'     => array_map( function ( $id, $t ) use ( $pt ) {
				return array( 'key' => $pt . ':' . $id, 'id' => $id, 'title' => $t );
			}, array_keys( $boxes ), array_values( $boxes ) ),
		);
	}
	return $res;
}

add_action( 'do_meta_boxes', 'gvac_learn_metaboxes', 9998, 2 );
function gvac_learn_metaboxes( $post_type, $context = '' ) {
	global $wp_meta_boxes;
	if ( 'side' !== $context || ! gvac_can_snapshot() || empty( $wp_meta_boxes[ $post_type ] ) ) {
		return;
	}
	$found = array();
	foreach ( $wp_meta_boxes[ $post_type ] as $ctx ) {
		foreach ( (array) $ctx as $prio ) {
			foreach ( (array) $prio as $id => $box ) {
				if ( $box && ! empty( $box['title'] ) ) {
					$found[ $id ] = mb_substr( gvac_clean_title( $box['title'] ), 0, 80 );
				}
			}
		}
	}
	$old = get_option( 'gvac_cat_metaboxes', array() );
	$old = is_array( $old ) ? $old : array();
	$new = $old;
	$new[ $post_type ] = $found + ( isset( $old[ $post_type ] ) ? $old[ $post_type ] : array() );
	if ( $new !== $old ) {
		update_option( 'gvac_cat_metaboxes', $new, false );
	}
}

/**
 * درخواست «یادگیری»: با ?gvac_learn=1 صفحه فقط فهرست را ذخیره می‌کند و تمام می‌شود.
 * دکمه‌ی «اسکن» در پنل این آدرس‌ها را پشت‌سرهم باز می‌کند.
 */
add_action( 'wp_dashboard_setup', function () {
	if ( isset( $_GET['gvac_learn'] ) && gvac_can_snapshot() ) { // phpcs:ignore
		wp_die( 'ok', '', array( 'response' => 200 ) );
	}
}, 9999 );
add_action( 'do_meta_boxes', function ( $post_type, $context ) {
	if ( 'side' === $context && isset( $_GET['gvac_learn'] ) && gvac_can_snapshot() ) { // phpcs:ignore
		wp_die( 'ok', '', array( 'response' => 200 ) );
	}
}, 9999, 2 );

/* ---------------------------- قابلیت‌ها ---------------------------- */

function gvac_cap_labels() {
	return array(
		'read' => 'ورود و مشاهده‌ی پیشخوان', 'edit_dashboard' => 'ویرایش پیشخوان', 'upload_files' => 'آپلود فایل',
		'manage_options' => 'مدیریت تنظیمات سایت', 'manage_categories' => 'مدیریت دسته‌ها', 'manage_links' => 'مدیریت پیوندها',
		'moderate_comments' => 'مدیریت دیدگاه‌ها', 'import' => 'درون‌ریزی', 'export' => 'برون‌بری', 'unfiltered_html' => 'درج HTML بدون فیلتر',
		'unfiltered_upload' => 'آپلود فایل بدون محدودیت نوع', 'update_core' => 'به‌روزرسانی وردپرس', 'edit_files' => 'ویرایش فایل‌ها',
		'list_users' => 'مشاهده‌ی لیست کاربران', 'create_users' => 'ساخت کاربر', 'edit_users' => 'ویرایش کاربران', 'delete_users' => 'حذف کاربران',
		'promote_users' => 'تغییر نقش کاربران', 'remove_users' => 'حذف کاربر از سایت', 'add_users' => 'افزودن کاربر',
		'activate_plugins' => 'فعال/غیرفعال کردن افزونه', 'install_plugins' => 'نصب افزونه', 'edit_plugins' => 'ویرایش کد افزونه',
		'update_plugins' => 'به‌روزرسانی افزونه', 'delete_plugins' => 'حذف افزونه', 'switch_themes' => 'تعویض قالب',
		'edit_themes' => 'ویرایش کد قالب', 'install_themes' => 'نصب قالب', 'update_themes' => 'به‌روزرسانی قالب',
		'delete_themes' => 'حذف قالب', 'edit_theme_options' => 'تنظیمات ظاهر (منو، ابزارک)', 'customize' => 'سفارشی‌ساز',
		'manage_woocommerce' => 'مدیریت ووکامرس', 'view_woocommerce_reports' => 'گزارش‌های ووکامرس',
		'gvwr_submit' => 'ثبت گزارش کار', 'gvwr_manage' => 'مدیریت گزارش کار', 'gvwr_view_client' => 'مشاهده‌ی گزارش کارفرما',
		'gvac_manage' => 'مدیریت سطح دسترسی (این ماژول)',
	);
}

function gvac_dangerous_caps() {
	return array( 'manage_options', 'activate_plugins', 'install_plugins', 'edit_plugins', 'delete_plugins', 'update_plugins', 'edit_themes', 'install_themes', 'delete_themes', 'switch_themes', 'edit_users', 'create_users', 'delete_users', 'promote_users', 'remove_users', 'update_core', 'unfiltered_html', 'edit_files', 'gvac_manage' );
}

/** فهرست گروه‌بندی‌شده‌ی همه‌ی قابلیت‌های سایت */
function gvac_cap_catalog() {
	global $wp_roles;
	$labels = gvac_cap_labels();
	$danger = gvac_dangerous_caps();
	$groups = array();
	$seen   = array();

	$add = function ( $gid, $glabel, $cap ) use ( &$groups, &$seen, $labels, $danger ) {
		if ( isset( $seen[ $cap ] ) || '' === $cap || 'do_not_allow' === $cap || 0 === strpos( $cap, 'level_' ) ) {
			return;
		}
		$seen[ $cap ] = 1;
		if ( ! isset( $groups[ $gid ] ) ) {
			$groups[ $gid ] = array( 'id' => $gid, 'label' => $glabel, 'caps' => array() );
		}
		$groups[ $gid ]['caps'][] = array( 'cap' => $cap, 'label' => isset( $labels[ $cap ] ) ? $labels[ $cap ] : '', 'danger' => in_array( $cap, $danger, true ) );
	};

	$fixed = array(
		'general' => array( 'عمومی', array( 'read', 'edit_dashboard', 'upload_files', 'moderate_comments', 'manage_categories', 'manage_links', 'import', 'export', 'unfiltered_html', 'unfiltered_upload' ) ),
		'users'   => array( 'کاربران', array( 'list_users', 'create_users', 'add_users', 'edit_users', 'delete_users', 'promote_users', 'remove_users' ) ),
		'plugins' => array( 'افزونه‌ها', array( 'activate_plugins', 'install_plugins', 'edit_plugins', 'update_plugins', 'delete_plugins' ) ),
		'themes'  => array( 'قالب و ظاهر', array( 'switch_themes', 'edit_themes', 'install_themes', 'update_themes', 'delete_themes', 'edit_theme_options', 'customize' ) ),
		'system'  => array( 'تنظیمات و سیستم', array( 'manage_options', 'update_core', 'edit_files' ) ),
	);
	foreach ( $fixed as $gid => $g ) {
		foreach ( $g[1] as $cap ) {
			$add( $gid, $g[0], $cap );
		}
	}

	foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pt ) {
		foreach ( array_unique( array_values( (array) $pt->cap ) ) as $cap ) {
			if ( in_array( $cap, array( 'read', 'edit_post', 'read_post', 'delete_post' ), true ) ) {
				continue;
			}
			$add( 'pt_' . $pt->name, 'محتوا: ' . $pt->labels->name, $cap );
		}
	}
	foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tx ) {
		foreach ( array_unique( array_values( (array) $tx->cap ) ) as $cap ) {
			$add( 'tx_' . $tx->name, 'طبقه‌بندی: ' . $tx->labels->name, $cap );
		}
	}

	$rest = array();
	foreach ( (array) $wp_roles->roles as $r ) {
		foreach ( array_keys( (array) $r['capabilities'] ) as $cap ) {
			$rest[] = $cap;
		}
	}
	$rest = array_unique( array_merge( $rest, array_keys( $labels ) ) );
	sort( $rest );
	foreach ( $rest as $cap ) {
		if ( 0 === strpos( $cap, 'gvwr_' ) || 0 === strpos( $cap, 'gvac_' ) ) {
			$add( 'groot', 'گروت ویژن', $cap );
		} elseif ( false !== strpos( $cap, 'woocommerce' ) || false !== strpos( $cap, 'shop_' ) ) {
			$add( 'woo', 'ووکامرس', $cap );
		} else {
			$add( 'other', 'سایر', $cap );
		}
	}
	return array_values( $groups );
}
