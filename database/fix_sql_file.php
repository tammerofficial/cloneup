<?php

/**
 * 🔧 سكريبت إصلاح ملف SQL
 * 
 * هذا السكريبت يستبدل utf8mb4_0900_ai_ci بـ utf8mb4_unicode_ci
 * في ملف SQL ليكون متوافقاً مع جميع إصدارات MySQL/MariaDB
 * 
 * الاستخدام:
 * php database/fix_sql_file.php input.sql output.sql
 */

if ($argc < 2) {
    echo "❌ الاستخدام: php database/fix_sql_file.php input.sql [output.sql]\n";
    echo "مثال: php database/fix_sql_file.php dump.sql dump_fixed.sql\n";
    exit(1);
}

$inputFile = $argv[1];
$outputFile = $argv[2] ?? str_replace('.sql', '_fixed.sql', $inputFile);

if (!file_exists($inputFile)) {
    echo "❌ الملف غير موجود: $inputFile\n";
    exit(1);
}

echo "🔧 جاري إصلاح ملف SQL...\n";

// قراءة الملف
$content = file_get_contents($inputFile);

// استبدال collation غير المتوافق
$replacements = [
    'utf8mb4_0900_ai_ci' => 'utf8mb4_unicode_ci',
    'utf8mb4_0900_as_ci' => 'utf8mb4_unicode_ci',
    'utf8mb4_0900_as_cs' => 'utf8mb4_unicode_ci',
    'utf8mb4_0900_bin' => 'utf8mb4_unicode_ci',
    'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci' => 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
];

foreach ($replacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

// كتابة الملف المعدل
file_put_contents($outputFile, $content);

echo "✅ تم إصلاح الملف بنجاح!\n";
echo "📁 الملف الأصلي: $inputFile\n";
echo "📁 الملف المعدل: $outputFile\n";
echo "\n💡 يمكنك الآن استيراد الملف المعدل في phpMyAdmin\n";

