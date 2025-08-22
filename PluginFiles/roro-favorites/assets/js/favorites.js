/* global roroFavorites, jQuery */
( function ( $ ) {
    'use strict';
    $( function () {
        $( document ).on( 'click', '.roro-fav-toggle', function ( e ) {
            e.preventDefault();
            var button   = $( this );
            var itemId   = button.data( 'item-id' );
            var itemType = button.data( 'item-type' );
            $.post( roroFavorites.ajaxUrl, {
                action:     'roro_fav_toggle',
                nonce:      roroFavorites.nonce,
                item_id:    itemId,
                item_type:  itemType
            }, function ( response ) {
                if ( response.success ) {
                    // Toggle visual state
                    button.toggleClass( 'is-favorited' );
                } else if ( response.data && response.data.message ) {
                    alert( response.data.message );
                }
            } );
        } );
    } );
} )( jQuery );