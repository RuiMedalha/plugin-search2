<?php
if (!defined('ABSPATH')) exit;
class HQS_Meili_Admin {
  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('admin_post_hqs_meili_save', [__CLASS__, 'save']);
    add_action('admin_post_hqs_meili_push_settings', [__CLASS__, 'push_settings']);
    add_action('admin_post_hqs_meili_reindex', [__CLASS__, 'reindex']);
    add_action('admin_post_hqs_meili_test', [__CLASS__, 'test']);
  }
  public static function menu(){ add_menu_page('HQS Meili Search','Meili Search','manage_options','hqs-meili',[__CLASS__,'render'],'dashicons-search',58); }
  public static function render(){ if(!current_user_can('manage_options')) return; $opt=HQS_Meili_Settings::get(); ?>
  <div class="wrap"><h1>HQS Meili Search (v2)</h1>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"><?php wp_nonce_field('hqs_meili_save'); ?><input type="hidden" name="action" value="hqs_meili_save" />
      <table class="form-table">
        <tr><th>Host</th><td><input type="url" name="host" value="<?php echo esc_attr($opt['host']); ?>" class="regular-text" /></td></tr>
        <tr><th>Search Key</th><td><input type="text" name="search_key" value="<?php echo esc_attr($opt['search_key']); ?>" class="regular-text" /></td></tr>
        <tr><th>Admin Key</th><td><input type="text" name="admin_key" value="<?php echo esc_attr($opt['admin_key']); ?>" class="regular-text" /></td></tr>
        <tr><th>Index</th><td><input type="text" name="index" value="<?php echo esc_attr($opt['index']); ?>" class="regular-text" /></td></tr>
        <tr><th>Distinct</th><td><input type="text" name="distinct" value="<?php echo esc_attr($opt['distinct']); ?>" /></td></tr>
        <tr><th>Sinónimos (JSON)</th><td><textarea name="synonyms" rows="6" class="large-text"><?php echo esc_textarea(wp_json_encode($opt['synonyms'], JSON_PRETTY_PRINT)); ?></textarea></td></tr>
        <tr><th>Stop-words (JSON)</th><td><textarea name="stop_words" rows="4" class="large-text"><?php echo esc_textarea(wp_json_encode($opt['stop_words'], JSON_PRETTY_PRINT)); ?></textarea></td></tr>
        <tr><th>Typo Tolerance</th><td><label><input type="checkbox" name="typo_enabled" value="1" <?php checked(($opt['typo']['enabled'] ?? true)); ?> /> Ativar</label></td></tr>
      </table>
      <p><button class="button button-primary" type="submit">Guardar</button></p>
    </form>
    <hr />
    <h2>Operações Meili</h2>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block;margin-right:10px;">
      <?php wp_nonce_field('hqs_meili_push_settings'); ?><input type="hidden" name="action" value="hqs_meili_push_settings" />
      <button class="button" type="submit">Aplicar Settings ao Índice</button>
    </form>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block;">
      <?php wp_nonce_field('hqs_meili_reindex'); ?><input type="hidden" name="action" value="hqs_meili_reindex" />
      <button class="button" type="submit">Reindexar Produtos</button>
    </form>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block;margin-left:10px;">
      <?php wp_nonce_field('hqs_meili_test'); ?><input type="hidden" name="action" value="hqs_meili_test" />
      <button class="button" type="submit">Testar ligação</button>
    </form>
    <?php if(isset($_GET['hqs_test'])) echo '<div class="notice notice-info"><p>'.esc_html($_GET['hqs_test']).'</p></div>'; ?>
  </div><?php }

  public static function save(){ if(!current_user_can('manage_options')||!check_admin_referer('hqs_meili_save')) wp_die('Unauthorized'); $o=HQS_Meili_Settings::get();
    $o['host']=esc_url_raw($_POST['host']??''); $o['search_key']=sanitize_text_field($_POST['search_key']??''); $o['admin_key']=sanitize_text_field($_POST['admin_key']??''); $o['index']=sanitize_text_field($_POST['index']??'');
    $o['distinct']=sanitize_text_field($_POST['distinct']??'id'); $o['synonyms']=json_decode(stripslashes($_POST['synonyms']??'[]'),true); $o['stop_words']=json_decode(stripslashes($_POST['stop_words']??'[]'),true); $o['typo']=['enabled'=>!empty($_POST['typo_enabled'])];
    HQS_Meili_Settings::update($o); wp_redirect(admin_url('admin.php?page=hqs-meili')); exit; }

  public static function push_settings(){ if(!current_user_can('manage_options')||!check_admin_referer('hqs_meili_push_settings')) wp_die('Unauthorized'); $o=HQS_Meili_Settings::get();
    $url = trailingslashit($o['host']).'indexes/'.$o['index'].'/settings'; $body=[
      'searchableAttributes'=>['title','brand','sku','categories','short_description','full_description'],
      'filterableAttributes'=>['categories','brand','stock_status','price'],
      'sortableAttributes'=>['price','created_at'], 'displayedAttributes'=>['*'], 'distinctAttribute'=>$o['distinct'],
      'synonyms'=>$o['synonyms'], 'stopWords'=>$o['stop_words'], 'typoTolerance'=>$o['typo'], 'rankingRules'=>['words','typo','proximity','attribute','exactness','sort']
    ];
    $headers=['Content-Type'=>'application/json','Authorization'=>'Bearer '.$o['admin_key'],'X-Meili-API-Key'=>$o['admin_key']];
    $res=wp_remote_put($url,['headers'=>$headers,'body'=>wp_json_encode($body),'timeout'=>15]); if(is_wp_error($res)) wp_die('Falha ao aplicar settings: '.$res->get_error_message());
    wp_redirect(admin_url('admin.php?page=hqs-meili')); exit; }

  public static function reindex(){ if(!current_user_can('manage_woocommerce')||!check_admin_referer('hqs_meili_reindex')) wp_die('Unauthorized'); $o=HQS_Meili_Settings::get();
    $ids=get_posts(['post_type'=>'product','fields'=>'ids','posts_per_page'=>-1]); $batch=[];
    foreach($ids as $id){ $doc=HQS_Meili_Indexer::build_document((int)$id); if($doc) $batch[]=$doc; if(count($batch)>=500){ HQS_Meili_Indexer::send_batch($batch,$o); $batch=[]; } }
    if(!empty($batch)) HQS_Meili_Indexer::send_batch($batch,$o); wp_redirect(admin_url('admin.php?page=hqs-meili')); exit; }

  public static function test(){ if(!current_user_can('manage_options')||!check_admin_referer('hqs_meili_test')) wp_die('Unauthorized'); $o=HQS_Meili_Settings::get(); $url = trailingslashit($o['host']).'indexes/'.$o['index'].'/stats'; $headers=['Authorization'=>'Bearer '.$o['admin_key'],'X-Meili-API-Key'=>$o['admin_key']];
    $res=wp_remote_get($url,['headers'=>$headers,'timeout'=>8]); $msg=''; if(is_wp_error($res)) $msg='Erro: '.$res->get_error_message(); else $msg='Stats: '.wp_remote_retrieve_body($res);
    wp_redirect(admin_url('admin.php?page=hqs-meili&hqs_test='.urlencode($msg))); exit; }
}
HQS_Meili_Admin::init();
