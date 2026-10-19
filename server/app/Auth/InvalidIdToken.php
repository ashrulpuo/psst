<?php

namespace App\Auth;

use RuntimeException;

/**
 * The reason is for logs only; clients always get the same generic response.
 */
class InvalidIdToken extends RuntimeException {}
