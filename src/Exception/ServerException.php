<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Exception;

/**
 * wordpress.org failed to process the request (HTTP 5xx).
 */
final class ServerException extends ApiException {}
