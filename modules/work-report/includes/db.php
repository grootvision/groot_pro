<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** اگر نسخه‌ی دیتابیس تغییر کرده، جدول‌ها و نقش‌ها را بساز/به‌روز کن. */
function gvwr_maybe_upgrade() {
	if ( get_option( 'gvwr_db_version' ) === GVWR_DB_VERSION ) {
		return;
	}
	gvwr_install();
	if ( function_exists( 'gvwr_legacy_housekeeping' ) ) {
		gvwr_legacy_housekeeping();
	}
	update_option( 'gvwr_db_version', GVWR_DB_VERSION );
}

function gvwr_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta( "CREATE TABLE " . gvwr_table( 'items' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  project_id bigint(20) unsigned NOT NULL DEFAULT 1,
  category_id bigint(20) unsigned NOT NULL,
  work_date date NOT NULL,
  description text NULL,
  url varchar(500) NOT NULL DEFAULT '',
  qty int(11) NOT NULL DEFAULT 1,
  start_time time NULL DEFAULT NULL,
  end_time time NULL DEFAULT NULL,
  duration_min int(11) NULL DEFAULT NULL,
  screenshots text NULL,
  period_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(10) NOT NULL DEFAULT 'pending',
  manager_note text NULL,
  batch varchar(32) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_date (user_id,work_date),
  KEY work_date (work_date),
  KEY category_id (category_id),
  KEY project_id (project_id),
  KEY period_id (period_id),
  KEY status (status)
) $c;" );

	dbDelta( "CREATE TABLE " . gvwr_table( 'categories' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL,
  grp varchar(60) NOT NULL DEFAULT '',
  icon varchar(16) NOT NULL DEFAULT '',
  color varchar(9) NOT NULL DEFAULT '#0f766e',
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id)
) $c;" );

	dbDelta( "CREATE TABLE " . gvwr_table( 'employees' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  job_title varchar(120) NOT NULL DEFAULT '',
  hourly_rate bigint(20) unsigned NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_id (user_id)
) $c;" );

	dbDelta( "CREATE TABLE " . gvwr_table( 'projects' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  is_default tinyint(1) NOT NULL DEFAULT 0,
  client_name varchar(190) NOT NULL DEFAULT '',
  client_user_id bigint(20) NOT NULL DEFAULT 0,
  manager_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'active',
  priority varchar(10) NOT NULL DEFAULT 'normal',
  health varchar(10) NOT NULL DEFAULT 'green',
  progress tinyint(3) unsigned NOT NULL DEFAULT 0,
  start_date date NULL DEFAULT NULL,
  end_date date NULL DEFAULT NULL,
  description text NULL,
  created_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id)
) $c;" );

	dbDelta( "CREATE TABLE " . gvwr_table( 'keywords' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  project_id bigint(20) unsigned NOT NULL DEFAULT 1,
  keyword varchar(180) NOT NULL,
  target_url varchar(500) NOT NULL DEFAULT '',
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY proj_kw (project_id,keyword)
) $c;" );

	dbDelta( "CREATE TABLE " . gvwr_table( 'ranks' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  keyword_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  rank_pos int(11) NOT NULL,
  rank_date date NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY kw_day (keyword_id,rank_date)
) $c;" );

	// اعضای پروژه
	dbDelta( "CREATE TABLE " . gvwr_table( 'project_members' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  project_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  role_label varchar(120) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY project_user (project_id,user_id),
  KEY user_id (user_id)
) $c;" );

	// درآمد پروژه (فقط برای مدیر کل وردپرس دیده می‌شود)
	dbDelta( "CREATE TABLE " . gvwr_table( 'project_income' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  project_id bigint(20) unsigned NOT NULL,
  amount bigint(20) NOT NULL DEFAULT 0,
  income_date date NOT NULL,
  note varchar(500) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY project_id (project_id)
) $c;" );

	// گزارش‌های دوره‌ای (ماهانه) با خلاصه، رشد صفحات و گام بعدی
	dbDelta( "CREATE TABLE " . gvwr_table( 'periods' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  project_id bigint(20) unsigned NOT NULL DEFAULT 0,
  client_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  client_name varchar(190) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  period_start date NOT NULL,
  period_end date NOT NULL,
  summary longtext NULL,
  next_steps longtext NULL,
  hours_spent decimal(8,2) NOT NULL DEFAULT 0,
  status varchar(12) NOT NULL DEFAULT 'published',
  visibility text NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY project_id (project_id),
  KEY client_user_id (client_user_id),
  KEY period_end (period_end)
) $c;" );

	dbDelta( "CREATE TABLE " . gvwr_table( 'period_keywords' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  period_id bigint(20) unsigned NOT NULL,
  keyword varchar(190) NOT NULL,
  search_engine varchar(50) NOT NULL DEFAULT '',
  page_url varchar(500) NOT NULL DEFAULT '',
  prev_rank int(11) NOT NULL DEFAULT 0,
  curr_rank int(11) NOT NULL DEFAULT 0,
  note varchar(500) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY period_id (period_id)
) $c;" );

	dbDelta( "CREATE TABLE " . gvwr_table( 'period_growth' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  period_id bigint(20) unsigned NOT NULL,
  page_title varchar(255) NOT NULL DEFAULT '',
  page_url varchar(500) NOT NULL DEFAULT '',
  metric_label varchar(120) NOT NULL DEFAULT '',
  before_value varchar(50) NOT NULL DEFAULT '',
  after_value varchar(50) NOT NULL DEFAULT '',
  note varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY period_id (period_id)
) $c;" );

	// نگاشت رکوردهای قدیمی به جدید (برای اینکه انتقال داده هر چند بار اجرا شود، چیزی تکراری نشود)
	dbDelta( "CREATE TABLE " . gvwr_table( 'legacy_map' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kind varchar(20) NOT NULL,
  old_key varchar(64) NOT NULL,
  new_id bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY kind_key (kind,old_key)
) $c;" );

	gvwr_seed();
	gvwr_roles_install();
}

function gvwr_seed() {
	global $wpdb;

	// پروژه‌ی پیش‌فرض = نام خود سایت
	$pt = gvwr_table( 'projects' );
	if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM $pt" ) ) {
		$wpdb->insert( $pt, array( 'id' => 1, 'name' => gvwr_site_name(), 'is_default' => 1 ) );
	}

	// دسته‌بندی‌های پیش‌فرض (طراح سایت + سئو)
	$ct = gvwr_table( 'categories' );
	if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM $ct" ) ) {
		$cats = array(
			array( 'محتوا', '✍️', 'تولید محتوای جدید', '#0f766e' ),
			array( 'محتوا', '🔄', 'بروزرسانی محتوا', '#0891b2' ),
			array( 'محتوا', '📢', 'ثبت آگهی', '#d97706' ),
			array( 'محتوا', '🎬', 'تولید ویدیو / پادکست', '#9333ea' ),
			array( 'سئو', '🔑', 'تحقیق کلمات کلیدی', '#2563eb' ),
			array( 'سئو', '🔎', 'سئو داخلی (On-Page)', '#1d4ed8' ),
			array( 'سئو', '🔗', 'لینک‌سازی داخلی', '#4f46e5' ),
			array( 'سئو', '🌐', 'بک‌لینک و لینک‌سازی خارجی', '#7c3aed' ),
			array( 'سئو', '📍', 'سئو محلی / گوگل مپ', '#be185d' ),
			array( 'سئو', '📈', 'بررسی و ثبت رتبه‌ها', '#059669' ),
			array( 'سئو', '📊', 'گزارش سرچ کنسول / آنالیتیکس', '#0d9488' ),
			array( 'طراحی', '🎨', 'طراحی و ساخت صفحه', '#db2777' ),
			array( 'طراحی', '🖌️', 'ویرایش ظاهر سایت', '#e11d48' ),
			array( 'طراحی', '📱', 'بهینه‌سازی موبایل / ریسپانسیو', '#ea580c' ),
			array( 'طراحی', '🖼️', 'طراحی گرافیک و بنر', '#f59e0b' ),
			array( 'فنی', '⚡', 'بهینه‌سازی سرعت', '#ca8a04' ),
			array( 'فنی', '🐞', 'رفع خطا و باگ', '#dc2626' ),
			array( 'فنی', '🛒', 'مدیریت محصولات / فروشگاه', '#65a30d' ),
			array( 'فنی', '💾', 'بکاپ و بروزرسانی افزونه‌ها', '#475569' ),
			array( 'فنی', '🔒', 'امنیت سایت', '#334155' ),
			array( 'سایر', '💬', 'پشتیبانی و ارتباط با مشتری', '#64748b' ),
			array( 'سایر', '🧩', 'سایر کارها', '#94a3b8' ),
		);
		foreach ( $cats as $i => $r ) {
			$wpdb->insert( $ct, array( 'grp' => $r[0], 'icon' => $r[1], 'name' => $r[2], 'color' => $r[3], 'sort_order' => $i, 'active' => 1 ) );
		}
	}

	// برگه‌ی کارفرما با شورت‌کد
	if ( ! gvwr_opt( 'client_page_id' ) || ! get_post( (int) gvwr_opt( 'client_page_id' ) ) ) {
		gvwr_create_client_page();
	}
}

function gvwr_create_client_page() {
	$id = wp_insert_post(
		array(
			'post_title'   => gvwr_opt( 'client_title' ),
			'post_name'    => 'work-report',
			'post_content' => '[groot_work_report]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		$o                   = get_option( 'gvwr_settings', array() );
		$o['client_page_id'] = (int) $id;
		update_option( 'gvwr_settings', $o );
		return (int) $id;
	}
	return 0;
}
