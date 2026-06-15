<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an uploaded resume cannot be parsed to text (e.g. encrypted or
 * empty file). Carries a user-facing message (NFR-R3).
 */
class ResumeParseException extends RuntimeException {}
