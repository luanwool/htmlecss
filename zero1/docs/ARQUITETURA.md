# Arquitetura — Zero 1 Barbearia

## 1. Restrições que guiaram as decisões

| Restrição (briefing) | Consequência técnica |
|---|---|
| Hospedagem de **sites** da Hostinger, **sem VPS** | PHP gerenciado (Apache/LiteSpeed), MySQL/MariaDB, cron do hPanel. Sem Docker, Redis, WebSockets, Node permanente ou workers sempre ativos. |
| Páginas SSR, assets compilados no build | Laravel + Blade renderizados no servidor; Vite/Tailwind compilados antes do upload (`deploy/build-release.sh`). Node **não** é necessário no servidor. |
| Sem processos em background | Fila em banco (`QUEUE_CONNECTION=database`) drenada pelo `schedule:run` a cada minuto com `queue:work --stop-when-empty --max-time=50`. |
| Segurança / LGPD | Senhas com bcrypt, CSRF, CSP com nonce, rate limit, sessões criptografadas e cookies seguros em produção, auditoria, coleta mínima, exportação/solicitação de dados pelo titular. |
| Dados reais não podem ser inventados | Seed de produção só contém o que o briefing verificou (nome, endereço, telefone, horário público **marcado como "a confirmar"**). Catálogo, planos, FAQ e termos nascem vazios ou como rascunho. Dados `[DEMO]` vivem em `DemoSeeder`, bloqueado em produção. |

## 2. Stack

- **PHP 8.2+** (testado em 8.3) e **Laravel 12** — Laravel 12 é a versão atual que ainda aceita PHP 8.2, o que dá margem caso o plano não ofereça 8.3/8.4. Confirme a versão no hPanel antes do primeiro deploy.
- **MySQL 8 / MariaDB 10.6+** em produção. A suíte de testes roda em SQLite (rápido) **e** em MariaDB (compatibilidade); ambas passam.
- **Blade + JavaScript sem framework** (≈4 KB). Não usamos Alpine/Livewire: o JS próprio é compatível com CSP estrita (sem `eval`), não exige build no servidor e cobre o que o site precisa (menu, revelação com `prefers-reduced-motion`, consentimento de cookies, confirmações, campos condicionais).
- **Tailwind CSS v4** via Vite. Fontes **auto-hospedadas** (Bodoni Moda + Archivo, via `@fontsource`) — sem Google Fonts, por desempenho e LGPD.
- **spatie/laravel-permission** para papéis/permissões (única dependência extra de produção).
- **GD** (disponível na Hostinger) para converter uploads em WebP responsivo (480/960/1600 px) e remover EXIF/geolocalização.

## 3. Organização do código

```
app/
  Enums/            Role, Permission, BarberStatus, PlanUsageMode
  Http/Controllers/
    Public/         Home, páginas legais, sitemap, robots, entradas de CTA
    Auth/           login (anti força bruta), cadastro, recuperação, verificação de e-mail
    Account/        área do cliente (perfil, senha, LGPD)
    Barber/         painel do barbeiro (só a própria área)
    Admin/          painel gerencial (catálogo, planos, CMS, configurações, equipe, LGPD, auditoria)
  Http/Middleware/  SecurityHeaders (CSP/HSTS/etc.), EnsureUserIsActive
  Models/
  Services/
    Audit/AuditLogger         trilha de auditoria com redação de campos sensíveis
    Media/MediaUploader       upload → WebP responsivo
    Seo/LocalBusinessSchema   JSON-LD schema.org/BarberShop somente com dados cadastrados
  Support/          SiteSettings (chave/valor com cache), BusinessTime (fuso), Money (centavos/BRL)
database/
  migrations/       schema da Fase 1 (ver §5)
  seeders/          RolesAndPermissions, SiteBase (produção), DemoSeeder (dev)
resources/views/
  components/       layouts (public, auth, account, admin), form/*, icon, wordmark
  public/sections/  uma partial por seção da landing (ordem/visibilidade vêm do CMS)
  admin/ account/ auth/ barber/
deploy/             empacotamento, index alternativo para public_html, cron
docs/               este arquivo, deploy e pendências
```

## 4. Papéis e autorização

| Papel | Acesso |
|---|---|
| `owner` | Tudo (`Gate::before`). Único que gerencia equipe/cargos, integrações e vê auditoria por padrão. Sistema impede remover o último proprietário ativo. |
| `manager` | Painel `/painel` conforme permissões granulares (`App\Enums\Permission`), editáveis pelo proprietário em **Equipe e acessos → Permissões do gestor**. |
| `barber` | Apenas `/barbeiro` (própria agenda e lançamentos nas Fases 2/4). |
| `customer` | Apenas `/minha-conta`. |

A autorização é aplicada **no backend** em cada grupo de rotas (`role:` e `permission:`), não só escondendo menus. Usuários desativados têm a sessão encerrada no próximo request.

## 5. Modelo de dados (Fase 1)

- `users` (soft delete, `is_active`, último login) · `customers` (pode existir sem login — cadastro de balcão; ao se registrar com o mesmo e-mail, o cadastro é vinculado, não duplicado)
- `barbers` (status ativo/afastado/desativado, visível no site, participa de "qualquer disponível") · `barber_service`
- `service_categories` · `services` (preço em centavos ou "sob consulta", "a partir de", duração, **intervalo de preparação**, antecedência mínima, visível, vendável avulso, elegível a plano, ativo, ordem; soft delete)
- `membership_plans` (preço, modo de franquia global/por serviço/ilimitado, limites por dia/semana/futuros, intervalo entre usos, janela, dias/horários permitidos, permanência, carência, políticas, termos, **`version`**) · `plan_service_rules` (franquia por serviço) · `barber_membership_plan`
- CMS: `site_settings` (chave/valor), `page_sections`, `pages` (Markdown seguro), `media_assets`, `reviews` (publicação exige autorização registrada), `faqs`, `opening_hours`
- `audit_logs` · `data_requests` (LGPD) · tabelas do `spatie/laravel-permission`

**Versionamento de planos:** qualquer mudança em preço, franquia, limites, políticas, termos ou serviços cobertos incrementa `version`. Na Fase 3 a assinatura guardará um *snapshot* das condições vigentes na adesão; alterações posteriores não mudam silenciosamente o que foi vendido.

**Dinheiro** sempre em centavos (`int`). **Datas** em UTC no banco (`app.timezone = UTC`) e exibidas/calculadas em `America/Sao_Paulo` (`config/zero1.php`, `App\Support\BusinessTime`).

## 6. Agenda, clube e operação (implementados)

- **Um único motor** (`BookingService` + `AvailabilityService`) atende site, painel, barbeiro e balcão.
- Disponibilidade por profissional e intervalo contínuo `[início, fim)`; duração efetiva = serviço + intervalo operacional; o atendimento precisa caber inteiro numa janela da escala; folgas, feriados, férias, bloqueios e almoço (lacuna entre períodos) removem horários.
- **Concorrência:** a linha `barber_day_locks (barbeiro, dia)` é criada fora da transação (autocommit) e travada com `SELECT … FOR UPDATE` dentro dela, em ordem fixa; a checagem de conflito acontece **depois** da trava; deadlocks repetem a transação (3 tentativas). Testado com processos paralelos no MariaDB.
- **Clube:** snapshot das condições na adesão; ciclos `[início, fim)` em datas locais com `addMonthNoOverflow` (31/01 → 28/02); benefício só com ciclo **pago** cobrindo a data; ledger `reserve/release/consume/forfeit` com unique por agendamento+tipo; webhook → registra evento (unique) → **consulta a API** → aplica estado (idempotente, tolera fora de ordem); conciliação recupera webhooks perdidos.
- **Operação:** `service_visits` com `appointment_id` único (agendado + concluído = um atendimento); balcão passa pelo motor de conflitos (ocupa a agenda); financeiro separa mensalidade (caixa/recorrente) de corte de plano (produção, sem receita nova); comissões por regra mais específica.

## 7. Decisões originais para as fases (referência)

- **Agenda (Fase 2):** intervalos semiabertos `[início, fim)`; conflito = `novo_início < fim_existente AND novo_fim > início_existente` para o mesmo barbeiro; reserva dentro de transação com `SELECT … FOR UPDATE` em `barber_day_locks (barber_id, date)`; "qualquer disponível" escolhe e trava dentro da mesma transação. Um único motor (`AvailabilityService`/`BookingService`) atende site, painel, barbeiro e balcão.
- **Clube + Mercado Pago (Fase 3):** `PaymentGatewayInterface` com adaptador `MercadoPagoGateway`; webhooks idempotentes (`payment_webhook_events` com unique no ID externo), confirmação sempre consultando a API, `billing_cycles` + `subscription_entitlement_ledger` (débitos/estornos, nunca contador sobrescrito). **Benefício só é liberado quando o ciclo que cobre a data do atendimento estiver pago.** Pix Automático só será habilitado se a API e a conta comprovarem suporte; caso contrário, Pix por cobrança mensal paga manualmente.
- **Operação (Fase 4):** `service_visits` como registro único que liga agendamento → execução → consumo de franquia → financeiro, evitando lançamento duplicado.

## 8. Segurança implementada

- CSP com nonce por request (sem `unsafe-eval`), `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS sob HTTPS.
- Login: 5 tentativas por e-mail+IP e 20 por IP a cada 15 min; resposta neutra na recuperação de senha; regeneração de sessão.
- Cadastro de equipe sem senha definida pelo proprietário: o usuário recebe link para criar a própria.
- Markdown das páginas legais renderizado com `html_input=strip` e links inseguros bloqueados; demais conteúdos escapados pelo Blade.
- Uploads: somente JPG/PNG/WebP, limite de tamanho, reprocessados (descarta EXIF), confirmação de autoria/autorização para galeria.
- Scripts de marketing (GA4/Meta Pixel) só carregam após consentimento explícito.
- `.env` e código fora de `public_html` (ver deploy).
