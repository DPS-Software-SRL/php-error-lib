<?php

namespace Dps;

use Cekurte\Environment\Environment as env;

/**
 * clase Errorhandler para el manejo de errores 
 * 
 * @requires $_ENV['ERROR_LOG_ENABLED']
 * @requires $_ENV['ERROR_LOG_TO_SCREEN']
 * @requires $_ENV['PATH_LOG']
 */
class ErrorHandler
{
    public $err;
    private $errType = [
           1 => "ERROR",
           2 => "WARNING",
           4 => "PARSING",
           8 => "NOTICE",
          16 => "CORE ERROR",
          32 => "CORE WARNING",
          64 => "COMPILE ERROR",
         128 => "COMPILE WARNING",
         256 => "USER ERROR",
         512 => "USER WARNING",
        1024 => "USER NOTICE",
        2048 => "STRICT",
        4096 => "ERROR FATAL CAPTURABLE",
        8192 => "DEPRECATED",
       16384 => "USER DEPRECATED"
    ];
    public array $mensajes = [];
    private string $memory;
    private string $to_log = true;
    private string $to_screen = false;
    private string $usuario;

    public function __construct() {
        $this->to_log         = env::get('ERROR_LOG_ENABLED', true);
        $this->to_screen      = env::get('ERROR_LOG_TO_SCREEN', false);
        $this->usuario        = $_SESSION['UsuarioConectado'] ?? 'sinUsuario';

        // Me guardo un poco de memoria, por las dudas, para ser usada en shutdown()
        $this->memory = str_repeat('*', 3 * 1024 * 1024); // 3mb

        ini_set('display_errors', 0);

        $this->setLogPath();
        
        set_error_handler( [ $this, 'error_handler' ] );
        set_exception_handler( [ $this, 'exception_handler' ] );
        register_shutdown_function( [ $this, 'shutdown' ] );

    }


    public function exception_handler( $ex ) {
        $this->procesar( E_ERROR, $ex->getMessage(), $ex->getFile(), $ex->getLine(), $ex->getTrace() );
        die;
    }

    public function error_handler( int $errNo, string $errMsg = '', string $file = '', int $line = 0, $context = [] ) {
        // Revisa que el error este incluido en error_reporting()
        if( ! ( $errNo & error_reporting() ) ) 
          return true;

        $trace = debug_backtrace();
        $this->procesar( $errNo, $errMsg, $file, $line, $trace );

        return true; // false para seguir con el manejador de errores nativo de php
    }


    private function procesar( int $errNo, string $errMsg = '', string $file = '', int $line = 0, array $trace = [] ) {
        $this->save( $errNo, $errMsg, $file, $line, $trace );
        $this->toLog();
        $this->toScreen();
    }


    /**
     * Guarda el error en un array agrupando los repetidos
     * @param int $errNo
     * @param string $errMsg
     * @param string $file
     * @param int $line
     * @param array $trace
     * @return void
     */
    private function save( int $errNo, string $errMsg = '', string $file = '', int $line = 0, array $trace = [] ) {
        $this->err = [
            'errNo'   => $errNo,
            'errType' => $this->errType[$errNo],
            'errMsg'  => $errMsg,
            'file'    => $file,
            'line'    => $line,
            'trace'   => $trace,
            'count'   => 1
        ];

        $hash = md5( $errMsg . $file . $line );
        if( array_key_exists( $hash, $this->mensajes ) ) {
            $this->mensajes[$hash]['count']++;    
            
        } else {
            $this->mensajes[ $hash ] = $this->err;
        }
    }


    /**
     * Envia el error al archivo de log
     * @return void
     */
    private function toLog() {
        if( $this->to_log ) {
            error_log( "[{$this->err['errType']}] {$this->err['errMsg']} {$this->err['file']} ({$this->err['line']})" );
        }
    }


    /**
     * Envia el error a la pantalla
     * @return void
     */
    private function toScreen() {
        if( $this->to_screen ) {
            debug( $this->err );
        }              
    }


    /**
     * Genera la ruta del archivo de log
     * @return void
     */
    private function setLogPath() {  
        $path = env::get('PATH_LOG', '/tmp') . date('/Y/m/d');
    
        if( ! file_exists( $path ) )
          mkdir( $path, 0755, true);        
            
        $fullPath = "{$path}/{$this->usuario}.log";
    
        $this->log_path = $fullPath;
        
        ini_set("error_log", $fullPath );
    }


    /**
     * Esta funcion se ejecuta al final de cada script
     * @return void
     */
    public function shutdown() {
        // devuelvo la memoria consumida a proposito
        // Eso será util cuando el error haya sido por memoria consumida, 
        // y entonces, no me quede nada disponbile para seguir manejando el error
        unset( $this->memory );
 
        $error = error_get_last();        
        
        if ($error) {        
            $this->procesar( $error["type"], $error['message'], $error['file'], $error['line'] );
            http_response_code(500);
        }
    }    
    
    public function getUsuario() {
        return $this->usuario;
    }

}