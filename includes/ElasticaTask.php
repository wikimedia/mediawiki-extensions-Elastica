<?php

use \Elastica\Client;
use \Elastica\Param;
use \Elastica\Response;

/**
 * Represents elasticsearch task.
 *
 * Backported from Elastica 6.x. Should be deleted after upgrade.
 *
 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/tasks.html
 */
class ElasticaTask extends Param {
	const WAIT_FOR_COMPLETION = 'wait_for_completion';
	const WAIT_FOR_COMPLETION_FALSE = 'false';
	const WAIT_FOR_COMPLETION_TRUE = 'true';

	/**
	 * Task id, e.g. in form of nodeNumber:taskId.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Contains all status infos.
	 *
	 * @var \Elastica\Response Response object
	 */
	protected $response;

	/**
	 * Data.
	 *
	 * @var array Data
	 */
	protected $data;

	/**
	 * Client object.
	 *
	 * @var \Elastica\Client Client object
	 */
	protected $client;

	/**
	 * @param Client $client
	 * @param string $id
	 */
	public function __construct( Client $client, $id ) {
		$this->client = $client;
		$this->id = $id;
	}

	/**
	 * Returns task id.
	 *
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Returns task data.
	 *
	 * @return array Task data
	 */
	public function getData(): array {
		if ( is_null( $this->data ) ) {
			$this->refresh();
		}

		return $this->data;
	}

	/**
	 * Returns response object.
	 *
	 * @return \Elastica\Response
	 */
	public function getResponse(): Response {
		if ( is_null( $this->response ) ) {
			$this->refresh();
		}

		return $this->response;
	}

	/**
	 * Refresh task status.
	 *
	 * @param array $options Options for endpoint
	 */
	public function refresh( array $options = [] ) {
		$endpoint = new \Elasticsearch\Endpoints\Tasks\Get();
		$endpoint->setTaskId( $this->id );
		$endpoint->setParams( $options );

		$this->response = $this->client->requestEndpoint( $endpoint );
		$this->data = $this->getResponse()->getData();
	}

	/**
	 * @return bool
	 */
	public function isCompleted() {
		$data = $this->getData();

		return $data['completed'] === true;
	}

	public function cancel(): Response {
		if ( empty( $this->id ) ) {
			throw new \Exception( 'No task id given' );
		}

		$endpoint = new \Elasticsearch\Endpoints\Tasks\Cancel();
		$endpoint->setTaskId( $this->id );

		return $this->client->requestEndpoint( $endpoint );
	}
}
