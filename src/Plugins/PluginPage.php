<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Plugins;

/**
 * One page of a plugin directory search.
 */
final readonly class PluginPage
{
    /**
     * @param int          $page    the requested page when unreadable
     * @param int          $pages   0 when unreadable
     * @param int          $results total number of matches, 0 when unreadable
     * @param list<Plugin> $plugins
     */
    public function __construct(
        public int $page,
        public int $pages,
        public int $results,
        public array $plugins,
    ) {}
}
