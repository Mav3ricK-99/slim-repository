<?php
use League\Csv\Reader;
use League\Csv\Writer;

class Pedido{

    public int $id, $idMesa, $codigoPedido, $tiempoEstimado; //En sec;
    public string $nombreCliente, $estadoPedido, $fotoPedido;
    public array $pedidoPorEmpleado;
    public float $totalFacturado;

    //Hacer pedido
    //$_POST['comidas'] == array de JSON
    //Verificar que exista la comida (middleware) Y validar Mesa
    //Crear objeto de Pedido pasandole $comidas (stdClass)
    //Armar PedidoPorEmpleado en este constructor por cada array de Comidas

    public function __construct(string $nombreCliente, int $idMesa, array $comidas){

        $this->nombreCliente = $nombreCliente;
        $this->idMesa = $idMesa;

        $this->estadoPedido = "aun sin tomar";
        $this->totalFacturado = -1;
        $this->codigoPedido = -1;

        $this->tiempoEstimado = 0;
        $this->fotoPedido = "";
        $this->pedidoPorEmpleado = array();

        foreach($comidas as $comida){
            array_push($this->pedidoPorEmpleado, new PedidoPorEmpleado($comida->id_comida, $comida->cantidad));
        }

        //var_dump($this);
    }

    public function __get($clave){
        return $this->$clave;
    }

    public function guardarPedidoEnDB(){

        $db = new DB('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        $stdOut2 = new stdClass();
        
        $valuesPedido = "'{$this->__get('nombreCliente')}', '{$this->__get('estadoPedido')}', '{$this->__get('tiempoEstimado')}', '{$this->__get('totalFacturado')}','{$this->__get('codigoPedido')}', '{$this->__get('idMesa')}', '{$this->__get('fotoPedido')}'";
        $stdOut->exito = $db->insertObject('pedido', 'nombreCliente, estadoPedido, tiempoEstimado, totalFacturado, codigoPedido, id_mesa, fotoPedido', $valuesPedido);

        $stdOut2->executeCode = $db->execSQL("UPDATE pedido SET codigoPedido = ((last_insert_id() * 19379 + 62327) % 89989) + 10000 WHERE id_pedido = last_insert_id()");
        $pedido = $db->selectObject("pedido", "codigoPedido","WHERE id_pedido = last_insert_id() LIMIT 1")[0];
        
        $stdOut->codigoPedido = $pedido->codigoPedido;
        foreach($this->pedidoPorEmpleado as $comida){
            $comida->guardarPedidoPorEmpleadoEnDB();
        }
        
        if ($stdOut->exito && $stdOut2->executeCode && isset($stdOut->codigoPedido)) {

            $stdOut->mensaje = get_class($this) ." agregado con exito.";
        } else {
            $stdOut->mensaje = "Hubo un error al guardar el ". get_class($this);
        }

        return $stdOut;
    }

    public static function traerPedidosDeDB($condicion = ''){

        $db = new DB('localhost', 'comandatp', 'root');
        $stdOut = new stdClass();
        
        $listadoPedidos = $db->selectObject('pedido', '*', "INNER JOIN (SELECT id_mesa, codigoMesa, lugarMesa, estadoMesa FROM mesa) AS mesa ON pedido.id_mesa = mesa.id_mesa ". $condicion);

        foreach($listadoPedidos as $pedido){
            $pedido->pedidoPorEmpleado = PedidoPorEmpleado::traerPedidoPorEmpleadoDeDB("INNER JOIN (SELECT id_comida, nombreComida, valor FROM comida) AS comida ON pedidoxempleado.id_comida = comida.id_comida WHERE id_pedido = {$pedido->id_pedido}");
        }
        //var_dump($listadoPedidos);

        return $listadoPedidos;
    }

    public static function modificarPedidoEnDB($id, $set){
            $db = DB::getInstance('localhost', 'comandatp', 'root');
    
            $resultado = $db->updateObject('pedido', $set, "WHERE id_pedido = '{$id}'");
    
            return $resultado;
    }

    public function guardarImagenPedido($imagen)
    {
        $ext = explode(".", $imagen->getClientFilename());
        $nPuntoEnNombre = count($ext);
        
        $nombreImagen = "{$this->nombreCliente}.{$ext[$nPuntoEnNombre - 1]}";
        $imagen->moveTo('../src/fotosPedidos/' . $nombreImagen);
        $this->fotoPedido = "./fotosPedidos/" . $nombreImagen;

        return file_exists('../src/fotosPedidos/' . $nombreImagen);
    }

    public static function listarPedidos($pedidos){

        $htmlTabla = '<table>';
       
        $htmlTabla .= "<tr><th>Nombre cliente</th>";
        $htmlTabla .= "<th>Estado pedido</th>";
        
        $htmlTabla .= "<th>Tiempo estimado</th>";
        $htmlTabla .= "<th>Codigo pedido</th>";
        
        $htmlTabla .= "<th>Pedidos</th>";
        $htmlTabla .= "<th>Lugar mesa</th>";
        $htmlTabla .= "<th>Codigo mesa</th></tr>";

        foreach($pedidos as $pedido){

            $htmlTabla .= "<tr><td>".$pedido->nombreCliente."</td>";
            $htmlTabla .= "<td>".$pedido->estadoPedido."</td>";

            if($pedido->estadoPedido != "aun sin tomar"){
                $htmlTabla .= "<td>".$pedido->tiempoEstimado."</td>";
                $htmlTabla .= "<td>".$pedido->codigoPedido."</td>";
            }

            $htmlTabla .= "<td>" .PedidoPorEmpleado::listarPedidosPorEmpleado($pedido->pedidoPorEmpleado) . "</td>";
            $htmlTabla .= "<td>".$pedido->lugarMesa."</td>";
            $htmlTabla .= "<td>".$pedido->codigoMesa."</td></tr>";
        }

        $htmlTabla .= "</table>";
        return $htmlTabla;
    }

    public static function getTiempoEstimadoEnDB($nPedido){

        $db = DB::getInstance('localhost', 'comandatp', 'root');

        $pedido = $db->selectObject('pedido', '*', "WHERE codigoPedido = '{$nPedido}' LIMIT 1");
        if(!isset($pedido)){
            return 0;
        }

        return $pedido[0]->tiempoEstimado;
    }
    
    public static function tiempoRestantePedidoMesa($idMesa, $codigoPedido){

        $stdOut = new stdClass();
        $pedido = Pedido::traerPedidosDeDB("WHERE mesa.codigoMesa = '{$idMesa}' AND pedido.codigoPedido = '{$codigoPedido}'");
        if(!isset($pedido) || $pedido == null){
            $stdOut->mensaje = "No existen pedidos para esa mesa";
        }else{
            $pedido = $pedido[0];
            if($pedido->estadoPedido == "listo para servir"){
                $stdOut->mensaje = "El pedido ya esta completado, llegara en unos instantes";
            }else{
                $stdOut->mensaje = "El tiempo estimado de preparacion del pedido es de: {$pedido->tiempoEstimado}";
            }
        }
        
        return $stdOut;
    }

    public static function getTotalDeUnPedido($pedido){

        $totalDeUnPedido = 0;
        foreach($pedido->pedidoPorEmpleado as $pedido){
            $totalDeUnPedido += ($pedido->valor * $pedido->cantidadComida);
        }

        return $totalDeUnPedido;
    }

    public static function getPedidoByCSV($csv){

        $nombreCSV = $csv->getClientFilename();
        $csv->moveTo('../src/csv/' . $nombreCSV);

        $csv = Reader::createFromPath("../src/csv/{$nombreCSV}", 'r');
        $csv->setHeaderOffset(0);
        $csv->setDelimiter(';');

        $records = $csv->getHeader();
        
        $nombreCliente = $records[0];
        $idMesa = $records[1];
        $comidas = json_decode($records[2]);
        //var_dump($records);

        return new Pedido($nombreCliente, $idMesa, $comidas);
    }

    public static function csvPedidosFromDB($pedidos){

        $csv = Writer::createFromString();
        $csv->setDelimiter(';');

        $pedidosArray = array();
        
        foreach($pedidos as $pedido){
            $pedido = (array)$pedido;
            unset($pedido['pedidoPorEmpleado']);

            //var_dump($pedido);
            array_push($pedidosArray, $pedido);
        }

        $csv->insertAll($pedidosArray);

        return $csv->toString(); 
    }

}
?>