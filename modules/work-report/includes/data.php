<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* پروژه، دسته‌بندی، کارمندان                                          */
/* ------------------------------------------------------------------ */

function gvwr_projects() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . gvwr_table( 'projects' ) . ' ORDER BY is_default DESC, id ASC' );
}

function gvwr_default_project_id() {
	global $wpdb;
	$id = (int) $wpdb->get_var( 'SELECT id FROM ' . gvwr_table( 'projects' ) . ' ORDER BY is_default DESC, id ASC LIMIT 1' );
	return $id ? $id : 1;
}

function gvwr_categories( $active_only = true ) {
	global $wpdb;
	$w = $active_only ? 'WHERE active=1' : '';
	return $wpdb->get_results( 'SELECT * FROM ' . gvwr_table( 'categories' ) . " $w ORDER BY sort_order ASC, id ASC" );
}

function gvwr_cat_map() {
	$m = array();
	foreach ( gvwr_categories( false ) as $c ) {
		$m[ (int) $c->id ] = $c;
	}
	return $m;
}

/** کارمندان تعریف‌شده (با نام کاربری وردپرس) */
function gvwr_employees( $active_only = false ) {
	global $wpdb;
	$w = $active_only ? 'WHERE e.active=1' : '';
	return $wpdb->get_results(
		'SELECT e.*, u.display_name, u.user_email, u.user_login FROM ' . gvwr_table( 'employees' ) . " e INNER JOIN {$wpdb->users} u ON u.ID=e.user_id $w ORDER BY u.display_name ASC"
	);
}

function gvwr_employee_name( $uid ) {
	$u = get_userdata( (int) $uid );
	return $u ? $u->display_name : '—';
}

/* ------------------------------------------------------------------ */
/* کوئری گزارش‌ها                                                      */
/* ------------------------------------------------------------------ */

function gvwr_where( $f ) {
	global $wpdb;
	$w = array( '1=1' );
	$a = array();
	if ( ! empty( $f['from'] ) ) {
		$w[] = 'i.work_date>=%s';
		$a[] = $f['from'];
	}
	if ( ! empty( $f['to'] ) ) {
		$w[] = 'i.work_date<=%s';
		$a[] = $f['to'];
	}
	foreach ( array( 'user_id', 'category_id', 'project_id' ) as $k ) {
		if ( ! empty( $f[ $k ] ) ) {
			$w[] = "i.$k=%d";
			$a[] = (int) $f[ $k ];
		}
	}
	if ( ! empty( $f['status'] ) ) {
		$st = array_values( array_intersect( (array) $f['status'], array( 'pending', 'approved', 'rejected' ) ) );
		if ( $st ) {
			$w[] = 'i.status IN (' . implode( ',', array_fill( 0, count( $st ), '%s' ) ) . ')';
			$a   = array_merge( $a, $st );
		}
	}
	if ( ! empty( $f['q'] ) ) {
		$like = '%' . $wpdb->esc_like( $f['q'] ) . '%';
		$w[]  = '(i.description LIKE %s OR i.url LIKE %s)';
		$a[]  = $like;
		$a[]  = $like;
	}
	return array( implode( ' AND ', $w ), $a );
}

function gvwr_prep( $sql, $args ) {
	global $wpdb;
	return $args ? $wpdb->prepare( $sql, $args ) : $sql; // phpcs:ignore
}

function gvwr_items( $f, $limit = 25, $offset = 0, $order = 'DESC' ) {
	global $wpdb;
	list( $w, $a ) = gvwr_where( $f );
	$order         = 'ASC' === $order ? 'ASC' : 'DESC';
	$sql           = 'SELECT i.*, c.name AS cat_name, c.color AS cat_color, c.icon AS cat_icon, c.grp AS cat_grp, u.display_name FROM ' . gvwr_table( 'items' ) . ' i
		LEFT JOIN ' . gvwr_table( 'categories' ) . " c ON c.id=i.category_id
		LEFT JOIN {$wpdb->users} u ON u.ID=i.user_id
		WHERE $w ORDER BY i.work_date $order, i.id $order LIMIT %d OFFSET %d";
	$a[]           = (int) $limit;
	$a[]           = (int) $offset;
	return $wpdb->get_results( $wpdb->prepare( $sql, $a ) ); // phpcs:ignore
}

function gvwr_items_count( $f ) {
	global $wpdb;
	list( $w, $a ) = gvwr_where( $f );
	return (int) $wpdb->get_var( gvwr_prep( 'SELECT COUNT(*) FROM ' . gvwr_table( 'items' ) . " i WHERE $w", $a ) );
}

function gvwr_item( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gvwr_table( 'items' ) . ' WHERE id=%d', $id ) );
}

/** آمار کلی. $with_users فقط برای مدیر (اطلاعات کارمند). */
function gvwr_stats( $f, $with_users = false ) {
	global $wpdb;
	list( $w, $a ) = gvwr_where( $f );
	$T             = gvwr_table( 'items' );

	$s = $wpdb->get_row(
		gvwr_prep( "SELECT COUNT(*) items, COALESCE(SUM(qty),0) qty, COUNT(DISTINCT work_date) days, COALESCE(SUM(duration_min),0) minutes,
			COALESCE(SUM(status='pending'),0) pending, COALESCE(SUM(status='approved'),0) approved, COALESCE(SUM(status='rejected'),0) rejected
			FROM $T i WHERE $w", $a ),
		ARRAY_A
	);
	$s = array_map( 'intval', (array) $s );

	$s['by_cat'] = array();
	foreach ( (array) $wpdb->get_results( gvwr_prep( "SELECT category_id, COUNT(*) items, SUM(qty) qty FROM $T i WHERE $w GROUP BY category_id ORDER BY qty DESC", $a ) ) as $r ) {
		$s['by_cat'][ (int) $r->category_id ] = array( 'items' => (int) $r->items, 'qty' => (int) $r->qty );
	}

	$s['by_day'] = array();
	foreach ( (array) $wpdb->get_results( gvwr_prep( "SELECT work_date d, COUNT(*) items, SUM(qty) qty FROM $T i WHERE $w GROUP BY work_date ORDER BY work_date ASC", $a ) ) as $r ) {
		$s['by_day'][ $r->d ] = array( 'items' => (int) $r->items, 'qty' => (int) $r->qty );
	}

	if ( $with_users ) {
		$s['by_user'] = array();
		foreach ( (array) $wpdb->get_results( gvwr_prep( "SELECT user_id, COUNT(*) items, SUM(qty) qty, COUNT(DISTINCT work_date) days, COALESCE(SUM(duration_min),0) minutes, COALESCE(SUM(status='approved'),0) approved, COALESCE(SUM(status='rejected'),0) rejected FROM $T i WHERE $w GROUP BY user_id ORDER BY qty DESC", $a ) ) as $r ) {
			$s['by_user'][ (int) $r->user_id ] = array_map( 'intval', (array) $r );
		}
		$s['by_user_cat'] = array();
		foreach ( (array) $wpdb->get_results( gvwr_prep( "SELECT user_id, category_id, SUM(qty) qty FROM $T i WHERE $w GROUP BY user_id, category_id", $a ) ) as $r ) {
			$s['by_user_cat'][ (int) $r->user_id ][ (int) $r->category_id ] = (int) $r->qty;
		}
	}
	return $s;
}

/* ------------------------------------------------------------------ */
/* ثبت / ویرایش / حذف گزارش                                            */
/* ------------------------------------------------------------------ */

/** اعتبارسنجی و پاکسازی یک ردیف گزارش. خروجی: آرایه یا WP_Error */
function gvwr_clean_item( $d, $uid ) {
	global $wpdb;
	$cat = isset( $d['cat'] ) ? (int) $d['cat'] : 0;
	if ( ! $cat || ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . gvwr_table( 'categories' ) . ' WHERE id=%d', $cat ) ) ) {
		return new WP_Error( 'cat', 'دسته‌بندی نامعتبر است.' );
	}
	$date = isset( $d['date'] ) ? $d['date'] : '';
	if ( ! gvwr_valid_date( $date ) ) {
		return new WP_Error( 'date', 'تاریخ نامعتبر است.' );
	}
	if ( $date > gvwr_today() ) {
		return new WP_Error( 'date', 'ثبت گزارش برای تاریخ آینده ممکن نیست.' );
	}
	$desc = trim( sanitize_textarea_field( isset( $d['desc'] ) ? $d['desc'] : '' ) );
	if ( '' === $desc ) {
		return new WP_Error( 'desc', 'توضیحات کار را بنویسید.' );
	}
	$time_ok = function ( $t ) {
		return is_string( $t ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t ) ? $t . ':00' : null;
	};
	$st  = $time_ok( isset( $d['start'] ) ? $d['start'] : '' );
	$en  = $time_ok( isset( $d['end'] ) ? $d['end'] : '' );
	$dur = null;
	if ( $st && $en && $en > $st ) {
		$dur = ( (int) substr( $en, 0, 2 ) * 60 + (int) substr( $en, 3, 2 ) ) - ( (int) substr( $st, 0, 2 ) * 60 + (int) substr( $st, 3, 2 ) );
	}
	$shots = array();
	foreach ( array_slice( array_filter( array_map( 'intval', (array) ( isset( $d['shots'] ) ? $d['shots'] : array() ) ) ), 0, 10 ) as $aid ) {
		$p = get_post( $aid );
		if ( $p && 'attachment' === $p->post_type && wp_attachment_is_image( $aid ) && ( (int) $p->post_author === (int) $uid || gvwr_can_manage() ) ) {
			$shots[] = $aid;
		}
	}
	return array(
		'category_id'  => $cat,
		'project_id'   => ! empty( $d['project'] ) ? (int) $d['project'] : gvwr_default_project_id(),
		'work_date'    => $date,
		'description'  => mb_substr( $desc, 0, 3000 ),
		'url'          => esc_url_raw( isset( $d['url'] ) ? $d['url'] : '' ),
		'qty'          => max( 1, min( 999, isset( $d['qty'] ) ? (int) $d['qty'] : 1 ) ),
		'start_time'   => $st,
		'end_time'     => $en,
		'duration_min' => $dur,
		'screenshots'  => implode( ',', $shots ),
	);
}

function gvwr_insert_items( $rows, $date, $project, $uid ) {
	global $wpdb;
	$batch = wp_generate_password( 12, false );
	$now   = current_time( 'mysql' );
	$ok    = 0;
	foreach ( $rows as $r ) {
		$r['date']    = $date;
		$r['project'] = $project;
		$c            = gvwr_clean_item( $r, $uid );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$c['user_id']    = (int) $uid;
		$c['status']     = gvwr_opt( 'auto_approve' ) ? 'approved' : 'pending';
		$c['batch']      = $batch;
		$c['created_at'] = $now;
		$c['updated_at'] = $now;
		$wpdb->insert( gvwr_table( 'items' ), $c );
		$ok++;
	}
	if ( $ok ) {
		gvwr_notify_managers( $uid, $ok );
	}
	return $ok;
}

function gvwr_update_item( $id, $d, $actor ) {
	global $wpdb;
	$it = gvwr_item( $id );
	if ( ! $it ) {
		return new WP_Error( 'nf', 'گزارش پیدا نشد.' );
	}
	$mgr = gvwr_can_manage();
	if ( ! $mgr ) {
		if ( (int) $it->user_id !== (int) $actor ) {
			return new WP_Error( 'perm', 'دسترسی ندارید.' );
		}
		if ( 'approved' === $it->status && ! gvwr_opt( 'allow_edit_approved' ) ) {
			return new WP_Error( 'locked', 'گزارش تأییدشده قابل ویرایش نیست.' );
		}
	}
	$c = gvwr_clean_item( $d, $it->user_id );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$c['updated_at'] = current_time( 'mysql' );
	if ( ! $mgr ) { // ویرایش کارمند، دوباره به صف تأیید می‌رود
		$c['status']       = gvwr_opt( 'auto_approve' ) ? 'approved' : 'pending';
		$c['manager_note'] = '';
	}
	$wpdb->update( gvwr_table( 'items' ), $c, array( 'id' => (int) $id ) );
	return true;
}

function gvwr_delete_item( $id, $actor ) {
	global $wpdb;
	$it = gvwr_item( $id );
	if ( ! $it ) {
		return new WP_Error( 'nf', 'گزارش پیدا نشد.' );
	}
	if ( ! gvwr_can_manage() ) {
		if ( (int) $it->user_id !== (int) $actor ) {
			return new WP_Error( 'perm', 'دسترسی ندارید.' );
		}
		if ( 'approved' === $it->status && ! gvwr_opt( 'allow_edit_approved' ) ) {
			return new WP_Error( 'locked', 'گزارش تأییدشده قابل حذف نیست.' );
		}
	}
	$wpdb->delete( gvwr_table( 'items' ), array( 'id' => (int) $id ) );
	return true;
}

function gvwr_set_status( $ids, $status, $note = '' ) {
	global $wpdb;
	$ids = array_filter( array_map( 'intval', (array) $ids ) );
	if ( ! $ids || ! in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
		return 0;
	}
	$in = implode( ',', $ids );
	return (int) $wpdb->query(
		$wpdb->prepare( 'UPDATE ' . gvwr_table( 'items' ) . " SET status=%s, manager_note=%s, updated_at=%s WHERE id IN ($in)", $status, sanitize_textarea_field( $note ), current_time( 'mysql' ) ) // phpcs:ignore
	);
}

function gvwr_pending_count() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gvwr_table( 'items' ) . " WHERE status='pending'" );
}

function gvwr_rejected_count( $uid ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . gvwr_table( 'items' ) . " WHERE status='rejected' AND user_id=%d", $uid ) );
}

function gvwr_notify_managers( $uid, $count ) {
	if ( ! gvwr_opt( 'email_notify' ) ) {
		return;
	}
	$to = array();
	foreach ( get_users( array( 'role' => 'gvwr_manager', 'fields' => array( 'user_email' ) ) ) as $m ) {
		$to[] = $m->user_email;
	}
	foreach ( explode( ',', (string) gvwr_opt( 'notify_emails' ) ) as $e ) {
		if ( is_email( trim( $e ) ) ) {
			$to[] = trim( $e );
		}
	}
	if ( ! $to ) {
		$to[] = get_option( 'admin_email' );
	}
	$name = gvwr_employee_name( $uid );
	wp_mail(
		array_unique( $to ),
		'گزارش کار جدید از ' . $name . ' (' . gvwr_fa( $count ) . ' مورد)',
		"{$name} تعداد " . gvwr_fa( $count ) . " گزارش جدید ثبت کرد و منتظر تأیید است.\n\n" . admin_url( 'admin.php?page=gv-wr-reports&gvwr_status=pending' )
	);
}

/* ------------------------------------------------------------------ */
/* کلمات کلیدی و رتبه                                                  */
/* ------------------------------------------------------------------ */

function gvwr_keywords( $project_id = 0, $active_only = true, $q = '' ) {
	global $wpdb;
	$w = array( '1=1' );
	$a = array();
	if ( $project_id ) {
		$w[] = 'project_id=%d';
		$a[] = (int) $project_id;
	}
	if ( $active_only ) {
		$w[] = 'active=1';
	}
	if ( '' !== $q ) {
		$w[] = 'keyword LIKE %s';
		$a[] = '%' . $wpdb->esc_like( $q ) . '%';
	}
	return $wpdb->get_results( gvwr_prep( 'SELECT * FROM ' . gvwr_table( 'keywords' ) . ' WHERE ' . implode( ' AND ', $w ) . ' ORDER BY id ASC', $a ) );
}

function gvwr_kw_add( $keyword, $url = '', $project_id = 0 ) {
	global $wpdb;
	$keyword = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $keyword ) ) );
	if ( '' === $keyword ) {
		return 0;
	}
	$project_id = $project_id ? (int) $project_id : gvwr_default_project_id();
	$T          = gvwr_table( 'keywords' );
	$ex         = $wpdb->get_row( $wpdb->prepare( "SELECT id, active FROM $T WHERE project_id=%d AND keyword=%s", $project_id, mb_substr( $keyword, 0, 180 ) ) );
	if ( $ex ) {
		if ( ! $ex->active ) {
			$wpdb->update( $T, array( 'active' => 1 ), array( 'id' => $ex->id ) );
		}
		return 0; // تکراری
	}
	$wpdb->insert( $T, array( 'project_id' => $project_id, 'keyword' => mb_substr( $keyword, 0, 180 ), 'target_url' => esc_url_raw( $url ), 'active' => 1, 'created_at' => current_time( 'mysql' ) ) );
	return (int) $wpdb->insert_id;
}

/**
 * آخرین $limit رتبه‌ی هر کلمه تا تاریخ $upto. خروجی: [kw_id => [ [date,rank] جدیدترین اول ]]
 */
function gvwr_rank_series( $ids, $upto = '', $limit = 6 ) {
	global $wpdb;
	$ids = array_filter( array_map( 'intval', (array) $ids ) );
	if ( ! $ids ) {
		return array();
	}
	$upto = gvwr_valid_date( $upto ) ? $upto : gvwr_today();
	$rows = $wpdb->get_results(
		$wpdb->prepare( 'SELECT keyword_id, rank_date, rank_pos FROM ' . gvwr_table( 'ranks' ) . ' WHERE keyword_id IN (' . implode( ',', $ids ) . ') AND rank_date<=%s AND rank_date>=%s ORDER BY keyword_id ASC, rank_date DESC', $upto, gvwr_add_days( $upto, -800 ) ) // phpcs:ignore
	);
	$out = array();
	foreach ( $rows as $r ) {
		$k = (int) $r->keyword_id;
		if ( ! isset( $out[ $k ] ) ) {
			$out[ $k ] = array();
		}
		if ( count( $out[ $k ] ) < $limit ) {
			$out[ $k ][] = array( $r->rank_date, (int) $r->rank_pos );
		}
	}
	return $out;
}

/** رشد/افت بر اساس دو رتبه‌ی آخر. رتبه‌ی کمتر = بهتر. */
function gvwr_growth( $series ) {
	$n = is_array( $series ) ? count( $series ) : 0;
	if ( ! $n ) {
		return array( 'last' => null, 'prev' => null, 'delta' => 0, 'dir' => 'none' );
	}
	$last = $series[0][1];
	if ( 1 === $n ) {
		return array( 'last' => $last, 'prev' => null, 'delta' => 0, 'dir' => 'new' );
	}
	$prev  = $series[1][1];
	$delta = $prev - $last; // مثبت = بهبود
	return array( 'last' => $last, 'prev' => $prev, 'delta' => $delta, 'dir' => $delta > 0 ? 'up' : ( $delta < 0 ? 'down' : 'same' ) );
}

function gvwr_save_ranks( $map, $date, $uid ) {
	global $wpdb;
	if ( ! gvwr_valid_date( $date ) || $date > gvwr_today() ) {
		return new WP_Error( 'date', 'تاریخ رتبه نامعتبر است.' );
	}
	$T  = gvwr_table( 'ranks' );
	$ok = 0;
	foreach ( (array) $map as $kid => $pos ) {
		$kid = (int) $kid;
		$pos = (int) preg_replace( '/\D/', '', strtr( (string) $pos, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) ) );
		if ( $kid < 1 || $pos < 1 || $pos > 9999 ) {
			continue;
		}
		$wpdb->query(
			$wpdb->prepare( "INSERT INTO $T (keyword_id,user_id,rank_pos,rank_date,created_at) VALUES (%d,%d,%d,%s,%s) ON DUPLICATE KEY UPDATE rank_pos=VALUES(rank_pos), user_id=VALUES(user_id)", $kid, $uid, $pos, $date, current_time( 'mysql' ) )
		);
		$ok++;
	}
	return $ok;
}

/** نمودار کوچک خطی رتبه (SVG)؛ بالاتر = رتبه‌ی بهتر */
function gvwr_sparkline( $series, $w = 84, $h = 26 ) {
	$pts = array_reverse( wp_list_pluck( (array) $series, 1 ) ); // قدیمی → جدید
	if ( count( $pts ) < 2 ) {
		return '';
	}
	$min = min( $pts );
	$max = max( $pts );
	$rng = max( 1, $max - $min );
	$n   = count( $pts );
	$d   = array();
	foreach ( $pts as $i => $p ) {
		$x   = 3 + ( $w - 6 ) * $i / ( $n - 1 );
		$y   = 3 + ( $h - 6 ) * ( ( $p - $min ) / $rng );
		$d[] = round( $x, 1 ) . ',' . round( $y, 1 );
	}
	$col = end( $pts ) < $pts[0] ? '#059669' : ( end( $pts ) > $pts[0] ? '#dc2626' : '#64748b' );
	$lx  = explode( ',', end( $d ) );
	return '<svg class="gvwr-spark" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true"><polyline fill="none" stroke="' . $col . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' . implode( ' ', $d ) . '"/><circle cx="' . $lx[0] . '" cy="' . $lx[1] . '" r="2.6" fill="' . $col . '"/></svg>';
}
