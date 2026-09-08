<?php

use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Guest redirect ───────────────────────────────────────────────

test('guests are redirected to the login page', function () {
    $this->get(route('metas.index'))->assertRedirect(route('login'));
    $this->post(route('metas.store'))->assertRedirect(route('login'));
});

// ─── Index ────────────────────────────────────────────────────────

test('index renders the goals page with the goal payload', function () {
    $user = User::factory()->create();

    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'name' => 'Laptop nueva',
        'target_amount' => 2000,
        'target_date' => now()->addMonths(2)->toDateString(),
    ]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 500]);

    $response = $this->actingAs($user)->get(route('metas.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Metas/Index')
        ->has('goals', 1)
        ->where('goals.0.name', 'Laptop nueva')
        ->where('goals.0.target_amount', fn ($value) => (float) $value === 2000.0)
        ->where('goals.0.target_date', now()->addMonths(2)->toDateString())
        ->where('goals.0.progress_amount', fn ($value) => (float) $value === 500.0)
        ->where('goals.0.percent', 25)
        ->where('goals.0.remaining_amount', fn ($value) => (float) $value === 1500.0)
        ->where('goals.0.days_to_target', fn ($value) => is_int($value) && $value > 0)
        ->where('goals.0.can_delete', false)
        ->where('goals.0.is_complete', false)
        ->has('goals.0.contributions', 1)
        ->where('goals.0.contributions.0.amount', fn ($value) => (float) $value === 500.0));
});

test('index flags can_delete true when the goal has no contributions', function () {
    $user = User::factory()->create();
    Goal::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('metas.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Metas/Index')
        ->where('goals.0.can_delete', true)
        ->has('goals.0.contributions', 0));
});

test('index reports days_to_target negative when the target date is past', function () {
    $user = User::factory()->create();
    Goal::factory()->create([
        'user_id' => $user->id,
        'target_date' => now()->subDays(5)->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('metas.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Metas/Index')
        ->where('goals.0.days_to_target', fn ($value) => is_int($value) && $value < 0));
});

test('index reports days_to_target null when there is no target date', function () {
    $user = User::factory()->create();
    Goal::factory()->create([
        'user_id' => $user->id,
        'target_date' => null,
    ]);

    $response = $this->actingAs($user)->get(route('metas.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Metas/Index')
        ->where('goals.0.days_to_target', null));
});

test('index computes summary apartado and available_real', function () {
    $user = User::factory()->create();

    // Real balance: one real income of 1300
    Movement::factory()->create([
        'user_id' => $user->id,
        'amount' => 1300,
        'is_projected' => false,
    ]);

    $activeGoal = Goal::factory()->create(['user_id' => $user->id]);
    GoalContribution::factory()->create(['goal_id' => $activeGoal->id, 'amount' => 300]);

    // Completed goals must not count toward apartado
    $completedGoal = Goal::factory()->completed()->create(['user_id' => $user->id]);
    GoalContribution::factory()->create(['goal_id' => $completedGoal->id, 'amount' => 700]);

    $response = $this->actingAs($user)->get(route('metas.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Metas/Index')
        ->where('summary.apartado', fn ($value) => (float) $value === 300.0)
        ->where('summary.available_real', fn ($value) => (float) $value === 1000.0));
});

test('index only shows the authenticated users goals', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Goal::factory()->count(2)->create(['user_id' => $user->id]);
    Goal::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->get(route('metas.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Metas/Index')
        ->has('goals', 2));
});

// ─── Store ────────────────────────────────────────────────────────

test('store creates a goal', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('metas.store'), [
        'name' => 'Viaje a Bariloche',
        'target_amount' => 3000,
        'target_date' => '2026-12-31',
    ]);

    $response->assertRedirect();

    expect(Goal::count())->toBe(1);
    $goal = Goal::first();
    expect($goal->user_id)->toBe($user->id);
    expect($goal->name)->toBe('Viaje a Bariloche');
    expect($goal->target_amount)->toBe('3000.00');
    expect($goal->target_date->format('Y-m-d'))->toBe('2026-12-31');
});

test('store creates a goal without target date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('metas.store'), [
        'name' => 'Fondo de emergencia',
        'target_amount' => 5000,
    ])->assertRedirect();

    $goal = Goal::first();
    expect($goal->target_date)->toBeNull();
});

test('store validates name is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('metas.store'), [
        'name' => '',
        'target_amount' => 1000,
    ]);

    $response->assertSessionHasErrors('name');
    expect(Goal::count())->toBe(0);
});

test('store validates target_amount is greater than zero', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('metas.store'), [
        'name' => 'Laptop nueva',
        'target_amount' => 0,
    ]);

    $response->assertSessionHasErrors('target_amount');
    expect(Goal::count())->toBe(0);
});

test('store validates target_date is a valid date', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('metas.store'), [
        'name' => 'Laptop nueva',
        'target_amount' => 1000,
        'target_date' => 'not-a-date',
    ]);

    $response->assertSessionHasErrors('target_date');
    expect(Goal::count())->toBe(0);
});

// ─── Update ───────────────────────────────────────────────────────

test('update modifies the goal fields', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'name' => 'Laptop nueva',
        'target_amount' => 2000,
    ]);

    $response = $this->actingAs($user)->put(route('metas.update', $goal), [
        'name' => 'Laptop gamer',
        'target_amount' => 2500,
        'target_date' => '2026-10-01',
    ]);

    $response->assertRedirect();

    $goal->refresh();
    expect($goal->name)->toBe('Laptop gamer');
    expect($goal->target_amount)->toBe('2500.00');
    expect($goal->target_date->format('Y-m-d'))->toBe('2026-10-01');
});

test('update reopens a completed goal when the target is raised', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'name' => 'Fondo de emergencia',
        'target_amount' => 1000,
    ]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 1000]);
    $goal->syncCompletion();

    expect($goal->fresh()->is_complete)->toBeTrue();

    $this->actingAs($user)->put(route('metas.update', $goal), [
        'name' => 'Fondo de emergencia',
        'target_amount' => 1500,
    ])->assertRedirect();

    expect($goal->fresh()->completed_at)->toBeNull();
    expect($goal->fresh()->is_complete)->toBeFalse();
});

test('update keeps a completed goal completed when lowering the target', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'name' => 'Fondo de emergencia',
        'target_amount' => 1000,
    ]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 1500]);
    $goal->syncCompletion();

    expect($goal->fresh()->is_complete)->toBeTrue();

    $this->actingAs($user)->put(route('metas.update', $goal), [
        'name' => 'Fondo de emergencia',
        'target_amount' => 1200,
    ])->assertRedirect();

    expect($goal->fresh()->is_complete)->toBeTrue();
});

// ─── Contributions ────────────────────────────────────────────────

test('store contribution registers the contribution and completes the goal', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'target_amount' => 1000,
    ]);

    $response = $this->actingAs($user)->post(route('metas.aportes.store', $goal), [
        'amount' => 600,
        'date' => now()->toDateString(),
        'notes' => 'Primer aporte',
    ]);

    $response->assertRedirect();

    expect(GoalContribution::count())->toBe(1);
    $contribution = GoalContribution::first();
    expect($contribution->goal_id)->toBe($goal->id);
    expect($contribution->amount)->toBe('600.00');
    expect($contribution->notes)->toBe('Primer aporte');

    // Not yet complete: 600 < 1000
    expect($goal->fresh()->is_complete)->toBeFalse();
});

test('store contribution allows a contribution on a completed goal', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'target_amount' => 1000,
    ]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 1000]);
    $goal->syncCompletion();

    $this->actingAs($user)->post(route('metas.aportes.store', $goal), [
        'amount' => 100,
        'date' => now()->toDateString(),
    ])->assertRedirect();

    $goal->refresh();
    expect($goal->contributions()->count())->toBe(2);
    expect($goal->is_complete)->toBeTrue();
});

test('store contribution validates amount is greater than zero', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('metas.aportes.store', $goal), [
        'amount' => 0,
        'date' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('amount');
    expect(GoalContribution::count())->toBe(0);
});

test('store contribution rejects a future date', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('metas.aportes.store', $goal), [
        'amount' => 100,
        'date' => now()->addDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('date');
    expect(GoalContribution::count())->toBe(0);
});

test('destroy contribution reopens the goal', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'target_amount' => 1000,
    ]);
    $contributionA = GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 600]);
    $contributionB = GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 400]);
    $goal->syncCompletion();

    expect($goal->fresh()->is_complete)->toBeTrue();

    $response = $this->actingAs($user)->delete(route('metas.aportes.destroy', [$goal, $contributionB]));

    $response->assertRedirect();
    expect(GoalContribution::count())->toBe(1);
    expect(GoalContribution::find($contributionB->id))->toBeNull();

    $goal->refresh();
    expect($goal->is_complete)->toBeFalse();
});

test('destroy contribution returns 404 when the contribution does not belong to the goal', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);
    $otherGoal = Goal::factory()->create(['user_id' => $user->id]);
    $contribution = GoalContribution::factory()->create(['goal_id' => $otherGoal->id]);

    $response = $this->actingAs($user)->delete(route('metas.aportes.destroy', [$goal, $contribution]));

    $response->assertStatus(404);
    expect(GoalContribution::count())->toBe(1);
});

// ─── Destroy ──────────────────────────────────────────────────────

test('destroy removes a goal without contributions', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->delete(route('metas.destroy', $goal));

    $response->assertRedirect();
    expect(Goal::count())->toBe(0);
});

test('destroy returns 409 when the goal has contributions', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);
    GoalContribution::factory()->create(['goal_id' => $goal->id]);

    $response = $this->actingAs($user)->delete(route('metas.destroy', $goal));

    $response->assertStatus(409);
    expect(Goal::count())->toBe(1);
});

// ─── Authorization ────────────────────────────────────────────────

test('user cannot update another users goal', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $goal = Goal::factory()->create(['user_id' => $user1->id]);

    $response = $this->actingAs($user2)->put(route('metas.update', $goal), [
        'name' => 'Updated name',
        'target_amount' => 1000,
    ]);

    $response->assertStatus(403);
});

test('user cannot delete another users goal', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $goal = Goal::factory()->create(['user_id' => $user1->id]);

    $response = $this->actingAs($user2)->delete(route('metas.destroy', $goal));

    $response->assertStatus(403);
});

test('user cannot contribute to another users goal', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $goal = Goal::factory()->create(['user_id' => $user1->id]);

    $response = $this->actingAs($user2)->post(route('metas.aportes.store', $goal), [
        'amount' => 100,
        'date' => now()->toDateString(),
    ]);

    $response->assertStatus(403);
    expect(GoalContribution::count())->toBe(0);
});

test('user cannot delete a contribution of another users goal', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $goal = Goal::factory()->create(['user_id' => $user1->id]);
    $contribution = GoalContribution::factory()->create(['goal_id' => $goal->id]);

    $response = $this->actingAs($user2)->delete(route('metas.aportes.destroy', [$goal, $contribution]));

    $response->assertStatus(403);
    expect(GoalContribution::count())->toBe(1);
});

// ─── Full cycle via endpoints ─────────────────────────────────────

test('full cycle via endpoints: create, contribute to complete, delete contribution to reopen', function () {
    $user = User::factory()->create();

    // Create the goal
    $this->actingAs($user)->post(route('metas.store'), [
        'name' => 'Consola de videojuegos',
        'target_amount' => 1000,
    ])->assertRedirect();

    $goal = Goal::first();

    // Contribute until complete
    $this->actingAs($user)->post(route('metas.aportes.store', $goal), [
        'amount' => 600,
        'date' => now()->toDateString(),
    ])->assertRedirect();
    $this->actingAs($user)->post(route('metas.aportes.store', $goal), [
        'amount' => 400,
        'date' => now()->toDateString(),
    ])->assertRedirect();

    $goal->refresh();
    expect($goal->is_complete)->toBeTrue();

    $this->actingAs($user)->get(route('metas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Metas/Index')
            ->where('goals.0.is_complete', true)
            ->where('goals.0.progress_amount', fn ($value) => (float) $value === 1000.0));

    // Delete the last contribution (400): falls below the target → reopen
    $lastContribution = $goal->contributions()->orderByDesc('id')->first();

    $this->actingAs($user)->delete(route('metas.aportes.destroy', [$goal, $lastContribution]))
        ->assertRedirect();

    $goal->refresh();
    expect($goal->is_complete)->toBeFalse();

    $this->actingAs($user)->get(route('metas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Metas/Index')
            ->where('goals.0.is_complete', false)
            ->where('goals.0.progress_amount', fn ($value) => (float) $value === 600.0));
});
