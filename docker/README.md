# Evolution API (Docker)

Sobe o Evolution API v2 na porta 8080, com PostgreSQL para persistência.

## Para o QR Code funcionar (Laravel no host)

1. No **nexxivo**, o `.env` deve ter `EVOLUTION_WEBHOOK_URL=http://host.docker.internal:8000`.
2. Suba o Laravel escutando em todas as interfaces: `php artisan serve --host=0.0.0.0 --port=8000` (ou `npm run serve` no nexxivo).
3. Depois suba a Evolution: `docker compose up -d`.

Assim o container consegue enviar o webhook do QR para o Laravel.

## Subir só a Evolution

```bash
cd docker
cp .env.example .env
# edite .env (EVOLUTION_API_KEY = mesma do nexxivo)
docker compose up -d
```

Serviços: **evolution-api** (porta 8080) e **evolution-postgres** (banco interno). Para parar: `docker compose down`.
