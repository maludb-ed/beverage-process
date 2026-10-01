<?php
declare(strict_types=1);

// A deliberately small Markdown subset for AMA answers: paragraphs, bullet and
// numbered lists, pipe tables, **bold**, `code`. Everything is escaped first;
// no raw HTML, no links, no images.

if (!function_exists('ama_markdown')) {
    function ama_inline(string $text): string
    {
        $html = e($text);
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html) ?? $html;
        $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
        return $html;
    }

    function ama_markdown(string $text): string
    {
        $lines = preg_split('/\R/', trim($text)) ?: [];
        $out = '';
        $para = [];
        $list = null;   // 'ul' | 'ol'
        $table = [];
        $flushPara = static function () use (&$para, &$out): void {
            if ($para !== []) {
                $out .= '<p class="mb-2">' . implode('<br>', array_map('ama_inline', $para)) . '</p>';
                $para = [];
            }
        };
        $flushList = static function () use (&$list, &$out): void {
            if ($list !== null) {
                $out .= "</{$list}>";
                $list = null;
            }
        };
        $flushTable = static function () use (&$table, &$out): void {
            if ($table === []) {
                return;
            }
            $rows = array_values(array_filter($table, static fn ($r) => !preg_match('/^\|?\s*:?-{2,}/', $r)));
            $out .= '<div class="table-responsive mb-2"><table class="table table-sm table-bordered mb-0">';
            foreach ($rows as $i => $row) {
                $cells = array_map('trim', explode('|', trim(trim($row), '|')));
                $tag = $i === 0 ? 'th' : 'td';
                $out .= '<tr>' . implode('', array_map(static fn ($c) => "<{$tag}>" . ama_inline($c) . "</{$tag}>", $cells)) . '</tr>';
            }
            $out .= '</table></div>';
            $table = [];
        };
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $flushPara(); $flushList(); $flushTable();
                continue;
            }
            if (str_starts_with($trim, '|')) {
                $flushPara(); $flushList();
                $table[] = $trim;
                continue;
            }
            $flushTable();
            if (preg_match('/^[-*•]\s+(.*)$/u', $trim, $m) || preg_match('/^\d+[.)]\s+(.*)$/', $trim, $n)) {
                $flushPara();
                $kind = isset($m[1]) ? 'ul' : 'ol';
                if ($list !== $kind) {
                    $flushList();
                    $out .= "<{$kind} class=\"mb-2 ps-3\">";
                    $list = $kind;
                }
                $out .= '<li>' . ama_inline($m[1] ?? $n[1]) . '</li>';
                unset($m, $n);
                continue;
            }
            $flushList();
            $para[] = preg_replace('/^#{1,6}\s+/', '', $trim) ?? $trim;
        }
        $flushPara(); $flushList(); $flushTable();
        return $out;
    }

    /** Split the trailing "Source: ..." line the AMA prompt asks for into its own note. */
    function ama_split_source(string $answer, array $sources): array
    {
        if (preg_match('/\n?\s*\**Source:?\**\s*(.+?)\s*$/i', $answer, $m, PREG_OFFSET_CAPTURE) && $m[0][1] > 0) {
            return [rtrim(substr($answer, 0, $m[0][1])), 'Source: ' . trim($m[1][0], " *")];
        }
        if ($sources !== []) {
            return [$answer, 'Source: ' . implode(' and ', $sources) . ' memory'];
        }
        return [$answer, null];
    }
}
