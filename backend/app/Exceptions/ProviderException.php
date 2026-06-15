<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an LLM/embedding provider call fails (network, timeout, 4xx/5xx
 * after retries). Surfaced to the user as a readable job-failure message.
 */
class ProviderException extends RuntimeException {}
