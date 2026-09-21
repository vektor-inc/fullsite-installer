<?php
/**
 * Full Site Installer
 *
 * @package vektor-inc/fullsite-installer
 * @license GPL-2.0+
 *
 * @version 0.1.0
 */
namespace VektorInc\FullSiteInstaller;

// sites.json の URL
define( 'SITES_JSON_API_URL', 'https://vk-fullsite-installer.com/wp-json/vkfsiw/v1/sites' );

// サイトデータのカウンター用 API の URL
define( 'SITES_COUNTER_API_URL', 'https://vk-fullsite-installer.com/wp-json/vkfsiw/v1/counter' );

// ライセンス認証用 API の URL
define( 'LICENSE_CHECK_API_URL', 'https://vk-fullsite-installer.com/wp-json/vkfsiw/v1/license' );

// Vektor Passport の購入 URL
define( 'PASSPORT_PURCHASE_URL', 'https://vws.vektor-inc.co.jp/product/vektor-passport-1y' );

// VK Booking Manager Pro の購入 URL
define( 'BOOKING_MANAGER_PRO_PURCHASE_URL', 'https://vws.vektor-inc.co.jp/product/vk-booking-manager-pro' );

// sites.json の license_type の種類
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_FREE', 'free' );
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT', 'passport' );
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_SITE', 'site' );
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE', 'passport_and_site' );
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_BOOKING_MANAGER_PRO', 'booking_manager_pro' );
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_BOOKING_MANAGER_PRO_AND_SITE', 'booking_manager_pro_and_site' );

use VektorInc\FullSiteInstaller\LicenseChecker;

/**
 * Installer クラス
 *
 * このクラスは、VK FullSite Installer プラグインのメイン機能を提供します。
 * サイトのインポート、設定画面の表示、ライセンス認証などを行います。
 */
class Installer {

	/**
	 * ライセンスの種類
	 */
	public static $license_type_name_array = [
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_FREE => '無料',
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT => 'Vektor Passport',
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_SITE => 'サイトライセンス',
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE => 'Vektor Passport + サイトライセンス',
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_BOOKING_MANAGER_PRO => 'VK Booking Manager Pro',
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_BOOKING_MANAGER_PRO_AND_SITE => 'VK Booking Manager Pro + サイトライセンス',
	];

	/**
	 * ライセンスキー入力欄の定義配列
	 *
	 * デモサイト一覧画面のライセンスキー入力欄、インポート後の保存処理、
	 * ライセンス認証 API への送信を、すべてこの配列からループで組み立てる。
	 * 新しい製品のライセンスキーに対応する場合は、ここへ1ブロック追加するだけでよい。
	 *
	 * 各要素の意味:
	 * - label         : 入力欄に表示するラベル文言
	 * - field         : $_POST のフィールド名（既存の入力値との互換のため、既存名は変更しない）
	 * - save_button   : 保存ボタンの name 属性（既存名は変更しない）
	 * - api_param     : ライセンス認証 API へ送るクエリパラメータ名（既存名は変更しない）
	 * - error_message : 認証エラー時に表示するメッセージ
	 * - license_types : この入力欄を表示する license_type（サイトの区分）の一覧
	 * - purchase_url  : 購入ボタンの遷移先。null の場合はデモサイトごとの購入リンク（shop-item.buy-link）を使う
	 * - price_note    : 販売価格の注記に足す文言（HTML 可・不要な区分は空文字列）
	 * - kind          : 認証結果の扱い方の区分。'product'（製品ライセンス。サイトライセンスが未認証でも単独で使える）
	 *                   または 'site'（サイトライセンス）。
	 *                   注意: 認証結果の status（success_passport / success_product）は製品同士を区別しない。
	 *                   そのため、同一区分（license_types の1つの値）に kind = 'product' のスロットを
	 *                   2つ以上該当させる構成にする場合は、API 側が「どの api_param が成功したか」の
	 *                   一覧を返す形に変更してから行うこと。変更せずに追加すると、
	 *                   displaySiteListPage() 側は「どちらが通ったか判断できない」として
	 *                   該当する製品キーをまとめてクリアする（フェイルクローズ）ため、
	 *                   認証が通ったはずのキーまで毎回消える不具合になる。
	 *                   また、このフェイルクローズは error_message（例:「◯◯ライセンスキーが間違っています。」）
	 *                   をそのまま流用して表示するため、認証が通ったキーにも「入力が誤り」の文言が出てしまう。
	 *                   API 側の変更に着手する際は、この error_message の流用も先に見直すこと
	 * - key_options   : インポート後にライセンスキーを保存するオプション名の一覧。
	 *                   文字列ならそのオプションへ直接 update_option() する。
	 *                   array( 'option' => オプション名, 'sub_key' => 配列内のキー名 ) の形なら、
	 *                   get_option() で取得した配列の該当キーだけを書き換えて保存する
	 *                   （VK Blocks Pro のように、複数設定をまとめて持つオプションのための書き込み方法）
	 * - send_always   : ライセンス認証 API へ常にこのパラメータを送るかどうか。
	 *                   true（passport / site）は既存2区分で、選択中のサイトの区分に関わらず
	 *                   常に送る（空文字でも送る）。これは main のリクエストの形をそのまま保つためで、
	 *                   ここを該当時のみ送る形に変えると、API 側が「値が無い＝そのパラメータでの認証を試みていない」
	 *                   ではなく「空文字を渡された＝認証失敗」と解釈した場合に、既存の Vektor Passport /
	 *                   サイトライセンスのみのサイトで認証が通らなくなる後退を招く。
	 *                   false（booking_manager_pro 以降の新しい製品）は、選択中のサイトの区分が
	 *                   license_types に該当するときだけ送る。既存4区分（free/site/passport/passport_and_site）
	 *                   のリクエストの形を変えないため
	 */
	private static $license_key_fields = array(
		'passport' => array(
			'label'         => 'Vektor Passport ライセンスキー',
			'field'         => 'license_key_vektor_passport',
			'save_button'   => 'save_license_key_vektor_passport',
			'api_param'     => 'passport_license_key',
			'error_message' => 'Vektor Passport ライセンスキーが間違っています。',
			'license_types' => array(
				VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT,
				VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE,
			),
			'purchase_url'  => PASSPORT_PURCHASE_URL,
			// price_note は静的プロパティの初期値（PHP の制約で関数呼び出しを含められない）のため、
			// URL は固定の定数文字列をそのまま埋め込んでいる（外部入力ではないため esc_url() は不要）
			'price_note'    => '<span class="vkfsi_price_passport">※ 別途 <a href="https://vws.vektor-inc.co.jp/vektor-passport" target="_blank">Vektor Passport</a> が必要です</span>',
			'kind'          => 'product',
			'send_always'   => true,
			'key_options'   => array(
				'lightning-g3-pro-unit-license-key',
				'vk_ab_testing_license_key',
				'smaveksive-license-key',
				array(
					'option'  => 'vk_blocks_options',
					'sub_key' => 'vk_blocks_pro_license_key',
				),
			),
		),
		'booking_manager_pro' => array(
			'label'         => 'VK Booking Manager Pro ライセンスキー',
			'field'         => 'license_key_booking_manager_pro',
			'save_button'   => 'save_license_key_booking_manager_pro',
			'api_param'     => 'product_license_key',
			'error_message' => 'VK Booking Manager Pro ライセンスキーが間違っています。',
			'license_types' => array(
				VK_FULLSITE_INSTALLER_LICENSE_TYPE_BOOKING_MANAGER_PRO,
				VK_FULLSITE_INSTALLER_LICENSE_TYPE_BOOKING_MANAGER_PRO_AND_SITE,
			),
			'purchase_url'  => BOOKING_MANAGER_PRO_PURCHASE_URL,
			// price_note の URL 埋め込みについては passport 側の price_note のコメントを参照
			'price_note'    => '<span class="vkfsi_price_passport">※ 別途 <a href="' . BOOKING_MANAGER_PRO_PURCHASE_URL . '" target="_blank">VK Booking Manager Pro</a> が必要です</span>',
			'kind'          => 'product',
			'send_always'   => false,
			'key_options'   => array(
				'vk-booking-manager-pro-license-key',
			),
		),
		'site' => array(
			'label'         => 'サイトライセンスキー',
			'field'         => 'license_key_site',
			'save_button'   => 'save_license_key_site',
			'api_param'     => 'site_license_key',
			'error_message' => 'サイトライセンスキーが間違っています。',
			'license_types' => array(
				VK_FULLSITE_INSTALLER_LICENSE_TYPE_SITE,
				VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE,
				VK_FULLSITE_INSTALLER_LICENSE_TYPE_BOOKING_MANAGER_PRO_AND_SITE,
			),
			'purchase_url'  => null,
			'price_note'    => '',
			'kind'          => 'site',
			'send_always'   => true,
			'key_options'   => array(),
		),
	);

	// 変更しないテーブルの配列
	public static $skip_table_array = array(
		'wp_users',
		'wp_usermeta',
	);

	/**
	 * プラグインの初期化処理
	 */
	public static function init() {
		add_action( 'admin_footer', array( __CLASS__, 'getSvgSprite' ) );
	}

	/**
	 * SVGスプライトの取得
	 */
	public static function getSvgSprite() {
		$path = __DIR__ . '/assets/images/icon.svg';
		if ( file_exists( $path ) ) {
			echo file_get_contents( $path );
		}
	}

	/**
	 * Execute the plugin.
	 */
	public static function execute() {
		// 処理終了メッセージの表示
		if ( isset( $_GET[ 'imported' ] ) ) {
			self::displayImportEndPage();
			return;
		}

		// インポート処理
		if ( isset( $_POST[ 'start_import' ] ) ) {
			self::importSite();
			return;
		}

		// インポート用フォーム（サイト名等の入力画面）
		if ( isset( $_POST['select_site'] ) ) {
			self::displayImportForm();
			return;
		}

		// サイト一覧の表示
		self::displaySiteListPage();
	}

	/**
	 * Display the import end page.
	 *
	 * This function is called when the import process is completed.
	 * It shows a message indicating that the import has finished and deactivates the plugin.
	 */
	public static function displayImportEndPage() {
		// パーマリンクの再設定
		flush_rewrite_rules();

		// タイトル画像
		$titleImage = self::getSvgImageTag( __DIR__ . '/assets/images/admin.svg', 'VK FullSite Installer 設定' );

		// 管理画面のインポートページを表示
		require_once __DIR__ . '/views/import-end.php';

		// プラグインを無効化
		$deactivate_plugins = array( 'vk-fullsite-installer', 'vk-fullsite-installer-beta-tester' );
		foreach ( $deactivate_plugins as $plugin ) {
			deactivate_plugins( $plugin . '/' . $plugin . '.php' );
		}
	}

	/**
	 * Display the import form.
	 *
	 * This function is called when the user selects a site to import.
	 * It shows a form where the user can enter the site code and license keys.
	 */
	public static function displayImportForm() {
		// タイトル画像
		$titleImage = self::getSvgImageTag( __DIR__ . '/assets/images/admin.svg', 'VK FullSite Installer 設定' );

		// インポートページを表示
		require_once __DIR__ . '/views/import-form.php';
	}

	/**
	 * Import the selected site.
	 *
	 * This function is called when the user submits the import form.
	 * It processes the import of the selected site based on the provided site code and license keys.
	 */
	public static function importSite() {

		//// インポート処理前の入力値チェック ////

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this action.', 'default' ) );
		}
		if ( ! isset( $_POST[ 'vkfsi_import_nonce' ] ) || ! wp_verify_nonce( $_POST[ 'vkfsi_import_nonce' ], 'vkfsi_start_import' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'default' ) );
		}

		// データ URL のチェック
		if ( ! isset( $_POST[ 'vkfsi_data_url' ] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>インポートするサイトを選択してください。</p></div>';
			return;
		}

		// 必須入力項目のチェック
		$validate_flag = true;

		if ( empty( $_POST[ 'content_user_id' ] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>コンテンツの所有者を指定してください。</p></div>';
			$validate_flag = false;
		}

		if ( ! isset( $_POST[ 'confirm_import' ] ) || $_POST[ 'confirm_import' ] !== 'yes' ) {
			echo '<div class="notice notice-error is-dismissible"><p>インポートを確認するチェックボックスをオンにしてください。</p></div>';
			$validate_flag = false;
		}

		if ( ! isset( $_POST[ 'confirm_import_database' ] ) || $_POST[ 'confirm_import_database' ] !== 'yes' ) {
			echo '<div class="notice notice-error is-dismissible"><p>データベースの更新に関する確認のチェックボックスをオンにしてください。</p></div>';
			$validate_flag = false;
		}

		if ( ! isset( $_POST[ 'confirm_import_maintenance' ] ) || $_POST[ 'confirm_import_maintenance' ] !== 'yes' ) {
			echo '<div class="notice notice-error is-dismissible"><p>メンテナンスモードに関する確認のチェックボックスをオンにしてください。</p></div>';
			$validate_flag = false;
		}

		// 入力エラーがあれば処理終了
		if ( ! $validate_flag ) {
			return;
		}

		//// ここからインポート処理 ////
		global $wpdb;

		// WordPress アドレスとサイトアドレスを事前に取得
		$site_url = get_site_url();
		$home_url = get_home_url();

		// WordPress のサイト名を取得
		$site_name = get_bloginfo( 'name' );

		// 管理者メールアドレスを取得
		$admin_email = get_option( 'admin_email' );

		// 各入力値の取得
		$data_url = esc_url_raw( $_POST[ 'vkfsi_data_url' ] );
		$content_user_id = intval( $_POST[ 'content_user_id' ] );

		// インポート用ディレクトリの作成
		$import_dir = WP_CONTENT_DIR . '/vk-fullsite-installer';
		if ( ! file_exists( $import_dir ) ) {
			mkdir( $import_dir, 0755, true );
		}

		// ZIP ファイルのダウンロード
		$zip_file = $import_dir . '/' . basename( $data_url );
		$response = wp_remote_get( $data_url, array( 'timeout' => 300 ) );
		if ( is_wp_error( $response ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>インポートデータのダウンロードに失敗しました。</p></div>';
			return;
		}
		file_put_contents( $zip_file, wp_remote_retrieve_body( $response ) );
		unset( $response );

		// ZIP ファイルの解凍
		$zip = new \ZipArchive();
		if ( $zip->open( $zip_file ) === TRUE ) {
			$zip->extractTo( $import_dir );
			$zip->close();
			unlink( $zip_file ); // ZIP ファイルを削除
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>Zip ファイルの解凍に失敗しました。</p></div>';
			return;
		}
		unset( $zip );

		// 分割するディレクトリのリスト
		$split_dirs = array( 'uploads', 'plugins', 'themes' );
		foreach ( $split_dirs as $split_dir ) {
			// 分割されたデータの URL
			$split_data_url = str_replace( '.zip', '-' . $split_dir . '.zip', $data_url );

			// ZIP ファイルのダウンロード
			$split_zip_file = $import_dir . '/' . basename( $split_data_url );
			$response = wp_remote_get( $split_data_url, array( 'timeout' => 300 ) );

			// ファイルが存在しない場合もあるのでそれに対処
			$status_code_404 = 404;
			if ( is_wp_error( $response ) ) {
				unset( $response );
				continue;
			} elseif ( wp_remote_retrieve_response_code( $response ) == $status_code_404 ) {
				unset( $response );
				continue;
			}
			file_put_contents( $split_zip_file, wp_remote_retrieve_body( $response ) );
			unset( $response );

			// ZIP ファイルの解凍
			$split_zip = new \ZipArchive();
			if ( $split_zip->open( $split_zip_file ) === TRUE ) {
				$split_zip->extractTo( $import_dir );
				$split_zip->close();
				unlink( $split_zip_file ); // ZIP ファイルを削除
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>Zip ファイルの解凍に失敗しました。</p></div>';
				return;
			}
			unset( $split_zip );
		}

		// Table Prefix の取得
		$prefix_file = $import_dir . '/prefix.txt';
		$table_prefix = 'wp_'; // デフォルトのプレフィックス
		if ( file_exists( $prefix_file ) ) {
			// プレフィックスファイルが存在する場合、プレフィックスを取得
			$table_prefix = file_get_contents( $prefix_file );
			unlink( $prefix_file ); // プレフィックスファイルを削除
		}

		// SQL ファイルのインポート
		$sql_file = $import_dir . '/site-export.sql';
		if ( file_exists( $sql_file ) ) {

			// wp_options テーブルのエクスポート部分から siteurl と home の値を取得
			$old_site_url = self::getSiteurl( $sql_file, $table_prefix );
			$old_home_url = self::getHome( $sql_file, $table_prefix );

			// 既存テーブルの DROP
			$default_table_array = $wpdb->get_col( 'SHOW TABLES' );
			foreach ( $default_table_array as $table ) {
				// 指定プレフィックスのテーブルのみ DROP
				if ( strpos( $table, $wpdb->prefix ) !== 0 ) {
					continue;
				}

				// wp_users, wp_usermeta テーブルの DROP を除外
				$table_without_prefix = str_replace( $wpdb->prefix, 'wp_', $table );
				if ( in_array( $table_without_prefix, self::$skip_table_array ) ) {
					continue;
				}
				$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
			}

			// 新規テーブルの作成
			self::createTables( $sql_file, $table_prefix );

			// SQL ファイルを読み込みデータの INSERT を行う
			$handle = fopen( $sql_file, 'r' );
			if ( $handle ) {
				$create_table_flag = false;
				$insert_flag = false;
				$query = '';
				while ( ( $line = fgets( $handle ) ) !== false ) {
					if ( ! $create_table_flag && strpos( $line, 'CREATE TABLE `' . $table_prefix ) === 0 ) {
						$create_table_flag = true;
						continue;
					}
					if ( $create_table_flag && strpos( $line, ') ' ) === 0 ) {
						$create_table_flag = false;
						continue;
					}
					if ( $create_table_flag ) {
						continue;
					}

					// INSERT INTO の行をまとめる
					if ( strpos( $line, 'INSERT INTO ' ) === 0 ) {
						// INSERT 文が１行で終わる場合
						if ( strpos( $line, ";\n" ) !== false ) {
							// wp_users, wp_usermeta テーブルの INSERT 文を除外
							if ( strpos( $line, $table_prefix . 'users' ) !== false ) {
								continue;
							}
							if ( strpos( $line, $table_prefix . 'usermeta' ) !== false ) {
								continue;
							}
							$query = $line;
							$query = str_replace( 'INSERT INTO ' . $table_prefix, 'INSERT INTO ' . $wpdb->prefix, $query );
							$result = $wpdb->query( $query );
							$query = '';

						// INSERT 文が複数行にわたる場合
						} else {
							$insert_flag = true;
							$query = $line;
							continue;
						}
					}

					// INSERT 文が複数行にわたる場合
					if ( $insert_flag ) {
						// INSERT 文の終わりを検出
						if ( strpos( $line, ";\n" ) !== false ) {
							$query .= $line;

							// wp_users, wp_usermeta テーブルの INSERT 文を除外
							if ( strpos( $line, $table_prefix . 'users' ) === false
								&& strpos( $line, $table_prefix . 'usermeta' ) === false ) {
								$query = str_replace( 'INSERT INTO ' . $table_prefix, 'INSERT INTO ' . $wpdb->prefix, $query );
								$result = $wpdb->query( $query );
							}

							$query = '';
							$insert_flag = false;
						} else {
							$query .= $line;
						}
					}
				}
				fclose( $handle );
			}

			// Role のオプションを変更
			if ( $table_prefix != $wpdb->prefix ) {
				// wp_user_roles のオプション名を変更
				$query = 'UPDATE ' . $wpdb->prefix . 'options';
				$query .= ' SET option_name = "' . $wpdb->prefix . 'user_roles"';
				$query .= ' WHERE option_name = "' . $table_prefix . 'user_roles"';
				$result = $wpdb->query( $query );

				// wp_user_roles の値を取得して新しいオプションに追加
				$value = get_option( $wpdb->prefix . 'user_roles' );
				add_option( $table_prefix . 'user_roles', $value );
			}

			unlink( $sql_file ); // SQL ファイルを削除
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>SQLファイルが見つかりません。</p></div>';
			return;
		}

		// wp_options テーブルの siteurl と home の値を更新
		if ( $old_site_url != '' && $old_home_url != '' ) {
			$wpdb->update( $wpdb->options, [ 'option_value' => $site_url ], [ 'option_name' => 'siteurl' ], [ '%s' ], '%s' );
			$wpdb->update( $wpdb->options, [ 'option_value' => $home_url ], [ 'option_name' => 'home' ], [ '%s' ], '%s' );
		}

		// wp_options テーブルの admin_email の値を更新
		$wpdb->update( $wpdb->options, [ 'option_value' => $admin_email ], [ 'option_name' => 'admin_email' ], [ '%s' ], '%s' );
		$wpdb->update( $wpdb->options, [ 'option_value' => '' ], [ 'option_name' => 'new_admin_email' ], [ '%s' ] );

		// テーブルの一覧を取得
		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
		foreach ( $tables as $table ) {
			$table_name = $table[ 0 ];
			if (strpos( $table_name, $wpdb->prefix ) !== 0) {
				continue; // プレフィックスが異なるテーブルはスキップ
			}
			self::replaceTableValues( $table_name, $old_site_url, $site_url );
			self::replaceTableValues( $table_name, $old_home_url, $home_url );
		}

		// wp-content 以下のファイルをインポート
		$content_dir = WP_CONTENT_DIR;
		$import_files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $import_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $import_files as $file ) {
			if ( ! $file->isDir() ) {
				$file_path = $file->getRealPath();
				$relative_path = substr( $file_path, strlen( $import_dir ) + 1 );
				$target_path = $content_dir . '/' . $relative_path;

				if ( ! file_exists( dirname( $target_path ) ) ) {
					mkdir( dirname( $target_path ), 0755, true );
				}
				copy( $file_path, $target_path );
			}
		}

		// wp_posts の post_author を更新
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_author = %d",
				$content_user_id
			)
		);

		if ( false === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>記事の Author 情報の更新に失敗しました。</p></div>';
			return;
		}

		// サイト名を更新
		$result = update_option( 'blogname', '' );
		$result = update_option( 'blogname', $site_name );

		// キャッシュのクリア
		wp_cache_flush();

		// プラグインを有効化
		$activate_plugins = array( 'vk-fullsite-installer', 'vk-fullsite-installer-beta-tester' );
		foreach ( $activate_plugins as $plugin ) {
			activate_plugins( $plugin . '/' . $plugin . '.php' );
		}

		// ZIP ファイル用のディレクトリを削除
		self::removeDirectory( $import_dir );

		// 各製品のライセンスキーの保存
		// 隠しフィールド名は import-form.php 側と同じ 'vkfsi_' . field の規則で導出する
		// （既存の Vektor Passport 用フィールド名 vkfsi_license_key_vektor_passport はこの規則のまま維持している）
		foreach ( self::$license_key_fields as $slot => $license_key_field ) {
			// インポート後に保存先を持たない区分（サイトライセンス等）はスキップ
			if ( empty( $license_key_field[ 'key_options' ] ) ) {
				continue;
			}

			$hidden_field_name = 'vkfsi_' . $license_key_field[ 'field' ];
			if ( empty( $_POST[ $hidden_field_name ] ) ) {
				continue;
			}
			$license_key_value = sanitize_text_field( wp_unslash( $_POST[ $hidden_field_name ] ) );

			foreach ( $license_key_field[ 'key_options' ] as $key_option ) {
				if ( is_array( $key_option ) ) {
					// 配列の中の1要素として保存する製品（例: VK Blocks Pro）
					// option / sub_key のどちらかが欠けている定義は書き込み先が特定できないためスキップする
					// （$options[ null ] のような意図しないキーへ静かに書き込むことを防ぐ）
					if ( ! isset( $key_option[ 'option' ], $key_option[ 'sub_key' ] ) ) {
						continue;
					}
					$options = get_option( $key_option[ 'option' ] );
					if ( ! is_array( $options ) ) {
						$options = array();
					}
					$options[ $key_option[ 'sub_key' ] ] = $license_key_value;
					update_option( $key_option[ 'option' ], $options );
				} else {
					// オプションへ直接保存する製品
					update_option( $key_option, $license_key_value );
				}
			}
		}

		// サイトデータのカウントアップ
		$site_code = isset( $_POST[ 'vkfsi_code' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'vkfsi_code' ] ) ) : '';
		$api_url = add_query_arg( 'code', $site_code, apply_filters( 'vkfsi_sites_counter_api_url', SITES_COUNTER_API_URL ) );
		$response = wp_remote_get( $api_url );

		// リダイレクト URL をフィルタリング
		$url = admin_url( 'options-general.php?page=vk-fullsite-installer&imported=true' );
		$url = apply_filters( 'vkfsi_redirect_url', $url );

		// インポートが完了したら、リダイレクトして完了ページを表示
		wp_redirect( $url );
		exit;
	}

	/**
	 * wp_options テーブルのエクスポート部分から siteurl の値を取得
	 * @param string $file SQL ファイルのパス
	 * @param string $prefix テーブルのプレフィックス
	 * @return string siteurl の値
	 */
	public static function getSiteurl( $file, $prefix ) {
		$value = '';

		$pattern = '/INSERT INTO ' . $prefix . 'options VALUES\(\'\d+\',\'siteurl\',\'(.*?)\'/';

		$handle = fopen( $file, 'r' );
		if ( $handle ) {
			while ( ( $line = fgets( $handle ) ) !== false ) {
				if ( preg_match( $pattern, $line, $matches ) ) {
					$value = $matches[1];
					break;
				}
			}
			fclose( $handle );
		}

		return $value;
	}

	/**
	 * wp_options テーブルのエクスポート部分から home の値を取得
	 * @param string $file SQL ファイルのパス
	 * @param string $prefix テーブルのプレフィックス
	 * @return string home の値
	 */
	public static function getHome( $file, $prefix ) {
		$value = '';

		$pattern = '/INSERT INTO ' . $prefix . 'options VALUES\(\'\d+\',\'home\',\'(.*?)\'/';

		$handle = fopen( $file, 'r' );
		if ( $handle ) {
			while ( ( $line = fgets( $handle ) ) !== false ) {
				if ( preg_match( $pattern, $line, $matches ) ) {
					$value = $matches[1];
					break;
				}
			}
			fclose( $handle );
		}

		return $value;
	}

	/**
	 * 新規テーブルの作成
	 * @param string $file SQL ファイルのパス
	 */
	public static function createTables( $file, $prefix ) {
		global $wpdb;

		// ファイルから CREATE TABLE の行を取得
		$handle = fopen( $file, 'r' );
		if ( $handle ) {
			$create_table_flag = false;
			$skip_flag = false;
			$query = '';
			while ( ( $line = fgets( $handle ) ) !== false ) {
				// デフォルトテーブルの CREATE TABLE の行をスキップ
				if ( preg_match( '/CREATE TABLE `' . $prefix . '([a-zA-Z0-9_]+)`/', $line, $matches ) ) {
					if ( in_array( $prefix . $matches[1], self::$skip_table_array ) ) {
						$skip_flag = true;
					} else {
						$skip_flag = false;
					}
					$create_table_flag = true;
				}

				if ( $skip_flag && strpos( $line, ') ' ) === 0 ) {
					$skip_flag = false;
					$create_table_flag = false;
					continue;
				}

				if ( ! $skip_flag && $create_table_flag ) {
					$query .= $line;

					if ( strpos( $line, ') ' ) === 0 ) {
						try {
							// テーブル名を置換してクエリを実行
							$query = str_replace( 'CREATE TABLE `' . $prefix, 'CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix, $query );
							$wpdb->query( $query );
						} catch ( Exception $e ) {
							fclose( $handle );
							return;
						}
						$create_table_flag = false;
						$skip_flag = false;
						$query = '';
					}
				}
			}
			fclose( $handle );
		}
	}

	/**
	 * 既存テーブルの値を置換
	 * @param string $table_name テーブル名
	 * @param string $old_url 置換前の URL
	 * @param string $new_url 置換後の URL
	 * @return void
	 */
	public static function replaceTableValues( $table_name, $old_url, $new_url ) {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
		foreach ( $rows as $row ) {
			$replaced_row = array();
			foreach ( $row as $key => $value ) {
				$replaced_row[ $key ] = self::recursiveUnserializeReplace( $old_url, $new_url, $value );
			}
			$wpdb->update( $table_name, $replaced_row, $row );
		}
	}

	/**
	 * 再帰的にデータを置換
	 * @param string $from 置換前の文字列
	 * @param string $to 置換後の文字列
	 * @param mixed $data 置換対象のデータ
	 * @param bool $serialised データがシリアライズされているかどうか
	 * @return mixed 置換後のデータ
	 */
	public static function recursiveUnserializeReplace( $from, $to, $data, $serialised = false ) {

		try {
			if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
				$data = self::recursiveUnserializeReplace( $from, $to, $unserialized, true );
			} elseif ( is_array( $data ) ) {
				$_tmp = array( );
				foreach ( $data as $key => $value ) {
					$_tmp[ $key ] = self::recursiveUnserializeReplace( $from, $to, $value, false );
				}

				$data = $_tmp;
				unset( $_tmp );
			} else {
				if ( is_string( $data ) )
					$data = str_replace( $from, $to, $data );
			}

			if ( $serialised )
				return serialize( $data );

		} catch( Exception $error ) {
		}

		return $data;
	}

	/**
	 * 再帰的にディレクトリを削除する
	 * @param string $dir 削除するディレクトリのパス
	 * @return bool 成功した場合は true、失敗した場合は false
	 */
	public static function removeDirectory( $dir ) {
		// これより下位のディレクトリのみ対象とする
		$parent_dir = WP_CONTENT_DIR . '/vk-fullsite-installer/';

		// 親ディレクトリより下位のディレクトリのみ削除する
		if ( strpos( $dir, $parent_dir ) !== 0 && $dir != rtrim( $parent_dir, '/' ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			// ファイルかディレクトリかシンボリックリンクかによって処理を分ける
			if ( is_dir( $path ) ) {
				// ディレクトリなら再度同じ関数を呼び出す
				self::removeDirectory( $path );
			} else if ( is_file( $path ) || is_link( $path ) ) {
				// ファイルまたはシンボリックリンクなら削除
				unlink( $path );
			}
		}
		// 指定したディレクトリを削除
		return rmdir( $dir );
	}

	/**
	 * サイトコードに一致するデモサイトの license_type を求める
	 *
	 * @param array  $sites     サイト一覧（sites.json をデコードした配列）
	 * @param string $site_code 検索対象のサイトコード
	 * @return string 一致するサイトが見つかればその license_type、見つからなければ空文字
	 */
	private static function findLicenseTypeBySiteCode( $sites, $site_code ) {
		foreach ( $sites as $site ) {
			if ( isset( $site[ 'site_code' ] ) && $site_code === $site[ 'site_code' ] ) {
				return isset( $site[ 'license_type' ] ) ? $site[ 'license_type' ] : '';
			}
		}
		// 一致するサイトが無い場合は空文字を返す。
		// 空文字は license_types のどの一覧にも含まれないため、呼び出し側ではどのスロットも該当しない扱いになる
		return '';
	}

	/**
	 * Display the site list page.
	 */
	public static function displaySiteListPage() {

		// API から sites.json を取得
		$sites_api = apply_filters( 'vkfsi_sites_api_url', SITES_JSON_API_URL );

		// 自分（この画面）が対応しているライセンス区分の一覧を送る
		// 新しい区分のデモサイトが古いバージョンの画面に出てしまうのを防ぐため
		$sites_api = add_query_arg(
			'supported_license_types',
			implode( ',', array_keys( self::$license_type_name_array ) ),
			$sites_api
		);

		$response = wp_remote_get( $sites_api );
		$sites_json = wp_remote_retrieve_body( $response );

		// JSON デコード
		// json_last_error() は構文エラーしか見ないため、API が null や 123 のような
		// 有効なスカラー JSON を返した場合は $sites が配列にならない
		$sites = json_decode( $sites_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			echo '<div class="notice notice-error is-dismissible"><p>sites.json ファイルの読み込みに失敗しました。</p></div>';
			return;
		}

		// sites.json ファイルの内容をフィルタリング
		$sites = apply_filters( 'vkfsi_sites', $sites );

		// 配列かどうかの確認は、フィルタ適用の直後（ここ）で行う。
		// デコード直後で確認しても、その後の apply_filters( 'vkfsi_sites', ... ) が
		// 配列以外を返す経路までは塞げないため、フィルタが配列以外を返す場合も含めて
		// この1か所で確認している。この後 findLicenseTypeBySiteCode() や site-list.php で
		// foreach ( $sites as $site ) するため、$sites を使う直前のこの位置で確認することで、
		// デコード直後・フィルタ後のどちらの経路も、この1か所で確実に守れる
		if ( ! is_array( $sites ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>sites.json ファイルの読み込みに失敗しました。</p></div>';
			return;
		}

		// タイトル画像
		$titleImage = self::getSvgImageTag( __DIR__ . '/assets/images/admin.svg', 'VK FullSite Installer 設定' );

		// 入力されたライセンスキーを格納する配列（キーは self::$license_key_fields のスロット名）
		// ただし、認証が失敗した場合は空文字にセットする
		$license_keys = array();
		foreach ( self::$license_key_fields as $slot => $license_key_field ) {
			$license_keys[ $slot ] = '';
		}

		// 処理対象のサイトコード
		$site_code = '';

		// データダウンロード URL
		// 認証が通れば、認証サーバーが返してくる
		$data_url = '';

		// 選択中のサイトの license_type。
		// A の対応: 画面に出ていない（＝この区分の license_types に該当しない）スロットを
		// $_POST から読んだり認証結果で操作したりしないよう、対象を絞り込むために使う。
		// サイトコードがどのサイトにも一致しない場合は空文字のままとなり、
		// 空文字は license_types のどの一覧にも含まれないため、どのスロットも該当しない扱いになる
		$selected_license_type = '';

		// 各ライセンスキー用のエラーフラグ（キーは self::$license_key_fields のスロット名）
		// true なら入力値に問題があるので、エラーメッセージを表示する
		$error_flags = array();
		foreach ( self::$license_key_fields as $slot => $license_key_field ) {
			$error_flags[ $slot ] = false;
		}

		// いずれかの「保存」ボタンが押されていれば、ライセンス認証を行う
		$save_button_pressed = false;
		foreach ( self::$license_key_fields as $slot => $license_key_field ) {
			if ( isset( $_POST[ $license_key_field[ 'save_button' ] ] ) ) {
				$save_button_pressed = true;
				break;
			}
		}

		if ( $save_button_pressed ) {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to do this action.', 'default' ) );
			}
			if ( ! isset( $_POST[ 'vkfsi_license_nonce' ] ) || ! wp_verify_nonce( $_POST[ 'vkfsi_license_nonce' ], 'vkfsi_license_action' ) ) {
				wp_die( esc_html__( 'Invalid request.', 'default' ) );
			}

			// サイトコードの取得
			$site_code = isset( $_POST[ 'vkfsi_code' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'vkfsi_code' ] ) ) : '';

			// 選択中のサイトの区分を、一覧取得済みの $sites から引く
			$selected_license_type = self::findLicenseTypeBySiteCode( $sites, $site_code );

			// R5 の対応: 画面の描画と送信の間に一覧から該当サイトが消えた等で、
			// サイトコードがどのサイトにも一致しない場合（$selected_license_type が空文字のまま）。
			// この場合は入力したキーがすべて意味を持たないため、認証 API を呼ばずに通知だけ出す。
			// return はせず、後続の一覧表示は行う（利用者が別のサイトを選び直せるようにするため）
			if ( '' === $selected_license_type ) {
				echo '<div class="notice notice-error is-dismissible"><p>対象のデモサイトが見つかりませんでした。下の一覧から選び直してください。</p></div>';
			} else {

				// ライセンス認証 URL
				$license_check_url = apply_filters( 'vkfsi_license_check_url', LICENSE_CHECK_API_URL );

				// 認証クラスの初期化
				$license_checker = LicenseChecker::getInstance();
				$license_checker->setApiUrl( $license_check_url );
				$license_checker->setSiteCode( $site_code );

				// 各ライセンスキーの入力値を取得し、認証クラスへセットする
				foreach ( self::$license_key_fields as $slot => $license_key_field ) {
					// 選択中のサイトの区分に、このスロットが該当するか
					$is_applicable = in_array( $selected_license_type, $license_key_field[ 'license_types' ], true );

					// 該当するスロットだけ $_POST から読む。
					// 該当しない（＝画面に出ていない）入力欄に POST で値を足されても、認証対象にしない
					if ( $is_applicable && isset( $_POST[ $license_key_field[ 'field' ] ] ) ) {
						$license_keys[ $slot ] = sanitize_text_field( wp_unslash( $_POST[ $license_key_field[ 'field' ] ] ) );
					}

					// B の対応: send_always なスロット（既存の passport / site）は常に送り、
					// それ以外（booking_manager_pro 等）は選択中の区分に該当するときだけ送る
					if ( $license_key_field[ 'send_always' ] || $is_applicable ) {
						$license_checker->setLicenseKey( $license_key_field[ 'api_param' ], $license_keys[ $slot ] );
					}
				}

				// 認証処理
				$result = $license_checker->getData();

				// 認証結果が返ってきた場合
				if ( $result ) {
					// 認証結果が失敗した場合、ライセンスキーはすべてクリアする
					if ( 'fail' == $result[ 'status' ] ) {
						foreach ( self::$license_key_fields as $slot => $license_key_field ) {
							$license_keys[ $slot ] = '';
						}

					// サイトライセンスキーだけ認証成功の場合、製品ライセンスキーをクリアする
					// （選択中のサイトの区分に該当するスロットのみ対象。該当しないスロットはそもそも空文字のまま）
					} else if ( 'success_site' == $result[ 'status' ] ) {
						foreach ( self::$license_key_fields as $slot => $license_key_field ) {
							if ( 'product' === $license_key_field[ 'kind' ]
								&& in_array( $selected_license_type, $license_key_field[ 'license_types' ], true ) ) {
								$license_keys[ $slot ] = '';
							}
						}

					// 製品ライセンスキー（Vektor Passport または VK Booking Manager Pro 等）だけ
					// 認証成功の場合、サイトライセンスキーをクリアする
					// （選択中のサイトの区分に該当するスロットのみ対象）
					} else if ( in_array( $result[ 'status' ], array( 'success_passport', 'success_product' ), true ) ) {
						foreach ( self::$license_key_fields as $slot => $license_key_field ) {
							if ( 'site' === $license_key_field[ 'kind' ]
								&& in_array( $selected_license_type, $license_key_field[ 'license_types' ], true ) ) {
								$license_keys[ $slot ] = '';
							}
						}

						// R4 の対応: status（success_passport / success_product）は製品同士を区別しない。
						// 選択中の区分に該当する kind = 'product' のスロットを数え、
						// 2つ以上あればどちらが認証を通ったのか判断できないため、
						// 未認証のキーを残すより安全側に倒して該当する製品キーもまとめてクリアする。
						// 該当が1つだけなら、上の分岐と合わせて従来どおり
						// 「製品キーは残し、サイト系だけクリア」の挙動になる
						$applicable_product_slots = array();
						foreach ( self::$license_key_fields as $slot => $license_key_field ) {
							if ( 'product' === $license_key_field[ 'kind' ]
								&& in_array( $selected_license_type, $license_key_field[ 'license_types' ], true ) ) {
								$applicable_product_slots[] = $slot;
							}
						}
						if ( count( $applicable_product_slots ) >= 2 ) {
							foreach ( $applicable_product_slots as $slot ) {
								$license_keys[ $slot ] = '';
							}
						}
					}

					// データダウンロード URL
					// 認証不可なら空文字が入ってくる
					$data_url = $result[ 'data_url' ];

				// 認証結果が返ってこない場合
				} else {
					// 入力値はすべてクリアする
					foreach ( self::$license_key_fields as $slot => $license_key_field ) {
						$license_keys[ $slot ] = '';
					}
					$data_url = '';
				}
			}
		}

		// ライセンスキーが認証エラーで空文字にされた場合
		// メッセージ通知用にエラーフラグを立てる（選択中のサイトの区分に該当するスロットのみ対象）
		foreach ( self::$license_key_fields as $slot => $license_key_field ) {
			if ( ! in_array( $selected_license_type, $license_key_field[ 'license_types' ], true ) ) {
				continue;
			}
			if ( isset( $_POST[ $license_key_field[ 'field' ] ] ) && ! empty( $_POST[ $license_key_field[ 'field' ] ] ) ) {
				if ( '' == $license_keys[ $slot ] ) {
					$error_flags[ $slot ] = true;
				}
			}
		}

		// 管理画面のインポートページを表示
		require_once __DIR__ . '/views/site-list.php';
	}

	/**
	 * Get the SVG image tag.
	 */
	private static function getSvgImageTag( $file, $title ) {
		$image = file_get_contents( $file );
		$encodedImage = base64_encode( $image );
		return '<img src="data:' . 'image/svg+xml' . ';base64,' . $encodedImage . '" alt="' . esc_attr( $title ) . '">';
	}
}