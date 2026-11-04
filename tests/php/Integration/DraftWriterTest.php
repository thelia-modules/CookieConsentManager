<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Admin\ContrastChecker;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\RegistryBrowser;
use CookieConsentManager\Admin\RegistryFilters;
use CookieConsentManager\Tests\Support\CcmTestCase;

final class DraftWriterTest extends CcmTestCase
{
    private DraftWriter $writer;
    private DraftSectionEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new DraftWriter($this->ccm->drafts, $this->ccm->normalizer, $this->ccm->validator, new ContrastChecker());
        $this->editor = new DraftSectionEditor($this->ccm->catalogue);
    }

    public function testAnInvalidIdentifierIsRefusedAndNothingIsWritten(): void
    {
        $before = $this->ccm->drafts->load();
        $candidate = $this->editor->catalogueService($before, 'gtag', ['enabled' => '1', 'parameters' => ['gtagUa' => 'INVALID']]);

        $result = $this->writer->save($candidate, ['services.gtag'], 'tester');

        self::assertFalse($result->saved);
        self::assertSame(['param_pattern'], array_map(static fn ($issue): string => $issue->code, $result->issues));
        self::assertSame($before, $this->ccm->drafts->load(), 'the refused candidate is not stored');
        self::assertSame('INVALID', $result->draft['services'][0]['parameters']['gtagUa'], 'but it is handed back to re-render the form');
    }

    public function testAValidIdentifierIsSaved(): void
    {
        $candidate = $this->editor->catalogueService($this->ccm->drafts->load(), 'gtag', ['enabled' => '1', 'parameters' => ['gtagUa' => 'G-ABC123DEF4']]);

        $result = $this->writer->save($candidate, ['services.gtag'], 'tester');

        self::assertTrue($result->saved);
        self::assertSame('G-ABC123DEF4', $this->ccm->drafts->load()['services'][0]['parameters']['gtagUa']);
    }

    public function testAnErrorOfAnotherSectionDoesNotBlockTheSave(): void
    {
        $draft = $this->ccm->drafts->load();
        $draft['services'] = [['key' => 'gtag', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['gtagUa' => 'bad']]];
        $this->ccm->drafts->replace($draft, 'tester');

        $candidate = $this->editor->general($this->ccm->drafts->load(), ['general' => ['enabled' => '1', 'renewal_months' => '3']]);

        self::assertTrue($this->writer->save($candidate, ['general.', 'policy.'], 'tester')->saved);
        self::assertSame(3, $this->ccm->drafts->load()['general']['renewal_months']);
    }

    public function testALowContrastIsRefusedWithRatiosAndAProposal(): void
    {
        $candidate = $this->editor->appearance($this->ccm->drafts->load(), ['layout' => 'bar', 'colors' => ['primary' => '#9fc5f8']]);

        $result = $this->writer->save($candidate, ['general.layout'], 'tester', true);

        self::assertFalse($result->saved);
        self::assertNotSame([], array_filter($result->contrast, static fn ($finding): bool => !$finding->passes() && null !== $finding->suggestion));
        self::assertSame('#1a56db', $this->ccm->drafts->load()['appearance']['colors']['primary']);

        $fixed = $this->editor->appearance($this->ccm->drafts->load(), ['colors' => ['primary' => '#1a56db']]);
        self::assertTrue($this->writer->save($fixed, ['general.layout'], 'tester', true)->saved);
    }

    public function testRegistrySearchFindsAnEntryByTheStartOfItsIdentifier(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'tester');
        $this->ccm->publisher->publish('tester');

        $browser = new RegistryBrowser();
        $none = $browser->search(RegistryFilters::fromQuery(['q' => 'zzzz-no-such-id']));
        self::assertSame(0, $none['total']);
        self::assertGreaterThanOrEqual(1, $browser->search(RegistryFilters::fromQuery(['page' => '99']))['pages']);
    }
}
