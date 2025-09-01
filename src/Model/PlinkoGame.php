<?php

namespace Acmeverse\PlinkoFortune\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

class PlinkoGame extends AbstractModel
{
    protected $table = 'plinko_games';
    
    protected $fillable = ['user_id', 'bet_amount', 'slot_hit', 'multiplier', 'payout'];
    
    protected $casts = [
        'multiplier' => 'float'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
