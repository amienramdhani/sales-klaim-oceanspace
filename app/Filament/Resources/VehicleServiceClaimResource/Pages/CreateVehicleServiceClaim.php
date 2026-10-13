<?php

namespace App\Filament\Resources\VehicleServiceClaimResource\Pages;

use App\Filament\Resources\VehicleServiceClaimResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleServiceClaim extends CreateRecord
{
    protected static string $resource = VehicleServiceClaimResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user) {
            $data['user_id'] = $data['user_id'] ?? $user->id;
            $data['employee_id'] = $data['employee_id'] ?? $user->getEffectiveEmployeeId();
            $data['claim_category'] = 'service_motor';
            $data['claim_type'] = 'Service Motor';
            $data['is_perdin'] = false;
            
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
            } else {
                $data['amount'] = (float)($data['amount'] ?? 0);
            }

            $emp = !empty($data['employee_id']) ? \App\Models\Employee::find($data['employee_id']) : null;
            $isAsm = $user->isAsm() || $emp?->isAsm();
            $isRgm = $user->isRgm() || $emp?->isRgm();

            if ($isAsm) {
                $data['approval_status'] = 'ACC_ASM';
                $data['approved_by_asm_id'] = $user->id;
                $data['approved_by_asm_at'] = now();
            } elseif ($isRgm) {
                $data['approval_status'] = 'ACC_RGM';
                $data['approved_by_rgm_id'] = $user->id;
                $data['approved_by_rgm_at'] = now();
            } else {
                $data['approval_status'] = $data['approval_status'] ?? 'DIAJUKAN';
            }

            // Resolve Region & Homebase according to the selected applicant / employee
            $emp = !empty($data['employee_id']) ? \App\Models\Employee::find($data['employee_id']) : null;
            if ($emp) {
                $data['region'] = $emp->region ?: ($emp->homebase ?: $user->getOperationalRegion());
                $data['homebase'] = $emp->homebase ?: ($emp->region ?: ($user->homebase ?? null));
            } else {
                $data['region'] = $user->getOperationalRegion();
                $data['homebase'] = $data['homebase'] ?? $user->homebase ?? $user->employee?->homebase;
            }

            // Sync Branch data (Reffnote, Brand, City)
            if (!empty($data['branch_id'])) {
                $branch = \App\Models\Branch::find($data['branch_id']);
                if ($branch) {
                    $data['reffnote'] = $data['reffnote'] ?? $branch->reffnote;
                    $data['brand'] = $data['brand'] ?? $branch->brand;
                    $data['city'] = $data['city'] ?? $branch->city;
                }
            } elseif ($emp) {
                $branches = $emp->getManagedBranchesList();
                if (!empty($branches)) {
                    $firstBranchId = $branches[0];
                    $branch = \App\Models\Branch::find($firstBranchId);
                    if ($branch) {
                        $data['branch_id'] = $branch->id;
                        $data['reffnote'] = $data['reffnote'] ?? $branch->reffnote;
                        $data['brand'] = $data['brand'] ?? $branch->brand;
                        $data['city'] = $data['city'] ?? $branch->city;
                    }
                }
            }
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
