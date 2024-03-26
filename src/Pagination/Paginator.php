<?php

namespace PixelTrack\Pagination;

class Paginator
{
    private int $itemsPerPage;

    private int $items_total;

    private int $current_page;

    private int $num_pages;

    private int $mid_range;

    /**
     * @var array<string,mixed>
     */
    private array $pageLinks;

    private int $default_ipp;

    private string $url;

    /**
     * @var array<string,mixed>
     */
    private array $params;

    /**
     * @param string $url
     * @param array<string,mixed> $params
     */
    public function __construct(string $url, array $params)
    {
        $this->default_ipp = 10;
        if (isset($params['page'])) {
            $this->current_page = (int)$params['page'];
        } else {
            $this->current_page = 1;
        }
        $this->mid_range = 7;

        if (isset($params['ipp'])) {
            $this->itemsPerPage = (int)$params['ipp'];
        } else {
            $this->itemsPerPage = $this->default_ipp;
            $params['ipp'] = $this->default_ipp;
        }
        $this->pageLinks = [];
        $this->url = $url;
        $this->params = $params;
    }



    /**
     *
     * @param int $totalItems
     * @return Paginator
     */
    public function setItemsTotal(int $totalItems): self
    {
        $this->items_total = $totalItems;

        return $this;
    }

    /**
     *
     * @param int $midRange
     * @return Paginator
     */
    public function setMidRange(int $midRange): self
    {
        $this->mid_range = $midRange;

        return $this;
    }

    public function paginate(): void
    {
        if (!is_numeric($this->itemsPerPage) || $this->itemsPerPage <= 0) {
            $this->itemsPerPage = $this->default_ipp;
        }
        $this->num_pages = (int)ceil($this->items_total / $this->itemsPerPage);

        if ($this->current_page < 1) {
            $this->current_page = 1;
        }
        if ($this->current_page > $this->num_pages) {
            $this->current_page = $this->num_pages;
        }

        if ($this->num_pages > 10) {
            $this->processMoreThanTenPages();
        } else {
            $this->processLessThanTenPages();
        }
    }

    private function processLessThanTenPages(): void
    {
        $prev_page = $this->current_page - 1;
        $next_page = $this->current_page + 1;

        //PRIMEIRA PÁGINA
        if ($this->current_page != 1 && $this->items_total >= 10) {
            $this->pageLinks[0] = [
                'caption' => '« ' . 'Previous',
                'link' => $this->url . '?' . $this->makeParams($prev_page),
                'isCurrent' => false,
            ];
        } else {
            $this->pageLinks[0] = [
                'caption' => 'Previous',
                'link' => '',
                'isCurrent' => false,
            ];
        }

        for ($i = 1; $i <= $this->num_pages; $i++) {
            if ($i == $this->current_page) {
                $this->pageLinks[] = [
                    'caption' => (string)$i,
                    'link' => '',
                    'isCurrent' => true
                ];
            } else {
                $this->pageLinks[] = [
                    'caption' => (string)$i,
                    'link' => $this->url . '?' . $this->makeParams($i),
                    'isCurrent' => false,
                ];
            }
        }

        //ULTIMA PÁGINA
        if ((($this->current_page != $this->num_pages && $this->items_total >= 10))) {
            $this->pageLinks[] = array(
                'caption' => 'Next' . ' »',
                'link' => $this->url . '?' . $this->makeParams($next_page),
                'isCurrent' => false
            );
        } else {
            $this->pageLinks[] = [
                'caption' => 'Next',
                'link' => '',
                'isCurrent' => false,
            ];
        }
    }

    private function processMoreThanTenPages(): void
    {
        $prev_page = $this->current_page - 1;
        $next_page = $this->current_page + 1;

        //PRIMEIRA PÁGINA
        if ($this->current_page != 1 && $this->items_total >= 10) {
            $this->pageLinks[0] = [
                'caption' => '« Previous',
                'link' => $this->url . '?' . $this->makeParams($prev_page),
                'isCurrent' => false,
            ];
        } else {
            $this->pageLinks[0] = [
                'caption' => '« Previous',
                'link' => '',
                'isCurrent' => false,
            ];
        }


        $start_range = $this->current_page - floor($this->mid_range / 2);
        $end_range = $this->current_page + floor($this->mid_range / 2);

        if ($start_range <= 0) {
            $end_range += abs($start_range) + 1;
            $start_range = 1;
        }
        if ($end_range > $this->num_pages) {
            $start_range -= $end_range - $this->num_pages;
            $end_range = $this->num_pages;
        }
        $range = range($start_range, $end_range);

        for ($i = 1; $i <= $this->num_pages; $i++) {
            if ($range[0] > 2 && $i == $range[0]) {
                $this->pageLinks[] = [
                    'caption' => '...',
                    'link' => '',
                    'isCurrent' => false,
                ];
            }


            // loop through all pages. if first, last, or in range, display
            if ($i == 1 || $i == $this->num_pages || in_array($i, $range)) {
                if ($i == $this->current_page) {
                    $this->pageLinks[] = [
                        'caption' => (string)$i,
                        'link' => '',
                        'isCurrent' => true,
                    ];
                } else {
                    $this->pageLinks[] = [
                        'caption' => (string)$i,
                        'link' => $this->url . '?' . $this->makeParams($i),
                        'isCurrent' => false,
                    ];
                }
            }

            if ($range[$this->mid_range - 1] < $this->num_pages - 1 && $i == $range[$this->mid_range - 1]) {
                $this->pageLinks[] = [
                    'caption' => '...',
                    'link' => '',
                    'isCurrent' => false,
                ];
            }
        }

        //ULTIMA PÁGINA
        if ((($this->current_page != $this->num_pages && $this->items_total >= 10))) {
            $this->pageLinks[] = [
                'caption' => 'Next' . ' »',
                'link' => $this->url . '?' . $this->makeParams($next_page),
                'isCurrent' => false,
            ];
        } else {
            $this->pageLinks[] = [
                'caption' => 'Next' . ' »',
                'link' => '',
                'isCurrent' => false,
            ];
        }
    }

    private function makeParams(int $page): string
    {

        $_temp_url = [];
        foreach ($this->params as $key => $value) {
            if ($key == 'page') {
                $_temp_url[] = $key . '=' . $page;
                continue;
            }
            if ($key == 'ipp') {
                //$_temp_url[] = $key . '=' . $this->items_per_page;
                continue;
            }
            $_temp_url[] = $key . '=' . $value;
        }
        if (!isset($this->params['page'])) {
            $_temp_url[] = 'page' . '=' . $page;
        }

//        if (!isset($this->params['ipp'])) {
//            $_temp_url[] = 'ipp' . '=' . $this->items_per_page;
//        }

        return implode('&', $_temp_url);
    }


    /**
     * @return array<string,mixed>
     */
    public function displayPages(): array
    {
        return $this->pageLinks;
    }
}
