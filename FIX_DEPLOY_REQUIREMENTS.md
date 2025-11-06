# 🔧 حل مشكلة "The system cannot deploy" في cPanel

## ❌ رسالة الخطأ

```
The system cannot deploy

For deployment, ensure that your repository meets the following requirements:

A valid .cpanel.yml file exists. For more information, read our documentation.

No uncommitted changes exist on the checked-out branch.
```

---

## ✅ الحلول

### 1️⃣ التأكد من أن `.cpanel.yml` موجود في Git

#### في المشروع المحلي:

```bash
# التحقق من وجود الملف في Git
git ls-files .cpanel.yml

# إذا لم يظهر شيء، أضف الملف:
git add .cpanel.yml
git commit -m "Add .cpanel.yml for deployment"
git push origin main
```

#### التحقق من أن الملف موجود في آخر commit:

```bash
git show HEAD:.cpanel.yml
```

إذا ظهر محتوى الملف، فهو موجود في Git ✅

---

### 2️⃣ رفع الملف إلى cPanel Repository

#### إذا كان لديك remote لـ cPanel:

```bash
# إضافة remote (إذا لم يكن موجوداً)
git remote add cpanel username@yourdomain.com:repositories/whatsapp-clone.git

# رفع الملف
git push cpanel main
```

#### أو عبر cPanel:

1. اذهب إلى: `Git Version Control`
2. اضغط على `Update from Remote` (إذا كان لديك remote)
3. أو استخدم `Pull or Deploy` → `Pull`

---

### 3️⃣ التأكد من عدم وجود Uncommitted Changes

#### في cPanel (عبر SSH):

```bash
cd ~/repositories/whatsapp-clone.git

# التحقق من حالة المستودع
git status

# إذا كان هناك تغييرات غير محفوظة:
git reset --hard HEAD
git clean -fd
```

#### في المشروع المحلي:

```bash
# التحقق من حالة المشروع
git status

# إذا كان هناك تغييرات غير محفوظة:
git add .
git commit -m "Commit changes"
git push origin main
```

---

### 4️⃣ التحقق من صحة ملف `.cpanel.yml`

#### التحقق من صيغة YAML:

استخدم [YAML Validator](https://www.yamllint.com/) للتحقق من صحة الملف.

#### التحقق من المحتوى:

```bash
# في المشروع المحلي
cat .cpanel.yml

# يجب أن يبدأ بـ:
# ---
# deployment:
#   tasks:
```

---

### 5️⃣ إعادة إنشاء المستودع في cPanel (حل أخير)

إذا لم تعمل الحلول السابقة:

1. **احذف المستودع في cPanel:**
   - `Git Version Control` → `Delete`

2. **أنشئ مستودع جديد:**
   - `Create`
   - **Repository Name:** `whatsapp-clone`
   - **Clone URL:** `https://github.com/username/whatsapp-clone.git`
   - **Repository Root:** `~/repositories`

3. **تأكد من رفع `.cpanel.yml` أولاً:**
   ```bash
   # في المشروع المحلي
   git add .cpanel.yml
   git commit -m "Add .cpanel.yml"
   git push origin main
   ```

4. **في cPanel:**
   - اضغط على `Update from Remote`
   - ثم `Deploy HEAD Commit`

---

## 🔍 خطوات التشخيص

### 1. التحقق من وجود الملف في cPanel:

```bash
# عبر SSH
cd ~/repositories/whatsapp-clone.git
ls -la .cpanel.yml

# أو
git show HEAD:.cpanel.yml
```

### 2. التحقق من حالة المستودع:

```bash
cd ~/repositories/whatsapp-clone.git
git status

# يجب أن يظهر:
# On branch main
# nothing to commit, working tree clean
```

### 3. التحقق من آخر commit:

```bash
cd ~/repositories/whatsapp-clone.git
git log -1 --oneline

# يجب أن يحتوي على .cpanel.yml
git show HEAD --name-only | grep .cpanel.yml
```

---

## 📋 قائمة التحقق

قبل الضغط على "Deploy HEAD Commit":

- [ ] ملف `.cpanel.yml` موجود في الجذر (root)
- [ ] الملف موجود في Git (git ls-files .cpanel.yml)
- [ ] الملف موجود في آخر commit (git show HEAD:.cpanel.yml)
- [ ] الملف موجود في cPanel repository
- [ ] لا توجد uncommitted changes (git status clean)
- [ ] صيغة YAML صحيحة
- [ ] الملف تم رفعه إلى cPanel (git push cpanel main)

---

## 🚀 الخطوات السريعة

### في المشروع المحلي:

```bash
# 1. تأكد من أن الملف موجود
git add .cpanel.yml
git commit -m "Add .cpanel.yml for deployment"
git push origin main

# 2. إذا كان لديك remote لـ cPanel
git push cpanel main
```

### في cPanel:

1. `Git Version Control` → `Update from Remote`
2. `Deploy HEAD Commit`

---

## ⚠️ ملاحظات مهمة

1. **ملف `.cpanel.yml` يجب أن يكون في الجذر:**
   - ✅ `.cpanel.yml` (في الجذر)
   - ❌ `config/.cpanel.yml` (في مجلد فرعي)

2. **الملف يجب أن يكون committed:**
   - ✅ موجود في Git commit
   - ❌ فقط في working directory

3. **لا يجب أن يكون هناك uncommitted changes:**
   - ✅ `git status` نظيف
   - ❌ `git status` يظهر تغييرات

---

## 🆘 إذا استمرت المشكلة

1. **تحقق من Deployment Logs** في cPanel
2. **تحقق من Error Logs** في cPanel
3. **اتصل بدعم cPanel** مع:
   - نسخة من `.cpanel.yml`
   - رسالة الخطأ الكاملة
   - نتائج `git status` و `git log`

---

**✅ بعد تطبيق هذه الخطوات، يجب أن يعمل "Deploy HEAD Commit"!**

