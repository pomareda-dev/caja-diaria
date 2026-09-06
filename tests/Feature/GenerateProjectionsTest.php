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

test('regenerate preserves realized recurring movements and rebuilds only projections', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);

    Carbon::setTestNow(Carbon::parse('2026-09-05'));

    // Realized recurring movement (already past, marked real) — must survive regeneration
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-08-28',
        'description' => 'Sueldo',
        'amount' => 4175.00,
        'source' => 'recurring',
        'is_projected' => false,
    ]);

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Sueldo',
        'amount' => 4175.00,
        'day_of_month' => 28,
        'start_month' => '2026-07-01',
        'end_month' => null,
        'active' => true,
    ]);

    $service->generateForUser($user->id);

    $projectedBefore = Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count();
    expect($projectedBefore)->toBeGreaterThan(0);

    $balanceBefore = (float) Movement::realBalance($user->id);

    $service->regenerateForUser($user->id);

    // Realized recurring movement is preserved
    expect(Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', false)
        ->count())->toBe(1);

    // Real balance unchanged
    expect((float) Movement::realBalance($user->id))->toBe($balanceBefore);

    // Future projections rebuilt
    expect(Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count())->toBe($projectedBefore);

    Carbon::setTestNow();
});

test('deleting a template and regenerating preserves realized movements of untouched templates', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-09-05'));

    $sueldo = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Sueldo',
        'amount' => 4175.00,
        'day_of_month' => 28,
        'start_month' => '2026-07-01',
        'end_month' => null,
        'active' => true,
    ]);

    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Huancayo',
        'amount' => -820.00,
        'day_of_month' => 15,
        'start_month' => '2026-07-01',
        'end_month' => '2027-04-01',
        'active' => true,
    ]);

    // Realized Sueldo movement (real history) linked to the Sueldo template
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-08-28',
        'description' => 'Sueldo',
        'amount' => 4175.00,
        'source' => 'recurring',
        'recurring_id' => $sueldo->id,
        'is_projected' => false,
    ]);

    $this->post(route('recurrentes.regenerate'));

    expect(Movement::where('description', 'Huancayo')->where('is_projected', true)->count())->toBeGreaterThan(0);

    // User flow: delete Huancayo template, then regenerate projections
    $huancayo = RecurringTransaction::where('name', 'Huancayo')->firstOrFail();
    $this->delete(route('recurrentes.destroy', $huancayo));
    $this->post(route('recurrentes.regenerate'));

    // Realized Sueldo movement preserved and real balance unchanged
    expect(Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', false)
        ->count())->toBe(1);
    expect((float) Movement::realBalance($user->id))->toBe(4175.0);

    // Huancayo projections are gone, Sueldo projections rebuilt
    expect(Movement::where('description', 'Huancayo')->count())->toBe(0);
    expect(Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count())->toBeGreaterThan(0);

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
