# 🔧 حل المشاكل الشائعة في cPanel Deployment

## ❌ المشاكل المحلولة تلقائياً في `.cpanel.yml`

### 1. خطأ قاعدة البيانات: `Database connection [msyql] not configured`

**السبب:** خطأ إملائي في ملف `.env` (msyql بدلاً من mysql)

**الحل التلقائي:**
```yaml
- sed -i 's/DB_CONNECTION=msyql/DB_CONNECTION=mysql/g' .env
- sed -i 's/DB_CONNECTION=msql/DB_CONNECTION=mysql/g' .env
```

---

### 2. ملف `.env` غير موجود

**السبب:** لم يتم إنشاء ملف `.env` على السيرفر

**الحل التلقائي:**
```yaml
- test -f .env || cp .env.example .env
```

**بعد النشر، يجب تعديل `.env` يدوياً:**
```bash
cd ~/whatsapp-clone
nano .env
```

---

### 3. APP_KEY غير موجود

**السبب:** لم يتم توليد APP_KEY

**الحل التلقائي:**
```yaml
- grep -q "APP_KEY=" .env || php artisan key:generate --force
```

---

### 4. مشاكل Cache

**السبب:** Cache قديم أو تالف

**الحل التلقائي:**
```yaml
- php artisan config:clear
- php artisan cache:clear
- php artisan route:clear
- php artisan view:clear
- php artisan config:cache
- php artisan route:cache
- php artisan view:cache
```

---

### 5. مشاكل Storage Link

**السبب:** رابط storage غير موجود أو تالف

**الحل التلقائي:**
```yaml
- cd $DEPLOYPATH
- rm -f storage
- ln -s $PROJECTPATH/storage/app/public storage
```

---

### 6. مشاكل الصلاحيات

**السبب:** صلاحيات خاطئة على المجلدات

**الحل التلقائي:**
```yaml
- chmod -R 775 storage
- chmod -R 775 bootstrap/cache
```

---

## 🔍 خطوات التحقق بعد النشر

### 1. التحقق من ملف `.env`:

```bash
cd ~/whatsapp-clone
cat .env | grep DB_CONNECTION
# يجب أن يظهر: DB_CONNECTION=mysql
```

### 2. التحقق من APP_KEY:

```bash
cat .env | grep APP_KEY
# يجب أن يحتوي على مفتاح (ليس فارغاً)
```

### 3. التحقق من قاعدة البيانات:

```bash
php artisan migrate:status
```

### 4. التحقق من Storage Link:

```bash
ls -la ~/public_html/storage
# يجب أن يكون رابط رمزي
```

---

## ⚙️ إعداد ملف `.env` بعد النشر

بعد النشر التلقائي، يجب تعديل ملف `.env` يدوياً:

```bash
cd ~/whatsapp-clone
nano .env
```

**تأكد من:**

```env
APP_NAME="WhatsApp Clone"
APP_ENV=production
APP_KEY=base64:... (يتم توليده تلقائياً)
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=whats
DB_USERNAME=your_username
DB_PASSWORD=your_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
```

---

## 🚨 حل المشاكل يدوياً

### إذا استمر خطأ قاعدة البيانات:

```bash
cd ~/whatsapp-clone

# 1. تحقق من .env
cat .env | grep DB_

# 2. أصلح الخطأ الإملائي
sed -i 's/msyql/mysql/g' .env
sed -i 's/msql/mysql/g' .env

# 3. نظف cache
php artisan config:clear
php artisan cache:clear

# 4. أعد تحميل config
php artisan config:cache
```

### إذا لم يعمل Storage:

```bash
cd ~/public_html
rm -f storage
ln -s ~/whatsapp-clone/storage/app/public storage
ls -la storage
```

### إذا كانت الصلاحيات خاطئة:

```bash
cd ~/whatsapp-clone
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R username:username storage
chown -R username:username bootstrap/cache
```

---

## 📋 قائمة التحقق بعد النشر

- [ ] ملف `.env` موجود ويحتوي على معلومات صحيحة
- [ ] `DB_CONNECTION=mysql` (بدون أخطاء إملائية)
- [ ] `APP_KEY` موجود ومولّد
- [ ] رابط storage موجود ويعمل
- [ ] الصلاحيات صحيحة (775)
- [ ] Cache تم تنظيفه وإعادة إنشائه
- [ ] قاعدة البيانات متصلة
- [ ] Migrations تم تشغيلها
- [ ] الموقع يعمل بدون أخطاء

---

## 🔄 إعادة النشر

إذا واجهت مشاكل بعد النشر:

1. **في cPanel:**
   - اذهب إلى `Git Version Control`
   - اضغط على `Deploy HEAD Commit` مرة أخرى

2. **أو عبر SSH:**
   ```bash
   cd ~/repositories/whatsapp-clone.git
   git pull
   # ثم نفذ الأوامر يدوياً من .cpanel.yml
   ```

---

## 📞 الدعم

إذا استمرت المشاكل:

1. تحقق من **Deployment Logs** في cPanel
2. تحقق من **Error Logs** في cPanel
3. تحقق من `storage/logs/laravel.log`
4. راجع ملف `FIX_DEPLOY_BUTTON.md`

---

**✅ الآن جميع المشاكل الشائعة يتم حلها تلقائياً!**

