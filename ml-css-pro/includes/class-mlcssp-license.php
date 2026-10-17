<?php
/**
 * Licença do ML CSS Pro.
 *
 * 1.6.0: a licença passa a ser emitida e conferida pelo site tools.mlopesdesign.com.br
 * (módulo ML License Hub, cliente ML_License_Client_V2). O servidor antigo
 * (hub.mlopesdesign.com.br) não é mais consultado. Regras do padrão ML:
 * 1 licença = 1 site (o Pacote Agência vale em vários sites); teste grátis de 30 dias
 * por site; sem teste/licença válidos, o painel fica só para leitura (salvar CSS,
 * overrides, presets, histórico, importar, editor visual) — o CSS já salvo continua
 * aplicado no site e nenhum dado é apagado.
 *
 * Quem já usava o plugin antes da 1.6.0 recebe o teste grátis automaticamente na
 * primeira visita ao painel (o site não trava de surpresa após a atualização).
 *
 * A option antiga (mlcsspro_license) é preservada (não é mais usada).
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-ml-license-client.php';

final class MLCSSP_License {
    public const API_BASE    = 'https://tools.mlopesdesign.com.br/wp-json/mllh/v1';
    public const PUBLIC_KEY  = 'VGZQp2X3RJMuDmQBlIEXS4fqsZD1CnFlIJgfVtY/pHo=';
    public const PRODUCT_ID  = 'ml-css-pro';
    public const BUY_URL     = 'https://tools.mlopesdesign.com.br/precos/';
    public const ACCOUNT_URL = 'https://tools.mlopesdesign.com.br/minha-conta/';
    public const AUTO_TRIAL  = 'mlcssp_auto_trial_tried';

    private static ?ML_License_Client_V2 $client = null;
    private static ?bool $locked = null;
    private static bool $booted = false;

    public static function client(): ML_License_Client_V2 {
        if (self::$client === null) {
            self::$client = new ML_License_Client_V2([
                'api_base'   => self::API_BASE,
                'product'    => self::PRODUCT_ID,
                'public_key' => self::PUBLIC_KEY,
                'version'    => MLCSSP_VERSION,
            ]);
        }
        return self::$client;
    }

    /** Teste ou licença válidos neste site. */
    public static function is_active(): bool {
        return self::client()->is_premium();
    }

    /** Painel travado para gravar (sem teste nem licença). */
    public static function is_locked(): bool {
        if (self::$locked === null) {
            self::$locked = !self::is_active();
        }
        return self::$locked;
    }

    public static function forget(): void {
        self::$locked = null;
    }

    /* ------------------------------------------------------------------ ganchos */

    public static function boot(): void {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::client(); // agenda a renovação diária
        add_action('admin_init', [__CLASS__, 'maybe_auto_trial'], 5);
        add_action('admin_notices', [__CLASS__, 'locked_notice']);
        // Formulário padrão do WP (options.php) para o grupo de configurações do plugin.
        add_filter('pre_update_option_' . MLCSSP_OPTION_KEY, [__CLASS__, 'lock_options_php'], 10, 2);
        add_action('admin_post_mlcssp_activate_license', [__CLASS__, 'handle_activate_request']);
        add_action('admin_post_mlcssp_sync_license', [__CLASS__, 'handle_sync_request']);
        add_action('admin_post_mlcssp_remove_license', [__CLASS__, 'handle_remove_request']);
        $opt = self::client()->option_name();
        foreach (['added_option', 'updated_option', 'deleted_option'] as $hook) {
            add_action($hook, static function ($name) use ($opt) {
                if ($name === $opt) {
                    MLCSSP_License::forget();
                }
            });
        }
    }

    /** Primeira visita ao painel: começa o teste grátis sozinho (uma tentativa por site). */
    public static function maybe_auto_trial(): void {
        if (wp_doing_ajax() || !current_user_can('manage_options') || get_option(self::AUTO_TRIAL)) {
            return;
        }
        update_option(self::AUTO_TRIAL, time(), false);
        $state = self::client()->state();
        if (empty($state['active']) && 'none' === $state['mode'] && empty($state['trial_used'])) {
            self::client()->start_trial();
        }
    }

    /**
     * Para as ações de gravação do painel (admin-post e AJAX).
     * Sem licença: não grava e volta para a aba Licença com o aviso.
     */
    public static function guard_write(bool $ajax = false): void {
        if (!self::is_locked()) {
            return;
        }
        $msg = 'Ative a licença (ou comece o teste grátis) para alterar o ML CSS Pro. O CSS já salvo continua aplicado no site.';
        if ($ajax) {
            wp_send_json_error($msg, 403);
        }
        self::redirect_to_license('error', $msg);
    }

    /** Bloqueia gravar as configurações pelo options.php quando travado. */
    public static function lock_options_php($value, $old_value) {
        if (isset($GLOBALS['pagenow']) && 'options.php' === $GLOBALS['pagenow'] && self::is_locked()) {
            return $old_value;
        }
        return $value;
    }

    public static function locked_notice(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || 'toplevel_page_ml-css-pro' !== $screen->id || !current_user_can('manage_options') || !self::is_locked()) {
            return;
        }
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';
        if ('license' === $tab) {
            return;
        }
        printf(
            '<div class="notice notice-warning"><p><strong>ML CSS Pro:</strong> %s <a href="%s">%s</a></p></div>',
            esc_html('sem licença ativa — o painel está só para leitura. O CSS já salvo continua aplicado no site.'),
            esc_url(self::license_url()),
            esc_html('Ativar licença')
        );
    }

    /* ------------------------------------------------------------------ tela */

    /** Estado pronto para a tela. */
    public function get_license_summary(): array {
        $state  = self::client()->state();
        $active = !empty($state['active']);
        $mode   = (string) ($state['mode'] ?? 'none');
        $plan   = strtolower((string) ($state['plan'] ?? ''));
        $days   = $state['days_left'] ?? null;

        $plans = [
            'trial'    => 'Teste grátis',
            'monthly'  => 'Mensal',
            'annual'   => 'Anual',
            'yearly'   => 'Anual',
            'lifetime' => 'Vitalícia',
            'manual'   => 'Cortesia',
            'courtesy' => 'Cortesia',
        ];
        $plan_label = $plans[$plan] ?? ('' !== $plan ? ucfirst($plan) : '—');

        if ($active && 'trial' === $mode) {
            $label   = 'Teste';
            $tone    = 'trial';
            $message = null === $days ? 'Teste grátis ativo neste site.' : sprintf(1 === (int) $days ? 'Teste grátis ativo: falta %d dia.' : 'Teste grátis ativo: faltam %d dias.', (int) $days);
        } elseif ($active) {
            $label   = 'Ativa';
            $tone    = 'full';
            $message = 'Licença ativa neste site.';
        } else {
            $label = 'Sem licença';
            $tone  = 'free';
            if ('trial_expired' === ($state['status'] ?? '')) {
                $message = 'O teste grátis terminou. Ative uma licença para voltar a editar o CSS. O CSS já salvo continua aplicado no site.';
            } elseif ('' !== (string) ($state['message'] ?? '')) {
                $message = (string) $state['message'];
            } else {
                $message = 'Comece o teste grátis de 30 dias ou ative a sua licença.';
            }
        }

        $expires_label = '';
        if (!empty($state['expires_at'])) {
            $ts = strtotime((string) $state['expires_at']);
            if ($ts) {
                $expires_label = wp_date((string) get_option('date_format', 'd/m/Y'), $ts);
            }
        }

        return [
            'active'          => $active,
            'mode'            => $mode,
            'is_trial'        => $active && 'trial' === $mode,
            'label'           => $label,
            'tone'            => $tone,
            'message'         => $message,
            'plan'            => $plan,
            'plan_label'      => $plan_label,
            'expires_label'   => $expires_label,
            'days_left'       => $days,
            'key_masked'      => (string) ($state['key_masked'] ?? ''),
            'can_start_trial' => !$active && empty($state['trial_used']) && 'license' !== $mode,
            'domain'          => (string) wp_parse_url(home_url(), PHP_URL_HOST),
        ];
    }

    /* ------------------------------------------------------------------ ações */

    public static function handle_activate_request(): void {
        self::guard_admin_request('mlcssp_activate_license');
        $key    = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash((string) $_POST['license_key'])) : '';
        $result = self::client()->activate($key);
        if (is_wp_error($result)) {
            self::redirect_to_license('error', $result->get_error_message());
        }
        self::redirect_to_license('success', 'Licença ativada neste site.');
    }

    /** Começa o teste grátis ou confere a licença agora. */
    public static function handle_sync_request(): void {
        self::guard_admin_request('mlcssp_sync_license');
        $op     = isset($_POST['mlcssp_op']) ? sanitize_key(wp_unslash((string) $_POST['mlcssp_op'])) : 'check';
        $result = 'trial' === $op ? self::client()->start_trial() : self::client()->refresh();
        if (is_wp_error($result)) {
            self::redirect_to_license('error', $result->get_error_message());
        }
        self::redirect_to_license('success', 'trial' === $op ? 'Teste grátis de 30 dias ativo neste site.' : 'Licença conferida.');
    }

    public static function handle_remove_request(): void {
        self::guard_admin_request('mlcssp_remove_license');
        self::client()->deactivate();
        self::redirect_to_license('success', 'Licença removida deste site. A chave pode ser usada em outro site.');
    }

    private static function guard_admin_request(string $nonce_action): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Permissão insuficiente.'));
        }
        check_admin_referer($nonce_action);
    }

    public static function license_url(): string {
        return add_query_arg(['page' => 'ml-css-pro', 'tab' => 'license'], admin_url('admin.php'));
    }

    public static function redirect_to_license(string $type, string $message): void {
        $url = add_query_arg([
            'page'              => 'ml-css-pro',
            'tab'               => 'license',
            'mlcssp_toast'      => rawurlencode($message),
            'mlcssp_toast_type' => 'error' === $type ? 'error' : 'success',
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
