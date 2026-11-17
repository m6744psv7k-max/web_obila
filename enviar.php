<?php
/* Óbila Club de Basket · recibe el formulario de inscripción y lo envía por correo.
   Subir a la misma carpeta que index.html (httpdocs/ o httpdocs/Nueva/).
   DIAGNÓSTICO: con la sesión de WordPress iniciada como administrador, abre
   https://obila.com/enviar.php?diag=1 y te dice si el correo sale y por qué falla. */
ob_start();
@ini_set('display_errors', '0');
const DESTINO = 'obilacenter@gmail.com';
$ERR = '';
function salida($arr, $code = 200) {
  if (ob_get_length()) ob_clean();
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit;
}
function fin($ok, $msg = '', $code = 200) { salida(['ok' => $ok, 'msg' => $msg], $code); }
set_exception_handler(function ($e) { fin(false, 'Error del servidor: ' . $e->getMessage(), 500); });
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    if (ob_get_length()) ob_clean();
    http_response_code(500); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Error PHP: ' . $e['message']], JSON_UNESCAPED_UNICODE);
  }
});
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { salida([]); }

// Cargar WordPress (si está) para usar su configuración de correo
$obila_wpload = is_file(__DIR__ . '/wp-load.php') ? __DIR__ . '/wp-load.php' : dirname(__DIR__) . '/wp-load.php';
$wpok = false;
if (is_file($obila_wpload)) { try { require_once $obila_wpload; $wpok = function_exists('wp_mail'); } catch (Throwable $e) { $ERR = 'wp-load: ' . $e->getMessage(); } }
if ($wpok) add_action('wp_mail_failed', function ($er) { global $ERR; $ERR = $er->get_error_message(); });

function enviar_correo($asunto, $cuerpo, $cab = []) {
  global $wpok, $ERR;
  if ($wpok) {
    $h = array_merge(['Content-Type: text/plain; charset=UTF-8'], $cab);
    if (wp_mail(DESTINO, $asunto, $cuerpo, $h)) return true;
  }
  // Plan B: mail() nativo de PHP
  $dom = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
  $hh = "From: Web Óbila <no-reply@$dom>\r\nContent-Type: text/plain; charset=UTF-8\r\n" . implode("\r\n", $cab);
  $ok = @mail(DESTINO, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $hh);
  if (!$ok && !$ERR) $ERR = 'mail() de PHP devolvió error (el servidor no puede enviar correo)';
  return $ok;
}

// Modo diagnóstico (solo administrador de WordPress)
if (isset($_GET['diag'])) {
  $adm = $wpok && function_exists('current_user_can') && current_user_can('manage_options');
  if (!$adm) fin(false, 'Inicia sesión como administrador de WordPress en este navegador y vuelve a abrir esta dirección.', 403);
  $ok = enviar_correo('Prueba de correo · Web Óbila', "Si lees esto, el envío funciona.\n" . date('c'));
  salida(['ok' => $ok, 'php' => PHP_VERSION, 'wordpress_cargado' => $wpok, 'error' => $ERR, 'destino' => DESTINO]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fin(false, 'Método no permitido', 405);

// Anti-spam: campo trampa, tiempo mínimo y límite por IP
if (!empty($_POST['web'])) fin(true);
$t = isset($_POST['t']) ? (int)$_POST['t'] : 0;
if ($t && (time() * 1000 - $t) < 3000) fin(true);
if ($wpok && function_exists('get_transient')) {
  $k = 'obila_insc_' . md5($_SERVER['REMOTE_ADDR'] ?? '0');
  $n = (int) get_transient($k);
  if ($n >= 5) fin(false, 'Demasiados envíos. Inténtalo más tarde.', 429);
  set_transient($k, $n + 1, 3600);
}

function limpia($v, $max = 300) {
  $v = trim(strip_tags((string)$v));
  $v = preg_replace('/[\r\n]+/', ' ', $v);
  return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}
$nombre = limpia($_POST['name'] ?? ''); $edad = limpia($_POST['age'] ?? ''); $contacto = limpia($_POST['contact'] ?? '');
if ($nombre === '' || $contacto === '') fin(false, 'Faltan datos', 400);
if (empty($_POST['priv'])) fin(false, 'Debes aceptar la política de privacidad', 400);

$cuerpo = "Nueva solicitud de inscripción desde la web\n\n"
  . "Nombre del jugador/a: $nombre\nEdad: $edad\nEmail o teléfono de contacto: $contacto\n\n"
  . "Política de privacidad aceptada (y, si es menor de 14 años, declaración del padre/madre/tutor): sí\n"
  . 'Fecha: ' . date('d/m/Y H:i') . " (hora del servidor)\n";
$cab = filter_var($contacto, FILTER_VALIDATE_EMAIL) ? ['Reply-To: ' . $contacto] : [];
if (!enviar_correo('Nueva solicitud de inscripción · Web Óbila', $cuerpo, $cab)) {
  fin(false, 'No se pudo enviar el correo' . ($ERR ? ' (' . $ERR . ')' : ''), 500);
}
fin(true);
