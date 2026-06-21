<div
    x-show="true"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-2"
    x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0 scale-95"
    :class="{
        'border-destructive bg-destructive text-destructive-foreground': toast.variant === 'destructive',
        'bg-background border': toast.variant !== 'destructive'
    }"
    class="group pointer-events-auto relative flex w-full items-center justify-between space-x-4 overflow-hidden rounded-md border p-6 pr-8 shadow-lg transition-all mt-4"
>
    <div class="grid gap-1">
        <template x-if="toast.title">
            <div class="text-sm font-semibold" x-text="toast.title"></div>
        </template>
        <template x-if="toast.description">
            <div class="text-sm opacity-90" x-text="toast.description"></div>
        </template>
    </div>
    <button
        @click="remove(toast.id)"
        class="absolute right-2 top-2 rounded-md p-1 text-foreground/50 opacity-0 transition-opacity hover:text-foreground focus:opacity-100 focus:outline-none focus:ring-2 group-hover:opacity-100"
        :class="{
            'text-red-300 hover:text-red-50 focus:ring-red-400 focus:ring-offset-red-600': toast.variant === 'destructive',
        }"
    >
        <x-lucide-x class="h-4 w-4" />
    </button>
</div>
