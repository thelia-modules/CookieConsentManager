<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\SafeUrlTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class BackOfficeLinksTest extends TestCase
{
    private function twig(): Environment
    {
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['CookieConsentManager/layout.html.twig' => '{% block ccm_crumb %}{% endblock %}{% block ccm_content %}{% endblock %}']),
            new FilesystemLoader(\dirname(__DIR__, 3).'/templates/backOffice/default-twig'),
        ]), ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new SafeUrlTwigExtension());
        $twig->addExtension(new class extends AbstractExtension {
            public function getFilters(): array
            {
                return [new TwigFilter('ccm_code', static fn (string $code): string => $code), new TwigFilter('ccm_number', static fn (mixed $value): string => (string) $value)];
            }

            public function getFunctions(): array
            {
                return [new TwigFunction('path', static fn (string $route): string => '/'.$route), new TwigFunction('assignToken', static fn (): string => 't'), new TwigFunction('ccm_field', static fn (string $path): string => $path), new TwigFunction('ccm_purpose', static fn (string $code): string => $code), new TwigFunction('ccm_can', static fn (string $access): bool => true), new TwigFunction('ccm_is_experimental', static fn (string $key): bool => false)];
            }
        });

        return $twig;
    }

    public function testAProposedServiceNeverLinksToADangerousAddressEvenIfOneIsStored(): void
    {
        $html = $this->twig()->render('CookieConsentManager/services.html.twig', [
            'proposed' => [
                ['key' => 'evil', 'name' => 'Evil', 'controller' => 'E', 'policy_url' => 'javascript:alert(1)', 'purpose' => 'ads', 'enabled' => false, 'strictly_necessary' => false],
                ['key' => 'fine', 'name' => 'Fine', 'controller' => 'F', 'policy_url' => 'https://example.org/privacy', 'purpose' => 'ads', 'enabled' => false, 'strictly_necessary' => false],
            ],
            'purposes' => [], 'issues' => [], 'can' => ['update' => true], 'draft' => ['services' => []],
        ]);

        self::assertStringContainsString('data-testid="ccm-proposed-evil"', $html, 'the card is rendered');
        self::assertStringNotContainsStringIgnoringCase('javascript:', $html);
        self::assertStringContainsString('href="https://example.org/privacy"', $html, 'a safe address stays a link');
    }

    public function testTheFilterKeepsPathsAndHttpsOnly(): void
    {
        $filter = (new SafeUrlTwigExtension())->getFilters()[0]->getCallable();

        self::assertSame('/page', $filter('/page'));
        self::assertSame('https://example.org/p?a=1', $filter('https://example.org/p?a=1'));
        foreach (['javascript:alert(1)', ' javascript:alert(1)', "java\tscript:alert(1)", 'data:text/html,x', '//evil.test/x', 'http://example.org', 'vbscript:x'] as $url) {
            self::assertSame('#', $filter($url), $url);
        }
    }
}
