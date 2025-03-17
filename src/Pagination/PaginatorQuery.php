<?php

namespace PixelTrack\Pagination;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PixelTrack\DataTransfers\DataTransferObjects\PaginatedTrackTransfer;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\Service\Database;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\Request;

class PaginatorQuery
{
    private Connection $database;

    public function __construct(
        private readonly Database $databaseService,
        private readonly Twig $twig,
        private readonly Request $request
    ) {
        $this->database = $this->databaseService->getDbConnection();
    }

    /**
     * @param string $userKey
     * @param int $page
     * @param int $ipp
     *
     * @return PaginatedTrackTransfer
     */
    public function getTracksFromUser(string $userKey, int $page, int $ipp): PaginatedTrackTransfer
    {
        $queryBuilder = $this->database->createQueryBuilder();
        $queryBuilder
            ->select('count(t.id)')
            ->from('users', 'u')
            ->innerJoin('u', 'tracks', 't', 'u.id = t.user_id')
            ->where(
                $queryBuilder->expr()->eq('u.key', '?')
            )
            ->setParameter(0, $userKey);

        $total = $queryBuilder->executeQuery()->fetchOne();
        $offset = ($page - 1) * $ipp;
        $limit = $ipp;

        $queryBuilder = $this->database->createQueryBuilder();
        $queryBuilder
            ->select('t.*')
            ->from('users', 'u')
            ->innerJoin('u', 'tracks', 't', 'u.id = t.user_id')
            ->where(
                $queryBuilder->expr()->eq('u.key', '?')
            )
            ->orderBy('t.created_at', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter(0, $userKey);

        $result = $queryBuilder->executeQuery();

        $paginatedTrackTransfer = new PaginatedTrackTransfer();
        $paginatedTrackTransfer
            ->setTotalRecords($total);

        foreach ($result->fetchAllAssociative() as $row) {
            $trackTransfer = new TrackTransfer();
            $trackTransfer->setId($row['id'])
                ->setUserid($row['user_id'])
                ->setName($row['name'])
                ->setKey($row['key'])
                ->setFilename($row['filename'])
                ->setTotalPoints($row['total_points'])
                ->setElevation($row['elevation'])
                ->setDistance($row['distance'])
                ->setCreatedAt(new DateTime($row['created_at']));

            $paginatedTrackTransfer->addTrack($trackTransfer);
        }

        $paginator = new Paginator($this->request->getPathInfo(), [
            'page' => $page,
            'ipp' => $ipp,
        ]);
        $paginator->setItemsTotal($total);
        $paginator->setMidRange(3);
        $paginator->paginate();

        $paginatedTrackTransfer->setTemplate($this->render($paginator));

        return $paginatedTrackTransfer;
    }

    private function render(Paginator $paginator): string
    {
        $template = $this->twig->getTwig()->load('Default/Blocks/pagination.twig');
        $view = $template->render([
            'pages' => $paginator->displayPages(),
        ]);

        return $view;
    }
}
