/*
 * Google Maps integration for RORO Core WP.
 *
 * This script is responsible for loading the Google Maps JavaScript
 * library on demand and instantiating a map with a search box.  The
 * API key is passed in via the global `RORO_MAP_CFG` object, which is
 * localised from PHP using wp_localize_script().  If the key is
 * undefined or empty the map will not be loaded and an error will be
 * logged to the console.
 */
( function () {
    function loadMap() {
        var cfg = window.RORO_MAP_CFG || {};
        var key = cfg.apiKey || '';
        if ( ! key ) {
            console.error( 'RORO: Google Maps API key missing or undefined' );
            return;
        }
        // Build the Google Maps script URL.
        var script = document.createElement( 'script' );
        var params = [];
        params.push( 'key=' + encodeURIComponent( key ) );
        params.push( 'libraries=places' );
        params.push( 'callback=RoroMapInit' );
        script.src = 'https://maps.googleapis.com/maps/api/js?' + params.join( '&' );
        script.async = true;
        script.defer = true;
        document.head.appendChild( script );
    }

    // Callback invoked by Google Maps once the API has loaded.
    window.RoroMapInit = function () {
        var el = document.getElementById( 'roro-map' );
        if ( ! el ) {
            return;
        }
        // Default to Tokyo Station if no other location is specified.
        var map = new google.maps.Map( el, {
            center: { lat: 35.681236, lng: 139.767125 },
            zoom: 12,
            mapTypeControl: false,
        } );
        var input = document.getElementById( 'roro-map-search' );
        if ( input ) {
            var searchBox = new google.maps.places.SearchBox( input );
            map.addListener( 'bounds_changed', function () {
                searchBox.setBounds( map.getBounds() );
            } );
            var markers = [];
            searchBox.addListener( 'places_changed', function () {
                var places = searchBox.getPlaces();
                if ( ! places || 0 === places.length ) {
                    return;
                }
                markers.forEach( function ( m ) {
                    m.setMap( null );
                } );
                markers = [];
                var bounds = new google.maps.LatLngBounds();
                places.forEach( function ( place ) {
                    if ( ! place.geometry || ! place.geometry.location ) {
                        return;
                    }
                    var marker = new google.maps.Marker( {
                        map: map,
                        title: place.name,
                        position: place.geometry.location,
                    } );
                    markers.push( marker );
                    if ( place.geometry.viewport ) {
                        bounds.union( place.geometry.viewport );
                    } else {
                        bounds.extend( place.geometry.location );
                    }
                } );
                map.fitBounds( bounds );
            } );
        }
    };

    // Wait for DOM readiness before loading the map script.
    document.addEventListener( 'DOMContentLoaded', loadMap );
} )();