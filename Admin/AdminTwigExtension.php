<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Draft\DraftRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/** Readable names for the paths and codes the back-office would otherwise print raw. */
final class AdminTwigExtension extends AbstractExtension
{
    /** @var array<string, mixed>|null */
    private ?array $draft = null;

    public function __construct(
        private readonly FieldLabeler $labels,
        private readonly DraftRepository $drafts,
        private readonly RequestStack $requests,
        private readonly TranslatorInterface $translator,
        private readonly AdminGuard $guard,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ccm_field', $this->field(...)),
            new TwigFunction('ccm_purpose', $this->purpose(...)),
            new TwigFunction('ccm_can', $this->guard->isGranted(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('ccm_code', fn (string $code, string $group): string => $this->translator->trans(CodeLabels::english($group, $code), [], 'cookieconsentmanager.bo.default-twig')),
            new TwigFilter('ccm_number', fn (float|int|null $value, int $decimals = 1): string => LocalizedNumber::format($value, $this->locale(), $decimals)),
        ];
    }

    public function field(string $path): string
    {
        $this->draft ??= $this->drafts->load();

        return $this->labels->label($path, $this->locale(), $this->draft);
    }

    public function purpose(string $code): string
    {
        if ('essential' === $code) {
            return $this->translator->trans('Essential (strictly necessary)', [], 'cookieconsentmanager.bo.default-twig');
        }

        $this->draft ??= $this->drafts->load();
        $locale = $this->locale();
        foreach ($this->draft['purposes'] as $purpose) {
            if ($purpose['code'] === $code) {
                return (string) ($purpose['label'][$locale] ?? $purpose['label']['en'] ?? $code);
            }
        }

        return $code;
    }

    private function locale(): string
    {
        return substr($this->requests->getCurrentRequest()?->getLocale() ?? 'en', 0, 2);
    }
}
