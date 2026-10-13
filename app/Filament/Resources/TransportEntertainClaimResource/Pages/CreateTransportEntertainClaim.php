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
        $data['user_id'] = $data['user_id'] ?? $user?->id;
        $data['employee_id'] = $data['employee_id'] ?? $user?->getEffectiveEmployeeId();
        $data['claim_category'] = 'transport_entertain';
        $data['claim_type'] = $data['claim_type'] ?? 'Entertain';
        $emp = !empty($data['employee_id']) ? \App\Models\Employee::find($data['employee_id']) : null;
        $isAsm = $user?->isAsm() || $emp?->isAsm();
        $isRgm = $user?->isRgm() || $emp?->isRgm();

        if ($isAsm) {
            $data['approval_status'] = 'ACC_ASM';
            $data['approved_by_asm_id'] = $user?->id;
            $data['approved_by_asm_at'] = now();
        } elseif ($isRgm) {
            $data['approval_status'] = 'ACC_RGM';
            $data['approved_by_rgm_id'] = $user?->id;
            $data['approved_by_rgm_at'] = now();
        } else {
            $data['approval_status'] = $data['approval_status'] ?? 'DIAJUKAN';
        }

        $data['disbursement_status'] = $data['disbursement_status'] ?? 'Belum Dicairkan';
        if ($user && !$user->isAdmin() && !$user->isSuperAdmin()) {
            $data['region'] = $user->getOperationalRegion();
            $data['homebase'] = $data['homebase'] ?? $user->homebase ?? $user->employee?->homebase;
        }

        if (!empty($data['items']) && is_array($data['items'])) {
            $firstItem = reset($data['items']);
            $data['purpose'] = $data['purpose'] ?? ($firstItem['purpose'] ?? '');
            $data['note'] = $data['note'] ?? ($firstItem['note'] ?? '');
            $data['amount'] = array_sum(array_column($data['items'], 'amount')) ?: ($data['amount'] ?? 0);

            // Bidirectional sync for city
            $firstCity = $firstItem['city'] ?? null;
            if (empty($data['city']) && !empty($firstCity)) {
                $data['city'] = $firstCity;
            }
            if (!empty($data['city'])) {
                foreach ($data['items'] as $idx => $it) {
                    if (empty($it['city'])) {
                        $data['items'][$idx]['city'] = $data['city'];
                    }
                }
            }

            // Sync custom bank if filled in Karangan Bunga item
            foreach ($data['items'] as $idx => $it) {
                if (($it['entertain_subtype'] ?? '') === 'Karangan Bunga' || str_contains(strtolower($it['purpose'] ?? ''), 'karangan bunga')) {
                    if (!empty($it['custom_bank_account_name']) && empty($data['custom_bank_account_name'])) {
                        $data['custom_bank_account_name'] = $it['custom_bank_account_name'];
                    }
                    if (!empty($it['custom_bank_name']) && empty($data['custom_bank_name'])) {
                        $data['custom_bank_name'] = $it['custom_bank_name'];
                    }
                    if (!empty($it['custom_bank_account_number']) && empty($data['custom_bank_account_number'])) {
                        $data['custom_bank_account_number'] = $it['custom_bank_account_number'];
                    }
                }

                // Auto-fill attendance perihal, tempat, and tanggal from item data so no double input
                if (empty($it['attendance_purpose']) && !empty($it['purpose'])) {
                    $data['items'][$idx]['attendance_purpose'] = $it['purpose'];
                }
                if (empty($it['attendance_place']) && !empty($it['note'])) {
                    $data['items'][$idx]['attendance_place'] = $it['note'];
                }
                if (empty($it['attendance_date']) && !empty($it['receipt_date'])) {
                    $data['items'][$idx]['attendance_date'] = $it['receipt_date'];
                }
            }
        } elseif (empty($data['items'])) {
            $data['amount'] = $data['amount'] ?? 0;
            $data['items'] = [
                [
                    'claim_type' => $data['claim_type'] ?? 'Entertain',
                    'entertain_subtype' => 'Makan',
                    'receipt_date' => $data['claim_date'] ?? now()->toDateString(),
                    'purpose' => $data['purpose'] ?? '',
                    'note' => $data['note'] ?? '',
                    'amount' => $data['amount'] ?? 0,
                ]
            ];
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
