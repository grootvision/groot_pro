<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — واترمارک تصاویر سایت (نسخه‌ی ۲ — اسکن مرحله‌ای)
 *  ------------------------------------------------------------
 *  - ابتدا اسکن دستی و مرحله‌ای «محل استفاده‌ی تصاویر» (بدون اعمال هیچ واترمارکی)
 *  - بعد از اسکن: نمایش کتابخانه، فیلتر و انتخاب دستی
 *  - واترمارک خودکار روی آپلودهای جدید (با امکان محدود کردن به دسته‌ها)
 *  - بازگردانی تکی، گروهی و «بازگردانی همه» (پردازش مرحله‌ای با نوار پیشرفت)
 *  - پشتیبان‌گیری از نسخه‌ی اصلی پیش از هر واترمارک
 * ==========================================================
 */

define( 'GV_WM_OPT', 'gv_watermark_settings' );
define( 'GV_WM_NONCE', 'gv_wm_nonce_action' );
define( 'GV_WM_DB_VERSION', '1.0' );
define( 'GV_WM_PAGE_SLUG', 'gv-watermark' );
define( 'GV_WM_BACKUP_DIR', 'gv-watermark-originals' );
define( 'GV_WM_META_FLAG', '_gv_watermark_applied' );
define( 'GV_WM_META_SKIP', '_gv_wm_skip' );          // تصویری که ادمین دستی بازگردانده و نباید خودکار دوباره واترمارک بخورد
define( 'GV_WM_USAGE_OPT', 'gv_wm_usage_index' );    // ایندکس «محل استفاده‌ی تصاویر»
define( 'GV_WM_SCAN_TMP', 'gv_wm_scan_tmp' );        // وضعیت موقت اسکن مرحله‌ای

/* ==========================================================================
   ۰) تنظیمات
   ========================================================================== */
function gv_wm_default_settings() {
	return array(
		'enabled'        => 0,
		'logo_id'        => 0,
		'position'       => 'bottom-right',
		'size_percent'   => 18,
		'opacity'        => 45,
		'margin_percent' => 3,
		'min_width'      => 300,
		'apply_to_sizes' => 1,
		'auto_cats'      => array( 'shop', 'blog', 'page', 'other' ), // دسته‌هایی که آپلود جدیدشان خودکار واترمارک می‌خورد
	);
}

function gv_wm_get_settings() {
	$s = wp_parse_args( get_option( GV_WM_OPT, array() ), gv_wm_default_settings() );
	if ( ! is_array( $s['auto_cats'] ) ) { $s['auto_cats'] = gv_wm_default_settings()['auto_cats']; }
	return $s;
}

/* دسته‌بندی‌های محل استفاده */
function gv_wm_categories() {
	return array(
		'shop'   => array( 'label' => 'فروشگاه',              'icon' => '🛍️', 'color' => '#059669' ),
		'blog'   => array( 'label' => 'وبلاگ',                'icon' => '📝', 'color' => '#2563eb' ),
		'page'   => array( 'label' => 'صفحات',                'icon' => '📄', 'color' => '#7c3aed' ),
		'header' => array( 'label' => 'هدر',                  'icon' => '🔝', 'color' => '#d97706' ),
		'footer' => array( 'label' => 'فوتر',                 'icon' => '🔚', 'color' => '#dc2626' ),
		'widget' => array( 'label' => 'ویجت و سایدبار',       'icon' => '🧩', 'color' => '#0891b2' ),
		'theme'  => array( 'label' => 'تنظیمات قالب',         'icon' => '🎨', 'color' => '#db2777' ),
		'other'  => array( 'label' => 'سایر بخش‌ها',          'icon' => '📦', 'color' => '#64748b' ),
		'unused' => array( 'label' => 'بدون استفاده / نامشخص', 'icon' => '💤', 'color' => '#94a3b8' ),
	);
}

/* ==========================================================================
   ۱) جدول دیتابیس (لاگ نسخه‌های پشتیبان)
   ========================================================================== */
add_action( 'plugins_loaded', 'gv_wm_maybe_install_db' );
function gv_wm_maybe_install_db() {
	if ( get_option( 'gv_wm_db_version' ) === GV_WM_DB_VERSION ) { return; }

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table = $wpdb->prefix . 'gv_watermark_log';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		attachment_id BIGINT UNSIGNED NOT NULL,
		file_name VARCHAR(255) NOT NULL,
		backup_path TEXT NULL,
		applied_at DATETIME NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'watermarked',
		restored_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY attachment_id (attachment_id),
		KEY status (status)
	) {$charset_collate};" );

	update_option( 'gv_wm_db_version', GV_WM_DB_VERSION );
}

/* ==========================================================================
   ۲) پوشه‌ی پشتیبان
   ========================================================================== */
function gv_wm_backup_dir() {
	$upload_dir = wp_upload_dir();
	$dir = trailingslashit( $upload_dir['basedir'] ) . GV_WM_BACKUP_DIR;
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		@file_put_contents( $dir . '/.htaccess', "Options -Indexes\nDeny from all\n" );
	}
	return $dir;
}

function gv_wm_backup_size() {
	$list = glob( trailingslashit( gv_wm_backup_dir() ) . '*' );
	if ( ! $list ) { return 0; }
	$total = 0;
	foreach ( $list as $f ) { if ( is_file( $f ) ) { $total += (int) filesize( $f ); } }
	return $total;
}

/* ==========================================================================
   ۳) قفل موقت پردازش
   ========================================================================== */
function gv_wm_processing_locked( $set = null ) {
	static $locked = false;
	if ( null !== $set ) { $locked = (bool) $set; }
	return $locked;
}

/* ==========================================================================
   ۴) تصاویر مستثنی (لوگو/آیکون سایت + فایل لوگوی واترمارک)
   ========================================================================== */
function gv_wm_excluded_ids( $settings ) {
	$ids = array();
	foreach ( array( (int) $settings['logo_id'], (int) get_theme_mod( 'custom_logo' ), (int) get_option( 'site_icon' ), (int) get_option( 'site_logo' ) ) as $id ) {
		if ( $id > 0 ) { $ids[ $id ] = true; }
	}
	return $ids;
}

function gv_wm_is_excluded_attachment( $attachment_id, $settings ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) { return true; }
	$ex = gv_wm_excluded_ids( $settings );
	return isset( $ex[ $attachment_id ] );
}

/* ==========================================================================
   ۵) توابع GD
   ========================================================================== */
function gv_wm_load_image( $path, $mime ) {
	switch ( $mime ) {
		case 'image/jpeg': $img = @imagecreatefromjpeg( $path ); break;
		case 'image/png':  $img = @imagecreatefrompng( $path ); break;
		case 'image/webp': $img = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false; break;
		case 'image/gif':  $img = @imagecreatefromgif( $path ); break;
		default: return false;
	}
	if ( ! $img ) { return false; }
	imagealphablending( $img, true );
	imagesavealpha( $img, true );
	return $img;
}

function gv_wm_save_image( $img, $path, $mime, $quality = 90 ) {
	switch ( $mime ) {
		case 'image/jpeg':
			$w = imagesx( $img ); $h = imagesy( $img );
			$flat = imagecreatetruecolor( $w, $h );
			$white = imagecolorallocate( $flat, 255, 255, 255 );
			imagefilledrectangle( $flat, 0, 0, $w, $h, $white );
			imagealphablending( $flat, true );
			imagecopy( $flat, $img, 0, 0, 0, 0, $w, $h );
			$ok = imagejpeg( $flat, $path, $quality );
			imagedestroy( $flat );
			return $ok;
		case 'image/png':
			return imagepng( $img, $path, 6 );
		case 'image/webp':
			return function_exists( 'imagewebp' ) ? imagewebp( $img, $path, $quality ) : false;
		case 'image/gif':
			return imagegif( $img, $path );
	}
	return false;
}

/* کم‌کردن شفافیتِ کلیِ لوگو روی خودِ کانال آلفا (بدون ایجاد جعبه‌ی تیره پشت لوگو) */
function gv_wm_scale_alpha( $img, $opacity_pct ) {
	$w = imagesx( $img ); $h = imagesy( $img );
	$factor = max( 0, min( 100, $opacity_pct ) ) / 100;

	imagealphablending( $img, false );
	imagesavealpha( $img, true );

	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$rgba = imagecolorat( $img, $x, $y );
			$a    = ( $rgba >> 24 ) & 0x7F;
			$orig_opaqueness = 1 - ( $a / 127 );
			$new_opaqueness  = $orig_opaqueness * $factor;
			$new_a = (int) round( 127 * ( 1 - $new_opaqueness ) );
			$new_a = max( 0, min( 127, $new_a ) );

			if ( $new_a !== $a ) {
				$r = ( $rgba >> 16 ) & 0xFF;
				$g = ( $rgba >> 8 ) & 0xFF;
				$b = $rgba & 0xFF;
				$color = imagecolorallocatealpha( $img, $r, $g, $b, $new_a );
				imagesetpixel( $img, $x, $y, $color );
			}
		}
	}
}

function gv_wm_composite_and_save( $target_file, $logo_file, $settings ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) { return false; }

	$target_info = @getimagesize( $target_file );
	$logo_info   = @getimagesize( $logo_file );
	if ( ! $target_info || ! $logo_info ) { return false; }

	list( $t_w, $t_h ) = $target_info;
	list( $l_w0, $l_h0 ) = $logo_info;
	if ( $t_w < 10 || $t_h < 10 || $l_w0 < 1 || $l_h0 < 1 ) { return false; }

	$target_img = gv_wm_load_image( $target_file, $target_info['mime'] );
	if ( ! $target_img ) { return false; }

	$logo_img0 = gv_wm_load_image( $logo_file, $logo_info['mime'] );
	if ( ! $logo_img0 ) { imagedestroy( $target_img ); return false; }

	$ratio    = max( 5, min( 60, (int) $settings['size_percent'] ) ) / 100;
	$new_l_w  = max( 1, (int) round( $t_w * $ratio ) );
	$new_l_h  = max( 1, (int) round( $l_h0 * ( $new_l_w / $l_w0 ) ) );

	if ( $new_l_w >= $t_w || $new_l_h >= $t_h ) {
		imagedestroy( $target_img ); imagedestroy( $logo_img0 );
		return false;
	}

	$logo_resized = imagecreatetruecolor( $new_l_w, $new_l_h );
	imagealphablending( $logo_resized, false );
	imagesavealpha( $logo_resized, true );
	$transparent = imagecolorallocatealpha( $logo_resized, 0, 0, 0, 127 );
	imagefilledrectangle( $logo_resized, 0, 0, $new_l_w, $new_l_h, $transparent );
	imagecopyresampled( $logo_resized, $logo_img0, 0, 0, 0, 0, $new_l_w, $new_l_h, $l_w0, $l_h0 );
	imagedestroy( $logo_img0 );

	$margin = (int) round( $t_w * ( max( 0, min( 20, (int) $settings['margin_percent'] ) ) / 100 ) );
	switch ( $settings['position'] ) {
		case 'bottom-left':
			$dst_x = $margin; $dst_y = $t_h - $new_l_h - $margin; break;
		case 'top-right':
			$dst_x = $t_w - $new_l_w - $margin; $dst_y = $margin; break;
		case 'top-left':
			$dst_x = $margin; $dst_y = $margin; break;
		case 'center':
			$dst_x = (int) round( ( $t_w - $new_l_w ) / 2 ); $dst_y = (int) round( ( $t_h - $new_l_h ) / 2 ); break;
		case 'bottom-right':
		default:
			$dst_x = $t_w - $new_l_w - $margin; $dst_y = $t_h - $new_l_h - $margin; break;
	}
	$dst_x = max( 0, min( $dst_x, $t_w - $new_l_w ) );
	$dst_y = max( 0, min( $dst_y, $t_h - $new_l_h ) );

	$opacity = max( 5, min( 100, (int) $settings['opacity'] ) );
	gv_wm_scale_alpha( $logo_resized, $opacity );

	imagealphablending( $target_img, true );
	imagesavealpha( $target_img, true );
	imagecopy( $target_img, $logo_resized, $dst_x, $dst_y, 0, 0, $new_l_w, $new_l_h );
	imagedestroy( $logo_resized );

	$saved = gv_wm_save_image( $target_img, $target_file, $target_info['mime'] );
	imagedestroy( $target_img );

	return (bool) $saved;
}

/* ==========================================================================
   ۶) مسیر فایل لوگوی واترمارک
   ========================================================================== */
function gv_wm_get_logo_path( $settings ) {
	$logo_id = (int) $settings['logo_id'];
	if ( ! $logo_id ) { return false; }
	$path = get_attached_file( $logo_id );
	if ( ! $path || ! file_exists( $path ) ) { return false; }
	return $path;
}

/* ==========================================================================
   ۷) واترمارک‌زدن به یک پیوست
   $force = true  → انتخاب دستیِ ادمین (نادیده‌گرفتن پرچم «معاف» و وضعیت «فعال بودن خودکار»)
   ========================================================================== */
function gv_wm_apply_to_attachment( $attachment_id, $metadata = null, $force = false ) {
	$attachment_id = (int) $attachment_id;

	if ( gv_wm_processing_locked() ) { return false; }
	if ( get_post_meta( $attachment_id, GV_WM_META_FLAG, true ) ) { return false; }
	if ( ! $force && get_post_meta( $attachment_id, GV_WM_META_SKIP, true ) ) { return false; }

	if ( ! wp_attachment_is_image( $attachment_id ) ) { return false; }

	$mime = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) { return false; }

	$s = gv_wm_get_settings();
	if ( gv_wm_is_excluded_attachment( $attachment_id, $s ) ) { return false; }

	$logo_path = gv_wm_get_logo_path( $s );
	if ( ! $logo_path ) { return false; }

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) { return false; }

	$size_info = @getimagesize( $file );
	if ( ! $size_info || $size_info[0] < max( 50, intval( $s['min_width'] ) ) ) { return false; }

	$backup_dir  = gv_wm_backup_dir();
	$backup_name = $attachment_id . '-' . wp_unique_filename( $backup_dir, basename( $file ) );
	$backup_path = trailingslashit( $backup_dir ) . $backup_name;
	if ( ! @copy( $file, $backup_path ) ) { return false; }

	$stamped_main = gv_wm_composite_and_save( $file, $logo_path, $s );

	if ( ! $stamped_main ) {
		@unlink( $backup_path );
		return false;
	}

	if ( ! empty( $s['apply_to_sizes'] ) ) {
		if ( null === $metadata ) { $metadata = wp_get_attachment_metadata( $attachment_id ); }
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$base_dir = trailingslashit( dirname( $file ) );
			foreach ( $metadata['sizes'] as $size_data ) {
				if ( empty( $size_data['file'] ) ) { continue; }
				$size_file = $base_dir . $size_data['file'];
				if ( file_exists( $size_file ) && $size_file !== $file ) {
					gv_wm_composite_and_save( $size_file, $logo_path, $s );
				}
			}
		}
	}

	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'gv_watermark_log', array(
		'attachment_id' => $attachment_id,
		'file_name'     => basename( $file ),
		'backup_path'   => $backup_path,
		'applied_at'    => current_time( 'mysql' ),
		'status'        => 'watermarked',
	) );

	update_post_meta( $attachment_id, GV_WM_META_FLAG, 1 );
	if ( $force ) { delete_post_meta( $attachment_id, GV_WM_META_SKIP ); }
	clearstatcache();

	return true;
}

/* دسته‌ی یک آپلود جدید بر اساس صفحه‌ای که در آن آپلود شده (والد پیوست) */
function gv_wm_upload_category( $attachment_id ) {
	$parent = (int) wp_get_post_parent_id( $attachment_id );
	if ( ! $parent ) { return 'other'; }
	$p = get_post( $parent );
	if ( ! $p ) { return 'other'; }
	$c = gv_wm_post_category( $p );
	return in_array( $c, array( 'shop', 'blog', 'page' ), true ) ? $c : 'other';
}

/* هوک اصلی: آپلود جدید — فقط در حالت «create» و فقط برای دسته‌های انتخاب‌شده */
add_filter( 'wp_generate_attachment_metadata', 'gv_wm_handle_new_upload', 30, 3 );
function gv_wm_handle_new_upload( $metadata, $attachment_id, $context = 'create' ) {
	if ( 'update' === $context ) { return $metadata; }
	$s = gv_wm_get_settings();
	if ( empty( $s['enabled'] ) ) { return $metadata; }
	if ( ! in_array( gv_wm_upload_category( $attachment_id ), $s['auto_cats'], true ) ) { return $metadata; }
	gv_wm_apply_to_attachment( $attachment_id, $metadata );
	return $metadata;
}

/* ==========================================================================
   ۸) بازگرداندن به نسخه‌ی اصلی
   $mark_skip = true → علامت «معاف» می‌خورد تا اعمال گروهی/خودکار دوباره روی آن اجرا نشود
   ========================================================================== */
function gv_wm_restore_attachment( $attachment_id, $mark_skip = false ) {
	global $wpdb;
	$attachment_id = (int) $attachment_id;

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_watermark_log WHERE attachment_id = %d AND status = 'watermarked' ORDER BY id DESC LIMIT 1",
		$attachment_id
	) );
	if ( ! $row || ! $row->backup_path || ! file_exists( $row->backup_path ) ) { return false; }

	$file = get_attached_file( $attachment_id );
	if ( ! $file ) { return false; }

	if ( ! @copy( $row->backup_path, $file ) ) { return false; }

	gv_wm_processing_locked( true );
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$new_metadata = wp_generate_attachment_metadata( $attachment_id, $file );
	if ( $new_metadata && ! is_wp_error( $new_metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $new_metadata );
	}
	gv_wm_processing_locked( false );

	delete_post_meta( $attachment_id, GV_WM_META_FLAG );
	if ( $mark_skip ) { update_post_meta( $attachment_id, GV_WM_META_SKIP, 1 ); }

	$wpdb->update( $wpdb->prefix . 'gv_watermark_log',
		array( 'status' => 'restored', 'restored_at' => current_time( 'mysql' ) ),
		array( 'id' => $row->id )
	);

	if ( file_exists( $row->backup_path ) ) { wp_delete_file( $row->backup_path ); }

	return true;
}

/* حذف نسخه‌ی پشتیبان بدون بازگرداندن (فقط آزادکردن فضا) */
function gv_wm_delete_backup_for_attachment( $attachment_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_watermark_log WHERE attachment_id = %d AND status = 'watermarked'", (int) $attachment_id
	) );
	if ( ! $rows ) { return false; }
	foreach ( $rows as $row ) {
		if ( $row->backup_path && file_exists( $row->backup_path ) ) { wp_delete_file( $row->backup_path ); }
		$wpdb->update( $wpdb->prefix . 'gv_watermark_log', array( 'status' => 'backup_deleted' ), array( 'id' => $row->id ) );
	}
	return true;
}

/* ==========================================================================
   ۹) محافظ: عکسی که بعداً لوگو/آیکون سایت شود، خودکار تمیز می‌شود
   ========================================================================== */
add_action( 'set_theme_mod_custom_logo', 'gv_wm_maybe_unwatermark_new_logo' );
function gv_wm_maybe_unwatermark_new_logo( $value ) {
	$id = (int) ( is_array( $value ) ? reset( $value ) : $value );
	if ( $id > 0 ) { gv_wm_restore_attachment( $id ); }
	return $value;
}

add_action( 'update_option_site_icon', 'gv_wm_maybe_unwatermark_option_logo', 10, 2 );
add_action( 'update_option_site_logo', 'gv_wm_maybe_unwatermark_option_logo', 10, 2 );
function gv_wm_maybe_unwatermark_option_logo( $old_value, $new_value ) {
	$id = (int) $new_value;
	if ( $id > 0 ) { gv_wm_restore_attachment( $id ); }
}

/* ==========================================================================
   ۱۰) ذخیره‌ی تنظیمات
   ========================================================================== */
add_action( 'admin_post_gv_wm_save_settings', 'gv_wm_save_settings' );
function gv_wm_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_WM_NONCE );

	$valid_positions = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left', 'center' );
	$position = isset( $_POST['position'] ) ? sanitize_key( $_POST['position'] ) : 'bottom-right';
	if ( ! in_array( $position, $valid_positions, true ) ) { $position = 'bottom-right'; }

	$auto_cats = array();
	foreach ( (array) ( $_POST['auto_cats'] ?? array() ) as $c ) {
		$c = sanitize_key( $c );
		if ( in_array( $c, array( 'shop', 'blog', 'page', 'other' ), true ) ) { $auto_cats[] = $c; }
	}

	$old_settings = gv_wm_get_settings();
	$new_logo_id  = intval( $_POST['logo_id'] ?? 0 );

	$settings = array(
		'enabled'        => isset( $_POST['enabled'] ) ? 1 : 0,
		'logo_id'        => $new_logo_id,
		'position'       => $position,
		'size_percent'   => max( 5, min( 40, intval( $_POST['size_percent'] ?? 18 ) ) ),
		'opacity'        => max( 10, min( 90, intval( $_POST['opacity'] ?? 45 ) ) ),
		'margin_percent' => max( 0, min( 15, intval( $_POST['margin_percent'] ?? 3 ) ) ),
		'min_width'      => max( 50, min( 3000, intval( $_POST['min_width'] ?? 300 ) ) ),
		'apply_to_sizes' => isset( $_POST['apply_to_sizes'] ) ? 1 : 0,
		'auto_cats'      => $auto_cats,
	);

	update_option( GV_WM_OPT, $settings );

	if ( $new_logo_id && $new_logo_id !== (int) $old_settings['logo_id'] ) {
		gv_wm_restore_attachment( $new_logo_id );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_WM_PAGE_SLUG . '&tab=settings&updated=1' ) );
	exit;
}

/* ==========================================================================
   ۱۱) ایندکس «محل استفاده‌ی تصاویر» — اسکن دستی و مرحله‌ای
   ========================================================================== */
function gv_wm_path_key( $rel ) {
	$rel = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
	$rel = rawurldecode( $rel );
	$rel = preg_replace( '/\.[a-z0-9]+$/i', '', $rel );
	$rel = preg_replace( '/(?:-scaled|-\d+x\d+)+$/i', '', $rel );
	return strtolower( $rel );
}

/* دسته‌ی یک پست (برای هدر/فوتر: تمپلیت‌های المنتور، Header Footer Elementor، پارت‌های قالب بلوکی) */
function gv_wm_post_category( $p ) {
	$type = $p->post_type;
	$name = isset( $p->post_name ) ? (string) $p->post_name : '';
	$id   = (int) $p->ID;

	switch ( $type ) {
		case 'product':
		case 'product_variation':
			return 'shop';
		case 'post':
			return 'blog';
		case 'page':
			return 'page';
		case 'elementor_library':
			$t = (string) get_post_meta( $id, '_elementor_template_type', true );
			if ( 'header' === $t ) { return 'header'; }
			if ( 'footer' === $t ) { return 'footer'; }
			return 'other';
		case 'elementor-hf':
			$t = (string) get_post_meta( $id, 'ehf_template_type', true );
			if ( stripos( $t, 'header' ) !== false ) { return 'header'; }
			if ( stripos( $t, 'footer' ) !== false ) { return 'footer'; }
			return 'other';
		case 'wp_template_part':
		case 'wp_template':
			if ( stripos( $name, 'footer' ) !== false ) { return 'footer'; }
			if ( stripos( $name, 'header' ) !== false ) { return 'header'; }
			return 'other';
	}
	return 'other';
}

function gv_wm_area_category( $name ) {
	$n = strtolower( (string) $name );
	if ( strpos( $n, 'footer' ) !== false ) { return 'footer'; }
	if ( strpos( $n, 'header' ) !== false || strpos( $n, 'topbar' ) !== false || strpos( $n, 'top-bar' ) !== false ) { return 'header'; }
	return 'widget';
}

/* استخراج شناسه‌ی تصاویر از یک متن (HTML / بلوک‌های گوتنبرگ / JSON المنتور / شورت‌کد گالری) */
function gv_wm_extract_ids_from_text( $text, $urlmap ) {
	$found = array();
	if ( ! is_string( $text ) || '' === $text ) { return $found; }
	$text = str_replace( '\\/', '/', $text );

	if ( preg_match_all( '/wp-image-(\d+)/', $text, $m ) ) {
		foreach ( $m[1] as $v ) { $found[ (int) $v ] = 1; }
	}
	if ( preg_match_all( '/<!--\s*wp:[a-z0-9\/-]+\s+\{[^}]*?"(?:id|mediaId|imageId)"\s*:\s*(\d+)/i', $text, $m ) ) {
		foreach ( $m[1] as $v ) { $found[ (int) $v ] = 1; }
	}
	if ( preg_match_all( '/\[gallery[^\]]*?ids=["\']([\d,\s]+)["\']/i', $text, $m ) ) {
		foreach ( $m[1] as $list ) {
			foreach ( preg_split( '/[,\s]+/', $list, -1, PREG_SPLIT_NO_EMPTY ) as $v ) { $found[ (int) $v ] = 1; }
		}
	}
	if ( preg_match_all( '#/uploads/([^"\'\s\)\\\\<>,]+?)\.(?:jpe?g|png|webp)#i', $text, $m ) ) {
		foreach ( $m[1] as $p ) {
			$k = gv_wm_path_key( $p );
			if ( isset( $urlmap[ $k ] ) ) { $found[ $urlmap[ $k ] ] = 1; }
		}
	}
	return array_keys( $found );
}

function gv_wm_walk_flat( $val, $path, &$out ) {
	if ( is_array( $val ) ) {
		foreach ( $val as $k => $v ) { gv_wm_walk_flat( $v, $path . '/' . $k, $out ); }
		return;
	}
	if ( is_object( $val ) ) { return; }
	$out[] = array( $path, $val );
}

function gv_wm_collect_id_keys( $arr, &$out ) {
	if ( ! is_array( $arr ) ) { return; }
	foreach ( $arr as $k => $v ) {
		if ( is_array( $v ) ) { gv_wm_collect_id_keys( $v, $out ); continue; }
		if ( is_scalar( $v ) && is_string( $k ) && preg_match( '/^(?:attachment_id|image_id|media_id|attachment|image)$/i', $k ) && is_numeric( $v ) && (int) $v > 0 ) {
			$out[] = (int) $v;
		}
	}
}

/* افزودن یک مورد به نقشه‌ی اسکن */
function gv_wm_scan_add( &$st, $aid, $cat, $ref ) {
	$aid = (int) $aid;
	if ( $aid <= 0 || ! isset( $st['ids'][ $aid ] ) ) { return; }
	if ( ! isset( $st['map'][ $aid ] ) ) { $st['map'][ $aid ] = array( 'c' => array(), 'r' => array() ); }
	if ( ! in_array( $cat, $st['map'][ $aid ]['c'], true ) ) { $st['map'][ $aid ]['c'][] = $cat; }
	if ( $ref && count( $st['map'][ $aid ]['r'] ) < 3 && ! in_array( $ref, $st['map'][ $aid ]['r'], true ) ) { $st['map'][ $aid ]['r'][] = $ref; }
}

/* اسکن مرحله‌ای: init → posts (چندبار) → final → done
   خروجی: array( 'phase' => مرحله‌ی بعدی, 'done' => n, 'total' => n ) */
function gv_wm_scan_step( $phase ) {
	global $wpdb;
	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 120 ); }
	if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }

	$skip_types  = array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_global_styles', 'shop_order', 'shop_order_placehold', 'shop_coupon', 'scheduled-action', 'wp_font_face', 'wp_font_family' );
	$in          = "'" . implode( "','", array_map( 'esc_sql', $skip_types ) ) . "'";
	$where_posts = "post_status IN ('publish','private','draft','future','pending') AND post_type NOT IN ($in)";

	/* ---- مرحله‌ی ۱: لیست تصاویر ---- */
	if ( 'init' === $phase ) {
		$att = $wpdb->get_results(
			"SELECT p.ID, p.post_parent, pm.meta_value AS rel
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
			 WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/jpeg','image/png','image/webp')"
		);
		$st = array( 'ids' => array(), 'urlmap' => array(), 'by_parent' => array(), 'map' => array(), 'last' => 0, 'done' => 0, 'total' => 0 );
		foreach ( (array) $att as $r ) {
			$aid = (int) $r->ID;
			$st['ids'][ $aid ] = true;
			if ( $r->rel ) { $st['urlmap'][ gv_wm_path_key( $r->rel ) ] = $aid; }
			if ( (int) $r->post_parent > 0 ) { $st['by_parent'][ (int) $r->post_parent ][] = $aid; }
		}
		$st['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE $where_posts" );
		update_option( GV_WM_SCAN_TMP, $st, false );
		return array( 'phase' => 'posts', 'done' => 0, 'total' => $st['total'] );
	}

	$st = get_option( GV_WM_SCAN_TMP );
	if ( ! is_array( $st ) ) { return array( 'phase' => 'init', 'done' => 0, 'total' => 0 ); }

	/* ---- مرحله‌ی ۲: محتوا، تصویر شاخص، گالری، المنتور (۱۵۰ تایی) ---- */
	if ( 'posts' === $phase ) {
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_type, post_name, post_content FROM {$wpdb->posts}
			 WHERE ID > %d AND $where_posts ORDER BY ID ASC LIMIT 150",
			(int) $st['last']
		) );
		if ( ! $posts ) {
			return array( 'phase' => 'final', 'done' => (int) $st['total'], 'total' => (int) $st['total'] );
		}

		$idlist = implode( ',', array_map( 'intval', wp_list_pluck( $posts, 'ID' ) ) );
		$metas  = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE post_id IN ($idlist) AND meta_key IN ('_thumbnail_id','_product_image_gallery','_elementor_data')"
		);
		$meta_by_post = array();
		foreach ( (array) $metas as $m ) { $meta_by_post[ (int) $m->post_id ][] = $m; }

		foreach ( $posts as $p ) {
			$st['last'] = (int) $p->ID;
			$st['done']++;
			$cat = gv_wm_post_category( $p );
			$ref = 'p:' . $st['last'];

			foreach ( gv_wm_extract_ids_from_text( $p->post_content, $st['urlmap'] ) as $aid ) { gv_wm_scan_add( $st, $aid, $cat, $ref ); }

			if ( ! empty( $meta_by_post[ $st['last'] ] ) ) {
				foreach ( $meta_by_post[ $st['last'] ] as $m ) {
					if ( '_thumbnail_id' === $m->meta_key ) {
						gv_wm_scan_add( $st, $m->meta_value, $cat, $ref );
					} elseif ( '_product_image_gallery' === $m->meta_key ) {
						foreach ( preg_split( '/[,\s]+/', (string) $m->meta_value, -1, PREG_SPLIT_NO_EMPTY ) as $v ) { gv_wm_scan_add( $st, $v, $cat, $ref ); }
					} elseif ( '_elementor_data' === $m->meta_key ) {
						foreach ( gv_wm_extract_ids_from_text( (string) $m->meta_value, $st['urlmap'] ) as $aid ) { gv_wm_scan_add( $st, $aid, $cat, $ref ); }
					}
				}
			}
		}
		update_option( GV_WM_SCAN_TMP, $st, false );
		return array( 'phase' => 'posts', 'done' => (int) $st['done'], 'total' => max( (int) $st['total'], (int) $st['done'] ) );
	}

	/* ---- مرحله‌ی ۳: والد پیوست، دسته‌بندی‌ها، قالب، ویجت‌ها ---- */
	if ( 'final' === $phase ) {
		foreach ( array_chunk( array_keys( $st['by_parent'] ), 500 ) as $chunk ) {
			$in_ids = implode( ',', array_map( 'intval', $chunk ) );
			$pp = $wpdb->get_results( "SELECT ID, post_type, post_name FROM {$wpdb->posts} WHERE ID IN ($in_ids)", OBJECT_K );
			foreach ( $chunk as $par ) {
				if ( ! isset( $pp[ $par ] ) ) { continue; }
				$cat = gv_wm_post_category( $pp[ $par ] );
				foreach ( $st['by_parent'][ $par ] as $aid ) { gv_wm_scan_add( $st, $aid, $cat, 'p:' . $par ); }
			}
		}

		$terms = $wpdb->get_results(
			"SELECT tm.meta_value AS v, tt.taxonomy AS tax
			 FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
			 WHERE tm.meta_key = 'thumbnail_id'"
		);
		foreach ( (array) $terms as $t ) {
			if ( 0 === strpos( $t->tax, 'product' ) || 0 === strpos( $t->tax, 'pa_' ) ) { $cat = 'shop'; $lbl = 'x:تصویر دسته‌بندی/برند محصولات'; }
			elseif ( in_array( $t->tax, array( 'category', 'post_tag' ), true ) ) { $cat = 'blog'; $lbl = 'x:تصویر دسته‌بندی وبلاگ'; }
			else { $cat = 'other'; $lbl = 'x:تصویر یک طبقه‌بندی'; }
			gv_wm_scan_add( $st, $t->v, $cat, $lbl );
		}

		$mods = get_theme_mods();
		$flat = array();
		gv_wm_walk_flat( is_array( $mods ) ? $mods : array(), '', $flat );
		foreach ( $flat as $pair ) {
			list( $path, $val ) = $pair;
			$lp = strtolower( $path );
			if ( strpos( $lp, 'footer' ) !== false ) { $cat = 'footer'; }
			elseif ( preg_match( '/header|logo|menu|nav|topbar|sticky|mobile/', $lp ) ) { $cat = 'header'; }
			else { $cat = 'theme'; }
			$ref = 'x:تنظیمات قالب (' . trim( wp_html_excerpt( $path, 36, '…' ), '/' ) . ')';

			if ( is_numeric( $val ) && (int) $val > 0 && preg_match( '/logo|image|img|bg|background|banner|icon|photo|thumb|attachment/i', $path ) ) {
				gv_wm_scan_add( $st, $val, $cat, $ref );
			} elseif ( is_string( $val ) && strpos( $val, '/uploads/' ) !== false ) {
				foreach ( gv_wm_extract_ids_from_text( $val, $st['urlmap'] ) as $aid ) { gv_wm_scan_add( $st, $aid, $cat, $ref ); }
			}
		}
		gv_wm_scan_add( $st, get_option( 'site_logo' ), 'header', 'x:لوگوی سایت' );
		gv_wm_scan_add( $st, get_option( 'site_icon' ), 'theme', 'x:آیکون سایت (فاوآیکن)' );

		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( is_array( $sidebars ) ) {
			foreach ( $sidebars as $sid => $wids ) {
				if ( ! is_array( $wids ) || 'wp_inactive_widgets' === $sid || 'array_version' === $sid ) { continue; }
				$cat = gv_wm_area_category( $sid );
				foreach ( $wids as $wid ) {
					if ( ! preg_match( '/^(.+)-(\d+)$/', $wid, $mm ) ) { continue; }
					$opt = get_option( 'widget_' . $mm[1] );
					if ( ! is_array( $opt ) || ! isset( $opt[ $mm[2] ] ) ) { continue; }
					$inst = $opt[ $mm[2] ];
					$ref  = 'x:ویجت (' . $sid . ')';
					$blob = wp_json_encode( $inst, JSON_UNESCAPED_SLASHES );
					foreach ( gv_wm_extract_ids_from_text( (string) $blob, $st['urlmap'] ) as $aid ) { gv_wm_scan_add( $st, $aid, $cat, $ref ); }
					$kid = array();
					gv_wm_collect_id_keys( $inst, $kid );
					foreach ( $kid as $aid ) { gv_wm_scan_add( $st, $aid, $cat, $ref ); }
				}
			}
		}

		update_option( GV_WM_USAGE_OPT, array( 'built' => time(), 'map' => $st['map'] ), false );
		delete_option( GV_WM_SCAN_TMP );
		return array( 'phase' => 'done', 'done' => (int) $st['total'], 'total' => (int) $st['total'] );
	}

	return array( 'phase' => 'init', 'done' => 0, 'total' => 0 );
}

/* AJAX اسکن */
add_action( 'wp_ajax_gv_wm_scan', 'gv_wm_ajax_scan' );
function gv_wm_ajax_scan() {
	check_ajax_referer( GV_WM_NONCE, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
	$phase = isset( $_POST['phase'] ) ? sanitize_key( $_POST['phase'] ) : 'init';
	if ( ! in_array( $phase, array( 'init', 'posts', 'final' ), true ) ) { $phase = 'init'; }
	wp_send_json_success( gv_wm_scan_step( $phase ) );
}

/* هرگز اسکن خودکار انجام نمی‌شود؛ اگر ایندکس نباشد built = 0 برمی‌گردد */
function gv_wm_get_usage_index() {
	$idx = get_option( GV_WM_USAGE_OPT );
	if ( ! is_array( $idx ) || ! isset( $idx['map'] ) ) {
		return array( 'built' => 0, 'map' => array() );
	}
	return $idx;
}

/* ==========================================================================
   ۱۲) کوئری، فیلتر و مرتب‌سازی کتابخانه‌ی تصاویر
   ========================================================================== */
function gv_wm_status_sets() {
	global $wpdb;
	$flagged = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", GV_WM_META_FLAG ) );
	$skip    = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", GV_WM_META_SKIP ) );
	$restor  = $wpdb->get_col( "SELECT DISTINCT attachment_id FROM {$wpdb->prefix}gv_watermark_log WHERE status = 'watermarked'" );
	return array(
		'flagged'     => array_fill_keys( array_map( 'intval', $flagged ), true ),
		'skip'        => array_fill_keys( array_map( 'intval', $skip ), true ),
		'restorable'  => array_fill_keys( array_map( 'intval', $restor ), true ),
	);
}

function gv_wm_parse_filters( $src ) {
	$defs = gv_wm_categories();
	$cats = array();
	if ( ! empty( $src['cats'] ) ) {
		foreach ( (array) $src['cats'] as $c ) {
			$c = sanitize_key( $c );
			if ( isset( $defs[ $c ] ) ) { $cats[] = $c; }
		}
	}
	$status = isset( $src['status'] ) ? sanitize_key( $src['status'] ) : 'all';
	if ( ! in_array( $status, array( 'all', 'clean', 'marked', 'exempt' ), true ) ) { $status = 'all'; }
	$sort = isset( $src['sort'] ) ? sanitize_key( $src['sort'] ) : 'new';
	if ( ! in_array( $sort, array( 'new', 'old', 'name', 'cat', 'status' ), true ) ) { $sort = 'new'; }
	$pp = isset( $src['pp'] ) ? (int) $src['pp'] : 24;
	if ( ! in_array( $pp, array( 24, 48, 96 ), true ) ) { $pp = 24; }

	return array(
		'cats'   => $cats,
		'status' => $status,
		'sort'   => $sort,
		'pp'     => $pp,
		's'      => isset( $src['s'] ) ? sanitize_text_field( $src['s'] ) : '',
	);
}

function gv_wm_load_rows( $search = '' ) {
	static $cache = array();
	if ( isset( $cache[ $search ] ) ) { return $cache[ $search ]; }
	global $wpdb;

	$sql = "SELECT ID, post_title, post_parent FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg','image/png','image/webp')";
	if ( '' !== $search ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$sql .= $wpdb->prepare( ' AND (post_title LIKE %s OR guid LIKE %s)', $like, $like );
	}
	$sql .= ' ORDER BY ID DESC';

	$idx  = gv_wm_get_usage_index();
	$map  = $idx['map'];
	$rows = array();
	foreach ( (array) $wpdb->get_results( $sql ) as $r ) {
		$id = (int) $r->ID;
		$rows[] = array(
			'id'    => $id,
			'title' => $r->post_title,
			'cats'  => ! empty( $map[ $id ]['c'] ) ? $map[ $id ]['c'] : array( 'unused' ),
			'refs'  => ! empty( $map[ $id ]['r'] ) ? $map[ $id ]['r'] : array(),
		);
	}
	$cache[ $search ] = $rows;
	return $rows;
}

function gv_wm_filter_rows( $rows, $f, $sets, $excluded ) {
	$out = array();
	foreach ( $rows as $r ) {
		$id = $r['id'];
		if ( $f['cats'] && ! array_intersect( $f['cats'], $r['cats'] ) ) { continue; }
		switch ( $f['status'] ) {
			case 'clean':  if ( isset( $sets['flagged'][ $id ] ) || isset( $excluded[ $id ] ) ) { continue 2; } break;
			case 'marked': if ( ! isset( $sets['flagged'][ $id ] ) ) { continue 2; } break;
			case 'exempt': if ( ! isset( $sets['skip'][ $id ] ) ) { continue 2; } break;
		}
		$out[] = $r;
	}

	$order = array_flip( array_keys( gv_wm_categories() ) );
	$prim  = function ( $r ) use ( $order ) {
		$best = 99;
		foreach ( $r['cats'] as $c ) { if ( isset( $order[ $c ] ) && $order[ $c ] < $best ) { $best = $order[ $c ]; } }
		return $best;
	};

	switch ( $f['sort'] ) {
		case 'old':
			usort( $out, function ( $a, $b ) { return $a['id'] - $b['id']; } );
			break;
		case 'name':
			usort( $out, function ( $a, $b ) { return strnatcasecmp( $a['title'], $b['title'] ); } );
			break;
		case 'cat':
			usort( $out, function ( $a, $b ) use ( $prim ) {
				$d = $prim( $a ) - $prim( $b );
				return $d ? $d : ( $b['id'] - $a['id'] );
			} );
			break;
		case 'status':
			usort( $out, function ( $a, $b ) use ( $sets ) {
				$d = (int) isset( $sets['flagged'][ $b['id'] ] ) - (int) isset( $sets['flagged'][ $a['id'] ] );
				return $d ? $d : ( $b['id'] - $a['id'] );
			} );
			break;
		default:
			break; // new: همان ترتیب ID نزولی
	}
	return $out;
}

/* ==========================================================================
   ۱۳) AJAX — دریافت شناسه‌ها و پردازش مرحله‌ایِ عملیات
   ========================================================================== */
add_action( 'wp_ajax_gv_wm_get_ids', 'gv_wm_ajax_get_ids' );
function gv_wm_ajax_get_ids() {
	check_ajax_referer( GV_WM_NONCE, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

	$scope = isset( $_POST['scope'] ) ? sanitize_key( $_POST['scope'] ) : '';
	$op    = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : 'apply';
	if ( ! in_array( $op, array( 'apply', 'restore', 'delete_backup' ), true ) ) { wp_send_json_error(); }

	$s        = gv_wm_get_settings();
	$sets     = gv_wm_status_sets();
	$excluded = gv_wm_excluded_ids( $s );
	$ids      = array();

	switch ( $scope ) {
		case 'filtered':
			$f    = gv_wm_parse_filters( wp_unslash( $_POST['filters'] ?? array() ) );
			$rows = gv_wm_filter_rows( gv_wm_load_rows( $f['s'] ), $f, $sets, $excluded );
			foreach ( $rows as $r ) {
				$id = $r['id'];
				if ( 'apply' === $op ) {
					if ( isset( $sets['flagged'][ $id ] ) || isset( $excluded[ $id ] ) ) { continue; }
					if ( 'exempt' !== $f['status'] && isset( $sets['skip'][ $id ] ) ) { continue; }
					$ids[] = $id;
				} elseif ( isset( $sets['restorable'][ $id ] ) ) {
					$ids[] = $id;
				}
			}
			break;

		case 'all_clean':
			foreach ( gv_wm_load_rows( '' ) as $r ) {
				$id = $r['id'];
				if ( isset( $sets['flagged'][ $id ] ) || isset( $excluded[ $id ] ) || isset( $sets['skip'][ $id ] ) ) { continue; }
				$ids[] = $id;
			}
			break;

		case 'restore_all':
		case 'delete_all_backups':
			$ids = array_keys( $sets['restorable'] );
			break;

		default:
			wp_send_json_error();
	}

	wp_send_json_success( array( 'ids' => array_values( array_map( 'intval', $ids ) ) ) );
}

add_action( 'wp_ajax_gv_wm_process', 'gv_wm_ajax_process' );
function gv_wm_ajax_process() {
	check_ajax_referer( GV_WM_NONCE, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 120 ); }
	if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'image' ); }

	$op = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	if ( ! in_array( $op, array( 'apply', 'restore', 'delete_backup' ), true ) ) { wp_send_json_error(); }

	$ids = array_slice( array_filter( array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) ) ), 0, 40 );
	$mark_skip = ! empty( $_POST['mark_skip'] );

	$ok = 0; $fail = 0;
	foreach ( $ids as $id ) {
		if ( 'apply' === $op )              { $res = gv_wm_apply_to_attachment( $id, null, true ); }
		elseif ( 'restore' === $op )        { $res = gv_wm_restore_attachment( $id, $mark_skip ); }
		else                                { $res = gv_wm_delete_backup_for_attachment( $id ); }
		if ( $res ) { $ok++; } else { $fail++; }
	}
	wp_send_json_success( array( 'ok' => $ok, 'fail' => $fail ) );
}

/* ==========================================================================
   ۱۴) اگر پیوستی حذف شود، پشتیبانش هم پاک شود
   ========================================================================== */
add_action( 'delete_attachment', 'gv_wm_on_attachment_deleted' );
function gv_wm_on_attachment_deleted( $attachment_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_watermark_log WHERE attachment_id = %d AND status = 'watermarked'", $attachment_id
	) );
	foreach ( $rows as $row ) {
		if ( $row->backup_path && file_exists( $row->backup_path ) ) { wp_delete_file( $row->backup_path ); }
	}
	$wpdb->update( $wpdb->prefix . 'gv_watermark_log',
		array( 'status' => 'deleted' ),
		array( 'attachment_id' => $attachment_id, 'status' => 'watermarked' )
	);
}

/* ==========================================================================
   ۱۵) منو
   ========================================================================== */
add_action( 'admin_menu', 'gv_wm_admin_menu' );
function gv_wm_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'واترمارک تصاویر | Groot Vision',
		'💧 واترمارک تصاویر',
		'manage_options',
		GV_WM_PAGE_SLUG,
		'gv_wm_render_admin_page'
	);
}

/* ==========================================================================
   ۱۶) استایل و اسکریپت صفحه‌ی مدیریت
   ========================================================================== */
add_action( 'admin_enqueue_scripts', 'gv_wm_admin_assets' );
function gv_wm_admin_assets( $hook ) {
	if ( strpos( $hook, GV_WM_PAGE_SLUG ) === false ) { return; }
	wp_enqueue_media();

	$css = <<<'CSS'
.gvwm{--p:#0369a1;--p-d:#0c4a6e;--p-l:#e0f2fe;--bg:#f4f7fb;--card:#fff;--bd:#e5eaf1;--tx:#0f172a;--mu:#64748b;--ok:#059669;--wn:#d97706;--dg:#dc2626;
	font-family:'Vazirmatn',Tahoma,sans-serif;max-width:1240px;color:var(--tx);margin:18px 20px 40px 0;}
.gvwm *{box-sizing:border-box;}
.gvwm-hero{position:relative;overflow:hidden;background:linear-gradient(125deg,#0c4a6e 0%,#0369a1 55%,#0ea5e9 100%);color:#fff;border-radius:22px;padding:28px 32px;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;box-shadow:0 12px 30px -12px rgba(3,105,161,.55);}
.gvwm-hero:before{content:"";position:absolute;inset:auto auto -60px -40px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.08);}
.gvwm-hero:after{content:"";position:absolute;inset:-70px -30px auto auto;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.07);}
.gvwm-hero h1{margin:0 0 8px;font-size:22px;font-weight:800;color:#fff;position:relative;z-index:1;}
.gvwm-hero p{margin:0;font-size:13px;line-height:2;color:#e0f2fe;max-width:640px;position:relative;z-index:1;}
.gvwm-hero-side{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1;}
.gvwm-pill{background:rgba(255,255,255,.16);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:7px 16px;font-size:12px;font-weight:700;color:#fff;}
.gvwm-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:18px 0;}
@media(max-width:900px){.gvwm-stats{grid-template-columns:repeat(2,1fr);}}
.gvwm-stat{background:var(--card);border:1px solid var(--bd);border-radius:18px;padding:16px 18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 2px rgba(15,23,42,.03);}
.gvwm-stat i{font-style:normal;width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;background:var(--p-l);}
.gvwm-stat b{display:block;font-size:22px;line-height:1.2;color:var(--p-d);}
.gvwm-stat span{font-size:12px;color:var(--mu);}
.gvwm-tabs{display:flex;gap:6px;background:#e8eef6;padding:6px;border-radius:16px;width:fit-content;max-width:100%;overflow-x:auto;margin-bottom:18px;}
.gvwm-tab{padding:10px 20px;border-radius:12px;text-decoration:none;color:#475569;font-weight:700;font-size:13px;white-space:nowrap;transition:.15s;}
.gvwm-tab:hover{color:var(--p-d);}
.gvwm-tab.is-active{background:#fff;color:var(--p-d);box-shadow:0 2px 8px rgba(15,23,42,.08);}
.gvwm-card{background:var(--card);border:1px solid var(--bd);border-radius:20px;padding:22px 24px;margin-bottom:20px;box-shadow:0 1px 2px rgba(15,23,42,.03);}
.gvwm-card h2{margin:0 0 6px;font-size:15px;font-weight:800;}
.gvwm-card>.gvwm-sub{margin:0 0 16px;color:var(--mu);font-size:12.5px;line-height:1.9;}
.gvwm-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,var(--p-d),var(--p));color:#fff!important;border:none;padding:10px 20px;border-radius:12px;font-weight:700;cursor:pointer;text-decoration:none;font-size:13px;font-family:inherit;transition:.15s;box-shadow:0 6px 14px -8px rgba(3,105,161,.8);}
.gvwm-btn:hover{transform:translateY(-1px);filter:brightness(1.06);}
.gvwm-btn[disabled],.gvwm-btn.is-disabled{opacity:.45;pointer-events:none;box-shadow:none;}
.gvwm-btn-ghost{background:#f1f5f9;color:#0f172a!important;border:1px solid var(--bd);box-shadow:none;}
.gvwm-btn-warn{background:linear-gradient(135deg,#b45309,#f59e0b);box-shadow:0 6px 14px -8px rgba(217,119,6,.8);}
.gvwm-btn-danger{background:linear-gradient(135deg,#991b1b,#ef4444);box-shadow:0 6px 14px -8px rgba(220,38,38,.8);}
.gvwm-btn-light{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);box-shadow:none;}
.gvwm-btn-sm{padding:7px 13px;font-size:12px;border-radius:10px;}
.gvwm-notice{border-radius:14px;padding:12px 18px;margin-bottom:16px;font-size:13px;font-weight:600;}
.gvwm-notice.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;}
.gvwm-notice.wn{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}
/* فیلترها */
.gvwm-filter{background:var(--card);border:1px solid var(--bd);border-radius:20px;padding:18px 20px;margin-bottom:16px;}
.gvwm-filter-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.gvwm-filter-row+.gvwm-filter-row{margin-top:14px;}
.gvwm-input,.gvwm-select{border:1px solid var(--bd)!important;border-radius:12px!important;padding:8px 14px!important;min-height:40px;background:#f8fafc!important;font-family:inherit;font-size:13px;box-shadow:none!important;}
.gvwm-input{flex:1;min-width:200px;}
.gvwm-chips{display:flex;gap:8px;flex-wrap:wrap;}
.gvwm-chip{cursor:pointer;}
.gvwm-chip input{position:absolute;opacity:0;pointer-events:none;}
.gvwm-chip span{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:999px;border:1.5px solid var(--bd);background:#fff;font-size:12.5px;font-weight:700;color:#334155;transition:.15s;}
.gvwm-chip span em{font-style:normal;background:#f1f5f9;color:#475569;border-radius:999px;padding:1px 8px;font-size:11px;}
.gvwm-chip:hover span{border-color:var(--c);}
.gvwm-chip input:checked+span{background:var(--c);border-color:var(--c);color:#fff;box-shadow:0 6px 14px -8px var(--c);}
.gvwm-chip input:checked+span em{background:rgba(255,255,255,.25);color:#fff;}
/* نوار عملیات */
.gvwm-bar{position:sticky;bottom:14px;z-index:50;background:rgba(15,23,42,.94);backdrop-filter:blur(8px);color:#fff;border-radius:18px;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:16px 0;box-shadow:0 14px 34px -12px rgba(15,23,42,.6);}
.gvwm-bar-info{font-size:13px;font-weight:600;}
.gvwm-bar-info b{font-size:18px;color:#7dd3fc;margin-left:4px;}
.gvwm-bar-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.gvwm-bar .gvwm-btn-ghost{background:rgba(255,255,255,.1);color:#fff!important;border-color:rgba(255,255,255,.2);}
.gvwm-bar .gvwm-btn-ghost.is-active{background:#0ea5e9;border-color:#0ea5e9;}
/* شبکه‌ی تصاویر */
.gvwm-grid-imgs{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;}
.gvwm-item{background:var(--card);border:1.5px solid var(--bd);border-radius:18px;overflow:hidden;position:relative;transition:.15s;display:flex;flex-direction:column;}
.gvwm-item:hover{box-shadow:0 12px 26px -14px rgba(15,23,42,.35);transform:translateY(-2px);}
.gvwm-item.is-selected{border-color:var(--p);box-shadow:0 0 0 3px rgba(14,165,233,.25);}
.gvwm-thumb{position:relative;aspect-ratio:1/1;background:repeating-conic-gradient(#f1f5f9 0% 25%,#fff 0% 50%) 50%/18px 18px;cursor:pointer;overflow:hidden;}
.gvwm-thumb img{width:100%;height:100%;object-fit:contain;display:block;}
.gvwm-tick{position:absolute;top:10px;right:10px;z-index:3;}
.gvwm-tick input{position:absolute;opacity:0;}
.gvwm-tick span{display:flex;width:26px;height:26px;border-radius:9px;background:rgba(255,255,255,.92);border:2px solid #cbd5e1;align-items:center;justify-content:center;color:transparent;font-size:15px;font-weight:900;transition:.12s;}
.gvwm-tick input:checked+span{background:var(--p);border-color:var(--p);color:#fff;}
.gvwm-tick input:disabled+span{opacity:.35;}
.gvwm-state{position:absolute;left:8px;bottom:8px;font-size:11px;font-weight:800;border-radius:999px;padding:4px 11px;background:#fff;color:#475569;box-shadow:0 2px 8px rgba(0,0,0,.12);}
.gvwm-state.on{background:#0369a1;color:#fff;}
.gvwm-state.perm{background:#7c3aed;color:#fff;}
.gvwm-state.ex{background:#334155;color:#fff;}
.gvwm-state.skip{background:#fef3c7;color:#92400e;}
.gvwm-body{padding:12px 14px 6px;flex:1;}
.gvwm-name{font-size:12px;font-weight:700;direction:ltr;text-align:right;color:#1e293b;word-break:break-all;}
.gvwm-badges{display:flex;gap:5px;flex-wrap:wrap;margin:8px 0 6px;}
.gvwm-badge{font-size:10.5px;font-weight:800;border-radius:999px;padding:3px 9px;color:#fff;background:var(--c,#64748b);}
.gvwm-ref{font-size:11px;color:var(--mu);line-height:1.7;}
.gvwm-ref a{color:var(--p);text-decoration:none;}
.gvwm-actions{display:flex;gap:6px;padding:8px 14px 14px;}
.gvwm-actions .gvwm-btn{flex:1;justify-content:center;}
.gvwm-actions .gvwm-btn.gvwm-icon{flex:0 0 auto;}
.gvwm-empty{text-align:center;padding:60px 20px;color:var(--mu);background:var(--card);border:1px dashed var(--bd);border-radius:20px;}
.gvwm-pager{margin:22px 0 4px;display:flex;justify-content:center;gap:6px;flex-wrap:wrap;}
.gvwm-pager .page-numbers{min-width:38px;height:38px;padding:0 12px;border-radius:11px;border:1px solid var(--bd);background:#fff;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;color:#334155;font-weight:700;font-size:13px;}
.gvwm-pager .page-numbers.current{background:var(--p);border-color:var(--p);color:#fff;}
.gvwm-pager a.page-numbers:hover{border-color:var(--p);color:var(--p);}
/* تنظیمات */
.gvwm-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:20px;align-items:start;}
@media(max-width:1000px){.gvwm-grid{grid-template-columns:1fr;}}
.gvwm-field{margin-bottom:20px;}
.gvwm-field>label{display:block;font-weight:700;margin-bottom:8px;font-size:13px;}
.gvwm-hint{color:#94a3b8;font-size:11.5px;margin:6px 0 0;line-height:1.8;}
.gvwm-switch{display:flex;align-items:center;gap:10px;font-weight:700;font-size:13px;cursor:pointer;}
.gvwm-checks{display:flex;gap:10px;flex-wrap:wrap;}
.gvwm-checks label{display:inline-flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid var(--bd);border-radius:11px;padding:7px 12px;font-size:12.5px;font-weight:600;cursor:pointer;}
.gvwm-logo-picker{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.gvwm-logo-picker img{max-width:90px;max-height:90px;border:1px solid var(--bd);border-radius:14px;background:repeating-conic-gradient(#f1f5f9 0% 25%,#fff 0% 50%) 50%/16px 16px;padding:6px;}
.gvwm-positions{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;max-width:300px;}
.gvwm-positions label{display:flex;align-items:center;justify-content:center;gap:5px;background:#f8fafc;border:1px solid var(--bd);border-radius:11px;padding:9px 6px;font-size:12px;font-weight:600;cursor:pointer;}
.gvwm-range-row{display:flex;align-items:center;gap:12px;}
.gvwm-range-row input[type=range]{flex:1;}
.gvwm-range-row span{min-width:46px;text-align:left;font-weight:800;color:var(--p-d);font-size:12.5px;}
.gvwm-preview{position:sticky;top:44px;}
#gvwm-preview-box{position:relative;width:100%;aspect-ratio:4/3;border-radius:16px;overflow:hidden;background:linear-gradient(135deg,#cbd5e1,#94a3b8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;}
#gvwm-preview-logo{position:absolute;max-width:70%;display:none;}
/* ابزارها */
.gvwm-tools{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;}
.gvwm-tool-card{background:var(--card);border:1px solid var(--bd);border-radius:20px;padding:22px;display:flex;flex-direction:column;gap:12px;}
.gvwm-tool-card .ico{font-size:28px;}
.gvwm-tool-card h3{margin:0;font-size:14.5px;font-weight:800;}
.gvwm-tool-card p{margin:0;color:var(--mu);font-size:12.5px;line-height:1.95;flex:1;}
.gvwm-tool-card.danger{border-color:#fecaca;background:linear-gradient(180deg,#fff,#fff5f5);}
.gvwm-tool-card form{margin:0;}
/* مودال پیشرفت */
.gvwm-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:100000;display:none;align-items:center;justify-content:center;padding:20px;}
.gvwm-modal.is-open{display:flex;}
.gvwm-modal-card{background:#fff;border-radius:24px;padding:30px;width:100%;max-width:460px;text-align:center;direction:rtl;font-family:'Vazirmatn',Tahoma,sans-serif;box-shadow:0 30px 60px -20px rgba(0,0,0,.5);}
.gvwm-modal-card h3{margin:0 0 18px;font-size:17px;font-weight:800;}
.gvwm-progress{height:12px;background:#e2e8f0;border-radius:999px;overflow:hidden;}
.gvwm-progress div{height:100%;width:0;background:linear-gradient(90deg,#0369a1,#38bdf8);border-radius:999px;transition:width .25s;}
.gvwm-modal-text{margin:12px 0;font-size:13px;color:#475569;font-weight:600;}
.gvwm-modal-stats{display:flex;justify-content:center;gap:22px;margin-bottom:18px;font-size:13px;font-weight:700;}
.gvwm-modal-actions{display:flex;justify-content:center;gap:10px;}
.gvwm-footer{font-size:11.5px;color:#94a3b8;text-align:center;margin-top:28px;}
CSS;
	wp_register_style( 'gv-wm-admin', false );
	wp_enqueue_style( 'gv-wm-admin' );
	wp_add_inline_style( 'gv-wm-admin', $css );

	$js = <<<'JS'
jQuery(function($){
	var C = window.gvwmCfg || {};
	var fa = function(n){ return Number(n).toLocaleString('fa-IR'); };

	/* ---------- انتخاب لوگو از کتابخانه‌ی رسانه ---------- */
	$('#gvwm-pick-logo').on('click', function(e){
		e.preventDefault();
		var frame = wp.media({ title: 'انتخاب تصویر لوگو برای واترمارک', multiple: false, library: { type: 'image' } });
		frame.on('select', function(){
			var att = frame.state().get('selection').first().toJSON();
			$('#gvwm-logo-id').val(att.id);
			$('#gvwm-logo-preview').attr('src', att.url).show();
			$('#gvwm-preview-logo').attr('src', att.url).show();
			$('#gvwm-no-logo-msg').hide();
			updatePreview();
		});
		frame.open();
	});

	/* ---------- پیش‌نمایش زنده ---------- */
	function updatePreview(){
		if(!$('#gvwm-preview-box').length){ return; }
		var size = parseInt($('#gvwm-size').val(),10), margin = parseInt($('#gvwm-margin').val(),10), opacity = parseInt($('#gvwm-opacity').val(),10);
		var pos = $('input[name="position"]:checked').val();
		$('#gvwm-size-val').text(size+'%'); $('#gvwm-margin-val').text(margin+'%'); $('#gvwm-opacity-val').text(opacity+'%');
		var $box = $('#gvwm-preview-box'), $logo = $('#gvwm-preview-logo'), boxW = $box.width();
		$logo.css({ width:(boxW*size/100)+'px', height:'auto', opacity:opacity/100, top:'auto', bottom:'auto', left:'auto', right:'auto', transform:'none' });
		var m = (boxW*margin/100)+'px';
		if(pos==='bottom-right'){ $logo.css({bottom:m,right:m}); }
		else if(pos==='bottom-left'){ $logo.css({bottom:m,left:m}); }
		else if(pos==='top-right'){ $logo.css({top:m,right:m}); }
		else if(pos==='top-left'){ $logo.css({top:m,left:m}); }
		else { $logo.css({top:'50%',left:'50%',transform:'translate(-50%,-50%)'}); }
	}
	$('#gvwm-size, #gvwm-margin, #gvwm-opacity').on('input', updatePreview);
	$('input[name="position"]').on('change', updatePreview);
	updatePreview();

	/* ---------- فیلترها: ارسال خودکار ---------- */
	$('#gvwm-filter').on('change', '.gvwm-chip input, .gvwm-select', function(){ $('#gvwm-filter').trigger('submit'); });

	/* ---------- انتخاب تصاویر ---------- */
	var $lib = $('#gvwm-lib'), allFiltered = false;
	var filters = {};
	try { filters = JSON.parse($lib.attr('data-filters') || '{}'); } catch(e){}

	function selectedIds(){ return $('.gvwm-check:checked').map(function(){ return parseInt(this.value,10); }).get(); }
	function refreshSel(){
		$('.gvwm-item').each(function(){ $(this).toggleClass('is-selected', $(this).find('.gvwm-check').is(':checked')); });
		var total = parseInt($lib.attr('data-total'),10) || 0;
		var n = allFiltered ? total : selectedIds().length;
		$('#gvwm-sel-count').text(fa(n));
		$('#gvwm-do-apply, #gvwm-do-restore').prop('disabled', n === 0);
		$('#gvwm-select-all').toggleClass('is-active', allFiltered);
	}
	$(document).on('change', '.gvwm-check', function(){ allFiltered = false; refreshSel(); });
	$(document).on('click', '.gvwm-thumb', function(e){
		if($(e.target).closest('.gvwm-tick').length){ return; }
		var $c = $(this).closest('.gvwm-item').find('.gvwm-check');
		if($c.prop('disabled')){ return; }
		$c.prop('checked', !$c.prop('checked')).trigger('change');
	});
	$('#gvwm-select-page').on('click', function(){
		var $all = $('.gvwm-check:not(:disabled)'), everyOn = $all.length && $all.filter(':checked').length === $all.length;
		$all.prop('checked', !everyOn); allFiltered = false; refreshSel();
	});
	$('#gvwm-select-all').on('click', function(){
		allFiltered = !allFiltered;
		$('.gvwm-check:not(:disabled)').prop('checked', allFiltered);
		refreshSel();
	});
	$('#gvwm-clear').on('click', function(){ allFiltered = false; $('.gvwm-check').prop('checked', false); refreshSel(); });
	refreshSel();

	/* ---------- اجرای عملیات با نوار پیشرفت ---------- */
	var titles = { apply:'در حال اعمال واترمارک…', restore:'در حال بازگردانی تصاویر…', delete_backup:'در حال حذف نسخه‌های پشتیبان…' };

	function runJob(op, ids, markSkip){
		var total = ids.length, idx = 0, ok = 0, fail = 0, stop = false;
		var size = (op === 'delete_backup') ? 30 : 4;
		$('#gvwm-modal-title').text(titles[op]);
		$('.gvwm-modal-stats').show(); $('#gvwm-finish').text('تأیید و بارگذاری مجدد');
		$('#gvwm-ok').text('0'); $('#gvwm-fail').text('0');
		$('#gvwm-bar-fill').css('width','0%');
		$('#gvwm-modal-text').text('۰ از ' + fa(total));
		$('#gvwm-stop').show().prop('disabled', false); $('#gvwm-finish').hide();
		$('#gvwm-modal').addClass('is-open');

		function paint(){
			var done = Math.min(idx,total);
			$('#gvwm-bar-fill').css('width', (total ? Math.round(done/total*100) : 100)+'%');
			$('#gvwm-modal-text').text(fa(done)+' از '+fa(total));
			$('#gvwm-ok').text(fa(ok)); $('#gvwm-fail').text(fa(fail));
		}
		function finish(){
			$('#gvwm-modal-title').text(stop ? 'عملیات متوقف شد' : 'عملیات تمام شد ✅');
			$('#gvwm-stop').hide(); $('#gvwm-finish').show();
		}
		function next(){
			if(stop || idx >= total){ finish(); return; }
			var chunk = ids.slice(idx, idx+size);
			$.post(C.ajax, { action:'gv_wm_process', nonce:C.nonce, op:op, mark_skip: markSkip?1:0, ids:chunk })
			.done(function(r){ if(r && r.success){ ok += r.data.ok; fail += r.data.fail; } else { fail += chunk.length; } })
			.fail(function(){ fail += chunk.length; })
			.always(function(){ idx += chunk.length; paint(); next(); });
		}
		$('#gvwm-stop').off('click').on('click', function(){ stop = true; $(this).prop('disabled', true); });
		next();
	}

	/* ---------- اسکن محل استفاده (مرحله‌ای، بدون اعمال واترمارک) ---------- */
	function runScan(){
		$('#gvwm-modal-title').text('در حال اسکن تصاویر سایت…');
		$('.gvwm-modal-stats').hide();
		$('#gvwm-bar-fill').css('width','2%');
		$('#gvwm-modal-text').text('آماده‌سازی…');
		$('#gvwm-stop').hide(); $('#gvwm-finish').hide();
		$('#gvwm-modal').addClass('is-open');

		function err(){
			$('#gvwm-modal-title').text('خطا در اسکن');
			$('#gvwm-modal-text').text('ارتباط با سرور قطع شد یا خطای PHP رخ داد. دوباره تلاش کنید.');
			$('#gvwm-finish').text('بستن').show();
		}
		function step(phase){
			$.post(C.ajax, { action:'gv_wm_scan', nonce:C.nonce, phase:phase })
			.done(function(r){
				if(!r || !r.success){ return err(); }
				var d = r.data;
				if(d.phase === 'done'){
					$('#gvwm-bar-fill').css('width','100%');
					$('#gvwm-modal-title').text('اسکن تمام شد ✅');
					$('#gvwm-modal-text').text('تصاویر آماده‌ی نمایش هستند.');
					$('#gvwm-finish').text('نمایش تصاویر').show();
					return;
				}
				if(d.phase === 'final'){
					$('#gvwm-modal-text').text('در حال بررسی قالب، ویجت‌ها و دسته‌بندی‌ها…');
					$('#gvwm-bar-fill').css('width','95%');
				} else if(d.total){
					$('#gvwm-bar-fill').css('width', Math.min(92, Math.round(d.done/d.total*90)+3)+'%');
					$('#gvwm-modal-text').text(fa(d.done)+' از '+fa(d.total)+' محتوا بررسی شد');
				}
				step(d.phase);
			})
			.fail(err);
		}
		step('init');
	}
	$(document).on('click', '.gvwm-scan', runScan);

	$('#gvwm-finish').on('click', function(){
		if($(this).text() === 'بستن'){ $('#gvwm-modal').removeClass('is-open'); return; }
		window.location.reload();
	});

	function confirmAndRun(op, ids, markSkip){
		var n = fa(ids.length), msg = '';
		if(op === 'apply'){ msg = 'واترمارک روی ' + n + ' تصویر اعمال شود؟\n(پیش از تغییر، از هر تصویر نسخه‌ی پشتیبان گرفته می‌شود.)'; }
		else if(op === 'restore'){ msg = n + ' تصویر به حالت اصلی (بدون واترمارک) برگردانده شود؟'; }
		else { msg = 'نسخه‌ی پشتیبان ' + n + ' تصویر حذف شود؟\nبعد از این دیگر نمی‌توانید آن‌ها را به حالت اول برگردانید.'; }
		if(window.confirm(msg)){ runJob(op, ids, markSkip); }
	}
	function fetchIds(data, op, markSkip){
		$.post(C.ajax, $.extend({ action:'gv_wm_get_ids', nonce:C.nonce, op:op }, data))
		.done(function(r){
			if(!r || !r.success){ window.alert('خطا در دریافت فهرست تصاویر.'); return; }
			var ids = r.data.ids || [];
			if(!ids.length){ window.alert('هیچ تصویر مناسبی برای این عملیات پیدا نشد.'); return; }
			confirmAndRun(op, ids, markSkip);
		})
		.fail(function(){ window.alert('ارتباط با سرور برقرار نشد.'); });
	}
	function needLogo(op){
		if(op === 'apply' && !C.hasLogo){ window.alert('ابتدا در تب «تنظیمات» یک تصویر لوگو انتخاب کنید.'); return true; }
		return false;
	}

	/* انتخاب‌شده‌ها */
	function startSelected(op){
		if(needLogo(op)){ return; }
		if(allFiltered){ fetchIds({ scope:'filtered', filters:filters }, op, true); return; }
		var ids = selectedIds();
		if(ids.length){ confirmAndRun(op, ids, true); }
	}
	$('#gvwm-do-apply').on('click', function(){ startSelected('apply'); });
	$('#gvwm-do-restore').on('click', function(){ startSelected('restore'); });

	/* دکمه‌های هر کارت */
	$(document).on('click', '.gvwm-row-act', function(){
		var op = $(this).data('op'), id = parseInt($(this).data('id'),10);
		if(needLogo(op)){ return; }
		if(op === 'delete_backup'){ confirmAndRun(op, [id], false); } else { runJob(op, [id], true); }
	});

	/* دکمه‌های ابزارها و بازگردانی همه */
	$(document).on('click', '.gvwm-tool', function(){
		var $b = $(this), op = $b.data('op'), scope = $b.data('scope'), markSkip = !!$b.data('markskip');
		if(needLogo(op)){ return; }
		fetchIds({ scope:scope }, op, markSkip);
	});
});
JS;
	wp_register_script( 'gv-wm-admin', false, array( 'jquery' ), GV_WM_DB_VERSION, true );
	wp_enqueue_script( 'gv-wm-admin' );
	wp_localize_script( 'gv-wm-admin', 'gvwmCfg', array(
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( GV_WM_NONCE ),
		'hasLogo' => (bool) gv_wm_get_settings()['logo_id'],
	) );
	wp_add_inline_script( 'gv-wm-admin', $js );
}

/* ==========================================================================
   ۱۷) توابع کمکیِ نمایش
   ========================================================================== */
function gv_wm_format_size( $bytes ) {
	$bytes = (float) $bytes;
	if ( $bytes >= 1048576 ) { return number_format_i18n( $bytes / 1048576, 2 ) . ' مگابایت'; }
	if ( $bytes >= 1024 ) { return number_format_i18n( $bytes / 1024, 1 ) . ' کیلوبایت'; }
	return number_format_i18n( $bytes ) . ' بایت';
}

function gv_wm_render_ref( $ref ) {
	if ( 0 === strpos( $ref, 'p:' ) ) {
		$pid  = (int) substr( $ref, 2 );
		$post = get_post( $pid );
		if ( ! $post ) { return ''; }
		$title = get_the_title( $post );
		$title = $title ? wp_html_excerpt( $title, 34, '…' ) : '#' . $pid;
		$link  = get_edit_post_link( $pid, 'raw' );
		$obj   = get_post_type_object( $post->post_type );
		$type  = $obj ? $obj->labels->singular_name : $post->post_type;
		$txt   = esc_html( $type . ': ' . $title );
		return $link ? '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . $txt . '</a>' : $txt;
	}
	if ( 0 === strpos( $ref, 'x:' ) ) { return esc_html( substr( $ref, 2 ) ); }
	return '';
}

/* ==========================================================================
   ۱۸) رندر صفحه‌ی مدیریت
   ========================================================================== */
function gv_wm_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;

	$s    = gv_wm_get_settings();
	$sets = gv_wm_status_sets();

	$total_images = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg','image/png','image/webp')" );
	$marked_count = count( $sets['flagged'] );
	$restor_count = count( $sets['restorable'] );

	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
	if ( ! in_array( $tab, array( 'library', 'settings', 'tools' ), true ) ) { $tab = $s['logo_id'] ? 'library' : 'settings'; }

	$page_url = admin_url( 'admin.php?page=' . GV_WM_PAGE_SLUG );
	?>
	<div class="wrap gvwm" dir="rtl">

		<div class="gvwm-hero">
			<div>
				<h1>💧 واترمارک خودکار تصاویر سایت</h1>
				<p>لوگوی خودتان را با شفافیت کم روی خودِ فایل تصاویر حک کنید؛ به‌صورت خودکار روی آپلودهای جدید، یا دستی روی هر تصویری که انتخاب می‌کنید. تصاویر بر اساس محل استفاده (فروشگاه، وبلاگ، هدر، فوتر و ...) دسته‌بندی شده‌اند و هر لحظه می‌توانید به نسخه‌ی اصلی برگردید.</p>
			</div>
			<div class="gvwm-hero-side">
				<span class="gvwm-pill"><?php echo ! empty( $s['enabled'] ) ? '🟢 واترمارک خودکار فعال' : '🔴 واترمارک خودکار غیرفعال'; ?></span>
				<?php if ( $restor_count > 0 ) : ?>
					<button type="button" class="gvwm-btn gvwm-btn-light gvwm-tool" data-op="restore" data-scope="restore_all" data-markskip="0">↩️ بازگردانی همه‌ی تصاویر</button>
				<?php endif; ?>
			</div>
		</div>

		<div class="gvwm-stats">
			<div class="gvwm-stat"><i>🖼️</i><div><b><?php echo esc_html( number_format_i18n( $total_images ) ); ?></b><span>کل تصاویر کتابخانه</span></div></div>
			<div class="gvwm-stat"><i>💧</i><div><b><?php echo esc_html( number_format_i18n( $marked_count ) ); ?></b><span>واترمارک‌خورده</span></div></div>
			<div class="gvwm-stat"><i>✨</i><div><b><?php echo esc_html( number_format_i18n( max( 0, $total_images - $marked_count ) ) ); ?></b><span>بدون واترمارک</span></div></div>
			<div class="gvwm-stat"><i>↩️</i><div><b><?php echo esc_html( number_format_i18n( $restor_count ) ); ?></b><span>قابل بازگردانی (دارای پشتیبان)</span></div></div>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="gvwm-notice ok">✅ تنظیمات ذخیره شد.</div><?php endif; ?>
		<?php if ( ! $s['logo_id'] ) : ?><div class="gvwm-notice wn">⚠️ هنوز تصویری برای لوگوی واترمارک انتخاب نکرده‌اید؛ تا آن موقع هیچ تصویری واترمارک نمی‌خورد.</div><?php endif; ?>

		<nav class="gvwm-tabs">
			<a class="gvwm-tab <?php echo 'library' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $page_url . '&tab=library' ); ?>">🖼️ کتابخانه و انتخاب تصاویر</a>
			<a class="gvwm-tab <?php echo 'settings' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $page_url . '&tab=settings' ); ?>">⚙️ تنظیمات</a>
			<a class="gvwm-tab <?php echo 'tools' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $page_url . '&tab=tools' ); ?>">🧰 ابزارها و بازگردانی</a>
		</nav>

		<?php
		if ( 'library' === $tab )      { gv_wm_render_tab_library( $s, $sets ); }
		elseif ( 'settings' === $tab ) { gv_wm_render_tab_settings( $s ); }
		else                           { gv_wm_render_tab_tools( $s, $sets ); }
		?>

		<div class="gvwm-modal" id="gvwm-modal">
			<div class="gvwm-modal-card">
				<h3 id="gvwm-modal-title"></h3>
				<div class="gvwm-progress"><div id="gvwm-bar-fill"></div></div>
				<div class="gvwm-modal-text" id="gvwm-modal-text"></div>
				<div class="gvwm-modal-stats"><span>✅ موفق: <b id="gvwm-ok">0</b></span><span>⏭️ ردشده/ناموفق: <b id="gvwm-fail">0</b></span></div>
				<div class="gvwm-modal-actions">
					<button type="button" class="gvwm-btn gvwm-btn-ghost gvwm-btn-sm" id="gvwm-stop">⏹ توقف</button>
					<button type="button" class="gvwm-btn" id="gvwm-finish" style="display:none;">تأیید و بارگذاری مجدد</button>
				</div>
				<p class="gvwm-hint" style="margin-top:14px;">تا پایان عملیات این پنجره را نبندید.</p>
			</div>
		</div>

		<p class="gvwm-footer">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p>
	</div>
	<?php
}

/* ------------------------------ کارت «ابتدا اسکن کنید» ------------------------------ */
function gv_wm_render_scan_required() {
	?>
	<div class="gvwm-card" style="text-align:center;padding:48px 24px;">
		<div style="font-size:44px;">🔍</div>
		<h2 style="font-size:17px;margin:10px 0;">ابتدا تصاویر سایت باید اسکن شوند</h2>
		<p class="gvwm-sub" style="max-width:560px;margin:0 auto 20px;">
			در این مرحله فقط بررسی می‌شود که هر تصویر در کجا استفاده شده (فروشگاه، وبلاگ، هدر، فوتر و ...). <strong>هیچ واترمارکی اعمال نمی‌شود</strong>؛
			بعد از اسکن، تصاویر لود می‌شوند و خودتان تصمیم می‌گیرید روی کدام‌ها اعمال شود.
		</p>
		<button type="button" class="gvwm-btn gvwm-scan">🔍 شروع اسکن تصاویر</button>
	</div>
	<?php
}

/* ------------------------------ تب کتابخانه ------------------------------ */
function gv_wm_render_tab_library( $s, $sets ) {
	$idx = gv_wm_get_usage_index();
	if ( empty( $idx['built'] ) ) { gv_wm_render_scan_required(); return; }

	$defs     = gv_wm_categories();
	$excluded = gv_wm_excluded_ids( $s );
	$f        = gv_wm_parse_filters( wp_unslash( $_GET ) );

	$all_rows = gv_wm_load_rows( '' );
	$rows     = ( '' === $f['s'] ) ? $all_rows : gv_wm_load_rows( $f['s'] );
	$filtered = gv_wm_filter_rows( $rows, $f, $sets, $excluded );
	$total    = count( $filtered );

	$pages = max( 1, (int) ceil( $total / $f['pp'] ) );
	$paged = min( $pages, max( 1, (int) ( $_GET['gvp'] ?? 1 ) ) );
	$slice = array_slice( $filtered, ( $paged - 1 ) * $f['pp'], $f['pp'] );

	$counts = array_fill_keys( array_keys( $defs ), 0 );
	foreach ( $all_rows as $r ) { foreach ( $r['cats'] as $c ) { if ( isset( $counts[ $c ] ) ) { $counts[ $c ]++; } } }

	$qs = array( 'page' => GV_WM_PAGE_SLUG, 'tab' => 'library', 'status' => $f['status'], 'sort' => $f['sort'], 'pp' => $f['pp'] );
	if ( $f['s'] !== '' ) { $qs['s'] = $f['s']; }
	if ( $f['cats'] ) { $qs['cats'] = $f['cats']; }
$base = str_replace( '999999', '%#%', add_query_arg( array_merge( $qs, array( 'gvp' => 999999 ) ), admin_url( 'admin.php' ) ) );	?>
	<div class="gvwm-card" style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">
		<div>
			<h2 style="margin:0 0 4px;">📍 دسته‌بندی بر اساس محل استفاده</h2>
			<p class="gvwm-sub" style="margin:0;">آخرین اسکن: <?php echo esc_html( wp_date( 'Y/m/d H:i', $idx['built'] ) ); ?> — اگر صفحه‌ای/محصولی ساخته یا ویرایش کرده‌اید، «بازبینی» را بزنید.</p>
		</div>
		<button type="button" class="gvwm-btn gvwm-btn-ghost gvwm-btn-sm gvwm-scan">🔄 بازبینی محل استفاده</button>
	</div>

	<form method="get" class="gvwm-filter" id="gvwm-filter" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( GV_WM_PAGE_SLUG ); ?>">
		<input type="hidden" name="tab" value="library">

		<div class="gvwm-filter-row">
			<input type="search" name="s" class="gvwm-input" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="🔍 جستجو در نام تصاویر...">
			<select name="status" class="gvwm-select">
				<option value="all" <?php selected( $f['status'], 'all' ); ?>>همه‌ی وضعیت‌ها</option>
				<option value="clean" <?php selected( $f['status'], 'clean' ); ?>>بدون واترمارک</option>
				<option value="marked" <?php selected( $f['status'], 'marked' ); ?>>واترمارک‌خورده</option>
				<option value="exempt" <?php selected( $f['status'], 'exempt' ); ?>>معاف‌شده (بازگردانده‌شده)</option>
			</select>
			<select name="sort" class="gvwm-select">
				<option value="new" <?php selected( $f['sort'], 'new' ); ?>>مرتب‌سازی: جدیدترین</option>
				<option value="old" <?php selected( $f['sort'], 'old' ); ?>>قدیمی‌ترین</option>
				<option value="cat" <?php selected( $f['sort'], 'cat' ); ?>>بر اساس دسته (محل استفاده)</option>
				<option value="status" <?php selected( $f['sort'], 'status' ); ?>>واترمارک‌خورده‌ها اول</option>
				<option value="name" <?php selected( $f['sort'], 'name' ); ?>>بر اساس نام</option>
			</select>
			<select name="pp" class="gvwm-select">
				<?php foreach ( array( 24, 48, 96 ) as $n ) : ?>
					<option value="<?php echo (int) $n; ?>" <?php selected( $f['pp'], $n ); ?>><?php echo (int) $n; ?> تصویر در صفحه</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="gvwm-btn gvwm-btn-sm">اعمال فیلتر</button>
		</div>

		<div class="gvwm-filter-row">
			<div class="gvwm-chips">
				<?php foreach ( $defs as $slug => $d ) :
					if ( empty( $counts[ $slug ] ) && ! in_array( $slug, $f['cats'], true ) ) { continue; } ?>
					<label class="gvwm-chip" style="--c:<?php echo esc_attr( $d['color'] ); ?>;">
						<input type="checkbox" name="cats[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $f['cats'], true ) ); ?>>
						<span><?php echo esc_html( $d['icon'] . ' ' . $d['label'] ); ?> <em><?php echo esc_html( number_format_i18n( $counts[ $slug ] ) ); ?></em></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	</form>

	<div id="gvwm-lib" data-total="<?php echo (int) $total; ?>" data-filters="<?php echo esc_attr( wp_json_encode( $f ) ); ?>">

		<div class="gvwm-bar">
			<div class="gvwm-bar-info"><b id="gvwm-sel-count">0</b> تصویر انتخاب شده — از کل <?php echo esc_html( number_format_i18n( $total ) ); ?> نتیجه</div>
			<div class="gvwm-bar-actions">
				<button type="button" class="gvwm-btn gvwm-btn-ghost gvwm-btn-sm" id="gvwm-select-page">☑️ انتخاب/لغو این صفحه</button>
				<button type="button" class="gvwm-btn gvwm-btn-ghost gvwm-btn-sm" id="gvwm-select-all">✅ انتخاب همه‌ی <?php echo esc_html( number_format_i18n( $total ) ); ?> نتیجه</button>
				<button type="button" class="gvwm-btn gvwm-btn-ghost gvwm-btn-sm" id="gvwm-clear">✖ لغو انتخاب</button>
				<button type="button" class="gvwm-btn gvwm-btn-sm" id="gvwm-do-apply" disabled>💧 واترمارک روی انتخاب‌شده‌ها</button>
				<button type="button" class="gvwm-btn gvwm-btn-warn gvwm-btn-sm" id="gvwm-do-restore" disabled>↩️ بازگردانی انتخاب‌شده‌ها</button>
			</div>
		</div>

		<?php if ( empty( $slice ) ) : ?>
			<div class="gvwm-empty">🔎 تصویری با این فیلترها پیدا نشد.</div>
		<?php else : ?>
			<div class="gvwm-grid-imgs">
			<?php foreach ( $slice as $r ) :
				$id         = $r['id'];
				$is_ex      = isset( $excluded[ $id ] );
				$is_marked  = isset( $sets['flagged'][ $id ] );
				$can_restore = isset( $sets['restorable'][ $id ] );
				$is_skip    = isset( $sets['skip'][ $id ] );
				$thumb      = wp_get_attachment_image_url( $id, 'medium' );
				if ( ! $thumb ) { $thumb = wp_get_attachment_url( $id ); }

				if ( $is_ex )                        { $st = array( 'ex', '⛔ مستثنی' ); }
				elseif ( $is_marked && $can_restore ) { $st = array( 'on', '💧 واترمارک‌خورده' ); }
				elseif ( $is_marked )                { $st = array( 'perm', '🔒 دائمی' ); }
				elseif ( $is_skip )                  { $st = array( 'skip', '🚫 معاف' ); }
				else                                 { $st = array( '', '⚪ بدون واترمارک' ); }

				$fname = basename( (string) get_attached_file( $id ) );
				?>
				<div class="gvwm-item">
					<div class="gvwm-thumb">
						<label class="gvwm-tick"><input type="checkbox" class="gvwm-check" value="<?php echo (int) $id; ?>" <?php disabled( $is_ex ); ?>><span>✓</span></label>
						<?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" loading="lazy" alt=""><?php endif; ?>
						<span class="gvwm-state <?php echo esc_attr( $st[0] ); ?>"><?php echo esc_html( $st[1] ); ?></span>
					</div>
					<div class="gvwm-body">
						<div class="gvwm-name"><?php echo esc_html( wp_html_excerpt( $fname, 28, '…' ) ); ?></div>
						<div class="gvwm-badges">
							<?php foreach ( array_slice( $r['cats'], 0, 3 ) as $c ) : $d = $defs[ $c ] ?? $defs['other']; ?>
								<span class="gvwm-badge" style="--c:<?php echo esc_attr( $d['color'] ); ?>;"><?php echo esc_html( $d['icon'] . ' ' . $d['label'] ); ?></span>
							<?php endforeach; ?>
							<?php if ( count( $r['cats'] ) > 3 ) : ?><span class="gvwm-badge" style="--c:#64748b;">+<?php echo (int) ( count( $r['cats'] ) - 3 ); ?></span><?php endif; ?>
						</div>
						<?php
						$ref_html = '';
						foreach ( array_slice( $r['refs'], 0, 2 ) as $ref ) {
							$h = gv_wm_render_ref( $ref );
							if ( $h ) { $ref_html .= '<div>📍 ' . $h . '</div>'; }
						}
						if ( $ref_html ) { echo '<div class="gvwm-ref">' . $ref_html . '</div>'; } // phpcs:ignore WordPress.Security.EscapeOutput
						?>
					</div>
					<div class="gvwm-actions">
						<?php if ( $can_restore ) : ?>
							<button type="button" class="gvwm-btn gvwm-btn-warn gvwm-btn-sm gvwm-row-act" data-op="restore" data-id="<?php echo (int) $id; ?>">↩️ بازگردانی</button>
							<button type="button" class="gvwm-btn gvwm-btn-ghost gvwm-btn-sm gvwm-icon gvwm-row-act" data-op="delete_backup" data-id="<?php echo (int) $id; ?>" title="حذف نسخه‌ی پشتیبان (فقط آزادسازی فضا)">🗑️</button>
						<?php elseif ( ! $is_marked && ! $is_ex ) : ?>
							<button type="button" class="gvwm-btn gvwm-btn-sm gvwm-row-act" data-op="apply" data-id="<?php echo (int) $id; ?>">💧 اعمال واترمارک</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			</div>

			<?php if ( $pages > 1 ) : ?>
				<div class="gvwm-pager">
					<?php
					echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput
						'base'      => $base,
						'format'    => '',
						'current'   => $paged,
						'total'     => $pages,
						'prev_text' => '→',
						'next_text' => '←',
						'type'      => 'plain',
					) );
					?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/* ------------------------------ تب تنظیمات ------------------------------ */
function gv_wm_render_tab_settings( $s ) {
	$logo_url = $s['logo_id'] ? wp_get_attachment_image_url( $s['logo_id'], 'thumbnail' ) : '';
	$auto_labels = array( 'shop' => '🛍️ فروشگاه (محصولات)', 'blog' => '📝 وبلاگ', 'page' => '📄 صفحات', 'other' => '📦 سایر / بدون والد' );
	?>
	<div class="gvwm-grid">
		<div>
			<div class="gvwm-card">
				<h2>⚙️ تنظیمات واترمارک</h2>
				<p class="gvwm-sub">این تنظیمات هم برای آپلودهای جدید و هم برای اعمال دستی/گروهی استفاده می‌شود.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gv_wm_save_settings">
					<?php wp_nonce_field( GV_WM_NONCE ); ?>
					<input type="hidden" id="gvwm-logo-id" name="logo_id" value="<?php echo esc_attr( $s['logo_id'] ); ?>">

					<div class="gvwm-field">
						<label class="gvwm-switch"><input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>> فعال‌سازی واترمارک خودکار روی تصاویرِ تازه‌آپلودشده</label>
						<p class="gvwm-hint">اگر خاموش باشد، هیچ آپلود جدیدی خودکار واترمارک نمی‌خورد؛ ولی همچنان از تب «کتابخانه» می‌توانید دستی هر تصویری را واترمارک کنید.</p>
					</div>

					<div class="gvwm-field">
						<label>آپلودهای جدیدِ کدام بخش‌ها خودکار واترمارک بخورند؟</label>
						<div class="gvwm-checks">
							<?php foreach ( $auto_labels as $k => $lbl ) : ?>
								<label><input type="checkbox" name="auto_cats[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $s['auto_cats'], true ) ); ?>> <?php echo esc_html( $lbl ); ?></label>
							<?php endforeach; ?>
						</div>
						<p class="gvwm-hint">بخش هر آپلود از روی صفحه‌ای که در آن تصویر را آپلود می‌کنید تشخیص داده می‌شود (مثلاً آپلود داخل صفحه‌ی ویرایش محصول = فروشگاه).</p>
					</div>

					<div class="gvwm-field">
						<label>تصویر لوگو (واترمارک)</label>
						<div class="gvwm-logo-picker">
							<img id="gvwm-logo-preview" src="<?php echo esc_url( $logo_url ?: '' ); ?>" <?php echo $logo_url ? '' : 'style="display:none;"'; ?> alt="">
							<span id="gvwm-no-logo-msg" <?php echo $logo_url ? 'style="display:none;"' : ''; ?> class="gvwm-hint">هنوز لوگویی انتخاب نشده</span>
							<button type="button" id="gvwm-pick-logo" class="gvwm-btn gvwm-btn-ghost gvwm-btn-sm">🖼️ انتخاب از کتابخانه رسانه</button>
						</div>
						<p class="gvwm-hint">ترجیحاً یک فایل PNG با پس‌زمینه‌ی شفاف انتخاب کنید.</p>
					</div>

					<div class="gvwm-field">
						<label>موقعیت واترمارک روی تصویر</label>
						<div class="gvwm-positions">
							<div></div>
							<label><input type="radio" name="position" value="top-left" <?php checked( $s['position'], 'top-left' ); ?>> بالا-چپ</label>
							<div></div>
							<label><input type="radio" name="position" value="top-right" <?php checked( $s['position'], 'top-right' ); ?>> بالا-راست</label>
							<label><input type="radio" name="position" value="center" <?php checked( $s['position'], 'center' ); ?>> وسط</label>
							<div></div>
							<label><input type="radio" name="position" value="bottom-left" <?php checked( $s['position'], 'bottom-left' ); ?>> پایین-چپ</label>
							<div></div>
							<label><input type="radio" name="position" value="bottom-right" <?php checked( $s['position'], 'bottom-right' ); ?>> پایین-راست</label>
						</div>
					</div>

					<div class="gvwm-field">
						<label for="gvwm-size">اندازه‌ی واترمارک (نسبت به عرض هر تصویر)</label>
						<div class="gvwm-range-row">
							<input type="range" id="gvwm-size" name="size_percent" min="5" max="40" value="<?php echo esc_attr( $s['size_percent'] ); ?>">
							<span id="gvwm-size-val"><?php echo esc_html( $s['size_percent'] ); ?>%</span>
						</div>
					</div>

					<div class="gvwm-field">
						<label for="gvwm-opacity">میزان شفافیت (واترمارک کم‌رنگ)</label>
						<div class="gvwm-range-row">
							<input type="range" id="gvwm-opacity" name="opacity" min="10" max="90" value="<?php echo esc_attr( $s['opacity'] ); ?>">
							<span id="gvwm-opacity-val"><?php echo esc_html( $s['opacity'] ); ?>%</span>
						</div>
					</div>

					<div class="gvwm-field">
						<label for="gvwm-margin">فاصله از لبه‌ی تصویر</label>
						<div class="gvwm-range-row">
							<input type="range" id="gvwm-margin" name="margin_percent" min="0" max="15" value="<?php echo esc_attr( $s['margin_percent'] ); ?>">
							<span id="gvwm-margin-val"><?php echo esc_html( $s['margin_percent'] ); ?>%</span>
						</div>
					</div>

					<div class="gvwm-field">
						<label for="gvwm-min-width">حداقل عرض تصویر برای واترمارک‌خوردن (پیکسل)</label>
						<input type="number" id="gvwm-min-width" name="min_width" min="50" max="3000" value="<?php echo esc_attr( $s['min_width'] ); ?>" class="gvwm-input" style="max-width:140px;flex:none;">
						<p class="gvwm-hint">تصاویر کوچک‌تر از این عرض (مثل آیکون‌های ریز) واترمارک نمی‌خورند.</p>
					</div>

					<div class="gvwm-field">
						<label class="gvwm-switch"><input type="checkbox" name="apply_to_sizes" <?php checked( $s['apply_to_sizes'], 1 ); ?>> روی همه‌ی سایزهای تولیدشده (تامبنیل، مدیوم، بزرگ و ...) هم اعمال شود</label>
						<p class="gvwm-hint">توصیه می‌شود فعال بماند؛ در غیر این صورت فقط فایل اصلی/بزرگ واترمارک می‌خورد.</p>
					</div>

					<button type="submit" class="gvwm-btn">💾 ذخیره تنظیمات</button>
				</form>
			</div>
		</div>

		<div class="gvwm-card gvwm-preview">
			<h2>👁️ پیش‌نمایش</h2>
			<div id="gvwm-preview-box">
				<span>نمونه تصویر سایت</span>
				<img id="gvwm-preview-logo" src="<?php echo esc_url( $logo_url ?: '' ); ?>" alt="">
			</div>
			<p class="gvwm-hint">این فقط یک شبیه‌سازی تقریبی از موقعیت، اندازه و شفافیت است؛ نتیجه‌ی واقعی روی خودِ فایل‌ها اعمال می‌شود.</p>
			<p class="gvwm-hint">💡 اگر لوگو یا تنظیمات را عوض کردید، تصاویرِ قبلاً واترمارک‌شده تغییر نمی‌کنند؛ ابتدا آن‌ها را «بازگردانی» و سپس دوباره «اعمال» کنید.</p>
		</div>
	</div>
	<?php
}

/* ------------------------------ تب ابزارها ------------------------------ */
function gv_wm_render_tab_tools( $s, $sets ) {
	$restor_count = count( $sets['restorable'] );
	$perm_count   = max( 0, count( $sets['flagged'] ) - $restor_count );
	$scanned      = ! empty( gv_wm_get_usage_index()['built'] );
	?>
	<div class="gvwm-tools">

		<div class="gvwm-tool-card">
			<div class="ico">🚀</div>
			<h3>اعمال روی همه‌ی تصاویر بدون واترمارک</h3>
			<p>واترمارک را روی تمام تصاویرِ کتابخانه که هنوز واترمارک نخورده‌اند اعمال می‌کند (لوگوی سایت و تصاویرِ «معاف‌شده» رد می‌شوند). برای انتخابِ دقیق‌تر به تب «کتابخانه» بروید و بر اساس دسته یا تیک‌زدن انتخاب کنید.</p>
			<button type="button" class="gvwm-btn gvwm-tool" data-op="apply" data-scope="all_clean" data-markskip="0" <?php disabled( ! $s['logo_id'] ); ?>>🚀 اعمال روی همه</button>
			<?php if ( ! $s['logo_id'] ) : ?><span class="gvwm-hint">ابتدا در تب تنظیمات یک لوگو انتخاب کنید.</span><?php endif; ?>
		</div>

		<div class="gvwm-tool-card">
			<div class="ico">↩️</div>
			<h3>بازگردانی همه‌ی تصاویر به حالت اصلی</h3>
			<p>تمام تصاویرِ واترمارک‌خورده که نسخه‌ی پشتیبان دارند (<strong><?php echo esc_html( number_format_i18n( $restor_count ) ); ?></strong> تصویر) به نسخه‌ی تمیز برمی‌گردند و همه‌ی سایزها دوباره ساخته می‌شوند. اگر می‌خواهید تصاویر جدید هم دیگر واترمارک نخورند، واترمارک خودکار را در تنظیمات خاموش کنید.
			<?php if ( $perm_count > 0 ) : ?><br><span class="gvwm-hint"><?php echo esc_html( number_format_i18n( $perm_count ) ); ?> تصویر واترمارک دائمی دارند (پشتیبانشان حذف شده) و قابل بازگردانی نیستند.</span><?php endif; ?></p>
			<button type="button" class="gvwm-btn gvwm-btn-warn gvwm-tool" data-op="restore" data-scope="restore_all" data-markskip="0" <?php disabled( 0 === $restor_count ); ?>>↩️ بازگردانی همه</button>
		</div>

		<div class="gvwm-tool-card">
			<div class="ico">🔄</div>
			<h3><?php echo $scanned ? 'بازبینی محل استفاده‌ی تصاویر' : 'اسکن محل استفاده‌ی تصاویر'; ?></h3>
			<p>تمام محصولات، نوشته‌ها، صفحات، المنتور، تنظیمات قالب، ویجت‌ها و تمپلیت‌های هدر/فوتر به‌صورت مرحله‌ای بررسی می‌شوند تا دسته‌بندی تصاویر ساخته/به‌روز شود. این کار هیچ واترمارکی اعمال نمی‌کند.</p>
			<button type="button" class="gvwm-btn gvwm-btn-ghost gvwm-scan"><?php echo $scanned ? '🔄 شروع بازبینی' : '🔍 شروع اسکن'; ?></button>
		</div>

		<div class="gvwm-tool-card danger">
			<div class="ico">🗑️</div>
			<h3>حذف همه‌ی نسخه‌های پشتیبان</h3>
			<p>فضای فعلی پشتیبان‌ها: <strong><?php echo esc_html( gv_wm_format_size( gv_wm_backup_size() ) ); ?></strong>. با حذف آن‌ها تصاویر همین‌طور با واترمارک می‌مانند و <strong>دیگر امکان بازگردانی وجود ندارد</strong>. فقط برای آزادکردن فضای هاست استفاده کنید.</p>
			<button type="button" class="gvwm-btn gvwm-btn-danger gvwm-tool" data-op="delete_backup" data-scope="delete_all_backups" data-markskip="0" <?php disabled( 0 === $restor_count ); ?>>🗑️ حذف همه‌ی پشتیبان‌ها</button>
		</div>

	</div>
	<?php
}