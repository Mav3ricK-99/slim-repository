<?php

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;
use Psr\Http\Message\ServerRequestInterface as Request;

class ValidacionComidaMiddleware{

    private $comidas;

    public function __invoke(Request $request, RequestHandler $handler) : ResponseMW
    {
        $datosPOST = $request->getParsedBody();
        $this->comidas = json_decode($datosPOST['comidas']);

        $nuevaResponse = new ResponseMW();
        $stdOut = new stdClass();

        $sql = "";
        $error = false;
        for($i = 0; $i<count($this->comidas);$i++){

            if($this->comidas[$i]->cantidad <= 0){
                $error = true;
            }
            $sql .= "id_comida = '{$this->comidas[$i]->id_comida}'";
            if($i != count($this->comidas)-1){
                $sql .= " OR ";
            }
        }
        
        if(count(Comida::traerComidaDeDB("WHERE ${sql}")) < count($this->comidas) || $error == true){
            $stdOut->exito = false;
            $stdOut->mensaje = "Alguna de las comidas ingresada resulto Invalida o se ingreso una cantidad invalida";
            $stdOut->status = 405;
            $nuevaResponse->getBody()->write(json_encode($stdOut));
        }else{
            $stdOut->status = 200;
            $nuevaResponse = \ApiMiddleware::HandleRequest($request, $handler, $nuevaResponse);
        }

        $nuevaResponse->withStatus($stdOut->status);
        $nuevaResponse->withHeader('Content-type', 'application/json');
      
        return $nuevaResponse;
    }
}

?>