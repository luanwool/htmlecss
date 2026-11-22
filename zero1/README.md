# Zero 1 Barbearia

Site, clube de assinaturas, agenda e gestão da **Zero 1 Barbearia** (Campo Grande, Rio de Janeiro).
Produto exclusivo de uma barbearia, preparado para **hospedagem de sites da Hostinger (sem VPS)**.

- Stack: PHP 8.2+ · Laravel 12 · MySQL/MariaDB · Blade · Tailwind CSS v4 (compilado no build) · JS sem framework
- Arquitetura e decisões: [`docs/ARQUITETURA.md`](docs/ARQUITETURA.md)
- Implantação na Hostinger: [`docs/DEPLOY_HOSTINGER.md`](docs/DEPLOY_HOSTINGER.md)
- Informações reais pendentes do proprietário: [`docs/PENDENCIAS_PROPRIETARIO.md`](docs/PENDENCIAS_PROPRIETARIO.md)

## Fases

| Fase | Escopo | Estado |
|---|---|---|
| 1 | Base Laravel/MySQL, autenticação e papéis, CMS, landing premium, SEO local, painel (serviços, planos, profissionais, conteúdo, configurações, equipe, LGPD, auditoria) | **Entregue** |
| 2 | Escalas, bloqueios, motor de disponibilidade com trava por barbeiro/dia, agendamento do cliente, agenda Hoje/3/7/15/mês, `.ics` e Google Agenda | Próxima |
| 3 | Clube: ciclos, ledger de franquia, Mercado Pago (cartão recorrente, Pix conforme suporte real), webhooks idempotentes, conciliação | — |
| 4 | Atendimentos de balcão, comissões, financeiro, dashboards e exportações | — |
| 5 | Homologação e deploy | Roteiro já documentado |

## Desenvolvimento local

Requisitos: PHP 8.2+ (ext. pdo_mysql, gd, intl/bcmath, mbstring), Composer 2, Node 20+, MySQL/MariaDB (ou SQLite).

```bash
composer install
npm install
cp .env.example .env          # ajuste DB_* ; para usar dados de demonstração: ZERO1_DEMO_MODE=true
php artisan key:generate
php artisan migrate --seed    # base; com ZERO1_DEMO_MODE=true inclui dados [DEMO]
php artisan storage:link
npm run build                 # ou: npm run dev
php artisan serve
```

Acesso de demonstração (somente com `ZERO1_DEMO_MODE=true`, fora de produção), senha `demo12345`:
`dono@demo.invalid` · `gestor@demo.invalid` · `barbeiro.x@demo.invalid` · `cliente@demo.invalid`.

Para criar um proprietário real: `php artisan zero1:create-owner`.

## Testes

```bash
php artisan test                                   # SQLite em memória
DB_CONNECTION=mysql DB_DATABASE=zero1_test \
DB_USERNAME=... DB_PASSWORD=... php artisan test   # mesma suíte no MySQL/MariaDB
```

## Rotas principais

| Área | URL |
|---|---|
| Site | `/` · `/p/{pagina}` · `/sitemap.xml` · `/robots.txt` · `/up` (saúde) |
| Conta | `/entrar` · `/cadastro` · `/esqueci-a-senha` · `/minha-conta` |
| Barbeiro | `/barbeiro` |
| Painel | `/painel` |
