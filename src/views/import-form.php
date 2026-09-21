<?php

// カレントユーザーの情報を取得
$current_user = wp_get_current_user();

// ユーザーのリストを取得
$users = get_users( array(
	'fields' => array( 'ID', 'user_login' ),
) );

$user_select_form = '<select name="content_user_id">';
foreach ( $users as $user ) {
	$selected = '';
	if ( $user->ID == $current_user->ID ) {
		$selected = 'selected';
	}
	$user_select_form .= '<option value="' . esc_attr( $user->ID ) . '" ' . $selected . '>';
	$user_select_form .= esc_html( $user->user_login );
	$user_select_form .= '</option>';
}
$user_select_form .= '</select>';

////////// 表示処理 //////////

// スタイルシートの読み込み
echo '<style>';
echo file_get_contents( __DIR__ . '/../assets/css/style.css' );
echo '</style>';

?>
<!-- ローディング画面 -->
<div class="vkfsi_loading" style="display: none;">
	<p class="vkfsi_loading-text">Loading...</p>
	<div class="vkfsi_spinner"></div>
</div>
<!-- 入力チェック -->
<script>
	jQuery( function( $ ) {
		$( '.vkfsi_start-import' ).on( 'click', function() {
			// 入力値のチェック
			if ( ! $( '#vkfsi_confirm_passport' ).prop( 'checked' ) ||
				 ! $( '#vkfsi_confirm_database' ).prop( 'checked' ) ||
				 ! $( '#vkfsi_confirm_maintenance' ).prop( 'checked' ) ) {
				alert( 'チェックボックスをオンにしてください。' );
				return false;
			}

			$( '.vkfsi_loading' ).show();
		});
	});
</script>

<!-- インポートフォーム -->
<div class="wrap vkfsi_admin-page">
	<h1 class="vkfsi_logo"><?php echo $titleImage; ?></h1>
	<h2>インポート設定</h2>
	<form method="post" action="">
		<?php wp_nonce_field( 'vkfsi_start_import', 'vkfsi_import_nonce' ); ?>
		<input type="hidden" name="vkfsi_code" value="<?php echo esc_attr( isset( $_POST[ 'vkfsi_code' ] ) ? wp_unslash( $_POST[ 'vkfsi_code' ] ) : '' ); ?>">
		<input type="hidden" name="vkfsi_data_url" value="<?php echo esc_url( isset( $_POST[ 'vkfsi_data_url' ] ) ? wp_unslash( $_POST[ 'vkfsi_data_url' ] ) : '' ); ?>">
		<?php
		// 各製品のライセンスキーを、次のインポート処理（Installer::importSite()）へ引き継ぐための隠しフィールド。
		// self::$license_key_fields のうち、インポート後に保存先（key_options）を持つ区分のみ引き継ぐ
		// （サイトライセンスキーのようにインポート後の保存先が無い区分は不要）。
		// 隠しフィールド名は 'vkfsi_' . field の規則で決める。
		// 既存の Vektor Passport 用フィールド名 vkfsi_license_key_vektor_passport は
		// この規則のまま維持しており、保存済みの挙動を変えていない。
		//
		// R3 の対応（据え置き）: このループは保存先（key_options）を持つスロットを全部回すだけで、
		// 選択中サイトの区分（license_type）で絞っていない。
		// 保存処理側（Installer::displaySiteListPage()）は選択中の区分に該当するスロットのみを
		// 対象にしているため、「どのスロットを扱うか」の判断がこのファイルと保存処理側の2箇所に
		// 分かれている。ここを絞るには一覧データ（sites.json）の再取得が必要になり、外部リクエストが
		// 1回増えるため、この PR では絞っていない（main でも同様に $_POST を素通しする設計であり、
		// 新規に開いた穴ではない）。
		// 将来どちらか片方だけを直すと2箇所の判断基準がずれる事故につながるため、
		// 修正する際は必ず両方をあわせて見直すこと。
		foreach ( self::$license_key_fields as $slot => $license_key_field ) {
			if ( empty( $license_key_field[ 'key_options' ] ) ) {
				continue;
			}
			$license_key_value = isset( $_POST[ $license_key_field[ 'field' ] ] )
				? sanitize_text_field( wp_unslash( $_POST[ $license_key_field[ 'field' ] ] ) )
				: '';
			$hidden_field_name = 'vkfsi_' . $license_key_field[ 'field' ];
			echo '<input type="hidden" name="' . esc_attr( $hidden_field_name ) . '" value="' . esc_attr( $license_key_value ) . '">';
		}
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="content_user_id">コンテンツの所有者 <span class="description">(必須)</span></label></th>
				<td><?php echo $user_select_form; ?></td>
			</tr>
		</table>
		<p>
			<label>
				<input name="confirm_import" type="checkbox" id="vkfsi_confirm_passport" value="yes">
				<span class="text-danger"><?php echo esc_html( __( 'このセットアップは新規サイト構築専用です。インポートすると現在のサイトのデータは完全に消失します。', 'fullsite-installer' ) ); ?></span>
			</label>
		</p>
		<p>
			<label>
				<input name="confirm_import_database" type="checkbox" id="vkfsi_confirm_database" value="yes">
				<?php echo esc_html( __( '「データベースの更新が必要です」と表示される場合があります。表示された場合は「WordPressデータベースを更新」をクリックして手続きを進めてください。', 'fullsite-installer' ) ); ?>
			</label>
		</p>
		<p>
			<label>
				<input name="confirm_import_maintenance" type="checkbox" id="vkfsi_confirm_maintenance" value="yes">
				<?php echo esc_html( __( 'インポートした後で「Briefly unavailable for scheduled maintenance. Check back in a minute.」と表示された場合はプラグインなどの自動更新が実行中です。しばらくしてから再度管理画面にアクセスしてください。', 'fullsite-installer' ) ); ?>
			</label>
		</p>
		<?php submit_button( 'インポート開始', 'primary vkfsi_start-import', 'start_import' ); ?>
	</form>
</div>
