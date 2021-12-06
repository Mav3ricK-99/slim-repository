<?php

    class Mesa{

        public int $id, $codigoMesa;
        public string $estadoMesa, $lugarMesa;

        public function __construct($lugarMesa)
        {
            $this->lugarMesa = $lugarMesa;
            $this->estadoMesa = "Libre";
        }

        public function __get($clave){

            return $this->$clave;
        }

        public function guardarMesaEnDB(){

            $db = DB::getInstance('sql10.freemysqlhosting.net', 'sql10456676', 'sql10456676', 'Pbn5Z9Ayd4');
            $stdOut = new stdClass();
            $stdOut2 = new stdClass();
            
            $valuesMesa = "'{$this->__get('lugarMesa')}','{$this->__get('estadoMesa')}'";
            $stdOut->executeCode = $db->insertObject('mesa', 'lugarMesa, estadoMesa', $valuesMesa);
            $stdOut2->executeCode = $db->execSQL("UPDATE mesa SET codigoMesa = ((last_insert_id() * 19379 + 62327) % 89989) + 10000 WHERE id_mesa = last_insert_id()");
            
            if ($stdOut->executeCode && $stdOut2->executeCode) {
                $stdOut->mensaje = get_class($this). " agregada con exito.";
            } else {
                $stdOut->mensaje = "Hubo un error al guardar la ". get_class($this);
            }
    
            return $stdOut;
        }
    
        public static function traerMesaDeDB($condicion = '')
        {
    
            $db = DB::getInstance('sql10.freemysqlhosting.net', 'sql10456676', 'sql10456676', 'Pbn5Z9Ayd4');
            $listadoMesas = $db->selectObject('mesa', '*', $condicion);
    
            return $listadoMesas;
        }

        public static function cambiarEstadoMesa($idMesa, $estado){

            $db = DB::getInstance('sql10.freemysqlhosting.net', 'sql10456676', 'sql10456676', 'Pbn5Z9Ayd4');

            $resultado = $db->updateObject('mesa', "estadoMesa = '{$estado}'", "WHERE id_mesa = '{$idMesa}'");

            return $resultado;
        }

        public static function getEstadoMesaById($idMesa){

            $db = DB::getInstance('sql10.freemysqlhosting.net', 'sql10456676', 'sql10456676', 'Pbn5Z9Ayd4');
            $listadoMesas = $db->selectObject('mesa', '*', "WHERE id_mesa = {$idMesa} LIMIT 1");

            return $listadoMesas[0]->estadoMesa;
        }

        public static function getCodigoMesaById($idMesa){

            $db = DB::getInstance('sql10.freemysqlhosting.net', 'sql10456676', 'sql10456676', 'Pbn5Z9Ayd4');
            $listadoMesas = $db->selectObject('mesa', '*', "WHERE id_mesa = {$idMesa} LIMIT 1");

            return $listadoMesas[0]->codigoMesa;
        }

        public static function getMesaMasUsada(){

            $resultado = new stdClass();
            $resultado->mensaje = "Ninguna mesa fue la mas usada";

            $idMesaMasPedidos = Pedido::getMesasConMasPedidos()[0]->mesaMasUsada;
            if(empty($idMesaMasPedidos)){
                return $resultado;
            }else{
                $resultado->mensaje = "La mesa con mas pedidos fue la del codigo " .Mesa::getCodigoMesaById($idMesaMasPedidos) . " - ID {$idMesaMasPedidos}";
                return $resultado;
            }
        }

    }

?>