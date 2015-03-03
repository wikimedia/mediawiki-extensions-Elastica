<?php
/**
 * Elasticsearch base extension.  Used by other extensions to ease working with
 * elasticsearch.
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

$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'Elastica',
	'author'         => array( 'Nik Everett', 'Chad Horohoe' ),
	'descriptionmsg' => 'elastica-desc',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Elastica',
	'version'        => '1.3.0.0'
);

/**
 * Classes
 */
$wgAutoloadClasses['ElasticaConnection'] = __DIR__ . '/ElasticaConnection.php';
$wgAutoloadClasses['ElasticaHttpTransportCloser'] = __DIR__ . '/ElasticaConnection.php';
$wgAutoloadClasses['ElasticaHooks'] = __DIR__ . '/Elastica.hooks.php';

ElasticaHooks::onRegistration();
/**
 * i18n
 */
$wgMessagesDirs['Elastica'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Elastica'] = __DIR__ . '/Elastica.i18n.php';
