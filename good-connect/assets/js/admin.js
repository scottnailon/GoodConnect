/* global GoodConnect, GoodConnectGF, jQuery */
( function ( $ ) {
    'use strict';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function showStatus( $btn, isError ) {
        var $status = $btn.siblings( '.goodconnect-save-status' );
        $status
            .text( isError ? GoodConnect.strings.error : GoodConnect.strings.saved )
            .removeClass( 'error visible' )
            .addClass( isError ? 'error visible' : 'visible' );
        setTimeout( function () {
            $status.removeClass( 'visible' );
        }, 2500 );
    }

    function setBusy( $btn, busy, label ) {
        $btn.prop( 'disabled', busy ).text( busy ? GoodConnect.strings.saving : ( label || 'Save Mapping' ) );
    }

    // -------------------------------------------------------------------------
    // Gravity Forms — form selector + dynamic mapper
    // -------------------------------------------------------------------------

    var currentFormId = null;

    function buildGFMapper( formId ) {
        if ( typeof GoodConnectGF === 'undefined' ) return;

        var form = null;
        for ( var i = 0; i < GoodConnectGF.forms.length; i++ ) {
            if ( GoodConnectGF.forms[ i ].id === parseInt( formId, 10 ) ) {
                form = GoodConnectGF.forms[ i ];
                break;
            }
        }
        if ( ! form ) return;

        currentFormId = form.id;

        $( '#goodconnect-gf-form-title' ).text( form.title + ' (Form ID: ' + form.id + ')' );

        // Build mapper rows — keep the header, replace rows.
        var $mapper  = $( '#goodconnect-gf-mapper' );
        $mapper.find( '.goodconnect-mapper-row' ).remove();

        var ghlFields = GoodConnectGF.ghlFields;
        $.each( ghlFields, function ( ghlKey, ghlLabel ) {
            var savedValue = form.mapping[ ghlKey ] || '';

            var $options = $( '<option>' ).val( '' ).text( '— Not mapped —' );
            var $select  = $( '<select>' )
                .addClass( 'goodconnect-gf-field-select' )
                .attr( 'data-ghl-field', ghlKey )
                .append( $options );

            $.each( form.fields, function ( idx, field ) {
                var $opt = $( '<option>' )
                    .val( field.id )
                    .text( field.label + ' (ID: ' + field.id + ')' );
                if ( savedValue === field.id ) {
                    $opt.prop( 'selected', true );
                }
                $select.append( $opt );
            } );

            var $row = $( '<div>' ).addClass( 'goodconnect-mapper-row' );
            $row.append( $( '<label>' ).text( ghlLabel ) );
            $row.append( $select );
            $mapper.append( $row );
        } );

        $( '#goodconnect-gf-mapper-wrap' ).show();
    }

    $( '#goodconnect-gf-form-select' ).on( 'change', function () {
        var formId = $( this ).val();
        if ( ! formId ) {
            $( '#goodconnect-gf-mapper-wrap' ).hide();
            currentFormId = null;
            return;
        }
        buildGFMapper( formId );
    } );

    $( '#goodconnect-save-gf' ).on( 'click', function () {
        if ( ! currentFormId ) return;

        var $btn    = $( this );
        var mapping = {};

        $( '#goodconnect-gf-mapper .goodconnect-gf-field-select' ).each( function () {
            var ghlField = $( this ).data( 'ghl-field' );
            var gfField  = $( this ).val();
            if ( gfField ) {
                mapping[ ghlField ] = gfField;
            }
        } );

        setBusy( $btn, true );

        $.post( GoodConnect.ajaxurl, {
            action:  'goodconnect_save_gf_mapping',
            nonce:   GoodConnect.nonce,
            form_id: currentFormId,
            mapping: mapping,
        } )
        .done( function ( res ) {
            // Update local cache so switching away and back reflects saved state.
            if ( res.success && typeof GoodConnectGF !== 'undefined' ) {
                for ( var i = 0; i < GoodConnectGF.forms.length; i++ ) {
                    if ( GoodConnectGF.forms[ i ].id === currentFormId ) {
                        GoodConnectGF.forms[ i ].mapping = mapping;
                        break;
                    }
                }
            }
            showStatus( $btn, ! res.success );
        } )
        .fail( function () {
            showStatus( $btn, true );
        } )
        .always( function () {
            setBusy( $btn, false, 'Save Mapping' );
        } );
    } );

    // -------------------------------------------------------------------------
    // Elementor — add / remove form cards
    // -------------------------------------------------------------------------

    $( document ).on( 'click', '.goodconnect-add-elementor-form', function () {
        var template = document.getElementById( 'goodconnect-elementor-card-template' );
        var clone    = document.importNode( template.content, true );
        $( '#goodconnect-elementor-forms' ).append( clone );
    } );

    $( document ).on( 'click', '.goodconnect-remove-elementor-form', function () {
        if ( confirm( 'Remove this form mapping?' ) ) {
            $( this ).closest( '.goodconnect-elementor-card' ).remove();
        }
    } );

    // -------------------------------------------------------------------------
    // Elementor — save mapping
    // -------------------------------------------------------------------------

    $( document ).on( 'click', '.goodconnect-save-elementor', function () {
        var $btn      = $( this );
        var $card     = $btn.closest( '.goodconnect-elementor-card' );
        var formName  = $card.find( '.goodconnect-elementor-form-name' ).val().trim();
        var mapping   = {};

        if ( ! formName ) {
            alert( 'Please enter the form name.' );
            return;
        }

        $card.find( '.goodconnect-elementor-field-id' ).each( function () {
            var ghlField = $( this ).data( 'ghl-field' );
            var fieldId  = $( this ).val().trim();
            if ( fieldId ) {
                mapping[ ghlField ] = fieldId;
            }
        } );

        setBusy( $btn, true );

        $.post( GoodConnect.ajaxurl, {
            action:    'goodconnect_save_elementor_mapping',
            nonce:     GoodConnect.nonce,
            form_name: formName,
            mapping:   mapping,
        } )
        .done( function ( res ) {
            showStatus( $btn, ! res.success );
        } )
        .fail( function () {
            showStatus( $btn, true );
        } )
        .always( function () {
            setBusy( $btn, false, 'Save Mapping' );
        } );
    } );

    // -------------------------------------------------------------------------
    // WooCommerce — save settings
    // -------------------------------------------------------------------------

    $( '#goodconnect-save-woo' ).on( 'click', function () {
        var $btn    = $( this );
        var enabled = $( '#gc_woo_enabled' ).is( ':checked' ) ? 1 : 0;

        setBusy( $btn, true, 'Save' );

        $.post( GoodConnect.ajaxurl, {
            action:      'goodconnect_save_woo_settings',
            nonce:       GoodConnect.nonce,
            woo_enabled: enabled,
        } )
        .done( function ( res ) {
            showStatus( $btn, ! res.success );
        } )
        .fail( function () {
            showStatus( $btn, true );
        } )
        .always( function () {
            setBusy( $btn, false, 'Save' );
        } );
    } );

} )( jQuery );
