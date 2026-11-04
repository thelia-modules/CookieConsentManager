<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Model\ConsentEntryQuery;
use Propel\Runtime\ActiveQuery\Criteria;

final readonly class RegistryBrowser
{
    /** @return array{rows: list<ConsentEntry>, total: int, pages: int} */
    public function search(RegistryFilters $filters): array
    {
        $query = $filters->applyTo(ConsentEntryQuery::create());

        $total = $query->count();
        $pages = max(1, (int) ceil($total / RegistryFilters::PER_PAGE));
        $page = min($filters->page, $pages);

        $rows = $query->orderById(Criteria::DESC)->limit(RegistryFilters::PER_PAGE)->offset(($page - 1) * RegistryFilters::PER_PAGE)->find();

        return ['rows' => array_values(iterator_to_array($rows, false)), 'total' => $total, 'pages' => $pages];
    }

    public function find(int $id): ?ConsentEntry
    {
        return ConsentEntryQuery::create()->findPk($id);
    }
}
