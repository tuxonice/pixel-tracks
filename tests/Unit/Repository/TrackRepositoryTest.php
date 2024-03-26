<?php

namespace Unit\Repository;

use Codeception\Test\Unit;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Service\Database;

class TrackRepositoryTest extends Unit
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

    public function testInsertTrack(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $trackRepository = new TrackRepository($databaseMock);

        $trackTransfer = new TrackTransfer();
        $trackTransfer->setName('test-track')
            ->setKey('test-key')
            ->setUserId(1)
            ->setDistance(15000)
            ->setElevation(100.0)
            ->setFilename('test-file.gpx')
            ->setTotalPoints(2000)
            ->setCreatedAt(new DateTime());
        self::assertEquals(1, $trackRepository->insertTrack($trackTransfer));
    }

    public function testGetTrackFilename(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $trackRepository = new TrackRepository($databaseMock);
        self::assertEquals(
            (new TrackTransfer())
            ->setId(20)
            ->setUserId(1)
            ->setName('Track name 01')
            ->setKey('6470d852afca9')
            ->setFilename('6470d852af8a1.gpx'),
            $trackRepository->getTrackFilename('6470d852afca9')
        );
        self::assertNull($trackRepository->getTrackFilename('not-found-file.gpx'));
    }

    public function testIsTrackFromUser(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $trackRepository = new TrackRepository($databaseMock);
        self::assertTrue($trackRepository->isTrackFromUser('6470d852afca9', 1));
        self::assertFalse($trackRepository->isTrackFromUser('6470d852afca9', 2));
    }

    public function testGetTrackByKey(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $trackRepository = new TrackRepository($databaseMock);
        self::assertEquals(
            (new TrackTransfer())
            ->setId(5)
            ->setUserId(3)
            ->setName('Track name 01')
            ->setKey('644ea57abfe8d')
            ->setFilename('644ea57abd042.gpx')
            ->setCreatedAt(new DateTime('2023-11-28T20:53:24.000000+0000')),
            $trackRepository->getTrackByKey('644ea57abfe8d')
        );
        self::assertNull($trackRepository->getTrackByKey('not-found-key'));
    }

    public function testDeleteTrack(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $trackRepository = new TrackRepository($databaseMock);
        self::assertEquals(1, $trackRepository->deleteTrack('6470d852afca9'));
        self::assertNull($trackRepository->getTrackByKey('6470d852afca9'));
    }

    public function testGetTrackById(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock->expects(self::once())
            ->method('getDbConnection')
            ->willReturn($this->connection);

        $trackRepository = new TrackRepository($databaseMock);
        self::assertEquals(
            (new TrackTransfer())
            ->setId(5)
            ->setUserId(3)
            ->setName('Track name 01')
            ->setKey('644ea57abfe8d')
            ->setFilename('644ea57abd042.gpx')
            ->setCreatedAt(new DateTime('2023-11-28T20:53:24.000000+0000')),
            $trackRepository->getTrackById(5)
        );
        self::assertNull($trackRepository->getTrackById(999));
    }
}
