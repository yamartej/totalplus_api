<?php

namespace App\Http\Controllers;

use App\Models\Batch;
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
            'batch_id' => 'nullable|integer',
            'final_cost' => 'nullable|numeric|min:0',
            'wholesale_final_cost' => 'nullable|numeric|min:0',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input('company_id'),
            true
        );

        $batchId = $this->resolveWritableBatchId(
            $request->input('batch_id'),
            $companyId
        );

        if (
            $request->input('batch_id') !== null
            && $batchId === null
        ) {
            return response()->json([
                'message' => 'Lote no encontrado',
            ], 404);
        }

        $product = Product::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'image' => $request->input('image'),
            'category_id' => $request->input('category_id'),
            'quantity' => $request->input('quantity'),
            'batch_id' => $batchId,

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
            ),
            true
        );

        $query = Product::query();

        /*
         * Product mutation requires exact ownership. Legacy NULL-company
         * products remain readable for compatibility, but a company tenant
         * cannot claim or mutate them through a normal update.
         */
        $this->scopeOwnedProductsForCompany(
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
            'batch_id' => 'nullable|integer',
        ]);

        $batchId = $this->resolveWritableBatchId(
            $request->input('batch_id'),
            $companyId
        );

        if (
            $request->input('batch_id') !== null
            && $batchId === null
        ) {
            return response()->json([
                'message' => 'Lote no encontrado',
            ], 404);
        }

        $product->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'image' => $request->input('image'),
            'category_id' => $request->input('category_id'),
            'quantity' => $request->input('quantity'),
            'batch_id' => $batchId,
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
            $request->query('company_id'),
            true
        );

        $query = Product::query();

        /*
         * Deletion is a write operation, so compatibility NULL ownership is
         * not accepted for a company-bound tenant.
         */
        $this->scopeOwnedProductsForCompany(
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

    public function updateBatchForProducts(
        Request $request,
        TenantContext $tenantContext
    ) {
        $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|integer|distinct',
            'batch_id' => 'required|integer',
        ]);

        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->input(
                'company_id',
                $request->query('company_id')
            ),
            true
        );

        /*
         * Assignment is a write operation, so compatibility NULL ownership
         * is intentionally not accepted. The destination batch must belong
         * exactly to the resolved tenant.
         */
        $batchQuery = Batch::query();

        if ($companyId !== null) {
            $batchQuery->where(
                'company_id',
                $companyId
            );
        }

        $batch = $batchQuery->find(
            $request->input('batch_id')
        );

        if (!$batch) {
            return response()->json([
                'message' => 'Lote no encontrado',
            ], 404);
        }

        $productIds = collect(
            $request->input('product_ids')
        )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $productQuery = Product::query()
            ->whereIn('id', $productIds->all());

        $this->scopeOwnedProductsForCompany(
            $productQuery,
            $companyId
        );

        /*
         * Validate the complete set before performing any update. This keeps
         * mixed-tenant requests atomic: either every product belongs to the
         * tenant or nothing changes.
         */
        $ownedProductIds = $productQuery
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($ownedProductIds->count() !== $productIds->count()) {
            return response()->json([
                'message' => 'Producto no encontrado',
            ], 404);
        }

        Product::query()
            ->whereIn('id', $productIds->all())
            ->update([
                'batch_id' => $batch->id,
            ]);

        $updatedQuery = Product::query()
            ->whereIn('id', $productIds->all());

        $this->scopeOwnedProductsForCompany(
            $updatedQuery,
            $companyId
        );

        $updatedProducts = $updatedQuery->get();

        return response()->json([
            'message' =>
                'Batch actualizado para los productos seleccionados.',
            'products' => $updatedProducts,
        ], 200);
    }

    public function getProductsWithBatchAndStatus(
        Request $request,
        TenantContext $tenantContext
    ) {
        $companyId = $tenantContext->resolveCompanyId(
            $request->user(),
            $request->query('company_id')
        );

        $query = Product::query();

        if ($companyId === null) {
            $query->whereHas(
                'batches',
                function ($batchQuery) {
                    $batchQuery->where(
                        'status',
                        'created'
                    );
                }
            );
        } else {
            /*
             * A visible association must have compatible ownership on both
             * sides. Tenant-owned products pair only with tenant-owned
             * batches. Legacy products pair only with legacy NULL batches.
             * This also hides malformed historical cross-company links.
             */
            $query->where(
                function (Builder $associationQuery) use ($companyId) {
                    $associationQuery
                        ->where(
                            function (Builder $ownedQuery) use ($companyId) {
                                $ownedQuery
                                    ->where(
                                        'company_id',
                                        $companyId
                                    )
                                    ->whereHas(
                                        'batches',
                                        function ($batchQuery) use ($companyId) {
                                            $batchQuery
                                                ->where(
                                                    'status',
                                                    'created'
                                                )
                                                ->where(
                                                    'company_id',
                                                    $companyId
                                                );
                                        }
                                    );
                            }
                        )
                        ->orWhere(
                            function (Builder $legacyQuery) {
                                $legacyQuery
                                    ->whereNull('company_id')
                                    ->whereHas(
                                        'batches',
                                        function ($batchQuery) {
                                            $batchQuery
                                                ->where(
                                                    'status',
                                                    'created'
                                                )
                                                ->whereNull(
                                                    'company_id'
                                                );
                                        }
                                    );
                            }
                        );
                }
            );
        }

        $inventoryScope = $this->inventoryCompanyScope(
            $companyId
        );

        $products = $query
            ->with([
                'batches',
                'inventory' => $inventoryScope,
                'inventory.warehouse',
            ])
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

        /*
         * Cost rows must represent a compatible product/batch ownership pair.
         * Tenant-owned products may use only tenant-owned batches, while
         * legacy NULL-company products remain readable only with legacy
         * NULL-company batches during the compatibility window.
         */
        $this->scopeProductsWithCompatibleBatchForCompany(
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

                    /*
                     * The denominator follows the same ownership side as the
                     * visible product/batch association. This prevents legacy
                     * NULL products from diluting an owned batch, and prevents
                     * tenant-owned products from diluting a legacy batch.
                     */
                    if ($companyId !== null) {
                        if ($product->company_id === null) {
                            $totalQuantityQuery->whereNull(
                                'company_id'
                            );
                        } else {
                            $totalQuantityQuery->where(
                                'company_id',
                                $companyId
                            );
                        }
                    }

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
            ),
            true
        );

        $query = Product::query();

        /*
         * Final prices are a write operation. Legacy NULL-company products
         * remain readable for compatibility but are not writable by a
         * company-bound tenant until ownership is explicit.
         */
        $this->scopeOwnedProductsForCompany(
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
     * Resolve a writable batch using exact tenant ownership.
     *
     * A NULL batch_id means "no batch" and is valid. Legacy NULL-company
     * batches are readable for compatibility but cannot receive new writes
     * from a company-bound tenant.
     */
    private function resolveWritableBatchId(
        $batchId,
        ?int $companyId
    ): ?int {
        if ($batchId === null) {
            return null;
        }

        $query = Batch::query();

        if ($companyId !== null) {
            $query->where(
                'company_id',
                $companyId
            );
        }

        $batch = $query->find($batchId);

        return $batch
            ? (int) $batch->id
            : null;
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
     * Cost/read associations must preserve ownership compatibility between
     * the product row and its batch. Explicit tenant rows pair with the same
     * tenant; legacy NULL rows pair only with legacy NULL batches.
     */
    private function scopeProductsWithCompatibleBatchForCompany(
        Builder $query,
        ?int $companyId
    ): Builder {
        if ($companyId === null) {
            return $query;
        }

        return $query->where(
            function (Builder $associationQuery) use ($companyId) {
                $associationQuery
                    ->where(
                        function (Builder $ownedQuery) use ($companyId) {
                            $ownedQuery
                                ->where(
                                    'company_id',
                                    $companyId
                                )
                                ->whereHas(
                                    'batches',
                                    function ($batchQuery) use ($companyId) {
                                        $batchQuery->where(
                                            'company_id',
                                            $companyId
                                        );
                                    }
                                );
                        }
                    )
                    ->orWhere(
                        function (Builder $legacyQuery) {
                            $legacyQuery
                                ->whereNull('company_id')
                                ->whereHas(
                                    'batches',
                                    function ($batchQuery) {
                                        $batchQuery->whereNull(
                                            'company_id'
                                        );
                                    }
                                );
                        }
                    );
            }
        );
    }

    /**
     * Writes require exact tenant ownership. Legacy NULL-company products are
     * readable during the compatibility window but are not writable through
     * tenant-specific assignment operations.
     */
    private function scopeOwnedProductsForCompany(
        Builder $query,
        ?int $companyId
    ): Builder {
        if ($companyId === null) {
            return $query;
        }

        return $query->where(
            'company_id',
            $companyId
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
