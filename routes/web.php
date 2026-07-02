<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('movimientos', 'Movimientos/Index')->name('movimientos.index');
    Route::inertia('categorias', 'Categorias/Index')->name('categorias.index');
    Route::inertia('cuentas', 'Cuentas/Index')->name('cuentas.index');
    Route::inertia('recurrentes', 'Recurrentes/Index')->name('recurrentes.index');
});

require __DIR__.'/settings.php';
