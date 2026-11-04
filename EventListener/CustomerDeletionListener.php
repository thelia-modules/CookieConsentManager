<?php

declare(strict_types=1);

namespace CookieConsentManager\EventListener;

use CookieConsentManager\Registry\EntryRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Customer;
use Thelia\Model\Event\CustomerEvent;

/** A deleted customer must not leave an identifier in the registry: the entries stay, unlinked. */
final readonly class CustomerDeletionListener
{
    public function __construct(private EntryRepository $entries)
    {
    }

    // Lower priority than the core handler (128): when the core refuses the deletion (orders), nothing is unlinked.
    #[AsEventListener(event: TheliaEvents::CUSTOMER_DELETEACCOUNT, priority: 0)]
    public function onDelete(CustomerEvent $event): void
    {
        $customer = $event->getModel();
        if ($customer instanceof Customer) {
            $this->entries->detachCustomer($customer->getId());
        }
    }
}
