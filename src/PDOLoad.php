<?php

namespace gonzie\PDOLoad;

class PDOLoad
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

        foreach ($options as $key => $val)
            if (in_array($key, self::DEFAULT_ALLOWED))
                $this->{'default_' . $key} = $val;


        if (isset($options['writes']))
            $this->pickOne($options['writes'], 'write');


        if (isset($options['reads']))
            $this->pickOne($options['reads'], 'read');
        else
            $this->pdo_read = $this->pdo_write;

    }


    private function initPDO($host, $db, $user, $password, $port = null, $driver = 'mysql')
    {
        if (gettype($host) == 'object' && is_a($host, 'PDO'))
            return $host;

        $dbh =  new PDO(
            $driver . ":host=" . $host . ";dbname=" . $db . ($port ? ';port=' . $port : ''),
            $user,
            $password
        );

        return $dbh;
    }

    private function pickOne($options, $type)
    {
        $o = $options[$type][array_rand($options[$type])];

        $this->{'pdo_'.$type} = $this->initPDO(
            $o['host'],
            $this->default_dbname ?? $o['dbname'],
            $this->default_user ?? $o['user'],
            $this->default_password ?? $o['password'],
            $this->default_port ?? $o['port'] ?? self::DEFAULT_PORT,
            $this->default_driver ?? $o['driver'] ?? self::DEFAULT_DRIVER
        );
    }

    public function addWrite($host, $db = null, $user = null, $password = null, $port = null, $driver = 'mysql')
    {
        $this->pdo_write = $this->initPDO($host, $db, $user, $password, $port, $driver);
    }


    public function addRead($host, $db = null, $user = null, $password = null, $port = null, $driver = 'mysql')
    {
        $this->pdo_read = $this->initPDO($host, $db, $user, $password, $port, $driver);
    }


    public function setAttribute($attribute, $value)
    {
        if ($this->pdo_read != null)
            $this->pdo_read->setAttribute($attribute, $value);

        $this->validate('write');

        return $this->pdo_write->setAttribute($attribute, $value);
    }


    public function getAttribute($attribute)
    {
        $this->validate('write');

        return $this->pdo_write->getAttribut($attribute);
    }


    public function quote($string, $parameter_type = PDO::PARAM_STR)
    {
        $this->validate('write');

        return $this->pdo_write->quote($string, $parameter_type);
    }


    public function prepare($statement, $driver_options = [])
    {
        $this->pdo_current = $this->getConnection($statement);

        $this->validate('current');

        return $this->pdo_current->prepare($statement, $driver_options);
    }


    public function exec($statement)
    {
        $this->validate('write');

        return $this->pdo_write->exec($statement);
    }


    public function query()
    {
        // To be done at a later date
    }


    public function lastInsertId($name = null)
    {
        $this->validate('write');

        return $this->pdo_write->lastInsertId($name);
    }


    public function errorInfo()
    {
        $this->validate('current');

        return $this->pdo_current->errorInfo();
    }


    public function errorCode()
    {
        $this->validate('current');

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
        $this->validate('write');

        $this->transaction = true;
        $this->pdo_write->beginTransaction();
    }


    public function rollBack()
    {
        $this->validate('write');

        $this->transaction = false;
        $this->pdo_write->rollBack();
    }


    public function commit()
    {
        $this->validate('write');

        $this->transaction = false;
        $this->pdo_write->commit();
    }

    private function validate($type)
    {
        if($this->{'pdo_' . $type} == null)
            throw new PDOLoadException('Connection of type ' . $type . ' not present.');
    }
}
