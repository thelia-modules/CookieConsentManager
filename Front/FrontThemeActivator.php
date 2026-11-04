<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * A route of a module is not a page of the theme: nobody has told the Twig loader which theme is
 * active, so a controller that renders templates by bare path has to.
 */
class FrontThemeActivator
{
    public function __construct(
        #[Autowire(service: 'TwigEngine\Template\TwigParser')]
        private readonly ParserInterface $parser,
        private readonly TemplateHelperInterface $templates,
    ) {
    }

    public function activate(): void
    {
        $this->parser->setTemplateDefinition($this->templates->getActiveFrontTemplate(), true);
    }
}
