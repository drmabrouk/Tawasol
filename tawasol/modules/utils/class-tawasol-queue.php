<?php
/**
 * Asynchronous processing queue for high-volume tasks.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/modules/utils
 */

class Tawasol_Queue {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tawasol_queue';
    }

    /**
     * Add a job to the queue.
     */
    public function push( $type, $payload ) {
        global $wpdb;
        return $wpdb->insert( $this->table_name, array(
            'job_type' => $type,
            'payload'  => json_encode( $payload ),
            'status'   => 'pending'
        ) );
    }

    /**
     * Process pending jobs in the queue.
     * Can be called via WP-Cron or during SSE idle time.
     */
    public function process_batch( $limit = 10 ) {
        global $wpdb;

        $jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE status = 'pending' ORDER BY id ASC LIMIT %d",
            $limit
        ) );

        if ( empty( $jobs ) ) return 0;

        foreach ( $jobs as $job ) {
            $wpdb->update( $this->table_name, array( 'status' => 'processing' ), array( 'id' => $job->id ) );

            $success = $this->execute_job( $job->job_type, json_decode( $job->payload, true ) );

            if ( $success ) {
                $wpdb->update( $this->table_name, array( 'status' => 'completed' ), array( 'id' => $job->id ) );
            } else {
                $attempts = $job->attempts + 1;
                $status = ( $attempts >= 3 ) ? 'failed' : 'pending';
                $wpdb->update( $this->table_name,
                    array( 'status' => $status, 'attempts' => $attempts ),
                    array( 'id' => $job->id )
                );
            }
        }

        return count( $jobs );
    }

    /**
     * Execute a specific job.
     */
    private function execute_job( $type, $payload ) {
        switch ( $type ) {
            case 'process_notification':
                // logic for async push notifications
                return true;
            case 'optimize_media':
                // logic for async image optimization if not done client-side
                return true;
            case 'sync_analytics':
                // logic for logging analytics without blocking the chat
                return true;
            default:
                do_action( 'tawasol_queue_job_' . $type, $payload );
                return true;
        }
    }
}
