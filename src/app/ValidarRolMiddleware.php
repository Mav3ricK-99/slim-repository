<?php

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;
use Psr\Http\Message\ServerRequestInterface as Request;

class ValidarRolMiddleware{

    private array $rolesMinimos;

    public function __construct($rol = ["bartender", "cerveceros", "cocineros", "mozos","socios"]){
        $this->rolesMinimos = $rol;
    }

    public function __invoke(Request $request, RequestHandler $handler) : ResponseMW
    {
        $nuevaResponse = new ResponseMW();
        $stdOut = new stdClass();

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        
        $error = false;
        
        if(!in_array($verifiedJWT->jwt->rol, $this->rolesMinimos, true)){
            $error = true;
        }

        if($error){
            $stdOut->exito = false;
            $stdOut->mensaje = "No tienes los permisos suficientes para realizar esta peticion. Tu rol es {$verifiedJWT->jwt->rol}";
            $stdOut->status = 403;
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