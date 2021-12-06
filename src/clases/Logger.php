<?php

class Logger{

    public static function escribir(string $path, string $content, $dateTime = true){

        $logger = fopen($path, "a+");

        if($dateTime){
            $content = date("Y-m-d H:i:s"). " " .$content . "\r\n";
        }else{
            $content = $content . "\r\n";
        }
        fwrite($logger, $content);

        fclose($logger);
    }

    public static function leer(string $path){

        $logger = fopen($path, "r");
        $contentArchivo = "";
        while (!feof($logger)){
            $contentArchivo .= fgets($logger);
        }

        return $contentArchivo;
    }

}
?>