<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\RolePermission;
use App\Models\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Development-only navigation setup for the isolated Phase 0-2 QA database.
 *
 * Does not grant API permissions or change any existing users.
 * Never run against production or a database containing real data.
 */
class QaNavigationSeeder extends Seeder
{
    public function run(): void
    {
        if (
            !app()->environment('testing')
            || DB::connection()->getDatabaseName() !== 'totalplus_phase02_testing'
        ) {
            throw new RuntimeException(
                'QaNavigationSeeder requires the isolated totalplus_phase02_testing database in testing mode.'
            );
        }

        DB::transaction(function (): void {
            $administrator = Roles::where('name', 'Administrator')->firstOrFail();

            $groups = [
                [
                    'name' => 'Administración',
                    'url' => '/menu/administration',
                    'icon' => 'SettingsIcon',
                    'children' => [
                        ['Usuarios', '/pages/users'],
                        ['Permisos', '/pages/permissions'],
                    ],
                ],
                [
                    'name' => 'Inventario',
                    'url' => '/menu/inventory',
                    'icon' => 'StockIcon',
                    'children' => [
                        ['Categorías', '/pages/categories'],
                        ['Almacenes', '/pages/warehouses'],
                        ['Existencias', '/pages/stocks'],
                        ['Precios', '/pages/prices'],
                    ],
                ],
                [
                    'name' => 'Compras',
                    'url' => '/menu/purchasing',
                    'icon' => 'ShoppingCartIcon',
                    'children' => [
                        ['Lotes', '/pages/batchs'],
                        ['Productos', '/pages/products'],
                        ['Costos', '/pages/costs'],
                        ['Pagos', '/pages/payments'],
                    ],
                ],
                [
                    'name' => 'Ventas',
                    'url' => '/menu/sales',
                    'icon' => 'SalesIcon',
                    'children' => [
                        ['Clientes', '/pages/customers'],
                        ['Puntos de venta', '/pages/pops'],
                        ['Ventas', '/pages/sales'],
                        ['Notas de crédito', '/pages/credit-note'],
                    ],
                ],
                [
                    'name' => 'Créditos',
                    'url' => '/menu/credits',
                    'icon' => 'CurrencyDollarIcon',
                    'children' => [
                        ['Pagos de créditos', '/pages/payment-details'],
                        ['Abonos de clientes', '/pages/payment-customer-details'],
                    ],
                ],
                [
                    'name' => 'Reportes',
                    'url' => '/menu/reports',
                    'icon' => 'ReportsIcon',
                    'children' => [
                        ['Reporte de ventas', '/pages/sales-report'],
                        ['Reporte de pagos', '/pages/payment-report'],
                    ],
                ],
            ];

            foreach ($groups as $groupIndex => $definition) {
                $parent = Menu::firstOrCreate(
                    ['url' => $definition['url']],
                    [
                        'name' => $definition['name'],
                        'parent_id' => null,
                        'order' => $groupIndex + 1,
                        'icon' => $definition['icon'],
                    ]
                );

                $this->grantMenuToAdministrator($administrator, $parent);

                foreach ($definition['children'] as $childIndex => $child) {
                    $menu = Menu::firstOrCreate(
                        ['url' => $child[1]],
                        [
                            'name' => $child[0],
                            'parent_id' => $parent->id,
                            'order' => $childIndex + 1,
                            'icon' => 'DefaultIcon',
                        ]
                    );

                    $this->grantMenuToAdministrator($administrator, $menu);
                }
            }
        });
    }

    private function grantMenuToAdministrator(Roles $administrator, Menu $menu): void
    {
        RolePermission::firstOrCreate(
            [
                'role_id' => $administrator->id,
                'menu_id' => $menu->id,
            ],
            ['can_access' => true]
        );
    }
}
