<?php
/*
Plugin Name: はてなブログインポートツール
Plugin URI: 
Description: はてなブログのエクスポートファイルを使い、記事、画像、アイキャッチ画像を WordPressに登録する
Version: 
Author: imuza.com
Author URI: https://imuza.com
License: 
*/

if(session_status() !== PHP_SESSION_ACTIVE) session_start();

add_action('admin_menu', 'set_plugin_menu');
add_action('admin_menu', 'set_plugin_submenu1');
add_action('admin_menu', 'set_plugin_submenu2');
add_action('admin_menu', 'set_plugin_submenu3');

function set_plugin_menu(){
  add_menu_page(
    'はてなブログインポートツール',
    'はてなブログインポートツール',
    'manage_options',
    'hatenatools',
    'show_about_plugin',
    'dashicons-admin-tools',
    99
  );
}
function set_plugin_submenu1(){
  add_submenu_page(
    'hatenatools',
    'フォトライフ画像コピー',
    'フォトライフ画像コピー',
    'manage_options',
    'hatenatools-move-images',
    'move_images'
	);
}
function set_plugin_submenu2(){
  add_submenu_page(
    'hatenatools',
    'ファイル整形',
    'ファイル整形',
    'manage_options',
    'hatenatools-format-document',
    'format_document'
	);
}
function set_plugin_submenu3(){
  add_submenu_page(
    'hatenatools',
    'アイキャッチ画像登録',
    'アイキャッチ画像登録',
    'manage_options',
    'hatenatools-featured-images',
    'featured_images'
	);
}

/*
 * メインページ
 */
function show_about_plugin() {
?>
<h2>はてなブログから WordPress へ移行するためのサポートツール</h2>
<p>このプラグインは次のことを行います</p>
<ol>
	<li>エクスポートファイルで使われているはてなフォトライフ画像を wp-contents/uploads 以下の yyyy/mm ディレクトリにコピーします</li>
	<li>エクスポートファイルを次のように整形します
		<ul style="list-style: disc; padding-left: 20px;">
			<li>画像の URL 変更、alt, title 等不要属性削除</li>
			<li>見出しの変更 h3->h2, h4->h3, h5->h4</li>
			<li>キーワードリンク削除</li>
			<li>Youtube リンクを https に変更</li>
		</ul>
	</li>
	<li>エクスポートファイルの指定に基づき記事ごとのアイキャッチ画像を登録します</li>
</ol>
<h3>移行手順</h3>
<p>次の順序で実行してください</p>
<ol>
	<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=hatenatools-move-images' ) );?>">フォトライフ画像コピー</a></li>
	<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=hatenatools-format-document' ) );?>">ファイル整形</a></li>
	<li>この段階で別途プラグイン「Movable Type と TypePad インポーター」を使いエクスポートファイルをインポートしてください</li>
	<li>続いて、別途プラグイン「Media from FTP」または「Bulk Media Register」をインストールし、1でコピーした画像をメディアライブラリに登録してください<br>
		<span style="color:red">（重要）プラグインの設定は「yyyy/mm/画像ファイル」の保存形式が維持されるようにしてください</span><br>
		・「日付」項目は、「ファイルの日時を取得し、それに基づいて更新」にチェックする<br>
		・「アップロードしたファイルを年月ベースのフォルダーに整理」のチェックは外す<br>
		など、画像を移動してしまわないように注意してください</li>
	<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=hatenatools-featured-images' ) );?>">アイキャッチ画像登録</li>
</ol>
<?php
}

/*
 * フォトライフ画像コピー
 */
function move_images(){
	session_start();
	$token = isset($_POST["token"]) ? $_POST["token"] : "";
	$flg = false;
	if ($token == ''){
		$token = uniqid('', true);
		$_SESSION['token'] = $token;
	}else{
		$session_token = isset($_SESSION["token"]) ? $_SESSION["token"] : "";
		unset($_SESSION["token"]);
		if($token == $session_token) {
			$flg = true;
		}else{
			$token = uniqid('', true);
			$_SESSION['token'] = $token;
		}
	}
?>
<h2>はてなブログ記事内のフォトライフ画像コピー</h2>
<p>はてなブログのエクスポートファイルから、はてなフォトライフの URL を取り出し、その画像を wp-contents/uploads 以下の yyyy/mm ディレクトリにコピーします</p>
<p>画像ファイルのコピーはそれなりの時間を要しますので、進行状態は 100ファイルずつ表示します<br>
	ただし、サーバーの設定によっては逐次表示されずに終了後一括表示される場合があります</p>
<p>また、ページを閉じても続行されますが「完了しました」の表示が出るまで気長に待ってください</p>
<br>
<p>はてなブログのエクスポートファイルを指定してください</p>
<form enctype="multipart/form-data" action="" method="POST">
	<input type="hidden" name="token" value="<?php echo $token;?>">
	<input type="file" name="uploaded_file"></input><br>
	<input type="submit" value="Upload"></input>
</form>
<br>
<?php
	if($flg && !empty($_FILES['uploaded_file'])){
		$path = plugin_dir_path(__FILE__) . 'hatenablog.txt';

		if(move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $path)) {
			echo "ファイルがアップロードされました<br><br>";

			$pattern1 = '/(https?:\/\/cdn-ak\.f\.st-hatena.com\/images\/fotolife\/.+?(jpg|gif|png))/';
			$pattern2 = '/^https?:\/\/cdn-ak\.f\.st-hatena.com\/images\/fotolife\/.+?\/.+?\/(\d{4})(\d{2})\d{2}\/(.+)$/';
			$array = array();

			$hatena_text = file_get_contents($path);
			preg_match_all($pattern1, $hatena_text, $matches);
			$img_urls = array_unique($matches[1]);	
			$total = count($img_urls);
			$count = 0;

			echo "画像ファイルは " . $total . " ファイルあります<br>";
			echo "表示は 100ファイルコピーごとに更新します<br><br>";
			ob_flush();
			flush();

			foreach($img_urls as $url){
				if($img_data = @file_get_contents($url)){
					preg_match($pattern2, $url, $matches);
					$directory_name = '../wp-content/uploads/' . $matches[1] . '/' . $matches[2];
					$img_file_name = $directory_name . '/' . $matches[3];

					if(!is_dir($directory_name)){
						mkdir($directory_name, 0705, true);
					}
					file_put_contents($img_file_name, $img_data);
					$array[] = $img_file_name;
					$count++;
					if($count % 100 == 0){
						echo $count . " / " . $total . "<br>";
						ob_flush();
						flush();
					}
				}
			}
			echo $total . " / " . $total . "<br><br>";
			echo "<p>完了しました<br>uploads内を確認してください</p><br>";
		} else{
			echo "<p>ファイルをアップロードできませんでした</p>";
		}
	}
	echo '<p><a href="' . esc_url( admin_url( "admin.php?page=hatenatools" ) ) . '">メインページへ</a></p>';
}

/*
 * ファイル整形
 */
function format_document(){
	$token1 = isset($_POST["token1"]) ? $_POST["token1"] : "";
	$token2 = isset($_POST["token2"]) ? $_POST["token2"] : "";
	$flg = false;
	if ($token1 == '' && $token2 == ''){
		$token1 = uniqid('', true);
		$_SESSION['token1'] = $token1;
	}elseif($token1 != ''){
		$session_token1 = isset($_SESSION["token1"]) ? $_SESSION["token1"] : "";
		unset($_SESSION["token1"]);
		if($token1 == $session_token1) {
			$flg = true;
			$token1 = '';
			$token2 = uniqid('', true);
			$_SESSION['token2'] = $token2;
		}else{
			$token1 = uniqid('', true);
			$_SESSION['token1'] = $token1;
		}
	}elseif($token2 != ''){
		$session_token2 = isset($_SESSION["token2"]) ? $_SESSION["token2"] : "";
		unset($_SESSION["token2"]);
		if($token2 == $session_token2 && isset($_POST['accept'])){
			$flg = true;
		}elseif(isset($_POST['accept'])){
			$token2 = uniqid('', true);
			$_SESSION['token2'] = $token2;
		}else{
			$token2 = '';
			$token1 = uniqid('', true);
			$_SESSION['token1'] = $token1;
		}			
	}
?>
<h2>ファイル整形</h2>
<p>エクスポートファイルを WordPress 用に整形します</p>
<ul style="list-style: disc; padding-left: 20px;">
	<li>画像の URL 変更、alt, title 等不要属性削除</li>
	<li>見出しの変更 h3->h2, h4->h3, h5->h4</li>
	<li>キーワードリンク削除</li>
	<li>Youtube リンクを https に変更</li>
</ul>
<br>
<p>はてなエクスポートファイルの画像 URL をドメイン付きにするかしないか、また運用はドメイン直下かサブディレクトリかを指定してください</p>
<table border="1" style="border-collapse: collapse">
	<thead>
		<tr>
			<th>指定（例）</th>
			<th>画像 URL</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>https://hogehoge</td>
			<td>https://hogehoge/wp-content/uploads/(yyyy)/(mm)/(ファイル名)</td>
		</tr>
		<tr>
			<td>https://hogehoge/wordpress</td>
			<td>https://hogehoge/wordpress/wp-content/uploads/(yyyy)/(mm)/(ファイル名)</td>
		</tr>
		<tr>
			<td>blankと入力</td>
			<td>/wp-content/uploads/(yyyy)/(mm)/(ファイル名)</td>
		</tr>
		<tr>
			<td>/wordpress</td>
			<td>/wordpress/wp-content/uploads/(yyyy)/(mm)/(ファイル名)</td>
		</tr>
	</tbody>
</table><br>
<form action="" method="POST">
	<input type="hidden" name="token1" value="<?php echo $token1 ?>">
	<input type="text" name="wp_path"><br>
	<input type="submit" value="Submit">
</form>
<br>
<?php
	$hatenablog_path = plugin_dir_path(__FILE__) . 'hatenablog.txt';
	if($flg && !file_exists($hatenablog_path)){
		echo '<p style="color:red;">はてなブログのエクスポートファイルがありません</p>';
		echo '<p>メインページに戻り「フォトライフ画像コピー」を実行してください</p>';
		echo '<a href="' . esc_url( admin_url( "admin.php?page=hatenatools" ) ) . '">メインページへ</a></p>';
	}elseif($flg && isset($_POST['accept'])){
		$wp_path = $_POST['wp_path'];
		$hatena_text = file_get_contents($hatenablog_path);
		$patterns = array (
			'/https?:\/\/cdn-ak\.f\.st-hatena.com\/images\/fotolife\/.+?\/.+?\/(\d{4})(\d{2})\d{2}\/(.+\.(jpg|gif|png))/', 
			'/alt="f:id:.+?"/', 
			'/title="f:id:.+?"/', 
			'/ figure-image-fotolife mceNonEditable/', 
			'/ class="mceEditable"/', 
			'/<h3 /', 
			'/<\/h3>/', 
			'/<h4 /', 
			'/<\/h4>/', 
			'/<h5 /', 
			'/<\/h5>/', 
			'/<a .*href="http:\/\/d\.hatena\.ne\.jp\/keyword\/.+?".*?>(.+)?<\/a>/', 
			'/<iframe.+?youtube\.com\/embed\/(.+)?\?.+?<\/iframe>/'
			);
		$replace = array (
			$wp_path . '/wp-content/uploads/$1/$2/$3', 
			'alt=""', 
			'title=""', 
			'', 
			'', 
			'<h2 ', 
			'</h2>', 
			'<h3 ', 
			'</h3>', 
			'<h4 ', 
			'</h4>', 
			'$1', 
			'<iframe src="https://www.youtube.com/embed/$1?enablejsapi=1" width="560" height="315" frameborder="0" allowfullscreen></iframe>'
			);
	
		$new_file = preg_replace($patterns, $replace, $hatena_text);
	
		$new_file_name = WP_CONTENT_DIR . "/mt-export.txt";
		file_put_contents($new_file_name, $new_file);
		echo '<p>WordPress 用に整形したファイルを /wp-content/mt-export.txt に保存しました</p>';
		echo '<p>プラグイン「Movable Type・TypePad インポートツール」を使い、「mt-export.txt のインポート」をクリックしてください</p><br>';
		echo '<p><a href="' . esc_url( admin_url( "import.php" ) ) . '">ツール -> インポートへ</a></p>';
		
	
	}elseif($flg && !empty($_POST['wp_path'])){
		$path = $_POST['wp_path'] == 'blank' ? '' : $_POST['wp_path'];
		echo '画像 URL は ' . $path . '/wp-content/uploads/(yyyy)/(mm)/(ファイル名) でいいですか？<br>';
		echo '<form action="" method="POST">';
		echo '<input type="hidden" name="token2" value="' . $token2 . '">';
		echo '<input type="hidden" name="wp_path" value="' . $path . '">'; 
		echo '<input type="submit" name="accept" value="YES"> ';
		echo '<input type="submit" value="NO"><br>';
		echo '</form>';
	}
}

/*
 * アイキャッチ画像登録
 */
function featured_images(){
	$token = isset($_POST["token"]) ? $_POST["token"] : "";
	$flg = false;
	if ($token == ''){
		$token = uniqid('', true);
		$_SESSION['token'] = $token;
	}else{
		$session_token = isset($_SESSION["token"]) ? $_SESSION["token"] : "";
		unset($_SESSION["token"]);
		if($token == $session_token) {
			$flg = true;
		}else{
			$token = uniqid('', true);
			$_SESSION['token'] = $token;
		}
	}
?>
<h2>各記事へのアイキャッチ画像登録</h2>
<p>記事ごとのアイキャッチ画像を次の順序で検索し一括登録します。</p>
<ul style="list-style: inherit;  padding-left: 20px;">
	<li>はてなブログエクスポートファイルの IMAGE: に設定されている画像</li>
	<li>記事本文の最初の画像</li>
	<li>次のテキストボックスで指定した画像</li>
</ul>
<br>
<form action="" method="POST">
	<label>記事ごとのアイキャッチ画像がない場合の画像のファイル名をしてしてください（空白可)<br>uploadフォルダ以下を指定（/hogehoge.jpg, /fuga/hogehoge.jpg等）<br>
		<input type="text" name="site_image"></input>
	</label><br><br>
	<input type="hidden" name="token" value="<?php echo $token;?>">
	<input type="submit" value="Upload"></input>
</form>
<?php
	$hatenablog_path = plugin_dir_path(__FILE__) . 'hatenablog.txt';
	if($flg && !file_exists($hatenablog_path)){
		echo '<p style="color:red;">はてなブログのエクスポートファイルがありません</p>';
		echo '<p>メインページに戻り「フォトライフ画像コピー」を実行してください</p>';
		echo '<a href="' . esc_url( admin_url( "admin.php?page=hatenatools" ) ) . '">メインページへ</a></p>';
		exit;
	}
	if($flg && isset($_POST['site_image'])){
		$upload_dir = wp_upload_dir();
		$site_image_path = $upload_dir['basedir'] . $_POST['site_image'];
		if(!file_exists($site_image_path)){
			echo '<p style="color:red;">指定された画像ファイルがありません</p>';
			exit;
		}
	}
	if($flg){
		$array = file($hatenablog_path, FILE_IGNORE_NEW_LINES);
		$new_array = array();
		$basename = '';
		$image = '';
		$pattern = '/https?:\/\/cdn-ak\.f\.st-hatena.com\/images\/fotolife\/.+?\/.+?\/(\d{4})(\d{2})\d{2}\/(.+\.(jpg|gif|png))/';
		$noimage = false;
		$dummy = empty($_POST['site_image']) ? '' : '/wp-content/uploads' . $_POST['site_image'];
		for( $i = 0; $i < count($array); ++$i ) {
			if (strpos($array[$i], 'BASENAME:') !== FALSE) {
				$basename = preg_replace('/^BASENAME: (.+)$/', '$1', $array[$i]);
				$basename = str_replace('/', '-', $basename);
			}
			if (strpos($array[$i], 'IMAGE:') !== FALSE && strpos($array[$i], 'st-hatena.com') !== FALSE) {
				$image = preg_replace('/^IMAGE: https?:\/\/cdn-ak2?\.f\.st-hatena.com\/images\/fotolife\/.+?\/.+?\/(\d{4})(\d{2})\d{2}\/(.+)$/', '/wp-content/uploads/$1/$2/$3', $array[$i]);
			}
			if (strpos($array[$i], 'BODY:') !== FALSE && $image === ''){
				$noimage = true;
			}
			if ($noimage && $mflg = preg_match($pattern, $array[$i], $matches)){
				$image = '/wp-content/uploads/' . $matches[1] . '/' . $matches[2] . '/' . $matches[3];
				$noimage= false;
			}
			if (strpos($array[$i], '--------') !== FALSE) {
				if($image === '') $image = $dummy;
				$new_array[] = array($basename, $image);
				$basename = '';
				$image = '';
			}
		}

		for( $i = 0; $i < count($new_array); ++$i ) {
			global $wpdb;
			$query = 'SELECT ID FROM wp_posts WHERE post_name ="' . $new_array[$i][0] .'"';
			$id = (int) $wpdb->get_var($query);

			$url =  home_url() . $new_array[$i][1];
			$query = 'SELECT ID FROM wp_posts WHERE guid ="'.$url.'"';
			$thumbnail_id = (int) $wpdb->get_var($query);

			set_post_thumbnail( $id, $thumbnail_id );
		}
		echo '完了しました';
	}
}
