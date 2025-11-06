<!-- da4fa3ad-4166-4c82-82cf-23aa2adacc98 0667e07f-5347-439b-8354-f25ba3917494 -->
# إضافة ميزات متقدمة للدردشة

## الميزات المطلوبة

1. **دعم فتح أكثر من دردشة مع نفس الشخص**
2. **البحث العام عن الدردشات (Global Search)**
3. **البحث داخل محادثة معينة (Search in Conversation)**
4. **التفاعل مع الرسائل (Reactions) مثل واتساب**
5. **ميزات UX المتقدمة:**

   - تمييز النتائج (Highlight results)
   - Scroll-to-result
   - عرض summary قبل القفز للرسالة

## التغييرات المطلوبة

### 1. قاعدة البيانات

#### Migrations جديدة:

- إزالة القيد الفريد من جدول `chats`
- إنشاء جدول `message_reactions` للتفاعلات
- إضافة indexes للبحث السريع (full-text search)

### 2. Backend (Laravel)

#### Models جديدة:

- `MessageReaction` model مع relationships

#### HomeController - Methods جديدة:

- `globalSearch()`: البحث العام في جميع الدردشات والرسائل
- `searchInConversation()`: البحث داخل محادثة محددة
- `addReaction()`: إضافة تفاعل لرسالة
- `removeReaction()`: إزالة تفاعل من رسالة
- `getReactions()`: جلب جميع التفاعلات لرسالة

#### تحديلات على Methods موجودة:

- `startChat()`: إزالة منطق البحث وإنشاء دردشة جديدة دائماً
- `show()`: إضافة `created_at` للدردشات
- `getMessages()`: إضافة التفاعلات مع الرسائل

#### Events جديدة:

- `ReactionAdded`: عند إضافة تفاعل
- `ReactionRemoved`: عند إزالة تفاعل

### 3. Frontend (Vue.js)

#### Components جديدة:

- `SearchDialog.vue`: Dialog للبحث العام
- `ConversationSearch.vue`: Component للبحث داخل المحادثة
- `MessageReactions.vue`: Component لعرض وإدارة التفاعلات
- `SearchResultSummary.vue`: Component لعرض summary قبل القفز

#### تحديثات على Home.vue:

- إضافة state للبحث (global search, conversation search)
- إضافة state للتفاعلات
- إضافة دالة `startNewChatWithPartner()` لإنشاء دردشة جديدة
- إضافة دالة `performGlobalSearch()` للبحث العام
- إضافة دالة `performConversationSearch()` للبحث داخل المحادثة
- إضافة دالة `scrollToMessage()` مع highlight
- إضافة دالة `showSearchSummary()` قبل القفز للرسالة
- إضافة دالة `addReactionToMessage()` لإضافة تفاعل
- إضافة دالة `removeReactionFromMessage()` لإزالة تفاعل
- إضافة دالة `editMessage()` لتعديل رسالة
- إضافة دالة `deleteMessage()` لحذف رسالة
- إضافة دالة `canEditMessage()` للتحقق من إمكانية التعديل (15 دقيقة)
- إضافة دالة `canDeleteMessage()` للتحقق من إمكانية الحذف (30 دقيقة)
- تحديث template لإضافة:
  - زر البحث العام في header
  - زر البحث داخل المحادثة في chat header
  - زر "دردشة جديدة" بجانب اسم الشخص
  - عرض التفاعلات على الرسائل
  - Dialog للبحث العام
  - Search bar داخل المحادثة

## الملفات التي سيتم إنشاؤها/تعديلها

### Migrations:

1. `database/migrations/YYYY_MM_DD_HHMMSS_remove_unique_constraint_from_chats_table.php`
2. `database/migrations/YYYY_MM_DD_HHMMSS_create_message_reactions_table.php`
3. `database/migrations/YYYY_MM_DD_HHMMSS_add_search_indexes.php`

### Models:

4. `app/Models/MessageReaction.php`

### Controllers:

5. `app/Http/Controllers/HomeController.php` (تحديث)

### Requests:

6. `app/Http/Requests/SearchRequest.php`
7. `app/Http/Requests/ReactionRequest.php`

### Events:

8. `app/Events/ReactionAdded.php`
9. `app/Events/ReactionRemoved.php`

### Frontend Components:

10. `resources/js/components/search/SearchDialog.vue`
11. `resources/js/components/search/ConversationSearch.vue`
12. `resources/js/components/messages/MessageReactions.vue`
13. `resources/js/components/search/SearchResultSummary.vue`

### Frontend Pages:

14. `resources/js/pages/Home.vue` (تحديث شامل)

### Routes:

15. `routes/web.php` (إضافة routes جديدة)

## التفاصيل التقنية

### Database Schema

```php
// message_reactions table
Schema::create('message_reactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('message_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('reaction'); // emoji مثل 👍, ❤️, 😂
    $table->timestamps();
    $table->unique(['message_id', 'user_id', 'reaction']);
    $table->index(['message_id']);
    $table->index(['user_id']);
});

// Search indexes
Schema::table('messages', function (Blueprint $table) {
    $table->fullText(['message']); // Full-text search
    $table->index(['chat_id', 'created_at']);
});
```

### API Endpoints

```
GET  /search/global?q={query}              - البحث العام
GET  /search/conversation/{chatId}?q={query} - البحث داخل محادثة
POST /messages/{messageId}/reactions       - إضافة تفاعل
DELETE /messages/{messageId}/reactions/{reactionId} - إزالة تفاعل
GET  /messages/{messageId}/reactions       - جلب التفاعلات
```

### Search Response Format

```json
{
  "results": [
    {
      "type": "chat|message",
      "id": 1,
      "title": "Chat/Message title",
      "preview": "Message preview text...",
      "highlighted_text": "Message with <mark>highlighted</mark> text",
      "chat_id": 1,
      "message_id": 123,
      "created_at": "2025-01-01T00:00:00Z",
      "context": {
        "before": ["message before", "..."],
        "after": ["message after", "..."]
      }
    }
  ],
  "total": 10,
  "summary": "Found 10 results in 3 chats"
}
```

### UX Features Implementation

1. **Highlight Results**: استخدام `<mark>` tag أو custom class للتمييز
2. **Scroll-to-result**: استخدام `scrollIntoView()` مع smooth behavior
3. **Summary Dialog**: عرض summary قبل القفز مع خيارات:

   - عرض context (رسائل قبل وبعد)
   - عدد النتائج في كل دردشة
   - زر للقفز مباشرة

### Reactions Implementation

- دعم Emojis الأساسية: 👍 ❤️ 😂 😮 😢 🙏
- عرض عدد كل تفاعل
- إظهار المستخدمين الذين تفاعلوا
- إمكانية إضافة/إزالة تفاعل بنقرة واحدة
- تحديث real-time عبر broadcasting

## ملاحظات مهمة

- الحفاظ على الكود الموجود وعدم كسره
- استخدام transactions للعمليات المتعددة
- إضافة proper error handling
- تحسين الأداء باستخدام indexes و eager loading
- دعم real-time updates للتفاعلات
- التأكد من security (authorization checks)
- إضافة loading states في الواجهة
- تحسين UX مع animations سلسة

### To-dos

- [ ] إنشاء migration لإزالة القيد الفريد من جدول chats
- [ ] إنشاء migration لجدول message_reactions
- [ ] إنشاء migration لإضافة search indexes
- [ ] إنشاء MessageReaction model مع relationships
- [ ] تحديث Message model لإضافة reactions relationship
- [ ] إضافة globalSearch() method في HomeController
- [ ] إضافة searchInConversation() method في HomeController
- [ ] إضافة methods للتفاعلات (add/remove/get) في HomeController
- [ ] تحديث startChat() لإنشاء دردشة جديدة دائماً
- [ ] تحديث show() و getMessages() لإضافة reactions
- [ ] إنشاء SearchRequest و ReactionRequest
- [ ] إنشاء ReactionAdded و ReactionRemoved events
- [ ] إضافة routes جديدة للبحث والتفاعلات
- [ ] إنشاء SearchDialog component
- [ ] إنشاء ConversationSearch component
- [ ] إنشاء MessageReactions component
- [ ] إنشاء SearchResultSummary component
- [ ] إضافة global search functionality في Home.vue
- [ ] إضافة conversation search functionality في Home.vue
- [ ] إضافة reactions functionality في Home.vue
- [ ] إضافة scroll-to-result مع highlight في Home.vue
- [ ] إضافة summary dialog قبل القفز للرسالة
- [ ] إضافة زر دردشة جديدة بجانب اسم الشخص
- [ ] إضافة real-time updates للتفاعلات عبر broadcasting