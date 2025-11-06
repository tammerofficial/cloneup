#!/bin/bash

# 🚀 سكريبت رفع المشروع على cPanel
# استخدم: bash DEPLOY.sh

echo "🚀 بدء عملية رفع المشروع على cPanel..."

# الألوان للرسائل
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# التحقق من Composer
if ! command -v composer &> /dev/null; then
    echo -e "${RED}❌ Composer غير مثبت${NC}"
    exit 1
fi

# التحقق من npm
if ! command -v npm &> /dev/null; then
    echo -e "${RED}❌ npm غير مثبت${NC}"
    exit 1
fi

# التحقق من PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP غير مثبت${NC}"
    exit 1
fi

echo -e "${GREEN}✅ جميع المتطلبات متوفرة${NC}"

# تثبيت تبعيات Composer
echo -e "${YELLOW}📦 تثبيت تبعيات Composer...${NC}"
composer install --optimize-autoloader --no-dev --no-interaction

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ فشل تثبيت تبعيات Composer${NC}"
    exit 1
fi

echo -e "${GREEN}✅ تم تثبيت تبعيات Composer${NC}"

# تثبيت تبعيات npm
echo -e "${YELLOW}📦 تثبيت تبعيات npm...${NC}"
npm install --production

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ فشل تثبيت تبعيات npm${NC}"
    exit 1
fi

echo -e "${GREEN}✅ تم تثبيت تبعيات npm${NC}"

# بناء الأصول
echo -e "${YELLOW}🔨 بناء الأصول (Assets)...${NC}"
npm run build

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ فشل بناء الأصول${NC}"
    exit 1
fi

echo -e "${GREEN}✅ تم بناء الأصول${NC}"

# التحقق من ملف .env
if [ ! -f .env ]; then
    echo -e "${YELLOW}⚠️  ملف .env غير موجود، يرجى إنشاؤه يدوياً${NC}"
else
    echo -e "${GREEN}✅ ملف .env موجود${NC}"
fi

# توليد APP_KEY إذا لم يكن موجوداً
if grep -q "APP_KEY=$" .env 2>/dev/null || ! grep -q "APP_KEY=" .env 2>/dev/null; then
    echo -e "${YELLOW}🔑 توليد APP_KEY...${NC}"
    php artisan key:generate --force
    echo -e "${GREEN}✅ تم توليد APP_KEY${NC}"
fi

# إنشاء رابط storage
echo -e "${YELLOW}🔗 إنشاء رابط storage...${NC}"
php artisan storage:link

# تشغيل migrations
read -p "هل تريد تشغيل migrations؟ (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}🗄️  تشغيل migrations...${NC}"
    php artisan migrate --force
    echo -e "${GREEN}✅ تم تشغيل migrations${NC}"
fi

# تحسين الأداء
echo -e "${YELLOW}⚡ تحسين الأداء...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo -e "${GREEN}✅ تم تحسين الأداء${NC}"

# تعيين الصلاحيات
echo -e "${YELLOW}🔐 تعيين صلاحيات الملفات...${NC}"
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo -e "${GREEN}✅ تم تعيين الصلاحيات${NC}"

echo -e "${GREEN}🎉 تم رفع المشروع بنجاح!${NC}"
echo -e "${YELLOW}📝 تأكد من:${NC}"
echo -e "   1. ملف .env مضبوط بشكل صحيح"
echo -e "   2. قاعدة البيانات متصلة"
echo -e "   3. ملف .htaccess موجود في public_html"
echo -e "   4. رابط storage تم إنشاؤه"

