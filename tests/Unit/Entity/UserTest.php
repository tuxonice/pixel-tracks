<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testANewUserHasNoLoginCodePending(): void
    {
        $user = new User('someone@example.com');

        self::assertNull($user->getLoginCodeHash());
        self::assertNull($user->getLoginCodeExpiresAt());
        self::assertSame(0, $user->getLoginCodeAttempts());
    }

    public function testSettingALoginCodeMakesItReadableAgain(): void
    {
        $user = new User('someone@example.com');
        $expiresAt = new \DateTimeImmutable('+5 minutes');

        $user->setLoginCodeHash('a-hash');
        $user->setLoginCodeExpiresAt($expiresAt);
        $user->setLoginCodeAttempts(2);

        self::assertSame('a-hash', $user->getLoginCodeHash());
        self::assertSame($expiresAt, $user->getLoginCodeExpiresAt());
        self::assertSame(2, $user->getLoginCodeAttempts());
    }

    public function testIncrementLoginCodeAttemptsIncreasesByOneEachCall(): void
    {
        $user = new User('someone@example.com');

        $user->incrementLoginCodeAttempts();
        $user->incrementLoginCodeAttempts();

        self::assertSame(2, $user->getLoginCodeAttempts());
    }

    public function testClearLoginCodeResetsEverything(): void
    {
        $user = new User('someone@example.com');
        $user->setLoginCodeHash('a-hash');
        $user->setLoginCodeExpiresAt(new \DateTimeImmutable('+5 minutes'));
        $user->incrementLoginCodeAttempts();

        $user->clearLoginCode();

        self::assertNull($user->getLoginCodeHash());
        self::assertNull($user->getLoginCodeExpiresAt());
        self::assertSame(0, $user->getLoginCodeAttempts());
    }
}
