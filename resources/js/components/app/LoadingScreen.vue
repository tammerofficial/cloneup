<script setup>
import ProgressBar from 'primevue/progressbar';

defineProps({
    loadingValue: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <div class="flex h-screen items-center justify-center bg-[#131C21]">
        <div class="text-center text-white">
            <div class="mb-4 flex justify-center">
                <span class="pi pi-whatsapp" style="font-size: xx-large"></span>
            </div>
            <h1 class="text-lg font-semibold">WhatsApp Clone</h1>
            <div class="relative mx-auto mt-2 h-1 w-40 overflow-hidden rounded-full bg-gray-700">
                <ProgressBar :value="loadingValue"></ProgressBar>
            </div>
            <p class="mt-2 flex items-center justify-center text-sm text-gray-400">
                <span class="pi pi-info-circle mr-1"></span>
                End-to-End encryption
            </p>
        </div>
    </div>
</template>
