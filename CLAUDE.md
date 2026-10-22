# UdS Posts Footer Grid — istruzioni per la sessione

Plugin WordPress del sito dell'Università Popolare del Sociale (UdS): la griglia "Ti
potrebbero interessare anche" in fondo agli articoli del blog. Gruppi di card scritti
nella pagina **Articoli → Footer Grid**, scelti per categoria dell'articolo, per singolo
articolo o col gruppo predefinito, e aggiunti al contenuto con `the_content`.

## Leggi prima di lavorare

1. `README.md` — che cosa fa e come si usa: tab, campi, logica di scelta del gruppo,
   filtri, struttura dei dati. È il riferimento; tenerlo aggiornato a ogni versione.
2. `docs/STORIA.md` — le versioni e **le decisioni prese**, con il perché. Prima di
   cambiare un comportamento, guardare se c'è una decisione che lo spiega.
3. `docs/BACKLOG.md` — ciò che resta da fare, e i dati da sistemare nei siti.
4. `uds-posts-footer-grid.txt` — il registro breve delle modifiche, per versione.

## Dove sta

| Sito | Cartella | Git |
|---|---|---|
| produzione (www) | `~/public_html/wp-content/plugins/uds-posts-footer-grid` | clone su `main` dal 22/10/2026 |
| clone di prova (st5) | `~/st5.universitadelsociale.org/wp-content/plugins/uds-posts-footer-grid` | clone |
| copia di lavoro | `W:\xampp\htdocs\uds25\wp-content\plugins\uds-posts-footer-grid` | clone |

Repo privata `70696E/uds-posts-footer-grid`. Produzione e st5 sono sullo stesso server
(`clio`), senza SSH da qui: i comandi li dà chi lavora con te, e la verifica si fa nel
browser. **Prima di ogni comando git sul server, un `pwd`**: le cartelle hanno lo
stesso nome nei due siti.

I gruppi stanno nel database di ciascun sito (opzioni `uds_pfg_*`), non nel codice:
st5 e www hanno gruppi diversi, e un gruppo cambiato in uno non cambia nell'altro.

## Regole di lavoro

- **Un branch per ogni lavoro** (`claude/<descrizione-in-kebab-case>`), mai commit
  diretti su `main`. Il branch si prova su st5 (`git checkout` del branch lì), poi si
  porta su `main` con `--no-ff`, si cancella (locale e remoto), e da `main` si fa il
  pull in produzione.
- **Non c'è WordPress in locale**: `php -l` su ogni PHP toccato, `node --check
  admin.js`, poi la prova su st5.
- **Versione**: in tre posti — intestazione del file PHP (`Version:`), `UDS_PFG_CONFIG
  ['version']`, titolo del README — più una voce in `uds-posts-footer-grid.txt`.
  CSS e JS hanno nell'indirizzo anche la data del file (`versione_file()`): una
  modifica arriva al browser anche senza cambiare versione.
- **Il plugin resta autonomo**: funziona su qualunque WordPress, senza Tutor e senza
  `pin-uds-custom-2026` (puc2). Ciò che sa solo Tutor (corsi nascosti, corsi visibili
  di una categoria) sta in puc2, modulo "Tutor LMS: ritocchi", ritocco
  `avvisi_griglia_articoli`, e arriva qui **solo dai filtri** (`uds_pfg_avvisi_link`,
  `uds_pfg_risultato_ricerca`, `uds_pfg_fonti_ricerca`). Un filtro nuovo si documenta
  nel README.
- **Sul sito le card non cambiano da sole**: gli avvisi stanno solo nella pagina di
  amministrazione, e le card si correggono a mano (decisione del 21/10, `docs/STORIA.md`).
- Codice, commenti e documenti in italiano. JS senza jQuery e senza passaggi di
  compilazione; PHP 8.0 o più.
- Niente numeri (ID di contenuti) presi da un sito in un comando per l'altro.
- **I documenti non sono pubblici**: il `.htaccess` del plugin nega via web `.md`, `.txt`
  e i file di git (CSS e JS restano serviti). Un tipo di file nuovo da tenere privato
  si aggiunge lì; dopo il pull si prova da ospite che risponda 403.

## Come si prova su st5

1. `cd ~/st5.universitadelsociale.org/wp-content/plugins/uds-posts-footer-grid && pwd
   && git fetch && git checkout <branch>`
2. Articoli → Footer Grid: un gruppo di prova (che si elimina alla fine) per non
   toccare quelli veri; avvisi, ricerca, aggancio, ordine, salvataggio.
3. Un articolo qualunque, da ospite: la griglia in fondo.
4. A prova finita la cartella di st5 torna su `main`.
