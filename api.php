<?php
// Cuentas de la peña · API mínima. CAMBIA LA CONTRASEÑA antes de subir:
$PASSWORD = 'Secatis85#';
// true: cualquiera con el enlace puede subir fotos. false: solo el editor (con contraseña).
$FOTOS_ABIERTAS = true;

$DIR = __DIR__ . '/datos';
$FILE = $DIR . '/cuentas.json';
$FDIR = __DIR__ . '/fotos';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_dir($DIR)) { @mkdir($DIR, 0775, true); }
if (!is_dir($FDIR)) { @mkdir($FDIR, 0775, true); }

function fail($code, $msg) { http_response_code($code); echo json_encode(['error' => $msg]); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (isset($_GET['fotos'])) {
    $out = [];
    foreach ((array)glob($FDIR . '/*') as $p) {
      $n = basename($p);
      if (preg_match('/^([a-z0-9]{3,20})-(\d+)-[a-f0-9]{6}\.(jpg|png|webp|gif)$/', $n, $mm)) {
        $out[] = ['f' => $n, 'm' => $mm[1], 't' => (int)$mm[2] * 1000];
      }
    }
    usort($out, function ($a, $b) { return $a['t'] <=> $b['t']; });
    echo json_encode($out);
    exit;
  }
  echo is_file($FILE) ? '{"data":' . file_get_contents($FILE) . '}' : '{"data":null}';
  exit;
}

// Subida de una foto (multipart)
if (isset($_FILES['foto'])) {
  $ok = hash_equals($PASSWORD, (string)($_POST['pass'] ?? ''));
  if (!$FOTOS_ABIERTAS && !$ok) { usleep(500000); fail(401, 'auth'); }
  $m = (string)($_POST['match'] ?? '');
  if (!preg_match('/^[a-z0-9]{3,20}$/', $m)) fail(400, 'partido no valido');
  $f = $_FILES['foto'];
  if ($f['error'] !== UPLOAD_ERR_OK) fail(400, 'fallo en la subida');
  if ($f['size'] > 8 * 1024 * 1024) fail(413, 'la foto es demasiado grande');
  $info = @getimagesize($f['tmp_name']);
  $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
  if (!$info || !isset($ext[$info[2]])) fail(400, 'formato no permitido');
  if (count((array)glob($FDIR . '/*')) >= 500) fail(400, 'limite de fotos alcanzado');
  $name = $m . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext[$info[2]];
  if (!move_uploaded_file($f['tmp_name'], $FDIR . '/' . $name)) fail(500, 'no se pudo guardar (permisos de la carpeta fotos)');
  echo json_encode(['ok' => true, 'f' => $name]);
  exit;
}
// Foto demasiado grande para PHP: llega vacia
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/') === 0) fail(413, 'la foto supera el limite de subida de PHP');

$in = json_decode(file_get_contents('php://input'), true);
$pass = (is_array($in) && isset($in['pass'])) ? (string)$in['pass'] : '';
if (!hash_equals($PASSWORD, $pass)) {
  usleep(500000);
  http_response_code(401);
  echo '{"error":"auth"}';
  exit;
}
if (!empty($in['check'])) { echo '{"ok":true}'; exit; }

// Borrar una foto (solo editor)
if (!empty($in['delfoto'])) {
  $n = basename((string)$in['delfoto']);
  if (!preg_match('/^[a-z0-9]{3,20}-\d+-[a-f0-9]{6}\.(jpg|png|webp|gif)$/', $n)) fail(400, 'nombre no valido');
  @unlink($FDIR . '/' . $n);
  echo '{"ok":true}';
  exit;
}

$raw = isset($in['data']) ? (string)$in['data'] : '';
if ($raw === '' || strlen($raw) > 1000000 || json_decode($raw) === null) {
  http_response_code(400);
  echo '{"error":"datos"}';
  exit;
}
if (file_put_contents($FILE, $raw, LOCK_EX) === false) {
  http_response_code(500);
  echo '{"error":"escritura"}';
  exit;
}
echo '{"ok":true}';
