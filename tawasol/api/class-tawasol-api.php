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
class Tawasol_API {

	private $plugin_name;
	private $version;
    private $namespace;

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

        register_rest_route( $this->namespace, '/messages/(?P<id>\d+)/read', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'mark_message_read' ),
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

    public function check_user( $request ) {
        $identifier = $request->get_param( 'identifier' );

        $user = get_user_by( 'email', $identifier );
        if ( ! $user ) {
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
        $identifier = $params['identifier'] ?? ''; // Phone or email
        $pin = $params['pin'] ?? '';

        if ( empty( $identifier ) || empty( $pin ) ) {
            return new WP_Error( 'missing_params', __( 'Missing parameters.', 'tawasol' ), array( 'status' => 400 ) );
        }

        // Brute force protection
        $key = 'tawasol_login_attempts_' . md5( $identifier );
        $attempts = get_transient( $key ) ?: 0;
        if ( $attempts >= 5 ) {
            $this->log_event( 'brute_force_alert', "Blocked login attempt for $identifier (too many attempts)" );
            return new WP_Error( 'too_many_attempts', __( 'Too many failed attempts. Please try again later.', 'tawasol' ), array( 'status' => 429 ) );
        }

        $user = Tawasol_Auth::authenticate( $identifier, $pin );

        if ( is_wp_error( $user ) ) {
            set_transient( $key, $attempts + 1, 900 ); // 15 minutes block
            $this->log_event( 'login_failed', "Failed login for $identifier" );
            return new WP_Error( 'auth_failed', $user->get_error_message(), array( 'status' => 401 ) );
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
        global $wpdb;
        $user_id = get_current_user_id();
        $term = $request->get_param( 'term' );

        if ( empty( $term ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        $table_messages = $wpdb->prefix . 'tawasol_messages';
        $table_participants = $wpdb->prefix . 'tawasol_participants';

        // Note: Simple LIKE search for demo. In production, consider full-text index.
        // We must also decrypt each message to check if it matches, but SQL can't do that easily.
        // For efficiency, we search encrypted content if term matches or fetch all and filter in PHP.
        // Here we'll do the simple SQL search on raw content for the demo purpose.
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT m.* FROM $table_messages m
             JOIN $table_participants p ON m.conversation_id = p.conversation_id
             WHERE p.user_id = %d AND m.content LIKE %s
             ORDER BY m.created_at DESC LIMIT 50",
            $user_id, '%' . $wpdb->esc_like( $term ) . '%'
        ) );

        foreach ( $results as &$msg ) {
            if ( $msg->content_type === 'text' ) {
                $msg->content = Tawasol_Encryption::decrypt( $msg->content );
            }
        }

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
        global $wpdb;
        $user_id = get_current_user_id();
        $table_conversations = $wpdb->prefix . 'tawasol_conversations';
        $table_participants = $wpdb->prefix . 'tawasol_participants';

        $conversations = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.* FROM $table_conversations c
             JOIN $table_participants p ON c.id = p.conversation_id
             WHERE p.user_id = %d",
            $user_id
        ) );

        return new WP_REST_Response( $conversations, 200 );
    }

    public function create_conversation( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $type = $params['type'] ?? 'one-on-one';
        $participants = $params['participants'] ?? array(); // Array of user IDs
        $title = $params['title'] ?? '';

        $table_conversations = $wpdb->prefix . 'tawasol_conversations';
        $table_participants = $wpdb->prefix . 'tawasol_participants';

        $wpdb->insert( $table_conversations, array(
            'title' => $title,
            'type'  => $type,
        ) );
        $conversation_id = $wpdb->insert_id;

        // Add creator as participant
        $wpdb->insert( $table_participants, array(
            'conversation_id' => $conversation_id,
            'user_id'         => $user_id,
            'is_admin'        => 1,
        ) );

        // Add other participants
        foreach ( $participants as $p_id ) {
            if ( $p_id != $user_id ) {
                $wpdb->insert( $table_participants, array(
                    'conversation_id' => $conversation_id,
                    'user_id'         => $p_id,
                    'is_admin'        => 0,
                ) );
            }
        }

        return new WP_REST_Response( array( 'success' => true, 'conversation_id' => $conversation_id ), 201 );
    }

    public function get_messages( $request ) {
        global $wpdb;
        $conversation_id = $request['id'];
        $user_id = get_current_user_id();

        if ( ! $this->is_participant( $conversation_id, $user_id ) ) {
            return new WP_Error( 'unauthorized', __( 'You are not a participant of this conversation.', 'tawasol' ), array( 'status' => 403 ) );
        }

        $after = $request->get_param( 'after' ) ?: 0;
        $table_messages = $wpdb->prefix . 'tawasol_messages';

        $messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name
             FROM $table_messages m
             JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.conversation_id = %d AND m.id > %d
             ORDER BY m.created_at ASC",
            $conversation_id, $after
        ) );

        foreach ( $messages as &$message ) {
            if ( $message->content_type === 'text' ) {
                $message->content = Tawasol_Encryption::decrypt( $message->content );
            }
        }

        return new WP_REST_Response( $messages, 200 );
    }

	/**
	 * Handle sending a new message.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
    public function send_message( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $conversation_id = $params['conversation_id'];

        if ( empty( $conversation_id ) || empty( $params['content'] ) ) {
            return new WP_Error( 'missing_params', __( 'Missing parameters.', 'tawasol' ), array( 'status' => 400 ) );
        }

        if ( ! $this->is_participant( $conversation_id, $user_id ) ) {
            return new WP_Error( 'unauthorized', __( 'You are not a participant of this conversation.', 'tawasol' ), array( 'status' => 403 ) );
        }

        $content = $params['content'];
        $content_type = $params['content_type'] ?? 'text';

        if ( $content_type === 'text' ) {
            $content = Tawasol_Encryption::encrypt( $content );
        }

        $table_messages = $wpdb->prefix . 'tawasol_messages';
        $wpdb->insert( $table_messages, array(
            'conversation_id' => $params['conversation_id'],
            'sender_id'       => $user_id,
            'content'         => $content,
            'content_type'    => $content_type,
            'status'          => 'sent'
        ) );
        $message_id = $wpdb->insert_id;

        // Trigger push notifications
        do_action( 'tawasol_message_sent', $message_id, $params['conversation_id'], $user_id );

        return new WP_REST_Response( array( 'success' => true, 'message_id' => $message_id ), 200 );
    }

    public function mark_message_read( $request ) {
        global $wpdb;
        $message_id = $request['id'];
        $user_id = get_current_user_id();
        $table_messages = $wpdb->prefix . 'tawasol_messages';
        $table_participants = $wpdb->prefix . 'tawasol_participants';

        // Update message status if the recipient is reading it
        // Actually, usually we mark all messages in a conversation as read for a user
        $message = $wpdb->get_row( $wpdb->prepare( "SELECT conversation_id FROM $table_messages WHERE id = %d", $message_id ) );

        if ( $message ) {
            $wpdb->update( $table_participants,
                array( 'last_read_at' => current_time( 'mysql' ) ),
                array( 'conversation_id' => $message->conversation_id, 'user_id' => $user_id )
            );

            // Mark message as read in messages table (this is usually global or simplified here)
            $wpdb->update( $table_messages, array( 'status' => 'read' ), array( 'id' => $message_id ) );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function update_presence( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_params();
        $status = $params['status'] ?? 'online'; // online, typing, offline

        set_transient( 'tawasol_presence_' . $user_id, $status, 60 ); // Expire in 60 seconds
        update_user_meta( $user_id, 'tawasol_last_seen', current_time( 'mysql' ) );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    public function get_presence( $request ) {
        $user_id = $request['id'];
        $status = get_transient( 'tawasol_presence_' . $user_id );
        $last_seen = get_user_meta( $user_id, 'tawasol_last_seen', true );

        return new WP_REST_Response( array(
            'user_id'   => $user_id,
            'status'    => $status ?: 'offline',
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
            // Local delete for user only - usually requires a mapping table, but we'll simulate with meta for now
            update_user_meta( $user_id, 'tawasol_deleted_msg_' . $message_id, true );
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

    public function upload_file( $request ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $files = $request->get_file_params();
        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', __( 'No file uploaded.', 'tawasol' ), array( 'status' => 400 ) );
        }

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
