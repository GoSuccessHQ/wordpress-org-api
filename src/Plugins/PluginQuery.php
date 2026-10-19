<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Plugins;

use GoSuccess\WordpressOrg\Directory\FieldSelection;

/**
 * Arguments of a plugin directory search (`action=query_plugins`); unset ones are not sent.
 */
final readonly class PluginQuery
{
    /**
     * @param list<string> $tags    plugins with any of these tags, sent as one comma separated `tag`
     * @param string|null  $browse  e.g. `popular`, `new`, `updated`, `top-rated`
     * @param int          $page    first page, starting at 1
     * @param int|null     $perPage results per page; the directory applies its own default and limit
     */
    public function __construct(
        public ?string $search = null,
        public array $tags = [],
        public ?string $author = null,
        public ?string $browse = null,
        public ?string $user = null,
        public int $page = 1,
        public ?int $perPage = null,
        public ?FieldSelection $fields = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toRequest(int $page): array
    {
        return [
            'search' => $this->search,
            'tag' => $this->tags === [] ? null : implode(',', $this->tags),
            'author' => $this->author,
            'browse' => $this->browse,
            'user' => $this->user,
            'page' => $page,
            'per_page' => $this->perPage,
            'fields' => $this->fields?->toRequest(),
        ];
    }
}
