<?php
namespace Eduardokum\LaravelBoleto\Tests\Retorno;

use Eduardokum\LaravelBoleto\Cnab\Retorno\Factory;
use Eduardokum\LaravelBoleto\Tests\TestCase;

/**
 * DD-803 (CNPJ alfanumérico): o documento do pagador lido do retorno CNAB 240. O banco escreve o número de inscrição
 * num campo de 15 posições, com zero à esquerda. Antes, a pessoa ficava com os últimos 14 dígitos, sem letra; depois
 * da primeira mudança da demanda, o campo de 15 posições passou a ser recusado, até com CNPJ numérico (A4 do QA ciclo
 * 1). Agora saem só os zeros que sobram, e o documento fica inteiro.
 *
 * Usa o retorno Santander do próprio fork (`files/cnab240/santander.ret`), trocando só o tipo e o número de inscrição
 * do pagador no segmento T (posições 128 a 143 no layout Santander). O par: CNPJ numérico e CPF.
 */
class RetornoCnab240DocumentoTest extends TestCase
{
    private $arquivo;

    public function tearDown()
    {
        if ($this->arquivo && is_file($this->arquivo)) {
            unlink($this->arquivo);
        }
        parent::tearDown();
    }

    private function retornoCom($tipo, $campo)
    {
        $linhas = file(__DIR__ . '/files/cnab240/santander.ret');
        foreach ($linhas as $i => $linha) {
            if (substr($linha, 13, 1) === 'T') {
                $linhas[$i] = substr($linha, 0, 127) . $tipo . $campo . substr($linha, 143);
            }
        }
        // O tempnam já cria o arquivo: ele é renomeado para .ret, e o tearDown apaga o único que sobra.
        $temporario = tempnam(sys_get_temp_dir(), 'dd803');
        $this->arquivo = $temporario . '.ret';
        rename($temporario, $this->arquivo);
        file_put_contents($this->arquivo, implode('', $linhas));

        $retorno = Factory::make($this->arquivo);
        $retorno->processar();

        return $retorno->getDetalhe(1)->getPagador();
    }

    public function documentosDoPagador()
    {
        return [
            'CNPJ alfanumérico' => ['2', '012ABC34501DE35', '12.ABC.345/01DE-35', 'CNPJ'],
            'CNPJ numérico'     => ['2', '011222333000181', '11.222.333/0001-81', 'CNPJ'],
            'CPF'               => ['1', '000052998224725', '529.982.247-25', 'CPF'],
        ];
    }

    /**
     * @dataProvider documentosDoPagador
     */
    public function testOPagadorDoRetornoFicaComODocumentoInteiro($tipo, $campo, $documento, $tipoDocumento)
    {
        $pagador = $this->retornoCom($tipo, $campo);

        $this->assertSame($documento, $pagador->getDocumento());
        $this->assertSame($tipoDocumento, $pagador->getTipoDocumento());
    }
}
