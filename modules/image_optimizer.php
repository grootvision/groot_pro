<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — بهینه‌ساز خودکار تصاویر
 *  ------------------------------------------------------------
 *  نحوه‌ی کار:
 *  ۱) هر تصویری که آپلود می‌شود، اگر حجمش از آستانه‌ی تعیین‌شده
 *     (پیش‌فرض ۷۰۰ کیلوبایت) بیشتر باشد، بلافاصله فشرده می‌شود؛
 *     ابعاد دست‌نخورده می‌ماند و فقط کیفیت/حجم فایل کم می‌شود،
 *     بنابراین همان لحظه نسخه‌ی سبک‌تر روی سایت قرار می‌گیرد.
 *  ۲) قبل از فشرده‌سازی، از فایل اصلیِ سنگین یک نسخه‌ی پشتیبان
 *     موقت گرفته می‌شود (برای احتیاط، تا چیزی از دست نرود).
 *  ۳) این نسخه‌ی پشتیبان در یک داشبورد اختصاصی با تامبنیل، نام و
 *     حجم فایل لیست می‌شود و ادمین می‌تواند همان لحظه حذفش کند.
 *  ۴) اگر ادمین کاری نکند، دقیقاً ۱۵ دقیقه (قابل تغییر) بعد از
 *     آپلود، به‌صورت خودکار توسط WP-Cron پاک می‌شود تا فضای
 *     هاست هدر نرود.
 *
 *  + بخش دوم همین فایل (پایین‌تر): ماژول «همگام‌ساز عنوان و آلت
 *    تصاویر» که عنوان/آلت تصاویر داخل هر محتوا را با عنوان همان
 *    محتوا یکی نگه می‌دارد و امکان اسکن/ممیزی کل سایت را می‌دهد.
 * ==========================================================
 */

define( 'GV_IMGOPT_OPT', 'gv_image_optimizer_settings' );
define( 'GV_IMGOPT_NONCE', 'gv_imgopt_nonce_action' );
define( 'GV_IMGOPT_DB_VERSION', '1.0' );
define( 'GV_IMGOPT_PAGE_SLUG', 'gv-image-optimizer' );
define( 'GV_IMGOPT_CRON_HOOK', 'gv_imgopt_auto_clean_event' );
define( 'GV_IMGOPT_BACKUP_DIR', 'gv-imgopt-backup' );

/* ==========================================================================
   ۰) تنظیمات پیش‌فرض
   ========================================================================== */
function gv_imgopt_default_settings() {
	return array(
		'enabled'           => 1,
		'threshold_kb'      => 700,
		'quality'           => 82,
		'retention_minutes' => 15,
	);
}

function gv_imgopt_get_settings() {
	return wp_parse_args( get_option( GV_IMGOPT_OPT, array() ), gv_imgopt_default_settings() );
}

/* ==========================================================================
   ۱) ساخت جدول دیتابیس
   ========================================================================== */
add_action( 'plugins_loaded', 'gv_imgopt_maybe_install_db' );
function gv_imgopt_maybe_install_db() {
	if ( get_option( 'gv_imgopt_db_version' ) === GV_IMGOPT_DB_VERSION ) { return; }

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table = $wpdb->prefix . 'gv_image_optimizer_log';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		attachment_id BIGINT UNSIGNED NOT NULL,
		file_name VARCHAR(255) NOT NULL,
		mime_type VARCHAR(60) NOT NULL DEFAULT '',
		original_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
		optimized_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
		backup_path TEXT NULL,
		uploaded_at DATETIME NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'pending',
		cleaned_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY attachment_id (attachment_id),
		KEY status (status)
	) {$charset_collate};" );

	update_option( 'gv_imgopt_db_version', GV_IMGOPT_DB_VERSION );
}

/* ==========================================================================
   ۲) پوشه‌ی نسخه‌های پشتیبان (خارج از دید عمومی)
   ========================================================================== */
function gv_imgopt_backup_dir() {
	$upload_dir = wp_upload_dir();
	$dir = trailingslashit( $upload_dir['basedir'] ) . GV_IMGOPT_BACKUP_DIR;
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		@file_put_contents( $dir . '/.htaccess', "Options -Indexes\nDeny from all\n" );
	}
	return $dir;
}

/* ==========================================================================
   ۳) بهینه‌سازی خودکار هنگام آپلود
   ------------------------------------------------------------------------
   از فیلتر wp_generate_attachment_metadata استفاده می‌کنیم چون در این
   مرحله فایل روی هاست ذخیره شده و سایزهای thumbnail هم ساخته شده‌اند؛
   ما فقط فایل اصلی (full size) را با همان ابعاد فشرده می‌کنیم.
   ========================================================================== */
add_filter( 'wp_generate_attachment_metadata', 'gv_imgopt_handle_new_upload', 20, 2 );
function gv_imgopt_handle_new_upload( $metadata, $attachment_id ) {
	$s = gv_imgopt_get_settings();
	if ( empty( $s['enabled'] ) ) { return $metadata; }
	if ( ! wp_attachment_is_image( $attachment_id ) ) { return $metadata; }

	$mime = get_post_mime_type( $attachment_id );
	// روی گیف (احتمال انیمیشن) و فرمت‌های دیگر دست نمی‌زنیم؛ فقط jpeg/png/webp
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) { return $metadata; }

	clearstatcache();
	$original_size    = filesize( $file );
	$threshold_bytes  = max( 1, intval( $s['threshold_kb'] ) ) * 1024;

	if ( $original_size <= $threshold_bytes ) {
		return $metadata; // حجم کمتر از آستانه است، نیازی به بهینه‌سازی نیست
	}

	// ۱) پشتیبان‌گیری از فایل اصلی و سنگین، پیش از هر تغییری
	$backup_dir  = gv_imgopt_backup_dir();
	$backup_name = $attachment_id . '-' . wp_unique_filename( $backup_dir, basename( $file ) );
	$backup_path = trailingslashit( $backup_dir ) . $backup_name;

	if ( ! @copy( $file, $backup_path ) ) {
		return $metadata; // اگر پشتیبان‌گیری ناموفق بود، ریسک نمی‌کنیم و فایل اصلی دست‌نخورده می‌ماند
	}

	// ۲) فشرده‌سازی فایل اصلی، دقیقاً با همان ابعاد (بدون resize)
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		@unlink( $backup_path );
		return $metadata;
	}

	$quality = max( 50, min( 100, intval( $s['quality'] ) ) );
	$editor->set_quality( $quality );
	$saved = $editor->save( $file );

	if ( is_wp_error( $saved ) ) {
		@unlink( $backup_path );
		return $metadata;
	}

	clearstatcache();
	$optimized_size = file_exists( $file ) ? filesize( $file ) : $original_size;

	// اگر عملاً حجمی کم نشد (به‌ندرت پیش می‌آید)، پشتیبان لازم نیست نگه داریم
	if ( $optimized_size >= $original_size ) {
		@unlink( $backup_path );
		return $metadata;
	}

	if ( isset( $metadata['filesize'] ) ) {
		$metadata['filesize'] = $optimized_size;
	}

	// ۳) ثبت در دیتابیس برای نمایش در داشبورد + زمان‌بندی پاک‌سازی خودکار
	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( $wpdb->prefix . 'gv_image_optimizer_log', array(
		'attachment_id'  => $attachment_id,
		'file_name'      => basename( $file ),
		'mime_type'      => $mime,
		'original_size'  => $original_size,
		'optimized_size' => $optimized_size,
		'backup_path'    => $backup_path,
		'uploaded_at'    => $now,
		'status'         => 'pending',
	) );
	$log_id = $wpdb->insert_id;

	$retention_minutes = max( 1, intval( $s['retention_minutes'] ) );
	wp_schedule_single_event( time() + ( $retention_minutes * MINUTE_IN_SECONDS ), GV_IMGOPT_CRON_HOOK, array( $log_id ) );

	return $metadata;
}

/* ==========================================================================
   ۴) پاک‌سازی خودکار نسخه‌ی پشتیبان، دقیقاً بعد از مدت تعیین‌شده
   ========================================================================== */
add_action( GV_IMGOPT_CRON_HOOK, 'gv_imgopt_auto_clean_callback' );
function gv_imgopt_auto_clean_callback( $log_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gv_image_optimizer_log WHERE id = %d", intval( $log_id ) ) );
	if ( ! $row || 'pending' !== $row->status ) { return; } // ادمین قبلاً خودش رسیدگی کرده

	if ( $row->backup_path && file_exists( $row->backup_path ) ) {
		wp_delete_file( $row->backup_path );
	}

	$wpdb->update( $wpdb->prefix . 'gv_image_optimizer_log',
		array( 'status' => 'cleaned', 'cleaned_at' => current_time( 'mysql' ) ),
		array( 'id' => $row->id )
	);
}

/* ==========================================================================
   ۵) حذف دستی نسخه پشتیبان از داخل داشبورد (توسط ادمین)
   ========================================================================== */
add_action( 'admin_post_gv_imgopt_delete_backup', 'gv_imgopt_handle_manual_delete' );
function gv_imgopt_handle_manual_delete() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_IMGOPT_NONCE );

	global $wpdb;
	$log_id = intval( $_POST['log_id'] ?? 0 );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gv_image_optimizer_log WHERE id = %d", $log_id ) );

	if ( $row && 'pending' === $row->status ) {
		if ( $row->backup_path && file_exists( $row->backup_path ) ) {
			wp_delete_file( $row->backup_path );
		}
		$wpdb->update( $wpdb->prefix . 'gv_image_optimizer_log',
			array( 'status' => 'deleted', 'cleaned_at' => current_time( 'mysql' ) ),
			array( 'id' => $row->id )
		);
		$timestamp = wp_next_scheduled( GV_IMGOPT_CRON_HOOK, array( $row->id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, GV_IMGOPT_CRON_HOOK, array( $row->id ) );
		}
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_IMGOPT_PAGE_SLUG . '&deleted=1' ) );
	exit;
}

/* ==========================================================================
   ۶) ذخیره تنظیمات
   ========================================================================== */
add_action( 'admin_post_gv_imgopt_save_settings', 'gv_imgopt_save_settings' );
function gv_imgopt_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_IMGOPT_NONCE );

	$settings = array(
		'enabled'           => isset( $_POST['enabled'] ) ? 1 : 0,
		'threshold_kb'      => max( 50, intval( $_POST['threshold_kb'] ?? 700 ) ),
		'quality'           => max( 50, min( 100, intval( $_POST['quality'] ?? 82 ) ) ),
		'retention_minutes' => max( 1, min( 1440, intval( $_POST['retention_minutes'] ?? 15 ) ) ),
	);
	update_option( GV_IMGOPT_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_IMGOPT_PAGE_SLUG . '&updated=1' ) );
	exit;
}

/* ==========================================================================
   ۷) اگر پیوست به‌طور کامل از کتابخانه رسانه حذف شود، پشتیبان مربوطه هم پاک شود
   ========================================================================== */
add_action( 'delete_attachment', 'gv_imgopt_on_attachment_deleted' );
function gv_imgopt_on_attachment_deleted( $attachment_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}gv_image_optimizer_log WHERE attachment_id = %d AND status = 'pending'", $attachment_id
	) );
	foreach ( $rows as $row ) {
		if ( $row->backup_path && file_exists( $row->backup_path ) ) {
			wp_delete_file( $row->backup_path );
		}
		$timestamp = wp_next_scheduled( GV_IMGOPT_CRON_HOOK, array( $row->id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, GV_IMGOPT_CRON_HOOK, array( $row->id ) );
		}
	}
	$wpdb->update( $wpdb->prefix . 'gv_image_optimizer_log',
		array( 'status' => 'deleted', 'cleaned_at' => current_time( 'mysql' ) ),
		array( 'attachment_id' => $attachment_id, 'status' => 'pending' )
	);
}

/* ==========================================================================
   ۸) منوی مدیریت (زیرمنوی داشبورد اصلی گروت ویژن)
   ========================================================================== */
add_action( 'admin_menu', 'gv_imgopt_admin_menu' );
function gv_imgopt_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'بهینه‌ساز تصاویر | Groot Vision',
		'🖼️ بهینه‌ساز تصاویر',
		'manage_options',
		GV_IMGOPT_PAGE_SLUG,
		'gv_imgopt_render_admin_page'
	);
}

/* ==========================================================================
   ۹) توابع کمکیِ نمایش
   ========================================================================== */
function gv_imgopt_format_size( $bytes ) {
	$bytes = (float) $bytes;
	if ( $bytes >= 1048576 ) { return number_format_i18n( $bytes / 1048576, 2 ) . ' مگابایت'; }
	if ( $bytes >= 1024 ) { return number_format_i18n( $bytes / 1024, 1 ) . ' کیلوبایت'; }
	return number_format_i18n( $bytes ) . ' بایت';
}

function gv_imgopt_time_left_label( $uploaded_at, $retention_minutes ) {
	$deadline = strtotime( $uploaded_at ) + ( $retention_minutes * 60 );
	$diff = $deadline - current_time( 'timestamp' );
	if ( $diff <= 0 ) { return 'در صف پاک‌سازی خودکار...'; }
	$minutes = (int) ceil( $diff / 60 );
	return 'حدود ' . number_format_i18n( $minutes ) . ' دقیقه‌ی دیگر';
}

/* ==========================================================================
   ۱۰) رندر صفحه مدیریت
   ========================================================================== */
function gv_imgopt_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$s = gv_imgopt_get_settings();

	$pending = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gv_image_optimizer_log WHERE status = 'pending' ORDER BY uploaded_at DESC" );

	$stats = $wpdb->get_row( "SELECT COUNT(*) as total_count, SUM(original_size - optimized_size) as total_saved FROM {$wpdb->prefix}gv_image_optimizer_log" );
	$pending_size = 0;
	foreach ( $pending as $p ) { $pending_size += (int) $p->original_size; }
	?>
	<div class="wrap" dir="rtl" style="font-family:'Vazirmatn',Tahoma,sans-serif;max-width:1100px;">
		<style>
			.gvio-header{background:linear-gradient(120deg,#0e4037,#145c4d);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;}
			.gvio-header h1{margin:0;font-size:20px;color:#fff;}
			.gvio-header p{margin:8px 0 0;font-size:13px;color:#cbd5e1;line-height:1.9;}
			.gvio-stat-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;}
			@media(max-width:900px){.gvio-stat-cards{grid-template-columns:1fr;}}
			.gvio-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px;}
			.gvio-stat b{display:block;font-size:22px;color:#0e4037;}
			.gvio-stat span{font-size:12.5px;color:#64748b;}
			.gvio-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin-bottom:18px;}
			.gvio-card h2{margin-top:0;font-size:15px;}
			.gvio-field{margin-bottom:14px;}
			.gvio-field label{font-weight:700;font-size:13px;display:block;margin-bottom:5px;}
			.gvio-field input[type=number]{width:150px;padding:9px 10px;border-radius:9px;border:1px solid #d1d5db;font-family:inherit;}
			.gvio-btn{background:#111827;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;}
			.gvio-btn-danger{background:#b91c1c;}
			.gvio-list-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:10px;flex-wrap:wrap;}
			.gvio-thumb{width:56px;height:56px;border-radius:10px;overflow:hidden;flex-shrink:0;background:#f1f5f4;display:flex;align-items:center;justify-content:center;}
			.gvio-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
			.gvio-list-info{flex:1;min-width:220px;}
			.gvio-list-info b{display:block;font-size:13px;margin-bottom:4px;word-break:break-all;color:#0f172a;}
			.gvio-list-info span{font-size:12px;color:#64748b;}
			.gvio-badge-time{background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;white-space:nowrap;}
		</style>

		<div class="gvio-header">
			<h1>🖼️ بهینه‌ساز خودکار تصاویر</h1>
			<p>
				تصاویری که حجم‌شان بیشتر از <?php echo esc_html( number_format_i18n( $s['threshold_kb'] ) ); ?> کیلوبایت باشد، همان لحظه‌ی آپلود با همان ابعاد فشرده می‌شوند تا کیفیت خراب نشود.
				نسخه‌ی اصلیِ سنگین به‌عنوان پشتیبان نگه داشته می‌شود و اگر خودتان زودتر حذفش نکنید، حداکثر <?php echo esc_html( number_format_i18n( $s['retention_minutes'] ) ); ?> دقیقه بعد به‌صورت خودکار پاک می‌شود.
			</p>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p>نسخه پشتیبان با موفقیت حذف شد.</p></div><?php endif; ?>

		<div class="gvio-stat-cards">
			<div class="gvio-stat"><b><?php echo esc_html( number_format_i18n( $stats->total_count ?: 0 ) ); ?></b><span>تصویر بهینه‌سازی‌شده تاکنون</span></div>
			<div class="gvio-stat"><b><?php echo esc_html( gv_imgopt_format_size( $stats->total_saved ?: 0 ) ); ?></b><span>فضای صرفه‌جویی‌شده از فشرده‌سازی</span></div>
			<div class="gvio-stat"><b style="color:#b45309;"><?php echo esc_html( number_format_i18n( count( $pending ) ) ); ?></b><span>پشتیبان در انتظار پاک‌سازی (<?php echo esc_html( gv_imgopt_format_size( $pending_size ) ); ?> روی هاست)</span></div>
		</div>

		<div class="gvio-card">
			<h2>⚙️ تنظیمات</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gv_imgopt_save_settings">
				<?php wp_nonce_field( GV_IMGOPT_NONCE ); ?>
				<div class="gvio-field"><label><input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>> فعال‌سازی بهینه‌سازی خودکار تصاویر</label></div>
				<div class="gvio-field">
					<label>حداقل حجم برای بهینه‌سازی (کیلوبایت)</label>
					<input type="number" name="threshold_kb" min="50" value="<?php echo esc_attr( $s['threshold_kb'] ); ?>">
				</div>
				<div class="gvio-field">
					<label>کیفیت فشرده‌سازی از ۵۰ تا ۱۰۰ (پیشنهادی: ۸۰ تا ۸۵ — عدد بالاتر یعنی کیفیت بهتر و حجم بیشتر)</label>
					<input type="number" name="quality" min="50" max="100" value="<?php echo esc_attr( $s['quality'] ); ?>">
				</div>
				<div class="gvio-field">
					<label>مدت نگهداری نسخه پشتیبان قبل از پاک‌سازی خودکار (دقیقه)</label>
					<input type="number" name="retention_minutes" min="1" max="1440" value="<?php echo esc_attr( $s['retention_minutes'] ); ?>">
				</div>
				<p style="font-size:12px;color:#94a3b8;">توجه: پاک‌سازی خودکار توسط زمان‌بند وردپرس (WP-Cron) انجام می‌شود و مثل بقیه‌ی زمان‌بندی‌های وردپرس، به بازدید سایت وابسته است.</p>
				<button type="submit" class="gvio-btn">💾 ذخیره تنظیمات</button>
			</form>
		</div>

		<div class="gvio-card">
			<h2>📋 نسخه‌های پشتیبانِ سنگین در انتظار پاک‌سازی</h2>
			<?php if ( empty( $pending ) ) : ?>
				<p style="color:#94a3b8;">در حال حاضر هیچ نسخه پشتیبان سنگینی در انتظار پاک‌سازی نیست.</p>
			<?php else : ?>
				<?php foreach ( $pending as $row ) : ?>
					<div class="gvio-list-item">
						<div class="gvio-thumb"><?php echo wp_get_attachment_image( $row->attachment_id, array( 56, 56 ) ); ?></div>
						<div class="gvio-list-info">
							<b><?php echo esc_html( $row->file_name ); ?></b>
							<span>حجم اصلی: <?php echo esc_html( gv_imgopt_format_size( $row->original_size ) ); ?> ← بعد از فشرده‌سازی: <?php echo esc_html( gv_imgopt_format_size( $row->optimized_size ) ); ?> | آپلود: <?php echo esc_html( $row->uploaded_at ); ?></span>
						</div>
						<span class="gvio-badge-time"><?php echo esc_html( gv_imgopt_time_left_label( $row->uploaded_at, $s['retention_minutes'] ) ); ?></span>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('نسخه پشتیبان سنگین این تصویر همین الان حذف شود؟');">
							<input type="hidden" name="action" value="gv_imgopt_delete_backup">
							<input type="hidden" name="log_id" value="<?php echo esc_attr( $row->id ); ?>">
							<?php wp_nonce_field( GV_IMGOPT_NONCE ); ?>
							<button type="submit" class="gvio-btn gvio-btn-danger">🗑️ حذف الان</button>
						</form>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p>
	</div>
	<?php
}


/* ==========================================================================
   ==========================================================================
    از این‌جا به بعد: ماژول «همگام‌ساز عنوان و آلت تصاویر»
   ==========================================================================
   ------------------------------------------------------------
   نحوه‌ی کار:
   ۱) وقتی یک پست/صفحه ذخیره می‌شود، تمام تصاویری که داخل محتوایش
      استفاده شده (چه از ادیتور معمولی/گوتنبرگ، چه از المنتور)
      پیدا می‌شوند و عنوان + متن جایگزین (Alt) هرکدام دقیقاً برابر
      با عنوان همان پست/صفحه تنظیم می‌شود.
   ۲) یک دکمه‌ی «اسکن سایت» وجود دارد که کل محتوای سایت را بررسی
      می‌کند و هر تصویری که عنوان/آلتش با عنوان محتوایی که در آن
      استفاده شده یکی نباشد را به همراه لینک ادیت تصویر، لینک ادیت
      محتوا، نام فایل، و نویسنده‌ی محتوا در یک لیست نشان می‌دهد.
   ========================================================================== */

define( 'GV_IMGSYNC_OPT', 'gv_image_title_sync_settings' );
define( 'GV_IMGSYNC_NONCE', 'gv_imgsync_nonce_action' );
define( 'GV_IMGSYNC_PAGE_SLUG', 'gv-image-title-sync' );
define( 'GV_IMGSYNC_TRANSIENT', 'gv_imgsync_audit_results' );

/* ==========================================================================
   ۰) تنظیمات پیش‌فرض
   ========================================================================== */
function gv_imgsync_default_settings() {
	return array(
		'enabled'        => 1,
		'sync_title'     => 1,
		'sync_alt'       => 1,
		'sync_featured'  => 1,
		'post_types'     => array( 'post', 'page' ),
	);
}

function gv_imgsync_get_settings() {
	$s = wp_parse_args( get_option( GV_IMGSYNC_OPT, array() ), gv_imgsync_default_settings() );
	if ( ! is_array( $s['post_types'] ) || empty( $s['post_types'] ) ) {
		$s['post_types'] = array( 'post', 'page' );
	}
	return $s;
}

/* ==========================================================================
   ۱) استخراج شناسه‌ی تصاویر استفاده‌شده در یک محتوا
   ------------------------------------------------------------------------
   الف) از متن HTML (ادیتور کلاسیک / گوتنبرگ): از روی class="wp-image-123"
        که وردپرس هنگام درج تصویر از کتابخانه‌ی رسانه اضافه می‌کند.
   ب)  از داده‌ی المنتور (_elementor_data که JSON است): به‌صورت بازگشتی
        دنبال آرایه‌هایی می‌گردیم که همزمان کلید id و url دارند و url
        به یک فایل آپلودی اشاره می‌کند (الگوی استاندارد کنترل تصویر المنتور).
   ========================================================================== */
function gv_imgsync_extract_ids_from_html( $content ) {
	$ids = array();
	if ( ! is_string( $content ) || '' === $content ) { return $ids; }
	if ( preg_match_all( '/wp-image-(\d+)/', $content, $m ) ) {
		foreach ( $m[1] as $id ) { $ids[] = intval( $id ); }
	}
	return $ids;
}

function gv_imgsync_walk_elementor_node( $node, array &$ids ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['id'], $node['url'] ) && is_numeric( $node['id'] ) && is_string( $node['url'] )
			&& false !== strpos( $node['url'], '/wp-content/uploads/' ) ) {
			$ids[] = intval( $node['id'] );
		}
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				gv_imgsync_walk_elementor_node( $value, $ids );
			}
		}
	}
}

function gv_imgsync_extract_ids_from_elementor( $post_id ) {
	$ids = array();
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( empty( $raw ) ) { return $ids; }
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		// بعضی نسخه‌ها اسلش زده ذخیره می‌کنند
		$data = json_decode( wp_unslash( $raw ), true );
	}
	if ( is_array( $data ) ) {
		gv_imgsync_walk_elementor_node( $data, $ids );
	}
	return $ids;
}

/**
 * تمام شناسه‌های تصاویرِ (فقط عکس، نه پیوست‌های دیگر) استفاده‌شده در یک محتوا
 */
function gv_imgsync_collect_image_ids( $post_id, $content, $include_featured = true ) {
	$ids = array_merge(
		gv_imgsync_extract_ids_from_html( $content ),
		gv_imgsync_extract_ids_from_elementor( $post_id )
	);

	if ( $include_featured ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) { $ids[] = intval( $thumb_id ); }
	}

	$ids = array_unique( array_filter( $ids ) );
	// فقط پیوست‌هایی که واقعاً تصویر هستند نگه داشته شوند
	$ids = array_values( array_filter( $ids, function ( $id ) {
		return 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id );
	} ) );
	return $ids;
}

/* ==========================================================================
   ۲) همگام‌سازی خودکار هنگام ذخیره‌ی پست/صفحه
   ========================================================================== */
add_action( 'save_post', 'gv_imgsync_sync_post_images', 25, 2 );
function gv_imgsync_sync_post_images( $post_id, $post ) {
	$s = gv_imgsync_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) { return; }
	if ( ! in_array( $post->post_type, $s['post_types'], true ) ) { return; }
	if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) { return; }

	$title = trim( $post->post_title );
	if ( '' === $title ) { return; } // بدون عنوان، همگام‌سازی معنا ندارد

	$image_ids = gv_imgsync_collect_image_ids( $post_id, $post->post_content, ! empty( $s['sync_featured'] ) );
	foreach ( $image_ids as $image_id ) {
		gv_imgsync_apply_title_to_attachment( $image_id, $title, $s );
	}
}

function gv_imgsync_apply_title_to_attachment( $attachment_id, $title, $s = null ) {
	if ( null === $s ) { $s = gv_imgsync_get_settings(); }

	if ( ! empty( $s['sync_title'] ) ) {
		$current = get_the_title( $attachment_id );
		if ( $current !== $title ) {
			wp_update_post( array(
				'ID'         => $attachment_id,
				'post_title' => $title,
			) );
		}
	}

	if ( ! empty( $s['sync_alt'] ) ) {
		$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( $current_alt !== $title ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags( $title, true ) );
		}
	}
}

/* ==========================================================================
   ۳) اسکن کل سایت (ممیزی) — پیدا کردن عکس‌هایی که عنوان/آلت‌شان با محتوا نمی‌خواند
   ========================================================================== */
function gv_imgsync_run_audit() {
	$s = gv_imgsync_get_settings();
	$results = array();

	$query = new WP_Query( array(
		'post_type'      => ! empty( $s['post_types'] ) ? $s['post_types'] : array( 'post', 'page' ),
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
	) );

	foreach ( $query->posts as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) { continue; }
		$title = trim( $post->post_title );
		if ( '' === $title ) { continue; }

		$image_ids = gv_imgsync_collect_image_ids( $post_id, $post->post_content, ! empty( $s['sync_featured'] ) );
		if ( empty( $image_ids ) ) { continue; }

		$author = get_userdata( $post->post_author );

		foreach ( $image_ids as $image_id ) {
			$img_title = get_the_title( $image_id );
			$img_alt   = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

			$title_mismatch = ! empty( $s['sync_title'] ) && ( $img_title !== $title );
			$alt_mismatch   = ! empty( $s['sync_alt'] ) && ( $img_alt !== $title );

			if ( ! $title_mismatch && ! $alt_mismatch ) { continue; }

			$file = get_attached_file( $image_id );

			$results[] = array(
				'post_id'        => $post_id,
				'post_title'     => $title,
				'post_type'      => $post->post_type,
				'post_edit_link' => get_edit_post_link( $post_id, '' ),
				'post_view_link' => get_permalink( $post_id ),
				'author_name'    => $author ? $author->display_name : '—',
				'image_id'       => $image_id,
				'image_title'    => $img_title,
				'image_alt'      => $img_alt,
				'image_file'     => $file ? basename( $file ) : '',
				'image_edit_link'=> get_edit_post_link( $image_id, '' ),
				'title_mismatch' => $title_mismatch,
				'alt_mismatch'   => $alt_mismatch,
			);
		}
	}

	set_transient( GV_IMGSYNC_TRANSIENT, array(
		'items'      => $results,
		'scanned_at' => current_time( 'mysql' ),
	), DAY_IN_SECONDS );

	return $results;
}

add_action( 'admin_post_gv_imgsync_run_scan', 'gv_imgsync_handle_run_scan' );
function gv_imgsync_handle_run_scan() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_IMGSYNC_NONCE );
	gv_imgsync_run_audit();
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_IMGSYNC_PAGE_SLUG . '&scanned=1' ) );
	exit;
}

/**
 * تصحیح دستی یک مورد مستقیماً از داخل لیست ممیزی (بدون نیاز به ورود به ادیتور پیوست)
 */
add_action( 'admin_post_gv_imgsync_fix_one', 'gv_imgsync_handle_fix_one' );
function gv_imgsync_handle_fix_one() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_IMGSYNC_NONCE );

	$image_id = intval( $_POST['image_id'] ?? 0 );
	$title    = sanitize_text_field( $_POST['post_title'] ?? '' );
	if ( $image_id && '' !== $title ) {
		gv_imgsync_apply_title_to_attachment( $image_id, $title );
		// این مورد را از نتایج ذخیره‌شده‌ی ممیزی هم حذف کن تا لیست بلافاصله به‌روز شود
		$cached = get_transient( GV_IMGSYNC_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['items'] ) ) {
			$cached['items'] = array_values( array_filter( $cached['items'], function ( $row ) use ( $image_id ) {
				return intval( $row['image_id'] ) !== $image_id;
			} ) );
			set_transient( GV_IMGSYNC_TRANSIENT, $cached, DAY_IN_SECONDS );
		}
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_IMGSYNC_PAGE_SLUG . '&fixed=1' ) );
	exit;
}

/* ==========================================================================
   ۴) ذخیره تنظیمات
   ========================================================================== */
add_action( 'admin_post_gv_imgsync_save_settings', 'gv_imgsync_save_settings' );
function gv_imgsync_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_IMGSYNC_NONCE );

	$post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
		? array_map( 'sanitize_key', $_POST['post_types'] )
		: array( 'post', 'page' );

	$settings = array(
		'enabled'       => isset( $_POST['enabled'] ) ? 1 : 0,
		'sync_title'    => isset( $_POST['sync_title'] ) ? 1 : 0,
		'sync_alt'      => isset( $_POST['sync_alt'] ) ? 1 : 0,
		'sync_featured' => isset( $_POST['sync_featured'] ) ? 1 : 0,
		'post_types'    => $post_types,
	);
	update_option( GV_IMGSYNC_OPT, $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_IMGSYNC_PAGE_SLUG . '&updated=1' ) );
	exit;
}

/* ==========================================================================
   ۵) منوی مدیریت
   ========================================================================== */
add_action( 'admin_menu', 'gv_imgsync_admin_menu' );
function gv_imgsync_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'همگام‌ساز عنوان تصاویر | Groot Vision',
		'🏷️ همگام‌ساز عنوان تصاویر',
		'manage_options',
		GV_IMGSYNC_PAGE_SLUG,
		'gv_imgsync_render_admin_page'
	);
}

/* ==========================================================================
   ۶) رندر صفحه مدیریت
   ========================================================================== */
/**
 * کوتاه‌کردن متن‌های طولانی برای نمایش تمیز داخل جدول.
 * متن کامل همیشه در attribute مربوطه (title) نگه داشته می‌شود تا با هاور موس دیده شود.
 */
function gv_imgsync_shorten( $text, $max_len = 28 ) {
	$text = trim( (string) $text );
	if ( '' === $text ) { return ''; }
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max_len ) {
		return mb_substr( $text, 0, $max_len ) . '…';
	}
	return $text;
}

function gv_imgsync_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = gv_imgsync_get_settings();

	$cached      = get_transient( GV_IMGSYNC_TRANSIENT );
	$results     = is_array( $cached ) && ! empty( $cached['items'] ) ? $cached['items'] : array();
	$scanned_at  = is_array( $cached ) ? ( $cached['scanned_at'] ?? '' ) : '';

	$post_type_objects = get_post_types( array( 'public' => true ), 'objects' );
	?>
	<div class="wrap" dir="rtl" style="font-family:'Vazirmatn',Tahoma,sans-serif;max-width:1100px;">
		<style>
			.gvis-header{background:linear-gradient(120deg,#3730a3,#4338ca);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;}
			.gvis-header h1{margin:0;font-size:20px;color:#fff;}
			.gvis-header p{margin:8px 0 0;font-size:13px;color:#e0e7ff;line-height:1.9;}
			.gvis-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin-bottom:18px;}
			.gvis-card h2{margin-top:0;font-size:15px;}
			.gvis-field{margin-bottom:12px;}
			.gvis-field label{font-weight:700;font-size:13px;}
			.gvis-checks{display:flex;flex-wrap:wrap;gap:16px;margin:10px 0;}
			.gvis-checks label{font-weight:400;font-size:13px;display:flex;align-items:center;gap:6px;}
			.gvis-btn{background:#111827;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;}
			.gvis-btn-scan{background:#4338ca;}
			.gvis-btn-small{padding:6px 14px;font-size:12px;border-radius:8px;}
			.gvis-table-scroll{width:100%;overflow-x:auto;border:1px solid #e5e7eb;border-radius:14px;}
			table.gvis-table{width:100%;min-width:840px;border-collapse:separate;border-spacing:0;font-size:12.5px;table-layout:fixed;}
			table.gvis-table col.gvis-col-content{width:20%;}
			table.gvis-table col.gvis-col-author{width:10%;}
			table.gvis-table col.gvis-col-image{width:18%;}
			table.gvis-table col.gvis-col-text{width:15%;}
			table.gvis-table col.gvis-col-mismatch{width:11%;}
			table.gvis-table col.gvis-col-action{width:11%;}
			table.gvis-table thead th{text-align:right;background:linear-gradient(180deg,#f8fafc,#eef1f7);color:#334155;font-size:11.5px;font-weight:800;letter-spacing:.2px;padding:12px 12px;border-bottom:2px solid #e2e8f0;}
			table.gvis-table thead th:first-child{border-top-right-radius:13px;}
			table.gvis-table thead th:last-child{border-top-left-radius:13px;}
			table.gvis-table td{padding:11px 12px;border-bottom:1px solid #eef1f4;vertical-align:middle;overflow:hidden;}
			table.gvis-table tbody tr{transition:background .12s ease;}
			table.gvis-table tbody tr:nth-child(even){background:#fafbff;}
			table.gvis-table tbody tr:hover{background:#eef2ff;}
			table.gvis-table tbody tr:last-child td{border-bottom:0;}
			.gvis-ellipsis{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;cursor:default;}
			a.gvis-ellipsis{color:#3730a3;text-decoration:none;font-weight:700;}
			a.gvis-ellipsis:hover{text-decoration:underline;}
			.gvis-thumb-wrap{display:flex;flex-direction:column;gap:6px;align-items:flex-start;max-width:100%;}
			.gvis-thumb-wrap img.gvis-thumb{width:40px;height:40px;object-fit:cover;border-radius:9px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(15,23,42,.12);}
			.gvis-tag{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:700;padding:4px 10px 4px 8px;border-radius:20px;margin:0 0 3px 4px;}
			.gvis-tag::before{content:"";width:6px;height:6px;border-radius:50%;flex-shrink:0;}
			.gvis-tag-title{background:#fee2e2;color:#991b1b;}
			.gvis-tag-title::before{background:#ef4444;}
			.gvis-tag-alt{background:#fef3c7;color:#92400e;}
			.gvis-tag-alt::before{background:#f59e0b;}
			.gvis-muted{color:#94a3b8;font-size:11.5px;}
		</style>

		<div class="gvis-header">
			<h1>🏷️ همگام‌ساز عنوان و آلت تصاویر</h1>
			<p>
				از این پس، هر عکسی که داخل محتوای یک پست یا صفحه (چه از ادیتور وردپرس، چه از المنتور) استفاده شود،
				عنوان و متن جایگزین (Alt) آن خودکار برابر با عنوان همان محتوا تنظیم می‌شود.
				با دکمه‌ی اسکن، می‌توانید محتوای قبلی سایت را هم بررسی و مغایرت‌ها را پیدا کنید.
			</p>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['scanned'] ) ) : ?><div class="notice notice-success is-dismissible"><p>اسکن سایت انجام شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['fixed'] ) ) : ?><div class="notice notice-success is-dismissible"><p>عنوان/آلت تصویر اصلاح شد.</p></div><?php endif; ?>

		<div class="gvis-card">
			<h2>⚙️ تنظیمات</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gv_imgsync_save_settings">
				<?php wp_nonce_field( GV_IMGSYNC_NONCE ); ?>
				<div class="gvis-field"><label><input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>> فعال‌سازی همگام‌سازی خودکار</label></div>
				<div class="gvis-field">
					<label>چه چیزی همگام‌سازی شود؟</label>
					<div class="gvis-checks">
						<label><input type="checkbox" name="sync_title" <?php checked( $s['sync_title'], 1 ); ?>> عنوان تصویر</label>
						<label><input type="checkbox" name="sync_alt" <?php checked( $s['sync_alt'], 1 ); ?>> متن جایگزین (Alt)</label>
						<label><input type="checkbox" name="sync_featured" <?php checked( $s['sync_featured'], 1 ); ?>> شامل تصویر شاخص هم بشود</label>
					</div>
				</div>
				<div class="gvis-field">
					<label>روی کدام نوع محتوا اعمال شود؟</label>
					<div class="gvis-checks">
						<?php foreach ( $post_type_objects as $pt_slug => $pt_obj ) :
							if ( 'attachment' === $pt_slug ) { continue; }
						?>
							<label><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt_slug ); ?>" <?php checked( in_array( $pt_slug, $s['post_types'], true ) ); ?>> <?php echo esc_html( $pt_obj->labels->name ); ?></label>
						<?php endforeach; ?>
					</div>
				</div>
				<button type="submit" class="gvis-btn">💾 ذخیره تنظیمات</button>
			</form>
		</div>

		<div class="gvis-card">
			<h2>🔍 اسکن سایت</h2>
			<p class="gvis-muted">
				<?php if ( $scanned_at ) : ?>
					آخرین اسکن: <?php echo esc_html( $scanned_at ); ?> — <?php echo esc_html( count( $results ) ); ?> مورد مغایرت پیدا شد.
				<?php else : ?>
					هنوز اسکنی انجام نشده است.
				<?php endif; ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('این کار ممکن است روی سایت‌های بزرگ کمی طول بکشد. ادامه می‌دهید؟');">
				<input type="hidden" name="action" value="gv_imgsync_run_scan">
				<?php wp_nonce_field( GV_IMGSYNC_NONCE ); ?>
				<button type="submit" class="gvis-btn gvis-btn-scan">🔍 اسکن مجدد سایت</button>
			</form>
		</div>

		<div class="gvis-card">
			<h2>📋 عکس‌هایی که عنوان/آلت‌شان با عنوان محتوا یکی نیست</h2>
			<?php if ( empty( $results ) ) : ?>
				<p class="gvis-muted">موردی برای نمایش نیست — یا هنوز اسکن نشده، یا همه چیز مرتب است.</p>
			<?php else : ?>
				<div class="gvis-table-scroll">
				<table class="gvis-table">
					<colgroup>
						<col class="gvis-col-content">
						<col class="gvis-col-author">
						<col class="gvis-col-image">
						<col class="gvis-col-text">
						<col class="gvis-col-text">
						<col class="gvis-col-mismatch">
						<col class="gvis-col-action">
					</colgroup>
					<thead>
						<tr>
							<th>محتوا</th>
							<th>نویسنده محتوا</th>
							<th>تصویر</th>
							<th>عنوان فعلی تصویر</th>
							<th>آلت فعلی تصویر</th>
							<th>مغایرت</th>
							<th>عملیات</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $results as $row ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( $row['post_edit_link'] ); ?>" target="_blank" class="gvis-ellipsis" title="<?php echo esc_attr( $row['post_title'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $row['post_title'], 26 ) ); ?></a><br>
								<span class="gvis-muted"><?php echo esc_html( $row['post_type'] ); ?> · <a href="<?php echo esc_url( $row['post_view_link'] ); ?>" target="_blank">مشاهده</a></span>
							</td>
							<td><span class="gvis-ellipsis" title="<?php echo esc_attr( $row['author_name'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $row['author_name'], 14 ) ); ?></span></td>
							<td>
								<div class="gvis-thumb-wrap">
									<?php echo wp_get_attachment_image( $row['image_id'], array( 44, 44 ), false, array( 'class' => 'gvis-thumb' ) ); ?>
									<a href="<?php echo esc_url( $row['image_edit_link'] ); ?>" target="_blank" class="gvis-muted gvis-ellipsis" title="<?php echo esc_attr( $row['image_file'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $row['image_file'], 20 ) ); ?></a>
								</div>
							</td>
							<td>
								<?php if ( $row['image_title'] ) : ?>
									<span class="gvis-ellipsis" title="<?php echo esc_attr( $row['image_title'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $row['image_title'], 24 ) ); ?></span>
								<?php else : ?>
									<span class="gvis-muted">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $row['image_alt'] ) : ?>
									<span class="gvis-ellipsis" title="<?php echo esc_attr( $row['image_alt'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $row['image_alt'], 24 ) ); ?></span>
								<?php else : ?>
									<span class="gvis-muted">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $row['title_mismatch'] ) : ?><span class="gvis-tag gvis-tag-title">عنوان</span><?php endif; ?>
								<?php if ( $row['alt_mismatch'] ) : ?><span class="gvis-tag gvis-tag-alt">آلت</span><?php endif; ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="gv_imgsync_fix_one">
									<input type="hidden" name="image_id" value="<?php echo esc_attr( $row['image_id'] ); ?>">
									<input type="hidden" name="post_title" value="<?php echo esc_attr( $row['post_title'] ); ?>">
									<?php wp_nonce_field( GV_IMGSYNC_NONCE ); ?>
									<button type="submit" class="gvis-btn gvis-btn-small">✅ اصلاح خودکار</button>
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