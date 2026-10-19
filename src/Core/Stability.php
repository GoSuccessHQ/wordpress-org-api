<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Core;

/**
 * How wordpress.org rates a WordPress release (`core/stable-check`).
 */
enum Stability: string
{
    case Latest = 'latest';
    case Outdated = 'outdated';
    case Insecure = 'insecure';
}
