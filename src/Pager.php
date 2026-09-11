<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky;

use Tigusigalpa\Goldsky\Exceptions\TransportException;

/**
 * Pager iterates paged list endpoints, fetching one page at a time. It never
 * accumulates unbounded results; callers iterate page by page. Completion is
 * inferred from next_page_token alone — a page can hold fewer than page_size
 * items and still have a next page.
 */
final class Pager
{
    private string $token = '';
    private bool $first = true;
    private bool $done = false;

    /**
     * @param callable(array<string, mixed>): Page $fetch A function that takes
     *        query parameters (including page_token) and returns a Page.
     * @param int $pageSize
     * @param string $initialToken
     * @param array<string, mixed> $extraQuery Extra query params applied to every page.
     */
    public function __construct(
        private $fetch,
        private int $pageSize = 0,
        string $initialToken = '',
        private array $extraQuery = [],
    ) {
        $this->token = $initialToken;
    }

    public function nextPage(): Page
    {
        if ($this->done) {
            return new self([]);
        }
        $query = $this->extraQuery;
        if ($this->pageSize > 0) {
            $query['page_size'] = $this->pageSize;
        }
        if ($this->token !== '') {
            $query['page_token'] = $this->token;
        }
        $page = ($this->fetch)($query);
        $this->token = $page->nextPageToken() ?? '';
        if ($this->token === '') {
            $this->done = true;
        }
        $this->first = false;
        return $page;
    }

    public function isDone(): bool
    {
        return $this->done;
    }
}
