<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_gvac', 'gvac_ajax' );
function gvac_ajax() {
	check_ajax_referer( 'gvac', 'nonce' );
	if ( ! current_user_can( 'gvac_manage' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی لازم را ندارید.' ), 403 );
	}
	$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : ''; // phpcs:ignore
	$fn = 'gvac_op_' . $op;
	if ( ! function_exists( $fn ) ) {
		wp_send_json_error( array( 'message' => 'درخواست نامعتبر.' ), 400 );
	}
	$data = array();
	if ( ! empty( $_POST['data'] ) ) { // phpcs:ignore
		$d = json_decode( wp_unslash( $_POST['data'] ), true ); // phpcs:ignore
		$data = is_array( $d ) ? $d : array();
	}
	$res = $fn( $data );
	if ( is_wp_error( $res ) ) {
		wp_send_json_error( array( 'message' => $res->get_error_message() ) );
	}
	wp_send_json_success( $res );
}

/* ------------------------------------------------------------------ */
/* داشبورد                                                             */
/* ------------------------------------------------------------------ */

function gvac_op_boot( $d ) {
	global $wpdb;
	$me    = wp_get_current_user();
	$roles = gvac_roles_list();
	$all   = gvac_all_rules();
	$act   = 0;
	foreach ( $all['role'] as $r ) {
		$act += gvac_count_rules( wp_parse_args( $r, gvac_empty_rules() ) );
	}
	foreach ( $all['user'] as $r ) {
		$act += gvac_count_rules( wp_parse_args( $r, gvac_empty_rules() ) );
	}
	$lt   = gvac_table( 'log' );
	$from = wp_date( 'Y-m-d 00:00:00', time() - 13 * DAY_IN_SECONDS );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) d, COUNT(*) n FROM $lt WHERE type='denied' AND created_at >= %s GROUP BY DATE(created_at)", $from ), OBJECT_K ); // phpcs:ignore
	$chart = array();
	for ( $i = 13; $i >= 0; $i-- ) {
		$day     = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$chart[] = array( 'd' => $day, 'n' => isset( $rows[ $day ] ) ? (int) $rows[ $day ]->n : 0 );
	}
	$top = $wpdb->get_results( "SELECT details label, COUNT(*) n FROM $lt WHERE type='denied' AND details<>'' GROUP BY details ORDER BY n DESC LIMIT 5" ); // phpcs:ignore
	$denied7 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $lt WHERE type='denied' AND created_at >= %s", wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
	$temp    = count( get_users( array( 'meta_key' => 'gvac_temp', 'fields' => 'ID', 'number' => 500 ) ) ); // phpcs:ignore
	$uc      = count_users();

	$health = array();
	if ( defined( 'GV_AC_DISABLE' ) && GV_AC_DISABLE ) {
		$health[] = array( 'level' => 'warn', 'text' => 'ثابت GV_AC_DISABLE در wp-config.php فعال است و همه‌ی محدودیت‌ها خاموش است.' );
	} elseif ( ! gvac_opt( 'enabled' ) ) {
		$health[] = array( 'level' => 'warn', 'text' => 'کلید اصلی ماژول خاموش است؛ هیچ محدودیتی اعمال نمی‌شود.' );
	}
	if ( get_transient( 'gvac_recovery_active' ) ) {
		$health[] = array( 'level' => 'warn', 'text' => 'حالت بازیابی فعال است و تا چند دقیقه‌ی دیگر محدودیت‌ها خاموش می‌ماند.' );
	}
	foreach ( $roles as $r ) {
		if ( $r['admin'] && ! $r['core'] ) {
			$health[] = array( 'level' => 'warn', 'text' => 'نقش «' . $r['name'] . '» قابلیت «مدیریت تنظیمات» دارد؛ این نقش می‌تواند همین ماژول را هم باز کند.' );
		}
	}
	if ( gvac_stealth_all() && ! gvac_opt( 'hide_plugin' ) ) {
		$health[] = array( 'level' => 'info', 'text' => 'کاربر پنهان دارید ولی خود افزونه هنوز در منو و لیست افزونه‌ها دیده می‌شود؛ از «پنهان‌سازی» آن را هم پنهان کنید.' );
	}
	if ( ! $health ) {
		$health[] = array( 'level' => 'ok', 'text' => 'همه‌چیز عادی است.' );
	}

	return array(
		'me'      => array( 'id' => $me->ID, 'name' => $me->display_name, 'immune' => gvac_is_immune( $me->ID ), 'preview' => (bool) gvac_preview_for( $me->ID ) ),
		'stats'   => array(
			'roles'     => count( $roles ),
			'custom'    => count( array_filter( $roles, function ( $r ) { return ! $r['core']; } ) ),
			'users'     => (int) $uc['total_users'],
			'rules'     => $act,
			'stealth'   => count( gvac_stealth_all() ),
			'denied7'   => $denied7,
			'temp'      => $temp,
		),
		'roles'   => $roles,
		'chart'   => $chart,
		'top'     => $top,
		'recent'  => gvac_op_log_list( array( 'type' => 'change', 'per' => 7 ) )['items'],
		'presets' => array_map( function ( $id, $p ) {
			return array( 'id' => $id, 'name' => $p['name'], 'desc' => $p['desc'], 'exists' => (bool) get_role( 'gvac_' . $id ) );
		}, array_keys( gvac_presets() ), array_values( gvac_presets() ) ),
		'settings' => gvac_settings_for_ui(),
		'health'  => $health,
	);
}

function gvac_settings_for_ui() {
	$s = gvac_opt();
	$s['constant_off'] = defined( 'GV_AC_DISABLE' ) && GV_AC_DISABLE;
	$s['immune']       = array_map( function ( $id ) {
		$u = get_userdata( $id );
		return array( 'id' => $id, 'name' => $u ? $u->display_name : '#' . $id );
	}, gvac_immune_ids() );
	if ( ! gvac_is_immune( get_current_user_id() ) ) {
		$s['recovery_key'] = '';
	}
	$s['recovery_url'] = $s['recovery_key'] ? add_query_arg( 'gvac_recover', $s['recovery_key'], wp_login_url() ) : '';
	return $s;
}

/* ------------------------------------------------------------------ */
/* فهرست‌ها                                                            */
/* ------------------------------------------------------------------ */

function gvac_op_catalog( $d ) {
	return array(
		'menus'     => gvac_catalog_menus(),
		'adminbar'  => gvac_catalog_adminbar(),
		'widgets'   => gvac_catalog_widgets(),
		'metaboxes' => gvac_catalog_metaboxes(),
		'caps'      => gvac_cap_catalog(),
		'self_slug' => GVAC_MENU_SLUG,
	);
}

function gvac_op_scan_targets( $d ) {
	$urls = array( admin_url( 'index.php?gvac_learn=1' ), home_url( '/' ) );
	foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pt ) {
		if ( 'attachment' === $pt->name ) {
			continue;
		}
		$p = get_posts( array( 'post_type' => $pt->name, 'numberposts' => 1, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'fields' => 'ids', 'suppress_filters' => true ) );
		$urls[] = $p ? admin_url( 'post.php?post=' . (int) $p[0] . '&action=edit&gvac_learn=1' ) : admin_url( 'post-new.php?post_type=' . $pt->name . '&gvac_learn=1' );
	}
	return array( 'urls' => $urls );
}

function gvac_op_search_posts( $d ) {
	$q   = isset( $d['q'] ) ? sanitize_text_field( $d['q'] ) : '';
	$arg = array( 'post_type' => 'any', 'numberposts' => 15, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'suppress_filters' => true );
	if ( ctype_digit( $q ) ) {
		$p = get_post( (int) $q );
		$posts = $p ? array( $p ) : array();
	} else {
		$arg['s'] = $q;
		$posts    = get_posts( $arg );
	}
	return array_map( function ( $p ) {
		$o = get_post_type_object( $p->post_type );
		return array( 'id' => $p->ID, 'title' => get_the_title( $p ) ? get_the_title( $p ) : '(بدون عنوان) #' . $p->ID, 'type' => $o ? $o->labels->singular_name : $p->post_type );
	}, $posts );
}

/* ------------------------------------------------------------------ */
/* نقش‌ها                                                              */
/* ------------------------------------------------------------------ */

function gvac_op_roles( $d ) {
	return array( 'roles' => gvac_roles_list() );
}

function gvac_op_role_get( $d ) {
	$slug = isset( $d['slug'] ) ? sanitize_key( $d['slug'] ) : '';
	$r    = get_role( $slug );
	if ( ! $r ) {
		return new WP_Error( 'role', 'نقش پیدا نشد.' );
	}
	return array( 'slug' => $slug, 'name' => gvac_role_name( $slug ), 'caps' => array_keys( array_filter( (array) $r->capabilities ) ), 'locked' => 'administrator' === $slug );
}

function gvac_op_role_save( $d ) {
	$name = isset( $d['name'] ) ? $d['name'] : '';
	$slug = isset( $d['slug'] ) ? sanitize_key( $d['slug'] ) : '';
	if ( ! $slug || ! get_role( $slug ) ) { // ساخت
		$new = gvac_role_create( $name, isset( $d['new_slug'] ) ? $d['new_slug'] : '', isset( $d['caps'] ) ? (array) $d['caps'] : array(), isset( $d['from'] ) ? sanitize_key( $d['from'] ) : '' );
		return is_wp_error( $new ) ? $new : array( 'slug' => $new );
	}
	if ( $name && gvac_role_name( $slug ) !== $name && 'administrator' !== $slug ) {
		$r = gvac_role_rename( $slug, $name );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	if ( isset( $d['caps'] ) ) {
		$r = gvac_role_set_caps( $slug, (array) $d['caps'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	return array( 'slug' => $slug );
}

function gvac_op_role_delete( $d ) {
	$r = gvac_role_delete( isset( $d['slug'] ) ? sanitize_key( $d['slug'] ) : '', isset( $d['reassign'] ) ? sanitize_key( $d['reassign'] ) : '' );
	return is_wp_error( $r ) ? $r : array( 'ok' => 1 );
}

function gvac_op_role_clone( $d ) {
	$r = gvac_role_clone( isset( $d['from'] ) ? sanitize_key( $d['from'] ) : '', isset( $d['name'] ) ? $d['name'] : '' );
	return is_wp_error( $r ) ? $r : array( 'slug' => $r );
}

function gvac_op_role_preset( $d ) {
	$r = gvac_role_from_preset( isset( $d['id'] ) ? sanitize_key( $d['id'] ) : '' );
	return is_wp_error( $r ) ? $r : array( 'slug' => $r );
}

/* ------------------------------------------------------------------ */
/* کاربران                                                             */
/* ------------------------------------------------------------------ */

function gvac_op_users( $d ) {
	return gvac_users_list( $d );
}

function gvac_op_user_roles( $d ) {
	$r = gvac_user_set_roles( (int) $d['id'], isset( $d['roles'] ) ? (array) $d['roles'] : array() );
	return is_wp_error( $r ) ? $r : array( 'ok' => 1 );
}

function gvac_op_user_temp( $d ) {
	$id = (int) $d['id'];
	if ( ! empty( $d['clear'] ) ) {
		gvac_user_revert_temp( $id );
		return array( 'ok' => 1 );
	}
	$until = isset( $d['until'] ) ? (int) $d['until'] : 0;
	$r     = gvac_user_set_temp( $id, isset( $d['role'] ) ? sanitize_key( $d['role'] ) : '', $until );
	return is_wp_error( $r ) ? $r : array( 'ok' => 1 );
}

function gvac_op_user_create( $d ) {
	return gvac_user_create( $d );
}

function gvac_op_user_bulk( $d ) {
	$ids  = array_map( 'intval', isset( $d['ids'] ) ? (array) $d['ids'] : array() );
	$role = isset( $d['role'] ) ? sanitize_key( $d['role'] ) : '';
	$act  = isset( $d['action'] ) ? $d['action'] : '';
	$ok   = 0;
	$err  = array();
	foreach ( $ids as $id ) {
		$u = get_userdata( $id );
		if ( ! $u ) {
			continue;
		}
		$roles = (array) $u->roles;
		if ( 'add' === $act ) {
			$roles[] = $role;
		} elseif ( 'remove' === $act ) {
			$roles = array_diff( $roles, array( $role ) );
		} else {
			$roles = array( $role );
		}
		$r = gvac_user_set_roles( $id, array_values( array_unique( $roles ) ) );
		if ( is_wp_error( $r ) ) {
			$err[] = $u->user_login . ': ' . $r->get_error_message();
		} else {
			$ok++;
		}
	}
	return array( 'ok' => $ok, 'errors' => $err );
}

/* ------------------------------------------------------------------ */
/* قوانین                                                              */
/* ------------------------------------------------------------------ */

function gvac_subject_check( $type, $id ) {
	if ( 'role' === $type ) {
		return get_role( $id ) ? true : new WP_Error( 'role', 'نقش پیدا نشد.' );
	}
	$u = get_userdata( (int) $id );
	if ( ! $u ) {
		return new WP_Error( 'user', 'کاربر پیدا نشد.' );
	}
	return true;
}

function gvac_op_rules_get( $d ) {
	$type = ( isset( $d['type'] ) && 'user' === $d['type'] ) ? 'user' : 'role';
	$id   = 'user' === $type ? (int) $d['id'] : sanitize_key( $d['id'] );
	$ok   = gvac_subject_check( $type, $id );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$rules = gvac_get_rules( $type, $id );
	$out   = array( 'type' => $type, 'id' => $id, 'rules' => $rules );
	if ( 'role' === $type ) {
		$r                = get_role( $id );
		$out['name']      = gvac_role_name( $id );
		$out['caps']      = array_keys( array_filter( (array) $r->capabilities ) );
		$out['caps_lock'] = 'administrator' === $id;
	} else {
		$u            = get_userdata( $id );
		$out['name']  = $u->display_name;
		$out['roles'] = array_map( 'gvac_role_name', (array) $u->roles );
		$inherit      = array();
		foreach ( (array) $u->roles as $rn ) {
			$r = get_role( $rn );
			if ( $r ) {
				$inherit = array_merge( $inherit, array_keys( array_filter( (array) $r->capabilities ) ) );
			}
		}
		$out['caps'] = array_values( array_unique( $inherit ) );
		$out['immune'] = gvac_is_immune( $id );
		// قوانین ارثی از نقش‌ها (فقط برای نمایش)
		$out['inherited'] = gvac_compose_rules( (array) $u->roles, 0 );
	}
	// عنوان محتوای محدودشده
	$meta = array();
	foreach ( array_keys( (array) $rules['content'] ) as $pid ) {
		$p = get_post( (int) $pid );
		$meta[ $pid ] = $p ? array( 'title' => get_the_title( $p ) ? get_the_title( $p ) : '#' . $pid, 'type' => $p->post_type ) : array( 'title' => '(حذف‌شده) #' . $pid, 'type' => '' );
	}
	$out['content_meta'] = $meta;
	return $out;
}

/** اگر این قانون راه ورود خود ادمین به این پنل را می‌بندد */
function gvac_rules_self_lock( $type, $id, $rules ) {
	$me = wp_get_current_user();
	if ( gvac_is_immune( $me->ID ) ) {
		return false;
	}
	if ( 'role' === $type && ! in_array( $id, (array) $me->roles, true ) ) {
		return false;
	}
	if ( 'user' === $type && (int) $id !== (int) $me->ID ) {
		return false;
	}
	$bad = array( GVAC_MENU_SLUG, 'groot-vision-hub', 'groot-vision-hub|' . GVAC_MENU_SLUG );
	foreach ( $bad as $k ) {
		if ( isset( $rules['menu'][ $k ] ) && 'allow' !== $rules['menu'][ $k ] ) {
			return true;
		}
	}
	return ! empty( $rules['behavior']['block_admin'] );
}

function gvac_op_rules_save( $d ) {
	$type = ( isset( $d['type'] ) && 'user' === $d['type'] ) ? 'user' : 'role';
	$id   = 'user' === $type ? (int) $d['id'] : sanitize_key( $d['id'] );
	$ok   = gvac_subject_check( $type, $id );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$rules = gvac_sanitize_rules( isset( $d['rules'] ) ? $d['rules'] : array(), $type );
	if ( gvac_rules_self_lock( $type, $id, $rules ) ) {
		return new WP_Error( 'self', 'این قانون خودِ شما را از همین پنل بیرون می‌اندازد. برای جلوگیری از قفل‌شدن، ذخیره نشد.' );
	}
	if ( 'role' === $type && isset( $d['caps'] ) && 'administrator' !== $id ) {
		$r = gvac_role_set_caps( $id, (array) $d['caps'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	gvac_save_rules( $type, $id, $rules );
	gvac_log( 'change', 'rules.save', gvac_count_rules( $rules ) . ' مورد', 'user' === $type ? ( get_userdata( $id )->user_login ) : $id );
	return array( 'count' => gvac_count_rules( $rules ) );
}

function gvac_op_rules_reset( $d ) {
	$type = ( isset( $d['type'] ) && 'user' === $d['type'] ) ? 'user' : 'role';
	$id   = 'user' === $type ? (int) $d['id'] : sanitize_key( $d['id'] );
	gvac_save_rules( $type, $id, gvac_empty_rules() );
	gvac_log( 'change', 'rules.reset', '', (string) $id );
	return array( 'ok' => 1 );
}

/** نمای نهایی دسترسی یک کاربر */
function gvac_op_effective( $d ) {
	$id = (int) $d['id'];
	$u  = get_userdata( $id );
	if ( ! $u ) {
		return new WP_Error( 'user', 'کاربر پیدا نشد.' );
	}
	$r  = gvac_compose_rules( (array) $u->roles, $id );
	$lbl = array();
	foreach ( gvac_catalog_menus() as $m ) {
		$lbl['menu'][ $m['key'] ] = $m['title'];
		foreach ( $m['children'] as $c ) {
			$lbl['menu'][ $c['key'] ] = $m['title'] . ' › ' . $c['title'];
		}
	}
	foreach ( gvac_catalog_adminbar() as $n ) {
		$lbl['adminbar'][ $n['id'] ] = $n['title'];
	}
	foreach ( gvac_catalog_widgets() as $w ) {
		$lbl['widgets'][ $w['id'] ] = $w['title'];
	}
	foreach ( gvac_catalog_metaboxes() as $g ) {
		foreach ( $g['boxes'] as $b ) {
			$lbl['metaboxes'][ $b['key'] ] = $g['label'] . ' › ' . $b['title'];
		}
	}
	$items = array();
	foreach ( array( 'menu' => 'منو', 'adminbar' => 'نوار ادمین', 'widgets' => 'ویجت', 'metaboxes' => 'متاباکس' ) as $sec => $name ) {
		foreach ( $r[ $sec ] as $k => $v ) {
			$items[] = array( 'sec' => $name, 'label' => isset( $lbl[ $sec ][ $k ] ) ? $lbl[ $sec ][ $k ] : $k, 'state' => $v );
		}
	}
	$caps = array();
	foreach ( $r['caps'] as $c => $v ) {
		$caps[] = array( 'cap' => $c, 'state' => $v );
	}
	$t = get_user_meta( $id, 'gvac_temp', true );
	return array(
		'user'     => array( 'id' => $id, 'name' => $u->display_name, 'roles' => array_map( 'gvac_role_name', (array) $u->roles ) ),
		'items'    => $items,
		'caps'     => $caps,
		'urls'     => $r['urls'],
		'content'  => count( $r['content'] ),
		'behavior' => $r['behavior'],
		'stealth'  => gvac_stealth_get( $id ),
		'immune'   => gvac_is_immune( $id ),
		'temp'     => is_array( $t ) ? array( 'role' => gvac_role_name( $t['role'] ), 'until' => (int) $t['until'] ) : null,
		'hides_plugin' => gvac_plugin_hidden_for( $id ),
	);
}

function gvac_op_preview( $d ) {
	if ( ! gvac_is_immune( get_current_user_id() ) ) {
		return new WP_Error( 'immune', 'پیش‌نمایش فقط برای کاربران «مصون» فعال است.' );
	}
	if ( ! empty( $d['stop'] ) ) {
		gvac_preview_stop();
		return array( 'ok' => 1 );
	}
	$type = ( isset( $d['type'] ) && 'user' === $d['type'] ) ? 'user' : 'role';
	$id   = 'user' === $type ? (int) $d['id'] : sanitize_key( $d['id'] );
	$ok   = gvac_subject_check( $type, $id );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	gvac_preview_start( $type, $id );
	gvac_log( 'system', 'preview.start', $type . ':' . $id );
	return array( 'url' => admin_url( 'index.php' ) );
}

/* ------------------------------------------------------------------ */
/* پنهان‌سازی                                                          */
/* ------------------------------------------------------------------ */

function gvac_op_stealth_list( $d ) {
	$out = array();
	foreach ( gvac_stealth_all() as $uid => $cfg ) {
		$u = get_userdata( $uid );
		if ( $u ) {
			$cfg   = wp_parse_args( $cfg, gvac_stealth_defaults() );
			$names = array();
			foreach ( (array) $cfg['from_users'] as $fu ) {
				$x = get_userdata( (int) $fu );
				$names[ (int) $fu ] = $x ? $x->display_name : '#' . (int) $fu;
			}
			$out[] = array( 'id' => (int) $uid, 'name' => $u->display_name, 'login' => $u->user_login, 'avatar' => get_avatar_url( $uid, array( 'size' => 64 ) ), 'cfg' => $cfg, 'names' => $names );
		}
	}
	return array( 'items' => $out, 'hide_plugin' => (bool) gvac_opt( 'hide_plugin' ), 'roles' => gvac_roles_list() );
}

function gvac_op_stealth_save( $d ) {
	$id = (int) $d['id'];
	if ( ! get_userdata( $id ) ) {
		return new WP_Error( 'user', 'کاربر پیدا نشد.' );
	}
	if ( ! empty( $d['off'] ) ) {
		gvac_stealth_save( $id, null );
		gvac_log( 'change', 'stealth.off', '', get_userdata( $id )->user_login );
	} else {
		gvac_stealth_save( $id, isset( $d['cfg'] ) ? (array) $d['cfg'] : array() );
		gvac_log( 'change', 'stealth.on', '', get_userdata( $id )->user_login );
	}
	return array( 'ok' => 1 );
}

function gvac_op_user_find( $d ) {
	$q = isset( $d['q'] ) ? sanitize_text_field( $d['q'] ) : '';
	$u = new WP_User_Query( array( 'number' => 10, 'search' => '*' . $q . '*', 'search_columns' => array( 'user_login', 'user_email', 'display_name' ) ) );
	return array_map( function ( $x ) {
		return array( 'id' => (int) $x->ID, 'name' => $x->display_name, 'login' => $x->user_login, 'avatar' => get_avatar_url( $x->ID, array( 'size' => 48 ) ) );
	}, $u->get_results() );
}

/* ------------------------------------------------------------------ */
/* تنظیمات، مصون‌ها، بازیابی                                           */
/* ------------------------------------------------------------------ */

function gvac_op_settings_save( $d ) {
	$s = gvac_opt();
	foreach ( array( 'enabled', 'log_denied', 'hide_plugin', 'show_lock_badge' ) as $f ) {
		if ( isset( $d[ $f ] ) ) {
			$s[ $f ] = empty( $d[ $f ] ) ? 0 : 1;
		}
	}
	if ( isset( $d['merge'] ) ) {
		$s['merge'] = 'allow' === $d['merge'] ? 'allow' : 'deny';
	}
	if ( isset( $d['hidden_response'] ) && in_array( $d['hidden_response'], array( 'denied', 'dashboard', 'notfound' ), true ) ) {
		$s['hidden_response'] = $d['hidden_response'];
	}
	if ( isset( $d['deny_title'] ) ) {
		$s['deny_title'] = sanitize_text_field( $d['deny_title'] );
	}
	if ( isset( $d['deny_message'] ) ) {
		$s['deny_message'] = sanitize_textarea_field( $d['deny_message'] );
	}
	if ( isset( $d['log_days'] ) ) {
		$s['log_days'] = max( 7, min( 365, (int) $d['log_days'] ) );
	}
	update_option( 'gvac_settings', $s, true );
	gvac_flush_cache();
	gvac_log( 'change', 'settings', '' );
	return gvac_settings_for_ui();
}

function gvac_op_immune_set( $d ) {
	if ( ! gvac_is_immune( get_current_user_id() ) ) {
		return new WP_Error( 'immune', 'فقط کاربران مصون می‌توانند این فهرست را تغییر دهند.' );
	}
	$id  = (int) $d['id'];
	$ids = gvac_immune_ids();
	if ( ! empty( $d['on'] ) ) {
		if ( ! get_userdata( $id ) ) {
			return new WP_Error( 'user', 'کاربر پیدا نشد.' );
		}
		$ids[] = $id;
	} else {
		$ids = array_diff( $ids, array( $id ) );
		if ( ! $ids ) {
			return new WP_Error( 'last', 'حداقل یک کاربر مصون باید بماند.' );
		}
	}
	update_option( 'gvac_immune', array_values( array_unique( $ids ) ), false );
	gvac_flush_cache();
	gvac_log( 'change', 'immune', ! empty( $d['on'] ) ? 'افزودن' : 'حذف', (string) $id );
	return gvac_settings_for_ui();
}

function gvac_op_recovery_regen( $d ) {
	if ( ! gvac_is_immune( get_current_user_id() ) ) {
		return new WP_Error( 'immune', 'فقط کاربران مصون.' );
	}
	$s                 = gvac_opt();
	$s['recovery_key'] = wp_generate_password( 32, false );
	update_option( 'gvac_settings', $s, true );
	gvac_log( 'change', 'recovery.regen', '' );
	return gvac_settings_for_ui();
}

/* ------------------------------------------------------------------ */
/* لاگ                                                                 */
/* ------------------------------------------------------------------ */

function gvac_op_log_list( $d ) {
	global $wpdb;
	$t     = gvac_table( 'log' );
	$per   = isset( $d['per'] ) ? max( 1, min( 100, (int) $d['per'] ) ) : 25;
	$page  = max( 1, isset( $d['page'] ) ? (int) $d['page'] : 1 );
	$where = '1=1';
	$args  = array();
	if ( ! empty( $d['type'] ) && in_array( $d['type'], array( 'change', 'denied', 'system' ), true ) ) {
		$where .= ' AND type=%s';
		$args[] = $d['type'];
	}
	if ( ! empty( $d['search'] ) ) {
		$like   = '%' . $wpdb->esc_like( sanitize_text_field( $d['search'] ) ) . '%';
		$where .= ' AND (details LIKE %s OR subject LIKE %s OR action LIKE %s)';
		array_push( $args, $like, $like, $like );
	}
	$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $args ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $where" ) ); // phpcs:ignore
	$a2    = array_merge( $args, array( $per, ( $page - 1 ) * $per ) );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", $a2 ) ); // phpcs:ignore
	$items = array();
	foreach ( $rows as $r ) {
		$u       = $r->user_id ? get_userdata( $r->user_id ) : null;
		$items[] = array(
			'id'      => (int) $r->id,
			'ts'      => strtotime( $r->created_at . ' ' . wp_timezone_string() ),
			'type'    => $r->type,
			'action'  => $r->action,
			'subject' => $r->subject,
			'details' => $r->details,
			'ip'      => $r->ip,
			'url'     => $r->url,
			'user'    => $u ? $u->display_name : ( $r->user_id ? '#' . $r->user_id : 'سیستم' ),
		);
	}
	return array( 'items' => $items, 'total' => $total, 'pages' => (int) ceil( $total / $per ), 'page' => $page );
}

function gvac_op_log_clear( $d ) {
	global $wpdb;
	$wpdb->query( 'TRUNCATE TABLE ' . gvac_table( 'log' ) ); // phpcs:ignore
	gvac_log( 'system', 'log.clear', 'پاک‌شدن لاگ' );
	return array( 'ok' => 1 );
}

/* ------------------------------------------------------------------ */
/* برون‌بری / درون‌ریزی                                                */
/* ------------------------------------------------------------------ */

function gvac_op_export( $d ) {
	$roles = array();
	foreach ( wp_roles()->roles as $slug => $r ) {
		if ( 'administrator' === $slug ) {
			continue;
		}
		$roles[] = array( 'slug' => $slug, 'name' => $r['name'], 'caps' => array_keys( array_filter( (array) $r['capabilities'] ) ) );
	}
	$all   = gvac_all_rules();
	$users = array();
	foreach ( $all['user'] as $uid => $rules ) {
		$u = get_userdata( $uid );
		if ( $u ) {
			$users[ $u->user_login ] = $rules;
		}
	}
	$st = array();
	foreach ( gvac_stealth_all() as $uid => $cfg ) {
		$u = get_userdata( $uid );
		if ( $u ) {
			$st[ $u->user_login ] = $cfg;
		}
	}
	$s = gvac_opt();
	unset( $s['recovery_key'] );
	return array( 'file' => array( 'gvac' => GVAC_VERSION, 'site' => home_url(), 'roles' => $roles, 'role_rules' => $all['role'], 'user_rules' => $users, 'stealth' => $st, 'settings' => $s ) );
}

function gvac_op_import( $d ) {
	$f = isset( $d['file'] ) && is_array( $d['file'] ) ? $d['file'] : array();
	if ( empty( $f['gvac'] ) ) {
		return new WP_Error( 'file', 'این فایل خروجی همین ماژول نیست.' );
	}
	$replace = ( isset( $d['mode'] ) && 'replace' === $d['mode'] );
	$n       = array( 'roles' => 0, 'rules' => 0, 'users' => 0, 'skipped' => 0 );
	foreach ( (array) ( isset( $f['roles'] ) ? $f['roles'] : array() ) as $r ) {
		$slug = isset( $r['slug'] ) ? sanitize_key( $r['slug'] ) : '';
		if ( ! $slug || 'administrator' === $slug ) {
			continue;
		}
		$caps = isset( $r['caps'] ) ? array_map( 'sanitize_text_field', (array) $r['caps'] ) : array();
		if ( get_role( $slug ) ) {
			if ( ! in_array( $slug, gvac_core_role_slugs(), true ) ) {
				$x = gvac_role_set_caps( $slug, $caps );
				is_wp_error( $x ) ? $n['skipped']++ : $n['roles']++;
			}
		} else {
			$x = gvac_role_create( isset( $r['name'] ) ? $r['name'] : $slug, $slug, $caps );
			is_wp_error( $x ) ? $n['skipped']++ : $n['roles']++;
		}
	}
	$all = $replace ? array( 'role' => array(), 'user' => array() ) : gvac_all_rules();
	foreach ( (array) ( isset( $f['role_rules'] ) ? $f['role_rules'] : array() ) as $slug => $rules ) {
		if ( get_role( sanitize_key( $slug ) ) ) {
			$all['role'][ sanitize_key( $slug ) ] = gvac_sanitize_rules( $rules, 'role' );
			$n['rules']++;
		}
	}
	foreach ( (array) ( isset( $f['user_rules'] ) ? $f['user_rules'] : array() ) as $login => $rules ) {
		$u = get_user_by( 'login', sanitize_user( $login ) );
		if ( $u ) {
			$all['user'][ $u->ID ] = gvac_sanitize_rules( $rules, 'user' );
			$n['users']++;
		} else {
			$n['skipped']++;
		}
	}
	foreach ( $all as $t => $set ) {
		foreach ( $set as $k => $v ) {
			if ( gvac_rules_is_empty( $v ) ) {
				unset( $all[ $t ][ $k ] );
			}
		}
	}
	update_option( 'gvac_rules', $all, true );
	foreach ( (array) ( isset( $f['stealth'] ) ? $f['stealth'] : array() ) as $login => $cfg ) {
		$u = get_user_by( 'login', sanitize_user( $login ) );
		if ( $u ) {
			gvac_stealth_save( $u->ID, (array) $cfg );
		}
	}
	gvac_flush_cache();
	gvac_log( 'change', 'import', wp_json_encode( $n ) );
	return $n;
}
