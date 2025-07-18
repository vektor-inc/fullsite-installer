<?php
/**
 * VK FullSite Installer インポート完了ページ
 */

////////// 表示処理 //////////

// スタイルシートの読み込み
echo '<style>';
echo file_get_contents( __DIR__ . '/../assets/css/style.css' );
echo '</style>';

?>
<!-- 管理画面の "VK FullSite Installer 設定" を非表示にする -->
<script>
	jQuery( function( $ ) {
		$( 'li.current' ).hide();
	});
</script>
<!-- インポートフォーム -->
<div class="wrap vkfsi_admin-page">
	<h1 class="vkfsi_logo"><?php echo $titleImage; ?></h1>
	<h2>インポート完了</h2>
</div>

<!-- インポート完了メッセージを表示 -->
<div class="notice notice-success is-dismissible"><p>インポートが完了しました。</p></div>
