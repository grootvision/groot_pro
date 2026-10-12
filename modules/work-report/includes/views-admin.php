<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* ۸) کارمندان و دسترسی‌ها                                             */
/* ------------------------------------------------------------------ */

function gvwr_view_people( $st ) {
	global $wpdb;
	$labels = gvwr_role_labels();
	$users  = get_users( array( 'role__in' => array_keys( $labels ), 'orderby' => 'display_name' ) );
	$titles = array();
	$act    = array();
	foreach ( gvwr_employees() as $e ) {
		$titles[ (int) $e->user_id ] = $e->job_title;
		$act[ (int) $e->user_id ]    = (int) $e->active;
	}
	$free = get_users( array( 'role__not_in' => array_merge( array_keys( $labels ), array( 'administrator' ) ), 'orderby' => 'display_name', 'number' => 300 ) );
	ob_start();
	?>
	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>افراد و دسترسی‌ها</h3><span class="gvwr-muted">هر نفر با یوزر وردپرس خودش وارد می‌شود.</span></div>
		<?php if ( $users ) : ?>
			<div class="gvwr-scroll">
				<table class="gvwr-table">
					<thead><tr><th>نام</th><th>نام کاربری</th><th>نقش</th><th>سمت (کارمند)</th><th>وضعیت</th><th></th></tr></thead>
					<tbody>
					<?php
					foreach ( $users as $u ) :
						$role = gvwr_user_role( $u );
						$emp  = 'gvwr_employee' === $role;
						?>
						<tr data-uid="<?php echo (int) $u->ID; ?>">
							<td><b><?php echo esc_html( $u->display_name ); ?></b><br><span class="gvwr-muted" dir="ltr"><?php echo esc_html( $u->user_email ); ?></span></td>
							<td dir="ltr"><?php echo esc_html( $u->user_login ); ?></td>
							<td><span class="gvwr-badge role-<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $labels[ $role ] ); ?></span></td>
							<td><?php if ( $emp ) : ?><input type="text" class="gvwr-title-in" value="<?php echo esc_attr( isset( $titles[ $u->ID ] ) ? $titles[ $u->ID ] : '' ); ?>" placeholder="مثلاً طراح سایت"><button type="button" class="gvwr-btn ghost sm" data-act="emp-title">ذخیره</button><?php else : ?>—<?php endif; ?></td>
							<td><?php if ( $emp ) : ?><label class="gvwr-switch"><input type="checkbox" data-act="emp-active" <?php checked( ! isset( $act[ $u->ID ] ) || $act[ $u->ID ] ); ?>><i></i><span>فعال</span></label><?php else : ?>—<?php endif; ?></td>
							<td><button type="button" class="gvwr-ib no" data-act="person-remove" title="حذف دسترسی گزارش‌کار (یوزر وردپرس حذف نمی‌شود)">🗑</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<?php echo gvwr_empty( 'هنوز کسی تعریف نشده است. از فرم پایین شروع کنید.' ); // phpcs:ignore ?>
		<?php endif; ?>
	</div>

	<div class="gvwr-card" id="gvwr-addperson">
		<div class="gvwr-cardhead"><h3>افزودن فرد</h3></div>
		<div class="gvwr-form">
			<label>نقش
				<select data-p="role">
					<option value="gvwr_employee">کارمند (ثبت گزارش)</option>
					<option value="gvwr_manager">مدیر گزارش کار (مدیر شرکت)</option>
					<option value="gvwr_client">کارفرما (فقط مشاهده‌ی گزارش)</option>
				</select>
			</label>
			<label>سمت (برای کارمند)<input type="text" data-p="title" placeholder="طراح سایت / سئوکار"></label>
			<label>اتصال به یوزر موجود
				<select data-p="user"><option value="0">— یک کاربر انتخاب کنید —</option>
					<?php foreach ( $free as $u ) : ?><option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html( $u->display_name . ' (' . $u->user_login . ')' ); ?></option><?php endforeach; ?>
				</select>
			</label>
		</div>
		<p class="gvwr-muted" style="margin:14px 0 6px"><b>یا یوزر جدید بسازید</b> (لینک تعیین رمز به ایمیل او ارسال می‌شود)</p>
		<div class="gvwr-form">
			<label>نام نمایشی<input type="text" data-p="name"></label>
			<label>نام کاربری<input type="text" data-p="login" dir="ltr"></label>
			<label>ایمیل<input type="email" data-p="email" dir="ltr"></label>
		</div>
		<div class="gvwr-formfoot"><button type="button" class="gvwr-btn primary" data-act="person-add">افزودن</button></div>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}

/* ------------------------------------------------------------------ */
/* ۹) دسته‌بندی‌ها                                                     */
/* ------------------------------------------------------------------ */

function gvwr_view_categories( $st ) {
	$cats = gvwr_categories( false );
	$cnt  = array();
	global $wpdb;
	foreach ( (array) $wpdb->get_results( 'SELECT category_id, COUNT(*) n FROM ' . gvwr_table( 'items' ) . ' GROUP BY category_id' ) as $r ) {
		$cnt[ (int) $r->category_id ] = (int) $r->n;
	}
	ob_start();
	?>
	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>دسته‌بندی کارها</h3><span class="gvwr-muted">کارمندان این فهرست را در فرم گزارش می‌بینند. «گروه» برای مقایسه و گزارش‌ها استفاده می‌شود.</span></div>
		<div class="gvwr-scroll">
			<table class="gvwr-table gvwr-cattable">
				<thead><tr><th>آیکون</th><th>نام</th><th>گروه</th><th>رنگ</th><th>ترتیب</th><th>فعال</th><th>استفاده</th><th></th></tr></thead>
				<tbody>
				<?php
				foreach ( array_merge( $cats, array( (object) array( 'id' => 0, 'name' => '', 'grp' => '', 'icon' => '', 'color' => '#0f766e', 'sort_order' => count( $cats ), 'active' => 1 ) ) ) as $c ) :
					?>
					<tr data-id="<?php echo (int) $c->id; ?>">
						<td><input type="text" data-c="icon" value="<?php echo esc_attr( $c->icon ); ?>" maxlength="4" class="gvwr-w-icon"></td>
						<td><input type="text" data-c="name" value="<?php echo esc_attr( $c->name ); ?>" placeholder="<?php echo $c->id ? '' : 'دسته‌بندی جدید…'; ?>"></td>
						<td><input type="text" data-c="grp" value="<?php echo esc_attr( $c->grp ); ?>" list="gvwr-grps" class="gvwr-w-grp"></td>
						<td><input type="color" data-c="color" value="<?php echo esc_attr( $c->color ); ?>"></td>
						<td><input type="number" data-c="sort" value="<?php echo (int) $c->sort_order; ?>" class="gvwr-w-num"></td>
						<td><input type="checkbox" data-c="active" <?php checked( (int) $c->active, 1 ); ?>></td>
						<td><?php echo $c->id ? esc_html( gvwr_fa( isset( $cnt[ $c->id ] ) ? $cnt[ $c->id ] : 0 ) ) : ''; ?></td>
						<td class="gvwr-acts">
							<button type="button" class="gvwr-btn <?php echo $c->id ? 'ghost' : 'primary'; ?> sm" data-act="cat-save"><?php echo $c->id ? 'ذخیره' : 'افزودن'; ?></button>
							<?php if ( $c->id ) : ?><button type="button" class="gvwr-ib no" data-act="cat-del" title="حذف">🗑</button><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<datalist id="gvwr-grps"><?php foreach ( array( 'محتوا', 'سئو', 'طراحی', 'فنی', 'سایر' ) as $g ) : ?><option value="<?php echo esc_attr( $g ); ?>"><?php endforeach; ?></datalist>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}

/* ------------------------------------------------------------------ */
/* ۱۰) تنظیمات                                                         */
/* ------------------------------------------------------------------ */

function gvwr_view_settings( $st ) {
	$o   = gvwr_opt();
	$pid = (int) $o['client_page_id'];
	$url = $pid ? get_permalink( $pid ) : '';
	$sw  = function ( $key, $label, $hint = '' ) use ( $o ) {
		return '<label class="gvwr-switch row"><input type="checkbox" data-s="' . esc_attr( $key ) . '" ' . checked( ! empty( $o[ $key ] ), true, false ) . '><i></i><span><b>' . esc_html( $label ) . '</b>' . ( $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</span></label>';
	};
	ob_start();
	?>
	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>برگه‌ی کارفرما</h3></div>
		<p>این شورت‌کد را در هر برگه بگذارید تا کارفرما (کاربر با نقش «کارفرما») گزارش را ببیند:</p>
		<div class="gvwr-copy"><code dir="ltr" id="gvwr-sc">[groot_work_report]</code><button type="button" class="gvwr-btn ghost sm" data-act="copy" data-target="gvwr-sc">کپی</button></div>
		<?php if ( $url ) : ?>
			<p>برگه‌ی آماده: <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $url ); ?></a></p>
		<?php else : ?>
			<p><button type="button" class="gvwr-btn primary sm" data-act="make-page">ساخت برگه‌ی کارفرما</button></p>
		<?php endif; ?>
	</div>

	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>تنظیمات عمومی</h3></div>
		<div class="gvwr-form">
			<label>تقویم نمایش
				<select data-s="calendar"><option value="jalali" <?php selected( $o['calendar'], 'jalali' ); ?>>شمسی</option><option value="gregorian" <?php selected( $o['calendar'], 'gregorian' ); ?>>میلادی</option></select>
			</label>
			<label>عنوان گزارش کارفرما<input type="text" data-s="client_title" value="<?php echo esc_attr( $o['client_title'] ); ?>"></label>
			<label>نام شرکت شما (در سربرگ گزارش)<input type="text" data-s="company_name" value="<?php echo esc_attr( $o['company_name'] ); ?>"></label>
			<label>ایمیل‌های اعلان (با کاما جدا کنید)<input type="text" data-s="notify_emails" dir="ltr" value="<?php echo esc_attr( $o['notify_emails'] ); ?>"></label>
		</div>
		<div class="gvwr-switches">
			<?php
			echo $sw( 'require_approval', 'کارفرما فقط گزارش‌های تأییدشده را ببیند', 'پیشنهاد می‌شود روشن باشد.' ); // phpcs:ignore
			echo $sw( 'auto_approve', 'تأیید خودکار گزارش‌ها', 'گزارش‌ها بدون بررسی مدیر تأیید می‌شوند.' ); // phpcs:ignore
			echo $sw( 'allow_edit_approved', 'کارمند بتواند گزارش تأییدشده را ویرایش کند', 'بعد از ویرایش دوباره به صف تأیید می‌رود.' ); // phpcs:ignore
			echo $sw( 'email_notify', 'ایمیل به مدیر هنگام ثبت گزارش جدید' ); // phpcs:ignore
			echo $sw( 'employees_add_keywords', 'کارمند بتواند کلمه‌ی کلیدی اضافه کند', 'اگر خاموش باشد فقط مدیر فهرست کلمات را می‌سازد.' ); // phpcs:ignore
			echo $sw( 'client_show_shots', 'نمایش اسکرین‌شات‌ها به کارفرما' ); // phpcs:ignore
			echo $sw( 'client_show_keywords', 'نمایش رتبه‌ی کلمات کلیدی به کارفرما' ); // phpcs:ignore
			echo $sw( 'login_redirect', 'بعد از ورود، مستقیم به صفحه‌ی گزارش بروند' ); // phpcs:ignore
			?>
		</div>
		<div class="gvwr-formfoot"><button type="button" class="gvwr-btn primary" data-act="settings-save">ذخیره‌ی تنظیمات</button></div>
	</div>

	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>پروژه / سایت</h3><span class="gvwr-muted">پیش‌فرض، نام همین سایت است. فقط اگر یک نصب چند سایت را پوشش می‌دهد پروژه‌ی دیگری اضافه کنید.</span></div>
		<?php foreach ( gvwr_projects() as $p ) : ?>
			<div class="gvwr-projrow" data-id="<?php echo (int) $p->id; ?>">
				<input type="text" data-pn value="<?php echo esc_attr( $p->name ); ?>">
				<button type="button" class="gvwr-btn ghost sm" data-act="proj-save">ذخیره</button>
				<?php if ( ! $p->is_default ) : ?><button type="button" class="gvwr-ib no" data-act="proj-del">🗑</button><?php endif; ?>
			</div>
		<?php endforeach; ?>
		<div class="gvwr-projrow" data-id="0"><input type="text" data-pn placeholder="پروژه‌ی جدید…"><button type="button" class="gvwr-btn primary sm" data-act="proj-save">افزودن</button></div>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}
