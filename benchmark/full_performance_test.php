<?php
/**
 * Teste Completo de Performance - Comparação CRUD Completo
 * 
 * Compara performance entre métodos da lib vs comandos SQL puros:
 * - SELECT: runQuery vs PureCommand
 * - INSERT: runPost vs INSERT puro
 * - UPDATE: runPut vs UPDATE puro
 * - DELETE: runDelet vs DELETE puro
 * 
 * Uso: php benchmark/full_performance_test.php [num_registros] [num_iteracoes]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BuildCake\SqlKit\Sql;
use BuildCake\SqlKit\Cache\QueryCache;

// ============================================
// CONFIGURAÇÃO
// ============================================
$numRegistros = isset($argv[1]) ? (int)$argv[1] : 10000;
$numIteracoes = isset($argv[2]) ? (int)$argv[2] : 50;

$dbConfig = [
    'DB_HOST' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'DB_NAME' => $_ENV['DB_NAME'] ?? 'test_performance',
    'DB_USER' => $_ENV['DB_USER'] ?? 'root',
    'DB_PASS' => $_ENV['DB_PASS'] ?? 'root',
    'DB_PORT' => isset($_ENV['DB_PORT']) ? (int)$_ENV['DB_PORT'] : 3306,
    'DB_TYPE' => $_ENV['DB_TYPE'] ?? 'MySQL'
];

if (isset($argv[3])) {
    $dbConfig['DB_PASS'] = $argv[3];
}

// ============================================
// INICIALIZAÇÃO
// ============================================
echo "========================================\n";
echo "  TESTE DE PERFORMANCE CRUD - SQLKit\n";
echo "========================================\n\n";

echo "Configuração:\n";
echo "  Registros: " . number_format($numRegistros) . "\n";
echo "  Iterações: $numIteracoes\n";
echo "  Host: {$dbConfig['DB_HOST']}\n";
echo "  Usuário: {$dbConfig['DB_USER']}\n";
echo "  Senha: " . (empty($dbConfig['DB_PASS']) ? "(vazia)" : "***") . "\n\n";

// ============================================
// PREPARAÇÃO DO AMBIENTE
// ============================================
echo "📦 Preparando ambiente...\n";

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['DB_HOST']};port={$dbConfig['DB_PORT']}",
        $dbConfig['DB_USER'],
        $dbConfig['DB_PASS']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "  Criando banco de dados...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['DB_NAME']}`");
    $pdo->exec("USE `{$dbConfig['DB_NAME']}`");
    
    echo "🔌 Conectando...\n";
    Sql::Call($dbConfig);
    echo "✅ Conectado!\n\n";
    
    // Tabela para SELECT
    echo "  Criando tabela para SELECT...\n";
    $pdo->exec("DROP TABLE IF EXISTS `benchmark_select`");
    $pdo->exec("
        CREATE TABLE `benchmark_select` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nome` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `idade` INT NOT NULL,
            `ativo` TINYINT(1) DEFAULT 1,
            `categoria` VARCHAR(100) NOT NULL,
            INDEX idx_categoria (`categoria`),
            INDEX idx_ativo (`ativo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Tabela para INSERT/UPDATE/DELETE
    echo "  Criando tabela para INSERT/UPDATE/DELETE...\n";
    $pdo->exec("DROP TABLE IF EXISTS `benchmark_crud`");
    $pdo->exec("
        CREATE TABLE `benchmark_crud` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nome` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `idade` INT NOT NULL,
            `ativo` TINYINT(1) DEFAULT 1,
            `is_active` TINYINT(1) DEFAULT 1,
            `categoria` VARCHAR(100) NOT NULL,
            `created_by` INT DEFAULT 0,
            `updated_by` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_categoria (`categoria`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insere dados para SELECT
    echo "📥 Inserindo dados para SELECT...\n";
    $stmt = $pdo->prepare("INSERT INTO benchmark_select (nome, email, idade, ativo, categoria) VALUES (?, ?, ?, ?, ?)");
    $categorias = ['A', 'B', 'C'];
    for ($i = 0; $i < $numRegistros; $i++) {
        $stmt->execute([
            "Usuario " . ($i + 1),
            "user" . ($i + 1) . "@test.com",
            20 + ($i % 50),
            $i % 2,
            $categorias[$i % count($categorias)]
        ]);
        if (($i + 1) % 1000 == 0) {
            echo "  " . number_format($i + 1) . " registros...\r";
        }
    }
    echo "\n✅ Ambiente preparado!\n\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n\n";
    echo "💡 Dicas:\n";
    echo "   1. Verifique se o MySQL está rodando\n";
    echo "   2. Configure: export DB_PASS=sua_senha\n";
    echo "   3. Ou passe: php benchmark/full_performance_test.php 10000 50 sua_senha\n\n";
    die("   Abortando...\n");
}

// Desabilita currentUser para testes
$GLOBALS['currentUser'] = (object)['sub' => 0, 'role' => 0];

// ============================================
// TESTES
// ============================================
$results = [];

echo str_repeat("=", 60) . "\n";
echo "EXECUTANDO TESTES CRUD\n";
echo str_repeat("=", 60) . "\n\n";

// ============================================
// SELECT - runQuery vs PureCommand
// ============================================
echo "📊 SELECT - Comparação\n";
echo str_repeat("-", 60) . "\n";

$selectQuery = "SELECT * FROM benchmark_select WHERE categoria = :categoria AND ativo = :ativo AND idade BETWEEN :idade_min AND :idade_max LIMIT 100";
$selectParams = ['categoria' => 'A', 'ativo' => 1, 'idade_min' => 25, 'idade_max' => 45];
$pureSelect = "SELECT * FROM benchmark_select WHERE categoria = 'A' AND ativo = 1 AND idade BETWEEN 25 AND 45 LIMIT 100";

// PureCommand SELECT
Sql::configureCache(['enabled' => false]);
Sql::clearCache();

$time = measureTime(function() use ($pureSelect, $numIteracoes) {
    for ($i = 0; $i < $numIteracoes; $i++) {
        $stmt = Sql::Call()->PureCommand($pureSelect);
        if ($stmt instanceof \PDOStatement) {
            $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    }
});
$results['SELECT_Pure'] = $time;
echo "PureCommand: ";
printResult($time, $numIteracoes);

// runQuery SELECT sem cache
$time = measureTime(function() use ($selectQuery, $selectParams, $numIteracoes) {
    for ($i = 0; $i < $numIteracoes; $i++) {
        Sql::runQuery($selectQuery, $selectParams, true);
    }
});
$results['SELECT_runQuery'] = $time;
echo "runQuery: ";
printResult($time, $numIteracoes);
$overhead = (($time - $results['SELECT_Pure']) / $results['SELECT_Pure']) * 100;
echo "   Overhead: " . number_format($overhead, 2) . "%\n";

// ============================================
// INSERT - runPost vs INSERT puro
// ============================================
echo "\n📊 INSERT - Comparação\n";
echo str_repeat("-", 60) . "\n";

// Prepara dados para INSERT
$insertData = [
    'nome' => 'Teste Insert',
    'email' => 'teste@insert.com',
    'idade' => 30,
    'ativo' => 1,
    'categoria' => 'A'
];

// INSERT puro
$time = measureTime(function() use ($pdo, $insertData, $numIteracoes) {
    $stmt = $pdo->prepare("INSERT INTO benchmark_crud (nome, email, idade, ativo, categoria) VALUES (?, ?, ?, ?, ?)");
    for ($i = 0; $i < $numIteracoes; $i++) {
        $stmt->execute([
            $insertData['nome'] . " " . $i,
            $insertData['email'],
            $insertData['idade'],
            $insertData['ativo'],
            $insertData['categoria']
        ]);
    }
    // Limpa após teste
    $pdo->exec("DELETE FROM benchmark_crud WHERE nome LIKE 'Teste Insert%'");
});
$results['INSERT_Pure'] = $time;
echo "INSERT puro: ";
printResult($time, $numIteracoes);

// runPost INSERT
$time = measureTime(function() use ($insertData, $numIteracoes, $pdo) {
    for ($i = 0; $i < $numIteracoes; $i++) {
        $data = $insertData;
        $data['nome'] = $insertData['nome'] . " " . $i;
        Sql::runPost('benchmark_crud', $data);
    }
    // Limpa após teste
    $pdo->exec("DELETE FROM benchmark_crud WHERE nome LIKE 'Teste Insert%'");
});
$results['INSERT_runPost'] = $time;
echo "runPost: ";
printResult($time, $numIteracoes);
$overhead = (($time - $results['INSERT_Pure']) / $results['INSERT_Pure']) * 100;
echo "   Overhead: " . number_format($overhead, 2) . "%\n";

// ============================================
// UPDATE - runPut vs UPDATE puro
// ============================================
echo "\n📊 UPDATE - Comparação\n";
echo str_repeat("-", 60) . "\n";

// Prepara dados para UPDATE (insere alguns registros primeiro)
$pdo->exec("DELETE FROM benchmark_crud");
$stmt = $pdo->prepare("INSERT INTO benchmark_crud (nome, email, idade, ativo, categoria) VALUES (?, ?, ?, ?, ?)");
for ($i = 0; $i < $numIteracoes; $i++) {
    $stmt->execute(["Update Test " . $i, "update{$i}@test.com", 25, 1, 'A']);
}
$ids = $pdo->query("SELECT id FROM benchmark_crud ORDER BY id LIMIT $numIteracoes")->fetchAll(PDO::FETCH_COLUMN);

// UPDATE puro
$time = measureTime(function() use ($pdo, $ids, $numIteracoes) {
    $stmt = $pdo->prepare("UPDATE benchmark_crud SET nome = ?, idade = ? WHERE id = ?");
    for ($i = 0; $i < $numIteracoes; $i++) {
        $stmt->execute(["Updated " . $i, 30 + $i, $ids[$i]]);
    }
});
$results['UPDATE_Pure'] = $time;
echo "UPDATE puro: ";
printResult($time, $numIteracoes);

// runPut UPDATE
$time = measureTime(function() use ($ids, $numIteracoes) {
    for ($i = 0; $i < $numIteracoes; $i++) {
        Sql::runPut('benchmark_crud', [
            'id' => $ids[$i],
            'nome' => "Updated " . $i,
            'idade' => 30 + $i
        ]);
    }
});
$results['UPDATE_runPut'] = $time;
echo "runPut: ";
printResult($time, $numIteracoes);
$overhead = (($time - $results['UPDATE_Pure']) / $results['UPDATE_Pure']) * 100;
echo "   Overhead: " . number_format($overhead, 2) . "%\n";

// ============================================
// DELETE - runDelet vs DELETE puro
// ============================================
echo "\n📊 DELETE - Comparação\n";
echo str_repeat("-", 60) . "\n";

// Prepara dados para DELETE (insere alguns registros primeiro)
$pdo->exec("DELETE FROM benchmark_crud");
$stmt = $pdo->prepare("INSERT INTO benchmark_crud (nome, email, idade, ativo, categoria) VALUES (?, ?, ?, ?, ?)");
for ($i = 0; $i < $numIteracoes; $i++) {
    $stmt->execute(["Delete Test " . $i, "delete{$i}@test.com", 25, 1, 'A']);
}
$ids = $pdo->query("SELECT id FROM benchmark_crud ORDER BY id LIMIT $numIteracoes")->fetchAll(PDO::FETCH_COLUMN);

// DELETE puro (soft delete usando is_active)
$time = measureTime(function() use ($pdo, $ids, $numIteracoes) {
    $stmt = $pdo->prepare("UPDATE benchmark_crud SET is_active = 0, updated_by = 0 WHERE id = ?");
    for ($i = 0; $i < $numIteracoes; $i++) {
        $stmt->execute([$ids[$i]]);
    }
    // Restaura para próximo teste
    $stmt = $pdo->prepare("UPDATE benchmark_crud SET is_active = 1 WHERE id = ?");
    for ($i = 0; $i < $numIteracoes; $i++) {
        $stmt->execute([$ids[$i]]);
    }
});
$results['DELETE_Pure'] = $time;
echo "DELETE puro: ";
printResult($time, $numIteracoes);

// runDelet DELETE
$time = measureTime(function() use ($ids, $numIteracoes, $pdo) {
    Sql::runDelet('benchmark_crud', $ids);
    // Restaura para próximo teste
    $stmt = $pdo->prepare("UPDATE benchmark_crud SET is_active = 1 WHERE id = ?");
    for ($i = 0; $i < $numIteracoes; $i++) {
        $stmt->execute([$ids[$i]]);
    }
});
$results['DELETE_runDelet'] = $time;
echo "runDelet: ";
printResult($time, $numIteracoes);
$overhead = (($time - $results['DELETE_Pure']) / $results['DELETE_Pure']) * 100;
echo "   Overhead: " . number_format($overhead, 2) . "%\n";

// ============================================
// RESUMO COMPARATIVO
// ============================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "RESUMO COMPARATIVO CRUD\n";
echo str_repeat("=", 60) . "\n\n";

printf("%-20s | %-20s | %12s | %12s | %12s\n", "Operação", "Método", "Total (ms)", "Médio (ms)", "Ops/Seg");
echo str_repeat("-", 80) . "\n";

$operations = [
    'SELECT' => ['Pure', 'runQuery'],
    'INSERT' => ['Pure', 'runPost'],
    'UPDATE' => ['Pure', 'runPut'],
    'DELETE' => ['Pure', 'runDelet']
];

foreach ($operations as $op => $methods) {
    foreach ($methods as $method) {
        $key = "{$op}_{$method}";
        if (isset($results[$key])) {
            $timeMs = $results[$key];
            $avg = $timeMs / $numIteracoes;
            $ops = 1000 / $avg;
            $methodName = $method === 'Pure' ? 'SQL Puro' : $method;
            printf("%-20s | %-20s | %12.2f | %12.4f | %12.0f\n", $op, $methodName, $timeMs, $avg, $ops);
        }
    }
}

echo "\n" . str_repeat("-", 80) . "\n";
echo "ANÁLISE DE OVERHEAD\n";
echo str_repeat("-", 80) . "\n\n";

foreach ($operations as $op => $methods) {
    $pureKey = "{$op}_Pure";
    $libKey = "{$op}_" . ($methods[1] === 'runQuery' ? 'runQuery' : ($methods[1] === 'runPost' ? 'runPost' : ($methods[1] === 'runPut' ? 'runPut' : 'runDelet')));
    
    if (isset($results[$pureKey]) && isset($results[$libKey])) {
        $overhead = (($results[$libKey] - $results[$pureKey]) / $results[$pureKey]) * 100;
        $status = $overhead > 30 ? "⚠️" : ($overhead > 15 ? "⚡" : "✅");
        echo sprintf("%-10s: %6.2f%% overhead %s\n", $op, $overhead, $status);
    }
}

// ============================================
// LIMPEZA
// ============================================
echo "\n🧹 Limpando...\n";
try {
    Sql::clearCache();
    $pdo->exec("DROP TABLE IF EXISTS `benchmark_select`");
    $pdo->exec("DROP TABLE IF EXISTS `benchmark_crud`");
    echo "✅ Concluído!\n";
} catch (Exception $e) {
    echo "⚠️  Erro na limpeza: " . $e->getMessage() . "\n";
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function measureTime(callable $function) {
    // Warmup
    for ($i = 0; $i < 3; $i++) {
        try {
            $function();
        } catch (Exception $e) {
            // Ignora
        }
    }
    
    // Medição
    $start = microtime(true);
    $function();
    $end = microtime(true);
    
    return ($end - $start) * 1000; // ms
}

function printResult($timeMs, $iterations) {
    $avg = $timeMs / $iterations;
    $ops = 1000 / $avg;
    echo number_format($timeMs, 2) . " ms | " . number_format($avg, 4) . " ms/op | " . number_format($ops, 0) . " ops/s\n";
}
