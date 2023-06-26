<?php

namespace PixelTrack\Repository;

use PixelTrack\DataTransferObjects\TrackTransfer;
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
            $trackTransfer->setId($row['id']);
            $trackTransfer->setUserid($row['user_id']);
            $trackTransfer->setName($row['name']);
            $trackTransfer->setKey($row['key']);
            $trackTransfer->setFilename($row['filename']);
            $tracks[] = $trackTransfer;
        }

        return $tracks;
    }

    public function insertTrack(string $userKey, string $trackName, string $trackFileName): bool
    {
        $sql = 'SELECT id FROM users WHERE key = :user_key';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':user_key', $userKey);
        $result = $statement->execute();

        if ($result === false) {
            return false;
        }

        $userId = $result->fetchArray(SQLITE3_ASSOC)['id'];

        $trackKey = uniqid();
        $sql = 'INSERT INTO tracks (user_id, name, key, filename) VALUES (:user_id, :name, :track_key, :filename)';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':user_id', $userId);
        $statement->bindValue(':track_key', $trackKey);
        $statement->bindValue(':name', $trackName);
        $statement->bindValue(':filename', $trackFileName);

        return $statement->execute() !== false;
    }

    public function getTrackFilename(string $userKey, string $trackKey): ?TrackTransfer
    {
        $sql = 'SELECT t.* FROM users AS u, tracks AS t WHERE u.id = t.user_id AND u.key = :userKey AND t.key = :trackKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userKey', $userKey, SQLITE3_TEXT);
        $statement->bindValue(':trackKey', $trackKey, SQLITE3_TEXT);
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

    public function isTrackFromUser(int $trackId, int $userId): bool
    {
        $sql = 'SELECT count(*) AS `count` FROM tracks AS t WHERE t.id = :trackId AND t.user_id = :userId';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $statement->bindValue(':trackId', $trackId);
        $result = $statement->execute();

        $databaseRow = $result->fetchArray(SQLITE3_ASSOC);

        return (bool)$databaseRow['count'];
    }

    public function deleteTrack(int $trackId): bool
    {
        $sql = 'DELETE FROM tracks WHERE id = :trackId';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':trackId', $trackId);
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
        $trackTransfer->setId($databaseRow['id']);
        $trackTransfer->setUserid($databaseRow['user_id']);
        $trackTransfer->setName($databaseRow['name']);
        $trackTransfer->setKey($databaseRow['key']);
        $trackTransfer->setFilename($databaseRow['filename']);

        return $trackTransfer;
    }
}
