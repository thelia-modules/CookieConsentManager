<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Symfony\Contracts\Translation\TranslatorInterface;

/** With the automatic blocking of embedded iframes off, an active video service loads its player (and its cookies) before any choice. */
final readonly class EmbeddedMediaAlert
{
    private const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    public function __construct(
        private FieldLabeler $labels,
        private TranslatorInterface $translator,
    ) {
    }

    /** @param array<string, mixed> $draft normalized */
    public function check(array $draft, string $locale): ?Alert
    {
        if ($draft['general']['block_embedded_iframes']) {
            return null;
        }

        $names = array_values(array_map(
            fn (array $service): string => $this->labels->label('services.'.$service['key'], $locale, $draft),
            array_filter($draft['services'], static fn (array $service): bool => $service['enabled'] && 'media' === $service['purpose']),
        ));
        if ([] === $names) {
            return null;
        }

        return new Alert(
            Alert::DANGER,
            'iframes_unblocked_media',
            $this->translator->trans('The automatic blocking of embedded content is off while a video service is active (%services%): its videos would load before the visitor consents.', ['%services%' => implode(', ', $names)], self::DOMAIN),
            'ccm_admin_dashboard',
        );
    }
}
