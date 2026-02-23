<?php
/**
 * Plugin Name: Smart Div Injector
 * Description: Inserisce un frammento di codice dentro una div specifica, in base a articolo, pagina e/o categoria. Supporta regole multiple con varianti, modifica rapida, ricerca, filtri e paginazione.
 * Version: 2.5.2
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

$sdi_vendor = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $sdi_vendor ) ) {
    require_once $sdi_vendor;
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
            '2.5.2'
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
     * Indica se l'export/import Excel (.xlsx) è disponibile (PhpSpreadsheet caricato)
     */
    private function excel_available() {
        return class_exists( 'PhpOffice\PhpSpreadsheet\Spreadsheet' );
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
        
        if ( isset( $_POST['sdi_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sdi_nonce'] ) ), 'sdi_rule_action' ) ) {
            wp_die( 'Nonce verification failed' );
        }
        
        // Aggiungi nuova regola
        if ( isset( $_POST['sdi_action'] ) && $_POST['sdi_action'] === 'add' ) {
            $this->save_rule_from_post();
            wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=added' ) );
            exit;
        }
        
        // Modifica regola esistente
        if ( isset( $_POST['sdi_action'] ) && $_POST['sdi_action'] === 'edit' && isset( $_POST['rule_id'] ) ) {
            $this->update_rule_from_post( sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) );
            wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=updated' ) );
            exit;
        }
        
        // Elimina regola
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['rule_id'] ) ) {
            $rule_id = sanitize_text_field( wp_unslash( $_GET['rule_id'] ) );
            $nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'delete_rule_' . $rule_id ) ) {
                wp_die( 'Nonce verification failed' );
            }
            $this->delete_rule( $rule_id );
            wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=deleted' ) );
            exit;
        }
        
        // Eliminazione massiva (bulk delete)
        if ( isset( $_POST['sdi_action'] ) && $_POST['sdi_action'] === 'bulk_delete' && ! empty( $_POST['rule_ids'] ) && is_array( $_POST['rule_ids'] ) ) {
            $ids = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['rule_ids'] ) );
            $count = 0;
            foreach ( $ids as $rule_id ) {
                if ( $rule_id !== '' ) {
                    $this->delete_rule( $rule_id );
                    $count++;
                }
            }
            $msg = $count === 1 ? 'deleted' : 'bulk_deleted';
            wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=' . $msg . '&count=' . $count ) );
            exit;
        }
        
        // Duplica regola
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'duplicate' && isset( $_GET['rule_id'] ) ) {
            $rule_id = sanitize_text_field( wp_unslash( $_GET['rule_id'] ) );
            $nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'duplicate_rule_' . $rule_id ) ) {
                wp_die( 'Nonce verification failed' );
            }
            $this->duplicate_rule( $rule_id );
            wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=duplicated' ) );
            exit;
        }
        
        // Modifica rapida (quick edit)
        if ( isset( $_POST['sdi_action'] ) && $_POST['sdi_action'] === 'quick_edit' && isset( $_POST['rule_id'] ) ) {
            $this->quick_edit_rule( sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) );
            wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=quick_updated' ) );
            exit;
        }
        
        // Scarica template CSV
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_csv_template' ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sdi_download_csv_template' ) ) {
                wp_die( 'Nonce verification failed' );
            }
            $this->download_csv_template();
            exit;
        }
        
        // Esporta CSV (tutte le regole)
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'export_csv' ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sdi_export_csv' ) ) {
                wp_die( 'Nonce verification failed' );
            }
            $this->export_rules_to_csv();
            exit;
        }
        
        // Esporta Excel (.xlsx) - richiede PhpSpreadsheet (composer install)
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'export_excel' ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sdi_export_excel' ) ) {
                wp_die( 'Nonce verification failed' );
            }
            if ( ! $this->excel_available() ) {
                wp_die( esc_html__( 'Export Excel non disponibile: esegui "composer install" nella cartella del plugin.', 'smart-div-injector' ) );
            }
            $this->export_rules_to_excel();
            exit;
        }
        
        // Importa CSV o Excel (file validated in import handler)
        if ( isset( $_POST['sdi_action'] ) && $_POST['sdi_action'] === 'import_csv' && ! empty( $_FILES['sdi_csv_file']['tmp_name'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via is_uploaded_file in import handler.
            $result = $this->import_rules_from_upload( $_FILES['sdi_csv_file'] );
            if ( isset( $result['imported'] ) && $result['imported'] > 0 ) {
                $msg = $result['imported'] === 1 ? 'imported_one' : 'imported_many';
                wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=' . $msg . '&count=' . $result['imported'] ) );
            } elseif ( ! empty( $result['errors'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=import_errors&errors=' . urlencode( wp_json_encode( $result['errors'] ) ) ) );
            } else {
                wp_safe_redirect( admin_url( 'admin.php?page=smart-div-injector&message=imported_zero' ) );
            }
            exit;
        }
    }
    
    /**
     * Scarica il file CSV template per l'import
     */
    private function download_csv_template() {
        $filename = 'smart-div-injector-template.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-public' );
        header( 'Expires: 0' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing to php://output stream, not filesystem.
        $out = fopen( 'php://output', 'w' );
        if ( $out === false ) {
            return;
        }
        // BOM UTF-8 per Excel
        fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        $headers = [
            'name',
            'active',
            'match_mode',
            'page_id',
            'category_id',
            'selector',
            'position',
            'paragraph_number',
            'device_target',
            'alignment',
            'variant_name',
            'variant_code',
        ];
        fputcsv( $out, $headers );
        // Riga di esempio con valori validi
        fputcsv( $out, [
            'Banner Contatti',
            '1',
            'site_wide',
            '0',
            '0',
            '#main',
            'append',
            '1',
            'both',
            'none',
            'Variante 1',
            '<div class="banner">Testo con spazi e virgole, va bene.</div>',
        ] );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream.
        fclose( $out );
    }
    
    /**
     * Esporta tutte le regole in un file CSV (stesso formato del template/import)
     */
    private function export_rules_to_csv() {
        $filename = 'smart-div-injector-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-public' );
        header( 'Expires: 0' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing to php://output stream, not filesystem.
        $out = fopen( 'php://output', 'w' );
        if ( $out === false ) {
            return;
        }
        fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        $headers = [
            'name',
            'active',
            'match_mode',
            'page_id',
            'page_title',
            'category_id',
            'category_name',
            'selector',
            'position',
            'paragraph_number',
            'device_target',
            'alignment',
            'variant_name',
            'variant_code',
        ];
        fputcsv( $out, $headers );
        $rules = $this->get_rules();
        foreach ( $rules as $rule_id => $rule ) {
            // Codice e nome variante: stessa logica di get_active_variant_code (incluso supporto legacy rule['code'])
            $variant_name = 'Variante 1';
            $variant_code = '';
            if ( isset( $rule['code'] ) && ! isset( $rule['variants'] ) ) {
                $variant_code = $rule['code'];
            } else {
                $variants   = $rule['variants'] ?? [];
                $active_idx = isset( $rule['active_variant'] ) ? (int) $rule['active_variant'] : 0;
                if ( ! empty( $variants ) && isset( $variants[ $active_idx ] ) ) {
                    $variant_name = $variants[ $active_idx ]['name'] ?? 'Variante 1';
                    $variant_code = $variants[ $active_idx ]['code'] ?? '';
                } elseif ( ! empty( $variants ) && isset( $variants[0] ) ) {
                    $variant_name = $variants[0]['name'] ?? 'Variante 1';
                    $variant_code = $variants[0]['code'] ?? '';
                }
            }
            // Contesto effettivo: titolo pagina e nome categoria (per lettura immediata)
            $page_id     = isset( $rule['page_id'] ) ? (int) $rule['page_id'] : 0;
            $category_id = isset( $rule['category_id'] ) ? (int) $rule['category_id'] : 0;
            $page_title  = '';
            $category_name = '';
            if ( $page_id > 0 && ( $rule['match_mode'] ?? '' ) === 'page' ) {
                $page_title = get_the_title( $page_id );
                if ( $page_title === '' ) {
                    $page_title = '(ID: ' . $page_id . ')';
                }
            }
            if ( $category_id > 0 && in_array( $rule['match_mode'] ?? '', [ 'category_archive', 'single_posts_category' ], true ) ) {
                $cat = get_category( $category_id );
                $category_name = $cat && ! is_wp_error( $cat ) ? $cat->name : '(ID: ' . $category_id . ')';
            }
            fputcsv( $out, [
                $rule['name'] ?? '',
                ! empty( $rule['active'] ) ? '1' : '0',
                $rule['match_mode'] ?? 'single_posts',
                $page_id,
                $page_title,
                $category_id,
                $category_name,
                $rule['selector'] ?? '',
                $rule['position'] ?? 'append',
                isset( $rule['paragraph_number'] ) ? (int) $rule['paragraph_number'] : 1,
                $rule['device_target'] ?? 'both',
                $rule['alignment'] ?? 'none',
                $variant_name,
                $variant_code,
            ] );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream.
        fclose( $out );
    }
    
    /**
     * Esporta tutte le regole in un file Excel (.xlsx) - richiede PhpSpreadsheet
     */
    private function export_rules_to_excel() {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle( __( 'Regole', 'smart-div-injector' ) );
        $headers = [
            'name', 'active', 'match_mode', 'page_id', 'page_title', 'category_id', 'category_name',
            'selector', 'position', 'paragraph_number', 'device_target', 'alignment', 'variant_name', 'variant_code',
        ];
        $col = 'A';
        foreach ( $headers as $h ) {
            $sheet->setCellValue( $col . '1', $h );
            $col++;
        }
        $rules = $this->get_rules();
        $row = 2;
        foreach ( $rules as $rule_id => $rule ) {
            $variant_name = 'Variante 1';
            $variant_code = '';
            if ( isset( $rule['code'] ) && ! isset( $rule['variants'] ) ) {
                $variant_code = $rule['code'];
            } else {
                $variants   = $rule['variants'] ?? [];
                $active_idx = isset( $rule['active_variant'] ) ? (int) $rule['active_variant'] : 0;
                if ( ! empty( $variants ) && isset( $variants[ $active_idx ] ) ) {
                    $variant_name = $variants[ $active_idx ]['name'] ?? 'Variante 1';
                    $variant_code = $variants[ $active_idx ]['code'] ?? '';
                } elseif ( ! empty( $variants ) && isset( $variants[0] ) ) {
                    $variant_name = $variants[0]['name'] ?? 'Variante 1';
                    $variant_code = $variants[0]['code'] ?? '';
                }
            }
            $page_id = isset( $rule['page_id'] ) ? (int) $rule['page_id'] : 0;
            $category_id = isset( $rule['category_id'] ) ? (int) $rule['category_id'] : 0;
            $page_title = '';
            $category_name = '';
            if ( $page_id > 0 && ( $rule['match_mode'] ?? '' ) === 'page' ) {
                $page_title = get_the_title( $page_id );
                if ( $page_title === '' ) {
                    $page_title = '(ID: ' . $page_id . ')';
                }
            }
            if ( $category_id > 0 && in_array( $rule['match_mode'] ?? '', [ 'category_archive', 'single_posts_category' ], true ) ) {
                $cat = get_category( $category_id );
                $category_name = $cat && ! is_wp_error( $cat ) ? $cat->name : '(ID: ' . $category_id . ')';
            }
            $values = [
                $rule['name'] ?? '',
                ! empty( $rule['active'] ) ? '1' : '0',
                $rule['match_mode'] ?? 'single_posts',
                $page_id,
                $page_title,
                $category_id,
                $category_name,
                $rule['selector'] ?? '',
                $rule['position'] ?? 'append',
                isset( $rule['paragraph_number'] ) ? (int) $rule['paragraph_number'] : 1,
                $rule['device_target'] ?? 'both',
                $rule['alignment'] ?? 'none',
                $variant_name,
                $variant_code,
            ];
            $col = 'A';
            foreach ( $values as $val ) {
                $sheet->setCellValue( $col . $row, $val );
                $col++;
            }
            $row++;
        }
        $filename = 'smart-div-injector-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.xlsx';
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-public' );
        header( 'Expires: 0' );
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $writer->save( 'php://output' );
    }
    
    /**
     * Importa regole da file CSV (supporta campi con virgole e spazi tramite quoting CSV)
     */
    private function import_rules_from_csv( $file ) {
        $errors = [];
        $imported = 0;
        $tmp = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
        if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
            $errors[] = __( 'File non valido o non caricato.', 'smart-div-injector' );
            return [ 'imported' => 0, 'errors' => $errors ];
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading uploaded temp file; WP_Filesystem not suitable.
        $handle = fopen( $tmp, 'r' );
        if ( $handle === false ) {
            $errors[] = __( 'Impossibile aprire il file CSV.', 'smart-div-injector' );
            return [ 'imported' => 0, 'errors' => $errors ];
        }
        // BOM UTF-8
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Reading uploaded temp file.
        $bom = fread( $handle, 3 );
        if ( $bom !== chr(0xEF) . chr(0xBB) . chr(0xBF) ) {
            rewind( $handle );
        }
        $header = fgetcsv( $handle );
        if ( $header === false ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Uploaded temp file.
            fclose( $handle );
            $errors[] = __( 'Il file CSV è vuoto o non valido.', 'smart-div-injector' );
            return [ 'imported' => 0, 'errors' => $errors ];
        }
        $header = array_map( 'trim', $header );
        $rows = [];
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $rows[] = $row;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Uploaded temp file.
        fclose( $handle );
        return $this->import_rules_from_rows( $header, $rows );
    }
    
    /**
     * Importa da upload: rileva CSV o XLSX e delega al parser appropriato
     */
    private function import_rules_from_upload( $file ) {
        $name = isset( $file['name'] ) ? $file['name'] : '';
        $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( $ext === 'xlsx' && $this->excel_available() ) {
            return $this->import_rules_from_xlsx( $file );
        }
        return $this->import_rules_from_csv( $file );
    }
    
    /**
     * Importa regole da file Excel (.xlsx) - richiede PhpSpreadsheet
     */
    private function import_rules_from_xlsx( $file ) {
        $errors = [];
        $tmp = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
        if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
            $errors[] = __( 'File non valido o non caricato.', 'smart-div-injector' );
            return [ 'imported' => 0, 'errors' => $errors ];
        }
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $tmp );
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray( '', true, true, true );
            $rows        = array_values( $rows );
            if ( empty( $rows ) ) {
                $errors[] = __( 'Il file Excel è vuoto o non valido.', 'smart-div-injector' );
                return [ 'imported' => 0, 'errors' => $errors ];
            }
            $header = array_map( 'trim', array_values( $rows[0] ) );
            $data_rows = [];
            for ( $i = 1; $i < count( $rows ); $i++ ) {
                $data_rows[] = array_values( $rows[ $i ] );
            }
            return $this->import_rules_from_rows( $header, $data_rows );
        } catch ( \Exception $e ) {
            $errors[] = __( 'Errore lettura Excel:', 'smart-div-injector' ) . ' ' . $e->getMessage();
            return [ 'imported' => 0, 'errors' => $errors ];
        }
    }
    
    /**
     * Importa regole a partire da intestazione e righe (usato da CSV e Excel)
     */
    private function import_rules_from_rows( $header, $rows ) {
        $errors = [];
        $imported = 0;
        $rules = $this->get_rules();
        $row_num = 1;
        foreach ( $rows as $row ) {
            $row_num++;
            if ( count( $row ) < 2 ) {
                continue;
            }
            $assoc = array_combine( $header, array_pad( $row, count( $header ), '' ) );
            if ( $assoc === false ) {
                $assoc = [];
                foreach ( $header as $i => $key ) {
                    $assoc[ $key ] = isset( $row[ $i ] ) ? $row[ $i ] : '';
                }
            }
            $data = [
                'name'              => isset( $assoc['name'] ) ? $assoc['name'] : 'Regola importata ' . $row_num,
                'active'            => ( isset( $assoc['active'] ) && ( $assoc['active'] === '1' || strtolower( $assoc['active'] ) === 'sì' || strtolower( $assoc['active'] ) === 'si' ) ) ? '1' : '0',
                'match_mode'        => isset( $assoc['match_mode'] ) ? trim( $assoc['match_mode'] ) : 'single_posts',
                'page_id'           => isset( $assoc['page_id'] ) ? absint( $assoc['page_id'] ) : 0,
                'category_id'       => isset( $assoc['category_id'] ) ? absint( $assoc['category_id'] ) : 0,
                'selector'          => isset( $assoc['selector'] ) ? trim( $assoc['selector'] ) : '',
                'position'          => isset( $assoc['position'] ) ? trim( $assoc['position'] ) : 'append',
                'paragraph_number'  => isset( $assoc['paragraph_number'] ) ? absint( $assoc['paragraph_number'] ) : 1,
                'device_target'     => isset( $assoc['device_target'] ) ? trim( $assoc['device_target'] ) : 'both',
                'alignment'         => isset( $assoc['alignment'] ) ? trim( $assoc['alignment'] ) : 'none',
                'active_variant'    => 0,
                'variant_names'     => [ isset( $assoc['variant_name'] ) ? trim( $assoc['variant_name'] ) : 'Variante 1' ],
                'variant_codes'     => [ isset( $assoc['variant_code'] ) ? $assoc['variant_code'] : '' ],
            ];
            $rule = $this->sanitize_rule_data( $data );
            $rule_id = $this->generate_rule_id();
            $rules[ $rule_id ] = $rule;
            $imported++;
        }
        if ( $imported > 0 ) {
            $this->save_rules( $rules );
        }
        return [ 'imported' => $imported, 'errors' => $errors ];
    }
    
    /**
     * Salva una nuova regola dai dati POST
     * Nonce verificato in handle_actions() prima della chiamata.
     */
    private function save_rule_from_post() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
        $rule = $this->sanitize_rule_data( $_POST );
        $rule_id = $this->generate_rule_id();
        
        $rules = $this->get_rules();
        $rules[ $rule_id ] = $rule;
        $this->save_rules( $rules );
    }
    
    /**
     * Aggiorna una regola esistente
     * Nonce verificato in handle_actions() prima della chiamata.
     */
    private function update_rule_from_post( $rule_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
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
     * Modifica rapida di una regola (quick edit)
     * Nonce verificato in handle_actions() prima della chiamata.
     */
    private function quick_edit_rule( $rule_id ) {
        $rules = $this->get_rules();
        
        if ( ! isset( $rules[ $rule_id ] ) ) {
            return;
        }
        
        $rule = $rules[ $rule_id ];
        
        // Aggiorna solo i campi modificabili tramite quick edit.
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
        if ( isset( $_POST['name'] ) ) {
            $rule['name'] = sanitize_text_field( wp_unslash( $_POST['name'] ) );
        }
        
        if ( isset( $_POST['active'] ) ) {
            $rule['active'] = $_POST['active'] === '1';
        } else {
            $rule['active'] = false;
        }
        
        if ( isset( $_POST['device_target'] ) ) {
            $valid_devices = [ 'both', 'desktop', 'mobile' ];
            $device_target = sanitize_text_field( wp_unslash( $_POST['device_target'] ) );
            if ( in_array( $device_target, $valid_devices, true ) ) {
                $rule['device_target'] = $device_target;
            }
        }
        
        if ( isset( $_POST['alignment'] ) ) {
            $valid_alignments = [ 'none', 'left', 'right', 'center' ];
            $alignment = sanitize_text_field( wp_unslash( $_POST['alignment'] ) );
            if ( in_array( $alignment, $valid_alignments, true ) ) {
                $rule['alignment'] = $alignment;
            }
        }
        
        if ( isset( $_POST['active_variant'] ) ) {
            $active_variant = absint( $_POST['active_variant'] );
            $variants = $rule['variants'] ?? [];
            
            // Verifica che l'indice sia valido
            if ( $active_variant >= 0 && $active_variant < count( $variants ) ) {
                $rule['active_variant'] = $active_variant;
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        $rules[ $rule_id ] = $rule;
        $this->save_rules( $rules );
    }
    
    /**
     * Sanitizza i dati della regola
     */
    private function sanitize_rule_data( $data ) {
        $valid_modes = [ 'single_posts', 'category_archive', 'single_posts_category', 'page', 'site_wide' ];
        $valid_positions = [ 
            'append', 'prepend', 'before', 'after', 'replace',
            'before_post', 'before_content', 'after_content',
            'before_paragraph', 'after_paragraph',
            'before_image', 'after_image'
        ];
        $valid_devices = [ 'both', 'desktop', 'mobile' ];
        $valid_alignments = [ 'none', 'left', 'right', 'center' ];
        
        $rule = [
            'name'              => isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : 'Regola senza nome',
            'active'            => isset( $data['active'] ) && $data['active'] === '1',
            'match_mode'        => in_array( $data['match_mode'] ?? 'single_posts', $valid_modes, true ) ? ( $data['match_mode'] ) : 'single_posts',
            'page_id'           => isset( $data['page_id'] ) ? absint( $data['page_id'] ) : 0,
            'category_id'       => isset( $data['category_id'] ) ? absint( $data['category_id'] ) : 0,
            'selector'          => isset( $data['selector'] ) ? sanitize_text_field( wp_unslash( $data['selector'] ) ) : '',
            'position'          => in_array( $data['position'] ?? 'append', $valid_positions, true ) ? ( $data['position'] ) : 'append',
            'paragraph_number'  => isset( $data['paragraph_number'] ) ? absint( $data['paragraph_number'] ) : 1,
            'device_target'     => in_array( $data['device_target'] ?? 'both', $valid_devices, true ) ? ( $data['device_target'] ) : 'both',
            'alignment'         => in_array( $data['alignment'] ?? 'none', $valid_alignments, true ) ? ( $data['alignment'] ) : 'none',
            'active_variant'    => isset( $data['active_variant'] ) ? absint( $data['active_variant'] ) : 0,
        ];
        
        // Sanitizza le varianti
        $variants = [];
        
        if ( isset( $data['variant_names'] ) && is_array( $data['variant_names'] ) ) {
            foreach ( $data['variant_names'] as $index => $variant_name ) {
                $variant_code = $data['variant_codes'][ $index ] ?? '';
                
                // Rimuovi escape automatici
                $variant_code = stripslashes( $variant_code );
                
                // Sanitizza il codice
                if ( current_user_can( 'unfiltered_html' ) ) {
                    $sanitized_code = $variant_code;
                } else {
                    $allowed_html = wp_kses_allowed_html( 'post' );
                    
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
                    
                    $sanitized_code = wp_kses( $variant_code, $allowed_html );
                }
                
                // Salva SEMPRE la variante, anche se il codice è vuoto
                // (mantiene gli indici corretti e l'utente può compilarla dopo)
                $variants[] = [
                    'name' => sanitize_text_field( wp_unslash( $variant_name ) ),
                    'code' => $sanitized_code,
                ];
            }
        }
        
        // Se non ci sono varianti, crea una di default
        if ( empty( $variants ) ) {
            $variants[] = [
                'name' => 'Variante 1',
                'code' => '',
            ];
        }
        
        $rule['variants'] = $variants;
        
        // Assicurati che active_variant sia valido
        if ( $rule['active_variant'] >= count( $variants ) ) {
            $rule['active_variant'] = 0;
        }
        
        return $rule;
    }
    
    /**
     * Ottieni il codice della variante attiva per una regola
     */
    private function get_active_variant_code( $rule ) {
        // Retrocompatibilità: se esiste 'code' direttamente, usalo
        if ( isset( $rule['code'] ) && ! isset( $rule['variants'] ) ) {
            return $rule['code'];
        }
        
        // Ottieni le varianti
        $variants = $rule['variants'] ?? [];
        if ( empty( $variants ) ) {
            return '';
        }
        
        // Ottieni l'indice della variante attiva
        $active_index = $rule['active_variant'] ?? 0;
        
        // Assicurati che l'indice sia valido
        if ( ! isset( $variants[ $active_index ] ) ) {
            $active_index = 0;
        }
        
        return $variants[ $active_index ]['code'] ?? '';
    }
    
    /**
     * Applica lo stile di allineamento al codice
     */
    private function apply_alignment( $code, $alignment ) {
        // Se non c'è allineamento specifico, restituisci il codice così com'è
        if ( empty( $alignment ) || $alignment === 'none' ) {
            return $code;
        }
        
        // Genera lo stile in base all'allineamento
        $style = '';
        $clear_style = '';
        
        switch ( $alignment ) {
            case 'left':
                $style = 'float: left; margin-right: 20px; margin-bottom: 15px;';
                $clear_style = 'clear: both;';
                break;
            case 'right':
                $style = 'float: right; margin-left: 20px; margin-bottom: 15px;';
                $clear_style = 'clear: both;';
                break;
            case 'center':
                $style = 'margin: 0 auto 15px auto; display: block; text-align: center; clear: both;';
                break;
        }
        
        // Wrappa il contenuto con un div che ha lo stile appropriato
        $wrapper_open = '<div class="sdi-alignment-wrapper sdi-alignment-' . esc_attr( $alignment ) . '" style="' . esc_attr( $style ) . '">';
        $wrapper_close = '</div>';
        
        // Se è float, aggiungi anche un clearfix dopo
        if ( in_array( $alignment, [ 'left', 'right' ] ) ) {
            $wrapper_close .= '<div style="' . esc_attr( $clear_style ) . '"></div>';
        }
        
        return $wrapper_open . $code . $wrapper_close;
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
        
        // Determina quale vista mostrare (GET usato solo per visualizzazione, non modifica stato).
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET used for display-only routing.
        $action   = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        $rule_id  = isset( $_GET['rule_id'] ) ? sanitize_text_field( wp_unslash( $_GET['rule_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( $action === 'edit' && $rule_id !== '' ) {
            $this->render_edit_rule_page( $rule_id );
        } elseif ( $action === 'add' ) {
            $this->render_add_rule_page();
        } else {
            $this->render_rules_list_page();
        }
    }
    
    /**
     * Render della lista delle regole
     */
    private function render_rules_list_page() {
        $all_rules = $this->get_rules();
        
        // Parametri di ricerca, filtri e paginazione (GET solo lettura per filtri/display).
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET used for display and filter only.
        $search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
        $filter_type   = isset( $_GET['filter_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_type'] ) ) : '';
        $filter_device = isset( $_GET['filter_device'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_device'] ) ) : '';
        $per_page      = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
        $paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        
        // Applica filtri
        $filtered_rules = $all_rules;
        
        // Filtro per ricerca (nome)
        if ( ! empty( $search ) ) {
            $filtered_rules = array_filter( $filtered_rules, function( $rule ) use ( $search ) {
                return stripos( $rule['name'], $search ) !== false;
            });
        }
        
        // Filtro per stato
        if ( $filter_status === 'active' ) {
            $filtered_rules = array_filter( $filtered_rules, function( $rule ) {
                return ! empty( $rule['active'] );
            });
        } elseif ( $filter_status === 'inactive' ) {
            $filtered_rules = array_filter( $filtered_rules, function( $rule ) {
                return empty( $rule['active'] );
            });
        }
        
        // Filtro per tipo
        if ( ! empty( $filter_type ) ) {
            $filtered_rules = array_filter( $filtered_rules, function( $rule ) use ( $filter_type ) {
                return $rule['match_mode'] === $filter_type;
            });
        }
        
        // Filtro per dispositivo
        if ( ! empty( $filter_device ) ) {
            $filtered_rules = array_filter( $filtered_rules, function( $rule ) use ( $filter_device ) {
                return ( $rule['device_target'] ?? 'both' ) === $filter_device;
            });
        }
        
        // Calcolo paginazione
        $total_items = count( $filtered_rules );
        $total_pages = ceil( $total_items / $per_page );
        $offset = ( $paged - 1 ) * $per_page;
        
        // Estrai solo gli elementi della pagina corrente
        $rules = array_slice( $filtered_rules, $offset, $per_page, true );
        
        // Messaggi di conferma
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET used for display message only.
        $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
        
        // Costruisci URL base per mantenere i filtri
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $base_url    = remove_query_arg( [ 'message', 'paged' ], $request_uri );
        
        ?>
        <div class="wrap">
            <div class="sdi-header">
                <h1>
                    <span class="dashicons dashicons-admin-generic"></span>
                    Smart Div Injector
                </h1>
                <div class="sdi-header-actions">
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=download_csv_template' ), 'sdi_download_csv_template', '_wpnonce' ) ); ?>" class="button">
                        <span class="dashicons dashicons-download"></span>
                        Scarica template CSV
                    </a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=export_csv' ), 'sdi_export_csv', '_wpnonce' ) ); ?>" class="button">
                        <span class="dashicons dashicons-database-export"></span>
                        Esporta CSV
                    </a>
                    <?php if ( $this->excel_available() ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=export_excel' ), 'sdi_export_excel', '_wpnonce' ) ); ?>" class="button">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        Esporta Excel
                    </a>
                    <?php endif; ?>
                    <form method="post" action="" enctype="multipart/form-data" class="sdi-import-form" style="display:inline;">
                        <?php wp_nonce_field( 'sdi_rule_action', 'sdi_nonce' ); ?>
                        <input type="hidden" name="sdi_action" value="import_csv">
                        <label for="sdi-csv-file" class="button">
                            <span class="dashicons dashicons-upload"></span>
                            Importa CSV<?php echo $this->excel_available() ? ' / Excel' : ''; ?>
                        </label>
                        <input type="file" id="sdi-csv-file" name="sdi_csv_file" accept=".csv,.xlsx" style="display:none;" required>
                        <script>
                        (function(){
                            var f = document.getElementById('sdi-csv-file');
                            var form = f && f.closest('form');
                            if (f && form) f.addEventListener('change', function(){ if (f.files.length) form.submit(); });
                        })();
                        </script>
                    </form>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector&action=add' ) ); ?>" class="button sdi-add-button">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Aggiungi Nuova Regola
                    </a>
                </div>
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
            <?php elseif ( $message === 'bulk_deleted' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ <?php echo esc_html( isset( $_GET['count'] ) ? absint( wp_unslash( $_GET['count'] ) ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only. ?> regole eliminate con successo.</strong></p>
                </div>
            <?php elseif ( $message === 'duplicated' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ Regola duplicata con successo!</strong></p>
                </div>
            <?php elseif ( $message === 'quick_updated' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ Modifica rapida completata con successo!</strong></p>
                </div>
            <?php elseif ( $message === 'imported_one' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ 1 regola importata con successo.</strong></p>
                </div>
            <?php elseif ( $message === 'imported_many' ) : ?>
                <div class="sdi-notice success">
                    <p><strong>✓ <?php echo esc_html( isset( $_GET['count'] ) ? absint( wp_unslash( $_GET['count'] ) ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only. ?> regole importate con successo.</strong></p>
                </div>
            <?php elseif ( $message === 'imported_zero' ) : ?>
                <div class="sdi-notice notice-warning">
                    <p><strong>Nessuna regola importata.</strong> Il file CSV è vuoto o contiene solo intestazioni. Aggiungi almeno una riga di dati.</p>
                </div>
            <?php
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET used for display only.
            elseif ( $message === 'import_errors' && ! empty( $_GET['errors'] ) ) : ?>
                <?php
                $err_json = isset( $_GET['errors'] ) ? sanitize_text_field( wp_unslash( $_GET['errors'] ) ) : '';
                $err_list = json_decode( $err_json, true );
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
                ?>
                <div class="sdi-notice error">
                    <p><strong>Importazione CSV:</strong></p>
                    <ul>
                        <?php foreach ( is_array( $err_list ) ? $err_list : [] as $e ) : ?>
                            <li><?php echo esc_html( $e ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ( empty( $all_rules ) ) : ?>
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
                <!-- Sezione ricerca e filtri -->
                <div class="sdi-filters-wrapper" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin: 20px 0; border-radius: 4px;">
                    <form method="get" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                        <input type="hidden" name="page" value="smart-div-injector">
                        
                        <!-- Ricerca -->
                        <div style="flex: 1; min-width: 250px;">
                            <label for="sdi-search" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                🔍 Cerca per nome
                            </label>
                            <input type="text" 
                                   id="sdi-search" 
                                   name="s" 
                                   value="<?php echo esc_attr( $search ); ?>" 
                                   placeholder="Inserisci nome regola..."
                                   style="width: 100%;">
                        </div>
                        
                        <!-- Filtro Stato -->
                        <div style="flex: 0 0 180px;">
                            <label for="filter-status" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                Stato
                            </label>
                            <select id="filter-status" name="filter_status" style="width: 100%;">
                                <option value="">Tutte</option>
                                <option value="active" <?php selected( $filter_status, 'active' ); ?>>Solo attive</option>
                                <option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>>Solo non attive</option>
                            </select>
                        </div>
                        
                        <!-- Filtro Tipo -->
                        <div style="flex: 0 0 200px;">
                            <label for="filter-type" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                Tipo di contenuto
                            </label>
                            <select id="filter-type" name="filter_type" style="width: 100%;">
                                <option value="">Tutti i tipi</option>
                                <option value="site_wide" <?php selected( $filter_type, 'site_wide' ); ?>>🌐 Tutto il sito web</option>
                                <option value="single_posts" <?php selected( $filter_type, 'single_posts' ); ?>>📄 Tutti gli articoli</option>
                                <option value="category_archive" <?php selected( $filter_type, 'category_archive' ); ?>>📁 Archivio categoria</option>
                                <option value="single_posts_category" <?php selected( $filter_type, 'single_posts_category' ); ?>>🏷️ Articoli per categoria</option>
                                <option value="page" <?php selected( $filter_type, 'page' ); ?>>📃 Pagina specifica</option>
                            </select>
                        </div>
                        
                        <!-- Filtro Dispositivo -->
                        <div style="flex: 0 0 180px;">
                            <label for="filter-device" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                Dispositivo
                            </label>
                            <select id="filter-device" name="filter_device" style="width: 100%;">
                                <option value="">Tutti i dispositivi</option>
                                <option value="both" <?php selected( $filter_device, 'both' ); ?>>📱💻 Entrambi</option>
                                <option value="desktop" <?php selected( $filter_device, 'desktop' ); ?>>💻 Desktop</option>
                                <option value="mobile" <?php selected( $filter_device, 'mobile' ); ?>>📱 Mobile</option>
                            </select>
                        </div>
                        
                        <!-- Elementi per pagina -->
                        <div style="flex: 0 0 120px;">
                            <label for="per-page" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                Per pagina
                            </label>
                            <select id="per-page" name="per_page" style="width: 100%;">
                                <option value="10" <?php selected( $per_page, 10 ); ?>>10</option>
                                <option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
                                <option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
                                <option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
                            </select>
                        </div>
                        
                        <!-- Pulsanti -->
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="button button-primary" style="height: 32px;">
                                Applica Filtri
                            </button>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector' ) ); ?>" class="button" style="height: 32px; line-height: 30px;">
                                Reset
                            </a>
                        </div>
                    </form>
                    
                    <!-- Info risultati -->
                    <?php if ( $search || $filter_status || $filter_type || $filter_device ) : ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; color: #646970;">
                            <strong>Risultati:</strong> Trovate <?php echo number_format( $total_items ); ?> regole su <?php echo number_format( count( $all_rules ) ); ?> totali
                            <?php if ( $total_items === 0 ) : ?>
                                <span style="color: #d63638;">— Nessuna regola corrisponde ai filtri applicati</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ( empty( $rules ) && $total_items === 0 ) : ?>
                    <div class="sdi-empty-state">
                        <span class="dashicons dashicons-search"></span>
                        <h3>Nessun risultato trovato</h3>
                        <p>Nessuna regola corrisponde ai criteri di ricerca o filtri selezionati.</p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector' ) ); ?>" class="button">
                            Reset Filtri
                        </a>
                    </div>
                <?php else : ?>
                    <form method="post" action="" id="sdi-bulk-form">
                        <?php wp_nonce_field( 'sdi_rule_action', 'sdi_nonce' ); ?>
                        <input type="hidden" name="sdi_action" value="bulk_delete">
                        <div class="tablenav top" style="margin-bottom: 10px;">
                            <div class="sdi-bulk-actions alignleft actions">
                                <button type="submit" name="sdi_bulk_delete" class="button" id="sdi-bulk-delete-btn" onclick="return confirm('Eliminare le regole selezionate?');">
                                    <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                                    Elimina selezionate
                                </button>
                                <span class="sdi-bulk-count" style="margin-left: 10px; color: #646970;"></span>
                            </div>
                        </div>
                    </form>
                    <table class="wp-list-table widefat fixed striped sdi-rules-table">
                        <thead>
                            <tr>
                                <td class="check-column" style="width: 2.2em;">
                                    <input type="checkbox" id="sdi-select-all" title="Seleziona tutte">
                                </td>
                                <th scope="col" style="width: 100px;">Stato</th>
                                <th scope="col">Nome Regola</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Target</th>
                                <th scope="col" style="width: 120px;">Dispositivo</th>
                                <th scope="col" style="width: 180px;">Variante Attiva</th>
                                <th scope="col">Selettore CSS</th>
                                <th scope="col" style="width: 240px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $rules as $rule_id => $rule ) : ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" form="sdi-bulk-form" name="rule_ids[]" value="<?php echo esc_attr( $rule_id ); ?>" class="sdi-rule-checkbox">
                                    </th>
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
                                                case 'site_wide':
                                                    echo '🌐 Tutto il sito web';
                                                    break;
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
                                            if ( $rule['match_mode'] === 'site_wide' ) {
                                                echo '<span class="dashicons dashicons-admin-site"></span>';
                                                echo 'Tutto il sito';
                                            } elseif ( ( $rule['match_mode'] === 'single_posts_category' || $rule['match_mode'] === 'category_archive' ) && $rule['category_id'] ) {
                                                $cat = get_category( $rule['category_id'] );
                                                echo '<span class="dashicons dashicons-category"></span>';
                                                echo $cat ? esc_html( $cat->name ) : 'Categoria #' . esc_html( (string) $rule['category_id'] );
                                            } elseif ( $rule['match_mode'] === 'page' && $rule['page_id'] ) {
                                                echo '<span class="dashicons dashicons-admin-page"></span>';
                                                echo esc_html( get_the_title( $rule['page_id'] ) ?: 'Pagina #' . $rule['page_id'] );
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
                                    <td>
                                        <?php
                                        // Migrazione per retrocompatibilità
                                        if ( isset( $rule['code'] ) && ! isset( $rule['variants'] ) ) {
                                            echo '<span style="color: #666;">—</span>';
                                        } else {
                                            $variants = $rule['variants'] ?? [];
                                            $active_variant = $rule['active_variant'] ?? 0;
                                            $active_variant_code = isset( $variants[ $active_variant ]['code'] ) ? $variants[ $active_variant ]['code'] : '';
                                            $has_code = ! empty( trim( $active_variant_code ) );
                                            
                                            if ( empty( $variants ) ) {
                                                echo '<span style="color: #d63638;">Nessuna variante</span>';
                                            } elseif ( count( $variants ) === 1 ) {
                                                echo '<span title="' . esc_attr( $variants[0]['name'] ?? 'Variante 1' ) . '">🎯 ' . esc_html( $variants[0]['name'] ?? 'Variante 1' ) . '</span>';
                                                if ( ! $has_code ) {
                                                    echo '<br><span style="color: #d63638; font-size: 11px; font-weight: 600;">⚠️ Codice vuoto - Non verrà iniettato</span>';
                                                }
                                            } else {
                                                $active_variant_name = $variants[ $active_variant ]['name'] ?? 'Variante ' . ( $active_variant + 1 );
                                                ?>
                                                <div class="sdi-variant-selector" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                    <span class="sdi-variant-badge" title="Variante attiva">
                                                        🎯 <?php echo esc_html( $active_variant_name ); ?>
                                                    </span>
                                                    <span class="sdi-variant-count" title="Totale varianti disponibili">
                                                        (<?php echo count( $variants ); ?>)
                                                    </span>
                                                    <?php if ( ! $has_code ) : ?>
                                                        <span style="color: #d63638; font-size: 11px; font-weight: 600; width: 100%;">⚠️ Codice vuoto - Non verrà iniettato</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><code class="sdi-code"><?php echo esc_html( $rule['selector'] ); ?></code></td>
                                    <td>
                                        <div class="sdi-actions">
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector&action=edit&rule_id=' . $rule_id ) ); ?>" class="button sdi-btn-edit">Modifica</a>
                                            <button type="button" class="button sdi-btn-quick-edit" onclick="sdiShowQuickEdit('<?php echo esc_js( $rule_id ); ?>')">
                                                <span class="dashicons dashicons-edit"></span>
                                                Modifica Rapida
                                            </button>
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=duplicate&rule_id=' . $rule_id ), 'duplicate_rule_' . $rule_id ) ); ?>" class="button sdi-btn-duplicate">Duplica</a>
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smart-div-injector&action=delete&rule_id=' . $rule_id ), 'delete_rule_' . $rule_id ) ); ?>" class="button sdi-btn-delete" onclick="return confirm('Sei sicuro di voler eliminare questa regola?');">Elimina</a>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Riga Quick Edit -->
                                <tr id="quick-edit-<?php echo esc_attr( $rule_id ); ?>" class="sdi-quick-edit-row" style="display: none;">
                                    <td colspan="9">
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=smart-div-injector' ) ); ?>" class="sdi-quick-edit-form">
                                            <?php wp_nonce_field( 'sdi_rule_action', 'sdi_nonce' ); ?>
                                            <input type="hidden" name="sdi_action" value="quick_edit">
                                            <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule_id ); ?>">
                                            
                                            <div class="sdi-quick-edit-container">
                                                <div class="sdi-quick-edit-header">
                                                    <h4>✏️ Modifica Rapida: <?php echo esc_html( $rule['name'] ); ?></h4>
                                                    <button type="button" class="button" onclick="sdiCancelQuickEdit('<?php echo esc_js( $rule_id ); ?>')">
                                                        <span class="dashicons dashicons-no-alt"></span>
                                                        Annulla
                                                    </button>
                                                </div>
                                                
                                                <div class="sdi-quick-edit-fields">
                                                    <div class="sdi-quick-edit-field">
                                                        <label for="quick-name-<?php echo esc_attr( $rule_id ); ?>">Nome Regola:</label>
                                                        <input type="text" 
                                                               id="quick-name-<?php echo esc_attr( $rule_id ); ?>" 
                                                               name="name" 
                                                               value="<?php echo esc_attr( $rule['name'] ); ?>" 
                                                               class="widefat" 
                                                               required>
                                                    </div>
                                                    
                                                    <div class="sdi-quick-edit-field">
                                                        <label>
                                                            <input type="checkbox" 
                                                                   name="active" 
                                                                   value="1" 
                                                                   <?php checked( $rule['active'], true ); ?>>
                                                            <strong>Regola Attiva</strong>
                                                        </label>
                                                    </div>
                                                    
                                                    <div class="sdi-quick-edit-field">
                                                        <label for="quick-device-<?php echo esc_attr( $rule_id ); ?>">Dispositivo:</label>
                                                        <select id="quick-device-<?php echo esc_attr( $rule_id ); ?>" name="device_target" class="widefat">
                                                            <option value="both" <?php selected( $rule['device_target'] ?? 'both', 'both' ); ?>>📱💻 Entrambi</option>
                                                            <option value="desktop" <?php selected( $rule['device_target'] ?? 'both', 'desktop' ); ?>>💻 Solo Desktop</option>
                                                            <option value="mobile" <?php selected( $rule['device_target'] ?? 'both', 'mobile' ); ?>>📱 Solo Mobile</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="sdi-quick-edit-field">
                                                        <label for="quick-alignment-<?php echo esc_attr( $rule_id ); ?>">Allineamento:</label>
                                                        <select id="quick-alignment-<?php echo esc_attr( $rule_id ); ?>" name="alignment" class="widefat">
                                                            <option value="none" <?php selected( $rule['alignment'] ?? 'none', 'none' ); ?>>Nessuno</option>
                                                            <option value="left" <?php selected( $rule['alignment'] ?? 'none', 'left' ); ?>>⬅️ Float a sinistra</option>
                                                            <option value="right" <?php selected( $rule['alignment'] ?? 'none', 'right' ); ?>>➡️ Float a destra</option>
                                                            <option value="center" <?php selected( $rule['alignment'] ?? 'none', 'center' ); ?>>↔️ Centrato</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <?php 
                                                    $variants = $rule['variants'] ?? [];
                                                    if ( count( $variants ) > 1 ) :
                                                    ?>
                                                    <div class="sdi-quick-edit-field">
                                                        <label for="quick-variant-<?php echo esc_attr( $rule_id ); ?>">Variante Attiva:</label>
                                                        <select id="quick-variant-<?php echo esc_attr( $rule_id ); ?>" name="active_variant" class="widefat">
                                                            <?php foreach ( $variants as $index => $variant ) : ?>
                                                                <option value="<?php echo esc_attr( $index ); ?>" <?php selected( $rule['active_variant'] ?? 0, $index ); ?>>
                                                                    <?php echo esc_html( $variant['name'] ?? 'Variante ' . ( $index + 1 ) ); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <?php else : ?>
                                                    <input type="hidden" name="active_variant" value="<?php echo esc_attr( $rule['active_variant'] ?? 0 ); ?>">
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="sdi-quick-edit-actions">
                                                    <button type="submit" class="button button-primary">
                                                        <span class="dashicons dashicons-yes"></span>
                                                        Aggiorna
                                                    </button>
                                                    <button type="button" class="button" onclick="sdiCancelQuickEdit('<?php echo esc_js( $rule_id ); ?>')">
                                                        Annulla
                                                    </button>
                                                    <span class="sdi-quick-edit-tip">💡 Per modificare il codice, usa il pulsante "Modifica" completo</span>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Paginazione -->
                    <?php if ( $total_pages > 1 ) : ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="displaying-num"><?php printf( '%s elementi', number_format( $total_items ) ); ?></span>
                                <?php
                                $pagination_args = [
                                    'base'      => add_query_arg( 'paged', '%#%' ),
                                    'format'    => '',
                                    'prev_text' => '&laquo; Precedente',
                                    'next_text' => 'Successivo &raquo;',
                                    'total'     => $total_pages,
                                    'current'   => $paged,
                                    'type'      => 'plain',
                                ];
                                echo '<span class="pagination-links">';
                                echo wp_kses_post( paginate_links( $pagination_args ) );
                                echo '</span>';
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            
            <p class="description" style="margin-top: 20px;">
                <strong>Come funziona:</strong> Ogni regola definisce dove e come inserire il codice. Le regole attive vengono applicate automaticamente sul frontend quando le condizioni sono soddisfatte.
            </p>
        </div>
        
        <script>
        (function() {
            'use strict';
            
            // ========== SELEZIONE MASSIVA ==========
            var bulkForm = document.getElementById('sdi-bulk-form');
            if (bulkForm) {
                var selectAll = document.getElementById('sdi-select-all');
                var checkboxes = document.querySelectorAll('.sdi-rule-checkbox');
                var countSpan = bulkForm.querySelector('.sdi-bulk-count');
                function updateCount() {
                    var n = document.querySelectorAll('.sdi-rule-checkbox:checked').length;
                    countSpan.textContent = n > 0 ? n + ' selezionate' : '';
                    selectAll.checked = n > 0 && n === checkboxes.length;
                    selectAll.indeterminate = n > 0 && n < checkboxes.length;
                    var btn = document.getElementById('sdi-bulk-delete-btn');
                    if (btn) btn.disabled = n === 0;
                }
                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                        updateCount();
                    });
                }
                checkboxes.forEach(function(cb) {
                    cb.addEventListener('change', updateCount);
                });
                updateCount();
            }
            
            // ========== QUICK EDIT ==========
            window.sdiShowQuickEdit = function(ruleId) {
                // Nascondi tutti gli altri quick edit aperti
                var allRows = document.querySelectorAll('.sdi-quick-edit-row');
                allRows.forEach(function(row) {
                    row.style.display = 'none';
                });
                
                // Mostra il quick edit per questa regola
                var quickEditRow = document.getElementById('quick-edit-' + ruleId);
                if (quickEditRow) {
                    quickEditRow.style.display = 'table-row';
                    
                    // Scroll verso il quick edit
                    setTimeout(function() {
                        quickEditRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 100);
                    
                    // Focus sul primo campo
                    var firstInput = quickEditRow.querySelector('input[type="text"]');
                    if (firstInput) {
                        setTimeout(function() {
                            firstInput.focus();
                            firstInput.select();
                        }, 400);
                    }
                }
            };
            
            window.sdiCancelQuickEdit = function(ruleId) {
                var quickEditRow = document.getElementById('quick-edit-' + ruleId);
                if (quickEditRow) {
                    quickEditRow.style.display = 'none';
                }
            };
            
            // Chiudi quick edit con Esc (solo se non già registrato)
            if (!window.sdiQuickEditEscListenerRegistered) {
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        var allRows = document.querySelectorAll('.sdi-quick-edit-row');
                        allRows.forEach(function(row) {
                            row.style.display = 'none';
                        });
                    }
                });
                window.sdiQuickEditEscListenerRegistered = true;
            }
        })();
        </script>
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
            'alignment'        => 'none',
            'active_variant'   => 0,
            'variants'         => [
                [
                    'name' => 'Variante 1',
                    'code' => ''
                ]
            ]
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
        
        // Migrazione: converti vecchie regole con 'code' in varianti
        if ( isset( $rule['code'] ) && ! isset( $rule['variants'] ) ) {
            $rule['variants'] = [
                [
                    'name' => 'Variante 1',
                    'code' => $rule['code']
                ]
            ];
            $rule['active_variant'] = 0;
            unset( $rule['code'] );
        }
        
        // Assicurati che le varianti esistano
        if ( ! isset( $rule['variants'] ) || empty( $rule['variants'] ) ) {
            $rule['variants'] = [
                [
                    'name' => 'Variante 1',
                    'code' => ''
                ]
            ];
        }
        
        // Assicurati che active_variant sia impostato
        if ( ! isset( $rule['active_variant'] ) ) {
            $rule['active_variant'] = 0;
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
                        <th scope="row"><label for="alignment">Posizionamento contenuto</label></th>
                        <td>
                            <select name="alignment" id="alignment" class="regular-text">
                                <option value="none" <?php selected( $rule['alignment'] ?? 'none', 'none' ); ?>>Nessuno (posizione naturale)</option>
                                <option value="left" <?php selected( $rule['alignment'] ?? 'none', 'left' ); ?>>⬅️ Float a sinistra</option>
                                <option value="right" <?php selected( $rule['alignment'] ?? 'none', 'right' ); ?>>➡️ Float a destra</option>
                                <option value="center" <?php selected( $rule['alignment'] ?? 'none', 'center' ); ?>>↔️ Centrato</option>
                            </select>
                            <p class="description">Scegli come posizionare il contenuto iniettato. Float permette al testo di fluire intorno al contenuto.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="match_mode">Tipo di contenuto *</label></th>
                        <td>
                            <select name="match_mode" id="match_mode" onchange="sdiToggleFields()">
                                <option value="site_wide" <?php selected( $rule['match_mode'], 'site_wide' ); ?>>🌐 Tutto il sito web</option>
                                <option value="single_posts" <?php selected( $rule['match_mode'], 'single_posts' ); ?>>📄 Tutti gli articoli</option>
                                <option value="category_archive" <?php selected( $rule['match_mode'], 'category_archive' ); ?>>📁 Pagina archivio categoria</option>
                                <option value="single_posts_category" <?php selected( $rule['match_mode'], 'single_posts_category' ); ?>>🏷️ Articoli di una categoria</option>
                                <option value="page" <?php selected( $rule['match_mode'], 'page' ); ?>>📃 Pagina specifica</option>
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
                                    <p><strong>⚠️ Attenzione:</strong> Il tuo sito ha <strong><?php echo number_format( $total_pages ); ?></strong> pagine. Il dropdown mostra solo le prime <strong><?php echo esc_html( (string) $limit ); ?></strong>.</p>
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
                        <th scope="row" style="vertical-align: top; padding-top: 20px;">
                            <label>Varianti Codice *</label>
                        </th>
                        <td>
                            <div class="sdi-notice info" style="margin-bottom: 20px;">
                                <p><strong>💡 Varianti Multiple:</strong> Puoi creare più versioni del codice e scegliere quale attivare. Perfetto per A/B testing o per avere diverse versioni pronte.</p>
                            </div>
                            
                            <input type="hidden" name="active_variant" id="active_variant" value="<?php echo esc_attr( $rule['active_variant'] ?? 0 ); ?>">
                            
                            <div id="variants-container">
                                <?php 
                                $variants = $rule['variants'] ?? [];
                                $active_variant = $rule['active_variant'] ?? 0;
                                
                                foreach ( $variants as $index => $variant ) : 
                                    $variant_name = $variant['name'] ?? 'Variante ' . ( $index + 1 );
                                    $variant_code = $variant['code'] ?? '';
                                    $is_active = ( $index === $active_variant );
                                ?>
                                    <div class="sdi-variant-item" data-variant-index="<?php echo esc_attr( (string) $index ); ?>">
                                        <div class="sdi-variant-header">
                                            <div class="sdi-variant-title">
                                                <span class="sdi-variant-number">Variante #<?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
                                                <input type="text" 
                                                       name="variant_names[]" 
                                                       value="<?php echo esc_attr( $variant_name ); ?>" 
                                                       class="sdi-variant-name-input" 
                                                       placeholder="Nome variante (es. Banner Natale)"
                                                       required>
                                            </div>
                                            <div class="sdi-variant-actions">
                                                <?php if ( $is_active ) : ?>
                                                    <span class="sdi-active-badge">✓ Attiva</span>
                                                <?php else : ?>
                                                    <button type="button" class="button sdi-btn-activate-variant" onclick="sdiActivateVariant(<?php echo esc_attr( (string) $index ); ?>)">Attiva questa</button>
                                                <?php endif; ?>
                                                <?php if ( count( $variants ) > 1 ) : ?>
                                                    <button type="button" class="button sdi-btn-delete-variant" onclick="sdiRemoveVariant(<?php echo esc_attr( (string) $index ); ?>)" title="Elimina variante">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="sdi-variant-body">
                                            <?php if ( $is_active && empty( trim( $variant_code ) ) ) : ?>
                                                <div style="background: #fcf3cf; border-left: 4px solid #f39c12; padding: 12px; margin-bottom: 10px;">
                                                    <strong>⚠️ Attenzione:</strong> Questa è la variante attiva ma il codice è vuoto. Il codice non verrà iniettato finché non aggiungi del contenuto qui.
                                                </div>
                                            <?php endif; ?>
                                            <textarea name="variant_codes[]" 
                                                      rows="8" 
                                                      class="large-text code sdi-variant-code <?php echo ( $is_active && empty( trim( $variant_code ) ) ) ? 'sdi-empty-active' : ''; ?>" 
                                                      spellcheck="false" 
                                                      placeholder="<div>Il tuo codice HTML/JS/CSS</div>"
                                                      required><?php echo esc_textarea( $variant_code ); ?></textarea>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <button type="button" class="button" onclick="sdiAddVariant()">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    Aggiungi Nuova Variante
                                </button>
                            </div>
                            
                            <p class="description" style="margin-top: 15px;">
                                Il codice verrà inserito tal quale. Solo gli utenti con permesso <code>unfiltered_html</code> possono salvare script non sanitizzati.
                                <br><strong>Nota:</strong> Solo la variante attiva verrà mostrata sul frontend.
                            </p>
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
        
        // ========== GESTIONE VARIANTI ==========
        
        (function() {
            'use strict';
            
            var variantCounter = <?php echo count( $rule['variants'] ?? [] ); ?>;
            
            window.sdiAddVariant = function() {
            var container = document.getElementById('variants-container');
            var newIndex = variantCounter++;
            
            var variantHTML = `
                <div class="sdi-variant-item" data-variant-index="${newIndex}">
                    <div class="sdi-variant-header">
                        <div class="sdi-variant-title">
                            <span class="sdi-variant-number">Variante #${newIndex + 1}</span>
                            <input type="text" 
                                   name="variant_names[]" 
                                   value="Variante ${newIndex + 1}" 
                                   class="sdi-variant-name-input" 
                                   placeholder="Nome variante (es. Banner Natale)"
                                   required>
                        </div>
                        <div class="sdi-variant-actions">
                            <button type="button" class="button sdi-btn-activate-variant" onclick="sdiActivateVariant(${newIndex})">Attiva questa</button>
                            <button type="button" class="button sdi-btn-delete-variant" onclick="sdiRemoveVariant(${newIndex})" title="Elimina variante">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="sdi-variant-body">
                        <textarea name="variant_codes[]" 
                                  rows="8" 
                                  class="large-text code sdi-variant-code" 
                                  spellcheck="false" 
                                  placeholder="<div>Il tuo codice HTML/JS/CSS</div>"
                                  required></textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', variantHTML);
            sdiUpdateVariantNumbers();
        };
        
        window.sdiRemoveVariant = function(index) {
            var variants = document.querySelectorAll('.sdi-variant-item');
            if (variants.length <= 1) {
                alert('Devi avere almeno una variante!');
                return;
            }
            
            if (!confirm('Sei sicuro di voler eliminare questa variante?')) {
                return;
            }
            
            var variantToRemove = document.querySelector('.sdi-variant-item[data-variant-index="' + index + '"]');
            if (variantToRemove) {
                variantToRemove.remove();
                sdiUpdateVariantNumbers();
                
                // Se abbiamo eliminato la variante attiva, attiva la prima
                var activeVariantInput = document.getElementById('active_variant');
                if (parseInt(activeVariantInput.value) === index) {
                    sdiActivateVariant(0);
                } else if (parseInt(activeVariantInput.value) > index) {
                    // Decrementa l'indice se necessario
                    activeVariantInput.value = parseInt(activeVariantInput.value) - 1;
                }
            }
        };
        
        window.sdiActivateVariant = function(index) {
            // Aggiorna il campo hidden
            document.getElementById('active_variant').value = index;
            
            // Aggiorna l'UI
            document.querySelectorAll('.sdi-variant-item').forEach(function(item, idx) {
                var actionsDiv = item.querySelector('.sdi-variant-actions');
                if (idx === index) {
                    actionsDiv.innerHTML = '<span class="sdi-active-badge">✓ Attiva</span>';
                    if (item.querySelectorAll('.sdi-btn-delete-variant').length === 0 && document.querySelectorAll('.sdi-variant-item').length > 1) {
                        actionsDiv.innerHTML += `
                            <button type="button" class="button sdi-btn-delete-variant" onclick="sdiRemoveVariant(${idx})" title="Elimina variante">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        `;
                    }
                } else {
                    actionsDiv.innerHTML = `
                        <button type="button" class="button sdi-btn-activate-variant" onclick="sdiActivateVariant(${idx})">Attiva questa</button>
                        ${document.querySelectorAll('.sdi-variant-item').length > 1 ? `
                        <button type="button" class="button sdi-btn-delete-variant" onclick="sdiRemoveVariant(${idx})" title="Elimina variante">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                        ` : ''}
                    `;
                }
            });
        };
        
        function sdiUpdateVariantNumbers() {
            document.querySelectorAll('.sdi-variant-item').forEach(function(item, index) {
                item.setAttribute('data-variant-index', index);
                item.querySelector('.sdi-variant-number').textContent = 'Variante #' + (index + 1);
                
                // Aggiorna anche i pulsanti per mantenere gli indici corretti
                var activateBtn = item.querySelector('.sdi-btn-activate-variant');
                if (activateBtn) {
                    activateBtn.setAttribute('onclick', 'sdiActivateVariant(' + index + ')');
                }
                var deleteBtn = item.querySelector('.sdi-btn-delete-variant');
                if (deleteBtn) {
                    deleteBtn.setAttribute('onclick', 'sdiRemoveVariant(' + index + ')');
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
        })();
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
                        $site_rules = $this->get_rules();
                        $is_configured = ! empty( $site_rules );
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
                case 'site_wide':
                    // Tutto il sito web - match sempre
                    $match = true;
                    break;
                    
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
                    
                    // Ottieni il codice della variante attiva
                    $variant_code = $this->get_active_variant_code( $rule );
                    
                    // Debug: Aggiungi un commento HTML se WP_DEBUG è attivo e il codice è vuoto
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && empty( trim( $variant_code ) ) ) {
                        add_action( 'wp_footer', function() use ( $rule_id, $rule ) {
                            $active_var     = $rule['active_variant'] ?? 0;
                            $variants_count = count( $rule['variants'] ?? [] );
                            echo '<!-- Smart Div Injector DEBUG: Regola \'' . esc_html( $rule['name'] ?? 'Senza nome' ) . '\' (ID: ' . esc_html( (string) $rule_id ) . ') - Variante attiva #' . esc_html( (string) $active_var ) . '/' . esc_html( (string) $variants_count ) . ' ha codice vuoto -->' . "\n";
                        }, 999 );
                    }
                    
                    // Se non c'è codice, salta questa regola
                    if ( empty( trim( $variant_code ) ) ) {
                        continue;
                    }
                    
                    // Applica l'allineamento al codice
                    $aligned_code = $this->apply_alignment( 
                        $variant_code, 
                        $rule['alignment'] ?? 'none' 
                    );
                    
                    $payload = [
                        'selector' => $rule['selector'],
                        'position' => $rule['position'],
                        'code'     => $aligned_code,
                    ];
                    
                    /**
                     * Filtra il payload prima dell'iniezione
                     * 
                     * @param array $payload Array con selector, position e code
                     * @param array $rule La regola completa
                     * @param string $rule_id ID della regola
                     */
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- sdi_ is the plugin prefix.
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
            
            wp_register_script( 'sdi-runtime', false, [], '2.5.2', true );
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
        // Ottieni il codice della variante attiva
        $variant_code = $this->get_active_variant_code( $rule );
        
        // Se non c'è codice, ritorna il contenuto originale
        if ( empty( $variant_code ) ) {
            return $content;
        }
        
        // Applica l'allineamento al codice
        $code = $this->apply_alignment( 
            $variant_code, 
            $rule['alignment'] ?? 'none' 
        );
        
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
      
      // Inserisci gli elementi in modo sequenziale, aspettando gli script esterni
      insertSequentially(target, elements, where, 0);
    }
    
    function insertSequentially(target, elements, where, index) {
      if (index >= elements.length) return; // Fine della sequenza
      
      var el = elements[index];
      var isExternalScript = el.nodeType === 1 && el.tagName === 'SCRIPT' && el.hasAttribute('src');
      
      // Funzione per continuare con il prossimo elemento
      var continueNext = function() {
        insertSequentially(target, elements, where, index + 1);
      };
      
      try {
        var newElement;
        
        if (el.nodeType === 1) {
          newElement = cloneAndExecute(el);
        } else {
          newElement = el.cloneNode(true);
        }
        
        // Inserisci l'elemento nella posizione corretta
        switch(where){
          case 'prepend':
            target.insertBefore(newElement, target.firstChild);
            break;
          case 'before':
            target.parentNode.insertBefore(newElement, target);
                break;
          case 'after':
            target.parentNode.insertBefore(newElement, target.nextSibling);
                break;
          case 'replace':
            if (index === 0) target.innerHTML = '';
            target.appendChild(newElement);
                break;
          case 'append':
          default:
            target.appendChild(newElement);
        }
        
        // Se è uno script esterno, aspetta che sia caricato
        if (isExternalScript) {
          newElement.onload = continueNext;
          newElement.onerror = function() {
            console.warn('Smart Div Injector: Errore nel caricamento dello script:', el.getAttribute('src'));
            continueNext();
          };
        } else {
          // Altrimenti continua subito (ma usa setTimeout per evitare problemi di stack)
          setTimeout(continueNext, 0);
        }
      } catch(e) {
        console.warn('Smart Div Injector: Errore nell\'inserimento dell\'elemento', e);
        continueNext();
      }
    }
    
    function cloneAndExecute(element) {
      // Se è uno script, crea una copia eseguibile
      if (element.nodeType === 1 && element.tagName === 'SCRIPT') {
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
          
          // Decodifica il codice da base64 con gestione UTF-8
          var decodedCode;
          try {
            decodedCode = decodeURIComponent(atob(rule.code).split('').map(function(c) {
              return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
          } catch(decodeError) {
            // Fallback: decodifica base64 semplice se UTF-8 fallisce
            console.warn('Smart Div Injector: Errore decodifica UTF-8, uso fallback per regola #' + (index + 1));
            try {
              decodedCode = atob(rule.code);
            } catch(base64Error) {
              console.error('Smart Div Injector: Impossibile decodificare la regola #' + (index + 1), base64Error);
              return;
            }
          }
          
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
