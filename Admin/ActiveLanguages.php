<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

class ActiveLanguages
{
    /** @return string two-letter code of the main language of the shop (the default language of Thelia) */
    public function mainCode(): string
    {
        return substr(Lang::getDefaultLanguage()->getCode(), 0, 2);
    }

    /** @return list<string> two-letter codes of the languages active on the shop */
    public function codes(): array
    {
        $codes = [];
        foreach (LangQuery::create()->filterByActive(true)->orderByPosition()->find() as $lang) {
            $codes[] = $lang->getCode();
        }

        return $codes;
    }

    /** @return list<string> locales of the languages active on the shop (fr_FR) */
    public function locales(): array
    {
        $locales = [];
        foreach (LangQuery::create()->filterByActive(true)->find() as $lang) {
            $locales[] = $lang->getLocale();
        }

        return $locales;
    }
}
