<?php

namespace JDZ\Database\Tests\Fixtures;

use JDZ\Database\Pdo\PdoSqliteDatabase;

/**
 * Records the options its constructor receives, so a test sees the config that
 * Database::createFromDsn() builds (`new static($config)`) without reflection
 * and without a connection.
 */
class ConfigCapturingDatabase extends PdoSqliteDatabase
{
    public array $received = [];

    public function __construct(array $options)
    {
        $this->received = $options;

        parent::__construct($options);
    }
}
