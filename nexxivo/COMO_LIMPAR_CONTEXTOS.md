# 🗑️ Como Limpar Todos os Contextos de Conversa

Existem **3 formas** de limpar todos os contextos de conversa (conversas e mensagens):

## 1️⃣ Via API (Recomendado)

### Limpar TODOS os contextos:
```bash
DELETE http://localhost:8000/api/conversations/clear-all
```

### Limpar contextos de uma instância específica:
```bash
DELETE http://localhost:8000/api/conversations/clear-all?instance_name=bot-instance
```

**Exemplo com cURL:**
```bash
# Limpar todos
curl -X DELETE http://localhost:8000/api/conversations/clear-all

# Limpar apenas de uma instância
curl -X DELETE "http://localhost:8000/api/conversations/clear-all?instance_name=bot-instance"
```

**Exemplo com PowerShell:**
```powershell
# Limpar todos
Invoke-RestMethod -Uri "http://localhost:8000/api/conversations/clear-all" -Method Delete

# Limpar apenas de uma instância
Invoke-RestMethod -Uri "http://localhost:8000/api/conversations/clear-all?instance_name=bot-instance" -Method Delete
```

---

## 2️⃣ Via Comando Artisan (Terminal)

### Limpar TODOS os contextos:
```bash
php artisan conversations:clear
```

### Limpar contextos de uma instância específica:
```bash
php artisan conversations:clear --instance=bot-instance
```

### Limpar sem confirmação (útil para scripts):
```bash
php artisan conversations:clear --force
php artisan conversations:clear --instance=bot-instance --force
```

**Exemplo:**
```bash
cd C:\Users\Redis_py\Documents\vone\sites\nexxivo
php artisan conversations:clear
```

O comando vai perguntar confirmação antes de deletar (a menos que use `--force`).

---

## 3️⃣ Via Banco de Dados (SQL)

⚠️ **CUIDADO:** Use apenas se souber o que está fazendo!

### Limpar TODOS os contextos:
```sql
-- Deletar todas as mensagens primeiro (devido à foreign key)
DELETE FROM messages;

-- Deletar todas as conversas
DELETE FROM conversations;
```

### Limpar contextos de uma instância específica:
```sql
-- Deletar mensagens de conversas de uma instância
DELETE FROM messages 
WHERE conversation_id IN (
    SELECT id FROM conversations 
    WHERE instance_name = 'bot-instance'
);

-- Deletar conversas da instância
DELETE FROM conversations 
WHERE instance_name = 'bot-instance';
```

---

## 📋 O que é deletado?

Quando você limpa os contextos, são removidos:
- ✅ Todas as **mensagens** armazenadas
- ✅ Todas as **conversas** armazenadas
- ✅ Todo o **histórico** usado pela IA para contexto

**IMPORTANTE:** 
- ⚠️ Esta operação **NÃO pode ser desfeita**
- ⚠️ Após limpar, a IA não terá mais memória de conversas anteriores
- ⚠️ Novas conversas serão criadas normalmente após a limpeza

---

## 🔍 Verificar quantos contextos existem

Antes de limpar, você pode verificar quantos contextos existem:

### Via API:
```bash
GET http://localhost:8000/api/conversations
```

### Via SQL:
```sql
SELECT COUNT(*) as total_conversas FROM conversations;
SELECT COUNT(*) as total_mensagens FROM messages;
```

---

## 💡 Dicas

1. **Backup:** Se quiser fazer backup antes de limpar, exporte as tabelas:
   ```sql
   -- Exportar conversas
   SELECT * FROM conversations INTO OUTFILE 'conversations_backup.csv';
   
   -- Exportar mensagens
   SELECT * FROM messages INTO OUTFILE 'messages_backup.csv';
   ```

2. **Limpeza seletiva:** Use `--instance=` para limpar apenas contextos de uma instância específica, mantendo outros intactos.

3. **Agendamento:** Você pode agendar limpeza automática usando cron jobs ou task schedulers do Laravel.

---

## ❓ Dúvidas?

Se tiver problemas ou dúvidas, verifique:
- ✅ Se o Laravel está rodando
- ✅ Se as rotas da API estão acessíveis
- ✅ Se você tem permissões no banco de dados
- ✅ Se a instância especificada existe





