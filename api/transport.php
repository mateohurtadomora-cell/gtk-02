<?php
/* No se ejecuta por su cuenta: solo lo incluye contact.php (o selftest.php
   al probarlo). Pedirlo por la URL no devuelve nada. */
if (!defined("GTK_FORM")) { http_response_code(404); exit; }

/**
 * Entrega del correo.
 *
 * Dos modos, elegidos en config.php con la clave 'transport':
 *
 *   'mail' -> la funcion mail() de PHP. Solo sirve si el servidor tiene un
 *             MTA instalado (postfix, sendmail). En un VPS recien montado no
 *             lo hay, y mail() devuelve false sin mas explicacion.
 *
 *   'smtp' -> conexion directa al servidor de correo del dominio, autenticada
 *             con la cuenta real del buzon. Es la opcion recomendada: el
 *             mensaje sale del servidor que el SPF del dominio autoriza y con
 *             la firma DKIM que ese servidor ponga, asi que no acaba en spam.
 *
 * Esta pieza esta separada del resto a proposito: es la unica que depende de
 * como este montado el hosting.
 */

/**
 * Ultimo error de entrega, para dejarlo en el registro. Nunca incluye la
 * contrasena: solo el paso que fallo y lo que contesto el servidor.
 */
function gtk_mail_error($nuevo = null) {
  static $error = '';
  if ($nuevo !== null) { $error = $nuevo; }
  return $error;
}

function gtk_send_mail($to, $subject, $body, $headers, $cfg) {
  $modo = isset($cfg['transport']) ? $cfg['transport'] : 'mail';

  if ($modo === 'smtp') {
    return gtk_send_smtp($to, $subject, $body, $headers, $cfg);
  }

  if (!function_exists('mail')) {
    gtk_mail_error('mail() no existe');
    return false;
  }

  /* El quinto parametro fija el remitente del sobre, que es lo que mira el
     receptor para comprobar SPF. La direccion sale de config.php, nunca del
     formulario, asi que no hay forma de colar opciones por aqui. */
  if (!empty($cfg['envelope_sender']) && gtk_valid_email($cfg['from'])) {
    $ok = @mail($to, $subject, $body, $headers, '-f' . $cfg['from']);
  } else {
    $ok = @mail($to, $subject, $body, $headers);
  }

  if (!$ok) { gtk_mail_error('mail() devolvio false (falta MTA?)'); }
  return $ok;
}

/* ------------------------------------------------------------------ */
/* SMTP                                                                */
/* ------------------------------------------------------------------ */

/**
 * Lee una respuesta completa del servidor. Puede venir en varias lineas:
 * las intermedias llevan guion tras el codigo (250-XXXX) y la ultima un
 * espacio (250 XXXX).
 */
function gtk_smtp_leer($fh) {
  $todo = '';
  while (($linea = fgets($fh, 1024)) !== false) {
    $todo .= $linea;
    if (strlen($linea) >= 4 && $linea[3] === ' ') { break; }
  }
  return $todo;
}

/** Envia una orden y comprueba el codigo de respuesta. */
function gtk_smtp_orden($fh, $orden, $esperado, $paso) {
  if ($orden !== null) {
    if (fwrite($fh, $orden . "\r\n") === false) {
      gtk_mail_error($paso . ': no se pudo escribir');
      return false;
    }
  }
  $r = gtk_smtp_leer($fh);
  $codigo = (int)substr($r, 0, 3);
  if (!in_array($codigo, (array)$esperado, true)) {
    /* Se guarda el codigo y el texto, recortado. Nunca la orden enviada:
       en el paso de la autenticacion llevaria la contrasena. */
    gtk_mail_error($paso . ': ' . $codigo . ' ' . substr(trim(str_replace("\r\n", ' ', $r)), 0, 120));
    return false;
  }
  return true;
}

function gtk_send_smtp($to, $subject, $body, $headers, $cfg) {
  $host    = isset($cfg['smtp_host'])    ? $cfg['smtp_host']    : '';
  $puerto  = isset($cfg['smtp_port'])    ? (int)$cfg['smtp_port'] : 587;
  $seguro  = isset($cfg['smtp_secure'])  ? $cfg['smtp_secure']  : 'tls';
  $usuario = isset($cfg['smtp_user'])    ? $cfg['smtp_user']    : '';
  $clave   = isset($cfg['smtp_pass'])    ? $cfg['smtp_pass']    : '';
  $espera  = isset($cfg['smtp_timeout']) ? (int)$cfg['smtp_timeout'] : 20;

  if ($host === '' || $usuario === '') {
    gtk_mail_error('smtp: falta host o usuario en config.php');
    return false;
  }

  /* 'ssl' es TLS desde el primer byte (puerto 465). 'tls' empieza en claro y
     sube a cifrado con STARTTLS (puerto 587). */
  $destino = ($seguro === 'ssl' ? 'ssl://' : '') . $host . ':' . $puerto;

  $ctx = stream_context_create(array('ssl' => array(
    'verify_peer'       => true,
    'verify_peer_name'  => true,
    'allow_self_signed' => false,
  )));

  $errno = 0; $errstr = '';
  $fh = @stream_socket_client($destino, $errno, $errstr, $espera,
    STREAM_CLIENT_CONNECT, $ctx);

  if (!$fh) {
    gtk_mail_error('smtp: no conecta con ' . $host . ':' . $puerto . ' (' . $errstr . ')');
    return false;
  }
  stream_set_timeout($fh, $espera);

  $quienSoy = isset($cfg['smtp_helo']) && $cfg['smtp_helo'] !== ''
    ? $cfg['smtp_helo']
    : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');

  $ok = gtk_smtp_orden($fh, null, 220, 'saludo')
     && gtk_smtp_orden($fh, 'EHLO ' . $quienSoy, 250, 'ehlo');

  if ($ok && $seguro === 'tls') {
    $ok = gtk_smtp_orden($fh, 'STARTTLS', 220, 'starttls');
    if ($ok) {
      /* CLIENT constante compuesta: cubre TLS 1.0 a 1.3 segun lo que tenga la
         version de PHP del servidor. */
      $metodo = STREAM_CRYPTO_METHOD_TLS_CLIENT;
      if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $metodo |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
      }
      if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
        $metodo |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
      }
      if (!@stream_socket_enable_crypto($fh, true, $metodo)) {
        gtk_mail_error('smtp: fallo al cifrar la conexion');
        $ok = false;
      } else {
        $ok = gtk_smtp_orden($fh, 'EHLO ' . $quienSoy, 250, 'ehlo cifrado');
      }
    }
  }

  if ($ok) {
    $ok = gtk_smtp_orden($fh, 'AUTH LOGIN', 334, 'auth')
       && gtk_smtp_orden($fh, base64_encode($usuario), 334, 'usuario')
       && gtk_smtp_orden($fh, base64_encode($clave), 235, 'contrasena');
  }

  if ($ok) {
    $ok = gtk_smtp_orden($fh, 'MAIL FROM:<' . $cfg['from'] . '>', 250, 'remitente')
       && gtk_smtp_orden($fh, 'RCPT TO:<' . $to . '>', array(250, 251), 'destinatario')
       && gtk_smtp_orden($fh, 'DATA', 354, 'data');
  }

  if ($ok) {
    /* El mensaje ya termina en el punto solo que cierra DATA, asi que aqui
       solo queda leer la respuesta: mandar otro punto seria un renglon de
       mas. */
    $mensaje = gtk_smtp_mensaje($to, $subject, $body, $headers, $cfg);
    fwrite($fh, $mensaje);
    $ok = gtk_smtp_orden($fh, null, 250, 'entrega');
  }

  @fwrite($fh, "QUIT\r\n");
  @fclose($fh);

  if ($ok) { gtk_mail_error(''); }
  return $ok;
}

/**
 * Monta el mensaje tal cual viaja dentro de DATA. Con mail() estas cabeceras
 * las pone PHP; por SMTP hay que escribirlas a mano.
 */
function gtk_smtp_mensaje($to, $subject, $body, $headers, $cfg) {
  $lineas = array();
  $lineas[] = 'Date: ' . date('r');
  $lineas[] = 'To: <' . $to . '>';
  $lineas[] = 'Subject: ' . $subject;
  $lineas[] = 'Message-ID: <' . bin2hex(gtk_bytes(8)) . '@' . gtk_dominio_de($cfg['from']) . '>';

  $completo = implode("\r\n", $lineas) . "\r\n" . $headers . "\r\n\r\n";

  /* Ningun renglon puede pasar de 998 octetos (RFC 5322). Un visitante que
     escriba un parrafo largo de una tirada lo haria. */
  $cuerpo = wordwrap($body, 900, "\r\n", true);

  /* Un punto solo al principio de renglon cierra el DATA: hay que doblarlo. */
  $cuerpo = preg_replace('/^\./m', '..', $cuerpo);

  return $completo . $cuerpo . "\r\n.\r\n";
}

function gtk_dominio_de($direccion) {
  $p = strpos($direccion, '@');
  return $p === false ? 'localhost' : substr($direccion, $p + 1);
}

function gtk_bytes($n) {
  if (function_exists('random_bytes'))            { return random_bytes($n); }
  if (function_exists('openssl_random_pseudo_bytes')) { return openssl_random_pseudo_bytes($n); }
  $s = '';
  for ($i = 0; $i < $n; $i++) { $s .= chr(mt_rand(0, 255)); }
  return $s;
}
