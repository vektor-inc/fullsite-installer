<?php
/**
 * VK FullSite Installer インポート完了ページ
 */
?>
<!-- 管理画面の "VK FullSite Installer 設定" を非表示にする -->
<script>
	jQuery( function( $ ) {
		$( 'li.current' ).hide();
	});
</script>
<!-- インポート完了メッセージを表示 -->
<div class="notice notice-success is-dismissible"><p>インポートが完了しました。</p></div>
