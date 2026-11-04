<?php

declare(strict_types=1);

namespace CookieConsentManager\Draft;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Model\DraftSetting;
use CookieConsentManager\Model\DraftSettingQuery;
use CookieConsentManager\Model\ServiceConfig;
use CookieConsentManager\Model\ServiceConfigQuery;
use Propel\Runtime\Propel;

final readonly class DraftRepository
{
    private const SETTINGS_ROW_ID = 1;

    public function __construct(
        private DraftNormalizer $normalizer,
        private CatalogueReader $catalogue,
    ) {
    }

    public function ensure(): void
    {
        if (null === DraftSettingQuery::create()->findPk(self::SETTINGS_ROW_ID)) {
            $this->store($this->normalizer->normalize([]), null);
        }
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $row = DraftSettingQuery::create()->findPk(self::SETTINGS_ROW_ID);
        $draft = null === $row ? [] : (array) json_decode($row->getSettings(), true, 512, \JSON_THROW_ON_ERROR);

        $services = [];
        foreach (ServiceConfigQuery::create()->orderByServiceKey()->find() as $service) {
            $services[] = $this->toArray($service);
        }
        $draft['services'] = $services;

        return $this->normalizer->normalize($draft);
    }

    public function baseVersion(): ?int
    {
        return DraftSettingQuery::create()->findPk(self::SETTINGS_ROW_ID)?->getBaseVersion();
    }

    /** @param array<string, mixed> $draft */
    public function replace(array $draft, ?string $author, ?int $baseVersion = null): void
    {
        $this->store($this->normalizer->normalize($draft), $author, $baseVersion);
    }

    public function markPublished(int $version): void
    {
        DraftSettingQuery::create()->findPk(self::SETTINGS_ROW_ID)?->setBaseVersion($version)->save();
    }

    /** @param list<string>|null $paths null confirms everything */
    public function confirmEnvironment(?array $paths = null): void
    {
        $draft = $this->load();
        $pending = (array) ($draft['_meta']['environment_pending'] ?? []);
        $remaining = null === $paths ? [] : array_values(array_diff($pending, $paths));

        if ([] === $remaining) {
            unset($draft['_meta']['environment_pending']);
        } else {
            $draft['_meta']['environment_pending'] = $remaining;
        }

        $this->replace($draft, null, $this->baseVersion());
    }

    /** @param array<string, mixed> $draft normalized */
    private function store(array $draft, ?string $author, ?int $baseVersion = null): void
    {
        $services = $draft['services'];
        unset($draft['services']);

        $con = Propel::getWriteConnection('TheliaMain');
        $con->beginTransaction();

        try {
            $row = DraftSettingQuery::create()->findPk(self::SETTINGS_ROW_ID) ?? (new DraftSetting())->setId(self::SETTINGS_ROW_ID);
            $row->setSettings(json_encode($draft, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
            $row->setUpdatedBy($author);
            $row->setBaseVersion($baseVersion ?? $row->getBaseVersion());
            $row->save($con);

            $keys = array_column($services, 'key');
            ServiceConfigQuery::create()->filterByServiceKey($keys, \Propel\Runtime\ActiveQuery\Criteria::NOT_IN)->delete($con);
            foreach ($services as $service) {
                $this->fromArray($service)->save($con);
            }

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }
    }

    /** @param array<string, mixed> $service */
    private function fromArray(array $service): ServiceConfig
    {
        $row = ServiceConfigQuery::create()->findPk($service['key']) ?? (new ServiceConfig())->setServiceKey($service['key']);
        $isCustom = isset($service['custom']);
        $isModule = isset($service['module']);

        $details = array_diff_key($service, array_flip(['key', 'purpose', 'enabled', 'parameters', 'kind', 'custom', 'module', 'proposed', 'strictly_necessary']));

        $row->setOrigin($isModule ? 'module' : ($isCustom ? 'custom' : 'catalogue'));
        $row->setKind($service['kind'] ?? $this->catalogue->find($service['key'])->kind ?? 'script');
        $row->setPurpose($service['purpose']);
        $row->setEnabled((int) $service['enabled']);
        $row->setProposed((int) ($service['proposed'] ?? 0));
        $row->setStrictlyNecessary((int) ($service['strictly_necessary'] ?? 0));
        $row->setParameters(isset($service['parameters']) ? json_encode($service['parameters'], \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT) : null);
        $row->setDetails([] === $details ? null : json_encode($details, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

        return $row;
    }

    /** @return array<string, mixed> */
    private function toArray(ServiceConfig $row): array
    {
        $service = [
            'key' => $row->getServiceKey(),
            'purpose' => $row->getPurpose(),
            'enabled' => 1 === $row->getEnabled(),
        ];

        if ('catalogue' === $row->getOrigin()) {
            return $service + ['parameters' => (array) json_decode((string) $row->getParameters(), true)] + (array) json_decode((string) $row->getDetails(), true);
        }

        $service += ['module' === $row->getOrigin() ? 'module' : 'custom' => true, 'kind' => $row->getKind()];
        $service += (array) json_decode((string) $row->getDetails(), true);

        if ('module' === $row->getOrigin()) {
            $service['proposed'] = 1 === $row->getProposed();
            $service['strictly_necessary'] = 1 === $row->getStrictlyNecessary();
        }

        return $service;
    }
}
