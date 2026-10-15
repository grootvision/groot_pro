<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* نقش‌ها                                                              */
/* ------------------------------------------------------------------ */

function gvac_core_role_slugs() {
	return array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
}

function gvac_role_name( $slug ) {
	$roles = wp_roles()->roles;
	return isset( $roles[ $slug ] ) ? translate_user_role( $roles[ $slug ]['name'] ) : $slug;
}

function gvac_our_roles() {
	$a = get_option( 'gvac_our_roles', array() );
	return is_array( $a ) ? $a : array();
}

function gvac_roles_list() {
	$counts = count_users();
	$avail  = isset( $counts['avail_roles'] ) ? $counts['avail_roles'] : array();
	$rules  = gvac_all_rules();
	$ours   = gvac_our_roles();
	$out    = array();
	foreach ( wp_roles()->roles as $slug => $r ) {
		$caps = array_keys( array_filter( (array) $r['capabilities'] ) );
		$rl   = isset( $rules['role'][ $slug ] ) ? gvac_count_rules( wp_parse_args( $rules['role'][ $slug ], gvac_empty_rules() ) ) : 0;
		$out[] = array(
			'slug'    => $slug,
			'name'    => translate_user_role( $r['name'] ),
			'caps'    => count( $caps ),
			'users'   => isset( $avail[ $slug ] ) ? (int) $avail[ $slug ] : 0,
			'core'    => in_array( $slug, gvac_core_role_slugs(), true ),
			'ours'    => in_array( $slug, $ours, true ),
			'rules'   => $rl,
			'admin'   => ! empty( $r['capabilities']['manage_options'] ),
			'locked'  => 'administrator' === $slug, // قابلیت‌های مدیر کل قابل ویرایش نیست
		);
	}
	return $out;
}

function gvac_role_slug_from_name( $name, $slug = '' ) {
	$slug = sanitize_key( str_replace( '-', '_', $slug ) );
	if ( '' === $slug ) {
		$ascii = sanitize_key( str_replace( array( ' ', '-' ), '_', remove_accents( $name ) ) );
		$slug  = ( '' !== $ascii && preg_match( '/^[a-z0-9_]+$/', $ascii ) ) ? $ascii : 'role_' . strtolower( wp_generate_password( 6, false ) );
	}
	$base = $slug;
	$i    = 2;
	while ( get_role( $slug ) ) {
		$slug = $base . '_' . $i++;
	}
	return $slug;
}

/** کاربر فعلی می‌تواند همه‌ی قابلیت‌های این فهرست را به دیگران بدهد؟ (جلوگیری از ارتقای دسترسی) */
function gvac_actor_can_grant( array $caps ) {
	foreach ( $caps as $c ) {
		if ( 'read' !== $c && ! current_user_can( $c ) ) {
			return new WP_Error( 'escalate', 'شما خودتان قابلیت «' . $c . '» را ندارید و نمی‌توانید آن را به دیگری بدهید.' );
		}
	}
	return true;
}

function gvac_role_create( $name, $slug = '', array $caps = array(), $from = '' ) {
	$name = trim( sanitize_text_field( $name ) );
	if ( '' === $name ) {
		return new WP_Error( 'name', 'نام نقش را وارد کنید.' );
	}
	if ( $from ) {
		$src = get_role( $from );
		if ( ! $src ) {
			return new WP_Error( 'from', 'نقش مبدأ پیدا نشد.' );
		}
		$caps = array_keys( array_filter( (array) $src->capabilities ) );
	}
	$caps = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $caps ) ) ) );
	$caps = array_diff( $caps, array( 'administrator' ) );
	$ok   = gvac_actor_can_grant( $caps );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$slug = gvac_role_slug_from_name( $name, $slug );
	$map  = array( 'read' => true );
	foreach ( $caps as $c ) {
		$map[ $c ] = true;
	}
	add_role( $slug, $name, $map );
	$ours   = gvac_our_roles();
	$ours[] = $slug;
	update_option( 'gvac_our_roles', array_values( array_unique( $ours ) ), false );
	gvac_log( 'change', 'role.create', $name, $slug );
	return $slug;
}

function gvac_role_rename( $slug, $name ) {
	$name = trim( sanitize_text_field( $name ) );
	$wr   = wp_roles();
	if ( ! isset( $wr->roles[ $slug ] ) || '' === $name ) {
		return new WP_Error( 'role', 'نقش یا نام نامعتبر است.' );
	}
	$wr->roles[ $slug ]['name'] = $name;
	$wr->role_names[ $slug ]    = $name;
	update_option( $wr->role_key, $wr->roles );
	gvac_log( 'change', 'role.rename', $name, $slug );
	return true;
}

/** ذخیره‌ی قابلیت‌های نقش (لیست قابلیت‌های فعال) */
function gvac_role_set_caps( $slug, array $caps ) {
	$role = get_role( $slug );
	if ( ! $role ) {
		return new WP_Error( 'role', 'نقش پیدا نشد.' );
	}
	if ( 'administrator' === $slug ) {
		return new WP_Error( 'admin', 'قابلیت‌های «مدیر کل» برای جلوگیری از قفل‌شدن سایت قابل ویرایش نیست. برای محدودکردن او از بخش قوانین دسترسی استفاده کنید یا یک نقش «مدیر محدود» بسازید.' );
	}
	$want = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $caps ) ) ) );
	$want[] = 'read';
	$want   = array_unique( $want );
	$have   = array_keys( array_filter( (array) $role->capabilities ) );
	$add    = array_diff( $want, $have );
	$del    = array_diff( $have, $want );

	$ok = gvac_actor_can_grant( $add );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$me = wp_get_current_user();
	if ( in_array( $slug, (array) $me->roles, true ) && ! gvac_is_immune( $me->ID ) && array_intersect( $del, array( 'manage_options', 'gvac_manage' ) ) ) {
		return new WP_Error( 'self', 'نقش خودِ شما است؛ برداشتن این قابلیت دسترسی شما را به این بخش می‌بندد.' );
	}
	foreach ( $add as $c ) {
		$role->add_cap( $c, true );
	}
	foreach ( $del as $c ) {
		$role->remove_cap( $c );
	}
	gvac_log( 'change', 'role.caps', '+' . count( $add ) . ' / −' . count( $del ), $slug );
	return array( 'added' => count( $add ), 'removed' => count( $del ) );
}

function gvac_role_delete( $slug, $reassign = '' ) {
	if ( in_array( $slug, gvac_core_role_slugs(), true ) ) {
		return new WP_Error( 'core', 'نقش‌های پیش‌فرض وردپرس حذف نمی‌شوند.' );
	}
	if ( ! get_role( $slug ) ) {
		return new WP_Error( 'role', 'نقش پیدا نشد.' );
	}
	$to = $reassign && get_role( $reassign ) && $reassign !== $slug ? $reassign : get_option( 'default_role', 'subscriber' );
	foreach ( get_users( array( 'role' => $slug, 'fields' => 'ID', 'number' => -1 ) ) as $uid ) {
		$u = new WP_User( $uid );
		$u->remove_role( $slug );
		if ( ! $u->roles ) {
			$u->add_role( $to );
		}
	}
	remove_role( $slug );
	$all = gvac_all_rules();
	unset( $all['role'][ $slug ] );
	update_option( 'gvac_rules', $all, true );
	update_option( 'gvac_our_roles', array_values( array_diff( gvac_our_roles(), array( $slug ) ) ), false );
	gvac_flush_cache();
	gvac_log( 'change', 'role.delete', '', $slug );
	return true;
}

function gvac_role_clone( $from, $name ) {
	$slug = gvac_role_create( $name, '', array(), $from );
	if ( is_wp_error( $slug ) ) {
		return $slug;
	}
	$all = gvac_all_rules();
	if ( isset( $all['role'][ $from ] ) ) {
		$all['role'][ $slug ] = $all['role'][ $from ];
		update_option( 'gvac_rules', $all, true );
		gvac_flush_cache();
	}
	return $slug;
}

/* --------------------------- قالب‌های آماده --------------------------- */

function gvac_presets() {
	$posts = array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_published_posts', 'edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages', 'delete_pages', 'delete_published_pages', 'upload_files', 'manage_categories' );
	$admin = get_role( 'administrator' );
	$lite  = $admin ? array_keys( array_filter( (array) $admin->capabilities ) ) : array();
	$lite  = array_diff( $lite, array( 'manage_options', 'gvac_manage', 'install_plugins', 'edit_plugins', 'delete_plugins', 'update_plugins', 'install_themes', 'edit_themes', 'delete_themes', 'update_themes', 'create_users', 'delete_users', 'edit_users', 'promote_users', 'remove_users', 'add_users', 'update_core', 'edit_files', 'unfiltered_html', 'administrator' ) );
	return array(
		'content_editor' => array(
			'name' => 'ویرایشگر محتوا', 'desc' => 'نوشتن و ویرایش نوشته و برگه و رسانه؛ بدون ابزار و تنظیمات. ویجت‌های اخبار و سلامت سایت پنهان است.',
			'caps' => array_merge( $posts, array( 'moderate_comments' ) ),
			'rules' => array( 'widgets' => array( 'dashboard_primary' => 'hide', 'dashboard_site_health' => 'hide', 'welcome_panel' => 'hide' ), 'adminbar' => array( 'wp-logo' => 'hide', 'updates' => 'hide' ) ),
		),
		'seo_specialist' => array(
			'name' => 'سئوکار', 'desc' => 'ویرایش محتوا و دسته‌ها. متاباکس نویسنده، دیدگاه و فیلدهای سفارشی پنهان است.',
			'caps' => $posts,
			'rules' => array( 'metaboxes' => array( 'post:authordiv' => 'hide', 'post:commentstatusdiv' => 'hide', 'post:postcustom' => 'hide', 'page:authordiv' => 'hide', 'page:commentstatusdiv' => 'hide', 'page:postcustom' => 'hide' ), 'widgets' => array( 'dashboard_primary' => 'hide' ) ),
		),
		'shop_operator' => array(
			'name' => 'اپراتور فروشگاه', 'desc' => 'مدیریت سفارش‌ها و محصولات ووکامرس بدون دسترسی به تنظیمات سایت.',
			'caps' => array( 'upload_files', 'manage_woocommerce', 'view_woocommerce_reports', 'edit_products', 'edit_others_products', 'edit_published_products', 'publish_products', 'read_private_products', 'edit_shop_orders', 'edit_others_shop_orders', 'read_private_shop_orders', 'publish_shop_orders', 'manage_product_terms', 'edit_product_terms' ),
			'rules' => array( 'widgets' => array( 'dashboard_primary' => 'hide', 'dashboard_site_health' => 'hide' ) ),
		),
		'support_agent' => array(
			'name' => 'پشتیبان', 'desc' => 'مشاهده‌ی محتوا و مدیریت دیدگاه‌ها و تیکت‌ها.',
			'caps' => array( 'read_private_posts', 'read_private_pages', 'moderate_comments', 'edit_posts' ),
			'rules' => array( 'adminbar' => array( 'new-content' => 'hide', 'wp-logo' => 'hide' ) ),
		),
		'viewer' => array(
			'name' => 'مشاهده‌گر (فقط خواندن)', 'desc' => 'فقط می‌تواند وارد شود و ببیند؛ هیچ تغییری نمی‌تواند بدهد.',
			'caps' => array( 'read_private_posts', 'read_private_pages' ),
			'rules' => array( 'adminbar' => array( 'new-content' => 'hide', 'comments' => 'hide' ) ),
		),
		'admin_lite' => array(
			'name' => 'مدیر محدود', 'desc' => 'تقریباً همه‌ی کارهای مدیر؛ بدون نصب/ویرایش افزونه و قالب، بدون مدیریت کاربران. بخش‌های حساس قفل است.',
			'caps' => $lite,
			'rules' => array( 'menu' => array( 'plugins.php' => 'lock', 'themes.php' => 'lock', 'tools.php' => 'lock' ) ),
		),
	);
}

function gvac_role_from_preset( $id ) {
	$p = gvac_presets();
	if ( ! isset( $p[ $id ] ) ) {
		return new WP_Error( 'preset', 'قالب پیدا نشد.' );
	}
	$slug = gvac_role_create( $p[ $id ]['name'], 'gvac_' . $id, $p[ $id ]['caps'] );
	if ( is_wp_error( $slug ) ) {
		return $slug;
	}
	gvac_save_rules( 'role', $slug, gvac_sanitize_rules( $p[ $id ]['rules'], 'role' ) );
	return $slug;
}

/* ------------------------------------------------------------------ */
/* تخصیص نقش به کاربر                                                  */
/* ------------------------------------------------------------------ */

function gvac_user_set_roles( $uid, array $roles, $keep_temp = false ) {
	$u = get_userdata( $uid );
	if ( ! $u ) {
		return new WP_Error( 'user', 'کاربر پیدا نشد.' );
	}
	$roles = array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );
	foreach ( $roles as $r ) {
		if ( ! get_role( $r ) ) {
			return new WP_Error( 'role', 'نقش «' . $r . '» وجود ندارد.' );
		}
	}
	if ( ! $roles ) {
		$roles = array( get_option( 'default_role', 'subscriber' ) );
	}
	if ( is_multisite() && is_super_admin( $uid ) ) {
		return new WP_Error( 'super', 'نقش سوپرادمین شبکه از اینجا تغییر نمی‌کند.' );
	}
	$actor = get_current_user_id();
	if ( $actor && ( gvac_stealth_blocks( $actor, $uid ) ) ) {
		return new WP_Error( 'stealth', 'کاربر پیدا نشد.' );
	}
	$had_admin = in_array( 'administrator', (array) $u->roles, true );
	foreach ( $roles as $r ) {
		if ( ! in_array( $r, (array) $u->roles, true ) ) {
			$cr = get_role( $r );
			$ok = gvac_actor_can_grant( array_keys( array_filter( (array) $cr->capabilities ) ) );
			if ( is_wp_error( $ok ) ) {
				return new WP_Error( 'escalate', 'اجازه‌ی دادن نقش «' . gvac_role_name( $r ) . '» را ندارید؛ ' . $ok->get_error_message() );
			}
		}
	}
	if ( $had_admin && ! in_array( 'administrator', $roles, true ) ) {
		$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 2 ) );
		if ( count( $admins ) < 2 ) {
			return new WP_Error( 'last', 'این آخرین مدیر کل سایت است؛ نقشش را نمی‌شود برداشت.' );
		}
		if ( (int) $uid === $actor ) {
			return new WP_Error( 'self', 'نمی‌توانید نقش مدیر کل را از خودتان بردارید.' );
		}
	}
	$first = array_shift( $roles );
	$u->set_role( $first );
	foreach ( $roles as $r ) {
		$u->add_role( $r );
	}
	if ( ! $keep_temp ) {
		delete_user_meta( $uid, 'gvac_temp' );
	}
	gvac_flush_cache();
	gvac_log( 'change', 'user.roles', implode( ', ', array_merge( array( $first ), $roles ) ), $u->user_login );
	return true;
}

/** نقش موقت: تا تاریخ مشخص نقش جدید، بعد برگشت به نقش‌های قبلی */
function gvac_user_set_temp( $uid, $role, $until ) {
	$u = get_userdata( $uid );
	if ( ! $u || ! get_role( $role ) || $until <= time() ) {
		return new WP_Error( 'temp', 'کاربر، نقش یا زمان پایان نامعتبر است.' );
	}
	$prev = get_user_meta( $uid, 'gvac_temp', true );
	$back = ( is_array( $prev ) && ! empty( $prev['back'] ) ) ? $prev['back'] : (array) $u->roles;
	$r    = gvac_user_set_roles( $uid, array( $role ), true );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	update_user_meta( $uid, 'gvac_temp', array( 'role' => $role, 'until' => (int) $until, 'back' => array_values( $back ) ) );
	gvac_log( 'change', 'user.temp', gvac_role_name( $role ) . ' تا ' . wp_date( 'Y-m-d H:i', $until ), $u->user_login );
	return true;
}

function gvac_user_revert_temp( $uid ) {
	$t = get_user_meta( $uid, 'gvac_temp', true );
	if ( ! is_array( $t ) ) {
		return;
	}
	$back = array_filter( (array) $t['back'], 'get_role' );
	delete_user_meta( $uid, 'gvac_temp' );
	$u = get_userdata( $uid );
	if ( ! $u ) {
		return;
	}
	$back = $back ? array_values( $back ) : array( get_option( 'default_role', 'subscriber' ) );
	$first = array_shift( $back );
	$u->set_role( $first );
	foreach ( $back as $r ) {
		$u->add_role( $r );
	}
	gvac_flush_cache();
	gvac_log( 'system', 'user.temp_end', 'پایان نقش موقت', $u->user_login, 0 );
}

function gvac_expire_temp_roles() {
	$users = get_users( array( 'meta_key' => 'gvac_temp', 'fields' => 'ID', 'number' => 200 ) ); // phpcs:ignore
	foreach ( $users as $uid ) {
		$t = get_user_meta( $uid, 'gvac_temp', true );
		if ( is_array( $t ) && (int) $t['until'] <= time() ) {
			gvac_user_revert_temp( $uid );
		}
	}
}

// بررسی سریع برای خود کاربر لاگین (قبل از کرون)
add_action( 'init', function () {
	if ( is_user_logged_in() ) {
		$t = get_user_meta( get_current_user_id(), 'gvac_temp', true );
		if ( is_array( $t ) && (int) $t['until'] <= time() ) {
			gvac_user_revert_temp( get_current_user_id() );
		}
	}
}, 20 );

/* ------------------------------------------------------------------ */
/* ساخت کاربر و لیست کاربران                                           */
/* ------------------------------------------------------------------ */

function gvac_user_create( $d ) {
	$login = sanitize_user( isset( $d['login'] ) ? $d['login'] : '', true );
	$email = sanitize_email( isset( $d['email'] ) ? $d['email'] : '' );
	$role  = sanitize_key( isset( $d['role'] ) ? $d['role'] : get_option( 'default_role' ) );
	if ( '' === $login || ! is_email( $email ) ) {
		return new WP_Error( 'in', 'نام کاربری و ایمیل معتبر لازم است.' );
	}
	if ( ! get_role( $role ) ) {
		return new WP_Error( 'role', 'نقش نامعتبر است.' );
	}
	$cr = get_role( $role );
	$ok = gvac_actor_can_grant( array_keys( array_filter( (array) $cr->capabilities ) ) );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'cap', 'اجازه‌ی ساخت کاربر را ندارید.' );
	}
	$pass = ! empty( $d['password'] ) ? (string) $d['password'] : wp_generate_password( 14, true, false );
	$id   = wp_insert_user( array(
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => $pass,
		'display_name' => ! empty( $d['name'] ) ? sanitize_text_field( $d['name'] ) : $login,
		'role'         => $role,
	) );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	if ( ! empty( $d['notify'] ) ) {
		wp_new_user_notification( $id, null, 'user' );
	}
	gvac_log( 'change', 'user.create', gvac_role_name( $role ), $login );
	return array( 'id' => $id, 'password' => empty( $d['password'] ) ? $pass : '' );
}

function gvac_users_list( $a ) {
	$per  = 20;
	$page = max( 1, (int) ( isset( $a['page'] ) ? $a['page'] : 1 ) );
	$args = array( 'number' => $per, 'paged' => $page, 'orderby' => 'registered', 'order' => 'DESC', 'count_total' => true );
	if ( ! empty( $a['search'] ) ) {
		$args['search']         = '*' . sanitize_text_field( $a['search'] ) . '*';
		$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
	}
	if ( ! empty( $a['role'] ) ) {
		$args['role'] = sanitize_key( $a['role'] );
	}
	$q     = new WP_User_Query( $args );
	$rules = gvac_all_rules();
	$st    = gvac_stealth_all();
	$items = array();
	foreach ( $q->get_results() as $u ) {
		$t   = get_user_meta( $u->ID, 'gvac_temp', true );
		$ur  = isset( $rules['user'][ $u->ID ] ) ? gvac_count_rules( wp_parse_args( $rules['user'][ $u->ID ], gvac_empty_rules() ) ) : 0;
		$ll  = (int) get_user_meta( $u->ID, 'gvac_last_login', true );
		$items[] = array(
			'id'      => (int) $u->ID,
			'login'   => $u->user_login,
			'name'    => $u->display_name,
			'email'   => $u->user_email,
			'avatar'  => get_avatar_url( $u->ID, array( 'size' => 64 ) ),
			'roles'   => array_map( function ( $r ) {
				return array( 'slug' => $r, 'name' => gvac_role_name( $r ) );
			}, array_values( (array) $u->roles ) ),
			'rules'   => $ur,
			'stealth' => isset( $st[ $u->ID ] ),
			'immune'  => gvac_is_immune( $u->ID ),
			'self'    => (int) $u->ID === get_current_user_id(),
			'temp'    => is_array( $t ) ? array( 'role' => gvac_role_name( $t['role'] ), 'until' => (int) $t['until'] ) : null,
			'last'    => $ll,
		);
	}
	return array( 'items' => $items, 'total' => (int) $q->get_total(), 'pages' => (int) ceil( $q->get_total() / $per ), 'page' => $page );
}
