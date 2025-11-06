# 🚀 دليل رفع المشروع على cPanel

## 📋 المتطلبات الأساسية

- PHP 8.2 أو أحدث
- Composer
- Node.js و npm (للبناء)
- MySQL/MariaDB
- mod_rewrite مفعّل في Apache

---

## 📁 هيكل المجلدات في cPanel

عند رفع المشروع على cPanel، يجب أن يكون الهيكل كالتالي:

```
/home/username/
├── public_html/          # هذا هو مجلد public الخاص بك
│   ├── index.php
│   ├── .htaccess
│   ├── build/           # الملفات المبنية من Vite
│   └── storage/         # رابط رمزي إلى storage/app/public
│
├── app/                  # باقي ملفات Laravel
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/              # مجلد storage الحقيقي
├── vendor/
├── .env
├── .htaccess            # لتوجيه الطلبات إلى public_html
└── composer.json
```

---

## 🔧 خطوات الإعداد

### 1️⃣ إعداد Git Repository في cPanel

#### الطريقة المفضلة: استخدام Git Version Control في cPanel (Automatic Deployment)

1. **افتح Git Version Control في cPanel:**
   - اذهب إلى: `cPanel » Home » Files » Git Version Control`

2. **إنشاء مستودع جديد:**
   - اضغط على **Create**
   - **Repository Name:** `whatsapp-clone`
   - **Clone URL:** `https://github.com/username/whatsapp-clone.git`
   - **Repository Root:** اتركه افتراضياً أو اختر `~/repositories`
   - اضغط **Create**

3. **إعداد Automatic Deployment:**
   - بعد إنشاء المستودع، تأكد من وجود ملف `.cpanel.yml` في الجذر
   - cPanel سيقوم تلقائياً بإضافة post-receive hook
   - عند عمل `git push` إلى المستودع، سيتم التنفيذ تلقائياً

4. **إضافة Remote في المشروع المحلي:**
```bash
# في مشروعك المحلي
git remote add cpanel username@yourdomain.com:repositories/whatsapp-clone.git
# أو
git remote add cpanel ssh://username@yourdomain.com:2083/~/repositories/whatsapp-clone.git
```

5. **رفع التغييرات (سيتم النشر تلقائياً):**
```bash
git push cpanel main
# أو
git push cpanel master
```

**✅ الآن كل مرة ترفع فيها تغييرات، سيتم النشر تلقائياً!**

---

#### الطريقة البديلة: رفع يدوي
```bash
# في cPanel File Manager أو عبر SSH
cd ~/public_html
git clone https://github.com/username/whatsapp-clone.git .
```

---

### 2️⃣ إعداد قاعدة البيانات

1. افتح **MySQL Databases** في cPanel
2. أنشئ قاعدة بيانات جديدة
3. أنشئ مستخدم جديد واربطه بقاعدة البيانات
4. امنح الصلاحيات الكاملة للمستخدم

---

### 3️⃣ إعداد ملف `.env`

```bash
# في cPanel File Manager، أنشئ ملف .env في الجذر (ليس في public_html)
cd ~
nano .env
```

أضف المحتوى التالي:

```env
APP_NAME="WhatsApp Clone"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://yourdomain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https

VITE_APP_NAME="${APP_NAME}"
```

**🔑 توليد APP_KEY:**
```bash
php artisan key:generate
```

---

### 4️⃣ تثبيت التبعيات

#### عبر SSH (الطريقة المفضلة):
```bash
cd ~
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

#### عبر cPanel Terminal:
```bash
cd ~
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

---

### 5️⃣ إعداد التخزين (Storage)

```bash
# إنشاء رابط رمزي
cd ~/public_html
php artisan storage:link

# أو يدوياً في cPanel File Manager:
# أنشئ رابط رمزي من storage/app/public إلى public_html/storage
```

**تأكد من الصلاحيات:**
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

---

### 6️⃣ تشغيل Migrations

```bash
php artisan migrate --force
```

---

### 7️⃣ تحسين الأداء

```bash
# تحسين التحميل التلقائي
composer install --optimize-autoloader --no-dev

# تحسين التكوين
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 🔐 إعدادات الأمان

### 1. حماية ملف `.env`
تأكد من أن `.env` غير قابل للوصول من المتصفح:

```apache
# في .htaccess في الجذر
<Files .env>
    Order allow,deny
    Deny from all
</Files>
```

### 2. حماية مجلدات حساسة
```apache
# في .htaccess
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(app|bootstrap|config|database|resources|routes|storage|tests|vendor) - [F,L]
</IfModule>
```

---

## 🌐 إعدادات Apache (.htaccess)

### ملف `.htaccess` في الجذر:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Redirect all requests to public folder
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

### ملف `public/.htaccess` (موجود بالفعل):
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## 🔄 تحديث المشروع من GitHub

### مع Automatic Deployment (الموصى به):
```bash
# في مشروعك المحلي
git add .
git commit -m "Update project"
git push cpanel main
# سيتم النشر تلقائياً! 🎉
```

### تحديث يدوي:
```bash
cd ~/whatsapp-clone
git pull origin main
composer install --optimize-autoloader --no-dev
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## ⚠️ حل المشاكل الشائعة

### 1. خطأ 500 Internal Server Error
- تحقق من ملف `.env` و `APP_KEY`
- تحقق من صلاحيات الملفات: `chmod -R 775 storage bootstrap/cache`
- تحقق من سجلات الأخطاء في `storage/logs/laravel.log`

### 2. خطأ 404 Not Found
- تأكد من تفعيل `mod_rewrite` في Apache
- تحقق من ملف `.htaccess` في `public_html`

### 3. مشاكل في الصور/الملفات
- تأكد من إنشاء رابط `storage:link`
- تحقق من صلاحيات مجلد `storage/app/public`

### 4. مشاكل في Vite Assets
- تأكد من تشغيل `npm run build`
- تحقق من `APP_URL` في `.env`
- تأكد من وجود مجلد `public/build`

---

## 📝 ملاحظات مهمة

1. **لا ترفع مجلد `vendor`** - قم بتثبيته عبر Composer على السيرفر
2. **لا ترفع مجلد `node_modules`** - قم بتثبيته عبر npm على السيرفر
3. **لا ترفع ملف `.env`** - أنشئه يدوياً على السيرفر
4. **احذف مجلد `.git`** بعد الرفع (اختياري)
5. **فعّل SSL** من cPanel للحصول على HTTPS

---

## 🎯 التحقق من الإعداد

بعد اكتمال الإعداد، تحقق من:

- ✅ الموقع يعمل على `https://yourdomain.com`
- ✅ قاعدة البيانات متصلة
- ✅ الصور والملفات تظهر بشكل صحيح
- ✅ لا توجد أخطاء في `storage/logs/laravel.log`
- ✅ Vite assets تعمل بشكل صحيح

---

## 📞 الدعم

إذا واجهت أي مشاكل، تحقق من:
- سجلات Laravel: `storage/logs/laravel.log`
- سجلات Apache في cPanel
- سجلات PHP في cPanel

---

**تم الإعداد بنجاح! 🎉**

