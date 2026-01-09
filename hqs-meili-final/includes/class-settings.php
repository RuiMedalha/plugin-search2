<?php
if (!defined('ABSPATH')) exit;
class HQS_Meili_Settings {
  const OPT_KEY = 'hqs_meili_settings';
  public static function get(){
    $defaults = [
      'host'       => get_option('hqs_meili_host',''),
      'search_key' => get_option('hqs_meili_search_key',''),
      'admin_key'  => get_option('hqs_meili_admin_key',''),
      'index'      => get_option('hqs_meili_index','products_stage'),
      'distinct'   => 'id',
      'synonyms'   => [],
      'stop_words' => [],
      'typo'       => ['enabled' => true],
    ];
    return wp_parse_args(get_option(self::OPT_KEY, []), $defaults);
  }
  public static function update($d){ update_option(self::OPT_KEY, $d); }
}
