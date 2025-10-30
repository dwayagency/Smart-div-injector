<?php
/**
 * Plugin Name: Smart Div Injector
 * Description: Inserisce un frammento di codice dentro una div specifica, in base a articolo, pagina e/o categoria. Supporta regole multiple.
 * Version: 2.0.0
 * Author: DWAY SRL
 * Author URI: https://dway.agency
 * License: GPL-2.0+
 * Text Domain: smart-div-injector
 * Network: true
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Smart_Div_Injector {
    const OPTION_KEY = 'sdi_rules'; // Cambiato da sdi_options a sdi_rules (array di regole)

    public function __construct() {
        // Admin
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Multisite: aggiungi menu anche nel Network Admin (opzionale)
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', [ $this, 'add_network_settings_page' ] );
        }

        // Frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_frontend' ] );
    }
    
    /**
     * Enqueue admin CSS
     */
    public function enqueue_admin_assets( $hook ) {
        // Carica solo nella nostra pagina
        if ( $hook !== 'toplevel_page_smart-div-injector' ) {
            return;
        }
        
        wp_enqueue_style( 
            'sdi-admin-style', 
            plugins_url( 'admin-style.css', __FILE__ ), 
            [], 
            '2.0.0' 
        );
    }
    
    /**
     * Ottieni tutte le regole salvate
     */
    public function get_rules() {
        $rules = get_option( self::OPTION_KEY, [] );
        return is_array( $rules ) ? $rules : [];
    }
    
    /**
     * Salva tutte le regole
     */
    public function save_rules( $rules ) {
        update_option( self::OPTION_KEY, $rules );
    }
    
    /**
     * Ottieni una singola regola per ID
     */
    public function get_rule( $rule_id ) {
        $rules = $this->get_rules();
        return isset( $rules[ $rule_id ] ) ? $rules[ $rule_id ] : null;
    }
    
    /**
     * Genera un nuovo ID univoco per una regola
     */
    private function generate_rule_id() {
        return 'rule_' . time() . '_' . wp_rand( 1000, 9999 );
    }
    
    /**
     * Verifica se il plugin è attivato a livello di network
     */
    public function is_network_activated() {
        if ( ! is_multisite() ) {
            return false;
        }
        
        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }
        
        return is_plugin_active_for_network( plugin_basename( __FILE__ ) );
    }

    /** -------------------- ADMIN -------------------- */
    public function add_settings_page() {
        add_menu_page(
            'Smart Div Injector',              // Page title
            'Smart Div Injector',              // Menu title
            'manage_options',                   // Capability
            'smart-div-injector',              // Menu slug
            [ $this, 'render_settings_page' ], // Callback function
            'dashicons-code-standards',        // Icon
            65                                  // Position (after Plugins)
        );
    }
    
    /**
     * Gestisce le azioni (aggiungi, modifica, elimina regole)
     */
    public function handle_actions() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'smart-div-injector' ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Verifica nonce per sicurezza
        if ( isset( $_POST['sdi_action'] ) && ! isset( $_POST['sdi_nonce'] ) ) {
            return;
        }
        
        if ( isset( $_POST['sdi_nonce'] ) && ! wp_verify_nonce( $_POST['sdi_nonce'], 'sdi_rule_action' ) ) {
            wp_die( 'Nonce verification failed' );
        }
        
        // Aggiungi nuova regola
        if ( isset( $_POST['sdi_action'] ) && $_POST['sdi_action'] === 'add' ) {
            $this->save_rule_from_post();
            wp_redirect( admin_url( 'admin.php?page=smart-div-injector&message=added' ) );
            exit;
        }
        
        // Modifica regola esistente
        if ( isset( $_POST['sdi_action'] ) && $_POST['sdi_action'] === 'edit' && isset( $_POST['rule_id'] ) ) {
            $this->update_rule_from_post( $_POST['rule_id'] );
            wp_redirect( admin_url( 'admin.php?page=smart-div-injector&message=updated' ) );
            exit;
        }
        
        // Elimina regola
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['rule_id'] ) ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_rule_' . $_GET['rule_id'] ) ) {
                wp_die( 'Nonce verification failed' );
            }
            $this->delete_rule( $_GET['rule_id'] );
            wp_redirect( admin_url( 'admin.php?page=smart-div-injector&message=deleted' ) );
            exit;
        }
        
        // Duplica regola
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'duplicate' && isset( $_GET['rule_id'] ) ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'duplicate_rule_' . $_GET['rule_id'] ) ) {
                wp_die( 'Nonce verification failed' );
            }
            $this->duplicate_rule( $_GET['rule_id'] );
            wp_redirect( admin_url( 'admin.php?page=smart-div-injector&message=duplicated' ) );
            exit;
        }
    }
    
    /**
     * Salva una nuova regola dai dati POST
     */
    private function save_rule_from_post() {
        $rule = $this->sanitize_rule_data( $_POST );
        $rule_id = $this->generate_rule_id();
        
        $rules = $this->get_rules();
        $rules[ $rule_id ] = $rule;
        $this->save_rules( $rules );
    }
    
    /**
     * Aggiorna una regola esistente
     */
    private function update_rule_from_post( $rule_id ) {
        $rule = $this->sanitize_rule_data( $_POST );
        
        $rules = $this->get_rules();
        if ( isset( $rules[ $rule_id ] ) ) {
            $rules[ $rule_id ] = $rule;
            $this->save_rules( $rules );
        }
    }
    
    /**
     * Elimina una regola
     */
    private function delete_rule( $rule_id ) {
        $rules = $this->get_rules();
        if ( isset( $rules[ $rule_id ] ) ) {
            unset( $rules[ $rule_id ] );
            $this->save_rules( $rules );
        }
    }
    
    /**
     * Duplica una regola
     */
    private function duplicate_rule( $rule_id ) {
        $rules = $this->get_rules();
        if ( isset( $rules[ $rule_id ] ) ) {
            $new_rule = $rules[ $rule_id ];
            $new_rule['name'] = $new_rule['name'] . ' (copia)';
            $new_rule_id = $this->generate_rule_id();
            $rules[ $new_rule_id ] = $new_rule;
            $this->save_rules( $rules );
        }
    }
    
    /**
     * Sanitizza i dati della regola
     */
    private function sanitize_rule_data( $data ) {
        $valid_modes = [ 'single_posts', 'category_archive', 'single_posts_category', 'page' ];
        $valid_positions = [ 
            'append', 'prepend', 'before', 'after', 'replace',
            'before_post', 'before_content', 'after_content',
            'before_paragraph', 'after_paragraph',
            'before_image', 'after_image'
        ];
        $valid_devices = [ 'both', 'desktop', 'mobile' ];
        
        $rule = [
            'name'              => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : 'Regola senza nome',
            'active'            => isset( $data['active'] ) && $data['active'] === '1',
            'match_mode'        => in_array( $data['match_mode'] ?? 'single_posts', $valid_modes, true ) ? $data['match_mode'] : 'single_posts',
            'page_id'           => isset( $data['page_id'] ) ? absint( $data['page_id'] ) : 0,
            'category_id'       => isset( $data['category_id'] ) ? absint( $data['category_id'] ) : 0,
            'selector'          => isset( $data['selector'] ) ? sanitize_text_field( $data['selector'] ) : '',
            'position'          => in_array( $data['position'] ?? 'append', $valid_positions, true ) ? $data['position'] : 'append',
            'paragraph_number'  => isset( $data['paragraph_number'] ) ? absint( $data['paragraph_number'] ) : 1,
            'device_target'     => in_array( $data['device_target'] ?? 'both', $valid_devices, true ) ? $data['device_target'] : 'both',
        ];
        
        // Sanitizza il codice
        $code = $data['code'] ?? '';
        
        // Rimuovi escape automatici aggiunti da editor o copia/incolla
        $code = stripslashes( $code );
        
        if ( current_user_can( 'unfiltered_html' ) ) {
            // Gli amministratori possono inserire qualsiasi codice
            $rule['code'] = $code;
        } else {
            // Per altri utenti, usa una whitelist permissiva ma sicura
            $allowed_html = wp_kses_allowed_html( 'post' );
            
            // Aggiungi tag e attributi necessari per gli script
            $allowed_html['script'] = [
                'src'           => true,
                'type'          => true,
                'async'         => true,
                'defer'         => true,
                'crossorigin'   => true,
                'integrity'     => true,
                'charset'       => true,
                'id'            => true,
                'class'         => true,
            ];
            $allowed_html['iframe'] = [
                'src'           => true,
                'width'         => true,
                'height'        => true,
                'frameborder'   => true,
                'allowfullscreen' => true,
                'style'         => true,
                'id'            => true,
                'class'         => true,
            ];
            
            $rule['code'] = wp_kses( $code, $allowed_html );
        }
        
        return $rule;
    }
    
    /**
     * Aggiungi pagina nel Network Admin (per multisite)
     */
    public function add_network_settings_page() {
        add_menu_page(
            'Smart Div Injector',                      // Page title
            'Smart Div Injector',                      // Menu title
            'manage_network_options',                  // Capability
            'smart-div-injector-network',              // Menu slug
            [ $this, 'render_network_settings_page' ], // Callback function
            'dashicons-code-standards',                // Icon
            65                                          // Position
        );
    }

    /**
     * Render della pagina principale (lista regole o edit regola)
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Determina quale vista mostrare
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['rule_id'] ) ) {
            $this->render_edit_rule_page( $_GET['rule_id'] );
        } elseif ( isset( $_GET['action'] ) && $_GET['action'] === 'add' ) {
            $this->render_add_rule_page();
        } else {
            $this->render_rules_list_page();
        }
    }
    
    /**
     * Render della lista delle regole
     */
    private function render_rules_list_page() {
        $rules = $this->get_rules();
        
        // Messaggi di conferma
        $message = isset( $_GET['message'] ) ? $_GET['message'] : '';
        
        ?>
        <div class="wrap">
            <div class="sdi-header">
                <h1>
                    <span class="dashicons dashicons-admin-generic"></span>
                    Smart Div Injector
                </h1>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector&action=add' ) ); ?>" class="button sdi-add-button">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Aggiungi Nuova Regola
                </a>
            </div>
            
            <?php if ( is_multisite() ) : ?>
                <div class="notice notice-info">
                    <p>
                        <strong>Multisite:</strong> Stai configurando le regole per questo sito specifico.
                        <?php if ( current_user_can( 'manage_network_options' ) ) : ?>
                            Puoi vedere lo stato di tutti i siti dalla <a href="<?php echo esc_url( network_admin_url( 'admin.php?page=smart-div-injector-network' ) ); ?>">pagina Network Admin</a>.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ( $message === 'added' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ Regola aggiunta con successo!</strong></p>
                </div>
            <?php elseif ( $message === 'updated' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ Regola aggiornata con successo!</strong></p>
                </div>
            <?php elseif ( $message === 'deleted' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ Regola eliminata con successo!</strong></p>
                </div>
            <?php elseif ( $message === 'duplicated' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ Regola duplicata con successo!</strong></p>
                </div>
            <?php endif; ?>
            
            <?php if ( empty( $rules ) ) : ?>
                <div class="sdi-empty-state">
                    <span class="dashicons dashicons-welcome-add-page"></span>
                    <h3>Nessuna regola configurata</h3>
                    <p>Inizia creando la tua prima regola di iniezione codice.</p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector&action=add' ) ); ?>" class="button button-primary button-large">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Crea Prima Regola
                    </a>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped sdi-rules-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 100px;">Stato</th>
                            <th scope="col">Nome Regola</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Target</th>
                            <th scope="col" style="width: 120px;">Dispositivo</th>
                            <th scope="col">Selettore CSS</th>
                            <th scope="col" style="width: 240px;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rules as $rule_id => $rule ) : ?>
                            <tr>
                                <td>
                                    <span class="sdi-status-badge <?php echo $rule['active'] ? 'active' : 'inactive'; ?>">
                                        <span class="dashicons dashicons-<?php echo $rule['active'] ? 'yes-alt' : 'dismiss'; ?>"></span>
                                        <?php echo $rule['active'] ? 'Attiva' : 'Non attiva'; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo esc_html( $rule['name'] ); ?></strong></td>
                                <td>
                                    <span class="sdi-type-badge">
                                        <?php 
                                        switch ( $rule['match_mode'] ) {
                                            case 'single_posts':
                                                echo '📄 Tutti gli articoli';
                                                break;
                                            case 'category_archive':
                                                echo '📁 Archivio categoria';
                                                break;
                                            case 'single_posts_category':
                                                echo '🏷️ Articoli per categoria';
                                                break;
                                            case 'page':
                                                echo '📃 Pagina specifica';
                                                break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="sdi-target-info">
                                        <?php 
                                        if ( ( $rule['match_mode'] === 'single_posts_category' || $rule['match_mode'] === 'category_archive' ) && $rule['category_id'] ) {
                                            $cat = get_category( $rule['category_id'] );
                                            echo '<span class="dashicons dashicons-category"></span>';
                                            echo $cat ? esc_html( $cat->name ) : 'Categoria #' . $rule['category_id'];
                                        } elseif ( $rule['match_mode'] === 'page' && $rule['page_id'] ) {
                                            echo '<span class="dashicons dashicons-admin-page"></span>';
                                            echo get_the_title( $rule['page_id'] ) ?: 'Pagina #' . $rule['page_id'];
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $device = $rule['device_target'] ?? 'both';
                                    switch ( $device ) {
                                        case 'desktop':
                                            echo '<span title="Solo Desktop">💻 Desktop</span>';
                                            break;
                                        case 'mobile':
                                            echo '<span title="Solo Mobile">📱 Mobile</span>';
                                            break;
                                        case 'both':
                                        default:
                                            echo '<span title="Desktop e Mobile">📱💻 Entrambi</span>';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td><code class="sdi-code"><?php echo esc_html( $rule['selector'] ); ?></code></td>
                                <td>
                                    <div class="sdi-actions">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector&action=edit&rule_id=' . $rule_id ) ); ?>" class="button sdi-btn-edit">Modifica</a>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=duplicate&rule_id=' . $rule_id ), 'duplicate_rule_' . $rule_id ) ); ?>" class="button sdi-btn-duplicate">Duplica</a>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=delete&rule_id=' . $rule_id ), 'delete_rule_' . $rule_id ) ); ?>" class="button sdi-btn-delete" onclick="return confirm('Sei sicuro di voler eliminare questa regola?');">Elimina</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p class="description" style="margin-top: 20px;">
                <strong>Come funziona:</strong> Ogni regola definisce dove e come inserire il codice. Le regole attive vengono applicate automaticamente sul frontend quando le condizioni sono soddisfatte.
            </p>
        </div>
        <?php
    }
    
    /**
     * Render pagina aggiungi nuova regola
     */
    private function render_add_rule_page() {
        $rule = [
            'name'             => '',
            'active'           => true,
            'match_mode'       => 'single_posts',
            'page_id'          => 0,
            'category_id'      => 0,
            'selector'         => '',
            'position'         => 'append',
            'paragraph_number' => 1,
            'device_target'    => 'both',
            'code'             => ''
        ];
        
        $this->render_rule_form( $rule, 'add', null );
    }
    
    /**
     * Render pagina modifica regola
     */
    private function render_edit_rule_page( $rule_id ) {
        $rule = $this->get_rule( $rule_id );
        
        if ( ! $rule ) {
            ?>
            <div class="wrap">
                <h1>Errore</h1>
                <div class="notice notice-error">
                    <p><strong>Regola non trovata.</strong></p>
                </div>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector' ) ); ?>" class="button">Torna alla lista regole</a></p>
            </div>
            <?php
            return;
        }
        
        $this->render_rule_form( $rule, 'edit', $rule_id );
    }
    
    /**
     * Render del form per aggiungere/modificare una regola
     */
    private function render_rule_form( $rule, $action, $rule_id ) {
        $is_edit = ( $action === 'edit' );
        $page_title = $is_edit ? 'Modifica Regola' : 'Aggiungi Nuova Regola';
        
        ?>
        <div class="wrap">
            <div class="sdi-header">
                <h1>
                    <span class="dashicons dashicons-<?php echo $is_edit ? 'edit' : 'plus-alt'; ?>"></span>
                    <?php echo esc_html( $page_title ); ?>
                </h1>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector' ) ); ?>" class="button">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    Torna alla Lista
                </a>
            </div>
            
            <div class="sdi-form-card">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector' ) ); ?>">
                <?php wp_nonce_field( 'sdi_rule_action', 'sdi_nonce' ); ?>
                <input type="hidden" name="sdi_action" value="<?php echo esc_attr( $action ); ?>">
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule_id ); ?>">
                <?php endif; ?>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="rule_name">Nome regola *</label></th>
                        <td>
                            <input type="text" name="name" id="rule_name" value="<?php echo esc_attr( $rule['name'] ); ?>" class="regular-text" required>
                            <p class="description">Dai un nome descrittivo alla regola (es. "Banner su articoli News")</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="rule_active">Stato</label></th>
                        <td>
                            <div class="sdi-toggle">
                                <input type="checkbox" name="active" id="rule_active" value="1" <?php checked( $rule['active'], true ); ?>>
                                <span class="sdi-toggle-label">Regola attiva</span>
                            </div>
                            <p class="description">Se disattivata, la regola non verrà applicata sul frontend</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="device_target">Dispositivo target *</label></th>
                        <td>
                            <select name="device_target" id="device_target" class="regular-text">
                                <option value="both" <?php selected( $rule['device_target'] ?? 'both', 'both' ); ?>>📱💻 Entrambi (Desktop e Mobile)</option>
                                <option value="desktop" <?php selected( $rule['device_target'] ?? 'both', 'desktop' ); ?>>💻 Solo Desktop</option>
                                <option value="mobile" <?php selected( $rule['device_target'] ?? 'both', 'mobile' ); ?>>📱 Solo Mobile</option>
                            </select>
                            <p class="description">Scegli su quale tipo di dispositivo applicare questa regola</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="match_mode">Tipo di contenuto *</label></th>
                        <td>
                            <select name="match_mode" id="match_mode" onchange="sdiToggleFields()">
                                <option value="single_posts" <?php selected( $rule['match_mode'], 'single_posts' ); ?>>Tutti gli articoli</option>
                                <option value="category_archive" <?php selected( $rule['match_mode'], 'category_archive' ); ?>>Pagina archivio categoria</option>
                                <option value="single_posts_category" <?php selected( $rule['match_mode'], 'single_posts_category' ); ?>>Articoli di una categoria</option>
                                <option value="page" <?php selected( $rule['match_mode'], 'page' ); ?>>Pagina specifica</option>
                            </select>
                            <p class="description">Scegli dove attivare l'iniezione del codice</p>
                        </td>
                    </tr>
                    
                    <tr id="category_row" style="display: none;">
                        <th scope="row"><label for="category_id">Categoria</label></th>
                        <td>
                            <?php
                            $categories = get_categories( [ 'hide_empty' => false, 'number' => 1000 ] );
                            ?>
                            <select name="category_id" id="category_id" class="regular-text">
                                <option value="0">— Seleziona una categoria —</option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $rule['category_id'], $cat->term_id ); ?>>
                                        <?php echo esc_html( $cat->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Seleziona la categoria target per gli articoli</p>
                        </td>
                    </tr>
                    
                    <tr id="page_row" style="display: none;">
                        <th scope="row"><label for="page_id">Pagina</label></th>
                        <td>
                            <?php
                            $page_count = wp_count_posts( 'page' );
                            $total_pages = isset( $page_count->publish ) ? $page_count->publish : 0;
                            $limit = 500;
                            
                            $pages = get_posts( [ 
                                'post_type'   => 'page',
                                'numberposts' => $limit,
                                'post_status' => 'publish',
                                'orderby'     => 'title',
                                'order'       => 'ASC',
                                'fields'      => 'ids'
                            ] );
                            ?>
                            
                            <?php if ( $total_pages > $limit ) : ?>
                                <div class="sdi-notice warning" style="margin-bottom: 15px;">
                                    <p><strong>⚠️ Attenzione:</strong> Il tuo sito ha <strong><?php echo number_format( $total_pages ); ?></strong> pagine. Il dropdown mostra solo le prime <strong><?php echo $limit; ?></strong>.</p>
                                    <p>Se non trovi la pagina, usa il campo ID manuale qui sotto.</p>
                                </div>
                            <?php endif; ?>
                            
                            <select name="page_id" id="page_select" class="regular-text" style="margin-bottom: 10px;">
                                <option value="0">— Seleziona una pagina dal dropdown —</option>
                                <?php foreach ( $pages as $page_id ) : 
                                    $page_title = get_the_title( $page_id );
                                    if ( empty( $page_title ) ) {
                                        $page_title = '(Nessun titolo)';
                                    }
                                ?>
                                    <option value="<?php echo esc_attr( $page_id ); ?>" <?php selected( $rule['page_id'], $page_id ); ?>>
                                        <?php echo esc_html( $page_title . ' (ID: ' . $page_id . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div class="sdi-manual-input">
                                <strong>Oppure inserisci l'ID manualmente:</strong>
                                <div>
                                    <input type="number" 
                                           id="page_manual" 
                                           min="1" 
                                           value="<?php echo esc_attr( $rule['page_id'] > 0 ? $rule['page_id'] : '' ); ?>" 
                                           placeholder="Esempio: 42" />
                                    <button type="button" class="button" onclick="sdiSetPageFromManual()">Usa questo ID</button>
                                </div>
                            </div>
                            
                            <p class="description">Seleziona una pagina dal dropdown oppure inserisci l'ID manualmente</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="selector">Selettore CSS *</label></th>
                        <td>
                            <input type="text" name="selector" id="selector" value="<?php echo esc_attr( $rule['selector'] ); ?>" class="regular-text" placeholder="#id-della-div, .classe, main > .wrap" required>
                            <p class="description">Selettore CSS della div (o elemento) in cui inserire il codice</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="position">Posizione di inserimento *</label></th>
                        <td>
                            <select name="position" id="position" onchange="sdiTogglePositionFields()">
                                <optgroup label="Posizioni Standard (per selettore CSS)">
                                    <option value="append" <?php selected( $rule['position'], 'append' ); ?>>Append (in fondo dentro la div)</option>
                                    <option value="prepend" <?php selected( $rule['position'], 'prepend' ); ?>>Prepend (all'inizio dentro la div)</option>
                                    <option value="before" <?php selected( $rule['position'], 'before' ); ?>>Prima della div</option>
                                    <option value="after" <?php selected( $rule['position'], 'after' ); ?>>Dopo la div</option>
                                    <option value="replace" <?php selected( $rule['position'], 'replace' ); ?>>Sostituisci contenuto della div</option>
                                </optgroup>
                                <optgroup label="Posizioni per Articoli" id="article_positions" style="display: none;">
                                    <option value="before_post" <?php selected( $rule['position'], 'before_post' ); ?>>Prima dell'articolo</option>
                                    <option value="before_content" <?php selected( $rule['position'], 'before_content' ); ?>>Prima del contenuto</option>
                                    <option value="after_content" <?php selected( $rule['position'], 'after_content' ); ?>>Dopo il contenuto</option>
                                    <option value="before_paragraph" <?php selected( $rule['position'], 'before_paragraph' ); ?>>Prima del paragrafo N</option>
                                    <option value="after_paragraph" <?php selected( $rule['position'], 'after_paragraph' ); ?>>Dopo il paragrafo N</option>
                                    <option value="before_image" <?php selected( $rule['position'], 'before_image' ); ?>>Prima della prima immagine</option>
                                    <option value="after_image" <?php selected( $rule['position'], 'after_image' ); ?>>Dopo la prima immagine</option>
                                </optgroup>
                            </select>
                            <p class="description">Scegli dove posizionare il codice</p>
                        </td>
                    </tr>
                    
                    <tr id="paragraph_number_row" style="display: none;">
                        <th scope="row"><label for="paragraph_number">Numero del Paragrafo</label></th>
                        <td>
                            <input type="number" name="paragraph_number" id="paragraph_number" value="<?php echo esc_attr( $rule['paragraph_number'] ?? 1 ); ?>" min="1" max="50" style="width: 100px;">
                            <p class="description">Specifica quale paragrafo (1 = primo paragrafo, 2 = secondo, ecc.)</p>
                        </td>
                    </tr>
                    
                    <tr id="selector_note_row">
                        <td colspan="2">
                            <div class="sdi-notice info" style="margin: 0;">
                                <p><strong>ℹ️ Nota sul Selettore CSS:</strong> Il selettore è necessario per le posizioni standard. Per le posizioni specifiche degli articoli, il selettore viene ignorato.</p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="code">Codice da inserire *</label></th>
                        <td>
                            <textarea name="code" id="code" rows="10" class="large-text code" spellcheck="false" placeholder="<div>Il tuo codice HTML/JS/CSS</div>" required><?php echo esc_textarea( $rule['code'] ); ?></textarea>
                            <p class="description">Il codice verrà inserito tal quale. Solo gli utenti con permesso <code>unfiltered_html</code> possono salvare script non sanitizzati.</p>
                            
                            <?php if ( ! empty( $rule['code'] ) && $is_edit ) : ?>
                                <details style="margin-top: 15px;">
                                    <summary style="cursor: pointer; font-weight: 600; color: #2271b1;">🔍 Debug: Mostra codice salvato nel database</summary>
                                    <div style="margin-top: 10px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1; border-radius: 4px;">
                                        <p style="margin: 0 0 10px 0;"><strong>Questo è esattamente il codice salvato nel database:</strong></p>
                                        <pre style="background: white; padding: 10px; border: 1px solid #ddd; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;"><?php echo htmlspecialchars( $rule['code'], ENT_QUOTES, 'UTF-8' ); ?></pre>
                                        <p style="margin: 10px 0 0 0; color: #d63638;"><strong>⚠️ Attenzione:</strong> Se vedi <code>\"</code> o <code>https:/</code> (un solo slash), il codice è corrotto. Cancella tutto e incolla di nuovo il codice originale da Google.</p>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <div class="sdi-submit-actions">
                    <button type="submit" class="button sdi-btn-primary">
                        <span class="dashicons dashicons-<?php echo $is_edit ? 'update' : 'saved'; ?>"></span>
                        <?php echo $is_edit ? 'Aggiorna Regola' : 'Salva Regola'; ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector' ) ); ?>" class="button sdi-btn-secondary">
                        Annulla
                    </a>
                </div>
            </form>
            </div>
        </div>
        
        <script>
        function sdiToggleFields() {
            var mode = document.getElementById('match_mode').value;
            var pageRow = document.getElementById('page_row');
            var categoryRow = document.getElementById('category_row');
            var articlePositions = document.getElementById('article_positions');
            
            // Nascondi tutto
            if (pageRow) pageRow.style.display = 'none';
            if (categoryRow) categoryRow.style.display = 'none';
            
            // Mostra/nascondi posizioni articoli in base al tipo
            var isArticleMode = (mode === 'single_posts' || mode === 'single_posts_category');
            if (articlePositions) {
                articlePositions.style.display = isArticleMode ? '' : 'none';
            }
            
            // Mostra in base alla selezione
            switch(mode) {
                case 'single_posts':
                    // Nessun campo aggiuntivo
                    break;
                case 'category_archive':
                    if (categoryRow) categoryRow.style.display = 'table-row';
                    break;
                case 'single_posts_category':
                    if (categoryRow) categoryRow.style.display = 'table-row';
                    break;
                case 'page':
                    if (pageRow) pageRow.style.display = 'table-row';
                    break;
            }
            
            // Aggiorna anche la visibilità dei campi posizione
            sdiTogglePositionFields();
        }
        
        function sdiTogglePositionFields() {
            var position = document.getElementById('position').value;
            var paragraphRow = document.getElementById('paragraph_number_row');
            var selectorRow = document.getElementById('selector').closest('tr');
            var selectorNoteRow = document.getElementById('selector_note_row');
            
            // Posizioni che richiedono il numero del paragrafo
            var needsParagraphNumber = (position === 'before_paragraph' || position === 'after_paragraph');
            if (paragraphRow) {
                paragraphRow.style.display = needsParagraphNumber ? 'table-row' : 'none';
            }
            
            // Posizioni specifiche articoli non usano il selettore CSS
            var articlePositions = ['before_post', 'before_content', 'after_content', 'before_paragraph', 'after_paragraph', 'before_image', 'after_image'];
            var usesSelector = !articlePositions.includes(position);
            
            if (selectorRow) {
                if (usesSelector) {
                    selectorRow.style.display = 'table-row';
                    document.getElementById('selector').required = true;
                } else {
                    selectorRow.style.display = 'none';
                    document.getElementById('selector').required = false;
                }
            }
            
            if (selectorNoteRow) {
                selectorNoteRow.style.display = usesSelector ? 'none' : 'table-row';
            }
        }
        
        function sdiSetPageFromManual() {
            var manualInput = document.getElementById('page_manual');
            var select = document.getElementById('page_select');
            var manualValue = manualInput.value;
            
            if (manualValue && manualValue > 0) {
                var optionExists = false;
                for (var i = 0; i < select.options.length; i++) {
                    if (select.options[i].value == manualValue) {
                        select.selectedIndex = i;
                        optionExists = true;
                        break;
                    }
                }
                
                if (!optionExists) {
                    var option = document.createElement('option');
                    option.value = manualValue;
                    option.text = 'ID: ' + manualValue + ' (inserito manualmente)';
                    option.selected = true;
                    select.add(option);
                }
                
                alert('ID pagina impostato: ' + manualValue);
            }
        }
        
        // Sincronizza campo manuale quando si cambia il select
        if (document.getElementById('page_select')) {
            document.getElementById('page_select').addEventListener('change', function() {
                var manualInput = document.getElementById('page_manual');
                if (manualInput) {
                    manualInput.value = this.value > 0 ? this.value : '';
                }
            });
        }
        
        // Esegui al caricamento
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                sdiToggleFields();
                sdiTogglePositionFields();
            });
        } else {
            sdiToggleFields();
            sdiTogglePositionFields();
        }
        </script>
        <?php
    }
    
    /**
     * Pagina impostazioni per Network Admin (multisite)
     */
    public function render_network_settings_page() {
        if ( ! current_user_can( 'manage_network_options' ) ) {
            return;
        }
        
        // Ottieni lista di tutti i siti nella rete
        $sites = get_sites( [ 'number' => 500 ] );
        
        ?>
        <div class="wrap">
            <h1>Smart Div Injector - Network Admin</h1>
            
            <div class="notice notice-info">
                <p>
                    <strong>Modalità Multisite:</strong> Questo plugin è configurato separatamente per ogni sito della rete. 
                    Ogni sito ha le proprie impostazioni indipendenti.
                </p>
            </div>
            
            <h2>Siti nella Rete</h2>
            <p>Di seguito trovi l'elenco di tutti i siti. Clicca su "Vai alle impostazioni" per configurare il plugin per quel sito specifico.</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 50px;">ID</th>
                        <th scope="col">Nome Sito</th>
                        <th scope="col">URL</th>
                        <th scope="col">Stato Plugin</th>
                        <th scope="col">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $sites as $site ) : ?>
                        <?php
                        switch_to_blog( $site->blog_id );
                        $site_name = get_bloginfo( 'name' );
                        $site_url = get_bloginfo( 'url' );
                        $opts = $this->get_options();
                        $is_configured = ! empty( $opts['selector'] ) && ! empty( $opts['code'] );
                        $admin_url = get_admin_url( $site->blog_id, 'admin.php?page=smart-div-injector' );
                        restore_current_blog();
                        ?>
                        <tr>
                            <td><?php echo absint( $site->blog_id ); ?></td>
                            <td><strong><?php echo esc_html( $site_name ); ?></strong></td>
                            <td><a href="<?php echo esc_url( $site_url ); ?>" target="_blank"><?php echo esc_html( $site_url ); ?></a></td>
                            <td>
                                <?php if ( $is_configured ) : ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Configurato
                                <?php else : ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span> Non configurato
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $admin_url ); ?>" class="button button-primary">
                                    Vai alle impostazioni
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <br>
            
            <div class="card">
                <h3>Note per Network Admin</h3>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li>Ogni sito può avere configurazioni completamente diverse</li>
                    <li>Le impostazioni sono salvate nel database di ogni singolo sito</li>
                    <li>Il plugin può essere attivato/disattivato per ogni sito individualmente</li>
                    <li>Per configurare un sito, accedi alle sue impostazioni tramite il link sopra</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /** -------------------- FRONTEND -------------------- */
    public function maybe_enqueue_frontend() {
        // Solo frontend, non admin
        if ( is_admin() ) {
            return;
        }

        // Ottieni tutte le regole
        $rules = $this->get_rules();
        
        if ( empty( $rules ) ) {
            return;
        }

        // Informazioni sulla pagina corrente
        $current_id = get_the_ID();
        $is_single_post = is_single();
        $is_page = is_page();
        $is_mobile = wp_is_mobile();
        
        // Array per raccogliere tutti i payload da iniettare via JS
        $payloads = [];
        
        // Itera su ogni regola
        foreach ( $rules as $rule_id => $rule ) {
            // Salta regole non attive
            if ( empty( $rule['active'] ) ) {
                continue;
            }
            
            // Verifica configurazione minima (il code è sempre necessario)
            if ( empty( $rule['code'] ) ) {
                continue;
            }
            
            // Verifica dispositivo target
            $device_target = $rule['device_target'] ?? 'both';
            if ( $device_target === 'desktop' && $is_mobile ) {
                continue; // Regola solo per desktop, ma siamo su mobile
            }
            if ( $device_target === 'mobile' && ! $is_mobile ) {
                continue; // Regola solo per mobile, ma siamo su desktop
            }
            
            // Verifica match
            $match = false;
            
            switch ( $rule['match_mode'] ) {
                case 'single_posts':
                    // Tutti gli articoli
                    $match = $is_single_post;
                    break;
                    
                case 'category_archive':
                    // Pagina archivio categoria
                    if ( $rule['category_id'] > 0 ) {
                        $match = is_category( (int) $rule['category_id'] );
                    }
                    break;
                    
                case 'single_posts_category':
                    // Articoli di una specifica categoria
                    if ( $is_single_post && $rule['category_id'] > 0 ) {
                        $match = has_category( (int) $rule['category_id'], $current_id );
                    }
                    break;
                    
                case 'page':
                    // Pagina specifica
                    if ( $is_page && $rule['page_id'] > 0 ) {
                        $match = ( $current_id === (int) $rule['page_id'] );
                    }
                    break;
            }
            
            // Se match, processa la regola
            if ( $match ) {
                $position = $rule['position'];
                $article_positions = [ 'before_post', 'before_content', 'after_content', 'before_paragraph', 'after_paragraph', 'before_image', 'after_image' ];
                
                // Le posizioni specifiche articoli usano filtri WordPress
                if ( in_array( $position, $article_positions, true ) ) {
                    add_filter( 'the_content', function( $content ) use ( $rule ) {
                        return $this->inject_in_content( $content, $rule );
                    }, 10 );
                } else {
                    // Le posizioni standard usano JavaScript
                    if ( empty( $rule['selector'] ) ) {
                        continue; // Selector necessario per posizioni standard
                    }
                    
                    $payload = [
                        'selector' => $rule['selector'],
                        'position' => $rule['position'],
                        'code'     => $rule['code'],
                    ];
                    
                    /**
                     * Filtra il payload prima dell'iniezione
                     * 
                     * @param array $payload Array con selector, position e code
                     * @param array $rule La regola completa
                     * @param string $rule_id ID della regola
                     */
                    $payload = apply_filters( 'sdi_injection_payload', $payload, $rule, $rule_id );
                    
                    // Verifica che il payload sia ancora valido dopo il filtro
                    if ( ! empty( $payload['selector'] ) && ! empty( $payload['code'] ) ) {
                        $payloads[] = $payload;
                    }
                }
            }
        }
        
        // Se ci sono payload da iniettare via JS, registra lo script
        if ( ! empty( $payloads ) ) {
            // Codifica il codice HTML in base64 per evitare problemi di escaping
            $encoded_payloads = array_map( function( $payload ) {
                return [
                    'selector' => $payload['selector'],
                    'position' => $payload['position'],
                    'code'     => base64_encode( $payload['code'] ), // Codifica in base64
                ];
            }, $payloads );
            
            wp_register_script( 'sdi-runtime', false, [], false, true );
            wp_enqueue_script( 'sdi-runtime' );
            
            // Passa i dati codificati
            wp_localize_script( 'sdi-runtime', 'sdiPayloads', $encoded_payloads );
            wp_add_inline_script( 'sdi-runtime', $this->get_inline_js() );
        }
    }
    
    /**
     * Inietta il codice nel contenuto dell'articolo
     */
    private function inject_in_content( $content, $rule ) {
        $code = $rule['code'];
        $position = $rule['position'];
        $paragraph_number = isset( $rule['paragraph_number'] ) ? absint( $rule['paragraph_number'] ) : 1;
        
        switch ( $position ) {
            case 'before_post':
                // Prima dell'intero contenuto (equivale a before_content ma con priorità diversa)
                return $code . $content;
                
            case 'before_content':
                // Prima del contenuto
                return $code . $content;
                
            case 'after_content':
                // Dopo il contenuto
                return $content . $code;
                
            case 'before_paragraph':
                // Prima del paragrafo N
                return $this->inject_before_paragraph( $content, $code, $paragraph_number );
                
            case 'after_paragraph':
                // Dopo il paragrafo N
                return $this->inject_after_paragraph( $content, $code, $paragraph_number );
                
            case 'before_image':
                // Prima della prima immagine
                return $this->inject_before_image( $content, $code );
                
            case 'after_image':
                // Dopo la prima immagine
                return $this->inject_after_image( $content, $code );
                
            default:
                return $content;
        }
    }
    
    /**
     * Inietta codice prima del paragrafo N
     */
    private function inject_before_paragraph( $content, $code, $paragraph_number ) {
        // Trova tutti i paragrafi <p>
        $paragraphs = preg_split( '/(<p[^>]*>.*?<\/p>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
        
        $p_count = 0;
        $result = '';
        
        foreach ( $paragraphs as $paragraph ) {
            // Se è un paragrafo (inizia con <p)
            if ( preg_match( '/^<p[^>]*>/i', $paragraph ) ) {
                $p_count++;
                
                // Se è il paragrafo target, inietta prima
                if ( $p_count === $paragraph_number ) {
                    $result .= $code . $paragraph;
                } else {
                    $result .= $paragraph;
                }
            } else {
                $result .= $paragraph;
            }
        }
        
        return $result;
    }
    
    /**
     * Inietta codice dopo il paragrafo N
     */
    private function inject_after_paragraph( $content, $code, $paragraph_number ) {
        // Trova tutti i paragrafi <p>
        $paragraphs = preg_split( '/(<p[^>]*>.*?<\/p>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
        
        $p_count = 0;
        $result = '';
        
        foreach ( $paragraphs as $paragraph ) {
            // Se è un paragrafo (inizia con <p)
            if ( preg_match( '/^<p[^>]*>/i', $paragraph ) ) {
                $p_count++;
                
                // Se è il paragrafo target, inietta dopo
                if ( $p_count === $paragraph_number ) {
                    $result .= $paragraph . $code;
                } else {
                    $result .= $paragraph;
                }
            } else {
                $result .= $paragraph;
            }
        }
        
        return $result;
    }
    
    /**
     * Inietta codice prima della prima immagine
     */
    private function inject_before_image( $content, $code ) {
        // Cerca il primo tag <img>
        $pattern = '/(<img[^>]*>)/i';
        
        if ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
            $img_position = $matches[0][1];
            $before = substr( $content, 0, $img_position );
            $after = substr( $content, $img_position );
            
            return $before . $code . $after;
        }
        
        // Se non trova immagini, non inietta nulla
        return $content;
    }
    
    /**
     * Inietta codice dopo la prima immagine
     */
    private function inject_after_image( $content, $code ) {
        // Cerca il primo tag <img>
        $pattern = '/(<img[^>]*>)/i';
        
        if ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
            $img_tag = $matches[0][0];
            $img_end_position = $matches[0][1] + strlen( $img_tag );
            
            $before = substr( $content, 0, $img_end_position );
            $after = substr( $content, $img_end_position );
            
            return $before . $code . $after;
        }
        
        // Se non trova immagini, non inietta nulla
        return $content;
    }

    private function get_inline_js(): string {
        // I payload vengono passati tramite wp_localize_script come variabile globale sdiPayloads
        // JavaScript inline formattato per leggibilità
        $js = <<<'JS'
(function(){
  try {
    // I dati sono passati da wp_localize_script nella variabile globale sdiPayloads
    var rules = window.sdiPayloads || [];
    
    function ready(fn){ 
      if(document.readyState !== 'loading'){ 
        fn(); 
      } else { 
        document.addEventListener('DOMContentLoaded', fn); 
      } 
    }
    
    function insert(target, html, where){
      if(!target) return;
      
      // Crea un contenitore temporaneo
      var temp = document.createElement('div');
      temp.innerHTML = html;
      
      // Estrae tutti gli elementi (non solo gli script)
      var elements = Array.from(temp.childNodes);
      
      try {
        // Inserisci gli elementi nella posizione corretta
        switch(where){
          case 'prepend':
            elements.reverse().forEach(function(el) {
              if (el.nodeType === 1) { // Element node
                target.insertBefore(cloneAndExecute(el), target.firstChild);
              } else {
                target.insertBefore(el.cloneNode(true), target.firstChild);
              }
            });
            break;
          case 'before':
            elements.forEach(function(el) {
              if (el.nodeType === 1) {
                target.parentNode.insertBefore(cloneAndExecute(el), target);
              } else {
                target.parentNode.insertBefore(el.cloneNode(true), target);
              }
            });
            break;
          case 'after':
            elements.reverse().forEach(function(el) {
              if (el.nodeType === 1) {
                target.parentNode.insertBefore(cloneAndExecute(el), target.nextSibling);
              } else {
                target.parentNode.insertBefore(el.cloneNode(true), target.nextSibling);
              }
            });
            break;
          case 'replace':
            target.innerHTML = '';
            elements.forEach(function(el) {
              if (el.nodeType === 1) {
                target.appendChild(cloneAndExecute(el));
              } else {
                target.appendChild(el.cloneNode(true));
              }
            });
            break;
          case 'append':
          default:
            elements.forEach(function(el) {
              if (el.nodeType === 1) {
                target.appendChild(cloneAndExecute(el));
              } else {
                target.appendChild(el.cloneNode(true));
              }
            });
        }
      } catch(insertError) {
        console.warn('Smart Div Injector: Errore nell\'inserimento:', insertError);
      }
    }
    
    function cloneAndExecute(element) {
      // Se è uno script, crea una copia eseguibile
      if (element.tagName === 'SCRIPT') {
        var script = document.createElement('script');
        
        // Copia tutti gli attributi in modo sicuro
        Array.from(element.attributes).forEach(function(attr) {
          try {
            // Usa getAttribute per ottenere il valore non processato
            var value = element.getAttribute(attr.name);
            if (value !== null) {
              script.setAttribute(attr.name, value);
            }
          } catch(e) {
            console.warn('Smart Div Injector: Errore nel copiare attributo', attr.name, e);
          }
        });
        
        // Copia il contenuto dello script
        if (element.textContent) {
          script.textContent = element.textContent;
        } else if (element.text) {
          script.text = element.text;
        }
        
        return script;
      }
      
      // Per altri elementi, clona e processa ricorsivamente gli script al loro interno
      var clone = element.cloneNode(false);
      
      Array.from(element.childNodes).forEach(function(child) {
        if (child.nodeType === 1 && child.tagName === 'SCRIPT') {
          clone.appendChild(cloneAndExecute(child));
        } else if (child.nodeType === 1) {
          clone.appendChild(cloneAndExecute(child));
        } else {
          clone.appendChild(child.cloneNode(true));
        }
      });
      
      return clone;
    }
    
    ready(function(){
      if (!rules || !Array.isArray(rules)) {
        console.warn('Smart Div Injector: Nessuna regola valida da processare');
        return;
      }
      
      // Processa ogni regola
      rules.forEach(function(rule, index){
        try {
          if (!rule || !rule.selector || !rule.code) {
            console.warn('Smart Div Injector: Regola #' + (index + 1) + ' non valida');
            return;
          }
          
          var el = document.querySelector(rule.selector);
          if(!el){ 
            console.warn('Smart Div Injector: Selettore non trovato:', rule.selector);
            return; 
          }
          
          // Decodifica il codice da base64
          var decodedCode = decodeURIComponent(atob(rule.code).split('').map(function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
          }).join(''));
          
          insert(el, decodedCode, rule.position || 'append');
        } catch(e) { 
          console.warn('Smart Div Injector: Errore nell\'iniezione della regola #' + (index + 1), e); 
        }
      });
    });
  } catch(globalError) {
    console.error('Smart Div Injector: Errore critico:', globalError);
  }
})();
JS;
        
        return $js;
    }
}

new Smart_Div_Injector();
