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
    از این‌جا به بعد: ماژول «همگام‌ساز عنوان و آلت تصاویر» (نسخه‌ی دستی/خودکار)
   ==========================================================================
   ------------------------------------------------------------
   ⚠️ در فایل اصلی، همه‌چیز از خطِ define( 'GV_IMGSYNC_OPT' ... به بعد را
   پاک کنید و این کد را جایگزین کنید.

   نحوه‌ی کار:
   • دو حالت: «دستی» (پیش‌فرض) و «خودکار».
   • خودکار: هنگام ذخیره‌ی هر پست/صفحه، تصاویر داخلش همگام می‌شوند.
   • دستی: با دکمه‌ی «اسکن سایت» لیست تصاویرِ استفاده‌شده در محتوا ساخته
     می‌شود. لیست بر اساس تاریخ آپلود (روزانه) دسته‌بندی شده، هیچ تیکی
     به‌صورت پیش‌فرض ندارد و صفحه‌بندی دارد. تیک‌خورده‌ها با دکمه‌ی
     «همگام‌سازی انتخاب‌شده‌ها» یا کل یک روز با دکمه‌ی همان روز همگام می‌شوند.
   ========================================================================== */

define( 'GV_IMGSYNC_OPT', 'gv_image_title_sync_settings' );
define( 'GV_IMGSYNC_NONCE', 'gv_imgsync_nonce_action' );
define( 'GV_IMGSYNC_PAGE_SLUG', 'gv-image-title-sync' );
define( 'GV_IMGSYNC_TRANSIENT', 'gv_imgsync_usage_map' );

/* ==========================================================================
   ۰) تنظیمات
   ========================================================================== */
function gv_imgsync_default_settings() {
	return array(
		'mode'          => 'manual', // manual | auto
		'sync_title'    => 1,
		'sync_alt'      => 1,
		'sync_featured' => 1,
		'post_types'    => array( 'post', 'page' ),
	);
}

function gv_imgsync_get_settings() {
	$s = wp_parse_args( get_option( GV_IMGSYNC_OPT, array() ), gv_imgsync_default_settings() );
	if ( ! in_array( $s['mode'], array( 'manual', 'auto' ), true ) ) { $s['mode'] = 'manual'; }
	if ( ! is_array( $s['post_types'] ) || empty( $s['post_types'] ) ) {
		$s['post_types'] = array( 'post', 'page' );
	}
	return $s;
}

/* ==========================================================================
   ۱) استخراج شناسه‌ی تصاویر استفاده‌شده در یک محتوا
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
		$data = json_decode( wp_unslash( $raw ), true );
	}
	if ( is_array( $data ) ) {
		gv_imgsync_walk_elementor_node( $data, $ids );
	}
	return $ids;
}

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
	$ids = array_values( array_filter( $ids, function ( $id ) {
		return 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id );
	} ) );
	return $ids;
}

/* ==========================================================================
   ۲) اعمال عنوان روی تصویر (خروجی: true اگر چیزی تغییر کرد)
   ========================================================================== */
function gv_imgsync_apply_title_to_attachment( $attachment_id, $title, $s = null ) {
	if ( null === $s ) { $s = gv_imgsync_get_settings(); }
	$changed = false;

	if ( ! empty( $s['sync_title'] ) ) {
		$current = get_post_field( 'post_title', $attachment_id );
		if ( $current !== $title ) {
			wp_update_post( wp_slash( array(
				'ID'         => $attachment_id,
				'post_title' => $title,
			) ) );
			$changed = true;
		}
	}

	if ( ! empty( $s['sync_alt'] ) ) {
		$alt_value   = wp_strip_all_tags( $title, true );
		$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( $current_alt !== $alt_value ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_value );
			$changed = true;
		}
	}
	return $changed;
}

/* ==========================================================================
   ۳) حالت خودکار: همگام‌سازی هنگام ذخیره‌ی پست/صفحه
   ========================================================================== */
add_action( 'save_post', 'gv_imgsync_sync_post_images', 25, 2 );
function gv_imgsync_sync_post_images( $post_id, $post ) {
	$s = gv_imgsync_get_settings();
	if ( 'auto' !== $s['mode'] ) { return; }
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) { return; }
	if ( ! in_array( $post->post_type, $s['post_types'], true ) ) { return; }
	if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) { return; }

	$title = trim( $post->post_title );
	if ( '' === $title ) { return; }

	$image_ids = gv_imgsync_collect_image_ids( $post_id, $post->post_content, ! empty( $s['sync_featured'] ) );
	foreach ( $image_ids as $image_id ) {
		gv_imgsync_apply_title_to_attachment( $image_id, $title, $s );
	}
}

/* ==========================================================================
   ۴) اسکن سایت: نقشه‌ی «کدام تصویر در کدام محتوا استفاده شده»
   ------------------------------------------------------------------------
   اگر یک تصویر در چند محتوا استفاده شده باشد، اولین محتوا مبنا قرار
   می‌گیرد و تعداد استفاده‌ها هم ذخیره می‌شود.
   ========================================================================== */
function gv_imgsync_run_scan() {
	$s   = gv_imgsync_get_settings();
	$map = array();

	$query = new WP_Query( array(
		'post_type'      => $s['post_types'],
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	foreach ( $query->posts as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || '' === trim( $post->post_title ) ) { continue; }

		$image_ids = gv_imgsync_collect_image_ids( $post_id, $post->post_content, ! empty( $s['sync_featured'] ) );
		foreach ( $image_ids as $image_id ) {
			if ( isset( $map[ $image_id ] ) ) {
				$map[ $image_id ]['uses']++;
				continue;
			}
			$map[ $image_id ] = array(
				'image_id' => $image_id,
				'post_id'  => $post_id,
				'uses'     => 1,
				'date'     => get_post_field( 'post_date', $image_id ),
			);
		}
	}

	$items = array_values( $map );
	usort( $items, function ( $a, $b ) {
		return strcmp( $b['date'], $a['date'] ); // جدیدترین آپلود اول
	} );

	set_transient( GV_IMGSYNC_TRANSIENT, array(
		'items'      => $items,
		'scanned_at' => current_time( 'mysql' ),
	), WEEK_IN_SECONDS );

	return $items;
}

add_action( 'admin_post_gv_imgsync_run_scan', 'gv_imgsync_handle_run_scan' );
function gv_imgsync_handle_run_scan() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_IMGSYNC_NONCE );
	gv_imgsync_run_scan();
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_IMGSYNC_PAGE_SLUG . '&scanned=1' ) );
	exit;
}

/**
 * ساخت ردیف‌های قابل نمایش با وضعیت «زنده» (عنوان/آلت فعلی همیشه از دیتابیس خوانده می‌شود)
 */
function gv_imgsync_build_rows( $items, $s ) {
	$rows = array();
	foreach ( $items as $it ) {
		$img  = get_post( $it['image_id'] );
		$post = get_post( $it['post_id'] );
		if ( ! $img || ! $post ) { continue; }

		$target = trim( $post->post_title );
		if ( '' === $target ) { continue; }

		$cur_title = $img->post_title;
		$cur_alt   = (string) get_post_meta( $img->ID, '_wp_attachment_image_alt', true );

		$title_mm = ! empty( $s['sync_title'] ) && ( $cur_title !== $target );
		$alt_mm   = ! empty( $s['sync_alt'] ) && ( $cur_alt !== wp_strip_all_tags( $target, true ) );

		$file = get_attached_file( $img->ID );
		$author = get_userdata( $post->post_author );

		$rows[] = array(
			'image_id'   => $img->ID,
			'day'        => substr( $img->post_date, 0, 10 ),
			'datetime'   => $img->post_date,
			'file'       => $file ? basename( $file ) : '',
			'img_edit'   => get_edit_post_link( $img->ID, '' ),
			'cur_title'  => $cur_title,
			'cur_alt'    => $cur_alt,
			'post_id'    => $post->ID,
			'post_title' => $target,
			'post_edit'  => get_edit_post_link( $post->ID, '' ),
			'post_type'  => $post->post_type,
			'author'     => $author ? $author->display_name : '—',
			'uses'       => intval( $it['uses'] ),
			'title_mm'   => $title_mm,
			'alt_mm'     => $alt_mm,
			'pending'    => ( $title_mm || $alt_mm ),
		);
	}
	return $rows;
}

/* ==========================================================================
   ۵) همگام‌سازی دستی (انتخاب‌شده‌ها / یک روز کامل / تک‌ردیف)
   ========================================================================== */
add_action( 'admin_post_gv_imgsync_sync_selected', 'gv_imgsync_handle_sync_selected' );
function gv_imgsync_handle_sync_selected() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_IMGSYNC_NONCE );

	$s      = gv_imgsync_get_settings();
	$cached = get_transient( GV_IMGSYNC_TRANSIENT );
	$items  = ( is_array( $cached ) && ! empty( $cached['items'] ) ) ? $cached['items'] : array();

	$post_of = array();
	$day_of  = array();
	foreach ( $items as $it ) {
		$post_of[ intval( $it['image_id'] ) ] = intval( $it['post_id'] );
		$day_of[ intval( $it['image_id'] ) ]  = substr( $it['date'], 0, 10 );
	}

	$ids = array();
	if ( ! empty( $_POST['sync_day'] ) ) {
		$day = sanitize_text_field( wp_unslash( $_POST['sync_day'] ) );
		foreach ( $day_of as $iid => $d ) {
			if ( $d === $day ) { $ids[] = $iid; }
		}
	} elseif ( ! empty( $_POST['sync_one'] ) ) {
		$ids = array( intval( $_POST['sync_one'] ) );
	} else {
		$ids = isset( $_POST['image_ids'] ) ? array_map( 'intval', (array) $_POST['image_ids'] ) : array();
	}

	$changed = 0;
	foreach ( array_unique( $ids ) as $iid ) {
		if ( empty( $post_of[ $iid ] ) ) { continue; }
		$title = trim( (string) get_post_field( 'post_title', $post_of[ $iid ] ) );
		if ( '' === $title ) { continue; }
		if ( gv_imgsync_apply_title_to_attachment( $iid, $title, $s ) ) { $changed++; }
	}

	$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . GV_IMGSYNC_PAGE_SLUG );
	$back = remove_query_arg( array( 'synced', 'scanned', 'updated' ), $back );
	wp_safe_redirect( add_query_arg( 'synced', $changed, $back ) );
	exit;
}

/* ==========================================================================
   ۶) ذخیره تنظیمات
   ========================================================================== */
add_action( 'admin_post_gv_imgsync_save_settings', 'gv_imgsync_save_settings' );
function gv_imgsync_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
	check_admin_referer( GV_IMGSYNC_NONCE );

	$post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
		? array_map( 'sanitize_key', $_POST['post_types'] )
		: array( 'post', 'page' );

	$mode = ( isset( $_POST['mode'] ) && 'auto' === $_POST['mode'] ) ? 'auto' : 'manual';

	update_option( GV_IMGSYNC_OPT, array(
		'mode'          => $mode,
		'sync_title'    => isset( $_POST['sync_title'] ) ? 1 : 0,
		'sync_alt'      => isset( $_POST['sync_alt'] ) ? 1 : 0,
		'sync_featured' => isset( $_POST['sync_featured'] ) ? 1 : 0,
		'post_types'    => $post_types,
	) );
	wp_safe_redirect( admin_url( 'admin.php?page=' . GV_IMGSYNC_PAGE_SLUG . '&updated=1' ) );
	exit;
}

/* ==========================================================================
   ۷) منوی مدیریت
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
   ۸) توابع کمکی نمایش
   ========================================================================== */
function gv_imgsync_shorten( $text, $max_len = 28 ) {
	$text = trim( (string) $text );
	if ( '' === $text ) { return ''; }
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max_len ) {
		return mb_substr( $text, 0, $max_len ) . '…';
	}
	return $text;
}

function gv_imgsync_day_label( $day ) {
	$today     = current_time( 'Y-m-d' );
	$yesterday = gmdate( 'Y-m-d', strtotime( $today ) - DAY_IN_SECONDS );
	$label     = date_i18n( 'l j F Y', strtotime( $day ) );
	if ( $day === $today ) { return 'امروز — ' . $label; }
	if ( $day === $yesterday ) { return 'دیروز — ' . $label; }
	return $label;
}

function gv_imgsync_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=' . GV_IMGSYNC_PAGE_SLUG ) );
}

/* ==========================================================================
   ۹) رندر صفحه مدیریت
   ========================================================================== */
function gv_imgsync_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = gv_imgsync_get_settings();

	$cached     = get_transient( GV_IMGSYNC_TRANSIENT );
	$items      = ( is_array( $cached ) && ! empty( $cached['items'] ) ) ? $cached['items'] : array();
	$scanned_at = is_array( $cached ) ? ( $cached['scanned_at'] ?? '' ) : '';

	// --- پارامترهای فیلتر و صفحه‌بندی
	$f_status = ( isset( $_GET['fstatus'] ) && 'all' === $_GET['fstatus'] ) ? 'all' : 'pending';
	$f_day    = isset( $_GET['fday'] ) ? sanitize_text_field( wp_unslash( $_GET['fday'] ) ) : '';
	if ( $f_day && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f_day ) ) { $f_day = ''; }
	$per_page = isset( $_GET['per'] ) ? intval( $_GET['per'] ) : 30;
	if ( ! in_array( $per_page, array( 15, 30, 50, 100 ), true ) ) { $per_page = 30; }
	$paged    = max( 1, intval( $_GET['paged'] ?? 1 ) );

	$all_rows = gv_imgsync_build_rows( $items, $s );

	// آمار هر روز (قبل از فیلتر روز)
	$days = array();
	foreach ( $all_rows as $r ) {
		if ( ! isset( $days[ $r['day'] ] ) ) { $days[ $r['day'] ] = array( 'total' => 0, 'pending' => 0 ); }
		$days[ $r['day'] ]['total']++;
		if ( $r['pending'] ) { $days[ $r['day'] ]['pending']++; }
	}
	$total_pending = 0;
	foreach ( $all_rows as $r ) { if ( $r['pending'] ) { $total_pending++; } }

	// اعمال فیلترها
	$rows = array_values( array_filter( $all_rows, function ( $r ) use ( $f_status, $f_day ) {
		if ( 'pending' === $f_status && ! $r['pending'] ) { return false; }
		if ( $f_day && $r['day'] !== $f_day ) { return false; }
		return true;
	} ) );

	$total_rows = count( $rows );
	$total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );
	$paged = min( $paged, $total_pages );
	$page_rows = array_slice( $rows, ( $paged - 1 ) * $per_page, $per_page );

	// گروه‌بندی روزانه‌ی همین صفحه
	$groups = array();
	foreach ( $page_rows as $r ) { $groups[ $r['day'] ][] = $r; }

	$base_args = array( 'fstatus' => $f_status, 'fday' => $f_day, 'per' => $per_page );
	$post_type_objects = get_post_types( array( 'public' => true ), 'objects' );
	?>
	<div class="wrap" dir="rtl" style="font-family:'Vazirmatn',Tahoma,sans-serif;max-width:1150px;">
		<style>
			.gvis-header{background:linear-gradient(120deg,#3730a3,#4338ca);color:#fff;padding:22px 26px;border-radius:14px;margin:20px 0;}
			.gvis-header h1{margin:0;font-size:20px;color:#fff;}
			.gvis-header p{margin:8px 0 0;font-size:13px;color:#e0e7ff;line-height:1.9;}
			.gvis-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px;}
			@media(max-width:900px){.gvis-stats{grid-template-columns:1fr;}}
			.gvis-stat{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px 20px;}
			.gvis-stat b{display:block;font-size:22px;color:#3730a3;}
			.gvis-stat span{font-size:12.5px;color:#64748b;}
			.gvis-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin-bottom:18px;}
			.gvis-card h2{margin-top:0;font-size:15px;}
			.gvis-field{margin-bottom:14px;}
			.gvis-field>label{font-weight:700;font-size:13px;display:block;margin-bottom:6px;}
			.gvis-checks{display:flex;flex-wrap:wrap;gap:16px;margin:8px 0;}
			.gvis-checks label{font-weight:400;font-size:13px;display:flex;align-items:center;gap:6px;}
			.gvis-mode{display:flex;gap:12px;flex-wrap:wrap;}
			.gvis-mode label{border:1px solid #d1d5db;border-radius:12px;padding:10px 16px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px;}
			.gvis-mode label:has(input:checked){border-color:#4338ca;background:#eef2ff;}
			.gvis-btn{background:#111827;color:#fff !important;border:none;padding:10px 22px;border-radius:10px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;font-family:inherit;}
			.gvis-btn:disabled{opacity:.4;cursor:not-allowed;}
			.gvis-btn-scan{background:#4338ca;}
			.gvis-btn-ok{background:#047857;}
			.gvis-btn-small{padding:6px 14px;font-size:12px;border-radius:8px;}
			.gvis-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px;}
			.gvis-toolbar .grp{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
			.gvis-toolbar select{border-radius:8px;border:1px solid #d1d5db;padding:5px 8px;font-family:inherit;font-size:12.5px;min-width:150px;}
			.gvis-tabs a{padding:7px 14px;border-radius:20px;font-size:12.5px;text-decoration:none;color:#334155;background:#f1f5f9;}
			.gvis-tabs a.on{background:#4338ca;color:#fff;font-weight:700;}
			.gvis-selbar{position:sticky;top:32px;z-index:5;background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:10px 16px;margin-bottom:12px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;}
			.gvis-selbar label{font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;}
			.gvis-day{margin-bottom:16px;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;}
			.gvis-day-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:11px 14px;background:linear-gradient(180deg,#f8fafc,#eef1f7);border-bottom:1px solid #e2e8f0;}
			.gvis-day-head label{display:flex;align-items:center;gap:8px;font-weight:800;font-size:13px;color:#1e293b;}
			.gvis-day-head .cnt{font-weight:400;color:#64748b;font-size:12px;}
			.gvis-scroll{width:100%;overflow-x:auto;}
			table.gvis-table{width:100%;min-width:900px;border-collapse:collapse;font-size:12.5px;table-layout:fixed;}
			table.gvis-table th{text-align:right;color:#475569;font-size:11.5px;font-weight:800;padding:9px 12px;border-bottom:1px solid #e2e8f0;background:#fff;}
			table.gvis-table td{padding:10px 12px;border-bottom:1px solid #eef1f4;vertical-align:middle;overflow:hidden;}
			table.gvis-table tbody tr:last-child td{border-bottom:0;}
			table.gvis-table tbody tr:hover{background:#f5f7ff;}
			table.gvis-table tr.is-ok{opacity:.72;}
			.gvis-ellipsis{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;}
			a.gvis-ellipsis{color:#3730a3;text-decoration:none;font-weight:700;}
			.gvis-thumb{width:42px;height:42px;object-fit:cover;border-radius:9px;border:1px solid #e2e8f0;display:block;}
			.gvis-imgcell{display:flex;gap:10px;align-items:center;}
			.gvis-tag{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:700;padding:4px 10px;border-radius:20px;margin:0 0 3px 4px;}
			.gvis-tag-title{background:#fee2e2;color:#991b1b;}
			.gvis-tag-alt{background:#fef3c7;color:#92400e;}
			.gvis-tag-ok{background:#d1fae5;color:#065f46;}
			.gvis-muted{color:#94a3b8;font-size:11.5px;}
			.gvis-pager{display:flex;justify-content:center;gap:4px;flex-wrap:wrap;margin-top:14px;}
			.gvis-pager .page-numbers{padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#334155;font-size:12.5px;background:#fff;}
			.gvis-pager .page-numbers.current{background:#4338ca;color:#fff;border-color:#4338ca;}
		</style>

		<div class="gvis-header">
			<h1>🏷️ همگام‌ساز عنوان و آلت تصاویر</h1>
			<p>
				عنوان و متن جایگزین (Alt) تصاویرِ استفاده‌شده در هر محتوا، با عنوان همان محتوا یکی می‌شود.
				در حالت <b>دستی</b> شما لیست تصاویر را می‌بینید، هر تعداد را تیک می‌زنید (یا کل یک روز را انتخاب می‌کنید) و همگام می‌کنید؛
				در حالت <b>خودکار</b> این کار هنگام ذخیره‌ی هر محتوا خودش انجام می‌شود.
			</p>
		</div>

		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['scanned'] ) ) : ?><div class="notice notice-success is-dismissible"><p>اسکن سایت انجام شد.</p></div><?php endif; ?>
		<?php if ( isset( $_GET['synced'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( number_format_i18n( intval( $_GET['synced'] ) ) ); ?> تصویر همگام‌سازی شد.</p></div><?php endif; ?>

		<div class="gvis-stats">
			<div class="gvis-stat"><b><?php echo esc_html( number_format_i18n( count( $all_rows ) ) ); ?></b><span>تصویر استفاده‌شده در محتوا (طبق آخرین اسکن)</span></div>
			<div class="gvis-stat"><b style="color:#b45309;"><?php echo esc_html( number_format_i18n( $total_pending ) ); ?></b><span>نیازمند همگام‌سازی</span></div>
			<div class="gvis-stat"><b><?php echo esc_html( number_format_i18n( count( $days ) ) ); ?></b><span>روز آپلود متفاوت</span></div>
		</div>

		<div class="gvis-card">
			<h2>⚙️ تنظیمات</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gv_imgsync_save_settings">
				<?php wp_nonce_field( GV_IMGSYNC_NONCE ); ?>
				<div class="gvis-field">
					<label>حالت اجرا</label>
					<div class="gvis-mode">
						<label><input type="radio" name="mode" value="manual" <?php checked( $s['mode'], 'manual' ); ?>> ✋ دستی (خودتان انتخاب و همگام می‌کنید)</label>
						<label><input type="radio" name="mode" value="auto" <?php checked( $s['mode'], 'auto' ); ?>> ⚡ خودکار (هنگام ذخیره‌ی پست/صفحه)</label>
					</div>
				</div>
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
							if ( 'attachment' === $pt_slug ) { continue; } ?>
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
					آخرین اسکن: <?php echo esc_html( $scanned_at ); ?>. پس از افزودن محتوای جدید یا تغییر نوع محتوا، اسکن را دوباره اجرا کنید.
				<?php else : ?>
					هنوز اسکنی انجام نشده است. برای ساخت لیست تصاویر، ابتدا اسکن کنید.
				<?php endif; ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('این کار ممکن است روی سایت‌های بزرگ کمی طول بکشد. ادامه می‌دهید؟');">
				<input type="hidden" name="action" value="gv_imgsync_run_scan">
				<?php wp_nonce_field( GV_IMGSYNC_NONCE ); ?>
				<button type="submit" class="gvis-btn gvis-btn-scan">🔍 <?php echo $scanned_at ? 'اسکن مجدد سایت' : 'اسکن سایت'; ?></button>
			</form>
		</div>

		<div class="gvis-card">
			<h2>📋 لیست تصاویر (دسته‌بندی روزانه بر اساس تاریخ آپلود)</h2>

			<!-- نوار فیلتر: فرم GET جدا -->
			<div class="gvis-toolbar">
				<div class="grp gvis-tabs">
					<a href="<?php echo esc_url( gv_imgsync_url( array( 'fstatus' => 'pending', 'fday' => $f_day, 'per' => $per_page ) ) ); ?>" class="<?php echo 'pending' === $f_status ? 'on' : ''; ?>">فقط نیازمند همگام‌سازی</a>
					<a href="<?php echo esc_url( gv_imgsync_url( array( 'fstatus' => 'all', 'fday' => $f_day, 'per' => $per_page ) ) ); ?>" class="<?php echo 'all' === $f_status ? 'on' : ''; ?>">همه‌ی تصاویر</a>
				</div>
				<form method="get" class="grp">
					<input type="hidden" name="page" value="<?php echo esc_attr( GV_IMGSYNC_PAGE_SLUG ); ?>">
					<input type="hidden" name="fstatus" value="<?php echo esc_attr( $f_status ); ?>">
					<select name="fday" onchange="this.form.submit()">
						<option value="">همه‌ی روزها</option>
						<?php $n = 0; foreach ( $days as $d => $c ) : if ( ++$n > 90 ) { break; } ?>
							<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $f_day, $d ); ?>><?php echo esc_html( gv_imgsync_day_label( $d ) . ' (' . number_format_i18n( $c['pending'] ) . '/' . number_format_i18n( $c['total'] ) . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="per" onchange="this.form.submit()">
						<?php foreach ( array( 15, 30, 50, 100 ) as $pp ) : ?>
							<option value="<?php echo esc_attr( $pp ); ?>" <?php selected( $per_page, $pp ); ?>><?php echo esc_html( $pp ); ?> مورد در صفحه</option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>

			<?php if ( ! $scanned_at ) : ?>
				<p class="gvis-muted">ابتدا اسکن سایت را اجرا کنید.</p>
			<?php elseif ( empty( $page_rows ) ) : ?>
				<p class="gvis-muted">موردی برای نمایش نیست — یا همه‌چیز مرتب است، یا فیلتر فعلی نتیجه‌ای ندارد.</p>
			<?php else : ?>
				<form method="post" id="gvis-list-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gv_imgsync_sync_selected">
					<?php wp_nonce_field( GV_IMGSYNC_NONCE ); ?>

					<div class="gvis-selbar">
						<label><input type="checkbox" id="gvis-all-cb"> انتخاب همه‌ی موارد این صفحه</label>
						<span id="gvis-counter" class="gvis-muted">۰ مورد انتخاب شده</span>
						<button type="submit" id="gvis-sync-btn" class="gvis-btn gvis-btn-ok" disabled onclick="return confirm('عنوان/آلت موارد انتخاب‌شده با عنوان محتوا یکی شود؟');">✅ همگام‌سازی انتخاب‌شده‌ها</button>
					</div>

					<?php foreach ( $groups as $day => $g_rows ) :
						$day_pending_total = $days[ $day ]['pending'] ?? 0; ?>
						<div class="gvis-day">
							<div class="gvis-day-head">
								<label>
									<input type="checkbox" class="gvis-day-cb" data-day="<?php echo esc_attr( $day ); ?>">
									📅 <?php echo esc_html( gv_imgsync_day_label( $day ) ); ?>
									<span class="cnt">(<?php echo esc_html( number_format_i18n( $days[ $day ]['total'] ?? count( $g_rows ) ) ); ?> تصویر، <?php echo esc_html( number_format_i18n( $day_pending_total ) ); ?> نیازمند همگام‌سازی)</span>
								</label>
								<?php if ( $day_pending_total > 0 ) : ?>
									<button type="submit" name="sync_day" value="<?php echo esc_attr( $day ); ?>" class="gvis-btn gvis-btn-small gvis-btn-scan" onclick="return confirm('همه‌ی تصاویر این روز (حتی موارد صفحه‌های دیگر) همگام شوند؟');">⚡ همگام‌سازی کل این روز</button>
								<?php endif; ?>
							</div>
							<div class="gvis-scroll">
							<table class="gvis-table">
								<colgroup>
									<col style="width:5%"><col style="width:23%"><col style="width:18%"><col style="width:15%"><col style="width:15%"><col style="width:11%"><col style="width:13%">
								</colgroup>
								<thead>
									<tr>
										<th></th><th>تصویر</th><th>محتوای مبنا</th><th>عنوان فعلی</th><th>آلت فعلی</th><th>وضعیت</th><th>عملیات</th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $g_rows as $r ) : ?>
									<tr class="<?php echo $r['pending'] ? '' : 'is-ok'; ?>">
										<td><input type="checkbox" class="gvis-cb" name="image_ids[]" value="<?php echo esc_attr( $r['image_id'] ); ?>" data-day="<?php echo esc_attr( $day ); ?>"></td>
										<td>
											<div class="gvis-imgcell">
												<?php echo wp_get_attachment_image( $r['image_id'], array( 44, 44 ), false, array( 'class' => 'gvis-thumb' ) ); ?>
												<div style="min-width:0;">
													<a href="<?php echo esc_url( $r['img_edit'] ); ?>" target="_blank" class="gvis-ellipsis" title="<?php echo esc_attr( $r['file'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $r['file'], 24 ) ); ?></a><br>
													<span class="gvis-muted"><?php echo esc_html( substr( $r['datetime'], 11, 5 ) ); ?></span>
												</div>
											</div>
										</td>
										<td>
											<a href="<?php echo esc_url( $r['post_edit'] ); ?>" target="_blank" class="gvis-ellipsis" title="<?php echo esc_attr( $r['post_title'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $r['post_title'], 26 ) ); ?></a><br>
											<span class="gvis-muted"><?php echo esc_html( $r['post_type'] ); ?> · <?php echo esc_html( gv_imgsync_shorten( $r['author'], 12 ) ); ?><?php if ( $r['uses'] > 1 ) { echo ' · ' . esc_html( number_format_i18n( $r['uses'] ) ) . ' استفاده'; } ?></span>
										</td>
										<td><?php if ( '' !== $r['cur_title'] ) : ?><span class="gvis-ellipsis" title="<?php echo esc_attr( $r['cur_title'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $r['cur_title'], 24 ) ); ?></span><?php else : ?><span class="gvis-muted">—</span><?php endif; ?></td>
										<td><?php if ( '' !== $r['cur_alt'] ) : ?><span class="gvis-ellipsis" title="<?php echo esc_attr( $r['cur_alt'] ); ?>"><?php echo esc_html( gv_imgsync_shorten( $r['cur_alt'], 24 ) ); ?></span><?php else : ?><span class="gvis-muted">—</span><?php endif; ?></td>
										<td>
											<?php if ( ! $r['pending'] ) : ?><span class="gvis-tag gvis-tag-ok">همگام ✓</span><?php endif; ?>
											<?php if ( $r['title_mm'] ) : ?><span class="gvis-tag gvis-tag-title">عنوان</span><?php endif; ?>
											<?php if ( $r['alt_mm'] ) : ?><span class="gvis-tag gvis-tag-alt">آلت</span><?php endif; ?>
										</td>
										<td>
											<?php if ( $r['pending'] ) : ?>
												<button type="submit" name="sync_one" value="<?php echo esc_attr( $r['image_id'] ); ?>" class="gvis-btn gvis-btn-small">همگام‌سازی</button>
											<?php else : ?>
												<span class="gvis-muted">—</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
							</div>
						</div>
					<?php endforeach; ?>
				</form>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="gvis-pager">
						<?php echo wp_kses_post( paginate_links( array(
							'base'      => esc_url_raw( add_query_arg( array_merge( $base_args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php?page=' . GV_IMGSYNC_PAGE_SLUG ) ) ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '‹ قبلی',
							'next_text' => 'بعدی ›',
							'end_size'  => 1,
							'mid_size'  => 2,
						) ) ); ?>
					</div>
				<?php endif; ?>
				<p class="gvis-muted" style="text-align:center;margin-top:10px;">
					نمایش <?php echo esc_html( number_format_i18n( count( $page_rows ) ) ); ?> از <?php echo esc_html( number_format_i18n( $total_rows ) ); ?> مورد — صفحه <?php echo esc_html( number_format_i18n( $paged ) ); ?> از <?php echo esc_html( number_format_i18n( $total_pages ) ); ?>
				</p>
			<?php endif; ?>
		</div>

		<script>
		(function(){
			var form = document.getElementById('gvis-list-form');
			if(!form){ return; }
			var cbs   = [].slice.call(form.querySelectorAll('.gvis-cb'));
			var dayCb = [].slice.call(form.querySelectorAll('.gvis-day-cb'));
			var all   = document.getElementById('gvis-all-cb');
			var btn   = document.getElementById('gvis-sync-btn');
			var cnt   = document.getElementById('gvis-counter');

			function refresh(){
				var n = cbs.filter(function(c){ return c.checked; }).length;
				cnt.textContent = n.toLocaleString('fa-IR') + ' مورد انتخاب شده';
				btn.disabled = (n === 0);
				all.checked = (n > 0 && n === cbs.length);
				dayCb.forEach(function(d){
					var rs = cbs.filter(function(c){ return c.dataset.day === d.dataset.day; });
					d.checked = rs.length > 0 && rs.every(function(c){ return c.checked; });
				});
			}
			all.addEventListener('change', function(){
				cbs.forEach(function(c){ c.checked = all.checked; });
				refresh();
			});
			dayCb.forEach(function(d){
				d.addEventListener('change', function(){
					cbs.forEach(function(c){ if(c.dataset.day === d.dataset.day){ c.checked = d.checked; } });
					refresh();
				});
			});
			cbs.forEach(function(c){ c.addEventListener('change', refresh); });
			refresh();
		})();
		</script>

		<p style="font-size:11.5px;color:#888;text-align:center;margin-top:24px;">ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong></p>
	</div>
	<?php
}