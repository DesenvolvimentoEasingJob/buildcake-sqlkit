<?php

namespace BuildCake\SqlKit\Cache;

use Exception;

/**
 * Classe QueryCache - Sistema de cache para queries SQL
 * 
 * Armazena resultados de queries em arquivos organizados por data/hora
 * Estrutura: temp/ano/mes/dia/hora/hash.cache
 * 
 * @author felipe.machado
 */
class QueryCache
{
    private static $cacheDir = null;
    private static $cacheEnabled = true;
    private static $cacheHours = 1; // Duração padrão do cache em horas
    private static $currentHourDir = null;
    
    /**
     * Configura o diretório de cache
     * @param string $dir Diretório base para cache (padrão: sys_get_temp_dir()/buildcake-sqlkit-cache)
     */
    public static function setCacheDir($dir = null)
    {
        if ($dir === null) {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'buildcake-sqlkit-cache';
        }
        
        self::$cacheDir = rtrim($dir, DIRECTORY_SEPARATOR);
        
        // Cria o diretório se não existir
        if (!is_dir(self::$cacheDir)) {
            if (!mkdir(self::$cacheDir, 0755, true)) {
                throw new Exception("Não foi possível criar o diretório de cache: " . self::$cacheDir);
            }
        }
    }
    
    /**
     * Obtém o diretório de cache atual
     * @return string
     */
    public static function getCacheDir()
    {
        if (self::$cacheDir === null) {
            self::setCacheDir();
        }
        return self::$cacheDir;
    }
    
    /**
     * Habilita ou desabilita o cache
     * @param bool $enabled
     */
    public static function setCacheEnabled($enabled)
    {
        self::$cacheEnabled = $enabled;
    }
    
    /**
     * Verifica se o cache está habilitado
     * @return bool
     */
    public static function isCacheEnabled()
    {
        return self::$cacheEnabled;
    }
    
    /**
     * Define a duração do cache em horas
     * @param int $hours Número de horas que o cache deve durar
     */
    public static function setCacheHours($hours)
    {
        self::$cacheHours = max(1, (int)$hours); // Mínimo 1 hora
    }
    
    /**
     * Obtém a duração do cache em horas
     * @return int
     */
    public static function getCacheHours()
    {
        return self::$cacheHours;
    }
    
    /**
     * Gera hash único baseado na query e parâmetros
     * @param string $query Query SQL
     * @param array|null $params Parâmetros da query
     * @return string Hash MD5
     */
    private static function generateHash($query, $params = null)
    {
        $data = $query;
        if ($params !== null && !empty($params)) {
            // Ordena os parâmetros para garantir consistência
            ksort($params);
            $data .= serialize($params);
        }
        return md5($data);
    }
    
    /**
     * Obtém o caminho do diretório da hora atual
     * @return string
     */
    private static function getCurrentHourDir()
    {
        $now = new \DateTime();
        $year = $now->format('Y');
        $month = $now->format('m');
        $day = $now->format('d');
        $hour = $now->format('H');
        
        $baseDir = self::getCacheDir();
        $hourDir = $baseDir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . 
                   $month . DIRECTORY_SEPARATOR . $day . DIRECTORY_SEPARATOR . $hour;
        
        return $hourDir;
    }
    
    /**
     * Cria o diretório da hora atual se não existir
     * @return string Caminho do diretório
     */
    private static function ensureHourDir()
    {
        $hourDir = self::getCurrentHourDir();
        
        if (!is_dir($hourDir)) {
            if (!mkdir($hourDir, 0755, true)) {
                throw new Exception("Não foi possível criar o diretório de cache: " . $hourDir);
            }
            
            // Limpa a pasta da hora anterior quando cria uma nova
            self::cleanPreviousHourDir();
        }
        
        return $hourDir;
    }
    
    /**
     * Remove o diretório da hora anterior
     */
    private static function cleanPreviousHourDir()
    {
        try {
            $now = new \DateTime();
            $now->modify('-1 hour');
            
            $year = $now->format('Y');
            $month = $now->format('m');
            $day = $now->format('d');
            $hour = $now->format('H');
            
            $baseDir = self::getCacheDir();
            $previousHourDir = $baseDir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . 
                              $month . DIRECTORY_SEPARATOR . $day . DIRECTORY_SEPARATOR . $hour;
            
            if (is_dir($previousHourDir)) {
                self::deleteDirectory($previousHourDir);
            }
        } catch (\Exception $e) {
            // Ignora erros na limpeza para não interromper a execução
        }
    }
    
    /**
     * Remove um diretório e todo seu conteúdo recursivamente
     * @param string $dir Caminho do diretório
     */
    private static function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        
        @rmdir($dir);
    }
    
    /**
     * Obtém o caminho completo do arquivo de cache
     * @param string $query Query SQL
     * @param array|null $params Parâmetros da query
     * @return string Caminho do arquivo
     */
    private static function getCacheFilePath($query, $params = null)
    {
        $hash = self::generateHash($query, $params);
        $hourDir = self::ensureHourDir();
        return $hourDir . DIRECTORY_SEPARATOR . $hash . '.cache';
    }
    
    /**
     * Verifica se existe cache válido para a query
     * @param string $query Query SQL
     * @param array|null $params Parâmetros da query
     * @return bool
     */
    public static function hasCache($query, $params = null)
    {
        if (!self::$cacheEnabled) {
            return false;
        }
        
        $cacheFile = self::getCacheFilePath($query, $params);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        // Verifica se o cache ainda é válido (dentro do período configurado)
        $fileTime = filemtime($cacheFile);
        $now = time();
        $maxAge = self::$cacheHours * 3600; // Converte horas para segundos
        
        return ($now - $fileTime) < $maxAge;
    }
    
    /**
     * Obtém o resultado do cache
     * @param string $query Query SQL
     * @param array|null $params Parâmetros da query
     * @return mixed|null Retorna null se não houver cache válido
     */
    public static function getCache($query, $params = null)
    {
        if (!self::hasCache($query, $params)) {
            return null;
        }
        
        $cacheFile = self::getCacheFilePath($query, $params);
        
        try {
            $content = file_get_contents($cacheFile);
            if ($content === false) {
                return null;
            }
            
            $data = unserialize($content);
            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Armazena o resultado no cache
     * @param string $query Query SQL
     * @param array|null $params Parâmetros da query
     * @param mixed $result Resultado a ser armazenado
     * @return bool True se salvou com sucesso
     */
    public static function setCache($query, $params = null, $result = null)
    {
        if (!self::$cacheEnabled) {
            return false;
        }
        
        try {
            $cacheFile = self::getCacheFilePath($query, $params);
            $content = serialize($result);
            
            $saved = file_put_contents($cacheFile, $content, LOCK_EX);
            return $saved !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Limpa todo o cache
     * @return bool
     */
    public static function clearCache()
    {
        try {
            $baseDir = self::getCacheDir();
            if (is_dir($baseDir)) {
                self::deleteDirectory($baseDir);
                return true;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

