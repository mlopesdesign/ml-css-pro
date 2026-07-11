<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Local CSS validation/lint engine.
 *
 * Inspired conceptually by client-side CSS linting (warnings, errors, rule
 * counting) but implemented as a lightweight, dependency-free PHP analyzer.
 * It reports issues to the admin UI without blocking saves, except for
 * critically unsafe payloads which the sanitizer already neutralizes.
 */
final class MLCSSP_Linter {

    /**
     * Patterns considered critically unsafe. These mirror the sanitizer so the
     * UI can flag them clearly. Saving is never hard-blocked; the sanitizer
     * neutralizes the payload, and the linter surfaces the reason.
     */
    private const UNSAFE_PATTERNS = [
        '/<\s*\/?\s*(script|iframe|object|embed|link|meta|style)[^>]*>/i' => 'Tag HTML perigosa detectada (script/iframe/style/etc).',
        '/<\?(php)?|\?>/i' => 'Tag PHP detectada no CSS.',
        '/expression\s*\(/i' => 'Uso de expression() nao e permitido.',
        '/javascript\s*:/i' => 'Uso de javascript: nao e permitido.',
        '/data\s*:\s*text\/html/i' => 'Uso de data:text/html nao e permitido.',
    ];

    /**
     * Analyze a CSS string and return a structured report.
     *
     * @return array{ok:bool,errors:array<int,string>,warnings:array<int,string>,stats:array<string,int>}
     */
    public static function analyze(string $css): array {
        $css = str_replace(["\r\n", "\r"], "\n", $css);
        $errors = [];
        $warnings = [];

        // Critically unsafe payloads -> errors (neutralized on save).
        foreach (self::UNSAFE_PATTERNS as $pattern => $message) {
            if (preg_match($pattern, $css)) {
                $errors[] = $message;
            }
        }

        // Strip comments before structural analysis.
        $stripped = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;

        // Brace balance.
        $open = substr_count($stripped, '{');
        $close = substr_count($stripped, '}');
        if ($open !== $close) {
            $errors[] = sprintf('Chaves desbalanceadas: %d "{" e %d "}".', $open, $close);
        }

        // Unterminated comment.
        if (substr_count($css, '/*') !== substr_count($css, '*/')) {
            $warnings[] = 'Comentario CSS aberto sem fechamento ("/*" sem "*/").';
        }

        // Empty rules.
        if (preg_match_all('/[^{}]+\{\s*\}/', $stripped)) {
            $warnings[] = 'Existe pelo menos uma regra vazia (seletor sem propriedades).';
        }

        // Declarations missing a value.
        if (preg_match('/[a-z-]+\s*:\s*;/i', $stripped)) {
            $warnings[] = 'Existe propriedade sem valor (ex.: "color:;").';
        }

        // Stats.
        $rule_count = max(0, (int) $open);
        $declaration_count = substr_count($stripped, ';');
        $important_count = preg_match_all('/!important/i', $stripped) ?: 0;
        $lines = $css === '' ? 0 : (substr_count($css, "\n") + 1);

        $stats = [
            'rules' => $rule_count,
            'declarations' => $declaration_count,
            'important' => (int) $important_count,
            'lines' => $lines,
            'bytes' => strlen($css),
        ];

        if ($important_count > 50) {
            $warnings[] = 'Uso intenso de !important (' . (int) $important_count . '). Considere reduzir para facilitar manutencao.';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => $stats,
        ];
    }

    /**
     * Convenience: true when CSS has no critical (unsafe/structural) errors.
     */
    public static function is_safe(string $css): bool {
        $report = self::analyze($css);
        return $report['ok'];
    }
}
