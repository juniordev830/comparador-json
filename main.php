<?php
declare(strict_types=1);
function pointer(string $part): string { return str_replace(['~','/'],['~0','~1'],$part); }
function diff(mixed $a, mixed $b, string $path=''): array {
    $out=[];
    if ($a instanceof stdClass && $b instanceof stdClass) {
        $left=get_object_vars($a); $right=get_object_vars($b);
        $keys=array_unique(array_merge(array_keys($left),array_keys($right))); sort($keys);
        foreach ($keys as $key) {
            $p=$path.'/'.pointer((string)$key);
            if (!array_key_exists($key,$left)) $out[]=['op'=>'add','path'=>$p,'value'=>$right[$key]];
            elseif (!array_key_exists($key,$right)) $out[]=['op'=>'remove','path'=>$p,'old'=>$left[$key]];
            else $out=array_merge($out,diff($left[$key],$right[$key],$p));
        }
    } elseif (is_array($a) && is_array($b)) {
        // Arrays are replaced as one value; this report is not an RFC 6902 patch.
        if (json_encode($a,JSON_THROW_ON_ERROR)!==json_encode($b,JSON_THROW_ON_ERROR)) $out[]=['op'=>'replace','path'=>$path,'old'=>$a,'value'=>$b];
    } elseif (get_debug_type($a)!==get_debug_type($b) || $a!==$b) {
        $out[]=['op'=>'replace','path'=>$path,'old'=>$a,'value'=>$b];
    }
    return $out;
}
/** Counts operations without exposing old or new JSON values. */
function summarizeDiff(array $changes): array {
    $counts=['add'=>0, 'remove'=>0, 'replace'=>0];
    foreach ($changes as $change) {
        $operation=$change['op']??null;
        if (!is_string($operation) || !array_key_exists($operation, $counts))
            throw new InvalidArgumentException('Operação desconhecida');
        $counts[$operation]++;
    }
    return [
        'iguais'=>count($changes)===0,
        'total'=>count($changes),
        'adicionados'=>$counts['add'],
        'removidos'=>$counts['remove'],
        'alterados'=>$counts['replace'],
    ];
}

function readJson(string $path): mixed {
    if (!is_file($path) || filesize($path)>2_000_000) throw new RuntimeException('Arquivo ausente ou maior que 2 MB');
    $text=file_get_contents($path); if ($text===false) throw new RuntimeException('Falha de leitura');
    return json_decode($text,false,128,JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
}
function expect(bool $ok): void { if (!$ok) throw new RuntimeException('Teste falhou'); }
try {
    if (($argv[1]??'')==='--self-test') {
        expect(diff((object)['x'=>null],(object)[])===[['op'=>'remove','path'=>'/x','old'=>null]]);
        expect(diff((object)[],[])!==[]);
        expect(diff((object)['a/b'=>1],(object)['a/b'=>2])[0]['path']==='/a~1b');
        expect(diff([1,2],[2,1])[0]['op']==='replace');
        $changes=diff((object)['x'=>1,'y'=>2,'z'=>3],(object)['x'=>2,'z'=>3,'novo'=>4]);
        expect(summarizeDiff($changes)===[
            'iguais'=>false,'total'=>3,'adicionados'=>1,'removidos'=>1,'alterados'=>1
        ]);
        expect(summarizeDiff([])['iguais']===true);
        expect(summarizeDiff(diff([1,2],[1,3]))['alterados']===1);
        try { summarizeDiff([['op'=>'move']]); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        echo "8 cenários OK\n"; exit(0);
    }
    $args=array_slice($argv,1);
    $summary=false;
    $failOnChange=false;
    while (in_array($args[0]??null, ['--resumo','--falhar-se-diferente'], true)) {
        $flag=array_shift($args);
        if ($flag==='--resumo') {
            if ($summary) throw new InvalidArgumentException('Opção --resumo duplicada');
            $summary=true;
        } else {
            if ($failOnChange) throw new InvalidArgumentException('Opção --falhar-se-diferente duplicada');
            $failOnChange=true;
        }
    }
    if (count($args)!==2)
        throw new InvalidArgumentException('Uso: php main.php [--resumo] [--falhar-se-diferente] antes.json depois.json');
    $changes=diff(readJson($args[0]),readJson($args[1]));
    $report=$summary ? summarizeDiff($changes) : $changes;
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    if ($failOnChange && $changes!==[]) exit(1);
} catch (Throwable $e) { fwrite(STDERR,"Erro: {$e->getMessage()}\n"); exit(2); }
