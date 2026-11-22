<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
define('PUB', ROOT . '/public');

$config = [
    'db_path'    => getenv('DB_PATH') ?: ROOT . '/storage/database.sqlite',
    'app_key'    => getenv('APP_KEY') ?: '',
    'debug'      => getenv('APP_DEBUG') === '1',
    'upload_dir' => PUB . '/uploads',
];
if (is_file(ROOT . '/config.php')) {
    $config = array_merge($config, require ROOT . '/config.php');
}
$GLOBALS['config'] = $config;

date_default_timezone_set('Asia/Ho_Chi_Minh');
mb_internal_encoding('UTF-8');

ini_set('display_errors', $config['debug'] ? '1' : '0');
error_reporting(E_ALL);

require APP . '/helpers.php';
require APP . '/db.php';
require APP . '/auth.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('vo_sess');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'path'     => '/',
    ]);
    session_start();
}
