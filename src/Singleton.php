<?php

namespace VektorInc\FullSiteInstaller;

/**
 * Singleton trait
 * use Singleton を宣言することでシングルトンクラスとなる
 */
trait Singleton {

	static $instances = array();

	/**
	 * コンストラクタ
	 */
	final protected function __construct() {
		if ( isset( self::$instances[ get_called_class() ] ) ) {
			throw new \Exception( "You can't clone this instance." );
		}
	}

	/**
	 * インスタンスの取得（継承にも対応するためサブクラス毎にインスタンスを保存）
	 *
	 * @return void
	 */
    public static function getInstance() {
        $subclass = static::class;
        if ( ! isset( self::$instances[ $subclass ] ) ) {
            self::$instances[ $subclass ] = new static();
            if ( method_exists( self::$instances[ $subclass ], 'initialize' ) ) {
                self::$instances[ $subclass ]->initialize();
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
		throw new \RuntimeException( "You can't clone this instance." );
	}

	/**
	 * 単一インスタンスを保証するためのシリアライズの防止
	 *
	 * @return void
	 */
	final public function __wakeup(): void {
		throw new \RuntimeException( "You can't unserialize this instance." );
	}
}
