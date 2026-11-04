<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Registry\FirstChoiceMetrics;
use PHPUnit\Framework\TestCase;

final class FirstChoiceMetricsTest extends TestCase
{
    public function testACustomChoiceWithNothingGrantedCountsAsARefusalViaThePanel(): void
    {
        $metrics = FirstChoiceMetrics::of('custom', ['analytics' => 0, 'media' => 0], ['gtag' => 0]);

        self::assertContains('first_reject_panel', $metrics);
        self::assertNotContains('first_custom', $metrics);
        self::assertNotContains('first_reject_all', $metrics);
    }

    public function testACustomChoiceGrantingAPurposeStaysCustom(): void
    {
        $metrics = FirstChoiceMetrics::of('custom', ['analytics' => 1, 'media' => 0], ['gtag' => 1]);

        self::assertContains('first_custom', $metrics);
        self::assertNotContains('first_reject_panel', $metrics);
        self::assertContains('purpose:analytics', $metrics);
    }

    public function testTheButtonsKeepTheirOwnMetric(): void
    {
        self::assertContains('first_reject_all', FirstChoiceMetrics::of('reject_all', ['analytics' => 0], []));
        self::assertContains('first_reject_all', FirstChoiceMetrics::of('close_reject', [], []));
        self::assertContains('first_accept_all', FirstChoiceMetrics::of('accept_all', ['analytics' => 1], ['gtag' => 1]));
    }
}
