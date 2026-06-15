<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an LLM response fails schema validation. Drives the single
 * corrective retry in StructuredLlm (NFR-R2).
 */
class InvalidStructuredOutput extends RuntimeException {}
