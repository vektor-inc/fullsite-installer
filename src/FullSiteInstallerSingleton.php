<?php

namespace VektorInc\FullSiteInstaller;

/**
 * Singleton trait
 * use FullSiteInstallerSingleton を宣言することでシングルトンクラスとなる
 */
trait FullSiteInstallerSingleton {

	static $instances = array();

	/**
	 * コンストラクタ
	 */
	final protected function __construct() {
		if ( isset( self::$instance[ get_called_class() ] ) ) {
			throw new Exception( "You can't clone this instance." );
		}
	}

	/**
	 * インスタンスの取得（継承にも対応するためサブクラス毎にインスタンスを保存）
	 *
	 * @return void
	 */
    public static function get_instance() {
        $subclass = static::class;
        if ( ! isset( self::$instances[ $subclass ] ) ) {
            self::$instances[ $subclass ] = new static();
            if ( method_exists(self::$instances[$subclass], 'initialize') ) {
                self::$instances[$subclass]->initialize();
            }
        }
        return self::$instances[ $subclass ];
    }

	/**
	 * 単一インスタンスを保証するためのクローンの防止
	 *
	 * @return void
	 */
	function __clone() {
		throw new RuntimeException( "You can't clone this instance." );
	}
}
