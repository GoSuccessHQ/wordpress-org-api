<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Directory;

/**
 * Outcome of looking up one plugin or theme in the directory.
 */
enum LookupStatus
{
    /** The entry exists and is available. */
    case Found;

    /** The entry exists but was closed. */
    case Closed;

    /** wordpress.org answered that it does not know the slug. */
    case NotFound;

    /** There is no usable answer for the slug (missing or malformed entry, slug not sent). */
    case Unknown;
}
