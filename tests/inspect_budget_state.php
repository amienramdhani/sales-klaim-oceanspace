<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\Claim;

echo "=== POSITIONS ===\n";
foreach (Position::all() as $p) {
    echo "Pos ID: {$p->id} | Name: {$p->name} | BBM: {$p->bbm_budget} | Ent: {$p->entertain_budget} | Perdin: {$p->perdin_budget} | Srv: {$p->service_motor_budget} | Total: {$p->total_budget}\n";
}

echo "\n=== ROLES ===\n";
foreach (Role::all() as $r) {
    echo "Role ID: {$r->id} | Name: {$r->name} | Code: {$r->code} | Ent: {$r->entertain_budget} | Total: {$r->total_budget}\n";
}

echo "\n=== EMPLOYEES & BUDGETS & USAGE ===\n";
foreach (Employee::with(['positionModel', 'role', 'claims'])->get() as $e) {
    echo "ID: {$e->id} | Name: {$e->name} | PosName: {$e->position_name} | PosID: {$e->position_id} | RoleID: {$e->role_id}\n";
    echo "  Budgets: BBM={$e->bbm_budget}, Ent={$e->entertain_budget}, Perdin={$e->perdin_budget}, Srv={$e->service_motor_budget}, Total={$e->total_budget}\n";
    echo "  Used: BBM={$e->used_bbm}, Ent={$e->used_entertain_makan}, Perdin={$e->used_perdin}, TotalUsed={$e->total_expense_used}\n";
    echo "  Sisa: BBM=" . ($e->bbm_budget - $e->used_bbm) . ", Ent=" . ($e->entertain_budget - $e->used_entertain_makan) . ", Total=" . ($e->total_budget - $e->total_expense_used) . "\n";
    echo "---------------------------------------------------\n";
}
