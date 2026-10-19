<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Directory;

/**
 * Classifies one entry of a directory answer (`plugins/info/1.2`, `themes/info/1.2`).
 *
 * @internal
 */
final class Entry
{
    private function __construct() {}

    /**
     * @return array{LookupStatus, ?ClosureDetails, ?string} status, closure details, raw error text
     */
    public static function classify(mixed $entry): array
    {
        // A non-empty list is not an object; `{}` decodes to `[]` and stays an (empty) object.
        if (!\is_array($entry) || ($entry !== [] && array_is_list($entry))) {
            return [LookupStatus::Unknown, null, null];
        }

        /** @var array<string, mixed> $entry */
        $error = isset($entry['error']) && \is_string($entry['error']) ? $entry['error'] : null;

        if (($entry['closed'] ?? null) === true || $error === 'closed') {
            return [LookupStatus::Closed, ClosureDetails::fromArray($entry), $error];
        }

        if (\array_key_exists('error', $entry)) {
            return [LookupStatus::NotFound, null, $error];
        }

        return [LookupStatus::Found, null, null];
    }

    /**
     * Whether a slug can be sent in a comma separated list without ambiguity.
     */
    public static function isSendable(string $slug): bool
    {
        // At least one letter or digit, so `.` and `..` never become path segments.
        return preg_match('/^[A-Za-z0-9._-]+$/D', $slug) === 1
            && preg_match('/[A-Za-z0-9]/', $slug) === 1;
    }
}
