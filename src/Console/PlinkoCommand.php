<?php

namespace Acmeverse\PlinkoFortune\Console;

use Acmeverse\PlinkoFortune\PlinkoService;
use Illuminate\Console\Command;
use Flarum\User\User;

class PlinkoCommand extends Command
{
    protected $signature = 'plinko {action} {key?} {value?}';
    protected $description = 'Manage Plinko game';
    
    protected $service;

    public function __construct(PlinkoService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'stats':
                $this->showStats();
                break;
            case 'config':
                $this->updateConfig();
                break;
            case 'points':
                $this->managePoints();
                break;
            default:
                $this->error('Available actions: stats, config, points');
        }
    }

    private function showStats()
    {
        $stats = $this->service->getStats();
        $this->info('=== PLINKO STATS ===');
        $this->line("Total Games: {$stats['total_games']}");
        $this->line("Total Wagered: {$stats['total_wagered']} points");
        $this->line("Total Payout: {$stats['total_payout']} points");
        $this->line("House Profit: {$stats['house_profit']} points");
        $this->line("Win Rate: {$stats['win_rate']}%");
        $this->line("Biggest Win: {$stats['biggest_win']} points");
    }

    private function updateConfig()
    {
        $key = $this->argument('key');
        $value = $this->argument('value');
        
        if (!$key || !$value) {
            $this->error('Usage: php flarum plinko config <key> <value>');
            $this->line('Keys: enabled, min_bet, max_bet, daily_limit');
            return;
        }

        $this->service->updateSetting($key, $value);
        $this->info("Updated {$key} to {$value}");
    }

    private function managePoints()
    {
        $username = $this->ask('Username?');
        $amount = (int)$this->ask('Points to add?');
        
        $user = User::where('username', $username)->first();
        if (!$user) {
            $this->error('User not found');
            return;
        }

        $user->commentCount += $amount;
        $user->save();
        
        $this->info("Added {$amount} points to {$username}. New balance: {$user->commentCount}");
    }
}
