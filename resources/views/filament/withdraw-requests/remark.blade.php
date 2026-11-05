@php($remark = $getState())
@if (filled($remark))
    <x-filament::modal
        :id="'withdraw-remark-' . $getRecord()->getKey()"
        :heading="__('withdraw.admin_remark') . ' #' . $getRecord()->getKey()"
        width="lg"
        teleport="body"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                icon="heroicon-s-chat-bubble-left-ellipsis"
                color="warning"
                :label="__('withdraw.admin_remark') . ' #' . $getRecord()->getKey()"
                :tooltip="$remark"
                aria-haspopup="dialog"
            />
        </x-slot>

        <p style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $remark }}</p>
    </x-filament::modal>
@endif
