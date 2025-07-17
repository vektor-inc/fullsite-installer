<?php
/**
 * Full Site Installer
 *
 * @package vektor-inc/full-site-installer
 * @license GPL-2.0+
 *
 * @version 0.0.5
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

// sites.json の license_type の種類
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_FREE', 'free' );
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT', 'passport' );
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_SITE', 'site' );
define( 'VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE', 'passport_and_site' );

use VektorInc\FullSiteInstaller\FullSiteInstallerLicenseChecker;

/**
 * FullSiteInstaller クラス
 *
 * このクラスは、VK FullSite Installer プラグインのメイン機能を提供します。
 * サイトのインポート、設定画面の表示、ライセンス認証などを行います。
 */
class FullSiteInstaller {

	public static $version = '0.0.5';

	/**
	 * ライセンスの種類を定義
	 */
	public static $license_type_name_array = [
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_FREE => '無料',
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT => 'Vektor Passport',
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_SITE => 'サイトライセンス',
		VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE => 'Vektor Passport + サイトライセンス',
	];

	// 変更しないテーブルの配列
	public static $skip_table_array = array(
		'wp_users',
		'wp_usermeta',
	);

	/**
	 * Initialize the plugin.
	 */
	public static function init() {
		// 管理画面にメニューを追加
		add_action( 'admin_menu', array( __CLASS__, 'addAdminMenu' ) );
	}

	/**
	 * Add the admin menu for the plugin.
	 *
	 * This function adds a new options page to the WordPress admin menu
	 * where users can access the VK FullSite Installer settings.
	 */
	public static function addAdminMenu() {
		add_options_page( 'VK FullSite Installer 設定', 'VK FullSite Installer', 'manage_options', 'vk-fullsite-installer', array( __CLASS__, 'execute' ) );
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

		// 管理画面のインポートページを表示
		require_once __DIR__ . '/views/import-end-page.php';

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
				$table = str_replace( 'wp_', $wpdb->prefix, $table );
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
		$activate_plugins = array( 'vk-fullsite-installer' );
		foreach ( $activate_plugins as $plugin ) {
			activate_plugins( $plugin . '/' . $plugin . '.php' );
		}

		// ZIP ファイル用のディレクトリを削除
		self::removeDirectory( $import_dir );

		// Vektor Passport ライセンスキーの保存
		if ( ! empty( $_POST[ 'vkfsi_license_key_vektor_passport' ] ) ) {
			$license_key_passport = sanitize_text_field( $_POST[ 'vkfsi_license_key_vektor_passport' ] );

			// Lightning G3 Pro Unit のライセンスキーを保存
			update_option( 'lightning-g3-pro-unit-license-key', $license_key_passport );

			// VK AB Testing のライセンスキーを保存
			update_option( 'vk_ab_testing_license_key', $license_key_passport );

			// VK Blocks Pro のライセンスキーを保存
			$options = get_option( 'vk_blocks_options' );
			if ( ! is_array( $options ) ) {
				$options = array();
			}
			$options[ 'vk_blocks_pro_license_key' ] = $license_key_passport;
			update_option( 'vk_blocks_options', $options );
		}

		// サイトデータのカウントアップ
		$site_code = $_POST[ 'vkfsi_code' ];
		$api_url = SITES_COUNTER_API_URL . '?code=' . $site_code;
		$response = wp_remote_get( $api_url );

		// インポートが完了したら、リダイレクトして完了ページを表示
		wp_redirect( admin_url( 'options-general.php?page=vk-fullsite-installer&imported=true' ) );
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
	 * Display the site list page.
	 */
	public static function displaySiteListPage() {

		// API から sites.json を取得
		$sites_json = file_get_contents( SITES_JSON_API_URL );

		// JSON デコード
		$sites = json_decode( $sites_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			echo '<div class="notice notice-error is-dismissible"><p>sites.json ファイルの読み込みに失敗しました。</p></div>';
			return;
		}

		// sites.json ファイルの内容をフィルタリング
		$sites = apply_filters( 'vkfsi_sites', $sites );

		// タイトル画像
		$titleImage = self::getSvgImageTag( __DIR__ . '/assets/images/admin.svg', 'VK FullSite Installer 設定' );

		// 入力されたライセンスキーを格納する変数
		// ただし、認証が失敗した場合は空文字にセットする
		$license_key_passport = '';
		$license_key_site = '';

		// 処理対象のサイトコード
		$site_code = '';

		// データダウンロード URL
		// 認証が通れば、認証サーバーが返してくる
		$data_url = '';

		// Vektor Passport ライセンスキーとサイトライセンスキー用のエラーフラグ
		// true なら入力値に問題があるので、エラーメッセージを表示する
		$error_flag_passport = false;
		$error_flag_site = false;

		// 「保存」ボタンが押されていれば、ライセンス認証を行う
		if ( isset( $_POST[ 'save_license_key_vektor_passport' ] ) || isset( $_POST[ 'save_license_key_site' ] ) ) {
			// サイトコードの取得
			$site_code = sanitize_text_field( $_POST[ 'vkfsi_code' ] );

			// Vektor Passport ライセンスキーの取得
			$license_key_passport = '';
			if ( isset( $_POST[ 'license_key_vektor_passport' ] ) ) {
				$license_key_passport = sanitize_text_field( $_POST[ 'license_key_vektor_passport' ] );
			}

			// サイトライセンスキーの取得
			$license_key_site = '';
			if ( isset( $_POST[ 'license_key_site' ] ) ) {
				$license_key_site = sanitize_text_field( $_POST[ 'license_key_site' ] );
			}

			// ライセンス認証 URL
			$license_check_url = LICENSE_CHECK_API_URL;
			$license_check_url = apply_filters( 'vkfsi_license_check_url', $license_check_url );

			// 認証クラスの初期化
			$license_checker = FullSiteInstallerLicenseChecker::get_instance();
			$license_checker->set_api_url( $license_check_url );
			$license_checker->set_site_code( $site_code );
			$license_checker->set_passport_license_key( $license_key_passport );
			$license_checker->set_site_license_key( $license_key_site );

			// 認証処理
			$result = $license_checker->get_data();

			// 認証結果が返ってきた場合
			if ( $result ) {
				// 認証結果が失敗した場合
				if ( 'fail' == $result[ 'status' ] ) {
					// ライセンスキーは２つともクリアする
					$license_key_passport = '';
					$license_key_site = '';

				// サイトライセンスキーだけ認証成功の場合
				} else if ( 'success_site' == $result[ 'status' ] ) {
					// Vektor Passport ライセンスキーはクリアする
					$license_key_passport = '';

				// Vektor Passport ライセンスキーだけ認証成功の場合
				} else if ( 'success_passport' == $result[ 'status' ] ) {
					// サイトライセンスキーはクリアする
					$license_key_site = '';
				}

				// データダウンロード URL
				// 認証不可なら空文字が入ってくる
				$data_url = $result[ 'data_url' ];

			// 認証結果が返ってこない場合
			} else {
				// 入力値はクリアする
				$license_key_passport = '';
				$license_key_site = '';
				$data_url = '';
			}
		}

		// Vektor Passport ライセンスキーが認証エラーで空文字にされた場合
		// メッセージ通知用にエラーフラグを立てる
		if ( isset( $_POST[ 'license_key_vektor_passport' ] ) && ! empty( $_POST[ 'license_key_vektor_passport' ] ) ) {
			if ( '' == $license_key_passport ) {
				$error_flag_passport = true;
			}
		}

		// サイトライセンスキーが認証エラーで空文字にされた場合
		// メッセージ通知用にエラーフラグを立てる
		if ( isset( $_POST[ 'license_key_site' ]) && ! empty( $_POST[ 'license_key_site' ] ) ) {
			if ( '' == $license_key_site ) {
				$error_flag_site = true;
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