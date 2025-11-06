<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const props = defineProps({
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
        const response = await axios.get('/search/global', {
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

const closeDialog = () => {
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
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="closeDialog"
        modal
        header="Global Search"
        :style="{ width: '600px' }"
    >
        <div class="search-dialog">
            <div class="search-input mb-4">
                <InputText
                    v-model="searchQuery"
                    placeholder="Search messages..."
                    class="w-full"
                    @keyup.enter="performSearch"
                />
            </div>

            <div v-if="isLoading" class="text-center py-4">
                <i class="pi pi-spin pi-spinner"></i> Searching...
            </div>

            <div v-else-if="searchSummary" class="mb-3 text-sm text-gray-600">
                {{ searchSummary }}
            </div>

            <div v-if="searchResults.length > 0" class="search-results max-h-96 overflow-y-auto">
                <div
                    v-for="result in searchResults"
                    :key="result.id"
                    class="search-result-item p-3 border-b cursor-pointer hover:bg-gray-100"
                    @click="selectResult(result)"
                >
                    <div class="font-semibold text-gray-800">{{ result.title }}</div>
                    <div class="text-sm text-gray-600 mt-1" v-html="result.highlighted_text || result.preview"></div>
                    <div class="text-xs text-gray-400 mt-1">
                        {{ new Date(result.created_at).toLocaleString() }}
                    </div>
                </div>
            </div>

            <div v-else-if="searchQuery && !isLoading" class="text-center py-4 text-gray-500">
                No results found
            </div>
        </div>
    </Dialog>
</template>

<style scoped>
.search-dialog {
    min-height: 200px;
}

.search-result-item {
    transition: background-color 0.2s;
}
</style>

