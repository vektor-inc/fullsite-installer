<?php

namespace VektorInc\FullSiteInstaller;

class LicenseChecker {

	use Singleton;

	/**
	 * サイトコード
	 * @var string
	 */
	private $site_code;

	/**
	 * ライセンスキーの連想配列
	 * API パラメータ名（api_param）をキーにして値を積む。
	 * 製品が増えてもこのクラスを触らずに済むよう、個別プロパティではなく配列で持つ。
	 * @var array
	 */
	private $license_keys = array();

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
	 * ライセンスキーをセット
	 * @param string $api_param : ライセンス認証 API へ送るクエリパラメータ名
	 * @param string $value     : ライセンスキーの値
	 * @return void
	 */
	public function setLicenseKey( $api_param, $value ) {
		$this->license_keys[ $api_param ] = $value;
	}

	/**
	 * ライセンス認証状況を UpdateChecker に問い合わせ
	 * @return string
	 */
	public function getData() {

		$this->api_url = apply_filters( 'vkfsibt_license_check_url', $this->api_url );
		$api_url = add_query_arg(
			array_merge(
				[ 'site_code' => $this->site_code ],
				$this->license_keys
			),
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