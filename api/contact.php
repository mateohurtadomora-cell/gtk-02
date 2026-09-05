<?php
/**
 * Punto de entrada del formulario de contacto.
 *
 *   GET  -> entrega un token firmado para el formulario que se acaba de abrir
 *   POST -> valida y envia la consulta a la direccion fijada en config.php
 *
 * Todo lo que decide algo vive en mailer.php; aqui solo se lee la peticion,
 * se aplica el orden de comprobaciones y se responde en JSON.
 *
 * Orden deliberado: primero lo que no cuesta nada (metodo, tamano, origen),
 * despues la trampa y el token, y solo al final la validacion de contenido y
 * el envio. Asi una avalancha de peticiones basura se descarta antes de tocar
 * el disco.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

/* --------------------------------------------------------------- */
/* Arranque                                                          */
/* --------------------------------------------------------------- */

/* Los avisos de PHP no deben salir por la respuesta: revelarian rutas del
   servidor y ademas romperian el JSON. Se registran, no se muestran. */
ini_set('display_errors', '0');
error_reporting(E_ALL);

$dir = __DIR__;

/* El secreto esta mas a salvo fuera de la raiz publica. Si el hosting lo
   permite, se mueve config.php a un directorio al que el servidor web no
   llegue y se apunta aqui con la variable de entorno GTK_FORM_CONFIG. Si no
   se define, se usa el config.php de esta carpeta, que protege el .htaccess. */
$configPath = getenv('GTK_FORM_CONFIG');
if (!$configPath || !is_file($configPath)) { $configPath = $dir . '/config.php'; }

if (!is_file($configPath)) {
  http_response_code(500);
  echo json_encode(array('ok' => false, 'error' => 'config'));
  exit;
}

$cfg = require $configPath;
require_once $dir . '/mailer.php';
require_once $dir . '/transport.php';

$dataDir = $dir . '/data';
if (!is_dir($dataDir)) { @mkdir($dataDir, 0700, true); }

$now = time();

/**
 * Respuesta unica. Nunca devuelve el texto que ha escrito el visitante ni el
 * motivo tecnico exacto: al que envia de buena fe no le sirve, y al que
 * prueba ataques le estaria diciendo que barrera ha tocado.
 */
function gtk_out($status, $ok, $codigo = '') {
  http_response_code($status);
  $r = array('ok' => $ok);
  if ($codigo !== '') { $r['error'] = $codigo; }
  echo json_encode($r);
  exit;
}

/* --------------------------------------------------------------- */
/* Origen                                                            */
/* --------------------------------------------------------------- */

$origen = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origen === '' && isset($_SERVER['HTTP_REFERER'])) {
  /* Sin Origin (algunas peticiones del mismo dominio no lo mandan) se
     reconstruye desde Referer. */
  $p = parse_url($_SERVER['HTTP_REFERER']);
  if (isset($p['scheme'], $p['host'])) {
    $origen = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
  }
}

$origenValido = in_array($origen, $cfg['allowed_origins'], true);
if ($origenValido) {
  header('Access-Control-Allow-Origin: ' . $origen);
  header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  if (!$origenValido) { gtk_out(403, false, 'origen'); }
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Accept, Content-Type');
  header('Access-Control-Max-Age: 600');
  http_response_code(204);
  exit;
}

if (!$origenValido) { gtk_out(403, false, 'origen'); }

/* --------------------------------------------------------------- */
/* IP del visitante                                                  */
/* --------------------------------------------------------------- */

/* Por defecto se usa la IP de la conexion. Las cabeceras tipo
   X-Forwarded-For las puede escribir cualquiera, asi que solo se leen si el
   administrador ha declarado que hay un proxy delante. */
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
if (!empty($cfg['trusted_ip_header'])) {
  $clave = 'HTTP_' . strtoupper(str_replace('-', '_', $cfg['trusted_ip_header']));
  if (!empty($_SERVER[$clave])) {
    $primera = trim(explode(',', $_SERVER[$clave])[0]);
    if (filter_var($primera, FILTER_VALIDATE_IP)) { $ip = $primera; }
  }
}
$ipHash = gtk_ip_hash($ip, $cfg['secret']);

function gtk_registrar($cfg, $dataDir, $ipHash, $resultado) {
  if (empty($cfg['log'])) { return; }
  gtk_log($dataDir, gmdate('Y-m-d H:i:s') . ' ' . $ipHash . ' ' . $resultado);
}

/* --------------------------------------------------------------- */
/* GET: entregar token                                               */
/* --------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo json_encode(array('ok' => true, 't' => gtk_token_make($cfg['secret'], $now)));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Allow: GET, POST, OPTIONS');
  gtk_out(405, false, 'metodo');
}

/* --------------------------------------------------------------- */
/* POST: enviar                                                      */
/* --------------------------------------------------------------- */

/* Corte por tamano: 64 KB sobran para cuatro campos de texto. No sustituye a
   post_max_size del php.ini, que es quien de verdad frena la subida antes de
   que llegue aqui; esto solo evita seguir procesando algo desmesurado. */
if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 65536) {
  gtk_registrar($cfg, $dataDir, $ipHash, 'rechazado tamano');
  gtk_out(413, false, 'tamano');
}

/* La trampa. El campo esta oculto por CSS y ningun visitante lo ve, asi que
   si viene relleno quien envia es un programa que rellena todo lo que
   encuentra. Se responde exito para no ensenarle donde ha fallado. */
if (!empty($_POST['website'])) {
  gtk_registrar($cfg, $dataDir, $ipHash, 'trampa');
  gtk_out(200, true);
}

$motivo = gtk_token_verify(
  isset($_POST['_t']) ? $_POST['_t'] : '',
  $cfg['secret'],
  $now,
  (int)$cfg['min_seconds_on_page'],
  (int)$cfg['max_token_age']
);
if ($motivo !== '') {
  gtk_registrar($cfg, $dataDir, $ipHash, 'rechazado ' . $motivo);
  /* Un token caducado le puede pasar a una persona que dejo la pestana
     abierta: ese caso pide recargar, no es un error generico. */
  if ($motivo === 'token_caducado') { gtk_out(409, false, 'caducado'); }
  gtk_out(403, false, 'token');
}

$v = gtk_validate($_POST, $cfg);
if (!$v['ok']) {
  gtk_registrar($cfg, $dataDir, $ipHash, 'rechazado ' . $v['reason'] . ':' . $v['field']);
  if ($v['reason'] === 'exceso_enlaces') { gtk_out(422, false, 'enlaces'); }
  gtk_out(400, false, 'datos');
}

$rl = gtk_rate_check($dataDir, $ipHash, $now, $cfg);
if (!$rl['ok']) {
  gtk_registrar($cfg, $dataDir, $ipHash, 'frenado ' . $rl['reason']);
  gtk_out(429, false, 'limite');
}

/* --------------------------------------------------------------- */
/* Envio                                                             */
/* --------------------------------------------------------------- */

$d = $v['data'];

/* is_string ademas de isset: un atacante puede mandar idioma[]=x y convertir
   el campo en un array, que al forzarlo a texto da avisos y no compara como
   uno espera. Lo mismo vale para el resto de campos, que ya lo comprueban
   dentro de gtk_validate. */
$idioma = (isset($_POST['idioma']) && is_string($_POST['idioma'])
  && preg_match('/^[a-z]{2}$/', $_POST['idioma'])) ? $_POST['idioma'] : '--';

$asunto = gtk_mime_header($cfg['subject_prefix'] . ' ' . $d['asunto']);
$cuerpo = gtk_build_body($d, array(
  'fecha'  => gmdate('Y-m-d H:i:s') . ' UTC',
  'idioma' => $idioma,
  'origen' => $origen,
  'ip'     => $ip,
));
$cabeceras = gtk_build_headers($d, $cfg);

$enviado = gtk_send_mail($cfg['to'], $asunto, $cuerpo, $cabeceras, $cfg);

if (!$enviado) {
  gtk_registrar($cfg, $dataDir, $ipHash, 'fallo envio');
  gtk_out(502, false, 'envio');
}

gtk_registrar($cfg, $dataDir, $ipHash, 'enviado');
gtk_out(200, true);
