<?php

namespace App\Pagination;

class Paginator
{
    private int $itemsPerPage;
    private int $items_total;
    private int $current_page;
    private int $num_pages;
    private int $mid_range;

    /** @var array<int|string,mixed> */
    private array $pageLinks;

    private int $default_ipp;
    private string $url;

    private string $previousLabel = 'Previous';
    private string $nextLabel = 'Next';

    /** @var array<string,mixed> */
    private array $params;

    /** @param array<string,mixed> $params */
    public function __construct(string $url, array $params)
    {
        $this->default_ipp = 10;
        $this->current_page = isset($params['page']) ? (int) $params['page'] : 1;
        $this->mid_range = 7;

        if (isset($params['ipp'])) {
            $this->itemsPerPage = (int) $params['ipp'];
        } else {
            $this->itemsPerPage = $this->default_ipp;
            $params['ipp'] = $this->default_ipp;
        }
        $this->pageLinks = [];
        $this->url = $url;
        $this->params = $params;
    }

    public function setItemsTotal(int $totalItems): self
    {
        $this->items_total = $totalItems;

        return $this;
    }

    public function setMidRange(int $midRange): self
    {
        $this->mid_range = $midRange;

        return $this;
    }

    public function setLabels(string $previousLabel, string $nextLabel): self
    {
        $this->previousLabel = $previousLabel;
        $this->nextLabel = $nextLabel;

        return $this;
    }

    public function paginate(): void
    {
        if ($this->itemsPerPage <= 0) {
            $this->itemsPerPage = $this->default_ipp;
        }
        $this->num_pages = (int) ceil($this->items_total / $this->itemsPerPage);

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

        if ($this->current_page != 1 && $this->items_total >= 10) {
            $this->pageLinks[0] = ['caption' => '« ' . $this->previousLabel, 'link' => $this->url . '?' . $this->makeParams($prev_page), 'isCurrent' => false];
        } else {
            $this->pageLinks[0] = ['caption' => $this->previousLabel, 'link' => '', 'isCurrent' => false];
        }

        for ($i = 1; $i <= $this->num_pages; $i++) {
            if ($i == $this->current_page) {
                $this->pageLinks[] = ['caption' => (string) $i, 'link' => '', 'isCurrent' => true];
            } else {
                $this->pageLinks[] = ['caption' => (string) $i, 'link' => $this->url . '?' . $this->makeParams($i), 'isCurrent' => false];
            }
        }

        if ($this->current_page != $this->num_pages && $this->items_total >= 10) {
            $this->pageLinks[] = ['caption' => $this->nextLabel . ' »', 'link' => $this->url . '?' . $this->makeParams($next_page), 'isCurrent' => false];
        } else {
            $this->pageLinks[] = ['caption' => $this->nextLabel, 'link' => '', 'isCurrent' => false];
        }
    }

    private function processMoreThanTenPages(): void
    {
        $prev_page = $this->current_page - 1;
        $next_page = $this->current_page + 1;

        if ($this->current_page != 1 && $this->items_total >= 10) {
            $this->pageLinks[0] = ['caption' => '« ' . $this->previousLabel, 'link' => $this->url . '?' . $this->makeParams($prev_page), 'isCurrent' => false];
        } else {
            $this->pageLinks[0] = ['caption' => '« ' . $this->previousLabel, 'link' => '', 'isCurrent' => false];
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
        $range = range((int) $start_range, (int) $end_range);

        for ($i = 1; $i <= $this->num_pages; $i++) {
            if ($range[0] > 2 && $i == $range[0]) {
                $this->pageLinks[] = ['caption' => '...', 'link' => '', 'isCurrent' => false];
            }

            if ($i == 1 || $i == $this->num_pages || in_array($i, $range)) {
                if ($i == $this->current_page) {
                    $this->pageLinks[] = ['caption' => (string) $i, 'link' => '', 'isCurrent' => true];
                } else {
                    $this->pageLinks[] = ['caption' => (string) $i, 'link' => $this->url . '?' . $this->makeParams($i), 'isCurrent' => false];
                }
            }

            if ($range[$this->mid_range - 1] < $this->num_pages - 1 && $i == $range[$this->mid_range - 1]) {
                $this->pageLinks[] = ['caption' => '...', 'link' => '', 'isCurrent' => false];
            }
        }

        if ($this->current_page != $this->num_pages && $this->items_total >= 10) {
            $this->pageLinks[] = ['caption' => $this->nextLabel . ' »', 'link' => $this->url . '?' . $this->makeParams($next_page), 'isCurrent' => false];
        } else {
            $this->pageLinks[] = ['caption' => $this->nextLabel . ' »', 'link' => '', 'isCurrent' => false];
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
                continue;
            }
            $_temp_url[] = $key . '=' . $value;
        }
        if (!isset($this->params['page'])) {
            $_temp_url[] = 'page=' . $page;
        }

        return implode('&', $_temp_url);
    }

    /** @return array<string,mixed> */
    public function displayPages(): array
    {
        return $this->pageLinks;
    }
}
