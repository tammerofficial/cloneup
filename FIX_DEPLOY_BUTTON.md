# 🔧 حل مشكلة زر "Deploy HEAD Commit" في cPanel

## ❌ المشكلة

زر **"Deploy HEAD Commit"** لا يعمل في cPanel Git Version Control.

---

## ✅ الحلول

### 1️⃣ التحقق من المتطلبات الأساسية

حسب [وثائق cPanel](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/)، يجب أن يكون:

- ✅ ملف `.cpanel.yml` موجود في **الجذر** (top-level directory)
- ✅ الملف **checked-in** (موجود في Git commit)
- ✅ المستودع **clean working tree**
- ✅ يوجد **branch واحد على الأقل**

#### التحقق:

```bash
# عبر SSH
cd ~/repositories/whatsapp-clone.git

# التحقق من وجود .cpanel.yml في آخر commit
git show HEAD:.cpanel.yml

# التحقق من branches
git branch -a

# التحقق من حالة المستودع
git status
```

---

### 2️⃣ التحقق من صيغة YAML

ملف `.cpanel.yml` يجب أن يكون بصيغة YAML صحيحة.

#### المشاكل الشائعة:

1. **استخدام emojis في التعليقات** - قد تسبب مشاكل
2. **أوامر `if` معقدة** - قد لا تعمل بشكل صحيح
3. **استخدام `|| true`** - قد يخفي الأخطاء
4. **مسافات غير صحيحة** - YAML حساس للمسافات

#### الحل:

استخدم ملف `.cpanel.yml` المبسط الموجود في المشروع.

---

### 3️⃣ التحقق من Deployment Logs

1. في cPanel:
   - اذهب إلى: `Git Version Control`
   - اضغط على المستودع `whatsapp-clone`
   - اضغط على **Deployment Logs** أو **Activity Log**
   - ابحث عن أخطاء

2. عبر SSH:
   ```bash
   # عرض سجلات النشر
   tail -f ~/repositories/whatsapp-clone.git/hooks/post-receive.log
   ```

---

### 4️⃣ إصلاح ملف `.cpanel.yml`

#### المشاكل في الملف القديم:

- ❌ استخدام `if` statements معقدة
- ❌ استخدام `2>/dev/null || true` (يخفي الأخطاء)
- ❌ استخدام emojis في التعليقات
- ❌ أوامر معقدة قد تفشل

#### الحل:

تم تبسيط الملف في النسخة الجديدة:

```yaml
---
deployment:
  tasks:
    - export DEPLOYPATH=$HOME/public_html
    - export PROJECTPATH=$HOME/whatsapp-clone
    - mkdir -p $PROJECTPATH
    - /bin/cp -R app $PROJECTPATH/
    # ... باقي الأوامر البسيطة
```

**الفرق:**
- ✅ أوامر بسيطة ومباشرة
- ✅ بدون `if` statements معقدة
- ✅ بدون `|| true` (لرؤية الأخطاء)
- ✅ بدون emojis في التعليقات

---

### 5️⃣ إعادة إضافة الملف إلى Git

إذا كان الملف غير موجود في Git:

```bash
# في مشروعك المحلي
git add .cpanel.yml
git commit -m "Add .cpanel.yml for deployment"
git push origin main

# إذا كان لديك remote لـ cPanel
git push cpanel main
```

---

### 6️⃣ التحقق من الصلاحيات

```bash
# عبر SSH
cd ~/repositories/whatsapp-clone.git

# التحقق من صلاحيات الملف
ls -la .cpanel.yml

# يجب أن يكون:
# -rw-r--r-- 1 username username

# إذا لم يكن كذلك:
chmod 644 .cpanel.yml
```

---

### 7️⃣ اختبار الملف يدوياً

```bash
# عبر SSH
cd ~/repositories/whatsapp-clone.git

# اختبار الأوامر يدوياً
export DEPLOYPATH=$HOME/public_html
export PROJECTPATH=$HOME/whatsapp-clone
mkdir -p $PROJECTPATH
/bin/cp -R app $PROJECTPATH/

# إذا نجحت الأوامر، المشكلة في cPanel
# إذا فشلت، المشكلة في الأوامر نفسها
```

---

### 8️⃣ إعادة إنشاء المستودع (حل أخير)

إذا لم تعمل الحلول السابقة:

1. **احذف المستودع في cPanel:**
   - `Git Version Control` → `Delete`

2. **أنشئ مستودع جديد:**
   - `Create` → أدخل التفاصيل
   - تأكد من رفع `.cpanel.yml` أولاً

3. **ارفع الملفات:**
   ```bash
   git add .cpanel.yml
   git commit -m "Add deployment config"
   git push cpanel main
   ```

---

## 🔍 خطوات التشخيص

### 1. التحقق من وجود الملف:

```bash
# في cPanel File Manager
# اذهب إلى: ~/repositories/whatsapp-clone.git
# تأكد من وجود .cpanel.yml
```

### 2. التحقق من محتوى الملف:

```bash
# عبر SSH
cat ~/repositories/whatsapp-clone.git/.cpanel.yml
```

### 3. التحقق من YAML syntax:

استخدم [YAML Validator](https://www.yamllint.com/) للتحقق من صحة الصيغة.

### 4. التحقق من Deployment Logs:

في cPanel → Git Version Control → Deployment Logs

---

## ⚠️ ملاحظات مهمة

1. **لا تستخدم wildcards** مثل `*` في `.cpanel.yml`
   - ❌ `/bin/cp -R * $DEPLOYPATH/`
   - ✅ `/bin/cp -R app $PROJECTPATH/`

2. **لا تستخدم characters غير صالحة** في YAML
   - راجع [YAML Specification](https://yaml.org/spec/)

3. **تأكد من أن الملف في الجذر**
   - ✅ `.cpanel.yml` في الجذر
   - ❌ `.cpanel.yml` في مجلد فرعي

4. **تأكد من checked-in**
   - الملف يجب أن يكون في Git commit
   - ليس فقط في working directory

---

## 📝 قائمة التحقق

قبل الضغط على "Deploy HEAD Commit":

- [ ] ملف `.cpanel.yml` موجود في الجذر
- [ ] الملف موجود في Git (checked-in)
- [ ] صيغة YAML صحيحة (بدون أخطاء)
- [ ] المستودع clean (لا توجد تغييرات غير محفوظة)
- [ ] يوجد branch واحد على الأقل
- [ ] الصلاحيات صحيحة (644)
- [ ] المسارات في الملف صحيحة
- [ ] الأوامر بسيطة ومباشرة (بدون `if` معقدة)

---

## 🆘 إذا استمرت المشكلة

1. **راجع Deployment Logs** في cPanel
2. **تحقق من سجلات Apache/PHP** في cPanel
3. **اتصل بدعم cPanel** مع:
   - نسخة من `.cpanel.yml`
   - Deployment Logs
   - رسالة الخطأ (إن وجدت)

---

## 📚 مراجع

- [cPanel Git Deployment Guide](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/)
- [YAML Specification](https://yaml.org/spec/)

---

**✅ بعد تطبيق هذه الحلول، يجب أن يعمل زر "Deploy HEAD Commit"!**

