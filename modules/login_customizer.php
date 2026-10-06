<?php
/**
 * Groot Vision Login Style
 * Developed by Groot Vision (grootvision)
 * Website: https://grootvision.com
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'GV_LOGIN_OPT', 'gv_login_style_settings' );
define( 'GV_LOGIN_NONCE', 'gv_login_nonce_action' );
define( 'GV_LOGIN_SLUG', 'gv-login-style' );

function gv_login_presets() {
	return array(
		'snow'     => array(
			'label'        => 'برف',
			'desc'         => 'روشن، تمیز و مینیمال',
			'bg'           => 'linear-gradient(180deg,#fafafa 0%,#f1f3f6 100%)',
			'card_bg'      => '#ffffff',
			'card_border'  => '#e7eaee',
			'text'         => '#0f172a',
			'muted'        => '#64748b',
			'input_bg'     => '#f8fafc',
			'input_border' => '#e2e8f0',
			'accent'       => '#111827',
			'shadow'       => '0 24px 60px -24px rgba(15,23,42,.18)',
		),
		'midnight' => array(
			'label'        => 'نیمه‌شب',
			'desc'         => 'تیره، آرام و حرفه‌ای',
			'bg'           => 'radial-gradient(1200px 600px at 50% -10%,#16202f 0%,#0a0e15 70%)',
			'card_bg'      => '#111826',
			'card_border'  => '#1f2a3b',
			'text'         => '#e5e9f0',
			'muted'        => '#8a96a8',
			'input_bg'     => '#0b111b',
			'input_border' => '#243044',
			'accent'       => '#34d399',
			'shadow'       => '0 30px 70px -30px rgba(0,0,0,.7)',
		),
		'mint'     => array(
			'label'        => 'نعنا',
			'desc'         => 'سبز ملایم، الهام‌گرفته از برند',
			'bg'           => 'linear-gradient(160deg,#ecfdf5 0%,#d5f3e6 100%)',
			'card_bg'      => '#ffffff',
			'card_border'  => '#cfeadb',
			'text'         => '#0b2a23',
			'muted'        => '#5b7a70',
			'input_bg'     => '#f4fbf8',
			'input_border' => '#cfe6db',
			'accent'       => '#0e4037',
			'shadow'       => '0 24px 60px -26px rgba(14,64,55,.28)',
		),
		'dusk'     => array(
			'label'        => 'گرگ‌ومیش',
			'desc'         => 'پاستلی نرم، صورتی و بنفش',
			'bg'           => 'linear-gradient(135deg,#fdf2f8 0%,#eef2ff 100%)',
			'card_bg'      => 'rgba(255,255,255,.88)',
			'card_border'  => '#e9e5f7',
			'text'         => '#1e1b3a',
			'muted'        => '#6b6a8c',
			'input_bg'     => '#ffffff',
			'input_border' => '#e3e0f3',
			'accent'       => '#6366f1',
			'shadow'       => '0 26px 60px -26px rgba(99,102,241,.35)',
		),
	);
}

function gv_login_default_settings() {
	return array(
		'enabled'   => 0,
		'theme'     => 'snow',
		'logo_url'  => '',
		'logo_size' => 84,
		'tagline'   => '',
		'accent'    => '#111827',
	);
}

function gv_login_get_settings() {
	$s = wp_parse_args( get_option( GV_LOGIN_OPT, array() ), gv_login_default_settings() );
	if ( ! isset( gv_login_presets()[ $s['theme'] ] ) ) {
		$s['theme'] = 'snow';
	}
	return $s;
}

function gv_login_contrast( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( strlen( $hex ) !== 6 ) { return '#ffffff'; }
	$lum = ( 0.299 * hexdec( substr( $hex, 0, 2 ) ) + 0.587 * hexdec( substr( $hex, 2, 2 ) ) + 0.114 * hexdec( substr( $hex, 4, 2 ) ) ) / 255;
	return $lum > 0.6 ? '#0b0f17' : '#ffffff';
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'groot-vision-hub',
		'صفحه ورود وردپرس | Groot Vision',
		'🔐 صفحه ورود',
		'manage_options',
		GV_LOGIN_SLUG,
		'gv_login_render_admin_page'
	);
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( strpos( $hook, GV_LOGIN_SLUG ) === false ) { return; }
	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	$js = <<<'JS'
jQuery(function ($) {
	var P = GVL_PRESETS;

	function contrast(hex) {
		hex = (hex || '').replace('#', '');
		if (hex.length === 3) { hex = hex.replace(/./g, '$&$&'); }
		var r = parseInt(hex.substr(0, 2), 16), g = parseInt(hex.substr(2, 2), 16), b = parseInt(hex.substr(4, 2), 16);
		return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.6 ? '#0b0f17' : '#ffffff';
	}

	function render() {
		var key = $('.gvl-theme-radio:checked').val(), p = P[key] || P.snow;
		var accent = $('#gvl-accent').val() || p.accent;
		var logo = $('#gvl-logo-url').val();
		var size = parseInt($('#gvl-logo-size').val(), 10) || 84;

		$('#gvl-pv').css('background', p.bg);
		$('.gvl-pv-card').css({ background: p.card_bg, borderColor: p.card_border, boxShadow: p.shadow });
		$('.gvl-pv-lbl').css('color', p.muted);
		$('.gvl-pv-input').css({ background: p.input_bg, borderColor: p.input_border });
		$('.gvl-pv-btn').css({ background: accent, color: contrast(accent) });
		$('.gvl-pv-tag').css('color', p.muted).text($('#gvl-tagline').val());

		var $logo = $('.gvl-pv-logo').empty();
		if (logo) {
			$('<img>').attr('src', logo).css('height', Math.max(24, Math.round(size * 0.55))).appendTo($logo);
		} else {
			$('<span class="dashicons dashicons-wordpress"></span>').appendTo($logo);
		}
	}

	$('#gvl-accent').wpColorPicker({
		change: function () { setTimeout(render, 30); },
		clear: function () { setTimeout(render, 30); }
	});

	$('.gvl-theme-radio').on('change', function () {
		$('.gvl-theme').removeClass('is-selected');
		$(this).closest('.gvl-theme').addClass('is-selected');
		$('#gvl-accent').wpColorPicker('color', (P[$(this).val()] || P.snow).accent);
		render();
	});

	$('#gvl-pick-logo').on('click', function (e) {
		e.preventDefault();
		var frame = wp.media({ title: 'انتخاب لوگو', multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			$('#gvl-logo-url').val(att.url);
			$('#gvl-logo-thumb').attr('src', att.url).show();
			$('#gvl-logo-empty').hide();
			render();
		});
		frame.open();
	});

	$('#gvl-remove-logo').on('click', function (e) {
		e.preventDefault();
		$('#gvl-logo-url').val('');
		$('#gvl-logo-thumb').hide();
		$('#gvl-logo-empty').show();
		render();
	});

	$('#gvl-logo-size, #gvl-tagline').on('input change', render);
	render();
});
JS;

	wp_add_inline_script( 'wp-color-picker', 'var GVL_PRESETS = ' . wp_json_encode( gv_login_presets() ) . ';' . $js );
} );

add_action( 'admin_post_gv_login_save_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_LOGIN_NONCE );

	$presets = gv_login_presets();
	$theme   = isset( $_POST['theme'] ) ? sanitize_key( wp_unslash( $_POST['theme'] ) ) : 'snow';
	if ( ! isset( $presets[ $theme ] ) ) { $theme = 'snow'; }

	$settings = array(
		'enabled'   => isset( $_POST['enabled'] ) ? 1 : 0,
		'theme'     => $theme,
		'logo_url'  => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
		'logo_size' => max( 40, min( 300, intval( $_POST['logo_size'] ?? 84 ) ) ),
		'tagline'   => sanitize_text_field( wp_unslash( $_POST['tagline'] ?? '' ) ),
		'accent'    => sanitize_hex_color( wp_unslash( $_POST['accent'] ?? '' ) ) ?: $presets[ $theme ]['accent'],
	);

	update_option( GV_LOGIN_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_LOGIN_SLUG . '&updated=1' ) );
	exit;
} );

function gv_login_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s       = gv_login_get_settings();
	$presets = gv_login_presets();
	?>
	<div class="wrap gvl-wrap" dir="rtl">
		<style>
			.gvl-wrap{max-width:1200px;margin-top:20px;font-family:'Vazirmatn',Tahoma,sans-serif;}
			.gvl-wrap *{box-sizing:border-box;}
			.gvl-header{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,#0a2e28 0%,#0e4037 45%,#145c4d 100%);color:#fff;padding:24px 30px;border-radius:18px;margin-bottom:26px;box-shadow:0 14px 36px rgba(14,64,55,.32);position:relative;overflow:hidden;}
			.gvl-header::after{content:"";position:absolute;left:-60px;top:-80px;width:220px;height:220px;background:radial-gradient(circle,rgba(74,222,128,.28),transparent 70%);pointer-events:none;}
			.gvl-logo{display:flex;align-items:center;gap:16px;position:relative;z-index:1;}
			.gvl-logo-icon{display:flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:16px;background:rgba(74,222,128,.14);border:1px solid rgba(74,222,128,.4);}
			.gvl-logo-icon .dashicons{font-size:28px;width:28px;height:28px;color:#4ade80;}
			.gvl-logo h1{margin:0;font-size:23px;font-weight:700;color:#fff;}
			.gvl-logo p{margin:3px 0 0;font-size:13px;color:#b6d3ca;}
			.gvl-badge{position:relative;z-index:1;background:rgba(74,222,128,.14);border:1px solid rgba(74,222,128,.55);color:#4ade80;padding:7px 16px;border-radius:30px;font-size:12px;font-weight:600;}

			.gvl-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:22px;align-items:start;}
			.gvl-card{background:#fff;border:1px solid #e6ebf0;border-radius:18px;padding:22px 24px;margin-bottom:20px;box-shadow:0 4px 18px rgba(15,23,42,.04);transition:box-shadow .25s;}
			.gvl-card:hover{box-shadow:0 10px 28px rgba(15,23,42,.08);}
			.gvl-card h2{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;margin:0 0 16px;color:#0f172a;border-bottom:1px solid #eef2f6;padding:0 0 12px;}
			.gvl-card h2 .dashicons{color:#145c4d;font-size:18px;width:18px;height:18px;}
			.gvl-hint{font-size:12.5px;color:#64748b;line-height:1.9;margin:8px 0 0;}

			.gvl-switch{display:flex;align-items:center;gap:12px;cursor:pointer;}
			.gvl-switch input{display:none;}
			.gvl-slider{width:46px;height:25px;background:#cbd5e1;border-radius:25px;position:relative;transition:.28s;flex-shrink:0;}
			.gvl-slider::before{content:"";position:absolute;width:19px;height:19px;background:#fff;border-radius:50%;top:3px;right:3px;transition:.28s;box-shadow:0 2px 5px rgba(0,0,0,.28);}
			.gvl-switch input:checked + .gvl-slider{background:linear-gradient(90deg,#0e4037,#1a7a64);}
			.gvl-switch input:checked + .gvl-slider::before{right:24px;}
			.gvl-switch-label{font-size:14px;font-weight:700;color:#0f172a;}

			.gvl-themes{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
			@media(max-width:700px){.gvl-themes{grid-template-columns:1fr;}}
			.gvl-theme{position:relative;display:block;border:2px solid #e6ebf0;border-radius:16px;padding:10px;cursor:pointer;transition:.2s;background:#fff;}
			.gvl-theme:hover{border-color:#b9c7d3;transform:translateY(-2px);}
			.gvl-theme.is-selected{border-color:#0e4037;box-shadow:0 0 0 4px rgba(14,64,55,.1);}
			.gvl-theme input{position:absolute;opacity:0;pointer-events:none;}
			.gvl-theme-check{position:absolute;top:16px;left:16px;width:22px;height:22px;border-radius:50%;background:#0e4037;color:#fff;display:none;align-items:center;justify-content:center;font-size:12px;z-index:2;}
			.gvl-theme.is-selected .gvl-theme-check{display:flex;}
			.gvl-swatch{height:112px;border-radius:11px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;}
			.gvl-mini{width:104px;padding:12px 11px;border-radius:10px;border:1px solid transparent;display:flex;flex-direction:column;gap:6px;}
			.gvl-mini i{display:block;height:9px;border-radius:4px;border:1px solid transparent;}
			.gvl-mini b{display:block;height:10px;border-radius:5px;margin-top:2px;}
			.gvl-theme strong{display:block;font-size:13.5px;color:#0f172a;}
			.gvl-theme small{display:block;font-size:12px;color:#64748b;margin-top:2px;}

			.gvl-field{margin-bottom:18px;}
			.gvl-field:last-child{margin-bottom:0;}
			.gvl-field > label{display:block;font-weight:700;font-size:13px;color:#334155;margin-bottom:8px;}
			.gvl-field input[type=text],.gvl-field input[type=number]{width:100%;max-width:380px;padding:8px 12px;min-height:38px;border:1px solid #d5dde6;border-radius:10px;background:#f8fafc;transition:border-color .2s,box-shadow .2s;}
			.gvl-field input:focus{border-color:#145c4d;box-shadow:0 0 0 3px rgba(20,92,77,.15);outline:none;background:#fff;}
			.gvl-logo-box{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
			.gvl-logo-frame{width:110px;height:84px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;}
			.gvl-logo-frame img{max-width:90%;max-height:90%;display:<?php echo $s['logo_url'] ? 'block' : 'none'; ?>;}
			.gvl-logo-frame .dashicons{font-size:36px;width:36px;height:36px;color:#2271b1;display:<?php echo $s['logo_url'] ? 'none' : 'block'; ?>;}
			.gvl-btns{display:flex;flex-direction:column;gap:8px;align-items:flex-start;}

			.gvl-save{width:100%;background:linear-gradient(90deg,#0e4037,#145c4d);color:#fff;border:none;padding:15px 30px;border-radius:14px;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 10px 24px rgba(14,64,55,.3);transition:transform .2s,box-shadow .2s;}
			.gvl-save:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(14,64,55,.38);}

			.gvl-sticky{position:sticky;top:40px;}
			#gvl-pv{border-radius:16px;padding:34px 22px;display:flex;flex-direction:column;align-items:center;gap:16px;min-height:380px;justify-content:center;transition:background .3s;border:1px solid #e6ebf0;}
			.gvl-pv-logo{display:flex;align-items:center;justify-content:center;min-height:50px;}
			.gvl-pv-logo .dashicons{font-size:46px;width:46px;height:46px;color:#2271b1;}
			.gvl-pv-logo img{max-width:160px;object-fit:contain;}
			.gvl-pv-card{width:100%;max-width:250px;border:1px solid transparent;border-radius:16px;padding:20px 18px;display:flex;flex-direction:column;gap:7px;transition:all .3s;}
			.gvl-pv-lbl{font-size:11px;font-weight:600;}
			.gvl-pv-input{height:30px;border-radius:8px;border:1px solid transparent;margin-bottom:6px;}
			.gvl-pv-btn{margin-top:6px;text-align:center;font-size:12px;font-weight:700;padding:9px 0;border-radius:9px;}
			.gvl-pv-tag{font-size:11.5px;text-align:center;min-height:16px;}

			.gvl-footer{text-align:center;color:#94a3b8;font-size:12.5px;margin:34px 0 10px;}
			.gvl-footer a{color:#145c4d;text-decoration:none;font-weight:600;}
			@media(max-width:960px){.gvl-grid{grid-template-columns:1fr;}.gvl-sticky{position:static;}}
		</style>

		<div class="gvl-header">
			<div class="gvl-logo">
				<span class="gvl-logo-icon"><span class="dashicons dashicons-lock"></span></span>
				<div>
					<h1>طراحی صفحه ورود</h1>
					<p>ظاهر مدرن و مینیمال برای wp-login</p>
				</div>
			</div>
			<span class="gvl-badge">Groot Vision</span>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>✔ تنظیمات ذخیره شد. برای دیدن نتیجه، از حالت خصوصی مرورگر وارد <a href="<?php echo esc_url( wp_login_url() ); ?>" target="_blank" rel="noopener">صفحه ورود</a> شوید.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gv_login_save_settings">
			<?php wp_nonce_field( GV_LOGIN_NONCE ); ?>

			<div class="gvl-grid">
				<div>
					<div class="gvl-card">
						<label class="gvl-switch">
							<input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'], 1 ); ?>>
							<span class="gvl-slider"></span>
							<span class="gvl-switch-label">فعال‌سازی طراحی اختصاصی صفحه ورود</span>
						</label>
					</div>

					<div class="gvl-card">
						<h2><span class="dashicons dashicons-art"></span> انتخاب تم</h2>
						<div class="gvl-themes">
							<?php foreach ( $presets as $key => $p ) : ?>
								<label class="gvl-theme <?php echo $s['theme'] === $key ? 'is-selected' : ''; ?>">
									<input type="radio" class="gvl-theme-radio" name="theme" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['theme'], $key ); ?>>
									<span class="gvl-theme-check">✓</span>
									<div class="gvl-swatch" style="background:<?php echo esc_attr( $p['bg'] ); ?>;">
										<div class="gvl-mini" style="background:<?php echo esc_attr( $p['card_bg'] ); ?>;border-color:<?php echo esc_attr( $p['card_border'] ); ?>;box-shadow:<?php echo esc_attr( $p['shadow'] ); ?>;">
											<i style="background:<?php echo esc_attr( $p['input_bg'] ); ?>;border-color:<?php echo esc_attr( $p['input_border'] ); ?>;"></i>
											<i style="background:<?php echo esc_attr( $p['input_bg'] ); ?>;border-color:<?php echo esc_attr( $p['input_border'] ); ?>;"></i>
											<b style="background:<?php echo esc_attr( $p['accent'] ); ?>;"></b>
										</div>
									</div>
									<strong><?php echo esc_html( $p['label'] ); ?></strong>
									<small><?php echo esc_html( $p['desc'] ); ?></small>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="gvl-card">
						<h2><span class="dashicons dashicons-format-image"></span> لوگو و متن</h2>

						<div class="gvl-field">
							<label>لوگو</label>
							<input type="hidden" id="gvl-logo-url" name="logo_url" value="<?php echo esc_attr( $s['logo_url'] ); ?>">
							<div class="gvl-logo-box">
								<div class="gvl-logo-frame">
									<img id="gvl-logo-thumb" src="<?php echo esc_url( $s['logo_url'] ); ?>" alt="">
									<span id="gvl-logo-empty" class="dashicons dashicons-wordpress"></span>
								</div>
								<div class="gvl-btns">
									<button type="button" id="gvl-pick-logo" class="button">📁 انتخاب از کتابخانه رسانه</button>
									<button type="button" id="gvl-remove-logo" class="button">بازگشت به لوگوی پیش‌فرض وردپرس</button>
								</div>
							</div>
							<p class="gvl-hint">اگر لوگو انتخاب نکنید، لوگوی پیش‌فرض خود وردپرس نمایش داده می‌شود.</p>
						</div>

						<div class="gvl-field">
							<label>ارتفاع لوگوی سفارشی (پیکسل)</label>
							<input type="number" id="gvl-logo-size" name="logo_size" min="40" max="300" value="<?php echo esc_attr( $s['logo_size'] ); ?>">
						</div>

						<div class="gvl-field">
							<label>توضیح کوتاه زیر فرم (اختیاری)</label>
							<input type="text" id="gvl-tagline" name="tagline" value="<?php echo esc_attr( $s['tagline'] ); ?>" placeholder="مثلاً: به پنل مدیریت خوش آمدید">
						</div>

						<div class="gvl-field">
							<label>رنگ دکمه و لینک‌ها</label>
							<input type="text" id="gvl-accent" name="accent" value="<?php echo esc_attr( $s['accent'] ); ?>">
						</div>
					</div>

					<button type="submit" class="gvl-save">💾 ذخیره تنظیمات</button>
				</div>

				<div>
					<div class="gvl-card gvl-sticky">
						<h2><span class="dashicons dashicons-desktop"></span> پیش‌نمایش زنده</h2>
						<div id="gvl-pv">
							<div class="gvl-pv-logo"></div>
							<div class="gvl-pv-card">
								<div class="gvl-pv-lbl">نام کاربری</div>
								<div class="gvl-pv-input"></div>
								<div class="gvl-pv-lbl">رمز عبور</div>
								<div class="gvl-pv-input"></div>
								<div class="gvl-pv-btn">ورود</div>
							</div>
							<div class="gvl-pv-tag"></div>
						</div>
						<p class="gvl-hint">پیش‌نمایش تقریبی است؛ نمای واقعی را در صفحه ورود ببینید.</p>
					</div>
				</div>
			</div>
		</form>

		<div class="gvl-footer">
			ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong> — <a href="https://grootvision.com" target="_blank" rel="noopener">grootvision.com</a>
		</div>
	</div>
	<?php
}

add_action( 'login_enqueue_scripts', function () {
	$s = gv_login_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }

	$p       = gv_login_presets()[ $s['theme'] ];
	$accent  = $s['accent'];
	$btn_txt = gv_login_contrast( $accent );
	?>
	<style>
		@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap');

		body.login{
			background:<?php echo $p['bg']; ?>;
			background-attachment:fixed;
			min-height:100vh;
			display:flex;
			flex-direction:column;
			align-items:center;
			justify-content:center;
			font-family:'Vazirmatn',Tahoma,sans-serif;
			color:<?php echo $p['text']; ?>;
		}
		body.login #login{
			width:380px;
			max-width:calc(100% - 32px);
			padding:0;
			margin:0 auto;
		}

		body.login h1 a{
			display:block;
			margin:0 auto 22px;
			transition:transform .25s;
		}
		body.login h1 a:hover{transform:scale(1.04);}
		<?php if ( $s['logo_url'] ) : ?>
		body.login h1 a{
			background-image:url('<?php echo esc_url( $s['logo_url'] ); ?>') !important;
			background-repeat:no-repeat;
			background-position:center;
			background-size:contain;
			width:100%;
			height:<?php echo intval( $s['logo_size'] ); ?>px;
		}
		<?php endif; ?>

		body.login form#loginform,
		body.login form#lostpasswordform,
		body.login form#registerform,
		body.login form#resetpassform{
			background:<?php echo $p['card_bg']; ?>;
			border:1px solid <?php echo $p['card_border']; ?>;
			border-radius:20px;
			box-shadow:<?php echo $p['shadow']; ?>;
			padding:30px 28px 26px;
			margin-top:0;
			overflow:visible;
			backdrop-filter:blur(14px);
		}
		body.login form label{
			color:<?php echo $p['muted']; ?>;
			font-size:13px;
			font-weight:600;
		}
		body.login form .input,
		body.login input[type=text],
		body.login input[type=password],
		body.login input[type=email]{
			height:46px;
			padding:0 14px;
			font-size:15px;
			font-family:inherit;
			border-radius:12px;
			border:1px solid <?php echo $p['input_border']; ?>;
			background:<?php echo $p['input_bg']; ?>;
			color:<?php echo $p['text']; ?>;
			box-shadow:none;
			transition:border-color .2s,box-shadow .2s;
		}
		body.login input[type=text]:focus,
		body.login input[type=password]:focus,
		body.login input[type=email]:focus{
			border-color:<?php echo esc_attr( $accent ); ?>;
			box-shadow:0 0 0 4px <?php echo esc_attr( $accent ); ?>26;
			outline:none;
		}
		body.login .wp-pwd .button.wp-hide-pw{
			background:transparent;
			border:none;
			box-shadow:none;
			color:<?php echo $p['muted']; ?>;
			height:46px;
		}
		body.login input[type=checkbox]{
			accent-color:<?php echo esc_attr( $accent ); ?>;
			border-color:<?php echo $p['input_border']; ?>;
			background:<?php echo $p['input_bg']; ?>;
		}
		body.login .forgetmenot{
			float:none;
			margin:6px 0 18px;
		}
		body.login .forgetmenot label{font-weight:400;}
		body.login p.submit{margin:0;}
		body.login .button-primary,
		body.login .wp-core-ui .button-primary{
			float:none;
			width:100%;
			height:46px;
			background:<?php echo esc_attr( $accent ); ?> !important;
			border:none !important;
			color:<?php echo esc_attr( $btn_txt ); ?> !important;
			border-radius:12px !important;
			font-family:inherit;
			font-size:14.5px;
			font-weight:700 !important;
			text-shadow:none !important;
			box-shadow:0 10px 22px -10px <?php echo esc_attr( $accent ); ?> !important;
			transition:transform .2s,filter .2s;
		}
		body.login .button-primary:hover{
			transform:translateY(-1px);
			filter:brightness(1.1);
		}

		body.login #nav,
		body.login #backtoblog{
			text-align:center;
			padding:0;
			margin:18px 0 0;
		}
		body.login #nav a,
		body.login #backtoblog a,
		body.login .privacy-policy-page-link a{
			color:<?php echo $p['muted']; ?> !important;
			font-size:12.5px;
			transition:color .2s;
		}
		body.login #nav a:hover,
		body.login #backtoblog a:hover{color:<?php echo esc_attr( $accent ); ?> !important;}

		body.login .message,
		body.login .success,
		body.login #login_error{
			background:<?php echo $p['card_bg']; ?>;
			color:<?php echo $p['text']; ?>;
			border:1px solid <?php echo $p['card_border']; ?>;
			border-right:4px solid <?php echo esc_attr( $accent ); ?>;
			border-radius:14px;
			box-shadow:none;
			padding:14px 16px;
			margin-bottom:16px;
		}
		body.login #login_error{border-right-color:#ef4444;}

		body.login .gvl-tagline{
			text-align:center;
			margin:18px 0 0;
			font-size:12.5px;
			color:<?php echo $p['muted']; ?>;
		}
	</style>
	<?php
} );

add_action( 'login_footer', function () {
	$s = gv_login_get_settings();
	if ( empty( $s['enabled'] ) || '' === $s['tagline'] ) { return; }
	echo '<p class="gvl-tagline">' . esc_html( $s['tagline'] ) . '</p>';
} );

add_filter( 'login_headerurl', function () {
	return home_url( '/' );
} );

add_filter( 'login_headertext', function () {
	return get_bloginfo( 'name' );
} );