-- 🔧 سكريبت إصلاح Collation لقاعدة البيانات
-- استخدم هذا الملف إذا واجهت خطأ: Unknown collation: 'utf8mb4_0900_ai_ci'

-- تغيير collation قاعدة البيانات
ALTER DATABASE `whats` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- أو إذا كنت تريد إنشاء قاعدة بيانات جديدة
-- CREATE DATABASE IF NOT EXISTS `whats` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

