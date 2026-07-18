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
