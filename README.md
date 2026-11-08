# Comparador JSON

Comparador local de documentos JSON em **PHP 8.3**, com inspeção de mudanças por campo, suporte a JSON Pointer e relatórios em JSON, texto e CSV. Não utiliza bibliotecas externas nem serviços de nuvem.

## Comparar documentos

O comando original continua funcionando:

```bash
php main.php examples/antes.json examples/depois.json
```

A nova CLI oferece relatórios e filtros:

```bash
php cli/inspect.php examples/antes.json examples/depois.json
php cli/inspect.php examples/antes.json examples/depois.json --format=text
php cli/inspect.php examples/antes.json examples/depois.json --format=csv > relatorio.csv
php cli/inspect.php examples/antes.json examples/depois.json --array-mode=index
php cli/inspect.php examples/antes.json examples/depois.json --path=/cliente --summary
php cli/inspect.php examples/antes.json examples/depois.json --fail-on-diff
```

O último exemplo retorna código **1** quando identifica diferenças, podendo ser usado em pipelines. Sem diferenças, retorna **0**; erro de parâmetro ou leitura retorna **2**.

## Implementação

- Comparação profunda de objetos com caminhos ordenados e escape correto de JSON Pointer.
- Preservação das diferenças entre objeto vazio, array vazio, `null`, números e strings.
- Dois modos de comparação de arrays: `atomic` (trata arrays inteiros como valores) e `index` (compara por posição).
- Consulta de valores em qualquer JSON Pointer existente, inclusive escapes de `/` e `~`.
- Filtro por subárvore e resumo por operações adicionadas, removidas ou substituídas.
- Relatório em texto para revisão humana e CSV com proteção contra fórmulas de planilha.
- Limites de segurança: 2 MB por arquivo, profundidade de 64 níveis e até 10.000 diferenças.
- Os documentos são apenas lidos; nenhuma alteração é aplicada a eles.

**Importante:** o relatório é um diagnóstico e **não** deve ser tratado como um JSON Patch RFC 6902 pronto para aplicar. O modo `index` não implementa identificação inteligente de elementos de lista e pode considerar uma inserção no começo da lista como várias substituições.

## Testes

```bash
php -l main.php
php -l src/JsonInspector.php
php -l cli/inspect.php
php main.php --self-test
php tests/JsonInspectorTest.php
php tests/CliTest.php
```

Os mesmos testes são executados no GitHub Actions com PHP 8.3. Dados de exemplo são fictícios; não envie documentos com segredos para o repositório.

## Biblioteca

```php
<?php
require 'src/JsonInspector.php';

use GX\JsonTools\JsonInspector;

$antes = JsonInspector::decode('{"a":1,"b":[1,2]}');
$depois = JsonInspector::decode('{"a":2,"b":[1,3]}');
$changes = JsonInspector::compare($antes, $depois, 'index');
echo JsonInspector::asText($changes);
```

## Licença

Ainda não definida.
