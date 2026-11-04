<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

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

    /** @param iterable<ContentSlotResolverInterface> $resolvers */
    public function __construct(
        private FrontConfigProvider $configs,
        private TemplateContext $context,
        #[AutowireIterator('thelia.content_slot_resolver')]
        private iterable $resolvers,
    ) {
    }

    public function resolve(string $slot, string $locale): ?array
    {
        if (ContentSlots::FOOTER !== $slot) {
            return null;
        }

        $front = $this->configs->forLocale($locale);
        if (null === $front) {
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
        $label = $this->context->texts($front->snapshot['config']['texts'], $language)['preferences'];

        return [...$links, new ContentSlotLink($label, $front->config['preferencesUrl'].self::ANCHOR, 'ccm')];
    }
}
