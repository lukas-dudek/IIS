<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Core\Upload;
use App\Core\View;
use App\Core\Authorization;
use App\Repositories\CategoryRepository;
use App\Repositories\UserRepository;
use App\Repositories\VenueRepository;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\UserService;
use App\Services\VenueService;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/app/Support/helpers.php';
require APP_ROOT . '/app/Support/dates.php';
require APP_ROOT . '/app/Support/slug.php';
require APP_ROOT . '/app/Support/venue_form.php';
require APP_ROOT . '/app/Support/category_form.php';

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    throw new RuntimeException('Chybí config/config.php. Vytvoř ho z config/config.example.php.');
}

$config = new Config(require $configFile);
$basePath = (string) $config->get('app.base_path', '');
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $basePath === '/' ? '' : rtrim($basePath, '/'));
}

date_default_timezone_set((string) $config->get('app.timezone', 'Europe/Prague'));

$debug = (bool) $config->get('app.debug', false);
$logDir = (string) $config->get('log.dir', APP_ROOT . '/storage/logs');
if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    throw new RuntimeException('Nelze vytvořit adresář pro logy: ' . $logDir);
}
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php-' . gmdate('Y-m-d') . '.log');

Session::start(
    (int) $config->get('session.timeout', 1800),
    (bool) $config->get('session.secure', false),
    (string) $config->get('session.name', 'iis_session'),
    BASE_PATH === '' ? '/' : BASE_PATH
);

$database = new Database($config);
$users = new UserRepository($database);
$auth = new Auth($users);
$authService = new AuthService($users);
$userService = new UserService($users);
$authorization = new Authorization($auth);
$view = new View(APP_ROOT . '/app/Views');
$logger = new Logger($logDir);
$upload = new Upload(
    (string) $config->get('upload.dir', APP_ROOT . '/public/uploads'),
    (int) $config->get('upload.max_bytes', 2 * 1024 * 1024)
);
$venues = new VenueRepository($database);
$venueService = new VenueService($venues, $upload);
$categories = new CategoryRepository($database);
$categoryService = new CategoryService($categories);

$request = Request::fromGlobals();

$view->share('appName', (string) $config->get('app.name', 'Události'));
$view->share('user', $auth->user());
$view->share('currentPath', $request->path());

return [
    'config' => $config,
    'database' => $database,
    'users' => $users,
    'auth' => $auth,
    'authService' => $authService,
    'userService' => $userService,
    'authorization' => $authorization,
    'view' => $view,
    'logger' => $logger,
    'upload' => $upload,
    'venues' => $venues,
    'venueService' => $venueService,
    'categories' => $categories,
    'categoryService' => $categoryService,
    'request' => $request,
];
