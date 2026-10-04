<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ==========================================================
 *  Groot Vision — مدیریت محصولات (ووکامرس)
 *  ------------------------------------------------------------
 *  ۱) لیست محصولات با جستجو، فیلتر، مرتب‌سازی و انتخاب گروهی
 *  ۲) خروجی Excel (XLSX) یا CSV با انتخاب ستون‌های دلخواه
 *  ۳) به‌روزرسانی گروهی (قیمت، نام، موجودی، دسته و ...) از روی فایل
 *  ۴) حذف گروهی محصولات و متغیرها (variation) از روی فایل (یا از روی انتخاب در لیست)
 *     + اکسل ردیف‌های حذف‌نشده همراه با علت
 *  ۵) قبل از هر تغییر، بکاپ خودکار گرفته می‌شود؛ هر زمان خواستید
 *     می‌توانید برگردانید یا اگر همه‌چیز درست بود بکاپ را پاک کنید.
 *
 *  همه‌ی کارهای سنگین به‌صورت تکه‌تکه (AJAX + نوار پیشرفت) انجام
 *  می‌شود تا با هزاران محصول هم تایم‌اوت نداشته باشیم.
 * ==========================================================
 */

define( 'GV_PM_PAGE_SLUG',    'gv-product-manager' );
define( 'GV_PM_NONCE',        'gv_pm_nonce_action' );
define( 'GV_PM_DIR_NAME',     'gv-product-manager' );
define( 'GV_PM_BACKUPS_OPT',  'gv_pm_backups' );
define( 'GV_PM_STEP_SECONDS', 8 );     // حداکثر زمان هر درخواست AJAX
define( 'GV_PM_EXPORT_BATCH', 200 );   // تعداد محصول در هر تکه‌ی خروجی
define( 'GV_PM_IMPORT_BATCH', 60 );    // تعداد ردیف در هر تکه‌ی به‌روزرسانی
define( 'GV_PM_DELETE_BATCH', 15 );    // تعداد محصول در هر تکه‌ی حذف
define( 'GV_PM_MAX_ROWS',     50000 ); // سقف ردیف فایل ورودی

/* ==========================================================================
   ۱) منوی مدیریت
   ========================================================================== */
add_action( 'admin_menu', 'gv_pm_admin_menu' );
function gv_pm_admin_menu() {
	add_submenu_page(
		'groot-vision-hub',
		'مدیریت محصولات | Groot Vision',
		'🛍️ مدیریت محصولات',
		'manage_options',
		GV_PM_PAGE_SLUG,
		'gv_pm_render_page'
	);
}

/* ==========================================================================
   ۲) کمک‌تابع‌های عمومی (پوشه، فایل، جاب)
   ========================================================================== */
function gv_pm_wc_active() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
}

function gv_pm_dir() {
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . GV_PM_DIR_NAME;
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		@file_put_contents( $dir . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
	}
	return $dir;
}

function gv_pm_new_id() {
	return strtolower( wp_generate_password( 16, false, false ) );
}

function gv_pm_valid_id( $id ) {
	return is_string( $id ) && preg_match( '/^[a-z0-9]{10,32}$/', $id );
}

function gv_pm_path( $kind, $id ) {
	// kind: job | rows | out | state | ids | backup | fail
	$map = array(
		'job'    => 'job-%s.json',
		'rows'   => 'job-%s.rows',
		'out'    => 'job-%s.out',
		'state'  => 'job-%s.state.json',
		'ids'    => 'job-%s.ids.json',
		'backup' => 'backup-%s.ndjson',
		'fail'   => 'job-%s.fail',
	);
	return trailingslashit( gv_pm_dir() ) . sprintf( $map[ $kind ], $id );
}

function gv_pm_json_write( $path, $data ) {
	return false !== @file_put_contents( $path, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ), LOCK_EX );
}

function gv_pm_json_read( $path ) {
	if ( ! file_exists( $path ) ) { return null; }
	$raw = @file_get_contents( $path );
	if ( false === $raw || '' === $raw ) { return null; }
	$data = json_decode( $raw, true );
	return is_array( $data ) ? $data : null;
}

function gv_pm_append_line( $path, $data ) {
	$j = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
	if ( false === $j ) { return false; }
	return false !== @file_put_contents( $path, $j . "\n", FILE_APPEND | LOCK_EX );
}

/** فایل‌های موقت قدیمی‌تر از ۲۴ ساعت را پاک می‌کند (بکاپ‌ها دست‌نخورده می‌مانند). */
function gv_pm_cleanup_jobs() {
	$dir = gv_pm_dir();
	foreach ( (array) glob( trailingslashit( $dir ) . 'job-*' ) as $f ) {
		if ( is_file( $f ) && ( time() - filemtime( $f ) ) > DAY_IN_SECONDS ) {
			@unlink( $f );
		}
	}
}

/** ثبت گروهی ردیف‌های ناموفق: هر خط {r: شماره‌ی ردیف فایل, i: شناسه, m: علت} */
function gv_pm_fail_add_many( $job, $entries ) {
	if ( ! $entries ) { return true; }
	$buf = '';
	foreach ( $entries as $e ) {
		$j = wp_json_encode( $e, JSON_UNESCAPED_UNICODE );
		if ( false !== $j ) { $buf .= $j . "\n"; }
	}
	return false !== @file_put_contents( gv_pm_path( 'fail', $job ), $buf, FILE_APPEND | LOCK_EX );
}

function gv_pm_fail_read( $job ) {
	$out  = array();
	$path = gv_pm_path( 'fail', $job );
	if ( ! file_exists( $path ) ) { return $out; }
	$fh = fopen( $path, 'rb' );
	while ( ( $line = fgets( $fh ) ) !== false ) {
		$d = json_decode( $line, true );
		if ( is_array( $d ) ) { $out[] = $d; }
	}
	fclose( $fh );
	return $out;
}

/** بعد از حذف/بازگردانی یک متغیر، محصول والد را همگام می‌کند (قیمت‌ها، موجودی و کش) */
function gv_pm_sync_parent( $parent_id ) {
	$parent_id = (int) $parent_id;
	if ( $parent_id <= 0 ) { return; }
	try {
		wc_delete_product_transients( $parent_id );
		if ( class_exists( 'WC_Product_Variable' ) ) { WC_Product_Variable::sync( $parent_id ); }
	} catch ( Throwable $e ) {
		// اختیاری است
	}
}

function gv_pm_format_size( $bytes ) {
	$bytes = (int) $bytes;
	if ( $bytes <= 0 ) { return '—'; }
	$units = array( 'بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت' );
	$i = 0; $v = $bytes;
	while ( $v >= 1024 && $i < count( $units ) - 1 ) { $v /= 1024; $i++; }
	return number_format_i18n( $v, $i > 0 ? 1 : 0 ) . ' ' . $units[ $i ];
}

function gv_pm_check_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی کافی ندارید.' ), 403 );
	}
	if ( ! check_ajax_referer( GV_PM_NONCE, 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'نشست شما منقضی شده؛ صفحه را دوباره بارگذاری کنید.' ), 403 );
	}
	if ( ! gv_pm_wc_active() ) {
		wp_send_json_error( array( 'message' => 'افزونه‌ی ووکامرس فعال نیست.' ) );
	}
	@set_time_limit( 60 );
	@ignore_user_abort( true );
}

function gv_pm_param_job() {
	$job = isset( $_REQUEST['job'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['job'] ) ) : '';
	if ( ! gv_pm_valid_id( $job ) ) {
		wp_send_json_error( array( 'message' => 'شناسه‌ی عملیات نامعتبر است.' ) );
	}
	return $job;
}

/* ==========================================================================
   ۳) نرمال‌سازی ارقام و مقادیر
   ========================================================================== */
function gv_pm_digits( $s ) {
	$fa = array( '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩' );
	$en = array( '0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9' );
	return str_replace( $fa, $en, (string) $s );
}

/** عدد را از هر قالبی (فارسی، با ویرگول هزارگان، با «تومان» و ...) به رشته‌ی عددی استاندارد برمی‌گرداند؛ نامعتبر = null */
function gv_pm_num( $s ) {
	$s = gv_pm_digits( $s );
	$s = str_replace( array( '٬', '،', ',', ' ', "\xC2\xA0", '٫' ), array( '', '', '', '', '', '.' ), $s );
	$s = preg_replace( '/[^\d.\-eE]/u', '', $s );
	if ( '' === $s || ! is_numeric( $s ) ) { return null; }
	return (string) $s;
}

function gv_pm_clean_text( $s ) {
	return trim( (string) $s );
}

function gv_pm_is_clear_token( $s ) {
	$s = mb_strtolower( trim( (string) $s ) );
	return in_array( $s, array( '[خالی]', '[empty]', '[clear]', '[پاک]' ), true );
}

/* ==========================================================================
   ۴) تعریف ستون‌ها (برای خروجی و ورودی)
   ------------------------------------------------------------
   import   : آیا در به‌روزرسانی از روی فایل قابل تغییر است؟
   clear    : آیا با «[خالی]» می‌شود مقدارش را پاک کرد؟
   numeric  : در فایل XLSX به‌صورت عدد ذخیره شود
   alias    : نام‌های جایگزین سرستون برای تشخیص خودکار هنگام ورود فایل
   ========================================================================== */
function gv_pm_fields() {
	static $f = null;
	if ( null !== $f ) { return $f; }

	$f = array(
		'id' => array(
			'label' => 'شناسه', 'group' => 'base', 'import' => false, 'numeric' => true,
			'alias' => array( 'id', 'product id', 'product_id', 'شناسه', 'آیدی', 'ایدی', 'شناسه محصول', 'id محصول' ),
		),
		'sku' => array(
			'label' => 'کد محصول (SKU)', 'group' => 'base', 'import' => true, 'clear' => true,
			'alias' => array( 'sku', 'کد محصول', 'کد کالا', 'شناسه کالا', 'کد محصول (sku)', 'شناسه یکتا' ),
		),
		'name' => array(
			'label' => 'نام محصول', 'group' => 'base', 'import' => true,
			'alias' => array( 'name', 'title', 'product name', 'product_title', 'نام', 'نام محصول', 'عنوان', 'عنوان محصول', 'نام کالا' ),
		),
		'type' => array(
			'label' => 'نوع محصول', 'group' => 'base', 'import' => false,
			'alias' => array( 'type', 'product type', 'نوع', 'نوع محصول' ),
		),
		'parent_id' => array(
			'label' => 'شناسه والد (برای متغیرها)', 'group' => 'base', 'import' => false, 'numeric' => true,
			'alias' => array( 'parent_id', 'parent', 'شناسه والد', 'شناسه والد (برای متغیرها)' ),
		),
		'status' => array(
			'label' => 'وضعیت انتشار', 'group' => 'base', 'import' => true,
			'alias' => array( 'status', 'post_status', 'وضعیت', 'وضعیت انتشار' ),
		),
		'slug' => array(
			'label' => 'نامک (آدرس)', 'group' => 'base', 'import' => true,
			'alias' => array( 'slug', 'post_name', 'نامک', 'نامک (آدرس)', 'آدرس' ),
		),
		'featured' => array(
			'label' => 'محصول ویژه', 'group' => 'base', 'import' => true,
			'alias' => array( 'featured', 'is_featured', 'ویژه', 'محصول ویژه' ),
		),
		'regular_price' => array(
			'label' => 'قیمت اصلی', 'group' => 'price', 'import' => true, 'numeric' => true,
			'alias' => array( 'regular_price', 'regular price', 'price', 'قیمت', 'قیمت اصلی', 'قیمت عادی', 'قیمت پایه', 'قیمت جدید' ),
		),
		'sale_price' => array(
			'label' => 'قیمت حراج', 'group' => 'price', 'import' => true, 'clear' => true, 'numeric' => true,
			'alias' => array( 'sale_price', 'sale price', 'قیمت حراج', 'قیمت فروش ویژه', 'قیمت تخفیفی', 'قیمت با تخفیف' ),
		),
		'sale_from' => array(
			'label' => 'شروع حراج', 'group' => 'price', 'import' => true, 'clear' => true,
			'alias' => array( 'sale_from', 'date_on_sale_from', 'شروع حراج', 'تاریخ شروع حراج' ),
		),
		'sale_to' => array(
			'label' => 'پایان حراج', 'group' => 'price', 'import' => true, 'clear' => true,
			'alias' => array( 'sale_to', 'date_on_sale_to', 'پایان حراج', 'تاریخ پایان حراج' ),
		),
		'current_price' => array(
			'label' => 'قیمت فعلی (فقط خروجی)', 'group' => 'price', 'import' => false, 'numeric' => true,
			'alias' => array( 'current_price', 'قیمت فعلی', 'قیمت فعلی (فقط خروجی)' ),
		),
		'manage_stock' => array(
			'label' => 'مدیریت موجودی', 'group' => 'stock', 'import' => true,
			'alias' => array( 'manage_stock', 'مدیریت موجودی' ),
		),
		'stock_qty' => array(
			'label' => 'تعداد موجودی', 'group' => 'stock', 'import' => true, 'clear' => true, 'numeric' => true,
			'alias' => array( 'stock_qty', 'stock', 'stock_quantity', 'quantity', 'موجودی', 'تعداد', 'تعداد موجودی', 'تعداد موجود' ),
		),
		'stock_status' => array(
			'label' => 'وضعیت موجودی', 'group' => 'stock', 'import' => true,
			'alias' => array( 'stock_status', 'وضعیت موجودی', 'وضعیت انبار' ),
		),
		'short_description' => array(
			'label' => 'توضیح کوتاه', 'group' => 'content', 'import' => true, 'clear' => true,
			'alias' => array( 'short_description', 'short description', 'excerpt', 'توضیح کوتاه', 'توضیحات کوتاه', 'خلاصه' ),
		),
		'description' => array(
			'label' => 'توضیحات کامل', 'group' => 'content', 'import' => true, 'clear' => true,
			'alias' => array( 'description', 'content', 'توضیحات', 'توضیحات کامل', 'توضیح', 'توضیح کامل' ),
		),
		'categories' => array(
			'label' => 'دسته‌ها', 'group' => 'tax', 'import' => true,
			'alias' => array( 'categories', 'category', 'product_cat', 'دسته', 'دسته‌ها', 'دسته ها', 'دسته‌بندی', 'دسته بندی', 'دسته‌بندی‌ها' ),
		),
		'tags' => array(
			'label' => 'برچسب‌ها', 'group' => 'tax', 'import' => true,
			'alias' => array( 'tags', 'tag', 'product_tag', 'برچسب', 'برچسب‌ها', 'برچسب ها', 'تگ', 'تگ‌ها' ),
		),
		'attributes' => array(
			'label' => 'ویژگی‌ها (فقط خروجی)', 'group' => 'tax', 'import' => false,
			'alias' => array( 'attributes', 'ویژگی‌ها', 'ویژگی ها', 'ویژگی‌ها (فقط خروجی)' ),
		),
		'weight' => array(
			'label' => 'وزن', 'group' => 'extra', 'import' => true, 'clear' => true, 'numeric' => true,
			'alias' => array( 'weight', 'وزن' ),
		),
		'length' => array(
			'label' => 'طول', 'group' => 'extra', 'import' => true, 'clear' => true, 'numeric' => true,
			'alias' => array( 'length', 'طول' ),
		),
		'width' => array(
			'label' => 'عرض', 'group' => 'extra', 'import' => true, 'clear' => true, 'numeric' => true,
			'alias' => array( 'width', 'عرض' ),
		),
		'height' => array(
			'label' => 'ارتفاع', 'group' => 'extra', 'import' => true, 'clear' => true, 'numeric' => true,
			'alias' => array( 'height', 'ارتفاع' ),
		),
		'image' => array(
			'label' => 'تصویر شاخص (آدرس)', 'group' => 'extra', 'import' => false,
			'alias' => array( 'image', 'image_url', 'تصویر', 'تصویر شاخص', 'تصویر شاخص (آدرس)' ),
		),
		'permalink' => array(
			'label' => 'لینک محصول', 'group' => 'extra', 'import' => false,
			'alias' => array( 'permalink', 'url', 'لینک', 'لینک محصول' ),
		),
		'date' => array(
			'label' => 'تاریخ ایجاد', 'group' => 'extra', 'import' => false,
			'alias' => array( 'date', 'date_created', 'تاریخ', 'تاریخ ایجاد' ),
		),
	);
	return $f;
}

function gv_pm_groups() {
	return array(
		'base'    => 'اطلاعات پایه',
		'price'   => 'قیمت و حراج',
		'stock'   => 'موجودی',
		'content' => 'توضیحات',
		'tax'     => 'دسته، برچسب و ویژگی',
		'extra'   => 'ابعاد و سایر',
	);
}

/** نقشه‌ی مقادیر قابل‌خواندن (فارسی) برای فیلدهای «انتخابی» */
function gv_pm_enum_maps() {
	return array(
		'status' => array(
			'publish' => 'منتشر شده', 'draft' => 'پیش‌نویس', 'private' => 'خصوصی',
			'pending' => 'در انتظار بررسی', 'trash' => 'زباله‌دان',
		),
		'stock_status' => array(
			'instock' => 'موجود', 'outofstock' => 'ناموجود', 'onbackorder' => 'پیش‌سفارش',
		),
		'type' => array(
			'simple' => 'ساده', 'variable' => 'متغیر', 'grouped' => 'گروهی', 'external' => 'خارجی', 'variation' => 'متغیر (زیرمجموعه)',
		),
	);
}

function gv_pm_enum_out( $field, $val, $lang ) {
	$maps = gv_pm_enum_maps();
	if ( 'en' === $lang || ! isset( $maps[ $field ][ $val ] ) ) { return $val; }
	return $maps[ $field ][ $val ];
}

function gv_pm_bool_out( $val, $lang ) {
	if ( 'en' === $lang ) { return $val ? 'yes' : 'no'; }
	return $val ? 'بله' : 'خیر';
}

/** مقدار انتخابی (فارسی/انگلیسی) را به کلید استاندارد برمی‌گرداند؛ نامعتبر = null */
function gv_pm_enum_in( $field, $text ) {
	$text = mb_strtolower( trim( (string) $text ) );
	$text = str_replace( array( "\xE2\x80\x8C", '_', '-' ), array( '', ' ', ' ' ), $text ); // نیم‌فاصله و جداکننده‌ها
	$text = preg_replace( '/\s+/u', ' ', $text );
	$maps = gv_pm_enum_maps();
	if ( empty( $maps[ $field ] ) ) { return null; }
	foreach ( $maps[ $field ] as $key => $fa ) {
		$fa_norm = preg_replace( '/\s+/u', ' ', str_replace( "\xE2\x80\x8C", '', mb_strtolower( $fa ) ) );
		if ( $text === $key || $text === $fa_norm || $text === str_replace( ' ', '', $fa_norm ) ) { return $key; }
	}
	$extra = array(
		'stock_status' => array( 'in stock' => 'instock', 'out of stock' => 'outofstock', 'on backorder' => 'onbackorder', 'پیش سفارش' => 'onbackorder', 'نا موجود' => 'outofstock', 'موجودی' => 'instock' ),
		'status'       => array( 'published' => 'publish', 'منتشر' => 'publish', 'پیش نویس' => 'draft', 'انتشار' => 'publish' ),
	);
	if ( isset( $extra[ $field ][ $text ] ) ) { return $extra[ $field ][ $text ]; }
	return null;
}

function gv_pm_bool_in( $text ) {
	$t = mb_strtolower( trim( gv_pm_digits( $text ) ) );
	if ( in_array( $t, array( 'yes', 'y', 'true', '1', 'بله', 'آری', 'فعال', 'بلی' ), true ) ) { return true; }
	if ( in_array( $t, array( 'no', 'n', 'false', '0', 'خیر', 'نه', 'غیرفعال' ), true ) ) { return false; }
	return null;
}

/* ==========================================================================
   ۵) خواندن مقدار فیلد از محصول (برای خروجی و برای بکاپ)
   ========================================================================== */
/** مقدار «خام/استاندارد» که هم برای مقایسه و هم برای بکاپ و بازگردانی استفاده می‌شود */
function gv_pm_raw_get( $p, $key ) {
	switch ( $key ) {
		case 'sku':               return (string) $p->get_sku( 'edit' );
		case 'name':              return (string) $p->get_name( 'edit' );
		case 'status':            return (string) $p->get_status( 'edit' );
		case 'slug':              return (string) $p->get_slug( 'edit' );
		case 'featured':          return (bool) $p->get_featured( 'edit' );
		case 'regular_price':     return (string) $p->get_regular_price( 'edit' );
		case 'sale_price':        return (string) $p->get_sale_price( 'edit' );
		case 'sale_from':
			$d = $p->get_date_on_sale_from( 'edit' );
			return $d ? $d->date( 'Y-m-d' ) : '';
		case 'sale_to':
			$d = $p->get_date_on_sale_to( 'edit' );
			return $d ? $d->date( 'Y-m-d' ) : '';
		case 'manage_stock':
			$m = $p->get_manage_stock( 'edit' );
			return ( 'parent' === $m ) ? 'parent' : (bool) $m;
		case 'stock_qty':
			$q = $p->get_stock_quantity( 'edit' );
			return ( null === $q || '' === $q ) ? '' : (string) $q;
		case 'stock_status':      return (string) $p->get_stock_status( 'edit' );
		case 'short_description': return (string) $p->get_short_description( 'edit' );
		case 'description':       return (string) $p->get_description( 'edit' );
		case 'categories':
			$ids = array_map( 'intval', (array) $p->get_category_ids( 'edit' ) );
			sort( $ids );
			return $ids;
		case 'tags':
			$ids = array_map( 'intval', (array) $p->get_tag_ids( 'edit' ) );
			sort( $ids );
			return $ids;
		case 'weight':            return (string) $p->get_weight( 'edit' );
		case 'length':            return (string) $p->get_length( 'edit' );
		case 'width':             return (string) $p->get_width( 'edit' );
		case 'height':            return (string) $p->get_height( 'edit' );
	}
	return '';
}

/** مقدار خام را روی شیء محصول می‌نشاند (ذخیره با خود تابع فراخوان انجام می‌شود) */
function gv_pm_raw_set( $p, $key, $val ) {
	switch ( $key ) {
		case 'sku':               $p->set_sku( (string) $val ); break;
		case 'name':              $p->set_name( (string) $val ); break;
		case 'status':            $p->set_status( (string) $val ); break;
		case 'slug':              $p->set_slug( (string) $val ); break;
		case 'featured':          $p->set_featured( (bool) $val ); break;
		case 'regular_price':     $p->set_regular_price( (string) $val ); break;
		case 'sale_price':        $p->set_sale_price( (string) $val ); break;
		case 'sale_from':         $p->set_date_on_sale_from( '' === (string) $val ? null : (string) $val ); break;
		case 'sale_to':           $p->set_date_on_sale_to( '' === (string) $val ? null : (string) $val ); break;
		case 'manage_stock':      $p->set_manage_stock( 'parent' === $val ? 'parent' : (bool) $val ); break;
		case 'stock_qty':         $p->set_stock_quantity( '' === (string) $val ? null : (int) $val ); break;
		case 'stock_status':      $p->set_stock_status( (string) $val ); break;
		case 'short_description': $p->set_short_description( (string) $val ); break;
		case 'description':       $p->set_description( (string) $val ); break;
		case 'categories':        $p->set_category_ids( array_map( 'intval', (array) $val ) ); break;
		case 'tags':              $p->set_tag_ids( array_map( 'intval', (array) $val ) ); break;
		case 'weight':            $p->set_weight( (string) $val ); break;
		case 'length':            $p->set_length( (string) $val ); break;
		case 'width':             $p->set_width( (string) $val ); break;
		case 'height':            $p->set_height( (string) $val ); break;
	}
}

function gv_pm_term_names( $ids, $tax ) {
	$names = array();
	foreach ( (array) $ids as $id ) {
		$t = get_term( (int) $id, $tax );
		if ( $t && ! is_wp_error( $t ) ) { $names[] = $t->name; }
	}
	return implode( ' | ', $names );
}

/** مقدار نمایشی یک فیلد برای ستون خروجی */
function gv_pm_export_value( $p, $key, $lang ) {
	$is_var = $p->is_type( 'variation' );
	switch ( $key ) {
		case 'id':        return $p->get_id();
		case 'parent_id': return $is_var ? $p->get_parent_id() : '';
		case 'type':      return gv_pm_enum_out( 'type', $p->get_type(), $lang );
		case 'status':    return gv_pm_enum_out( 'status', $p->get_status( 'edit' ), $lang );
		case 'featured':  return $is_var ? '' : gv_pm_bool_out( $p->get_featured( 'edit' ), $lang );
		case 'current_price': return (string) $p->get_price( 'edit' );
		case 'manage_stock':
			$m = $p->get_manage_stock( 'edit' );
			return ( 'parent' === $m ) ? ( 'en' === $lang ? 'parent' : 'از والد' ) : gv_pm_bool_out( (bool) $m, $lang );
		case 'stock_status': return gv_pm_enum_out( 'stock_status', $p->get_stock_status( 'edit' ), $lang );
		case 'categories':
			return $is_var ? '' : gv_pm_term_names( $p->get_category_ids( 'edit' ), 'product_cat' );
		case 'tags':
			return $is_var ? '' : gv_pm_term_names( $p->get_tag_ids( 'edit' ), 'product_tag' );
		case 'attributes':
			if ( $is_var ) {
				$out = array();
				foreach ( (array) $p->get_attributes( 'edit' ) as $name => $val ) {
					$label = taxonomy_exists( $name ) ? wc_attribute_label( $name ) : urldecode( $name );
					if ( taxonomy_exists( $name ) && '' !== $val ) {
						$term = get_term_by( 'slug', $val, $name );
						$val  = $term ? $term->name : $val;
					} else {
						$val = urldecode( (string) $val );
					}
					$out[] = $label . ': ' . ( '' === $val ? '(هر مقدار)' : $val );
				}
				return implode( ' | ', $out );
			}
			$out = array();
			foreach ( (array) $p->get_attributes( 'edit' ) as $attr ) {
				if ( is_object( $attr ) && method_exists( $attr, 'get_name' ) ) {
					$opts = (array) $attr->get_options();
					if ( $attr->is_taxonomy() ) {
						$names = array();
						foreach ( $opts as $tid ) { $t = get_term( (int) $tid ); if ( $t && ! is_wp_error( $t ) ) { $names[] = $t->name; } }
						$opts = $names;
					}
					$out[] = wc_attribute_label( $attr->get_name() ) . ': ' . implode( ', ', $opts );
				}
			}
			return implode( ' | ', $out );
		case 'image':
			$img = $p->get_image_id( 'edit' );
			if ( ! $img && $is_var ) { return ''; }
			return $img ? (string) wp_get_attachment_url( $img ) : '';
		case 'permalink': return $is_var ? '' : (string) get_permalink( $p->get_id() );
		case 'date':
			$d = $p->get_date_created( 'edit' );
			return $d ? $d->date( 'Y-m-d H:i' ) : '';
		case 'stock_qty':
		case 'sale_from':
		case 'sale_to':
		case 'sku':
		case 'regular_price':
		case 'sale_price':
		case 'weight':
		case 'length':
		case 'width':
		case 'height':
		case 'name':
		case 'slug':
		case 'short_description':
		case 'description':
			return gv_pm_raw_get( $p, $key );
	}
	return '';
}

/* ==========================================================================
   ۶) کوئری لیست محصولات (فیلتر + مرتب‌سازی + جستجو)
   ========================================================================== */
function gv_pm_read_filters( $src ) {
	$g = function ( $k, $d = '' ) use ( $src ) {
		return isset( $src[ $k ] ) ? sanitize_text_field( wp_unslash( $src[ $k ] ) ) : $d;
	};
	$f = array(
		'search'  => $g( 'search' ),
		'status'  => $g( 'status', 'all' ),
		'cat'     => (int) $g( 'cat', 0 ),
		'type'    => $g( 'type', '' ),
		'stock'   => $g( 'stock', '' ),
		'orderby' => $g( 'orderby', 'date' ),
		'order'   => strtoupper( $g( 'order', 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC',
	);
	if ( ! in_array( $f['orderby'], array( 'date', 'title', 'price', 'sku', 'stock', 'id' ), true ) ) { $f['orderby'] = 'date'; }
	return $f;
}

function gv_pm_query_args( $f ) {
	$args = array(
		'post_type'      => 'product',
		'fields'         => 'ids',
		'posts_per_page' => 25,
		'paged'          => 1,
		'no_found_rows'  => false,
		'suppress_filters' => false,
	);

	// وضعیت
	$allowed = array( 'publish', 'draft', 'pending', 'private' );
	if ( 'trash' === $f['status'] ) {
		$args['post_status'] = 'trash';
	} elseif ( in_array( $f['status'], $allowed, true ) ) {
		$args['post_status'] = $f['status'];
	} else {
		$args['post_status'] = $allowed;
	}

	$tax = array();
	if ( $f['cat'] > 0 ) {
		$tax[] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => array( (int) $f['cat'] ), 'include_children' => true );
	}
	if ( in_array( $f['type'], array( 'simple', 'variable', 'grouped', 'external' ), true ) ) {
		$tax[] = array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => array( $f['type'] ) );
	}
	if ( $tax ) {
		$tax['relation'] = 'AND';
		$args['tax_query'] = $tax;
	}

	$meta = array( 'relation' => 'AND' );
	if ( in_array( $f['stock'], array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
		$meta[] = array( 'key' => '_stock_status', 'value' => $f['stock'] );
	}

	// مرتب‌سازی (ردیف‌های بدون متا حذف نشوند)
	$order = $f['order'];
	switch ( $f['orderby'] ) {
		case 'title':
			$args['orderby'] = array( 'title' => $order, 'ID' => 'DESC' );
			break;
		case 'id':
			$args['orderby'] = array( 'ID' => $order );
			break;
		case 'price':
		case 'stock':
		case 'sku':
			$key  = array( 'price' => '_price', 'stock' => '_stock', 'sku' => '_sku' );
			$type = ( 'sku' === $f['orderby'] ) ? 'CHAR' : 'NUMERIC';
			$meta[] = array(
				'relation' => 'OR',
				'gv_sort'  => array( 'key' => $key[ $f['orderby'] ], 'type' => $type, 'compare' => 'EXISTS' ),
				array( 'key' => $key[ $f['orderby'] ], 'compare' => 'NOT EXISTS' ),
			);
			$args['orderby'] = array( 'gv_sort' => $order, 'ID' => 'DESC' );
			break;
		default:
			$args['orderby'] = array( 'date' => $order, 'ID' => 'DESC' );
	}
	if ( count( $meta ) > 1 ) { $args['meta_query'] = $meta; }

	// جستجو (عنوان، SKU و ...)
	if ( '' !== $f['search'] ) {
		$ids = array();
		try {
			$ds  = WC_Data_Store::load( 'product' );
			$ids = $ds->search_products( wc_clean( $f['search'] ), '', false, true, 3000 );
		} catch ( Exception $e ) {
			$ids = array();
		}
		$args['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
	}
	return $args;
}

/** همه‌ی شناسه‌های منطبق با فیلتر (برای «انتخاب همه» و خروجی فیلترشده) */
function gv_pm_all_ids( $f ) {
	$args = gv_pm_query_args( $f );
	$args['posts_per_page'] = GV_PM_MAX_ROWS;
	$args['no_found_rows']  = true;
	$args['fields']         = 'ids';
	$q = new WP_Query( $args );
	return array_map( 'intval', $q->posts );
}

function gv_pm_money( $v ) {
	if ( '' === $v || null === $v ) { return ''; }
	return number_format_i18n( (float) $v, 0 );
}

function gv_pm_list_row( $p ) {
	$id    = $p->get_id();
	$thumb = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) : '';
	$type  = $p->get_type();

	$regular = $sale = '';
	$range   = '';
	if ( $p->is_type( 'variable' ) ) {
		$min = $p->get_variation_price( 'min' );
		$max = $p->get_variation_price( 'max' );
		if ( '' !== $min ) {
			$range = ( $min === $max ) ? gv_pm_money( $min ) : gv_pm_money( $min ) . ' – ' . gv_pm_money( $max );
		}
	} else {
		$regular = gv_pm_money( $p->get_regular_price( 'edit' ) );
		$sale    = gv_pm_money( $p->get_sale_price( 'edit' ) );
	}

	$qty = $p->get_manage_stock( 'edit' ) ? $p->get_stock_quantity( 'edit' ) : null;
	$cats = array();
	foreach ( (array) $p->get_category_ids() as $cid ) {
		$t = get_term( (int) $cid, 'product_cat' );
		if ( $t && ! is_wp_error( $t ) ) { $cats[] = $t->name; }
	}

	return array(
		'id'       => $id,
		'name'     => $p->get_name() !== '' ? $p->get_name() : '(بدون نام)',
		'sku'      => (string) $p->get_sku(),
		'thumb'    => $thumb ? $thumb : '',
		'type'     => $type,
		'type_fa'  => gv_pm_enum_out( 'type', $type, 'fa' ),
		'status'   => $p->get_status(),
		'status_fa'=> gv_pm_enum_out( 'status', $p->get_status(), 'fa' ),
		'regular'  => $regular,
		'sale'     => $sale,
		'range'    => $range,
		'stock'    => $p->get_stock_status(),
		'stock_fa' => gv_pm_enum_out( 'stock_status', $p->get_stock_status(), 'fa' ),
		'qty'      => ( null === $qty ) ? null : (int) $qty,
		'cats'     => implode( '، ', $cats ),
		'edit'     => get_edit_post_link( $id, 'raw' ),
		'editable'    => in_array( $type, array( 'simple', 'external' ), true ),
		'regular_raw' => in_array( $type, array( 'simple', 'external' ), true ) ? (string) $p->get_regular_price( 'edit' ) : '',
		'sale_raw'    => in_array( $type, array( 'simple', 'external' ), true ) ? (string) $p->get_sale_price( 'edit' ) : '',
	);
}

add_action( 'wp_ajax_gv_pm_list', 'gv_pm_ajax_list' );
function gv_pm_ajax_list() {
	gv_pm_check_ajax();
	$f    = gv_pm_read_filters( $_POST );
	$args = gv_pm_query_args( $f );
	$per  = isset( $_POST['per_page'] ) ? max( 10, min( 100, (int) $_POST['per_page'] ) ) : 25;
	$args['posts_per_page'] = $per;
	$args['paged'] = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;

	$q    = new WP_Query( $args );
	$rows = array();
	foreach ( $q->posts as $pid ) {
		$p = wc_get_product( $pid );
		if ( $p ) { $rows[] = gv_pm_list_row( $p ); }
	}
	wp_send_json_success( array(
		'rows'  => $rows,
		'total' => (int) $q->found_posts,
		'pages' => (int) $q->max_num_pages,
		'page'  => (int) $args['paged'],
	) );
}

add_action( 'wp_ajax_gv_pm_ids', 'gv_pm_ajax_ids' );
function gv_pm_ajax_ids() {
	gv_pm_check_ajax();
	$f   = gv_pm_read_filters( $_POST );
	$ids = gv_pm_all_ids( $f );
	wp_send_json_success( array( 'ids' => $ids, 'total' => count( $ids ) ) );
}

/** ویرایش سریع قیمت (اصلی / حراج) از داخل لیست، بدون ریلود */
add_action( 'wp_ajax_gv_pm_price_update', 'gv_pm_ajax_price_update' );
function gv_pm_ajax_price_update() {
	gv_pm_check_ajax();
	$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
	$val   = isset( $_POST['value'] ) ? trim( (string) wp_unslash( $_POST['value'] ) ) : '';
	if ( ! in_array( $field, array( 'regular_price', 'sale_price' ), true ) ) {
		wp_send_json_error( array( 'message' => 'فیلد نامعتبر است.' ) );
	}
	$p = $id > 0 ? wc_get_product( $id ) : null;
	if ( ! $p || ! in_array( $p->get_type(), array( 'simple', 'external' ), true ) ) {
		wp_send_json_error( array( 'message' => 'قیمت این نوع محصول از اینجا قابل ویرایش نیست (محصول متغیر را از صفحه‌ی خودش ویرایش کنید).' ) );
	}
	if ( '' === $val ) {
		if ( 'regular_price' === $field ) { wp_send_json_error( array( 'message' => 'قیمت اصلی نمی‌تواند خالی باشد.' ) ); }
		$val = '[خالی]';
	}
	$r = gv_pm_compute_changes( $p, array( $field => $val ), array() );
	if ( $r['errors'] ) { wp_send_json_error( array( 'message' => implode( ' — ', $r['errors'] ) ) ); }
	$old     = gv_pm_raw_get( $p, $field );
	$changed = false;
	if ( isset( $r['changes'][ $field ] ) ) {
		try {
			gv_pm_raw_set( $p, $field, $r['changes'][ $field ]['new'] );
			$p->save();
			$changed = true;
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => 'ذخیره نشد: ' . $e->getMessage() ) );
		}
		$p = wc_get_product( $id );
	}
	wp_send_json_success( array( 'row' => gv_pm_list_row( $p ), 'old' => $old, 'changed' => $changed ) );
}

/* ==========================================================================
   ۷) خروجی (CSV / XLSX) — تکه‌تکه
   ========================================================================== */
function gv_pm_expand_variations( $ids ) {
	global $wpdb;
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( ! $ids ) { return array(); }
	$kids = array();
	foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
		$in   = implode( ',', $chunk );
		$rows = $wpdb->get_results(
			"SELECT ID, post_parent FROM {$wpdb->posts}
			 WHERE post_type = 'product_variation' AND post_status IN ('publish','private') AND post_parent IN ($in)
			 ORDER BY post_parent ASC, menu_order ASC, ID ASC",
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) { $kids[ (int) $r['post_parent'] ][] = (int) $r['ID']; }
	}
	$out = array();
	foreach ( $ids as $pid ) {
		$out[] = $pid;
		if ( ! empty( $kids[ $pid ] ) ) { foreach ( $kids[ $pid ] as $k ) { $out[] = $k; } }
	}
	return $out;
}

add_action( 'wp_ajax_gv_pm_export_start', 'gv_pm_ajax_export_start' );
function gv_pm_ajax_export_start() {
	gv_pm_check_ajax();
	gv_pm_cleanup_jobs();

	$all    = gv_pm_fields();
	$wanted = isset( $_POST['fields'] ) ? array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( $_POST['fields'] ) ) ) ) : array();
	$cols   = array( 'id' );
	foreach ( $wanted as $k ) {
		if ( 'id' !== $k && isset( $all[ $k ] ) && ! in_array( $k, $cols, true ) ) { $cols[] = $k; }
	}
	// ترتیب ستون‌ها = ترتیب تعریف فیلدها
	$ordered = array();
	foreach ( array_keys( $all ) as $k ) { if ( in_array( $k, $cols, true ) ) { $ordered[] = $k; } }
	$cols = $ordered;
	if ( count( $cols ) < 2 ) {
		wp_send_json_error( array( 'message' => 'حداقل یک ستون را برای خروجی انتخاب کنید.' ) );
	}

	$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'all';
	if ( 'selected' === $scope ) {
		$raw = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '';
		$ids = array_filter( array_map( 'intval', explode( ',', $raw ) ) );
	} elseif ( 'filter' === $scope ) {
		$ids = gv_pm_all_ids( gv_pm_read_filters( $_POST ) );
	} else {
		$ids = gv_pm_all_ids( array( 'search' => '', 'status' => 'all', 'cat' => 0, 'type' => '', 'stock' => '', 'orderby' => 'id', 'order' => 'ASC' ) );
	}
	if ( ! $ids ) {
		wp_send_json_error( array( 'message' => 'هیچ محصولی برای خروجی گرفتن پیدا نشد.' ) );
	}
	if ( ! empty( $_POST['variations'] ) ) {
		$ids = gv_pm_expand_variations( $ids );
	}

	$format = ( isset( $_POST['format'] ) && 'csv' === $_POST['format'] ) ? 'csv' : 'xlsx';
	$notice = '';
	if ( 'xlsx' === $format && ! class_exists( 'ZipArchive' ) ) {
		$format = 'csv';
		$notice = 'سرور شما از ZipArchive پشتیبانی نمی‌کند؛ به‌جای Excel فایل CSV ساخته شد.';
	}
	$lang  = ( isset( $_POST['lang'] ) && 'en' === $_POST['lang'] ) ? 'en' : 'fa';
	$delim = ',';
	if ( isset( $_POST['delim'] ) ) {
		$d = wp_unslash( $_POST['delim'] );
		if ( ';' === $d ) { $delim = ';'; } elseif ( 'tab' === $d ) { $delim = "\t"; }
	}

	$job = gv_pm_new_id();
	$out = gv_pm_path( 'out', $job );
	$fh  = @fopen( $out, 'wb' );
	if ( ! $fh ) { wp_send_json_error( array( 'message' => 'امکان ساخت فایل موقت وجود ندارد (دسترسی نوشتن پوشه‌ی آپلود را بررسی کنید).' ) ); }
	fwrite( $fh, "\xEF\xBB\xBF" );
	$header = array();
	foreach ( $cols as $k ) { $header[] = ( 'en' === $lang ) ? $k : $all[ $k ]['label']; }
	fputcsv( $fh, $header, $delim, '"', '' );
	fclose( $fh );

	gv_pm_json_write( gv_pm_path( 'ids', $job ), array( 'ids' => array_values( $ids ) ) );
	gv_pm_json_write( gv_pm_path( 'job', $job ), array(
		'type' => 'export', 'cols' => $cols, 'lang' => $lang, 'delim' => $delim,
		'format' => $format, 'offset' => 0, 'total' => count( $ids ), 'created' => time(),
	) );

	wp_send_json_success( array( 'job' => $job, 'total' => count( $ids ), 'format' => $format, 'notice' => $notice ) );
}

add_action( 'wp_ajax_gv_pm_export_step', 'gv_pm_ajax_export_step' );
function gv_pm_ajax_export_step() {
	gv_pm_check_ajax();
	$id  = gv_pm_param_job();
	$job = gv_pm_json_read( gv_pm_path( 'job', $id ) );
	$idl = gv_pm_json_read( gv_pm_path( 'ids', $id ) );
	if ( ! $job || ! $idl || 'export' !== $job['type'] ) {
		wp_send_json_error( array( 'message' => 'این خروجی دیگر معتبر نیست؛ دوباره شروع کنید.' ) );
	}
	$ids   = $idl['ids'];
	$total = count( $ids );
	$off   = (int) $job['offset'];
	$start = microtime( true );
	$fh    = fopen( gv_pm_path( 'out', $id ), 'ab' );
	if ( ! $fh ) { wp_send_json_error( array( 'message' => 'نوشتن در فایل خروجی ممکن نیست.' ) ); }

	$n = 0;
	while ( $off < $total && $n < GV_PM_EXPORT_BATCH && ( microtime( true ) - $start ) < GV_PM_STEP_SECONDS ) {
		$p = wc_get_product( $ids[ $off ] );
		$off++; $n++;
		if ( ! $p ) { continue; }
		$row = array();
		foreach ( $job['cols'] as $k ) {
			$v = gv_pm_export_value( $p, $k, $job['lang'] );
			$row[] = is_scalar( $v ) ? (string) $v : '';
		}
		fputcsv( $fh, $row, $job['delim'], '"', '' );
	}
	fclose( $fh );

	$job['offset'] = $off;
	gv_pm_json_write( gv_pm_path( 'job', $id ), $job );
	wp_send_json_success( array( 'offset' => $off, 'total' => $total, 'done' => $off >= $total ) );
}

add_action( 'admin_post_gv_pm_download', 'gv_pm_handle_download' );
function gv_pm_handle_download() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی کافی ندارید.' ); }
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), GV_PM_NONCE ) ) { wp_die( 'لینک منقضی شده است.' ); }
	$id = isset( $_GET['job'] ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : '';
	if ( ! gv_pm_valid_id( $id ) ) { wp_die( 'شناسه نامعتبر است.' ); }
	$job = gv_pm_json_read( gv_pm_path( 'job', $id ) );
	$csv = gv_pm_path( 'out', $id );
	if ( ! $job || 'export' !== $job['type'] || ! file_exists( $csv ) ) { wp_die( 'فایل خروجی پیدا نشد؛ دوباره خروجی بگیرید.' ); }

	$stamp = wp_date( 'Ymd-His' );
	while ( ob_get_level() ) { ob_end_clean(); }

	if ( 'xlsx' === $job['format'] ) {
		$tmp = gv_pm_path( 'out', $id . 'x' );
		$ok  = gv_pm_csv_to_xlsx( $csv, $job['delim'], $job['cols'], $tmp );
		if ( ! $ok ) { wp_die( 'ساخت فایل Excel ناموفق بود؛ خروجی CSV را امتحان کنید.' ); }
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="products-' . $stamp . '.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="products-' . $stamp . '.csv"' );
	header( 'Content-Length: ' . filesize( $csv ) );
	readfile( $csv );
	exit;
}

/* ---------- ساخت فایل XLSX بدون هیچ کتابخانه‌ی خارجی ---------- */
function gv_pm_col_letter( $i ) {
	$s = '';
	$i++;
	while ( $i > 0 ) {
		$m = ( $i - 1 ) % 26;
		$s = chr( 65 + $m ) . $s;
		$i = intdiv( $i - 1, 26 );
	}
	return $s;
}

function gv_pm_xml_text( $s ) {
	$s = wp_check_invalid_utf8( (string) $s, true );
	$t = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s );
	if ( null === $t ) { $t = ''; }
	return htmlspecialchars( $t, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

function gv_pm_csv_to_xlsx( $csv_path, $delim, $cols, $dest ) {
	if ( ! class_exists( 'ZipArchive' ) ) { return false; }
	$fields  = gv_pm_fields();
	$widths  = array(
		'id' => 9, 'sku' => 16, 'name' => 42, 'type' => 12, 'parent_id' => 12, 'status' => 14, 'slug' => 26, 'featured' => 11,
		'regular_price' => 14, 'sale_price' => 14, 'sale_from' => 13, 'sale_to' => 13, 'current_price' => 14,
		'manage_stock' => 13, 'stock_qty' => 13, 'stock_status' => 14, 'short_description' => 40, 'description' => 60,
		'categories' => 28, 'tags' => 24, 'attributes' => 28, 'weight' => 9, 'length' => 9, 'width' => 9, 'height' => 9,
		'image' => 36, 'permalink' => 40, 'date' => 18,
	);

	$sheet = $dest . '.sheet';
	$out   = @fopen( $sheet, 'wb' );
	$in    = @fopen( $csv_path, 'rb' );
	if ( ! $out || ! $in ) { return false; }

	fwrite( $out, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' );
	fwrite( $out, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' );
	fwrite( $out, '<sheetViews><sheetView rightToLeft="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' );
	fwrite( $out, '<sheetFormatPr defaultRowHeight="18"/><cols>' );
	foreach ( $cols as $i => $k ) {
		$w = isset( $widths[ $k ] ) ? $widths[ $k ] : 16;
		fwrite( $out, '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . $w . '" customWidth="1"/>' );
	}
	fwrite( $out, '</cols><sheetData>' );

	// حذف BOM ابتدای فایل
	$bom = fread( $in, 3 );
	if ( "\xEF\xBB\xBF" !== $bom ) { rewind( $in ); }

	$r = 0;
	while ( ( $row = fgetcsv( $in, 0, $delim, '"', '' ) ) !== false ) {
		$r++;
		fwrite( $out, '<row r="' . $r . '">' );
		foreach ( $row as $i => $val ) {
			$ref = gv_pm_col_letter( $i ) . $r;
			$key = isset( $cols[ $i ] ) ? $cols[ $i ] : '';
			if ( 1 === $r ) {
				fwrite( $out, '<c r="' . $ref . '" s="1" t="inlineStr"><is><t>' . gv_pm_xml_text( $val ) . '</t></is></c>' );
				continue;
			}
			if ( '' === $val ) { continue; }
			$is_num = ! empty( $fields[ $key ]['numeric'] ) && preg_match( '/^-?\d+(\.\d+)?$/', $val ) && strlen( $val ) <= 15;
			if ( $is_num ) {
				fwrite( $out, '<c r="' . $ref . '"><v>' . $val . '</v></c>' );
			} else {
				fwrite( $out, '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . gv_pm_xml_text( $val ) . '</t></is></c>' );
			}
		}
		fwrite( $out, '</row>' );
	}
	fwrite( $out, '</sheetData></worksheet>' );
	fclose( $out );
	fclose( $in );

	$zip = new ZipArchive();
	if ( true !== $zip->open( $dest, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { @unlink( $sheet ); return false; }
	$zip->addFromString( '[Content_Types].xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' );
	$zip->addFromString( '_rels/.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
	$zip->addFromString( 'xl/workbook.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Products" sheetId="1" r:id="rId1"/></sheets></workbook>' );
	$zip->addFromString( 'xl/_rels/workbook.xml.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
	$zip->addFromString( 'xl/styles.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>' );
	$zip->addFile( $sheet, 'xl/worksheets/sheet1.xml' );
	$ok = $zip->close();
	@unlink( $sheet );
	return $ok;
}

/* ==========================================================================
   ۸) خواندن فایل ورودی (CSV / XLSX) و تبدیل به فایل ردیف‌ها
   ========================================================================== */
function gv_pm_col_index( $ref ) {
	$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
	$n = 0;
	for ( $i = 0, $l = strlen( $letters ); $i < $l; $i++ ) { $n = $n * 26 + ( ord( $letters[ $i ] ) - 64 ); }
	return max( 0, $n - 1 );
}

/** هر ردیف فایل را با callback می‌دهد: $cb( array $cells ) */
function gv_pm_read_xlsx( $path, $cb ) {
	if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'nozip', 'سرور از خواندن فایل Excel پشتیبانی نمی‌کند؛ فایل را به‌صورت CSV ذخیره و ارسال کنید.' ); }
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) { return new WP_Error( 'badzip', 'فایل Excel معتبر نیست.' ); }

	$shared = array();
	$ss = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( false !== $ss ) {
		$xml = simplexml_load_string( $ss, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE );
		if ( $xml ) {
			foreach ( $xml->si as $si ) {
				$t = isset( $si->t ) ? (string) $si->t : '';
				foreach ( $si->r as $r ) { $t .= (string) $r->t; }
				$shared[] = $t;
			}
		}
		unset( $xml, $ss );
	}

	$entry = 'xl/worksheets/sheet1.xml';
	if ( false === $zip->locateName( $entry ) ) {
		$entry = '';
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$n = $zip->getNameIndex( $i );
			if ( preg_match( '#^xl/worksheets/[^/]+\.xml$#', $n ) ) { $entry = $n; break; }
		}
	}
	$zip->close();
	if ( '' === $entry ) { return new WP_Error( 'nosheet', 'هیچ برگه‌ای در فایل Excel پیدا نشد.' ); }

	$x = new XMLReader();
	if ( ! $x->open( 'zip://' . $path . '#' . $entry, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
		return new WP_Error( 'badsheet', 'خواندن برگه‌ی Excel ممکن نشد.' );
	}
	while ( $x->read() ) {
		if ( XMLReader::ELEMENT !== $x->nodeType || 'row' !== $x->localName ) { continue; }
		$node = simplexml_load_string( $x->readOuterXml(), 'SimpleXMLElement', LIBXML_NONET );
		$cells = array();
		if ( $node ) {
			foreach ( $node->c as $c ) {
				$idx  = gv_pm_col_index( (string) $c['r'] );
				$type = (string) $c['t'];
				if ( 's' === $type ) {
					$val = isset( $shared[ (int) $c->v ] ) ? $shared[ (int) $c->v ] : '';
				} elseif ( 'inlineStr' === $type ) {
					$val = '';
					if ( isset( $c->is ) ) {
						$val = isset( $c->is->t ) ? (string) $c->is->t : '';
						foreach ( $c->is->r as $r ) { $val .= (string) $r->t; }
					}
				} elseif ( 'b' === $type ) {
					$val = ( (string) $c->v === '1' ) ? '1' : '0';
				} else {
					$val = (string) $c->v;
				}
				$cells[ $idx ] = $val;
			}
		}
		if ( $cells ) {
			$max = max( array_keys( $cells ) );
			$row = array();
			for ( $i = 0; $i <= $max; $i++ ) { $row[] = isset( $cells[ $i ] ) ? $cells[ $i ] : ''; }
		} else {
			$row = array();
		}
		if ( false === call_user_func( $cb, $row ) ) { break; }
	}
	$x->close();
	return true;
}

function gv_pm_read_csv( $path, $cb ) {
	$content = @file_get_contents( $path );
	if ( false === $content || '' === $content ) { return new WP_Error( 'empty', 'فایل خالی است.' ); }

	if ( 0 === strpos( $content, "\xFF\xFE" ) ) {
		$content = mb_convert_encoding( substr( $content, 2 ), 'UTF-8', 'UTF-16LE' );
	} elseif ( 0 === strpos( $content, "\xFE\xFF" ) ) {
		$content = mb_convert_encoding( substr( $content, 2 ), 'UTF-8', 'UTF-16BE' );
	} else {
		if ( 0 === strpos( $content, "\xEF\xBB\xBF" ) ) { $content = substr( $content, 3 ); }
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			$content = mb_convert_encoding( $content, 'UTF-8', 'Windows-1256' );
		}
	}

	// تشخیص جداکننده از روی خط اول
	$first = strtok( $content, "\n" );
	$cand  = array( ',' => substr_count( $first, ',' ), ';' => substr_count( $first, ';' ), "\t" => substr_count( $first, "\t" ) );
	arsort( $cand );
	$delim = key( $cand );
	if ( 0 === reset( $cand ) ) { $delim = ','; }

	$fh = fopen( 'php://temp', 'w+' );
	fwrite( $fh, $content );
	rewind( $fh );
	unset( $content );
	while ( ( $row = fgetcsv( $fh, 0, $delim, '"', '' ) ) !== false ) {
		if ( false === call_user_func( $cb, $row ) ) { break; }
	}
	fclose( $fh );
	return true;
}

function gv_pm_norm_header( $s ) {
	$s = (string) $s;
	$s = str_replace( array( 'ي', 'ك', "\xE2\x80\x8C", '_', "\xEF\xBB\xBF" ), array( 'ی', 'ک', '', ' ', '' ), $s );
	$s = mb_strtolower( trim( $s ) );
	return preg_replace( '/\s+/u', ' ', $s );
}

function gv_pm_alias_map() {
	static $m = null;
	if ( null !== $m ) { return $m; }
	$m = array();
	foreach ( gv_pm_fields() as $key => $def ) {
		$names = array_merge( array( $key, $def['label'] ), $def['alias'] );
		foreach ( $names as $n ) {
			$nn = gv_pm_norm_header( $n );
			if ( '' !== $nn && ! isset( $m[ $nn ] ) ) { $m[ $nn ] = $key; }
		}
	}
	return $m;
}

/** هر سرستون را به یک فیلد وصل می‌کند (در صورت تشخیص)؛ فیلد تکراری فقط یک‌بار */
function gv_pm_auto_map( $headers ) {
	$map  = gv_pm_alias_map();
	$used = array();
	$out  = array();
	foreach ( $headers as $i => $h ) {
		$k = $map[ gv_pm_norm_header( $h ) ] ?? '';
		if ( '' !== $k && isset( $used[ $k ] ) ) { $k = ''; }
		if ( '' !== $k ) { $used[ $k ] = true; }
		$out[ $i ] = $k;
	}
	return $out;
}

/**
 * فایل آپلودی را می‌خواند و در job-{id}.rows ذخیره می‌کند (هر خط: [شماره‌ی ردیف، [سلول‌ها]])
 */
function gv_pm_ingest( $tmp, $name, $job ) {
	$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'csv', 'txt', 'tsv', 'xlsx' ), true ) ) {
		return new WP_Error( 'ext', 'فرمت فایل پشتیبانی نمی‌شود. فقط CSV یا XLSX بدهید (اگر فایل Excel قدیمی xls دارید، آن را با Save As به xlsx یا csv تبدیل کنید).' );
	}
	$rows_path = gv_pm_path( 'rows', $job );
	$fh = fopen( $rows_path, 'wb' );
	if ( ! $fh ) { return new WP_Error( 'write', 'امکان ذخیره‌ی فایل موقت وجود ندارد.' ); }

	$state = array( 'headers' => null, 'n' => 0, 'rownum' => 0, 'too_many' => false );
	$cb = function ( $row ) use ( &$state, $fh ) {
		$state['rownum']++;
		$row = array_map( function ( $c ) { return is_string( $c ) ? $c : (string) $c; }, $row );
		$empty = true;
		foreach ( $row as $c ) { if ( '' !== trim( $c ) ) { $empty = false; break; } }
		if ( $empty ) { return true; }
		if ( null === $state['headers'] ) {
			$state['headers'] = array_map( function ( $h ) { return trim( str_replace( "\xEF\xBB\xBF", '', $h ) ); }, $row );
			return true;
		}
		if ( $state['n'] >= GV_PM_MAX_ROWS ) { $state['too_many'] = true; return false; }
		$line = wp_json_encode( array( $state['rownum'], $row ), JSON_UNESCAPED_UNICODE );
		if ( false === $line ) { return true; }
		fwrite( $fh, $line . "\n" );
		$state['n']++;
		return true;
	};

	$res = ( 'xlsx' === $ext ) ? gv_pm_read_xlsx( $tmp, $cb ) : gv_pm_read_csv( $tmp, $cb );
	fclose( $fh );
	if ( is_wp_error( $res ) ) { @unlink( $rows_path ); return $res; }
	if ( null === $state['headers'] || ! $state['headers'] ) { @unlink( $rows_path ); return new WP_Error( 'nohead', 'سطر عنوان (سرستون) در فایل پیدا نشد.' ); }
	if ( 0 === $state['n'] ) { @unlink( $rows_path ); return new WP_Error( 'norows', 'فایل فقط سرستون دارد و هیچ ردیف داده‌ای ندارد.' ); }
	return array( 'headers' => $state['headers'], 'total' => $state['n'], 'too_many' => $state['too_many'] );
}

add_action( 'wp_ajax_gv_pm_upload', 'gv_pm_ajax_upload' );
function gv_pm_ajax_upload() {
	gv_pm_check_ajax();
	gv_pm_cleanup_jobs();
	if ( empty( $_FILES['file'] ) || ! empty( $_FILES['file']['error'] ) || empty( $_FILES['file']['tmp_name'] ) ) {
		$code = isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : 0;
		$msg  = ( 1 === $code || 2 === $code ) ? 'حجم فایل از سقف مجاز آپلود سرور بیشتر است.' : 'فایل دریافت نشد؛ دوباره امتحان کنید.';
		wp_send_json_error( array( 'message' => $msg ) );
	}
	$purpose = ( isset( $_POST['purpose'] ) && 'delete' === $_POST['purpose'] ) ? 'delete' : 'update';
	$name    = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) );
	$job     = gv_pm_new_id();

	$res = gv_pm_ingest( $_FILES['file']['tmp_name'], $name, $job );
	if ( is_wp_error( $res ) ) { wp_send_json_error( array( 'message' => $res->get_error_message() ) ); }

	$headers = $res['headers'];
	$map     = gv_pm_auto_map( $headers );

	// چند ردیف اول برای نمایش
	$sample = array();
	$fh = fopen( gv_pm_path( 'rows', $job ), 'rb' );
	while ( count( $sample ) < 3 && ( $line = fgets( $fh ) ) !== false ) {
		$d = json_decode( $line, true );
		if ( $d ) { $sample[] = $d[1]; }
	}
	fclose( $fh );

	gv_pm_json_write( gv_pm_path( 'job', $job ), array(
		'type' => 'import', 'purpose' => $purpose, 'headers' => $headers, 'total' => $res['total'], 'file' => $name, 'created' => time(),
	) );

	// برای حذف: ستون شناسه‌ی پیشنهادی
	$ident_col = null; $ident_match = 'id';
	foreach ( $map as $i => $k ) {
		if ( 'id' === $k ) { $ident_col = $i; $ident_match = 'id'; break; }
	}
	if ( null === $ident_col ) {
		foreach ( $map as $i => $k ) { if ( 'sku' === $k ) { $ident_col = $i; $ident_match = 'sku'; break; } }
	}

	wp_send_json_success( array(
		'job' => $job, 'file' => $name, 'headers' => $headers, 'total' => $res['total'], 'too_many' => $res['too_many'],
		'mapping' => $map, 'sample' => $sample, 'ident_col' => $ident_col, 'ident_match' => $ident_match,
	) );
}

/* ==========================================================================
   ۹) بکاپ‌ها (ثبت، لیست، حذف)
   ========================================================================== */
function gv_pm_backups_get() {
	$b = get_option( GV_PM_BACKUPS_OPT, array() );
	return is_array( $b ) ? $b : array();
}

function gv_pm_backups_save( $b ) {
	update_option( GV_PM_BACKUPS_OPT, $b, false );
}

function gv_pm_backup_create( $type, $extra = array() ) {
	$id   = gv_pm_new_id();
	$path = gv_pm_path( 'backup', $id );
	$meta = array_merge( array( 't' => 'meta', 'type' => $type, 'created' => time(), 'site' => home_url() ), $extra );
	if ( ! gv_pm_append_line( $path, $meta ) ) { return false; }
	$all = gv_pm_backups_get();
	$all[ $id ] = array_merge( array( 'id' => $id, 'type' => $type, 'created' => time(), 'count' => 0, 'restored' => 0 ), $extra );
	gv_pm_backups_save( $all );
	return $id;
}

function gv_pm_backup_set( $id, $patch ) {
	$all = gv_pm_backups_get();
	if ( ! isset( $all[ $id ] ) ) { return; }
	$all[ $id ] = array_merge( $all[ $id ], $patch );
	gv_pm_backups_save( $all );
}

function gv_pm_backup_remove( $id ) {
	$all = gv_pm_backups_get();
	@unlink( gv_pm_path( 'backup', $id ) );
	if ( isset( $all[ $id ] ) ) {
		unset( $all[ $id ] );
		gv_pm_backups_save( $all );
	}
}

function gv_pm_backups_payload() {
	$out = array();
	$all = gv_pm_backups_get();
	uasort( $all, function ( $a, $b ) { return (int) $b['created'] - (int) $a['created']; } );
	foreach ( $all as $id => $b ) {
		$path = gv_pm_path( 'backup', $id );
		if ( ! file_exists( $path ) ) { continue; }
		$out[] = array(
			'id'       => $id,
			'type'     => $b['type'],
			'mode'     => isset( $b['mode'] ) ? $b['mode'] : '',
			'created'  => wp_date( 'Y/m/d - H:i', (int) $b['created'] ),
			'count'    => (int) $b['count'],
			'size'     => gv_pm_format_size( filesize( $path ) ),
			'restored' => ! empty( $b['restored'] ) ? wp_date( 'Y/m/d - H:i', (int) $b['restored'] ) : '',
			'note'     => isset( $b['note'] ) ? $b['note'] : '',
		);
	}
	return $out;
}

function gv_pm_backups_total_size() {
	$n = 0;
	foreach ( gv_pm_backups_get() as $id => $b ) {
		$p = gv_pm_path( 'backup', $id );
		if ( file_exists( $p ) ) { $n += filesize( $p ); }
	}
	return $n;
}

add_action( 'wp_ajax_gv_pm_backups', 'gv_pm_ajax_backups' );
function gv_pm_ajax_backups() {
	gv_pm_check_ajax();
	wp_send_json_success( array( 'items' => gv_pm_backups_payload(), 'size' => gv_pm_format_size( gv_pm_backups_total_size() ) ) );
}

function gv_pm_param_backup() {
	$id  = isset( $_REQUEST['backup'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['backup'] ) ) : '';
	$all = gv_pm_backups_get();
	if ( ! gv_pm_valid_id( $id ) || ! isset( $all[ $id ] ) || ! file_exists( gv_pm_path( 'backup', $id ) ) ) {
		wp_send_json_error( array( 'message' => 'این بکاپ پیدا نشد.' ) );
	}
	return $id;
}

add_action( 'wp_ajax_gv_pm_backup_delete', 'gv_pm_ajax_backup_delete' );
function gv_pm_ajax_backup_delete() {
	gv_pm_check_ajax();
	if ( isset( $_POST['all'] ) && '1' === $_POST['all'] ) {
		foreach ( array_keys( gv_pm_backups_get() ) as $id ) { gv_pm_backup_remove( $id ); }
	} else {
		gv_pm_backup_remove( gv_pm_param_backup() );
	}
	wp_send_json_success( array( 'items' => gv_pm_backups_payload(), 'size' => gv_pm_format_size( gv_pm_backups_total_size() ) ) );
}

add_action( 'admin_post_gv_pm_backup_download', 'gv_pm_handle_backup_download' );
function gv_pm_handle_backup_download() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی کافی ندارید.' ); }
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), GV_PM_NONCE ) ) { wp_die( 'لینک منقضی شده است.' ); }
	$id  = isset( $_GET['backup'] ) ? sanitize_text_field( wp_unslash( $_GET['backup'] ) ) : '';
	$all = gv_pm_backups_get();
	if ( ! gv_pm_valid_id( $id ) || ! isset( $all[ $id ] ) || ! file_exists( gv_pm_path( 'backup', $id ) ) ) { wp_die( 'بکاپ پیدا نشد.' ); }
	$path = gv_pm_path( 'backup', $id );
	while ( ob_get_level() ) { ob_end_clean(); }
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="groot-products-backup-' . $all[ $id ]['type'] . '-' . wp_date( 'Ymd-His', (int) $all[ $id ]['created'] ) . '.ndjson"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path );
	exit;
}

/* ==========================================================================
   ۱۰) به‌روزرسانی از روی فایل
   ========================================================================== */
function gv_pm_term_map( $tax, $refresh = false ) {
	static $cache = array();
	if ( $refresh || ! isset( $cache[ $tax ] ) ) {
		$m = array( 'id' => array(), 'name' => array(), 'slug' => array() );
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'number' => 0 ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$m['id'][ (int) $t->term_id ] = true;
				$nn = gv_pm_norm_header( $t->name );
				if ( ! isset( $m['name'][ $nn ] ) ) { $m['name'][ $nn ] = (int) $t->term_id; }
				$m['slug'][ mb_strtolower( urldecode( $t->slug ) ) ] = (int) $t->term_id;
			}
		}
		$cache[ $tax ] = $m;
	}
	return $cache[ $tax ];
}

function gv_pm_resolve_terms( $text, $tax, $create, &$err ) {
	$ids = array();
	foreach ( (array) preg_split( '/[|\n;؛]+/u', (string) $text ) as $tok ) {
		$tok = trim( $tok );
		if ( '' === $tok ) { continue; }
		$map = gv_pm_term_map( $tax );
		$num = gv_pm_digits( $tok );
		$tid = 0;
		if ( ctype_digit( $num ) && isset( $map['id'][ (int) $num ] ) ) {
			$tid = (int) $num;
		} elseif ( isset( $map['name'][ gv_pm_norm_header( $tok ) ] ) ) {
			$tid = $map['name'][ gv_pm_norm_header( $tok ) ];
		} elseif ( isset( $map['slug'][ mb_strtolower( urldecode( $tok ) ) ] ) ) {
			$tid = $map['slug'][ mb_strtolower( urldecode( $tok ) ) ];
		} elseif ( false !== strpos( $tok, '>' ) ) {
			$parts = explode( '>', $tok );
			$last  = gv_pm_norm_header( end( $parts ) );
			if ( isset( $map['name'][ $last ] ) ) { $tid = $map['name'][ $last ]; }
		}
		if ( ! $tid && $create ) {
			$name = trim( false !== strpos( $tok, '>' ) ? substr( $tok, strrpos( $tok, '>' ) + 1 ) : $tok );
			$ins  = wp_insert_term( $name, $tax );
			if ( ! is_wp_error( $ins ) ) {
				$tid = (int) $ins['term_id'];
				gv_pm_term_map( $tax, true );
			}
		}
		if ( ! $tid ) {
			$err = ( 'product_cat' === $tax ? 'دسته' : 'برچسب' ) . ' «' . $tok . '» در سایت پیدا نشد';
			return null;
		}
		$ids[] = $tid;
	}
	$ids = array_values( array_unique( $ids ) );
	sort( $ids );
	return $ids;
}

function gv_pm_parse_date( $s, &$err ) {
	$s = str_replace( '/', '-', gv_pm_digits( trim( $s ) ) );
	if ( ! preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m ) ) { $err = 'قالب تاریخ باید مثل 2026-03-21 باشد'; return null; }
	if ( (int) $m[1] < 1700 ) { $err = 'تاریخ باید میلادی باشد (مثل 2026-03-21)'; return null; }
	if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) { $err = 'تاریخ معتبر نیست'; return null; }
	return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
}

/**
 * یک سلول را به مقدار استاندارد تبدیل می‌کند.
 * خروجی: null = بدون تغییر، یا array('v' => مقدار). خطا در $err.
 */
function gv_pm_parse_cell( $key, $cell, $opts, &$err ) {
	$defs = gv_pm_fields();
	$cell = (string) $cell;
	$t    = trim( $cell );
	if ( '' === $t ) { return null; }

	if ( gv_pm_is_clear_token( $t ) ) {
		if ( empty( $defs[ $key ]['clear'] ) ) { $err = 'این ستون را نمی‌شود با [خالی] پاک کرد'; return null; }
		return array( 'v' => '' );
	}

	switch ( $key ) {
		case 'name':
			return array( 'v' => $t );
		case 'sku':
			return array( 'v' => $t );
		case 'slug':
			$s = sanitize_title( $t );
			if ( '' === $s ) { $err = 'نامک معتبر نیست'; return null; }
			return array( 'v' => $s );
		case 'status':
			$v = gv_pm_enum_in( 'status', $t );
			if ( null === $v ) { $err = 'وضعیت «' . $t . '» نامعتبر است (منتشر شده / پیش‌نویس / خصوصی / در انتظار بررسی)'; return null; }
			if ( 'trash' === $v ) { $err = 'برای حذف محصول از بخش «حذف با فایل» استفاده کنید'; return null; }
			return array( 'v' => $v );
		case 'stock_status':
			$v = gv_pm_enum_in( 'stock_status', $t );
			if ( null === $v ) { $err = 'وضعیت موجودی «' . $t . '» نامعتبر است (موجود / ناموجود / پیش‌سفارش)'; return null; }
			return array( 'v' => $v );
		case 'featured':
		case 'manage_stock':
			$v = gv_pm_bool_in( $t );
			if ( null === $v ) { $err = 'مقدار «' . $t . '» را با «بله» یا «خیر» بنویسید'; return null; }
			return array( 'v' => $v );
		case 'regular_price':
		case 'sale_price':
		case 'weight':
		case 'length':
		case 'width':
		case 'height':
			$n = gv_pm_num( $t );
			if ( null === $n || (float) $n < 0 ) { $err = 'عدد «' . $t . '» معتبر نیست'; return null; }
			return array( 'v' => (string) ( 0 + $n ) );
		case 'stock_qty':
			$n = gv_pm_num( $t );
			if ( null === $n || (float) $n != (int) (float) $n ) { $err = 'تعداد موجودی باید عدد صحیح باشد («' . $t . '»)'; return null; }
			return array( 'v' => (string) (int) (float) $n );
		case 'sale_from':
		case 'sale_to':
			$d = gv_pm_parse_date( $t, $err );
			return null === $d ? null : array( 'v' => $d );
		case 'short_description':
		case 'description':
			return array( 'v' => $t );
		case 'categories':
			$ids = gv_pm_resolve_terms( $t, 'product_cat', ! empty( $opts['create_terms'] ), $err );
			return null === $ids ? null : array( 'v' => $ids );
		case 'tags':
			$ids = gv_pm_resolve_terms( $t, 'product_tag', ! empty( $opts['create_terms'] ), $err );
			return null === $ids ? null : array( 'v' => $ids );
	}
	return null;
}

function gv_pm_same( $key, $a, $b ) {
	if ( 'parent' === $a || 'parent' === $b ) { return $a === $b; }
	if ( is_array( $a ) || is_array( $b ) ) {
		$a = array_map( 'intval', (array) $a ); $b = array_map( 'intval', (array) $b );
		sort( $a ); sort( $b );
		return $a === $b;
	}
	if ( is_bool( $a ) || is_bool( $b ) ) { return (bool) $a === (bool) $b; }
	if ( in_array( $key, array( 'regular_price', 'sale_price', 'weight', 'length', 'width', 'height', 'stock_qty' ), true ) ) {
		if ( '' === (string) $a || '' === (string) $b ) { return (string) $a === (string) $b; }
		return abs( (float) $a - (float) $b ) < 0.0000001;
	}
	if ( in_array( $key, array( 'description', 'short_description' ), true ) ) {
		$n = function ( $s ) { return trim( str_replace( "\r\n", "\n", (string) $s ) ); };
		return $n( $a ) === $n( $b );
	}
	return (string) $a === (string) $b;
}

/** نمایش خوانا از یک مقدار خام (برای پیش‌نمایش تغییرات) */
function gv_pm_display( $key, $val ) {
	if ( 'categories' === $key ) { $s = gv_pm_term_names( $val, 'product_cat' ); return '' === $s ? '(خالی)' : $s; }
	if ( 'tags' === $key )       { $s = gv_pm_term_names( $val, 'product_tag' ); return '' === $s ? '(خالی)' : $s; }
	if ( 'parent' === $val )     { return 'از والد'; }
	if ( 'featured' === $key || 'manage_stock' === $key ) { return $val ? 'بله' : 'خیر'; }
	if ( 'status' === $key || 'stock_status' === $key ) { return gv_pm_enum_out( $key, $val, 'fa' ); }
	$s = (string) $val;
	if ( in_array( $key, array( 'description', 'short_description' ), true ) && mb_strlen( $s ) > 70 ) { $s = mb_substr( wp_strip_all_tags( $s ), 0, 70 ) . '…'; }
	return '' === $s ? '(خالی)' : $s;
}

function gv_pm_compute_changes( $p, $values, $opts ) {
	$defs   = gv_pm_fields();
	$res    = array( 'changes' => array(), 'errors' => array(), 'warnings' => array() );
	$is_var = $p->is_type( 'variation' );
	$is_vbl = $p->is_type( 'variable' );
	$no_var = array( 'name', 'slug', 'featured', 'short_description', 'categories', 'tags' );
	$no_vbl = array( 'regular_price', 'sale_price', 'sale_from', 'sale_to' );

	foreach ( $values as $key => $cell ) {
		if ( ! isset( $defs[ $key ] ) || empty( $defs[ $key ]['import'] ) ) { continue; }
		$label = $defs[ $key ]['label'];
		if ( '' === trim( (string) $cell ) ) { continue; }
		if ( $is_var && in_array( $key, $no_var, true ) ) { $res['warnings'][] = '«' . $label . '» برای محصولات متغیر (variation) اعمال نمی‌شود'; continue; }
		if ( $is_vbl && in_array( $key, $no_vbl, true ) ) { $res['warnings'][] = 'قیمت محصول متغیر را باید روی خود متغیرها (ردیف‌های variation) تنظیم کنید'; continue; }

		$err = '';
		$parsed = gv_pm_parse_cell( $key, $cell, $opts, $err );
		if ( '' !== $err ) { $res['errors'][] = $label . ': ' . $err; continue; }
		if ( null === $parsed ) { continue; }
		$old = gv_pm_raw_get( $p, $key );
		if ( ! gv_pm_same( $key, $old, $parsed['v'] ) ) {
			$res['changes'][ $key ] = array( 'old' => $old, 'new' => $parsed['v'] );
		}
	}

	// کنترل منطقی قیمت حراج
	if ( isset( $res['changes']['regular_price'] ) || isset( $res['changes']['sale_price'] ) ) {
		$reg  = isset( $res['changes']['regular_price'] ) ? $res['changes']['regular_price']['new'] : gv_pm_raw_get( $p, 'regular_price' );
		$sale = isset( $res['changes']['sale_price'] ) ? $res['changes']['sale_price']['new'] : gv_pm_raw_get( $p, 'sale_price' );
		if ( '' !== (string) $sale && ( '' === (string) $reg || (float) $sale >= (float) $reg ) ) {
			$res['errors'][] = 'قیمت حراج باید کمتر از قیمت اصلی باشد';
		}
	}

	// وقتی «مدیریت موجودی» روشن است، وضعیت موجود/ناموجود را ووکامرس از روی تعداد حساب می‌کند؛ پس تغییرش اثری ندارد
	if ( isset( $res['changes']['stock_status'] ) ) {
		$eff = isset( $res['changes']['manage_stock'] ) ? $res['changes']['manage_stock']['new'] : gv_pm_raw_get( $p, 'manage_stock' );
		$given_m = isset( $values['manage_stock'] ) && '' !== trim( (string) $values['manage_stock'] );
		$will_manage = ( true === $eff ) || ( 'parent' === $eff ) || ( isset( $res['changes']['stock_qty'] ) && '' !== (string) $res['changes']['stock_qty']['new'] && ! $given_m );
		if ( $will_manage ) {
			unset( $res['changes']['stock_status'] );
			$res['warnings'][] = 'وضعیت موجودی روی محصولی که «مدیریت موجودی» دارد اثر ندارد (از روی تعداد محاسبه می‌شود)؛ برای ناموجود کردن، تعداد را ۰ بگذارید';
		}
	}

	// اگر فقط تعداد موجودی داده شده ولی مدیریت موجودی خاموش است، روشنش می‌کنیم
	if ( isset( $res['changes']['stock_qty'] ) && '' !== (string) $res['changes']['stock_qty']['new'] ) {
		$given = isset( $values['manage_stock'] ) && '' !== trim( (string) $values['manage_stock'] );
		$cur   = gv_pm_raw_get( $p, 'manage_stock' );
		if ( ! $given && true !== $cur ) {
			$res['changes']['manage_stock'] = array( 'old' => $cur, 'new' => true );
		}
	}
	return $res;
}

function gv_pm_find_product( $match, $cell ) {
	if ( 'id' === $match ) {
		$id = (int) gv_pm_digits( trim( (string) $cell ) );
		if ( $id <= 0 ) { return null; }
		$p = wc_get_product( $id );
		return $p ? $p : null;
	}
	$sku = trim( (string) $cell );
	if ( '' === $sku ) { return null; }
	$id = wc_get_product_id_by_sku( $sku );
	if ( ! $id ) { return null; }
	$p = wc_get_product( $id );
	return $p ? $p : null;
}

function gv_pm_read_import_settings( $jd ) {
	$mapping = isset( $_POST['mapping'] ) ? json_decode( wp_unslash( $_POST['mapping'] ), true ) : null;
	$match   = ( isset( $_POST['match'] ) && 'sku' === $_POST['match'] ) ? 'sku' : 'id';
	if ( ! is_array( $mapping ) ) { wp_send_json_error( array( 'message' => 'تنظیم ستون‌ها نامعتبر است.' ) ); }

	$defs = gv_pm_fields();
	$cols = array();
	foreach ( $mapping as $idx => $key ) {
		$key = sanitize_key( $key );
		if ( '' === $key || ! isset( $defs[ $key ] ) ) { continue; }
		if ( 'id' !== $key && empty( $defs[ $key ]['import'] ) ) { continue; }
		if ( ! isset( $cols[ $key ] ) ) { $cols[ $key ] = (int) $idx; }
	}
	if ( ! isset( $cols[ $match ] ) ) {
		wp_send_json_error( array( 'message' => ( 'id' === $match ? 'ستونی را به «شناسه» وصل نکرده‌اید.' : 'ستونی را به «کد محصول (SKU)» وصل نکرده‌اید.' ) . ' محصولات از روی همین ستون پیدا می‌شوند.' ) );
	}
	$update = array();
	foreach ( $cols as $k => $i ) {
		if ( 'id' === $k || $k === $match ) { continue; }
		$update[] = $k;
	}
	if ( ! $update ) {
		wp_send_json_error( array( 'message' => 'هیچ ستونی برای به‌روزرسانی انتخاب نشده است (مثلاً قیمت یا موجودی را به یک ستون وصل کنید).' ) );
	}
	return array(
		'cols'         => $cols,
		'match'        => $match,
		'update'       => $update,
		'create_terms' => ! empty( $_POST['create_terms'] ) && '0' !== $_POST['create_terms'],
	);
}

function gv_pm_load_import_job() {
	$id = gv_pm_param_job();
	$jd = gv_pm_json_read( gv_pm_path( 'job', $id ) );
	if ( ! $jd || 'import' !== $jd['type'] || ! file_exists( gv_pm_path( 'rows', $id ) ) ) {
		wp_send_json_error( array( 'message' => 'فایل آپلود‌شده دیگر در دسترس نیست؛ دوباره آپلود کنید.' ) );
	}
	return array( $id, $jd );
}

/** از روی لیستی از شناسه‌ها/SKUها، تعیین می‌کند کدام‌ها در سایت هستند: بازگشت map[ lower(ident) ] = array(ID, type, status, parent) */
function gv_pm_lookup_idents( $match, $idents ) {
	global $wpdb;
	$found = array();
	$idents = array_values( array_unique( $idents ) );
	foreach ( array_chunk( $idents, 500 ) as $chunk ) {
		if ( 'id' === $match ) {
			$in   = implode( ',', array_map( 'intval', $chunk ) );
			$rows = $wpdb->get_results( "SELECT ID, post_type, post_status, post_parent FROM {$wpdb->posts} WHERE ID IN ($in) AND post_type IN ('product','product_variation')", ARRAY_A );
			foreach ( (array) $rows as $r ) { $found[ (string) $r['ID'] ] = array( (int) $r['ID'], $r['post_type'], $r['post_status'], (int) $r['post_parent'] ); }
		} else {
			$ph   = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$sql  = $wpdb->prepare(
				"SELECT p.ID, p.post_type, p.post_status, p.post_parent, pm.meta_value AS sku FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_sku' AND pm.meta_value IN ($ph) AND p.post_type IN ('product','product_variation') AND p.post_status <> 'trash'",
				$chunk
			);
			foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $r ) {
				$found[ mb_strtolower( $r['sku'] ) ] = array( (int) $r['ID'], $r['post_type'], $r['post_status'], (int) $r['post_parent'] );
			}
		}
	}
	return $found;
}

function gv_pm_ident_key( $match, $cell ) {
	if ( 'id' === $match ) {
		$n = (int) gv_pm_digits( trim( (string) $cell ) );
		return $n > 0 ? (string) $n : '';
	}
	return trim( (string) $cell );
}

add_action( 'wp_ajax_gv_pm_import_preview', 'gv_pm_ajax_import_preview' );
function gv_pm_ajax_import_preview() {
	gv_pm_check_ajax();
	list( $id, $jd ) = gv_pm_load_import_job();
	$set = gv_pm_read_import_settings( $jd );
	$defs = gv_pm_fields();

	// ۱) همه‌ی شناسه‌ها را جمع کن و یک‌جا در دیتابیس جستجو کن
	$idents = array(); $rows_n = 0; $empty = 0; $seen = array(); $dups = 0;
	$fh = fopen( gv_pm_path( 'rows', $id ), 'rb' );
	while ( ( $line = fgets( $fh ) ) !== false ) {
		$d = json_decode( $line, true );
		if ( ! $d ) { continue; }
		$rows_n++;
		$k = gv_pm_ident_key( $set['match'], isset( $d[1][ $set['cols'][ $set['match'] ] ] ) ? $d[1][ $set['cols'][ $set['match'] ] ] : '' );
		if ( '' === $k ) { $empty++; continue; }
		$lk = mb_strtolower( $k );
		if ( isset( $seen[ $lk ] ) ) { $dups++; } else { $seen[ $lk ] = true; $idents[] = $k; }
	}
	fclose( $fh );
	$found = gv_pm_lookup_idents( $set['match'], $idents );
	$matched = 0; $not_found = array();
	foreach ( $idents as $k ) {
		if ( isset( $found[ mb_strtolower( $k ) ] ) ) { $matched++; } else { $not_found[] = $k; }
	}

	// ۲) نمونه‌ی تغییرات از ردیف‌های ابتدایی
	$sample = array(); $scanned = 0; $sc_changed = 0; $sc_err = 0;
	$fh = fopen( gv_pm_path( 'rows', $id ), 'rb' );
	while ( $scanned < 300 && ( $line = fgets( $fh ) ) !== false ) {
		$d = json_decode( $line, true );
		if ( ! $d ) { continue; }
		$cells = $d[1];
		$p = gv_pm_find_product( $set['match'], isset( $cells[ $set['cols'][ $set['match'] ] ] ) ? $cells[ $set['cols'][ $set['match'] ] ] : '' );
		if ( ! $p ) { continue; }
		$scanned++;
		$values = array();
		foreach ( $set['update'] as $k ) { $values[ $k ] = isset( $cells[ $set['cols'][ $k ] ] ) ? $cells[ $set['cols'][ $k ] ] : ''; }
		$r = gv_pm_compute_changes( $p, $values, $set );
		if ( $r['errors'] ) { $sc_err++; }
		elseif ( $r['changes'] ) { $sc_changed++; }
		if ( count( $sample ) < 8 && ( $r['changes'] || $r['errors'] ) ) {
			$ch = array();
			foreach ( $r['changes'] as $k => $c ) {
				$ch[] = array( 'label' => $defs[ $k ]['label'], 'old' => gv_pm_display( $k, $c['old'] ), 'new' => gv_pm_display( $k, $c['new'] ) );
			}
			$sample[] = array(
				'row' => $d[0], 'id' => $p->get_id(), 'name' => $p->get_name(), 'sku' => (string) $p->get_sku(),
				'changes' => $ch, 'errors' => $r['errors'], 'warnings' => array_values( array_unique( $r['warnings'] ) ),
			);
		}
	}
	fclose( $fh );

	$used = array();
	foreach ( $set['update'] as $k ) { $used[] = $defs[ $k ]['label']; }

	wp_send_json_success( array(
		'rows' => $rows_n, 'matched' => $matched, 'not_found' => count( $not_found ), 'not_found_list' => array_slice( $not_found, 0, 20 ),
		'empty' => $empty, 'dups' => $dups, 'sample' => $sample, 'scanned' => $scanned, 'scanned_changed' => $sc_changed, 'scanned_errors' => $sc_err,
		'fields' => $used,
	) );
}

add_action( 'wp_ajax_gv_pm_import_run', 'gv_pm_ajax_import_run' );
function gv_pm_ajax_import_run() {
	gv_pm_check_ajax();
	list( $id, $jd ) = gv_pm_load_import_job();
	$spath = gv_pm_path( 'state', $id );
	$state = gv_pm_json_read( $spath );
	$defs  = gv_pm_fields();

	if ( ! $state ) {
		$set = gv_pm_read_import_settings( $jd );
		$bid = gv_pm_backup_create( 'update', array( 'note' => 'به‌روزرسانی از فایل «' . $jd['file'] . '»', 'file' => $jd['file'] ) );
		if ( ! $bid ) { wp_send_json_error( array( 'message' => 'ساخت فایل بکاپ ممکن نیست؛ دسترسی نوشتن پوشه‌ی آپلود را بررسی کنید. (هیچ تغییری اعمال نشد)' ) ); }
		$state = array(
			'pos' => 0, 'processed' => 0, 'updated' => 0, 'unchanged' => 0, 'not_found' => 0, 'empty' => 0, 'errors' => 0,
			'warnings' => 0, 'err_list' => array(), 'nf_list' => array(), 'backup' => $bid, 'settings' => $set, 'done' => false,
		);
	}
	if ( ! empty( $state['done'] ) ) {
		wp_send_json_success( gv_pm_import_state_out( $state, $jd ) );
	}

	$set   = $state['settings'];
	$bpath = gv_pm_path( 'backup', $state['backup'] );
	$fh    = fopen( gv_pm_path( 'rows', $id ), 'rb' );
	fseek( $fh, (int) $state['pos'] );
	$start = microtime( true );
	$n = 0; $reached_end = true; $fatal = '';

	while ( ( $line = fgets( $fh ) ) !== false ) {
		$d = json_decode( $line, true );
		if ( $d ) {
			$rownum = $d[0]; $cells = $d[1];
			$state['processed']++;
			$ident = isset( $cells[ $set['cols'][ $set['match'] ] ] ) ? trim( (string) $cells[ $set['cols'][ $set['match'] ] ] ) : '';
			$p = gv_pm_find_product( $set['match'], $ident );
			if ( ! $p ) {
				if ( '' === $ident ) { $state['empty']++; } else {
					$state['not_found']++;
					if ( count( $state['nf_list'] ) < 50 ) { $state['nf_list'][] = $ident; }
				}
			} else {
				$values = array();
				foreach ( $set['update'] as $k ) { $values[ $k ] = isset( $cells[ $set['cols'][ $k ] ] ) ? $cells[ $set['cols'][ $k ] ] : ''; }
				$r = gv_pm_compute_changes( $p, $values, $set );
				if ( $r['warnings'] ) { $state['warnings']++; }
				if ( $r['errors'] ) {
					$state['errors']++;
					if ( count( $state['err_list'] ) < 100 ) { $state['err_list'][] = array( 'row' => $rownum, 'ident' => $ident, 'msg' => implode( ' — ', $r['errors'] ) ); }
				} elseif ( ! $r['changes'] ) {
					$state['unchanged']++;
				} else {
					$old = array();
					foreach ( $r['changes'] as $k => $c ) { $old[ $k ] = $c['old']; }
					// اول بکاپ، بعد تغییر
					if ( ! gv_pm_append_line( $bpath, array( 't' => 'u', 'id' => $p->get_id(), 'name' => $p->get_name(), 'old' => $old ) ) ) {
						$fatal = 'نوشتن در فایل بکاپ ناموفق بود؛ برای امنیت، عملیات متوقف شد.';
						$state['pos'] = ftell( $fh ) - strlen( $line );
						$reached_end = false;
						break;
					}
					try {
						foreach ( $r['changes'] as $k => $c ) { gv_pm_raw_set( $p, $k, $c['new'] ); }
						$p->save();
						$state['updated']++;
					} catch ( Exception $e ) {
						$state['errors']++;
						if ( count( $state['err_list'] ) < 100 ) { $state['err_list'][] = array( 'row' => $rownum, 'ident' => $ident, 'msg' => $e->getMessage() ); }
					}
				}
			}
		}
		$n++;
		if ( $n >= GV_PM_IMPORT_BATCH || ( microtime( true ) - $start ) >= GV_PM_STEP_SECONDS ) {
			$reached_end = false;
			break;
		}
	}
	if ( $reached_end ) {
		$state['done'] = true;
		$state['pos']  = ftell( $fh );
	} elseif ( '' === $fatal ) {
		$state['pos']  = ftell( $fh );
	}
	fclose( $fh );

	gv_pm_backup_set( $state['backup'], array( 'count' => $state['updated'] ) );
	if ( $state['done'] && 0 === (int) $state['updated'] ) {
		// چیزی تغییر نکرد؛ بکاپ خالی بی‌معنی است
		gv_pm_backup_remove( $state['backup'] );
		$state['backup'] = '';
	}
	gv_pm_json_write( $spath, $state );

	if ( '' !== $fatal ) { wp_send_json_error( array( 'message' => $fatal ) ); }
	wp_send_json_success( gv_pm_import_state_out( $state, $jd ) );
}

function gv_pm_import_state_out( $state, $jd ) {
	return array(
		'done' => ! empty( $state['done'] ), 'processed' => $state['processed'], 'total' => (int) $jd['total'],
		'updated' => $state['updated'], 'unchanged' => $state['unchanged'], 'not_found' => $state['not_found'], 'empty' => $state['empty'],
		'errors' => $state['errors'], 'warnings' => $state['warnings'], 'err_list' => $state['err_list'], 'nf_list' => array_slice( $state['nf_list'], 0, 20 ),
		'backup' => $state['backup'],
	);
}

/* ==========================================================================
   ۱۱) حذف (از روی فایل یا از روی انتخاب لیست) + بکاپ کامل
   پشتیبانی از محصول و متغیر (variation)؛ ثبت علت هر ردیف ناموفق + اکسل گزارش
   ========================================================================== */
function gv_pm_delete_preview( $job, $ids, $extra = array() ) {
	$rows = array();
	foreach ( array_slice( $ids, 0, 60 ) as $pid ) {
		$p = wc_get_product( $pid );
		if ( ! $p ) { continue; }
		$thumb  = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) : '';
		$is_var = $p->is_type( 'variation' );
		$attrs  = '';
		if ( $is_var ) {
			try { $attrs = wp_strip_all_tags( wc_get_formatted_variation( $p, true ) ); } catch ( Throwable $e ) { $attrs = ''; }
		}
		$rows[] = array(
			'id' => $pid, 'name' => $p->get_name(), 'sku' => (string) $p->get_sku(), 'thumb' => $thumb ? $thumb : '',
			'status_fa' => gv_pm_enum_out( 'status', $p->get_status(), 'fa' ), 'type_fa' => gv_pm_enum_out( 'type', $p->get_type(), 'fa' ),
			'is_var' => $is_var, 'parent' => $is_var ? (int) $p->get_parent_id() : 0, 'attrs' => $attrs,
		);
	}
	return array_merge( array( 'job' => $job, 'found' => count( $ids ), 'preview' => $rows ), $extra );
}

add_action( 'wp_ajax_gv_pm_delete_from_file', 'gv_pm_ajax_delete_from_file' );
function gv_pm_ajax_delete_from_file() {
	gv_pm_check_ajax();
	list( $id, $jd ) = gv_pm_load_import_job();
	$col   = isset( $_POST['col'] ) ? (int) $_POST['col'] : -1;
	$match = ( isset( $_POST['match'] ) && 'sku' === $_POST['match'] ) ? 'sku' : 'id';
	if ( $col < 0 || $col >= count( $jd['headers'] ) ) { wp_send_json_error( array( 'message' => 'ستون شناسه را انتخاب کنید.' ) ); }

	// هر بار بررسی از صفر شروع می‌شود
	@unlink( gv_pm_path( 'fail', $id ) );
	@unlink( gv_pm_path( 'state', $id ) );

	$idents = array(); $first = array(); $dups = 0; $empty = 0; $fails = array();
	$fh = fopen( gv_pm_path( 'rows', $id ), 'rb' );
	while ( ( $line = fgets( $fh ) ) !== false ) {
		$d = json_decode( $line, true );
		if ( ! $d ) { continue; }
		$k = gv_pm_ident_key( $match, isset( $d[1][ $col ] ) ? $d[1][ $col ] : '' );
		if ( '' === $k ) {
			$empty++;
			$fails[] = array( 'r' => (int) $d[0], 'i' => '', 'm' => 'ستون شناسه در این ردیف خالی یا نامعتبر است' );
			continue;
		}
		$lk = mb_strtolower( $k );
		if ( isset( $first[ $lk ] ) ) { $dups++; continue; }
		$first[ $lk ] = (int) $d[0];
		$idents[]     = $k;
	}
	fclose( $fh );
	if ( ! $idents && ! $fails ) { wp_send_json_error( array( 'message' => 'در ستون انتخاب‌شده هیچ مقداری پیدا نشد.' ) ); }

	$found = $idents ? gv_pm_lookup_idents( $match, $idents ) : array();
	$why_nf = ( 'id' === $match )
		? 'محصولی با این شناسه در سایت پیدا نشد (قبلاً حذف شده، شناسه اشتباه است، یا شناسه‌ی برگه/نوشته/سفارش است)'
		: 'محصولی با این کد (SKU) پیدا نشد (ممکن است صفر ابتدای کد در Excel حذف شده یا فاصله‌ی اضافه داشته باشد، یا محصول قبلاً حذف شده)';

	$items = array(); $nf = 0; $nf_list = array();
	foreach ( $idents as $k ) {
		$lk  = mb_strtolower( $k );
		$row = $first[ $lk ];
		if ( ! isset( $found[ $lk ] ) ) {
			$nf++;
			if ( count( $nf_list ) < 30 ) { $nf_list[] = $k; }
			$fails[] = array( 'r' => $row, 'i' => $k, 'm' => $why_nf );
			continue;
		}
		$pid = (int) $found[ $lk ][0];
		if ( isset( $items[ $pid ] ) ) { $dups++; continue; }
		$items[ $pid ] = array( 'row' => $row, 'type' => $found[ $lk ][1], 'parent' => isset( $found[ $lk ][3] ) ? (int) $found[ $lk ][3] : 0 );
	}

	// اگر هم والد و هم متغیرش در فایل بود، متغیر همراه والد حذف می‌شود (جداگانه نمی‌خواهد)
	$covered = 0; $vars = 0;
	foreach ( $items as $pid => $it ) {
		if ( 'product_variation' !== $it['type'] ) { continue; }
		if ( isset( $items[ $it['parent'] ] ) && 'product' === $items[ $it['parent'] ]['type'] ) {
			unset( $items[ $pid ] );
			$covered++;
		} else {
			$vars++;
		}
	}
	$ids = array(); $rowmap = array();
	foreach ( $items as $pid => $it ) { $ids[] = (int) $pid; $rowmap[ $pid ] = $it['row']; }

	gv_pm_json_write( gv_pm_path( 'ids', $id ), array( 'ids' => $ids, 'rows' => $rowmap ) );
	$jd['purpose'] = 'delete';
	gv_pm_json_write( gv_pm_path( 'job', $id ), $jd );
	gv_pm_fail_add_many( $id, $fails );

	wp_send_json_success( gv_pm_delete_preview( $id, $ids, array(
		'not_found' => $nf, 'not_found_list' => $nf_list, 'variations' => $vars, 'covered' => $covered,
		'dups' => $dups, 'empty' => $empty, 'fail_count' => count( $fails ),
	) ) );
}

add_action( 'wp_ajax_gv_pm_delete_from_ids', 'gv_pm_ajax_delete_from_ids' );
function gv_pm_ajax_delete_from_ids() {
	gv_pm_check_ajax();
	global $wpdb;
	$raw = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '';
	$ids = array_values( array_unique( array_filter( array_map( 'intval', explode( ',', $raw ) ) ) ) );
	if ( ! $ids ) { wp_send_json_error( array( 'message' => 'هیچ محصولی انتخاب نشده است.' ) ); }
	$ok = array();
	foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
		$in = implode( ',', $chunk );
		foreach ( (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE ID IN ($in) AND post_type = 'product'" ) as $v ) { $ok[] = (int) $v; }
	}
	if ( ! $ok ) { wp_send_json_error( array( 'message' => 'محصولی پیدا نشد.' ) ); }
	$job = gv_pm_new_id();
	gv_pm_json_write( gv_pm_path( 'ids', $job ), array( 'ids' => $ok, 'rows' => array() ) );
	gv_pm_json_write( gv_pm_path( 'job', $job ), array( 'type' => 'import', 'purpose' => 'delete', 'headers' => array(), 'total' => count( $ok ), 'file' => 'انتخاب از لیست محصولات', 'created' => time() ) );
	wp_send_json_success( gv_pm_delete_preview( $job, $ok, array( 'fail_count' => 0 ) ) );
}

function gv_pm_snapshot_post( $id ) {
	global $wpdb;
	$post = get_post( $id, ARRAY_A );
	if ( ! $post ) { return null; }
	unset( $post['filter'] );
	$meta  = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $id ), ARRAY_N );
	$terms = array();
	foreach ( get_object_taxonomies( $post['post_type'] ) as $tax ) {
		$ts = wp_get_object_terms( $id, $tax, array( 'fields' => 'all' ) );
		if ( is_wp_error( $ts ) || ! $ts ) { continue; }
		foreach ( $ts as $t ) { $terms[] = array( $tax, (int) $t->term_id, $t->slug ); }
	}
	return array( 'post' => $post, 'meta' => $meta ? $meta : array(), 'terms' => $terms );
}

function gv_pm_snapshot_product( $id ) {
	$s = gv_pm_snapshot_post( $id );
	if ( ! $s ) { return null; }
	$s['t'] = 'd';
	$s['id'] = (int) $id;
	$s['children'] = array();
	$kids = get_posts( array(
		'post_type' => 'product_variation', 'post_parent' => $id, 'post_status' => array( 'publish', 'private', 'draft', 'pending', 'future', 'trash' ),
		'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true,
	) );
	foreach ( $kids as $k ) {
		$c = gv_pm_snapshot_post( $k );
		if ( $c ) { $s['children'][] = $c; }
	}
	return $s;
}

add_action( 'wp_ajax_gv_pm_delete_run', 'gv_pm_ajax_delete_run' );
function gv_pm_ajax_delete_run() {
	gv_pm_check_ajax();
	$id  = gv_pm_param_job();
	$idl = gv_pm_json_read( gv_pm_path( 'ids', $id ) );
	$jd  = gv_pm_json_read( gv_pm_path( 'job', $id ) );
	if ( ! $idl || ! $jd || 'delete' !== ( isset( $jd['purpose'] ) ? $jd['purpose'] : '' ) ) {
		wp_send_json_error( array( 'message' => 'فهرست حذف دیگر در دسترس نیست؛ از ابتدا شروع کنید.' ) );
	}
	$ids  = array_map( 'intval', (array) $idl['ids'] );
	$rows = ( isset( $idl['rows'] ) && is_array( $idl['rows'] ) ) ? $idl['rows'] : array();
	if ( ! $ids ) { wp_send_json_error( array( 'message' => 'فهرست حذف خالی است.' ) ); }
	$spath = gv_pm_path( 'state', $id );
	$state = gv_pm_json_read( $spath );

	if ( ! $state ) {
		$mode = ( isset( $_POST['mode'] ) && 'permanent' === $_POST['mode'] ) ? 'permanent' : 'trash';
		$bid  = gv_pm_backup_create( 'delete', array( 'mode' => $mode, 'note' => 'حذف گروهی (' . $jd['file'] . ')' ) );
		if ( ! $bid ) { wp_send_json_error( array( 'message' => 'ساخت فایل بکاپ ممکن نیست؛ هیچ محصولی حذف نشد.' ) ); }
		$state = array( 'pos' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0, 'err_list' => array(), 'backup' => $bid, 'mode' => $mode, 'done' => false );
	}
	if ( ! empty( $state['done'] ) ) {
		wp_send_json_success( gv_pm_delete_state_out( $state, count( $ids ), $id ) );
	}

	$bpath = gv_pm_path( 'backup', $state['backup'] );
	$start = microtime( true );
	$n = 0; $fails = array(); $row = 0;
	$note = function ( $kind, $pid, $name, $msg ) use ( &$state, &$fails, &$row ) {
		$state[ $kind ]++;
		$fails[] = array( 'r' => $row, 'i' => (string) $pid, 'm' => $msg );
		if ( count( $state['err_list'] ) < 100 ) { $state['err_list'][] = array( 'id' => $pid, 'name' => $name, 'msg' => $msg ); }
	};

	while ( $state['pos'] < count( $ids ) && $n < GV_PM_DELETE_BATCH && ( microtime( true ) - $start ) < GV_PM_STEP_SECONDS ) {
		$pid = (int) $ids[ $state['pos'] ];
		$state['pos']++; $n++;
		$row = isset( $rows[ $pid ] ) ? (int) $rows[ $pid ] : 0;

		$p = wc_get_product( $pid );
		if ( ! $p ) {
			$note( 'skipped', $pid, '', 'این شناسه در لحظه‌ی حذف دیگر در سایت وجود نداشت (احتمالاً قبلاً حذف شده بود)' );
			continue;
		}
		$name = $p->get_name();
		if ( 'trash' === $state['mode'] && 'trash' === get_post_status( $pid ) ) {
			$note( 'skipped', $pid, $name, 'از قبل در زباله‌دان بود (برای پاک‌کردن همیشگی، حالت «حذف دائمی» را انتخاب کنید)' );
			continue;
		}
		$parent = $p->is_type( 'variation' ) ? (int) $p->get_parent_id() : 0;

		$snap = gv_pm_snapshot_product( $pid );
		if ( ! $snap || ! gv_pm_append_line( $bpath, $snap ) ) {
			$note( 'errors', $pid, $name, 'بکاپ این مورد ساخته نشد (فضای دیسک پر است، محتوا خیلی حجیم است یا کاراکتر نامعتبر دارد)؛ برای امنیت حذف نشد' );
			continue;
		}
		$ok = false; $why = '';
		try {
			$ok = $p->delete( 'permanent' === $state['mode'] );
		} catch ( Throwable $e ) {
			$why = $e->getMessage();
		}
		if ( $ok ) {
			$state['deleted']++;
			if ( $parent ) { gv_pm_sync_parent( $parent ); }
		} else {
			$note( 'errors', $pid, $name, 'ووکامرس حذف را انجام نداد' . ( '' !== $why ? ': ' . $why : ' (احتمالاً افزونه‌ای جلوی حذف را گرفته)' ) );
		}
	}
	if ( $state['pos'] >= count( $ids ) ) { $state['done'] = true; }

	gv_pm_fail_add_many( $id, $fails );
	gv_pm_backup_set( $state['backup'], array( 'count' => $state['deleted'] ) );
	if ( $state['done'] && 0 === (int) $state['deleted'] ) {
		gv_pm_backup_remove( $state['backup'] );
		$state['backup'] = '';
	}
	gv_pm_json_write( $spath, $state );
	wp_send_json_success( gv_pm_delete_state_out( $state, count( $ids ), $id ) );
}

function gv_pm_delete_state_out( $state, $total, $job ) {
	return array(
		'done' => ! empty( $state['done'] ), 'pos' => $state['pos'], 'total' => $total, 'deleted' => $state['deleted'], 'skipped' => $state['skipped'],
		'errors' => $state['errors'], 'err_list' => $state['err_list'], 'backup' => $state['backup'], 'mode' => $state['mode'],
		'job' => $job, 'fail_count' => count( gv_pm_fail_read( $job ) ),
	);
}

/** دانلود Excel ردیف‌های حذف‌نشده؛ همان ستون‌های فایل اصلی + ستون «علت عدم حذف» */
add_action( 'admin_post_gv_pm_fail_report', 'gv_pm_handle_fail_report' );
function gv_pm_handle_fail_report() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی کافی ندارید.' ); }
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), GV_PM_NONCE ) ) { wp_die( 'لینک منقضی شده است.' ); }
	$id = isset( $_GET['job'] ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : '';
	if ( ! gv_pm_valid_id( $id ) ) { wp_die( 'شناسه نامعتبر است.' ); }
	$jd    = gv_pm_json_read( gv_pm_path( 'job', $id ) );
	$fails = gv_pm_fail_read( $id );
	if ( ! $jd || ! $fails ) { wp_die( 'گزارشی برای این عملیات وجود ندارد (یا بیش از ۲۴ ساعت گذشته است).' ); }

	$file_mode = ! empty( $jd['headers'] );
	$headers   = $file_mode ? array_values( $jd['headers'] ) : array( 'شناسه' );
	$n         = count( $headers );
	$by_row = array(); $loose = array();
	foreach ( $fails as $f ) {
		if ( ! empty( $f['r'] ) ) { $by_row[ (int) $f['r'] ] = $f['m']; } else { $loose[] = $f; }
	}

	$csv = gv_pm_path( 'out', $id . 'f' );
	$fh  = @fopen( $csv, 'wb' );
	if ( ! $fh ) { wp_die( 'ساخت فایل گزارش ممکن نیست.' ); }
	fwrite( $fh, "\xEF\xBB\xBF" );
	fputcsv( $fh, array_merge( $headers, array( 'علت عدم حذف' ) ), ',', '"', '' );

	$rows_path = gv_pm_path( 'rows', $id );
	if ( $by_row && file_exists( $rows_path ) ) {
		$rf = fopen( $rows_path, 'rb' );
		while ( ( $line = fgets( $rf ) ) !== false ) {
			$d = json_decode( $line, true );
			if ( ! $d || ! isset( $by_row[ (int) $d[0] ] ) ) { continue; }
			$cells   = array_slice( array_pad( array_map( 'strval', (array) $d[1] ), $n, '' ), 0, $n );
			$cells[] = $by_row[ (int) $d[0] ];
			fputcsv( $fh, $cells, ',', '"', '' );
		}
		fclose( $rf );
	}
	foreach ( $loose as $f ) {
		$cells   = array_pad( array( (string) $f['i'] ), $n, '' );
		$cells[] = $f['m'];
		fputcsv( $fh, $cells, ',', '"', '' );
	}
	fclose( $fh );

	$stamp = wp_date( 'Ymd-His' );
	while ( ob_get_level() ) { ob_end_clean(); }
	nocache_headers();

	if ( class_exists( 'ZipArchive' ) ) {
		$tmp = gv_pm_path( 'out', $id . 'fx' );
		if ( gv_pm_csv_to_xlsx( $csv, ',', array_fill( 0, $n + 1, '' ), $tmp ) ) {
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename="delete-failed-' . $stamp . '.xlsx"' );
			header( 'Content-Length: ' . filesize( $tmp ) );
			readfile( $tmp );
			@unlink( $tmp );
			@unlink( $csv );
			exit;
		}
	}
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="delete-failed-' . $stamp . '.csv"' );
	header( 'Content-Length: ' . filesize( $csv ) );
	readfile( $csv );
	@unlink( $csv );
	exit;
}

/* ==========================================================================
   ۱۲) بازگردانی از بکاپ و حذف نهایی
   ========================================================================== */
function gv_pm_insert_post_raw( $s ) {
	global $wpdb;
	$post = $s['post'];
	$id   = (int) $post['ID'];
	$cols = array( 'ID','post_author','post_date','post_date_gmt','post_content','post_title','post_excerpt','post_status','comment_status','ping_status','post_password','post_name','to_ping','pinged','post_modified','post_modified_gmt','post_content_filtered','post_parent','guid','menu_order','post_type','post_mime_type','comment_count' );
	$row  = array();
	foreach ( $cols as $c ) { if ( array_key_exists( $c, $post ) ) { $row[ $c ] = $post[ $c ]; } }
	if ( false === $wpdb->insert( $wpdb->posts, $row ) ) { return false; }
	foreach ( (array) $s['meta'] as $m ) {
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $m[0], 'meta_value' => $m[1] ) );
	}
	$by_tax = array();
	foreach ( (array) $s['terms'] as $t ) {
		list( $tax, $tid, $slug ) = $t;
		if ( ! taxonomy_exists( $tax ) ) { continue; }
		$term = get_term( (int) $tid, $tax );
		if ( ! $term || is_wp_error( $term ) ) { $term = get_term_by( 'slug', $slug, $tax ); }
		if ( $term && ! is_wp_error( $term ) ) { $by_tax[ $tax ][] = (int) $term->term_id; }
	}
	foreach ( $by_tax as $tax => $tids ) { wp_set_object_terms( $id, $tids, $tax, false ); }
	clean_post_cache( $id );
	return true;
}

function gv_pm_untrash_raw( $id, $post ) {
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_status' => $post['post_status'], 'post_name' => $post['post_name'] ), array( 'ID' => (int) $id ) );
	delete_post_meta( $id, '_wp_trash_meta_status' );
	delete_post_meta( $id, '_wp_trash_meta_time' );
	delete_post_meta( $id, '_wp_desired_post_slug' );
	clean_post_cache( $id );
}

function gv_pm_refresh_product( $id, $child_ids = array() ) {
	try {
		wc_delete_product_transients( $id );
		$ds = WC_Data_Store::load( 'product' );
		$rp = new ReflectionProperty( $ds, 'instance' );
		$rp->setAccessible( true );
		$inst = $rp->getValue( $ds );
		$rm   = new ReflectionMethod( $inst, 'update_lookup_table' );
		$rm->setAccessible( true );
		foreach ( array_merge( array( $id ), $child_ids ) as $pid ) { $rm->invoke( $inst, $pid, 'wc_product_meta_lookup' ); }
		$p = wc_get_product( $id );
		if ( $p && $p->is_type( 'variable' ) ) {
			WC_Product_Variable::sync( $id );
		} elseif ( $p && $p->is_type( 'variation' ) ) {
			gv_pm_sync_parent( (int) $p->get_parent_id() );
		}
	} catch ( Throwable $e ) {
		// بازسازی جدول جستجو اختیاری است؛ خطا نباید بازگردانی را خراب کند.
	}
}

function gv_pm_restore_deleted( $s ) {
	$id  = (int) $s['id'];
	if ( isset( $s['post']['post_type'] ) && 'product_variation' === $s['post']['post_type'] && ! get_post( (int) $s['post']['post_parent'] ) ) {
		return 'noparent';
	}
	$cur = get_post( $id );
	$kid_ids = array();
	if ( $cur ) {
		if ( 'trash' !== $cur->post_status ) { return 'exists'; }
		gv_pm_untrash_raw( $id, $s['post'] );
		foreach ( (array) $s['children'] as $c ) {
			$cid = (int) $c['post']['ID'];
			$kid_ids[] = $cid;
			$cp = get_post( $cid );
			if ( $cp && 'trash' === $cp->post_status ) { gv_pm_untrash_raw( $cid, $c['post'] ); }
			elseif ( ! $cp ) { gv_pm_insert_post_raw( $c ); }
		}
	} else {
		if ( ! gv_pm_insert_post_raw( $s ) ) { return 'error'; }
		foreach ( (array) $s['children'] as $c ) {
			$kid_ids[] = (int) $c['post']['ID'];
			if ( ! get_post( (int) $c['post']['ID'] ) ) { gv_pm_insert_post_raw( $c ); }
		}
	}
	gv_pm_refresh_product( $id, $kid_ids );
	return 'restored';
}

add_action( 'wp_ajax_gv_pm_restore_run', 'gv_pm_ajax_restore_run' );
function gv_pm_ajax_restore_run() {
	gv_pm_check_ajax();
	$bid  = gv_pm_param_backup();
	$all  = gv_pm_backups_get();
	$type = $all[ $bid ]['type'];
	$pos  = isset( $_POST['pos'] ) ? max( 0, (int) $_POST['pos'] ) : 0;
	$fh   = fopen( gv_pm_path( 'backup', $bid ), 'rb' );
	fseek( $fh, $pos );
	$start = microtime( true );
	$n = 0; $r = array( 'restored' => 0, 'exists' => 0, 'missing' => 0, 'errors' => 0 ); $errs = array(); $end = true;

	while ( ( $line = fgets( $fh ) ) !== false ) {
		$d = json_decode( $line, true );
		if ( is_array( $d ) && isset( $d['t'] ) ) {
			if ( 'u' === $d['t'] ) {
				$p = wc_get_product( (int) $d['id'] );
				if ( ! $p ) { $r['missing']++; } else {
					try {
						foreach ( $d['old'] as $k => $v ) { gv_pm_raw_set( $p, $k, $v ); }
						$p->save();
						$r['restored']++;
					} catch ( Exception $e ) {
						$r['errors']++;
						if ( count( $errs ) < 30 ) { $errs[] = '#' . $d['id'] . ': ' . $e->getMessage(); }
					}
				}
			} elseif ( 'd' === $d['t'] ) {
				$res = gv_pm_restore_deleted( $d );
				if ( 'restored' === $res ) { $r['restored']++; } elseif ( 'exists' === $res ) { $r['exists']++; } else {
					$r['errors']++;
					if ( count( $errs ) < 30 ) { $errs[] = '#' . $d['id'] . ': ' . ( 'noparent' === $res ? 'محصول والد این متغیر در سایت نیست؛ اول والد را بازگردانی کنید' : 'بازگردانی انجام نشد' ); }
				}
			}
			$n++;
		}
		if ( $n >= 10 || ( microtime( true ) - $start ) >= GV_PM_STEP_SECONDS ) { $end = false; break; }
	}
	$newpos = ftell( $fh );
	fclose( $fh );
	if ( $end ) { gv_pm_backup_set( $bid, array( 'restored' => time() ) ); }
	wp_send_json_success( array( 'done' => $end, 'pos' => $newpos, 'size' => filesize( gv_pm_path( 'backup', $bid ) ), 'r' => $r, 'errs' => $errs, 'type' => $type ) );
}

/** حذف نهایی محصولاتی که در حالت «زباله‌دان» حذف شده بودند + پاک‌کردن بکاپ */
add_action( 'wp_ajax_gv_pm_finalize_run', 'gv_pm_ajax_finalize_run' );
function gv_pm_ajax_finalize_run() {
	gv_pm_check_ajax();
	$bid = gv_pm_param_backup();
	$all = gv_pm_backups_get();
	if ( 'delete' !== $all[ $bid ]['type'] ) { wp_send_json_error( array( 'message' => 'این عملیات فقط برای بکاپ حذف است.' ) ); }
	$pos = isset( $_POST['pos'] ) ? max( 0, (int) $_POST['pos'] ) : 0;
	$fh  = fopen( gv_pm_path( 'backup', $bid ), 'rb' );
	fseek( $fh, $pos );
	$start = microtime( true ); $n = 0; $purged = 0; $end = true;
	while ( ( $line = fgets( $fh ) ) !== false ) {
		$d = json_decode( $line, true );
		if ( is_array( $d ) && isset( $d['t'] ) && 'd' === $d['t'] ) {
			$pid = (int) $d['id'];
			if ( 'trash' === get_post_status( $pid ) ) {
				$p = wc_get_product( $pid );
				if ( $p && $p->delete( true ) ) { $purged++; }
			}
			$n++;
		}
		if ( $n >= GV_PM_DELETE_BATCH || ( microtime( true ) - $start ) >= GV_PM_STEP_SECONDS ) { $end = false; break; }
	}
	$newpos = ftell( $fh );
	fclose( $fh );
	if ( $end ) { gv_pm_backup_remove( $bid ); }
	wp_send_json_success( array( 'done' => $end, 'pos' => $newpos, 'purged' => $purged ) );
}

/* ==========================================================================
   ۱۳) صفحه‌ی مدیریت (HTML + CSS + JS)
   ========================================================================== */
function gv_pm_cat_options() {
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 2000, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) || ! $terms ) { return array(); }
	$by_parent = array();
	foreach ( $terms as $t ) { $by_parent[ (int) $t->parent ][] = $t; }
	$out = array();
	$walk = function ( $parent, $depth ) use ( &$walk, &$out, $by_parent ) {
		if ( empty( $by_parent[ $parent ] ) || $depth > 6 ) { return; }
		foreach ( $by_parent[ $parent ] as $t ) {
			$out[] = array( 'id' => (int) $t->term_id, 'name' => str_repeat( '— ', $depth ) . $t->name, 'count' => (int) $t->count );
			$walk( (int) $t->term_id, $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $out;
}

function gv_pm_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	if ( ! gv_pm_wc_active() ) {
		echo '<div class="wrap" dir="rtl"><h1>🛍️ مدیریت محصولات</h1><div class="notice notice-warning"><p>این بخش به افزونه‌ی <b>ووکامرس</b> نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.</p></div></div>';
		return;
	}
	gv_pm_cleanup_jobs();

	$cnt   = wp_count_posts( 'product' );
	$total = (int) $cnt->publish + (int) $cnt->draft + (int) $cnt->pending + (int) $cnt->private;
	$fields = array();
	foreach ( gv_pm_fields() as $k => $d ) {
		$fields[] = array( 'key' => $k, 'label' => $d['label'], 'group' => $d['group'], 'import' => ! empty( $d['import'] ) );
	}
	$cfg = array(
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'post'    => admin_url( 'admin-post.php' ),
		'nonce'   => wp_create_nonce( GV_PM_NONCE ),
		'fields'  => $fields,
		'groups'  => gv_pm_groups(),
		'cats'    => gv_pm_cat_options(),
		'currency'=> html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
	);
	$backups_n    = count( gv_pm_backups_payload() );
	$backups_size = gv_pm_format_size( gv_pm_backups_total_size() );
	?>
<div class="wrap gvpm" dir="rtl" id="gvpm">
<style>
.gvpm{--c1:#4f46e5;--c2:#0f766e;--bg:#f6f7fb;--bd:#e5e7eb;--tx:#111827;--mu:#6b7280;--ok:#15803d;--wr:#b45309;--er:#b91c1c;max-width:1320px;color:var(--tx)}
.gvpm *{box-sizing:border-box}
.gvpm-hero{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin:18px 0 18px;padding:22px 26px;border-radius:18px;color:#fff;background:linear-gradient(135deg,#4f46e5 0%,#0f766e 100%);box-shadow:0 10px 30px -12px rgba(79,70,229,.55)}
.gvpm-hero h1{margin:0;color:#fff;font-size:22px;display:flex;align-items:center;gap:10px}
.gvpm-hero p{margin:6px 0 0;opacity:.9;font-size:13px}
.gvpm-hero-stats{display:flex;gap:10px;flex-wrap:wrap}
.gvpm-pill{background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);border-radius:14px;padding:10px 16px;min-width:110px;text-align:center}
.gvpm-pill b{display:block;font-size:20px}.gvpm-pill span{font-size:11.5px;opacity:.9}
.gvpm-tabs{display:flex;gap:6px;flex-wrap:wrap;background:#fff;border:1px solid var(--bd);border-radius:14px;padding:6px;margin-bottom:16px}
.gvpm-tab{border:0;background:transparent;border-radius:10px;padding:10px 16px;font-size:13.5px;cursor:pointer;color:#374151;display:flex;align-items:center;gap:6px;transition:.15s}
.gvpm-tab:hover{background:#f3f4f6}.gvpm-tab.on{background:var(--c1);color:#fff;box-shadow:0 4px 12px -4px rgba(79,70,229,.6)}
.gvpm-tab .bd{background:#ef4444;color:#fff;border-radius:20px;font-size:11px;padding:0 7px;line-height:18px}
.gvpm-pane{display:none}.gvpm-pane.on{display:block}
.gvpm-card{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:20px;margin-bottom:16px}
.gvpm-card h3{margin:0 0 4px;font-size:15px}.gvpm-card .sub{color:var(--mu);font-size:12.5px;margin:0 0 14px}
.gvpm-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.gvpm input[type=text],.gvpm input[type=search],.gvpm select{border:1px solid #d1d5db;border-radius:10px;padding:8px 12px;font-size:13px;min-height:38px;background:#fff;max-width:100%}
.gvpm input:focus,.gvpm select:focus{outline:2px solid #c7d2fe;border-color:var(--c1)}
.gvpm-search{flex:1;min-width:200px}
.gvpm-btn{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:10px;padding:9px 18px;font-size:13px;cursor:pointer;background:var(--c1);color:#fff;transition:.15s;text-decoration:none}
.gvpm-btn:hover{filter:brightness(.92);color:#fff}.gvpm-btn:disabled{opacity:.55;cursor:not-allowed}
.gvpm-btn.g{background:#fff;color:#374151;border:1px solid #d1d5db}.gvpm-btn.g:hover{background:#f9fafb;color:#111}
.gvpm-btn.ok{background:var(--c2)}.gvpm-btn.er{background:var(--er)}.gvpm-btn.wr{background:#d97706}.gvpm-btn.sm{padding:6px 12px;font-size:12px}
.gvpm-bulk{display:none;align-items:center;gap:10px;flex-wrap:wrap;background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:10px 14px;margin-bottom:12px}
.gvpm-bulk.on{display:flex}.gvpm-bulk b{color:#3730a3}
.gvpm-tablewrap{background:#fff;border:1px solid var(--bd);border-radius:16px;overflow:auto}
.gvpm table.t{width:100%;border-collapse:collapse;font-size:13px;min-width:860px}
.gvpm table.t th{background:#f9fafb;text-align:right;padding:11px 12px;font-weight:600;color:#374151;border-bottom:1px solid var(--bd);white-space:nowrap}
.gvpm table.t td{padding:10px 12px;border-bottom:1px solid #f1f1f1;vertical-align:middle}
.gvpm table.t tr:hover td{background:#fafaff}.gvpm table.t tr.sel td{background:#eef2ff}
.gvpm th.cb,.gvpm td.cb{width:36px;text-align:center}
.gvpm input[type=checkbox],.gvpm input[type=radio]{margin:0}
.gvpm-th{width:46px;height:46px;border-radius:10px;object-fit:cover;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:20px;color:#9ca3af}
.gvpm-pn{font-weight:600;color:#111;text-decoration:none}.gvpm-pn:hover{color:var(--c1)}
.gvpm-sku{display:block;font-size:11.5px;color:#9ca3af;direction:ltr;text-align:right}
.gvpm-b{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11.5px;background:#eef2ff;color:#4338ca;white-space:nowrap}
.gvpm-b.g{background:#dcfce7;color:#15803d}.gvpm-b.r{background:#fee2e2;color:#b91c1c}.gvpm-b.y{background:#fef3c7;color:#92400e}.gvpm-b.n{background:#f3f4f6;color:#4b5563}
.gvpm-old{text-decoration:line-through;color:#9ca3af;font-size:12px;display:block}
.gvpm-sale{color:#b91c1c;font-weight:600}
.gvpm .pe{display:inline-block;cursor:text;border-radius:6px;padding:1px 6px;margin:-1px -6px;transition:background .12s}
.gvpm .pe:hover{background:#eef2ff;outline:1px dashed #a5b4fc}
.gvpm .pe.add{font-size:11px;color:#6366f1;opacity:0;margin:2px 0 0;display:block;width:max-content}
.gvpm tr:hover .pe.add{opacity:1}
.gvpm .pe.editing{background:transparent;outline:0;cursor:default}
.gvpm .pe.saving{opacity:.5}
.gvpm .pe-in{width:110px;min-height:30px!important;padding:3px 8px!important;border:2px solid var(--c1)!important;border-radius:8px!important;font-size:13px;text-align:left}
.gvpm tr.flash td{animation:gvflash 1.2s}
@keyframes gvflash{0%{background:#bbf7d0}100%{background:transparent}}
.gvpm-toast{position:fixed;bottom:24px;left:24px;z-index:99999;background:#111827;color:#fff;border-radius:12px;padding:12px 16px;font-size:13px;display:flex;gap:12px;align-items:center;box-shadow:0 10px 30px -8px rgba(0,0,0,.5);max-width:420px}
.gvpm-toast.er{background:#b91c1c}
.gvpm-toast button{background:rgba(255,255,255,.18);border:0;color:#fff;border-radius:8px;padding:4px 10px;cursor:pointer}
.gvpm-pager{display:flex;align-items:center;justify-content:center;gap:10px;padding:14px;font-size:12.5px;color:#4b5563;flex-wrap:wrap}
.gvpm-empty{padding:46px 20px;text-align:center;color:var(--mu)}
.gvpm-fg{margin-bottom:12px}.gvpm-fg h4{margin:0 0 8px;font-size:12.5px;color:#4b5563}
.gvpm-chk{display:inline-flex;align-items:center;gap:6px;border:1px solid #e5e7eb;border-radius:10px;padding:7px 12px;font-size:12.5px;cursor:pointer;background:#fff;margin:0 0 6px 6px;user-select:none}
.gvpm-chk:has(input:checked){background:#eef2ff;border-color:#a5b4fc;color:#3730a3}
.gvpm-opt{display:flex;gap:10px;align-items:flex-start;border:1px solid #e5e7eb;border-radius:12px;padding:12px 14px;cursor:pointer;flex:1;min-width:200px}
.gvpm-opt:has(input:checked){border-color:var(--c1);background:#f5f3ff}.gvpm-opt small{display:block;color:var(--mu);font-size:11.5px;margin-top:2px}
.gvpm-drop{border:2px dashed #c7d2fe;border-radius:16px;padding:34px 20px;text-align:center;background:#fafaff;cursor:pointer;transition:.15s}
.gvpm-drop:hover,.gvpm-drop.dr{background:#eef2ff;border-color:var(--c1)}
.gvpm-drop .ic{font-size:34px}.gvpm-drop p{margin:6px 0 0;color:var(--mu);font-size:12.5px}
.gvpm-prog{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:16px;margin-top:14px;display:none}
.gvpm-prog.on{display:block}.gvpm-bar{height:10px;background:#e0e7ff;border-radius:20px;overflow:hidden;margin-top:10px}
.gvpm-bar i{display:block;height:100%;width:0;background:linear-gradient(90deg,#4f46e5,#0f766e);transition:width .25s}
.gvpm-msg{border-radius:12px;padding:12px 16px;font-size:13px;margin-top:12px;line-height:1.9}
.gvpm-msg.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}.gvpm-msg.er{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.gvpm-msg.wr{background:#fffbeb;border:1px solid #fde68a;color:#92400e}.gvpm-msg.in{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
.gvpm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:12px 0}
.gvpm-st{background:#f9fafb;border:1px solid var(--bd);border-radius:12px;padding:12px;text-align:center}.gvpm-st b{display:block;font-size:19px}.gvpm-st span{font-size:11.5px;color:var(--mu)}
.gvpm-st.ok b{color:var(--ok)}.gvpm-st.wr b{color:var(--wr)}.gvpm-st.er b{color:var(--er)}
.gvpm-map td,.gvpm-map th{padding:8px 10px;border-bottom:1px solid #f1f1f1;font-size:12.5px;text-align:right}
.gvpm-map th{background:#f9fafb}.gvpm-map .smp{color:#6b7280;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gvpm-chg{border:1px solid var(--bd);border-radius:12px;padding:10px 14px;margin-bottom:8px;font-size:12.5px;background:#fff}
.gvpm-chg .h{font-weight:600;margin-bottom:4px}.gvpm-chg .a{color:#b91c1c;text-decoration:line-through}.gvpm-chg .n{color:#15803d;font-weight:600}.gvpm-chg .e{color:#b91c1c}
.gvpm-del{display:flex;gap:10px;align-items:center;border-bottom:1px solid #f1f1f1;padding:8px 0;font-size:13px}
.gvpm-bk{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;border:1px solid var(--bd);border-radius:14px;padding:14px 16px;margin-bottom:10px;background:#fff}
.gvpm-bk .i{font-size:26px}.gvpm-bk .m{flex:1;min-width:220px}.gvpm-bk .m small{color:var(--mu);display:block;margin-top:2px}
.gvpm-note{background:#f8fafc;border:1px solid var(--bd);border-radius:12px;padding:12px 16px;font-size:12.5px;color:#475569;line-height:2}
.gvpm-note code{background:#e2e8f0;border-radius:6px;padding:1px 6px;direction:ltr;display:inline-block}
.gvpm a.lnk{color:var(--c1);cursor:pointer;text-decoration:underline;font-size:12.5px}
@media(max-width:700px){.gvpm-hero{padding:16px}.gvpm-tab{padding:8px 11px;font-size:12.5px}}
</style>

<div class="gvpm-hero">
  <div><h1>🛍️ مدیریت محصولات</h1><p>خروجی Excel، به‌روزرسانی و حذف گروهی محصولات؛ همیشه با بکاپ خودکار و امکان بازگردانی</p></div>
  <div class="gvpm-hero-stats">
    <div class="gvpm-pill"><b id="gvpm-s-total"><?php echo esc_html( number_format_i18n( $total ) ); ?></b><span>کل محصولات</span></div>
    <div class="gvpm-pill"><b id="gvpm-s-bk"><?php echo esc_html( number_format_i18n( $backups_n ) ); ?></b><span>بکاپ نگهداری‌شده</span></div>
    <div class="gvpm-pill"><b id="gvpm-s-size"><?php echo esc_html( $backups_size ); ?></b><span>حجم بکاپ‌ها</span></div>
  </div>
</div>

<div class="gvpm-tabs" id="gvpm-tabs">
  <button class="gvpm-tab on" data-tab="list">📋 لیست محصولات</button>
  <button class="gvpm-tab" data-tab="export">📤 خروجی Excel / CSV</button>
  <button class="gvpm-tab" data-tab="update">✏️ به‌روزرسانی با فایل</button>
  <button class="gvpm-tab" data-tab="delete">🗑️ حذف با فایل</button>
  <button class="gvpm-tab" data-tab="backups">🛟 بکاپ‌ها <span class="bd" id="gvpm-bd" style="display:none"></span></button>
</div>

<!-- ============ لیست ============ -->
<div class="gvpm-pane on" id="pane-list">
  <div class="gvpm-card">
    <div class="gvpm-row">
      <input type="search" class="gvpm-search" id="f-search" placeholder="جستجو در نام یا کد محصول (SKU)…">
      <select id="f-status"><option value="all">همه وضعیت‌ها</option><option value="publish">منتشر شده</option><option value="draft">پیش‌نویس</option><option value="pending">در انتظار بررسی</option><option value="private">خصوصی</option><option value="trash">زباله‌دان</option></select>
      <select id="f-cat"><option value="0">همه دسته‌ها</option></select>
      <select id="f-type"><option value="">همه انواع</option><option value="simple">ساده</option><option value="variable">متغیر</option><option value="grouped">گروهی</option><option value="external">خارجی</option></select>
      <select id="f-stock"><option value="">همه موجودی‌ها</option><option value="instock">موجود</option><option value="outofstock">ناموجود</option><option value="onbackorder">پیش‌سفارش</option></select>
      <select id="f-orderby"><option value="date">جدیدترین</option><option value="title">نام</option><option value="price">قیمت</option><option value="sku">کد محصول</option><option value="stock">موجودی</option><option value="id">شناسه</option></select>
      <select id="f-order"><option value="DESC">نزولی</option><option value="ASC">صعودی</option></select>
      <select id="f-per"><option value="25">۲۵ در صفحه</option><option value="50">۵۰ در صفحه</option><option value="100">۱۰۰ در صفحه</option></select>
    </div>
  </div>
  <div class="gvpm-bulk" id="bulk">
    <b id="bulk-n">۰ انتخاب</b>
    <a class="lnk" id="bulk-all"></a>
    <span style="flex:1"></span>
    <button class="gvpm-btn sm ok" id="bulk-export">📤 خروجی از انتخاب‌ها</button>
    <button class="gvpm-btn sm er" id="bulk-del">🗑️ حذف انتخاب‌ها</button>
    <button class="gvpm-btn sm g" id="bulk-clear">لغو انتخاب</button>
  </div>
  <div class="gvpm-tablewrap">
    <table class="t"><thead><tr>
      <th class="cb"><input type="checkbox" id="chk-page"></th><th style="width:60px"></th><th>محصول</th><th>نوع</th><th>قیمت</th><th>موجودی</th><th>دسته</th><th>وضعیت</th>
    </tr></thead><tbody id="list-body"><tr><td colspan="8" class="gvpm-empty">در حال بارگذاری…</td></tr></tbody></table>
    <div class="gvpm-pager" id="pager"></div>
  </div>
</div>

<!-- ============ خروجی ============ -->
<div class="gvpm-pane" id="pane-export">
  <div class="gvpm-card">
    <h3>۱) کدام محصولات؟</h3><p class="sub">می‌توانید همه‌ی محصولات، نتیجه‌ی فیلتر فعلی لیست، یا فقط موارد انتخاب‌شده را خروجی بگیرید.</p>
    <div class="gvpm-row">
      <label class="gvpm-opt"><input type="radio" name="x-scope" value="all" checked><div>همه‌ی محصولات<small id="x-all-n"></small></div></label>
      <label class="gvpm-opt"><input type="radio" name="x-scope" value="filter"><div>نتیجه‌ی فیلتر لیست<small>همان فیلتری که در تب «لیست» تنظیم کرده‌اید</small></div></label>
      <label class="gvpm-opt"><input type="radio" name="x-scope" value="selected"><div>موارد انتخاب‌شده<small id="x-sel-n">هنوز چیزی انتخاب نشده</small></div></label>
    </div>
  </div>
  <div class="gvpm-card">
    <h3>۲) چه اطلاعاتی؟</h3><p class="sub">ستون «شناسه» همیشه هست (برای تطبیق در به‌روزرسانی/حذف لازم است).</p>
    <div class="gvpm-row" style="margin-bottom:12px">
      <button class="gvpm-btn sm g" data-preset="basic">پایه (نام، SKU، قیمت، موجودی)</button>
      <button class="gvpm-btn sm g" data-preset="price">فقط قیمت‌ها</button>
      <button class="gvpm-btn sm g" data-preset="all">همه</button>
      <button class="gvpm-btn sm g" data-preset="none">هیچ‌کدام</button>
    </div>
    <div id="x-fields"></div>
  </div>
  <div class="gvpm-card">
    <h3>۳) قالب فایل</h3>
    <div class="gvpm-row">
      <select id="x-format"><option value="xlsx">Excel (xlsx)</option><option value="csv">CSV</option></select>
      <select id="x-lang"><option value="fa">سرستون و مقادیر فارسی</option><option value="en">سرستون و مقادیر انگلیسی (برای ایمپورت ووکامرس)</option></select>
      <select id="x-delim"><option value=",">CSV با ویرگول ( , )</option><option value=";">CSV با نقطه‌ویرگول ( ; )</option><option value="tab">CSV با Tab</option></select>
      <label class="gvpm-chk"><input type="checkbox" id="x-var"> شامل متغیرها (variation)</label>
    </div>
    <div style="margin-top:16px"><button class="gvpm-btn ok" id="x-go">📤 ساخت و دانلود فایل</button></div>
    <div class="gvpm-prog" id="x-prog"><div id="x-prog-t">…</div><div class="gvpm-bar"><i id="x-bar"></i></div></div>
    <div id="x-res"></div>
  </div>
</div>

<!-- ============ به‌روزرسانی ============ -->
<div class="gvpm-pane" id="pane-update">
  <div class="gvpm-card" id="u-step1">
    <h3>۱) فایل خود را بدهید</h3>
    <p class="sub">بهترین کار: اول از تب «خروجی» فایل بگیرید، در Excel تغییر بدهید (مثلاً قیمت‌ها) و همان را اینجا بدهید. فقط ستون‌هایی که وصل می‌کنید تغییر می‌کنند و خانه‌ی خالی یعنی «دست نزن».</p>
    <div class="gvpm-drop" id="u-drop"><div class="ic">📄</div><b>فایل Excel یا CSV را اینجا رها کنید یا کلیک کنید</b><p>xlsx ، csv</p></div>
    <input type="file" id="u-file" accept=".xlsx,.csv,.txt,.tsv" hidden>
    <div class="gvpm-note" style="margin-top:14px">
      راهنما: برای <b>خالی کردن</b> یک مقدار (مثلاً پایان حراج) بنویسید <code>[خالی]</code> — قیمت‌ها با ارقام فارسی و جداکننده‌ی هزارگان هم قبول می‌شوند — چند دسته/برچسب را با <code>|</code> جدا کنید.
    </div>
    <div id="u-err"></div>
  </div>
  <div class="gvpm-card" id="u-step2" style="display:none">
    <h3>۲) ستون‌ها را مشخص کنید</h3><p class="sub" id="u-file-info"></p>
    <div class="gvpm-row" style="margin-bottom:12px">
      <span>محصولات را پیدا کن بر اساس:</span>
      <select id="u-match"><option value="id">شناسه (ID)</option><option value="sku">کد محصول (SKU)</option></select>
      <label class="gvpm-chk"><input type="checkbox" id="u-create"> اگر دسته/برچسبی در سایت نبود، بساز</label>
    </div>
    <div style="overflow:auto"><table class="gvpm-map" style="width:100%;border-collapse:collapse"><thead><tr><th>ستون فایل</th><th>نمونه</th><th>وصل به</th></tr></thead><tbody id="u-map"></tbody></table></div>
    <div class="gvpm-row" style="margin-top:16px"><button class="gvpm-btn" id="u-prev">🔍 پیش‌نمایش تغییرات</button><button class="gvpm-btn g" id="u-reset">انتخاب فایل دیگر</button></div>
    <div id="u-prev-res"></div>
  </div>
  <div class="gvpm-card" id="u-step3" style="display:none">
    <h3>۳) اعمال تغییرات</h3><p class="sub">قبل از تغییر هر محصول، مقدار قبلی در بکاپ ذخیره می‌شود. هر وقت خواستید از تب «بکاپ‌ها» برمی‌گردانید.</p>
    <button class="gvpm-btn ok" id="u-run">✅ اعمال تغییرات روی سایت</button>
    <div class="gvpm-prog" id="u-prog"><div id="u-prog-t">…</div><div class="gvpm-bar"><i id="u-bar"></i></div></div>
    <div id="u-res"></div>
  </div>
</div>

<!-- ============ حذف ============ -->
<div class="gvpm-pane" id="pane-delete">
  <div class="gvpm-card" id="d-step1">
    <h3>۱) فهرست محصولاتِ حذفی را بدهید</h3>
    <p class="sub">یک فایل Excel/CSV که ستونی از <b>شناسه</b> یا <b>کد محصول (SKU)</b> دارد. شناسه‌ی <b>متغیرها (variation)</b> هم قبول است؛ در این حالت فقط همان متغیر (مثلاً یک رنگ) حذف می‌شود و محصول اصلی می‌ماند. (یا در تب «لیست» محصولات را تیک بزنید و «حذف انتخاب‌ها» را بزنید.)</p>
    <div class="gvpm-drop" id="d-drop"><div class="ic">🗑️</div><b>فایل فهرست حذف را اینجا رها کنید یا کلیک کنید</b><p>xlsx ، csv</p></div>
    <input type="file" id="d-file" accept=".xlsx,.csv,.txt,.tsv" hidden>
    <div id="d-err"></div>
  </div>
  <div class="gvpm-card" id="d-stepcol" style="display:none">
    <h3>۲) کدام ستون، شناسه‌ی محصول است؟</h3><p class="sub" id="d-file-info"></p>
    <div class="gvpm-row">
      <select id="d-col"></select>
      <select id="d-match"><option value="id">مقادیر این ستون، «شناسه (ID)» هستند</option><option value="sku">مقادیر این ستون، «کد محصول (SKU)» هستند</option></select>
      <button class="gvpm-btn" id="d-check">🔍 بررسی فهرست</button><button class="gvpm-btn g" id="d-reset">فایل دیگر</button>
    </div>
  </div>
  <div class="gvpm-card" id="d-step3" style="display:none">
    <h3 id="d-h3">محصولات پیداشده</h3>
    <div id="d-sum"></div>
    <div id="d-list" style="max-height:340px;overflow:auto;margin:10px 0"></div>
    <div class="gvpm-row" style="margin:12px 0">
      <label class="gvpm-opt"><input type="radio" name="d-mode" value="trash" checked><div>انتقال به زباله‌دان <small>امن‌تر؛ بعداً می‌توانید حذف نهایی کنید. بکاپ کامل هم گرفته می‌شود.</small></div></label>
      <label class="gvpm-opt"><input type="radio" name="d-mode" value="permanent"><div>حذف دائمی <small>بلافاصله و برای همیشه از سایت پاک می‌شود (فقط از طریق بکاپ برمی‌گردد).</small></div></label>
    </div>
    <div class="gvpm-row">
      <input type="text" id="d-confirm" placeholder="برای تأیید، کلمه‌ی «حذف» را بنویسید">
      <button class="gvpm-btn er" id="d-run" disabled>🗑️ حذف محصولات</button>
    </div>
    <div class="gvpm-prog" id="d-prog"><div id="d-prog-t">…</div><div class="gvpm-bar"><i id="d-bar"></i></div></div>
    <div id="d-res"></div>
  </div>
</div>

<!-- ============ بکاپ‌ها ============ -->
<div class="gvpm-pane" id="pane-backups">
  <div class="gvpm-card">
    <h3>بکاپ‌های ذخیره‌شده</h3>
    <p class="sub">با هر به‌روزرسانی یا حذف گروهی، یک بکاپ ساخته می‌شود. اگر همه‌چیز درست بود، بکاپ را پاک کنید تا فضا آزاد شود.</p>
    <div class="gvpm-row"><button class="gvpm-btn g sm" id="bk-refresh">🔄 تازه‌سازی</button><button class="gvpm-btn er sm" id="bk-del-all">پاک‌کردن همه‌ی بکاپ‌ها</button></div>
    <div id="bk-list" style="margin-top:14px"></div>
    <div class="gvpm-prog" id="bk-prog"><div id="bk-prog-t">…</div><div class="gvpm-bar"><i id="bk-bar"></i></div></div>
    <div id="bk-res"></div>
  </div>
</div>

<script>
(function(){
'use strict';
var C = <?php echo wp_json_encode( $cfg ); ?>;
var $ = function(s,r){return (r||document).querySelector(s)};
var $$ = function(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))};
var esc = function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})};
var fa = function(n){return Number(n||0).toLocaleString('fa-IR')};
var sel = new Set(), lastTotal = 0, page = 1, busy = {};

function api(action, data, file){
  var fd = new FormData();
  fd.append('action','gv_pm_'+action); fd.append('nonce',C.nonce);
  Object.keys(data||{}).forEach(function(k){fd.append(k,data[k])});
  if(file) fd.append('file',file);
  return fetch(C.ajax,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){
    return r.text().then(function(t){
      var j; try{j=JSON.parse(t)}catch(e){throw new Error('پاسخ نامعتبر از سرور (احتمالاً خطای PHP یا تایم‌اوت). '+t.replace(/<[^>]+>/g,' ').slice(0,160))}
      if(!j.success) throw new Error((j.data&&j.data.message)||'خطای ناشناخته');
      return j.data;
    });
  });
}
function msg(el,type,html){el.innerHTML='<div class="gvpm-msg '+type+'">'+html+'</div>'}
function prog(p,on,txt,pct){var box=$('#'+p+'-prog');box.classList.toggle('on',on);if(txt!=null)$('#'+p+'-prog-t').innerHTML=txt;if(pct!=null)$('#'+p+'-bar').style.width=pct+'%'}
function filters(){return {search:$('#f-search').value,status:$('#f-status').value,cat:$('#f-cat').value,type:$('#f-type').value,stock:$('#f-stock').value,orderby:$('#f-orderby').value,order:$('#f-order').value}}

/* ---- tabs ---- */
function tab(n){
  $$('.gvpm-tab').forEach(function(b){b.classList.toggle('on',b.dataset.tab===n)});
  $$('.gvpm-pane').forEach(function(p){p.classList.toggle('on',p.id==='pane-'+n)});
  if(n==='backups') loadBackups();
  if(n==='export') syncExportScope();
}
$$('.gvpm-tab').forEach(function(b){b.addEventListener('click',function(){tab(b.dataset.tab)})});

/* ---- list ---- */
C.cats.forEach(function(c){var o=document.createElement('option');o.value=c.id;o.textContent=c.name+' ('+c.count+')';$('#f-cat').appendChild(o)});
function stockBadge(r){
  var cls = r.stock==='instock'?'g':(r.stock==='outofstock'?'r':'y');
  return '<span class="gvpm-b '+cls+'">'+esc(r.stock_fa)+'</span>'+(r.qty!=null?'<span class="gvpm-sku" style="direction:rtl">'+fa(r.qty)+' عدد</span>':'');
}
var ROWS={};
function priceCell(r){
  if(!r.editable){
    if(r.range) return '<b title="قیمت محصول متغیر را از صفحه‌ی خودش ویرایش کنید">'+esc(r.range)+'</b>';
    return '<span style="color:#9ca3af">—</span>';
  }
  var reg=r.regular?esc(r.regular):'<span style="color:#9ca3af">— تعیین قیمت</span>';
  var T=' title="برای ویرایش کلیک کنید"';
  if(r.sale) return '<span class="pe gvpm-old" data-f="regular_price"'+T+'>'+reg+'</span><span class="pe gvpm-sale" data-f="sale_price"'+T+'>'+esc(r.sale)+'</span>';
  return '<b class="pe" data-f="regular_price"'+T+'>'+reg+'</b>'+(r.regular?'<span class="pe add" data-f="sale_price" title="افزودن قیمت حراج">＋ حراج</span>':'');
}
function renderPrice(tr,r){var td=tr.querySelector('.pc');if(td)td.innerHTML=priceCell(r)}
var toastT=null;
function toast(text,type,undo){
  var old=$('#gvpm-toast');if(old)old.remove();
  var t=document.createElement('div');t.id='gvpm-toast';t.className='gvpm-toast'+(type==='er'?' er':'');
  t.innerHTML='<span>'+esc(text)+'</span>'+(undo?'<button type="button">↩️ بازگردانی</button>':'');
  $('#gvpm').appendChild(t);
  if(undo)t.querySelector('button').addEventListener('click',function(){t.remove();undo()});
  clearTimeout(toastT);toastT=setTimeout(function(){t.remove()},type==='er'?7000:6000);
}
function savePrice(id,f,v,tr){
  return api('price_update',{id:id,field:f,value:v}).then(function(res){
    ROWS[id]=res.row;if(tr)renderPrice(tr,res.row);
    if(tr){tr.classList.remove('flash');void tr.offsetWidth;tr.classList.add('flash')}
    return res;
  });
}
function statusBadge(r){var m={publish:'g',draft:'n',pending:'y','private':'y',trash:'r'};return '<span class="gvpm-b '+(m[r.status]||'n')+'">'+esc(r.status_fa)+'</span>'}
var tmr=null;
function loadList(p){
  page = p||1;
  var d = filters(); d.page=page; d.per_page=$('#f-per').value;
  $('#list-body').innerHTML='<tr><td colspan="8" class="gvpm-empty">در حال بارگذاری…</td></tr>';
  api('list',d).then(function(res){
    lastTotal = res.total;
    if(!res.rows.length){$('#list-body').innerHTML='<tr><td colspan="8" class="gvpm-empty">محصولی با این فیلتر پیدا نشد.</td></tr>';$('#pager').innerHTML='';updateBulk();return}
    res.rows.forEach(function(r){ROWS[r.id]=r});
    $('#list-body').innerHTML = res.rows.map(function(r){
      return '<tr data-id="'+r.id+'" class="'+(sel.has(r.id)?'sel':'')+'"><td class="cb"><input type="checkbox" class="rc" value="'+r.id+'"'+(sel.has(r.id)?' checked':'')+'></td>'
      +'<td>'+(r.thumb?'<img class="gvpm-th" src="'+esc(r.thumb)+'" alt="">':'<div class="gvpm-th">🖼️</div>')+'</td>'
      +'<td><a class="gvpm-pn" href="'+esc(r.edit||'#')+'" target="_blank">'+esc(r.name)+'</a><span class="gvpm-sku">#'+r.id+(r.sku?' · '+esc(r.sku):'')+'</span></td>'
      +'<td><span class="gvpm-b">'+esc(r.type_fa)+'</span></td><td class="pc">'+priceCell(r)+'</td><td>'+stockBadge(r)+'</td><td style="max-width:180px">'+esc(r.cats||'—')+'</td><td>'+statusBadge(r)+'</td></tr>';
    }).join('');
    var pg='<button class="gvpm-btn g sm" '+(page<=1?'disabled':'')+' data-p="'+(page-1)+'">قبلی</button><span>صفحه '+fa(res.page)+' از '+fa(res.pages)+' — '+fa(res.total)+' محصول</span><button class="gvpm-btn g sm" '+(page>=res.pages?'disabled':'')+' data-p="'+(page+1)+'">بعدی</button>';
    $('#pager').innerHTML=pg;
    $('#chk-page').checked = $$('.rc').length>0 && $$('.rc').every(function(c){return c.checked});
    updateBulk();
  }).catch(function(e){$('#list-body').innerHTML='<tr><td colspan="8" class="gvpm-empty" style="color:#b91c1c">'+esc(e.message)+'</td></tr>'});
}
$('#pager').addEventListener('click',function(e){var b=e.target.closest('button[data-p]');if(b&&!b.disabled)loadList(+b.dataset.p)});
$('#list-body').addEventListener('click',function(e){
  var s=e.target.closest('.pe');if(!s||s.classList.contains('editing'))return;
  var tr=s.closest('tr'),id=+tr.dataset.id,f=s.dataset.f,r=ROWS[id];if(!r)return;
  var cur=String(f==='sale_price'?r.sale_raw:r.regular_raw||'');
  var inp=document.createElement('input');inp.type='text';inp.className='pe-in';inp.value=cur;inp.dir='ltr';inp.setAttribute('inputmode','decimal');
  s.classList.add('editing');s.textContent='';s.appendChild(inp);inp.focus();inp.select();
  var done=false;
  function cancel(){if(done)return;done=true;renderPrice(tr,r)}
  function save(){
    if(done)return;var v=inp.value.trim();
    if(v===cur.trim()||(f==='regular_price'&&v==='')){cancel();return}
    done=true;inp.disabled=true;s.classList.add('saving');
    savePrice(id,f,v,tr).then(function(res){
      if(!res.changed)return;
      var lbl=f==='sale_price'?'قیمت حراج':'قیمت اصلی';
      toast(lbl+' «'+res.row.name+'» ذخیره شد.','ok',function(){
        savePrice(id,f,res.old,tr).then(function(){toast('به مقدار قبلی برگشت.','ok')}).catch(function(er){toast(er.message,'er')});
      });
    }).catch(function(er){renderPrice(tr,r);toast(er.message,'er')});
  }
  inp.addEventListener('keydown',function(ev){
    if(ev.key==='Enter'){ev.preventDefault();save()}
    else if(ev.key==='Escape'){ev.preventDefault();cancel()}
  });
  inp.addEventListener('blur',save);
});
$('#list-body').addEventListener('change',function(e){
  if(!e.target.classList.contains('rc'))return;
  var id=+e.target.value; if(e.target.checked)sel.add(id); else sel.delete(id);
  e.target.closest('tr').classList.toggle('sel',e.target.checked);
  $('#chk-page').checked = $$('.rc').every(function(c){return c.checked}); updateBulk();
});
$('#chk-page').addEventListener('change',function(e){
  $$('.rc').forEach(function(c){c.checked=e.target.checked;var id=+c.value;if(e.target.checked)sel.add(id);else sel.delete(id);c.closest('tr').classList.toggle('sel',e.target.checked)});updateBulk();
});
function updateBulk(){
  $('#bulk').classList.toggle('on',sel.size>0);
  $('#bulk-n').textContent=fa(sel.size)+' محصول انتخاب شده';
  var a=$('#bulk-all'); if(lastTotal>sel.size){a.style.display='';a.textContent='انتخاب همه‌ی '+fa(lastTotal)+' نتیجه‌ی این فیلتر'}else a.style.display='none';
  $('#x-sel-n').textContent = sel.size? fa(sel.size)+' محصول انتخاب شده':'هنوز چیزی انتخاب نشده';
}
$('#bulk-all').addEventListener('click',function(){
  this.textContent='در حال انتخاب…';
  api('ids',filters()).then(function(r){r.ids.forEach(function(i){sel.add(i)});$$('.rc').forEach(function(c){c.checked=sel.has(+c.value);c.closest('tr').classList.toggle('sel',c.checked)});updateBulk()}).catch(function(e){alert(e.message)});
});
$('#bulk-clear').addEventListener('click',function(){sel.clear();$$('.rc').forEach(function(c){c.checked=false;c.closest('tr').classList.remove('sel')});$('#chk-page').checked=false;updateBulk()});
$('#bulk-export').addEventListener('click',function(){tab('export');document.querySelector('input[name=x-scope][value=selected]').checked=true});
$('#bulk-del').addEventListener('click',function(){tab('delete');startDeleteFromIds()});
['f-status','f-cat','f-type','f-stock','f-orderby','f-order','f-per'].forEach(function(i){$('#'+i).addEventListener('change',function(){loadList(1)})});
$('#f-search').addEventListener('input',function(){clearTimeout(tmr);tmr=setTimeout(function(){loadList(1)},450)});

/* ---- export ---- */
var presets={basic:['sku','name','type','status','regular_price','sale_price','stock_qty','stock_status'],price:['sku','name','regular_price','sale_price','sale_from','sale_to']};
(function(){
  var h='';
  Object.keys(C.groups).forEach(function(g){
    var items=C.fields.filter(function(f){return f.group===g&&f.key!=='id'}); if(!items.length)return;
    h+='<div class="gvpm-fg"><h4>'+esc(C.groups[g])+'</h4>'+items.map(function(f){return '<label class="gvpm-chk"><input type="checkbox" class="xf" value="'+f.key+'"> '+esc(f.label)+'</label>'}).join('')+'</div>';
  });
  $('#x-fields').innerHTML=h; $('#x-all-n').textContent=fa($('#gvpm-s-total').textContent?parseInt(String($('#gvpm-s-total').textContent).replace(/[^\d]/g,''))||0:0)+' محصول';
  setPreset('basic');
})();
function setPreset(p){var list=p==='all'?C.fields.map(function(f){return f.key}):(presets[p]||[]);$$('.xf').forEach(function(c){c.checked=list.indexOf(c.value)>-1})}
$$('[data-preset]').forEach(function(b){b.addEventListener('click',function(){setPreset(b.dataset.preset)})});
function syncExportScope(){updateBulk(); if(sel.size&&document.querySelector('input[name=x-scope]:checked').value==='all'&&false){}}
$('#x-go').addEventListener('click',function(){
  var fields=$$('.xf').filter(function(c){return c.checked}).map(function(c){return c.value});
  if(!fields.length){msg($('#x-res'),'er','حداقل یک ستون را انتخاب کنید.');return}
  var scope=document.querySelector('input[name=x-scope]:checked').value;
  var d=filters(); d.fields=fields.join(','); d.scope=scope; d.format=$('#x-format').value; d.lang=$('#x-lang').value; d.delim=$('#x-delim').value; d.variations=$('#x-var').checked?1:'';
  if(scope==='selected'){if(!sel.size){msg($('#x-res'),'er','ابتدا در تب «لیست» محصولات را انتخاب کنید.');return}d.ids=Array.from(sel).join(',')}
  var btn=this; btn.disabled=true; $('#x-res').innerHTML=''; prog('x',true,'در حال آماده‌سازی…',2);
  api('export_start',d).then(function(s){
    function step(){
      api('export_step',{job:s.job}).then(function(r){
        prog('x',true,'در حال ساخت فایل… '+fa(r.offset)+' از '+fa(r.total),Math.round(r.offset/r.total*100));
        if(!r.done)return step();
        prog('x',false);btn.disabled=false;
        var url=C.post+'?action=gv_pm_download&job='+s.job+'&_wpnonce='+C.nonce;
        msg($('#x-res'),'ok','✅ فایل آماده شد ('+fa(r.total)+' ردیف). اگر دانلود شروع نشد <a class="lnk" href="'+url+'">اینجا کلیک کنید</a>.'+(s.notice?'<br>'+esc(s.notice):''));
        window.location.href=url;
      }).catch(fail);
    }
    step();
  }).catch(fail);
  function fail(e){prog('x',false);btn.disabled=false;msg($('#x-res'),'er',esc(e.message))}
});

/* ---- upload helper ---- */
function hookDrop(dropId,fileId,cb){
  var dz=$('#'+dropId),fi=$('#'+fileId);
  dz.addEventListener('click',function(){fi.value='';fi.click()});
  ['dragover','dragenter'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.add('dr')})});
  ['dragleave','drop'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.remove('dr')})});
  dz.addEventListener('drop',function(e){if(e.dataTransfer.files[0])cb(e.dataTransfer.files[0])});
  fi.addEventListener('change',function(){if(fi.files[0])cb(fi.files[0])});
}

/* ---- update ---- */
var U={job:null,headers:[],mapping:[]};
function mapOptions(selKey){
  var h='<option value="">— نادیده بگیر —</option>';
  C.fields.filter(function(f){return f.import||f.key==='id'}).forEach(function(f){h+='<option value="'+f.key+'"'+(f.key===selKey?' selected':'')+'>'+esc(f.label)+'</option>'});
  return h;
}
hookDrop('u-drop','u-file',function(file){
  $('#u-err').innerHTML=''; msg($('#u-err'),'in','در حال خواندن فایل…');
  api('upload',{purpose:'update'},file).then(function(r){
    U.job=r.job;U.headers=r.headers;$('#u-err').innerHTML='';
    $('#u-step2').style.display='';$('#u-step3').style.display='none';$('#u-prev-res').innerHTML='';
    $('#u-file-info').innerHTML='فایل «'+esc(r.file)+'» — '+fa(r.total)+' ردیف'+(r.too_many?' (فقط ۵۰٬۰۰۰ ردیف اول خوانده شد)':'');
    $('#u-map').innerHTML=r.headers.map(function(h,i){
      var smp=r.sample.map(function(s){return s[i]||''}).filter(Boolean).slice(0,2).join(' ، ');
      return '<tr><td><b>'+esc(h||('ستون '+(i+1)))+'</b></td><td class="smp">'+esc(smp)+'</td><td><select class="um" data-i="'+i+'">'+mapOptions(r.mapping[i])+'</select></td></tr>';
    }).join('');
    $('#u-match').value = r.mapping.indexOf('id')>-1?'id':(r.mapping.indexOf('sku')>-1?'sku':'id');
    $('#u-step2').scrollIntoView({behavior:'smooth',block:'start'});
  }).catch(function(e){msg($('#u-err'),'er',esc(e.message))});
});
function mapping(){var m={};$$('.um').forEach(function(s){if(s.value)m[s.dataset.i]=s.value});return m}
function ud(){return {job:U.job,mapping:JSON.stringify(mapping()),match:$('#u-match').value,create_terms:$('#u-create').checked?1:0}}
$('#u-reset').addEventListener('click',function(){$('#u-step2').style.display='none';$('#u-step3').style.display='none'});
$('#u-prev').addEventListener('click',function(){
  var btn=this;btn.disabled=true;msg($('#u-prev-res'),'in','در حال بررسی فایل…');$('#u-step3').style.display='none';
  api('import_preview',ud()).then(function(r){
    var h='<div class="gvpm-stats">'
      +'<div class="gvpm-st"><b>'+fa(r.rows)+'</b><span>ردیف در فایل</span></div>'
      +'<div class="gvpm-st ok"><b>'+fa(r.matched)+'</b><span>محصول پیدا شد</span></div>'
      +'<div class="gvpm-st '+(r.not_found?'wr':'')+'"><b>'+fa(r.not_found)+'</b><span>پیدا نشد</span></div>'
      +(r.dups?'<div class="gvpm-st wr"><b>'+fa(r.dups)+'</b><span>ردیف تکراری</span></div>':'')
      +(r.empty?'<div class="gvpm-st wr"><b>'+fa(r.empty)+'</b><span>بدون شناسه</span></div>':'')+'</div>';
    h+='<p style="font-size:12.5px;color:#4b5563">فیلدهایی که به‌روز می‌شوند: <b>'+r.fields.map(esc).join('، ')+'</b></p>';
    if(r.not_found_list.length) h+='<div class="gvpm-msg wr">محصولاتی که پیدا نشدند (نمونه): '+r.not_found_list.map(esc).join('، ')+'</div>';
    if(r.sample.length){
      h+='<h4 style="margin:16px 0 8px">نمونه‌ی تغییرات (از '+fa(r.scanned)+' ردیف اول بررسی‌شده)</h4>';
      h+=r.sample.map(function(s){
        return '<div class="gvpm-chg"><div class="h">#'+s.id+' — '+esc(s.name)+' <span style="color:#9ca3af;font-weight:400">(ردیف '+fa(s.row)+')</span></div>'
        +s.changes.map(function(c){return '<div>'+esc(c.label)+': <span class="a">'+esc(c.old)+'</span> ← <span class="n">'+esc(c.new)+'</span></div>'}).join('')
        +s.errors.map(function(x){return '<div class="e">⛔ '+esc(x)+' (این ردیف اعمال نمی‌شود)</div>'}).join('')
        +s.warnings.map(function(x){return '<div style="color:#92400e">⚠️ '+esc(x)+'</div>'}).join('')+'</div>';
      }).join('');
    } else if(r.matched) h+='<div class="gvpm-msg wr">در ردیف‌های بررسی‌شده هیچ تغییری دیده نشد؛ مقادیر فایل با سایت یکسان است.</div>';
    $('#u-prev-res').innerHTML=h; btn.disabled=false;
    if(r.matched){$('#u-step3').style.display='';$('#u-res').innerHTML=''}
  }).catch(function(e){btn.disabled=false;msg($('#u-prev-res'),'er',esc(e.message))});
});
$('#u-run').addEventListener('click',function(){
  if(!confirm('تغییرات روی سایت اعمال شود؟ (بکاپ خودکار گرفته می‌شود)'))return;
  var btn=this;btn.disabled=true;$('#u-res').innerHTML='';prog('u',true,'شروع…',1);
  var d=ud();
  function step(){
    api('import_run',d).then(function(r){
      prog('u',true,'در حال به‌روزرسانی… '+fa(r.processed)+' از '+fa(r.total)+' — '+fa(r.updated)+' محصول تغییر کرد',Math.round(r.processed/r.total*100));
      if(!r.done)return step();
      prog('u',false);btn.disabled=false;showImportResult(r);refreshBackupStats();
    }).catch(function(e){prog('u',false);btn.disabled=false;msg($('#u-res'),'er',esc(e.message)+'<br>هر تغییری که تا اینجا اعمال شده در بکاپ ثبت است؛ از تب «بکاپ‌ها» می‌توانید برگردانید.');refreshBackupStats()});
  }
  step();
});
function showImportResult(r){
  var h='<div class="gvpm-stats"><div class="gvpm-st ok"><b>'+fa(r.updated)+'</b><span>به‌روز شد</span></div><div class="gvpm-st"><b>'+fa(r.unchanged)+'</b><span>بدون تغییر</span></div><div class="gvpm-st '+(r.not_found?'wr':'')+'"><b>'+fa(r.not_found)+'</b><span>پیدا نشد</span></div><div class="gvpm-st '+(r.errors?'er':'')+'"><b>'+fa(r.errors)+'</b><span>ردیف دارای خطا</span></div></div>';
  if(r.err_list&&r.err_list.length) h+='<div class="gvpm-msg er"><b>خطاها (این ردیف‌ها اعمال نشدند):</b><br>'+r.err_list.slice(0,25).map(function(e){return 'ردیف '+fa(e.row)+' ('+esc(e.ident)+'): '+esc(e.msg)}).join('<br>')+(r.err_list.length>25?'<br>…':'')+'</div>';
  if(r.backup) h+='<div class="gvpm-msg ok">✅ بکاپ ساخته شد. اگر نتیجه درست نبود، از تب «بکاپ‌ها» <b>بازگردانی</b> کنید؛ اگر درست بود، همان‌جا بکاپ را <b>پاک</b> کنید. <br><button class="gvpm-btn sm g" onclick="document.querySelector(\'[data-tab=backups]\').click()">رفتن به بکاپ‌ها</button></div>';
  else h+='<div class="gvpm-msg wr">هیچ محصولی تغییر نکرد، پس بکاپی هم ساخته نشد.</div>';
  $('#u-res').innerHTML=h;
}

/* ---- delete ---- */
var D={job:null,found:0};
function failLink(job){return C.post+'?action=gv_pm_fail_report&job='+job+'&_wpnonce='+C.nonce}
function failBox(job,n){
  return n?'<div class="gvpm-msg wr">⚠️ <b>'+fa(n)+'</b> ردیف حذف نشد (یا قابل حذف نبود). علت هر ردیف در فایل زیر نوشته شده؛ بعد از رفع مشکل می‌توانید همان فایل را دوباره آپلود کنید.<br><a class="gvpm-btn sm wr" style="margin-top:8px" href="'+failLink(job)+'">⬇️ دانلود اکسل ردیف‌های حذف‌نشده + علت</a></div>':'';
}
function showDelPreview(r,title){
  D.job=r.job;D.found=r.found;
  $('#d-step3').style.display='';$('#d-h3').textContent=title||'محصولات پیداشده';
  var h='<div class="gvpm-stats"><div class="gvpm-st er"><b>'+fa(r.found)+'</b><span>مورد برای حذف</span></div>'
   +(r.variations?'<div class="gvpm-st"><b>'+fa(r.variations)+'</b><span>از این‌ها «متغیر» (variation)</span></div>':'')
   +(r.not_found!=null?'<div class="gvpm-st '+(r.not_found?'wr':'')+'"><b>'+fa(r.not_found)+'</b><span>در سایت پیدا نشد</span></div>':'')
   +(r.covered?'<div class="gvpm-st"><b>'+fa(r.covered)+'</b><span>متغیرِ همراه والد</span></div>':'')
   +(r.dups?'<div class="gvpm-st"><b>'+fa(r.dups)+'</b><span>تکراری</span></div>':'')
   +(r.empty?'<div class="gvpm-st wr"><b>'+fa(r.empty)+'</b><span>بدون شناسه</span></div>':'')+'</div>';
  if(r.variations) h+='<div class="gvpm-msg in">'+fa(r.variations)+' مورد «متغیر» (مثلاً یک رنگ/سایز) است؛ فقط همان متغیرها حذف می‌شوند و محصول اصلی (والد) می‌ماند.</div>';
  if(r.covered) h+='<div class="gvpm-msg in">'+fa(r.covered)+' متغیر چون والدشان هم در فهرست بود، همراه والد حذف می‌شوند.</div>';
  if(r.not_found_list&&r.not_found_list.length) h+='<div class="gvpm-msg wr">پیدا نشدند (نمونه): '+r.not_found_list.map(esc).join('، ')+'</div>';
  h+=failBox(r.job,r.fail_count);
  if(!r.found) h+='<div class="gvpm-msg er">هیچ موردی برای حذف پیدا نشد.</div>';
  $('#d-sum').innerHTML=h;
  $('#d-list').innerHTML=r.preview.map(function(p){
    var tag=p.is_var?'<span class="gvpm-b y">متغیر از #'+p.parent+'</span>':'<span class="gvpm-b">'+esc(p.type_fa)+'</span>';
    return '<div class="gvpm-del">'+(p.thumb?'<img class="gvpm-th" style="width:38px;height:38px" src="'+esc(p.thumb)+'">':'<div class="gvpm-th" style="width:38px;height:38px">🖼️</div>')
    +'<div style="flex:1"><b>'+esc(p.name)+'</b>'+(p.attrs?'<span class="gvpm-sku" style="direction:rtl">'+esc(p.attrs)+'</span>':'')+'<span class="gvpm-sku">#'+p.id+(p.sku?' · '+esc(p.sku):'')+'</span></div>'+tag+'<span class="gvpm-b n">'+esc(p.status_fa)+'</span></div>';
  }).join('')+(r.found>r.preview.length?'<div style="padding:8px;color:#6b7280;font-size:12.5px">و '+fa(r.found-r.preview.length)+' مورد دیگر…</div>':'');
  $('#d-confirm').value='';$('#d-run').disabled=true;$('#d-res').innerHTML='';
  $('#d-step3').scrollIntoView({behavior:'smooth',block:'start'});
}
hookDrop('d-drop','d-file',function(file){
  msg($('#d-err'),'in','در حال خواندن فایل…');
  api('upload',{purpose:'delete'},file).then(function(r){
    D.job=r.job;$('#d-err').innerHTML='';$('#d-step3').style.display='none';
    $('#d-stepcol').style.display='';
    $('#d-file-info').innerHTML='فایل «'+esc(r.file)+'» — '+fa(r.total)+' ردیف';
    $('#d-col').innerHTML=r.headers.map(function(h,i){return '<option value="'+i+'">'+esc(h||('ستون '+(i+1)))+'</option>'}).join('');
    if(r.ident_col!=null)$('#d-col').value=r.ident_col;
    $('#d-match').value=r.ident_match||'id';
  }).catch(function(e){msg($('#d-err'),'er',esc(e.message))});
});
$('#d-reset').addEventListener('click',function(){$('#d-stepcol').style.display='none';$('#d-step3').style.display='none'});
$('#d-check').addEventListener('click',function(){
  var btn=this;btn.disabled=true;
  api('delete_from_file',{job:D.job,col:$('#d-col').value,match:$('#d-match').value}).then(function(r){btn.disabled=false;showDelPreview(r)}).catch(function(e){btn.disabled=false;$('#d-step3').style.display='';D.found=0;$('#d-sum').innerHTML='';$('#d-list').innerHTML='';$('#d-run').disabled=true;msg($('#d-res'),'er',esc(e.message))});
});
function startDeleteFromIds(){
  if(!sel.size)return;
  $('#d-stepcol').style.display='none';
  api('delete_from_ids',{ids:Array.from(sel).join(',')}).then(function(r){showDelPreview(r,'محصولات انتخاب‌شده از لیست')}).catch(function(e){alert(e.message)});
}
$('#d-confirm').addEventListener('input',function(){$('#d-run').disabled=!(D.found>0)||this.value.trim()!=='حذف'});
$('#d-run').addEventListener('click',function(){
  var mode=document.querySelector('input[name=d-mode]:checked').value;
  if(mode==='permanent'&&!confirm('حذف دائمی انجام شود؟ فقط از طریق بکاپ قابل بازگشت است.'))return;
  var btn=this;btn.disabled=true;$('#d-res').innerHTML='';prog('d',true,'شروع…',1);
  function step(){
    api('delete_run',{job:D.job,mode:mode}).then(function(r){
      prog('d',true,'در حال حذف… '+fa(r.pos)+' از '+fa(r.total),Math.round(r.pos/r.total*100));
      if(!r.done)return step();
      prog('d',false);refreshBackupStats();
      var h='<div class="gvpm-stats"><div class="gvpm-st ok"><b>'+fa(r.deleted)+'</b><span>'+(r.mode==='trash'?'به زباله‌دان رفت':'برای همیشه حذف شد')+'</span></div><div class="gvpm-st '+(r.skipped?'wr':'')+'"><b>'+fa(r.skipped)+'</b><span>رد شد</span></div><div class="gvpm-st '+(r.errors?'er':'')+'"><b>'+fa(r.errors)+'</b><span>خطا</span></div></div>';
      if(r.err_list.length)h+='<div class="gvpm-msg er"><b>علت ردیف‌های حذف‌نشده:</b><br>'+r.err_list.slice(0,25).map(function(e){return '#'+e.id+' '+esc(e.name)+': '+esc(e.msg)}).join('<br>')+(r.err_list.length>25?'<br>…':'')+'</div>';
      h+=failBox(r.job,r.fail_count);
      if(r.backup)h+='<div class="gvpm-msg ok">✅ بکاپ کامل نگهداری شد. اگر اشتباه شده، از تب «بکاپ‌ها» برگردانید؛ اگر همه‌چیز درست بود، همان‌جا بکاپ را پاک کنید.<br><button class="gvpm-btn sm g" onclick="document.querySelector(\'[data-tab=backups]\').click()">رفتن به بکاپ‌ها</button></div>';
      $('#d-res').innerHTML=h;sel.clear();updateBulk();loadList(1);
    }).catch(function(e){prog('d',false);msg($('#d-res'),'er',esc(e.message));refreshBackupStats()});
  }
  step();
});

/* ---- backups ---- */
function refreshBackupStats(){api('backups',{}).then(function(r){renderBackups(r,true)}).catch(function(){})}
function loadBackups(){$('#bk-list').innerHTML='<div class="gvpm-empty">در حال بارگذاری…</div>';api('backups',{}).then(renderBackups).catch(function(e){$('#bk-list').innerHTML='<div class="gvpm-msg er">'+esc(e.message)+'</div>'})}
function renderBackups(r,statsOnly){
  $('#gvpm-s-bk').textContent=fa(r.items.length);$('#gvpm-s-size').textContent=r.size;
  var bd=$('#gvpm-bd');bd.style.display=r.items.length?'':'none';bd.textContent=fa(r.items.length);
  if(statsOnly&&!$('#pane-backups').classList.contains('on'))return;
  if(!r.items.length){$('#bk-list').innerHTML='<div class="gvpm-empty">🎉 بکاپی نگهداری نمی‌شود.</div>';return}
  $('#bk-list').innerHTML=r.items.map(function(b){
    var isDel=b.type==='delete';
    var title=isDel?('حذف '+fa(b.count)+' محصول ('+(b.mode==='permanent'?'دائمی':'زباله‌دان')+')'):('به‌روزرسانی '+fa(b.count)+' محصول');
    return '<div class="gvpm-bk" data-id="'+b.id+'"><div class="i">'+(isDel?'🗑️':'✏️')+'</div><div class="m"><b>'+esc(title)+'</b><small>'+esc(b.created)+' · '+esc(b.size)+(b.note?' · '+esc(b.note):'')+'</small>'+(b.restored?'<small style="color:#15803d">✔ آخرین بازگردانی: '+esc(b.restored)+'</small>':'')+'</div>'
    +'<div class="gvpm-row"><button class="gvpm-btn sm ok" data-a="restore">↩️ بازگردانی</button>'
    +(isDel&&b.mode==='trash'?'<button class="gvpm-btn sm wr" data-a="finalize" title="محصولات داخل زباله‌دان برای همیشه پاک می‌شوند و بکاپ حذف می‌شود">حذف نهایی + پاک‌کردن بکاپ</button>':'')
    +'<a class="gvpm-btn sm g" href="'+C.post+'?action=gv_pm_backup_download&backup='+b.id+'&_wpnonce='+C.nonce+'">⬇️ دانلود</a>'
    +'<button class="gvpm-btn sm er" data-a="remove">پاک‌کردن بکاپ</button></div></div>';
  }).join('');
}
$('#bk-list').addEventListener('click',function(e){
  var b=e.target.closest('button[data-a]');if(!b)return;
  var id=b.closest('.gvpm-bk').dataset.id,a=b.dataset.a;
  if(a==='remove'){
    if(!confirm('این بکاپ پاک شود؟ پس از آن دیگر نمی‌توانید به این وضعیت برگردید.'))return;
    api('backup_delete',{backup:id}).then(renderBackups).catch(function(e){alert(e.message)});
  } else if(a==='restore'){
    if(!confirm('محصولات به وضعیت قبل از آن عملیات برگردند؟'))return;
    runLoop('restore_run',id,'در حال بازگردانی…',function(tot,r){tot.r=tot.r||{restored:0,exists:0,missing:0,errors:0};Object.keys(r.r).forEach(function(k){tot.r[k]+=r.r[k]});tot.errs=(tot.errs||[]).concat(r.errs||[])},function(tot){
      var r=tot.r;msg($('#bk-res'),r.errors?'wr':'ok','✅ '+fa(r.restored)+' محصول بازگردانی شد'+(r.exists?' — '+fa(r.exists)+' محصول از قبل در سایت بود':'')+(r.missing?' — '+fa(r.missing)+' محصول دیگر در سایت نبود':'')+(r.errors?' — '+fa(r.errors)+' خطا:<br>'+tot.errs.map(esc).join('<br>'):'')+'<br>اگر نتیجه درست است می‌توانید بکاپ را پاک کنید.');
      loadList(page);
    });
  } else if(a==='finalize'){
    if(!confirm('محصولاتِ داخل زباله‌دان برای همیشه پاک می‌شوند و بکاپ هم حذف می‌شود. ادامه می‌دهید؟'))return;
    runLoop('finalize_run',id,'در حال حذف نهایی…',function(tot,r){tot.p=(tot.p||0)+r.purged},function(tot){msg($('#bk-res'),'ok','✅ '+fa(tot.p||0)+' محصول برای همیشه حذف شد و بکاپ پاک شد.');loadBackups();loadList(page)});
  }
});
function runLoop(action,id,label,acc,fin){
  $('#bk-res').innerHTML='';prog('bk',true,label,2);var tot={},pos=0;
  function step(){
    api(action,{backup:id,pos:pos}).then(function(r){
      acc(tot,r);pos=r.pos;prog('bk',true,label,r.size?Math.min(99,Math.round(r.pos/r.size*100)):50);
      if(!r.done)return step();
      prog('bk',false);fin(tot);refreshBackupStats();
      if(action==='restore_run')loadBackups();
    }).catch(function(e){prog('bk',false);msg($('#bk-res'),'er',esc(e.message))});
  }
  step();
}
$('#bk-refresh').addEventListener('click',loadBackups);
$('#bk-del-all').addEventListener('click',function(){
  if(!confirm('همه‌ی بکاپ‌ها پاک شوند؟ این کار برگشت‌پذیر نیست.'))return;
  api('backup_delete',{all:1}).then(renderBackups).catch(function(e){alert(e.message)});
});

loadList(1); refreshBackupStats();
})();
</script>
</div>
<?php
}