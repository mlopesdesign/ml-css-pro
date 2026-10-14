<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Atualizacao automatica via GitHub Releases.
 *
 * Fonte: repositorio PUBLICO mlopesdesign/ml-css-pro-releases (somente ZIPs).
 * - Usa somente o asset exato ml-css-pro-vX.Y.Z.zip (sem zipball, sem "qualquer ZIP").
 * - response quando ha versao nova; no_update caso contrario (controle de auto-update do WP).
 * - Cache: 6 h com sucesso, 15 min em falha (falha nao apaga um resultado ainda valido).
 * - Sem rotina pos-instalacao: o ZIP oficial ja vem com a pasta ml-css-pro/.
 *
 * @since 1.5.5 (antes: repo de codigo, "qualquer .zip" ou zipball, falha em cache por 6 h)
 */
final class MLCSSP_Updater {

    private const FAIL_TTL = 900;
    private const OK_TTL   = 21600;

    private string $plugin_file;
    private string $plugin_slug;
    private string $github_repo;
    private string $current_version;
    private string $transient_key;

    public function __construct(
        string $plugin_file,
        string $plugin_slug,
        string $github_repo,
        string $current_version
    ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = $plugin_slug;
        $this->github_repo     = $github_repo;
        $this->current_version = $current_version;
        // Chave inclui o repositorio: o cache gravado pela 1.5.4 (repo antigo) nao e reaproveitado.
        $this->transient_key   = 'mlcssp_updater_' . md5($plugin_slug . '|' . $github_repo);
    }

    public function hooks(): void {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'after_upgrade'], 10, 2);
    }

    /**
     * @param mixed $transient
     * @return mixed
     */
    public function check_update($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        $remote  = $release ? $this->release_version($release) : '';
        $package = $release ? $this->find_zip_url($release) : '';

        $item = (object) [
            'id'            => 'github.com/' . $this->github_repo,
            'slug'          => $this->plugin_slug,
            'plugin'        => $this->plugin_file,
            'new_version'   => $remote,
            'url'           => 'https://github.com/' . $this->github_repo,
            'package'       => $package,
            'icons'         => $this->icons(),
            'banners'       => [],
            'banners_rtl'   => [],
            'tested'        => '6.8',
            'requires'      => '6.0',
            'requires_php'  => '8.1',
            'compatibility' => new stdClass(),
        ];

        if ($remote !== '' && $package !== '' && version_compare($this->current_version, $remote, '<')) {
            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = [];
            }
            $transient->response[$this->plugin_file] = $item;
            if (isset($transient->no_update) && is_array($transient->no_update)) {
                unset($transient->no_update[$this->plugin_file]);
            }
        } else {
            $item->new_version = $this->current_version;
            $item->package     = '';
            if (!isset($transient->no_update) || !is_array($transient->no_update)) {
                $transient->no_update = [];
            }
            $transient->no_update[$this->plugin_file] = $item;
            if (isset($transient->response) && is_array($transient->response)) {
                unset($transient->response[$this->plugin_file]);
            }
        }

        return $transient;
    }

    /**
     * @param mixed $result
     * @param mixed $args
     * @return mixed
     */
    public function plugin_info($result, string $action, $args) {
        if ('plugin_information' !== $action || !is_object($args) || empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }
        $release = $this->get_latest_release();
        if (!$release || $this->find_zip_url($release) === '') {
            return $result;
        }
        $package = $this->find_zip_url($release);
        return (object) [
            'name'          => 'ML CSS Pro',
            'slug'          => $this->plugin_slug,
            'version'       => $this->release_version($release),
            'author'        => '<a href="https://mlopesdesign.com.br">ML Lopes Design</a>',
            'homepage'      => 'https://mlopesdesign.com.br',
            'download_link' => $package,
            'trunk'         => $package,
            'requires'      => '6.0',
            'requires_php'  => '8.1',
            'tested'        => '6.8',
            'last_updated'  => (string) ($release['published_at'] ?? ''),
            'sections'      => [
                'description' => '<p>ML CSS Pro - editor visual de CSS por ML Lopes Design.</p>',
                'changelog'   => '<pre>' . esc_html((string) ($release['body'] ?? '')) . '</pre>',
            ],
            'banners' => [],
            'icons'   => $this->icons(),
        ];
    }

    /**
     * Limpa o cache depois que este plugin e atualizado (lote usa 'plugins', simples/automatica 'plugin').
     *
     * @param mixed $upgrader
     * @param mixed $hook_extra
     */
    public function after_upgrade($upgrader, $hook_extra): void {
        if (!is_array($hook_extra) || ($hook_extra['type'] ?? '') !== 'plugin') {
            return;
        }
        $mine = in_array($this->plugin_file, (array) ($hook_extra['plugins'] ?? []), true)
            || ($hook_extra['plugin'] ?? '') === $this->plugin_file;
        if ($mine) {
            delete_transient($this->transient_key);
            delete_transient('mlcssp_updater_' . md5($this->plugin_slug)); // chave da 1.5.4
        }
    }

    private function get_latest_release(): ?array {
        $cached = get_transient($this->transient_key);
        if (false !== $cached) {
            return (is_array($cached) && !empty($cached)) ? $cached : null;
        }
        $url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
        $response = wp_remote_get($url, [
            'timeout'    => 10,
            'user-agent' => 'ML-CSS-Pro-Updater/' . $this->current_version . '; WordPress/' . get_bloginfo('version'),
            'headers'    => ['Accept' => 'application/vnd.github+json'],
        ]);
        $data = null;
        if (!is_wp_error($response) && 200 === (int) wp_remote_retrieve_response_code($response)) {
            $data = json_decode((string) wp_remote_retrieve_body($response), true);
        }
        if (!is_array($data) || empty($data['tag_name'])) {
            set_transient($this->transient_key, [], self::FAIL_TTL);
            return null;
        }
        // Release ainda sem o ZIP exato (asset em upload): cache curto para nao atrasar a oferta.
        set_transient($this->transient_key, $data, $this->find_zip_url($data) !== '' ? self::OK_TTL : self::FAIL_TTL);
        return $data;
    }

    private function release_version(array $release): string {
        return ltrim((string) ($release['tag_name'] ?? ''), 'vV');
    }

    private function find_zip_url(array $release): string {
        $expected = $this->plugin_slug . '-v' . $this->release_version($release) . '.zip';
        foreach ((array) ($release['assets'] ?? []) as $asset) {
            if (is_array($asset) && ($asset['name'] ?? '') === $expected && !empty($asset['browser_download_url'])) {
                return (string) $asset['browser_download_url'];
            }
        }
        return '';
    }

    /**
     * @return array<string,string>
     */
    private function icons(): array {
        return [
            '1x'      => MLCSSP_URL . 'assets/images/icon-128x128.png',
            '2x'      => MLCSSP_URL . 'assets/images/icon-256x256.png',
            'default' => MLCSSP_URL . 'assets/images/icon-256x256.png',
        ];
    }
}
