<?php

use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Relationships ────────────────────────────────────────────────

test('goal belongs to user', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    expect($goal->user)->toBeInstanceOf(User::class);
    expect($goal->user->id)->toBe($user->id);
});

test('user has many goals', function () {
    $user = User::factory()->create();
    Goal::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->goals)->toHaveCount(3);
    $user->goals->each(fn ($goal) => expect($goal->user_id)->toBe($user->id));
});

test('goal has many contributions', function () {
    $goal = Goal::factory()->create();
    GoalContribution::factory()->count(2)->create(['goal_id' => $goal->id]);

    expect($goal->contributions)->toHaveCount(2);
});

test('contribution belongs to goal', function () {
    $goal = Goal::factory()->create();
    $contribution = GoalContribution::factory()->create(['goal_id' => $goal->id]);

    expect($contribution->goal)->toBeInstanceOf(Goal::class);
    expect($contribution->goal->id)->toBe($goal->id);
});

// ─── Casts ────────────────────────────────────────────────────────

test('goal target_amount is decimal cast to string', function () {
    $goal = Goal::factory()->create(['target_amount' => 5000.50]);

    expect($goal->target_amount)->toBeString();
    expect((float) $goal->target_amount)->toBe(5000.50);
});

test('goal target_date is cast to carbon or null', function () {
    $goal = Goal::factory()->create(['target_date' => '2026-12-31']);

    expect($goal->target_date)->toBeInstanceOf(CarbonInterface::class);
    expect($goal->target_date->format('Y-m-d'))->toBe('2026-12-31');

    $goalWithoutDate = Goal::factory()->create(['target_date' => null]);

    expect($goalWithoutDate->target_date)->toBeNull();
});

test('goal completed_at is cast to carbon or null', function () {
    $goal = Goal::factory()->create(['completed_at' => '2026-08-15 10:00:00']);

    expect($goal->completed_at)->toBeInstanceOf(CarbonInterface::class);

    $openGoal = Goal::factory()->create(['completed_at' => null]);

    expect($openGoal->completed_at)->toBeNull();
});

test('contribution date is cast to carbon', function () {
    $contribution = GoalContribution::factory()->create(['date' => '2026-07-20']);

    expect($contribution->date)->toBeInstanceOf(CarbonInterface::class);
    expect($contribution->date->format('Y-m-d'))->toBe('2026-07-20');
});

test('contribution amount is decimal cast to string', function () {
    $contribution = GoalContribution::factory()->create(['amount' => 250.50]);

    expect($contribution->amount)->toBeString();
    expect((float) $contribution->amount)->toBe(250.50);
});

// ─── Accessors ────────────────────────────────────────────────────

test('progressAmount sums all contributions', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 200]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 350]);

    expect($goal->progress_amount)->toBe('550.00');
});

test('progressAmount is zero when there are no contributions', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);

    expect($goal->progress_amount)->toBe('0.00');
});

test('progressAmount uses the loaded withSum aggregate', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 120.50]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 80.50]);

    $loaded = Goal::withSum('contributions', 'amount')->find($goal->id);

    expect((float) $loaded->contributions_sum_amount)->toBe(201.0);
    expect($loaded->progress_amount)->toBe('201.00');
});

test('percent reflects partial progress', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 250]);

    expect($goal->percent)->toBe(25);
});

test('percent is 100 when the goal is met', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 1000]);

    expect($goal->percent)->toBe(100);
});

test('percent caps at 100 when the goal is exceeded', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 1300]);

    expect($goal->percent)->toBe(100);
});

test('percent returns zero when target is zero', function () {
    $goal = Goal::factory()->create(['target_amount' => 0]);

    expect($goal->percent)->toBe(0);
});

test('remainingAmount is target minus progress', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 400]);

    expect($goal->remaining_amount)->toBe('600.00');
});

test('remainingAmount never goes below zero', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 1500]);

    expect($goal->remaining_amount)->toBe('0.00');
});

test('isComplete is true when completed_at is set', function () {
    $goal = Goal::factory()->completed()->create();

    expect($goal->is_complete)->toBeTrue();
});

test('isComplete is false when completed_at is null', function () {
    $goal = Goal::factory()->create();

    expect($goal->is_complete)->toBeFalse();
});

// ─── syncCompletion ───────────────────────────────────────────────

test('syncCompletion marks the goal complete when the target is reached', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 600]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 400]);

    $goal->syncCompletion();

    expect($goal->fresh()->completed_at)->not->toBeNull();
    expect($goal->fresh()->is_complete)->toBeTrue();
});

test('syncCompletion keeps the goal complete when contributing more', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 1000]);
    $goal->syncCompletion();

    $completedAt = $goal->fresh()->completed_at;

    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 200]);
    $goal->syncCompletion();

    expect($goal->fresh()->completed_at->toDateTimeString())->toBe($completedAt->toDateTimeString());
    expect($goal->fresh()->is_complete)->toBeTrue();
});

test('syncCompletion reopens the goal when progress falls below the target', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 600]);
    $contribution = GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 400]);
    $goal->syncCompletion();

    expect($goal->fresh()->is_complete)->toBeTrue();

    $contribution->delete();
    $goal->syncCompletion();

    expect($goal->fresh()->completed_at)->toBeNull();
    expect($goal->fresh()->is_complete)->toBeFalse();
});

test('syncCompletion does nothing when the completion state does not change', function () {
    $goal = Goal::factory()->create(['target_amount' => 1000]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 200]);

    $goal->syncCompletion();

    expect($goal->fresh()->completed_at)->toBeNull();
});

// ─── apartadoAmount ───────────────────────────────────────────────

test('apartadoAmount sums contributions of active goals', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 200]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 300]);

    expect(Goal::apartadoAmount($user->id))->toBe(500.0);
});

test('apartadoAmount ignores contributions of completed goals', function () {
    $user = User::factory()->create();
    $activeGoal = Goal::factory()->create(['user_id' => $user->id]);
    $completedGoal = Goal::factory()->completed()->create(['user_id' => $user->id]);

    GoalContribution::factory()->create(['goal_id' => $activeGoal->id, 'amount' => 100]);
    GoalContribution::factory()->create(['goal_id' => $completedGoal->id, 'amount' => 900]);

    expect(Goal::apartadoAmount($user->id))->toBe(100.0);
});

test('apartadoAmount returns zero when the user has no contributions', function () {
    $user = User::factory()->create();

    expect(Goal::apartadoAmount($user->id))->toBe(0.0);
});

test('apartadoAmount does not count other users goals', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $goal = Goal::factory()->create(['user_id' => $user->id]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 300]);

    expect(Goal::apartadoAmount($otherUser->id))->toBe(0.0);
});

// ─── Factory ──────────────────────────────────────────────────────

test('factory completed state sets completed_at', function () {
    $goal = Goal::factory()->completed()->create();

    expect($goal->completed_at)->not->toBeNull();
    expect($goal->is_complete)->toBeTrue();
});

// ─── FK cascade ───────────────────────────────────────────────────

test('deleting a user cascades to goals and contributions', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);
    GoalContribution::factory()->count(2)->create(['goal_id' => $goal->id]);

    $user->delete();

    expect(Goal::where('user_id', $user->id)->count())->toBe(0);
    expect(GoalContribution::where('goal_id', $goal->id)->count())->toBe(0);
});

test('deleting a goal cascades to contributions', function () {
    $goal = Goal::factory()->create();
    GoalContribution::factory()->count(2)->create(['goal_id' => $goal->id]);

    $goal->delete();

    expect(GoalContribution::where('goal_id', $goal->id)->count())->toBe(0);
});
