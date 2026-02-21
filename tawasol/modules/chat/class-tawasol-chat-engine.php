<?php
/**
 * Core Chat Engine for business logic.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/modules/chat
 */

class Tawasol_Chat_Engine {

    private $queries;
    private $transactions;

    public function __construct() {
        $this->queries = new Tawasol_DB_Queries();
        $this->transactions = new Tawasol_DB_Transactions();
    }

    public function get_conversations( $user_id ) {
        $conversations = $this->queries->get_user_conversations( $user_id );
        foreach ( $conversations as &$conv ) {
            if ( $conv->type === 'one-on-one' ) {
                if ( empty( $conv->other_user_id ) && $conv->participant_count == 1 ) {
                    $conv->title = __( 'Archives', 'tawasol' );
                    $conv->is_self = true;
                } else {
                    $other_user = get_userdata( $conv->other_user_id );
                    $conv->title = $other_user ? $other_user->display_name : __( 'Deleted User', 'tawasol' );
                    $conv->is_self = false;
                }
            }
        }
        return $conversations;
    }

    public function get_messages( $conversation_id, $user_id, $after = 0 ) {
        $deleted_ids = get_user_meta( $user_id, 'tawasol_deleted_messages', true ) ?: array();
        $messages = $this->queries->get_messages( $conversation_id, $after, $deleted_ids );

        if ( ! empty( $messages ) ) {
            $msg_ids = array();
            foreach ( $messages as $message ) {
                if ( $message->sender_id != $user_id && $message->status === 'sent' ) {
                    $msg_ids[] = $message->id;
                }
            }

            if ( ! empty( $msg_ids ) ) {
                $this->transactions->mark_delivered( $msg_ids );
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

        $message_id = $this->transactions->insert_message( $data );
        if ( $message_id ) {
            do_action( 'tawasol_message_sent', $message_id, $conversation_id, $sender_id );
        }
        return $message_id;
    }

    public function start_conversation( $title, $type, $creator_id, $participants = array() ) {
        // Feature: Single Chat Thread Enforcement
        if ( $type === 'one-on-one' && count($participants) === 1 ) {
            $other_user = $participants[0];
            $existing = $this->queries->find_existing_one_on_one( $creator_id, $other_user );
            if ( $existing ) return $existing;
        }

        $conv_id = $this->transactions->create_conversation( $title, $type );
        if ( $conv_id ) {
            $this->transactions->add_participant( $conv_id, $creator_id, 1 );
            foreach ( $participants as $p_id ) {
                if ( $p_id != $creator_id ) {
                    $this->transactions->add_participant( $conv_id, $p_id, 0 );
                }
            }
        }
        return $conv_id;
    }

    public function archive_conversation( $conversation_id, $user_id, $is_archived ) {
        return $this->transactions->set_archived( $conversation_id, $user_id, $is_archived );
    }

    public function search_messages( $user_id, $term ) {
        $recent_messages = $this->queries->get_recent_messages_for_user( $user_id );
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
