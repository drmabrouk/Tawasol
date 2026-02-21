<?php
/**
 * Core Chat Engine for business logic.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/modules/chat
 */

class Tawasol_Chat_Engine {

    private $db;

    public function __construct() {
        $this->db = new Tawasol_DB_Messenger();
    }

    public function get_conversations( $user_id ) {
        return $this->db->get_user_conversations( $user_id );
    }

    public function get_messages( $conversation_id, $user_id, $after = 0 ) {
        $deleted_ids = get_user_meta( $user_id, 'tawasol_deleted_messages', true ) ?: array();
        $messages = $this->db->get_messages( $conversation_id, $after, $deleted_ids );

        if ( ! empty( $messages ) ) {
            $msg_ids = array();
            foreach ( $messages as $message ) {
                if ( $message->sender_id != $user_id && $message->status === 'sent' ) {
                    $msg_ids[] = $message->id;
                }
            }

            if ( ! empty( $msg_ids ) ) {
                $this->db->mark_delivered( $msg_ids );
                foreach ( $messages as &$message ) {
                    if ( in_array( $message->id, $msg_ids ) ) {
                        $message->status = 'delivered';
                    }
                }
            }
        }

        foreach ( $messages as &$message ) {
            if ( $message->content_type === 'text' ) {
                $message->content = Tawasol_Encryption::decrypt( $message->content );
            }
        }

        return $messages;
    }

    public function send_message( $conversation_id, $sender_id, $content, $type = 'text' ) {
        $data = array(
            'conversation_id' => $conversation_id,
            'sender_id'       => $sender_id,
            'content'         => ( $type === 'text' ) ? Tawasol_Encryption::encrypt( $content ) : $content,
            'content_type'    => $type,
            'status'          => 'sent'
        );

        $message_id = $this->db->insert_message( $data );
        if ( $message_id ) {
            do_action( 'tawasol_message_sent', $message_id, $conversation_id, $sender_id );
        }
        return $message_id;
    }

    public function start_conversation( $title, $type, $creator_id, $participants = array() ) {
        $conv_id = $this->db->create_conversation( $title, $type );
        if ( $conv_id ) {
            $this->db->add_participant( $conv_id, $creator_id, 1 );
            foreach ( $participants as $p_id ) {
                if ( $p_id != $creator_id ) {
                    $this->db->add_participant( $conv_id, $p_id, 0 );
                }
            }
        }
        return $conv_id;
    }

    public function search_messages( $user_id, $term ) {
        $recent_messages = $this->db->get_recent_messages_for_user( $user_id );
        $results = array();
        $term = strtolower( $term );

        foreach ( $recent_messages as $msg ) {
            if ( $msg->content_type === 'text' ) {
                $decrypted = Tawasol_Encryption::decrypt( $msg->content );
                if ( strpos( strtolower( $decrypted ), $term ) !== false ) {
                    $msg->content = $decrypted;
                    $results[] = $msg;
                }
            }
        }
        return $results;
    }
}
