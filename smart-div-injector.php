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

        // Multisite: aggiungi menu anche nel Network Admin (opzionale)
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', [ $this, 'add_network_settings_page' ] );
        }

        // Frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_frontend' ] );
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
        
        $rule = [
            'name'        => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : 'Regola senza nome',
            'active'      => isset( $data['active'] ) && $data['active'] === '1',
            'match_mode'  => in_array( $data['match_mode'] ?? 'single_posts', $valid_modes, true ) ? $data['match_mode'] : 'single_posts',
            'page_id'     => isset( $data['page_id'] ) ? absint( $data['page_id'] ) : 0,
            'category_id' => isset( $data['category_id'] ) ? absint( $data['category_id'] ) : 0,
            'selector'    => isset( $data['selector'] ) ? sanitize_text_field( $data['selector'] ) : '',
            'position'    => in_array( $data['position'] ?? 'append', [ 'append', 'prepend', 'before', 'after', 'replace' ], true ) ? $data['position'] : 'append',
        ];
        
        // Sanitizza il codice
        if ( current_user_can( 'unfiltered_html' ) ) {
            $rule['code'] = $data['code'] ?? '';
        } else {
            $rule['code'] = wp_kses_post( $data['code'] ?? '' );
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
            <h1 class="wp-heading-inline">Smart Div Injector</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector&action=add' ) ); ?>" class="page-title-action">Aggiungi nuova regola</a>
            <hr class="wp-header-end">
            
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
                <div class="notice notice-success is-dismissible">
                    <p><strong>Regola aggiunta con successo!</strong></p>
                </div>
            <?php elseif ( $message === 'updated' ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Regola aggiornata con successo!</strong></p>
                </div>
            <?php elseif ( $message === 'deleted' ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Regola eliminata con successo!</strong></p>
                </div>
            <?php elseif ( $message === 'duplicated' ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Regola duplicata con successo!</strong></p>
                </div>
            <?php endif; ?>
            
            <?php if ( empty( $rules ) ) : ?>
                <div class="notice notice-info">
                    <p><strong>Nessuna regola configurata.</strong> Clicca su "Aggiungi nuova regola" per iniziare.</p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 50px;">Attiva</th>
                            <th scope="col">Nome</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Target</th>
                            <th scope="col">Selettore</th>
                            <th scope="col" style="width: 150px;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rules as $rule_id => $rule ) : ?>
                            <tr>
                                <td>
                                    <?php if ( $rule['active'] ) : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: green;" title="Attiva"></span>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-dismiss" style="color: #999;" title="Non attiva"></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html( $rule['name'] ); ?></strong></td>
                                <td>
                                    <?php 
                                    switch ( $rule['match_mode'] ) {
                                        case 'single_posts':
                                            echo 'Tutti gli articoli';
                                            break;
                                        case 'category_archive':
                                            echo 'Pagina archivio categoria';
                                            break;
                                        case 'single_posts_category':
                                            echo 'Articoli di una categoria';
                                            break;
                                        case 'page':
                                            echo 'Pagina specifica';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ( ( $rule['match_mode'] === 'single_posts_category' || $rule['match_mode'] === 'category_archive' ) && $rule['category_id'] ) {
                                        $cat = get_category( $rule['category_id'] );
                                        echo $cat ? esc_html( $cat->name ) : 'Categoria #' . $rule['category_id'];
                                    } elseif ( $rule['match_mode'] === 'page' && $rule['page_id'] ) {
                                        echo get_the_title( $rule['page_id'] ) ?: 'Pagina #' . $rule['page_id'];
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td><code><?php echo esc_html( $rule['selector'] ); ?></code></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector&action=edit&rule_id=' . $rule_id ) ); ?>" class="button button-small">Modifica</a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=duplicate&rule_id=' . $rule_id ), 'duplicate_rule_' . $rule_id ) ); ?>" class="button button-small">Duplica</a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=delete&rule_id=' . $rule_id ), 'delete_rule_' . $rule_id ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('Sei sicuro di voler eliminare questa regola?');">Elimina</a>
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
            'name'        => '',
            'active'      => true,
            'match_mode'  => 'single_posts',
            'page_id'     => 0,
            'category_id' => 0,
            'selector'    => '',
            'position'    => 'append',
            'code'        => ''
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
            <h1><?php echo esc_html( $page_title ); ?></h1>
            
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
                            <label>
                                <input type="checkbox" name="active" id="rule_active" value="1" <?php checked( $rule['active'], true ); ?>>
                                Regola attiva
                            </label>
                            <p class="description">Se disattivata, la regola non verrà applicata sul frontend</p>
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
                                <p class="description" style="color: #d63638; font-weight: 600;">
                                    ⚠️ Il tuo sito ha <?php echo number_format( $total_pages ); ?> pagine. Il dropdown mostra solo le prime <?php echo $limit; ?>.
                                    <br>Se non trovi la pagina, usa il campo ID manuale qui sotto.
                                </p>
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
                            
                            <div style="margin-top: 10px;">
                                <label>
                                    <strong>Oppure inserisci l'ID manualmente:</strong><br>
                                    <input type="number" 
                                           id="page_manual" 
                                           min="1" 
                                           value="<?php echo esc_attr( $rule['page_id'] > 0 ? $rule['page_id'] : '' ); ?>" 
                                           placeholder="Esempio: 42" 
                                           style="width: 200px;" />
                                    <button type="button" class="button" onclick="sdiSetPageFromManual()">Usa questo ID</button>
                                </label>
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
                        <th scope="row"><label for="position">Posizione di inserimento</label></th>
                        <td>
                            <select name="position" id="position">
                                <option value="append" <?php selected( $rule['position'], 'append' ); ?>>Append (in fondo dentro la div)</option>
                                <option value="prepend" <?php selected( $rule['position'], 'prepend' ); ?>>Prepend (all'inizio dentro la div)</option>
                                <option value="before" <?php selected( $rule['position'], 'before' ); ?>>Prima della div</option>
                                <option value="after" <?php selected( $rule['position'], 'after' ); ?>>Dopo la div</option>
                                <option value="replace" <?php selected( $rule['position'], 'replace' ); ?>>Sostituisci contenuto della div</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="code">Codice da inserire *</label></th>
                        <td>
                            <textarea name="code" id="code" rows="10" class="large-text code" spellcheck="false" placeholder="<div>Il tuo codice HTML/JS/CSS</div>" required><?php echo esc_textarea( $rule['code'] ); ?></textarea>
                            <p class="description">Il codice verrà inserito tal quale. Solo gli utenti con permesso <code>unfiltered_html</code> possono salvare script non sanitizzati.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Aggiorna Regola' : 'Salva Regola'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector' ) ); ?>" class="button">Annulla</a>
                </p>
            </form>
        </div>
        
        <script>
        function sdiToggleFields() {
            var mode = document.getElementById('match_mode').value;
            var pageRow = document.getElementById('page_row');
            var categoryRow = document.getElementById('category_row');
            
            // Nascondi tutto
            if (pageRow) pageRow.style.display = 'none';
            if (categoryRow) categoryRow.style.display = 'none';
            
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
            document.addEventListener('DOMContentLoaded', sdiToggleFields);
        } else {
            sdiToggleFields();
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
        
        // Array per raccogliere tutti i payload da iniettare
        $payloads = [];
        
        // Itera su ogni regola
        foreach ( $rules as $rule_id => $rule ) {
            // Salta regole non attive
            if ( empty( $rule['active'] ) ) {
                continue;
            }
            
            // Verifica configurazione minima
            if ( empty( $rule['selector'] ) || empty( $rule['code'] ) ) {
                continue;
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
            
            // Se match, aggiungi il payload
            if ( $match ) {
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
        
        // Se ci sono payload da iniettare, registra lo script
        if ( ! empty( $payloads ) ) {
            wp_register_script( 'sdi-runtime', false, [], false, true );
            wp_enqueue_script( 'sdi-runtime' );
            wp_add_inline_script( 'sdi-runtime', $this->get_inline_js( $payloads ) );
        }
    }

    private function get_inline_js( array $payloads ): string {
        $json = wp_json_encode( $payloads );
        
        // JavaScript inline formattato per leggibilità
        $js = <<<JS
(function(){
  var rules = {$json};
  
  function ready(fn){ 
    if(document.readyState !== 'loading'){ 
      fn(); 
    } else { 
      document.addEventListener('DOMContentLoaded', fn); 
    } 
  }
  
  function insert(target, html, where){
    if(!target) return;
    
    var container = document.createElement('div');
    container.innerHTML = html;

    function activateScripts(scope){
      var scripts = scope.querySelectorAll('script');
      scripts.forEach(function(oldScript){
        var newScript = document.createElement('script');
        for (var i = 0; i < oldScript.attributes.length; i++) {
          var attr = oldScript.attributes[i];
          newScript.setAttribute(attr.name, attr.value);
        }
        newScript.text = oldScript.text;
        oldScript.parentNode.replaceChild(newScript, oldScript);
      });
    }

    switch(where){
      case 'prepend':
        target.insertAdjacentElement('afterbegin', container);
        activateScripts(container);
        break;
      case 'before':
        target.insertAdjacentElement('beforebegin', container);
        activateScripts(container);
        break;
      case 'after':
        target.insertAdjacentElement('afterend', container);
        activateScripts(container);
        break;
      case 'replace':
        target.innerHTML = '';
        target.appendChild(container);
        activateScripts(container);
        break;
      case 'append':
      default:
        target.appendChild(container);
        activateScripts(container);
    }
  }
  
  ready(function(){
    // Processa ogni regola
    rules.forEach(function(rule, index){
      try {
        var el = document.querySelector(rule.selector);
        if(!el){ 
          console.warn('Smart Div Injector: Selettore non trovato:', rule.selector);
          return; 
        }
        insert(el, rule.code, rule.position || 'append');
      } catch(e) { 
        console.warn('Smart Div Injector: Errore nell\'iniezione della regola #' + (index + 1), e); 
      }
    });
  });
})();
JS;
        
        return $js;
    }
}

new Smart_Div_Injector();
