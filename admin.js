/* global wp */
document.addEventListener( 'DOMContentLoaded', function () {

    var groupCounter    = document.querySelectorAll( '.uds-pfg-group' ).length;
    var overrideCounter = document.querySelectorAll( '.uds-pfg-override-row' ).length;

    function randId() {
        return 'grp_' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 5 );
    }

    // ============================================================
    // GRUPPI APERTI E CHIUSI
    // I gruppi chiusi si ricordano per la durata della scheda del
    // browser (sessionStorage), così dopo "Salva gruppi" restano chiusi.
    // ============================================================

    var CHIAVE_CHIUSI = 'uds-pfg-gruppi-chiusi';

    function gruppi() {
        return document.querySelectorAll( '#uds-pfg-groups-list .uds-pfg-group' );
    }

    function idGruppo( gruppo ) {
        var campo = gruppo.querySelector( 'input[name$="][id]"]' );
        return campo ? campo.value : '';
    }

    function apriGruppo( gruppo, aperto ) {
        gruppo.querySelector( '.uds-pfg-group-body' ).classList.toggle( 'is-hidden', ! aperto );
        gruppo.classList.toggle( 'is-chiuso', ! aperto );
        var bottone = gruppo.querySelector( '.uds-pfg-toggle-group' );
        if ( bottone ) bottone.setAttribute( 'aria-expanded', aperto ? 'true' : 'false' );
    }

    function ricordaGruppi() {
        var chiusi = [];
        gruppi().forEach( function ( g ) {
            if ( g.classList.contains( 'is-chiuso' ) ) chiusi.push( idGruppo( g ) );
        } );
        try { sessionStorage.setItem( CHIAVE_CHIUSI, JSON.stringify( chiusi ) ); } catch ( err ) {}
    }

    // Le card chiuse, allo stesso modo: una card si riconosce dal gruppo e
    // da ciò a cui porta (aggancio, o link, o titolo).
    var CHIAVE_CARD_CHIUSE = 'uds-pfg-card-chiuse';

    function idCard( card ) {
        var gruppo = card.closest( '.uds-pfg-group' );
        var valore = function ( sel ) { var c = card.querySelector( sel ); return c ? c.value : ''; };
        return ( gruppo ? idGruppo( gruppo ) : '' ) + '|' +
            ( valore( '.uds-pfg-aggancio' ) || valore( '.uds-pfg-link' ) || valore( '.uds-pfg-card-titolo' ) );
    }

    function apriCard( card, aperta ) {
        card.querySelector( '.uds-pfg-card-body' ).classList.toggle( 'is-hidden', ! aperta );
        card.classList.toggle( 'is-chiusa', ! aperta );
        var bottone = card.querySelector( '.uds-pfg-toggle-card' );
        if ( bottone ) bottone.setAttribute( 'aria-expanded', aperta ? 'true' : 'false' );
    }

    function ricordaCard() {
        var chiuse = [];
        document.querySelectorAll( '#uds-pfg-groups-list .uds-pfg-card.is-chiusa' ).forEach( function ( c ) {
            chiuse.push( idCard( c ) );
        } );
        try { sessionStorage.setItem( CHIAVE_CARD_CHIUSE, JSON.stringify( chiuse ) ); } catch ( err ) {}
    }

    ( function () {
        var chiusi = [], chiuse = [];
        try { chiusi = JSON.parse( sessionStorage.getItem( CHIAVE_CHIUSI ) || '[]' ); } catch ( err ) {}
        try { chiuse = JSON.parse( sessionStorage.getItem( CHIAVE_CARD_CHIUSE ) || '[]' ); } catch ( err ) {}
        gruppi().forEach( function ( g ) {
            if ( chiusi.indexOf( idGruppo( g ) ) !== -1 ) apriGruppo( g, false );
        } );
        document.querySelectorAll( '#uds-pfg-groups-list .uds-pfg-card' ).forEach( function ( c ) {
            if ( chiuse.indexOf( idCard( c ) ) !== -1 ) apriCard( c, false );
        } );
    } )();

    // ============================================================
    // CLICK — delegazione su document
    // ============================================================

    document.addEventListener( 'click', function ( e ) {
        var t = e.target;

        // Chiudi tutti / Apri tutti (in cima alla lista e in ogni gruppo)
        if ( t.matches( '.uds-pfg-chiudi-tutti' ) || t.matches( '.uds-pfg-apri-tutti' ) ) {
            var aperti = t.matches( '.uds-pfg-apri-tutti' );
            gruppi().forEach( function ( g ) { apriGruppo( g, aperti ); } );
            ricordaGruppi();
            return;
        }

        // Apri solo questo: apre il gruppo e chiude gli altri
        if ( t.matches( '.uds-pfg-solo-questo' ) ) {
            var questo = t.closest( '.uds-pfg-group' );
            gruppi().forEach( function ( g ) { apriGruppo( g, g === questo ); } );
            ricordaGruppi();
            return;
        }

        // Apri / chiudi un gruppo: il pulsante ▼ o un punto qualunque della
        // sua intestazione, tranne gli altri pulsanti
        var intestazione = t.closest( '.uds-pfg-group-header' );
        if ( intestazione && ( t.matches( '.uds-pfg-toggle-group' ) || ! t.closest( 'button, a, input, select, textarea, .uds-pfg-drag-handle' ) ) ) {
            var gruppo = intestazione.closest( '.uds-pfg-group' );
            apriGruppo( gruppo, gruppo.classList.contains( 'is-chiuso' ) );
            ricordaGruppi();
            return;
        }

        // Card del gruppo: chiudi tutte / apri solo questa
        if ( t.matches( '.uds-pfg-chiudi-card' ) || t.matches( '.uds-pfg-sola-card' ) ) {
            var questa = t.closest( '.uds-pfg-card' );
            var sola   = t.matches( '.uds-pfg-sola-card' );
            questa.closest( '.uds-pfg-cards-list' ).querySelectorAll( '.uds-pfg-card' ).forEach( function ( c ) {
                apriCard( c, sola && c === questa );
            } );
            ricordaCard();
            return;
        }

        // Apri / chiudi una card: il pulsante ▼ o un punto qualunque della sua
        // intestazione, tranne gli altri pulsanti
        var testaCard = t.closest( '.uds-pfg-card-header' );
        if ( testaCard && ( t.matches( '.uds-pfg-toggle-card' ) || ! t.closest( 'button, a, input, select, textarea, .uds-pfg-drag-handle' ) ) ) {
            var cardCliccata = testaCard.closest( '.uds-pfg-card' );
            apriCard( cardCliccata, cardCliccata.classList.contains( 'is-chiusa' ) );
            ricordaCard();
            return;
        }

        // Rimuovi gruppo
        if ( t.matches( '.uds-pfg-remove-group' ) ) {
            e.stopPropagation();
            if ( ! confirm( 'Eliminare questo gruppo?' ) ) return;
            t.closest( '.uds-pfg-group' ).remove();
            return;
        }

        // Aggiungi gruppo
        if ( t.matches( '#uds-pfg-add-group' ) ) {
            var tmplGroup = document.getElementById( 'tmpl-uds-pfg-group' );
            var htmlGroup = tmplGroup.innerHTML
                .replace( /__GI__/g, groupCounter++ )
                .replace( /__ID__/g, randId() );
            document.getElementById( 'uds-pfg-groups-list' ).insertAdjacentHTML( 'beforeend', htmlGroup );
            return;
        }

        // Aggiungi card
        if ( t.matches( '.uds-pfg-add-card' ) ) {
            var group   = t.closest( '.uds-pfg-group' );
            var gi      = group.dataset.gi;
            var ci      = group.querySelectorAll( '.uds-pfg-card' ).length;
            var tmplCard = document.getElementById( 'tmpl-uds-pfg-card' );
            var htmlCard = tmplCard.innerHTML.replace( /__GI__/g, gi ).replace( /__CI__/g, ci );
            group.querySelector( '.uds-pfg-cards-list' ).insertAdjacentHTML( 'beforeend', htmlCard );
            return;
        }

        // Rimuovi card
        if ( t.matches( '.uds-pfg-remove-card' ) ) {
            e.stopPropagation();
            // Come per i gruppi: un clic sbagliato non deve far sparire la card.
            var titoloCard = t.closest( '.uds-pfg-card' ).querySelector( '.uds-pfg-card-titolo' ).value;
            if ( ! confirm( 'Rimuovere la card' + ( titoloCard ? ' «' + titoloCard + '»' : '' ) + '?' ) ) return;
            t.closest( '.uds-pfg-card' ).remove();
            return;
        }

        // Seleziona immagine — Media Library
        if ( t.matches( '.uds-pfg-select-image' ) ) {
            e.preventDefault();
            var cardBody = t.closest( '.uds-pfg-card-body' );
            var frame = wp.media( {
                title: 'Seleziona immagine',
                button: { text: 'Usa questa immagine' },
                multiple: false,
                library: { type: 'image' },
            } );
            frame.on( 'select', function () {
                var att    = frame.state().get( 'selection' ).first().toJSON();
                var imgUrl = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                cardBody.querySelector( '.uds-pfg-img-id' ).value           = att.id;
                cardBody.querySelector( '.uds-pfg-img-url' ).value          = att.url;
                cardBody.querySelector( '.uds-pfg-image-preview' ).innerHTML = '<img src="' + imgUrl + '">';
                cardBody.querySelector( '.uds-pfg-remove-image' ).style.display = '';
                segnaModifica();
            } );
            frame.open();
            return;
        }

        // Rimuovi immagine
        if ( t.matches( '.uds-pfg-remove-image' ) ) {
            e.preventDefault();
            var cardBody2 = t.closest( '.uds-pfg-card-body' );
            cardBody2.querySelector( '.uds-pfg-image-preview' ).innerHTML = '';
            cardBody2.querySelector( '.uds-pfg-img-id' ).value            = '';
            cardBody2.querySelector( '.uds-pfg-img-url' ).value           = '';
            t.style.display = 'none';
            return;
        }

        // Aggiungi override
        if ( t.matches( '#uds-pfg-add-override' ) ) {
            var tmplOv = document.getElementById( 'tmpl-override-row' );
            var htmlOv = tmplOv.innerHTML.replace( /__OI__/g, overrideCounter++ );
            document.getElementById( 'uds-pfg-overrides-body' ).insertAdjacentHTML( 'beforeend', htmlOv );
            return;
        }

        // Rimuovi override
        if ( t.matches( '.uds-pfg-remove-override' ) ) {
            var titoloOv = t.closest( 'tr' ).querySelector( '.uds-pfg-ov-titolo' );
            titoloOv = titoloOv ? titoloOv.textContent.trim() : '';
            if ( titoloOv && ! confirm( 'Togliere l\'override di «' + titoloOv + '»?' ) ) return;
            t.closest( 'tr' ).remove();
            return;
        }

        // Riempi da…: cerca, sceglie un risultato, chiude i risultati
        if ( t.matches( '.uds-pfg-cerca-btn' ) ) {
            cerca( t.closest( CONTENITORI_RICERCA ) );
            return;
        }
        var risultato = t.closest( '.uds-pfg-risultato' );
        if ( risultato ) {
            var box  = risultato.closest( '.uds-pfg-risultati' );
            var riga = risultato.closest( '.uds-pfg-override-row' );
            if ( riga ) {
                scegliArticolo( riga, box.risultati[ risultato.dataset.i ] );
            } else {
                riempi( risultato.closest( '.uds-pfg-card' ), box.risultati[ risultato.dataset.i ] );
            }
            box.innerHTML = '';
            return;
        }
        if ( t.matches( '.uds-pfg-ov-cambia' ) ) {
            var ricerca = t.closest( '.uds-pfg-override-row' ).querySelector( '.uds-pfg-ov-ricerca' );
            ricerca.hidden = ! ricerca.hidden;
            if ( ! ricerca.hidden ) ricerca.querySelector( '.uds-pfg-cerca' ).focus();
            return;
        }
        if ( t.matches( '.uds-pfg-chiudi-risultati' ) ) {
            t.closest( '.uds-pfg-risultati' ).innerHTML = '';
            return;
        }

        // Sgancia: la card torna al link scritto a mano
        if ( t.matches( '.uds-pfg-sgancia' ) ) {
            sgancia( t.closest( '.uds-pfg-card-body' ) );
            return;
        }
    } );

    // ============================================================
    // RIEMPI DA… — cerca un contenuto o una categoria del sito e
    // riempie la card, agganciandola (il link poi si aggiorna da solo)
    // ============================================================

    var dati   = window.udsPfg || {};
    var attesa = null;

    // La ricerca sta nelle card dei gruppi e nelle righe degli override.
    var CONTENITORI_RICERCA = '.uds-pfg-card, .uds-pfg-override-row';

    // Override: l'articolo scelto dai risultati.
    function scegliArticolo( riga, r ) {
        var id = String( r.aggancio || '' ).replace( 'post:', '' );
        riga.querySelector( '.uds-pfg-ov-id' ).value = id;
        riga.querySelector( '.uds-pfg-ov-titolo' ).textContent = r.titolo;
        riga.querySelector( '.uds-pfg-ov-numero' ).textContent = 'n. ' + id + ( r.stato ? ' · ' + r.stato : '' );
        riga.querySelector( '.uds-pfg-ov-articolo' ).hidden = false;
        riga.querySelector( '.uds-pfg-ov-ricerca' ).hidden  = true;
        var vecchi = riga.querySelector( '.uds-pfg-avvisi' );
        if ( vecchi ) vecchi.remove();
        modificatoOverride = true;
    }

    function cerca( card ) {
        var box  = card.querySelector( '.uds-pfg-risultati' );
        var body = new FormData();
        body.append( 'action', 'uds_pfg_cerca' );
        body.append( '_ajax_nonce', dati.nonce || '' );
        body.append( 'fonte', card.querySelector( '.uds-pfg-fonte' ).value );
        body.append( 'q', card.querySelector( '.uds-pfg-cerca' ).value );
        box.innerHTML = '<p class="uds-pfg-cercando">Cerco…</p>';

        fetch( dati.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( risposta ) {
                if ( ! risposta || ! risposta.success ) throw new Error( 'ricerca' );
                mostraRisultati( box, risposta.data );
            } )
            .catch( function () {
                box.innerHTML = '<p class="uds-pfg-errore">La ricerca non ha funzionato: ricarica la pagina e riprova.</p>';
            } );
    }

    function elemento( tag, classe, testo ) {
        var el = document.createElement( tag );
        if ( classe ) el.className = classe;
        if ( testo ) el.textContent = testo;
        return el;
    }

    function mostraRisultati( box, risultati ) {
        box.innerHTML  = '';
        box.risultati  = risultati;
        if ( ! risultati.length ) {
            box.appendChild( elemento( 'p', 'uds-pfg-nessuno', 'Nessun risultato.' ) );
        }
        risultati.forEach( function ( r, i ) {
            var b = elemento( 'button', 'uds-pfg-risultato' );
            b.type      = 'button';
            b.dataset.i = i;
            if ( r.anteprima ) {
                var img = elemento( 'img' );
                img.src = r.anteprima;
                img.alt = '';
                b.appendChild( img );
            } else {
                b.appendChild( elemento( 'span', 'uds-pfg-senza-img', 'senza immagine' ) );
            }
            var testo = elemento( 'span', 'uds-pfg-ris-testo' );
            testo.appendChild( elemento( 'strong', '', r.titolo || '(senza titolo)' ) );
            var meta = [];
            if ( r.stato ) meta.push( r.stato );
            if ( r.conteggio !== null && r.conteggio !== undefined ) meta.push( r.conteggio + ( r.conteggio === 1 ? ' elemento' : ' elementi' ) );
            meta = meta.concat( r.note || [] );
            if ( meta.length ) testo.appendChild( elemento( 'span', 'uds-pfg-ris-meta', meta.join( ' · ' ) ) );
            testo.appendChild( elemento( 'span', 'uds-pfg-ris-link', r.link.replace( location.origin, '' ) ) );
            b.appendChild( testo );
            box.appendChild( b );
        } );
        var chiudi = elemento( 'button', 'button-link uds-pfg-chiudi-risultati', 'Chiudi i risultati' );
        chiudi.type = 'button';
        box.appendChild( chiudi );
    }

    function riempi( card, r ) {
        var body = card.querySelector( '.uds-pfg-card-body' );
        body.querySelector( '.uds-pfg-card-titolo' ).value = r.titolo;
        body.querySelector( '.uds-pfg-card-desc' ).value   = r.descrizione;
        body.querySelector( '.uds-pfg-link' ).value        = r.link;
        // L'immagine del contenuto scelto; se non ne ha, via quella di prima.
        body.querySelector( '.uds-pfg-img-id' ).value  = r.immagine_id || '';
        body.querySelector( '.uds-pfg-img-url' ).value = r.immagine_url || '';
        var anteprima = body.querySelector( '.uds-pfg-image-preview' );
        anteprima.innerHTML = '';
        if ( r.immagine_url ) {
            var img = elemento( 'img' );
            img.src = r.anteprima || r.immagine_url;
            anteprima.appendChild( img );
        }
        body.querySelector( '.uds-pfg-remove-image' ).style.display = r.immagine_url ? '' : 'none';
        body.querySelector( '.uds-pfg-aggancio' ).value = r.aggancio;
        var info = body.querySelector( '.uds-pfg-aggancio-info' );
        info.querySelector( '.uds-pfg-aggancio-nome' ).textContent = r.nome_aggancio;
        info.hidden = false;
        card.querySelector( '.uds-pfg-card-label' ).textContent = r.titolo || 'Nuova card';
    }

    function sgancia( body ) {
        body.querySelector( '.uds-pfg-aggancio' ).value = '';
        body.querySelector( '.uds-pfg-aggancio-info' ).hidden = true;
    }

    // Invio nel campo di ricerca: cerca, non salva il modulo.
    document.addEventListener( 'keydown', function ( e ) {
        if ( e.key === 'Enter' && e.target.matches( '.uds-pfg-cerca' ) ) {
            e.preventDefault();
            clearTimeout( attesa );
            cerca( e.target.closest( CONTENITORI_RICERCA ) );
        }
    } );

    document.addEventListener( 'change', function ( e ) {
        if ( e.target.matches( '.uds-pfg-fonte' ) ) cerca( e.target.closest( CONTENITORI_RICERCA ) );
    } );

    // ============================================================
    // TRASCINAMENTO — ☰ riordina i gruppi e le card (anche da un
    // gruppo all'altro). L'ordine vale con "Salva gruppi".
    // ============================================================

    var trascinato = null;

    // Si trascina solo tenendo il ☰: così nei campi si può ancora selezionare il testo.
    document.addEventListener( 'mousedown', function ( e ) {
        var maniglia = e.target.closest( '.uds-pfg-drag-handle' );
        if ( ! maniglia ) return;
        var el = maniglia.closest( '.uds-pfg-card' ) || maniglia.closest( '.uds-pfg-group' );
        if ( el ) el.setAttribute( 'draggable', 'true' );
    } );

    document.addEventListener( 'mouseup', function () {
        if ( trascinato ) return;
        document.querySelectorAll( '.uds-pfg-wrap [draggable="true"]' ).forEach( function ( el ) {
            el.removeAttribute( 'draggable' );
        } );
    } );

    document.addEventListener( 'dragstart', function ( e ) {
        var el = e.target;
        if ( ! el.matches || ! el.matches( '.uds-pfg-group[draggable="true"], .uds-pfg-card[draggable="true"]' ) ) return;
        e.stopPropagation();
        trascinato = el;
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData( 'text/plain', '' ); } catch ( err ) {}
        // Si vede trascinare solo l'intestazione, non tutto il gruppo aperto.
        var testa = el.querySelector( '.uds-pfg-group-header, .uds-pfg-card-header' );
        if ( testa && e.dataTransfer.setDragImage ) e.dataTransfer.setDragImage( testa, 20, 15 );
        // Dopo: cambiare la pagina dentro dragstart interrompe il trascinamento.
        setTimeout( function () {
            el.classList.add( 'is-trascinato' );
            document.querySelectorAll( '.uds-pfg-cards-list' ).forEach( function ( lista ) {
                lista.classList.toggle( 'uds-pfg-lista-vuota', ! lista.querySelector( '.uds-pfg-card' ) );
            } );
            document.body.classList.add( el.classList.contains( 'uds-pfg-card' ) ? 'uds-pfg-trascina-card' : 'uds-pfg-trascina-gruppo' );
        }, 0 );
    } );

    // Durante il trascinamento il browser non scorre con la rotella: la pagina
    // scorre da sola vicino ai bordi della finestra, e la rotella si gira a mano.
    document.addEventListener( 'wheel', function ( e ) {
        if ( trascinato ) window.scrollBy( 0, e.deltaY );
    }, { passive: true } );

    document.addEventListener( 'dragover', function ( e ) {
        if ( ! trascinato ) return;
        var bordo = 80;
        if ( e.clientY < bordo ) {
            window.scrollBy( 0, -Math.ceil( ( bordo - e.clientY ) / 4 ) );
        } else if ( e.clientY > window.innerHeight - bordo ) {
            window.scrollBy( 0, Math.ceil( ( e.clientY - ( window.innerHeight - bordo ) ) / 4 ) );
        }
        var tipo  = trascinato.classList.contains( 'uds-pfg-card' ) ? '.uds-pfg-card' : '.uds-pfg-group';
        var sopra = e.target.closest( tipo );

        // Una card in un gruppo che non ne ha.
        if ( ! sopra && tipo === '.uds-pfg-card' ) {
            var lista = e.target.closest( '.uds-pfg-cards-list' );
            if ( lista && ! lista.querySelector( '.uds-pfg-card' ) ) {
                e.preventDefault();
                lista.appendChild( trascinato );
            }
            return;
        }
        if ( ! sopra || sopra === trascinato ) return;

        e.preventDefault();
        var r    = sopra.getBoundingClientRect();
        var dopo = e.clientY > r.top + r.height / 2;
        if ( dopo && sopra.nextElementSibling !== trascinato ) {
            sopra.parentNode.insertBefore( trascinato, sopra.nextElementSibling );
        } else if ( ! dopo && sopra.previousElementSibling !== trascinato ) {
            sopra.parentNode.insertBefore( trascinato, sopra );
        }
    } );

    document.addEventListener( 'drop', function ( e ) {
        if ( trascinato ) e.preventDefault();
    } );

    document.addEventListener( 'dragend', function () {
        if ( ! trascinato ) return;
        trascinato.classList.remove( 'is-trascinato' );
        trascinato.removeAttribute( 'draggable' );
        trascinato = null;
        document.body.classList.remove( 'uds-pfg-trascina-card', 'uds-pfg-trascina-gruppo' );
        document.querySelectorAll( '.uds-pfg-lista-vuota' ).forEach( function ( lista ) {
            lista.classList.remove( 'uds-pfg-lista-vuota' );
        } );
    } );

    // Al salvataggio i campi si rinumerano nell'ordine in cui stanno nella
    // pagina: così vale l'ordine trascinato, e due card aggiunte dopo una
    // rimozione non finiscono con lo stesso numero.
    var moduloGruppi = document.getElementById( 'uds-pfg-groups-form' );
    if ( moduloGruppi ) {
        moduloGruppi.addEventListener( 'submit', function () {
            modificato = false;
            gruppi().forEach( function ( g, gi ) {
                g.querySelectorAll( '[name^="groups["]' ).forEach( function ( campo ) {
                    campo.name = campo.name.replace( /^groups\[[^\]]*\]/, 'groups[' + gi + ']' );
                } );
                g.querySelectorAll( '.uds-pfg-card' ).forEach( function ( c, ci ) {
                    c.querySelectorAll( '[name*="[cards]["]' ).forEach( function ( campo ) {
                        campo.name = campo.name.replace( /\[cards\]\[[^\]]*\]/, '[cards][' + ci + ']' );
                    } );
                } );
            } );
        } );
    }

    // ============================================================
    // INPUT — aggiorna label in tempo reale
    // ============================================================

    document.addEventListener( 'input', function ( e ) {
        var t = e.target;

        if ( t.matches( '.uds-pfg-group-nome' ) ) {
            t.closest( '.uds-pfg-group' ).querySelector( '.uds-pfg-group-label' ).textContent =
                t.value || 'Nuovo gruppo';
            return;
        }

        if ( t.matches( '.uds-pfg-card-titolo' ) ) {
            t.closest( '.uds-pfg-card' ).querySelector( '.uds-pfg-card-label' ).textContent =
                t.value || 'Nuova card';
            return;
        }

        // Un link scritto a mano sgancia la card: vale quello.
        if ( t.matches( '.uds-pfg-link' ) ) {
            sgancia( t.closest( '.uds-pfg-card-body' ) );
            return;
        }

        // Ricerca mentre si scrive, con una piccola attesa.
        if ( t.matches( '.uds-pfg-cerca' ) ) {
            clearTimeout( attesa );
            var contenitore = t.closest( CONTENITORI_RICERCA );
            attesa = setTimeout( function () { cerca( contenitore ); }, 400 );
            return;
        }
    } );

    // ============================================================
    // MODIFICHE NON SALVATE — il browser chiede conferma prima di
    // lasciare la pagina (la ricerca non conta: non cambia i gruppi)
    // ============================================================

    var modificato = false;

    function segnaModifica() { modificato = true; }

    if ( moduloGruppi ) {
        [ 'input', 'change' ].forEach( function ( evento ) {
            moduloGruppi.addEventListener( evento, function ( e ) {
                if ( ! e.target.matches( '.uds-pfg-cerca, .uds-pfg-fonte' ) ) segnaModifica();
            } );
        } );
        moduloGruppi.addEventListener( 'click', function ( e ) {
            if ( e.target.closest( '.uds-pfg-add-card, .uds-pfg-remove-card, #uds-pfg-add-group, .uds-pfg-remove-group, .uds-pfg-remove-image, .uds-pfg-risultato, .uds-pfg-sgancia' ) ) {
                segnaModifica();
            }
        } );
        moduloGruppi.addEventListener( 'dragend', segnaModifica );
    }

    // Anche nella scheda degli override: un articolo scelto o un gruppo cambiato.
    var modificatoOverride = false;
    var moduloOverride = document.getElementById( 'uds-pfg-overrides-body' );
    if ( moduloOverride ) {
        moduloOverride = moduloOverride.closest( 'form' );
        moduloOverride.addEventListener( 'change', function ( e ) {
            if ( ! e.target.matches( '.uds-pfg-cerca, .uds-pfg-fonte' ) ) modificatoOverride = true;
        } );
        moduloOverride.addEventListener( 'click', function ( e ) {
            if ( e.target.closest( '#uds-pfg-add-override, .uds-pfg-remove-override' ) ) modificatoOverride = true;
        } );
        moduloOverride.addEventListener( 'submit', function () { modificatoOverride = false; } );
    }

    window.addEventListener( 'beforeunload', function ( e ) {
        if ( ! modificato && ! modificatoOverride ) return;
        e.preventDefault();
        e.returnValue = '';
    } );

} );
