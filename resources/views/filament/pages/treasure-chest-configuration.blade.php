<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
            {{ __('filament-actions::edit.single.modal.actions.save.label') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
