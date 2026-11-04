<?php

declare(strict_types=1);

namespace CookieConsentManager\Model;

use CookieConsentManager\Model\Base\Version as BaseVersion;
use Propel\Runtime\Connection\ConnectionInterface;

/** A published version is evidence: once written it is neither modified nor deleted through the model. */
class Version extends BaseVersion
{
    public function preUpdate(?ConnectionInterface $con = null): bool
    {
        return false;
    }

    public function preDelete(?ConnectionInterface $con = null): bool
    {
        return false;
    }
}
