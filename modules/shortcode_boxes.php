<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — کادرهای رنگی و دکمه (شورت‌کد)
 *  ------------------------------------------------------------
 *  دو شورت‌کد اضافه می‌کند که در محیط متنی وردپرس (بلوک "شورت‌کد"
 *  یا هر ادیتور دیگر) قابل استفاده هستند:
 *
 *  ۱) [gv_box type="warning" title="توجه"]متن دلخواه[/gv_box]
 *     یک کادر مینیمال رنگی با آیکون، برای اخطار/نکته/موفقیت/
 *     لینک به مقاله دیگر و ... . انواع type:
 *     info, success, warning, danger, tip, note, quote, link
 *
 *  ۲) [gv_button url="https://example.com" color="primary" style="solid"]متن دکمه[/gv_button]
 *     یک دکمه‌ی کلیک‌پذیر با رنگ/استایل/سایز دلخواه.
 *
 *  همچنین یک صفحه‌ی «گالری» به داشبورد اضافه می‌شود که تمام
 *  مدل‌ها را با پیش‌نمایش زنده + دکمه‌ی کپی شورت‌کد نشان می‌دهد.
 * ==========================================================
 */

define( 'GV_SCBX_PAGE_SLUG', 'gv-shortcode-boxes' );

/* ==========================================================================
   ۱) تعریف انواع کادر (رنگ، آیکون، برچسب فارسی)
   ========================================================================== */
function gv_scbx_box_types() {
	return array(
		'info'    => array( 'label' => 'اطلاعات',       'icon' => 'ℹ️', 'accent' => '#2563eb', 'bg' => '#eff6ff' ),
		'success' => array( 'label' => 'موفقیت',        'icon' => '✅', 'accent' => '#16a34a', 'bg' => '#f0fdf4' ),
		'warning' => array( 'label' => 'هشدار',         'icon' => '⚠️', 'accent' => '#b45309', 'bg' => '#fffbeb' ),
		'danger'  => array( 'label' => 'خطر / اخطار',   'icon' => '⛔', 'accent' => '#b91c1c', 'bg' => '#fef2f2' ),
		'tip'     => array( 'label' => 'نکته / ترفند',  'icon' => '💡', 'accent' => '#7c3aed', 'bg' => '#f5f3ff' ),
		'note'    => array( 'label' => 'یادداشت',       'icon' => '📝', 'accent' => '#4b5563', 'bg' => '#f9fafb' ),
		'quote'   => array( 'label' => 'نقل‌قول',       'icon' => '💬', 'accent' => '#4338ca', 'bg' => '#eef2ff' ),
		'link'    => array( 'label' => 'لینک به مقاله', 'icon' => '🔗', 'accent' => '#0e7490', 'bg' => '#ecfeff' ),
	);
}

/**
 * فقط یک‌بار در طول بارگذاری صفحه، CSS مربوط به کادر و دکمه را چاپ می‌کند
 * (فقط وقتی حداقل یکبار شورت‌کد استفاده شده باشد، برای سبک ماندن صفحه).
 */
function gv_scbx_print_styles_once() {
	static $printed = false;
	if ( $printed ) { return; }
	$printed = true;
	?>
	<style id="gv-scbx-style">
		.gvbox{border-radius:14px;padding:16px 18px;margin:20px 0;border:1px solid rgba(0,0,0,.06);border-inline-start:4px solid var(--gvbox-accent,#2563eb);background:var(--gvbox-bg,#eff6ff);font-size:15px;line-height:1.9;}
		.gvbox-head{display:flex;align-items:center;gap:8px;font-weight:700;margin-bottom:6px;color:var(--gvbox-accent,#2563eb);}
		.gvbox-icon{font-size:19px;line-height:1;}
		.gvbox-body p:first-child{margin-top:0;}
		.gvbox-body p:last-child{margin-bottom:0;}
		.gvbox-more{display:inline-flex;align-items:center;gap:4px;margin-top:10px;font-weight:600;font-size:13.5px;color:var(--gvbox-accent,#2563eb);text-decoration:none;}
		.gvbox-more:hover{text-decoration:underline;}

		.gvbtn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:10px;font-size:14.5px;font-weight:600;text-decoration:none!important;border:1.5px solid transparent;transition:.15s ease;cursor:pointer;}
		.gvbtn:hover{transform:translateY(-1px);filter:brightness(1.03);}
		.gvbtn-block{display:flex;justify-content:center;width:100%;}
		.gvbtn-sm{padding:6px 14px;font-size:13px;border-radius:8px;}
		.gvbtn-lg{padding:13px 28px;font-size:16px;border-radius:12px;}

		.gvbtn-solid.gvbtn-primary{background:#4338ca;border-color:#4338ca;color:#fff!important;}
		.gvbtn-solid.gvbtn-success{background:#16a34a;border-color:#16a34a;color:#fff!important;}
		.gvbtn-solid.gvbtn-danger{background:#dc2626;border-color:#dc2626;color:#fff!important;}
		.gvbtn-solid.gvbtn-warning{background:#d97706;border-color:#d97706;color:#fff!important;}
		.gvbtn-solid.gvbtn-dark{background:#111827;border-color:#111827;color:#fff!important;}

		.gvbtn-outline.gvbtn-primary{background:transparent;border-color:#4338ca;color:#4338ca!important;}
		.gvbtn-outline.gvbtn-success{background:transparent;border-color:#16a34a;color:#16a34a!important;}
		.gvbtn-outline.gvbtn-danger{background:transparent;border-color:#dc2626;color:#dc2626!important;}
		.gvbtn-outline.gvbtn-warning{background:transparent;border-color:#d97706;color:#d97706!important;}
		.gvbtn-outline.gvbtn-dark{background:transparent;border-color:#111827;color:#111827!important;}

		.gvbtn-soft.gvbtn-primary{background:#eef2ff;border-color:#eef2ff;color:#4338ca!important;}
		.gvbtn-soft.gvbtn-success{background:#f0fdf4;border-color:#f0fdf4;color:#16a34a!important;}
		.gvbtn-soft.gvbtn-danger{background:#fef2f2;border-color:#fef2f2;color:#dc2626!important;}
		.gvbtn-soft.gvbtn-warning{background:#fffbeb;border-color:#fffbeb;color:#d97706!important;}
		.gvbtn-soft.gvbtn-dark{background:#f3f4f6;border-color:#f3f4f6;color:#111827!important;}
	</style>
	<?php
}

/* ==========================================================================
   ۲) شورت‌کد کادر رنگی: [gv_box type="..." title="..." url="..."]متن[/gv_box]
   ========================================================================== */
add_shortcode( 'gv_box', 'gv_scbx_box_shortcode' );
function gv_scbx_box_shortcode( $atts, $content = null ) {
	gv_scbx_print_styles_once();

	$atts = shortcode_atts( array(
		'type'   => 'info',
		'title'  => '',
		'icon'   => '',
		'url'    => '',
		'target' => '_self',
	), $atts, 'gv_box' );

	$types = gv_scbx_box_types();
	$type  = isset( $types[ $atts['type'] ] ) ? $atts['type'] : 'info';
	$def   = $types[ $type ];

	$icon  = $atts['icon'] ? $atts['icon'] : $def['icon'];
	$title = $atts['title'];

	// اگر آدرس داده شده و عنوانی هم مشخص نشده، از برچسب پیش‌فرض همان نوع کادر استفاده می‌شود.
	if ( $atts['url'] && ! $title && 'link' === $type ) {
		$title = $def['label'];
	}

	$body = do_shortcode( trim( (string) $content ) );
	$body = wpautop( $body );

	$style = sprintf( '--gvbox-accent:%s;--gvbox-bg:%s;', esc_attr( $def['accent'] ), esc_attr( $def['bg'] ) );

	ob_start();
	?>
	<div class="gvbox gvbox-<?php echo esc_attr( $type ); ?>" style="<?php echo esc_attr( $style ); ?>">
		<?php if ( $title ) : ?>
			<div class="gvbox-head"><span class="gvbox-icon"><?php echo esc_html( $icon ); ?></span><span><?php echo esc_html( $title ); ?></span></div>
		<?php endif; ?>
		<div class="gvbox-body"><?php echo $body; // phpcs:ignore ?></div>
		<?php if ( $atts['url'] ) : ?>
			<a class="gvbox-more" href="<?php echo esc_url( $atts['url'] ); ?>" <?php echo ( '_blank' === $atts['target'] ) ? 'target="_blank" rel="noopener"' : ''; ?>>مطالعه بیشتر ⬅</a>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   ۳) شورت‌کد دکمه: [gv_button url="..." color="primary" style="solid" size="md"]متن[/gv_button]
   ========================================================================== */
add_shortcode( 'gv_button', 'gv_scbx_button_shortcode' );
function gv_scbx_button_shortcode( $atts, $content = null ) {
	gv_scbx_print_styles_once();

	$atts = shortcode_atts( array(
		'url'    => '#',
		'text'   => '',
		'color'  => 'primary', // primary, success, danger, warning, dark
		'style'  => 'solid',   // solid, outline, soft
		'size'   => 'md',      // sm, md, lg
		'icon'   => '',
		'target' => '_self',
		'block'  => 'no',      // yes = تمام عرض
	), $atts, 'gv_button' );

	$valid_colors = array( 'primary', 'success', 'danger', 'warning', 'dark' );
	$valid_styles = array( 'solid', 'outline', 'soft' );
	$valid_sizes  = array( 'sm', 'md', 'lg' );

	$color = in_array( $atts['color'], $valid_colors, true ) ? $atts['color'] : 'primary';
	$style = in_array( $atts['style'], $valid_styles, true ) ? $atts['style'] : 'solid';
	$size  = in_array( $atts['size'], $valid_sizes, true ) ? $atts['size'] : 'md';

	$text = $atts['text'] ? $atts['text'] : wp_strip_all_tags( (string) $content );
	if ( ! $text ) { $text = 'کلیک کنید'; }

	$classes = array( 'gvbtn', 'gvbtn-' . $style, 'gvbtn-' . $color );
	if ( 'md' !== $size ) { $classes[] = 'gvbtn-' . $size; }
	if ( 'yes' === $atts['block'] ) { $classes[] = 'gvbtn-block'; }

	$target_attr = ( '_blank' === $atts['target'] ) ? ' target="_blank" rel="noopener"' : '';

	return sprintf(
		'<a href="%1$s" class="%2$s"%3$s>%4$s%5$s</a>',
		esc_url( $atts['url'] ),
		esc_attr( implode( ' ', $classes ) ),
		$target_attr,
		$atts['icon'] ? '<span>' . esc_html( $atts['icon'] ) . '</span>' : '',
		esc_html( $text )
	);
}

/* ==========================================================================
   ۴) صفحه‌ی گالری در داشبورد (پیش‌نمایش زنده + کپی شورت‌کد)
   ========================================================================== */
add_action( 'admin_menu', 'gv_scbx_admin_menu' );
function gv_scbx_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'کادر و دکمه شورت‌کد | Groot Vision',
		'🧩 کادر و دکمه',
		'manage_options',
		GV_SCBX_PAGE_SLUG,
		'gv_scbx_render_admin_page'
	);
}

function gv_scbx_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$box_types    = gv_scbx_box_types();
	$button_combos = array(
		array( 'color' => 'primary', 'style' => 'solid',   'label' => 'اصلی / پر' ),
		array( 'color' => 'success', 'style' => 'solid',   'label' => 'موفقیت / پر' ),
		array( 'color' => 'danger',  'style' => 'solid',   'label' => 'خطر / پر' ),
		array( 'color' => 'warning', 'style' => 'solid',   'label' => 'هشدار / پر' ),
		array( 'color' => 'dark',    'style' => 'solid',   'label' => 'تیره / پر' ),
		array( 'color' => 'primary', 'style' => 'outline', 'label' => 'اصلی / خط‌دور' ),
		array( 'color' => 'primary', 'style' => 'soft',    'label' => 'اصلی / کم‌رنگ' ),
	);
	?>
	<div class="wrap gvscbx-wrap" dir="rtl">
		<style>
			.gvscbx-wrap{max-width:1100px;}
			.gvscbx-wrap h1{font-size:20px;margin:18px 0 6px;}
			.gvscbx-intro{color:#555;max-width:720px;margin-bottom:22px;}
			.gvscbx-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;margin-bottom:16px;}
			.gvscbx-card h2{font-size:15px;margin:0 0 12px;display:flex;align-items:center;gap:8px;}
			.gvscbx-row{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;}
			.gvscbx-preview{flex:1;min-width:260px;}
			.gvscbx-code-wrap{flex:1;min-width:280px;display:flex;gap:8px;align-items:flex-start;}
			.gvscbx-code{flex:1;background:#0f172a;color:#e2e8f0;border-radius:8px;padding:10px 12px;font-family:monospace;font-size:12.5px;direction:ltr;text-align:left;white-space:pre-wrap;word-break:break-all;margin:0;}
			.gvscbx-copy{border:1px solid #d1d5db;background:#f9fafb;border-radius:8px;padding:8px 12px;font-size:12.5px;cursor:pointer;white-space:nowrap;height:fit-content;}
			.gvscbx-copy.is-copied{background:#dcfce7;border-color:#16a34a;color:#15803d;}
			.gvscbx-btn-gallery{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;}
			.gvscbx-hint{font-size:12px;color:#6b7280;margin-top:10px;}
			.gvscbx-attr-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px;}
			.gvscbx-attr-table th,.gvscbx-attr-table td{border-bottom:1px solid #f1f1f1;padding:7px 10px;text-align:right;}
			.gvscbx-attr-table th{color:#374151;background:#f9fafb;}
			.gvscbx-attr-table code{direction:ltr;display:inline-block;}
		</style>

		<h1>🧩 کادر و دکمه شورت‌کد</h1>
		<p class="gvscbx-intro">هر کدام را که پسندیدید، دکمه‌ی «کپی شورت‌کد» را بزنید و داخل ادیتور متن وردپرس (در حالت متنی/HTML یا بلوک شورت‌کد) جای‌گذاری کنید. متن داخل کادر یا دکمه را با متن دلخواه خودتان جایگزین کنید.</p>

		<div class="gvscbx-card">
			<h2>📦 کادرهای رنگی</h2>
			<?php foreach ( $box_types as $key => $def ) :
				$sample_shortcode = '[gv_box type="' . $key . '" title="' . $def['label'] . '"]این یک متن نمونه برای پیش‌نمایش این کادر است.[/gv_box]';
				?>
				<div class="gvscbx-row" style="margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid #f1f1f1;">
					<div class="gvscbx-preview"><?php echo do_shortcode( $sample_shortcode ); ?></div>
					<div class="gvscbx-code-wrap">
						<pre class="gvscbx-code"><?php echo esc_html( '[gv_box type="' . $key . '" title="عنوان دلخواه"]متن دلخواه شما اینجا[/gv_box]' ); ?></pre>
						<button type="button" class="gvscbx-copy" data-copy="<?php echo esc_attr( '[gv_box type="' . $key . '" title="عنوان دلخواه"]متن دلخواه شما اینجا[/gv_box]' ); ?>">📋 کپی</button>
					</div>
				</div>
			<?php endforeach; ?>

			<p class="gvscbx-hint">💡 برای کادر «لینک به مقاله»، آدرس مقاله را هم اضافه کنید تا لینک «مطالعه بیشتر» زیر کادر نمایش داده شود، مثل:</p>
			<pre class="gvscbx-code" style="max-width:600px;">[gv_box type="link" title="مقاله مرتبط" url="https://example.com/article"]یک خلاصه کوتاه از مقاله اینجا بنویسید.[/gv_box]</pre>

			<table class="gvscbx-attr-table">
				<thead><tr><th>پارامتر</th><th>توضیح</th><th>مقادیر مجاز</th></tr></thead>
				<tbody>
					<tr><td><code>type</code></td><td>نوع/رنگ کادر</td><td>info, success, warning, danger, tip, note, quote, link</td></tr>
					<tr><td><code>title</code></td><td>عنوان بالای کادر (اختیاری)</td><td>هر متنی</td></tr>
					<tr><td><code>icon</code></td><td>اموجی سفارشی به‌جای آیکون پیش‌فرض (اختیاری)</td><td>هر اموجی</td></tr>
					<tr><td><code>url</code></td><td>لینک «مطالعه بیشتر» زیر کادر (اختیاری)</td><td>آدرس کامل</td></tr>
					<tr><td><code>target</code></td><td>باز شدن لینک در تب جدید</td><td>_self (پیش‌فرض) یا _blank</td></tr>
				</tbody>
			</table>
		</div>

		<div class="gvscbx-card">
			<h2>🔘 دکمه‌ها</h2>
			<div class="gvscbx-btn-gallery">
				<?php foreach ( $button_combos as $combo ) :
					echo do_shortcode( '[gv_button url="#" color="' . $combo['color'] . '" style="' . $combo['style'] . '"]' . $combo['label'] . '[/gv_button]' );
				endforeach; ?>
			</div>
			<div class="gvscbx-code-wrap">
				<pre class="gvscbx-code">[gv_button url="https://example.com" color="primary" style="solid" size="md" target="_blank"]متن دکمه شما[/gv_button]</pre>
				<button type="button" class="gvscbx-copy" data-copy='[gv_button url="https://example.com" color="primary" style="solid" size="md" target="_blank"]متن دکمه شما[/gv_button]'>📋 کپی</button>
			</div>

			<table class="gvscbx-attr-table">
				<thead><tr><th>پارامتر</th><th>توضیح</th><th>مقادیر مجاز</th></tr></thead>
				<tbody>
					<tr><td><code>url</code></td><td>آدرس مقصد دکمه</td><td>آدرس کامل</td></tr>
					<tr><td><code>color</code></td><td>رنگ دکمه</td><td>primary, success, danger, warning, dark</td></tr>
					<tr><td><code>style</code></td><td>سبک دکمه</td><td>solid (پر), outline (خط‌دور), soft (کم‌رنگ)</td></tr>
					<tr><td><code>size</code></td><td>سایز دکمه</td><td>sm, md (پیش‌فرض), lg</td></tr>
					<tr><td><code>icon</code></td><td>اموجی کنار متن دکمه (اختیاری)</td><td>هر اموجی</td></tr>
					<tr><td><code>target</code></td><td>باز شدن در تب جدید</td><td>_self (پیش‌فرض) یا _blank</td></tr>
					<tr><td><code>block</code></td><td>تمام‌عرض شدن دکمه</td><td>yes / no (پیش‌فرض)</td></tr>
				</tbody>
			</table>
		</div>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong> | اینستاگرام: grootvision</p>
	</div>

	<script>
	(function () {
		document.querySelectorAll( '.gvscbx-copy' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var text = btn.getAttribute( 'data-copy' ) || '';
				var done = function () {
					var original = btn.textContent;
					btn.textContent = '✅ کپی شد';
					btn.classList.add( 'is-copied' );
					setTimeout( function () {
						btn.textContent = original;
						btn.classList.remove( 'is-copied' );
					}, 1500 );
				};
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( done ).catch( function () {
						window.prompt( 'کپی خودکار ممکن نشد، دستی کپی کنید:', text );
					} );
				} else {
					window.prompt( 'کپی خودکار ممکن نشد، دستی کپی کنید:', text );
				}
			} );
		} );
	})();
	</script>
	<?php
}
