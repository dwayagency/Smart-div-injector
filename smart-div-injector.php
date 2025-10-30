<?php
/**
 * Plugin Name: Smart Div Injector
 * Description: Inserisce un frammento di codice dentro una div specifica, in base a ID articolo e/o categoria.
 * Version: 1.0.1
 * Author: DWAY SRL
 * License: GPL-2.0+
 * Text Domain: smart-div-injector
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

        // Frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_frontend' ] );
    }

    /** -------------------- ADMIN -------------------- */
    public function add_settings_page() {
        add_options_page(
            'Smart Div Injector',
            'Smart Div Injector',
            'manage_options',
            'smart-div-injector',
            [ $this, 'render_settings_page' ]
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

        add_settings_field( 'match_mode', 'Condizione di attivazione', [ $this, 'field_match_mode' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'post_id', 'ID articolo', [ $this, 'field_post_id' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'category_id', 'Categoria', [ $this, 'field_category' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'selector', 'Selettore CSS della div', [ $this, 'field_selector' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'position', 'Posizione di inserimento', [ $this, 'field_position' ], 'smart-div-injector', 'sdi_main' );
        add_settings_field( 'code', 'Codice da inserire', [ $this, 'field_code' ], 'smart-div-injector', 'sdi_main' );
    }

    public function get_options() {
        $defaults = [
            'match_mode'  => 'id', // id|category|both
            'post_id'     => '',
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

        $output['match_mode']  = in_array( $input['match_mode'] ?? 'id', [ 'id', 'category', 'both' ], true ) ? $input['match_mode'] : 'id';
        
        // Fix: gestisci correttamente campo vuoto per post_id
        if ( isset( $input['post_id'] ) && '' !== trim( $input['post_id'] ) ) {
            $output['post_id'] = absint( $input['post_id'] );
        } else {
            $output['post_id'] = 0;
        }
        
        $output['category_id'] = isset( $input['category_id'] ) ? absint( $input['category_id'] ) : 0;
        $output['selector']    = isset( $input['selector'] ) ? sanitize_text_field( $input['selector'] ) : '';
        $output['position']    = in_array( $input['position'] ?? 'append', [ 'append', 'prepend', 'before', 'after', 'replace' ], true ) ? $input['position'] : 'append';

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
        if ( 'id' === $opts['match_mode'] && ! $opts['post_id'] ) {
            $warnings[] = 'ID articolo non impostato (richiesto per la modalità selezionata)';
        }
        if ( 'category' === $opts['match_mode'] && ! $opts['category_id'] ) {
            $warnings[] = 'Categoria non impostata (richiesta per la modalità selezionata)';
        }
        if ( 'both' === $opts['match_mode'] && ( ! $opts['post_id'] || ! $opts['category_id'] ) ) {
            $warnings[] = 'ID articolo e/o categoria non impostati (entrambi richiesti per la modalità AND)';
        }
        
        ?>
        <div class="wrap">
            <h1>Smart Div Injector</h1>
            
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

    public function field_match_mode() {
        $opts = $this->get_options();
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[match_mode]">
            <option value="id" <?php selected( $opts['match_mode'], 'id' ); ?>>Solo ID articolo</option>
            <option value="category" <?php selected( $opts['match_mode'], 'category' ); ?>>Solo categoria</option>
            <option value="both" <?php selected( $opts['match_mode'], 'both' ); ?>>ID E categoria (AND)</option>
        </select>
        <p class="description">Scegli quando attivare l'iniezione del codice.</p>
        <?php
    }

    public function field_post_id() {
        $opts = $this->get_options();
        $value = $opts['post_id'] > 0 ? $opts['post_id'] : '';
        ?>
        <input type="number" min="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_id]" value="<?php echo esc_attr( $value ); ?>" placeholder="Esempio: 123" />
        <p class="description">Inserisci l'ID del post/pagina (lascia vuoto se non usato).</p>
        <?php
    }

    public function field_category() {
        $opts = $this->get_options();
        $categories = get_categories( [ 'hide_empty' => false ] );
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[category_id]">
            <option value="0" <?php selected( $opts['category_id'], 0 ); ?>>— Nessuna —</option>
            <?php foreach ( $categories as $cat ) : ?>
                <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $opts['category_id'], $cat->term_id ); ?>>
                    <?php echo esc_html( $cat->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Scegli la categoria target (per gli articoli).</p>
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
        $is_single_post = is_single();
        $current_post_id = $is_single_post ? get_the_ID() : 0;
        $in_category = ( $is_single_post && $opts['category_id'] ) ? has_category( (int) $opts['category_id'], $current_post_id ) : false;
        $match = false;

        switch ( $opts['match_mode'] ) {
            case 'id':
                // Match su ID post/pagina
                $match = ( $opts['post_id'] > 0 && $current_post_id === (int) $opts['post_id'] );
                break;
                
            case 'category':
                // Match su categoria
                $match = (bool) $in_category;
                break;
                
            case 'both':
                // Match su ID E categoria
                $match = ( $opts['post_id'] > 0 && $current_post_id === (int) $opts['post_id'] && $in_category );
                break;
        }

        // Permetti anche l'iniezione sulle pagine (solo per match su ID)
        if ( ! $match && is_page() && 'id' === $opts['match_mode'] ) {
            $match = ( $opts['post_id'] > 0 && get_the_ID() === (int) $opts['post_id'] );
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
