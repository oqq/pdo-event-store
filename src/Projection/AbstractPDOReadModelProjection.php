<?php
/**
 * This file is part of the prooph/pdo-event-store.
 * (c) 2016-2016 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2016 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStore\PDO\Projection;

use PDO;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\PDO\Exception\RuntimeException;
use Prooph\EventStore\Projection\AbstractReadModelProjection;
use Prooph\EventStore\Projection\ReadModel;

abstract class AbstractPDOReadModelProjection extends AbstractReadModelProjection
{
    use PDOQueryTrait;

    /**
     * @var string
     */
    protected $eventStreamsTable;

    /**
     * @var string
     */
    protected $projectionsTable;

    /**
     * @var int lock timeout in milliseconds
     */
    protected $lockTimeoutMs;

    public function __construct(
        EventStore $eventStore,
        PDO $connection,
        string $name,
        ReadModel $readModel,
        string $eventStreamsTable,
        string $projectionsTable,
        int $lockTimeoutMs,
        int $cacheSize,
        int $persistBlockSize
    ) {
        parent::__construct($eventStore, $name, $readModel, $cacheSize, $persistBlockSize);

        $this->connection = $connection;
        $this->eventStreamsTable = $eventStreamsTable;
        $this->projectionsTable = $projectionsTable;
        $this->lockTimeoutMs = $lockTimeoutMs;
    }

    abstract protected function createProjection(): void;

    /**
     * @throws RuntimeException
     */
    abstract protected function acquireLock(): void;

    abstract protected function releaseLock(): void;

    public function run(bool $keepRunning = true): void
    {
        $this->createProjection();
        $this->acquireLock();

        try {
            parent::run($keepRunning);
        } finally {
            $this->releaseLock();
        }
    }

    protected function load(): void
    {
        $sql = <<<EOT
SELECT position, state FROM $this->projectionsTable WHERE name = '$this->name' ORDER BY no DESC LIMIT 1;
EOT;
        $statement = $this->connection->prepare($sql);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_OBJ);

        $this->position->merge(json_decode($result->position, true));
        $state = json_decode($result->state, true);

        if (! empty($state)) {
            $this->state = $state;
        }
    }
}
