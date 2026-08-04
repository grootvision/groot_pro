<?php
/**
 * ==========================================================================
 *  Groot Vision SEO Reports — پچ UX (تب‌های جدید + کارت‌های کولاپس‌شونده)
 * --------------------------------------------------------------------------
 *  این فایل ۲ کار می‌کند:
 *
 *  الف) تب‌های بالای صفحه (📋 گزارش‌های مشتری / 🕒 کارکرد من / 👥 مدیریت تیم /
 *       🗂️ پروژه‌ها) را با یک استایل «سگمنت کنترل» تمیزتر و جمع‌وجورتر نشان
 *       می‌دهد — بدون نیاز به تغییر کد HTML تب‌ها.
 *
 *  ب) هر کارتی که چند بخش/ردیف دارد (مثل «خلاصه گزارش»، «کلمات کلیدی»،
 *       «ریز فعالیت‌ها»، «رشد صفحات») را به‌صورت خودکار بسته (کولاپس‌شده)
 *       نمایش می‌دهد؛ با کلیک روی عنوانش باز می‌شود. کارت‌هایی که داخلشان
 *       فرم فعال هست (مثل فرم ثبت گزارش یا فرم افزودن کارمند) دست‌نخورده و
 *       همیشه باز می‌مانند — چون بازکردن اجباری‌شان با هر کلیک آزاردهنده
 *       می‌شود.
 *
 *  نحوه نصب (۲ قدم، هیچ‌کدام دیتابیس یا فرم‌ها را دست نمی‌زند):
 *
 *  ۱) این فایل را کنار فایل اصلی و پچ قبلی (gv-seo-restyle-patch.php) ذخیره
 *     کن، با نام gv-seo-ux-patch.php ، و در فایل اصلی، زیر خط
 *         require_once __DIR__ . '/gv-seo-restyle-patch.php';
 *     این خط را هم اضافه کن:
 *         require_once __DIR__ . '/gv-seo-ux-patch.php';
 *
 *  ۲) در تابع gv_sr_render_admin_page() فایل اصلی، دقیقاً بعد از خط
 *         gv_sr_render_top_bar();
 *     این خط را اضافه کن:
 *         gv_sr_render_ux_assets();
 *
 *  اگر کارتی هست که هرگز نباید کولاپس بشود (مثلاً یک باکس مهم که باید همیشه
 *  باز بماند)، کافیست کلاس gvsr-no-collapse را به همان div اضافه کنی —
 *  مثلاً: <div class="gvsr-report-card gvsr-no-collapse">
 * ==========================================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function gv_sr_render_ux_assets() {
	?>
	<style>
	/* ======================================================================
	   الف) تب‌های بالای صفحه — سگمنت کنترل تمیزتر (روی همون HTML قبلی سوار می‌شه)
	   ====================================================================== */
	.gvsr-maintabs{
		background:#eef0f3!important; border:0!important; padding:5px!important;
		border-radius:14px!important; gap:3px!important; box-shadow:inset 0 1px 2px rgba(17,24,39,.04)!important;
	}
	.gvsr-maintab{
		padding:9px 18px!important; border-radius:10px!important; font-size:12.6px!important;
		color:#64748b!important; transition:background .15s ease,color .15s ease,box-shadow .15s ease!important;
	}
	.gvsr-maintab.is-active{
		background:#ffffff!important; color:#111827!important; font-weight:800!important;
		box-shadow:0 3px 10px rgba(17,24,39,.10)!important;
	}
	.gvsr-maintab:not(.is-active):hover{ background:rgba(255,255,255,.65)!important; color:#111827!important; }

	/* ======================================================================
	   ب) کارت‌های خودکار-کولاپس‌شونده
	   ====================================================================== */
	.gvsr-auto-collapsible > .gvsr-collapse-head{
		cursor:pointer; user-select:none; display:flex; align-items:center; gap:6px;
	}
	.gvsr-auto-collapsible > .gvsr-collapse-head .gvsr-collapse-arrow{
		margin-inline-start:auto; display:inline-flex; color:#9ca3af; font-size:15px;
		transition:transform .18s ease; flex:0 0 auto;
	}
	.gvsr-auto-collapsible.is-open > .gvsr-collapse-head .gvsr-collapse-arrow{ transform:rotate(90deg); }
	.gvsr-collapse-body{
		display:grid; grid-template-rows:0fr; transition:grid-template-rows .22s ease;
	}
	.gvsr-collapse-body > div{ overflow:hidden; }
	.gvsr-auto-collapsible.is-open .gvsr-collapse-body{ grid-template-rows:1fr; }
	.gvsr-auto-collapsible:not(.is-open) .gvsr-collapse-body > div{ padding-top:0; }
	.gvsr-collapse-body > div{ padding-top:14px; }
	</style>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var cards = document.querySelectorAll('.gvsr-report-card, .gvsr-box');
		cards.forEach(function (card) {
			if (card.dataset.gvsrCollapseInit) { return; }
			if (card.classList.contains('gvsr-no-collapse')) { return; }
			if (card.querySelector(':scope > form, :scope form')) { return; } // فرم فعال داخلش هست، دست نمی‌زنیم
			var heading = card.querySelector(':scope > h2, :scope > h3');
			if (!heading) { return; }

			card.dataset.gvsrCollapseInit = '1';

			var body = document.createElement('div');
			body.className = 'gvsr-collapse-body';
			var inner = document.createElement('div');
			body.appendChild(inner);

			var node = heading.nextSibling;
			while (node) {
				var next = node.nextSibling;
				inner.appendChild(node);
				node = next;
			}
			card.appendChild(body);

			heading.classList.add('gvsr-collapse-head');
			var arrow = document.createElement('span');
			arrow.className = 'gvsr-collapse-arrow';
			arrow.textContent = '›';
			heading.appendChild(arrow);

			card.classList.add('gvsr-auto-collapsible');
			heading.addEventListener('click', function () {
				card.classList.toggle('is-open');
			});
		});
	});
	</script>
	<?php
}