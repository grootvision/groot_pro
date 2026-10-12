/* Groot Vision — Work Report | wr.js
   بدون وابستگی (Vanilla JS) به‌جز Chart.js که کنار همین فایل است.
   ساختار: post() → ارتباط با سرور | load() → نمایش صفحه‌ها | handlers → دکمه‌ها (data-act) */
(function () {
	'use strict';
	var C = window.GVWR;
	if (!C) { return; }

	/* ---------------------------- ابزارهای پایه ---------------------------- */
	function $(s, r) { return (r || document).querySelector(s); }
	function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
	function fa(n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); }

	function toFD(data) {
		var fd = new FormData();
		Object.keys(data).forEach(function (k) {
			var v = data[k];
			if (v === null || v === undefined) { return; }
			if (k === 'ids' && Array.isArray(v)) { v.forEach(function (i) { fd.append('ids[]', i); }); }
			else if (typeof v === 'object' && !(v instanceof Blob)) { fd.append(k, JSON.stringify(v)); }
			else { fd.append(k, v); }
		});
		return fd;
	}

	function post(op, data) {
		var fd = data instanceof FormData ? data : toFD(data || {});
		fd.append('action', 'gvwr');
		fd.append('nonce', C.nonce);
		fd.append('op', op);
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.catch(function () { throw new Error('پاسخ نامعتبر از سرور. دوباره تلاش کنید.'); })
			.then(function (j) {
				if (!j || !j.success) { throw new Error((j && j.data && j.data.msg) || 'خطایی رخ داد.'); }
				return j.data || {};
			});
	}

	function toast(msg, err) {
		var box = $('#gvwr-toasts');
		if (!box) {
			box = document.createElement('div');
			box.id = 'gvwr-toasts';
			box.className = 'gvwr-toasts';
			box.setAttribute('dir', 'rtl');
			document.body.appendChild(box);
		}
		var t = document.createElement('div');
		t.className = 'gvwr-toast' + (err ? ' err' : '');
		t.textContent = msg;
		box.appendChild(t);
		setTimeout(function () { t.remove(); }, err ? 5000 : 2800);
	}

	function busy(btn, on) { if (btn) { btn.disabled = !!on; } }

	/* ------------------------------- تاریخ شمسی ------------------------------- */
	function g2j(gy, gm, gd) {
		var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
		var gy2 = gm > 2 ? gy + 1 : gy;
		var days = 355666 + 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + gdm[gm - 1];
		var jy = -1595 + 33 * Math.floor(days / 12053);
		days %= 12053;
		jy += 4 * Math.floor(days / 1461);
		days %= 1461;
		if (days > 365) { jy += Math.floor((days - 1) / 365); days = (days - 1) % 365; }
		var jm, jd;
		if (days < 186) { jm = 1 + Math.floor(days / 31); jd = 1 + (days % 31); }
		else { jm = 7 + Math.floor((days - 186) / 30); jd = 1 + ((days - 186) % 30); }
		return [jy, jm, jd];
	}
	var MONTHS = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
	function dateLabel() {
		var inp = $('#gvwr-date'), out = $('#gvwr-jlabel');
		if (!inp || !out) { return; }
		if (!C.jalali || !inp.value) { out.textContent = ''; return; }
		var p = inp.value.split('-').map(Number);
		var j = g2j(p[0], p[1], p[2]);
		out.textContent = fa(j[2]) + ' ' + MONTHS[j[1]] + ' ' + fa(j[0]);
	}

	/* ---------------------------------- نمودارها --------------------------------- */
	function merge(a, b) {
		Object.keys(b || {}).forEach(function (k) {
			if (b[k] && typeof b[k] === 'object' && !Array.isArray(b[k])) { a[k] = merge(a[k] && typeof a[k] === 'object' ? a[k] : {}, b[k]); }
			else { a[k] = b[k]; }
		});
		return a;
	}

	function drawCharts(list, scope) {
		if (!window.Chart || !list) { return; }
		window.Chart.defaults.font.family = 'Vazirmatn, IRANSans, Tahoma, system-ui, sans-serif';
		list.forEach(function (c) {
			var el = (scope || document).querySelector('#' + c.id);
			if (!el) { return; }
			var old = window.Chart.getChart(el);
			if (old) { old.destroy(); }
			var opt = merge({
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { rtl: true, textDirection: 'rtl', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { rtl: true, textDirection: 'rtl' } },
				scales: c.type === 'doughnut' ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }
			}, c.options);
			if (c.type === 'doughnut') { delete opt.scales; opt.cutout = '62%'; }
			new window.Chart(el, { type: c.type, data: { labels: c.labels, datasets: c.datasets }, options: opt });
		});
	}

	/* ------------------------------- پنجره (modal) ------------------------------- */
	function closeModal() { var m = $('.gvwr-modal'); if (m) { m.remove(); } }
	function modal(html, charts) {
		closeModal();
		var m = document.createElement('div');
		m.className = 'gvwr-modal gvwr-app';
		m.setAttribute('dir', 'rtl');
		m.innerHTML = '<div class="gvwr-modal-box"><button type="button" class="gvwr-ib gvwr-modal-x" data-act="modal-close" aria-label="بستن">×</button>' + html + '</div>';
		document.body.appendChild(m);
		m.addEventListener('mousedown', function (e) { if (e.target === m) { closeModal(); } });
		$$('textarea', m).forEach(grow);
		drawCharts(charts, m);
		return m;
	}
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeModal(); } });

	/* ---------------------------- لود صفحه‌ها (views) ---------------------------- */
	function stateOf(root) {
		if (!root._st) {
			root._st = { period: root.dataset.period || 'month', anchor: '', user: 0, cat: 0, project: root.dataset.project || 0, status: root.dataset.status || '', q: '', page: 1 };
		}
		return root._st;
	}

	function load(root, patch, focusName) {
		var st = Object.assign(stateOf(root), patch || {});
		root.style.opacity = '.55';
		var payload = Object.assign({ view: root.dataset.view }, st);
		return post('view', payload).then(function (d) {
			root.innerHTML = d.html;
			root.style.opacity = '';
			drawCharts(d.charts, root);
			initForm(root);
			if (focusName) {
				var f = $('[data-f="' + focusName + '"]', root);
				if (f) { f.focus(); try { f.setSelectionRange(f.value.length, f.value.length); } catch (e) { /* ignore */ } }
			}
		}).catch(function (e) {
			root.style.opacity = '';
			root.innerHTML = '<div class="gvwr-empty">' + e.message + '</div>';
		});
	}

	function reloadAll() { $$('.gvwr-view').forEach(function (r) { load(r); }); }
	function rootOf(el) { return el.closest('.gvwr-view') || $('.gvwr-view'); }

	/* ------------------------------ فرم ثبت سریع ------------------------------ */
	function grow(t) { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight + 2, 220) + 'px'; }

	function newRow(after, focus) {
		var tpl = $('#gvwr-row-tpl'), box = $('#gvwr-rows');
		if (!tpl || !box) { return null; }
		var wrap = document.createElement('div');
		wrap.innerHTML = tpl.innerHTML;
		var row = wrap.firstElementChild;
		var ref = after || box.lastElementChild;
		if (ref) { // دسته‌ی ردیف قبلی را پیش‌فرض می‌گیرد: ثبت پشت‌سرهم سریع‌تر می‌شود
			var prev = $('[data-k="cat"]', ref);
			if (prev) { $('[data-k="cat"]', row).value = prev.value; }
			ref.after(row);
		} else { box.appendChild(row); }
		if (focus) { $('[data-k="desc"]', row).focus(); }
		return row;
	}

	function initForm(root) {
		if ($('#gvwr-rows', root) && !$('#gvwr-rows', root).children.length) { newRow(null, false); }
		dateLabel();
	}

	function collectRow(row) {
		var g = function (k) { var e = $('[data-k="' + k + '"]', row); return e ? e.value : ''; };
		return { cat: g('cat'), desc: g('desc').trim(), qty: g('qty') || 1, url: g('url').trim(), start: g('start'), end: g('end'), shots: g('shots').split(',').filter(Boolean) };
	}

	function uploadTo(row, files) {
		var more = $('.gvwr-row-more', row), thumbs = $('.gvwr-thumbs', row), hid = $('[data-k="shots"]', row);
		if (more) { more.hidden = false; }
		Array.prototype.forEach.call(files, function (f) {
			if (!/^image\//.test(f.type)) { toast('فقط فایل تصویر مجاز است.', true); return; }
			var ph = document.createElement('span');
			ph.className = 'gvwr-th up';
			thumbs.appendChild(ph);
			var fd = new FormData();
			fd.append('file', f, f.name || 'screenshot.png');
			post('upload', fd).then(function (d) {
				ph.className = 'gvwr-th';
				ph.dataset.id = d.id;
				ph.innerHTML = '<img src="' + d.url + '" alt=""><button type="button" data-act="shot-del">×</button>';
				hid.value = hid.value ? hid.value + ',' + d.id : String(d.id);
			}).catch(function (e) { ph.remove(); toast(e.message, true); });
		});
	}

	function syncShots(row) {
		$('[data-k="shots"]', row).value = $$('.gvwr-th[data-id]', row).map(function (t) { return t.dataset.id; }).join(',');
	}

	document.addEventListener('input', function (e) {
		var t = e.target;
		if (t.matches && t.matches('textarea[data-k="desc"]')) {
			grow(t);
			var row = t.closest('.gvwr-row'), box = $('#gvwr-rows');
			if (box && row && row === box.lastElementChild && t.value.trim()) { newRow(row, false); } // ردیف بعدی خودکار باز می‌شود
		}
		if (t.matches && t.matches('[data-f="q"]')) {
			var root = rootOf(t);
			clearTimeout(root._qt);
			root._qt = setTimeout(function () { load(root, { q: t.value, page: 1 }, 'q'); }, 450);
		}
	});

	document.addEventListener('keydown', function (e) {
		var t = e.target;
		if (t.matches && t.matches('textarea[data-k="desc"]') && e.key === 'Enter' && !e.shiftKey && $('#gvwr-rows') && $('#gvwr-rows').contains(t)) {
			e.preventDefault();
			var row = t.closest('.gvwr-row'), nxt = row.nextElementSibling;
			if (nxt && !$('[data-k="desc"]', nxt).value.trim()) { $('[data-k="desc"]', nxt).focus(); } else { newRow(row, true); }
		}
		if (t.matches && t.matches('.gvwr-rankin') && e.key === 'Enter') {
			e.preventDefault();
			var inputs = $$('.gvwr-rankin'), i = inputs.indexOf(t);
			if (inputs[i + 1]) { inputs[i + 1].focus(); }
		}
	});

	document.addEventListener('paste', function (e) {
		var row = e.target.closest && e.target.closest('.gvwr-row');
		if (!row || !e.clipboardData) { return; }
		var files = [];
		Array.prototype.forEach.call(e.clipboardData.items || [], function (it) { if (it.kind === 'file' && /^image\//.test(it.type)) { files.push(it.getAsFile()); } });
		if (files.length) { e.preventDefault(); uploadTo(row, files); }
	});

	document.addEventListener('change', function (e) {
		var t = e.target;
		if (t.matches('#gvwr-date')) { dateLabel(); return; }
		if (t.matches('[data-k="file"]')) { uploadTo(t.closest('.gvwr-row'), t.files); t.value = ''; return; }
		if (t.matches('[data-f]') && !t.matches('[data-f="q"]')) {
			var root = rootOf(t), patch = { page: 1 };
			patch[t.dataset.f] = t.value;
			load(root, patch);
			return;
		}
		if (t.matches('[data-act="emp-active"]')) {
			var tr = t.closest('tr');
			post('emp_update', { user: tr.dataset.uid, active: t.checked ? 1 : 0 }).then(function (d) { toast(d.msg); }).catch(function (er) { toast(er.message, true); });
		}
	});

	/* ------------------------------ دکمه‌ها (data-act) ------------------------------ */
	var A = {};

	A['modal-close'] = closeModal;
	A.print = function () { window.print(); };
	A.copy = function (b) { var el = document.getElementById(b.dataset.target); if (el && navigator.clipboard) { navigator.clipboard.writeText(el.textContent.trim()).then(function () { toast('کپی شد.'); }); } };

	A.period = function (b) { load(rootOf(b), { period: b.dataset.period, page: 1 }); };
	A.nav = function (b) { if (b.dataset.anchor) { load(rootOf(b), { anchor: b.dataset.anchor, page: 1 }); } };
	A.page = function (b) { var r = rootOf(b); load(r, { page: +b.dataset.page }).then(function () { r.scrollIntoView({ behavior: 'smooth', block: 'start' }); }); };

	A['row-more'] = function (b) { var m = $('.gvwr-row-more', b.closest('.gvwr-row')); m.hidden = !m.hidden; };
	A['row-add'] = function (b) { newRow(b.closest('.gvwr-row'), true); };
	A['row-add-end'] = function () { newRow(null, true); };
	A['row-del'] = function (b) {
		var row = b.closest('.gvwr-row'), box = $('#gvwr-rows');
		row.remove();
		if (box && !box.children.length) { newRow(null, false); }
	};
	A['shot-pick'] = function (b) { $('[data-k="file"]', b.closest('.gvwr-row')).click(); };
	A['shot-del'] = function (b) { var row = b.closest('.gvwr-row'); b.closest('.gvwr-th').remove(); syncShots(row); };

	A['submit-items'] = function (b) {
		var items = $$('#gvwr-rows .gvwr-row').map(collectRow).filter(function (r) { return r.desc || r.shots.length; });
		if (!items.length) { toast('حداقل یک کار را بنویسید.', true); return; }
		busy(b, true);
		var proj = $('#gvwr-project');
		post('save_items', { items: items, date: $('#gvwr-date').value, project: proj ? proj.value : 0 })
			.then(function (d) { toast(d.msg); return load(rootOf(b)); })
			.catch(function (e) { toast(e.message, true); })
			.then(function () { busy(b, false); });
	};

	A['item-edit'] = function (b) {
		post('item_form', { id: b.dataset.id }).then(function (d) { modal(d.html); }).catch(function (e) { toast(e.message, true); });
	};
	A['item-update'] = function (b) {
		var box = $('.gvwr-editrow'), item = collectRow(box);
		item.date = $('[data-k="date"]', box).value;
		busy(b, true);
		post('update_item', { id: box.dataset.id, item: item })
			.then(function (d) { toast(d.msg); closeModal(); reloadAll(); })
			.catch(function (e) { toast(e.message, true); busy(b, false); });
	};
	A['item-del'] = function (b) {
		if (!window.confirm('این گزارش حذف شود؟')) { return; }
		post('delete_item', { id: b.dataset.id }).then(function (d) { toast(d.msg); reloadAll(); }).catch(function (e) { toast(e.message, true); });
	};

	function setStatus(ids, status, note) {
		return post('set_status', { ids: ids, status: status, note: note || '' }).then(function (d) { toast(d.msg); closeModal(); reloadAll(); }).catch(function (e) { toast(e.message, true); });
	}
	A.approve = function (b) { setStatus([b.dataset.id], 'approved'); };
	A['reject-form'] = function (b) { post('reject_form', { id: b.dataset.id }).then(function (d) { modal(d.html); }); };
	A['reject-do'] = function (b) { setStatus([b.dataset.id], 'rejected', $('#gvwr-reject-note').value); };
	A['sel-all'] = function (b) { $$('[data-sel]', rootOf(b)).forEach(function (c) { c.checked = b.checked; }); };
	A['bulk-approve'] = function (b) {
		var ids = $$('[data-sel]:checked', rootOf(b)).map(function (c) { return c.value; });
		if (!ids.length) { toast('ابتدا گزارش‌ها را انتخاب کنید.', true); return; }
		setStatus(ids, 'approved');
	};

	A.day = function (b) {
		var st = stateOf(rootOf(b));
		post('day_items', { date: b.dataset.date, user: st.user }).then(function (d) { modal(d.html); }).catch(function (e) { toast(e.message, true); });
	};

	/* کلمات کلیدی */
	A['kw-add'] = function (b) {
		post('kw_add', { keyword: $('#gvwr-kw-new').value, url: $('#gvwr-kw-url').value, project: b.dataset.project })
			.then(function (d) { toast(d.msg); load(rootOf(b)); }).catch(function (e) { toast(e.message, true); });
	};
	A['kw-import'] = function (b) {
		var f = $('#gvwr-kw-file').files[0];
		if (!f) { toast('ابتدا فایل را انتخاب کنید.', true); return; }
		var fd = new FormData();
		fd.append('file', f);
		fd.append('project', b.dataset.project);
		busy(b, true);
		post('kw_import', fd).then(function (d) { toast(d.msg); return load(rootOf(b)); }).catch(function (e) { toast(e.message, true); }).then(function () { busy(b, false); });
	};
	A['kw-del'] = function (b) {
		if (!window.confirm('این کلمه و تمام تاریخچه‌ی رتبه‌اش حذف شود؟')) { return; }
		post('kw_delete', { id: b.dataset.id }).then(function (d) { toast(d.msg); load(rootOf(b)); });
	};
	A['kw-history'] = function (b) { post('kw_history', { id: b.dataset.id }).then(function (d) { modal(d.html, d.charts); }); };
	A['rank-save'] = function (b) {
		var ranks = {};
		$$('[data-rank]').forEach(function (i) { if (i.value.trim()) { ranks[i.dataset.rank] = i.value.trim(); } });
		busy(b, true);
		post('save_ranks', { ranks: ranks, date: $('#gvwr-rank-date').value })
			.then(function (d) { toast(d.msg); return load(rootOf(b)); })
			.catch(function (e) { toast(e.message, true); })
			.then(function () { busy(b, false); });
	};

	/* افراد، دسته‌بندی، تنظیمات */
	A['person-add'] = function (b) {
		var d = {};
		$$('#gvwr-addperson [data-p]').forEach(function (e) { d[e.dataset.p] = e.value; });
		busy(b, true);
		post('person_add', d).then(function (r) { toast(r.msg); return load(rootOf(b)); }).catch(function (e) { toast(e.message, true); }).then(function () { busy(b, false); });
	};
	A['person-remove'] = function (b) {
		if (!window.confirm('دسترسی گزارش‌کار این فرد حذف شود؟ (یوزر وردپرس و گزارش‌های قبلی حفظ می‌شود)')) { return; }
		post('person_remove', { user: b.closest('tr').dataset.uid }).then(function (r) { toast(r.msg); load(rootOf(b)); });
	};
	A['emp-title'] = function (b) {
		var tr = b.closest('tr');
		post('emp_update', { user: tr.dataset.uid, title: $('.gvwr-title-in', tr).value }).then(function (r) { toast(r.msg); }).catch(function (e) { toast(e.message, true); });
	};
	A['cat-save'] = function (b) {
		var tr = b.closest('tr'), c = { id: tr.dataset.id };
		$$('[data-c]', tr).forEach(function (e) { c[e.dataset.c] = e.type === 'checkbox' ? e.checked : e.value; });
		post('cat_save', { cat: c }).then(function (r) { toast(r.msg); load(rootOf(b)); }).catch(function (e) { toast(e.message, true); });
	};
	A['cat-del'] = function (b) {
		if (!window.confirm('این دسته‌بندی حذف شود؟')) { return; }
		post('cat_delete', { id: b.closest('tr').dataset.id }).then(function (r) { toast(r.msg); load(rootOf(b)); });
	};
	A['settings-save'] = function (b) {
		var s = {};
		$$('[data-s]').forEach(function (e) { s[e.dataset.s] = e.type === 'checkbox' ? (e.checked ? 1 : 0) : e.value; });
		busy(b, true);
		post('settings_save', { settings: s }).then(function (r) { toast(r.msg); }).catch(function (e) { toast(e.message, true); }).then(function () { busy(b, false); });
	};
	A['make-page'] = function (b) { post('make_page').then(function (r) { toast(r.msg); load(rootOf(b)); }).catch(function (e) { toast(e.message, true); }); };
	A['proj-save'] = function (b) {
		var row = b.closest('.gvwr-projrow');
		post('proj_save', { id: row.dataset.id, name: $('[data-pn]', row).value }).then(function (r) { toast(r.msg); load(rootOf(b)); }).catch(function (e) { toast(e.message, true); });
	};
	A['proj-del'] = function (b) {
		if (!window.confirm('این پروژه حذف شود؟')) { return; }
		post('proj_delete', { id: b.closest('.gvwr-projrow').dataset.id }).then(function (r) { toast(r.msg); load(rootOf(b)); }).catch(function (e) { toast(e.message, true); });
	};

	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-act]');
		if (!b || b.type === 'checkbox' && b.dataset.act === 'emp-active') { return; }
		var fn = A[b.dataset.act];
		if (fn) { fn(b, e); }
	});

	/* --------------------------------- شروع --------------------------------- */
	document.addEventListener('DOMContentLoaded', function () {
		$$('.gvwr-view[data-autoload]').forEach(function (r) { load(r); });
	});
})();
