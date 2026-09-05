<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleTransactionContractPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function createSale(): Sale
    {
        return Sale::create([
            'total_amount' => 0,
            'type_of_sale' => 'normal',
        ]);
    }

    public function test_sales_details_support_phase3_transaction_snapshot_fields(): void
    {
        $this->assertTrue(
            Schema::hasColumns('sales_details', [
                'warehouse_id',
                'unit_price',
            ])
        );

        $company = Company::create([
            'name' => 'Phase 3A Company',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Phase 3A Warehouse',
            'description' => 'Transactional sales contract test',
            'address' => 'Testing',
            'company_id' => $company->id,
        ]);

        $sale = $this->createSale();
        $product = Product::factory()->create();

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 2,
            'unit_price' => 123.45,
        ]);

        $fresh = $detail->fresh();

        $this->assertSame(
            $warehouse->id,
            (int) $fresh->warehouse_id
        );

        $this->assertSame(
            '123.45',
            $fresh->unit_price
        );

        $this->assertSame(
            $warehouse->id,
            $fresh->warehouse->id
        );
    }

    public function test_legacy_sale_detail_can_remain_without_phase3_snapshot(): void
    {
        $sale = $this->createSale();
        $product = Product::factory()->create();

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $fresh = $detail->fresh();

        $this->assertNull($fresh->warehouse_id);
        $this->assertNull($fresh->unit_price);
        $this->assertNull($fresh->warehouse);
    }
}
