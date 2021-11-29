<?php

class Comida{

    public int $id_comida;
    public string $nombreComida, $tipo;
    public float $valor;

    public function __construct($nombreComida, $tipo, $valor, $id_comida = -1){

        $this->nombreComida = $nombreComida;
        $this->tipo = $tipo;
        $this->valor = $valor;
        $this->id_comida = $id_comida;

    }

    public function __get($clave){

        return $this->$clave;
    }

    public function guardarComidaEnDB(){

        $db = DB::getInstance('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        
        $valuesComida = "'{$this->__get('nombreComida')}','{$this->__get('tipo')}','{$this->__get('valor')}'";
        $stdOut->exito = $db->insertObject('comida', 'nombreComida, tipo, valor', $valuesComida);
        
        if ($stdOut->exito) {
            $stdOut->mensaje = "Comida agregada con exito.";
        } else {
            $stdOut->mensaje = "Hubo un error al guardar la Comida";
        }

        return $stdOut;
    }

    public static function traerComidaDeDB($condicion = '')
    {

        $db = DB::getInstance('localhost', 'comandatp', 'root');
        $listadoComidas = $db->selectObject('comida', '*', $condicion);

        return $listadoComidas;
    }

    public static function traerPropiedadDeDB($prop, $condicion){

        $db = DB::getInstance('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        
        $resultado = $db->selectObject('comida', $prop, $condicion . " LIMIT 1")[0];

        return $resultado->$prop;
    }

    public static function getTipoComidaByRol($rol){
     
        $tipoComida = "";
        switch($rol){
            case "cerveceros":$tipoComida = "cervesas";break;
            case "cocineros":$tipoComida = "cocina";break;
            case "bartender":$tipoComida = "tragos";break;
            case "socios":$tipoComida = "cocina";break;
        }

        return $tipoComida;
    }

}

?>