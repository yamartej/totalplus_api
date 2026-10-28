<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Explicit, narrowly restricted QA helper.
 * Never run it against production, the accepted Phase 0-2 QA database,
 * or an arbitrary database. Does not create user accounts or roles.
 */
class Phase3QaAccess extends Command
{
    protected $signature = 'phase3:qa-access {--grant : Explicitly give existing Administrator role the Phase 3 QA permissions}';

    protected $description = 'Inspect Phase 3 access; optionally configure Administrator on isolated Phase 3 QA clones only';

    private const REQUIRED = [
        'tenant.cross_company',
        'suppliers.view',
        'suppliers.create',
        'purchaseorders.view',
        'purchaseorders.create',
        'purchaseorders.update',
        'purchaseorderproducts.view',
        'products.view',
        'warehouses.view',
        'inventory.view',
    ];

    public function handle(PermissionService $permissionService)
    {
        $database = DB::connection()->getDatabaseName();

        if (
            !app()->environment('testing') ||
            !is_string($database) ||
            !preg_match('/^totalplus_phase3_qa_[0-9]{8}_[0-9]{6}$/', $database)
        ) {
            $this->error('Refused. APP_ENV=testing and an isolated totalplus_phase3_qa_YYYYMMDD_HHMMSS database are required.');
            return 1;
        }

        $this->info('QA database confirmed: ' . $database);

        $administrator = Roles::query()
            ->where('name', 'Administrator')
            ->first();

        if (!$administrator) {
            $this->warn('No existing Administrator role. No role was created or modified.');
            return 2;
        }

        $existing = $administrator->permissions()
            ->pluck('permissions.name')
            ->all();
        $missing = array_values(array_diff(self::REQUIRED, $existing));

        $this->info('Administrator role ID: ' . $administrator->id);
        $this->info('Missing Phase 3 permissions: ' . ($missing ? implode(', ', $missing) : 'none'));

        if ($this->option('grant') && $missing) {
            DB::transaction(function () use ($administrator, $missing) {
                foreach ($missing as $name) {
                    $permission = Permission::query()->firstOrCreate(
                        ['name' => $name],
                        ['description' => 'TotalPlus Phase 3 QA operation']
                    );
                    $administrator->permissions()->syncWithoutDetaching([
                        $permission->id,
                    ]);
                }
            });
            $this->info('Granted missing permissions to the existing Administrator role on THIS QA clone only.');
        }

        foreach (User::query()->with('roles')->orderBy('id')->get() as $user) {
            $missingForUser = [];

            foreach (self::REQUIRED as $name) {
                if (
                    $name === 'tenant.cross_company'
                    && $user->company_id !== null
                ) {
                    continue;
                }
                if (!$permissionService->userHasPermission($user, $name)) {
                    $missingForUser[] = $name;
                }
            }

            $this->line(
                'User #' . $user->id .
                ' | company: ' . ($user->company_id ?? 'GLOBAL') .
                ' | roles: ' . ($user->roles->pluck('name')->implode(', ') ?: '(none)') .
                ' | missing: ' . ($missingForUser ? implode(', ', $missingForUser) : 'none')
            );
        }

        return 0;
    }
}
