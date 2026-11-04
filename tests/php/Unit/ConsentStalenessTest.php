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
            't' => time() - $case['ageSeconds'],
            'p' => ['analytics' => 1],
            's' => ['gtag' => 1],
            'x' => [],
            'pd' => 0,
        ];
        if (null !== $case['choiceVersion']) {
            $payload['ver'] = $case['choiceVersion'];
        }
        $request = Request::create('/');
        $request->cookies->set(ConsentCookie::NAME, ConsentCookie::encode($payload));

        $reader = new RequestConsentReader();
        self::assertSame(!$case['stale'], $reader->hasConsent($request, 'gtag', $case['repromptDays'], $case['substantialVersion']));
        self::assertSame(!$case['stale'], $reader->hasPurposeConsent($request, 'analytics', $case['repromptDays'], $case['substantialVersion']));
    }
}
