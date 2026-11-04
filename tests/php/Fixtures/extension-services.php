<?php

declare(strict_types=1);

use CookieConsentManager\Admin\AlertCollector;
use CookieConsentManager\Validation\DraftValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The same registration as CookieConsentManager::configureServices(): load, autowire, autoconfigure
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $root = dirname(__DIR__, 3);
    // The module registers its own interfaces (with their tag attribute) through its own load(); a third-party module only implements them.
    $services->load('CookieConsentManager\\Admin\\AlertProvider\\', $root.'/Admin/AlertProvider/*')->exclude([$root.'/Admin/AlertProvider/AlertText.php', $root.'/Admin/AlertProvider/*Alerts.php'])->autowire()->autoconfigure();
    $services->load('CookieConsentManager\\Validation\\', $root.'/Validation/*')->exclude([$root.'/Validation/*Section.php', $root.'/Validation/IssueFactory.php', $root.'/Validation/FrenchPresetLanguages.php', $root.'/Validation/DraftValidator.php', $root.'/Validation/ConflictingModuleDetector.php'])->autowire()->autoconfigure();
    $services->load('CookieConsentManager\\Tests\\Fixtures\\Extension\\', __DIR__.'/Extension/*')->autowire()->autoconfigure();
    $services->set(AlertCollector::class)->autowire()->public();
    $services->set(DraftValidator::class)->autowire()->public();
};
