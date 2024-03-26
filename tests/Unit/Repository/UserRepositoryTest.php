<?php

namespace Unit\Repository;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Database;

class UserRepositoryTest extends Unit
{
    private ?Connection $connection = null;

    // phpcs:ignore
    protected function _before(): void
    {
        // Restore database
        copy('tests/_data/dump.sqlite', 'tests/_data/test-database.sqlite');
        $connectionParams = [
            'path' => 'tests/_data/test-database.sqlite',
            'driver' => 'sqlite3',
        ];

        $this->connection = DriverManager::getConnection($connectionParams);
    }

    public function testUserExists(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $userRepository = new UserRepository($databaseMock);

        self::assertTrue($userRepository->userExists('4b094973-ba0d-47d5-b43f-385b3d1be072'));
        self::assertFalse($userRepository->userExists('invalid-key'));
    }

    public function testRegenerateUserKey(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $userRepository = new UserRepository($databaseMock);

        self::assertEquals(
            (new UserTransfer())
                ->setId(1)
                ->setKey('4a1706e1-ece2-4bc4-8238-cc62a5afe9ca')
                ->setEmail('user01@example.com'),
            $userRepository->findUserByEmail('user01@example.com')
        );
        $newKey = $userRepository->regenerateUserKey('user01@example.com');
        self::assertEquals(
            (new UserTransfer())
                ->setId(1)
                ->setKey($newKey)
                ->setEmail('user01@example.com'),
            $userRepository->findUserByEmail('user01@example.com')
        );
        self::assertNull($userRepository->regenerateUserKey('invalid-user@example.com'));
    }

    public function testFindUserByEmail(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $userRepository = new UserRepository($databaseMock);

        self::assertEquals(
            (new UserTransfer())
            ->setId(1)
            ->setKey('4a1706e1-ece2-4bc4-8238-cc62a5afe9ca')
            ->setEmail('user01@example.com'),
            $userRepository->findUserByEmail('user01@example.com')
        );
        self::assertNull($userRepository->findUserByEmail('invalid-user@example.com'));
    }

    public function testCreateUserByEmail(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $userRepository = new UserRepository($databaseMock);
        self::assertNull($userRepository->findUserByEmail('new-user@example.com'));
        $userKey = $userRepository->createUserByEmail('new-user@example.com');

        $userTransfer = $userRepository->getUserByKey($userKey);
        self::assertEquals(9, $userTransfer->getId());
        self::assertEquals($userKey, $userTransfer->getKey());
        self::assertEquals('new-user@example.com', $userTransfer->getEmail());
    }

    public function testGetUserByKey(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $userRepository = new UserRepository($databaseMock);

        self::assertEquals(
            (new UserTransfer())
            ->setId(2)
            ->setKey('4b094973-ba0d-47d5-b43f-385b3d1be072')
            ->setEmail('user02@example.com'),
            $userRepository->getUserByKey('4b094973-ba0d-47d5-b43f-385b3d1be072')
        );
        self::assertNull($userRepository->getUserByKey('invalid-key'));
    }

    public function testFindUserByLoginKey(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $userRepository = new UserRepository($databaseMock);
        $newLoginKey = $userRepository->regenerateLoginKey('user03@example.com');

        $userTransfer = $userRepository->findUserByLoginKey($newLoginKey, 1);
        $expectedUserTransfer = (new UserTransfer())
            ->setId(3)
            ->setKey('2d0f92da-b0cb-4a62-9cb1-a8ca6e27e062')
            ->setLoginKey($newLoginKey)
            ->setEmail('user03@example.com');

        self::assertEquals($expectedUserTransfer->getId(), $userTransfer->getId());
        self::assertEquals($expectedUserTransfer->getKey(), $userTransfer->getKey());
        self::assertEquals($expectedUserTransfer->getLoginKey(), $userTransfer->getLoginKey());
        self::assertEquals($expectedUserTransfer->getEmail(), $userTransfer->getEmail());
        self::assertNull($userRepository->findUserByLoginKey('invalid-login-key', 1));
    }

    public function testRegenerateLoginKey(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $userRepository = new UserRepository($databaseMock);

        $userTransfer1 = $userRepository->findUserByEmail('user03@example.com');
        $newLoginKey = $userRepository->regenerateLoginKey('user03@example.com');
        $userTransfer2 = $userRepository->findUserByEmail('user03@example.com');

        self::assertNotEquals($userTransfer1->getLoginKey(), $newLoginKey);
        self::assertEquals($userTransfer2->getLoginKey(), $newLoginKey);
        self::assertNotEquals($userTransfer1->getUpdatedAt(), $userTransfer2->getUpdatedAt());
    }

    public function testResetLoginKey(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $userRepository = new UserRepository($databaseMock);
        $userRepository->resetLoginKey('user03@example.com');
        $userTransfer = $userRepository->findUserByEmail('user03@example.com');
        self::assertNull($userTransfer->getLoginKey());
    }
}
