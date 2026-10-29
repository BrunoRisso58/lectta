# Deploy na Hostinger

## Layout no servidor

```
domains/lectta.online/
├── lectta-app/          <- código Laravel, vendor, worker e .env   (fora da web)
└── public_html/
    └── aulas/           <- só a pasta pública: index.php, css, js, img
        └── uploads/     <- áudios aguardando processamento (bloqueada para a web)
```

O `public_html/aulas/index.php` encontra o app em `../../lectta-app`.

## Antes de começar

1. **hPanel → Avançado → Configuração PHP:** a versão do PHP do domínio precisa
   ser **8.2 ou maior** (o Laravel 12 exige).
2. Gere o pacote:
   ```
   bash scripts/build-deploy.sh            # usa .env.production
   ```
   Saída: `../deploy/lectta-app.zip`, `../deploy/aulas.zip` e `../deploy/deploy-hostinger.sh`.

## Opção A — via SSH (recomendado)

1. Envie os três arquivos para `domains/lectta.online/` (File Manager ou SFTP).
2. No SSH:
   ```
   cd ~/domains/lectta.online
   bash deploy-hostinger.sh
   ```
   O script para no primeiro erro. Ele faz, nesta ordem:
   1. `mysqldump` do banco em `~/lectta-backups/<data>/banco.sql`;
   2. compacta a pasta `public_html/aulas` atual em `aulas.tar.gz`;
   3. instala o app em `lectta-app/`. Se já existir uma versão, ela vai para o
      backup e as sessões e os logs são mantidos;
   4. troca o conteúdo de `public_html/aulas`, **preservando `uploads/`**;
   5. atualiza o `youtube_worker.py` no `$HOME`, se encontrar, e mostra a URL
      que o cron deve chamar.

## Opção B — pelo File Manager (sem SSH)

1. **Backup do banco:** phpMyAdmin → banco → Exportar → Executar.
2. **Backup dos arquivos:** File Manager → clique com o botão direito em
   `public_html/aulas` → Compactar → baixe o zip.
3. Envie `lectta-app.zip` para `domains/lectta.online/` (a pasta que **contém**
   `public_html`) e extraia. Deve aparecer `domains/lectta.online/lectta-app/`.
4. Abra `public_html/aulas`, selecione **tudo, exceto a pasta `uploads`**, e
   exclua.
5. Envie `aulas.zip` para dentro de `public_html/aulas` e extraia ali.
6. Faça os passos de "Cron e worker" abaixo.

## Cron e worker

- **Cron** (hPanel → Avançado → Cron Jobs), a cada minuto:
  ```
  curl -s "https://lectta.online/aulas/api/process?token=<LECTTA_API_TOKEN>"
  ```
- **Worker do YouTube:** fica no `$HOME`, com o próprio `config.py` (ver
  `worker/config.py.example`). Ele chama `/api/youtube/jobs` e
  `/api/youtube/ingest`.

## Depois do deploy (checklist)

- [ ] `https://lectta.online/aulas/` abre a tela de login.
- [ ] O login funciona e as aulas aparecem na tela inicial.
- [ ] `https://lectta.online/aulas/api/process?token=<TOKEN>` responde
      `{"ok":true,"job":null}` quando não há nada na fila.
- [ ] O cron chama `.../api/process?token=...`, sem `.php` no final.

## Voltar para a versão anterior

O deploy não altera o banco, então basta restaurar os arquivos do backup:

```
cd ~/domains/lectta.online
rm -rf lectta-app && mv ~/lectta-backups/<data>/lectta-app lectta-app   # se existir
cd public_html
find aulas -mindepth 1 -maxdepth 1 ! -name uploads -exec rm -rf {} +
tar -xzf ~/lectta-backups/<data>/aulas.tar.gz
```

O backup também tem o `youtube_worker.py` anterior, se o script o atualizou.

Logs: `lectta-app/storage/logs/laravel.log`.

## Comandos úteis (SSH)

```
php lectta-app/artisan lectta:process               # avança um passo do pipeline
php lectta-app/artisan lectta:process --until-idle  # processa até a fila acabar
php lectta-app/artisan route:list --except-vendor
```
