<?php

namespace App\Auth\Oidc;

use RuntimeException;

/**
 * The bearer token is malformed, forged, expired, or meant for someone else.
 */
class InvalidTokenException extends RuntimeException {}
