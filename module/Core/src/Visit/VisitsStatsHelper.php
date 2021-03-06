<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\Core\Visit;

use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Adapter\AdapterInterface;
use Shlinkio\Shlink\Common\Paginator\Paginator;
use Shlinkio\Shlink\Core\Entity\ShortUrl;
use Shlinkio\Shlink\Core\Entity\Tag;
use Shlinkio\Shlink\Core\Entity\Visit;
use Shlinkio\Shlink\Core\Exception\ShortUrlNotFoundException;
use Shlinkio\Shlink\Core\Exception\TagNotFoundException;
use Shlinkio\Shlink\Core\Model\ShortUrlIdentifier;
use Shlinkio\Shlink\Core\Model\VisitsParams;
use Shlinkio\Shlink\Core\Paginator\Adapter\OrphanVisitsPaginatorAdapter;
use Shlinkio\Shlink\Core\Paginator\Adapter\VisitsForTagPaginatorAdapter;
use Shlinkio\Shlink\Core\Paginator\Adapter\VisitsPaginatorAdapter;
use Shlinkio\Shlink\Core\Repository\ShortUrlRepositoryInterface;
use Shlinkio\Shlink\Core\Repository\TagRepository;
use Shlinkio\Shlink\Core\Repository\VisitRepository;
use Shlinkio\Shlink\Core\Repository\VisitRepositoryInterface;
use Shlinkio\Shlink\Core\Visit\Model\VisitsStats;
use Shlinkio\Shlink\Rest\Entity\ApiKey;

class VisitsStatsHelper implements VisitsStatsHelperInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getVisitsStats(?ApiKey $apiKey = null): VisitsStats
    {
        /** @var VisitRepository $visitsRepo */
        $visitsRepo = $this->em->getRepository(Visit::class);

        return new VisitsStats($visitsRepo->countVisits($apiKey), $visitsRepo->countOrphanVisits());
    }

    /**
     * @return Visit[]|Paginator
     * @throws ShortUrlNotFoundException
     */
    public function visitsForShortUrl(
        ShortUrlIdentifier $identifier,
        VisitsParams $params,
        ?ApiKey $apiKey = null
    ): Paginator {
        $spec = $apiKey !== null ? $apiKey->spec() : null;

        /** @var ShortUrlRepositoryInterface $repo */
        $repo = $this->em->getRepository(ShortUrl::class);
        if (! $repo->shortCodeIsInUse($identifier->shortCode(), $identifier->domain(), $spec)) {
            throw ShortUrlNotFoundException::fromNotFound($identifier);
        }

        /** @var VisitRepositoryInterface $repo */
        $repo = $this->em->getRepository(Visit::class);

        return $this->createPaginator(new VisitsPaginatorAdapter($repo, $identifier, $params, $spec), $params);
    }

    /**
     * @return Visit[]|Paginator
     * @throws TagNotFoundException
     */
    public function visitsForTag(string $tag, VisitsParams $params, ?ApiKey $apiKey = null): Paginator
    {
        /** @var TagRepository $tagRepo */
        $tagRepo = $this->em->getRepository(Tag::class);
        if (! $tagRepo->tagExists($tag, $apiKey)) {
            throw TagNotFoundException::fromTag($tag);
        }

        /** @var VisitRepositoryInterface $repo */
        $repo = $this->em->getRepository(Visit::class);

        return $this->createPaginator(new VisitsForTagPaginatorAdapter($repo, $tag, $params, $apiKey), $params);
    }

    /**
     * @return Visit[]|Paginator
     */
    public function orphanVisits(VisitsParams $params): Paginator
    {
        /** @var VisitRepositoryInterface $repo */
        $repo = $this->em->getRepository(Visit::class);

        return $this->createPaginator(new OrphanVisitsPaginatorAdapter($repo, $params), $params);
    }

    private function createPaginator(AdapterInterface $adapter, VisitsParams $params): Paginator
    {
        $paginator = new Paginator($adapter);
        $paginator->setMaxPerPage($params->getItemsPerPage())
                  ->setCurrentPage($params->getPage());

        return $paginator;
    }
}
