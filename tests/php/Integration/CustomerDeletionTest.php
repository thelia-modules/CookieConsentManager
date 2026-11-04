<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Model\ConsentEntryQuery;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Test\FixtureFactory;

final class CustomerDeletionTest extends CcmTestCase
{
    public function testDeletingACustomerUnlinksItsRegistryEntriesAndKeepsThem(): void
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $customer = $factory->customer($factory->customerTitle());
        $other = $factory->customer($factory->customerTitle());
        $mine = $this->entry('cust-del-mine-0001', $customer->getId());
        $theirs = $this->entry('cust-del-other-0002', $other->getId());

        static::getContainer()->get(EventDispatcherInterface::class)->dispatch(new CustomerEvent($customer), TheliaEvents::CUSTOMER_DELETEACCOUNT);

        self::assertNull(ConsentEntryQuery::create()->findPk($mine)->getCustomerId());
        self::assertSame($other->getId(), ConsentEntryQuery::create()->findPk($theirs)->getCustomerId(), 'the entries of other customers are untouched');
    }

    private function entry(string $consentId, int $customerId): int
    {
        $entry = (new ConsentEntry())
            ->setConsentId($consentId)->setRecordedAt(new \DateTimeImmutable())->setAction('custom')->setSource('api')
            ->setVersion(1)->setVersionHash('abcdef012345')->setLocale('fr_FR')->setPath('/')
            ->setPurposesJson('{}')->setServicesJson('{}')->setCustomerId($customerId);
        $entry->save();

        return (int) $entry->getId();
    }
}
