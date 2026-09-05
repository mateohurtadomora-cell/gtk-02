<?php
/**
 * Configuracion del formulario de contacto.
 *
 * COPIAR ESTE FICHERO A config.php Y EDITARLO ALLI. config.php no se sube al
 * repositorio (esta en .gitignore) porque contiene el secreto del token.
 *
 * Ninguno de estos valores llega nunca al navegador.
 */

return array(

  /* -- Entrega -------------------------------------------------------- */

  /* A donde llegan las consultas. Es el unico destinatario posible: el
     formulario no acepta ninguna direccion que venga del navegador, asi que
     el script no se puede usar como pasarela para enviar correo a terceros. */
  'to' => 'info@globaltk.com',

  /* Remitente. TIENE que ser una direccion del propio dominio: si se pone
     aqui la direccion del visitante, los servidores de correo lo leen como
     una suplantacion y el mensaje acaba en spam o rechazado. La direccion
     del visitante va en Reply-To, que es donde corresponde. */
  'from'      => 'web@globaltk.com',
  'from_name' => 'Formulario web GTK',

  /* Prefijo del asunto, para poder filtrar en el buzon. */
  'subject_prefix' => '[Web GTK]',

  /* Anadir "-f <from>" a la llamada a mail(). Mejora la entrega en la
     mayoria de hostings, pero algunos lo rechazan. Si los correos dejan de
     salir despues de activarlo, ponlo en false. */
  'envelope_sender' => true,

  /* -- Secreto -------------------------------------------------------- */

  /* Cadena larga y aleatoria. Firma los tokens del formulario y anonimiza
     las IP en el registro. Generala con:
       php -r "echo bin2hex(random_bytes(32));"
     Si cambia, los formularios abiertos en ese momento dejan de validar:
     el visitante solo tiene que recargar. */
  'secret' => 'CAMBIAME-POR-UNA-CADENA-ALEATORIA-LARGA',

  /* -- Origen permitido ----------------------------------------------- */

  /* Dominios desde los que se acepta el envio. Sin barra final. Cualquier
     peticion con otro origen se rechaza. Deja aqui solo los dominios reales
     de la web; el de pruebas se quita antes de publicar. */
  'allowed_origins' => array(
    'https://www.globaltk.com',
    'https://globaltk.com',
  ),

  /* -- Limites de uso -------------------------------------------------- */

  /* Envios permitidos por IP. El primero frena la rafaga; el segundo frena
     el goteo sostenido. */
  'max_per_ip_window'  => 3,
  'ip_window_seconds'  => 900,     /* 15 minutos */
  'max_per_ip_day'     => 10,

  /* Tope global por hora. Es la red de seguridad: aunque un atacante use
     cientos de IP distintas, el script deja de enviar al llegar aqui. Subelo
     si la web recibe mucho trafico legitimo. */
  'max_global_hour'    => 40,

  /* Segundos que tienen que pasar entre que se carga el formulario y se
     envia. Una persona no rellena cuatro campos en menos de esto; un robot
     si. */
  'min_seconds_on_page' => 4,

  /* Validez maxima del token, en segundos. Pasado este tiempo hay que
     recargar la pagina. Dos horas cubre a quien deja la pestana abierta. */
  'max_token_age' => 7200,

  /* -- Filtro de contenido --------------------------------------------- */

  /* Numero maximo de enlaces admitidos en el mensaje. El spam automatico
     mete muchos; una consulta real, uno o dos como mucho. */
  'max_links' => 3,

  /* Longitudes admitidas. Un mensaje por debajo del minimo casi siempre es
     una prueba de robot. */
  'min_message'  => 20,
  'max_message'  => 5000,
  'max_name'     => 100,
  'max_subject'  => 150,

  /* -- Registro -------------------------------------------------------- */

  /* Guardar un registro de intentos en data/log.txt. No incluye el contenido
     de los mensajes ni la IP en claro, solo un hash, la fecha y el motivo
     del rechazo. Sirve para saber si te estan atacando. */
  'log' => true,

  /* Cabecera de la que sacar la IP real cuando el sitio esta detras de un
     proxy o de Cloudflare. DEJALO VACIO si no lo estas: cualquiera puede
     inventarse esa cabecera y saltarse asi el limite por IP. Valores
     habituales: 'CF-Connecting-IP', 'X-Forwarded-For'. */
  'trusted_ip_header' => '',

);
