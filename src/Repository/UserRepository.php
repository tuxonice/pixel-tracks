<?php

namespace PixelTrack\Repository;

use DateInterval;
use DateTime;
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

    public function regenerateUserKey(string $email): string
    {
        $newKey = Uuid::v4();

        $sql = 'UPDATE users SET key = :newKey WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':newKey', $newKey);
        $statement->bindValue(':email', $email);
        $statement->execute();

        return $newKey;
    }

    public function findUserByLoginKey(string $loginKey, int $toleranceInMinutes): ?UserTransfer
    {
        $sql = "SELECT * FROM users WHERE login_key = :login_key AND DATETIME() <= DATETIME(updated_at, '+" . $toleranceInMinutes . " minutes')";
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':login_key', $loginKey);
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
        $userTransfer->setEmail($databaseRow['email']);

        return $userTransfer;
    }

    public function regenerateLoginKey(string $email): string
    {
        $loginKey = Uuid::v4();

        $sql = 'UPDATE users SET login_key = :newLoginKey, updated_at = :updated_at WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':newLoginKey', $loginKey, SQLITE3_TEXT);
        $statement->bindValue(':updated_at', (new DateTime())->format('c'), SQLITE3_TEXT);
        $statement->bindValue(':email', $email, SQLITE3_TEXT);
        $statement->execute();

        return $loginKey;
    }

    public function resetLoginKey(string $email): void
    {
        $sql = 'UPDATE users SET login_key = NULL WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':email', $email);
        $statement->execute();
    }
}
