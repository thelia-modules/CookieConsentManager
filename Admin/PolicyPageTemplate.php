<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Catalogue\JointResponsibility;
use CookieConsentManager\Controller\PreferencesPageController;
use CookieConsentManager\Front\CookieDuration;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\TemplateContext;

/**
 * The model of the "Cookie policy" page, written from the published configuration in one language. It keeps what
 * stays true (publisher, roles, duration of the choice, proofs, rights) and sends the visitor to the live page of
 * preferences for what changes with the services: the list of services, cookies, lifetimes and responsibles.
 */
final readonly class PolicyPageTemplate
{
    public function __construct(
        private FrontTexts $frontTexts,
        private TemplateContext $context,
        private ShopIdentity $identity,
        private JointResponsibility $joint,
    ) {
    }

    /**
     * @param array<string, mixed> $snapshot published snapshot
     *
     * @return string HTML fragment to paste in the description of a content of the shop
     */
    public function render(array $snapshot, string $language): string
    {
        $config = $snapshot['config'];
        $labels = $this->frontTexts->forLanguage($language);
        $words = $this->frontTexts->policyPage($language);
        $texts = $this->context->texts($config['texts'], $language);
        $identity = $this->identity->read();
        $listLink = '<a href="'.PreferencesPageController::PATH.'">'.self::e($words['policy_list_link']).'</a>';

        $html = [];
        $html[] = '<p>'.self::e($words['policy_intro']).'</p>';

        $publisher = array_filter([$identity['name'], $identity['address']], static fn (string $part): bool => '' !== $part);
        if ([] !== $publisher || '' !== $identity['email']) {
            $html[] = '<h2>'.self::e($words['policy_publisher_title']).'</h2>';
            if ([] !== $publisher) {
                $html[] = '<p>'.self::e(str_replace('%identity%', implode(', ', $publisher), $words['policy_publisher'])).'</p>';
            }
            if ('' !== $identity['email']) {
                $html[] = '<p>'.self::e(str_replace('%email%', $identity['email'], $words['policy_publisher_contact'])).'</p>';
            }
        }

        $html[] = '<h2>'.self::e($words['policy_responsible_title']).'</h2>';
        $html[] = '<p>'.self::e($words['policy_responsible']).'</p>';
        if ($this->joint->among((array) $snapshot['services_resolved'])) {
            $html[] = '<p>'.self::e($words['policy_responsible_joint']).'</p>';
        }

        $html[] = '<h2>'.self::e($words['policy_services_title']).'</h2>';
        $html[] = '<p>'.str_replace('%link%', $listLink, self::e($words['policy_list'])).'</p>';

        $html[] = '<h2>'.self::e($words['policy_choice_title']).'</h2>';
        $html[] = '<p>'.self::e(str_replace(['%duration%', '%link%'], [CookieDuration::months((int) $config['general']['renewal_months'], $labels), $texts['preferences']], $words['policy_choice'])).'</p>';

        $html[] = '<h2>'.self::e($words['policy_proof_title']).'</h2>';
        $proof = [str_replace('%duration%', CookieDuration::months((int) $config['registry']['retention_months'], $labels), $words['policy_proof']), 'none' === $config['registry']['ip_mode'] ? $words['policy_proof_no_ip'] : $words['policy_proof_ip']];
        $html[] = '<p>'.self::e(implode(' ', $proof)).'</p>';

        $html[] = '<h2>'.self::e($words['policy_rights_title']).'</h2>';
        $html[] = '<p>'.self::e($words['policy_rights']).'</p>';

        return implode("\n", $html)."\n";
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
