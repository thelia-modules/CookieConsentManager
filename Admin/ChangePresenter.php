<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Publication\ConfigDiff;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The differences between two configurations as readable rows: label, old and new value; a whole service added or removed is one row. */
final readonly class ChangePresenter
{
    private const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    /** Fields that hold a code of the module, not a text typed by the administrator. */
    private const CODE_PATHS = ['general.layout', 'consent_mode.mode', 'registry.ip_mode'];

    public function __construct(
        private FieldLabeler $labels,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return list<array{path: string, label: string, before: string, after: string}>
     */
    public function present(array $before, array $after, string $locale): array
    {
        $changes = ConfigDiff::changes($before, $after);
        $byService = [];
        foreach ($changes as $path => $change) {
            if (1 === preg_match('/^services\.([^.]+)\./', $path, $match)) {
                $byService[$match[1]][$path] = $change;
            }
        }

        $rows = [];
        $collapsed = [];
        foreach ($byService as $key => $serviceChanges) {
            $added = [] === array_filter($serviceChanges, static fn (array $change): bool => null !== $change['before']);
            $removed = [] === array_filter($serviceChanges, static fn (array $change): bool => null !== $change['after']);
            if ($added || $removed) {
                $collapsed[$key] = true;
                $rows[] = ['path' => 'services.'.$key, 'label' => $this->labels->label('services.'.$key, $locale, $after + $before), 'before' => $added ? '-' : $this->t('Service configured'), 'after' => $added ? $this->t('Service added') : $this->t('Service removed')];
                // What an added service will run on the storefront is shown in full, never folded.
                foreach (['code', 'script_url'] as $executable) {
                    $path = 'services.'.$key.'.'.$executable;
                    if ($added && isset($serviceChanges[$path]) && '' !== (string) $serviceChanges[$path]['after']) {
                        $rows[] = ['path' => $path, 'label' => $this->labels->label($path, $locale, $after + $before), 'before' => '-', 'after' => (string) $serviceChanges[$path]['after']];
                    }
                }
            }
        }

        foreach ($changes as $path => $change) {
            if (1 === preg_match('/^services\.([^.]+)\./', $path, $match) && isset($collapsed[$match[1]])) {
                continue;
            }
            $rows[] = ['path' => $path, 'label' => $this->labels->label($path, $locale, $after), 'before' => $this->value($path, $change['before']), 'after' => $this->value($path, $change['after'])];
        }

        return $rows;
    }

    /** A value typed by the administrator is shown as typed: only the Consent Mode states, which the module names, are translated. */
    private function value(string $path, mixed $value): string
    {
        return match (true) {
            null === $value => '-',
            true === $value => $this->t('Yes'),
            false === $value => $this->t('No'),
            '' === $value => $this->t('(empty)'),
            \in_array($path, self::CODE_PATHS, true), str_contains($path, '.default.') => $this->t(CodeLabels::english('signal_state', (string) $value)),
            default => (string) $value,
        };
    }

    private function t(string $message): string
    {
        return $this->translator->trans($message, [], self::DOMAIN);
    }
}
