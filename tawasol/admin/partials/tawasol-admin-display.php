<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="tawasol-admin-header" style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 class="nav-tab-wrapper" style="margin-bottom: 0;">
            <a href="#users" class="nav-tab nav-tab-active"><?php _e( 'Users', 'tawasol' ); ?></a>
            <a href="#logs" class="nav-tab"><?php _e( 'Security Logs', 'tawasol' ); ?></a>
            <a href="#analytics" class="nav-tab"><?php _e( 'Analytics', 'tawasol' ); ?></a>
        </h2>
        <button id="tawasol-launch-chat-admin" class="button button-primary"><?php _e( 'Open Full-Screen Chat', 'tawasol' ); ?></button>
    </div>

    <div id="tawasol-admin-content">
        <div id="users-section">
            <h3><?php _e( 'Manage Users', 'tawasol' ); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Username', 'tawasol' ); ?></th>
                        <th><?php _e( 'Email', 'tawasol' ); ?></th>
                        <th><?php _e( 'Phone', 'tawasol' ); ?></th>
                        <th><?php _e( 'Status', 'tawasol' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
                    $number = 20;
                    $user_query = new WP_User_Query( array(
                        'number' => $number,
                        'offset' => ( $paged - 1 ) * $number,
                    ) );
                    $users = $user_query->get_results();
                    foreach ( $users as $user ) {
                        $phone = get_user_meta( $user->ID, 'tawasol_phone', true );
                        echo "<tr>
                            <td>" . esc_html( $user->user_login ) . "</td>
                            <td>" . esc_html( $user->user_email ) . "</td>
                            <td>" . esc_html( $phone ) . "</td>
                            <td>" . __( 'Active', 'tawasol' ) . "</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php
            $total_users = $user_query->get_total();
            $total_pages = ceil( $total_users / $number );
            if ( $total_pages > 1 ) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links( array(
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'prev_text' => __( '&laquo;' ),
                    'next_text' => __( '&raquo;' ),
                    'total'   => $total_pages,
                    'current' => $paged,
                ) );
                echo '</div></div>';
            }
            ?>
        </div>

        <div id="logs-section" style="display:none;">
            <h3><?php _e( 'Security Logs', 'tawasol' ); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Date', 'tawasol' ); ?></th>
                        <th><?php _e( 'Event', 'tawasol' ); ?></th>
                        <th><?php _e( 'User', 'tawasol' ); ?></th>
                        <th><?php _e( 'IP', 'tawasol' ); ?></th>
                        <th><?php _e( 'Description', 'tawasol' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    global $wpdb;
                    $table_logs = $wpdb->prefix . 'tawasol_security_logs';
                    $logs = $wpdb->get_results( "SELECT * FROM $table_logs ORDER BY created_at DESC LIMIT 50" );
                    foreach ( $logs as $log ) {
                        $user = $log->user_id ? get_userdata( $log->user_id ) : null;
                        $username = $user ? $user->user_login : 'Guest';
                        echo "<tr>
                            <td>{$log->created_at}</td>
                            <td>{$log->event_type}</td>
                            <td>{$username}</td>
                            <td>{$log->ip_address}</td>
                            <td>{$log->description}</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div id="analytics-section" style="display:none;">
            <h3><?php _e( 'Analytics', 'tawasol' ); ?></h3>
            <?php
            global $wpdb;
            $table_msgs = $wpdb->prefix . 'tawasol_messages';
            $table_convs = $wpdb->prefix . 'tawasol_conversations';
            $msg_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_msgs" );
            $conv_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_convs" );
            $user_count = count_users()['total_users'];
            $active_today = $wpdb->get_var( "SELECT COUNT(DISTINCT sender_id) FROM $table_msgs WHERE created_at >= CURDATE()" );
            ?>
            <div class="tawasol-stats" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="card" style="padding: 20px; background: white; border: 1px solid #ccd0d4;">
                    <h4><?php _e( 'Total Messages', 'tawasol' ); ?></h4>
                    <p style="font-size: 24px; font-weight: bold; margin:0;"><?php echo intval( $msg_count ); ?></p>
                </div>
                <div class="card" style="padding: 20px; background: white; border: 1px solid #ccd0d4;">
                    <h4><?php _e( 'Total Conversations', 'tawasol' ); ?></h4>
                    <p style="font-size: 24px; font-weight: bold; margin:0;"><?php echo intval( $conv_count ); ?></p>
                </div>
                <div class="card" style="padding: 20px; background: white; border: 1px solid #ccd0d4;">
                    <h4><?php _e( 'Total Users', 'tawasol' ); ?></h4>
                    <p style="font-size: 24px; font-weight: bold; margin:0;"><?php echo intval( $user_count ); ?></p>
                </div>
                <div class="card" style="padding: 20px; background: white; border: 1px solid #ccd0d4;">
                    <h4><?php _e( 'Active Users (Today)', 'tawasol' ); ?></h4>
                    <p style="font-size: 24px; font-weight: bold; margin:0;"><?php echo intval( $active_today ); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#tawasol-launch-chat-admin').click(function() {
        if (typeof window.tawasolOpenChat === 'function') {
            window.tawasolOpenChat();
        }
    });

    $('.nav-tab').click(function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        const target = $(this).attr('href').substring(1);
        $('#tawasol-admin-content > div').hide();
        $('#' + target + '-section').show();
    });
});
</script>
