<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — استایل جدول‌ها، سوالات متداول و ساده‌سازی تسویه‌حساب
 *  ۱) ۳ تم آماده برای استایل تمام جدول‌های سایت + حالت سفارشی (+ رنگ پس‌زمینه)
 *  ۲) ۳ تم آماده برای استایل آکاردئونی سوالات متداول + Schema.org (FAQPage)
 *     - استایل/تم سراسری است، اما محتوای سوال و جواب را می‌توان در هر صفحه
 *       جداگانه تعریف کرد (با [gv_faq]...[gv_faq_item]...[/gv_faq_item]...[/gv_faq])
 *  ۳) روشن/خاموش کردن فیلدهای فرم تسویه‌حساب ووکامرس
 * ==========================================================
 */
define( 'GV_TBL_OPT', 'gv_table_style_settings' );
define( 'GV_TBL_NONCE', 'gv_tbl_nonce_action' );
define( 'GV_FAQ_OPT', 'gv_faq_style_settings' );

/* ==========================================================================
   ۱) تنظیمات پیش‌فرض و تم‌های آماده — جدول‌ها
   ========================================================================== */

function gv_tbl_default_settings() {
	return array(
		'enabled'        => 1,
		'theme'          => 'modern_blue', // modern_blue | dark_elegant | minimal_green | custom
		'custom_colors'  => array(
			'header_bg'    => '#2563eb',
			'header_text'  => '#ffffff',
			'border_color' => '#e2e8f0',
			'body_bg'      => '#ffffff', // پس‌زمینه اصلی بدنه جدول
			'row_alt_bg'   => '#f8fafc',
			'row_hover_bg' => '#eff6ff',
			'text_color'   => '#1e293b',
			'radius'       => 10,
		),
		// فیلدهای فرم تسویه‌حساب ووکامرس (پیش‌فرض همه روشن)
		'checkout_fields' => gv_tbl_default_checkout_fields(),
	);
}

function gv_tbl_default_checkout_fields() {
	return array(
		'company'   => 1,
		'address_1' => 1, // خیابان / آدرس اصلی
		'address_2' => 1, // خیابان خط دوم (پلاک/واحد)
		'city'      => 1, // شهر
		'country'   => 1,
		'state'     => 1, // استان
		'postcode'  => 1, // کد پستی
		'phone'     => 1,
		'email'     => 1, // ایمیل
		'order_notes' => 1,
		'ship_to_different' => 1,
	);
}

/** پالت رنگی ۳ تم آماده جدول‌ها */
function gv_tbl_presets() {
	return array(
		'modern_blue' => array(
			'label'        => 'مدرن آبی',
			'desc'         => 'استایل تمیز و کسب‌وکاری با هدر آبی و ردیف‌های راه‌راه ملایم',
			'header_bg'    => '#2563eb',
			'header_text'  => '#ffffff',
			'border_color' => '#e2e8f0',
			'body_bg'      => '#ffffff',
			'row_alt_bg'   => '#f8fafc',
			'row_hover_bg' => '#eff6ff',
			'text_color'   => '#1e293b',
			'radius'       => 10,
		),
		'dark_elegant' => array(
			'label'        => 'شیک تیره',
			'desc'         => 'استایل تیره و لوکس، مناسب سایت‌های فروشگاهی و حرفه‌ای',
			'header_bg'    => '#111827',
			'header_text'  => '#f9fafb',
			'border_color' => '#1f2937',
			'body_bg'      => '#161f2e',
			'row_alt_bg'   => '#1f2937',
			'row_hover_bg' => '#374151',
			'text_color'   => '#e5e7eb',
			'radius'       => 12,
		),
		'minimal_green' => array(
			'label'        => 'مینیمال سبز',
			'desc'         => 'ساده و تازه، مناسب سایت‌های محتوایی و فروشگاه‌های ارگانیک',
			'header_bg'    => '#059669',
			'header_text'  => '#ffffff',
			'border_color' => '#d1fae5',
			'body_bg'      => '#ffffff',
			'row_alt_bg'   => '#f0fdf4',
			'row_hover_bg' => '#dcfce7',
			'text_color'   => '#14532d',
			'radius'       => 8,
		),
	);
}

function gv_tbl_get_settings() {
	$saved    = get_option( GV_TBL_OPT, array() );
	$defaults = gv_tbl_default_settings();
	$settings = wp_parse_args( $saved, $defaults );
	if ( isset( $saved['custom_colors'] ) && is_array( $saved['custom_colors'] ) ) {
		$settings['custom_colors'] = wp_parse_args( $saved['custom_colors'], $defaults['custom_colors'] );
	}
	if ( isset( $saved['checkout_fields'] ) && is_array( $saved['checkout_fields'] ) ) {
		$settings['checkout_fields'] = wp_parse_args( $saved['checkout_fields'], $defaults['checkout_fields'] );
	}
	return $settings;
}

/** رنگ‌های فعال (بر اساس تم انتخابی یا سفارشی) — جدول‌ها */
function gv_tbl_get_active_palette( $settings ) {
	if ( 'custom' === $settings['theme'] ) {
		return $settings['custom_colors'];
	}
	$presets = gv_tbl_presets();
	return isset( $presets[ $settings['theme'] ] ) ? $presets[ $settings['theme'] ] : $presets['modern_blue'];
}

/* ==========================================================================
   ۱ب) تنظیمات پیش‌فرض و تم‌های آماده — سوالات متداول (FAQ)
   ========================================================================== */

function gv_faq_default_settings() {
	return array(
		'enabled'       => 1,
		'show_schema'   => 1, // خروجی Schema.org FAQPage برای موتورهای جستجو
		'theme'         => 'modern_blue',
		'custom_colors' => array(
			'question_bg'   => '#2563eb',
			'question_text' => '#ffffff',
			'answer_bg'     => '#ffffff',
			'answer_text'   => '#1e293b',
			'border_color'  => '#e2e8f0',
			'radius'        => 10,
		),
		// این آیتم‌ها «پیش‌فرض عمومی» هستند؛ اگر در یک صفحه با
		// [gv_faq_item] سوال/جواب اختصاصی تعریف شود، همان اولویت دارد.
		'items' => array(
			array(
				'question' => 'چگونه می‌توانم سفارش خود را پیگیری کنم؟',
				'answer'   => 'پس از ثبت سفارش، لینک پیگیری از طریق ایمیل یا پیامک برای شما ارسال می‌شود.',
			),
			array(
				'question' => 'آیا امکان مرجوعی کالا وجود دارد؟',
				'answer'   => 'بله، تا ۷ روز پس از دریافت کالا امکان مرجوعی طبق قوانین فروشگاه وجود دارد.',
			),
		),
	);
}

/** پالت رنگی ۳ تم آماده سوالات متداول */
function gv_faq_presets() {
	return array(
		'modern_blue' => array(
			'label'         => 'مدرن آبی',
			'desc'          => 'دکمه سوال آبی با پاسخ روشن و تمیز',
			'question_bg'   => '#2563eb',
			'question_text' => '#ffffff',
			'answer_bg'     => '#ffffff',
			'answer_text'   => '#1e293b',
			'border_color'  => '#e2e8f0',
			'radius'        => 10,
		),
		'dark_elegant' => array(
			'label'         => 'شیک تیره',
			'desc'          => 'استایل تیره برای سایت‌های حرفه‌ای و شیک',
			'question_bg'   => '#111827',
			'question_text' => '#f9fafb',
			'answer_bg'     => '#1f2937',
			'answer_text'   => '#e5e7eb',
			'border_color'  => '#374151',
			'radius'        => 12,
		),
		'minimal_green' => array(
			'label'         => 'مینیمال سبز',
			'desc'          => 'ساده و تازه، مناسب سایت‌های محتوایی',
			'question_bg'   => '#059669',
			'question_text' => '#ffffff',
			'answer_bg'     => '#f0fdf4',
			'answer_text'   => '#14532d',
			'border_color'  => '#d1fae5',
			'radius'        => 8,
		),
	);
}

function gv_faq_get_settings() {
	$saved    = get_option( GV_FAQ_OPT, array() );
	$defaults = gv_faq_default_settings();
	$settings = wp_parse_args( $saved, $defaults );
	if ( isset( $saved['custom_colors'] ) && is_array( $saved['custom_colors'] ) ) {
		$settings['custom_colors'] = wp_parse_args( $saved['custom_colors'], $defaults['custom_colors'] );
	}
	if ( isset( $saved['items'] ) && is_array( $saved['items'] ) ) {
		$settings['items'] = $saved['items'];
	}
	return $settings;
}

function gv_faq_get_active_palette( $settings ) {
	if ( 'custom' === $settings['theme'] ) {
		return $settings['custom_colors'];
	}
	$presets = gv_faq_presets();
	return isset( $presets[ $settings['theme'] ] ) ? $presets[ $settings['theme'] ] : $presets['modern_blue'];
}

/* ==========================================================================
   ۱ج) بررسی فعال بودن ووکامرس (پایدار در برابر تایمینگ بارگذاری پلاگین‌ها)
   ========================================================================== */

function gv_tbl_is_woocommerce_active() {
	if ( function_exists( 'WC' ) || class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' ) ) {
		return true;
	}
	$active_plugins = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	foreach ( $active_plugins as $plugin ) {
		if ( false !== strpos( $plugin, 'woocommerce.php' ) ) {
			return true;
		}
	}
	return false;
}

/* ==========================================================================
   ۲) منوی مدیریت
   ========================================================================== */

add_action( 'admin_menu', 'gv_tbl_admin_menu' );
function gv_tbl_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'استایل جدول‌ها | Groot Vision',
		'🎨 استایل جدول‌ها',
		'manage_options',
		'gv-table-style',
		'gv_tbl_render_admin_page'
	);
}

add_action( 'admin_enqueue_scripts', 'gv_tbl_admin_assets' );
function gv_tbl_admin_assets( $hook ) {
	if ( strpos( $hook, 'gv-table-style' ) === false ) { return; }
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script( 'wp-color-picker', "jQuery(function($){
		$('.gvtbl-color-field').wpColorPicker();

		$('.gvtbl-tab-btn').on('click', function(e){
			e.preventDefault();
			var target = $(this).data('tab');
			$('.gvtbl-tab-btn').removeClass('is-active');
			$(this).addClass('is-active');
			$('.gvtbl-tab-panel').removeClass('is-active').hide();
			$('#gvtbl-tab-' + target).addClass('is-active').show();
		});

		$('input[name=\"theme\"]').on('change', function(){
			if ($(this).val() === 'custom') {
				$('#gvtbl-custom-box').slideDown(150);
			} else {
				$('#gvtbl-custom-box').slideUp(150);
			}
		});

		$('input[name=\"faq_theme\"]').on('change', function(){
			if ($(this).val() === 'custom') {
				$('#gvfaq-custom-box').slideDown(150);
			} else {
				$('#gvfaq-custom-box').slideUp(150);
			}
		});

		var gvfaqIndex = $('#gvfaq-items-wrap .gvfaq-item-row').length;
		$('#gvfaq-add-row').on('click', function(){
			var idx = 'new_' + (gvfaqIndex++) + '_' + Date.now();
			var row = $(
				'<div class=\"gvfaq-item-row\">' +
					'<div class=\"gvfaq-item-fields\">' +
						'<input type=\"text\" name=\"faq_items[' + idx + '][question]\" placeholder=\"متن سوال\" class=\"gvfaq-q-input\">' +
						'<textarea name=\"faq_items[' + idx + '][answer]\" placeholder=\"متن پاسخ\" rows=\"3\" class=\"gvfaq-a-input\"></textarea>' +
					'</div>' +
					'<button type=\"button\" class=\"button gvfaq-remove-row\">🗑️ حذف</button>' +
				'</div>'
			);
			$('#gvfaq-items-wrap').append(row);
		});
		$(document).on('click', '.gvfaq-remove-row', function(){
			$(this).closest('.gvfaq-item-row').remove();
		});
	});" );
}

add_action( 'admin_post_gv_tbl_save_settings', 'gv_tbl_save_settings' );
function gv_tbl_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_TBL_NONCE );

	/* ---------- ذخیره تنظیمات جدول‌ها ---------- */
	$defaults = gv_tbl_default_settings();

	$theme = isset( $_POST['theme'] ) ? sanitize_key( $_POST['theme'] ) : 'modern_blue';
	$valid_themes = array_merge( array_keys( gv_tbl_presets() ), array( 'custom' ) );
	if ( ! in_array( $theme, $valid_themes, true ) ) { $theme = 'modern_blue'; }

	$custom_colors = array(
		'header_bg'    => sanitize_hex_color( $_POST['custom_colors']['header_bg'] ?? '' ) ?: $defaults['custom_colors']['header_bg'],
		'header_text'  => sanitize_hex_color( $_POST['custom_colors']['header_text'] ?? '' ) ?: $defaults['custom_colors']['header_text'],
		'border_color' => sanitize_hex_color( $_POST['custom_colors']['border_color'] ?? '' ) ?: $defaults['custom_colors']['border_color'],
		'body_bg'      => sanitize_hex_color( $_POST['custom_colors']['body_bg'] ?? '' ) ?: $defaults['custom_colors']['body_bg'],
		'row_alt_bg'   => sanitize_hex_color( $_POST['custom_colors']['row_alt_bg'] ?? '' ) ?: $defaults['custom_colors']['row_alt_bg'],
		'row_hover_bg' => sanitize_hex_color( $_POST['custom_colors']['row_hover_bg'] ?? '' ) ?: $defaults['custom_colors']['row_hover_bg'],
		'text_color'   => sanitize_hex_color( $_POST['custom_colors']['text_color'] ?? '' ) ?: $defaults['custom_colors']['text_color'],
		'radius'       => isset( $_POST['custom_colors']['radius'] ) ? max( 0, min( 30, intval( $_POST['custom_colors']['radius'] ) ) ) : 10,
	);

	$checkout_fields = array();
	foreach ( array_keys( $defaults['checkout_fields'] ) as $key ) {
		$checkout_fields[ $key ] = isset( $_POST['checkout_fields'][ $key ] ) ? 1 : 0;
	}

	$settings = array(
		'enabled'         => isset( $_POST['enabled'] ) ? 1 : 0,
		'theme'           => $theme,
		'custom_colors'   => $custom_colors,
		'checkout_fields' => $checkout_fields,
	);

	update_option( GV_TBL_OPT, $settings );

	/* ---------- ذخیره تنظیمات سوالات متداول (FAQ) ---------- */
	$faq_defaults = gv_faq_default_settings();

	$faq_theme = isset( $_POST['faq_theme'] ) ? sanitize_key( $_POST['faq_theme'] ) : 'modern_blue';
	$valid_faq_themes = array_merge( array_keys( gv_faq_presets() ), array( 'custom' ) );
	if ( ! in_array( $faq_theme, $valid_faq_themes, true ) ) { $faq_theme = 'modern_blue'; }

	$faq_custom_colors = array(
		'question_bg'   => sanitize_hex_color( $_POST['faq_custom_colors']['question_bg'] ?? '' ) ?: $faq_defaults['custom_colors']['question_bg'],
		'question_text' => sanitize_hex_color( $_POST['faq_custom_colors']['question_text'] ?? '' ) ?: $faq_defaults['custom_colors']['question_text'],
		'answer_bg'     => sanitize_hex_color( $_POST['faq_custom_colors']['answer_bg'] ?? '' ) ?: $faq_defaults['custom_colors']['answer_bg'],
		'answer_text'   => sanitize_hex_color( $_POST['faq_custom_colors']['answer_text'] ?? '' ) ?: $faq_defaults['custom_colors']['answer_text'],
		'border_color'  => sanitize_hex_color( $_POST['faq_custom_colors']['border_color'] ?? '' ) ?: $faq_defaults['custom_colors']['border_color'],
		'radius'        => isset( $_POST['faq_custom_colors']['radius'] ) ? max( 0, min( 30, intval( $_POST['faq_custom_colors']['radius'] ) ) ) : 10,
	);

	$faq_items = array();
	if ( isset( $_POST['faq_items'] ) && is_array( $_POST['faq_items'] ) ) {
		foreach ( $_POST['faq_items'] as $row ) {
			$q = isset( $row['question'] ) ? sanitize_text_field( wp_unslash( $row['question'] ) ) : '';
			$a = isset( $row['answer'] ) ? wp_kses_post( wp_unslash( $row['answer'] ) ) : '';
			if ( '' === $q && '' === $a ) { continue; }
			$faq_items[] = array( 'question' => $q, 'answer' => $a );
		}
	}

	$faq_settings = array(
		'enabled'       => isset( $_POST['faq_enabled'] ) ? 1 : 0,
		'show_schema'   => isset( $_POST['faq_show_schema'] ) ? 1 : 0,
		'theme'         => $faq_theme,
		'custom_colors' => $faq_custom_colors,
		'items'         => $faq_items,
	);

	update_option( GV_FAQ_OPT, $faq_settings );

	wp_safe_redirect( admin_url( 'admin.php?page=gv-table-style&updated=1&tab=' . ( isset( $_POST['current_tab'] ) ? sanitize_key( $_POST['current_tab'] ) : 'theme' ) ) );
	exit;
}

function gv_tbl_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s       = gv_tbl_get_settings();
	$presets = gv_tbl_presets();
	$faq_s       = gv_faq_get_settings();
	$faq_presets = gv_faq_presets();
	$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'theme';
	$has_wc  = gv_tbl_is_woocommerce_active();
	?>
	<div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif;">
		<style>
			.gvtbl-header{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,#7c3aed,#a855f7);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;box-shadow:0 6px 20px rgba(0,0,0,.12);}
			.gvtbl-header h1{margin:0;font-size:20px;color:#fff;}
			.gvtbl-header span{opacity:.85;font-size:12.5px;}
			.gvtbl-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap;}
			.gvtbl-tab-btn{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:9px 18px;cursor:pointer;font-size:13px;font-family:inherit;font-weight:600;}
			.gvtbl-tab-btn.is-active{background:#7c3aed;color:#fff;border-color:#7c3aed;}
			.gvtbl-tab-panel{display:none;}
			.gvtbl-tab-panel.is-active{display:block;}
			.gvtbl-card{background:#fff;border:1px solid #e2e4e7;border-radius:12px;padding:20px 22px;margin-bottom:18px;box-shadow:0 1px 3px rgba(0,0,0,.04);max-width:860px;}
			.gvtbl-card h2{margin-top:0;font-size:15px;border-bottom:1px solid #f0f0f1;padding-bottom:10px;}
			.gvtbl-hint{color:#666;font-size:12px;margin-top:6px;line-height:1.9;}
			.gvtbl-theme-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
			@media(max-width:900px){.gvtbl-theme-grid{grid-template-columns:1fr;}}
			.gvtbl-theme-card{border:2px solid #e2e8f0;border-radius:12px;padding:14px;cursor:pointer;position:relative;transition:.15s;}
			.gvtbl-theme-card:hover{border-color:#c4b5fd;}
			.gvtbl-theme-card input{position:absolute;top:12px;left:12px;}
			.gvtbl-theme-card.is-checked{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.15);}
			.gvtbl-theme-preview{border-radius:8px;overflow:hidden;margin-bottom:10px;border:1px solid #e2e8f0;}
			.gvtbl-theme-preview table{width:100%;border-collapse:collapse;font-size:11px;}
			.gvtbl-theme-preview th{padding:6px 8px;text-align:right;}
			.gvtbl-theme-preview td{padding:6px 8px;text-align:right;border-top:1px solid;}
			.gvtbl-theme-card label{font-weight:700;font-size:13px;display:block;}
			.gvtbl-theme-card p{font-size:11.5px;color:#64748b;margin:4px 0 0;}
			.gvtbl-color-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
			@media(max-width:800px){.gvtbl-color-grid{grid-template-columns:1fr 1fr;}}
			.gvtbl-color-item label{display:block;font-size:12.5px;font-weight:600;margin-bottom:6px;}
			.gvtbl-toggle-row{display:flex;align-items:center;gap:8px;background:#f6f7f7;padding:10px 14px;border-radius:8px;margin-bottom:10px;}
			.gvtbl-toggle-row label{margin:0;font-weight:600;font-size:13px;}
			.gvtbl-note{background:#f5f3ff;border:1px solid #c4b5fd;color:#5b21b6;padding:12px 16px;border-radius:10px;font-size:12.5px;line-height:1.9;margin-bottom:18px;max-width:860px;}
			.gvtbl-warn{background:#fef3c7;border:1px solid #fbbf24;color:#92400e;padding:12px 16px;border-radius:10px;font-size:12.5px;line-height:1.9;margin-bottom:18px;max-width:860px;}
			.gvtbl-shortcode-box{background:#0f172a;color:#a7f3d0;padding:10px 14px;border-radius:8px;font-family:monospace;display:inline-block;direction:ltr;margin-top:8px;white-space:pre;}
			.gvfaq-item-row{display:flex;gap:10px;align-items:flex-start;background:#f6f7f7;padding:12px;border-radius:8px;margin-bottom:10px;}
			.gvfaq-item-fields{flex:1;display:flex;flex-direction:column;gap:8px;}
			.gvfaq-item-fields input, .gvfaq-item-fields textarea{width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-family:inherit;}
		</style>

		<div class="gvtbl-header">
			<h1>🎨 استایل جدول‌ها، سوالات متداول و تسویه‌حساب — Groot Vision</h1>
			<span>ظاهر یکپارچه برای جدول‌ها، بخش FAQ و فرم تسویه‌حساب کوتاه‌تر</span>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>✅ تنظیمات با موفقیت ذخیره شد.</p></div>
		<?php endif; ?>

		<div class="gvtbl-tabs">
			<button type="button" class="gvtbl-tab-btn <?php echo 'theme' === $tab ? 'is-active' : ''; ?>" data-tab="theme">🎨 تم جدول‌ها</button>
			<button type="button" class="gvtbl-tab-btn <?php echo 'faq' === $tab ? 'is-active' : ''; ?>" data-tab="faq">❓ سوالات متداول</button>
			<button type="button" class="gvtbl-tab-btn <?php echo 'checkout' === $tab ? 'is-active' : ''; ?>" data-tab="checkout">🧾 فرم تسویه‌حساب ووکامرس</button>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gv_tbl_save_settings">
			<input type="hidden" name="current_tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( GV_TBL_NONCE ); ?>

			<!-- تب تم جدول‌ها -->
			<div class="gvtbl-tab-panel <?php echo 'theme' === $tab ? 'is-active' : ''; ?>" id="gvtbl-tab-theme" <?php echo 'theme' === $tab ? '' : 'style="display:none;"'; ?>>

				<div class="gvtbl-note">
					🎨 با انتخاب یکی از تم‌های زیر و ذخیره، رنگ‌بندی و شکل <b>همه جدول‌های سایت</b> (جدول‌های محتوا، جدول‌های ووکامرس مثل سبد خرید و سفارش‌ها، و جدول‌های بلوک گوتنبرگ) یکدست و زیبا می‌شود.
				</div>

				<div class="gvtbl-card">
					<h2>وضعیت کلی</h2>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>>
						<label>فعال‌سازی استایل یکپارچه جدول‌ها در سایت</label>
					</div>
				</div>

				<div class="gvtbl-card">
					<h2>انتخاب تم</h2>
					<div class="gvtbl-theme-grid">
						<?php foreach ( $presets as $key => $p ) :
							$checked = ( $s['theme'] === $key );
							?>
							<label class="gvtbl-theme-card <?php echo $checked ? 'is-checked' : ''; ?>">
								<input type="radio" name="theme" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?>>
								<div class="gvtbl-theme-preview" style="border-radius:<?php echo esc_attr( $p['radius'] ); ?>px;">
									<table>
										<thead>
											<tr style="background:<?php echo esc_attr( $p['header_bg'] ); ?>;color:<?php echo esc_attr( $p['header_text'] ); ?>;">
												<th>ستون ۱</th><th>ستون ۲</th><th>ستون ۳</th>
											</tr>
										</thead>
										<tbody style="background:<?php echo esc_attr( $p['body_bg'] ); ?>;color:<?php echo esc_attr( $p['text_color'] ); ?>;">
											<tr style="border-color:<?php echo esc_attr( $p['border_color'] ); ?>;"><td>داده</td><td>داده</td><td>داده</td></tr>
											<tr style="border-color:<?php echo esc_attr( $p['border_color'] ); ?>;background:<?php echo esc_attr( $p['row_alt_bg'] ); ?>;"><td>داده</td><td>داده</td><td>داده</td></tr>
										</tbody>
									</table>
								</div>
								<span><?php echo esc_html( $p['label'] ); ?></span>
								<p><?php echo esc_html( $p['desc'] ); ?></p>
							</label>
						<?php endforeach; ?>

						<label class="gvtbl-theme-card <?php echo 'custom' === $s['theme'] ? 'is-checked' : ''; ?>">
							<input type="radio" name="theme" value="custom" <?php checked( 'custom' === $s['theme'] ); ?>>
							<div class="gvtbl-theme-preview" style="height:60px;display:flex;align-items:center;justify-content:center;background:#faf5ff;">
								<span style="font-size:22px;">🎛️</span>
							</div>
							<span>سفارشی</span>
							<p>رنگ‌های دلخواه خودتان را انتخاب کنید؛ ساختار و افکت‌ها خودکار زیبا اعمال می‌شود.</p>
						</label>
					</div>
				</div>

				<div class="gvtbl-card" id="gvtbl-custom-box" style="<?php echo 'custom' === $s['theme'] ? '' : 'display:none;'; ?>">
					<h2>رنگ‌های سفارشی</h2>
					<div class="gvtbl-color-grid">
						<div class="gvtbl-color-item">
							<label>رنگ پس‌زمینه هدر جدول</label>
							<input type="text" class="gvtbl-color-field" name="custom_colors[header_bg]" value="<?php echo esc_attr( $s['custom_colors']['header_bg'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ متن هدر جدول</label>
							<input type="text" class="gvtbl-color-field" name="custom_colors[header_text]" value="<?php echo esc_attr( $s['custom_colors']['header_text'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ متن اصلی جدول</label>
							<input type="text" class="gvtbl-color-field" name="custom_colors[text_color]" value="<?php echo esc_attr( $s['custom_colors']['text_color'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ پس‌زمینه بدنه جدول</label>
							<input type="text" class="gvtbl-color-field" name="custom_colors[body_bg]" value="<?php echo esc_attr( $s['custom_colors']['body_bg'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ خطوط و حاشیه</label>
							<input type="text" class="gvtbl-color-field" name="custom_colors[border_color]" value="<?php echo esc_attr( $s['custom_colors']['border_color'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ ردیف‌های زوج (راه‌راه)</label>
							<input type="text" class="gvtbl-color-field" name="custom_colors[row_alt_bg]" value="<?php echo esc_attr( $s['custom_colors']['row_alt_bg'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ هاور روی ردیف</label>
							<input type="text" class="gvtbl-color-field" name="custom_colors[row_hover_bg]" value="<?php echo esc_attr( $s['custom_colors']['row_hover_bg'] ); ?>">
						</div>
					</div>
					<p class="gvtbl-hint">میزان گردی گوشه‌ها (پیکسل):</p>
					<input type="number" min="0" max="30" name="custom_colors[radius]" value="<?php echo esc_attr( $s['custom_colors']['radius'] ); ?>" style="width:90px;padding:6px 8px;border-radius:6px;border:1px solid #cbd5e1;">
					<p class="gvtbl-hint">نیازی به تنظیم سایه یا فاصله‌گذاری نیست — این‌ها خودکار و با ساختاری استاندارد و زیبا روی رنگ‌های شما اعمال می‌شوند.</p>
				</div>
			</div>

			<!-- تب سوالات متداول -->
			<div class="gvtbl-tab-panel <?php echo 'faq' === $tab ? 'is-active' : ''; ?>" id="gvtbl-tab-faq" <?php echo 'faq' === $tab ? '' : 'style="display:none;"'; ?>>

				<div class="gvtbl-note">
					❓ <b>تم و استایل</b> این بخش (رنگ‌ها، گردی گوشه‌ها، انیمیشن آکاردئون) سراسری است و روی هر FAQ ای که در سایت باشد اعمال می‌شود.
					<b>محتوای سوال/جواب</b> را کافی است این کد HTML را در هر صفحه یا نوشته‌ای که خواستید (فقط داخل بلوک «HTML سفارشی» در گوتنبرگ، یا ویجت HTML در المنتور — نه ویرایشگر متنی معمولی) کپی و متن‌ها را عوض کنید:
					<div class="gvtbl-shortcode-box">&lt;div class="gvfaq-box"&gt;

&lt;div class="gvfaq-item"&gt;
&lt;div class="gvfaq-question"&gt;سوال شما؟&lt;/div&gt;
&lt;div class="gvfaq-answer"&gt;پاسخ شما.&lt;/div&gt;
&lt;/div&gt;

&lt;div class="gvfaq-item"&gt;
&lt;div class="gvfaq-question"&gt;سوال دوم؟&lt;/div&gt;
&lt;div class="gvfaq-answer"&gt;پاسخ دوم.&lt;/div&gt;
&lt;/div&gt;

&lt;/div&gt;</div>
					همین چهار کلاس (<code>gvfaq-box</code>, <code>gvfaq-item</code>, <code>gvfaq-question</code>, <code>gvfaq-answer</code>) کافی است؛ باز/بسته شدن و ظاهر آکاردئون به‌صورت خودکار توسط استایل و اسکریپت سراسری اعمال می‌شود و خروجی Schema.org هم به‌طور خودکار برای همین آیتم‌ها ساخته می‌شود — نیازی به شورت‌کد نیست. کلاس‌ها عمداً با پیشوند gvfaq- نام‌گذاری شده‌اند تا با استایل‌های قالب یا افزونه‌های دیگر تداخل نداشته باشند.
				</div>

				<div class="gvtbl-card">
					<h2>وضعیت کلی</h2>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="faq_enabled" <?php checked( $faq_s['enabled'], 1 ); ?>>
						<label>فعال‌سازی نمایش و استایل سوالات متداول</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="faq_show_schema" <?php checked( $faq_s['show_schema'], 1 ); ?>>
						<label>تولید خودکار Schema.org (FAQPage) برای سئو</label>
					</div>
				</div>

				<div class="gvtbl-card">
					<h2>انتخاب تم (سراسری برای همه‌ی FAQهای سایت)</h2>
					<div class="gvtbl-theme-grid">
						<?php foreach ( $faq_presets as $key => $p ) :
							$checked = ( $faq_s['theme'] === $key );
							?>
							<label class="gvtbl-theme-card <?php echo $checked ? 'is-checked' : ''; ?>">
								<input type="radio" name="faq_theme" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?>>
								<div class="gvtbl-theme-preview" style="border-radius:<?php echo esc_attr( $p['radius'] ); ?>px;padding:0;">
									<div style="background:<?php echo esc_attr( $p['question_bg'] ); ?>;color:<?php echo esc_attr( $p['question_text'] ); ?>;padding:8px 10px;font-size:11px;font-weight:700;">سوال نمونه؟</div>
									<div style="background:<?php echo esc_attr( $p['answer_bg'] ); ?>;color:<?php echo esc_attr( $p['answer_text'] ); ?>;padding:8px 10px;font-size:10.5px;border-top:1px solid <?php echo esc_attr( $p['border_color'] ); ?>;">این یک پاسخ نمونه است.</div>
								</div>
								<span><?php echo esc_html( $p['label'] ); ?></span>
								<p><?php echo esc_html( $p['desc'] ); ?></p>
							</label>
						<?php endforeach; ?>

						<label class="gvtbl-theme-card <?php echo 'custom' === $faq_s['theme'] ? 'is-checked' : ''; ?>">
							<input type="radio" name="faq_theme" value="custom" <?php checked( 'custom' === $faq_s['theme'] ); ?>>
							<div class="gvtbl-theme-preview" style="height:60px;display:flex;align-items:center;justify-content:center;background:#faf5ff;">
								<span style="font-size:22px;">🎛️</span>
							</div>
							<span>سفارشی</span>
							<p>رنگ‌های دلخواه خودتان را برای سوال و پاسخ انتخاب کنید.</p>
						</label>
					</div>
				</div>

				<div class="gvtbl-card" id="gvfaq-custom-box" style="<?php echo 'custom' === $faq_s['theme'] ? '' : 'display:none;'; ?>">
					<h2>رنگ‌های سفارشی</h2>
					<div class="gvtbl-color-grid">
						<div class="gvtbl-color-item">
							<label>رنگ پس‌زمینه سوال</label>
							<input type="text" class="gvtbl-color-field" name="faq_custom_colors[question_bg]" value="<?php echo esc_attr( $faq_s['custom_colors']['question_bg'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ متن سوال</label>
							<input type="text" class="gvtbl-color-field" name="faq_custom_colors[question_text]" value="<?php echo esc_attr( $faq_s['custom_colors']['question_text'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ پس‌زمینه پاسخ</label>
							<input type="text" class="gvtbl-color-field" name="faq_custom_colors[answer_bg]" value="<?php echo esc_attr( $faq_s['custom_colors']['answer_bg'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ متن پاسخ</label>
							<input type="text" class="gvtbl-color-field" name="faq_custom_colors[answer_text]" value="<?php echo esc_attr( $faq_s['custom_colors']['answer_text'] ); ?>">
						</div>
						<div class="gvtbl-color-item">
							<label>رنگ خطوط و حاشیه</label>
							<input type="text" class="gvtbl-color-field" name="faq_custom_colors[border_color]" value="<?php echo esc_attr( $faq_s['custom_colors']['border_color'] ); ?>">
						</div>
					</div>
					<p class="gvtbl-hint">میزان گردی گوشه‌ها (پیکسل):</p>
					<input type="number" min="0" max="30" name="faq_custom_colors[radius]" value="<?php echo esc_attr( $faq_s['custom_colors']['radius'] ); ?>" style="width:90px;padding:6px 8px;border-radius:6px;border:1px solid #cbd5e1;">
				</div>

			</div>

			<!-- تب تسویه‌حساب -->
			<div class="gvtbl-tab-panel <?php echo 'checkout' === $tab ? 'is-active' : ''; ?>" id="gvtbl-tab-checkout" <?php echo 'checkout' === $tab ? '' : 'style="display:none;"'; ?>>

				<?php if ( ! $has_wc ) : ?>
				<div class="gvtbl-warn">
					⚠️ ووکامرس در حال حاضر روی این سایت شناسایی نشد. تنظیمات این بخش ذخیره می‌شوند اما تا زمانی که ووکامرس نصب و فعال نباشد، اعمال نخواهند شد. اگر مطمئنید ووکامرس فعال است و این پیام را می‌بینید، یک‌بار صفحه را رفرش یا افزونه ووکامرس را غیرفعال و مجدد فعال کنید.
				</div>
				<?php endif; ?>

				<div class="gvtbl-note">
					🧾 فیلدهایی که خاموش کنید، به‌طور کامل از فرم تسویه‌حساب ووکامرس (هم بخش صورت‌حساب و هم ارسال) حذف می‌شوند تا فرم کوتاه‌تر و ساده‌تر شود. نام و نام‌خانوادگی همیشه نمایش داده می‌شوند چون ووکامرس بدون آن‌ها کار نمی‌کند.
					<br>⚠️ توجه: اگر ایمیل یا آدرس/شهر را خاموش کنید، دیگر امکان پیگیری خودکار سفارش یا ارسال ایمیل فاکتور به مشتری وجود نخواهد داشت — فقط در صورتی خاموش کنید که روش پرداخت/ارسال شما نیازی به آن‌ها ندارد.
				</div>

				<div class="gvtbl-card">
					<h2>فیلدهای قابل‌حذف</h2>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[company]" <?php checked( $s['checkout_fields']['company'], 1 ); ?>>
						<label>🏢 نام شرکت</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[address_1]" <?php checked( $s['checkout_fields']['address_1'], 1 ); ?>>
						<label>🛣️ خیابان / آدرس اصلی</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[address_2]" <?php checked( $s['checkout_fields']['address_2'], 1 ); ?>>
						<label>🏠 آدرس خط دوم (پلاک/واحد)</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[city]" <?php checked( $s['checkout_fields']['city'], 1 ); ?>>
						<label>🏙️ شهر</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[state]" <?php checked( $s['checkout_fields']['state'], 1 ); ?>>
						<label>🗺️ استان</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[postcode]" <?php checked( $s['checkout_fields']['postcode'], 1 ); ?>>
						<label>📮 کد پستی</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[country]" <?php checked( $s['checkout_fields']['country'], 1 ); ?>>
						<label>🌍 کشور (برای سایت‌های تک‌کشوره معمولاً غیرضروری است)</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[phone]" <?php checked( $s['checkout_fields']['phone'], 1 ); ?>>
						<label>📱 شماره تلفن</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[email]" <?php checked( $s['checkout_fields']['email'], 1 ); ?>>
						<label>✉️ ایمیل</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[order_notes]" <?php checked( $s['checkout_fields']['order_notes'], 1 ); ?>>
						<label>📝 یادداشت سفارش</label>
					</div>
					<div class="gvtbl-toggle-row">
						<input type="checkbox" name="checkout_fields[ship_to_different]" <?php checked( $s['checkout_fields']['ship_to_different'], 1 ); ?>>
						<label>📦 گزینه «ارسال به آدرس دیگر»</label>
					</div>
				</div>
			</div>

			<?php submit_button( '💾 ذخیره تنظیمات' ); ?>
		</form>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong> | اینستاگرام: grootvision</p>
	</div>
	<?php
}

/* ==========================================================================
   ۳) خروجی CSS در سمت سایت — جدول‌ها
   ========================================================================== */

add_action( 'wp_head', 'gv_tbl_output_css', 50 );
function gv_tbl_output_css() {
	$s = gv_tbl_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }

	$c = gv_tbl_get_active_palette( $s );

	/*
	 * مهم: این استایل فقط باید روی جدول‌های داخل محتوای مقالات، برگه‌ها و
	 * ووکامرس (وبلاگ) اعمال شود — نه روی جدول‌های فرم تسویه‌حساب/سبد خرید
	 * که مبلغ و قیمت نمایش می‌دهند. برای همین:
	 * ۱) فقط جدول‌های داخل .entry-content / .post-content / .wp-block-table را هدف می‌گیریم.
	 * ۲) با body:not(.woocommerce-checkout):not(.woocommerce-cart) کل صفحات
	 *    تسویه‌حساب و سبد خرید ووکامرس را به‌طور کامل مستثنی می‌کنیم، حتی اگر
	 *    آن صفحه یک جدول محتوایی دیگر هم داشته باشد.
	 */
	$scope = 'body:not(.woocommerce-checkout):not(.woocommerce-cart)';

	$selectors_wrap  = "{$scope} .entry-content table, {$scope} .post-content table, {$scope} .wp-block-table table, {$scope} article.post table, {$scope} article.page table";
	$selectors_th    = "{$scope} .entry-content table th, {$scope} .post-content table th, {$scope} .wp-block-table table th, {$scope} article.post table th, {$scope} article.page table th";
	$selectors_td    = "{$scope} .entry-content table td, {$scope} .post-content table td, {$scope} .wp-block-table table td, {$scope} article.post table td, {$scope} article.page table td";
	$selectors_tr_even = "{$scope} .entry-content table tbody tr:nth-child(even), {$scope} .post-content table tbody tr:nth-child(even), {$scope} .wp-block-table table tbody tr:nth-child(even)";
	$selectors_tr_hover = "{$scope} .entry-content table tbody tr:hover, {$scope} .post-content table tbody tr:hover, {$scope} .wp-block-table table tbody tr:hover";
	?>
	<style id="gv-table-style-css">
		<?php echo esc_html( $selectors_wrap ); ?> {
			border-collapse: separate;
			border-spacing: 0;
			width: 100%;
			border: 1px solid <?php echo esc_html( $c['border_color'] ); ?>;
			border-radius: <?php echo intval( $c['radius'] ); ?>px;
			overflow: hidden;
			color: <?php echo esc_html( $c['text_color'] ); ?>;
			background: <?php echo esc_html( $c['body_bg'] ); ?>;
		}
		<?php echo esc_html( $selectors_th ); ?> {
			background: <?php echo esc_html( $c['header_bg'] ); ?>;
			color: <?php echo esc_html( $c['header_text'] ); ?>;
			padding: 12px 14px;
			font-weight: 700;
			text-align: right;
		}
		<?php echo esc_html( $selectors_td ); ?> {
			padding: 10px 14px;
			border-top: 1px solid <?php echo esc_html( $c['border_color'] ); ?>;
			text-align: right;
		}
		<?php echo esc_html( $selectors_tr_even ); ?> {
			background: <?php echo esc_html( $c['row_alt_bg'] ); ?>;
		}
		<?php echo esc_html( $selectors_tr_hover ); ?> {
			background: <?php echo esc_html( $c['row_hover_bg'] ); ?>;
			transition: background .15s ease;
		}
	</style>
	<?php
}

/* ==========================================================================
   ۴) خروجی CSS در سمت سایت — سوالات متداول (FAQ)
   ========================================================================== */

add_action( 'wp_head', 'gv_faq_output_css', 51 );
function gv_faq_output_css() {
	$s = gv_faq_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }

	$c = gv_faq_get_active_palette( $s );

	/*
	 * این CSS سراسری است و هر جای سایت که کد HTML زیر را (دستی، با
	 * کلاس‌های gvfaq-box / gvfaq-item / gvfaq-question / gvfaq-answer) پیست
	 * کرده باشید، به‌طور خودکار همین ظاهر و انیمیشن آکاردئون را می‌گیرد —
	 * نیازی به شورت‌کد یا تنظیم اضافه نیست.
	 * از پیشوند gvfaq- و !important استفاده شده تا با استایل‌های قالب یا
	 * افزونه‌های دیگر که ممکن است از نام‌های عمومی faq-item/faq-question
	 * استفاده کنند تداخل نداشته باشد.
	 */
	?>
	<style id="gv-faq-style-css">
		.gvfaq-title {
			font-size: 18px !important;
			font-weight: 700 !important;
			margin-bottom: 14px !important;
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			color: <?php echo esc_html( $c['question_bg'] ); ?> !important;
		}
		.gvfaq-box {
			max-width: 720px !important;
			margin: 12px 0 !important;
			direction: rtl !important;
		}
		.gvfaq-box, .gvfaq-item, .gvfaq-question, .gvfaq-answer {
			box-sizing: border-box !important;
		}
		.gvfaq-item {
			border: 1px solid <?php echo esc_html( $c['border_color'] ); ?> !important;
			border-radius: <?php echo intval( $c['radius'] ); ?>px !important;
			margin: 0 0 10px 0 !important;
			background: <?php echo esc_html( $c['answer_bg'] ); ?> !important;
			overflow: hidden !important;
			transition: border-color .25s ease !important;
			list-style: none !important;
		}
		.gvfaq-item:hover {
			border-color: <?php echo esc_html( $c['question_bg'] ); ?> !important;
		}
		.gvfaq-question {
			display: block !important;
			padding: 12px 42px 12px 16px !important;
			margin: 0 !important;
			cursor: pointer !important;
			user-select: none !important;
			font-weight: 600 !important;
			position: relative !important;
			color: <?php echo esc_html( $c['question_text'] ); ?> !important;
			background: <?php echo esc_html( $c['question_bg'] ); ?> !important;
			font-size: 15px !important;
			line-height: 1.6 !important;
		}
		.gvfaq-question::after {
			content: "+" !important;
			position: absolute !important;
			left: 16px !important;
			top: 50% !important;
			transform: translateY(-50%) !important;
			font-size: 18px !important;
			line-height: 1 !important;
			transition: transform .3s ease !important;
		}
		.gvfaq-item.gvfaq-active .gvfaq-question::after {
			content: "−" !important;
			transform: translateY(-50%) rotate(180deg) !important;
		}
		.gvfaq-answer {
			max-height: 0 !important;
			opacity: 0 !important;
			overflow: hidden !important;
			padding: 0 16px !important;
			margin: 0 !important;
			line-height: 1.85 !important;
			font-size: 14px !important;
			color: <?php echo esc_html( $c['answer_text'] ); ?> !important;
			transition: max-height .35s ease, opacity .35s ease, padding .35s ease !important;
		}
		.gvfaq-item.gvfaq-active .gvfaq-answer {
			max-height: 600px !important;
			opacity: 1 !important;
			padding: 10px 16px 14px !important;
		}
		@media (max-width: 768px) {
			.gvfaq-box { max-width: 100% !important; }
			.gvfaq-question { padding: 10px 36px 10px 14px !important; font-size: 14px !important; }
			.gvfaq-answer { font-size: 13px !important; line-height: 1.75 !important; }
			.gvfaq-question::after { left: 14px !important; font-size: 16px !important; }
		}
	</style>
	<?php
}

/* ==========================================================================
   ۵) رفتار آکاردئون سوالات متداول + خروجی خودکار Schema.org (FAQPage)
   --------------------------------------------------------------------------
   دیگر نیازی به شورت‌کد نیست. کافیست این ساختار را در هر صفحه/نوشته‌ای
   (ترجیحاً در بلوک HTML سفارشی، نه ویرایشگر متنی معمولی) کپی کرده و متن
   سوال و جواب را عوض کنید؛ ظاهر و رفتار آکاردئون و خروجی Schema.org
   به‌طور خودکار روی آن اعمال می‌شود:

   <div class="gvfaq-box">
     <div class="gvfaq-item">
       <div class="gvfaq-question">سوال؟</div>
       <div class="gvfaq-answer">پاسخ.</div>
     </div>
   </div>

   نکته فنی: کلیک با event delegation روی document مدیریت می‌شود (نه
   افزودن مستقیم listener به هر آیتم در لحظه بارگذاری صفحه)، تا حتی اگر
   محتوا توسط صفحه‌ساز (المنتور و مشابه آن) با تاخیر رندر شود، باز هم کلیک
   درست کار کند.
   ========================================================================== */

add_action( 'wp_footer', 'gv_faq_footer_output', 20 );
function gv_faq_footer_output() {
	$s = gv_faq_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }
	$show_schema = ! empty( $s['show_schema'] ) ? 'true' : 'false';
	?>
	<script>
	(function () {
		// باز/بسته کردن آکاردئون — با event delegation، مستقل از زمان رندر محتوا
		document.addEventListener("click", function (e) {
			var q = e.target.closest(".gvfaq-question");
			if (!q) { return; }
			var item = q.closest(".gvfaq-item");
			if (!item) { return; }
			item.classList.toggle("gvfaq-active");
		});

		// ساخت خودکار Schema.org (FAQPage) از روی سوال/جواب‌های موجود در صفحه
		function gvfaqBuildSchema() {
			if (!<?php echo esc_js( $show_schema ); ?>) { return; }
			if (document.getElementById("gvfaq-schema-json")) { return; }
			var faqs = document.querySelectorAll(".gvfaq-item");
			if (!faqs.length) { return; }
			var schema = {
				"@context": "https://schema.org",
				"@type": "FAQPage",
				"mainEntity": []
			};
			faqs.forEach(function (item) {
				var q = item.querySelector(".gvfaq-question");
				var a = item.querySelector(".gvfaq-answer");
				if (!q || !a) { return; }
				schema.mainEntity.push({
					"@type": "Question",
					"name": q.innerText.trim(),
					"acceptedAnswer": {
						"@type": "Answer",
						"text": a.innerText.trim()
					}
				});
			});
			if (schema.mainEntity.length) {
				var script = document.createElement("script");
				script.type = "application/ld+json";
				script.id = "gvfaq-schema-json";
				script.text = JSON.stringify(schema);
				document.body.appendChild(script);
			}
		}

		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", gvfaqBuildSchema);
		} else {
			gvfaqBuildSchema();
		}
	})();
	</script>
	<?php
}

/* ==========================================================================
   ۶) ساده‌سازی فرم تسویه‌حساب ووکامرس
   ========================================================================== */

add_filter( 'woocommerce_checkout_fields', 'gv_tbl_filter_checkout_fields' );
function gv_tbl_filter_checkout_fields( $fields ) {
	$s      = gv_tbl_get_settings();
	$toggle = $s['checkout_fields'];

	$map = array(
		'company'   => 'company',
		'address_1' => 'address_1',
		'address_2' => 'address_2',
		'city'      => 'city',
		'country'   => 'country',
		'state'     => 'state',
		'postcode'  => 'postcode',
		'phone'     => 'phone',
	);

	foreach ( array( 'billing', 'shipping' ) as $group ) {
		if ( empty( $fields[ $group ] ) ) { continue; }
		foreach ( $map as $toggle_key => $suffix ) {
			$field_key = $group . '_' . $suffix;
			if ( empty( $toggle[ $toggle_key ] ) && isset( $fields[ $group ][ $field_key ] ) ) {
				unset( $fields[ $group ][ $field_key ] );
			}
		}
	}

	// ایمیل فقط در بخش billing وجود دارد
	if ( empty( $toggle['email'] ) && isset( $fields['billing']['billing_email'] ) ) {
		unset( $fields['billing']['billing_email'] );
	}

	if ( empty( $toggle['order_notes'] ) && isset( $fields['order']['order_comments'] ) ) {
		unset( $fields['order']['order_comments'] );
	}

	return $fields;
}

add_filter( 'woocommerce_enable_order_notes_field', 'gv_tbl_filter_order_notes_toggle' );
function gv_tbl_filter_order_notes_toggle( $enabled ) {
	$s = gv_tbl_get_settings();
	if ( empty( $s['checkout_fields']['order_notes'] ) ) { return false; }
	return $enabled;
}

add_filter( 'woocommerce_ship_to_different_address_checked', 'gv_tbl_filter_ship_to_different' );
function gv_tbl_filter_ship_to_different( $checked ) {
	return $checked;
}

add_action( 'wp_head', 'gv_tbl_maybe_hide_ship_to_different', 60 );
function gv_tbl_maybe_hide_ship_to_different() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
	$s = gv_tbl_get_settings();
	if ( ! empty( $s['checkout_fields']['ship_to_different'] ) ) { return; }
	echo '<style>.woocommerce-shipping-fields__field-wrapper, #ship-to-different-address{display:none !important;}</style>';
}