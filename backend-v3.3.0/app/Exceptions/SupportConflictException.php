<?php

namespace App\Exceptions;

use RuntimeException;

// Conflict exception for support chat business validation failures (e.g. concurrent active sessions)
class SupportConflictException extends RuntimeException {}
