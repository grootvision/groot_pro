/* Groot Vision — Access Manager | ac.js
   پنل کاملاً بدون وابستگی (Vanilla JS). بخش‌ها: ابزارها، داشبورد، نقش‌ها، کاربران، قوانین، پنهان‌سازی، گزارش‌ها، تنظیمات */
(function () {
	'use strict';
	var C = window.GVAC, root = document.getElementById('gvac-app');
	if (!C || !root) { return; }

	/* ======================= ابزارها ======================= */
	var fa = function (n) { return Number(n || 0).toLocaleString('fa-IR'); };
	var fdate = function (ts) { return ts ? new Date(ts * 1000).toLocaleString('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }) : '—'; };

	function add(e, kids) {
		kids.forEach(function f(c) {
			if (c == null || c === false) { return; }
			if (Array.isArray(c)) { c.forEach(f); return; }
			e.append(c.nodeType ? c : document.createTextNode(c));
		});
	}
	function h(tag, a) {
		var e = document.createElement(tag), kids = Array.prototype.slice.call(arguments, 2);
		if (a) {
			Object.keys(a).forEach(function (k) {
				var v = a[k];
				if (v == null || v === false) { return; }
				if (k === 'class') { e.className = v; }
				else if (k === 'html') { e.innerHTML = v; }
				else if (k.indexOf('on') === 0) { e.addEventListener(k.slice(2), v); }
				else if (k === 'value' || k === 'checked' || k === 'disabled') { e[k] = v; }
				else { e.setAttribute(k, v === true ? '' : v); }
			});
		}
		add(e, kids);
		return e;
	}

	var ICONS = {
		lock: '<path d="M17 9V7a5 5 0 0 0-10 0v2H5v12h14V9h-2zM9 7a3 3 0 0 1 6 0v2H9V7z" fill="currentColor" stroke="none"/>',
		open: '<path d="M5 12l4 4 10-10" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
		hide: '<path d="M3 3l18 18M10.6 6.1A9.7 9.7 0 0 1 12 6c5 0 8.5 4 9.5 6a12 12 0 0 1-2.6 3.4M6.3 6.8C4.3 8.2 2.9 10.2 2.5 12c1 2 4.5 6 9.5 6 1.3 0 2.5-.3 3.6-.8M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		inherit: '<path d="M12 4v12m0 0l-5-5m5 5l5-5M5 20h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		search: '<circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		plus: '<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
		chev: '<path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
		dash: '<path d="M4 20V10m6 10V4m6 16v-7m4 7H2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		roles: '<path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6l8-3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
		users: '<circle cx="9" cy="8" r="3.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M2.5 20c.6-3.5 3.2-5.5 6.5-5.5s5.9 2 6.5 5.5M16 5.2a3.5 3.5 0 0 1 0 6.6M18 14.8c2 .6 3.2 2.3 3.5 5.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		rules: '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h12M20 18h0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="16" cy="6" r="2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="8" cy="12" r="2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="18" cy="18" r="2" fill="none" stroke="currentColor" stroke-width="2"/>',
		ghost: '<path d="M5 20V10a7 7 0 0 1 14 0v10l-3-2-2 2-2-2-2 2-2-2-3 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="9.5" cy="10.5" r="1" fill="currentColor"/><circle cx="14.5" cy="10.5" r="1" fill="currentColor"/>',
		logs: '<path d="M6 3h9l4 4v14H6zM9 12h7M9 16h7M9 8h3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		gear: '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
		eye: '<path d="M2.5 12C4 8.5 7.5 6 12 6s8 2.5 9.5 6c-1.5 3.5-5 6-9.5 6s-8-2.5-9.5-6z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>',
		trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
		copy: '<rect x="8" y="8" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3" fill="none" stroke="currentColor" stroke-width="2"/>',
		clock: '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
	};
	function ico(n) {
		var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('aria-hidden', 'true');
		s.innerHTML = ICONS[n] || '';
		return s;
	}

	function toast(msg, type) {
		var box = document.getElementById('gvac-toasts'), t = h('div', { class: 'gvac-toast ' + (type || ''), role: 'status' }, msg);
		box.append(t);
		setTimeout(function () { t.remove(); }, type === 'err' ? 6500 : 3200);
	}

	function api(op, data) {
		var fd = new FormData();
		fd.append('action', 'gvac'); fd.append('nonce', C.nonce); fd.append('op', op); fd.append('data', JSON.stringify(data || {}));
		return fetch(C.ajax, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (r) { return r.json(); }).catch(function () { throw new Error('پاسخ نامعتبر از سرور.'); }).then(function (j) {
			if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || 'خطای ناشناخته'); }
			return j.data;
		});
	}
	function call(op, data, okMsg) {
		return api(op, data).then(function (r) { if (okMsg) { toast(okMsg, 'ok'); } return r; }, function (e) { toast(e.message, 'err'); return null; });
	}

	function modal(title, body, buttons, wide) {
		var bg, onKey = function (e) { if (e.key === 'Escape') { close(); } };
		function close() { document.removeEventListener('keydown', onKey); bg.remove(); }
		var foot = h('footer');
		(buttons || []).forEach(function (b) {
			foot.append(h('button', { type: 'button', class: 'gvac-btn ' + (b.cls || ''), onclick: function () { b.fn(close); } }, b.label));
		});
		bg = h('div', { class: 'gvac-modal-bg', onmousedown: function (e) { if (e.target === bg) { close(); } } },
			h('div', { class: 'gvac-modal' + (wide ? ' wide' : ''), role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
				h('header', null, h('h3', null, title), h('button', { type: 'button', class: 'gvac-x', 'aria-label': 'بستن', onclick: close }, '×')),
				h('div', { class: 'bd' }, body), (buttons && buttons.length) ? foot : null));
		document.addEventListener('keydown', onKey);
		document.body.append(bg);
		var f = bg.querySelector('input,select,textarea'); if (f) { f.focus(); }
		return { close: close, el: bg };
	}
	function confirmBox(msg, label, danger) {
		return new Promise(function (res) {
			modal('تأیید', h('p', null, msg), [
				{ label: label || 'تأیید', cls: danger ? 'danger' : 'pri', fn: function (c) { c(); res(true); } },
				{ label: 'انصراف', fn: function (c) { c(); res(false); } }
			]);
		});
	}

	function field(label, input, hint) { return h('div', { class: 'gvac-field' }, h('label', null, label), input, hint ? h('small', null, hint) : null); }
	function sw(label, hint, checked, on) {
		var i = h('input', { type: 'checkbox', checked: !!checked, onchange: function () { on(i.checked); } });
		return h('label', { class: 'gvac-sw' }, i, h('span', null, h('b', null, label), hint ? h('small', null, hint) : null));
	}
	function chip(text, cls, icon) { return h('span', { class: 'gvac-chip ' + (cls || '') }, icon ? ico(icon) : null, text); }
	function searchBox(ph, on) {
		var t, i = h('input', { class: 'gvac-in', type: 'search', placeholder: ph, oninput: function () { clearTimeout(t); t = setTimeout(function () { on(i.value.trim()); }, 280); } });
		return h('div', { class: 'gvac-search' }, ico('search'), i);
	}
	function btn(label, cls, fn, icon) { return h('button', { type: 'button', class: 'gvac-btn ' + (cls || ''), onclick: fn }, icon ? ico(icon) : null, label); }
	function pager(d, go) {
		if (!d || d.pages < 2) { return null; }
		return h('div', { class: 'gvac-pager' },
			h('button', { class: 'gvac-btn sm', type: 'button', disabled: d.page <= 1, onclick: function () { go(d.page - 1); } }, 'قبلی'),
			h('span', { class: 'gvac-muted' }, fa(d.page) + ' از ' + fa(d.pages)),
			h('button', { class: 'gvac-btn sm', type: 'button', disabled: d.page >= d.pages, onclick: function () { go(d.page + 1); } }, 'بعدی'));
	}
	function download(name, obj) {
		var a = h('a', { href: URL.createObjectURL(new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json' })), download: name });
		document.body.append(a); a.click(); a.remove();
	}

	/* ======================= وضعیت و ناوبری ======================= */
	var S = { tab: C.tab || 'dashboard', boot: null, cat: null, view: null, users: { page: 1, search: '', role: '' }, logs: { page: 1, type: '', search: '' }, sel: {}, rpick: null };
	var TABS = [['dashboard', 'داشبورد', 'dash'], ['roles', 'نقش‌ها', 'roles'], ['users', 'کاربران', 'users'], ['rules', 'قوانین دسترسی', 'rules'], ['stealth', 'پنهان‌سازی', 'ghost'], ['logs', 'گزارش‌ها', 'logs'], ['settings', 'تنظیمات', 'gear']];

	function go(tab, opts) {
		if (S.dirtyCheck && S.tab === 'rules' && tab !== 'rules' && S.dirtyCheck()) {
			confirmBox('تغییرات ذخیره‌نشده‌ی قوانین از بین می‌رود. ادامه می‌دهید؟', 'ادامه', true).then(function (ok) { if (ok) { S.edit = null; go(tab, opts); } });
			return;
		}
		S.tab = tab; S.opts = opts || {};
		try { history.replaceState(null, '', location.pathname + '?page=' + C.self + '&tab=' + tab); } catch (e) { /* ignore */ }
		render();
	}

	function reloadBoot() { return api('boot').then(function (b) { S.boot = b; return b; }); }

	function render() {
		root.innerHTML = '';
		var b = S.boot, off = !b.settings.enabled || b.settings.constant_off;
		var head = h('div', { class: 'gvac-head' }, h('h1', null, 'مدیریت سطح دسترسی'),
			off ? h('span', { class: 'gvac-pill off' }, h('i'), 'محدودیت‌ها خاموش') : h('span', { class: 'gvac-pill' }, h('i'), 'محدودیت‌ها فعال'),
			b.me.preview ? h('a', { class: 'gvac-pill pv', href: '#', onclick: function (e) { e.preventDefault(); call('preview', { stop: 1 }).then(function () { location.reload(); }); } }, 'پیش‌نمایش روشن است — خروج') : null);
		var tabs = h('nav', { class: 'gvac-tabs', role: 'tablist' });
		TABS.forEach(function (t) {
			var n = t[0] === 'roles' ? b.stats.roles : (t[0] === 'users' ? null : null);
			tabs.append(h('button', { type: 'button', role: 'tab', class: 'gvac-tab' + (S.tab === t[0] ? ' on' : ''), 'aria-selected': S.tab === t[0], onclick: function () { go(t[0]); } }, ico(t[2]), t[1], n ? h('span', { class: 'n' }, fa(n)) : null));
		});
		S.view = h('div', { class: 'gvac-view' }, h('div', { class: 'gvac-boot gvac-spin' }, 'در حال بارگذاری…'));
		root.append(head, h('p', { class: 'gvac-sub' }, 'نقش بسازید، به کاربران بدهید و برای هر نقش یا هر کاربر مشخص کنید چه چیزی «قفل» یا «مخفی» باشد.'), tabs, S.view);
		var fn = { dashboard: vDashboard, roles: vRoles, users: vUsers, rules: vRules, stealth: vStealth, logs: vLogs, settings: vSettings }[S.tab] || vDashboard;
		fn();
	}
	function setView() { S.view.innerHTML = ''; add(S.view, Array.prototype.slice.call(arguments)); }

	function needCatalog() { return S.cat ? Promise.resolve(S.cat) : api('catalog').then(function (c) { S.cat = c; return c; }); }

	/* ======================= داشبورد ======================= */
	function presetCards(onDone) {
		return h('div', { class: 'gvac-presets' }, S.boot.presets.map(function (p) {
			return h('div', { class: 'gvac-preset' }, h('b', null, p.name), h('p', null, p.desc),
				p.exists ? chip('ساخته شده', 'ok', 'open') : btn('ساخت این نقش', 'sm pri', function () {
					call('role_preset', { id: p.id }, 'نقش ساخته شد.').then(function (r) { if (r) { reloadBoot().then(onDone); } });
				}));
		}));
	}

	function vDashboard() {
		reloadBoot().then(function (b) {
			var s = b.stats, max = Math.max.apply(null, b.chart.map(function (x) { return x.n; }).concat([1]));
			var kp = function (v, l, w) { return h('div', { class: 'gvac-kpi' + (w ? ' warn' : '') }, h('b', null, fa(v)), h('span', null, l)); };
			var bars = h('div', { class: 'gvac-bars', role: 'img', 'aria-label': 'نمودار تلاش‌های ممنوع ۱۴ روز اخیر' }, b.chart.map(function (x) {
				var d = new Date(x.d + 'T12:00:00');
				return h('div', { title: fa(x.n) + ' تلاش — ' + d.toLocaleDateString('fa-IR') }, h('i', { class: x.n ? '' : 'z', style: 'height:' + (x.n ? Math.max(6, x.n / max * 90) : 3) + 'px' }), h('span', null, d.toLocaleDateString('fa-IR', { day: 'numeric' })));
			}));
			var warns = h('ul', { class: 'gvac-health' }, b.health.map(function (x) { return h('li', null, h('span', { class: 'gvac-dot ' + x.level }), h('span', null, x.text)); }));
			var feed = h('ul', { class: 'gvac-feed' }, b.recent.length ? b.recent.map(function (x) {
				return h('li', null, h('b', null, x.user), h('span', null, actionLabel(x.action) + (x.subject ? ' — ' + x.subject : '')), h('time', null, fdate(x.ts)));
			}) : h('li', { class: 'gvac-muted' }, 'هنوز تغییری ثبت نشده.'));
			setView(
				h('div', { class: 'gvac-kpis' }, kp(s.roles, 'نقش (' + fa(s.custom) + ' سفارشی)'), kp(s.users, 'کاربر'), kp(s.rules, 'محدودیت فعال'), kp(s.stealth, 'کاربر پنهان'), kp(s.temp, 'نقش موقت فعال'), kp(s.denied7, 'تلاش ممنوع در ۷ روز', s.denied7 > 0)),
				h('div', { class: 'gvac-grid2' },
					h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'تلاش‌های ممنوع (۱۴ روز اخیر)')), bars,
						b.top.length ? h('ul', { class: 'gvac-top' }, b.top.map(function (t) { return h('li', null, h('span', null, t.label), chip(fa(t.n) + ' بار', 'lock')); })) : h('p', { class: 'gvac-muted', style: 'margin-top:10px' }, 'هنوز کسی به بخش بسته‌شده سر نزده است.')),
					h('div', null,
						h('div', { class: 'gvac-card' }, h('h3', { style: 'margin-bottom:8px' }, 'وضعیت سیستم'), warns),
						h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'آخرین تغییرات'), btn('همه', 'sm', function () { go('logs'); })), feed))),
				h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'شروع سریع با قالب‌های آماده'), btn('قوانین دسترسی', 'sm', function () { go('rules'); })), presetCards(vDashboard)));
		});
	}

	var ACTIONS = { 'role.create': 'ساخت نقش', 'role.rename': 'تغییر نام نقش', 'role.caps': 'ویرایش قابلیت‌های نقش', 'role.delete': 'حذف نقش', 'user.roles': 'تغییر نقش کاربر', 'user.temp': 'نقش موقت', 'user.temp_end': 'پایان نقش موقت', 'user.create': 'ساخت کاربر', 'rules.save': 'ذخیره‌ی قوانین', 'rules.reset': 'بازنشانی قوانین', 'stealth.on': 'پنهان‌سازی کاربر', 'stealth.off': 'پایان پنهان‌سازی', settings: 'ذخیره‌ی تنظیمات', immune: 'تغییر کاربران مصون', import: 'درون‌ریزی', 'recovery.regen': 'کلید بازیابی جدید', recovery: 'بازیابی اضطراری', 'preview.start': 'شروع پیش‌نمایش', 'log.clear': 'پاک‌کردن لاگ', hidden: 'ورود به آیتم مخفی', locked: 'ورود به آیتم قفل', 'admin.block': 'ورود به پیشخوان (بسته)' };
	function actionLabel(a) { return ACTIONS[a] || a; }

	/* ======================= انتخابگر قابلیت‌ها ======================= */
	function capsPicker(groups, set, locked) {
		var wrap = h('div'), q = '';
		var body = h('div');
		function draw() {
			body.innerHTML = '';
			groups.forEach(function (g) {
				var caps = g.caps.filter(function (c) { return !q || c.cap.indexOf(q) > -1 || (c.label && c.label.indexOf(q) > -1); });
				if (!caps.length) { return; }
				var on = caps.filter(function (c) { return set.has(c.cap); }).length;
				body.append(h('div', { class: 'gvac-cap-group' },
					h('h4', null, g.label, h('span', { class: 'gvac-muted' }, fa(on) + ' / ' + fa(caps.length)),
						locked ? null : h('button', { type: 'button', class: 'gvac-btn sm', onclick: function () { var all = on < caps.length; caps.forEach(function (c) { if (all) { set.add(c.cap); } else if (c.cap !== 'read') { set.delete(c.cap); } }); draw(); } }, on < caps.length ? 'انتخاب همه' : 'برداشتن همه')),
					h('div', { class: 'gvac-caps' }, caps.map(function (c) {
						var i = h('input', { type: 'checkbox', checked: set.has(c.cap), disabled: locked || c.cap === 'read', onchange: function () { if (i.checked) { set.add(c.cap); } else { set.delete(c.cap); } draw(); } });
						return h('label', { class: 'gvac-cap' }, i, h('span', null, c.label || c.cap, ' ', c.label ? h('code', null, c.cap) : null, c.danger ? h('span', { class: 'dg' }, ' ⚠ حساس') : null));
					}))));
			});
			if (!body.children.length) { body.append(h('p', { class: 'gvac-empty' }, 'قابلیتی پیدا نشد.')); }
		}
		draw();
		wrap.append(searchBox('جستجوی قابلیت…', function (v) { q = v; draw(); }), h('div', { style: 'height:12px' }), body);
		return wrap;
	}

	/* ======================= نقش‌ها ======================= */
	function openCaps(slug) {
		Promise.all([api('role_get', { slug: slug }), needCatalog()]).then(function (r) {
			var role = r[0], set = new Set(role.caps);
			var name = h('input', { class: 'gvac-in', value: role.name, disabled: role.locked });
			var body = h('div', null,
				role.locked ? h('div', { class: 'gvac-note' }, 'قابلیت‌های «مدیر کل» برای جلوگیری از قفل‌شدن سایت قابل ویرایش نیست. اگر مدیر محدودتری می‌خواهید، از قالب «مدیر محدود» نقش جدید بسازید.') : null,
				field('نام نقش', name), capsPicker(S.cat.caps, set, role.locked));
			modal('قابلیت‌های نقش: ' + role.name, body, role.locked ? [{ label: 'بستن', fn: function (c) { c(); } }] : [
				{ label: 'ذخیره', cls: 'pri', fn: function (c) { call('role_save', { slug: slug, name: name.value, caps: Array.from(set) }, 'ذخیره شد.').then(function (x) { if (x) { c(); reloadBoot().then(render); } }); } },
				{ label: 'انصراف', fn: function (c) { c(); } }], true);
		});
	}

	function newRoleModal() {
		var name = h('input', { class: 'gvac-in', placeholder: 'مثلاً: ویرایشگر محتوا' }), slug = h('input', { class: 'gvac-in ltr', placeholder: 'اختیاری، مثل content_editor' });
		var from = h('select', { class: 'gvac-sel' }, h('option', { value: '' }, 'نقش خالی (فقط ورود به پیشخوان)'), S.boot.roles.map(function (r) { return h('option', { value: r.slug }, 'کپی از: ' + r.name); }));
		modal('نقش جدید', h('div', null, field('نام نقش', name), field('شروع از', from, 'می‌توانید از روی یک نقش موجود شروع کنید و بعد قابلیت‌ها را کم و زیاد کنید.'), field('شناسه (انگلیسی)', slug, 'اگر خالی بگذارید خودکار ساخته می‌شود.')), [
			{ label: 'ساخت نقش', cls: 'pri', fn: function (c) { call('role_save', { name: name.value, new_slug: slug.value, from: from.value }, 'نقش ساخته شد.').then(function (r) { if (r) { c(); reloadBoot().then(function () { go('roles'); openCaps(r.slug); }); } }); } },
			{ label: 'انصراف', fn: function (c) { c(); } }]);
	}

	function vRoles() {
		reloadBoot().then(function (b) {
			var rows = b.roles.map(function (r) {
				return h('tr', null,
					h('td', null, h('b', null, r.name), h('div', { class: 'gvac-muted', style: 'direction:ltr;text-align:right' }, r.slug)),
					h('td', null, h('div', { class: 'gvac-chips' }, r.core ? chip('پیش‌فرض وردپرس') : (r.ours ? chip('سفارشی', 'brand') : chip('افزونه‌ای')), r.admin ? chip('مدیریت تنظیمات', 'hide') : null, r.locked ? chip('ثابت', 'lock', 'lock') : null)),
					h('td', null, fa(r.users)), h('td', null, fa(r.caps)), h('td', null, r.rules ? chip(fa(r.rules) + ' محدودیت', 'lock') : h('span', { class: 'gvac-muted' }, '—')),
					h('td', null, h('div', { class: 'gvac-acts' },
						btn('قابلیت‌ها', 'sm', function () { openCaps(r.slug); }),
						btn('قوانین دسترسی', 'sm', function () { go('rules', { type: 'role', id: r.slug }); }),
						b.me.immune ? btn('پیش‌نمایش', 'sm', function () { startPreview('role', r.slug); }, 'eye') : null,
						btn('کپی', 'sm', function () { cloneRole(r); }, 'copy'),
						r.core ? null : btn('حذف', 'sm danger', function () { delRole(r); }, 'trash'))));
			});
			setView(h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'نقش‌ها (سطوح کاربری)'), btn('نقش جدید', 'pri', newRoleModal, 'plus')),
				h('div', { class: 'gvac-scroll' }, h('table', { class: 'gvac-table' }, h('thead', null, h('tr', null, ['نقش', 'نوع', 'کاربران', 'قابلیت', 'محدودیت‌ها', 'عملیات'].map(function (x) { return h('th', null, x); }))), h('tbody', null, rows)))),
				h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'قالب‌های آماده')), presetCards(vRoles)));
		});
	}
	function startPreview(type, id) {
		call('preview', { type: type, id: id }).then(function (r) { if (r) { location.href = r.url; } });
	}
	function cloneRole(r) {
		var n = h('input', { class: 'gvac-in', value: r.name + ' (کپی)' });
		modal('کپی نقش «' + r.name + '»', field('نام نقش جدید', n, 'قابلیت‌ها و قوانین دسترسی هم کپی می‌شود.'), [
			{ label: 'کپی', cls: 'pri', fn: function (c) { call('role_clone', { from: r.slug, name: n.value }, 'کپی شد.').then(function (x) { if (x) { c(); vRoles(); } }); } }, { label: 'انصراف', fn: function (c) { c(); } }]);
	}
	function delRole(r) {
		var to = h('select', { class: 'gvac-sel' }, S.boot.roles.filter(function (x) { return x.slug !== r.slug; }).map(function (x) { return h('option', { value: x.slug, selected: x.slug === 'subscriber' }, x.name); }));
		modal('حذف نقش «' + r.name + '»', h('div', null, h('p', null, fa(r.users) + ' کاربر این نقش را دارند. بعد از حذف، آن‌ها این نقش را می‌گیرند:'), h('div', { style: 'height:8px' }), to), [
			{ label: 'حذف نقش', cls: 'danger', fn: function (c) { call('role_delete', { slug: r.slug, reassign: to.value }, 'حذف شد.').then(function (x) { if (x) { c(); vRoles(); } }); } }, { label: 'انصراف', fn: function (c) { c(); } }]);
	}

	/* ======================= کاربران ======================= */
	function vUsers() {
		var U = S.users;
		var list = h('div', { class: 'gvac-card' }), bulkBar = h('div', { class: 'gvac-bulk' });
		var roleSel = h('select', { class: 'gvac-sel', style: 'max-width:200px', onchange: function () { U.role = roleSel.value; U.page = 1; load(); } }, h('option', { value: '' }, 'همه‌ی نقش‌ها'), S.boot.roles.map(function (r) { return h('option', { value: r.slug, selected: U.role === r.slug }, r.name); }));
		var top = h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-row' }, h('div', { style: 'flex:1;min-width:220px' }, searchBox('جستجوی نام، نام کاربری یا ایمیل…', function (v) { U.search = v; U.page = 1; load(); })), roleSel, btn('کاربر جدید', 'pri', newUserModal, 'plus')));
		setView(top, list);
		function load() {
			list.innerHTML = ''; list.append(h('div', { class: 'gvac-boot gvac-spin' }, 'در حال بارگذاری…'));
			api('users', U).then(function (d) {
				list.innerHTML = '';
				var ids = function () { return Object.keys(S.sel).filter(function (k) { return S.sel[k]; }).map(Number); };
				var bulkRole = h('select', { class: 'gvac-sel', style: 'max-width:180px' }, S.boot.roles.map(function (r) { return h('option', { value: r.slug }, r.name); }));
				var bulkAct = h('select', { class: 'gvac-sel', style: 'max-width:170px' }, h('option', { value: 'add' }, 'افزودن نقش'), h('option', { value: 'remove' }, 'برداشتن نقش'), h('option', { value: 'set' }, 'تعیین نقش (جایگزین)'));
				bulkBar = h('div', { class: 'gvac-bulk' }, h('span', { class: 'gvac-muted' }, 'عملیات گروهی روی کاربران انتخاب‌شده:'), bulkAct, bulkRole, btn('اجرا', 'sm pri', function () {
					if (!ids().length) { toast('کاربری انتخاب نشده.', 'err'); return; }
					call('user_bulk', { ids: ids(), action: bulkAct.value, role: bulkRole.value }).then(function (r) { if (r) { toast(fa(r.ok) + ' کاربر به‌روز شد.' + (r.errors.length ? ' ' + r.errors.join(' | ') : ''), r.errors.length ? 'err' : 'ok'); S.sel = {}; load(); } });
				}));
				var rows = d.items.map(function (u) {
					var cb = h('input', { type: 'checkbox', checked: !!S.sel[u.id], 'aria-label': 'انتخاب ' + u.name, onchange: function () { S.sel[u.id] = cb.checked; } });
					return h('tr', null, h('td', null, cb),
						h('td', null, h('div', { class: 'gvac-who' }, h('img', { class: 'gvac-av', src: u.avatar, alt: '', loading: 'lazy' }), h('div', null, h('b', null, u.name, u.self ? ' (شما)' : ''), h('small', null, u.email)))),
						h('td', null, h('div', { class: 'gvac-chips' }, u.roles.map(function (r) { return chip(r.name, 'brand'); }), u.temp ? chip('موقت تا ' + fdate(u.temp.until), 'lock', 'clock') : null)),
						h('td', null, h('div', { class: 'gvac-chips' }, u.rules ? chip(fa(u.rules) + ' قانون', 'lock') : null, u.stealth ? chip('پنهان', 'hide', 'ghost') : null, u.immune ? chip('مصون', 'ok', 'roles') : null, !u.rules && !u.stealth && !u.immune ? h('span', { class: 'gvac-muted' }, '—') : null)),
						h('td', { class: 'gvac-muted' }, u.last ? fdate(u.last) : 'ثبت نشده'),
						h('td', null, h('div', { class: 'gvac-acts' },
							btn('نقش‌ها', 'sm', function () { userRolesModal(u, load); }),
							btn('قوانین', 'sm', function () { go('rules', { type: 'user', id: u.id }); }),
							btn('نمای نهایی', 'sm', function () { effectiveModal(u); }),
							S.boot.me.immune && !u.self ? btn('پیش‌نمایش', 'sm', function () { startPreview('user', u.id); }, 'eye') : null)));
				});
				list.append(h('div', { class: 'gvac-cardhead' }, h('h3', null, 'کاربران'), h('span', { class: 'gvac-muted' }, fa(d.total) + ' کاربر')), bulkBar,
					d.items.length ? h('div', { class: 'gvac-scroll' }, h('table', { class: 'gvac-table' }, h('thead', null, h('tr', null, ['', 'کاربر', 'نقش', 'وضعیت', 'آخرین ورود', 'عملیات'].map(function (x) { return h('th', null, x); }))), h('tbody', null, rows))) : h('p', { class: 'gvac-empty' }, 'کاربری پیدا نشد.'),
					pager(d, function (p) { U.page = p; load(); }));
			}, function (e) { list.innerHTML = ''; list.append(h('p', { class: 'gvac-empty' }, e.message)); });
		}
		load();
	}

	function userRolesModal(u, after) {
		var set = new Set(u.roles.map(function (r) { return r.slug; }));
		var body = h('div', null, h('p', { class: 'gvac-muted', style: 'margin-bottom:10px' }, 'می‌توانید چند نقش بدهید. اولین نقشِ فهرست، نقش اصلی می‌شود. وقتی چند نقش هست، قوانین طبق «روش ادغام» در تنظیمات ترکیب می‌شود.'),
			h('div', { class: 'gvac-caps' }, S.boot.roles.map(function (r) {
				var i = h('input', { type: 'checkbox', checked: set.has(r.slug), onchange: function () { if (i.checked) { set.add(r.slug); } else { set.delete(r.slug); } } });
				return h('label', { class: 'gvac-cap' }, i, h('span', null, r.name, ' ', h('code', null, r.slug)));
			})));
		var btns = [{ label: 'ذخیره', cls: 'pri', fn: function (c) { var order = S.boot.roles.map(function (r) { return r.slug; }).filter(function (s) { return set.has(s); }); call('user_roles', { id: u.id, roles: order }, 'نقش‌ها ذخیره شد.').then(function (x) { if (x) { c(); after(); } }); } },
			{ label: 'نقش موقت…', fn: function (c) { c(); tempModal(u, after); } }, { label: 'انصراف', fn: function (c) { c(); } }];
		modal('نقش‌های ' + u.name, body, btns);
	}

	function tempModal(u, after) {
		var role = h('select', { class: 'gvac-sel' }, S.boot.roles.map(function (r) { return h('option', { value: r.slug }, r.name); }));
		var dur = h('select', { class: 'gvac-sel' }, [['3600', '۱ ساعت'], ['21600', '۶ ساعت'], ['86400', '۱ روز'], ['259200', '۳ روز'], ['604800', '۷ روز'], ['2592000', '۳۰ روز']].map(function (x) { return h('option', { value: x[0] }, x[1]); }));
		var btns = [{ label: 'اعمال نقش موقت', cls: 'pri', fn: function (c) { call('user_temp', { id: u.id, role: role.value, until: Math.floor(Date.now() / 1000) + Number(dur.value) }, 'نقش موقت اعمال شد.').then(function (x) { if (x) { c(); after(); } }); } }, { label: 'انصراف', fn: function (c) { c(); } }];
		if (u.temp) { btns.unshift({ label: 'لغو و برگرداندن نقش قبلی', cls: 'danger', fn: function (c) { call('user_temp', { id: u.id, clear: 1 }, 'برگشت.').then(function (x) { if (x) { c(); after(); } }); } }); }
		modal('نقش موقت برای ' + u.name, h('div', null, u.temp ? h('div', { class: 'gvac-note info' }, 'نقش موقت «' + u.temp.role + '» تا ' + fdate(u.temp.until) + ' فعال است.') : null, h('p', { class: 'gvac-muted', style: 'margin-bottom:10px' }, 'نقش‌های فعلی کاربر تا پایان مدت کنار می‌رود و بعد خودکار برمی‌گردد.'), field('نقش موقت', role), field('مدت', dur)), btns);
	}

	function effectiveModal(u) {
		call('effective', { id: u.id }).then(function (e) {
			if (!e) { return; }
			var st = { hide: ['مخفی', 'hide'], lock: ['قفل', 'lock'], allow: ['باز (بازنویسی)', 'ok'] };
			var items = e.items.length ? h('table', { class: 'gvac-table' }, h('tbody', null, e.items.map(function (x) { return h('tr', null, h('td', { class: 'gvac-muted' }, x.sec), h('td', null, x.label), h('td', null, chip(st[x.state][0], st[x.state][1]))); }))) : h('p', { class: 'gvac-muted' }, 'هیچ آیتمی محدود نشده است.');
			var b = e.behavior || {};
			modal('نمای نهایی دسترسی: ' + e.user.name, h('div', null,
				h('div', { class: 'gvac-chips', style: 'margin-bottom:12px' }, e.user.roles.map(function (r) { return chip(r, 'brand'); }), e.temp ? chip('نقش موقت: ' + e.temp.role, 'lock', 'clock') : null, e.immune ? chip('مصون — محدودیت‌ها روی او اعمال نمی‌شود', 'ok') : null, e.stealth ? chip('پنهان', 'hide', 'ghost') : null, e.hides_plugin ? chip('افزونه برای او پنهان است', 'hide') : null),
				h('h4', { style: 'margin:6px 0' }, 'آیتم‌های محدود (ترکیب نقش‌ها و قوانین شخصی)'), items,
				e.caps.length ? h('div', null, h('h4', { style: 'margin:12px 0 6px' }, 'قابلیت‌های بازنویسی‌شده'), h('div', { class: 'gvac-chips' }, e.caps.map(function (c) { return chip(c.cap, c.state === 'grant' ? 'ok' : 'hide'); }))) : null,
				h('div', { class: 'gvac-chips', style: 'margin-top:12px' }, e.urls.length ? chip(fa(e.urls.length) + ' قانون آدرس', 'lock') : null, e.content ? chip(fa(e.content) + ' محتوای محدود', 'lock') : null, b.block_admin ? chip('پیشخوان بسته است', 'hide') : null, b.hide_adminbar_all || b.hide_adminbar_front ? chip('نوار ادمین پنهان', 'lock') : null)), [{ label: 'بستن', fn: function (c) { c(); } }], true);
		});
	}

	function newUserModal() {
		var f = { login: h('input', { class: 'gvac-in ltr' }), email: h('input', { class: 'gvac-in ltr', type: 'email' }), name: h('input', { class: 'gvac-in' }), pass: h('input', { class: 'gvac-in ltr', placeholder: 'خالی = رمز خودکار' }) };
		var role = h('select', { class: 'gvac-sel' }, S.boot.roles.map(function (r) { return h('option', { value: r.slug, selected: r.slug === 'subscriber' }, r.name); }));
		var notify = h('input', { type: 'checkbox' });
		modal('کاربر جدید', h('div', null, field('نام کاربری', f.login), field('ایمیل', f.email), field('نام نمایشی', f.name), field('رمز عبور', f.pass), field('نقش', role), h('label', { class: 'gvac-cap' }, notify, 'ارسال ایمیل خوش‌آمد به کاربر')), [
			{ label: 'ساخت کاربر', cls: 'pri', fn: function (c) { call('user_create', { login: f.login.value, email: f.email.value, name: f.name.value, password: f.pass.value, role: role.value, notify: notify.checked }).then(function (r) { if (r) { c(); toast('کاربر ساخته شد.' + (r.password ? ' رمز: ' + r.password : ''), 'ok'); vUsers(); } }); } },
			{ label: 'انصراف', fn: function (c) { c(); } }]);
	}

	/* ======================= قوانین دسترسی ======================= */
	var E = null; // سوژه‌ی در حال ویرایش

	function normRules(r) {
		var out = { menu: {}, adminbar: {}, widgets: {}, metaboxes: {}, caps: {}, urls: [], content: {}, behavior: {} };
		['menu', 'adminbar', 'widgets', 'metaboxes', 'caps'].forEach(function (k) { if (r && r[k] && !Array.isArray(r[k])) { out[k] = r[k]; } });
		if (r && Array.isArray(r.urls)) { out.urls = r.urls; }
		if (r && r.content && !Array.isArray(r.content)) { out.content = r.content; }
		if (r && r.behavior && !Array.isArray(r.behavior)) { out.behavior = r.behavior; }
		return out;
	}
	function capsStr() { return E && E.caps ? JSON.stringify(Array.from(E.caps).sort()) : ''; }
	function dirty() { return !!E && (JSON.stringify(E.rules) !== E.orig || (E.type === 'role' && capsStr() !== E.origCaps)); }
	S.dirtyCheck = dirty;

	function loadSubject(type, id) {
		return call('rules_get', { type: type, id: id }).then(function (d) {
			if (!d) { return; }
			E = { type: d.type, id: d.id, name: d.name, rules: normRules(d.rules), inherited: normRules(d.inherited), caps: new Set(d.caps), capsLocked: !!d.caps_lock, immune: !!d.immune, roles: d.roles || [], meta: d.content_meta || {}, sub: (E && E.sub) || 'menu', filter: '', exp: {} };
			E.orig = JSON.stringify(E.rules); E.origCaps = capsStr();
			S.edit = E;
			if (type === 'user') { S.recent = S.recent || {}; S.recent[d.id] = d.name; }
		});
	}

	function vRules() {
		Promise.all([needCatalog(), loadSubjectFromOpts()]).then(function () { drawRules(); });
	}
	function loadSubjectFromOpts() {
		var o = S.opts || {};
		if (o.type) { S.opts = {}; return loadSubject(o.type, o.id); }
		if (E) { return Promise.resolve(); }
		var first = S.boot.roles.filter(function (r) { return r.slug === 'editor'; })[0] || S.boot.roles[0];
		return loadSubject('role', first.slug);
	}

	function pickSubject(type, id) {
		if (E && E.type === type && E.id === id) { return; }
		var run = function () { loadSubject(type, id).then(drawRules); };
		if (dirty()) { confirmBox('تغییرات ذخیره‌نشده از بین می‌رود. ادامه می‌دهید؟', 'ادامه', true).then(function (ok) { if (ok) { run(); } }); } else { run(); }
	}

	function stateOpts() {
		var role = E.type === 'role';
		return {
			full: (role ? [] : [['inherit', 'ارث‌بری', 'inherit']]).concat([['allow', role ? 'باز' : 'باز (بازنویسی)', 'open'], ['lock', 'قفل', 'lock'], ['hide', 'مخفی', 'hide']]),
			two: (role ? [] : [['inherit', 'ارث‌بری', 'inherit']]).concat([['allow', 'باز', 'open'], ['hide', 'مخفی', 'hide']])
		};
	}
	function getSt(sec, key) { return E.rules[sec][key] || (E.type === 'role' ? 'allow' : 'inherit'); }
	function setSt(sec, key, v) {
		if (v === 'inherit' || (v === 'allow' && E.type === 'role')) { delete E.rules[sec][key]; } else { E.rules[sec][key] = v; }
		refreshBar();
	}
	function seg(opts, val, on, dis) {
		var w = h('div', { class: 'gvac-seg' + (dis ? ' dis' : ''), role: 'radiogroup' });
		opts.forEach(function (o) {
			var b = h('button', { type: 'button', class: 'st-' + o[0] + (val === o[0] ? ' on' : ''), 'aria-pressed': val === o[0], onclick: function () {
				on(o[0]);
				Array.prototype.forEach.call(w.children, function (x) { x.classList.remove('on'); x.setAttribute('aria-pressed', 'false'); });
				b.classList.add('on'); b.setAttribute('aria-pressed', 'true');
			} }, o[2] ? ico(o[2]) : null, o[1]);
			w.append(b);
		});
		return w;
	}
	var SEC_LBL = { hide: 'مخفی', lock: 'قفل', allow: 'باز' };
	function inhChip(sec, key) {
		if (E.type !== 'user' || getSt(sec, key) !== 'inherit') { return null; }
		var v = E.inherited[sec][key];
		return v ? chip('از نقش: ' + SEC_LBL[v], v === 'hide' ? 'hide' : 'lock') : null;
	}
	function ruleRow(sec, key, title, sub, opts, cls, extra) {
		return h('div', { class: 'gvac-rule ' + (cls || '') }, extra || null, h('div', { class: 't' }, title, ' ', sub ? h('small', null, sub) : null, ' ', inhChip(sec, key)), seg(opts, getSt(sec, key), function (v) { setSt(sec, key, v); }));
	}
	function countSec(sec) {
		var n = 0, o = E.rules[sec];
		if (Array.isArray(o)) { return o.length; }
		Object.keys(o).forEach(function (k) { if (o[k] !== 'allow') { n++; } });
		return n;
	}

	function drawRules() {
		var side = h('div', { class: 'gvac-card gvac-side' }), main = h('div');
		var modes = h('div', { class: 'gvac-modes' },
			h('button', { type: 'button', class: E.type === 'role' ? 'on' : '', onclick: function () { S.rpick = 'role'; drawSide(); } }, 'نقش‌ها'),
			h('button', { type: 'button', class: E.type === 'user' || S.rpick === 'user' ? 'on' : '', onclick: function () { S.rpick = 'user'; drawSide(); } }, 'کاربران'));
		var list = h('div');
		function drawSide() {
			var mode = S.rpick || E.type;
			modes.children[0].className = mode === 'role' ? 'on' : ''; modes.children[1].className = mode === 'user' ? 'on' : '';
			list.innerHTML = '';
			if (mode === 'role') {
				S.boot.roles.forEach(function (r) {
					list.append(h('button', { type: 'button', class: 'gvac-subj' + (E.type === 'role' && E.id === r.slug ? ' on' : ''), onclick: function () { pickSubject('role', r.slug); } }, h('span', { class: 't' }, r.name, h('small', null, fa(r.users) + ' کاربر')), r.rules ? h('span', { class: 'c' }, fa(r.rules)) : null));
				});
			} else {
				var res = h('div');
				list.append(searchBox('جستجوی کاربر…', function (v) {
					res.innerHTML = '';
					if (v.length < 2) { return; }
					api('user_find', { q: v }).then(function (u) {
						u.forEach(function (x) { res.append(h('button', { type: 'button', class: 'gvac-subj', onclick: function () { pickSubject('user', x.id); } }, h('img', { class: 'gvac-av', src: x.avatar, alt: '', style: 'width:28px;height:28px' }), h('span', { class: 't' }, x.name, h('small', null, x.login)))); });
						if (!u.length) { res.append(h('p', { class: 'gvac-muted' }, 'کاربری پیدا نشد.')); }
					});
				}), res);
				var rec = Object.keys(S.recent || {});
				if (rec.length) {
					list.append(h('h4', null, 'اخیراً باز شده'));
					rec.forEach(function (id) { list.append(h('button', { type: 'button', class: 'gvac-subj' + (E.type === 'user' && String(E.id) === id ? ' on' : ''), onclick: function () { pickSubject('user', Number(id)); } }, h('span', { class: 't' }, S.recent[id]))); });
				}
				list.append(h('p', { class: 'gvac-muted', style: 'margin-top:10px' }, 'قوانین کاربر روی قوانین نقش‌هایش اولویت دارد.'));
			}
		}
		side.append(modes, list); drawSide();

		var title = h('div', { class: 'gvac-subhead' }, h('h2', null, E.name), chip(E.type === 'role' ? 'نقش' : 'کاربر', 'brand'),
			E.type === 'user' ? E.roles.map(function (r) { return chip(r); }) : null, E.immune ? chip('مصون', 'ok') : null, h('span', { class: 'gvac-spacer' }),
			S.boot.me.immune && !(E.type === 'user' && E.id === S.boot.me.id) ? btn('پیش‌نمایش به‌جای او', '', function () { startPreview(E.type, E.id); }, 'eye') : null,
			btn('بازنشانی همه', 'danger', function () {
				confirmBox('همه‌ی قوانین «' + E.name + '» پاک شود؟', 'پاک کن', true).then(function (ok) { if (ok) { call('rules_reset', { type: E.type, id: E.id }, 'بازنشانی شد.').then(function () { loadSubject(E.type, E.id).then(function () { reloadBoot().then(drawRules); }); }); } });
			}, 'trash'));
		var TABS2 = [['menu', 'منوها'], ['adminbar', 'نوار ادمین'], ['widgets', 'ویجت‌ها'], ['metaboxes', 'متاباکس‌ها'], ['caps', 'قابلیت‌ها'], ['content', 'محتوا'], ['urls', 'آدرس‌ها'], ['behavior', 'ورود و رفتار']];
		var rt = h('div', { class: 'gvac-rtabs' }), panel = h('div', { class: 'gvac-card' });
		function drawTabs() {
			rt.innerHTML = '';
			TABS2.forEach(function (t) {
				var n = t[0] === 'behavior' ? Object.keys(E.rules.behavior).length : (t[0] === 'caps' ? Object.keys(E.rules.caps).length : countSec(t[0]));
				rt.append(h('button', { type: 'button', class: 'gvac-rtab' + (E.sub === t[0] ? ' on' : ''), onclick: function () { E.sub = t[0]; drawTabs(); drawPanel(); } }, t[1], n ? h('span', { class: 'n' }, fa(n)) : null));
			});
		}
		function drawPanel() {
			panel.innerHTML = '';
			var fn = { menu: pMenus, adminbar: pAdminbar, widgets: pWidgets, metaboxes: pMeta, caps: pCaps, content: pContent, urls: pUrls, behavior: pBehavior }[E.sub];
			add(panel, [fn(drawPanel)]);
		}
		var bar = h('div', { class: 'gvac-sticky clean' });
		refreshBar = function () {
			var d = dirty(); bar.className = 'gvac-sticky' + (d ? '' : ' clean'); bar.innerHTML = '';
			bar.append(h('span', null, d ? 'تغییرات ذخیره‌نشده دارید.' : 'همه‌چیز ذخیره است.'), h('span', { class: 'gvac-spacer' }),
				d ? btn('برگرداندن', '', function () { E.rules = JSON.parse(E.orig); E.caps = new Set(JSON.parse(E.origCaps || '[]')); drawTabs(); drawPanel(); refreshBar(); }) : null,
				btn('ذخیره‌ی قوانین', 'pri', saveRules));
			drawTabs();
		};
		function saveRules() {
			var payload = { type: E.type, id: E.id, rules: E.rules };
			if (E.type === 'role' && !E.capsLocked) { payload.caps = Array.from(E.caps); }
			call('rules_save', payload, 'قوانین ذخیره شد.').then(function (r) {
				if (!r) { return; }
				E.orig = JSON.stringify(E.rules); E.origCaps = capsStr(); reloadBoot().then(function () { refreshBar(); drawSide(); });
			});
		}
		setView(h('div', { class: 'gvac-editor' }, side, h('div', null, title, rt, panel, bar)));
		drawTabs(); drawPanel(); refreshBar();
	}
	var refreshBar = function () {};

	function scanBtn(redraw) {
		return btn('اسکن خودکار فهرست', '', function () {
			needCatalog().then(function () { return call('scan_targets'); }).then(function (r) {
				if (!r) { return; }
				var i = 0;
				(function next() {
					if (i >= r.urls.length) { S.cat = null; needCatalog().then(function () { toast('فهرست به‌روز شد.', 'ok'); redraw(); }); return; }
					toast('اسکن ' + fa(i + 1) + ' از ' + fa(r.urls.length) + '…');
					fetch(r.urls[i++], { credentials: 'same-origin' }).then(next, next);
				})();
			});
		}, 'search');
	}
	function bulkBtns(apply, withInherit) {
		var items = [['allow', 'همه باز'], ['lock', 'همه قفل'], ['hide', 'همه مخفی']];
		if (withInherit) { items.unshift(['inherit', 'همه ارث‌بری']); }
		return h('div', { class: 'gvac-bulk' }, items.map(function (x) { return btn(x[1], 'sm', function () { apply(x[0]); }); }));
	}
	function filterBox(redraw) {
		var i = searchBox('فیلتر…', function (v) { E.filter = v; redraw(); });
		i.querySelector('input').value = E.filter;
		return i;
	}
	function match(t) { return !E.filter || String(t).toLowerCase().indexOf(E.filter.toLowerCase()) > -1; }

	function pMenus(redraw) {
		var st = stateOpts(), self = C.self, tops = S.cat.menus;
		if (!tops.length) { return h('p', { class: 'gvac-empty' }, 'فهرست منوها هنوز ساخته نشده؛ یک بار صفحه را تازه کنید.'); }
		var vis = tops.filter(function (m) { return match(m.title) || m.children.some(function (c) { return match(c.title); }); });
		var rows = [];
		vis.forEach(function (m) {
			var ts = getSt('menu', m.key), blocked = ts === 'hide' || ts === 'lock';
			var ex = m.children.length ? h('button', { type: 'button', class: 'gvac-ex' + (E.exp[m.key] || E.filter ? ' open' : ''), 'aria-label': 'باز/بسته', onclick: function () { E.exp[m.key] = !E.exp[m.key]; redraw(); } }, ico('chev')) : h('span', { style: 'width:26px;flex:none' });
			rows.push(ruleRow('menu', m.key, m.title, m.slug, st.full, 'top', ex));
			if (E.exp[m.key] || E.filter) {
				m.children.forEach(function (c) {
					if (E.filter && !match(c.title) && !match(m.title)) { return; }
					var isSelf = c.slug === self;
					if (blocked) {
						rows.push(h('div', { class: 'gvac-rule kid dim' }, h('div', { class: 't' }, c.title, ' ', h('small', null, c.slug)), chip('طبق منوی بالا: ' + SEC_LBL[ts], ts === 'hide' ? 'hide' : 'lock')));
					} else {
						rows.push(ruleRow('menu', c.key, c.title, c.slug, st.full, 'kid', null));
						if (isSelf) { rows[rows.length - 1].querySelector('.t').append(' ', chip('برای کاربران مصون همیشه باز است', 'ok')); }
					}
				});
			}
		});
		return h('div', null,
			h('div', { class: 'gvac-note info' }, '«قفل»: آیتم در منو دیده می‌شود ولی با آیکن قفل؛ کلیک روی آن صفحه‌ی «دسترسی بسته است» را نشان می‌دهد. «مخفی»: آیتم کلاً از منو برداشته می‌شود و ورود مستقیم با آدرس هم بسته است. محدودکردن یک منوی اصلی، زیرمنوهایش را هم شامل می‌شود.'),
			h('div', { class: 'gvac-row', style: 'margin-bottom:8px' }, h('div', { style: 'flex:1;min-width:200px' }, filterBox(redraw)), bulkBtns(function (v) { vis.forEach(function (m) { setSt('menu', m.key, v); }); redraw(); }, E.type === 'user')),
			rows.length ? rows : h('p', { class: 'gvac-empty' }, 'موردی پیدا نشد.'));
	}

	function pAdminbar(redraw) {
		var st = stateOpts(), nodes = S.cat.adminbar;
		if (!nodes.length) { return h('p', { class: 'gvac-empty' }, 'هنوز آیتمی ثبت نشده. چند صفحه‌ی ادمین یا سایت را باز کنید یا دکمه‌ی اسکن را بزنید.'); }
		var top = nodes.filter(function (n) { return !n.parent; }), kids = function (id) { return nodes.filter(function (n) { return n.parent === id; }); };
		var rows = [];
		top.concat(nodes.filter(function (n) { return n.parent && !nodes.some(function (p) { return p.id === n.parent; }); })).forEach(function (n) {
			if (match(n.title) || kids(n.id).some(function (k) { return match(k.title); })) {
				rows.push(ruleRow('adminbar', n.id, n.title, n.id, st.full, 'top', null));
				kids(n.id).forEach(function (k) { if (match(k.title) || match(n.title)) { rows.push(ruleRow('adminbar', k.id, k.title, k.id, st.full, 'kid', null)); } });
			}
		});
		return h('div', null, h('div', { class: 'gvac-row', style: 'margin-bottom:8px' }, h('div', { style: 'flex:1;min-width:200px' }, filterBox(redraw)), scanBtn(redraw)), rows);
	}

	function pWidgets(redraw) {
		var st = stateOpts();
		return h('div', null, h('div', { class: 'gvac-note info' }, 'ویجت‌هایی که روی صفحه‌ی اول پیشخوان دیده می‌شوند. برای دیدن ویجت‌های افزونه‌ها دکمه‌ی اسکن را بزنید.'),
			h('div', { class: 'gvac-row', style: 'margin-bottom:8px' }, h('div', { style: 'flex:1' }, filterBox(redraw)), scanBtn(redraw)),
			S.cat.widgets.filter(function (w) { return match(w.title); }).map(function (w) { return ruleRow('widgets', w.id, w.title, w.id, st.two, '', null); }));
	}

	function pMeta(redraw) {
		var st = stateOpts(), out = [];
		S.cat.metaboxes.forEach(function (g) {
			var boxes = g.boxes.filter(function (b) { return match(b.title) || match(g.label); });
			if (boxes.length) { out.push(h('h4', { style: 'margin:14px 0 4px' }, g.label), boxes.map(function (b) { return ruleRow('metaboxes', b.key, b.title, b.id, st.two, '', null); })); }
		});
		return h('div', null, h('div', { class: 'gvac-note info' }, 'کادرهای صفحه‌ی ویرایش نوشته، برگه و محصول. متاباکس مخفی در ویرایشگر نمایش داده نمی‌شود.'),
			h('div', { class: 'gvac-row', style: 'margin-bottom:8px' }, h('div', { style: 'flex:1' }, filterBox(redraw)), scanBtn(redraw)), out);
	}

	function pCaps(redraw) {
		if (E.type === 'role') {
			return h('div', null, h('div', { class: 'gvac-note info' }, 'این‌ها قابلیت‌های واقعی نقش هستند و با «ذخیره‌ی قوانین» روی خود نقش اعمال می‌شوند. نقشی که قابلیتی ندارد، منوهای مربوط به آن را هم نمی‌بیند.'),
				E.capsLocked ? h('div', { class: 'gvac-note' }, 'قابلیت‌های مدیر کل قابل ویرایش نیست.') : null,
				capsPicker(S.cat.caps, E.caps, E.capsLocked));
		}
		var groups = S.cat.caps, q = E.filter;
		return h('div', null, h('div', { class: 'gvac-note info' }, 'برای این کاربر یک قابلیت را «اعطا» یا «ممنوع» کنید؛ بدون تغییر، از نقش‌های او ارث می‌برد. نشان «نقش دارد» یعنی نقش‌های فعلی این قابلیت را می‌دهند.'),
			h('div', { style: 'margin-bottom:10px' }, filterBox(redraw)),
			groups.map(function (g) {
				var caps = g.caps.filter(function (c) { return match(c.cap) || match(c.label); });
				if (!caps.length) { return null; }
				return h('div', { class: 'gvac-cap-group' }, h('h4', null, g.label), caps.map(function (c) {
					var v = E.rules.caps[c.cap] || 'inherit', has = E.caps.has(c.cap);
					return h('div', { class: 'gvac-rule' }, h('div', { class: 't' }, c.label || c.cap, ' ', c.label ? h('small', null, c.cap) : null, ' ', v === 'inherit' ? chip(has ? 'نقش دارد' : 'نقش ندارد', has ? 'ok' : '') : null, c.danger ? chip('حساس', 'hide') : null),
						seg([['inherit', 'ارث‌بری', 'inherit'], ['grant', 'اعطا', 'open'], ['deny', 'ممنوع', 'hide']], v, function (x) { if (x === 'inherit') { delete E.rules.caps[c.cap]; } else { E.rules.caps[c.cap] = x; } refreshBar(); }));
				}));
			}));
	}

	function pContent(redraw) {
		var res = h('div'), rows = Object.keys(E.rules.content);
		var box = searchBox('جستجوی نوشته، برگه یا محصول (عنوان یا شناسه)…', function (v) {
			res.innerHTML = '';
			if (v.length < 2) { return; }
			api('search_posts', { q: v }).then(function (p) {
				var r = h('div', { class: 'gvac-results' });
				p.forEach(function (x) { r.append(h('button', { type: 'button', onclick: function () { E.rules.content[x.id] = ['edit', 'delete']; E.meta[x.id] = { title: x.title, type: x.type }; refreshBar(); redraw(); } }, h('span', null, x.title), chip(x.type), h('span', { class: 'gvac-muted' }, '#' + x.id))); });
				res.append(p.length ? r : h('p', { class: 'gvac-muted' }, 'چیزی پیدا نشد.'));
			});
		});
		var acts = [['view', 'مشاهده در سایت'], ['edit', 'ویرایش'], ['delete', 'حذف']];
		return h('div', null, h('div', { class: 'gvac-note info' }, 'برای محتوای مشخص، «مشاهده» را ببندید تا این کاربر/نقش آن را در سایت نبیند (صفحه‌ی دسترسی بسته)، یا «ویرایش» و «حذف» را ببندید.'), box, res, h('div', { style: 'height:12px' }),
			rows.length ? rows.map(function (id) {
				var m = E.meta[id] || { title: '#' + id, type: '' };
				return h('div', { class: 'gvac-rule' }, h('div', { class: 't' }, m.title, ' ', m.type ? chip(m.type) : null, ' ', h('small', null, '#' + id)),
					acts.map(function (a) {
						var cb = h('input', { type: 'checkbox', checked: E.rules.content[id].indexOf(a[0]) > -1, onchange: function () {
							var s = E.rules.content[id], i = s.indexOf(a[0]);
							if (cb.checked && i < 0) { s.push(a[0]); } else if (!cb.checked && i > -1) { s.splice(i, 1); }
							refreshBar();
						} });
						return h('label', { class: 'gvac-cap' }, cb, a[1]);
					}), btn('', 'sm danger', function () { delete E.rules.content[id]; refreshBar(); redraw(); }, 'trash'));
			}) : h('p', { class: 'gvac-empty' }, 'محتوایی محدود نشده است.'));
	}

	function pUrls(redraw) {
		var rows = E.rules.urls.map(function (u, i) {
			var inp = h('input', { class: 'gvac-in ltr', value: u.pattern, placeholder: '/wp-admin/tools.php*', oninput: function () { u.pattern = inp.value; refreshBar(); } });
			return h('div', { class: 'gvac-rule' }, h('div', { class: 't' }, inp), seg([['lock', 'قفل', 'lock'], ['hide', 'مخفی', 'hide']], u.mode, function (v) { u.mode = v; refreshBar(); }), btn('', 'sm danger', function () { E.rules.urls.splice(i, 1); refreshBar(); redraw(); }, 'trash'));
		});
		return h('div', null, h('div', { class: 'gvac-note info' }, 'آدرس‌هایی که برای این نقش/کاربر بسته می‌شود (هم سایت، هم پیشخوان). ستاره (*) یعنی هر چیزی. اگر الگو با / شروع شود از ابتدای آدرس بررسی می‌شود، وگرنه هرجای آدرس. مثال: ' , h('code', { style: 'direction:ltr;display:inline-block' }, '/wp-admin/options-*'), ' یا ', h('code', { style: 'direction:ltr;display:inline-block' }, 'action=delete')),
			rows.length ? rows : h('p', { class: 'gvac-empty' }, 'قانون آدرسی ثبت نشده.'),
			btn('افزودن آدرس', '', function () { E.rules.urls.push({ pattern: '', mode: 'lock' }); refreshBar(); redraw(); }, 'plus'));
	}

	function pBehavior() {
		var b = E.rules.behavior;
		function inp(k, label, hint) { var i = h('input', { class: 'gvac-in ltr', value: b[k] || '', placeholder: 'https://', oninput: function () { if (i.value.trim()) { b[k] = i.value.trim(); } else { delete b[k]; } refreshBar(); } }); return field(label, i, hint); }
		function tg(k, label, hint) { return sw(label, hint, !!b[k], function (v) { if (v) { b[k] = 1; } else { delete b[k]; } refreshBar(); }); }
		var msg = h('textarea', { class: 'gvac-ta', placeholder: 'خالی = پیام عمومی تنظیمات', oninput: function () { if (msg.value.trim()) { b.deny_message = msg.value; } else { delete b.deny_message; } refreshBar(); } }, b.deny_message || '');
		return h('div', null,
			inp('login_redirect', 'بعد از ورود به این آدرس برود', 'مثلاً صفحه‌ی گزارش کار. خالی = رفتار پیش‌فرض وردپرس.'),
			inp('logout_redirect', 'بعد از خروج به این آدرس برود'),
			inp('deny_redirect', 'هنگام رسیدن به بخش بسته به این آدرس برود', 'اگر پر شود، به‌جای صفحه‌ی «دسترسی بسته است» کاربر به این آدرس هدایت می‌شود.'),
			field('پیام اختصاصی «دسترسی بسته است»', msg),
			tg('block_admin', 'ورود به پیشخوان وردپرس را کاملاً ببند', 'کاربر فقط سایت را می‌بیند. مناسب مشتری‌ها و اعضا.'),
			inp('block_redirect', 'کاربرِ ممنوع از پیشخوان به این آدرس برود', 'خالی = صفحه‌ی اصلی سایت.'),
			tg('hide_adminbar_front', 'نوار ادمین در سایت دیده نشود'), tg('hide_adminbar_all', 'نوار ادمین همه‌جا (سایت و پیشخوان) دیده نشود'));
	}

	/* ======================= پنهان‌سازی ======================= */
	function vStealth() {
		api('stealth_list').then(function (d) {
			var intro = h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'پنهان‌سازی کاربر (Stealth)')),
				h('p', { class: 'gvac-muted', style: 'margin-bottom:10px' }, 'کاربر پنهان در فهرست کاربران، جستجو، منوی انتخاب نویسنده، REST API و نقشه‌ی سایت دیده نمی‌شود و دیگران نمی‌توانند او را ویرایش، حذف یا تغییر نقش بدهند. کاربران پنهان همدیگر را می‌بینند. می‌توانید فقط از چشم نقش‌ها یا افراد مشخصی پنهان بمانید.'),
				sw('پنهان‌کردن خودِ افزونه گروت ویژن', 'منوی افزونه، همه‌ی صفحه‌های آن، ردیف افزونه در «افزونه‌ها» و اعلان به‌روزرسانی‌اش، از دید همه‌ی کاربران به‌جز کاربران پنهان و مصون برداشته می‌شود.', d.hide_plugin, function (v) { call('settings_save', { hide_plugin: v ? 1 : 0 }, 'ذخیره شد.').then(function (r) { if (r) { S.boot.settings = r; } }); }));
			var addRes = h('div');
			var add1 = h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'پنهان‌کردن یک کاربر')), searchBox('نام یا ایمیل کاربر را جستجو کنید…', function (v) {
				addRes.innerHTML = ''; if (v.length < 2) { return; }
				api('user_find', { q: v }).then(function (u) {
					var r = h('div', { class: 'gvac-results' });
					u.forEach(function (x) { r.append(h('button', { type: 'button', onclick: function () { call('stealth_save', { id: x.id, cfg: { users: 1, rest: 1, protect: 1, from: 'all' } }, 'کاربر پنهان شد.').then(function (o) { if (o) { reloadBoot().then(vStealth); } }); } }, h('img', { class: 'gvac-av', src: x.avatar, alt: '', style: 'width:28px;height:28px' }), h('span', null, x.name, ' ', h('small', { class: 'gvac-muted' }, x.login)))); });
					addRes.append(u.length ? r : h('p', { class: 'gvac-muted' }, 'کاربری پیدا نشد.'));
				});
			}), addRes);
			var cards = d.items.map(function (it) { return stealthCard(it, d.roles); });
			setView(intro, add1, cards.length ? cards : h('div', { class: 'gvac-card' }, h('p', { class: 'gvac-empty' }, 'هنوز کاربری پنهان نشده است.')));
		});
	}
	function stealthCard(it, roles) {
		var c = it.cfg, names = it.names || {};
		var from = h('div'), roleBox = h('div');
		function drawFrom() {
			from.innerHTML = '';
			from.append(h('div', { class: 'gvac-seg', style: 'margin:6px 0' }, [['all', 'از همه پنهان باشم'], ['selected', 'فقط از افراد/نقش‌های مشخص']].map(function (o) {
				return h('button', { type: 'button', class: 'st-inherit' + (c.from === o[0] ? ' on' : ''), onclick: function () { c.from = o[0]; drawFrom(); } }, o[1]);
			})));
			if (c.from === 'selected') {
				var picks = h('div', { class: 'gvac-pick' }, roles.map(function (r) {
					var cb = h('input', { type: 'checkbox', checked: c.from_roles.indexOf(r.slug) > -1, onchange: function () { var i = c.from_roles.indexOf(r.slug); if (cb.checked && i < 0) { c.from_roles.push(r.slug); } else if (!cb.checked && i > -1) { c.from_roles.splice(i, 1); } } });
					return h('label', null, cb, r.name);
				}));
				var chips = h('div', { class: 'gvac-chips', style: 'margin:6px 0' }), res = h('div');
				var drawChips = function () { chips.innerHTML = ''; c.from_users.forEach(function (id) { chips.append(h('button', { type: 'button', class: 'gvac-chip brand', style: 'border:0;cursor:pointer', title: 'حذف', onclick: function () { c.from_users = c.from_users.filter(function (x) { return x !== id; }); drawChips(); } }, (names[id] || '#' + id) + ' ×')); }); };
				drawChips();
				from.append(h('p', { class: 'gvac-muted' }, 'از چشم این نقش‌ها پنهان باش:'), picks, h('p', { class: 'gvac-muted' }, 'و از چشم این افراد:'), chips, searchBox('افزودن فرد…', function (v) {
					res.innerHTML = ''; if (v.length < 2) { return; }
					api('user_find', { q: v }).then(function (u) { var r = h('div', { class: 'gvac-results' }); u.forEach(function (x) { r.append(h('button', { type: 'button', onclick: function () { if (c.from_users.indexOf(x.id) < 0) { c.from_users.push(x.id); names[x.id] = x.name; } drawChips(); res.innerHTML = ''; } }, x.name, ' ', h('small', { class: 'gvac-muted' }, x.login))); }); res.append(r); });
				}), res);
			}
		}
		drawFrom();
		return h('div', { class: 'gvac-stl' }, h('div', { class: 'gvac-row' }, h('img', { class: 'gvac-av', src: it.avatar, alt: '' }), h('div', null, h('b', null, it.name), h('div', { class: 'gvac-muted', style: 'direction:ltr;text-align:right' }, it.login)), h('span', { class: 'gvac-spacer' }),
			btn('ذخیره', 'pri', function () { call('stealth_save', { id: it.id, cfg: c }, 'ذخیره شد.'); }), btn('نمایان‌کردن', 'danger', function () { call('stealth_save', { id: it.id, off: 1 }, 'دوباره نمایان شد.').then(function (r) { if (r) { reloadBoot().then(vStealth); } }); })),
			h('div', { class: 'gvac-stl-grid' },
				sw('لیست کاربران و جستجو', 'در «همه‌ی کاربران»، شمارنده‌ها و منوی نویسنده دیده نشود.', c.users, function (v) { c.users = v ? 1 : 0; }),
				sw('REST API', 'در /wp-json/wp/v2/users پیدا نشود.', c.rest, function (v) { c.rest = v ? 1 : 0; }),
				sw('آرشیو نویسنده و نقشه‌ی سایت', 'صفحه‌ی نویسنده ۴۰۴ شود و در sitemap نیاید (برای همه‌ی بازدیدکننده‌ها).', c.author, function (v) { c.author = v ? 1 : 0; }),
				sw('محافظت', 'دیگران نتوانند او را ویرایش، حذف یا تغییر نقش بدهند.', c.protect, function (v) { c.protect = v ? 1 : 0; })),
			from);
	}

	/* ======================= گزارش‌ها ======================= */
	function vLogs() {
		var L = S.logs, list = h('div', { class: 'gvac-card' });
		var types = [['', 'همه'], ['change', 'تغییرات'], ['denied', 'تلاش‌های ممنوع'], ['system', 'سیستم']];
		var tabs = h('div', { class: 'gvac-rtabs' }, types.map(function (t) { return h('button', { type: 'button', class: 'gvac-rtab' + (L.type === t[0] ? ' on' : ''), onclick: function () { L.type = t[0]; L.page = 1; vLogs(); } }, t[1]); }));
		var top = h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-row' }, h('div', { style: 'flex:1;min-width:220px' }, searchBox('جستجو در گزارش‌ها…', function (v) { L.search = v; L.page = 1; load(); })), btn('پاک‌کردن همه', 'danger', function () { confirmBox('همه‌ی گزارش‌ها پاک شود؟', 'پاک کن', true).then(function (ok) { if (ok) { call('log_clear', {}, 'پاک شد.').then(load); } }); }, 'trash')), h('div', { style: 'height:10px' }), tabs);
		setView(top, list);
		var tl = { change: ['تغییر', 'brand'], denied: ['ممنوع', 'lock'], system: ['سیستم', ''] };
		function load() {
			api('log_list', L).then(function (d) {
				list.innerHTML = '';
				list.append(d.items.length ? h('div', { class: 'gvac-scroll' }, h('table', { class: 'gvac-table' }, h('thead', null, h('tr', null, ['زمان', 'نوع', 'کاربر', 'رویداد', 'جزئیات', 'IP'].map(function (x) { return h('th', null, x); }))), h('tbody', null, d.items.map(function (x) {
					return h('tr', null, h('td', { style: 'white-space:nowrap' }, fdate(x.ts)), h('td', null, chip(tl[x.type][0], tl[x.type][1])), h('td', null, x.user), h('td', null, actionLabel(x.action), x.subject ? h('div', { class: 'gvac-muted' }, x.subject) : null), h('td', { class: 'gvac-muted' }, x.details), h('td', { class: 'gvac-muted', style: 'direction:ltr' }, x.ip));
				})))) : h('p', { class: 'gvac-empty' }, 'گزارشی ثبت نشده.'), pager(d, function (p) { L.page = p; load(); }));
			});
		}
		load();
	}

	/* ======================= تنظیمات ======================= */
	function vSettings() {
		reloadBoot().then(function (b) {
			var s = Object.assign({}, b.settings);
			var sel = function (k, opts) { var e = h('select', { class: 'gvac-sel', onchange: function () { s[k] = e.value; } }, opts.map(function (o) { return h('option', { value: o[0], selected: s[k] === o[0] }, o[1]); })); return e; };
			var title = h('input', { class: 'gvac-in', value: s.deny_title, oninput: function () { s.deny_title = title.value; pvT.textContent = title.value; } });
			var msg = h('textarea', { class: 'gvac-ta', oninput: function () { s.deny_message = msg.value; pvM.textContent = msg.value; } }, s.deny_message);
			var pvT = h('h3', null, s.deny_title), pvM = h('p', { class: 'gvac-muted' }, s.deny_message);
			var core = h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'عملکرد')),
				s.constant_off ? h('div', { class: 'gvac-note' }, 'ثابت GV_AC_DISABLE در wp-config.php فعال است و تا برداشتن آن، همه‌ی محدودیت‌ها خاموش می‌ماند.') : null,
				sw('اعمال محدودیت‌ها', 'کلید اصلی ماژول. خاموش = هیچ قانونی اجرا نمی‌شود (قوانین پاک نمی‌شوند).', s.enabled, function (v) { s.enabled = v ? 1 : 0; }),
				field('وقتی کاربر چند نقش دارد', sel('merge', [['deny', 'سخت‌گیرانه: اگر در هر نقش بسته بود، بسته بماند'], ['allow', 'سهل‌گیرانه: فقط اگر در همه‌ی نقش‌ها بسته بود']])),
				field('ورود مستقیم (با آدرس) به آیتم «مخفی»', sel('hidden_response', [['denied', 'صفحه‌ی «دسترسی بسته است»'], ['dashboard', 'انتقال به پیشخوان'], ['notfound', 'پیام «اجازه‌ی دسترسی ندارید» استاندارد وردپرس']])),
				sw('آیکن قفل کنار آیتم‌های قفل', '', s.show_lock_badge, function (v) { s.show_lock_badge = v ? 1 : 0; }));
			var den = h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'صفحه‌ی «دسترسی بسته است»')),
				h('div', { class: 'gvac-grid2', style: 'grid-template-columns:1fr 1fr' }, h('div', null, field('عنوان', title), field('پیام', msg)),
					h('div', { class: 'gvac-denied-pv' }, h('div', { class: 'k' }, ico('lock')), pvT, pvM)));
			var log = h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'گزارش‌گیری')),
				sw('ثبت تلاش‌های ممنوع', 'هر بار که کسی به بخش قفل یا مخفی سر بزند ثبت می‌شود.', s.log_denied, function (v) { s.log_denied = v ? 1 : 0; }),
				field('نگهداری گزارش (روز)', h('input', { class: 'gvac-in', type: 'number', min: 7, max: 365, value: s.log_days, style: 'max-width:140px', oninput: function (e) { s.log_days = e.target.value; } })));
			var save = h('div', { class: 'gvac-sticky' }, h('span', null, 'تنظیمات بالا با دکمه‌ی ذخیره اعمال می‌شود.'), h('span', { class: 'gvac-spacer' }),
				btn('ذخیره‌ی تنظیمات', 'pri', function () { call('settings_save', s, 'تنظیمات ذخیره شد.').then(function (r) { if (r) { S.boot.settings = r; } }); }));

			var imm = h('div', { class: 'gvac-card' }), recov = h('div', { class: 'gvac-card' });
			function drawImm(set) {
				imm.innerHTML = ''; var res = h('div');
				imm.append(h('div', { class: 'gvac-cardhead' }, h('h3', null, 'کاربران مصون')), h('p', { class: 'gvac-muted', style: 'margin-bottom:10px' }, 'هیچ محدودیتی روی کاربر مصون اعمال نمی‌شود تا هیچ‌وقت از پنل بیرون نیفتید. فقط خود مصون‌ها می‌توانند این فهرست را تغییر دهند و فقط آن‌ها «پیش‌نمایش» دارند.'),
					h('div', { class: 'gvac-chips' }, set.immune.map(function (u) { return h('button', { type: 'button', class: 'gvac-chip ok', style: 'border:0;cursor:pointer', title: 'حذف از مصون‌ها', onclick: function () { call('immune_set', { id: u.id, on: 0 }, 'حذف شد.').then(function (r) { if (r) { drawImm(r); } }); } }, u.name + ' ×'); })),
					h('div', { style: 'height:10px' }),
					searchBox('افزودن کاربر مصون…', function (v) { res.innerHTML = ''; if (v.length < 2) { return; } api('user_find', { q: v }).then(function (u) { var r = h('div', { class: 'gvac-results' }); u.forEach(function (x) { r.append(h('button', { type: 'button', onclick: function () { call('immune_set', { id: x.id, on: 1 }, 'افزوده شد.').then(function (o) { if (o) { drawImm(o); } }); } }, x.name, ' ', h('small', { class: 'gvac-muted' }, x.login))); }); res.append(r); }); }), res);
				recov.innerHTML = '';
				recov.append(h('div', { class: 'gvac-cardhead' }, h('h3', null, 'حالت اضطراری (اگر قفل شدید)')),
					h('p', { class: 'gvac-muted', style: 'margin-bottom:8px' }, 'راه اول: در wp-config.php این خط را اضافه کنید تا همه‌ی محدودیت‌ها خاموش شود و بعد از حل مشکل پاکش کنید:'),
					h('code', { style: 'display:block;direction:ltr;text-align:left;background:#f2f4f7;padding:8px 12px;border-radius:8px;margin-bottom:12px' }, "define( 'GV_AC_DISABLE', true );"),
					set.recovery_url ? h('div', null, h('p', { class: 'gvac-muted', style: 'margin-bottom:6px' }, 'راه دوم: این آدرس را (حتی بدون ورود) باز کنید؛ ۱۵ دقیقه همه‌ی محدودیت‌ها خاموش می‌شود. آن را جایی امن نگه دارید:'),
						h('div', { class: 'gvac-row' }, h('input', { class: 'gvac-in ltr', readonly: true, value: set.recovery_url, style: 'flex:1' }), btn('کپی', 'sm', function () { navigator.clipboard.writeText(set.recovery_url).then(function () { toast('کپی شد.', 'ok'); }); }, 'copy'), btn('ساخت کلید جدید', 'sm danger', function () { call('recovery_regen', {}, 'کلید جدید ساخته شد.').then(function (r) { if (r) { drawImm(r); } }); }))) : h('p', { class: 'gvac-muted' }, 'کلید بازیابی فقط برای کاربران مصون نمایش داده می‌شود.'));
			}
			drawImm(b.settings);

			var file = h('input', { type: 'file', accept: 'application/json,.json' }), mode = h('select', { class: 'gvac-sel', style: 'max-width:240px' }, h('option', { value: 'merge' }, 'ادغام با قوانین فعلی'), h('option', { value: 'replace' }, 'جایگزین قوانین فعلی'));
			var io = h('div', { class: 'gvac-card' }, h('div', { class: 'gvac-cardhead' }, h('h3', null, 'خروجی و ورودی')),
				h('p', { class: 'gvac-muted', style: 'margin-bottom:10px' }, 'نقش‌ها، قوانین، پنهان‌سازی و تنظیمات را برای سایت دیگر یا بکاپ ببرید. قوانین کاربران با نام کاربری جابه‌جا می‌شود. کلید بازیابی و مصون‌ها منتقل نمی‌شود.'),
				h('div', { class: 'gvac-row' }, btn('دانلود خروجی', '', function () { call('export').then(function (r) { if (r) { download('groot-access-' + new Date().toISOString().slice(0, 10) + '.json', r.file); } }); })),
				h('div', { style: 'height:14px' }), h('div', { class: 'gvac-row' }, file, mode, btn('درون‌ریزی', 'pri', function () {
					if (!file.files[0]) { toast('فایل را انتخاب کنید.', 'err'); return; }
					file.files[0].text().then(function (t) { var j; try { j = JSON.parse(t); } catch (e) { toast('فایل JSON معتبر نیست.', 'err'); return; } call('import', { file: j, mode: mode.value }).then(function (r) { if (r) { toast('درون‌ریزی شد: ' + fa(r.roles) + ' نقش، ' + fa(r.rules) + ' قانون نقش، ' + fa(r.users) + ' قانون کاربر' + (r.skipped ? '، ' + fa(r.skipped) + ' ردشده' : ''), 'ok'); S.cat = null; E = null; reloadBoot(); } }); });
				})));
			setView(core, den, log, save, imm, recov, io);
		});
	}

	/* ======================= شروع ======================= */
	reloadBoot().then(render, function (e) { root.innerHTML = ''; root.append(h('div', { class: 'gvac-boot' }, 'خطا در بارگذاری: ' + e.message)); });

	window.GVAC_UI = { h: h, add: add, ico: ico, api: api, call: call, toast: toast, modal: modal, confirmBox: confirmBox, field: field, sw: sw, chip: chip, searchBox: searchBox, btn: btn, pager: pager, download: download, fa: fa, fdate: fdate, S: S, go: go, render: render, reloadBoot: reloadBoot, needCatalog: needCatalog, setView: setView, capsPicker: capsPicker, startPreview: startPreview, actionLabel: actionLabel };
})();
