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

namespace Doctrine\Shards\DBAL;

use Doctrine\DBAL\Connection;

/**
 * Sharding Manager gives access to APIs to implementing sharding on top of
 * Doctrine\DBAL\Connection instances.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface ShardManager
{
    /**
     * SELECT queries after this statement will be issued against all shards.
     *
     * Results are appended to each other in the order of the shards. No
     * processing is done with regard to aggregates/distinct or other types of
     * queries that are impossible to generically distribute accross databases.
     *
     * @param array $options
     * @return void
     */
    function useAllShards(array $options = array());

    /**
     * SELECT queries after this statement will be issued against the selected
     * shard.
     *
     * You can reset the sharding to its default value by calling
     * <code>useShard(null);</code>
     *
     * @param string $shardIdentifier
     * @param array $options
     * @return void
     */
    function useShard($shardIdentifier, array $options = array());

    /**
     * SELECT queries after this statement are issued against the set of
     * selected shards.
     *
     * @param array $shardIdentifiers
     * @param array $options
     * @return void
     */
    function useShards(array $shardIdentifiers, array $options = array());

    /**
     * Return an array of all the currently active shards with some details
     * depending on the sharding implementation.
     *
     * @return Shard[]
     */
    function getShards();
}

