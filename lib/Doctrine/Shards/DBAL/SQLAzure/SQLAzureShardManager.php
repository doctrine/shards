<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\Shards\DBAL\SQLAzure;

use Doctrine\Shards\DBAL\ShardManager;
use Doctrine\Shards\DBAL\ShardingException;
use Doctrine\DBAL\Connection;

/**
 * Sharding using the SQL Azure Federations support.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class SQLAzureShardManager implements ShardManager
{
    /**
     * @var string
     */
    private $federationName;

    /**
     * @var bool
     */
    private $filteringEnabled;

    /**
     * @var string
     */
    private $distributionKey;

    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var string
     */
    private $currentDistributionValue;

    /**
     * @param Connection $conn
     */
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
        $params = $conn->getParams();

        if ( ! isset($params['federationName'])) {
            throw ShardingException::missingDefaultFederationName();
        }

        if ( ! isset($params['distributionKey'])) {
            throw ShardingException::missingDefaultDistributionKey();
        }

        $this->federationNameName = $params['federationName'];
        $this->distributionKey = $params['distributionKey'];
        $this->filteringEnabled = (isset($params['filteringEnabled'])) ? (bool)$params['filteringEnabled'] : false;
    }

    /**
     * @override
     * {@inheritDoc}
     */
    public function selectGlobal()
    {
        if ($this->conn->isTransactionActive()) {
            throw ShardingException::activeTransaction();
        }

        $sql = "USE FEDERATION ROOT WITH RESET";
        $this->conn->executeQuery($sql);
        $this->currentDistributionValue = null;
    }

    /**
     * @override
     * {@inheritDoc}
     */
    public function selectShard($distributionValue)
    {
        if ($this->conn->isTransactionActive()) {
            throw ShardingException::activeTransaction();
        }

        if ($distributionValue === null || is_bool($distributionValue) || !is_scalar($distributionValue)) {
            throw ShardingException::noShardDistributionValue();
        }

        $platform = $this->conn->getDatabasePlatform();
        $sql = sprintf(
            "USE FEDERATION %s (%s = ?) WITH RESET, FILTERING = %s",
            $platform->quoteIdentifier($this->federationName),
            $platform->quoteIdentifier($this->distributionKey),
            ($this->filteringFlag ? 'ON' : 'OFF')
        );
        $this->conn->executeQuery($sql, array($distributionValue));
        $this->currentDistributionValue = $distributionValue;
    }

    /**
     * @override
     * {@inheritDoc}
     */
    public function getCurrentDistributionValue()
    {
        return $this->currentDistributionValue;
    }

    /**
     * @override
     * {@inheritDoc}
     */
    public function getShards()
    {
        $sql = "SELECT d.member_id as name, d.Distribution_name as distributionKey, " .
               " d.Range_low as rangeLow, d.Range_high as rangeHigh " .
               "FROM sys.federation_member_distributions d " .
               "INNER JOIN sys.federations f ON f.federation_id = d.federation_id " .
               "WHERE f.name = ?";
        return $this->conn->fetchAll($sql, array($this->federationName));
    }

     /**
      * @override
      * {@inheritDoc}
      */
    public function queryAll($sql, array $params, array $types)
    {
        $oldDistribution = $this->getCurrentDistributionValue();
        $shards = $this->getShards();

        $result = array();
        foreach ($shards as $shard) {
            $this->selectShard($shard['rangeLow']);
            foreach ($this->conn->fetchAll($sql, $params, $types) as $row) {
                $result[] = $row;
            }
        }

        if ($oldDistribution === null) {
            $this->selectGlobal();
        } else {
            $this->selectShard($oldDistribution);
        }

        return $result;
    }
}

