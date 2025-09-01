<?php

namespace Acmeverse\PlinkoFortune\Console;

use Flarum\Console\AbstractCommand;
use Acmeverse\PlinkoFortune\Model\PlinkoGame;
use Acmeverse\PlinkoFortune\PlinkoService;
use Flarum\User\User;

class PlinkoCommand extends AbstractCommand
{
    protected $signature = 'plinko {action} {key?} {value?}';
    protected $description = 'Manage Plinko game';

    public function handle()
    {
        $action = $this->argument('action');
        $key = $this->argument('key');
        $value = $this->argument('value');

        switch ($action) {
            case 'stats':
                $this->showStats();
                break;
                
            case 'config':
                if (!$key) {
                    $this->showConfig();
                } else {
                    $this->setConfig($key, $value);
                }
                break;
                
            case 'points':
                $this->managePoints();
                break;
                
            default:
                $this->error("Available actions: stats, config, points");
        }
    }

    private function showStats()
    {
        // Fix: Use query builder instead of collection methods
        $totalGames = PlinkoGame::count();
        $totalBets = PlinkoGame::sum('bet_amount');
        $totalPayouts = PlinkoGame::sum('payout');
        $totalProfit = $totalBets - $totalPayouts; // House profit
        
        // Get recent games using query builder
        $recentGames = PlinkoGame::orderBy('created_at', 'desc')
            ->limit(5)
            ->with('user')
            ->get();

        // Get top players
        $topPlayers = PlinkoGame::selectRaw('user_id, COUNT(*) as games_count, SUM(bet_amount) as total_bets, SUM(payout) as total_payouts')
            ->groupBy('user_id')
            ->orderBy('total_bets', 'desc')
            ->limit(5)
            ->with('user')
            ->get();

        $this->info("=== PLINKO STATISTICS ===");
        $this->line("Total Games: " . number_format($totalGames));
        $this->line("Total Bets: " . number_format($totalBets) . " points");
        $this->line("Total Payouts: " . number_format($totalPayouts) . " points");
        $this->line("House Profit: " . number_format($totalProfit) . " points");
        
        if ($totalGames > 0) {
            $avgBet = $totalBets / $totalGames;
            $avgPayout = $totalPayouts / $totalGames;
            $this->line("Average Bet: " . number_format($avgBet, 2) . " points");
            $this->line("Average Payout: " . number_format($avgPayout, 2) . " points");
        }

        $this->info("\n=== RECENT GAMES ===");
        foreach ($recentGames as $game) {
            $username = $game->user ? $game->user->username : 'Unknown';
            $profit = $game->payout - $game->bet_amount;
            $profitStr = $profit >= 0 ? "+$profit" : "$profit";
            
            $this->line(sprintf(
                "%s | %s | Bet: %d | Slot: %d (%.1fx) | Payout: %d | Profit: %s",
                $game->created_at->format('Y-m-d H:i'),
                $username,
                $game->bet_amount,
                $game->slot_hit,
                $game->multiplier,
                $game->payout,
                $profitStr
            ));
        }

        $this->info("\n=== TOP PLAYERS ===");
        foreach ($topPlayers as $player) {
            $username = $player->user ? $player->user->username : 'Unknown';
            $netProfit = $player->total_payouts - $player->total_bets;
            $netStr = $netProfit >= 0 ? "+$netProfit" : "$netProfit";
            
            $this->line(sprintf(
                "%s | Games: %d | Total Bets: %d | Net: %s",
                $username,
                $player->games_count,
                $player->total_bets,
                $netStr
            ));
        }
    }

    private function showConfig()
    {
        $service = resolve(PlinkoService::class);
        
        $this->info("=== PLINKO CONFIGURATION ===");
        $this->line("Max Bet: " . $service->getSetting('max_bet', 100) . " points");
        $this->line("Daily Limit: " . $service->getSetting('daily_limit', 50) . " games");
        $this->line("Game Enabled: " . ($service->getSetting('enabled', true) ? 'Yes' : 'No'));
        
        $this->info("\nTo change settings:");
        $this->line("php flarum plinko config max_bet 200");
        $this->line("php flarum plinko config daily_limit 25");
        $this->line("php flarum plinko config enabled true");
    }

    private function setConfig($key, $value)
    {
        if (!$value) {
            $this->error("Please provide a value");
            return;
        }

        $allowedKeys = ['max_bet', 'daily_limit', 'enabled'];
        if (!in_array($key, $allowedKeys)) {
            $this->error("Allowed keys: " . implode(', ', $allowedKeys));
            return;
        }

        // Convert value to appropriate type
        if ($key === 'enabled') {
            $value = in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        } else {
            $value = (int) $value;
        }

        $service = resolve(PlinkoService::class);
        $service->setSetting($key, $value);
        
        $this->info("Setting updated: $key = " . ($value === true ? 'true' : ($value === false ? 'false' : $value)));
    }

    private function managePoints()
    {
        $username = $this->ask('Enter username:');
        $amount = (int) $this->ask('Enter points amount (positive to add, negative to subtract):');

        $user = User::where('username', $username)->first();
        if (!$user) {
            $this->error("User '$username' not found");
            return;
        }

        // Assuming you have a points system - adjust this based on your setup
        $currentPoints = $user->getPreference('points', 0);
        $newPoints = $currentPoints + $amount;
        
        if ($newPoints < 0) {
            $this->error("Cannot set negative points. User has $currentPoints points.");
            return;
        }

        $user->setPreference('points', $newPoints);
        $user->save();

        $action = $amount >= 0 ? 'Added' : 'Subtracted';
        $this->info("$action " . abs($amount) . " points to $username. New balance: $newPoints points");
    }
}
