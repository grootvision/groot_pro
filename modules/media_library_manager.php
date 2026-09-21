<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — مدیریت فایل‌های چندرسانه‌ای (نسخه بهینه‌شده)
 *  ------------------------------------------------------------
 *  تفاوت‌های اصلی با نسخه قبلی:
 *  ۱) اسکن دیگر «به ازای هر فایل ۶ کوئری» نیست. کل سایت یک‌بار
 *     خوانده می‌شود و نام فایل‌ها با یک نقشه (map) در PHP تطبیق
 *     داده می‌شود. یعنی به‌جای هزاران کوئری، چند ده کوئری.
 *  ۲) اسکن دیگر هنگام باز کردن صفحه اجرا نمی‌شود. صفحه فوراً باز
 *     می‌شود و اسکن به‌صورت تکه‌تکه (AJAX + نوار پیشرفت) انجام
 *     می‌شود؛ پس تایم‌اوت و صفحه سفید نداریم.
 *  ۳) جدول دیگر هزاران ردیف HTML نیست؛ داده به صورت JSON به
 *     مرورگر داده می‌شود و فقط ۵۰ ردیف هر صفحه رندر می‌شود.
 * ==========================================================
 */

define( 'GV_MLM_PAGE_SLUG',  'gv-media-manager' );
define( 'GV_MLM_TRANSIENT',  'gv_mlm_scan_data' );   // نتیجه نهایی اسکن
define( 'GV_MLM_STATE',      'gv_mlm_scan_state' );  // وضعیت اسکن در حال انجام
define( 'GV_MLM_NONCE',      'gv_mlm_nonce_action' );

define( 'GV_MLM_BATCH_ATT',   300 );   // تعداد فایل در هر تکه
define( 'GV_MLM_BATCH_POSTS', 150 );   // تعداد نوشته در هر تکه
define( 'GV_MLM_BATCH_META',  3000 );  // تعداد ردیف postmeta در هر تکه
define( 'GV_MLM_MAX_USAGE',   10 );    // حداکثر محل استفاده ذخیره‌شده برای هر فایل
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
 * (مثلاً photo-300x200.jpg و photo-scaled.jpg) همگی به photo.jpg برسند.
 */
function gv_mlm_norm_name( $name ) {
	$name = strtolower( basename( (string) $name ) );
	$name = preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/', '', $name );
	$name = preg_replace( '/-(scaled|rotated|e\d+)(?=\.[a-z0-9]+$)/', '', $name );
	return $name;
}

/**
 * از یک متن (محتوای نوشته، متای المنتور، مقدار ویجت و ...) تمام
 * نام‌فایل‌ها و شناسه‌های رسانه‌ی ارجاع‌داده‌شده را بیرون می‌کشد.
 * این کار یک‌بار روی هر متن انجام می‌شود (به‌جای جستجوی جداگانه‌ی هر فایل).
 */
function gv_mlm_extract_refs( $text ) {
	$names = array();
	$ids   = array();

	if ( ! is_string( $text ) || '' === $text ) {
		return array( $names, $ids );
	}

	// شناسه‌های رسانه داخل کلاس‌های وردپرس و بلوک‌های گوتنبرگ/المنتور
	if ( false !== stripos( $text, 'wp-image-' ) && preg_match_all( '/wp-image-(\d+)/', $text, $m ) ) {
		$ids = array_merge( $ids, $m[1] );
	}
	if ( false !== stripos( $text, 'attachment_' ) && preg_match_all( '/attachment_(\d+)/', $text, $m ) ) {
		$ids = array_merge( $ids, $m[1] );
	}

	// نام فایل‌ها (با پسوندهای رایج). \/ داخل JSON هم درست هندل می‌شود.
	$pattern = '/[^\/\\\\"\'\s<>()\[\]{},;]+\.(?:jpe?g|png|gif|webp|avif|svg|bmp|ico|mp4|m4v|mov|avi|mkv|webm|ogv|mp3|wav|ogg|m4a|flac|pdf|docx?|xlsx?|pptx?|zip|rar|csv|txt)/i';
	if ( preg_match_all( $pattern, $text, $m2 ) ) {
		$names = $m2[0];
	}

	return array( $names, $ids );
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

	return array(
		'stage'       => 'attachments',
		'last_att'    => 0,
		'last_post'   => 0,
		'last_meta'   => 0,
		'done_att'    => 0,
		'done_posts'  => 0,
		'total_att'   => $total_att,
		'total_posts' => $total_posts,
		'max_meta'    => $max_meta,
		'items'       => array(), // id => اطلاعات پایه فایل
		'map'         => array(), // نام نرمال‌شده => آرایه‌ای از idها
		'usage'       => array(), // id => array('posts'=>array(post_id=>label_code), 'other'=>array(label=>1))
		'titles'      => array(), // post_id => عنوان (برای صرفه‌جویی در کوئری)
		'started_at'  => time(),
	);
}

function gv_mlm_add_usage( &$state, $att_id, $type, $key, $label_code = '' ) {
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
			$state['usage'][ $att_id ]['posts'][ $key ] = $label_code;
		}
	} else {
		$state['usage'][ $att_id ]['other'][ $key ] = 1;
	}
}

/**
 * نام‌ها و idهای استخراج‌شده از یک متن را به فایل‌های کتابخانه رسانه وصل می‌کند.
 */
function gv_mlm_match_refs( &$state, $names, $ids, $type, $key, $label_code = '' ) {
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
		gv_mlm_add_usage( $state, $att_id, $type, $key, $label_code );
	}
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

		/* --- مرحله ۲: محتوای نوشته‌ها/صفحات/محصولات --- */
		case 'posts':
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_title, post_content
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
				list( $names, $ids ) = gv_mlm_extract_refs( $row->post_content );
				if ( $names || $ids ) {
					gv_mlm_match_refs( $state, $names, $ids, 'post', $pid, 'content' );
					$state['titles'][ $pid ] = $row->post_title ? $row->post_title : '(بدون عنوان)';
				}
				$state['last_post'] = $pid;
				$state['done_posts']++;
			}
			break;

		/* --- مرحله ۳: فیلدهای سفارشی، سازنده صفحه، تصویر شاخص، ACF و ... --- */
		case 'meta':
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_id, post_id, meta_key, meta_value
				 FROM {$wpdb->postmeta}
				 WHERE meta_id > %d
				 ORDER BY meta_id ASC LIMIT %d",
				(int) $state['last_meta'],
				GV_MLM_BATCH_META
			) );

			if ( empty( $rows ) ) {
				$state['stage'] = 'options';
				break;
			}

			foreach ( $rows as $row ) {
				$state['last_meta'] = (int) $row->meta_id;
				$key                = (string) $row->meta_key;

				if ( '_thumbnail_id' === $key ) {
					gv_mlm_add_usage( $state, (int) $row->meta_value, 'post', (int) $row->post_id, 'featured' );
					continue;
				}

				// متاهای خود فایل‌ها و متاهای بی‌ربط را رد می‌کنیم
				if ( '_wp_attached_file' === $key || '_wp_attachment_metadata' === $key
					|| 0 === strpos( $key, '_edit_' ) || 0 === strpos( $key, '_oembed' ) ) {
					continue;
				}

				$value = (string) $row->meta_value;
				if ( '' === $value || false === strpos( $value, '.' ) ) { continue; }

				list( $names, $ids ) = gv_mlm_extract_refs( $value );
				if ( $names || $ids ) {
					gv_mlm_match_refs( $state, $names, $ids, 'post', (int) $row->post_id, 'meta' );
				}
			}
			break;

		/* --- مرحله ۴: ویجت‌ها و شخصی‌سازی قالب --- */
		case 'options':
			$opts = $wpdb->get_results(
				"SELECT option_name, option_value FROM {$wpdb->options}
				 WHERE option_name LIKE 'widget\_%'
				 OR option_name LIKE 'theme\_mods\_%'
				 OR option_name IN ('site_icon','site_logo')"
			);

			foreach ( $opts as $o ) {
				$name = (string) $o->option_name;

				if ( in_array( $name, array( 'site_icon', 'site_logo' ), true ) ) {
					gv_mlm_add_usage( $state, (int) $o->option_value, 'other', 'شخصی‌سازی قالب (Customizer)' );
					continue;
				}

				$label = ( 0 === strpos( $name, 'widget_' ) ) ? 'ویجت سایت' : 'شخصی‌سازی قالب (Customizer)';
				list( $names, $ids ) = gv_mlm_extract_refs( (string) $o->option_value );
				if ( $names || $ids ) {
					gv_mlm_match_refs( $state, $names, $ids, 'other', $label );
				}
			}

			$state['stage'] = 'finalize';
			break;
	}

	return $state;
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

	foreach ( array_chunk( $need, 300 ) as $chunk ) {
		$in   = implode( ',', array_map( 'intval', $chunk ) );
		$rows = $wpdb->get_results( "SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE ID IN ($in)" );
		$found = array();
		foreach ( $rows as $r ) {
			$found[ (int) $r->ID ] = true;
			if ( in_array( $r->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				$invalid[ (int) $r->ID ] = true;
				continue;
			}
			$state['titles'][ (int) $r->ID ] = $r->post_title ? $r->post_title : '(بدون عنوان)';
		}
		foreach ( $chunk as $pid ) {
			if ( empty( $found[ (int) $pid ] ) ) { $invalid[ (int) $pid ] = true; }
		}
	}

	$items        = array();
	$total_size   = 0;
	$used_count   = 0;
	$unused_count = 0;
	$unused_size  = 0;
	$type_counts  = array( 'image' => 0, 'video' => 0, 'audio' => 0, 'document' => 0, 'other' => 0 );

	foreach ( $state['items'] as $id => $it ) {
		$usages = array();

		if ( isset( $state['usage'][ $id ] ) ) {
			foreach ( $state['usage'][ $id ]['posts'] as $pid => $code ) {
				if ( isset( $invalid[ $pid ] ) ) { continue; }
				$usages[] = array(
					'l' => isset( $labels[ $code ] ) ? $labels[ $code ] : 'محل نامشخص',
					't' => isset( $state['titles'][ $pid ] ) ? $state['titles'][ $pid ] : '#' . $pid,
					'u' => admin_url( 'post.php?post=' . (int) $pid . '&action=edit' ),
				);
			}
			foreach ( array_keys( $state['usage'][ $id ]['other'] ) as $label ) {
				$usages[] = array(
					'l' => $label,
					't' => ( 'ویجت سایت' === $label ) ? 'ویجت‌های سایت' : 'تنظیمات ظاهری قالب',
					'u' => ( 'ویجت سایت' === $label ) ? admin_url( 'widgets.php' ) : admin_url( 'customize.php' ),
				);
			}
		}

		$it['usages'] = $usages;
		$it['used']   = empty( $usages ) ? 0 : 1;

		$total_size += $it['size'];
		if ( isset( $type_counts[ $it['group'] ] ) ) { $type_counts[ $it['group'] ]++; }

		if ( $it['used'] ) {
			$used_count++;
		} else {
			$unused_count++;
			$unused_size += $it['size'];
		}

		$items[] = $it;
	}

	// مرتب‌سازی پیش‌فرض: جدیدترین
	usort( $items, function ( $a, $b ) { return $b['ts'] - $a['ts']; } );

	$data = array(
		'scanned_at'   => current_time( 'timestamp' ), // phpcs:ignore
		'items'        => $items,
		'total_count'  => count( $items ),
		'total_size'   => $total_size,
		'used_count'   => $used_count,
		'unused_count' => $unused_count,
		'unused_size'  => $unused_size,
		'type_counts'  => $type_counts,
	);

	set_transient( GV_MLM_TRANSIENT, $data, DAY_IN_SECONDS );
	delete_transient( GV_MLM_STATE );

	return $data;
}

function gv_mlm_progress( $state ) {
	$p = 0;

	$att   = $state['total_att']   > 0 ? min( 1, $state['done_att']   / $state['total_att'] )   : 1;
	$posts = $state['total_posts'] > 0 ? min( 1, $state['done_posts'] / $state['total_posts'] ) : 1;
	$meta  = $state['max_meta']    > 0 ? min( 1, $state['last_meta']  / $state['max_meta'] )    : 1;

	$p += $att * 20;
	if ( 'attachments' !== $state['stage'] ) { $p += $posts * 35; }
	if ( 'attachments' !== $state['stage'] && 'posts' !== $state['stage'] ) { $p += $meta * 40; }
	if ( 'finalize' === $state['stage'] || 'done' === $state['stage'] ) { $p = 100; }

	$stages = array(
		'attachments' => 'خواندن کتابخانه رسانه...',
		'posts'       => 'بررسی محتوای نوشته‌ها و صفحات...',
		'meta'        => 'بررسی سازنده صفحه و فیلدهای سفارشی...',
		'options'     => 'بررسی ویجت‌ها و تنظیمات قالب...',
		'finalize'    => 'جمع‌بندی نتایج...',
		'done'        => 'تمام شد',
	);

	return array(
		'percent' => (int) round( min( 100, $p ) ),
		'label'   => isset( $stages[ $state['stage'] ] ) ? $stages[ $state['stage'] ] : '',
	);
}

/* ==========================================================================
   ۴) اکشن AJAX (هر درخواست = یک تکه از اسکن)
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

	if ( ! is_array( $state ) || empty( $state['stage'] ) ) {
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
   ۵) حذف فایل‌های انتخاب‌شده
   ========================================================================== */

/**
 * فایل‌های حذف‌شده را از کش بیرون می‌کشد و آمار را دوباره حساب می‌کند،
 * تا نیازی به اسکن مجدد بعد از هر حذف نباشد.
 */
function gv_mlm_remove_from_cache( $deleted_ids ) {
	$data = get_transient( GV_MLM_TRANSIENT );
	if ( ! is_array( $data ) || ! isset( $data['items'] ) ) { return null; }

	$drop  = array_flip( array_map( 'intval', $deleted_ids ) );
	$items = array();

	$total_size   = 0;
	$used_count   = 0;
	$unused_count = 0;
	$unused_size  = 0;
	$type_counts  = array( 'image' => 0, 'video' => 0, 'audio' => 0, 'document' => 0, 'other' => 0 );

	foreach ( $data['items'] as $it ) {
		if ( isset( $drop[ (int) $it['id'] ] ) ) { continue; }

		$total_size += $it['size'];
		if ( isset( $type_counts[ $it['group'] ] ) ) { $type_counts[ $it['group'] ]++; }
		if ( ! empty( $it['used'] ) ) {
			$used_count++;
		} else {
			$unused_count++;
			$unused_size += $it['size'];
		}
		$items[] = $it;
	}

	$data['items']        = $items;
	$data['total_count']  = count( $items );
	$data['total_size']   = $total_size;
	$data['used_count']   = $used_count;
	$data['unused_count'] = $unused_count;
	$data['unused_size']  = $unused_size;
	$data['type_counts']  = $type_counts;

	set_transient( GV_MLM_TRANSIENT, $data, DAY_IN_SECONDS );
	return $data;
}

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

	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 0 ); } // phpcs:ignore

	$deleted = array();
	$failed  = array();

	foreach ( $ids as $id ) {
		$post = get_post( $id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			$failed[] = $id;
			continue;
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			$failed[] = $id;
			continue;
		}

		// true یعنی حذف کامل (سطل زباله برای پیوست‌ها معنی ندارد)
		$result = wp_delete_attachment( $id, true );

		if ( $result ) {
			$deleted[] = $id;
		} else {
			$failed[] = $id;
		}
	}

	$data = gv_mlm_remove_from_cache( $deleted );

	$stats = null;
	if ( is_array( $data ) ) {
		$stats = array(
			'total_count'  => (int) $data['total_count'],
			'total_size'   => gv_mlm_format_size( $data['total_size'] ),
			'used_count'   => (int) $data['used_count'],
			'unused_count' => (int) $data['unused_count'],
			'unused_size'  => gv_mlm_format_size( $data['unused_size'] ),
		);
	}

	wp_send_json_success( array(
		'deleted' => $deleted,
		'failed'  => $failed,
		'stats'   => $stats,
	) );
}

/* ==========================================================================
   ۶) صفحه‌ی مدیریت
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
			<span style="font-size:11.5px;color:#9a3412;">حذف دائمی است و قابل بازگشت نیست.</span>
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

		/* ---------- اسکن تکه‌تکه ---------- */
		var btn      = document.getElementById('gv-mlm-rescan-btn');
		var box      = document.getElementById('gv-mlm-progress');
		var bar      = document.getElementById('gv-mlm-bar');
		var barLabel = document.getElementById('gv-mlm-progress-label');

		function step(restart) {
			var body = new URLSearchParams();
			body.append('action', 'gv_mlm_scan_step');
			body.append('nonce', GV.nonce);
			if (restart) { body.append('restart', '1'); }

			fetch(GV.ajax, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json || !json.success) {
					fail((json && json.data && json.data.message) || 'خطا در اسکن. دوباره تلاش کنید.');
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
			.catch(function () { fail('خطا در ارتباط با سرور. دوباره تلاش کنید.'); });
		}

		function fail(msg) {
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

		/* ---------- انتخاب و حذف ---------- */
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

		function refreshCounts(stats) {
			if (stats) {
				document.getElementById('gv-stat-total').textContent  = stats.total_count.toLocaleString('fa-IR');
				document.getElementById('gv-stat-size').textContent   = stats.total_size;
				document.getElementById('gv-stat-used').textContent   = stats.used_count.toLocaleString('fa-IR');
				document.getElementById('gv-stat-unused').textContent = stats.unused_count.toLocaleString('fa-IR');
				document.getElementById('gv-stat-free').textContent   = stats.unused_size;
			}

			var counts = { all: GV.items.length, used: 0, unused: 0 };
			var groups = {};
			GV.items.forEach(function (it) {
				counts[it.used ? 'used' : 'unused']++;
				groups[it.group] = (groups[it.group] || 0) + 1;
			});

			var names = { all: 'همه', used: 'استفاده‌شده', unused: 'بدون استفاده' };
			statusChips.forEach(function (chip) {
				var k = chip.getAttribute('data-filter-status');
				chip.textContent = names[k] + ' (' + (counts[k] || 0) + ')';
			});
			typeChips.forEach(function (chip) {
				var k = chip.getAttribute('data-filter-type');
				if (k === 'all') { return; }
				chip.textContent = (GV.labels[k] || k) + ' (' + (groups[k] || 0) + ')';
				chip.hidden = !groups[k];
			});
		}

		function deleteIds(ids) {
			if (!ids.length) { return; }

			var usedCount = ids.filter(function (id) { return byId[id] && byId[id].used; }).length;
			var msg = 'آیا از حذف دائمی ' + ids.length + ' فایل مطمئن هستید؟\nفایل‌ها از هاست و کتابخانه رسانه پاک می‌شوند و قابل بازگشت نیستند.';
			if (usedCount) {
				msg += '\n\n⚠️ هشدار: ' + usedCount + ' فایل از این‌ها در سایت استفاده شده‌اند و حذفشان ممکن است صفحات را خراب کند.';
			}
			if (!window.confirm(msg)) { return; }

			var queue = ids.slice();
			var total = queue.length;
			var okAll = [], failAll = [], lastStats = null;

			deleteBtn.disabled = true;

			function nextChunk() {
				if (!queue.length) { finish(); return; }

				var chunk = queue.splice(0, 25);
				deleteBtn.textContent = '⏳ در حال حذف... (' + (total - queue.length) + ' از ' + total + ')';

				var body = new URLSearchParams();
				body.append('action', 'gv_mlm_delete');
				body.append('nonce', GV.nonce);
				chunk.forEach(function (id) { body.append('ids[]', id); });

				fetch(GV.ajax, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				})
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (!json || !json.success) {
						failAll = failAll.concat(chunk);
						window.alert((json && json.data && json.data.message) || 'خطا در حذف فایل‌ها.');
						finish();
						return;
					}
					okAll    = okAll.concat(json.data.deleted || []);
					failAll  = failAll.concat(json.data.failed || []);
					lastStats = json.data.stats || lastStats;
					nextChunk();
				})
				.catch(function () {
					failAll = failAll.concat(chunk);
					window.alert('خطا در ارتباط با سرور. بقیه‌ی فایل‌ها حذف نشدند.');
					finish();
				});
			}

			function finish() {
				var dropped = {};
				okAll.forEach(function (id) { dropped[id] = true; delete selected[id]; delete byId[id]; });
				GV.items = GV.items.filter(function (it) { return !dropped[it.id]; });

				deleteBtn.disabled = false;
				deleteBtn.textContent = '🗑️ حذف فایل‌های انتخاب‌شده';

				refreshCounts(lastStats);
				applyFilters();

				if (failAll.length) {
					window.alert(okAll.length + ' فایل حذف شد. ' + failAll.length + ' فایل حذف نشد (احتمالاً دسترسی یا فایل ناموجود).');
				}
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
			var btn = e.target;
			if (!btn.classList || !btn.classList.contains('gvmlm-row-del')) { return; }
			deleteIds([parseInt(btn.getAttribute('data-id'), 10)]);
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