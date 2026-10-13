<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="create"
    >
        {{-- Tombol Simpan & Batal di Bagian Paling Atas Form --}}
        <div class="mb-5 p-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center justify-between gap-3">
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                Lengkapi formulir di bawah lalu klik Simpan:
            </span>
            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </div>

        {{ $this->form }}

        {{-- Tombol Simpan & Batal di Bagian Bawah Form --}}
        <div class="mt-5 pt-3 border-t border-gray-200 dark:border-gray-800">
            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </div>
    </x-filament-panels::form>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
