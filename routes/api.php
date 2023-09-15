<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\RolesController;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;



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

// Ruta de logout
Route::post('/logout', [LoginController::class, 'logout']);

// Ruta de registro de usuario
Route::post('/register', [RegisterController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    // Rutas protegidas aquí

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

    // Agrega las demás rutas protegidas aquí
});


// Rutas para cliente
Route::get('/customers', [CustomerController::class, 'index']);
Route::post('/customers', [CustomerController::class, 'store']);
Route::get('/customers/{id}', [CustomerController::class, 'show']);
Route::put('/customers/{id}', [CustomerController::class, 'update']);
Route::delete('/customers/{id}', [CustomerController::class, 'delete']);

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

// Rutas para Agregar Categorias de los productos
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::put('/categories/{id}', [CategoryController::class, 'put']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

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

// Rutas para Roles
Route::get('/roles', [RolesController::class, 'index']);
Route::post('/roles', [RolesController::class, 'store']);
Route::put('/roles/{id}', [RolesController::class, 'put']);
Route::get('/roles/{id}', [RolesController::class, 'show']);
Route::delete('/roles/{id}', [RolesController::class, 'destroy']);





