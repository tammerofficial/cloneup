# 🔧 حل مشكلة Seeder على cPanel

## ❌ المشكلة

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'email_verified_at'
```

**السبب:** السيرفر يستخدم نسخة قديمة من `DatabaseSeeder` أو `UserFactory` التي تحتوي على `email_verified_at`.

---

## ✅ الحل السريع على cPanel

### الطريقة 1: تحديث الملفات يدوياً

```bash
# 1. اذهب إلى مجلد المشروع
cd ~/whatsapp-clone

# 2. اسحب التحديثات من GitHub
git pull origin main

# 3. تأكد من تحديث الملفات
cat database/seeders/DatabaseSeeder.php
# يجب أن يحتوي على: $this->call([UserSeeder::class]);

# 4. شغّل Seeder
php artisan db:seed --class=UserSeeder --force
```

### الطريقة 2: تحديث الملفات يدوياً (إذا لم يعمل git pull)

```bash
cd ~/whatsapp-clone

# تحديث DatabaseSeeder
cat > database/seeders/DatabaseSeeder.php << 'EOF'
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}
EOF

# تحديث UserFactory (إزالة email_verified_at)
sed -i '/email_verified_at/d' database/factories/UserFactory.php

# شغّل Seeder
php artisan db:seed --class=UserSeeder --force
```

---

## 🔍 التحقق من الملفات

### 1. التحقق من DatabaseSeeder:

```bash
cat ~/whatsapp-clone/database/seeders/DatabaseSeeder.php
```

**يجب أن يحتوي على:**
```php
$this->call([
    UserSeeder::class,
]);
```

**يجب ألا يحتوي على:**
```php
User::factory()->create([...]);
```

### 2. التحقق من UserFactory:

```bash
cat ~/whatsapp-clone/database/factories/UserFactory.php | grep email_verified_at
```

**يجب ألا يظهر أي شيء** (لا يوجد `email_verified_at`)

---

## 🚀 الحل التلقائي (عبر .cpanel.yml)

عند النشر عبر `Deploy HEAD Commit`، سيتم:
1. ✅ نسخ الملفات المحدثة
2. ✅ تشغيل migrations
3. ✅ تشغيل UserSeeder تلقائياً

**لكن يجب التأكد من:**
- سحب التحديثات أولاً: `git pull origin main`
- أو استخدام `Update from Remote` في cPanel

---

## 📋 خطوات كاملة على cPanel

```bash
# 1. اذهب إلى مجلد المشروع
cd ~/whatsapp-clone

# 2. اسحب التحديثات
git pull origin main

# 3. تأكد من تحديث الملفات
ls -la database/seeders/
ls -la database/factories/

# 4. نظف cache
php artisan config:clear
php artisan cache:clear

# 5. شغّل Seeder
php artisan db:seed --class=UserSeeder --force
```

---

## ⚠️ إذا استمرت المشكلة

### 1. حذف cache القديم:

```bash
cd ~/whatsapp-clone
rm -rf bootstrap/cache/*
php artisan config:clear
php artisan cache:clear
```

### 2. إعادة تحميل autoload:

```bash
composer dump-autoload
```

### 3. التحقق من vendor:

```bash
# تأكد من تحديث vendor
composer install --no-dev
```

---

## ✅ التحقق من النجاح

بعد تشغيل Seeder:

```bash
# التحقق من المستخدمين
php artisan tinker
>>> User::count()
>>> User::pluck('email')
```

يجب أن يظهر:
- 4 مستخدمين محددين (admin, ahmed, sara, khalid)
- 10 مستخدمين إضافيين (من Factory)

---

**✅ بعد تطبيق هذه الخطوات، يجب أن يعمل Seeder بدون مشاكل!**

