<?php
/**
 * Plugin Name: HQS Meili Search Pro (Versão API Interna)
 * Description: Usa a mesma lógica do dropdown (REST API) para a página de resultados. Zero erros de Host/CORS.
 * Version: 4.0
 * Author: Rui & Copilot
 */

if (!defined('ABSPATH')) exit;

// ====== 1. INCLUDES ======
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-rest-v2.php';
require_once __DIR__ . '/includes/class-indexer.php';
require_once __DIR__ . '/includes/class-logger.php';

// Ativação e Setup
register_activation_hook(__FILE__, function(){
    global $wpdb;
    $table = $wpdb->prefix . 'hqs_search_queries';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, term VARCHAR(255) NOT NULL, hits INT UNSIGNED DEFAULT 0, device VARCHAR(32) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, user_id BIGINT UNSIGNED NULL, PRIMARY KEY (id), INDEX(term), INDEX(created_at)) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

add_action('after_setup_theme', function(){ add_image_size('hqs-search-thumb', 320, 320, false); });

// ====== 2. SCRIPTS ======
add_action('wp_enqueue_scripts', function(){
    // Dropdown (Mantém-se igual)
    wp_enqueue_style('hqs-meili-search', plugins_url('assets/css/search.css', __FILE__), [], '1.0.9');
    wp_enqueue_script('hqs-meili-search', plugins_url('assets/js/search.js', __FILE__), [], '1.0.9', true);
    wp_enqueue_style('hqs-mobile', plugins_url('assets/css/mobile.css', __FILE__), [], '1.0.1');
    wp_enqueue_script('hqs-mobile', plugins_url('assets/js/mobile.js', __FILE__), ['hqs-meili-search'], '1.0.1', true);
    
    $category_base = get_option('woocommerce_category_slug', 'product-category');
    $category_base = '/' . trim($category_base, '/') . '/';
    
    wp_localize_script('hqs-meili-search', 'HQS_SEARCH', [
        'endpoint'      => site_url('/wp-json/hqs/v2/search?q='),
        'log_endpoint'  => site_url('/wp-json/hqs/v2/log'),
        'search_page'   => site_url('/pesquisa/?q='), 
        'category_base' => trailingslashit($category_base),
        'currency'      => get_woocommerce_currency(),
    ]);
});

// Shortcode Dropdown
add_shortcode('hqs_meili_search', function(){ ob_start(); ?>
    <div class="hqs-meili-wrapper"><input type="search" class="hqs-meili-input" placeholder="Pesquisar produtos…" aria-label="Pesquisar" /></div>
<?php return ob_get_clean(); });

// ====== 3. PÁGINA DE RESULTADOS (VIA API INTERNA) ======
// SUBSTITUI a função hqs_render_search_page() no teu ficheiro principal

function hqs_render_search_page() {
    
    // Carregar assets com versão atualizada para limpar cache
    wp_enqueue_script('hqs-meili-search-js', plugin_dir_url(__FILE__) . 'assets/js/search-results.js', array('jquery'), '7.5', true);
    wp_enqueue_style('hqs-search-style', plugin_dir_url(__FILE__) . 'assets/css/search-style.css', array(), '7.5');

    // Passamos o endpoint da API
    wp_localize_script('hqs-meili-search-js', 'hqsData', array(
        'apiUrl' => site_url('/wp-json/hqs/v2/search'),
        'nonce'  => wp_create_nonce('wp_rest')
    ));

    ob_start();
    ?>
    <div id="hqs-search-app">
        
        <div class="hqs-header">
            <p class="hqs-title">
                Resultados para: <span id="hqs-search-term">...</span>
                <span style="font-size:0.8em; color:#777; margin-left:10px;">(<span id="hqs-total-hits">0</span> produtos)</span>
            </p>
        </div>

        <div class="hqs-layout">
            
            <aside class="hqs-sidebar" style="display:none;">
                <!-- Filtros serão injetados via JS -->
            </aside>

            <main class="hqs-results">
                <div id="hqs-grid" class="hqs-grid-container"></div>
            </main>
            
        </div>
        
    </div>
    <div style="clear:both;"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('hqs_meili_search_page', 'hqs_render_search_page');