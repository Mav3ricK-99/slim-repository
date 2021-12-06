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

    public function suspenderEmpleado(int $id, $suspender = 1)
    {
        $db = DB::getInstance('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        
        $set = "suspendido = {$suspender}";
        $resultado = $db->updateObject("empleado", $set, "WHERE id_empleado = '{$id}'");

        if ($resultado->executeCode) {
            $stdOut->exito = true;
            if($suspender == 1){
                $mensajeLog = "suspendido";
                $stdOut->mensaje = "Empleado suspendido exitosamente";
            }else{
                $mensajeLog = "recuperado";
                $stdOut->mensaje = "Empleado recuperado exitosamente";
            }

            $codigoEmpleado = Empleado::getCodigoEmpleadoById($id);
            Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ({$this->rol}) ha {$mensajeLog} al empleado codigo {$codigoEmpleado}");

        } else {
            $stdOut->exito = false;
            $stdOut->mensaje = $resultado->exception;
        }
        return $stdOut;
    }

    public function habilitarEmpleado(int $id)
    {
        return $this->suspenderEmpleado($id, 0);
    }

    public function eliminarEmpleado(int $id, $eliminar = 1)
    {
        $db = DB::getInstance('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        
        $set = "eliminado = {$eliminar}";
        $resultado = $db->updateObject("empleado", $set, "WHERE id_empleado = '{$id}'");

        if ($resultado->executeCode) {
            $stdOut->exito = true;
            if($eliminar == 1){
                $mensajeLog = "eliminado";
                $stdOut->mensaje = "Empleado eliminado exitosamente";
            }else{
                $mensajeLog = "recuperado";
                $stdOut->mensaje = "Empleado recuperado exitosamente";
            }

            $codigoEmpleado = Empleado::getCodigoEmpleadoById($id);
            Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ha {$mensajeLog} al empleado codigo {$codigoEmpleado}");
        } else {
            $stdOut->exito = false;
            $stdOut->mensaje = $resultado->exception;
        }
        return $stdOut;
    }

    public function recuperarEmpleado(int $id)
    {
        return $this->eliminarEmpleado($id, 0);
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

    public static function getCodigoEmpleadoById($id){

        $db = DB::getInstance('localhost', 'comandatp', 'root');
        $listadoEmpleado = $db->selectObject('empleado', 'codigoEmpleado', "WHERE id_empleado = {$id} LIMIT 1");
        if(empty($listadoEmpleado)){
            return "EMPLEADO INEXISTENTE";
        }

        return $listadoEmpleado[0]->codigoEmpleado;
    }

    public static function getNombreEmpleadoById($id){

        $db = DB::getInstance('localhost', 'comandatp', 'root');
        $listadoEmpleado = $db->selectObject('empleado', 'nombre', "WHERE id_empleado = {$id} LIMIT 1");
        if(empty($listadoEmpleado)){
            return "EMPLEADO INEXISTENTE";
        }

        return $listadoEmpleado[0]->nombre;
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
                $codigoMesa = Mesa::getCodigoMesaById($pedido->idMesa);
                Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ({$this->rol}) ha realizado el pedido de " . json_encode($pedido->pedidoPorEmpleado) . " en la mesa codigo: {$codigoMesa}");
                Mesa::cambiarEstadoMesa($pedido->idMesa, "con cliente esperando pedido");
            }
        }
        
        return $stdOut;
    }

    public function tomarPedido($nPedido, $tiempoEstimado){

        $idEmpleado = $this->getIdEmpleado();
        $stdOut = new stdClass();

        $ahora = date("Y-m-d H:i:s");

        $tipoComidaBuscado = Comida::getTipoComidaByRol($this->rol);
        $pedido = PedidoPorEmpleado::traerPedidoPorEmpleadoDeDB("INNER JOIN pedido ON pedidoxempleado.id_pedido = pedido.id_pedido INNER JOIN comida ON pedidoxempleado.id_comida = comida.id_comida WHERE tipo = '{$tipoComidaBuscado}' AND codigoPedido = '{$nPedido}' AND pedidoxempleado.estadoPedido = 'aun sin tomar' LIMIT 1");
        
        if(!$pedido || $pedido == null || !isset($pedido)){
            $stdOut->mensaje = "No se encontraron pedidos de caracter '{$this->rol}' con el numero de Pedido '{$nPedido}' o fueron todos los pedidos tomados";
            return $stdOut;
        }

        $pedido = $pedido[0];

        $set = "tiempoEstimado = '{$tiempoEstimado}', id_empleado = '{$idEmpleado}', estadoPedido = 'en preparacion', tiempoPedidoTomado = '{$ahora}'";
        $resultadoModPedPorEmpleado = PedidoPorEmpleado::modificarPedidoPorEmpleadoEnDB($pedido->id_pedidoxEmpleado, $set);

        $mayorTiempoEspera = PedidoPorEmpleado::getMayorTiempoEspera($nPedido);
        if($tiempoEstimado > $mayorTiempoEspera){
            $mayorTiempoEspera = $tiempoEstimado;
        }

        $setPedido = "estadoPedido = 'en preparacion', tiempoEstimado = '{$mayorTiempoEspera}', tiempoPedidoTomado = '{$ahora}'";
        $resultadoModPedido = Pedido::modificarPedidoEnDB($pedido->id_pedido, $setPedido);

        if($resultadoModPedPorEmpleado->executeCode && $resultadoModPedido->executeCode){
        
           $pedidoStdClass = new stdClass();
           $pedidoStdClass->cantidadComida = $pedido->cantidadComida;
           $pedidoStdClass->nombreComida = $pedido->nombreComida;
           $codigoMesa = Mesa::getCodigoMesaById($pedido->id_mesa);

           $stdOut->mensaje ="Usted ha tomado el Pedido {$pedido->nombreComida} del Pedido N° {$nPedido}";
           Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ({$this->rol}) ha tomado el pedido de " . json_encode($pedidoStdClass) . " en la mesa codigo: {$codigoMesa}");
        }else{
           $stdOut->mensaje ="Hubo un problema al tomar el pedido {$pedido->nombreComida}";
        }

        return $stdOut;
    }

    public function finalizarPedido($nPedido){

        $idEmpleado = $this->getIdEmpleado();
        $stdOut = new stdClass();

        $ahora = date("Y-m-d H:i:s");

        $tipoComidaBuscado = Comida::getTipoComidaByRol($this->rol);
        $pedidos = PedidoPorEmpleado::traerPedidoPorEmpleadoDeDB("INNER JOIN pedido ON pedidoxempleado.id_pedido = pedido.id_pedido INNER JOIN comida ON pedidoxempleado.id_comida = comida.id_comida WHERE tipo = '{$tipoComidaBuscado}' AND id_empleado = '{$idEmpleado}' AND codigoPedido = '{$nPedido}' AND pedidoxempleado.estadoPedido = 'en preparacion'");
        
        //var_dump($pedidos);
        
        if(!$pedidos || $pedidos == null || !isset($pedidos)){
            $stdOut->mensaje = "No se encontraron pedidos en preparacion con el numero de Pedido '{$nPedido}' o todos los pedidos ya se encuentran para servir";
            return $stdOut;
        }
        $pedido = $pedidos[0];

        $set = "estadoPedido = 'listo para servir', tiempoPedidoFinalizado = '{$ahora}'";
        $resultadoModPedPorEmpleado = PedidoPorEmpleado::modificarPedidoPorEmpleadoEnDB($pedido->id_pedidoxEmpleado, $set);

        $pedidos[0]->estadoPedido = "listo para servir";
        $estanTodosListos = PedidoPorEmpleado::estanTodosLosPedidosListos($pedidos);
        
        if($estanTodosListos){

            $setPedido = "estadoPedido = 'listo para servir', tiempoPedidoFinalizado = '{$ahora}'";
            $resultadoModPedido = Pedido::modificarPedidoEnDB($pedido->id_pedido, $setPedido);
        }
        
        if($resultadoModPedPorEmpleado->executeCode){
            $stdOut->mensaje ="Usted ha completado el pedido {$pedido->nombreComida} del Pedido N° {$nPedido}";
            
            $pedidoStdClass = new stdClass();
            $pedidoStdClass->cantidadComida = $pedido->cantidadComida;
            $pedidoStdClass->nombreComida = $pedido->nombreComida;
            $codigoMesa = Mesa::getCodigoMesaById($pedido->id_mesa);

            Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ({$this->rol}) ha finalizado el pedido de " . json_encode($pedidoStdClass) . " en la mesa codigo: {$codigoMesa}");
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
        
            $codigoMesa = Mesa::getCodigoMesaById($pedido->id_mesa);

            Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ({$this->rol}) ha servido el pedido ({$nPedido}) de " . json_encode($pedido) . " en la mesa codigo: {$codigoMesa}");
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

        $codigoMesa = Mesa::getCodigoMesaById($pedido->id_mesa);
        Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ({$this->rol}) ha cobrado el pedido ({$nPedido}) de en la mesa codigo: {$codigoMesa} - Valor total de {$totalPedido}");

        $pedido->codigoMesa = $codigoMesa;
        $pedido->totalPedido = $totalPedido;
        Logger::escribir("../src/ventas/ventas.txt", json_encode($pedido), false);

        return $stdOut;
    }

    public function guardarComida(Comida $nuevaComida){

        $stdOut = $nuevaComida->guardarComidaEnDB($nuevaComida);
        if($stdOut->exito){
            Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ha guardado una nueva comida ({$nuevaComida->nombreComida}) valor: {$nuevaComida->valor}");
        }

        return $stdOut;
    }

    public function agregarMesa(Mesa $mesa){

        $stdOut = $mesa->guardarMesaEnDB($mesa);
        if($stdOut->executeCode){
            Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ha agregado una nueva mesa ({$mesa->lugarMesa})");
        }

        return $stdOut;
    }

    public function liberarMesa($idMesa){

        $stdOut = Mesa::cambiarEstadoMesa($idMesa, "Libre");
        if($stdOut->executeCode){
            Logger::escribir("../src/logs/accionesEmpleado.txt", "{$this->nombre} ha liberado una mesa");
        }

        return $stdOut;
    }

}

?>