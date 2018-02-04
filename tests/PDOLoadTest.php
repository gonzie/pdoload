<?php

use Gonzie\PDOLoad\PDOLoad;
use Gonzie\PDOLoad\PDOLoadException;

use PHPUnit\Framework\TestCase;

final class PDOLoadTest extends TestCase
{

    public function testCannotBeCreatedWithNoWriters()
    {
        $this->expectException(PDOLoadException::class);

        $dbh = new PDOLoad([]);
    }

    public function testCannotBeCreatedWithNoSettings()
    {
        $this->expectException(PDOLoadException::class);

        $dbh = new PDOLoad();
    }

    public function testCannotBeCreatedWithEmptyString()
    {
        $this->expectException(PDOLoadException::class);

        $dbh = new PDOLoad('');
    }

    public function testSuccessfulConnections()
    {
        $dbh =$this->getDefaultConnection();

        $this->assertEquals(
            ['writer' => $dbh->getWriter(), 'reader' => $dbh->getReader()],
            $dbh->getActiveConnections()
        );
    }

    public function testInTransaction()
    {
        $dbh = $this->getDefaultConnection();
        $dbh->beginTransaction();
        $this->assertEquals(true, $dbh->inTransaction());
        $dbh->rollBack();
    }

    public function testNotInTransaction()
    {
        $dbh = $this->getDefaultConnection();
        $this->assertEquals(false, $dbh->inTransaction());
    }

    public function testValidStatementObjectFromPrepare()
    {
        $dbh = $this->getDefaultConnection();

        $stmt = $dbh->prepare('CREATE TABLE dog (name VARCHAR(20))');

        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    public function testCorrectAttributeList()
    {
        $dbh = $this->getDefaultConnection();

        $data = [
            ['attribute' => PDO::ATTR_ERRMODE, 'value' => PDO::ERRMODE_EXCEPTION],
            ['attribute' => PDO::ATTR_DEFAULT_FETCH_MODE, 'value' => PDO::FETCH_ASSOC]
        ];

        $this->assertEquals($data, $dbh->getAttributeList());
    }

    public function testInsertSuccessfull()
    {
        $dbh = $this->getDefaultConnection();
        $dbh->beginTransaction();
        
        $stmt = $dbh->prepare('CREATE TABLE dog (name VARCHAR(20), owner VARCHAR(20), species VARCHAR(20), sex CHAR(1), birth DATE, death DATE)');
        $stmt->execute();

        $stmt = $dbh->prepare('INSERT INTO dog (name) VALUES (?)');
        $stmt->execute(['test']);

        $this->assertEquals("1", $dbh->lastInsertId());

        $dbh->rollBack();
    }

    private function getDefaultConnection()
    {
        $dbh = new PDOLoad($this->getSettings());
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $dbh;
    }

    private function getSettings()
    {
        return [
            'driver' => 'sqlite',
            'reader' => [
                [
                    'host' => ':memory',
                ]
            ],
            'writer' => [
                [
                    'host' => ':memory',
                ]
            ],
            'balancer' => 'round-robin',
        ];
    }
}
