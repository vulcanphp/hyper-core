<?php

namespace hyper\utils;

class paginator
{
    private int $pages = 0;
    private int $page = 0;
    private int $offset = 0;
    private array $data = [];

    public function __construct(
        public int $total,
        public int $limit = 10,
        public string $keyword = 'page',
        public bool $lazy = true
    ) {
        $this->resetPaginator();
    }

    public function resetPaginator(): self
    {
        $this->pages  = (int)ceil($this->getTotal() / $this->getLimit());
        $this->page   = min($this->getPages(), $this->getKeywordValue());
        $this->offset = (int)ceil($this->getLimit() * ($this->getPage() - 1));

        return $this;
    }

    public function getPages(): int
    {
        return max($this->pages, 0);
    }

    public function getPage(): int
    {
        return max($this->page, 0);
    }

    public function getOffset(): int
    {
        return max($this->offset, 0);
    }

    public function getTotal(): int
    {
        return max($this->total, 0);
    }

    public function getLimit(): int
    {
        return max($this->limit, 0);
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function getKeywordValue(): int
    {
        return filter_input(
            INPUT_GET,
            $this->getKeyword(),
            FILTER_VALIDATE_INT,
            ['options' => ['default' => 1, 'min_range' => 1]]
        ) ?: 1;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): array
    {
        if ($this->lazy) {
            return array_slice($this->data, $this->offset, $this->limit);
        }

        return $this->data;
    }

    public function hasData(): bool
    {
        return !empty($this->getData());
    }

    public function hasLinks(): bool
    {
        return $this->getPages() > 1;
    }

    public function getLinks(int $links = 2, array $classes = [], array $entity = []): string
    {
        $output = [];
        $start = max(1, $this->getPage() - $links);
        $end = min($this->getPages(), $this->getPage() + $links);

        $output[] = sprintf('<ul class="%s">', $classes['ul'] ?? 'pagination');

        if ($this->getPage() > 1) {
            $output[] = sprintf(
                '<li class="%s"><a class="%s" href="%s">%s</a></li>',
                $classes['li'] ?? 'page-item',
                $classes['a'] ?? 'page-link',
                $this->getAnchor($this->getPage() - 1),
                $entity['prev'] ?? __('Previous')
            );
        }

        if ($start > 1) {
            $output[] = sprintf(
                '<li class="%s"><a class="%s" href="%s">%s</a></li>',
                $classes['li'] ?? 'page-item',
                $classes['a'] ?? 'page-link',
                $this->getAnchor(1),
                1
            );
            $output[] = sprintf(
                '<li class="%s disabled"><span class="%s">...</span></li>',
                $classes['li'] ?? 'page-item',
                $classes['a'] ?? 'page-link'
            );
        }

        for ($i = $start; $i <= $end; $i++) {
            $output[] = sprintf(
                '<li class="%s %s"><a class="%s %s" href="%s">%s</a></li>',
                $classes['li'] ?? 'page-item',
                $this->getPage() === $i ? ($classes['li.current'] ?? 'active') : '',
                $classes['a'] ?? 'page-link',
                $this->getPage() === $i ? ($classes['a.current'] ?? '') : '',
                $this->getAnchor($i),
                $i
            );
        }

        if ($end < $this->getPages()) {
            $output[] = sprintf(
                '<li class="%s disabled"><span class="%s">...</span></li>',
                $classes['li'] ?? 'page-item',
                $classes['a'] ?? 'page-link'
            );

            $output[] = sprintf(
                '<li class="%s"><a class="%s" href="%s">%s</a></li>',
                $classes['li'] ?? 'page-item',
                $classes['a'] ?? 'page-link',
                $this->getAnchor($this->getPages()),
                $this->getPages()
            );
        }

        if ($this->getPage() < $this->getPages()) {
            $output[] = sprintf(
                '<li class="%s"><a class="%s" href="%s">%s</a></li>',
                $classes['li'] ?? 'page-item',
                $classes['a'] ?? 'page-link',
                $this->getAnchor($this->getPage() + 1),
                $entity['next'] ?? __('Next')
            );
        }

        $output[] = '</ul>';

        return implode('', $output);
    }

    private function getAnchor(int $page): string
    {
        $get = $_GET;
        $get[$this->getKeyword()] = $page;
        return '?' . http_build_query($get);
    }
}
