<?php

namespace BuildCake\SqlKit;

use BuildCake\SqlKit\Drive\MySQLDriver;
use BuildCake\SqlKit\Query\BuildQuery;
use BuildCake\SqlKit\Cache\QueryCache;
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
    
    /**
     * Configura o sistema de cache
     * @param array $config Configurações do cache:
     *   - 'enabled' (bool): Habilita/desabilita cache (padrão: true)
     *   - 'hours' (int): Duração do cache em horas (padrão: 1)
     *   - 'dir' (string): Diretório para armazenar cache (padrão: sys_get_temp_dir()/buildcake-sqlkit-cache)
     */
    public static function configureCache($config = [])
    {
        if (isset($config['enabled'])) {
            QueryCache::setCacheEnabled($config['enabled']);
        }
        
        if (isset($config['hours'])) {
            QueryCache::setCacheHours($config['hours']);
        }
        
        if (isset($config['dir'])) {
            QueryCache::setCacheDir($config['dir']);
        }
    }
    
    /**
     * Limpa todo o cache armazenado
     * @return bool
     */
    public static function clearCache()
    {
        return QueryCache::clearCache();
    }
    
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
    
        // Palavras perigosas (somente palavra inteira)
        $forbidden = ['DELETE', 'ALTER', 'DROP', 'UPDATE', 'CREATE', 'INSERT', 'UNION'];
    
        foreach ($forbidden as $word) {
            // \b = boundary → match somente palavra isolada
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $query)) {
                throw new Exception("Uso de SQL não permitido: {$word}");
            }
        }
    
        // Tokens perigosos que não são palavras (tratamento especial)
        $forbiddenTokens = ['--', '#', '/*', '*/'];
        foreach ($forbiddenTokens as $token) {
            if (stripos($query, $token) !== false) {
                throw new Exception("Uso de SQL não permitido: {$token}");
            }
        }
    
        return $query;
    }
    
    static function runQuery($query, $params = null, $ignoreCache = false) {
        $buildQuery = new BuildQuery();
        $query = Sql::Call()->sanitizeQuery($query);
        return $buildQuery->runQuery($query, $params, $ignoreCache);
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
