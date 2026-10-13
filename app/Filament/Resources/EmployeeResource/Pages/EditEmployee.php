<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $isAsmOrRgm = false;
        if (!empty($data['role_id'])) {
            $role = \App\Models\Role::find($data['role_id']);
            if ($role) {
                $code = strtoupper($role->code ?? '');
                $name = strtoupper($role->name ?? '');
                if (str_contains($code, 'ASM') || str_contains($code, 'RGM') || str_contains($name, 'ASM') || str_contains($name, 'RGM') || str_contains($name, 'AREA SALES') || str_contains($name, 'REGIONAL GENERAL')) {
                    $isAsmOrRgm = true;
                }
            }
        }
        if (!$isAsmOrRgm && !empty($data['position_id'])) {
            $pos = \App\Models\Position::find($data['position_id']);
            if ($pos) {
                $name = strtoupper($pos->name ?? '');
                if (str_contains($name, 'ASM') || str_contains($name, 'RGM') || str_contains($name, 'AREA SALES') || str_contains($name, 'REGIONAL GENERAL')) {
                    $isAsmOrRgm = true;
                }
            }
        }
        if (!$isAsmOrRgm && $this->record) {
            $isAsmOrRgm = $this->record->isAsm() || $this->record->isRgm();
        }

        if (!$isAsmOrRgm) {
            $data['bbm_budget'] = 0;
            $data['entertain_budget'] = 0;
        } else {
            $data['bbm_budget'] = isset($data['bbm_budget']) && $data['bbm_budget'] !== '' ? (float)$data['bbm_budget'] : 0;
            $data['entertain_budget'] = isset($data['entertain_budget']) && $data['entertain_budget'] !== '' ? (float)$data['entertain_budget'] : null;
        }

        $data['perdin_budget'] = isset($data['perdin_budget']) && $data['perdin_budget'] !== '' ? (float)$data['perdin_budget'] : 0;
        $data['transport_budget'] = isset($data['transport_budget']) && $data['transport_budget'] !== '' ? (float)$data['transport_budget'] : 0;
        $data['perdin_meal_allowance'] = isset($data['perdin_meal_allowance']) && $data['perdin_meal_allowance'] !== '' ? (float)$data['perdin_meal_allowance'] : 100000;
        $data['perdin_lodging_allowance'] = isset($data['perdin_lodging_allowance']) && $data['perdin_lodging_allowance'] !== '' ? (float)$data['perdin_lodging_allowance'] : 250000;
        $data['perdin_transport_budget'] = isset($data['perdin_transport_budget']) && $data['perdin_transport_budget'] !== '' ? (float)$data['perdin_transport_budget'] : 500000;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
