<?php

namespace App\Tests\Integration\Repository;

use App\Repository\TrackRepository;

class TrackRepositoryTest extends RepositoryTestCase
{
    public function testFindOneByKeyReturnsTheMatchingTrack(): void
    {
        $user = $this->persistUser();
        $track = $this->persistTrack($user, 'morning-run', new \DateTimeImmutable('2026-01-01 08:00:00'));

        $found = $this->trackRepository()->findOneByKey($track->getKey());

        self::assertNotNull($found);
        self::assertSame($track->getId(), $found->getId());
        self::assertSame('morning-run', $found->getName());
    }

    public function testFindOneByKeyReturnsNullWhenNoTrackMatches(): void
    {
        $this->persistUser();

        self::assertNull($this->trackRepository()->findOneByKey('does-not-exist'));
    }

    public function testCountForUserOnlyCountsThatUsersOwnTracks(): void
    {
        $userA = $this->persistUser('a@example.com');
        $userB = $this->persistUser('b@example.com');

        $this->persistTrack($userA, 'a-track-1', new \DateTimeImmutable('2026-01-01 08:00:00'));
        $this->persistTrack($userA, 'a-track-2', new \DateTimeImmutable('2026-01-02 08:00:00'));
        $this->persistTrack($userB, 'b-track-1', new \DateTimeImmutable('2026-01-01 08:00:00'));

        self::assertSame(2, $this->trackRepository()->countForUser($userA));
        self::assertSame(1, $this->trackRepository()->countForUser($userB));
    }

    public function testFindPageForUserOrdersByCreatedAtDescendingAndScopesToTheUser(): void
    {
        $userA = $this->persistUser('a@example.com');
        $userB = $this->persistUser('b@example.com');

        $this->persistTrack($userA, 'oldest', new \DateTimeImmutable('2026-01-01 08:00:00'));
        $this->persistTrack($userA, 'middle', new \DateTimeImmutable('2026-01-02 08:00:00'));
        $this->persistTrack($userA, 'newest', new \DateTimeImmutable('2026-01-03 08:00:00'));
        $this->persistTrack($userB, 'other-users-track', new \DateTimeImmutable('2026-01-04 08:00:00'));

        $firstPage = $this->trackRepository()->findPageForUser($userA, offset: 0, limit: 2);
        self::assertSame(['newest', 'middle'], array_map(static fn ($t) => $t->getName(), $firstPage));

        $secondPage = $this->trackRepository()->findPageForUser($userA, offset: 2, limit: 2);
        self::assertSame(['oldest'], array_map(static fn ($t) => $t->getName(), $secondPage));
    }

    private function trackRepository(): TrackRepository
    {
        return $this->entityManager->getRepository(\App\Entity\Track::class);
    }
}
