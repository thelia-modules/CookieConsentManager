<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Registry\ConsentCookie;
use CookieConsentManager\Registry\RequestConsentReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/** The table is shared with tests/js/engine/staleness.test.mjs: PHP and JS must agree on what a stale choice is. */
final class ConsentStalenessTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>}> */
    public static function cases(): iterable
    {
        $table = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/consent-staleness.json'), true, 512, \JSON_THROW_ON_ERROR);
        foreach ($table['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('cases')]
    public function testAnExpiredOrOutdatedChoiceGrantsNothing(array $case): void
    {
        $payload = [
            'f' => 1,
            'id' => '4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90',
            'p' => ['analytics' => 1],
            's' => ['gtag' => 1],
            'x' => [],
            'pd' => 0,
        ];
        if (!\array_key_exists('rawTime', $case)) {
            $payload['t'] = ($case['timeAsFloat'] ?? false) ? (float) (time() - $case['ageSeconds']) : time() - $case['ageSeconds'];
        } elseif (null !== $case['rawTime']) {
            $payload['t'] = $case['rawTime'];
        }
        if (null !== $case['choiceVersion']) {
            $payload['ver'] = $case['choiceVersion'];
        }
        $request = Request::create('/');
        // ConsentCookie::encode() writes a whole float without its fraction: the float form needs JSON_PRESERVE_ZERO_FRACTION.
        $encoded = ($case['timeAsFloat'] ?? false)
            ? rtrim(strtr(base64_encode(json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION)), '+/', '-_'), '=')
            : ConsentCookie::encode($payload);
        $request->cookies->set(ConsentCookie::NAME, $encoded);

        $reader = new RequestConsentReader();
        self::assertSame(!$case['stale'], $reader->hasConsent($request, 'gtag', $case['repromptDays'], $case['substantialVersion']));
        self::assertSame(!$case['stale'], $reader->hasPurposeConsent($request, 'analytics', $case['repromptDays'], $case['substantialVersion']));
    }
}
