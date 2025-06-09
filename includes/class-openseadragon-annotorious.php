<?php
/**
 * Openseadragon_Annotorious Class
 *
 * This class handles the main functionality of the plugin.
 *
 * @package ARWAI_Openseadragon_Annotorious
 */

class Openseadragon_Annotorious {
    public $filter_called;
    private $table_name;
    private $history_table_name;

    const META_POST_DISPLAY_MODE = '_arwai_openseadragon_post_display_mode';
    const OPTION_DEFAULT_NEW_POST_MODE = 'arwai_openseadragon_default_new_post_mode';
    const META_SET_FIRST_AS_FEATURED = '_arwai_openseadragon_set_first_as_featured';
    const OPTION_ACTIVE_POST_TYPES = 'arwai_openseadragon_active_post_types';
    const META_IMAGE_IDS = '_arwai_multi_image_ids';

    function __construct() {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'annotorious_data';
        $this->history_table_name = $wpdb->prefix . 'annotorious_history';

        add_action( 'wp_enqueue_scripts', array( $this, 'load_public_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_plugin_metaboxes' ) );
        add_action( 'save_post', array( $this, 'save_multi_image_uploader_metabox' ), 10, 2 );
        add_filter( 'the_content', array( $this , 'content_filter' ), 20 );

        // AJAX actions
        add_action( 'wp_ajax_nopriv_arwai_anno_get', array( $this, 'anno_get') );
        add_action( 'wp_ajax_arwai_anno_get', array( $this, 'anno_get' ) );
        add_action( 'wp_ajax_nopriv_arwai_anno_add', array( $this, 'anno_add') );
        add_action( 'wp_ajax_arwai_anno_add', array( $this, 'anno_add' ) );
        add_action( 'wp_ajax_nopriv_arwai_anno_delete', array( $this, 'anno_delete') );
        add_action( 'wp_ajax_arwai_anno_delete', array( $this, 'anno_delete' ) );
        add_action( 'wp_ajax_nopriv_arwai_anno_update', array( $this, 'anno_update') );
        add_action( 'wp_ajax_arwai_anno_update', array( $this, 'anno_update' ) );
        add_action( 'wp_ajax_arwai_get_annotorious_history', array( $this, 'get_annotorious_history' ) );
        add_action( 'wp_ajax_nopriv_arwai_get_annotorious_history', array( $this, 'get_annotorious_history' ) );

        $this->filter_called = 0;
    }

    private function get_active_post_types() {
        $active_types = get_option( self::OPTION_ACTIVE_POST_TYPES, array( 'post', 'page' ) );
        return !empty($active_types) ? $active_types : array( 'post', 'page' );
    }

    public function settings_init() {
        register_setting('arwai_openseadragon_options_group', self::OPTION_DEFAULT_NEW_POST_MODE, ['type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_display_mode_option' ), 'default' => 'metabox_viewer']);
        register_setting('arwai_openseadragon_options_group', self::OPTION_ACTIVE_POST_TYPES, ['type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_active_post_types_option' ), 'default' => array( 'post', 'page' )]);
        add_settings_section('arwai_openseadragon_settings_section_main', 'Openseadragon-Annotorious Global Settings', array($this, 'settings_section_main_callback'), 'arwai-openseadragon-settings');
        add_settings_field('arwai_openseadragon_default_new_post_mode_field', 'Default Viewer Mode for New Posts', array( $this, 'default_new_post_mode_callback' ), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_main');
        add_settings_field('arwai_openseadragon_active_post_types_field', 'Activate Plugin for Post Types', array( $this, 'active_post_types_callback' ), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_main');
    }
    
    public function sanitize_display_mode_option( $input ) { $valid_options = array( 'metabox_viewer', 'gutenberg_block' ); return in_array( $input, $valid_options, true ) ? $input : 'metabox_viewer'; }
    public function sanitize_active_post_types_option( $input ) { $sanitized_input = array(); if ( is_array( $input ) ) { $all_registered_post_types = get_post_types( array( 'public' => true ), 'names' ); foreach ( $input as $post_type_slug ) { $slug = sanitize_key( $post_type_slug ); if ( in_array( $slug, $all_registered_post_types, true ) && $slug !== 'attachment' ) { $sanitized_input[] = $slug; } } } return !empty($sanitized_input) ? $sanitized_input : array('post', 'page'); }
    public function settings_section_main_callback() { echo '<p>Configure global settings for the plugin. The primary display choice for individual posts is managed on its edit screen. Here you can set the default mode for new posts and select which post types the plugin activates for.</p>'; }

    public function default_new_post_mode_callback() {
        $option_value = get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );
        ?>
        <fieldset>
            <legend class="screen-reader-text"><span>Default Viewer Mode</span></legend>
            <label><input type="radio" name="<?php echo esc_attr( self::OPTION_DEFAULT_NEW_POST_MODE ); ?>" value="metabox_viewer" <?php checked( $option_value, 'metabox_viewer' ); ?> /> Default Viewer (uses images from the Image Collection metabox)</label><br />
            <label><input type="radio" name="<?php echo esc_attr( self::OPTION_DEFAULT_NEW_POST_MODE ); ?>" value="gutenberg_block" <?php checked( $option_value, 'gutenberg_block' ); ?> /> Gutenberg Block (manual placement)</label>
        </fieldset>
        <?php
    }

    public function active_post_types_callback() {
        $saved_options = $this->get_active_post_types();
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <fieldset>
            <legend class="screen-reader-text"><span>Activate for Post Types</span></legend>
            <?php foreach ( $post_types as $post_type ) : if ( $post_type->name === 'attachment' ) continue; ?>
                <label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_ACTIVE_POST_TYPES ); ?>[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $saved_options, true ) ); ?> /> <?php echo esc_html( $post_type->labels->name ); ?></label><br />
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    public function add_settings_page() { add_options_page('Openseadragon-Annotorious Settings', 'Openseadragon-Annotorious', 'manage_options', 'arwai-openseadragon-settings', array( $this, 'settings_page_html' )); }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields( 'arwai_openseadragon_options_group' ); do_settings_sections( 'arwai-openseadragon-settings' ); submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }

    public function load_public_scripts(){
        if ( ! is_singular( $this->get_active_post_types() ) ) return;
        $post_id = get_the_ID();
        if (!$post_id) return;

        $display_mode = get_post_meta( $post_id, self::META_POST_DISPLAY_MODE, true ) ?: get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );

        if ( 'metabox_viewer' === $display_mode ) {
            $image_ids = json_decode( get_post_meta( $post_id, self::META_IMAGE_IDS, true ), true );
            if ( !empty( $image_ids ) && is_array( $image_ids ) ) {
                $image_sources = array_reduce( $image_ids, function($carry, $id) {
                    $src = wp_get_attachment_image_src( $id, 'full' );
                    if ($src) $carry[] = ['type' => 'image', 'url' => $src[0], 'post_id' => $id];
                    return $carry;
                }, []);

                if (!empty($image_sources)) {
                    wp_enqueue_style( 'arwai-annotorious-css', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/css/annotorious/annotorious.min.css');
                    wp_enqueue_script( 'arwai-openseadragon-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/openseadragon/openseadragon.min.js', array(), null, true );
                    wp_enqueue_script( 'arwai-annotorious-core-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/annotorious/annotorious.min.js', array(), null, true );
                    wp_enqueue_script( 'arwai-annotorious-osd-plugin-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/annotorious/openseadragon-annotorious.min.js', array( 'arwai-openseadragon-js', 'arwai-annotorious-core-js' ), null, true );
                    wp_enqueue_script( 'arwai-public-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/public/script.js', array('jquery', 'arwai-annotorious-osd-plugin-js'), null, true);
                    
                    wp_localize_script( 'arwai-public-js', 'ArwaiOSD_ViewerConfig', ['id' => 'openseadragon-viewer-' . $post_id, 'images' => $image_sources, 'currentPostId' => $post_id] );
                    // CORRECTED: Pass the local prefixUrl for OSD button images to the script
                    wp_localize_script( 'arwai-public-js', 'ArwaiOSD_Vars', ['ajax_url' => admin_url( 'admin-ajax.php' ), 'prefixUrl' => ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/images/'] );
                }
            }
        }
    }

    public function load_admin_scripts($hook_suffix) {
        if (in_array($hook_suffix, array('post.php', 'post-new.php'))) {
            $screen = get_current_screen();
            if ( $screen && in_array( $screen->post_type, $this->get_active_post_types() ) ) {
                wp_enqueue_script('arwai-admin-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/admin/admin.js', array('jquery', 'jquery-ui-sortable'), null, true);
                wp_enqueue_media();
            }
        }
    }

    public function content_filter($content) {
        if ( !is_singular( $this->get_active_post_types() ) || !in_the_loop() || !is_main_query() || $this->filter_called > 0 ) return $content;

        $post_id = get_the_ID();
        if (!$post_id) return $content;
        
        $display_mode = get_post_meta( $post_id, self::META_POST_DISPLAY_MODE, true ) ?: get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );
        
        if ( 'metabox_viewer' === $display_mode ) {
            $image_ids = json_decode( get_post_meta( $post_id, self::META_IMAGE_IDS, true ), true );
            if ( !empty($image_ids) ) {
                $this->filter_called++;
                $viewer_id = 'openseadragon-viewer-' . $post_id;
                $viewer_html = '<div id="' . esc_attr( $viewer_id ) . '" style="width: 100%; height: 600px; background-color: #000;"></div>';
                return $viewer_html . $content;
            }
        }
        return $content;
    }

    public function add_plugin_metaboxes() {
        $active_post_types = $this->get_active_post_types();
        if (empty($active_post_types)) return;

        add_meta_box('arwai-openseadragon-display-mode-metabox', __('Viewer Mode', 'arwai-openseadragon'), array( $this, 'render_display_mode_metabox' ), $active_post_types, 'side');
        add_meta_box('arwai-multi-image-uploader-metabox', __('Image Collection (sortable)', 'arwai-openseadragon'), array( $this, 'render_multi_image_uploader_metabox' ), $active_post_types, 'normal', 'high');
    }

    public function render_display_mode_metabox($post) {
        $current_display_mode = get_post_meta( $post->ID, self::META_POST_DISPLAY_MODE, true ) ?: get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );
        ?>
        <div id="arwai-openseadragon-options-container">
            <p><label><input type="radio" name="<?php echo esc_attr( self::META_POST_DISPLAY_MODE ); ?>" value="metabox_viewer" <?php checked( $current_display_mode, 'metabox_viewer' ); ?> /> <?php _e( 'Default Viewer', 'arwai-openseadragon' ); ?></label><br /><small class="description"><?php _e( 'Uses images from the "Image Collection" metabox.', 'arwai-openseadragon' ); ?></small></p>
            <p><label><input type="radio" name="<?php echo esc_attr( self::META_POST_DISPLAY_MODE ); ?>" value="gutenberg_block" <?php checked( $current_display_mode, 'gutenberg_block' ); ?> /> <?php _e( 'Gutenberg Block', 'arwai-openseadragon' ); ?></label><br/><small class="description"><?php _e( 'Manual placement via block editor.', 'arwai-openseadragon' ); ?></small></p>
        </div>
        <?php
    }

    public function render_multi_image_uploader_metabox( $post ) {
        wp_nonce_field( 'arwai_multi_image_uploader_save', 'arwai_multi_image_uploader_nonce' );
        $image_ids_json = get_post_meta( $post->ID, self::META_IMAGE_IDS, true );
        $image_ids = json_decode( $image_ids_json, true );
        if ( ! is_array( $image_ids ) ) { $image_ids = array(); }
        ?>
        <div id="arwai-multi-image-uploader-container">
            <p class="description"><?php _e( 'Select images. Drag to reorder.', 'arwai-openseadragon' ); ?></p>
            <ul class="arwai-multi-image-list">
                <?php if ( ! empty( $image_ids ) ) { foreach ( $image_ids as $id ) { $thumb_url = wp_get_attachment_image_url( $id, 'thumbnail' ); if ( $thumb_url ) { echo '<li data-id="' . esc_attr( $id ) . '"><img src="' . esc_url( $thumb_url ) . '" style="max-width:100px; max-height:100px; display:block;" /><a href="#" class="arwai-multi-image-remove dashicons dashicons-trash" title="Remove image"></a></li>'; } } } ?>
            </ul>
            <p>
                <a href="#" class="button button-secondary arwai-multi-image-add-button"><?php _e( 'Add/Select Images', 'arwai-openseadragon' ); ?></a>
                <input type="hidden" id="arwai_multi_image_ids_field" name="<?php echo esc_attr(self::META_IMAGE_IDS); ?>" value="<?php echo esc_attr( $image_ids_json ); ?>" />
            </p>
            <p><label><input type="checkbox" name="<?php echo esc_attr( self::META_SET_FIRST_AS_FEATURED ); ?>" value="yes" <?php checked( get_post_meta( $post->ID, self::META_SET_FIRST_AS_FEATURED, true ), 'yes' ); ?> /> <?php _e( 'Use the first image in this collection as the post\'s featured image.', 'arwai-openseadragon' ); ?></label></p>
        </div>
        <style>#arwai-multi-image-uploader-container .arwai-multi-image-list li { cursor: move; position: relative; width: 100px; height: 100px; margin: 5px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; overflow: hidden; } #arwai-multi-image-uploader-container .arwai-multi-image-list { display: flex; flex-wrap: wrap; list-style: none; margin: 0; padding: 0; } #arwai-multi-image-uploader-container .arwai-multi-image-list li img { max-width: 100%; max-height: 100%; object-fit: contain; } #arwai-multi-image-uploader-container .arwai-multi-image-remove { position: absolute; top: 0; right: 0; background: rgba(255,0,0,0.7); color: white; padding: 3px; cursor: pointer; line-height: 1; text-decoration: none; } .arwai-multi-image-placeholder { background-color: #f0f0f0; border: 1px dashed #ccc; height: 100px; width: 100px; margin: 5px; list-style-type: none; }</style>
        <?php
    }

    public function save_multi_image_uploader_metabox( $post_id, $post ) {
        if ( ! isset( $_POST['arwai_multi_image_uploader_nonce'] ) || ! wp_verify_nonce( $_POST['arwai_multi_image_uploader_nonce'], 'arwai_multi_image_uploader_save' ) ) { return; }
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( ! in_array($post->post_type, $this->get_active_post_types()) ) return;

        // Save Display Mode
        if ( isset( $_POST[self::META_POST_DISPLAY_MODE] ) ) {
            update_post_meta($post_id, self::META_POST_DISPLAY_MODE, sanitize_text_field($_POST[self::META_POST_DISPLAY_MODE]));
        }

        // Save Image IDs
        if ( isset( $_POST[self::META_IMAGE_IDS] ) ) {
            $ids_json = wp_unslash($_POST[self::META_IMAGE_IDS]);
            $ids = json_decode($ids_json, true);
            if (is_array($ids)) {
                update_post_meta($post_id, self::META_IMAGE_IDS, json_encode(array_values(array_map('intval', $ids))));
            } else {
                delete_post_meta($post_id, self::META_IMAGE_IDS);
            }
        } else {
            delete_post_meta($post_id, self::META_IMAGE_IDS);
        }

        // Save "Set First as Featured"
        $set_featured = isset($_POST[self::META_SET_FIRST_AS_FEATURED]) ? 'yes' : 'no';
        update_post_meta($post_id, self::META_SET_FIRST_AS_FEATURED, $set_featured);
        if ('yes' === $set_featured) {
            $ids = json_decode(get_post_meta($post_id, self::META_IMAGE_IDS, true), true);
            if (!empty($ids) && intval($ids[0]) > 0) {
                set_post_thumbnail($post_id, intval($ids[0]));
            }
        }
    }

    // --- AJAX Functions ---
    
    function anno_get() {
        global $wpdb;
        $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;
        if (empty($attachment_id)) { wp_send_json_error('Missing attachment_id.'); }
        header('Content-Type: application/json');
        $all_annotations = [];
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT annotation_data FROM {$this->table_name} WHERE attachment_id = %d", $attachment_id ), ARRAY_A );
        if ( ! empty( $results ) ) {
            foreach ( $results as $row ) {
                $decoded_annotation = json_decode( $row['annotation_data'], true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $all_annotations[] = $decoded_annotation;
                }
            }
        }
        echo wp_json_encode($all_annotations);
        wp_die();
    }

    function anno_add() {
        global $wpdb;
        $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';
        if (empty($annotation_json)) { wp_send_json_error('Annotation data missing.'); }
        $annotation = json_decode($annotation_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) { wp_send_json_error('Invalid JSON data.'); }
        $image_url = $annotation['target']['source'] ?? '';
        if (empty($image_url)) { wp_send_json_error('Annotation target source URL missing.'); }
        $attachment_id = attachment_url_to_postid($image_url);
        if (empty($attachment_id)) { wp_send_json_error('Could not find attachment ID for source URL.'); }
        $annotation_id = $annotation['id'] ?? '';
        if (empty($annotation_id)) { wp_send_json_error('Annotorious ID missing.'); }
        if (isset($annotation['body'][0]['value'])) { $annotation['body'][0]['value'] = wp_kses_post($annotation['body'][0]['value']); }
        
        $wpdb->insert( $this->history_table_name, array('annotation_id_from_annotorious' => $annotation_id, 'attachment_id' => $attachment_id, 'action_type' => 'created', 'annotation_data_snapshot' => wp_json_encode($annotation), 'user_id' => get_current_user_id()), array('%s', '%d', '%s', '%s', '%d') );
        $inserted = $wpdb->insert( $this->table_name, array('annotation_id_from_annotorious' => $annotation_id, 'attachment_id' => $attachment_id, 'annotation_data' => wp_json_encode($annotation)), array('%s', '%d', '%s') );

        if ($inserted) { wp_send_json_success(['database_id' => $wpdb->insert_id]); } else { wp_send_json_error(['message' => 'Failed to add annotation.']); }
        wp_die();
    }

    function anno_delete() {
        global $wpdb;
        $annoid = isset($_POST['annotationid']) ? sanitize_text_field($_POST['annotationid']) : '';
        $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';
        if (empty($annoid) || empty($annotation_json)) { wp_send_json_error('Missing data.'); }
        $annotation = json_decode($annotation_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) { wp_send_json_error('Invalid JSON.'); }
        $image_url = $annotation['target']['source'] ?? '';
        $attachment_id = attachment_url_to_postid($image_url);
        if (empty($attachment_id)) { wp_send_json_error('Could not find attachment ID.'); }

        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT annotation_data FROM {$this->table_name} WHERE annotation_id_from_annotorious = %s AND attachment_id = %d", $annoid, $attachment_id ), ARRAY_A );
        if ($existing) {
            $wpdb->insert( $this->history_table_name, array('annotation_id_from_annotorious' => $annoid, 'attachment_id' => $attachment_id, 'action_type' => 'deleted', 'annotation_data_snapshot' => $existing['annotation_data'], 'user_id' => get_current_user_id()), array('%s', '%d', '%s', '%s', '%d') );
        }

        $deleted = $wpdb->delete( $this->table_name, array('annotation_id_from_annotorious' => $annoid, 'attachment_id' => $attachment_id), array('%s', '%d') );

        if ($deleted) { wp_send_json_success(); } else { wp_send_json_error(); }
        wp_die();
    }

    function anno_update() {
        global $wpdb;
        $annoid = isset($_POST['annotationid']) ? sanitize_text_field($_POST['annotationid']) : '';
        $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';
        if (empty($annoid) || empty($annotation_json)) { wp_send_json_error('Missing data.'); }
        $annotation = json_decode($annotation_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) { wp_send_json_error('Invalid JSON.'); }
        $image_url = $annotation['target']['source'] ?? '';
        $attachment_id = attachment_url_to_postid($image_url);
        if (empty($attachment_id)) { wp_send_json_error('Could not find attachment ID.'); }
        if (isset($annotation['body'][0]['value'])) { $annotation['body'][0]['value'] = wp_kses_post($annotation['body'][0]['value']); }

        $updated = $wpdb->update( $this->table_name, array('annotation_data' => wp_json_encode($annotation)), array('annotation_id_from_annotorious' => $annoid, 'attachment_id' => $attachment_id), array('%s'), array('%s', '%d') );
        
        if ($updated) {
            $wpdb->insert( $this->history_table_name, array('annotation_id_from_annotorious' => $annoid, 'attachment_id' => $attachment_id, 'action_type' => 'updated', 'annotation_data_snapshot' => wp_json_encode($annotation), 'user_id' => get_current_user_id()), array('%s', '%d', '%s', '%s', '%d') );
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
        wp_die();
    }
    
    function get_annotorious_history() {
        global $wpdb;
        $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;
        $annotation_id = isset($_GET['annotation_id']) ? sanitize_text_field($_GET['annotation_id']) : '';
        if (empty($attachment_id) && empty($annotation_id)) { wp_send_json_error('Missing ID.'); }
        
        header('Content-Type: application/json');
        $query_params = [];
        $where_clauses = [];
        if ($attachment_id) { $where_clauses[] = 'attachment_id = %d'; $query_params[] = $attachment_id; }
        if ($annotation_id) { $where_clauses[] = 'annotation_id_from_annotorious = %s'; $query_params[] = $annotation_id; }
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT * FROM {$this->history_table_name} WHERE {$where_sql} ORDER BY action_timestamp DESC";
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A );
        
        $history_records = array_map(function($row) {
            $user_info = get_userdata( $row['user_id'] );
            return [
                'id' => (int) $row['id'],
                'annotationId' => $row['annotation_id_from_annotorious'],
                'attachmentId' => (int) $row['attachment_id'],
                'actionType' => $row['action_type'],
                'annotationData' => json_decode($row['annotation_data_snapshot']),
                'userId' => (int) $row['user_id'],
                'userName' => $user_info ? $user_info->display_name : 'Guest',
                'timestamp' => $row['action_timestamp'],
            ];
        }, $results);

        wp_send_json_success(['history' => $history_records]);
        wp_die();
    }
}