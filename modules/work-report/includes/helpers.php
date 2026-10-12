<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* تنظیمات                                                             */
/* ------------------------------------------------------------------ */

function gvwr_defaults() {
	return array(
		'calendar'               => 'jalali', // jalali | gregorian
		'require_approval'       => 1,        // کارفرما فقط گزارش‌های تأییدشده را ببیند
		'auto_approve'           => 0,        // تأیید خودکار گزارش‌ها
		'allow_edit_approved'    => 0,        // ویرایش گزارش تأییدشده توسط کارمند
		'email_notify'           => 1,        // ایمیل به مدیر هنگام ثبت گزارش جدید
		'notify_emails'          => '',       // ایمیل‌های اضافه (با کاما)
		'employees_add_keywords' => 0,        // کارمند بتواند کلمه‌ی کلیدی اضافه کند
		'login_redirect'         => 1,        // بعد از ورود، مستقیم به صفحه‌ی گزارش
		'client_page_id'         => 0,
		'client_title'           => 'گزارش عملکرد سایت',
		'client_show_shots'      => 1,        // نمایش اسکرین‌شات‌ها به کارفرما
		'client_show_keywords'   => 1,        // نمایش رتبه‌ی کلمات به کارفرما
		'company_name'           => '',       // نام شرکت شما در سربرگ گزارش کارفرما
	);
}

function gvwr_opt( $key = null ) {
	$o = wp_parse_args( get_option( 'gvwr_settings', array() ), gvwr_defaults() );
	return null === $key ? $o : ( isset( $o[ $key ] ) ? $o[ $key ] : null );
}

function gvwr_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'gvwr_' . $name;
}

function gvwr_today() {
	return current_time( 'Y-m-d' );
}

function gvwr_is_jalali() {
	return 'jalali' === gvwr_opt( 'calendar' );
}

/* ------------------------------------------------------------------ */
/* دسترسی‌ها                                                           */
/* ------------------------------------------------------------------ */

function gvwr_can_manage() {
	return current_user_can( 'gvwr_manage' ) || current_user_can( 'manage_options' );
}
function gvwr_can_submit() {
	return current_user_can( 'gvwr_submit' ) || current_user_can( 'manage_options' );
}
function gvwr_can_client() {
	return current_user_can( 'gvwr_view_client' ) || current_user_can( 'manage_options' );
}

/* ------------------------------------------------------------------ */
/* تاریخ شمسی                                                          */
/* ------------------------------------------------------------------ */

function gvwr_g2j( $gy, $gm, $gd ) {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
	$days %= 12053;
	$jy   += 4 * (int) ( $days / 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy  += (int) ( ( $days - 1 ) / 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + (int) ( $days / 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + (int) ( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

function gvwr_j2g( $jy, $jm, $jd ) {
	$jy  += 1595;
	$days = -355668 + ( 365 * $jy ) + ( (int) ( $jy / 33 ) * 8 ) + (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
	$gy   = 400 * (int) ( $days / 146097 );
	$days %= 146097;
	if ( $days > 36524 ) {
		$gy  += 100 * (int) ( --$days / 36524 );
		$days %= 36524;
		if ( $days >= 365 ) {
			$days++;
		}
	}
	$gy  += 4 * (int) ( $days / 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$gy  += (int) ( ( $days - 1 ) / 365 );
		$days = ( $days - 1 ) % 365;
	}
	$gd    = $days + 1;
	$leap  = ( ( $gy % 4 == 0 && $gy % 100 != 0 ) || $gy % 400 == 0 ) ? 29 : 28;
	$sal_a = array( 0, 31, $leap, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	for ( $gm = 0; $gm < 13 && $gd > $sal_a[ $gm ]; $gm++ ) {
		$gd -= $sal_a[ $gm ];
	}
	return array( $gy, $gm, $gd );
}

function gvwr_jmonth( $m ) {
	$n = array( '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	return isset( $n[ $m ] ) ? $n[ $m ] : '';
}

function gvwr_fa( $s ) {
	return strtr( (string) $s, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}

function gvwr_n( $n, $dec = 0 ) {
	return gvwr_fa( number_format( (float) $n, $dec, '.', ',' ) );
}

/** رشته‌ی تاریخ میلادی Y-m-d را به قالب نمایشی (شمسی یا میلادی) تبدیل می‌کند. */
function gvwr_date( $ymd, $mode = 'full' ) {
	if ( ! $ymd || '0000-00-00' === $ymd ) {
		return '';
	}
	$p = array_map( 'intval', explode( '-', substr( $ymd, 0, 10 ) ) );
	if ( count( $p ) < 3 ) {
		return '';
	}
	if ( gvwr_is_jalali() ) {
		list( $jy, $jm, $jd ) = gvwr_g2j( $p[0], $p[1], $p[2] );
		if ( 'long' === $mode ) {
			return gvwr_fa( $jd . ' ' . gvwr_jmonth( $jm ) . ' ' . $jy );
		}
		if ( 'short' === $mode ) {
			return gvwr_fa( sprintf( '%02d/%02d', $jm, $jd ) );
		}
		return gvwr_fa( sprintf( '%04d/%02d/%02d', $jy, $jm, $jd ) );
	}
	$ts = gmmktime( 12, 0, 0, $p[1], $p[2], $p[0] );
	if ( 'long' === $mode ) {
		return gmdate( 'j M Y', $ts );
	}
	if ( 'short' === $mode ) {
		return gmdate( 'm/d', $ts );
	}
	return gmdate( 'Y/m/d', $ts );
}

function gvwr_time( $t ) {
	return $t ? gvwr_fa( substr( $t, 0, 5 ) ) : '';
}

function gvwr_minutes( $m ) {
	$m = (int) $m;
	if ( $m <= 0 ) {
		return '—';
	}
	return gvwr_fa( floor( $m / 60 ) . ':' . sprintf( '%02d', $m % 60 ) );
}

function gvwr_add_days( $ymd, $days ) {
	$d = new DateTime( $ymd . ' 12:00:00', new DateTimeZone( 'UTC' ) );
	$d->modify( ( $days >= 0 ? '+' : '' ) . (int) $days . ' day' );
	return $d->format( 'Y-m-d' );
}

function gvwr_valid_date( $s ) {
	return is_string( $s ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) && checkdate( (int) substr( $s, 5, 2 ), (int) substr( $s, 8, 2 ), (int) substr( $s, 0, 4 ) );
}

/**
 * بازه‌ی زمانی بر اساس نوع (روز/هفته/ماه/سال/همه) و یک تاریخ مرجع.
 * هفته از شنبه شروع می‌شود. ماه و سال در حالت شمسی، شمسی حساب می‌شوند.
 */
function gvwr_range( $period, $anchor = '' ) {
	$today = gvwr_today();
	if ( ! gvwr_valid_date( $anchor ) ) {
		$anchor = $today;
	}
	$jal = gvwr_is_jalali();
	$p   = array_map( 'intval', explode( '-', $anchor ) );

	switch ( $period ) {
		case 'all':
			return array( 'period' => 'all', 'anchor' => $anchor, 'from' => '2000-01-01', 'to' => $today, 'label' => 'همه‌ی زمان‌ها', 'prev' => '', 'next' => '' );

		case 'day':
			$from = $to = $anchor;
			$label = gvwr_date( $anchor, 'long' );
			break;

		case 'week':
			$w     = (int) gmdate( 'w', gmmktime( 12, 0, 0, $p[1], $p[2], $p[0] ) ); // 0=یکشنبه
			$off   = ( $w + 1 ) % 7;                                              // شنبه = 0
			$from  = gvwr_add_days( $anchor, -$off );
			$to    = gvwr_add_days( $from, 6 );
			$label = gvwr_date( $from, 'long' ) . ' تا ' . gvwr_date( $to, 'long' );
			break;

		case 'year':
			if ( $jal ) {
				list( $jy ) = gvwr_g2j( $p[0], $p[1], $p[2] );
				$a    = gvwr_j2g( $jy, 1, 1 );
				$b    = gvwr_j2g( $jy + 1, 1, 1 );
				$from = sprintf( '%04d-%02d-%02d', $a[0], $a[1], $a[2] );
				$to   = gvwr_add_days( sprintf( '%04d-%02d-%02d', $b[0], $b[1], $b[2] ), -1 );
				$label = 'سال ' . gvwr_fa( $jy );
			} else {
				$from  = $p[0] . '-01-01';
				$to    = $p[0] . '-12-31';
				$label = (string) $p[0];
			}
			break;

		case 'month':
		default:
			$period = 'month';
			if ( $jal ) {
				list( $jy, $jm ) = gvwr_g2j( $p[0], $p[1], $p[2] );
				$a    = gvwr_j2g( $jy, $jm, 1 );
				$ny   = $jm == 12 ? $jy + 1 : $jy;
				$nm   = $jm == 12 ? 1 : $jm + 1;
				$b    = gvwr_j2g( $ny, $nm, 1 );
				$from = sprintf( '%04d-%02d-%02d', $a[0], $a[1], $a[2] );
				$to   = gvwr_add_days( sprintf( '%04d-%02d-%02d', $b[0], $b[1], $b[2] ), -1 );
				$label = gvwr_jmonth( $jm ) . ' ' . gvwr_fa( $jy );
			} else {
				$from  = sprintf( '%04d-%02d-01', $p[0], $p[1] );
				$to    = gmdate( 'Y-m-t', gmmktime( 12, 0, 0, $p[1], 1, $p[0] ) );
				$label = gmdate( 'F Y', gmmktime( 12, 0, 0, $p[1], 1, $p[0] ) );
			}
	}

	$next = gvwr_add_days( $to, 1 );
	return array(
		'period' => $period,
		'anchor' => $anchor,
		'from'   => $from,
		'to'     => $to,
		'label'  => $label,
		'prev'   => gvwr_add_days( $from, -1 ),
		'next'   => $next > $today ? '' : $next,
	);
}

function gvwr_period_name( $p ) {
	$n = array( 'day' => 'روزانه', 'week' => 'هفتگی', 'month' => 'ماهانه', 'year' => 'سالانه', 'all' => 'همه' );
	return isset( $n[ $p ] ) ? $n[ $p ] : $n['month'];
}

/** رنگ‌های پیش‌فرض نمودار */
function gvwr_palette( $i = null ) {
	$p = array( '#0f766e', '#2563eb', '#d97706', '#7c3aed', '#db2777', '#059669', '#dc2626', '#0891b2', '#65a30d', '#9333ea', '#ea580c', '#475569' );
	return null === $i ? $p : $p[ $i % count( $p ) ];
}

function gvwr_site_name() {
	return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}
