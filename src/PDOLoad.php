<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Gonzie <hello@gonz.ie>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace gonzie\PDOLoad;

/**
 * [PDOLoad description]
 */
class PDOLoad
{
    const DEFAULT_DRIVER = 'mysql';
    const DEFAULT_PORT = null;
    const DEFAULT_ALLOWED = ['user', 'password', 'dbname', 'driver', 'port'];

    protected $options;

    protected $reader;
    protected $writer;

    protected $pdo_reader;
    protected $pdo_writer;

    protected $transaction = false;

    /**
     * Type of load balancer. Allows round-robin, random, fixed or null
     * @var string
     */
    protected $balancer;

    protected $attributes;


    /**
     * [__construct description]
     * @param array  $options  [description]
     * @param [type] $user     [description]
     * @param [type] $password [description]
     */
    public function __construct($options = [], $user = null, $password = null)
    {
        $this->options = $options;

        if ('string' === gettype($options)) {
            $this->pdo_writer =  new \PDO(
                $options,
                $user,
                $password
            );

            $this->pdo_reader = $this->pdo_writer;

            return true;
        }

        foreach ($options as $key => $val) {
            if (in_array($key, self::DEFAULT_ALLOWED)) {
                $this->{'default_' . $key} = $val;
            }
        }

        if (isset($options['balancer'])) {
            $this->balancer = $options['balancer'];
        }

        if (isset($options['writer'])) {
            $this->writer = $options['writer'];
            shuffle($this->writer);
        }

        if (isset($options['reader'])) {
            $this->reader = $options['reader'];
            shuffle($this->reader);
        }

        $this->pickOne('reader');
        $this->pickOne('writer');
    }


    /**
     * [addWrite description]
     * @param [type] $options [description]
     */
    public function addWrite($options)
    {
        $this->pdo_writer = $this->initPDO(populate($options));
    }


    /**
     * [addRead description]
     * @param [type] $options [description]
     */
    public function addRead($options)
    {
        $this->pdo_reader = $this->initPDO(populate($this->$options));
    }


    /**
     * [setAttribute description]
     * @param [type] $attribute [description]
     * @param [type] $value     [description]
     *
     * @return [type]
     */
    public function setAttribute($attribute, $value)
    {
        if (null !== $this->pdo_reader) {
            $this->pdo_reader->setAttribute($attribute, $value);
        }

        $this->validate('writer');

        $this->attributes[] = ['attribute' => $attribute, 'value' => $value];

        return $this->pdo_writer->setAttribute($attribute, $value);
    }


    /**
     * [getAttribute description]
     * @param  [type] $attribute [description]
     *
     * @return [type]            [description]
     */
    public function getAttribute($attribute)
    {
        $this->validate('writer');

        return $this->pdo_writer->getAttribut($attribute);
    }



    /**
     * [quote description]
     * @param  [type] $string        [description]
     * @param  [type] $parameterType [description]
     *
     * @return [type]                [description]
     */
    public function quote($string, $parameterType = PDO::PARAM_STR)
    {
        $this->validate('writer');

        return $this->pdo_writer->quote($string, $parameterType);
    }


    /**
     * [prepare description]
     * @param  [type] $statement     [description]
     * @param  array  $driverOptions [description]
     *
     * @return [type]                [description]
     */
    public function prepare($statement, $driverOptions = [])
    {
        $this->pdo_current = $this->getConnection($statement);

        $this->validate('current');

        return $this->pdo_current->prepare($statement, $driverOptions);
    }


    /**
     * [exec description]
     * @param  [type] $statement [description]
     *
     * @return [type]            [description]
     */
    public function exec($statement)
    {
        $this->validate('writer');

        return $this->pdo_writer->exec($statement);
    }


    /**
     * [query description]
     * @return [type] [description]
     */
    public function query()
    {
        // To be done at a later date
    }


    /**
     * [lastInsertId description]
     * @param  [type] $name [description]
     *
     * @return [type]       [description]
     */
    public function lastInsertId($name = null)
    {
        $this->validate('writer');

        return $this->pdo_writer->lastInsertId($name);
    }


    /**
     * [errorInfo description]
     * @return [type] [description]
     */
    public function errorInfo()
    {
        $this->validate('current');

        return $this->pdo_current->errorInfo();
    }


    /**
     * [errorCode description]
     * @return [type] [description]
     */
    public function errorCode()
    {
        $this->validate('current');

        return $this->pdo_current->errorCode();
    }


    /**
     * [inTransaction description]
     * @return [type] [description]
     */
    public function inTransaction()
    {
        return $this->transaction;
    }


    /**
     * [beginTransaction description]
     * @return [type] [description]
     */
    public function beginTransaction()
    {
        $this->validate('writer');

        $this->transaction = true;
        $this->pdo_writer->beginTransaction();
    }


    /**
     * [rollBack description]
     * @return [type] [description]
     */
    public function rollBack()
    {
        $this->validate('writer');

        $this->transaction = false;
        $this->pdo_writer->rollBack();
    }


    /**
     * [commit description]
     * @return [type] [description]
     */
    public function commit()
    {
        $this->validate('writer');

        $this->transaction = false;
        $this->pdo_writer->commit();
    }


    /**
     * [getConnection description]
     *
     * @param  [type] $statement [description]
     *
     * @return [type]            [description]
     */
    private function getConnection($statement)
    {
        if ($this->transaction) {
            return $this->pdo_writer;
        }

        $matches = [];

        $regEx = '/^([( ])*(SELECT|EXPLAIN SELECT)/i';
        preg_match($regEx, $statement, $matches, PREG_OFFSET_CAPTURE);

        if (count($matches) > 0 && null !== $this->pdo_reader) {
            return $this->pdo_reader;
        }

        return $this->pdo_writer;
    }


    /**
     * [initPDO description]
     *
     * @param  [type] $options [description]
     *
     * @return [type]          [description]
     */
    private function initPDO($options)
    {
        if ('object' === gettype($options) && is_a($options, 'PDO')) {
            return $options;
        }

        $dbh =  new \PDO(
            sprintf(
                "%s:host=%s;dbname=%s%s",
                $options['driver'],
                $options['host'],
                $options['dbname'],
                ($options['port'] ? ";port=" . $options['port'] : '')
            ),
            $user,
            $password
        );

        return $dbh;
    }


    /**
     * [pickOne description]
     * @param  [type] $type [description]
     *
     * @return [type]       [description]
     */
    private function pickOne($type)
    {
        if (0 === count($this->$type)) {
            if ('reader' === $type) {
                $this->pdo_reader = $this->pdo_writer;
            }

            return false;
        }

        // If pdo_$type is null then it's first connection
        if (null === $this->{'pdo_'.$type}) {
            $settings = $this->$type[0];

            $this->{'pdo_'.$type} = $this->initPDO(populate($settings));
        } else {
            if (count($this->$type) < 2 || null === $this->balancer) {
                return;
            }

            switch ($this->balancer) {
                case 'round-robin':
                    $index = 0;
                    end($this->$type);
                    break;
                case 'fixed':
                    return;
                case 'random':
                default:
                    $index = array_rand($this->$type);
                    break;
            }

            $settings = $this->$type[$index];

            $this->{'pdo_' . $type} = null;
            $this->{'pdo_' . $type} = $this->initPDO(populate($settings));
        }
    }


    /**
     * [populateDefaults description]
     * @param  [type] $options [description]
     *
     * @return [type]          [description]
     */
    private function populate($options)
    {
        return [
            $options['host'],
            $options['dbname'] ?? $this->default_dbname,
            $o['user'] ?? $this->default_user,
            $o['password'] ?? $this->default_password,
            $o['port'] ?? $this->default_port ??  self::DEFAULT_PORT,
            $o['driver'] ?? $this->default_driver ?? self::DEFAULT_DRIVER,
        ];
    }


    /**
     * [validate description]
     * @param  [type] $type [description]
     *
     * @return [type]       [description]
     */
    private function validate($type)
    {
        if (null === $this->{'pdo_' . $type}) {
            throw new PDOLoadException(sprintf('Invalid %s connection.', $type));
        }
    }
}
