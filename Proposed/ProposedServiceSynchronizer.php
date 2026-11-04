<?php

declare(strict_types=1);

namespace CookieConsentManager\Proposed;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\CookieConsentManager;
use CookieConsentManager\Draft\DraftRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Brings the services other modules declare with the `ccm.service` tag into the draft as
 * "proposed": inactive until an admin enables them and publishes. Never run at compilation.
 */
final readonly class ProposedServiceSynchronizer
{
    private const REQUIRED = ['key', 'name', 'controller', 'purpose', 'policy_url'];

    /** @param list<array{service_id: string, attributes: array<string, mixed>}> $declared */
    public function __construct(
        #[Autowire(param: CookieConsentManager::PARAMETER_DECLARED_SERVICES)]
        private array $declared,
        private DraftRepository $drafts,
        private CatalogueReader $catalogue,
    ) {
    }

    /** @return array{added: list<string>, updated: list<string>, alerts: list<string>} */
    public function synchronize(): array
    {
        $report = ['added' => [], 'updated' => [], 'alerts' => []];
        $draft = $this->drafts->load();
        $services = array_column($draft['services'], null, 'key');
        $changed = false;
        $declaredNow = [];

        foreach ($this->declared as $declaration) {
            $attributes = $declaration['attributes'];
            $service = $this->toService($attributes);
            $serviceId = $declaration['service_id'];

            if (null === $service) {
                $report['alerts'][] = \sprintf('%s: the ccm.service tag lacks one of %s', $serviceId, implode(', ', self::REQUIRED));
                continue;
            }

            $key = $service['key'];
            if (isset($declaredNow[$key])) {
                $report['alerts'][] = \sprintf('%s: the key "%s" is already declared by %s, the declaration is refused', $serviceId, $key, $declaredNow[$key]);
                continue;
            }
            $declaredNow[$key] = $serviceId;
            $existing = $services[$key] ?? null;

            if (null !== $this->catalogue->find($key) || (null !== $existing && !isset($existing['module']))) {
                $report['alerts'][] = \sprintf('%s: the key "%s" is already used, the declaration is refused', $serviceId, $key);
                continue;
            }

            if (null === $existing) {
                $services[$key] = $service;
                $report['added'][] = $key;
                $changed = true;
                continue;
            }

            $updated = array_replace($existing, array_diff_key($service, array_flip(['enabled', 'proposed'])));
            if ($updated !== $existing) {
                $services[$key] = $updated;
                $report['updated'][] = $key;
                $changed = true;
            }
        }

        if ($changed) {
            $draft['services'] = array_values($services);
            $this->drafts->replace($draft, null, $this->drafts->baseVersion());
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>|null
     */
    private function toService(array $attributes): ?array
    {
        foreach (self::REQUIRED as $required) {
            if (!isset($attributes[$required]) || '' === $attributes[$required]) {
                return null;
            }
        }

        $service = [
            'key' => (string) $attributes['key'],
            'module' => true,
            'proposed' => true,
            'enabled' => false,
            'kind' => isset($attributes['script']) ? 'script' : 'declarative',
            'purpose' => (string) $attributes['purpose'],
            'name' => (string) $attributes['name'],
            'controller' => (string) $attributes['controller'],
            'policy_url' => (string) $attributes['policy_url'],
            'cookies' => array_values((array) ($attributes['cookies'] ?? [])),
            'strictly_necessary' => (bool) ($attributes['strictly_necessary'] ?? false),
        ];

        if (isset($attributes['script'])) {
            $service['script_url'] = (string) $attributes['script'];
        }
        if (isset($attributes['justification'])) {
            $service['justification'] = (string) $attributes['justification'];
        }

        return $service;
    }
}
