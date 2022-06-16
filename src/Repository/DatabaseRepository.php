<?php

namespace PixelTrack\Repository;

use PixelTrack\Service\Database;
use SQLite3;

class DatabaseRepository
{
    private SQLite3 $database;


    public function __construct(private Database $databaseService)
    {
        $this->database = $this->databaseService->getDbInstance();
    }

    public function createDatabase(): void
    {
        $this->database->query('CREATE TABLE IF NOT EXISTS "users" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,            
                "key" VARCHAR NOT NULL,
                "email" VARCHAR NOT NULL
            )');

        $this->database->query('CREATE TABLE IF NOT EXISTS "tracks" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT, 
                "user_id" INTEGER NOT NULL,
                "name" VARCHAR NOT NULL,
                "key" VARCHAR NOT NULL,            
                "filename" VARCHAR NOT NULL
            )');
    }

    public function getTracksFromUser(string $userKey): array
    {
        $sql = 'SELECT t.key, t.name FROM users AS u, tracks AS t WHERE u.key = :userKey AND u.id = t.user_id';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userKey', $userKey, SQLITE3_TEXT);
        $result = $statement->execute();

        $tracks = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tracks[] = [
                'name' => $row['name'],
                'key' => $row['key'],
            ];
        }

        return $tracks;
    }

    public function userExists(string $userKey): bool
    {
        $sql = 'SELECT count(*) AS userCount FROM users AS u WHERE u.key = :userKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userKey', $userKey);
        $result = $statement->execute();

        if ($result === false) {
            return false;
        }

        return (bool)$result->fetchArray(SQLITE3_ASSOC)['userCount'];
    }

    public function findOrCreateUserByEmail(string $email): string
    {
        $sql = 'SELECT * FROM users WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $statement->execute();

        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row !== false) {
            return $row['key'];
        }

        $userKey = md5(uniqid());

        $sql = 'INSERT INTO users (key, email) VALUES (:user_key, :email)';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':user_key', $userKey, SQLITE3_TEXT);
        $statement->bindValue(':email', $email, SQLITE3_TEXT);
        $statement->execute();

        return $userKey;
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

    public function getTrackFilename(string $userKey, string $trackKey): ?string
    {
        $sql = 'SELECT t.filename FROM users AS u, tracks AS t WHERE u.id = t.user_id AND u.key = :userKey AND t.key = :trackKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userKey', $userKey, SQLITE3_TEXT);
        $statement->bindValue(':trackKey', $trackKey, SQLITE3_TEXT);
        $result = $statement->execute();

        $databaseRow = $result->fetchArray(SQLITE3_ASSOC);

        if ($databaseRow === false) {
            return null;
        }

        return $databaseRow['filename'];
    }
}
