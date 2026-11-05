<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Front\PreviewToken;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Tools\URL;

/** Front URLs the preview and the verification open; the token is added by the caller. */
class FrontLinks
{
    public function __construct(
        private readonly PreviewToken $tokens,
        private readonly DraftRepository $drafts,
    ) {
    }

    /** The token of the front lot: derived from the draft, so one value opens both the preview and the audit. Null while the secret of the shop is too weak to sign one. */
    public function token(): ?string
    {
        return $this->tokens->forDraft($this->drafts->load());
    }

    public function preview(string $path): ?string
    {
        $token = $this->token();
        if (null === $token) {
            return null;
        }

        return $this->withParameter($this->absolute($path), PreviewToken::QUERY, $token);
    }

    /** @return array<string, string> label key => absolute URL of a real page of the shop */
    public function auditPresets(string $locale): array
    {
        $presets = ['Home' => $this->absolute('/')];

        $category = CategoryQuery::create()->filterByVisible(1)->orderById()->findOne();
        if (null !== $category) {
            $presets['Category'] = (string) URL::getInstance()->retrieve('category', $category->getId(), $locale)->toString();
        }

        $product = ProductQuery::create()->filterByVisible(1)->orderById()->findOne();
        if (null !== $product) {
            $presets['Product'] = (string) URL::getInstance()->retrieve('product', $product->getId(), $locale)->toString();
        }

        $presets['Cart'] = $this->absolute('/checkout/cart');

        return $presets;
    }

    public function auditUrl(string $url, string $phase, string $token): string
    {
        return $this->withParameter($this->withParameter($url, 'ccm_audit', $token), 'ccm_audit_phase', $phase);
    }

    private function absolute(string $path): string
    {
        return URL::getInstance()->absoluteUrl($path);
    }

    private function withParameter(string $url, string $name, string $value): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').rawurlencode($name).'='.rawurlencode($value);
    }
}
