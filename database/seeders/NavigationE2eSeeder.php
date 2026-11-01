<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Product;
use App\Models\RolePermission;
use App\Models\Roles;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * CI-only synthetic user profiles for end-to-end testing.
 * This seeder must NEVER run against persistent QA, staging or production.
 */
class NavigationE2eSeeder extends Seeder
{
    public function run(): void
    {
        $database = DB::connection()->getDatabaseName();

        if (
            getenv('GITHUB_ACTIONS') !== 'true'
            || !app()->environment('testing')
            || getenv('DB_DATABASE') !== 'totalplus_ci_navigation_e2e'
            || $database !== 'totalplus_ci_navigation_e2e'
            || DB::table('users')->exists()
            || !getenv('NAV_QA_PASSWORD')
        ) {
            throw new RuntimeException('Navigation E2E seeding is permitted only in the empty ephemeral GitHub Actions database.');
        }

        DB::transaction(function (): void {
            $companyA = Company::create(['name' => 'QA Navigation Company Alpha']);
            $companyB = Company::create(['name' => 'QA Navigation Company Beta']);

            Warehouse::create([
                'name' => 'QA Alpha Principal',
                'address' => 'Synthetic QA address',
                'company_id' => $companyA->id,
            ]);
            Warehouse::create([
                'name' => 'QA Alpha Secundario',
                'address' => 'Synthetic QA address',
                'company_id' => $companyA->id,
            ]);
            Warehouse::create([
                'name' => 'QA Beta Privado',
                'address' => 'Synthetic QA address',
                'company_id' => $companyB->id,
            ]);

            $category = Category::create(['name' => 'QA Navigation Category']);
            $product = Product::create([
                'name' => 'QA Synthetic Product',
                'description' => 'Only for the ephemeral navigation suite',
                'price' => 10,
                'category_id' => $category->id,
                'quantity' => 5,
                'final_cost' => 5,
                'wholesale_final_cost' => 5,
            ]);
            $product->company_id = $companyA->id;
            $product->save();

            $profiles = [
                ['seller', 'QA Vendedor', $companyA->id,
                    ['/pages/sales', '/pages/customers'],
                    ['sales.view', 'customers.view']],
                ['warehouse', 'QA Almacén', $companyA->id,
                    ['/pages/kardex', '/pages/transfers', '/pages/stocks'],
                    ['inventory.view', 'inventory.update', 'products.view', 'warehouses.view']],
                ['buyer', 'QA Compras', $companyA->id,
                    ['/pages/purchase-orders', '/pages/purchase-receipts'],
                    ['purchaseorders.view', 'purchaseorders.create', 'purchaseorders.update',
                     'purchaseorderproducts.view', 'products.view',
                     'warehouses.view', 'suppliers.view']],
                ['finance', 'QA Cobranza', $companyA->id,
                    ['/pages/payment-details', '/pages/sales-report'],
                    ['credits.view', 'reports.view']],
                ['admin', 'QA Administrador Global', null,
                    ['/pages/users', '/pages/permissions', '/pages/transfers', '/pages/products'],
                    ['users.view', 'permissions.view', 'roles.view',
                     'inventory.view', 'inventory.update', 'products.view', 'warehouses.view',
                     'tenant.cross_company']],
            ];

            foreach ($profiles as [$key, $name, $companyId, $menuUrls, $permissionNames]) {
                $role = Roles::create(['name' => 'Navigation QA ' . $key]);
                $user = User::create([
                    'name' => $name,
                    'email' => 'qa-' . $key . '@totalplus.invalid',
                    'company_id' => $companyId,
                    'email_verified_at' => now(),
                    'password' => Hash::make(getenv('NAV_QA_PASSWORD')),
                ]);
                $user->roles()->attach($role->id);

                foreach ($menuUrls as $url) {
                    $menu = Menu::where('url', $url)->firstOrFail();
                    RolePermission::create([
                        'role_id' => $role->id,
                        'menu_id' => $menu->id,
                        'can_access' => true,
                    ]);
                }

                foreach ($permissionNames as $name) {
                    $permission = Permission::firstOrCreate(
                        ['name' => $name],
                        ['description' => 'Ephemeral navigation E2E permission']
                    );
                    $role->permissions()->attach($permission->id);
                }
            }
        });
    }
}
