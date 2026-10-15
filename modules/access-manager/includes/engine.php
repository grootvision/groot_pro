<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ================================================================== */
/* ۱) قابلیت‌ها (Capabilities)                                         */
/* ================================================================== */

/** قابلیت‌های واقعی یک سوژه‌ی پیش‌نمایش (نقش یا کاربر) */
function gvac_preview_caps( $prev ) {
	$caps = array();
	if ( 'role' === $prev['type'] ) {
		$r = get_role( $prev['id'] );
		if ( $r ) {
			$caps           = (array) $r->capabilities;
			$caps[ $r->name ] = true;
		}
		return $caps;
	}
	$u = get_userdata( (int) $prev['id'] );
	if ( $u ) {
		foreach ( (array) $u->roles as $rn ) {
			$r = get_role( $rn );
			if ( $r ) {
				$caps = array_merge( $caps, (array) $r->capabilities );
			}
			$caps[ $rn ] = true;
		}
		foreach ( (array) $u->caps as $c => $v ) {
			$caps[ $c ] = $v;
		}
	}
	return $caps;
}

add_filter( 'user_has_cap', 'gvac_filter_user_caps', 99, 4 );
function gvac_filter_user_caps( $allcaps, $caps, $args, $user ) {
	if ( ! $user || empty( $user->ID ) ) {
		return $allcaps;
	}
	$uid  = (int) $user->ID;
	$off  = gvac_engine_off();
	$prev = $off ? false : gvac_preview_for( $uid );

	if ( $prev ) {
		$allcaps = gvac_preview_caps( $prev );
	}
	if ( ! empty( $allcaps['manage_options'] ) ) {
		$allcaps['gvac_manage'] = true;
	}
	if ( $off || ( ! $prev && gvac_is_immune( $uid ) ) ) {
		return $allcaps;
	}
	$rules = gvac_effective_rules( $uid );
	foreach ( (array) $rules['caps'] as $cap => $v ) {
		$allcaps[ $cap ] = ( 'grant' === $v );
	}
	return $allcaps;
}

add_filter( 'map_meta_cap', 'gvac_filter_map_meta_cap', 99, 4 );
function gvac_filter_map_meta_cap( $caps, $cap, $user_id, $args ) {
	if ( gvac_engine_off() || ! $user_id ) {
		return $caps;
	}
	static $content_caps = array(
		'edit_post' => 'edit', 'edit_page' => 'edit', 'delete_post' => 'delete', 'delete_page' => 'delete', 'read_post' => 'view', 'read_page' => 'view',
	);
	if ( isset( $content_caps[ $cap ] ) && ! empty( $args[0] ) ) {
		$pid   = (int) $args[0];
		$rules = gvac_effective_rules( $user_id );
		if ( ! empty( $rules['content'][ $pid ] ) && in_array( $content_caps[ $cap ], $rules['content'][ $pid ], true ) ) {
			return array( 'do_not_allow' );
		}
	}
	if ( in_array( $cap, array( 'edit_user', 'delete_user', 'remove_user', 'promote_user' ), true ) && ! empty( $args[0] ) && function_exists( 'gvac_stealth_blocks' ) ) {
		if ( gvac_stealth_blocks( (int) $user_id, (int) $args[0] ) ) {
			return array( 'do_not_allow' );
		}
	}
	return $caps;
}

/* ================================================================== */
/* ۲) منوها                                                            */
/* ================================================================== */

function gvac_parse_slug( $slug ) {
	$q = array();
	if ( false !== strpos( $slug, '.php' ) ) {
		$parts = explode( '?', $slug, 2 );
		$file  = $parts[0];
		if ( isset( $parts[1] ) ) {
			parse_str( html_entity_decode( $parts[1] ), $q );
		}
	} else {
		$file = 'admin.php';
		$q    = array( 'page' => $slug );
	}
	unset( $q['return'], $q['autofocus'] );
	if ( in_array( $file, array( 'edit.php', 'post-new.php' ), true ) && ! isset( $q['post_type'] ) ) {
		$q['post_type'] = 'post';
	}
	return array( $file, $q );
}

add_action( 'admin_menu', 'gvac_apply_menu_rules', 9999 );
function gvac_apply_menu_rules() {
	global $menu, $submenu;
	$GLOBALS['gvac_menu_entries'] = array();
	$uid = get_current_user_id();
	if ( ! $uid || empty( $menu ) || gvac_engine_off() ) {
		return;
	}
	$rules  = gvac_effective_rules( $uid );
	$mr     = $rules['menu'];
	$stealth_hide = function_exists( 'gvac_plugin_hidden_for' ) && gvac_plugin_hidden_for( $uid );
	if ( ! $mr && ! $stealth_hide ) {
		return;
	}
	$badge   = (bool) gvac_opt( 'show_lock_badge' );
	$entries = array();

	foreach ( $menu as $mk => $m ) {
		if ( empty( $m[2] ) || 0 === strpos( $m[2], 'separator' ) ) {
			continue;
		}
		$slug  = $m[2];
		$ts    = isset( $mr[ $slug ] ) ? $mr[ $slug ] : '';
		$stl   = false;
		if ( $stealth_hide && gvac_is_plugin_slug( $slug ) ) {
			$ts  = 'hide';
			$stl = true;
		}
		$kids = isset( $submenu[ $slug ] ) ? $submenu[ $slug ] : array();
		$kept = 0;
		foreach ( $kids as $sk => $s ) {
			if ( empty( $s[2] ) ) {
				continue;
			}
			$st = $ts ? $ts : ( isset( $mr[ $slug . '|' . $s[2] ] ) ? $mr[ $slug . '|' . $s[2] ] : '' );
			$sl = $stl;
			if ( $stealth_hide && gvac_is_plugin_slug( $s[2] ) ) {
				$st = 'hide';
				$sl = true;
			}
			if ( $st ) {
				$entries[] = array( 'slug' => $s[2], 'state' => $st, 'title' => gvac_clean_title( $s[0] ), 'stealth' => $sl );
			}
			if ( 'hide' === $st ) {
				unset( $submenu[ $slug ][ $sk ] );
			} else {
				$kept++;
				if ( 'lock' === $st && $badge ) {
					$submenu[ $slug ][ $sk ][0] .= ' <span class="gvac-lock" aria-hidden="true"></span>';
				}
			}
		}
		if ( $ts ) {
			$entries[] = array( 'slug' => $slug, 'state' => $ts, 'title' => gvac_clean_title( $m[0] ), 'stealth' => $stl );
		}
		if ( 'hide' === $ts || ( $kids && ! $kept ) ) {
			unset( $menu[ $mk ] );
		} elseif ( 'lock' === $ts ) {
			if ( $badge ) {
				$menu[ $mk ][0] .= ' <span class="gvac-lock" aria-hidden="true"></span>';
			}
			$menu[ $mk ][4] = ( isset( $menu[ $mk ][4] ) ? $menu[ $mk ][4] : '' ) . ' gvac-locked';
		}
		if ( isset( $submenu[ $slug ] ) ) {
			$submenu[ $slug ] = array_values( $submenu[ $slug ] ) ? array_values( $submenu[ $slug ] ) : array();
			if ( ! $submenu[ $slug ] ) {
				unset( $submenu[ $slug ] );
			}
		}
	}
	$GLOBALS['gvac_menu_entries'] = $entries;
}

/** درخواست فعلی را به (فایل + پارامترها) نرمال می‌کند تا با آیتم‌های منو مقایسه شود */
function gvac_request_signature() {
	global $pagenow, $plugin_page;
	$q = array();
	foreach ( $_GET as $k => $v ) { // phpcs:ignore
		if ( is_scalar( $v ) ) {
			$q[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( (string) $v ) );
		}
	}
	if ( ! empty( $plugin_page ) ) {
		return array( 'admin.php', array( 'page' => $plugin_page ) );
	}
	$file = $pagenow;
	switch ( $pagenow ) {
		case 'post.php':
			$pid = isset( $q['post'] ) ? (int) $q['post'] : ( isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0 ); // phpcs:ignore
			$pt  = $pid ? get_post_type( $pid ) : 'post';
			$file = 'edit.php';
			$q    = array( 'post_type' => $pt ? $pt : 'post' );
			break;
		case 'edit.php':
		case 'post-new.php':
			if ( empty( $q['post_type'] ) ) {
				$q['post_type'] = 'post';
			}
			break;
		case 'term.php':
		case 'edit-tags.php':
			$file = 'edit-tags.php';
			if ( ! empty( $q['taxonomy'] ) && empty( $q['post_type'] ) ) {
				$tx = get_taxonomy( $q['taxonomy'] );
				if ( $tx && ! empty( $tx->object_type[0] ) ) {
					$q['post_type'] = $tx->object_type[0];
				}
			}
			break;
		case 'user-edit.php':
			$file = 'users.php';
			break;
		case 'theme-install.php':
			$file = 'themes.php';
			break;
	}
	return array( $file, $q );
}

function gvac_entry_matches( $slug, $sig ) {
	list( $file, $q ) = gvac_parse_slug( $slug );
	if ( $file !== $sig[0] ) {
		return false;
	}
	foreach ( $q as $k => $v ) {
		if ( ! isset( $sig[1][ $k ] ) || (string) $sig[1][ $k ] !== (string) $v ) {
			// edit-tags.php?taxonomy=category داخل نقش post_type را اختیاری می‌گیرد
			if ( 'post_type' === $k && 'edit-tags.php' === $file && 'post' === $v && empty( $sig[1]['post_type'] ) ) {
				continue;
			}
			return false;
		}
	}
	return true;
}

/* ================================================================== */
/* ۳) نگهبان درخواست (ادمین)                                            */
/* ================================================================== */

add_action( 'admin_init', 'gvac_guard_admin', 1 );
function gvac_guard_admin() {
	global $pagenow;
	if ( gvac_engine_off() || wp_doing_ajax() || wp_doing_cron() || ! is_user_logged_in() ) {
		return;
	}
	if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'load-scripts.php', 'load-styles.php', 'upgrade.php' ), true ) ) {
		return;
	}
	$uid   = get_current_user_id();
	$rules = gvac_effective_rules( $uid );

	// بستن کامل wp-admin
	if ( ! empty( $rules['behavior']['block_admin'] ) ) {
		gvac_log( 'denied', 'admin.block', 'ورود به پیشخوان', wp_get_current_user()->user_login );
		$to = ! empty( $rules['behavior']['block_redirect'] ) ? $rules['behavior']['block_redirect'] : home_url( '/' );
		wp_redirect( $to ); // phpcs:ignore
		exit;
	}

	$entries = isset( $GLOBALS['gvac_menu_entries'] ) ? $GLOBALS['gvac_menu_entries'] : array();
	if ( $entries ) {
		$sig = gvac_request_signature();
		foreach ( $entries as $e ) {
			if ( gvac_entry_matches( $e['slug'], $sig ) ) {
				gvac_deny( $e['state'], $e['title'], ! empty( $e['stealth'] ) );
			}
		}
	}
	gvac_check_url_rules( $rules );
}

/** تطبیق قوانین آدرس (برای ادمین و سایت) */
function gvac_check_url_rules( $rules ) {
	if ( empty( $rules['urls'] ) ) {
		return;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore
	$base = wp_parse_url( home_url(), PHP_URL_PATH );
	$rel  = ( $base && '/' !== $base && 0 === strpos( $uri, $base ) ) ? substr( $uri, strlen( rtrim( $base, '/' ) ) ) : $uri;
	foreach ( $rules['urls'] as $row ) {
		$p = $row['pattern'];
		$re = preg_quote( $p, '#' );
		$re = str_replace( '\*', '.*', $re );
		$re = ( 0 === strpos( $p, '/' ) ) ? '#^' . $re . '#iu' : '#' . $re . '#iu';
		if ( preg_match( $re, $rel ) ) {
			gvac_deny( $row['mode'], $p, false, is_admin() );
		}
	}
}

/* ================================================================== */
/* ۴) رد دسترسی + صفحه‌ی «دسترسی بسته است»                              */
/* ================================================================== */

function gvac_deny( $state, $label = '', $stealth = false, $in_admin = null ) {
	$in_admin = null === $in_admin ? is_admin() : $in_admin;
	$user     = wp_get_current_user();
	$rules    = gvac_effective_rules( $user->ID );
	$b        = $rules['behavior'];

	if ( gvac_opt( 'log_denied' ) ) {
		$key = 'gvac_t_' . md5( $user->ID . '|' . ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ) ); // phpcs:ignore
		if ( ! get_transient( $key ) ) {
			set_transient( $key, 1, 60 );
			gvac_log( 'denied', ( 'hide' === $state ? 'hidden' : 'locked' ), $label, $user->user_login );
		}
	}

	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		wp_send_json_error( array( 'message' => gvac_opt( 'deny_title' ) ), 403 );
	}
	if ( ! empty( $b['deny_redirect'] ) ) {
		wp_redirect( $b['deny_redirect'] ); // phpcs:ignore
		exit;
	}

	$mode = ( 'hide' === $state ) ? ( $stealth ? 'notfound' : gvac_opt( 'hidden_response' ) ) : 'denied';

	if ( 'hide' === $state && 'dashboard' === $mode && $in_admin ) {
		global $pagenow;
		if ( 'index.php' !== $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
		$mode = 'denied';
	}
	if ( 'notfound' === $mode ) {
		if ( $in_admin ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ), '', array( 'response' => 403 ) );
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		include get_query_template( '404' );
		exit;
	}
	gvac_render_denied( $label, ! empty( $b['deny_message'] ) ? $b['deny_message'] : gvac_opt( 'deny_message' ) );
}

function gvac_render_denied( $label, $message ) {
	nocache_headers();
	status_header( 403 );
	header( 'Content-Type: text/html; charset=utf-8' );
	$brand = function_exists( 'gv_wl_get_settings' ) ? gv_wl_get_settings() : array();
	$bn    = ! empty( $brand['brand_name'] ) ? $brand['brand_name'] : get_bloginfo( 'name' );
	$prev  = gvac_preview_for( get_current_user_id() );
	$back  = is_admin() ? admin_url() : home_url( '/' );
	?>
<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( gvac_opt( 'deny_title' ) ); ?></title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f1ec;color:#1b2333;font-family:Vazirmatn,IRANSans,Vazir,Tahoma,system-ui,sans-serif;line-height:1.9}
.c{width:min(520px,92vw);background:#fff;border:1px solid #e4dfd3;border-radius:18px;padding:36px 32px;text-align:center;box-shadow:0 20px 50px rgba(27,35,51,.08)}
.k{width:72px;height:72px;margin:0 auto 18px;border-radius:50%;background:#fff4dc;color:#b45309;display:grid;place-items:center}
h1{font-size:22px;margin:0 0 8px}p{margin:0 0 6px;color:#4b5565}.l{display:inline-block;margin:8px 0 14px;padding:2px 12px;border-radius:99px;background:#f3f1ec;font-size:12.5px;color:#6b7280}
.a{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:18px}.a a,.a button{font:inherit;font-size:14px;padding:9px 18px;border-radius:10px;border:1px solid #d9d3c4;background:#fff;color:#1b2333;text-decoration:none;cursor:pointer}
.a .p{background:#1b2333;border-color:#1b2333;color:#fff}.f{margin-top:22px;font-size:12px;color:#9ca3af}a:focus-visible,button:focus-visible{outline:3px solid #f59e0b;outline-offset:2px}
</style></head><body><main class="c" role="alert">
<div class="k"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></div>
<h1><?php echo esc_html( gvac_opt( 'deny_title' ) ); ?></h1>
<p><?php echo nl2br( esc_html( $message ) ); ?></p>
<?php if ( $label ) : ?><span class="l"><?php echo esc_html( $label ); ?></span><?php endif; ?>
<div class="a"><button type="button" onclick="history.length>1?history.back():location.href='<?php echo esc_js( $back ); ?>'">بازگشت</button><a class="p" href="<?php echo esc_url( $back ); ?>"><?php echo is_admin() ? 'رفتن به پیشخوان' : 'صفحه‌ی اصلی'; ?></a>
<?php if ( $prev ) : ?><a href="<?php echo esc_url( gvac_exit_preview_url() ); ?>">خروج از پیش‌نمایش</a><?php endif; ?></div>
<div class="f"><?php echo esc_html( $bn ); ?></div></main></body></html>
	<?php
	exit;
}

/* ================================================================== */
/* ۵) نوار ادمین، ویجت‌ها، متاباکس‌ها                                  */
/* ================================================================== */

add_action( 'admin_bar_menu', 'gvac_apply_adminbar', 9999 );
function gvac_apply_adminbar( $bar ) {
	$uid = get_current_user_id();
	if ( ! $uid || gvac_engine_off() ) {
		return;
	}
	$rules = gvac_effective_rules( $uid );
	foreach ( (array) $rules['adminbar'] as $id => $state ) {
		$node = $bar->get_node( $id );
		if ( ! $node ) {
			continue;
		}
		if ( 'hide' === $state ) {
			$kill = array( $id );
			$grew = true;
			while ( $grew ) { // فرزندان را هم حذف کن تا به ریشه نچسبند
				$grew = false;
				foreach ( $bar->get_nodes() as $n ) {
					if ( $n->parent && in_array( $n->parent, $kill, true ) && ! in_array( $n->id, $kill, true ) ) {
						$kill[] = $n->id;
						$grew   = true;
					}
				}
			}
			foreach ( $kill as $k ) {
				$bar->remove_node( $k );
			}
		} elseif ( 'lock' === $state ) {
			$bar->add_node( array(
				'id'    => $id,
				'title' => $node->title . ' <span class="gvac-lock" aria-hidden="true"></span>',
				'href'  => false,
				'meta'  => array( 'class' => 'gvac-locked' ),
			) );
		}
	}
}

add_action( 'wp_dashboard_setup', 'gvac_apply_widgets', 10000 );
function gvac_apply_widgets() {
	$uid = get_current_user_id();
	if ( ! $uid || gvac_engine_off() ) {
		return;
	}
	$rules = gvac_effective_rules( $uid );
	foreach ( (array) $rules['widgets'] as $id => $state ) {
		if ( 'hide' !== $state ) {
			continue;
		}
		if ( 'welcome_panel' === $id ) {
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
			continue;
		}
		foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $ctx ) {
			remove_meta_box( $id, 'dashboard', $ctx );
		}
	}
}

add_action( 'do_meta_boxes', 'gvac_apply_metaboxes', 10000, 2 );
function gvac_apply_metaboxes( $post_type, $context ) {
	$uid = get_current_user_id();
	if ( ! $uid || gvac_engine_off() ) {
		return;
	}
	$rules = gvac_effective_rules( $uid );
	foreach ( (array) $rules['metaboxes'] as $key => $state ) {
		if ( 'hide' !== $state || false === strpos( $key, ':' ) ) {
			continue;
		}
		list( $pt, $id ) = explode( ':', $key, 2 );
		if ( $pt === $post_type ) {
			remove_meta_box( $id, $post_type, $context );
		}
	}
}

/* ================================================================== */
/* ۶) رفتارها: ریدایرکت ورود/خروج، نوار ادمین                          */
/* ================================================================== */

add_filter( 'login_redirect', function ( $to, $req, $user ) {
	if ( gvac_engine_off() || ! $user || is_wp_error( $user ) || empty( $user->ID ) ) {
		return $to;
	}
	$r = gvac_effective_rules( $user->ID );
	return ! empty( $r['behavior']['login_redirect'] ) ? $r['behavior']['login_redirect'] : $to;
}, 99, 3 );

add_filter( 'logout_redirect', function ( $to, $req, $user ) {
	if ( gvac_engine_off() || ! $user || is_wp_error( $user ) || empty( $user->ID ) ) {
		return $to;
	}
	$r = gvac_effective_rules( $user->ID );
	return ! empty( $r['behavior']['logout_redirect'] ) ? $r['behavior']['logout_redirect'] : $to;
}, 99, 3 );

add_filter( 'show_admin_bar', function ( $show ) {
	if ( ! $show || gvac_engine_off() || ! is_user_logged_in() ) {
		return $show;
	}
	$r = gvac_effective_rules( get_current_user_id() );
	if ( ! empty( $r['behavior']['hide_adminbar_all'] ) || ( ! is_admin() && ! empty( $r['behavior']['hide_adminbar_front'] ) ) ) {
		return false;
	}
	return $show;
}, 99 );

/* ================================================================== */
/* ۷) سمت سایت: محتوای محدودشده و قوانین آدرس                          */
/* ================================================================== */

add_action( 'template_redirect', function () {
	if ( gvac_engine_off() || ! is_user_logged_in() || wp_doing_ajax() ) {
		return;
	}
	$rules = gvac_effective_rules( get_current_user_id() );
	gvac_check_url_rules( $rules );
	if ( is_singular() && $rules['content'] ) {
		$id = (int) get_queried_object_id();
		if ( $id && ! empty( $rules['content'][ $id ] ) && in_array( 'view', $rules['content'][ $id ], true ) ) {
			gvac_deny( 'lock', get_the_title( $id ), false, false );
		}
	}
}, 1 );

/* ================================================================== */
/* ۸) CSS آیکن قفل در منوی ادمین                                       */
/* ================================================================== */

add_action( 'admin_head', 'gvac_lock_css' );
add_action( 'wp_head', 'gvac_lock_css' );
function gvac_lock_css() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$r   = gvac_effective_rules( get_current_user_id() );
	$css = '';
	if ( $r['menu'] || $r['adminbar'] ) {
		$css .= '.gvac-lock{display:inline-block;width:11px;height:11px;margin-inline-start:6px;vertical-align:-1px;background:currentColor;opacity:.6;-webkit-mask:url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'><path d=\'M17 9V7a5 5 0 0 0-10 0v2H5v12h14V9h-2zM9 7a3 3 0 0 1 6 0v2H9V7z\'/></svg>") center/contain no-repeat;mask:url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'><path d=\'M17 9V7a5 5 0 0 0-10 0v2H5v12h14V9h-2zM9 7a3 3 0 0 1 6 0v2H9V7z\'/></svg>") center/contain no-repeat}#adminmenu li.gvac-locked>a{opacity:.8}#wpadminbar .gvac-locked>.ab-item{cursor:not-allowed}';
	}
	if ( is_admin() && ! empty( $r['behavior']['hide_adminbar_all'] ) ) {
		$css .= '#wpadminbar{display:none!important}html.wp-toolbar{padding-top:0!important}#adminmenuwrap,#adminmenuback{top:0!important}';
	}
	if ( $css ) {
		echo '<style id="gvac-css">' . $css . '</style>'; // phpcs:ignore
	}
}

/* ================================================================== */
/* ۹) پیش‌نمایش «به‌جای کاربر/نقش»                                     */
/* ================================================================== */

function gvac_preview_start( $type, $id ) {
	update_user_meta( get_current_user_id(), 'gvac_preview', array( 'type' => $type, 'id' => $id, 'until' => time() + 30 * MINUTE_IN_SECONDS ) );
	gvac_flush_cache();
}

function gvac_preview_stop( $uid = 0 ) {
	delete_user_meta( $uid ? $uid : get_current_user_id(), 'gvac_preview' );
	gvac_flush_cache();
}

function gvac_exit_preview_url() {
	return add_query_arg( array( 'gvac_exit_preview' => 1, '_wpnonce' => wp_create_nonce( 'gvac_exit' ) ), admin_url( 'index.php' ) );
}

function gvac_preview_label( $prev ) {
	if ( 'role' === $prev['type'] ) {
		$r = get_role( $prev['id'] );
		$n = function_exists( 'gvac_role_name' ) ? gvac_role_name( $prev['id'] ) : $prev['id'];
		return 'نقش «' . $n . '»';
	}
	$u = get_userdata( (int) $prev['id'] );
	return 'کاربر «' . ( $u ? $u->display_name : $prev['id'] ) . '»';
}

add_action( 'init', function () {
	if ( isset( $_GET['gvac_exit_preview'] ) && is_user_logged_in() ) { // phpcs:ignore
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gvac_exit' ) ) {
			gvac_preview_stop();
		}
		wp_safe_redirect( gvac_menu_url( 'rules' ) );
		exit;
	}
}, 1 );

function gvac_preview_banner() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$prev = gvac_preview_for( get_current_user_id() );
	if ( ! $prev ) {
		return;
	}
	echo '<div dir="rtl" style="position:fixed;z-index:999999;bottom:16px;left:50%;transform:translateX(-50%);display:flex;gap:14px;align-items:center;background:#1b2333;color:#fff;padding:10px 18px;border-radius:14px;font:14px/1.6 Vazirmatn,Tahoma,sans-serif;box-shadow:0 12px 32px rgba(0,0,0,.35)">'
		. '<span>پیش‌نمایش به‌جای ' . esc_html( gvac_preview_label( $prev ) ) . ' (نقش‌ها و دسترسی‌های واقعی شما موقتاً کنار رفته)</span>'
		. '<a style="background:#f59e0b;color:#1b2333;padding:5px 14px;border-radius:8px;text-decoration:none;font-weight:700" href="' . esc_url( gvac_exit_preview_url() ) . '">خروج از پیش‌نمایش</a></div>'; // phpcs:ignore
}
add_action( 'admin_footer', 'gvac_preview_banner', 999 );
add_action( 'wp_footer', 'gvac_preview_banner', 999 );

/* ================================================================== */
/* ۱۰) بازیابی اضطراری با کلید                                          */
/* wp-login.php?gvac_recover=KEY  → ۱۵ دقیقه همه‌ی محدودیت‌ها خاموش      */
/* ================================================================== */

add_action( 'init', function () {
	if ( ! isset( $_GET['gvac_recover'] ) ) { // phpcs:ignore
		return;
	}
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	$tkey = 'gvac_rf_' . md5( $ip );
	$fail = (int) get_transient( $tkey );
	if ( $fail >= 5 ) {
		wp_die( 'تلاش‌های ناموفق زیاد است. چند دقیقه بعد دوباره امتحان کنید.', '', array( 'response' => 429 ) );
	}
	$key = (string) gvac_opt( 'recovery_key' );
	$in  = sanitize_text_field( wp_unslash( $_GET['gvac_recover'] ) ); // phpcs:ignore
	if ( '' !== $key && hash_equals( $key, $in ) ) {
		set_transient( 'gvac_recovery_active', 1, 15 * MINUTE_IN_SECONDS );
		gvac_log( 'system', 'recovery', 'خاموش‌شدن موقت محدودیت‌ها با کلید بازیابی', '', 0 );
		wp_safe_redirect( admin_url() );
		exit;
	}
	set_transient( $tkey, $fail + 1, 10 * MINUTE_IN_SECONDS );
	wp_die( 'کلید بازیابی معتبر نیست.', '', array( 'response' => 403 ) );
}, 1 );
