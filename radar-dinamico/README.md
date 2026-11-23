# Radar Dinâmico — app Android para motoristas de aplicativo

Acompanha os dinâmicos perto de você, guarda o histórico e **avisa quando aparece um dinâmico que vale a pena**
segundo as **suas** regras. Exemplo: *"me avise de dinâmicos a partir de +R$ 6 num raio de 5 km"*.

## Instalar no celular

1. Baixe `dist/radar-dinamico-0.1.0.apk` (ou o artefato da última execução do GitHub Actions).
2. No Android, permita **"Instalar apps desconhecidos"** para o navegador ou gerenciador de arquivos.
3. Abra o APK, instale e toque em **Iniciar radar**. Aceite a localização e as notificações.
4. (Opcional) Ative o **leitor do Uber Driver**: Ajustes do Android → Acessibilidade → *Radar Dinâmico — leitor do Uber Driver*.

Requer Android 8.0 ou superior.

## O que dá para configurar (aba Ajustes)

| Grupo | Opções |
|---|---|
| Perfis rápidos | Seletivo (+R$ 10 / 3 km), Equilibrado (+R$ 6 / 5 km), Caçador (+R$ 4 / 10 km) |
| Regras | dinâmico mínimo em R$ (1–30), raio (1–30 km), multiplicador mínimo (1,1x–3,0x, liga/desliga), validade da leitura, intervalo entre alertas da mesma região |
| Horário | faixa de horas (inclusive noturna, ex.: 20h–4h) e dias da semana |
| Como avisar | som, vibração, **falar em voz alta**, sugestões de regiões pelo histórico e com que frequência |
| Fontes | leitor de tela do Uber Driver, comunidade (receber e/ou compartilhar), URL do servidor, intervalo de atualização |
| Dados | apagar histórico |

## Telas

- **Radar** — liga/desliga, resumo da sua regra, status (GPS, notificações, leitor), botões *Marcar dinâmico aqui* (+R$ 4…20 ou outro valor) e a lista de dinâmicos ativos com distância e direção. Tocar abre a navegação (Waze/Maps).
- **Mapa** — seu raio de alerta, os dinâmicos ativos e, opcionalmente, as médias históricas para o horário atual (OpenStreetMap, sem chave de API).
- **Melhores** — ranking das regiões (~1 km) que mais pagaram no horário escolhido, separado por dia útil e fim de semana.
- **Notificação fixa** — com os botões *Marcar dinâmico aqui* e *Parar*, para usar sem abrir o app.

## De onde vêm os dados (importante)

A Uber **não oferece API pública** com os dinâmicos do app de motorista. Por isso o app junta três fontes, todas opcionais:

1. **Leitor de tela (experimental)** — um Serviço de Acessibilidade que lê apenas os textos visíveis do app
   `com.ubercab.driver` e procura padrões como `+R$ 6,00` ou `1,5x`. A leitura é gravada **na sua posição atual**
   (não na zona exata do mapa). Ele não clica nem altera nada. Se a Uber desenhar os valores só como imagem no mapa,
   o leitor não vai enxergá-los — por isso o status mostra a hora da última leitura, para você conferir se está funcionando.
   Ler a tela de outro app pode contrariar os Termos de Uso da Uber; use por sua conta e risco.
2. **Registro manual** — um toque em *+R$ 6* (no app ou na notificação) grava o dinâmico onde você está.
3. **Comunidade** — qualquer servidor que siga o contrato abaixo. Com vários motoristas compartilhando, os alertas
   passam a cobrir regiões onde você ainda não está.

Tudo fica salvo só no aparelho (SQLite) a menos que você ative *Compartilhar minhas leituras*.

### Contrato do servidor comunitário

```
GET  {url}/surges?lat=-23.55&lng=-46.63&radiusKm=10
  -> [{"lat":-23.56,"lng":-46.64,"kind":"MONEY","value":8.0,"ts":1764000000000}]
POST {url}/surges
  body: {"lat":-23.56,"lng":-46.64,"kind":"MONEY","value":8.0,"ts":1764000000000}
```
`kind` é `MONEY` (valor em R$) ou `MULTIPLIER` (ex.: 1.5). `ts` em milissegundos.

## Como os alertas são decididos

`data/AlertEngine.kt` (coberto por testes): considera só leituras dentro da validade, que passam do mínimo em R$
(ou do multiplicador, se ligado), dentro do raio, dentro do horário e fora do intervalo de repetição daquela região.
Entre as que passam, avisa a de maior valor (1,0x de multiplicador conta como cerca de R$ 10 para comparar).

## Compilar

```bash
cd radar-dinamico
./gradlew testDebugUnitTest assembleRelease
# APK: app/build/outputs/apk/release/app-release.apk
```
Precisa do JDK 17+ e do Android SDK (35). O APK é assinado com a chave de debug, para instalar direto;
para publicar na Play Store, configure uma chave própria em `app/build.gradle.kts`.

## Estrutura

```
app/src/main/java/br/com/radardinamico/
  data/      Settings, SurgeParser, AlertEngine, Insights, SurgeRepository (SQLite), Geo
  service/   RadarService (GPS + regras + notificações), UberReaderService (acessibilidade),
             CommunitySource, Notifier (notificação, vibração, voz)
  ui/        Radar, Mapa, Melhores, Ajustes (Jetpack Compose, tema escuro de alto contraste)
```
