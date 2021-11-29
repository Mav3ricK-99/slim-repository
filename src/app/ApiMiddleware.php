<?php

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiMiddleware{

    public function ValidarJWT(Request $request, RequestHandler $handler) : ResponseMW{

        $nuevaResponse = new ResponseMW();
        
        if(!isset($request->getHeader('token')[0])){
            
            $obj = new stdClass();
            $obj->mensaje= "Ingrese jwt ;)";
            
            $nuevaResponse->getBody()->write(JSON_encode($obj));
            $nuevaResponse->withHeader('Content-type', 'application/json');
            
            return $nuevaResponse;
        }
        $jwt = $request->getHeader('token')[0];

        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        
        if($verifiedJWT->status != 200){

            $nuevaResponse->getBody()->write(JSON_encode($verifiedJWT));
            $nuevaResponse->withHeader('Content-type', 'application/json');
        }else{

            $nuevaResponse = ApiMiddleware::HandleRequest($request, $handler, $nuevaResponse);
        }

        return $nuevaResponse;
    }

    public static function HandleRequest(Request $request, RequestHandler $handler, ResponseMW $nuevaResponse) : ResponseMW{

        $response = $handler->handle($request);

        $apiContent = (string) $response->getBody();
        $nuevaResponse->getBody()->write("${apiContent}");
        $nuevaResponse->withHeader('Content-type', 'application/json');

        return $nuevaResponse;
    }
}

?>