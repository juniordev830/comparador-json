# Comparador JSON

Comparação de documentos JSON com diferenças por campo.

## Implementado na primeira versão

**PHP — núcleo executável de domínio.** Compara JSON por campo, preservando a diferença entre objeto, lista e null. Produz caminhos JSON Pointer com escape de barras e tils.

## Como executar

Instale PHP 8.3 CLI. Não há dependências Composer. Os comandos abaixo partem da raiz do repositório.

```sh
php main.php examples/antes.json examples/depois.json
```

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
- [ ] Destacar campos adicionados e removidos — ampliar o núcleo atual até o fluxo completo.
- [ ] Exportar relatório de diferenças — ampliar o núcleo atual até o fluxo completo.

Licença: ainda não definida.
