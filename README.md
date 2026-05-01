# UdS Posts Footer Grid

Plugin WordPress per inserire una griglia di suggerimenti (box con immagine, titolo, descrizione, link) al fondo degli articoli del blog. La griglia è configurabile per categoria e sovrascrivibile per singolo articolo.

---

## Stato attuale — v1.0.0

### Funzionalità implementate

- Griglia responsive appesa al fondo dei post del blog (`post_type = post`) tramite hook `the_content`
- Tre livelli di selezione del gruppo di card da mostrare, in ordine di priorità:
  1. **Override per post ID** — assegna un gruppo specifico a un singolo articolo
  2. **Mappa categorie** — associa categorie a gruppi, con campo Peso per definire la priorità quando un articolo ha più categorie
  3. **Gruppo default** — usato quando nessuna categoria corrisponde
- Interfaccia di amministrazione con tre tab: Gruppi di card, Categorie, Override post
- Selezione immagini dalla media library di WordPress
- CSS responsive: 3 colonne su desktop, 2 su tablet, 1 su mobile
- Ogni card è interamente cliccabile (elemento `<a>`)
- Configurazione centralizzata tramite costante `UDS_PFG_CONFIG` in cima al file PHP

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
| `titolo_sezione` | `Ti potrebbero interessare anche` | Titolo sopra la griglia (hard-coded, da rendere configurabile) |
| `opt_groups`     | `uds_pfg_groups`         | Chiave wp_options per i gruppi                           |
| `opt_map`        | `uds_pfg_cat_map`        | Chiave wp_options per la mappa categorie                 |
| `opt_overrides`  | `uds_pfg_overrides`      | Chiave wp_options per gli override                       |

---

## Sviluppi pianificati

### Interfaccia e configurazione

- **Titolo sezione configurabile** — campo editabile nell'admin, non più hard-coded in `UDS_PFG_CONFIG`. Se vuoto, il titolo non viene renderizzato.

- **Bottone opzionale** — se il campo "Testo bottone" è compilato, viene mostrato un bottone con quel testo. In questo caso il link vale solo per il bottone, e il box non è interamente cliccabile. Se il campo è vuoto, il box rimane interamente cliccabile come ora.

- **Numero massimo di colonne** — campo numerico nell'admin per limitare le colonne della griglia (utile quando ci sono molte card). Le card in eccesso vanno a capo automaticamente.

- **Colori configurabili** — almeno colore del bordo e del testo del bottone, per adattarsi al tema senza modificare il CSS.

- **Export/Import impostazioni** — pulsante per scaricare le impostazioni come file JSON e per importarle. Utile per passare la configurazione da staging a produzione.

- **Pulizia database** — pulsante nell'admin per eliminare tutte le opzioni salvate dal plugin (`uds_pfg_groups`, `uds_pfg_cat_map`, `uds_pfg_overrides`), con richiesta di conferma.

### Comportamento immagini

- **Immagine a larghezza piena** — di default le immagini devono occupare tutta la larghezza del box mostrandosi completamente, senza crop (`object-fit: contain` invece dell'attuale `cover`). Valutare se offrire entrambe le modalità come opzione per gruppo o per card.

### Link e portabilità

- **Link relativi** — supportare URL relativi (es. `/corsi/nome-corso`) per immagini e link, in modo che le impostazioni siano portabili tra staging e produzione senza modifiche. Attualmente `esc_url_raw` accetta già relativi, ma va verificato il comportamento end-to-end e documentato.

### Stabilità e manutenzione

- **Hook filtro per il titolo** — esporre `apply_filters('uds_pfg_titolo_sezione', $titolo)` per permettere override da tema o mu-plugin senza modificare il plugin.

- **Hook filtro per le card** — esporre `apply_filters('uds_pfg_cards', $cards, $post_id)` per permettere manipolazione programmatica delle card prima del rendering.

- **Uninstall hook** — aggiungere `register_uninstall_hook` per pulire le opzioni dal database quando il plugin viene eliminato da WordPress.

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
