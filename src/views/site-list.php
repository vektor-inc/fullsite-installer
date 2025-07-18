<?php

////////// 関数定義 //////////

/**
 * サイト一覧の表示フィルタリング（検索条件の適用）
 * @param array $site サイト情報
 * @return bool true: 検索条件にマッチする、false: 検索条件にマッチしない
 */
function vkfsi_search_filter( $site ) {
	if ( ! isset( $_POST[ 's-search' ] ) ) {
		return true;
	}

	// 言語
	if ( isset( $_POST[ 's-language' ] ) ) {
		if ( $site[ 'language' ] != $_POST[ 's-language' ] ) {
			return false;
		}
	}

	// テーマ
	if ( isset( $_POST[ 's-theme' ] ) ) {
		$array = $_POST[ 's-theme' ];
		if ( ! in_array( $site[ 'theme' ], $array ) ) {
			return false;
		}
	}

	// テーマタイプ
	if ( isset( $_POST[ 's-theme-type' ] ) && ! empty( $_POST[ 's-theme-type' ] ) ) {
		if ( $site[ 'theme_type' ] != $_POST[ 's-theme-type' ] ) {
			return false;
		}
	}

	// ライセンス区分
	if ( isset( $_POST[ 's-license-type' ] ) ) {
		$array = $_POST[ 's-license-type' ];
		if ( ! in_array( $site[ 'license_type' ], $array ) ) {
			return false;
		}
	}

	// Author
	if ( isset( $_POST[ 's-author' ] ) ) {
		$array = $_POST[ 's-author' ];
		if ( ! in_array( $site[ 'author' ], $array ) ) {
			return false;
		}
	}

	// キーワード
	if ( isset( $_POST[ 's-keyword' ] ) ) {
		$input_keyword = sanitize_text_field( $_POST[ 's-keyword' ] );
		$input_keyword = str_replace( '　', ' ', $input_keyword );
		$keyword_array = explode( ' ', $input_keyword );
		$match_counter = 0;
		foreach ( $keyword_array as $keyword ) {
			if ( strpos( $site[ 'site_name' ], $keyword ) !== false
				|| strpos( $site[ 'description' ], $keyword ) !== false ) {
				$match_counter++;
			}
		}
		if ( $match_counter != count( $keyword_array ) ) {
			return false;
		}
	}

	return true;
}

/**
 * 販売価格情報
 *
 * @param array $site
 *
 * @return array $price_data
 */
function vkfst_get_display_price_data( $site ) {
	$price_normal   = isset( $site['shop-item']['price'] ) ? $site['shop-item']['price'] : '';
	$price_discount = isset( $site['shop-item']['price_discount'] ) ? $site['shop-item']['price_discount'] : '';

	$price_data = array(
		'display_mode'   => '',
		'price_deleted'  => '',
		'price_discount' => '',
		'price_normal'   => '',
	);

	// 無料（ 0円入力のある場合は特別に0円になっている ）
	if ( empty( $price_normal ) && empty( $price_discount ) && '0' !== $price_discount ) {
		$price_data['display_mode'] = 'free';
	} elseif ( ! empty( $price_discount ) ) {
		$price_data['display_mode']   = 'discount';
		$price_data['price_deleted']  = number_format( vkfst_string_to_number( $price_normal ) );
		$price_data['price_discount'] = number_format( vkfst_string_to_number( $price_discount ) );
	} elseif ( '0' === $price_discount ) {
		$price_data['display_mode']   = 'discount_free';
		// 割引で 0 円にしているが通常価格が未記入の場合
		if ( $price_normal ){
			$price_data['price_deleted']  = number_format( vkfst_string_to_number( $price_normal ) );
		}
		$price_data['price_discount']  = number_format( vkfst_string_to_number( $price_discount ) );
	} elseif ( empty( $price_discount ) && ! empty( $price_normal ) ) {
		$price_data['display_mode']   = 'normal';
		$price_data['price_normal']  = number_format( vkfst_string_to_number( $price_normal ) );
	} else {
		$price_data['display_mode'] = 'normal';
		$price_data['price_normal'] = number_format( vkfst_string_to_number( $price_normal ) );
	}

	return $price_data;
}

function vkfst_get_display_price_html( $price_data ) {
	$price_html = '';

	// 打ち消し価格
	if ( ! empty( $price_data['price_deleted'] ) ){
		$price_html .= '<span class="vkfsi_price_del">¥' . number_format( vkfst_string_to_number( $price_data['price_deleted'] ) ) . '円<span class="vkfsi_price_tax">(税込)</span></span>';
	}

	// 割引価格
	if ( isset( $price_data['price_discount'] ) && '' !== $price_data['price_discount'] ){
		$price_html .= '<span class="vkfsi_price vkfsi_price_discount">¥<span class="vkfsi_price_number">' . number_format( vkfst_string_to_number( $price_data['price_discount'] ) ) . '</span>円<span class="vkfsi_price_tax">(税込)</span></span>';
	}

	// 通常価格
	if ( isset( $price_data['price_normal'] ) && '' !== $price_data['price_normal'] ){
		$price_html .= '<span class="vkfsi_price vkfsi_price_no_discount">¥<span class="vkfsi_price_number">' . number_format( vkfst_string_to_number(  $price_data['price_normal'] ) ) . '</span>円<span class="vkfsi_price_tax">(税込)</span></span>';
	}

	// 無料
	if ( isset( $price_data['display_mode'] ) && 'free' === $price_data['display_mode'] ){
		$price_html .= '<span class="vkfsi_price vkfsi_price_no_discount">無料</span>';
	}

	return $price_html;
}

function vkfst_string_to_number( $number_string ){
	$number = (int) str_replace( ',', '', $number_string );
	return $number;
}

////////// 表示処理 //////////

// スタイルシートの読み込み
echo '<style>';
echo file_get_contents( __DIR__ . '/../assets/css/style.css' );
echo '</style>';

// サイト一覧画面ヘッダー
echo '<div class="wrap vkfsi_admin-page">';
echo '<h1 class="vkfsi_logo">';
echo $titleImage;
echo '</h1>';

// 設定追加用のアクションフック
do_action( 'vkfsi_add_settings' );

// 検索フォーム用に各値を取得
$search_language_array = []; // 言語の配列
$search_theme_array = []; // テーマ名の配列
$search_theme_type_array = []; // テーマタイプの配列
$search_license_type_array = []; // ライセンス区分の配列
$search_author_array = []; // Author の配列
foreach ( $sites as $site ) {
	$search_theme_array[] = $site[ 'theme' ];
	$search_theme_type_array[] = $site[ 'theme_type' ];
	$search_language_array[] = $site[ 'language' ];
	$search_license_type_array[] = $site[ 'license_type' ];
	$search_author_array[] = $site[ 'author' ];
}

// array_unique で重複を削除
$search_theme_array = array_unique( $search_theme_array );
$search_theme_type_array = array_unique( $search_theme_type_array );
$search_language_array = array_unique( $search_language_array );
$search_license_type_array = array_unique( $search_license_type_array );
$search_author_array = array_unique( $search_author_array );


// 検索フォーム
echo '<div class="vkfsi_search-form">';
echo '<form method="post" action="">';
echo '<input type="hidden" name="s-search" value="on">';
echo '<h3>サイト検索</h3>';
echo '<div class="vkfsi_search-content">';

// デフォルトの言語選択肢
$default_language = '';
if ( isset( $_POST[ 's-language' ] ) ) {
    $default_language = sanitize_text_field( $_POST[ 's-language' ] );
} else {
    $locale = get_locale();
    if ( $locale != 'ja' ) {
        $locale = 'en';
    }
    $default_language = $locale;
}

/********** 言語の検索フォーム
// 言語の種類
$language_name_array = [
    'ja' => '日本語',
    'en' => '英語',
];

// 検索フォーム - 言語
echo '<div class="vkfsi_search-item">';
echo '<strong>言語</strong>';
echo '<ul class="vkfsi_input-wrap">';
echo '<select name="s-language" id="s-language">';

foreach ( $language_name_array as $language => $language_name ) {
	$selected = '';
	if ( $language == $default_language ) {
		$selected = 'selected';
	}
	echo '<option value="' . esc_attr( $language ) . '" ' . $selected . '>';
	echo esc_html( $language_name );
	echo '</option>';
}
echo '</select>';
echo '</ul>';
echo '</div>';
*/

// 検索フォーム - テーマ
echo '<div class="vkfsi_search-item">';
echo '<strong>テーマ</strong>';
echo '<ul class="vkfsi_input-wrap">';
foreach ( $search_theme_array as $theme ) {
    $checked = '';
    if ( isset( $_POST[ 's-theme' ] ) && in_array( $theme, $_POST[ 's-theme' ] ) ) {
        $checked = 'checked';
    }
    echo '<li><label>';
    echo '<input type="checkbox" name="s-theme[]" value="' . esc_attr( $theme ) . '" ' . $checked . '>';
    echo esc_html( $theme );
    echo '</label></li>';
}
echo '</ul>';
echo '</div>';

// 検索フォーム - テーマタイプ
echo '<div class="vkfsi_search-item">';
echo '<strong>テーマタイプ</strong>';
echo '<ul class="vkfsi_input-wrap">';
echo '<select name="s-theme-type" id="s-theme-type">';
echo '<option value="">指定なし</option>';
foreach ( $search_theme_type_array as $theme_type ) {
    $selected = '';
    if ( isset( $_POST[ 's-theme-type' ] ) && $theme_type == $_POST[ 's-theme-type' ] ) {
        $selected = 'selected';
    }
    echo '<option value="' . esc_attr( $theme_type ) . '" ' . $selected . '>';
    echo esc_html( $theme_type );
    echo '</option>';
}
echo '</select>';
echo '</ul>';
echo '</div>';

// 検索フォーム - ライセンス区分
echo '<div class="vkfsi_search-item">';
echo '<strong>ライセンス区分</strong>';
echo '<ul class="vkfsi_input-wrap">';
global $license_type_name_array;
foreach ( self::$license_type_name_array as $license_type => $license_name ) {
    $checked = '';
    if ( isset( $_POST[ 's-license-type' ] ) && in_array( $license_type, $_POST[ 's-license-type' ] ) ) {
        $checked = 'checked';
    }
    echo '<li><label>';
    echo '<input type="checkbox" name="s-license-type[]" value="' . esc_attr( $license_type ) . '" ' . $checked . '>';
    echo esc_html( $license_name );
    echo '</label></li>';
}
echo '</ul>';
echo '</div>';

// 検索フォーム - Author
echo '<div class="vkfsi_search-item">';
echo '<strong>Author</strong>';
echo '<ul class="vkfsi_input-wrap">';
foreach ( $search_author_array as $author ) {
    $checked = '';
    if ( isset( $_POST[ 's-author' ] ) && in_array( $author, $_POST[ 's-author' ] ) ) {
        $checked = 'checked';
    }
    echo '<li><label>';
    echo '<input type="checkbox" name="s-author[]" value="' . esc_attr( $author ) . '" ' . $checked . '>';
    echo esc_html( $author );
    echo '</label></li>';
}
echo '</ul>';
echo '</div>';

// 検索フォーム - キーワード
echo '<div class="vkfsi_search-item">';
$keyword = '';
if ( isset( $_POST[ 's-keyword' ] ) ) {
    $keyword = sanitize_text_field( $_POST[ 's-keyword' ] );
}
echo '<strong>キーワード</strong>';
echo '<div class="vkfsi_input-wrap">';
echo '<input type="text" name="s-keyword" value="' . $keyword . '">';
echo '</div>';
echo '</div>';
echo '</div>'; // vkfsi_search-content

// 検索フォーム - 検索ボタン
echo '<input type="submit" value="検索" class="button button-primary">';

echo '</form>';
echo '</div>'; // vkfsi_search-form

// 指定された site_code があれば、そのサイトまでスクロールさせる
$vkfsi_code = isset( $_POST[ 'vkfsi_code' ] ) ? $_POST[ 'vkfsi_code' ] : '';
?>
<script>
    jQuery( function( $ ) {
        var site_code = '<?php echo esc_js( $vkfsi_code ); ?>';
        if ( site_code != '' ) {
            var target = $( '#div-' + site_code );
            if ( target.length ) {
                var position = target.offset().top;
                $( 'html, body' ).animate( { scrollTop: position }, 500 );
            }
        }
    });
</script>
<?php

// 検索条件にマッチしたもののみを抽出
$filtered_sites = [];
foreach ( $sites as $site ) {
    // 検索条件にマッチしなければスキップ
    if ( ! vkfsi_search_filter( $site ) ) {
        continue;
    }
    $filtered_sites[] = $site;
}

if ( count( $filtered_sites ) == 0 ) {
    echo '<div class="notice notice-info is-dismissible"><p>該当するサイトが見つかりませんでした。</p></div>';
} else {
    echo '<p>インストールするサイトを選択してください</p>';
}

// 検索条件用 hidden タグ
$search_hidden = '';
foreach ( $_POST as $key => $value ) {
    if ( 0 === strpos( $key, 's-' ) ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $v ) {
                $v = sanitize_text_field( $v );
                $search_hidden .= '<input type="hidden" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $v ) . '">';
            }
        } else {
            $value = sanitize_text_field( $value );
            $search_hidden .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
        }
    }
}

// サイト一覧の表示
echo '<div class="vkfsi_sites">';
foreach ( $filtered_sites as $site ) {
    // サイトデータの表示
    echo '<div id="div-' . $site[ 'site_code' ] . '" class="vkfsi_site">';

    // フォーム
    echo '<form method="post" action="">';

    // サイトコードとライセンスタイプ
    echo '<input type="hidden" name="vkfsi_code" value="' . esc_html( $site[ 'site_code' ] ) . '">';
    echo '<input type="hidden" name="vkfsi_license_type" value="' . esc_html( $site[ 'license_type' ] ) . '">';

    // 検索条件用 hidden タグ
    echo $search_hidden;

    // サムネイル画像
    echo '<a href="' . esc_url( $site[ 'demo_url' ] ) . '" target="_blank">';
    echo '<img src="' . esc_url( $site[ 'thumbnail_url' ] ) . '" alt="' . esc_attr( $site[ 'site_name' ] ) . '" width="300" class="vkfsi_thumbnail">';
    echo '</a>';

    // サイト名の表示
    echo '<h3>' . esc_html( $site[ 'site_name' ] ) . '</h3>';

    $license_type = '';
    switch ( $site['license_type'] ) {
        case VK_FULLSITE_INSTALLER_LICENSE_TYPE_FREE:
            $license_type = '無料';
            break;
        case VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT:
            $license_type = 'Vektor Passport';
            break;
        case VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE:
            $license_type = 'Vektor Passport + サイトライセンス';
            break;
        case VK_FULLSITE_INSTALLER_LICENSE_TYPE_SITE:
            $license_type = 'サイトライセンス';
            break;
        default:
            $license_type = '';
            break;
    }

    echo '<dl class="vkfsi_table"><dt><span class="vkfsi_table_label">ライセンスタイプ</span></dt><dd>' . $license_type . '</dd></dl>';
    // 使用テーマの表示
    echo '<dl class="vkfsi_table"><dt><span class="vkfsi_table_label">使用テーマ</span></dt><dd>' . $site[ 'theme' ] . '</dd></dl>';

    echo '<dl class="vkfsi_table"><dt><span class="vkfsi_table_label">テーマタイプ</span></dt><dd>' . $site[ 'theme_type' ] . '</dd></dl>';

    // 使用言語の表示
    // echo '<dl class="vkfsi_table"><dt><span class="vkfsi_table_label">使用言語</span></dt><dd>' . $site[ 'language' ] . '</dd></dl>';

    // Author の表示
    echo '<dl class="vkfsi_table"><dt><span class="vkfsi_table_label">Author</span></dt><dd>' . $site[ 'author' ] . '</dd></dl>';

    // Price ///////////////////////////////////////////////////////////////
    $price_data = vkfst_get_display_price_data( $site );
    $price_html = '<div class="vkfsi_price-outer">';
    $price_html .= vkfst_get_display_price_html( $price_data );
    if (
        VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE == $site[ 'license_type' ]
        || VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT == $site[ 'license_type' ]
        ) {
        $price_html .= '<span class="vkfsi_price_passport">※ 別途 <a href="https://vws.vektor-inc.co.jp/vektor-passport" target="_blank">Vektor Passport</a> が必要です</span>';
    }
    $price_html .= '</div>'; // vkfsi_price-outer

    echo '<dl class="vkfsi_table"><dt><span class="vkfsi_table_label">販売価格</span></dt><dd>';
    echo $price_html;
    echo '</dd></dl>';

    echo '<div class="vkfsi_description">';
    echo esc_html( $site[ 'description' ] );
    echo '</div>';

    // $data_url はループの外からもってきているので、ループ内で $site の内容に応じて書き換えてしまうとるとインストール URL がおかしくなるので注意
    if ( VK_FULLSITE_INSTALLER_LICENSE_TYPE_FREE == $site[ 'license_type' ] ) {
        $submit_data_url = $site[ 'data_url' ];
    } else {
        $submit_data_url = $data_url;
    }
    echo '<input type="hidden" name="vkfsi_data_url" value="' . esc_url( $submit_data_url ) . '">';

    echo '<div class="vkfsi_btn-outer">';
    echo '<div class="vkfsi_btn-inner">';

    // デモサイトへのリンク表示
    echo '<p class="vkfsi_site-demo-url">
        <a href="' . esc_url( $site[ 'demo_url' ] ) . '" target="_blank" class="button vkfsi_button-with-icon">
            <span class="vkfsi_button-text">デモサイトを見る</span>
            <svg class="vkfsi_icon" width="18" height="18" aria-hidden="true">
                <use xlink:href="#icon-external-link"></use>
            </svg>
        </a>
    </p>';

    // インポートボタンの表示処理
    // 無料版の場合は無条件で表示する
    if ( VK_FULLSITE_INSTALLER_LICENSE_TYPE_FREE == $site[ 'license_type' ] ) {
        echo '<p class="submit">
            <button type="submit" name="select_site" id="select_site" class="button button-primary vkfsi_button-with-icon">
                <span class="vkfsi_button-text">このサイトをインポート</span>
                <svg class="vkfsi_icon" width="18" height="18" aria-hidden="true">
                    <use xlink:href="#icon-import"></use>
                </svg>
            </button>
        </p>';

    // 有料版の場合は、ダウンロード URL が空文字でなければ、インポートボタンを表示
    } else {
        if ( '' != $data_url && $site_code == $site[ 'site_code' ] ) {
            echo '<p class="submit">
                <button type="submit" name="select_site" id="select_site" class="button button-primary vkfsi_button-with-icon">
                    <span class="vkfsi_button-text">このサイトをインポート</span>
                    <svg class="vkfsi_icon" width="18" height="18" aria-hidden="true">
                        <use xlink:href="#icon-import"></use>
                    </svg>
                </button>
            </p>';
        }
    }

    // Vektor Passport ライセンスキーの入力欄
    if ( VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE == $site[ 'license_type' ]
        || VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT == $site[ 'license_type' ] ) {

        echo '<label for="license_key_vektor_passport">Vektor Passport ライセンスキー</label>';
        echo '<div class="vkfsi_license-form">';

        // エラーメッセージを表示
        if ( $error_flag_passport && $site_code == $site[ 'site_code' ]) {
            echo '<div class="vkfsi_error">Vektor Passport ライセンスキーが間違っています。</div>';
        }

        // 保存ボタンを押したサイトコードと同じなら、
        // Vektor Passport ライセンスキーを表示する
        if ( $site_code == $site[ 'site_code' ] ) {
            echo '<input type="password" name="license_key_vektor_passport" value="' . $license_key_passport . '">';
        } else {
            echo '<input type="password" name="license_key_vektor_passport" value="">';
        }
        submit_button( '保存', 'primary', 'save_license_key_vektor_passport' );

        // 購入ボタン
        echo '<a href="' . PASSPORT_PURCHASE_URL . '" target="_blank">';
        echo '<button type="button" class="button button-primary">購入</button>';
        echo '</a>';
        echo '</div>';
    }

    // サイトライセンスキーの入力欄
    if ( VK_FULLSITE_INSTALLER_LICENSE_TYPE_PASSPORT_AND_SITE == $site[ 'license_type' ]
        || VK_FULLSITE_INSTALLER_LICENSE_TYPE_SITE == $site[ 'license_type' ] ) {

        echo '<label for="license_key_site">サイト ライセンスキー</label>';
        echo '<div class="vkfsi_license-form">';

        // エラーメッセージを表示
        if ( $error_flag_site && $site_code == $site[ 'site_code' ] ) {
            echo '<div class="vkfsi_error">サイトライセンスキーが間違っています。</div>';
        }

        // 保存ボタンを押したサイトコードと同じなら、
        // サイトライセンスキーを表示する
        if ( $site_code == $site[ 'site_code' ] ) {
            echo '<input type="password" name="license_key_site" value="' . $license_key_site . '">';
        } else {
            echo '<input type="password" name="license_key_site" value="">';
        }
        submit_button( '保存', 'primary', 'save_license_key_site' );

        // 購入ボタン
        echo '<a href="' . $site['shop-item' ][ 'buy-link' ] . '" target="_blank">';
        echo '<button type="button" class="button button-primary">購入</button>';
        echo '</a>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}
echo '</div>';
echo '</div>';

