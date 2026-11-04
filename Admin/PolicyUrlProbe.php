<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Asks the shop itself what the link of the banner answers: a page that is not found is only seen by visitors. */
class PolicyUrlProbe
{
    private const TTL = 300;

    private readonly HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        private readonly RequestStack $requests,
        private readonly CacheInterface $cache,
    ) {
        // The address is typed by an administrator: the shop never reaches a private, loopback or metadata address, redirects included.
        $this->client = new NoPrivateNetworkHttpClient($client);
    }

    /** @return int|null the final HTTP status, null when nothing answered */
    public function status(string $url): ?int
    {
        $absolute = $this->absolute($url);
        if (null === $absolute) {
            return null;
        }

        $status = $this->cache->get('ccm_policy_probe_'.hash('sha256', $absolute), function (ItemInterface $item) use ($absolute): int {
            $item->expiresAfter(self::TTL);

            try {
                return $this->client->request('GET', $absolute, ['timeout' => 4, 'max_redirects' => 3, 'headers' => ['User-Agent' => 'CookieConsentManager-link-check']])->getStatusCode();
            } catch (\Throwable) {
                return 0;
            }
        });

        return 0 === $status ? null : $status;
    }

    private function absolute(string $url): ?string
    {
        if (1 === preg_match('~^https?://~i', $url)) {
            return $url;
        }

        $request = $this->requests->getCurrentRequest();
        if (null === $request || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return $request->getSchemeAndHttpHost().$url;
    }
}
