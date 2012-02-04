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

## Technical Requirements for Database Schemas

Tables that are shareded between different databases ALL require the distribution key as one of their columns. This affects your code. If you have a Blog <-> BlogPost <-> PostComments entity setup sharded by `blog_id` then even the PostComment table needs this column, even if an "unsharded", normalized DB-Schema does not need this information.

## Implementation Details

Assumptions:

* For querying you either want to query ALL or just exactly one shard.
* Non-shareded data is replicated between all shards. They redundantly keep the information available. This is necessary so join queries on shards to reference data work.
* If you retrieve an object A from a shard, then all references and collections of this object reside on the same shard.

### SQL Azure Federations

SQL Azure is a special case, points 1, 2, 3 and 8 are handled on the database level. This makes it a perfect test-implementation for just the subset of features in points 4-7.
