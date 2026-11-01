<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aditiva e idempotente: reutiliza los IDs por URL para preservar
     * role_permissions. Nunca crea ni modifica autorizaciones.
     */
    public function up(): void
    {
        $groups = [
            ['/menu/sales', 'Ventas', 'SalesIcon', [
                ['Ventas', '/pages/sales'],
                ['Puntos de venta', '/pages/pops'],
                ['Clientes', '/pages/customers'],
            ]],
            ['/menu/purchasing', 'Compras', 'ShoppingCartIcon', [
                ['Órdenes de compra', '/pages/purchase-orders'],
                ['Recepción de mercancía', '/pages/purchase-receipts'],
                ['Pagos de compras (legado)', '/pages/payments'],
            ]],
            ['/menu/inventory', 'Inventario', 'StockIcon', [
                ['Existencias', '/pages/stocks'],
                ['Kardex de movimientos', '/pages/kardex'],
                ['Transferencias entre almacenes', '/pages/transfers'],
                ['Almacenes', '/pages/warehouses'],
                ['Lotes', '/pages/batchs'],
            ]],
            ['/menu/catalog', 'Catálogo y precios', 'TagsIcon', [
                ['Productos', '/pages/products'],
                ['Categorías', '/pages/categories'],
                ['Costos', '/pages/costs'],
                ['Precios', '/pages/prices'],
            ]],
            ['/menu/credits', 'Créditos y cobranzas', 'CurrencyDollarIcon', [
                ['Pagos de facturas', '/pages/payment-details'],
                ['Abonos de clientes', '/pages/payment-customer-details'],
                ['Notas de crédito', '/pages/credit-note'],
            ]],
            // No se publica Caja aún: /cashregisters no filtra por empresa.
            // Se incorporará al catálogo cuando el BACKEND quede aislado.
            ['/menu/reports', 'Reportes', 'ReportsIcon', [
                ['Reporte de ventas', '/pages/sales-report'],
                ['Reporte de pagos', '/pages/payment-report'],
            ]],
            ['/menu/administration', 'Administración', 'SettingsIcon', [
                ['Usuarios', '/pages/users'],
                ['Permisos y accesos', '/pages/permissions'],
            ]],
        ];

        DB::transaction(function () use ($groups): void {
            foreach ($groups as $position => [$url, $name, $icon, $children]) {
                $parentId = $this->upsertMenu(
                    $url, $name, null, $position + 1, $icon
                );

                foreach ($children as $childPosition => [$childName, $childUrl]) {
                    $this->upsertMenu(
                        $childUrl, $childName, $parentId,
                        $childPosition + 1, 'DefaultIcon'
                    );
                }
            }
        });
    }

    private function upsertMenu(
        string $url,
        string $name,
        ?int $parentId,
        int $order,
        string $icon
    ): int {
        $query = DB::table('menus')->where('url', $url);
        if ((clone $query)->count() > 1) {
            throw new RuntimeException('Duplicate navigation URL: ' . $url);
        }

        $existing = $query->first();
        $fields = [
            'name' => $name,
            'parent_id' => $parentId,
            'order' => $order,
            'icon' => $icon,
            'updated_at' => now(),
        ];

        if ($existing) {
            $query->update($fields);
            return (int) $existing->id;
        }

        return (int) DB::table('menus')->insertGetId([
            'url' => $url,
            ...$fields,
            'created_at' => now(),
        ]);
    }

    /**
     * Revertir esta reorganización necesita la copia anterior de la tabla:
     * borrar menús o reconstruir padres aquí destruiría permisos existentes.
     */
    public function down(): void
    {
        // Non-destructive by design. The application remains recoverable from
        // the pre-migration database backup under an approved rollback plan.
    }
};
