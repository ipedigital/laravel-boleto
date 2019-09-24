<?php
/**
 * Created by PhpStorm.
 * User: fernando
 * Date: 28/02/19
 * Time: 16:48
 */

namespace Eduardokum\LaravelBoleto\Cnab\Remessa\Cnab400\Banco;

use Eduardokum\LaravelBoleto\CalculoDV;
use Eduardokum\LaravelBoleto\Cnab\Remessa\Cnab400\AbstractRemessa;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Eduardokum\LaravelBoleto\Contracts\Cnab\Remessa as RemessaContract;
use Eduardokum\LaravelBoleto\Util;

class Santander356 extends AbstractRemessa implements RemessaContract
{
    const ESPECIE_DUPLICATA = '01';
    const ESPECIE_NOTA_PROMISSORIA = '02';
    const ESPECIE_NOTA_SEGURO = '03';
    const ESPECIE_RECIBO = '05';
    const ESPECIE_DUPLICATA_SERVICO = '06';
    const ESPECIE_LETRA_CAMBIO = '07';

    const OCORRENCIA_REMESSA = '01';
    const OCORRENCIA_PEDIDO_BAIXA = '02';
    const OCORRENCIA_CONCESSAO_ABATIMENTO = '04';
    const OCORRENCIA_CANC_ABATIMENTO = '05';
    const OCORRENCIA_ALT_VENCIMENTO = '06';
    const OCORRENCIA_ALT_CONTROLE_PARTICIPANTE = '07';
    const OCORRENCIA_ALT_SEUNUMERO = '08';
    const OCORRENCIA_PROTESTAR = '09';
    const OCORRENCIA_SUSTAR_PROTESTO = '18';

    const INSTRUCAO_SEM = '00';
    const INSTRUCAO_BAIXAR_APOS_VENC_15 = '02';
    const INSTRUCAO_BAIXAR_APOS_VENC_30 = '03';
    const INSTRUCAO_NAO_BAIXAR = '04';
    const INSTRUCAO_PROTESTAR = '06';
    const INSTRUCAO_NAO_PROTESTAR = '07';
    const INSTRUCAO_NAO_COBRAR_MORA = '08';

    public function __construct(array $params = [])
    {
        parent::__construct($params);
        $this->addCampoObrigatorio('codigoCliente');
    }

    /**
     * Código do banco
     *
     * @var string
     */
    protected $codigoBanco = BoletoContract::COD_BANCO_SANTANDER;

    /**
     * Define as carteiras disponíveis para cada banco
     *
     * @var array
     */
    protected $carteiras = [101, 201];

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
     * Codigo do cliente junto ao banco.
     *
     * @var string
     */
    protected $codigoCliente;

    /**
     * Retorna o codigo do cliente.
     *
     * @return string
     */
    public function getCodigoCliente()
    {
        return $this->codigoCliente;
    }

    /**
     * Seta o codigo do cliente.
     *
     * @param mixed $codigoCliente
     *
     * @return Santander
     */
    public function setCodigoCliente($codigoCliente)
    {
        $this->codigoCliente = $codigoCliente;

        return $this;
    }

    /**
     * Retorna o codigo de transmissão.
     *
     * @return string
     * @throws \Exception
     */
    public function getCodigoTransmissao()
    {
        return Util::formatCnab('9', $this->getAgencia(), 4) . '0'
            . Util::formatCnab('9', substr($this->getCodigoCliente(), 0, 7), 8);
    }

    /**
     * Valor total dos titulos.
     *
     * @var float
     */
    private $total = 0;

    /**
     * @return $this
     * @throws \Exception
     */
    protected function header()
    {
        $this->iniciaHeader();

        $this->add(1, 1, '0');
        $this->add(2, 2, '1');
        $this->add(3, 9, 'REMESSA');
        $this->add(10, 11, '01');
        $this->add(12, 26, Util::formatCnab('X', 'COBRANCA', 15));
        $this->add(27, 27, '0');
        $this->add(28, 39, Util::formatCnab('9', $this->getCodigoTransmissao(), 12));
        $this->add(40, 46, '');
        $this->add(47, 76, Util::formatCnab('X', $this->getBeneficiario()->getNome(), 30));
        $this->add(77, 79, $this->getCodigoBanco());
        $this->add(80, 94, Util::formatCnab('X', 'Banco Real', 15));
        $this->add(95, 100, $this->getDataRemessa('dmy'));
        $this->add(101, 108, '01600BPI');
        $this->add(109, 394, '');
        $this->add(395, 400, Util::formatCnab('9', 1, 6));

        return $this;
    }

    /**
     * @param BoletoContract $boleto
     *
     * @return $this
     * @throws \Exception
     */
    public function addBoleto(BoletoContract $boleto, $dadosPartilha = [])
    {
        $this->boletos[] = $boleto;
        $this->iniciaDetalhe();

        $this->total += $boleto->getValor();

        // Carrega os últimos 8 digitos do nosso numero e devemos desconsiderar
        // e desconsidera o último número porque é o
        $nossoNumero = substr(substr(Util::onlyNumbers($boleto->getNossoNumero()), -8),0,7);

        $this->add(1, 1, '1');
        $this->add(2, 3, strlen(Util::onlyNumbers($this->getBeneficiario()->getDocumento())) == 14 ? '02' : '01');
        $this->add(4, 17, Util::formatCnab('9L', $this->getBeneficiario()->getDocumento(), 14));
        $this->add(18, 18, '0');
        $this->add(19, 22, Util::formatCnab('9', $this->getAgencia(), 4));
        $this->add(23, 23, '0');
        $this->add(24, 30, Util::formatCnab('9', substr($this->getCodigoCliente(), 0, 7), 7));
        $this->add(31, 38, '');
        $this->add(39, 62, Util::formatCnab('X', $boleto->getNumeroControle(), 24)); // numero de controle
        $this->add(63, 64, '00');
        $this->add(65, 71, Util::formatCnab('9', $nossoNumero,7));
        $this->add(72, 72, '0'); // Incidência da Multa - 0' - Sobre o valor Título - 1' - Sobre o valor Corrigido
        $this->add(73, 74, '00'); // Número de Dias para Multa - 00' - Após Vencimento - 01-99' - Número de Dias Após o vencimento
        $this->add(75, 75, '1'); // Tipo da Multa: '0' - Valor, '1' - Taxa
        $this->add(76, 88, Util::formatCnab('9', $boleto->getMulta(), 13, 2));
        $this->add(89, 95, '');
        $this->add(96, 104, Util::formatCnab('9', 0, 9));
        $this->add(105, 105, '');
        $this->add(106, 108, '001');
        $this->add(109, 110, self::OCORRENCIA_REMESSA); // REGISTRO
        if ($boleto->getStatus() == $boleto::STATUS_BAIXA) {
            $this->add(109, 110, self::OCORRENCIA_PEDIDO_BAIXA); // BAIXA
        }
        if ($boleto->getStatus() == $boleto::STATUS_ALTERACAO) {
            $this->add(109, 110, self::OCORRENCIA_ALT_VENCIMENTO); // ALTERAR VENCIMENTO
        }
        $this->add(111, 120, Util::formatCnab('X', $boleto->getNumeroDocumento(), 10));
        $this->add(121, 126, $boleto->getDataVencimento()->format('dmy'));
        $this->add(127, 139, Util::formatCnab('9', $boleto->getValor(), 13, 2));
        $this->add(140, 142, $this->getCodigoBanco());
        $this->add(143, 147, '00000');
        $this->add(148, 149, $boleto->getEspecieDocCodigo());
        $this->add(150, 150, $boleto->getAceite());
        $this->add(151, 156, $boleto->getDataDocumento()->format('dmy'));

        // Código do Protesto:
        // '00' – Conforme cadastro do convênio,
        // '03-55' Número de dias vencidos para protesto,
        // '99' - Não protestar
        $this->add(157, 158, '99');
        $this->add(159, 160, '');

        // Tipo de juros
        // '0' - Valor
        // '1' - Taxa
        $this->add(161, 161, '0'); // Juros cobrados por valor
        $this->add(162, 173, Util::formatCnab('9', $boleto->getMoraDia(), 12, 2));
        $this->add(174, 179, $boleto->getDesconto() > 0 ? $boleto->getDataDesconto()->format('dmy') : '000000');
        $this->add(180, 192, Util::formatCnab('9', $boleto->getDesconto(), 13, 2));
        $this->add(193, 205, Util::formatCnab('9', 0, 13, 2));
        $this->add(206, 218, Util::formatCnab('9', 0, 13, 2));
        $this->add(219, 220, strlen(Util::onlyNumbers($boleto->getPagador()->getDocumento())) == 14 ? '02' : '01');
        $this->add(221, 234, Util::formatCnab('9L', $boleto->getPagador()->getDocumento(), 14));
        $this->add(235, 274, Util::formatCnab('X', $boleto->getPagador()->getNome(), 40));
        $this->add(275, 314, Util::formatCnab('X', $boleto->getPagador()->getEndereco(), 40));
        $this->add(315, 326, Util::formatCnab('X', $boleto->getPagador()->getBairro(), 12));
        $this->add(327, 334, Util::formatCnab('9L', $boleto->getPagador()->getCep(), 8));
        $this->add(335, 349, Util::formatCnab('X', $boleto->getPagador()->getCidade(), 15));
        $this->add(350, 351, Util::formatCnab('X', $boleto->getPagador()->getUf(), 2));
        $this->add(352, 391, Util::formatCnab('X', $boleto->getSacadorAvalista() ? $boleto->getSacadorAvalista()->getNome() : '', 40));
        $this->add(392, 392, '0'); //0 - reais
        $this->add(393, 394, '07');
        $this->add(395, 400, Util::formatCnab('9', $this->iRegistros + 1, 6));

        if (count($dadosPartilha)) {
            // Inserindo informações relativas a partilha
            // São no máximo 4 receptores

            $this->iniciaDetalhe();

            $this->add(1, 1, '2');
            $this->add(2, 11, Util::formatCnab('X', $boleto->getNumeroDocumento(), 10));
            foreach(range(0,3) as $idReceptor) {
                $delta = $idReceptor * 41;
                $this->add(12 + $delta, 13 + $delta, Util::formatCnab('X', array_get($dadosPartilha, $idReceptor.'.codigo', ''), 2));
                $this->add(14 + $delta, 26 + $delta, Util::formatCnab('9', array_get($dadosPartilha, $idReceptor .'.valor',0), 13, 2));
                $this->add(27 + $delta, 52 + $delta, Util::formatCnab('9', 0, 26));
            }
            $this->add(176, 177, '');
            $this->add(178, 216, Util::formatCnab('9', 0, 39));
            $this->add(217, 218, '');
            $this->add(219, 257, Util::formatCnab('9', 0, 39));
            $this->add(258, 394, '');
            $this->add(395, 400, Util::formatCnab('9', $this->iRegistros + 1, 6));
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function trailer()
    {
        $this->iniciaTrailer();

        $this->add(1, 1, '9');
        $this->add(2, 7, Util::formatCnab('9', $this->getCount(), 6));
        $this->add(8, 20, Util::formatCnab('9', $this->total, 13, 2));
        $this->add(21, 394, Util::formatCnab('9', 0, 374));
        $this->add(395, 400, Util::formatCnab('9', $this->getCount(), 6));

        return $this;
    }
}