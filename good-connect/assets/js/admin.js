/* global GoodConnect, jQuery */
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

    function setBusy( $btn, busy ) {
        if ( busy ) {
            $btn.prop( 'disabled', true ).text( GoodConnect.strings.saving );
        } else {
            $btn.prop( 'disabled', false ).text(
                $btn.hasClass( 'goodconnect-save-woo' ) || $btn.attr( 'id' ) === 'goodconnect-save-woo'
                    ? 'Save'
                    : 'Save Mapping'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Gravity Forms — save mapping
    // -------------------------------------------------------------------------

    $( document ).on( 'click', '.goodconnect-save-gf', function () {
        var $btn    = $( this );
        var formId  = $btn.data( 'form-id' );
        var $card   = $btn.closest( '.goodconnect-card' );
        var mapping = {};

        $card.find( '.goodconnect-gf-field-select' ).each( function () {
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
            form_id: formId,
            mapping: mapping,
        } )
        .done( function ( res ) {
            showStatus( $btn, ! res.success );
        } )
        .fail( function () {
            showStatus( $btn, true );
        } )
        .always( function () {
            setBusy( $btn, false );
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
            setBusy( $btn, false );
        } );
    } );

    // -------------------------------------------------------------------------
    // WooCommerce — save settings
    // -------------------------------------------------------------------------

    $( '#goodconnect-save-woo' ).on( 'click', function () {
        var $btn    = $( this );
        var enabled = $( '#gc_woo_enabled' ).is( ':checked' ) ? 1 : 0;

        setBusy( $btn, true );

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
            setBusy( $btn, false );
        } );
    } );

} )( jQuery );
