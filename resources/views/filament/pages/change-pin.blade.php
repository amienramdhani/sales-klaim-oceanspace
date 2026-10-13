<x-filament-panels::page>
    <div class="max-w-xl mx-auto w-full">
        <form wire:submit="updatePin" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end gap-3 pt-2">
                <x-filament::button type="submit" size="lg" color="primary" icon="heroicon-o-check-circle">
                    Simpan PIN & Lanjutkan
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
