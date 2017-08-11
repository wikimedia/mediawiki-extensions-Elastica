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
	 * @var \Elastica\Client
	 */
	protected $client;

	/**
	 * @return array(string) server ips or hostnames
	 */
	abstract public function getServerList();

	/**
	 * How many times can we attempt to connect per host?
	 *
	 * @return int
	 */
	public function getMaxConnectionAttempts() {
		return 1;
	}

	/**
	 * Set the client side timeout to be used for the rest of this process.
	 * @param int $timeout timeout in seconds
	 */
	public function setTimeout( $timeout ) {
		$client = $this->getClient();
		// Set the timeout for new connections
		$client->setConfigValue( 'timeout', $timeout );
		foreach ( $client->getConnections() as $connection ) {
			$connection->setTimeout( $timeout );
		}
	}

	/**
	 * Set the client side connect timeout to be used for the rest of this process.
	 * @param int $timeout timeout in seconds
	 */
	public function setConnectTimeout( $timeout ) {
		$client = $this->getClient();
		// Set the timeout for new connections
		$client->setConfigValue( 'connectTimeout', $timeout );
		foreach ( $client->getConnections() as $connection ) {
			$connection->setConnectTimeout( $timeout );
		}
	}

	/**
	 * Fetch a connection.
	 * @return \Elastica\Client
	 */
	public function getClient() {
		if ( $this->client === null ) {
			// Setup the Elastica servers
			$servers = [];
			$serverList = $this->getServerList();
			if ( !is_array( $serverList ) ) {
				$serverList = [ $serverList ];
			}
			foreach ( $serverList as $server ) {
				if ( is_array( $server ) ) {
					$servers[] = $server;
				} else {
					$servers[] = [ 'host' => $server ];
				}
			}

			$this->client = new \Elastica\Client( [ 'servers' => $servers ],
				/**
				 * Callback for \Elastica\Client on request failures.
				 * @param \Elastica\Connection $connection The current connection to elasticasearch
				 * @param \Elastica\Exception $e Exception to be thrown if we don't do anything
				 */
				function ( $connection, $e ) {
					// We only want to try to reconnect on http connection errors
					// Beyond that we want to give up fast.  Configuring a single connection
					// through LVS accomplishes this.
					if ( !( $e instanceof \Elastica\Exception\Connection\HttpException ) ) {
						wfLogWarning( 'Unknown connection exception communicating with Elasticsearch:  ' .
							get_class( $e ) );
						// This leaves the connection disabled.
						return;
					}
					if ( $e->getError() === CURLE_OPERATION_TIMEOUTED ) {
						// Timeouts shouldn't disable the connection and should always be thrown
						// back to the caller so they can catch it and handle it.  They should
						// never be retried blindly.
						$connection->setEnabled( true );
						throw $e;
					}
					if ( $e->getError() !== CURLE_COULDNT_CONNECT ) {
						wfLogWarning( 'Unexpected connection error communicating with Elasticsearch.  Curl code:  ' .
							$e->getError() );
						// This also leaves the connection disabled but at least we have a log of
						// what happened.
						return;
					}
					// Keep track of the number of times we've hit a host
					static $connectionAttempts = [];
					$host = $connection->getParam( 'host' );
					$connectionAttempts[ $host ] = isset( $connectionAttempts[ $host ] )
						? $connectionAttempts[ $host ] + 1 : 1;

					// Check if we've hit the host the max # of times. If not, try again
					if ( $connectionAttempts[ $host ] < $this->getMaxConnectionAttempts() ) {
						wfLogWarning( "Retrying connection to $host after " . $connectionAttempts[ $host ] .
							' attempts.' );
						$connection->setEnabled( true );
					}
				}
			);
		}

		return $this->client;
	}

	/**
	 * Fetch the Elastica Index.
	 * @param string $name get the index(es) with this basename
	 * @param mixed $type type of index (named type or false to get all)
	 * @param mixed $identifier if specified get the named identifier of the index
	 * @return \Elastica\Index
	 */
	public function getIndex( $name, $type = false, $identifier = false ) {
		return $this->getClient()->getIndex( $this->getIndexName( $name, $type, $identifier ) );
	}

	/**
	 * Get the name of the index.
	 * @param string $name get the index(es) with this basename
	 * @param mixed $type type of index (named type or false to get all)
	 * @param mixed $identifier if specified get the named identifier of the index
	 * @return string name of index for $type and $identifier
	 */
	public function getIndexName( $name, $type = false, $identifier = false ) {
		if ( $type ) {
			$name .= '_' . $type;
		}
		if ( $identifier ) {
			$name .= '_' . $identifier;
		}
		return $name;
	}

	public function destroyClient() {
		$this->client = null;
		ElasticaHttpTransportCloser::destroySingleton();
	}

	/**
	 * @deprecated
	 */
	public function setTimeout2( $timeout ) {
		$this->setTimeout( $timeout );
	}

	/**
	 * @deprecated
	 */
	public function getClient2() {
		// This method used to have an optional argument $options, which was
		// unused and confusing
		return $this->getClient();
	}

	/**
	 * @deprecated
	 */
	public function getIndex2( $name, $type = false, $identifier = false ) {
		return $this->getIndex( $name, $type, $identifier );
	}

	/**
	 * @deprecated
	 */
	public function getIndexName2( $name, $type = false, $identifier = false ) {
		return $this->getIndexName2( $name, $type, $identifier );
	}

	/**
	 * @deprecated
	 */
	public function destroySingleton() {
		$this->destroyClient();
	}
}

class ElasticaHttpTransportCloser extends \Elastica\Transport\Http {
	public static function destroySingleton() {
		\Elastica\Transport\Http::$_curlConnection = null;
	}
}

/**
 * Utility class
 */
class MWElasticUtils {
	/**
	 * Iterate over a scroll.
	 *
	 * @param \Elastica\Index $index
	 * @param string $scrollId the initial $scrollId
	 * @param string $scrollTime the scroll timeout
	 * @param callable $consumer function that receives the results
	 * @param int $limit the max number of results to fetch (0: no limit)
	 * @param int $retryAttempts the number of times we retry
	 * @param callable $retryErrorCallback function called before each retries
	 */
	public static function iterateOverScroll( \Elastica\Index $index, $scrollId, $scrollTime,
		$consumer, $limit = 0, $retryAttempts = 0, $retryErrorCallback = null
	) {
		$clearScroll = true;
		$fetched = 0;

		while ( true ) {
			$result = static::withRetry( $retryAttempts,
				function () use ( $index, $scrollId, $scrollTime ) {
					return $index->search( [], [
						'scroll_id' => $scrollId,
						'scroll' => $scrollTime
					] );
				}, $retryErrorCallback );

			$scrollId = $result->getResponse()->getScrollId();

			if ( !$result->count() ) {
				// No need to clear scroll on the last call
				$clearScroll = false;
				break;
			}

			$fetched += $result->count();
			$results = $result->getResults();

			if ( $limit > 0 && $fetched > $limit ) {
				$results = array_slice( $results, 0, count( $results ) - ( $fetched - $limit ) );
			}
			$consumer( $results );

			if ( $limit > 0 && $fetched >= $limit ) {
				break;
			}
		}
		// @todo: catch errors and clear the scroll, it'd be easy with a finally block ...

		if ( $clearScroll ) {
			try {
				$index->getClient()->request( "_search/scroll/".$scrollId, \Elastica\Request::DELETE );
			} catch ( Exception $e ) {
			}
		}
	}

	/**
	 * A function that retries callback $func if it throws an exception.
	 * The $beforeRetry is called before a retry and receives the underlying
	 * ExceptionInterface object and the number of failed attempts.
	 * It's generally used to log and sleep between retries. Default behaviour
	 * is to sleep with a random backoff.
	 * @see Util::backoffDelay
	 *
	 * @param int $attempts the number of times we retry
	 * @param callable $func
	 * @param callable $beforeRetry function called before each retry
	 * @return mixed
	 */
	public static function withRetry( $attempts, $func, $beforeRetry = null ) {
		$errors = 0;
		while ( true ) {
			if ( $errors < $attempts ) {
				try {
					return $func();
				} catch ( Exception $e ) {
					$errors += 1;
					if ( $beforeRetry ) {
						$beforeRetry( $e, $errors );
					} else {
						$seconds = static::backoffDelay( $errors );
						sleep( $seconds );
					}
				}
			} else {
				return $func();
			}
		}
	}

	/**
	 * Backoff with lowest possible upper bound as 16 seconds.
	 * With the default maximum number of errors (5) this maxes out at 256 seconds.
	 *
	 * @param int $errorCount
	 * @return int
	 */
	public static function backoffDelay( $errorCount ) {
		return rand( 1, (int)pow( 2, 3 + $errorCount ) );
	}
}
