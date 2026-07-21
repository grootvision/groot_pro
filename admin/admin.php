<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — Hub / Dashboard (نسخه بازطراحی‌شده)
 *  این فایل باید همیشه با اولویت زودتر از سایر اسنیپت‌ها
 *  (نوار اعلان / کادر تبلیغاتی / پروگرس بار / اعلان خرید / امنیت و سرعت /
 *   استایل جدول‌ها / کلمات کلیدی سئو)
 *  لود شود تا منوی والد قبل از ثبت زیرمنوها ساخته شده باشد.
 * ==========================================================
 */

define( 'GV_HUB_SLUG', 'groot-vision-hub' );

/* ==========================================================
   ۱) ساخت منوی والد + آیتم داشبورد
   ========================================================== */
add_action( 'admin_menu', 'gv_hub_register_menu', 5 ); // اولویت ۵ = زودتر از بقیه (پیش‌فرض ۱۰)
function gv_hub_register_menu() {

	add_menu_page(
		'افزونه‌های ضروری گروت ویژن',
		'گروت ویژن پرو',
		'manage_options',
		GV_HUB_SLUG,
		'gv_hub_render_page',
		'dashicons-star-filled',
		58
	);

	// آیتم اول زیرمنو را خودمان به دلخواه تغییر نام می‌دهیم
	add_submenu_page(
		GV_HUB_SLUG,
		'داشبورد افزونه‌های گروت ویژن',
		'🏠 داشبورد',
		'manage_options',
		GV_HUB_SLUG,
		'gv_hub_render_page'
	);
}

/* ==========================================================
   ۲) دسته‌بندی‌ها
   ------------------------------------------------------------
   هر افزونه با یک کلید 'category' به یکی از این دسته‌ها
   وصل می‌شود. برای افزودن دسته‌ی جدید، فقط یک آیتم دیگر
   به این آرایه اضافه کنید.
   ========================================================== */
function gv_hub_get_categories() {
	return array(
		'marketing' => array(
			'label' => 'افزایش فروش',
			'tag'   => 'فروش',
			'sub'   => 'جلب توجه بازدیدکننده و افزایش نرخ خرید',
			'icon'  => '🎯',
			'color' => '#9f1239',
		),
		'design' => array(
			'label' => 'استایل و ظاهر',
			'tag'   => 'استایل',
			'sub'   => 'ظاهر، فونت و حس‌وحال بصری سایت',
			'icon'  => '🎨',
			'color' => '#7c3aed',
		),
		'security' => array(
			'label' => 'فنی، امنیت و سرعت',
			'tag'   => 'فنی',
			'sub'   => 'محافظت، سرعت و پایداری زیرساخت سایت',
			'icon'  => '🛡️',
			'color' => '#2563eb',
		),
		'seo' => array(
			'label' => 'سئو و محتوا',
			'tag'   => 'سئو',
			'sub'   => 'تولید و مدیریت محتوای هدفمند برای رتبه گوگل',
			'icon'  => '🔍',
			'color' => '#16a34a',
		),
		'manage' => array(
			'label' => 'مدیریت و پشتیبانی',
			'tag'   => 'مدیریت',
			'sub'   => 'کنترل، رصد آمار و پشتیبانی از پشت‌صحنه',
			'icon'  => '📊',
			'color' => '#0e4037',
		),
	);
}

/* ==========================================================
   ۳) اطلاعات کارت هر افزونه
   ------------------------------------------------------------
   'status_option' : نام آپشنی که وضعیت فعال/غیرفعال بودن
                     افزونه را نگه می‌دارد (برای نمایش روی کارت)
   'status_key'    : اگر تنظیمات داخل یک آرایه ذخیره شده،
                     کلیدی که فیلد enabled را نشان می‌دهد
   'page'          : اسلاگ صفحه‌ی زیرمنو (همیشه با admin.php?page=... باز می‌شود)
   'category'      : کلید دسته‌بندی (از gv_hub_get_categories)
   برای افزودن افزونه جدید، فقط یک آیتم دیگر به این آرایه اضافه کنید.
   ========================================================== */
function gv_hub_get_items() {
	return array(
		array(
			'title'         => 'نوار اعلان بالای صفحه',
			'desc'          => 'نوار متحرک با افکت تایپ‌اسکرول برای نمایش پیام‌های تبلیغاتی و لینک‌دار در بالای سایت.',
			'icon'          => '📢',
			'page'          => 'gv-topbar-settings',
			'color'         => '#0e4037',
			'category'      => 'marketing',
			'status_option' => 'gv_topbar_settings',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'کادر تبلیغاتی گوشه صفحه',
			'desc'          => 'کادر شناور با شمارش معکوس، دکمه تماس فوری و دیتای سئوی Schema.org برای پیشنهاد ویژه.',
			'icon'          => '📣',
			'page'          => 'clx-discount-bar',
			'color'         => '#0EA5A4',
			'category'      => 'marketing',
			'status_option' => 'clx_bar_settings',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'اعلان خرید',
			'desc'          => 'نمایش اعلان خریدهای اخیر روی سایت، افزودن خرید جدید، ایمپورت/اکسپورت CSV و مشاهده آمار کلیک‌ها.',
			'icon'          => '🛒',
			'page'          => 'vitrin_purchase_settings',
			'color'         => '#9f1239',
			'category'      => 'marketing',
			'status_option' => 'vp_enabled',
			'status_key'    => null,
		),
		array(
			'title'         => 'پروگرس بار اسکرول',
			'desc'          => 'نوار پیشرفت گرادیانی در بالای صفحه که میزان اسکرول کاربر را به‌صورت زنده نمایش می‌دهد.',
			'icon'          => '📊',
			'page'          => 'clx-progress-bar',
			'color'         => '#7c3aed',
			'category'      => 'design',
			'status_option' => 'clx_progress_settings',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'استایل جدول‌ها و تسویه‌حساب',
			'desc'          => '۳ تم آماده + رنگ‌بندی سفارشی برای همه جدول‌های سایت، به‌همراه ساده‌سازی فرم تسویه‌حساب ووکامرس.',
			'icon'          => '🎨',
			'page'          => 'gv-table-style',
			'color'         => '#7c3aed',
			'category'      => 'design',
			'status_option' => 'gv_table_style_settings',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'طراحی صفحه ورود وردپرس',
			'desc'          => 'چند تم آماده و زیبا برای صفحه لاگین وردپرس، همراه با امکان انتخاب لوگو و متن دلخواه.',
			'icon'          => '🔐',
			'page'          => 'gv-login-style',
			'color'         => '#6d28d9',
			'category'      => 'design',
			'status_option' => 'gv_login_style_settings',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'مدیریت فونت سایت',
			'desc'          => 'آپلود فونت دلخواه، پیش‌نمایش و اعمال خودکار روی کل سایت با مقیاس تایپوگرافی هوشمند برای سربرگ‌ها.',
			'icon'          => '🔤',
			'page'          => 'gv-font-manager',
			'color'         => '#db2777',
			'category'      => 'design',
			'status_option' => 'gv_font_manager_settings',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'تاریخ خودکار شمسی',
			'desc'          => 'افزودن خودکار تاریخ امروز (شمسی) به انتهای عنوان نوشته‌ها و برگه‌ها، هر روز به‌صورت زنده به‌روز می‌شود.',
			'icon'          => '📅',
			'page'          => 'gv-jalali-date',
			'color'         => '#0891b2',
			'category'      => 'design',
			'status_option' => 'gv_jalali_date_settings',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'امنیت و سرعت',
			'desc'          => 'کپچای ریاضی، بلاک درخواست‌های سرور-به-سرور غیرضروری، لیست سفید دائمی درگاه پرداخت و تشخیص خودکار دامنه‌های کند.',
			'icon'          => '🛡️',
			'page'          => 'gv-security-speed',
			'color'         => '#2563eb',
			'category'      => 'security',
			'status_option' => 'gv_security_settings',
			'status_key'    => 'active',
		),
		array(
			'title'         => 'بهینه‌ساز خودکار تصاویر',
			'desc'          => 'فشرده‌سازی خودکار تصاویر سنگین هنگام آپلود (بدون افت کیفیت یا ابعاد) و پاک‌سازی خودکار نسخه‌ی اصلیِ سنگین بعد از مدت مشخص برای صرفه‌جویی در فضای هاست.',
			'icon'          => '🖼️',
			'page'          => GV_IMGOPT_PAGE_SLUG,
			'color'         => '#059669',
			'category'      => 'security',
			'status_option' => GV_IMGOPT_OPT,
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'همگام‌ساز عنوان و آلت تصاویر',
			'desc'          => 'همگام‌سازی خودکار عنوان و متن جایگزین (Alt) هر تصویر با عنوان همان پست/صفحه‌ای که در آن استفاده شده، به‌همراه اسکن کل سایت برای پیدا کردن مغایرت‌ها.',
			'icon'          => '🏷️',
			'page'          => GV_IMGSYNC_PAGE_SLUG,
			'color'         => '#4338ca',
			'category'      => 'seo',
			'status_option' => GV_IMGSYNC_OPT,
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'حالت تعمیر',
			'desc'          => 'بخش حالت تعمیر برای بروزرسانی وبسایت، هنگام آپدیت های ضروری و احتمال مشکل ساز شدن بازدید مشتری.',
			'icon'          => '🚧',
			'page'          => 'wpmc-maintenance',
			'color'         => '#2563eb',
			'category'      => 'security',
			'status_option' => 'wpmc_options',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'دستیار هوش‌مصنوعی سئو',
			'desc'          => 'کارمند کلمه کلیدی و توضیحات را وارد می‌کند، بخش سایت (نوشته/برگه/محصول) را انتخاب می‌کند و محتوای سئوشده به‌صورت خودکار تولید و آپلود می‌شود.',
			'icon'          => '🤖',
			'page'          => 'gv-ai-seo-writer',
			'color'         => '#16a34a',
			'category'      => 'seo',
			'status_option' => null,
			'status_key'    => null,
		),
		array(
			'title'         => 'کلمات کلیدی سئو',
			'desc'          => 'جمع‌آوری تمام کلمات کلیدی فوکوس سایت از Yoast / RankMath / AIOSEO / SEOPress در یک‌جا، به‌همراه خروجی CSV.',
			'icon'          => '🔑',
			'page'          => 'gv-seo-keywords',
			'color'         => '#b45309',
			'category'      => 'seo',
			'status_option' => null,
			'status_key'    => null,
		),
		array(
			'title'         => 'گزارش عملکرد سئوی مشتری',
			'desc'          => 'ثبت گزارش دوره‌ای کار سئو (تغییر رتبه کلمات، محتوای تولیدشده، رشد صفحات، ساعت کار) و نمایش آن با نمودار در پنل اختصاصی مشتری از طریق شورت‌کد [gv_seo_reports].',
			'icon'          => '📑',
			'page'          => GV_SR_PAGE_SLUG,
			'color'         => '#065f46',
			'category'      => 'seo',
			'status_option' => null,
			'status_key'    => null,
		),
		array(
			'title'         => 'آمار بازدید و رفتار کاربران',
			'desc'          => 'ثبت بازدید هر صفحه، مدت‌زمان حضور، مسیر حرکت بین صفحات و کلیک‌های کاربران به‌همراه داشبورد آماری.',
			'icon'          => '📈',
			'page'          => 'gv-visitor-analytics',
			'color'         => '#2563eb',
			'category'      => 'manage',
			'status_option' => 'gv_visitor_analytics_settings',
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'سیستم تیکت پشتیبانی',
			'desc'          => 'ثبت، پیگیری و مدیریت تیکت‌های کاربران به همراه پاسخ‌دهی از پیشخوان و ارسال اعلان ایمیلی.',
			'icon'          => '🎫',
			'page'          => GV_ST_PAGE_SLUG,
			'color'         => '#2563eb',
			'category'      => 'manage',
			'status_option' => GV_ST_OPT,
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'به‌روزرسانی خودکار گیت‌هاب',
			'desc'          => 'اتصال افزونه به مخزن گیت‌هاب پروژه؛ با هر Release جدید، دکمه به‌روزرسانی و خلاصه تغییرات به‌صورت خودکار در پیشخوان نمایش داده می‌شود.',
			'icon'          => '🔄',
			'page'          => 'gv-github-updater',
			'color'         => '#0e4037',
			'category'      => 'manage',
			'status_option' => 'gv_github_updater_settings',
			'status_key'    => 'enabled',
		),
	);
}

/**
 * وضعیت فعال/غیرفعال بودن هر افزونه را برمی‌گرداند.
 */
function gv_hub_get_item_status( $item ) {
	if ( empty( $item['status_option'] ) ) {
		return null; // این افزونه وضعیت روشن/خاموش ندارد (مثل کلمات کلیدی سئو که همیشه در دسترس است)
	}

	$raw = get_option( $item['status_option'], null );

	if ( null === $raw ) {
		return null; // یعنی هنوز تنظیمات ذخیره نشده (افزونه تازه نصب شده)
	}

	if ( null === $item['status_key'] ) {
		return (bool) $raw;
	}

	if ( is_array( $raw ) && isset( $raw[ $item['status_key'] ] ) ) {
		return (bool) $raw[ $item['status_key'] ];
	}

	return null;
}

/* ==========================================================
   ۳.۵) فعال/غیرفعال‌سازی سریع از روی کارت داشبورد (AJAX)
   ========================================================== */
add_action( 'wp_ajax_gv_hub_toggle_status', 'gv_hub_ajax_toggle_status' );
function gv_hub_ajax_toggle_status() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز است.' ), 403 );
	}

	check_ajax_referer( 'gv_hub_toggle_nonce', 'nonce' );

	$page      = isset( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : '';
	$new_state = isset( $_POST['state'] ) && '1' === $_POST['state'];

	if ( '' === $page ) {
		wp_send_json_error( array( 'message' => 'افزونه مشخص نشده است.' ), 400 );
	}

	// فقط آیتم‌هایی که واقعاً در لیست هاب تعریف شده‌اند قابل تغییرند
	// (جلوگیری از تغییر آپشن‌های دلخواه/غیرمرتبط).
	$target = null;
	foreach ( gv_hub_get_items() as $item ) {
		if ( isset( $item['page'] ) && $item['page'] === $page ) {
			$target = $item;
			break;
		}
	}

	if ( ! $target || empty( $target['status_option'] ) ) {
		wp_send_json_error( array( 'message' => 'این افزونه قابلیت فعال/غیرفعال‌سازی از این بخش را ندارد.' ), 404 );
	}

	$option_name = $target['status_option'];
	$status_key  = $target['status_key'];

	if ( null === $status_key ) {
		update_option( $option_name, $new_state ? 1 : 0 );
	} else {
		$current = get_option( $option_name, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$current[ $status_key ] = $new_state ? 1 : 0;
		update_option( $option_name, $current );
	}

	wp_send_json_success( array( 'state' => $new_state ) );
}

/* ==========================================================
   ۴) رندر صفحه داشبورد
   ========================================================== */
function gv_hub_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$items      = gv_hub_get_items();
	$categories = gv_hub_get_categories();

	// گروه‌بندی آیتم‌ها بر اساس دسته
	$grouped = array();
	foreach ( $categories as $cat_key => $cat ) {
		$grouped[ $cat_key ] = array();
	}
	foreach ( $items as $item ) {
		$cat_key = isset( $item['category'] ) && isset( $categories[ $item['category'] ] ) ? $item['category'] : 'manage';
		$grouped[ $cat_key ][] = $item;
	}

	$active_count = 0;
	foreach ( $items as $item ) {
		if ( true === gv_hub_get_item_status( $item ) ) { $active_count++; }
	}
	?>
	<div class="gv-hub-wrap" id="gv-hub-wrap" dir="rtl" data-theme="light">

		<div class="gv-hub-header">
			<div class="gv-hub-header-row">
				<div class="gv-hub-logo">
					<span class="gv-hub-logo-mark" aria-hidden="true">
						<svg viewBox="0 0 48 48" width="30" height="30" fill="none">
							<path d="M24 4C15 10 10 18 10 27c0 8 6 15 14 17 8-2 14-9 14-17 0-9-5-17-14-23Z" fill="currentColor" opacity=".18"/>
							<path d="M24 44V22M24 22c0-6 4-10 10-12M24 22c0-5-3-9-8-11" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<div>
						<h1>افزونه‌های ضروری گروت ویژن</h1>
						<p>مدیریت یکپارچه‌ی تمام ابزارهای نصب‌شده روی سایت شما</p>
					</div>
				</div>

				<div class="gv-hub-header-actions">
					<div class="gv-hub-header-stats">
						<span class="gv-hub-badge gv-hub-badge-total"><?php echo count( $items ); ?> افزونه نصب‌شده</span>
						<span class="gv-hub-badge gv-hub-badge-active"><?php echo esc_html( $active_count ); ?> فعال</span>
					</div>
					<button type="button" id="gv-theme-toggle" class="gv-theme-toggle" aria-label="تغییر پوسته تاریک/روشن" aria-pressed="false">
						<span class="gv-theme-toggle-icon gv-icon-sun" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="4.2" fill="currentColor"/><g stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 2.5v2.6M12 18.9v2.6M4.6 12H2M22 12h-2.6M6.3 6.3 4.5 4.5M19.5 19.5l-1.8-1.8M17.7 6.3l1.8-1.8M4.5 19.5l1.8-1.8"/></g></svg>
						</span>
						<span class="gv-theme-toggle-icon gv-icon-moon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="16" height="16"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" fill="currentColor"/></svg>
						</span>
						<span class="gv-theme-toggle-track"><span class="gv-theme-toggle-dot"></span></span>
					</button>
				</div>
			</div>

			<div class="gv-hub-toolbar">
				<div class="gv-hub-filters" id="gv-hub-filters" role="tablist" aria-label="فیلتر دسته‌بندی">
					<button type="button" class="gv-hub-filter is-active" data-filter="all" role="tab" aria-selected="true">
						<span>همه</span><b><?php echo count( $items ); ?></b>
					</button>
					<?php foreach ( $categories as $cat_key => $cat ) : ?>
						<button type="button" class="gv-hub-filter" data-filter="<?php echo esc_attr( $cat_key ); ?>" role="tab" aria-selected="false">
							<span><?php echo esc_html( $cat['icon'] . ' ' . $cat['label'] ); ?></span>
							<b><?php echo count( $grouped[ $cat_key ] ); ?></b>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="gv-hub-toolbar-left">
					<div class="gv-hub-sort">
						<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M6 8h12M9 12h6M11 16h2" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>
						<select id="gv-hub-sort-select" aria-label="مرتب‌سازی افزونه‌ها">
							<option value="default">مرتب‌سازی: پیش‌فرض</option>
							<option value="name-asc">نام (الف تا ی)</option>
							<option value="status">فعال‌ها اول</option>
						</select>
					</div>
					<div class="gv-hub-search">
						<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="m20 20-3.2-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
						<input type="text" id="gv-hub-search-input" placeholder="جستجوی افزونه…" autocomplete="off">
					</div>
				</div>
			</div>
		</div>

		<div class="gv-hub-sections">
			<?php foreach ( $categories as $cat_key => $cat ) :
				if ( empty( $grouped[ $cat_key ] ) ) { continue; }
				?>
				<section class="gv-hub-section" data-section="<?php echo esc_attr( $cat_key ); ?>">
					<div class="gv-hub-section-head">
						<span class="gv-hub-section-icon"><?php echo esc_html( $cat['icon'] ); ?></span>
						<div>
							<h2><?php echo esc_html( $cat['label'] ); ?></h2>
							<p><?php echo esc_html( $cat['sub'] ); ?></p>
						</div>
						<span class="gv-hub-section-count"><?php echo count( $grouped[ $cat_key ] ); ?> افزونه</span>
					</div>

					<div class="gv-hub-grid">
						<?php foreach ( $grouped[ $cat_key ] as $item ) :
							$url    = admin_url( 'admin.php?page=' . $item['page'] );
							$status = gv_hub_get_item_status( $item );
							$search_blob = esc_attr( gv_hub_strip_for_search( $item['title'] . ' ' . $item['desc'] ) );
							$status_rank = true === $status ? 0 : ( false === $status ? 1 : 2 );
							?>
							<a href="<?php echo esc_url( $url ); ?>"
							   class="gv-hub-card"
							   data-category="<?php echo esc_attr( $cat_key ); ?>"
							   data-search="<?php echo $search_blob; ?>"
							   data-name="<?php echo esc_attr( gv_hub_strip_for_search( $item['title'] ) ); ?>"
							   data-status-rank="<?php echo esc_attr( $status_rank ); ?>"
							   style="--gv-card-color: <?php echo esc_attr( $item['color'] ); ?>;">
								<div class="gv-hub-card-top">
									<div class="gv-hub-card-icon"><?php echo esc_html( $item['icon'] ); ?></div>
									<span class="gv-hub-card-tag" style="--gv-tag-color: <?php echo esc_attr( $cat['color'] ); ?>;"><?php echo esc_html( $cat['tag'] ); ?></span>
									<?php if ( true === $status ) : ?>
										<span class="gv-hub-dot gv-hub-dot-on" title="فعال" aria-hidden="true"></span>
									<?php elseif ( false === $status ) : ?>
										<span class="gv-hub-dot gv-hub-dot-off" title="غیرفعال" aria-hidden="true"></span>
									<?php endif; ?>
								</div>
								<h3><?php echo esc_html( $item['title'] ); ?></h3>
								<p><?php echo esc_html( $item['desc'] ); ?></p>
								<div class="gv-hub-card-bottom">
									<span class="gv-hub-card-btn">تنظیمات
										<svg viewBox="0 0 24 24" width="12" height="12"><path d="M15 6 9 12l6 6" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</span>
									<?php if ( null !== $item['status_option'] ) : ?>
										<div class="gv-hub-card-switch" onclick="event.stopPropagation();">
											<span class="gv-hub-status-text <?php echo true === $status ? 'is-on' : 'is-off'; ?>">
												<?php echo true === $status ? 'فعال' : ( false === $status ? 'غیرفعال' : 'تنظیم‌نشده' ); ?>
											</span>
											<label class="gv-hub-toggle" title="فعال/غیرفعال کردن این ابزار">
												<input type="checkbox"
													class="gv-hub-toggle-input"
													data-page="<?php echo esc_attr( $item['page'] ); ?>"
													onchange="gvHubToggleStatus(this)"
													<?php checked( true === $status ); ?>>
												<span class="gv-hub-toggle-track"><span class="gv-hub-toggle-thumb"></span></span>
											</label>
										</div>
									<?php endif; ?>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>

			<p class="gv-hub-empty" id="gv-hub-empty" hidden>چیزی با این جستجو پیدا نشد.</p>
		</div>

		<!-- ============ بخش تبلیغاتی توسعه‌دهنده ============ -->
		<div class="gv-hub-promo">
			<div class="gv-hub-promo-glow" aria-hidden="true"></div>
			<div class="gv-hub-promo-inner">
				<div class="gv-hub-promo-text">
					<span class="gv-hub-promo-badge">✦ توسعه‌یافته توسط تیم Groot Vision</span>
					<h2>نیاز به افزونه اختصاصی، سایت جدید یا سفارشی‌سازی دارید؟</h2>
					<p>
						تیم <strong>گروت ویژن</strong> طراح و توسعه‌دهنده‌ی همین افزونه‌هاست.
						اگر به یک افزونه اختصاصی، طراحی سایت حرفه‌ای، یا رفع اشکال و شخصی‌سازی
						افزونه‌های فعلی نیاز دارید، همین حالا با ما در ارتباط باشید.
					</p>
					<div class="gv-hub-promo-contacts">
						<a href="tel:+989130617187" class="gv-hub-promo-contact">
							<span class="gv-hub-promo-contact-icon">📞</span>
							<span>
								<b>تماس مستقیم</b>
								<bdi>0913 061 7187</bdi>
							</span>
						</a>
						<a href="https://www.grootvision.com" target="_blank" rel="noopener" class="gv-hub-promo-contact">
							<span class="gv-hub-promo-contact-icon">🌐</span>
							<span>
								<b>وب‌سایت</b>
								<bdi>grootvision.com</bdi>
							</span>
						</a>
						<a href="https://instagram.com/grootvision" target="_blank" rel="noopener" class="gv-hub-promo-contact">
							<span class="gv-hub-promo-contact-icon">📸</span>
							<span>
								<b>اینستاگرام</b>
								<bdi>grootvision</bdi>
							</span>
						</a>
					</div>
				</div>
				<a href="tel:+989130617187" class="gv-hub-promo-cta">
					☎ سفارش کار و مشاوره رایگان
				</a>
			</div>
		</div>

		<p class="gv-hub-footer">توسعه‌یافته توسط <strong>Groot Vision</strong> — تمامی حقوق محفوظ است.</p>
	</div>

	<style>
		:root{
			--gv-radius-lg:18px;
			--gv-radius-md:14px;
			--gv-radius-sm:10px;
		}

		/* ---------- توکن‌های رنگ: پوسته روشن ---------- */
		.gv-hub-wrap[data-theme="light"]{
			--gv-bg:#F5F3EC;
			--gv-surface:#FFFFFF;
			--gv-surface-2:#FBFAF5;
			--gv-border:#E6E1D3;
			--gv-text:#1B2B24;
			--gv-text-muted:#66756B;
			--gv-accent:#1F6F5C;
			--gv-accent-soft:rgba(31,111,92,.10);
			--gv-shadow:0 2px 10px rgba(27,43,36,.06);
			--gv-shadow-lg:0 18px 40px rgba(27,43,36,.10);
			--gv-header-a:#0e4037;
			--gv-header-b:#1c6350;
			--gv-header-text:#EFF7F1;
			color-scheme: light;
		}

		/* ---------- توکن‌های رنگ: پوسته تاریک ---------- */
		.gv-hub-wrap[data-theme="dark"]{
			--gv-bg:#0E1613;
			--gv-surface:#152019;
			--gv-surface-2:#1A251E;
			--gv-border:#28352C;
			--gv-text:#EAF2ED;
			--gv-text-muted:#8FA398;
			--gv-accent:#3ED9A0;
			--gv-accent-soft:rgba(62,217,160,.14);
			--gv-shadow:0 2px 10px rgba(0,0,0,.25);
			--gv-shadow-lg:0 20px 46px rgba(0,0,0,.45);
			--gv-header-a:#081511;
			--gv-header-b:#12261d;
			--gv-header-text:#EAF7F0;
			color-scheme: dark;
		}

		.gv-hub-wrap{
			max-width:1180px;margin:20px auto 0;font-family:'Vazirmatn',Tahoma,sans-serif;
			background:var(--gv-bg);color:var(--gv-text);
			padding:22px;border-radius:22px;
			transition:background .25s ease,color .25s ease;
		}
		.gv-hub-wrap *{box-sizing:border-box;}

		/* ---------- هدر ---------- */
		.gv-hub-header{
			position:relative;overflow:hidden;
			background:linear-gradient(135deg,var(--gv-header-a),var(--gv-header-b) 70%);
			color:var(--gv-header-text);padding:26px 28px 20px;border-radius:var(--gv-radius-lg);
			margin-bottom:22px;box-shadow:var(--gv-shadow-lg);
		}
		.gv-hub-header::before{
			content:"";position:absolute;inset:0;pointer-events:none;
			background:radial-gradient(circle at 92% -10%, rgba(255,255,255,.10), transparent 55%);
		}
		.gv-hub-header-row{position:relative;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
		.gv-hub-logo{display:flex;align-items:center;gap:14px;}
		.gv-hub-logo-mark{
			width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;
			background:rgba(255,255,255,.10);color:#8CE9C1;flex-shrink:0;
		}
		.gv-hub-logo h1{margin:0;font-size:21px;color:var(--gv-header-text);font-weight:800;}
		.gv-hub-logo p{margin:4px 0 0;font-size:12.5px;color:rgba(239,247,241,.7);}

		.gv-hub-header-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;position:relative;}
		.gv-hub-header-stats{display:flex;gap:8px;flex-wrap:wrap;}
		.gv-hub-badge{padding:7px 14px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;border:1px solid transparent;}
		.gv-hub-badge-total{background:rgba(250,204,21,.14);border-color:rgba(250,204,21,.5);color:#FBD24A;}
		.gv-hub-badge-active{background:rgba(74,222,128,.14);border-color:rgba(74,222,128,.5);color:#5EE897;}

		.gv-theme-toggle{
			display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.08);
			border:1px solid rgba(255,255,255,.18);border-radius:20px;padding:6px 10px;cursor:pointer;color:#fff;
			transition:background .18s ease;
		}
		.gv-theme-toggle:hover{background:rgba(255,255,255,.15);}
		.gv-theme-toggle-icon{display:flex;color:#FBD24A;}
		.gv-theme-toggle-icon.gv-icon-moon{color:#B9C9FF;}
		.gv-theme-toggle-track{
			width:34px;height:18px;border-radius:20px;background:rgba(255,255,255,.18);
			position:relative;flex-shrink:0;
		}
		.gv-theme-toggle-dot{
			position:absolute;top:2px;right:2px;width:14px;height:14px;border-radius:50%;
			background:#fff;transition:transform .22s ease;
		}
		.gv-hub-wrap[data-theme="dark"] .gv-theme-toggle-dot{transform:translateX(-16px);}

		/* ---------- نوار ابزار: فیلتر + جستجو ---------- */
		.gv-hub-toolbar{position:relative;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-top:20px;}
		.gv-hub-filters{display:flex;gap:6px;flex-wrap:wrap;}
		.gv-hub-filter{
			display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.07);
			border:1px solid rgba(255,255,255,.14);color:rgba(239,247,241,.85);
			font-family:inherit;font-size:12px;font-weight:600;padding:7px 12px;border-radius:20px;cursor:pointer;
			transition:background .15s ease,color .15s ease,border-color .15s ease;
		}
		.gv-hub-filter b{font-weight:700;background:rgba(255,255,255,.14);border-radius:10px;padding:1px 6px;font-size:10.5px;}
		.gv-hub-filter:hover{background:rgba(255,255,255,.13);}
		.gv-hub-filter.is-active{background:#8CE9C1;border-color:#8CE9C1;color:#0b2019;}
		.gv-hub-filter.is-active b{background:rgba(11,32,25,.15);}

		.gv-hub-toolbar-left{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-inline-start:auto;}

		.gv-hub-sort{
			display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.08);
			border:1px solid rgba(255,255,255,.16);border-radius:20px;padding:7px 12px;color:rgba(239,247,241,.75);
		}
		.gv-hub-sort select{
			background:transparent;border:0;outline:0;color:#fff;font-family:inherit;font-size:12px;cursor:pointer;
		}
		.gv-hub-sort select option{color:#0b2019;}

		.gv-hub-search{
			display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.08);
			border:1px solid rgba(255,255,255,.16);border-radius:20px;padding:7px 14px;color:rgba(239,247,241,.65);
			min-width:180px;
		}
		.gv-hub-search input{
			background:transparent;border:0;outline:0;color:#fff;font-family:inherit;font-size:12.5px;width:100%;
		}
		.gv-hub-search input::placeholder{color:rgba(239,247,241,.5);}

		/* ---------- بخش‌های دسته‌بندی ---------- */
		.gv-hub-sections{display:flex;flex-direction:column;gap:26px;margin-bottom:26px;}
		.gv-hub-section{position:relative;}
		.gv-hub-section-head{
			display:flex;align-items:center;gap:12px;margin-bottom:14px;padding-inline-start:2px;
		}
		.gv-hub-section-icon{
			width:38px;height:38px;border-radius:11px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
			font-size:18px;background:var(--gv-accent-soft);
		}
		.gv-hub-section-head h2{margin:0;font-size:15.5px;font-weight:800;color:var(--gv-text);}
		.gv-hub-section-head p{margin:2px 0 0;font-size:12px;color:var(--gv-text-muted);}
		.gv-hub-section-count{margin-inline-start:auto;font-size:11.5px;color:var(--gv-text-muted);background:var(--gv-surface-2);border:1px solid var(--gv-border);padding:4px 10px;border-radius:20px;white-space:nowrap;}

		.gv-hub-empty{text-align:center;color:var(--gv-text-muted);font-size:13px;padding:40px 0;}

		/* ---------- گرید کارت‌ها ---------- */
		.gv-hub-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
		@media(max-width:1100px){.gv-hub-grid{grid-template-columns:repeat(3,1fr);}}
		@media(max-width:760px){.gv-hub-grid{grid-template-columns:repeat(2,1fr);}}
		@media(max-width:480px){.gv-hub-grid{grid-template-columns:1fr;}}

		.gv-hub-card{
			display:flex;flex-direction:column;background:var(--gv-surface);border:1px solid var(--gv-border);
			border-radius:var(--gv-radius-sm);padding:13px 13px 11px;text-decoration:none;color:inherit;
			box-shadow:var(--gv-shadow);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;
			position:relative;
		}
		.gv-hub-card::before{
			content:"";position:absolute;inset-inline-start:0;top:11px;bottom:11px;width:3px;border-radius:4px;
			background:var(--gv-card-color);opacity:.85;
		}
		.gv-hub-card:hover{transform:translateY(-3px);box-shadow:var(--gv-shadow-lg);border-color:var(--gv-card-color);color:inherit;}
		.gv-hub-card-top{display:flex;align-items:center;gap:6px;margin-bottom:8px;}
		.gv-hub-card-icon{
			font-size:16px;width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
			background:color-mix(in srgb, var(--gv-card-color) 14%, transparent);
		}
		.gv-hub-card-tag{
			font-size:10px;font-weight:700;color:var(--gv-tag-color);background:color-mix(in srgb, var(--gv-tag-color) 12%, transparent);
			border:1px solid color-mix(in srgb, var(--gv-tag-color) 35%, transparent);
			border-radius:20px;padding:2px 8px;white-space:nowrap;
		}
		.gv-hub-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-inline-start:auto;}
		.gv-hub-dot-on{background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.18);}
		.gv-hub-dot-off{background:#cbd5e1;}
		.gv-hub-wrap[data-theme="dark"] .gv-hub-dot-off{background:#3a473e;}

		.gv-hub-card h3{font-size:13px;margin:0 0 4px;color:var(--gv-text);font-weight:700;line-height:1.5;}
		.gv-hub-card p{font-size:11.3px;color:var(--gv-text-muted);line-height:1.75;margin:0 0 10px;
			display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
		.gv-hub-card-bottom{display:flex;align-items:center;justify-content:space-between;gap:6px;margin-top:auto;padding-top:8px;border-top:1px dashed var(--gv-border);}
		.gv-hub-card-btn{
			display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:var(--gv-card-color);
		}
		.gv-hub-card-btn svg{transform:rotate(180deg);transition:transform .16s ease;}
		.gv-hub-card:hover .gv-hub-card-btn svg{transform:rotate(180deg) translateX(3px);}

		.gv-hub-card-switch{display:flex;align-items:center;gap:5px;}
		.gv-hub-status-text{font-size:9.5px;font-weight:700;color:var(--gv-text-muted);white-space:nowrap;}
		.gv-hub-status-text.is-on{color:#1b9c56;}
		.gv-hub-wrap[data-theme="dark"] .gv-hub-status-text.is-on{color:#5EE897;}

		.gv-hub-toggle{position:relative;display:inline-block;width:30px;height:17px;flex-shrink:0;cursor:pointer;}
		.gv-hub-toggle input{position:absolute;inset:0;opacity:0;margin:0;cursor:pointer;z-index:2;}
		.gv-hub-toggle-track{position:absolute;inset:0;background:#cbd5e1;border-radius:20px;transition:background .18s ease;}
		.gv-hub-toggle-thumb{position:absolute;top:2px;inset-inline-start:2px;width:13px;height:13px;background:#fff;border-radius:50%;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:inset-inline-start .18s ease;}
		.gv-hub-toggle input:checked ~ .gv-hub-toggle-track{background:#22c55e;}
		.gv-hub-toggle input:checked ~ .gv-hub-toggle-track .gv-hub-toggle-thumb{inset-inline-start:15px;}
		.gv-hub-toggle input:focus-visible ~ .gv-hub-toggle-track{box-shadow:0 0 0 2px rgba(34,197,94,.35);}
		.gv-hub-toggle input:disabled{cursor:wait;}
		.gv-hub-toggle input:disabled ~ .gv-hub-toggle-track{opacity:.55;}
		.gv-hub-card.gv-hub-card-busy{opacity:.65;pointer-events:none;}

		.gv-hub-card[hidden]{display:none;}
		.gv-hub-section[hidden]{display:none;}

		/* ---------- بخش تبلیغاتی ---------- */
		.gv-hub-promo{position:relative;overflow:hidden;border-radius:20px;background:linear-gradient(135deg,#0b1f26,#0e4037 60%,#145c4d);color:#fff;padding:2px;margin-bottom:22px;box-shadow:var(--gv-shadow-lg);}
		.gv-hub-promo-glow{position:absolute;inset:-40%;background:radial-gradient(circle at 20% 20%, rgba(74,222,128,.22), transparent 55%), radial-gradient(circle at 85% 80%, rgba(250,204,21,.16), transparent 50%);pointer-events:none;}
		.gv-hub-promo-inner{position:relative;display:flex;align-items:center;justify-content:space-between;gap:28px;padding:30px 32px;flex-wrap:wrap;}
		.gv-hub-promo-text{flex:1;min-width:280px;}
		.gv-hub-promo-badge{display:inline-block;background:rgba(74,222,128,.15);border:1px solid #4ade80;color:#4ade80;font-size:11.5px;font-weight:700;padding:5px 14px;border-radius:20px;margin-bottom:14px;}
		.gv-hub-promo-text h2{margin:0 0 10px;font-size:18.5px;line-height:1.6;color:#fff;}
		.gv-hub-promo-text p{margin:0 0 20px;font-size:13.3px;line-height:1.95;color:#cbd5e1;max-width:560px;}
		.gv-hub-promo-contacts{display:flex;gap:12px;flex-wrap:wrap;}
		.gv-hub-promo-contact{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:9px 14px;text-decoration:none;color:#fff;transition:background .18s ease, transform .18s ease;}
		.gv-hub-promo-contact:hover{background:rgba(255,255,255,.14);transform:translateY(-2px);color:#fff;}
		.gv-hub-promo-contact-icon{font-size:18px;}
		.gv-hub-promo-contact b{display:block;font-size:11px;color:#94ded1;font-weight:700;}
		.gv-hub-promo-contact bdi{display:block;font-size:12.5px;font-weight:600;direction:ltr;text-align:right;}
		.gv-hub-promo-cta{flex-shrink:0;background:linear-gradient(120deg,#4ade80,#22c55e);color:#052e18 !important;font-weight:800;font-size:14px;padding:15px 26px;border-radius:14px;text-decoration:none;box-shadow:0 10px 24px rgba(34,197,94,.35);white-space:nowrap;transition:transform .18s ease, filter .18s ease;}
		.gv-hub-promo-cta:hover{transform:translateY(-3px);filter:brightness(1.05);color:#052e18 !important;}

		@media(max-width:700px){
			.gv-hub-promo-inner{flex-direction:column;align-items:stretch;text-align:center;}
			.gv-hub-promo-text p{max-width:none;}
			.gv-hub-promo-contacts{justify-content:center;}
			.gv-hub-promo-cta{text-align:center;}
			.gv-hub-toolbar{flex-direction:column;align-items:stretch;}
			.gv-hub-toolbar-left{margin-inline-start:0;width:100%;}
			.gv-hub-sort,.gv-hub-search{flex:1;min-width:0;}
			.gv-hub-search{min-width:0;}
		}

		.gv-hub-footer{text-align:center;color:var(--gv-text-muted);font-size:12.5px;margin:6px 0 4px;}
	</style>

	<script>
	var GV_HUB_AJAX = {
		url:   <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
		nonce: <?php echo wp_json_encode( wp_create_nonce( 'gv_hub_toggle_nonce' ) ); ?>
	};

	function gvHubToggleStatus( input ) {
		var page     = input.getAttribute( 'data-page' );
		var newState = input.checked;
		var card     = input.closest( '.gv-hub-card' );
		var label    = card ? card.querySelector( '.gv-hub-status-text' ) : null;

		input.disabled = true;
		if ( card ) { card.classList.add( 'gv-hub-card-busy' ); }

		var body = new URLSearchParams();
		body.append( 'action', 'gv_hub_toggle_status' );
		body.append( 'nonce', GV_HUB_AJAX.nonce );
		body.append( 'page', page || '' );
		body.append( 'state', newState ? '1' : '0' );

		fetch( GV_HUB_AJAX.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
		.then( function( res ) { return res.json(); } )
		.then( function( json ) {
			input.disabled = false;
			if ( card ) { card.classList.remove( 'gv-hub-card-busy' ); }

			if ( ! json || ! json.success ) {
				input.checked = ! newState;
				window.alert( ( json && json.data && json.data.message ) ? json.data.message : 'خطا در تغییر وضعیت. دوباره تلاش کنید.' );
				return;
			}

			if ( label ) {
				label.textContent = newState ? 'فعال' : 'غیرفعال';
				label.classList.toggle( 'is-on', newState );
			}

			var activeBadge = document.querySelector( '.gv-hub-badge-active' );
			if ( activeBadge ) {
				var current = parseInt( activeBadge.textContent, 10 ) || 0;
				var next    = newState ? current + 1 : Math.max( 0, current - 1 );
				activeBadge.textContent = next + ' فعال';
			}
		} )
		.catch( function() {
			input.disabled = false;
			if ( card ) { card.classList.remove( 'gv-hub-card-busy' ); }
			input.checked = ! newState;
			window.alert( 'خطا در ارتباط با سرور. دوباره تلاش کنید.' );
		} );
	}

	(function(){
		var wrap = document.getElementById('gv-hub-wrap');
		if (!wrap) return;

		/* ---- تم تاریک/روشن ---- */
		var STORAGE_KEY = 'gv_hub_theme';
		var toggle = document.getElementById('gv-theme-toggle');
		var saved = null;
		try { saved = window.localStorage.getItem(STORAGE_KEY); } catch(e) {}
		var initial = saved || ( window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light' );
		wrap.setAttribute('data-theme', initial);
		if (toggle) toggle.setAttribute('aria-pressed', initial === 'dark' ? 'true' : 'false');

		if (toggle) {
			toggle.addEventListener('click', function(){
				var next = wrap.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
				wrap.setAttribute('data-theme', next);
				toggle.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
				try { window.localStorage.setItem(STORAGE_KEY, next); } catch(e) {}
			});
		}

		/* ---- فیلتر دسته‌بندی + جستجو + مرتب‌سازی ---- */
		var filterButtons = Array.prototype.slice.call(document.querySelectorAll('#gv-hub-filters .gv-hub-filter'));
		var sections       = Array.prototype.slice.call(document.querySelectorAll('.gv-hub-section'));
		var searchInput    = document.getElementById('gv-hub-search-input');
		var sortSelect     = document.getElementById('gv-hub-sort-select');
		var emptyState     = document.getElementById('gv-hub-empty');
		var activeFilter   = 'all';

		function applySort(){
			var sortBy = sortSelect ? sortSelect.value : 'default';
			if (sortBy === 'default') return;

			sections.forEach(function(section){
				var grid  = section.querySelector('.gv-hub-grid');
				if (!grid) return;
				var cards = Array.prototype.slice.call(grid.querySelectorAll('.gv-hub-card'));

				cards.sort(function(a, b){
					if (sortBy === 'name-asc') {
						return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '', 'fa');
					}
					if (sortBy === 'status') {
						return (parseInt(a.getAttribute('data-status-rank'), 10) || 0) - (parseInt(b.getAttribute('data-status-rank'), 10) || 0);
					}
					return 0;
				});

				cards.forEach(function(card){ grid.appendChild(card); });
			});
		}

		function applyFilters(){
			var term = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
			var anyVisible = false;

			sections.forEach(function(section){
				var sectionMatchesCategory = activeFilter === 'all' || section.getAttribute('data-section') === activeFilter;
				var cards = Array.prototype.slice.call(section.querySelectorAll('.gv-hub-card'));
				var visibleInSection = 0;

				cards.forEach(function(card){
					var matchesSearch = !term || (card.getAttribute('data-search') || '').indexOf(term) !== -1;
					var visible = sectionMatchesCategory && matchesSearch;
					card.hidden = !visible;
					if (visible) visibleInSection++;
				});

				section.hidden = visibleInSection === 0;
				if (visibleInSection > 0) anyVisible = true;
			});

			if (emptyState) emptyState.hidden = anyVisible;
		}

		filterButtons.forEach(function(btn){
			btn.addEventListener('click', function(){
				filterButtons.forEach(function(b){
					b.classList.remove('is-active');
					b.setAttribute('aria-selected', 'false');
				});
				btn.classList.add('is-active');
				btn.setAttribute('aria-selected', 'true');
				activeFilter = btn.getAttribute('data-filter');
				applyFilters();
			});
		});

		if (searchInput) {
			searchInput.addEventListener('input', applyFilters);
		}

		if (sortSelect) {
			sortSelect.addEventListener('change', applySort);
		}
	})();
	</script>
	<?php
}

/**
 * متن ساده‌شده (بدون فاصله‌های اضافه) برای جستجوی سمت کلاینت.
 */
function gv_hub_strip_for_search( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $text ) ) );
}