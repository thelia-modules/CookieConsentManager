<?php

declare(strict_types=1);

$siteRoot = getenv('CCM_SITE_ROOT') ?: getcwd();

require_once $siteRoot.'/bootstrap.php';
require_once $siteRoot.'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'CookieConsentManager\\Tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
