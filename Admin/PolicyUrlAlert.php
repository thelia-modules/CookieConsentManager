<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Symfony\Contracts\Translation\TranslatorInterface;

/** The link to the privacy policy that the banner shows must lead to a page that answers 200 and, under the France preset, is not a terms-and-conditions page. */
final readonly class PolicyUrlAlert
{
    public function __construct(
        private PolicyUrlProbe $probe,
        private TranslatorInterface $translator,
    ) {
    }

    public function check(?string $url, string $suffix = ''): ?Alert
    {
        if (null === $url || '' === $url) {
            return null;
        }

        $status = $this->probe->status($url);
        if (200 === $status) {
            return null;
        }

        if (null === $status) {
            return new Alert(Alert::WARNING, 'policy_unverified'.$suffix, $this->t('The page of the privacy policy (%url%) could not be checked from the server: open the link of the banner yourself.', ['%url%' => $url]), 'ccm_admin_dashboard', [], [], 'policy_url');
        }

        return new Alert(Alert::DANGER, 'policy_unreachable'.$suffix, $this->t('The link to the privacy policy (%url%) answers %status% instead of 200: visitors land on an error page. Create the page (Folders and Pages menu of the back-office) or correct the address.', ['%url%' => $url, '%status%' => (string) $status]), 'ccm_admin_dashboard', [], [], 'policy_url');
    }

    /** @param array<string, string> $parameters */
    private function t(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, 'cookieconsentmanager.bo.default-twig');
    }
}
