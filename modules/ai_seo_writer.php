<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — دستیار هوش‌مصنوعی نویسنده توضیحات و سئوی محصول
 * ----------------------------------------------------------
 *  کارمند کلمه کلیدی + توضیحات کوتاه را وارد می‌کند، محل انتشار
 *  (نوشته / برگه / محصول / ...) را انتخاب می‌کند، روی «تولید و
 *  نمایش» می‌زند و متن سئو‌شده (عنوان، توضیحات متا، محتوا،
 *  کلمه کلیدی فوکوس، کلمات کلیدی مرتبط) توسط OpenAI تولید و
 *  پیش‌نمایش می‌شود. سپس با یک کلیک «آپلود مستقیم» محتوا در
 *  همان بخش انتخاب‌شده به‌صورت پیش‌نویس/منتشرشده ساخته می‌شود
 *  و فیلدهای سئوی Yoast (عنوان سئو / متا دیسکریپشن / فوکوس
 *  کیورد) نیز به‌صورت خودکار پر می‌گردد.
 * ==========================================================
 */

define( 'GV_AI_SEO_PAGE_SLUG', 'gv-ai-seo-writer' );
define( 'GV_AI_SEO_OPTION',    'gv_ai_seo_settings' );

/* ==========================================================
   ۱) ثبت زیرمنو
   ========================================================== */
add_action( 'admin_menu', 'gv_ai_seo_register_menu', 20 );
function gv_ai_seo_register_menu() {
	$hook = add_submenu_page(
		defined( 'GV_HUB_SLUG' ) ? GV_HUB_SLUG : 'groot-vision-hub',
		'دستیار هوش‌مصنوعی سئو',
		'🤖 دستیار سئوی محصول',
		'edit_posts', // هم ادمین و هم کارمند (نویسنده/ادیتور) دسترسی داشته باشد
		GV_AI_SEO_PAGE_SLUG,
		'gv_ai_seo_render_page'
	);
	add_action( 'load-' . $hook, 'gv_ai_seo_enqueue_assets' );
}

function gv_ai_seo_enqueue_assets() {
	// همه‌چیز داخل همین فایل به‌صورت inline لود می‌شود، نیازی به فایل جدا نیست.
}

/* ==========================================================
   ۲) ذخیره‌ی تنظیمات (فقط ادمین) — روی admin_init پردازش می‌شود
   ========================================================== */
add_action( 'admin_init', 'gv_ai_seo_handle_settings_save' );
function gv_ai_seo_handle_settings_save() {
	if ( empty( $_POST['gv_ai_seo_settings_nonce'] ) ) { return; }
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gv_ai_seo_settings_nonce'] ) ), 'gv_ai_seo_save_settings' ) ) { return; }

	$settings = array(
		'api_key' => isset( $_POST['gv_ai_seo_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['gv_ai_seo_api_key'] ) ) : '',
		'model'   => isset( $_POST['gv_ai_seo_model'] ) ? sanitize_text_field( wp_unslash( $_POST['gv_ai_seo_model'] ) ) : 'gpt-4o-mini',
	);
	update_option( GV_AI_SEO_OPTION, $settings );

	wp_safe_redirect( add_query_arg( array( 'page' => GV_AI_SEO_PAGE_SLUG, 'gv_saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
}

function gv_ai_seo_get_settings() {
	$defaults = array( 'api_key' => '', 'model' => 'gpt-4o-mini' );
	$saved    = get_option( GV_AI_SEO_OPTION, array() );
	return wp_parse_args( $saved, $defaults );
}

/* ==========================================================
   ۳) لیست بخش‌های قابل انتخاب سایت (نوع نوشته‌ها)
   ========================================================== */
function gv_ai_seo_get_post_types() {
	$types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
	$out   = array();
	foreach ( $types as $type ) {
		if ( in_array( $type->name, array( 'attachment' ), true ) ) { continue; }
		$out[ $type->name ] = $type->labels->singular_name;
	}
	return $out;
}

// اولین تاکسونومی سلسله‌مراتبی (دسته‌بندی) مربوط به یک نوع پست را برمی‌گرداند
function gv_ai_seo_get_primary_taxonomy( $post_type ) {
	$taxonomies = get_object_taxonomies( $post_type, 'objects' );
	foreach ( $taxonomies as $tax ) {
		if ( $tax->hierarchical && $tax->public ) {
			return $tax->name;
		}
	}
	return '';
}

/* ==========================================================
   ۴) AJAX: گرفتن لیست دسته‌بندی‌های مربوط به یک نوع پست
   ========================================================== */
add_action( 'wp_ajax_gv_ai_seo_get_terms', 'gv_ai_seo_ajax_get_terms' );
function gv_ai_seo_ajax_get_terms() {
	check_ajax_referer( 'gv_ai_seo_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'دسترسی غیرمجاز' ); }

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
	$taxonomy  = gv_ai_seo_get_primary_taxonomy( $post_type );

	if ( ! $taxonomy ) {
		wp_send_json_success( array( 'taxonomy' => '', 'terms' => array() ) );
	}

	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
	$list  = array();
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$list[] = array( 'id' => $term->term_id, 'name' => $term->name );
		}
	}
	wp_send_json_success( array( 'taxonomy' => $taxonomy, 'terms' => $list ) );
}

/* ==========================================================
   ۵) AJAX: تولید محتوا با OpenAI
   ========================================================== */
add_action( 'wp_ajax_gv_ai_seo_generate', 'gv_ai_seo_ajax_generate' );
function gv_ai_seo_ajax_generate() {
	check_ajax_referer( 'gv_ai_seo_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'دسترسی غیرمجاز' ); }

	$keyword     = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
	$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
	$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';

	if ( empty( $keyword ) || empty( $description ) ) {
		wp_send_json_error( 'کلمه کلیدی و توضیحات را وارد کنید.' );
	}

	$settings = gv_ai_seo_get_settings();
	if ( empty( $settings['api_key'] ) ) {
		wp_send_json_error( 'ابتدا کلید API سرویس OpenAI را در بخش تنظیمات وارد کنید.' );
	}

	$types      = gv_ai_seo_get_post_types();
	$type_label = isset( $types[ $post_type ] ) ? $types[ $post_type ] : $post_type;

	$is_product = ( 'product' === $post_type );

	$system_prompt = 'تو یک متخصص کپی‌رایتینگ و سئو (بر اساس استاندارد‌های افزونه Yoast SEO) هستی که فقط و فقط به زبان فارسی روان و طبیعی می‌نویسی. خروجی را همیشه فقط به‌صورت یک JSON معتبر و بدون هیچ توضیح اضافه، بدون بک‌تیک و بدون Markdown برگردان.';

	$user_prompt  = "برای بخش «{$type_label}» یک سایت وردپرسی، بر اساس کلمه کلیدی فوکوس زیر و توضیحات کارمند، محتوای سئو‌شده تولید کن.\n\n";
	$user_prompt .= "کلمه کلیدی فوکوس: {$keyword}\n";
	$user_prompt .= "توضیحات/موضوع مورد نظر کارمند: {$description}\n\n";
	$user_prompt .= "قوانین سئوی Yoast که باید کاملاً رعایت شود:\n";
	$user_prompt .= "- عنوان سئو (title) بین ۴۰ تا ۶۰ کاراکتر و شامل کلمه کلیدی فوکوس، ترجیحاً در ابتدای عنوان.\n";
	$user_prompt .= "- توضیحات متا (meta_description) بین ۱۲۰ تا ۱۵۶ کاراکتر، شامل کلمه کلیدی فوکوس و یک دعوت به عمل (CTA).\n";
	$user_prompt .= "- کلمه کلیدی فوکوس باید در همان پاراگراف اول محتوا نیز بیاید.\n";
	$user_prompt .= "- تراکم کلمه کلیدی در متن بین ۰.۵ تا ۲.۵ درصد باشد (نه بیشتر، نه کمتر؛ از تکرار مصنوعی خودداری کن).\n";
	$user_prompt .= "- از حداقل یک زیرعنوان H2 یا H3 مرتبط با کلمه کلیدی استفاده کن.\n";
	$user_prompt .= "- متن باید طبیعی، خوانا، بدون غلط املایی و متناسب با بخش «{$type_label}» باشد";
	$user_prompt .= $is_product ? " (لحن فروش‌محور، معرفی ویژگی‌ها با bullet list و یک CTA خرید در پایان).\n" : ".\n";
	$user_prompt .= "- محتوا را با تگ‌های HTML ساده (p, h2, h3, ul, li, strong) بنویس، نه Markdown.\n\n";
	$user_prompt .= "خروجی را دقیقاً با این ساختار JSON برگردان:\n";
	$user_prompt .= '{"title": "...", "meta_description": "...", "focus_keyword": "...", "content": "...", "related_keywords": ["...", "..."]}';

	$body = array(
		'model'           => $settings['model'],
		'response_format' => array( 'type' => 'json_object' ),
		'temperature'     => 0.7,
		'messages'        => array(
			array( 'role' => 'system', 'content' => $system_prompt ),
			array( 'role' => 'user', 'content' => $user_prompt ),
		),
	);

	$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
		'timeout' => 60,
		'headers' => array(
			'Authorization' => 'Bearer ' . $settings['api_key'],
			'Content-Type'  => 'application/json',
		),
		'body'    => wp_json_encode( $body ),
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( 'خطا در اتصال به OpenAI: ' . $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$raw  = wp_remote_retrieve_body( $response );
	$data = json_decode( $raw, true );

	if ( 200 !== (int) $code ) {
		$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'خطای نامشخص از سمت OpenAI';
		wp_send_json_error( 'خطای OpenAI: ' . $msg );
	}

	$content_raw = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
	$content_raw = trim( str_replace( array( '```json', '```' ), '', $content_raw ) );
	$parsed      = json_decode( $content_raw, true );

	if ( ! is_array( $parsed ) || empty( $parsed['title'] ) ) {
		wp_send_json_error( 'پاسخ هوش مصنوعی قابل پردازش نبود، دوباره تلاش کنید.' );
	}

	$result = array(
		'title'             => sanitize_text_field( $parsed['title'] ),
		'meta_description'  => sanitize_text_field( isset( $parsed['meta_description'] ) ? $parsed['meta_description'] : '' ),
		'focus_keyword'     => sanitize_text_field( isset( $parsed['focus_keyword'] ) ? $parsed['focus_keyword'] : $keyword ),
		'content'           => wp_kses_post( isset( $parsed['content'] ) ? $parsed['content'] : '' ),
		'related_keywords'  => isset( $parsed['related_keywords'] ) && is_array( $parsed['related_keywords'] ) ? array_map( 'sanitize_text_field', $parsed['related_keywords'] ) : array(),
	);

	$result['seo_check'] = gv_ai_seo_analyze( $result );

	wp_send_json_success( $result );
}

/* ==========================================================
   ۶) تحلیل ساده‌ی سئو (چک‌لیست سبک Yoast) روی خروجی تولیدشده
   ========================================================== */
function gv_ai_seo_analyze( $data ) {
	$title   = $data['title'];
	$meta    = $data['meta_description'];
	$kw      = $data['focus_keyword'];
	$content = wp_strip_all_tags( $data['content'] );

	$checks = array();

	$checks['keyword_in_title'] = ( '' !== $kw && false !== mb_stripos( $title, $kw ) );
	$checks['title_length']     = ( mb_strlen( $title ) >= 30 && mb_strlen( $title ) <= 65 );
	$checks['keyword_in_meta']  = ( '' !== $kw && false !== mb_stripos( $meta, $kw ) );
	$checks['meta_length']      = ( mb_strlen( $meta ) >= 100 && mb_strlen( $meta ) <= 165 );

	$first_paragraph = '';
	if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $data['content'], $m ) ) {
		$first_paragraph = wp_strip_all_tags( $m[1] );
	} else {
		$first_paragraph = mb_substr( $content, 0, 300 );
	}
	$checks['keyword_in_intro'] = ( '' !== $kw && false !== mb_stripos( $first_paragraph, $kw ) );

	$word_count = str_word_count( $content ) > 0 ? str_word_count( $content ) : max( 1, (int) round( mb_strlen( $content ) / 5 ) );
	$kw_count   = '' !== $kw ? max( 0, substr_count( mb_strtolower( $content ), mb_strtolower( $kw ) ) ) : 0;
	$density    = $word_count > 0 ? ( $kw_count / $word_count ) * 100 : 0;
	$checks['keyword_density'] = ( $density >= 0.3 && $density <= 3 );

	$checks['has_subheading'] = ( false !== stripos( $data['content'], '<h2' ) || false !== stripos( $data['content'], '<h3' ) );
	$checks['content_length'] = ( $word_count >= 120 );

	$score = 0;
	foreach ( $checks as $ok ) { if ( $ok ) { $score++; } }
	$checks['score']     = $score;
	$checks['max_score'] = count( $checks ) - 1; // منهای خودِ score
	$checks['density_value'] = round( $density, 2 );

	return $checks;
}

/* ==========================================================
   ۷) AJAX: آپلود مستقیم محتوای تولیدشده در وردپرس
   ========================================================== */
add_action( 'wp_ajax_gv_ai_seo_publish', 'gv_ai_seo_ajax_publish' );
function gv_ai_seo_ajax_publish() {
	check_ajax_referer( 'gv_ai_seo_nonce', 'nonce' );

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
	$pt_object = get_post_type_object( $post_type );
	if ( ! $pt_object ) { wp_send_json_error( 'نوع محتوا نامعتبر است.' ); }

	$cap = ! empty( $pt_object->cap->publish_posts ) ? $pt_object->cap->publish_posts : 'publish_posts';
	if ( ! current_user_can( $cap ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'شما دسترسی انتشار در این بخش را ندارید.' );
	}

	$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$content  = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
	$meta_d   = isset( $_POST['meta_description'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_description'] ) ) : '';
	$focus_kw = isset( $_POST['focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ) ) : '';
	$status   = isset( $_POST['status'] ) && 'publish' === $_POST['status'] ? 'publish' : 'draft';
	$term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

	if ( empty( $title ) || empty( $content ) ) {
		wp_send_json_error( 'عنوان و محتوا خالی است.' );
	}

	$postarr = array(
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => $status,
		'post_type'    => $post_type,
		'post_author'  => get_current_user_id(),
	);

	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( 'خطا در ایجاد محتوا: ' . $post_id->get_error_message() );
	}

	// دسته‌بندی (در صورت انتخاب)
	if ( $term_id > 0 ) {
		$taxonomy = gv_ai_seo_get_primary_taxonomy( $post_type );
		if ( $taxonomy ) {
			wp_set_object_terms( $post_id, array( $term_id ), $taxonomy );
		}
	}

	// فیلدهای سئو Yoast
	if ( '' !== $title ) {
		update_post_meta( $post_id, '_yoast_wpseo_title', $title );
	}
	if ( '' !== $meta_d ) {
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_d );
	}
	if ( '' !== $focus_kw ) {
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_kw );
	}

	wp_send_json_success( array(
		'post_id'   => $post_id,
		'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
		'view_url'  => get_permalink( $post_id ),
		'status'    => $status,
	) );
}

/* ==========================================================
   ۸) رندر صفحه
   ========================================================== */
function gv_ai_seo_render_page() {
	if ( ! current_user_can( 'edit_posts' ) ) { return; }

	$settings   = gv_ai_seo_get_settings();
	$is_admin   = current_user_can( 'manage_options' );
	$post_types = gv_ai_seo_get_post_types();
	$nonce      = wp_create_nonce( 'gv_ai_seo_nonce' );
	$saved      = isset( $_GET['gv_saved'] );
	?>
	<div class="wrap gv-ai-wrap" dir="rtl">

		<div class="gv-ai-header">
			<div class="gv-ai-header-icon">🤖</div>
			<div>
				<h1>دستیار هوش‌مصنوعی نویسنده توضیحات و سئو</h1>
				<p>کلمه کلیدی و توضیحات کوتاه را بنویسید، بخش سایت را انتخاب کنید؛ محتوای سئوشده تولید و مستقیم آپلود می‌شود.</p>
			</div>
		</div>

		<?php if ( $saved ) : ?>
			<div class="gv-ai-notice gv-ai-notice-success">✅ تنظیمات ذخیره شد.</div>
		<?php endif; ?>

		<?php if ( empty( $settings['api_key'] ) && ! $is_admin ) : ?>
			<div class="gv-ai-notice gv-ai-notice-warn">⚠️ هنوز کلید API توسط مدیر سایت تنظیم نشده است. لطفاً با مدیر سایت هماهنگ کنید.</div>
		<?php endif; ?>

		<div class="gv-ai-grid">

			<!-- ستون فرم -->
			<div class="gv-ai-card gv-ai-form-card">
				<h2>۱) اطلاعات محتوا</h2>

				<label class="gv-ai-label">کلمه کلیدی فوکوس (سئو)</label>
				<input type="text" id="gv_ai_keyword" class="gv-ai-input" placeholder="مثلاً: خرید کیف چرم زنانه">

				<label class="gv-ai-label">توضیحات / موضوع (برای هوش مصنوعی)</label>
				<textarea id="gv_ai_description" class="gv-ai-textarea" rows="5" placeholder="توضیح بده این محتوا درباره‌ی چیه، چه نکاتی حتما باید بیاد، لحن دلخواه و..."></textarea>

				<label class="gv-ai-label">بخش سایت برای انتشار</label>
				<select id="gv_ai_post_type" class="gv-ai-input">
					<?php foreach ( $post_types as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<div id="gv_ai_term_wrap" style="display:none;">
					<label class="gv-ai-label">دسته‌بندی (اختیاری)</label>
					<select id="gv_ai_term" class="gv-ai-input">
						<option value="0">— بدون دسته‌بندی —</option>
					</select>
				</div>

				<label class="gv-ai-label">وضعیت انتشار</label>
				<select id="gv_ai_status" class="gv-ai-input">
					<option value="draft">پیش‌نویس (برای بررسی قبل از انتشار نهایی)</option>
					<option value="publish">منتشر شود (بلافاصله روی سایت)</option>
				</select>

				<button type="button" id="gv_ai_generate_btn" class="gv-ai-btn gv-ai-btn-primary">
					👁️ تولید و نمایش پیش‌نمایش
				</button>

				<div id="gv_ai_generate_status" class="gv-ai-status"></div>
			</div>

			<!-- ستون پیش‌نمایش -->
			<div class="gv-ai-card gv-ai-preview-card">
				<h2>۲) پیش‌نمایش سئوشده</h2>

				<div id="gv_ai_empty_state" class="gv-ai-empty">
					هنوز محتوایی تولید نشده. فرم سمت راست را پر کنید و روی «تولید و نمایش» بزنید.
				</div>

				<div id="gv_ai_preview_body" style="display:none;">

					<div id="gv_ai_seo_badge" class="gv-ai-seo-badge"></div>

					<label class="gv-ai-label">عنوان سئو</label>
					<input type="text" id="gv_ai_out_title" class="gv-ai-input">

					<label class="gv-ai-label">توضیحات متا</label>
					<textarea id="gv_ai_out_meta" class="gv-ai-textarea" rows="3"></textarea>

					<label class="gv-ai-label">کلمه کلیدی فوکوس</label>
					<input type="text" id="gv_ai_out_focus" class="gv-ai-input">

					<label class="gv-ai-label">کلمات کلیدی مرتبط پیشنهادی</label>
					<div id="gv_ai_out_related" class="gv-ai-tags"></div>

					<label class="gv-ai-label">محتوای اصلی</label>
					<textarea id="gv_ai_out_content" class="gv-ai-textarea gv-ai-content-area" rows="14"></textarea>

					<ul id="gv_ai_checklist" class="gv-ai-checklist"></ul>

					<button type="button" id="gv_ai_publish_btn" class="gv-ai-btn gv-ai-btn-success">
						⬆️ آپلود مستقیم در سایت
					</button>
					<div id="gv_ai_publish_status" class="gv-ai-status"></div>
				</div>
			</div>
		</div>

		<?php if ( $is_admin ) : ?>
		<!-- تنظیمات (فقط مدیر سایت) -->
		<div class="gv-ai-card gv-ai-settings-card">
			<h2>⚙️ تنظیمات سرویس هوش مصنوعی (فقط مدیر)</h2>
			<form method="post">
				<?php wp_nonce_field( 'gv_ai_seo_save_settings', 'gv_ai_seo_settings_nonce' ); ?>
				<label class="gv-ai-label">کلید API سرویس OpenAI</label>
				<input type="password" name="gv_ai_seo_api_key" class="gv-ai-input" value="<?php echo esc_attr( $settings['api_key'] ); ?>" placeholder="sk-...">

				<label class="gv-ai-label">مدل هوش مصنوعی</label>
				<select name="gv_ai_seo_model" class="gv-ai-input">
					<?php
					$models = array( 'gpt-4o-mini' => 'GPT-4o mini (پیشنهادی، سریع و اقتصادی)', 'gpt-4o' => 'GPT-4o (کیفیت بالاتر)' );
					foreach ( $models as $val => $label ) :
						?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['model'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<button type="submit" class="gv-ai-btn gv-ai-btn-primary">ذخیره تنظیمات</button>
			</form>
		</div>
		<?php endif; ?>
	</div>

	<style>
		.gv-ai-wrap{max-width:1200px;margin-top:20px;font-family:'Vazirmatn',Tahoma,sans-serif;}
		.gv-ai-header{display:flex;align-items:center;gap:16px;background:linear-gradient(120deg,#0e4037,#145c4d);color:#fff;padding:24px 28px;border-radius:16px;margin-bottom:22px;box-shadow:0 10px 30px rgba(14,64,55,.25);}
		.gv-ai-header-icon{font-size:34px;}
		.gv-ai-header h1{margin:0;font-size:21px;color:#fff;}
		.gv-ai-header p{margin:6px 0 0;font-size:13px;color:#cbd5e1;}

		.gv-ai-notice{padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:13px;}
		.gv-ai-notice-success{background:rgba(74,222,128,.12);border:1px solid #4ade80;color:#15803d;}
		.gv-ai-notice-warn{background:rgba(250,204,21,.12);border:1px solid #facc15;color:#a16207;}

		.gv-ai-grid{display:grid;grid-template-columns:1fr 1.2fr;gap:20px;align-items:start;}
		@media(max-width:960px){.gv-ai-grid{grid-template-columns:1fr;}}

		.gv-ai-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.04);margin-bottom:20px;}
		.gv-ai-card h2{margin:0 0 16px;font-size:16px;color:#0f172a;}

		.gv-ai-label{display:block;font-size:12.5px;font-weight:700;color:#334155;margin:14px 0 6px;}
		.gv-ai-label:first-of-type{margin-top:0;}
		.gv-ai-input,.gv-ai-textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;font-size:13px;font-family:inherit;box-sizing:border-box;}
		.gv-ai-textarea{resize:vertical;}
		.gv-ai-content-area{font-family:monospace;direction:rtl;}

		.gv-ai-btn{margin-top:18px;border:none;border-radius:12px;padding:12px 20px;font-size:13.5px;font-weight:700;cursor:pointer;width:100%;transition:filter .15s ease, transform .15s ease;}
		.gv-ai-btn:hover{filter:brightness(1.07);transform:translateY(-1px);}
		.gv-ai-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
		.gv-ai-btn-primary{background:#0e4037;color:#fff;}
		.gv-ai-btn-success{background:linear-gradient(120deg,#4ade80,#22c55e);color:#052e18;}

		.gv-ai-status{margin-top:10px;font-size:12.5px;min-height:18px;}
		.gv-ai-status.error{color:#dc2626;}
		.gv-ai-status.ok{color:#16a34a;}

		.gv-ai-empty{color:#94a3b8;font-size:13px;text-align:center;padding:40px 10px;}

		.gv-ai-seo-badge{display:inline-block;padding:6px 14px;border-radius:20px;font-size:12.5px;font-weight:700;margin-bottom:14px;}
		.gv-ai-seo-badge.good{background:rgba(74,222,128,.15);color:#15803d;}
		.gv-ai-seo-badge.mid{background:rgba(250,204,21,.15);color:#a16207;}
		.gv-ai-seo-badge.bad{background:rgba(248,113,113,.15);color:#b91c1c;}

		.gv-ai-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;}
		.gv-ai-tag{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:20px;padding:4px 12px;font-size:11.5px;color:#334155;}

		.gv-ai-checklist{list-style:none;margin:16px 0 0;padding:0;background:#f8fafc;border-radius:10px;padding:14px 16px;}
		.gv-ai-checklist li{font-size:12.5px;padding:4px 0;color:#334155;}

		@media(max-width:640px){.gv-ai-grid{grid-template-columns:1fr;}}
	</style>

	<script>
	(function(){
		var ajaxUrl = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
		var nonce   = '<?php echo esc_js( $nonce ); ?>';

		var els = {
			keyword: document.getElementById('gv_ai_keyword'),
			description: document.getElementById('gv_ai_description'),
			postType: document.getElementById('gv_ai_post_type'),
			termWrap: document.getElementById('gv_ai_term_wrap'),
			term: document.getElementById('gv_ai_term'),
			status: document.getElementById('gv_ai_status'),
			genBtn: document.getElementById('gv_ai_generate_btn'),
			genStatus: document.getElementById('gv_ai_generate_status'),
			emptyState: document.getElementById('gv_ai_empty_state'),
			previewBody: document.getElementById('gv_ai_preview_body'),
			seoBadge: document.getElementById('gv_ai_seo_badge'),
			outTitle: document.getElementById('gv_ai_out_title'),
			outMeta: document.getElementById('gv_ai_out_meta'),
			outFocus: document.getElementById('gv_ai_out_focus'),
			outRelated: document.getElementById('gv_ai_out_related'),
			outContent: document.getElementById('gv_ai_out_content'),
			checklist: document.getElementById('gv_ai_checklist'),
			pubBtn: document.getElementById('gv_ai_publish_btn'),
			pubStatus: document.getElementById('gv_ai_publish_status')
		};

		var checklistLabels = {
			keyword_in_title: 'کلمه کلیدی در عنوان استفاده شده',
			title_length: 'طول عنوان مناسب است (۳۰ تا ۶۵ کاراکتر)',
			keyword_in_meta: 'کلمه کلیدی در توضیحات متا استفاده شده',
			meta_length: 'طول توضیحات متا مناسب است (۱۰۰ تا ۱۶۵ کاراکتر)',
			keyword_in_intro: 'کلمه کلیدی در پاراگراف اول آمده',
			keyword_density: 'تراکم کلمه کلیدی در متن مناسب است',
			has_subheading: 'حداقل یک زیرعنوان (H2/H3) دارد',
			content_length: 'طول محتوا کافی است'
		};

		function loadTerms(postType) {
			var body = new URLSearchParams();
			body.append('action', 'gv_ai_seo_get_terms');
			body.append('nonce', nonce);
			body.append('post_type', postType);

			fetch(ajaxUrl, { method: 'POST', body: body })
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (!res.success) { els.termWrap.style.display = 'none'; return; }
					if (!res.data.taxonomy || !res.data.terms.length) {
						els.termWrap.style.display = 'none';
						return;
					}
					els.term.innerHTML = '<option value="0">— بدون دسته‌بندی —</option>';
					res.data.terms.forEach(function(t){
						var opt = document.createElement('option');
						opt.value = t.id;
						opt.textContent = t.name;
						els.term.appendChild(opt);
					});
					els.termWrap.style.display = 'block';
				})
				.catch(function(){ els.termWrap.style.display = 'none'; });
		}

		els.postType.addEventListener('change', function(){ loadTerms(this.value); });
		loadTerms(els.postType.value);

		els.genBtn.addEventListener('click', function(){
			var keyword = els.keyword.value.trim();
			var description = els.description.value.trim();

			if (!keyword || !description) {
				els.genStatus.className = 'gv-ai-status error';
				els.genStatus.textContent = 'لطفاً کلمه کلیدی و توضیحات را وارد کنید.';
				return;
			}

			els.genBtn.disabled = true;
			els.genBtn.textContent = '⏳ در حال تولید محتوا...';
			els.genStatus.className = 'gv-ai-status';
			els.genStatus.textContent = '';

			var body = new URLSearchParams();
			body.append('action', 'gv_ai_seo_generate');
			body.append('nonce', nonce);
			body.append('keyword', keyword);
			body.append('description', description);
			body.append('post_type', els.postType.value);

			fetch(ajaxUrl, { method: 'POST', body: body })
				.then(function(r){ return r.json(); })
				.then(function(res){
					els.genBtn.disabled = false;
					els.genBtn.textContent = '👁️ تولید و نمایش پیش‌نمایش';

					if (!res.success) {
						els.genStatus.className = 'gv-ai-status error';
						els.genStatus.textContent = res.data || 'خطایی رخ داد.';
						return;
					}

					var d = res.data;
					els.outTitle.value = d.title || '';
					els.outMeta.value = d.meta_description || '';
					els.outFocus.value = d.focus_keyword || '';
					els.outContent.value = d.content || '';

					els.outRelated.innerHTML = '';
					(d.related_keywords || []).forEach(function(k){
						var span = document.createElement('span');
						span.className = 'gv-ai-tag';
						span.textContent = k;
						els.outRelated.appendChild(span);
					});

					var sc = d.seo_check || {};
					els.checklist.innerHTML = '';
					Object.keys(checklistLabels).forEach(function(key){
						var li = document.createElement('li');
						var ok = !!sc[key];
						li.textContent = (ok ? '✅ ' : '⚠️ ') + checklistLabels[key];
						els.checklist.appendChild(li);
					});

					var score = sc.score || 0;
					var max = sc.max_score || 8;
					var ratio = score / max;
					els.seoBadge.className = 'gv-ai-seo-badge ' + (ratio >= 0.85 ? 'good' : (ratio >= 0.6 ? 'mid' : 'bad'));
					els.seoBadge.textContent = (ratio >= 0.85 ? '✅ سئو شده / اوکی است' : (ratio >= 0.6 ? '⚠️ نسبتاً بهینه — بهتر است بازبینی شود' : '❌ نیاز به اصلاح دارد')) + ' (' + score + '/' + max + ')';

					els.emptyState.style.display = 'none';
					els.previewBody.style.display = 'block';
					els.pubStatus.textContent = '';
				})
				.catch(function(){
					els.genBtn.disabled = false;
					els.genBtn.textContent = '👁️ تولید و نمایش پیش‌نمایش';
					els.genStatus.className = 'gv-ai-status error';
					els.genStatus.textContent = 'خطا در ارتباط با سرور.';
				});
		});

		els.pubBtn.addEventListener('click', function(){
			els.pubBtn.disabled = true;
			els.pubBtn.textContent = '⏳ در حال آپلود...';
			els.pubStatus.className = 'gv-ai-status';
			els.pubStatus.textContent = '';

			var body = new URLSearchParams();
			body.append('action', 'gv_ai_seo_publish');
			body.append('nonce', nonce);
			body.append('post_type', els.postType.value);
			body.append('title', els.outTitle.value);
			body.append('content', els.outContent.value);
			body.append('meta_description', els.outMeta.value);
			body.append('focus_keyword', els.outFocus.value);
			body.append('status', els.status.value);
			body.append('term_id', els.term.value || '0');

			fetch(ajaxUrl, { method: 'POST', body: body })
				.then(function(r){ return r.json(); })
				.then(function(res){
					els.pubBtn.disabled = false;
					els.pubBtn.textContent = '⬆️ آپلود مستقیم در سایت';

					if (!res.success) {
						els.pubStatus.className = 'gv-ai-status error';
						els.pubStatus.textContent = res.data || 'خطا در آپلود.';
						return;
					}

					els.pubStatus.className = 'gv-ai-status ok';
					els.pubStatus.textContent = '✅ با موفقیت آپلود شد. در حال انتقال به صفحه ویرایش...';

					setTimeout(function(){
						window.location.href = res.data.edit_url;
					}, 900);
				})
				.catch(function(){
					els.pubBtn.disabled = false;
					els.pubBtn.textContent = '⬆️ آپلود مستقیم در سایت';
					els.pubStatus.className = 'gv-ai-status error';
					els.pubStatus.textContent = 'خطا در ارتباط با سرور.';
				});
		});
	})();
	</script>
	<?php
}
