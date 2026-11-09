<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Controller\ConsentController;
use CookieConsentManager\Event\ConsentRecordedEvent;
use CookieConsentManager\Model\ConsentEntryQuery;
use CookieConsentManager\Model\DailyStatQuery;
use CookieConsentManager\Registry\ConsentCookie;
use CookieConsentManager\Registry\ConsentRateLimiter;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConsentEndpointTest extends CcmTestCase
{
    private const HOST = 'shop.test';
    private const UUID = '4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90';

    private ConsentController $controller;
    private int $version;
    private string $hash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $published = $this->ccm->publisher->publish('test');
        $this->version = $published->getVersion();
        $this->hash = $published->getServedHash();
        $this->controller = $this->controller(120);
    }

    public function testRecordsAChoiceAndSetsTheCookieOfTheContract(): void
    {
        $recorded = [];
        $this->ccm->dispatcher->addListener(ConsentRecordedEvent::class, static function (ConsentRecordedEvent $event) use (&$recorded): void {
            $recorded[] = $event;
        });

        $response = ($this->controller)($this->request($this->payload(['purposes' => ['analytics' => 1, 'media' => 2], 'services' => ['gtag' => 1, 'youtube' => 0]])));

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $body['consent_id']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $body['recorded_at']);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame(
            'ccm_consent='.$cookies[0]->getValue().'; Path=/; Max-Age=34214400; SameSite=Lax; Secure',
            (string) $cookies[0],
        );
        self::assertFalse($cookies[0]->isHttpOnly());

        $decoded = ConsentCookie::decode($cookies[0]->getValue());
        self::assertSame(1, $decoded['f']);
        self::assertSame($body['consent_id'], $decoded['id']);
        self::assertSame($this->version, $decoded['ver']);
        self::assertSame(0, $decoded['pd']);
        self::assertSame(1, $decoded['sd'], 'the time comes from the server clock');
        self::assertSame(['analytics' => 1, 'media' => 2], $decoded['p']);
        self::assertSame(['gtag' => 1, 'youtube' => 0], $decoded['s']);

        $entry = ConsentEntryQuery::create()->filterByConsentId($body['consent_id'])->findOne();
        self::assertSame('custom', $entry->getAction());
        self::assertSame($this->hash, $entry->getVersionHash());
        self::assertSame('/fr/creme.html', $entry->getPath());
        self::assertSame('Chrome 130', $entry->getBrowser());
        self::assertNull($entry->getIpTruncated(), 'IP not stored by default');
        self::assertCount(1, $recorded);
        self::assertTrue($recorded[0]->firstChoice);
    }

    public function testTheCookieIsSecureBehindAnUndeclaredTlsProxyWhenTheOriginIsHttps(): void
    {
        $plain = static function (string $origin, string $body): Request {
            $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36', 'REMOTE_ADDR' => '203.0.113.78', 'HTTP_ORIGIN' => $origin];

            return Request::create('http://'.self::HOST.'/ccm/consent', 'POST', [], [], [], $server, $body);
        };

        $secure = ($this->controller)($plain('https://'.self::HOST, $this->payload()));
        self::assertSame(200, $secure->getStatusCode());
        self::assertStringEndsWith('; Secure', (string) $secure->headers->getCookies()[0]);

        $insecure = ($this->controller)($plain('http://'.self::HOST, $this->payload()));
        self::assertSame(200, $insecure->getStatusCode());
        self::assertStringEndsNotWith('; Secure', (string) $insecure->headers->getCookies()[0]);
    }

    public function testTheRegistryKeepsNoSecretSegmentOfThePath(): void
    {
        $paths = [
            '/order/track/abc123' => '/order/track/*',
            '/fr/order/track/abc123/details' => '/fr/order/track/*',
            '/fr/creme-solaire-spf50.html' => '/fr/creme-solaire-spf50.html',
            '/account/token/4f1c2b9e8d7a4c3e9b1f2a6d5e7c8b90' => '/account/token/*',
            '/reset/4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90' => '/reset/*',
        ];

        foreach ($paths as $sent => $stored) {
            $response = ($this->controller)($this->request($this->payload(['path' => $sent])));
            self::assertSame(200, $response->getStatusCode(), $sent);

            $entry = ConsentEntryQuery::create()->filterByConsentId(json_decode((string) $response->getContent(), true)['consent_id'])->findOne();
            self::assertSame($stored, $entry->getPath(), $sent);
            self::assertStringNotContainsString('abc123', $entry->getPath());
        }
    }

    public function testKeepsAValidFallbackUuidAndRejectsAnInvalidOne(): void
    {
        $response = ($this->controller)($this->request($this->payload(['consent_id' => self::UUID])));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::UUID, json_decode((string) $response->getContent(), true)['consent_id']);
        self::assertSame(self::UUID, ConsentCookie::decode($response->headers->getCookies()[0]->getValue())['id']);

        $invalid = ($this->controller)($this->request($this->payload(['consent_id' => '1234'])));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame(['error' => 'invalid_payload', 'field' => 'consent_id'], json_decode((string) $invalid->getContent(), true));
    }

    public function test400OnInvalidJson(): void
    {
        $response = ($this->controller)($this->request('{not json'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'invalid_json'], json_decode((string) $response->getContent(), true));
        self::assertCount(0, $response->headers->getCookies());
    }

    public function test403OnForeignOriginOrCrossSiteFetch(): void
    {
        foreach ([
            ['Origin' => 'https://evil.example'],
            ['Origin' => 'null'],
            ['Origin' => 'https://'.self::HOST, 'Sec-Fetch-Site' => 'cross-site'],
            [],
        ] as $headers) {
            $response = ($this->controller)($this->request($this->payload(), $headers, false));

            self::assertSame(403, $response->getStatusCode(), json_encode($headers));
            self::assertSame(['error' => 'forbidden_origin'], json_decode((string) $response->getContent(), true));
        }

        $sameOriginWithoutOrigin = ($this->controller)($this->request($this->payload(), ['Sec-Fetch-Site' => 'same-origin'], false));
        self::assertSame(200, $sameOriginWithoutOrigin->getStatusCode());
    }

    public function test413OnABodyOverEightKilobytes(): void
    {
        $response = ($this->controller)($this->request($this->payload(['path' => '/'.str_repeat('a', 9000)])));

        self::assertSame(413, $response->getStatusCode());
        self::assertSame(['error' => 'too_large'], json_decode((string) $response->getContent(), true));
    }

    public function test422OnUnknownKeysAndBadValues(): void
    {
        $cases = [
            'services.foo' => ['services' => ['foo' => 1]],
            'services.gtag' => ['services' => ['gtag' => 2]],
            'purposes.essential' => ['purposes' => ['essential' => 1]],
            'purposes.analytics' => ['purposes' => ['analytics' => 3]],
            'exempt.matomo' => ['exempt' => ['matomo' => 1]],
            'config_version' => ['config_version' => 9999],
            'action' => ['action' => 'hack'],
            'source' => ['source' => 'x'],
            'served_hash' => ['served_hash' => 'ZZ'],
            'path' => ['path' => '/a?b=1'],
            'format' => ['format' => 2],
        ];

        $before = ConsentEntryQuery::create()->count();
        foreach ($cases as $field => $override) {
            $response = ($this->controller)($this->request($this->payload($override)));

            self::assertSame(422, $response->getStatusCode(), $field);
            self::assertSame(['error' => 'invalid_payload', 'field' => $field], json_decode((string) $response->getContent(), true), $field);
        }
        self::assertSame($before, ConsentEntryQuery::create()->count(), 'a refused request writes nothing');
    }

    public function test429WithRetryAfterOnceTheLimitIsReached(): void
    {
        $controller = $this->controller(2);

        $responses = array_map(fn (): Response => $controller($this->request($this->payload())), [1, 2, 3]);
        $limited = $responses[2];

        self::assertSame([200, 200, 429], array_map(static fn (Response $response): int => $response->getStatusCode(), $responses));
        self::assertSame(['error' => 'rate_limited'], json_decode((string) $limited->getContent(), true));
        self::assertGreaterThanOrEqual(1, (int) $limited->headers->get('Retry-After'));
    }

    public function testFirstChoiceOfAnIdentifierIsCountedOnce(): void
    {
        $first = ($this->controller)($this->request($this->payload(['action' => 'accept_all', 'purposes' => ['analytics' => 1, 'media' => 1], 'services' => ['gtag' => 1, 'youtube' => 1]])));
        $id = json_decode((string) $first->getContent(), true)['consent_id'];
        $totalBefore = $this->stat('first_total');

        ($this->controller)($this->request($this->payload(['consent_id' => $id, 'action' => 'reject_all', 'purposes' => ['analytics' => 0], 'services' => ['gtag' => 0]])));
        ($this->controller)($this->request($this->payload(['consent_id' => $id, 'action' => 'renew'])));

        self::assertSame($totalBefore, $this->stat('first_total'), 'a second choice of the same identifier is not a first choice');
        self::assertSame(3, ConsentEntryQuery::create()->filterByConsentId($id)->count(), 'the registry keeps every entry');
        self::assertGreaterThanOrEqual(1, $this->stat('first_accept_all'));
        self::assertGreaterThanOrEqual(1, $this->stat('service:gtag'));
    }

    public function testRobotsAreRecordedButNotCountedInTheStatistics(): void
    {
        $before = $this->stat('first_total');

        ($this->controller)($this->request($this->payload(), ['User-Agent' => 'Googlebot/2.1']));

        self::assertSame($before, $this->stat('first_total'));
    }

    public function testAnAutomatedBrowserIsACountedVisitorAndRobotsAreCountedApart(): void
    {
        $total = $this->stat('first_total');
        $robots = $this->stat('first_robot');

        ($this->controller)($this->request($this->payload(['action' => 'reject_all']), ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/153.0.0.0 Safari/537.36']));
        self::assertSame($total + 1, $this->stat('first_total'), 'a headless browser used for acceptance tests is counted');
        self::assertSame($robots, $this->stat('first_robot'));

        ($this->controller)($this->request($this->payload(), ['User-Agent' => 'Googlebot/2.1']));
        self::assertSame($total + 1, $this->stat('first_total'));
        self::assertSame($robots + 1, $this->stat('first_robot'), 'the excluded robots stay visible');
    }

    public function testTheStatisticsCanBeRebuiltFromTheRegistryWithoutTouchingIt(): void
    {
        $repository = new \CookieConsentManager\Registry\DailyStatRepository();
        ($this->controller)($this->request($this->payload(['action' => 'reject_all', 'purposes' => ['analytics' => 0], 'services' => ['gtag' => 0]])));
        ($this->controller)($this->request($this->payload(['action' => 'accept_all', 'purposes' => ['analytics' => 1], 'services' => ['gtag' => 1]])));
        $entriesAfter = ConsentEntryQuery::create()->count();
        \Propel\Runtime\Propel::getWriteConnection('TheliaMain')->exec('DELETE FROM ccm_daily_stat');
        self::assertSame(0, $this->stat('first_total'));

        $rebuilt = $repository->rebuildFromRegistry();

        self::assertSame($entriesAfter, ConsentEntryQuery::create()->count(), 'the registry is not modified');
        self::assertGreaterThanOrEqual(2, $rebuilt);
        self::assertSame(ConsentEntryQuery::create()->withColumn('COUNT(DISTINCT consent_id)', 'n')->select('n')->findOne(), $this->stat('first_total'));
        self::assertGreaterThanOrEqual(1, $this->stat('first_reject_all'));
        self::assertGreaterThanOrEqual(1, $this->stat('first_accept_all'));
    }

    public function testACustomChoiceWithNothingGrantedIsCountedAsARefusalViaThePanelAndRebuildFixesTheOldTotals(): void
    {
        $before = $this->stat('first_reject_panel');
        ($this->controller)($this->request($this->payload(['action' => 'custom', 'purposes' => ['analytics' => 0], 'services' => ['gtag' => 0]])));
        self::assertSame($before + 1, $this->stat('first_reject_panel'));

        // totals recorded before the rule existed counted this choice as customized
        $con = \Propel\Runtime\Propel::getWriteConnection('TheliaMain');
        $con->exec("UPDATE ccm_daily_stat SET total = 0 WHERE metric = 'first_reject_panel'");
        $registry = ConsentEntryQuery::create()->count();

        (new \CookieConsentManager\Registry\DailyStatRepository())->rebuildFromRegistry();

        self::assertGreaterThanOrEqual(1, $this->stat('first_reject_panel'));
        self::assertSame($registry, ConsentEntryQuery::create()->count(), 'the registry is never modified');
    }

    public function testTheRouteIsStatelessAndOpensNoSession(): void
    {
        $kernel = static::getContainer()->get('kernel');
        $request = \Thelia\Core\HttpFoundation\Request::create('https://'.self::HOST.'/ccm/consent', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => 'https://'.self::HOST,
            'HTTPS' => 'on',
        ], $this->payload(['config_version' => 999999]));

        $response = $kernel->handle($request);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        foreach ($response->headers->getCookies() as $cookie) {
            self::assertNotSame('PHPSESSID', $cookie->getName());
        }
    }

    private function controller(int $limit): ConsentController
    {
        return new ConsentController(new ConsentRateLimiter(new ArrayAdapter(), $limit), $this->ccm->payloadValidator, $this->ccm->recorder);
    }

    private function stat(string $metric): int
    {
        return (int) DailyStatQuery::create()->filterByMetric($metric)->withColumn('SUM(total)', 'sum')->select('sum')->findOne();
    }

    /**
     * @param array<string, mixed> $override
     */
    private function payload(array $override = []): string
    {
        return json_encode($override + [
            'format' => 1,
            'consent_id' => null,
            'action' => 'custom',
            'source' => 'preferences',
            'config_version' => $this->version,
            'served_hash' => $this->hash,
            'locale' => 'fr_FR',
            'path' => '/fr/creme.html',
            'purposes' => ['analytics' => 1],
            'services' => ['gtag' => 1],
            'exempt' => (object) [],
        ], \JSON_THROW_ON_ERROR);
    }

    /** @param array<string, string> $headers */
    private function request(string $body, array $headers = [], bool $defaultOrigin = true): Request
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTPS' => 'on', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36', 'REMOTE_ADDR' => '203.0.113.77'];
        if ($defaultOrigin) {
            $server['HTTP_ORIGIN'] = 'https://'.self::HOST;
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create('https://'.self::HOST.'/ccm/consent', 'POST', [], [], [], $server, $body);
    }
}
