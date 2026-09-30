<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — واترمارک خودکار تصاویر سایت
 *  ------------------------------------------------------------
 *  نحوه‌ی کار:
 *  ۱) ادمین یک تصویر (لوگو) را از کتابخانه‌ی رسانه انتخاب می‌کند.
 *  ۲) از آن پس، هر عکسی که در سایت آپلود شود (محصول، وبلاگ، هر
 *     پیوست تصویری دیگر) به‌صورت خودکار و همان لحظه‌ی آپلود،
 *     همین لوگو با شفافیت کم در گوشه‌ی انتخاب‌شده روی خودِ فایل
 *     تصویر «حک» می‌شود؛ یعنی حتی اگر کاربر فایل را دانلود کند،
 *     واترمارک روی آن هست (برخلاف روش‌های CSS/Overlay که فقط
 *     ظاهر صفحه را تغییر می‌دهند و در دانلود اثری ندارند).
 *  ۳) لوگوی خود سایت (Site Identity / آیکون سایت) و خودِ فایل
 *     لوگوی واترمارک، همیشه از این کار مستثنی هستند و دست‌نخورده
 *     می‌مانند.
 *  ۴) پیش از واترمارک زدن، از نسخه‌ی اصلی و تمیز هر عکس یک نسخه‌ی
 *     پشتیبان گرفته می‌شود تا ادمین در صورت نیاز بتواند از داشبورد
 *     همان عکس را به حالت اول (بدون واترمارک) برگرداند.
 *  ۵) یک ابزار «اعمال روی تصاویر قبلی» هم وجود دارد تا عکس‌هایی که
 *     پیش از فعال‌سازی این قابلیت آپلود شده‌اند هم واترمارک بخورند.
 * ==========================================================
 */

define( 'GV_WM_OPT', 'gv_watermark_settings' );
define( 'GV_WM_NONCE', 'gv_wm_nonce_action' );
define( 'GV_WM_DB_VERSION', '1.0' );
define( 'GV_WM_PAGE_SLUG', 'gv-watermark' );
define( 'GV_WM_BACKUP_DIR', 'gv-watermark-originals' );
define( 'GV_WM_META_FLAG', '_gv_watermark_applied' );

/* ==========================================================================
   ۰) تنظیمات پیش‌فرض
   ========================================================================== */
function gv_wm_default_settings() {
	return array(
		'enabled'        => 0,
		'logo_id'        => 0,
		'position'       => 'bottom-right', // bottom-right | bottom-left | top-right | top-left | center
		'size_percent'   => 18,             // عرض واترمارک نسبت به عرض تصویر (درصد)
		'opacity'        => 45,             // میزان شفافیت (۱۰ تا ۹۰)
		'margin_percent' => 3,              // فاصله از لبه‌ها نسبت به عرض تصویر (درصد)
		'min_width'      => 300,            // تصاویر کوچک‌تر از این عرض (پیکسل) واترمارک نمی‌خورند
		'apply_to_sizes' => 1,              // روی تمام سایزهای تولیدشده (تامبنیل، مدیوم و ...) هم اعمال شود
	);
}

function gv_wm_get_settings() {
	return wp_parse_args( get_option( GV_WM_OPT, array() ), gv_wm_default_settings() );
}

/* ==========================================================================
   ۱) ساخت جدول دیتابیس (لاگ نسخه‌های پشتیبان برای امکان بازگرداندن)
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
   ۲) پوشه‌ی نسخه‌های پشتیبان (نسخه‌ی تمیز/اصلی هر عکس، خارج از دید عمومی)
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

/* ==========================================================================
   ۳) قفل موقت پردازش — برای جلوگیری از حلقه‌ی بی‌پایان هنگام بازگرداندن
   (وقتی نسخه‌ی پاک را دوباره از طریق wp_generate_attachment_metadata
   می‌سازیم، نباید همان لحظه دوباره واترمارک بخورد)
   ========================================================================== */
function gv_wm_processing_locked( $set = null ) {
	static $locked = false;
	if ( null !== $set ) { $locked = (bool) $set; }
	return $locked;
}

/* ==========================================================================
   ۴) تشخیص تصاویری که هرگز نباید واترمارک بخورند (لوگو/آیکون سایت و خودِ فایل واترمارک)
   ========================================================================== */
function gv_wm_is_excluded_attachment( $attachment_id, $settings ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) { return true; }

	if ( $attachment_id === (int) $settings['logo_id'] ) { return true; }

	$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id && $attachment_id === $custom_logo_id ) { return true; }

	$site_icon_id = (int) get_option( 'site_icon' );
	if ( $site_icon_id && $attachment_id === $site_icon_id ) { return true; }

	// برخی قالب‌ها/افزونه‌های قدیمی‌تر لوگو را در این آپشن نگه می‌دارند
	$site_logo_id = (int) get_option( 'site_logo' );
	if ( $site_logo_id && $attachment_id === $site_logo_id ) { return true; }

	return false;
}

/* ==========================================================================
   ۵) توابع GD — بارگذاری، تغییر اندازه با حفظ شفافیت، ترکیب با شفافیت دلخواه و ذخیره
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
			// روی JPEG کانال شفافیت وجود ندارد؛ پس‌زمینه‌ی سفید زیرش می‌گذاریم تا سیاه نشود
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

/**
 * شفافیتِ کلیِ یک تصویر (که خودش ممکن است بخشی از پیکسل‌هایش هم از قبل
 * شفاف باشد، مثل پس‌زمینه‌ی حذف‌شده‌ی یک لوگوی PNG) را با یک درصد مشخص کم می‌کند.
 * این کار روی خودِ کانال آلفا انجام می‌شود تا در مرحله‌ی بعد بتوانیم با یک
 * imagecopy ساده و صحیح آن را روی هر مقصدی (چه یک عکس کدر، چه یک عکس با
 * پس‌زمینه‌ی شفاف مثل عکس محصول بدون بک‌گراند) ترکیب کنیم — بدون این‌که
 * پس‌زمینه‌ی شفافِ خودِ مقصد سیاه یا کدر شود.
 */
function gv_wm_scale_alpha( $img, $opacity_pct ) {
	$w = imagesx( $img ); $h = imagesy( $img );
	$factor = max( 0, min( 100, $opacity_pct ) ) / 100;

	imagealphablending( $img, false );
	imagesavealpha( $img, true );

	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$rgba = imagecolorat( $img, $x, $y );
			$a    = ( $rgba >> 24 ) & 0x7F; // ۰=کاملاً کدر ... ۱۲۷=کاملاً شفاف
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

/**
 * واترمارک را روی یک فایل تصویر مشخص (فایل اصلی یا هرکدام از سایزهای تولیدشده) اعمال و ذخیره می‌کند.
 * خروجی: true در صورت موفقیت، false در صورت رد شدن/عدم امکان (فرمت پشتیبانی‌نشده، GD ناموجود، تصویر خیلی کوچک و ...)
 */
function gv_wm_composite_and_save( $target_file, $logo_file, $settings ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) { return false; } // کتابخانه GD روی هاست فعال نیست

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

	// اندازه‌ی نهایی واترمارک: درصدی از عرض تصویر مقصد، با حفظ نسبت تصویر لوگو
	$ratio    = max( 5, min( 60, (int) $settings['size_percent'] ) ) / 100;
	$new_l_w  = max( 1, (int) round( $t_w * $ratio ) );
	$new_l_h  = max( 1, (int) round( $l_h0 * ( $new_l_w / $l_w0 ) ) );

	// اگر تصویر مقصد آن‌قدر کوچک است که واترمارک روی آن جا نمی‌شود، صرف‌نظر می‌کنیم
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

	// موقعیت قرارگیری بر اساس گوشه‌ی انتخاب‌شده + فاصله از لبه
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

	// شفافیت دلخواه را روی کانال آلفای خودِ لوگو اعمال می‌کنیم (نه با ترفند
	// imagecopymerge که روی مقصدهای دارای پس‌زمینه‌ی شفاف — مثل عکس محصول
	// بدون بک‌گراند — به‌اشتباه یک جعبه‌ی تیره پشت واترمارک ایجاد می‌کند)
	$opacity = max( 5, min( 100, (int) $settings['opacity'] ) );
	gv_wm_scale_alpha( $logo_resized, $opacity );

	// حالا یک imagecopy ساده با آلفابلندینگ فعال، هم آلفای لوگو و هم آلفای
	// احتمالیِ خودِ تصویر مقصد (اگر شفاف باشد) را درست ترکیب می‌کند
	imagealphablending( $target_img, true );
	imagesavealpha( $target_img, true );
	imagecopy( $target_img, $logo_resized, $dst_x, $dst_y, 0, 0, $new_l_w, $new_l_h );
	imagedestroy( $logo_resized );

	$saved = gv_wm_save_image( $target_img, $target_file, $target_info['mime'] );
	imagedestroy( $target_img );

	return (bool) $saved;
}

/* ==========================================================================
   ۶) مسیر فایل لوگوی واترمارک انتخاب‌شده
   ========================================================================== */
function gv_wm_get_logo_path( $settings ) {
	$logo_id = (int) $settings['logo_id'];
	if ( ! $logo_id ) { return false; }
	$path = get_attached_file( $logo_id );
	if ( ! $path || ! file_exists( $path ) ) { return false; }
	return $path;
}

/* ==========================================================================
   ۷) پردازش اصلی: واترمارک زدن به یک پیوست (فایل اصلی + در صورت فعال بودن، همه‌ی سایزها)
   ========================================================================== */
function gv_wm_apply_to_attachment( $attachment_id, $metadata = null ) {
	$attachment_id = (int) $attachment_id;

	if ( gv_wm_processing_locked() ) { return false; }
	if ( get_post_meta( $attachment_id, GV_WM_META_FLAG, true ) ) { return false; } // قبلاً واترمارک خورده

	if ( ! wp_attachment_is_image( $attachment_id ) ) { return false; }

	$mime = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) { return false; }

	$s = gv_wm_get_settings();
	if ( empty( $s['enabled'] ) ) { return false; }
	if ( gv_wm_is_excluded_attachment( $attachment_id, $s ) ) { return false; }

	$logo_path = gv_wm_get_logo_path( $s );
	if ( ! $logo_path ) { return false; }

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) { return false; }

	$size_info = @getimagesize( $file );
	if ( ! $size_info || $size_info[0] < max( 50, intval( $s['min_width'] ) ) ) { return false; }

	// پشتیبان‌گیری از نسخه‌ی اصلی و تمیز، پیش از هر تغییری (برای امکان بازگرداندن بعدی)
	$backup_dir  = gv_wm_backup_dir();
	$backup_name = $attachment_id . '-' . wp_unique_filename( $backup_dir, basename( $file ) );
	$backup_path = trailingslashit( $backup_dir ) . $backup_name;
	if ( ! @copy( $file, $backup_path ) ) { return false; }

	$stamped_main = gv_wm_composite_and_save( $file, $logo_path, $s );

	if ( ! $stamped_main ) {
		@unlink( $backup_path ); // پشتیبانی که به دردمان نخورد را نگه نمی‌داریم
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
	clearstatcache();

	return true;
}

/* هوک اصلی: بلافاصله بعد از آپلود و ساخت سایزهای مختلف تصویر اجرا می‌شود.
   اولویت ۳۰ عمداً بعد از بهینه‌ساز تصاویر (اولویت ۲۰) است تا واترمارک روی
   نسخه‌ی نهایی/فشرده‌شده زده شود، نه نسخه‌ای که بعداً دوباره فشرده می‌شود. */
add_filter( 'wp_generate_attachment_metadata', 'gv_wm_handle_new_upload', 30, 2 );
function gv_wm_handle_new_upload( $metadata, $attachment_id ) {
	gv_wm_apply_to_attachment( $attachment_id, $metadata );
	return $metadata;
}

/* ==========================================================================
   ۸) بازگرداندن یک تصویر به نسخه‌ی اصلی/تمیزِ پیش از واترمارک
   ========================================================================== */
function gv_wm_restore_attachment( $attachment_id ) {
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

	// بازسازی کامل تمام سایزها از روی نسخه‌ی پاک، تا اثری از واترمارک حتی در تامبنیل‌ها نماند
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

	$wpdb->update( $wpdb->prefix . 'gv_watermark_log',
		array( 'status' => 'restored', 'restored_at' => current_time( 'mysql' ) ),
		array( 'id' => $row->id )
	);

	if ( file_exists( $row->backup_path ) ) { wp_delete_file( $row->backup_path ); }

	return true;
}

/* ==========================================================================
   ۹) محافظ خودکار: اگر عکسی که قبلاً واترمارک خورده، بعداً به‌عنوان لوگو یا
   آیکون سایت انتخاب شود، همان لحظه به‌صورت خودکار به حالت تمیز برمی‌گردد.
   (پوشش سناریویی که عکس ابتدا یک عکس معمولی بوده و بعداً لوگو شده است)
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
	);

	update_option( GV_WM_OPT, $settings );

	// اگر تصویر جدیدی به‌عنوان لوگوی واترمارک انتخاب شده، مطمئن می‌شویم خودِ آن
	// تصویر (که ممکن است همین چند لحظه‌ی پیش آپلود شده و خودکار واترمارک خورده باشد) تمیز بماند
	if ( $new_logo_id && $new_logo_id !== (int) $old_settings['logo_id'] ) {
		gv_wm_restore_attachment( $new_logo_id );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_WM_PAGE_SLUG . '&updated=1' ) );
	exit;
}

/* ==========================================================================
   ۱۱) بازگرداندن دستی یک تصویر از جدول داشبورد
   ========================================================================== */
add_action( 'admin_post_gv_wm_restore_one', 'gv_wm_handle_manual_restore' );
function gv_wm_handle_manual_restore() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_WM_NONCE );

	$attachment_id = intval( $_POST['attachment_id'] ?? 0 );
	gv_wm_restore_attachment( $attachment_id );

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_WM_PAGE_SLUG . '&restored=1' ) );
	exit;
}

/* ==========================================================================
   ۱۲) حذف نسخه‌ی پشتیبان بدون بازگرداندن (فقط برای آزاد کردن فضای هاست؛
        تصویر همان‌طور که هست، با واترمارک، برای همیشه باقی می‌ماند)
   ========================================================================== */
add_action( 'admin_post_gv_wm_delete_backup', 'gv_wm_handle_delete_backup' );
function gv_wm_handle_delete_backup() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_WM_NONCE );

	global $wpdb;
	$log_id = intval( $_POST['log_id'] ?? 0 );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gv_watermark_log WHERE id = %d", $log_id ) );

	if ( $row && 'watermarked' === $row->status ) {
		if ( $row->backup_path && file_exists( $row->backup_path ) ) {
			wp_delete_file( $row->backup_path );
		}
		$wpdb->update( $wpdb->prefix . 'gv_watermark_log',
			array( 'status' => 'backup_deleted' ),
			array( 'id' => $row->id )
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_WM_PAGE_SLUG . '&backup_deleted=1' ) );
	exit;
}

/* ==========================================================================
   ۱۳) اعمال دسته‌جمعی روی تصاویری که پیش از فعال‌سازی این قابلیت آپلود شده‌اند
   ========================================================================== */
add_action( 'admin_post_gv_wm_bulk_apply', 'gv_wm_handle_bulk_apply' );
function gv_wm_handle_bulk_apply() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_WM_NONCE );

	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 0 ); }

	$s = gv_wm_get_settings();
	$done = 0; $skipped = 0;

	if ( ! empty( $s['enabled'] ) && gv_wm_get_logo_path( $s ) ) {
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array( 'key' => GV_WM_META_FLAG, 'compare' => 'NOT EXISTS' ),
			),
		) );

		foreach ( $ids as $id ) {
			if ( gv_wm_apply_to_attachment( $id ) ) { $done++; } else { $skipped++; }
		}
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_WM_PAGE_SLUG . '&bulk_done=' . $done . '&bulk_skipped=' . $skipped ) );
	exit;
}

/* ==========================================================================
   ۱۴) اگر پیوست به‌طور کامل حذف شود، نسخه‌ی پشتیبان مربوطه هم پاک شود
   ========================================================================== */
add_action( 'delete_attachment', 'gv_wm_on_attachment_deleted' );
function gv_wm_on_attachment_deleted( $attachment_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_watermark_log WHERE attachment_id = %d AND status = 'watermarked'", $attachment_id
	) );
	foreach ( $rows as $row ) {
		if ( $row->backup_path && file_exists( $row->backup_path ) ) {
			wp_delete_file( $row->backup_path );
		}
	}
	$wpdb->update( $wpdb->prefix . 'gv_watermark_log',
		array( 'status' => 'deleted' ),
		array( 'attachment_id' => $attachment_id, 'status' => 'watermarked' )
	);
}

/* ==========================================================================
   ۱۵) منوی مدیریت (زیرمنوی داشبورد اصلی گروت ویژن)
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
   ۱۶) اسکریپت انتخاب‌گر رسانه + پیش‌نمایش زنده در صفحه‌ی تنظیمات
   ========================================================================== */
add_action( 'admin_enqueue_scripts', 'gv_wm_admin_assets' );
function gv_wm_admin_assets( $hook ) {
	if ( strpos( $hook, GV_WM_PAGE_SLUG ) === false ) { return; }
	wp_enqueue_media();

	$js = <<<'JS'
	jQuery(function($){
		$('#gvwm-pick-logo').on('click', function(e){
			e.preventDefault();
			var frame = wp.media({ title: 'انتخاب تصویر لوگو برای واترمارک', multiple: false, library: { type: 'image' } });
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				$('#gvwm-logo-id').val(att.id);
				$('#gvwm-logo-preview').attr('src', att.url).show();
				$('#gvwm-preview-logo').attr('src', att.url).show();
				$('#gvwm-no-logo-msg').hide();
				gvwmUpdatePreview();
			});
			frame.open();
		});

		function gvwmUpdatePreview(){
			var size   = parseInt($('#gvwm-size').val(), 10);
			var margin = parseInt($('#gvwm-margin').val(), 10);
			var opacity= parseInt($('#gvwm-opacity').val(), 10);
			var pos    = $('input[name="position"]:checked').val();

			$('#gvwm-size-val').text(size + '%');
			$('#gvwm-margin-val').text(margin + '%');
			$('#gvwm-opacity-val').text(opacity + '%');

			var $box = $('#gvwm-preview-box');
			var $logo = $('#gvwm-preview-logo');
			var boxW = $box.width();

			$logo.css({ width: (boxW * size / 100) + 'px', height: 'auto', opacity: opacity/100,
				top: 'auto', bottom: 'auto', left: 'auto', right: 'auto' });

			var m = (boxW * margin / 100) + 'px';
			if (pos === 'bottom-right') { $logo.css({ bottom: m, right: m }); }
			else if (pos === 'bottom-left') { $logo.css({ bottom: m, left: m }); }
			else if (pos === 'top-right') { $logo.css({ top: m, right: m }); }
			else if (pos === 'top-left') { $logo.css({ top: m, left: m }); }
			else { $logo.css({ top: '50%', left: '50%', transform: 'translate(-50%,-50%)' }); }
		}

		$('#gvwm-size, #gvwm-margin, #gvwm-opacity').on('input', gvwmUpdatePreview);
		$('input[name="position"]').on('change', gvwmUpdatePreview);
		gvwmUpdatePreview();
	});
JS;

	wp_add_inline_script( 'jquery-core', $js );
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

function gv_wm_position_label( $key ) {
	$labels = array(
		'bottom-right' => 'پایین سمت راست',
		'bottom-left'  => 'پایین سمت چپ',
		'top-right'    => 'بالا سمت راست',
		'top-left'     => 'بالا سمت چپ',
		'center'       => 'وسط تصویر',
	);
	return $labels[ $key ] ?? $key;
}

/* ==========================================================================
   ۱۸) رندر صفحه‌ی مدیریت
   ========================================================================== */
function gv_wm_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;

	$s = gv_wm_get_settings();
	$logo_url = $s['logo_id'] ? wp_get_attachment_image_url( $s['logo_id'], 'thumbnail' ) : '';

	$log_rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gv_watermark_log WHERE status = 'watermarked' ORDER BY applied_at DESC LIMIT 200" );
	$total_watermarked = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gv_watermark_log WHERE status = 'watermarked'" );
	?>
	<div class="wrap" dir="rtl" style="font-family:'Vazirmatn',Tahoma,sans-serif;max-width:1100px;">
		<style>
			.gvwm-header{background:linear-gradient(120deg,#0c4a6e,#0369a1);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;}
			.gvwm-header h1{margin:0;font-size:20px;color:#fff;}
			.gvwm-header p{margin:8px 0 0;font-size:13px;color:#e0f2fe;line-height:1.9;}
			.gvwm-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:20px;align-items:start;}
			@media(max-width:960px){.gvwm-grid{grid-template-columns:1fr;}}
			.gvwm-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;margin-bottom:20px;}
			.gvwm-card h2{margin-top:0;font-size:15px;}
			.gvwm-field{margin-bottom:16px;}
			.gvwm-field label{display:block;font-weight:600;margin-bottom:6px;font-size:13px;}
			.gvwm-hint{color:#888;font-size:11.5px;margin-top:4px;}
			.gvwm-logo-picker{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
			.gvwm-logo-picker img{max-width:90px;max-height:90px;border:1px solid #e2e8f0;border-radius:10px;background:repeating-conic-gradient(#f1f5f9 0% 25%,#fff 0% 50%) 50%/16px 16px;padding:4px;}
			.gvwm-positions{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;max-width:260px;}
			.gvwm-pos-item{display:flex;align-items:center;justify-content:center;}
			.gvwm-pos-item input{margin-left:6px;}
			.gvwm-pos-item label{font-weight:400;font-size:12px;margin:0;display:flex;align-items:center;gap:4px;cursor:pointer;}
			.gvwm-btn{background:#0c4a6e;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;font-size:13px;}
			.gvwm-btn-secondary{background:#f1f5f9;color:#0f172a !important;border:1px solid #e2e8f0;}
			.gvwm-btn-danger{background:#fee2e2;color:#991b1b !important;}
			.gvwm-btn-small{padding:6px 14px;font-size:12px;border-radius:8px;}
			.gvwm-preview-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;position:sticky;top:40px;}
			#gvwm-preview-box{position:relative;width:100%;aspect-ratio:4/3;border-radius:10px;overflow:hidden;background:linear-gradient(135deg,#cbd5e1,#94a3b8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;}
			#gvwm-preview-logo{position:absolute;max-width:70%;display:none;filter:drop-shadow(0 1px 2px rgba(0,0,0,.4));}
			.gvwm-stat-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:20px;}
			.gvwm-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px 18px;}
			.gvwm-stat b{display:block;font-size:20px;color:#0c4a6e;}
			.gvwm-stat span{font-size:12px;color:#666;}
			.gvwm-table-scroll{width:100%;overflow-x:auto;border:1px solid #e5e7eb;border-radius:14px;}
			table.gvwm-table{width:100%;min-width:640px;border-collapse:separate;border-spacing:0;font-size:12.5px;}
			table.gvwm-table thead th{text-align:right;background:linear-gradient(180deg,#f8fafc,#eef1f7);color:#334155;font-size:11.5px;font-weight:800;padding:12px;border-bottom:2px solid #e2e8f0;}
			table.gvwm-table td{padding:10px 12px;border-bottom:1px solid #eef1f4;vertical-align:middle;}
			table.gvwm-table tbody tr:nth-child(even){background:#fafbff;}
			table.gvwm-table img.gvwm-thumb{width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;}
			.gvwm-muted{color:#94a3b8;font-size:11.5px;}
			.gvwm-range-row{display:flex;align-items:center;gap:10px;}
			.gvwm-range-row input[type=range]{flex:1;}
			.gvwm-range-row span{min-width:44px;text-align:left;font-weight:700;color:#0c4a6e;font-size:12px;}
		</style>

		<div class="gvwm-header">
			<h1>💧 واترمارک خودکار تصاویر سایت</h1>
			<p>یک تصویر به‌عنوان لوگوی واترمارک انتخاب کنید تا از این پس، هر عکسی که در سایت (محصولات، وبلاگ و ...) آپلود می‌شود، همان لحظه با شفافیت کم روی خودِ فایل تصویر حک شود؛ حتی اگر بازدیدکننده عکس را دانلود کند، لوگوی شما رویش می‌ماند. لوگوی خودِ سایت از این کار همیشه مستثنی است.</p>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['restored'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تصویر به حالت اصلی (بدون واترمارک) بازگردانده شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['backup_deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p>نسخه‌ی پشتیبان حذف شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['bulk_done'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				عملیات دسته‌جمعی تمام شد — <?php echo esc_html( (int) $_GET['bulk_done'] ); ?> تصویر واترمارک خورد
				<?php if ( ! empty( $_GET['bulk_skipped'] ) ) : ?> و <?php echo esc_html( (int) $_GET['bulk_skipped'] ); ?> مورد رد شد (کوچک بودن، فرمت پشتیبانی‌نشده یا استثنا بودن).<?php else : ?>.<?php endif; ?>
			</p></div>
		<?php endif; ?>
		<?php if ( ! empty( $s['enabled'] ) && ! $s['logo_id'] ) : ?>
			<div class="notice notice-warning"><p>واترمارک فعال است ولی هنوز هیچ تصویری برای لوگو انتخاب نکرده‌اید؛ تا وقتی لوگو انتخاب نشود، هیچ عکسی واترمارک نمی‌خورد.</p></div>
		<?php endif; ?>

		<div class="gvwm-stat-cards">
			<div class="gvwm-stat"><b><?php echo esc_html( number_format_i18n( (int) $total_watermarked ) ); ?></b><span>تصویر واترمارک‌خورده در حال حاضر</span></div>
			<div class="gvwm-stat"><b><?php echo $s['enabled'] ? '🟢 فعال' : '🔴 غیرفعال'; ?></b><span>وضعیت واترمارک خودکار</span></div>
		</div>

		<div class="gvwm-grid">
			<div>
				<div class="gvwm-card">
					<h2>⚙️ تنظیمات واترمارک</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="gv_wm_save_settings">
						<?php wp_nonce_field( GV_WM_NONCE ); ?>
						<input type="hidden" id="gvwm-logo-id" name="logo_id" value="<?php echo esc_attr( $s['logo_id'] ); ?>">

						<div class="gvwm-field">
							<label><input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>> فعال‌سازی واترمارک خودکار روی تصاویر جدید</label>
						</div>

						<div class="gvwm-field">
							<label>تصویر لوگو (واترمارک)</label>
							<div class="gvwm-logo-picker">
								<img id="gvwm-logo-preview" src="<?php echo esc_url( $logo_url ?: '' ); ?>" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>>
								<span id="gvwm-no-logo-msg" <?php echo $logo_url ? 'style="display:none;"' : ''; ?> class="gvwm-muted">هنوز لوگویی انتخاب نشده</span>
								<button type="button" id="gvwm-pick-logo" class="gvwm-btn gvwm-btn-secondary gvwm-btn-small">🖼️ انتخاب از کتابخانه رسانه</button>
							</div>
							<p class="gvwm-hint">ترجیحاً یک فایل PNG با پس‌زمینه‌ی شفاف انتخاب کنید تا نتیجه‌ی بهتری بگیرید.</p>
						</div>

						<div class="gvwm-field">
							<label>موقعیت واترمارک روی تصویر</label>
							<div class="gvwm-positions">
								<div></div>
								<div class="gvwm-pos-item"><label><input type="radio" name="position" value="top-left" <?php checked( $s['position'], 'top-left' ); ?>> بالا-چپ</label></div>
								<div></div>
								<div class="gvwm-pos-item"><label><input type="radio" name="position" value="top-right" <?php checked( $s['position'], 'top-right' ); ?>> بالا-راست</label></div>
								<div class="gvwm-pos-item"><label><input type="radio" name="position" value="center" <?php checked( $s['position'], 'center' ); ?>> وسط</label></div>
								<div></div>
								<div class="gvwm-pos-item"><label><input type="radio" name="position" value="bottom-left" <?php checked( $s['position'], 'bottom-left' ); ?>> پایین-چپ</label></div>
								<div></div>
								<div class="gvwm-pos-item"><label><input type="radio" name="position" value="bottom-right" <?php checked( $s['position'], 'bottom-right' ); ?>> پایین-راست</label></div>
							</div>
							<p class="gvwm-hint">پیش‌فرض پیشنهادی: پایین سمت راست.</p>
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
							<input type="number" id="gvwm-min-width" name="min_width" min="50" max="3000" value="<?php echo esc_attr( $s['min_width'] ); ?>" class="small-text">
							<p class="gvwm-hint">تصاویر کوچک‌تر از این عرض (مثل آیکون‌های ریز) واترمارک نمی‌خورند.</p>
						</div>

						<div class="gvwm-field">
							<label><input type="checkbox" name="apply_to_sizes" <?php checked( $s['apply_to_sizes'], 1 ); ?>> روی همه‌ی سایزهای تولیدشده (تامبنیل، مدیوم، بزرگ و ...) هم اعمال شود</label>
							<p class="gvwm-hint">توصیه می‌شود فعال بماند؛ در غیر این صورت فقط فایل اصلی/بزرگ واترمارک می‌خورد.</p>
						</div>

						<button type="submit" class="gvwm-btn">💾 ذخیره تنظیمات</button>
					</form>
				</div>

				<div class="gvwm-card">
					<h2>🚀 اعمال روی تصاویر قبلی</h2>
					<p class="gvwm-muted">تصاویری که پیش از فعال‌سازی این قابلیت آپلود شده‌اند، خودکار واترمارک نمی‌خورند. با این دکمه می‌توانید واترمارک را یک‌جا روی تمام تصاویر موجود در کتابخانه‌ی رسانه (به‌جز موارد مستثنی) اعمال کنید.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('این کار روی خودِ فایل تصاویر موجود اعمال می‌شود (قابل بازگشت از جدول پایین) و ممکن است روی سایت‌های بزرگ کمی طول بکشد. ادامه می‌دهید؟');">
						<input type="hidden" name="action" value="gv_wm_bulk_apply">
						<?php wp_nonce_field( GV_WM_NONCE ); ?>
						<button type="submit" class="gvwm-btn" <?php disabled( empty( $s['enabled'] ) || ! $s['logo_id'] ); ?>>🚀 اعمال واترمارک روی تصاویر موجود</button>
					</form>
					<?php if ( empty( $s['enabled'] ) || ! $s['logo_id'] ) : ?>
						<p class="gvwm-hint">ابتدا واترمارک را فعال و یک لوگو انتخاب کنید.</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="gvwm-preview-wrap">
				<h2 style="margin-top:0;font-size:15px;">👁️ پیش‌نمایش</h2>
				<div id="gvwm-preview-box">
					<span>نمونه تصویر سایت</span>
					<img id="gvwm-preview-logo" src="<?php echo esc_url( $logo_url ?: '' ); ?>">
				</div>
				<p class="gvwm-hint">این فقط یک شبیه‌سازی تقریبی است تا حس موقعیت/اندازه/شفافیت را بگیرید؛ نتیجه‌ی واقعی روی خودِ عکس‌ها اعمال می‌شود.</p>
			</div>
		</div>

		<div class="gvwm-card">
			<h2>📋 تصاویر واترمارک‌خورده (قابل بازگرداندن)</h2>
			<?php if ( empty( $log_rows ) ) : ?>
				<p class="gvwm-muted">هنوز هیچ تصویری واترمارک نخورده است.</p>
			<?php else : ?>
				<div class="gvwm-table-scroll">
				<table class="gvwm-table">
					<thead>
						<tr>
							<th>تصویر</th>
							<th>نام فایل</th>
							<th>تاریخ واترمارک</th>
							<th>حجم پشتیبان</th>
							<th>عملیات</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $log_rows as $row ) : ?>
						<tr>
							<td><?php echo wp_get_attachment_image( $row->attachment_id, array( 40, 40 ), false, array( 'class' => 'gvwm-thumb' ) ); ?></td>
							<td><?php echo esc_html( $row->file_name ); ?></td>
							<td><?php echo esc_html( $row->applied_at ); ?></td>
							<td><?php echo ( $row->backup_path && file_exists( $row->backup_path ) ) ? esc_html( gv_wm_format_size( filesize( $row->backup_path ) ) ) : '<span class="gvwm-muted">—</span>'; ?></td>
							<td style="white-space:nowrap;">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
									<input type="hidden" name="action" value="gv_wm_restore_one">
									<input type="hidden" name="attachment_id" value="<?php echo esc_attr( $row->attachment_id ); ?>">
									<?php wp_nonce_field( GV_WM_NONCE ); ?>
									<button type="submit" class="gvwm-btn gvwm-btn-secondary gvwm-btn-small">↩️ بازگرداندن</button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" onsubmit="return confirm('نسخه‌ی پشتیبان حذف می‌شود و دیگر امکان بازگرداندن این تصویر نخواهید داشت. مطمئنید؟');">
									<input type="hidden" name="action" value="gv_wm_delete_backup">
									<input type="hidden" name="log_id" value="<?php echo esc_attr( $row->id ); ?>">
									<?php wp_nonce_field( GV_WM_NONCE ); ?>
									<button type="submit" class="gvwm-btn gvwm-btn-danger gvwm-btn-small">🗑️ حذف پشتیبان</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php endif; ?>
		</div>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p>
	</div>
	<?php
}
