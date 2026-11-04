<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Admin\ContrastChecker;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Transfer\ImportRefusedException;
use CookieConsentManager\Validation\DraftValidator;
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

    /** @return iterable<string, array{string, string}> */
    public static function forgedModuleDocuments(): iterable
    {
        yield 'undeclared key' => ['"key": "payzen_tracking"', '"key": "forged_tracking"'];
        yield 'code on a declared service' => ['"script_url": "https://static.payzen.eu/track.js",', '"script_url": "https://static.payzen.eu/track.js", "code": "window.x=1;",'];
        yield 'other script address' => ['https://static.payzen.eu/track.js', 'https://evil.example/track.js'];
        yield 'necessary without the declaration' => ['"purpose": "analytics", "enabled": false, "name": "Payzen suivi"', '"purpose": "essential", "strictly_necessary": true, "enabled": false, "name": "Payzen suivi"'];
    }

    #[DataProvider('forgedModuleDocuments')]
    public function testAnImportForgingAModuleServiceWritesNothing(string $search, string $replace): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'before');
        $before = $this->ccm->drafts->load();
        self::assertStringContainsString($search, $this->completeConfigJson());

        try {
            $this->ccm->importer->import(str_replace($search, $replace, $this->completeConfigJson()), 'alice');
            self::fail('the import should have been refused');
        } catch (ImportRefusedException) {
            self::assertSame([], ConfigDiff::between($before, $this->ccm->drafts->load()), 'the draft is untouched');
        }
    }

    public function testTheDeclaredModuleServicesOfTheFixtureImportAndAForgedDraftCannotBePublished(): void
    {
        $this->ccm->importer->import($this->completeConfigJson(), 'alice');

        $draft = $this->ccm->drafts->load();
        $draft['services'][] = ['key' => 'forged', 'module' => true, 'kind' => 'custom', 'purpose' => 'essential', 'strictly_necessary' => true, 'justification' => 'x', 'enabled' => true, 'name' => 'x', 'controller' => 'x', 'policy_url' => 'https://x.test/p', 'code' => 'window.x=1;'];
        $codes = array_map(static fn ($issue): string => $issue->code, $this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)));

        self::assertContains('module_undeclared', $codes);
    }

    public function testABackslashNeverMakesAPolicyUrl(): void
    {
        self::assertFalse(DraftValidator::isPolicyUrl('/\\evil.example'));
        self::assertFalse(DraftValidator::isPolicyUrl('https://x.test/a\\b'));
        self::assertTrue(DraftValidator::isPolicyUrl('/politique'));
    }
}
