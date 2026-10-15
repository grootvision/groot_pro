<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* تنظیمات                                                             */
/* ------------------------------------------------------------------ */

function gvac_defaults() {
	return array(
		'enabled'         => 1,        // کلید اصلی؛ خاموش = هیچ محدودیتی اعمال نمی‌شود
		'merge'           => 'deny',   // deny: اگر در هر نقش بسته بود بسته بماند | allow: فقط اگر در همه‌ی نقش‌ها بسته بود
		'hidden_response' => 'denied', // ورود مستقیم به آیتم مخفی: denied | dashboard | notfound
		'deny_title'      => 'دسترسی بسته است',
		'deny_message'    => 'سطح کاربری شما اجازه‌ی استفاده از این بخش را ندارد. اگر فکر می‌کنید اشتباهی رخ داده، موضوع را با مدیر سایت در میان بگذارید.',
		'log_denied'      => 1,        // ثبت تلاش‌های ممنوع
		'log_days'        => 90,       // نگهداری لاگ (روز)
		'recovery_key'    => '',       // کلید بازیابی اضطراری
		'hide_plugin'     => 0,        // پنهان‌سازی خود افزونه از دید کاربران عادی
		'show_lock_badge' => 1,        // نمایش آیکن قفل کنار آیتم‌های قفل‌شده
	);
}

function gvac_opt( $key = null ) {
	$o = wp_parse_args( get_option( 'gvac_settings', array() ), gvac_defaults() );
	return null === $key ? $o : ( isset( $o[ $key ] ) ? $o[ $key ] : null );
}

function gvac_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'gvac_' . $name;
}

function gvac_fa( $n ) {
	return strtr( (string) $n, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}

function gvac_menu_url( $tab = '' ) {
	return admin_url( 'admin.php?page=' . GVAC_MENU_SLUG . ( $tab ? '&tab=' . rawurlencode( $tab ) : '' ) );
}

/* ------------------------------------------------------------------ */
/* حالت اضطراری                                                         */
/* ------------------------------------------------------------------ */

/** آیا موتور محدودیت‌ها در این درخواست باید خاموش باشد؟ */
function gvac_engine_off() {
	static $off = null;
	if ( null !== $off ) {
		return $off;
	}
	$off = false;
	if ( defined( 'GV_AC_DISABLE' ) && GV_AC_DISABLE ) {
		$off = true;
	} elseif ( ! gvac_opt( 'enabled' ) ) {
		$off = true;
	} elseif ( get_transient( 'gvac_recovery_active' ) ) {
		$off = true;
	}
	return $off;
}

/* ------------------------------------------------------------------ */
/* کاربران مصون (Immune): هیچ‌وقت تحت قوانین قرار نمی‌گیرند              */
/* (مگر وقتی خودشان «پیش‌نمایش» را روشن کنند)                            */
/* ------------------------------------------------------------------ */

function gvac_immune_ids() {
	$ids = get_option( 'gvac_immune', array() );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

function gvac_is_immune( $user_id ) {
	return in_array( (int) $user_id, gvac_immune_ids(), true );
}

/* ------------------------------------------------------------------ */
/* ذخیره‌ی قوانین                                                       */
/* ساختار: array( 'role' => [slug => rules], 'user' => [id => rules] ) */
/* ------------------------------------------------------------------ */

function gvac_empty_rules() {
	return array(
		'menu'      => array(), // key => hide|lock (برای کاربر allow هم دارد)
		'adminbar'  => array(), // node_id => hide|lock|allow
		'widgets'   => array(), // widget_id => hide|allow
		'metaboxes' => array(), // post_type:id => hide|allow
		'caps'      => array(), // cap => grant|deny (فقط سطح کاربر)
		'urls'      => array(), // [ [pattern, mode] ]
		'content'   => array(), // post_id => [view, edit, delete]
		'behavior'  => array(), // login_redirect, logout_redirect, deny_redirect, block_admin, block_redirect, hide_adminbar_front, hide_adminbar_all, deny_message
	);
}

function gvac_all_rules() {
	$r = get_option( 'gvac_rules', array() );
	if ( ! is_array( $r ) ) {
		$r = array();
	}
	$r += array( 'role' => array(), 'user' => array() );
	return $r;
}

function gvac_get_rules( $type, $id ) {
	$all = gvac_all_rules();
	$set = isset( $all[ $type ][ $id ] ) && is_array( $all[ $type ][ $id ] ) ? $all[ $type ][ $id ] : array();
	return wp_parse_args( $set, gvac_empty_rules() );
}

function gvac_rules_is_empty( $r ) {
	foreach ( (array) $r as $v ) {
		if ( ! empty( $v ) ) {
			return false;
		}
	}
	return true;
}

/** پاک‌سازی ورودی خام قوانین */
function gvac_sanitize_rules( $in, $type = 'role' ) {
	$in  = is_array( $in ) ? $in : array();
	$out = gvac_empty_rules();

	$user   = ( 'user' === $type );
	$states = array(
		'menu'      => $user ? array( 'hide', 'lock', 'allow' ) : array( 'hide', 'lock' ),
		'adminbar'  => $user ? array( 'hide', 'lock', 'allow' ) : array( 'hide', 'lock' ),
		'widgets'   => $user ? array( 'hide', 'allow' ) : array( 'hide' ),
		'metaboxes' => $user ? array( 'hide', 'allow' ) : array( 'hide' ),
	);
	foreach ( $states as $sec => $ok ) {
		if ( empty( $in[ $sec ] ) || ! is_array( $in[ $sec ] ) ) {
			continue;
		}
		foreach ( $in[ $sec ] as $k => $v ) {
			$k = is_string( $k ) ? trim( $k ) : '';
			$v = is_string( $v ) ? sanitize_key( $v ) : '';
			if ( '' === $k || strlen( $k ) > 300 || ! in_array( $v, $ok, true ) ) {
				continue;
			}
			$out[ $sec ][ wp_strip_all_tags( $k ) ] = $v;
		}
	}

	if ( $user && ! empty( $in['caps'] ) && is_array( $in['caps'] ) ) {
		foreach ( $in['caps'] as $cap => $v ) {
			$cap = sanitize_text_field( (string) $cap );
			if ( '' !== $cap && in_array( $v, array( 'grant', 'deny' ), true ) ) {
				$out['caps'][ $cap ] = $v;
			}
		}
	}

	if ( ! empty( $in['urls'] ) && is_array( $in['urls'] ) ) {
		foreach ( $in['urls'] as $row ) {
			$p = isset( $row['pattern'] ) ? trim( sanitize_text_field( (string) $row['pattern'] ) ) : '';
			$m = isset( $row['mode'] ) && 'hide' === $row['mode'] ? 'hide' : 'lock';
			if ( '' !== $p && strlen( $p ) <= 300 ) {
				$out['urls'][] = array( 'pattern' => $p, 'mode' => $m );
			}
		}
		$out['urls'] = array_slice( $out['urls'], 0, 200 );
	}

	if ( ! empty( $in['content'] ) && is_array( $in['content'] ) ) {
		foreach ( $in['content'] as $pid => $acts ) {
			$pid = (int) $pid;
			if ( $pid < 1 || ! is_array( $acts ) ) {
				continue;
			}
			$acts = array_values( array_intersect( $acts, array( 'view', 'edit', 'delete' ) ) );
			if ( $acts ) {
				$out['content'][ $pid ] = $acts;
			}
		}
		$out['content'] = array_slice( $out['content'], 0, 500, true );
	}

	if ( ! empty( $in['behavior'] ) && is_array( $in['behavior'] ) ) {
		$b = $in['behavior'];
		foreach ( array( 'login_redirect', 'logout_redirect', 'deny_redirect', 'block_redirect' ) as $f ) {
			if ( ! empty( $b[ $f ] ) ) {
				$u = esc_url_raw( trim( (string) $b[ $f ] ) );
				if ( '' !== $u ) {
					$out['behavior'][ $f ] = $u;
				}
			}
		}
		foreach ( array( 'block_admin', 'hide_adminbar_front', 'hide_adminbar_all' ) as $f ) {
			if ( ! empty( $b[ $f ] ) ) {
				$out['behavior'][ $f ] = 1;
			}
		}
		if ( ! empty( $b['deny_message'] ) ) {
			$out['behavior']['deny_message'] = sanitize_textarea_field( (string) $b['deny_message'] );
		}
	}
	return $out;
}

function gvac_save_rules( $type, $id, $rules ) {
	$all = gvac_all_rules();
	if ( gvac_rules_is_empty( $rules ) ) {
		unset( $all[ $type ][ $id ] );
	} else {
		$all[ $type ][ $id ] = $rules;
	}
	update_option( 'gvac_rules', $all, true );
	gvac_flush_cache();
}

function gvac_flush_cache() {
	$GLOBALS['gvac_eff_cache'] = array();
}

/** تعداد محدودیت‌های فعال یک مجموعه‌قانون */
function gvac_count_rules( $r ) {
	$n = 0;
	foreach ( array( 'menu', 'adminbar', 'widgets', 'metaboxes' ) as $s ) {
		foreach ( (array) $r[ $s ] as $v ) {
			if ( 'allow' !== $v ) {
				$n++;
			}
		}
	}
	return $n + count( (array) $r['caps'] ) + count( (array) $r['urls'] ) + count( (array) $r['content'] ) + count( (array) $r['behavior'] );
}

/* ------------------------------------------------------------------ */
/* ادغام قوانین مؤثر                                                    */
/* ------------------------------------------------------------------ */

/** قوانین مؤثر = ادغام نقش‌ها + بازنویسی با قوانین خود کاربر */
function gvac_compose_rules( array $roles, $user_id = 0 ) {
	$all   = gvac_all_rules();
	$merge = gvac_opt( 'merge' );
	$out   = gvac_empty_rules();
	$rank  = array( 'lock' => 1, 'hide' => 2 );

	$sets = array();
	foreach ( $roles as $r ) {
		$sets[] = isset( $all['role'][ $r ] ) ? wp_parse_args( $all['role'][ $r ], gvac_empty_rules() ) : gvac_empty_rules();
	}

	foreach ( array( 'menu', 'adminbar', 'widgets', 'metaboxes' ) as $sec ) {
		$keys = array();
		foreach ( $sets as $set ) {
			$keys += array_fill_keys( array_keys( (array) $set[ $sec ] ), 1 );
		}
		foreach ( array_keys( $keys ) as $k ) {
			$vals = array();
			foreach ( $sets as $set ) {
				$vals[] = isset( $set[ $sec ][ $k ] ) ? $set[ $sec ][ $k ] : 'allow';
			}
			if ( 'allow' === $merge ) { // اشتراک: همه‌ی نقش‌ها باید ببندند؛ ضعیف‌ترین حالت
				if ( in_array( 'allow', $vals, true ) ) {
					continue;
				}
				$out[ $sec ][ $k ] = in_array( 'lock', $vals, true ) ? 'lock' : 'hide';
			} else { // اجتماع: سخت‌گیرانه‌ترین حالت
				$pick = '';
				foreach ( $vals as $v ) {
					if ( isset( $rank[ $v ] ) && ( '' === $pick || $rank[ $v ] > $rank[ $pick ] ) ) {
						$pick = $v;
					}
				}
				if ( '' !== $pick ) {
					$out[ $sec ][ $k ] = $pick;
				}
			}
		}
	}

	$merge_content = function ( &$dst, $src ) {
		foreach ( (array) $src as $pid => $acts ) {
			$dst[ $pid ] = array_values( array_unique( array_merge( isset( $dst[ $pid ] ) ? $dst[ $pid ] : array(), $acts ) ) );
		}
	};
	foreach ( $sets as $set ) {
		foreach ( (array) $set['urls'] as $u ) {
			$out['urls'][] = $u;
		}
		$merge_content( $out['content'], $set['content'] );
		foreach ( (array) $set['behavior'] as $b => $v ) {
			if ( ! isset( $out['behavior'][ $b ] ) ) { // اولین نقش اولویت دارد
				$out['behavior'][ $b ] = $v;
			}
		}
	}

	if ( $user_id && isset( $all['user'][ $user_id ] ) ) {
		$u = wp_parse_args( $all['user'][ $user_id ], gvac_empty_rules() );
		foreach ( array( 'menu', 'adminbar', 'widgets', 'metaboxes' ) as $sec ) {
			foreach ( (array) $u[ $sec ] as $k => $v ) {
				if ( 'allow' === $v ) {
					unset( $out[ $sec ][ $k ] );
				} else {
					$out[ $sec ][ $k ] = $v;
				}
			}
		}
		$out['caps'] = (array) $u['caps'];
		foreach ( (array) $u['urls'] as $x ) {
			$out['urls'][] = $x;
		}
		$merge_content( $out['content'], $u['content'] );
		foreach ( (array) $u['behavior'] as $b => $v ) {
			$out['behavior'][ $b ] = $v;
		}
	}
	return $out;
}

/**
 * قوانین مؤثر یک کاربر (با کش). برای کاربر مصون و وقتی موتور خاموش است خالی است.
 * در حالت پیش‌نمایش، برای ادمین فعلی قوانین «سوژه‌ی پیش‌نمایش» برگردانده می‌شود.
 */
function gvac_effective_rules( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! isset( $GLOBALS['gvac_eff_cache'] ) ) {
		$GLOBALS['gvac_eff_cache'] = array();
	}
	if ( isset( $GLOBALS['gvac_eff_cache'][ $user_id ] ) ) {
		return $GLOBALS['gvac_eff_cache'][ $user_id ];
	}
	$res = gvac_empty_rules();
	if ( ! $user_id || gvac_engine_off() ) {
		return $GLOBALS['gvac_eff_cache'][ $user_id ] = $res;
	}
	$prev = gvac_preview_for( $user_id );
	if ( $prev ) {
		if ( 'role' === $prev['type'] ) {
			$res = gvac_compose_rules( array( $prev['id'] ), 0 );
		} else {
			$u   = get_userdata( (int) $prev['id'] );
			$res = $u ? gvac_compose_rules( (array) $u->roles, (int) $u->ID ) : $res;
		}
		return $GLOBALS['gvac_eff_cache'][ $user_id ] = $res;
	}
	if ( gvac_is_immune( $user_id ) ) {
		return $GLOBALS['gvac_eff_cache'][ $user_id ] = $res;
	}
	$u = get_userdata( $user_id );
	if ( $u ) {
		$res = gvac_compose_rules( (array) $u->roles, $user_id );
	}
	return $GLOBALS['gvac_eff_cache'][ $user_id ] = $res;
}

/** پیش‌نمایش فعال برای این کاربر؟ (فقط مصون‌ها) → array(type,id) یا false */
function gvac_preview_for( $user_id ) {
	static $memo = array();
	$user_id = (int) $user_id;
	if ( isset( $memo[ $user_id ] ) ) {
		return $memo[ $user_id ];
	}
	$memo[ $user_id ] = false;
	if ( ! gvac_is_immune( $user_id ) ) {
		return false;
	}
	$p = get_user_meta( $user_id, 'gvac_preview', true );
	if ( is_array( $p ) && ! empty( $p['type'] ) && ! empty( $p['id'] ) && ! empty( $p['until'] ) && (int) $p['until'] > time() ) {
		$memo[ $user_id ] = array( 'type' => $p['type'], 'id' => $p['id'] );
	}
	return $memo[ $user_id ];
}

/* ------------------------------------------------------------------ */
/* لاگ                                                                 */
/* ------------------------------------------------------------------ */

/** @param string $type change | denied | system */
function gvac_log( $type, $action, $details = '', $subject = '', $user_id = null ) {
	global $wpdb;
	$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
	$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$url     = isset( $_SERVER['REQUEST_URI'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 0, 300 ) : '';
	$wpdb->insert( gvac_table( 'log' ), array(
		'created_at' => current_time( 'mysql' ),
		'user_id'    => $user_id,
		'type'       => substr( $type, 0, 12 ),
		'action'     => substr( $action, 0, 60 ),
		'subject'    => substr( (string) $subject, 0, 120 ),
		'details'    => substr( (string) $details, 0, 1000 ),
		'ip'         => substr( $ip, 0, 45 ),
		'url'        => $url,
	) );
}
