# Doctrine Shards

Doctrine Extension to support horizontal sharding in the Doctrine ORM.

## Idea

Implement sharding inside Doctrine at a level that is almost invisible to the developer.

Problems to tackle:

1. Where to send INSERT statements?
2. How to generate primary keys?
3. How to pick shards for update, delete statements?
4. How to pick shards for select operations?
5. How to merge select queries that span multiple shards?
6. How to handle/prevent multi-shard queries that cannot be merged (GROUP BY)?
7. How to handle non-sharded data? (static metadata tables for example)
8. How to handle multiple connections?
9. Implementation on the DBAL or ORM level?

## Technical Requirements for Database Schemas

Sharded tables require the sharding-distribution key as one of their columns. This will affect your code compared to a normalized db-schema. If you have a Blog <-> BlogPost <-> PostComments entity setup sharded by `blog_id` then even the PostComment table needs this column, even if an "unsharded", normalized DB-Schema does not need this information.

## Implementation Details

Assumptions:

* For querying you either want to query ALL or just exactly one shard.
* IDs for ALL sharded tables are unique across all shards.
* Non-shareded data is replicated between all shards. They redundantly keep the information available. This is necessary so join queries on shards to reference data work.
* If you retrieve an object A from a shard, then all references and collections of this object reside on the same shard.
* The database schema on all shards is the same (or compatible)

Instead of `Doctrine\DBAL\Connection` and the respective statement we need a sharding aware connection that can distribute queries.

### SQL Azure Federations

SQL Azure is a special case, points 1, 2, 3 and 8 are partly handled on the database level. This makes it a perfect test-implementation for just the subset of features in points 4-7. However there need to be a way to configure SchemaTool to generate the correct Schema on SQL Azure.

* SELECT Operations: The most simple assumption is to always query all shards unless the user specifies otherwise explicitly.
* Queries can be merged in PHP code, this obviously does not work for DISTINCT, GROUP BY and ORDER BY queries.

### Generic Sharding

More features are necessary to implement sharding on the PHP level, independent from database support:

1. Configuration of multiple connections, one connection = one shard.
2. Primary Key Generation mechanisms (UUID, central table, sequence emulation)

## Primary Use-Cases

1. Multi-Tenent Applications

These are easier to support as you have some value to determine the shard id for the whole request very early on.
Here also queries can always be limited to a single shard.

2. Scale-Out by some attribute (Round-Robin?)

This strategy requires to access multiple shards in a single request based on the data accessed.

## API

    @@@ php
    <?php

    namespace Doctrine\Shards\DBAL;

    /**
     * Extended functionality on the connection for sharding.
     */
    interface ShardingConnection
    {
        function getShardingManager();
    }

    interface ShardingManager
    {
        function useShards(array $values);
        function useShard($value);
        function usetAllShards();
        function getShards();
        function getQueryResolver();
        function getShardResolver();
    }

    interface QueryResolver
    {
        /**
         * Returns the shard ids to query on
         *
         * Warning: If none are returned no query is executed and the result is empty.
         * 
         * @param array $shardIds
         * @param string $sqlQuery
         * @return array 
         */
        function resolve(array $shardIds, $sqlQuery);
    }

    interface ShardResolver
    {
        /**
         * Resolve the shard to save a new database table entry on. 
         * 
         * @param array $shardIds
         * @param string $tableName
         * @param array $data
         * @return int
         */
        function resolve(array $shardIds, $tableName, array $data);
    }

    $dbParams = array(
        'dbname' => 'dbname.database.windows.net',
        'driverClass' => 'Doctrine\Shards\DBAL\SQLAzure\AzureConnection',
        'queryResolver' => 'Doctrine\Shards\DBAL\SQLAzure\AzureQueryResolver',
        'shardResolver' => 'Doctrine\Shards\DBAL\SQLAzure\AzureShardResolver',
        // ...
    );

    $conn = DriverManager::getConnection($dbParams);

