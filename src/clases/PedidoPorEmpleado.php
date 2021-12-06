<?php

class PedidoPorEmpleado{

    public int $id_pedido, $id_comida, $id_empleado, $cantidad, $tiempoEstimado; //En sec;
    public string $estadoPedido;

    public function __construct(int $id_comida, int $cantidad){

        $this->id_comida = $id_comida;
        $this->cantidad = $cantidad;
        $this->tiempoEstimado = -1;
        
        $this->estadoPedido = "aun sin tomar";
    }

    public function __get($clave){
        return $this->$clave;
    }

    public function guardarPedidoPorEmpleadoEnDB(){

        $db = new DB('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        
        $valuesPedidoPorEmpleado = "'{$this->__get('id_comida')}', '{$this->__get('cantidad')}', '{$this->__get('estadoPedido')}', '{$this->__get('tiempoEstimado')}', last_insert_id()";
        $stdOut->exito = $db->insertObject('pedidoxempleado', 'id_comida, cantidadComida, estadoPedido, tiempoEstimado, id_pedido', $valuesPedidoPorEmpleado);
        
        if (!$stdOut->exito) {
            $stdOut->mensaje = "Hubo un error al guardar la ". get_class($this);
        }
        return $stdOut;
    }

    public static function traerPedidoPorEmpleadoDeDB($condicion = ''){

        $db = new DB('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        
        $listadoPedidos = $db->selectObject('pedidoxempleado', '*', $condicion);

        //$listadoPedidos = $db->selectObject('pedidoxempleado', '*', "INNER JOIN pedido AS p ON pedidoxempleado.id_pedido = p.id_pedido");
        return $listadoPedidos;
    }

    public static function modificarPedidoPorEmpleadoEnDB($id, $set)
    {
        $db = DB::getInstance('localhost', 'comandatp', 'root');

        $resultado = $db->updateObject('pedidoxempleado', $set, "WHERE id_pedidoxEmpleado = '{$id}'");

        return $resultado;
    }

    public static function listarPedidosPorEmpleado($pedidosPorEmpleado){

        $htmlLista = "<ul>";
        foreach($pedidosPorEmpleado as $pedido){
            $htmlLista .= "<li>{$pedido->cantidadComida} - {$pedido->nombreComida}</li>";
        }
        $htmlLista .= "</ul>";

        return $htmlLista;
    }

    public static function estanTodosLosPedidosListos($pedidos){

        $flagTodosListos = true;
        foreach($pedidos as $pedido){
            //echo $pedido->estadoPedido;
            if($pedido->estadoPedido != "listo para servir"){
                $flagTodosListos = false;
            }
        }
        
        return $flagTodosListos;
    }

    public static function pendientesPorRol($rol){

        $tipoComidaBuscado = Comida::getTipoComidaByRol($rol);

        $condicion = "";
        if($rol != "socios"){
            $condicion = "tipo = '{$tipoComidaBuscado}' AND" ;
        }
        $pedidos = PedidoPorEmpleado::traerPedidoPorEmpleadoDeDB("INNER JOIN pedido ON pedidoxempleado.id_pedido = pedido.id_pedido INNER JOIN comida ON pedidoxempleado.id_comida = comida.id_comida WHERE {$condicion} pedidoxempleado.estadoPedido = 'en preparacion'");
    
        return $pedidos;
    }

    public static function getMayorTiempoEspera($nPedido){

        $listaPedidos = PedidoPorEmpleado::traerPedidoPorEmpleadoDeDB("INNER JOIN pedido ON pedidoxempleado.id_pedido = pedido.id_pedido WHERE pedido.codigoPedido = '{$nPedido}' AND pedidoxempleado.estadoPedido = 'en preparacion'");
    
        $maximoTiempoEspera = 0;
        foreach($listaPedidos as $pedido){

            if($pedido->tiempoEstimado > $maximoTiempoEspera){
                $maximoTiempoEspera = $pedido->tiempoEstimado;
            }
        }

        return $maximoTiempoEspera;
    }

    public static function getPedidosTardios(){

        $pedidos = PedidoPorEmpleado::traerPedidoPorEmpleadoDeDB("WHERE tiempoPedidoFinalizado != 'NULL'");
        $pedidosTardios = [];


        foreach($pedidos as $pedido){

            $dateinsec=strtotime($pedido->tiempoPedidoTomado);
            $nuevaFecha=$dateinsec+$pedido->tiempoEstimado;

            if(strtotime($pedido->tiempoPedidoFinalizado) > $nuevaFecha){
                $pedido->fechaEstimada = date("Y-m-d H:i:s", $nuevaFecha);
                array_push($pedidosTardios, $pedido);
            }
        }

        return $pedidosTardios;
    }
}


?>