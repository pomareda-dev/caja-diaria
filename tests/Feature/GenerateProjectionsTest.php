<?php

use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\ProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Service: generateForUser ─────────────────────────────────────

test('generate creates correct number of projected movements', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Falabella',
        'amount' => -300.96,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => '2027-07-01',
        'active' => true,
    ]);

    // 12 months: Aug 2026 → Jul 2027
    $count = $service->generateForUser($user->id);

    expect($count)->toBe(12);

    // Verify one per month
    $movements = Movement::where('user_id', $user->id)->where('source', 'recurring')->get();

    expect($movements)->toHaveCount(12);

    foreach ($movements as $m) {
        expect($m->source)->toBe('recurring');
        expect($m->is_projected)->toBeTrue();
        expect($m->amount)->toBe('-300.96');
        expect($m->description)->toBe('Falabella');
    }

    Carbon::setTestNow();
});

test('generate creates 24 months when end_month is far', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Falabella',
        'amount' => -300.96,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => '2028-07-01',
        'active' => true,
    ]);

    $count = $service->generateForUser($user->id);

    expect($count)->toBe(24);

    Carbon::setTestNow();
});

test('generate skips past dates', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Template with start_month before today — should only generate FUTURE months
    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Alquiler',
        'amount' => -1200,
        'day_of_month' => 1,
        'start_month' => '2026-01-01',
        'end_month' => '2026-12-01',
        'active' => true,
    ]);

    $count = $service->generateForUser($user->id);

    // Months: Aug-Dec 2026 = 5 months (Jan-Jul 2026 are past/skipped)
    expect($count)->toBe(5);

    Carbon::setTestNow();
});

test('generate uses default horizon when no end_month set', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-01-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Suscripción',
        'amount' => -50,
        'day_of_month' => 10,
        'start_month' => '2026-02-01',
        'end_month' => null, // no end_month → use default horizon (12 months)
        'active' => true,
    ]);

    $count = $service->generateForUser($user->id);

    // Feb 2026 → Jan 2027 = 12 months
    expect($count)->toBe(12);

    Carbon::setTestNow();
});

test('generate sets correct dates with day clamping for february', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-01-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Fin mes',
        'amount' => -100,
        'day_of_month' => 31, // 31 doesn't exist in Feb
        'start_month' => '2026-02-01',
        'end_month' => '2026-04-01',
        'active' => true,
    ]);

    $count = $service->generateForUser($user->id);

    expect($count)->toBe(3);

    $movements = Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->orderBy('date')
        ->get();

    // Feb 2026 has 28 days, so day 31 → 28
    expect($movements[0]->date->format('Y-m-d'))->toBe('2026-02-28');
    // March has 31 days
    expect($movements[1]->date->format('Y-m-d'))->toBe('2026-03-31');
    // April has 30 days
    expect($movements[2]->date->format('Y-m-d'))->toBe('2026-04-30');

    Carbon::setTestNow();
});

test('generate does not create duplicates (idempotent)', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Falabella',
        'amount' => -300.96,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => '2026-10-01',
        'active' => true,
    ]);

    // First call
    $firstCount = $service->generateForUser($user->id);
    expect($firstCount)->toBe(3);

    // Second call — should not create duplicates
    $secondCount = $service->generateForUser($user->id);
    expect($secondCount)->toBe(0);

    $totalMovements = Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->count();

    expect($totalMovements)->toBe(3);

    Carbon::setTestNow();
});

// ─── Service: regenerateForUser ───────────────────────────────────

test('regenerate deletes existing and recreates', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Falabella',
        'amount' => -300.96,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => '2026-10-01',
        'active' => true,
    ]);

    // Generate first batch
    $service->generateForUser($user->id);

    expect(Movement::where('user_id', $user->id)->where('source', 'recurring')->count())->toBe(3);

    // Regenerate — deletes and recreates
    $count = $service->regenerateForUser($user->id);

    expect($count)->toBe(3);
    expect(Movement::where('user_id', $user->id)->where('source', 'recurring')->count())->toBe(3);

    Carbon::setTestNow();
});

// ─── Only active templates ────────────────────────────────────────

test('generate only processes active templates', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Activo',
        'amount' => -100,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => '2026-08-01',
        'active' => true,
    ]);

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Inactivo',
        'amount' => -200,
        'day_of_month' => 10,
        'start_month' => '2026-08-01',
        'end_month' => '2026-08-01',
        'active' => false,
    ]);

    $count = $service->generateForUser($user->id);

    expect($count)->toBe(1); // Only the active one
    expect(Movement::where('user_id', $user->id)->where('description', 'Activo')->exists())->toBeTrue();
    expect(Movement::where('user_id', $user->id)->where('description', 'Inactivo')->exists())->toBeFalse();

    Carbon::setTestNow();
});

// ─── Regenerate via controller ────────────────────────────────────

test('regenerate endpoint calls service and returns success', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test',
        'amount' => -100,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => '2026-08-01',
        'active' => true,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->post(route('recurrentes.regenerate'));

    $response->assertRedirect();
    expect(Movement::where('user_id', $user->id)->where('source', 'recurring')->count())->toBe(1);

    Carbon::setTestNow();
});

// ─── Command ──────────────────────────────────────────────────────

test('generate projections artisan command works', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Falabella',
        'amount' => -300.96,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => '2026-10-01',
        'active' => true,
    ]);

    $this->artisan('app:generate-projections')
        ->expectsOutputToContain('3 projected movements')
        ->assertExitCode(0);

    $this->assertDatabaseHas('movements', [
        'user_id' => $user->id,
        'source' => 'recurring',
        'description' => 'Falabella',
    ]);

    // Running again must NOT duplicate (command is idempotent / non-destructive)
    $this->artisan('app:generate-projections')
        ->expectsOutputToContain('0 projected movements')
        ->assertExitCode(0);

    expect(Movement::where('user_id', $user->id)->where('source', 'recurring')->count())->toBe(3);

    Carbon::setTestNow();
});

// ─── Projection Horizon (settings vs --horizon flag) ───────────────

test('projection command uses user settings horizon when --horizon omitted', function () {
    $user = User::factory()->create(['settings' => ['projection_horizon' => 2]]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Settings Horizon',
        'amount' => -100,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => null,
        'active' => true,
    ]);

    $this->artisan('app:generate-projections')
        ->assertExitCode(0);

    expect(Movement::where('user_id', $user->id)->where('source', 'recurring')->count())->toBe(2);

    Carbon::setTestNow();
});

test('projection command --horizon overrides user settings', function () {
    $user = User::factory()->create(['settings' => ['projection_horizon' => 2]]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Override Horizon',
        'amount' => -100,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => null,
        'active' => true,
    ]);

    $this->artisan('app:generate-projections --horizon=3')
        ->assertExitCode(0);

    expect(Movement::where('user_id', $user->id)->where('source', 'recurring')->count())->toBe(3);

    Carbon::setTestNow();
});
