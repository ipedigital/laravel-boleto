<?php
namespace Eduardokum\LaravelBoleto\Tests;

use Eduardokum\LaravelBoleto\Util;

/**
 * DD-803 (CNPJ alfanumérico): os três helpers do `Util` que a demanda criou.
 *
 *  - `documentoCanonico`: tira a máscara e põe em maiúsculas, sem apagar letra;
 *  - `formatCnab('9A', ...)`: o campo de documento do arquivo CNAB, alinhado à direita com zeros, preservando letra,
 *    e recusando o que não cabe;
 *  - `documentoDoRetorno`: o documento do pagador lido do retorno CNAB 240, num campo de 15 posições com zero de
 *    enchimento.
 *
 * O par: para documento numérico, o `9A` produz exatamente os bytes que o campo produzia antes (`9` sobre
 * `onlyNumbers`, ou `9L`). É essa equivalência que garante que nenhum arquivo de CNPJ numérico ou CPF muda.
 */
class UtilDocumentoAlfanumericoTest extends TestCase
{
    public function documentosParaCanonizar()
    {
        return [
            'CNPJ alfanumérico com máscara e minúsculas' => ['12.abc.345/01de-35', '12ABC34501DE35'],
            'CNPJ numérico com máscara'                   => ['11.222.333/0001-81', '11222333000181'],
            'CPF com máscara'                             => ['529.982.247-25', '52998224725'],
            'com espaços'                                 => [' 12 ABC 345 01DE35 ', '12ABC34501DE35'],
        ];
    }

    /**
     * @dataProvider documentosParaCanonizar
     */
    public function testDocumentoCanonico($documento, $esperado)
    {
        $this->assertSame($esperado, Util::documentoCanonico($documento));
    }

    public function documentosNumericosECampos()
    {
        $casos = [];
        foreach (['11.222.333/0001-81', '11222333000181', '529.982.247-25', '52998224725', '04.740.714/0001-97'] as $doc) {
            foreach ([14, 15, 16] as $tamanho) {
                $casos["$doc em $tamanho posições"] = [$doc, $tamanho];
            }
        }

        return $casos;
    }

    /**
     * @dataProvider documentosNumericosECampos
     */
    public function testNoveAProduzOsMesmosBytesDeAntesParaDocumentoNumerico($documento, $tamanho)
    {
        $comoAntes = Util::formatCnab('9', Util::onlyNumbers($documento), $tamanho);

        $this->assertSame($comoAntes, Util::formatCnab('9A', $documento, $tamanho));
        $this->assertSame(Util::formatCnab('9L', $documento, $tamanho), Util::formatCnab('9A', $documento, $tamanho));
    }

    public function testNoveAPreservaALetraAlinhadaADireitaComZeros()
    {
        $this->assertSame('012ABC34501DE35', Util::formatCnab('9A', '12.abc.345/01de-35', 15));
        $this->assertSame('12ABC34501DE35', Util::formatCnab('9A', '12ABC34501DE35', 14));
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage não cabe no campo
     */
    public function testNoveARecusaODocumentoQueNaoCabeEmVezDeCortar()
    {
        Util::formatCnab('9A', '12ABC34501DE35', 13);
    }

    public function camposDoRetorno()
    {
        return [
            'CNPJ alfanumérico com zero de enchimento' => ['012ABC34501DE35', '2', '12ABC34501DE35'],
            'CNPJ numérico com zero de enchimento'     => ['011222333000181', '2', '11222333000181'],
            'CNPJ numérico que começa com zero'        => ['002809905000132', '2', '02809905000132'],
            'CPF com zeros de enchimento'              => ['000052998224725', '1', '52998224725'],
            'campo com mais posições significativas'   => ['112ABC34501DE35', '2', '112ABC34501DE35'],
        ];
    }

    /**
     * @dataProvider camposDoRetorno
     */
    public function testDocumentoDoRetornoTiraSoOsZerosQueSobram($campo, $tipo, $esperado)
    {
        $this->assertSame($esperado, Util::documentoDoRetorno($campo, $tipo));
    }
}
