<?php

namespace Dps;

use Dps\ErrorHandler;
use Dps\Discord;

/**
 * Utiliza el manejador de errores de DPS y envia los errores a discord
 */
class ErrorToDiscord extends ErrorHandler
{
    private Discord $D;
    private $reporting = E_ALL;
    private $colors = [
            1 => "C5081D",
            2 => "FF7400",
            4 => "FF7400",
            8 => "FFC100",
           16 => "FF4D00",
           32 => "FF4D00",
           64 => "FF4D00",
          128 => "FF4D00",
          256 => "C5081D",
          512 => "FF7400",
         1024 => "FFC100",
         2048 => "FF7400",
         4096 => "C5081D",
         8192 => "FF7400",
        16384 => "FF7400"
    ];


    public function __construct()
    {
        parent::__construct();

        $this->D = new Discord();

        register_shutdown_function( [ $this, 'shutdown2' ] );
    }


    public function __destruct() {
        if( count( $this->mensajes ) > 0 ) {
            $this->log();    
        }
    }


    /**
     * Envia los errores a discord en un solo llamado agrupando los embbeds de a 10
     * @return void
     */
    private function log() {        

        // Hay un limite de 10 embbeds por envio
        $chunks = array_chunk( $this->mensajes, 10 );
        
        foreach ( $chunks as $bloque) {
            $embeds = [];        
            foreach ( $bloque as $mensaje) {

                if( !( $mensaje['errNo'] & $this->reporting ) ) {
                    continue;
                }

                $tipo     = $mensaje['errType'];
                $repetido = ( $mensaje['count'] > 1 ) ? ". Repetido {$mensaje['count']} veces" : '';

                $trace = "";
                if( count( $mensaje['trace'] ) > 0 ) {
                    foreach ($mensaje['trace'] as $t) {
                        $trace .= "\n{$t['file']} ({$t['line']})";
                    }
                }

                $embeds[] = [
                    'timestamp'   => date('c'),
                    'title'       => $mensaje['errMsg'],
                    'description' => "{$mensaje['file']} ({$mensaje['line']} )",
                    "color"       => hexdec( $this->colors[$mensaje['errNo']] ),
                    "footer" => [
                        "text" => "[{$tipo}] $repetido"
                    ],
                    "fields" => [
                        [
                            "name" => "Trace",
                            "value" => $trace
                        ]
                    ]
                ];            
            }

            $this->D->sendMessage( '', $embeds, $this->getUsuario() );
        }
    }


    // Caso excepcional para controlar FATALES que maten al script
    // Memoria o tiempo por ejemplo
    public function shutdown2() {        
        $error = error_get_last();

        if ($error) {        
            $this->log();
        }

    }    


    /**
     * Similar a error_reporting pero para indicar cuales errores se envian Discord
     * @param int $reporting idem error_reporting ()
     */
    public function setReporting( int $reporting ) {
        $this->reporting = $reporting;
    }


    public function getReporting() {
        return $this->reporting;
    }

}