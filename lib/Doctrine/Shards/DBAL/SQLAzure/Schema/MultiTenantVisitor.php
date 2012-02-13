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

/**
 * Converts a single tenant schema into a multi-tenant schema for SQL Azure
 * Federations under the following assumptions:
 *
 * - Every table is part of the multi-tenant application, only explicitly
 *   excluded tables are non-federated.
 * - Every Primary key of a federated table is extended by another column
 *   'tenant_id' with a default value of the SQLAzure function
 *   `federation_filtering_value('tenant_id')`.
 * - You have to work with `filtering=On` when using federations.
 * - Primary keys are either using globally unique ids (GUID, Table Generator)
 *   or you explicitly add the tenent_id in every UPDATE or DELETE statement
 *   (otherwise they will affect the same-id rows from other tenents as well).
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class MultiTenantVisitor
{
    /**
     * @var array
     */
    private $excludedTables = array();

    /**
     * @var string
     */
    private $tenantColumnName;

    /**
     * @var string
     */
    private $tenantColumnType;

    public function __construct(array $excludedTables = array(), $tenantColumnName = 'tenant_id', $tenantColumnType = 'integer')
    {
        $this->excludedTables = $excludedTables;
        $this->tenantColumnName = $tenantColumnName;
        $this->tenantColumnType = $tenantColumnType;
    }
}

