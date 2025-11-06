<script setup>
import { ref } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    result: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:visible', 'jump-to-message']);

const closeDialog = () => {
    emit('update:visible', false);
};

const jumpToMessage = () => {
    emit('jump-to-message', props.result);
    emit('update:visible', false);
};
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="closeDialog"
        modal
        header="Search Result"
        :style="{ width: '500px' }"
    >
        <div v-if="result" class="search-result-summary">
            <div class="mb-4">
                <div class="font-semibold text-gray-800 mb-2">{{ result.title || 'Message' }}</div>
                <div class="text-sm text-gray-600 mb-2" v-html="result.highlighted_text || result.preview"></div>
                <div class="text-xs text-gray-400">
                    {{ new Date(result.created_at).toLocaleString() }}
                </div>
            </div>

            <div v-if="result.context && (result.context.before?.length || result.context.after?.length)" class="context-section mb-4">
                <div class="text-sm font-semibold text-gray-700 mb-2">Context:</div>
                
                <div v-if="result.context.before?.length" class="context-before mb-2">
                    <div class="text-xs text-gray-500 mb-1">Before:</div>
                    <div class="text-xs text-gray-600 bg-gray-50 p-2 rounded">
                        <div v-for="(msg, index) in result.context.before" :key="index" class="mb-1">
                            {{ msg }}
                        </div>
                    </div>
                </div>

                <div v-if="result.context.after?.length" class="context-after">
                    <div class="text-xs text-gray-500 mb-1">After:</div>
                    <div class="text-xs text-gray-600 bg-gray-50 p-2 rounded">
                        <div v-for="(msg, index) in result.context.after" :key="index" class="mb-1">
                            {{ msg }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" @click="closeDialog" severity="secondary" />
                <Button label="Jump to Message" @click="jumpToMessage" />
            </div>
        </div>
    </Dialog>
</template>

<style scoped>
.search-result-summary {
    min-height: 150px;
}

.context-section {
    border-top: 1px solid #e5e7eb;
    padding-top: 1rem;
}
</style>

