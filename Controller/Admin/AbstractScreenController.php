<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\AdminGuard;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\ExecutableChanges;
use CookieConsentManager\Admin\SaveResult;
use CookieConsentManager\CookieConsentManager;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;

/** What the six screens share: rights, CSRF, the draft being edited and the common view variables. */
abstract class AbstractScreenController extends BaseAdminController
{
    public const BASE_PATH = '/admin/module/CookieConsentManager';
    public const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    public function __construct(
        protected readonly UrlGeneratorInterface $urls,
        protected readonly DraftRepository $drafts,
        protected readonly DraftWriter $writer,
        protected readonly DraftSectionEditor $editor,
        protected readonly VersionRepository $versions,
    ) {
    }

    /** @param array<string, mixed> $variables */
    protected function view(string $template, string $tab, array $variables = [], int $status = 200): Response
    {
        $draft = $variables['draft'] ?? $this->drafts->load();
        $latest = $this->versions->latest();
        // The badge compares what is stored: a draft refused on save (422) was never stored.
        $unpublished = null === $latest ? null : \count(ConfigDiff::between($this->versions->snapshot($latest)['config'], $this->drafts->load()));

        return $this->render('CookieConsentManager/'.$template, $variables + [
            'draft' => $draft,
            'tab' => $tab,
            'can' => $this->grants(),
            'published' => $latest,
            'unpublished_changes' => $unpublished,
            'ui_locale' => substr($this->requestStack->getCurrentRequest()?->getLocale() ?? 'en', 0, 2),
        ], $status);
    }

    /** @return array<string, bool> */
    protected function grants(): array
    {
        $grants = [];
        foreach ([AccessManager::VIEW, AccessManager::UPDATE, AccessManager::CREATE, AccessManager::DELETE] as $access) {
            $grants[strtolower($access)] = $this->isGranted($access);
        }

        return $grants;
    }

    protected function isGranted(string $access): bool
    {
        return $this->guard()->isGranted($access);
    }

    protected function guardRead(): ?Response
    {
        return $this->guardAccess(AccessManager::VIEW);
    }

    /** A refusal is rendered in the back-office, in the language of the administrator. */
    protected function guardAccess(string $access): ?Response
    {
        if (null === $this->checkAuth([], [CookieConsentManager::getModuleCode()], $access)) {
            return null;
        }

        return $this->forbidden($this->say('You do not have the right to do this on the cookie consent manager. Ask an administrator to grant it to your profile (Configuration, Administration profiles, module "CookieConsentManager").'));
    }

    protected function forbidden(string $message): Response
    {
        return $this->render('CookieConsentManager/forbidden', ['message' => $message], 403);
    }

    /** Every write: the session token in the body, then the right the action needs. */
    protected function guardWrite(Request $request, string $access): ?Response
    {
        if (!$this->guard()->tokenIsValid($request)) {
            return $this->forbidden($this->say('The form is no longer valid (the session expired or the page is outdated): reload the page and try again.'));
        }

        return $this->guardAccess($access);
    }

    private function guard(): AdminGuard
    {
        return new AdminGuard($this->tokenProvider, $this->securityContext);
    }

    protected function author(): ?string
    {
        $admin = $this->securityContext->getAdminUser();

        return \is_object($admin) && method_exists($admin, 'getLogin') ? (string) $admin->getLogin() : null;
    }

    protected function adminId(): int
    {
        $admin = $this->securityContext->getAdminUser();

        return \is_object($admin) && method_exists($admin, 'getId') ? (int) $admin->getId() : 0;
    }

    /**
     * A change that puts a script on the storefront needs the right that publishes: every screen that writes services asks it here.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    protected function guardExecutable(ExecutableChanges $executable, array $before, array $after): ?Response
    {
        return $executable->between($before, $after) ? $this->guardAccess(AccessManager::CREATE) : null;
    }

    protected function flash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    /** @param array<string, mixed> $parameters */
    protected function redirectTo(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, $parameters));
    }

    protected function say(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, self::DOMAIN);
    }

    /**
     * Saves a candidate, then redirects with a success flash, or lets the caller re-render with the refusal.
     *
     * @param list<string>         $prefixes
     * @param array<string, mixed> $candidate
     */
    protected function save(array $candidate, array $prefixes, bool $checkContrast = false): SaveResult
    {
        return $this->writer->save($candidate, $prefixes, $this->author(), $checkContrast);
    }
}
