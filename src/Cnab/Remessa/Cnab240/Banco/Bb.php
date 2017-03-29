<?php
/**
 * Created by PhpStorm.
 * User: Fernando
 * Date: 23/12/2016
 * Time: 13:08
 */

namespace Eduardokum\LaravelBoleto\Cnab\Remessa\Cnab240\Banco;

use Eduardokum\LaravelBoleto\Cnab\Remessa\Cnab240\AbstractRemessa;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Eduardokum\LaravelBoleto\Contracts\Cnab\Remessa as RemessaContract;
use Eduardokum\LaravelBoleto\Util;

class Bb extends AbstractRemessa implements RemessaContract
{
    const ESPECIE_DUPLICATA_MERCANTIL = '02';

    const OCORRENCIA_REMESSA = '01';
    const OCORRENCIA_PEDIDO_BAIXA = '02';
    const OCORRENCIA_CONCESSAO_ABATIMENTO = '04';
    const OCORRENCIA_CANC_ABATIMENTO_CONCEDIDO = '05';
    const OCORRENCIA_ALT_VENCIMENTO = '06';
    const OCORRENCIA_CONCEDER_DESC = '07';
    const OCORRENCIA_NAO_CONCEDER_DESC = '08';
    const OCORRENCIA_PEDIDO_PROTESTO = '09';
    const OCORRENCIA_SUSTAR_PROTESTO = '10';
    const OCORRENCIA_RECUSA_ALEGACAO_SACADO = '30';
    const OCORRENCIA_ALT_OUTROS_DADOS = '31';
    const OCORRENCIA_ALT_MODALIDADE = '31';

    const INSTRUCAO_SEM = '00';
    const INSTRUCAO_COBRAR_JUROS = '01';
    const INSTRUCAO_NAO_PROTESTAR = '07';
    const INSTRUCAO_PROTESTAR = '09';
    const INSTRUCAO_PROTESTAR_VENC_03 = '03';
    const INSTRUCAO_PROTESTAR_VENC_04 = '04';
    const INSTRUCAO_PROTESTAR_VENC_05 = '05';
    const INSTRUCAO_PROTESTAR_VENC_XX = '06';
    const INSTRUCAO_PROTESTAR_VENC_15 = '15';
    const INSTRUCAO_PROTESTAR_VENC_20 = '20';
    const INSTRUCAO_PROTESTAR_VENC_25 = '25';
    const INSTRUCAO_PROTESTAR_VENC_30 = '30';
    const INSTRUCAO_PROTESTAR_VENC_45 = '45';
    const INSTRUCAO_CONCEDER_DESC_ATE = '22';
    const INSTRUCAO_DEVOLVER = '42';
    const INSTRUCAO_BAIXAR = '44';
    const INSTRUCAO_ENTREGAR_SACADO_PAGAMENTO = '46';

    /**
     * Quantidade de registros do lote.
     */
    private $qtyRegistrosLote;

    /**
     * Código do banco
     *
     * @var string
     */
    protected $codigoBanco = BoletoContract::COD_BANCO_BB;

    /**
     * Tipo de inscrição da empresa
     *
     * @var string
     */
    protected $tipoInscricaoEmpresa;

    /**
     * Numero de inscrição da empresa
     *
     * @var string
     */
    protected $numeroInscricaoEmpresa;


    /**
     * Define as carteiras disponíveis para cada banco
     *
     * @var array
     */
    protected $carteiras = [11, 12, 17, 31, 51];

    /**
     * Caracter de fim de linha
     *
     * @var string
     */
    protected $fimLinha = "\r\n";

    /**
     * Caracter de fim de arquivo
     *
     * @var null
     */
    protected $fimArquivo = "\r\n";

    /**
     * @return mixed
     */
    protected function headerLote()
    {
        // TODO: Implement headerLote() method.
    }

    /**
     * @return mixed
     */
    protected function trailerLote()
    {
        // TODO: Implement trailerLote() method.
    }

    /**
     * @return mixed
     */
    protected function header()
    {
        // TODO: Implement header() method.
    }

    /**
     * @param BoletoContract $detalhe
     * @return mixed
     */
    public function addBoleto(BoletoContract $detalhe)
    {
        // TODO: Implement addBoleto() method.
    }

    /**
     * @return mixed
     */
    protected function trailer()
    {
        // TODO: Implement trailer() method.
    }
}