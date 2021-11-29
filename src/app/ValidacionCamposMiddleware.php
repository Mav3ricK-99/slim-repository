<?php

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;
use Psr\Http\Message\ServerRequestInterface as Request;

class ValidacionCamposMiddleware{

    private array $camposValidar;

    public function __construct($campos){
        $this->camposValidar = $campos;
    }

    public function __invoke(Request $request, RequestHandler $handler) : ResponseMW
    {
        $nuevaResponse = new ResponseMW();
        $stdOut = new stdClass();

        $camposFaltantes = "Los siguientes campos faltaron en la Request: ";

        $error = false;
        $datos = (object)$request->getParsedBody();
        $datos = get_object_vars($datos);
        foreach($this->camposValidar as $campo){

            if(!isset($datos[$campo]) || empty($datos[$campo]) || $datos[$campo] == null){
                $camposFaltantes .= $campo . " ";
                $error = true;
            }
        }

        if($error){
            $stdOut->exito = false;
            $stdOut->mensaje = $camposFaltantes;
            $stdOut->status = 400;
            $nuevaResponse->getBody()->write(json_encode($stdOut));
            
        }else{
            $stdOut->status = 200;
            $nuevaResponse = ApiMiddleware::HandleRequest($request, $handler, $nuevaResponse);
        }
        $nuevaResponse->withStatus($stdOut->status);
        $nuevaResponse->withHeader('Content-type', 'application/json');
      
        return $nuevaResponse;
    }
}

?>