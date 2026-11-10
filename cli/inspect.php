<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/JsonInspector.php';

use GX\JsonTools\JsonInspector;

function usage(): string
{
    return implode("\n", [
        'Uso:',
        '  php cli/inspect.php antes.json depois.json [opções]',
        '',
        'Opções:',
        '  --array-mode=atomic  Arrays são substituídos como um único valor (padrão)',
        '  --array-mode=index   Arrays comparados por posição',
        '  --format=json        Saída JSON (padrão)',
        '  --format=text        Relatório legível no terminal',
        '  --format=csv         CSV para importar em planilhas',
        '  --path=/a/b          Mostrar apenas diferenças neste JSON Pointer',
        '  --ignore-path=/a/b   Ignorar mudanças nesta subárvore (repetível)',
        '  --summary            Resumo das operações em JSON',
        '  --fail-on-diff       Código de saída 1 quando houver diferenças',
        '  --help               Exibe esta ajuda',
        '',
        'Arquivos de entrada devem ter até 2 MB e são apenas lidos.',
        'Nenhuma alteração é aplicada aos documentos.',
        '',
    ]);
}

function run(array $args): int
{
    if ($args === [] || in_array('--help', $args, true)) {
        echo usage();
        return $args === [] ? 2 : 0;
    }
    if (count($args) < 2) {
        throw new InvalidArgumentException('Informe dois arquivos JSON. Use --help.');
    }
    [$beforePath, $afterPath] = array_slice($args, 0, 2);
    if (str_starts_with($beforePath, '--') || str_starts_with($afterPath, '--')) {
        throw new InvalidArgumentException('Os dois primeiros argumentos devem ser arquivos.');
    }

    $arrayMode = 'atomic';
    $format = 'json';
    $prefix = '';
    $ignoredPaths = [];
    $summary = false;
    $failOnDiff = false;

    foreach (array_slice($args, 2) as $flag) {
        if (str_starts_with($flag, '--array-mode=')) {
            $arrayMode = substr($flag, strlen('--array-mode='));
        } elseif (str_starts_with($flag, '--format=')) {
            $format = substr($flag, strlen('--format='));
        } elseif (str_starts_with($flag, '--path=')) {
            $prefix = substr($flag, strlen('--path='));
        } elseif (str_starts_with($flag, '--ignore-path=')) {
            $ignoredPaths[] = substr($flag, strlen('--ignore-path='));
        } elseif ($flag === '--summary') {
            $summary = true;
        } elseif ($flag === '--fail-on-diff') {
            $failOnDiff = true;
        } else {
            throw new InvalidArgumentException('Opção desconhecida: ' . $flag);
        }
    }

    if (!in_array($format, ['json', 'text', 'csv'], true)) {
        throw new InvalidArgumentException('Formato inválido.');
    }
    $before = JsonInspector::read($beforePath);
    $after = JsonInspector::read($afterPath);
    $changes = JsonInspector::compare($before, $after, $arrayMode);
    $changes = JsonInspector::filter($changes, $prefix);
    $changes = JsonInspector::exclude($changes, $ignoredPaths);

    if ($summary) {
        echo json_encode(
            JsonInspector::summary($changes),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";
    } elseif ($format === 'text') {
        echo JsonInspector::asText($changes);
    } elseif ($format === 'csv') {
        echo JsonInspector::asCsv($changes);
    } else {
        echo json_encode(
            $changes,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE |
            JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ) . "\n";
    }

    return $failOnDiff && $changes !== [] ? 1 : 0;
}

try {
    exit(run(array_slice($argv, 1)));
} catch (Throwable $error) {
    fwrite(STDERR, 'Erro: ' . $error->getMessage() . "\n");
    exit(2);
}
