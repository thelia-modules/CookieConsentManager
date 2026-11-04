<?php

declare(strict_types=1);

namespace CookieConsentManager\Event;

use CookieConsentManager\Model\ConsentEntry;
use Symfony\Contracts\EventDispatcher\Event;

/** Dispatched after an entry was written to the registry. */
final class ConsentRecordedEvent extends Event
{
    public function __construct(
        public readonly ConsentEntry $entry,
        public readonly bool $firstChoice,
    ) {
    }
}
