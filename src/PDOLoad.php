<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Gonzie <hello@gonz.ie>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gonzie\PDOLoad;

/**
 * [PDOLoad description]
 */
class PDOLoad
{
    /**
     * [DEFAULT_DRIVER description]
     * @var string
     */
    const DEFAULT_DRIVER = 'mysql';

    /**
     * [DEFAULT_PORT description]
     * @var [type]
     */
    const DEFAULT_PORT = null;

    /**
     * [DEFAULT_CHARSET description]
     * @var [type]
     */
    const DEFAULT_CHARSET = 'utf8mb4';

    /**
     * [DEFAULT_ALLOWED description]
     * @var array
     */
    const DEFAULT_ALLOWED = ['user', 'password', 'dbname', 'driver', 'port', 'charset'];

    /**
     * [protected description]
     * @var [type]
     */
    protected $overwrite_allowed = false;

    /**
     * [protected description]
     * @var [type]
     */
    protected $options;

    /**
     * [protected description]
     * @var [type]
     */
    protected $reader;

    /**
     * [protected description]
     * @var [type]
     */
    protected $writer;


    /**
     * [protected description]
     * @var [type]
     */
    protected $pdo_reader;

    /**
     * [protected description]
     * @var [type]
     */
    protected $pdo_writer;


    /**
     * [protected description]
     * @var [type]
     */
    protected $transaction = false;

    /**
     * Type of load balancer. Allows round-robin, random, fixed or null
     * @var string
     */
    protected $balancer;

    /**
     * [protected description]
     * @var [type]
     */
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
            if (empty($options)) {
                throw new PDOLoadException('Invalid connection settings.');
            }

            $this->pdo_writer =  new \PDO(
                $options,
                $user,
                $password
            );

            $this->pdo_reader = $this->pdo_writer;

            return true;
        }

        if (!isset($options['writer']) || (isset($options['writer']) && count($options['writer']) === 0)) {
            throw new PDOLoadException('At least one writer connection must be defined.');
        }


        if (isset($options['overwrite_allowed'])) {
            $this->overwrite_allowed = $options['overwrite_allowed'];
        }

        foreach ($options as $key => $val) {
            if (in_array($key, self::DEFAULT_ALLOWED) || $this->overwrite_allowed) {
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
     * [getSettings description]
     * @return [type] [description]
     */
    public function getSettings()
    {
        return $this->options;
    }


    /**
     * [getActiveConnections description]
     * @return [type] [description]
     */
    public function getActiveConnections()
    {
        return ['writer' => $this->pdo_writer, 'reader' => $this->pdo_reader];
    }


    /**
     * [addWriter description]
     * @param [type] $options [description]
     */
    public function addWriter($options)
    {
        $this->options['writer'][] = $this->populate($options);
        $this->pdo_writer = $this->initPDO(end($this->options['writer']));
    }


    /**
     * [getWriter description]
     * @return [type] [description]
     */
    public function getWriter()
    {
        return $this->pdo_writer;
    }


    /**
     * [addReader description]
     * @param [type] $options [description]
     */
    public function addReader($options)
    {
        $this->options['reader'][] = $this->populate($options);
        $this->pdo_reader = $this->initPDO(end($this->options['reader']));
    }


    /**
     * [getReader description]
     * @return [type] [description]
     */
    public function getReader()
    {
        return $this->pdo_reader;
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

        return $this->pdo_writer->getAttribute($attribute);
    }


    /**
     * [getAttributeList description]
     * @return [type] [description]
     */
    public function getAttributeList()
    {
        return $this->attributes;
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

        $stmt = $this->pdo_current->prepare($statement, $driverOptions);

        if ($this->pdo_current === $this->pdo_writer) {
            $stmt->connectionType = 'writer';
        } else {
            $stmt->connectionType = 'reader';
        }

        return $stmt;
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
            $options['user'],
            $options['password']
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

            $this->{'pdo_'.$type} = $this->initPDO($this->populate($settings));
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
            $this->{'pdo_' . $type} = $this->initPDO($this->populate($settings));
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
            'host' => $options['host'],
            'dbname' => $options['dbname'] ?? $this->default_dbname ?? '',
            'user' => $options['user'] ?? $this->default_user ?? '',
            'password' => $options['password'] ?? $this->default_password ?? '',
            'port' => $options['port'] ?? $this->default_port ??  self::DEFAULT_PORT,
            'driver' => $options['driver'] ?? $this->default_driver ?? self::DEFAULT_DRIVER,
            'charset' => $options['charset'] ?? $this->default_charset ?? self::DEFAULT_CHARSET,
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
