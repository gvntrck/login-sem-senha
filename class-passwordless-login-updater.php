<?php
if (!defined('ABSPATH')) {
    exit; // Bloqueia acesso direto ao arquivo
}

class PasswordlessLoginUpdater {
    private $plugin_file;
    private $github_url;
    private $current_version;

    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->github_url = 'https://api.github.com/repos/gvntrck/plugin-login-sem-senha';
        $this->current_version = $this->get_plugin_version();
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    }

    private function get_plugin_version() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data($this->plugin_file);
        return $plugin_data['Version'];
    }

    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $response = wp_remote_get($this->github_url . '/releases/latest', [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            ]
        ]);

        if (is_wp_error($response)) {
            return $transient;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return $transient;
        }

        $release = json_decode(wp_remote_retrieve_body($response));
        if (empty($release) || !isset($release->tag_name)) {
            return $transient;
        }

        // Remove o 'v' da tag se existir
        $latest_version = str_replace('v', '', $release->tag_name);
        $current_version = str_replace('v', '', $this->current_version);

        if (version_compare($latest_version, $current_version, '>')) {
            // Pega o primeiro asset (ZIP) da release
            $download_url = '';
            if (!empty($release->assets) && isset($release->assets[0]->browser_download_url)) {
                $download_url = $release->assets[0]->browser_download_url;
            } else {
                $download_url = $release->zipball_url; // Fallback para o zipball
            }

            $transient->response[plugin_basename($this->plugin_file)] = (object)[
                'slug' => plugin_basename($this->plugin_file),
                'new_version' => $latest_version,
                'package' => $download_url,
                'url' => $release->html_url
            ];
        }

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== plugin_basename($this->plugin_file)) {
            return $result;
        }

        $response = wp_remote_get($this->github_url . '/releases/latest', [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            ]
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $result;
        }

        $release = json_decode(wp_remote_retrieve_body($response));
        if (empty($release)) {
            return $result;
        }

        $plugin_data = get_plugin_data($this->plugin_file);
        $latest_version = str_replace('v', '', $release->tag_name);
        
        // Pega o primeiro asset (ZIP) da release
        $download_url = '';
        if (!empty($release->assets) && isset($release->assets[0]->browser_download_url)) {
            $download_url = $release->assets[0]->browser_download_url;
        } else {
            $download_url = $release->zipball_url; // Fallback para o zipball
        }
        
        $result = (object)[
            'name' => $plugin_data['Name'],
            'slug' => plugin_basename($this->plugin_file),
            'version' => $latest_version,
            'author' => '<a href="https://github.com/giovanitrevisol">Giovani Trueck</a>',
            'homepage' => $release->html_url,
            'requires' => $plugin_data['RequiresWP'] ?? '5.0',
            'tested' => $plugin_data['TestedUpTo'] ?? get_bloginfo('version'),
            'download_link' => $download_url,
            'sections' => [
                'description' => $plugin_data['Description'],
                'changelog' => $release->body ?? 'See GitHub releases for changelog.'
            ]
        ];

        return $result;
    }
}
