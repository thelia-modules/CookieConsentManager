<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

/*
 * The tests run from the root of the shop the module is installed in (a Thelia project, or a
 * checkout of thelia/thelia as in the shared module CI). CCM_SITE_ROOT names that root when the
 * module is mounted outside of it.
 */
$siteRoot = getenv('CCM_SITE_ROOT') ?: getcwd();
if (!is_file($siteRoot.'/vendor/autoload.php')) {
    fwrite(\STDERR, "Run the tests from the root of a Thelia shop, or set CCM_SITE_ROOT (no vendor/autoload.php in \"$siteRoot\").\n");
    exit(1);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'CookieConsentManager\\Tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

if (is_file($siteRoot.'/tests/bootstrap.php')) {
    // thelia/thelia: its own bootstrap loads the autoloader, the environment and the Propel models of the test database.
    require_once $siteRoot.'/tests/bootstrap.php';
} else {
    require_once $siteRoot.'/bootstrap.php';
    require_once $siteRoot.'/vendor/autoload.php';
    (new Dotenv())->bootEnv($siteRoot.'/.env');
}

// The integration tests write to the database: never the one of the shop.
$database = strtolower((string) ($_SERVER['DATABASE_NAME'] ?? ''));
if (!str_contains($database, 'test')) {
    fwrite(\STDERR, "Refusing to run: DATABASE_NAME must name a test database (got \"$database\"). Prepare one with bin/test-prepare.\n");
    exit(1);
}
