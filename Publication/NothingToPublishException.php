<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

final class NothingToPublishException extends \RuntimeException
{
    public function __construct(public readonly int $version)
    {
        parent::__construct(\sprintf('Nothing changed since version %d: the configuration, the module and the templates are the same.', $version));
    }
}
