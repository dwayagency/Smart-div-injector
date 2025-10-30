<?php
/**
 * Plugin Name: Smart Div Injector
 * Description: Inserisce un frammento di codice dentro una div specifica, in base a articolo, pagina e/o categoria.
 * Version: 1.1.0
 * Author: DWAY SRL
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
    const OPTION_KEY = 'sdi_options';

    public function __construct() {
        // Admin
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Multisite: aggiungi menu anche nel Network Admin (opzionale)
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', [ $this, 'add_network_settings_page' ] );
        }

        // Frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_frontend' ] );
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

    public function register_settings() {
        register_setting( 'sdi_group', self::OPTION_KEY, [ $this, 'sanitize_options' ] );

        add_settings_section(
            'sdi_main',
            'Regole di inserimento',
            function () {
                echo '<p>Configura quando e dove inserire il codice. Il plugin inserirà automaticamente il tuo codice HTML/JS/CSS nella posizione specificata quando le condizioni sono soddisfatte.</p>';
                echo '<p><strong>Note:</strong></p>';
                echo '<ul style="list-style: disc; padding-left: 20px;">';
                echo '<li>Il selettore CSS deve essere valido (es. <code>#my-div</code>, <code>.my-class</code>, <code>main > article</code>)</li>';
                echo '<li>Il codice viene inserito dopo il caricamento del DOM</li>';
                echo '<li>Gli script inseriti vengono automaticamente attivati</li>';
                echo '</ul>';
            },
            'smart-div-injector'
        );

        add_settings_field( 'match_mode', 'Tipo di contenuto target', [ $this, 'field_match_mode' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'post_id', 'Articolo specifico', [ $this, 'field_post_id' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'page_id', 'Pagina specifica', [ $this, 'field_page_id' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'category_id', 'Categoria', [ $this, 'field_category' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'selector', 'Selettore CSS della div', [ $this, 'field_selector' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'position', 'Posizione di inserimento', [ $this, 'field_position' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'code', 'Codice da inserire', [ $this, 'field_code' ], 'smart-div-injector', 'sdi_main' );
    }

    public function get_options() {
        $defaults = [
            'match_mode'  => 'post', // post|page|category|post_category|page_category
            'post_id'     => 0,
            'page_id'     => 0,
            'category_id' => 0,
            'selector'    => '',
            'position'    => 'append', // append|prepend|before|after|replace
            'code'        => '',
        ];
        $opts = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $opts, $defaults );
    }

    public function sanitize_options( $input ) {
        $output = $this->get_options();

        // Valida match_mode
        $valid_modes = [ 'post', 'page', 'category', 'post_category', 'page_category' ];
        $output['match_mode'] = in_array( $input['match_mode'] ?? 'post', $valid_modes, true ) ? $input['match_mode'] : 'post';
        
        // Sanitizza post_id
        $output['post_id'] = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        
        // Sanitizza page_id
        $output['page_id'] = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
        
        // Sanitizza category_id
        $output['category_id'] = isset( $input['category_id'] ) ? absint( $input['category_id'] ) : 0;
        
        $output['selector'] = isset( $input['selector'] ) ? sanitize_text_field( $input['selector'] ) : '';
        $output['position'] = in_array( $input['position'] ?? 'append', [ 'append', 'prepend', 'before', 'after', 'replace' ], true ) ? $input['position'] : 'append';

        // Permetti codice non filtrato solo a chi ha la capability unfiltered_html.
        if ( current_user_can( 'unfiltered_html' ) ) {
            $output['code'] = $input['code'] ?? '';
        } else {
            // Fallback: sanitizza per sicurezza (script verranno rimossi per ruoli senza capability).
            $output['code'] = wp_kses_post( $input['code'] ?? '' );
        }

        return $output;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $opts = $this->get_options();
        
        // Mostra avvisi se la configurazione è incompleta
        $warnings = [];
        if ( empty( $opts['selector'] ) ) {
            $warnings[] = 'Selettore CSS non impostato';
        }
        if ( empty( $opts['code'] ) ) {
            $warnings[] = 'Codice da inserire non impostato';
        }
        
        // Validazione basata sul tipo di contenuto
        switch ( $opts['match_mode'] ) {
            case 'post':
                if ( ! $opts['post_id'] ) {
                    $warnings[] = 'Articolo non selezionato (richiesto per la modalità selezionata)';
                }
                break;
            case 'page':
                if ( ! $opts['page_id'] ) {
                    $warnings[] = 'Pagina non selezionata (richiesta per la modalità selezionata)';
                }
                break;
            case 'category':
                if ( ! $opts['category_id'] ) {
                    $warnings[] = 'Categoria non selezionata (richiesta per la modalità selezionata)';
                }
                break;
            case 'post_category':
                if ( ! $opts['post_id'] || ! $opts['category_id'] ) {
                    $warnings[] = 'Articolo e categoria devono essere entrambi selezionati (richiesti per la modalità AND)';
                }
                break;
            case 'page_category':
                if ( ! $opts['page_id'] || ! $opts['category_id'] ) {
                    $warnings[] = 'Pagina e categoria devono essere entrambe selezionate (richieste per la modalità AND)';
                }
                break;
        }
        
        ?>
        <div class="wrap">
            <h1>Smart Div Injector</h1>
            
            <?php if ( is_multisite() ) : ?>
                <div class="notice notice-info">
                    <p>
                        <strong>Multisite:</strong> Stai configurando il plugin per questo sito specifico.
                        <?php if ( current_user_can( 'manage_network_options' ) ) : ?>
                            Puoi vedere lo stato di tutti i siti dalla <a href="<?php echo esc_url( network_admin_url( 'admin.php?page=smart-div-injector-network' ) ); ?>">pagina Network Admin</a>.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ( ! empty( $warnings ) ) : ?>
                <div class="notice notice-warning">
                    <p><strong>Attenzione:</strong> La configurazione è incompleta. Il plugin non verrà attivato fino a quando non completerai:</p>
                    <ul style="list-style: disc; padding-left: 20px;">
                        <?php foreach ( $warnings as $warning ) : ?>
                            <li><?php echo esc_html( $warning ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'sdi_group' ); ?>
                <?php do_settings_sections( 'smart-div-injector' ); ?>
                <?php submit_button(); ?>
            </form>
        </div>
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

    public function field_match_mode() {
        $opts = $this->get_options();
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[match_mode]" id="sdi_match_mode" onchange="sdiToggleFields()">
            <option value="post" <?php selected( $opts['match_mode'], 'post' ); ?>>Articolo specifico</option>
            <option value="page" <?php selected( $opts['match_mode'], 'page' ); ?>>Pagina specifica</option>
            <option value="category" <?php selected( $opts['match_mode'], 'category' ); ?>>Categoria</option>
            <option value="post_category" <?php selected( $opts['match_mode'], 'post_category' ); ?>>Articolo E Categoria (AND)</option>
            <option value="page_category" <?php selected( $opts['match_mode'], 'page_category' ); ?>>Pagina E Categoria (AND)</option>
        </select>
        <p class="description">Scegli il tipo di contenuto su cui attivare l'iniezione del codice.</p>
        
        <script>
        function sdiToggleFields() {
            var mode = document.getElementById('sdi_match_mode').value;
            var postDiv = document.getElementById('sdi_post_row');
            var pageDiv = document.getElementById('sdi_page_row');
            var categoryDiv = document.getElementById('sdi_category_row');
            
            // Trova le righe <tr> parent
            var postRow = postDiv ? postDiv.closest('tr') : null;
            var pageRow = pageDiv ? pageDiv.closest('tr') : null;
            var categoryRow = categoryDiv ? categoryDiv.closest('tr') : null;
            
            // Nascondi tutte le righe
            if (postRow) postRow.style.display = 'none';
            if (pageRow) pageRow.style.display = 'none';
            if (categoryRow) categoryRow.style.display = 'none';
            
            // Mostra in base alla selezione
            switch(mode) {
                case 'post':
                    if (postRow) postRow.style.display = 'table-row';
                    break;
                case 'page':
                    if (pageRow) pageRow.style.display = 'table-row';
                    break;
                case 'category':
                    if (categoryRow) categoryRow.style.display = 'table-row';
                    break;
                case 'post_category':
                    if (postRow) postRow.style.display = 'table-row';
                    if (categoryRow) categoryRow.style.display = 'table-row';
                    break;
                case 'page_category':
                    if (pageRow) pageRow.style.display = 'table-row';
                    if (categoryRow) categoryRow.style.display = 'table-row';
                    break;
            }
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

    public function field_post_id() {
        $opts = $this->get_options();
        $posts = get_posts( [ 
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby'     => 'title',
            'order'       => 'ASC'
        ] );
        ?>
        <div id="sdi_post_row">
            <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_id]" class="regular-text">
                <option value="0">— Seleziona un articolo —</option>
                <?php foreach ( $posts as $post ) : ?>
                    <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $opts['post_id'], $post->ID ); ?>>
                        <?php echo esc_html( $post->post_title . ' (ID: ' . $post->ID . ')' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Seleziona l'articolo su cui attivare l'iniezione.</p>
        </div>
        <?php
    }
    
    public function field_page_id() {
        $opts = $this->get_options();
        $pages = get_pages( [ 
            'post_status' => 'publish',
            'sort_column' => 'post_title',
            'sort_order'  => 'ASC'
        ] );
        ?>
        <div id="sdi_page_row">
            <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[page_id]" class="regular-text">
                <option value="0">— Seleziona una pagina —</option>
                <?php foreach ( $pages as $page ) : ?>
                    <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $opts['page_id'], $page->ID ); ?>>
                        <?php echo esc_html( $page->post_title . ' (ID: ' . $page->ID . ')' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Seleziona la pagina su cui attivare l'iniezione.</p>
        </div>
        <?php
    }

    public function field_category() {
        $opts = $this->get_options();
        $categories = get_categories( [ 'hide_empty' => false ] );
        ?>
        <div id="sdi_category_row">
            <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[category_id]" class="regular-text">
                <option value="0">— Seleziona una categoria —</option>
                <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $opts['category_id'], $cat->term_id ); ?>>
                        <?php echo esc_html( $cat->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Seleziona la categoria target per gli articoli.</p>
        </div>
        <?php
    }

    public function field_selector() {
        $opts = $this->get_options();
        ?>
        <input type="text" class="regular-text" placeholder="#id-della-div, .classe, main > .wrap"
               name="<?php echo esc_attr( self::OPTION_KEY ); ?>[selector]" value="<?php echo esc_attr( $opts['selector'] ); ?>" />
        <p class="description">Selettore CSS della <em>div</em> (o elemento) in cui inserire il codice.</p>
        <?php
    }

    public function field_position() {
        $opts = $this->get_options();
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[position]">
            <option value="append" <?php selected( $opts['position'], 'append' ); ?>>Append (in fondo dentro la div)</option>
            <option value="prepend" <?php selected( $opts['position'], 'prepend' ); ?>>Prepend (all'inizio dentro la div)</option>
            <option value="before" <?php selected( $opts['position'], 'before' ); ?>>Prima della div</option>
            <option value="after" <?php selected( $opts['position'], 'after' ); ?>>Dopo la div</option>
            <option value="replace" <?php selected( $opts['position'], 'replace' ); ?>>Sostituisci contenuto della div</option>
        </select>
        <?php
    }

    public function field_code() {
        $opts = $this->get_options();
        $code = $opts['code'];
        ?>
        <textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[code]" rows="10" class="large-text code" spellcheck="false" placeholder="<div>Il tuo codice HTML/JS/CSS</div>"><?php echo esc_textarea( $code ); ?></textarea>
        <p class="description">Il codice verrà inserito tal quale. Solo gli utenti con permesso <code>unfiltered_html</code> possono salvare script non sanitizzati.</p>
        <?php
    }

    /** -------------------- FRONTEND -------------------- */
    public function maybe_enqueue_frontend() {
        // Solo frontend, non admin
        if ( is_admin() ) {
            return;
        }

        $opts = $this->get_options();
        
        // Verifica che ci siano le impostazioni minime richieste
        if ( empty( $opts['selector'] ) || empty( $opts['code'] ) ) {
            return;
        }

        // Verifica condizioni di match
        $current_id = get_the_ID();
        $is_single_post = is_single();
        $is_page = is_page();
        $match = false;

        switch ( $opts['match_mode'] ) {
            case 'post':
                // Match su articolo specifico
                $match = ( $is_single_post && $opts['post_id'] > 0 && $current_id === (int) $opts['post_id'] );
                break;
                
            case 'page':
                // Match su pagina specifica
                $match = ( $is_page && $opts['page_id'] > 0 && $current_id === (int) $opts['page_id'] );
                break;
                
            case 'category':
                // Match su categoria (solo per articoli)
                if ( $is_single_post && $opts['category_id'] > 0 ) {
                    $match = has_category( (int) $opts['category_id'], $current_id );
                }
                break;
                
            case 'post_category':
                // Match su articolo E categoria
                if ( $is_single_post && $opts['post_id'] > 0 && $current_id === (int) $opts['post_id'] ) {
                    if ( $opts['category_id'] > 0 ) {
                        $match = has_category( (int) $opts['category_id'], $current_id );
                    }
                }
                break;
                
            case 'page_category':
                // Match su pagina E categoria (la pagina deve essere nell'articolo associato)
                // Nota: le pagine non hanno categorie, quindi questo controlla se la pagina
                // corrisponde E se ci sono articoli nella categoria specificata
                if ( $is_page && $opts['page_id'] > 0 && $current_id === (int) $opts['page_id'] ) {
                    // La pagina corrisponde, consideriamo match se la categoria è impostata
                    // (interpretazione: mostra sulla pagina specifica solo se la categoria esiste)
                    if ( $opts['category_id'] > 0 ) {
                        $match = term_exists( (int) $opts['category_id'], 'category' );
                    }
                }
                break;
        }

        if ( ! $match ) {
            return;
        }

        // Passa i dati in JS e inietta in footer
        $payload = [
            'selector' => $opts['selector'],
            'position' => $opts['position'],
            'code'     => $opts['code'],
        ];
        
        /**
         * Filtra il payload prima dell'iniezione
         * 
         * @param array $payload Array con selector, position e code
         * @param array $opts Tutte le opzioni del plugin
         */
        $payload = apply_filters( 'sdi_injection_payload', $payload, $opts );
        
        // Verifica che il payload sia ancora valido dopo il filtro
        if ( empty( $payload['selector'] ) || empty( $payload['code'] ) ) {
            return;
        }

        // Registra e accoda lo script inline in footer
        wp_register_script( 'sdi-runtime', false, [], false, true );
        wp_enqueue_script( 'sdi-runtime' );
        wp_add_inline_script( 'sdi-runtime', $this->get_inline_js( $payload ) );
    }

    private function get_inline_js( array $payload ): string {
        $json = wp_json_encode( $payload );
        
        // JavaScript inline formattato per leggibilità
        $js = <<<JS
(function(){
  var cfg = {$json};
  
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
    try {
      var el = document.querySelector(cfg.selector);
      if(!el){ return; }
      insert(el, cfg.code, cfg.position || 'append');
    } catch(e) { 
      console.warn('Smart Div Injector: Errore nell\'iniezione del codice', e); 
    }
  });
})();
JS;
        
        return $js;
    }
}

new Smart_Div_Injector();
