<?php

declare(strict_types=1);

// The rule set of the Thelia core (thelia/thelia .php-cs-fixer.dist.php), without its header comment:
// this module is not part of the Thelia package.
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['node_modules', 'tools', 'assets', 'Resources', 'vendor'])
    ->notPath(['Model/Base', 'Model/Map', 'Model/Event'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,
        '@PHP81Migration' => true,
        '@PHP82Migration' => true,
        '@PHP83Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'yoda_style' => false,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
