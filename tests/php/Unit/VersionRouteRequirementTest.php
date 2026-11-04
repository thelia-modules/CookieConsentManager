<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Controller\PanelController;
use CookieConsentManager\Controller\ServedScriptController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

/** A version number too big for an integer would be a 500 instead of a 404. */
final class VersionRouteRequirementTest extends TestCase
{
    /** @return iterable<string, array{class-string, string}> */
    public static function routes(): iterable
    {
        yield 'script' => [ServedScriptController::class, 'script'];
        yield 'panel' => [PanelController::class, '__invoke'];
    }

    #[DataProvider('routes')]
    public function testTheVersionIsBoundedToNineDigits(string $controller, string $method): void
    {
        $route = (new \ReflectionMethod($controller, $method))->getAttributes(Route::class)[0]->newInstance();
        $pattern = '~^'.$route->getRequirements()['version'].'$~';

        self::assertSame(1, preg_match($pattern, '123456789'));
        self::assertSame(0, preg_match($pattern, '12345678901234567890'));
    }
}
