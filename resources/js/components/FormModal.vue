<script setup>
import { watch } from 'vue'

const props = defineProps({
    title: { type: String, default: '' },
    show: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
})
const emit = defineEmits(['close'])

const sizeClasses = {
    sm: 'max-w-md',
    md: 'max-w-2xl',
    lg: 'max-w-4xl',
}

watch(() => props.show, (val) => {
    if (val) {
        document.body.style.overflow = 'hidden'
    } else {
        document.body.style.overflow = ''
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40 transition-opacity"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full transition-all" :class="sizeClasses[size]">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">{{ title }}</h3>
                        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                        <slot />
                    </div>
                    <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-active > div:last-child, .modal-leave-active > div:last-child {
    transition: transform 0.2s ease;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
.modal-enter-from > div:last-child, .modal-leave-to > div:last-child {
    transform: scale(0.95);
}
</style>
