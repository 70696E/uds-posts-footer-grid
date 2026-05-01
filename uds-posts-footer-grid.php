<?php
/**
 * Plugin Name: UdS Posts Footer Grid
 * Description: Inserisce una griglia di suggerimenti al fondo degli articoli del blog, configurabile per categoria.
 * Version:     1.0.0
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
    'version'         => '1.0.0',

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
            $this->config['version'],
            true
        );

        wp_enqueue_style(
            'uds-pfg-admin',
            plugin_dir_url( __FILE__ ) . 'admin.css',
            [],
            $this->config['version']
        );
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
                'id'      => sanitize_key( $g['id'] ),
                'nome'    => sanitize_text_field( $g['nome'] ),
                'titolo'  => sanitize_text_field( $g['titolo'] ?? '' ),
                'default' => isset( $g['default'] ) ? 1 : 0,
                'cards'   => [],
            ];
            foreach ( $g['cards'] ?? [] as $c ) {
                $group['cards'][] = [
                    'immagine_id'  => absint( $c['immagine_id'] ),
                    'immagine_url' => esc_url_raw( $c['immagine_url'] ),
                    'titolo'       => sanitize_text_field( $c['titolo'] ),
                    'descrizione'  => sanitize_textarea_field( $c['descrizione'] ),
                    'link'         => esc_url_raw( $c['link'] ),
                    'testo_btn'    => sanitize_text_field( $c['testo_btn'] ?? '' ),
                ];
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
            <h1>UdS Posts Footer Grid</h1>

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
        $empty_card  = [ 'immagine_id' => '', 'immagine_url' => '', 'titolo' => '', 'descrizione' => '', 'link' => '' ];
        ?>
        <form method="post" id="uds-pfg-groups-form">
            <?php wp_nonce_field( 'uds_pfg_save' ); ?>
            <input type="hidden" name="uds_pfg_action" value="save_groups">

            <div id="uds-pfg-groups-list">
                <?php foreach ( $groups as $gi => $group ) : ?>
                    <?php $this->render_group( $gi, $group ); ?>
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

    private function render_group( $gi, array $group ) {
        // Per i template (id vuoto) usa il placeholder __ID__, sostituito via JS con un ID univoco.
        $group_id = $group['id'] ?: '__ID__';
        ?>
        <div class="uds-pfg-group" data-gi="<?php echo esc_attr( $gi ); ?>">
            <div class="uds-pfg-group-header">
                <span class="uds-pfg-drag-handle">☰</span>
                <strong class="uds-pfg-group-label"><?php echo esc_html( $group['nome'] ?: 'Nuovo gruppo' ); ?></strong>
                <button type="button" class="button-link uds-pfg-toggle-group">▼</button>
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
                        <?php $this->render_card( $gi, $ci, $card ); ?>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button uds-pfg-add-card" data-gi="<?php echo esc_attr( $gi ); ?>">
                    + Aggiungi card
                </button>
            </div>
        </div>
        <?php
    }

    private function render_card( $gi, $ci, array $card ) {
        $testo_btn = $card['testo_btn'] ?? '';
        ?>
        <div class="uds-pfg-card" data-ci="<?php echo esc_attr( $ci ); ?>">
            <div class="uds-pfg-card-header">
                <span class="uds-pfg-drag-handle">☰</span>
                <span class="uds-pfg-card-label"><?php echo esc_html( $card['titolo'] ?: 'Nuova card' ); ?></span>
                <button type="button" class="button-link-delete uds-pfg-remove-card">Rimuovi</button>
            </div>
            <div class="uds-pfg-card-body">
                <table class="form-table">
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
                                      rows="2" class="large-text"><?php echo esc_textarea( $card['descrizione'] ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Link</th>
                        <td>
                            <input type="text" name="groups[<?php echo $gi; ?>][cards][<?php echo $ci; ?>][link]"
                                   value="<?php echo esc_url( $card['link'] ); ?>"
                                   class="large-text"
                                   placeholder="https://... oppure /percorso/relativo">
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
        $titolo = $this->get_titolo();
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

        ob_start();
        ?>
        <div class="uds-pfg-wrapper">
            <?php if ( $titolo ) : ?>
                <h3 class="uds-pfg-titolo"><?php echo esc_html( $titolo ); ?></h3>
            <?php endif; ?>
            <div class="uds-pfg-griglia" style="--uds-pfg-cols:<?php echo min( count( $cards ), 3 ); ?>">
                <?php foreach ( $cards as $card ) :
                    $has_btn  = ! empty( $card['testo_btn'] );
                    $has_link = ! empty( $card['link'] );
                    // Card cliccabile solo se ha un link e non ha un bottone dedicato
                    $card_tag = ( ! $has_btn && $has_link ) ? 'a' : 'div';
                ?>
                    <?php if ( $card_tag === 'a' ) : ?>
                    <a class="uds-pfg-card" href="<?php echo esc_url( $card['link'] ); ?>">
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
                                <a class="uds-pfg-card-btn" href="<?php echo esc_url( $card['link'] ); ?>">
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
            $this->config['version']
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
