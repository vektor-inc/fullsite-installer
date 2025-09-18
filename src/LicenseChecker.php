<?php

namespace VektorInc\FullSiteInstaller;

use \GuzzleHttp\Client;

class LicenseChecker {

	use Singleton;

	/**
	 * サイトコード
	 * @var string
	 */
	private $site_code;

	/**
	 * Vektor Passport ライセンスキー
	 * @var string
	 */
	private $passport_license_key;

	/**
	 * サイトライセンスキー
	 * @var string
	 */
	private $site_license_key;

	/**
	 * ライセンス認証 URL
	 * @var string
	 */
	private $api_url = '';

	/**
	 * ライセンスチェッカー初期化
	 * @return void
	 */
	public function initialize() {
	}

	/**
	 * API URL をセット
	 */
	public function setApiUrl( $url ) {
		$this->api_url = $url;
	}

	/**
	 * サイトコードをセット
	 * @param string $code : サイトコード
	 * @return void
	 */
	public function setSiteCode( $code ) {
		$this->site_code = $code;
	}

	/**
	 * Vektor Passport ライセンスキーをセット
	 * @param string $license_key : ライセンスキー
	 * @return void
	 */
	public function setPassportLicenseKey( $license_key ) {
		$this->passport_license_key = $license_key;
	}

	/**
	 * サイトライセンスキーをセット
	 * @param string $license_key : ライセンスキー
	 * @return void
	 */
	public function setSiteLicenseKey( $license_key ) {
		$this->site_license_key = $license_key;
	}

	/**
	 * ライセンス認証状況を UpdateChecker に問い合わせ
	 * @return string
	 */
	public function getData() {

		$api_url = add_query_arg(
			[
				'site_code'             => $this->site_code,
				'passport_license_key'  => $this->passport_license_key,
				'site_license_key'      => $this->site_license_key,
			],
			$this->api_url
		);

		$response = wp_remote_get( $api_url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $response ) ) { return null; }

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) { return null; }

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) { return null; }

		return $json;
	}
}