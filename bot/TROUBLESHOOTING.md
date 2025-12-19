# 🔧 Troubleshooting - Erro 403

## Problema: Erro 403 (Forbidden)

O erro 403 geralmente indica problema de autenticação com o Evolution API.

## ✅ Soluções

### 1. Verificar se o arquivo .env existe

Crie um arquivo `.env` na pasta `bot/` com:

```env
EVOLUTION_API_URL=http://localhost:8080
EVOLUTION_API_KEY=sua-chave-api-evolution
INSTANCE_NAME=bot-instance
LARAVEL_API_URL=http://localhost:8000
```

### 2. Verificar se o Evolution API está rodando

```bash
# Teste se a API está acessível
curl http://localhost:8080
```

Ou abra no navegador: `http://localhost:8080`

### 3. Verificar a API Key

A API Key deve ser a mesma configurada no Evolution API. 

**Como encontrar a API Key:**
- Verifique o arquivo de configuração do Evolution API (geralmente `.env` ou `config.json`)
- Procure por `API_KEY` ou `AUTHENTICATION_API_KEY`
- Se não encontrar, você pode precisar gerar uma nova no Evolution API

### 4. Verificar a URL do Evolution API

- Por padrão: `http://localhost:8080`
- Se estiver em outro host/porta, atualize no `.env`
- Exemplo: `http://192.168.1.100:8080` ou `https://api.exemplo.com`

### 5. Verificar se a instância já existe

Se a instância já existe, o bot deve continuar normalmente. Se houver problemas:

```bash
# Você pode deletar a instância via API
curl -X DELETE http://localhost:8080/instance/bot-instance \
  -H "apikey: sua-chave-api"
```

### 6. Verificar logs do Evolution API

Verifique os logs do Evolution API para ver se há mais detalhes sobre o erro.

## 🧪 Teste Manual

Teste a conexão manualmente:

```bash
# Windows PowerShell
$headers = @{
    "apikey" = "sua-chave-api"
    "Content-Type" = "application/json"
}
$body = @{
    instanceName = "bot-instance"
    qrcode = $true
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:8080/instance/create" -Method Post -Headers $headers -Body $body
```

Se isso funcionar, o problema está no código. Se não funcionar, o problema está na configuração do Evolution API.

## 📝 Checklist

- [ ] Arquivo `.env` criado na pasta `bot/`
- [ ] `EVOLUTION_API_KEY` configurada corretamente
- [ ] `EVOLUTION_API_URL` está correto
- [ ] Evolution API está rodando e acessível
- [ ] API Key está correta no Evolution API
- [ ] Porta 8080 não está bloqueada pelo firewall

## 💡 Dicas

- Use `npm run dev` para ver logs detalhados
- O bot agora mostra mais informações sobre erros
- Verifique se não há espaços extras na API Key no `.env`

