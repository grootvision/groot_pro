<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — ورود و ثبت‌نام با ایمیل یا موبایل + رمز ثابت
 * ----------------------------------------------------------
 *  به‌جای کد یکبارمصرف پیامکی، هر کاربر یک رمز عبور ثابت
 *  انتخاب می‌کند و می‌تواند با «ایمیل + رمز» یا «موبایل + رمز»
 *  وارد شود. کاملاً با فرم‌های پیش‌فرض وردپرس و فرم ثبت‌نام/ورود
 *  ووکامرس (که قالب‌های وودمارت و بی‌تم هم از همان استفاده
 *  می‌کنند) سازگار است، چون در سطح هسته (هوک authenticate)
 *  عمل می‌کند و به AJAX داخلی قالب دست نمی‌زند.
 * ==========================================================
 */

define( 'GV_MLOGIN_OPT', 'gv_mobile_login_settings' );
define( 'GV_MLOGIN_NONCE', 'gv_mlogin_nonce_action' );
define( 'GV_MLOGIN_META_KEY', 'billing_phone' ); // از همان متای شماره‌تلفن ووکامرس استفاده می‌کنیم تا با آدرس‌های ثبت‌شده هم‌خوان بماند

function gv_mlogin_default_settings() {
	return array(
		'enabled'            => 0,
		'require_mobile'     => 1, // آیا فیلد موبایل در فرم ثبت‌نام اجباری باشد
		'allow_email_too'    => 1, // آیا اجازه بدهیم کاربر با ایمیل هم ثبت‌نام کند
		'default_country'    => '98',
	);
}
function gv_mlogin_get_settings() {
	return wp_parse_args( get_option( GV_MLOGIN_OPT, array() ), gv_mlogin_default_settings() );
}

/* ==========================================================
   منوی مدیریت (زیرمجموعه‌ی همان هاب گروت ویژن)
   ========================================================== */
add_action( 'admin_menu', 'gv_mlogin_admin_menu' );
function gv_mlogin_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'ورود با موبایل | Groot Vision',
		'📱 ورود با موبایل',
		'manage_options',
		'gv-mobile-login',
		'gv_mlogin_render_admin_page'
	);
}

add_action( 'admin_post_gv_mlogin_save_settings', 'gv_mlogin_save_settings' );
function gv_mlogin_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_MLOGIN_NONCE );

	$settings = array(
		'enabled'         => isset( $_POST['enabled'] ) ? 1 : 0,
		'require_mobile'  => isset( $_POST['require_mobile'] ) ? 1 : 0,
		'allow_email_too' => isset( $_POST['allow_email_too'] ) ? 1 : 0,
		'default_country' => preg_replace( '/[^0-9]/', '', sanitize_text_field( $_POST['default_country'] ?? '98' ) ) ?: '98',
	);
	update_option( GV_MLOGIN_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=gv-mobile-login&updated=1' ) );
	exit;
}

function gv_mlogin_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = gv_mlogin_get_settings();

	// چند شماره‌ی اخیر برای نمونه (کمک به دیباگ سریع)
	global $wpdb;
	$sample = $wpdb->get_results(
		"SELECT u.ID, u.user_login, u.user_email, um.meta_value AS phone
		 FROM {$wpdb->users} u
		 LEFT JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = '" . esc_sql( GV_MLOGIN_META_KEY ) . "'
		 ORDER BY u.ID DESC LIMIT 8"
	);
	?>
	<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif; max-width:980px;">
		<style>
			.gvml-header{background:linear-gradient(120deg,#0e4037,#145c4d);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;}
			.gvml-header h1{margin:0;font-size:20px;color:#fff;}
			.gvml-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin-bottom:18px;}
			.gvml-card h2{margin-top:0;font-size:15px;}
			.gvml-field{margin-bottom:14px;display:flex;align-items:center;gap:8px;}
			.gvml-field label{font-weight:700;font-size:13px;color:#334155;}
			.gvml-btn{background:#111827;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;}
			table.gvml-table{width:100%;border-collapse:collapse;font-size:12.5px;}
			table.gvml-table th, table.gvml-table td{border:1px solid #e5e7eb;padding:8px 10px;text-align:right;}
			table.gvml-table th{background:#f8fafc;}
			.gvml-note{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;font-size:12.5px;color:#92400e;}
		</style>

		<div class="gvml-header"><h1>📱 ورود و ثبت‌نام با ایمیل یا موبایل (رمز ثابت)</h1></div>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
		<?php endif; ?>

		<div class="gvml-note">
			با فعال‌سازی این ماژول: ۱) فیلد «شماره موبایل» به فرم ثبت‌نام حساب کاربری ووکامرس (و فرم پیش‌فرض ثبت‌نام وردپرس) اضافه می‌شود.
			۲) هر کاربر با ایمیل یا موبایل + رمز عبور دلخواه خودش ثبت‌نام می‌کند (بدون کد پیامکی).
			۳) در فرم ورود، کاربر می‌تواند به‌جای نام کاربری/ایمیل، شماره موبایلش را وارد کند.
			چون این ماژول در سطح هسته‌ی وردپرس (authenticate) کار می‌کند، با قالب‌های <strong>وودمارت</strong> و <strong>بی‌تم</strong> و فرم ورود/ثبت‌نام AJAX آن‌ها هم سازگار است.
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gv_mlogin_save_settings">
			<?php wp_nonce_field( GV_MLOGIN_NONCE ); ?>

			<div class="gvml-card">
				<div class="gvml-field"><label><input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>> فعال‌سازی ورود/ثبت‌نام با موبایل و ایمیل</label></div>
				<div class="gvml-field"><label><input type="checkbox" name="require_mobile" <?php checked( $s['require_mobile'], 1 ); ?>> اجباری‌کردن وارد کردن شماره موبایل در ثبت‌نام</label></div>
				<div class="gvml-field"><label><input type="checkbox" name="allow_email_too" <?php checked( $s['allow_email_too'], 1 ); ?>> اجازه‌ی ثبت‌نام با ایمیل هم داده شود (در کنار موبایل)</label></div>
				<div class="gvml-field"><label>کد کشور پیش‌فرض برای نرمال‌سازی شماره‌ها</label>
					<input type="text" name="default_country" value="<?php echo esc_attr( $s['default_country'] ); ?>" style="width:80px;" placeholder="98">
				</div>
			</div>

			<button type="submit" class="gvml-btn">💾 ذخیره تنظیمات</button>
		</form>

		<div class="gvml-card">
			<h2>آخرین کاربران (برای بررسی سریع اینکه شماره موبایل ذخیره می‌شود)</h2>
			<table class="gvml-table">
				<tr><th>شناسه</th><th>نام کاربری</th><th>ایمیل</th><th>موبایل ثبت‌شده</th></tr>
				<?php if ( $sample ) : foreach ( $sample as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->ID ); ?></td>
						<td><?php echo esc_html( $row->user_login ); ?></td>
						<td><?php echo esc_html( $row->user_email ); ?></td>
						<td><?php echo esc_html( $row->phone ? $row->phone : '—' ); ?></td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="4">کاربری یافت نشد.</td></tr>
				<?php endif; ?>
			</table>
		</div>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p>
	</div>
	<?php
}

/* ==========================================================
   ابزار کمکی: نرمال‌سازی شماره موبایل
   ورودی‌های مختلف (۰۹۱۲..., 09121234567, +989121234567,
   989121234567, ارقام فارسی) را به فرمت یکسان 09xxxxxxxxx برمی‌گرداند.
   ========================================================== */
function gv_mlogin_normalize_mobile( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) { return ''; }

	// تبدیل ارقام فارسی/عربی به انگلیسی
	$fa = array( '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' );
	$ar = array( '٠','١','٢','٣','٤','٥','٦','٧','٨','٩' );
	$en = array( '0','1','2','3','4','5','6','7','8','9' );
	$raw = str_replace( $fa, $en, $raw );
	$raw = str_replace( $ar, $en, $raw );

	// حذف هرچیزی غیر از رقم و +
	$raw = preg_replace( '/[^0-9+]/', '', $raw );

	$s = gv_mlogin_get_settings();
	$cc = preg_replace( '/[^0-9]/', '', $s['default_country'] ?: '98' );

	if ( 0 === strpos( $raw, '+' . $cc ) ) {
		$raw = '0' . substr( $raw, strlen( '+' . $cc ) );
	} elseif ( 0 === strpos( $raw, $cc ) && strlen( $raw ) > 10 ) {
		$raw = '0' . substr( $raw, strlen( $cc ) );
	} elseif ( 0 === strpos( $raw, '00' . $cc ) ) {
		$raw = '0' . substr( $raw, strlen( '00' . $cc ) );
	} elseif ( 1 === strlen( $raw ) - strlen( ltrim( $raw, '9' ) ) && 10 === strlen( $raw ) ) {
		// چیزی مثل 9121234567 بدون صفر ابتدایی
		$raw = '0' . $raw;
	}

	return $raw;
}

function gv_mlogin_is_valid_mobile( $normalized ) {
	// فرمت ایران: 11 رقم، با 0 شروع می‌شود. برای کشورهای دیگر در صورت نیاز این تابع را تغییر دهید.
	return (bool) preg_match( '/^0[0-9]{10}$/', $normalized );
}

/* ==========================================================
   افزودن فیلد موبایل به فرم ثبت‌نام حساب کاربری ووکامرس
   (همان فرمی که قالب‌های وودمارت/بی‌تم روی صفحه‌ی «حساب کاربری من» نشان می‌دهند)
   ========================================================== */
add_action( 'woocommerce_register_form', 'gv_mlogin_add_field_to_wc_register' );
function gv_mlogin_add_field_to_wc_register() {
	$s = gv_mlogin_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }
	?>
	<p class="form-row form-row-wide">
		<label for="gv_mobile_number"><?php echo esc_html( $s['require_mobile'] ? 'شماره موبایل *' : 'شماره موبایل (اختیاری)' ); ?></label>
		<input type="tel" class="input-text" name="gv_mobile_number" id="gv_mobile_number"
			   value="<?php echo isset( $_POST['gv_mobile_number'] ) ? esc_attr( wp_unslash( $_POST['gv_mobile_number'] ) ) : ''; ?>"
			   placeholder="09xxxxxxxxx" autocomplete="tel" />
	</p>
	<?php
}

add_action( 'register_form', 'gv_mlogin_add_field_to_wp_register' );
function gv_mlogin_add_field_to_wp_register() {
	$s = gv_mlogin_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }
	?>
	<p>
		<label for="gv_mobile_number"><?php echo esc_html( $s['require_mobile'] ? 'شماره موبایل *' : 'شماره موبایل (اختیاری)' ); ?><br>
		<input type="tel" name="gv_mobile_number" id="gv_mobile_number" class="input"
			   value="<?php echo isset( $_POST['gv_mobile_number'] ) ? esc_attr( wp_unslash( $_POST['gv_mobile_number'] ) ) : ''; ?>"
			   size="25" placeholder="09xxxxxxxxx" /></label>
	</p>
	<?php
}

/* ==========================================================
   اعتبارسنجی هنگام ثبت‌نام (هم ووکامرس، هم فرم پیش‌فرض وردپرس)
   ========================================================== */
add_filter( 'woocommerce_registration_errors', 'gv_mlogin_validate_wc_registration', 10, 3 );
function gv_mlogin_validate_wc_registration( $errors, $username, $email ) {
	$s = gv_mlogin_get_settings();
	if ( empty( $s['enabled'] ) ) { return $errors; }
	return gv_mlogin_validate_mobile_common( $errors );
}

add_filter( 'registration_errors', 'gv_mlogin_validate_wp_registration', 10, 3 );
function gv_mlogin_validate_wp_registration( $errors, $sanitized_user_login, $user_email ) {
	$s = gv_mlogin_get_settings();
	if ( empty( $s['enabled'] ) ) { return $errors; }
	return gv_mlogin_validate_mobile_common( $errors );
}

function gv_mlogin_validate_mobile_common( $errors ) {
	$s = gv_mlogin_get_settings();
	$mobile_raw = isset( $_POST['gv_mobile_number'] ) ? wp_unslash( $_POST['gv_mobile_number'] ) : '';
	$mobile     = gv_mlogin_normalize_mobile( $mobile_raw );

	if ( '' === $mobile ) {
		if ( ! empty( $s['require_mobile'] ) ) {
			$errors->add( 'gv_mobile_required', 'لطفاً شماره موبایل خود را وارد کنید.' );
		}
		return $errors;
	}

	if ( ! gv_mlogin_is_valid_mobile( $mobile ) ) {
		$errors->add( 'gv_mobile_invalid', 'شماره موبایل واردشده معتبر نیست.' );
		return $errors;
	}

	$existing = gv_mlogin_find_user_by_mobile( $mobile );
	if ( $existing ) {
		$errors->add( 'gv_mobile_taken', 'کاربری با این شماره موبایل قبلاً ثبت‌نام کرده است.' );
	}

	return $errors;
}

/* ذخیره‌ی موبایل بعد از ساخته‌شدن کاربر */
add_action( 'woocommerce_created_customer', 'gv_mlogin_save_mobile_after_wc_register', 10, 1 );
function gv_mlogin_save_mobile_after_wc_register( $customer_id ) {
	gv_mlogin_save_submitted_mobile( $customer_id );
}
add_action( 'user_register', 'gv_mlogin_save_mobile_after_wp_register', 10, 1 );
function gv_mlogin_save_mobile_after_wp_register( $user_id ) {
	gv_mlogin_save_submitted_mobile( $user_id );
}
function gv_mlogin_save_submitted_mobile( $user_id ) {
	if ( ! isset( $_POST['gv_mobile_number'] ) ) { return; }
	$mobile = gv_mlogin_normalize_mobile( wp_unslash( $_POST['gv_mobile_number'] ) );
	if ( $mobile && gv_mlogin_is_valid_mobile( $mobile ) ) {
		update_user_meta( $user_id, GV_MLOGIN_META_KEY, $mobile );
	}
}

/* ==========================================================
   پیدا کردن کاربر بر اساس شماره موبایل (متای billing_phone)
   ========================================================== */
function gv_mlogin_find_user_by_mobile( $normalized_mobile ) {
	if ( ! $normalized_mobile ) { return null; }
	$users = get_users( array(
		'meta_key'   => GV_MLOGIN_META_KEY,
		'meta_value' => $normalized_mobile,
		'number'     => 1,
		'fields'     => 'all',
	) );
	if ( ! empty( $users ) ) { return $users[0]; }

	// اگر فرمت ذخیره‌شده دقیقاً یکی نبود (مثلاً بدون صفر اول)، یک تلاش اضافه
	$alt = ltrim( $normalized_mobile, '0' );
	if ( $alt && $alt !== $normalized_mobile ) {
		$users = get_users( array(
			'meta_key'   => GV_MLOGIN_META_KEY,
			'meta_value' => $alt,
			'number'     => 1,
			'fields'     => 'all',
		) );
		if ( ! empty( $users ) ) { return $users[0]; }
	}
	return null;
}

/* ==========================================================
   هسته‌ی ورود: اگر مقدار واردشده شماره موبایل بود، آن را به
   نام‌کاربری واقعی کاربر تبدیل می‌کنیم و اجازه می‌دهیم زنجیره‌ی
   استاندارد احراز هویت وردپرس (بررسی رمز عبور) ادامه پیدا کند.
   این کار در سطح هسته انجام می‌شود، پس هر فرم ورودی (پیش‌فرض
   وردپرس، ووکامرس، فرم AJAX وودمارت/بی‌تم) که در نهایت
   wp_signon()/wp_authenticate() را صدا بزند، پشتیبانی می‌شود.
   ========================================================== */
add_filter( 'authenticate', 'gv_mlogin_authenticate_by_mobile', 19, 3 );
function gv_mlogin_authenticate_by_mobile( $user, $username, $password ) {
	$s = gv_mlogin_get_settings();
	if ( empty( $s['enabled'] ) || empty( $username ) ) { return $user; }

	// اگر از قبل با ایمیل/نام‌کاربری معتبر پیدا شده، دست نمی‌زنیم
	if ( is_a( $user, 'WP_User' ) ) { return $user; }

	// فقط وقتی ورودی شبیه شماره موبایل است (نه ایمیل، نه نام‌کاربری معمولی) دخالت می‌کنیم
	$normalized = gv_mlogin_normalize_mobile( $username );
	if ( ! gv_mlogin_is_valid_mobile( $normalized ) ) { return $user; }

	$found = gv_mlogin_find_user_by_mobile( $normalized );
	if ( ! $found ) {
		return new WP_Error( 'gv_mobile_not_found', 'کاربری با این شماره موبایل پیدا نشد.' );
	}

	// نام‌کاربری واقعی را جایگزین می‌کنیم و اجازه می‌دهیم فیلترهای بعدی
	// (بررسی رمز عبور استاندارد وردپرس) کار خودشان را انجام دهند.
	return wp_authenticate_username_password( null, $found->user_login, $password );
}

/* راهنمای متن فیلد نام‌کاربری در صفحه‌ی ورود */
add_filter( 'gettext', 'gv_mlogin_tweak_login_label', 20, 3 );
function gv_mlogin_tweak_login_label( $translated, $original, $domain ) {
	$s = gv_mlogin_get_settings();
	if ( empty( $s['enabled'] ) ) { return $translated; }
	if ( 'Username or Email Address' === $original ) {
		return 'نام کاربری، ایمیل یا شماره موبایل';
	}
	return $translated;
}
