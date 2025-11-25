<?php

namespace BuildCake\SqlKit\Query;

use BuildCake\SqlKit\Sql;
use BuildCake\SqlKit\Cache\QueryCache;
use DateTime;
use Exception;

/**
 * Classe BuildQuery - Sistema de construção e validação de queries
 * 
 * Esta classe fornece métodos para construir queries SQL com validação automática
 * de tipos de dados baseada na estrutura da tabela.
 * 
 * Exemplo de uso:
 * 
 * $buildQuery = new BuildQuery();
 * 
 * // Inserir dados com validação automática de tipos
 * $data = [
 *     'nome' => 'João Silva',
 *     'idade' => '25',           // Será convertido para int
 *     'ativo' => 'true',         // Será convertido para 1
 *     'salario' => '1500.50',    // Será convertido para float
 *     'data_nascimento' => '1998-05-15', // Será validado como DATE
 *     'email' => 'joao@email.com'
 * ];
 * 
 * try {
 *     $result = $buildQuery->runPost('usuarios', $data);
 *     echo "Usuário inserido com sucesso!";
 * } catch (Exception $e) {
 *     echo "Erro: " . $e->getMessage();
 * }
 * 
 * // Atualizar dados
 * $updateData = [
 *     'id' => 1,
 *     'nome' => 'João Silva Atualizado',
 *     'idade' => 26
 * ];
 * 
 * $result = $buildQuery->runPut('usuarios', $updateData);
 * 
 * // Obter informações dos campos da tabela
 * $fieldsInfo = $buildQuery->getTableFieldsInfo('usuarios');
 * 
 * // Validar campos obrigatórios
 * $missingFields = $buildQuery->validateRequiredFields('usuarios', $data);
 * if (!empty($missingFields)) {
 *     echo "Campos obrigatórios faltando: " . implode(', ', $missingFields);
 * }
 */
class BuildQuery
{
    public function __construct(){ }
    
    /**
     * Trata o valor de um campo baseado no tipo de dados da coluna
     * @param mixed $value Valor original do campo
     * @param string $dataType Tipo de dados da coluna
     * @param string $isNullable Se a coluna permite NULL
     * @return mixed Valor tratado
     */
    private function treatFieldValue($value, $dataType, $isNullable = 'YES') {
        // Se o valor é null e a coluna permite NULL, retorna null
        if ($value === null && $isNullable === 'YES') {
            return null;
        }
        
        // Se o valor é string vazia e a coluna permite NULL, converte para null
        if ($value === '' && $isNullable === 'YES') {
            return null;
        }
        
        $dataType = strtoupper($dataType);
        
        switch ($dataType) {
            case 'BIT':
            case 'TINYINT':
            case 'BOOLEAN':
                // Para campos booleanos e TINYINT(1), converter para inteiro (0 ou 1)
                if (is_bool($value)) {
                    return $value ? 1 : 0;
                } elseif (is_numeric($value)) {
                    return (int)$value;
                } elseif (is_string($value)) {
                    // Trata strings como "0", "1", "true", "false"
                    $value = strtolower(trim($value));
                    if ($value === 'true' || $value === '1' || $value === 'yes') {
                        return 1;
                    } elseif ($value === 'false' || $value === '0' || $value === 'no') {
                        return 0;
                    }
                }
                break;
                
            case 'SMALLINT':
            case 'MEDIUMINT':
            case 'INT':
            case 'INTEGER':
            case 'BIGINT':
                // Para campos inteiros, garantir que sejam tratados como inteiros
                if (is_numeric($value)) {
                    return (int)$value;
                } elseif (is_string($value) && is_numeric(trim($value))) {
                    return (int)trim($value);
                }
                break;
                
            case 'DECIMAL':
            case 'FLOAT':
            case 'DOUBLE':
            case 'REAL':
                // Para campos decimais, garantir que sejam tratados como float
                if (is_numeric($value)) {
                    return (float)$value;
                } elseif (is_string($value) && is_numeric(trim($value))) {
                    return (float)trim($value);
                }
                break;
                
            case 'DATE':
                // Para campos DATE, garantir formato Y-m-d
                if ($value instanceof DateTime) {
                    return $value->format('Y-m-d');
                } elseif (is_string($value) && !empty($value)) {
                    // Tenta validar se é uma data válida
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    if ($date !== false) {
                        return $value;
                    }
                    // Tenta outros formatos comuns
                    $date = DateTime::createFromFormat('d/m/Y', $value);
                    if ($date !== false) {
                        return $date->format('Y-m-d');
                    }
                }
                break;
                
            case 'DATETIME':
            case 'TIMESTAMP':
                // Para campos DATETIME/TIMESTAMP, garantir formato Y-m-d H:i:s
                if ($value instanceof DateTime) {
                    return $value->format('Y-m-d H:i:s');
                } elseif (is_string($value) && !empty($value)) {
                    // Tenta validar se é uma data/hora válida
                    $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                    if ($date !== false) {
                        return $value;
                    }
                    // Tenta outros formatos comuns
                    $date = DateTime::createFromFormat('d/m/Y H:i:s', $value);
                    if ($date !== false) {
                        return $date->format('Y-m-d H:i:s');
                    }
                    $date = DateTime::createFromFormat('Y-m-d H:i', $value);
                    if ($date !== false) {
                        return $date->format('Y-m-d H:i:s');
                    }
                }
                break;
                
            case 'TIME':
                // Para campos TIME, garantir formato H:i:s
                if ($value instanceof DateTime) {
                    return $value->format('H:i:s');
                } elseif (is_string($value) && !empty($value)) {
                    // Tenta validar se é um tempo válido
                    $time = DateTime::createFromFormat('H:i:s', $value);
                    if ($time !== false) {
                        return $value;
                    }
                    $time = DateTime::createFromFormat('H:i', $value);
                    if ($time !== false) {
                        return $time->format('H:i:s');
                    }
                }
                break;
                
            case 'YEAR':
                // Para campos YEAR, garantir que seja um ano válido
                if (is_numeric($value)) {
                    $year = (int)$value;
                    if ($year >= 1901 && $year <= 2155) {
                        return $year;
                    }
                } elseif (is_string($value) && is_numeric(trim($value))) {
                    $year = (int)trim($value);
                    if ($year >= 1901 && $year <= 2155) {
                        return $year;
                    }
                }
                break;
                
            case 'CHAR':
            case 'VARCHAR':
            case 'TEXT':
            case 'LONGTEXT':
            case 'MEDIUMTEXT':
            case 'TINYTEXT':
                // Para campos de texto, garantir que sejam strings
                if (is_string($value)) {
                    return trim($value);
                } elseif (is_numeric($value)) {
                    return (string)$value;
                } elseif (is_bool($value)) {
                    return $value ? '1' : '0';
                }
                break;
                
            case 'BLOB':
            case 'LONGBLOB':
            case 'MEDIUMBLOB':
            case 'TINYBLOB':
                // Para campos BLOB, retornar como está (assumindo que já está no formato correto)
                return $value;
                break;
                
            case 'JSON':
                // Para campos JSON, garantir que seja uma string JSON válida
                if (is_string($value)) {
                    // Verifica se é JSON válido
                    json_decode($value);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $value;
                    }
                } elseif (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }
                break;
        }
        
        return $value;
    }
    
    /**
     * Trata valores de parâmetros para queries quando não temos acesso ao tipo de dados da coluna
     * @param mixed $value Valor original do parâmetro
     * @return mixed Valor tratado
     */
    private function treatQueryParamValue($value) {
        // Se já é um tipo primitivo adequado, retorna como está
        if (is_int($value) || is_float($value) || is_null($value)) {
            return $value;
        }
        
        // Se é boolean, converte para inteiro
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        
        // Se é string, verifica se parece ser um valor booleano
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if ($value === 'true' || $value === '1') {
                return 1;
            } elseif ($value === 'false' || $value === '0') {
                return 0;
            }
        }
        
        // Se é numérico mas string, converte para o tipo apropriado
        if (is_numeric($value)) {
            if (strpos($value, '.') !== false) {
                return (float)$value;
            } else {
                return (int)$value;
            }
        }
        
        return $value;
    }
    
    /**
     * Valida e trata um valor antes de inserir/atualizar no banco
     * @param mixed $value Valor a ser validado
     * @param string $dataType Tipo de dados da coluna
     * @param string $isNullable Se a coluna permite NULL
     * @param string $columnName Nome da coluna (para mensagens de erro)
     * @return mixed Valor tratado
     * @throws Exception Se o valor não for válido para o tipo de dados
     */
    private function validateAndTreatValue($value, $dataType, $isNullable, $columnName) {
        try {
            $treatedValue = $this->treatFieldValue($value, $dataType, $isNullable);
            
            // Validações específicas por tipo e conversão para tipo PHP apropriado
            $dataType = strtoupper($dataType);
            
            switch ($dataType) {
                case 'BIT':
                case 'BOOLEAN':
                    // Para campos booleanos, retornar como boolean PHP
                    if ($treatedValue !== null) {
                        if (!is_bool($treatedValue) && !is_int($treatedValue)) {
                            throw new Exception("Campo '{$columnName}' deve ser um valor booleano válido");
                        }
                        return (bool)$treatedValue;
                    }
                    break;
                    
                case 'INT':
                case 'TINYINT':
                case 'INTEGER':
                case 'BIGINT':
                case 'SMALLINT':
                case 'MEDIUMINT':
                    if ($treatedValue !== null) {
                        if (!is_numeric($treatedValue)) {
                            throw new Exception("Campo '{$columnName}' deve ser um número inteiro");
                        }
                        return (int)$treatedValue;
                    }
                    break;
                    
                case 'DECIMAL':
                case 'FLOAT':
                case 'DOUBLE':
                case 'REAL':
                    if ($treatedValue !== null) {
                        if (!is_numeric($treatedValue)) {
                            throw new Exception("Campo '{$columnName}' deve ser um número decimal");
                        }
                        return (float)$treatedValue;
                    }
                    break;
                    
                case 'DATE':
                    if ($treatedValue !== null) {
                        if (!$this->isValidDate($treatedValue, 'Y-m-d')) {
                            throw new Exception("Campo '{$columnName}' deve ser uma data válida no formato Y-m-d");
                        }
                        return $treatedValue; // Retorna como string no formato Y-m-d
                    }
                    break;
                    
                case 'DATETIME':
                case 'TIMESTAMP':
                    if ($treatedValue !== null) {
                        if (!$this->isValidDate($treatedValue, 'Y-m-d H:i:s')) {
                            throw new Exception("Campo '{$columnName}' deve ser uma data/hora válida no formato Y-m-d H:i:s");
                        }
                        return $treatedValue; // Retorna como string no formato Y-m-d H:i:s
                    }
                    break;
                    
                case 'TIME':
                    if ($treatedValue !== null) {
                        if (!$this->isValidTime($treatedValue)) {
                            throw new Exception("Campo '{$columnName}' deve ser um horário válido no formato H:i:s");
                        }
                        return $treatedValue; // Retorna como string no formato H:i:s
                    }
                    break;
                    
                case 'YEAR':
                    if ($treatedValue !== null) {
                        if (!is_numeric($treatedValue) || (int)$treatedValue < 1901 || (int)$treatedValue > 2155) {
                            throw new Exception("Campo '{$columnName}' deve ser um ano válido entre 1901 e 2155");
                        }
                        return (int)$treatedValue;
                    }
                    break;
                    
                case 'JSON':
                    if ($treatedValue !== null) {
                        if (!$this->isValidJson($treatedValue)) {
                            throw new Exception("Campo '{$columnName}' deve ser um JSON válido");
                        }
                        // Retorna como array/object se for JSON válido
                        $decoded = json_decode($treatedValue, true);
                        return $decoded !== null ? $decoded : $treatedValue;
                    }
                    break;
                    
                case 'CHAR':
                case 'VARCHAR':
                case 'TEXT':
                case 'LONGTEXT':
                case 'MEDIUMTEXT':
                case 'TINYTEXT':
                    if ($treatedValue !== null) {
                        return (string)$treatedValue;
                    }
                    break;
                    
                case 'BLOB':
                case 'LONGBLOB':
                case 'MEDIUMBLOB':
                case 'TINYBLOB':
                    // Para BLOB, retornar como está (assumindo que já está no formato correto)
                    return $treatedValue;
                    break;
            }
            
            return $treatedValue;
            
        } catch (Exception $e) {
            throw new Exception("Erro ao validar campo '{$columnName}': " . $e->getMessage());
        }
    }
    
    /**
     * Verifica se uma string é uma data válida no formato especificado
     * @param string $date String da data
     * @param string $format Formato esperado
     * @return bool
     */
    private function isValidDate($date, $format) {
        if (!is_string($date)) {
            return false;
        }
        
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Verifica se uma string é um horário válido
     * @param string $time String do horário
     * @return bool
     */
    private function isValidTime($time) {
        if (!is_string($time)) {
            return false;
        }
        
        $t = DateTime::createFromFormat('H:i:s', $time);
        return $t && $t->format('H:i:s') === $time;
    }
    
    /**
     * Verifica se uma string é um JSON válido
     * @param string $json String JSON
     * @return bool
     */
    private function isValidJson($json) {
        if (!is_string($json)) {
            return false;
        }
        
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Obtém informações detalhadas sobre os campos de uma tabela
     * @param string $table Nome da tabela
     * @return array Array com informações dos campos
     */
    public function getTableFieldsInfo($table) {
        $fields = Sql::Call()->Select("SELECT 
            COLUMN_NAME, 
            DATA_TYPE, 
            IS_NULLABLE, 
            COLUMN_DEFAULT, 
            CHARACTER_MAXIMUM_LENGTH,
            NUMERIC_PRECISION,
            NUMERIC_SCALE,
            COLUMN_KEY,
            EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = database() 
        AND TABLE_NAME = '{$table}'
        ORDER BY ORDINAL_POSITION");
        
        if (!is_array($fields) || empty($fields)) {
            throw new Exception("Erro ao obter os campos da tabela: {$table}");
        }
        
        return $fields;
    }
    
    /**
     * Valida se um objeto tem todos os campos obrigatórios para uma tabela
     * @param string $table Nome da tabela
     * @param array $object Objeto a ser validado
     * @return array Array com campos obrigatórios que estão faltando
     */
    public function validateRequiredFields($table, $object) {
        $fields = $this->getTableFieldsInfo($table);
        $missingFields = [];
        
        foreach ($fields as $field) {
            $columnName = $field['COLUMN_NAME'];
            $isNullable = $field['IS_NULLABLE'];
            $hasDefault = $field['COLUMN_DEFAULT'] !== null;
            
            // Campos que sempre são preenchidos automaticamente
            $autoFields = ['id', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at'];
            
            if ($isNullable === 'NO' && !$hasDefault && !in_array($columnName, $autoFields)) {
                if (!isset($object[$columnName]) || $object[$columnName] === null || $object[$columnName] === '') {
                    $missingFields[] = $columnName;
                }
            }
        }
        
        return $missingFields;
    }

    //build query
    //query example "SELECT u.queryfilter FROM profilefilter u WHERE u.profile = :userprofile AND u.filename = :requestfile {filter}"
    public function runQuery($query, $params = null, $ignoreCache = false) {
        $params = $params ?? [];
        
        $formatedParms  = [];
        
        if ($params === null || empty($params)) {
            $query = str_replace("{filter}", "", $query);
            return Sql::Call()->Select($query);
        }
        
        foreach ($params as $key => $value) {
            $treatedValue = $this->treatQueryParamValue($value);
            array_push($formatedParms,['COLUMN_NAME' => $key, 'VALUE' => $treatedValue]);
        }

        

        $tablePrincipal = $this->extrairTabelaPrincipal($query);
        $conditionalInit = $this->hasWhereClause($query)? " AND" : " WHERE";

        if(isset($GLOBALS['currentUser'])) {
            $user = $GLOBALS['currentUser'];
            $userProfile = $user->role ?? 0;
            $queryFiler = Sql::Call()->Select("SELECT u.queryfilter 
                                                FROM profilefilter u 
                                                WHERE u.profile = {$userProfile} 
                                                AND u.tablename = '{$tablePrincipal}'");

            if ($queryFiler && isset($queryFiler[0]['queryfilter'])) {
                $query =   $query . str_replace(":userid", $user->sub, $queryFiler[0]['queryfilter']);
            }
        }

        

        if (isset($params["id"])) {
            $id = $params["id"];
            $query .= " {$conditionalInit} {$tablePrincipal}.id in ({$id})";
        }

        if (isset($params["where"])) {
            $where = explode("|", $params["where"]);
            $stringContitional = " {$conditionalInit} (";
            $queryStringBuild = "";

            foreach ($where as $key => $value) {
                $queryStringBuild .= $stringContitional;
                $stringContitional = " OR ";
                $valuefinish = str_replace(";", "=", $value);
                $queryStringBuild .= " {$tablePrincipal}.{$valuefinish}";
            }

            $queryStringBuild .= ")";
            $stringContitional = " AND (";

            if(isset($params["and"])){
            foreach ($params["and"] as $key => $baseValue) {
                $and = explode("|", $baseValue);

                foreach ($and as $key => $value) {
                    $queryStringBuild .= $stringContitional;
                    $stringContitional = " OR ";
                    $valuefinish = str_replace(";", "=", $value);
                    $queryStringBuild .= " {$tablePrincipal}.{$valuefinish}";
                }

                $queryStringBuild .= ")";
                $stringContitional = " AND (";
            }}

            $query = str_replace("{filter}", $queryStringBuild, $query);
        }

        if (isset($params["order"]) && isset($params["ordination"])) {
            $order = $params["order"];
            $ordination = $params["ordination"];
            $query .= " ORDER BY {$tablePrincipal}.{$order} {$ordination}";
        }

        if (isset($params["like"]) && isset($params["value"])) {
            $where = $params["like"];
            $value = $params["value"];

            $query .= "SELECT * 
                        FROM {$query} as tempLikeTerms 
                        WHERE tempLikeTerms.{$where} 
                        LIKE '{$value}'";
        }

           
        //limit and page
        $limit = $params["limit"] ?? 100;
        $limit = $limit > 1000 ? 1000 : $limit;
        
        $query .= " LIMIT {$limit}";
        $pageNumber = $params["page"] ?? 1;
        $limit2 = $params["limit2"] ?? 100;

       
        $page = ($pageNumber * $limit2) - $limit2;
        $query .= " OFFSET {$page}";

        $finalQuery = str_replace("{filter}", "", $query);
        $result = Sql::Call()->SelectParms($this->filterParamsByQuery($finalQuery,$formatedParms),$finalQuery);

        
        return $result;
    }

    public function runPost($table, $object, $user = null) {
        $formatedParms = [];

        if($user === null) {
            //criação de item ambos são inseridos com mesmo usuario
            $object['created_by'] = $GLOBALS['currentUser']->sub ?? 0;
            $object['updated_by'] = $object['created_by'];
        }else{
            $object['created_by'] = $user['id'];
            $object['updated_by'] = $user['id'];
        }

        $fields = Sql::Call()->Select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = database() 
        AND TABLE_NAME ='{$table}' 
        AND COLUMN_NAME NOT IN ('id','is_active','updated_by','created_by','created_at','updated_at')");

        $fieldNotnull = "";
        $delemiter = "";
        $keyValues = "";
        $parmsKeys = "";
        $delemiterInsert = "";

        if (!is_array($fields) || empty($fields)) {
            throw new Exception("Erro ao obter os campos da tabela: {$table}");
        }

        foreach ($fields as $key => $value) {
            $columnName = $value["COLUMN_NAME"];
            $dataType = $value["DATA_TYPE"];
            $isNullable = $value["IS_NULLABLE"];

            if ($isNullable !== "YES") {
                if (!isset($object[$columnName])) {
                    $fieldNotnull .= ($delemiter . $columnName);
                    $delemiter = ",";
                    continue;
                }
            }

            if(isset($object[$columnName]) &&
               $columnName !== "id" &&
               $columnName !== "is_active" &&
               $columnName !== "created_by" &&
               $columnName !== "updated_by" &&
               $columnName !== "created_at" &&
               $columnName !== "updated_at"){
                
                // Valida e trata o valor baseado no tipo de dados da coluna
                $fieldValue = $this->validateAndTreatValue($object[$columnName], $dataType, $isNullable, $columnName);
                
                array_push($formatedParms,['COLUMN_NAME' => $columnName, 'VALUE' => $fieldValue]);
                $keyValues .= ($delemiterInsert . $columnName);
                $parmsKeys .= ($delemiterInsert . ":" . $columnName);
                $delemiterInsert = ",";
            }
        }

        if ($fieldNotnull !== "") {
            throw new Exception("Campos obrigatórios não informados: {$fieldNotnull}", 1);
        }

        return Sql::Call()->InsertParms($formatedParms, 
        "INSERT INTO {$table} ({$keyValues}) VALUES ({$parmsKeys})");
    }

    public function runPut($table, $object, $user = null){
        $formatedParms = [];

        if($user === null) {
            $object['updated_by'] = $GLOBALS['currentUser']->sub?? 0;
        }else{
            $object['updated_by'] = $user['id'];
        }

        $object['updated_at'] = date('Y-m-d H:i:s');

        $fields = Sql::Call()->Select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = database() 
        AND TABLE_NAME ='{$table}' 
        AND COLUMN_NAME NOT IN ('id','is_active','created_by','created_at')");

        $fieldNotnull = "";
        $delemiter = "";
        $keyValues = "";
        $delemiterInsert = "";

        if (!is_array($fields) || empty($fields)) {
            throw new Exception("Erro ao obter os campos da tabela: {$table}");
        }        

        foreach ($fields as $key => $value) {
            $columnName = $value["COLUMN_NAME"];
            $dataType = $value["DATA_TYPE"];
            $isNullable = $value["IS_NULLABLE"];

            if(isset($object[$columnName]) &&
               $columnName !== "id" &&
               $columnName !== "is_active" &&
               $columnName !== "created_by" &&
               $columnName !== "created_at" ){
                
                // Valida e trata o valor baseado no tipo de dados da coluna
                $fieldValue = $this->validateAndTreatValue($object[$columnName], $dataType, $isNullable, $columnName);
                
                array_push($formatedParms,['COLUMN_NAME' => $columnName, 'VALUE' => $fieldValue]);
                $keyValues .= (($delemiterInsert . $columnName) . " = " . (":" . $columnName));
                $delemiterInsert = ",";
            }
        }

        if ($fieldNotnull !== "") {
            throw new Exception("Campos obrigatórios não informados: {$fieldNotnull}", 1);
        }
        
        $return = Sql::Call()->UpdateParms($formatedParms, "UPDATE {$table} SET {$keyValues} WHERE id in ({$object['id']})");//permite passar muitos ids separados por virgula atualizando uma massa maior de dados de uma ves

        return $return;
    }

    public function runDelet($table, $object, $user = null) {
        $userId = 0;
        if($user === null) {

            if(!isset($GLOBALS['currentUser'])) {
                throw new Exception("User not found, this method requires authentication", 1);
            }

            $userId = $GLOBALS['currentUser']->sub;
        }

        $ids = "'" . implode("', '", $object) . "'";
        Sql::Call()->Delete("UPDATE {$table} SET is_active = false, updated_by = {$userId} WHERE id in ({$ids})");

        return Sql::Call()->Select("SELECT * FROM {$table} WHERE id in ({$ids})");
    }

    public function extrairTabelaPrincipal($sql){
        // Remove quebras de linha e tabs para facilitar o processo de regex
        $sql = str_replace(["\n", "\t", "\r"], ' ', $sql);

        // Normaliza espaços em branco para apenas um espaço
        $sql = preg_replace('/\s+/', ' ', $sql);

        // Primeiro, vamos lidar com subqueries removendo-as temporariamente
        $tempSql = $sql;
        $subqueries = [];
        $i = 0;
        
        // Substitui subqueries por placeholders
        while (preg_match('/\([^()]*SELECT[^()]*FROM[^()]*\)/i', $tempSql)) {
            $tempSql = preg_replace_callback('/\([^()]*SELECT[^()]*FROM[^()]*\)/i', 
                function($match) use (&$subqueries, &$i) {
                    $placeholder = "SUBQUERY_PLACEHOLDER_$i";
                    $subqueries[$placeholder] = $match[0];
                    $i++;
                    return $placeholder;
                }, 
                $tempSql
            );
        }
        
        // Agora procura a tabela principal na query principal
        // Considera a primeira tabela após o FROM na query principal
        $pattern = '/\bFROM\b\s+([a-zA-Z0-9_]+)(?:\s+(?:AS\s+)?([a-zA-Z0-9_]+))?/i';
        
        if (preg_match($pattern, $tempSql, $matches)) {
            // Retorna o nome da tabela principal
            return $matches[1];
        } else {
            // Tenta novamente com a query original se não encontrou na versão modificada
            if (preg_match($pattern, $sql, $matches)) {
                return $matches[1];
            }
            // Retorna null se a tabela principal não puder ser encontrada
            return null;
        }
    }

    function hasWhereClause($sql) {
        $sql = strtoupper($sql); // Padroniza para facilitar a busca
        $sql = preg_replace('/\s+/', ' ', $sql); // Normaliza espaços
    
        $selectPosition = stripos($sql, 'SELECT'); // Posição do primeiro SELECT
        $wherePosition = stripos($sql, 'WHERE', $selectPosition); // Busca WHERE a partir do SELECT
    
        if ($wherePosition === false) {
            return false; // Não há WHERE
        }
    
        // Verifica se o WHERE pertence à consulta principal ou a uma subquery
        $openParentheses = 0;
        for ($i = 0; $i < $wherePosition; $i++) {
            if ($sql[$i] === '(') {
                $openParentheses++;
            } elseif ($sql[$i] === ')') {
                $openParentheses--;
            }
        }
    
        return $openParentheses === 0; // Se WHERE está fora de parênteses, é do SELECT principal
    }

    public function filterParamsByQuery($query, $formatedParms) {
        $paramsInQuery = [];
        preg_match_all('/:([a-zA-Z0-9_]+)/', $query, $matches); // Captura os nomes dos parâmetros da query
        
        if (!empty($matches[1])) {
            $paramsInQuery = $matches[1]; // Lista dos parâmetros usados na query
        }

        // Filtra $formatedParms para conter apenas os parâmetros usados na query
        $filteredParams = array_filter($formatedParms, function ($param) use ($paramsInQuery) {
            return in_array($param["COLUMN_NAME"], $paramsInQuery);
        });

        return array_values($filteredParams); // Reindexa o array filtrado
    }

}