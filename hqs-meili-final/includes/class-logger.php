<?php
if (!defined('ABSPATH')) exit;
class HQS_Meili_Logger {
  public static function log(string $term,int $hits=0,string $device='',?int $user_id=null){
    if (mb_strlen($term) < 2) return;
    global $wpdb; $table = $wpdb->prefix.'hqs_search_queries';
    $wpdb->insert($table, [ 'term'=>$term, 'hits'=>$hits, 'device'=>$device, 'user_id'=>$user_id, 'created_at'=>current_time('mysql') ], [ '%s','%d','%s','%d','%s' ]);
  }
}
