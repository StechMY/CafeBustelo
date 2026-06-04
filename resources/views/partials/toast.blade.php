@php
    $toasts = [];

    // if (session('success')) {
    //     $toasts[] = [
    //         'type' => 'success',
    //         'message' => session('success'),
    //     ];
    // }

    // if (session('error')) {
    //     $toasts[] = [
    //         'type' => 'error',
    //         'message' => session('error'),
    //     ];
    // }

    if ($errors->any()) {
        $toasts[] = [
            'type' => 'error',
            'message' => 'Please check the form errors and try again.',
        ];
    }
@endphp

@if (count($toasts))
    <div
        x-data="{
            toasts: @js($toasts),
            remove(index) {
                this.toasts.splice(index, 1)
            }
        }"
        x-init="setTimeout(() => toasts = [], 4500)"
        x-cloak
        class="fixed right-4 top-4 z-[9999] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3 sm:right-6 sm:top-6 sm:w-full"
    >
        <template x-for="(toast, index) in toasts" :key="index">
            <div
                x-show="true"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
                x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="flex items-start gap-3 rounded-2xl border bg-white px-4 py-3 text-sm font-bold shadow-xl ring-1 ring-black/5"
                :class="toast.type === 'success' ? 'border-green-200 text-green-700' : 'border-red-200 text-red-700'"
            >
                <div
                    class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs text-white"
                    :class="toast.type === 'success' ? 'bg-green-500' : 'bg-red-500'"
                >
                    <span x-text="toast.type === 'success' ? '✓' : '!' "></span>
                </div>

                <div class="min-w-0 flex-1 leading-5" x-text="toast.message"></div>

                <button
                    type="button"
                    class="-mr-1 rounded-full px-2 py-1 text-lg leading-none text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                    @click="remove(index)"
                    aria-label="Close notification"
                >
                    ×
                </button>
            </div>
        </template>
    </div>
@endif
