<?php

namespace PixelTrack\Repository;

use DateTime;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\Service\Database;
use SQLite3;

class TrackRepository
{
    private SQLite3 $database;

    public function __construct(private readonly Database $databaseService)
    {
        $this->database = $this->databaseService->getDbInstance();
    }

    /**
     * @param string $userKey
     *
     * @return array<TrackTransfer>
     */
    public function getTracksFromUser(string $userKey): array
    {
        $sql = 'SELECT t.* FROM users AS u, tracks AS t WHERE u.key = :userKey AND u.id = t.user_id';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userKey', $userKey, SQLITE3_TEXT);
        $result = $statement->execute();

        if ($result === false) {
            return [];
        }

        $tracks = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
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
            $tracks[] = $trackTransfer;
        }

        return $tracks;
    }

    public function insertTrack(TrackTransfer $trackTransfer): bool
    {
        $sql = 'INSERT INTO tracks (user_id, name, key, filename, total_points, elevation, distance, created_at) VALUES (:user_id, :name, :track_key, :filename, :total_points, :elevation, :distance, :created_at)';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':user_id', $trackTransfer->getUserId());
        $statement->bindValue(':track_key', $trackTransfer->getKey());
        $statement->bindValue(':name', $trackTransfer->getName());
        $statement->bindValue(':filename', $trackTransfer->getFilename());
        $statement->bindValue(':total_points', $trackTransfer->getTotalPoints());
        $statement->bindValue(':elevation', $trackTransfer->getElevation());
        $statement->bindValue(':distance', $trackTransfer->getDistance());
        $statement->bindValue(':created_at', $trackTransfer->getCreatedAt()->format('c'));

        return $statement->execute() !== false;
    }

    public function getTrackFilename(string $trackKey): ?TrackTransfer
    {
        $sql = 'SELECT * FROM tracks WHERE key = :trackKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':trackKey', $trackKey);
        $result = $statement->execute();

        $databaseRow = $result->fetchArray(SQLITE3_ASSOC);

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
        $statement->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $statement->bindValue(':trackKey', $trackKey);
        $result = $statement->execute();

        $databaseRow = $result->fetchArray(SQLITE3_ASSOC);

        return (bool)$databaseRow['count'];
    }

    public function deleteTrack(string $trackKey): bool
    {
        $sql = 'DELETE FROM tracks WHERE key = :trackKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':trackKey', $trackKey);
        $result = $statement->execute();

        return (bool)$result;
    }

    public function getTrackById(int $trackId): ?TrackTransfer
    {
        $sql = 'SELECT * FROM tracks WHERE id = :trackId';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':trackId', $trackId, SQLITE3_INTEGER);
        $result = $statement->execute();

        $databaseRow = $result->fetchArray(SQLITE3_ASSOC);

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
        $result = $statement->execute();

        $databaseRow = $result->fetchArray(SQLITE3_ASSOC);

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
