# Guia técnico — Comparador JSON

## Estado atual

Ferramenta PHP 8.3 de comparação profunda e inspeção de JSON, sem executar o conteúdo recebido.

## Referências de código

`main.php`, `src/JsonInspector.php`, `cli/inspect.php`, `tests/JsonInspectorTest.php` e `tests/CliTest.php`.

## Validar mudanças

`php -l main.php`; `php main.php --self-test`; `php tests/JsonInspectorTest.php`; `php tests/CliTest.php`.

Usar somente dados fictícios e registrar comandos executados com resultados no PR. A mera presença de um teste no repositório não comprova que ele passou.

## Cenários de regressão

Comparar objetos vazios, arrays ordenados, `null`, escapes em JSON Pointer, arquivos grandes, profundidade excessiva e neutralização de fórmulas no CSV.

## Próximo incremento

O resultado não é JSON Patch pronto para aplicação. Expandir casos de compatibilidade sem aumentar a superfície de leitura de arquivos externos.

## Critérios de revisão

- [ ] Código e documentação refletem o mesmo comportamento.
- [ ] Casos de fronteira estão protegidos por teste ou roteiro de validação.
- [ ] Arquivos e saídas geradas ficam fora do versionamento, quando contêm dados privados.
- [ ] Mudanças passam por branch e PR, sem integração automática.
