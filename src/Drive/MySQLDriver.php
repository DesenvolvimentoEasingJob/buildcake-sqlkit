<?php

namespace BuildCake\SqlKit\Drive;

use PDO;
use PDOException;

class MySQLDriver {

    
    public static  $handle;
    public static  $conection;
    public static  $stmt; 

    public static function GetIstance($object = null) {
        if($object === null){
            $object = $_ENV;
        }
        
        $host =  $object['DB_HOST'];
        $name =  $object['DB_NAME'];
        $user =  $object['DB_USER'];
        $pass =  $object['DB_PASS'];
        $port = isset($object['DB_PORT']) ? $object['DB_PORT'] : 3306;
        
        try {
            if(!isset(self::$conection)){
                self::$handle  = new PDO("mysql:host={$host};dbname={$name};port={$port};",$user,$pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
                self::$conection = new MySQLDriver();
            }
        } catch (\Throwable $exception) {
            throw new \Exception('Erro ao se conectar com o servidor: ' . $exception->getMessage());
        }
        return self::$conection;
    }

    function PureCommand($expressao){
        try {
            return self::$handle->query($expressao);
        } catch (PDOException $e) {
            throw new \Exception("Erro na execução do comando SQL: " . $e->getMessage());
        }
    }

    function Delete($expressao){
        return  self::$handle->query($expressao);
    }
    
    function Update($expressao){
        $return =  self::$handle->prepare($expressao);
        $return->execute();
        return  $return->fetchAll(PDO::FETCH_ASSOC);
    }

    function Select($expressao){
        $return =  self::$handle->prepare($expressao);
        $return->execute();
        return  $return->fetchAll(PDO::FETCH_ASSOC);
    }

    function SelectParms($object,$expressao){
        self::$stmt =  self::$handle->prepare($expressao);

        foreach ($object as  $value) {
            self::$stmt->bindParam(":{$value["COLUMN_NAME"]}", $value["VALUE"]);
        }

        self::$handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            self::$stmt->execute();
        } catch (PDOException $e) {
            throw new \Exception("Erro na consulta: " . $e->getMessage());
        }
        
        return  self::$stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function InsertParms($object,$expressao){
        self::$stmt = self::$handle->prepare($expressao);

        foreach ($object as  $value) {
                self::$stmt->bindParam(":{$value["COLUMN_NAME"]}", $value["VALUE"]);
        }

         self::$stmt->execute();

        return self::$handle->lastInsertId();
    }

    function UpdateParms($object,$expressao){
        self::$stmt = self::$handle->prepare($expressao);

        foreach ($object as  $value) {
            // Tratamento especial para campos BIT/BOOLEAN
            if (is_bool($value["VALUE"]) || (is_int($value["VALUE"]) && ($value["VALUE"] === 0 || $value["VALUE"] === 1))) {
                // Para campos BIT, usar PDO::PARAM_INT
                self::$stmt->bindParam(":{$value["COLUMN_NAME"]}", $value["VALUE"], PDO::PARAM_INT);
            } else {
                self::$stmt->bindParam(":{$value["COLUMN_NAME"]}", $value["VALUE"]);
            }
        }

         self::$stmt->execute();

        return self::$stmt->rowCount();
    }


    function SelectOnly($expressao){
            $retorno = self::$handle->query($expressao)->fetchAll(PDO::FETCH_ASSOC);

            if(count($retorno) > 1){
                return $retorno[0];
            }
    
            if($retorno == false){
                return null;
            }

        return $retorno[0];
    }
    
    function Insert($expressao){
        self::$stmt = self::$handle->prepare($expressao);
        self::$stmt->execute();

        return self::$handle->lastInsertId();
    }

}
