# Evolution API (imagem oficial) + Nexxivo

Este compose usa a **imagem oficial** `evoapicloud/evolution-api:latest`, que devolve o QR Code no `GET /instance/connect` e no webhook. Assim o painel Laravel (Nexxivo) consegue exibir o QR ao criar uma instância.

## Aplicar a solução (já configurada)

1. Na pasta do Docker (esta pasta):
   ```bash
   docker compose pull
   docker compose up -d --force-recreate evolution-api
   ```

2. Laravel (Nexxivo) na porta 8000, acessível pelo host:
   ```bash
   cd ../nexxivo && php artisan serve --host=0.0.0.0 --port=8000
   ```

3. Se o webhook não alcançar o Laravel (ex.: host não resolve `host.docker.internal`), defina no `.env` desta pasta e no do Nexxivo uma URL acessível pelo container (ex.: ngrok):
   ```bash
   WEBHOOK_GLOBAL_URL=https://seu-ngrok.ngrok.io/api/webhooks/evolution
   ```

4. No painel: criar uma nova instância e escanear o QR (ou usar o código de pareamento, se aparecer).

## Variáveis

- `EVOLUTION_API_KEY`: mesma chave usada no `.env` do Nexxivo (`EVOLUTION_API_KEY`).
- `WEBHOOK_GLOBAL_URL`: URL do webhook (Laravel). Padrão: `http://host.docker.internal:8000/api/webhooks/evolution`.
