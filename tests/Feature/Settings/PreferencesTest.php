<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─── Settings: Update (PUT /settings) ──────────────────────────────

test('settings update persists valid values', function (string $field, mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [$field => $value])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings[$field])->toEqual($value);
})->with([
    'theme' => ['theme', 'rose'],
    'density' => ['density', 'compact'],
    'start_section' => ['start_section', 'movements'],
    'projection_horizon' => ['projection_horizon', 6],
]);

test('settings update rejects invalid values', function (string $field, mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [$field => $value])
        ->assertSessionHasErrors($field);
})->with([
    'theme pink' => ['theme', 'pink'],
    'density cozy' => ['density', 'cozy'],
    'start_section wallet' => ['start_section', 'wallet'],
    'horizon 0' => ['projection_horizon', 0],
    'horizon 25' => ['projection_horizon', 25],
    'horizon abc' => ['projection_horizon', 'abc'],
]);

test('settings update ignores unknown keys', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [
            'theme' => 'rose',
            'is_admin' => true,
        ])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings['theme'])->toBe('rose');
    expect($user->settings)->not->toHaveKey('is_admin');
});

test('settings update merges with existing settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), ['theme' => 'rose'])
        ->assertNoContent();

    $this->actingAs($user)
        ->put(route('settings.update'), ['density' => 'compact'])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings['theme'])->toBe('rose');
    expect($user->settings['density'])->toBe('compact');
});

// ─── Settings: Profile Photo Upload ────────────────────────────────

test('photo upload stores valid image and updates settings', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('me.jpg', 100, 100);

    $response = $this->actingAs($user)
        ->post(route('settings.profile-photo.store'), ['photo' => $file]);

    $response->assertOk();
    $response->assertJsonStructure(['avatar_path', 'avatar_url']);

    $path = $response->json('avatar_path');

    expect($path)->toStartWith('avatars/');
    expect($response->json('avatar_url'))->toContain('/storage/');

    Storage::disk('public')->assertExists($path);

    $user->refresh();
    expect($user->settings['avatar_path'])->toBe($path);
});

test('photo upload rejects non-image file', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('a.txt', 1, 'text/plain');

    $this->actingAs($user)
        ->post(route('settings.profile-photo.store'), ['photo' => $file])
        ->assertSessionHasErrors('photo');
});

test('photo upload rejects oversized image', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('big.png')->size(3000);

    $this->actingAs($user)
        ->post(route('settings.profile-photo.store'), ['photo' => $file])
        ->assertSessionHasErrors('photo');
});

test('photo upload replaces previous avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    // Upload first photo
    $fileA = UploadedFile::fake()->image('avatar.jpg', 100, 100);
    $this->actingAs($user)
        ->post(route('settings.profile-photo.store'), ['photo' => $fileA])
        ->assertOk();

    $pathA = $user->fresh()->settings['avatar_path'];

    // Upload second photo (different extension to verify deletion)
    $fileB = UploadedFile::fake()->image('avatar.png', 100, 100);
    $this->actingAs($user)
        ->post(route('settings.profile-photo.store'), ['photo' => $fileB])
        ->assertOk();

    $pathB = $user->fresh()->settings['avatar_path'];

    Storage::disk('public')->assertExists($pathB);
    Storage::disk('public')->assertMissing($pathA);
});

test('photo upload requires authentication', function () {
    $response = $this->post(route('settings.profile-photo.store'));

    $response->assertRedirect(route('login'));
});

// ─── Login Redirect (via Fortify LoginResponse) ────────────────────

test('login redirects to dashboard when no start_section setting', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('login redirects to start_section movements', function () {
    $user = User::factory()->create([
        'settings' => ['start_section' => 'movements'],
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('movimientos.index', absolute: false));
});
