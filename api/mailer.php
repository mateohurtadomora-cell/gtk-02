<?php
/* No se ejecuta por su cuenta: solo lo incluye contact.php (o selftest.php
   al probarlo). Pedirlo por la URL no devuelve nada. */
if (!defined("GTK_FORM")) { http_response_code(404); exit; }

/**
 * Logica del formulario de contacto: tokens, validacion, limite de uso y
 * composicion del mensaje.
 *
 * Aqui no se lee ni una sola variable global de la peticion. Todo entra por
 * parametro y todo sale por retorno, para que selftest.php pueda ejercitar
 * cada caso sin levantar un servidor. La parte que si toca la peticion vive
 * en contact.php.
 *
 * PHP 7.0 en adelante.
 */

/* ------------------------------------------------------------------ */
/* Token de formulario                                                 */
/* ------------------------------------------------------------------ */

/**
 * El token es "<marca de tiempo>.<firma>". No guarda nada en el servidor:
 * la firma HMAC es lo que impide fabricarlo, y la marca de tiempo es lo que
 * permite exigir que el visitante haya estado unos segundos en la pagina.
 *
 * No se ata a la IP a proposito: en movil la IP cambia entre que se carga la
 * pagina y se envia el formulario, y eso romperia envios legitimos.
 */
function gtk_token_make($secret, $now) {
  $ts = (string)(int)$now;
  return $ts . '.' . hash_hmac('sha256', $ts, $secret);
}

/**
 * Devuelve '' si el token vale, o el motivo del rechazo.
 */
function gtk_token_verify($token, $secret, $now, $minAge, $maxAge) {
  if (!is_string($token) || $token === '') { return 'token_ausente'; }
  if (strlen($token) > 200) { return 'token_malformado'; }

  $parts = explode('.', $token);
  if (count($parts) !== 2) { return 'token_malformado'; }

  $ts  = $parts[0];
  $sig = $parts[1];
  if (!preg_match('/^\d{1,12}$/', $ts))      { return 'token_malformado'; }
  if (!preg_match('/^[a-f0-9]{64}$/', $sig)) { return 'token_malformado'; }

  /* hash_equals compara en tiempo constante: sin el, se podria averiguar la
     firma byte a byte midiendo lo que tarda en responder. */
  $esperada = hash_hmac('sha256', $ts, $secret);
  if (!hash_equals($esperada, $sig)) { return 'token_invalido'; }

  $edad = (int)$now - (int)$ts;
  if ($edad < 0)        { return 'token_futuro'; }
  if ($edad < $minAge)  { return 'demasiado_rapido'; }
  if ($edad > $maxAge)  { return 'token_caducado'; }

  return '';
}

/* ------------------------------------------------------------------ */
/* Saneado y validacion                                                */
/* ------------------------------------------------------------------ */

/**
 * Salto de linea en un campo de una sola linea. Es LA comprobacion critica
 * del script: sin ella, un atacante escribe
 *   Pepe\r\nBcc: mil@direcciones.com
 * en el campo del nombre y convierte el formulario en un emisor de spam
 * masivo. Se rechaza en vez de limpiar, porque un salto de linea ahi no
 * puede venir de una persona rellenando el formulario.
 */
function gtk_has_crlf($s) {
  return preg_match('/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F]/', $s) === 1;
}

/** Recorta y colapsa espacios. No toca acentos ni signos. */
function gtk_clean_line($s) {
  $s = str_replace("\xc2\xa0", ' ', $s);      /* espacio duro */
  $s = preg_replace('/[ \t]+/', ' ', $s);
  return trim($s);
}

/** Normaliza el cuerpo: quita retornos de carro y caracteres de control. */
function gtk_clean_text($s) {
  $s = str_replace("\r\n", "\n", $s);
  $s = str_replace("\r", "\n", $s);
  $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
  $s = preg_replace('/\n{4,}/', "\n\n\n", $s);
  return trim($s);
}

/** UTF-8 valido. Una cadena mal formada puede romper el correo o el filtro. */
function gtk_is_utf8($s) {
  return preg_match('//u', $s) === 1;
}

function gtk_valid_email($s) {
  if ($s === '' || strlen($s) > 254) { return false; }
  if (gtk_has_crlf($s)) { return false; }
  return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
}

/** Cuenta enlaces en el mensaje. */
function gtk_count_links($s) {
  $n = preg_match_all('~(https?://|www\.)~i', $s, $m);
  return $n === false ? 0 : $n;
}

/**
 * Longitud en caracteres, no en bytes: con acentos, strlen cuenta de mas y
 * rechazaria mensajes validos.
 */
function gtk_len($s) {
  if (function_exists('mb_strlen')) { return mb_strlen($s, 'UTF-8'); }
  return strlen(preg_replace('/[\x80-\xBF]/', '', $s));
}

/**
 * Valida los campos que llegan del formulario.
 *
 * Devuelve array('ok'=>bool, 'reason'=>string, 'field'=>string, 'data'=>array).
 * 'reason' es un codigo interno; nunca se le enseña al visitante tal cual.
 */
function gtk_validate($in, $cfg) {
  $mal = function ($reason, $field) {
    return array('ok' => false, 'reason' => $reason, 'field' => $field, 'data' => array());
  };

  $campos = array('nombre', 'email', 'asunto', 'mensaje');
  foreach ($campos as $c) {
    if (!isset($in[$c]) || !is_string($in[$c])) { return $mal('campo_ausente', $c); }
    if (!gtk_is_utf8($in[$c]))                  { return $mal('codificacion', $c); }
  }

  $nombre  = gtk_clean_line($in['nombre']);
  $email   = gtk_clean_line($in['email']);
  $asunto  = gtk_clean_line($in['asunto']);
  $mensaje = gtk_clean_text($in['mensaje']);

  /* Cabeceras: los tres campos de una linea no pueden llevar saltos. */
  if (gtk_has_crlf($nombre)) { return $mal('inyeccion_cabecera', 'nombre'); }
  if (gtk_has_crlf($email))  { return $mal('inyeccion_cabecera', 'email'); }
  if (gtk_has_crlf($asunto)) { return $mal('inyeccion_cabecera', 'asunto'); }

  if (gtk_len($nombre) < 2 || gtk_len($nombre) > $cfg['max_name']) {
    return $mal('longitud', 'nombre');
  }
  if (!gtk_valid_email($email)) { return $mal('email_invalido', 'email'); }
  if (gtk_len($asunto) < 2 || gtk_len($asunto) > $cfg['max_subject']) {
    return $mal('longitud', 'asunto');
  }
  if (gtk_len($mensaje) < $cfg['min_message'] || gtk_len($mensaje) > $cfg['max_message']) {
    return $mal('longitud', 'mensaje');
  }

  /* La casilla de terminos: el navegador ya la exige, pero quien envia por
     fuera del navegador se la salta, y la aceptacion tiene que constar. */
  $acepta = (isset($in['acepta_terminos']) && is_string($in['acepta_terminos']))
    ? $in['acepta_terminos'] : '';
  if ($acepta !== 'on' && $acepta !== '1' && $acepta !== 'true') {
    return $mal('sin_consentimiento', 'acepta_terminos');
  }

  if (gtk_count_links($mensaje) > $cfg['max_links']) {
    return $mal('exceso_enlaces', 'mensaje');
  }

  return array('ok' => true, 'reason' => '', 'field' => '', 'data' => array(
    'nombre'  => $nombre,
    'email'   => $email,
    'asunto'  => $asunto,
    'mensaje' => $mensaje,
  ));
}

/* ------------------------------------------------------------------ */
/* Limite de uso                                                       */
/* ------------------------------------------------------------------ */

/**
 * La IP no se guarda en claro: se guarda su HMAC. Sirve igual para contar y
 * deja de ser un dato personal legible si alguien accede al fichero.
 */
function gtk_ip_hash($ip, $secret) {
  return substr(hash_hmac('sha256', $ip, $secret), 0, 32);
}

/**
 * Cuenta y registra envios. Un unico fichero JSON con bloqueo exclusivo, que
 * es lo que hace que dos peticiones simultaneas no se pisen el conteo.
 *
 * Devuelve array('ok'=>bool, 'reason'=>string). Cuando ok es true el envio
 * ya queda anotado: se llama justo antes de enviar, no despues.
 */
function gtk_rate_check($dir, $ipHash, $now, $cfg) {
  $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'rate.json';

  $fh = @fopen($file, 'c+');
  if ($fh === false) {
    /* Sin fichero de conteo no hay freno: es preferible no enviar. */
    return array('ok' => false, 'reason' => 'almacen_no_disponible');
  }
  if (!flock($fh, LOCK_EX)) {
    fclose($fh);
    return array('ok' => false, 'reason' => 'almacen_bloqueado');
  }

  $raw  = stream_get_contents($fh);
  $data = $raw === '' ? array() : json_decode($raw, true);
  if (!is_array($data)) { $data = array(); }
  if (!isset($data['ips']) || !is_array($data['ips']))       { $data['ips'] = array(); }
  if (!isset($data['global']) || !is_array($data['global'])) { $data['global'] = array(); }

  $dia = 86400;

  /* Poda: fuera todo lo de hace mas de un dia. Sin esto el fichero crece sin
     limite. */
  foreach ($data['ips'] as $k => $marcas) {
    $vivas = array();
    foreach ($marcas as $m) { if ($now - $m < $dia) { $vivas[] = $m; } }
    if (count($vivas)) { $data['ips'][$k] = $vivas; } else { unset($data['ips'][$k]); }
  }
  $globalVivas = array();
  foreach ($data['global'] as $m) { if ($now - $m < 3600) { $globalVivas[] = $m; } }
  $data['global'] = $globalVivas;

  /* Tope duro de entradas, por si alguien pasa por muchisimas IP. Se tiran
     las mas antiguas. */
  if (count($data['ips']) > 5000) {
    $data['ips'] = array_slice($data['ips'], -2500, null, true);
  }

  $mias = isset($data['ips'][$ipHash]) ? $data['ips'][$ipHash] : array();

  $enVentana = 0;
  foreach ($mias as $m) { if ($now - $m < $cfg['ip_window_seconds']) { $enVentana++; } }

  $resultado = array('ok' => true, 'reason' => '');
  if (count($data['global']) >= $cfg['max_global_hour']) {
    $resultado = array('ok' => false, 'reason' => 'tope_global');
  } elseif ($enVentana >= $cfg['max_per_ip_window']) {
    $resultado = array('ok' => false, 'reason' => 'demasiados_seguidos');
  } elseif (count($mias) >= $cfg['max_per_ip_day']) {
    $resultado = array('ok' => false, 'reason' => 'tope_diario');
  }

  if ($resultado['ok']) {
    $mias[] = $now;
    $data['ips'][$ipHash] = $mias;
    $data['global'][] = $now;
  }

  /* Se reescribe siempre, aunque se rechace: la poda tiene que persistir. */
  rewind($fh);
  ftruncate($fh, 0);
  fwrite($fh, json_encode($data));
  fflush($fh);
  flock($fh, LOCK_UN);
  fclose($fh);

  return $resultado;
}

/* ------------------------------------------------------------------ */
/* Composicion del correo                                              */
/* ------------------------------------------------------------------ */

/**
 * Codifica una cabecera con acentos segun RFC 2047. Sin esto, un asunto con
 * "ñ" llega ilegible.
 */
function gtk_mime_header($s) {
  if (preg_match('/^[\x20-\x7E]*$/', $s)) { return $s; }
  return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/**
 * Cuerpo del correo, en texto plano. No se genera HTML a proposito: el
 * contenido lo escribe un desconocido, y en texto plano no hay nada que un
 * lector de correo pueda interpretar como marcado.
 */
function gtk_build_body($d, $meta) {
  $l = array();
  $l[] = 'Nueva consulta desde el formulario de la web.';
  $l[] = '';
  $l[] = 'Nombre:  ' . $d['nombre'];
  $l[] = 'Correo:  ' . $d['email'];
  $l[] = 'Asunto:  ' . $d['asunto'];
  $l[] = '';
  $l[] = '--- Mensaje ---';
  $l[] = $d['mensaje'];
  $l[] = '--- Fin del mensaje ---';
  $l[] = '';
  $l[] = 'Acepto los terminos de uso: si';
  $l[] = 'Fecha:   ' . $meta['fecha'];
  $l[] = 'Idioma:  ' . $meta['idioma'];
  $l[] = 'Origen:  ' . $meta['origen'];
  $l[] = 'IP:      ' . $meta['ip'];
  $l[] = '';
  $l[] = 'Responda a este correo para contestar directamente al remitente.';
  return implode("\r\n", $l) . "\r\n";
}

/**
 * Cabeceras del mensaje. El remitente es siempre una direccion propia; la del
 * visitante va en Reply-To y solo despues de haber pasado gtk_valid_email.
 */
function gtk_build_headers($d, $cfg) {
  $h = array();
  $h[] = 'From: ' . gtk_mime_header($cfg['from_name']) . ' <' . $cfg['from'] . '>';
  $h[] = 'Reply-To: ' . gtk_mime_header($d['nombre']) . ' <' . $d['email'] . '>';
  $h[] = 'MIME-Version: 1.0';
  $h[] = 'Content-Type: text/plain; charset=UTF-8';
  $h[] = 'Content-Transfer-Encoding: 8bit';
  $h[] = 'X-Mailer: gtk-web';
  $h[] = 'Auto-Submitted: auto-generated';
  return implode("\r\n", $h);
}

/* ------------------------------------------------------------------ */
/* Registro                                                            */
/* ------------------------------------------------------------------ */

/**
 * Una linea por intento. Sin contenido del mensaje y sin IP en claro: lo
 * justo para detectar un ataque. Rota al llegar a 1 MB.
 */
function gtk_log($dir, $linea) {
  $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'log.txt';
  if (is_file($file) && filesize($file) > 1048576) {
    @rename($file, $file . '.1');
  }
  @file_put_contents($file, $linea . "\n", FILE_APPEND | LOCK_EX);
}
