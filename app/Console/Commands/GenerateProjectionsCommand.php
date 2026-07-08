<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProjectionService;
use Illuminate\Console\Command;

class GenerateProjectionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-projections
        {--horizon= : Number of months into the future (default: user preference or 12)}
        {--user= : Generate for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera movimientos proyectados para las plantillas recurrentes activas (idempotente: no duplica existentes)';

    /**
     * Execute the console command.
     */
    public function handle(ProjectionService $projectionService): int
    {
        $explicitHorizon = $this->option('horizon');
        if ($explicitHorizon !== null) {
            $explicitHorizon = (int) $explicitHorizon;
        }

        $userId = $this->option('user');
        if ($userId !== null) {
            $userId = (int) $userId;

            // When --horizon is not given, read the user's preference
            if ($explicitHorizon === null) {
                $user = User::findOrFail($userId);
                $horizonMonths = $user->settings['projection_horizon'] ?? ProjectionService::DEFAULT_HORIZON_MONTHS;
            } else {
                $horizonMonths = $explicitHorizon;
            }

            $count = $projectionService->generateForUser($userId, $horizonMonths);
            $this->info("Generated {$count} projected movements for user #{$userId} (horizon: {$horizonMonths}).");

            return self::SUCCESS;
        }

        $users = User::all();
        $total = 0;

        foreach ($users as $user) {
            // When --horizon is not given, each user uses their own preference
            $horizonMonths = $explicitHorizon !== null
                ? $explicitHorizon
                : ($user->settings['projection_horizon'] ?? ProjectionService::DEFAULT_HORIZON_MONTHS);

            $count = $projectionService->generateForUser($user->id, $horizonMonths);
            $total += $count;
            $this->info("User #{$user->id}: {$count} projected movements (horizon: {$horizonMonths}).");
        }

        $this->info("Total: {$total} projected movements generated.");

        return self::SUCCESS;
    }
}
