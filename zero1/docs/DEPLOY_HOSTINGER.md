# Implantação na Hostinger (hospedagem de sites, sem VPS)

> Este roteiro não assume recursos que nem todo plano tem. **Antes de começar, confira no hPanel** e anote:
>
> | Item | Onde ver | Necessário |
> |---|---|---|
> | Plano exato | hPanel → Hospedagem | Premium/Business/Cloud de *sites* |
> | Versão do PHP | Avançado → Configuração PHP | **8.2 ou superior** |
> | Extensões PHP | Configuração PHP → Extensões | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` ou `intl`, `fileinfo`, `gd` (com WebP), `curl`, `zip` |
> | MySQL/MariaDB | Bancos de dados → MySQL | 1 banco + 1 usuário |
> | Cron Jobs | Avançado → Cron Jobs | execução a cada minuto (ou a menor disponível) |
> | SSH e Composer | Avançado → Acesso SSH | **opcional** — há caminho sem SSH |
> | SSL | Segurança → SSL | certificado ativo + "Forçar HTTPS" |
>
> Node.js **não** é necessário no servidor: os assets são compilados antes do upload.

## 1. Estrutura de diretórios segura

O código, o `.env`, `storage/` e `vendor/` **ficam fora** da pasta pública:

```
/home/SEU_USUARIO/
├── zero1/                    ← aplicação (NÃO pública)
│   ├── .env                  ← segredos (permissão 600)
│   ├── app/ bootstrap/ config/ database/ resources/ routes/ storage/ vendor/
│   └── public/               ← única pasta que deve ser servida
└── domains/SEU_DOMINIO/public_html  →  aponta para zero1/public
```

**Opção A — symlink (recomendada, exige SSH):**
```bash
cd ~/domains/SEU_DOMINIO
mv public_html public_html_antigo
ln -s ~/zero1/public public_html
```

**Opção B — sem SSH/symlink:** copie o conteúdo de `zero1/public/` para `public_html/` e substitua `public_html/index.php` por `deploy/public_html-index.php` (ajuste `$app_path` se a pasta não for `../zero1`). Nesse caso, uploads ficam em `zero1/storage/app/public`; crie o link `public_html/storage → ~/zero1/storage/app/public` pelo Gerenciador de Arquivos (se não for possível, defina `MEDIA_DISK` para um disco que grave em `public_html/storage` — ver `config/filesystems.php`).

Nunca coloque `.env`, backups, `storage/logs` ou dumps SQL dentro de `public_html`.

## 1.1 Caminho rápido (teste, sem SSH)

```bash
./deploy/build-hostinger.sh --teste   # → release/zero1-hostinger.zip
```

O zip já vem no formato do hPanel (`public_html/` + `zero1/`), com `.env` de homologação (APP_KEY gerada,
dados demo, pagamentos simulados, uploads direto em `public_html/storage`) e um `LEIA-ME.txt` passo a passo.
A instalação é feita por um Cron Job único: `php artisan zero1:install --demo` (idempotente; resultado em
`zero1/storage/logs/install.log`). Sem `--teste`, o pacote sai sem `.env`, para a instalação de produção.

## 2. Gerar o pacote (na sua máquina ou CI)

Requisitos locais: PHP 8.2+, Composer 2, Node 20+.

```bash
./deploy/build-release.sh
# → release/zero1-AAAAMMDD-HHMM.zip (com vendor/ de produção e public/build/)
```

## 3. Primeira instalação

1. **Banco:** hPanel → Bancos de dados → crie banco e usuário (charset `utf8mb4`). Anote host (geralmente `localhost`), nome, usuário e senha.
2. **Upload:** envie o zip para `~/` pelo Gerenciador de Arquivos e extraia renomeando a pasta para `zero1`.
3. **`.env`:** copie `.env.example` para `.env` e preencha, no mínimo:
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://SEU_DOMINIO
   APP_KEY=            # gere com: php artisan key:generate  (ou localmente com --show e cole aqui)
   ZERO1_DEMO_MODE=false
   DB_HOST=localhost
   DB_DATABASE=...
   DB_USERNAME=...
   DB_PASSWORD=...
   SESSION_ENCRYPT=true
   SESSION_SECURE_COOKIE=true
   LOG_LEVEL=warning
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtps
   MAIL_HOST=smtp.hostinger.com
   MAIL_PORT=465
   MAIL_USERNAME=nao-responda@SEU_DOMINIO
   MAIL_PASSWORD=...
   MAIL_FROM_ADDRESS=nao-responda@SEU_DOMINIO
   ```
   Permissões: `chmod 600 ~/zero1/.env`; `storage/` e `bootstrap/cache/` graváveis (`775`).
4. **Migrations e base** (via SSH):
   ```bash
   cd ~/zero1
   php artisan migrate --force
   php artisan db:seed --force          # papéis, permissões e CMS base — SEM dados demo
   php artisan zero1:create-owner       # cria o proprietário (senha digitada por você)
   php artisan storage:link
   php artisan optimize                 # cache de config, rotas, views e eventos
   ```
   **Sem SSH:** `migrate`, `db:seed` e `storage:link` podem ser executados por um Cron Job temporário (ex.: `cd ~/zero1 && php artisan migrate --force && php artisan db:seed --force`), removido logo após rodar uma vez. Já o `zero1:create-owner` pede a senha de forma interativa de propósito (nada de senha em linha de comando ou em seed) — ative o acesso SSH do plano para essa etapa.
5. **Cron:** hPanel → Cron Jobs → a cada minuto:
   ```
   /usr/bin/php /home/SEU_USUARIO/zero1/artisan schedule:run >> /dev/null 2>&1
   ```
   (use o caminho do binário PHP mostrado no hPanel para a versão escolhida). Esse único cron executa:
   - fila de e-mails (confirmações, lembretes, avisos de cobrança) — a cada minuto;
   - `zero1:send-reminders` (lembretes 24h/2h) — a cada 5 min;
   - `zero1:subscriptions-tick` (renovações, atrasos, encerramentos) — de hora em hora;
   - `zero1:reconcile-payments` (conciliação com o gateway) — diariamente às 04:10.
6. **SSL/HTTPS:** ative o SSL e "Forçar HTTPS". A aplicação já força `https` em produção e envia HSTS.
7. **Verificação:** `https://SEU_DOMINIO/up` deve responder 200; abra `/entrar`, entre como proprietário e siga a lista **"Informações reais pendentes"** do painel.

## 4. Publicar uma nova versão

```bash
# local
./deploy/build-release.sh
# servidor (SSH)
cd ~/zero1 && php artisan down --retry=60
# substitua os arquivos (exceto .env e storage/app, storage/logs)
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan up
```
Faça **backup do banco** antes de cada migração (hPanel → Backups, ou `mysqldump`).

## 5. Modo manutenção, logs e saúde

- `php artisan down` / `php artisan up` (o arquivo de manutenção fica em `storage/framework`).
- Logs diários em `storage/logs` (fora da pasta pública), retidos por 14 dias (`LOG_DAILY_DAYS`).
- Health check: `GET /up`.

## 6. Backups e restauração

- Ative os backups automáticos do plano (diários/semanais conforme o plano).
- Adicionalmente, exporte o banco semanalmente pelo phpMyAdmin e guarde fora da Hostinger.
- **Teste de restauração** mensal: importe o dump em um banco de homologação e rode `php artisan migrate:status`.
- Mídias enviadas ficam em `zero1/storage/app/public/media` — inclua essa pasta no backup de arquivos.

## 7. Pagamentos simulados x reais

Esta versão usa `PAYMENT_GATEWAY=simulated`. Em produção o simulador **recusa-se a rodar**; para uma homologação pública
use `ALLOW_SIMULATED_PAYMENTS=true` e deixe claro aos testadores que nenhuma cobrança é real. Não venda planos reais
antes do adaptador do Mercado Pago (`docs/MERCADO_PAGO_PROXIMA_ETAPA.md`).

## 7.1 Webhook do Mercado Pago (próxima etapa)

Exigirá URL **HTTPS** pública (`https://SEU_DOMINIO/webhooks/mercadopago`), segredo do webhook no `.env` (`MERCADOPAGO_WEBHOOK_SECRET`) e credenciais de **teste** em homologação. Nada disso deve ser enviado por mensagem ou commitado.

## 8. Checklist de homologação (Fase 1)

- [ ] `/up` = 200 e HTTPS forçado
- [ ] Página inicial sem `[DEMO]` e sem selo de demonstração
- [ ] `robots.txt` liberando indexação somente após aprovação (Configurações → SEO)
- [ ] Endereço, telefone, WhatsApp e horário conferidos pelos donos e "Horário confirmado" marcado
- [ ] Logo, cor oficial e foto de compartilhamento enviados
- [ ] Termos, Privacidade e Política de agendamento revisados e publicados
- [ ] FAQ respondido e publicado
- [ ] E-mail de recuperação de senha chegando (SMTP)
- [ ] Cron executando (`storage/logs` sem erros do scheduler)
- [ ] Usuário gestor e barbeiros criados com os cargos corretos
