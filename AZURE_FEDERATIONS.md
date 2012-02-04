# Azure Federations

Implementing Federations inside a new Doctrine Sharding Extension

1. DBAL (Database Abstraction Layer)

* Add support for Database Schema Operations
    * CREATE FEDERATION
    * CREATE TABLE ... FEDERATED ON
* Add API to pick a shard based on distribution key and atomic value
* Add API to ask about federations, federation members and so on.
* Add Sharding Abstraction
    * If a shard is picked via distribution key and atomic value fire queries against this only
    * If no shard is explicitly picked, execute fan out query against all shards and aggregate results
* Support for exactly one federation first

## Example API:

    @@@ php
    <?php
    use Doctrine\DBAL\DriverManager;

    $dbParams = array(
        'dbname' => 'dbname.database.windows.net',
        'driverClass' => 'Doctrine\Shards\DBAL\SQLAzure\AzureConnection',
        'sharding' => array(
            'queryResolver' => 'Doctrine\Shards\DBAL\SQLAzure\AzureQueryResolver',
            'shardResolver' => 'Doctrine\Shards\DBAL\SQLAzure\AzureShardResolver',
            'federationName' => 'Orders_Federation',
            'distributionKey' => 'CustID',
        ),
        // ...
    );

    $conn = DriverManager::getConnection($dbParams);

    // query against all shards
    $sql = "SELECT * FROM Customers";
    $rows = $conn->executeQuery($sql);

    $aCustomerID = 100;
    $conn->setShard($aCustomerID); // Using Default federationName and distributionKey
    // Query: "USE FEDERATION Orders_Federation (CustID = $aCustomerID) WITH RESET, FILTERING OFF;"

    // query against the selected shard
    $sql = "SELECT * FROM Customers";
    $rows = $conn->executeQuery($sql);

    // SQL Azure specific shard configuration
    $conn->setShard($aCustomerID, array('filtering' => true, 'federationName' => 'OtherFederationName', 'distributionKey' => 'OtherId'));
    // Query: "USE FEDERATION OtherFederationName (OtherId = $aCustomerID) WITH RESET, FILTERING ON;"

## ID Generation

With sharding all the ids have to be generated for global uniqueness. There are three strategies for this.

1. Use GUIDs as described here http://blogs.msdn.com/b/cbiyikoglu/archive/2011/06/20/id-generation-in-federations-identity-sequences-and-guids-uniqueidentifier.aspx
2. Having a central table that is accessed with a second connection to generate sequential ids
3. Using natural keys from the domain.

The second approach has the benefit of having numerical primary keys, however also a central failure location. The third strategy can seldom be used, because the domains don't allow this. Identity columns cannot be used at all.

    @@@ php
    <?php
    use Doctrine\DBAL\DriverManager;

    $dbParams = array(
        'dbname' => 'dbname.database.windows.net',
        // ...
    );
    $conn = DriverManager::getConnection($dbParams);

    $idGenerator = new TableHiLoIdGenerator($conn, 'id_table_name', $multiplicator = 1);
    // only once, create this table
    $idGenerator->createTable();

    $nextId = $idGenerator->generateId('for_table_name');

