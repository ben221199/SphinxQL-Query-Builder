<?php
namespace Foolz\SphinxQL\Drivers\Pdo;

use Foolz\SphinxQL\Drivers\ConnectionBase;
use Foolz\SphinxQL\Drivers\MultiResultSet;
use Foolz\SphinxQL\Drivers\ResultSet;
use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use mysqli;
use PDO;
use PDOException;
use RuntimeException;

class Connection extends ConnectionBase
{

    /**
     * @return PDO
     * @throws ConnectionException
     */
    public function getConnection(): PDO
    {
        $connection = parent::getConnection();

        if ($connection instanceof mysqli) {
            throw new RuntimeException('Connection type mismatch');
        }

        return $connection;
    }


    /**
     * @inheritdoc
     */
    public function query($query)
    {
        $this->ensureConnection();

        $statement = $this->getConnection()->prepare($query);

        try {
            $statement->execute();
        } catch (PDOException $exception) {
            throw new DatabaseException($exception->getMessage() . ' [' . $query . ']', $exception->getCode(), $exception);
        }

        return new ResultSet(new ResultSetAdapter($statement));
    }

    /**
     * @inheritdoc
     */
    public function connect(): bool
    {
        $params = $this->getParams();

        $dsn = 'mysql:';
        if (isset($params['host']) && $params['host'] != '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }
        if (isset($params['port'])) {
            $dsn .= 'port=' . $params['port'] . ';';
        }
        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }

        if (isset($params['socket']) && $params['socket'] != '') {
            $dsn .= 'unix_socket=' . $params['socket'] . ';';
        }

        try {
            $con = new PDO($dsn);
        } catch (PDOException $exception) {
            throw new ConnectionException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $this->connection = $con;
        $this->getConnection()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return true;
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function ping()
    {
        $this->ensureConnection();

        return $this->getConnection() !== null;
    }

    /**
     * @inheritdoc
     */
    public function multiQuery(array $queue)
    {
        $this->ensureConnection();

        if (count($queue) === 0) {
            throw new SphinxQLException('The Queue is empty.');
        }

        try {
            $statement = $this->getConnection()->query(implode(';', $queue));
        } catch (PDOException $exception) {
            throw new DatabaseException($exception->getMessage() .' [ '.implode(';', $queue).']', $exception->getCode(), $exception);
        }

        return new MultiResultSet(new MultiResultSetAdapter($statement));
    }

    /**
     * @inheritdoc
     */
    public function escape($value)
    {
        $this->ensureConnection();

        return $this->getConnection()->quote($value);
    }
}
