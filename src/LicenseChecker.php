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
	 *
	 * $api_param は製品ごとに一意にすること。同じ $api_param で複数回呼ぶと、
	 * 後から積んだ値で以前の値が黙って上書きされる（エラーにはならない）。
	 * また 'site_code' は getData() が site_code の送信に使う予約済みのキー名のため、
	 * $api_param に 'site_code' は使えない（使っても getData() 側で site_code の値に上書きされる）。
	 *
	 * @param string $api_param : ライセンス認証 API へ送るクエリパラメータ名（'site_code' は使用不可）
	 * @param string $value     : ライセンスキーの値
	 * @return void
	 */
	public function setLicenseKey( $api_param, $value ) {
		$this->license_keys[ $api_param ] = $value;
	}

	/**
	 * ライセンス認証状況を UpdateChecker に問い合わせ
	 * @return array|null 認証結果の連想配列。リクエスト失敗時や不正なレスポンス時は null
	 */
	public function getData() {

		$this->api_url = apply_filters( 'vkfsibt_license_check_url', $this->api_url );
		// license_keys → site_code の順で merge し、api_param に 'site_code' が使われていても
		// site_code の値が必ず勝つようにする（後の配列の値で上書きされる array_merge の仕様を利用）
		$api_url = add_query_arg(
			array_merge(
				$this->license_keys,
				[ 'site_code' => $this->site_code ]
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