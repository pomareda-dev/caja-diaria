<?php

use App\Models\Category;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Authentication ───────────────────────────────────────────────

test('unauthenticated user cannot access categories index', function () {
    $response = $this->get(route('categorias.index'));

    $response->assertRedirect(route('login'));
});

test('unauthenticated user cannot create a category', function () {
    $response = $this->post(route('categorias.store'), [
        'name' => 'Mercado',
        'kind' => 'expense',
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated user can view categories index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Category::factory()->expense()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    $response = $this->get(route('categorias.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Categorias/Index')
        ->has('categories', 1)
        ->has('selectedMonth')
        ->has('currentMonth')
    );
});

// ─── Create ───────────────────────────────────────────────────────

test('authenticated user can create an expense category without limit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('categorias.store'), [
        'name' => 'Mercado',
        'kind' => 'expense',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Mercado',
        'kind' => 'expense',
        'monthly_limit' => null,
    ]);
});

test('authenticated user can create a category with monthly limit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('categorias.store'), [
        'name' => 'Mercado',
        'kind' => 'expense',
        'monthly_limit' => 400.00,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Mercado',
        'monthly_limit' => '400.00',
    ]);
});

test('authenticated user can create an income category', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('categorias.store'), [
        'name' => 'Sueldo',
        'kind' => 'income',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Sueldo',
        'kind' => 'income',
    ]);
});

// ─── Update ───────────────────────────────────────────────────────

test('authenticated user can update their own category', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $category = Category::factory()->expense()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    $response = $this->put(route('categorias.update', $category), [
        'name' => 'Supermercado',
        'kind' => 'expense',
        'monthly_limit' => 500.00,
    ]);

    $response->assertRedirect();
    $category->refresh();

    expect($category->name)->toBe('Supermercado');
    expect($category->monthly_limit)->toBe('500.00');
});

test('user cannot update another users category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    $response = $this->put(route('categorias.update', $category), [
        'name' => 'Hacked',
        'kind' => 'expense',
    ]);

    $response->assertForbidden();
});

// ─── Delete ───────────────────────────────────────────────────────

test('authenticated user can delete their own category', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $category = Category::factory()->create(['user_id' => $user->id]);

    $response = $this->delete(route('categorias.destroy', $category));

    $response->assertRedirect();
    $this->assertModelMissing($category);
});

test('user cannot delete another users category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    $response = $this->delete(route('categorias.destroy', $category));

    $response->assertForbidden();
    $this->assertModelExists($category);
});

test('deleting_category_with_movements_sets_category_id_null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    $movement1 = Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-05',
        'amount' => -100,
        'source' => 'manual',
    ]);

    $movement2 = Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-10',
        'amount' => -200,
        'source' => 'manual',
    ]);

    $response = $this->delete(route('categorias.destroy', $category));

    $response->assertRedirect();
    $this->assertModelMissing($category);

    $movement1->refresh();
    $movement2->refresh();

    expect($movement1->category_id)->toBeNull();
    expect($movement2->category_id)->toBeNull();
});

// ─── Validation ───────────────────────────────────────────────────

test('validation: name is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('categorias.store'), [
        'kind' => 'expense',
    ]);

    $response->assertSessionHasErrors('name');
});

test('validation: kind must be valid', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('categorias.store'), [
        'name' => 'Test',
        'kind' => 'invalid-kind',
    ]);

    $response->assertSessionHasErrors('kind');
});

test('validation: monthly_limit must be numeric', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('categorias.store'), [
        'name' => 'Test',
        'kind' => 'expense',
        'monthly_limit' => 'not-a-number',
    ]);

    $response->assertSessionHasErrors('monthly_limit');
});

test('validation: name must be unique per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    $response = $this->post(route('categorias.store'), [
        'name' => 'Mercado',
        'kind' => 'expense',
    ]);

    $response->assertSessionHasErrors('name');
});

test('validation: same name allowed for different users', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Category::factory()->create([
        'user_id' => $user1->id,
        'name' => 'Mercado',
    ]);

    $this->actingAs($user2);

    $response = $this->post(route('categorias.store'), [
        'name' => 'Mercado',
        'kind' => 'expense',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'user_id' => $user2->id,
        'name' => 'Mercado',
    ]);
});

test('validation: update ignores self name uniqueness', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    $response = $this->put(route('categorias.update', $category), [
        'name' => 'Mercado',
        'kind' => 'expense',
    ]);

    $response->assertRedirect();
    $category->refresh();
    expect($category->name)->toBe('Mercado');
});

// ─── Spent Calculation ────────────────────────────────────────────

test('spent returns zero for category with no movements', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->withLimit(400)->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
        'sort_order' => 0,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.spent', 0)
    );

    Carbon::setTestNow();
});

test('spent sums negative movements for category in the month', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->withLimit(400)->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    // Two expenses in the month
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-05',
        'amount' => -100,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-10',
        'amount' => -250.50,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.spent', 350.50)
    );

    Carbon::setTestNow();
});

test('spent only counts actual movements not projected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->withLimit(400)->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
        'sort_order' => 0,
    ]);

    // Past expense — should count
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-05',
        'amount' => -100,
        'source' => 'manual',
    ]);

    // Future expense — should NOT count (projected)
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-25',
        'amount' => -200,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.spent', 100)
    );

    Carbon::setTestNow();
});

test('spent reflects net spending including refunds for expense categories', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->withLimit(400)->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
        'sort_order' => 0,
    ]);

    // Expense: -50
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-05',
        'amount' => -50,
        'source' => 'manual',
    ]);

    // Income/refund in same category: +200 (more income than expense)
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-10',
        'amount' => 200,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Balance = -50 + 200 = +150 → net is positive, so spent = 0
    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.spent', 0)
        ->where('categories.0.balance', 150)
    );

    Carbon::setTestNow();
});

test('spent nets expenses and refunds when net is still negative', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->withLimit(500)->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
        'sort_order' => 0,
    ]);

    // Expense: -300
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-05',
        'amount' => -300,
        'source' => 'manual',
    ]);

    // Partial refund: +50
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-10',
        'amount' => 50,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Balance = -300 + 50 = -250 → spent = abs(-250) = 250
    // Old bug would show spent = 300 (only negative amounts, ignoring refund)
    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.spent', 250)
        ->where('categories.0.balance', -250)
    );

    Carbon::setTestNow();
});

test('spent from previous month does not affect current month spent', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->withLimit(400)->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
        'sort_order' => 0,
    ]);

    // Previous month expense
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-06-15',
        'amount' => -300,
        'source' => 'manual',
    ]);

    // Current month expense
    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-10',
        'amount' => -100,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.spent', 100)
    );

    Carbon::setTestNow();
});

// ─── Balance Calculation ──────────────────────────────────────────

test('balance returns zero for category with no movements', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.balance', 0)
    );

    Carbon::setTestNow();
});

test('balance sums income and expenses for category in the month', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Sueldo',
        'kind' => 'income',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-05',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-10',
        'amount' => -200,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.balance', 800)
    );

    Carbon::setTestNow();
});

test('balance only counts actual movements not projected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-05',
        'amount' => -100,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-25',
        'amount' => -200,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.balance', -100)
    );

    Carbon::setTestNow();
});

test('balance from previous month does not affect current month balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->expense()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-06-15',
        'amount' => -300,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'date' => '2026-07-10',
        'amount' => -100,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('categorias.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.balance', -100)
    );

    Carbon::setTestNow();
});

test('categories with limit show monthly_limit and categories without show null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Category::factory()->expense()->withLimit(400)->create([
        'user_id' => $user->id,
        'name' => 'Con límite',
        'sort_order' => 0,
    ]);

    Category::factory()->expense()->withoutLimit()->create([
        'user_id' => $user->id,
        'name' => 'Sin límite',
        'sort_order' => 1,
    ]);

    $response = $this->get(route('categorias.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('categories', 2)
        ->where('categories.0.name', 'Con límite')
        ->where('categories.0.monthly_limit', 400)
        ->where('categories.1.name', 'Sin límite')
        ->where('categories.1.monthly_limit', null)
    );
});

// ─── Month Filtering ──────────────────────────────────────────────

test('categories index respects month filter', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('categorias.index', ['month' => '2026-06']));

    $response->assertInertia(fn ($page) => $page
        ->where('selectedMonth', '2026-06')
    );
});

test('defaults to current month when no month filter provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $now = now()->format('Y-m');

    $response = $this->get(route('categorias.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('selectedMonth', $now)
    );
});

// ─── Category ordering ────────────────────────────────────────────

test('categories are ordered by sort_order then name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Segunda',
        'sort_order' => 1,
    ]);

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Primera',
        'sort_order' => 0,
    ]);

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Tercera',
        'sort_order' => 2,
    ]);

    $response = $this->get(route('categorias.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.name', 'Primera')
        ->where('categories.1.name', 'Segunda')
        ->where('categories.2.name', 'Tercera')
    );
});

// ─── Multiple users ───────────────────────────────────────────────

test('user only sees their own categories', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Category::factory()->create([
        'user_id' => $user1->id,
        'name' => 'De usuario 1',
    ]);

    Category::factory()->create([
        'user_id' => $user2->id,
        'name' => 'De usuario 2',
    ]);

    $this->actingAs($user1);

    $response = $this->get(route('categorias.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('categories', 1)
        ->where('categories.0.name', 'De usuario 1')
    );
});
