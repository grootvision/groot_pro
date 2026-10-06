<?php
/**
 * Groot Vision Topbar
 * Developed by Groot Vision (grootvision)
 * Website: https://grootvision.com
 */

if (!defined('ABSPATH')) exit;

define('GV_TOPBAR_VERSION', '2.3.0');
define('GV_TOPBAR_OPTION', 'gv_topbar_settings');
define('GV_TOPBAR_SLUG', 'gv-topbar-settings');

// این افزونه به فایل هاب (groot-vision-hub.php) نیاز دارد.
add_action('admin_menu', function () {
    add_submenu_page(
        'groot-vision-hub',
        'نوار اعلان Groot Vision',
        '📢 نوار اعلان',
        'manage_options',
        GV_TOPBAR_SLUG,
        'gv_topbar_settings_page'
    );
});

function gv_topbar_defaults() {
    return array(
        'enabled'      => 1,
        'messages'     => "🔥 تخفیف ویژه امروز|\n🚀 ارسال سریع و مطمئن|\n💎 کیفیت حرفه‌ای، رضایت تضمینی|",
        'position'     => 'top',
        'bg_start'     => '#0e4037',
        'bg_end'       => '#145c4d',
        'text_color'   => '#ffffff',
        'accent_color' => '#4ade80',
        'font_size'    => 15,
        'height'       => 42,
        'speed_type'   => 45,
        'speed_erase'  => 20,
        'pause_time'   => 1400,
        'closable'     => 1,
        'sticky'       => 1,
        'radius'       => 0,
    );
}

function gv_topbar_get_settings() {
    return wp_parse_args(get_option(GV_TOPBAR_OPTION, array()), gv_topbar_defaults());
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'groot-vision-hub_page_' . GV_TOPBAR_SLUG) return;
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_enqueue_script('jquery');
});

function gv_topbar_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['gv_topbar_save']) && check_admin_referer('gv_topbar_save_action', 'gv_topbar_nonce')) {
        $data = array(
            'enabled'      => isset($_POST['enabled']) ? 1 : 0,
            'messages'     => sanitize_textarea_field(wp_unslash($_POST['messages'])),
            'position'     => in_array($_POST['position'], array('top', 'bottom'), true) ? sanitize_text_field($_POST['position']) : 'top',
            'bg_start'     => sanitize_hex_color($_POST['bg_start']),
            'bg_end'       => sanitize_hex_color($_POST['bg_end']),
            'text_color'   => sanitize_hex_color($_POST['text_color']),
            'accent_color' => sanitize_hex_color($_POST['accent_color']),
            'font_size'    => absint($_POST['font_size']),
            'height'       => absint($_POST['height']),
            'speed_type'   => absint($_POST['speed_type']),
            'speed_erase'  => absint($_POST['speed_erase']),
            'pause_time'   => absint($_POST['pause_time']),
            'closable'     => isset($_POST['closable']) ? 1 : 0,
            'sticky'       => isset($_POST['sticky']) ? 1 : 0,
            'radius'       => absint($_POST['radius']),
        );
        update_option(GV_TOPBAR_OPTION, $data);
        echo '<div class="updated notice is-dismissible"><p>✔ تنظیمات با موفقیت ذخیره شد.</p></div>';
    }

    $s = gv_topbar_get_settings();
    ?>
    <div class="wrap gv-wrap" dir="rtl">
        <div class="gv-header">
            <div class="gv-logo">
                <span class="gv-logo-icon"><span class="dashicons dashicons-megaphone"></span></span>
                <div>
                    <h1>Groot Vision</h1>
                    <p>پنل مدیریت نوار اعلان سایت</p>
                </div>
            </div>
            <span class="gv-badge">نسخه <?php echo esc_html(GV_TOPBAR_VERSION); ?></span>
        </div>

        <form method="post" class="gv-form">
            <?php wp_nonce_field('gv_topbar_save_action', 'gv_topbar_nonce'); ?>

            <div class="gv-grid">
                <div class="gv-col">

                    <div class="gv-card">
                        <h2><span class="dashicons dashicons-visibility"></span> وضعیت نمایش</h2>
                        <label class="gv-switch">
                            <input type="checkbox" name="enabled" value="1" <?php checked($s['enabled'], 1); ?>>
                            <span class="gv-slider"></span>
                            <span class="gv-switch-label">فعال بودن نوار اعلان</span>
                        </label>
                    </div>

                    <div class="gv-card">
                        <h2><span class="dashicons dashicons-edit"></span> پیام‌ها</h2>
                        <p class="gv-hint">هر خط یک پیام جدا محسوب می‌شود. برای لینک‌دار کردن یک پیام، آدرس را با علامت <code>|</code> در انتهای خط اضافه کنید. مثال:<br><code>🔥 تخفیف ویژه امروز|https://grootvision.com/sale</code></p>
                        <textarea name="messages" class="gv-textarea" rows="8"><?php echo esc_textarea($s['messages']); ?></textarea>
                    </div>

                    <div class="gv-card">
                        <h2><span class="dashicons dashicons-admin-settings"></span> موقعیت و رفتار</h2>
                        <div class="gv-row">
                            <label>موقعیت نوار</label>
                            <select name="position">
                                <option value="top" <?php selected($s['position'], 'top'); ?>>بالای صفحه</option>
                                <option value="bottom" <?php selected($s['position'], 'bottom'); ?>>پایین صفحه</option>
                            </select>
                        </div>
                        <label class="gv-switch">
                            <input type="checkbox" name="closable" value="1" <?php checked($s['closable'], 1); ?>>
                            <span class="gv-slider"></span>
                            <span class="gv-switch-label">نمایش دکمه بستن (بستن تا پایان جلسه)</span>
                        </label>
                        <label class="gv-switch">
                            <input type="checkbox" name="sticky" value="1" <?php checked($s['sticky'], 1); ?>>
                            <span class="gv-slider"></span>
                            <span class="gv-switch-label">چسبیده باقی ماندن هنگام اسکرول (Sticky)</span>
                        </label>
                    </div>

                    <div class="gv-card">
                        <h2><span class="dashicons dashicons-art"></span> ظاهر و رنگ‌بندی</h2>
                        <div class="gv-row">
                            <label>رنگ شروع گرادینت</label>
                            <input type="text" class="gv-color" name="bg_start" value="<?php echo esc_attr($s['bg_start']); ?>">
                        </div>
                        <div class="gv-row">
                            <label>رنگ پایان گرادینت</label>
                            <input type="text" class="gv-color" name="bg_end" value="<?php echo esc_attr($s['bg_end']); ?>">
                        </div>
                        <div class="gv-row">
                            <label>رنگ متن</label>
                            <input type="text" class="gv-color" name="text_color" value="<?php echo esc_attr($s['text_color']); ?>">
                        </div>
                        <div class="gv-row">
                            <label>رنگ تاکیدی (خط جداکننده و کرسر)</label>
                            <input type="text" class="gv-color" name="accent_color" value="<?php echo esc_attr($s['accent_color']); ?>">
                        </div>
                        <div class="gv-row">
                            <label>اندازه فونت (px)</label>
                            <input type="number" name="font_size" value="<?php echo esc_attr($s['font_size']); ?>" min="10" max="30">
                        </div>
                        <div class="gv-row">
                            <label>ارتفاع نوار (px)</label>
                            <input type="number" name="height" value="<?php echo esc_attr($s['height']); ?>" min="30" max="100">
                        </div>
                        <div class="gv-row">
                            <label>گردی گوشه‌ها (px)</label>
                            <input type="number" name="radius" value="<?php echo esc_attr($s['radius']); ?>" min="0" max="40">
                        </div>
                    </div>

                    <div class="gv-card">
                        <h2><span class="dashicons dashicons-controls-play"></span> سرعت انیمیشن تایپ</h2>
                        <div class="gv-row">
                            <label>سرعت تایپ (میلی‌ثانیه بین حروف)</label>
                            <input type="number" name="speed_type" value="<?php echo esc_attr($s['speed_type']); ?>" min="10" max="200">
                        </div>
                        <div class="gv-row">
                            <label>سرعت پاک شدن</label>
                            <input type="number" name="speed_erase" value="<?php echo esc_attr($s['speed_erase']); ?>" min="5" max="150">
                        </div>
                        <div class="gv-row">
                            <label>مکث بین پیام‌ها (میلی‌ثانیه)</label>
                            <input type="number" name="pause_time" value="<?php echo esc_attr($s['pause_time']); ?>" min="200" max="6000">
                        </div>
                    </div>

                    <button type="submit" name="gv_topbar_save" class="gv-save-btn">💾 ذخیره تنظیمات</button>
                </div>

                <div class="gv-col gv-preview-col">
                    <div class="gv-card gv-sticky">
                        <h2><span class="dashicons dashicons-desktop"></span> پیش‌نمایش زنده</h2>
                        <div id="gv-preview-box">
                            <div id="gv-preview-bar">
                                <span id="gv-preview-text"></span>
                            </div>
                        </div>
                        <p class="gv-hint" style="margin-top:12px">پیش‌نمایش تقریبی است؛ نمای واقعی در سایت مشاهده می‌شود.</p>
                    </div>
                </div>
            </div>
        </form>

        <div class="gv-footer">
            ساخته و توسعه‌یافته توسط <strong>Groot Vision</strong> — <a href="https://grootvision.com" target="_blank" rel="noopener">grootvision.com</a>
        </div>
    </div>

    <style>
        .gv-wrap{max-width:1200px;margin-top:20px;font-family:'Vazirmatn',Tahoma,sans-serif;}
        .gv-wrap *{box-sizing:border-box;}
        .gv-header{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,#0a2e28 0%,#0e4037 45%,#145c4d 100%);color:#fff;padding:24px 30px;border-radius:18px;margin-bottom:26px;box-shadow:0 14px 36px rgba(14,64,55,.32);position:relative;overflow:hidden;}
        .gv-header::after{content:"";position:absolute;left:-60px;top:-80px;width:220px;height:220px;background:radial-gradient(circle,rgba(74,222,128,.28),transparent 70%);pointer-events:none;}
        .gv-logo{display:flex;align-items:center;gap:16px;position:relative;z-index:1;}
        .gv-logo-icon{display:flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:16px;background:rgba(74,222,128,.14);border:1px solid rgba(74,222,128,.4);}
        .gv-logo-icon .dashicons{font-size:30px;width:30px;height:30px;color:#4ade80;}
        .gv-logo h1{margin:0;font-size:23px;font-weight:700;color:#fff;letter-spacing:.3px;}
        .gv-logo p{margin:3px 0 0;font-size:13px;color:#b6d3ca;}
        .gv-badge{position:relative;z-index:1;background:rgba(74,222,128,.14);border:1px solid rgba(74,222,128,.55);color:#4ade80;padding:7px 16px;border-radius:30px;font-size:12px;font-weight:600;}
        .gv-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:22px;align-items:start;}
        .gv-card{background:#fff;border:1px solid #e6ebf0;border-radius:18px;padding:22px 24px;margin-bottom:20px;box-shadow:0 4px 18px rgba(15,23,42,.04);transition:box-shadow .25s,transform .25s;}
        .gv-card:hover{box-shadow:0 10px 28px rgba(15,23,42,.08);}
        .gv-card h2{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;margin:0 0 16px;color:#0f172a;border-bottom:1px solid #eef2f6;padding:0 0 12px;}
        .gv-card h2 .dashicons{color:#145c4d;font-size:18px;width:18px;height:18px;}
        .gv-hint{font-size:12.5px;color:#64748b;margin:0 0 12px;line-height:1.95;}
        .gv-hint code{background:#f1f5f9;color:#0e4037;padding:2px 7px;border-radius:6px;direction:ltr;display:inline-block;}
        .gv-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:12px;}
        .gv-row label{font-size:13px;color:#334155;font-weight:600;flex:1;}
        .gv-row select,.gv-row input[type=number]{border-radius:10px;border:1px solid #d5dde6;padding:6px 12px;width:150px;min-height:36px;background:#f8fafc;transition:border-color .2s,box-shadow .2s;}
        .gv-row select:focus,.gv-row input[type=number]:focus,.gv-textarea:focus{border-color:#145c4d;box-shadow:0 0 0 3px rgba(20,92,77,.15);outline:none;background:#fff;}
        .gv-textarea{width:100%;border-radius:12px;border:1px solid #d5dde6;padding:14px;font-size:14px;line-height:1.9;background:#f8fafc;font-family:'Vazirmatn',Tahoma,sans-serif;transition:border-color .2s,box-shadow .2s;}
        .gv-switch{display:flex;align-items:center;gap:12px;margin-bottom:14px;cursor:pointer;}
        .gv-switch input{display:none;}
        .gv-slider{width:46px;height:25px;background:#cbd5e1;border-radius:25px;position:relative;transition:.28s;flex-shrink:0;}
        .gv-slider::before{content:"";position:absolute;width:19px;height:19px;background:#fff;border-radius:50%;top:3px;right:3px;transition:.28s;box-shadow:0 2px 5px rgba(0,0,0,.28);}
        .gv-switch input:checked + .gv-slider{background:linear-gradient(90deg,#0e4037,#1a7a64);}
        .gv-switch input:checked + .gv-slider::before{right:24px;}
        .gv-switch-label{font-size:13px;color:#334155;}
        .gv-save-btn{width:100%;background:linear-gradient(90deg,#0e4037,#145c4d);color:#fff;border:none;padding:15px 30px;border-radius:14px;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 10px 24px rgba(14,64,55,.3);transition:transform .2s,box-shadow .2s;}
        .gv-save-btn:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(14,64,55,.38);}
        .gv-sticky{position:sticky;top:40px;}
        #gv-preview-box{background:repeating-linear-gradient(45deg,#f8fafc,#f8fafc 10px,#f1f5f9 10px,#f1f5f9 20px);border-radius:14px;padding:22px 16px;border:1px dashed #cbd5e1;}
        #gv-preview-bar{display:flex;align-items:center;justify-content:center;padding:10px 16px;font-size:14px;min-height:46px;box-shadow:0 8px 22px rgba(0,0,0,.18);transition:all .25s;}
        #gv-preview-text{border-left:2px solid #4ade80;padding-left:8px;animation:gvPrevBlink 1s steps(1) infinite;}
        @keyframes gvPrevBlink{50%{border-left-color:transparent;}}
        .gv-footer{text-align:center;color:#94a3b8;font-size:12.5px;margin:34px 0 10px;}
        .gv-footer a{color:#145c4d;text-decoration:none;font-weight:600;}
        .gv-footer a:hover{text-decoration:underline;}
        @media(max-width:960px){.gv-grid{grid-template-columns:1fr;}.gv-sticky{position:static;}}
    </style>

    <script>
    jQuery(function ($) {
        $('.gv-color').wpColorPicker({ change: gvUpdatePreview, clear: gvUpdatePreview });

        function gvUpdatePreview() {
            setTimeout(function () {
                const bg1 = $('input[name=bg_start]').val() || '#0e4037';
                const bg2 = $('input[name=bg_end]').val() || '#145c4d';
                const color = $('input[name=text_color]').val() || '#fff';
                const accent = $('input[name=accent_color]').val() || '#4ade80';
                const fs = $('input[name=font_size]').val() || 15;
                const radius = $('input[name=radius]').val() || 0;
                $('#gv-preview-bar').css({
                    background: 'linear-gradient(90deg,' + bg1 + ',' + bg2 + ')',
                    color: color,
                    fontSize: fs + 'px',
                    borderRadius: radius + 'px'
                });
                $('#gv-preview-text').css('border-left-color', accent);
            }, 50);
        }

        $('#gv-preview-text').text('🔥 پیش‌نمایش نوار اعلان شما');
        gvUpdatePreview();
        $('.gv-form input, .gv-form select').on('input change', gvUpdatePreview);
    });
    </script>
    <?php
}

function gv_topbar_parse_messages($raw) {
    $lines = array_filter(array_map('trim', explode("\n", $raw)));
    $messages = array();
    foreach ($lines as $line) {
        if (strpos($line, '|') !== false) {
            list($text, $url) = array_map('trim', explode('|', $line, 2));
            $messages[] = array('text' => $text, 'url' => $url ? esc_url($url) : '');
        } else {
            $messages[] = array('text' => $line, 'url' => '');
        }
    }
    return $messages;
}

add_action('wp_head', function () {
    $s = gv_topbar_get_settings();
    if (!$s['enabled']) return;
    $position_css = $s['position'] === 'bottom' ? 'bottom:0;' : 'top:0;';
    $sticky_css   = $s['sticky'] ? 'position:sticky;' : 'position:relative;';
    ?>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;700&display=swap');
    #gv-topbar {
        <?php echo $sticky_css; ?>
        <?php echo $position_css; ?>
        z-index: 99999;
        width: 100%;
        min-height: <?php echo esc_attr($s['height']); ?>px;
        background: linear-gradient(90deg, <?php echo esc_attr($s['bg_start']); ?>, <?php echo esc_attr($s['bg_end']); ?>, <?php echo esc_attr($s['bg_start']); ?>);
        background-size: 200% 100%;
        animation: gvBarFlow 12s ease-in-out infinite;
        color: <?php echo esc_attr($s['text_color']); ?>;
        font-family: 'Vazirmatn', sans-serif;
        font-size: <?php echo esc_attr($s['font_size']); ?>px;
        font-weight: 600;
        letter-spacing: .2px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px 52px;
        box-shadow: 0 6px 22px rgba(0,0,0,.22), inset 0 -1px 0 rgba(255,255,255,.08);
        border-radius: <?php echo esc_attr($s['radius']); ?>px;
        box-sizing: border-box;
        direction: rtl;
        overflow: hidden;
    }
    #gv-topbar::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,.14) 50%, transparent 65%);
        transform: translateX(-100%);
        animation: gvBarShine 5s ease-in-out infinite;
        pointer-events: none;
    }
    #gv-topbar::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, <?php echo esc_attr($s['accent_color']); ?>, transparent);
        opacity: .75;
        pointer-events: none;
    }
    #gv-topbar a { color: inherit; text-decoration: none; }
    #gv-text {
        position: relative;
        border-left: 2px solid <?php echo esc_attr($s['accent_color']); ?>;
        padding-left: 8px;
        white-space: nowrap;
        overflow: hidden;
        text-shadow: 0 1px 8px rgba(0,0,0,.25);
        animation: gvCursor .9s steps(1) infinite;
    }
    #gv-topbar-close {
        cursor: pointer;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
        color: <?php echo esc_attr($s['text_color']); ?>;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        font-size: 11px;
        line-height: 1;
        flex-shrink: 0;
        transition: background .2s, transform .2s;
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
    }
    #gv-topbar-close:hover { background: rgba(255,255,255,.28); transform: translateY(-50%) rotate(90deg); }
    @keyframes gvCursor { 50% { border-left-color: transparent; } }
    @keyframes gvBarFlow { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
    @keyframes gvBarShine { 0% { transform: translateX(-100%); } 60%,100% { transform: translateX(100%); } }
    @media (max-width: 600px) { #gv-topbar { padding: 8px 44px; } }
    @media (prefers-reduced-motion: reduce) { #gv-topbar, #gv-topbar::before, #gv-text { animation: none; } }
    </style>
    <?php
});

add_action('wp_body_open', function () {
    $s = gv_topbar_get_settings();
    if (!$s['enabled']) return;

    $messages = gv_topbar_parse_messages($s['messages']);
    if (empty($messages)) return;
    ?>
    <div id="gv-topbar" data-gv-bar>
        <span id="gv-text"></span>
        <?php if ($s['closable']) : ?>
            <button id="gv-topbar-close" aria-label="بستن نوار اعلان">✕</button>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        const messages = <?php echo wp_json_encode($messages); ?>;
        const bar = document.getElementById('gv-topbar');
        if (!bar) return;

        if (sessionStorage.getItem('gv_topbar_closed') === '1') {
            bar.style.display = 'none';
            return;
        }

        const el = document.getElementById('gv-text');
        const closeBtn = document.getElementById('gv-topbar-close');
        let msgIndex = 0, charIndex = 0, paused = false;

        const TYPE_SPEED  = <?php echo (int) $s['speed_type']; ?>;
        const ERASE_SPEED = <?php echo (int) $s['speed_erase']; ?>;
        const PAUSE_TIME  = <?php echo (int) $s['pause_time']; ?>;

        function currentMsg() { return messages[msgIndex]; }

        function type() {
            if (paused) return;
            const text = currentMsg().text;
            if (charIndex < text.length) {
                el.textContent = text.substring(0, charIndex + 1);
                charIndex++;
                setTimeout(type, TYPE_SPEED);
            } else {
                setTimeout(erase, PAUSE_TIME);
            }
        }

        function erase() {
            if (paused) return;
            const text = currentMsg().text;
            if (charIndex > 0) {
                el.textContent = text.substring(0, charIndex - 1);
                charIndex--;
                setTimeout(erase, ERASE_SPEED);
            } else {
                msgIndex = (msgIndex + 1) % messages.length;
                setTimeout(type, 300);
            }
        }

        bar.addEventListener('mouseenter', () => { paused = true; });
        bar.addEventListener('mouseleave', () => { paused = false; type(); });

        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                bar.style.display = 'none';
                sessionStorage.setItem('gv_topbar_closed', '1');
            });
        }

        function refreshClickable() {
            const msg = currentMsg();
            if (msg.url) {
                bar.style.cursor = 'pointer';
                bar.onclick = function (e) {
                    if (e.target === closeBtn) return;
                    window.open(msg.url, '_blank');
                };
            } else {
                bar.style.cursor = 'default';
                bar.onclick = null;
            }
        }
        setInterval(refreshClickable, 500);

        type();
    })();
    </script>
    <?php
}, 5);

add_action('wp_footer', function () {
    if (did_action('wp_body_open')) return;
    $s = gv_topbar_get_settings();
    if (!$s['enabled']) return;
    echo '<script>console.warn("Groot Vision Topbar: قالب شما از wp_body_open پشتیبانی نمی‌کند.");</script>';
});