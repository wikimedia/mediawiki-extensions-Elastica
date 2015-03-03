<?php

class ElasticaHooks {

	/**
	 * Registration callback to load the composer autoloader if one is present
	 */
	public static function onRegistration() {
		if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
			require_once __DIR__ . '/vendor/autoload.php';
		}
	}
}
