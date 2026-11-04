<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\EventListener\StatelessRoutesListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\EventListener\KernelListener;

final class StatelessRoutesListenerTest extends TestCase
{
    public function testMarksTheModuleRoutesStatelessAndNothingElse(): void
    {
        $listener = new StatelessRoutesListener();

        foreach (['/ccm/consent' => true, '/ccm/12-abcdef012345.js' => true, '/ccm/preferences' => false, '/fr/creme.html' => false, '/ccmx/consent' => false] as $path => $expected) {
            $request = Request::create($path);
            $listener->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

            self::assertSame($expected, $request->attributes->get('_stateless', false), $path);
        }

        $sub = Request::create('/ccm/consent');
        $listener->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $sub, HttpKernelInterface::SUB_REQUEST));
        self::assertFalse($sub->attributes->get('_stateless', false), 'a sub request is left alone');
    }

    /**
     * The core starts the session at PHP_INT_MAX - 2, before the router could read `stateless: true`:
     * the marker must be set before, or every POST of the registry opens a session.
     */
    public function testRunsBeforeTheCoreStartsTheSession(): void
    {
        self::assertGreaterThan(
            $this->priority(KernelListener::class, 'warmupSession'),
            $this->priority(StatelessRoutesListener::class, 'onRequest'),
        );
        self::assertGreaterThan(
            $this->priority(KernelListener::class, 'initializeLanguageAndAdmin'),
            $this->priority(StatelessRoutesListener::class, 'onRequest'),
        );
    }

    private function priority(string $class, string $method): int
    {
        $attributes = (new \ReflectionMethod($class, $method))->getAttributes(AsEventListener::class);

        return $attributes[0]->newInstance()->priority;
    }
}
