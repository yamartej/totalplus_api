<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class ProtectedBusinessRoutesTest extends TestCase
{
    /**
     * @dataProvider protectedGetRoutes
     */
    public function test_business_get_routes_require_authentication(string $uri): void
    {
        $this->getJson($uri)->assertStatus(401);
    }

    public function protectedGetRoutes(): array
    {
        return [
            'sales list' => ['/api/sales'],
            'sale detail' => ['/api/sales/999999'],
            'sales report period' => ['/api/reports/sales/period/2026-01-01/2026-01-31'],
            'sales report customer' => ['/api/reports/sales/customer/999999'],
            'sales report product' => ['/api/reports/sales/product/999999'],
            'suppliers' => ['/api/suppliers'],
            'purchase orders' => ['/api/purchaseorders'],
            'purchase order products' => ['/api/purchaseorderproducts'],
            'cash registers' => ['/api/cashregisters'],
        ];
    }

    public function test_sale_creation_requires_authentication(): void
    {
        $this->postJson('/api/sales', [])->assertStatus(401);
    }

    public function test_sale_update_requires_authentication(): void
    {
        $this->putJson('/api/sales/999999', [])->assertStatus(401);
    }

    public function test_sale_delete_requires_authentication(): void
    {
        $this->deleteJson('/api/sales/999999')->assertStatus(401);
    }

    public function test_supplier_creation_requires_authentication(): void
    {
        $this->postJson('/api/suppliers', [])->assertStatus(401);
    }

    public function test_purchase_order_creation_requires_authentication(): void
    {
        $this->postJson('/api/purchaseorders', [])->assertStatus(401);
    }

    public function test_cash_register_creation_requires_authentication(): void
    {
        $this->postJson('/api/cashregisters', [])->assertStatus(401);
    }
}
