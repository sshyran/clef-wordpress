<?php

class LoginException extends Exception {}

class ClefCore {
    private static $instance = null;

    private $settings;
    private $badge;

    private function __construct() {
        // General utility functions
        require_once(CLEF_PATH . 'includes/lib/utils.inc');
        require_once(CLEF_PATH . 'includes/class.clef-utils.php');

        // Site options
        require_once(CLEF_PATH . 'includes/class.clef-internal-settings.php');
        $settings = ClefInternalSettings::start();

        // Onboarding settings
        require_once(CLEF_PATH . 'includes/class.clef-onboarding.php');
        $onboarding = ClefOnboarding::start($settings);

        // Clef login functions
        require_once(CLEF_PATH . 'includes/class.clef-login.php');
        $login = ClefLogin::start($settings);

        // Clef logout hook functions
        require_once(CLEF_PATH . 'includes/class.clef-logout.php');
        $logout = ClefLogout::start($settings);

        // Badge display options
        require_once(CLEF_PATH . 'includes/class.clef-badge.php');
        $badge = ClefBadge::start($settings, $onboarding);
        $badge->hook_display();

        // Admin functions and hooks
        $admin = null;
        require_once(CLEF_PATH . 'includes/class.clef-admin.php');
        require_once(CLEF_PATH . 'includes/class.clef-network-admin.php');
        if (is_network_admin()) {
            $admin = ClefNetworkAdmin::start($settings);
        } else if (is_admin()) {
            $admin = ClefAdmin::start($settings);
        }

        // Plugin setup hooks
        require_once(CLEF_PATH . 'includes/class.clef-setup.php');

        $this->settings = $settings;
        $this->badge = $badge; 

        // Load translations
        load_plugin_textdomain( 'clef', false, CLEF_PATH . 'languages/' );

        // Register public hooks
        if ($admin) {
            add_action('clef_render_settings', array($admin, 'general_settings'));
        }
        add_action('clef_plugin_uninstall', array('ClefSetup', 'uninstall_plugin'));

        add_action('clef_plugin_updated', array($this, 'plugin_updated'), 10, 2);

        // Run migrations and other hooks upon plugin update
        $old_version = $settings->get('version');
        $current_version = CLEF_VERSION;
        if (!$old_version || $current_version != $old_version) {
            do_action('clef_plugin_updated', $current_version, $old_version);
        }

        if (CLEF_IS_BASE_PLUGIN) {
            do_action('clef_hook_admin_menu');
        }
    }

    public function plugin_updated($version, $previous_version) {
        $settings_changes = false;

        if ($previous_version) {
            if (version_compare($previous_version, "1.9.1.1", '<')) {
                $this->badge->hide_prompt();
            }

            if (version_compare($previous_version, "1.9", '<')) {
               if (!$previous_version) {
                    $previous_version = $version;
               }
               $this->settings->get('installed_at', $previous_version);
            }

            if (version_compare($previous_version, "1.8.0", '<')) {
                $settings_changes = array(
                    "clef_password_settings_override_key" => "clef_override_settings_key"
                );
            }
        } else {
            $this->settings->set('installed_at', $version);
        }

        if ($settings_changes) {
            foreach ($settings_changes as $old_name => $new_name) {
                $value = $this->settings->get($old_name);
                if ($value) {
                    $this->settings->set($new_name, $value);
                    $this->settings->remove($old_name);
                }
            }
        }

        $this->settings->set("version", $version);
    }

    public static function start() {
        if (!isset(self::$instance) || self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }
}

?>