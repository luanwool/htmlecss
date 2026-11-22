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
| 2 | Escalas, folgas, bloqueios, motor de disponibilidade com trava por barbeiro/dia, agendamento do cliente (específico ou “qualquer”), agenda Hoje/3/7/15/mês (lista e colunas), reagendamento/cancelamento, `.ics` e Google Agenda, e-mails e lembretes | **Entregue** |
| 3 | Clube: adesão com termos, snapshot das condições, ciclos mensais, ledger de franquia, bloqueio de inadimplentes, cancelamento no fim do período, webhooks idempotentes, conciliação, estorno/chargeback | **Entregue com pagamento SIMULADO** |
| 4 | Atendimentos (agendados e de balcão) sem duplicidade, comissões, financeiro (caixa × recorrente), dashboard, relatórios e CSV | **Entregue** |
| Próx. | Adaptador Mercado Pago real ([guia](docs/MERCADO_PAGO_PROXIMA_ETAPA.md)) + deploy/homologação | — |

> **Pagamentos simulados:** nenhuma cobrança real acontece. O simulador imita o checkout do provedor (aprovar, recusar,
> Pix pendente, perder webhook) e o painel de cada assinatura simula renovação, estorno e chargeback. Ele é bloqueado em
> produção, salvo `ALLOW_SIMULATED_PAYMENTS=true` para homologação.

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
`dono@demo.invalid` · `gestor@demo.invalid` · `barbeiro.x@demo.invalid` · `barbeiro.y@demo.invalid` ·
`cliente@demo.invalid` (sem plano — teste a assinatura) · `assinante@demo.invalid` (plano com 1º mês pago no simulador).

### Roteiro rápido de teste no navegador
1. `cliente@` → **Agendar** → serviço, barbeiro ou “qualquer”, data, horário → confirmar → baixe o `.ics`/Google Agenda.
2. `cliente@` → Clube → **Assinar agora** → simulador → *Aprovar* (ou *Recusar*, ou marque “perder webhook”).
3. `assinante@` → agende pelo plano; veja saldo em **Minha conta → Assinatura**.
4. `gestor@` → **Agenda** (Hoje/3/7/15/Mês, lista e “por profissional”), **Novo agendamento** por telefone, **Escalas**, **Assinaturas** (simular renovação/estorno/chargeback, conciliar), **Financeiro**, **Relatórios**.
5. `barbeiro.x@` (celular) → agenda própria, *Concluir* / *Não compareceu*, **Registrar atendimento** de balcão.

Para criar um proprietário real: `php artisan zero1:create-owner`.

## Testes

```bash
php artisan test                                   # SQLite em memória
DB_CONNECTION=mysql DB_DATABASE=zero1_test \
DB_USERNAME=... DB_PASSWORD=... php artisan test   # mesma suíte no MySQL/MariaDB (inclui concorrência real)
```

A suíte cobre os 18 critérios de aceitação do briefing. O grupo `concurrency` dispara processos paralelos (pcntl)
disputando o mesmo horário no MySQL e só roda nesse banco.

## Rotas principais

| Área | URL |
|---|---|
| Site | `/` · `/p/{pagina}` · `/sitemap.xml` · `/robots.txt` · `/up` (saúde) |
| Agendar | `/agendar` · `/agendamento/{uid}` (ver, `.ics`, reagendar, cancelar) |
| Clube | `/clube/{plano}/assinar` · `/assinatura/retorno` · `/simulador/checkout/{id}` · `POST /webhooks/pagamentos/{gateway}` |
| Conta | `/entrar` · `/cadastro` · `/esqueci-a-senha` · `/minha-conta` (agendamentos, assinatura, dados, privacidade) |
| Barbeiro | `/barbeiro` |
| Painel | `/painel` |
