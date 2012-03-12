# Azure Federations

Implementing Federations inside a new Doctrine Sharding Extension. Some extensions to the DBAL and ORM core have to be done to get this working.

1. DBAL (Database Abstraction Layer)

* Add support for Database Schema Operations
    * CREATE FEDERATION
    * CREATE TABLE ... FEDERATED ON
    * Add support to create a multi-tenent schema from any given schema
* Add API to pick a shard based on distribution key and atomic value
* Add API to ask about federations, federation members and so on.
* Add Sharding Abstraction
    * If a shard is picked via distribution key and atomic value fire queries against this only
    * If no shard is explicitly picked, execute fan out query against all shards and aggregate results

2. ORM (Object-Relational Mapper)

* Federation Key has to be part of the clustered index of the table
    * Test with a pure Multi-Tenent App with Filtering = ON (TaskList)
    * Test with sharded app (Weather)
* Implement API that allow users to choose the shard target for any given query

## Implementation Details

SQL Azure requires one and exactly one clustered index. It makes no difference if the primary key
or any other key is the clustered index. Sharding requires an external ID generation (no auto-increment)
such as GUIDs. GUIDs have negative properties with regard to clustered index performance, so that
typically you would add a "created" timestamp for example that holds the clustered index instead
of making the GUID a clustered index.

## Example API:

    @@@ php
    <?php
    use Doctrine\DBAL\DriverManager;

    $dbParams = array(
        'dbname' => 'dbname.database.windows.net',
        'driverClass' => 'Doctrine\Shards\DBAL\SQLAzure\AzureConnection',
        'sharding' => array(
            'federationName' => 'Orders_Federation',
            'distributionKey' => 'CustID',
            'filteringEnabled' => false,
        ),
        // ...
    );

    $conn = DriverManager::getConnection($dbParams);
    $shardManager = $conn->getShardManager();

    // Example 1: query against root database
    $sql = "SELECT * FROM Products";
    $rows = $conn->executeQuery($sql);

    // Example 2: query against all shards, fan out query
    $shardManager->useAllShards();
    $sql = "SELECT * FROM Customers";
    $rows = $conn->executeQuery($sql);

    // Example 3:  query against the selected shard with CustomerId = 100
    $aCustomerID = 100;
    $shardManager->useShard($aCustomerID); // Using Default federationName and distributionKey
    // Query: "USE FEDERATION Orders_Federation (CustID = $aCustomerID) WITH RESET, FILTERING OFF;"

    $sql = "SELECT * FROM Customers";
    $rows = $conn->executeQuery($sql);

    // Example 4: SQL Azure specific sharding configuration
    $shardManager->useShard($aCustomerID, array('filtering' => true, 'federationName' => 'OtherFederationName', 'distributionKey' => 'OtherId'));
    // Query: "USE FEDERATION OtherFederationName (OtherId = $aCustomerID) WITH RESET, FILTERING ON;"

    // Example 5: Reset API to root database again
    $shardManager->useShard( null );

    // Example 6: Query all shards that contain the given values (Fan Out)
    $shardManager->useShards( array($value1, $value2) );

## Shard Management API

    @@@ php
    <?php

    use Doctrine\DBAL\DriverManager;

    $dbParams = array(...);
    $conn = DriverManager::getConnection($dbParams);
    $shardManager = $conn->getShardManager();

    $shards = $shardManager->getShards();
    foreach ($shards as $shard) {
        echo sprintf('ID: %d distributed by %s on range from %d to %d', 
            $shard->getId(),
            $shard->getDistributionKey(),
            $shard->getRangeLow(),
            $shard->getRangeHigh()
        );
    }

## Shard DDL API

Doctrine comes with an API to create Database schema from an object-oriented representation. This would be extended to be azure compatible through existing hooks:

    @@@ php
    <?php
    use Doctrine\DBAL\Schema\Schema;

    $schema = new Schema();
    $customers = $schema->createTable("Customers");
    $customers->addColumn('CustomerId', 'integer');
    $customers->addColumn('CustomerName', 'string');
    $customers->setPrimaryKey(array('CustomerId'));
    $customers->addOption('azure.federatedOnDistributionName', 'CustId');
    $customers->addOtpion('azure.federatedOnColumnName', 'CustomerId');
    $customers->addOption('azure.federationName', 'Orders_Federation');

    $orders = $schema->createTable('Orders');
    $orders->addColumn('OrderId', 'integer');
    $orders->addColumn('CustomerId', 'integer');
    $orders->addColumn('OrderDate', 'datetime');
    $orders->setPrimaryKey(array('OrderId', 'CustomerId'));
    $orders->addOption('azure.federatedOnDistributionName', 'CustId');
    $orders->addOtpion('azure.federatedOnColumnName', 'CustomerId');
    $orders->addOption('azure.federationName', 'Orders_Federation');

    $orderItems = $schema->createTable('OrderItems');
    $orderItems->addColumn('OrderId', 'integer');
    $orderItems->addColumn('CustomerId', 'integer');
    $orderItems->addColumn('ProductId', 'integer');
    $orderItems->addColumn('Quantity', 'integer');
    $orderItems->setPrimaryKey(array('OrderId', 'CustomerId', 'ProductId'));
    $orderItems->addOption('azure.federatedOnDistributionName', 'CustId');
    $orderItems->addOtpion('azure.federatedOnColumnName', 'CustomerId');
    $orderItems->addOption('azure.federationName', 'Orders_Federation');

    $sqls = $schema->toSQL($conn->getDatabasePlatform());
    foreach ($sqls as $sql) {
        $conn->exec($sql);
    }

This should also be doable by a federation schema extension:

    @@@ php
    <?php
    use Doctrine\Shards\DBAL\Azure\FederationSchemaVisitor;
    use Doctrine\DBAL\Schema\Schema;

    $schema = new Schema();
    // build schema

    $visitor = new FederationSchemaVisitor('Orders_Federation', 'CustId', 'integer', array(
        'Customers' => 'CustomerId',
        'Orders' => 'CustomerId',
        'OrderItems' => 'CustomerId',
    ));

    $schema->visit($visitor);

Following tasks have to be done by this visitor:

1. Add Column to tables if not already existing
2. Append Column to clustered index of the table if not yet part of it.

## ID Generation

With sharding all the ids have to be generated for global uniqueness. There are three strategies for this.

1. Use GUIDs as described here http://blogs.msdn.com/b/cbiyikoglu/archive/2011/06/20/id-generation-in-federations-identity-sequences-and-guids-uniqueidentifier.aspx
2. Having a central table that is accessed with a second connection to generate sequential ids
3. Using natural keys from the domain.

The second approach has the benefit of having numerical primary keys, however also a central failure location. The third strategy can seldom be used, because the domains dont allow this. Identity columns cannot be used at all.

    @@@ php
    <?php
    use Doctrine\DBAL\DriverManager;
    use Doctrine\DBAL\Id\TableHiLoIdGenerator;

    $dbParams = array(
        'dbname' => 'dbname.database.windows.net',
        // ...
    );
    $conn = DriverManager::getConnection($dbParams);

    $idGenerator = new TableHiLoIdGenerator($conn, 'id_table_name', $multiplicator = 1);
    // only once, create this table
    $idGenerator->createTable();

    $nextId = $idGenerator->generateId('for_table_name');
    $nextOtherId = $idGenerator->generateId('for_other_table');

The connection for the table generator has to be a different one than the one used for the main app to avoid transaction clashes.
