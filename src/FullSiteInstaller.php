<?php
/**
 * Full Site Installer
 *
 * @package vektor-inc/full-site-installer
 * @license GPL-2.0+
 *
 * @version 0.0.3
 */

namespace VektorInc\FullSiteInstaller;

class FullSiteInstaller {

	public static $version = '0.0.3';

	/**
	 * Get sites from the API.
	 *
	 * @param string $api_url The URL of the API endpoint to fetch sites from.
	 * @return array An array of sites, or an empty array if the API call fails or returns invalid data.
	 */
	public static function get_sites( $api_url ) {
		// API から sites.json を取得
		$sites_json = file_get_contents( $api_url );

		// JSON デコード
		$sites = json_decode( $sites_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [];
		}

		return $sites;
	}
}