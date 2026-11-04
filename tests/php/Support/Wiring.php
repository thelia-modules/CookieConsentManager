<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\FrontUrls;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\PublishedConfigBuilder;
use CookieConsentManager\Publication\PublishedVersionPointer;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Publication\VersionRepository;
use CookieConsentManager\Publication\VersionRestorer;
use CookieConsentManager\Registry\ConsentPayloadValidator;
use CookieConsentManager\Registry\ConsentRecorder;
use CookieConsentManager\Registry\DailyStatRepository;
use CookieConsentManager\Registry\EntryRepository;
use CookieConsentManager\Transfer\ConfigExporter;
use CookieConsentManager\Transfer\ConfigImporter;
use CookieConsentManager\Transfer\ConfigSchemaValidator;
use CookieConsentManager\Transfer\PolicyUrlResolver;
use CookieConsentManager\Validation\ConflictingModuleDetector;
use CookieConsentManager\Validation\DraftValidator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Object graph on the fixture catalogue, so that the tests do not depend on the container. */
final class Wiring
{
    public CatalogueReader $catalogue;
    public DraftNormalizer $normalizer;
    public DraftRepository $drafts;
    public DraftValidator $validator;
    public VersionRepository $versions;
    public Publisher $publisher;
    public SnapshotAssembler $assembler;
    public PublishedConfigBuilder $builder;
    public FrontUrls $urls;
    public VersionRestorer $restorer;
    public ConfigExporter $exporter;
    public ConfigImporter $importer;
    public ConsentPayloadValidator $payloadValidator;
    public ConsentRecorder $recorder;
    public EntryRepository $entries;
    public EventDispatcher $dispatcher;
    public RecordingCachePurger $cache;
    public ArrayAdapter $pool;
    public PublishedVersionPointer $pointer;

    public function __construct(bool $googleTagManagerModuleActive = false)
    {
        $moduleDirectory = \dirname(__DIR__, 3);
        $this->catalogue = new CatalogueReader(__DIR__.'/../Fixtures/catalogue.json');
        $this->normalizer = new DraftNormalizer();
        $this->drafts = new DraftRepository($this->normalizer, $this->catalogue);
        $modules = new class($googleTagManagerModuleActive) extends ConflictingModuleDetector {
            public function __construct(private readonly bool $active)
            {
            }

            public function isActive(string $moduleCode): bool
            {
                return $this->active;
            }
        };
        $translator = new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtr($id, $parameters);
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
        $this->validator = new DraftValidator($this->catalogue, $modules, $translator, null, new class extends ActiveLanguages {
            public function mainCode(): string
            {
                return 'fr';
            }

            public function codes(): array
            {
                return ['fr', 'en'];
            }
        });
        $this->versions = new VersionRepository();
        $moduleVersion = new ModuleVersion($moduleDirectory.'/Config/module.xml');
        $this->assembler = new SnapshotAssembler($this->catalogue, $moduleVersion, new NoOverriddenTemplates());
        $this->cache = new RecordingCachePurger();
        $this->pool = new ArrayAdapter();
        $this->pointer = new PublishedVersionPointer($this->versions, $this->pool);
        $this->publisher = new Publisher(
            $this->drafts,
            $this->validator,
            $this->assembler,
            new AssembledScriptHasher(new ServedScriptAssembler($moduleDirectory)),
            $this->versions,
            $this->cache,
            $moduleVersion,
            $this->pointer,
        );
        $this->builder = new PublishedConfigBuilder();
        $this->urls = ModuleRoutes::urls();
        $this->restorer = new VersionRestorer($this->versions, $this->drafts);
        $this->exporter = new ConfigExporter($this->drafts, $this->catalogue, $moduleVersion, $this->versions);
        $policy = new class extends PolicyUrlResolver {
            public function resolves(string $url): bool
            {
                return '/politique-de-confidentialite' === $url;
            }
        };
        $this->importer = new ConfigImporter(
            new ConfigSchemaValidator($moduleDirectory.'/Config/schema/ccm-config-1.schema.json'),
            $this->normalizer,
            $this->drafts,
            $this->catalogue,
            $this->validator,
            $policy,
        );
        $this->payloadValidator = new ConsentPayloadValidator($this->versions);
        $this->entries = new EntryRepository();
        $this->dispatcher = new EventDispatcher();
        $this->recorder = new ConsentRecorder($this->versions, $this->entries, new DailyStatRepository(), $this->dispatcher);
    }
}
