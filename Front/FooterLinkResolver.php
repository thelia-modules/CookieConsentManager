<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Core\Content\Slot\ContentSlotLink;
use Thelia\Core\Content\Slot\ContentSlotResolverInterface;
use Thelia\Core\Content\Slot\ContentSlots;

/**
 * Owns the slot `footer_links` (Thelia 3.2 and later) to give the other resolvers' links back with
 * the link that reopens the preferences at the end: `ContentSlotLink` carries nothing but a label
 * and a URL, so the link goes to the page of the module that explains the choices without JavaScript. Registered only where the
 * interface exists.
 */
#[AsTaggedItem(priority: 1000)]
final readonly class FooterLinkResolver implements ContentSlotResolverInterface
{
    public const ANCHOR = '#ccm-preferences';
    public const URL = '/ccm/preferences'.self::ANCHOR;

    /** @param iterable<ContentSlotResolverInterface> $resolvers */
    public function __construct(
        private VersionRepository $versions,
        private TemplateContext $context,
        #[AutowireIterator('thelia.content_slot_resolver')]
        private iterable $resolvers,
    ) {
    }

    public function resolve(string $slot, string $locale): ?array
    {
        $latest = $this->versions->latest();
        if (ContentSlots::FOOTER !== $slot || null === $latest) {
            return null;
        }

        $links = [];
        foreach ($this->resolvers as $resolver) {
            if ($resolver instanceof self) {
                continue;
            }
            $answer = $resolver->resolve($slot, $locale);
            if (null !== $answer) {
                $links = $answer;
                break;
            }
        }

        $language = strtolower(substr($locale, 0, 2));
        $label = $this->context->texts($this->versions->snapshot($latest)['config']['texts'], $language)['preferences'];

        return [...$links, new ContentSlotLink($label, self::URL, 'ccm')];
    }
}
