<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\Core\EventDispatcher;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Shlinkio\Shlink\Core\Entity\Visit;
use Shlinkio\Shlink\Core\EventDispatcher\Event\VisitLocated;
use Shlinkio\Shlink\Core\Mercure\MercureUpdatesGeneratorInterface;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

use function Functional\each;

class NotifyVisitToMercure
{
    private PublisherInterface $publisher;
    private MercureUpdatesGeneratorInterface $updatesGenerator;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        PublisherInterface $publisher,
        MercureUpdatesGeneratorInterface $updatesGenerator,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->publisher = $publisher;
        $this->em = $em;
        $this->logger = $logger;
        $this->updatesGenerator = $updatesGenerator;
    }

    public function __invoke(VisitLocated $shortUrlLocated): void
    {
        $visitId = $shortUrlLocated->visitId();

        /** @var Visit|null $visit */
        $visit = $this->em->find(Visit::class, $visitId);
        if ($visit === null) {
            $this->logger->warning('Tried to notify mercure for visit with id "{visitId}", but it does not exist.', [
                'visitId' => $visitId,
            ]);
            return;
        }

        try {
            each($this->determineUpdatesForVisit($visit), fn (Update $update) => ($this->publisher)($update));
        } catch (Throwable $e) {
            $this->logger->debug('Error while trying to notify mercure hub with new visit. {e}', [
                'e' => $e,
            ]);
        }
    }

    /**
     * @return Update[]
     */
    private function determineUpdatesForVisit(Visit $visit): array
    {
        if ($visit->isOrphan()) {
            return [$this->updatesGenerator->newOrphanVisitUpdate($visit)];
        }

        return [
            $this->updatesGenerator->newShortUrlVisitUpdate($visit),
            $this->updatesGenerator->newVisitUpdate($visit),
        ];
    }
}
