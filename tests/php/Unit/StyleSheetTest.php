<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\StyleSheet;
use PHPUnit\Framework\TestCase;

final class StyleSheetTest extends TestCase
{
    private function sheet(string $css): StyleSheet
    {
        $path = tempnam(sys_get_temp_dir(), 'ccm-css');
        file_put_contents($path, $css);

        return new StyleSheet($path);
    }

    public function testItDropsCommentsAndSpacesButKeepsTheMeaningOfASelector(): void
    {
        $sheet = $this->sheet("/* note */\n.a :hover,\n.b > .c {\n  color: red;\n  margin: 0 auto;\n}\n");

        self::assertSame('.a :hover,.b>.c{color:red;margin:0 auto}', $sheet->minified());
    }

    public function testTheHashFollowsTheContent(): void
    {
        $one = $this->sheet('.a{color:red}');

        self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $one->hash());
        self::assertSame($one->hash(), $this->sheet(".a {\n color: red;\n}")->hash(), 'formatting does not change the name');
        self::assertNotSame($one->hash(), $this->sheet('.a{color:blue}')->hash());
    }
}
