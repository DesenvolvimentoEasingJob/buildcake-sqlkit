# BuildCake SqlKit

Uma biblioteca PHP simples e poderosa para interagir com bancos de dados MySQL, fornecendo uma camada de abstração SQL com drivers e um compilador de queries com validação automática de tipos.

## Características

- ✅ **Drivers de Banco de Dados**: Suporte para MySQL (Oracle em desenvolvimento)
- ✅ **Validação Automática de Tipos**: Conversão e validação automática de dados baseada na estrutura da tabela
- ✅ **Query Builder**: Sistema de construção de queries com parâmetros seguros
- ✅ **PSR-4 Compatible**: Estrutura de namespaces seguindo padrões PSR-4
- ✅ **Prepared Statements**: Proteção contra SQL Injection usando PDO

## Instalação

```bash
composer require darkeght/buildcake-sqlkit
```

## Requisitos

- PHP >= 8.0
- Extensão PDO habilitada
- MySQL 5.7+ ou MariaDB 10.2+

## Configuração

Configure as variáveis de ambiente ou passe um array de configuração:

```php
// Via variáveis de ambiente
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'meu_banco';
$_ENV['DB_USER'] = 'usuario';
$_ENV['DB_PASS'] = 'senha';
$_ENV['DB_PORT'] = '3306'; // Opcional, padrão é 3306
$_ENV['DB_TYPE'] = 'MySQL'; // Opcional, padrão é MySQL

// Ou via array de configuração
$config = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'meu_banco',
    'DB_USER' => 'usuario',
    'DB_PASS' => 'senha',
    'DB_PORT' => '3306', // Opcional, padrão é 3306
    'DB_TYPE' => 'MySQL'
];
```

## Uso Básico

### Inicialização

```php
use BuildCake\SqlKit\Sql;

// Inicializar com configuração padrão (usa $_ENV)
$sql = Sql::Call();

// Ou passar configuração customizada
$sql = Sql::Call($config);
```

### Operações CRUD Simples

```php
use BuildCake\SqlKit\Sql;

$sql = Sql::Call();

// SELECT
$resultados = $sql->Select("SELECT * FROM usuarios WHERE ativo = 1");

// INSERT
$id = $sql->Insert("INSERT INTO usuarios (nome, email) VALUES ('João', 'joao@email.com')");

// UPDATE
$sql->Update("UPDATE usuarios SET nome = 'João Silva' WHERE id = 1");

// DELETE
$sql->Delete("DELETE FROM usuarios WHERE id = 1");
```

### Operações com Parâmetros

```php
use BuildCake\SqlKit\Sql;

$sql = Sql::Call();

// SELECT com parâmetros
$params = [
    ['COLUMN_NAME' => 'id', 'VALUE' => 1],
    ['COLUMN_NAME' => 'ativo', 'VALUE' => true]
];
$resultados = $sql->SelectParms($params, "SELECT * FROM usuarios WHERE id = :id AND ativo = :ativo");

// INSERT com parâmetros
$params = [
    ['COLUMN_NAME' => 'nome', 'VALUE' => 'João'],
    ['COLUMN_NAME' => 'email', 'VALUE' => 'joao@email.com']
];
$id = $sql->InsertParms($params, "INSERT INTO usuarios (nome, email) VALUES (:nome, :email)");
```

## Query Builder (BuildQuery)

A classe `BuildQuery` oferece métodos avançados com validação automática de tipos baseada na estrutura da tabela.

### Inserir Dados (runPost)

```php
use BuildCake\SqlKit\Query\BuildQuery;

$buildQuery = new BuildQuery();

$data = [
    'nome' => 'João Silva',
    'idade' => '25',           // Será convertido para int automaticamente
    'ativo' => 'true',         // Será convertido para 1
    'salario' => '1500.50',    // Será convertido para float
    'data_nascimento' => '1998-05-15', // Será validado como DATE
    'email' => 'joao@email.com'
];

try {
    $id = $buildQuery->runPost('usuarios', $data);
    echo "Usuário inserido com ID: $id";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
```

### Atualizar Dados (runPut)

```php
use BuildCake\SqlKit\Query\BuildQuery;

$buildQuery = new BuildQuery();

$updateData = [
    'id' => 1,
    'nome' => 'João Silva Atualizado',
    'idade' => 26
];

$result = $buildQuery->runPut('usuarios', $updateData);
echo "Linhas afetadas: $result";
```

### Deletar Dados (runDelet)

```php
use BuildCake\SqlKit\Query\BuildQuery;

$buildQuery = new BuildQuery();

// Soft delete (marca como inativo)
$ids = [1, 2, 3];
$result = $buildQuery->runDelet('usuarios', $ids);
```

### Executar Query Customizada (runQuery)

```php
use BuildCake\SqlKit\Query\BuildQuery;

$buildQuery = new BuildQuery();

$query = "SELECT * FROM usuarios WHERE idade > :idade AND ativo = :ativo";
$params = [
    'idade' => 18,
    'ativo' => true
];

$resultados = $buildQuery->runQuery($query, $params);
```

### Obter Informações da Tabela

```php
use BuildCake\SqlKit\Query\BuildQuery;

$buildQuery = new BuildQuery();

// Obter informações dos campos
$fieldsInfo = $buildQuery->getTableFieldsInfo('usuarios');

// Validar campos obrigatórios
$data = ['nome' => 'João'];
$missingFields = $buildQuery->validateRequiredFields('usuarios', $data);

if (!empty($missingFields)) {
    echo "Campos obrigatórios faltando: " . implode(', ', $missingFields);
}
```

## Validação Automática de Tipos

A biblioteca automaticamente valida e converte tipos de dados baseado na estrutura da tabela:

- **Booleanos**: Converte `true`/`false`, `'true'`/`'false'`, `1`/`0` para valores apropriados
- **Inteiros**: Valida e converte strings numéricas para inteiros
- **Decimais**: Converte para float quando necessário
- **Datas**: Valida formatos DATE, DATETIME, TIMESTAMP
- **JSON**: Valida e converte arrays/objetos para JSON
- **Strings**: Trata e sanitiza strings

## Métodos Estáticos Úteis

```php
use BuildCake\SqlKit\Sql;

// Executar query com sanitização
$result = Sql::runQuery("SELECT * FROM usuarios WHERE nome = :nome", ['nome' => 'João']);

// Inserir dados
$id = Sql::runPost('usuarios', ['nome' => 'João', 'email' => 'joao@email.com']);

// Atualizar dados
Sql::runPut('usuarios', ['id' => 1, 'nome' => 'João Atualizado']);

// Deletar dados
Sql::runDelet('usuarios', [1, 2, 3]);
```

## Tratamento de Erros

```php
use BuildCake\SqlKit\Sql;
use BuildCake\SqlKit\Query\BuildQuery;

try {
    $sql = Sql::Call($config);
    $result = $sql->Select("SELECT * FROM usuarios");
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
```

## Estrutura do Projeto

```
src/
├── Sql.php              # Classe principal
├── Drive/
│   └── MySQLDriver.php  # Driver MySQL
└── Query/
    └── BuildQuery.php   # Query Builder com validação
```

## Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou pull requests.

## Licença

Este projeto está licenciado sob a licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.

## Autor

Desenvolvido por Felipe Machado Hillesheim

## Roadmap

- [ ] Suporte para Oracle Database
- [ ] Suporte para PostgreSQL
- [ ] Migrations
- [ ] Transações
- [ ] Cache de queries
- [ ] Logging de queries

