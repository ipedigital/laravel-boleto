<?php
namespace Eduardokum\LaravelBoleto\Tests\Remessa;

use Eduardokum\LaravelBoleto\Pessoa;
use Eduardokum\LaravelBoleto\Tests\TestCase;

/**
 * DD-803 (CNPJ alfanumérico): o documento na remessa dos 19 pares de banco e layout que a demanda alterou (11 em CNAB
 * 400 e 8 em CNAB 240). Antes, os campos de documento eram escritos com `onlyNumbers`: o CNPJ alfanumérico perdia as
 * letras, e o tipo de inscrição saía como pessoa física.
 *
 * A prova não depende do layout de cada banco: o mesmo arquivo é gerado com o CNPJ numérico e com o CNPJ alfanumérico
 * (no beneficiário e no pagador), e os dois têm de ser iguais byte a byte, a não ser pelo próprio documento. Ou seja, o
 * CNPJ alfanumérico ocupa exatamente as posições do numérico, inteiro, e o tipo de inscrição é o mesmo (pessoa
 * jurídica). Que o numérico sai como antes está provado no `UtilDocumentoAlfanumericoTest` (o `9A` produz os mesmos
 * bytes que o campo produzia antes).
 */
class RemessaDocumentoAlfanumericoTest extends TestCase
{
    const CNPJ_NUMERICO = '11222333000181';
    const CNPJ_ALFA = '12ABC34501DE35';

    private static function pessoa($documento)
    {
        return new Pessoa([
            'nome'      => 'ACME',
            'endereco'  => 'Rua um, 123',
            'bairro'    => 'Bairro',
            'cep'       => '99999-999',
            'uf'        => 'UF',
            'cidade'    => 'CIDADE',
            'documento' => $documento,
        ]);
    }

    public function bancos()
    {
        $bancos = [];
        $layouts = [
            'Cnab400' => ['Bancoob', 'Banrisul', 'Bb', 'Bnb', 'Bradesco', 'Caixa', 'Hsbc', 'Itau', 'Santander356',
                'Santander', 'Sicredi'],
            'Cnab240' => ['Bancoob', 'Banrisul', 'Bb', 'Bradesco', 'Caixa', 'Itau', 'Santander', 'Sicredi'],
        ];
        foreach ($layouts as $layout => $nomes) {
            foreach ($nomes as $nome) {
                $bancos["$layout $nome"] = [$layout, $nome];
            }
        }

        return $bancos;
    }

    private function gerar($layout, $nome, $documento)
    {
        $classeBoleto = 'Eduardokum\\LaravelBoleto\\Boleto\\Banco\\' . ($nome == 'Santander356' ? 'Santander' : $nome);
        $classeRemessa = "Eduardokum\\LaravelBoleto\\Cnab\\Remessa\\$layout\\Banco\\$nome";

        $padrao = (new \ReflectionClass($classeBoleto))->getDefaultProperties();
        $carteiraBoleto = $padrao['carteiras'][0];
        $padraoRemessa = (new \ReflectionClass($classeRemessa))->getDefaultProperties();
        $carteiraRemessa = in_array($carteiraBoleto, (array) $padraoRemessa['carteiras'])
            ? $carteiraBoleto
            : $padraoRemessa['carteiras'][0];

        $conta = [
            'agencia'          => 1111,
            'agenciaDv'        => 1,
            'conta'            => 12345,
            'contaDv'          => 9,
            'codigoCliente'    => 12345,
            'convenio'         => 1234567,
            'posto'            => 11,
            'byte'             => 2,
            'variacaoCarteira' => '017',
        ];

        $boleto = new $classeBoleto($conta + [
            'dataVencimento'  => new \Carbon\Carbon('2026-12-31'),
            'valor'           => 100,
            'multa'           => false,
            'juros'           => false,
            'numero'          => 1,
            'numeroDocumento' => 1,
            'carteira'        => $carteiraBoleto,
            'aceite'          => 'S',
            'especieDoc'      => 'DM',
            'pagador'         => self::pessoa($documento),
            'beneficiario'    => self::pessoa($documento),
        ]);

        $remessa = new $classeRemessa($conta + [
            'carteira'     => $carteiraRemessa,
            'idremessa'    => 1,
            'beneficiario' => self::pessoa($documento),
        ]);
        $remessa->addBoleto($boleto);

        return $remessa->gerar();
    }

    /**
     * Gera os dois arquivos no mesmo segundo: alguns layouts escrevem a hora da geração (`date('His')`).
     */
    private function numericoEAlfanumerico($layout, $nome)
    {
        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $antes = date('His');
            $numerico = $this->gerar($layout, $nome, '11.222.333/0001-81');
            $alfanumerico = $this->gerar($layout, $nome, '12.ABC.345/01DE-35');
            if (date('His') === $antes) {
                return [$numerico, $alfanumerico];
            }
        }

        $this->fail('não foi possível gerar os dois arquivos no mesmo segundo');
    }

    /**
     * @dataProvider bancos
     */
    public function testOCnpjAlfanumericoOcupaAsPosicoesDoNumerico($layout, $nome)
    {
        list($numerico, $alfanumerico) = $this->numericoEAlfanumerico($layout, $nome);

        $ocorrencias = substr_count($numerico, self::CNPJ_NUMERICO);
        $this->assertGreaterThan(0, $ocorrencias, 'o layout escreve o documento');
        $this->assertSame($ocorrencias, substr_count($alfanumerico, self::CNPJ_ALFA), 'o alfanumérico sai inteiro');
        $this->assertSame(
            str_replace(self::CNPJ_NUMERICO, self::CNPJ_ALFA, $numerico),
            $alfanumerico,
            'fora o documento, nenhum byte muda: mesmas posições e mesmo tipo de inscrição'
        );
    }
}
