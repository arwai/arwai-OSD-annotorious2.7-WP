<?php
/*
    Plugin Name: Openseadragon-Annotorious2.7
    Plugin URI: arwai.me
    Description: Extends WordPress to manage and annotate image collections via OpenSeadragon and Annotorious 2.7.
    Version: 1.0.0
    Author: Arwai
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants for URL and path
if ( ! defined( 'ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL' ) ) {
    define( 'ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'ARWAI_OPENSEADRAGON_ANNOTORIOUS_PATH' ) ) {
    define( 'ARWAI_OPENSEADRAGON_ANNOTORIOUS_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing hooks.
 */
require ARWAI_OPENSEADRAGON_ANNOTORIOUS_PATH . 'includes/class-openseadragon-annotorious.php';

/**
 * Begins execution of the plugin.
 */
function run_arwai_openseadragon_annotorious_plugin() {
    new Openseadragon_Annotorious();
}
run_arwai_openseadragon_annotorious_plugin();

/**
 * Activation Hook
 * Creates custom database tables on plugin activation.
 */
function arwai_openseadragon_annotorious_activate() {
    global $wpdb;

    // Table names reverted to original 'annotorious' prefix
    $table_name_data = $wpdb->prefix . 'annotorious_data';
    $table_name_history = $wpdb->prefix . 'annotorious_history';

    $charset_collate = $wpdb->get_charset_collate();

    // SQL for annotorious_data table
    $sql_data = "CREATE TABLE $table_name_data (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        annotation_id_from_annotorious VARCHAR(255) NOT NULL,
        attachment_id BIGINT(20) UNSIGNED NOT NULL,
        annotation_data LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY annotorious_id (annotation_id_from_annotorious),
        KEY attachment_id (attachment_id)
    ) $charset_collate;";

    // SQL for annotorious_history table
    $sql_history = "CREATE TABLE $table_name_history (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        annotation_id_from_annotorious VARCHAR(255) NOT NULL,
        attachment_id BIGINT(20) UNSIGNED NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        annotation_data_snapshot LONGTEXT NOT NULL,
        user_id BIGINT(20) UNSIGNED,
        action_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY annotorious_id_idx (annotation_id_from_annotorious),
        KEY attachment_id_idx (attachment_id),
        KEY user_id_idx (user_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_data );
    dbDelta( $sql_history );
}
register_activation_hook( __FILE__, 'arwai_openseadragon_annotorious_activate' );