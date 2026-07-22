<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — گزارش عملکرد سئوی مشتری
 *  ------------------------------------------------------------
 *  - کارمند از پیشخوان، گزارش دوره‌ای سئوی هر مشتری را وارد می‌کند:
 *      کلمات کلیدی و تغییر رتبه، فعالیت‌ها و محتوای تولید/بروزرسانی‌شده،
 *      رشد صفحات، ترافیک ارگانیک، ساعت کار صرف‌شده و برنامه گام بعدی
 *  - هر بخش از گزارش قابل نمایش/مخفی‌سازی جداگانه برای مشتری است
 *  - مشتری از طریق شورت‌کد [gv_seo_reports] وارد پنل اختصاصی خودش
 *    می‌شود و گزارش‌های منتشرشده را با فیلتر، دسته‌بندی، جدول قابل
 *    مرتب‌سازی و نمودار مشاهده می‌کند
 *  - تمام تاریخ‌ها (ورودی کارمند و نمایش مشتری) شمسی است
 *  داده‌ها در ۴ جدول اختصاصی دیتابیس ذخیره می‌شوند.
 * ==========================================================
 */

define( 'GV_SR_NONCE',      'gv_sr_nonce_action' );
define( 'GV_SR_DB_VERSION', '1.3' );
define( 'GV_SR_PAGE_SLUG',  'gv-seo-reports' );
define( 'GV_SR_EMP_COOKIE', 'gv_sr_employee_id' );
define( 'GV_SR_TEAM_COOKIE','gv_sr_team_token' );
define( 'GV_SR_TEAM_NONCE', 'gv_sr_team_nonce_action' );

/* ==========================================================================
   ۰) ساخت جداول دیتابیس
   ========================================================================== */
add_action( 'plugins_loaded', 'gv_sr_maybe_install_db' );
function gv_sr_maybe_install_db() {
	if ( get_option( 'gv_sr_db_version' ) === GV_SR_DB_VERSION ) { return; }

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$t_reports  = $wpdb->prefix . 'gv_sr_reports';
	$t_keywords = $wpdb->prefix . 'gv_sr_keywords';
	$t_tasks    = $wpdb->prefix . 'gv_sr_tasks';
	$t_growth   = $wpdb->prefix . 'gv_sr_growth';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$t_reports} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		client_name VARCHAR(191) NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		title VARCHAR(255) NOT NULL,
		period_start DATE NOT NULL,
		period_end DATE NOT NULL,
		summary LONGTEXT NULL,
		next_steps LONGTEXT NULL,
		hours_spent DECIMAL(6,2) NOT NULL DEFAULT 0,
		traffic_before BIGINT NOT NULL DEFAULT 0,
		traffic_after BIGINT NOT NULL DEFAULT 0,
		overall_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
		status VARCHAR(20) NOT NULL DEFAULT 'draft',
		visibility LONGTEXT NULL,
		author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY status (status),
		KEY period_end (period_end)
	) {$charset_collate};" );

	dbDelta( "CREATE TABLE {$t_keywords} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		report_id BIGINT UNSIGNED NOT NULL,
		keyword VARCHAR(191) NOT NULL,
		search_engine VARCHAR(50) NOT NULL DEFAULT 'گوگل',
		page_url VARCHAR(500) NULL,
		prev_rank SMALLINT NOT NULL DEFAULT 0,
		curr_rank SMALLINT NOT NULL DEFAULT 0,
		note VARCHAR(500) NULL,
		sort_order INT NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY report_id (report_id),
		KEY keyword (keyword)
	) {$charset_collate};" );

	dbDelta( "CREATE TABLE {$t_tasks} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		report_id BIGINT UNSIGNED NOT NULL,
		task_type VARCHAR(30) NOT NULL DEFAULT 'other',
		title VARCHAR(255) NOT NULL,
		url VARCHAR(500) NULL,
		target_keyword VARCHAR(191) NULL,
		work_date DATE NOT NULL,
		hours DECIMAL(5,2) NOT NULL DEFAULT 0,
		note VARCHAR(500) NULL,
		PRIMARY KEY  (id),
		KEY report_id (report_id),
		KEY task_type (task_type),
		KEY work_date (work_date)
	) {$charset_collate};" );

	dbDelta( "CREATE TABLE {$t_growth} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		report_id BIGINT UNSIGNED NOT NULL,
		page_title VARCHAR(255) NULL,
		page_url VARCHAR(500) NULL,
		metric_label VARCHAR(120) NOT NULL DEFAULT 'رشد',
		before_value VARCHAR(50) NULL,
		after_value VARCHAR(50) NULL,
		note VARCHAR(500) NULL,
		PRIMARY KEY  (id),
		KEY report_id (report_id)
	) {$charset_collate};" );

	/* ---- کارمندان تیم سئو ---- */
	$t_employees = $wpdb->prefix . 'gv_sr_employees';
	dbDelta( "CREATE TABLE {$t_employees} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(191) NOT NULL,
		hourly_rate BIGINT UNSIGNED NOT NULL DEFAULT 0,
		global_code VARCHAR(40) NULL,
		active TINYINT UNSIGNED NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY global_code (global_code)
	) {$charset_collate};" );

	/* ---- کارکرد روزانه (تایم‌شیت) کارمندان ---- */
	$t_timelogs = $wpdb->prefix . 'gv_sr_timelogs';
	dbDelta( "CREATE TABLE {$t_timelogs} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		employee_id BIGINT UNSIGNED NOT NULL,
		work_date DATE NOT NULL,
		entry_mode VARCHAR(10) NOT NULL DEFAULT 'manual',
		start_time VARCHAR(5) NULL,
		end_time VARCHAR(5) NULL,
		hours DECIMAL(5,2) NOT NULL DEFAULT 0,
		client_name VARCHAR(191) NULL,
		note VARCHAR(500) NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY employee_id (employee_id),
		KEY work_date (work_date)
	) {$charset_collate};" );

	/* ---- رمز عبور پیش‌فرض بخش مدیریت تیم (اگر قبلاً تنظیم نشده) ---- */
	if ( false === get_option( 'gv_sr_manager_pass_hash', false ) ) {
		update_option( 'gv_sr_manager_pass_hash', password_hash( 'GrootSEO@1404', PASSWORD_DEFAULT ) );
	}

	/* ---- تایمر زنده‌ی کارکرد (استارت/استاپ) ---- */
	$t_timers = $wpdb->prefix . 'gv_sr_active_timers';
	dbDelta( "CREATE TABLE {$t_timers} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		employee_id BIGINT UNSIGNED NOT NULL,
		started_at DATETIME NOT NULL,
		client_name VARCHAR(191) NULL,
		note VARCHAR(500) NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY employee_id (employee_id)
	) {$charset_collate};" );

	update_option( 'gv_sr_db_version', GV_SR_DB_VERSION );
}

/* ==========================================================================
   ۱) تنظیمات ثابت افزونه (نوع فعالیت‌ها / کلیدهای نمایش به مشتری)
   ========================================================================== */
function gv_sr_task_types() {
	return array(
		'content_new'    => array( 'label' => 'تولید محتوای جدید',         'icon' => '📝', 'color' => '#059669' ),
		'content_update' => array( 'label' => 'بروزرسانی محتوای قبلی',      'icon' => '🔄', 'color' => '#2563eb' ),
		'technical'      => array( 'label' => 'اصلاح فنی سئو',              'icon' => '🛠️', 'color' => '#7c3aed' ),
		'backlink'       => array( 'label' => 'لینک‌سازی',                  'icon' => '🔗', 'color' => '#db2777' ),
		'onpage'         => array( 'label' => 'بهینه‌سازی داخل صفحه',       'icon' => '🎯', 'color' => '#ea580c' ),
		'other'          => array( 'label' => 'سایر فعالیت‌ها',             'icon' => '✅', 'color' => '#64748b' ),
	);
}
function gv_sr_content_task_types() {
	return array( 'content_new', 'content_update' );
}
function gv_sr_task_type_label( $type ) {
	$types = gv_sr_task_types();
	return isset( $types[ $type ] ) ? $types[ $type ]['label'] : $type;
}

function gv_sr_visibility_keys() {
	return array(
		'show_summary'    => 'خلاصه گزارش کارمند',
		'show_keywords'   => 'جدول کلمات کلیدی و تغییر رتبه',
		'show_tasks'      => 'ریز فعالیت‌ها و محتوای تولیدشده',
		'show_growth'     => 'رشد صفحات',
		'show_traffic'    => 'ترافیک ارگانیک',
		'show_hours'      => 'ساعت کار ثبت‌شده',
		'show_charts'     => 'نمودارها',
		'show_next_steps' => 'برنامه گام بعدی',
	);
}
function gv_sr_default_visibility() {
	$out = array();
	foreach ( array_keys( gv_sr_visibility_keys() ) as $k ) { $out[ $k ] = 1; }
	return $out;
}
function gv_sr_get_visibility( $report ) {
	$defaults = gv_sr_default_visibility();
	$saved    = json_decode( (string) $report->visibility, true );
	if ( ! is_array( $saved ) ) { $saved = array(); }
	return wp_parse_args( $saved, $defaults );
}

/* ==========================================================================
   ۲) توابع تاریخ شمسی (کاملاً مستقل از سایر ماژول‌ها)
   ========================================================================== */

/** میلادی → شمسی (چرخه ۳۳ ساله؛ دقیق برای بازه سال‌های فعلی) */
function gv_sr_g2j( $g_y, $g_m, $g_d ) {
	$g_days_in_month = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	$j_days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );

	$gy = $g_y - 1600;
	$gm = $g_m - 1;
	$gd = $g_d - 1;

	$g_day_no = 365 * $gy + intdiv( $gy + 3, 4 ) - intdiv( $gy + 99, 100 ) + intdiv( $gy + 399, 400 );
	for ( $i = 0; $i < $gm; ++$i ) { $g_day_no += $g_days_in_month[ $i ]; }
	if ( $gm > 1 && ( ( $g_y % 4 === 0 && $g_y % 100 !== 0 ) || ( $g_y % 400 === 0 ) ) ) { $g_day_no++; }
	$g_day_no += $gd;

	$j_day_no = $g_day_no - 79;
	$j_np     = intdiv( $j_day_no, 12053 );
	$j_day_no = $j_day_no % 12053;

	$jy = 979 + 33 * $j_np + 4 * intdiv( $j_day_no, 1461 );
	$j_day_no %= 1461;

	if ( $j_day_no >= 366 ) {
		$jy += intdiv( $j_day_no - 1, 365 );
		$j_day_no = ( $j_day_no - 1 ) % 365;
	}

	$jm = 0;
	for ( $i = 0; $i < 11 && $j_day_no >= $j_days_in_month[ $i ]; ++$i ) {
		$j_day_no -= $j_days_in_month[ $i ];
		$jm++;
	}
	$jd = $j_day_no + 1;

	return array( $jy, $jm + 1, $jd );
}

/**
 * شمسی → میلادی. به‌جای معکوس‌سازی فرمول ریاضی (که خطاپذیر است)،
 * از جست‌وجوی محدود اطراف یک تخمین اولیه استفاده می‌شود؛ به این ترتیب
 * همیشه با gv_sr_g2j() دقیقاً سازگار (رفت‌وبرگشت‌پذیر) می‌ماند.
 */
function gv_sr_j2g( $j_y, $j_m, $j_d ) {
	$j_m = max( 1, min( 12, (int) $j_m ) );
	$j_d = max( 1, min( 31, (int) $j_d ) );

	$j_days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );
	$day_offset      = 0;
	for ( $i = 0; $i < $j_m - 1; $i++ ) { $day_offset += $j_days_in_month[ $i ]; }
	$day_offset += ( $j_d - 1 );

	// تخمین اولیه: هر سال شمسی تقریباً ۲۱ مارس همان سال + ۶۲۱ آغاز می‌شود
	$approx_gy = (int) $j_y + 621;
	$base_ts   = mktime( 12, 0, 0, 3, 21, $approx_gy );
	if ( false === $base_ts ) { $base_ts = time(); }
	$ts_estimate = $base_ts + ( $day_offset * DAY_IN_SECONDS );

	for ( $delta = -6; $delta <= 6; $delta++ ) {
		$ts = $ts_estimate + ( $delta * DAY_IN_SECONDS );
		$gy = (int) gmdate( 'Y', $ts );
		$gm = (int) gmdate( 'n', $ts );
		$gd = (int) gmdate( 'j', $ts );
		list( $chk_jy, $chk_jm, $chk_jd ) = gv_sr_g2j( $gy, $gm, $gd );
		if ( $chk_jy === (int) $j_y && $chk_jm === (int) $j_m && $chk_jd === (int) $j_d ) {
			return array( $gy, $gm, $gd );
		}
	}

	// بازگشت احتیاطی (عملاً هرگز نباید به این‌جا برسد)
	return array( (int) gmdate( 'Y', $ts_estimate ), (int) gmdate( 'n', $ts_estimate ), (int) gmdate( 'j', $ts_estimate ) );
}

function gv_sr_fa_digits( $str ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	return preg_replace_callback( '/[0-9]/', function ( $m ) use ( $fa ) { return $fa[ $m[0] ]; }, (string) $str );
}
function gv_sr_month_name( $m ) {
	$names = array(
		1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
		5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
		9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
	);
	return $names[ $m ] ?? '';
}

/** رشته Y-m-d میلادی → «۲ مرداد ۱۴۰۳» */
function gv_sr_jalali_str( $mysql_date, $with_year = true ) {
	if ( empty( $mysql_date ) || '0000-00-00' === $mysql_date ) { return '—'; }
	$parts = explode( '-', $mysql_date );
	if ( count( $parts ) < 3 ) { return '—'; }
	list( $jy, $jm, $jd ) = gv_sr_g2j( (int) $parts[0], (int) $parts[1], (int) $parts[2] );
	$str = gv_sr_fa_digits( $jd ) . ' ' . gv_sr_month_name( $jm );
	if ( $with_year ) { $str .= ' ' . gv_sr_fa_digits( $jy ); }
	return $str;
}

/** رشته Y-m-d میلادی → «۱۴۰۳/۰۵/۰۲» (برای نمایش فشرده/جدول) */
function gv_sr_jalali_numeric( $mysql_date ) {
	if ( empty( $mysql_date ) || '0000-00-00' === $mysql_date ) { return '—'; }
	$parts = explode( '-', $mysql_date );
	if ( count( $parts ) < 3 ) { return '—'; }
	list( $jy, $jm, $jd ) = gv_sr_g2j( (int) $parts[0], (int) $parts[1], (int) $parts[2] );
	return gv_sr_fa_digits( sprintf( '%04d/%02d/%02d', $jy, $jm, $jd ) );
}

function gv_sr_today_jalali_year() {
	$now = current_time( 'timestamp' );
	list( $jy ) = gv_sr_g2j( (int) date( 'Y', $now ), (int) date( 'n', $now ), (int) date( 'j', $now ) );
	return (int) $jy;
}

/** رندر ۳ باکس انتخاب سال/ماه/روز شمسی برای یک فیلد */
function gv_sr_jalali_select_fields( $name, $mysql_date = '' ) {
	$now      = current_time( 'timestamp' );
	$cur_jy   = gv_sr_today_jalali_year();
	$sel_jy   = $cur_jy;
	$sel_jm   = 1;
	$sel_jd   = 1;

	if ( ! empty( $mysql_date ) && '0000-00-00' !== $mysql_date ) {
		$parts = explode( '-', $mysql_date );
		if ( count( $parts ) === 3 ) {
			list( $sel_jy, $sel_jm, $sel_jd ) = gv_sr_g2j( (int) $parts[0], (int) $parts[1], (int) $parts[2] );
		}
	} else {
		list( $sel_jy, $sel_jm, $sel_jd ) = gv_sr_g2j( (int) date( 'Y', $now ), (int) date( 'n', $now ), (int) date( 'j', $now ) );
	}

	ob_start();
	?>
	<span class="gvsr-jdate-group">
		<select name="<?php echo esc_attr( $name ); ?>_jd" class="gvsr-jdate-d">
			<?php for ( $d = 1; $d <= 31; $d++ ) : ?>
				<option value="<?php echo esc_attr( $d ); ?>" <?php selected( (int) $sel_jd, $d ); ?>><?php echo esc_html( gv_sr_fa_digits( $d ) ); ?></option>
			<?php endfor; ?>
		</select>
		<select name="<?php echo esc_attr( $name ); ?>_jm" class="gvsr-jdate-m">
			<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
				<option value="<?php echo esc_attr( $m ); ?>" <?php selected( (int) $sel_jm, $m ); ?>><?php echo esc_html( gv_sr_month_name( $m ) ); ?></option>
			<?php endfor; ?>
		</select>
		<select name="<?php echo esc_attr( $name ); ?>_jy" class="gvsr-jdate-y">
			<?php for ( $y = $cur_jy - 4; $y <= $cur_jy + 1; $y++ ) : ?>
				<option value="<?php echo esc_attr( $y ); ?>" <?php selected( (int) $sel_jy, $y ); ?>><?php echo esc_html( gv_sr_fa_digits( $y ) ); ?></option>
			<?php endfor; ?>
		</select>
	</span>
	<?php
	return ob_get_clean();
}

/** خواندن یک فیلد تاریخ شمسیِ پست‌شده و تبدیل به Y-m-d میلادی */
function gv_sr_read_jalali_post( $name ) {
	if ( ! isset( $_POST[ $name . '_jy' ], $_POST[ $name . '_jm' ], $_POST[ $name . '_jd' ] ) ) {
		return current_time( 'Y-m-d' );
	}
	$jy = (int) $_POST[ $name . '_jy' ];
	$jm = (int) $_POST[ $name . '_jm' ];
	$jd = (int) $_POST[ $name . '_jd' ];
	if ( $jy < 1300 || $jy > 1500 ) { return current_time( 'Y-m-d' ); }
	list( $gy, $gm, $gd ) = gv_sr_j2g( $jy, $jm, $jd );
	return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
}

/** خواندن یک فیلد تاریخ شمسیِ پست‌شده *داخل یک ردیف تکرارشونده* (آرایه) */
function gv_sr_read_jalali_post_row( $name, $index ) {
	$jy_arr = isset( $_POST[ $name . '_jy' ] ) ? (array) $_POST[ $name . '_jy' ] : array();
	$jm_arr = isset( $_POST[ $name . '_jm' ] ) ? (array) $_POST[ $name . '_jm' ] : array();
	$jd_arr = isset( $_POST[ $name . '_jd' ] ) ? (array) $_POST[ $name . '_jd' ] : array();
	$jy     = isset( $jy_arr[ $index ] ) ? (int) $jy_arr[ $index ] : 0;
	$jm     = isset( $jm_arr[ $index ] ) ? (int) $jm_arr[ $index ] : 1;
	$jd     = isset( $jd_arr[ $index ] ) ? (int) $jd_arr[ $index ] : 1;
	if ( $jy < 1300 || $jy > 1500 ) { return current_time( 'Y-m-d' ); }
	list( $gy, $gm, $gd ) = gv_sr_j2g( $jy, $jm, $jd );
	return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
}

/** خواندن یک فیلد تاریخ شمسیِ ارسال‌شده از طریق GET (برای فیلترهای بازه تاریخی) */
function gv_sr_read_jalali_get( $name, $fallback_mysql_date = '' ) {
	$fallback = $fallback_mysql_date ? $fallback_mysql_date : current_time( 'Y-m-d' );
	if ( ! isset( $_GET[ $name . '_jy' ], $_GET[ $name . '_jm' ], $_GET[ $name . '_jd' ] ) ) {
		return $fallback;
	}
	$jy = (int) $_GET[ $name . '_jy' ];
	$jm = (int) $_GET[ $name . '_jm' ];
	$jd = (int) $_GET[ $name . '_jd' ];
	if ( $jy < 1300 || $jy > 1500 ) { return $fallback; }
	list( $gy, $gm, $gd ) = gv_sr_j2g( $jy, $jm, $jd );
	return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
}

/* ==========================================================================
   ۳) دسترسی به دیتابیس — گزارش‌ها
   ========================================================================== */
function gv_sr_get_report( $id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_reports';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) ); // phpcs:ignore
}

function gv_sr_get_reports( $args = array() ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_reports';

	$defaults = array(
		'user_id'   => 0,
		'status'    => '',
		'search'    => '',
		'date_from' => '',
		'date_to'   => '',
		'orderby'   => 'period_end',
		'order'     => 'DESC',
		'limit'     => 0,
	);
	$args = wp_parse_args( $args, $defaults );

	$where  = array( '1=1' );
	$params = array();

	if ( $args['user_id'] > 0 ) {
		$where[]  = 'user_id = %d';
		$params[] = (int) $args['user_id'];
	}
	if ( '' !== $args['status'] ) {
		$where[]  = 'status = %s';
		$params[] = $args['status'];
	}
	if ( '' !== $args['search'] ) {
		$where[]  = '(client_name LIKE %s OR title LIKE %s)';
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$params[] = $like;
		$params[] = $like;
	}
	if ( '' !== $args['date_from'] ) {
		$where[]  = 'period_end >= %s';
		$params[] = $args['date_from'];
	}
	if ( '' !== $args['date_to'] ) {
		$where[]  = 'period_end <= %s';
		$params[] = $args['date_to'];
	}

	$allowed_orderby = array( 'period_end', 'period_start', 'created_at', 'client_name', 'title', 'hours_spent' );
	$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'period_end';
	$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

	$sql = "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}";
	if ( $args['limit'] > 0 ) {
		$sql .= $wpdb->prepare( ' LIMIT %d', (int) $args['limit'] );
	}

	if ( ! empty( $params ) ) {
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore
	}
	return $wpdb->get_results( $sql ); // phpcs:ignore
}

/** لیست یکتای مشتریان (برای فیلتر پیشخوان) */
function gv_sr_get_clients() {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_reports';
	return $wpdb->get_results( "SELECT DISTINCT client_name, user_id FROM {$t} ORDER BY client_name ASC" ); // phpcs:ignore
}

function gv_sr_save_report( $data, $report_id = 0 ) {
	global $wpdb;
	$t   = $wpdb->prefix . 'gv_sr_reports';
	$now = current_time( 'mysql' );

	$row = array(
		'client_name'    => sanitize_text_field( $data['client_name'] ),
		'user_id'        => (int) $data['user_id'],
		'title'          => sanitize_text_field( $data['title'] ),
		'period_start'   => $data['period_start'],
		'period_end'     => $data['period_end'],
		'summary'        => wp_kses_post( $data['summary'] ),
		'next_steps'     => wp_kses_post( $data['next_steps'] ),
		'hours_spent'    => (float) $data['hours_spent'],
		'traffic_before' => (int) $data['traffic_before'],
		'traffic_after'  => (int) $data['traffic_after'],
		'overall_score'  => max( 0, min( 100, (int) $data['overall_score'] ) ),
		'status'         => in_array( $data['status'], array( 'draft', 'published' ), true ) ? $data['status'] : 'draft',
		'visibility'     => wp_json_encode( $data['visibility'] ),
		'updated_at'     => $now,
	);

	if ( $report_id > 0 ) {
		$wpdb->update( $t, $row, array( 'id' => $report_id ) ); // phpcs:ignore
		return $report_id;
	}

	$row['author_id']  = get_current_user_id();
	$row['created_at'] = $now;
	$wpdb->insert( $t, $row ); // phpcs:ignore
	return (int) $wpdb->insert_id;
}

function gv_sr_delete_report( $report_id ) {
	global $wpdb;
	$report_id = (int) $report_id;
	$wpdb->delete( $wpdb->prefix . 'gv_sr_reports', array( 'id' => $report_id ) );  // phpcs:ignore
	$wpdb->delete( $wpdb->prefix . 'gv_sr_keywords', array( 'report_id' => $report_id ) ); // phpcs:ignore
	$wpdb->delete( $wpdb->prefix . 'gv_sr_tasks', array( 'report_id' => $report_id ) ); // phpcs:ignore
	$wpdb->delete( $wpdb->prefix . 'gv_sr_growth', array( 'report_id' => $report_id ) ); // phpcs:ignore
}

/* ---------------- کلمات کلیدی ---------------- */
function gv_sr_get_keywords( $report_id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_keywords';
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE report_id = %d ORDER BY sort_order ASC, id ASC", (int) $report_id ) ); // phpcs:ignore
}
function gv_sr_save_keywords( $report_id, $rows ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_keywords';
	$wpdb->delete( $t, array( 'report_id' => $report_id ) ); // phpcs:ignore
	if ( empty( $rows ) ) { return; }
	$order = 0;
	foreach ( $rows as $r ) {
		if ( empty( trim( (string) $r['keyword'] ) ) ) { continue; }
		$wpdb->insert( $t, array( // phpcs:ignore
			'report_id'     => $report_id,
			'keyword'       => sanitize_text_field( $r['keyword'] ),
			'search_engine' => sanitize_text_field( $r['search_engine'] ?: 'گوگل' ),
			'page_url'      => esc_url_raw( $r['page_url'] ),
			'prev_rank'     => (int) $r['prev_rank'],
			'curr_rank'     => (int) $r['curr_rank'],
			'note'          => sanitize_text_field( $r['note'] ),
			'sort_order'    => $order++,
		) );
	}
}

/* ---------------- فعالیت‌ها / محتوا ---------------- */
function gv_sr_get_tasks( $report_id, $orderby = 'work_date', $order = 'DESC' ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_tasks';
	$allowed = array( 'work_date', 'task_type', 'title', 'hours' );
	$orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'work_date';
	$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE report_id = %d ORDER BY {$orderby} {$order}", (int) $report_id ) ); // phpcs:ignore
}
function gv_sr_save_tasks( $report_id, $rows ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_tasks';
	$wpdb->delete( $t, array( 'report_id' => $report_id ) ); // phpcs:ignore
	if ( empty( $rows ) ) { return; }
	$types = array_keys( gv_sr_task_types() );
	foreach ( $rows as $r ) {
		if ( empty( trim( (string) $r['title'] ) ) ) { continue; }
		$wpdb->insert( $t, array( // phpcs:ignore
			'report_id'      => $report_id,
			'task_type'      => in_array( $r['task_type'], $types, true ) ? $r['task_type'] : 'other',
			'title'          => sanitize_text_field( $r['title'] ),
			'url'            => esc_url_raw( $r['url'] ),
			'target_keyword' => sanitize_text_field( $r['target_keyword'] ),
			'work_date'      => $r['work_date'],
			'hours'          => (float) $r['hours'],
			'note'           => sanitize_text_field( $r['note'] ),
		) );
	}
}

/* ---------------- رشد صفحات ---------------- */
function gv_sr_get_growth( $report_id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_growth';
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE report_id = %d ORDER BY id ASC", (int) $report_id ) ); // phpcs:ignore
}
function gv_sr_save_growth( $report_id, $rows ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_growth';
	$wpdb->delete( $t, array( 'report_id' => $report_id ) ); // phpcs:ignore
	if ( empty( $rows ) ) { return; }
	foreach ( $rows as $r ) {
		if ( empty( trim( (string) $r['metric_label'] ) ) && empty( trim( (string) $r['page_title'] ) ) ) { continue; }
		$wpdb->insert( $t, array( // phpcs:ignore
			'report_id'    => $report_id,
			'page_title'   => sanitize_text_field( $r['page_title'] ),
			'page_url'     => esc_url_raw( $r['page_url'] ),
			'metric_label' => sanitize_text_field( $r['metric_label'] ?: 'رشد' ),
			'before_value' => sanitize_text_field( $r['before_value'] ),
			'after_value'  => sanitize_text_field( $r['after_value'] ),
			'note'         => sanitize_text_field( $r['note'] ),
		) );
	}
}

/* ==========================================================================
   ۴) تحلیل و تجمیع داده برای پنل مشتری / داشبورد
   ========================================================================== */

/** وضعیت تغییر رتبه یک کلمه کلیدی به‌صورت برچسب آماده نمایش */
function gv_sr_rank_delta( $prev, $curr ) {
	$prev = (int) $prev;
	$curr = (int) $curr;

	if ( 0 === $curr ) {
		return array( 'type' => 'out', 'diff' => 0, 'label' => 'خارج از ۱۰۰', 'color' => '#94a3b8', 'icon' => '—' );
	}
	if ( 0 === $prev ) {
		return array( 'type' => 'new', 'diff' => 0, 'label' => 'ورود تازه به رتبه ' . gv_sr_fa_digits( $curr ), 'color' => '#2563eb', 'icon' => '✨' );
	}
	if ( $curr < $prev ) {
		$diff = $prev - $curr;
		return array( 'type' => 'up', 'diff' => $diff, 'label' => gv_sr_fa_digits( $diff ) . ' رتبه بهبود', 'color' => '#16a34a', 'icon' => '▲' );
	}
	if ( $curr > $prev ) {
		$diff = $curr - $prev;
		return array( 'type' => 'down', 'diff' => $diff, 'label' => gv_sr_fa_digits( $diff ) . ' رتبه افت', 'color' => '#dc2626', 'icon' => '▼' );
	}
	return array( 'type' => 'same', 'diff' => 0, 'label' => 'بدون تغییر', 'color' => '#64748b', 'icon' => '■' );
}

/** بازه‌های زمانیِ گزارش تعداد محتوا */
function gv_sr_period_windows() {
	return array( 7, 14, 21, 30, 60, 90 );
}

/**
 * شمارش تعداد محتوای تولید/بروزرسانی‌شده در بازه‌های ۷/۱۴/۲۱/۳۰/۶۰/۹۰ روز اخیر
 * برای یک مشتری خاص (بر اساس user_id)، فقط از گزارش‌های منتشرشده‌ای که
 * بخش «ریز فعالیت‌ها» در آن‌ها برای مشتری فعال است.
 */
function gv_sr_count_content_periods( $user_id ) {
	$windows = gv_sr_period_windows();
	$out     = array_fill_keys( $windows, 0 );
	if ( $user_id <= 0 ) { return $out; }

	global $wpdb;
	$t_tasks   = $wpdb->prefix . 'gv_sr_tasks';
	$t_reports = $wpdb->prefix . 'gv_sr_reports';

	$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
		"SELECT t.work_date, t.task_type, r.visibility FROM {$t_tasks} t
		 INNER JOIN {$t_reports} r ON r.id = t.report_id
		 WHERE r.user_id = %d AND r.status = 'published' AND t.task_type IN ('content_new','content_update')",
		$user_id
	) );

	$today_ts = strtotime( current_time( 'Y-m-d' ) );
	foreach ( $rows as $row ) {
		$vis = json_decode( (string) $row->visibility, true );
		if ( is_array( $vis ) && isset( $vis['show_tasks'] ) && ! $vis['show_tasks'] ) { continue; }
		$days_ago = (int) floor( ( $today_ts - strtotime( $row->work_date ) ) / DAY_IN_SECONDS );
		if ( $days_ago < 0 ) { continue; }
		foreach ( $windows as $w ) {
			if ( $days_ago <= $w ) { $out[ $w ]++; }
		}
	}
	return $out;
}

/** مجموع ساعت کار ثبت‌شده برای یک مشتری، در گزارش‌های منتشرشده و قابل نمایش */
function gv_sr_total_hours( $user_id ) {
	if ( $user_id <= 0 ) { return 0.0; }
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_reports';
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT hours_spent, visibility FROM {$t} WHERE user_id = %d AND status = 'published'", $user_id ) ); // phpcs:ignore
	$sum = 0.0;
	foreach ( $rows as $r ) {
		$vis = json_decode( (string) $r->visibility, true );
		if ( is_array( $vis ) && isset( $vis['show_hours'] ) && ! $vis['show_hours'] ) { continue; }
		$sum += (float) $r->hours_spent;
	}
	return $sum;
}

/** تاریخچه رتبه هر کلمه کلیدی در طول زمان (برای نمودار روند)، به تفکیک کلمه */
function gv_sr_keyword_history( $user_id ) {
	$out = array();
	if ( $user_id <= 0 ) { return $out; }
	global $wpdb;
	$t_kw = $wpdb->prefix . 'gv_sr_keywords';
	$t_r  = $wpdb->prefix . 'gv_sr_reports';

	$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
		"SELECT k.keyword, k.prev_rank, k.curr_rank, r.period_end, r.visibility FROM {$t_kw} k
		 INNER JOIN {$t_r} r ON r.id = k.report_id
		 WHERE r.user_id = %d AND r.status = 'published'
		 ORDER BY r.period_end ASC",
		$user_id
	) );

	foreach ( $rows as $row ) {
		$vis = json_decode( (string) $row->visibility, true );
		if ( is_array( $vis ) && isset( $vis['show_keywords'] ) && ! $vis['show_keywords'] ) { continue; }
		if ( ! isset( $out[ $row->keyword ] ) ) { $out[ $row->keyword ] = array(); }
		$out[ $row->keyword ][] = array(
			'date' => $row->period_end,
			'rank' => (int) $row->curr_rank,
		);
	}
	return $out;
}

/** روند ترافیک ارگانیک قبل/بعد به ازای هر گزارش، مرتب بر اساس تاریخ */
function gv_sr_traffic_trend( $user_id ) {
	$out = array();
	if ( $user_id <= 0 ) { return $out; }
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_reports';
	$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
		"SELECT title, period_end, traffic_before, traffic_after, visibility FROM {$t}
		 WHERE user_id = %d AND status = 'published' ORDER BY period_end ASC",
		$user_id
	) );
	foreach ( $rows as $r ) {
		$vis = json_decode( (string) $r->visibility, true );
		if ( is_array( $vis ) && isset( $vis['show_traffic'] ) && ! $vis['show_traffic'] ) { continue; }
		$out[] = array(
			'label'  => gv_sr_jalali_numeric( $r->period_end ),
			'before' => (int) $r->traffic_before,
			'after'  => (int) $r->traffic_after,
		);
	}
	return $out;
}

/** خلاصه وضعیت کلمات کلیدی یک مشتری (بهبود/افت/بدون تغییر/تازه) */
function gv_sr_keyword_status_summary( $user_id ) {
	$summary = array( 'up' => 0, 'down' => 0, 'same' => 0, 'new' => 0, 'out' => 0 );
	if ( $user_id <= 0 ) { return $summary; }
	global $wpdb;
	$t_kw = $wpdb->prefix . 'gv_sr_keywords';
	$t_r  = $wpdb->prefix . 'gv_sr_reports';
	$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
		"SELECT k.prev_rank, k.curr_rank, r.visibility FROM {$t_kw} k
		 INNER JOIN {$t_r} r ON r.id = k.report_id
		 WHERE r.user_id = %d AND r.status = 'published'",
		$user_id
	) );
	foreach ( $rows as $r ) {
		$vis = json_decode( (string) $r->visibility, true );
		if ( is_array( $vis ) && isset( $vis['show_keywords'] ) && ! $vis['show_keywords'] ) { continue; }
		$delta = gv_sr_rank_delta( $r->prev_rank, $r->curr_rank );
		$summary[ $delta['type'] ]++;
	}
	return $summary;
}

/** خلاصه وضعیت کلمات کلیدی در کل گزارش‌های مشتریان (صرف‌نظر از مشتری خاص)، در یک بازه تاریخی بر اساس پایان بازه گزارش */
function gv_sr_global_keyword_summary( $date_from = '', $date_to = '' ) {
	global $wpdb;
	$t_kw = $wpdb->prefix . 'gv_sr_keywords';
	$t_r  = $wpdb->prefix . 'gv_sr_reports';

	$where  = array( '1=1' );
	$params = array();
	if ( '' !== $date_from ) { $where[] = 'r.period_end >= %s'; $params[] = $date_from; }
	if ( '' !== $date_to )   { $where[] = 'r.period_end <= %s'; $params[] = $date_to; }

	$sql = "SELECT k.prev_rank, k.curr_rank FROM {$t_kw} k INNER JOIN {$t_r} r ON r.id = k.report_id WHERE " . implode( ' AND ', $where );
	$rows = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore

	$summary = array( 'up' => 0, 'down' => 0, 'same' => 0, 'new' => 0, 'out' => 0 );
	foreach ( $rows as $r ) {
		$delta = gv_sr_rank_delta( $r->prev_rank, $r->curr_rank );
		$summary[ $delta['type'] ]++;
	}
	return $summary;
}

/** شمارش فعالیت‌ها/محتوا به تفکیک نوع، در کل گزارش‌های مشتریان طی یک بازه تاریخی */
function gv_sr_global_task_counts( $date_from = '', $date_to = '' ) {
	global $wpdb;
	$t_tasks = $wpdb->prefix . 'gv_sr_tasks';
	$t_r     = $wpdb->prefix . 'gv_sr_reports';

	$where  = array( '1=1' );
	$params = array();
	if ( '' !== $date_from ) { $where[] = 'r.period_end >= %s'; $params[] = $date_from; }
	if ( '' !== $date_to )   { $where[] = 'r.period_end <= %s'; $params[] = $date_to; }

	$sql = "SELECT t.task_type, COUNT(*) AS c FROM {$t_tasks} t INNER JOIN {$t_r} r ON r.id = t.report_id WHERE " . implode( ' AND ', $where ) . ' GROUP BY t.task_type';
	$rows = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore

	$out = array();
	foreach ( array_keys( gv_sr_task_types() ) as $type ) { $out[ $type ] = 0; }
	foreach ( $rows as $r ) { $out[ $r->task_type ] = (int) $r->c; }
	return $out;
}

/* ==========================================================================
   ۵) نمودارهای SVG سبک (بدون هیچ وابستگی خارجی)
   ========================================================================== */

/** نمودار میله‌ای ساده */
function gv_sr_svg_bar_chart( $items, $color = '#059669', $width = 640, $height = 200 ) {
	if ( empty( $items ) ) { return '<div class="gvsr-chart-empty">داده‌ای برای نمایش نمودار وجود ندارد.</div>'; }

	$pad_l = 34; $pad_b = 30; $pad_t = 14; $pad_r = 10;
	$chart_w = $width - $pad_l - $pad_r;
	$chart_h = $height - $pad_t - $pad_b;
	$max_v   = max( 1, max( wp_list_pluck( $items, 'value' ) ) );
	$n       = count( $items );
	$gap     = 10;
	$bar_w   = max( 8, ( $chart_w - ( $gap * ( $n - 1 ) ) ) / $n );

	$svg  = '<svg viewBox="0 0 ' . esc_attr( $width ) . ' ' . esc_attr( $height ) . '" xmlns="http://www.w3.org/2000/svg" class="gvsr-svg-chart">';
	$svg .= '<line x1="' . $pad_l . '" y1="' . ( $height - $pad_b ) . '" x2="' . ( $width - $pad_r ) . '" y2="' . ( $height - $pad_b ) . '" stroke="#e2e8f0" stroke-width="1"/>';

	$x = $pad_l;
	foreach ( $items as $item ) {
		$v      = max( 0, (float) $item['value'] );
		$bar_h  = $max_v > 0 ? ( $v / $max_v ) * $chart_h : 0;
		$y      = $height - $pad_b - $bar_h;
		$svg   .= '<rect x="' . round( $x, 1 ) . '" y="' . round( $y, 1 ) . '" width="' . round( $bar_w, 1 ) . '" height="' . round( $bar_h, 1 ) . '" rx="4" fill="' . esc_attr( $color ) . '"><title>' . esc_html( $item['label'] . ': ' . $item['value'] ) . '</title></rect>';
		$svg   .= '<text x="' . round( $x + $bar_w / 2, 1 ) . '" y="' . ( $height - $pad_b + 16 ) . '" font-size="10" fill="#64748b" text-anchor="middle">' . esc_html( $item['label'] ) . '</text>';
		if ( $v > 0 ) {
			$svg .= '<text x="' . round( $x + $bar_w / 2, 1 ) . '" y="' . round( $y - 4, 1 ) . '" font-size="10" fill="#334155" text-anchor="middle" font-weight="700">' . esc_html( gv_sr_fa_digits( $v ) ) . '</text>';
		}
		$x += $bar_w + $gap;
	}
	$svg .= '</svg>';
	return $svg;
}

/**
 * نمودار خطی. اگر invert=true باشد (مناسب برای رتبه کلمه کلیدی)، مقدار
 * کوچک‌تر بالاتر از نمودار قرار می‌گیرد چون رتبه ۱ بهترین حالت است.
 */
function gv_sr_svg_line_chart( $items, $color = '#2563eb', $width = 640, $height = 200, $invert = false ) {
	if ( count( $items ) < 1 ) { return '<div class="gvsr-chart-empty">داده‌ای برای نمایش نمودار وجود ندارد.</div>'; }

	$pad_l = 34; $pad_b = 30; $pad_t = 18; $pad_r = 14;
	$chart_w = $width - $pad_l - $pad_r;
	$chart_h = $height - $pad_t - $pad_b;

	$values = wp_list_pluck( $items, 'value' );
	$min_v  = min( $values );
	$max_v  = max( $values );
	if ( $max_v === $min_v ) { $max_v = $min_v + 1; }

	$n = count( $items );
	$step = $n > 1 ? $chart_w / ( $n - 1 ) : 0;

	$points = array();
	$i = 0;
	foreach ( $items as $item ) {
		$v = (float) $item['value'];
		$ratio = ( $v - $min_v ) / ( $max_v - $min_v );
		if ( $invert ) { $ratio = 1 - $ratio; }
		$x = $pad_l + ( $step * $i );
		$y = $pad_t + ( 1 - $ratio ) * $chart_h;
		$points[] = array( round( $x, 1 ), round( $y, 1 ), $item['label'], $v );
		$i++;
	}

	$svg  = '<svg viewBox="0 0 ' . esc_attr( $width ) . ' ' . esc_attr( $height ) . '" xmlns="http://www.w3.org/2000/svg" class="gvsr-svg-chart">';
	$svg .= '<line x1="' . $pad_l . '" y1="' . ( $height - $pad_b ) . '" x2="' . ( $width - $pad_r ) . '" y2="' . ( $height - $pad_b ) . '" stroke="#e2e8f0" stroke-width="1"/>';

	$path_line = '';
	$path_area = 'M ' . $points[0][0] . ' ' . ( $height - $pad_b );
	foreach ( $points as $p ) {
		$path_line .= ( '' === $path_line ? 'M ' : ' L ' ) . $p[0] . ' ' . $p[1];
		$path_area .= ' L ' . $p[0] . ' ' . $p[1];
	}
	$path_area .= ' L ' . $points[ count( $points ) - 1 ][0] . ' ' . ( $height - $pad_b ) . ' Z';

	$svg .= '<path d="' . esc_attr( $path_area ) . '" fill="' . esc_attr( $color ) . '" opacity="0.10"/>';
	$svg .= '<path d="' . esc_attr( $path_line ) . '" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';

	foreach ( $points as $p ) {
		$svg .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="3.4" fill="#fff" stroke="' . esc_attr( $color ) . '" stroke-width="2"><title>' . esc_html( $p[2] . ': ' . $p[3] ) . '</title></circle>';
		$svg .= '<text x="' . $p[0] . '" y="' . ( $height - $pad_b + 16 ) . '" font-size="9.5" fill="#64748b" text-anchor="middle">' . esc_html( $p[2] ) . '</text>';
	}
	$svg .= '</svg>';
	return $svg;
}

/** نمودار دو-میله‌ای مقایسه‌ای (قبل/بعد) */
function gv_sr_svg_dual_bar_chart( $items, $color_a = '#94a3b8', $color_b = '#059669', $width = 640, $height = 200 ) {
	if ( empty( $items ) ) { return '<div class="gvsr-chart-empty">داده‌ای برای نمایش نمودار وجود ندارد.</div>'; }

	$pad_l = 34; $pad_b = 30; $pad_t = 14; $pad_r = 10;
	$chart_w = $width - $pad_l - $pad_r;
	$chart_h = $height - $pad_t - $pad_b;

	$all_vals = array();
	foreach ( $items as $it ) { $all_vals[] = $it['before']; $all_vals[] = $it['after']; }
	$max_v = max( 1, max( $all_vals ) );

	$n     = count( $items );
	$gap   = 18;
	$group_w = ( $chart_w - ( $gap * ( $n - 1 ) ) ) / $n;
	$bar_w   = max( 6, ( $group_w - 4 ) / 2 );

	$svg  = '<svg viewBox="0 0 ' . esc_attr( $width ) . ' ' . esc_attr( $height ) . '" xmlns="http://www.w3.org/2000/svg" class="gvsr-svg-chart">';
	$svg .= '<line x1="' . $pad_l . '" y1="' . ( $height - $pad_b ) . '" x2="' . ( $width - $pad_r ) . '" y2="' . ( $height - $pad_b ) . '" stroke="#e2e8f0" stroke-width="1"/>';

	$x = $pad_l;
	foreach ( $items as $item ) {
		$bh_before = ( $item['before'] / $max_v ) * $chart_h;
		$bh_after  = ( $item['after'] / $max_v ) * $chart_h;

		$svg .= '<rect x="' . round( $x, 1 ) . '" y="' . round( $height - $pad_b - $bh_before, 1 ) . '" width="' . round( $bar_w, 1 ) . '" height="' . round( $bh_before, 1 ) . '" rx="3" fill="' . esc_attr( $color_a ) . '"><title>قبل: ' . esc_html( $item['before'] ) . '</title></rect>';
		$svg .= '<rect x="' . round( $x + $bar_w + 4, 1 ) . '" y="' . round( $height - $pad_b - $bh_after, 1 ) . '" width="' . round( $bar_w, 1 ) . '" height="' . round( $bh_after, 1 ) . '" rx="3" fill="' . esc_attr( $color_b ) . '"><title>بعد: ' . esc_html( $item['after'] ) . '</title></rect>';
		$svg .= '<text x="' . round( $x + $group_w / 2, 1 ) . '" y="' . ( $height - $pad_b + 16 ) . '" font-size="9.5" fill="#64748b" text-anchor="middle">' . esc_html( $item['label'] ) . '</text>';

		$x += $group_w + $gap;
	}
	$svg .= '</svg>';
	return $svg;
}

/* ==========================================================================
   ۵.۱) تاریخ شمسی — نام روز هفته و بازه ماه شمسی (برای تایم‌شیت و حقوق)
   ========================================================================== */
function gv_sr_weekday_name( $mysql_date ) {
	if ( empty( $mysql_date ) || '0000-00-00' === $mysql_date ) { return '—'; }
	$ts = strtotime( $mysql_date . ' 12:00:00' );
	if ( false === $ts ) { return '—'; }
	$names = array( 0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه' );
	return $names[ (int) gmdate( 'w', $ts ) ];
}

/** تعداد روزهای اسفند سال شمسی $jy (۲۹ یا ۳۰)، با استفاده از رفت‌وبرگشت دقیق به فروردین سال بعد */
function gv_sr_jalali_esfand_days( $jy ) {
	list( $gy, $gm, $gd ) = gv_sr_j2g( (int) $jy + 1, 1, 1 );
	$ts = mktime( 12, 0, 0, $gm, $gd, $gy );
	if ( false === $ts ) { return 29; }
	$ts -= DAY_IN_SECONDS;
	list( , , $jd ) = gv_sr_g2j( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ), (int) gmdate( 'j', $ts ) );
	return (int) $jd;
}

/** اولین و آخرین روز یک ماه شمسی را به تاریخ میلادی (Y-m-d) برمی‌گرداند */
function gv_sr_jalali_month_bounds( $jy, $jm ) {
	$jy = (int) $jy;
	$jm = max( 1, min( 12, (int) $jm ) );

	if ( $jm <= 6 ) {
		$last_day = 31;
	} elseif ( $jm <= 11 ) {
		$last_day = 30;
	} else {
		$last_day = gv_sr_jalali_esfand_days( $jy );
	}

	list( $sgy, $sgm, $sgd ) = gv_sr_j2g( $jy, $jm, 1 );
	list( $egy, $egm, $egd ) = gv_sr_j2g( $jy, $jm, $last_day );

	return array(
		sprintf( '%04d-%02d-%02d', $sgy, $sgm, $sgd ),
		sprintf( '%04d-%02d-%02d', $egy, $egm, $egd ),
	);
}

/** بازه پیش‌فرض حقوقی: از اول تا آخر ماه شمسی جاری */
function gv_sr_current_jalali_month_bounds() {
	$now = current_time( 'timestamp' );
	list( $jy, $jm ) = gv_sr_g2j( (int) date( 'Y', $now ), (int) date( 'n', $now ), (int) date( 'j', $now ) );
	return gv_sr_jalali_month_bounds( $jy, $jm );
}

/** محاسبه ساعت بین دو زمان HH:MM (اگر پایان از شروع کوچک‌تر بود، شبانه در نظر گرفته می‌شود) */
function gv_sr_calc_range_hours( $start, $end ) {
	if ( empty( $start ) || empty( $end ) ) { return 0.0; }
	if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $start ) || ! preg_match( '/^\d{1,2}:\d{2}$/', $end ) ) { return 0.0; }
	list( $sh, $sm ) = array_map( 'intval', explode( ':', $start ) );
	list( $eh, $em ) = array_map( 'intval', explode( ':', $end ) );
	$start_min = ( $sh * 60 ) + $sm;
	$end_min   = ( $eh * 60 ) + $em;
	if ( $end_min <= $start_min ) { $end_min += 24 * 60; }
	return round( ( $end_min - $start_min ) / 60, 2 );
}

/* ==========================================================================
   ۵.۲) کارمندان تیم سئو
   ========================================================================== */
function gv_sr_get_employees( $active_only = false ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_employees';
	if ( $active_only ) {
		return $wpdb->get_results( "SELECT * FROM {$t} WHERE active = 1 ORDER BY name ASC" ); // phpcs:ignore
	}
	return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY name ASC" ); // phpcs:ignore
}
function gv_sr_get_employee( $id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_employees';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) ); // phpcs:ignore
}
function gv_sr_get_employee_by_code( $code ) {
	global $wpdb;
	$code = gv_sr_sanitize_employee_code( $code );
	if ( '' === $code ) { return null; }
	$t = $wpdb->prefix . 'gv_sr_employees';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE global_code = %s", $code ) ); // phpcs:ignore
}
function gv_sr_sanitize_employee_code( $code ) {
	$code = strtolower( trim( (string) $code ) );
	$code = preg_replace( '/[^a-z0-9\-]/', '', $code );
	return substr( $code, 0, 40 );
}
/** ساخت یک کد کارمندی یکتا و خوانا بر اساس نام (برای اشتراک بین چند سایت) */
function gv_sr_generate_employee_code( $name ) {
	$base = sanitize_title( $name );
	if ( '' === $base ) { $base = 'emp'; }
	do {
		$candidate = $base . '-' . wp_rand( 1000, 9999 );
	} while ( gv_sr_get_employee_by_code( $candidate ) );
	return $candidate;
}
function gv_sr_save_employee( $data, $employee_id = 0 ) {
	global $wpdb;
	$t   = $wpdb->prefix . 'gv_sr_employees';
	$row = array(
		'name'        => sanitize_text_field( $data['name'] ),
		'hourly_rate' => max( 0, (int) $data['hourly_rate'] ),
		'active'      => empty( $data['active'] ) ? 0 : 1,
	);

	if ( ! empty( $data['global_code'] ) ) {
		$row['global_code'] = gv_sr_sanitize_employee_code( $data['global_code'] );
	}

	if ( $employee_id > 0 ) {
		$wpdb->update( $t, $row, array( 'id' => $employee_id ) ); // phpcs:ignore
		return $employee_id;
	}

	if ( empty( $row['global_code'] ) ) {
		$row['global_code'] = gv_sr_generate_employee_code( $row['name'] );
	}
	$row['created_at'] = current_time( 'mysql' );
	$wpdb->insert( $t, $row ); // phpcs:ignore
	return (int) $wpdb->insert_id;
}

/** کارمندی که از طریق کوکی شناسایی شده (۰ یعنی هنوز شناسایی نشده) */
function gv_sr_current_employee_id() {
	if ( empty( $_COOKIE[ GV_SR_EMP_COOKIE ] ) ) { return 0; }
	$id = (int) $_COOKIE[ GV_SR_EMP_COOKIE ];
	if ( $id <= 0 ) { return 0; }
	$emp = gv_sr_get_employee( $id );
	return ( $emp && (int) $emp->active === 1 ) ? $id : 0;
}
function gv_sr_current_employee() {
	$id = gv_sr_current_employee_id();
	return $id > 0 ? gv_sr_get_employee( $id ) : null;
}

/* ==========================================================================
   ۵.۳) کارکرد روزانه (تایم‌شیت) کارمندان
   ========================================================================== */
function gv_sr_get_timelogs( $args = array() ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_timelogs';

	$defaults = array(
		'employee_id' => 0,
		'date_from'   => '',
		'date_to'     => '',
		'orderby'     => 'work_date',
		'order'       => 'ASC',
	);
	$args = wp_parse_args( $args, $defaults );

	$where  = array( '1=1' );
	$params = array();
	if ( $args['employee_id'] > 0 ) {
		$where[]  = 'employee_id = %d';
		$params[] = (int) $args['employee_id'];
	}
	if ( '' !== $args['date_from'] ) {
		$where[]  = 'work_date >= %s';
		$params[] = $args['date_from'];
	}
	if ( '' !== $args['date_to'] ) {
		$where[]  = 'work_date <= %s';
		$params[] = $args['date_to'];
	}
	$allowed_orderby = array( 'work_date', 'hours', 'created_at' );
	$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'work_date';
	$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

	$sql = "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}, id {$order}";
	if ( ! empty( $params ) ) {
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore
	}
	return $wpdb->get_results( $sql ); // phpcs:ignore
}
function gv_sr_get_timelog( $id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_timelogs';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) ); // phpcs:ignore
}
function gv_sr_save_timelog( $data, $timelog_id = 0 ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_timelogs';

	$mode = in_array( $data['entry_mode'], array( 'range', 'timer' ), true ) ? $data['entry_mode'] : 'manual';
	if ( 'range' === $mode ) {
		$hours = gv_sr_calc_range_hours( $data['start_time'], $data['end_time'] );
		$start = $data['start_time'];
		$end   = $data['end_time'];
	} elseif ( 'timer' === $mode ) {
		// در حالت تایمر، ساعت شروع/پایان/مدت از قبل توسط gv_sr_stop_timer() محاسبه و ارسال شده است.
		$hours = round( (float) $data['hours'], 2 );
		$start = isset( $data['start_time'] ) ? $data['start_time'] : null;
		$end   = isset( $data['end_time'] ) ? $data['end_time'] : null;
	} else {
		$h     = max( 0, (int) $data['manual_h'] );
		$m     = max( 0, min( 59, (int) $data['manual_m'] ) );
		$hours = round( $h + ( $m / 60 ), 2 );
		$start = null;
		$end   = null;
	}

	$row = array(
		'employee_id' => (int) $data['employee_id'],
		'work_date'   => $data['work_date'],
		'entry_mode'  => $mode,
		'start_time'  => $start,
		'end_time'    => $end,
		'hours'       => $hours,
		'client_name' => sanitize_text_field( (string) $data['client_name'] ),
		'project_id'  => isset( $data['project_id'] ) ? (int) $data['project_id'] : 0,
		'note'        => sanitize_text_field( (string) $data['note'] ),
	);

	if ( $timelog_id > 0 ) {
		$wpdb->update( $t, $row, array( 'id' => $timelog_id ) ); // phpcs:ignore
		return $timelog_id;
	}
	$row['created_at'] = current_time( 'mysql' );
	$wpdb->insert( $t, $row ); // phpcs:ignore
	return (int) $wpdb->insert_id;
}
function gv_sr_delete_timelog( $id ) {
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'gv_sr_timelogs', array( 'id' => (int) $id ) ); // phpcs:ignore
}
function gv_sr_employee_total_hours( $employee_id, $date_from = '', $date_to = '' ) {
	$logs = gv_sr_get_timelogs( array( 'employee_id' => $employee_id, 'date_from' => $date_from, 'date_to' => $date_to ) );
	$sum  = 0.0;
	foreach ( $logs as $l ) { $sum += (float) $l->hours; }
	return round( $sum, 2 );
}

/* ==========================================================================
   ۵.۳.۱) تایمر زنده کارکرد (استارت / استاپ)
   ------------------------------------------------------------
   کارمند دکمه‌ی «شروع تایمر» را می‌زند؛ لحظه‌ی شروع در دیتابیس
   ذخیره می‌شود و حتی اگر تب/مرورگر را ببندد، تایمر (که فقط یک
   رکورد «از چه زمانی شروع شده» است) در پس‌زمینه پابرجا می‌ماند.
   با زدن «توقف»، مدت‌زمان سپری‌شده محاسبه و یک ردیف کارکرد
   (entry_mode = timer) برای همان روز ثبت می‌شود.
   ========================================================================== */
function gv_sr_get_active_timer( $employee_id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_active_timers';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE employee_id = %d", (int) $employee_id ) ); // phpcs:ignore
}
function gv_sr_start_timer( $employee_id, $client_name = '', $note = '' ) {
	$existing = gv_sr_get_active_timer( $employee_id );
	if ( $existing ) { return $existing; }
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_active_timers';
	$wpdb->insert( $t, array( // phpcs:ignore
		'employee_id' => (int) $employee_id,
		'started_at'  => current_time( 'mysql' ),
		'client_name' => sanitize_text_field( $client_name ),
		'note'        => sanitize_text_field( $note ),
		'created_at'  => current_time( 'mysql' ),
	) );
	return gv_sr_get_active_timer( $employee_id );
}
/** توقف تایمر جاری یک کارمند؛ یک ردیف کارکرد جدید می‌سازد و شناسه‌ی آن را برمی‌گرداند (یا false در صورت نبود تایمر فعال) */
function gv_sr_stop_timer( $employee_id, $client_name_override = null, $note_override = null ) {
	$timer = gv_sr_get_active_timer( $employee_id );
	if ( ! $timer ) { return false; }

	$now_mysql = current_time( 'mysql' );
	// هر دو زمان با یک روش یکسان (تاریخ محلی سایت به‌صورت رشته‌ی ساده) محاسبه می‌شوند تا اختلاف
	// منطقه‌زمانی سرور تأثیری روی محاسبه‌ی مدت‌زمان سپری‌شده نگذارد.
	$start_ts = strtotime( $timer->started_at );
	$now_ts   = strtotime( $now_mysql );
	$hours    = round( max( 0, $now_ts - $start_ts ) / 3600, 2 );

	$timelog_id = gv_sr_save_timelog( array(
		'employee_id' => $employee_id,
		'work_date'   => substr( $timer->started_at, 0, 10 ),
		'entry_mode'  => 'timer',
		'start_time'  => substr( $timer->started_at, 11, 5 ),
		'end_time'    => substr( $now_mysql, 11, 5 ),
		'hours'       => $hours,
		'client_name' => null !== $client_name_override ? $client_name_override : $timer->client_name,
		'note'        => null !== $note_override ? $note_override : $timer->note,
	) );

	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'gv_sr_active_timers', array( 'employee_id' => (int) $employee_id ) ); // phpcs:ignore

	do_action( 'gv_sr_after_save_timelog', $employee_id );

	return array( 'timelog_id' => $timelog_id, 'hours' => $hours );
}

/* ==========================================================================
   ۵.۴) ورود/احراز هویت بخش «مدیریت تیم» با رمز عبور جدا از پیشخوان
   ========================================================================== */
function gv_sr_team_is_authed() {
	if ( empty( $_COOKIE[ GV_SR_TEAM_COOKIE ] ) ) { return false; }
	$token = sanitize_text_field( $_COOKIE[ GV_SR_TEAM_COOKIE ] );
	return (bool) get_transient( 'gv_sr_team_sess_' . $token );
}
function gv_sr_team_login( $password ) {
	$hash = get_option( 'gv_sr_manager_pass_hash', '' );
	if ( empty( $hash ) || ! password_verify( $password, $hash ) ) { return false; }
	$token = wp_generate_password( 40, false, false );
	set_transient( 'gv_sr_team_sess_' . $token, 1, 12 * HOUR_IN_SECONDS );
	setcookie( GV_SR_TEAM_COOKIE, $token, time() + ( 12 * HOUR_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ GV_SR_TEAM_COOKIE ] = $token;
	return true;
}
function gv_sr_team_logout() {
	if ( ! empty( $_COOKIE[ GV_SR_TEAM_COOKIE ] ) ) {
		delete_transient( 'gv_sr_team_sess_' . sanitize_text_field( $_COOKIE[ GV_SR_TEAM_COOKIE ] ) );
		setcookie( GV_SR_TEAM_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		unset( $_COOKIE[ GV_SR_TEAM_COOKIE ] );
	}
}

/* ==========================================================================
   ۶) منوی مدیریت
   ========================================================================== */
add_action( 'admin_menu', 'gv_sr_admin_menu' );
function gv_sr_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'گزارش عملکرد سئوی مشتری | Groot Vision',
		'📑 گزارش سئوی مشتری',
		'manage_options',
		GV_SR_PAGE_SLUG,
		'gv_sr_render_admin_page'
	);
}

/* ==========================================================================
   ۷) ثبت / ویرایش / حذف گزارش (Admin Post handlers)
   ========================================================================== */
add_action( 'admin_post_gv_sr_save_report', 'gv_sr_handle_save_report' );
function gv_sr_handle_save_report() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$report_id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;

	$visibility = array();
	foreach ( array_keys( gv_sr_visibility_keys() ) as $key ) {
		$visibility[ $key ] = isset( $_POST[ $key ] ) ? 1 : 0;
	}

	$data = array(
		'client_name'    => isset( $_POST['client_name'] ) ? wp_unslash( $_POST['client_name'] ) : '',
		'user_id'        => isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0,
		'title'          => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
		'period_start'   => gv_sr_read_jalali_post( 'period_start' ),
		'period_end'     => gv_sr_read_jalali_post( 'period_end' ),
		'summary'        => isset( $_POST['summary'] ) ? wp_unslash( $_POST['summary'] ) : '',
		'next_steps'     => isset( $_POST['next_steps'] ) ? wp_unslash( $_POST['next_steps'] ) : '',
		'hours_spent'    => isset( $_POST['hours_spent'] ) ? $_POST['hours_spent'] : 0,
		'traffic_before' => isset( $_POST['traffic_before'] ) ? $_POST['traffic_before'] : 0,
		'traffic_after'  => isset( $_POST['traffic_after'] ) ? $_POST['traffic_after'] : 0,
		'overall_score'  => isset( $_POST['overall_score'] ) ? $_POST['overall_score'] : 0,
		'status'         => isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'draft',
		'visibility'     => $visibility,
	);

	$new_id = gv_sr_save_report( $data, $report_id );

	/* کلمات کلیدی */
	$kw_rows = array();
	if ( ! empty( $_POST['kw_keyword'] ) && is_array( $_POST['kw_keyword'] ) ) {
		foreach ( $_POST['kw_keyword'] as $i => $keyword ) {
			$kw_rows[] = array(
				'keyword'       => wp_unslash( $keyword ),
				'search_engine' => wp_unslash( $_POST['kw_engine'][ $i ] ?? 'گوگل' ),
				'page_url'      => wp_unslash( $_POST['kw_url'][ $i ] ?? '' ),
				'prev_rank'     => $_POST['kw_prev'][ $i ] ?? 0,
				'curr_rank'     => $_POST['kw_curr'][ $i ] ?? 0,
				'note'          => wp_unslash( $_POST['kw_note'][ $i ] ?? '' ),
			);
		}
	}
	gv_sr_save_keywords( $new_id, $kw_rows );

	/* فعالیت‌ها / محتوا */
	$task_rows = array();
	if ( ! empty( $_POST['task_title'] ) && is_array( $_POST['task_title'] ) ) {
		foreach ( $_POST['task_title'] as $i => $title ) {
			$task_rows[] = array(
				'task_type'      => wp_unslash( $_POST['task_type'][ $i ] ?? 'other' ),
				'title'          => wp_unslash( $title ),
				'url'            => wp_unslash( $_POST['task_url'][ $i ] ?? '' ),
				'target_keyword' => wp_unslash( $_POST['task_keyword'][ $i ] ?? '' ),
				'work_date'      => gv_sr_read_jalali_post_row( 'task_date', $i ),
				'hours'          => $_POST['task_hours'][ $i ] ?? 0,
				'note'           => wp_unslash( $_POST['task_note'][ $i ] ?? '' ),
			);
		}
	}
	gv_sr_save_tasks( $new_id, $task_rows );

	/* رشد صفحات */
	$growth_rows = array();
	if ( ! empty( $_POST['growth_metric'] ) && is_array( $_POST['growth_metric'] ) ) {
		foreach ( $_POST['growth_metric'] as $i => $metric ) {
			$growth_rows[] = array(
				'page_title'   => wp_unslash( $_POST['growth_title'][ $i ] ?? '' ),
				'page_url'     => wp_unslash( $_POST['growth_url'][ $i ] ?? '' ),
				'metric_label' => wp_unslash( $metric ),
				'before_value' => wp_unslash( $_POST['growth_before'][ $i ] ?? '' ),
				'after_value'  => wp_unslash( $_POST['growth_after'][ $i ] ?? '' ),
				'note'         => wp_unslash( $_POST['growth_note'][ $i ] ?? '' ),
			);
		}
	}
	gv_sr_save_growth( $new_id, $growth_rows );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=view&id=' . $new_id . '&saved=1' ) );
	exit;
}

add_action( 'admin_post_gv_sr_delete_report', 'gv_sr_handle_delete_report' );
function gv_sr_handle_delete_report() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( $id > 0 ) { gv_sr_delete_report( $id ); }
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&deleted=1' ) );
	exit;
}

add_action( 'admin_post_gv_sr_export_tasks_csv', 'gv_sr_handle_export_tasks_csv' );
function gv_sr_handle_export_tasks_csv() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$report_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$report    = gv_sr_get_report( $report_id );
	if ( ! $report ) { wp_die( 'گزارش پیدا نشد.' ); }
	$tasks = gv_sr_get_tasks( $report_id );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=gv-seo-report-' . $report_id . '-' . gmdate( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'نوع فعالیت', 'عنوان', 'کلمه هدف', 'لینک', 'تاریخ (شمسی)', 'ساعت صرف‌شده', 'توضیح' ) );
	foreach ( $tasks as $t ) {
		fputcsv( $output, array(
			gv_sr_task_type_label( $t->task_type ),
			$t->title,
			$t->target_keyword,
			$t->url,
			gv_sr_jalali_numeric( $t->work_date ),
			$t->hours,
			$t->note,
		) );
	}
	fclose( $output );
	exit;
}

/* ---------------- شناسایی کارمند (کوکی) ---------------- */
add_action( 'admin_post_gv_sr_set_employee', 'gv_sr_handle_set_employee' );
function gv_sr_handle_set_employee() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$emp_id = isset( $_POST['existing_employee_id'] ) ? (int) $_POST['existing_employee_id'] : 0;
	$new_name = isset( $_POST['new_employee_name'] ) ? trim( wp_unslash( $_POST['new_employee_name'] ) ) : '';
	$new_rate = isset( $_POST['new_employee_rate'] ) ? (int) $_POST['new_employee_rate'] : 0;
	$shared_code = isset( $_POST['shared_employee_code'] ) ? trim( wp_unslash( $_POST['shared_employee_code'] ) ) : '';

	if ( '' !== $new_name ) {
		$emp_id = gv_sr_save_employee( array( 'name' => $new_name, 'hourly_rate' => $new_rate, 'active' => 1, 'global_code' => $shared_code ) );
	}

	if ( $emp_id > 0 ) {
		setcookie( GV_SR_EMP_COOKIE, (string) $emp_id, time() + ( 180 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my' ) );
	exit;
}

/* ---------------- تایمر زنده کارکرد ---------------- */
add_action( 'admin_post_gv_sr_start_timer', 'gv_sr_handle_start_timer' );
function gv_sr_handle_start_timer() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$employee_id = gv_sr_current_employee_id();
	if ( $employee_id <= 0 ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my&err=noemp' ) );
		exit;
	}
	$client_name = isset( $_POST['timer_client_name'] ) ? wp_unslash( $_POST['timer_client_name'] ) : '';
	$note        = isset( $_POST['timer_note'] ) ? wp_unslash( $_POST['timer_note'] ) : '';
	gv_sr_start_timer( $employee_id, $client_name, $note );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my&timer_started=1' ) );
	exit;
}

add_action( 'admin_post_gv_sr_stop_timer', 'gv_sr_handle_stop_timer' );
function gv_sr_handle_stop_timer() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$employee_id = gv_sr_current_employee_id();
	if ( $employee_id <= 0 ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my' ) );
		exit;
	}
	$client_override = isset( $_POST['timer_client_name'] ) ? wp_unslash( $_POST['timer_client_name'] ) : null;
	$note_override   = isset( $_POST['timer_note'] ) ? wp_unslash( $_POST['timer_note'] ) : null;
	gv_sr_stop_timer( $employee_id, $client_override, $note_override );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my&timer_stopped=1' ) );
	exit;
}

/* ---------------- ثبت/ویرایش کارکرد روزانه (تایم‌شیت) ---------------- */
add_action( 'admin_post_gv_sr_save_timelog', 'gv_sr_handle_save_timelog' );
function gv_sr_handle_save_timelog() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$employee_id = gv_sr_current_employee_id();
	if ( $employee_id <= 0 ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my&err=noemp' ) );
		exit;
	}

	$timelog_id = isset( $_POST['timelog_id'] ) ? (int) $_POST['timelog_id'] : 0;
	// در حالت ویرایش، فقط صاحب همان کارکرد اجازه تغییر دارد.
	if ( $timelog_id > 0 ) {
		$existing = gv_sr_get_timelog( $timelog_id );
		if ( ! $existing || (int) $existing->employee_id !== $employee_id ) { $timelog_id = 0; }
	}

	$data = array(
		'employee_id' => $employee_id,
		'work_date'   => gv_sr_read_jalali_post( 'log_date' ),
		'entry_mode'  => isset( $_POST['entry_mode'] ) ? sanitize_key( $_POST['entry_mode'] ) : 'manual',
		'start_time'  => isset( $_POST['start_time'] ) ? sanitize_text_field( $_POST['start_time'] ) : '',
		'end_time'    => isset( $_POST['end_time'] ) ? sanitize_text_field( $_POST['end_time'] ) : '',
		'manual_h'    => isset( $_POST['manual_h'] ) ? $_POST['manual_h'] : 0,
		'manual_m'    => isset( $_POST['manual_m'] ) ? $_POST['manual_m'] : 0,
		'client_name' => isset( $_POST['client_name'] ) ? wp_unslash( $_POST['client_name'] ) : '',
		'project_id'  => isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0,
		'note'        => isset( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : '',
	);

	gv_sr_save_timelog( $data, $timelog_id );
	/**
	 * فراخوانی بعد از ثبت/ویرایش کارکرد — ماژول همگام‌سازی چندسایتی (در صورت فعال بودن) از این‌جا استفاده می‌کند.
	 */
	do_action( 'gv_sr_after_save_timelog', $employee_id );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my&saved_log=1' ) );
	exit;
}

add_action( 'admin_post_gv_sr_delete_timelog', 'gv_sr_handle_delete_timelog' );
function gv_sr_handle_delete_timelog() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$id          = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$employee_id = gv_sr_current_employee_id();
	$log         = $id > 0 ? gv_sr_get_timelog( $id ) : null;

	// کارمند فقط می‌تواند ردیف‌های خودش را حذف کند؛ مدیرِ احرازشده می‌تواند هر ردیفی را حذف کند.
	if ( $log && ( (int) $log->employee_id === $employee_id || gv_sr_team_is_authed() ) ) {
		$affected_employee_id = (int) $log->employee_id;
		gv_sr_delete_timelog( $id );
		do_action( 'gv_sr_after_save_timelog', $affected_employee_id );
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my' ) );
	exit;
}

/* ---------------- ورود/خروج و مدیریت کارمندان در تب «مدیریت تیم» ---------------- */
add_action( 'admin_post_gv_sr_team_login', 'gv_sr_handle_team_login' );
function gv_sr_handle_team_login() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_TEAM_NONCE );
	$pass = isset( $_POST['team_password'] ) ? (string) $_POST['team_password'] : '';
	$ok   = gv_sr_team_login( $pass );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team' . ( $ok ? '' : '&team_err=1' ) ) );
	exit;
}
add_action( 'admin_post_gv_sr_team_logout', 'gv_sr_handle_team_logout' );
function gv_sr_handle_team_logout() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_TEAM_NONCE );
	gv_sr_team_logout();
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team' ) );
	exit;
}
add_action( 'admin_post_gv_sr_save_employee', 'gv_sr_handle_save_employee' );
function gv_sr_handle_save_employee() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_TEAM_NONCE );

	$employee_id = isset( $_POST['employee_id'] ) ? (int) $_POST['employee_id'] : 0;
	gv_sr_save_employee( array(
		'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
		'hourly_rate' => isset( $_POST['hourly_rate'] ) ? $_POST['hourly_rate'] : 0,
		'active'      => isset( $_POST['active'] ) ? 1 : 0,
	), $employee_id );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&saved_emp=1' ) );
	exit;
}
add_action( 'admin_post_gv_sr_team_change_pass', 'gv_sr_handle_team_change_pass' );
function gv_sr_handle_team_change_pass() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_TEAM_NONCE );
	$new_pass = isset( $_POST['new_password'] ) ? (string) $_POST['new_password'] : '';
	if ( strlen( $new_pass ) >= 6 ) {
		update_option( 'gv_sr_manager_pass_hash', password_hash( $new_pass, PASSWORD_DEFAULT ) );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&pass_changed=1' ) );
	exit;
}

/** برچسب خوانا برای روش ثبت ساعت کارکرد */
function gv_sr_entry_mode_label( $mode ) {
	$labels = array( 'range' => 'ساعت به ساعت', 'manual' => 'دستی', 'timer' => 'تایمر زنده' );
	return isset( $labels[ $mode ] ) ? $labels[ $mode ] : $mode;
}

/* ---------------- خروجی اکسل (CSV) تایم‌شیت ---------------- */
function gv_sr_output_timesheet_csv( $filename, $logs, $with_employee_col = false, $employee_map = array() ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );

	$header = array( 'ردیف' );
	if ( $with_employee_col ) { $header[] = 'نام کارمند'; }
	$header = array_merge( $header, array( 'تاریخ شمسی', 'روز هفته', 'حالت ثبت', 'ساعت شروع', 'ساعت پایان', 'ساعت کارکرد', 'مربوط به مشتری/کار', 'توضیح' ) );
	fputcsv( $output, $header );

	$row_no = 1;
	$total  = 0.0;
	foreach ( $logs as $l ) {
		$row = array( $row_no++ );
		if ( $with_employee_col ) {
			$row[] = isset( $employee_map[ $l->employee_id ] ) ? $employee_map[ $l->employee_id ] : '—';
		}
		$row = array_merge( $row, array(
			gv_sr_jalali_numeric( $l->work_date ),
			gv_sr_weekday_name( $l->work_date ),
			'range' === $l->entry_mode ? 'ساعت شروع/پایان' : gv_sr_entry_mode_label( $l->entry_mode ),
			$l->start_time ? $l->start_time : '—',
			$l->end_time ? $l->end_time : '—',
			$l->hours,
			$l->client_name,
			$l->note,
		) );
		fputcsv( $output, $row );
		$total += (float) $l->hours;
	}

	fputcsv( $output, array() );
	$sum_row = array_fill( 0, $with_employee_col ? 6 : 5, '' );
	$sum_row[] = 'جمع کل ساعت کارکرد:';
	$sum_row[] = $total;
	fputcsv( $output, $sum_row );

	fclose( $output );
	exit;
}

add_action( 'admin_post_gv_sr_export_my_timesheet', 'gv_sr_handle_export_my_timesheet' );
function gv_sr_handle_export_my_timesheet() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$employee_id = gv_sr_current_employee_id();
	if ( $employee_id <= 0 ) { wp_die( 'ابتدا باید کارمند خود را مشخص کنید.' ); }

	$from = isset( $_GET['from'] ) ? sanitize_text_field( $_GET['from'] ) : '';
	$to   = isset( $_GET['to'] ) ? sanitize_text_field( $_GET['to'] ) : '';
	$logs = gv_sr_get_timelogs( array( 'employee_id' => $employee_id, 'date_from' => $from, 'date_to' => $to ) );
	$emp  = gv_sr_get_employee( $employee_id );

	gv_sr_output_timesheet_csv( 'timesheet-' . sanitize_title( $emp ? $emp->name : $employee_id ) . '-' . gmdate( 'Y-m-d' ) . '.csv', $logs );
}

add_action( 'admin_post_gv_sr_export_team_timesheet', 'gv_sr_handle_export_team_timesheet' );
function gv_sr_handle_export_team_timesheet() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_NONCE );

	$from        = isset( $_GET['from'] ) ? sanitize_text_field( $_GET['from'] ) : '';
	$to          = isset( $_GET['to'] ) ? sanitize_text_field( $_GET['to'] ) : '';
	$employee_id = isset( $_GET['employee_id'] ) ? (int) $_GET['employee_id'] : 0;

	$logs = gv_sr_get_timelogs( array( 'employee_id' => $employee_id, 'date_from' => $from, 'date_to' => $to ) );

	$employee_map = array();
	foreach ( gv_sr_get_employees() as $e ) { $employee_map[ $e->id ] = $e->name; }

	$filename = $employee_id > 0
		? 'timesheet-' . sanitize_title( $employee_map[ $employee_id ] ?? $employee_id ) . '-' . gmdate( 'Y-m-d' ) . '.csv'
		: 'timesheet-all-team-' . gmdate( 'Y-m-d' ) . '.csv';

	gv_sr_output_timesheet_csv( $filename, $logs, true, $employee_map );
}

/* ==========================================================================
   ۸) صفحه مدیریت — روتر تب‌ها
   ========================================================================== */
/* ==========================================================================
   ۷.۵) نوار سراسری بالای صفحه — شناسایی کارمند + تایمر زنده (در همه‌ی تب‌ها)
   ========================================================================== */
function gv_sr_render_top_bar() {
	$emp = gv_sr_current_employee();

	if ( ! $emp ) {
		$employees = gv_sr_get_employees( true );
		?>
		<div class="gvsr-topbar">
			<div class="gvsr-topbar-row">
				<span class="gvsr-topbar-idle">👋 برای ثبت کارکرد و استفاده از تایمر، اول مشخص کنید چه کسی هستید:</span>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-topbar-form">
					<?php wp_nonce_field( GV_SR_NONCE ); ?>
					<input type="hidden" name="action" value="gv_sr_set_employee">
					<select name="existing_employee_id" class="gvsr-select" style="min-width:150px;">
						<option value="0">— انتخاب از لیست —</option>
						<?php foreach ( $employees as $e ) : ?>
							<option value="<?php echo esc_attr( $e->id ); ?>"><?php echo esc_html( $e->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<span style="color:var(--gv-muted);font-size:11.5px;">یا</span>
					<input type="text" name="new_employee_name" placeholder="نام کارمند جدید...">
					<button type="submit" class="gvsr-topbar-btn-start">تایید</button>
				</form>
			</div>
		</div>
		<?php
		return;
	}

	$active_timer = gv_sr_get_active_timer( $emp->id );
	$employees    = gv_sr_get_employees( true );
	?>
	<div class="gvsr-topbar">
		<div class="gvsr-topbar-row">
			<span class="gvsr-topbar-name<?php echo $active_timer ? ' running' : ''; ?>"><span class="dot"></span><?php echo esc_html( $emp->name ); ?></span>

			<?php if ( $active_timer ) : ?>
				<span class="gvsr-topbar-clock" id="gvsr-timer-display" data-started="<?php echo esc_attr( str_replace( ' ', 'T', $active_timer->started_at ) ); ?>">۰۰:۰۰:۰۰</span>
				<?php if ( $active_timer->client_name ) : ?>
					<span class="gvsr-hint-inline">روی: <b style="color:var(--gv-ink);"><?php echo esc_html( $active_timer->client_name ); ?></b></span>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="gvsr-stop-timer-form" style="margin-inline-start:auto;">
					<?php wp_nonce_field( GV_SR_NONCE ); ?>
					<input type="hidden" name="action" value="gv_sr_stop_timer">
					<button type="submit" class="gvsr-topbar-btn-stop" onclick="return confirm('تایمر متوقف شود و کارکرد این مدت ثبت شود؟');">⏹ توقف تایمر</button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-topbar-form" style="margin-inline-start:auto;">
					<?php wp_nonce_field( GV_SR_NONCE ); ?>
					<input type="hidden" name="action" value="gv_sr_start_timer">
					<input type="text" name="timer_client_name" list="gvsr-client-datalist" placeholder="روی چه کاری کار می‌کنید؟ (اختیاری)">
					<button type="submit" class="gvsr-topbar-btn-start">▶ شروع تایمر</button>
				</form>
			<?php endif; ?>

			<details class="gvsr-topbar-switch">
				<summary>تغییر کارمند</summary>
				<div class="gvsr-topbar-switch-panel">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( GV_SR_NONCE ); ?>
						<input type="hidden" name="action" value="gv_sr_set_employee">
						<label style="display:block;font-size:11.8px;font-weight:700;color:var(--gv-ink-soft);margin-bottom:8px;">من یکی از این افرادم:
							<select name="existing_employee_id" class="gvsr-select" style="width:100%;margin-top:6px;">
								<option value="0">— انتخاب کنید —</option>
								<?php foreach ( $employees as $e ) : ?>
									<option value="<?php echo esc_attr( $e->id ); ?>" <?php selected( (int) $emp->id === (int) $e->id ); ?>><?php echo esc_html( $e->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label style="display:block;font-size:11.8px;font-weight:700;color:var(--gv-ink-soft);margin-bottom:10px;">یا کارمند جدید:
							<input type="text" name="new_employee_name" placeholder="نام..." style="width:100%;margin-top:6px;padding:8px 10px;border:1px solid var(--gv-border);border-radius:8px;">
						</label>
						<button type="submit" class="gvsr-topbar-btn-start" style="width:100%;justify-content:center;">تایید</button>
					</form>
				</div>
			</details>
		</div>
	</div>

	<!-- مودال هشدار خروج، فقط وقتی تایمر فعال است و کاربر داخل همین پیشخوان روی لینکی کلیک می‌کند -->
	<div id="gvsr-timer-leave-modal" class="gvsr-timer-modal" style="display:none;">
		<div class="gvsr-timer-modal-box">
			<p>⏱️ تایمر کارکرد شما هنوز در حال اجراست. قبل از رفتن چه کار کنیم؟</p>
			<div class="gvsr-timer-modal-actions">
				<button type="button" class="gvsr-btn-export" id="gvsr-timer-stop-and-go">⏹ متوقف کن و برو</button>
				<button type="button" class="gvsr-btn-ghost" id="gvsr-timer-continue-and-go">▶️ ادامه بده و فقط برو</button>
				<button type="button" class="gvsr-btn-ghost" id="gvsr-timer-cancel-nav">انصراف (همین‌جا بمانم)</button>
			</div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var display = document.getElementById('gvsr-timer-display');
		var timerActive = !!display;

		if (display) {
			var startedAt = new Date(display.getAttribute('data-started'));
			function faDigits(str) {
				var map = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
				return String(str).replace(/[0-9]/g, function (d) { return map[d]; });
			}
			function pad(n) { return (n < 10 ? '0' : '') + n; }
			function tick() {
				var diffSec = Math.max(0, Math.floor((Date.now() - startedAt.getTime()) / 1000));
				var h = Math.floor(diffSec / 3600);
				var m = Math.floor((diffSec % 3600) / 60);
				var s = diffSec % 60;
				display.textContent = faDigits(pad(h) + ':' + pad(m) + ':' + pad(s));
			}
			tick();
			setInterval(tick, 1000);
		}

		window.addEventListener('beforeunload', function (e) {
			if (!timerActive) { return; }
			e.preventDefault();
			e.returnValue = 'تایمر کارکرد شما در حال اجراست.';
		});

		var modal = document.getElementById('gvsr-timer-leave-modal');
		var pendingUrl = null;

		if (timerActive && modal) {
			document.addEventListener('click', function (e) {
				var link = e.target.closest('a[href]');
				if (!link) { return; }
				if (link.target === '_blank' || link.href.indexOf('javascript:') === 0) { return; }
				e.preventDefault();
				pendingUrl = link.href;
				modal.style.display = 'flex';
			});

			var cancelBtn = document.getElementById('gvsr-timer-cancel-nav');
			var continueBtn = document.getElementById('gvsr-timer-continue-and-go');
			var stopBtn = document.getElementById('gvsr-timer-stop-and-go');

			if ( cancelBtn ) { cancelBtn.addEventListener('click', function () { modal.style.display = 'none'; pendingUrl = null; } ); }
			if ( continueBtn ) { continueBtn.addEventListener('click', function () {
				var url = pendingUrl;
				modal.style.display = 'none';
				if (url) { window.location.href = url; }
			} ); }
			if ( stopBtn ) { stopBtn.addEventListener('click', function () {
				var url = pendingUrl;
				modal.style.display = 'none';
				timerActive = false;
				var form = document.getElementById('gvsr-stop-timer-form');
				var body = new URLSearchParams(new FormData(form));
				fetch(form.action, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function () { if (url) { window.location.href = url; } })
					.catch(function () { if (url) { window.location.href = url; } });
			} ); }
		}
	});
	</script>
	<?php
}

function gv_sr_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'list';
	$client_tabs = array( 'list', 'edit', 'view' );

	echo '<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif;">';
	gv_sr_admin_styles();

	gv_sr_render_top_bar();

	echo '<div class="gvsr-header">';
	echo '<div><h1>📑 گزارش عملکرد سئو — Groot Vision</h1><span>گزارش دوره‌ای مشتری + مدیریت کارکرد و حقوق تیم سئو</span></div>';
	if ( in_array( $tab, $client_tabs, true ) && 'list' !== $tab ) {
		echo '<a class="gvsr-btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG ) ) . '">← بازگشت به لیست گزارش‌ها</a>';
	} elseif ( 'list' === $tab ) {
		echo '<a class="gvsr-btn-export" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=edit' ) ) . '">➕ ثبت گزارش جدید</a>';
	}
	echo '</div>';

	$maintab = in_array( $tab, array( 'my', 'team', 'projects' ), true ) ? $tab : 'list';
	echo '<div class="gvsr-maintabs">';
	echo '<a class="gvsr-maintab' . ( 'list' === $maintab ? ' is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG ) ) . '">📋 گزارش‌های مشتری</a>';
	echo '<a class="gvsr-maintab' . ( 'my' === $maintab ? ' is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my' ) ) . '">🕒 کارکرد من</a>';
	echo '<a class="gvsr-maintab' . ( 'team' === $maintab ? ' is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team' ) ) . '">👥 مدیریت تیم (ویژه مدیر)</a>';
	echo '<a class="gvsr-maintab' . ( 'projects' === $maintab ? ' is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects' ) ) . '">🗂️ پروژه‌ها</a>';
	echo '</div>';

	if ( isset( $_GET['saved'] ) ) { echo '<div class="gvsr-notice">گزارش با موفقیت ذخیره شد.</div>'; }
	if ( isset( $_GET['deleted'] ) ) { echo '<div class="gvsr-notice">گزارش حذف شد.</div>'; }
	if ( isset( $_GET['saved_log'] ) ) { echo '<div class="gvsr-notice">کارکرد با موفقیت ثبت شد.</div>'; }
	if ( isset( $_GET['saved_emp'] ) ) { echo '<div class="gvsr-notice">اطلاعات کارمند ذخیره شد.</div>'; }
	if ( isset( $_GET['pass_changed'] ) ) { echo '<div class="gvsr-notice">رمز عبور بخش مدیریت تیم تغییر کرد.</div>'; }
	if ( isset( $_GET['err'] ) && 'noemp' === $_GET['err'] ) { echo '<div class="gvsr-notice" style="background:#fee2e2;color:#b91c1c;border-color:#fca5a5;">ابتدا باید مشخص کنید چه کسی هستید.</div>'; }

	if ( 'my' === $tab ) {
		gv_sr_render_my_tab();
		} elseif ( 'projects' === $tab ) {
		gv_sr_render_projects_tab();
	} elseif ( 'team' === $tab ) {
		gv_sr_render_team_tab();
	} elseif ( 'edit' === $tab ) {
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$report = $id > 0 ? gv_sr_get_report( $id ) : null;
		gv_sr_render_admin_form( $report );
	} elseif ( 'view' === $tab ) {
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$report = gv_sr_get_report( $id );
		if ( $report ) {
			echo '<div class="gvsr-preview-tools">';
			echo '<a class="gvsr-btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=edit&id=' . $id ) ) . '">✏️ ویرایش این گزارش</a>';
			echo '<a class="gvsr-btn-ghost" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_export_tasks_csv&id=' . $id ), GV_SR_NONCE ) ) . '">📥 خروجی CSV فعالیت‌ها</a>';
			echo '<span class="gvsr-hint-inline">این پیش‌نمایش دقیقاً همان چیزی است که مشتری می‌بیند؛ بخش‌های مخفی با برچسب قرمز مشخص شده‌اند.</span>';
			echo '</div>';
			gv_sr_render_report_detail( $report, true );
		} else {
			echo '<div class="gvsr-empty">گزارش مورد نظر پیدا نشد.</div>';
		}
	} else {
		gv_sr_render_admin_list();
	}

	echo '<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong> | اینستاگرام: grootvision</p>';
	echo '</div>';
}

function gv_sr_render_admin_list() {
	$status  = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
	$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$reports = gv_sr_get_reports( array( 'status' => $status, 'search' => $search ) );

	$total_reports = count( $reports );
	$total_hours   = array_sum( wp_list_pluck( $reports, 'hours_spent' ) );
	$published     = count( array_filter( $reports, function ( $r ) { return 'published' === $r->status; } ) );
	$clients       = count( array_unique( wp_list_pluck( $reports, 'client_name' ) ) );
	?>
	<div class="gvsr-stat-cards">
		<div class="gvsr-stat"><b><?php echo esc_html( number_format_i18n( $total_reports ) ); ?></b><span>تعداد کل گزارش‌ها</span></div>
		<div class="gvsr-stat"><b><?php echo esc_html( number_format_i18n( $published ) ); ?></b><span>گزارش‌های منتشرشده</span></div>
		<div class="gvsr-stat"><b><?php echo esc_html( number_format_i18n( $clients ) ); ?></b><span>تعداد مشتریان</span></div>
		<div class="gvsr-stat"><b><?php echo esc_html( number_format_i18n( $total_hours, 1 ) ); ?></b><span>مجموع ساعت کار ثبت‌شده</span></div>
	</div>

	<form method="get" class="gvsr-filter-bar">
		<input type="hidden" name="page" value="<?php echo esc_attr( GV_SR_PAGE_SLUG ); ?>">
		<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جست‌وجوی مشتری یا عنوان گزارش...">
		<select name="status">
			<option value="">همه وضعیت‌ها</option>
			<option value="published" <?php selected( $status, 'published' ); ?>>منتشرشده (قابل مشاهده مشتری)</option>
			<option value="draft" <?php selected( $status, 'draft' ); ?>>پیش‌نویس (فقط داخلی)</option>
		</select>
		<button type="submit" class="gvsr-btn-ghost">فیلتر</button>
	</form>

	<div class="gvsr-table-wrap">
		<?php if ( empty( $reports ) ) : ?>
			<div class="gvsr-empty">هنوز هیچ گزارشی ثبت نشده. با دکمه «ثبت گزارش جدید» بالای صفحه شروع کنید.</div>
		<?php else : ?>
			<table class="gvsr-table gvsr-sortable">
				<thead>
					<tr>
						<th data-sort-type="text">مشتری</th>
						<th data-sort-type="text">عنوان گزارش</th>
						<th data-sort-type="date">بازه گزارش</th>
						<th data-sort-type="text">وضعیت</th>
						<th data-sort-type="number">ساعت کار</th>
						<th class="no-sort">مرتبط با کاربر سایت</th>
						<th class="no-sort">عملیات</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $reports as $r ) : ?>
					<tr>
						<td><b><?php echo esc_html( $r->client_name ); ?></b></td>
						<td><?php echo esc_html( $r->title ); ?></td>
						<td data-sort-value="<?php echo esc_attr( strtotime( $r->period_end ) ); ?>">
							<?php echo esc_html( gv_sr_jalali_numeric( $r->period_start ) . ' تا ' . gv_sr_jalali_numeric( $r->period_end ) ); ?>
						</td>
						<td>
							<?php if ( 'published' === $r->status ) : ?>
								<span class="gvsr-badge gvsr-badge-green">منتشرشده</span>
							<?php else : ?>
								<span class="gvsr-badge gvsr-badge-gray">پیش‌نویس</span>
							<?php endif; ?>
						</td>
						<td data-sort-value="<?php echo esc_attr( $r->hours_spent ); ?>"><?php echo esc_html( gv_sr_fa_digits( $r->hours_spent ) ); ?></td>
						<td>
							<?php if ( $r->user_id > 0 ) : $u = get_user_by( 'id', $r->user_id ); ?>
								<?php echo $u ? esc_html( $u->display_name ) : '—'; ?>
							<?php else : ?>
								<span class="gvsr-cs-none">بدون اتصال</span>
							<?php endif; ?>
						</td>
						<td class="gvsr-row-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=view&id=' . $r->id ) ); ?>">مشاهده</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=edit&id=' . $r->id ) ); ?>">ویرایش</a>
							<a class="gvsr-danger" onclick="return confirm('این گزارش برای همیشه حذف می‌شود. ادامه می‌دهید؟');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_delete_report&id=' . $r->id ), GV_SR_NONCE ) ); ?>">حذف</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
	gv_sr_admin_sort_script();
}

/* ==========================================================================
   ۸.۱) تب «کارکرد من» — مختص خود کارمند (بر اساس شناسایی کوکی)
   ========================================================================== */
/**
 * ==========================================================
 *  بازطراحی تب «کارکرد من» — Groot Vision SEO Reports
 *  ------------------------------------------------------------
 *  این فایل فقط شامل بخش‌هایی است که باید در فایل اصلی افزونه
 *  جایگزین شوند. نحوه‌ی استفاده در انتهای همین فایل توضیح داده شده.
 * ==========================================================
 */

/* ==========================================================================
   ۱) جایگزین تابع gv_sr_render_my_tab() در فایل اصلی
   ------------------------------------------------------------
   تغییرات کلیدی نسبت به نسخه قبلی:
   - فرم «شناسایی کارمند» و کارت «تایمر زنده» و مودال خروج، از این تب
     حذف شدند؛ چون نوار بالای صفحه (gv_sr_render_top_bar) که در همه‌ی
     تب‌ها از جمله همین تب نمایش داده می‌شود، دقیقاً همین‌ کارها را
     انجام می‌داد. نتیجه: همان id ها (مثل gvsr-timer-display و
     gvsr-timer-leave-modal) دو بار در صفحه تکرار می‌شدند که هم باعث
     بهم‌ریختگی بصری می‌شد و هم اسکریپت را روی id تکراری اجرا می‌کرد.
   - محتوای باقی‌مانده (ثبت کارکرد، فیلتر بازه، آمار، جدول) در یک
     چیدمان دو‌ستونه چسبان (sidebar + main) قرار گرفته تا به‌جای
     اسکرول طولانی، فرم ثبت کارکرد همیشه کنار دست بماند و جدول/آمار
     در ستون اصلی و عریض‌تر دیده شود.
   ========================================================================== */
function gv_sr_render_my_tab() {
	$emp = gv_sr_current_employee();

	if ( ! $emp ) {
		echo '<div class="gvsr-empty">برای استفاده از این بخش، ابتدا از نوار بالای صفحه خودتان را به‌عنوان کارمند مشخص کنید.</div>';
		return;
	}

	list( $default_from, $default_to ) = gv_sr_current_jalali_month_bounds();
	$from = isset( $_GET['from_jy'] ) ? gv_sr_read_jalali_get( 'from', $default_from ) : $default_from;
	$to   = isset( $_GET['to_jy'] ) ? gv_sr_read_jalali_get( 'to', $default_to ) : $default_to;

	/* در حال ویرایش یک ردیف کارکرد؟ */
	$editing = null;
	if ( isset( $_GET['edit_log'] ) ) {
		$maybe = gv_sr_get_timelog( (int) $_GET['edit_log'] );
		if ( $maybe && (int) $maybe->employee_id === (int) $emp->id ) { $editing = $maybe; }
	}

	$logs        = gv_sr_get_timelogs( array( 'employee_id' => $emp->id, 'date_from' => $from, 'date_to' => $to ) );
	$total_hours = gv_sr_employee_total_hours( $emp->id, $from, $to );
	$rate        = (int) $emp->hourly_rate;
	$total_pay   = $rate > 0 ? round( $total_hours * $rate ) : 0;

	$export_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=gv_sr_export_my_timesheet&from=' . rawurlencode( $from ) . '&to=' . rawurlencode( $to ) ),
		GV_SR_NONCE
	);

	if ( isset( $_GET['timer_started'] ) ) { echo '<div class="gvsr-notice">تایمر شروع شد.</div>'; }
	if ( isset( $_GET['timer_stopped'] ) ) { echo '<div class="gvsr-notice">تایمر متوقف شد و کارکرد آن ثبت گردید.</div>'; }
	if ( isset( $_GET['saved_log'] ) ) { echo '<div class="gvsr-notice">کارکرد با موفقیت ثبت شد.</div>'; }

	echo '<div class="gvsr-my-layout">';

	/* ==================== ستون کناری: هویت + فرم ثبت کارکرد ==================== */
	echo '<div class="gvsr-my-sidebar">';

	?>
	<div class="gvsr-report-card gvsr-my-idcard">
		<h3>👤 <?php echo esc_html( $emp->name ); ?></h3>
		<div class="gvsr-code-box">
			کد کارمندی مشترک: <b><?php echo esc_html( $emp->global_code ); ?></b>
			<span>اگر روی سایت‌های دیگری هم با همین افزونه کار می‌کنید، همین کد را آن‌جا هم وارد کنید تا کارکردتان یک‌جا جمع شود.</span>
		</div>
		<p class="gvsr-hint-inline">برای تغییر کارمند، یا شروع/توقف تایمر زنده، از نوار بالای همین صفحه استفاده کنید.</p>
	</div>

	<div class="gvsr-report-card">
		<h3><?php echo $editing ? '✏️ ویرایش کارکرد ثبت‌شده' : '➕ ثبت کارکرد جدید'; ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-timelog-form">
			<?php wp_nonce_field( GV_SR_NONCE ); ?>
			<input type="hidden" name="action" value="gv_sr_save_timelog">
			<input type="hidden" name="timelog_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">

			<label>تاریخ کارکرد (شمسی)
				<?php echo gv_sr_jalali_select_fields( 'log_date', $editing ? $editing->work_date : '' ); ?>
			</label>

			<label>مربوط به کدام مشتری/کار بوده؟ (اختیاری)
				<input type="text" name="client_name" list="gvsr-client-datalist" value="<?php echo esc_attr( $editing ? $editing->client_name : '' ); ?>" placeholder="مثلاً: فروشگاه نمونه">
			</label>
			<datalist id="gvsr-client-datalist">
				<?php foreach ( gv_sr_get_clients() as $c ) : ?>
					<option value="<?php echo esc_attr( $c->client_name ); ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<label>این کارکرد مربوط به کدام پروژه است؟ (اختیاری)
				<?php $my_projects = gv_sr_get_employee_projects( $emp->id ); ?>
				<select name="project_id" class="gvsr-select">
					<option value="0">— بدون پروژه —</option>
					<?php foreach ( $my_projects as $proj ) : ?>
						<option value="<?php echo esc_attr( $proj->id ); ?>" <?php selected( $editing ? (int) $editing->project_id : 0, (int) $proj->id ); ?>><?php echo esc_html( $proj->title . ' (' . $proj->client_name . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $my_projects ) ) : ?>
					<span class="gvsr-hint-inline">هنوز به‌عنوان عضو هیچ پروژه‌ای اضافه نشده‌اید؛ از تب «پروژه‌ها» یک مدیر باید شما را به پروژه اضافه کند.</span>
				<?php endif; ?>
			</label>

			<label class="gvsr-radio-row">روش ثبت ساعت کار:
				<span class="gvsr-radio-item"><input type="radio" name="entry_mode" value="range" class="gvsr-mode-radio" <?php checked( ! $editing || 'range' === $editing->entry_mode ); ?>> از ساعت تا ساعت</span>
				<span class="gvsr-radio-item"><input type="radio" name="entry_mode" value="manual" class="gvsr-mode-radio" <?php checked( $editing && 'manual' === $editing->entry_mode ); ?>> ثبت دستی مدت‌زمان</span>
			</label>

			<div class="gvsr-grid-2 gvsr-mode-range">
				<label>ساعت شروع
					<input type="time" name="start_time" value="<?php echo esc_attr( $editing ? $editing->start_time : '' ); ?>">
				</label>
				<label>ساعت پایان
					<input type="time" name="end_time" value="<?php echo esc_attr( $editing ? $editing->end_time : '' ); ?>">
				</label>
			</div>
			<div class="gvsr-grid-2 gvsr-mode-manual" style="display:none;">
				<label>ساعت
					<input type="number" min="0" max="23" name="manual_h" value="<?php echo esc_attr( $editing && 'manual' === $editing->entry_mode ? floor( $editing->hours ) : '' ); ?>" placeholder="مثلاً: 3">
				</label>
				<label>دقیقه
					<input type="number" min="0" max="59" name="manual_m" value="<?php echo esc_attr( $editing && 'manual' === $editing->entry_mode ? round( ( $editing->hours - floor( $editing->hours ) ) * 60 ) : '' ); ?>" placeholder="مثلاً: 45">
				</label>
			</div>

			<label>توضیح کوتاه کاری که انجام شد (اختیاری)
				<input type="text" name="note" value="<?php echo esc_attr( $editing ? $editing->note : '' ); ?>" placeholder="مثلاً: تولید ۲ محتوا برای فروشگاه نمونه">
			</label>

			<div class="gvsr-form-actions">
				<button type="submit" class="gvsr-btn-export"><?php echo $editing ? '💾 بروزرسانی کارکرد' : '💾 ثبت کارکرد'; ?></button>
				<?php if ( $editing ) : ?>
					<a class="gvsr-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my' ) ); ?>">انصراف</a>
				<?php endif; ?>
			</div>
		</form>
	</div>
	<?php

	echo '</div>'; /* پایان .gvsr-my-sidebar */

	/* ==================== ستون اصلی: فیلتر بازه + آمار + جدول ==================== */
	echo '<div class="gvsr-my-main">';
	?>
	<div class="gvsr-report-card gvsr-my-filter-card">
		<h3>📅 بازه گزارش‌گیری</h3>
		<form method="get" class="gvsr-filter-bar" style="align-items:flex-end;">
			<input type="hidden" name="page" value="<?php echo esc_attr( GV_SR_PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="my">
			<label style="margin:0;">از تاریخ<?php echo gv_sr_jalali_select_fields( 'from', $from ); ?></label>
			<label style="margin:0;">تا تاریخ<?php echo gv_sr_jalali_select_fields( 'to', $to ); ?></label>
			<button type="submit" class="gvsr-btn-ghost">اعمال بازه</button>
			<a class="gvsr-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my' ) ); ?>">بازه ماه جاری</a>
		</form>
	</div>

	<div class="gvsr-kpi-grid" style="grid-template-columns:repeat(3,1fr);">
		<div class="gvsr-kpi"><b><?php echo esc_html( gv_sr_fa_digits( $total_hours ) ); ?></b><span>جمع ساعت کارکرد در این بازه</span></div>
		<div class="gvsr-kpi"><b><?php echo $rate > 0 ? esc_html( gv_sr_fa_digits( number_format_i18n( $rate ) ) ) : '—'; ?></b><span>نرخ ساعتی (تومان) — توسط مدیر تنظیم می‌شود</span></div>
		<div class="gvsr-kpi"><b><?php echo $rate > 0 ? esc_html( gv_sr_fa_digits( number_format_i18n( $total_pay ) ) ) : '—'; ?></b><span>مبلغ قابل‌پرداخت این بازه (تومان)</span></div>
	</div>

	<div class="gvsr-report-card">
		<h3>🗒️ شیت کارکرد من
			<a class="gvsr-btn-ghost" style="margin-inline-start:auto;font-size:11.5px;" href="<?php echo esc_url( $export_url ); ?>">📥 خروجی اکسل (CSV)</a>
		</h3>
		<?php if ( empty( $logs ) ) : ?>
			<div class="gvsr-chart-empty">در این بازه هنوز کارکردی ثبت نکرده‌اید.</div>
		<?php else : ?>
			<div class="gvsr-table-wrap" style="max-width:100%;">
				<table class="gvsr-table">
					<thead><tr>
						<th>تاریخ</th><th>روز</th><th>روش ثبت</th><th>ساعت شروع</th><th>ساعت پایان</th><th>ساعت کارکرد</th><th>مشتری/کار</th><th>توضیح</th><th>عملیات</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $logs as $l ) : ?>
						<tr>
							<td><?php echo esc_html( gv_sr_jalali_numeric( $l->work_date ) ); ?></td>
							<td><?php echo esc_html( gv_sr_weekday_name( $l->work_date ) ); ?></td>
							<td><?php echo esc_html( gv_sr_entry_mode_label( $l->entry_mode ) ); ?></td>
							<td><?php echo esc_html( $l->start_time ? $l->start_time : '—' ); ?></td>
							<td><?php echo esc_html( $l->end_time ? $l->end_time : '—' ); ?></td>
							<td><b><?php echo esc_html( gv_sr_fa_digits( $l->hours ) ); ?></b></td>
							<td><?php echo esc_html( $l->client_name ?: '—' ); ?></td>
							<td><?php echo esc_html( $l->note ?: '—' ); ?></td>
							<td class="gvsr-row-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=my&edit_log=' . $l->id ) ); ?>">ویرایش</a>
								<a class="gvsr-danger" onclick="return confirm('این ردیف کارکرد حذف شود؟');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_delete_timelog&id=' . $l->id ), GV_SR_NONCE ) ); ?>">حذف</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
	echo '</div>'; /* پایان .gvsr-my-main */
	echo '</div>'; /* پایان .gvsr-my-layout */

	/* فقط سوییچ نمایش «از ساعت تا ساعت / دستی» لازم است؛ منطق تایمر و مودال خروج
	   دیگر این‌جا تکرار نمی‌شود چون در نوار بالای صفحه (که در همه تب‌ها نمایش
	   داده می‌شود) همیشه فعال است. */
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var radios = document.querySelectorAll('.gvsr-mode-radio');
		var rangeBox = document.querySelector('.gvsr-mode-range');
		var manualBox = document.querySelector('.gvsr-mode-manual');
		function sync() {
			var checked = document.querySelector('.gvsr-mode-radio:checked');
			var mode = checked ? checked.value : 'range';
			if (rangeBox) { rangeBox.style.display = (mode === 'range') ? '' : 'none'; }
			if (manualBox) { manualBox.style.display = (mode === 'manual') ? '' : 'none'; }
		}
		radios.forEach(function (r) { r.addEventListener('change', sync); });
		sync();
	});
	</script>
	<?php
}

/* ==========================================================================
   ۲) این چند خط را داخل تابع gv_sr_admin_styles() (در بخش استایل‌ها)
   اضافه کنید — مثلاً درست بعد از قانون ".gvsr-emp-identify-form{...}".
   چیدمان دو‌ستونه‌ی تب «کارکرد من» را می‌سازد.
   ========================================================================== */
/*
.gvsr-my-layout{display:grid;grid-template-columns:328px 1fr;gap:16px;align-items:start;max-width:1100px;}
@media(max-width:900px){.gvsr-my-layout{grid-template-columns:1fr;}}
.gvsr-my-sidebar{position:sticky;top:100px;display:flex;flex-direction:column;gap:16px;}
@media(max-width:900px){.gvsr-my-sidebar{position:static;}}
.gvsr-my-sidebar .gvsr-report-card{margin-bottom:0;}
.gvsr-my-idcard h3{margin-bottom:10px;}
.gvsr-my-main{min-width:0;}
.gvsr-my-filter-card .gvsr-filter-bar{margin-bottom:0;}
*/

/* ==========================================================================
   ۸.۲) تب «مدیریت تیم» — ویژه مدیر، با رمز عبور جداگانه
   ========================================================================== */
/**
 * ==========================================================
 *  بازطراحی تب «مدیریت تیم» — Groot Vision SEO Reports
 *  ------------------------------------------------------------
 *  منطق و کوئری‌ها دقیقاً همان قبلی‌اند؛ فقط چیدمان تغییر کرده:
 *  - «افزودن/ویرایش کارمند» و «تغییر رمز عبور» — که کارهای مدیریتیِ
 *    کم‌تکرار هستند — داخل یک آکاردئون (details/summary) جمع شدند
 *    و به‌طور پیش‌فرض بسته‌اند (مگر وقتی در حال ویرایش یک کارمند
 *    هستید یا هنوز هیچ کارمندی ثبت نشده، که خودکار باز می‌شوند).
 *  - بخش‌های «همیشه لازم» یعنی فیلتر بازه، آمار کلی، جدول حقوق و
 *    خلاصه سئو، بدون تغییر و همیشه باز باقی مانده‌اند تا مدیر با
 *    کمترین اسکرول به آن‌ها برسد.
 *  ========================================================================== */
function gv_sr_render_team_tab() {

	if ( ! gv_sr_team_is_authed() ) {
		?>
		<div class="gvsr-report-card" style="max-width:460px;margin:0 auto;text-align:center;">
			<h3 style="justify-content:center;">🔒 ورود به بخش مدیریت تیم</h3>
			<p class="gvsr-hint-inline">این بخش شامل کارکرد، ساعت کاری و حقوق تمام کارمندان است و فقط با رمز عبور مدیریتی قابل مشاهده است.</p>
			<?php if ( isset( $_GET['team_err'] ) ) : ?>
				<div class="gvsr-notice" style="background:#fee2e2;color:#b91c1c;border-color:#fca5a5;">رمز عبور اشتباه است.</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( GV_SR_TEAM_NONCE ); ?>
				<input type="hidden" name="action" value="gv_sr_team_login">
				<label>رمز عبور مدیریت تیم
					<input type="password" name="team_password" required>
				</label>
				<button type="submit" class="gvsr-btn-export" style="margin-top:10px;">ورود</button>
			</form>
		</div>
		<?php
		return;
	}

	echo '<div class="gvsr-preview-tools">';
	echo '<a class="gvsr-btn-ghost" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_team_logout' ), GV_SR_TEAM_NONCE ) ) . '">🚪 خروج از بخش مدیریت تیم</a>';
	echo '<span class="gvsr-hint-inline">این بخش فقط برای مدیر است؛ کارمندان به آن دسترسی ندارند.</span>';
	echo '</div>';

	/* ==================== ۱) آکاردئون: کارمندان و نرخ ساعتی (کم‌تکرار) ==================== */
	$editing_emp = isset( $_GET['edit_emp'] ) ? gv_sr_get_employee( (int) $_GET['edit_emp'] ) : null;
	$employees   = gv_sr_get_employees();
	$open_emp_section = $editing_emp || empty( $employees );
	?>
	<details class="gvsr-section-toggle" <?php echo $open_emp_section ? 'open' : ''; ?>>
		<summary>👥 کارمندان تیم سئو و نرخ ساعتی حقوق <span class="gvsr-toggle-count">(<?php echo esc_html( number_format_i18n( count( $employees ) ) ); ?> نفر)</span></summary>
		<div class="gvsr-section-toggle-body">
			<div class="gvsr-table-wrap" style="max-width:100%;margin-bottom:16px;">
				<?php if ( empty( $employees ) ) : ?>
					<div class="gvsr-empty">هنوز کارمندی ثبت نشده. کارمندان با ورود به تب «کارکرد من» و معرفی خودشان، این‌جا اضافه می‌شوند؛ یا از فرم زیر مستقیم اضافه کنید.</div>
				<?php else : ?>
					<table class="gvsr-table">
						<thead><tr><th>نام</th><th>نرخ ساعتی (تومان)</th><th>وضعیت</th><th>جمع ساعت (کل دوران)</th><th>عملیات</th></tr></thead>
						<tbody>
						<?php foreach ( $employees as $e ) : ?>
							<tr>
								<td><b><?php echo esc_html( $e->name ); ?></b></td>
								<td><?php echo $e->hourly_rate > 0 ? esc_html( gv_sr_fa_digits( number_format_i18n( $e->hourly_rate ) ) ) : '—'; ?></td>
								<td><?php echo (int) $e->active === 1 ? '<span class="gvsr-badge gvsr-badge-green">فعال</span>' : '<span class="gvsr-badge gvsr-badge-gray">غیرفعال</span>'; ?></td>
								<td><?php echo esc_html( gv_sr_fa_digits( gv_sr_employee_total_hours( $e->id ) ) ); ?></td>
								<td class="gvsr-row-actions"><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&edit_emp=' . $e->id ) ); ?>">ویرایش</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<h4 style="font-size:13px;color:#1e293b;margin:0 0 10px;"><?php echo $editing_emp ? '✏️ ویرایش کارمند: ' . esc_html( $editing_emp->name ) : '➕ افزودن کارمند جدید'; ?></h4>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-grid-4" style="align-items:end;">
				<?php wp_nonce_field( GV_SR_TEAM_NONCE ); ?>
				<input type="hidden" name="action" value="gv_sr_save_employee">
				<input type="hidden" name="employee_id" value="<?php echo esc_attr( $editing_emp ? $editing_emp->id : 0 ); ?>">
				<label>نام کارمند
					<input type="text" name="name" required value="<?php echo esc_attr( $editing_emp ? $editing_emp->name : '' ); ?>">
				</label>
				<label>نرخ ساعتی (تومان)
					<input type="number" min="0" step="1000" name="hourly_rate" value="<?php echo esc_attr( $editing_emp ? $editing_emp->hourly_rate : 0 ); ?>">
				</label>
				<label class="gvsr-vis-item" style="margin-top:22px;">
					<input type="checkbox" name="active" <?php checked( ! $editing_emp || (int) $editing_emp->active === 1 ); ?>> کارمند فعال است
				</label>
				<button type="submit" class="gvsr-btn-export">💾 ذخیره کارمند</button>
			</form>
		</div>
	</details>

	<?php
	/* ==================== ۲) بازه گزارش‌گیری (همیشه در دسترس) ==================== */
	list( $default_from, $default_to ) = gv_sr_current_jalali_month_bounds();
	$from        = isset( $_GET['from_jy'] ) ? gv_sr_read_jalali_get( 'from', $default_from ) : $default_from;
	$to          = isset( $_GET['to_jy'] ) ? gv_sr_read_jalali_get( 'to', $default_to ) : $default_to;
	$filter_emp  = isset( $_GET['employee_id'] ) ? (int) $_GET['employee_id'] : 0;
	?>
	<div class="gvsr-report-card gvsr-my-filter-card">
		<h3>📅 بازه گزارش‌گیری کارکرد و حقوق تیم</h3>
		<form method="get" class="gvsr-filter-bar" style="align-items:flex-end;">
			<input type="hidden" name="page" value="<?php echo esc_attr( GV_SR_PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="team">
			<label style="margin:0;">از تاریخ<?php echo gv_sr_jalali_select_fields( 'from', $from ); ?></label>
			<label style="margin:0;">تا تاریخ<?php echo gv_sr_jalali_select_fields( 'to', $to ); ?></label>
			<label style="margin:0;">کارمند
				<select name="employee_id" class="gvsr-select">
					<option value="0">همه کارمندان</option>
					<?php foreach ( $employees as $e ) : ?>
						<option value="<?php echo esc_attr( $e->id ); ?>" <?php selected( $filter_emp, (int) $e->id ); ?>><?php echo esc_html( $e->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit" class="gvsr-btn-ghost">اعمال</button>
			<a class="gvsr-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team' ) ); ?>">بازه ماه جاری</a>
		</form>
		<p class="gvsr-hint">پیش‌فرض، بازه از اول تا آخر ماه شمسی جاری است (مناسب برای محاسبه حقوق ماهانه).</p>
	</div>

	<?php
	/* ==================== ۳) خلاصه جامع: ساعت‌کاری تیم + محتوا/کلمات/رتبه‌ها ==================== */
	$grand_hours = 0.0;
	$grand_pay   = 0;
	$per_emp     = array();
	$emp_list    = $filter_emp > 0 ? array_filter( $employees, function ( $e ) use ( $filter_emp ) { return (int) $e->id === $filter_emp; } ) : $employees;

	foreach ( $emp_list as $e ) {
		$hours = gv_sr_employee_total_hours( $e->id, $from, $to );
		$pay   = $e->hourly_rate > 0 ? round( $hours * $e->hourly_rate ) : 0;
		$per_emp[] = array( 'emp' => $e, 'hours' => $hours, 'pay' => $pay );
		$grand_hours += $hours;
		$grand_pay   += $pay;
	}

	$kw_summary    = gv_sr_global_keyword_summary( $from, $to );
	$task_counts   = gv_sr_global_task_counts( $from, $to );
	$reports_range = gv_sr_get_reports( array( 'date_from' => $from, 'date_to' => $to, 'limit' => 0 ) );
	$content_total = $task_counts['content_new'] + $task_counts['content_update'];
	?>
	<div class="gvsr-kpi-grid">
		<div class="gvsr-kpi"><b><?php echo esc_html( gv_sr_fa_digits( $grand_hours ) ); ?></b><span>جمع ساعت کارکرد تیم در این بازه</span></div>
		<div class="gvsr-kpi"><b><?php echo esc_html( gv_sr_fa_digits( number_format_i18n( $grand_pay ) ) ); ?></b><span>مجموع حقوق قابل‌پرداخت (تومان)</span></div>
		<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( count( $reports_range ) ) ); ?></b><span>گزارش سئوی مشتریان در این بازه</span></div>
		<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( $content_total ) ); ?></b><span>محتوای تولید/بروزرسانی‌شده</span></div>
		<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( $kw_summary['up'] ) ); ?></b><span>کلمه کلیدی بهبودیافته</span></div>
	</div>

	<div class="gvsr-report-card">
		<h3>💰 جدول حقوق و ساعت کارکرد به تفکیک کارمند</h3>
		<?php if ( empty( $per_emp ) ) : ?>
			<div class="gvsr-chart-empty">کارمندی برای نمایش وجود ندارد.</div>
		<?php else : ?>
			<div class="gvsr-table-wrap" style="max-width:100%;">
				<table class="gvsr-table">
					<thead><tr><th>کارمند</th><th>نرخ ساعتی</th><th>جمع ساعت این بازه</th><th>مبلغ قابل‌پرداخت</th><th>عملیات</th></tr></thead>
					<tbody>
					<?php foreach ( $per_emp as $row ) :
						$e = $row['emp'];
						$exp_url = wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_export_team_timesheet&employee_id=' . $e->id . '&from=' . rawurlencode( $from ) . '&to=' . rawurlencode( $to ) ), GV_SR_NONCE );
						?>
						<tr>
							<td><b><?php echo esc_html( $e->name ); ?></b></td>
							<td><?php echo $e->hourly_rate > 0 ? esc_html( gv_sr_fa_digits( number_format_i18n( $e->hourly_rate ) ) ) : '—'; ?></td>
							<td><?php echo esc_html( gv_sr_fa_digits( $row['hours'] ) ); ?></td>
							<td><b style="color:#059669;"><?php echo $row['pay'] > 0 ? esc_html( gv_sr_fa_digits( number_format_i18n( $row['pay'] ) ) ) : '—'; ?></b></td>
							<td><a href="<?php echo esc_url( $exp_url ); ?>" class="gvsr-btn-ghost" style="font-size:11px;">📥 خروجی CSV</a></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<td><b>جمع کل</b></td>
						<td>—</td>
						<td><b><?php echo esc_html( gv_sr_fa_digits( $grand_hours ) ); ?></b></td>
						<td><b><?php echo esc_html( gv_sr_fa_digits( number_format_i18n( $grand_pay ) ) ); ?></b></td>
						<td>
							<?php $exp_all = wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_export_team_timesheet&employee_id=0&from=' . rawurlencode( $from ) . '&to=' . rawurlencode( $to ) ), GV_SR_NONCE ); ?>
							<a href="<?php echo esc_url( $exp_all ); ?>" class="gvsr-btn-ghost" style="font-size:11px;">📥 خروجی همه (CSV)</a>
						</td>
					</tr>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="gvsr-report-card">
		<h3>📈 خلاصه جامع سئو در این بازه (کل مشتریان)</h3>
		<div class="gvsr-period-grid" style="grid-template-columns:repeat(5,1fr);">
			<div class="gvsr-period-item"><b><?php echo esc_html( number_format_i18n( $task_counts['content_new'] ) ); ?></b><span>محتوای جدید</span></div>
			<div class="gvsr-period-item"><b><?php echo esc_html( number_format_i18n( $task_counts['content_update'] ) ); ?></b><span>بروزرسانی محتوا</span></div>
			<div class="gvsr-period-item"><b><?php echo esc_html( number_format_i18n( $kw_summary['up'] ) ); ?></b><span>رتبه بهبودیافته</span></div>
			<div class="gvsr-period-item"><b><?php echo esc_html( number_format_i18n( $kw_summary['down'] ) ); ?></b><span>رتبه افت‌کرده</span></div>
			<div class="gvsr-period-item"><b><?php echo esc_html( number_format_i18n( $kw_summary['new'] ) ); ?></b><span>کلمه تازه‌وارد</span></div>
		</div>
	</div>

	<div class="gvsr-report-card">
		<h3>🗒️ ریز کارکرد روزانه هر کارمند در این بازه</h3>
		<?php foreach ( $per_emp as $row ) :
			$e    = $row['emp'];
			$logs = gv_sr_get_timelogs( array( 'employee_id' => $e->id, 'date_from' => $from, 'date_to' => $to ) );
			?>
			<details class="gvsr-emp-accordion">
				<summary><?php echo esc_html( $e->name ); ?> — <?php echo esc_html( gv_sr_fa_digits( $row['hours'] ) ); ?> ساعت / <?php echo esc_html( number_format_i18n( count( $logs ) ) ); ?> ردیف کارکرد</summary>
				<?php if ( empty( $logs ) ) : ?>
					<div class="gvsr-chart-empty">کارکردی در این بازه ثبت نشده.</div>
				<?php else : ?>
					<div class="gvsr-table-wrap" style="max-width:100%;">
						<table class="gvsr-table">
							<thead><tr><th>تاریخ</th><th>روز</th><th>روش ثبت</th><th>شروع</th><th>پایان</th><th>ساعت</th><th>مشتری/کار</th><th>توضیح</th></tr></thead>
							<tbody>
							<?php foreach ( $logs as $l ) : ?>
								<tr>
									<td><?php echo esc_html( gv_sr_jalali_numeric( $l->work_date ) ); ?></td>
									<td><?php echo esc_html( gv_sr_weekday_name( $l->work_date ) ); ?></td>
									<td><?php echo esc_html( gv_sr_entry_mode_label( $l->entry_mode ) ); ?></td>
									<td><?php echo esc_html( $l->start_time ?: '—' ); ?></td>
									<td><?php echo esc_html( $l->end_time ?: '—' ); ?></td>
									<td><b><?php echo esc_html( gv_sr_fa_digits( $l->hours ) ); ?></b></td>
									<td><?php echo esc_html( $l->client_name ?: '—' ); ?></td>
									<td><?php echo esc_html( $l->note ?: '—' ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</details>
		<?php endforeach; ?>
	</div>

	<?php /* ==================== ۴) آکاردئون: تغییر رمز عبور (کم‌تکرار) ==================== */ ?>
	<details class="gvsr-section-toggle">
		<summary>🔑 تغییر رمز عبور بخش مدیریت تیم</summary>
		<div class="gvsr-section-toggle-body">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-grid-2">
				<?php wp_nonce_field( GV_SR_TEAM_NONCE ); ?>
				<input type="hidden" name="action" value="gv_sr_team_change_pass">
				<label>رمز عبور جدید (حداقل ۶ کاراکتر)
					<input type="password" name="new_password" minlength="6" required>
				</label>
				<div style="align-self:flex-end;"><button type="submit" class="gvsr-btn-export">💾 تغییر رمز</button></div>
			</form>
		</div>
	</details>

	<?php
	do_action( 'gv_sr_team_tab_extra', $from, $to );
}

/* ==========================================================================
   ۲) این چند خط را داخل تابع gv_sr_admin_styles() اضافه کنید
   (مثلاً کنار قانون‌های .gvsr-emp-accordion که از قبل هست).
   استایل آکاردئون‌های سطح‌بالا (کارمندان / تغییر رمز) را می‌سازد؛
   طراحی‌اش عمداً شبیه و هم‌خانواده با .gvsr-emp-accordion است تا
   یکدست به‌نظر برسد.
   ========================================================================== */
/*
.gvsr-section-toggle{background:var(--gv-surface);border:1px solid var(--gv-border);border-radius:var(--gv-radius-lg);padding:0;margin-bottom:16px;box-shadow:var(--gv-shadow);max-width:1100px;overflow:hidden;}
.gvsr-section-toggle summary{cursor:pointer;list-style:none;font-size:14px;font-weight:800;color:var(--gv-ink);padding:16px 20px;display:flex;align-items:center;gap:8px;}
.gvsr-section-toggle summary::-webkit-details-marker{display:none;}
.gvsr-section-toggle summary::before{content:"›";display:inline-block;transition:transform .15s ease;color:var(--gv-muted);font-size:16px;}
.gvsr-section-toggle[open] summary::before{transform:rotate(90deg);}
.gvsr-section-toggle summary:hover{background:#f8fafc;}
.gvsr-section-toggle .gvsr-toggle-count{font-weight:600;color:var(--gv-muted);font-size:12px;}
.gvsr-section-toggle-body{padding:0 20px 20px;border-top:1px solid var(--gv-border);padding-top:16px;}
*/

/* ==========================================================================
   ۹) فرم افزودن / ویرایش گزارش
   ========================================================================== */
function gv_sr_render_admin_form( $report ) {
	$is_edit    = ! empty( $report );
	$report_id  = $is_edit ? (int) $report->id : 0;
	$keywords   = $is_edit ? gv_sr_get_keywords( $report_id ) : array();
	$tasks      = $is_edit ? gv_sr_get_tasks( $report_id, 'work_date', 'ASC' ) : array();
	$growth     = $is_edit ? gv_sr_get_growth( $report_id ) : array();
	$visibility = $is_edit ? gv_sr_get_visibility( $report ) : gv_sr_default_visibility();
	$task_types = gv_sr_task_types();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-form">
		<?php wp_nonce_field( GV_SR_NONCE ); ?>
		<input type="hidden" name="action" value="gv_sr_save_report">
		<input type="hidden" name="report_id" value="<?php echo esc_attr( $report_id ); ?>">

		<div class="gvsr-box">
			<h2>👤 اطلاعات مشتری و گزارش</h2>
			<div class="gvsr-grid-2">
				<label>نام مشتری / کسب‌وکار
					<input type="text" name="client_name" required value="<?php echo esc_attr( $is_edit ? $report->client_name : '' ); ?>" placeholder="مثلاً: فروشگاه اینترنتی نمونه">
				</label>
				<label>اتصال به کاربر سایت (برای ورود مشتری به پنل)
					<?php
					wp_dropdown_users( array(
						'name'             => 'user_id',
						'show_option_none' => '— بدون اتصال به کاربر سایت —',
						'option_none_value'=> 0,
						'selected'         => $is_edit ? (int) $report->user_id : 0,
						'class'            => 'gvsr-select',
					) );
					?>
				</label>
			</div>
			<div class="gvsr-grid-2">
				<label>عنوان گزارش
					<input type="text" name="title" required value="<?php echo esc_attr( $is_edit ? $report->title : 'گزارش هفتگی سئو' ); ?>">
				</label>
				<label>وضعیت انتشار
					<select name="status" class="gvsr-select">
						<option value="draft" <?php selected( $is_edit ? $report->status : 'draft', 'draft' ); ?>>پیش‌نویس (فقط داخلی، مشتری نمی‌بیند)</option>
						<option value="published" <?php selected( $is_edit ? $report->status : '', 'published' ); ?>>منتشرشده (برای مشتری قابل مشاهده است)</option>
					</select>
				</label>
			</div>
			<div class="gvsr-grid-2">
				<label>شروع بازه گزارش (شمسی)
					<?php echo gv_sr_jalali_select_fields( 'period_start', $is_edit ? $report->period_start : '' ); ?>
				</label>
				<label>پایان بازه گزارش (شمسی)
					<?php echo gv_sr_jalali_select_fields( 'period_end', $is_edit ? $report->period_end : '' ); ?>
				</label>
			</div>
			<label>خلاصه عملکرد (توضیح کارمند برای مشتری)
				<textarea name="summary" rows="4" placeholder="در این بازه چه اقداماتی انجام شد و نتیجه کلی چه بود؟"><?php echo esc_textarea( $is_edit ? $report->summary : '' ); ?></textarea>
			</label>
			<label>برنامه گام بعدی
				<textarea name="next_steps" rows="3" placeholder="برنامه و اولویت‌های بازه بعدی چیست؟"><?php echo esc_textarea( $is_edit ? $report->next_steps : '' ); ?></textarea>
			</label>
			<div class="gvsr-grid-4">
				<label>ساعت کار صرف‌شده
					<input type="number" step="0.5" min="0" name="hours_spent" value="<?php echo esc_attr( $is_edit ? $report->hours_spent : '' ); ?>">
				</label>
				<label>ترافیک ارگانیک — قبل
					<input type="number" min="0" name="traffic_before" value="<?php echo esc_attr( $is_edit ? $report->traffic_before : '' ); ?>">
				</label>
				<label>ترافیک ارگانیک — بعد
					<input type="number" min="0" name="traffic_after" value="<?php echo esc_attr( $is_edit ? $report->traffic_after : '' ); ?>">
				</label>
				<label>امتیاز کلی سئو (۰ تا ۱۰۰)
					<input type="number" min="0" max="100" name="overall_score" value="<?php echo esc_attr( $is_edit ? $report->overall_score : 70 ); ?>">
				</label>
			</div>
		</div>

		<div class="gvsr-box">
			<h2>🔑 کلمات کلیدی و تغییر رتبه</h2>
			<p class="gvsr-hint">رتبه ۰ یعنی «خارج از ۱۰۰ / رتبه‌بندی نشده». برای کلمه‌ای که تازه وارد نتایج شده، «رتبه قبلی» را ۰ بگذارید.</p>
			<table class="gvsr-repeater" id="gvsr-repeater-kw">
				<thead><tr>
					<th style="width:20%;">کلمه کلیدی</th>
					<th style="width:12%;">موتور جستجو</th>
					<th style="width:20%;">لینک صفحه</th>
					<th style="width:10%;">رتبه قبلی</th>
					<th style="width:10%;">رتبه فعلی</th>
					<th>توضیح</th>
					<th style="width:34px;"></th>
				</tr></thead>
				<tbody>
					<?php if ( $keywords ) : foreach ( $keywords as $k ) : ?>
					<tr>
						<td><input type="text" name="kw_keyword[]" value="<?php echo esc_attr( $k->keyword ); ?>"></td>
						<td><input type="text" name="kw_engine[]" value="<?php echo esc_attr( $k->search_engine ); ?>"></td>
						<td><input type="url" name="kw_url[]" value="<?php echo esc_attr( $k->page_url ); ?>"></td>
						<td><input type="number" min="0" name="kw_prev[]" value="<?php echo esc_attr( $k->prev_rank ); ?>"></td>
						<td><input type="number" min="0" name="kw_curr[]" value="<?php echo esc_attr( $k->curr_rank ); ?>"></td>
						<td><input type="text" name="kw_note[]" value="<?php echo esc_attr( $k->note ); ?>"></td>
						<td><button type="button" class="gvsr-row-del">✕</button></td>
					</tr>
					<?php endforeach; else : ?>
					<tr>
						<td><input type="text" name="kw_keyword[]"></td>
						<td><input type="text" name="kw_engine[]" value="گوگل"></td>
						<td><input type="url" name="kw_url[]"></td>
						<td><input type="number" min="0" name="kw_prev[]"></td>
						<td><input type="number" min="0" name="kw_curr[]"></td>
						<td><input type="text" name="kw_note[]"></td>
						<td><button type="button" class="gvsr-row-del">✕</button></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<button type="button" class="gvsr-btn-add" data-target="gvsr-repeater-kw">➕ افزودن کلمه کلیدی</button>
		</div>

		<div class="gvsr-box">
			<h2>🗂️ ریز فعالیت‌ها و محتوای تولید/بروزرسانی‌شده</h2>
			<table class="gvsr-repeater" id="gvsr-repeater-task">
				<thead><tr>
					<th style="width:14%;">نوع فعالیت</th>
					<th style="width:20%;">عنوان</th>
					<th style="width:16%;">لینک</th>
					<th style="width:12%;">کلمه هدف</th>
					<th style="width:16%;">تاریخ انجام</th>
					<th style="width:8%;">ساعت</th>
					<th>توضیح</th>
					<th style="width:34px;"></th>
				</tr></thead>
				<tbody>
					<?php if ( $tasks ) : foreach ( $tasks as $t ) : ?>
					<tr>
						<td>
							<select name="task_type[]" class="gvsr-select">
								<?php foreach ( $task_types as $key => $info ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $t->task_type, $key ); ?>><?php echo esc_html( $info['icon'] . ' ' . $info['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="text" name="task_title[]" value="<?php echo esc_attr( $t->title ); ?>"></td>
						<td><input type="url" name="task_url[]" value="<?php echo esc_attr( $t->url ); ?>"></td>
						<td><input type="text" name="task_keyword[]" value="<?php echo esc_attr( $t->target_keyword ); ?>"></td>
						<td><?php echo gv_sr_jalali_select_fields( 'task_date[]', $t->work_date ); ?></td>
						<td><input type="number" step="0.5" min="0" name="task_hours[]" value="<?php echo esc_attr( $t->hours ); ?>"></td>
						<td><input type="text" name="task_note[]" value="<?php echo esc_attr( $t->note ); ?>"></td>
						<td><button type="button" class="gvsr-row-del">✕</button></td>
					</tr>
					<?php endforeach; else : ?>
					<tr>
						<td>
							<select name="task_type[]" class="gvsr-select">
								<?php foreach ( $task_types as $key => $info ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $info['icon'] . ' ' . $info['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="text" name="task_title[]"></td>
						<td><input type="url" name="task_url[]"></td>
						<td><input type="text" name="task_keyword[]"></td>
						<td><?php echo gv_sr_jalali_select_fields( 'task_date[]', '' ); ?></td>
						<td><input type="number" step="0.5" min="0" name="task_hours[]"></td>
						<td><input type="text" name="task_note[]"></td>
						<td><button type="button" class="gvsr-row-del">✕</button></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<button type="button" class="gvsr-btn-add" data-target="gvsr-repeater-task">➕ افزودن فعالیت</button>
			<p class="gvsr-hint">نکته: تعداد محتوای منتشرشده در بازه‌های ۷ تا ۹۰ روز، به‌صورت خودکار از روی همین ردیف‌ها (نوع «تولید محتوای جدید» و «بروزرسانی محتوا») برای مشتری محاسبه و نمایش داده می‌شود.</p>
		</div>

		<div class="gvsr-box">
			<h2>📈 رشد صفحات</h2>
			<table class="gvsr-repeater" id="gvsr-repeater-growth">
				<thead><tr>
					<th style="width:18%;">عنوان صفحه</th>
					<th style="width:20%;">لینک</th>
					<th style="width:16%;">شاخص اندازه‌گیری</th>
					<th style="width:12%;">مقدار قبل</th>
					<th style="width:12%;">مقدار بعد</th>
					<th>توضیح</th>
					<th style="width:34px;"></th>
				</tr></thead>
				<tbody>
					<?php if ( $growth ) : foreach ( $growth as $g ) : ?>
					<tr>
						<td><input type="text" name="growth_title[]" value="<?php echo esc_attr( $g->page_title ); ?>"></td>
						<td><input type="url" name="growth_url[]" value="<?php echo esc_attr( $g->page_url ); ?>"></td>
						<td><input type="text" name="growth_metric[]" value="<?php echo esc_attr( $g->metric_label ); ?>"></td>
						<td><input type="text" name="growth_before[]" value="<?php echo esc_attr( $g->before_value ); ?>"></td>
						<td><input type="text" name="growth_after[]" value="<?php echo esc_attr( $g->after_value ); ?>"></td>
						<td><input type="text" name="growth_note[]" value="<?php echo esc_attr( $g->note ); ?>"></td>
						<td><button type="button" class="gvsr-row-del">✕</button></td>
					</tr>
					<?php endforeach; else : ?>
					<tr>
						<td><input type="text" name="growth_title[]"></td>
						<td><input type="url" name="growth_url[]"></td>
						<td><input type="text" name="growth_metric[]" placeholder="مثلاً: بازدید ماهانه صفحه"></td>
						<td><input type="text" name="growth_before[]"></td>
						<td><input type="text" name="growth_after[]"></td>
						<td><input type="text" name="growth_note[]"></td>
						<td><button type="button" class="gvsr-row-del">✕</button></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<button type="button" class="gvsr-btn-add" data-target="gvsr-repeater-growth">➕ افزودن ردیف رشد</button>
		</div>

		<div class="gvsr-box">
			<h2>👁️ کنترل نمایش برای مشتری</h2>
			<p class="gvsr-hint">هر بخش را می‌توانید جدا برای مشتری فعال یا غیرفعال کنید. علاوه بر این، تا وقتی وضعیت گزارش «پیش‌نویس» باشد، کل گزارش برای مشتری نامرئی است.</p>
			<div class="gvsr-vis-grid">
				<?php foreach ( gv_sr_visibility_keys() as $key => $label ) : ?>
					<label class="gvsr-vis-item">
						<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $visibility[ $key ] ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="gvsr-form-actions">
			<button type="submit" class="gvsr-btn-export">💾 ذخیره گزارش</button>
			<a class="gvsr-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG ) ); ?>">انصراف</a>
		</div>
	</form>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.gvsr-btn-add').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var table = document.getElementById(btn.getAttribute('data-target'));
				var tbody = table.querySelector('tbody');
				var lastRow = tbody.querySelector('tr:last-child');
				var newRow = lastRow.cloneNode(true);
				newRow.querySelectorAll('input[type="text"], input[type="url"], input[type="number"]').forEach(function (el) { el.value = ''; });
				newRow.querySelectorAll('select.gvsr-select').forEach(function (el) { el.selectedIndex = 0; });
				tbody.appendChild(newRow);
			});
		});
		document.addEventListener('click', function (e) {
			if (e.target && e.target.classList.contains('gvsr-row-del')) {
				var tbody = e.target.closest('tbody');
				if (tbody.querySelectorAll('tr').length > 1) {
					e.target.closest('tr').remove();
				} else {
					e.target.closest('tr').querySelectorAll('input[type="text"], input[type="url"], input[type="number"]').forEach(function (el) { el.value = ''; });
				}
			}
		});
	});
	</script>
	<?php
}

/* ==========================================================================
   ۱۰) استایل مشترک صفحات مدیریت + اسکریپت مرتب‌سازی جدول
   ========================================================================== */
function gv_sr_admin_styles() {
	?>
	<style>
		:root{
			--gv-ink:#0f172a; --gv-ink-soft:#475569; --gv-muted:#94a3b8;
			--gv-bg:#f4f6f8; --gv-surface:#ffffff; --gv-border:#e6e9ee;
			--gv-accent:#0f766e; --gv-accent-dark:#0b5a54; --gv-accent-soft:#e6f6f4;
			--gv-green:#16a34a; --gv-green-soft:#e9f9ee;
			--gv-red:#dc2626; --gv-red-soft:#fdeeee;
			--gv-blue:#2563eb; --gv-blue-soft:#eef4ff;
			--gv-amber:#b45309; --gv-amber-soft:#fef6e7;
			--gv-radius-lg:16px; --gv-radius-md:12px; --gv-radius-sm:8px;
			--gv-shadow:0 1px 2px rgba(15,23,42,.04), 0 6px 18px rgba(15,23,42,.06);
			--gv-shadow-lift:0 10px 30px rgba(15,23,42,.10);
		}
		.wrap:has(.gvsr-topbar){background:var(--gv-bg);}
		#wpbody-content .gvsr-report-card,
		#wpbody-content .gvsr-box{ direction:rtl; }

		/* ---------- عمومی ---------- */
		.gvsr-header{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,var(--gv-accent-dark),var(--gv-accent));color:#fff;padding:22px 26px;border-radius:var(--gv-radius-lg);margin:16px 0 14px;box-shadow:var(--gv-shadow-lift);flex-wrap:wrap;gap:12px;}
		.gvsr-header h1{margin:0;font-size:19px;color:#fff;font-weight:800;}
		.gvsr-header span{opacity:.85;font-size:12.5px;}
		.gvsr-btn-export,.gvsr-btn-ghost,.gvsr-btn-add{
			display:inline-flex;align-items:center;gap:6px;font-family:inherit;cursor:pointer;white-space:nowrap;
			border-radius:10px;font-size:12.8px;font-weight:700;transition:filter .15s ease, transform .1s ease, background .15s ease;
		}
		.gvsr-btn-export{background:#fff;color:var(--gv-accent-dark);padding:10px 18px;border:0;box-shadow:0 1px 2px rgba(0,0,0,.06);}
		.gvsr-btn-export:hover{filter:brightness(.97);}
		.gvsr-header .gvsr-btn-ghost{background:rgba(255,255,255,.14);color:#fff;padding:9px 16px;border:1px solid rgba(255,255,255,.3);}
		.gvsr-header .gvsr-btn-ghost:hover{background:rgba(255,255,255,.22);}
		.gvsr-btn-ghost{background:var(--gv-surface);color:var(--gv-ink-soft);padding:8px 14px;border:1px solid var(--gv-border);}
		.gvsr-btn-ghost:hover{border-color:var(--gv-accent);color:var(--gv-accent-dark);}
		.gvsr-btn-add{background:var(--gv-accent-soft);color:var(--gv-accent-dark);border:1px solid #bfe6e1;padding:9px 16px;}
		.gvsr-btn-add:hover{background:#d8f0ec;}
		.gvsr-btn-export:active,.gvsr-btn-ghost:active,.gvsr-btn-add:active{transform:translateY(1px);}

		.gvsr-notice{background:var(--gv-green-soft);color:#166534;border:1px solid #bbf0cd;padding:11px 16px;border-radius:var(--gv-radius-sm);font-size:13px;margin-bottom:14px;max-width:1100px;}
		.gvsr-stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;max-width:1100px;}
		@media(max-width:900px){.gvsr-stat-cards{grid-template-columns:1fr 1fr;}}
		.gvsr-stat{background:var(--gv-surface);border:1px solid var(--gv-border);border-radius:var(--gv-radius-md);padding:16px 18px;box-shadow:var(--gv-shadow);}
		.gvsr-stat b{display:block;font-size:24px;color:var(--gv-accent-dark);font-weight:800;}
		.gvsr-stat span{font-size:12.5px;color:var(--gv-ink-soft);}

		.gvsr-filter-bar{display:flex;gap:10px;margin-bottom:16px;max-width:1100px;flex-wrap:wrap;align-items:flex-end;}
		.gvsr-filter-bar input[type="text"]{flex:1;min-width:200px;padding:9px 12px;border:1px solid var(--gv-border);border-radius:var(--gv-radius-sm);font-family:inherit;}
		.gvsr-filter-bar select{padding:9px 12px;border:1px solid var(--gv-border);border-radius:var(--gv-radius-sm);font-family:inherit;background:#fff;}

		.gvsr-table-wrap{background:var(--gv-surface);border:1px solid var(--gv-border);border-radius:var(--gv-radius-md);overflow:hidden;max-width:1100px;overflow-x:auto;box-shadow:var(--gv-shadow);}
		.gvsr-table{width:100%;border-collapse:collapse;}
		.gvsr-table th{background:#f8fafc;color:var(--gv-ink-soft);padding:11px 14px;text-align:right;font-size:11.8px;font-weight:800;white-space:nowrap;cursor:pointer;border-bottom:1px solid var(--gv-border);}
		.gvsr-table th:hover{color:var(--gv-accent-dark);}
		.gvsr-table td{padding:10px 14px;border-top:1px solid #f1f5f9;font-size:12.6px;white-space:nowrap;color:var(--gv-ink);}
		.gvsr-table tbody tr:hover{background:#fafcfc;}
		.gvsr-row-actions a{margin-inline-start:10px;text-decoration:none;font-size:12px;color:var(--gv-accent-dark);font-weight:700;}
		.gvsr-row-actions a.gvsr-danger{color:var(--gv-red);}
		.gvsr-row-actions a:hover{text-decoration:underline;}
		.gvsr-badge{padding:3px 10px;border-radius:20px;font-size:10.8px;font-weight:800;white-space:nowrap;}
		.gvsr-badge-green{background:var(--gv-green-soft);color:#166534;}
		.gvsr-badge-gray{background:#f1f5f9;color:#64748b;}
		.gvsr-cs-none{color:#cbd5e1;font-size:11px;}
		.gvsr-empty{padding:34px 20px;text-align:center;color:var(--gv-muted);font-size:13px;}
		.gvsr-hint{font-size:11.3px;color:var(--gv-muted);margin:6px 0 10px;line-height:1.8;}
		.gvsr-hint-inline{font-size:11.8px;color:var(--gv-muted);line-height:1.9;}
		.gvsr-preview-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;}

		/* ---------- فرم‌ها ---------- */
		.gvsr-form{max-width:1100px;}
		.gvsr-box,.gvsr-report-card{background:var(--gv-surface);border:1px solid var(--gv-border);border-radius:var(--gv-radius-lg);padding:20px 22px;margin-bottom:16px;box-shadow:var(--gv-shadow);}
		.gvsr-box h2{font-size:14.5px;margin:0 0 14px;color:var(--gv-ink);font-weight:800;display:flex;align-items:center;gap:8px;}
		.gvsr-form label{display:block;font-size:12.3px;color:var(--gv-ink-soft);font-weight:700;margin-bottom:12px;}
		.gvsr-form input[type="text"],.gvsr-form input[type="url"],.gvsr-form input[type="number"],.gvsr-form input[type="time"],.gvsr-form input[type="password"],.gvsr-form textarea,.gvsr-form select.gvsr-select{
			width:100%;box-sizing:border-box;margin-top:6px;padding:9px 11px;border:1px solid var(--gv-border);border-radius:var(--gv-radius-sm);font-family:inherit;font-size:12.6px;font-weight:400;color:var(--gv-ink);background:#fff;transition:border-color .15s ease, box-shadow .15s ease;
		}
		.gvsr-form input:focus,.gvsr-form textarea:focus,.gvsr-form select:focus{outline:0;border-color:var(--gv-accent);box-shadow:0 0 0 3px var(--gv-accent-soft);}
		.gvsr-form textarea{resize:vertical;}
		.gvsr-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
		.gvsr-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
		@media(max-width:800px){.gvsr-grid-2,.gvsr-grid-4{grid-template-columns:1fr;}}

		.gvsr-repeater{width:100%;border-collapse:collapse;margin-bottom:10px;}
		.gvsr-repeater th{background:#f8fafc;font-size:10.8px;color:var(--gv-ink-soft);padding:7px 8px;text-align:right;font-weight:800;}
		.gvsr-repeater td{padding:5px 6px;border-top:1px solid #f1f5f9;vertical-align:middle;}
		.gvsr-repeater input,.gvsr-repeater select{width:100%;box-sizing:border-box;padding:7px 8px;border:1px solid var(--gv-border);border-radius:7px;font-family:inherit;font-size:11.5px;}
		.gvsr-jdate-group{display:flex;gap:3px;}
		.gvsr-jdate-group select{min-width:0;}
		.gvsr-row-del{background:var(--gv-red-soft);color:var(--gv-red);border:0;border-radius:7px;width:28px;height:28px;cursor:pointer;font-size:12px;}
		.gvsr-vis-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
		@media(max-width:700px){.gvsr-vis-grid{grid-template-columns:1fr;}}
		.gvsr-vis-item{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #eef0f2;border-radius:var(--gv-radius-sm);padding:10px 12px;font-size:12.3px;font-weight:600;color:var(--gv-ink-soft);margin-bottom:0;}
		.gvsr-vis-item input{width:auto!important;margin:0!important;}
		.gvsr-form-actions{display:flex;gap:10px;}

		/* ---------- گزارش مشتری (پیش‌نمایش) ---------- */
		.gvsr-report-view{max-width:1100px;}
		.gvsr-hidden-flag{background:var(--gv-red-soft);color:var(--gv-red);font-size:10.3px;font-weight:800;padding:2px 8px;border-radius:20px;margin-inline-start:8px;}
		.gvsr-report-card h3{margin:0 0 12px;font-size:14px;color:var(--gv-ink);font-weight:800;display:flex;align-items:center;gap:6px;}
		.gvsr-kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:18px;}
		@media(max-width:900px){.gvsr-kpi-grid{grid-template-columns:repeat(2,1fr);}}
		.gvsr-kpi{background:#fbfdfd;border:1px solid var(--gv-border);border-radius:var(--gv-radius-md);padding:15px 16px;text-align:center;}
		.gvsr-kpi b{display:block;font-size:21px;color:var(--gv-accent-dark);font-weight:800;}
		.gvsr-kpi span{font-size:11.2px;color:var(--gv-ink-soft);}
		.gvsr-period-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:18px;}
		@media(max-width:900px){.gvsr-period-grid{grid-template-columns:repeat(3,1fr);}}
		.gvsr-period-item{background:var(--gv-accent-soft);border:1px solid #cdeae5;border-radius:var(--gv-radius-md);padding:12px 8px;text-align:center;}
		.gvsr-period-item b{display:block;font-size:19px;color:var(--gv-accent-dark);font-weight:800;}
		.gvsr-period-item span{font-size:10.3px;color:#0f766e;}
		.gvsr-chart-empty{padding:30px;text-align:center;color:var(--gv-muted);font-size:12.5px;}
		.gvsr-svg-chart{width:100%;height:auto;font-family:inherit;}
		.gvsr-delta{font-weight:800;white-space:nowrap;}
		.gvsr-summary-text{white-space:pre-wrap;line-height:2;font-size:13px;color:#334155;background:#f8fafc;border-radius:var(--gv-radius-sm);padding:14px 16px;}

		/* ---------- تب‌های اصلی (segmented control) ---------- */
		.gvsr-maintabs{display:inline-flex;gap:3px;background:#fff;border:1px solid var(--gv-border);padding:4px;border-radius:12px;margin-bottom:16px;box-shadow:var(--gv-shadow);flex-wrap:wrap;}
		.gvsr-maintab{background:transparent;border:0;color:var(--gv-ink-soft);font-weight:700;font-size:12.6px;padding:9px 18px;border-radius:9px;text-decoration:none;transition:background .15s ease,color .15s ease;}
		.gvsr-maintab.is-active{background:var(--gv-accent);color:#fff;box-shadow:0 2px 8px rgba(15,118,110,.28);}
		.gvsr-maintab:not(.is-active):hover{background:#f1f5f9;color:var(--gv-ink);}

		/* ---------- نوار سراسری بالای صفحه: شناسایی کارمند + تایمر ---------- */
		.gvsr-topbar{position:sticky;top:32px;z-index:500;background:#fff;border:1px solid var(--gv-border);border-radius:var(--gv-radius-md);box-shadow:var(--gv-shadow-lift);padding:12px 18px;margin:16px 0;max-width:1100px;}
		@media(max-width:782px){.gvsr-topbar{top:46px;}}
		.gvsr-topbar-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
		.gvsr-topbar-idle{color:var(--gv-ink-soft);font-size:12.8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
		.gvsr-topbar-name{font-weight:800;color:var(--gv-ink);}
		.gvsr-topbar-name .dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--gv-muted);margin-inline-end:6px;}
		.gvsr-topbar-name.running .dot{background:var(--gv-green);box-shadow:0 0 0 3px var(--gv-green-soft);animation:gvsr-pulse 1.6s infinite;}
		@keyframes gvsr-pulse{0%{box-shadow:0 0 0 0 rgba(22,163,74,.35);}70%{box-shadow:0 0 0 6px rgba(22,163,74,0);}100%{box-shadow:0 0 0 0 rgba(22,163,74,0);}}
		.gvsr-topbar-clock{font-family:'Courier New',monospace;font-weight:800;font-size:18px;color:var(--gv-accent-dark);background:var(--gv-accent-soft);border-radius:8px;padding:5px 12px;direction:ltr;letter-spacing:1px;}
		.gvsr-topbar-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex:1;}
		.gvsr-topbar-form input[type="text"]{padding:8px 10px;border:1px solid var(--gv-border);border-radius:8px;font-family:inherit;font-size:12.3px;min-width:160px;}
		.gvsr-topbar-form input[type="text"]:focus{outline:0;border-color:var(--gv-accent);box-shadow:0 0 0 3px var(--gv-accent-soft);}
		.gvsr-topbar-btn-start{background:var(--gv-accent);color:#fff;border:0;border-radius:9px;padding:8px 18px;font-weight:800;font-size:12.6px;cursor:pointer;white-space:nowrap;}
		.gvsr-topbar-btn-start:hover{background:var(--gv-accent-dark);}
		.gvsr-topbar-btn-stop{background:var(--gv-red-soft);color:var(--gv-red);border:1px solid #f7c9c9;border-radius:9px;padding:8px 18px;font-weight:800;font-size:12.6px;cursor:pointer;white-space:nowrap;}
		.gvsr-topbar-btn-stop:hover{background:#fbdada;}
		.gvsr-topbar-switch{margin-inline-start:auto;font-size:11.5px;}
		.gvsr-topbar-switch summary{cursor:pointer;color:var(--gv-muted);list-style:none;}
		.gvsr-topbar-switch summary::-webkit-details-marker{display:none;}
		.gvsr-topbar-switch summary:hover{color:var(--gv-accent-dark);}
		.gvsr-topbar-switch-panel{position:absolute;left:0;margin-top:10px;background:#fff;border:1px solid var(--gv-border);border-radius:var(--gv-radius-md);box-shadow:var(--gv-shadow-lift);padding:16px;width:340px;max-width:90vw;z-index:600;}
		@media(max-width:600px){.gvsr-topbar-switch-panel{position:static;width:auto;margin-top:12px;}}

		/* ---------- شناسایی کارمند در تب کارکرد من (فشرده) ---------- */
		.gvsr-emp-identify-form{max-width:900px;}
		.gvsr-timelog-form{max-width:900px;}
		.gvsr-code-box{background:var(--gv-accent-soft);border:1px solid #bfe6e1;color:var(--gv-accent-dark);border-radius:var(--gv-radius-sm);padding:11px 15px;font-size:12.6px;margin-bottom:14px;}
		.gvsr-code-box span{display:block;font-size:11px;color:#0f766e;margin-top:3px;font-weight:400;}

		/* ---------- سوییچ روش ثبت ساعت (segmented) ---------- */
		.gvsr-radio-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#f8fafc;border:1px solid #eef0f2;border-radius:11px;padding:6px;margin-bottom:16px;font-weight:700;color:var(--gv-ink-soft);}
		.gvsr-radio-row > span:first-child{padding-inline-start:6px;font-size:11.8px;color:var(--gv-muted);font-weight:600;}
		.gvsr-radio-item{position:relative;display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:12.3px;color:var(--gv-ink-soft);padding:8px 16px;border-radius:8px;cursor:pointer;transition:background .15s ease,color .15s ease;}
		.gvsr-radio-item:has(input:checked){background:#fff;color:var(--gv-accent-dark);box-shadow:var(--gv-shadow);}
		.gvsr-radio-item input{width:auto!important;accent-color:var(--gv-accent);}

		.gvsr-emp-accordion{border:1px solid var(--gv-border);border-radius:var(--gv-radius-md);padding:12px 15px;margin-bottom:10px;background:#fafcfc;}
		.gvsr-emp-accordion summary{cursor:pointer;font-weight:700;font-size:12.8px;color:var(--gv-ink);list-style:none;}
		.gvsr-emp-accordion summary::-webkit-details-marker{display:none;}
		.gvsr-emp-accordion[open]{background:#fff;box-shadow:var(--gv-shadow);}

		.gvsr-node-box,.gvsr-hub-box{border:1px dashed #cbd5e1;border-radius:var(--gv-radius-md);padding:14px 16px;margin-top:12px;}
		.gvsr-sync-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:800;}
		.gvsr-sync-badge.ok{background:var(--gv-green-soft);color:#166534;}
		.gvsr-sync-badge.bad{background:var(--gv-red-soft);color:var(--gv-red);}

		/* ---------- مودال هشدار خروج هنگام تایمر فعال ---------- */
		.gvsr-timer-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:99999;align-items:center;justify-content:center;}
		.gvsr-timer-modal-box{background:#fff;border-radius:var(--gv-radius-lg);padding:26px 24px;max-width:380px;width:90%;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.25);}
		.gvsr-timer-modal-box p{font-size:13.5px;color:var(--gv-ink);margin:0 0 18px;line-height:1.9;}
		.gvsr-timer-modal-actions{display:flex;flex-direction:column;gap:8px;}
	</style>
	<?php
}

function gv_sr_admin_sort_script() {
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.gvsr-sortable').forEach(function (table) {
			var headerRow = table.querySelector('thead tr');
			if (!headerRow) { return; }
			var ths = Array.prototype.slice.call(headerRow.children);
			ths.forEach(function (th, index) {
				if (th.classList.contains('no-sort')) { return; }
				th.addEventListener('click', function () {
					var tbody = table.querySelector('tbody');
					if (!tbody) { return; }
					var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
					var ascending = th.getAttribute('data-sort-dir') !== 'asc';
					ths.forEach(function (t) { t.removeAttribute('data-sort-dir'); });
					th.setAttribute('data-sort-dir', ascending ? 'asc' : 'desc');
					var type = th.getAttribute('data-sort-type') || 'text';
					rows.sort(function (rowA, rowB) {
						var cellA = rowA.children[index], cellB = rowB.children[index];
						var valA = cellA.getAttribute('data-sort-value'), valB = cellB.getAttribute('data-sort-value');
						if (valA === null) { valA = cellA.textContent.trim(); }
						if (valB === null) { valB = cellB.textContent.trim(); }
						if (type === 'number' || type === 'date') {
							valA = parseFloat(valA) || 0; valB = parseFloat(valB) || 0;
							return ascending ? valA - valB : valB - valA;
						}
						return ascending ? valA.localeCompare(valB, 'fa') : valB.localeCompare(valA, 'fa');
					});
					rows.forEach(function (row) { tbody.appendChild(row); });
				});
			});
		});
	});
	</script>
	<?php
}

/* ==========================================================================
   ۱۱) رندر جزئیات یک گزارش — مشترک بین پیش‌نمایش ادمین و پنل مشتری
   $is_admin = true یعنی همه‌چیز نشان داده شود، حتی بخش‌های مخفی
   (با برچسب قرمز «مخفی از مشتری»)؛ false یعنی فقط طبق تنظیمات نمایش.
   ========================================================================== */
function gv_sr_render_report_detail( $report, $is_admin = false ) {
	$vis     = gv_sr_get_visibility( $report );
	$keywords = gv_sr_get_keywords( $report->id );
	$tasks    = gv_sr_get_tasks( $report->id, 'work_date', 'ASC' );
	$growth   = gv_sr_get_growth( $report->id );
	$types    = gv_sr_task_types();

	$can = function ( $key ) use ( $vis, $is_admin ) {
		return $is_admin || ! empty( $vis[ $key ] );
	};
	$flag = function ( $key ) use ( $vis, $is_admin ) {
		if ( $is_admin && empty( $vis[ $key ] ) ) {
			echo '<span class="gvsr-hidden-flag">مخفی از مشتری</span>';
		}
	};
	?>
	<div class="gvsr-report-view">

		<div class="gvsr-report-card">
			<h3>🧾 <?php echo esc_html( $report->title ); ?></h3>
			<p class="gvsr-hint-inline">
				بازه گزارش: <b><?php echo esc_html( gv_sr_jalali_str( $report->period_start ) . ' تا ' . gv_sr_jalali_str( $report->period_end, true ) ); ?></b>
				&nbsp;|&nbsp; مشتری: <b><?php echo esc_html( $report->client_name ); ?></b>
				<?php if ( $report->overall_score > 0 ) : ?>
					&nbsp;|&nbsp; امتیاز کلی سئو: <b><?php echo esc_html( gv_sr_fa_digits( $report->overall_score ) ); ?> از ۱۰۰</b>
				<?php endif; ?>
			</p>

			<div class="gvsr-kpi-grid">
				<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( count( $keywords ) ) ); ?></b><span>کلمه کلیدی رصدشده</span></div>
				<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( count( $tasks ) ) ); ?></b><span>فعالیت ثبت‌شده</span></div>
				<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( count( $growth ) ) ); ?></b><span>صفحه در حال رشد</span></div>
				<div class="gvsr-kpi"><b><?php echo esc_html( gv_sr_fa_digits( $report->hours_spent ) ); ?><?php $flag( 'show_hours' ); ?></b><span>ساعت کار</span></div>
				<div class="gvsr-kpi"><b>
					<?php
					if ( $report->traffic_before > 0 ) {
						$growth_pct = round( ( ( $report->traffic_after - $report->traffic_before ) / max( 1, $report->traffic_before ) ) * 100 );
						echo esc_html( gv_sr_fa_digits( ( $growth_pct >= 0 ? '+' : '' ) . $growth_pct ) ) . '٪';
					} else {
						echo '—';
					}
					$flag( 'show_traffic' );
					?>
				</b><span>رشد ترافیک ارگانیک</span></div>
			</div>
		</div>

		<?php if ( $can( 'show_summary' ) && ( $report->summary || $is_admin ) ) : ?>
		<div class="gvsr-report-card">
			<h3>📋 خلاصه عملکرد <?php $flag( 'show_summary' ); ?></h3>
			<?php if ( $report->summary ) : ?>
				<div class="gvsr-summary-text"><?php echo wp_kses_post( wpautop( $report->summary ) ); ?></div>
			<?php else : ?>
				<div class="gvsr-chart-empty">خلاصه‌ای ثبت نشده است.</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( $can( 'show_keywords' ) && ( $keywords || $is_admin ) ) : ?>
		<div class="gvsr-report-card">
			<h3>🔑 کلمات کلیدی و تغییر رتبه <?php $flag( 'show_keywords' ); ?></h3>
			<?php if ( empty( $keywords ) ) : ?>
				<div class="gvsr-chart-empty">کلمه کلیدی‌ای برای این گزارش ثبت نشده است.</div>
			<?php else : ?>
				<table class="gvsr-table gvsr-sortable">
					<thead><tr>
						<th data-sort-type="text">کلمه کلیدی</th>
						<th data-sort-type="text">موتور جستجو</th>
						<th data-sort-type="number">رتبه قبلی</th>
						<th data-sort-type="number">رتبه فعلی</th>
						<th data-sort-type="text">تغییر</th>
						<th class="no-sort">توضیح</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $keywords as $k ) : $d = gv_sr_rank_delta( $k->prev_rank, $k->curr_rank ); ?>
						<tr>
							<td><b><?php echo esc_html( $k->keyword ); ?></b><?php if ( $k->page_url ) : ?><br><a href="<?php echo esc_url( $k->page_url ); ?>" target="_blank" rel="noopener" style="font-size:10.5px;">مشاهده صفحه ↗</a><?php endif; ?></td>
							<td><?php echo esc_html( $k->search_engine ); ?></td>
							<td data-sort-value="<?php echo esc_attr( $k->prev_rank ); ?>"><?php echo esc_html( $k->prev_rank > 0 ? gv_sr_fa_digits( $k->prev_rank ) : '—' ); ?></td>
							<td data-sort-value="<?php echo esc_attr( $k->curr_rank ); ?>"><?php echo esc_html( $k->curr_rank > 0 ? gv_sr_fa_digits( $k->curr_rank ) : '—' ); ?></td>
							<td data-sort-value="<?php echo esc_attr( 'up' === $d['type'] ? $d['diff'] : ( 'down' === $d['type'] ? -1 * $d['diff'] : 0 ) ); ?>">
								<span class="gvsr-delta" style="color:<?php echo esc_attr( $d['color'] ); ?>;"><?php echo esc_html( $d['icon'] . ' ' . $d['label'] ); ?></span>
							</td>
							<td><?php echo esc_html( $k->note ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( $can( 'show_tasks' ) && ( $tasks || $is_admin ) ) : ?>
		<div class="gvsr-report-card">
			<h3>🗂️ ریز فعالیت‌ها و محتوای تولیدشده <?php $flag( 'show_tasks' ); ?></h3>
			<?php if ( empty( $tasks ) ) : ?>
				<div class="gvsr-chart-empty">فعالیتی برای این گزارش ثبت نشده است.</div>
			<?php else : ?>
				<table class="gvsr-table gvsr-sortable">
					<thead><tr>
						<th data-sort-type="text">نوع فعالیت</th>
						<th data-sort-type="text">عنوان</th>
						<th data-sort-type="text">کلمه هدف</th>
						<th data-sort-type="date">تاریخ</th>
						<th data-sort-type="number">ساعت</th>
						<th class="no-sort">لینک</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $tasks as $t ) : $info = $types[ $t->task_type ] ?? array( 'label' => $t->task_type, 'icon' => '•', 'color' => '#64748b' ); ?>
						<tr>
							<td><span class="gvsr-badge" style="background:<?php echo esc_attr( $info['color'] ); ?>1a;color:<?php echo esc_attr( $info['color'] ); ?>;"><?php echo esc_html( $info['icon'] . ' ' . $info['label'] ); ?></span></td>
							<td><?php echo esc_html( $t->title ); ?></td>
							<td><?php echo esc_html( $t->target_keyword ?: '—' ); ?></td>
							<td data-sort-value="<?php echo esc_attr( strtotime( $t->work_date ) ); ?>"><?php echo esc_html( gv_sr_jalali_numeric( $t->work_date ) ); ?></td>
							<td data-sort-value="<?php echo esc_attr( $t->hours ); ?>"><?php echo esc_html( $t->hours > 0 ? gv_sr_fa_digits( $t->hours ) : '—' ); ?></td>
							<td><?php if ( $t->url ) : ?><a href="<?php echo esc_url( $t->url ); ?>" target="_blank" rel="noopener">مشاهده ↗</a><?php else : ?>—<?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( $can( 'show_growth' ) && ( $growth || $is_admin ) ) : ?>
		<div class="gvsr-report-card">
			<h3>📈 رشد صفحات <?php $flag( 'show_growth' ); ?></h3>
			<?php if ( empty( $growth ) ) : ?>
				<div class="gvsr-chart-empty">ردیف رشدی برای این گزارش ثبت نشده است.</div>
			<?php else : ?>
				<table class="gvsr-table">
					<thead><tr><th>صفحه</th><th>شاخص</th><th>قبل</th><th>بعد</th><th>توضیح</th></tr></thead>
					<tbody>
					<?php foreach ( $growth as $g ) : ?>
						<tr>
							<td><?php echo esc_html( $g->page_title ?: '—' ); ?><?php if ( $g->page_url ) : ?><br><a href="<?php echo esc_url( $g->page_url ); ?>" target="_blank" rel="noopener" style="font-size:10.5px;">مشاهده ↗</a><?php endif; ?></td>
							<td><?php echo esc_html( $g->metric_label ); ?></td>
							<td><?php echo esc_html( $g->before_value ); ?></td>
							<td><b style="color:#059669;"><?php echo esc_html( $g->after_value ); ?></b></td>
							<td><?php echo esc_html( $g->note ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( $can( 'show_next_steps' ) && ( $report->next_steps || $is_admin ) ) : ?>
		<div class="gvsr-report-card">
			<h3>🚀 برنامه گام بعدی <?php $flag( 'show_next_steps' ); ?></h3>
			<?php if ( $report->next_steps ) : ?>
				<div class="gvsr-summary-text"><?php echo wp_kses_post( wpautop( $report->next_steps ) ); ?></div>
			<?php else : ?>
				<div class="gvsr-chart-empty">برنامه‌ای برای گام بعدی ثبت نشده است.</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

	</div>
	<?php
	// اسکریپت مرتب‌سازی جداول همین صفحه (در صورت وجود جدول قابل‌سورت)
	gv_sr_admin_sort_script();
}

/* ==========================================================================
   ۱۲) پنل مشتری در سایت — شورت‌کد [gv_seo_reports]
   ========================================================================== */
add_shortcode( 'gv_seo_reports', 'gv_sr_shortcode' );
function gv_sr_shortcode( $atts ) {
	ob_start();
	echo '<div class="gvsr-front" dir="rtl">';
	gv_sr_admin_styles();
	echo '<style>.gvsr-front{font-family:inherit;} .gvsr-front .gvsr-table-wrap,.gvsr-front .gvsr-report-view,.gvsr-front .gvsr-stat-cards,.gvsr-front .gvsr-kpi-grid,.gvsr-front .gvsr-period-grid,.gvsr-front .gvsr-filter-bar,.gvsr-front .gvsr-report-card{max-width:100%;}
	.gvsr-front-login{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:40px 24px;text-align:center;max-width:480px;margin:0 auto;}
	.gvsr-front-login h3{margin:0 0 10px;font-size:16px;color:#1e293b;}
	.gvsr-back-link{display:inline-block;margin-bottom:16px;color:#059669;font-weight:700;text-decoration:none;font-size:13px;}
	.gvsr-client-list{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;}
	.gvsr-client-list a{display:block;padding:14px 18px;text-decoration:none;color:#1e293b;border-top:1px solid #f1f5f9;}
	.gvsr-client-list a:first-child{border-top:0;}
	.gvsr-client-list a:hover{background:#f8fafc;}
	</style>';

	if ( ! is_user_logged_in() ) {
		echo '<div class="gvsr-front-login"><h3>🔒 برای مشاهده گزارش عملکرد سئو باید وارد حساب کاربری خود شوید</h3><p style="color:#64748b;font-size:13px;">پس از ورود، گزارش‌های سئوی اختصاصی شما این‌جا نمایش داده می‌شود.</p>';
		echo wp_login_form( array( 'echo' => false ) );
		echo '</div></div>';
		return ob_get_clean();
	}

	$user_id     = get_current_user_id();
	$viewing_id  = isset( $_GET['gv_report'] ) ? (int) $_GET['gv_report'] : 0;

	if ( $viewing_id > 0 ) {
		$report = gv_sr_get_report( $viewing_id );
		if ( ! $report || (int) $report->user_id !== $user_id || 'published' !== $report->status ) {
			echo '<div class="gvsr-empty">این گزارش پیدا نشد یا برای شما نیست.</div></div>';
			return ob_get_clean();
		}
		echo '<a class="gvsr-back-link" href="' . esc_url( remove_query_arg( 'gv_report' ) ) . '">→ بازگشت به لیست گزارش‌ها</a>';
		gv_sr_render_report_detail( $report, false );
		echo '</div>';
		return ob_get_clean();
	}

	gv_sr_render_customer_dashboard( $user_id );
	echo '</div>';
	return ob_get_clean();
}

function gv_sr_render_customer_dashboard( $user_id ) {
	$reports = gv_sr_get_reports( array( 'user_id' => $user_id, 'status' => 'published' ) );

	if ( empty( $reports ) ) {
		echo '<div class="gvsr-empty">هنوز هیچ گزارش سئویی برای شما منتشر نشده است. به‌محض ثبت اولین گزارش توسط تیم سئو، این‌جا نمایش داده می‌شود.</div>';
		return;
	}

	$period_counts   = gv_sr_count_content_periods( $user_id );
	$total_hours     = gv_sr_total_hours( $user_id );
	$kw_summary      = gv_sr_keyword_status_summary( $user_id );
	$traffic_trend   = gv_sr_traffic_trend( $user_id );
	$kw_history      = gv_sr_keyword_history( $user_id );

	$latest_traffic_growth = null;
	if ( ! empty( $traffic_trend ) ) {
		$first = reset( $traffic_trend );
		$last  = end( $traffic_trend );
		if ( $first['before'] > 0 ) {
			$latest_traffic_growth = round( ( ( $last['after'] - $first['before'] ) / max( 1, $first['before'] ) ) * 100 );
		}
	}

	?>
	<div class="gvsr-kpi-grid">
		<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( count( $reports ) ) ); ?></b><span>گزارش دریافت‌شده</span></div>
		<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( $kw_summary['up'] ) ); ?></b><span>کلمه کلیدی بهبودیافته</span></div>
		<div class="gvsr-kpi"><b><?php echo esc_html( number_format_i18n( $kw_summary['new'] ) ); ?></b><span>کلمه کلیدی تازه‌وارد</span></div>
		<div class="gvsr-kpi"><b><?php echo esc_html( gv_sr_fa_digits( round( $total_hours, 1 ) ) ); ?></b><span>مجموع ساعت کار روی سایت شما</span></div>
		<div class="gvsr-kpi"><b><?php echo null !== $latest_traffic_growth ? esc_html( gv_sr_fa_digits( ( $latest_traffic_growth >= 0 ? '+' : '' ) . $latest_traffic_growth ) . '٪' ) : '—'; ?></b><span>رشد ترافیک ارگانیک (کل دوره)</span></div>
	</div>

	<div class="gvsr-report-card">
		<h3>🗓️ تعداد محتوای منتشرشده در بازه‌های اخیر</h3>
		<div class="gvsr-period-grid">
			<?php foreach ( $period_counts as $days => $count ) : ?>
				<div class="gvsr-period-item"><b><?php echo esc_html( number_format_i18n( $count ) ); ?></b><span>‌طی <?php echo esc_html( gv_sr_fa_digits( $days ) ); ?> روز اخیر</span></div>
			<?php endforeach; ?>
		</div>
		<?php
		$bar_items = array();
		foreach ( $period_counts as $days => $count ) {
			$bar_items[] = array( 'label' => gv_sr_fa_digits( $days ) . ' روز', 'value' => $count );
		}
		echo gv_sr_svg_bar_chart( $bar_items, '#059669' ); // phpcs:ignore
		?>
	</div>

	<?php if ( ! empty( $traffic_trend ) && count( $traffic_trend ) > 1 ) : ?>
	<div class="gvsr-report-card">
		<h3>📊 روند ترافیک ارگانیک در طول زمان</h3>
		<?php
		$line_items = array();
		foreach ( $traffic_trend as $t ) { $line_items[] = array( 'label' => $t['label'], 'value' => $t['after'] ); }
		echo gv_sr_svg_line_chart( $line_items, '#2563eb' ); // phpcs:ignore
		?>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $kw_history ) ) : ?>
	<div class="gvsr-report-card">
		<h3>🔑 روند رتبه مهم‌ترین کلمات کلیدی</h3>
		<p class="gvsr-hint">نمودار پایین‌تر بهتر است؛ رتبه ۱ یعنی بهترین جایگاه در نتایج جستجو.</p>
		<?php
		$shown = 0;
		foreach ( $kw_history as $keyword => $history ) {
			if ( count( $history ) < 2 || $shown >= 6 ) { continue; }
			$shown++;
			$line_items = array();
			foreach ( $history as $h ) {
				if ( $h['rank'] <= 0 ) { continue; }
				$line_items[] = array( 'label' => gv_sr_jalali_numeric( $h['date'] ), 'value' => $h['rank'] );
			}
			if ( count( $line_items ) < 2 ) { continue; }
			echo '<h4 style="font-size:12.5px;color:#334155;margin:16px 0 6px;">' . esc_html( $keyword ) . '</h4>';
			echo gv_sr_svg_line_chart( $line_items, '#7c3aed', 640, 140, true ); // phpcs:ignore
		}
		if ( 0 === $shown ) { echo '<div class="gvsr-chart-empty">برای مشاهده روند، این کلمه کلیدی باید در بیش از یک گزارش رصد شده باشد.</div>'; }
		?>
	</div>
	<?php endif; ?>

	<div class="gvsr-report-card">
		<h3>📋 لیست کامل گزارش‌ها</h3>
		<p class="gvsr-hint">روی سرستون‌ها کلیک کنید تا جدول مرتب شود؛ روی هر گزارش کلیک کنید تا جزئیات کامل آن باز شود.</p>
		<div class="gvsr-table-wrap">
			<table class="gvsr-table gvsr-sortable">
				<thead><tr>
					<th data-sort-type="text">عنوان گزارش</th>
					<th data-sort-type="date">بازه گزارش</th>
					<th data-sort-type="number">ساعت کار</th>
					<th class="no-sort">مشاهده</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $reports as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r->title ); ?></td>
						<td data-sort-value="<?php echo esc_attr( strtotime( $r->period_end ) ); ?>"><?php echo esc_html( gv_sr_jalali_numeric( $r->period_start ) . ' تا ' . gv_sr_jalali_numeric( $r->period_end ) ); ?></td>
						<td data-sort-value="<?php echo esc_attr( $r->hours_spent ); ?>"><?php echo esc_html( gv_sr_fa_digits( $r->hours_spent ) ); ?></td>
						<td><a href="<?php echo esc_url( add_query_arg( 'gv_report', $r->id ) ); ?>" class="gvsr-btn-ghost" style="background:#ecfdf5;color:#065f46;border-color:#d1fae5;">مشاهده گزارش کامل ↗</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
	gv_sr_admin_sort_script();
}
// جدید

if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — ماژول «مدیریت پروژه‌ها»
 *  ------------------------------------------------------------
 *  نحوه‌ی الحاق: محتوای این فایل را در انتهای فایل اصلی افزونه
 *  (همان فایلی که gv_sr_maybe_install_db و بقیه‌ی توابع gv_sr_*
 *  در آن هستند) اضافه کنید، یا این فایل را require_once کنید.
 *  این ماژول از همان ثابت‌ها، توابع تاریخ شمسی، توابع کارمندان/
 *  تایم‌شیت و استایل مشترک (gv_sr_admin_styles) فایل اصلی استفاده
 *  می‌کند و چیز جدیدی در آن‌ها تعریف نمی‌کند.
 *
 *  آنچه اضافه می‌شود:
 *   ۱) ۳ جدول دیتابیس جدید: پروژه‌ها / اعضای پروژه / درآمد پروژه
 *   ۲) یک ستون جدید (project_id) روی جدول تایم‌شیت موجود
 *      تا کارکرد هر کارمند بتواند به یک پروژه مشخص متصل شود
 *   ۳) محاسبه‌ی درآمد کل، حقوق پرداختی، و سود خالص هر پروژه
 *   ۴) تب جدید «🗂️ پروژه‌ها» در پیشخوان (لیست / فرم / نمای جزئیات)
 *   ۵) نمایش سود پروژه فقط برای مدیرِ احرازشده در تب «مدیریت تیم»
 * ==========================================================
 */

define( 'GV_SR_PROJECTS_DB_VERSION', '1.4' );
define( 'GV_SR_PROJECTS_NONCE', 'gv_sr_projects_nonce_action' );

/* ==========================================================================
   ۰) نصب/بروزرسانی جداول دیتابیس
   ========================================================================== */
add_action( 'plugins_loaded', 'gv_sr_projects_maybe_install_db' );
function gv_sr_projects_maybe_install_db() {
	if ( get_option( 'gv_sr_projects_db_version' ) === GV_SR_PROJECTS_DB_VERSION ) { return; }

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$t_projects = $wpdb->prefix . 'gv_sr_projects';
	$t_members  = $wpdb->prefix . 'gv_sr_project_members';
	$t_income   = $wpdb->prefix . 'gv_sr_project_income';
	$t_timelogs = $wpdb->prefix . 'gv_sr_timelogs';

	dbDelta( "CREATE TABLE {$t_projects} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(255) NOT NULL,
		client_name VARCHAR(191) NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		manager_employee_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		status VARCHAR(20) NOT NULL DEFAULT 'planning',
		priority VARCHAR(10) NOT NULL DEFAULT 'normal',
		health VARCHAR(10) NOT NULL DEFAULT 'green',
		start_date DATE NULL,
		end_date DATE NULL,
		progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
		description LONGTEXT NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY client_name (client_name),
		KEY user_id (user_id),
		KEY status (status),
		KEY manager_employee_id (manager_employee_id)
	) {$charset_collate};" );

	dbDelta( "CREATE TABLE {$t_members} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		project_id BIGINT UNSIGNED NOT NULL,
		employee_id BIGINT UNSIGNED NOT NULL,
		role VARCHAR(120) NULL,
		added_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY project_id (project_id),
		KEY employee_id (employee_id),
		UNIQUE KEY project_employee (project_id, employee_id)
	) {$charset_collate};" );

	dbDelta( "CREATE TABLE {$t_income} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		project_id BIGINT UNSIGNED NOT NULL,
		amount BIGINT NOT NULL DEFAULT 0,
		income_date DATE NOT NULL,
		note VARCHAR(500) NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY project_id (project_id),
		KEY income_date (income_date)
	) {$charset_collate};" );

	/* افزودن ستون project_id به جدول تایم‌شیت موجود (dbDelta فقط ستون‌های
	   جدید را اضافه می‌کند و داده‌های فعلی دست‌نخورده می‌مانند) */
	dbDelta( "CREATE TABLE {$t_timelogs} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		employee_id BIGINT UNSIGNED NOT NULL,
		work_date DATE NOT NULL,
		entry_mode VARCHAR(10) NOT NULL DEFAULT 'manual',
		start_time VARCHAR(5) NULL,
		end_time VARCHAR(5) NULL,
		hours DECIMAL(5,2) NOT NULL DEFAULT 0,
		client_name VARCHAR(191) NULL,
		project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		note VARCHAR(500) NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY employee_id (employee_id),
		KEY work_date (work_date),
		KEY project_id (project_id)
	) {$charset_collate};" );

	update_option( 'gv_sr_projects_db_version', GV_SR_PROJECTS_DB_VERSION );
}

/* ==========================================================================
   ۱) تنظیمات ثابت (وضعیت / اولویت / سلامت پروژه)
   ========================================================================== */
function gv_sr_project_statuses() {
	return array(
		'planning'   => array( 'label' => 'برنامه‌ریزی',   'color' => '#64748b' ),
		'active'     => array( 'label' => 'در حال اجرا',    'color' => '#2563eb' ),
		'on_hold'    => array( 'label' => 'متوقف‌شده',      'color' => '#b45309' ),
		'completed'  => array( 'label' => 'تکمیل‌شده',      'color' => '#16a34a' ),
		'cancelled'  => array( 'label' => 'لغوشده',         'color' => '#dc2626' ),
	);
}
function gv_sr_project_priorities() {
	return array(
		'low'    => array( 'label' => 'کم',      'color' => '#64748b' ),
		'normal' => array( 'label' => 'عادی',    'color' => '#2563eb' ),
		'high'   => array( 'label' => 'بالا',    'color' => '#b45309' ),
		'urgent' => array( 'label' => 'فوری',    'color' => '#dc2626' ),
	);
}
function gv_sr_project_health_types() {
	return array(
		'green'  => array( 'label' => 'سالم',        'color' => '#16a34a', 'icon' => '🟢' ),
		'yellow' => array( 'label' => 'نیازمند توجه', 'color' => '#b45309', 'icon' => '🟡' ),
		'red'    => array( 'label' => 'در خطر',       'color' => '#dc2626', 'icon' => '🔴' ),
	);
}
function gv_sr_project_status_label( $key ) {
	$s = gv_sr_project_statuses();
	return isset( $s[ $key ] ) ? $s[ $key ]['label'] : $key;
}
function gv_sr_project_badge( $key, $map_fn ) {
	$map = call_user_func( $map_fn );
	$info = isset( $map[ $key ] ) ? $map[ $key ] : array( 'label' => $key, 'color' => '#64748b' );
	return '<span class="gvsr-badge" style="background:' . esc_attr( $info['color'] ) . '1a;color:' . esc_attr( $info['color'] ) . ';">' . esc_html( ( $info['icon'] ?? '' ) . ' ' . $info['label'] ) . '</span>';
}

/* ==========================================================================
   ۲) دسترسی به دیتابیس — پروژه‌ها
   ========================================================================== */
function gv_sr_get_project( $id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_projects';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) ); // phpcs:ignore
}

function gv_sr_get_projects( $args = array() ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_projects';

	$defaults = array(
		'status'      => '',
		'client_name' => '',
		'manager_id'  => 0,
		'search'      => '',
		'orderby'     => 'created_at',
		'order'       => 'DESC',
	);
	$args = wp_parse_args( $args, $defaults );

	$where  = array( '1=1' );
	$params = array();

	if ( '' !== $args['status'] ) { $where[] = 'status = %s'; $params[] = $args['status']; }
	if ( '' !== $args['client_name'] ) { $where[] = 'client_name = %s'; $params[] = $args['client_name']; }
	if ( $args['manager_id'] > 0 ) { $where[] = 'manager_employee_id = %d'; $params[] = (int) $args['manager_id']; }
	if ( '' !== $args['search'] ) {
		$where[]  = '(title LIKE %s OR client_name LIKE %s)';
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$params[] = $like;
		$params[] = $like;
	}

	$allowed_orderby = array( 'created_at', 'title', 'client_name', 'start_date', 'end_date', 'progress', 'status' );
	$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
	$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

	$sql = "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}";
	return ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore
}

function gv_sr_save_project( $data, $project_id = 0 ) {
	global $wpdb;
	$t   = $wpdb->prefix . 'gv_sr_projects';
	$now = current_time( 'mysql' );

	$statuses   = array_keys( gv_sr_project_statuses() );
	$priorities = array_keys( gv_sr_project_priorities() );
	$healths    = array_keys( gv_sr_project_health_types() );

	$row = array(
		'title'                => sanitize_text_field( $data['title'] ),
		'client_name'          => sanitize_text_field( $data['client_name'] ),
		'user_id'              => (int) $data['user_id'],
		'manager_employee_id'  => (int) $data['manager_employee_id'],
		'status'               => in_array( $data['status'], $statuses, true ) ? $data['status'] : 'planning',
		'priority'             => in_array( $data['priority'], $priorities, true ) ? $data['priority'] : 'normal',
		'health'               => in_array( $data['health'], $healths, true ) ? $data['health'] : 'green',
		'start_date'           => ! empty( $data['start_date'] ) ? $data['start_date'] : null,
		'end_date'             => ! empty( $data['end_date'] ) ? $data['end_date'] : null,
		'progress'             => max( 0, min( 100, (int) $data['progress'] ) ),
		'description'          => wp_kses_post( $data['description'] ),
		'updated_at'           => $now,
	);

	if ( $project_id > 0 ) {
		$wpdb->update( $t, $row, array( 'id' => $project_id ) ); // phpcs:ignore
		return $project_id;
	}
	$row['created_at'] = $now;
	$wpdb->insert( $t, $row ); // phpcs:ignore
	return (int) $wpdb->insert_id;
}

function gv_sr_delete_project( $project_id ) {
	global $wpdb;
	$project_id = (int) $project_id;
	$wpdb->delete( $wpdb->prefix . 'gv_sr_projects', array( 'id' => $project_id ) ); // phpcs:ignore
	$wpdb->delete( $wpdb->prefix . 'gv_sr_project_members', array( 'project_id' => $project_id ) ); // phpcs:ignore
	$wpdb->delete( $wpdb->prefix . 'gv_sr_project_income', array( 'project_id' => $project_id ) ); // phpcs:ignore
	// کارکرد ثبت‌شده روی این پروژه حذف نمی‌شود، فقط اتصالش به پروژه برداشته می‌شود
	$wpdb->update( $wpdb->prefix . 'gv_sr_timelogs', array( 'project_id' => 0 ), array( 'project_id' => $project_id ) ); // phpcs:ignore
}

/** لیست پروژه‌هایی که یک کارمند خاص عضوشان است (برای انتخاب هنگام ثبت کارکرد) */
function gv_sr_get_employee_projects( $employee_id ) {
	global $wpdb;
	$t_p = $wpdb->prefix . 'gv_sr_projects';
	$t_m = $wpdb->prefix . 'gv_sr_project_members';
	return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
		"SELECT p.* FROM {$t_p} p
		 INNER JOIN {$t_m} m ON m.project_id = p.id
		 WHERE m.employee_id = %d AND p.status IN ('planning','active')
		 ORDER BY p.title ASC",
		(int) $employee_id
	) );
}
/* ---------------- اعضای پروژه ---------------- */
function gv_sr_get_project_members( $project_id ) {
	global $wpdb;
	$t_m = $wpdb->prefix . 'gv_sr_project_members';
	$t_e = $wpdb->prefix . 'gv_sr_employees';
	return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
		"SELECT m.*, e.name AS employee_name, e.hourly_rate FROM {$t_m} m
		 INNER JOIN {$t_e} e ON e.id = m.employee_id
		 WHERE m.project_id = %d ORDER BY e.name ASC",
		(int) $project_id
	) );
}

/** جایگزینی کامل لیست اعضای یک پروژه؛ $rows = آرایه‌ای از [employee_id, role] */
function gv_sr_save_project_members( $project_id, $rows ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_project_members';
	$wpdb->delete( $t, array( 'project_id' => $project_id ) ); // phpcs:ignore
	if ( empty( $rows ) ) { return; }
	$now = current_time( 'mysql' );
	foreach ( $rows as $r ) {
		$employee_id = (int) $r['employee_id'];
		if ( $employee_id <= 0 ) { continue; }
		$wpdb->insert( $t, array( // phpcs:ignore
			'project_id'  => (int) $project_id,
			'employee_id' => $employee_id,
			'role'        => sanitize_text_field( $r['role'] ?? '' ),
			'added_at'    => $now,
		) );
	}
}

/* ---------------- درآمد پروژه ---------------- */
function gv_sr_get_project_income_rows( $project_id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_project_income';
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE project_id = %d ORDER BY income_date DESC, id DESC", (int) $project_id ) ); // phpcs:ignore
}
function gv_sr_save_project_income_row( $data, $income_id = 0 ) {
	global $wpdb;
	$t   = $wpdb->prefix . 'gv_sr_project_income';
	$row = array(
		'project_id'   => (int) $data['project_id'],
		'amount'       => (int) $data['amount'],
		'income_date'  => $data['income_date'],
		'note'         => sanitize_text_field( $data['note'] ?? '' ),
	);
	if ( $income_id > 0 ) {
		$wpdb->update( $t, $row, array( 'id' => $income_id ) ); // phpcs:ignore
		return $income_id;
	}
	$row['created_at'] = current_time( 'mysql' );
	$wpdb->insert( $t, $row ); // phpcs:ignore
	return (int) $wpdb->insert_id;
}
function gv_sr_delete_project_income_row( $income_id ) {
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'gv_sr_project_income', array( 'id' => (int) $income_id ) ); // phpcs:ignore
}

/* ==========================================================================
   ۳) محاسبه سود پروژه — فقط برای مدیریت
   ========================================================================== */

/** مجموع درآمد ثبت‌شده برای یک پروژه (اختیاراً در یک بازه تاریخی) */
function gv_sr_project_total_income( $project_id, $date_from = '', $date_to = '' ) {
	global $wpdb;
	$t = $wpdb->prefix . 'gv_sr_project_income';
	$where  = array( 'project_id = %d' );
	$params = array( (int) $project_id );
	if ( '' !== $date_from ) { $where[] = 'income_date >= %s'; $params[] = $date_from; }
	if ( '' !== $date_to )   { $where[] = 'income_date <= %s'; $params[] = $date_to; }
	$sql = "SELECT SUM(amount) FROM {$t} WHERE " . implode( ' AND ', $where );
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore
}

/** مجموع حقوق پرداختی پروژه = مجموع (ساعت کارکرد روی این پروژه × نرخ ساعتی همان کارمند) */
function gv_sr_project_labor_cost( $project_id, $date_from = '', $date_to = '' ) {
	global $wpdb;
	$t_log = $wpdb->prefix . 'gv_sr_timelogs';
	$t_emp = $wpdb->prefix . 'gv_sr_employees';

	$where  = array( 'l.project_id = %d' );
	$params = array( (int) $project_id );
	if ( '' !== $date_from ) { $where[] = 'l.work_date >= %s'; $params[] = $date_from; }
	if ( '' !== $date_to )   { $where[] = 'l.work_date <= %s'; $params[] = $date_to; }

	$sql = "SELECT l.hours, e.hourly_rate FROM {$t_log} l
	        INNER JOIN {$t_emp} e ON e.id = l.employee_id
	        WHERE " . implode( ' AND ', $where );
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore

	$total_hours = 0.0;
	$total_cost  = 0.0;
	foreach ( $rows as $r ) {
		$total_hours += (float) $r->hours;
		$total_cost  += (float) $r->hours * (int) $r->hourly_rate;
	}
	return array( 'hours' => round( $total_hours, 2 ), 'cost' => (int) round( $total_cost ) );
}

/** خلاصه کامل سود یک پروژه: درآمد / هزینه نیروی انسانی / سود خالص */
function gv_sr_project_profit( $project_id, $date_from = '', $date_to = '' ) {
	$income = gv_sr_project_total_income( $project_id, $date_from, $date_to );
	$labor  = gv_sr_project_labor_cost( $project_id, $date_from, $date_to );
	return array(
		'income'      => $income,
		'labor_hours' => $labor['hours'],
		'labor_cost'  => $labor['cost'],
		'net_profit'  => $income - $labor['cost'],
	);
}

/* ==========================================================================
   ۴) Admin Post Handlers
   ========================================================================== */
add_action( 'admin_post_gv_sr_save_project', 'gv_sr_handle_save_project' );
function gv_sr_handle_save_project() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_PROJECTS_NONCE );

	$project_id = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0;

	$data = array(
		'title'               => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
		'client_name'         => isset( $_POST['client_name'] ) ? wp_unslash( $_POST['client_name'] ) : '',
		'user_id'             => isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0,
		'manager_employee_id' => isset( $_POST['manager_employee_id'] ) ? (int) $_POST['manager_employee_id'] : 0,
		'status'              => isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'planning',
		'priority'            => isset( $_POST['priority'] ) ? sanitize_key( $_POST['priority'] ) : 'normal',
		'health'              => isset( $_POST['health'] ) ? sanitize_key( $_POST['health'] ) : 'green',
		'start_date'          => gv_sr_read_jalali_post( 'start_date' ),
		'end_date'            => gv_sr_read_jalali_post( 'end_date' ),
		'progress'            => isset( $_POST['progress'] ) ? $_POST['progress'] : 0,
		'description'         => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
	);

	$new_id = gv_sr_save_project( $data, $project_id );

	/* اعضای پروژه */
	$member_rows = array();
	if ( ! empty( $_POST['member_employee_id'] ) && is_array( $_POST['member_employee_id'] ) ) {
		foreach ( $_POST['member_employee_id'] as $i => $emp_id ) {
			if ( (int) $emp_id <= 0 ) { continue; }
			$member_rows[] = array(
				'employee_id' => (int) $emp_id,
				'role'        => wp_unslash( $_POST['member_role'][ $i ] ?? '' ),
			);
		}
	}
	gv_sr_save_project_members( $new_id, $member_rows );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects&view=' . $new_id . '&saved_project=1' ) );
	exit;
}

add_action( 'admin_post_gv_sr_delete_project', 'gv_sr_handle_delete_project' );
function gv_sr_handle_delete_project() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_PROJECTS_NONCE );
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( $id > 0 ) { gv_sr_delete_project( $id ); }
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects&deleted_project=1' ) );
	exit;
}

/* درآمد پروژه — فقط مدیرِ احرازشده (همان رمز تیم) می‌تواند ثبت/حذف کند */
add_action( 'admin_post_gv_sr_save_project_income', 'gv_sr_handle_save_project_income' );
function gv_sr_handle_save_project_income() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_PROJECTS_NONCE );

	$project_id = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0;
	gv_sr_save_project_income_row( array(
		'project_id'  => $project_id,
		'amount'      => isset( $_POST['amount'] ) ? $_POST['amount'] : 0,
		'income_date' => gv_sr_read_jalali_post( 'income_date' ),
		'note'        => isset( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : '',
	) );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&view_profit=' . $project_id . '&saved_income=1' ) );
	exit;
}
add_action( 'admin_post_gv_sr_delete_project_income', 'gv_sr_handle_delete_project_income' );
function gv_sr_handle_delete_project_income() {
	if ( ! current_user_can( 'manage_options' ) || ! gv_sr_team_is_authed() ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_SR_PROJECTS_NONCE );
	$id         = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$project_id = isset( $_GET['project_id'] ) ? (int) $_GET['project_id'] : 0;
	if ( $id > 0 ) { gv_sr_delete_project_income_row( $id ); }
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=team&view_profit=' . $project_id ) );
	exit;
}

/* ==========================================================================
   ۵) رندر تب «پروژه‌ها» — این تابع را از روتر اصلی تب‌ها صدا بزنید:
   در gv_sr_render_admin_page() داخل شرط‌های if/elseif یک شاخه اضافه کنید:
       } elseif ( 'projects' === $tab ) {
           gv_sr_render_projects_tab();
   و در نوار gvsr-maintabs یک لینک هم اضافه کنید:
       <a class="gvsr-maintab ..." href="...&tab=projects">🗂️ پروژه‌ها</a>
   ========================================================================== */
function gv_sr_render_projects_tab() {
	if ( isset( $_GET['saved_project'] ) ) { echo '<div class="gvsr-notice">پروژه با موفقیت ذخیره شد.</div>'; }
	if ( isset( $_GET['deleted_project'] ) ) { echo '<div class="gvsr-notice">پروژه حذف شد.</div>'; }

	$view_id = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
	$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;

	if ( $view_id > 0 ) {
		$project = gv_sr_get_project( $view_id );
		if ( $project ) {
			gv_sr_render_project_detail( $project );
		} else {
			echo '<div class="gvsr-empty">پروژه پیدا نشد.</div>';
		}
		return;
	}

	if ( $edit_id > 0 || isset( $_GET['new'] ) ) {
		$project = $edit_id > 0 ? gv_sr_get_project( $edit_id ) : null;
		gv_sr_render_project_form( $project );
		return;
	}

	gv_sr_render_projects_list();
}

function gv_sr_render_projects_list() {
	$status = isset( $_GET['pstatus'] ) ? sanitize_key( $_GET['pstatus'] ) : '';
	$search = isset( $_GET['ps'] ) ? sanitize_text_field( wp_unslash( $_GET['ps'] ) ) : '';
	$projects = gv_sr_get_projects( array( 'status' => $status, 'search' => $search ) );

	$total    = count( $projects );
	$active   = count( array_filter( $projects, function ( $p ) { return 'active' === $p->status; } ) );
	$at_risk  = count( array_filter( $projects, function ( $p ) { return 'red' === $p->health; } ) );
	$avg_prog = $total > 0 ? round( array_sum( wp_list_pluck( $projects, 'progress' ) ) / $total ) : 0;
	?>
	<div class="gvsr-stat-cards">
		<div class="gvsr-stat"><b><?php echo esc_html( number_format_i18n( $total ) ); ?></b><span>تعداد کل پروژه‌ها</span></div>
		<div class="gvsr-stat"><b><?php echo esc_html( number_format_i18n( $active ) ); ?></b><span>در حال اجرا</span></div>
		<div class="gvsr-stat"><b style="color:#dc2626;"><?php echo esc_html( number_format_i18n( $at_risk ) ); ?></b><span>در خطر (سلامت قرمز)</span></div>
		<div class="gvsr-stat"><b><?php echo esc_html( gv_sr_fa_digits( $avg_prog ) ); ?>٪</b><span>میانگین پیشرفت</span></div>
	</div>

	<div class="gvsr-preview-tools">
		<a class="gvsr-btn-export" href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects&new=1' ) ); ?>">➕ پروژه جدید</a>
	</div>

	<form method="get" class="gvsr-filter-bar">
		<input type="hidden" name="page" value="<?php echo esc_attr( GV_SR_PAGE_SLUG ); ?>">
		<input type="hidden" name="tab" value="projects">
		<input type="text" name="ps" value="<?php echo esc_attr( $search ); ?>" placeholder="جست‌وجوی عنوان پروژه یا مشتری...">
		<select name="pstatus">
			<option value="">همه وضعیت‌ها</option>
			<?php foreach ( gv_sr_project_statuses() as $key => $info ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="gvsr-btn-ghost">فیلتر</button>
	</form>

	<div class="gvsr-table-wrap">
		<?php if ( empty( $projects ) ) : ?>
			<div class="gvsr-empty">هنوز پروژه‌ای ثبت نشده. با دکمه «پروژه جدید» شروع کنید.</div>
		<?php else : ?>
			<table class="gvsr-table gvsr-sortable">
				<thead><tr>
					<th data-sort-type="text">عنوان پروژه</th>
					<th data-sort-type="text">مشتری</th>
					<th data-sort-type="text">مسئول پروژه</th>
					<th data-sort-type="text">وضعیت</th>
					<th data-sort-type="text">اولویت</th>
					<th data-sort-type="text">سلامت</th>
					<th data-sort-type="number">پیشرفت</th>
					<th data-sort-type="date">پایان</th>
					<th class="no-sort">عملیات</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $projects as $p ) :
					$manager = $p->manager_employee_id > 0 ? gv_sr_get_employee( $p->manager_employee_id ) : null;
					?>
					<tr>
						<td><b><?php echo esc_html( $p->title ); ?></b></td>
						<td><?php echo esc_html( $p->client_name ); ?></td>
						<td><?php echo $manager ? esc_html( $manager->name ) : '—'; ?></td>
						<td><?php echo gv_sr_project_badge( $p->status, 'gv_sr_project_statuses' ); ?></td>
						<td><?php echo gv_sr_project_badge( $p->priority, 'gv_sr_project_priorities' ); ?></td>
						<td><?php echo gv_sr_project_badge( $p->health, 'gv_sr_project_health_types' ); ?></td>
						<td data-sort-value="<?php echo esc_attr( $p->progress ); ?>"><?php echo esc_html( gv_sr_fa_digits( $p->progress ) ); ?>٪</td>
						<td data-sort-value="<?php echo esc_attr( $p->end_date ? strtotime( $p->end_date ) : 0 ); ?>"><?php echo esc_html( gv_sr_jalali_numeric( $p->end_date ) ); ?></td>
						<td class="gvsr-row-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects&view=' . $p->id ) ); ?>">مشاهده</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects&edit=' . $p->id ) ); ?>">ویرایش</a>
							<a class="gvsr-danger" onclick="return confirm('این پروژه برای همیشه حذف می‌شود. ادامه می‌دهید؟');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_delete_project&id=' . $p->id ), GV_SR_PROJECTS_NONCE ) ); ?>">حذف</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
	gv_sr_admin_sort_script();
}

function gv_sr_render_project_form( $project ) {
	$is_edit    = ! empty( $project );
	$project_id = $is_edit ? (int) $project->id : 0;
	$members    = $is_edit ? gv_sr_get_project_members( $project_id ) : array();
	$employees  = gv_sr_get_employees( true );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-form">
		<?php wp_nonce_field( GV_SR_PROJECTS_NONCE ); ?>
		<input type="hidden" name="action" value="gv_sr_save_project">
		<input type="hidden" name="project_id" value="<?php echo esc_attr( $project_id ); ?>">

		<div class="gvsr-box">
			<h2>🗂️ اطلاعات کلی پروژه</h2>
			<div class="gvsr-grid-2">
				<label>عنوان پروژه
					<input type="text" name="title" required value="<?php echo esc_attr( $is_edit ? $project->title : '' ); ?>" placeholder="مثلاً: طراحی سایت فروشگاه نمونه">
				</label>
				<label>نام مشتری
					<input type="text" name="client_name" list="gvsr-client-datalist" required value="<?php echo esc_attr( $is_edit ? $project->client_name : '' ); ?>">
				</label>
			</div>
			<datalist id="gvsr-client-datalist">
				<?php foreach ( gv_sr_get_clients() as $c ) : ?>
					<option value="<?php echo esc_attr( $c->client_name ); ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<div class="gvsr-grid-2">
				<label>اتصال به کاربر سایت (اختیاری)
					<?php
					wp_dropdown_users( array(
						'name'              => 'user_id',
						'show_option_none'  => '— بدون اتصال —',
						'option_none_value' => 0,
						'selected'          => $is_edit ? (int) $project->user_id : 0,
						'class'             => 'gvsr-select',
					) );
					?>
				</label>
				<label>مسئول پروژه
					<select name="manager_employee_id" class="gvsr-select">
						<option value="0">— تعیین نشده —</option>
						<?php foreach ( $employees as $e ) : ?>
							<option value="<?php echo esc_attr( $e->id ); ?>" <?php selected( $is_edit ? (int) $project->manager_employee_id : 0, (int) $e->id ); ?>><?php echo esc_html( $e->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<div class="gvsr-grid-4">
				<label>وضعیت پروژه
					<select name="status" class="gvsr-select">
						<?php foreach ( gv_sr_project_statuses() as $key => $info ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $is_edit ? $project->status : 'planning', $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>اولویت
					<select name="priority" class="gvsr-select">
						<?php foreach ( gv_sr_project_priorities() as $key => $info ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $is_edit ? $project->priority : 'normal', $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>سلامت پروژه
					<select name="health" class="gvsr-select">
						<?php foreach ( gv_sr_project_health_types() as $key => $info ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $is_edit ? $project->health : 'green', $key ); ?>><?php echo esc_html( $info['icon'] . ' ' . $info['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>درصد پیشرفت
					<input type="number" min="0" max="100" name="progress" value="<?php echo esc_attr( $is_edit ? $project->progress : 0 ); ?>">
				</label>
			</div>

			<div class="gvsr-grid-2">
				<label>تاریخ شروع (شمسی)
					<?php echo gv_sr_jalali_select_fields( 'start_date', $is_edit ? $project->start_date : '' ); ?>
				</label>
				<label>تاریخ پایان (شمسی)
					<?php echo gv_sr_jalali_select_fields( 'end_date', $is_edit ? $project->end_date : '' ); ?>
				</label>
			</div>

			<label>توضیحات / دامنه پروژه
				<textarea name="description" rows="4"><?php echo esc_textarea( $is_edit ? $project->description : '' ); ?></textarea>
			</label>
		</div>

		<div class="gvsr-box">
			<h2>👥 اعضای پروژه</h2>
			<p class="gvsr-hint">هر کارمندی که به‌عنوان عضو اضافه شود، هنگام ثبت کارکرد در تب «کارکرد من» می‌تواند این پروژه را انتخاب کند.</p>
			<table class="gvsr-repeater" id="gvsr-repeater-members">
				<thead><tr><th style="width:55%;">کارمند</th><th>نقش در پروژه</th><th style="width:34px;"></th></tr></thead>
				<tbody>
					<?php if ( $members ) : foreach ( $members as $m ) : ?>
					<tr>
						<td>
							<select name="member_employee_id[]" class="gvsr-select">
								<option value="0">— انتخاب —</option>
								<?php foreach ( $employees as $e ) : ?>
									<option value="<?php echo esc_attr( $e->id ); ?>" <?php selected( (int) $m->employee_id, (int) $e->id ); ?>><?php echo esc_html( $e->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="text" name="member_role[]" value="<?php echo esc_attr( $m->role ); ?>" placeholder="مثلاً: توسعه‌دهنده فرانت"></td>
						<td><button type="button" class="gvsr-row-del">✕</button></td>
					</tr>
					<?php endforeach; else : ?>
					<tr>
						<td>
							<select name="member_employee_id[]" class="gvsr-select">
								<option value="0">— انتخاب —</option>
								<?php foreach ( $employees as $e ) : ?>
									<option value="<?php echo esc_attr( $e->id ); ?>"><?php echo esc_html( $e->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="text" name="member_role[]" placeholder="مثلاً: توسعه‌دهنده فرانت"></td>
						<td><button type="button" class="gvsr-row-del">✕</button></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<button type="button" class="gvsr-btn-add" data-target="gvsr-repeater-members">➕ افزودن عضو</button>
		</div>

		<div class="gvsr-form-actions">
			<button type="submit" class="gvsr-btn-export">💾 ذخیره پروژه</button>
			<a class="gvsr-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects' ) ); ?>">انصراف</a>
		</div>
	</form>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.gvsr-btn-add').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var table = document.getElementById(btn.getAttribute('data-target'));
				var tbody = table.querySelector('tbody');
				var lastRow = tbody.querySelector('tr:last-child');
				var newRow = lastRow.cloneNode(true);
				newRow.querySelectorAll('input[type="text"]').forEach(function (el) { el.value = ''; });
				newRow.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
				tbody.appendChild(newRow);
			});
		});
		document.addEventListener('click', function (e) {
			if (e.target && e.target.classList.contains('gvsr-row-del')) {
				var tbody = e.target.closest('tbody');
				if (tbody.querySelectorAll('tr').length > 1) { e.target.closest('tr').remove(); }
			}
		});
	});
	</script>
	<?php
}

/** نمای جزئیات پروژه؛ سود پروژه فقط برای مدیر احرازشده نمایش داده می‌شود */
function gv_sr_render_project_detail( $project ) {
	$members  = gv_sr_get_project_members( $project->id );
	$manager  = $project->manager_employee_id > 0 ? gv_sr_get_employee( $project->manager_employee_id ) : null;
	$is_admin_authed = gv_sr_team_is_authed();
	?>
	<div class="gvsr-preview-tools">
		<a class="gvsr-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects' ) ); ?>">← بازگشت به لیست پروژه‌ها</a>
		<a class="gvsr-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=projects&edit=' . $project->id ) ); ?>">✏️ ویرایش پروژه</a>
	</div>

	<div class="gvsr-report-card">
		<h3>🗂️ <?php echo esc_html( $project->title ); ?></h3>
		<p class="gvsr-hint-inline">
			مشتری: <b><?php echo esc_html( $project->client_name ); ?></b>
			&nbsp;|&nbsp; مسئول پروژه: <b><?php echo $manager ? esc_html( $manager->name ) : '—'; ?></b>
			&nbsp;|&nbsp; بازه: <b><?php echo esc_html( gv_sr_jalali_numeric( $project->start_date ) . ' تا ' . gv_sr_jalali_numeric( $project->end_date ) ); ?></b>
		</p>
		<p>
			<?php echo gv_sr_project_badge( $project->status, 'gv_sr_project_statuses' ); ?>
			<?php echo gv_sr_project_badge( $project->priority, 'gv_sr_project_priorities' ); ?>
			<?php echo gv_sr_project_badge( $project->health, 'gv_sr_project_health_types' ); ?>
		</p>

		<div style="background:#f1f5f9;border-radius:20px;overflow:hidden;height:14px;margin:14px 0 6px;">
			<div style="height:100%;width:<?php echo esc_attr( (int) $project->progress ); ?>%;background:linear-gradient(90deg,#0f766e,#059669);"></div>
		</div>
		<p class="gvsr-hint-inline"><?php echo esc_html( gv_sr_fa_digits( $project->progress ) ); ?>٪ پیشرفت</p>

		<?php if ( $project->description ) : ?>
			<div class="gvsr-summary-text" style="margin-top:12px;"><?php echo wp_kses_post( wpautop( $project->description ) ); ?></div>
		<?php endif; ?>
	</div>

	<div class="gvsr-report-card">
		<h3>👥 اعضای پروژه (<?php echo esc_html( number_format_i18n( count( $members ) ) ); ?> نفر)</h3>
		<?php if ( empty( $members ) ) : ?>
			<div class="gvsr-chart-empty">هنوز عضوی به این پروژه اضافه نشده.</div>
		<?php else : ?>
			<table class="gvsr-table">
				<thead><tr><th>کارمند</th><th>نقش</th><th>جمع ساعت کارکرد روی این پروژه</th></tr></thead>
				<tbody>
				<?php foreach ( $members as $m ) :
					$hours = gv_sr_employee_total_hours( $m->employee_id ); // کل ساعت کارمند
					$logs  = gv_sr_get_timelogs( array( 'employee_id' => $m->employee_id ) );
					$proj_hours = 0.0;
					foreach ( $logs as $l ) { if ( (int) $l->project_id === (int) $project->id ) { $proj_hours += (float) $l->hours; } }
					?>
					<tr>
						<td><b><?php echo esc_html( $m->employee_name ); ?></b></td>
						<td><?php echo esc_html( $m->role ?: '—' ); ?></td>
						<td><?php echo esc_html( gv_sr_fa_digits( round( $proj_hours, 2 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php if ( $is_admin_authed ) : gv_sr_render_project_profit_box( $project ); else : ?>
		<div class="gvsr-report-card">
			<h3>💰 سود پروژه</h3>
			<div class="gvsr-chart-empty">این بخش فقط برای مدیر (پس از ورود در تب «مدیریت تیم») قابل مشاهده است.</div>
		</div>
	<?php endif; ?>
	<?php
}

/** جعبه‌ی محاسبه سود پروژه — فقط وقتی مدیر با رمز عبور جدا احراز شده باشد صدا زده می‌شود */
function gv_sr_render_project_profit_box( $project ) {
	$income_rows = gv_sr_get_project_income_rows( $project->id );
	$profit      = gv_sr_project_profit( $project->id );
	?>
	<div class="gvsr-report-card">
		<h3>💰 محاسبه سود پروژه (ویژه مدیریت)</h3>
		<div class="gvsr-kpi-grid" style="grid-template-columns:repeat(3,1fr);">
			<div class="gvsr-kpi"><b style="color:#059669;"><?php echo esc_html( gv_sr_fa_digits( number_format_i18n( $profit['income'] ) ) ); ?></b><span>مجموع درآمد پروژه (تومان)</span></div>
			<div class="gvsr-kpi"><b style="color:#b45309;"><?php echo esc_html( gv_sr_fa_digits( number_format_i18n( $profit['labor_cost'] ) ) ); ?></b><span>مجموع حقوق پرداختی (تومان) — <?php echo esc_html( gv_sr_fa_digits( $profit['labor_hours'] ) ); ?> ساعت</span></div>
			<div class="gvsr-kpi"><b style="color:<?php echo $profit['net_profit'] >= 0 ? '#059669' : '#dc2626'; ?>;"><?php echo esc_html( gv_sr_fa_digits( number_format_i18n( $profit['net_profit'] ) ) ); ?></b><span>سود خالص پروژه (تومان)</span></div>
		</div>

		<h4 style="font-size:13px;color:#1e293b;margin:18px 0 10px;">➕ ثبت درآمد جدید برای این پروژه</h4>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gvsr-grid-4" style="align-items:end;">
			<?php wp_nonce_field( GV_SR_PROJECTS_NONCE ); ?>
			<input type="hidden" name="action" value="gv_sr_save_project_income">
			<input type="hidden" name="project_id" value="<?php echo esc_attr( $project->id ); ?>">
			<label>مبلغ (تومان)
				<input type="number" min="0" step="1000" name="amount" required>
			</label>
			<label>تاریخ دریافت (شمسی)
				<?php echo gv_sr_jalali_select_fields( 'income_date', '' ); ?>
			</label>
			<label>توضیح
				<input type="text" name="note" placeholder="مثلاً: قسط دوم قرارداد">
			</label>
			<button type="submit" class="gvsr-btn-export">💾 ثبت درآمد</button>
		</form>

		<?php if ( ! empty( $income_rows ) ) : ?>
			<div class="gvsr-table-wrap" style="max-width:100%;margin-top:16px;">
				<table class="gvsr-table">
					<thead><tr><th>تاریخ</th><th>مبلغ (تومان)</th><th>توضیح</th><th>عملیات</th></tr></thead>
					<tbody>
					<?php foreach ( $income_rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( gv_sr_jalali_numeric( $r->income_date ) ); ?></td>
							<td><b style="color:#059669;"><?php echo esc_html( gv_sr_fa_digits( number_format_i18n( $r->amount ) ) ); ?></b></td>
							<td><?php echo esc_html( $r->note ?: '—' ); ?></td>
							<td>
								<a class="gvsr-danger" style="font-size:12px;font-weight:700;" onclick="return confirm('این ردیف درآمد حذف شود؟');" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gv_sr_delete_project_income&id=' . $r->id . '&project_id=' . $project->id ), GV_SR_PROJECTS_NONCE ) ); ?>">حذف</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
}