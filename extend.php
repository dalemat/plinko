<?php

use Flarum\Extend;
use Acmeverse\PlinkoFortune\Api\Controller\PlinkoController;
use Acmeverse\PlinkoFortune\Console\PlinkoCommand;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less'),

    (new Extend\Routes('api'))
        ->post('/plinko/play', 'plinko.play', PlinkoController::class)
        ->get('/plinko/stats', 'plinko.stats', PlinkoController::class),

    (new Extend\Console())
        ->command(PlinkoCommand::class),
];
