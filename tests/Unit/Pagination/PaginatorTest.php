<?php

namespace Unit\Pagination;

use PixelTrack\Pagination\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function testPaginationForTotalPagesLessThanDisplayPages(): void
    {

        $paginator = new Paginator(
            'https://example.com/list.php',
            [
                'color' => 'black',
                'size' => 'M',
            ],
        );

        $paginator->setItemsTotal(5);
        $paginator->setMidRange(3);
        $paginator->paginate();

        self::assertEquals([
            [
                'caption' => 'Previous',
                'link' => '',
                'isCurrent' => false,
            ],
            [
                'caption' => '1',
                'link' => '',
                'isCurrent' => true,
            ],
            [
                'caption' => 'Next',
                'link' => '',
                'isCurrent' => false,
            ],
        ], $paginator->displayPages());
    }

    public function testPaginationForTotalPagesMoreThanDisplayPagesAndMidRange(): void
    {

        $paginator = new Paginator(
            'https://example.com/list.php',
            [
                'color' => 'black',
                'size' => 'M',
                'ipp' => 10,
            ],
        );

        $paginator->setItemsTotal(150);
        $paginator->setMidRange(5);
        $paginator->paginate();

        self::assertEquals([
            [
                'caption' => '« Previous',
                'link' => '',
                'isCurrent' => false,
            ],
            [
                'caption' => '1',
                'link' => '',
                'isCurrent' => true,
            ],
            [
                'caption' => '2',
                'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                'isCurrent' => false,
            ],
            [
                'caption' => '3',
                'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                'isCurrent' => false,
            ],
            [
                'caption' => '4',
                'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                'isCurrent' => false,
            ],
            [
                'caption' => '5',
                'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                'isCurrent' => false,
            ],
            [
                'caption' => '...',
                'link' => '',
                'isCurrent' => false,
            ],
            [
                'caption' => '15',
                'link' => 'https://example.com/list.php?color=black&size=M&page=15',
                'isCurrent' => false,
            ],
            [
                'caption' => 'Next »',
                'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                'isCurrent' => false,
            ],
        ], $paginator->displayPages());
    }

    /**
     * @dataProvider dataProvider
     *
     * @param int $page
     * @param int $midRange
     * @param array<string,array<string,mixed>> $expected
     * @return void
     */
    public function testPaginationForPages(int $page, int $midRange, array $expected): void
    {

        $paginator = new Paginator(
            'https://example.com/list.php',
            [
                'color' => 'black',
                'size' => 'M',
                'ipp' => 8,
                'page' => $page,
            ],
        );

        $paginator->setItemsTotal(150);
        $paginator->setMidRange($midRange);
        $paginator->paginate();

        self::assertEquals($expected, $paginator->displayPages());
    }

    /**
     * @return array<string,array<string,mixed>> $expected
     */
    public static function dataProvider(): array
    {
        return [
            'Page 1 and midRange 3' => [
                'page' => 1,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => '',
                        'isCurrent' => false,

                    ],
                    [
                        'caption' => '1',
                        'link' => '',
                        'isCurrent' => true,

                    ],
                    [
                        'caption' => '2',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '3',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 2 and midRange 3' => [
                'page' => 2,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '2',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '3',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 3 and midRange 3' => [
                'page' => 3,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '2',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '3',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '4',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 4 and midRange 3' => [
                'page' => 4,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '3',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '4',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '5',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 5 and midRange 3' => [
                'page' => 5,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '4',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '5',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '6',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=6',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=6',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 6 and midRange 3' => [
                'page' => 6,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '5',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '6',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '7',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=7',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=7',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 15 and midRange 3' => [
                'page' => 15,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=14',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '14',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=14',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '15',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '16',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 16 and midRange 3' => [
                'page' => 16,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=15',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '15',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=15',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '16',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '17',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 17 and midRange 3' => [
                'page' => 17,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '16',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '17',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '18',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 18 and midRange 3' => [
                'page' => 18,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '17',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '18',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 19 and midRange 3' => [
                'page' => 19,
                'midRange' => 3,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '17',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '18',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 1 and midRange 5' => [
                'page' => 1,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '2',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '3',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '4',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '5',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 2 and midRange 5' => [
                'page' => 2,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '2',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '3',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '4',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '5',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 3 and midRange 5' => [
                'page' => 3,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '2',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '3',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '4',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '5',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 4 and midRange 5' => [
                'page' => 4,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '2',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=2',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '3',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '4',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '5',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '6',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=6',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 5 and midRange 5' => [
                'page' => 5,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '3',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=3',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '4',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '5',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '6',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=6',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '7',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=7',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=6',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 6 and midRange 5' => [
                'page' => 6,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '4',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=4',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '5',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=5',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '6',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '7',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=7',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '8',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=8',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=7',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 15 and midRange 5' => [
                'page' => 15,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=14',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '13',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=13',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '14',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=14',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '15',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '16',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '17',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 16 and midRange 5' => [
                'page' => 16,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=15',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '14',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=14',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '15',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=15',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '16',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '17',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '18',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 17 and midRange 5' => [
                'page' => 17,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '15',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=15',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '16',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '17',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '18',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 18 and midRange 5' => [
                'page' => 18,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '15',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=15',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '16',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '17',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '18',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => '19',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=19',
                        'isCurrent' => false,
                    ],
                ],
            ],
            'Page 19 and midRange 5' => [
                'page' => 19,
                'midRange' => 5,
                'expected' => [
                    [
                        'caption' => '« Previous',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '1',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=1',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '...',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '15',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=15',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '16',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=16',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '17',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=17',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '18',
                        'link' => 'https://example.com/list.php?color=black&size=M&page=18',
                        'isCurrent' => false,
                    ],
                    [
                        'caption' => '19',
                        'link' => '',
                        'isCurrent' => true,
                    ],
                    [
                        'caption' => 'Next »',
                        'link' => '',
                        'isCurrent' => false,
                    ],
                ],
            ]
        ];
    }
}
