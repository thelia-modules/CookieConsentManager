<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Admin\ContrastChecker;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Transfer\ImportRefusedException;
use PHPUnit\Framework\Attributes\DataProvider;

/** A URL of the configuration is a path of the shop or an https address: nothing else is stored, whatever the way in. */
final class DangerousUrlTest extends CcmTestCase
{
    private const CUSTOM = 'chat_maison';

    /** @return iterable<string, array{string, string}> the text of the complete fixture, and what replaces it */
    public static function dangerousDocuments(): iterable
    {
        yield 'policy.url' => ['"url": "/politique-de-confidentialite"', '"url": "javascript:alert(1)"'];
        yield 'policy.urls' => ['"page_title"', '"urls": {"fr": "data:text/html,x"}, "page_title"'];
        yield 'policy_url of a custom service' => ['https://example.com/privacy', 'JaVaScRiPt:alert(1)'];
        yield 'script_url of a service that is not a script' => ['"controller": "Exemple SAS",', '"controller": "Exemple SAS", "script_url": "javascript:alert(1)",'];
    }

    #[DataProvider('dangerousDocuments')]
    public function testAnImportCarryingADangerousUrlWritesNothing(string $search, string $replace): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'before');
        $before = $this->ccm->drafts->load();
        self::assertStringContainsString($search, $this->completeConfigJson());
        $json = str_replace($search, $replace, $this->completeConfigJson());

        try {
            $this->ccm->importer->import($json, 'alice');
            self::fail('the import should have been refused');
        } catch (ImportRefusedException) {
            self::assertSame([], ConfigDiff::between($before, $this->ccm->drafts->load()), 'the draft is untouched');
        }
    }

    public function testASaveCarryingADangerousUrlIsRefused(): void
    {
        $writer = new DraftWriter($this->ccm->drafts, $this->ccm->normalizer, $this->ccm->validator, new ContrastChecker());
        $custom = ['key' => self::CUSTOM, 'custom' => true, 'kind' => 'custom', 'purpose' => 'ads', 'enabled' => false, 'name' => 'x', 'controller' => 'x', 'policy_url' => 'https://x.test/p', 'code' => 'window.x=1;'];

        foreach (['policy_url' => 'javascript:alert(1)', 'script_url' => 'javascript:alert(1)'] as $field => $url) {
            $draft = $this->publishableDraft();
            $draft['services'][] = [$field => $url] + $custom;
            $result = $writer->save($draft, ['services.'], 'alice');

            self::assertFalse($result->saved, $field);
            self::assertSame([], ConfigDiff::between($this->ccm->drafts->load(), $this->ccm->drafts->load()));
            self::assertNotContains(self::CUSTOM, array_column($this->ccm->drafts->load()['services'], 'key'), $field.': nothing was written');
        }

        $draft = $this->publishableDraft();
        $draft['policy']['urls'] = ['fr' => 'javascript:alert(1)'];
        self::assertFalse($writer->save($draft, ['policy.'], 'alice')->saved, 'policy.urls');
    }
}
