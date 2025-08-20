<?php
// wp-content/plugins/roro-core/email/notification.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
function roro_send_material_order_mail( $customer_email, $material_title ) {
    $subject = sprintf( __( 'Thank you for purchasing the material: %s', 'roro-core' ), $material_title );
    $message = __( "Thank you for your purchase.\n\nWe will contact you regarding payment soon.", 'roro-core' );
    wp_mail( $customer_email, $subject, $message );
}
function roro_send_event_registration_mail( $customer_email, $event_title, $start_time ) {
    $subject = sprintf( __( 'Event registration confirmed: %s', 'roro-core' ), $event_title );
    $message = sprintf( __( "You have registered for the event '%s'.\nStart time: %s\n\nWe look forward to seeing you.", 'roro-core' ), $event_title, $start_time );
    wp_mail( $customer_email, $subject, $message );
}
