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

/**
 * لینک صفحه‌ی محصول/خرید که دکمه‌ی «خرید نسخه کامل» در داشبورد به آن اشاره می‌کند.
 * این آدرس را با لینک واقعی صفحه‌ی محصولی که روی سایت خودتان گذاشته‌اید جایگزین کنید.
 */
define( 'GV_HUB_PRODUCT_URL', 'https://example.com/product/groot-vision-pro/' );

/* ==========================================================================
   ماژول وایت‌لیبل
   ------------------------------------------------------------------------
   این بخش کاملاً مستقل است و می‌توانید کل آن (از همین‌جا تا خط
   «پایان ماژول وایت‌لیبل») را در یک فایل جداگانه (مثلاً gv-whitelabel.php)
   قرار دهید و در پوشه‌ی افزونه‌ها/اسنیپت‌های سایت لود کنید — فقط باید قبل
   از این فایل (gv-hub.php) اجرا شود چون از GV_HUB_SLUG استفاده می‌کند.
   با این ماژول، مدیر سایت می‌تواند تمام مشخصات تبلیغاتیِ نمایش داده‌شده در
   محیط داشبورد (نام برند، شماره تماس، آدرس سایت و اینستاگرام) را از یک
   صفحه‌ی تنظیمات تغییر دهد؛ همه‌جای داشبورد از همین مقادیر استفاده می‌کند.
   ========================================================================== */
define( 'GV_WL_OPTION', 'gv_hub_whitelabel_settings' );
define( 'GV_WL_PAGE_SLUG', 'gv-hub-whitelabel' );

/**
 * مقادیر وایت‌لیبل را برمی‌گرداند (مقدار ذخیره‌شده یا مقدار پیش‌فرض گروت ویژن).
 */
function gv_wl_get_settings() {
	$defaults = array(
		'brand_name'        => 'گروت ویژن',
		'phone'             => '+989130617187',
		'phone_display'     => '0913 061 7187',
		'website_url'       => 'https://www.grootvision.com',
		'website_display'   => 'grootvision.com',
		'instagram_url'     => 'https://instagram.com/grootvision',
		'instagram_display' => 'grootvision',
	);
	$saved = get_option( GV_WL_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, $defaults );
}

// صفحه‌ی تنظیمات به‌عنوان یک زیرمنو مثل بقیه‌ی ابزارها ثبت می‌شود (از فلای‌اوت هاور هم مثل بقیه مخفی می‌شود).
add_action( 'admin_menu', 'gv_wl_register_menu', 20 );
function gv_wl_register_menu() {
	add_submenu_page(
		GV_HUB_SLUG,
		'تنظیمات وایت‌لیبل',
		'🏷️ وایت‌لیبل',
		'manage_options',
		GV_WL_PAGE_SLUG,
		'gv_wl_render_page'
	);
}

/**
 * صفحه‌ی تنظیمات وایت‌لیبل: نام برند، شماره تماس، سایت و اینستاگرام.
 */
function gv_wl_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$saved_ok = false;

	if ( isset( $_POST['gv_wl_save'] ) && check_admin_referer( 'gv_wl_save_action', 'gv_wl_nonce' ) ) {
		$posted = array(
			'brand_name'        => isset( $_POST['brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['brand_name'] ) ) : '',
			'phone'             => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'phone_display'     => isset( $_POST['phone_display'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_display'] ) ) : '',
			'website_url'       => isset( $_POST['website_url'] ) ? esc_url_raw( wp_unslash( $_POST['website_url'] ) ) : '',
			'website_display'   => isset( $_POST['website_display'] ) ? sanitize_text_field( wp_unslash( $_POST['website_display'] ) ) : '',
			'instagram_url'     => isset( $_POST['instagram_url'] ) ? esc_url_raw( wp_unslash( $_POST['instagram_url'] ) ) : '',
			'instagram_display' => isset( $_POST['instagram_display'] ) ? sanitize_text_field( wp_unslash( $_POST['instagram_display'] ) ) : '',
		);

		// فیلد خالی = همان مقدار پیش‌فرض گروت ویژن حفظ شود.
		$current = gv_wl_get_settings();
		foreach ( $posted as $key => $val ) {
			if ( '' !== $val ) {
				$current[ $key ] = $val;
			}
		}

		update_option( GV_WL_OPTION, $current );
		$saved_ok = true;
	}

	$wl = gv_wl_get_settings();
	?>
	<div class="wrap gv-wl-wrap" dir="rtl">
		<h1 style="font-size:20px;">🏷️ تنظیمات وایت‌لیبل داشبورد</h1>
		<p style="color:#555;max-width:640px;">هرچه اینجا وارد کنید، به‌جای مشخصات پیش‌فرض «گروت ویژن» در سرتاسر محیط داشبورد (عنوان‌ها، کارت دسترسی سریع، دکمه‌های تماس و ...) نمایش داده می‌شود. برای بازگرداندن هر فیلد به مقدار پیش‌فرض، آن را خالی بگذارید و ذخیره کنید.</p>

		<?php if ( $saved_ok ) : ?>
			<div class="notice notice-success"><p>تنظیمات وایت‌لیبل با موفقیت ذخیره شد.</p></div>
		<?php endif; ?>

		<form method="post" style="max-width:640px;background:#fff;border:1px solid #e2e2e2;border-radius:12px;padding:20px 24px;margin-top:16px;">
			<?php wp_nonce_field( 'gv_wl_save_action', 'gv_wl_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gv_wl_brand_name">نام برند</label></th>
					<td><input type="text" id="gv_wl_brand_name" name="brand_name" class="regular-text" value="<?php echo esc_attr( $wl['brand_name'] ); ?>" placeholder="گروت ویژن"></td>
				</tr>
				<tr>
					<th scope="row"><label for="gv_wl_phone">شماره تماس (برای لینک tel:)</label></th>
					<td><input type="text" id="gv_wl_phone" name="phone" class="regular-text" dir="ltr" value="<?php echo esc_attr( $wl['phone'] ); ?>" placeholder="+989130617187"></td>
				</tr>
				<tr>
					<th scope="row"><label for="gv_wl_phone_display">شماره تماس (نمایشی)</label></th>
					<td><input type="text" id="gv_wl_phone_display" name="phone_display" class="regular-text" dir="ltr" value="<?php echo esc_attr( $wl['phone_display'] ); ?>" placeholder="0913 061 7187"></td>
				</tr>
				<tr>
					<th scope="row"><label for="gv_wl_website_url">آدرس سایت (لینک)</label></th>
					<td><input type="url" id="gv_wl_website_url" name="website_url" class="regular-text" dir="ltr" value="<?php echo esc_attr( $wl['website_url'] ); ?>" placeholder="https://www.example.com"></td>
				</tr>
				<tr>
					<th scope="row"><label for="gv_wl_website_display">آدرس سایت (نمایشی)</label></th>
					<td><input type="text" id="gv_wl_website_display" name="website_display" class="regular-text" dir="ltr" value="<?php echo esc_attr( $wl['website_display'] ); ?>" placeholder="example.com"></td>
				</tr>
				<tr>
					<th scope="row"><label for="gv_wl_instagram_url">اینستاگرام (لینک)</label></th>
					<td><input type="url" id="gv_wl_instagram_url" name="instagram_url" class="regular-text" dir="ltr" value="<?php echo esc_attr( $wl['instagram_url'] ); ?>" placeholder="https://instagram.com/yourpage"></td>
				</tr>
				<tr>
					<th scope="row"><label for="gv_wl_instagram_display">اینستاگرام (نمایشی)</label></th>
					<td><input type="text" id="gv_wl_instagram_display" name="instagram_display" class="regular-text" dir="ltr" value="<?php echo esc_attr( $wl['instagram_display'] ); ?>" placeholder="yourpage"></td>
				</tr>
			</table>

			<p><button type="submit" name="gv_wl_save" value="1" class="button button-primary">ذخیره تغییرات</button></p>
		</form>
	</div>
	<?php
}
/* ============================ پایان ماژول وایت‌لیبل ============================ */

/* ==========================================================
   ۱) ساخت منوی والد + آیتم داشبورد
   ========================================================== */
add_action( 'admin_menu', 'gv_hub_register_menu', 5 ); // اولویت ۵ = زودتر از بقیه (پیش‌فرض ۱۰)
function gv_hub_register_menu() {

	$brand = gv_wl_get_settings();
	$brand_name = $brand['brand_name'];

	add_menu_page(
		'افزونه‌های ضروری ' . $brand_name,
		$brand_name . ' پرو',
		'manage_options',
		GV_HUB_SLUG,
		'gv_hub_render_page',
		'dashicons-star-filled',
		58
	);

	// آیتم اول زیرمنو را خودمان به دلخواه تغییر نام می‌دهیم
	add_submenu_page(
		GV_HUB_SLUG,
		'داشبورد افزونه‌های ' . $brand_name,
		'🏠 داشبورد',
		'manage_options',
		GV_HUB_SLUG,
		'gv_hub_render_page'
	);

	// به‌جای نمایش تک‌تک افزونه‌ها در فلای‌اوت هاور منو (که خیلی شلوغ می‌شد)
	// فقط نام دسته‌بندی‌ها را نشان می‌دهیم؛ کلیک روی هرکدام داشبورد را
	// به‌صورت فیلترشده روی همان دسته باز می‌کند.
	$categories = gv_hub_get_categories();
	foreach ( $categories as $cat_key => $cat ) {
		add_submenu_page(
			GV_HUB_SLUG,
			$cat['label'] . ' | ' . $brand_name,
			$cat['icon'] . ' ' . $cat['label'],
			'manage_options',
			GV_HUB_SLUG . '-cat-' . $cat_key,
			'gv_hub_render_page'
		);
	}
}

/* ==========================================================
   ۱-ب) پاک کردن تک‌تک زیرمنوهای هر ابزار از لیست منو
   ------------------------------------------------------------
   هر ماژول (نوار اعلان، لاگین، فونت، تیکت و ...) صفحه‌ی تنظیمات
   خودش را با add_submenu_page ثبت می‌کند تا از آدرس
   admin.php?page=... مستقیم قابل‌دسترسی باشد. این کاملاً لازم است
   و دست‌نخورده می‌ماند؛ فقط از فهرست/فلای‌اوت منو حذفشان می‌کنیم
   تا هاور روی «گروت ویژن پرو» شلوغ نباشد و فقط دسته‌بندی‌ها دیده شوند.
   این کار با اولویت خیلی دیرتر (999) انجام می‌شود تا مطمئن شویم همه‌ی
   ماژول‌ها قبلاً زیرمنوی خودشان را ثبت کرده‌اند.
   ========================================================== */
/* ==========================================================
   ۱-ب) مخفی‌کردن بصری تک‌تک زیرمنوهای هر ابزار از فلای‌اوت هاور
   ------------------------------------------------------------
   نکته‌ی مهم: اینجا از remove_submenu_page() استفاده نمی‌کنیم،
   چون آن تابع باعث می‌شود وردپرس دیگر نتواند parent_file صفحه را
   پیدا کند و با کلیک روی کارت‌های داشبورد (که مستقیم به
   admin.php?page=... می‌روند) خطای «اجازه‌ی دسترسی ندارید»
   (wp_die در wp-admin/includes/menu.php) نمایش می‌دهد.
   به‌جای آن فقط با یک اسکریپت کوچک، همان آیتم‌ها را در فلای‌اوت
   هاور به‌صورت بصری مخفی می‌کنیم؛ ثبت واقعی صفحه دست‌نخورده
   می‌ماند و کلیک روی کارت‌های داشبورد بدون مشکل کار می‌کند.
   ========================================================== */
add_action( 'admin_footer', 'gv_hub_hide_individual_tool_submenus_js' );
function gv_hub_hide_individual_tool_submenus_js() {
	$items = gv_hub_get_items();
	$pages = array();
	foreach ( $items as $item ) {
		if ( ! empty( $item['page'] ) ) {
			$pages[] = $item['page'];
		}
	}
	if ( empty( $pages ) ) { return; }
	?>
	<script>
	(function () {
		var toolPages = <?php echo wp_json_encode( array_values( $pages ) ); ?>;
		var menuItem = document.getElementById('toplevel_page_<?php echo esc_js( GV_HUB_SLUG ); ?>');
		if (!menuItem) { return; }
		var links = menuItem.querySelectorAll('.wp-submenu a, #adminmenu .wp-submenu a');
		links.forEach(function (a) {
			var href = a.getAttribute('href') || '';
			for (var i = 0; i < toolPages.length; i++) {
				if (href.indexOf('page=' + toolPages[i]) !== -1) {
					var li = a.closest('li');
					if (li) { li.style.display = 'none'; }
					break;
				}
			}
		});
	})();
	</script>
	<?php
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
			'title'         => 'ورود با موبایل یا ایمیل (رمز ثابت)',
			'desc'          => 'ثبت‌نام و ورود کاربران با ایمیل یا شماره موبایل + رمز عبور ثابت دلخواه، بدون نیاز به کد پیامکی. سازگار با فرم حساب کاربری وودمارت و بی‌تم.',
			'icon'          => '📱',
			'page'          => 'gv-mobile-login',
			'color'         => '#0e4037',
			'category'      => 'security',
			'status_option' => GV_MLOGIN_OPT,
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'مینی CRM و کمپین مشتریان',
			'desc'          => 'دسته‌بندی خودکار مشتری‌ها بر اساس رفتار خرید (وفادار / مدت‌هاست خرید نکرده / فقط یک‌بار خریده) و ارسال پیامک یا ایمیل هدفمند برای هر گروه.',
			'icon'          => '👥',
			'page'          => GV_CRM_PAGE_SLUG,
			'color'         => '#9f1239',
			'category'      => 'marketing',
			'status_option' => GV_CRM_OPT,
			'status_key'    => 'enabled',
		),
		array(
			'title'         => 'وایت‌لیبل داشبورد',
			'desc'          => 'تغییر نام برند، شماره تماس، آدرس سایت و اینستاگرامی که در سرتاسر محیط داشبورد نمایش داده می‌شود.',
			'icon'          => '🏷️',
			'page'          => GV_WL_PAGE_SLUG,
			'color'         => '#334155',
			'category'      => 'manage',
			'status_option' => null,
			'status_key'    => null,
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
   ۳.۲) لیست کارمندها برای نمایش آواتار در داشبورد
   ------------------------------------------------------------
   به‌صورت پیش‌فرض کاربران با نقش‌های gv_employee / administrator /
   editor / author را به‌عنوان «کارمند» نمایش می‌دهیم. تعداد
   گزارش‌ها و تاریخ آخرین گزارش هر کارمند از طریق دو فیلتر زیر
   قابل تزریق است تا ماژول «گزارش عملکرد سئوی مشتری» (یا هر
   ماژول دیگری) بتواند اطلاعات واقعی خودش را جایگزین کند:
     add_filter( 'gv_hub_employee_reports_count', function( $count, $user_id ) { ... }, 10, 2 );
     add_filter( 'gv_hub_employee_last_report',   function( $date,  $user_id ) { ... }, 10, 2 );
   ========================================================== */
function gv_hub_get_employees() {
	$users = get_users( array(
		'role__in' => array( 'gv_employee', 'administrator', 'editor', 'author' ),
		'orderby'  => 'display_name',
		'order'    => 'ASC',
		'number'   => 30,
	) );

	// اجازه می‌دهد سایت لیست دقیق‌تری از کارمندها (مثلاً فقط یک نقش خاص) تعریف کند.
	$users = apply_filters( 'gv_hub_employee_users', $users );

	$palette = array( '#9f1239', '#7c3aed', '#2563eb', '#16a34a', '#0e4037', '#db2777', '#0891b2', '#b45309' );

	$employees = array();
	foreach ( $users as $index => $user ) {
		$reports_count = (int) apply_filters( 'gv_hub_employee_reports_count', 0, $user->ID );
		$last_report   = (string) apply_filters( 'gv_hub_employee_last_report', '', $user->ID );
		$initial       = mb_strtoupper( mb_substr( trim( $user->display_name ), 0, 1 ) );

		$employees[] = array(
			'id'            => $user->ID,
			'name'          => $user->display_name,
			'initial'       => $initial,
			'color'         => $palette[ $index % count( $palette ) ],
			'reports_count' => $reports_count,
			'last_report'   => $last_report,
		);
	}

	return $employees;
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
	$whitelabel = gv_wl_get_settings();
	$brand_name = $whitelabel['brand_name'];
	$employees  = gv_hub_get_employees();

	// اگر از روی یکی از آیتم‌های دسته‌بندی در منو وارد شده باشیم،
	// همان دسته به‌عنوان فیلتر پیش‌فرض داشبورد فعال می‌شود.
	$current_page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : GV_HUB_SLUG;
	$default_filter = 'all';
	$cat_prefix     = GV_HUB_SLUG . '-cat-';
	if ( 0 === strpos( $current_page, $cat_prefix ) ) {
		$maybe_cat = substr( $current_page, strlen( $cat_prefix ) );
		if ( isset( $categories[ $maybe_cat ] ) ) {
			$default_filter = $maybe_cat;
		}
	}

	// گروه‌بندی آیتم‌ها بر اساس دسته
	$grouped = array();
	foreach ( $categories as $cat_key => $cat ) {
		$grouped[ $cat_key ] = array();
	}
	foreach ( $items as $item ) {
		$cat_key = isset( $item['category'] ) && isset( $categories[ $item['category'] ] ) ? $item['category'] : 'manage';
		$grouped[ $cat_key ][] = $item;
	}

	// آمار خلاصه برای دو کارت پیشرفت بالای صفحه
	$active_count      = 0;
	$inactive_count    = 0;
	$toggleable_total  = 0;
	foreach ( $items as $item ) {
		if ( empty( $item['status_option'] ) ) { continue; }
		$toggleable_total++;
		$status = gv_hub_get_item_status( $item );
		if ( true === $status ) { $active_count++; }
		elseif ( false === $status ) { $inactive_count++; }
	}
	$total_items  = count( $items );
	$active_pct   = $toggleable_total ? (int) round( $active_count / $toggleable_total * 100 ) : 0;
	$inactive_pct = $toggleable_total ? (int) round( $inactive_count / $toggleable_total * 100 ) : 0;
	?>
	<div class="gv-hub-wrap" id="gv-hub-wrap" dir="rtl" data-theme="light">
		<div class="gv-hub-shell">

			<!-- ============ نوار کناری (قابل باز/بسته شدن) ============ -->
			<nav class="gv-hub-rail" id="gv-hub-rail">
				<button type="button" class="gv-hub-rail-toggle" id="gv-hub-rail-toggle" title="باز/بسته کردن منو">
					<svg viewBox="0 0 24 24" width="13" height="13"><path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>

				<div class="gv-hub-rail-top">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_HUB_SLUG ) ); ?>" class="gv-hub-rail-logo" title="داشبورد <?php echo esc_attr( $brand_name ); ?>">
						<svg viewBox="0 0 48 48" width="20" height="20" fill="none"><path d="M24 4C15 10 10 18 10 27c0 8 6 15 14 17 8-2 14-9 14-17 0-9-5-17-14-23Z" fill="currentColor" opacity=".2"/><path d="M24 44V22M24 22c0-6 4-10 10-12M24 22c0-5-3-9-8-11" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>
				</div>

				<div class="gv-hub-rail-nav">
					<button type="button" class="gv-hub-rail-btn gv-hub-filter is-active" data-filter="all">
						<span class="gv-hub-rail-icon"><svg viewBox="0 0 24 24" width="18" height="18"><rect x="3" y="3" width="8" height="8" rx="2.2" fill="currentColor"/><rect x="13" y="3" width="8" height="8" rx="2.2" fill="currentColor" opacity=".45"/><rect x="3" y="13" width="8" height="8" rx="2.2" fill="currentColor" opacity=".45"/><rect x="13" y="13" width="8" height="8" rx="2.2" fill="currentColor"/></svg></span>
						<span class="gv-hub-rail-label">میزکار</span>
					</button>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_ST_PAGE_SLUG ) ); ?>" class="gv-hub-rail-btn" title="سیستم تیکت پشتیبانی">
						<span class="gv-hub-rail-icon"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9A1.5 1.5 0 0 1 18.5 16H9l-4 3.5v-3.5H5.5A1.5 1.5 0 0 1 4 14.5v-9Z" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linejoin="round"/></svg></span>
						<span class="gv-hub-rail-label">پیام‌ها</span>
					</a>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_SR_PAGE_SLUG ) ); ?>" class="gv-hub-rail-btn" title="گزارش عملکرد سئو">
						<span class="gv-hub-rail-icon"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M5 19V10M11 19V5M17 19v-7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg></span>
						<span class="gv-hub-rail-label">گزارش‌ها</span>
					</a>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GV_WL_PAGE_SLUG ) ); ?>" class="gv-hub-rail-btn" title="تنظیمات وایت‌لیبل">
						<span class="gv-hub-rail-icon gv-hub-rail-emoji">🏷️</span>
						<span class="gv-hub-rail-label">وایت‌لیبل</span>
					</a>

					<div class="gv-hub-rail-sep" aria-hidden="true"></div>

					<?php foreach ( $categories as $cat_key => $cat ) : ?>
						<button type="button" class="gv-hub-rail-btn gv-hub-filter" data-filter="<?php echo esc_attr( $cat_key ); ?>" title="<?php echo esc_attr( $cat['label'] ); ?>">
							<span class="gv-hub-rail-icon gv-hub-rail-emoji"><?php echo esc_html( $cat['icon'] ); ?></span>
							<span class="gv-hub-rail-label"><?php echo esc_html( $cat['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			</nav>

			<div class="gv-hub-main">

				<!-- ============ نوار بالا ============ -->
				<div class="gv-hub-topbar">
					<div class="gv-hub-topbar-title">
						<h1>افزونه‌های <?php echo esc_html( $brand_name ); ?></h1>
						<span class="gv-hub-topbar-badge"><?php echo esc_html( $active_count ); ?> افزونه فعال از <?php echo esc_html( $total_items ); ?></span>
					</div>
					<div class="gv-hub-topbar-actions">
						<div class="gv-hub-search">
							<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="m20 20-3.2-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
							<input type="text" id="gv-hub-search-input" placeholder="جستجوی افزونه…" autocomplete="off">
						</div>
						<button type="button" id="gv-theme-toggle" class="gv-theme-toggle" aria-label="تغییر پوسته تاریک/روشن" aria-pressed="false">
							<span class="gv-theme-toggle-icon gv-icon-sun" aria-hidden="true"><svg viewBox="0 0 24 24" width="15" height="15"><circle cx="12" cy="12" r="4.2" fill="currentColor"/><g stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 2.5v2.6M12 18.9v2.6M4.6 12H2M22 12h-2.6M6.3 6.3 4.5 4.5M19.5 19.5l-1.8-1.8M17.7 6.3l1.8-1.8M4.5 19.5l1.8-1.8"/></g></svg></span>
							<span class="gv-theme-toggle-icon gv-icon-moon" aria-hidden="true"><svg viewBox="0 0 24 24" width="15" height="15"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" fill="currentColor"/></svg></span>
							<span class="gv-theme-toggle-track"><span class="gv-theme-toggle-dot"></span></span>
						</button>
						<a href="<?php echo esc_url( $whitelabel['website_url'] ); ?>" target="_blank" rel="noopener" class="gv-hub-avatar" title="ارتباط با پشتیبانی">
							<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="8.5" r="3.4" fill="currentColor"/><path d="M5 20c1-3.6 4-5.6 7-5.6s6 2 7 5.6" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>
						</a>
					</div>
				</div>

				<!-- ============ دو دکمه‌ی اقدام سریع ============ -->
				<div class="gv-hub-cta-row">
					<a href="<?php echo esc_url( GV_HUB_PRODUCT_URL ); ?>" target="_blank" rel="noopener" class="gv-hub-cta-buy">🛒 خرید نسخه کامل</a>
					<a href="<?php echo esc_url( 'tel:' . $whitelabel['phone'] ); ?>" class="gv-hub-cta-solid">☎ تماس با پشتیبانی <?php echo esc_html( $brand_name ); ?></a>
					<a href="<?php echo esc_url( $whitelabel['website_url'] ); ?>" target="_blank" rel="noopener" class="gv-hub-cta-outline">🌐 مشاهده وب‌سایت <?php echo esc_html( $brand_name ); ?></a>
				</div>

				<!-- ============ ردیف کارت‌های خلاصه ============ -->
				<div class="gv-hub-quickgrid">
					<div class="gv-hub-quickcard gv-hub-cat-stack-card">
						<div class="gv-hub-quickcard-head">
							<span>دسته‌بندی‌ها</span>
							<b><?php echo count( $categories ); ?></b>
						</div>
						<div class="gv-hub-avatar-stack">
							<?php foreach ( $categories as $cat_key => $cat ) : ?>
								<button type="button" class="gv-hub-stack-avatar gv-hub-filter" data-filter="<?php echo esc_attr( $cat_key ); ?>" title="<?php echo esc_attr( $cat['label'] . ' — ' . count( $grouped[ $cat_key ] ) . ' افزونه' ); ?>" style="--gv-card-color: <?php echo esc_attr( $cat['color'] ); ?>;">
									<?php echo esc_html( $cat['icon'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<p class="gv-hub-quickcard-sub">با کلیک روی هر دسته، همان بخش برایتان باز و نمایش داده می‌شود</p>
					</div>

					<div class="gv-hub-quickcard">
						<div class="gv-hub-quickcard-head">
							<span>افزونه‌های فعال</span>
							<b><?php echo esc_html( $active_pct ); ?>٪</b>
						</div>
						<div class="gv-hub-progress"><div class="gv-hub-progress-fill" style="width: <?php echo esc_attr( $active_pct ); ?>%;"></div></div>
						<span class="gv-hub-quickcard-pill is-on">در حال اجرا</span>
					</div>

					<div class="gv-hub-quickcard">
						<div class="gv-hub-quickcard-head">
							<span>افزونه‌های غیرفعال</span>
							<b><?php echo esc_html( $inactive_pct ); ?>٪</b>
						</div>
						<div class="gv-hub-progress"><div class="gv-hub-progress-fill" style="width: <?php echo esc_attr( $inactive_pct ); ?>%;"></div></div>
						<span class="gv-hub-quickcard-pill is-off">نیاز به بررسی</span>
					</div>
				</div>

				<!-- ============ ردیف کارمندها + دسترسی سریع (کنار هم) ============ -->
				<div class="gv-hub-duo-row">

					<!-- کارت کارمندها: هم‌شکل کارت دسته‌بندی‌ها، آواتار = حرف اول اسم -->
					<div class="gv-hub-quickcard gv-hub-team-quickcard">
						<div class="gv-hub-quickcard-head">
							<span>کارمندها</span>
							<b><?php echo count( $employees ); ?></b>
						</div>
						<?php if ( ! empty( $employees ) ) : ?>
							<div class="gv-hub-avatar-stack">
								<?php foreach ( $employees as $emp ) : ?>
									<div class="gv-hub-stack-avatar gv-hub-team-avatar" style="--gv-card-color: <?php echo esc_attr( $emp['color'] ); ?>;">
										<?php echo esc_html( $emp['initial'] ); ?>
										<div class="gv-hub-team-tooltip">
											<b><?php echo esc_html( $emp['name'] ); ?></b>
											<span>تعداد گزارش‌ها: <?php echo esc_html( $emp['reports_count'] ); ?></span>
											<span>آخرین گزارش: <?php echo esc_html( $emp['last_report'] ? $emp['last_report'] : 'ثبت نشده' ); ?></span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<p class="gv-hub-quickcard-sub">برای مشاهده‌ی خلاصه‌ی هر کارمند، نشانگر را روی آواتار نگه دارید</p>
						<?php else : ?>
							<p class="gv-hub-quickcard-sub">هنوز کارمندی ثبت‌نام نکرده است.</p>
						<?php endif; ?>
					</div>

					<!-- کارت تیره‌ی دسترسی سریع -->
					<div class="gv-hub-notes" id="gv-hub-quicklinks">
						<div class="gv-hub-notes-head">دسترسی سریع به تیم <?php echo esc_html( $brand_name ); ?></div>
						<div class="gv-hub-notes-items">
							<a href="<?php echo esc_url( 'tel:' . $whitelabel['phone'] ); ?>" class="gv-hub-notes-item">
								<span class="gv-hub-notes-icon">📞</span>
								<span class="gv-hub-notes-text"><b>تماس مستقیم</b><i><?php echo esc_html( $whitelabel['phone_display'] ); ?></i></span>
							</a>
							<a href="<?php echo esc_url( $whitelabel['website_url'] ); ?>" target="_blank" rel="noopener" class="gv-hub-notes-item">
								<span class="gv-hub-notes-icon">🌐</span>
								<span class="gv-hub-notes-text"><b>وب‌سایت</b><i><?php echo esc_html( $whitelabel['website_display'] ); ?></i></span>
							</a>
							<a href="<?php echo esc_url( $whitelabel['instagram_url'] ); ?>" target="_blank" rel="noopener" class="gv-hub-notes-item">
								<span class="gv-hub-notes-icon">📸</span>
								<span class="gv-hub-notes-text"><b>اینستاگرام</b><i><?php echo esc_html( $whitelabel['instagram_display'] ); ?></i></span>
							</a>
						</div>
						<a href="<?php echo esc_url( 'tel:' . $whitelabel['phone'] ); ?>" class="gv-hub-notes-cta">سفارش کار و مشاوره رایگان</a>
					</div>

				</div>

				<!-- ============ کارت اصلی: فهرست همه‌ی افزونه‌ها ============ -->
				<div class="gv-hub-table-card">
					<div class="gv-hub-table-head">
						<span>همه‌ی افزونه‌ها</span>
						<div class="gv-hub-sort">
							<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M6 8h12M9 12h6M11 16h2" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>
							<select id="gv-hub-sort-select" aria-label="مرتب‌سازی افزونه‌ها">
								<option value="default">مرتب‌سازی: پیش‌فرض</option>
								<option value="name-asc">نام (الف تا ی)</option>
								<option value="status">فعال‌ها اول</option>
							</select>
						</div>
					</div>

					<div class="gv-hub-sections">
						<?php foreach ( $categories as $cat_key => $cat ) :
							if ( empty( $grouped[ $cat_key ] ) ) { continue; }
							?>
							<section class="gv-hub-section is-collapsed" data-section="<?php echo esc_attr( $cat_key ); ?>">
								<button type="button" class="gv-hub-section-head">
									<span class="gv-hub-section-icon" style="--gv-section-color: <?php echo esc_attr( $cat['color'] ); ?>;"><?php echo esc_html( $cat['icon'] ); ?></span>
									<span class="gv-hub-section-title">
										<span class="gv-hub-section-name"><?php echo esc_html( $cat['label'] ); ?></span>
										<span class="gv-hub-section-sub"><?php echo esc_html( $cat['sub'] ); ?></span>
									</span>
									<span class="gv-hub-section-count"><?php echo count( $grouped[ $cat_key ] ); ?> افزونه</span>
									<svg class="gv-hub-section-chevron" viewBox="0 0 24 24" width="16" height="16"><path d="m7 10 5 5 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</button>

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
				</div>

				<p class="gv-hub-footer">توسعه‌یافته توسط <strong><?php echo esc_html( $brand_name ); ?></strong> — تمامی حقوق محفوظ است.</p>
			</div>
		</div>
	</div>

	<style>
		:root{
			--gv-radius-lg:22px;
			--gv-radius-md:18px;
			--gv-radius-sm:16px;
		}

		/* ---------- توکن‌های رنگ: پوسته روشن (تم سبز) ---------- */
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
			--gv-pill-bg:#FBFAF5;
			--gv-pill-active-bg:var(--gv-accent);
			--gv-pill-active-text:#EFF7F1;
			--gv-rail-a:#0e4037;
			--gv-rail-b:#1c6350;
			--gv-rail-text:rgba(239,247,241,.68);
			--gv-rail-active-bg:#EFF7F1;
			--gv-rail-active-text:#0e4037;
			color-scheme: light;
		}

		/* ---------- توکن‌های رنگ: پوسته تاریک (تم سبز) ---------- */
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
			--gv-pill-bg:#1A251E;
			--gv-pill-active-bg:var(--gv-accent);
			--gv-pill-active-text:#0E1613;
			--gv-rail-a:#081511;
			--gv-rail-b:#12261d;
			--gv-rail-text:rgba(234,247,240,.6);
			--gv-rail-active-bg:#EAF7F0;
			--gv-rail-active-text:#0E1613;
			color-scheme: dark;
		}

		.gv-hub-wrap{
			max-width:1220px;margin:20px auto 0;font-family:'Vazirmatn',Tahoma,sans-serif;
			background:var(--gv-bg);color:var(--gv-text);
			padding:14px;border-radius:28px;
			transition:background .25s ease,color .25s ease;
		}
		.gv-hub-wrap *{box-sizing:border-box;}
		.gv-hub-wrap button{font-family:inherit;}

		/* ==================== ساختار کلی: ریل + محتوای اصلی ==================== */
		.gv-hub-shell{display:flex;align-items:flex-start;gap:14px;}
		.gv-hub-main{flex:1;min-width:0;display:flex;flex-direction:column;gap:14px;}

		/* ---------- ریل کناری (قابل باز/بسته شدن) ---------- */
		.gv-hub-rail{
			flex-shrink:0;width:68px;min-height:520px;
			background:linear-gradient(165deg,var(--gv-rail-a),var(--gv-rail-b));border-radius:var(--gv-radius-lg);
			display:flex;flex-direction:column;gap:10px;padding:14px 0;
			position:sticky;top:16px;box-shadow:var(--gv-shadow-lg);
			transition:width .2s ease;
			overflow:visible;
		}
		.gv-hub-rail.is-expanded{width:208px;}
		.gv-hub-rail-top{display:flex;align-items:center;padding:0 13px;}
		.gv-hub-rail-logo{
			width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;
			background:rgba(255,255,255,.1);color:#fff;flex-shrink:0;text-decoration:none;
		}
		/* دکمه‌ی باز/بسته کردن به‌صورت یک حباب شناور روی خط مرزی ریل قرار می‌گیرد (نیمی داخل، نیمی بیرون از کادر) */
		.gv-hub-rail-toggle{
			position:absolute;top:22px;left:-13px;
			width:26px;height:26px;border-radius:50%;flex-shrink:0;
			display:flex;align-items:center;justify-content:center;
			background:var(--gv-surface);border:1px solid var(--gv-border);color:var(--gv-text);
			cursor:pointer;box-shadow:var(--gv-shadow);z-index:6;
			transition:transform .2s ease,background .15s ease;
			transform:rotate(180deg);
		}
		.gv-hub-rail-toggle:hover{background:var(--gv-surface-2);}
		.gv-hub-rail.is-expanded .gv-hub-rail-toggle{transform:rotate(0deg);}

		.gv-hub-rail-nav{display:flex;flex-direction:column;gap:6px;padding:4px 10px;overflow-y:auto;overflow-x:hidden;}
		.gv-hub-rail-sep{height:1px;background:rgba(255,255,255,.12);margin:4px 4px;flex-shrink:0;}

		.gv-hub-rail-btn{
			display:flex;align-items:center;gap:10px;height:40px;border-radius:12px;padding:0 10px;
			background:transparent;border:0;color:var(--gv-rail-text);cursor:pointer;text-decoration:none;
			transition:background .15s ease,color .15s ease;white-space:nowrap;
		}
		.gv-hub-rail-icon{display:flex;align-items:center;justify-content:center;width:20px;flex-shrink:0;}
		.gv-hub-rail-emoji{font-size:15px;filter:grayscale(.3);}
		.gv-hub-rail-btn:hover{background:rgba(255,255,255,.08);color:#fff;}
		.gv-hub-rail-btn.is-active{background:var(--gv-rail-active-bg);color:var(--gv-rail-active-text);}
		.gv-hub-rail-btn.is-active .gv-hub-rail-emoji{filter:none;}

		.gv-hub-rail-label{
			font-size:12.5px;font-weight:700;max-width:0;opacity:0;overflow:hidden;
			transition:max-width .2s ease, opacity .15s ease;
		}
		.gv-hub-rail.is-expanded .gv-hub-rail-label{max-width:150px;opacity:1;}

		/* ---------- نوار بالا ---------- */
		.gv-hub-topbar{
			background:var(--gv-surface);border:1px solid var(--gv-border);border-radius:var(--gv-radius-lg);
			padding:16px 20px;box-shadow:var(--gv-shadow);
			display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
		}
		.gv-hub-topbar-title h1{margin:0;font-size:18px;font-weight:800;color:var(--gv-text);}
		.gv-hub-topbar-badge{display:inline-block;margin-top:5px;font-size:11.5px;font-weight:700;color:var(--gv-accent);background:var(--gv-accent-soft);border:1px solid transparent;padding:4px 11px;border-radius:20px;}
		.gv-hub-topbar-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}

		.gv-hub-search{
			display:flex;align-items:center;gap:8px;background:var(--gv-pill-bg);
			border:1px solid var(--gv-border);border-radius:20px;padding:7px 15px;color:var(--gv-text-muted);
			min-width:200px;
		}
		.gv-hub-search input{background:transparent;border:0;outline:0;color:var(--gv-text);font-family:inherit;font-size:12.5px;width:100%;}
		.gv-hub-search input::placeholder{color:var(--gv-text-muted);}

		.gv-theme-toggle{
			display:flex;align-items:center;gap:8px;background:var(--gv-pill-bg);
			border:1px solid var(--gv-border);border-radius:20px;padding:7px 10px;cursor:pointer;color:var(--gv-text);
			transition:background .18s ease;
		}
		.gv-theme-toggle:hover{background:var(--gv-surface-2);}
		.gv-theme-toggle-icon{display:flex;color:#E0A100;}
		.gv-theme-toggle-icon.gv-icon-moon{color:#7C8CF0;}
		.gv-theme-toggle-track{width:32px;height:17px;border-radius:20px;background:var(--gv-border);position:relative;flex-shrink:0;}
		.gv-theme-toggle-dot{position:absolute;top:2px;right:2px;width:13px;height:13px;border-radius:50%;background:var(--gv-accent);transition:transform .22s ease;}
		.gv-hub-wrap[data-theme="dark"] .gv-theme-toggle-dot{transform:translateX(-15px);}

		.gv-hub-avatar{
			width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;
			background:var(--gv-accent);color:var(--gv-bg);text-decoration:none;
		}

		/* ---------- ردیف دکمه‌های اقدام سریع ---------- */
		.gv-hub-cta-row{display:flex;gap:10px;flex-wrap:wrap;}
		.gv-hub-cta-solid{
			background:var(--gv-accent);color:var(--gv-bg) !important;font-weight:700;font-size:12.5px;
			padding:12px 20px;border-radius:16px;text-decoration:none;box-shadow:var(--gv-shadow);
			transition:transform .16s ease, filter .16s ease;
		}
		.gv-hub-cta-solid:hover{transform:translateY(-2px);filter:brightness(1.08);}
		.gv-hub-cta-outline{
			background:var(--gv-surface);border:1px solid var(--gv-border);color:var(--gv-text) !important;font-weight:700;font-size:12.5px;
			padding:12px 20px;border-radius:16px;text-decoration:none;transition:transform .16s ease, background .16s ease;
		}
		.gv-hub-cta-outline:hover{transform:translateY(-2px);background:var(--gv-surface-2);}
		.gv-hub-cta-buy{
			background:#b45309;color:#fff !important;font-weight:700;font-size:12.5px;
			padding:12px 20px;border-radius:16px;text-decoration:none;box-shadow:var(--gv-shadow);
			transition:transform .16s ease, filter .16s ease;
		}
		.gv-hub-cta-buy:hover{transform:translateY(-2px);filter:brightness(1.08);color:#fff !important;}

		/* ---------- ردیف کارت‌های خلاصه ---------- */
		.gv-hub-quickgrid{display:grid;grid-template-columns:1.1fr 1fr 1fr;gap:12px;}
		@media(max-width:820px){.gv-hub-quickgrid{grid-template-columns:1fr;}}
		.gv-hub-quickcard{background:var(--gv-surface);border:1px solid var(--gv-border);border-radius:var(--gv-radius-md);padding:16px 18px;box-shadow:var(--gv-shadow);display:flex;flex-direction:column;gap:10px;}
		.gv-hub-quickcard-head{display:flex;align-items:center;justify-content:space-between;font-size:12.5px;font-weight:700;color:var(--gv-text-muted);}
		.gv-hub-quickcard-head b{font-size:15px;color:var(--gv-text);font-weight:800;}
		.gv-hub-quickcard-sub{margin:0;font-size:11px;color:var(--gv-text-muted);}

		.gv-hub-progress{width:100%;height:8px;border-radius:20px;background:var(--gv-surface-2);border:1px solid var(--gv-border);overflow:hidden;}
		.gv-hub-progress-fill{height:100%;border-radius:20px;background:var(--gv-accent);}
		.gv-hub-quickcard-pill{align-self:flex-start;font-size:10.5px;font-weight:700;padding:4px 11px;border-radius:20px;}
		.gv-hub-quickcard-pill.is-on{background:rgba(34,197,94,.12);color:#1b9c56;}
		.gv-hub-quickcard-pill.is-off{background:rgba(239,68,68,.10);color:#dc4c4c;}
		.gv-hub-wrap[data-theme="dark"] .gv-hub-quickcard-pill.is-on{color:#5EE897;}

		.gv-hub-avatar-stack{display:flex;}
		.gv-hub-stack-avatar{
			width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;
			background:color-mix(in srgb, var(--gv-card-color) 22%, var(--gv-surface));border:2px solid var(--gv-surface);
			margin-inline-end:-10px;cursor:pointer;transition:transform .15s ease,z-index .15s ease;position:relative;
		}
		.gv-hub-stack-avatar:hover{transform:translateY(-3px);z-index:2;}
		.gv-hub-stack-avatar.is-active{outline:2px solid var(--gv-accent);outline-offset:1px;}

		/* ---------- ردیف کارمندها + دسترسی سریع (کنار هم) ---------- */
		.gv-hub-duo-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:stretch;}
		@media(max-width:820px){.gv-hub-duo-row{grid-template-columns:1fr;}}

		.gv-hub-team-quickcard{justify-content:flex-start;}
		.gv-hub-team-avatar{font-weight:800;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.15);}
		.gv-hub-team-tooltip{
			position:absolute;bottom:calc(100% + 10px);right:50%;transform:translateX(50%) translateY(4px);
			background:var(--gv-text);color:var(--gv-surface);border-radius:10px;padding:9px 12px;
			min-width:160px;display:flex;flex-direction:column;gap:3px;font-size:11px;line-height:1.6;
			opacity:0;visibility:hidden;pointer-events:none;transition:opacity .15s ease,transform .15s ease;z-index:5;
			box-shadow:var(--gv-shadow-lg);text-align:right;
		}
		.gv-hub-team-tooltip b{font-size:12px;font-weight:800;color:var(--gv-surface);}
		.gv-hub-team-tooltip::after{content:"";position:absolute;top:100%;right:50%;transform:translateX(50%);border:6px solid transparent;border-top-color:var(--gv-text);}
		.gv-hub-team-avatar:hover .gv-hub-team-tooltip{opacity:1;visibility:visible;transform:translateX(50%) translateY(0);}

		/* ---------- کارت تیره‌ی دسترسی سریع ---------- */
		.gv-hub-notes{background:linear-gradient(160deg,var(--gv-rail-a),var(--gv-rail-b));color:#fff;border-radius:var(--gv-radius-md);padding:12px 14px 14px;box-shadow:var(--gv-shadow-lg);}
		.gv-hub-notes-head{font-size:11.5px;font-weight:800;margin-bottom:7px;}
		.gv-hub-notes-items{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:9px;}
		.gv-hub-notes-item{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:6px 11px;text-decoration:none;color:#fff;transition:background .15s ease,transform .15s ease;flex:1;min-width:128px;}
		.gv-hub-notes-item:hover{background:rgba(255,255,255,.13);transform:translateY(-2px);color:#fff;}
		.gv-hub-notes-icon{font-size:14px;}
		.gv-hub-notes-text{display:flex;flex-direction:column;}
		.gv-hub-notes-text b{font-size:10px;font-weight:700;color:#8CE9C1;}
		.gv-hub-notes-text i{font-style:normal;font-size:11px;color:#E7EFEA;direction:ltr;text-align:right;}
		.gv-hub-notes-cta{display:inline-block;background:#fff;color:#0e4037 !important;font-weight:800;font-size:11px;padding:8px 16px;border-radius:10px;text-decoration:none;transition:filter .15s ease,transform .15s ease;}
		.gv-hub-notes-cta:hover{filter:brightness(.96);transform:translateY(-2px);}

		/* ---------- کارت اصلی فهرست ---------- */
		.gv-hub-table-card{background:var(--gv-surface);border:1px solid var(--gv-border);border-radius:var(--gv-radius-lg);padding:18px 20px 20px;box-shadow:var(--gv-shadow);}
		.gv-hub-table-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
		.gv-hub-table-head > span{font-size:14.5px;font-weight:800;color:var(--gv-text);}
		.gv-hub-sort{display:flex;align-items:center;gap:6px;background:var(--gv-pill-bg);border:1px solid var(--gv-border);border-radius:20px;padding:7px 12px;color:var(--gv-text-muted);}
		.gv-hub-sort select{background:transparent;border:0;outline:0;color:var(--gv-text);font-family:inherit;font-size:12px;cursor:pointer;}
		.gv-hub-sort select option{color:#1B2B24;}

		.gv-hub-sections{display:flex;flex-direction:column;gap:12px;}
		.gv-hub-section{position:relative;border:1px solid var(--gv-border);border-radius:var(--gv-radius-md);overflow:hidden;background:var(--gv-surface-2);}
		.gv-hub-section-head{
			width:100%;display:flex;align-items:center;gap:12px;padding:12px 14px;
			background:transparent;border:0;cursor:pointer;text-align:inherit;color:inherit;
		}
		.gv-hub-section-icon{width:36px;height:36px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:16px;background:color-mix(in srgb, var(--gv-section-color) 16%, transparent);}
		.gv-hub-section-title{display:flex;flex-direction:column;text-align:right;}
		.gv-hub-section-name{font-size:13.5px;font-weight:800;color:var(--gv-text);}
		.gv-hub-section-sub{font-size:11px;color:var(--gv-text-muted);margin-top:1px;}
		.gv-hub-section-count{margin-inline-start:auto;font-size:11px;color:var(--gv-text-muted);background:var(--gv-surface);border:1px solid var(--gv-border);padding:4px 11px;border-radius:20px;white-space:nowrap;}
		.gv-hub-section-chevron{flex-shrink:0;color:var(--gv-text-muted);transition:transform .18s ease;}
		.gv-hub-section.is-collapsed .gv-hub-section-chevron{transform:rotate(-90deg);}
		.gv-hub-section.is-collapsed .gv-hub-grid{display:none;}

		.gv-hub-empty{text-align:center;color:var(--gv-text-muted);font-size:13px;padding:40px 0;}

		/* ---------- گرید کارت‌های افزونه ---------- */
		.gv-hub-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:0 14px 14px;}
		@media(max-width:1000px){.gv-hub-grid{grid-template-columns:repeat(2,1fr);}}
		@media(max-width:560px){.gv-hub-grid{grid-template-columns:1fr;}}

		.gv-hub-card{
			display:flex;flex-direction:column;background:var(--gv-surface);border:1px solid var(--gv-border);
			border-radius:var(--gv-radius-sm);padding:14px 14px 12px;text-decoration:none;color:inherit;
			box-shadow:var(--gv-shadow);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;
			position:relative;
		}
		.gv-hub-card:hover{transform:translateY(-3px);box-shadow:var(--gv-shadow-lg);border-color:color-mix(in srgb, var(--gv-card-color) 45%, var(--gv-border));color:inherit;}
		.gv-hub-card-top{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
		.gv-hub-card-icon{font-size:16px;width:34px;height:34px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:color-mix(in srgb, var(--gv-card-color) 14%, transparent);}
		.gv-hub-card-tag{font-size:10px;font-weight:700;color:var(--gv-tag-color);background:color-mix(in srgb, var(--gv-tag-color) 10%, transparent);border:1px solid color-mix(in srgb, var(--gv-tag-color) 30%, transparent);border-radius:20px;padding:3px 9px;white-space:nowrap;}
		.gv-hub-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-inline-start:auto;}
		.gv-hub-dot-on{background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.18);}
		.gv-hub-dot-off{background:var(--gv-border);}

		.gv-hub-card h3{font-size:13px;margin:0 0 4px;color:var(--gv-text);font-weight:700;line-height:1.5;}
		.gv-hub-card p{font-size:11.2px;color:var(--gv-text-muted);line-height:1.8;margin:0 0 10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
		.gv-hub-card-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:auto;padding-top:10px;border-top:1px solid var(--gv-border);}
		.gv-hub-card-btn{display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;background:var(--gv-accent);color:var(--gv-bg);padding:6px 11px;border-radius:20px;transition:filter .16s ease;}
		.gv-hub-card-btn svg{transform:rotate(180deg);}
		.gv-hub-card:hover .gv-hub-card-btn{filter:brightness(1.1);}

		.gv-hub-card-switch{display:flex;align-items:center;gap:5px;}
		.gv-hub-status-text{font-size:9.5px;font-weight:700;color:var(--gv-text-muted);white-space:nowrap;}
		.gv-hub-status-text.is-on{color:#1b9c56;}
		.gv-hub-wrap[data-theme="dark"] .gv-hub-status-text.is-on{color:#5EE897;}

		.gv-hub-toggle{position:relative;display:inline-block;width:30px;height:17px;flex-shrink:0;cursor:pointer;}
		.gv-hub-toggle input{position:absolute;inset:0;opacity:0;margin:0;cursor:pointer;z-index:2;}
		.gv-hub-toggle-track{position:absolute;inset:0;background:var(--gv-border);border-radius:20px;transition:background .18s ease;}
		.gv-hub-toggle-thumb{position:absolute;top:2px;inset-inline-start:2px;width:13px;height:13px;background:var(--gv-surface);border-radius:50%;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:inset-inline-start .18s ease;}
		.gv-hub-toggle input:checked ~ .gv-hub-toggle-track{background:#22c55e;}
		.gv-hub-toggle input:checked ~ .gv-hub-toggle-track .gv-hub-toggle-thumb{inset-inline-start:15px;background:#fff;}
		.gv-hub-toggle input:focus-visible ~ .gv-hub-toggle-track{box-shadow:0 0 0 2px rgba(34,197,94,.35);}
		.gv-hub-toggle input:disabled{cursor:wait;}
		.gv-hub-toggle input:disabled ~ .gv-hub-toggle-track{opacity:.55;}
		.gv-hub-card.gv-hub-card-busy{opacity:.65;pointer-events:none;}

		.gv-hub-card[hidden]{display:none;}
		.gv-hub-section[hidden]{display:none;}

		.gv-hub-footer{text-align:center;color:var(--gv-text-muted);font-size:12px;margin:2px 0 6px;}

		/* ---------- ریسپانسیو: ریل به نوار افقی بالای صفحه تبدیل می‌شود ---------- */
		@media(max-width:900px){
			.gv-hub-shell{flex-direction:column;}
			.gv-hub-rail{
				width:100% !important;min-height:0;flex-direction:row;position:relative;top:0;
				padding:10px 14px;align-items:center;
			}
			.gv-hub-rail-top{padding:0;}
			.gv-hub-rail-toggle{position:static;margin-inline-start:8px;transform:none !important;}
			.gv-hub-rail-nav{flex-direction:row;flex:1;overflow-x:auto;padding:0 10px;}
			.gv-hub-rail-sep{width:1px;height:24px;margin:0 4px;}
			.gv-hub-rail.is-expanded .gv-hub-rail-label{max-width:120px;}
		}
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

			var activeBadge = document.querySelector( '.gv-hub-topbar-badge' );
			if ( activeBadge ) {
				var m = activeBadge.textContent.match(/\d+/g);
				if ( m && m.length >= 2 ) {
					var current = parseInt( m[0], 10 ) || 0;
					var next    = newState ? current + 1 : Math.max( 0, current - 1 );
					activeBadge.textContent = next + ' افزونه فعال از ' + m[1];
				}
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

		/* ---- باز/بسته کردن نوار کناری ---- */
		var rail       = document.getElementById('gv-hub-rail');
		var railToggle = document.getElementById('gv-hub-rail-toggle');
		if (railToggle && rail) {
			railToggle.addEventListener('click', function(){
				rail.classList.toggle('is-expanded');
			});
		}

		/* ---- فیلتر دسته‌بندی (ریل کناری + آواتارها) + جستجو + مرتب‌سازی ---- */
		var filterButtons = Array.prototype.slice.call(document.querySelectorAll('.gv-hub-filter'));
		var sections       = Array.prototype.slice.call(document.querySelectorAll('.gv-hub-section'));
		var searchInput    = document.getElementById('gv-hub-search-input');
		var sortSelect     = document.getElementById('gv-hub-sort-select');
		var emptyState     = document.getElementById('gv-hub-empty');
		var tableCard      = document.querySelector('.gv-hub-table-card');
		var activeFilter   = '<?php echo esc_js( $default_filter ); ?>';

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
				var filterValue = btn.getAttribute('data-filter');

				filterButtons.forEach(function(b){
					b.classList.toggle('is-active', b.getAttribute('data-filter') === filterValue);
				});
				activeFilter = filterValue;
				applyFilters();

				var GV_SCROLL_OFFSET = 90; // یکم بالاتر از خود کارت متوقف شود تا عنوان «همه‌ی افزونه‌ها» هم دیده شود

				if (activeFilter !== 'all') {
					var targetSection = document.querySelector('.gv-hub-section[data-section="' + activeFilter + '"]');
					if (targetSection) {
						targetSection.classList.remove('is-collapsed');
						var targetTop = targetSection.getBoundingClientRect().top + window.pageYOffset - GV_SCROLL_OFFSET;
						window.scrollTo({ top: targetTop, behavior: 'smooth' });
					}
				} else if (tableCard) {
					var cardTop = tableCard.getBoundingClientRect().top + window.pageYOffset - GV_SCROLL_OFFSET;
					window.scrollTo({ top: cardTop, behavior: 'smooth' });
				}
			});
		});

		if (searchInput) {
			searchInput.addEventListener('input', applyFilters);
		}

		if (sortSelect) {
			sortSelect.addEventListener('change', applySort);
		}

		/* ---- باز/بسته‌کردن هر گروه دسته‌بندی در فهرست ---- */
		document.querySelectorAll('.gv-hub-section-head').forEach(function(head){
			head.addEventListener('click', function(){
				head.closest('.gv-hub-section').classList.toggle('is-collapsed');
			});
		});

		// اعمال فیلتر اولیه (اگر از روی منوی دسته‌بندی وارد شده باشیم)
		applyFilters();
		if ('all' !== activeFilter) {
			var initialSection = document.querySelector('.gv-hub-section[data-section="' + activeFilter + '"]');
			if (initialSection) { initialSection.classList.remove('is-collapsed'); }
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