<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use CookieConsentManager\Event\ConsentRecordedEvent;
use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Publication\VersionRepository;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class ConsentRecorder
{
    public function __construct(
        private VersionRepository $versions,
        private EntryRepository $entries,
        private DailyStatRepository $stats,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /** @throws ConsentRejectedException 413 when the cookie to write would exceed its size */
    public function record(ConsentPayload $payload, Request $request, ?\DateTimeImmutable $now = null): RecordedConsent
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $consentId = null === $payload->consentId ? Uuid4::generate() : strtolower($payload->consentId);

        $cookie = ConsentCookie::forChoice(
            $consentId,
            $payload->configVersion,
            $now->getTimestamp(),
            $payload->purposes,
            $payload->services,
            $payload->exempt,
            $request->isSecure(),
        );
        if ($cookie->encodedLength() > ConsentCookie::MAX_ENCODED_BYTES) {
            throw new ConsentRejectedException(413, 'too_large');
        }

        $version = $this->versions->find($payload->configVersion);
        $options = $this->versions->snapshot($version)['config']['registry'];
        $browser = BrowserFamily::parse($request->headers->get('User-Agent'));

        $con = Propel::getWriteConnection('TheliaMain');
        $con->beginTransaction();

        try {
            $first = !$this->entries->exists($consentId);

            $entry = (new ConsentEntry())
                ->setConsentId($consentId)
                ->setRecordedAt($now)
                ->setAction($payload->action)
                ->setSource($payload->source)
                ->setVersion($payload->configVersion)
                ->setVersionHash($payload->servedHash)
                ->setLocale($payload->locale)
                ->setPath($payload->path)
                ->setBrowser($browser['label'])
                ->setPurposesJson($this->json($payload->purposes))
                ->setServicesJson($this->json($payload->services))
                ->setExemptJson($this->json($payload->exempt))
                ->setIpTruncated('truncated' === $options['ip_mode'] ? IpTruncator::truncate($request->getClientIp()) : null);
            $entry->save($con);

            if ($first) {
                $this->stats->increment($now, $browser['bot']
                    ? [FirstChoiceMetrics::ROBOT]
                    : FirstChoiceMetrics::of($payload->action, $payload->purposes, $payload->services));
            }

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }

        $this->dispatcher->dispatch(new ConsentRecordedEvent($entry, $first));

        return new RecordedConsent($consentId, $now, $cookie);
    }

    /** @param array<string, int> $states */
    private function json(array $states): string
    {
        return json_encode((object) $states, \JSON_THROW_ON_ERROR);
    }
}
