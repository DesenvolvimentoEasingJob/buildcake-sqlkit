<?php

namespace BuildCake\SqlKit;

use BuildCake\SqlKit\Drive\MySQLDriver;
use BuildCake\SqlKit\Query\BuildQuery;
use PDOException;
use Exception;

/**
 * Classe principal para execução de operações SQL
 * 
 * @author felipe.machado 
 */
class Sql
{
   
    public static $conection;
	public static $single;
    
    public static function Call($config = null,$server = ""){

        if($config === null){
            $config = $_ENV;
        }

        if(self::$conection === null){
            switch ($config['DB_TYPE'] ?? 'MySQL') {
                case 'MySQL':
                    self::$conection = MySQLDriver::GetIstance($config);
                    break;
                case 'T-SQL':
                    // T-SQL driver será implementado futuramente
                    throw new \Exception('Driver T-SQL ainda não implementado');
                default:
                    self::$conection = MySQLDriver::GetIstance($config);
                    break;
            }
        }

        if(self::$single === null){            
            self::$single = new Sql();
        }

        return self::$single;
    }
    
    function Delete($expressao){
        try{
            return Sql::$conection->Delete($expressao);
        }
        catch(PDOException $e)
        {
            throw new Exception("Erro ao executar DELETE: " . $e->getMessage());
        }
    }
    
    function Update($expressao){
        try{
            return Sql::$conection->Update($expressao);
        }
        catch(PDOException $e)
        {
            throw new Exception("Erro ao executar UPDATE: " . $e->getMessage());
        }
    }

    function Select($expressao){
        try{
            return Sql::$conection->Select($expressao);

        }
        catch(PDOException $e){
            return false;
        }
    }

    function SelectOnly($expressao){
        try{
            return Sql::$conection->SelectOnly($expressao);
        }
        catch(PDOException $e){
            return false;
        }
    }
    
    function Insert($expressao){
         try{
            return Sql::$conection->Insert($expressao);
        }
        catch(PDOException $e)
        {
            throw new Exception("Erro ao executar INSERT: " . $e->getMessage());
        }
    }

    function PureCommand($expressao){
        try{
            return Sql::$conection->PureCommand($expressao);
        }
        catch(PDOException $e)
        {
            throw new Exception("Erro na execução do comando SQL: " . $e->getMessage());
        }
    }

    function SelectParms($object,$expressao){
        try{
            return self::$conection->SelectParms($object,$expressao);
        }
        catch(PDOException $e){
            return false;
        }
    }

    function UpdateParms($object,$expressao){
        try{
            return self::$conection->UpdateParms($object,$expressao);
        }
        catch(PDOException $e){
            return false;
        }
    }

    function InsertParms($object,$expressao){
        try{
            return Sql::$conection->InsertParms($object,$expressao);
        }
        catch(PDOException $e){
            return false;
        }
    }

    private function sanitizeQuery($query) {
        // Remove múltiplos espaços e normaliza a string
        $query = preg_replace('/\s+/', ' ', trim($query));
        
        // Bloqueia uso de UNION, subqueries e outras palavras-chave perigosas
        $forbidden = ['DELETE', 'ALTER', 'DROP', 'UPDATE', 'INSERT', 'UNION', '--', '#', '/*', '*/'];
        
        foreach ($forbidden as $word) {
            if (stripos($query, $word) !== false) {
                throw new Exception("Uso de SQL não permitido: {$word}");
            }
        }
    
        return $query;
    }
    
    static function runQuery($query, $params = null) {
        $buildQuery = new BuildQuery();
        $query = Sql::Call()->sanitizeQuery($query);
        return $buildQuery->runQuery($query, $params);
    }

    static function runPost($table, $object, $user = null) {
        $buildQuery = new BuildQuery();
        return $buildQuery->runPost($table, $object, $user);
    }

    static function runDelet($table, $object, $user = null) {
        $buildQuery = new BuildQuery();
        return $buildQuery->runDelet($table, $object, $user);
    }

    static function runPut($table, $object, $user = null) {
        $buildQuery = new BuildQuery();
        return $buildQuery->runPut($table, $object, $user);
    }
}
