<?php
/**
 * Forms and caches connection to Elasticsearch as well as client objects
 * that contain connection information like \Elastica\Index and \Elastica\Type.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
abstract class ElasticaConnection {
	/**
	 * Singleton instance of the client
	 * @var \Elastica\Client
	 */
	private static $client = null;

	/**
	 * @return array(string) server ips or hostnames
	 */
	public abstract function getServerList();

	/**
	 * @return string base name for index
	 */
	public abstract function getIndexBaseName();

	/**
	 * How many times can we attempt to connect per host?
	 *
	 * @return int
	 */
	public function getMaxConnectionAttempts() {
		return 1;
	}

	/**
	 * Fetch a connection.
	 * @return \Elastica\Client
	 */
	public static function getClient() {
		if ( self::$client === null ) {
			// Setup the Elastica servers
			$servers = array();
			$me = new static();
			foreach ( $me->getServerList() as $server ) {
				$servers[] = array( 'host' => $server );
			}

			self::$client = new \Elastica\Client( array( 'servers' => $servers ),
				/**
				 * Callback for \Elastica\Client on request failures.
				 * @param \Elastica\Connection $connection The current connection to elasticasearch
				 * @param \Elastica\Exception $e Exception to be thrown if we don't do anything
				 * @param \ElasticaConnection $me Child class of us
				 */
				function( $connection, $e ) use ( $me ) {
					// Keep track of the number of times we've hit a host
					static $connectionAttempts = array();
					$host = $connection->getConfig( 'host' );
					$connectionAttempts[$host] = isset( $connectionAttempts[$host] )
						? $connectionAttempts[$host] + 1 : 1;

					// Check if we've hit the host the max # of times. If not, try again
					if ( $connectionAttempts[$host] < $me->getMaxConnectionAttempts() ) {
						$connection->setEnabled( true );
					}
				}
			);
		}

		return self::$client;
	}

	/**
	 * Fetch the Elastica Index.
	 * @param mixed $type type of index (named type or false to get all)
	 * @param mixed $identifier if specified get the named identified version of the index
	 * @return \Elastica\Index
	 */
	public static function getIndex( $type = false, $identifier = false ) {
		return self::getClient()->getIndex( self::getIndexName( $type, $identifier ) );
	}

	/**
	 * Get the name of the index.
	 * @param mixed $type type of index (named type or false to get all)
	 * @param mixed $identifier if specified get the named identifier of the index
	 * @return string name of index for $type and $identifier
	 */
	public static function getIndexName( $type = false, $identifier = false ) {
		$name = wfWikiId();
		if ( $type ) {
			$name .= '_' . $type;
		}
		if ( $identifier ) {
			$name .= '_' . $identifier;
		}
		return $name;
	}

	public static function destroySingleton() {
		self::$client = null;
		ElasticaHttpTransportCloser::destroySingleton();
	}
}

class ElasticaHttpTransportCloser extends \Elastica\Transport\Http {
	public static function destroySingleton() {
		\Elastica\Transport\Http::$_curlConnection = null;
	}
}
