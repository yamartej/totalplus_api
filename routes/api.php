<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderProductController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TokenVerificationController;

use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;





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

// Ruta de verificación de correo
Route::post('/check-email', [AuthController::class, 'checkEmail']);

Route::middleware('auth:sanctum',)->group(function () {
    // Rutas protegidas aquí

    // Ruta de users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Ruta de logout
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/verify-token', [VerificationController::class, 'verifyToken']);
    Route::get('/refresh-token', [VerificationController::class, 'refreshToken']);

    // Rutas para productos
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'create']);
    Route::get('/products/{id}', [ProductController::class, 'get']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'delete']);

    // Rutas para inventario
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'create']);
    Route::get('/inventory/{id}', [InventoryController::class, 'get']);
    Route::put('/inventory/{id}', [InventoryController::class, 'update']);
    Route::delete('/inventory/{id}', [InventoryController::class, 'delete']);

    // Rutas Menu
    Route::get('/menus', [MenuController::class, 'index']);
    Route::post('/menu_items', [MenuController::class, 'getMenuByRoles']);

    // Rutas para Roles
    Route::get('/roles', [RolesController::class, 'index']);
    Route::post('/roles', [RolesController::class, 'store']);
    Route::put('/roles/{id}', [RolesController::class, 'put']);
    Route::get('/roles/{id}', [RolesController::class, 'show']);
    Route::delete('/roles/{id}', [RolesController::class, 'destroy']);

    // Rutas para Persimos de roles
    Route::get('/permissions', [RolePermissionController::class, 'index']);
    Route::post('/permissions', [RolePermissionController::class, 'update']);

    // Rutas para Roles
    Route::get('/companies', [CompanyController::class, 'index']);

    // Rutas para Agregar Categorias de los productos
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'put']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Rutas para cliente
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'delete']);

    // Agrega las demás rutas protegidas aquí
});
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return response()->json(['message' => 'Email verified successfully.']);
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return response()->json(['message' => 'Verification link sent!']);
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');


/*Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);

    if ($user->hasVerifiedEmail()) {
        return redirect(env('FRONT_URL')); // Redirige a la URL de redirección definida en .env
    }

    if ($user->markEmailAsVerified()) {
        event(new Verified($user)); // Dispara el evento Verified
    }

    return redirect(env('FRONT_URL'))->with('verified', true); // Redirige a la URL de redirección con un mensaje de correo electrónico verificado
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

// Rutas para Venta
Route::get('/sales', [SaleController::class, 'index']);
Route::post('/sales', [SaleController::class, 'store']);
Route::get('/sales/{id}', [SaleController::class, 'show']);
Route::put('/sales/{id}', [SaleController::class, 'put']);
Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

//Rutas reportes
Route::get('/reports/sales/period/{start_date}/{end_date}', [SalesReportController::class, 'salesByPeriod']);
Route::get('/reports/sales/customer/{customer_id}', [SalesReportController::class, 'salesByCustomer']);
Route::get('/reports/sales/product/{product_id}', [SalesReportController::class, 'salesByProduct']);

// Rutas para Venta
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::post('/suppliers', [SupplierController::class, 'store']);
Route::put('/suppliers/{id}', [SupplierController::class, 'put']);
Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

// Rutas para Órdenes de compra
Route::get('/purchaseorders', [PurchaseOrderController::class, 'index']);
Route::post('/purchaseorders', [PurchaseOrderController::class, 'store']);
Route::put('/purchaseorders/{id}', [PurchaseOrderController::class, 'put']);
Route::get('/purchaseorders/{id}', [PurchaseOrderController::class, 'show']);
Route::delete('/purchaseorders/{id}', [PurchaseOrderController::class, 'destroy']);

// Rutas para Agregar productos a las Órdenes de compra
Route::get('/purchaseorderproducts', [PurchaseOrderProductController::class, 'index']);
Route::post('/purchaseorderproducts', [PurchaseOrderProductController::class, 'store']);
Route::put('/purchaseorderproducts/{id}', [PurchaseOrderProductController::class, 'put']);
Route::get('/purchaseorderproducts/{id}', [PurchaseOrderProductController::class, 'show']);
Route::delete('/purchaseorderproducts/{id}', [PurchaseOrderProductController::class, 'destroy']);



// Rutas para Agregar Almacenes de los productos
Route::get('/warehouses', [WarehouseController::class, 'index']);
Route::post('/warehouses', [WarehouseController::class, 'store']);
Route::put('/warehouses/{id}', [WarehouseController::class, 'put']);
Route::get('/warehouses/{id}', [WarehouseController::class, 'show']);
Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy']);

// Rutas para Agregar Cajas registradoras de los productos
Route::get('/cashregisters', [CashRegisterController::class, 'index']);
Route::post('/cashregisters', [CashRegisterController::class, 'store']);
Route::put('/cashregisters/{id}', [CashRegisterController::class, 'put']);
Route::get('/cashregisters/{id}', [CashRegisterController::class, 'show']);
Route::delete('/cashregisters/{id}', [CashRegisterController::class, 'destroy']);
