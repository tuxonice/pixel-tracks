<?php

namespace PixelTrack\Repository;

use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Service\Database;
use SQLite3;
use Symfony\Component\Uid\Uuid;

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
        $newKey = Uuid::v4();

        $sql = 'UPDATE users SET key = :newKey WHERE key = :oldKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':newKey', $newKey, SQLITE3_TEXT);
        $statement->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
        $statement->execute();

        return $newKey;
    }

    public function findUserByLoginKey(string $loginKey): ?UserTransfer
    {
        $sql = 'SELECT * FROM users WHERE login_key = :login_key';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':login_key', $loginKey, SQLITE3_TEXT);
        $result = $statement->execute();

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row === false) {
            return null;
        }

        $userTransfer = new UserTransfer();
        $userTransfer->setId($row['id']);
        $userTransfer->setKey($row['key']);
        $userTransfer->setEmail($row['email']);

        return $userTransfer;
    }

    public function findUserByEmail(string $email): ?UserTransfer
    {
        $sql = 'SELECT * FROM users AS u WHERE u.email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':email', $email);
        $result = $statement->execute();

        $databaseRow = $result->fetchArray(SQLITE3_ASSOC);

        if ($databaseRow === false) {
            return null;
        }

        $userTransfer = new UserTransfer();
        $userTransfer->setId($databaseRow['id']);
        $userTransfer->setKey($databaseRow['key']);
        $userTransfer->setEmail($databaseRow['email']);

        return $userTransfer;
    }

    public function createUserByEmail(string $email): string
    {
        $userKey = Uuid::v4();

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

        $userTransfer = new UserTransfer();
        $userTransfer->setId($databaseRow['id']);
        $userTransfer->setKey($databaseRow['key']);
        $userTransfer->setEmail($databaseRow['id']);

        return $userTransfer;
    }

    public function regenerateLoginKey(string $email): string
    {
        $loginKey = Uuid::v4();

        $sql = 'UPDATE users SET login_key = :newLoginKey WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':newLoginKey', $loginKey, SQLITE3_TEXT);
        $statement->bindValue(':email', $email, SQLITE3_TEXT);
        $statement->execute();

        return $loginKey;
    }
}
