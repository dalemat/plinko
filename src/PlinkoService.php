<?php

namespace Acmeverse\PlinkoFortune;

use Acmeverse\PlinkoFortune\Model\PlinkoGame;
use Flarum\User\User;
use Illuminate\Support\Facades\DB;

class PlinkoService
{
    // Slot multipliers: 0x, 0.5x, 1x, 2x, 5x, 2x, 1x, 0.5x, 0x
    private $multipliers = [0, 0.5, 1, 2, 5, 2, 1, 0.5, 0];

    public function playGame(User $user, int $betAmount, int $dropPosition): array
    {
        // Validate bet
        $this->validateBet($user, $betAmount);

        // Simulate physics (simplified)
        $finalSlot = $this->simulatePhysics($dropPosition);
        $multiplier = $this->multipliers[$finalSlot];
        $payout = (int)($betAmount * $multiplier);

        // Update user points
        $newBalance = $user->commentCount - $betAmount + $payout; // Using commentCount as points
        $user->commentCount = $newBalance;
        $user->save();

        // Save game record
        $game = PlinkoGame::create([
            'user_id' => $user->id,
            'bet_amount' => $betAmount,
            'slot_hit' => $finalSlot,
            'multiplier' => $multiplier,
            'payout' => $payout
        ]);

        return [
            'game_id' => $game->id,
            'slot_hit' => $finalSlot,
            'multiplier' => $multiplier,
            'payout' => $payout,
            'profit' => $payout - $betAmount,
            'new_balance' => $newBalance,
            'ball_path' => $this->generateBallPath($dropPosition, $finalSlot)
        ];
    }

    private function validateBet(User $user, int $betAmount): void
    {
        $settings = $this->getSettings();
        
        if (!$settings['enabled']) {
            throw new \Exception('Plinko is currently disabled');
        }

        if ($betAmount < $settings['min_bet'] || $betAmount > $settings['max_bet']) {
            throw new \Exception("Bet must be between {$settings['min_bet']} and {$settings['max_bet']} points");
        }

        if ($user->commentCount < $betAmount) {
            throw new \Exception('Insufficient points');
        }

        // Check daily limit
        $todayGames = PlinkoGame::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        if ($todayGames >= $settings['daily_limit']) {
            throw new \Exception('Daily game limit reached');
        }
    }

    private function simulatePhysics(int $dropPosition): int
    {
        // Simple physics: ball bounces left/right randomly at each peg level
        $position = $dropPosition; // Start position (0-8)
        
        // 5 levels of pegs
        for ($level = 0; $level < 5; $level++) {
            // 50/50 chance to go left or right, with slight bias toward center
            if (rand(1, 100) <= 50) {
                $position = max(0, $position - 1);
            } else {
                $position = min(8, $position + 1);
            }
        }
        
        return $position;
    }

    private function generateBallPath(int $start, int $end): array
    {
        $path = [$start];
        $current = $start;
        
        // Generate path from start to end
        for ($i = 0; $i < 5; $i++) {
            if ($current < $end) {
                $current = min(8, $current + (rand(0, 1) ? 1 : 0));
            } elseif ($current > $end) {
                $current = max(0, $current - (rand(0, 1) ? 1 : 0));
            }
            $path[] = $current;
        }
        
        $path[5] = $end; // Ensure we end at the right slot
        return $path;
    }

    public function getStats(User $user = null): array
    {
        $query = PlinkoGame::query();
        if ($user) {
            $query->where('user_id', $user->id);
        }

        $games = $query->get();
        $totalGames = $games->count();
        $totalWagered = $games->sum('bet_amount');
        $totalPayout = $games->sum('payout');

        return [
            'total_games' => $totalGames,
            'total_wagered' => $totalWagered,
            'total_payout' => $totalPayout,
            'house_profit' => $totalWagered - $totalPayout,
            'win_rate' => $totalGames > 0 ? round(($games->where('payout', '>', 0)->count() / $totalGames) * 100, 1) : 0,
            'biggest_win' => $games->max('payout'),
            'recent_games' => $games->latest()->take(10)->values()
        ];
    }

    public function getSettings(): array
    {
        $settings = DB::table('plinko_settings')->pluck('value', 'key')->toArray();
        return [
            'enabled' => (bool)($settings['enabled'] ?? true),
            'min_bet' => (int)($settings['min_bet'] ?? 1),
            'max_bet' => (int)($settings['max_bet'] ?? 100),
            'daily_limit' => (int)($settings['daily_limit'] ?? 50)
        ];
    }

    public function updateSetting(string $key, $value): void
    {
        DB::table('plinko_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
