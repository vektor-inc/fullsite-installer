<?php

namespace VektorInc\FullSiteInstaller;

use \GuzzleHttp\Client;

class FullSiteInstallerLicenseChecker {

	use FullSiteInstallerSingleton;

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
	public function set_api_url( $url ) {
		$this->api_url = $url;
	}

	/**
	 * サイトコードをセット
	 * @param string $code : サイトコード
	 * @return void
	 */
	public function set_site_code( $code ) {
		$this->site_code = $code;
	}

	/**
	 * Vektor Passport ライセンスキーをセット
	 * @param string $license_key : ライセンスキー
	 * @return void
	 */
	public function set_passport_license_key( $license_key ) {
		$this->passport_license_key = $license_key;
	}

	/**
	 * サイトライセンスキーをセット
	 * @param string $license_key : ライセンスキー
	 * @return void
	 */
	public function set_site_license_key( $license_key ) {
		$this->site_license_key = $license_key;
	}

	/**
	 * ライセンス認証状況を UpdateChecker に問い合わせ
	 * @return string
	 */
	public function get_data() {

		// ライセンス認証 URL を生成
		$api_url = $this->api_url;
		if ( strpos( $api_url, '?' ) === false ) {
			$api_url .= '?';
		} else {
			$api_url .= '&';
		}
		$api_url .= 'site_code=' . $this->site_code;
		$api_url .= '&passport_license_key=' . $this->passport_license_key;
		$api_url .= '&site_license_key=' . $this->site_license_key;

		// ライセンス認証 URL にアクセスして結果を取得
		$client = new Client();
		$response = $client->request( 'GET', $api_url );
		$contents = $response->getBody()->getContents();
		$json = json_decode( $contents, true );

		return $json;
	}
}