<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Instalação/atualização em um único comando — pensado para hospedagem sem SSH,
 * onde ele é executado UMA vez por um Cron Job do hPanel. Idempotente: pode rodar de novo.
 * O resultado fica em storage/logs/install.log (visível pelo Gerenciador de Arquivos).
 *
 *   php artisan zero1:install            → produção (sem dados de demonstração)
 *   php artisan zero1:install --demo     → ambiente de TESTE com dados [DEMO] (bloqueado em produção)
 */
class Install extends Command
{
    protected $signature = 'zero1:install {--demo : popula dados de demonstração (somente fora de produção)}';

    protected $description = 'Prepara o banco, as tabelas base e os caches (idempotente)';

    public function handle(): int
    {
        $log = [];
        $say = function (string $line) use (&$log) {
            $this->line($line);
            $log[] = '['.now()->format('Y-m-d H:i:s').'] '.$line;
        };

        try {
            if (! config('app.key')) {
                $say('ERRO: APP_KEY vazia no .env. Preencha antes de instalar (veja LEIA-ME).');

                return $this->finish($log, self::FAILURE);
            }
            DB::connection()->getPdo();
            $say('Banco conectado: '.DB::connection()->getDatabaseName());

            Artisan::call('migrate', ['--force' => true]);
            $say('Migrations aplicadas.');

            Artisan::call('db:seed', ['--force' => true]);
            $say('Papéis, permissões e conteúdo base prontos.');

            if ($this->option('demo')) {
                if (app()->environment('production')) {
                    $say('AVISO: --demo ignorado porque APP_ENV=production.');
                } else {
                    Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
                    $say('Dados de demonstração criados. Acesso: dono@demo.invalid / '.DemoSeeder::PASSWORD.' (troque ou apague antes de abrir ao público).');
                }
            }

            if (! config('zero1.media.public_disk_in_public') && ! file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
                $say('Link de uploads criado.');
            }
            // Sem config:cache de propósito: o caminho da pasta pública só é conhecido na requisição web
            // (public_html), e um cache gerado pelo Cron gravaria o caminho errado dos uploads.
            Artisan::call('optimize:clear');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Artisan::call('event:cache');
            $say('Caches de rotas, telas e eventos gerados.');
            $say('OK — instalação concluída. Remova o Cron Job de instalação.');

            return $this->finish($log, self::SUCCESS);
        } catch (Throwable $e) {
            $say('ERRO: '.$e->getMessage());

            return $this->finish($log, self::FAILURE);
        }
    }

    private function finish(array $log, int $code): int
    {
        @file_put_contents(storage_path('logs/install.log'), implode(PHP_EOL, $log).PHP_EOL.PHP_EOL, FILE_APPEND);

        return $code;
    }
}
