<?php
namespace Doctrine\Tests\Shards\DBAL\SQLAzure;

use Doctrine\DBAL\Platforms\SQLAzurePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Shards\DBAL\SQLAzure\Schema\CreateFederationSchemaVisitor;

class CreateFederationSchemaVisitorTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateFederations()
    {
        $platform = new SQLAzurePlatform();
        $visitor = new CreateFederationSchemaVisitor($platform);

        $schema = new Schema();
        $tasks = $schema->createTable('tasks');
        $tasks->addOption('azure.federationName', 'UserFed');
        $tasks->addOption('azure.federatedOnDistributionName', 'user_id');
        $tasks->addOption('azure.federatedOnColumnName', 'user_id');
        $tasks->addColumn('id', 'guid');
        $tasks->addColumn('user_id', 'guid');
        $tasks->setPrimaryKey(array('id'));
        $schema->visit($visitor);

        $sql = $visitor->getQueries();
        $this->assertEquals(array(
            'CREATE FEDERATION UserFed (user_id UNIQUEIDENTIFIER)',
            'GO',
            "USE FEDERATION UserFed (user_id = '') WITH RESET, FILTERING=OFF",
            'GO',
            'CREATE TABLE tasks (id UNIQUEIDENTIFIER NOT NULL, user_id UNIQUEIDENTIFIER NOT NULL, PRIMARY KEY (id)) FEDERATED ON (user_id = user_id)',
        ), $sql);
    }
}

