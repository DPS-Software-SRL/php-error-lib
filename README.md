# php-error-lib

Gestor de errores 

Captura FATAL errors, por Time Limit o Allowed Memory Size


Al constructor se le puede enviar opcionalmente
 * un string con el nombre de la APP que luego sera visible en los envios a Discord
 * Una URL webhook de Discord para el envio de los mensajes. Este valor tendrá prioridad sobre el encontrado en .env
```
new Dps\ErrorToDiscord( [ 'Puma ARG Incidencias' ] [, $urlWebhook ] );
```



Agrupa los errores repetidos para no saturar Discord.
Y los envia en bloques de a 10 ( por el limite de embbeds de Discord webhook )

**Variables de Entorno usadas
* PATH_LOG
  Path a la raiz de la carpeta de logs
  Default en ```/tmp```

* ERROR_LOG_ENABLED (bool)
  Guarda mensajes en archivo separado por Y/m/d/NombreUsuario.log

* ERROR_LOG_TO_SCREEN (bool)
  Muestra info para debug en pantalla ( solo util durante desarrollo )
  
* DISCORD_WEBHOOK_URL (string|null)
  Opcionalmente envia los mensajes a un canal de Discord

* ENVIRONMENT
  


**Metodos utiles

* ErrorToDiscord::setReporting
  Similar al uso de error_reporting(), configura que tipos de mensajes son enviados a Discord
