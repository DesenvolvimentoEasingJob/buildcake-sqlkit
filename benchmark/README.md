# Teste de Performance CRUD - SQLKit

Teste completo comparando todas as operações CRUD da biblioteca com comandos SQL puros.

## Teste Principal

### `full_performance_test.php`

Compara performance entre métodos da lib vs comandos SQL puros para todas as operações CRUD:

- **SELECT**: `runQuery` vs `PureCommand`
- **INSERT**: `runPost` vs `INSERT` puro
- **UPDATE**: `runPut` vs `UPDATE` puro
- **DELETE**: `runDelet` vs `DELETE` puro

**Uso:**
```bash
# Com valores padrão (10000 registros, 50 iterações)
php benchmark/full_performance_test.php

# Com valores customizados
php benchmark/full_performance_test.php 50000 100

# Com senha do MySQL
php benchmark/full_performance_test.php 10000 50 sua_senha
```

**Parâmetros:**
- `num_registros`: Número de registros para SELECT (padrão: 10000)
- `num_iteracoes`: Número de iterações por teste (padrão: 50)
- `senha`: Senha do MySQL (opcional)

## O que o teste faz

1. **Prepara ambiente**
   - Conecta ao MySQL
   - Cria banco `test_performance` se não existir
   - Cria 2 tabelas: uma para SELECT, outra para INSERT/UPDATE/DELETE
   - Insere dados massivos para testes de SELECT

2. **Executa testes comparativos:**

   **SELECT:**
   - PureCommand: SQL puro direto no banco
   - runQuery: Com tratamento de parâmetros

   **INSERT:**
   - INSERT puro: Comando SQL direto
   - runPost: Com validação de tipos e tratamento

   **UPDATE:**
   - UPDATE puro: Comando SQL direto
   - runPut: Com validação de tipos e tratamento

   **DELETE:**
   - DELETE puro: Soft delete direto
   - runDelet: Com tratamento e validação

3. **Gera relatório:**
   - Tabela comparativa com todos os métodos
   - Tempo total e médio por operação
   - Operações por segundo
   - Overhead de cada método da lib
   - Análise com indicadores visuais

4. **Limpeza automática**
   - Remove tabelas de teste
   - Limpa cache

## Configuração

**Padrão:**
- Host: `127.0.0.1`
- Usuário: `root`
- Senha: (vazia)
- Porta: `3306`
- Banco: `test_performance` (criado automaticamente)

**Formas de configurar:**

1. **Variável de ambiente:**
```bash
export DB_PASS=sua_senha
php benchmark/full_performance_test.php
```

2. **Parâmetro na linha de comando:**
```bash
php benchmark/full_performance_test.php 10000 50 sua_senha
```

3. **Editando o arquivo:**
Edite a linha 29 de `full_performance_test.php`:
```php
'DB_PASS' => 'sua_senha',
```

## Requisitos

- PHP 8.0+
- MySQL rodando
- Permissão para criar banco de dados
- Extensão PDO habilitada

## Exemplo de Saída

```
========================================
  TESTE DE PERFORMANCE CRUD - SQLKit
========================================

Configuração:
  Registros: 10,000
  Iterações: 50
  Host: 127.0.0.1
  Usuário: root
  Senha: ***

📦 Preparando ambiente...
✅ Ambiente preparado!

============================================================
EXECUTANDO TESTES CRUD
============================================================

📊 SELECT - Comparação
------------------------------------------------------------
PureCommand: 123.45 ms | 2.4690 ms/op | 405 ops/s
runQuery: 145.67 ms | 2.9134 ms/op | 343 ops/s
   Overhead: 18.02%

📊 INSERT - Comparação
------------------------------------------------------------
INSERT puro: 234.56 ms | 4.6912 ms/op | 213 ops/s
runPost: 456.78 ms | 9.1356 ms/op | 109 ops/s
   Overhead: 94.73%

📊 UPDATE - Comparação
------------------------------------------------------------
UPDATE puro: 123.45 ms | 2.4690 ms/op | 405 ops/s
runPut: 234.56 ms | 4.6912 ms/op | 213 ops/s
   Overhead: 90.00%

📊 DELETE - Comparação
------------------------------------------------------------
DELETE puro: 98.76 ms | 1.9752 ms/op | 506 ops/s
runDelet: 123.45 ms | 2.4690 ms/op | 405 ops/s
   Overhead: 25.00%

============================================================
RESUMO COMPARATIVO CRUD
============================================================

Operação            | Método               | Total (ms)   | Médio (ms)   |     Ops/Seg
--------------------------------------------------------------------------------
SELECT              | SQL Puro             |       123.45 |      2.4690 |          405
SELECT              | runQuery             |       145.67 |      2.9134 |          343
INSERT              | SQL Puro             |       234.56 |      4.6912 |          213
INSERT              | runPost              |       456.78 |      9.1356 |          109
UPDATE              | SQL Puro             |       123.45 |      2.4690 |          405
UPDATE              | runPut               |       234.56 |      4.6912 |          213
DELETE              | SQL Puro             |        98.76 |      1.9752 |          506
DELETE              | runDelet             |       123.45 |      2.4690 |          405

--------------------------------------------------------------------------------
ANÁLISE DE OVERHEAD
--------------------------------------------------------------------------------

SELECT   : 18.02% overhead ✅
INSERT  : 94.73% overhead ⚠️
UPDATE  : 90.00% overhead ⚠️
DELETE  : 25.00% overhead ⚡

✅ Concluído!
```

## Interpretando Resultados

- **✅ Overhead < 15%**: Excelente, overhead mínimo
- **⚡ Overhead 15-30%**: Aceitável, overhead moderado
- **⚠️ Overhead > 30%**: Alto, pode indicar necessidade de otimização

**Nota:** Overhead alto em INSERT/UPDATE é esperado pois `runPost` e `runPut` fazem:
- Consulta à INFORMATION_SCHEMA para obter tipos de dados
- Validação e tratamento de tipos
- Verificação de campos obrigatórios
- Tratamento de campos especiais (created_by, updated_by, etc.)

Este overhead é o custo da segurança e validação automática que a biblioteca oferece.
