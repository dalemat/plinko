<?php

namespace Acmeverse\PlinkoFortune\Console;

use Flarum\Console\AbstractCommand;
use Acmeverse\PlinkoFortune\Model\PlinkoGame;
use Flarum\User\User;
use Flarum\Settings\SettingsRepositoryInterface;

class PlinkoCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('plinko')
            ->setDescription('Manage Plinko game')
            ->addArgument('action', null, 'Action to perform (stats, config, points)')
            ->addArgument('key', null, 'Configuration key or username')
            ->addArgument('value', null, 'Configuration value or points amount');
    }

    protected function fire()
    {
        $action = $this->input->getArgument('action');
        $key = $this->input->getArgument('key');
        $value = $this->input->getArgument('value');

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
                $this->managePoints($key, $value);
                break;
                
            default:
                $this->output->writeln("<error>Available actions: stats, config, points</error>");
                $this->output->writeln("<info>Examples:</info>");
                $this->output->writeln("  php flarum plinko stats");
                $this->output->writeln("  php flarum plinko config");
                $this->output->writeln("  php flarum plinko config max_bet 100");
                $this->output->writeln("  php flarum plinko points username 1000");
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

            $this->output->writeln("<info>=== PLINKO STATISTICS ===</info>");
            $this->output->writeln("Total Games: " . number_format($totalGames));
            $this->output->writeln("Total Bets: " . number_format($totalBets) . " points");
            $this->output->writeln("Total Payouts: " . number_format($totalPayouts) . " points");
            $this->output->writeln("House Profit: " . number_format($totalProfit) . " points");
            
            if ($totalGames > 0) {
                $avgBet = $totalBets / $totalGames;
                $avgPayout = $totalPayouts / $totalGames;
                $this->output->writeln("Average Bet: " . number_format($avgBet, 2) . " points");
                $this->output->writeln("Average Payout: " . number_format($avgPayout, 2) . " points");
            }

            $this->output->writeln("\n<info>=== RECENT GAMES ===</info>");
            foreach ($recentGames as $game) {
                $user = User::find($game->user_id);
                $username = $user ? $user->username : "User#{$game->user_id}";
                $this->output->writeln("$username: Bet {$game->bet_amount}, Won {$game->payout}, Slot {$game->slot_hit}");
            }

            $this->output->writeln("\n<info>=== TOP PLAYERS ===</info>");
            foreach ($topPlayers as $player) {
                $user = User::find($player->user_id);
                $username = $user ? $user->username : "User#{$player->user_id}";
                $profit = $player->total_payouts - $player->total_bets;
                $this->output->writeln("$username: {$player->games_count} games, Profit: $profit points");
            }

        } catch (\Exception $e) {
            $this->output->writeln("<error>Error getting stats: " . $e->getMessage() . "</error>");
        }
    }

    private function showConfig()
    {
        $settings = resolve(SettingsRepositoryInterface::class);
        
        $this->output->writeln("<info>=== PLINKO CONFIGURATION ===</info>");
        
        $configs = [
            'enabled' => 'Game Enabled',
            'max_bet' => 'Maximum Bet',
            'min_bet' => 'Minimum Bet', 
            'daily_limit' => 'Daily Game Limit'
        ];

        foreach ($configs as $key => $label) {
            $value = $settings->get("acmeverse-plinko.$key", 'Not set');
            $this->output->writeln("$label: $value");
        }
    }

    private function setConfig($key, $value)
    {
        $allowedKeys = ['enabled', 'max_bet', 'min_bet', 'daily_limit'];
        
        if (!in_array($key, $allowedKeys)) {
            $this->output->writeln("<error>Allowed keys: " . implode(', ', $allowedKeys) . "</error>");
            return;
        }

        if ($value === null) {
            $this->output->writeln("<error>Value is required for config changes</error>");
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
        
        $this->output->writeln("<info>Setting updated: $key = $value</info>");
    }

    private function managePoints($username, $amount)
    {
        if (!$username || $amount === null) {
            $this->output->writeln("<error>Usage: php flarum plinko points <username> <amount></error>");
            return;
        }

        $amount = (int) $amount;
        $user = User::where('username', $username)->first();
        
        if (!$user) {
            $this->output->writeln("<error>User '$username' not found</error>");
            return;
        }

        // Get current points from preferences
        $currentPoints = (int) $user->getPreference('plinko_points', 0);
        $newPoints = $currentPoints + $amount;
        
        if ($newPoints < 0) {
            $this->output->writeln("<error>Cannot set negative points. User has $currentPoints points.</error>");
            return;
        }

        $user->setPreference('plinko_points', $newPoints);
        $user->save();

        $action = $amount >= 0 ? 'Added' : 'Subtracted';
        $this->output->writeln("<info>$action " . abs($amount) . " points to $username. New balance: $newPoints points</info>");
    }
}
