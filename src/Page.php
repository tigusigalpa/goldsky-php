<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky;

/**
 * Page is a single page of paged list results.
 *
 * @phpstan-import-type PageData from \Tigusigalpa\Goldsky\Page
 */
final class Page
{
    /**
     * @param array<int, array<string, mixed>> $data
     * @param array{next_page_token?: ?string, page_size?: int|string} $pagination
     */
    public function __construct(
        public array $data,
        public array $pagination = [],
    ) {}

    public function hasMore(): bool
    {
        $token = $this->pagination['next_page_token'] ?? null;
        return $token !== null && $token !== '';
    }

    public function nextPageToken(): ?string
    {
        $token = $this->pagination['next_page_token'] ?? null;
        return ($token !== null && $token !== '') ? (string) $token : null;
    }

    public function pageSize(): int
    {
        $ps = $this->pagination['page_size'] ?? 0;
        return (int) $ps;
    }

    /**
     * @param string $body Raw JSON response body.
     */
    public static function fromJSON(string $body): self
    {
        if ($body === '') {
            return new self([]);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new self([]);
        }
        $data = $decoded['data'] ?? [];
        $pagination = $decoded['pagination'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        if (!is_array($pagination)) {
            $pagination = [];
        }
        return new self($data, $pagination);
    }
}
