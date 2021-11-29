<?php

class Empleado{

    public int $id_empleado;
    public string $nombre, $rol;
    public ?Pedido $pedidos = null;
    public bool $suspendido, $eliminado;

    public function __construct($nombre, $rol){

        $this->nombre = $nombre;
        $this->rol = $rol;

        $this->suspendido = false;
        $this->eliminado = false;
        $this->codigoEmpleado = 0;
    }

    public function __get($clave){

        return $this->$clave;
    }

    public function guardarEmpleadoEnDB(){

        $db = new DB('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        $stdOut2 = new stdClass();
        
        $valuesEmpleado = "'{$this->__get('nombre')}','{$this->__get('rol')}','{$this->__get('codigoEmpleado')}','{$this->__get('suspendido')}', '{$this->__get('eliminado')}'";
        $stdOut->exito = $db->insertObject('empleado', 'nombre, rol, codigoEmpleado, suspendido, eliminado', $valuesEmpleado);
        $stdOut2->executeCode = $db->execSQL("UPDATE empleado SET codigoEmpleado = ((last_insert_id() * 19379 + 62327) % 89989) + 10000 WHERE id_empleado = last_insert_id()");
        $empleado = $db->selectObject("empleado", "codigoEmpleado","WHERE id_empleado = last_insert_id() LIMIT 1")[0];

        $stdOut->codigoEmpleado = $empleado->codigoEmpleado;
        if ($stdOut->exito && $stdOut2->executeCode && $stdOut->codigoEmpleado != 0) {
            $stdOut->mensaje = "Empleado agregado con exito.";
        } else {
            $stdOut->mensaje = "Hubo un error al guardar la Empleado";
        }

        return $stdOut;
    }

    public static function traerEmpleadosDeDB($condicion = '')
    {
        $db = new DB('localhost', 'comandatp', 'root');
        $listadoEmpleados = $db->selectObject('empleado', '*', $condicion);

        return $listadoEmpleados;
    }

    public function getIdEmpleado(){

        $db = new DB('localhost', 'comandatp', 'root');
        $empleado = Empleado::traerEmpleadosDeDB("WHERE nombre = '{$this->nombre}' AND rol = '{$this->rol}' LIMIT 1")[0];
        
        return $empleado->id_empleado;
    }

    public function realizarPedido(Pedido $pedido, $foto = null){

        $stdOut = new stdClass();
        if(Mesa::getEstadoMesaById($pedido->idMesa) != "Libre"){
            $stdOut->mensaje = "La mesa no se encuentra Libre";
        }else{
            if($foto != null){
                $pedido->guardarImagenPedido($foto);
            }
            $stdOut = $pedido->guardarPedidoEnDB();
            
            if($stdOut->exito){
                Mesa::cambiarEstadoMesa($pedido->idMesa, "con cliente esperando pedido");
            }
        }
        
        return $stdOut;
    }

    public function tomarPedido($nPedido, $tiempoEstimado){

        $idEmpleado = $this->getIdEmpleado();
        $stdOut = new stdClass();

        $tipoComidaBuscado = Comida::getTipoComidaByRol($this->rol);
        $pedido = PedidoPorEmpleado::traerPedidoPorEmpleadoDeDB("INNER JOIN pedido ON pedidoxempleado.id_pedido = pedido.id_pedido INNER JOIN comida ON pedidoxempleado.id_comida = comida.id_comida WHERE tipo = '{$tipoComidaBuscado}' AND codigoPedido = '{$nPedido}' AND pedidoxempleado.estadoPedido = 'aun sin tomar' LIMIT 1");
        
        if(!$pedido || $pedido == null || !isset($pedido)){
            $stdOut->mensaje = "No se encontraron pedidos de caracter '{$this->rol}' con el numero de Pedido '{$nPedido}' o fueron todos los pedidos tomados";
            return $stdOut;
        }

        $pedido = $pedido[0];
        //var_dump($pedido);

        $set = "tiempoEstimado = '{$tiempoEstimado}', id_empleado = '{$idEmpleado}', estadoPedido = 'en preparacion'";
        $resultadoModPedPorEmpleado = PedidoPorEmpleado::modificarPedidoPorEmpleadoEnDB($pedido->id_pedidoxEmpleado, $set);

        $tiempoEstimadoPedido = Pedido::getTiempoEstimadoEnDB($nPedido);
        $tiempoEstimadoPedido += $tiempoEstimado;

        $setPedido = "estadoPedido = 'en preparacion', tiempoEstimado = '{$tiempoEstimadoPedido}'";
        $resultadoModPedido = Pedido::modificarPedidoEnDB($pedido->id_pedido, $setPedido);

        if($resultadoModPedPorEmpleado->executeCode && $resultadoModPedido->executeCode){
           $stdOut->mensaje ="Usted ha tomado el Pedido {$pedido->nombreComida} del Pedido N° {$nPedido}";
        }else{
           $stdOut->mensaje ="Hubo un problema al tomar el pedido {$pedido->nombreComida}";
        }

        return $stdOut;
    }

    public function finalizarPedido($nPedido){

        $idEmpleado = $this->getIdEmpleado();
        $stdOut = new stdClass();

        $tipoComidaBuscado = Comida::getTipoComidaByRol($this->rol);
        $pedidos = PedidoPorEmpleado::traerPedidoPorEmpleadoDeDB("INNER JOIN pedido ON pedidoxempleado.id_pedido = pedido.id_pedido INNER JOIN comida ON pedidoxempleado.id_comida = comida.id_comida WHERE tipo = '{$tipoComidaBuscado}' AND id_empleado = '{$idEmpleado}' AND codigoPedido = '{$nPedido}' AND pedidoxempleado.estadoPedido = 'en preparacion'");
        
        //var_dump($pedidos);
        
        if(!$pedidos || $pedidos == null || !isset($pedidos)){
            $stdOut->mensaje = "No se encontraron pedidos en preparacion con el numero de Pedido '{$nPedido}' o todos los pedidos ya se encuentran para servir";
            return $stdOut;
        }
        $pedido = $pedidos[0];

        $set = "tiempoEstimado = '0', estadoPedido = 'listo para servir'";
        $resultadoModPedPorEmpleado = PedidoPorEmpleado::modificarPedidoPorEmpleadoEnDB($pedido->id_pedidoxEmpleado, $set);

        $pedidos[0]->estadoPedido = "listo para servir";
        $estanTodosListos = PedidoPorEmpleado::estanTodosLosPedidosListos($pedidos);
        
        if($estanTodosListos){

            $setPedido = "estadoPedido = 'listo para servir', tiempoEstimado = '0'";
            $resultadoModPedido = Pedido::modificarPedidoEnDB($pedido->id_pedido, $setPedido);
        }
        
        if($resultadoModPedPorEmpleado->executeCode){
            $stdOut->mensaje ="Usted ha completado el pedido {$pedido->nombreComida} del Pedido N° {$nPedido}";
        }else{
            $stdOut->mensaje ="Hubo un problema al notificar el completado del pedido {$pedido->nombreComida}";
        }
        
        return $stdOut;
    }

    public function servirPedido($nPedido){

        $stdOut = new stdClass();
        $pedidos = Pedido::traerPedidosDeDB("WHERE codigoPedido = '{$nPedido}' AND pedido.estadoPedido = 'listo para servir' LIMIT 1");
        
        if(!$pedidos || $pedidos == null || !isset($pedidos)){
            $stdOut->mensaje = "No se encontraron pedidos listos para servir con el numero de Pedido '{$nPedido}' o todos los pedidos ya fueron servidos";
            return $stdOut;
        }
        $pedido = $pedidos[0];

        $resultadoCambioEstadoMesa = Mesa::cambiarEstadoMesa($pedido->id_mesa, "con cliente comiendo");

        if($resultadoCambioEstadoMesa->executeCode){
            
            $stdOut->mensaje = "La mesa {$pedido->codigoMesa} con el pedido {$nPedido} ahora se encuentra comiendo";
        }

        return $stdOut;
    }

    public function cobrarPedido($nPedido){

        $stdOut = new stdClass();
        $pedidos = Pedido::traerPedidosDeDB("WHERE codigoPedido = '{$nPedido}' AND pedido.estadoPedido = 'listo para servir' AND mesa.estadoMesa = 'con cliente comiendo' LIMIT 1");
        
        if(!$pedidos || $pedidos == null || !isset($pedidos)){
            $stdOut->mensaje = "No se encontraron pedidos con clientes comiendo '{$nPedido}' o la mesa fue cobrada";
            return $stdOut;
        }
        $pedido = $pedidos[0];

        $totalPedido = Pedido::getTotalDeUnPedido($pedido);
        $stdOut->mensaje = "Pedido facturado, total del pedido: {$totalPedido}, nombre comprador: {$pedido->nombreCliente}";
        Mesa::cambiarEstadoMesa($pedido->id_mesa, "con cliente pagando");
        Pedido::modificarPedidoEnDB($pedido->id_pedido, "totalFacturado = {$totalPedido}");

        return $stdOut;
    }

}

?>