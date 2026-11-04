<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Model\Version;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\OverriddenTemplatesProvider;
use CookieConsentManager\Publication\ServedScriptHasher;
use CookieConsentManager\Publication\VersionRepository;

/** The latest published version must still match what visitors are served. */
final readonly class ServedHashAlerts implements AlertProvider
{
    public function __construct(
        private VersionRepository $versions,
        private ServedHashResolver $served,
        private ModuleVersion $moduleVersion,
        private ServedScriptHasher $scripts,
        private OverriddenTemplatesProvider $templates,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 200;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        $this->served($alerts);

        return $alerts;
    }

    /** @param list<Alert> $alerts */
    private function served(array &$alerts): void
    {
        $latest = $this->versions->latest();
        if (null === $latest) {
            return;
        }

        $served = $this->served->forVersion($latest);
        if ($served !== $latest->getServedHash()) {
            $alerts[] = new Alert(Alert::DANGER, 'served_hash', $this->text->t('Version %version% no longer matches what visitors are served. %cause% Publish again to align them.', ['%version%' => (string) $latest->getVersion(), '%cause%' => $this->servedCause($latest)]), 'ccm_admin_versions', [\sprintf('served %s / published %s', $served, $latest->getServedHash())]);
        }
    }

    private function servedCause(Version $version): string
    {
        $snapshot = $this->versions->snapshot($version);
        $current = $this->moduleVersion->current();
        if ($version->getModuleVersion() !== $current) {
            return $this->text->t('The version was published with module %published%, the module is now %current%.', ['%published%' => $version->getModuleVersion(), '%current%' => $current]);
        }

        if ($this->scripts->hash($snapshot) !== $version->getJsHash()) {
            return $this->text->t('The script of the module changed since the publication (module update).');
        }

        $before = array_column((array) ($snapshot['templates'] ?? []), 'hash', 'template');
        $now = array_column($this->templates->list(), 'hash', 'template');
        $changed = array_keys(array_diff_assoc($now, $before) + array_diff_assoc($before, $now));
        if ([] !== $changed) {
            return $this->text->t('The overridden template(s) %names% changed since the publication.', ['%names%' => implode(', ', $changed)]);
        }

        return $this->text->t('Nothing changed in the module or in the theme since the publication: the version was probably published before the script fingerprint was corrected.');
    }
}
