<script setup>
defineProps({
    title: String,
    value: [Number, String],
    icon: String,
    trend: { type: String, default: '' },
    color: { type: String, default: 'blue' },
})
</script>

<template>
    <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">{{ title }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ value }}</p>
                <p v-if="trend" class="text-xs text-green-600 mt-1">{{ trend }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center" :class="'bg-' + color + '-50'">
                <span class="text-indofilter" v-html="icon"></span>
            </div>
        </div>
    </div>
</template>
