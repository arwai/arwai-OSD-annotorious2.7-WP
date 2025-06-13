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

    // Meta and Option Keys
    const META_POST_DISPLAY_MODE = '_arwai_openseadragon_post_display_mode';
    const OPTION_DEFAULT_NEW_POST_MODE = 'arwai_openseadragon_default_new_post_mode';
    const META_SET_FIRST_AS_FEATURED = '_arwai_openseadragon_set_first_as_featured';
    const OPTION_ACTIVE_POST_TYPES = 'arwai_openseadragon_active_post_types';
    const META_IMAGE_IDS = '_arwai_multi_image_ids';

    // Annotorious Settings Keys
    const OPTION_ANNO_READ_ONLY = 'arwai_anno_read_only';
    const OPTION_ANNO_ALLOW_EMPTY = 'arwai_anno_allow_empty';
    const OPTION_ANNO_DRAW_ON_SINGLE_CLICK = 'arwai_anno_draw_on_single_click';
    const OPTION_ANNO_TAGS_LINK_TAXONOMY = 'arwai_anno_tags_link_taxonomy';

    // Annotorious constant for the LOCALE OPTION
    const OPTION_ANNO_LOCALE = 'arwai_anno_locale';
    const OPTION_ANNO_TRANSLATIONS = 'arwai_anno_translations'; 

    // OpenSeadragon Settings Keys
    private $osd_options_keys = [
        'backgroundColor' => ['type' => 'string', 'default' => '#000000', 'sanitize' => 'sanitize_hex_color'],
        'prefixUrl' => ['type' => 'string', 'default' => '', 'sanitize' => 'esc_url_raw'],
        'autoHideControls' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'degrees' => ['type' => 'number', 'default' => 0, 'sanitize' => 'floatval'],
        'visibilityRatio' => ['type' => 'number', 'default' => 0.5, 'sanitize' => 'floatval'],
        'controlsFadeDelay' => ['type' => 'number', 'default' => 2000, 'sanitize' => 'intval'],
        'controlsFadeLength' => ['type' => 'number', 'default' => 1500, 'sanitize' => 'intval'],
        'mouseNavEnabled' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'showNavigationControl' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'navigationControlAnchor' => ['type' => 'string', 'default' => 'TOP_LEFT', 'sanitize' => 'sanitize_text_field'],
        'showZoomControl' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'showHomeControl' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'showFullPageControl' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'showRotationControl' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'showFlipControl' => ['type' => 'boolean', 'default' => false, 'sanitize' => 'rest_sanitize_boolean'],
        'showSequenceControl' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'sequenceControlAnchor' => ['type' => 'string', 'default' => 'TOP_LEFT', 'sanitize' => 'sanitize_text_field'],
        'sequenceMode' => ['type' => 'boolean', 'default' => true, 'sanitize' => 'rest_sanitize_boolean'],
        'showReferenceStrip' => ['type' => 'boolean', 'default' => false, 'sanitize' => 'rest_sanitize_boolean'],
        'referenceStripSizeRatio' => ['type' => 'number', 'default' => 0.2, 'sanitize' => 'floatval'],
    ];

    private $gesture_settings_keys = [
        'dragToPan' => ['type' => 'boolean', 'default' => true],
        'scrollToZoom' => ['type' => 'boolean', 'default' => true],
        'clickToZoom' => ['type' => 'boolean', 'default' => true],
        'dblClickToZoom' => ['type' => 'boolean', 'default' => false],
        'pinchToZoom' => ['type' => 'boolean', 'default' => true],
        'flickEnabled' => ['type' => 'boolean', 'default' => false],
        'flickMinSpeed' => ['type' => 'number', 'default' => 120],
        'pinchRotate' => ['type' => 'boolean', 'default' => false],
    ];


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
        add_action( 'wp_ajax_arwai_add_taxonomy_term', array( $this, 'arwai_add_taxonomy_term' ) );
    }

    private function get_active_post_types() {
        $active_types = get_option( self::OPTION_ACTIVE_POST_TYPES, array( 'post', 'page' ) );
        return !empty($active_types) ? $active_types : array( 'post', 'page' );
    }

public function settings_init() {
        // Main Settings
        register_setting('arwai_openseadragon_options_group', self::OPTION_DEFAULT_NEW_POST_MODE, ['type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_display_mode_option' ), 'default' => 'metabox_viewer']);
        register_setting('arwai_openseadragon_options_group', self::OPTION_ACTIVE_POST_TYPES, ['type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_active_post_types_option' ), 'default' => array( 'post', 'page' )]);
        
        // Default translations as a JSON string (used if the option is empty)
        $default_translations_json = '{ "en-alt": { "Add a comment...": "Add a comment...", "Add tag...": "Add name or tag...", "Cancel": "Cancel", "Done": "Done" }, "pt": { "Add a comment...": "Adicionar um comentário...", "Add tag...": "Adicionar nome ouetiqueta...", "Cancel": "Cancelar", "Done": "Feito" } }';

        // Annotorious Settings
        register_setting('arwai_openseadragon_options_group', self::OPTION_ANNO_LOCALE, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'en-alt']);
        register_setting('arwai_openseadragon_options_group', self::OPTION_ANNO_TRANSLATIONS, ['type' => 'string', 'sanitize_callback' => array($this, 'sanitize_translations_json'), 'default' => $default_translations_json]); // <-- REGISTER NEW SETTING
        register_setting('arwai_openseadragon_options_group', self::OPTION_ANNO_READ_ONLY, ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false]);
        register_setting('arwai_openseadragon_options_group', self::OPTION_ANNO_ALLOW_EMPTY, ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false]);
        register_setting('arwai_openseadragon_options_group', self::OPTION_ANNO_DRAW_ON_SINGLE_CLICK, ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false]);
        register_setting('arwai_openseadragon_options_group', self::OPTION_ANNO_TAGS_LINK_TAXONOMY, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'none']);

        // OpenSeadragon Dynamic Settings
        foreach ($this->osd_options_keys as $key => $props) {
            register_setting('arwai_openseadragon_options_group', 'arwai_osd_' . $key, ['type' => $props['type'], 'sanitize_callback' => $props['sanitize'], 'default' => $props['default']]);
        }

        // Gesture Settings
        $device_types = ['mouse', 'touch']; 
        foreach ($device_types as $device) {
            foreach ($this->gesture_settings_keys as $key => $props) {
                register_setting('arwai_openseadragon_options_group', 'arwai_osd_gesture_' . $device . '_' . $key, ['type' => $props['type'], 'sanitize_callback' => $props['type'] === 'boolean' ? 'rest_sanitize_boolean' : 'floatval', 'default' => $props['default']]);
            }
        }
        
        // Add settings sections
        add_settings_section('arwai_openseadragon_settings_section_main', 'Openseadragon-Annotorious Global Settings', null, 'arwai-openseadragon-settings');
        add_settings_field('arwai_openseadragon_active_post_types_field', 'Activate Plugin for Post Types', array( $this, 'active_post_types_callback' ), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_main');
        add_settings_field('arwai_openseadragon_default_new_post_mode_field', 'Default Viewer Mode for New Posts', array( $this, 'default_new_post_mode_callback' ), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_main');
        
        add_settings_section('arwai_openseadragon_settings_section_osd', 'OpenSeadragon Viewer Settings', null, 'arwai-openseadragon-settings');
        add_settings_field('field_osd_controls_options', '', array($this, 'field_osd_controls_options_callback'), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_osd');
        add_settings_field('field_osd_gesture_options', '', array($this, 'field_osd_gesture_options_callback'), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_osd');

        add_settings_section('arwai_openseadragon_settings_section_annotorious', 'Annotorious Settings', null, 'arwai-openseadragon-settings');
        add_settings_field('field_anno_locale', 'Language', array($this, 'field_anno_locale_callback'), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_annotorious');
        add_settings_field('field_anno_translations', '', array($this, 'field_anno_translations_callback'), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_annotorious'); // <-- ADD NEW FIELD
        add_settings_field('field_anno_options', '', array($this, 'field_anno_options_callback'), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_annotorious');
        add_settings_field('field_anno_taxonomy', '', array($this, 'field_anno_taxonomy_callback'), 'arwai-openseadragon-settings', 'arwai_openseadragon_settings_section_annotorious');
    }
    
    // Sanitization Callbacks
    public function sanitize_display_mode_option( $input ) { $valid_options = array( 'metabox_viewer', 'gutenberg_block' ); return in_array( $input, $valid_options, true ) ? $input : 'metabox_viewer'; }
    public function sanitize_active_post_types_option( $input ) { $sanitized_input = array(); if ( is_array( $input ) ) { $all_registered_post_types = get_post_types( array( 'public' => true ), 'names' ); foreach ( $input as $post_type_slug ) { $slug = sanitize_key( $post_type_slug ); if ( in_array( $slug, $all_registered_post_types, true ) && $slug !== 'attachment' ) { $sanitized_input[] = $slug; } } } return !empty($sanitized_input) ? $sanitized_input : array('post', 'page'); }

    // Sanitization callback for the translations JSON
        public function sanitize_translations_json($input) {
            $decoded = json_decode(wp_unslash($input), true);
            
            // If json_decode fails, it returns null for invalid JSON.
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Return valid, minified JSON to save space. Use wp_json_encode for security.
                return wp_json_encode($decoded);
            }

            // If the input is not valid JSON, show an error and return the old value.
            add_settings_error(
                self::OPTION_ANNO_TRANSLATIONS,
                'invalid_json',
                'The text you entered was not valid JSON. Your changes to the translations have not been saved.',
                'error'
            );
            return get_option(self::OPTION_ANNO_TRANSLATIONS);
        }



    // --- Field Callbacks ---

    // ---Openseadragon-Annotorious Global Settings---

    // Activate Plugin for Post Types
    public function active_post_types_callback() {
        $saved_options = $this->get_active_post_types();
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <fieldset>
            <?php foreach ( $post_types as $post_type ) : if ( $post_type->name === 'attachment' ) continue; ?>
                <label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_ACTIVE_POST_TYPES ); ?>[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $saved_options, true ) ); ?> /> <?php echo esc_html( $post_type->labels->name ); ?></label><br />
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    // Default Viewer Mode for New Posts
    public function default_new_post_mode_callback() {
        $option_value = get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );
        ?>
        <fieldset>
            <label><input type="radio" name="<?php echo esc_attr( self::OPTION_DEFAULT_NEW_POST_MODE ); ?>" value="metabox_viewer" <?php checked( $option_value, 'metabox_viewer' ); ?> /> Default Viewer (uses images from the Image Collection metabox)</label><br />
            <label><input type="radio" name="<?php echo esc_attr( self::OPTION_DEFAULT_NEW_POST_MODE ); ?>" value="gutenberg_block" <?php checked( $option_value, 'gutenberg_block' ); ?> /> Gutenberg Block (manual placement)</label>
        </fieldset>
        <?php
    }

    // --- OpenSeadragon Fields ---
    // Controls and displays
    public function field_osd_controls_options_callback() {
         ?>
        <div class="arwai-toggle-list">
            <h3 class="arwai-toggle-list-header">Controls & Display</h3>
            <div class="arwai-toggle-list-content">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="arwai_osd_backgroundColor">Viewer Background Color</label></th>
                        <td><input type="text" id="arwai_osd_backgroundColor" name="arwai_osd_backgroundColor" value="<?php echo esc_attr(get_option('arwai_osd_backgroundColor', '#000000')); ?>" class="arwai-color-picker" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Functionality</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="arwai_osd_sequenceMode" value="1" <?php checked(get_option('arwai_osd_sequenceMode', true)); ?>> Enable Sequence Mode (for multiple images)</label><br>
                                <label><input type="checkbox" name="arwai_osd_mouseNavEnabled" value="1" <?php checked(get_option('arwai_osd_mouseNavEnabled', true)); ?>> Enable Mouse Navigation (pan and scroll-zoom)</label><br>
                                <label><input type="checkbox" name="arwai_osd_autoHideControls" value="1" <?php checked(get_option('arwai_osd_autoHideControls', true)); ?> /> Auto-hide Controls</label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Control Buttons</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="arwai_osd_showNavigationControl" value="1" <?php checked(get_option('arwai_osd_showNavigationControl', true)); ?>> Show Navigation Control (Zoom, Pan, Home)</label><br>
                                <label><input type="checkbox" name="arwai_osd_showZoomControl" value="1" <?php checked(get_option('arwai_osd_showZoomControl', true)); ?>> Show Zoom Controls (in/out)</label><br>
                                <label><input type="checkbox" name="arwai_osd_showHomeControl" value="1" <?php checked(get_option('arwai_osd_showHomeControl', true)); ?>> Show Home Control (reset zoom)</label><br>
                                <label><input type="checkbox" name="arwai_osd_showFullPageControl" value="1" <?php checked(get_option('arwai_osd_showFullPageControl', true)); ?>> Show Full Page Control</label><br>
                                <label><input type="checkbox" name="arwai_osd_showRotationControl" value="1" <?php checked(get_option('arwai_osd_showRotationControl', true)); ?>> Show Rotation Control</label><br>
                                <label><input type="checkbox" name="arwai_osd_showFlipControl" value="1" <?php checked(get_option('arwai_osd_showFlipControl', false)); ?>> Show Flip Control</label><br>
                                <label><input type="checkbox" name="arwai_osd_showSequenceControl" value="1" <?php checked(get_option('arwai_osd_showSequenceControl', true)); ?>> Show Sequence Control (prev/next buttons)</label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Reference Strip (Filmstrip)</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="arwai_osd_showReferenceStrip" value="1" <?php checked(get_option('arwai_osd_showReferenceStrip', true)); ?>> Show Reference Strip</label><br>
                                <label for="arwai_osd_referenceStripSizeRatio">Size Ratio</label>
                                <input type="number" step="0.05" id="arwai_osd_referenceStripSizeRatio" name="arwai_osd_referenceStripSizeRatio" value="<?php echo esc_attr(get_option('arwai_osd_referenceStripSizeRatio', 0.2)); ?>" class="small-text" />
                                <p class="description">The height of the strip as a portion of the viewer's height.</p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    // Gesture Settings
    public function field_osd_gesture_options_callback() {
        ?>
        <div class="arwai-toggle-list">
            <h3 class="arwai-toggle-list-header">Gesture Settings</h3>
            <div class="arwai-toggle-list-content">
                <table class="wp-list-table widefat striped">
                    <thead><tr><th>Gesture</th><th>Mouse</th><th>Touch</th></tr></thead>
                    <tbody>
                    <?php 
                    $device_types = ['mouse', 'touch'];
                    foreach ($this->gesture_settings_keys as $key => $props): ?>
                        <tr>
                            <td><?php echo esc_html(ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))); ?></td>
                            <?php foreach ($device_types as $device): 
                                $option_name = 'arwai_osd_gesture_' . $device . '_' . $key;
                                $value = get_option($option_name, $props['default']);
                            ?>
                                <td>
                                    <?php if ($props['type'] === 'boolean'): ?>
                                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>" value="1" <?php checked($value, true); ?> />
                                    <?php else: ?>
                                        <input type="number" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($value); ?>" class="small-text" />
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // --- Annotorious Fields ---
    // Language selector for Annotations, dynamically populated
        public function field_anno_locale_callback() {
            $current_locale = get_option(self::OPTION_ANNO_LOCALE, 'en-alt');
            $translations_json = get_option(self::OPTION_ANNO_TRANSLATIONS);
            $translations_array = json_decode($translations_json, true);

            $available_locales = [];
            if (json_last_error() === JSON_ERROR_NONE && is_array($translations_array)) {
                $available_locales = array_keys($translations_array);
            }

            // Ensure the currently saved locale is always in the list, even if it was removed from the JSON.
            // This prevents the setting from being silently lost before saving.
            if (!in_array($current_locale, $available_locales)) {
                $available_locales[] = $current_locale;
            }
            
            // Remove duplicates and sort for a clean list
            $available_locales = array_unique($available_locales);
            sort($available_locales);
            ?>
            <p class="description">Select the language for the annotation editor pop-up. Options are generated from the keys in the Translations Dictionary below.</p>

            <select name="<?php echo esc_attr(self::OPTION_ANNO_LOCALE); ?>" id="<?php echo esc_attr(self::OPTION_ANNO_LOCALE); ?>">
                <?php if (!empty($available_locales)) : ?>
                    <?php foreach ($available_locales as $locale_code) : ?>
                        <option value="<?php echo esc_attr($locale_code); ?>" <?php selected($current_locale, $locale_code); ?>>
                            <?php echo esc_html(ucfirst(str_replace('-', ' ', $locale_code))); // Makes "en-alt" -> "En alt" for readability ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    // Fallback in case the JSON is completely empty and there's no saved option
                    <option value="en-alt">English (Default)</option>
                <?php endif; ?>
            </select>
            <?php
        }

    // Translation Dictionary JSON?

    public function field_anno_translations_callback() {
        $json_string = get_option(self::OPTION_ANNO_TRANSLATIONS);
        // Pretty-print the JSON for readability in the textarea
        $decoded = json_decode($json_string, true);
        $pretty_json = (json_last_error() === JSON_ERROR_NONE) ? wp_json_encode($decoded, JSON_PRETTY_PRINT) : $json_string;
        ?>
        <div class="arwai-toggle-list">
            <h3 class="arwai-toggle-list-header">Translations Dictionary (JSON)</h3>
            <div class="arwai-toggle-list-content">
                <textarea name="<?php echo esc_attr(self::OPTION_ANNO_TRANSLATIONS); ?>" rows="10" cols="80" class="large-text code"><?php echo esc_textarea($pretty_json); ?></textarea>
                <p class="description">Enter the complete translations dictionary in JSON format. Each top-level key should be a locale code (e.g., "en-alt", "pt").</p>
            </div>
        </div>

        <?php
    }

    // Behaviour Options

    public function field_anno_options_callback() {
        ?>
        <div class="arwai-toggle-list">
            <h3 class="arwai-toggle-list-header">Behavior Options</h3>
            <div class="arwai-toggle-list-content">
                <fieldset>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_ANNO_READ_ONLY); ?>" value="1" <?php checked(get_option(self::OPTION_ANNO_READ_ONLY, false)); ?> /> Read Only</label>
                    <p class="description">Prevent users from creating, editing, or deleting annotations.</p>
                    <br>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_ANNO_ALLOW_EMPTY); ?>" value="1" <?php checked(get_option(self::OPTION_ANNO_ALLOW_EMPTY, false)); ?> /> Allow Empty Annotations</label>
                    <p class="description">Allow users to save annotations that do not contain any text or tags.</p>
                    <br>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_ANNO_DRAW_ON_SINGLE_CLICK); ?>" value="1" <?php checked(get_option(self::OPTION_ANNO_DRAW_ON_SINGLE_CLICK, false)); ?> /> Draw on Single Click</label>
                    <p class="description">Allows users to draw rectangles with a single mouse click (instead of drag-and-drop).</p>
                </fieldset>
            </div>
        </div>
        <?php
    }

    // Link Annotorioius Tags to WP-Tags
    public function field_anno_taxonomy_callback() {
         ?>
        <div class="arwai-toggle-list">
            <h3 class="arwai-toggle-list-header">Link Annotorious Tags to WordPress Taxonomy***</h3> 
            <div class="arwai-toggle-list-content">
                <select name="<?php echo esc_attr(self::OPTION_ANNO_TAGS_LINK_TAXONOMY); ?>" id="<?php echo esc_attr(self::OPTION_ANNO_TAGS_LINK_TAXONOMY); ?>">
                    <option value="none" <?php selected(get_option(self::OPTION_ANNO_TAGS_LINK_TAXONOMY, 'none'), 'none'); ?>>Do not link (freeform tags)</option>
                    <?php
                    $taxonomies = get_taxonomies(['public' => true], 'objects');
                    $current_selection = get_option(self::OPTION_ANNO_TAGS_LINK_TAXONOMY, 'none');
                    foreach ($taxonomies as $taxonomy) {
                        echo '<option value="' . esc_attr($taxonomy->name) . '" ' . selected($current_selection, $taxonomy->name, false) . '>' . esc_html($taxonomy->labels->name) . '</option>';
                    }
                    ?>
                </select>
                <p class="description">This setting syncs the tag vocabulary with a WordPress taxonomy. <br><strong>***Tag linkage is NOT retroactive</strong>.</p>
            </div>
        </div>
        <?php
    }

    public function add_settings_page() { 
        add_options_page(
            'Openseadragon-Annotorious Settings', 
            'Openseadragon-Annotorious', 
            'manage_options', 
            'arwai-openseadragon-settings', 
            array( $this, 'settings_page_html' )
        ); 
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php 
                submit_button( 'Save Settings' );
                settings_fields( 'arwai_openseadragon_options_group' ); 
                do_settings_sections( 'arwai-openseadragon-settings' ); 
                submit_button( 'Save Settings' ); 
                ?>
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
            
            // UPDATED: Now fetches both full image and thumbnail URLs
            $image_sources = array_reduce( $image_ids, function($carry, $id) {
                $full_src = wp_get_attachment_image_src( $id, 'full' );
                $thumb_src = wp_get_attachment_image_src( $id, 'thumbnail' );
                if ($full_src) {
                    $carry[] = [
                        'type'         => 'image',
                        'url'          => $full_src[0],
                        'post_id'      => $id,
                        'thumbnailUrl' => $thumb_src ? $thumb_src[0] : '' // Pass thumbnail URL
                    ];
                }
                return $carry;
            }, []);

            if (!empty($image_sources)) {
                // Enqueue Styles
                wp_enqueue_style( 'dashicons' );
                wp_enqueue_style( 'arwai-annotorious-css', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/css/annotorious/annotorious.min.css');
                wp_enqueue_style( 'arwai-public-css', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/css/public/public.css'); // <-- THIS LINE IS ESSENTIAL


                // Enqueue Scripts
                wp_enqueue_script( 'arwai-openseadragon-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/openseadragon/openseadragon.min.js', array(), null, true );
                wp_enqueue_script( 'arwai-annotorious-core-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/annotorious/annotorious.min.js', array(), null, true );
                wp_enqueue_script( 'arwai-annotorious-osd-plugin-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/annotorious/openseadragon-annotorious.min.js', array( 'arwai-openseadragon-js', 'arwai-annotorious-core-js' ), null, true );
                wp_enqueue_script( 'arwai-public-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/public/script.js', array('jquery', 'arwai-annotorious-osd-plugin-js'), null, true);
                
                // Viewer Configuration
                $viewer_config = [
                    'id' => 'openseadragon-viewer-' . $post_id, 
                    'images' => $image_sources, 
                    'currentPostId' => $post_id
                ];
               
                // --- Prepare All Options ---
                $osd_options = [];
                foreach($this->osd_options_keys as $key => $props) {
                    $osd_options[$key] = get_option('arwai_osd_' . $key, $props['default']);
                    if ($props['type'] === 'boolean') {
                         $osd_options[$key] = rest_sanitize_boolean($osd_options[$key]);
                    }
                }
                 $osd_options['prefixUrl'] = ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/images/';


                // Gesture Settings
                $osd_options['gestureSettingsMouse'] = [];
                $osd_options['gestureSettingsTouch'] = [];
                $device_types = ['mouse', 'touch'];
                foreach($device_types as $device) {
                    foreach($this->gesture_settings_keys as $key => $props) {
                        $option_name = 'arwai_osd_gesture_' . $device . '_' . $key;
                        $value = get_option($option_name, $props['default']);
                        $osd_options['gestureSettings' . ucfirst($device)][$key] = ($props['type'] === 'boolean') ? rest_sanitize_boolean($value) : $value;
                    }
                }

                // Annotorious options
                $linked_taxonomy = get_option(self::OPTION_ANNO_TAGS_LINK_TAXONOMY, 'none');
                $current_user_data = null;
                if ( is_user_logged_in() ) {
                    $user = wp_get_current_user();
                    $current_user_data = [
                        'id' => $user->ID,
                        'displayName' => $user->display_name,
                    ];
                }

                // Get translations from settings, decode from JSON string to PHP array
                $translations_json = get_option(self::OPTION_ANNO_TRANSLATIONS);
                $translations_array = json_decode($translations_json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $translations_array = []; // Fallback to empty if saved JSON is invalid
                }

                $anno_options = [
                    'locale' => get_option(self::OPTION_ANNO_LOCALE, 'en-alt'),
                    'readOnly' => rest_sanitize_boolean(get_option(self::OPTION_ANNO_READ_ONLY, false)),
                    'allowEmpty' => rest_sanitize_boolean(get_option(self::OPTION_ANNO_ALLOW_EMPTY, false)),
                    'drawOnSingleClick' => rest_sanitize_boolean(get_option(self::OPTION_ANNO_DRAW_ON_SINGLE_CLICK, false)),
                    'linkTaxonomy' => $linked_taxonomy,
                    'addTermNonce' => wp_create_nonce( 'arwai_add_term_nonce' ),
                    'tagVocabulary' => [],
                    'currentUser' => $current_user_data,
                    'translations' => $translations_array, // <-- PASS TRANSLATIONS TO JS
                ];

                if ($linked_taxonomy !== 'none') {
                    $terms = get_terms(['taxonomy' => $linked_taxonomy, 'hide_empty' => false]);
                    if (!is_wp_error($terms) && !empty($terms)) {
                        $anno_options['tagVocabulary'] = wp_list_pluck($terms, 'name');
                    }
                }

                // Filter out null values from OSD options before localizing
                $osd_options = array_filter($osd_options, function($value) {
                    return !is_null($value);
                });

                // Localize all scripts
                wp_localize_script( 'arwai-public-js', 'ArwaiOSD_ViewerConfig', $viewer_config );
                wp_localize_script( 'arwai-public-js', 'ArwaiOSD_Options', ['osd_options' => $osd_options, 'anno_options' => $anno_options]);
                wp_localize_script( 'arwai-public-js', 'ArwaiOSD_Vars', ['ajax_url' => admin_url( 'admin-ajax.php' )] );
            }
        }
    }
}
    

    public function load_admin_scripts($hook_suffix) {
        $is_settings_page = $hook_suffix === 'settings_page_arwai-openseadragon-settings';
        $is_post_edit_page = in_array($hook_suffix, array('post.php', 'post-new.php'));

        if ($is_settings_page) {
             wp_enqueue_script('arwai-admin-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/admin/admin.js', array('jquery', 'wp-color-picker'), null, true);
             wp_enqueue_style('wp-color-picker');
             wp_enqueue_style('arwai-admin-css', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/css/admin/admin.css');
        }
        
        if ($is_post_edit_page) {
            $screen = get_current_screen();
            if ( $screen && in_array( $screen->post_type, $this->get_active_post_types() ) ) {
                wp_enqueue_script('arwai-admin-js', ARWAI_OPENSEADRAGON_ANNOTORIOUS_URL . 'assets/js/admin/admin.js', array('jquery', 'jquery-ui-sortable'), null, true);
                wp_enqueue_media();
            }
        }
    }
    // start
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
                    
                    // NEW: Added a wrapper div and the custom reference strip container
                    $viewer_html = "
                    <div class='arwai-viewer-wrapper'>
                    <div class='arwai-viewer-frame' style='border-radius: 20px; overflow: hidden;'>
                        <div id='" . esc_attr( $viewer_id ) . "' style='width: 100%; height: 600px; border-radius: 20px; background-color: " . esc_attr(get_option('arwai_osd_backgroundColor', '#000000')) . ";'></div>
                    </div>
                        <div class='arwai-custom-reference-strip-container'>
                            <button id='arwai-strip-scroll-left' class='arwai-strip-scroll-arrow'>
                            <span class='dashicons dashicons-arrow-left-alt2'></span>
                            </button>

                                <div id='arwai-custom-reference-strip' class='arwai-custom-reference-strip'></div>

                            <button id='arwai-strip-scroll-right' class='arwai-strip-scroll-arrow'>
                            <span class='dashicons dashicons-arrow-right-alt2'></span>
                            </button>
                        </div>

                    </div>";

                    return $viewer_html . $content;
                }
            }
            return $content;
        }
// end
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

        // Save "Set first image in collection as the post's Featured Image"
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
    
    function arwai_add_taxonomy_term() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to add new tags.' );
        }
        check_ajax_referer( 'arwai_add_term_nonce', 'nonce' );

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

        if ( empty($taxonomy) || empty($term) ) {
            wp_send_json_error('Missing taxonomy or term.');
        }

        if ( ! taxonomy_exists($taxonomy) ) {
            wp_send_json_error('Taxonomy does not exist.');
        }
        
        $tax_object = get_taxonomy($taxonomy);
        if ( ! current_user_can($tax_object->cap->manage_terms) ) {
            wp_send_json_error('User does not have permission to add terms.');
        }

        if ( term_exists( $term, $taxonomy ) ) {
            wp_send_json_success('Term already exists.');
        }

        $result = wp_insert_term( $term, $taxonomy );

        if ( is_wp_error($result) ) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(['term_id' => $result['term_id']]);
        }
    }

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
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to create annotations.' );
        }

        global $wpdb;
        $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';
        if (empty($annotation_json)) { wp_send_json_error('Annotation data missing.'); }
        
        $annotation = json_decode($annotation_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) { wp_send_json_error('Invalid JSON data.'); }

        $image_url = $annotation['target']['source'] ?? '';
        if (empty($image_url)) { wp_send_json_error('Annotation target source URL missing.'); }
        
        $attachment_id = attachment_url_to_postid($image_url);
        if (empty($attachment_id)) { wp_send_json_error('Could not find attachment ID for source URL.'); }
        
        $annotation_id_from_annotorious = $annotation['id'] ?? '';
        if (empty($annotation_id_from_annotorious)) { wp_send_json_error('Annotorious ID missing.'); }
        
        // Sanitize comment body if it exists
        if (isset($annotation['body']) && is_array($annotation['body'])) {
            foreach ($annotation['body'] as $key => $body_item) {
                if (isset($body_item['purpose']) && $body_item['purpose'] === 'commenting' && isset($body_item['value'])) {
                    $annotation['body'][$key]['value'] = wp_kses_post($body_item['value']);
                }
            }
        }
        
        $inserted = $wpdb->insert(
            $this->table_name,
            array(
                'annotation_id_from_annotorious' => $annotation_id_from_annotorious,
                'attachment_id' => $attachment_id,
                'annotation_data' => wp_json_encode($annotation)
            ),
            array('%s', '%d', '%s')
        );

        if ($inserted) {
            $new_db_id = $wpdb->insert_id;

            $arwai_id_body = [
                'type'    => 'TextualBody',
                'purpose' => 'arwai-AnnotationID',
                'value'   => (string) $new_db_id,
            ];

            if (!isset($annotation['body']) || !is_array($annotation['body'])) {
                $annotation['body'] = [];
            }
            $annotation['body'][] = $arwai_id_body;

            $wpdb->update(
                $this->table_name,
                ['annotation_data' => wp_json_encode($annotation)],
                ['id' => $new_db_id],
                ['%s'],
                ['%d']
            );

            $wpdb->insert( $this->history_table_name, array(
                'annotation_id_from_annotorious' => $annotation_id_from_annotorious, 
                'attachment_id' => $attachment_id, 
                'action_type' => 'created', 
                'annotation_data_snapshot' => wp_json_encode($annotation), 
                'user_id' => get_current_user_id()
            ), array('%s', '%d', '%s', '%s', '%d') );
            
            // Send the complete, updated annotation back to the frontend
            wp_send_json_success(['annotation' => $annotation]);

        } else {
            wp_send_json_error(['message' => 'Failed to add annotation.']);
        }
        
        wp_die();
    }


    function anno_delete() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to delete annotations.' );
        }

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
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to update annotations.' );
        }
        
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