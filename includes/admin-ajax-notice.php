<?php

    /**
     * Serves up a notice to leave a review for this plugin
     *
     * @link http://wp.tutsplus.com/tutorials/creative-coding/a-primer-on-ajax-in-the-wordpress-dashboard-requesting-and-responding/
     * @link http://wptheming.com/2011/08/admin-notices-in-wordpress/
     *
     * @since 3.0
     *
     */

    add_action( 'admin_notices', 'wprss_display_admin_notice' );
    /**
     * Renders the administration notice. Also renders a hidden nonce used for security when processing the Ajax request.
     *
     * @since 3.0
     */
    function wprss_display_admin_notice() {
        // If not an admin, do not show the notification
        if ( ! current_user_can( 'manage_options' ) ) return;

        global $pagenow, $typenow;
        if ( empty( $typenow ) && !empty( $_GET['post'] ) ) {
          $post = get_post( $_GET['post'] );
          if ( $post !== NULL && !is_wp_error( $post ) )
            $typenow = $post->post_type;
        }
        $notices_settings = get_option( 'wprss_settings_notices' );

        if ( ( false == $notices_settings ) && ( ( $typenow == 'wprss_feed' ) || ( $typenow == 'wprss_feed_item' ) ) ) {
            $html = '<div id="ajax-notification" class="updated">';
                $html .= '<p>';
                $html .= __( 'Did you know that you can get more RSS features? Excerpts, thumbnails, keyword filtering, importing into posts and more... ', WPRSS_TEXT_DOMAIN );
                $html .= __( 'Check out the', WPRSS_TEXT_DOMAIN ) . ' <a target="_blank" href="http://www.wprssaggregator.com/extensions"><strong>' . __( 'extensions', 'WPRSS_TEXT_DOMAIN' ) . '</strong></a> ' . __( 'page.', WPRSS_TEXT_DOMAIN );
                $html .= '<a href="javascript:;" id="dismiss-ajax-notification" style="float:right;">' . __( 'Dismiss this notification', WPRSS_TEXT_DOMAIN ) . '</a>';
                $html .= '</p>';
                $html .= '<span id="ajax-notification-nonce" class="hidden">' . wp_create_nonce( 'ajax-notification-nonce' ) . '</span>';
            $html .= '</div>';

            echo $html;
        }
    }


    add_action( 'wp_ajax_wprss_hide_admin_notification', 'wprss_hide_admin_notification' );
    /**
     * JavaScript callback used to hide the administration notice when the 'Dismiss' anchor is clicked on the front end.
     *
     * @since 3.0
     */
    function wprss_hide_admin_notification() {

        // First, check the nonce to make sure it matches what we created when displaying the message.
        // If not, we won't do anything.
        if( wp_verify_nonce( $_REQUEST['nonce'], 'ajax-notification-nonce' ) ) {

            // If the update to the option is successful, send 1 back to the browser;
            // Otherwise, send 0.
            $general_settings = get_option( 'wprss_settings_notices' );
            $general_settings = true;

            if( update_option( 'wprss_settings_notices', $general_settings ) ) {
                die( '1' );
            } else {
                die( '0' );
            }
        }
    }



    /**
     * Checks if the addon notices option exists in the database, and creates it
     * if it does not.
     *
     * @return The addon notices option
     * @since 3.4.2
     */
    function wprss_check_addon_notice_option() {
        $option = get_option( 'wprss_addon_notices' );
        if ( $option === FALSE ) {
            update_option( 'wprss_addon_notices', array() );
            return array();
        }
        return $option;
    }



    /**
     * This function is called through AJAX to dismiss a particular addon notification.
     *
     * @since 3.4.2
     */
    function wprss_dismiss_addon_notice() {
        $addon = ( isset( $_POST['addon'] ) === TRUE )? $_POST['addon'] : null;
        if ( $addon === null ) {
            echo 'false';
            die();
        }
        $notice = ( isset( $_POST['notice'] ) === TRUE )? $_POST['notice'] : null;
        if ( $notice === null ){
            echo 'false';
            die();
        }

        $notices = wprss_check_addon_notice_option();
        if ( isset( $notices[$addon] ) === FALSE ) {
            $notices[$addon] = array();
        }
        if ( isset( $notices[$addon][$addon] ) === FALSE ) {
            $notices[$addon][$notice] = '1';
        }
        update_option( 'wprss_addon_notices', $notices );
        echo 'true';

        die();
    }

    add_action( 'wp_ajax_wprss_dismiss_addon_notice', 'wprss_dismiss_addon_notice' );




    /**
     * AJAX action for the tracking pointer
     *
     * @since 3.6
     */
    function wprss_tracking_ajax_opt() {
        if ( isset( $_POST['opted'] ) ){
            $opted = $_POST['opted'];
            $settings = get_option( 'wprss_settings_general' );
            $settings['tracking'] = $opted;
            update_option( 'wprss_settings_general', $settings );
        }
        die();
    }

    add_action( 'wp_ajax_wprss_tracking_ajax_opt', 'wprss_tracking_ajax_opt' );


/**
 * Responsible for tracking and outputting admin notices
 *
 * @since [*next-version*]
 */
class WPRSS_Admin_Notices {

	// How should a set of conditions be evaluated
	const CONDITION_TYPE_ALL = 'all'; // Requires all conditions to be true
	const CONDITION_TYPE_ANY = 'any'; // Requires one condition to be true
	const CONDITION_TYPE_NONE = 'none'; // Requires none of the conditions to be true
	const CONDITION_TYPE_ALMOST = 'almost'; // Requires at least one of the conditions to be false

	// What happens if a condition encounters an error
//	const CONDITION_ON_ERROR_STOP_FALSE = 'stop_false'; // Assume that the condition was not satisfied, and do not evaluate other conditions
//	const CONDITION_ON_ERROR_STOP_TRUE = 'stop_true'; // Assume that the condition was satisfied, and do not evaluate other conditions
//	const CONDITION_ON_ERROR_CONTINUE_FALSE = 'continue_false'; // Assume that the condition was not satisfied, and continue evaluating
//	const CONDITION_ON_ERROR_CONTINUE_TRUE = 'continue_true'; // Assume that the condition was satisfied, and continue evaluating
	const CONDITION_ON_ERROR_THROW_EXCEPTION = 'throw_exception'; // Just halt


	protected $_notices = array();
	protected $_setting_code;
	protected $_id_prefix;
	protected $_text_domain;
	protected $_notice_base_class;
	protected $_nonce_base_class;
	protected $_btn_close_base_class;


	/**
	 *
	 * @since [*next-version*]
	 * @param null|array The settings of this instance.
	 *  Possible values are:
	 *		- 'setting_code': The code of the database setting used for storing and managing notices of this instance.
	 *			See {@link set_setting_code()}.
	 *			If a string is passed as data, this is what it is assumed to be.
	 *		- 'id_prefix': The prefix of all IDs generated by this instance.
	 *			See {@link set_id_prefix()}.
	 *		- 'text_domain': The text domain to use for translation.
	 *			See {@link set_text_domain()}.
	 *		- 'notice_base_class': The class for all notice elements.
	 *			See {@link set_notice_base_class()}.
	 *		- 'nonce_base_class': The class for all notice nonce elements.
	 *			See {@link set_nonce_base_class()}.
	 */
	public function __construct( $data = array() ) {
		if ( is_string( $data ) )
			$data = array( 'setting_code' => $data );

		if ( isset( $data['setting_code'] ) )
			$this->set_setting_code (  $data['setting_code'] );

		if ( isset( $data['id_prefix'] ) )
			$this->set_id_prefix( $data['id_prefix'] );

		if ( isset( $data['text_domain'] ) )
			$this->set_text_domain( $data['text_domain'] );

		// Common class for all notices
		if ( !isset( $data['notice_base_class'] ) )
			$data['notice_base_class'] = $this->prefix( 'admin-notice' );
		$this->set_notice_base_class( $data['notice_base_class'] );

		// Common class for all nonces
		if ( !isset( $data['nonce_base_class'] ) )
			$data['nonce_base_class'] = $this->prefix( 'admin-notice-nonce' );
		$this->set_nonce_base_class( $data['nonce_base_class'] );

		// Common class for all close buttons
		if ( !isset( $data['btn_close_base_class'] ) )
			$data['btn_close_base_class'] = $this->prefix( 'admin-notice-btn-close' );
		$this->set_btn_close_base_class( $data['btn_close_base_class'] );

		$this->_construct();
	}


	/**
	 * Internal, parameter-less constructor.
	 *
	 * @since [*next-version*]
	 */
	protected function _construct() {

	}


	public function init() {
		do_action( $this->prefix( 'admin_notice_before_init' ), $this );
		add_action( 'admin_notices', array( $this, 'output_allowed_notices' ) );
		do_action( $this->prefix( 'admin_notice_after_init' ), $this );

		return $this;
	}


	/**
	 * Get the ID prefix, or a prefixed string.
	 *
	 * This function is also used internally by this class to prefix generated
	 * IDs that are specific to this instance.
	 * Currently, this prefix is used in HTML of the notices, and in names
	 * of hooks.
	 *
	 * @param null|string $string The string to prefix.
	 * @return string The prefix, or prefixed string
	 */
	public function prefix( $string = null ) {
		$prefix = (string)$this->_id_prefix;
		return is_null( $string ) ? $prefix : $prefix . $string;
	}


	/**
	 * Sets a prefix that will be added to IDs specific to this collection.
	 *
	 * @since [*next-version*]
	 * @param string $prefix The prefix to set.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function set_id_prefix( $prefix ) {
		$this->_id_prefix = $prefix;
		return $this;
	}


	/**
	 * Set the name of the setting to store the notices in.
	 *
	 * @since [*next-version*]
	 * @see get_setting_name()
	 * @param string $name The name of the notices setting to use.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function set_setting_code( $name ) {
		$this->_setting_code = $name;
		return $this;
	}


	/**
	 * Get the name of the notices setting.
	 *
	 * @since [*next-version*]
	 * @see set_setting_name()
	 * @return string The name of the setting which stores notices and their states.
	 */
	public function get_setting_name() {
		return $this->_setting_code;
	}


	/**
	 * Retrieve the text domain that is used for translation by this instance.
	 *
	 * @since [*next-version*]
	 * @return string The text domain.
	 */
	public function get_text_domain() {
		return $this->_text_domain;
	}


	/**
	 * Set the text domain that is used for translation by this instance.
	 *
	 * @since [*next-version*]
	 * @param string $text_domain The text domain.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function set_text_domain( $text_domain ) {
		$this->_text_domain = $text_domain;
		return $this;
	}


	/**
	 * Get the class that is the base, common class for all notices' top HTML elements.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_base_class To modify return value.
	 * @return string The class common to all notices
	 */
	public function get_notice_base_class() {
		return apply_filters( $this->prefix( 'admin_notice_base_class' ), $this->_notice_base_class );
	}


	/**
	 * Set the class that will be the base, common class for all notices' top HTML elements.
	 *
	 * @since [*next-version*]
	 * @param string $class The class name that will be common to all notices.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function set_notice_base_class( $class ) {
		$this->_notice_base_class = $class;
		return $this;
	}


	/**
	 * Get the class that is the base, common class for all notices' nonces' HTML elements.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_nonce_base_class To modify return value.
	 * @return string The class common to all nonces
	 */
	public function get_nonce_base_class() {
		return apply_filters( $this->prefix( 'admin_notice_nonce_base_class' ), $this->_nonce_base_class );
	}


	/**
	 * Set the class that will be the base, common class for all notices' nonces' HTML elements.
	 *
	 * @since [*next-version*]
	 * @param string $class The class name that will be common to all nonces.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function set_nonce_base_class( $class ) {
		$this->_nonce_base_class = $class;
		return $this;
	}


	/**
	 * Get the class that is the base, common class for all notices' close buttons' HTML elements.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_btn_close_base_class To modify return value.
	 * @return string The class common to all close buttons
	 */
	public function get_btn_close_base_class() {
		return apply_filters( $this->prefix( 'admin_notice_btn_close_base_class' ), $this->_btn_close_base_class );
	}


	/**
	 * Set the class that will be the base, common class for all notices' close buttons' HTML elements.
	 *
	 * @since [*next-version*]
	 * @param string $class The class name that will be common to close buttons.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function set_btn_close_base_class( $class ) {
		$this->_btn_close_base_class = $class;
		return $this;
	}


	/**
	 * Adds an admin notice.
	 *
	 * - If 'id' is not passed, a unique ID will be auto-generated.
	 * - If 'nonce' is not passed, a nonce will be auto-generated based on the ID.
	 * - A 'condition' is one or more callbacks. If none are passed, the notice will be displayed on all admin pages.
	 * - A 'condition_type' is one of the CONDITION_TYPE_* class constants. By default, all conditions have to be true.
	 * - The 'class' index determinces what type of notice it is. Currently, the valid values are 'updated', 'error'  and 'update-nag'. See https://codex.wordpress.org/Plugin_API/Action_Reference/admin_notices
	 * - The 'content index is the literal content of the notice.
	 * - If 'btn_close_id' is not passed, it will be auto-generated based on the ID.
	 * - The 'btn_close_class' index determines the class that the close button will have, in addition to the default 'btn-close'.
	 * - The 'btn_close_content' index determines the literal content of the element of the close button. HTML allowed.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_add_before_normalize To allow pre-normalization modification of notice.
	 * @uses-filter admin_notice_add_before To allow post-normalization modification of notice.
	 * @uses-action admin_notice_add_after To expose data of added notice. This will not be fired if notice was not added.
	 * @param array $notice Data of the notice to add.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function add_notice( $notice ) {
		$notice = apply_filters( $this->prefix( 'admin_notice_add_before_normalize' ), $notice, $this );
		$notice = $this->normalize_notice_data( $notice );
		$notice = apply_filters( $this->prefix( 'admin_notice_add_before' ), $notice, $this );
		$this->set_notice( $notice );
		do_action( $this->prefix( 'admin_notice_add_after' ), $notice, $this );

		return $this;
	}


	/**
	 * Sets the data for a notice with the specified ID.
	 *
	 * No normalization or checks are made, except for the presence of an ID.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_set_before To alter the data of the notice before setting.
	 * @uses-action admin_notice_set_after To expose the data of the notice after setting.
	 * @param array $notice Data of the notice.
	 * @param null|string $id The ID of the notice. If set, overrides 'id' index in notice data.
	 * @return \WPRSS_Admin_Notices This instance.
	 * @throws Exception If ID is missing.
	 */
	public function set_notice( $notice, $id = null ) {
		$notice = apply_filters( $this->prefix( 'admin_notice_set_before' ), $notice, $id, $this );
		$id = isset( $notice['id'] ) ? $notice['id'] : $id;
		if ( is_null( $id ) )
			throw new Exception( 'Could not set admin notice: ID must be specified in either notice data, or as separate argument' );

		$this->_notices[ $id ] = $notice;
		do_action( $this->prefix( 'admin_notice_set_after' ), $notice, $id, $this );
		return $this;
	}


	/**
	 * Normalize data of a notice, adding defaults.
	 *
	 * Auto-generating 'id', 'nonce', 'btn_close_id'.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_defaults Default values, before addin auto-generated values.
	 * @uses-filter admin_notice_defaults_autogenerated Default values, after adding auto-generated values.
	 * @param array $data The notice data to normalize
	 * @return array $data The normalized data of a notice
	 */
	public function normalize_notice_data( $data ) {
		$data = wp_parse_args( $data, apply_filters( $this->prefix( 'admin_notice_defaults' ), array(
			'id'					=> null, // ID of the notice. Unique for the notice in this collection.
			'nonce'					=> null, // Nonce for the notice. Prevents unauthorised manipulation.
			'condition'				=> array(), // These callbacks will decide whether or not the nonce is to be displayed
			'condition_type'		=> self::CONDITION_TYPE_ALL, // Which of the conditions have to be satisfied
			'conditon_on_error'		=> self::CONDITION_ON_ERROR_THROW_EXCEPTION,
			'is_active'				=> true, // Whether this notice should be assumed to be active, unless set otherwise
			'nonce_element_class'	=> $this->prefix( 'admin-notice-nonce' ), // HTML class for the element that contains the nonce
			'nonce_element_id'		=> null,
			'class'					=> '', // HTML class for the element of the notice
			'notice_type'			=> 'updated', // Type of the notice.
			'notice_element_class'	=> $this->prefix( 'admin-notice' ),
			'content'				=> '', // The content of this notice
			'btn_close_id'			=> null, // The HTML ID for the close button
			'btn_close_class'		=> 'btn-close', // The HTML class for the close button, in addition to default
			'btn_close_content'	 => __( 'Dismiss this notification', $this->get_text_domain() ), // The content of the close button. HTML allowed.
		)));

		// Auto-generate ID
		if ( is_null( $data['id'] ) )
			$data['id'] = $this->generate_unique_id( 'admin-notice-' );

		// Prefix ID
		$data['id'] = $this->prefix( $data['id'] );

		// Auto-generate nonce
		if ( is_null( $data['nonce'] ) && !is_null( $data['id'] ) ) {
			$data['nonce'] = $this->generate_nonce_for_notice( $data['id'] );
		}

		// Auto-generate nonce element ID
		if ( is_null( $data['nonce_element_id'] ) && !is_null( $data['id'] ) )
			$data['nonce_element_id'] = sprintf( '%1$s-nonce', $data['id'] );

		// Auto-generate close button ID
		if ( is_null( $data['btn_close_id'] ) && !is_null( $data['id'] ) )
			$data['btn_close_id'] = sprintf( 'close-%1$s', $data['id'] );

		return apply_filters( $this->prefix( 'admin_notice_defaults_autogenerated' ), $data );
	}


	/**
	 * Removes a notice from this collection.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_remove_before To modify the notice ID that will be removed. Returning falsy value prevents removal.
	 * @uses-action admin_notice_remove_before To expose notice ID after removal.
	 * @param array|int $notice A notice, or notice ID.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function remove_notice( $notice ) {
		if ( is_array( $notice ) )
			$notice = isset( $notice['id'] ) ? $notice['id'] : null;

		if ( is_null( $notice ) )
			return $this;

		if ( !$this->has_notice( $notice ) )
			return $this;

		$notice = apply_filters( $this->prefix( 'admin_notice_remove_before' ), $notice, $this );
		if( !$notice ) return $this;

		$this->_remove_notice ( $notice, $this->_notices);
		do_action( $this->prefix( 'admin_notice_remove_before' ), $notice, $this );

		return $this;
	}


	/**
	 * Removes a notice by ID from the supplied array.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_internal_remove_before To modify notice before removal. Returning falsy value prevents removal.
	 * @uses-action admin_notice_internal_remove_after To expose data of notice after removal.
	 * @param array|string $notice A notice, or notice ID
	 * @param array $array The array, from which to remove the notice.
	 * @return \WPRSS_Admin_Notices This instance.
	 * @throws Exception If no ID specified.
	 */
	protected function _remove_notice( $notice, &$array = null ) {
		if ( is_array( $notice ) )
			$notice = isset( $notice['id'] ) ? $notice['id'] : null;

		if ( is_null( $notice ) )
			throw new Exception( 'Could not remove notice: an ID must be specified' );

		if ( is_null( $array ) )
			$array = &$this->_notices;

		if ( !array_key_exists( $notice, $array ) )
			return $this;

		$notice = apply_filters( $this->prefix( 'admin_notice_internal_remove_before' ), $notice, $array, $this );
		if ( !$notice ) return $this;

		unset( $array[ $notice ] );
		do_action( $this->prefix( 'admin_notice_internal_remove_after' ), $notice, $array, $this );

		return $this;
	}


	/**
	 * Checks whether a notice already exists.
	 *
	 * @since [*next-version*]
	 * @param array|int $notice A notice, or notice ID to check for.
	 * @return boolean True if notice already exists; false otherwise.
	 */
	public function has_notice( $notice ) {
		if ( is_array( $notice ) )
			$notice = isset( $notice['id'] ) ? $notice['id'] : null;

		return array_key_exists( $notice , $this->_notices );
	}


	/**
	 * Get all notices, or a notice with the specified ID.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_get_all To modify all notices returned.
	 * @uses-filter admin_notice_get To modify single returned notice.
	 * @param null|string $id The ID of a notice to retrieve.
	 * @param null $default What to return if notice not found.
	 * @return array Retrieve all or one notice. See {@link normalize_notice_data()} for data keys.
	 */
	public function get_notices( $id = null, $default = null ) {
		if ( is_null( $id ) )
			return apply_filters( $this->prefix( 'admin_notice_get_all' ), $this->_notices, $this );

		return apply_filters( $this->prefix( 'admin_notice_get' ),
				isset( $this->_notices[ $id ] ) ? $this->_notices[ $id ] : $default,
				$id,
				$default,
				$this );
	}


	/**
	 * Get all notices that are active.
	 *
	 * @since [*next-version*]
	 * @see is_notice_active()
	 * @return array Notices that are currently active;
	 */
	public function get_active_notices( $is_default_active = null ) {
		if ( is_null( $is_default_active ) )
			$is_default_active = true;

		$active_notices = array();
		foreach ( $this->get_notices() as $_id => $_notice ) {
			if ( $this->is_notice_active( $_notice, $is_default_active ) )
				$active_notices[ $_id ] = $_notice;
		}

		return $active_notices;
	}


	/**
	 * Determine whether the specified notice is active.
	 *
	 * If data array is passed, it's 'is_active' key will be used as default.
	 * Otherwise, data will be retrieved by ID and compared to database.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_is_active To modify return value. Used in several places.
	 * @param array|string $notice Notice or notice ID.
	 * @param boolean $default What to return if no notice state data exists.
	 * @return boolean Whether or not the specified notice is active.
	 * @throws Exception If ID not specified.
	 */
	public function is_notice_active( $notice, $default = null ) {
		// State if no state provided
		if ( is_null( $default ) )
			$default = true;

		// If ID passed, retrieve the notice
		if ( !is_array( $notice ) )
			$notice = $this->get_notices( $notice, array() );
		// Last resort defaults
		$id = isset( $notice['id'] ) ? $notice['id'] : null;
		$is_active_default = isset( $notice['is_active'] ) ? (bool)$notice['is_active'] : $default;

		if ( is_null( $id ) )
			throw new Exception( 'Could not determine notice state: ID must be specified' );

		// Settings from DB
		$settings = $this->get_notices_settings( $id );

		// If no state, assume default
		if ( !isset( $settings['is_active'] ) )
			return apply_filters( $this->prefix( 'admin_notice_is_active' ), $is_active_default, $id, $this );

		return apply_filters( $this->prefix( 'admin_notice_is_active' ), (bool)$settings['is_active'], $id, $this );
	}


	/**
	 * Set notice active state.
	 *
	 * @since [*next-version*]
	 * @param array|string $notice Notice data or ID.
	 * @param null|boolean $is_active If true, notice state will be set to active; if false - to inactive. Default: true.
	 */
	public function set_notice_active( $notice, $is_active = null ) {
		if ( is_null( $is_active ) )
			$is_active = true;

		$this->set_notices_settings( $notice, (bool)$is_active );
		return $this;
	}


	/**
	 * Gets all notices that pass their conditions according to the condition type.
	 *
	 * Allowed notices are also only active ones. Inactive notices are not evaluated.
	 *
	 * @since [*next-version*]
	 * @see is_notice_allowed()
	 * @uses-filter admin_notice_all_allowed To modify return value.
	 * @return array Allowed notices.
	 */
	public function get_allowed_notices() {
		$allowed_notices = array();
		foreach ( $this->get_active_notices() as $_id => $_notice ) {
			if ( $this->is_notice_allowed( $_notice) )
				$allowed_notices[ $_id ] = $_notice;
		}

		return apply_filters( $this->prefix( 'admin_notice_all_allowed' ), $allowed_notices, $this );
	}


	/**
	 * Checks if the specified notice is allowed.
	 *
	 * To determine that, the notice's conditions are evaluated according
	 * to the condition type.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_is_allowed To modify return value.
	 * @param array|string $notice Notice or notice ID.
	 * @return bool Whether or not the specified notice passed it's conditions to be allowed.
	 * @throws Exception If ID not specified.
	 */
	public function is_notice_allowed( $notice ) {
		if ( !is_array( $notice ) )
			$notice = $this->get_notices( $notice );

		$conditions = isset( $notice['condition'] ) ? $notice['condition'] : array();
		$condition_type = isset( $notice['condition_type'] ) ? $notice['condition_type'] : self::CONDITION_TYPE_ALL;
		$is_allowed = $this->evaluate_conditions( $conditions, $condition_type, array( $notice ) );
		return apply_filters( $this->prefix( 'admin_notice_is_allowed' ), $is_allowed, $notice );
	}


	/**
	 * Generates a nonce for a notice ID.
	 *
	 * @since [*next-version*]
	 * @see wp_create_nonce()
	 * @see generate_nonce_code()
	 * @uses-filter admin_notice_nonce_for_notice
	 * @param array|string $notice Notice or notice ID.
	 * @return string The nonce.
	 * @throws Exception If ID not specified.
	 */
	public function generate_nonce_for_notice( $notice ) {
		if ( is_array( $notice ) )
			$notice = isset( $notice['id'] ) ? $notice['id'] : null;

		if ( is_null( $notice ) )
			throw new Exception( 'Could not get nonce for notice: notice ID must be specified' );

		$nonce_code = $this->generate_nonce_code( $notice );
		$nonce = wp_create_nonce( $nonce_code );

		return apply_filters( $this->prefix( 'admin_notice_nonce_for_notice' ), $nonce, $notice, $nonce_code, $this );
	}


	/**
	 * Generates a code that is used to generate a nonce for a notice.
	 *
	 * @since [*next-version*]
	 * @see wp_create_nonce()
	 * @see generate_nonce_for_notice()
	 * @uses-filter admin_notice_nonce_code To modify return value.
	 * @param array|string $notice Notice or notice ID;
	 * @return string Code (action) for the nonce.
	 * @throws Exception If nonce ID not specified.
	 */
	public function generate_nonce_code( $notice ) {
		if ( is_array( $notice ) )
			$notice = isset( $notice['id'] ) ? $notice['id'] : null;

		if ( is_null( $notice ) )
			throw new Exception( 'Could not generate nonce code for notice: notice ID must be specified' );

		return apply_filters( $this->prefix( 'admin_notice_nonce_code' ), sprintf( '%1$s-nonce', $notice ), $notice, $this );
	}


	/**
	 * Evaluates a condition or group of conditions based on the condition type.
	 *
	 * A condition is a callable that returns true or false (no type checking is done here).
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_conditions_evaluated To modify the return value.
	 * @uses-filter admin_notice_condition_result To alter the result of each condition's evaluated.
	 * @param array|callable $conditions A callable or an array of callables.
	 * @param string $condition_type One of the CONDITION_TYPE_* class constants. Default: CONDITION_TYPE_ALL.
	 * @param array $args These args will be passed to the condition callable.
	 * @return boolean Whether or not the conditions evaluate according to the condition type.
	 * @throws Exception If a condition cannot be called.
	 */
	public function evaluate_conditions( $conditions, $condition_type = self::CONDITION_TYPE_ALL, $args = array() ) {
		$event_name = $this->prefix( 'admin_notice_conditions_evaluated' );
		if ( empty( $conditions ) ) return apply_filters ( $event_name, true, $condition_type, $this ); // Unconditional ;)
		if ( !is_array( $conditions ) ) $conditions = (array)$conditions; // Normalizing

		foreach ( $conditions as $_idx => $_condition ) {
			$func = is_array( $_condition ) && isset( $_condition['func'] )
					? $_condition['func']
					: $_condition;
			$args = array_merge( // Appending our args to the passed ars
						array_values( isset( $_condition['args'] ) ? (array)$_condition['args'] : array() ),
						array_values( $args ));

			if ( !is_callable( $func ) )
				throw new Exception ( sprintf( 'Could not evaluate condition %1$d: condition must contain a callable', $_idx ) );

			$_value = call_user_func_array( $func, $args );
			$_value = apply_filters( $this->prefix( 'admin_notice_condition_result' ), $_value, $_idx, $condition_type, $_condition, $args, $this );
			switch ( $condition_type ) {
				case self::CONDITION_TYPE_ANY: // At least one must be true
					if ( (bool)$_value ) return apply_filters ( $event_name, true, $condition_type, $this );
					$result = false;
					break;

				case self::CONDITION_TYPE_ALMOST: // At least one must be false
					if ( !(bool)$_value ) return  apply_filters ( $event_name, true, $condition_type, $this );
					$result = false;
					break;

				case self::CONDITION_TYPE_NONE: // All must be false
					if ( (bool)$_value ) return apply_filters ( $event_name, false, $condition_type, $this );
					$result = true;
					break;

				default:
				case self::CONDITION_TYPE_ALL: // All must be true
					if ( !(bool)$_value ) return apply_filters ( $event_name, false, $condition_type, $this );
					$result = true;
					break;
			}
		}

		return apply_filters ( $event_name, $result, $condition_type, $this );
	}


	/**
	 * Get settings for all notices, or just one.
	 *
	 * It appears that options are already being cached by WP.
	 * Also, some notices in the returned array may not be registered for display,
	 * in which case they will not be displayed. And vice-versa: some of the registered
	 * notices will not have any settings associated with them, in which case
	 * defaults are assumed. See {@link is_notice_active()} for information.
	 * The settings contain states, not notice information.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_get_notices_settings_all To modify settings of all notices.
	 * @uses-filter admin_notice_get_notices_settings To modify settings of just one notice. May have been modified by admin_notice_get_notices_settings_all.
	 * @param null|string $id Notice ID
	 * @param null|mixed $default What to return if no settings for notice. Default: empty array.
	 * @return array An array, where key is notice ID, and value is boolean indicating whether or not it is active.
	 */
	public function get_notices_settings( $id = null, $default = null ) {
		if( is_null( $default ) )
			$default = array();

		$settings = apply_filters( $this->prefix( 'admin_notice_get_notices_settings_all' ),
				get_option( $this->get_setting_name(), array() ),
				$this );

		if ( is_null( $id ) )
			return $settings;

		// Normalize
		$settings = isset( $settings[ $id ] ) ? $settings[ $id ] : $default;
		$settings = $this->normalize_notice_data_from_db( $settings );

		return apply_filters( $this->prefix( 'admin_notice_get_notices_settings' ),
				$settings,
				$id,
				$default,
				$this );
	}


	/**
	 *
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_set_settings_before To modify what gets saved. Also see {@link prepare_notice_data_for_db()}.
	 * @uses-action admin_notice_set_settings_after To expose data that has been saved.
	 * @param string|array $notice The notice data, or notice ID, to save.
	 * @param null|array|boolean $settings The settings, or just the active state, to save.
	 * @return \WPRSS_Admin_Notices This instance.
	 * @throws Exception If an ID is nowhere to be found. How to save? :S
	 */
	public function set_notices_settings( $notice, $settings = null ) {
		// Normalizing notice data
		if ( !is_array( $notice ) )
			$notice = array( 'id' => $notice );
		// If using just the notice data to save everything
		if ( is_null( $settings ) )
			$settings = $notice;
		// If saving just the active state
		if ( is_bool( $settings ) )
			$settings = array( 'is_active' => $settings );

		// Making sure notice ID isn't overwritten
		if( isset( $settings['id'] ) )
			unset( $settings['id'] );

		// Merging the data together to get all data to save
		$settings = wp_parse_args( $settings, $notice );

		// Making sure that an ID ultimately exists
		if ( !isset( $settings['id'] ) )
			throw new Exception( 'Could not set notice settings: ID must be specified' );

		$id = $settings['id'];
		$db_settings = $this->get_notices_settings( $id );

		// Merge again to only update what is in the database
		$settings = wp_parse_args( $settings, $db_settings );

		// Get all settings data
		$all_settings = $this->get_notices_settings();
		// Set and finally save
		$settings = apply_filters( $this->prefix( 'admin_notice_set_settings_before' ), $settings, $id, $this );
		$settings = $this->prepare_notice_data_for_db( $settings );
		$all_settings[ $id ] = $settings;
		$this->set_notices_settings_all( $all_settings );
		do_action( $this->prefix( 'admin_notice_set_settings_after' ), $settings, $id, $this );

		return $this;
	}


	/**
	 * Saves the data of all specified notices to the database.
	 *
	 * The passed data will replace all data currently stored in the option.
	 *
	 * @since [*next-version*]
	 * @see get_setting_name()
	 * @uses-filter admin_notice_set_settings_all_before To modify what gets saved.
	 * @uses-action admin_notice_set_settings_all_after To expose saved data.
	 * @param array $settings An array containing data of all notices.
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function set_notices_settings_all( $settings ) {
		$settings = apply_filters( $this->prefix( 'admin_notice_set_settings_all_before' ), $settings, $this );
		update_option( $this->get_setting_name(), $settings );
		do_action( $this->prefix( 'admin_notice_set_settings_all_after' ), $settings, $this );

		return $this;
	}


	/**
	 * Normalize a single notice's data that was returned from the database.
	 *
	 * @since[*next-version*]
	 * @uses-filter admin_notice_normalize_notice_data_from_db To modify the return value.
	 * @param null|mixed|array $data The individual notice's data to normalize.
	 * @return array The notice data returned from the database.
	 */
	public function normalize_notice_data_from_db( $data ) {
		if ( is_null( $data ) )
			$data = array();

		if ( !is_array( $data ) )
			$data = array( 'is_active' => (bool)$data );

		return apply_filters( $this->prefix( 'admin_notice_normalize_notice_data_from_db' ), $data, $this );
	}


	/**
	 * Prepares data of a single notice to be saved to the database.
	 *
	 * Is responsible for preserving only allowed fields, and adding some
	 * required ones, if necessary and possible.
	 *
	 * @since[*next-version*]
	 * @uses-filter admin_notice_prepare_notice_data_for_db To modify the resulting prepared data.
	 * @param array $data The data to prepare.
	 * @return array The data that should be saved to the database.
	 */
	public function prepare_notice_data_for_db( $data ) {
		$prepared_data = array();
		if ( isset( $data['is_active'] ) )
			$prepared_data['is_active'] = (bool)$data['is_active'];

		return apply_filters( $this->prefix( 'admin_notice_prepare_notice_data_for_db' ), $prepared_data, $data, $this );
	}


	/**
	 * Generates a unique ID.
	 *
	 * This ID will be unique to this collection.
	 *
	 * @since [*next-version*]
	 * @see uniqid()
	 * @uses-filter admin_notice_generate_unique_id To allow modification of ID.
	 * @param string $prefix The prefix to give to the generated ID.
	 * @return string A notice ID unique to this instance in the scope of this collection.
	 */
	public function generate_unique_id( $prefix = '' ) {
		do {
			$id = uniqid( $prefix );
		} while ( $this->has_notice( $id ) );

		return apply_filters( $this->prefix( 'admin_notice_generate_unique_id' ), $id, $prefix, $this );
	}


	/**
	 * Generate the HTML for all allowed notices, sequentially.
	 *
	 * @since [*next-version*]
	 * @return string The rendered HTML.
	 */
	public function render_allowed_notices() {
		$output = '';
		foreach ( $this->get_allowed_notices() as $_id => $_notice ) {
			$output .= $this->render_notice( $_notice );
		}

		return $output;
	}


	/**
	 * Directly output the rendered HTML of all allowed notices.
	 *
	 * @since [*next-version*]
	 * @return \WPRSS_Admin_Notices This instance.
	 */
	public function output_allowed_notices() {
		echo $this->render_allowed_notices();
		return $this;
	}


	/**
	 * Generate the HTML of a notice.
	 *
	 * @since [*next-version*]
	 * @uses-filter admin_notice_render_before To allow modification of notice data before rendering.
	 * @uses-action admin_notice_render_after To allow injection inside the notice HTML.
	 * @uses-filter admin_notice_rendered To allow modification of rendered HTML.
	 * @param array|string $id Notice, or notice ID.
	 * @return string The HTML output of the notice.
	 * @throws Exception If no notice found for ID.
	 */
	public function render_notice( $id ) {
		$notice = is_array( $id )
				? $id
				: $this->get_notices( $id );

		if ( !$notice )
			throw new Exception( sprintf( 'Could not render notice: no notice found for ID "%1$s"' ), $id );
		ob_start();
		$notice = apply_filters( $this->prefix( 'admin_notice_render_before' ), $notice, $this );
		?>

		<div id="<?php echo $notice['id'] ?>" class="<?php echo $notice['notice_type'] ?> <?php echo $notice['notice_element_class'] ?> <?php echo $this->get_notice_base_class() ?>">
				<div class="notice-content">
					<?php echo $notice['content'] ?>
				</div>
                <a href="javascript:;" id="<?php echo $notice['btn_close_id'] ?>" style="float:right;" class="<?php echo $this->get_btn_close_base_class() ?> <?php echo $notice['btn_close_class'] ?>"><?php echo $notice['btn_close_content'] ?></a>
                <span id="<?php echo $notice['nonce_element_id'] ?>" class="hidden <?php echo $notice['nonce_element_class'] ?> <?php echo $this->get_nonce_base_class() ?>"><?php echo $notice['nonce'] ?></span>
            </div>
		<?php
		do_action( $this->prefix( 'admin_notice_render_after' ), $notice, $this );
		$output = ob_get_clean();

		return apply_filters( $this->prefix( 'admin_notice_rendered' ), $output, $notice, $this );
	}


	/**
	 * Used to hide a notice, typically responding to a frontend event.
	 *
	 * @since [*next-version*]
	 * @param array|string $notice Notice or notice ID.
	 * @param string $nonce The nonce from the frontend.
	 * @return \WPRSS_Admin_Notices This instance.
	 * @throws Exception If no notice ID specified, or no notice found for it,
	 *	or specified nonce does not belong to the notice, or the nonce is not right.
	 */
	public function hide_notice( $notice, $nonce ) {
		if ( is_array( $notice ) )
			$notice = isset( $notice['id'] ) ? $notice['id'] : null;

		if ( is_null( $notice ) )
			throw new Exception( sprintf( 'Could not hide notice: Notice ID must be specified' ) );
		if ( is_null( $nonce ) )
			throw new Exception( sprintf( 'Could not hide notice: nonce must be specified' ) );
		if ( !($notice = $this->get_notices( $notice ) ) )
			throw new Exception( sprintf( 'Could not hide notice: No notice found for ID "%1$s"', $notice ) );

		// Is it the right nonce?
		if ( $notice['nonce'] !== $nonce )
			throw new Exception( sprintf( 'Could not hide notice: Nonce "%1$s" does not belong to notice "%2$s"', $nonce, $notice_id ) );

		// Verify nonce
		if( !wp_verify_nonce( $nonce, $this->generate_nonce_code( $notice ) ) )
			throw new Exception( sprintf( 'Could not hide notice: Nonce "%1$s" is incorrect', $nonce ) );

		wprss_admin_notice_get_collection()->set_notice_active( $notice, false );

		return $this;
	}
}


// This should initialize the notice collection before anything can use it
add_action( 'init', 'wprss_admin_notice_get_collection', 9 );


/**
 * Returns the singleton, plugin-wide instane of the admin notices controller.
 * Initializes it if necessary.
 *
 * @since [*next-version*]
 * @uses-filter wprss_admin_notice_collection_before_init To modify collection before initialization.
 * @uses-filter wprss_admin_notice_collection_after_init To modify collection after initialization.
 * @uses-filter wprss_admin_notice_collection_before_enqueue_scripts To modify list of script handles to enqueue.
 * @uses-action wprss_admin_notice_collection_after_enqueue_scripts To access list of enqueued script handles.
 * @uses-filter wprss_admin_notice_collection_before_localize_vars To modify list of vars to expose to the frontend.
 * @uses-action wprss_admin_notice_collection_before_localize_vars To access list of vars exposed to the frontend.
 * @staticvar WPRSS_Admin_Notices $collection The singleton instance.
 * @return \WPRSS_Admin_Notices The singleton instance.
 */
function wprss_admin_notice_get_collection() {
	static $collection = null;

	if ( is_null( $collection ) ) {
		// Initialize collection
		$collection = new WPRSS_Admin_Notices(array(
			'setting_code'			=> 'wprss_admin_notices',
			'id_prefix'				=> 'wprss_',
			'text_domain'			=> WPRSS_TEXT_DOMAIN
		));
		$collection = apply_filters( 'wprss_admin_notice_collection_before_init', $collection );
		$collection->init();
		$collection = apply_filters( 'wprss_admin_notice_collection_after_init', $collection );

		$script_handles = apply_filters( 'wprss_admin_notice_collection_before_enqueue_scripts', array( 'wprss-admin-notifications' ), $collection );
        foreach ( $script_handles as $_idx => $_handle ) wp_enqueue_script( $_handle );
		do_action( 'wprss_admin_notice_collection_after_enqueue_scripts', $script_handles, $collection );

		// Frontend settings
		$settings = apply_filters( 'wprss_admin_notice_collection_before_localize_vars', array(
			'notice_class'				=> $collection->get_notice_base_class(),
			'nonce_class'				=> $collection->get_nonce_base_class(),
			'btn_close_class'			=> $collection->get_btn_close_base_class(),
			'action_code'				=> wprss_admin_notice_get_action_code()
		), $collection );
		wp_localize_script( 'aventura', 'adminNoticeGlobalVars', $settings);
		do_action( 'wprss_admin_notice_collection_before_localize_vars', $settings, $collection );
	}

	return $collection;
}


/**
 * Centralizes access to the name of the AJAX action handler for dismissing admin notices.
 *
 * This is necessary for configuration of the frontend.
 *
 * @since [*next-version*]
 * @uses-filter wprss_admin_notice_action_code To modify return value.
 * @return string The action code
 */
function wprss_admin_notice_get_action_code() {
	return apply_filters( 'wprss_admin_notice_action_code', 'wprss_admin_notice_hide' );
}


/**
 * Adds a notice to be displayed on top of an admin page.
 *
 * @since [*next-version*]
 * @param array $notice Data of the notice
 * @return bool|WP_Error True if notice added, or WP_Error if something went wrong.
 */
function wprss_admin_notice_add( $notice ) {
	try {
		wprss_admin_notice_get_collection()->add_notice( $notice );
	} catch ( Exception $e ) {
		return new WP_Error( 'could_not_add_admin_notice', $e->getMessage() );
	}

	return true;
}


add_action( sprintf( 'wp_ajax_%1$s', wprss_admin_notice_get_action_code() ), 'wprss_admin_notice_hide' );
/**
 * This is what handles the AJAX action of dismissing admin notices.
 *
 * @see WPRSS_Admin_Notices::hide_notice()
 * @since [*next-version*]
 */
function wprss_admin_notice_hide() {
	$notice_id = isset( $_REQUEST['notice_id'] ) ? $_REQUEST['notice_id'] : null;
	$nonce = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : null;

	try {
		wprss_admin_notice_get_collection()->hide_notice( $notice_id, $nonce );
	} catch (Exception $e) {
		// Failure
		echo $e->getMessage();
		exit();
	}

	// Success
	exit( '1' );
}

