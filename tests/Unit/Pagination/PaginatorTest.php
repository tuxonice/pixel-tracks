<?php

namespace App\Tests\Unit\Pagination;

use App\Pagination\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function testFirstPageOfThreeMarksItselfCurrentAndLinksTheRest(): void
    {
        $paginator = new Paginator('/profile', ['page' => 1]);
        $paginator->setItemsTotal(25)->paginate();

        self::assertSame([
            ['caption' => 'Previous', 'link' => '', 'isCurrent' => false],
            ['caption' => '1', 'link' => '', 'isCurrent' => true],
            ['caption' => '2', 'link' => '/profile?page=2', 'isCurrent' => false],
            ['caption' => '3', 'link' => '/profile?page=3', 'isCurrent' => false],
            ['caption' => 'Next »', 'link' => '/profile?page=2', 'isCurrent' => false],
        ], $paginator->displayPages());
    }

    public function testMiddlePageOfThreeGetsBothPreviousAndNextLinks(): void
    {
        $paginator = new Paginator('/profile', ['page' => 2]);
        $paginator->setItemsTotal(25)->paginate();

        self::assertSame([
            ['caption' => '« Previous', 'link' => '/profile?page=1', 'isCurrent' => false],
            ['caption' => '1', 'link' => '/profile?page=1', 'isCurrent' => false],
            ['caption' => '2', 'link' => '', 'isCurrent' => true],
            ['caption' => '3', 'link' => '/profile?page=3', 'isCurrent' => false],
            ['caption' => 'Next »', 'link' => '/profile?page=3', 'isCurrent' => false],
        ], $paginator->displayPages());
    }

    public function testZeroItemsProducesOnlyDisabledPreviousAndNextLinks(): void
    {
        $paginator = new Paginator('/profile', ['page' => 1]);
        $paginator->setItemsTotal(0)->paginate();

        self::assertSame([
            ['caption' => 'Previous', 'link' => '', 'isCurrent' => false],
            ['caption' => 'Next', 'link' => '', 'isCurrent' => false],
        ], $paginator->displayPages());
    }

    public function testExtraQueryParamsArePreservedAndPageIsAppendedWhenAbsent(): void
    {
        $paginator = new Paginator('/profile', ['filter' => 'mine']);
        $paginator->setItemsTotal(25)->paginate();

        self::assertSame([
            ['caption' => 'Previous', 'link' => '', 'isCurrent' => false],
            ['caption' => '1', 'link' => '', 'isCurrent' => true],
            ['caption' => '2', 'link' => '/profile?filter=mine&page=2', 'isCurrent' => false],
            ['caption' => '3', 'link' => '/profile?filter=mine&page=3', 'isCurrent' => false],
            ['caption' => 'Next »', 'link' => '/profile?filter=mine&page=2', 'isCurrent' => false],
        ], $paginator->displayPages());
    }

    public function testManyPagesTruncatesWithEllipsisAroundTheCurrentPage(): void
    {
        $paginator = new Paginator('/profile', ['page' => 10]);
        $paginator->setItemsTotal(200)->paginate();

        self::assertSame([
            ['caption' => '« Previous', 'link' => '/profile?page=9', 'isCurrent' => false],
            ['caption' => '1', 'link' => '/profile?page=1', 'isCurrent' => false],
            ['caption' => '...', 'link' => '', 'isCurrent' => false],
            ['caption' => '7', 'link' => '/profile?page=7', 'isCurrent' => false],
            ['caption' => '8', 'link' => '/profile?page=8', 'isCurrent' => false],
            ['caption' => '9', 'link' => '/profile?page=9', 'isCurrent' => false],
            ['caption' => '10', 'link' => '', 'isCurrent' => true],
            ['caption' => '11', 'link' => '/profile?page=11', 'isCurrent' => false],
            ['caption' => '12', 'link' => '/profile?page=12', 'isCurrent' => false],
            ['caption' => '13', 'link' => '/profile?page=13', 'isCurrent' => false],
            ['caption' => '...', 'link' => '', 'isCurrent' => false],
            ['caption' => '20', 'link' => '/profile?page=20', 'isCurrent' => false],
            ['caption' => 'Next »', 'link' => '/profile?page=11', 'isCurrent' => false],
        ], $paginator->displayPages());
    }
}
