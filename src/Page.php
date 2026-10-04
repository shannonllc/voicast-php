<?php

declare(strict_types=1);

namespace Voicast;

/**
 * One page of a paged list (`calls->list()`).
 *
 * `data` and `hasMore` are this page. Iterating the page with `foreach` walks every item across pages, newest first,
 * fetching the next page when needed.
 *
 * @template T of array<string, mixed>
 * @implements \IteratorAggregate<int, T>
 */
final class Page implements \IteratorAggregate
{
    /** @var list<T> */
    public readonly array $data;
    /** True when there are more items after this page. */
    public readonly bool $hasMore;

    /**
     * @param array{data?: array<T>|null, has_more?: bool|null} $body
     * @param \Closure(int): array{data?: array<T>|null, has_more?: bool|null} $fetch Fetches the page before a cursor.
     * @param \Closure(T): int $cursorOf The cursor for the page after an item (the last item of a page).
     */
    public function __construct(array $body, private readonly \Closure $fetch, private readonly \Closure $cursorOf)
    {
        $this->data = array_values($body['data'] ?? []);
        $this->hasMore = (bool) ($body['has_more'] ?? false);
    }

    public function hasNextPage(): bool
    {
        // 続きがあると言いながら空のページなら止める（同じページを読み続けない）
        return $this->hasMore && $this->data !== [];
    }

    /** The cursor for the next page (`before`), or null on the last page. */
    public function nextCursor(): ?int
    {
        return $this->hasNextPage() ? ($this->cursorOf)($this->data[count($this->data) - 1]) : null;
    }

    /** @return Page<T>|null The next page, or null on the last page. */
    public function nextPage(): ?Page
    {
        $cursor = $this->nextCursor();
        if ($cursor === null) {
            return null;
        }
        return new Page(($this->fetch)($cursor), $this->fetch, $this->cursorOf);
    }

    /** @return \Generator<int, Page<T>> */
    public function pages(): \Generator
    {
        $page = $this;
        while ($page !== null) {
            yield $page;
            $page = $page->nextPage();
        }
    }

    /** @return \Generator<int, T> */
    public function getIterator(): \Generator
    {
        foreach ($this->pages() as $page) {
            foreach ($page->data as $item) {
                yield $item;
            }
        }
    }
}
