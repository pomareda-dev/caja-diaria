<?php

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Authentication ───────────────────────────────────────────────

test('unauthenticated user cannot access recurrentes index', function () {
    $response = $this->get(route('recurrentes.index'));

    $response->assertRedirect(route('login'));
});

test('unauthenticated user cannot create a recurring template', function () {
    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Falabella',
        'amount' => -300.96,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated user can view recurrentes index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Falabella',
    ]);

    $response = $this->get(route('recurrentes.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Recurrentes/Index')
        ->has('templates', 1)
        ->has('categories')
    );
});

// ─── Create ───────────────────────────────────────────────────────

test('authenticated user can create a recurring template', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Falabella',
        'amount' => -300.96,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('recurring_transactions', [
        'user_id' => $user->id,
        'name' => 'Falabella',
        'amount' => '-300.96',
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => null,
        'active' => true,
    ]);
});

test('authenticated user can create a recurring template with category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Alquiler',
        'amount' => -1200,
        'category_id' => $category->id,
        'day_of_month' => 1,
        'start_month' => '2026-01-01',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('recurring_transactions', [
        'user_id' => $user->id,
        'name' => 'Alquiler',
        'category_id' => $category->id,
    ]);
});

test('user can create a template with end_month', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Suscripción',
        'amount' => -50,
        'day_of_month' => 15,
        'start_month' => '2026-01-01',
        'end_month' => '2027-12-01',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('recurring_transactions', [
        'user_id' => $user->id,
        'name' => 'Suscripción',
        'end_month' => '2027-12-01',
    ]);
});

// ─── Update ───────────────────────────────────────────────────────

test('authenticated user can update their own recurring template', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $template = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Falabella',
        'amount' => -300.96,
    ]);

    $response = $this->put(route('recurrentes.update', $template), [
        'name' => 'Falabella Actualizado',
        'amount' => -350.00,
        'day_of_month' => 10,
        'start_month' => '2026-08-01',
    ]);

    $response->assertRedirect();
    $template->refresh();

    expect($template->name)->toBe('Falabella Actualizado');
    expect($template->amount)->toBe('-350.00');
    expect($template->day_of_month)->toBe(10);
});

test('user cannot update another users recurring template', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $template = RecurringTransaction::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    $response = $this->put(route('recurrentes.update', $template), [
        'name' => 'Hacked',
        'amount' => -999,
        'day_of_month' => 1,
        'start_month' => '2026-01-01',
    ]);

    $response->assertForbidden();
});

// ─── Delete ───────────────────────────────────────────────────────

test('authenticated user can delete their own recurring template', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $template = RecurringTransaction::factory()->create(['user_id' => $user->id]);

    $response = $this->delete(route('recurrentes.destroy', $template));

    $response->assertRedirect();
    $this->assertModelMissing($template);
});

test('user cannot delete another users recurring template', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $template = RecurringTransaction::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    $response = $this->delete(route('recurrentes.destroy', $template));

    $response->assertForbidden();
    $this->assertModelExists($template);
});

// ─── Validation ───────────────────────────────────────────────────

test('validation: name is required for recurring', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'amount' => -100,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
    ]);

    $response->assertSessionHasErrors('name');
});

test('validation: amount must be numeric for recurring', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Test',
        'amount' => 'not-a-number',
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('validation: amount must not be zero for recurring', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Test',
        'amount' => 0,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('validation: day_of_month is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Test',
        'amount' => -100,
        'start_month' => '2026-08-01',
    ]);

    $response->assertSessionHasErrors('day_of_month');
});

test('validation: day_of_month must be between 1 and 31', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Test',
        'amount' => -100,
        'day_of_month' => 32,
        'start_month' => '2026-08-01',
    ]);

    $response->assertSessionHasErrors('day_of_month');
});

test('validation: start_month is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Test',
        'amount' => -100,
        'day_of_month' => 5,
    ]);

    $response->assertSessionHasErrors('start_month');
});

test('validation: end_month must be after start_month', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Test',
        'amount' => -100,
        'day_of_month' => 5,
        'start_month' => '2026-12-01',
        'end_month' => '2026-06-01',
    ]);

    $response->assertSessionHasErrors('end_month');
});

test('validation: category must belong to user when provided', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCategory = Category::factory()->create(['user_id' => $otherUser->id]);
    $this->actingAs($user);

    $response = $this->post(route('recurrentes.store'), [
        'name' => 'Test',
        'amount' => -100,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'category_id' => $otherCategory->id,
    ]);

    $response->assertSessionHasErrors('category_id');
});

// ─── User scoping ─────────────────────────────────────────────────

test('user only sees their own recurring templates', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    RecurringTransaction::factory()->create([
        'user_id' => $user1->id,
        'name' => 'De usuario 1',
    ]);

    RecurringTransaction::factory()->create([
        'user_id' => $user2->id,
        'name' => 'De usuario 2',
    ]);

    $this->actingAs($user1);

    $response = $this->get(route('recurrentes.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('templates', 1)
        ->where('templates.0.name', 'De usuario 1')
    );
});
