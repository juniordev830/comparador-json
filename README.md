# Comparador JSON

Comparação de documentos JSON com diferenças por campo.

## Implementado na primeira versão

**PHP — núcleo executável de domínio.** Compara JSON por campo, preservando a diferença entre objeto, lista e null. Produz caminhos JSON Pointer com escape de barras e tils. Agora oferece um resumo de operações, sem expor valores, e um código de saída apropriado para pipelines de CI.

## Como executar

Instale PHP 8.3 CLI. Não há dependências Composer. Os comandos abaixo partem da raiz do repositório.

```sh
php main.php examples/antes.json examples/depois.json
php main.php --resumo examples/antes.json examples/depois.json
php main.php --resumo --falhar-se-diferente examples/antes.json examples/depois.json
```

## Relatório resumido e uso em CI

`--resumo` imprime somente a quantidade de campos **adicionados**, **removidos**, **alterados** e o total, além do booleano `iguais`. Nenhum valor antigo ou novo é exibido neste modo. O relatório completo continua sendo o padrão.

Com `--falhar-se-diferente`, o comando retorna **0** quando os documentos são iguais e **1** quando existem diferenças; entradas inválidas ou arquivos ausentes retornam **2**. As duas opções podem ser combinadas, antes dos caminhos dos arquivos, na ordem desejada. A saída continua em JSON mesmo quando o processo retorna 1.

## Testes

```sh
php main.php --self-test
```

Os testes cobrem invariantes e entradas inválidas. A preparação local não dispõe deste runtime; o workflow em `.github/workflows/ci.yml` executa a validação no GitHub Actions. O resultado de cada execução está na aba Actions, associado ao commit.

## Organização e limites

Código e exemplos: `main.php e examples/*.json`. Os dados de exemplo são fictícios. As funções de domínio são separadas da leitura de argumentos no mesmo arquivo para manter a primeira versão pequena e revisável.

Arrays são comparados como um valor completo. O relatório não é um patch RFC 6902. Inteiros maiores que a precisão nativa são lidos como strings; números 1 e 1.0 são distintos. JSON Schema e Symfony Console são etapas futuras.

A proposta completa de evolução está em `ARQUITETURA.md`. As tecnologias propostas nessa arquitetura não devem ser interpretadas como dependências já implementadas.

## Próximas entregas

- [ ] Validar dois documentos JSON — ampliar o núcleo atual até o fluxo completo.
- [x] Contabilizar e distinguir inclusões, remoções e alterações no resumo.
- [ ] Gerar relatório HTML amigável.
- [x] Exportar relatório JSON completo ou resumido no terminal.
- [ ] Adicionar exportação em HTML.

Licença: ainda não definida.
