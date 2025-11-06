# 🚀 دليل Git Deployment التلقائي على cPanel

## 📋 نظرة عامة

هذا المشروع مُعد للعمل مع **Automatic/Push Deployment** في cPanel. عند رفع التغييرات على GitHub، سيتم نشرها تلقائياً على السيرفر.

---

## ⚙️ كيفية عمل Automatic Deployment

### المخطط:

```
Local Computer          GitHub          cPanel Repository          Production
     │                    │                    │                        │
     │  git push origin   │                    │                        │
     ├───────────────────>│                    │                        │
     │                    │                    │                        │
     │                    │  git push cpanel   │                        │
     ├────────────────────────────────────────>│                        │
     │                    │                    │                        │
     │                    │                    │  .cpanel.yml runs      │
     │                    │                    ├───────────────────────>│
     │                    │                    │  (Automatic)          │
     │                    │                    │                        │
     │                    │                    │  ✅ Deployed!         │
```

---

## 🔧 خطوات الإعداد الأولي

### 1️⃣ إعداد Git Repository في cPanel

1. **افتح cPanel:**
   - اذهب إلى: `cPanel » Home » Files » Git Version Control`

2. **إنشاء مستودع جديد:**
   - اضغط على **Create**
   - **Repository Name:** `whatsapp-clone`
   - **Clone URL:** `https://github.com/username/whatsapp-clone.git`
   - **Repository Root:** `~/repositories` (افتراضي)
   - اضغط **Create**

3. **ملاحظة مهمة:**
   - cPanel سيقوم تلقائياً بإضافة **post-receive hook**
   - هذا الـ hook يقرأ ملف `.cpanel.yml` وينفذ الأوامر تلقائياً

---

### 2️⃣ إضافة Remote في المشروع المحلي

```bash
# في مجلد مشروعك المحلي
cd whatsapp-clone

# إضافة remote لـ cPanel
git remote add cpanel username@yourdomain.com:repositories/whatsapp-clone.git

# أو إذا كان لديك SSH مخصص
git remote add cpanel ssh://username@yourdomain.com:2083/~/repositories/whatsapp-clone.git

# التحقق من الـ remotes
git remote -v
```

**النتيجة المتوقعة:**
```
origin    https://github.com/username/whatsapp-clone.git (fetch)
origin    https://github.com/username/whatsapp-clone.git (push)
cpanel    username@yourdomain.com:repositories/whatsapp-clone.git (fetch)
cpanel    username@yourdomain.com:repositories/whatsapp-clone.git (push)
```

---

### 3️⃣ رفع المشروع لأول مرة

```bash
# رفع إلى GitHub (اختياري)
git push origin main

# رفع إلى cPanel (سيبدأ النشر التلقائي)
git push cpanel main
```

**ما يحدث تلقائياً:**
1. ✅ الملفات تُرفع إلى cPanel repository
2. ✅ post-receive hook يتم تفعيله
3. ✅ ملف `.cpanel.yml` يُقرأ
4. ✅ الأوامر تُنفذ تلقائياً:
   - نسخ الملفات إلى `~/whatsapp-clone`
   - نسخ `public/*` إلى `~/public_html`
   - تثبيت Composer dependencies
   - تثبيت npm dependencies
   - بناء Assets (Vite)
   - إنشاء storage link
   - تحسين الأداء (Cache)

---

## 🔄 سير العمل اليومي

### عند إجراء تغييرات:

```bash
# 1. إضافة التغييرات
git add .

# 2. عمل commit
git commit -m "Add new feature"

# 3. رفع إلى GitHub
git push origin main

# 4. رفع إلى cPanel (سيتم النشر تلقائياً)
git push cpanel main
```

**🎉 سيتم النشر تلقائياً دون أي تدخل يدوي!**

---

## 📝 ملف `.cpanel.yml`

هذا الملف موجود في الجذر ويحتوي على أوامر النشر:

```yaml
deployment:
  tasks:
    - export DEPLOYPATH=$HOME/public_html
    - export PROJECTPATH=$HOME/whatsapp-clone
    # ... باقي الأوامر
```

**ملاحظات مهمة:**
- ✅ يجب أن يكون الملف في **الجذر** (root) للمستودع
- ✅ يجب أن يكون بصيغة YAML صحيحة
- ✅ لا تستخدم wildcards مثل `*` (قد يرفع ملفات حساسة)
- ✅ الأوامر تُنفذ تلقائياً عند `git push`

---

## 🔍 التحقق من النشر

### 1. في cPanel:
- اذهب إلى: `Git Version Control`
- اضغط على المستودع `whatsapp-clone`
- تحقق من **Deployment Logs** أو **Activity Log**

### 2. عبر SSH:
```bash
# التحقق من آخر commit
cd ~/repositories/whatsapp-clone.git
git log -1

# التحقق من الملفات في public_html
ls -la ~/public_html

# التحقق من الملفات في مجلد المشروع
ls -la ~/whatsapp-clone
```

### 3. في المتصفح:
- افتح موقعك: `https://yourdomain.com`
- تحقق من أن التغييرات ظهرت

---

## ⚠️ حل المشاكل

### المشكلة: النشر لا يعمل تلقائياً

**الحل:**
1. تحقق من وجود ملف `.cpanel.yml` في الجذر
2. تحقق من صحة صيغة YAML
3. تحقق من **Deployment Logs** في cPanel
4. تأكد من أن post-receive hook موجود:
   ```bash
   ls -la ~/repositories/whatsapp-clone.git/hooks/post-receive
   ```

### المشكلة: خطأ في الأوامر

**الحل:**
1. تحقق من **Deployment Logs** في cPanel
2. تأكد من أن جميع المسارات صحيحة
3. تأكد من وجود Composer و npm على السيرفر
4. تحقق من الصلاحيات:
   ```bash
   chmod -R 775 ~/whatsapp-clone/storage
   chmod -R 775 ~/whatsapp-clone/bootstrap/cache
   ```

### المشكلة: الملفات لا تظهر في public_html

**الحل:**
1. تحقق من أن الأوامر في `.cpanel.yml` صحيحة
2. تحقق من Deployment Logs
3. تأكد من أن `$DEPLOYPATH` يشير إلى `~/public_html`

---

## 🎯 أفضل الممارسات

1. **اختبار قبل النشر:**
   ```bash
   # اختبر التغييرات محلياً أولاً
   npm run build
   php artisan serve
   ```

2. **استخدم branches:**
   ```bash
   # للتطوير
   git checkout -b develop
   git push cpanel develop
   
   # للإنتاج
   git checkout main
   git push cpanel main
   ```

3. **احفظ ملف `.env` خارج Git:**
   - ✅ موجود في `.gitignore`
   - ✅ أنشئه يدوياً على السيرفر

4. **راقب Deployment Logs:**
   - تحقق من السجلات بعد كل نشر
   - تأكد من عدم وجود أخطاء

---

## 📚 مراجع إضافية

- [cPanel Git Version Control Documentation](https://docs.cpanel.net/cpanel/files/git-version-control/)
- [cPanel Deployment Guide](https://docs.cpanel.net/cpanel/files/git-version-control/guide-to-git-deployment/)

---

## ✅ قائمة التحقق

قبل النشر، تأكد من:

- [ ] ملف `.cpanel.yml` موجود في الجذر
- [ ] ملف `.env` موجود على السيرفر
- [ ] قاعدة البيانات مُعدة
- [ ] Composer و npm مثبتان على السيرفر
- [ ] الصلاحيات مضبوطة بشكل صحيح
- [ ] تم اختبار التغييرات محلياً

---

**🎉 الآن أنت جاهز للنشر التلقائي!**

كل ما عليك هو `git push cpanel main` وسيتم النشر تلقائياً! 🚀

