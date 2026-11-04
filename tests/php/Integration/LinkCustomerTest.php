<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Model\ConsentEntryQuery;
use CookieConsentManager\Registry\RegistryCsvExporter;
use CookieConsentManager\Registry\RegistryOptions;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Test\FixtureFactory;

/**
 * The recording route is stateless: it never sees the session of a logged-in customer, so the
 * "link the customer" option could not work and was removed.
 */
final class LinkCustomerTest extends CcmTestCase
{
    public function testARecordFromALoggedInCustomerCarriesNoCustomerIdentifier(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $published = $this->ccm->publisher->publish('test');

        $factory = new FixtureFactory($this->getPropelConnection());
        $customer = $factory->customer($factory->customerTitle());
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->start();
        $session->set('thelia.customer_user', $customer);
        $session->save();

        $body = json_encode([
            'format' => 1, 'consent_id' => null, 'action' => 'custom', 'source' => 'preferences',
            'config_version' => $published->getVersion(), 'served_hash' => $published->getServedHash(), 'locale' => 'fr_FR',
            'path' => '/fr/creme.html', 'purposes' => ['analytics' => 1], 'services' => ['gtag' => 1], 'exempt' => (object) [],
        ], \JSON_THROW_ON_ERROR);
        $request = Request::create('https://shop.test/ccm/consent', 'POST', [], [$session->getName() => $session->getId()], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://shop.test', 'HTTPS' => 'on', 'REMOTE_ADDR' => '203.0.113.78',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36',
        ], $body);

        $response = static::getContainer()->get('kernel')->handle($request);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $entry = ConsentEntryQuery::create()->orderById('desc')->findOne();
        self::assertNotNull($entry);
        self::assertNull($entry->getCustomerId());
    }

    public function testAnExportMadeWithTheRemovedOptionStillImportsAndTheOptionIsDropped(): void
    {
        self::assertStringContainsString('"link_customer": true', $this->completeConfigJson(), 'the fixture is an export of the time of the option');

        $this->ccm->importer->import($this->completeConfigJson(), 'alice');

        $draft = $this->ccm->drafts->load();
        self::assertArrayNotHasKey('link_customer', $draft['registry']);
        self::assertSame(36, $draft['registry']['retention_months'], 'the rest of the registry options is imported');
        self::assertArrayNotHasKey('link_customer', $this->ccm->exporter->export()['registry'] ?? []);

        $stream = fopen('php://memory', 'w+');
        (new RegistryCsvExporter(new RegistryOptions($this->ccm->drafts, $this->ccm->versions), $this->ccm->entries))->write($stream);
        rewind($stream);
        self::assertStringNotContainsString('customer_id', (string) strtok((string) stream_get_contents($stream), "\n"));
    }
}
