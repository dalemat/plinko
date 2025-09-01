<?php

namespace Acmeverse\PlinkoFortune\Console;

use Flarum\Console\AbstractCommand;
use Acmeverse\PlinkoFortune\Model\PlinkoGame;
use Acmeverse\PlinkoFortune\PlinkoService;
use Flarum\User\User;
use Flarum\Settings\SettingsRepositoryInterface;

class PlinkoCommand extends AbstractCommand
{
    protected $signature = 'plinko {action} {key?} {value?}';
    protected $description = 'Manage Plinko game';

    protected function fire()
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
        try {
            $totalGames = PlinkoGame::count();
            $totalBets = PlinkoGame::sum('bet_amount') ?: 0;
            $totalPayouts = PlinkoGame::sum('payout') ?: 0;
            $totalProfit = $totalBets - $totalPayouts;
            
            $recentGames = PlinkoGame::orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $topPlayers = PlinkoGame::selectRaw('user_id, COUNT(*) as games_count, SUM(bet_amount) as total_bets, SUM(payout) as total_payouts')
                ->groupBy('user_id')
                ->orderBy('total_bets', 'desc')
                ->limit(5)
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
            if ($recentGames->count() > 0) {
                foreach ($recentGames as $game) {
                    $username = $game->user ? $game->user->username : 'Unknown';
                    $this->line("$username: Bet {$game->bet_amount}, Won {$game->payout} (Multiplier: {$game->multiplier}x)");
                }
            } else {
                $this->line("No games played yet");
            }

            $this->info("\n=== TOP PLAYERS ===");
            if ($topPlayers->count() > 0) {
                foreach ($topPlayers as $player) {
                    $user = User::find($player->user_id);
                    $username = $user ? $user->username : 'Unknown';
                    $profit = $player->total_payouts - $player->total_bets;
                    $this->line("$username: {$player->games_count} games, Profit: $profit points");
                }
            } else {
                $this->line("No players yet");
            }

        } catch (\Exception $e) {
            $this->error("Error getting stats: " . $e->getMessage());
        }
    }

    private function showConfig()
    {
        $settings = resolve(SettingsRepositoryInterface::class);
        
        $configs = [
            'acmeverse-plinko.enabled' => 'Enabled',
            'acmeverse-plinko.max_bet' => 'Max Bet',
            'acmeverse-plinko.min_bet' => 'Min Bet',
            'acmeverse-plinko.daily_limit' => 'Daily Limit'
        ];

        $this->info("=== PLINKO CONFIGURATION ===");
        foreach ($configs as $key => $label) {
            $value = $settings->get($key, 'Not set');
            $this->line("$label: $value");
        }
    }

    private function setConfig($key, $value)
    {
        $allowedKeys = ['enabled', 'max_bet', 'min_bet', 'daily_limit'];
        
        if (!in_array($key, $allowedKeys)) {
            $this->error("Allowed keys: " . implode(', ', $allowedKeys));
            return;
        }

        $settings = resolve(SettingsRepositoryInterface::class);
        $settingKey = "acmeverse-plinko.$key";

        if ($key === 'enabled') {
            $value = in_array(strtolower($value), ['true', '1', 'yes', 'on']) ? '1' : '0';
        } else {
            $value = (string) ((int) $value);
        }

        $settings->set($settingKey, $value);
        
        $this->info("Setting updated: $key = $value");
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

        // Get current points from preferences
        $currentPoints = (int) $user->getPreference('plinko_points', 0);
        $newPoints = $currentPoints + $amount;
        
        if ($newPoints < 0) {
            $this->error("Cannot set negative points. User has $currentPoints points.");
            return;
        }

        $user->setPreference('plinko_points', $newPoints);
        $user->save();

        $action = $amount >= 0 ? 'Added' : 'Subtracted';
        $this->info("$action " . abs($amount) . " points to $username. New balance: $newPoints points");
    }
}
