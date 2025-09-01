<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('plinko_games', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('bet_amount');
            $table->integer('slot_hit'); // 0-8
            $table->float('multiplier'); // 0, 0.5, 1, 2, 5, 2, 1, 0.5, 0
            $table->integer('payout');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'created_at']);
        });

        $schema->create('plinko_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
        });

        // Default settings
        $schema->getConnection()->table('plinko_settings')->insert([
            ['key' => 'enabled', 'value' => '1'],
            ['key' => 'min_bet', 'value' => '1'],
            ['key' => 'max_bet', 'value' => '100'],
            ['key' => 'daily_limit', 'value' => '50']
        ]);
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('plinko_games');
        $schema->dropIfExists('plinko_settings');
    }
];
