# UdS Posts Footer Grid

Plugin WordPress che appende automaticamente una griglia di card suggerimento al fondo degli articoli del blog. Ogni card mostra immagine, titolo, descrizione e link. La griglia da mostrare si seleziona in base alla categoria dell'articolo, con possibilità di override per singolo post.

---

## Installazione

1. Copia la cartella `uds-posts-footer-grid/` in `wp-content/plugins/`
2. Attiva il plugin da *Amministrazione → Plugin*
3. Il menu **Footer Grid** appare sotto **Articoli** (configurabile, v. sotto)
4. Crea almeno un gruppo di card nel tab *Gruppi di card*
5. Assegna le categorie nel tab *Categorie*, oppure segna un gruppo come **Default**

---

## Configurazione rapida (`UDS_PFG_CONFIG`)

La costante in cima a `uds-posts-footer-grid.php` è l'unico punto da toccare per adattare il plugin all'installazione senza modificare la logica.

```php
define( 'UDS_PFG_CONFIG', [

    // Versione per cache busting CSS/JS — aggiornare ad ogni release
    'version'        => '1.0.0',

    // Chi può accedere al menu admin.
    // Stringa singola o array di capability e/o nomi di ruolo.
    'capability'     => [ 'manage_options', 'uds_blog_author' ],

    // Posizione del menu nell'admin WP:
    //   'edit.php'            → sotto Articoli
    //   'options-general.php' → sotto Impostazioni
    //   'themes.php'          → sotto Aspetto
    //   ''                    → voce di primo livello
    'menu_parent'    => 'edit.php',

    // Post type su cui mostrare la griglia
    'post_types'     => [ 'post' ],

    // Titolo sopra la griglia nel frontend (fallback se non impostato dall'admin)
    // Stringa vuota = titolo non mostrato
    'titolo_sezione' => 'Ti potrebbero interessare anche',

    // Chiavi wp_options (modificare solo se necessario evitare conflitti)
    'opt_groups'     => 'uds_pfg_groups',
    'opt_map'        => 'uds_pfg_cat_map',
    'opt_overrides'  => 'uds_pfg_overrides',

] );
```

### Accesso per ruoli multipli

`capability` accetta una stringa o un array. Ogni elemento può essere una **capability WordPress** (es. `manage_options`, `edit_posts`) oppure un **nome di ruolo** (es. `administrator`, `uds_blog_author`). L'utente ottiene l'accesso se soddisfa almeno uno degli elementi.

```php
// Solo amministratori
'capability' => 'manage_options',

// Admin + ruolo personalizzato
'capability' => [ 'manage_options', 'uds_blog_author' ],

// Tutti gli autori standard
'capability' => 'publish_posts',
```

---

## Interfaccia di amministrazione

Il pannello ha quattro tab.

### Tab — Gruppi di card

Un **gruppo** è un insieme di card visualizzate insieme. Per ogni gruppo:

- **Nome gruppo** — identificativo leggibile
- **Gruppo default** — usato quando nessuna categoria corrisponde; solo un gruppo può essere default
- **Card** — ogni card ha: immagine (dalla media library), titolo, descrizione, link, testo bottone

**Comportamento del link per card:**

| `testo_btn` | `link` | Risultato |
|-------------|--------|-----------|
| vuoto       | presente | intera card cliccabile (`<a>`) |
| vuoto       | vuoto    | card non cliccabile (`<div>`) |
| compilato   | presente | card `<div>` + bottone `<a>` col testo indicato |
| compilato   | vuoto    | card `<div>` + bottone `<span>` non cliccabile |

Il link accetta URL assoluti (`https://...`) e relativi (`/percorso/pagina`).

### Tab — Categorie

Tabella con tutte le categorie del sito. Per ciascuna si sceglie il gruppo da usare e un **Peso** (numero intero). Se un articolo appartiene a più categorie, viene usata la prima corrispondenza in ordine di peso crescente (peso 10 prima di peso 20). Le categorie lasciate su *— nessuno (usa default) —* non contribuiscono alla selezione.

### Tab — Override post

Assegna un gruppo specifico a un singolo articolo tramite il suo ID. Ha priorità assoluta su categoria e default.

### Tab — Impostazioni

Imposta il **titolo della sezione** visualizzato sopra la griglia nel frontend. Se lasciato vuoto, il titolo non appare. Il valore qui ha priorità su `titolo_sezione` in `UDS_PFG_CONFIG`.

---

## Logica di selezione del gruppo

Per ogni singolo articolo, il plugin scorre questi passi nell'ordine:

1. **Override per post ID** — se esiste un override per quell'articolo, usa quel gruppo
2. **Mappa categorie** — controlla le categorie dell'articolo in ordine di peso crescente; usa il primo gruppo assegnato
3. **Gruppo default** — usa il gruppo marcato come default
4. **Nessun gruppo trovato** — la griglia non viene mostrata

---

## Filtri WordPress

Per personalizzazioni da tema o `mu-plugin` senza modificare il plugin:

```php
// Sovrascrive il titolo della sezione
add_filter( 'uds_pfg_titolo_sezione', function( string $titolo ): string {
    return 'Potrebbe interessarti anche';
} );

// Filtra o riordina le card prima del rendering
// $cards = array di card, $post_id = ID dell'articolo corrente
add_filter( 'uds_pfg_cards', function( array $cards, int $post_id ): array {
    // es. rimuovi la prima card su certi post
    return $cards;
}, 10, 2 );
```

---

## Struttura dati in `wp_options`

| Chiave              | Contenuto                                        |
|---------------------|--------------------------------------------------|
| `uds_pfg_groups`    | Array dei gruppi di card                         |
| `uds_pfg_cat_map`   | Mappa categorie → gruppi, ordinata per peso      |
| `uds_pfg_overrides` | Override per post ID                             |
| `uds_pfg_settings`  | Impostazioni generali (es. titolo sezione)       |

Tutte le chiavi vengono eliminate automaticamente se il plugin viene disinstallato da WordPress (*Plugin → Elimina*).

### Struttura JSON gruppo

```json
{
  "id": "grp_abc123",
  "nome": "Costellazioni",
  "default": 0,
  "cards": [
    {
      "immagine_id":  1234,
      "immagine_url": "https://esempio.it/img.jpg",
      "titolo":       "Scuola di Costellazioni",
      "descrizione":  "Breve descrizione del corso",
      "link":         "/corsi/costellazioni",
      "testo_btn":    "Scopri il corso"
    }
  ]
}
```

---

## File del plugin

```
uds-posts-footer-grid/
├── uds-posts-footer-grid.php   # Configurazione + classe UDS_Posts_Footer_Grid
├── frontend.css                # Stili della griglia nel frontend
├── admin.css                   # Stili della pagina di amministrazione
└── admin.js                    # JS admin vanilla (no jQuery): accordion,
                                #   template gruppi/card, media library
```

---

## Note tecniche

### Perché non si usano i blocchi Gutenberg

I blocchi con `layout: grid` generano CSS tramite classi dinamiche (`wp-container-core-group-is-layout-*`) accodate da `WP_Style_Engine` solo su post nativi a blocchi. Iniettare questi blocchi via `do_blocks()` su un post classico produce HTML corretto ma senza i CSS. La soluzione HTML + CSS custom è più robusta e portabile.

### Perché `the_content` e non un template hook

`the_content` con priorità 20 è la scelta più compatibile con editor classico, Gutenberg e temi ibridi. Hook come `get_template_part` o `wp_after_content` sono tema-dipendenti. La guardia `in_the_loop()` previene rendering doppi nei loop secondari.

### Sottolineatura link e temi aggressivi

Alcuni temi usano selettori ad alta specificità (es. `body.single-post #primary .entry-content a`) che sovrascrivono i reset standard. Il CSS del plugin usa `!important` su `text-decoration: none` per `.uds-pfg-wrapper a` e relativi pseudo-stati — tecnica accettata per componenti plugin che devono isolarsi dal tema.

### Accesso multi-ruolo

Il plugin registra il menu con una capability virtuale interna (`uds_pfg_manage`) e la concede tramite filtro `user_has_cap` agli utenti che soddisfano uno qualsiasi degli elementi di `config['capability']`. Il filtro controlla direttamente `$allcaps` (capability) e `$user->roles` (ruoli) senza chiamare `current_user_can()` per evitare ricorsione.

---

## Sviluppi pianificati

- **Numero massimo di colonne** — campo nell'admin per impostare il cap (ora fisso a 3)
- **Colori configurabili** — colore bordo card e testo bottone dall'admin, senza toccare il CSS
- **Export/Import impostazioni** — scarica/carica la configurazione come JSON (utile per staging → produzione)
- **Pulizia database** — pulsante nell'admin con conferma (alternativa all'uninstall hook)

---

## Requisiti

- WordPress 6.0+
- PHP 8.0+
- Nessuna dipendenza da plugin di terze parti

## Licenza

GPL-2.0-or-later
