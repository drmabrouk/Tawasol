<?php

/**
 * The REST API functionality of the plugin.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/api
 */

/**
 * The REST API functionality of the plugin.
 *
 * Handles all chat-related requests, authentication, and presence updates.
 */
class Tawasol_API_V1 {

	private $plugin_name;
	private $version;
    private $namespace;
    private $engine;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
        $this->namespace = 'tawasol/v1';
        $this->engine = new Tawasol_Chat_Engine();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/auth/check-user', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'check_user' ),
			'permission_callback' => array( $this, 'check_rate_limit' ),
		) );

		register_rest_route( $this->namespace, '/auth/login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'login' ),
			'permission_callback' => array( $this, 'check_rate_limit' ),
		) );

		register_rest_route( $this->namespace, '/auth/register', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'register' ),
			'permission_callback' => '__return_true',
		) );

        register_rest_route( $this->namespace, '/auth/check-username', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'check_username' ),
			'permission_callback' => '__return_true',
		) );

        register_rest_route( $this->namespace, '/users/search', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'search_users' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/messages/search', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'search_messages' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/conversations', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_conversations' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/conversations', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_conversation' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/conversations/(?P<id>\d+)/messages', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_messages' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/messages', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'send_message' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/messages/upload', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'upload_file' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/profile/photo', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'upload_profile_photo' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/messages/(?P<id>\d+)/read', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'mark_message_read' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/messages/(?P<id>\d+)/played', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'mark_message_played' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/presence', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_presence' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/presence/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_presence' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/profile', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_profile' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/profile', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_profile' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/profile/privacy', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_privacy' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/blocks', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_blocked_users' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/blocks', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'block_user' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/blocks/(?P<id>\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( $this, 'unblock_user' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/security/pin', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'change_pin' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/account/export', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'export_data' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/account/delete', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'delete_account' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/messages/(?P<id>\d+)', array(
			'methods'             => 'PATCH',
			'callback'            => array( $this, 'edit_message' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/messages/(?P<id>\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( $this, 'delete_message' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/messages/(?P<id>\d+)/pin', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'pin_message' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/auth/request-otp', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'request_otp' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/auth/verify-otp', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'verify_otp' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/realtime/stream', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'stream_updates' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/sessions', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_sessions' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/sessions/(?P<id>\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( $this, 'terminate_session' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

        register_rest_route( $this->namespace, '/conversations/(?P<id>\d+)/archive', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'archive_conversation' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );
	}

	/**
	 * Check if the current user has permission to perform the request.
	 *
	 * @return bool|WP_Error
	 */
    public function check_auth() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        // Enforce HTTPS in production for E2E transmission security
        if ( ! is_ssl() && defined('WP_DEBUG') && ! WP_DEBUG ) {
            return new WP_Error( 'rest_forbidden', __( 'Tawasol requires HTTPS for secure communication.', 'tawasol' ), array( 'status' => 403 ) );
        }

        return $this->check_rate_limit();
    }

    public function check_rate_limit() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'tawasol_rate_limit_' . md5( $ip );
        $requests = get_transient( $key ) ?: 0;

        if ( $requests > 100 ) { // 100 requests per minute
            return new WP_Error( 'rate_limit_exceeded', __( 'Too many requests.', 'tawasol' ), array( 'status' => 429 ) );
        }

        set_transient( $key, $requests + 1, 60 );
        return true;
    }

    private function log_event( $event_type, $description, $user_id = null ) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'tawasol_security_logs';
        $wpdb->insert( $table_logs, array(
            'user_id'     => $user_id ?: get_current_user_id(),
            'event_type'  => $event_type,
            'description' => $description,
            'ip_address'  => $_SERVER['REMOTE_ADDR'],
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'],
        ) );
    }

    private function is_participant( $conversation_id, $user_id ) {
        global $wpdb;
        $table_participants = $wpdb->prefix . 'tawasol_participants';
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_participants WHERE conversation_id = %d AND user_id = %d",
            $conversation_id, $user_id
        ) );
        return (bool) $exists;
    }

    private function is_blocked( $user_id, $target_user_id ) {
        global $wpdb;
        $table_blocks = $wpdb->prefix . 'tawasol_blocks';
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_blocks WHERE user_id = %d AND blocked_user_id = %d",
            $target_user_id, $user_id // Check if target has blocked the user
        ) );
        return (bool) $exists;
    }

    public function check_user( $request ) {
        $identifier = $request->get_param( 'identifier' );

        // Try username first
        $user = get_user_by( 'login', $identifier );

        if ( ! $user ) {
            // Try email
            $user = get_user_by( 'email', $identifier );
        }

        if ( ! $user ) {
            // Try phone
            $users = get_users( array(
                'meta_key'   => 'tawasol_phone',
                'meta_value' => $identifier,
                'number'     => 1,
            ) );
            if ( ! empty( $users ) ) {
                $user = $users[0];
            }
        }

        if ( $user ) {
            return new WP_REST_Response( array(
                'exists' => true,
                'name'   => $user->display_name,
            ), 200 );
        }

        return new WP_REST_Response( array( 'exists' => false ), 200 );
    }

	public function login( $request ) {
        $params = $request->get_params();
        $identifier = $params['identifier'] ?? ''; // Strictly Username
        $pin = $params['pin'] ?? '';

        if ( empty( $identifier ) || empty( $pin ) ) {
            return new WP_Error( 'missing_params', __( 'Missing parameters.', 'tawasol' ), array( 'status' => 400 ) );
        }

        // Brute force protection
        $key = 'tawasol_login_attempts_' . md5( $identifier );
        $attempts = get_transient( $key ) ?: 0;

        // Persistent lockout check if user exists
        $user_obj = get_user_by( 'login', $identifier );
        if ( $user_obj ) {
            $lockout = get_user_meta( $user_obj->ID, 'tawasol_lockout_until', true );
            if ( $lockout && $lockout > time() ) {
                return new WP_Error( 'account_locked', __( 'Account locked due to multiple failed attempts. Please try again later.', 'tawasol' ), array( 'status' => 429 ) );
            }
        }

        if ( $attempts >= 5 ) {
            $this->log_event( 'brute_force_alert', "Blocked login attempt for $identifier (too many attempts)" );
            return new WP_Error( 'too_many_attempts', __( 'Too many failed attempts. Please try again later.', 'tawasol' ), array( 'status' => 429 ) );
        }

        $user = Tawasol_Auth::authenticate( $identifier, $pin );

        if ( is_wp_error( $user ) ) {
            set_transient( $key, $attempts + 1, 900 ); // 15 minutes block

            if ( $user_obj ) {
                $failed_attempts = (int) get_user_meta( $user_obj->ID, 'tawasol_failed_logins', true ) + 1;
                update_user_meta( $user_obj->ID, 'tawasol_failed_logins', $failed_attempts );
                if ( $failed_attempts >= 10 ) {
                    update_user_meta( $user_obj->ID, 'tawasol_lockout_until', time() + 3600 ); // 1 hour lockout
                    update_user_meta( $user_obj->ID, 'tawasol_failed_logins', 0 );
                }
            }

            $this->log_event( 'login_failed', "Failed login for $identifier" );
            // Generic error message
            return new WP_Error( 'auth_failed', __( 'Invalid username or PIN.', 'tawasol' ), array( 'status' => 401 ) );
        }

        if ( $user_obj ) {
            delete_user_meta( $user_obj->ID, 'tawasol_failed_logins' );
            delete_user_meta( $user_obj->ID, 'tawasol_lockout_until' );
        }
        delete_transient( $key );
        Tawasol_Auth::login_user( $user );
        $this->log_event( 'login_success', "Successful login for $identifier", $user->ID );

        return new WP_REST_Response( array(
            'success' => true,
            'user'    => array(
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
            ),
        ), 200 );
    }

    public function register( $request ) {
        $params = $request->get_params();
        $email    = $params['email'] ?? '';
        $phone    = $params['phone'] ?? '';
        $username = $params['username'] ?? '';
        $pin      = $params['pin'] ?? '';

        if ( empty( $email ) || empty( $username ) || empty( $pin ) ) {
            return new WP_Error( 'missing_params', __( 'Missing parameters.', 'tawasol' ), array( 'status' => 400 ) );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', __( 'Email already registered.', 'tawasol' ), array( 'status' => 400 ) );
        }

        if ( username_exists( $username ) ) {
            return new WP_Error( 'username_exists', __( 'Username already taken.', 'tawasol' ), array( 'status' => 400 ) );
        }

        // Create user with a random long password, since we use PIN for Tawasol login
        $wp_password = wp_generate_password( 24 );
        $user_id = wp_create_user( $username, $wp_password, $email );

        if ( is_wp_error( $user_id ) ) {
            return new WP_Error( 'registration_failed', $user_id->get_error_message(), array( 'status' => 500 ) );
        }

        // Set the Tawasol PIN
        Tawasol_Auth::set_pin( $user_id, $pin );

        if ( ! empty( $phone ) ) {
            update_user_meta( $user_id, 'tawasol_phone', $phone );
        }

        $user = get_user_by( 'id', $user_id );
        Tawasol_Auth::login_user( $user );

        return new WP_REST_Response( array(
            'success' => true,
            'user'    => array(
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
            ),
        ), 200 );
    }

    public function search_users( $request ) {
        $term = $request->get_param( 'term' );
        if ( empty( $term ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        $users = get_users( array(
            'search'         => '*' . $term . '*',
            'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
            'number'         => 10,
            'exclude'        => array( get_current_user_id() ),
        ) );

        $results = array();
        foreach ( $users as $user ) {
            $results[] = array(
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'username'     => $user->user_login,
            );
        }

        return new WP_REST_Response( $results, 200 );
    }

    /**
     * Search within message content across all conversations for the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function search_messages( $request ) {
        $user_id = get_current_user_id();
        $term = $request->get_param( 'term' );

        if ( empty( $term ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        $results = $this->engine->search_messages( $user_id, $term );

        return new WP_REST_Response( $results, 200 );
    }

    public function check_username( $request ) {
        $username = $request->get_param('username');
        $available = ! username_exists( $username );

        $suggestions = array();
        if ( ! $available ) {
            for ( $i = 1; $i <= 3; $i++ ) {
                $suggestion = $username . rand( 10, 99 );
                if ( ! username_exists( $suggestion ) ) {
                    $suggestions[] = $suggestion;
                }
            }
        }

        return new WP_REST_Response( array(
            'available'   => $available,
            'suggestions' => $suggestions
        ), 200 );
    }

    public function get_conversations() {
        $user_id = get_current_user_id();
        $conversations = $this->engine->get_conversations( $user_id );
        return new WP_REST_Response( $conversations, 200 );
    }

    public function create_conversation( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $type = $params['type'] ?? 'one-on-one';
        $participants = $params['participants'] ?? array();
        $title = $params['title'] ?? '';

        $conversation_id = $this->engine->start_conversation( $title, $type, $user_id, $participants );

        if ( ! $conversation_id ) {
            return new WP_Error( 'db_error', __( 'Failed to create conversation.', 'tawasol' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'conversation_id' => $conversation_id ), 201 );
    }

    public function get_messages( $request ) {
        $conversation_id = $request['id'];
        $user_id = get_current_user_id();

        if ( ! $this->is_participant( $conversation_id, $user_id ) ) {
            return new WP_Error( 'unauthorized', __( 'You are not a participant of this conversation.', 'tawasol' ), array( 'status' => 403 ) );
        }

        $after = $request->get_param( 'after' ) ?: 0;
        $before = $request->get_param( 'before' ) ?: 0;
        $limit = $request->get_param( 'limit' ) ?: 50;

        $messages = $this->engine->get_messages( $conversation_id, $user_id, $after, $before, $limit );

        return new WP_REST_Response( $messages, 200 );
    }

	/**
	 * Handle sending a new message.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
    public function send_message( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $conversation_id = $params['conversation_id'];
        $content = $params['content'];
        $content_type = $params['content_type'] ?? 'text';

        if ( empty( $conversation_id ) || empty( $content ) ) {
            return new WP_Error( 'missing_params', __( 'Missing parameters.', 'tawasol' ), array( 'status' => 400 ) );
        }

        if ( ! $this->is_participant( $conversation_id, $user_id ) ) {
            return new WP_Error( 'unauthorized', __( 'You are not a participant of this conversation.', 'tawasol' ), array( 'status' => 403 ) );
        }

        // Check for blocks
        global $wpdb;
        $table_participants = $wpdb->prefix . 'tawasol_participants';
        $participants = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $table_participants WHERE conversation_id = %d", $conversation_id ) );

        foreach ( $participants as $p_id ) {
            if ( $p_id != $user_id && $this->is_blocked( $user_id, $p_id ) ) {
                return new WP_Error( 'blocked', __( 'You cannot send messages to this user.', 'tawasol' ), array( 'status' => 403 ) );
            }
        }

        $message_id = $this->engine->send_message( $conversation_id, $user_id, $content, $content_type );

        if ( ! $message_id ) {
            return new WP_Error( 'db_error', __( 'Failed to save message.', 'tawasol' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message_id' => $message_id ), 200 );
    }

    public function mark_message_read( $request ) {
        global $wpdb;
        $message_id = $request['id'];
        $user_id = get_current_user_id();
        $table_messages = $wpdb->prefix . 'tawasol_messages';
        $table_participants = $wpdb->prefix . 'tawasol_participants';

        $message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_messages WHERE id = %d", $message_id ) );

        if ( $message ) {
            $wpdb->update( $table_participants,
                array( 'last_read_at' => current_time( 'mysql' ) ),
                array( 'conversation_id' => $message->conversation_id, 'user_id' => $user_id )
            );

            if ( $message->sender_id != $user_id && $message->status !== 'read' && $message->status !== 'played' ) {
                $wpdb->update( $table_messages, array( 'status' => 'read' ), array( 'id' => $message_id ) );
            }
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function mark_message_played( $request ) {
        global $wpdb;
        $message_id = $request['id'];
        $user_id = get_current_user_id();
        $table_messages = $wpdb->prefix . 'tawasol_messages';

        $message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_messages WHERE id = %d", $message_id ) );
        if ( ! $message ) {
            return new WP_Error( 'not_found', __( 'Message not found.', 'tawasol' ), array( 'status' => 404 ) );
        }

        if ( ! $this->is_participant( $message->conversation_id, $user_id ) ) {
            return new WP_Error( 'unauthorized', __( 'Unauthorized.', 'tawasol' ), array( 'status' => 403 ) );
        }

        if ( $message->sender_id != $user_id && $message->content_type === 'voice' ) {
             $wpdb->update( $table_messages, array( 'status' => 'played' ), array( 'id' => $message_id ) );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function archive_conversation( $request ) {
        $conversation_id = $request['id'];
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $archive = isset( $params['archive'] ) ? $params['archive'] === 'true' : true;

        $success = $this->engine->archive_conversation( $conversation_id, $user_id, $archive );

        return new WP_REST_Response( array( 'success' => (bool)$success ), 200 );
    }

    public function update_presence( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $status = $params['status'] ?? 'online';
        $is_typing = isset( $params['is_typing'] ) ? $params['is_typing'] === 'true' : false;
        $conversation_id = $params['conversation_id'] ?? null;

        $data = array(
            'status'    => $status,
            'is_typing' => $is_typing,
            'conv_id'   => $conversation_id,
            'timestamp' => time()
        );

        set_transient( 'tawasol_presence_' . $user_id, $data, 60 );
        wp_cache_set( 'tawasol_presence_' . $user_id, $data, 'tawasol', 60 );

        update_user_meta( $user_id, 'tawasol_last_seen', current_time( 'mysql' ) );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function get_presence( $request ) {
        $user_id = $request['id'];
        $current_user_id = get_current_user_id();

        // High performance lookup via cache
        $data = wp_cache_get( 'tawasol_presence_' . $user_id, 'tawasol' );
        if ( ! $data ) {
            $data = get_transient( 'tawasol_presence_' . $user_id );
        }

        // Check privacy
        $privacy = get_user_meta( $user_id, 'tawasol_privacy_online', true ) ?: 'everyone';
        if ( $privacy === 'nobody' && $user_id != $current_user_id ) {
             return new WP_REST_Response( array( 'user_id' => $user_id, 'status' => 'hidden' ), 200 );
        }

        if ( $this->is_blocked( $current_user_id, $user_id ) ) {
            return new WP_REST_Response( array( 'user_id' => $user_id, 'status' => 'hidden' ), 200 );
        }

        $last_seen = get_user_meta( $user_id, 'tawasol_last_seen', true );

        return new WP_REST_Response( array(
            'user_id'   => $user_id,
            'status'    => $data['status'] ?? 'offline',
            'is_typing' => $data['is_typing'] ?? false,
            'conv_id'   => $data['conv_id'] ?? null,
            'last_seen' => $last_seen,
        ), 200 );
    }

    /**
     * Edit an existing message.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function edit_message( $request ) {
        global $wpdb;
        $message_id = $request['id'];
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $new_content = $params['content'];

        if ( empty( $new_content ) ) {
            return new WP_Error( 'missing_params', __( 'Content is required.', 'tawasol' ), array( 'status' => 400 ) );
        }

        $table_messages = $wpdb->prefix . 'tawasol_messages';
        $message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_messages WHERE id = %d", $message_id ) );

        if ( ! $message || $message->sender_id != $user_id ) {
            return new WP_Error( 'unauthorized', __( 'You cannot edit this message.', 'tawasol' ), array( 'status' => 403 ) );
        }

        $encrypted_content = Tawasol_Encryption::encrypt( $new_content );

        $wpdb->update( $table_messages,
            array( 'content' => $encrypted_content, 'is_edited' => 1 ),
            array( 'id' => $message_id )
        );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Delete a message.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_message( $request ) {
        global $wpdb;
        $message_id = $request['id'];
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $for_everyone = isset( $params['everyone'] ) && $params['everyone'] === 'true';

        $table_messages = $wpdb->prefix . 'tawasol_messages';
        $message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_messages WHERE id = %d", $message_id ) );

        if ( ! $message ) {
            return new WP_Error( 'not_found', __( 'Message not found.', 'tawasol' ), array( 'status' => 404 ) );
        }

        if ( $for_everyone ) {
            if ( $message->sender_id != $user_id ) {
                return new WP_Error( 'unauthorized', __( 'You cannot delete this message for everyone.', 'tawasol' ), array( 'status' => 403 ) );
            }
            // Actually delete or mark as deleted
            $wpdb->update( $table_messages, array( 'content' => '[Message Deleted]', 'content_type' => 'text' ), array( 'id' => $message_id ) );
        } else {
            // Local delete for user only
            $deleted_ids = get_user_meta( $user_id, 'tawasol_deleted_messages', true ) ?: array();
            if ( ! in_array( $message_id, $deleted_ids ) ) {
                $deleted_ids[] = (int) $message_id;
                update_user_meta( $user_id, 'tawasol_deleted_messages', $deleted_ids );
            }
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Pin or unpin a message.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function pin_message( $request ) {
        global $wpdb;
        $message_id = $request['id'];
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $pin = isset( $params['pin'] ) ? $params['pin'] === 'true' : true;

        $table_messages = $wpdb->prefix . 'tawasol_messages';
        $message = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_messages WHERE id = %d", $message_id ) );

        if ( ! $message ) {
            return new WP_Error( 'not_found', __( 'Message not found.', 'tawasol' ), array( 'status' => 404 ) );
        }

        if ( ! $this->is_participant( $message->conversation_id, $user_id ) ) {
            return new WP_Error( 'unauthorized', __( 'Unauthorized.', 'tawasol' ), array( 'status' => 403 ) );
        }

        $wpdb->update( $table_messages, array( 'is_pinned' => $pin ? 1 : 0 ), array( 'id' => $message_id ) );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Get current user profile and settings.
     */
    public function get_profile( $request ) {
        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );

        return new WP_REST_Response( array(
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'username'     => $user->user_login,
            'email'        => $user->user_email,
            'phone'        => get_user_meta( $user_id, 'tawasol_phone', true ),
            'photo'        => get_user_meta( $user_id, 'tawasol_photo', true ),
            'status_msg'   => get_user_meta( $user_id, 'tawasol_status_msg', true ),
            'bio'          => get_user_meta( $user_id, 'tawasol_bio', true ),
            'privacy'      => array(
                'photo'     => get_user_meta( $user_id, 'tawasol_privacy_photo', true ) ?: 'everyone',
                'status'    => get_user_meta( $user_id, 'tawasol_privacy_status', true ) ?: 'everyone',
                'last_seen' => get_user_meta( $user_id, 'tawasol_privacy_last_seen', true ) ?: 'everyone',
                'online'    => get_user_meta( $user_id, 'tawasol_privacy_online', true ) ?: 'everyone',
                'read_receipts' => (bool) get_user_meta( $user_id, 'tawasol_read_receipts', true ),
            )
        ), 200 );
    }

    /**
     * Update current user profile.
     */
    public function update_profile( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_params();

        if ( isset( $params['display_name'] ) ) {
            wp_update_user( array( 'ID' => $user_id, 'display_name' => $params['display_name'] ) );
        }

        $meta_fields = array( 'tawasol_photo', 'tawasol_status_msg', 'tawasol_bio', 'tawasol_phone' );
        foreach ( $meta_fields as $field ) {
            if ( isset( $params[ str_replace('tawasol_', '', $field) ] ) ) {
                update_user_meta( $user_id, $field, $params[ str_replace('tawasol_', '', $field) ] );
            }
        }

        return $this->get_profile( $request );
    }

    /**
     * Update current user privacy settings.
     */
    public function update_privacy( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_params();

        $privacy_fields = array( 'photo', 'status', 'last_seen', 'online', 'read_receipts' );
        foreach ( $privacy_fields as $field ) {
            if ( isset( $params[ $field ] ) ) {
                $meta_key = $field === 'read_receipts' ? 'tawasol_read_receipts' : 'tawasol_privacy_' . $field;
                update_user_meta( $user_id, $meta_key, $params[ $field ] );
            }
        }

        return $this->get_profile( $request );
    }

    /**
     * Get list of blocked users.
     */
    public function get_blocked_users( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table_blocks = $wpdb->prefix . 'tawasol_blocks';

        $blocked_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT blocked_user_id FROM $table_blocks WHERE user_id = %d",
            $user_id
        ) );

        $results = array();
        if ( ! empty( $blocked_ids ) ) {
            $users = get_users( array( 'include' => $blocked_ids ) );
            foreach ( $users as $user ) {
                $results[] = array(
                    'id'           => $user->ID,
                    'display_name' => $user->display_name,
                    'username'     => $user->user_login,
                );
            }
        }

        return new WP_REST_Response( $results, 200 );
    }

    /**
     * Block a user.
     */
    public function block_user( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $blocked_user_id = $request->get_param( 'user_id' );

        if ( empty( $blocked_user_id ) ) {
            return new WP_Error( 'missing_params', __( 'User ID is required.', 'tawasol' ), array( 'status' => 400 ) );
        }

        $table_blocks = $wpdb->prefix . 'tawasol_blocks';
        $wpdb->replace( $table_blocks, array(
            'user_id'         => $user_id,
            'blocked_user_id' => $blocked_user_id,
        ) );

        $this->log_event( 'user_blocked', "Blocked user ID $blocked_user_id" );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Unblock a user.
     */
    public function unblock_user( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $blocked_user_id = $request['id'];

        $table_blocks = $wpdb->prefix . 'tawasol_blocks';
        $wpdb->delete( $table_blocks, array(
            'user_id'         => $user_id,
            'blocked_user_id' => $blocked_user_id,
        ) );

        $this->log_event( 'user_unblocked', "Unblocked user ID $blocked_user_id" );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Change user PIN.
     */
    public function change_pin( $request ) {
        $user_id = get_current_user_id();

        // Require OTP verification for PIN change
        if ( ! get_transient( 'tawasol_otp_verified_' . $user_id ) ) {
            return new WP_Error( 'otp_required', __( 'OTP verification required.', 'tawasol' ), array( 'status' => 403 ) );
        }
        delete_transient( 'tawasol_otp_verified_' . $user_id );

        $params = $request->get_params();
        $old_pin = $params['old_pin'] ?? '';
        $new_pin = $params['new_pin'] ?? '';

        if ( empty( $old_pin ) || empty( $new_pin ) ) {
            return new WP_Error( 'missing_params', __( 'Old and new PIN are required.', 'tawasol' ), array( 'status' => 400 ) );
        }

        $user = get_userdata( $user_id );
        $hashed_pin = get_user_meta( $user_id, 'tawasol_pin_hash', true );

        if ( ! $hashed_pin || ! wp_check_password( $old_pin, $hashed_pin, $user_id ) ) {
            return new WP_Error( 'invalid_pin', __( 'Incorrect old PIN.', 'tawasol' ), array( 'status' => 401 ) );
        }

        if ( ! preg_match( '/^\d{6}$/', $new_pin ) ) {
            return new WP_Error( 'invalid_format', __( 'New PIN must be 6 digits.', 'tawasol' ), array( 'status' => 400 ) );
        }

        Tawasol_Auth::set_pin( $user_id, $new_pin );
        $this->log_event( 'pin_changed', "Changed PIN" );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Export user data.
     */
    public function export_data( $request ) {
        $user_id = get_current_user_id();
        $profile = $this->get_profile( $request )->get_data();

        // Include chat history summary or link
        return new WP_REST_Response( array(
            'profile' => $profile,
            'message' => __( 'Your data export is ready.', 'tawasol' )
        ), 200 );
    }

    /**
     * Delete account.
     */
    public function delete_account( $request ) {
        $user_id = get_current_user_id();
        $pin = $request->get_param( 'pin' );

        $hashed_pin = get_user_meta( $user_id, 'tawasol_pin_hash', true );
        if ( ! $hashed_pin || ! wp_check_password( $pin, $hashed_pin, $user_id ) ) {
            return new WP_Error( 'invalid_pin', __( 'Incorrect PIN.', 'tawasol' ), array( 'status' => 401 ) );
        }

        // In WP, we might just disable the user or delete them
        // For Tawasol, we'll mark as deactivated in meta for now
        update_user_meta( $user_id, 'tawasol_deactivated', true );
        $this->log_event( 'account_deactivated', "Deactivated account" );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function request_otp( $request ) {
        $user_id = get_current_user_id();
        $otp = rand( 100000, 999999 );

        // Store OTP in transient for 10 minutes
        set_transient( 'tawasol_otp_' . $user_id, $otp, 600 );

        // Log event
        $this->log_event( 'otp_requested', "OTP requested by user ID $user_id" );

        // In a real app, this would send an SMS or Email.
        // For this demo, we'll return it in the response for simulation.
        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'OTP sent (Simulation: ' . $otp . ')', 'tawasol' )
        ), 200 );
    }

    public function get_sessions( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table_sessions = $wpdb->prefix . 'tawasol_sessions';

        $sessions = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, ip_address, user_agent, last_activity, created_at
             FROM $table_sessions WHERE user_id = %d ORDER BY last_activity DESC",
            $user_id
        ) );

        return new WP_REST_Response( $sessions, 200 );
    }

    public function terminate_session( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $session_id = $request['id'];
        $table_sessions = $wpdb->prefix . 'tawasol_sessions';

        $wpdb->delete( $table_sessions, array( 'id' => $session_id, 'user_id' => $user_id ) );

        $this->log_event( 'session_terminated', "Terminated session ID $session_id" );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function verify_otp( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $otp = $params['otp'] ?? '';

        $stored_otp = get_transient( 'tawasol_otp_' . $user_id );

        if ( $stored_otp && $otp == $stored_otp ) {
            delete_transient( 'tawasol_otp_' . $user_id );
            // Store verification success in transient for a short time to allow subsequent action
            set_transient( 'tawasol_otp_verified_' . $user_id, true, 300 );
            return new WP_REST_Response( array( 'success' => true ), 200 );
        }

        return new WP_Error( 'invalid_otp', __( 'Invalid or expired OTP.', 'tawasol' ), array( 'status' => 401 ) );
    }

    public function stream_updates( $request ) {
        $user_id = get_current_user_id();

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $last_id = (int) $request->get_param( 'last_id' ) ?: 0;
        $last_status_check = current_time( 'mysql' );
        $start_time = time();

        while (time() - $start_time < 50) {
            global $wpdb;
            $table_messages = $wpdb->prefix . 'tawasol_messages';
            $table_participants = $wpdb->prefix . 'tawasol_participants';

            // New messages
            $new_messages = $wpdb->get_results( $wpdb->prepare(
                "SELECT m.id, m.conversation_id, m.sender_id, m.content, m.content_type, m.status, m.created_at, m.is_pinned, m.is_edited
                 FROM $table_messages m
                 JOIN $table_participants p ON m.conversation_id = p.conversation_id
                 WHERE p.user_id = %d AND m.id > %d
                 ORDER BY m.id ASC",
                $user_id, $last_id
            ) );

            if ( ! empty( $new_messages ) ) {
                foreach ( $new_messages as $msg ) {
                    if ( $msg->content_type === 'text' ) {
                        $msg->content = Tawasol_Encryption::decrypt( $msg->content );
                    }
                    echo "event: message\n";
                    echo "data: " . json_encode( $msg ) . "\n\n";
                    $last_id = max($last_id, $msg->id);
                }
            }

            // Status updates
            $status_updates = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, conversation_id, status FROM $table_messages
                 WHERE sender_id = %d AND updated_at > %s",
                $user_id, $last_status_check
            ) );

            if ( ! empty( $status_updates ) ) {
                foreach ( $status_updates as $update ) {
                    echo "event: status_update\n";
                    echo "data: " . json_encode( $update ) . "\n\n";
                }
            }
            $last_status_check = current_time( 'mysql' );

            // Ping to keep connection alive
            echo "event: ping\ndata: {\"time\": " . time() . "}\n\n";

            if (ob_get_level() > 0) ob_flush();
            flush();

            if ( connection_aborted() ) break;
            sleep(1);
        }
        exit;
    }

    public function upload_profile_photo( $request ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $user_id = get_current_user_id();
        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', __( 'No file uploaded.', 'tawasol' ), array( 'status' => 400 ) );
        }

        $upload = wp_handle_upload( $files['file'], array( 'test_form' => false ) );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'upload_error', $upload['error'], array( 'status' => 500 ) );
        }

        // Image optimization
        $image = wp_get_image_editor( $upload['file'] );
        if ( ! is_wp_error( $image ) ) {
            $image->resize( 300, 300, true );
            $image->save( $upload['file'] );
        }

        update_user_meta( $user_id, 'tawasol_photo', $upload['url'] );

        return new WP_REST_Response( array( 'success' => true, 'url' => $upload['url'] ), 200 );
    }

    public function upload_file( $request ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $user_id = get_current_user_id();
        $chat_id = $request->get_param('conversation_id') ?: 'misc';
        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', __( 'No file uploaded.', 'tawasol' ), array( 'status' => 400 ) );
        }

        // Determine type folder
        $file_type = $files['file']['type'];
        $type_folder = 'attachments';
        if ( strpos( $file_type, 'image/' ) === 0 ) {
            $type_folder = 'images';
        } elseif ( strpos( $file_type, 'audio/' ) === 0 || strpos( $file_type, 'video/' ) === 0 ) {
            $type_folder = 'voice'; // Mapping audio/video to voice for now as per req
        } elseif ( strpos( $file_type, 'text/' ) === 0 ) {
            $type_folder = 'text';
        }

        // Custom upload directory for Tawasol modular storage
        // We still use WP uploads dir as base for web accessibility, but follow the requested structure
        add_filter( 'upload_dir', function( $dir ) use ( $user_id, $chat_id, $type_folder ) {
            $base_subdir = "/tawasol/media/{$type_folder}/{$user_id}/{$chat_id}";
            $dir['path']   = $dir['basedir'] . $base_subdir;
            $dir['url']    = $dir['baseurl'] . $base_subdir;
            $dir['subdir'] = $base_subdir;
            return $dir;
        });

        $upload = wp_handle_upload( $files['file'], array( 'test_form' => false ) );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'upload_error', $upload['error'], array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'url'  => $upload['url'],
            'type' => $upload['type'],
        ), 200 );
    }

}
