<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Roles;
use Illuminate\Database\Seeder;

class TenantIsolationSeeder extends Seeder
{
    public function run()
    {
        $permission = Permission::updateOrCreate(
            ['name' => 'tenant.cross_company'],
            [
                'description' =>
                    'Access data belonging to multiple companies',
            ]
        );

        $administrator = Roles::where(
            'name',
            'Administrator'
        )->first();

        if ($administrator) {
            $administrator->permissions()
                ->syncWithoutDetaching([$permission->id]);
        }
    }
}
