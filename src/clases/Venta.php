<?php
class Venta{

    public $pedidoVenta;
    public $encuesta;
    public function __construct($pedidoVenta, $encuesta)
    {
        $this->pedidoVenta = $pedidoVenta;
        $this->encuesta = $encuesta;
    }

    public function guardarVenta(){

        $ventasJSON = (array)json_decode(file_get_contents("../src/ventas/ventas.json"));
        
        if($ventasJSON == null){
            $ventasJSON = array();
        }

        array_push($ventasJSON, $this);
        
        $return = new stdClass();
        if(file_put_contents("../src/ventas/ventas.json" , json_encode($ventasJSON))){
            $return->exito = true;
            $return->mensaje = "Se guardo el la venta exitosamente";
        }else{
            $return->exito = false;
            $return->mensaje = "Hubo un problema al guardar la venta. Checkear ruta";
        }

        return $return;
    }

    public static function tomarVentasJSON(){

        $ventasJSON = json_decode(file_get_contents("../src/ventas/ventas.json"));
        if($ventasJSON == null){
            $ventasJSON = array();
        }
        return $ventasJSON;
    }

}




?>