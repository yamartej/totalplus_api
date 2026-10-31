<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Regression for products through authenticated company-scoped API endpoints. */
class ProductTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/products';
    protected string $modelClass = Product::class;

    protected function fixture(): Model
    {
        return Product::factory()->create(['company_id' => $this->qaCompany->id]);
    }

    protected function createPayload(): array
    {
        $category = Category::factory()->create();
        return [
            'name' => 'Created product', 'description' => 'QA product',
            'category_id' => $category->id, 'price' => 30,
            'final_cost' => 30, 'wholesale_final_cost' => 25,
        ];
    }

    protected function updatePayload(): array
    {
        $category = Category::factory()->create();
        return [
            'name' => 'Updated product', 'description' => 'QA product update',
            'category_id' => $category->id, 'price' => 40,
        ];
    }

    public function test_creation_without_quantity_initializes_zero_stock(): void
    {
        $data = $this->createPayload();
        $this->assertArrayNotHasKey('quantity', $data);

        $response = $this->postJson($this->endpoint, $data)->assertOk();
        $product = Product::findOrFail($response->json('product.id'));
        $this->assertSame(0, (int) $product->quantity);
    }

    public function test_edit_without_quantity_preserves_current_stock(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->qaCompany->id,
            'quantity' => 7,
        ]);

        $this->putJson(
            $this->endpoint . '/' . $product->id,
            $this->updatePayload()
        )->assertOk();

        $this->assertSame(7, (int) $product->fresh()->quantity);
    }

    protected function expectedCreateStatus(): int
    {
        return 200; // Existing products API wraps the product and returns HTTP 200.
    }

    protected function idFromCreationResponse(array $json): int
    {
        return (int) $json['product']['id'];
    }
}
