<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import InputText from 'primevue/inputtext';

const props = defineProps({
    chatId: {
        type: Number,
        required: true,
    },
    visible: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:visible', 'result-selected']);

const searchQuery = ref('');
const searchResults = ref([]);
const isLoading = ref(false);
const searchSummary = ref('');

const performSearch = async () => {
    if (!searchQuery.value.trim() || isLoading.value) return;

    isLoading.value = true;
    searchResults.value = [];

    try {
        const response = await axios.get(`/search/conversation/${props.chatId}`, {
            params: { q: searchQuery.value },
        });

        searchResults.value = response.data.results;
        searchSummary.value = response.data.summary;
    } catch (error) {
        console.error('Search error:', error);
    } finally {
        isLoading.value = false;
    }
};

const selectResult = (result) => {
    emit('result-selected', result);
    emit('update:visible', false);
    searchQuery.value = '';
    searchResults.value = [];
};

const closeSearch = () => {
    emit('update:visible', false);
    searchQuery.value = '';
    searchResults.value = [];
};

// Debounce search
let searchTimeout;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (searchQuery.value.trim()) {
            performSearch();
        } else {
            searchResults.value = [];
        }
    }, 500);
});

// Watch visible prop
watch(() => props.visible, (newVal) => {
    if (!newVal) {
        searchQuery.value = '';
        searchResults.value = [];
    }
});
</script>

<template>
    <div v-if="visible" class="conversation-search fixed top-0 left-0 right-0 bg-white border-b shadow-md z-50 p-4">
        <div class="flex items-center gap-2">
            <InputText
                v-model="searchQuery"
                placeholder="Search in conversation..."
                class="flex-1"
                @keyup.enter="performSearch"
                autofocus
            />
            <button
                @click="closeSearch"
                class="px-3 py-2 text-gray-600 hover:text-gray-800"
            >
                ✕
            </button>
        </div>

        <div v-if="isLoading" class="text-center py-2 text-sm text-gray-500">
            <i class="pi pi-spin pi-spinner"></i> Searching...
        </div>

        <div v-else-if="searchSummary" class="text-sm text-gray-600 mt-2">
            {{ searchSummary }}
        </div>

        <div v-if="searchResults.length > 0" class="search-results max-h-64 overflow-y-auto mt-2">
            <div
                v-for="result in searchResults"
                :key="result.id"
                class="search-result-item p-2 border-b cursor-pointer hover:bg-gray-100"
                @click="selectResult(result)"
            >
                <div class="text-sm text-gray-800" v-html="result.highlighted_text || result.preview"></div>
                <div class="text-xs text-gray-400 mt-1">
                    {{ new Date(result.created_at).toLocaleString() }}
                </div>
            </div>
        </div>

        <div v-else-if="searchQuery && !isLoading" class="text-center py-2 text-sm text-gray-500">
            No results found
        </div>
    </div>
</template>

<style scoped>
.conversation-search {
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.search-result-item {
    transition: background-color 0.2s;
}
</style>

