<?php

namespace PixelTrack\Repository;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\Service\Database;

class TrackRepository
{
    private Connection $database;

    public function __construct(private readonly Database $databaseService)
    {
        $this->database = $this->databaseService->getDbConnection();
    }

    public function insertTrack(TrackTransfer $trackTransfer): bool
    {
        $queryBuilder = $this->database->createQueryBuilder();
        $queryBuilder
            ->insert('tracks')
            ->values(
                [
                    'user_id' => '?',
                    'name' => '?',
                    'key' => '?',
                    'filename' => '?',
                    'total_points' => '?',
                    'elevation' => '?',
                    'distance' => '?',
                    'created_at' => '?'
                ]
            )
            ->setParameter(0, $trackTransfer->getUserId(), ParameterType::INTEGER)
            ->setParameter(1, $trackTransfer->getName())
            ->setParameter(2, $trackTransfer->getKey())
            ->setParameter(3, $trackTransfer->getFilename())
            ->setParameter(4, $trackTransfer->getTotalPoints(), ParameterType::INTEGER)
            ->setParameter(5, $trackTransfer->getElevation())
            ->setParameter(6, $trackTransfer->getDistance())
            ->setParameter(7, $trackTransfer->getCreatedAt()->format('c'))
        ;
        $result = $queryBuilder->executeQuery();

        return (bool)$result->rowCount();
    }

    public function getTrackFilename(string $trackKey): ?TrackTransfer
    {
        $sql = 'SELECT * FROM tracks WHERE key = :trackKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':trackKey', $trackKey);
        $result = $statement->executeQuery();

        $databaseRow = $result->fetchAssociative();

        if ($databaseRow === false) {
            return null;
        }

        $trackTransfer = new TrackTransfer();
        $trackTransfer->setId($databaseRow['id']);
        $trackTransfer->setUserid($databaseRow['user_id']);
        $trackTransfer->setName($databaseRow['name']);
        $trackTransfer->setKey($databaseRow['key']);
        $trackTransfer->setFilename($databaseRow['filename']);

        return $trackTransfer;
    }

    public function isTrackFromUser(string $trackKey, int $userId): bool
    {
        $sql = 'SELECT count(*) AS `count` FROM tracks AS t WHERE t.key = :trackKey AND t.user_id = :userId';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userId', $userId, ParameterType::INTEGER);
        $statement->bindValue(':trackKey', $trackKey);
        $result = $statement->executeQuery();

        return (bool)$result->fetchOne();
    }

    public function deleteTrack(string $trackKey): bool
    {
        $sql = 'DELETE FROM tracks WHERE key = :trackKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':trackKey', $trackKey);
        $result = $statement->executeQuery();

        return (bool)$result->rowCount();
    }

    public function getTrackById(int $trackId): ?TrackTransfer
    {
        $sql = 'SELECT * FROM tracks WHERE id = :trackId';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':trackId', $trackId, ParameterType::INTEGER);
        $result = $statement->executeQuery();

        $databaseRow = $result->fetchAssociative();

        if ($databaseRow === false) {
            return null;
        }

        $trackTransfer = new TrackTransfer();
        $trackTransfer->setId($databaseRow['id'])
            ->setUserid($databaseRow['user_id'])
            ->setName($databaseRow['name'])
            ->setKey($databaseRow['key'])
            ->setFilename($databaseRow['filename'])
            ->setTotalPoints($databaseRow['total_points'])
            ->setElevation($databaseRow['elevation'])
            ->setDistance($databaseRow['distance'])
            ->setCreatedAt(new DateTime($databaseRow['created_at']));

        return $trackTransfer;
    }

    public function getTrackByKey(string $trackKey): ?TrackTransfer
    {
        $sql = 'SELECT * FROM tracks WHERE key = :trackKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':trackKey', $trackKey);
        $result = $statement->executeQuery();

        $databaseRow = $result->fetchAssociative();

        if ($databaseRow === false) {
            return null;
        }

        $trackTransfer = new TrackTransfer();
        $trackTransfer->setId($databaseRow['id'])
            ->setUserid($databaseRow['user_id'])
            ->setName($databaseRow['name'])
            ->setKey($databaseRow['key'])
            ->setFilename($databaseRow['filename'])
            ->setTotalPoints($databaseRow['total_points'])
            ->setElevation($databaseRow['elevation'])
            ->setDistance($databaseRow['distance'])
            ->setCreatedAt(new DateTime($databaseRow['created_at']));

        return $trackTransfer;
    }
}
