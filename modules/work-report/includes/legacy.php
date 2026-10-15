<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * انتقال داده‌های بخش قدیمی «گزارش عملکرد سئوی مشتری» به ماژول گزارش کار.
 *
 * تنها فایلی که به جدول‌های قدیمی (gv_sr_* و gv_sync_*) دست می‌زند همین فایل است.
 * انتقال فقط می‌خواند و هیچ چیز را از جدول‌های قدیمی حذف نمی‌کند؛ هر چند بار اجرا شود
 * چیزی تکراری ساخته نمی‌شود (نگاشت قدیمی ← جدید در جدول gvwr_legacy_map نگه داشته می‌شود).
 * بعد از اطمینان از درست بودن داده‌ها، از «تنظیمات ▸ داده‌های قدیمی» جدول‌های قدیمی پاک
 * می‌شوند؛ پس از آن این فایل دیگر کاری ندارد و می‌توانید آن را حذف کنید.
 *
 * نگاشت:
 *  کارمند قدیمی          → کاربر وردپرس با نقش «کارمند» (+ نرخ ساعتی)
 *  پروژه / اعضا / درآمد   → پروژه‌ها (کارفرما = همان کاربر مشتری قدیمی)
 *  گزارش ماهانه           → گزارش دوره‌ای (خلاصه، گام بعدی، رشد صفحات، کلمات همان دوره)
 *  فعالیت‌ها و کارکردها    → گزارش‌ها (ردیف‌های کار) با ساعت‌شان
 *  کلمات کلیدی گزارش‌ها    → کلمات کلیدی و تاریخچه‌ی رتبه
 */

/* ------------------------------------------------------------------ */
/* ابزارهای پایه                                                       */
/* ------------------------------------------------------------------ */

/** نام کامل جدول قدیمی (بدون پیشوند gv_sr_)، یا رشته‌ی خالی اگر وجود ندارد. */
function gvwr_legacy_tn( $name, $prefix = 'gv_sr_' ) {
	global $wpdb;
	$t = $wpdb->prefix . $prefix . $name;
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) === $t ? $t : '';
}

function gvwr_legacy_exists() {
	foreach ( array( 'reports', 'tasks', 'timelogs', 'employees', 'projects', 'keywords' ) as $n ) {
		if ( gvwr_legacy_tn( $n ) ) {
			return true;
		}
	}
	return false;
}

/** تعداد ردیف‌های هر جدول قدیمی (برای نمایش در تنظیمات) */
function gvwr_legacy_counts() {
	global $wpdb;
	$labels = array(
		'reports'         => 'گزارش ماهانه',
		'tasks'           => 'فعالیت',
		'timelogs'        => 'کارکرد روزانه',
		'keywords'        => 'کلمه‌ی کلیدی گزارش',
		'growth'          => 'ردیف رشد صفحات',
		'employees'       => 'کارمند',
		'projects'        => 'پروژه',
		'project_members' => 'عضو پروژه',
		'project_income'  => 'ردیف درآمد پروژه',
	);
	$out = array();
	foreach ( $labels as $n => $label ) {
		$t = gvwr_legacy_tn( $n );
		if ( $t ) {
			$out[ $label ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t`" ); // phpcs:ignore
		}
	}
	return $out;
}

/** خواندن یک جدول قدیمی به‌صورت تکه‌تکه (حافظه‌ی کم). هر بار آرایه‌ای از ردیف‌ها برمی‌گرداند. */
function gvwr_legacy_chunks( $table, $size = 500 ) {
	global $wpdb;
	$last = 0;
	while ( true ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE id > %d ORDER BY id ASC LIMIT %d", $last, $size ) ); // phpcs:ignore
		if ( ! $rows ) {
			return;
		}
		yield $rows;
		$last = (int) end( $rows )->id;
	}
}

function gvwr_lm_get( $kind, $key ) {
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare( 'SELECT new_id FROM ' . gvwr_table( 'legacy_map' ) . ' WHERE kind=%s AND old_key=%s', $kind, (string) $key ) );
	return null === $v ? 0 : (int) $v;
}

function gvwr_lm_set( $kind, $key, $new ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . gvwr_table( 'legacy_map' ) . ' (kind,old_key,new_id) VALUES (%s,%s,%d) ON DUPLICATE KEY UPDATE new_id=VALUES(new_id)', $kind, (string) $key, (int) $new ) );
}

/** همه‌ی نگاشت‌های یک نوع: [old_key => new_id] */
function gvwr_lm_all( $kind ) {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT old_key, new_id FROM ' . gvwr_table( 'legacy_map' ) . ' WHERE kind=%s', $kind ) ) as $r ) {
		$out[ $r->old_key ] = (int) $r->new_id;
	}
	return $out;
}

function gvwr_legacy_date( $d ) {
	$d = substr( (string) $d, 0, 10 );
	return gvwr_valid_date( $d ) ? $d : null;
}

function gvwr_legacy_time( $t ) {
	$t = (string) $t;
	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d/', $t ) ? substr( $t, 0, 5 ) . ':00' : null;
}

function gvwr_legacy_prop( $o, $k, $default = '' ) {
	return isset( $o->$k ) && null !== $o->$k ? $o->$k : $default;
}

/** اولین مدیر کل وردپرس: صاحب ردیف‌هایی که سازنده‌ی مشخصی ندارند */
function gvwr_legacy_fallback_user() {
	static $id = null;
	if ( null === $id ) {
		$a  = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ) );
		$id = $a ? (int) $a[0] : 1;
	}
	return $id;
}

function gvwr_legacy_warn( &$res, $msg ) {
	if ( count( $res['warnings'] ) < 30 ) {
		$res['warnings'][] = $msg;
	}
}

/* ------------------------------------------------------------------ */
/* ۱) کارمندان → کاربران وردپرس                                         */
/* ------------------------------------------------------------------ */

function gvwr_legacy_user_for_employee( $e, &$res ) {
	$name  = trim( (string) gvwr_legacy_prop( $e, 'name' ) );
	$name  = '' !== $name ? $name : 'کارمند ' . (int) $e->id;
	$login = sanitize_user( (string) gvwr_legacy_prop( $e, 'username' ), true );

	// فقط کاربری که همین حالا در ماژول گزارش کار تعریف شده را دوباره به کار می‌بریم (هیچ‌وقت مشتری یا کاربر عادی را به کارمند تبدیل نمی‌کنیم).
	if ( '' !== $login ) {
		$u = get_user_by( 'login', $login );
		if ( $u && array_intersect( array_keys( gvwr_role_labels() ), (array) $u->roles ) && ! get_user_meta( $u->ID, 'gvwr_legacy_employee_id', true ) ) {
			update_user_meta( $u->ID, 'gvwr_legacy_employee_id', (int) $e->id );
			$res['employees_reused']++;
			return (int) $u->ID;
		}
	}

	$base = '' !== $login ? $login : 'emp' . (int) $e->id;
	$try  = $base;
	$n    = 1;
	while ( username_exists( $try ) ) {
		$n++;
		$try = $base . '_' . $n;
	}
	$uid = wp_insert_user(
		array(
			'user_login'   => $try,
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => $name,
			'nickname'     => $name,
			'first_name'   => $name,
			'role'         => 'gvwr_employee',
		)
	);
	if ( is_wp_error( $uid ) ) {
		gvwr_legacy_warn( $res, 'ساخت کاربر برای «' . $name . '» ناموفق بود: ' . $uid->get_error_message() );
		return 0;
	}
	update_user_meta( $uid, 'gvwr_legacy_employee_id', (int) $e->id );

	// رمز قبلی کارمند فعال حفظ می‌شود (با وردپرس ۶٫۸ به بالا همان نام کاربری و رمز قبلی کار می‌کند). کارمند غیرفعال رمز تصادفی می‌گیرد و نمی‌تواند وارد شود.
	$hash = (string) gvwr_legacy_prop( $e, 'password_hash' );
	if ( (int) gvwr_legacy_prop( $e, 'active', 1 ) && preg_match( '/^\$2y\$\d\d\$[.\/A-Za-z0-9]{53}$/', $hash ) ) {
		global $wpdb;
		$wpdb->update( $wpdb->users, array( 'user_pass' => $hash ), array( 'ID' => $uid ) );
		clean_user_cache( $uid );
	}
	$res['employees_created']++;
	return (int) $uid;
}

function gvwr_legacy_import_employees( &$res ) {
	global $wpdb;
	$T = gvwr_legacy_tn( 'employees' );
	if ( ! $T ) {
		return;
	}
	$ET = gvwr_table( 'employees' );
	foreach ( gvwr_legacy_chunks( $T ) as $rows ) {
		foreach ( $rows as $e ) {
			$uid = gvwr_lm_get( 'emp', $e->id );
			if ( ! $uid || ! get_userdata( $uid ) ) {
				$uid = gvwr_legacy_user_for_employee( $e, $res );
				if ( ! $uid ) {
					continue;
				}
				gvwr_lm_set( 'emp', $e->id, $uid );
			}
			$rate = max( 0, (int) gvwr_legacy_prop( $e, 'hourly_rate', 0 ) );
			$ex   = $wpdb->get_row( $wpdb->prepare( "SELECT id, hourly_rate FROM $ET WHERE user_id=%d", $uid ) );
			if ( $ex ) {
				if ( ! (int) $ex->hourly_rate && $rate ) {
					$wpdb->update( $ET, array( 'hourly_rate' => $rate ), array( 'id' => $ex->id ) );
				}
			} else {
				$wpdb->insert( $ET, array( 'user_id' => $uid, 'job_title' => '', 'hourly_rate' => $rate, 'active' => (int) gvwr_legacy_prop( $e, 'active', 1 ) ? 1 : 0, 'created_at' => gvwr_legacy_prop( $e, 'created_at', current_time( 'mysql' ) ) ) );
			}
		}
	}
}

/** کاربر مشتری قدیمی را «کارفرما» می‌کند تا بتواند برگه‌ی گزارش را ببیند (نقش‌های قبلی‌اش حفظ می‌شود). */
function gvwr_legacy_grant_client( $uid ) {
	$u = get_userdata( (int) $uid );
	if ( ! $u || array_intersect( array_merge( array_keys( gvwr_role_labels() ), array( 'administrator' ) ), (array) $u->roles ) ) {
		return;
	}
	$u->add_role( 'gvwr_client' );
}

/* ------------------------------------------------------------------ */
/* ۲) پروژه‌ها، اعضا و درآمد                                            */
/* ------------------------------------------------------------------ */

function gvwr_legacy_client_uid( $uid ) {
	$uid = (int) $uid;
	if ( $uid > 0 && get_userdata( $uid ) ) {
		gvwr_legacy_grant_client( $uid );
		return $uid;
	}
	return -1; // مشتری مشخص نیست → فقط مدیر می‌بیند تا خودتان کارفرما را تعیین کنید
}

/** پروژه‌ی جایگزین برای ردیف‌های قدیمی که پروژه‌ی مشخصی نداشتند (بر اساس نام مشتری) */
function gvwr_legacy_fallback_project( $client_name, $client_uid ) {
	global $wpdb;
	$client_name = trim( (string) $client_name );
	$cu          = gvwr_legacy_client_uid( $client_uid );
	$key         = md5( $client_name . '|' . $cu );
	$id          = gvwr_lm_get( 'cproj', $key );
	if ( $id && gvwr_project( $id ) ) {
		return $id;
	}
	$wpdb->insert(
		gvwr_table( 'projects' ),
		array(
			'name'           => '' !== $client_name ? mb_substr( $client_name, 0, 190 ) : 'بدون پروژه (داده‌ی قبلی)',
			'is_default'     => 0,
			'client_name'    => mb_substr( $client_name, 0, 190 ),
			'client_user_id' => $cu,
			'status'         => 'active',
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$id = (int) $wpdb->insert_id;
	gvwr_lm_set( 'cproj', $key, $id );
	return $id;
}

function gvwr_legacy_import_projects( &$res ) {
	global $wpdb;
	$T = gvwr_legacy_tn( 'projects' );
	if ( ! $T ) {
		return;
	}
	$PT = gvwr_table( 'projects' );
	foreach ( gvwr_legacy_chunks( $T ) as $rows ) {
		foreach ( $rows as $p ) {
			if ( gvwr_lm_get( 'proj', $p->id ) && gvwr_project( gvwr_lm_get( 'proj', $p->id ) ) ) {
				continue;
			}
			$name   = trim( (string) gvwr_legacy_prop( $p, 'title' ) );
			$client = trim( (string) gvwr_legacy_prop( $p, 'client_name' ) );
			$status = (string) gvwr_legacy_prop( $p, 'status', 'active' );
			$prio   = (string) gvwr_legacy_prop( $p, 'priority', 'normal' );
			$health = (string) gvwr_legacy_prop( $p, 'health', 'green' );
			$mgr    = (int) gvwr_lm_get( 'emp', gvwr_legacy_prop( $p, 'manager_employee_id', 0 ) );
			$wpdb->insert(
				$PT,
				array(
					'name'            => mb_substr( '' !== $name ? $name : ( '' !== $client ? $client : 'پروژه ' . (int) $p->id ), 0, 190 ),
					'is_default'      => 0,
					'client_name'     => mb_substr( $client, 0, 190 ),
					'client_user_id'  => gvwr_legacy_client_uid( gvwr_legacy_prop( $p, 'user_id', 0 ) ),
					'manager_user_id' => $mgr,
					'status'          => isset( gvwr_project_statuses()[ $status ] ) ? $status : 'active',
					'priority'        => isset( gvwr_project_priorities()[ $prio ] ) ? $prio : 'normal',
					'health'          => isset( gvwr_project_healths()[ $health ] ) ? $health : 'green',
					'progress'        => max( 0, min( 100, (int) gvwr_legacy_prop( $p, 'progress', 0 ) ) ),
					'start_date'      => gvwr_legacy_date( gvwr_legacy_prop( $p, 'start_date', '' ) ),
					'end_date'        => gvwr_legacy_date( gvwr_legacy_prop( $p, 'end_date', '' ) ),
					'description'     => wp_kses_post( (string) gvwr_legacy_prop( $p, 'description', '' ) ),
					'created_at'      => gvwr_legacy_prop( $p, 'created_at', current_time( 'mysql' ) ),
				)
			);
			$new = (int) $wpdb->insert_id;
			if ( ! $new ) {
				gvwr_legacy_warn( $res, 'انتقال پروژه‌ی «' . $name . '» ناموفق بود.' );
				continue;
			}
			gvwr_lm_set( 'proj', $p->id, $new );
			$res['projects']++;
		}
	}

	$MT = gvwr_legacy_tn( 'project_members' );
	if ( $MT ) {
		foreach ( gvwr_legacy_chunks( $MT ) as $rows ) {
			foreach ( $rows as $m ) {
				$pid = gvwr_lm_get( 'proj', $m->project_id );
				$uid = gvwr_lm_get( 'emp', $m->employee_id );
				if ( ! $pid || ! $uid ) {
					continue;
				}
				$ex = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . gvwr_table( 'project_members' ) . ' WHERE project_id=%d AND user_id=%d', $pid, $uid ) );
				if ( ! $ex ) {
					$wpdb->insert( gvwr_table( 'project_members' ), array( 'project_id' => $pid, 'user_id' => $uid, 'role_label' => mb_substr( (string) gvwr_legacy_prop( $m, 'role', '' ), 0, 120 ) ) );
					$res['members']++;
				}
			}
		}
	}

	$IT = gvwr_legacy_tn( 'project_income' );
	if ( $IT ) {
		foreach ( gvwr_legacy_chunks( $IT ) as $rows ) {
			foreach ( $rows as $i ) {
				if ( gvwr_lm_get( 'income', $i->id ) ) {
					continue;
				}
				$pid = gvwr_lm_get( 'proj', $i->project_id );
				$d   = gvwr_legacy_date( gvwr_legacy_prop( $i, 'income_date', '' ) );
				if ( ! $pid || ! $d ) {
					continue;
				}
				$wpdb->insert( gvwr_table( 'project_income' ), array( 'project_id' => $pid, 'amount' => (int) $i->amount, 'income_date' => $d, 'note' => mb_substr( (string) gvwr_legacy_prop( $i, 'note', '' ), 0, 500 ), 'created_at' => gvwr_legacy_prop( $i, 'created_at', current_time( 'mysql' ) ) ) );
				gvwr_lm_set( 'income', $i->id, (int) $wpdb->insert_id );
				$res['income']++;
			}
		}
	}
}

/* ------------------------------------------------------------------ */
/* ۳) گزارش‌های ماهانه → گزارش‌های دوره‌ای                              */
/* ------------------------------------------------------------------ */

function gvwr_legacy_import_periods( &$res ) {
	global $wpdb;
	$T = gvwr_legacy_tn( 'reports' );
	if ( ! $T ) {
		return;
	}
	$KT = gvwr_legacy_tn( 'keywords' );
	$GT = gvwr_legacy_tn( 'growth' );
	foreach ( gvwr_legacy_chunks( $T ) as $rows ) {
		foreach ( $rows as $r ) {
			if ( gvwr_lm_get( 'period', $r->id ) ) {
				continue;
			}
			$ps = gvwr_legacy_date( gvwr_legacy_prop( $r, 'period_start', '' ) );
			$pe = gvwr_legacy_date( gvwr_legacy_prop( $r, 'period_end', '' ) );
			if ( ! $ps || ! $pe ) {
				$res['skipped']++;
				gvwr_legacy_warn( $res, 'گزارش ماهانه‌ی شماره‌ی ' . (int) $r->id . ' تاریخ معتبر نداشت و منتقل نشد.' );
				continue;
			}
			$pid = (int) gvwr_lm_get( 'proj', gvwr_legacy_prop( $r, 'project_id', 0 ) );
			if ( ! $pid || ! gvwr_project( $pid ) ) {
				$pid = gvwr_legacy_fallback_project( gvwr_legacy_prop( $r, 'client_name', '' ), gvwr_legacy_prop( $r, 'user_id', 0 ) );
			}
			$cu = (int) gvwr_legacy_prop( $r, 'user_id', 0 );
			if ( $cu > 0 && ! get_userdata( $cu ) ) {
				$cu = 0;
			}
			if ( $cu > 0 ) {
				gvwr_legacy_grant_client( $cu );
			}
			$vis = json_decode( (string) gvwr_legacy_prop( $r, 'visibility', '' ), true );
			$now = current_time( 'mysql' );
			$wpdb->insert(
				gvwr_table( 'periods' ),
				array(
					'project_id'     => $pid,
					'client_user_id' => $cu,
					'client_name'    => mb_substr( (string) gvwr_legacy_prop( $r, 'client_name', '' ), 0, 190 ),
					'title'          => mb_substr( (string) gvwr_legacy_prop( $r, 'title', '' ), 0, 255 ),
					'period_start'   => $ps,
					'period_end'     => $pe,
					'summary'        => wp_kses_post( (string) gvwr_legacy_prop( $r, 'summary', '' ) ),
					'next_steps'     => wp_kses_post( (string) gvwr_legacy_prop( $r, 'next_steps', '' ) ),
					'hours_spent'    => (float) gvwr_legacy_prop( $r, 'hours_spent', 0 ),
					'status'         => 'published' === gvwr_legacy_prop( $r, 'status', 'draft' ) ? 'published' : 'draft',
					'visibility'     => is_array( $vis ) ? wp_json_encode( $vis ) : '',
					'created_at'     => gvwr_legacy_prop( $r, 'created_at', $now ),
					'updated_at'     => gvwr_legacy_prop( $r, 'updated_at', $now ),
				)
			);
			$new = (int) $wpdb->insert_id;
			if ( ! $new ) {
				gvwr_legacy_warn( $res, 'انتقال گزارش ماهانه‌ی شماره‌ی ' . (int) $r->id . ' ناموفق بود.' );
				continue;
			}
			gvwr_lm_set( 'period', $r->id, $new );
			$res['periods']++;

			if ( $KT ) {
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$KT` WHERE report_id=%d ORDER BY sort_order ASC, id ASC", $r->id ) ) as $k ) { // phpcs:ignore
					$wpdb->insert( gvwr_table( 'period_keywords' ), array( 'period_id' => $new, 'keyword' => mb_substr( (string) $k->keyword, 0, 190 ), 'search_engine' => mb_substr( (string) gvwr_legacy_prop( $k, 'search_engine', '' ), 0, 50 ), 'page_url' => mb_substr( (string) gvwr_legacy_prop( $k, 'page_url', '' ), 0, 500 ), 'prev_rank' => (int) gvwr_legacy_prop( $k, 'prev_rank', 0 ), 'curr_rank' => (int) gvwr_legacy_prop( $k, 'curr_rank', 0 ), 'note' => mb_substr( (string) gvwr_legacy_prop( $k, 'note', '' ), 0, 500 ), 'sort_order' => (int) gvwr_legacy_prop( $k, 'sort_order', 0 ) ) );
					$res['period_keywords']++;
				}
			}
			if ( $GT ) {
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$GT` WHERE report_id=%d ORDER BY id ASC", $r->id ) ) as $g ) { // phpcs:ignore
					$wpdb->insert( gvwr_table( 'period_growth' ), array( 'period_id' => $new, 'page_title' => mb_substr( (string) gvwr_legacy_prop( $g, 'page_title', '' ), 0, 255 ), 'page_url' => mb_substr( (string) gvwr_legacy_prop( $g, 'page_url', '' ), 0, 500 ), 'metric_label' => mb_substr( (string) gvwr_legacy_prop( $g, 'metric_label', '' ), 0, 120 ), 'before_value' => mb_substr( (string) gvwr_legacy_prop( $g, 'before_value', '' ), 0, 50 ), 'after_value' => mb_substr( (string) gvwr_legacy_prop( $g, 'after_value', '' ), 0, 50 ), 'note' => mb_substr( (string) gvwr_legacy_prop( $g, 'note', '' ), 0, 500 ) ) );
					$res['period_growth']++;
				}
			}
		}
	}
}

/* ------------------------------------------------------------------ */
/* ۴) فعالیت‌ها و کارکردها → گزارش‌ها (ردیف‌های کار)                      */
/* ------------------------------------------------------------------ */

/** نوع فعالیت قدیمی → شناسه‌ی دسته‌بندی جدید (در صورت نبودن، دسته ساخته می‌شود) */
function gvwr_legacy_cat_id( $type ) {
	static $cache = array();
	if ( isset( $cache[ $type ] ) ) {
		return $cache[ $type ];
	}
	global $wpdb;
	$defs = array(
		'content_new'    => array( 'تولید محتوای جدید', 'محتوا', '✍️', '#0f766e' ),
		'content_update' => array( 'بروزرسانی محتوا', 'محتوا', '🔄', '#0891b2' ),
		'technical'      => array( 'اصلاح فنی سئو', 'سئو', '🛠️', '#7c3aed' ),
		'backlink'       => array( 'بک‌لینک و لینک‌سازی خارجی', 'سئو', '🌐', '#7c3aed' ),
		'onpage'         => array( 'سئو داخلی (On-Page)', 'سئو', '🔎', '#1d4ed8' ),
		'search_console' => array( 'گزارش سرچ کنسول / آنالیتیکس', 'سئو', '📊', '#0d9488' ),
		'meeting'        => array( 'جلسه / هماهنگی', 'سایر', '🗣️', '#4f46e5' ),
		'other'          => array( 'سایر کارها', 'سایر', '🧩', '#94a3b8' ),
	);
	$d  = isset( $defs[ $type ] ) ? $defs[ $type ] : $defs['other'];
	$CT = gvwr_table( 'categories' );
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $CT WHERE name=%s ORDER BY active DESC, id ASC LIMIT 1", $d[0] ) );
	if ( ! $id ) {
		$wpdb->insert( $CT, array( 'name' => $d[0], 'grp' => $d[1], 'icon' => $d[2], 'color' => $d[3], 'sort_order' => 100, 'active' => 1 ) );
		$id = (int) $wpdb->insert_id;
	}
	$cache[ $type ] = $id;
	return $id;
}

function gvwr_legacy_type_label( $type ) {
	$n = array( 'content_new' => 'تولید محتوای جدید', 'content_update' => 'بروزرسانی محتوای قبلی', 'technical' => 'اصلاح فنی سئو', 'backlink' => 'لینک‌سازی', 'onpage' => 'بهینه‌سازی داخل صفحه', 'search_console' => 'سرچ کنسول / آنالیتیکس', 'meeting' => 'جلسه / هماهنگی', 'other' => 'سایر فعالیت‌ها' );
	return isset( $n[ $type ] ) ? $n[ $type ] : $n['other'];
}

function gvwr_legacy_import_items( &$res ) {
	global $wpdb;
	$TT = gvwr_legacy_tn( 'tasks' );
	$LT = gvwr_legacy_tn( 'timelogs' );
	$IT = gvwr_table( 'items' );
	$fb = gvwr_legacy_fallback_user();

	// پروژه‌ی هر گزارش ماهانه و وضعیت انتشارش
	$period_of  = gvwr_lm_all( 'period' ); // old report id → new period id
	$period_row = array();
	foreach ( (array) $wpdb->get_results( 'SELECT id, project_id, status FROM ' . gvwr_table( 'periods' ) ) as $p ) {
		$period_row[ (int) $p->id ] = $p;
	}
	$author = array();
	$RT     = gvwr_legacy_tn( 'reports' );
	if ( $RT ) {
		foreach ( (array) $wpdb->get_results( "SELECT id, author_id, created_at FROM `$RT`" ) as $r ) { // phpcs:ignore
			$author[ (int) $r->id ] = $r;
		}
	}
	$emp_map = gvwr_lm_all( 'emp' );

	$done_task = gvwr_lm_all( 'task' );
	$done_log  = gvwr_lm_all( 'log' );

	$tasks_per_log = array();
	if ( $TT ) {
		foreach ( (array) $wpdb->get_results( "SELECT source_timelog_id sid, COUNT(*) c FROM `$TT` WHERE source_timelog_id>0 GROUP BY source_timelog_id" ) as $r ) { // phpcs:ignore
			$tasks_per_log[ (int) $r->sid ] = (int) $r->c;
		}
	}

	// --- فعالیت‌ها
	if ( $TT ) {
		foreach ( gvwr_legacy_chunks( $TT ) as $rows ) {
			$ids = array();
			foreach ( $rows as $t ) {
				if ( (int) gvwr_legacy_prop( $t, 'source_timelog_id', 0 ) > 0 ) {
					$ids[ (int) $t->source_timelog_id ] = true;
				}
			}
			$logs = array();
			if ( $ids && $LT ) {
				foreach ( (array) $wpdb->get_results( "SELECT * FROM `$LT` WHERE id IN (" . implode( ',', array_keys( $ids ) ) . ')' ) as $l ) { // phpcs:ignore
					$logs[ (int) $l->id ] = $l;
				}
			}
			foreach ( $rows as $t ) {
				if ( isset( $done_task[ (string) $t->id ] ) ) {
					continue;
				}
				$date = gvwr_legacy_date( gvwr_legacy_prop( $t, 'work_date', '' ) );
				if ( ! $date ) {
					$res['skipped']++;
					continue;
				}
				$rid = (int) gvwr_legacy_prop( $t, 'report_id', 0 );
				$pid = isset( $period_of[ (string) $rid ] ) ? (int) $period_of[ (string) $rid ] : 0;
				$prw = $pid && isset( $period_row[ $pid ] ) ? $period_row[ $pid ] : null;
				$log = isset( $logs[ (int) gvwr_legacy_prop( $t, 'source_timelog_id', 0 ) ] ) ? $logs[ (int) $t->source_timelog_id ] : null;

				if ( $log && isset( $emp_map[ (string) $log->employee_id ] ) ) {
					$uid = (int) $emp_map[ (string) $log->employee_id ];
				} elseif ( isset( $author[ $rid ] ) && (int) $author[ $rid ]->author_id > 0 && get_userdata( (int) $author[ $rid ]->author_id ) ) {
					$uid = (int) $author[ $rid ]->author_id;
				} else {
					$uid = $fb;
				}

				$title = trim( (string) gvwr_legacy_prop( $t, 'title', '' ) );
				$note  = trim( (string) gvwr_legacy_prop( $t, 'note', '' ) );
				$desc  = '' !== $title ? $title : ( '' !== $note ? $note : gvwr_legacy_type_label( (string) gvwr_legacy_prop( $t, 'task_type', 'other' ) ) );
				if ( '' !== $note && $note !== $desc ) {
					$desc .= "\n" . $note;
				}
				$kw = trim( (string) gvwr_legacy_prop( $t, 'target_keyword', '' ) );
				if ( '' !== $kw ) {
					$desc .= "\nکلمه‌ی هدف: " . $kw;
				}
				$hours = (float) gvwr_legacy_prop( $t, 'hours', 0 );
				$single = $log && isset( $tasks_per_log[ (int) $log->id ] ) && 1 === $tasks_per_log[ (int) $log->id ];
				$st     = $single ? gvwr_legacy_time( gvwr_legacy_prop( $log, 'start_time', '' ) ) : null;
				$en     = $single ? gvwr_legacy_time( gvwr_legacy_prop( $log, 'end_time', '' ) ) : null;
				if ( ! $st || ! $en || $en <= $st ) {
					$st = null;
					$en = null;
				}
				$created = $log ? gvwr_legacy_prop( $log, 'created_at', '' ) : ( isset( $author[ $rid ] ) ? $author[ $rid ]->created_at : '' );
				$created = $created ? $created : $date . ' 12:00:00';
				$project = $prw ? (int) $prw->project_id : 0;
				if ( ! $project && $log ) {
					$project = (int) gvwr_lm_get( 'proj', gvwr_legacy_prop( $log, 'project_id', 0 ) );
				}
				if ( ! $project ) {
					$project = gvwr_legacy_fallback_project( $log ? gvwr_legacy_prop( $log, 'client_name', '' ) : '', 0 );
				}
				$ok = $wpdb->insert(
					$IT,
					array(
						'user_id'      => $uid,
						'project_id'   => $project,
						'category_id'  => gvwr_legacy_cat_id( (string) gvwr_legacy_prop( $t, 'task_type', 'other' ) ),
						'work_date'    => $date,
						'description'  => mb_substr( $desc, 0, 3000 ),
						'url'          => mb_substr( esc_url_raw( (string) gvwr_legacy_prop( $t, 'url', '' ) ), 0, 500 ),
						'qty'          => 1,
						'start_time'   => $st,
						'end_time'     => $en,
						'duration_min' => $hours > 0 ? (int) round( $hours * 60 ) : null,
						'screenshots'  => '',
						'period_id'    => $pid,
						'status'       => $prw && 'published' === $prw->status ? 'approved' : 'pending', // پیش‌نویس‌ها به مشتری نمی‌رسیدند؛ حالا هم تا تأیید مدیر نمی‌رسند.
						'manager_note' => '',
						'batch'        => 'legacy',
						'created_at'   => $created,
						'updated_at'   => $created,
					)
				);
				if ( $ok ) {
					gvwr_lm_set( 'task', $t->id, (int) $wpdb->insert_id );
					$done_task[ (string) $t->id ] = (int) $wpdb->insert_id;
					$res['items']++;
				} else {
					gvwr_legacy_warn( $res, 'انتقال فعالیت شماره‌ی ' . (int) $t->id . ' ناموفق بود.' );
				}
			}
		}
	}

	// --- کارکردهای روزانه‌ای که هیچ فعالیتی از آن‌ها ساخته نشده بود (بیشتر: تایمر یا ساعت بدون شرح)
	if ( $LT ) {
		foreach ( gvwr_legacy_chunks( $LT ) as $rows ) {
			foreach ( $rows as $l ) {
				if ( isset( $done_log[ (string) $l->id ] ) || ! empty( $tasks_per_log[ (int) $l->id ] ) ) {
					continue;
				}
				$date = gvwr_legacy_date( gvwr_legacy_prop( $l, 'work_date', '' ) );
				if ( ! $date ) {
					$res['skipped']++;
					continue;
				}
				$uid = isset( $emp_map[ (string) $l->employee_id ] ) ? (int) $emp_map[ (string) $l->employee_id ] : $fb;
				$pid = (int) gvwr_lm_get( 'proj', gvwr_legacy_prop( $l, 'project_id', 0 ) );
				$has_project = (bool) $pid;
				if ( ! $pid ) {
					$pid = gvwr_legacy_fallback_project( gvwr_legacy_prop( $l, 'client_name', '' ), 0 );
				}
				$note = trim( (string) gvwr_legacy_prop( $l, 'note', '' ) );
				$desc = '' !== $note ? $note : 'کارکرد ثبت‌شده (بدون شرح)';
				$st   = gvwr_legacy_time( gvwr_legacy_prop( $l, 'start_time', '' ) );
				$en   = gvwr_legacy_time( gvwr_legacy_prop( $l, 'end_time', '' ) );
				if ( ! $st || ! $en || $en <= $st ) {
					$st = null;
					$en = null;
				}
				$hours   = (float) gvwr_legacy_prop( $l, 'hours', 0 );
				$created = gvwr_legacy_prop( $l, 'created_at', $date . ' 12:00:00' );
				// کارکرد بدون شرح و متصل به یک پروژه‌ی مشتری، تا بازبینی مدیر برای کارفرما نمایش داده نمی‌شود.
				$status = ( '' === $note && $has_project ) ? 'pending' : 'approved';
				$ok     = $wpdb->insert(
					$IT,
					array(
						'user_id'      => $uid,
						'project_id'   => $pid,
						'category_id'  => gvwr_legacy_cat_id( (string) gvwr_legacy_prop( $l, 'activity_type', 'other' ) ),
						'work_date'    => $date,
						'description'  => mb_substr( $desc, 0, 3000 ),
						'url'          => '',
						'qty'          => 1,
						'start_time'   => $st,
						'end_time'     => $en,
						'duration_min' => $hours > 0 ? (int) round( $hours * 60 ) : null,
						'screenshots'  => '',
						'period_id'    => 0,
						'status'       => $status,
						'manager_note' => '',
						'batch'        => 'legacy',
						'created_at'   => $created,
						'updated_at'   => $created,
					)
				);
				if ( $ok ) {
					gvwr_lm_set( 'log', $l->id, (int) $wpdb->insert_id );
					$done_log[ (string) $l->id ] = (int) $wpdb->insert_id;
					$res['items_logs']++;
				} else {
					gvwr_legacy_warn( $res, 'انتقال کارکرد شماره‌ی ' . (int) $l->id . ' ناموفق بود.' );
				}
			}
		}
	}
}

/* ------------------------------------------------------------------ */
/* ۵) کلمات کلیدی گزارش‌ها → کلمات کلیدی و تاریخچه‌ی رتبه                  */
/* ------------------------------------------------------------------ */

function gvwr_legacy_import_keywords( &$res ) {
	global $wpdb;
	$KT = gvwr_legacy_tn( 'keywords' );
	$RT = gvwr_legacy_tn( 'reports' );
	if ( ! $KT || ! $RT ) {
		return;
	}
	$period_of  = gvwr_lm_all( 'period' );
	$period_prj = array();
	foreach ( (array) $wpdb->get_results( 'SELECT id, project_id FROM ' . gvwr_table( 'periods' ) ) as $p ) {
		$period_prj[ (int) $p->id ] = (int) $p->project_id;
	}
	$kwT   = gvwr_table( 'keywords' );
	$rkT   = gvwr_table( 'ranks' );
	$today = gvwr_today();
	$off   = 0;
	while ( true ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT k.*, r.period_start r_start, r.period_end r_end, r.updated_at r_upd, r.author_id r_author FROM `$KT` k INNER JOIN `$RT` r ON r.id=k.report_id ORDER BY r.period_end ASC, k.id ASC LIMIT 500 OFFSET %d", $off ) ); // phpcs:ignore
		if ( ! $rows ) {
			break;
		}
		$off += 500;
		foreach ( $rows as $k ) {
			$word = trim( preg_replace( '/\s+/u', ' ', (string) $k->keyword ) );
			$np   = isset( $period_of[ (string) $k->report_id ] ) ? (int) $period_of[ (string) $k->report_id ] : 0;
			$pid  = $np && isset( $period_prj[ $np ] ) ? $period_prj[ $np ] : 0;
			if ( '' === $word || ! $pid ) {
				continue;
			}
			$word = mb_substr( $word, 0, 180 );
			$url  = mb_substr( esc_url_raw( (string) gvwr_legacy_prop( $k, 'page_url', '' ) ), 0, 500 );
			$kid  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $kwT WHERE project_id=%d AND keyword=%s", $pid, $word ) );
			if ( ! $kid ) {
				$wpdb->insert( $kwT, array( 'project_id' => $pid, 'keyword' => $word, 'target_url' => $url, 'active' => 1, 'created_at' => current_time( 'mysql' ) ) );
				$kid = (int) $wpdb->insert_id;
				if ( $kid ) {
					$res['keywords']++;
				}
			} elseif ( '' !== $url ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $kwT SET target_url=%s WHERE id=%d AND target_url=''", $url, $kid ) );
			}
			if ( ! $kid ) {
				continue;
			}
			$end   = gvwr_legacy_date( $k->r_end );
			$start = gvwr_legacy_date( $k->r_start );
			$upd   = gvwr_legacy_date( $k->r_upd );
			$by    = (int) $k->r_author;
			// رتبه‌ی «فعلی» به تاریخ پایان دوره ثبت می‌شود (برای دوره‌ی جاری: آخرین ویرایش گزارش) و رتبه‌ی «قبلی» به روز قبل از شروع دوره.
			$when = array();
			if ( (int) $k->curr_rank > 0 && $end ) {
				$d = $end;
				if ( $upd && $upd < $d ) {
					$d = $upd;
				}
				$when[ min( $d, $today ) ] = (int) $k->curr_rank;
			}
			if ( (int) $k->prev_rank > 0 && $start ) {
				$d = gvwr_add_days( $start, -1 );
				if ( $d <= $today && ! isset( $when[ $d ] ) ) {
					$when[ $d ] = (int) $k->prev_rank;
				}
			}
			foreach ( $when as $d => $pos ) {
				if ( $pos > 9999 || $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $rkT WHERE keyword_id=%d AND rank_date=%s", $kid, $d ) ) ) {
					continue; // برای آن روز رتبه‌ای هست؛ رتبه‌ی ثبت‌شده‌ی موجود را دست نمی‌زنیم
				}
				$wpdb->insert( $rkT, array( 'keyword_id' => $kid, 'user_id' => max( 0, $by ), 'rank_pos' => $pos, 'rank_date' => $d, 'created_at' => current_time( 'mysql' ) ) );
				$res['ranks']++;
			}
		}
	}
}

/* ------------------------------------------------------------------ */
/* اجرا                                                                */
/* ------------------------------------------------------------------ */

function gvwr_legacy_import() {
	if ( ! gvwr_legacy_exists() ) {
		return array( 'ok' => false, 'msg' => 'داده‌ی قدیمی پیدا نشد.' );
	}
	if ( get_transient( 'gvwr_legacy_lock' ) ) {
		return array( 'ok' => false, 'msg' => 'انتقال همین حالا در حال اجراست؛ چند دقیقه بعد دوباره تلاش کنید.' );
	}
	set_transient( 'gvwr_legacy_lock', 1, 10 * MINUTE_IN_SECONDS );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
	}
	$res = array(
		'employees_created' => 0, 'employees_reused' => 0, 'projects' => 0, 'members' => 0, 'income' => 0,
		'periods' => 0, 'period_keywords' => 0, 'period_growth' => 0, 'items' => 0, 'items_logs' => 0,
		'keywords' => 0, 'ranks' => 0, 'skipped' => 0, 'warnings' => array(),
	);
	try {
		gvwr_maybe_upgrade(); // اطمینان از وجود جدول‌های جدید
		gvwr_legacy_import_employees( $res );
		gvwr_legacy_import_projects( $res );
		gvwr_legacy_import_periods( $res );
		gvwr_legacy_import_items( $res );
		gvwr_legacy_import_keywords( $res );
		gvwr_legacy_housekeeping();
		update_option( 'gvwr_legacy_import', array( 'at' => current_time( 'mysql' ), 'result' => $res ), false );
	} finally {
		delete_transient( 'gvwr_legacy_lock' );
	}
	$res['ok'] = true;
	return $res;
}

/** زمان‌بندی همگام‌سازی چندسایتی قدیمی را برمی‌دارد (دیگر کدی برای اجرای آن وجود ندارد). */
function gvwr_legacy_housekeeping() {
	wp_clear_scheduled_hook( 'gv_sync_cron_push' );
}

/** بعد از به‌روزرسانی افزونه، اولین ورود مدیر کل به پیشخوان، انتقال را خودکار انجام می‌دهد. */
add_action( 'admin_init', 'gvwr_legacy_maybe_auto_import' );
function gvwr_legacy_maybe_auto_import() {
	if ( wp_doing_ajax() || wp_doing_cron() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( get_option( 'gvwr_legacy_import' ) || (int) get_option( 'gvwr_legacy_attempts', 0 ) >= 3 || ! gvwr_legacy_exists() ) {
		return;
	}
	update_option( 'gvwr_legacy_attempts', (int) get_option( 'gvwr_legacy_attempts', 0 ) + 1, false ); // اگر خطای مرگبار رخ دهد، پیشخوان در حلقه نمی‌افتد
	gvwr_legacy_import();
}

/** پاک‌سازی نهایی: جدول‌ها و تنظیمات بخش قدیمی حذف می‌شوند. فقط بعد از یک انتقال موفق. */
function gvwr_legacy_drop() {
	global $wpdb;
	$imp = get_option( 'gvwr_legacy_import' );
	if ( ! $imp ) {
		return array( 'ok' => false, 'msg' => 'ابتدا انتقال داده را اجرا کنید.' );
	}
	foreach ( array( 'reports', 'keywords', 'tasks', 'growth', 'employees', 'timelogs', 'active_timers', 'projects', 'project_members', 'project_income', 'admins' ) as $n ) {
		$t = gvwr_legacy_tn( $n );
		if ( $t ) {
			$wpdb->query( "DROP TABLE IF EXISTS `$t`" ); // phpcs:ignore
		}
	}
	foreach ( array( 'hub_nodes', 'remote_logs', 'hub_rates' ) as $n ) {
		$t = gvwr_legacy_tn( $n, 'gv_sync_' );
		if ( $t ) {
			$wpdb->query( "DROP TABLE IF EXISTS `$t`" ); // phpcs:ignore
		}
	}
	foreach ( array( 'gv_sr_db_version', 'gv_sync_db_version', 'gv_sync_hub_enabled', 'gv_sync_node_settings', 'gvwr_legacy_attempts' ) as $o ) {
		delete_option( $o );
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_gv\\_sr\\_%' OR option_name LIKE '\\_transient\\_timeout\\_gv\\_sr\\_%'" ); // phpcs:ignore
	gvwr_legacy_housekeeping();
	$wpdb->query( 'DROP TABLE IF EXISTS ' . gvwr_table( 'legacy_map' ) ); // phpcs:ignore
	update_option( 'gvwr_legacy_import', array_merge( (array) $imp, array( 'dropped_at' => current_time( 'mysql' ) ) ), false );
	return array( 'ok' => true );
}

/* ------------------------------------------------------------------ */
/* کارت «داده‌های قدیمی» در تنظیمات (فقط مدیر کل)                         */
/* ------------------------------------------------------------------ */

function gvwr_legacy_card_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$imp    = get_option( 'gvwr_legacy_import' );
	$exists = gvwr_legacy_exists();
	if ( ! $exists && ! $imp ) {
		return '';
	}
	ob_start();
	?>
	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>داده‌های بخش قدیمی «گزارش عملکرد سئو»</h3></div>
		<?php if ( $exists ) : ?>
			<p class="gvwr-muted">این داده‌ها هنوز در دیتابیس هستند:
			<?php
			$parts = array();
			foreach ( gvwr_legacy_counts() as $label => $n ) {
				$parts[] = gvwr_fa( $n ) . ' ' . $label;
			}
			echo esc_html( implode( '، ', $parts ) );
			?>
			</p>
		<?php endif; ?>
		<?php if ( $imp ) : $r = isset( $imp['result'] ) ? (array) $imp['result'] : array(); ?>
			<div class="gvwr-alert" style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46">
				✅ انتقال داده انجام شده است (<?php echo esc_html( gvwr_fa( $imp['at'] ) ); ?>):
				<?php
				echo esc_html(
					gvwr_fa( isset( $r['items'] ) ? (int) $r['items'] + (int) $r['items_logs'] : 0 ) . ' گزارش کار، ' .
					gvwr_fa( isset( $r['periods'] ) ? (int) $r['periods'] : 0 ) . ' گزارش ماهانه، ' .
					gvwr_fa( isset( $r['ranks'] ) ? (int) $r['ranks'] : 0 ) . ' رتبه‌ی کلمه، ' .
					gvwr_fa( isset( $r['projects'] ) ? (int) $r['projects'] : 0 ) . ' پروژه، ' .
					gvwr_fa( isset( $r['employees_created'] ) ? (int) $r['employees_created'] + (int) $r['employees_reused'] : 0 ) . ' کارمند'
				);
				?>
				<?php if ( ! empty( $r['warnings'] ) ) : ?>
					<ul style="margin:8px 18px 0;list-style:disc"><?php foreach ( $r['warnings'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $exists ) : ?>
			<p>رمز کارمندان قدیمی حفظ شده است؛ هر کارمند با همان نام کاربری و رمز قبلی از <b>صفحه‌ی ورود وردپرس</b> وارد می‌شود (با وردپرس ۶٫۸ یا بالاتر؛ در نسخه‌های قدیمی‌تر رمز را از «کاربران» وردپرس تغییر دهید). مشتریان قدیمی نقش «کارفرما» گرفته‌اند.</p>
			<div class="gvwr-formfoot" style="justify-content:flex-start">
				<button type="button" class="gvwr-btn ghost" data-act="legacy-run"><?php echo $imp ? 'اجرای دوباره‌ی انتقال (بدون تکرار)' : 'انتقال داده‌ها'; ?></button>
				<?php if ( $imp ) : ?><button type="button" class="gvwr-btn danger" data-act="legacy-drop">پاک‌سازی نهایی جدول‌های قدیمی</button><?php endif; ?>
			</div>
			<p class="gvwr-muted">«پاک‌سازی نهایی» جدول‌های قدیمی را برای همیشه حذف می‌کند؛ فقط بعد از اینکه گزارش‌ها و رتبه‌ها را در بخش‌های جدید دیدید و مطمئن شدید، انجامش بدهید.</p>
		<?php else : ?>
			<p class="gvwr-muted">جدول‌های قدیمی پاک شده‌اند و چیزی برای انجام نیست. اگر خواستید می‌توانید فایل <code>includes/legacy.php</code> را هم از ماژول حذف کنید.</p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
