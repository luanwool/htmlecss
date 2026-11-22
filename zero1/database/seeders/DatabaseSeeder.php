<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Base segura para produção: papéis, permissões, configurações verificadas e estrutura do CMS.
     * Dados de demonstração só são inseridos fora de produção e com ZERO1_DEMO_MODE=true
     * (ou chamando explicitamente: php artisan db:seed --class=DemoSeeder).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SiteBaseSeeder::class,
        ]);

        if (! app()->environment('production') && config('zero1.demo_mode')) {
            $this->call(DemoSeeder::class);
        }
    }
}
