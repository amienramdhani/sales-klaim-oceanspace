<?php

namespace App\Filament\Resources\VehicleServiceClaimResource\Pages;

use App\Filament\Resources\VehicleServiceClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicleServiceClaim extends EditRecord
{
    protected static string $resource = VehicleServiceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['items']) && !empty($data['amount'])) {
            $data['items'] = [
                [
                    'item_name' => 'Service Motor',
                    'purpose' => is_array($data['purpose'] ?? null) ? implode(', ', $data['purpose']) : ($data['purpose'] ?? 'Service Rutin Sepeda Motor'),
                    'amount' => (float)$data['amount'],
                ]
            ];
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $sum = 0;
            $names = [];
            foreach ($data['items'] as $item) {
                $rawAmt = $item['amount'] ?? 0;
                $amt = is_string($rawAmt) ? (float)preg_replace('/[^0-9]/', '', $rawAmt) : (float)$rawAmt;
                $sum += $amt;
                $name = $item['item_name'] ?? ($item['name'] ?? null);
                if (!empty($name)) $names[] = $name;
            }
            if ($sum > 0 || empty($data['amount'])) {
                $data['amount'] = $sum;
            }
            if (!empty($names)) {
                $data['purpose'] = implode(', ', array_unique($names));
            }
        }

        $emp = !empty($data['employee_id']) ? \App\Models\Employee::find($data['employee_id']) : null;
        if ($emp && (empty($data['region']) || str_contains($data['region'], ','))) {
            $data['region'] = $emp->region ?: ($emp->homebase ?: $data['region']);
            $data['homebase'] = $emp->homebase ?: ($emp->region ?: ($data['homebase'] ?? null));
        }
        if (!empty($data['branch_id'])) {
            $branch = \App\Models\Branch::find($data['branch_id']);
            if ($branch) {
                if (empty($data['reffnote'])) $data['reffnote'] = $branch->reffnote;
                if (empty($data['brand'])) $data['brand'] = $branch->brand;
                if (empty($data['city'])) $data['city'] = $branch->city;
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
