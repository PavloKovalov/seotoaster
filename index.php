<?php
// Necessary alter for pcre.backtrack_limit
ini_set('pcre.backtrack_limit', '10000000000000');


// Installation check
//if (is_dir(realpath(dirname(__FILE__) . '/install/'))) {
//    header('Location: install/');
//    exit();
//}
// End installation check


// Reading current core config
$coreConfigPath = realpath(__DIR__ . '/system/coreinfo.php');

if (file_exists($coreConfigPath)) {
    require_once realpath($coreConfigPath);
}

defined('CORE') || define('CORE', __DIR__ . '/seotoaster_core/');
defined('SITE_NAME') || define('SITE_NAME', '');

// End reading current core config


// Define path to application directory
defined('APPLICATION_PATH')
|| define('APPLICATION_PATH', CORE . 'application/');

// Define application environment
defined('APPLICATION_ENV')
|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

$libraryPath = realpath(CORE . 'library/');
if (!$libraryPath) {
    exit('Can not find library directory');
}

// Ensure library/ is on include_path
set_include_path(
    implode(
        PATH_SEPARATOR,
        array(
            $libraryPath,
            get_include_path(),
        )
    )
);

// header to prevent security issues in iframes (to avoid clickjacking attacks)
header('X-Frame-Options: SAMEORIGIN');

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
if (SITE_NAME) {
    $configIni = APPLICATION_PATH . 'configs' . DIRECTORY_SEPARATOR . SITE_NAME . '.ini';
} else {
    $configIni = APPLICATION_PATH . '/installer/installer.ini';
    define('INSTALL_PATH', __DIR__.'/');
}

$application = new Zend_Application(
    APPLICATION_ENV,
    $configIni
);

$application->bootstrap()
    ->run();