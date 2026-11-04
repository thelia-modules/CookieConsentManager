<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Thelia\Model\ConfigQuery;

/** Name, postal address and email of the shop, as entered in the store configuration of Thelia. */
class ShopIdentity
{
    /** @return array{name: string, address: string, email: string} empty strings for what is not entered */
    public function read(): array
    {
        $value = static fn (string $key): string => trim((string) ConfigQuery::read($key, ''));
        $town = trim($value('store_zipcode').' '.$value('store_city'));

        return [
            'name' => $value('store_name'),
            'address' => implode(', ', array_filter([$value('store_address1'), $value('store_address2'), $value('store_address3'), $town], static fn (string $part): bool => '' !== $part)),
            'email' => $value('store_email'),
        ];
    }
}
