<?php

namespace Kanboard\Core\Log;

use Stringable;

/**
 * Built-in PHP Logger
 *
 * @package Kanboard\Core\Log
 * @author  Frédéric Guillot
 */
class System extends Base
{
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        error_log('['.$level.'] '.$this->interpolate($message, $context));
    }
}
