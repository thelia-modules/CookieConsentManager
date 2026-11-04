<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\CookieConsentManager;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProfileModuleQuery;
use Thelia\Model\ProfileQuery;

/** What each administrator profile may do on this module: the table of rights of the dashboard help. */
class ProfileRights
{
    public const ACCESSES = [AccessManager::VIEW, AccessManager::UPDATE, AccessManager::CREATE, AccessManager::DELETE];

    /** @return list<array{title: string, unrestricted: bool, grants: array<string, bool>}> the first row is the administrators without profile, who hold every right */
    public function rows(string $locale): array
    {
        $module = ModuleQuery::create()->findOneByCode(CookieConsentManager::getModuleCode());
        $rows = [['title' => '', 'unrestricted' => true, 'grants' => array_fill_keys(self::ACCESSES, true)]];

        foreach (ProfileQuery::create()->orderById()->find() as $profile) {
            $access = null === $module ? 0 : (ProfileModuleQuery::create()->filterByProfileId($profile->getId())->filterByModuleId($module->getId())->findOne()?->getAccess() ?? 0);
            $manager = new AccessManager($access);
            $profile->setLocale($locale);

            $grants = [];
            foreach (self::ACCESSES as $name) {
                $grants[$name] = $manager->can($name);
            }
            $rows[] = ['title' => (string) ($profile->getTitle() ?: $profile->getCode()), 'unrestricted' => false, 'grants' => $grants];
        }

        return $rows;
    }
}
