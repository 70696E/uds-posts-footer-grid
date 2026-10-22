<?php
/**
 * Plugin Name: UdS Posts Footer Grid
 * Description: Inserisce una griglia di suggerimenti al fondo degli articoli del blog, configurabile per categoria.
 * Version:     1.4.0
 * Author:      UdS
 * License:     GPL-2.0-or-later
 * Text Domain: uds-posts-footer-grid
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// CONFIGURAZIONE
// Modifica questi valori per personalizzare il comportamento
// del plugin senza toccare la logica.
// ============================================================

define( 'UDS_PFG_CONFIG', [

    // Versione — aggiornare ad ogni release per invalidare cache CSS/JS
    'version'         => '1.4.0',

    // Capability o ruoli autorizzati ad accedere alla pagina di amministrazione.
    // Accetta una stringa singola o un array di capability e/o nomi di ruolo.
    // Esempi:
    //   'manage_options'                          → solo amministratori
    //   'edit_posts'                              → editor e autori standard
    //   ['manage_options', 'uds_blog_author']     → admin + ruolo personalizzato
    'capability'      => ['manage_options', 'uds_blog_author'],

    // Posizione del menu di amministrazione.
    // Valori possibili:
    //   ''                    → voce di primo livello
    //   'options-general.php' → sotto Impostazioni
    //   'themes.php'          → sotto Aspetto
    //   'tools.php'           → sotto Strumenti
    //   'edit.php'              sotto Articoli
    'menu_parent'     => 'edit.php',

    // Post type su cui mostrare la griglia.
    // Aggiungere altri slug se in futuro si vuole estendere.
    'post_types'      => [ 'post' ],

    // Testo sopra la griglia nel frontend.
    // Stringa vuota per nascondere il titolo.
    'titolo_sezione'  => 'Ti potrebbero interessare anche',

    // Chiavi delle opzioni nel database (wp_options)
    'opt_groups'      => 'uds_pfg_groups',
    'opt_map'         => 'uds_pfg_cat_map',
    'opt_overrides'   => 'uds_pfg_overrides',

] );

// ============================================================
// CLASSE PRINCIPALE
// ============================================================

class UDS_Posts_Footer_Grid {

    // Capability virtuale usata internamente per il menu WP.
    // L'accesso reale è gestito da grant_admin_cap() tramite user_has_cap.
    private const ADMIN_CAP = 'uds_pfg_manage';

    private array $config;

    public function __construct( array $config ) {
        $this->config = $config;
    }

    // ----------------------------------------------------------
    // BOOTSTRAP — registra tutti gli hook
    // ----------------------------------------------------------

    public function init() {
        add_filter( 'user_has_cap',           [ $this, 'grant_admin_cap' ], 10, 3 );
        add_action( 'admin_menu',             [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_init',             [ $this, 'handle_save' ] );
        add_action( 'wp_ajax_uds_pfg_cerca',  [ $this, 'ajax_cerca' ] );
        add_filter( 'the_content',            [ $this, 'append_grid' ], 20 );
        add_action( 'wp_enqueue_scripts',     [ $this, 'enqueue_frontend_assets' ] );
    }

    // ----------------------------------------------------------
    // ACCESSO — concede ADMIN_CAP agli utenti autorizzati
    // ----------------------------------------------------------

    public function grant_admin_cap( array $allcaps, array $caps, array $args ): array {
        if ( ! in_array( self::ADMIN_CAP, $caps, true ) ) return $allcaps;

        $allowed = (array) $this->config['capability'];
        $user    = isset( $args[1] ) ? get_userdata( $args[1] ) : false;

        foreach ( $allowed as $cap_or_role ) {
            // Controlla come capability diretta
            if ( ! empty( $allcaps[ $cap_or_role ] ) ) {
                $allcaps[ self::ADMIN_CAP ] = true;
                return $allcaps;
            }
            // Controlla come nome di ruolo
            if ( $user && in_array( $cap_or_role, (array) $user->roles, true ) ) {
                $allcaps[ self::ADMIN_CAP ] = true;
                return $allcaps;
            }
        }

        return $allcaps;
    }

    // ----------------------------------------------------------
    // MENU DI AMMINISTRAZIONE
    // ----------------------------------------------------------

    public function register_admin_menu() {
        $parent = $this->config['menu_parent'];

        if ( $parent === '' ) {
            add_menu_page(
                'UdS Posts Footer Grid',
                'Footer Grid',
                self::ADMIN_CAP,
                'uds-pfg',
                [ $this, 'render_admin_page' ],
                'dashicons-grid-view',
                80
            );
        } else {
            add_submenu_page(
                $parent,
                'UdS Posts Footer Grid',
                'Footer Grid',
                self::ADMIN_CAP,
                'uds-pfg',
                [ $this, 'render_admin_page' ]
            );
        }
    }

    // ----------------------------------------------------------
    // ASSET DI AMMINISTRAZIONE
    // ----------------------------------------------------------

    public function enqueue_admin_assets( string $hook ) {
        $expected = $this->config['menu_parent'] === ''
            ? 'toplevel_page_uds-pfg'
            : null;

        // Verifica hook per entrambe le posizioni di menu
        $is_our_page = ( $expected && $hook === $expected )
            || str_contains( $hook, 'uds-pfg' );

        if ( ! $is_our_page ) return;

        wp_enqueue_media();

        wp_enqueue_script(
            'uds-pfg-admin',
            plugin_dir_url( __FILE__ ) . 'admin.js',
            [ 'media-upload' ],
            $this->versione_file( 'admin.js' ),
            true
        );
        wp_localize_script( 'uds-pfg-admin', 'udsPfg', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'uds_pfg_cerca' ),
        ] );

        wp_enqueue_style(
            'uds-pfg-admin',
            plugin_dir_url( __FILE__ ) . 'admin.css',
            [],
            $this->versione_file( 'admin.css' )
        );
    }

    /**
     * Versione di un file CSS o JS nell'indirizzo: quella del plugin più la data
     * del file, così un aggiornamento arriva al browser anche senza cambiare
     * numero di versione (senza, la copia in cache restava: visto il 22/10).
     */
    private function versione_file( string $file ): string {
        $data = @filemtime( plugin_dir_path( __FILE__ ) . $file );
        return $this->config['version'] . ( $data ? '.' . $data : '' );
    }

    // ----------------------------------------------------------
    // SALVATAGGIO DATI
    // ----------------------------------------------------------

    public function handle_save() {
        if ( ! isset( $_POST['uds_pfg_action'] ) ) return;
        if ( ! current_user_can( self::ADMIN_CAP ) ) return;
        if ( ! check_admin_referer( 'uds_pfg_save' ) ) return;

        $action = sanitize_key( $_POST['uds_pfg_action'] );

        match ( $action ) {
            'save_groups'    => $this->save_groups(),
            'save_map'       => $this->save_map(),
            'save_overrides' => $this->save_overrides(),
            'save_settings'  => $this->save_settings(),
            default          => null,
        };
    }

    private function save_groups() {
        $groups = [];
        $raw    = wp_unslash( $_POST['groups'] ?? [] );

        foreach ( $raw as $g ) {
            $group = [
                'id'           => sanitize_key( $g['id'] ),
                'nome'         => sanitize_text_field( $g['nome'] ),
                'titolo'       => sanitize_text_field( $g['titolo'] ?? '' ),
                'utm_campaign' => sanitize_text_field( $g['utm_campaign'] ?? '' ),
                'default'      => isset( $g['default'] ) ? 1 : 0,
                'cards'        => [],
            ];
            foreach ( $g['cards'] ?? [] as $c ) {
                $card = [
                    'immagine_id'  => absint( $c['immagine_id'] ),
                    'immagine_url' => esc_url_raw( $c['immagine_url'] ),
                    'titolo'       => sanitize_text_field( $c['titolo'] ),
                    'descrizione'  => sanitize_textarea_field( $c['descrizione'] ),
                    'link'         => esc_url_raw( $c['link'] ),
                    'testo_btn'    => sanitize_text_field( $c['testo_btn'] ?? '' ),
                    'aggancio'     => $this->pulisci_aggancio( (string) ( $c['aggancio'] ?? '' ) ),
                ];
                // Il link scritto resta come riserva, aggiornato a ogni salvataggio.
                $card['link'] = $this->link_card( $card );
                $group['cards'][] = $card;
            }
            $groups[] = $group;
        }

        // Un solo gruppo può essere default
        $found_default = false;
        foreach ( $groups as &$g ) {
            if ( $g['default'] && ! $found_default ) {
                $found_default = true;
            } elseif ( $g['default'] ) {
                $g['default'] = 0;
            }
        }
        unset( $g );

        update_option( $this->config['opt_groups'], $groups );
        $this->redirect_saved( 'groups' );
    }

    private function save_map() {
        $map = [];
        foreach ( $_POST['cat_map'] ?? [] as $entry ) {
            if ( empty( $entry['slug'] ) || empty( $entry['group_id'] ) ) continue;
            $map[] = [
                'slug'     => sanitize_key( $entry['slug'] ),
                'group_id' => sanitize_key( $entry['group_id'] ),
                'peso'     => absint( $entry['peso'] ),
            ];
        }
        usort( $map, fn( $a, $b ) => $a['peso'] - $b['peso'] );

        update_option( $this->config['opt_map'], $map );
        $this->redirect_saved( 'map' );
    }

    private function save_overrides() {
        $overrides = [];
        foreach ( $_POST['overrides'] ?? [] as $o ) {
            if ( empty( $o['post_id'] ) || empty( $o['group_id'] ) ) continue;
            $overrides[] = [
                'post_id'  => absint( $o['post_id'] ),
                'group_id' => sanitize_key( $o['group_id'] ),
            ];
        }
        update_option( $this->config['opt_overrides'], $overrides );
        $this->redirect_saved( 'overrides' );
    }

    private function save_settings() {
        $settings = [
            'titolo_sezione' => sanitize_text_field( wp_unslash( $_POST['titolo_sezione'] ?? '' ) ),
            'utm_enabled'    => ! empty( $_POST['utm_enabled'] ) ? 1 : 0,
        ];
        update_option( 'uds_pfg_settings', $settings );
        $this->redirect_saved( 'settings' );
    }

    private function get_titolo(): string {
        $settings = get_option( 'uds_pfg_settings', [] );
        // Priorità: DB → UDS_PFG_CONFIG → stringa vuota
        return $settings['titolo_sezione'] ?? $this->config['titolo_sezione'];
    }

    private function redirect_saved( string $tab ) {
        wp_redirect( add_query_arg( [
            'page'  => 'uds-pfg',
            'tab'   => $tab,
            'saved' => '1',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ----------------------------------------------------------
    // PAGINA DI AMMINISTRAZIONE
    // ----------------------------------------------------------

    public function render_admin_page() {
        $tab       = sanitize_key( $_GET['tab'] ?? 'groups' );
        $saved     = ( $_GET['saved'] ?? '' ) === '1';
        $groups    = get_option( $this->config['opt_groups'], [] );
        $map       = get_option( $this->config['opt_map'], [] );
        $overrides = get_option( $this->config['opt_overrides'], [] );
        ?>
        <div class="wrap uds-pfg-wrap">
            <h1>UdS Posts Footer Grid <span class="uds-pfg-versione">v<?php echo esc_html( $this->config['version'] ); ?></span></h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p>Impostazioni salvate.</p></div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <?php foreach ( [
                    'groups'   => 'Gruppi di card',
                    'map'      => 'Categorie',
                    'overrides'=> 'Override post',
                    'settings' => 'Impostazioni',
                ] as $key => $label ) : ?>
                    <a href="?page=uds-pfg&tab=<?php echo $key; ?>"
                       class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="uds-pfg-tab-content">
                <?php
                match ( $tab ) {
                    'groups'   => $this->render_tab_groups( $groups ),
                    'map'      => $this->render_tab_map( $groups, $map ),
                    'overrides'=> $this->render_tab_overrides( $groups, $overrides ),
                    'settings' => $this->render_tab_settings(),
                    default    => $this->render_tab_groups( $groups ),
                };
                ?>
            </div>
        </div>
        <?php
    }

    // ----------------------------------------------------------
    // TAB 1 — GRUPPI DI CARD
    // ----------------------------------------------------------

    private function render_tab_groups( array $groups ) {
        $empty_group = [ 'id' => '', 'nome' => '', 'default' => 0, 'cards' => [] ];
        $empty_card  = [ 'immagine_id' => '', 'immagine_url' => '', 'titolo' => '', 'descrizione' => '', 'link' => '', 'aggancio' => '' ];

        // I link da controllare, card per card, e il riepilogo per gruppo.
        $avvisi    = [];
        $riepilogo = [];
        foreach ( $groups as $gi => $group ) {
            foreach ( $group['cards'] as $ci => $card ) {
                $trovati = $this->avvisi_card( $card );
                if ( $trovati ) {
                    $avvisi[ $gi ][ $ci ] = $trovati;
                    $nome = $group['nome'] ?: 'Senza nome';
                    $riepilogo[ $nome ] = ( $riepilogo[ $nome ] ?? 0 ) + 1;
                }
            }
        }
        ?>
        <?php if ( $riepilogo ) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong><?php echo esc_html( sprintf( 'Card con il link da controllare: %d.', array_sum( $riepilogo ) ) ); ?></strong>
                    <?php
                    echo esc_html( implode( ', ', array_map(
                        fn( $nome, $n ) => "$nome ($n)",
                        array_keys( $riepilogo ),
                        $riepilogo
                    ) ) );
                    ?>
                    — il motivo è scritto sotto il link di ogni card.
                </p>
            </div>
        <?php endif; ?>
        <form method="post" id="uds-pfg-groups-form">
            <?php wp_nonce_field( 'uds_pfg_save' ); ?>
            <input type="hidden" name="uds_pfg_action" value="save_groups">

            <?php if ( $groups ) : ?>
                <p class="uds-pfg-comandi-lista">
                    <button type="button" class="button button-small uds-pfg-chiudi-tutti">Chiudi tutti</button>
                    <button type="button" class="button button-small uds-pfg-apri-tutti">Apri tutti</button>
                </p>
            <?php endif; ?>

            <div id="uds-pfg-groups-list">
                <?php foreach ( $groups as $gi => $group ) : ?>
                    <?php $this->render_group( $gi, $group, $avvisi[ $gi ] ?? [] ); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button" id="uds-pfg-add-group">+ Aggiungi gruppo</button>
            <br><br>
            <?php submit_button( 'Salva gruppi' ); ?>
        </form>

        <template id="tmpl-uds-pfg-group">
            <?php $this->render_group( '__GI__', $empty_group ); ?>
        </template>
        <template id="tmpl-uds-pfg-card">
            <?php $this->render_card( '__GI__', '__CI__', $empty_card ); ?>
        </template>
        <?php
    }

    private function render_group( $gi, array $group, array $avvisi = [] ) {
        // Per i template (id vuoto) usa il placeholder __ID__, sostituito via JS con un ID univoco.
        $group_id = $group['id'] ?: '__ID__';
        ?>
        <div class="uds-pfg-group" data-gi="<?php echo esc_attr( $gi ); ?>">
            <div class="uds-pfg-group-header">
                <span class="uds-pfg-drag-handle">☰</span>
                <strong class="uds-pfg-group-label"><?php echo esc_html( $group['nome'] ?: 'Nuovo gruppo' ); ?></strong>
                <?php if ( $avvisi ) : ?>
                    <span class="uds-pfg-avviso"><?php echo esc_html( sprintf( '⚠ link da controllare: %d', count( $avvisi ) ) ); ?></span>
                <?php endif; ?>
                <span class="uds-pfg-group-comandi">
                    <button type="button" class="button button-small uds-pfg-chiudi-tutti">Chiudi tutti</button>
                    <button type="button" class="button button-small uds-pfg-solo-questo">Apri solo questo</button>
                </span>
                <button type="button" class="button-link uds-pfg-toggle-group" aria-expanded="true" title="Apri / chiudi">▼</button>
                <button type="button" class="button-link-delete uds-pfg-remove-group">Elimina</button>
            </div>
            <div class="uds-pfg-group-body">
                <input type="hidden" name="groups[<?php echo $gi; ?>][id]" value="<?php echo esc_attr( $group_id ); ?>">
                <table class="form-table">
                    <tr>
                        <th>Nome gruppo</th>
                        <td>
                            <input type="text" name="groups[<?php echo $gi; ?>][nome]"
                                   value="<?php echo esc_attr( $group['nome'] ); ?>"
                                   class="regular-text uds-pfg-group-nome">
                        </td>
                    </tr>
                    <tr>
                        <th>Titolo sezione</th>
                        <td>
                            <input type="text" name="groups[<?php echo $gi; ?>][titolo]"
                                   value="<?php echo esc_attr( $group['titolo'] ?? '' ); ?>"
                                   class="large-text"
                                   placeholder="Lascia vuoto per usare il titolo comune">
                            <p class="description">
                                Se compilato sovrascrive il titolo comune per questo gruppo.
                                Se vuoto usa il titolo impostato in <em>Impostazioni</em>;
                                se anche quello è vuoto, nessun titolo viene mostrato.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>UTM Campaign</th>
                        <td>
                            <input type="text" name="groups[<?php echo $gi; ?>][utm_campaign]"
                                   value="<?php echo esc_attr( $group['utm_campaign'] ?? '' ); ?>"
                                   class="regular-text"
                                   placeholder="Lascia vuoto per usare il nome del gruppo">
                            <p class="description">
                                Sovrascrive <code>utm_campaign</code> per questo gruppo.
                                Utile se usi nomi campagna specifici in Google Analytics.
                                Attivo solo se i parametri UTM sono abilitati in <em>Impostazioni</em>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Gruppo default</th>
                        <td>
                            <label>
                                <input type="checkbox" name="groups[<?php echo $gi; ?>][default]" value="1"
                                       <?php checked( $group['default'], 1 ); ?>>
                                Usato quando nessuna categoria corrisponde
                            </label>
                        </td>
                    </tr>
                </table>
                <h4>Card del gruppo</h4>
                <div class="uds-pfg-cards-list">
                    <?php foreach ( $group['cards'] as $ci => $card ) : ?>
                        <?php $this->render_card( $gi, $ci, $card, $avvisi[ $ci ] ?? [] ); ?>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button uds-pfg-add-card" data-gi="<?php echo esc_attr( $gi ); ?>">
                    + Aggiungi card
                </button>
            </div>
        </div>
        <?php
    }

    private function render_card( $gi, $ci, array $card, array $avvisi = [] ) {
        $testo_btn = $card['testo_btn'] ?? '';
        ?>
        <div class="uds-pfg-card" data-ci="<?php echo esc_attr( $ci ); ?>">
            <div class="uds-pfg-card-header">
                <span class="uds-pfg-drag-handle">☰</span>
                <span class="uds-pfg-card-label"><?php echo esc_html( $card['titolo'] ?: 'Nuova card' ); ?></span>
                <?php if ( $avvisi ) : ?>
                    <span class="uds-pfg-avviso">⚠ link da controllare</span>
                <?php endif; ?>
                <button type="button" class="button-link-delete uds-pfg-remove-card">Rimuovi</button>
            </div>
            <div class="uds-pfg-card-body">
                <table class="form-table">
                    <tr class="uds-pfg-riga-ricerca">
                        <th>Riempi da</th>
                        <td>
                            <div class="uds-pfg-ricerca">
                                <select class="uds-pfg-fonte" aria-label="Dove cercare">
                                    <?php foreach ( $this->fonti_ricerca() as $valore => $etichetta ) : ?>
                                        <option value="<?php echo esc_attr( $valore ); ?>"><?php echo esc_html( $etichetta ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="search" class="uds-pfg-cerca regular-text"
                                       placeholder="Una parola del titolo (vuoto: gli ultimi)"
                                       aria-label="Parola da cercare">
                                <button type="button" class="button uds-pfg-cerca-btn">Cerca</button>
                            </div>
                            <div class="uds-pfg-risultati" aria-live="polite"></div>
                            <p class="description">
                                Un clic su un risultato riempie titolo, descrizione, link e immagine
                                (poi si possono ritoccare) e aggancia la card a quel contenuto.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Immagine</th>
                        <td>
                            <div class="uds-pfg-image-preview">
                                <?php if ( $card['immagine_url'] ) : ?>
                                    <img src="<?php echo esc_url( $card['immagine_url'] ); ?>">
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="groups[<?php echo $gi; ?>][cards][<?php echo $ci; ?>][immagine_id]"
                                   class="uds-pfg-img-id" value="<?php echo esc_attr( $card['immagine_id'] ); ?>">
                            <input type="hidden" name="groups[<?php echo $gi; ?>][cards][<?php echo $ci; ?>][immagine_url]"
                                   class="uds-pfg-img-url" value="<?php echo esc_url( $card['immagine_url'] ); ?>">
                            <button type="button" class="button uds-pfg-select-image">Scegli immagine</button>
                            <button type="button" class="button uds-pfg-remove-image"
                                    <?php echo empty( $card['immagine_url'] ) ? 'style="display:none"' : ''; ?>>
                                Rimuovi
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th>Titolo</th>
                        <td>
                            <input type="text" name="groups[<?php echo $gi; ?>][cards][<?php echo $ci; ?>][titolo]"
                                   value="<?php echo esc_attr( $card['titolo'] ); ?>"
                                   class="regular-text uds-pfg-card-titolo">
                        </td>
                    </tr>
                    <tr>
                        <th>Descrizione</th>
                        <td>
                            <textarea name="groups[<?php echo $gi; ?>][cards][<?php echo $ci; ?>][descrizione]"
                                      rows="2" class="large-text uds-pfg-card-desc"><?php echo esc_textarea( $card['descrizione'] ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Link</th>
                        <td>
                            <input type="text" name="groups[<?php echo $gi; ?>][cards][<?php echo $ci; ?>][link]"
                                   value="<?php echo esc_url( $this->link_card( $card ) ); ?>"
                                   class="large-text uds-pfg-link"
                                   placeholder="https://... oppure /percorso/relativo">
                            <input type="hidden" name="groups[<?php echo $gi; ?>][cards][<?php echo $ci; ?>][aggancio]"
                                   class="uds-pfg-aggancio" value="<?php echo esc_attr( $card['aggancio'] ?? '' ); ?>">
                            <?php $nome_aggancio = $this->nome_aggancio( (string) ( $card['aggancio'] ?? '' ) ); ?>
                            <p class="uds-pfg-aggancio-info" <?php echo $nome_aggancio === '' ? 'hidden' : ''; ?>>
                                🔗 Agganciata a <strong class="uds-pfg-aggancio-nome"><?php echo esc_html( $nome_aggancio ); ?></strong>:
                                il link si aggiorna da solo se cambia indirizzo.
                                <button type="button" class="button-link uds-pfg-sgancia">Sgancia</button>
                            </p>
                            <?php if ( $avvisi ) : ?>
                                <ul class="uds-pfg-avvisi">
                                    <?php foreach ( $avvisi as $avviso ) : ?>
                                        <li>⚠ <?php echo esc_html( $avviso ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Testo bottone</th>
                        <td>
                            <input type="text" name="groups[<?php echo $gi; ?>][cards][<?php echo $ci; ?>][testo_btn]"
                                   value="<?php echo esc_attr( $testo_btn ); ?>"
                                   class="regular-text"
                                   placeholder="es. Scopri il corso">
                            <p class="description">
                                Se compilato, mostra un bottone con questo testo e il link vale solo per il bottone.
                                Se vuoto, l'intera card è cliccabile (nessun bottone visibile).
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    // ----------------------------------------------------------
    // TAB 4 — IMPOSTAZIONI GENERALI
    // ----------------------------------------------------------

    private function render_tab_settings() {
        $titolo      = $this->get_titolo();
        $settings    = get_option( 'uds_pfg_settings', [] );
        $utm_enabled = ! empty( $settings['utm_enabled'] );
        $utm_source  = parse_url( home_url(), PHP_URL_HOST );
        ?>
        <form method="post">
            <?php wp_nonce_field( 'uds_pfg_save' ); ?>
            <input type="hidden" name="uds_pfg_action" value="save_settings">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="uds-pfg-titolo">Titolo sezione</label></th>
                    <td>
                        <input type="text" id="uds-pfg-titolo" name="titolo_sezione"
                               value="<?php echo esc_attr( $titolo ); ?>"
                               class="large-text"
                               placeholder="es. Ti potrebbero interessare anche">
                        <p class="description">
                            Testo visualizzato sopra la griglia nel frontend.
                            Lascia vuoto per nascondere il titolo.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Parametri UTM</th>
                    <td>
                        <label>
                            <input type="checkbox" name="utm_enabled" value="1"
                                   <?php checked( $utm_enabled ); ?>>
                            Attiva parametri UTM nei link delle card
                        </label>
                        <p class="description">
                            Se attivato, aggiunge automaticamente a ogni link:<br>
                            <code>utm_source=<?php echo esc_html( $utm_source ); ?></code>
                            &nbsp;<code>utm_medium=footer_grid</code>
                            &nbsp;<code>utm_campaign=[nome gruppo]</code>
                            &nbsp;<code>utm_content=[titolo card]</code><br>
                            Il campo <em>UTM Campaign</em> di ogni gruppo permette di
                            sovrascrivere il nome campagna per quel gruppo specifico.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Salva impostazioni' ); ?>
        </form>
        <?php
    }

    // ----------------------------------------------------------
    // TAB 2 — MAPPA CATEGORIE
    // ----------------------------------------------------------

    private function render_tab_map( array $groups, array $map ) {
        $categories = get_categories( [ 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ] );
        $map_index  = array_column( $map, null, 'slug' );
        ?>
        <form method="post">
            <?php wp_nonce_field( 'uds_pfg_save' ); ?>
            <input type="hidden" name="uds_pfg_action" value="save_map">
            <p>
                Il campo <strong>Peso</strong> determina la priorità: se un articolo ha più categorie,
                vale la prima corrispondenza trovata in ordine di peso crescente.
            </p>
            <table class="widefat fixed striped uds-pfg-map-table">
                <thead>
                    <tr>
                        <th style="width:80px">Peso</th>
                        <th>Categoria</th>
                        <th>Slug</th>
                        <th>Gruppo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $categories as $i => $cat ) :
                        $saved = $map_index[ $cat->slug ] ?? [ 'group_id' => '', 'peso' => ( $i + 1 ) * 10 ];
                        ?>
                        <tr>
                            <td>
                                <input type="number" name="cat_map[<?php echo $i; ?>][peso]"
                                       value="<?php echo esc_attr( $saved['peso'] ); ?>"
                                       style="width:60px" min="0">
                                <input type="hidden" name="cat_map[<?php echo $i; ?>][slug]"
                                       value="<?php echo esc_attr( $cat->slug ); ?>">
                            </td>
                            <td><?php echo esc_html( $cat->name ); ?></td>
                            <td><code><?php echo esc_html( $cat->slug ); ?></code></td>
                            <td>
                                <select name="cat_map[<?php echo $i; ?>][group_id]">
                                    <option value="">— nessuno (usa default) —</option>
                                    <?php foreach ( $groups as $g ) : ?>
                                        <option value="<?php echo esc_attr( $g['id'] ); ?>"
                                                <?php selected( $saved['group_id'], $g['id'] ); ?>>
                                            <?php echo esc_html( $g['nome'] ); ?>
                                            <?php echo $g['default'] ? ' (default)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <?php submit_button( 'Salva mappa categorie' ); ?>
        </form>
        <?php
    }

    // ----------------------------------------------------------
    // TAB 3 — OVERRIDE PER POST ID
    // ----------------------------------------------------------

    private function render_tab_overrides( array $groups, array $overrides ) {
        ?>
        <form method="post">
            <?php wp_nonce_field( 'uds_pfg_save' ); ?>
            <input type="hidden" name="uds_pfg_action" value="save_overrides">
            <p>Assegna un gruppo specifico a un singolo articolo. Ha priorità assoluta sulla categoria.</p>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:130px">Post ID</th>
                        <th>Titolo post</th>
                        <th>Gruppo</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody id="uds-pfg-overrides-body">
                    <?php foreach ( $overrides as $oi => $ov ) : ?>
                        <tr class="uds-pfg-override-row">
                            <td>
                                <input type="number" name="overrides[<?php echo $oi; ?>][post_id]"
                                       value="<?php echo esc_attr( $ov['post_id'] ); ?>"
                                       style="width:100px" min="1">
                            </td>
                            <td><?php echo esc_html( get_the_title( $ov['post_id'] ) ?: '—' ); ?></td>
                            <td>
                                <select name="overrides[<?php echo $oi; ?>][group_id]">
                                    <?php foreach ( $groups as $g ) : ?>
                                        <option value="<?php echo esc_attr( $g['id'] ); ?>"
                                                <?php selected( $ov['group_id'], $g['id'] ); ?>>
                                            <?php echo esc_html( $g['nome'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="button-link-delete uds-pfg-remove-override">Rimuovi</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <button type="button" class="button" id="uds-pfg-add-override">+ Aggiungi override</button>

            <template id="tmpl-override-row">
                <tr class="uds-pfg-override-row">
                    <td><input type="number" name="overrides[__OI__][post_id]" style="width:100px" min="1"></td>
                    <td>—</td>
                    <td>
                        <select name="overrides[__OI__][group_id]">
                            <?php foreach ( $groups as $g ) : ?>
                                <option value="<?php echo esc_attr( $g['id'] ); ?>">
                                    <?php echo esc_html( $g['nome'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><button type="button" class="button-link-delete uds-pfg-remove-override">Rimuovi</button></td>
                </tr>
            </template>

            <br><br>
            <?php submit_button( 'Salva override' ); ?>
        </form>
        <?php
    }

    // ----------------------------------------------------------
    // AGGANCIO — la card legata a un contenuto o a una categoria
    // ----------------------------------------------------------

    /*
     * Una card riempita dalla ricerca ricorda a che cosa è agganciata:
     * "post:123" (pagina, articolo, corso: qualunque tipo di contenuto) o
     * "term:tassonomia:45" (una categoria). Il link si ricalcola ogni volta
     * da lì, così se il contenuto cambia indirizzo la card lo segue (il
     * 22/10 su www sette card puntavano a corsi rinominati). Titolo, testo
     * e immagine invece restano quelli salvati: si possono ritoccare.
     * Il link scritto resta come riserva, se il contenuto sparisce.
     * I numeri sono quelli del sito: non valgono su un altro sito.
     */

    private function pulisci_aggancio( string $aggancio ): string {
        $aggancio = trim( $aggancio );
        return preg_match( '/^(post:\d+|term:[a-z0-9_-]+:\d+)$/', $aggancio ) ? $aggancio : '';
    }

    /** L'oggetto agganciato, se c'è ancora: WP_Post, WP_Term o null. */
    private function oggetto_aggancio( string $aggancio ): \WP_Post|\WP_Term|null {
        $parti = explode( ':', $this->pulisci_aggancio( $aggancio ) );
        if ( $parti[0] === 'post' ) {
            $post = get_post( (int) $parti[1] );
            return ( $post && $post->post_status !== 'trash' ) ? $post : null;
        }
        if ( $parti[0] === 'term' && taxonomy_exists( $parti[1] ) ) {
            $termine = get_term( (int) $parti[2], $parti[1] );
            return $termine instanceof \WP_Term ? $termine : null;
        }
        return null;
    }

    /** Il link da usare: quello dell'oggetto agganciato, o quello scritto. */
    private function link_card( array $card ): string {
        $oggetto = $this->oggetto_aggancio( (string) ( $card['aggancio'] ?? '' ) );
        if ( $oggetto instanceof \WP_Post ) {
            $link = get_permalink( $oggetto );
            if ( $link ) return $link;
        } elseif ( $oggetto instanceof \WP_Term ) {
            $link = get_term_link( $oggetto );
            if ( ! is_wp_error( $link ) ) return $link;
        }
        return trim( (string) ( $card['link'] ?? '' ) );
    }

    /** "Corso «Titolo»", per la riga sotto il link; vuoto se non agganciata. */
    private function nome_aggancio( string $aggancio ): string {
        if ( $this->pulisci_aggancio( $aggancio ) === '' ) return '';
        $oggetto = $this->oggetto_aggancio( $aggancio );
        if ( $oggetto instanceof \WP_Post ) {
            $tipo = get_post_type_object( $oggetto->post_type );
            return sprintf( '%s «%s»', $tipo ? $tipo->labels->singular_name : $oggetto->post_type, $this->testo( get_the_title( $oggetto ) ) );
        }
        if ( $oggetto instanceof \WP_Term ) {
            $tassonomia = get_taxonomy( $oggetto->taxonomy );
            return sprintf( '%s «%s»', $tassonomia ? $tassonomia->labels->singular_name : $oggetto->taxonomy, $this->testo( $oggetto->name ) );
        }
        return 'un contenuto che non c\'è più';
    }

    /** Gli avvisi di una card: quelli del link, più l'aggancio perso. */
    private function avvisi_card( array $card ): array {
        $avvisi   = [];
        $aggancio = $this->pulisci_aggancio( (string) ( $card['aggancio'] ?? '' ) );
        if ( $aggancio !== '' && ! $this->oggetto_aggancio( $aggancio ) ) {
            $avvisi[] = 'il contenuto a cui era agganciata non c\'è più: vale il link scritto';
        }
        return array_merge( $avvisi, $this->avvisi_link( $this->link_card( $card ) ) );
    }

    /** Testo semplice da un titolo di WordPress (che può avere entità e tag). */
    private function testo( string $html ): string {
        return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
    }

    // ----------------------------------------------------------
    // RICERCA — "Riempi da…" nelle card
    // ----------------------------------------------------------

    /**
     * Dove si può cercare: i tipi di contenuto pubblici (pagine, articoli e,
     * dove ci sono, corsi e pacchetti di Tutor) e le loro categorie.
     * Valori: il nome del tipo, o "tax:nome della tassonomia".
     * Filtro uds_pfg_fonti_ricerca per toglierne o aggiungerne.
     */
    private function fonti_ricerca(): array {
        $fonti = [];
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $tipo ) {
            if ( $tipo->name === 'attachment' ) continue;
            $fonti[ $tipo->name ] = $tipo->labels->name;
        }
        foreach ( get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'objects' ) as $tassonomia ) {
            $fonti[ 'tax:' . $tassonomia->name ] = $tassonomia->labels->name;
        }
        // Le pagine per prime: sono la scelta più comune.
        if ( isset( $fonti['page'] ) ) {
            $fonti = [ 'page' => $fonti['page'] ] + $fonti;
        }
        return (array) apply_filters( 'uds_pfg_fonti_ricerca', $fonti );
    }

    /**
     * La ricerca, chiamata dallo script della pagina: fino a 20 risultati,
     * pubblicati o privati (lo stato si vede), con quello che serve a
     * riempire la card. Altri plugin aggiungono note a ogni risultato col
     * filtro uds_pfg_risultato_ricerca (array $risultato, WP_Post|WP_Term).
     */
    public function ajax_cerca() {
        check_ajax_referer( 'uds_pfg_cerca' );
        if ( ! current_user_can( self::ADMIN_CAP ) ) {
            wp_send_json_error( 'Permesso negato.', 403 );
        }

        $fonte  = sanitize_text_field( wp_unslash( $_POST['fonte'] ?? '' ) );
        $parola = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
        if ( ! array_key_exists( $fonte, $this->fonti_ricerca() ) ) {
            wp_send_json_error( 'Origine della ricerca sconosciuta.', 400 );
        }

        $risultati = [];

        if ( str_starts_with( $fonte, 'tax:' ) ) {
            $tassonomia = substr( $fonte, 4 );
            $termini    = get_terms( [
                'taxonomy'   => $tassonomia,
                'hide_empty' => false,
                'search'     => $parola,
                'number'     => 20,
                'orderby'    => 'name',
            ] );
            foreach ( is_wp_error( $termini ) ? [] : $termini as $termine ) {
                $link = get_term_link( $termine );
                if ( is_wp_error( $link ) ) continue;
                $immagine = (int) get_term_meta( $termine->term_id, 'thumbnail_id', true );
                $risultati[] = $this->risultato( $termine, [
                    'aggancio'    => 'term:' . $tassonomia . ':' . $termine->term_id,
                    'titolo'      => $this->testo( $termine->name ),
                    'link'        => $link,
                    'descrizione' => wp_trim_words( $this->testo( $termine->description ), 25 ),
                    'immagine_id' => $immagine,
                    'stato'       => '',
                    'conteggio'   => (int) $termine->count,
                ] );
            }
        } else {
            $query = new \WP_Query( [
                'post_type'           => $fonte,
                'post_status'         => [ 'publish', 'private' ],
                's'                   => $parola,
                'search_columns'      => [ 'post_title', 'post_excerpt' ],
                'orderby'             => $parola === '' ? 'modified' : 'relevance',
                'order'               => 'DESC',
                'posts_per_page'      => 20,
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            ] );
            foreach ( $query->posts as $post ) {
                $estratto = has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
                $stato    = get_post_status_object( $post->post_status );
                $risultati[] = $this->risultato( $post, [
                    'aggancio'    => 'post:' . $post->ID,
                    'titolo'      => $this->testo( get_the_title( $post ) ),
                    'link'        => get_permalink( $post ),
                    'descrizione' => wp_trim_words( $this->testo( $estratto ), 25 ),
                    'immagine_id' => (int) get_post_thumbnail_id( $post ),
                    'stato'       => $post->post_status === 'publish' ? '' : mb_strtolower( $stato ? $stato->label : $post->post_status ),
                    'conteggio'   => null,
                ] );
            }
        }

        wp_send_json_success( $risultati );
    }

    /** Completa un risultato: immagini, tipo, note dei filtri. */
    private function risultato( \WP_Post|\WP_Term $oggetto, array $dati ): array {
        $immagine            = $dati['immagine_id'];
        $dati['immagine_url'] = $immagine ? (string) ( wp_get_attachment_image_url( $immagine, 'large' ) ?: '' ) : '';
        $dati['anteprima']    = $immagine ? (string) ( wp_get_attachment_image_url( $immagine, 'thumbnail' ) ?: '' ) : '';
        if ( $dati['immagine_url'] === '' ) $dati['immagine_id'] = 0;
        $dati['nome_aggancio'] = $this->nome_aggancio( $dati['aggancio'] );
        $dati['note']          = [];
        $dati = (array) apply_filters( 'uds_pfg_risultato_ricerca', $dati, $oggetto );
        $dati['note'] = array_values( array_map( 'strval', (array) ( $dati['note'] ?? [] ) ) );
        return $dati;
    }

    // ----------------------------------------------------------
    // CONTROLLO DEI LINK — avvisi nella pagina di amministrazione
    // ----------------------------------------------------------

    /**
     * Che cosa non va nel link di una card, visto da WordPress: frasi brevi,
     * nessuna se il link porta a un contenuto pubblicato, a una categoria che
     * esiste, a un archivio, o fuori dal sito (lì non si controlla).
     *
     * Solo nella pagina di amministrazione: sul sito le card restano come sono.
     * Un link a un contenuto che non c'è più può funzionare lo stesso, se un
     * reindirizzamento lo porta altrove; l'avviso serve a scrivere l'indirizzo
     * nuovo, non a nascondere la card.
     *
     * Altri plugin aggiungono i loro motivi col filtro uds_pfg_avvisi_link
     * (array $avvisi, string $link assoluto, int $post_id o 0).
     */
    private function avvisi_link( string $link ): array {
        $link = trim( $link );
        if ( $link === '' ) return [];

        if ( str_starts_with( $link, '/' ) && ! str_starts_with( $link, '//' ) ) {
            $link = home_url( $link );
        }
        if ( ! $this->link_del_sito( $link ) ) return [];

        $avvisi   = [];
        $percorso = $this->percorso( $link );

        // "&amp;" copiato dal codice HTML: il parametro dopo non vale più.
        if ( str_contains( $link, '&amp;' ) ) {
            $avvisi[] = 'nel link c\'è "&amp;" al posto di "&": i parametri che seguono non valgono';
        }

        $post_id = url_to_postid( $link );
        if ( ! $post_id && $percorso !== '' ) {
            // url_to_postid trova solo ciò che si vede: le bozze si cercano per nome.
            $post_id = $this->contenuto_dal_percorso( $percorso );
        }

        if ( $post_id ) {
            $stato = (string) get_post_status( $post_id );
            if ( $stato !== 'publish' ) {
                $oggetto  = get_post_status_object( $stato );
                $avvisi[] = sprintf(
                    'porta a un contenuto non pubblicato (%s)',
                    mb_strtolower( $oggetto ? $oggetto->label : $stato )
                );
            }
        } elseif ( $percorso !== '' && ! $this->percorso_riconosciuto( $percorso ) ) {
            $avvisi[] = 'non porta a un contenuto né a una categoria del sito; se il sito lo reindirizza (una regola, o WordPress che indovina un indirizzo simile) funziona, ma conviene scrivere l\'indirizzo giusto';
        }

        return array_values( array_filter( array_map(
            'strval',
            (array) apply_filters( 'uds_pfg_avvisi_link', $avvisi, $link, (int) $post_id )
        ) ) );
    }

    /** Stesso sito, con o senza "www.". */
    private function link_del_sito( string $link ): bool {
        $host = wp_parse_url( $link, PHP_URL_HOST );
        $casa = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( ! $host || ! $casa ) return false;
        $senza_www = fn( string $h ): string => preg_replace( '/^www\./i', '', strtolower( $h ) );
        return $senza_www( $host ) === $senza_www( $casa );
    }

    /** Il percorso del link rispetto alla home, senza barre ai lati. */
    private function percorso( string $link ): string {
        $percorso = trim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' );
        $base     = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
        if ( $base !== '' && str_starts_with( $percorso . '/', $base . '/' ) ) {
            $percorso = trim( substr( $percorso, strlen( $base ) ), '/' );
        }
        return $percorso;
    }

    /**
     * Un contenuto in qualunque stato (bozza, privato, cestino) dal percorso:
     * "prefisso del tipo/nome", oppure pagina o articolo senza prefisso.
     */
    private function contenuto_dal_percorso( string $percorso ): int {
        foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $tipo ) {
            $prefisso = is_array( $tipo->rewrite ) ? trim( (string) ( $tipo->rewrite['slug'] ?? '' ), '/' ) : '';
            if ( $prefisso === '' || ! str_starts_with( $percorso . '/', $prefisso . '/' ) ) continue;
            $resto = trim( substr( $percorso, strlen( $prefisso ) ), '/' );
            $post  = $resto !== '' ? get_page_by_path( $resto, OBJECT, $tipo->name ) : null;
            if ( $post ) return (int) $post->ID;
        }
        $post = get_page_by_path( $percorso, OBJECT, [ 'page', 'post' ] );
        return $post ? (int) $post->ID : 0;
    }

    /** Archivio di un tipo di contenuto, o di una categoria che esiste. */
    private function percorso_riconosciuto( string $percorso ): bool {
        foreach ( get_post_types( [ 'public' => true ] ) as $tipo ) {
            $archivio = get_post_type_archive_link( $tipo );
            if ( $archivio && $this->percorso( $archivio ) === $percorso ) return true;
        }
        foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tassonomia ) {
            $prefisso = is_array( $tassonomia->rewrite ) ? trim( (string) ( $tassonomia->rewrite['slug'] ?? '' ), '/' ) : '';
            if ( $prefisso === '' || ! str_starts_with( $percorso . '/', $prefisso . '/' ) ) continue;
            $parti = explode( '/', $percorso );
            return (bool) get_term_by( 'slug', end( $parti ), $tassonomia->name );
        }
        return false;
    }

    // ----------------------------------------------------------
    // LOGICA FRONTEND — SELEZIONE GRUPPO
    // ----------------------------------------------------------

    private function get_group_for_post( int $post_id ): ?array {
        $groups    = get_option( $this->config['opt_groups'], [] );
        $map       = get_option( $this->config['opt_map'], [] );
        $overrides = get_option( $this->config['opt_overrides'], [] );

        if ( empty( $groups ) ) return null;

        // 1. Override per post ID
        foreach ( $overrides as $ov ) {
            if ( (int) $ov['post_id'] === $post_id ) {
                return $this->find_group( $groups, $ov['group_id'] );
            }
        }

        // 2. Mappa categorie (già ordinata per peso al salvataggio)
        $post_cats = wp_get_post_categories( $post_id, [ 'fields' => 'slugs' ] );
        foreach ( $map as $entry ) {
            if ( empty( $entry['group_id'] ) ) continue;
            if ( in_array( $entry['slug'], $post_cats, true ) ) {
                return $this->find_group( $groups, $entry['group_id'] );
            }
        }

        // 3. Gruppo default
        foreach ( $groups as $g ) {
            if ( ! empty( $g['default'] ) ) return $g;
        }

        return null;
    }

    private function find_group( array $groups, string $group_id ): ?array {
        foreach ( $groups as $g ) {
            if ( $g['id'] === $group_id ) return $g;
        }
        return null;
    }

    // ----------------------------------------------------------
    // RENDERING HTML GRIGLIA
    // ----------------------------------------------------------

    private function render_grid( array $group, int $post_id = 0 ): string {
        $cards = apply_filters( 'uds_pfg_cards', $group['cards'], $post_id );
        if ( empty( $cards ) ) return '';

        $titolo_comune = apply_filters( 'uds_pfg_titolo_sezione', $this->get_titolo() );
        $titolo        = ! empty( $group['titolo'] ) ? $group['titolo'] : $titolo_comune;

        $settings    = get_option( 'uds_pfg_settings', [] );
        $utm_enabled = ! empty( $settings['utm_enabled'] );
        $utm_base    = [];
        if ( $utm_enabled ) {
            $utm_base = [
                'utm_source'   => parse_url( home_url(), PHP_URL_HOST ),
                'utm_medium'   => 'footer_grid',
                'utm_campaign' => ! empty( $group['utm_campaign'] )
                    ? $group['utm_campaign']
                    : sanitize_title( $group['nome'] ),
            ];
        }

        ob_start();
        ?>
        <div class="uds-pfg-wrapper">
            <?php if ( $titolo ) : ?>
                <h3 class="uds-pfg-titolo"><?php echo esc_html( $titolo ); ?></h3>
            <?php endif; ?>
            <div class="uds-pfg-griglia" style="--uds-pfg-cols:<?php echo min( count( $cards ), 3 ); ?>">
                <?php foreach ( $cards as $card ) :
                    $link     = $this->link_card( $card );
                    $has_btn  = ! empty( $card['testo_btn'] );
                    $has_link = $link !== '';
                    $card_tag = ( ! $has_btn && $has_link ) ? 'a' : 'div';

                    // Calcola il link con eventuali parametri UTM
                    if ( $has_link && $utm_enabled ) {
                        $utm_content = sanitize_title( $card['titolo'] );
                        $utm_args    = array_filter( array_merge( $utm_base, [
                            'utm_content' => $utm_content,
                        ] ) );
                        $link = add_query_arg( $utm_args, $link );
                    }
                ?>
                    <?php if ( $card_tag === 'a' ) : ?>
                    <a class="uds-pfg-card" href="<?php echo esc_url( $link ); ?>">
                    <?php else : ?>
                    <div class="uds-pfg-card">
                    <?php endif; ?>
                        <?php if ( $card['immagine_url'] ) : ?>
                            <div class="uds-pfg-img-wrap">
                                <img src="<?php echo esc_url( $card['immagine_url'] ); ?>"
                                     alt="<?php echo esc_attr( $card['titolo'] ); ?>"
                                     loading="lazy">
                            </div>
                        <?php endif; ?>
                        <div class="uds-pfg-card-titolo"><?php echo esc_html( $card['titolo'] ); ?></div>
                        <?php if ( $card['descrizione'] ) : ?>
                            <div class="uds-pfg-card-desc"><?php echo esc_html( $card['descrizione'] ); ?></div>
                        <?php endif; ?>
                        <?php if ( $has_btn ) : ?>
                            <?php if ( $has_link ) : ?>
                                <a class="uds-pfg-card-btn" href="<?php echo esc_url( $link ); ?>">
                                    <?php echo esc_html( $card['testo_btn'] ); ?>
                                </a>
                            <?php else : ?>
                                <span class="uds-pfg-card-btn"><?php echo esc_html( $card['testo_btn'] ); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php if ( $card_tag === 'a' ) : ?>
                    </a>
                    <?php else : ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ----------------------------------------------------------
    // HOOK — APPENDE LA GRIGLIA AL CONTENUTO
    // ----------------------------------------------------------

    public function append_grid( string $content ): string {
        if ( ! in_the_loop() ) return $content;
        if ( ! in_array( get_post_type(), $this->config['post_types'], true ) ) return $content;
        if ( ! is_single() ) return $content;

        $post_id = get_the_ID();
        $group   = $this->get_group_for_post( $post_id );
        if ( ! $group ) return $content;

        return $content . $this->render_grid( $group, $post_id );
    }

    // ----------------------------------------------------------
    // HOOK — CARICA CSS FRONTEND
    // ----------------------------------------------------------

    public function enqueue_frontend_assets() {
        if ( ! is_single() ) return;
        if ( ! in_array( get_post_type(), $this->config['post_types'], true ) ) return;

        wp_enqueue_style(
            'uds-pfg-frontend',
            plugin_dir_url( __FILE__ ) . 'frontend.css',
            [],
            $this->versione_file( 'frontend.css' )
        );
    }

}

// ============================================================
// AVVIO
// ============================================================

register_uninstall_hook( __FILE__, 'uds_pfg_uninstall' );

function uds_pfg_uninstall() {
    $config = UDS_PFG_CONFIG;
    delete_option( $config['opt_groups'] );
    delete_option( $config['opt_map'] );
    delete_option( $config['opt_overrides'] );
    delete_option( 'uds_pfg_settings' );
}

( new UDS_Posts_Footer_Grid( UDS_PFG_CONFIG ) )->init();
