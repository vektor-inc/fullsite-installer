<?php
/**
 * Full Site Installer
 *
 * @package vektor-inc/full-site-installer
 * @license GPL-2.0+
 *
 * @version 0.0.2
 */

namespace VektorInc\FullSiteInstaller;

class FullSiteInstaller {

	public static $version = '0.0.2';

    /**
     * Initialize the Full Site Installer plugin.
     */
	public static function init() {
        // 管理者でないならば、ダッシュボードにリダイレクトする
        if ( ! is_admin() ) {
            wp_redirect( admin_url() );
            exit;
        }
	}
}