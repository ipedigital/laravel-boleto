<?php
namespace Eduardokum\LaravelBoleto\Tests;

use Eduardokum\LaravelBoleto\Pessoa;
use Eduardokum\LaravelBoleto\Util;

/**
 * DD-803 (CNPJ alfanumérico, IN RFB nº 2.229/2024): o documento da pessoa (pagador, beneficiário e sacador avalista).
 *
 * Antes, `Pessoa::setDocumento` reduzia o documento a dígitos (`substr(Util::onlyNumbers($documento), -14)`): o CNPJ
 * alfanumérico perdia as letras e, com três letras, virava um "CPF" de 11 dígitos, que ia ao banco como pessoa física.
 * Agora o documento fica canônico (sem máscara, maiúsculas) e o tipo sai pelo comprimento dele.
 *
 * O par: para documento numérico, `getDocumento` e `getTipoDocumento` devolvem exatamente o que devolviam antes, e o
 * teste calcula o "antes" com a regra antiga, na própria asserção.
 */
class PessoaDocumentoAlfanumericoTest extends TestCase
{
    /** A regra antiga de `getDocumento`, para comparar com o numérico. */
    private static function documentoComoAntes($documento)
    {
        $digitos = substr(Util::onlyNumbers($documento), -14);
        if (strlen($digitos) == 11) {
            return Util::maskString($digitos, '###.###.###-##');
        } elseif (strlen($digitos) == 10) {
            return Util::maskString($digitos, '##.#####.#-##');
        }

        return Util::maskString($digitos, '##.###.###/####-##');
    }

    /** A regra antiga de `getTipoDocumento`. */
    private static function tipoComoAntes($documento)
    {
        $tamanho = strlen(substr(Util::onlyNumbers($documento), -14));
        if ($tamanho == 11) {
            return 'CPF';
        } elseif ($tamanho == 10) {
            return 'CEI';
        }

        return 'CNPJ';
    }

    private function pessoa($documento)
    {
        return new Pessoa(['nome' => 'ACME', 'documento' => $documento]);
    }

    public function documentosAlfanumericos()
    {
        return [
            'canônico'                  => ['12ABC34501DE35'],
            'com máscara'               => ['12.ABC.345/01DE-35'],
            'com máscara e minúsculas'  => ['12.abc.345/01de-35'],
        ];
    }

    /**
     * @dataProvider documentosAlfanumericos
     */
    public function testCnpjAlfanumericoFicaComAsLetras($documento)
    {
        $pessoa = $this->pessoa($documento);

        $this->assertSame('12.ABC.345/01DE-35', $pessoa->getDocumento());
        $this->assertSame('CNPJ', $pessoa->getTipoDocumento());
    }

    /**
     * Com três letras, os dígitos que sobram são onze: a regra antiga o classificava como CPF.
     */
    public function testCnpjAlfanumericoDeOnzeDigitosNaoViraCpf()
    {
        $pessoa = $this->pessoa('ABC12345678901');

        $this->assertSame('CNPJ', $pessoa->getTipoDocumento());
        $this->assertSame('AB.C12.345/6789-01', $pessoa->getDocumento());
        $this->assertSame('CPF', self::tipoComoAntes('ABC12345678901'), 'era o defeito');
    }

    public function documentosNumericos()
    {
        return [
            'CNPJ com máscara' => ['11.222.333/0001-81'],
            'CNPJ canônico'    => ['11222333000181'],
            'CPF com máscara'  => ['529.982.247-25'],
            'CPF canônico'     => ['52998224725'],
            'CEI'              => ['12.34567.8-90'],
        ];
    }

    /**
     * @dataProvider documentosNumericos
     */
    public function testDocumentoNumericoSegueComoAntes($documento)
    {
        $pessoa = $this->pessoa($documento);

        $this->assertSame(self::documentoComoAntes($documento), $pessoa->getDocumento());
        $this->assertSame(self::tipoComoAntes($documento), $pessoa->getTipoDocumento());
    }

    /**
     * Cadastro antigo com 'XXXXXXXXXXX' no lugar do documento: continua ausente, como antes, e não vira CPF.
     */
    public function testPlaceholderSemDigitoContinuaAusente()
    {
        $pessoa = $this->pessoa('XXXXXXXXXXX');

        $this->assertSame('', $pessoa->getDocumento());
        $this->assertSame(self::documentoComoAntes('XXXXXXXXXXX'), $pessoa->getDocumento());
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Documento inválido
     */
    public function testDocumentoAlfanumericoIncompletoERecusado()
    {
        $this->pessoa('12ABC34501DE3');
    }

    /**
     * Antes, o documento maior que 14 posições era cortado em silêncio. Agora é recusado.
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Documento inválido
     */
    public function testDocumentoMaiorQueOCampoERecusadoEmVezDeCortado()
    {
        $this->pessoa('112ABC34501DE35');
    }
}
