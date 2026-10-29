# Lectta

Envie uma aula (áudio MP3, link do YouTube ou texto) e o Lectta devolve a
**transcrição**, um **resumo estruturado** e **flashcards** gerados por IA. Ele
também agenda a revisão desses cards com **repetição espaçada (SM-2)**, o mesmo
princípio do Anki.

Em uso real em [lectta.online/aulas](https://lectta.online/aulas): mais de 60
aulas processadas, cerca de 380 flashcards e mais de 1.100 revisões registradas.

## Funcionalidades

- **Três formas de enviar uma aula:** MP3, um ou vários links do YouTube, ou
  texto colado.
- **Processamento com IA (Google Gemini 2.5 Flash):** transcrição do áudio,
  resumo em markdown e flashcards de pergunta e resposta.
- **Edição completa:** resumo, transcrição e flashcards podem ser editados.
  Também dá para pedir mais cards à IA, sem repetir os que já existem.
- **Revisão espaçada:** quatro notas (Novamente, Difícil, Bom, Fácil),
  calculadas com o SM-2. Há também um modo prova, que puxa cards antes da data.
- **Organização:** matérias e assuntos, com um "radar de domínio" por matéria.
- **Progresso:** streak de dias seguidos e um heatmap das últimas 18 semanas.
- **Aviso por e-mail** quando uma aula termina de processar.
- **Multiusuário:** cada conta vê apenas os próprios dados.
- **PWA:** dá para instalar no celular. O layout foi pensado primeiro para o
  celular e tem tema claro e escuro.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.2, **Laravel 12** (MVC, Eloquent, Blade, middleware, Artisan) |
| Banco | MySQL / MariaDB |
| IA | Google Gemini 2.5 Flash (API REST via `Http` client do Laravel) |
| Front | Blade, CSS próprio e JavaScript puro, sem build step; PWA |
| Worker | Python (yt-dlp e ffmpeg) para baixar o áudio do YouTube |
| Infra | Hospedagem compartilhada (Hostinger) e cron |
| Testes | PHPUnit |

## Arquitetura

```
 Navegador / PWA
      │  sessão + CSRF
      ▼
 ┌──────────────────────────── Laravel ─────────────────────────────┐
 │ routes/web.php ─► Controllers ─► Views (Blade)                   │
 │                       │                                          │
 │                       ▼                                          │
 │               Models (Eloquent) ─► MySQL                         │
 │                       ▲                                          │
 │ routes/api.php ─► Api\PipelineController ─► LessonPipeline ──────┼─► Gemini
 │   (token)        Api\YoutubeController           │               │
 └──────────────────────▲───────────────────────────┼───────────────┘
                        │                           └─► e-mail "aula pronta"
          cron (1/min) ─┤
       worker Python  ──┘  (baixa o áudio do YouTube e entrega o MP3)
```

Estados de uma aula: `queued_youtube → downloading → pending → processing → done | error`.

## Como o processamento funciona

O problema: a hospedagem derruba qualquer requisição que passe de cerca de 2
minutos. Transcrever e resumir uma aula de 2 horas leva bem mais do que isso.
Uma única requisição "processa tudo" seria cortada no meio, e a aula ficaria
travada.

A solução é dividir o trabalho em passos curtos e salvar o progresso no banco
depois de cada um.

1. O MP3 é cortado em pedaços de até 15 MB, e a transcrição é dividida em
   partes de cerca de 60 mil caracteres.
2. A cada chamada, `LessonPipeline::tick()` faz **um único passo**: transcreve
   um pedaço do áudio ou resume uma parte. Em seguida, grava em
   `classes.pipeline_state` (JSON) o que já foi feito, por exemplo "pedaços 1 a
   4 transcritos".
3. O cron chama o pipeline a cada minuto. Cada chamada lê o estado salvo e
   continua do ponto em que a anterior parou. Quando ainda falta trabalho, o
   próprio endpoint dispara o passo seguinte, sem esperar o próximo minuto.
4. Se uma chamada cair no meio, só aquele passo se perde. O passo anterior já
   estava salvo, então o próximo tick refaz apenas o que faltou.
5. Um lock (`Cache::lock`) impede que o cron e o disparo encadeado processem
   a mesma aula ao mesmo tempo.
6. No último passo, transcrição, resumo e flashcards são gravados numa
   transação. Depois disso, o áudio é apagado e o e-mail é enviado.

**Erros:** um 429 ou 5xx da IA não marca a aula como erro. Ela continua em
`processing` e é tentada de novo no minuto seguinte. Se a IA recusar uma parte
por tamanho, a parte é dividida ao meio. Uma aula que fica 3 horas sem avançar é
encerrada com uma mensagem de erro.

Num servidor com fila de verdade, `tick()` viraria um Job do Laravel sem mudar a
lógica. O cron com estado salvo cumpre o mesmo papel em hospedagem
compartilhada, onde não dá para manter um worker de fila rodando.

## Outras decisões técnicas

- **A IA responde em texto com marcadores, não em JSON.** Com o markdown dentro
  de uma string JSON, o modelo precisa escapar cada quebra de linha e cada
  aspa, e ele erra sem avisar: o resumo vem cortado. Com marcadores como
  `<<<RESUMO>>>` e `P:`/`R:`, até uma resposta truncada continua aproveitável.
- **O MP3 é dividido por frames, em PHP.** Hospedagem compartilhada não tem
  ffmpeg. Como o MP3 é uma sequência de frames independentes, cortar entre
  frames gera arquivos válidos. A leitura é em stream, para não estourar a
  memória com arquivos de mais de 100 MB.
- **O prompt limita os flashcards.** O número de cards é um teto, não uma meta,
  e há regras explícitas do que não vira card. Um card ruim custa caro na
  repetição espaçada, porque continua voltando para revisão.
- **Isolamento por usuário:** todas as consultas filtram por `user_id`.
- **Segurança:**
  - senhas com bcrypt e CSRF em todos os formulários;
  - rate limit no login;
  - token comparado com `hash_equals` nos endpoints de máquina;
  - pasta de uploads bloqueada para acesso web.

## Estrutura

```
app/
├── Http/Controllers/        telas (login, painel, envio, edição, revisão, matérias, progresso)
│   └── Api/                 endpoints do cron e do worker (protegidos por token)
├── Http/Middleware/         VerifyApiToken
├── Models/                  User, Lesson (tabela classes), Subject, Topic, Flashcard, ReviewLog
├── Services/
│   ├── Gemini/              GeminiClient (HTTP + retries), PromptBuilder, ResponseParser
│   ├── Pipeline/            LessonPipeline (processamento em passos), ProcessingTrigger
│   ├── Audio/Mp3Splitter    divisão de MP3 por frames, sem ffmpeg
│   ├── Study/               Sm2, StreakService
│   └── Notifications/       e-mail de "aula pronta"
├── Support/                 Markdown, ícones SVG, cores por matéria
└── Console/Commands/        php artisan lectta:process
database/migrations/         schema completo
resources/views/             Blade (layout, componentes, telas)
routes/                      web.php (sessão) e api.php (token)
worker/                      worker Python do YouTube
scripts/                     build do pacote e deploy na Hostinger
tests/Unit/                  SM-2, parser da IA, markdown, divisão do texto
```

## Rodando localmente

Requisitos: PHP 8.2+, Composer e MySQL ou MariaDB.

```bash
composer install
cp .env.example .env     # preencha DB_*, GEMINI_API_KEY e LECTTA_API_TOKEN
php artisan key:generate
php artisan migrate
php artisan db:seed      # conta demo: demo@lectta.test / password
php artisan serve
```

Para processar as aulas pendentes sem cron:

```bash
php artisan lectta:process --until-idle
```

Em produção, o cron chama a cada minuto:

```
GET /api/process?token=<LECTTA_API_TOKEN>
```

### Variáveis específicas do app

| Variável | Para que serve |
|---|---|
| `GEMINI_API_KEY` | chave da API do Google Gemini |
| `LECTTA_API_TOKEN` | token dos endpoints `/api/*` (cron e worker) |
| `LECTTA_NOTIFY_FROM` | remetente do e-mail de "aula pronta" |
| `APP_TIMEZONE` | fuso usado para "hoje" nas revisões (vazio = fuso do servidor) |

## Testes

```bash
php artisan test
```

## Deploy

Veja [`DEPLOY.md`](DEPLOY.md).
