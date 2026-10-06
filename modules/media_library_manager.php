<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — مدیریت فایل‌های چندرسانه‌ای (نسخه ۳: تشخیص عمیق + بکاپ)
 *  ------------------------------------------------------------
 *  تغییرات این نسخه:
 *  ۱) تشخیص استفاده بسیار دقیق‌تر شد:
 *     - نام فایل‌های URL-encode شده (فارسی) و \uXXXX داخل JSON
 *     - شناسه‌ی عددی تصاویر در JSON/سریالایز/شورت‌کد (گالری، ACF، ووکامرس، المنتور، ...)
 *     - تمام جدول options (تنظیمات قالب، Redux، Kirki، ACF Options و ...)
 *     - منوها (تصویر آیتم منو)، تصویر دسته‌بندی‌ها (termmeta)
 *     - جدول‌های اختصاصی اسلایدرها: Slider Revolution، LayerSlider،
 *       Smart Slider، NextGEN و هر جدول مشابه دیگر
 *     - نسخه‌های قدیمی (revision) دیگر باعث «استفاده‌شده» نمی‌شوند
 *  ۲) بکاپ خودکار قبل از هر حذف (فایل‌ها + اطلاعات دیتابیس با همان ID قبلی)
 *     با امکان «بازگردانی» یا «پاک کردن دائمی بکاپ».
 * ==========================================================
 */

define( 'GV_MLM_PAGE_SLUG',  'gv-media-manager' );
define( 'GV_MLM_TRANSIENT',  'gv_mlm_scan_data' );   // نتیجه نهایی اسکن
define( 'GV_MLM_STATE',      'gv_mlm_scan_state' );  // وضعیت اسکن در حال انجام
define( 'GV_MLM_NONCE',      'gv_mlm_nonce_action' );

define( 'GV_MLM_BK_LIST',    'gv_mlm_bk_list' );     // فهرست دسته‌های بکاپ
define( 'GV_MLM_BK_TOKEN',   'gv_mlm_bk_token' );    // بخش تصادفی نام پوشه بکاپ
define( 'GV_MLM_BK_PREFIX',  'gv_mlm_bk_' );         // + شناسه دسته = فهرست فایل‌های همان دسته

define( 'GV_MLM_BATCH_ATT',   300 );   // تعداد فایل در هر تکه
define( 'GV_MLM_BATCH_POSTS', 150 );   // تعداد نوشته در هر تکه
define( 'GV_MLM_BATCH_META',  3000 );  // تعداد ردیف postmeta در هر تکه
define( 'GV_MLM_BATCH_TERM',  3000 );  // تعداد ردیف termmeta در هر تکه
define( 'GV_MLM_BATCH_OPT',   150 );   // تعداد ردیف options در هر تکه
define( 'GV_MLM_BATCH_TBL',   300 );   // تعداد ردیف جدول‌های اسلایدر در هر تکه
define( 'GV_MLM_MAX_USAGE',   12 );    // حداکثر محل استفاده ذخیره‌شده برای هر فایل
define( 'GV_MLM_STEP_SECONDS', 8 );    // حداکثر زمان هر درخواست AJAX

/* ==========================================================================
   ۱) منوی مدیریت
   ========================================================================== */
add_action( 'admin_menu', 'gv_mlm_admin_menu' );
function gv_mlm_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'مدیریت فایل‌های چندرسانه‌ای | Groot Vision',
		'🖼️ مدیریت رسانه‌ها',
		'manage_options',
		GV_MLM_PAGE_SLUG,
		'gv_mlm_render_admin_page'
	);
}

/* ==========================================================================
   ۲) کمک‌تابع‌ها
   ========================================================================== */
function gv_mlm_format_size( $bytes ) {
	$bytes = (int) $bytes;
	if ( $bytes <= 0 ) { return '—'; }

	$units = array( 'بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت' );
	$i     = 0;
	$value = $bytes;
	while ( $value >= 1024 && $i < count( $units ) - 1 ) {
		$value /= 1024;
		$i++;
	}
	return number_format_i18n( $value, ( $i > 0 ? 2 : 0 ) ) . ' ' . $units[ $i ];
}

function gv_mlm_mime_group( $mime ) {
	$mime = (string) $mime;
	if ( 0 === strpos( $mime, 'image/' ) ) { return 'image'; }
	if ( 0 === strpos( $mime, 'video/' ) ) { return 'video'; }
	if ( 0 === strpos( $mime, 'audio/' ) ) { return 'audio'; }
	if ( 0 === strpos( $mime, 'application/' ) || 0 === strpos( $mime, 'text/' ) ) { return 'document'; }
	return 'other';
}

function gv_mlm_type_label( $group ) {
	$labels = array(
		'image'    => 'تصویر',
		'video'    => 'ویدیو',
		'audio'    => 'صوت',
		'document' => 'سند',
		'other'    => 'سایر',
	);
	return isset( $labels[ $group ] ) ? $labels[ $group ] : 'سایر';
}

function gv_mlm_type_icon( $group ) {
	$icons = array(
		'image'    => '🖼️',
		'video'    => '🎬',
		'audio'    => '🎵',
		'document' => '📄',
		'other'    => '📁',
	);
	return isset( $icons[ $group ] ) ? $icons[ $group ] : '📁';
}

/**
 * نام فایل را نرمال می‌کند تا سایزهای مختلف یک تصویر
 * (مثلاً photo-300x200.jpg و photo-scaled.jpg و photo.jpg.webp) همگی به photo.jpg برسند.
 */
function gv_mlm_norm_name( $name ) {
	$name = wp_basename( str_replace( '\\', '/', (string) $name ) );
	$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
	$name = preg_replace( '/\.(jpe?g|png|gif|bmp|tiff?)\.(webp|avif)$/', '.$1', $name );
	$name = preg_replace( '/-\d+x\d+(?=\.[^.]+$)/', '', $name );
	$name = preg_replace( '/-(scaled|rotated|e\d+)(?=\.[^.]+$)/', '', $name );
	return $name;
}

/** کلیدهایی (در JSON / آرایه‌ی سریالایز) که معمولاً شناسه‌ی رسانه را نگه می‌دارند */
function gv_mlm_id_keys() {
	return 'id|ids|imageid|image_id|mediaid|media_id|attachment_id|attachmentid|bg_image_id|bgimageid|background_image_id|logo_id|thumbnail_id|thumbnailid|custom_logo|site_logo|site_icon|poster_id|video_id|file_id|fileid|img_id|photo_id|picture_id|gallery_ids|gallery|image|bg_image|background_image|bgimage';
}

/**
 * از یک متن (محتوای نوشته، متای المنتور، مقدار ویجت، ردیف اسلایدر و ...) تمام
 * نام‌فایل‌ها و شناسه‌های رسانه‌ی ارجاع‌داده‌شده را بیرون می‌کشد.
 */
function gv_mlm_extract_refs( $text ) {
	$names = array();
	$ids   = array();

	if ( ! is_string( $text ) || strlen( $text ) < 3 ) {
		return array( $names, $ids );
	}

	// \u0627 داخل JSON را به حرف واقعی تبدیل می‌کنیم (نام فایل‌های فارسی)
	if ( false !== strpos( $text, '\\u' ) ) {
		$text = preg_replace_callback(
			'/\\\\u([0-9a-fA-F]{4})/',
			function ( $m ) { return html_entity_decode( '&#x' . $m[1] . ';', ENT_QUOTES, 'UTF-8' ); },
			$text
		);
	}

	$end = '(?![\w\/\\\\.\-%:])'; // عدد باید واقعاً تمام شود (نه ابتدای تاریخ/مسیر)
	$q   = '\\\\*["\']?';         // کوتیشن اختیاری با بک‌اسلش اختیاری (JSON تودرتو)
	$k   = gv_mlm_id_keys();

	// کلاس‌ها و بلوک‌های وردپرس
	if ( false !== stripos( $text, 'wp-image-' ) && preg_match_all( '/wp-image-(\d+)/', $text, $m ) ) {
		$ids = array_merge( $ids, $m[1] );
	}
	if ( false !== stripos( $text, 'attachment_' ) && preg_match_all( '/attachment_(\d+)/', $text, $m ) ) {
		$ids = array_merge( $ids, $m[1] );
	}
	if ( false !== stripos( $text, 'wp-att-' ) && preg_match_all( '/wp-att-(\d+)/', $text, $m ) ) {
		$ids = array_merge( $ids, $m[1] );
	}

	// شورت‌کدها: [gallery ids="1,2,3"] ، [vc_single_image image="12"] ، ...
	$sc = '/\b(?:ids|include|image|images|img|img_id|image_id|bg_image|background_image|attachment|attachment_id|gallery|slides|logo|media|photo|picture|thumb|thumbnail|poster|video|mp3|mp4)\s*=\s*' . $q . '(\d+(?:\s*,\s*\d+)*)' . $end . '/i';
	if ( preg_match_all( $sc, $text, $m ) ) {
		foreach ( $m[1] as $list ) {
			if ( preg_match_all( '/\d+/', $list, $mm ) ) { $ids = array_merge( $ids, $mm[0] ); }
		}
	}

	// JSON: "id":123 ، "image":{"id":12,...} ، "ids":[1,2,3] ، "imageId":"55"
	$js = '/' . $q . '(?:' . $k . ')' . $q . '\s*:\s*\[?\s*' . $q . '(\d+(?:' . $q . '\s*,\s*' . $q . '\d+)*)' . $end . '/i';
	if ( preg_match_all( $js, $text, $m ) ) {
		foreach ( $m[1] as $list ) {
			if ( preg_match_all( '/\d+/', $list, $mm ) ) { $ids = array_merge( $ids, $mm[0] ); }
		}
	}

	// آرایه‌ی سریالایز PHP: s:2:"id";i:123;  یا  s:2:"id";s:3:"123";
	if ( false !== strpos( $text, 's:' ) ) {
		$se = '/"(?:' . $k . ')";(?:i:(\d+);|s:\d+:"(\d+)")/i';
		if ( preg_match_all( $se, $text, $m ) ) {
			foreach ( $m[1] as $v ) { if ( '' !== $v ) { $ids[] = $v; } }
			foreach ( $m[2] as $v ) { if ( '' !== $v ) { $ids[] = $v; } }
		}
	}

	// نام فایل‌ها (با پسوندهای رایج). \/ داخل JSON هم درست هندل می‌شود.
	$pattern = '/[^\/\\\\"\'\s<>()\[\]{},;=:|]+\.(?:jpe?g|jfif|png|gif|webp|avif|svg|bmp|ico|tiff?|heic|mp4|m4v|mov|avi|mkv|webm|ogv|wmv|flv|3gp|mpe?g|mp3|wav|ogg|m4a|flac|aac|opus|pdf|docx?|xlsx?|pptx?|zip|rar|csv|txt)(?![a-z0-9])/i';
	if ( preg_match_all( $pattern, $text, $m2 ) ) {
		foreach ( $m2[0] as $n ) {
			// نام فایل‌های URL-encode شده (%D8%A7...)
			if ( false !== strpos( $n, '%' ) ) { $n = rawurldecode( $n ); }
			$names[] = $n;
		}
	}

	return array( $names, array_values( array_unique( $ids ) ) );
}

/** آیا کلید متا/آپشن احتمالاً شناسه‌ی رسانه نگه می‌دارد؟ */
function gv_mlm_is_media_key( $key, $acf = array() ) {
	$key = (string) $key;
	if ( isset( $acf[ $key ] ) ) { return true; }
	if ( preg_match( '/_\d+_(.+)$/', $key, $m ) && isset( $acf[ $m[1] ] ) ) { return true; } // زیرفیلد ریپیتر ACF
	if ( 0 === strpos( $key, 'options_' ) && isset( $acf[ substr( $key, 8 ) ] ) ) { return true; }
	if ( '_product_image_gallery' === $key ) { return true; }
	return (bool) preg_match( '/(image|img|photo|picture|gallery|thumb|logo|icon|banner|background|bg_|_bg|cover|poster|slide|media|attachment|avatar|hero|favicon|video|audio)/i', $key );
}

/** همه‌ی مقدارهای عددیِ داخل یک آرایه‌ی سریالایز یا JSON */
function gv_mlm_numeric_leaves( $value ) {
	$out  = array();
	$data = null;
	$value = (string) $value;

	if ( is_serialized( $value ) ) {
		$data = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore
	} elseif ( isset( $value[0] ) && ( '[' === $value[0] || '{' === $value[0] ) ) {
		$data = json_decode( $value, true );
	}

	if ( is_array( $data ) ) {
		array_walk_recursive( $data, function ( $v ) use ( &$out ) {
			if ( ( is_int( $v ) || ( is_string( $v ) && ctype_digit( $v ) ) ) && (int) $v > 0 ) {
				$out[] = (int) $v;
			}
		} );
	}
	return $out;
}

/* ==========================================================================
   ۳) موتور اسکن تکه‌تکه (Batch Scan)
   ========================================================================== */

function gv_mlm_init_state() {
	global $wpdb;

	$total_att = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );

	$total_posts = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts}
		 WHERE post_type NOT IN ('revision','attachment','nav_menu_item','customize_changeset','oembed_cache')
		 AND post_status NOT IN ('trash','auto-draft','inherit')"
	);

	$max_meta = (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->postmeta}" );
	$max_term = (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->termmeta}" );
	$max_opt  = (int) $wpdb->get_var( "SELECT MAX(option_id) FROM {$wpdb->options}" );

	// نام فیلدهای ACF از نوع تصویر/گالری/فایل
	$acf  = array();
	$rows = $wpdb->get_results( "SELECT post_excerpt, post_content FROM {$wpdb->posts} WHERE post_type = 'acf-field' LIMIT 5000" );
	foreach ( (array) $rows as $r ) {
		$c = maybe_unserialize( $r->post_content );
		if ( is_array( $c ) && ! empty( $c['type'] ) && in_array( $c['type'], array( 'image', 'gallery', 'file' ), true ) && '' !== $r->post_excerpt ) {
			$acf[ $r->post_excerpt ] = 1;
		}
	}

	return array(
		'stage'       => 'attachments',
		'last_att'    => 0,
		'last_post'   => 0,
		'last_meta'   => 0,
		'last_term'   => 0,
		'last_opt'    => 0,
		'tbl_list'    => array(),
		'tbl_idx'     => 0,
		'tbl_off'     => 0,
		'done_att'    => 0,
		'done_posts'  => 0,
		'total_att'   => $total_att,
		'total_posts' => $total_posts,
		'max_meta'    => $max_meta,
		'max_term'    => $max_term,
		'max_opt'     => $max_opt,
		'acf'         => $acf,
		'items'       => array(), // id => اطلاعات پایه فایل
		'map'         => array(), // نام نرمال‌شده => آرایه‌ای از idها
		'usage'       => array(), // id => array('posts'=>array(post_id=>code), 'other'=>array(key=>info))
		'titles'      => array(), // post_id => عنوان
		'started_at'  => time(),
	);
}

/**
 * ثبت یک محل استفاده.
 * type = post  → $key = شناسه‌ی نوشته ، $extra = کد برچسب
 * type = other → $key = کلید یکتا ، $extra = array('l'=>برچسب,'t'=>متن,'u'=>لینک)
 */
function gv_mlm_add_usage( &$state, $att_id, $type, $key, $extra = '' ) {
	$att_id = (int) $att_id;
	if ( ! isset( $state['items'][ $att_id ] ) ) { return; }

	if ( ! isset( $state['usage'][ $att_id ] ) ) {
		$state['usage'][ $att_id ] = array( 'posts' => array(), 'other' => array() );
	}

	$current = count( $state['usage'][ $att_id ]['posts'] ) + count( $state['usage'][ $att_id ]['other'] );
	if ( $current >= GV_MLM_MAX_USAGE ) { return; }

	if ( 'post' === $type ) {
		$key = (int) $key;
		if ( ! isset( $state['usage'][ $att_id ]['posts'][ $key ] ) ) {
			$state['usage'][ $att_id ]['posts'][ $key ] = $extra;
		}
	} else {
		if ( ! isset( $state['usage'][ $att_id ]['other'][ $key ] ) ) {
			$state['usage'][ $att_id ]['other'][ $key ] = is_array( $extra ) ? $extra : array( 'l' => (string) $key, 't' => '', 'u' => admin_url() );
		}
	}
}

/** چند شناسه را یکجا به‌عنوان «استفاده‌شده» ثبت می‌کند */
function gv_mlm_use_ids( &$state, $ids, $type, $key, $extra = '' ) {
	foreach ( (array) $ids as $id ) {
		gv_mlm_add_usage( $state, (int) $id, $type, $key, $extra );
	}
}

/**
 * نام‌ها و idهای استخراج‌شده از یک متن را به فایل‌های کتابخانه رسانه وصل می‌کند.
 */
function gv_mlm_match_refs( &$state, $names, $ids, $type, $key, $extra = '' ) {
	$hit = array();

	foreach ( $names as $n ) {
		$norm = gv_mlm_norm_name( $n );
		if ( isset( $state['map'][ $norm ] ) ) {
			foreach ( $state['map'][ $norm ] as $att_id ) { $hit[ $att_id ] = true; }
		}
	}
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( isset( $state['items'][ $id ] ) ) { $hit[ $id ] = true; }
	}

	foreach ( array_keys( $hit ) as $att_id ) {
		gv_mlm_add_usage( $state, $att_id, $type, $key, $extra );
	}
}

/**
 * جدول‌های اختصاصی اسلایدر/گالری/بنر/سازنده که ممکن است تصویر نگه دارند.
 */
function gv_mlm_candidate_tables() {
	global $wpdb;

	$core = array( 'posts', 'postmeta', 'options', 'users', 'usermeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'comments', 'commentmeta', 'links', 'blogs', 'site', 'sitemeta', 'blog_versions', 'registration_log', 'signups' );
	$like = $wpdb->esc_like( $wpdb->prefix ) . '%';
	$all  = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	$out  = array();

	foreach ( (array) $all as $table ) {
		$short = substr( $table, strlen( $wpdb->prefix ) );
		if ( in_array( $short, $core, true ) ) { continue; }
		if ( ! preg_match( '/(slide|banner|galler|layer|portfolio|carousel|ngg_|popup|builder|photo|image|img|media|hero|logo|nextend|smartslider|revslider|metaslider|royal|huge_it|ultimate|brizy|visual)/i', $short ) ) { continue; }

		$cols = $wpdb->get_results( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '`', ARRAY_A );
		if ( empty( $cols ) ) { continue; }

		$text = array();
		$idc  = array();
		$pk   = '';
		foreach ( $cols as $c ) {
			$field = $c['Field'];
			$type  = strtolower( $c['Type'] );
			if ( 'PRI' === $c['Key'] && '' === $pk ) { $pk = $field; }
			if ( preg_match( '/char|text|json/', $type ) ) {
				$text[] = $field;
			} elseif ( preg_match( '/int/', $type ) && preg_match( '/(image|img|attachment|media|thumb|photo|picture|logo|bg|background|cover|poster)/i', $field ) ) {
				$idc[] = $field;
			}
		}
		if ( ! $text && ! $idc ) { continue; }
		if ( '' === $pk ) { $pk = $cols[0]['Field']; }

		$out[] = array( 'table' => $table, 'short' => $short, 'text' => $text, 'idc' => $idc, 'pk' => $pk );
	}

	return $out;
}

function gv_mlm_table_admin_url( $short ) {
	if ( 0 === strpos( $short, 'revslider' ) ) { return admin_url( 'admin.php?page=revslider' ); }
	if ( 0 === strpos( $short, 'layerslider' ) ) { return admin_url( 'admin.php?page=layerslider' ); }
	if ( 0 === strpos( $short, 'nextend2_smartslider3' ) ) { return admin_url( 'admin.php?page=nextend-smart-slider3' ); }
	return admin_url();
}

/**
 * یک تکه از اسکن را جلو می‌برد.
 */
function gv_mlm_scan_batch( $state ) {
	global $wpdb;

	switch ( $state['stage'] ) {

		/* --- مرحله ۱: خواندن کتابخانه رسانه --- */
		case 'attachments':
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_title, post_date, post_mime_type, post_parent
				 FROM {$wpdb->posts}
				 WHERE post_type = 'attachment' AND ID > %d
				 ORDER BY ID ASC LIMIT %d",
				(int) $state['last_att'],
				GV_MLM_BATCH_ATT
			) );

			if ( empty( $rows ) ) {
				$state['stage'] = 'posts';
				break;
			}

			$ids = wp_list_pluck( $rows, 'ID' );
			update_meta_cache( 'post', $ids ); // همه متاها با یک کوئری

			foreach ( $rows as $p ) {
				$id    = (int) $p->ID;
				$group = gv_mlm_mime_group( $p->post_mime_type );

				$attached = get_post_meta( $id, '_wp_attached_file', true );
				$filename = $attached ? basename( $attached ) : '';

				// حجم فایل: اول از متادیتا (بدون دسترسی به دیسک)، بعد از خود فایل
				$meta = wp_get_attachment_metadata( $id );
				$size = 0;
				if ( is_array( $meta ) && ! empty( $meta['filesize'] ) ) {
					$size   = (int) $meta['filesize'];
					$exists = true;
				} else {
					$path   = get_attached_file( $id );
					$exists = ( $path && file_exists( $path ) );
					$size   = $exists ? (int) filesize( $path ) : 0;
				}

				$url = wp_get_attachment_url( $id );

				$thumb = '';
				if ( 'image' === $group && $url ) {
					if ( is_array( $meta ) && ! empty( $meta['sizes']['thumbnail']['file'] ) ) {
						$thumb = trailingslashit( dirname( $url ) ) . $meta['sizes']['thumbnail']['file'];
					} elseif ( $size > 0 && $size < 300000 ) {
						$thumb = $url;
					}
				}

				$state['items'][ $id ] = array(
					'id'     => $id,
					'title'  => $p->post_title ? $p->post_title : $filename,
					'file'   => $filename,
					'group'  => $group,
					'size'   => $size,
					'exists' => $exists ? 1 : 0,
					'ts'     => (int) mysql2date( 'U', $p->post_date ),
					'date'   => mysql2date( 'Y/m/d', $p->post_date ),
					'url'    => $url,
					'thumb'  => $thumb,
					'edit'   => admin_url( 'post.php?post=' . $id . '&action=edit' ),
				);

				if ( $filename ) {
					$norm = gv_mlm_norm_name( $filename );
					if ( ! isset( $state['map'][ $norm ] ) ) { $state['map'][ $norm ] = array(); }
					$state['map'][ $norm ][] = $id;
				}

				// پیوست مستقیم به یک نوشته
				if ( $p->post_parent ) {
					gv_mlm_add_usage( $state, $id, 'post', (int) $p->post_parent, 'parent' );
				}

				$state['last_att'] = $id;
				$state['done_att']++;
			}
			break;

		/* --- مرحله ۲: محتوای نوشته‌ها/صفحات/محصولات/قالب‌های بلوکی --- */
		case 'posts':
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_title, post_content, post_excerpt
				 FROM {$wpdb->posts}
				 WHERE ID > %d
				 AND post_type NOT IN ('revision','attachment','nav_menu_item','customize_changeset','oembed_cache')
				 AND post_status NOT IN ('trash','auto-draft','inherit')
				 ORDER BY ID ASC LIMIT %d",
				(int) $state['last_post'],
				GV_MLM_BATCH_POSTS
			) );

			if ( empty( $rows ) ) {
				$state['stage'] = 'meta';
				break;
			}

			foreach ( $rows as $row ) {
				$pid = (int) $row->ID;
				list( $names, $ids ) = gv_mlm_extract_refs( $row->post_content . ' ' . $row->post_excerpt );
				if ( $names || $ids ) {
					gv_mlm_match_refs( $state, $names, $ids, 'post', $pid, 'content' );
					$state['titles'][ $pid ] = $row->post_title ? $row->post_title : '(بدون عنوان)';
				}
				$state['last_post'] = $pid;
				$state['done_posts']++;
			}
			break;

		/* --- مرحله ۳: فیلدهای سفارشی، سازنده صفحه، تصویر شاخص، ACF، منوها و ... --- */
		case 'meta':
			// فقط متای نوشته‌های واقعی (نه revision، نه سطل زباله). آیتم‌های منو هم شامل می‌شوند.
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT m.meta_id, m.post_id, m.meta_key, m.meta_value
				 FROM {$wpdb->postmeta} m
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				 WHERE m.meta_id > %d
				 AND p.post_type NOT IN ('revision','attachment','customize_changeset','oembed_cache')
				 AND p.post_status NOT IN ('trash','auto-draft','inherit')
				 ORDER BY m.meta_id ASC LIMIT %d",
				(int) $state['last_meta'],
				GV_MLM_BATCH_META
			) );

			if ( empty( $rows ) ) {
				$state['stage'] = 'termmeta';
				break;
			}

			foreach ( $rows as $row ) {
				$state['last_meta'] = (int) $row->meta_id;
				$key                = (string) $row->meta_key;
				$pid                = (int) $row->post_id;

				if ( '_thumbnail_id' === $key ) {
					gv_mlm_add_usage( $state, (int) $row->meta_value, 'post', $pid, 'featured' );
					continue;
				}

				if ( '_wp_attached_file' === $key || '_wp_attachment_metadata' === $key
					|| 0 === strpos( $key, '_edit_' ) || 0 === strpos( $key, '_oembed' ) || 0 === strpos( $key, '_wp_old_slug' ) ) {
					continue;
				}

				$value = (string) $row->meta_value;
				if ( '' === $value ) { continue; }

				// مقدار فقط عدد/لیست عدد است (گالری ووکامرس، فیلد تصویر ACF، ...)
				if ( preg_match( '/^\d+(?:\s*,\s*\d+)*$/', $value ) ) {
					if ( gv_mlm_is_media_key( $key, $state['acf'] ) && preg_match_all( '/\d+/', $value, $mm ) ) {
						gv_mlm_use_ids( $state, $mm[0], 'post', $pid, 'meta' );
					}
					continue;
				}

				// آرایه‌ی سریالایز/JSON از شناسه‌ها زیر یک کلید تصویری
				if ( gv_mlm_is_media_key( $key, $state['acf'] ) && strlen( $value ) < 200000 ) {
					$nums = gv_mlm_numeric_leaves( $value );
					if ( $nums ) { gv_mlm_use_ids( $state, $nums, 'post', $pid, 'meta' ); }
				}

				list( $names, $ids ) = gv_mlm_extract_refs( $value );
				if ( $names || $ids ) {
					gv_mlm_match_refs( $state, $names, $ids, 'post', $pid, 'meta' );
				}
			}
			break;

		/* --- مرحله ۴: متای دسته‌بندی‌ها/برچسب‌ها/ویژگی‌ها --- */
		case 'termmeta':
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_id, term_id, meta_key, meta_value
				 FROM {$wpdb->termmeta}
				 WHERE meta_id > %d
				 ORDER BY meta_id ASC LIMIT %d",
				(int) $state['last_term'],
				GV_MLM_BATCH_TERM
			) );

			if ( empty( $rows ) ) {
				$state['stage'] = 'options';
				break;
			}

			foreach ( $rows as $row ) {
				$state['last_term'] = (int) $row->meta_id;
				$value              = (string) $row->meta_value;
				if ( '' === $value ) { continue; }

				$ids   = array();
				$names = array();

				if ( preg_match( '/^\d+(?:\s*,\s*\d+)*$/', $value ) ) {
					if ( 'thumbnail_id' === $row->meta_key || gv_mlm_is_media_key( $row->meta_key, $state['acf'] ) ) {
						preg_match_all( '/\d+/', $value, $mm );
						$ids = $mm[0];
					}
				} else {
					if ( gv_mlm_is_media_key( $row->meta_key, $state['acf'] ) ) { $ids = gv_mlm_numeric_leaves( $value ); }
					list( $n2, $i2 ) = gv_mlm_extract_refs( $value );
					$names = $n2;
					$ids   = array_merge( $ids, $i2 );
				}

				if ( $names || $ids ) {
					$link = get_edit_term_link( (int) $row->term_id );
					gv_mlm_match_refs( $state, $names, $ids, 'other', 'term-' . (int) $row->term_id, array(
						'l' => 'تصویر دسته‌بندی / برچسب',
						't' => 'دسته‌بندی #' . (int) $row->term_id,
						'u' => $link ? $link : admin_url( 'edit-tags.php' ),
					) );
				}
			}
			break;

		/* --- مرحله ۵: تمام تنظیمات (ویجت، قالب، Redux، Kirki، ACF Options، ...) --- */
		case 'options':
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT option_id, option_name, option_value
				 FROM {$wpdb->options}
				 WHERE option_id > %d
				 AND option_name NOT LIKE %s
				 AND option_name NOT LIKE %s
				 AND option_name NOT LIKE %s
				 AND LENGTH(option_value) BETWEEN 1 AND 3000000
				 ORDER BY option_id ASC LIMIT %d",
				(int) $state['last_opt'],
				$wpdb->esc_like( '_transient_' ) . '%',
				$wpdb->esc_like( '_site_transient_' ) . '%',
				$wpdb->esc_like( 'gv_mlm_' ) . '%',
				GV_MLM_BATCH_OPT
			) );

			if ( empty( $rows ) ) {
				$state['stage']    = 'tables';
				$state['tbl_list'] = gv_mlm_candidate_tables();
				$state['tbl_idx']  = 0;
				$state['tbl_off']  = 0;
				break;
			}

			$skip = array( 'cron', 'rewrite_rules', 'active_plugins', 'recently_activated', 'wp_user_roles', 'sidebars_widgets', 'uninstall_plugins', 'fresh_site', 'siteurl', 'home', 'blogname', 'blogdescription' );

			foreach ( $rows as $o ) {
				$state['last_opt'] = (int) $o->option_id;
				$name              = (string) $o->option_name;
				$value             = (string) $o->option_value;

				if ( in_array( $name, $skip, true ) ) { continue; }

				if ( 0 === strpos( $name, 'widget_' ) ) {
					$info = array( 'l' => 'ویجت سایت', 't' => 'ویجت‌های سایت', 'u' => admin_url( 'widgets.php' ) );
					$ukey = 'widgets';
				} elseif ( 0 === strpos( $name, 'theme_mods_' ) ) {
					$info = array( 'l' => 'شخصی‌سازی قالب (Customizer)', 't' => 'تنظیمات ظاهری قالب', 'u' => admin_url( 'customize.php' ) );
					$ukey = 'customizer';
				} else {
					$info = array( 'l' => 'تنظیمات قالب/افزونه', 't' => $name, 'u' => admin_url( 'options-general.php' ) );
					$ukey = 'opt-' . $name;
				}

				// مقدار فقط عدد (site_icon، site_logo، ...)
				if ( preg_match( '/^\d+(?:\s*,\s*\d+)*$/', $value ) ) {
					if ( gv_mlm_is_media_key( $name, $state['acf'] ) ) {
						preg_match_all( '/\d+/', $value, $mm );
						gv_mlm_use_ids( $state, $mm[0], 'other', $ukey, $info );
					}
					continue;
				}

				if ( gv_mlm_is_media_key( $name, $state['acf'] ) && strlen( $value ) < 200000 ) {
					$nums = gv_mlm_numeric_leaves( $value );
					if ( $nums ) { gv_mlm_use_ids( $state, $nums, 'other', $ukey, $info ); }
				}

				list( $names, $ids ) = gv_mlm_extract_refs( $value );
				if ( $names || $ids ) {
					gv_mlm_match_refs( $state, $names, $ids, 'other', $ukey, $info );
				}
			}
			break;

		/* --- مرحله ۶: جدول‌های اختصاصی اسلایدرها (Revolution Slider و ...) --- */
		case 'tables':
			if ( $state['tbl_idx'] >= count( $state['tbl_list'] ) ) {
				$state['stage'] = 'finalize';
				break;
			}

			$t      = $state['tbl_list'][ $state['tbl_idx'] ];
			$cols   = array_merge( $t['text'], $t['idc'] );
			$select = '`' . implode( '`,`', array_map( 'esc_sql', $cols ) ) . '`';

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT {$select} FROM `" . esc_sql( $t['table'] ) . '` ORDER BY `' . esc_sql( $t['pk'] ) . '` ASC LIMIT %d OFFSET %d',
				GV_MLM_BATCH_TBL,
				(int) $state['tbl_off']
			), ARRAY_A );

			$info = array(
				'l' => 'اسلایدر / افزونه',
				't' => $t['short'],
				'u' => gv_mlm_table_admin_url( $t['short'] ),
			);
			$ukey = 'tbl-' . $t['short'];

			foreach ( (array) $rows as $r ) {
				foreach ( $t['idc'] as $c ) {
					if ( isset( $r[ $c ] ) && ctype_digit( (string) $r[ $c ] ) && (int) $r[ $c ] > 0 ) {
						gv_mlm_add_usage( $state, (int) $r[ $c ], 'other', $ukey, $info );
					}
				}
				foreach ( $t['text'] as $c ) {
					if ( empty( $r[ $c ] ) ) { continue; }
					$val = (string) $r[ $c ];
					if ( preg_match( '/^\d+$/', $val ) ) { continue; }
					list( $names, $ids ) = gv_mlm_extract_refs( $val );
					if ( $names || $ids ) {
						gv_mlm_match_refs( $state, $names, $ids, 'other', $ukey, $info );
					}
				}
			}

			if ( count( (array) $rows ) < GV_MLM_BATCH_TBL ) {
				$state['tbl_idx']++;
				$state['tbl_off'] = 0;
			} else {
				$state['tbl_off'] += GV_MLM_BATCH_TBL;
			}
			break;
	}

	return $state;
}

/** آمار کلی را از روی آرایه‌ی items دوباره حساب می‌کند */
function gv_mlm_recalc( $data ) {
	$total_size   = 0;
	$used_count   = 0;
	$unused_count = 0;
	$unused_size  = 0;
	$type_counts  = array( 'image' => 0, 'video' => 0, 'audio' => 0, 'document' => 0, 'other' => 0 );

	foreach ( $data['items'] as $it ) {
		$total_size += $it['size'];
		if ( isset( $type_counts[ $it['group'] ] ) ) { $type_counts[ $it['group'] ]++; }
		if ( ! empty( $it['used'] ) ) {
			$used_count++;
		} else {
			$unused_count++;
			$unused_size += $it['size'];
		}
	}

	$data['total_count']  = count( $data['items'] );
	$data['total_size']   = $total_size;
	$data['used_count']   = $used_count;
	$data['unused_count'] = $unused_count;
	$data['unused_size']  = $unused_size;
	$data['type_counts']  = $type_counts;
	return $data;
}

/**
 * ساخت خروجی نهایی و ذخیره در کش.
 */
function gv_mlm_finalize( $state ) {
	global $wpdb;

	$labels = array(
		'featured' => 'تصویر شاخص',
		'parent'   => 'پیوست به نوشته',
		'content'  => 'داخل محتوای نوشته',
		'meta'     => 'فیلد سفارشی / سازنده صفحه',
	);

	// عنوان و وضعیت نوشته‌هایی که هنوز نداریم را با چند کوئری گروهی می‌گیریم
	$need = array();
	foreach ( $state['usage'] as $u ) {
		foreach ( array_keys( $u['posts'] ) as $pid ) {
			if ( ! isset( $state['titles'][ $pid ] ) ) { $need[ $pid ] = true; }
		}
	}
	$need    = array_keys( $need );
	$invalid = array();
	$menu    = array();

	foreach ( array_chunk( $need, 300 ) as $chunk ) {
		$in    = implode( ',', array_map( 'intval', $chunk ) );
		$rows  = $wpdb->get_results( "SELECT ID, post_title, post_status, post_type FROM {$wpdb->posts} WHERE ID IN ($in)" );
		$found = array();
		foreach ( $rows as $r ) {
			$rid           = (int) $r->ID;
			$found[ $rid ] = true;
			if ( in_array( $r->post_status, array( 'trash', 'auto-draft' ), true ) || 'revision' === $r->post_type ) {
				$invalid[ $rid ] = true;
				continue;
			}
			if ( 'nav_menu_item' === $r->post_type ) {
				$menu[ $rid ] = true;
				continue;
			}
			$state['titles'][ $rid ] = $r->post_title ? $r->post_title : '(بدون عنوان)';
		}
		foreach ( $chunk as $pid ) {
			if ( empty( $found[ (int) $pid ] ) ) { $invalid[ (int) $pid ] = true; }
		}
	}

	$items = array();

	foreach ( $state['items'] as $id => $it ) {
		$usages    = array();
		$menu_done = false;

		if ( isset( $state['usage'][ $id ] ) ) {
			foreach ( $state['usage'][ $id ]['posts'] as $pid => $code ) {
				if ( isset( $invalid[ $pid ] ) ) { continue; }

				if ( isset( $menu[ $pid ] ) ) {
					if ( ! $menu_done ) {
						$usages[]  = array( 'l' => 'منوی سایت', 't' => 'منوها', 'u' => admin_url( 'nav-menus.php' ) );
						$menu_done = true;
					}
					continue;
				}

				$usages[] = array(
					'l' => isset( $labels[ $code ] ) ? $labels[ $code ] : 'محل نامشخص',
					't' => isset( $state['titles'][ $pid ] ) ? $state['titles'][ $pid ] : '#' . $pid,
					'u' => admin_url( 'post.php?post=' . (int) $pid . '&action=edit' ),
				);
			}
			foreach ( $state['usage'][ $id ]['other'] as $info ) {
				$usages[] = array(
					'l' => isset( $info['l'] ) ? $info['l'] : '',
					't' => isset( $info['t'] ) ? $info['t'] : '',
					'u' => isset( $info['u'] ) ? $info['u'] : admin_url(),
				);
			}
		}

		$it['usages'] = $usages;
		$it['used']   = empty( $usages ) ? 0 : 1;
		$items[]      = $it;
	}

	// مرتب‌سازی پیش‌فرض: جدیدترین
	usort( $items, function ( $a, $b ) { return $b['ts'] - $a['ts']; } );

	$data = gv_mlm_recalc( array(
		'scanned_at' => current_time( 'timestamp' ), // phpcs:ignore
		'items'      => $items,
	) );

	set_transient( GV_MLM_TRANSIENT, $data, DAY_IN_SECONDS );
	delete_transient( GV_MLM_STATE );

	return $data;
}

function gv_mlm_progress( $state ) {
	$weights = array(
		'attachments' => 10,
		'posts'       => 25,
		'meta'        => 35,
		'termmeta'    => 3,
		'options'     => 12,
		'tables'      => 15,
	);

	$frac = array(
		'attachments' => $state['total_att']   > 0 ? min( 1, $state['done_att']   / $state['total_att'] )   : 1,
		'posts'       => $state['total_posts'] > 0 ? min( 1, $state['done_posts'] / $state['total_posts'] ) : 1,
		'meta'        => $state['max_meta']    > 0 ? min( 1, $state['last_meta']  / $state['max_meta'] )    : 1,
		'termmeta'    => $state['max_term']    > 0 ? min( 1, $state['last_term']  / $state['max_term'] )    : 1,
		'options'     => $state['max_opt']     > 0 ? min( 1, $state['last_opt']   / $state['max_opt'] )     : 1,
		'tables'      => count( $state['tbl_list'] ) > 0 ? min( 1, $state['tbl_idx'] / count( $state['tbl_list'] ) ) : 1,
	);

	$p     = 0;
	$found = false;
	foreach ( $weights as $stage => $w ) {
		if ( $stage === $state['stage'] ) {
			$p    += $w * $frac[ $stage ];
			$found = true;
			break;
		}
		$p += $w;
	}
	if ( ! $found ) { $p = 100; }

	$stages = array(
		'attachments' => 'خواندن کتابخانه رسانه...',
		'posts'       => 'بررسی محتوای نوشته‌ها و صفحات...',
		'meta'        => 'بررسی سازنده صفحه، فیلدهای سفارشی و منوها...',
		'termmeta'    => 'بررسی تصویر دسته‌بندی‌ها...',
		'options'     => 'بررسی ویجت‌ها و تنظیمات قالب/افزونه‌ها...',
		'tables'      => 'بررسی اسلایدرها (Revolution Slider و ...)...',
		'finalize'    => 'جمع‌بندی نتایج...',
		'done'        => 'تمام شد',
	);

	return array(
		'percent' => (int) round( min( 100, $p ) ),
		'label'   => isset( $stages[ $state['stage'] ] ) ? $stages[ $state['stage'] ] : '',
	);
}

/* ==========================================================================
   ۴) اکشن AJAX اسکن (هر درخواست = یک تکه از اسکن)
   ========================================================================== */
add_action( 'wp_ajax_gv_mlm_scan_step', 'gv_mlm_ajax_scan_step' );
function gv_mlm_ajax_scan_step() {
	check_ajax_referer( GV_MLM_NONCE, 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'شما اجازه‌ی این کار را ندارید.' ) );
	}

	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 0 ); } // phpcs:ignore

	$restart = ! empty( $_POST['restart'] );
	$state   = $restart ? false : get_transient( GV_MLM_STATE );

	if ( ! is_array( $state ) || empty( $state['stage'] ) || ! isset( $state['tbl_list'] ) ) {
		$state = gv_mlm_init_state();
	}

	$start = microtime( true );
	while ( 'finalize' !== $state['stage'] && ( microtime( true ) - $start ) < GV_MLM_STEP_SECONDS ) {
		$state = gv_mlm_scan_batch( $state );
	}

	if ( 'finalize' === $state['stage'] ) {
		$data = gv_mlm_finalize( $state );
		wp_send_json_success( array(
			'done'    => true,
			'percent' => 100,
			'label'   => 'اسکن کامل شد',
			'count'   => $data['total_count'],
		) );
	}

	set_transient( GV_MLM_STATE, $state, HOUR_IN_SECONDS );

	$progress = gv_mlm_progress( $state );
	wp_send_json_success( array(
		'done'    => false,
		'percent' => $progress['percent'],
		'label'   => $progress['label'],
	) );
}

/* ==========================================================================
   ۵) سیستم بکاپ (قبل از حذف، بعد از حذف: بازگردانی یا پاک‌سازی)
   ========================================================================== */

/** شناسه‌ی دسته‌ی بکاپ را امن می‌کند (b + حروف/عدد) */
function gv_mlm_clean_batch_id( $raw ) {
	$b = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $raw ) );
	return preg_match( '/^b[a-z0-9]{5,23}$/', $b ) ? $b : '';
}

/** مسیر نسبی داخل uploads معتبر و بدون پرش به بیرون است؟ */
function gv_mlm_safe_rel( $rel ) {
	return is_string( $rel ) && '' !== $rel
		&& false === strpos( $rel, '..' )
		&& false === strpos( $rel, "\0" )
		&& '/' !== $rel[0];
}

/** پوشه‌ی اصلی بکاپ (داخل uploads، با نام تصادفی و محافظت‌شده) */
function gv_mlm_bk_root() {
	$up = wp_upload_dir( null, false );
	if ( ! empty( $up['error'] ) || empty( $up['basedir'] ) ) { return false; }

	$token = get_option( GV_MLM_BK_TOKEN );
	if ( ! $token ) {
		$token = strtolower( wp_generate_password( 14, false, false ) );
		update_option( GV_MLM_BK_TOKEN, $token, false );
	}

	$root = untrailingslashit( $up['basedir'] ) . '/gv-media-backup-' . $token;

	if ( ! is_dir( $root ) ) {
		if ( ! wp_mkdir_p( $root ) ) { return false; }
		@file_put_contents( $root . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		@file_put_contents( $root . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
	}

	return is_writable( $root ) ? $root : false;
}

/** حذف بازگشتی یک پوشه (فقط داخل پوشه‌ی بکاپ) */
function gv_mlm_rrmdir( $dir ) {
	if ( ! file_exists( $dir ) ) { return true; }

	$root = gv_mlm_bk_root();
	if ( ! $root ) { return false; }

	$real  = realpath( $dir );
	$rroot = realpath( $root );
	if ( ! $real || ! $rroot || 0 !== strpos( $real, $rroot . DIRECTORY_SEPARATOR ) ) { return false; }

	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $f ) {
		if ( $f->isDir() && ! $f->isLink() ) {
			@rmdir( $f->getPathname() ); // phpcs:ignore
		} else {
			@unlink( $f->getPathname() ); // phpcs:ignore
		}
	}
	return @rmdir( $real ); // phpcs:ignore
}

/** بروزرسانی فهرست کلی دسته‌ها از روی فایل‌های یک دسته */
function gv_mlm_bk_refresh_list( $batch, $items ) {
	$list = get_option( GV_MLM_BK_LIST, array() );
	if ( ! is_array( $list ) ) { $list = array(); }

	if ( empty( $items ) ) {
		unset( $list[ $batch ] );
		delete_option( GV_MLM_BK_PREFIX . $batch );
	} else {
		$size = 0;
		foreach ( $items as $i ) { $size += (int) $i['bsize']; }
		$list[ $batch ] = array(
			'created' => isset( $list[ $batch ]['created'] ) ? $list[ $batch ]['created'] : time(),
			'count'   => count( $items ),
			'size'    => $size,
		);
	}
	update_option( GV_MLM_BK_LIST, $list, false );
}

function gv_mlm_bk_register( $batch, $id, $info ) {
	$items = get_option( GV_MLM_BK_PREFIX . $batch, array() );
	if ( ! is_array( $items ) ) { $items = array(); }
	$items[ (int) $id ] = $info;
	update_option( GV_MLM_BK_PREFIX . $batch, $items, false );
	gv_mlm_bk_refresh_list( $batch, $items );
}

/** حذف بکاپ یک فایل (بعد از بازگردانی موفق یا شکست حذف) */
function gv_mlm_bk_discard( $batch, $id ) {
	$root = gv_mlm_bk_root();
	if ( $root ) { gv_mlm_rrmdir( $root . '/' . $batch . '/' . (int) $id ); }

	$items = get_option( GV_MLM_BK_PREFIX . $batch, array() );
	if ( is_array( $items ) ) {
		unset( $items[ (int) $id ] );
		gv_mlm_bk_refresh_list( $batch, $items );
		if ( empty( $items ) && $root ) { gv_mlm_rrmdir( $root . '/' . $batch ); }
	}
}

/** پاک کردن دائمی یک دسته‌ی کامل بکاپ */
function gv_mlm_bk_purge_batch( $batch ) {
	$root = gv_mlm_bk_root();
	if ( $root ) { gv_mlm_rrmdir( $root . '/' . $batch ); }
	gv_mlm_bk_refresh_list( $batch, array() );
}

/** همه‌ی فایل‌های فیزیکی مربوط به یک پیوست (اصلی + سایزها + نسخه‌های webp/avif) */
function gv_mlm_attachment_rel_files( $id ) {
	$files = array();
	$main  = get_post_meta( $id, '_wp_attached_file', true );
	if ( ! $main ) { return $files; }

	$main  = ltrim( str_replace( '\\', '/', $main ), '/' );
	$dir   = ( false !== strpos( $main, '/' ) ) ? dirname( $main ) . '/' : '';
	$files[] = $main;

	$meta = wp_get_attachment_metadata( $id );
	if ( is_array( $meta ) ) {
		if ( ! empty( $meta['file'] ) ) { $files[] = ltrim( str_replace( '\\', '/', $meta['file'] ), '/' ); }
		if ( ! empty( $meta['original_image'] ) ) { $files[] = $dir . $meta['original_image']; }
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $s ) {
				if ( ! empty( $s['file'] ) ) { $files[] = $dir . $s['file']; }
			}
		}
	}

	$bk = get_post_meta( $id, '_wp_attachment_backup_sizes', true );
	if ( is_array( $bk ) ) {
		foreach ( $bk as $s ) {
			if ( ! empty( $s['file'] ) ) { $files[] = $dir . $s['file']; }
		}
	}

	$extra = array();
	foreach ( $files as $f ) {
		$extra[] = $f . '.webp';
		$extra[] = $f . '.avif';
		$extra[] = preg_replace( '/\.[^.\/]+$/', '.webp', $f );
		$extra[] = preg_replace( '/\.[^.\/]+$/', '.avif', $f );
	}

	$clean = array();
	foreach ( array_merge( $files, $extra ) as $f ) {
		$f = ltrim( str_replace( '\\', '/', (string) $f ), '/' );
		if ( gv_mlm_safe_rel( $f ) ) { $clean[ $f ] = true; }
	}
	return array_keys( $clean );
}

/**
 * از یک پیوست بکاپ کامل می‌گیرد: فایل‌ها + ردیف پست + متاها + دسته‌بندی‌ها + تصویر شاخص‌ها.
 * فقط اگر همه‌چیز با موفقیت ذخیره شد true برمی‌گرداند (وگرنه حذفی انجام نمی‌شود).
 */
function gv_mlm_backup_attachment( $id, $batch, $cache_item ) {
	global $wpdb;

	$id   = (int) $id;
	$root = gv_mlm_bk_root();
	if ( ! $root ) { return false; }

	$up   = wp_upload_dir( null, false );
	$base = trailingslashit( $up['basedir'] );

	$item_dir = $root . '/' . $batch . '/' . $id;
	if ( ! wp_mkdir_p( $item_dir ) ) { return false; }

	$post_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A );
	if ( ! $post_row ) { gv_mlm_rrmdir( $item_dir ); return false; }

	$metas = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $id ), ARRAY_A );

	// نوشته‌هایی که این فایل را «تصویر شاخص» دارند (وردپرس موقع حذف این ارتباط را پاک می‌کند)
	$thumb_posts = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s",
		(string) $id
	) );

	$terms = array();
	foreach ( get_object_taxonomies( 'attachment' ) as $tax ) {
		$t = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $t ) && $t ) { $terms[ $tax ] = array_map( 'intval', $t ); }
	}

	$copied = array();
	$bsize  = 0;
	foreach ( gv_mlm_attachment_rel_files( $id ) as $rel ) {
		$src = $base . $rel;
		if ( ! is_file( $src ) ) { continue; }

		$dst = $item_dir . '/files/' . $rel;
		if ( ! wp_mkdir_p( dirname( $dst ) ) || ! @copy( $src, $dst ) || filesize( $dst ) !== filesize( $src ) ) { // phpcs:ignore
			gv_mlm_rrmdir( $item_dir );
			return false;
		}
		$copied[] = $rel;
		$bsize   += (int) filesize( $dst );
	}

	$payload = array(
		'post'        => $post_row,
		'metas'       => $metas,
		'thumb_posts' => array_map( 'intval', $thumb_posts ),
		'terms'       => $terms,
		'files'       => $copied,
		'cache_item'  => $cache_item,
		'created'     => time(),
	);

	$written = @file_put_contents( $item_dir . '/data.dat', base64_encode( serialize( $payload ) ) ); // phpcs:ignore
	if ( ! $written ) {
		gv_mlm_rrmdir( $item_dir );
		return false;
	}

	$title = $post_row['post_title'] ? $post_row['post_title'] : basename( (string) get_post_meta( $id, '_wp_attached_file', true ) );

	gv_mlm_bk_register( $batch, $id, array(
		'id'    => $id,
		'title' => $title,
		'file'  => basename( (string) get_post_meta( $id, '_wp_attached_file', true ) ),
		'group' => gv_mlm_mime_group( $post_row['post_mime_type'] ),
		'size'  => is_array( $cache_item ) && isset( $cache_item['size'] ) ? (int) $cache_item['size'] : $bsize,
		'bsize' => $bsize,
	) );

	return true;
}

/** برگرداندن یک فایل از بکاپ (با همان شناسه‌ی قبلی) */
function gv_mlm_restore_attachment( $batch, $id ) {
	global $wpdb;

	$id   = (int) $id;
	$root = gv_mlm_bk_root();
	if ( ! $root ) { return new WP_Error( 'no_root', 'پوشه‌ی بکاپ در دسترس نیست.' ); }

	$item_dir = $root . '/' . $batch . '/' . $id;
	$file     = $item_dir . '/data.dat';
	if ( ! is_file( $file ) ) { return new WP_Error( 'no_data', 'فایل بکاپ پیدا نشد.' ); }

	$raw     = @file_get_contents( $file ); // phpcs:ignore
	$payload = $raw ? @unserialize( base64_decode( $raw ), array( 'allowed_classes' => false ) ) : false; // phpcs:ignore
	if ( ! is_array( $payload ) || empty( $payload['post'] ) ) {
		return new WP_Error( 'bad_data', 'اطلاعات بکاپ خراب است.' );
	}

	if ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $id ) ) ) {
		return new WP_Error( 'exists', 'این شناسه قبلاً در کتابخانه وجود دارد.' );
	}

	$up   = wp_upload_dir( null, false );
	$base = trailingslashit( $up['basedir'] );

	// ۱) فایل‌ها
	foreach ( (array) $payload['files'] as $rel ) {
		if ( ! gv_mlm_safe_rel( $rel ) ) { return new WP_Error( 'bad_path', 'مسیر فایل نامعتبر است.' ); }
		$src = $item_dir . '/files/' . $rel;
		$dst = $base . $rel;
		if ( ! is_file( $src ) ) { return new WP_Error( 'no_file', 'فایل ' . $rel . ' در بکاپ نیست.' ); }
		if ( is_file( $dst ) ) { continue; }
		if ( ! wp_mkdir_p( dirname( $dst ) ) || ! @copy( $src, $dst ) ) { // phpcs:ignore
			return new WP_Error( 'copy_fail', 'کپی فایل ' . $rel . ' انجام نشد.' );
		}
	}

	// ۲) ردیف پست با همان ID
	if ( false === $wpdb->insert( $wpdb->posts, $payload['post'] ) ) {
		return new WP_Error( 'db_fail', 'ثبت اطلاعات در دیتابیس ناموفق بود.' );
	}

	// ۳) متاها
	foreach ( (array) $payload['metas'] as $m ) {
		$wpdb->insert( $wpdb->postmeta, array(
			'post_id'    => $id,
			'meta_key'   => $m['meta_key'],
			'meta_value' => $m['meta_value'],
		) );
	}

	// ۴) تصویر شاخص نوشته‌ها
	foreach ( (array) $payload['thumb_posts'] as $pid ) {
		if ( get_post_status( $pid ) && ! get_post_meta( $pid, '_thumbnail_id', true ) ) {
			update_post_meta( $pid, '_thumbnail_id', $id );
		}
	}

	// ۵) دسته‌بندی‌های رسانه
	foreach ( (array) $payload['terms'] as $tax => $term_ids ) {
		if ( taxonomy_exists( $tax ) ) { wp_set_object_terms( $id, array_map( 'intval', $term_ids ), $tax ); }
	}

	clean_post_cache( $id );
	wp_cache_delete( $id, 'post_meta' );

	// ۶) برگرداندن به لیست کش اسکن
	if ( ! empty( $payload['cache_item'] ) && is_array( $payload['cache_item'] ) ) {
		gv_mlm_add_to_cache( $payload['cache_item'] );
	}

	// ۷) پاک کردن بکاپ همین فایل
	gv_mlm_bk_discard( $batch, $id );

	return true;
}

/* ==========================================================================
   ۶) کش اسکن: حذف/افزودن فایل بدون نیاز به اسکن مجدد
   ========================================================================== */
function gv_mlm_remove_from_cache( $deleted_ids ) {
	$data = get_transient( GV_MLM_TRANSIENT );
	if ( ! is_array( $data ) || ! isset( $data['items'] ) ) { return null; }

	$drop  = array_flip( array_map( 'intval', $deleted_ids ) );
	$items = array();
	foreach ( $data['items'] as $it ) {
		if ( isset( $drop[ (int) $it['id'] ] ) ) { continue; }
		$items[] = $it;
	}

	$data['items'] = $items;
	$data          = gv_mlm_recalc( $data );

	set_transient( GV_MLM_TRANSIENT, $data, DAY_IN_SECONDS );
	return $data;
}

function gv_mlm_add_to_cache( $item ) {
	$data = get_transient( GV_MLM_TRANSIENT );
	if ( ! is_array( $data ) || ! isset( $data['items'] ) ) { return; }

	foreach ( $data['items'] as $it ) {
		if ( (int) $it['id'] === (int) $item['id'] ) { return; }
	}

	$data['items'][] = $item;
	usort( $data['items'], function ( $a, $b ) { return $b['ts'] - $a['ts']; } );
	$data = gv_mlm_recalc( $data );

	set_transient( GV_MLM_TRANSIENT, $data, DAY_IN_SECONDS );
}

/* ==========================================================================
   ۷) AJAX: حذف (با بکاپ)، بازگردانی، پاک کردن بکاپ
   ========================================================================== */
add_action( 'wp_ajax_gv_mlm_delete', 'gv_mlm_ajax_delete' );
function gv_mlm_ajax_delete() {
	check_ajax_referer( GV_MLM_NONCE, 'nonce' );

	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'delete_posts' ) ) {
		wp_send_json_error( array( 'message' => 'شما اجازه‌ی حذف فایل را ندارید.' ) );
	}

	$raw = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $raw ) ) ) );
	$ids = array_slice( $ids, 0, 100 ); // سقف امن برای هر درخواست

	if ( empty( $ids ) ) {
		wp_send_json_error( array( 'message' => 'هیچ فایل معتبری برای حذف انتخاب نشده است.' ) );
	}

	if ( ! gv_mlm_bk_root() ) {
		wp_send_json_error( array( 'message' => 'پوشه‌ی بکاپ ساخته نشد (دسترسی نوشتن در uploads را بررسی کنید). برای امنیت، هیچ فایلی حذف نشد.' ) );
	}

	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 0 ); } // phpcs:ignore

	$batch = gv_mlm_clean_batch_id( isset( $_POST['batch'] ) ? wp_unslash( $_POST['batch'] ) : '' ); // phpcs:ignore
	if ( '' === $batch ) { $batch = 'b' . strtolower( wp_generate_password( 10, false, false ) ); }

	// آیتم‌های کش (برای برگرداندن بعد از ریستور)
	$cache_map = array();
	$cache     = get_transient( GV_MLM_TRANSIENT );
	if ( is_array( $cache ) && ! empty( $cache['items'] ) ) {
		foreach ( $cache['items'] as $it ) { $cache_map[ (int) $it['id'] ] = $it; }
	}

	$deleted = array();
	$failed  = array();

	foreach ( $ids as $id ) {
		$post = get_post( $id );

		if ( ! $post || 'attachment' !== $post->post_type || ! current_user_can( 'delete_post', $id ) ) {
			$failed[] = $id;
			continue;
		}

		// اول بکاپ؛ اگر بکاپ کامل نشد، هیچ‌چیز حذف نمی‌شود
		if ( ! gv_mlm_backup_attachment( $id, $batch, isset( $cache_map[ $id ] ) ? $cache_map[ $id ] : null ) ) {
			$failed[] = $id;
			continue;
		}

		$result = wp_delete_attachment( $id, true );

		if ( $result ) {
			$deleted[] = $id;
		} else {
			gv_mlm_bk_discard( $batch, $id ); // حذف نشد → بکاپ بی‌مصرف است
			$failed[] = $id;
		}
	}

	$data  = gv_mlm_remove_from_cache( $deleted );
	$stats = null;
	if ( is_array( $data ) ) {
		$stats = array(
			'total_count'  => (int) $data['total_count'],
			'unused_count' => (int) $data['unused_count'],
		);
	}

	wp_send_json_success( array(
		'batch'   => $batch,
		'deleted' => $deleted,
		'failed'  => $failed,
		'stats'   => $stats,
	) );
}

add_action( 'wp_ajax_gv_mlm_backup_restore', 'gv_mlm_ajax_backup_restore' );
function gv_mlm_ajax_backup_restore() {
	check_ajax_referer( GV_MLM_NONCE, 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'شما اجازه‌ی این کار را ندارید.' ) );
	}

	$batch = gv_mlm_clean_batch_id( isset( $_POST['batch'] ) ? wp_unslash( $_POST['batch'] ) : '' ); // phpcs:ignore
	if ( '' === $batch ) { wp_send_json_error( array( 'message' => 'شناسه‌ی بکاپ نامعتبر است.' ) ); }

	$raw = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore
	$ids = array_slice( array_values( array_unique( array_filter( array_map( 'intval', $raw ) ) ) ), 0, 30 );
	if ( empty( $ids ) ) { wp_send_json_error( array( 'message' => 'فایلی انتخاب نشده است.' ) ); }

	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 0 ); } // phpcs:ignore

	$items    = get_option( GV_MLM_BK_PREFIX . $batch, array() );
	$restored = array();
	$failed   = array();
	$errors   = array();

	foreach ( $ids as $id ) {
		if ( ! is_array( $items ) || ! isset( $items[ $id ] ) ) {
			$failed[] = $id;
			$errors[] = '#' . $id . ': در بکاپ پیدا نشد';
			continue;
		}
		$r = gv_mlm_restore_attachment( $batch, $id );
		if ( is_wp_error( $r ) ) {
			$failed[] = $id;
			$errors[] = '#' . $id . ': ' . $r->get_error_message();
		} else {
			$restored[] = $id;
		}
	}

	wp_send_json_success( array( 'restored' => $restored, 'failed' => $failed, 'errors' => $errors ) );
}

add_action( 'wp_ajax_gv_mlm_backup_purge', 'gv_mlm_ajax_backup_purge' );
function gv_mlm_ajax_backup_purge() {
	check_ajax_referer( GV_MLM_NONCE, 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'شما اجازه‌ی این کار را ندارید.' ) );
	}

	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 0 ); } // phpcs:ignore

	$raw = isset( $_POST['batch'] ) ? wp_unslash( $_POST['batch'] ) : ''; // phpcs:ignore

	if ( 'all' === $raw ) {
		$list = get_option( GV_MLM_BK_LIST, array() );
		foreach ( array_keys( (array) $list ) as $b ) {
			$b = gv_mlm_clean_batch_id( $b );
			if ( $b ) { gv_mlm_bk_purge_batch( $b ); }
		}
	} else {
		$batch = gv_mlm_clean_batch_id( $raw );
		if ( '' === $batch ) { wp_send_json_error( array( 'message' => 'شناسه‌ی بکاپ نامعتبر است.' ) ); }
		gv_mlm_bk_purge_batch( $batch );
	}

	wp_send_json_success( array( 'ok' => true ) );
}

/* ==========================================================================
   ۸) پنل بکاپ‌ها در صفحه‌ی مدیریت
   ========================================================================== */
function gv_mlm_render_backups_panel() {
	$list = get_option( GV_MLM_BK_LIST, array() );
	if ( ! is_array( $list ) || empty( $list ) ) { return; }

	uasort( $list, function ( $a, $b ) { return $b['created'] - $a['created']; } );

	$total_size = 0;
	$total_cnt  = 0;
	foreach ( $list as $b ) {
		$total_size += (int) $b['size'];
		$total_cnt  += (int) $b['count'];
	}
	?>
	<div class="gvmlm-bk" id="gv-mlm-backups">
		<div class="gvmlm-bk-head">
			<div>
				<h2>🛟 بکاپ فایل‌های حذف‌شده</h2>
				<p>این فایل‌ها از کتابخانه حذف شده‌اند ولی هنوز یک نسخه‌ی کامل از آن‌ها (با همان شناسه‌ی قبلی) نگه‌داری می‌شود و <b>فضای هاست را اشغال می‌کنند</b>.
				اگر سایت را بررسی کردید و همه‌چیز درست بود، بکاپ را پاک کنید تا فضا واقعاً آزاد شود. اگر چیزی خراب شد، «بازگردانی» را بزنید.</p>
			</div>
			<button type="button" class="gvmlm-btn gvmlm-btn-danger" data-bk="purge-all">🧹 پاک کردن همه‌ی بکاپ‌ها (<?php echo esc_html( number_format_i18n( $total_cnt ) . ' فایل، ' . gv_mlm_format_size( $total_size ) ); ?>)</button>
		</div>

		<?php foreach ( $list as $batch_id => $b ) :
			$items = get_option( GV_MLM_BK_PREFIX . $batch_id, array() );
			if ( ! is_array( $items ) || empty( $items ) ) { continue; }
			$ids_json = wp_json_encode( array_map( 'intval', array_keys( $items ) ) );
			?>
			<div class="gvmlm-bk-batch">
				<div class="gvmlm-bk-batch-head">
					<div>
						<b><?php echo esc_html( wp_date( 'Y/m/d H:i', (int) $b['created'] ) ); ?></b>
						<span><?php echo esc_html( number_format_i18n( (int) $b['count'] ) . ' فایل — ' . gv_mlm_format_size( $b['size'] ) ); ?></span>
					</div>
					<div class="gvmlm-bk-btns">
						<button type="button" class="gvmlm-btn" data-bk="restore-batch" data-batch="<?php echo esc_attr( $batch_id ); ?>" data-ids="<?php echo esc_attr( $ids_json ); ?>">↩️ بازگردانی همه</button>
						<button type="button" class="gvmlm-btn gvmlm-btn-danger" data-bk="purge-batch" data-batch="<?php echo esc_attr( $batch_id ); ?>">🗑️ پاک کردن این بکاپ</button>
					</div>
				</div>
				<details>
					<summary>مشاهده‌ی فایل‌های این بکاپ</summary>
					<table class="gvmlm-bk-table">
						<?php $shown = 0; foreach ( $items as $it ) : if ( ++$shown > 150 ) { break; } ?>
							<tr>
								<td><?php echo esc_html( gv_mlm_type_icon( $it['group'] ) ); ?></td>
								<td><?php echo esc_html( $it['title'] ); ?><span class="gvmlm-fname"><?php echo esc_html( $it['file'] ); ?></span></td>
								<td><?php echo esc_html( gv_mlm_format_size( $it['bsize'] ) ); ?></td>
								<td><button type="button" class="gvmlm-row-del" style="color:#4338ca;" data-bk="restore-item" data-batch="<?php echo esc_attr( $batch_id ); ?>" data-id="<?php echo (int) $it['id']; ?>">بازگردانی</button></td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php if ( count( $items ) > 150 ) : ?>
						<p style="font-size:11.5px;color:#6b7280;padding:6px 12px;">فقط ۱۵۰ فایل اول نمایش داده شد؛ «بازگردانی همه» همه‌ی فایل‌ها را برمی‌گرداند.</p>
					<?php endif; ?>
				</details>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/* ==========================================================================
   ۹) صفحه‌ی مدیریت
   ========================================================================== */
function gv_mlm_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$data       = get_transient( GV_MLM_TRANSIENT );
	$has_data   = is_array( $data ) && isset( $data['items'] );
	$scanned_at = $has_data ? date_i18n( 'Y/m/d H:i', $data['scanned_at'] ) : '';

	$total_count  = $has_data ? (int) $data['total_count'] : 0;
	$used_count   = $has_data ? (int) $data['used_count'] : 0;
	$unused_count = $has_data ? (int) $data['unused_count'] : 0;
	$type_counts  = $has_data ? $data['type_counts'] : array();
	?>
	<div class="wrap gvmlm-wrap" dir="rtl" id="gv-mlm-wrap">
		<style>
			.gvmlm-wrap{max-width:1280px;}
			.gvmlm-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin:18px 0 22px;}
			.gvmlm-head h1{font-size:20px;margin:0;display:flex;align-items:center;gap:8px;}
			.gvmlm-scan-info{font-size:12.5px;color:#6b7280;margin-inline-end:10px;}
			.gvmlm-btn{display:inline-flex;align-items:center;gap:6px;background:#4338ca;color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer;transition:.15s;}
			.gvmlm-btn:hover{background:#3730a3;}
			.gvmlm-btn:disabled{opacity:.6;cursor:progress;}
			.gvmlm-progress{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:20px;}
			.gvmlm-bar{height:10px;background:#eef2ff;border-radius:20px;overflow:hidden;margin-top:10px;}
			.gvmlm-bar i{display:block;height:100%;width:0;background:#4338ca;transition:width .3s;}
			.gvmlm-progress-label{font-size:13px;color:#374151;}
			.gvmlm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;}
			.gvmlm-stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;}
			.gvmlm-stat b{display:block;font-size:20px;margin-bottom:4px;}
			.gvmlm-stat span{font-size:12.5px;color:#6b7280;}
			.gvmlm-stat.is-warn b{color:#b45309;}
			.gvmlm-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px 14px;margin-bottom:16px;}
			.gvmlm-search{flex:1;min-width:180px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;}
			.gvmlm-chip{border:1px solid #d1d5db;background:#f9fafb;border-radius:20px;padding:6px 14px;font-size:12.5px;cursor:pointer;color:#374151;}
			.gvmlm-chip.is-active{background:#4338ca;border-color:#4338ca;color:#fff;}
			.gvmlm-sort{padding:7px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:12.5px;}
			.gvmlm-table-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;}
			table.gvmlm-table{width:100%;border-collapse:collapse;font-size:13px;}
			table.gvmlm-table th{background:#f9fafb;text-align:right;padding:10px 12px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;}
			table.gvmlm-table td{padding:10px 12px;border-bottom:1px solid #f1f1f1;vertical-align:middle;}
			.gvmlm-thumb{width:44px;height:44px;border-radius:8px;object-fit:cover;background:#f3f4f6;display:inline-flex;align-items:center;justify-content:center;font-size:20px;}
			.gvmlm-fname{font-size:11.5px;color:#9ca3af;direction:ltr;text-align:right;display:block;}
			.gvmlm-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11.5px;background:#eef2ff;color:#4338ca;}
			.gvmlm-badge.used{background:#dcfce7;color:#15803d;}
			.gvmlm-badge.unused{background:#fee2e2;color:#b91c1c;}
			.gvmlm-usage-toggle{font-size:11.5px;color:#4338ca;cursor:pointer;display:inline;}
			.gvmlm-usage-list{margin:6px 0 0;padding-inline-start:16px;font-size:12px;color:#4b5563;}
			.gvmlm-usage-list li{margin-bottom:3px;}
			.gvmlm-empty{padding:40px;text-align:center;color:#6b7280;}
			.gvmlm-missing{color:#b91c1c;font-size:11px;display:block;}
			.gvmlm-actions a{margin-inline-end:8px;font-size:12px;}
			.gvmlm-pager{display:flex;align-items:center;justify-content:center;gap:10px;padding:14px;font-size:12.5px;color:#4b5563;}
			.gvmlm-pager button{border:1px solid #d1d5db;background:#fff;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:12.5px;}
			.gvmlm-pager button:disabled{opacity:.45;cursor:default;}
			.gvmlm-btn-danger{background:#b91c1c;}
			.gvmlm-btn-danger:hover{background:#991b1b;}
			.gvmlm-bulkbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:10px 14px;margin-bottom:14px;}
			.gvmlm-bulkbar b{font-size:13px;color:#9a3412;}
			.gvmlm-cb,.gvmlm-checkall{width:17px;height:17px;cursor:pointer;margin:0;}
			table.gvmlm-table td.gvmlm-cbcell,table.gvmlm-table th.gvmlm-cbcell{width:34px;text-align:center;padding-inline:8px;}
			.gvmlm-row-del{color:#b91c1c;cursor:pointer;background:none;border:none;padding:0;font-size:12px;text-decoration:underline;}
			.gvmlm-bk{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px 18px;margin-bottom:20px;}
			.gvmlm-bk-head{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;}
			.gvmlm-bk-head h2{margin:0 0 6px;font-size:16px;}
			.gvmlm-bk-head p{margin:0;font-size:12.5px;color:#374151;max-width:760px;line-height:1.9;}
			.gvmlm-bk-batch{background:#fff;border:1px solid #d1fae5;border-radius:10px;padding:12px 14px;margin-top:12px;}
			.gvmlm-bk-batch-head{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;}
			.gvmlm-bk-batch-head span{font-size:12.5px;color:#6b7280;margin-inline-start:10px;}
			.gvmlm-bk-btns{display:flex;gap:8px;flex-wrap:wrap;}
			.gvmlm-bk-batch details summary{cursor:pointer;font-size:12.5px;color:#4338ca;margin-top:10px;}
			table.gvmlm-bk-table{width:100%;border-collapse:collapse;font-size:12.5px;margin-top:8px;}
			table.gvmlm-bk-table td{padding:6px 10px;border-bottom:1px solid #f1f5f9;}
		</style>

		<div class="gvmlm-head">
			<h1>🖼️ مدیریت فایل‌های چندرسانه‌ای</h1>
			<div>
				<span class="gvmlm-scan-info" id="gv-mlm-scan-info"><?php echo $has_data ? 'آخرین اسکن: ' . esc_html( $scanned_at ) : 'هنوز اسکنی انجام نشده است'; ?></span>
				<button type="button" class="gvmlm-btn" id="gv-mlm-rescan-btn"><?php echo $has_data ? '🔄 اسکن مجدد کل سایت' : '▶️ شروع اسکن سایت'; ?></button>
			</div>
		</div>

		<div class="gvmlm-progress" id="gv-mlm-progress" hidden>
			<div class="gvmlm-progress-label" id="gv-mlm-progress-label">در حال آماده‌سازی اسکن...</div>
			<div class="gvmlm-bar"><i id="gv-mlm-bar"></i></div>
			<p style="font-size:11.5px;color:#9ca3af;margin:10px 0 0;">اسکن به‌صورت تکه‌تکه انجام می‌شود؛ لطفاً این صفحه را نبندید.</p>
		</div>

		<?php gv_mlm_render_backups_panel(); ?>

		<?php if ( ! $has_data ) : ?>
			<div class="gvmlm-table-card">
				<div class="gvmlm-empty">
					برای دیدن لیست فایل‌ها و مشخص شدن فایل‌های بدون استفاده، دکمه‌ی «شروع اسکن سایت» را بزنید.<br>
					<span style="font-size:12px;">نتیجه تا ۲۴ ساعت کش می‌شود و دفعات بعد صفحه بلافاصله باز خواهد شد.</span>
				</div>
			</div>
		<?php else : ?>

		<div class="gvmlm-stats">
			<div class="gvmlm-stat"><b id="gv-stat-total"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></b><span>کل فایل‌های رسانه</span></div>
			<div class="gvmlm-stat"><b id="gv-stat-size"><?php echo esc_html( gv_mlm_format_size( $data['total_size'] ) ); ?></b><span>حجم کل کتابخانه رسانه</span></div>
			<div class="gvmlm-stat"><b id="gv-stat-used" style="color:#15803d;"><?php echo esc_html( number_format_i18n( $used_count ) ); ?></b><span>فایل استفاده‌شده در سایت</span></div>
			<div class="gvmlm-stat is-warn"><b id="gv-stat-unused"><?php echo esc_html( number_format_i18n( $unused_count ) ); ?></b><span>فایل بدون استفاده در سایت</span></div>
			<div class="gvmlm-stat is-warn"><b id="gv-stat-free"><?php echo esc_html( gv_mlm_format_size( $data['unused_size'] ) ); ?></b><span>حجم قابل آزادسازی</span></div>
		</div>

		<div class="gvmlm-toolbar">
			<input type="text" class="gvmlm-search" id="gv-mlm-search" placeholder="جستجو در نام فایل...">

			<button type="button" class="gvmlm-chip is-active" data-filter-status="all">همه (<?php echo esc_html( $total_count ); ?>)</button>
			<button type="button" class="gvmlm-chip" data-filter-status="used">استفاده‌شده (<?php echo esc_html( $used_count ); ?>)</button>
			<button type="button" class="gvmlm-chip" data-filter-status="unused">بدون استفاده (<?php echo esc_html( $unused_count ); ?>)</button>

			<button type="button" class="gvmlm-chip is-active" data-filter-type="all">همه انواع</button>
			<?php foreach ( $type_counts as $group => $count ) : if ( ! $count ) { continue; } ?>
				<button type="button" class="gvmlm-chip" data-filter-type="<?php echo esc_attr( $group ); ?>"><?php echo esc_html( gv_mlm_type_icon( $group ) . ' ' . gv_mlm_type_label( $group ) . ' (' . $count . ')' ); ?></button>
			<?php endforeach; ?>

			<select class="gvmlm-sort" id="gv-mlm-sort">
				<option value="date-desc">جدیدترین</option>
				<option value="date-asc">قدیمی‌ترین</option>
				<option value="size-desc">بیشترین حجم</option>
				<option value="size-asc">کمترین حجم</option>
				<option value="name-asc">نام (الفبا)</option>
			</select>
		</div>

		<div class="gvmlm-bulkbar" id="gv-mlm-bulkbar" hidden>
			<b id="gv-mlm-selcount">۰ فایل انتخاب شده</b>
			<button type="button" class="gvmlm-chip" id="gv-mlm-select-filtered">انتخاب همه‌ی نتایج فعلی</button>
			<button type="button" class="gvmlm-chip" id="gv-mlm-clear-sel">پاک کردن انتخاب</button>
			<button type="button" class="gvmlm-btn gvmlm-btn-danger" id="gv-mlm-delete-btn">🗑️ حذف فایل‌های انتخاب‌شده</button>
			<span style="font-size:11.5px;color:#166534;">قبل از حذف، از هر فایل بکاپ گرفته می‌شود و از بخش «بکاپ» بالای صفحه قابل بازگردانی است.</span>
		</div>

		<div class="gvmlm-table-card">
			<table class="gvmlm-table" id="gv-mlm-table">
				<thead>
					<tr>
						<th class="gvmlm-cbcell"><input type="checkbox" class="gvmlm-checkall" id="gv-mlm-checkall" title="انتخاب همه‌ی این صفحه"></th>
						<th></th>
						<th>نام فایل</th>
						<th>نوع</th>
						<th>حجم</th>
						<th>وضعیت استفاده</th>
						<th>تاریخ آپلود</th>
						<th>عملیات</th>
					</tr>
				</thead>
				<tbody id="gv-mlm-tbody"></tbody>
			</table>
			<div class="gvmlm-empty" id="gv-mlm-empty-filtered" hidden>هیچ فایلی با این فیلتر/جستجو پیدا نشد.</div>
			<div class="gvmlm-pager" id="gv-mlm-pager">
				<button type="button" id="gv-mlm-prev">قبلی</button>
				<span id="gv-mlm-pageinfo"></span>
				<button type="button" id="gv-mlm-next">بعدی</button>
			</div>
		</div>

		<?php endif; ?>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong> | اینستاگرام: grootvision</p>
	</div>

	<script>
	(function () {
		var GV = {
			ajax:  <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			nonce: <?php echo wp_json_encode( wp_create_nonce( GV_MLM_NONCE ) ); ?>,
			items: <?php echo $has_data ? wp_json_encode( $data['items'] ) : '[]'; ?>,
			labels: <?php echo wp_json_encode( array(
				'image'    => gv_mlm_type_icon( 'image' ) . ' ' . gv_mlm_type_label( 'image' ),
				'video'    => gv_mlm_type_icon( 'video' ) . ' ' . gv_mlm_type_label( 'video' ),
				'audio'    => gv_mlm_type_icon( 'audio' ) . ' ' . gv_mlm_type_label( 'audio' ),
				'document' => gv_mlm_type_icon( 'document' ) . ' ' . gv_mlm_type_label( 'document' ),
				'other'    => gv_mlm_type_icon( 'other' ) . ' ' . gv_mlm_type_label( 'other' ),
			) ); ?>
		};

		var PER_PAGE = 50;
		var wrap = document.getElementById('gv-mlm-wrap');
		if (!wrap) { return; }

		/* ---------- درخواست AJAX عمومی ---------- */
		function postAjax(action, data) {
			var body = new URLSearchParams();
			body.append('action', action);
			body.append('nonce', GV.nonce);
			Object.keys(data || {}).forEach(function (k) {
				var v = data[k];
				if (Array.isArray(v)) { v.forEach(function (x) { body.append(k + '[]', x); }); }
				else { body.append(k, v); }
			});
			return fetch(GV.ajax, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			}).then(function (r) { return r.json(); });
		}

		/* ---------- اسکن تکه‌تکه ---------- */
		var btn      = document.getElementById('gv-mlm-rescan-btn');
		var box      = document.getElementById('gv-mlm-progress');
		var bar      = document.getElementById('gv-mlm-bar');
		var barLabel = document.getElementById('gv-mlm-progress-label');

		function step(restart) {
			postAjax('gv_mlm_scan_step', restart ? { restart: '1' } : {})
			.then(function (json) {
				if (!json || !json.success) {
					scanFail((json && json.data && json.data.message) || 'خطا در اسکن. دوباره تلاش کنید.');
					return;
				}
				bar.style.width = json.data.percent + '%';
				barLabel.textContent = json.data.label + ' (' + json.data.percent + '٪)';

				if (json.data.done) {
					barLabel.textContent = 'اسکن کامل شد، در حال بارگذاری نتایج...';
					window.location.reload();
				} else {
					step(false);
				}
			})
			.catch(function () { scanFail('خطا در ارتباط با سرور. دوباره تلاش کنید.'); });
		}

		function scanFail(msg) {
			btn.disabled = false;
			btn.textContent = '🔄 تلاش مجدد';
			barLabel.textContent = msg;
		}

		if (btn) {
			btn.addEventListener('click', function () {
				btn.disabled = true;
				btn.textContent = '⏳ در حال اسکن...';
				box.hidden = false;
				bar.style.width = '0%';
				step(true);
			});
		}

		/* ---------- بکاپ‌ها: بازگردانی / پاک کردن ---------- */
		function restoreIds(batch, ids, el) {
			var queue = ids.slice(), total = queue.length, nOk = 0, nFail = 0, msgs = [];
			var oldText = el.textContent;
			el.disabled = true;

			(function next() {
				if (!queue.length) {
					el.disabled = false;
					el.textContent = oldText;
					var m = nOk + ' فایل بازگردانی شد.';
					if (nFail) { m += '\n' + nFail + ' فایل بازگردانی نشد:\n' + msgs.slice(0, 6).join('\n'); }
					window.alert(m);
					if (nOk) { window.location.reload(); }
					return;
				}
				var chunk = queue.splice(0, 20);
				el.textContent = '⏳ در حال بازگردانی... (' + (total - queue.length) + ' از ' + total + ')';

				postAjax('gv_mlm_backup_restore', { batch: batch, ids: chunk })
				.then(function (json) {
					if (!json || !json.success) {
						nFail += chunk.length + queue.length;
						msgs.push((json && json.data && json.data.message) || 'خطای نامشخص');
						queue = [];
					} else {
						nOk   += (json.data.restored || []).length;
						nFail += (json.data.failed || []).length;
						(json.data.errors || []).forEach(function (e) { msgs.push(e); });
					}
					next();
				})
				.catch(function () {
					nFail += chunk.length + queue.length;
					msgs.push('خطا در ارتباط با سرور');
					queue = [];
					next();
				});
			})();
		}

		var bkPanel = document.getElementById('gv-mlm-backups');
		if (bkPanel) {
			bkPanel.addEventListener('click', function (e) {
				var el = e.target.closest ? e.target.closest('[data-bk]') : null;
				if (!el || el.disabled) { return; }

				var act   = el.getAttribute('data-bk');
				var batch = el.getAttribute('data-batch') || '';

				if (act === 'restore-batch' || act === 'restore-item') {
					var ids = [];
					if (act === 'restore-item') { ids = [parseInt(el.getAttribute('data-id'), 10)]; }
					else { try { ids = JSON.parse(el.getAttribute('data-ids') || '[]'); } catch (err) { ids = []; } }
					if (!ids.length) { return; }
					if (!window.confirm('آیا از بازگردانی ' + ids.length + ' فایل مطمئن هستید؟\nفایل‌ها با همان شناسه‌ی قبلی به کتابخانه رسانه برمی‌گردند.')) { return; }
					restoreIds(batch, ids, el);
				} else if (act === 'purge-batch' || act === 'purge-all') {
					var msg = act === 'purge-all'
						? 'همه‌ی بکاپ‌ها برای همیشه پاک می‌شوند و دیگر قابل بازگردانی نخواهند بود. ادامه می‌دهید؟'
						: 'این بکاپ برای همیشه پاک می‌شود و دیگر قابل بازگردانی نخواهد بود. ادامه می‌دهید؟';
					if (!window.confirm(msg)) { return; }

					var old = el.textContent;
					el.disabled = true;
					el.textContent = '⏳ در حال پاک کردن...';
					postAjax('gv_mlm_backup_purge', { batch: act === 'purge-all' ? 'all' : batch })
					.then(function (json) {
						if (!json || !json.success) {
							el.disabled = false;
							el.textContent = old;
							window.alert((json && json.data && json.data.message) || 'خطا در پاک کردن بکاپ.');
							return;
						}
						window.location.reload();
					})
					.catch(function () {
						el.disabled = false;
						el.textContent = old;
						window.alert('خطا در ارتباط با سرور.');
					});
				}
			});
		}

		/* ---------- جدول (فیلتر/سورت/صفحه‌بندی سمت مرورگر) ---------- */
		var tbody = document.getElementById('gv-mlm-tbody');
		if (!tbody || !GV.items.length) { return; }

		var searchInput = document.getElementById('gv-mlm-search');
		var sortSelect  = document.getElementById('gv-mlm-sort');
		var emptyBox    = document.getElementById('gv-mlm-empty-filtered');
		var pager       = document.getElementById('gv-mlm-pager');
		var pageInfo    = document.getElementById('gv-mlm-pageinfo');
		var prevBtn     = document.getElementById('gv-mlm-prev');
		var nextBtn     = document.getElementById('gv-mlm-next');
		var statusChips = [].slice.call(wrap.querySelectorAll('[data-filter-status]'));
		var typeChips   = [].slice.call(wrap.querySelectorAll('[data-filter-type]'));

		var activeStatus = 'all', activeType = 'all', page = 1, filtered = [];
		var timer = null;
		var selected = {}; // id => true
		var byId = {};

		var bulkBar     = document.getElementById('gv-mlm-bulkbar');
		var selCountBox = document.getElementById('gv-mlm-selcount');
		var checkAll    = document.getElementById('gv-mlm-checkall');
		var deleteBtn   = document.getElementById('gv-mlm-delete-btn');

		GV.items.forEach(function (it) {
			it._s = ((it.title || '') + ' ' + (it.file || '')).toLowerCase();
			byId[it.id] = it;
		});

		function fmtSize(b) {
			b = parseInt(b, 10) || 0;
			if (b <= 0) { return '—'; }
			var u = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'], i = 0;
			while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
			return (i > 0 ? b.toFixed(2) : b) + ' ' + u[i];
		}

		function esc(s) {
			return String(s == null ? '' : s)
				.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
		}

		function applyFilters() {
			var term = (searchInput.value || '').trim().toLowerCase();
			filtered = GV.items.filter(function (it) {
				if (term && it._s.indexOf(term) === -1) { return false; }
				if (activeStatus !== 'all' && (it.used ? 'used' : 'unused') !== activeStatus) { return false; }
				if (activeType !== 'all' && it.group !== activeType) { return false; }
				return true;
			});
			applySort(true);
		}

		function applySort(keepPage) {
			var parts = sortSelect.value.split('-'), key = parts[0], dir = parts[1];
			filtered.sort(function (a, b) {
				if (key === 'name') {
					return dir === 'asc' ? a._s.localeCompare(b._s, 'fa') : b._s.localeCompare(a._s, 'fa');
				}
				var va = key === 'size' ? (a.size || 0) : (a.ts || 0);
				var vb = key === 'size' ? (b.size || 0) : (b.ts || 0);
				return dir === 'asc' ? va - vb : vb - va;
			});
			if (!keepPage) { page = 1; }
			if (page > Math.ceil(filtered.length / PER_PAGE)) { page = 1; }
			render();
		}

		function rowHtml(it) {
			var usage;
			if (it.used && it.usages && it.usages.length) {
				var lis = it.usages.map(function (u) {
					var t = esc(u.t);
					return '<li><b>' + esc(u.l) + ':</b> ' + (u.u ? '<a href="' + esc(u.u) + '" target="_blank" rel="noopener">' + t + '</a>' : t) + '</li>';
				}).join('');
				usage = '<span class="gvmlm-badge used">استفاده‌شده</span>' +
					'<details><summary class="gvmlm-usage-toggle">' + it.usages.length + ' محل استفاده</summary>' +
					'<ul class="gvmlm-usage-list">' + lis + '</ul></details>';
			} else {
				usage = '<span class="gvmlm-badge unused">بدون استفاده</span>';
			}

			var thumb = it.thumb
				? '<img class="gvmlm-thumb" loading="lazy" src="' + esc(it.thumb) + '" alt="">'
				: '<span class="gvmlm-thumb">' + (GV.labels[it.group] || '📁').split(' ')[0] + '</span>';

			return '<tr data-row-id="' + it.id + '">' +
				'<td class="gvmlm-cbcell"><input type="checkbox" class="gvmlm-cb" data-id="' + it.id + '"' + (selected[it.id] ? ' checked' : '') + '></td>' +
				'<td>' + thumb + '</td>' +
				'<td>' + esc(it.title) + '<span class="gvmlm-fname">' + esc(it.file) + '</span>' +
					(it.exists ? '' : '<span class="gvmlm-missing">⚠️ فایل روی هاست پیدا نشد</span>') + '</td>' +
				'<td><span class="gvmlm-badge">' + esc(GV.labels[it.group] || '📁 سایر') + '</span></td>' +
				'<td>' + fmtSize(it.size) + '</td>' +
				'<td>' + usage + '</td>' +
				'<td>' + esc(it.date) + '</td>' +
				'<td class="gvmlm-actions">' +
					'<a href="' + esc(it.url) + '" target="_blank" rel="noopener">مشاهده فایل ↗</a>' +
					'<a href="' + esc(it.edit) + '" target="_blank" rel="noopener">ویرایش</a>' +
					'<button type="button" class="gvmlm-row-del" data-id="' + it.id + '">حذف</button>' +
				'</td></tr>';
		}

		function render() {
			var total = filtered.length;
			var pages = Math.max(1, Math.ceil(total / PER_PAGE));
			if (page > pages) { page = pages; }

			var slice = filtered.slice((page - 1) * PER_PAGE, page * PER_PAGE);
			tbody.innerHTML = slice.map(rowHtml).join('');

			emptyBox.hidden = total !== 0;
			pager.style.display = total > PER_PAGE ? 'flex' : 'none';
			pageInfo.textContent = 'صفحه ' + page + ' از ' + pages + ' — ' + total + ' فایل';
			prevBtn.disabled = page <= 1;
			nextBtn.disabled = page >= pages;

			syncSelectionUI(slice);
		}

		/* ---------- انتخاب و حذف (همراه با بکاپ) ---------- */
		function selectedIds() {
			return Object.keys(selected).map(function (k) { return parseInt(k, 10); });
		}

		function syncSelectionUI(slice) {
			var ids = selectedIds();
			bulkBar.hidden = ids.length === 0;
			selCountBox.textContent = ids.length.toLocaleString('fa-IR') + ' فایل انتخاب شده';

			var pageIds = (slice || []).map(function (it) { return it.id; });
			checkAll.checked = pageIds.length > 0 && pageIds.every(function (id) { return selected[id]; });
		}

		function deleteIds(ids) {
			if (!ids.length) { return; }

			var usedCount = ids.filter(function (id) { return byId[id] && byId[id].used; }).length;
			var msg = 'آیا از حذف ' + ids.length + ' فایل مطمئن هستید؟\n\nقبل از حذف، از فایل‌ها (و اطلاعاتشان) بکاپ گرفته می‌شود. بعد از حذف می‌توانید سایت را بررسی کنید؛ اگر مشکلی بود از بخش «بکاپ» بالای صفحه بازگردانی کنید، و اگر همه‌چیز درست بود بکاپ را پاک کنید تا فضا آزاد شود.';
			if (usedCount) {
				msg += '\n\n⚠️ هشدار: ' + usedCount + ' فایل از این‌ها در سایت استفاده شده‌اند و حذفشان ممکن است صفحات را خراب کند.';
			}
			if (!window.confirm(msg)) { return; }

			var batch = 'b' + Date.now().toString(36) + Math.random().toString(36).replace(/[^a-z0-9]/g, '').slice(0, 4);
			var queue = ids.slice();
			var total = queue.length;
			var okAll = [], failAll = [];

			deleteBtn.disabled = true;

			function nextChunk() {
				if (!queue.length) { finish(); return; }

				var chunk = queue.splice(0, 20);
				deleteBtn.textContent = '⏳ بکاپ و حذف... (' + (total - queue.length) + ' از ' + total + ')';

				postAjax('gv_mlm_delete', { batch: batch, ids: chunk })
				.then(function (json) {
					if (!json || !json.success) {
						failAll = failAll.concat(chunk);
						window.alert((json && json.data && json.data.message) || 'خطا در حذف فایل‌ها.');
						finish();
						return;
					}
					okAll   = okAll.concat(json.data.deleted || []);
					failAll = failAll.concat(json.data.failed || []);
					nextChunk();
				})
				.catch(function () {
					failAll = failAll.concat(chunk);
					window.alert('خطا در ارتباط با سرور. بقیه‌ی فایل‌ها حذف نشدند.');
					finish();
				});
			}

			function finish() {
				deleteBtn.disabled = false;
				deleteBtn.textContent = '🗑️ حذف فایل‌های انتخاب‌شده';

				if (failAll.length) {
					window.alert(okAll.length + ' فایل حذف شد. ' + failAll.length + ' فایل حذف نشد (دسترسی، فایل ناموجود یا ناموفق بودن بکاپ).');
				}
				if (okAll.length) { window.location.reload(); }
			}

			nextChunk();
		}

		tbody.addEventListener('change', function (e) {
			var cb = e.target;
			if (!cb.classList || !cb.classList.contains('gvmlm-cb')) { return; }
			var id = parseInt(cb.getAttribute('data-id'), 10);
			if (cb.checked) { selected[id] = true; } else { delete selected[id]; }
			syncSelectionUI(filtered.slice((page - 1) * PER_PAGE, page * PER_PAGE));
		});

		tbody.addEventListener('click', function (e) {
			var b = e.target;
			if (!b.classList || !b.classList.contains('gvmlm-row-del')) { return; }
			deleteIds([parseInt(b.getAttribute('data-id'), 10)]);
		});

		checkAll.addEventListener('change', function () {
			var slice = filtered.slice((page - 1) * PER_PAGE, page * PER_PAGE);
			slice.forEach(function (it) {
				if (checkAll.checked) { selected[it.id] = true; } else { delete selected[it.id]; }
			});
			render();
		});

		document.getElementById('gv-mlm-select-filtered').addEventListener('click', function () {
			filtered.forEach(function (it) { selected[it.id] = true; });
			render();
		});

		document.getElementById('gv-mlm-clear-sel').addEventListener('click', function () {
			selected = {};
			render();
		});

		deleteBtn.addEventListener('click', function () { deleteIds(selectedIds()); });

		searchInput.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(function () { page = 1; applyFilters(); }, 200);
		});
		sortSelect.addEventListener('change', function () { applySort(false); });
		prevBtn.addEventListener('click', function () { if (page > 1) { page--; render(); window.scrollTo(0, 0); } });
		nextBtn.addEventListener('click', function () { page++; render(); window.scrollTo(0, 0); });

		statusChips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				statusChips.forEach(function (c) { c.classList.remove('is-active'); });
				chip.classList.add('is-active');
				activeStatus = chip.getAttribute('data-filter-status');
				page = 1;
				applyFilters();
			});
		});

		typeChips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				typeChips.forEach(function (c) { c.classList.remove('is-active'); });
				chip.classList.add('is-active');
				activeType = chip.getAttribute('data-filter-type');
				page = 1;
				applyFilters();
			});
		});

		applyFilters();
	})();
	</script>
	<?php
}