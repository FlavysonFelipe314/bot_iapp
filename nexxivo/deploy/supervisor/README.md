# Fila Laravel com Supervisor (produção)

Solução recomendada pela [documentação oficial do Laravel](https://laravel.com/docs/queues#supervisor-configuration) para manter o worker da fila sempre rodando.

## Por que Supervisor?

- O worker (`queue:work`) pode morrer por timeout, deploy ou erro.
- Supervisor **reinicia automaticamente** o processo e **inicia no boot** do servidor.
- Sem Supervisor, quando o worker para, as mensagens do WhatsApp ficam na fila e o bot não responde.

## Instalação (Ubuntu/Debian)

### 1. Instalar Supervisor

```bash
sudo apt-get update
sudo apt-get install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### 2. Copiar e ativar a config

```bash
cd /home/cv-389/Sites/sites/sites/nexxivo
sudo cp deploy/supervisor/laravel-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start "laravel-worker:*"
```

### 3. Verificar

```bash
sudo supervisorctl status
```

Deve mostrar `laravel-worker:laravel-worker_00` e `laravel-worker_01` como RUNNING.

## Comandos úteis

| Comando | Descrição |
|---------|-----------|
| `sudo supervisorctl status` | Status dos workers |
| `sudo supervisorctl restart laravel-worker:*` | Reiniciar todos os workers (útil após deploy) |
| `sudo supervisorctl stop laravel-worker:*` | Parar workers |
| `tail -f storage/logs/supervisor-worker.log` | Log do worker |

## Parâmetros importantes

- **stopwaitsecs=3600**: Supervisor espera até 1h para o job terminar antes de matar (Ollama pode demorar).
- **timeout=300**: Cada job pode rodar até 5 minutos (ProcessIncomingMessageJob).
- **numprocs=2**: Dois workers em paralelo (pode aumentar se precisar de mais throughput).

## Alternativas (Kafka, Redis, Horizon)

- **Redis + Horizon**: Melhor para alto volume; requer Redis. Ver [Laravel Horizon](https://laravel.com/docs/horizon).
- **Kafka**: Útil para event streaming e múltiplos consumidores; exige infraestrutura adicional. Para um bot WhatsApp, Supervisor + fila database é suficiente e recomendado pela documentação Laravel.
