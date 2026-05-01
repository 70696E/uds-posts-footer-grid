# UdS Posts Footer Grid

Plugin WordPress per inserire una griglia di suggerimenti (box con immagine, titolo, descrizione, link) al fondo degli articoli del blog. La griglia è configurabile per categoria e sovrascrivibile per singolo articolo.

---

## Stato attuale — v1.1.0

### Funzionalità implementate

- Griglia responsive appesa al fondo dei post del blog (`post_type = post`) tramite hook `the_content`
- Tre livelli di selezione del gruppo di card da mostrare, in ordine di priorità:
  1. **Override per post ID** — assegna un gruppo specifico a un singolo articolo
  2. **Mappa categorie** — associa categorie a gruppi, con campo Peso per definire la priorità quando un articolo ha più categorie
  3. **Gruppo default** — usato quando nessuna categoria corrisponde
- Interfaccia di amministrazione con quattro tab: Gruppi di card, Categorie, Override post, Impostazioni
- Selezione immagini dalla media library di WordPress
- CSS responsive: max 3 colonne su desktop, 2 su tablet, 1 su mobile
- **Bottone card configurabile**: se il campo "Testo bottone" è compilato, mostra un bottone `<a>` e il box non è cliccabile; se vuoto, l'intera card è un `<a>` cliccabile (nessun bottone)
- **Titolo sezione configurabile** dall'interfaccia admin (tab Impostazioni); fallback al valore in `UDS_PFG_CONFIG`; vuoto = nasconde il titolo
- Configurazione centralizzata tramite costante `UDS_PFG_CONFIG` in cima al file PHP
- **Uninstall hook**: elimina tutte le opzioni dal database quando il plugin viene rimosso da WordPress
- **Filtri WordPress**: `uds_pfg_titolo_sezione` e `uds_pfg_cards` per override da tema o mu-plugin
- Admin scritto in vanilla JS (nessuna dipendenza da jQuery nel codice custom)

### File

```
uds-posts-footer-grid/
├── uds-posts-footer-grid.php   # Logica principale, classe UDS_Posts_Footer_Grid
├── frontend.css                # Stili della griglia nel frontend
├── admin.css                   # Stili della pagina di amministrazione
└── admin.js                    # JS admin: media library, aggiunta/rimozione gruppi e card
```

### Dati salvati in wp_options

| Chiave              | Contenuto                          |
|---------------------|------------------------------------|
| `uds_pfg_groups`    | Array dei gruppi di card           |
| `uds_pfg_cat_map`   | Mappa categorie → gruppi (ordinata per peso) |
| `uds_pfg_overrides` | Override per post ID               |

### Configurazione (UDS_PFG_CONFIG)

| Parametro        | Default                  | Descrizione                                              |
|------------------|--------------------------|----------------------------------------------------------|
| `version`        | `1.0.0`                  | Versione per cache busting CSS/JS                        |
| `capability`     | `manage_options`         | Capability richiesta per accedere all'admin              |
| `menu_parent`    | `options-general.php`    | Posizione menu: vuoto = primo livello, oppure slug pagina WP |
| `post_types`     | `['post']`               | Post type su cui mostrare la griglia                     |
| `titolo_sezione` | `Ti potrebbero interessare anche` | Titolo di fallback (sovrascrivibile dall'admin o con `apply_filters`) |
| `opt_groups`     | `uds_pfg_groups`         | Chiave wp_options per i gruppi                           |
| `opt_map`        | `uds_pfg_cat_map`        | Chiave wp_options per la mappa categorie                 |
| `opt_overrides`  | `uds_pfg_overrides`      | Chiave wp_options per gli override                       |

### Filtri disponibili

```php
// Modifica il titolo della sezione prima del rendering
add_filter( 'uds_pfg_titolo_sezione', function( $titolo ) {
    return 'Il mio titolo personalizzato';
} );

// Modifica o filtra le card prima del rendering (es. rimuovi card per certi utenti)
add_filter( 'uds_pfg_cards', function( $cards, $post_id ) {
    return $cards;
}, 10, 2 );
```

---

## Sviluppi pianificati

### Interfaccia e configurazione

- **Numero massimo di colonne** — campo numerico nell'admin per limitare le colonne della griglia (utile quando ci sono molte card). Attualmente cappato a 3 in modo fisso.

- **Colori configurabili** — almeno colore del bordo e del testo del bottone, per adattarsi al tema senza modificare il CSS.

- **Export/Import impostazioni** — pulsante per scaricare le impostazioni come file JSON e per importarle. Utile per passare la configurazione da staging a produzione.

- **Pulizia database** — pulsante nell'admin per eliminare tutte le opzioni salvate dal plugin, con richiesta di conferma (in alternativa all'uninstall hook).

### Comportamento immagini

- **Immagine a larghezza piena** — valutare `object-fit: contain` invece dell'attuale `cover`, per mostrare l'immagine completa senza crop. Potrebbe diventare un'opzione per gruppo o per card.

### Link e portabilità

- **Link relativi** — `esc_url_raw` accetta già URL relativi, ma va verificato e documentato il comportamento end-to-end per garantire la portabilità tra staging e produzione.

### Stabilità e manutenzione

- **Readme.txt** — aggiungere il file in formato WordPress.org per compatibilità con il repository plugin di WP (anche se il plugin non è destinato alla distribuzione pubblica).

---

## Note tecniche

### Perché non si usano i blocchi Gutenberg per la griglia

I blocchi con `layout: grid` generano CSS tramite classi dinamiche (`wp-container-core-group-is-layout-*`) che vengono accodate da `WP_Style_Engine` solo quando il post è nativo a blocchi. Iniettare questi blocchi via `do_blocks()` su un post classico produce HTML corretto ma senza i CSS necessari. La soluzione adottata (HTML + CSS custom) è più robusta e portabile.

### Perché `the_content` e non un template hook

`the_content` con priorità 20 è la scelta più compatibile con editor classico, Gutenberg e temi ibridi. Hook come `get_template_part` o `wp_after_content` sono tema-dipendenti. La guardia `in_the_loop()` previene rendering doppi nei loop secondari.

### Struttura dati gruppi (wp_options)

```json
[
  {
    "id": "grp_abc123",
    "nome": "Costellazioni",
    "default": 0,
    "cards": [
      {
        "immagine_id": 1234,
        "immagine_url": "https://...",
        "titolo": "Scuola di Costellazioni",
        "descrizione": "Breve descrizione",
        "link": "https://..."
      }
    ]
  }
]
```

---

## Requisiti

- WordPress 6.0+
- PHP 8.0+
- Nessuna dipendenza da plugin di terze parti

## Licenza

GPL-2.0-or-later
