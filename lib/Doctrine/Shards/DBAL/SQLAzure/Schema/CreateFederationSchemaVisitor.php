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

namespace Doctrine\Shards\DBAL\SQLAzure\Schema;

use Doctrine\DBAL\Schema\Visitor\Visitor,
    Doctrine\DBAL\Schema\Table,
    Doctrine\DBAL\Schema\Schema,
    Doctrine\DBAL\Schema\Column,
    Doctrine\DBAL\Schema\ForeignKeyConstraint,
    Doctrine\DBAL\Schema\Constraint,
    Doctrine\DBAL\Schema\Sequence,
    Doctrine\DBAL\Schema\Index,
    Doctrine\DBAL\Types\Type,
    Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * SQL Azure specific schema creator that will add the CREATE Federations
 * statements.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class CreateFederationSchemaVisitor implements Visitor
{
    /**
     * @var array
     */
    private $createTableQueries;

    /**
     * @var array
     */
    private $createSequenceQueries;

    /**
     * @var array
     */
    private $createFkConstraintQueries;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $platform;

    /**
     * Details about all federations that need to be created.
     *
     * @var array
     */
    private $federations = array();

    /**
     * @param AbstractPlatform $platform
     */
    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
        $this->resetQueries();
    }

    /**
     * @param Table $table
     */
    public function acceptTable(Table $table)
    {
        $federationName = $this->getFederationName($table);

        $this->createTableQueries[$federationName] = array_merge(
            $this->createTableQueries[$federationName],
            $this->platform->getCreateTableSQL($table)
        );
    }

    private function getFederationName(Table $table)
    {
        $federationName = $table->getOption('azure.federationName');
        if ( !$federationName) {
            return '_root';
        }

        if ( ! $table->getOption('azure.federatedOnDistributionName')) {
            throw new \RuntimeException("Missing 'azure.federatedOnDistributionName' option on table that is part of a federation.");
        }

        if ( ! $table->getOption('azure.federatedOnColumnName')) {
            throw new \RuntimeException("Missing 'azure.federatedOnColumnName' option on table that is part of a federation.");
        }

        if ( ! isset($this->createTableQueries[$federationName])) {
            $this->createTableQueries[$federationName]          = array();
            $this->createFkConstraintQueries[$federationName]   = array();

            $this->federations[$federationName] = array(
                'name' => $federationName,
                'type' => $table->getColumn($table->getOption('azure.federatedOnColumnName'))->getType(),
                'distributionName' => $table->getOption('azure.federatedOnDistributionName'),
            );
        }


        return $federationName;
    }

    /**
     * @param Table $localTable
     * @param ForeignKeyConstraint $fkConstraint
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint)
    {
        $federationName = $this->getFederationName($localTable);

        $this->createFkConstraintQueries[$federationName] = array_merge(
            $this->createFkConstraintQueries[$federationName],
            (array) $this->platform->getCreateForeignKeySQL(
                $fkConstraint, $localTable
            )
        );
    }

    /**
     * @param Schema $schema
     */
    public function acceptSchema(Schema $schema)
    {
    }

    /**
     * @param Column $column
     */
    public function acceptColumn(Table $table, Column $column)
    {
    }

    /**
     * @param Table $table
     * @param Index $index
     */
    public function acceptIndex(Table $table, Index $index)
    {
    }

    /**
     * @param Sequence $sequence
     */
    public function acceptSequence(Sequence $sequence)
    {
    }

    /**
     * @return array
     */
    public function resetQueries()
    {
        $this->createTableQueries = array('_root' => array());
        $this->createSequenceQueries = array('_root' => array());
        $this->createFkConstraintQueries = array('_root' => array());
    }

    /**
     * Get all queries collected so far.
     *
     * @return array
     */
    public function getQueries()
    {
        $sql = array();
        foreach ($this->federations as $federation) {
            $sql[] = "CREATE FEDERATION " . $federation['name'] . " (" .
                   $federation['distributionName'] . " " .
                   $federation['type']->getSQLDeclaration(array(), $this->platform) .
                   ")";
        }

        $sqls = array($this->createTableQueries, $this->createFkConstraintQueries);
        foreach ($sqls as $allSql) {
            foreach ($allSql as $name => $federationSql) {
                if ($federationSql) {
                    $sql = array_merge($sql, $this->getSwitchFederationSQL($name));
                    $sql = array_merge($sql, $federationSql);
                }
            }
        }

        return $sql;
    }

    /**
     * Switch Federation SQL
     *
     * @param string $name
     * @return array
     */
    private function getSwitchFederationSQL($name)
    {
        if ($name === '_root') {
            return array("GO", "USE FEDERATION ROOT WITH RESET", "GO");
        }

        switch ($this->federations[$name]['type']->getName()) {
            case Type::GUID:
                $value = '00000000-0000-0000-0000-000000000000';
                break;
            case Type::INTEGER:
            case Type::SMALLINT:
            case Type::BIGINT:
                $value = '0';
                break;
            default:
                $value = '';
        }

        return array(
            "GO",
            "USE FEDERATION " . $name . " (" . $this->federations[$name]['distributionName'] . " = '" . $value . "') WITH RESET, FILTERING=OFF",
            "GO"
        );
    }
}

