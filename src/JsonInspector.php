<?php
declare(strict_types=1);

namespace GX\JsonTools;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;

final class JsonInspector
{
    public const MAX_BYTES = 2_000_000;
    public const MAX_DEPTH = 64;
    public const MAX_CHANGES = 10_000;

    /**
     * Decode JSON without losing the difference between [] and {}.
     * Reject unknown/oversized local files before parsing.
     */
    public static function read(string $filename): mixed
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new RuntimeException('Arquivo JSON não encontrado ou inacessível.');
        }
        clearstatcache(true, $filename);
        $size = filesize($filename);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new RuntimeException('Documento excede 2 MB.');
        }
        $data = file_get_contents($filename);
        if ($data === false) {
            throw new RuntimeException('Falha ao ler arquivo.');
        }
        return self::decode($data);
    }

    public static function decode(string $data): mixed
    {
        if (strlen($data) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Documento excede 2 MB.');
        }
        try {
            return json_decode(
                $data,
                false,
                self::MAX_DEPTH,
                JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
            );
        } catch (JsonException $error) {
            throw new InvalidArgumentException(
                'JSON inválido: ' . $error->getMessage(), 0, $error
            );
        }
    }

    public static function pointer(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }

    public static function unescapePointer(string $segment): string
    {
        // Do not accept malformed JSON Pointer escapes.
        if (preg_match('/~(?![01])/', $segment) === 1) {
            throw new InvalidArgumentException('Escape JSON Pointer inválido.');
        }
        return str_replace(['~1', '~0'], ['/', '~'], $segment);
    }

    /** @return list<string> */
    public static function segments(string $pointer): array
    {
        if ($pointer === '') {
            return [];
        }
        if (!str_starts_with($pointer, '/')) {
            throw new InvalidArgumentException('JSON Pointer deve começar com /');
        }
        return array_map(
            [self::class, 'unescapePointer'],
            explode('/', substr($pointer, 1))
        );
    }

    public static function at(mixed $document, string $pointer): mixed
    {
        foreach (self::segments($pointer) as $segment) {
            if ($document instanceof stdClass) {
                if (!property_exists($document, $segment)) {
                    throw new InvalidArgumentException('Caminho não encontrado: ' . $pointer);
                }
                $document = $document->{$segment};
            } elseif (is_array($document)) {
                if ($segment === '-' || !preg_match('/^(0|[1-9][0-9]*)$/D', $segment)) {
                    throw new InvalidArgumentException('Índice de array inválido.');
                }
                $index = (int) $segment;
                if (!array_key_exists($index, $document)) {
                    throw new InvalidArgumentException('Índice ausente: ' . $pointer);
                }
                $document = $document[$index];
            } else {
                throw new InvalidArgumentException('O caminho atravessa um valor escalar.');
            }
        }
        return $document;
    }

    /**
     * Diagnostic diff, intentionally NOT an RFC 6902 JSON Patch.
     *
     * @param 'atomic'|'index' $arrayMode
     * @return list<array<string, mixed>>
     */
    public static function compare(
        mixed $before,
        mixed $after,
        string $arrayMode = 'atomic'
    ): array {
        if (!in_array($arrayMode, ['atomic', 'index'], true)) {
            throw new InvalidArgumentException('Modo de comparação inválido.');
        }
        $changes = [];
        self::walk($before, $after, '', $arrayMode, 0, $changes);
        return $changes;
    }

    /** @param list<array<string, mixed>> $changes */
    private static function emit(
        array &$changes, string $operation, string $path,
        mixed $oldValue, mixed $newValue
    ): void {
        if (count($changes) >= self::MAX_CHANGES) {
            throw new RuntimeException('Limite de 10.000 diferenças excedido.');
        }
        $entry = ['op' => $operation, 'path' => $path];
        if ($operation !== 'add') {
            $entry['old'] = $oldValue;
        }
        if ($operation !== 'remove') {
            $entry['value'] = $newValue;
        }
        $changes[] = $entry;
    }

    /** @param list<array<string, mixed>> $changes */
    private static function walk(
        mixed $a, mixed $b, string $path,
        string $arrayMode, int $depth, array &$changes
    ): void {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('Profundidade máxima excedida.');
        }

        if ($a instanceof stdClass && $b instanceof stdClass) {
            $left = get_object_vars($a);
            $right = get_object_vars($b);
            $keys = array_unique(array_merge(array_keys($left), array_keys($right)));
            sort($keys, SORT_STRING);
            foreach ($keys as $key) {
                $segment = $path . '/' . self::pointer((string) $key);
                if (!array_key_exists($key, $left)) {
                    self::emit($changes, 'add', $segment, null, $right[$key]);
                } elseif (!array_key_exists($key, $right)) {
                    self::emit($changes, 'remove', $segment, $left[$key], null);
                } else {
                    self::walk($left[$key], $right[$key], $segment,
                        $arrayMode, $depth + 1, $changes);
                }
            }
            return;
        }

        if (is_array($a) && is_array($b) && $arrayMode === 'index') {
            $limit = max(count($a), count($b));
            for ($i = 0; $i < $limit; $i++) {
                $segment = $path . '/' . $i;
                if (!array_key_exists($i, $a)) {
                    self::emit($changes, 'add', $segment, null, $b[$i]);
                } elseif (!array_key_exists($i, $b)) {
                    self::emit($changes, 'remove', $segment, $a[$i], null);
                } else {
                    self::walk($a[$i], $b[$i], $segment,
                        $arrayMode, $depth + 1, $changes);
                }
            }
            return;
        }

        if (self::equal($a, $b)) {
            return;
        }
        self::emit($changes, 'replace', $path, $a, $b);
    }

    /**
     * Compare JSON values structurally: object key order is irrelevant, while
     * array positions and scalar types remain significant.
     */
    private static function equal(mixed $a, mixed $b, int $depth = 0): bool
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('Profundidade máxima excedida.');
        }
        if (get_debug_type($a) !== get_debug_type($b)) {
            return false;
        }
        if ($a instanceof stdClass) {
            $left = get_object_vars($a);
            $right = get_object_vars($b);
            if (count($left) !== count($right)) {
                return false;
            }
            foreach ($left as $key => $value) {
                if (!array_key_exists($key, $right) ||
                    !self::equal($value, $right[$key], $depth + 1)) {
                    return false;
                }
            }
            return true;
        }
        if (is_array($a)) {
            if (count($a) !== count($b)) {
                return false;
            }
            foreach ($a as $index => $value) {
                if (!array_key_exists($index, $b) ||
                    !self::equal($value, $b[$index], $depth + 1)) {
                    return false;
                }
            }
            return true;
        }
        return $a === $b;
    }

    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION |
            JSON_THROW_ON_ERROR);
    }

    /**
     * Prefix filtering respects JSON Pointer segment boundaries:
     * /customer/1 matches /customer, but /customers does not.
     * @param list<array<string,mixed>> $changes
     * @return list<array<string,mixed>>
     */
    public static function filter(array $changes, string $prefix): array
    {
        self::segments($prefix);
        if ($prefix === '') {
            return $changes;
        }
        return array_values(array_filter(
            $changes,
            static fn (array $item): bool =>
                $item['path'] === $prefix ||
                str_starts_with((string) $item['path'], $prefix . '/')
        ));
    }

    /** @param list<array<string,mixed>> $changes */
    public static function summary(array $changes): array
    {
        $summary = [
            'total' => count($changes),
            'add' => 0,
            'remove' => 0,
            'replace' => 0,
            'paths' => [],
        ];
        foreach ($changes as $entry) {
            $op = $entry['op'];
            if (!isset($summary[$op])) {
                throw new InvalidArgumentException('Operação desconhecida.');
            }
            $summary[$op]++;
            $summary['paths'][] = $entry['path'];
        }
        return $summary;
    }

    /** @param list<array<string,mixed>> $changes */
    public static function asText(array $changes): string
    {
        if ($changes === []) {
            return "Nenhuma diferença encontrada.\n";
        }
        $lines = [];
        foreach ($changes as $entry) {
            $path = $entry['path'] === '' ? '<root>' : $entry['path'];
            $lines[] = strtoupper($entry['op']) . ' ' . $path;
            if (array_key_exists('old', $entry)) {
                $lines[] = '  antes: ' . self::encode($entry['old']);
            }
            if (array_key_exists('value', $entry)) {
                $lines[] = '  depois: ' . self::encode($entry['value']);
            }
        }
        return implode("\n", $lines) . "\n";
    }

    /** @param list<array<string,mixed>> $changes */
    public static function asCsv(array $changes): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Falha ao preparar CSV.');
        }
        try {
            fputcsv($stream, ['operacao', 'caminho', 'antes', 'depois'], ',', '"', '');
            foreach ($changes as $entry) {
                $values = [
                    (string) $entry['op'],
                    (string) $entry['path'],
                    array_key_exists('old', $entry) ? self::encode($entry['old']) : '',
                    array_key_exists('value', $entry) ? self::encode($entry['value']) : '',
                ];
                // Neutralize spreadsheet formulas even when preceded by whitespace.
                $safe = array_map(
                    static fn (string $cell): string =>
                        preg_match('/^\s*[=+\-@]/', $cell) === 1 ? "'" . $cell : $cell,
                    $values
                );
                fputcsv($stream, $safe, ',', '"', '');
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            if ($csv === false) {
                throw new RuntimeException('Falha ao ler CSV.');
            }
            return $csv;
        } finally {
            fclose($stream);
        }
    }
}
