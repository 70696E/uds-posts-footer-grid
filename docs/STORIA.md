# La storia del plugin, e le decisioni

Una sezione per tappa, con ciò che è stato deciso e perché. Il dettaglio delle
modifiche è in `uds-posts-footer-grid.txt` e nei commit.

## 1. Le origini (28/04 – 24/05/2026, versioni 1.0 – 1.2)

- Nato in una conversazione con Claude (chat), poi passato a Claude Code il 01/05.
- **Perché HTML e CSS propri e non i blocchi**: i blocchi con layout a griglia hanno CSS
  generato solo per i post a blocchi; iniettati in un post classico perdono lo stile
  (README, "Note tecniche").
- **Perché `the_content`** a priorità 20 e non un aggancio del tema: vale con
  qualunque tema ed editor; `in_the_loop()` evita la griglia doppia.
- `!important` sulla sottolineatura dei link: il tema di allora la imponeva con
  selettori molto specifici.
- Accesso alla pagina per capability **o** nome di ruolo (`uds_blog_author`), con una
  capability virtuale concessa da `user_has_cap`.
- 1.2.0 (24/05): parametri UTM automatici, titolo per gruppo.

## 2. In produzione senza git, poi collegato (30/09 – 22/10/2026)

- Nel passaggio del sito a GeneratePress (ottobre 2026) la cartella di produzione era
  una copia senza git. Il 22/10 è stata collegata al repo: copia di sicurezza in
  `~/uds-posts-footer-grid-www-20261022`, `git init`, `git reset origin/main` **senza
  `--hard`** (così `git status` mostra in che cosa il disco differisce dal repo:
  era la 1.2.0 identica, mancavano solo `.gitattributes`, `.gitignore`, `LICENSE`),
  poi `git checkout -- .`. Da allora si aggiorna col pull.

## 3. Gli avvisi sui link (21–22/10/2026, versione 1.3)

- **Il motivo**: la ristrutturazione del sito verso la psicogenealogia (ottobre 2026)
  ha nascosto, reso privati o rinominato molti corsi, e le card puntavano ancora lì.
  Il 22/10 su www 15 card avevano il link da controllare.
- **Decisione: avvisare, non nascondere.** Un modulo che toglie da solo le card non
  valide nasconde il problema (nessuno lo corregge) e lascia griglie di due card
  invece di tre. Gli avvisi stanno solo nella pagina di amministrazione: riepilogo in
  cima, segno sul gruppo e sulla card, motivo sotto il link.
- **Decisione: il plugin resta autonomo.** Il controllo generico è qui (contenuto non
  pubblicato, indirizzo che non porta a niente, `&amp;` copiato dal codice HTML);
  quello che sa solo Tutor è in puc2 (corso nascosto, categoria inesistente o con meno
  di due corsi visibili, parametro della categoria senza `course_filter=true`) e arriva
  col filtro `uds_pfg_avvisi_link`. Si è valutato di spostare il plugin in puc2: no,
  perché funziona su qualunque WordPress e il filtro basta a collegarli.
- **Un corso nascosto dà "pagina non trovata"** a chi non è amministratore, anche dal
  link diretto (il filtro dei corsi nascosti di puc2 vale anche per la pagina del
  corso): l'avviso lo dice.
- **Un link "che non porta a niente" può funzionare**: WordPress reindirizza un nome
  rinominato (o simile: `journaling_videocorso` → `journaling-videocorso`) al nuovo
  indirizzo. L'avviso c'è lo stesso: conviene scrivere l'indirizzo giusto.
- Comandi per aprire e chiudere i gruppi (chiudi tutti, apri tutti, apri solo questo,
  clic sull'intestazione); i gruppi chiusi si ricordano nella scheda del browser.
- **La trappola della cache**: dopo un pull i bottoni nuovi non funzionavano, perché
  lo script aveva lo stesso indirizzo (`admin.js?ver=1.3.0`) e il browser teneva il
  vecchio. Da allora CSS e JS hanno nella versione la data del file.

## 4. Riempi da…, card agganciata, ordine (22/10/2026, versione 1.4)

- **Riempi da…**: le card si possono ancora scrivere a mano, oppure riempire da una
  ricerca fra pagine, articoli, corsi, pacchetti e categorie (fino a 20 risultati con
  copertina, stato, numero di elementi). Le fonti le dà WordPress (tipi di contenuto e
  tassonomie pubbliche); puc2 toglie le parti interne di Tutor (lezioni, quiz,
  compiti), mette i corsi in alto, segna i corsi nascosti e conta i corsi visibili di
  una categoria (il numero di WordPress conta anche i nascosti, non i pacchetti né le
  sottocategorie: due numeri diversi confondevano, ne resta uno).
- **Card agganciata**: una card riempita dalla ricerca ricorda l'oggetto (`post:123`,
  `term:tassonomia:45`) e il link si ricalcola da lì, così segue i cambi di indirizzo
  (i sette corsi rinominati del 22/10 non avrebbero rotto niente). Titolo, testo e
  immagine restano quelli salvati, perché si ritoccano. Il link scritto resta come
  riserva. Scrivere il link a mano sgancia la card. I numeri valgono solo nel sito in
  cui sono stati scelti.
- **Link relativi**: i link del sito si salvano senza `https://dominio` (con o senza
  `www`), così valgono uguale in produzione e nei cloni di prova.
- **☰ trascina davvero** (prima era solo un segno): gruppi, e card anche fra gruppi.
  Durante il trascinamento si vedono solo le intestazioni, e la pagina scorre da sola
  ai bordi della finestra (il browser, trascinando, ignora la rotella). Al salvataggio
  i campi si rinumerano nell'ordine della pagina: prima, togliendo una card e
  aggiungendone due, due card potevano avere lo stesso numero.
- Le card hanno gli stessi comandi dei gruppi (chiudi tutte, apri solo questa).
- Provato su st5 con un gruppo di prova (ricerca, aggancio a un corso e a una
  categoria, ordine, salvataggio), poi eliminato.

## 5. Il salvataggio da una pagina vecchia (22/10/2026, versione 1.4.1)

- **Il caso**: su www tre card già corrette (Journaling e Carte immaginali in "coach
  emozionale", La magia dell'Acqua in ACQUA) sono tornate ai link vecchi. Il più
  probabile: un salvataggio da una scheda del browser aperta prima delle correzioni.
  *Salva gruppi* salva tutti i gruppi insieme, quindi una pagina vecchia riscrive tutto.
- **Decisione**: un'impronta dei gruppi nella pagina; se al salvataggio non coincide
  con quelli salvati, **non si salva**. I gruppi inviati restano per un'ora (transient
  per utente) e la pagina li ripresenta con un avviso: *Salva gruppi* li salva così
  (l'impronta è ora quella nuova), oppure si ricarica e si perdono. Non si fondono le
  due versioni: con gruppi e card riordinabili, una fusione automatica sbaglierebbe.
- In più il browser chiede conferma prima di lasciare la pagina con modifiche non
  salvate (la ricerca "Riempi da…" non conta).
