# 🚀 Como Executar o SEED de WODs

## Opção 1: Via phpMyAdmin (Mais Fácil)

1. Abra http://localhost:8082
2. Faça login:
   - Usuário: `root`
   - Senha: `root`

3. Selecione o banco: `appcheckin`

4. Clique em "SQL" no topo

5. Cole **TODO** o conteúdo do arquivo `database/seeds/seed_wods.sql`

6. Clique em "Executar" (Execute)

7. Pronto! ✅

---

## Opção 2: Via Linha de Comando

```bash
cd /Users/andrecabral/Projetos/AppCheckin/Backend
docker exec -i appcheckin_mysql mysql -uroot -proot appcheckin < database/seeds/seed_wods.sql
```

---

## Opção 3: Verificar se Funcionou

Depois de executar, teste no phpMyAdmin:

```sql
-- Ver todos os WODs
SELECT * FROM wods;

-- Ver total de blocos
SELECT COUNT(*) FROM wod_blocos;

-- Ver variações
SELECT * FROM wod_variacoes;

-- Ver resultados
SELECT * FROM wod_resultados;
```

Ou via API:

```bash
curl -X GET http://localhost:8080/admin/wods \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Deve retornar:
```json
{
  "type": "success",
  "message": "WODs listados com sucesso",
  "data": [
    {
      "id": 1,
      "titulo": "WOD 15 de Janeiro",
      "data": "2026-01-15",
      "status": "published"
    },
    ...
  ],
  "total": 5
}
```

---

## 📊 O que será inserido:

- ✅ 5 WODs diferentes
- ✅ 18 Blocos (aquecimento, força, metcon, etc)
- ✅ 13 Variações (RX, Scaled, Beginner)
- ✅ 14 Resultados com tempos e pesos

---

## 🆘 Se deu erro:

Tente limpar e recarregar:

```sql
-- Limpar dados (se houver conflito de IDs)
DELETE FROM wod_resultados;
DELETE FROM wod_variacoes;
DELETE FROM wod_blocos;
DELETE FROM wods;

-- Depois execute o seed novamente
```

