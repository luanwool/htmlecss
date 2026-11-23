<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Agendador — acionado pelo cron da Hostinger a cada minuto:
|   * * * * * /usr/bin/php /home/USUARIO/zero1/artisan schedule:run >> /dev/null 2>&1
| Não há processos permanentes: a fila é drenada a cada execução e encerra sozinha.
|--------------------------------------------------------------------------
*/

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --backoff=30')
    ->everyMinute()->withoutOverlapping(5);

Schedule::command('auth:clear-resets')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();

Schedule::command('zero1:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('zero1:subscriptions-tick')->hourly()->withoutOverlapping();
Schedule::command('zero1:reconcile-payments')->dailyAt('04:10')->timezone(config('zero1.timezone'))->withoutOverlapping();
