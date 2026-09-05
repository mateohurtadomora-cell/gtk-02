<?php
/**
 * Comprobacion del formulario. Ejercita las defensas una a una contra los
 * ataques que pretenden parar. No envia ningun correo y no toca los datos de
 * produccion: el limite de uso se prueba en un directorio temporal.
 *
 * Se ejecuta en el hosting, por linea de comandos:
 *
 *   php api/selftest.php
 *
 * o abriendolo en el navegador. BORRA ESTE FICHERO del servidor cuando
 * termines: no expone nada, pero no pinta nada en produccion.
 */

require_once __DIR__ . '/mailer.php';

$esWeb = PHP_SAPI !== 'cli';
if ($esWeb) { header('Content-Type: text/plain; charset=utf-8'); }

$bien = 0;
$mal  = 0;

function comprueba($descripcion, $condicion) {
  global $bien, $mal;
  if ($condicion) { $bien++; echo "  ok   $descripcion\n"; }
  else            { $mal++;  echo "  MAL  $descripcion\n"; }
}

$secreto = 'secreto-de-prueba-no-usar-en-produccion';
$ahora   = 1000000;

$cfg = array(
  'max_name' => 100, 'max_subject' => 150,
  'min_message' => 20, 'max_message' => 5000, 'max_links' => 3,
  'max_per_ip_window' => 3, 'ip_window_seconds' => 900,
  'max_per_ip_day' => 10, 'max_global_hour' => 40,
);

/* Un envio que tiene que pasar todas las comprobaciones. */
function envio_valido($cambios = array()) {
  $base = array(
    'nombre'  => 'María Pérez',
    'email'   => 'maria@ejemplo.com',
    'asunto'  => 'Consulta sobre alianzas',
    'mensaje' => 'Buenos días, nos interesa hablar de internacionalización.',
    'acepta_terminos' => 'on',
  );
  return array_merge($base, $cambios);
}

echo "\n== Token ==\n";

$t = gtk_token_make($secreto, $ahora);
comprueba('token recien hecho se rechaza por demasiado rapido',
  gtk_token_verify($t, $secreto, $ahora, 4, 7200) === 'demasiado_rapido');
comprueba('token con cuatro segundos de antiguedad vale',
  gtk_token_verify($t, $secreto, $ahora + 5, 4, 7200) === '');
comprueba('token caducado se rechaza',
  gtk_token_verify($t, $secreto, $ahora + 8000, 4, 7200) === 'token_caducado');
comprueba('token firmado con otro secreto se rechaza',
  gtk_token_verify(gtk_token_make('otro-secreto', $ahora), $secreto, $ahora + 5, 4, 7200) === 'token_invalido');
comprueba('token inventado se rechaza',
  gtk_token_verify('1000000.' . str_repeat('a', 64), $secreto, $ahora + 5, 4, 7200) === 'token_invalido');
comprueba('token vacio se rechaza',
  gtk_token_verify('', $secreto, $ahora + 5, 4, 7200) === 'token_ausente');
comprueba('token con formato raro se rechaza',
  gtk_token_verify('no-es-un-token', $secreto, $ahora + 5, 4, 7200) === 'token_malformado');
comprueba('marca de tiempo adelantada se rechaza',
  gtk_token_verify(gtk_token_make($secreto, $ahora + 900), $secreto, $ahora, 4, 7200) === 'token_futuro');

echo "\n== Inyeccion de cabeceras ==\n";

$ataques = array(
  "Pepe\r\nBcc: victima@ejemplo.com",
  "Pepe\nCc: victima@ejemplo.com",
  "Pepe\rContent-Type: text/html",
  "Pepe\r\n\r\nCuerpo falso del mensaje",
);
foreach ($ataques as $i => $a) {
  $r = gtk_validate(envio_valido(array('nombre' => $a)), $cfg);
  comprueba('salto de linea en el nombre, caso ' . ($i + 1),
    !$r['ok'] && $r['reason'] === 'inyeccion_cabecera');
}
$r = gtk_validate(envio_valido(array('asunto' => "Hola\r\nBcc: otro@ejemplo.com")), $cfg);
comprueba('salto de linea en el asunto', !$r['ok'] && $r['reason'] === 'inyeccion_cabecera');

$r = gtk_validate(envio_valido(array('email' => "a@b.com\r\nBcc: otro@ejemplo.com")), $cfg);
comprueba('salto de linea en el correo', !$r['ok']);

comprueba('gtk_has_crlf detecta el byte nulo', gtk_has_crlf("a\x00b"));
comprueba('gtk_has_crlf no marca texto normal', !gtk_has_crlf('Texto normal con acentos: ñáé'));

echo "\n== Correo electronico ==\n";

comprueba('correo normal vale',            gtk_valid_email('maria@ejemplo.com'));
comprueba('correo sin arroba no vale',     !gtk_valid_email('mariaejemplo.com'));
comprueba('correo vacio no vale',          !gtk_valid_email(''));
comprueba('correo con salto no vale',      !gtk_valid_email("a@b.com\nBcc: x@y.com"));
comprueba('correo larguisimo no vale',     !gtk_valid_email(str_repeat('a', 250) . '@b.com'));

echo "\n== Contenido ==\n";

$r = gtk_validate(envio_valido(), $cfg);
comprueba('un envio legitimo pasa', $r['ok']);
comprueba('los acentos sobreviven', $r['ok'] && $r['data']['nombre'] === 'María Pérez');

$r = gtk_validate(envio_valido(array('acepta_terminos' => '')), $cfg);
comprueba('sin aceptar los terminos no pasa', !$r['ok'] && $r['reason'] === 'sin_consentimiento');

$r = gtk_validate(envio_valido(array('mensaje' => 'corto')), $cfg);
comprueba('mensaje demasiado corto no pasa', !$r['ok'] && $r['reason'] === 'longitud');

$r = gtk_validate(envio_valido(array('mensaje' => str_repeat('a', 6000))), $cfg);
comprueba('mensaje desmesurado no pasa', !$r['ok'] && $r['reason'] === 'longitud');

$r = gtk_validate(envio_valido(array('nombre' => 'A')), $cfg);
comprueba('nombre de una letra no pasa', !$r['ok'] && $r['reason'] === 'longitud');

$spam = 'Compra aqui http://a.com http://b.com http://c.com http://d.com ahora mismo.';
$r = gtk_validate(envio_valido(array('mensaje' => $spam)), $cfg);
comprueba('mensaje con cuatro enlaces no pasa', !$r['ok'] && $r['reason'] === 'exceso_enlaces');

$r = gtk_validate(envio_valido(array('mensaje' => 'Le escribo desde https://ejemplo.com para consultarles una alianza.')), $cfg);
comprueba('mensaje con un enlace si pasa', $r['ok']);

$r = gtk_validate(array('nombre' => 'X'), $cfg);
comprueba('faltan campos y no pasa', !$r['ok'] && $r['reason'] === 'campo_ausente');

$r = gtk_validate(envio_valido(array('nombre' => "\xC3\x28")), $cfg);
comprueba('UTF-8 mal formado no pasa', !$r['ok'] && $r['reason'] === 'codificacion');

$r = gtk_validate(envio_valido(array('mensaje' => "Línea uno.\r\n\r\n\r\n\r\n\r\nLínea dos, con texto suficiente.")), $cfg);
comprueba('se normalizan los saltos del mensaje',
  $r['ok'] && strpos($r['data']['mensaje'], "\r") === false);

echo "\n== Longitud con acentos ==\n";

comprueba('un nombre de 100 letras acentuadas cabe',
  gtk_validate(envio_valido(array('nombre' => str_repeat('ñ', 100))), $cfg)['ok']);
comprueba('uno de 101 no cabe',
  !gtk_validate(envio_valido(array('nombre' => str_repeat('ñ', 101))), $cfg)['ok']);

echo "\n== Limite de uso ==\n";

$tmp = sys_get_temp_dir() . '/gtk_selftest_' . getmypid();
@mkdir($tmp, 0700, true);

$hash = gtk_ip_hash('203.0.113.9', $secreto);
comprueba('la IP no se guarda en claro', strpos($hash, '203.0.113') === false);
comprueba('la misma IP da el mismo hash', $hash === gtk_ip_hash('203.0.113.9', $secreto));
comprueba('otra IP da otro hash', $hash !== gtk_ip_hash('203.0.113.10', $secreto));

$ok = 0; $frenados = 0;
for ($i = 0; $i < 6; $i++) {
  $r = gtk_rate_check($tmp, $hash, $ahora + $i, $cfg);
  if ($r['ok']) { $ok++; } else { $frenados++; }
}
comprueba('a la cuarta seguida se frena', $ok === 3 && $frenados === 3);

$otra = gtk_ip_hash('198.51.100.7', $secreto);
comprueba('otra IP no arrastra el freno de la primera',
  gtk_rate_check($tmp, $otra, $ahora + 10, $cfg)['ok']);

comprueba('pasada la ventana se vuelve a permitir',
  gtk_rate_check($tmp, $hash, $ahora + 1000, $cfg)['ok']);

$cfgTope = array_merge($cfg, array('max_global_hour' => 1));
$tmp2 = $tmp . '_g';
@mkdir($tmp2, 0700, true);
gtk_rate_check($tmp2, $hash, $ahora, $cfgTope);
comprueba('el tope global corta aunque cambie la IP',
  !gtk_rate_check($tmp2, $otra, $ahora + 1, $cfgTope)['ok']);

foreach (array($tmp, $tmp2) as $d) {
  foreach (glob($d . '/*') as $f) { @unlink($f); }
  @rmdir($d);
}

echo "\n== Cabeceras del correo ==\n";

$d = envio_valido();
$v = gtk_validate($d, $cfg);
$cabeceras = gtk_build_headers($v['data'], array(
  'from' => 'web@globaltk.com', 'from_name' => 'Formulario web GTK',
));
comprueba('el remitente es el del dominio propio',
  strpos($cabeceras, 'From: Formulario web GTK <web@globaltk.com>') !== false);
comprueba('el visitante va en Reply-To',
  strpos($cabeceras, 'Reply-To:') !== false && strpos($cabeceras, 'maria@ejemplo.com') !== false);
comprueba('el correo se envia en texto plano',
  strpos($cabeceras, 'text/plain') !== false && stripos($cabeceras, 'text/html') === false);

comprueba('un asunto con acentos se codifica',
  gtk_mime_header('Consulta sobre diseño') === '=?UTF-8?B?' . base64_encode('Consulta sobre diseño') . '?=');
comprueba('un asunto en ASCII se deja tal cual',
  gtk_mime_header('Simple subject') === 'Simple subject');

$cuerpo = gtk_build_body($v['data'], array(
  'fecha' => '2026-01-01 00:00:00 UTC', 'idioma' => 'es',
  'origen' => 'https://www.globaltk.com', 'ip' => '203.0.113.9',
));
comprueba('el cuerpo lleva el mensaje', strpos($cuerpo, 'internacionalización') !== false);
comprueba('el cuerpo deja constancia del consentimiento',
  strpos($cuerpo, 'Acepto los terminos de uso: si') !== false);

echo "\n-------------------------------------------\n";
echo "  $bien correctas, $mal fallidas\n";
echo "-------------------------------------------\n\n";

if (!$esWeb) { exit($mal === 0 ? 0 : 1); }
