<?php
header('Access-Control-Allow-Origin: *');

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Dompdf\Dompdf;


require __DIR__ . '/../../vendor/autoload.php';

$app = AppFactory::create();

require_once('../src/clases/DB.php');
require_once('../src/clases/Comida.php');
require_once('../src/clases/Empleado.php');
require_once('../src/clases/Mesa.php');
require_once("../src/clases/Logger.php");
require_once("../src/clases/Encuesta.php");
require_once('../src/clases/Pedido.php');
require_once("../src/clases/Venta.php");
require_once("../src/clases/PedidoPorEmpleado.php");

require_once('ApiController.php');
require_once('ApiMiddleware.php');
require_once('ValidacionCamposMiddleware.php');
require_once('ValidarRolMiddleware.php');
require_once('ValidacionComidaMiddleware.php');
require_once('ValidarImagenMiddleware.php');
require_once('ValidacionEmpleadoPuedeIngresar.php');

$middleware = new ApiMiddleware();

$app->group('/empleados', function (RouteCollectorProxy $group) {

    $group->post('/ingresar', function (Request $request, Response $response): Response {

        $datosPOST = $request->getParsedBody();
        $nombre = $datosPOST['nombre'];
        $codigo = $datosPOST['codigoEmpleado'];

        $responseOut = new stdClass();
        $empleado = Empleado::traerEmpleadosDeDB("WHERE nombre = '${nombre}' AND codigoEmpleado = '${codigo}' LIMIT 1");
        if (empty($empleado)) {

            $responseOut->exito = false;
            $responseOut->jwt = null;
            $responseOut->status = 401;
            $response = $response->withStatus(401, "Invalid credentials");
        } else {

            $empleado = $empleado[0];
            $responseOut->exito = true;
            $responseOut->jwt = \ApiController::GenerarJWT(array("nombre" => $empleado->nombre, "rol" => $empleado->rol, "codigoEmpleado" => $codigo));
            $responseOut->status = 200;
        }

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($responseOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidacionCamposMiddleware(["nombre", "codigoEmpleado"]));

    $group->post('/', function (Request $request, Response $response, array $args): Response {

        $datosPOST = $request->getParsedBody();
        $nombreEmpleado = $datosPOST['nombre'];
        $rol = $datosPOST['rol'];

        $nuevoEmpleado = new Empleado($nombreEmpleado, $rol);
        $stdOut = $nuevoEmpleado->guardarEmpleadoEnDB($nuevoEmpleado);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["nombre", "rol"]));

    $group->get('/', function (Request $request, Response $response, array $args): Response {

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode(Empleado::traerEmpleadosDeDB()));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/{id}', function (Request $request, Response $response, array $args): Response {

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode(Empleado::traerEmpleadosDeDB("WHERE id_empleado = {$args['id']}")));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->delete('/{id}', function (Request $request, Response $response, array $args): Response {

        $id = $args["id"];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $stdOut = $empleado->eliminarEmpleado($id);

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/recuperar/{id}', function (Request $request, Response $response, array $args): Response {

        $id = $args["id"];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $stdOut = $empleado->recuperarEmpleado($id);

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->delete('/suspender/{id}', function (Request $request, Response $response, array $args): Response {

        $id = $args["id"];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $stdOut = $empleado->suspenderEmpleado($id);

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/habilitar/{id}', function (Request $request, Response $response, array $args): Response {

        $id = $args["id"];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $stdOut = $empleado->habilitarEmpleado($id);

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');
});

$app->group('/comidas', function (RouteCollectorProxy $group) {

    $group->post('/', function (Request $request, Response $response, array $args): Response {

        $datosPOST = $request->getParsedBody();
        $nombreComida = $datosPOST['nombre'];
        $tipo = $datosPOST['tipo'];
        $valor = $datosPOST['valor'];

        $nuevaComida = new Comida($nombreComida, $tipo, $valor);

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $stdOut = $empleado->guardarComida($nuevaComida);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["nombre", "tipo", "valor"]));

    $group->get('/listar', function (Request $request, Response $response, array $args): Response {

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode(Comida::traerComidaDeDB()));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    });
});

$app->group('/mesas', function (RouteCollectorProxy $group) {

    $group->post('/', function (Request $request, Response $response, array $args): Response {

        $datosPOST = $request->getParsedBody();
        $lugarMesa = $datosPOST['lugarMesa'];

        $nuevaMesa = new Mesa($lugarMesa);

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $respuesta = $empleado->agregarMesa($nuevaMesa);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($respuesta));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["lugarMesa"]));

    $group->get('/', function (Request $request, Response $response, array $args): Response {

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode(Mesa::traerMesaDeDB()));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware())->add('ApiMiddleware:ValidarJWT');

    $group->get('/masUsada/', function (Request $request, Response $response, array $args): Response {

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode(Mesa::getMesaMasUsada()));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware())->add('ApiMiddleware:ValidarJWT');

    $group->get('/{idMesa}/{codigoPedido}', function (Request $request, Response $response, array $args): Response {

        $idMesa = $args['idMesa'];
        $codigoPedido = $args['codigoPedido'];

        $resultado = Pedido::tiempoRestantePedidoMesa($idMesa, $codigoPedido);
        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    });

    $group->post('/liberar/{idMesa}', function (Request $request, Response $response, array $args): Response {

        $idMesa = $args['idMesa'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $resultado = $empleado->liberarMesa($idMesa, "Libre");
        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios", "mozos"]))->add('ApiMiddleware:ValidarJWT');

    $group->post('/cerrar/{idMesa}', function (Request $request, Response $response, array $args): Response {

        $idMesa = $args['idMesa'];

        $resultado = Mesa::cambiarEstadoMesa($idMesa, "Cerrada");
        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');
    //Aca solo entran socios. . .
});

$app->group('/encuesta', function (RouteCollectorProxy $group) {

    $group->post('/', function (Request $request, Response $response, array $args): Response {

        $encuesta = new stdClass();
        $datosPOST = $request->getParsedBody();

        $encuesta->codigoPedido = $datosPOST["codigoPedido"];
        $encuesta->puntajeServicio = $datosPOST["puntajeServicio"];
        $encuesta->comentarioServicio = $datosPOST["comentarioServicio"];

        $nuevaEncuesta = new Encuesta($datosPOST["codigoPedido"], $datosPOST["puntajeServicio"], $datosPOST["comentarioServicio"]);
        $nuevaEncuesta->guardarEncuestaEnDB();

        Logger::escribir("../src/encuesta/encuestas.txt", "El servicio codigo {$datosPOST["codigoPedido"]} ha sido puntado con {$datosPOST["puntajeServicio"]}: {$datosPOST["comentarioServicio"]}");
        $response->getBody()->write(json_encode($encuesta));
        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionCamposMiddleware(["codigoPedido", "puntajeServicio", "comentarioServicio"]));

    $group->get('/mejores', function (Request $request, Response $response, array $args): Response {

        $encuestas = Encuesta::traerMejoresEncuestas();

        $response->getBody()->write(json_encode($encuestas));
        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware())->add('ApiMiddleware:ValidarJWT');
});

$app->group('/pedidos', function (RouteCollectorProxy $group) {

    $group->get('/', function (Request $request, Response $response, array $args): Response {

        $response->getBody()->write(json_encode(Pedido::traerPedidosDeDB()));
        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["mozos", "socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->post('/', function (Request $request, Response $response, array $args): Response {

        $datosPOST = $request->getParsedBody();
        $nombreCliente = $datosPOST['nombreCliente'];
        $mesa = $datosPOST['idMesa'];
        $comidas = $datosPOST['comidas'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $nuevoPedido = new Pedido($nombreCliente, $mesa, json_decode($comidas));

        if (isset($request->getUploadedFiles()['foto'])) {

            $foto = $request->getUploadedFiles()['foto'];
            $resultado = $empleado->realizarPedido($nuevoPedido, $foto);
        } else {
            $resultado = $empleado->realizarPedido($nuevoPedido);
        }

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["mozos", "socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionComidaMiddleware())->add(new ValidacionCamposMiddleware(["nombreCliente", "idMesa", "comidas"]))->add(new ValidarImagenMiddleware("foto")); //Preguntar

    $group->post('/csv', function (Request $request, Response $response, array $args): Response {

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);

        $nuevoPedido = Pedido::getPedidoByCSV($request->getUploadedFiles()['csv']);

        if (isset($request->getUploadedFiles()['foto'])) {

            $foto = $request->getUploadedFiles()['foto'];
            $resultado = $empleado->realizarPedido($nuevoPedido, $foto);
        } else {
            $resultado = $empleado->realizarPedido($nuevoPedido);
        }

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["mozos", "socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidarImagenMiddleware("foto"));

    $group->get('/csv', function (Request $request, Response $response, array $args): Response {

        $csv = Pedido::csvPedidosFromDB(Pedido::traerPedidosDeDB());
        
        $csv->output("pedidos.csv");
        
        $response = $response->withStatus(200);
        $response->withHeader("Content-Description", "File Transfer");
        $response->withHeader("Content-Type","text/csv; charset=UTF-8");
        $response->withHeader('Content-Disposition', "attachment; filename=pedidos.csv");
        return $response;
    })->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/pdf', function (Request $request, Response $response, $array): Response {

        $listadoVentas = Pedido::listarPedidos(Pedido::traerPedidosDeDB());

        $pdf = new DOMPDF();
        $filename = "ListadoPedidos" . date("Y-d-m") . ".pdf";

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
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(['socios']))->add('ApiMiddleware:ValidarJWT');

    $group->get('/listar', function (Request $request, Response $response, array $args): Response {

        $response->getBody()->write(Pedido::listarPedidos(Pedido::traerPedidosDeDB()));
        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware())->add('ApiMiddleware:ValidarJWT');


    $group->get('/pendientes', function (Request $request, Response $response, array $args): Response {

        //Listados pendientes de tomar
        $stdOut = new stdClass();
        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));

        $listadoPendientes = PedidoPorEmpleado::pendientesPorRol($verifiedJWT->jwt->rol);
        if (!isset($listadoPendientes) || empty($listadoPendientes)) {
            $stdOut->mensaje = "No hay pedidos por cubrir!";
        } else {
            $stdOut->pedidos = $listadoPendientes;
        }

        $response->getBody()->write(json_encode($stdOut));
        $response = $response->withStatus(200);
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware())->add('ApiMiddleware:ValidarJWT');
    //listado pendientes (segun rol) si sos socio te trae todos

    $group->get('/servir/{nPedido}', function (Request $request, Response $response, array $args): Response {

        $nPedido = $args['nPedido'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        $resultado = $empleado->servirPedido($nPedido);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios", "mozos"]))->add('ApiMiddleware:ValidarJWT');

    $group->post('/tomar/{nPedido}', function (Request $request, Response $response, array $args): Response {

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
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios", "bartender", "cervecero", "cocineros"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["tiempoPedido"]));

    $group->get('/finalizar/{nPedido}', function (Request $request, Response $response, array $args): Response {

        $nPedido = $args['nPedido'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        $resultado = $empleado->finalizarPedido($nPedido);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios", "bartender", "cervecero", "cocineros"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/cobrar/{nPedido}', function (Request $request, Response $response, array $args): Response {

        $nPedido = $args['nPedido'];

        $jwt = $request->getHeader('token')[0];
        $verifiedJWT = \ApiController::VerificarJWT($jwt, array('HS256'));
        $empleado = new Empleado($verifiedJWT->jwt->nombre, $verifiedJWT->jwt->rol);
        $resultado = $empleado->cobrarPedido($nPedido);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios", "mozos"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/tardios/', function (Request $request, Response $response, array $args): Response {

        $stdOut = new stdClass();
        $pedidos = PedidoPorEmpleado::getPedidosTardios();

        $stdOut->pedidos = $pedidos;
        if(empty($pedidos)){
            $stdOut->mensaje = "No se encontraron pedidos entregados fuera de tiempo";
        }

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->get('/entiempo/', function (Request $request, Response $response, array $args): Response {

        $stdOut = new stdClass();
        $pedidos = PedidoPorEmpleado::getPedidosRealizadosEnTiempo();

        $stdOut->pedidos = $pedidos;
        if(empty($pedidos)){
            $stdOut->mensaje = "No se encontraron pedidos entregados en tiempo y forma";
        }

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($stdOut));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

    $group->post('/pedidoConMasGanancia/', function (Request $request, Response $response, array $args): Response {

        $resultado = new stdClass();

        $datosPOST = $request->getParsedBody();
        $fechaInicio = $datosPOST['fechaInicio'];
        $fechaLimite = $datosPOST['fechaLimite'];

        $resultado = Pedido::getPedidoConMasGanancia($fechaInicio, $fechaLimite);

        $response = $response->withStatus(200);

        $response->getBody()->write(json_encode($resultado));
        $response->withHeader('Content-type', 'application/json');
        return $response;
    })->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT')->add(new ValidacionCamposMiddleware(["fechaInicio", "fechaLimite"]));
});

$app->get('/logoComanda', function (Request $request, Response $response, array $args): Response {

    $pdf = new Dompdf(array('enable_remote' => true));
    $filename = "LogoComanda.pdf";

    $img_path = "../src/images/logoComanda.png";
    $img_data = fopen ( $img_path, 'rb' );
    $img_size = filesize ( $img_path );
    $binary_image = fread ( $img_data, $img_size );
    fclose ( $img_data );

    $img_src = "data:image/png;base64,".str_replace ("\n", "", base64_encode($binary_image));


    $pdf->setPaper("A4", "portrait");
    $pdf->loadHtml("<!DOCTYPE html><body><img src='{$img_src}' width='100px' height='100px'></body></html>");
    $pdf->render();
    $pdf->stream($filename);

    $str = $pdf->output();
    $length = mb_strlen($str, '8bit');

    $response = $response->withStatus(200);
    $response->withHeader('Content-type', 'application/pdf');
    $response->withHeader('Content-Length', $length);
    $response->withHeader('Content-Disposition', 'inline;  filename=' . $filename)->withHeader('Accept-Ranges', $length);
    
    return $response;
})->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');

$app->get('/logComanda', function (Request $request, Response $response, array $args): Response {

    $pdf = new Dompdf(array('enable_remote' => true));
    $filename = "LogLaComanda.pdf";

    $log = Logger::leer("../src/logs/accionesEmpleado.txt");

    $pdf->setPaper("A3", "portrait");
    $pdf->loadHtml("<!DOCTYPE html><head><style>*{margin-left:0}</style></head><body><pre style='font-size:10px;'>{$log}</pre></body></html>");
    $pdf->render();
    $pdf->stream($filename);

    $str = $pdf->output();
    $length = mb_strlen($str, '8bit');

    $response = $response->withStatus(200);
    $response->withHeader('Content-type', 'application/pdf');
    $response->withHeader('Content-Length', $length);
    $response->withHeader('Content-Disposition', 'inline;  filename=' . $filename)->withHeader('Accept-Ranges', $length);
    
    return $response;
})->add(new ValidacionEmpleadoPuedeIngresar())->add(new ValidarRolMiddleware(["socios"]))->add('ApiMiddleware:ValidarJWT');


/*
            -Guardar el tiempo en el que se tomo el pedido
            -Guardar el tiempo en el que se finalizo
        -Poner el tiempo estimado MAXIMO de los pedidoxempleado EN LA TABLA 'Pedidos'
            -Descargar CSV (no mostrar solamente) (Fijarse en el explorador)
            -Estado de un empleado (eliminado-suspendido) - faltano mstrar
            -Estadistica de 30 dias (PUROS SELECTs)
            -Logger en acciones que realizo el usuario
            -Suspendido o eliminado no se puede loguear y ¿se muestra?
    -DICIEMBRE
    -SUBIR HEROKUUUU
            -GUARDAR VENTAS
    listar con imagens

            Probar todo - 
*/
$app->run();
