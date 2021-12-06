<?php

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;
use Psr\Http\Message\ServerRequestInterface as Request;

class ValidacionEmpleadoPuedeIngresar{

    public function __invoke(Request $request, RequestHandler $handler) : ResponseMW
    {
        $nuevaResponse = new ResponseMW();
        $stdOut = new stdClass();
        $datosPOST = $request->getParsedBody();

        if(isset($datosPOST['nombre']) && isset($datosPOST['codigoEmpleado'])){
            $nombre = $datosPOST['nombre'];
            $codigoEmpleado = $datosPOST['codigoEmpleado'];
        }else{
            $jwt = $request->getHeader('token')[0];
            $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));

            $nombre = $verifiedJWT->jwt->nombre;
            $codigoEmpleado = $verifiedJWT->jwt->codigoEmpleado;
        }

        $empleado = Empleado::traerEmpleadosDeDB("WHERE nombre = '${nombre}' AND codigoEmpleado = '${codigoEmpleado}' LIMIT 1");
        if(!empty($empleado)){

            $empleado = $empleado[0];
            if($empleado->eliminado == 1 || $empleado->suspendido){
                $stdOut->exito = false;
                $stdOut->mensaje = "El empleado se encuentra eliminado o suspendido del sistema";
                $stdOut->status = 405;
                $nuevaResponse->getBody()->write(json_encode($stdOut));
            }else{
                $stdOut->status = 200;
                $nuevaResponse = \ApiMiddleware::HandleRequest($request, $handler, $nuevaResponse);
            }
        }

        $nuevaResponse->withStatus($stdOut->status);
        $nuevaResponse->withHeader('Content-type', 'application/json');
      
        return $nuevaResponse;
    }
}

?>