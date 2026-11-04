<?php

declare(strict_types=1);

namespace CookieConsentManager\AdminHook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

final class MenuHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'main.in-top-menu-items' => [['type' => 'back', 'method' => 'onTopMenuItems']],
            'module.configuration' => [['type' => 'back', 'method' => 'onModuleConfiguration']],
        ];
    }

    public function onTopMenuItems(HookRenderEvent $event): void
    {
        $event->add($this->render('CookieConsentManager/hook/menu-item.html.twig', $event->getTemplateVars()));
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add($this->render('CookieConsentManager/hook/module-configuration.html.twig'));
    }
}
