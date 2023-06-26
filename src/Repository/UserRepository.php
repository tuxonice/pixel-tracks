<?php

namespace PixelTrack\Repository;

use PixelTrack\DataTransferObjects\UserTransfer;
use PixelTrack\Service\Database;
use SQLite3;

class UserRepository
{
    private SQLite3 $database;

    public function __construct(private readonly Database $databaseService)
    {
        $this->database = $this->databaseService->getDbInstance();
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

    public function regenerateUserKey(string $oldKey): string
    {
        $newKey = sha1(uniqid('pixel', true));

        $sql = 'UPDATE users SET key = :newKey WHERE key = :oldKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':newKey', $newKey, SQLITE3_TEXT);
        $statement->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
        $statement->execute();

        return $newKey;
    }

    public function findUserByEmail(string $email): ?string
    {
        $sql = 'SELECT key FROM users WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $statement->execute();

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row === false) {
            return null;
        }

        return $row['key'];
    }

    public function createUserByEmail(string $email): string
    {
        $userKey = sha1(uniqid('pixel', true));

        $sql = 'INSERT INTO users (key, email) VALUES (:user_key, :email)';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':user_key', $userKey, SQLITE3_TEXT);
        $statement->bindValue(':email', $email, SQLITE3_TEXT);
        $statement->execute();

        return $userKey;
    }

    public function getUserByKey(string $key): ?UserTransfer
    {
        $sql = 'SELECT * FROM users AS u WHERE u.key = :userKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userKey', $key);
        $result = $statement->execute();

        $databaseRow = $result->fetchArray(SQLITE3_ASSOC);

        if ($databaseRow === false) {
            return null;
        }

        $trackTransfer = new UserTransfer();
        $trackTransfer->setId($databaseRow['id']);
        $trackTransfer->setKey($databaseRow['key']);
        $trackTransfer->setEmail($databaseRow['id']);

        return $trackTransfer;
    }
}
