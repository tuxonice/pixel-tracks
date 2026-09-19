<?php

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;

class UserRepositoryTest extends RepositoryTestCase
{
    public function testFindOneByEmailReturnsTheMatchingUser(): void
    {
        $user = $this->persistUser('someone@example.com');

        $found = $this->userRepository()->findOneByEmail('someone@example.com');

        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testFindOneByEmailReturnsNullWhenNoUserMatches(): void
    {
        self::assertNull($this->userRepository()->findOneByEmail('nobody@example.com'));
    }

    private function userRepository(): UserRepository
    {
        return $this->entityManager->getRepository(User::class);
    }
}
