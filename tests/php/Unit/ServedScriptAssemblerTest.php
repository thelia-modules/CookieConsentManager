<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\EmbedRules;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Front\ServedScriptAssembler;
use PHPUnit\Framework\TestCase;

final class ServedScriptAssemblerTest extends TestCase
{
    private function assembler(): ServedScriptAssembler
    {
        return new ServedScriptAssembler(\dirname(__DIR__, 3));
    }

    /** @param list<array<string, mixed>> $services */
    private function snapshot(array $services): array
    {
        return ['services_resolved' => $services];
    }

    private function service(string $key, string $kind, array $extra = []): array
    {
        return ['key' => $key, 'kind' => $kind] + $extra;
    }

    public function testFollowsTheOrderOfTheContract(): void
    {
        $script = $this->assembler()->assemble($this->snapshot([
            $this->service('gtag', 'script'),
            $this->service('youtube', 'media'),
            $this->service('acme', 'custom', ['code' => 'window.acme = 1;']),
        ]));

        $positions = array_map(static fn (string $needle): int|false => strpos($script, $needle), [
            'window.CCMCompat=', '.CookieConsentManager={', 'CookieConsentManager.__boot();',
            'tarteaucitron.services.gtag = ', 'tarteaucitron.services.youtube = ', 'tarteaucitron.services["acme"] = ', 'CookieConsentManager.__start();',
        ]);

        self::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions);
        self::assertSame(1, substr_count($script, 'CookieConsentManager.__start();'));
    }

    public function testFragmentsAreVerbatimAndCustomCodeIsWrapped(): void
    {
        $fragment = rtrim((string) file_get_contents(\dirname(__DIR__, 3).'/Resources/catalogue/fragments/youtube.js'));
        $script = $this->assembler()->assemble($this->snapshot([
            $this->service('youtube', 'media'),
            $this->service('acme', 'custom', ['code' => 'window.acme = "</script>";']),
        ]));

        self::assertStringContainsString($fragment, $script);
        self::assertStringContainsString("tarteaucitron.services[\"acme\"] = {key:\"acme\", js:function(){\nwindow.acme = \"</script>\";\n}, fallback:function(){}};", $script);
    }

    public function testDeclarativeServicesAndUnknownFragmentsAddNothing(): void
    {
        $base = $this->assembler()->assemble($this->snapshot([]));

        self::assertSame($base, $this->assembler()->assemble($this->snapshot([
            $this->service('tiktok-via-gtm', 'declarative'),
            $this->service('does-not-exist', 'script'),
            $this->service('../../etc/passwd', 'script'),
        ])));
    }

    public function testTheHashFollowsTheServedBytes(): void
    {
        $hasher = new AssembledScriptHasher($this->assembler());
        $none = $this->snapshot([]);
        $one = $this->snapshot([$this->service('gtag', 'script')]);

        self::assertSame($hasher->hash($none), $hasher->hash($none));
        self::assertNotSame($hasher->hash($none), $hasher->hash($one));
        self::assertNotSame($hasher->hash($one), $hasher->hash($this->snapshot([$this->service('gtag', 'script'), $this->service('acme', 'custom', ['code' => 'a()'])])));
        self::assertSame(hash('sha256', $this->assembler()->assemble($one)), $hasher->hash($one));
    }

    public function testPreviewTokenFollowsTheDraftAndTheSecret(): void
    {
        $tokens = new PreviewToken('secret');
        $token = $tokens->forDraft(['a' => 1]);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        self::assertTrue($tokens->isValid($token, ['a' => 1]));
        self::assertFalse($tokens->isValid($token, ['a' => 2]));
        self::assertFalse((new PreviewToken('other'))->isValid($token, ['a' => 1]));
        self::assertFalse($tokens->isValid('', ['a' => 1]));
    }

    public function testEveryRuleTargetsAServiceOfTheCatalogue(): void
    {
        $catalogue = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/Resources/catalogue/catalogue.json'), true, 512, \JSON_THROW_ON_ERROR);
        $media = array_column(array_filter($catalogue, static fn (array $row): bool => 'media' === $row['kind']), 'key');

        foreach (array_keys(EmbedRules::rules()) as $key) {
            self::assertContains($key, $media, $key);
        }
    }
}
