## Attachment images not rendering (showing filenames) — Fix Summary

### Problem
- Attachments (images/video/audio/files) uploaded in chat were displayed as filenames instead of rendered media.
- After page refresh, attachments disappeared visually and only filenames showed.
- Additional issues encountered during development on Windows:
  - Temp filenames used `:` which is invalid on Windows → temp store failed.
  - Some Vue errors (URL access, window.open on undefined URL).

### Root Causes
- Temporary file naming incompatible with Windows (`:` in filename) → write failures, jobs couldn’t find content.
- URLs built via storage helper could vary between local/prod and weren’t guaranteed in API serialization.
- Frontend attempted to render before `file_url`/`thumbnail_url` were included on initial fetch.
- Image click handler invoked `window.open` even when URL was undefined.

### Backend Changes

1) Temp storage: Windows-safe filenames and ensured directories
- File: `app/Http/Controllers/HomeController.php`
  - Replaced temp key `attachment:uniqid():time()` with `attachment_<uniqid>_<time>`.
  - Ensured temp directory creation under `storage/app/private/temp/attachments/<Y/m>` before writing.
  - Used `Storage::disk('local')` for temp files (removed Redis temp dependency in fallback).

2) Attachment processing job
- File: `app/Jobs/ProcessMessageAttachment.php`
  - Reads temp file from local storage, determines `file_type` from `mime_type` (image/video/audio/file),
    stores final file on `public` disk, generates thumbnail for images (and placeholder for videos if applicable),
    creates `message_attachments` row, deletes temp file.

3) Model accessors and API serialization
- File: `app/Models/MessageAttachment.php`
  - Accessors: `getFileUrlAttribute()` and `getThumbnailUrlAttribute()` generate URLs via `url('/storage/...')` so they respect `APP_URL`.
  - Added: `protected $appends = ['file_url', 'thumbnail_url'];` to always include URLs in JSON responses (fixes missing URLs after refresh).

4) Validation
- File: `app/Http/Requests/SendMessageRequest.php`
  - Switched to `mimetypes` rule (Laravel 12), limited to supported types, size limit 10MB per file, max 10 files. Ensures either `message` or `attachments` exists.

5) Filesystem config and symlink
- File: `config/filesystems.php`
  - Public disk URL is `env('APP_URL').'/storage'` (default).
- Ensure the symbolic link exists:
  ```bash
  php artisan storage:link
  ```

### Frontend Changes (Vue/Inertia)

1) Image/video/audio rendering
- File: `resources/js/pages/Home.vue`
  - Image block now renders when `attachment.file_type === 'image'` OR `getFileTypeFromMime(attachment.mime_type) === 'image'`.
  - Uses `attachment.thumbnail_url || attachment.file_url` to render `<img>`.
  - Added variants for video `<video>` and audio `<audio>` only when `file_url` exists.
  - Added simple processing placeholders while background job runs.

2) Safer click handler
- Avoided error when `window` is undefined or URL is missing:
  ```vue
  @click="attachment.file_url && typeof window !== 'undefined' && window.open(attachment.file_url, '_blank')"
  ```

3) Local preview fix
- Replaced direct `URL.createObjectURL` usage with a helper function to avoid Vue runtime access warnings.

### Configuration
- `.env`:
  - Set proper base URL for your environment:
    ```env
    APP_URL=http://localhost:8000
    ```
  - In production, set to your domain (e.g. `https://yourdomain.com`).

### Verification Steps
1) Ensure storage link exists:
   ```bash
   php artisan storage:link
   ```
2) Upload an image in any chat and confirm it renders immediately.
3) Refresh the page; images should still render (because `file_url`/`thumbnail_url` are now serialized).
4) Open DevTools → Network tab; confirm media URLs resolve under `${APP_URL}/storage/...`.
5) If using queues asynchronously, start a worker:
   ```bash
   php artisan queue:work
   ```

### Troubleshooting
- Image shows filename only:
  - Confirm API response for `getMessages` contains `file_url`/`thumbnail_url` for attachments.
  - Confirm `APP_URL` is correct and reachable.
  - Confirm `public/storage` link exists and files exist under `public/storage/attachments/...`.

- Image click error (`Cannot read properties of undefined (reading 'open')`):
  - Ensure the updated click handler is present and `attachment.file_url` is defined.

- Windows write failures to temp path:
  - Verify temp keys do not include `:` and that intermediate directories are created.

### Impacted Files (Key Edits)
- `app/Http/Controllers/HomeController.php`
- `app/Jobs/ProcessMessageAttachment.php`
- `app/Models/MessageAttachment.php`
- `app/Http/Requests/SendMessageRequest.php`
- `resources/js/pages/Home.vue`
- `database/migrations/2025_11_06_163055_create_message_attachments_table.php`

### Production Notes
- Set `APP_URL` to your domain.
- Ensure web server serves `public/` and allows `public/storage`.
- Run queue worker for async processing; remove sync job execution once stable.


