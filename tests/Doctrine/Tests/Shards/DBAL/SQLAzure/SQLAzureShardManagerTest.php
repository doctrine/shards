<?php

namespace Doctrine\Tests\Shards\DBAL\SQLAzure;

use Doctrine\Shards\DBAL\SQLAzure\SQLAzureShardManager;

class SQLAzureShardManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testNoFederationName()
    {
        $this->setExpectedException('Doctrine\Shards\DBAL\ShardingException', 'SQLAzure requires a federation name to be set during sharding configuration.');

        $conn = $this->createConnection(array('distributionKey' => 'abc'));
        $sm = new SQLAzureShardManager($conn);
    }

    public function testNoDistributionKey()
    {
        $this->setExpectedException('Doctrine\Shards\DBAL\ShardingException', 'SQLAzure requires a distribution key to be set during sharding configuration.');

        $conn = $this->createConnection(array('federationName' => 'abc'));
        $sm = new SQLAzureShardManager($conn);
    }

    public function testGetDefaultDistributionValue()
    {
        $conn = $this->createConnection(array('federationName' => 'abc', 'distributionKey' => 'foo'));

        $sm = new SQLAzureShardManager($conn);
        $this->assertNull($sm->getCurrentDistributionValue());
    }

    public function testSelectGlobalTransactionActive()
    {
        $conn = $this->createConnection(array('federationName' => 'abc', 'distributionKey' => 'foo'));
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(true));

        $this->setExpectedException('Doctrine\Shards\DBAL\ShardingException', 'Cannot switch shard during an active transaction.');

        $sm = new SQLAzureShardManager($conn);
        $sm->selectGlobal();
    }

    public function testSelectGlobal()
    {
        $conn = $this->createConnection(array('federationName' => 'abc', 'distributionKey' => 'foo'));
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(false));
        $conn->expects($this->at(2))->method('executeQuery')->with($this->equalTo('USE FEDERATION ROOT WITH RESET'));

        $sm = new SQLAzureShardManager($conn);
        $sm->selectGlobal();
    }

    public function testSelectShard()
    {
        $conn = $this->createConnection(array('federationName' => 'abc', 'distributionKey' => 'foo'));
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(true));

        $this->setExpectedException('Doctrine\Shards\DBAL\ShardingException', 'Cannot switch shard during an active transaction.');

        $sm = new SQLAzureShardManager($conn);
        $sm->selectShard(1234);

        $this->assertEquals(1234, $sm->getCurrentDistributionValue());
    }

    public function testSelectShardNoDistriubtionValue()
    {
        $conn = $this->createConnection(array('federationName' => 'abc', 'distributionKey' => 'foo'));
        $conn->expects($this->at(1))->method('isTransactionActive')->will($this->returnValue(false));

        $this->setExpectedException('Doctrine\Shards\DBAL\ShardingException', 'You have to specify a string or integer as shard distribution value.');

        $sm = new SQLAzureShardManager($conn);
        $sm->selectShard(null);
    }

    private function createConnection(array $params)
    {
        $conn = $this->getMock('Doctrine\DBAL\Connection', array('getParams', 'executeQuery', 'isTransactionActive'), array(), '', false);
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue($params));
        return $conn;
    }
}

