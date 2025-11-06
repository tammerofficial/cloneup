<?php

namespace App\Http\Controllers;

use App\Events\ChatStarted;
use App\Events\MessageDeleted;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\ReactionAdded;
use App\Events\ReactionRemoved;
use App\Events\UserStatusChange;
use App\Http\Requests\SendMessageRequest;
use App\Jobs\ProcessMessageAttachment;
use App\MessageStatus;
use App\Models\Chat;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Models\UserHasContact;
use App\UserStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function show()
    {
        $contacts = UserHasContact::query()
            ->where('user_id', Auth::id())
            ->join('users', 'users.id', 'user_has_contacts.contact_id')
            ->select(['users.name', 'users.email', 'users.is_active', 'users.about', 'users.last_seen'])
            ->orderBy('users.name')
            ->get()
            ->each(function ($item, $index) {
                $item->id = ++$index;
            });

        $chats = Chat::query()
            ->where('user_one', Auth::id())
            ->orWhere('user_two', Auth::id())
            ->get()
            ->map(function ($chat) {
                $partnerId = $chat->user_one === Auth::id() ? $chat->user_two : $chat->user_one;
                $chat->partner = User::select(['name', 'email', 'id'])->find($partnerId);

                $lastMessage = Message::query()
                    ->where('chat_id', $chat->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $chat->last_message = $lastMessage?->message;
                $chat->last_message_created_at = $lastMessage?->created_at;
                $chat->created_at = $chat->created_at; // Add created_at for display
                $chat->unread_messages = Message::query()
                    ->where('chat_id', $chat->id)
                    ->where('user_id', '!=', Auth::id())
                    ->where('status', MessageStatus::Delivered)
                    ->count();

                return $chat;
            })
            ->sortByDesc('last_message_created_at')
            ->values();

        Auth::user()->update([
            'is_active' => true
        ]);

        broadcast(new UserStatusChange(Auth::id(), true))->toOthers();

        // Get all users except the current user
        $allUsers = User::query()
            ->where('id', '!=', Auth::id())
            ->select(['id', 'name', 'email', 'is_active', 'about', 'last_seen'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Home', [
            'contacts' => $contacts,
            'chats' => $chats,
            'allUsers' => $allUsers
        ]);
    }

    public function storeContact(Request $request)
    {
        $validated = $request->validate([
            'contact_email' => 'required|string|exists:users,email'
        ]);

        if ($validated['contact_email'] === Auth::user()->email)
            return response()->json(['message' => 'The selected contact email is invalid.'], 422);

        $contact = User::query()
            ->where('email', $validated['contact_email'])
            ->first();

        $alreadyHasContact = UserHasContact::query()
            ->where('user_id', Auth::id())
            ->where('contact_id', $contact['id'])
            ->exists();

        if ($alreadyHasContact)
            return response()->json(['message' => "You already have {$contact->name} in your contact list."], 409);

        UserHasContact::query()
            ->create([
                'user_id' => Auth::id(),
                'contact_id' => $contact['id']
            ]);

        return response()->json(['message' => 'Contact added successfully.'], 201);
    }

    public function startChat(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|exists:users,email'
        ]);

        $partner = User::query()
            ->where('email', $validated['email'])
            ->first();

        // Add to contacts if not already added
        $hasContact = UserHasContact::query()
            ->where('user_id', Auth::id())
            ->where('contact_id', $partner['id'])
            ->exists();

        if (!$hasContact) {
            UserHasContact::query()
                ->create([
                    'user_id' => Auth::id(),
                    'contact_id' => $partner['id']
                ]);
        }

        // Always create a new chat (multiple chats with same person allowed)
        $chat = Chat::create([
            'user_one' => Auth::id(),
            'user_two' => $partner['id']
        ]);

        broadcast(new ChatStarted($chat))->toOthers();

        return response()->json(['chat_id' => $chat->id, 'created' => true]);
    }

    public function getMessages(Request $request, int $id)
    {
        try {
            $chat = Chat::query()
                ->findOrFail($id);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Chat not found.'], 404);
        }

        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();

        if (!$canAccessChat)
            return response()->json(['message' => 'You are not authorized to access this chat.'], 403);

        $parnterId = $chat->user_one === Auth::id() ? $chat->user_two : $chat->user_one;

        $messages = Message::query()
            ->where('chat_id', $chat->id)
            ->with(['attachments', 'reactions.user'])
            ->select(['messages.user_id', 'messages.created_at', 'messages.message', 'messages.status', 'messages.id', 'messages.is_edited', 'messages.is_deleted', 'messages.deleted_at'])
            ->orderBy('messages.created_at', 'desc')
            ->limit(20)
            ->offset($request->query('offset', 0))
            ->get()
            ->map(function ($message) {
                $message->attachments = $message->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_type' => $attachment->file_type,
                        'file_size' => $attachment->file_size,
                        'mime_type' => $attachment->mime_type,
                        'file_url' => $attachment->file_url,
                        'thumbnail_url' => $attachment->thumbnail_url,
                        'duration' => $attachment->duration,
                    ];
                });
                
                // Format reactions
                $message->reactions = $message->reactions->groupBy('reaction')->map(function ($reactions, $reaction) {
                    return [
                        'reaction' => $reaction,
                        'count' => $reactions->count(),
                        'users' => $reactions->map(function ($r) {
                            return [
                                'id' => $r->user->id,
                                'name' => $r->user->name,
                                'reaction_id' => $r->id, // Add reaction ID for removal
                            ];
                        })->values(),
                    ];
                })->values();
                
                return $message;
            })
            ->sortBy('created_at')
            ->values();


        $partnerData = User::query()
            ->where('id', $parnterId)
            ->select(['id', 'name', 'email', 'is_active', 'last_seen'])
            ->first();

        return response()->json(['messages' => $messages, 'partner' => $partnerData, 'chat_id' => $chat->id]);
    }

    public function sendMessage(SendMessageRequest $request)
    {
        $validated = $request->validated();

        $chat = Chat::query()->find($validated['chat_id']);

        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();

        if (!$canAccessChat)
            return response()->json(['message' => 'You are not authorized to access this chat.'], 403);

        // Create message
        $message = Message::query()
            ->create([
                'user_id' => Auth::id(),
                'message' => $validated['message'] ?? '',
                'chat_id' => $chat->id,
                'status' => MessageStatus::Delivered
            ]);

        // Process attachments if any
        $attachmentsData = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                // Generate unique key for temporary storage (avoid : in filename for Windows compatibility)
                $tempKey = 'attachment_' . uniqid() . '_' . time();
                
                // Store file temporarily in file storage
                $tempPath = 'temp/attachments/' . date('Y/m') . '/' . $tempKey;
                $fileContent = file_get_contents($file->getRealPath());
                
                // Ensure directory exists
                $fullDirPath = storage_path('app/private/temp/attachments/' . date('Y/m'));
                if (!is_dir($fullDirPath)) {
                    mkdir($fullDirPath, 0755, true);
                }
                
                Storage::disk('local')->put($tempPath, $fileContent);
                $tempKey = $tempPath; // Use path for file storage
                
                Log::info("Stored temporary file: {$tempPath}, Size: " . strlen($fileContent));
                
                // Prepare file data
                $fileData = [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'duration' => null, // Can be extracted from video/audio files later
                ];
                
                // Dispatch job to process attachment in background
                Log::info("Dispatching job for message: {$message->id}, tempKey: {$tempKey}");
                
                // For now, process synchronously to ensure it works
                // TODO: Change to async when queue worker is running
                try {
                    $job = new ProcessMessageAttachment($message->id, $tempKey, $fileData);
                    $job->handle();
                    Log::info("Job processed successfully for message: {$message->id}");
                } catch (\Exception $e) {
                    Log::error("Error processing attachment job: " . $e->getMessage());
                    // Still dispatch to queue for retry
                    ProcessMessageAttachment::dispatch($message->id, $tempKey, $fileData);
                }
                
                // Add temporary attachment info for immediate response
                $attachmentsData[] = [
                    'temp_id' => uniqid(),
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $this->getFileTypeFromMime($file->getMimeType()),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'processing' => true,
                ];
            }
        }

        // Reload message with attachments if they were processed
        $message->load('attachments');
        
        $messageData = [
            'id' => $message->id,
            'message' => $message->message,
            'created_at' => $message->created_at->toISOString(),
            'status' => $message->status,
            'user_id' => $message->user_id,
            'chat_id' => $message->chat_id,
            'attachments' => $message->attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                    'file_url' => $attachment->file_url,
                    'thumbnail_url' => $attachment->thumbnail_url,
                    'duration' => $attachment->duration,
                ];
            })->toArray(),
        ];

        broadcast(new MessageSent($messageData))->toOthers();

        return response()->json(['message' => $messageData]);
    }

    /**
     * Get file type from mime type.
     */
    private function getFileTypeFromMime(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        
        return 'file';
    }

    public function updateUserStatus(Request $request)
    {
        $validated = $request->validate([
            'active' => 'required|boolean'
        ]);

        User::query()
            ->find(Auth::id())
            ->update([
                'is_active' => $validated['active']
            ]);

        broadcast(new UserStatusChange(Auth::id(), $validated['active']))->toOthers();
    }

    public function updateMessageStatus(Request $request)
    {
        $validated = $request->validate([
            'message_ids' => 'array|required|min:1',
            'message_ids.*' => 'required|numeric|exists:messages,id'
        ]);

        foreach ($validated['message_ids'] as $messageId) {
            $message = Message::find($messageId);
            $user = User::find($message->user_id);
            $chat = Chat::find($message->chat_id);

            $canAccessChat = ($chat->user_one === Auth::id() || $chat->user_two === Auth::id()) && $user->id !== Auth::id();

            if (!$canAccessChat)
                return response()->json(['message' => 'You are not authorized to do this action.'], 403);

            $message->update([
                'status' => MessageStatus::Read
            ]);

            broadcast(new MessageRead($message->fresh()))->toOthers();
        }
    }

    /**
     * Get attachment file.
     */
    public function getAttachment(int $attachmentId)
    {
        $attachment = \App\Models\MessageAttachment::with('message')->findOrFail($attachmentId);
        
        $message = $attachment->message;
        $chat = Chat::find($message->chat_id);
        
        // Check if user has access to this chat
        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();
        
        if (!$canAccessChat) {
            return response()->json(['message' => 'You are not authorized to access this file.'], 403);
        }
        
        $filePath = storage_path('app/public/' . $attachment->file_path);
        
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        
        return response()->download($filePath, $attachment->file_name);
    }

    /**
     * Global search across all chats and messages.
     */
    public function globalSearch(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:255',
        ]);

        $query = $validated['q'];
        $results = collect();

        // Search in messages
        $messages = Message::query()
            ->where(function ($q) use ($query) {
                $q->where('message', 'LIKE', "%{$query}%")
                  ->where('is_deleted', false);
            })
            ->with(['chat', 'user'])
            ->whereHas('chat', function ($q) {
                $q->where('user_one', Auth::id())
                  ->orWhere('user_two', Auth::id());
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        foreach ($messages as $message) {
            $chat = $message->chat;
            $partnerId = $chat->user_one === Auth::id() ? $chat->user_two : $chat->user_one;
            $partner = User::find($partnerId);

            // Get context (messages before and after)
            $contextBefore = Message::query()
                ->where('chat_id', $chat->id)
                ->where('created_at', '<', $message->created_at)
                ->orderBy('created_at', 'desc')
                ->limit(2)
                ->pluck('message')
                ->reverse()
                ->values()
                ->toArray();

            $contextAfter = Message::query()
                ->where('chat_id', $chat->id)
                ->where('created_at', '>', $message->created_at)
                ->orderBy('created_at', 'asc')
                ->limit(2)
                ->pluck('message')
                ->values()
                ->toArray();

            // Highlight search term
            $highlightedText = preg_replace(
                '/(' . preg_quote($query, '/') . ')/i',
                '<mark>$1</mark>',
                $message->message
            );

            $results->push([
                'type' => 'message',
                'id' => $message->id,
                'title' => $partner->name,
                'preview' => strlen($message->message) > 100 
                    ? substr($message->message, 0, 100) . '...' 
                    : $message->message,
                'highlighted_text' => $highlightedText,
                'chat_id' => $chat->id,
                'message_id' => $message->id,
                'created_at' => $message->created_at->toISOString(),
                'context' => [
                    'before' => $contextBefore,
                    'after' => $contextAfter,
                ],
            ]);
        }

        // Group results by chat
        $chatsCount = $results->pluck('chat_id')->unique()->count();

        return response()->json([
            'results' => $results->values(),
            'total' => $results->count(),
            'summary' => "Found {$results->count()} results in {$chatsCount} chats",
        ]);
    }

    /**
     * Search within a specific conversation.
     */
    public function searchInConversation(Request $request, int $chatId)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:255',
        ]);

        $query = $validated['q'];

        // Verify chat access
        $chat = Chat::findOrFail($chatId);
        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();

        if (!$canAccessChat) {
            return response()->json(['message' => 'You are not authorized to access this chat.'], 403);
        }

        // Search messages in this chat
        $messages = Message::query()
            ->where('chat_id', $chatId)
            ->where('message', 'LIKE', "%{$query}%")
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $results = $messages->map(function ($message) use ($query) {
            // Get context (messages before and after)
            $contextBefore = Message::query()
                ->where('chat_id', $message->chat_id)
                ->where('created_at', '<', $message->created_at)
                ->orderBy('created_at', 'desc')
                ->limit(2)
                ->pluck('message')
                ->reverse()
                ->values()
                ->toArray();

            $contextAfter = Message::query()
                ->where('chat_id', $message->chat_id)
                ->where('created_at', '>', $message->created_at)
                ->orderBy('created_at', 'asc')
                ->limit(2)
                ->pluck('message')
                ->values()
                ->toArray();

            // Highlight search term
            $highlightedText = preg_replace(
                '/(' . preg_quote($query, '/') . ')/i',
                '<mark>$1</mark>',
                $message->message
            );

            return [
                'type' => 'message',
                'id' => $message->id,
                'message_id' => $message->id,
                'preview' => strlen($message->message) > 100 
                    ? substr($message->message, 0, 100) . '...' 
                    : $message->message,
                'highlighted_text' => $highlightedText,
                'created_at' => $message->created_at->toISOString(),
                'context' => [
                    'before' => $contextBefore,
                    'after' => $contextAfter,
                ],
            ];
        });

        return response()->json([
            'results' => $results->values(),
            'total' => $results->count(),
            'summary' => "Found {$results->count()} results in this conversation",
        ]);
    }

    /**
     * Add a reaction to a message.
     */
    public function addReaction(Request $request, int $messageId)
    {
        $validated = $request->validate([
            'reaction' => 'required|string|max:10',
        ]);

        $message = Message::with('chat')->findOrFail($messageId);
        $chat = $message->chat;

        // Verify chat access
        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();
        if (!$canAccessChat) {
            return response()->json(['message' => 'You are not authorized to access this chat.'], 403);
        }

        // Check if reaction already exists
        $existingReaction = MessageReaction::where('message_id', $messageId)
            ->where('user_id', Auth::id())
            ->where('reaction', $validated['reaction'])
            ->first();

        if ($existingReaction) {
            return response()->json(['message' => 'Reaction already exists.'], 409);
        }

        // Create reaction
        $reaction = MessageReaction::create([
            'message_id' => $messageId,
            'user_id' => Auth::id(),
            'reaction' => $validated['reaction'],
        ]);

        $reaction->load('user');
        $message->load('chat');

        broadcast(new ReactionAdded($reaction, $message))->toOthers();

        return response()->json([
            'reaction' => [
                'id' => $reaction->id,
                'message_id' => $reaction->message_id,
                'user_id' => $reaction->user_id,
                'reaction' => $reaction->reaction,
                'user' => [
                    'id' => $reaction->user->id,
                    'name' => $reaction->user->name,
                ],
            ],
        ], 201);
    }

    /**
     * Remove a reaction from a message.
     */
    public function removeReaction(Request $request, int $messageId, int $reactionId)
    {
        $message = Message::with('chat')->findOrFail($messageId);
        $chat = $message->chat;

        // Verify chat access
        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();
        if (!$canAccessChat) {
            return response()->json(['message' => 'You are not authorized to access this chat.'], 403);
        }

        // Find and delete reaction
        $reaction = MessageReaction::where('id', $reactionId)
            ->where('message_id', $messageId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $reactionData = $reaction->toArray();
        $reaction->delete();

        // Recreate reaction object for broadcasting (since it's deleted)
        $reactionForBroadcast = new MessageReaction($reactionData);
        $reactionForBroadcast->id = $reactionData['id'];
        $message->load('chat');

        broadcast(new ReactionRemoved($reactionForBroadcast, $message))->toOthers();

        return response()->json(['message' => 'Reaction removed successfully.']);
    }

    /**
     * Get all reactions for a message.
     */
    public function getReactions(int $messageId)
    {
        $message = Message::with('chat')->findOrFail($messageId);
        $chat = $message->chat;

        // Verify chat access
        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();
        if (!$canAccessChat) {
            return response()->json(['message' => 'You are not authorized to access this chat.'], 403);
        }

        $reactions = MessageReaction::where('message_id', $messageId)
            ->with('user')
            ->get()
            ->groupBy('reaction')
            ->map(function ($reactions, $reaction) {
                return [
                    'reaction' => $reaction,
                    'count' => $reactions->count(),
                    'users' => $reactions->map(function ($r) {
                        return [
                            'id' => $r->user->id,
                            'name' => $r->user->name,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json(['reactions' => $reactions]);
    }

    /**
     * Update a message (within 15 minutes).
     */
    public function updateMessage(Request $request, int $messageId)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = Message::with('chat')->findOrFail($messageId);
        $chat = $message->chat;

        // Verify ownership
        if ($message->user_id !== Auth::id()) {
            return response()->json(['message' => 'You can only edit your own messages.'], 403);
        }

        // Verify chat access
        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();
        if (!$canAccessChat) {
            return response()->json(['message' => 'You are not authorized to access this chat.'], 403);
        }

        // Check time limit (15 minutes)
        $minutesSinceCreation = $message->created_at->diffInMinutes(now());
        if ($minutesSinceCreation > 15) {
            return response()->json([
                'message' => 'You can only edit messages within 15 minutes of sending.',
                'minutes_elapsed' => $minutesSinceCreation,
            ], 403);
        }

        // Update message
        $message->update([
            'message' => $validated['message'],
            'is_edited' => true,
        ]);

        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json([
            'message' => [
                'id' => $message->id,
                'message' => $message->message,
                'is_edited' => $message->is_edited,
                'updated_at' => $message->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Delete a message (after 30 minutes).
     */
    public function deleteMessage(int $messageId)
    {
        $message = Message::with('chat')->findOrFail($messageId);
        $chat = $message->chat;

        // Verify ownership
        if ($message->user_id !== Auth::id()) {
            return response()->json(['message' => 'You can only delete your own messages.'], 403);
        }

        // Verify chat access
        $canAccessChat = $chat->user_one === Auth::id() || $chat->user_two === Auth::id();
        if (!$canAccessChat) {
            return response()->json(['message' => 'You are not authorized to access this chat.'], 403);
        }

        // Check time limit (30 minutes)
        $minutesSinceCreation = $message->created_at->diffInMinutes(now());
        if ($minutesSinceCreation < 30) {
            return response()->json([
                'message' => 'You can only delete messages after 30 minutes of sending.',
                'minutes_elapsed' => $minutesSinceCreation,
            ], 403);
        }

        // Soft delete message
        $message->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        broadcast(new MessageDeleted($message))->toOthers();

        return response()->json([
            'message' => [
                'id' => $message->id,
                'is_deleted' => $message->is_deleted,
                'deleted_at' => $message->deleted_at->toISOString(),
            ],
        ]);
    }
}
