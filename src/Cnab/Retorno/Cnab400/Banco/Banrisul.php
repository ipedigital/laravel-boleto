<?php
namespace Eduardokum\LaravelBoleto\Cnab\Retorno\Cnab400\Banco;

use Eduardokum\LaravelBoleto\Cnab\Retorno\Cnab400\AbstractRetorno;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Eduardokum\LaravelBoleto\Contracts\Cnab\RetornoCnab400;
use Eduardokum\LaravelBoleto\Util;

class Banrisul extends AbstractRetorno implements RetornoCnab400
{
    /**
     * Código do banco
     *
     * @var string
     */
    protected $codigoBanco = BoletoContract::COD_BANCO_BANRISUL;

    /**
     * Array com as ocorrencias do banco;
     *
     * @var array
     */
    private $ocorrencias = [
        '02' => 'Confirmação de entrada',
        '03' => 'Entrada rejeitada',
        '04' => 'Baixa de título liquidado por edital',
        '06' => 'Liquidação normal',
        '07' => 'Liquidação parcial',
        '08' => 'Baixa por pagamento, liquidação pelo saldo',
        '09' => 'Devolução automática',
        '10' => 'Baixado conforme instruções',
        '11' => 'Arquivo levantamento',
        '12' => 'Concessão de abatimento',
        '13' => 'Cancelamento de abatimento',
        '14' => 'Vencimento alterado',
        '15' => 'Pagamento em cartório',
        '16' => 'Alteração de dados',
        '18' => 'Alteração de instruções',
        '19' => 'Confirmação de instrução protesto',
        '20' => 'Confirmação de instrução para sustar protesto',
        '21' => 'Aguardando autorização para protesto por edital',
        '22' => 'Protesto sustado por alteração de vencimento e prazo de cartório',
        '23' => 'Confirmação da entrada em cartório',
        '24' => 'Alterações de dados do sacador',
        '25' => 'Devolução, liquidado anteriormente',
        '26' => 'Devolvido pelo cartório –| erro de informação.',
        '30' => 'cobrança a creditar (liquidação em trânsito)',
        '31' => 'Título em trânsito pago em cartório',
        '32' => 'Reembolso e transferência Desconto e Vendor ou carteira em garantia',
        '33' => 'Reembolso e devolução Desconto e Vendor',
        '34' => 'Reembolso não efetuado por falta de saldo',
        '40' => 'Baixa de títulos protestados',
        '41' => 'Despesa de aponte.',
        '42' => 'Alteração de título',
        '43' => 'Relação de títulos',
        '44' => 'Manutenção mensal',
        '45' => 'Sustação de cartório e envio de título a cartório',
        '46' => 'Fornecimento de formulário pré-impresso',
        '47' => 'Confirmação de entrada –| Pagador DDA',
        '68' => 'Acerto dos dados do rateio de crédito',
        '69' => 'Cancelamento dos dados do rateio',
    ];

    /**
     * Roda antes dos metodos de processar
     */
    protected function init()
    {
        $this->totais = [
            'liquidados' => 0,
            'entradas' => 0,
            'baixados' => 0,
            'protestados' => 0,
            'erros' => 0,
            'alterados' => 0,
        ];
    }

    protected function processarHeader(array $header)
    {
        $this->getHeader()
            ->setOperacaoCodigo($this->rem(2, 2, $header))
            ->setOperacao($this->rem(3, 9, $header))
            ->setServicoCodigo($this->rem(10, 11, $header))
            ->setServico($this->rem(12, 19, $header))
            ->setAgencia($this->rem(27, 30, $header))
            ->setCodigoCliente($this->rem(31, 39, $header))
            ->setData($this->rem(95, 100, $header));

        return true;
    }

    protected function processarDetalhe(array $detalhe)
    {
        if ($this->rem(1, 1, $detalhe) != '1') {
            return false;
        }

        $d = $this->detalheAtual();

        $d->setNossoNumero($this->rem(63, 72, $detalhe))
            ->setOcorrencia($this->rem(109, 110, $detalhe))
            ->setOcorrenciaDescricao(array_get($this->ocorrencias, $d->getOcorrencia(), 'Desconhecida'))
            ->setDataOcorrencia($this->rem(111, 116, $detalhe))
            ->setNumeroDocumento($this->rem(127, 146, $detalhe))
            ->setDataVencimento($this->rem(147, 152, $detalhe))
            ->setValor(Util::nFloat($this->rem(153, 165, $detalhe)/100, 2, false))
            ->setValorTarifa(Util::nFloat($this->rem(182, 188, $detalhe)/100, 2, false))
            ->setValorAbatimento(Util::nFloat($this->rem(228, 240, $detalhe)/100, 2, false))
            ->setValorDesconto(Util::nFloat($this->rem(241, 253, $detalhe)/100, 2, false))
            ->setValorRecebido(Util::nFloat($this->rem(254, 266, $detalhe)/100, 2, false))
            ->setValorMora(Util::nFloat($this->rem(267, 279, $detalhe)/100, 2, false))
            ->setValorMulta(Util::nFloat($this->rem(280, 292, $detalhe)/100, 2, false))
            ->setDataCredito($this->rem(296, 301, $detalhe));

        if ($d->hasOcorrencia('06', '07', '08', '10', '15')) {
            $this->totais['liquidados']++;
            $d->setOcorrenciaTipo($d::OCORRENCIA_LIQUIDADA);
        } elseif ($d->hasOcorrencia('02')) {
            $this->totais['entradas']++;
            $d->setOcorrenciaTipo($d::OCORRENCIA_ENTRADA);
        } elseif ($d->hasOcorrencia('04', '10')) {
            $this->totais['baixados']++;
            $d->setOcorrenciaTipo($d::OCORRENCIA_BAIXADA);
        } elseif ($d->hasOcorrencia('19', '22')) {
            $this->totais['protestados']++;
            $d->setOcorrenciaTipo($d::OCORRENCIA_PROTESTADA);
        } elseif ($d->hasOcorrencia('16', '18', '24')) {
            $this->totais['alterados']++;
            $d->setOcorrenciaTipo($d::OCORRENCIA_ALTERACAO);
        } elseif ($d->hasOcorrencia('03')) {
            $this->totais['erros']++;
            $d->setError(array_get($this->rejeicoes, $d->getOcorrencia(), 'Consulte seu Internet Banking'));
        } else {
            $d->setOcorrenciaTipo($d::OCORRENCIA_OUTROS);
        }

        return true;
    }

    protected function processarTrailer(array $trailer)
    {
        $this->getTrailer()
            ->setValorTitulos(Util::nFloat($this->rem(26, 39, $trailer)/100, 2, false))
            ->setQuantidadeTitulos((int) $this->rem(18, 25, $trailer))
            ->setQuantidadeEntradas((int) $this->totais['entradas'])
            ->setQuantidadeLiquidados((int) $this->totais['liquidados'])
            ->setQuantidadeBaixados((int) $this->totais['baixados'])
            ->setQuantidadeAlterados((int) $this->totais['alterados']);

        return true;
    }
}
