<?php
namespace Eduardokum\LaravelBoleto;

use Eduardokum\LaravelBoleto\Contracts\Pessoa as PessoaContract;

class Pessoa implements PessoaContract
{
    /**
     * @var string
     */
    protected $nome;
    /**
     * @var string
     */
    protected $endereco;
    /**
     * @var string
     */
    protected $bairro;
    /**
     * @var string
     */
    protected $cep;
    /**
     * @var string
     */
    protected $uf;
    /**
     * @var string
     */
    protected $cidade;
    /**
     * @var string
     */
    protected $documento;

    /**
     * @var boolean
     */
    protected $dda = false;

    /**
     * Cria a pessoa passando os parametros.
     *e
     * @param $nome
     * @param $documento
     * @param null      $endereco
     * @param null      $cep
     * @param null      $cidade
     * @param null      $uf
     *
     * @return Pessoa
     */
    public static function create($nome, $documento, $endereco = null, $bairro = null, $cep = null, $cidade = null, $uf = null)
    {
        return new static([
            'nome' => $nome,
            'endereco' => $endereco,
            'bairro' => $bairro,
            'cep' => $cep,
            'uf' => $uf,
            'cidade' => $cidade,
            'documento' => $documento,
        ]);
    }

    /**
     * Construtor
     *
     * @param array $params
     */
    public function __construct($params = [])
    {
        Util::fillClass($this, $params);
    }

    /**
     * Define o CEP
     *
     * @param string $cep
     *
     * @return Pessoa
     */
    public function setCep($cep)
    {
        $this->cep = $cep;

        return $this;
    }
    /**
     * Retorna o CEP
     *
     * @return string
     */
    public function getCep()
    {
        return Util::maskString(Util::onlyNumbers($this->cep), '#####-###');
    }

    /**
     * Define a cidade
     *
     * @param string $cidade
     *
     * @return Pessoa
     */
    public function setCidade($cidade)
    {
        $this->cidade = $cidade;


        return $this;
    }
    /**
     * Retorna a cidade
     *
     * @return string
     */
    public function getCidade()
    {
        return $this->cidade;
    }

    /**
     * Define o documento (CPF, CNPJ ou CEI)
     *
     * O CNPJ alfanumérico (IN RFB nº 2.229/2024, em circulação desde 31/07/2026) tem a forma
     * [0-9A-Z]{12}[0-9]{2}: as doze primeiras posições aceitam letra, os dois dígitos verificadores
     * não. Por isso o documento NÃO pode ser reduzido a dígitos antes de ser validado — era o que
     * `substr(Util::onlyNumbers($documento), -14)` fazia, e a consequência não era recusar o
     * alfanumérico: era aceitá-lo como outro documento. Com três letras sobram onze dígitos, um
     * comprimento "válido", e o valor seguia adiante classificado como CPF.
     *
     * A decisão passa a ser pelo comprimento do documento CANÔNICO (sem máscara, maiúsculas), que é
     * o mesmo critério para numérico e alfanumérico:
     *
     *   11 posições → CPF · 14 → CNPJ (numérico ou alfanumérico) · 10 → CEI
     *
     * Documento SEM NENHUM DÍGITO é tratado como ausente, e isso é deliberado: cadastros antigos
     * usam 'XXXXXXXXXXX' no lugar do documento, e hoje eles atravessam porque `onlyNumbers` devolve
     * string vazia e comprimento zero está na lista de aceitos. Preservar as letras sem esta guarda
     * faria esses onze 'X' virarem um CPF no arquivo; recusá-los pararia cobranças que hoje saem.
     *
     * Já o documento maior que 14 posições, que antes era truncado em silêncio pelo `substr`, passa
     * a ser recusado: adaptar documento para caber é o que produz um documento que ninguém informou.
     *
     * @param string $documento
     *
     * @throws \Exception
     */
    public function setDocumento($documento)
    {
        $canonico = self::canonizaDocumento($documento);

        if (!preg_match('/[0-9]/', $canonico)) {
            $this->documento = '';

            return;
        }

        if (!in_array(strlen($canonico), array(10, 11, 14))) {
            throw new \Exception(sprintf('Documento inválido: [%s] tem %d posições; são esperadas 11 (CPF), 14 (CNPJ) ou 10 (CEI)', $canonico, strlen($canonico)));
        }

        $this->documento = $canonico;
    }

    /**
     * Forma canônica do documento: sem máscara, em maiúsculas, só [0-9A-Z].
     *
     * Aceita o valor como o usuário digita ('12.abc.345/01de-35') e como o sistema guarda
     * ('12ABC34501DE35'), e não deixa passar nada fora da allowlist.
     *
     * @param string $documento
     *
     * @return string
     */
    private static function canonizaDocumento($documento)
    {
        return preg_replace('/[^0-9A-Z]/', '', Util::upper((string) $documento));
    }
    /**
     * Retorna o documento (CPF ou CNPJ) mascarado
     *
     * Sem `Util::onlyNumbers`: o documento já está canônico desde `setDocumento`, e reduzi-lo a
     * dígitos aqui apagaria as letras do CNPJ alfanumérico no boleto impresso e em tudo que exibe
     * o pagador. `Util::maskString` encaixa caractere a caractere nas posições '#' do molde, sem
     * exigir que sejam dígitos.
     *
     * @return string
     */
    public function getDocumento()
    {
        if ($this->getTipoDocumento() == 'CPF') {
            return Util::maskString($this->documento, '###.###.###-##');
        } elseif ($this->getTipoDocumento() == 'CEI') {
            return Util::maskString($this->documento, '##.#####.#-##');
        }
        return Util::maskString($this->documento, '##.###.###/####-##');
    }

    /**
     * Retorna o documento sem máscara, na forma canônica — o valor que vai para arquivo posicional.
     *
     * Existe porque `getDocumento()` devolve o valor MASCARADO, e as classes de remessa precisavam
     * desfazer a máscara para escrever no campo. Enquanto isso era feito com `onlyNumbers`, desfazer
     * a máscara e apagar as letras eram a mesma operação.
     *
     * @return string
     */
    public function getDocumentoCanonico()
    {
        return (string) $this->documento;
    }

    /**
     * Define o endereço
     *
     * @param string $endereco
     *
     * @return Pessoa
     */
    public function setEndereco($endereco)
    {
        $this->endereco = $endereco;

        return $this;
    }
    /**
     * Retorna o endereço
     *
     * @return string
     */
    public function getEndereco()
    {
        return $this->endereco;
    }

    /**
     * Define o bairro
     *
     * @param string $bairro
     *
     * @return Pessoa
     */
    public function setBairro($bairro)
    {
        $this->bairro = $bairro;

        return $this;
    }
    /**
     * Retorna o bairro
     *
     * @return string
     */
    public function getBairro()
    {
        return $this->bairro;
    }

    /**
     * Define o nome
     *
     * @param string $nome
     *
     * @return Pessoa
     */
    public function setNome($nome)
    {
        $this->nome = $nome;

        return $this;
    }
    /**
     * Retorna o nome
     *
     * @return string
     */
    public function getNome()
    {
        return $this->nome;
    }

    /**
     * Define a UF
     *
     * @param string $uf
     *
     * @return Pessoa
     */
    public function setUf($uf)
    {
        $this->uf = $uf;

        return $this;
    }
    /**
     * Retorna a UF
     *
     * @return string
     */
    public function getUf()
    {
        return $this->uf;
    }
    /**
     * Retorna o nome e o documento formatados
     *
     * @return string
     */
    public function getNomeDocumento()
    {
        if (!$this->getDocumento()) {
            return $this->getNome();
        } else {
            return $this->getNome() . ' / ' . $this->getTipoDocumento() . ': ' . $this->getDocumento();
        }
    }
    /**
     * Retorna se o tipo do documento é CPF ou CNPJ ou Documento
     *
     * Decide pelo comprimento do documento CANÔNICO, não pela contagem de dígitos. Com `onlyNumbers`
     * um CNPJ alfanumérico de três letras devolvia onze dígitos e era classificado como **CPF** — e é
     * este método que alimenta o "tipo de inscrição" do arquivo CNAB (01/02, ou 10/20 no Sicredi).
     * O efeito era o banco receber uma pessoa jurídica declarada como pessoa física.
     *
     * @return string
     */
    public function getTipoDocumento()
    {
        $tamanho = strlen((string) $this->documento);

        if ($tamanho == 11) {
            return 'CPF';
        } elseif ($tamanho == 10) {
            return 'CEI';
        }

        return 'CNPJ';
    }
    /**
     * Retorna o endereço formatado para a linha 2 de endereço
     *
     * Ex: 71000-000 - Brasília - DF
     *
     * @return string
     */
    public function getCepCidadeUf()
    {
        $dados = array_filter(array($this->getCep(), $this->getCidade(), $this->getUf()));
        return implode(' - ', $dados);
    }

    /**
     * Retorna o endereço completo em uma única string
     *
     * Ex.: Rua um, 123 - Bairro Industrial - Brasília - DF - 71000-000
     *
     * @return string
     */
    public function getEnderecoCompleto()
    {
        $dados = array_filter(array($this->getEndereco(), $this->getBairro(), $this->getCidade(), $this->getUf(), $this->getCep()));
        return implode(' - ', $dados);
    }

    /**
     * @return bool
     */
    public function isDda() {
        return $this->dda;
    }

    /**
     * @param bool $dda
     *
     * @return Pessoa
     */
    public function setDda($dda) {
        $this->dda = $dda;

        return $this;
    }
    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'nome' => $this->getNome(),
            'endereco' => $this->getEndereco(),
            'bairro' => $this->getBairro(),
            'cep' => $this->getCep(),
            'uf' => $this->getUf(),
            'cidade' => $this->getCidade(),
            'documento' => $this->getDocumento(),
            'nome_documento' => $this->getNomeDocumento(),
            'endereco2' => $this->getCepCidadeUf(),
			'endereco_completo' => $this->getEnderecoCompleto(),
            'dda' => $this->isDda(),
        ];
    }
}
