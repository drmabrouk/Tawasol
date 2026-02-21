<?php
/**
 * Database handler for messaging operations.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/database
 */

class Tawasol_DB_Messenger {

    private $table_conversations;
    private $table_messages;
    private $table_participants;

    public function __construct() {
        global $wpdb;
        $this->table_conversations = $wpdb->prefix . 'tawasol_conversations';
        $this->table_messages      = $wpdb->prefix . 'tawasol_messages';
        $this->table_participants  = $wpdb->prefix . 'tawasol_participants';
    }

    public function get_user_conversations( $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*,
                (SELECT user_id FROM $this->table_participants p2 WHERE p2.conversation_id = c.id AND p2.user_id != %d LIMIT 1) as other_user_id
             FROM $this->table_conversations c
             JOIN $this->table_participants p ON c.id = p.conversation_id
             WHERE p.user_id = %d",
            $user_id, $user_id
        ) );
    }

    public function get_messages( $conversation_id, $after = 0, $exclude_ids = array() ) {
        global $wpdb;
        $exclude_sql = "";
        if ( ! empty( $exclude_ids ) ) {
            $exclude_sql = " AND m.id NOT IN (" . implode( ',', array_map( 'intval', $exclude_ids ) ) . ")";
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name
             FROM $this->table_messages m
             JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.conversation_id = %d AND m.id > %d $exclude_sql
             ORDER BY m.created_at ASC",
            $conversation_id, $after
        ) );
    }

    public function insert_message( $data ) {
        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );
        $inserted = $wpdb->insert( $this->table_messages, $data );
        if ( false === $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }
        $insert_id = $wpdb->insert_id;
        $wpdb->query( 'COMMIT' );
        return $insert_id;
    }

    public function mark_delivered( $msg_ids ) {
        global $wpdb;
        if ( empty( $msg_ids ) ) return;
        $ids_placeholder = implode( ',', array_fill( 0, count( $msg_ids ), '%d' ) );
        return $wpdb->query( $wpdb->prepare(
            "UPDATE $this->table_messages SET status = 'delivered' WHERE id IN ($ids_placeholder)",
            ...$msg_ids
        ) );
    }

    public function create_conversation( $title, $type ) {
        global $wpdb;
        $wpdb->insert( $this->table_conversations, array(
            'title' => $title,
            'type'  => $type,
        ) );
        return $wpdb->insert_id;
    }

    public function add_participant( $conversation_id, $user_id, $is_admin = 0 ) {
        global $wpdb;
        return $wpdb->insert( $this->table_participants, array(
            'conversation_id' => $conversation_id,
            'user_id'         => $user_id,
            'is_admin'        => $is_admin,
        ) );
    }

    public function get_recent_messages_for_user( $user_id, $limit = 500 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT m.* FROM $this->table_messages m
             JOIN $this->table_participants p ON m.conversation_id = p.conversation_id
             WHERE p.user_id = %d
             ORDER BY m.created_at DESC LIMIT %d",
            $user_id, $limit
        ) );
    }
}
