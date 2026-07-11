<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLCSSP_Helpers {
    public static function get_current_language(): string {
        $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
        $locale = strtolower($locale);
        if (strpos($locale, 'pt') === 0) {
            return 'pt';
        }
        if (strpos($locale, 'es') === 0) {
            return 'es';
        }
        return 'en';
    }

    public static function t(string $pt, string $en = '', string $es = ''): string {
        $lang = self::get_current_language();
        if ($lang === 'pt') {
            return $pt;
        }
        if ($lang === 'es') {
            return $es !== '' ? $es : ($en !== '' ? $en : $pt);
        }
        return $en !== '' ? $en : $pt;
    }

    public static function logo_url(): string {
        return esc_url(MLCSSP_URL . 'assets/images/logo-wordpress.png');
    }

    public static function default_project_prompt(): string {
        return trim((string) <<<PROMPT
PROJETO: ML CSS PRO

BASE OBRIGATORIA
Usar como base obrigatoria o template mestre oficial dos plugins ML.

OBJETIVO
Controlar CSS global e CSS especifico para Nicepage sem quebrar o template, mantendo:
- layout administrativo padrao ML
- logo oficial
- paleta oficial
- abas de navegacao
- cards e toasts
- settings persistentes
- licenciamento padrao funcional
- motor funcional separado da camada visual

REGRAS
- manter slug, pasta raiz e arquivo principal desta instalacao
- preservar licenciamento free/trial/full/lifetime
- manter product_id, endpoint e textos centralizados
- nao editar arquivos do tema para aplicar CSS
- injetar CSS de forma controlada pelo plugin
- validar estrutura do ZIP antes da entrega
PROMPT);
    }

    public static function format_bytes(int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }
}
