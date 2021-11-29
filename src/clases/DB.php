<?php
/*
                funciones que cuando tomen de la db no formen un objeto
*/
class DB{

    private static $inicializado = false;
    private static $conexion;
    private static $sentencia;
    public static $instance;

    public function __construct($host, $db, $user, $pass = '')
    {
        self::$instance = DB::init($host, $db, $user, $pass);
    }

    private static function init($host, $db, $user, &$pass) {
        if (self::$inicializado) {
            return;
        }
        try{
            DB::$conexion = $pass == '' ? new PDO('mysql:host='. $host .';dbname='. $db, $user, $pass) : new PDO('mysql:host='. $host .';dbname='. $db, $user, '');
        }catch(PDOException $e){
            echo "Error al conectarse a la Base de datos " . $e->getMessage() . "<br>";
            die();
        }
        self::$inicializado = true; 
    }

    public static function getInstance($host, $db, $user, $pass = ""){
        if (self::$instance === null) {
            self::$instance = new self($host, $db, $user, $pass);
        }
            
        return self::$instance;
    }

    private function prepareSQL($sql){
        if(DB::$sentencia == null || DB::$sentencia->queryString != $sql){
            DB::$sentencia = DB::$conexion->prepare($sql);
        }
    }

    public function insertObject($nombreClase, $columnas, $values){

        $columnas = ' ('.$columnas.') ';
        $values = 'VALUES ('.$values.')';
        $res = false;
        try{
        $sql = 'INSERT INTO '. $nombreClase . $columnas . $values;
        
        //echo $sql;
        $this->prepareSQL($sql);

        $res = DB::$sentencia->execute();
        }catch(PDOException $e){
            echo "Hubo un error al ingresar un registro en la tabla: ". $e->getMessage(). "<br>";
        }finally{
            return $res;
        }
    }

    public function updateObject($tabla, $set, $condicion, $params = ''){

        $sql = "UPDATE ${tabla} SET ${set} ${condicion}";
        $this->prepareSQL($sql);

        $stdOut = new stdClass();

        //echo $sql.'<br>';
        $res = false;
        try{
            $res = $params != '' ? DB::$sentencia->execute($params) : DB::$sentencia->execute();
        }catch(PDOException $e){
            $stdOut->exception = "Hubo un error al actualizar el registro en la tabla: ". $e->getMessage(). "<br>";
        }finally{
            $stdOut->executeCode = $res;
            return $stdOut;
        }
    }

    public function selectObject($nombreClase, $campos, $condicion = '', $params = ''){

        $sql = 'SELECT '. $campos . ' FROM ' . $nombreClase . ' ' . $condicion;
        
        //echo $sql;
        $this->prepareSQL($sql);
        $listaObjetos = array();
        try{
            $params != '' ? DB::$sentencia->execute($params) : DB::$sentencia->execute();
            
            while($objeto = DB::$sentencia->fetch(PDO::FETCH_OBJ)){
                $listaObjetos[] = $objeto;
            }
        }catch(PDOException $e){
            echo "Hubo un error al generar la consulta: ". $e->getMessage(). "<br>";
            //echo $e->getTrace(); Trazado de exceptiones
        }
        return $listaObjetos;
      
    }

    public function selectAllObjects($nombreTabla, $nombreClase, $condicion = ''){

        $this->prepareSQL('SELECT * FROM '.$nombreTabla. ' '. $condicion);
        
        $listaObjetos = array();
        try{
            DB::$sentencia->execute();
            //Parametros opcionales en el constructor de la clase $nombreTabla
            //Al hacer fetch con PDO::FETCH_CLASS en una clase que hereda de 'tal' esa clase 'tal' tiene que tener propiedades Protected
            //Necesita constructor vacio!! (args)
            
            while($objeto = DB::$sentencia->fetchAll(PDO::FETCH_CLASS, $nombreClase)){
                $listaObjetos = $objeto;
            }
        }catch(PDOException $e){
            echo "Hubo un error al generar la consulta: ". $e->getMessage(). "<br>";
        }
        return $listaObjetos;
    }

    public function delete($nombreClase, $condicion, $params = ''){

        $sql = "DELETE FROM ${nombreClase} ${condicion}";
        
        //echo $sql;
        $eliminar = false; $excepcion = "";
        $this->prepareSQL($sql);
        
        try{
        $params != '' ? $eliminar = DB::$sentencia->execute($params) : $eliminar = DB::$sentencia->execute();
        
        }catch(PDOException $e){
            $excepcion = "Hubo un error al generar la consulta: ". $e->getMessage();
        }finally{

            $std = new stdClass();
            $std->executeCode = $eliminar;
            $std->excepcion = $excepcion;
            return $std;
        }
    }

    public function execSQL($sql){

        $excepcion = "";
        $this->prepareSQL($sql);
        
        $sqlExec = "";
        try{
            $sqlExec = DB::$sentencia->execute();
        
        }catch(PDOException $e){
            $excepcion = "Hubo un error al generar la consulta: ". $e->getMessage();
        }finally{

            $std = new stdClass();
            $std->executeCode = $sqlExec;
            $std->excepcion = $excepcion;
            return $std;
        }
    }

}


?>