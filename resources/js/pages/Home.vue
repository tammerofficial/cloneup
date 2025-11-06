<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { nextTick, onBeforeMount, onBeforeUnmount, onMounted, onUnmounted, ref } from 'vue';

import 'emoji-picker-element';

import axios from 'axios';

import { formatTimeFromTimestamp, formatTimestamp } from '@/lib/utils';

import LoadingScreen from '@/components/app/LoadingScreen.vue';
import SearchDialog from '@/components/search/SearchDialog.vue';
import ConversationSearch from '@/components/search/ConversationSearch.vue';
import SearchResultSummary from '@/components/search/SearchResultSummary.vue';
import MessageReactions from '@/components/messages/MessageReactions.vue';

import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Popover from 'primevue/popover';
import Toast from 'primevue/toast';

import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

const isInitialized = ref(false);
const loadingValue = ref(0);

const showContacts = ref(false);

const newContactEmail = ref(null);
const showAddNewContactDialog = ref(false);
const isAddingNewContact = ref(false);

const isStartingNewChat = ref(false);
const showChat = ref(false);
const currentChat = ref({
    partner: null,
    messages: [],
    id: null,
});

const isSendingMessage = ref(false);
const currentMessage = ref('');
const selectedAttachments = ref([]);
const attachmentInput = ref(null);

const messageInput = ref();
const messageBody = ref();

const newChats = ref(0);

const emojiPopover = ref();

const hasMoreMessages = ref(true);

// Search states
const showGlobalSearch = ref(false);
const showConversationSearch = ref(false);
const showSearchSummary = ref(false);
const selectedSearchResult = ref(null);

// Message edit/delete states
const editingMessage = ref(null);
const editingMessageText = ref('');
const deletingMessageId = ref(null);

// Reactions states
const showingReactionPicker = ref(null);

// Voice recording states
const isRecording = ref(false);
const recordingTime = ref(0);
const mediaRecorder = ref(null);
const audioChunks = ref([]);
const recordingInterval = ref(null);
const audioBlob = ref(null);

// Media playback control (single media at a time)
const currentlyPlayingType = ref(null); // 'audio' | 'video' | null
let currentAudioEl = null;
let currentVideoEl = null;

// Sidebar resize
const sidebarWidth = ref(360); // initial width in px
const isResizingSidebar = ref(false);
const minSidebarWidth = 260;
const maxSidebarWidth = 720;
let sidebarStartX = 0;
let sidebarStartWidth = 360;

function startSidebarResize(e) {
    isResizingSidebar.value = true;
    sidebarStartX = e.clientX || (e.touches && e.touches[0]?.clientX) || 0;
    sidebarStartWidth = sidebarWidth.value;
    document.body.classList.add('select-none');
}

function onSidebarResize(e) {
    if (!isResizingSidebar.value) return;
    const clientX = e.clientX || (e.touches && e.touches[0]?.clientX) || 0;
    const delta = clientX - sidebarStartX;
    let next = sidebarStartWidth + delta;
    if (next < minSidebarWidth) next = minSidebarWidth;
    if (next > maxSidebarWidth) next = maxSidebarWidth;
    sidebarWidth.value = next;
}

function stopSidebarResize() {
    if (!isResizingSidebar.value) return;
    isResizingSidebar.value = false;
    document.body.classList.remove('select-none');
    try {
        localStorage.setItem('sidebarWidth', String(sidebarWidth.value));
    } catch (e) {}
}

function pauseCurrentMedia(exceptEl = null) {
    if (currentAudioEl && currentAudioEl !== exceptEl) {
        try { currentAudioEl.pause(); } catch (e) {}
    }
    if (currentVideoEl && currentVideoEl !== exceptEl) {
        try { currentVideoEl.pause(); } catch (e) {}
    }
}

function handleAudioPlay(messageId, el) {
    // Enforce single-media policy
    pauseCurrentMedia(el);
    currentlyPlayingType.value = 'audio';
    currentAudioEl = el;
}

function handleVideoPlay(messageId, el) {
    // Enforce single-media policy
    pauseCurrentMedia(el);
    currentlyPlayingType.value = 'video';
    currentVideoEl = el;
}

function handleAudioEnded(messageId, el) {
    // Try next audio within the same message first
    const audiosInSameMessage = Array.from(document.querySelectorAll(`audio[data-message-id="${messageId}"]`));
    if (audiosInSameMessage.length > 1) {
        const idx = audiosInSameMessage.indexOf(el);
        if (idx > -1 && idx + 1 < audiosInSameMessage.length) {
            const nextInSame = audiosInSameMessage[idx + 1];
            pauseCurrentMedia(nextInSame);
            currentlyPlayingType.value = 'audio';
            currentAudioEl = nextInSame;
            try { nextInSame.play(); } catch (e) {}
            return;
        }
    }
    // Otherwise, auto-play next voice message in subsequent messages
    playNextVoiceFrom(messageId);
}

function playNextVoiceFrom(messageId) {
    const idx = currentChat.value.messages.findIndex((m) => m.id === messageId);
    if (idx === -1) return;
    for (let i = idx + 1; i < currentChat.value.messages.length; i++) {
        const msg = currentChat.value.messages[i];
        if (msg && Array.isArray(msg.attachments)) {
            const hasAudio = msg.attachments.some((att) => att && ((att.file_type === 'audio') || getFileTypeFromMime(att.mime_type) === 'audio'));
            if (hasAudio) {
                // Find audio element for this message and play it
                const nextAudio = document.querySelector(`audio[data-message-id="${msg.id}"]`);
                if (nextAudio) {
                    // Scroll into view and play
                    const container = document.querySelector(`[data-message-id="${msg.id}"]`) || nextAudio;
                    try {
                        container?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } catch (e) {}
                    // Enforce single media
                    pauseCurrentMedia(nextAudio);
                    currentlyPlayingType.value = 'audio';
                    currentAudioEl = nextAudio;
                    try { nextAudio.play(); } catch (e) {}
                }
                break;
            }
        }
    }
}
onMounted(() => {
    newChats.value = page.props.chats.filter((chat) => chat.unread_messages > 0).length;

    setupEmojiPicker();

    // Simulate loading
    const interval = setInterval(() => {
        loadingValue.value += Math.floor(Math.random() * 70) + 1;

        if (loadingValue.value > 125) {
            clearInterval(interval);
            isInitialized.value = true;
        }
    }, 1000);

    // Init sidebar width from localStorage
    try {
        const stored = Number(localStorage.getItem('sidebarWidth'));
        if (!Number.isNaN(stored) && stored >= minSidebarWidth && stored <= maxSidebarWidth) {
            sidebarWidth.value = stored;
        }
    } catch (e) {}

    // Attach resize listeners
    document.addEventListener('mousemove', onSidebarResize);
    document.addEventListener('mouseup', stopSidebarResize);
    document.addEventListener('touchmove', onSidebarResize, { passive: false });
    document.addEventListener('touchend', stopSidebarResize);
});

onBeforeMount(() => {
    axios.defaults.headers.common['X-Socket-ID'] = window.Echo.socketId();

    // Handle incoming messages
    for (const chat of page.props.chats) {
        window.Echo.private(`chat.${chat.id}`)
            .listen('.MessageSent', (e) => {
                setupMessageListener(e);
            })
            .listen('.MessageRead', (e) => {
                setupMessageStatusChange(e);
            })
            .listen('.ReactionAdded', (e) => {
                setupReactionAdded(e);
            })
            .listen('.ReactionRemoved', (e) => {
                setupReactionRemoved(e);
            })
            .listen('.MessageUpdated', (e) => {
                setupMessageUpdated(e);
            })
            .listen('.MessageDeleted', (e) => {
                setupMessageDeleted(e);
            });
    }

    // Handle incoming chat creations from other users
    window.Echo.private(`chat.start.user.${page.props.auth.user.id}`).listen('.ChatStarted', (e) => {
        router.reload({ only: ['chats'] });

        window.Echo.private(`chat.${e.chat.id}`)
            .listen('.MessageSent', (e) => {
                setupMessageListener(e);
            })
            .listen('.MessageRead', (e) => {
                setupMessageStatusChange(e);
            });
    });

    // Handle user status changes
    document.addEventListener('visibilitychange', function () {
        handleVisibilityChange();
    });

    window.addEventListener('beforeunload', function () {
        handleVisibilityChange(true);
    });
});

onBeforeUnmount(() => {
    // Clean up recording
    if (isRecording.value) {
        cancelVoiceRecording();
    }
    
    if (recordingInterval.value) {
        clearInterval(recordingInterval.value);
    }
    
    // Detach sidebar resize listeners
    document.removeEventListener('mousemove', onSidebarResize);
    document.removeEventListener('mouseup', stopSidebarResize);
    document.removeEventListener('touchmove', onSidebarResize);
    document.removeEventListener('touchend', stopSidebarResize);
    
    window.Echo.leave(`chat.start.user.${page.props.auth.user.id}`);

    for (const chat of page.props.chats) {
        window.Echo.leave(`chat.${chat.id}`);
    }

    document.removeEventListener('visibilitychange', function () {
        handleVisibilityChange();
    });

    window.removeEventListener('beforeunload', function () {
        handleVisibilityChange(true);
    });
});

function setupEmojiPicker() {
    let emojiPicker = null;
    let emojiClickHandler = null;

    const observer = new MutationObserver(() => {
        const newEmojiPicker = document.querySelector('emoji-picker');

        if (newEmojiPicker && newEmojiPicker !== emojiPicker) {
            // Remove old event listener if it exists
            if (emojiPicker && emojiClickHandler) {
                emojiPicker.removeEventListener('emoji-click', emojiClickHandler);
            }

            // Update reference
            emojiPicker = newEmojiPicker;
            emojiClickHandler = (event) => {
                currentMessage.value += event.detail.emoji.unicode;
            };

            // Attach new event listener
            emojiPicker.addEventListener('emoji-click', emojiClickHandler);
        }

        // If emojiPicker is removed
        if (!newEmojiPicker && emojiPicker) {
            emojiPicker.removeEventListener('emoji-click', emojiClickHandler);
            emojiPicker = null;
            emojiClickHandler = null;
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    onUnmounted(() => {
        observer.disconnect();
        if (emojiPicker && emojiClickHandler) {
            emojiPicker.removeEventListener('emoji-click', emojiClickHandler);
        }
    });
}

function setupMessageStatusChange(e) {
    if (currentChat.value.id === e.message.chat_id) {
        const message = currentChat.value.messages.find((message) => message.id === e.message.id);
        if (message) message.status = 'read';
    }
}

function setupReactionAdded(e) {
    const message = currentChat.value.messages.find((m) => m.id === e.reaction.message_id);
    if (message && currentChat.value.id) {
        if (!message.reactions) message.reactions = [];
        
        const reactionIndex = message.reactions.findIndex((r) => r.reaction === e.reaction.reaction);
        if (reactionIndex >= 0) {
            message.reactions[reactionIndex].count++;
            if (!message.reactions[reactionIndex].users.find((u) => u.id === e.reaction.user_id)) {
                message.reactions[reactionIndex].users.push(e.reaction.user);
            }
        } else {
            message.reactions.push({
                reaction: e.reaction.reaction,
                count: 1,
                users: [e.reaction.user],
            });
        }
    }
}

function setupReactionRemoved(e) {
    const message = currentChat.value.messages.find((m) => m.id === e.reaction.message_id);
    if (message && message.reactions && currentChat.value.id) {
        const reactionIndex = message.reactions.findIndex((r) => r.reaction === e.reaction.reaction);
        if (reactionIndex >= 0) {
            message.reactions[reactionIndex].count--;
            message.reactions[reactionIndex].users = message.reactions[reactionIndex].users.filter(
                (u) => u.id !== e.reaction.user_id
            );
            
            if (message.reactions[reactionIndex].count === 0) {
                message.reactions.splice(reactionIndex, 1);
            }
        }
    }
}

function setupMessageUpdated(e) {
    if (currentChat.value.id === e.message.chat_id) {
        const message = currentChat.value.messages.find((m) => m.id === e.message.id);
        if (message) {
            message.message = e.message.message;
            message.is_edited = e.message.is_edited;
            message.updated_at = e.message.updated_at;
            editingMessage.value = null;
            editingMessageText.value = '';
        }
    }
}

function setupMessageDeleted(e) {
    if (currentChat.value.id === e.message.chat_id) {
        const message = currentChat.value.messages.find((m) => m.id === e.message.id);
        if (message) {
            message.is_deleted = e.message.is_deleted;
            message.deleted_at = e.message.deleted_at;
        }
    }
}

function setupMessageListener(e) {
    if (e.message.user_id === page.props.auth.user.id) return;

    const chat = page.props.chats.find((chat) => chat.id === e.message.chat_id);
    const lastMessageText = e.message.message || 
        (e.message.attachments?.length > 0 ? 
            `${e.message.attachments.length} attachment(s)` : '');
    chat.last_message = lastMessageText;
    chat.last_message_created_at = e.message.created_at;
    if (currentChat.value.id !== e.message.chat_id) {
        chat.unread_messages++;
        newChats.value = page.props.chats.filter((chat) => chat.unread_messages > 0).length;
    }

    page.props.chats.splice(page.props.chats.indexOf(chat), 1);
    page.props.chats.unshift(chat);

    if (currentChat.value.id === e.message.chat_id) {
        currentChat.value.messages.push(e.message);

        axios.patch('/message-status', {
            message_ids: [e.message.id],
        });

        nextTick(() => {
            scrollToBottom();
        });
    }
}

let timeoutId;
function handleVisibilityChange(exit = false) {
    clearTimeout(timeoutId);

    timeoutId = setTimeout(() => {
        axios.patch('/user-status', { active: !document.hidden && !exit });
    }, 2000);
}

const toggleEmojiPicker = (event) => {
    emojiPopover.value.toggle(event);
};

const handleContactListToggle = () => {
    showContacts.value = !showContacts.value;
};

const handleAddNewContact = () => {
    if (!newContactEmail.value || isAddingNewContact.value) return;

    isAddingNewContact.value = true;

    axios
        .post('/contact/store', {
            contact_email: newContactEmail.value,
        })
        .then(() => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'New Contact added',
                life: 5000,
            });

            newContactEmail.value = null;

            router.reload({ only: ['contacts'] });
        })
        .catch((error) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response.data.message ?? error.response.data,
                life: 5000,
            });
        })
        .finally(() => {
            isAddingNewContact.value = false;
        });
};

const startNewChat = (email) => {
    if (isStartingNewChat.value) return;

    isStartingNewChat.value = true;

    axios
        .post('/chat/start', {
            email: email,
        })
        .then((response) => {
            showChat.value = true;

            if (response.data.created) {
                router.reload({ only: ['chats'] });

                window.Echo.private(`chat.${response.data.chat_id}`)
                    .listen('.MessageSent', (e) => {
                        setupMessageListener(e);
                    })
                    .listen('.MessageRead', (e) => {
                        setupMessageStatusChange(e);
                    })
                    .listen('.ReactionAdded', (e) => {
                        setupReactionAdded(e);
                    })
                    .listen('.ReactionRemoved', (e) => {
                        setupReactionRemoved(e);
                    })
                    .listen('.MessageUpdated', (e) => {
                        setupMessageUpdated(e);
                    })
                    .listen('.MessageDeleted', (e) => {
                        setupMessageDeleted(e);
                    });
            }

            handleChatSelection(response.data.chat_id);

            showContacts.value = false;
        })
        .catch((error) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response.data.message ?? error.response.data,
                life: 5000,
            });
        })
        .finally(() => {
            isStartingNewChat.value = false;
        });
};

const handleChatSelection = (id) => {
    if (currentChat.value.id === id) return;

    axios
        .get(`/chat/${id}/messages`)
        .then((response) => {
            window.Echo.leave(`status.user.${currentChat.value.partner?.id}`);

            hasMoreMessages.value = true;

            currentMessage.value = '';

            currentChat.value.partner = null;
            currentChat.value.messages = [];
            currentChat.value.id = null;

            currentChat.value.id = response.data.chat_id;
            currentChat.value.partner = response.data.partner;
            currentChat.value.messages.push(...response.data.messages);
            
            // Debug: Log messages with attachments
            const messagesWithAttachments = response.data.messages.filter(m => m.attachments && m.attachments.length > 0);
            if (messagesWithAttachments.length > 0) {
                console.log('Messages with attachments:', messagesWithAttachments);
                messagesWithAttachments.forEach(msg => {
                    console.log(`Message ${msg.id} attachments:`, msg.attachments);
                    msg.attachments.forEach(att => {
                        console.log(`  Attachment ${att.id}:`, {
                            file_type: att.file_type,
                            mime_type: att.mime_type,
                            file_url: att.file_url,
                            thumbnail_url: att.thumbnail_url
                        });
                    });
                });
            }

            const chat = page.props.chats.find((chat) => chat.id === currentChat.value.id);
            chat.unread_messages = 0;
            newChats.value = page.props.chats.filter((chat) => chat.unread_messages > 0).length;

            window.Echo.private(`status.user.${currentChat.value.partner?.id}`).listen('.UserStatusChange', (e) => {
                if (currentChat.value.partner) currentChat.value.partner.is_active = e.active;
            });

            // Setup listeners for reactions and message updates
            window.Echo.private(`chat.${currentChat.value.id}`)
                .listen('.ReactionAdded', (e) => {
                    setupReactionAdded(e);
                })
                .listen('.ReactionRemoved', (e) => {
                    setupReactionRemoved(e);
                })
                .listen('.MessageUpdated', (e) => {
                    setupMessageUpdated(e);
                })
                .listen('.MessageDeleted', (e) => {
                    setupMessageDeleted(e);
                });

            const unreadMessages = currentChat.value.messages.filter(
                (message) => message.status !== 'read' && message.user_id !== page.props.auth.user.id,
            );

            if (unreadMessages.length > 0)
                axios.patch('/message-status', {
                    message_ids: unreadMessages.map((message) => message.id),
                });

            showChat.value = true;

            nextTick(() => {
                messageInput.value.focus();

                scrollToBottom();
            });
        })
        .catch((error) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response.data.message ?? error.response.data,
                life: 5000,
            });
        });
};

const sendMessage = () => {
    if (isSendingMessage.value || (currentMessage.value.length === 0 && selectedAttachments.value.length === 0)) return;

    isSendingMessage.value = true;

    const formData = new FormData();
    formData.append('chat_id', currentChat.value.id);
    if (currentMessage.value) {
        formData.append('message', currentMessage.value);
    }
    
    // Add attachments
    selectedAttachments.value.forEach((file) => {
        formData.append('attachments[]', file);
    });

    axios
        .post('/chat/message', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })
        .then((response) => {
            currentChat.value.messages.push(response.data.message);

            const chat = page.props.chats.find((chat) => chat.id === currentChat.value.id);
            const lastMessageText = response.data.message.message || 
                (response.data.message.attachments?.length > 0 ? 
                    `${response.data.message.attachments.length} attachment(s)` : '');
            chat.last_message = lastMessageText;
            chat.last_message_created_at = response.data.message.created_at;

            // Move the chat to the first position in the array
            page.props.chats.splice(page.props.chats.indexOf(chat), 1);
            page.props.chats.unshift(chat);
        })
        .catch((error) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response?.data?.message ?? error.response?.data ?? 'Failed to send message',
                life: 5000,
            });
        })
        .finally(() => {
            isSendingMessage.value = false;

            currentMessage.value = '';
            selectedAttachments.value = [];

            nextTick(() => {
                messageInput.value.focus();

                scrollToBottom();
            });
        });
};

const handleAttachmentSelect = (event) => {
    const files = Array.from(event.target.files);
    
    // Validate file count
    if (selectedAttachments.value.length + files.length > 10) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'You can upload a maximum of 10 files',
            life: 5000,
        });
        return;
    }
    
    // Validate file sizes (10MB each)
    const invalidFiles = files.filter(file => file.size > 10 * 1024 * 1024);
    if (invalidFiles.length > 0) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Some files exceed the 10MB limit',
            life: 5000,
        });
        return;
    }
    
    selectedAttachments.value.push(...files);
    
    // Reset input
    if (attachmentInput.value) {
        attachmentInput.value.value = '';
    }
};

const removeAttachment = (index) => {
    selectedAttachments.value.splice(index, 1);
};

const getFileType = (file) => {
    if (file.type.startsWith('image/')) return 'image';
    if (file.type.startsWith('video/')) return 'video';
    if (file.type.startsWith('audio/')) return 'audio';
    return 'file';
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const getPreviewUrl = (file) => {
    try {
        return (window.URL || URL).createObjectURL(file);
    } catch (e) {
        return '';
    }
};

const getFileTypeFromMime = (mimeType) => {
    if (!mimeType) return 'file';
    if (mimeType.startsWith('image/')) return 'image';
    if (mimeType.startsWith('video/')) return 'video';
    if (mimeType.startsWith('audio/')) return 'audio';
    return 'file';
};

const formatDuration = (seconds) => {
    if (!seconds) return '';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    return `${minutes}:${String(secs).padStart(2, '0')}`;
};

function scrollToBottom() {
    messageBody.value.scrollTo(0, messageBody.value.scrollHeight);
}

const fetchMoreMessagesOnScroll = () => {
    if (!hasMoreMessages.value) return;

    if (messageBody.value.scrollTop === 0) {
        axios
            .get(`/chat/${currentChat.value.id}/messages?offset=${currentChat.value.messages.length}`)
            .then((response) => {
                const previousScrollHeight = messageBody.value.scrollHeight;

                currentChat.value.messages.unshift(...response.data.messages);

                hasMoreMessages.value = response.data.messages.length > 0;

                nextTick(() => {
                    messageBody.value.scrollTo(0, messageBody.value.scrollHeight - previousScrollHeight);
                });
            })
            .catch((error) => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: error.response.data.message ?? error.response.data,
                    life: 5000,
                });
            });
    }
};

// Start new chat with existing partner
const startNewChatWithPartner = (partnerEmail) => {
    if (isStartingNewChat.value) return;

    isStartingNewChat.value = true;

    axios
        .post('/chat/start', {
            email: partnerEmail,
        })
        .then((response) => {
            showChat.value = true;

            if (response.data.created) {
                router.reload({ only: ['chats'] });

                window.Echo.private(`chat.${response.data.chat_id}`)
                    .listen('.MessageSent', (e) => {
                        setupMessageListener(e);
                    })
                    .listen('.MessageRead', (e) => {
                        setupMessageStatusChange(e);
                    })
                    .listen('.ReactionAdded', (e) => {
                        setupReactionAdded(e);
                    })
                    .listen('.ReactionRemoved', (e) => {
                        setupReactionRemoved(e);
                    })
                    .listen('.MessageUpdated', (e) => {
                        setupMessageUpdated(e);
                    })
                    .listen('.MessageDeleted', (e) => {
                        setupMessageDeleted(e);
                    });
            }

            handleChatSelection(response.data.chat_id);
        })
        .catch((error) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response.data.message ?? error.response.data,
                life: 5000,
            });
        })
        .finally(() => {
            isStartingNewChat.value = false;
        });
};

// Search functions
const handleSearchResultSelected = (result) => {
    selectedSearchResult.value = result;
    showSearchSummary.value = true;
};

const jumpToSearchResult = (result) => {
    if (result.chat_id && result.message_id) {
        // Switch to chat if not already there
        if (currentChat.value.id !== result.chat_id) {
            handleChatSelection(result.chat_id);
        }

        // Wait for messages to load, then scroll to message
        nextTick(() => {
            setTimeout(() => {
                scrollToMessage(result.message_id);
            }, 500);
        });
    }
};

const scrollToMessage = (messageId) => {
    const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
    if (messageElement) {
        messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        messageElement.classList.add('highlight-message');
        setTimeout(() => {
            messageElement.classList.remove('highlight-message');
        }, 2000);
    }
};

// Message edit/delete functions
const canEditMessage = (message) => {
    if (message.user_id !== page.props.auth.user.id) return false;
    const minutesSinceCreation = Math.floor((new Date() - new Date(message.created_at)) / 60000);
    return minutesSinceCreation <= 15;
};

const canDeleteMessage = (message) => {
    if (message.user_id !== page.props.auth.user.id) return false;
    const minutesSinceCreation = Math.floor((new Date() - new Date(message.created_at)) / 60000);
    return minutesSinceCreation >= 30;
};

const startEditMessage = (message) => {
    editingMessage.value = message;
    editingMessageText.value = message.message;
};

const cancelEditMessage = () => {
    editingMessage.value = null;
    editingMessageText.value = '';
};

const saveEditMessage = async () => {
    if (!editingMessage.value || !editingMessageText.value.trim()) return;

    try {
        const response = await axios.put(`/messages/${editingMessage.value.id}`, {
            message: editingMessageText.value,
        });

        const message = currentChat.value.messages.find((m) => m.id === editingMessage.value.id);
        if (message) {
            message.message = response.data.message.message;
            message.is_edited = response.data.message.is_edited;
            message.updated_at = response.data.message.updated_at;
        }

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Message updated successfully',
            life: 3000,
        });

        cancelEditMessage();
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message ?? 'Failed to update message',
            life: 5000,
        });
    }
};

const deleteMessage = async (messageId) => {
    if (!confirm('Are you sure you want to delete this message?')) return;

    try {
        await axios.delete(`/messages/${messageId}`);

        const message = currentChat.value.messages.find((m) => m.id === messageId);
        if (message) {
            message.is_deleted = true;
            message.deleted_at = new Date().toISOString();
        }

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Message deleted successfully',
            life: 3000,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message ?? 'Failed to delete message',
            life: 5000,
        });
    }
};

// Reaction functions
const addReactionToMessage = async (messageId, reaction) => {
    try {
        const response = await axios.post(`/messages/${messageId}/reactions`, {
            reaction: reaction,
        });

        const message = currentChat.value.messages.find((m) => m.id === messageId);
        if (message) {
            if (!message.reactions) message.reactions = [];
            
            const reactionIndex = message.reactions.findIndex((r) => r.reaction === reaction);
            if (reactionIndex >= 0) {
                message.reactions[reactionIndex].count++;
                if (!message.reactions[reactionIndex].users.find((u) => u.id === page.props.auth.user.id)) {
                    message.reactions[reactionIndex].users.push({
                        id: page.props.auth.user.id,
                        name: page.props.auth.user.name,
                    });
                }
            } else {
                message.reactions.push({
                    reaction: reaction,
                    count: 1,
                    users: [{
                        id: page.props.auth.user.id,
                        name: page.props.auth.user.name,
                    }],
                });
            }
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message ?? 'Failed to add reaction',
            life: 5000,
        });
    }
};

const removeReactionFromMessage = async (messageId, reaction) => {
    try {
        // Get reaction ID from backend
        const reactionsResponse = await axios.get(`/messages/${messageId}/reactions`);
        const allReactions = reactionsResponse.data.reactions;
        const reactionInfo = allReactions.find((r) => r.reaction === reaction);
        
        if (!reactionInfo) return;

        // Find user's reaction ID
        const userReaction = reactionInfo.users.find((u) => u.id === page.props.auth.user.id);
        if (!userReaction || !userReaction.reaction_id) return;

        await axios.delete(`/messages/${messageId}/reactions/${userReaction.reaction_id}`);

        // Update local state
        const message = currentChat.value.messages.find((m) => m.id === messageId);
        if (message && message.reactions) {
            const reactionData = message.reactions.find((r) => r.reaction === reaction);
            if (reactionData) {
                reactionData.count--;
                reactionData.users = reactionData.users.filter((u) => u.id !== page.props.auth.user.id);
                
                if (reactionData.count === 0) {
                    message.reactions = message.reactions.filter((r) => r.reaction !== reaction);
                }
            }
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message ?? 'Failed to remove reaction',
            life: 5000,
        });
    }
};

const toggleReaction = (messageId, reaction) => {
    const message = currentChat.value.messages.find((m) => m.id === messageId);
    if (!message || !message.reactions) {
        addReactionToMessage(messageId, reaction);
        return;
    }

    const reactionData = message.reactions.find((r) => r.reaction === reaction);
    if (!reactionData) {
        addReactionToMessage(messageId, reaction);
        return;
    }

    const hasUserReaction = reactionData.users?.some((u) => u.id === page.props.auth.user.id);
    if (hasUserReaction) {
        removeReactionFromMessage(messageId, reaction);
    } else {
        addReactionToMessage(messageId, reaction);
    }
};

// Voice recording functions
const startVoiceRecording = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const recorder = new MediaRecorder(stream);
        
        mediaRecorder.value = recorder;
        audioChunks.value = [];
        recordingTime.value = 0;
        
        recorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                audioChunks.value.push(event.data);
            }
        };
        
        recorder.onstop = () => {
            const audioBlobData = new Blob(audioChunks.value, { type: 'audio/webm' });
            audioBlob.value = audioBlobData;
            
            // Stop all tracks
            stream.getTracks().forEach(track => track.stop());
        };
        
        recorder.start();
        isRecording.value = true;
        
        // Start timer
        recordingInterval.value = setInterval(() => {
            recordingTime.value++;
        }, 1000);
        
        toast.add({
            severity: 'info',
            summary: 'Recording',
            detail: 'Voice recording started',
            life: 2000,
        });
    } catch (error) {
        console.error('Error starting recording:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to start recording. Please check microphone permissions.',
            life: 5000,
        });
    }
};

const stopVoiceRecording = () => {
    if (mediaRecorder.value && isRecording.value) {
        mediaRecorder.value.stop();
        isRecording.value = false;
        
        if (recordingInterval.value) {
            clearInterval(recordingInterval.value);
            recordingInterval.value = null;
        }
        
        // Wait for blob to be ready
        setTimeout(() => {
            if (audioBlob.value) {
                toast.add({
                    severity: 'success',
                    summary: 'Recording Stopped',
                    detail: 'Voice recording completed. Click send to share.',
                    life: 3000,
                });
            }
        }, 100);
    }
};

const cancelVoiceRecording = () => {
    if (mediaRecorder.value) {
        if (isRecording.value) {
            mediaRecorder.value.stop();
        }
        isRecording.value = false;
        audioChunks.value = [];
        audioBlob.value = null;
        recordingTime.value = null;
        
        if (recordingInterval.value) {
            clearInterval(recordingInterval.value);
            recordingInterval.value = null;
        }
        
        // Stop all tracks if stream exists
        if (mediaRecorder.value.stream) {
            mediaRecorder.value.stream.getTracks().forEach(track => track.stop());
        }
    } else {
        // Clean up if no recorder but state is set
        isRecording.value = false;
        audioChunks.value = [];
        audioBlob.value = null;
        recordingTime.value = 0;
        
        if (recordingInterval.value) {
            clearInterval(recordingInterval.value);
            recordingInterval.value = null;
        }
    }
};

const sendVoiceRecording = async () => {
    if (!audioBlob.value || recordingTime.value === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'No recording to send',
            life: 3000,
        });
        return;
    }
    
    if (isSendingMessage.value) return;
    
    isSendingMessage.value = true;
    
    // Convert blob to file
    const audioFile = new File([audioBlob.value], `voice_${Date.now()}.webm`, {
        type: 'audio/webm'
    });
    
    const formData = new FormData();
    formData.append('chat_id', currentChat.value.id);
    formData.append('attachments[]', audioFile);
    
    try {
        const response = await axios.post('/chat/message', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        
        currentChat.value.messages.push(response.data.message);
        
        const chat = page.props.chats.find((chat) => chat.id === currentChat.value.id);
        chat.last_message = 'Voice message';
        chat.last_message_created_at = response.data.message.created_at;
        
        page.props.chats.splice(page.props.chats.indexOf(chat), 1);
        page.props.chats.unshift(chat);
        
        // Reset recording
        audioBlob.value = null;
        audioChunks.value = [];
        recordingTime.value = 0;
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Voice message sent',
            life: 3000,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message ?? error.response?.data ?? 'Failed to send voice message',
            life: 5000,
        });
    } finally {
        isSendingMessage.value = false;
        
        nextTick(() => {
            messageInput.value?.focus();
            scrollToBottom();
        });
    }
};

const formatRecordingTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${String(secs).padStart(2, '0')}`;
};
</script>

<template>
    <Toast />

    <Head :title="newChats > 0 ? `(${newChats.toString()})` : ''" />

    <!-- Source Code for frontend: https://codepen.io/macridgway23/pen/rNMgRgY -->

    <LoadingScreen v-if="!isInitialized" :loadingValue="loadingValue" />

    <div v-else class="flex h-screen w-full bg-black">
        <aside
            class="relative flex shrink-0 flex-col overflow-y-auto border-r border-gray-800 bg-gray-200"
            :style="{ width: sidebarWidth + 'px' }"
        >
            <div class="aside-header sticky left-0 right-0 top-0 z-40 text-gray-400">
                <div class="flex items-center bg-[#131C21] px-4 py-6">
                    <div class="text-2xl font-bold text-white">{{ showContacts ? 'New Chat' : 'Chats' }}</div>
                    <div v-if="showContacts" class="flex-1 text-right">
                        <span class="pi pi-arrow-left cursor-pointer" v-tooltip="'Back'" @click="handleContactListToggle"></span>
                    </div>
                    <div v-else class="flex-1 text-right">
                        <span
                            class="pi pi-search mr-6 inline cursor-pointer"
                            v-tooltip="'Global Search'"
                            style="font-size: large"
                            @click="showGlobalSearch = true"
                        ></span>
                        <span
                            class="pi pi-pen-to-square mr-6 inline cursor-pointer"
                            v-tooltip="'New Chat'"
                            style="font-size: large"
                            @click="handleContactListToggle"
                        ></span>
                        <span class="pi pi-ellipsis-v inline cursor-pointer" style="font-size: large"></span>
                    </div>
                </div>
                <div class="search-bar w-full px-4 py-2">
                    <form @submit.prevent>
                        <div class="relative text-gray-600 focus-within:text-gray-200">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-2">
                                <button type="submit" class="focus:shadow-outline p-1 focus:outline-none">
                                    <svg
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                        class="h-4 w-4 text-gray-300"
                                    >
                                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </button>
                            </span>
                            <input
                                class="w-full rounded-full bg-gray-600 py-2 pl-10 text-sm text-white focus:bg-gray-600/50 focus:outline-none"
                                placeholder="Search or start new chat"
                                autocomplete="off"
                            />
                        </div>
                    </form>
                </div>
            </div>
            <div v-if="showContacts" class="aside-messages grow select-none">
                <div
                    class="message cursor-pointer border-gray-700 px-4 py-3 text-gray-300 hover:bg-gray-600/50"
                    @click="showAddNewContactDialog = true"
                >
                    <div class="relative flex items-center">
                        <div class="w-1/6">
                            <Avatar class="mr-2" icon="pi pi-user" style="background-color: #00a884" size="large" shape="circle" />
                        </div>
                        <div class="w-5/6">
                            <div class="text-xl text-white" id="personName">New Contact</div>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-4 text-green-400">Me</div>
                <div class="message cursor-pointer border-gray-700 px-4 py-3 text-gray-300 hover:bg-gray-600/50">
                    <div class="relative flex items-center">
                        <div class="w-1/6">
                            <Avatar :label="$page.props.auth.user.name[0]" class="mr-2" size="large" shape="circle" />
                        </div>
                        <div class="w-5/6">
                            <div class="text-xl text-white" id="personName">{{ $page.props.auth.user.name }}</div>
                            <div class="truncate text-sm" id="messagePreview">{{ $page.props.auth.user.about }}</div>
                        </div>
                        <span class="absolute right-0 top-0 mt-1 text-xs">{{ $page.props.auth.user.email }}</span>
                    </div>
                </div>

                <div class="px-4 py-6 text-green-400">My Contacts</div>
                <div class="flex-col divide-y-2">
                    <div
                        v-for="contact in $page.props.contacts"
                        :key="contact.id"
                        class="message cursor-pointer border-gray-700 px-4 py-3 text-gray-300 hover:bg-gray-600/50"
                        @click="startNewChat(contact.email)"
                    >
                        <div class="relative flex items-center">
                            <div class="w-1/6">
                                <Avatar :label="contact.name[0]" class="mr-2" size="large" shape="circle" />
                            </div>
                            <div class="w-5/6">
                                <div class="text-xl text-white" id="personName">{{ contact.name }}</div>
                                <div class="truncate text-sm" id="messagePreview">{{ contact.about }}</div>
                            </div>
                            <span class="absolute right-0 top-0 mt-1 text-xs">{{ contact.email }}</span>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-6 text-green-400">All Users</div>
                <div class="flex-col divide-y-2">
                    <div
                        v-for="user in $page.props.allUsers"
                        :key="user.id"
                        class="message cursor-pointer border-gray-700 px-4 py-3 text-gray-300 hover:bg-gray-600/50"
                        @click="startNewChat(user.email)"
                    >
                        <div class="relative flex items-center">
                            <div class="w-1/6">
                                <Avatar :label="user.name[0]" class="mr-2" size="large" shape="circle" />
                            </div>
                            <div class="w-5/6">
                                <div class="text-xl text-white" id="personName">{{ user.name }}</div>
                                <div class="truncate text-sm" id="messagePreview">{{ user.about || 'No status' }}</div>
                            </div>
                            <span class="absolute right-0 top-0 mt-1 text-xs">{{ user.email }}</span>
                            <span v-if="user.is_active" class="absolute right-0 top-0 mt-4 mr-2 h-2 w-2 rounded-full bg-green-500"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="aside-messages grow">
                <div
                    v-for="chat in $page.props.chats"
                    :key="chat.id"
                    class="message cursor-pointer border-b border-gray-700 px-4 py-3 text-gray-300 hover:bg-gray-600/50"
                    @click="handleChatSelection(chat.id)"
                >
                    <div class="relative flex select-none items-center">
                        <div class="w-1/6">
                            <Avatar :label="chat.partner.name[0]" class="mr-2" size="large" shape="circle" />
                        </div>
                        <div class="w-5/6">
                            <div class="flex items-center gap-2">
                                <div class="text-xl text-white" id="personName">{{ chat.partner.name }}</div>
                                <span
                                    class="pi pi-plus text-xs text-gray-400 hover:text-white cursor-pointer"
                                    v-tooltip="'New Chat'"
                                    @click.stop="startNewChatWithPartner(chat.partner.email)"
                                ></span>
                            </div>
                            <div class="truncate text-sm" id="messagePreview">
                                {{ chat.last_message || (chat.created_at ? 'New chat' : '') }}
                            </div>
                        </div>
                        <span class="absolute right-0 top-0 mt-1 text-xs">{{
                            chat.last_message_created_at 
                                ? formatTimestamp(chat.last_message_created_at) 
                                : (chat.created_at ? formatTimestamp(chat.created_at) : '')
                        }}</span>
                        <span v-if="chat.unread_messages > 0" class="absolute bottom-0 right-0 mt-1 text-xs text-green-400">
                            {{ chat.unread_messages }} new
                        </span>
                    </div>
                </div>
            </div>
        </aside>
        <div v-if="!showChat" class="flex w-full bg-[#222E35]">
            <div class="m-auto flex flex-col items-center gap-4">
                <div class="pi pi-whatsapp" style="font-size: 5rem"></div>
                <div class="text-sm text-gray-200">
                    WhatsApp Clone - <a class="underline" href="https://github.com/BlackyDrum/whatsapp-clone">Github</a>
                </div>
            </div>
        </div>
        <main
            v-else
            ref="messageBody"
            id="messageBody"
            class="bg-whatsapp relative flex w-full flex-col overflow-y-auto"
            @scroll="fetchMoreMessagesOnScroll"
        >
            <div class="main-header sticky left-0 right-0 top-0 z-40 text-gray-400">
                <div class="flex items-center px-4 py-3">
                    <div class="flex-1 truncate">
                        <div class="flex">
                            <div class="mr-4">
                                <Avatar :label="currentChat.partner?.name[0]" class="mr-2" size="large" shape="circle" />
                            </div>
                            <div>
                                <p class="text-md font-bold text-white">{{ currentChat.partner?.name }}</p>
                                <p v-if="!currentChat.partner?.is_active" class="text-sm text-gray-400">
                                    last seen {{ formatTimestamp(currentChat.partner?.last_seen || new Date()) }}
                                </p>
                                <p v-else class="text-sm text-green-400">Online</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 text-right">
                        <span 
                            class="pi pi-search mr-5 inline h-6 w-6 cursor-pointer" 
                            v-tooltip="'Search in Conversation'"
                            @click="showConversationSearch = !showConversationSearch"
                        > </span>
                        <span class="pi pi-ellipsis-v inline h-6 w-6 cursor-pointer"> </span>
                    </div>
                </div>
            </div>
            <div class="main-messages block grow px-4 py-3">
                <ConversationSearch
                    :visible="showConversationSearch"
                    :chatId="currentChat.id"
                    @update:visible="showConversationSearch = $event"
                    @result-selected="handleSearchResultSelected"
                />
                <div
                    v-for="message in currentChat.messages"
                    :key="message.id"
                    :data-message-id="message.id"
                    class="flex message-item"
                    :class="{ 'justify-end': message.user_id === $page.props.auth.user.id }"
                >
                    <div
                        class="single-message mb-4 max-w-[57%] break-words rounded-bl-lg rounded-br-lg rounded-tl-lg px-4 py-2 text-gray-200 relative group"
                        :class="{ user: message.user_id === $page.props.auth.user.id }"
                    >
                        <!-- Attachments -->
                        <div v-if="message.attachments && message.attachments.length > 0" class="mb-2 space-y-2">
                            <div
                                v-for="attachment in message.attachments"
                                :key="attachment.id || attachment.temp_id"
                                class="relative"
                            >
                                <!-- Image -->
                                <div v-if="(attachment.file_type === 'image' || (attachment.mime_type && getFileTypeFromMime(attachment.mime_type) === 'image')) && !attachment.processing" class="rounded-lg overflow-hidden max-w-xs">
                                    <img
                                        v-if="attachment.thumbnail_url || attachment.file_url"
                                        :src="attachment.thumbnail_url || attachment.file_url"
                                        :alt="attachment.file_name"
                                        class="max-w-full h-auto cursor-pointer rounded-lg"
                                        @click="attachment.file_url && typeof window !== 'undefined' && window.open(attachment.file_url, '_blank')"
                                        @error="(e) => { console.error('Image load error:', e.target.src); }"
                                    />
                                </div>
                                <!-- Image Processing -->
                                <div v-else-if="(attachment.file_type === 'image' || getFileTypeFromMime(attachment.mime_type) === 'image') && attachment.processing" class="rounded-lg overflow-hidden max-w-xs bg-gray-800 relative">
                                    <div class="h-48 flex items-center justify-center">
                                        <span class="pi pi-spin pi-spinner text-white text-2xl"></span>
                                    </div>
                                </div>
                                <!-- Video -->
                                <div v-else-if="(attachment.file_type === 'video' || getFileTypeFromMime(attachment.mime_type) === 'video') && !attachment.processing" class="rounded-lg overflow-hidden max-w-xs bg-gray-800 relative">
                                    <video
                                        v-if="attachment.file_url"
                                        :src="attachment.file_url"
                                        controls
                                        class="max-w-full h-auto"
                                        :data-message-id="message.id"
                                        @play="handleVideoPlay(message.id, $event.target)"
                                    ></video>
                                    <div v-else class="h-48 flex items-center justify-center">
                                        <span class="pi pi-video text-4xl text-gray-400"></span>
                                    </div>
                                    <div v-if="attachment.duration" class="absolute bottom-2 right-2 bg-black bg-opacity-70 text-white text-xs px-2 py-1 rounded">
                                        {{ formatDuration(attachment.duration) }}
                                    </div>
                                </div>
                                <!-- Video Processing -->
                                <div v-else-if="(attachment.file_type === 'video' || getFileTypeFromMime(attachment.mime_type) === 'video') && attachment.processing" class="rounded-lg overflow-hidden max-w-xs bg-gray-800 relative">
                                    <div class="h-48 flex items-center justify-center">
                                        <span class="pi pi-spin pi-spinner text-white text-2xl"></span>
                                    </div>
                                </div>
                                <!-- Audio -->
                                <div v-else-if="(attachment.file_type === 'audio' || getFileTypeFromMime(attachment.mime_type) === 'audio') && !attachment.processing" class="flex items-center gap-2 bg-gray-800 rounded-lg p-3 max-w-xs">
                                    <span class="pi pi-volume-up text-2xl text-gray-400"></span>
                                    <div class="flex-1">
                                        <div class="text-sm text-white">{{ attachment.file_name }}</div>
                                        <audio
                                            v-if="attachment.file_url"
                                            :src="attachment.file_url"
                                            controls
                                            class="w-full mt-1"
                                            :data-message-id="message.id"
                                            @play="handleAudioPlay(message.id, $event.target)"
                                            @ended="handleAudioEnded(message.id, $event.target)"
                                        ></audio>
                                    </div>
                                </div>
                                <!-- Audio Processing -->
                                <div v-else-if="(attachment.file_type === 'audio' || getFileTypeFromMime(attachment.mime_type) === 'audio') && attachment.processing" class="flex items-center gap-2 bg-gray-800 rounded-lg p-3 max-w-xs">
                                    <span class="pi pi-volume-up text-2xl text-gray-400"></span>
                                    <div class="flex-1">
                                        <div class="text-sm text-white">{{ attachment.file_name }}</div>
                                        <div class="mt-1">
                                            <span class="pi pi-spin pi-spinner text-white text-xs"></span>
                                        </div>
                                    </div>
                                </div>
                                <!-- File -->
                                <div v-else class="flex items-center gap-2 bg-gray-800 rounded-lg p-3 max-w-xs cursor-pointer hover:bg-gray-700"
                                     @click="attachment.file_url && window.open(attachment.file_url, '_blank')">
                                    <span class="pi pi-file text-2xl text-gray-400"></span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm text-white truncate">{{ attachment.file_name }}</div>
                                        <div class="text-xs text-gray-400">{{ formatFileSize(attachment.file_size) }}</div>
                                    </div>
                                    <span v-if="attachment.file_url" class="pi pi-download text-gray-400"></span>
                                    <div v-if="attachment.processing" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center rounded-lg">
                                        <span class="pi pi-spin pi-spinner text-white"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Message Actions (Edit/Delete) -->
                        <div v-if="message.user_id === $page.props.auth.user.id" class="absolute top-0 right-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <div class="flex gap-1 bg-gray-800 rounded p-1">
                                <span
                                    v-if="canEditMessage(message)"
                                    class="pi pi-pencil text-xs cursor-pointer hover:text-blue-400"
                                    v-tooltip="'Edit'"
                                    @click="startEditMessage(message)"
                                ></span>
                                <span
                                    v-if="canDeleteMessage(message)"
                                    class="pi pi-trash text-xs cursor-pointer hover:text-red-400"
                                    v-tooltip="'Delete'"
                                    @click="deleteMessage(message.id)"
                                ></span>
                            </div>
                        </div>

                        <!-- Message Text -->
                        <div v-if="editingMessage?.id === message.id" class="mb-2">
                            <input
                                v-model="editingMessageText"
                                class="w-full bg-gray-700 text-white px-2 py-1 rounded"
                                @keyup.enter="saveEditMessage"
                                @keyup.esc="cancelEditMessage"
                                autofocus
                            />
                            <div class="flex gap-2 mt-1">
                                <button
                                    @click="saveEditMessage"
                                    class="text-xs text-blue-400 hover:text-blue-300"
                                >
                                    Save
                                </button>
                                <button
                                    @click="cancelEditMessage"
                                    class="text-xs text-gray-400 hover:text-gray-300"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                        <div v-else-if="message.is_deleted" class="text-gray-500 italic">
                            This message was deleted
                        </div>
                        <div v-else-if="message.message">{{ message.message }}</div>
                        <span v-if="message.is_edited && !editingMessage" class="text-xs text-gray-400 ml-1">(edited)</span>
                        <span class="relative top-1 ml-2 inline-flex gap-[3px]">
                            <span class="inline-block select-none text-xs text-gray-200/70">{{ formatTimeFromTimestamp(message.created_at) }}</span>
                            <span
                                v-if="message.user_id === $page.props.auth.user.id"
                                class="relative top-[3px] inline-block select-none text-xs text-blue-400"
                                :class="{ 'text-gray-200/70': message.status !== 'read' }"
                            >
                                <svg viewBox="0 0 16 11" height="11" width="16" preserveAspectRatio="xMidYMid meet" class="" fill="none">
                                    <title>{{ message.status }}</title>
                                    <path
                                        d="M11.0714 0.652832C10.991 0.585124 10.8894 0.55127 10.7667 0.55127C10.6186 0.55127 10.4916 0.610514 10.3858 0.729004L4.19688 8.36523L1.79112 6.09277C1.7488 6.04622 1.69802 6.01025 1.63877 5.98486C1.57953 5.95947 1.51817 5.94678 1.45469 5.94678C1.32351 5.94678 1.20925 5.99544 1.11192 6.09277L0.800883 6.40381C0.707784 6.49268 0.661235 6.60482 0.661235 6.74023C0.661235 6.87565 0.707784 6.98991 0.800883 7.08301L3.79698 10.0791C3.94509 10.2145 4.11224 10.2822 4.29844 10.2822C4.40424 10.2822 4.5058 10.259 4.60313 10.2124C4.70046 10.1659 4.78086 10.1003 4.84434 10.0156L11.4903 1.59863C11.5623 1.5013 11.5982 1.40186 11.5982 1.30029C11.5982 1.14372 11.5348 1.01888 11.4078 0.925781L11.0714 0.652832ZM8.6212 8.32715C8.43077 8.20866 8.2488 8.09017 8.0753 7.97168C7.99489 7.89128 7.8891 7.85107 7.75791 7.85107C7.6098 7.85107 7.4892 7.90397 7.3961 8.00977L7.10411 8.33984C7.01947 8.43717 6.97715 8.54508 6.97715 8.66357C6.97715 8.79476 7.0237 8.90902 7.1168 9.00635L8.1959 10.0791C8.33132 10.2145 8.49636 10.2822 8.69102 10.2822C8.79681 10.2822 8.89838 10.259 8.99571 10.2124C9.09304 10.1659 9.17556 10.1003 9.24327 10.0156L15.8639 1.62402C15.9358 1.53939 15.9718 1.43994 15.9718 1.32568C15.9718 1.1818 15.9125 1.05697 15.794 0.951172L15.4386 0.678223C15.3582 0.610514 15.2587 0.57666 15.1402 0.57666C14.9964 0.57666 14.8715 0.635905 14.7657 0.754395L8.6212 8.32715Z"
                                        fill="currentColor"
                                    ></path>
                                </svg>
                            </span>
                        </span>
                        
                        <!-- Reactions -->
                        <div v-if="message.reactions && message.reactions.length > 0" class="mt-2 flex flex-wrap gap-1">
                            <button
                                v-for="reaction in message.reactions"
                                :key="reaction.reaction"
                                @click="toggleReaction(message.id, reaction.reaction)"
                                class="flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-gray-700 hover:bg-gray-600"
                                :class="{
                                    'bg-blue-500': reaction.users?.some(u => u.id === $page.props.auth.user.id)
                                }"
                            >
                                <span>{{ reaction.reaction }}</span>
                                <span v-if="reaction.count > 1">{{ reaction.count }}</span>
                            </button>
                            <button
                                @click="showingReactionPicker = showingReactionPicker === message.id ? null : message.id"
                                class="px-2 py-1 rounded-full text-xs bg-gray-700 hover:bg-gray-600"
                            >
                                +
                            </button>
                            <div
                                v-if="showingReactionPicker === message.id"
                                class="absolute bottom-full left-0 mb-2 bg-gray-800 rounded-lg p-2 flex gap-2 z-10"
                            >
                                <button
                                    v-for="emoji in ['👍', '❤️', '😂', '😮', '😢', '🙏']"
                                    :key="emoji"
                                    @click="addReactionToMessage(message.id, emoji); showingReactionPicker = null"
                                    class="text-xl hover:scale-125 transition-transform"
                                >
                                    {{ emoji }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="mt-2">
                            <button
                                @click="showingReactionPicker = showingReactionPicker === message.id ? null : message.id"
                                class="opacity-0 group-hover:opacity-100 px-2 py-1 rounded-full text-xs bg-gray-700 hover:bg-gray-600 transition-opacity"
                            >
                                Add reaction
                            </button>
                            <div
                                v-if="showingReactionPicker === message.id"
                                class="absolute bottom-full left-0 mb-2 bg-gray-800 rounded-lg p-2 flex gap-2 z-10"
                            >
                                <button
                                    v-for="emoji in ['👍', '❤️', '😂', '😮', '😢', '🙏']"
                                    :key="emoji"
                                    @click="addReactionToMessage(message.id, emoji); showingReactionPicker = null"
                                    class="text-xl hover:scale-125 transition-transform"
                                >
                                    {{ emoji }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="main-footer sticky bottom-0 left-0 right-0 text-gray-400">
                <!-- Attachment Preview -->
                <div v-if="selectedAttachments.length > 0" class="border-t border-gray-700 bg-gray-800 px-4 py-2">
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="(file, index) in selectedAttachments"
                            :key="index"
                            class="relative flex items-center gap-2 rounded-lg bg-gray-700 p-2"
                        >
                            <div v-if="getFileType(file) === 'image'" class="h-16 w-16 overflow-hidden rounded">
                                <img :src="getPreviewUrl(file)" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="flex h-16 w-16 items-center justify-center rounded bg-gray-600">
                                <span class="pi pi-file text-2xl"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="truncate text-sm text-white">{{ file.name }}</div>
                                <div class="text-xs text-gray-400">{{ formatFileSize(file.size) }}</div>
                            </div>
                            <span
                                @click="removeAttachment(index)"
                                class="pi pi-times cursor-pointer text-red-400 hover:text-red-300"
                            ></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center px-4 py-1">
                    <Popover ref="emojiPopover">
                        <emoji-picker></emoji-picker>
                    </Popover>
                    <div class="flex-none">
                        <span @click="toggleEmojiPicker" class="pi pi-face-smile -mt-1 inline h-6 w-6 cursor-pointer"> </span>
                        <input
                            ref="attachmentInput"
                            type="file"
                            multiple
                            accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar"
                            @change="handleAttachmentSelect"
                            class="hidden"
                        />
                        <span 
                            @click="attachmentInput?.click()" 
                            class="pi pi-paperclip -mt-1 ml-2 inline h-6 w-6 cursor-pointer"
                            v-tooltip="'Attach File'"
                        > </span>
                        <span
                            v-if="!isRecording"
                            @mousedown="startVoiceRecording"
                            @touchstart="startVoiceRecording"
                            class="pi pi-microphone -mt-1 ml-2 inline h-6 w-6 cursor-pointer text-red-400 hover:text-red-300"
                            v-tooltip="'Hold to record voice'"
                        > </span>
                    </div>
                    <div class="flex-grow">
                        <div class="w-full px-4 py-2">
                            <!-- Recording UI -->
                            <div v-if="isRecording" class="flex items-center gap-3 bg-red-600 rounded-full px-4 py-3">
                                <div class="flex items-center gap-2 flex-1">
                                    <span class="pi pi-circle-fill text-white animate-pulse" style="font-size: 0.75rem"></span>
                                    <span class="text-white text-sm font-semibold">{{ formatRecordingTime(recordingTime) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        @click="cancelVoiceRecording"
                                        class="pi pi-times text-white cursor-pointer hover:text-gray-200"
                                        v-tooltip="'Cancel'"
                                    ></span>
                                    <span
                                        @click="stopVoiceRecording"
                                        class="pi pi-check text-white cursor-pointer hover:text-gray-200"
                                        v-tooltip="'Stop & Send'"
                                    ></span>
                                </div>
                            </div>
                            <!-- Voice recording ready to send -->
                            <div v-else-if="audioBlob && recordingTime > 0" class="flex items-center gap-3 bg-gray-700 rounded-full px-4 py-3">
                                <div class="flex items-center gap-2 flex-1">
                                    <span class="pi pi-volume-up text-gray-300"></span>
                                    <span class="text-white text-sm">{{ formatRecordingTime(recordingTime) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        @click="cancelVoiceRecording"
                                        class="pi pi-times text-gray-400 cursor-pointer hover:text-red-400"
                                        v-tooltip="'Cancel'"
                                    ></span>
                                    <span
                                        @click="sendVoiceRecording"
                                        :class="{ 'pi pi-spin pi-spinner': isSendingMessage }"
                                        class="pi pi-send text-green-400 cursor-pointer hover:text-green-300"
                                        v-tooltip="'Send'"
                                    ></span>
                                </div>
                            </div>
                            <!-- Normal message input -->
                            <form v-else @submit.prevent="sendMessage">
                                <div class="relative text-gray-600 focus-within:text-gray-200">
                                    <input
                                        ref="messageInput"
                                        class="message-input w-full select-none rounded-full bg-gray-700 py-3 pl-5 text-sm text-white focus:bg-gray-600/50 focus:outline-none"
                                        placeholder="Type a message"
                                        autocomplete="off"
                                        v-model="currentMessage"
                                        :disabled="isSendingMessage || isRecording"
                                    />
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="flex-none text-right">
                        <span
                            v-if="!isRecording && !audioBlob"
                            @click="sendMessage"
                            :class="{ 'pi pi-spin pi-spinner': isSendingMessage }"
                            class="pi pi-send mt-2 inline cursor-pointer"
                            style="font-size: x-large"
                        >
                        </span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add new contact Dialog -->
    <Dialog v-model:visible="showAddNewContactDialog" :draggable="false" modal header="Add Contact" :style="{ width: '25rem' }">
        <form @submit.prevent="handleAddNewContact">
            <div class="relative text-gray-600 focus-within:text-gray-200">
                <input
                    v-model="newContactEmail"
                    class="message-input w-full rounded-full bg-gray-700 py-3 pl-5 text-sm text-white focus:bg-gray-600/50 focus:outline-none"
                    placeholder="Email of your contact"
                />
                <Button type="submit" :icon="isAddingNewContact ? 'pi pi-spin pi-spinner' : ''" label="Add Contact" class="mt-3 w-full" />
            </div>
        </form>
    </Dialog>

    <!-- Global Search Dialog -->
    <SearchDialog
        v-model:visible="showGlobalSearch"
        @result-selected="handleSearchResultSelected"
    />

    <!-- Search Result Summary Dialog -->
    <SearchResultSummary
        v-model:visible="showSearchSummary"
        :result="selectedSearchResult"
        @jump-to-message="jumpToSearchResult"
    />
</template>

<style>
.p-message-error {
    word-break: break-word;
}
.p-toast {
    max-width: calc(100vw - 40px);
    word-break: break-word;
}

.p-popover-content {
    padding: 0 !important;
}

.message-item {
    transition: background-color 0.2s;
}

.highlight-message {
    background-color: rgba(59, 130, 246, 0.2);
    animation: highlight 2s ease-out;
}

@keyframes highlight {
    0% {
        background-color: rgba(59, 130, 246, 0.5);
    }
    100% {
        background-color: transparent;
    }
}

/* Voice recording animation */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
