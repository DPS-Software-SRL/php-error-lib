<?php

namespace Dps;

use Cekurte\Environment\Environment as env;
use Kint;

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
    private string $log_path = '';
    private string $memory;
    private bool $to_log = true;
    private bool $to_screen = false;
    private string $usuario;

    public function __construct() {
        $this->to_log         = env::get('ERROR_LOG_ENABLED', true);
        $this->to_screen      = env::get('ERROR_LOG_TO_SCREEN', false);

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

        if( get_class( $ex ) == 'Dps\MysqlException' ) {

            global $smarty;
            
            if( $smarty ) {        
                $nro   = $ex->MysqlNro;
                $texto = MysqlMessages::getMsg( $nro ) ?? $ex->MysqlError;
        
                $_GET['sinMenu'] = 1;
                http_response_code(406);
                $smarty->assign( "soloCerrar", true );
                $smarty->assign( "mensaje", "<span style='color:red;font-size:xx-small;'>- $nro -</span><br>$texto" );
                $smarty->mostrar( "mensajeerror.tpl" );
            }              
        }        
        
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
        $miniTrace = [];
        foreach ($trace as $t) {
            
            $f = $t['file'] ?? '';
            $l = $t['line'] ?? '';
            $miniTrace[] = "{$f} ({$l})"; 
        }

        $this->err = [
            'errNo'   => $errNo,
            'errType' => $this->errType[$errNo],
            'errMsg'  => $errMsg,
            'file'    => $file,
            'line'    => $line,
            'trace'   => $miniTrace,
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

            $this->usuario  = $_SESSION['UsuarioConectado'] ?? 'sinUsuario';
            $fullPath = "{$this->log_path}/{$this->usuario}.log";
            ini_set("error_log", $fullPath );

            
            error_log( "[{$this->err['errType']}] {$this->err['errMsg']} {$this->err['file']} ({$this->err['line']})" );
        }
    }


    /**
     * Envia el error a la pantalla
     * @return void
     */
    private function toScreen() {
        if( $this->to_screen ) {
            Kint::dump( $this->err );
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
        
        $this->log_path = $path;       
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