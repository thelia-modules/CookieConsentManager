<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\ExperimentalServiceTwigExtension;
use CookieConsentManager\Catalogue\CatalogueReader;
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

final class ExperimentalBadgeTest extends TestCase
{
    private function twig(): Environment
    {
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['CookieConsentManager/layout.html.twig' => '{% block ccm_crumb %}{% endblock %}{% block ccm_content %}{% endblock %}']),
            new FilesystemLoader(\dirname(__DIR__, 3).'/templates/backOffice/default-twig'),
        ]), ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new ExperimentalServiceTwigExtension(new CatalogueReader(__DIR__.'/../Fixtures/catalogue.json')));
        $twig->addExtension(new class extends AbstractExtension {
            public function getFilters(): array
            {
                return [new TwigFilter('ccm_safe_url', static fn (string $url): string => $url), new TwigFilter('ccm_code', static fn (?string $code): string => (string) $code), new TwigFilter('ccm_number', static fn (mixed $value): string => (string) $value)];
            }

            public function getFunctions(): array
            {
                return [new TwigFunction('path', static fn (string $route): string => '/'.$route), new TwigFunction('assignToken', static fn (): string => 't'), new TwigFunction('ccm_field', static fn (string $path): string => $path), new TwigFunction('ccm_purpose', static fn (?string $code): string => (string) $code), new TwigFunction('ccm_can', static fn (string $access): bool => true)];
            }
        });

        return $twig;
    }

    private function badges(string $html): int
    {
        return substr_count($html, 'data-testid="ccm-experimental-badge"');
    }

    public function testTheCatalogueListLabelsOnlyTheUnverifiedServices(): void
    {
        $verified = (object) ['key' => 'gtag', 'name' => 'Google Analytics', 'purpose' => 'analytics', 'verified' => true, 'googleTag' => false, 'parameters' => []];
        $imported = (object) ['key' => 'obscure', 'name' => 'Obscure', 'purpose' => 'analytics', 'verified' => false, 'googleTag' => false, 'parameters' => []];

        $html = $this->twig()->render('CookieConsentManager/services.html.twig', [
            'rows' => [['entry' => $verified, 'service' => null, 'controller' => ''], ['entry' => $imported, 'service' => null, 'controller' => '']],
            'proposed' => [], 'custom' => [], 'purposes' => [], 'issues' => [], 'can' => ['update' => true],
            'draft' => ['services' => [], 'general' => ['experimental_services' => true]],
            'filters' => ['status' => 'all', 'q' => ''], 'catalogue_count' => ['verified' => 1, 'all' => 2],
        ]);

        self::assertSame(1, $this->badges($html), 'one badge, on the imported service');
        self::assertMatchesRegularExpression('~Obscure</strong>.*?ccm-experimental-badge~s', $html);
        self::assertStringContainsString('data-testid="ccm-experimental-note"', $html);
    }

    public function testTheServicesOfTheVerificationAndOfTheProofSheetAreLabelledToo(): void
    {
        $services = [['key' => 'gtag', 'name' => 'Google Analytics', 'purpose' => 'analytics', 'domains' => []], ['key' => 'obscure', 'name' => 'Obscure', 'purpose' => 'analytics', 'domains' => []]];

        self::assertSame(1, $this->badges($this->twig()->render('CookieConsentManager/verification.html.twig', ['services' => $services])));
        self::assertSame(1, $this->badges($this->twig()->render('CookieConsentManager/registry-proof.html.twig', ['proof' => ['services_resolved' => $services, 'entry' => [], 'version' => ['version' => 1]]])));
    }

    public function testACustomOrUnknownServiceIsNeverCalledExperimental(): void
    {
        $extension = new ExperimentalServiceTwigExtension(new CatalogueReader(__DIR__.'/../Fixtures/catalogue.json'));

        self::assertTrue($extension->isExperimental('obscure'));
        self::assertFalse($extension->isExperimental('gtag'));
        self::assertFalse($extension->isExperimental('my-own-script'));
    }
}
