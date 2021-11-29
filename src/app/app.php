<?php
header('Access-Control-Allow-Origin: *');
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Firebase\JWT\JWT;
use Dompdf\Dompdf;

use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;


require __DIR__ . '/../../vendor/autoload.php';

$app = AppFactory::create();

require_once('../src/clases/DB.php');
require_once('../src/clases/Comida.php');
require_once('../src/clases/Empleado.php');
require_once('../src/clases/Mesa.php');
require_once('../src/clases/Pedido.php');
require_once("../src/clases/PedidoPorEmpleado.php");

require_once('ApiController.php');
require_once('ApiMiddleware.php');
require_once('ValidacionCamposMiddleware.php');
require_once('ValidarRolMiddleware.php');
require_once('ValidacionComidaMiddleware.php');
require_once('ValidarImagenMiddleware.php');

$middleware = new ApiMiddleware();

$app->group('/empleados', function (RouteCollectorProxy $group) {

    $group->post('/ingresar', function (Request $request, Response $response) : Response {  

        $datosPOST = $request->getParsedBody();
        $nombre = $datosPOST['nombre'];
        $codigo = $datosPOST['codigoEmpleado'];
    
        $responseOut = new stdClass();
        $empleado = Empleado::traerEmpleadosDeDB("WHERE nombre = '${nombre}' AND codigoEmpleado = '${codigo}' LIMIT 1");
        if(empty($empleado)){
    
            $responseOut->exito = false;
            $responseOut->jwt = null;
            $responseOut->status = 401;        
            $response = $response->withStatus(401, "Invalid credentials");
        }else{
    
            $empleado = $empleado[0];
            $responseOut->exito = true;
            $responseOut->jwt = \ApiController::GenerarJWT(array("nombre" => $empleado->nombre,"rol" => $empleado->rol));
            $responseOut->status = 200;
        }
        
        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($responseOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionCamposMiddleware(["nombre", "codigoEmpleado"]));

    $group->post('/', function (Request $request, Response $response, array $args) : Response {  

        $datosPOST = $request->getParsedBody();
        $nombreEmpleado = $datosPOST['nombre'];
        $rol = $datosPOST['rol'];
        
        $nuevoEmpleado = new Empleado($nombreEmpleado, $rol);
        $stdOut = $nuevoEmpleado->guardarEmpleadoEnDB($nuevoEmpleado);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["nombre", "rol"]));

    $group->get('/', function (Request $request, Response $response, array $args) : Response {  

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode(Empleado::traerEmpleadosDeDB()));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

});

$app->group('/comidas', function (RouteCollectorProxy $group) {

    $group->post('/', function (Request $request, Response $response, array $args) : Response {  

        $datosPOST = $request->getParsedBody();
        $nombreComida = $datosPOST['nombre'];
        $tipo = $datosPOST['tipo'];
        $valor = $datosPOST['valor'];
        
        $nuevaComida = new Comida($nombreComida, $tipo, $valor);
        $stdOut = $nuevaComida->guardarComidaEnDB($nuevaComida);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["nombre", "tipo", "valor"]));

    $group->get('/listar', function (Request $request, Response $response, array $args) : Response {  

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode(Comida::traerComidaDeDB()));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    });

});

$app->group('/mesas', function (RouteCollectorProxy $group) {

    $group->post('/', function (Request $request, Response $response, array $args) : Response {  

        $datosPOST = $request->getParsedBody();
        $lugarMesa = $datosPOST['lugarMesa'];
        
        $nuevaMesa = new Mesa($lugarMesa);
        $respuesta = $nuevaMesa->guardarMesaEnDB($nuevaMesa);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($respuesta));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["lugarMesa"]));

    $group->get('/', function (Request $request, Response $response, array $args) : Response {  

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode(Mesa::traerMesaDeDB()));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware())->add('ApiMiddleware:ValidarJWT');

    $group->get('/{idMesa}/{codigoPedido}', function (Request $request, Response $response, array $args) : Response {  

        $idMesa = $args['idMesa'];
        $codigoPedido = $args['codigoPedido'];

        $resultado = Pedido::tiempoRestantePedidoMesa($idMesa, $codigoPedido);
        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    });

    $group->get('/liberar/{idMesa}', function (Request $request, Response $response, array $args) : Response {  

        $idMesa = $args['idMesa'];

        $resultado = Mesa::cambiarEstadoMesa($idMesa, "Libre");
        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios", "mozos"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/cerrar/{idMesa}', function (Request $request, Response $response, array $args) : Response {  

        $idMesa = $args['idMesa'];

        $resultado = Mesa::cambiarEstadoMesa($idMesa, "Cerrada");
        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

});

$app->group('/pedidos', function (RouteCollectorProxy $group) {

    $group->get('/', function (Request $request, Response $response, array $args) : Response {  
        
        $response->getBody()->write(json_encode(Pedido::traerPedidosDeDB()));
        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["mozos", "socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->post('/', function (Request $request, Response $response, array $args) : Response {  

        $datosPOST = $request->getParsedBody();
        $nombreCliente = $datosPOST['nombreCliente'];
        $mesa = $datosPOST['idMesa'];
        $comidas = $datosPOST['comidas'];
        
        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        
        $nuevoPedido = new Pedido($nombreCliente, $mesa, json_decode($comidas));
        
        if(isset($request->getUploadedFiles()['foto'])){

            $foto = $request->getUploadedFiles()['foto'];
            $resultado = $empleado->realizarPedido($nuevoPedido, $foto);
            
        }else{
            $resultado = $empleado->realizarPedido($nuevoPedido);
        }

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["mozos", "socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionComidaMiddleware())->add(new ValidacionCamposMiddleware(["nombreCliente", "idMesa", "comidas"]))->add(new ValidarImagenMiddleware("foto"));//Preguntar

    $group->post('/csv', function (Request $request, Response $response, array $args) : Response {  

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        
        $nuevoPedido = Pedido::getPedidoByCSV($request->getUploadedFiles()['csv']);
        
        if(isset($request->getUploadedFiles()['foto'])){

            $foto = $request->getUploadedFiles()['foto'];
            $resultado = $empleado->realizarPedido($nuevoPedido, $foto);
            
        }else{
            $resultado = $empleado->realizarPedido($nuevoPedido);
        }

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["mozos", "socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidarImagenMiddleware("foto"));

    $group->get('/csv', function (Request $request, Response $response, array $args) : Response {  

        $csv = Pedido::csvPedidosFromDB(Pedido::traerPedidosDeDB());

        $response = $response->withStatus(200);
        $response->getBody()->write($csv);
        $response->withHeader('Content-type', 'text/csv');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/pdf', function (Request $request, Response $response, $array) : Response {  

        $listadoVentas = Pedido::listarPedidos(Pedido::traerPedidosDeDB());
        
        $pdf = new DOMPDF();
        $filename = "ListadoPedidos".date("Y-d-m").".pdf";
 
        $pdf->setPaper("A4", "portrait");
        $pdf->loadHtml("<!DOCTYPE html><body>{$listadoVentas}</body></html>");
        $pdf->render();
        $pdf->stream($filename);

        $str = $pdf->output();
        $length = mb_strlen($str, '8bit');

        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/pdf');
        $response->withHeader('Content-Length', $length);
        $response->withHeader('Content-Disposition', 'attachment;  filename=' . $filename)->withHeader('Accept-Ranges', $length);
        return $response;
    })->add(new ValidarRolMiddleware(['socios']))->add('ApiMiddleware:ValidarJWT');

    $group->get('/listar', function (Request $request, Response $response, array $args) : Response {  
        
        $response->getBody()->write(Pedido::listarPedidos(Pedido::traerPedidosDeDB()));
        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware())->add('ApiMiddleware:ValidarJWT');


    $group->get('/pendientes', function (Request $request, Response $response, array $args) : Response {  
        
        //Listados pendientes de tomar
        $stdOut = new stdClass();
        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        
        $listadoPendientes = PedidoPorEmpleado::pendientesPorRol($verifiedJWT->jwt->rol);
        if(!isset($listadoPendientes) || empty($listadoPendientes)){
            $stdOut->mensaje = "No hay pedidos por cubrir!";
        }else{
            $stdOut->pedidos = $listadoPendientes;
        }

        $response->getBody()->write(json_encode($stdOut));
        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware())->add('ApiMiddleware:ValidarJWT');
    //listado pendientes (segun rol)

    $group->get('/servir/{nPedido}', function (Request $request, Response $response, array $args) : Response {  

        $nPedido = $args['nPedido'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        $resultado = $empleado->servirPedido($nPedido);

        $response = $response->withStatus(200);
        
        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios", "mozos"]))->add('ApiMiddleware:ValidarJWT');

    $group->post('/tomar/{nPedido}', function (Request $request, Response $response, array $args) : Response {  

        $datosPOST = $request->getParsedBody();
        $nPedido = $args['nPedido'];
        $tiempoPedido = $datosPOST['tiempoPedido'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        $resultado = $empleado->tomarPedido($nPedido, $tiempoPedido);

        $response = $response->withStatus(200);
        
        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios", "bartender", "cervecero", "cocineros"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["tiempoPedido"]));

    $group->get('/finalizar/{nPedido}', function (Request $request, Response $response, array $args) : Response {  

        $nPedido = $args['nPedido'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        $resultado = $empleado->finalizarPedido($nPedido);

        $response = $response->withStatus(200);
        
        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios", "bartender", "cervecero", "cocineros"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/cobrar/{nPedido}', function (Request $request, Response $response, array $args) : Response {  

        $nPedido = $args['nPedido'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        $resultado = $empleado->cobrarPedido($nPedido);

        $response = $response->withStatus(200);
        
        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios", "mozos"]))->add('ApiMiddleware:ValidarJWT');
});


//->add('ApiMiddleware:ValidarJWT');
//JSon web token guardar rol
//RealizarPedido
//AdministrarPedido
//EntregarPedido
$app->run();
