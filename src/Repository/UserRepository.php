<?php

namespace PixelTrack\Repository;

use DateInterval;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Service\Database;
use SQLite3;
use Symfony\Component\Uid\Uuid;

class UserRepository
{
    private Connection $database;

    public function __construct(private readonly Database $databaseService)
    {
        $this->database = $this->databaseService->getDbConnection();
    }

    public function userExists(string $userKey): bool
    {
        $sql = 'SELECT count(*) AS userCount FROM users AS u WHERE u.key = :userKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userKey', $userKey);
        $result = $statement->executeQuery();

        return (bool)$result->fetchOne();
    }

    public function regenerateUserKey(string $email): ?string
    {
        $newKey = Uuid::v4();

        $sql = 'UPDATE users SET key = :newKey WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':newKey', $newKey);
        $statement->bindValue(':email', $email);
        $result = $statement->executeQuery();

        if (!$result->rowCount()) {
            return null;
        }

        return $newKey;
    }

    public function findUserByLoginKey(string $loginKey, int $toleranceInMinutes): ?UserTransfer
    {
        $sql = "SELECT * FROM users WHERE login_key = :login_key AND DATETIME() <= DATETIME(updated_at, '+" . $toleranceInMinutes . " minutes')";
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':login_key', $loginKey);
        $result = $statement->executeQuery();

        $row = $result->fetchAssociative();
        if ($row === false) {
            return null;
        }

        $userTransfer = new UserTransfer();
        $userTransfer->setId($row['id']);
        $userTransfer->setKey($row['key']);
        $userTransfer->setEmail($row['email']);
        $userTransfer->setLoginKey($row['login_key']);
        $userTransfer->setUpdatedAt(new DateTime($row['updated_at']));

        return $userTransfer;
    }

    public function findUserByEmail(string $email): ?UserTransfer
    {
        $sql = 'SELECT * FROM users AS u WHERE u.email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':email', $email);
        $result = $statement->executeQuery();

        $databaseRow = $result->fetchAssociative();

        if ($databaseRow === false) {
            return null;
        }

        $userTransfer = new UserTransfer();
        $userTransfer->setId($databaseRow['id']);
        $userTransfer->setKey($databaseRow['key']);
        $userTransfer->setEmail($databaseRow['email']);
        $userTransfer->setLoginKey($databaseRow['login_key']);
        if ($databaseRow['updated_at'] !== null) {
            $userTransfer->setUpdatedAt(new DateTime($databaseRow['updated_at']));
        }


        return $userTransfer;
    }

    //TODO: replace return type by a UserTransfer
    public function createUserByEmail(string $email): string
    {
        $userKey = Uuid::v4();

        $sql = 'INSERT INTO users (key, email) VALUES (:user_key, :email)';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':user_key', $userKey);
        $statement->bindValue(':email', $email);
        $statement->executeQuery();

        return $userKey;
    }

    public function getUserByKey(string $key): ?UserTransfer
    {
        $sql = 'SELECT * FROM users AS u WHERE u.key = :userKey';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':userKey', $key);
        $result = $statement->executeQuery();

        $databaseRow = $result->fetchAssociative();

        if ($databaseRow === false) {
            return null;
        }

        $userTransfer = new UserTransfer();
        $userTransfer->setId($databaseRow['id']);
        $userTransfer->setKey($databaseRow['key']);
        $userTransfer->setEmail($databaseRow['email']);
        $userTransfer->setLoginKey($databaseRow['login_key']);
        if ($databaseRow['updated_at'] !== null) {
            $userTransfer->setUpdatedAt(new DateTime($databaseRow['updated_at']));
        }

        return $userTransfer;
    }

    public function regenerateLoginKey(string $email): string
    {
        $loginKey = Uuid::v4();

        $sql = 'UPDATE users SET login_key = :newLoginKey, updated_at = :updated_at WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':newLoginKey', $loginKey);
        $statement->bindValue(':updated_at', (new DateTime())->format('c'));
        $statement->bindValue(':email', $email);
        $statement->executeQuery();

        return $loginKey;
    }

    public function resetLoginKey(string $email): void
    {
        $sql = 'UPDATE users SET login_key = NULL WHERE email = :email';
        $statement = $this->database->prepare($sql);
        $statement->bindValue(':email', $email);
        $statement->executeQuery();
    }
}
