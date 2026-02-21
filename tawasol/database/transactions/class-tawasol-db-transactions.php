<?php
/**
 * Database transactions handler.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/database/transactions
 */

class Tawasol_DB_Transactions {

    private $table_conversations;
    private $table_messages;
    private $table_participants;

    public function __construct() {
        global $wpdb;
        $this->table_conversations = $wpdb->prefix . 'tawasol_conversations';
        $this->table_messages      = $wpdb->prefix . 'tawasol_messages';
        $this->table_participants  = $wpdb->prefix . 'tawasol_participants';
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

    public function set_archived( $conversation_id, $user_id, $is_archived ) {
        global $wpdb;
        return $wpdb->update(
            $this->table_participants,
            array( 'is_archived' => $is_archived ? 1 : 0 ),
            array( 'conversation_id' => $conversation_id, 'user_id' => $user_id )
        );
    }
}
