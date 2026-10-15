# ماژول مدیریت سطح دسترسی (Access Manager)

## نصب (اگر کل افزونه‌ی به‌روز را نصب نمی‌کنید)
1. پوشه‌ی `access-manager` را داخل `modules/` افزونه بگذارید.
2. در `groot-vision-tools.php`، زیر خط work-report:
   ```php
   require_once GV_TOOLS_PATH . 'modules/access-manager/access-manager.php';
   ```
3. در `admin/admin.php` داخل آرایه‌ی `gv_hub_get_items()` یک آیتم اضافه کنید:
   ```php
   array(
       'title' => 'مدیریت سطح دسترسی کاربران', 'desc' => '...', 'icon' => '🛡️',
       'page' => 'gv-access-manager', 'color' => '#243b6b', 'category' => 'security',
       'status_option' => null, 'status_key' => null,
   ),
   ```
4. افزونه را یک بار غیرفعال/فعال کنید (یا صفحه‌ی ادمین را باز کنید) تا جدول لاگ ساخته شود.

منو: گروت ویژن ← «🛡️ سطح دسترسی».

## اگر قفل شدید
- در wp-config.php: `define( 'GV_AC_DISABLE', true );`
- یا آدرس بازیابی در تنظیمات ← «حالت اضطراری» (۱۵ دقیقه محدودیت‌ها را خاموش می‌کند).
