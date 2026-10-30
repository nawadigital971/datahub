<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:purge-exports')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('app:purge-imports')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
