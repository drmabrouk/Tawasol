<?php

/**
 * Fired during plugin activation
 *
 * @package           Tawasol
 * @subpackage        Tawasol/includes
 */

class Tawasol_Activator {

	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Conversations table
		$table_conversations = $wpdb->prefix . 'tawasol_conversations';
		$sql_conversations = "CREATE TABLE $table_conversations (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) DEFAULT '',
			type enum('one-on-one', 'group') NOT NULL DEFAULT 'one-on-one',
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// Messages table
		$table_messages = $wpdb->prefix . 'tawasol_messages';
		$sql_messages = "CREATE TABLE $table_messages (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) NOT NULL,
			sender_id bigint(20) NOT NULL,
			content text NOT NULL,
			content_type enum('text', 'image', 'file', 'voice') DEFAULT 'text' NOT NULL,
			status enum('sent', 'delivered', 'read') DEFAULT 'sent' NOT NULL,
			is_pinned tinyint(1) DEFAULT 0 NOT NULL,
			is_edited tinyint(1) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY sender_id (sender_id)
		) $charset_collate;";

		// Participants table
		$table_participants = $wpdb->prefix . 'tawasol_participants';
		$sql_participants = "CREATE TABLE $table_participants (
			conversation_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			last_read_at datetime DEFAULT CURRENT_TIMESTAMP,
			is_admin tinyint(1) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (conversation_id, user_id)
		) $charset_collate;";

        // Security Logs table
		$table_logs = $wpdb->prefix . 'tawasol_security_logs';
		$sql_logs = "CREATE TABLE $table_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) DEFAULT NULL,
			event_type varchar(100) NOT NULL,
			description text,
			ip_address varchar(45),
			user_agent text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_conversations );
		dbDelta( $sql_messages );
		dbDelta( $sql_participants );
		dbDelta( $sql_logs );

        // Add custom capability
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->add_cap( 'manage_tawasol' );
        }
        $role = get_role( 'subscriber' );
        if ( $role ) {
            $role->add_cap( 'use_tawasol' );
        }

        self::create_pages();
	}

    /**
     * Create default Tawasol pages
     */
    private static function create_pages() {
        $pages = array(
            'tawasol-login' => array(
                'title'   => 'Tawasol Login',
                'content' => '[tawasol_login]',
            ),
            'tawasol-chat' => array(
                'title'   => 'Tawasol Chat',
                'content' => '[tawasol_chat]',
            ),
        );

        foreach ( $pages as $slug => $page ) {
            $query = new WP_Query( array(
                'post_type'      => 'page',
                'name'           => $slug,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
            ) );

            if ( ! $query->have_posts() ) {
                wp_insert_post( array(
                    'post_title'   => $page['title'],
                    'post_content' => $page['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_name'    => $slug,
                ) );
            }
        }
    }

}
