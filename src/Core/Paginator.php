<?php
declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public int $totalItems;
    public int $perPage;
    public int $currentPage;
    public int $lastPage;
    public int $offset;

    public function __construct(int $totalItems, int $perPage = 10, int $currentPage = 1)
    {
        $this->totalItems  = max(0, $totalItems);
        $this->perPage     = max(1, $perPage);
        $this->lastPage    = (int)ceil($this->totalItems / $this->perPage);
        if ($this->lastPage < 1) $this->lastPage = 1;
        $this->currentPage = min(max(1, $currentPage), $this->lastPage);
        $this->offset      = ($this->currentPage - 1) * $this->perPage;
    }

    public function links(string $baseUrl = ''): string
    {
        if ($this->lastPage <= 1) return '';

        $query = $_GET;
        $html = '<nav class="pagination" aria-label="Page navigation"><ul class="pagination__list">';

        // Previous button
        if ($this->currentPage > 1) {
            $query['page'] = $this->currentPage - 1;
            $link = $baseUrl . '?' . http_build_query($query);
            $html .= '<li class="pagination__item"><a href="' . e($link) . '" class="pagination__link">&laquo; Prev</a></li>';
        }

        for ($i = 1; $i <= $this->lastPage; $i++) {
            if ($i === $this->currentPage) {
                $html .= '<li class="pagination__item"><span class="pagination__link pagination__link--active" aria-current="page">' . $i . '</span></li>';
            } elseif ($i == 1 || $i == $this->lastPage || abs($i - $this->currentPage) <= 2) {
                $query['page'] = $i;
                $link = $baseUrl . '?' . http_build_query($query);
                $html .= '<li class="pagination__item"><a href="' . e($link) . '" class="pagination__link">' . $i . '</a></li>';
            } elseif ($i == 2 && $this->currentPage > 4) {
                $html .= '<li class="pagination__item"><span class="pagination__ellipsis">&hellip;</span></li>';
            } elseif ($i == $this->lastPage - 1 && $this->currentPage < $this->lastPage - 3) {
                $html .= '<li class="pagination__item"><span class="pagination__ellipsis">&hellip;</span></li>';
            }
        }

        // Next button
        if ($this->currentPage < $this->lastPage) {
            $query['page'] = $this->currentPage + 1;
            $link = $baseUrl . '?' . http_build_query($query);
            $html .= '<li class="pagination__item"><a href="' . e($link) . '" class="pagination__link">Next &raquo;</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }
}
