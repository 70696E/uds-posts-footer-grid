/* global jQuery, wp */
jQuery( function( $ ) {

    // ============================================================
    // TOGGLE CORPO GRUPPO / CARD (accordion)
    // ============================================================

    $( document ).on( 'click', '.uds-pfg-toggle-group, .uds-pfg-group-header strong', function( e ) {
        e.stopPropagation();
        var $group = $( this ).closest( '.uds-pfg-group' );
        $group.find( '.uds-pfg-group-body' ).slideToggle( 150 );
    });

    $( document ).on( 'click', '.uds-pfg-card-header', function() {
        $( this ).siblings( '.uds-pfg-card-body' ).slideToggle( 150 );
    });

    // ============================================================
    // AGGIORNA LABEL GRUPPO quando si modifica il nome
    // ============================================================

    $( document ).on( 'input', '.uds-pfg-group-nome', function() {
        var val = $( this ).val() || 'Nuovo gruppo';
        $( this ).closest( '.uds-pfg-group' ).find( '.uds-pfg-group-label' ).text( val );
    });

    $( document ).on( 'input', '.uds-pfg-card-titolo', function() {
        var val = $( this ).val() || 'Nuova card';
        $( this ).closest( '.uds-pfg-card' ).find( '.uds-pfg-card-label' ).text( val );
    });

    // ============================================================
    // AGGIUNGI GRUPPO
    // ============================================================

    var groupCounter = $( '.uds-pfg-group' ).length;

    $( '#uds-pfg-add-group' ).on( 'click', function() {
        var tmpl = $( '#tmpl-uds-pfg-group' ).html();
        // Sostituisce il placeholder indice con il contatore corrente
        tmpl = tmpl.replace( /__GI__/g, groupCounter );
        groupCounter++;
        $( '#uds-pfg-groups-list' ).append( tmpl );
    });

    // ============================================================
    // RIMUOVI GRUPPO
    // ============================================================

    $( document ).on( 'click', '.uds-pfg-remove-group', function( e ) {
        e.stopPropagation();
        if ( ! confirm( 'Eliminare questo gruppo?' ) ) return;
        $( this ).closest( '.uds-pfg-group' ).remove();
    });

    // ============================================================
    // AGGIUNGI CARD
    // ============================================================

    $( document ).on( 'click', '.uds-pfg-add-card', function() {
        var $group   = $( this ).closest( '.uds-pfg-group' );
        var gi       = $group.data( 'gi' );
        var ci       = $group.find( '.uds-pfg-card' ).length;
        var tmpl     = $( '#tmpl-uds-pfg-card' ).html();

        tmpl = tmpl.replace( /__GI__/g, gi ).replace( /__CI__/g, ci );
        $group.find( '.uds-pfg-cards-list' ).append( tmpl );
    });

    // ============================================================
    // RIMUOVI CARD
    // ============================================================

    $( document ).on( 'click', '.uds-pfg-remove-card', function( e ) {
        e.stopPropagation();
        $( this ).closest( '.uds-pfg-card' ).remove();
    });

    // ============================================================
    // SELEZIONE IMMAGINE — Media Library
    // ============================================================

    $( document ).on( 'click', '.uds-pfg-select-image', function( e ) {
        e.preventDefault();
        var $btn     = $( this );
        var $card    = $btn.closest( '.uds-pfg-card-body' );
        var $preview = $card.find( '.uds-pfg-image-preview' );
        var $id      = $card.find( '.uds-pfg-img-id' );
        var $url     = $card.find( '.uds-pfg-img-url' );
        var $remove  = $card.find( '.uds-pfg-remove-image' );

        var frame = wp.media({
            title: 'Seleziona immagine',
            button: { text: 'Usa questa immagine' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on( 'select', function() {
            var attachment = frame.state().get( 'selection' ).first().toJSON();
            // Usa la dimensione medium se disponibile, altrimenti originale
            var imgUrl = ( attachment.sizes && attachment.sizes.medium )
                ? attachment.sizes.medium.url
                : attachment.url;

            $id.val( attachment.id );
            $url.val( attachment.url ); // salviamo l'URL originale
            $preview.html( '<img src="' + imgUrl + '">' );
            $remove.show();
        });

        frame.open();
    });

    // ============================================================
    // RIMUOVI IMMAGINE
    // ============================================================

    $( document ).on( 'click', '.uds-pfg-remove-image', function( e ) {
        e.preventDefault();
        var $card = $( this ).closest( '.uds-pfg-card-body' );
        $card.find( '.uds-pfg-image-preview' ).empty();
        $card.find( '.uds-pfg-img-id' ).val( '' );
        $card.find( '.uds-pfg-img-url' ).val( '' );
        $( this ).hide();
    });

    // ============================================================
    // OVERRIDE — AGGIUNGI RIGA
    // ============================================================

    var overrideCounter = $( '.uds-pfg-override-row' ).length;

    $( '#uds-pfg-add-override' ).on( 'click', function() {
        var tmpl = $( '#tmpl-override-row' ).innerHTML ||
                   document.getElementById( 'tmpl-override-row' ).innerHTML;
        tmpl = tmpl.replace( /__OI__/g, overrideCounter );
        overrideCounter++;
        $( '#uds-pfg-overrides-body' ).append( tmpl );
    });

    // ============================================================
    // OVERRIDE — RIMUOVI RIGA
    // ============================================================

    $( document ).on( 'click', '.uds-pfg-remove-override', function() {
        $( this ).closest( 'tr' ).remove();
    });

});
