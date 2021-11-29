<?php

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;
use Psr\Http\Message\ServerRequestInterface as Request;

class ValidarImagenMiddleware{

    private string $camposValidar;

    public function __construct($campos){
        $this->camposValidar = $campos;
    }

    public function __invoke(Request $request, RequestHandler $handler) : ResponseMW
    {
        $nuevaResponse = new ResponseMW();
        $stdOut = new stdClass();
        $stdOut->exito = false;
        $error = true;
        $imagen = isset($request->getUploadedFiles()[$this->camposValidar]) ? $request->getUploadedFiles()[$this->camposValidar] : null;
        
        if(isset($imagen)){

            if ($imagen->getError() != 0) {
                $stdOut->mensaje = "Error " . $imagen->error . " al subir la Imagen";
            } else {

                $ext = explode(".", $imagen->getClientFilename());
                $nPuntoEnNombre = count($ext);
                if ($imagen->getSize() > 1000000) {
                    $stdOut->mensaje = "Imagen no valida";
                }else{
                    $error = false;
                    if(!in_array($ext[$nPuntoEnNombre - 1], ["jpg", "jpeg", "png"], true)){
                        $error = true;
                        $stdOut->mensaje = "Imagen con extension no valida";
                    }
                }
            }
        }else{
            $error = false;
        }

        if($error){
            $stdOut->status = 400;
            $nuevaResponse->getBody()->write(json_encode($stdOut));
            
        }else{
            $stdOut->exito = true;
            $stdOut->status = 200;
            $nuevaResponse = ApiMiddleware::HandleRequest($request, $handler, $nuevaResponse);
        }
        $nuevaResponse->withStatus($stdOut->status);
        $nuevaResponse->withHeader('Content-type', 'application/json');
      
        return $nuevaResponse;
    }
}