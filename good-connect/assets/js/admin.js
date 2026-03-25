/* global GoodConnect, GoodConnectGF, jQuery */
( function ( $ ) {
    'use strict';

    function showStatus( $btn, isError ) {
        var $status = $btn.siblings( '.goodconnect-save-status' );
        $status.text( isError ? GoodConnect.strings.error : GoodConnect.strings.saved )
               .removeClass( 'error visible' )
               .addClass( isError ? 'error visible' : 'visible' );
        setTimeout( function () { $status.removeClass( 'visible' ); }, 2500 );
    }

    function setBusy( $btn, busy, label ) {
        $btn.prop( 'disabled', busy ).text( busy ? GoodConnect.strings.saving : label );
    }

    function makeFieldSelect( fields, selectedId, cssClass, ghlField ) {
        var $sel = $( '<select>' ).addClass( cssClass );
        if ( ghlField ) $sel.attr( 'data-ghl-field', ghlField );
        $sel.append( $( '<option>' ).val( '' ).text( '— Not mapped —' ) );
        $.each( fields, function ( i, f ) {
            $sel.append(
                $( '<option>' ).val( f.id ).text( f.label + ' (ID: ' + f.id + ')' )
                               .prop( 'selected', f.id === String( selectedId ) )
            );
        } );
        return $sel;
    }

    // =========================================================================
    // ACCOUNTS
    // =========================================================================

    $( '#goodconnect-add-account' ).on( 'click', function () {
        var tpl   = document.getElementById( 'goodconnect-account-row-template' );
        var clone = $( document.importNode( tpl.content, true ) );
        var newId = 'account_new_' + Date.now();
        clone.find( '.goodconnect-account-row' ).attr( 'data-id', newId );
        clone.find( '.gc-account-default' ).val( newId );
        $( '#goodconnect-accounts-list' ).append( clone );
    } );

    $( document ).on( 'click', '.goodconnect-remove-account', function () {
        if ( ! confirm( GoodConnect.strings.confirmDelete ) ) return;
        $( this ).closest( '.goodconnect-account-row' ).remove();
    } );

    $( '#goodconnect-save-accounts' ).on( 'click', function () {
        var $btn     = $( this );
        var accounts = [];
        var defaultId = $( 'input[name="gc_default_account"]:checked' ).val();

        $( '#goodconnect-accounts-list .goodconnect-account-row' ).each( function () {
            var id = $( this ).data( 'id' ) || '';
            accounts.push( {
                id:          id,
                label:       $( this ).find( '.gc-account-label' ).val().trim(),
                api_key:     $( this ).find( '.gc-account-apikey' ).val().trim(),
                location_id: $( this ).find( '.gc-account-locationid' ).val().trim(),
                is_default:  id === defaultId ? 1 : 0,
            } );
        } );

        setBusy( $btn, true, 'Save Accounts' );
        $.post( GoodConnect.ajaxurl, {
            action:   'goodconnect_save_accounts',
            nonce:    GoodConnect.nonce,
            accounts: accounts,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                GoodConnect.accounts = res.data.accounts;
                // Update real IDs on new rows.
                $.each( res.data.accounts, function ( i, acc ) {
                    $( '#goodconnect-accounts-list .goodconnect-account-row' ).eq( i )
                        .attr( 'data-id', acc.id )
                        .find( '.gc-account-default' ).val( acc.id );
                } );
            }
            showStatus( $btn, ! res.success );
        } )
        .fail( function () { showStatus( $btn, true ); } )
        .always( function () { setBusy( $btn, false, 'Save Accounts' ); } );
    } );

    // =========================================================================
    // GRAVITY FORMS
    // =========================================================================

    var currentFormId   = null;
    var currentFormData = null;

    function populateAccountSelect( $select, selectedId ) {
        $select.empty().append( $( '<option>' ).val( '' ).text( '— Use default account —' ) );
        $.each( GoodConnect.accounts, function ( i, acc ) {
            $select.append(
                $( '<option>' ).val( acc.id ).text( acc.label )
                               .prop( 'selected', acc.id === selectedId )
            );
        } );
    }

    function buildGFMapper( formId ) {
        if ( typeof GoodConnectGF === 'undefined' ) return;

        var form = null;
        $.each( GoodConnectGF.forms, function ( i, f ) {
            if ( f.id === parseInt( formId, 10 ) ) { form = f; return false; }
        } );
        if ( ! form ) return;

        currentFormId   = form.id;
        currentFormData = form;

        var config = form.config || {};

        $( '#goodconnect-gf-form-title' ).text( form.title + ' (Form ID: ' + form.id + ')' );

        // Account selector.
        populateAccountSelect( $( '#goodconnect-gf-account' ), config.account_id || '' );

        // Standard field mapper.
        var $mapper = $( '#goodconnect-gf-mapper' );
        $mapper.find( '.goodconnect-mapper-row' ).remove();
        $.each( GoodConnectGF.ghlFields, function ( ghlKey, ghlLabel ) {
            var savedVal = ( config.field_map || {} )[ ghlKey ] || '';
            var $row = $( '<div>' ).addClass( 'goodconnect-mapper-row' );
            $row.append( $( '<label>' ).text( ghlLabel ) );
            $row.append( makeFieldSelect( form.fields, savedVal, 'goodconnect-gf-field-select', ghlKey ) );
            $mapper.append( $row );
        } );

        // Custom fields.
        var $cfWrap = $( '#goodconnect-gf-custom-fields' ).empty();
        $.each( config.custom_fields || [], function ( i, row ) {
            $cfWrap.append( buildCustomFieldRow( row.ghl_key, row.gf_field_id, form.fields ) );
        } );

        // Static tags.
        $( '#goodconnect-gf-static-tags' ).val( ( config.static_tags || [] ).join( ', ' ) );

        // Dynamic tags.
        var $dtWrap = $( '#goodconnect-gf-dynamic-tags' ).empty();
        $.each( config.dynamic_tags || [], function ( i, row ) {
            $dtWrap.append( buildDynamicTagRow( row.gf_field_id, form.fields ) );
        } );

        $( '#goodconnect-gf-mapper-wrap' ).show();
    }

    // Cache of fetched GHL custom fields keyed by account ID ('default' when no account selected).
    var ghlCustomFieldsCache = {};

    /**
     * Fetch GHL custom fields for the given account ID, with caching.
     * Calls callback( fields ) on success, where fields = [{id, name, fieldKey}, ...].
     */
    function loadGHLCustomFields( accountId, callback ) {
        var cacheKey = accountId || 'default';
        if ( ghlCustomFieldsCache[ cacheKey ] ) {
            callback( ghlCustomFieldsCache[ cacheKey ] );
            return;
        }
        $.post( GoodConnect.ajaxurl, {
            action:     'goodconnect_fetch_ghl_custom_fields',
            nonce:      GoodConnect.nonce,
            account_id: accountId || '',
        } )
        .done( function ( res ) {
            if ( res.success && res.data ) {
                ghlCustomFieldsCache[ cacheKey ] = res.data;
                callback( res.data );
            } else {
                alert( ( res.data && typeof res.data === 'string' ? res.data : 'Could not load GHL custom fields. Check your API key and Location ID.' ) );
            }
        } )
        .fail( function ( xhr ) {
            alert( 'Request failed (HTTP ' + xhr.status + '). Check your browser console for details.' );
        } );
    }

    /**
     * Build a <select> element populated with GHL custom fields.
     * selectedId should match field.id or field.fieldKey.
     */
    function makeGHLFieldSelect( ghlFields, selectedId ) {
        var $sel = $( '<select>' ).addClass( 'gc-custom-ghl-key-select' );
        $sel.append( $( '<option>' ).val( '' ).text( '— Select GHL field —' ) );
        $.each( ghlFields, function ( i, f ) {
            var val = f.id || f.fieldKey || '';
            $sel.append(
                $( '<option>' ).val( val ).text( f.name )
                               .prop( 'selected', val === selectedId || f.fieldKey === selectedId )
            );
        } );
        return $sel;
    }

    /**
     * Update all gc-custom-ghl-key-select dropdowns within $container to show the loaded fields.
     */
    function populateGHLSelects( $container, ghlFields ) {
        $container.find( 'select.gc-custom-ghl-key-select' ).each( function () {
            var $sel     = $( this );
            var current  = $sel.val() || $sel.find( 'option:first' ).next().val() || '';
            var $newSel  = makeGHLFieldSelect( ghlFields, current );
            $newSel.addClass( $sel.attr( 'class' ) );
            $sel.replaceWith( $newSel );
        } );
    }

    function buildCustomFieldRow( ghlKey, gfFieldId, fields, ghlFields ) {
        var $row = $( '<div>' ).addClass( 'goodconnect-custom-field-row' );
        if ( ghlFields && ghlFields.length ) {
            $row.append( makeGHLFieldSelect( ghlFields, ghlKey ) );
        } else {
            // Fallback to text input when GHL fields haven't been loaded yet.
            $row.append( $( '<input type="text">' ).addClass( 'gc-custom-ghl-key' ).attr( 'placeholder', 'GHL field key' ).val( ghlKey || '' ) );
        }
        $row.append( makeFieldSelect( fields, gfFieldId, 'gc-custom-gf-field', '' ) );
        $row.append( $( '<button type="button">' ).addClass( 'button goodconnect-remove-custom-field' ).text( '\u2715' ) );
        return $row;
    }

    function buildDynamicTagRow( gfFieldId, fields ) {
        var $row = $( '<div>' ).addClass( 'goodconnect-dynamic-tag-row' );
        $row.append( $( '<span>' ).text( 'Tag value from: ' ) );
        $row.append( makeFieldSelect( fields, gfFieldId, 'gc-dynamic-tag-field', '' ) );
        $row.append( $( '<button type="button">' ).addClass( 'button goodconnect-remove-dynamic-tag' ).text( '\u2715' ) );
        return $row;
    }

    $( '#goodconnect-gf-form-select' ).on( 'change', function () {
        var formId = $( this ).val();
        if ( ! formId ) { $( '#goodconnect-gf-mapper-wrap' ).hide(); currentFormId = null; return; }
        buildGFMapper( formId );
    } );

    $( document ).on( 'click', '.goodconnect-add-custom-field', function () {
        if ( ! currentFormData ) return;
        var ghlFields = $( '#goodconnect-gf-custom-fields-wrap' ).data( 'ghl-fields' ) || [];
        $( '#goodconnect-gf-custom-fields' ).append( buildCustomFieldRow( '', '', currentFormData.fields, ghlFields ) );
    } );

    $( document ).on( 'click', '.goodconnect-remove-custom-field', function () {
        $( this ).closest( '.goodconnect-custom-field-row' ).remove();
    } );

    $( document ).on( 'click', '.goodconnect-add-dynamic-tag', function () {
        if ( ! currentFormData ) return;
        $( '#goodconnect-gf-dynamic-tags' ).append( buildDynamicTagRow( '', currentFormData.fields ) );
    } );

    $( document ).on( 'click', '.goodconnect-remove-dynamic-tag', function () {
        $( this ).closest( '.goodconnect-dynamic-tag-row' ).remove();
    } );

    // =========================================================================
    // LOAD FROM GHL — shared handler for all tabs
    // =========================================================================

    $( document ).on( 'click', '.goodconnect-load-ghl-fields', function () {
        var $btn     = $( this );
        var $card    = $btn.closest( '.goodconnect-card, .goodconnect-cf7-card, .goodconnect-elementor-card' );
        var $status  = $btn.siblings( '.goodconnect-ghl-fields-status' );
        var target   = $btn.data( 'target' ); // 'gf', 'elementor', 'cf7'

        // Determine account ID from the nearest account selector in the same card.
        var accountId = '';
        if ( target === 'gf' ) {
            accountId = $( '#goodconnect-gf-account' ).val();
        } else {
            accountId = $card.find( 'select[class*="account"]' ).val() || '';
        }

        $btn.prop( 'disabled', true );
        $status.text( 'Loading…' );

        loadGHLCustomFields( accountId, function ( fields ) {
            $btn.prop( 'disabled', false );
            $status.text( fields.length + ' fields loaded' );
            setTimeout( function () { $status.text( '' ); }, 3000 );

            // Populate existing selects within the relevant container.
            if ( target === 'gf' ) {
                var $gfContainer = $( '#goodconnect-gf-custom-fields' );
                populateGHLSelects( $gfContainer, fields );
                // If no rows exist yet, add one so the user can see the dropdown.
                if ( $gfContainer.find( '.goodconnect-custom-field-row' ).length === 0 && currentFormData ) {
                    $gfContainer.append( buildCustomFieldRow( '', '', currentFormData.fields, fields ) );
                }
                // Store on the wrap for use when adding new rows.
                $( '#goodconnect-gf-custom-fields-wrap' ).data( 'ghl-fields', fields );
            } else if ( target === 'elementor' ) {
                populateGHLSelects( $card.find( '.goodconnect-elementor-custom-fields' ), fields );
                $card.find( '.goodconnect-elementor-custom-fields-wrap' ).data( 'ghl-fields', fields );
            } else if ( target === 'cf7' ) {
                populateGHLSelects( $card.find( '.goodconnect-cf7-custom-fields' ), fields );
                $card.find( '.goodconnect-cf7-custom-fields-wrap' ).data( 'ghl-fields', fields );
            }
        } );
    } );

    // =========================================================================
    // ELEMENTOR custom field add/remove/save
    // =========================================================================

    $( document ).on( 'click', '.goodconnect-add-elementor-custom-field', function () {
        var $card     = $( this ).closest( '.goodconnect-elementor-card' );
        var $wrap     = $card.find( '.goodconnect-elementor-custom-fields-wrap' );
        var ghlFields = $wrap.data( 'ghl-fields' ) || [];
        var $row      = $( '<div>' ).addClass( 'goodconnect-custom-field-row goodconnect-elementor-custom-field-row' );

        if ( ghlFields.length ) {
            $row.append( makeGHLFieldSelect( ghlFields, '' ) );
        } else {
            $row.append( $( '<input type="text">' ).addClass( 'gc-custom-ghl-key' ).attr( 'placeholder', 'GHL field key' ).val( '' ) );
        }
        $row.append( $( '<input type="text">' ).addClass( 'gc-custom-elementor-field' ).attr( 'placeholder', 'Elementor field ID' ) );
        $row.append( $( '<button type="button">' ).addClass( 'button goodconnect-remove-custom-field' ).text( '\u2715' ) );
        $card.find( '.goodconnect-elementor-custom-fields' ).append( $row );
    } );

    // =========================================================================
    // CF7 custom field add/remove
    // =========================================================================

    $( document ).on( 'click', '.goodconnect-add-cf7-custom-field', function () {
        var $card     = $( this ).closest( '.goodconnect-cf7-card' );
        var $wrap     = $card.find( '.goodconnect-cf7-custom-fields-wrap' );
        var ghlFields = $wrap.data( 'ghl-fields' ) || [];
        var $row      = $( '<div>' ).addClass( 'goodconnect-custom-field-row goodconnect-cf7-custom-field-row' );

        if ( ghlFields.length ) {
            $row.append( makeGHLFieldSelect( ghlFields, '' ) );
        } else {
            $row.append( $( '<input type="text">' ).addClass( 'gc-custom-ghl-key' ).attr( 'placeholder', 'GHL field key' ).val( '' ) );
        }
        $row.append( $( '<input type="text">' ).addClass( 'gc-custom-cf7-field' ).attr( 'placeholder', 'CF7 field name' ) );
        $row.append( $( '<button type="button">' ).addClass( 'button goodconnect-remove-custom-field' ).text( '\u2715' ) );
        $card.find( '.goodconnect-cf7-custom-fields' ).append( $row );
    } );

    $( '#goodconnect-save-gf' ).on( 'click', function () {
        if ( ! currentFormId ) return;
        var $btn      = $( this );
        var field_map = {};
        var custom_fields = [];
        var dynamic_tags  = [];

        $( '#goodconnect-gf-mapper .goodconnect-gf-field-select' ).each( function () {
            var ghlField = $( this ).data( 'ghl-field' );
            var val      = $( this ).val();
            if ( val ) field_map[ ghlField ] = val;
        } );

        $( '#goodconnect-gf-custom-fields .goodconnect-custom-field-row' ).each( function () {
            // Support both select (after Load from GHL) and text input (fallback).
            var key = $( this ).find( '.gc-custom-ghl-key-select' ).val()
                   || $( this ).find( '.gc-custom-ghl-key' ).val() || '';
            key = key.trim();
            var fid = $( this ).find( '.gc-custom-gf-field' ).val();
            if ( key && fid ) custom_fields.push( { ghl_key: key, gf_field_id: fid } );
        } );

        $( '#goodconnect-gf-dynamic-tags .goodconnect-dynamic-tag-row' ).each( function () {
            var fid = $( this ).find( '.gc-dynamic-tag-field' ).val();
            if ( fid ) dynamic_tags.push( { gf_field_id: fid } );
        } );

        setBusy( $btn, true, 'Save Mapping' );
        $.post( GoodConnect.ajaxurl, {
            action:        'goodconnect_save_gf_config',
            nonce:         GoodConnect.nonce,
            form_id:       currentFormId,
            account_id:    $( '#goodconnect-gf-account' ).val(),
            field_map:     field_map,
            custom_fields: custom_fields,
            static_tags:   $( '#goodconnect-gf-static-tags' ).val(),
            dynamic_tags:  dynamic_tags,
        } )
        .done( function ( res ) {
            if ( res.success && currentFormData ) {
                currentFormData.config = {
                    account_id:    $( '#goodconnect-gf-account' ).val(),
                    field_map:     field_map,
                    custom_fields: custom_fields,
                    static_tags:   $( '#goodconnect-gf-static-tags' ).val().split( ',' ).map( function ( t ) { return t.trim(); } ).filter( Boolean ),
                    dynamic_tags:  dynamic_tags,
                };
            }
            showStatus( $btn, ! res.success );
        } )
        .fail( function () { showStatus( $btn, true ); } )
        .always( function () { setBusy( $btn, false, 'Save Mapping' ); } );
    } );

    // =========================================================================
    // ELEMENTOR
    // =========================================================================

    $( document ).on( 'click', '.goodconnect-add-elementor-form', function () {
        var tpl   = document.getElementById( 'goodconnect-elementor-card-template' );
        var clone = document.importNode( tpl.content, true );
        $( '#goodconnect-elementor-forms' ).append( clone );
    } );

    $( document ).on( 'click', '.goodconnect-remove-elementor-form', function () {
        if ( confirm( 'Remove this form mapping?' ) ) $( this ).closest( '.goodconnect-elementor-card' ).remove();
    } );

    $( document ).on( 'click', '.goodconnect-save-elementor', function () {
        var $btn      = $( this );
        var $card     = $btn.closest( '.goodconnect-elementor-card' );
        var form_name = $card.find( '.goodconnect-elementor-form-name' ).val().trim();
        if ( ! form_name ) { alert( 'Please enter the form name.' ); return; }

        var field_map = {};
        $card.find( '.goodconnect-elementor-field-id' ).each( function () {
            var ghlField = $( this ).data( 'ghl-field' );
            var val      = $( this ).val().trim();
            if ( val ) field_map[ ghlField ] = val;
        } );

        var custom_fields = [];
        $card.find( '.goodconnect-elementor-custom-field-row' ).each( function () {
            var key   = $( this ).find( '.gc-custom-ghl-key-select' ).val()
                     || $( this ).find( '.gc-custom-ghl-key' ).val() || '';
            var field = $( this ).find( '.gc-custom-elementor-field' ).val() || '';
            if ( key.trim() && field.trim() ) custom_fields.push( { ghl_key: key.trim(), elementor_field: field.trim() } );
        } );

        setBusy( $btn, true, 'Save Mapping' );
        $.post( GoodConnect.ajaxurl, {
            action:        'goodconnect_save_elementor_config',
            nonce:         GoodConnect.nonce,
            form_name:     form_name,
            account_id:    $card.find( '.goodconnect-elementor-account' ).val(),
            field_map:     field_map,
            custom_fields: custom_fields,
            static_tags:   $card.find( '.goodconnect-elementor-static-tags' ).val(),
        } )
        .done( function ( res ) { showStatus( $btn, ! res.success ); } )
        .fail( function () { showStatus( $btn, true ); } )
        .always( function () { setBusy( $btn, false, 'Save Mapping' ); } );
    } );

    // =========================================================================
    // CONTACT FORM 7
    // =========================================================================

    $( document ).on( 'click', '.goodconnect-save-cf7', function () {
        var $btn     = $( this );
        var $card    = $btn.closest( '.goodconnect-cf7-card' );
        var form_id  = $card.data( 'form-id' );
        var field_map = {};

        $card.find( '.goodconnect-cf7-field-id' ).each( function () {
            var ghlField = $( this ).data( 'ghl-field' );
            var val      = $( this ).val().trim();
            if ( val ) field_map[ ghlField ] = val;
        } );

        var custom_fields = [];
        $card.find( '.goodconnect-cf7-custom-field-row' ).each( function () {
            var key   = $( this ).find( '.gc-custom-ghl-key-select' ).val()
                     || $( this ).find( '.gc-custom-ghl-key' ).val() || '';
            var field = $( this ).find( '.gc-custom-cf7-field' ).val() || '';
            if ( key.trim() && field.trim() ) custom_fields.push( { ghl_key: key.trim(), cf7_field: field.trim() } );
        } );

        setBusy( $btn, true, 'Save Mapping' );
        $.post( GoodConnect.ajaxurl, {
            action:        'goodconnect_save_cf7_config',
            nonce:         GoodConnect.nonce,
            form_id:       form_id,
            account_id:    $card.find( '.goodconnect-cf7-account' ).val(),
            field_map:     field_map,
            custom_fields: custom_fields,
            static_tags:   $card.find( '.goodconnect-cf7-static-tags' ).val(),
        } )
        .done( function ( res ) { showStatus( $btn, ! res.success ); } )
        .fail( function () { showStatus( $btn, true ); } )
        .always( function () { setBusy( $btn, false, 'Save Mapping' ); } );
    } );

    // =========================================================================
    // BULK SYNC
    // =========================================================================

    var bulkSyncPollTimer = null;

    function updateBulkSyncUI( progress ) {
        if ( ! progress ) return;
        $( '#gc-bulk-sync-status' ).text( progress.status );
        $( '#gc-bulk-sync-processed' ).text( progress.processed );
        $( '#gc-bulk-sync-total' ).text( progress.total );
        $( '#gc-bulk-sync-errors' ).text( progress.errors );
        $( '#gc-bulk-sync-progress-wrap' ).show();

        if ( progress.status === 'running' ) {
            $( '#gc-bulk-sync-start' ).prop( 'disabled', true );
            $( '#gc-bulk-sync-cancel' ).show();
        } else {
            $( '#gc-bulk-sync-start' ).prop( 'disabled', false );
            $( '#gc-bulk-sync-cancel' ).hide();
            if ( bulkSyncPollTimer ) { clearInterval( bulkSyncPollTimer ); bulkSyncPollTimer = null; }
        }
    }

    function pollBulkSync() {
        $.post( GoodConnect.ajaxurl, { action: 'goodconnect_bulk_sync_progress', nonce: GoodConnect.nonce } )
        .done( function ( res ) {
            if ( res.success ) updateBulkSyncUI( res.data );
        } );
    }

    $( '#gc-bulk-sync-start' ).on( 'click', function () {
        var $btn = $( this );
        $btn.prop( 'disabled', true );
        $.post( GoodConnect.ajaxurl, {
            action:     'goodconnect_bulk_sync_start',
            nonce:      GoodConnect.nonce,
            account_id: $( '#gc-bulk-sync-account' ).val(),
        } )
        .done( function ( res ) {
            if ( res.success ) {
                updateBulkSyncUI( res.data );
                bulkSyncPollTimer = setInterval( pollBulkSync, 5000 );
                $( '#gc-bulk-sync-msg' ).text( 'Sync started!' ).addClass( 'visible' );
                setTimeout( function () { $( '#gc-bulk-sync-msg' ).removeClass( 'visible' ); }, 3000 );
            } else {
                $btn.prop( 'disabled', false );
                alert( res.data || 'Could not start sync.' );
            }
        } )
        .fail( function () { $btn.prop( 'disabled', false ); } );
    } );

    $( '#gc-bulk-sync-cancel' ).on( 'click', function () {
        $.post( GoodConnect.ajaxurl, { action: 'goodconnect_bulk_sync_cancel', nonce: GoodConnect.nonce } )
        .done( function () {
            if ( bulkSyncPollTimer ) { clearInterval( bulkSyncPollTimer ); bulkSyncPollTimer = null; }
            $( '#gc-bulk-sync-status' ).text( 'cancelled' );
            $( '#gc-bulk-sync-start' ).prop( 'disabled', false );
            $( this ).hide();
        }.bind( this ) );
    } );

    // Auto-poll if a sync is already running on page load.
    if ( $( '#gc-bulk-sync-progress-wrap' ).is( ':visible' ) && $( '#gc-bulk-sync-status' ).text() === 'running' ) {
        bulkSyncPollTimer = setInterval( pollBulkSync, 5000 );
    }

    // =========================================================================
    // WOOCOMMERCE
    // =========================================================================

    $( '#goodconnect-save-woo' ).on( 'click', function () {
        var $btn = $( this );
        setBusy( $btn, true, 'Save' );
        $.post( GoodConnect.ajaxurl, {
            action:         'goodconnect_save_woo_settings',
            nonce:          GoodConnect.nonce,
            woo_enabled:    $( '#gc_woo_enabled' ).is( ':checked' ) ? 1 : 0,
            woo_account_id: $( '#gc_woo_account_id' ).val(),
        } )
        .done( function ( res ) { showStatus( $btn, ! res.success ); } )
        .fail( function () { showStatus( $btn, true ); } )
        .always( function () { setBusy( $btn, false, 'Save' ); } );
    } );

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    $( '#goodconnect-clear-log' ).on( 'click', function () {
        if ( ! confirm( GoodConnect.strings.confirmClear ) ) return;
        var $btn = $( this );
        $btn.prop( 'disabled', true );
        $.post( GoodConnect.ajaxurl, { action: 'goodconnect_clear_log', nonce: GoodConnect.nonce } )
        .done( function () { window.location.reload(); } )
        .fail( function () { $btn.prop( 'disabled', false ); alert( GoodConnect.strings.error ); } );
    } );

} )( jQuery );

// ---- Webhook tab ----
if ( document.getElementById( 'gc-webhook-url' ) ) {
    jQuery( function( $ ) {
        // Copy webhook URL
        $( document ).on( 'click', '#gc-webhook-url-copy', function() {
            var val = $( '#gc-webhook-url' ).val();
            if ( navigator.clipboard ) {
                navigator.clipboard.writeText( val );
            } else {
                var tmp = $( '<input>' ).val( val ).appendTo( 'body' ).select();
                document.execCommand( 'copy' );
                tmp.remove();
            }
        } );

        // Add rule.
        $( document ).on( 'click', '.goodconnect-add-webhook-rule', function() {
            var tpl = document.getElementById( 'goodconnect-webhook-rule-template' );
            var idx = Date.now();
            var html = tpl.innerHTML.replace( /__IDX__/g, idx );
            $( '#goodconnect-webhook-rules' ).append( html );
        } );

        // Remove rule.
        $( document ).on( 'click', '.goodconnect-remove-webhook-rule', function() {
            $( this ).closest( '.goodconnect-webhook-rule-row' ).remove();
        } );

        // Save rules.
        $( document ).on( 'click', '.goodconnect-save-webhook-rules', function() {
            var $btn  = $( this );
            var rules = [];
            $( '.goodconnect-webhook-rule-row' ).each( function() {
                rules.push( {
                    event_type:   $( this ).find( '.gc-rule-event' ).val(),
                    action_type:  $( this ).find( '.gc-rule-action' ).val(),
                    extra_config: $( this ).find( '.gc-rule-extra' ).val(),
                } );
            } );
            $btn.prop( 'disabled', true ).text( GoodConnect.strings.saving );
            $.post( GoodConnect.ajaxurl, {
                action: 'goodconnect_save_webhook_rules',
                nonce:  GoodConnect.nonce,
                rules:  rules,
            } ).done( function( res ) {
                var $status = $btn.siblings( '.goodconnect-save-status' );
                $status.text( res.success ? GoodConnect.strings.saved : GoodConnect.strings.error ).addClass( 'visible' );
                setTimeout( function() { $status.removeClass( 'visible' ); }, 2500 );
            } ).always( function() { $btn.prop( 'disabled', false ).text( 'Save Rules' ); } );
        } );

        // Save allowed roles.
        $( document ).on( 'click', '.goodconnect-save-allowed-roles', function() {
            var $btn  = $( this );
            var roles = $( 'input[name="gc_allowed_roles[]"]:checked' ).map( function() { return $( this ).val(); } ).get();
            $btn.prop( 'disabled', true ).text( GoodConnect.strings.saving );
            $.post( GoodConnect.ajaxurl, {
                action: 'goodconnect_save_protection_settings',
                nonce:  GoodConnect.nonce,
                allowed_roles: roles,
            } ).done( function( res ) {
                var $status = $btn.siblings( '.goodconnect-save-status' );
                $status.text( res.success ? GoodConnect.strings.saved : GoodConnect.strings.error ).addClass( 'visible' );
                setTimeout( function() { $status.removeClass( 'visible' ); }, 2500 );
            } ).always( function() { $btn.prop( 'disabled', false ).text( 'Save' ); } );
        } );

        // Regenerate secret.
        $( document ).on( 'click', '.goodconnect-regenerate-secret', function() {
            if ( ! confirm( 'Regenerate the webhook secret? The old URL will stop working immediately.' ) ) { return; }
            var $btn = $( this );
            $btn.prop( 'disabled', true );
            $.post( GoodConnect.ajaxurl, {
                action: 'goodconnect_regenerate_webhook_secret',
                nonce:  GoodConnect.nonce,
            } ).done( function( res ) {
                if ( res.success ) { $( '#gc-webhook-url' ).val( res.data.url ); }
            } ).always( function() { $btn.prop( 'disabled', false ); } );
        } );
    } );
}

// ---- Content protection meta box ----
if ( document.getElementById( 'gc-denied-action' ) ) {
    jQuery( function( $ ) {
        function gcToggleDeniedMessage() {
            var val = $( '#gc-denied-action' ).val();
            $( '#gc-denied-message-row' ).toggle( val === 'message' );
        }
        gcToggleDeniedMessage();
        $( '#gc-denied-action' ).on( 'change', gcToggleDeniedMessage );
    } );
}
