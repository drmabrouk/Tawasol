<?php
/**
 * Database queries handler.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/database/queries
 */

class Tawasol_DB_Queries {

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
            "SELECT c.*, p.is_archived,
                (SELECT user_id FROM $this->table_participants p2 WHERE p2.conversation_id = c.id AND p2.user_id != %d LIMIT 1) as other_user_id,
                (SELECT COUNT(*) FROM $this->table_participants p3 WHERE p3.conversation_id = c.id) as participant_count
             FROM $this->table_conversations c
             JOIN $this->table_participants p ON c.id = p.conversation_id
             WHERE p.user_id = %d",
            $user_id, $user_id
        ) );
    }

    public function get_messages( $conversation_id, $after = 0, $before = 0, $limit = 50, $exclude_ids = array() ) {
        global $wpdb;
        $exclude_sql = "";
        if ( ! empty( $exclude_ids ) ) {
            $exclude_sql = " AND m.id NOT IN (" . implode( ',', array_map( 'intval', $exclude_ids ) ) . ")";
        }

        $where = $wpdb->prepare( "m.conversation_id = %d", $conversation_id );
        if ( $after ) {
            $where .= $wpdb->prepare( " AND m.id > %d", $after );
            $order = "ASC";
        } elseif ( $before ) {
            $where .= $wpdb->prepare( " AND m.id < %d", $before );
            $order = "DESC";
        } else {
            $order = "DESC";
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name
             FROM $this->table_messages m
             JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE $where $exclude_sql
             ORDER BY m.id $order LIMIT %d",
            $limit
        ) );

        if ( $order === 'DESC' ) {
            return array_reverse( $results );
        }

        return $results;
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

    public function find_existing_one_on_one( $user_a, $user_b ) {
        global $wpdb;
        if ( $user_a == $user_b ) {
            return $wpdb->get_var( $wpdb->prepare(
                "SELECT p.conversation_id
                 FROM $this->table_participants p
                 JOIN $this->table_conversations c ON p.conversation_id = c.id
                 WHERE c.type = 'one-on-one' AND p.user_id = %d
                 GROUP BY p.conversation_id HAVING COUNT(*) = 1",
                $user_a
            ) );
        }

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT p1.conversation_id
             FROM $this->table_participants p1
             JOIN $this->table_participants p2 ON p1.conversation_id = p2.conversation_id
             JOIN $this->table_conversations c ON p1.conversation_id = c.id
             WHERE c.type = 'one-on-one' AND p1.user_id = %d AND p2.user_id = %d AND p1.user_id != p2.user_id
             GROUP BY p1.conversation_id HAVING COUNT(*) = 2",
            $user_a, $user_b
        ) );
    }
}
