<?php

use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Cashier\CashierController;
use App\Http\Controllers\Customer\MenuController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Kitchen\KitchenController;
use App\Http\Controllers\PickupDisplayController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

// ── Guest flow: QR table → menu → cart → order ─────────────────────────────
Route::get('/menu', [MenuController::class, 'publicMenu'])->name('menu.public');
Route::get('/t/{qrToken}', [MenuController::class, 'reserveTable'])->name('table.reserve');
Route::get('/t/{qrToken}/menu', [MenuController::class, 'tableMenu'])->name('menu.table');

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
});
Route::get('/orders/track/{order}/{guestToken}', [OrderController::class, 'track'])
    ->name('orders.track');

// ── Staff auth ──────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// ── Cashier ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:cashier,admin'])->prefix('cashier')->group(function () {
    Route::get('/', [CashierController::class, 'index'])->name('cashier.index');
    Route::post('/orders/{order}/pay', [CashierController::class, 'pay'])->name('cashier.pay');
    Route::get('/orders/{order}/receipt', [CashierController::class, 'receipt'])->name('cashier.receipt');
    Route::post('/orders/{order}/repeat-announcement', [CashierController::class, 'repeatAnnouncement'])
        ->name('cashier.repeat');
    Route::post('/tables/{table}/release', [CashierController::class, 'releaseTable'])
        ->name('cashier.tables.release');
});

// ── Kitchen display ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:kitchen,admin'])->prefix('kitchen')->group(function () {
    Route::get('/', [KitchenController::class, 'index'])->name('kitchen.index');
    Route::post('/orders/{order}/start', [KitchenController::class, 'start'])->name('kitchen.start');
    Route::post('/orders/{order}/ready', [KitchenController::class, 'ready'])->name('kitchen.ready');
});

// ── Admin ───────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/tables', [TableController::class, 'index'])->name('admin.tables');
    Route::get('/tables/{table}/qr', [TableController::class, 'qr'])->name('admin.tables.qr');
    Route::post('/tables/{table}/rotate-token', [TableController::class, 'rotateToken'])
        ->name('admin.tables.rotate');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('admin.inventory');
    Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->name('admin.inventory.items.store');
    Route::post('/inventory/items/{item}/adjust', [InventoryController::class, 'adjustStock'])
        ->name('admin.inventory.items.adjust');
    Route::post('/inventory/items/{item}/toggle', [InventoryController::class, 'toggleItem'])
        ->name('admin.inventory.items.toggle');
    Route::post('/inventory/items/{item}/waste', [InventoryController::class, 'storeWaste'])
        ->name('admin.inventory.items.waste');
    Route::post('/products/{product}/recipe', [InventoryController::class, 'saveRecipe'])
        ->name('admin.products.recipe.save');
    Route::get('/products/{product}/recipe-versions', [InventoryController::class, 'recipeVersions'])
        ->name('admin.products.recipe-versions');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('admin.notifications.read-all');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('admin.purchase-orders');
    Route::get('/purchase-orders/new', [PurchaseOrderController::class, 'create'])->name('admin.purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('admin.purchase-orders.store');
    Route::post('/purchase-orders/{order}/submit', [PurchaseOrderController::class, 'submit'])
        ->name('admin.purchase-orders.submit');
    Route::post('/purchase-orders/{order}/receive', [PurchaseOrderController::class, 'receive'])
        ->name('admin.purchase-orders.receive');
    Route::post('/purchase-orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->name('admin.purchase-orders.cancel');
});

// ── Public pickup display (no login) ────────────────────────────────────────
Route::get('/pickup/{branch}', [PickupDisplayController::class, 'show'])->name('pickup.show');
