<?php

use Firebase\JWT\JWT;
class ApiController{

    private string $jwt;
    private static string $BASE_URL = "http://apislim4/";

    public static function GenerarJWT($array = null){

        $fecha = new DateTime();

        $key = "anon...iknee1_!![][][!][]S[A*Ñ[]SÑB]Ñ]B¨ÑP*";
        $payload = array(
            "iss" => ApiController::$BASE_URL,
            "aud" => ApiController::$BASE_URL,
            "iat" => $fecha->getTimestamp(),
            "nbf" => $fecha->getTimestamp()-1,
            "exp" => $fecha->getTimestamp()+(60*10),
        );

        if(isset($array) && $array != null){
            $payload = array_merge($payload, $array);
        }

        return $jwt = JWT::encode($payload, $key);

    }

    public static function VerificarJWT($jwt, $array){

        $privateKey = "anon...iknee1_!![][][!][]S[A*Ñ[]SÑB]Ñ]B¨ÑP*";
        $obj = new stdClass();

        try{
            $obj->jwt = JWT::decode($jwt, $privateKey, $array);
            $obj->mensaje = "Validacion exitosa";
            $obj->status = 200;
        }catch(Exception $e){
            $obj->mensaje = $e->getMessage();
            $obj->status = 403;
        }
        finally{
            return $obj;
        }
    }

}

?>