<?php

use App\Http\Controllers\MovementController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('movimientos', [MovementController::class, 'index'])->name('movimientos.index');
    Route::post('movimientos', [MovementController::class, 'store'])->name('movimientos.store');
    Route::put('movimientos/{movement}', [MovementController::class, 'update'])->name('movimientos.update');
    Route::patch('movimientos/{movement}', [MovementController::class, 'update'])->name('movimientos.patch');
    Route::delete('movimientos/{movement}', [MovementController::class, 'destroy'])->name('movimientos.destroy');

    Route::inertia('categorias', 'Categorias/Index')->name('categorias.index');
    Route::inertia('cuentas', 'Cuentas/Index')->name('cuentas.index');
    Route::inertia('recurrentes', 'Recurrentes/Index')->name('recurrentes.index');
});

require __DIR__.'/settings.php';
