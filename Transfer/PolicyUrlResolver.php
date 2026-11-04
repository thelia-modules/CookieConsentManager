<?php

declare(strict_types=1);

namespace CookieConsentManager\Transfer;

use Thelia\Model\RewritingUrlQuery;

/** The privacy policy page is carried by its rewritten URL, never by an identifier of the source site. */
class PolicyUrlResolver
{
    public function resolves(string $url): bool
    {
        $path = trim((string) parse_url($url, \PHP_URL_PATH), '/');

        return '' !== $path && RewritingUrlQuery::create()->filterByUrl($path)->count() > 0;
    }
}
