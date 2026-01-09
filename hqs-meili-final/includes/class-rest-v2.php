<?php
if (!defined('ABSPATH')) exit;

class HQS_Meili_REST_V2 {
    
    public static function init() {
        add_action('rest_api_init', function() {
            register_rest_route('hqs/v2', '/search', array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'search'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'q'          => array('required' => true, 'type' => 'string'),
                    'limit'      => array('required' => false, 'type' => 'integer', 'default' => 24),
                    'offset'     => array('required' => false, 'type' => 'integer', 'default' => 0),
                    'categories' => array('required' => false, 'type' => 'string'),
                    'brands'     => array('required' => false, 'type' => 'string'),
                    'price_min'  => array('required' => false, 'type' => 'number'),
                    'price_max'  => array('required' => false, 'type' => 'number'),
                )
            ));
            
            register_rest_route('hqs/v2', '/log', array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'log'),
                'permission_callback' => '__return_true'
            ));
        });
    }
    
    public static function search(WP_REST_Request $req) {
        $q = sanitize_text_field(trim((string) $req->get_param('q')));
        $limit = max(1, min(100, intval($req->get_param('limit') ? $req->get_param('limit') : 24)));
        $offset = max(0, intval($req->get_param('offset') ? $req->get_param('offset') : 0));
        
        $filter_categories = sanitize_text_field((string) $req->get_param('categories'));
        $filter_brands = sanitize_text_field((string) $req->get_param('brands'));
        $price_min = $req->get_param('price_min');
        $price_max = $req->get_param('price_max');
        
        if (mb_strlen($q) < 2) {
            return array(
                'query'      => $q,
                'products'   => array(),
                'categories' => array(),
                'brands'     => array(),
                'total_hits' => 0,
                'offset'     => $offset,
                'limit'      => $limit,
                'has_more'   => false
            );
        }
        
        $o = HQS_Meili_Settings::get();
        $url = trailingslashit($o['host']) . 'indexes/' . $o['index'] . '/search';
        
        $body = array(
            'q'      => $q,
            'limit'  => $limit,
            'offset' => $offset,
            'facets' => array('categories', 'brand', 'price')
        );
        
        $filters = array();
        
        if (!empty($filter_categories)) {
            $cats = array_map('trim', explode(',', $filter_categories));
            $cat_filters = array();
            foreach ($cats as $cat) {
                if (!empty($cat)) {
                    $cat_filters[] = 'categories = "' . addslashes($cat) . '"';
                }
            }
            if (!empty($cat_filters)) {
                $filters[] = '(' . implode(' OR ', $cat_filters) . ')';
            }
        }
        
        if (!empty($filter_brands)) {
            $brands = array_map('trim', explode(',', $filter_brands));
            $brand_filters = array();
            foreach ($brands as $brand) {
                if (!empty($brand)) {
                    $brand_filters[] = 'brand = "' . addslashes($brand) . '"';
                }
            }
            if (!empty($brand_filters)) {
                $filters[] = '(' . implode(' OR ', $brand_filters) . ')';
            }
        }
        
        if ($price_min !== null && is_numeric($price_min)) {
            $filters[] = 'price >= ' . floatval($price_min);
        }
        
        if ($price_max !== null && is_numeric($price_max)) {
            $filters[] = 'price <= ' . floatval($price_max);
        }
        
        if (!empty($filters)) {
            $body['filter'] = implode(' AND ', $filters);
        }
        
        $headers = array(
            'Content-Type'    => 'application/json',
            'Authorization'   => 'Bearer ' . $o['search_key'],
            'X-Meili-API-Key' => $o['search_key']
        );
        
        $res = wp_remote_post($url, array(
            'headers' => $headers,
            'body'    => wp_json_encode($body),
            'timeout' => 10
        ));
        
        if (is_wp_error($res)) {
            return new WP_Error('hqs_error', 'Erro ao conectar Meilisearch', array('status' => 500));
        }
        
        $json = json_decode(wp_remote_retrieve_body($res), true);
        
        if (!$json) {
            return new WP_Error('hqs_error', 'Resposta invalida do Meilisearch', array('status' => 500));
        }
        
        $hits = isset($json['hits']) ? $json['hits'] : array();
        $products = array();
        
        foreach ($hits as $h) {
            if (empty($h['title']) || empty($h['url']) || empty($h['thumbnail'])) {
                $doc = HQS_Meili_Indexer::build_document(intval(isset($h['id']) ? $h['id'] : 0));
                if ($doc) {
                    $products[] = $doc;
                }
                continue;
            }
            
            $products[] = array(
                'id'                => intval(isset($h['id']) ? $h['id'] : 0),
                'title'             => isset($h['title']) ? $h['title'] : '',
                'thumbnail'         => isset($h['thumbnail']) ? $h['thumbnail'] : '',
                'images'            => isset($h['images']) ? $h['images'] : array(),
                'price'             => floatval(isset($h['price']) ? $h['price'] : 0),
                'regular_price'     => floatval(isset($h['regular_price']) ? $h['regular_price'] : 0),
                'sale_price'        => floatval(isset($h['sale_price']) ? $h['sale_price'] : 0),
                'sku'               => isset($h['sku']) ? $h['sku'] : '',
                'brand'             => isset($h['brand']) ? $h['brand'] : '',
                'url'               => isset($h['url']) ? $h['url'] : '',
                'short_description' => isset($h['short_description']) ? $h['short_description'] : '',
                'categories'        => isset($h['categories']) ? $h['categories'] : array()
            );
        }
        
        $facet_distribution = isset($json['facetDistribution']) ? $json['facetDistribution'] : array();
        
        $categories_facet = array();
        if (isset($facet_distribution['categories'])) {
            foreach ($facet_distribution['categories'] as $cat => $count) {
                $categories_facet[] = array('name' => $cat, 'count' => $count);
            }
            usort($categories_facet, function($a, $b) {
                return $b['count'] - $a['count'];
            });
        }
        
        $brands_facet = array();
        if (isset($facet_distribution['brand'])) {
            foreach ($facet_distribution['brand'] as $brand => $count) {
                if (!empty($brand)) {
                    $brands_facet[] = array('name' => $brand, 'count' => $count);
                }
            }
            usort($brands_facet, function($a, $b) {
                return $b['count'] - $a['count'];
            });
        }
        
        $total_hits = intval(isset($json['estimatedTotalHits']) ? $json['estimatedTotalHits'] : count($products));
        
        // Extrair range de preços do facetStats
        $price_range = null;
        $facet_stats = isset($json['facetStats']) ? $json['facetStats'] : array();
        if (isset($facet_stats['price'])) {
            $price_range = array(
                'min' => floor(isset($facet_stats['price']['min']) ? $facet_stats['price']['min'] : 0),
                'max' => ceil(isset($facet_stats['price']['max']) ? $facet_stats['price']['max'] : 10000)
            );
        }
        
        // Extrair marcas e categorias dos produtos (para popular)
        $all_brands = array();
        $all_cats = array();
        foreach ($products as $p) {
            if (!empty($p['brand'])) {
                $all_brands[] = $p['brand'];
            }
            if (!empty($p['categories'])) {
                $all_cats = array_merge($all_cats, $p['categories']);
            }
        }
        $all_brands = array_values(array_unique(array_filter($all_brands)));
        $all_cats = array_values(array_unique(array_filter($all_cats)));
        
        // Popular = marcas + categorias (dos produtos retornados)
        $popular = array_slice(array_values(array_unique(array_merge($all_brands, $all_cats))), 0, 10);
        
        // Categorias simples para dropdown (limitado a 8)
        $categories_simple = array_slice($all_cats, 0, 8);
        
        // Marcas simples para dropdown
        $brands_simple = $all_brands;
        
        return array(
            'query'           => $q,
            'products'        => $products,
            'categories'      => $categories_simple,
            'brands'          => $brands_simple,
            'popular'         => $popular,
            'categories_facet'=> $categories_facet,
            'brands_facet'    => $brands_facet,
            'price_range'     => $price_range,
            'total_hits'      => $total_hits,
            'offset'          => $offset,
            'limit'           => $limit,
            'has_more'        => ($offset + count($products)) < $total_hits
        );
    }
    
    public static function log(WP_REST_Request $req) {
        $term = sanitize_text_field((string) $req->get_param('term'));
        $hits = intval($req->get_param('hits'));
        $dev = sanitize_text_field((string) $req->get_param('device'));
        $uid = get_current_user_id();
        if (!$uid) $uid = null;
        
        HQS_Meili_Logger::log($term, $hits, $dev, $uid);
        
        return array('ok' => true);
    }
}

HQS_Meili_REST_V2::init();