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
    private $defaultFederationName;

    /**
     * @var bool
     */
    private $defaultFilteringEnabled;

    /**
     * @var string
     */
    private $defaultDistributionKey;

    /**
     * @var Connection
     */
    private $conn;

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
        $this->defaultFederationNameName = $params['federationName'];
        $this->defaultDistributionKey = $params['distributionKey'];
        $this->defaultFilteringEnabled = (isset($params['filteringEnabled'])) ? (bool)$params['filteringEnabled'] : false;
    }

    /**
     * @override
     * {@inheritDoc}
     */
    public function useShard($shardIdentifier, array $options = array());
    {
        if (!$shardIdentifier) {
            $sql = "USE FEDERATION ROOT WITH RESET";
            $params = array();
        } else {
            $federationName = $this->getFederationName($options);
            $distributionKey = $this->getDistributionKey($options);
            $filteringFlag = $this->getFilteringFlag($options);

            $this->assertValidKeyword($federationName);
            $this->assertValidKeyword($distributionKey);

            $sql = sprintf(
                "USE FEDERATION %s (%s = ?) WITH RESET, FILTERING = %s",
                $federationName, $distributionKey, $filteringFlag
            );
            $params = array($shardIdentifier);
        }
        $this->conn->executeQuery($sql, $params);
    }

    public function useShards(array $shardIdentifiers, array $options = array());
    {
        throw ShardingException::notImplemented();
    }

    public function useAllShards(array $options = array())
    {
        throw ShardingException::notImplemented();
    }

    public function getShards();
    {

    }

    private function getFederationName(array $options)
    {
        if (!isset($options['federationName'])) {
            return $this->defaultFederationName;
        }
        return $options['federationName'];
    }

    private function getDistributionKey(array $options)
    {
        if (!isset($options['distributionKey'])) {

        }
        return $options['distributionKey'];
    }
}

