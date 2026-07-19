<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — تاریخ خودکار شمسی در انتهای «عنوان سئو»
 *  توجه: این نسخه فقط تگ <title> صفحه (عنوانی که در نتایج
 *  گوگل و تب مرورگر دیده می‌شود) را تغییر می‌دهد. عنوان
 *  واقعی نوشته/برگه (H1، ویجت‌ها، آرشیوها و ...) هیچ تغییری
 *  نمی‌کند و دست‌نخورده باقی می‌ماند.
 *  هر روز که کاربر صفحه را در گوگل ببیند، تاریخ شمسی امروز
 *  به‌صورت خودکار در انتهای عنوان سئو درج می‌شود.
 * ==========================================================
 */
define( 'GV_JDATE_OPT', 'gv_jalali_date_settings' );
define( 'GV_JDATE_NONCE', 'gv_jdate_nonce_action' );

function gv_jdate_default_settings() {
	return array(
		'enabled'          => 0,
		'post_types'       => array( 'post' => 1, 'page' => 0, 'product' => 0 ),
		'separator'        => ' - ',
		'context'          => 'single', // single | archive_and_single
		// --- تنظیمات تازگی محتوا (freshness) ---
		'freshness_enabled' => 0,       // فعال/غیرفعال کردن schema + بج نمایشی
		'freshness_mode'     => 'real', // real = تاریخ واقعی آخرین ویرایش | today = همیشه امروز (پرریسک)
		'freshness_badge'    => 0,      // نمایش بج تاریخ بالای محتوا برای کاربر
	);
}

function gv_jdate_get_settings() {
	$saved = get_option( GV_JDATE_OPT, array() );
	$defaults = gv_jdate_default_settings();
	$settings = wp_parse_args( $saved, $defaults );
	if ( isset( $saved['post_types'] ) && is_array( $saved['post_types'] ) ) {
		$settings['post_types'] = wp_parse_args( $saved['post_types'], $defaults['post_types'] );
	}
	return $settings;
}

/* ==========================================================================
   ۱) تبدیل تاریخ میلادی به شمسی (بدون نیاز به هیچ کتابخانه خارجی)
   ========================================================================== */

function gv_jdate_gregorian_to_jalali( $g_y, $g_m, $g_d ) {
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
	$j_np = intdiv( $j_day_no, 12053 );
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

function gv_jdate_month_name( $m ) {
	$names = array(
		1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
		5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
		9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
	);
	return $names[ $m ] ?? '';
}

function gv_jdate_to_persian_digits( $str ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	return preg_replace_callback( '/[0-9]/', function ( $m ) use ( $fa ) { return $fa[ $m[0] ]; }, (string) $str );
}

/** رشته امروز به شمسی، مثل «۲۵ تیر» یا با سال اگر لازم شود «۲۵ تیر ۱۴۰۳» */
function gv_jdate_today_string( $with_year = false ) {
	$now = current_time( 'timestamp' ); // زمان محلی سایت
	list( $jy, $jm, $jd ) = gv_jdate_gregorian_to_jalali( (int) date( 'Y', $now ), (int) date( 'n', $now ), (int) date( 'j', $now ) );
	$str = gv_jdate_to_persian_digits( $jd ) . ' ' . gv_jdate_month_name( $jm );
	if ( $with_year ) { $str .= ' ' . gv_jdate_to_persian_digits( $jy ); }
	return $str;
}

/* ==========================================================================
   ۲) منوی مدیریت
   ========================================================================== */

add_action( 'admin_menu', 'gv_jdate_admin_menu' );
function gv_jdate_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'تاریخ خودکار شمسی | Groot Vision',
		'📅 تاریخ خودکار',
		'manage_options',
		'gv-jalali-date',
		'gv_jdate_render_admin_page'
	);
}

add_action( 'admin_post_gv_jdate_save_settings', 'gv_jdate_save_settings' );
function gv_jdate_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_JDATE_NONCE );

	$defaults = gv_jdate_default_settings();
	$post_types = array();
	foreach ( array_keys( $defaults['post_types'] ) as $pt ) {
		$post_types[ $pt ] = isset( $_POST['post_types'][ $pt ] ) ? 1 : 0;
	}

	$sep = sanitize_text_field( wp_unslash( $_POST['separator'] ?? ' - ' ) );
	if ( '' === trim( $sep ) ) { $sep = ' - '; }

	$settings = array(
		'enabled'           => isset( $_POST['enabled'] ) ? 1 : 0,
		'post_types'        => $post_types,
		'separator'         => $sep,
		'context'           => in_array( $_POST['context'] ?? '', array( 'single', 'archive_and_single' ), true ) ? $_POST['context'] : 'single',
		'freshness_enabled' => isset( $_POST['freshness_enabled'] ) ? 1 : 0,
		'freshness_mode'    => in_array( $_POST['freshness_mode'] ?? '', array( 'real', 'today' ), true ) ? $_POST['freshness_mode'] : 'real',
		'freshness_badge'   => isset( $_POST['freshness_badge'] ) ? 1 : 0,
	);
	update_option( GV_JDATE_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=gv-jalali-date&updated=1' ) );
	exit;
}

function gv_jdate_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = gv_jdate_get_settings();
	$post_type_objects = get_post_types( array( 'public' => true ), 'objects' );
	?>
	<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif; max-width:900px;">
		<style>
			.gvjd-header{background:linear-gradient(120deg,#0e4037,#145c4d);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;}
			.gvjd-header h1{margin:0;font-size:20px;color:#fff;}
			.gvjd-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin-bottom:18px;}
			.gvjd-card h2{margin-top:0;font-size:15px;}
			.gvjd-field{margin-bottom:14px;}
			.gvjd-field label{display:block;font-weight:700;font-size:13px;margin-bottom:5px;color:#334155;}
			.gvjd-field input[type=text]{width:100%;max-width:260px;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;}
			.gvjd-btn{background:#111827;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;}
			.gvjd-preview{background:#f0fdf9;border:1px dashed #0e4037;border-radius:12px;padding:16px;font-size:15px;font-weight:700;color:#0e4037;}
			.gvjd-toggle-row{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
			.gvjd-note{background:#fff7ed;border:1px solid #fdba74;border-radius:10px;padding:12px 16px;font-size:12.5px;color:#9a3412;margin-bottom:18px;}
		</style>

		<div class="gvjd-header"><h1>📅 تاریخ خودکار شمسی روی عنوان سئو (تگ Title)</h1></div>

		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div><?php endif; ?>

		<div class="gvjd-note">
			⚠️ این نسخه فقط <strong>عنوان سئو (تگ &lt;title&gt; و نتایج گوگل)</strong> را تغییر می‌دهد.
			عنوان نمایشی نوشته در خود سایت (H1، لیست مطالب، ویجت‌ها) دست‌نخورده باقی می‌ماند.
		</div>

		<div class="gvjd-card">
			<h2>پیش‌نمایش امروز</h2>
			<div class="gvjd-preview">مثال در گوگل: «عنوان نوشته شما<?php echo esc_html( $s['separator'] ); ?><?php echo esc_html( gv_jdate_today_string() ); ?>»</div>
			<p style="color:#94a3b8;font-size:12.5px;margin-top:10px;">این متن کاملاً خودکار است؛ نیازی به ویرایش نوشته‌ها نیست. فردا خودش به روز بعد تغییر می‌کند.</p>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gv_jdate_save_settings">
			<?php wp_nonce_field( GV_JDATE_NONCE ); ?>

			<div class="gvjd-card">
				<label style="font-weight:700;"><input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>> فعال‌سازی تاریخ خودکار شمسی روی عنوان سئو</label>
			</div>

			<div class="gvjd-card">
				<h2>روی چه نوع محتوایی اعمال شود؟</h2>
				<?php foreach ( array( 'post' => '📝 نوشته‌ها', 'page' => '📄 برگه‌ها', 'product' => '🛍️ محصولات (ووکامرس)' ) as $pt => $label ) :
					if ( 'product' === $pt && ! post_type_exists( 'product' ) ) { continue; }
					?>
					<div class="gvjd-toggle-row">
						<input type="checkbox" name="post_types[<?php echo esc_attr( $pt ); ?>]" <?php checked( ! empty( $s['post_types'][ $pt ] ), true ); ?>>
						<label style="margin:0;"><?php echo esc_html( $label ); ?></label>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="gvjd-card">
				<h2>محل نمایش</h2>
				<label class="gvjd-toggle-row"><input type="radio" name="context" value="single" <?php checked( $s['context'], 'single' ); ?>> فقط عنوان سئوی صفحه خود نوشته/برگه</label>
				<label class="gvjd-toggle-row"><input type="radio" name="context" value="archive_and_single" <?php checked( $s['context'], 'archive_and_single' ); ?>> هم صفحه نوشته و هم صفحات آرشیو/لیست مطالب</label>
			</div>

			<div class="gvjd-card">
				<h2>جداکننده بین عنوان و تاریخ</h2>
				<div class="gvjd-field">
					<input type="text" name="separator" value="<?php echo esc_attr( $s['separator'] ); ?>" placeholder=" - ">
				</div>
			</div>

			<div class="gvjd-card">
				<h2>🕓 تازگی محتوا برای گوگل (Structured Data / Yoast)</h2>
				<p style="font-size:12.5px;color:#64748b;margin-top:-6px;">
					این بخش تاریخ <code>dateModified</code> را در Schema سایت (که یوست تولید می‌کند) تنظیم می‌کند تا گوگل زیر نتیجه جست‌وجو تاریخ به‌روزرسانی را نشان دهد.
				</p>

				<label style="font-weight:700;display:block;margin-bottom:12px;">
					<input type="checkbox" name="freshness_enabled" <?php checked( $s['freshness_enabled'], 1 ); ?>>
					فعال‌سازی مدیریت تاریخ Schema
				</label>

				<div style="margin-bottom:10px;">
					<label class="gvjd-toggle-row">
						<input type="radio" name="freshness_mode" value="real" <?php checked( $s['freshness_mode'], 'real' ); ?>>
						✅ حالت امن — تاریخ واقعیِ آخرین ویرایش نوشته (پیشنهاد می‌شود)
					</label>
					<label class="gvjd-toggle-row">
						<input type="radio" name="freshness_mode" value="today" <?php checked( $s['freshness_mode'], 'today' ); ?>>
						⚠️ حالت مصنوعی — همیشه تاریخ امروز (ریسک برای سئو، گوگل ممکن است این را نشانه فریب تشخیص دهد)
					</label>
				</div>

				<label style="font-weight:700;display:block;">
					<input type="checkbox" name="freshness_badge" <?php checked( $s['freshness_badge'], 1 ); ?>>
					نمایش بج «به‌روزرسانی: ...» بالای محتوای نوشته برای کاربر
				</label>
			</div>

			<button type="submit" class="gvjd-btn">💾 ذخیره تنظیمات</button>
		</form>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p>
	</div>
	<?php
}

/* ==========================================================================
   ۳) اعمال روی «عنوان سئو» — نه روی عنوان نمایشی نوشته
   با هوک به فیلترهای تگ <title> (وردپرس هسته + Yoast + Rank Math + AIOSEO)
   ========================================================================== */

/**
 * تشخیص می‌دهد که آیا در این درخواست باید تاریخ اضافه شود یا نه،
 * و در صورت نیاز رشتهٔ نهایی عنوان را برمی‌گرداند.
 */
function gv_jdate_maybe_append_seo_date( $title ) {
	if ( is_admin() || is_feed() ) { return $title; }

	$s = gv_jdate_get_settings();
	if ( empty( $s['enabled'] ) ) { return $title; }

	$apply = false;

	if ( is_singular() ) {
		$post_type = get_post_type( get_queried_object_id() );
		if ( $post_type && ! empty( $s['post_types'][ $post_type ] ) ) {
			$apply = true;
		}
	} elseif ( 'archive_and_single' === $s['context'] ) {
		// فقط زمانی که آرشیو مربوط به یکی از پست‌تایپ‌های انتخاب‌شده باشد
		if ( is_post_type_archive() ) {
			$archive_pt = get_query_var( 'post_type' );
			if ( is_array( $archive_pt ) ) { $archive_pt = reset( $archive_pt ); }
			if ( $archive_pt && ! empty( $s['post_types'][ $archive_pt ] ) ) { $apply = true; }
		} elseif ( ( is_home() || is_category() || is_tag() || is_date() || is_author() ) && ! empty( $s['post_types']['post'] ) ) {
			$apply = true;
		}
	}

	if ( ! $apply ) { return $title; }

	$date_str = gv_jdate_today_string();
	// جلوگیری از دوباره‌کاری اگر پلاگین دیگری فیلتر را دوبار صدا زد
	if ( false !== strpos( $title, $date_str ) ) { return $title; }

	return $title . $s['separator'] . $date_str;
}

// عنوان استاندارد تگ <title> در وردپرس (زمانی که هیچ پلاگین سئویی جایگزینش نکرده باشد)
add_filter( 'pre_get_document_title', 'gv_jdate_maybe_append_seo_date', 20 );

// Yoast SEO
add_filter( 'wpseo_title', 'gv_jdate_maybe_append_seo_date', 20 );

// Rank Math
add_filter( 'rank_math/frontend/title', 'gv_jdate_maybe_append_seo_date', 20 );

// All in One SEO (نسخه ۴ به بعد)
add_filter( 'aioseo_title', 'gv_jdate_maybe_append_seo_date', 20 );

/* ==========================================================================
   ۴) تازگی محتوا (Freshness) — تاریخ dateModified در Schema یوست
   + بج نمایشی «به‌روزرسانی: ...» بالای محتوا برای کاربر
   ========================================================================== */

/** آیا freshness برای این پست فعال است؟ */
function gv_jdate_freshness_applies( $post_id ) {
	$s = gv_jdate_get_settings();
	if ( empty( $s['freshness_enabled'] ) ) { return false; }
	if ( ! is_singular() ) { return false; }
	$post_type = get_post_type( $post_id );
	return $post_type && ! empty( $s['post_types'][ $post_type ] );
}

/** تایم‌استمپ مبنا برای freshness، بر اساس حالت انتخاب‌شده در تنظیمات */
function gv_jdate_freshness_timestamp( $post_id ) {
	$s = gv_jdate_get_settings();
	if ( 'today' === $s['freshness_mode'] ) {
		return current_time( 'timestamp' );
	}
	// حالت امن: تاریخ واقعی آخرین ویرایش نوشته
	$modified = get_post_modified_time( 'U', false, $post_id );
	return $modified ? (int) $modified : current_time( 'timestamp' );
}

/** رشته ISO 8601 (برای schema) بر اساس تایم‌استمپ freshness */
function gv_jdate_freshness_iso8601( $post_id ) {
	$ts = gv_jdate_freshness_timestamp( $post_id );
	return date( 'c', $ts ); // 'c' شامل آفست تایم‌زون سرور است
}

/** رشته شمسی خوانا برای انسان، مثل «۲۸ تیر ۱۴۰۴» */
function gv_jdate_freshness_jalali_string( $post_id ) {
	$ts = gv_jdate_freshness_timestamp( $post_id );
	list( $jy, $jm, $jd ) = gv_jdate_gregorian_to_jalali( (int) date( 'Y', $ts ), (int) date( 'n', $ts ), (int) date( 'j', $ts ) );
	return gv_jdate_to_persian_digits( $jd ) . ' ' . gv_jdate_month_name( $jm ) . ' ' . gv_jdate_to_persian_digits( $jy );
}

/**
 * درج تاریخ در schema مقاله‌ی یوست (Article Schema).
 * این همان چیزی است که گوگل برای نمایش «X روز پیش» زیر نتیجه جست‌وجو می‌خواند.
 */
add_filter( 'wpseo_schema_article', 'gv_jdate_filter_schema_article' );
function gv_jdate_filter_schema_article( $data ) {
	$post_id = get_queried_object_id();
	if ( ! gv_jdate_freshness_applies( $post_id ) ) { return $data; }
	$data['dateModified'] = gv_jdate_freshness_iso8601( $post_id );
	return $data;
}

/** همان کار برای schema صفحه (WebPage) که یوست همیشه می‌سازد */
add_filter( 'wpseo_schema_webpage', 'gv_jdate_filter_schema_webpage' );
function gv_jdate_filter_schema_webpage( $data ) {
	$post_id = get_queried_object_id();
	if ( ! gv_jdate_freshness_applies( $post_id ) ) { return $data; }
	$data['dateModified'] = gv_jdate_freshness_iso8601( $post_id );
	return $data;
}

/**
 * اگر هیچ پلاگین سئویی نصب نباشد (نه یوست، نه رنک‌مث)، یک Schema ساده
 * از نوع Article خودمان چاپ می‌کنیم تا freshness باز هم کار کند.
 */
add_action( 'wp_head', 'gv_jdate_fallback_schema_output', 30 );
function gv_jdate_fallback_schema_output() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) { return; } // پلاگین سئو خودش schema می‌سازد
	$post_id = get_queried_object_id();
	if ( ! gv_jdate_freshness_applies( $post_id ) ) { return; }

	$schema = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'Article',
		'headline'      => get_the_title( $post_id ),
		'datePublished' => get_post_time( 'c', false, $post_id ),
		'dateModified'  => gv_jdate_freshness_iso8601( $post_id ),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
}

/**
 * بج نمایشی «به‌روزرسانی: ...» بالای محتوای نوشته — چیزی که کاربر واقعی
 * توی خود صفحه می‌بیند (جدا از عنوان و جدا از عنوان سئو).
 */
add_filter( 'the_content', 'gv_jdate_maybe_prepend_freshness_badge' );
function gv_jdate_maybe_prepend_freshness_badge( $content ) {
	if ( is_admin() || ! is_main_query() || ! in_the_loop() ) { return $content; }

	$s = gv_jdate_get_settings();
	if ( empty( $s['freshness_enabled'] ) || empty( $s['freshness_badge'] ) ) { return $content; }

	$post_id = get_the_ID();
	if ( ! gv_jdate_freshness_applies( $post_id ) ) { return $content; }

	$label = ( 'today' === $s['freshness_mode'] ) ? 'به‌روزرسانی' : 'آخرین به‌روزرسانی';
	$badge = '<div class="gvjd-freshness-badge" style="display:inline-block;background:#f0fdf9;border:1px solid #0e4037;color:#0e4037;border-radius:8px;padding:6px 14px;font-size:13px;font-weight:700;margin-bottom:16px;">'
		. '🕓 ' . esc_html( $label ) . ': ' . esc_html( gv_jdate_freshness_jalali_string( $post_id ) )
		. '</div>';

	return $badge . $content;
}