<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\ContrastChecker;
use CookieConsentManager\Registry\Uuid4;
use CookieConsentManager\Validation\DraftValidator;
use PHPUnit\Framework\TestCase;

/** `$` also matches before a final line feed: an anchored validation pattern needs the `D` modifier. */
final class TrailingNewlineTest extends TestCase
{
    private static function root(): string
    {
        return (string) realpath(__DIR__.'/../../..');
    }

    public function testNoPatternOfTheModuleIsAnchoredByADollarThatAcceptsAFinalLineFeed(): void
    {
        $offenders = [];
        foreach ($this->sources() as $file) {
            foreach (token_get_all((string) file_get_contents($file)) as $token) {
                if (!\is_array($token) || \T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
                    continue;
                }
                $literal = substr($token[1], 1, -1);
                if (1 !== preg_match('/\$([\/~#])([a-zA-Z]*)$/', $literal, $match)) {
                    continue;
                }
                if (!str_contains($match[2], 'D') && !str_contains($match[2], 'm')) {
                    $offenders[] = substr($file, \strlen(self::root()) + 1).':'.$token[2].' '.$token[1];
                }
            }
        }

        self::assertSame([], $offenders, 'patterns ending in "$" without the D modifier');
    }

    public function testTheLintSeesAPatternWithoutTheModifier(): void
    {
        self::assertSame(1, preg_match('/\$([\/~#])([a-zA-Z]*)$/', '/^a$/i'));
        self::assertSame(1, preg_match('/\$([\/~#])([a-zA-Z]*)$/', '.parameters\.(.+)$/'));
    }

    public function testAKeyAnIdentifierAndAnAddressRefuseAFinalLineFeed(): void
    {
        self::assertSame(1, preg_match(DraftValidator::KEY_PATTERN, 'gtag'));
        self::assertSame(0, preg_match(DraftValidator::KEY_PATTERN, "gtag\n"));
        self::assertTrue(Uuid4::isValid('4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90'));
        self::assertFalse(Uuid4::isValid("4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90\n"));
        self::assertTrue(DraftValidator::isPolicyUrl('https://example.com/privacy'));
        self::assertFalse(DraftValidator::isPolicyUrl("https://example.com/privacy\n"));
        self::assertTrue(ContrastChecker::isHex('#0b5fff'));
        self::assertFalse(ContrastChecker::isHex("#0b5fff\n"));
    }

    /** @return iterable<string> */
    private function sources(): iterable
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root(), \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ('php' === $file->getExtension() && 1 !== preg_match('~/(tests|node_modules|vendor|tools|\.git)/|/Resources/vendor/~', $path)) {
                yield $path;
            }
        }
    }
}
