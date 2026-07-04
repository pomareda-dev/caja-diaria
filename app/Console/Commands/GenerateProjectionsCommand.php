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
        {--horizon= : Number of months into the future (default: 12)}
        {--user= : Regenerate for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate projected movements from all active recurring templates';

    /**
     * Execute the console command.
     */
    public function handle(ProjectionService $projectionService): int
    {
        $horizonMonths = $this->option('horizon');
        if ($horizonMonths !== null) {
            $horizonMonths = (int) $horizonMonths;
        }

        $userId = $this->option('user');
        if ($userId !== null) {
            $userId = (int) $userId;
            $count = $projectionService->regenerateForUser($userId, $horizonMonths);
            $this->info("Generated {$count} projected movements for user #{$userId}.");

            return self::SUCCESS;
        }

        $users = User::all();
        $total = 0;

        foreach ($users as $user) {
            $count = $projectionService->regenerateForUser($user->id, $horizonMonths);
            $total += $count;
            $this->info("User #{$user->id}: {$count} projected movements.");
        }

        $this->info("Total: {$total} projected movements generated.");

        return self::SUCCESS;
    }
}
