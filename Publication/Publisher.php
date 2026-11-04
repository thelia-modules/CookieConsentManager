<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Model\Version;
use CookieConsentManager\Validation\DraftValidator;
use Propel\Runtime\Propel;

final readonly class Publisher
{
    public function __construct(
        private DraftRepository $drafts,
        private DraftValidator $validator,
        private SnapshotAssembler $assembler,
        private ServedScriptHasher $scriptHasher,
        private VersionRepository $versions,
        private SharedCachePurger $cache,
        private ModuleVersion $moduleVersion,
    ) {
    }

    /**
     * @param bool $refuseUnchanged refuse when the rendering served to visitors would be the one of the latest version
     *                              (a substantial republication is never unchanged: it shows the banner again)
     */
    public function publish(?string $author, bool $substantial = false, ?string $comment = null, bool $refuseUnchanged = false): Version
    {
        $draft = $this->drafts->load();
        $issues = $this->validator->validate($draft);
        if (DraftValidator::hasErrors($issues)) {
            throw new PublicationRefusedException($issues);
        }

        $snapshot = $this->assembler->assemble($draft);
        $configHash = CanonicalJson::hash(['config' => $snapshot['config'], 'services' => $snapshot['services_resolved']]);
        // The script is hashed from the snapshot as it will be read back, exactly as the rendering does.
        $scriptHash = $this->scriptHasher->hash((array) json_decode(CanonicalJson::encode($snapshot), true, 512, \JSON_THROW_ON_ERROR));

        $servedHash = self::servedHash($configHash, $this->moduleVersion->current(), $scriptHash, $snapshot['templates']);
        $latest = $this->versions->latest();
        if ($refuseUnchanged && !$substantial && null !== $latest && $latest->getServedHash() === $servedHash) {
            throw new NothingToPublishException($latest->getVersion());
        }

        $con = Propel::getWriteConnection('TheliaMain');
        $con->beginTransaction();

        try {
            $version = (new Version())
                ->setVersion($this->versions->nextNumber())
                ->setSnapshot(CanonicalJson::encode($snapshot))
                ->setConfigHash($configHash)
                ->setJsHash($scriptHash)
                ->setServedHash($servedHash)
                ->setModuleVersion($this->moduleVersion->current())
                ->setAuthor($author)
                ->setSubstantial((int) $substantial)
                ->setComment($comment)
                ->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $version->save($con);

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }

        $this->drafts->markPublished($version->getVersion());
        $this->cache->purgeAll();

        return $version;
    }

    /**
     * Hash of the rendering the visitor is served: the published configuration, the version of
     * the module, the served script and the resolved templates (SPEC 6.3).
     *
     * @param list<array<string, string>> $templates
     */
    public static function servedHash(string $configHash, string $moduleVersion, string $scriptHash, array $templates): string
    {
        return substr(hash('sha256', $configHash.'|'.$moduleVersion.'|'.$scriptHash.'|'.CanonicalJson::hash($templates)), 0, 12);
    }
}
