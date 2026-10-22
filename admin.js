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

    ( function () {
        var chiusi = [];
        try { chiusi = JSON.parse( sessionStorage.getItem( CHIAVE_CHIUSI ) || '[]' ); } catch ( err ) {}
        gruppi().forEach( function ( g ) {
            if ( chiusi.indexOf( idGruppo( g ) ) !== -1 ) apriGruppo( g, false );
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
        if ( intestazione && ( t.matches( '.uds-pfg-toggle-group' ) || ! t.closest( 'button, a, input, select, textarea' ) ) ) {
            var gruppo = intestazione.closest( '.uds-pfg-group' );
            apriGruppo( gruppo, gruppo.classList.contains( 'is-chiuso' ) );
            ricordaGruppi();
            return;
        }

        // Toggle card (clic sull'header, ma non sui bottoni interni)
        if ( t.matches( '.uds-pfg-card-header' ) ) {
            t.nextElementSibling.classList.toggle( 'is-hidden' );
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
            t.closest( 'tr' ).remove();
            return;
        }
    } );

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
    } );

} );
