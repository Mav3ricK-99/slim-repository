<?php

class Encuesta{

    public string $codigoPedido, $puntajeServicio, $comentarioServicio;

    public function __construct($codigoPedido, $puntajeServicio, $comentarioServicio){

        $this->codigoPedido = $codigoPedido;
        $this->puntajeServicio = $puntajeServicio;
        $this->comentarioServicio = $comentarioServicio;
    }

    public function __get($clave){

        return $this->$clave;
    }

    public function guardarEncuestaEnDB(){

        $db = new DB('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        
        $valuesEncuesta = "'{$this->__get('codigoPedido')}','{$this->__get('puntajeServicio')}','{$this->__get('comentarioServicio')}'";
        $stdOut->exito = $db->insertObject('encuesta', 'codigoPedido, puntajeServicio, comentarioServicio', $valuesEncuesta);
       
        if ($stdOut->exito) {
            $stdOut->mensaje = "Encuesta agregado con exito.";
        } else {
            $stdOut->mensaje = "Hubo un error al guardar la Encuesta";
        }

        return $stdOut;
    }

    public static function traerEncuestasDeDB($condicion = '')
        {
    
            $db = new DB('localhost', 'comandatp', 'root');
            $listadoMesas = $db->selectObject('encuesta', '*', $condicion);
    
            return $listadoMesas;
        }

    public static function traerMejoresEncuestas(){

        $encuestas = Encuesta::traerEncuestasDeDB("WHERE puntajeServicio > 2");

        if(empty($encuestas)){

            $encuestas = new stdClass();
            $encuestas->mensaje = "No se encontraron buenas encuestas";
        }
        return $encuestas;
    }


}

?>