<?php

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EnvironmentSanityTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironmentAgainstTheIsolatedDatabase(): void
    {
        self::bootKernel();

        self::assertSame('test', self::$kernel->getEnvironment());

        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame('database_test.sqlite', basename((string) $connection->getParams()['path']));
    }
}
