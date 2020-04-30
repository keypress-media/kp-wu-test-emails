<?php
/**
 * Plugin Name:       KeyPress WP Ultimo Test Emails
 * Plugin URI:        https://github.com/keypress-media/kp-wu-test-emails
 * Description:       Send test emails of WP Ultimo email templates.
 * Version:           1.0
 * Author:            KeyPress Media
 * Author URI:        https://getkeypress.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       kpwutem
 * Domain Path:       /languages
 *
 * KeyPress WP Ultimo Test Emails is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * KeyPress WP Ultimo Test Emails is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with KeyPress UI. If not, see <http://www.gnu.org/licenses/>.
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'KPWUTEM_PLUGIN_VERSION' ) ) {
    define( 'KPWUTEM_PLUGIN_VERSION', '1.0' );
}

if ( ! defined( 'KPWUTEM_PLUGIN_FILE' ) ) {
    define( 'KPWUTEM_PLUGIN_FILE', trailingslashit( dirname( dirname( __FILE__ ) ) ) . 'kp-snippets.php' );
}

if ( ! defined( 'KPWUTEM_PLUGIN_DIR' ) ) {
    define( 'KPWUTEM_PLUGIN_DIR', plugin_dir_path( KPWUTEM_PLUGIN_FILE ) );
}

if ( ! class_exists( 'KP_WU_TEST_EMAILS' ) ) {

    final class KP_WU_TEST_EMAILS {

        const ACTION_SEND_TEST_EMAIL = 'kpwutem_send_test_email';

        private static $instance;

        private function __construct() {
            $this->init();
        }

        public static function instance() {
            if ( ! isset( self::$instance ) && ! ( self::$instance instanceof KP_WU_TEST_EMAILS ) ) {
                self::$instance = new KP_WU_TEST_EMAILS();
            }
            return self::$instance;
        }

        /**
         * Initialize hooks.
         *
         * @since 1.0
         * @return void
         */
        public function init() {
            add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

            if ( ! is_multisite() ) {
                add_action( 'admin_notices', array( $this, 'render_not_multisite_notice' ) );
            } elseif( ! is_plugin_active( 'wp-ultimo/wp-ultimo.php' ) ) {
                add_action( 'network_admin_notices', array( $this, 'render_not_wp_ultimo_notice' ) );
            } else {
                add_action( 'wu_after_settings_section_emails', array( $this, 'add_test_emails_section' ) );
                add_action( 'wp_ajax_' . self::ACTION_SEND_TEST_EMAIL, array( $this, 'ajax_send_test_email' ) );
            }
        }

        /**
         * Load the plugin text domain for translation.
         *
         * @since    1.0
         */
        public function load_plugin_textdomain() {
            load_plugin_textdomain(
                'kpwutem',
                false,
                KPWUTEM_PLUGIN_VERSION . '/languages/'
            );
        }

        /**
         * Adds the section that allows sending test emails.
         *
         * @since 1.0
         * @return void
         */
        public function add_test_emails_section() {
            require_once WP_PLUGIN_DIR . '/wp-ultimo/inc/class-wu-admin-settings.php';
            require_once WP_PLUGIN_DIR . '/wp-ultimo/inc/class-wu-mail.php';

            $wu_mail         = WU_Mail::get_instance();
            $email_templates = $wu_mail->get_templates();

            ?>
            <table class="form-table" id="rr-send-test-email-section" style="margin-bottom: 50px;">
                <thead>
                    <tr>
                        <th colspan="2">
                            <h3><?php _e( 'Test Email Templates', 'kpwutem' ); ?></h3>
                            <p style="font-weight: initial;"><?php _e( 'Send tests of the email templates to check if they are visualized correctly.', 'kpwutem' ); ?></p>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row"><label for="expiring_days"><?php _e( 'Send Test Email', 'kpwutem' ) ?></label> </th>
                        <td>
                            <input type="text" id="rr-test-email-address" placeholder="email address" style="vertical-align: bottom"/>
                            <select id="rr-test-email-template">
                                <?php foreach( $email_templates as $id => $template ) : ?>
                                    <option value="<?php echo esc_attr( $id ) ?>">
                                        <?php echo esc_html( $template['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button id="rr-send-test-email-btn" class="button button-secondary"><?php esc_html_e( 'Send Test Email', 'kpwutem' ) ?></button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <script type="text/javascript">
                (function( $ ) {
                    $( document ).ready( function () {
                        var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/,
                            url        = '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                            sendBtn    = $( '#rr-send-test-email-btn' ),
                            spinner    = $( '<div class="spinner" style="float: none;"></div>' ),
                            msg        = $( '<span style="display:inline-block;margin-left: 10px;"></span>' );

                        spinner.insertAfter( sendBtn );
                        msg.insertAfter( sendBtn );

                        sendBtn.click( function( e ) {
                            e.preventDefault();
                            msg.hide();
                            var email    = $('#rr-test-email-address').val();
                            var template = $('#rr-test-email-template').val();

                            if( ! email.match( mailformat ) ) {
                                alert( 'Please enter a valid email address' );
                                return false;
                            } else {
                                spinner.addClass('is-active');

                                var data    = {
                                    'action'   : '<?php echo self::ACTION_SEND_TEST_EMAIL ?>',
                                    'nonce'    : '<?php echo wp_create_nonce( self::ACTION_SEND_TEST_EMAIL ); ?>',
                                    'email'    : email,
                                    'template' : template
                                }

                                $.post( url, data, function( response ) {
                                    spinner.removeClass('is-active');
                                    msg.text(response);
                                    msg.show();
                                });
                            }
                        });
                    });
                })( jQuery );
            </script>
            <?php
        }

        /**
         * Handles the ajax request and sends a test email.
         *
         * @since 1.0
         * @return void
         */
        public function ajax_send_test_email() {

            $error_msg   = __( 'Error!', 'kpwutem' );
            $success_msg = __( 'Done!', 'kpwutem' );

            if ( ! current_user_can( 'manage_network_options' ) ||
                ! isset( $_POST['action'] ) ||
                ! isset( $_POST['nonce'] ) ||
                ! isset( $_POST['email'] ) ||
                ! isset( $_POST['template'] ) ||
                ! wp_verify_nonce( $_POST['nonce'], self::ACTION_SEND_TEST_EMAIL ) )
            {
                wp_die( $error_msg );
            }

            require_once WP_PLUGIN_DIR . '/wp-ultimo/inc/class-wu-admin-settings.php';
            require_once WP_PLUGIN_DIR . '/wp-ultimo/inc/class-wu-mail.php';

            $wu_mail  = WU_Mail::get_instance();
            $email    = $_POST['email'];
            $template = $_POST['template'];
            $result   = $wu_mail->send_template( $template, $email, array() );

            wp_die( $result ? $success_msg : $error_msg );
        }

        /**
         * Shows an admin notice if multisite is not enabled.
         *
         * @since 1.0
         * @return void
         */
        public function render_not_multisite_notice() {
            $message = __( 'KeyPress WP Ultimo Test Emails plugin requires multisite to be activated.', 'kpwutem' );
            $this->_render_admin_notice( $message, 'warning' );
        }

        /**
         * Shows an admin notice if WP Ultimo is not active.
         *
         * @since 1.0
         * @return void
         */
        public function render_not_wp_ultimo_notice() {
            $message = __( 'KeyPress WP Ultimo Test Emails plugin requires WP Ultimo plugin to be active.', 'kpwutem' );
            $this->_render_admin_notice( $message, 'warning' );
        }

        /**
         * Renders an admin notice.
         *
         * @since 1.0
         * @access private
         * @param string $message
         * @param string $type
         * @return void
         */
        private function _render_admin_notice( $message, $type = 'update' ) {
            if ( ! is_admin() ) {
                return;
            } elseif ( ! is_user_logged_in() ) {
                return;
            } elseif ( ! current_user_can( 'update_plugins' ) ) {
                return;
            }

            echo '<div class="notice notice-' . $type . ' is-dismissible">';
            echo '<p>' . $message . '</p>';
            echo '</div>';
        }

        /**
         * Throw error on object clone.
         *
         * The whole idea of the singleton design pattern is that there is a single
         * object therefore, we don't want the object to be cloned.
         *
         * @since 1.0
         * @access protected
         * @return void
         */
        public function __clone() {
            // Cloning instances of the class is forbidden.
            _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'kpwutem' ), KPWUTEM_PLUGIN_VERSION );
        }

        /**
         * Disable unserializing of the class.
         *
         * @since 1.0
         * @access protected
         * @return void
         */
        public function __wakeup() {
            // Unserializing instances of the class is forbidden.
            _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'kpwutem' ), KPWUTEM_PLUGIN_VERSION );
        }
    }
}

KP_WU_TEST_EMAILS::instance();