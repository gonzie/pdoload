<?php

namespace gonzie\PDOLoad;

Class PDOLoad
{
    const DEFAULT_DRIVER = 'mysql';
    const DEFAULT_PORT = null;
    const DEFAULT_ALLOWED = ['user', 'password', 'dbname', 'driver', 'port'];

    protected $options;

    protected $reads;
    protected $writes;

    protected $pdo_read;
    protected $pdo_write;

    protected $transaction = false;


    function __construct($options = [], $user = null, $password = null)
    {
        $this->options = $options;

        if (gettype($options) === 'string') {
            $this->pdo_write =  new PDO(
                $options,
                $user,
                $password
            );

            $this->pdo_read = $this->pdo_write;

            return true;
        }

        foreach ($options as $key => $val) {

            if (in_array($key, self::DEFAULT_ALLOWED)) {
                $this->{'default_'.$key} = $val;
            }
        }


        if (isset($options['writes'])) {
            $w = $options['writes'][array_rand($options['writes'])];

            $this->pdo_write = $this->initPDO(
                $w['host'],
                $this->default_dbname ?? $w['dbname'],
                $this->default_user ?? $w['user'],
                $this->default_password ?? $w['password'],
                $this->default_port ?? $w['port'] ?? self::DEFAULT_PORT,
                $this->default_driver ?? $w['driver'] ?? self::DEFAULT_DRIVER
            );
        }


        if (isset($options['reads'])) {
            $r = $options['reads'][array_rand($options['reads'])];

            $this->pdo_read = $this->initPDO(
                $r['host'],
                $this->default_dbname ?? $r['dbname'],
                $this->default_user ?? $r['user'],
                $this->default_password ?? $r['password'],
                $this->default_port ?? $r['port'] ?? self::DEFAULT_PORT,
                $this->default_driver ?? $r['driver'] ?? self::DEFAULT_DRIVER
            );
        } else {
            $this->pdo_read = $this->pdo_write;
        }
    }


    private function initPDO($host, $db, $user, $password, $port = null, $driver = 'mysql')
    {
        $dbh =  new PDO(
            $driver . ":host=" . $host . ";dbname=" . $db . ($port ? ';port=' . $port : ''),
            $user,
            $password
        );

        return $dbh;
    }


    public function addWrite($host, $db, $user, $password, $port = null, $driver = 'mysql')
    {
        $this->pdo_write = $this->initPDO($host, $db, $user, $password, $port, $driver);
    }


    public function addRead($host, $db, $user, $password, $port = null, $driver = 'mysql')
    {
        $this->pdo_read = $this->initPDO($host, $db, $user, $password, $port, $driver);
    }


    public function setAttribute($attribute, $value)
    {
        if ($this->pdo_read !== null)
            $this->pdo_read->setAttribute($attribute, $value);

        return $this->pdo_write->setAttribute($attribute, $value);
    }


    public function getAttribute($attribute)
    {
        return $this->pdo_write->getAttribut($attribute);
    }


    public function quote($string, $parameter_type = PDO::PARAM_STR)
    {
        return $this->pdo_write->quote($string, $parameter_type);
    }


    public function prepare($statement, $driver_options = [])
    {
        $this->pdo_current = $this->getConnection($statement);

        return $this->pdo_current->prepare($statement, $driver_options);
    }


    public function exec($statement)
    {
        return $this->pdo_write->exec($statement);
    }


    public function query()
    {
        // To be done at a later date
    }


    public function lastInsertId($name = null)
    {
        return $this->pdo_write->lastInsertId($name);
    }


    public function errorInfo()
    {
        return $this->pdo_current->errorInfo();
    }


    public function errorCode()
    {
        return $this->pdo_current->errorCode();
    }


    private function getConnection($statement)
    {
        if ($this->transaction)
            return $this->pdo_write;

        $matches = [];

        $regEx = '/^([( ])*(SELECT|EXPLAIN SELECT)/i';
        preg_match($regEx, $statement, $matches, PREG_OFFSET_CAPTURE);

        if (count($matches) > 0 && $this->pdo_read != null)
            return $this->pdo_read;

        return $this->pdo_write;
    }


    ///
    /// Transactions
    ///

    public function inTransaction()
    {
        return $this->transaction;
    }


    public function beginTransaction()
    {
        $this->transaction = true;
        $this->pdo_write->beginTransaction();
    }


    public function rollBack()
    {
        $this->transaction = false;
        $this->pdo_write->rollBack();
    }


    public function commit()
    {
        $this->transaction = false;
        $this->pdo_write->commit();
    }
}
