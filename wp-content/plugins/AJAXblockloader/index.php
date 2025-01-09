<?php
/**
 * Plugin Name: AJAX Block Loader
 * Description: Convierte bloques de WordPress en llamados AJAX para crear una experiencia tipo SPA.
 * Version: 1.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    exit;
}

// Registrar los scripts necesarios
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('ajax-block-loader', plugin_dir_url(__FILE__) . 'js/ajax-block-loader.js', ['jquery'], '1.0', true);
    wp_localize_script('ajax-block-loader', 'ajaxBlockLoader', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'content_selector' => get_option('ajax_block_loader_selector', '#main-content'),
        'loading_message' => get_option('ajax_block_loader_loading_message', '<p>Cargando...</p>'),
        'error_message' => get_option('ajax_block_loader_error_message', '<p>Error al cargar el contenido.</p>'),
        'network_error_message' => get_option('ajax_block_loader_network_error_message', '<p>Error de red.</p>'),
    ]);
});

// Endpoint AJAX para devolver bloques
add_action('wp_ajax_nopriv_load_blocks', 'ajax_block_loader_callback');
add_action('wp_ajax_load_blocks', 'ajax_block_loader_callback');


function ajax_block_loader_callback() {
    // Obtener la URL solicitada
    $requested_url = isset($_POST['url']) ? sanitize_text_field(wp_unslash($_POST['url'])) : '';
    if (!$requested_url) {
        wp_send_json_error(['message' => 'URL no especificada']);
    }

    // Parsear la URL para obtener el path
    $parsed_url = parse_url($requested_url);
    $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';

    // Detectar subdirectorio del sitio
    $site_url = site_url();
    $parsed_site_url = parse_url($site_url);
    $subdirectory = isset($parsed_site_url['path']) ? trim($parsed_site_url['path'], '/') : '';

    // Eliminar el subdirectorio del path si existe
    if (!empty($subdirectory) && strpos($path, $subdirectory) === 0) {
        $path = substr($path, strlen($subdirectory));
    }

    $path = trim($path, '/'); // Limpiar el path final

    // Configurar la consulta para WordPress
    $query_args = [
        'pagename' => $path, // Buscar por slug de página
    ];

    $query = new WP_Query($query_args);

    // Verificar si se encontró contenido
    if ($query->have_posts()) {
        $query->the_post();

        // Analizar y procesar bloques manualmente
        $raw_content = get_the_content();
        $parsed_blocks = parse_blocks($raw_content);
        $rendered_content = ''; 
        foreach ($parsed_blocks as $block) { 
            $rendered_content .= render_block($block); // Renderiza cada bloque
        }
         

        wp_send_json_success(['content' => $rendered_content]);
    } else {
        wp_send_json_error(['message' => 'Página no encontrada']);
    }
}

// Registrar el menú de configuración
add_action('admin_menu', function () {
    add_options_page(
        'Configuración AJAX Block Loader',
        'AJAX Block Loader',
        'manage_options',
        'ajax-block-loader-settings',
        'ajax_block_loader_settings_page'
    );
});

// Registrar la configuración
add_action('admin_init', function () {
    register_setting('ajax_block_loader_settings', 'ajax_block_loader_selector');
    register_setting('ajax_block_loader_settings', 'ajax_block_loader_loading_message');
    register_setting('ajax_block_loader_settings', 'ajax_block_loader_error_message');
    register_setting('ajax_block_loader_settings', 'ajax_block_loader_network_error_message');
});

// Página de configuración 
function ajax_block_loader_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configuración de AJAX Block Loader</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ajax_block_loader_settings');
            do_settings_sections('ajax_block_loader_settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="ajax_block_loader_selector">Selector CSS del contenedor</label>
                    </th>
                    <td>
                        <input type="text" id="ajax_block_loader_selector" name="ajax_block_loader_selector" 
                               value="<?php echo esc_attr(get_option('ajax_block_loader_selector', '#main-content')); ?>" 
                               class="regular-text" />
                        <p class="description">Especifica el selector CSS del contenedor donde se actualizará el contenido dinámicamente (por ejemplo, <code>#main-content</code>).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="ajax_block_loader_loading_message">Mensaje de carga</label>
                    </th>
                    <td>
                        <input type="text" id="ajax_block_loader_loading_message" name="ajax_block_loader_loading_message" 
                               value="<?php echo esc_attr(get_option('ajax_block_loader_loading_message', '<p>Cargando...</p>')); ?>" 
                               class="regular-text" />
                        <p class="description">Mensaje que se mostrará mientras se carga el contenido (puedes usar HTML).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="ajax_block_loader_error_message">Mensaje de error</label>
                    </th>
                    <td>
                        <input type="text" id="ajax_block_loader_error_message" name="ajax_block_loader_error_message" 
                               value="<?php echo esc_attr(get_option('ajax_block_loader_error_message', '<p>Error al cargar el contenido.</p>')); ?>" 
                               class="regular-text" />
                        <p class="description">Mensaje que se mostrará si ocurre un error al cargar el contenido (puedes usar HTML).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="ajax_block_loader_network_error_message">Mensaje de error de red</label>
                    </th>
                    <td>
                        <input type="text" id="ajax_block_loader_network_error_message" name="ajax_block_loader_network_error_message" 
                               value="<?php echo esc_attr(get_option('ajax_block_loader_network_error_message', '<p>Error de red.</p>')); ?>" 
                               class="regular-text" />
                        <p class="description">Mensaje que se mostrará si ocurre un problema de red (puedes usar HTML).</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

