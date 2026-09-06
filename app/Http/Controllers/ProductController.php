<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Inventory\InventoryCompatibilityService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Product::query();

        $this->scopeProductsForCompany(
            $query,
            $companyId
        );

        $products = $query
            ->with(['category', 'batches'])
            ->get();

        return response()->json($products);
    }

    public function create(
        Request $request,
        TenantContext $tenantContext
    ) {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'batch_id' => 'nullable|exists:batches,id',
            'final_cost' => 'nullable|numeric|min:0',
            'wholesale_final_cost' => 'nullable|numeric|min:0',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $product = Product::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'image' => $request->input('image'),
            'category_id' => $request->input('category_id'),
            'quantity' => $request->input('quantity'),
            'batch_id' => $request->input('batch_id'),

            // These columns are NOT NULL in the legacy schema and the
            // current frontend creates products before retail/wholesale
            // prices are configured on PricePage.
            'final_cost' => $request->input(
                'final_cost',
                0
            ),
            'wholesale_final_cost' => $request->input(
                'wholesale_final_cost',
                0
            ),
        ]);

        // Do not accept ownership from mass assignment.
        $product->company_id = $companyId;
        $product->save();

        return response()->json([
            'product' => $product,
        ]);
    }

    public function get(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Product::query();

        $this->scopeProductsForCompany(
            $query,
            $companyId
        );

        $product = $query->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado',
            ], 404);
        }

        return response()->json($product, 200);
    }

    public function update(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            )
        );

        $query = Product::query();

        $this->scopeProductsForCompany(
            $query,
            $companyId
        );

        $product = $query->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'batch_id' => 'nullable|exists:batches,id',
        ]);

        $product->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'image' => $request->input('image'),
            'category_id' => $request->input('category_id'),
            'quantity' => $request->input('quantity'),
            'batch_id' => $request->input('batch_id'),
        ]);

        return response()->json($product, 200);
    }

    public function delete(
        Request $request,
        $id,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Product::query();

        $this->scopeProductsForCompany(
            $query,
            $companyId
        );

        $product = $query->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $product->delete();

        return response()->json(null, 204);
    }

    /**
     * Phase 2E inventory-availability contract.
     *
     * products.quantity remains the legacy received/purchase quantity.
     * Current stock is derived only from inventories.quantity.
     *
     * Product rows explicitly owned by another company are excluded.
     * company_id = null remains readable during the compatibility window
     * because legacy product creation did not always persist ownership.
     *
     * Inventory balances are always restricted to the resolved company's
     * warehouses, including for those legacy null-company products.
     */
    public function getAvailableProducts(
        Request $request,
        InventoryCompatibilityService $compatibility,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Product::query()
            ->where('quantity', '>', 0);

        $this->scopeProductsForCompany(
            $query,
            $companyId
        );

        $inventoryScope = $this->inventoryCompanyScope(
            $companyId
        );

        $products = $query
            ->with([
                'category',
                'batches',
                'inventories' => $inventoryScope,
                'inventories.warehouse',
            ])
            ->get()
            ->map(
                function (Product $product) use (
                    $compatibility,
                    $companyId
                ) {
                    $compatibility->appendCompatibilityAttributes(
                        $product,
                        $companyId
                    );

                    $unallocated = (int)
                        $product->unallocated_quantity;

                    if ($unallocated <= 0) {
                        return null;
                    }

                    // Response-only legacy compatibility. This does not
                    // modify products.quantity in the database.
                    $product->setAttribute(
                        'quantity',
                        $unallocated
                    );

                    return $product;
                }
            )
            ->filter()
            ->values();

        return response()->json($products, 200);
    }

    public function updateBatchForProducts(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        Product::whereIn('id', $request->input('product_ids'))
            ->update([
                'batch_id' => $request->input('batch_id'),
            ]);

        $updatedProducts = Product::whereIn(
            'id',
            $request->input('product_ids')
        )->get();

        return response()->json([
            'message' =>
                'Batch actualizado para los productos seleccionados.',
            'products' => $updatedProducts,
        ], 200);
    }

    public function getProductsWithBatchAndStatus()
    {
        $products = Product::whereHas(
            'batches',
            function ($query) {
                $query->where('status', 'created');
            }
        )
            ->with('batches', 'inventory')
            ->get();

        return response()->json($products, 200);
    }

    /**
     * Cost-management contract.
     *
     * Keep quantity with its legacy purchase/batch meaning so PricePage and
     * the existing costing workflow do not change in Phase 2E. Canonical
     * inventory metadata is exposed alongside it for new consumers.
     *
     * The product list, inventory relations and batch quantity denominator
     * are tenant-scoped. Legacy products with company_id = null are kept
     * temporarily for backwards compatibility.
     */
    public function getProductsWithCosts(
        Request $request,
        InventoryCompatibilityService $compatibility,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Product::query();

        $this->scopeProductsForCompany(
            $query,
            $companyId
        );

        $inventoryScope = $this->inventoryCompanyScope(
            $companyId
        );

        $products = $query
            ->with([
                'batches.costs',
                'inventory' => $inventoryScope,
                'inventory.warehouse',
                'inventories' => $inventoryScope,
                'inventories.warehouse',
            ])
            ->whereHas(
                'batches.costs',
                function ($costQuery) {
                    $costQuery->where('amount', '>', 0);
                }
            )
            ->get()
            ->map(
                function (Product $product) use (
                    $compatibility,
                    $companyId
                ) {
                    $compatibility->appendCompatibilityAttributes(
                        $product,
                        $companyId
                    );

                    $totalCosts =
                        $product->batches->costs->sum('amount');

                    $totalQuantityQuery = Product::query()
                        ->where(
                            'batch_id',
                            $product->batch_id
                        );

                    $this->scopeProductsForCompany(
                        $totalQuantityQuery,
                        $companyId
                    );

                    $totalQuantity = $totalQuantityQuery
                        ->sum('quantity');

                    $unitCost = $totalQuantity > 0
                        ? round(
                            $totalCosts / $totalQuantity,
                            2
                        )
                        : 0;

                    $unitCostProduct = round(
                        $unitCost + $product->price,
                        2
                    );

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,

                        // Preserve existing cost/purchase semantics.
                        'quantity' => $product->quantity,

                        // Explicit Phase 2E compatibility fields.
                        'legacy_quantity' =>
                            (int) $product->legacy_quantity,
                        'inventory_total_quantity' =>
                            (int)
                            $product->inventory_total_quantity,
                        'unallocated_quantity' =>
                            (int)
                            $product->unallocated_quantity,

                        'unit_cost' => $unitCost,
                        'price_shipping' => $unitCostProduct,
                        'final_cost' => $product->final_cost,
                        'wholesale_final_cost' =>
                            $product->wholesale_final_cost,
                        'batches' => $product->batches,
                        'costs' =>
                            $product->batches?->costs,

                        // Legacy single-inventory compatibility.
                        'warehouse' =>
                            $product->inventory?->warehouse,
                        'inventory' => $product->inventory,

                        // Canonical multi-warehouse representation.
                        'inventories' => $product->inventories,
                    ];
                }
            );

        return response()->json($products, 200);
    }

    public function updateFinalCostProduct(
        Request $request,
        TenantContext $tenantContext
    ) {
        $request->validate([
            'id' => 'required|integer',
            'final_cost' => 'required|numeric|min:0',
            'wholesale_final_cost' =>
                'required|numeric|min:0',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            )
        );

        $query = Product::query();

        $this->scopeProductsForCompany(
            $query,
            $companyId
        );

        $product = $query->find(
            $request->input('id')
        );

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $product->update([
            'final_cost' => $request->input('final_cost'),
            'wholesale_final_cost' =>
                $request->input('wholesale_final_cost'),
        ]);

        return response()->json($product, 200);
    }

    /**
     * During the compatibility window, null-company products remain readable
     * alongside tenant-owned products. Explicitly-owned products from another
     * company are always excluded.
     */
    private function scopeProductsForCompany(
        Builder $query,
        ?int $companyId
    ): Builder {
        if ($companyId === null) {
            return $query;
        }

        return $query->where(
            function (Builder $tenantQuery) use ($companyId) {
                $tenantQuery
                    ->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            }
        );
    }

    /**
     * Scope inventory relations by their warehouse company.
     */
    private function inventoryCompanyScope(
        ?int $companyId
    ): callable {
        return function ($inventoryQuery) use ($companyId) {
            if ($companyId === null) {
                return;
            }

            $inventoryQuery->whereHas(
                'warehouse',
                function ($warehouseQuery) use ($companyId) {
                    $warehouseQuery->where(
                        'company_id',
                        $companyId
                    );
                }
            );
        };
    }
}
