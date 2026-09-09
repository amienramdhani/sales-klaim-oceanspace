<?php

namespace App\Filament\Resources\TransportEntertainClaimResource\Pages;

use App\Filament\Resources\TransportEntertainClaimResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransportEntertainClaim extends CreateRecord
{
    protected static string $resource = TransportEntertainClaimResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user && $user->isFieldUser() && !$user->isAdmin() && !$user->isSuperAdmin()) {
            $data['user_id'] = $user->id;
            $data['employee_id'] = $data['employee_id'] ?? $user->getEffectiveEmployeeId();
            $data['claim_category'] = 'transport_entertain';
            $data['claim_type'] = 'Entertain';
            $data['approval_status'] = 'DIAJUKAN';
            $data['region'] = $user->getOperationalRegion();
            $data['homebase'] = $data['homebase'] ?? $user->homebase ?? $user->employee?->homebase;
            $data['amount'] = $data['amount'] ?? 0;

            if (empty($data['items'])) {
                $data['items'] = [
                    [
                        'claim_type' => 'Entertain',
                        'entertain_subtype' => 'Makan',
                        'receipt_date' => $data['claim_date'] ?? now()->toDateString(),
                        'purpose' => $data['purpose'] ?? '',
                        'note' => $data['note'] ?? '',
                        'amount' => $data['amount'] ?? 0,
                    ]
                ];
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
