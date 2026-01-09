<?php
if (!defined('ABSPATH')) exit;
class HQS_Meili_Indexer {
  public static function build_document(int $id){
    $p = wc_get_product($id); if (!$p) return null;

    // Usar tamanhos otimizados
    $size  = 'hqs-search-thumb';
    $thumb = wp_get_attachment_image_url($p->get_image_id(), $size);
    if (!$thumb) { $thumb = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail'); }

    $gallery_ids = $p->get_gallery_image_ids();
    $hover = $gallery_ids ? wp_get_attachment_image_url($gallery_ids[0], $size) : $thumb;
    if (!$hover && $gallery_ids) { $hover = wp_get_attachment_image_url($gallery_ids[0], 'woocommerce_thumbnail'); }

    $cats  = [];
    $terms = get_the_terms($id, 'product_cat');
    if ($terms && !is_wp_error($terms)) { foreach ($terms as $t) { $cats[] = $t->name; } }

    $brand   = $p->get_attribute('pa_marca');
    $short   = wp_strip_all_tags($p->get_short_description());
    $full    = wp_strip_all_tags($p->get_description());
    $regular = (float) $p->get_regular_price();
    $sale    = (float) $p->get_sale_price();
    $price_r = (float) $p->get_price();
    $price   = $price_r ?: $regular;

    return [
      'id'                => $id,
      'title'             => $p->get_name(),
      'url'               => get_permalink($id),
      'thumbnail'         => $thumb,
      'images'            => array_filter([$hover]), // 1º da galeria como hover
      'brand'             => $brand,
      'sku'               => $p->get_sku(),
      'categories'        => $cats,
      'short_description' => mb_substr($short ?: $full, 0, 160),
      'full_description'  => $full,
      'price'             => $price,
      'regular_price'     => $regular,
      'sale_price'        => $sale,
      'stock_status'      => $p->get_stock_status(),
      'created_at'        => get_post_time('c', true, $id),
      'product_type'      => $p->get_type(),
    ];
  }

  public static function send_batch(array $docs, array $opt){
    $url = trailingslashit($opt['host']) . 'indexes/' . $opt['index'] . '/documents';
    $headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $opt['admin_key'], 'X-Meili-API-Key' => $opt['admin_key'] ];
    $res = wp_remote_post($url, [ 'headers' => $headers, 'body' => wp_json_encode($docs), 'timeout' => 30 ]);
    return !is_wp_error($res);
  }
}
