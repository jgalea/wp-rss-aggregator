<?php

namespace RebelCode\Wpra\Core\Logger;

use Traversable;

/**
 * Interface for an object that can retrieve logs.
 *
 * @since [*next-version*]
 */
interface LogReaderInterface
{
    /**
     * Retrieves a certain number of logs.
     *
     * @since [*next-version*]
     *
     * @param int|null $num  Optional number of logs to read, or null to retrieve all the logs.
     * @param int      $page Optional page number to retrieve a particular page when $num is not null.
     *
     * @return array|Traversable The list of log entries.
     */
    public function getLogs($num = null, $page = 1);
}