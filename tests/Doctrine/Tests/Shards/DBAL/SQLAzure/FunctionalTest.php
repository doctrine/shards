<?php
namespace Doctrine\Tests\Shards\DBAL\SQLAzure;

use Doctrine\DBAL\DriverManager;
use Doctrine\Shards\DBAL\SQLAzure\SQLAzureShardManager;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    private $conn;
    private $sm;

    public function setUp()
    {
        if (!isset($GLOBALS['db_type']) || strpos($GLOBALS['db_type'], "sqlsrv") === false) {
            $this->markTestSkipped('No driver or sqlserver driver specified.');
        }

        $params = array(
            'driver' => $GLOBALS['db_type'],
            'dbname' => $GLOBALS['db_name'],
            'user' => $GLOBALS['db_username'],
            'password' => $GLOBALS['db_password'],
            'host' => $GLOBALS['db_host'],
            'sharding' => array(
                'federationName' => 'Orders_Federation',
                'distributionKey' => 'CustID',
                'filteringEnabled' => false,
            ),
            'driverOptions' => array('MultipleActiveResultSets' => false)
        );
        $this->conn = DriverManager::getConnection($params);
        // assume database is created and schema is:
        // Global products table
        // Customers, Orders, OrderItems federation tables.
        // See http://cloud.dzone.com/articles/using-sql-azure-federations
        $this->sm = new SQLAzureShardManager($this->conn);
    }

    public function testSharding()
    {
        $this->sm->selectShard(0);

        $query = "SELECT * FROM Products";
        $data = $this->conn->fetchAll($query);
        $this->assertTrue(count($data) > 0);

        $query = "SELECT * FROM Customers";
        $data = $this->conn->fetchAll($query);
        $this->assertTrue(count($data) > 0);

        $data = $this->sm->queryAll("SELECT * FROM Customers");
        #$this->assertTrue(count($data) > 0);
    }
}

