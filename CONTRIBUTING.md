# Contribuindo para o Comparador JSON

Este projeto utiliza PHP 8.3 e não depende de pacotes externos. Mudanças pequenas e verificáveis são preferíveis a refatorações amplas.

## Preparação

1. Instale PHP 8.3 e confirme com `php -v`.
2. Faça um fork ou crie uma branch de trabalho a partir de `main`.
3. Execute os testes existentes antes de alterar o código:

```bash
php -l main.php
php -l src/JsonInspector.php
php -l cli/inspect.php
php main.php --self-test
php tests/JsonInspectorTest.php
php tests/CliTest.php
```

## Ao modificar o comparador

- Preserve a distinção entre objetos, arrays, `null` e valores escalares.
- Cubra caminhos JSON Pointer que contenham `/` e `~`.
- Verifique os modos `atomic` e `index` quando a alteração afetar arrays.
- Mantenha os códigos de saída da CLI: `0` sem diferenças, `1` com diferenças quando `--fail-on-diff` estiver ativo, `2` para erros de entrada ou execução.
- Não trate o relatório como JSON Patch aplicável.
- Evite dados reais ou credenciais nos exemplos e testes.

## Pull requests

Descreva o problema, o comportamento esperado e o que mudou. Inclua o comando usado para testar e seu resultado. Se a mudança alterar a CLI, atualize os exemplos do README.

Antes de abrir o PR, confira o diff para remover arquivos temporários e alterações não relacionadas.
