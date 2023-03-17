<?php
/*
Plugin Name: SLUG TRANSLATER
Description: 日本語の投稿記事やカテゴリなどのslugを英訳して最適な形式に置き換えます。
Version: 1.1.3
Author:WebクリエイターItmaroon
Author URI:https://itmaroon.net
*/

if ( ! defined( 'ABSPATH' ) ) exit;

//プラグインのパス
define( 'SLTRANSLATE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SLTRANSLATE_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

//thephpleague/oauth2-clientの読込
require SLTRANSLATE_PLUGIN_PATH.'vendor/autoload.php';
//use GuzzleHttp\Client;
// $http = new GuzzleHttp\Client([
//   'verify' => TRASLATE_PLUGIN_PATH.'vendor\guzzlehttp\cacert.pem'
// ]);

//google translate ライブラリの読み込み
use Google\Cloud\Translate\V2\TranslateClient;


//現在のパーマリンク設定
$LINK_FORMAT=get_option('permalink_structure');

//新規投稿フラグ
$NEW_POST_FLG=false;
//みんなの自動翻訳認証情報
define('SLTRANSLATE_URL','https://mt-auto-minhon-mlt.ucri.jgn-x.jp');  // 基底URL (https://xxx.jpまでを入力)
define('SLTRANSLATE_KEY', get_option('sl_trans_API_key',''));  // API key
define('SLTRANSLATE_SECRET', get_option('sl_trans_API_secret',''));  // API secret
define('SLTRANSLATE_NAME',get_option('sl_trans_ID',''));  // ログインID
//googletranslate認証情報
define('SLTRANSLATE_GOOGLE_API', get_option('sl_trans_google_apikey',''));  // API KEY
define('SLTRANSLATE_GOOGLE_PRID',get_option('sl_trans_google_prid',''));  //プロジェクトID


//セッションスタートとオプションを読み込み
function sl_trans_session_start(){
  if(!isset($_SESSION)){
    session_start();
		//フラグのセット
		if(!isset($_SESSION['sl_trans_newPost'])){
			$_SESSION['sl_trans_newPost'] = false;
		}
  }
}
//add_action('init', 'sl_trans_session_start');

//プラグイン用get_template_part
function sl_trans_get_template_part($slug, $name = null) {
  $templates = array();
  $name = (string) $name;
  if ( '' !== $name )
    $templates[] = "{$slug}-{$name}.php";
  
  $templates[] = "{$slug}.php";
  $located = '';
  foreach ( (array) $templates as $template_name ) {
    if ( !$template_name ) {
      continue;
    }
    if ( file_exists(SLTRANSLATE_PLUGIN_PATH . '/' . $template_name) ) {
      $located = SLTRANSLATE_PLUGIN_PATH . '/' . $template_name;
      break;
    }
  }
  if($located) {
    require_once( $located );
  }
}

//ページのスラッグ取得
function sl_trans_page_slug(){
  if(is_front_page()) $slug = 'home';//トップページの場合

  if(is_archive()) {//アーカイブページの場合
    if(is_tax()){ //タクソノミーアーカイブの場合
      $taxonomy = get_query_var('taxonomy');
      $slug = get_taxonomy($taxonomy)->object_type[0];
    }elseif(is_category()){//カテゴリーアーカイブの場合
      $slug=get_post_type();
      
    }
    else{//それ以外のアーカイブの場合
      //$slug = get_query_var( 'post_type' );
      $slug=get_post_type();
      
    }
  }

  if(is_page()){//固定ページの場合
    $page = get_post( get_the_ID() );
    $slug = $page->post_name;
    if($slug === 'thanks'){
      $slug = 'contact';
    }
  }

  if(is_single()){//シングルページの場合
    $slug = get_post_type();
    
  }

  return $slug;
}

//CSS等の読込
function sl_trans_script_init(){  
	wp_enqueue_style('mytrans', SLTRANSLATE_PLUGIN_URL . 'css/translater.css?'.date('YmdHis'), array(), '1.0.0', 'all');
}
add_action('admin_enqueue_scripts', 'sl_trans_script_init');

//プラグインのアクティベーション時に設定キーを追加
function sl_trans_activate(){
  add_option('sl_trans_timing_check','on');
  //投稿タイプ
  $result=[];
  $args = array(
    'public'   => true,
  );
  $post_types = get_post_types( $args, 'objects','and');
  //一部削除
  foreach ( $post_types as $i=>$post_type ){
    $object_name = $post_type->name;
    if(($object_name != 'attachment') and ($object_name != 'revision') and ($object_name != 'nav_menu_item')){
      $result[]=$object_name;
    }
  }
  add_option('sl_trans_type_check',$result);
  //タクソノミ
  $result=[];
  $args = array(
    'public'   => true,
  );
  $tax_arr = get_taxonomies( $args, 'objects','and');
  //一部削除
  foreach ( $tax_arr as $i=>$tax ){
    $object_name = $tax->name;
    if($object_name != 'post_format'){
      $result[]=$object_name;
    }
  }
  add_option('sl_trans_tax_check',$result);
}
register_activation_hook( __FILE__, 'sl_trans_activate' );

//プラグイン無効化時にオプションキーを削除
function sl_trans_deactivate(){
  // delete_option('sl_trans_ID');
  // delete_option('sl_trans_API_key');
  // delete_option('sl_trans_API_secret');
  // delete_option('sl_trans_timing_check');
  // delete_option('sl_trans_type_check');
  // delete_option('sl_trans_tax_check');
}
register_deactivation_hook( __FILE__, 'sl_trans_deactivate' );


/**
 * 「設定」にメニューを追加
 */
function sl_trans_add_admin_menu() {
  add_options_page(
    'SLUG TRANSLATER設定ページ', // 設定画面のページタイトル.
    'SLUG TRANSLATER', // 管理画面メニューに表示される名前.
    'manage_options',
    'sl_trans_menu', // メニューのスラッグ.
    'sl_trans_menu_page' // メニューの中身を表示させる関数の名前.
  );
}
//メニューと画面表示
function sl_trans_menu_page() {
  // 権限チェック.
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }
  
  ?>
  <div class="wrap">
    <h1>SLUG TRANSLATER の設定</h1>

    <form method="POST" action="options.php">
      <?php 
      settings_fields( 'sl_trans_setting_group' ); // ページのスラッグ.
      do_settings_sections( 'sl_trans_setting' );  // ページのスラッグ.
      submit_button();
      ?>
    </form>
  </div>
  <?php
}
add_action( 'admin_menu', 'sl_trans_add_admin_menu' );


/**
 * 設定項目の準備
 */
function sl_trans_init_settings() {
  // 設定のセクション追加.
  add_settings_section(
    'sl_trans_setting-section-1',
    '翻訳APIの選択',
    'sl_trans_authorize_section_func', // セクションの説明文を表示するための関数.
    'sl_trans_setting'
  );
  add_settings_section(
    'sl_trans_setting-section-2',
    'スラッグを英訳に置き換えるタイミング',
    'sl_trans_timing_section_func',
    'sl_trans_setting'
  );
  add_settings_section(
    'sl_trans_setting-section-3',
    'パーマリンクの設定',
    'sl_trans_permalink_section_func',
    'sl_trans_setting'
  );
  add_settings_section(
    'sl_trans_setting-section-4',
    'スラッグを置き換える投稿タイプ・タクソノミ',
    'sl_trans_type_section_func',
    'sl_trans_setting'
  );
  // 設定項目の追加.
	add_settings_field(
    'sl_trans_engine', // 設定名.
    '', // 設定タイトル.
    'sl_trans_select_func', // 設定項目のHTMLを出力する関数名.
    'sl_trans_setting', // メニュースラッグ.
    'sl_trans_setting-section-1', // どのセクションに表示するか.
  );

  add_settings_field(
    'sl_trans_check', // 設定名.
    '', // 設定タイトル.
    'sl_trans_check_func', // 設定項目のHTMLを出力する関数名.
    'sl_trans_setting', // メニュースラッグ.
    'sl_trans_setting-section-2' // どのセクションに表示するか.
  );
  add_settings_field(
    'sl_trans_type', // 設定名.
    '投稿タイプ', // 設定タイトル.
    'sl_trans_type_func', // 設定項目のHTMLを出力する関数名.
    'sl_trans_setting', // メニュースラッグ.
    'sl_trans_setting-section-4' // どのセクションに表示するか.
  );

  add_settings_field(
    'sl_trans_tax', // 設定名.
    'タクソノミ等', // 設定タイトル.
    'sl_trans_tax_func', // 設定項目のHTMLを出力する関数名.
    'sl_trans_setting', // メニュースラッグ.
    'sl_trans_setting-section-4' // どのセクションに表示するか.
    
  );
  

  // 設定の登録
	register_setting(
    'sl_trans_setting_group',
    'sl_trans_engine',
    [
      'sanitize_callback' => 'esc_attr',
    ]
  );

  register_setting(
    'sl_trans_setting_group',
    'sl_trans_ID',
    [
      'sanitize_callback' => 'esc_attr',
    ]
  );
  register_setting(
    'sl_trans_setting_group',
    'sl_trans_API_key',
    [
      'sanitize_callback' => 'esc_attr',
    ]
  );
  register_setting(
    'sl_trans_setting_group',
    'sl_trans_API_secret',
    [
      'sanitize_callback' => 'esc_attr',
    ]
  );
	register_setting(
    'sl_trans_setting_group',
    'sl_trans_google_prid',
    [
      'sanitize_callback' => 'esc_attr',
    ]
  );
	register_setting(
    'sl_trans_setting_group',
    'sl_trans_google_apikey',
    [
      'sanitize_callback' => 'esc_attr',
    ]
  );
  register_setting(
    'sl_trans_setting_group',
    'sl_trans_timing_check'
    
  );
  register_setting(
    'sl_trans_setting_group',
    'sl_trans_type_check'
  );
  register_setting(
    'sl_trans_setting_group',
    'sl_trans_tax_check'
  );
}

/**
 * セクションの説明文を表示するための関数
 */
function sl_trans_authorize_section_func() {
  echo '<p class="authorize_caution">翻訳APIを選択してください。</p>';
}
function sl_trans_timing_section_func() {
  echo '<p class="timing_caution">このチェックを外すと<strong>手動でのスラッグ名変更ができなくなります。</strong><br>常に自動的にスラッグ名を置き換えたい場合のみチェックを外して下さい。</p>';
}
function sl_trans_permalink_section_func() {
  global $LINK_FORMAT;
  preg_match('/\%postname\%/', $LINK_FORMAT, $m);
  if(isset($m[0])){
    echo '<p class="permalink_caution">パーマリンク設定は「'.esc_html($LINK_FORMAT).'」となっています。<br>%postname%の部分が英訳されて置き換わります。</p>';
  }else{
    echo '<p class="permalink_caution"><strong>パーマリンク設定に%postname%が含まれていません。</strong><br>英訳に置き換えるには%postname%が含まれる設定にしてください。パーマリンク設定は<a href="'.esc_url(home_url()).'/wp-admin/options-permalink.php">こちら</a></p>';
  }
}

function sl_trans_select_func() {
	?>
  <select name="sl_trans_engine">
		<option value="">--翻訳APIを選択してください--</option>
    <option value="minna">みんなの自動翻訳</option>
    <option value="google">Google Cloud Translation</option>
	</select>
	<button type="button" id="api_check">認証チェック</button>
	<div class="minna regist">
		<p class="regist_caution">「みんなの翻訳」のユーザー登録は<a href="https://mt-auto-minhon-mlt.ucri.jgn-x.jp/" target="_blank">
			こちら</a><br>（ユーザー登録が済んでいる方は認証情報を入力してください。<br>「みんなの自動翻訳」は無料で使用できますが、１日のアクセス回数に制限があります。</p>
		<table>
			<tr>
				<th>ログインID</th>
				<td><input type="text" name="sl_trans_ID" value="<?php form_option('sl_trans_ID'); ?>" ></td>
			</tr>
			<tr>
				<th>API key</th>
				<td><input type="text" name="sl_trans_API_key" value="<?php form_option('sl_trans_API_key'); ?>" ></td>
			</tr>
			<tr>
				<th>API secret</th>
				<td><input type="text" name="sl_trans_API_secret" value="<?php form_option('sl_trans_API_secret'); ?>" ></td>
			</tr>
		</table>
	</div><!-- /.minna -->
	<div class="google regist">
		<p class="regist_caution">Google Cloud Translation APIを利用するにはGoogle Cloud Platform（GCP）でプロジェクトを作成する必要があります。<br>
		プロジェクトの作成は<a href="https://console.cloud.google.com/home/dashboard" target="_blank">
			こちら</a><br>
			作成したプロジェクトでCloud Translation APIを有効にして、認証情報を取得してください。<br>
			このAPIは1カ月につき500,000文字までは無料ですが、それ以上は<strong>従量制で課金</strong>されます。</p>
		<table>
			<tr>
				<th>プロジェクトID</th>
				<td><input type="text" name="sl_trans_google_prid" value="<?php form_option('sl_trans_google_prid'); ?>" ></td>
			</tr>
			<tr>
				<th>API key</th>
				<td><input type="text" name="sl_trans_google_apikey" value="<?php form_option('sl_trans_google_apikey'); ?>" ></td>
			</tr>
		</table>
			
	</div><!-- /.minna -->
	<script>
		jQuery(function($) {
			//ajaxの送り先
			let ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php', __FILE__ ) ); ?>';
			//初期設定
			let sel_api="<?php form_option('sl_trans_engine'); ?>";
			if(sel_api!=''){
				$('select[name="sl_trans_engine"]').val(sel_api);
				$('.'+sel_api+'.regist').css('display','block');
			}
			
			//セレクトボックス変更時
			$('select[name="sl_trans_engine"]').change(function(){
				sel_api=$(this).val();
				$('.regist').each(function(){
					if($(this).hasClass(sel_api)){
						$(this).slideDown();
					}else{
						$(this).slideUp();
					}
				});
			});

			//チェックボタン押下時
			$('#api_check').click(function(){
				let regist_arr = [];
				//認証情報
				$('.regist input[type="text"]').each(function(){
					if($(this).parents('.regist').hasClass(sel_api)){
						regist_arr.push($(this).val());
					}
				});
				$(this).text('しばらくお待ちください');
				$(this).addClass('checking');
				let btn=$(this);

				$.ajax({
					type: 'POST',
					url: ajaxUrl,
					data: {
						'action' : 'sl_trans_check_api',
						'sel_api': sel_api,
						'regist_arr' : regist_arr,
						'nonce': '<?php echo wp_create_nonce('my-ajax-nonce' ); ?>'
					}
				}).done(function(data) {
					let ret_data=JSON.parse(data);
					if(ret_data['code']==0){
						alert('正常に認証されています。');
					}else{
						alert('エラーが発生しています。');
					}
				}).fail(function(XMLHttpRequest, textStatus, errorThrown) {
					console.log(XMLHttpRequest.status);
					console.log(textStatus);
					console.log(errorThrown.message);
					
				}).always(function() {
					btn.text('認証チェック');
					btn.removeClass('checking');
				})

			});
		});
	</script>
	<?php
}

function sl_trans_type_section_func() {
  echo '<p class="type_caution">次の投稿タイプ等のスラッグを英訳して置き換えます。</p>';
}

//Wordpress投稿情報を取得
function sl_trans_check_api_ajax(){
  $nonce = $_REQUEST['nonce'];
  $sel_api = isset( $_POST['sel_api'] ) ? $_POST['sel_api'] : '';
	$regist_arr = isset( $_POST['regist_arr'] ) ? $_POST['regist_arr'] : '';
  
	if ( wp_verify_nonce( $nonce, 'my-ajax-nonce' ) ) {
    switch($sel_api){
			case 'minna':
				$ret_data=sl_trans_exec_minna('テスト',$regist_arr[0],$regist_arr[1],$regist_arr[2]);
				break;
			case 'google':
				$ret_data=sl_trans_exec_google('テスト',$regist_arr[0],$regist_arr[1]);
				break;
		}
		echo json_encode($ret_data);
  }
  die();
}
add_action( 'wp_ajax_sl_trans_check_api', 'sl_trans_check_api_ajax' );
add_action( 'wp_ajax_nopriv_sl_trans_check_api', 'sl_trans_check_api_ajax' );

/**
 * テキストのサンプル設定項目表示用関数
 */
function sl_trans_text_func($input_arr) {
  ?>
  <input type="text" name="<?php echo esc_attr($input_arr[0]) ?>" value="<?php form_option($input_arr[0]); ?>" >
  <?php
}

/**
 * 単独のチェックボックス
 */
function sl_trans_check_func() {
  $option = get_option( 'sl_trans_timing_check','on' );
  
  echo '<label><input type="checkbox" name="sl_trans_timing_check" value="on" ' . checked( 'on', $option, false ) . ' />初回の保存時にのみスラッグを置き換える</label> ';
  
}

/**
 * 複数のチェックボックス
 */

function sl_trans_type_func() {
  //設定済みのオプションの読み込み
  $option_arr = get_option( 'sl_trans_type_check',[] );
  $option_arr=sl_trans_sanitize_item_array($option_arr);
  //設定された投稿タイプの読み込み
  $args = array(
    'public'   => true,
  );
  $post_types = get_post_types( $args, 'objects','and');
  //チェックボックスの表示
  foreach ( $post_types as $i=>$post_type ){
    $object_name = $post_type->name;
    if(($object_name != 'attachment') and ($object_name != 'revision') and ($object_name != 'nav_menu_item')){
      //設定済みのオプションに投稿タイプのスラッグが含まれるか(初期状態ではCheckedにする)
      if(in_array($post_type->name , $option_arr , true) || count($option_arr)==0){
        echo '<label><input type="checkbox" name="sl_trans_type_check[]" value="'.esc_attr($post_type->name).'" checked />'.esc_html($post_type->label).'</label> ';
      }else{
        echo '<label><input type="checkbox" name="sl_trans_type_check[]" value="'.esc_attr($post_type->name) . '" />'.esc_html($post_type->label).'</label> ';
      }
    }
  }
}

function sl_trans_tax_func() {
  $option_arr = get_option( 'sl_trans_tax_check',[] );
  $option_arr=sl_trans_sanitize_item_array($option_arr);
  //設定された投稿タイプの読み込み
  $args = array(
    'public'   => true,
  );
  $tax_arr = get_taxonomies( $args, 'objects','and');
  
  foreach ( $tax_arr as $tax_type ){
    if($tax_type->name != 'post_format'){
      if(in_array($tax_type->name , $option_arr , true) || count($option_arr)==0){
        echo '<label><input type="checkbox" name="sl_trans_tax_check[]" value="'.esc_attr($tax_type->name).'" checked />'.esc_html($tax_type->label).'</label> ';
      }else{
        echo '<label><input type="checkbox" name="sl_trans_tax_check[]" value="'.esc_attr($tax_type->name) . '" />'.esc_html($tax_type->label).'</label> ';
      }
    }
  }
}

// サニタイズ（チェックボックスの配列用）
function sl_trans_sanitize_item_array( $args ){
  $args = isset( $args ) ? (array) $args : [];
  $args = array_map('esc_attr', $args);
  return $args;
}

add_action( 'admin_init', 'sl_trans_init_settings' );

//翻訳のフィルターフック
function sl_trans_post_data($data, $postarr,$unsanitized_postarr, $update) {
	sl_trans_session_start();
  //オプションの読み込み
  
  $target_array=get_option('sl_trans_type_check',[]);
  $timing_flg = get_option('sl_trans_timing_check','on');
  //新規投稿フラグ
  $tr_flg=filter_var($_SESSION['sl_trans_newPost'],FILTER_VALIDATE_BOOLEAN);

  if(($tr_flg || $timing_flg!='on') && ($data['post_status']!='inherit' && $data['post_status']!='auto-draft')){//置き換えの判別(timingフラグがonでなく、新規投稿である。ステータスはinheritでなくauto-draftでない。)
    if(in_array($data['post_type'] , $target_array , true)){//指定された投稿タイプか
      // Change post name
      $ret_data=sl_trans_exec_translate($data['post_title']);//翻訳の実行
      if($ret_data['code']==0){//翻訳が成功したら
        $ret_name=$ret_data['text'];
        $sanitize_data=sanitize_title_with_dashes( $ret_name );//一旦サニタイズする
        $sanitize_data=wp_unique_post_slug( $sanitize_data, $postarr['ID'],$postarr['post_status'],$postarr['post_type'],0 );//ユニークなURLに変換
        $data['post_name']=$sanitize_data;
      }
    }
  }
  return $data;
}
add_action( 'wp_insert_post_data', 'sl_trans_post_data', 99, 4 );

//新規投稿のフラグをセットする
function sl_trans_change_newflg( $new_status, $old_status, $post ) {
	sl_trans_session_start();
  //オプション設定の読み込み
  $timing_flg = get_option('sl_trans_timing_check','on');

  if($old_status=='new' && $new_status=='auto-draft'){//inheritは置き換えない
    $_SESSION['sl_trans_newPost'] = true;
  }

  if($timing_flg=='on'){
    if($new_status=='draft' || $new_status=='publish' || $new_status=='private' || $new_status=='pending'){
      //下書きから公開される時にオフ
      $_SESSION['sl_trans_newPost'] = false;
    }
  }
}
add_action( 'transition_post_status', 'sl_trans_change_newflg', 10, 3 );

function sl_trans_create_term($term_id,$tax_id,$tax_name,$args){
	sl_trans_session_start();
  //オプションの読込
  $tax_array = get_option('sl_trans_tax_check',[]);

  if(in_array($tax_name,$tax_array,true)){//置き換え対象か
    $ret_data=sl_trans_exec_translate($args['name']);//翻訳の実行
    if($ret_data['code']==0){//翻訳が成功したら
			$ret_name=$ret_data['text'];
      $sanitize_data=sanitize_title_with_dashes( $ret_name );//サニタイズする

      $_SESSION['sl_trans_newPost']=true;//新規フラグを立てる
      wp_update_term( $term_id, $tax_name, array(
        'slug' => $sanitize_data
      ));
    }
  }
}
add_action( 'create_term', 'sl_trans_create_term', 10, 4 );

function sl_trans_edited_term($term_id,$tax_id,$tax_name,$args){
	sl_trans_session_start();

	$tr_flg=filter_var($_SESSION['sl_trans_newPost'],FILTER_VALIDATE_BOOLEAN);

  if($tr_flg){//新規ならフラグをおろしてリターン
    $_SESSION['sl_trans_newPost']=false;
    return;
  }
  //オプションの読込
  $tax_array = get_option('sl_trans_tax_check',[]);
  $timing_flg = get_option('sl_trans_timing_check','on');
  if(in_array($tax_name,$tax_array,true) && $timing_flg!='on'){//置き換え対象か
    $ret_data=sl_trans_exec_translate($args['name']);//翻訳の実行
    if($ret_data['code']==0){//翻訳が成功したら
			$ret_name=$ret_data['text'];
      $sanitize_data=sanitize_title_with_dashes( $ret_name );//サニタイズする
      $_SESSION['sl_trans_newPost']=true;//新規フラグを立てる
      wp_update_term( $term_id, $tax_name, array(
        'slug' => $sanitize_data
      ) );
    }
  }
}
add_action( 'edited_term', 'sl_trans_edited_term', 10, 4 );


//翻訳の実行
function sl_trans_exec_translate($ja_text){
	$ret_data=[];
	$sel_engine=get_option('sl_trans_engine','');
	switch($sel_engine){
		case 'minna':
			$ret_data=sl_trans_exec_minna($ja_text);
			break;
		case 'google':
			$ret_data=sl_trans_exec_google($ja_text);
			break;
	}
	return $ret_data;
}

//みんなの自動翻訳
function sl_trans_exec_minna($ja_text,$id=null, $api_key=null, $api_secret=null){
  $api_name  = 'mt';  // API名 (https://xxx.jp/api/mt/generalNT_ja_en/ の場合は、"mt")
  $api_param = 'generalNT_ja_en';  // API値 (https://xxx.jp/api/mt/generalNT_ja_en/ の場合は、"generalNT_ja_en")

	if($id == null && $api_key == null && $api_secret==null){
		$provider = new  \League\OAuth2\Client\Provider\GenericProvider(
			[
				'clientId' => SLTRANSLATE_KEY, // API Key
				'clientSecret' => SLTRANSLATE_SECRET, // API secret
				'redirectUri' => '', // リダイレクトURI（不要）
				'urlAuthorize' => '', // 認可コード取得URI（不要）
				'urlAccessToken' => SLTRANSLATE_URL . '/oauth2/token.php', // アクセストークン取得URI
				'urlResourceOwnerDetails' => '',
			],
		);
	}else{//テスト用
		$provider = new  \League\OAuth2\Client\Provider\GenericProvider(
			[
				'clientId' => $api_key, // API Key
				'clientSecret' => $api_secret, // API secret
				'redirectUri' => '', // リダイレクトURI（不要）
				'urlAuthorize' => '', // 認可コード取得URI（不要）
				'urlAccessToken' => SLTRANSLATE_URL . '/oauth2/token.php', // アクセストークン取得URI
				'urlResourceOwnerDetails' => '',
			],
		);
	}
  
  try {
    // Try to get an access token using the authorization code grant.
    $accessToken = $provider->getAccessToken('client_credentials');

    // The provider provides a way to get an authenticated API request for
    // the service, using the access token; it returns an object conforming
    // to Psr\Http\Message\RequestInterface.
    if($id == null && $api_key == null && $api_secret==null){
			$params = array(
        'access_token' => $accessToken->getToken(),  // アクセストークン
        'key' => SLTRANSLATE_KEY,// API Key
        'api_name' => $api_name,
        'api_param' => $api_param,
        'name' => SLTRANSLATE_NAME, // ログインID
        'type' => 'json', // レスポンスタイプ
        'text' => $ja_text, // 以下、APIごとのパラメータ
			);
		}else{
			$params = array(
        'access_token' => $accessToken->getToken(),  // アクセストークン
        'key' => SLTRANSLATE_KEY,// API Key
        'api_name' => $api_name,
        'api_param' => $api_param,
        'name' => $id, // ログインID
        'type' => 'json', // レスポンスタイプ
        'text' => $ja_text, // 以下、APIごとのパラメータ
			);
		}
    

    $request = $provider->getAuthenticatedRequest(
        'POST',
        SLTRANSLATE_URL . '/api/?' . http_build_query($params),// URL + URLパラメータ
        $accessToken,
    );

    $response = $provider->getResponse($request);
    $data = $response->getBody()->getContents();
    $json=json_decode($data,true);
		$ret_data=array('code'=>$json['resultset']['code'],'text'=>$json['resultset']['result']['text']);
    return $ret_data;
  } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

      // Failed to get the access token or user details.
      //exit($e->getMessage());
			$ret_data=array('code'=>500,'text'=>$e->getMessage());
      return $ret_data;

  }
}

//google翻訳
function sl_trans_exec_google($ja_text, $prid=null, $api_key=null){
	try{
		if($prid == null && $api_key == null){
			$translate = new TranslateClient([
				'projectId' => SLTRANSLATE_GOOGLE_PRID,
				'key' => SLTRANSLATE_GOOGLE_API,
			]);
		}else{//接続テスト用
			$translate = new TranslateClient([
				'projectId' => $prid,
				'key' => $api_key,
			]);
		}
		
		
		//翻訳したい言語を指定。今回は「日本語→英語」なので「en」
		$lang = "en";
		
		//翻訳開始
		$result = $translate->translate($ja_text, [
			'target' => $lang,
		]);
		$ret_data=array('code'=>0,'text'=>$result['text']);
		
    return $ret_data;
	}
	catch(\Exception $e){
		$ret_json=json_decode($e->getMessage());
		$ret_data=array('code'=>$ret_json->error->code,'text'=>__($ret_json->error->message));
	}
}