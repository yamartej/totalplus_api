<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReceiptController;
use App\Http\Controllers\PurchaseOrderProductController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\PointOfSaleController;
use App\Http\Controllers\PointOfSaleStatusController;
use App\Http\Controllers\TokenVerificationController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\CreditDetailController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\CreditCustomerDetail;
use App\Http\Controllers\CreditCustomerDetailController;
use App\Http\Controllers\SaleDetailController;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Notifications\CustomVerifyEmail;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Ruta de login
Route::post('/login', [LoginController::class, 'login']);

// Ruta de login with provider
Route::post('/login-provider', [LoginController::class, 'loginWithProvider']);

// Ruta de registro de usuario
Route::post('/register', [RegisterController::class, 'register']);

// Ruta de verificaciÃ³n de correo
Route::post('/check-email', [AuthController::class, 'checkEmail']);

// Access-token renewal using a rotating opaque refresh token.
Route::post('/refresh-token', [VerificationController::class, 'refreshToken']);

Route::get('/companies', [CompanyController::class, 'index']);

Route::middleware('auth:sanctum',)->group(function () {
    // Rutas protegidas aquÃ­

    // Ruta de users

    Route::get('/users/by-role', [UserController::class, 'getUsersByRole'])->middleware('permission:users.view');
    Route::get('/users/getUsersByCompany/{id}', [UserController::class, 'getUsersByCompany'])->middleware('permission:users.view');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

    // Ruta de logout
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/verify-token', [VerificationController::class, 'verifyToken']);

    // Rutas para productos

    Route::put('/products/update-final-cost', [ProductController::class, 'updateFinalCostProduct'])->middleware('permission:products.update');
    Route::get('/products/available', [ProductController::class, 'getAvailableProducts'])->middleware('permission:products.view');
    Route::put('/products/update-batch', [ProductController::class, 'updateBatchForProducts'])->middleware('permission:products.update');
    Route::get('/products/with-batch-and-status', [ProductController::class, 'getProductsWithBatchAndStatus'])->middleware('permission:products.view');
    Route::get('/products/with-costs', [ProductController::class, 'getProductsWithCosts'])->middleware('permission:products.view');
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::post('/products', [ProductController::class, 'create'])->middleware('permission:products.create');
    Route::get('/products/{id}', [ProductController::class, 'get'])->middleware('permission:products.view');
    Route::put('/products/{id}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/products/{id}', [ProductController::class, 'delete'])->middleware('permission:products.delete');


    // Rutas para inventario
    Route::post('/inventory/transfer', [InventoryTransferController::class, 'store'])->middleware('permission:inventory.update');
    Route::get('/inventory/kardex', [InventoryMovementController::class, 'index'])->middleware('permission:inventory.view');
    Route::post('/inventory/saveInventoryProducts', [InventoryController::class, 'saveInventoryProducts'])->middleware('permission:inventory.update');
    Route::post('/inventory/removeAssignedInventory', [InventoryController::class, 'removeAssignedInventory'])->middleware('permission:inventory.update');
    Route::get('/inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view');
    Route::post('/inventory', [InventoryController::class, 'create'])->middleware('permission:inventory.create');
    Route::get('/inventory/{id}', [InventoryController::class, 'get'])->middleware('permission:inventory.view');
    Route::put('/inventory/{id}', [InventoryController::class, 'update'])->middleware('permission:inventory.update');
    Route::delete('/inventory/{id}', [InventoryController::class, 'delete'])->middleware('permission:inventory.delete');

    // Rutas Menu
    Route::get('/menus', [MenuController::class, 'index']);
    Route::post('/menu_items', [MenuController::class, 'getMenuByRoles']);

    // Rutas para Roles
    Route::get('/roles', [RolesController::class, 'index'])->middleware('permission:roles.view');
    Route::post('/roles', [RolesController::class, 'store'])->middleware('permission:roles.manage');
    Route::put('/roles/{id}', [RolesController::class, 'put'])->middleware('permission:roles.manage');
    Route::get('/roles/{id}', [RolesController::class, 'show'])->middleware('permission:roles.view');
    Route::delete('/roles/{id}', [RolesController::class, 'destroy'])->middleware('permission:roles.manage');

    // Rutas para Persimos de roles
    Route::get('/permissions', [RolePermissionController::class, 'index'])->middleware('permission:permissions.view');
    Route::post('/permissions', [RolePermissionController::class, 'update'])->middleware('permission:permissions.manage');

    // Rutas para Roles
    // Rutas para Agregar Categorias de los productos
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.manage');
    Route::put('/categories/{id}', [CategoryController::class, 'put'])->middleware('permission:categories.manage');
    Route::get('/categories/{id}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->middleware('permission:categories.manage');

    // Rutas para cliente
    Route::get('/customers/with-credits-and-payments', [CustomerController::class, 'getCustomersWithCreditsAndPayments'])->middleware('permission:customers.view');
    Route::get('/customers/get-customers-by-company/{id}', [CustomerController::class, 'getCustomersByCompany'])->middleware('permission:customers.view');
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
    Route::put('/customers/{id}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
    Route::delete('/customers/{id}', [CustomerController::class, 'delete'])->middleware('permission:customers.delete');

    // Rutas para Agregar Almacenes de los productos

    Route::get('/warehouses/get-by-company/{company_id}', [WarehouseController::class, 'getByCompany'])->middleware('permission:warehouses.view');
    Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create');
    Route::put('/warehouses/{id}', [WarehouseController::class, 'put'])->middleware('permission:warehouses.update');
    Route::get('/warehouses/{id}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.view');
    Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete');

    // Rutas para Puntos de Ventas
    Route::get('/pops/seller/{seller_id}', [PointOfSaleController::class, 'getBySellerId'])->middleware('auth:sanctum')->middleware('permission:pops.view');
    Route::get('/pops/by-company/{company_id}', [PointOfSaleController::class, 'getByCompanyId'])->middleware('auth:sanctum')->middleware('permission:pops.view');
    Route::get('/pops', [PointOfSaleController::class, 'index'])->middleware('auth:sanctum')->middleware('permission:pops.view');
    Route::post('/pops', [PointOfSaleController::class, 'store'])->middleware('auth:sanctum')->middleware('permission:pops.create');
    Route::get('/pops/{id}', [PointOfSaleController::class, 'show'])->middleware('auth:sanctum')->middleware('permission:pops.view');
    Route::put('/pops/{id}', [PointOfSaleController::class, 'update'])->middleware('auth:sanctum')->middleware('permission:pops.update');
    Route::delete('/pops/{id}', [PointOfSaleController::class, 'destroy'])->middleware('auth:sanctum')->middleware('permission:pops.delete');

    // Rutas para Ventas
    Route::get('/sales-by-company/{id}', [SaleController::class, 'getSalesByCompany'])->middleware('auth:sanctum')->middleware('permission:sales.view');
    Route::get('/sales/credit-type-by-customer-by-company/{id}', [SaleController::class, 'getCreditByCustomersByCompany'])->middleware('auth:sanctum')->middleware('permission:credits.view');
    Route::get('/sales/credit-note-list-by-company/{id}', [SaleController::class, 'getCreditNoteListByCompany'])->middleware('auth:sanctum')->middleware('permission:credits.view');
    Route::get('/sales/credit-note-list', [SaleController::class, 'getCreditNoteList'])->middleware('auth:sanctum')->middleware('permission:credits.view');
    Route::post('/sales/credit-customer-register', [SaleController::class, 'creditCustomerRegister'])->middleware('auth:sanctum')->middleware('permission:credits.create');
    Route::get('/sales/credit-type-by-customer', [SaleController::class, 'getCreditByCustomers'])->middleware('auth:sanctum')->middleware('permission:credits.view');
    Route::get('/sales/sales-by-credit-type', [SaleController::class, 'getSalesByCreditType'])->middleware('auth:sanctum')->middleware('permission:credits.view');
    Route::get('/sales', [SaleController::class, 'index'])->middleware('auth:sanctum')->middleware('permission:sales.view');
    Route::post('/sales', [SaleController::class, 'store'])->middleware('auth:sanctum')->middleware('permission:sales.create');
    Route::post('/sales/{id}', [SaleController::class, 'destroy'])->middleware('auth:sanctum')->middleware('permission:sales.delete');

    Route::put('/sales/detail/{id}', [SaleDetailController::class, 'update'])->middleware('permission:sales.update');
    Route::delete('/sales/detail/{id}', [SaleDetailController::class, 'destroy'])->middleware('permission:sales.delete');


    // Rutas para Lotes

    Route::get('/batches/get-batches-with-products', [BatchController::class, 'getBatchesWithProducts'])->middleware('permission:batches.view');
    Route::get('/batches/getBatchesReceived', [BatchController::class, 'getBatchesReceived'])->middleware('permission:batches.view');
    Route::get('/batches', [BatchController::class, 'index'])->middleware('permission:batches.view');
    Route::post('/batches', [BatchController::class, 'store'])->middleware('permission:batches.create');
    Route::put('/batches/{id}', [BatchController::class, 'update'])->middleware('permission:batches.update');
    Route::get('/batches/{id}', [BatchController::class, 'show'])->middleware('permission:batches.view');
    Route::delete('/batches/{id}', [BatchController::class, 'destroy'])->middleware('permission:batches.delete');

    // Rutas para Costos
    Route::get('/costs', [CostController::class, 'index'])->middleware('permission:costs.view');
    Route::post('/costs', [CostController::class, 'store'])->middleware('permission:costs.create');
    Route::put('/costs/{id}', [CostController::class, 'update'])->middleware('permission:costs.update');
    Route::get('/costs/{id}', [CostController::class, 'show'])->middleware('permission:costs.view');
    Route::delete('/costs/{id}', [CostController::class, 'destroy'])->middleware('permission:costs.delete');

    // Rutas para Detalle de Pagos por factura
    Route::get('/credit-details', [CreditDetailController::class, 'index'])->middleware('permission:credits.view');
    Route::post('/credit-details', [CreditDetailController::class, 'store'])->middleware('permission:credits.create');
    Route::delete('/credit-details/{id}', [CreditDetailController::class, 'destroy'])->middleware('permission:credits.delete');

    // Rutas para Detalle de Pagos por cliente
    Route::get('/credit-customer-details-by-company/{id}', [CreditCustomerDetailController::class, 'creditCustomerDetailsByCompany'])->middleware('permission:credits.view');
    Route::get('/credit-customer-details/payments-by-date', [CreditCustomerDetailController::class, 'paymentByDate'])->middleware('permission:credits.view');
    Route::get('/credit-customer-details', [CreditCustomerDetailController::class, 'index'])->middleware('permission:credits.view');
    Route::post('/credit-customer-details', [CreditCustomerDetailController::class, 'store'])->middleware('permission:credits.create');
    Route::delete('/credit-customer-details/{id}', [CreditCustomerDetailController::class, 'destroy'])->middleware('permission:credits.delete');

    Route::post('/email/verification-notification', function (Request $request) {
        $user = $request->user();

        //$user->sendEmailVerificationNotification();
        $user->notify(new CustomVerifyEmail);

        return response()->json(['message' => 'Correo de verificaciÃ³n reenviado']);
    });

    // Agrega las demÃ¡s rutas protegidas aquÃ­
});
Route::get('/email/verify/{id}/{hash}', function ($id, $hash) {
    $user = User::findOrFail($id);
    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Invalid verification link'], 403);
    }

    if ($user->hasVerifiedEmail()) {
        //return response()->json(['message' => 'Email already verified']);
        return redirect(env('FRONTEND_URL') . '/verify-success');
    }

    $user->markEmailAsVerified();

    return redirect(env('FRONTEND_URL') . '/verify-email');
})->middleware(['signed'])->name('verification.verify');

/*Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return response()->json(['message' => 'Verification link sent!']);
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');*/


/*Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);

    if ($user->hasVerifiedEmail()) {
        return redirect(env('FRONT_URL')); // Redirige a la URL de redirecciÃ³n definida en .env
    }

    if ($user->markEmailAsVerified()) {
        event(new Verified($user)); // Dispara el evento Verified
    }

    return redirect(env('FRONT_URL'))->with('verified', true); // Redirige a la URL de redirecciÃ³n con un mensaje de correo electrÃ³nico verificado
})->middleware('signed')->name('verification.verify');*/

/*Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');*/

/*Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect('/home');
})->middleware(['auth', 'signed'])->name('verification.verify');*/

/*Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('message', 'Verification link sent!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');*/

// Phase 1B: business routes that historically existed outside auth:sanctum.
// Keep their existing HTTP contracts, but require an authenticated Sanctum token.
Route::middleware('auth:sanctum')->group(function () {
    // Sale detail/update/delete routes not declared in the main protected group.
    Route::get('/sales/{id}', [SaleController::class, 'show'])->middleware('auth:sanctum')->middleware('permission:sales.view');
    Route::put('/sales/{id}', [SaleController::class, 'put'])->middleware('auth:sanctum')->middleware('permission:sales.update');
    Route::delete('/sales/{id}', [SaleController::class, 'destroy'])->middleware('auth:sanctum')->middleware('permission:sales.delete');

    // Sales reports
    Route::get('/reports/sales/period/{start_date}/{end_date}', [SalesReportController::class, 'salesByPeriod'])->middleware('auth:sanctum')->middleware('permission:reports.view');
    Route::get('/reports/sales/customer/{customer_id}', [SalesReportController::class, 'salesByCustomer'])->middleware('auth:sanctum')->middleware('permission:reports.view');
    Route::get('/reports/sales/product/{product_id}', [SalesReportController::class, 'salesByProduct'])->middleware('auth:sanctum')->middleware('permission:reports.view');

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('auth:sanctum')->middleware('permission:suppliers.view');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('auth:sanctum')->middleware('permission:suppliers.create');
    Route::put('/suppliers/{id}', [SupplierController::class, 'put'])->middleware('auth:sanctum')->middleware('permission:suppliers.update');
    Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->middleware('auth:sanctum')->middleware('permission:suppliers.view');
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->middleware('auth:sanctum')->middleware('permission:suppliers.delete');

    // Purchase orders
    Route::get('/purchaseorders', [PurchaseOrderController::class, 'index'])->middleware('auth:sanctum')->middleware('permission:purchaseorders.view');
    Route::post('/purchaseorders', [PurchaseOrderController::class, 'store'])->middleware('auth:sanctum')->middleware('permission:purchaseorders.create');
    Route::post('/purchaseorders/{id}/receive', [PurchaseReceiptController::class, 'store'])->middleware('auth:sanctum')->middleware('permission:purchaseorders.update');
    Route::put('/purchaseorders/{id}', [PurchaseOrderController::class, 'put'])->middleware('auth:sanctum')->middleware('permission:purchaseorders.update');
    Route::get('/purchaseorders/{id}', [PurchaseOrderController::class, 'show'])->middleware('auth:sanctum')->middleware('permission:purchaseorders.view');
    Route::delete('/purchaseorders/{id}', [PurchaseOrderController::class, 'destroy'])->middleware('auth:sanctum')->middleware('permission:purchaseorders.delete');

    // Purchase order products
    Route::get('/purchaseorderproducts', [PurchaseOrderProductController::class, 'index'])->middleware('auth:sanctum')->middleware('permission:products.view')->middleware('permission:purchaseorderproducts.view');
    Route::post('/purchaseorderproducts', [PurchaseOrderProductController::class, 'store'])->middleware('auth:sanctum')->middleware('permission:purchaseorderproducts.create');
    Route::put('/purchaseorderproducts/{id}', [PurchaseOrderProductController::class, 'put'])->middleware('auth:sanctum')->middleware('permission:purchaseorderproducts.update');
    Route::get('/purchaseorderproducts/{id}', [PurchaseOrderProductController::class, 'show'])->middleware('auth:sanctum')->middleware('permission:purchaseorderproducts.view');
    Route::delete('/purchaseorderproducts/{id}', [PurchaseOrderProductController::class, 'destroy'])->middleware('auth:sanctum')->middleware('permission:purchaseorderproducts.delete');

    // Cash registers
    Route::get('/cashregisters', [CashRegisterController::class, 'index'])->middleware('auth:sanctum')->middleware('permission:cashregisters.view');
    Route::post('/cashregisters', [CashRegisterController::class, 'store'])->middleware('auth:sanctum')->middleware('permission:cashregisters.create');
    Route::put('/cashregisters/{id}', [CashRegisterController::class, 'put'])->middleware('auth:sanctum')->middleware('permission:cashregisters.update');
    Route::get('/cashregisters/{id}', [CashRegisterController::class, 'show'])->middleware('auth:sanctum')->middleware('permission:cashregisters.view');
    Route::delete('/cashregisters/{id}', [CashRegisterController::class, 'destroy'])->middleware('auth:sanctum')->middleware('permission:cashregisters.delete');
});
