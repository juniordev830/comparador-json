<?php
declare(strict_types=1);

$directory = sys_get_temp_dir() . '/gx-json-cli-' . bin2hex(random_bytes(6));
if (!mkdir($directory, 0700, true)) {
    throw new RuntimeException('Pasta temporária indisponível');
}

function runCli(array $arguments): array
{
    global $directory;
    $command = array_merge(
        [PHP_BINARY, dirname(__DIR__) . '/cli/inspect.php'],
        $arguments
    );
    $pipes = [];
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $directory
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Falha ao executar CLI');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}

function cliAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Teste CLI: ' . $message);
    }
}

try {
    $old = $directory . '/antes.json';
    $new = $directory . '/depois.json';
    file_put_contents($old, '{"team":{"name":"Ana","skills":["PHP"]}}');
    file_put_contents($new, '{"team":{"name":"Bruno","skills":["PHP","SQL"]}}');

    [$status, $output] = runCli([$old, $new]);
    cliAssert($status === 0, 'default JSON output should succeed');
    $differences = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    cliAssert(count($differences) === 2, 'expected two changes');
    cliAssert($differences[0]['path'] === '/team/name', 'name path');
    cliAssert($differences[1]['path'] === '/team/skills', 'atomic list path');

    [$status, $output] = runCli([$old, $new, '--array-mode=index']);
    $differences = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    cliAssert($differences[1]['path'] === '/team/skills/1', 'array index path');

    [$status, $output] = runCli([$old, $new, '--path=/team/name', '--summary']);
    $stats = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    cliAssert($stats['total'] === 1, 'path filtering');

    [$status, $output] = runCli([$old, $new, '--format=text']);
    cliAssert(str_contains($output, 'REPLACE /team/name'), 'human readable output');

    [$status, $output] = runCli([$old, $new, '--format=csv']);
    cliAssert(str_contains($output, 'operacao,caminho'), 'csv output');

    [$status] = runCli([$old, $new, '--fail-on-diff']);
    cliAssert($status === 1, 'fail-on-diff signals changes');

    [$status] = runCli([$old, $old, '--fail-on-diff']);
    cliAssert($status === 0, 'identical input should pass');

    [$status] = runCli([$old, $new, '--format=yaml']);
    cliAssert($status === 2, 'unknown format should fail');

    [$status] = runCli([$old, $new, '--path=invalid']);
    cliAssert($status === 2, 'invalid path should fail');

    [$status] = runCli([$old, $new, '--nonexistent']);
    cliAssert($status === 2, 'unknown option should fail');

    [$status] = runCli(['--help']);
    cliAssert($status === 0, 'help should pass');
    echo "JsonInspector CLI: 12 scenarios passed.\n";
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}
