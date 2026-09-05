<?php
/**
 * Entrega del correo.
 *
 * Esta separado del resto a proposito: es la unica pieza que depende de como
 * este montado el hosting. Si mail() no entrega bien, se cambia solo este
 * fichero y nada mas del formulario se entera.
 */

/**
 * Devuelve true si el servidor ha aceptado el mensaje. Que lo acepte no
 * garantiza que llegue a la bandeja de entrada: eso depende de SPF, DKIM y
 * de la reputacion del servidor (ver README).
 */
function gtk_send_mail($to, $subject, $body, $headers, $cfg) {

  if (!function_exists('mail')) { return false; }

  /* El quinto parametro fija el remitente del sobre, que es lo que mira el
     receptor para comprobar SPF. Sin el, muchos hostings ponen algo como
     "apache@servidor123.hosting.com" y el correo se marca como sospechoso.
     La direccion ya viene de config.php, nunca del formulario, asi que no hay
     forma de colar opciones adicionales por aqui. */
  if (!empty($cfg['envelope_sender']) && gtk_valid_email($cfg['from'])) {
    return @mail($to, $subject, $body, $headers, '-f' . $cfg['from']);
  }

  return @mail($to, $subject, $body, $headers);
}

/* ------------------------------------------------------------------
 * SI LOS CORREOS ACABAN EN SPAM
 *
 * La causa casi siempre es que el servidor web no es el servidor de correo
 * del dominio y el mensaje no lleva firma DKIM. La solucion es enviar por
 * SMTP autenticado con la cuenta real del buzon. Para eso:
 *
 *   1. Instalar PHPMailer en el hosting (composer require phpmailer/phpmailer,
 *      o subir la carpeta a mano).
 *   2. Sustituir el cuerpo de gtk_send_mail por una llamada a PHPMailer con
 *      host, puerto, usuario y contrasena de la cuenta, guardados en
 *      config.php.
 *
 * No lo dejo escrito porque no se puede probar sin las credenciales reales
 * del buzon, y un envio de correo que no se ha probado no sirve de nada.
 * ------------------------------------------------------------------ */
