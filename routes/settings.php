<?php

use App\Http\Controllers\Settings\PreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserProfilePhotoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::put('config', [PreferencesController::class, 'update'])->name('settings.update');

    Route::get('config', fn () => redirect('/config/perfil'));

    Route::get('config/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('config/perfil', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('config/perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('config/seguridad', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('config/contrasena', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('config/apariencia', 'settings/Appearance')->name('appearance.edit');

    Route::get('config/preferencias', [PreferencesController::class, 'edit'])
        ->name('preferences.edit');

    Route::post('config/foto-perfil', [UserProfilePhotoController::class, 'store'])
        ->name('config.profile-photo.store');
});
