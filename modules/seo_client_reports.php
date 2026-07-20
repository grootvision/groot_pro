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
define( 'GV_SR_DB_VERSION', '1.0' );
define( 'GV_SR_PAGE_SLUG',  'gv-seo-reports' );

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
		'user_id'  => 0,
		'status'   => '',
		'search'   => '',
		'orderby'  => 'period_end',
		'order'    => 'DESC',
		'limit'    => 0,
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

/* ==========================================================================
   ۸) صفحه مدیریت — روتر تب‌ها
   ========================================================================== */
function gv_sr_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'list';

	echo '<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif;">';
	gv_sr_admin_styles();

	echo '<div class="gvsr-header">';
	echo '<div><h1>📑 گزارش عملکرد سئوی مشتری — Groot Vision</h1><span>ثبت گزارش دوره‌ای کار سئو برای هر مشتری و نمایش آن در پنل اختصاصی مشتری</span></div>';
	if ( 'list' !== $tab ) {
		echo '<a class="gvsr-btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG ) ) . '">← بازگشت به لیست گزارش‌ها</a>';
	} else {
		echo '<a class="gvsr-btn-export" href="' . esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG . '&tab=edit' ) ) . '">➕ ثبت گزارش جدید</a>';
	}
	echo '</div>';

	if ( isset( $_GET['saved'] ) ) { echo '<div class="gvsr-notice">گزارش با موفقیت ذخیره شد.</div>'; }
	if ( isset( $_GET['deleted'] ) ) { echo '<div class="gvsr-notice">گزارش حذف شد.</div>'; }

	if ( 'edit' === $tab ) {
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
		.gvsr-header{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,#065f46,#10b981);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;box-shadow:0 6px 20px rgba(0,0,0,.12);flex-wrap:wrap;gap:12px;}
		.gvsr-header h1{margin:0;font-size:20px;color:#fff;}
		.gvsr-header span{opacity:.9;font-size:12.5px;}
		.gvsr-btn-export{background:#fff;color:#065f46;font-weight:800;padding:10px 20px;border-radius:10px;text-decoration:none;font-size:13px;white-space:nowrap;border:0;cursor:pointer;}
		.gvsr-btn-ghost{background:rgba(255,255,255,.16);color:#fff;font-weight:700;padding:9px 16px;border-radius:10px;text-decoration:none;font-size:12.5px;white-space:nowrap;border:1px solid rgba(255,255,255,.35);cursor:pointer;}
		.gvsr-notice{background:#dcfce7;color:#166534;border:1px solid #86efac;padding:10px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;max-width:1100px;}
		.gvsr-stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;max-width:1100px;}
		@media(max-width:900px){.gvsr-stat-cards{grid-template-columns:1fr 1fr;}}
		.gvsr-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px;}
		.gvsr-stat b{display:block;font-size:26px;color:#059669;}
		.gvsr-stat span{font-size:13px;color:#64748b;}
		.gvsr-filter-bar{display:flex;gap:10px;margin-bottom:16px;max-width:1100px;flex-wrap:wrap;}
		.gvsr-filter-bar input[type="text"]{flex:1;min-width:200px;padding:9px 12px;border:1px solid #dcdcde;border-radius:8px;font-family:inherit;}
		.gvsr-filter-bar select{padding:9px 12px;border:1px solid #dcdcde;border-radius:8px;font-family:inherit;}
		.gvsr-table-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;max-width:1100px;overflow-x:auto;}
		.gvsr-table{width:100%;border-collapse:collapse;}
		.gvsr-table th{background:#d1fae5;color:#065f46;padding:10px 14px;text-align:right;font-size:12.5px;white-space:nowrap;cursor:pointer;}
		.gvsr-table td{padding:9px 14px;border-top:1px solid #f1f5f9;font-size:12.5px;white-space:nowrap;}
		.gvsr-row-actions a{margin-inline-start:10px;text-decoration:none;font-size:12px;color:#059669;font-weight:700;}
		.gvsr-row-actions a.gvsr-danger{color:#dc2626;}
		.gvsr-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
		.gvsr-badge-green{background:#dcfce7;color:#166534;}
		.gvsr-badge-gray{background:#f1f5f9;color:#64748b;}
		.gvsr-cs-none{color:#cbd5e1;font-size:11px;}
		.gvsr-empty{padding:30px;text-align:center;color:#94a3b8;font-size:13px;}
		.gvsr-hint{font-size:11px;color:#94a3b8;margin:6px 0 10px;}
		.gvsr-hint-inline{font-size:11.5px;color:#94a3b8;}
		.gvsr-preview-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;}
		.gvsr-preview-tools .gvsr-btn-ghost{background:#f1f5f9;color:#065f46;border:1px solid #d1fae5;}

		.gvsr-form{max-width:1100px;}
		.gvsr-box{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;margin-bottom:18px;}
		.gvsr-box h2{font-size:15px;margin:0 0 14px;color:#1e293b;}
		.gvsr-form label{display:block;font-size:12.5px;color:#475569;font-weight:700;margin-bottom:12px;}
		.gvsr-form input[type="text"],.gvsr-form input[type="url"],.gvsr-form input[type="number"],.gvsr-form textarea,.gvsr-form select.gvsr-select{
			width:100%;box-sizing:border-box;margin-top:5px;padding:8px 10px;border:1px solid #dcdcde;border-radius:8px;font-family:inherit;font-size:12.5px;font-weight:400;color:#1e293b;
		}
		.gvsr-form textarea{resize:vertical;}
		.gvsr-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
		.gvsr-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
		@media(max-width:800px){.gvsr-grid-2,.gvsr-grid-4{grid-template-columns:1fr;}}

		.gvsr-repeater{width:100%;border-collapse:collapse;margin-bottom:10px;}
		.gvsr-repeater th{background:#f8fafc;font-size:11px;color:#64748b;padding:6px 8px;text-align:right;}
		.gvsr-repeater td{padding:5px 6px;border-top:1px solid #f1f5f9;vertical-align:middle;}
		.gvsr-repeater input,.gvsr-repeater select{width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;font-family:inherit;font-size:11.5px;}
		.gvsr-jdate-group{display:flex;gap:3px;}
		.gvsr-jdate-group select{min-width:0;}
		.gvsr-row-del{background:#fee2e2;color:#b91c1c;border:0;border-radius:6px;width:26px;height:26px;cursor:pointer;font-size:12px;}
		.gvsr-btn-add{background:#ecfdf5;color:#065f46;border:1px dashed #10b981;border-radius:8px;padding:8px 16px;font-size:12.5px;font-weight:700;cursor:pointer;}
		.gvsr-vis-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
		@media(max-width:700px){.gvsr-vis-grid{grid-template-columns:1fr;}}
		.gvsr-vis-item{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #eef0f2;border-radius:8px;padding:10px 12px;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:0;}
		.gvsr-vis-item input{width:auto!important;margin:0!important;}
		.gvsr-form-actions{display:flex;gap:10px;}

		/* پیش‌نمایش گزارش (مشترک بین ادمین و مشتری) */
		.gvsr-report-view{max-width:1100px;}
		.gvsr-hidden-flag{background:#fee2e2;color:#b91c1c;font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:20px;margin-inline-start:8px;}
		.gvsr-report-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;margin-bottom:18px;}
		.gvsr-report-card h3{margin:0 0 12px;font-size:14.5px;color:#1e293b;display:flex;align-items:center;}
		.gvsr-kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:18px;}
		@media(max-width:900px){.gvsr-kpi-grid{grid-template-columns:repeat(2,1fr);}}
		.gvsr-kpi{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px 18px;text-align:center;}
		.gvsr-kpi b{display:block;font-size:22px;color:#059669;}
		.gvsr-kpi span{font-size:11.5px;color:#64748b;}
		.gvsr-period-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:18px;}
		@media(max-width:900px){.gvsr-period-grid{grid-template-columns:repeat(3,1fr);}}
		.gvsr-period-item{background:#ecfdf5;border:1px solid #d1fae5;border-radius:12px;padding:12px 8px;text-align:center;}
		.gvsr-period-item b{display:block;font-size:20px;color:#065f46;}
		.gvsr-period-item span{font-size:10.5px;color:#0f766e;}
		.gvsr-chart-empty{padding:30px;text-align:center;color:#94a3b8;font-size:12.5px;}
		.gvsr-svg-chart{width:100%;height:auto;font-family:inherit;}
		.gvsr-delta{font-weight:700;white-space:nowrap;}
		.gvsr-summary-text{white-space:pre-wrap;line-height:2;font-size:13px;color:#334155;background:#f8fafc;border-radius:10px;padding:14px 16px;}
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
