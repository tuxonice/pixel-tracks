<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Track;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class RepositoryTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Rebuilding the schema straight from entity metadata (rather than running
        // migrations) keeps each test isolated and fast, independent of migration history.
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
    }

    protected function persistUser(string $email = 'user@example.com'): User
    {
        $user = new User($email);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function persistTrack(User $user, string $name, \DateTimeImmutable $createdAt): Track
    {
        $track = new Track($user, $name, $name . '.gpx');

        // Track's constructor stamps createdAt with "now"; tests that assert ordering
        // need distinct, controlled timestamps, so it's overridden via reflection since
        // the entity intentionally exposes no setter for it.
        $property = new \ReflectionProperty(Track::class, 'createdAt');
        $property->setValue($track, $createdAt);

        $this->entityManager->persist($track);
        $this->entityManager->flush();

        return $track;
    }
}
