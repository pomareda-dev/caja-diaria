<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\RecurringTransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('movimientos', [MovementController::class, 'index'])->name('movimientos.index');
    Route::post('movimientos', [MovementController::class, 'store'])->name('movimientos.store');
    Route::patch('movimientos/reorder', [MovementController::class, 'reorder'])->name('movimientos.reorder');
    Route::put('movimientos/{movement}', [MovementController::class, 'update'])->name('movimientos.update');
    Route::patch('movimientos/{movement}', [MovementController::class, 'update'])->name('movimientos.patch');
    Route::delete('movimientos/{movement}', [MovementController::class, 'destroy'])->name('movimientos.destroy');

    Route::get('categorias', [CategoryController::class, 'index'])->name('categorias.index');
    Route::post('categorias', [CategoryController::class, 'store'])->name('categorias.store');
    Route::patch('categorias/reorder', [CategoryController::class, 'reorder'])->name('categorias.reorder');
    Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categorias.update');
    Route::patch('categorias/{category}', [CategoryController::class, 'update'])->name('categorias.patch');
    Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categorias.destroy');
    Route::get('cuentas', [AccountController::class, 'index'])->name('cuentas.index');
    Route::post('cuentas', [AccountController::class, 'store'])->name('cuentas.store');
    Route::patch('cuentas/reorder', [AccountController::class, 'reorder'])->name('cuentas.reorder');
    Route::put('cuentas/{account}', [AccountController::class, 'update'])->name('cuentas.update');
    Route::patch('cuentas/{account}', [AccountController::class, 'update'])->name('cuentas.patch');
    Route::delete('cuentas/{account}', [AccountController::class, 'destroy'])->name('cuentas.destroy');

    // Recurring transactions
    Route::get('recurrentes', [RecurringTransactionController::class, 'index'])->name('recurrentes.index');
    Route::post('recurrentes', [RecurringTransactionController::class, 'store'])->name('recurrentes.store');
    Route::put('recurrentes/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurrentes.update');
    Route::patch('recurrentes/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurrentes.patch');
    Route::delete('recurrentes/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurrentes.destroy');
    Route::post('recurrentes/regenerate', [RecurringTransactionController::class, 'regenerate'])->name('recurrentes.regenerate');

    // Projection view
    Route::get('proyeccion', [ProjectionController::class, 'index'])->name('proyeccion.index');
});

require __DIR__.'/settings.php';
