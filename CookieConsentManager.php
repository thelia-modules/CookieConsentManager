<?php

declare(strict_types=1);

namespace CookieConsentManager;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Compiler\DeclaredServicesPass;
use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\FooterLinkResolver;
use CookieConsentManager\Front\ThemeOverriddenTemplates;
use CookieConsentManager\Install\DataEraser;
use CookieConsentManager\Install\SchemaInstaller;
use CookieConsentManager\Proposed\ProposedServiceSynchronizer;
use CookieConsentManager\Publication\OverriddenTemplatesProvider;
use CookieConsentManager\Publication\ServedScriptHasher;
use CookieConsentManager\Publication\SharedCachePurger;
use CookieConsentManager\Registry\ScheduledPurge;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Thelia\Module\BaseModule;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class CookieConsentManager extends BaseModule
{
    public const DOMAIN_NAME = 'cookieconsentmanager';
    public const TAG_SERVICE = 'ccm.service';
    public const PARAMETER_DECLARED_SERVICES = 'ccm.declared_services';

    public static function configureServices(ServicesConfigurator $services): void
    {
        $services->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n/*',
                __DIR__.'/Config/**/*.php',
                __DIR__.'/Model/*',
                __DIR__.'/Event/*',
                __DIR__.'/Compiler/*',
                __DIR__.'/Registry/ScheduledPurge.php',
                __DIR__.'/Publication/SharedCachePurger.php',
                __DIR__.'/Front/FooterLinkResolver.php',
                __DIR__.'/tests/*',
                __DIR__.'/CookieConsentManager.php',
            ])
            ->autowire()
            ->autoconfigure();

        // The shared cache of Thelia exists from 3.3: a reference that is null where the service does not exist.
        $services->set(SharedCachePurger::class)->args([service('Thelia\Core\SharedCache\SharedCacheInvalidator')->nullOnInvalid()]);

        $services->alias(ServedScriptHasher::class, AssembledScriptHasher::class);
        $services->alias(OverriddenTemplatesProvider::class, ThemeOverriddenTemplates::class);

        // The slot `footer_links` exists from Thelia 3.2: before, the link comes from `layout.footer.top`.
        if (interface_exists('Thelia\Core\Content\Slot\ContentSlotResolverInterface')) {
            $services->set(FooterLinkResolver::class)->autowire()->autoconfigure();
        }

        // The scheduler of the shop only exists from Thelia 3.3 and symfony/scheduler is optional:
        // the console command stays the way to purge on every version.
        foreach ([DraftRepository::class, ProposedServiceSynchronizer::class] as $public) {
            $services->set($public)->autowire()->autoconfigure()->public();
        }

        if (class_exists('Thelia\Scheduler\TheliaSchedule') && class_exists(AsCronTask::class)) {
            $services->set(ScheduledPurge::class)->autowire()->autoconfigure();
        }
    }

    public static function getCompilers(): array
    {
        return [new DeclaredServicesPass()];
    }

    // install() and update() run at module:refresh, before the module is active: the Propel
    // models of the module do not exist yet, so only SQL is used here.
    public function install(?ConnectionInterface $con = null): void
    {
        SchemaInstaller::forModule(__DIR__)->apply($con);
    }

    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $installer = SchemaInstaller::forModule(__DIR__);
        $installer->apply($con);
        $installer->applyUpdates((string) $currentVersion, $con);
    }

    public function destroy(?ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        if ($deleteModuleData) {
            (new DataEraser())->erase($con);
        }
    }

    public function postActivation(?ConnectionInterface $con = null): void
    {
        $this->synchronize();
    }

    public function postUpdate(string $currentVersion, string $newVersion): void
    {
        $this->synchronize();
    }

    // Right after activation the running container was compiled without the module: its services
    // do not exist yet, so the draft is created by hand. The services declared by other modules
    // join the draft at the next module:refresh, ccm:services:sync or opening of the back-office.
    private function synchronize(): void
    {
        $container = $this->container;

        if (null !== $container && $container->has(DraftRepository::class)) {
            $container->get(DraftRepository::class)->ensure();
            $container->get(ProposedServiceSynchronizer::class)->synchronize();

            return;
        }

        (new DraftRepository(new DraftNormalizer(), new CatalogueReader(__DIR__.'/Resources/catalogue/catalogue.json')))->ensure();
    }
}
