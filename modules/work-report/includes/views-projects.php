<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پروژه‌ها: کارفرما، وضعیت، پیشرفت، اعضا و (فقط برای مدیر کل وردپرس) درآمد، هزینه‌ی نیرو و سود.
 */

function gvwr_is_super() {
	return current_user_can( 'manage_options' );
}

function gvwr_status_badge( $map, $key ) {
	$i = isset( $map[ $key ] ) ? $map[ $key ] : array( 'label' => $key, 'color' => '#64748b' );
	return '<span class="gvwr-badge" style="background:' . esc_attr( $i['color'] ) . '1a;color:' . esc_attr( $i['color'] ) . '">' . esc_html( ( isset( $i['icon'] ) ? $i['icon'] . ' ' : '' ) . $i['label'] ) . '</span>';
}

function gvwr_client_label( $p ) {
	$cu = (int) $p->client_user_id;
	if ( $cu > 0 ) {
		$u = get_userdata( $cu );
		return $u ? $u->display_name : 'کاربر حذف‌شده';
	}
	return $cu < 0 ? 'داخلی (بدون دسترسی کارفرما)' : 'همه‌ی کارفرمایان';
}

function gvwr_view_projects( $st ) {
	global $wpdb;
	$super = gvwr_is_super();
	$rows  = gvwr_projects();
	$mem   = array();
	foreach ( (array) $wpdb->get_results( 'SELECT project_id, COUNT(*) n FROM ' . gvwr_table( 'project_members' ) . ' GROUP BY project_id' ) as $r ) {
		$mem[ (int) $r->project_id ] = (int) $r->n;
	}
	$mins = array();
	foreach ( (array) $wpdb->get_results( 'SELECT project_id, COALESCE(SUM(duration_min),0) m, COUNT(*) n FROM ' . gvwr_table( 'items' ) . ' GROUP BY project_id' ) as $r ) {
		$mins[ (int) $r->project_id ] = array( (int) $r->m, (int) $r->n );
	}
	$sum_inc = 0;
	$sum_lab = 0;
	ob_start();
	?>
	<div class="gvwr-card">
		<div class="gvwr-cardhead"><h3>پروژه‌ها</h3><button type="button" class="gvwr-btn primary sm" data-act="proj-edit" data-id="0">+ پروژه‌ی جدید</button></div>
		<div class="gvwr-scroll">
			<table class="gvwr-table">
				<thead><tr><th>پروژه</th><th>کارفرما (دسترسی)</th><th>وضعیت</th><th>سلامت</th><th>پیشرفت</th><th>بازه</th><th>اعضا</th><th>گزارش‌ها</th><th>ساعت</th><?php if ( $super ) : ?><th>درآمد</th><th>هزینه‌ی نیرو</th><th>سود</th><?php endif; ?><th></th></tr></thead>
				<tbody>
				<?php
				foreach ( $rows as $p ) :
					$inc = $super ? gvwr_project_income_total( $p->id ) : 0;
					$lab = $super ? gvwr_project_labor( $p->id ) : array( 'cost' => 0 );
					$sum_inc += $inc;
					$sum_lab += $lab['cost'];
					$m = isset( $mins[ $p->id ] ) ? $mins[ $p->id ] : array( 0, 0 );
					?>
					<tr>
						<td><b><?php echo esc_html( $p->name ); ?></b><?php echo $p->is_default ? ' <span class="gvwr-muted">(پیش‌فرض)</span>' : ''; // phpcs:ignore ?><?php echo $p->client_name ? '<br><span class="gvwr-muted">' . esc_html( $p->client_name ) . '</span>' : ''; // phpcs:ignore ?></td>
						<td><?php echo esc_html( gvwr_client_label( $p ) ); ?></td>
						<td><?php echo gvwr_status_badge( gvwr_project_statuses(), $p->status ); // phpcs:ignore ?></td>
						<td><?php echo gvwr_status_badge( gvwr_project_healths(), $p->health ); // phpcs:ignore ?></td>
						<td><div class="gvwr-bar" title="<?php echo esc_attr( (int) $p->progress ); ?>٪"><i style="width:<?php echo (int) $p->progress; ?>%"></i></div><span class="gvwr-muted"><?php echo esc_html( gvwr_fa( (int) $p->progress ) ); ?>٪</span></td>
						<td><?php echo esc_html( $p->start_date || $p->end_date ? gvwr_date( $p->start_date, 'short' ) . ' ← ' . gvwr_date( $p->end_date, 'short' ) : '—' ); ?></td>
						<td><?php echo esc_html( gvwr_n( isset( $mem[ $p->id ] ) ? $mem[ $p->id ] : 0 ) ); ?></td>
						<td><?php echo esc_html( gvwr_n( $m[1] ) ); ?></td>
						<td dir="ltr"><?php echo esc_html( gvwr_minutes( $m[0] ) ); ?></td>
						<?php if ( $super ) : ?>
							<td><?php echo esc_html( gvwr_n( $inc ) ); ?></td>
							<td><?php echo esc_html( gvwr_n( $lab['cost'] ) ); ?></td>
							<td class="<?php echo $inc - $lab['cost'] >= 0 ? 'gvwr-up' : 'gvwr-down'; ?>"><b><?php echo esc_html( gvwr_n( $inc - $lab['cost'] ) ); ?></b></td>
						<?php endif; ?>
						<td class="gvwr-acts">
							<button type="button" class="gvwr-ib" data-act="proj-edit" data-id="<?php echo (int) $p->id; ?>" title="ویرایش">✎</button>
							<?php if ( ! $p->is_default ) : ?><button type="button" class="gvwr-ib no" data-act="proj-del" data-id="<?php echo (int) $p->id; ?>" title="حذف">🗑</button><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $super ) : ?>
			<p class="gvwr-muted">جمع درآمد: <b><?php echo esc_html( gvwr_n( $sum_inc ) ); ?></b> · هزینه‌ی نیرو: <b><?php echo esc_html( gvwr_n( $sum_lab ) ); ?></b> · سود: <b><?php echo esc_html( gvwr_n( $sum_inc - $sum_lab ) ); ?></b> تومان. هزینه‌ی نیرو = ساعت‌های ثبت‌شده × نرخ ساعتی هر کارمند. این ستون‌ها فقط برای مدیر کل وردپرس دیده می‌شود.</p>
		<?php endif; ?>
	</div>
	<?php
	return array( 'html' => ob_get_clean(), 'charts' => array() );
}

/** فرم ایجاد/ویرایش پروژه (پنجره) */
function gvwr_project_form_html( $id ) {
	$p       = $id ? gvwr_project( $id ) : null;
	$super   = gvwr_is_super();
	$members = $p ? gvwr_project_members( $p->id ) : array();
	$val     = function ( $k, $d = '' ) use ( $p ) {
		return $p && isset( $p->$k ) && null !== $p->$k ? $p->$k : $d;
	};
	$users = get_users( array( 'orderby' => 'display_name', 'number' => 500, 'fields' => array( 'ID', 'display_name', 'user_login' ) ) );
	$cu    = (int) $val( 'client_user_id', 0 );
	$h     = '<h3>' . ( $p ? 'ویرایش پروژه' : 'پروژه‌ی جدید' ) . '</h3><div id="gvwr-projform" data-id="' . (int) $id . '"><div class="gvwr-form">';
	$h    .= '<label>نام پروژه<input type="text" data-j="name" value="' . esc_attr( $val( 'name' ) ) . '"></label>';
	$h    .= '<label>نام مشتری<input type="text" data-j="client_name" value="' . esc_attr( $val( 'client_name' ) ) . '"></label>';
	$h    .= '<label>کارفرما (چه کسی گزارش را می‌بیند)<select data-j="client_user_id"><option value="0"' . selected( $cu, 0, false ) . '>همه‌ی کاربران با نقش کارفرما</option><option value="-1"' . selected( $cu, -1, false ) . '>هیچ‌کس (فقط داخلی)</option>';
	foreach ( $users as $u ) {
		$h .= '<option value="' . (int) $u->ID . '"' . selected( $cu, (int) $u->ID, false ) . '>' . esc_html( $u->display_name . ' (' . $u->user_login . ')' ) . '</option>';
	}
	$h .= '</select></label>';
	$h .= '<label>مدیر پروژه<select data-j="manager_user_id"><option value="0">—</option>';
	foreach ( $users as $u ) {
		$h .= '<option value="' . (int) $u->ID . '"' . selected( (int) $val( 'manager_user_id', 0 ), (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
	}
	$h .= '</select></label>';
	foreach ( array( 'status' => array( 'وضعیت', gvwr_project_statuses() ), 'priority' => array( 'اولویت', gvwr_project_priorities() ), 'health' => array( 'سلامت پروژه', gvwr_project_healths() ) ) as $k => $def ) {
		$h .= '<label>' . esc_html( $def[0] ) . '<select data-j="' . $k . '">';
		foreach ( $def[1] as $key => $i ) {
			$h .= '<option value="' . esc_attr( $key ) . '"' . selected( $val( $k, 'status' === $k ? 'active' : ( 'priority' === $k ? 'normal' : 'green' ) ), $key, false ) . '>' . esc_html( $i['label'] ) . '</option>';
		}
		$h .= '</select></label>';
	}
	$h .= '<label>پیشرفت (٪)<input type="number" min="0" max="100" data-j="progress" value="' . (int) $val( 'progress', 0 ) . '"></label>';
	$h .= '<label>تاریخ شروع<input type="date" data-j="start_date" value="' . esc_attr( $val( 'start_date' ) ) . '"></label>';
	$h .= '<label>تاریخ پایان<input type="date" data-j="end_date" value="' . esc_attr( $val( 'end_date' ) ) . '"></label>';
	$h .= '</div><label class="gvwr-block">توضیحات<textarea data-j="description" rows="3" class="gvwr-full">' . esc_textarea( $val( 'description' ) ) . '</textarea></label>';

	$h .= '<h4 class="gvwr-h4">اعضای پروژه</h4><p class="gvwr-muted">اگر هیچ عضوی انتخاب نشود، همه‌ی کارمندان می‌توانند برای این پروژه گزارش ثبت کنند.</p><div class="gvwr-members">';
	foreach ( gvwr_employees( true ) as $e ) {
		$on = isset( $members[ (int) $e->user_id ] );
		$h .= '<div class="gvwr-mrow" data-uid="' . (int) $e->user_id . '"><label class="gvwr-chk"><input type="checkbox" data-m="on"' . checked( $on, true, false ) . '> ' . esc_html( $e->display_name ) . '</label><input type="text" data-m="role" placeholder="نقش در پروژه" value="' . esc_attr( $on ? $members[ (int) $e->user_id ] : '' ) . '"></div>';
	}
	$h .= '</div><div class="gvwr-formfoot"><button type="button" class="gvwr-btn primary" data-act="proj-save">ذخیره‌ی پروژه</button></div></div>';

	if ( $p && $super ) {
		global $wpdb;
		$inc = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . gvwr_table( 'project_income' ) . ' WHERE project_id=%d ORDER BY income_date DESC, id DESC', $p->id ) );
		$lab = gvwr_project_labor( $p->id );
		$tot = gvwr_project_income_total( $p->id );
		$h  .= '<h4 class="gvwr-h4">درآمد و سود <span class="gvwr-muted">(فقط مدیر کل)</span></h4><div class="gvwr-kpis gvwr-kpis-sm">' . gvwr_kpi( 'کل درآمد', gvwr_n( $tot ) ) . gvwr_kpi( 'هزینه‌ی نیرو', gvwr_n( $lab['cost'] ), gvwr_minutes( $lab['minutes'] ) . ' ساعت' ) . gvwr_kpi( 'سود خالص', gvwr_n( $tot - $lab['cost'] ), 'تومان', $tot - $lab['cost'] >= 0 ? 'good' : 'warn' ) . '</div>';
		$h  .= '<div class="gvwr-scroll"><table class="gvwr-table"><thead><tr><th>تاریخ</th><th>مبلغ (تومان)</th><th>توضیح</th><th></th></tr></thead><tbody>';
		foreach ( $inc as $r ) {
			$h .= '<tr><td>' . esc_html( gvwr_date( $r->income_date ) ) . '</td><td>' . esc_html( gvwr_n( $r->amount ) ) . '</td><td>' . esc_html( $r->note ) . '</td><td><button type="button" class="gvwr-ib no" data-act="income-del" data-id="' . (int) $r->id . '" data-project="' . (int) $p->id . '">🗑</button></td></tr>';
		}
		$h .= '</tbody></table></div><div class="gvwr-kwadd" id="gvwr-incform" data-project="' . (int) $p->id . '"><input type="date" data-i="date" value="' . esc_attr( gvwr_today() ) . '"><input type="number" data-i="amount" min="1" placeholder="مبلغ (تومان)"><input type="text" data-i="note" placeholder="توضیح (اختیاری)"><button type="button" class="gvwr-btn ghost" data-act="income-add">افزودن درآمد</button></div>';
	}
	return $h;
}

function gvwr_save_project( $d ) {
	global $wpdb;
	$id   = isset( $d['id'] ) ? (int) $d['id'] : 0;
	$name = isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : '';
	if ( '' === $name ) {
		return new WP_Error( 'name', 'نام پروژه را بنویسید.' );
	}
	$date = function ( $v ) {
		return gvwr_valid_date( $v ) ? $v : null;
	};
	$pick = function ( $v, $map, $def ) {
		return isset( $map[ $v ] ) ? $v : $def;
	};
	$cu = isset( $d['client_user_id'] ) ? (int) $d['client_user_id'] : 0;
	if ( $cu > 0 ) {
		if ( ! get_userdata( $cu ) ) {
			return new WP_Error( 'client', 'کاربر کارفرما پیدا نشد.' );
		}
		$u = get_userdata( $cu );
		if ( ! array_intersect( array( 'administrator', 'gvwr_manager', 'gvwr_employee' ), (array) $u->roles ) && ! in_array( 'gvwr_client', (array) $u->roles, true ) ) {
			$u->add_role( 'gvwr_client' ); // کاربر انتخاب‌شده به‌عنوان کارفرما دسترسی دیدن گزارش می‌گیرد
		}
	} elseif ( $cu < 0 ) {
		$cu = -1;
	}
	$row = array(
		'name'            => mb_substr( $name, 0, 190 ),
		'client_name'     => mb_substr( isset( $d['client_name'] ) ? sanitize_text_field( $d['client_name'] ) : '', 0, 190 ),
		'client_user_id'  => $cu,
		'manager_user_id' => isset( $d['manager_user_id'] ) ? max( 0, (int) $d['manager_user_id'] ) : 0,
		'status'          => $pick( isset( $d['status'] ) ? $d['status'] : '', gvwr_project_statuses(), 'active' ),
		'priority'        => $pick( isset( $d['priority'] ) ? $d['priority'] : '', gvwr_project_priorities(), 'normal' ),
		'health'          => $pick( isset( $d['health'] ) ? $d['health'] : '', gvwr_project_healths(), 'green' ),
		'progress'        => max( 0, min( 100, isset( $d['progress'] ) ? (int) $d['progress'] : 0 ) ),
		'start_date'      => $date( isset( $d['start_date'] ) ? $d['start_date'] : '' ),
		'end_date'        => $date( isset( $d['end_date'] ) ? $d['end_date'] : '' ),
		'description'     => wp_kses_post( isset( $d['description'] ) ? $d['description'] : '' ),
	);
	$T = gvwr_table( 'projects' );
	if ( $id ) {
		if ( ! gvwr_project( $id ) ) {
			return new WP_Error( 'nf', 'پروژه پیدا نشد.' );
		}
		$wpdb->update( $T, $row, array( 'id' => $id ) );
	} else {
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $T, $row );
		$id = (int) $wpdb->insert_id;
	}
	// اعضا
	$M = gvwr_table( 'project_members' );
	$wpdb->delete( $M, array( 'project_id' => $id ) );
	foreach ( isset( $d['members'] ) ? (array) $d['members'] : array() as $m ) {
		$uid = isset( $m['user'] ) ? (int) $m['user'] : 0;
		if ( $uid > 0 ) {
			$wpdb->insert( $M, array( 'project_id' => $id, 'user_id' => $uid, 'role_label' => mb_substr( isset( $m['role'] ) ? sanitize_text_field( $m['role'] ) : '', 0, 120 ) ) );
		}
	}
	return $id;
}
