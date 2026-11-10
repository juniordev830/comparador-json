<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/JsonInspector.php';

use GX\JsonTools\JsonInspector;

$checks = 0;

function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException('Falha: ' . $message);
    }
}

function throws(callable $callback, string $message): void
{
    global $checks;
    $checks++;
    try {
        $callback();
    } catch (InvalidArgumentException | RuntimeException $error) {
        return;
    }
    throw new RuntimeException('Exceção esperada: ' . $message);
}

function json(string $input): mixed
{
    return JsonInspector::decode($input);
}

// Scalar, null, object and array distinctions.
check(JsonInspector::compare(json('1'), json('1')) === [], 'same scalar');
check(JsonInspector::compare(json('1'), json('1.0'))[0]['op'] === 'replace', 'number type');
check(JsonInspector::compare(json('{}'), json('[]'))[0]['path'] === '', 'root type');
check(JsonInspector::compare(json('null'), json('false'))[0]['old'] === null, 'null value');
check(JsonInspector::compare(json('[]'), json('{}')) !== [], 'empty object versus list');
check(JsonInspector::compare(json('[1,2]'), json('[2,1]'))[0]['op'] === 'replace', 'arrays atomic');

// JSON objects are unordered, even when nested within atomic arrays.
$reordered = json('[{"profile":{"name":"Ana","role":"Dev"},"active":true}]');
$sameReordered = json('[{"active":true,"profile":{"role":"Dev","name":"Ana"}}]');
check(JsonInspector::compare($reordered, $sameReordered) === [],
    'atomic array with reordered object keys is unchanged');
check(JsonInspector::compare(json('[{"a":1}]'), json('[{"a":2}]'))[0]['path'] === '',
    'atomic array detects a real nested value change');
check(JsonInspector::compare(json('[1,2]'), json('[2,1]')) !== [],
    'array element order remains significant');
check(JsonInspector::compare(json('[{"n":1}]'), json('[{"n":1.0}]')) !== [],
    'nested numeric types remain significant');
check(JsonInspector::compare(json('[{"a":1}]'), json('[{"a":1,"b":2}]')) !== [],
    'nested object missing key remains significant');

// Deep changes and deterministic sorting.
$before = json('{"z":2,"nested":{"a":1,"b":null},"a/b":5}');
$after = json('{"z":3,"nested":{"a":4,"c":true},"a/b":7}');
$changes = JsonInspector::compare($before, $after);
check(count($changes) === 5, 'expected nested differences');
check($changes[0]['path'] === '/a~1b', 'JSON Pointer escapes slashes');
check($changes[1]['path'] === '/nested/a', 'paths sorted');
check($changes[2]['path'] === '/nested/b' && $changes[2]['op'] === 'remove', 'remove null property');
check($changes[3]['path'] === '/nested/c' && $changes[3]['op'] === 'add', 'add property');
check($changes[4]['path'] === '/z', 'last sorted path');
check(JsonInspector::summary($changes)['total'] === 5, 'summary total');
check(JsonInspector::summary($changes)['replace'] === 3, 'summary replace count');
check(JsonInspector::filter($changes, '/nested')[0]['path'] === '/nested/a', 'prefix filter');
check(count(JsonInspector::filter($changes, '/nested')) === 3, 'nested prefix count');
check(JsonInspector::filter($changes, '/nest') === [], 'prefix boundary');

// Ignore selected JSON Pointer subtrees without matching sibling prefixes.
$ignoredChanges = [
    ['op' => 'replace', 'path' => '/audit/id', 'old' => 1, 'value' => 2],
    ['op' => 'replace', 'path' => '/audit/logs/0', 'old' => 1, 'value' => 2],
    ['op' => 'replace', 'path' => '/auditor', 'old' => 1, 'value' => 2],
    ['op' => 'replace', 'path' => '/a~1b/value', 'old' => 1, 'value' => 2],
];
$excluded = JsonInspector::exclude($ignoredChanges, ['/audit', '/a~1b']);
check(array_column($excluded, 'path') === ['/auditor'], 'ignore with segment boundaries');
check(JsonInspector::exclude($ignoredChanges, []) === $ignoredChanges, 'no ignores');
check(JsonInspector::exclude($ignoredChanges, ['']) === [], 'empty pointer excludes root');
throws(static fn() => JsonInspector::exclude($ignoredChanges, ['/bad~2']), 'invalid ignore escape');
throws(static fn() => JsonInspector::exclude($ignoredChanges, ['bad']), 'invalid ignore path');
throws(static fn() => JsonInspector::exclude($ignoredChanges, array_fill(0, 101, '/a')), 'ignore limit');

// JSON Pointer navigation.
$object = json('{"a/b":{"~name":[0,{"x":null}]}}');
check(JsonInspector::at($object, '/a~1b/~0name/1/x') === null, 'pointer nested null');
check(JsonInspector::at($object, '') instanceof stdClass, 'pointer root');
check(JsonInspector::at(json('[10,20]'), '/1') === 20, 'pointer array');
check(JsonInspector::segments('/')[0] === '', 'empty property key');
check(JsonInspector::segments('/a~1b')[0] === 'a/b', 'unescape pointer');
throws(static fn() => JsonInspector::at($object, '/unknown'), 'missing key');
throws(static fn() => JsonInspector::at(json('[10]'), '/01'), 'leading-zero array index');
throws(static fn() => JsonInspector::at(json('[10]'), '/-'), 'array append not a lookup');
throws(static fn() => JsonInspector::segments('/bad~2key'), 'invalid tilde escape');
throws(static fn() => JsonInspector::segments('bad'), 'missing slash');

// Array-index mode is diagnostic, not a JSON Patch.
$a = json('[{"id":1,"name":"A"},{"id":2,"name":"B"}]');
$b = json('[{"id":1,"name":"C"},{"id":2,"name":"B"},{"id":3}]');
$indexed = JsonInspector::compare($a, $b, 'index');
check(count($indexed) === 2, 'index mode two changes');
check($indexed[0]['path'] === '/0/name', 'index mode nested property');
check($indexed[1]['path'] === '/2', 'index mode appended value');
check(JsonInspector::compare(json('[1,2]'), json('[1]'), 'index')[0]['op'] === 'remove', 'array removal');
throws(static fn() => JsonInspector::compare(1, 2, 'unknown'), 'bad array mode');

// Text and CSV output including malicious spreadsheet-looking keys.
$text = JsonInspector::asText($changes);
check(str_contains($text, 'REPLACE /z'), 'human report');
check(str_contains(JsonInspector::asText([]), 'Nenhuma diferença'), 'no changes');
$csv = JsonInspector::asCsv($changes);
check(str_contains($csv, 'operacao,caminho,antes,depois'), 'csv headings');
check(substr_count($csv, "\n") === count($changes) + 1, 'csv rows');
$malicious = [
    ['op' => 'replace', 'path' => '=SUM(A1:A2)', 'old' => '@cmd', 'value' => '+SUM(1)'],
];
$csvMalicious = JsonInspector::asCsv($malicious);
check(str_contains($csvMalicious, "'=SUM(A1:A2)"), 'CSV formula neutralized');
check(str_contains($csvMalicious, '@cmd'), 'old value kept as JSON string');

// File-size guard and invalid JSON.
$tmp = tempnam(sys_get_temp_dir(), 'gx-json-inspector-');
if ($tmp === false) {
    throw new RuntimeException('Falha ao criar arquivo temporário.');
}
try {
    file_put_contents($tmp, '{"ok":true}');
    check(JsonInspector::read($tmp)->ok === true, 'read valid file');
    file_put_contents($tmp, '{invalid');
    throws(static fn() => JsonInspector::read($tmp), 'bad JSON');
    file_put_contents($tmp, str_repeat('x', JsonInspector::MAX_BYTES + 1));
    throws(static fn() => JsonInspector::read($tmp), 'oversized JSON');
} finally {
    unlink($tmp);
}
throws(static fn() => JsonInspector::read('/unlikely/missing/file.json'), 'file missing');
throws(static fn() => JsonInspector::decode('{invalid'), 'JSON decoder error');
throws(static fn() => JsonInspector::decode(str_repeat('x', JsonInspector::MAX_BYTES + 1)), 'JSON size');

// Enforce upper bound on changes without allocating enormous input documents.
$left = (object) [];
$right = (object) [];
for ($i = 0; $i <= JsonInspector::MAX_CHANGES; $i++) {
    $right->{'k' . $i} = $i;
}
throws(static fn() => JsonInspector::compare($left, $right), 'difference limit');

echo "JsonInspector: {$checks} assertions passed.\n";
