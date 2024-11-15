<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function assinarXML(string $xml, string $caminhoCertificado): string
{
    $privateKey = openssl_pkey_get_private(file_get_contents($caminhoCertificado));
    $certData = file_get_contents($caminhoCertificado);

    if (!$privateKey || !$certData) {
        throw new Exception("Erro ao ler o certificado ou chave privada: " . openssl_error_string());
    }

    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $doc->encoding = 'UTF-8';

    // Adicionar o atributo xmlns:ds ao elemento root do XML
    $doc->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');

    $signature = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Signature');
    $doc->documentElement->appendChild($signature);

    $signedInfo = $doc->createElement('ds:SignedInfo');
    $signature->appendChild($signedInfo);

    $canonicalizationMethod = $doc->createElement('ds:CanonicalizationMethod');
    $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
    $signedInfo->appendChild($canonicalizationMethod);

    $signatureMethod = $doc->createElement('ds:SignatureMethod');
    $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
    $signedInfo->appendChild($signatureMethod);

    $reference = $doc->createElement('ds:Reference');
    $reference->setAttribute('URI', '#Rps_5'); // Certifique-se de que o ID está correto
    $signedInfo->appendChild($reference);

    $transforms = $doc->createElement('ds:Transforms');
    $reference->appendChild($transforms);

    $transform1 = $doc->createElement('ds:Transform');
    $transform1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
    $transforms->appendChild($transform1);

    $transform2 = $doc->createElement('ds:Transform');
    $transform2->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');
    $transforms->appendChild($transform2);

    $digestMethod = $doc->createElement('ds:DigestMethod');
    $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
    $reference->appendChild($digestMethod);

    $canonicalData = $doc->C14N(true, false);
    $digestValue = base64_encode(sha1($canonicalData, true));
    $digestValueElement = $doc->createElement('ds:DigestValue', $digestValue);
    $reference->appendChild($digestValueElement);

    $signedInfoData = $signedInfo->C14N(true, false);
    $signatureValue = '';

    if (!openssl_sign($signedInfoData, $signatureValue, $privateKey, OPENSSL_ALGO_SHA1)) {
        throw new Exception("Erro ao assinar: " . openssl_error_string());
    }

    $signatureValueElement = $doc->createElement('ds:SignatureValue', base64_encode($signatureValue));
    $signature->appendChild($signatureValueElement);

    $keyInfo = $doc->createElement('ds:KeyInfo');
    $signature->appendChild($keyInfo);

    $x509Data = $doc->createElement('ds:X509Data');
    $keyInfo->appendChild($x509Data);

    // Extrair o certificado como string
    $x509Certificate = $doc->createElement('ds:X509Certificate', str_replace(["\n", "\r", "-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----"], "", $certData));
    $x509Data->appendChild($x509Certificate);

    return $doc->saveXML();
}

function enviarXmlParaWebService($xmlAssinado, $caminhoCertificado, $senhaCertificado) {
    $url = 'https://nfse.recife.pe.gov.br/WS/nfse_v03.asmx';

    // Adicionar cabeçalho com a versão dos dados
    $soapHeader = '<cabeçalho xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">
        <versaoDados>1.00</versaoDados>
    </cabeçalho>';

    // Envolver o XML assinado em um envelope SOAP com cabeçalho
    $soapEnvelope = '<?xml version="1.0" encoding="UTF-8"?>
    <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Header>' . $soapHeader . '</soap:Header>
        <soap:Body>
            ' . $xmlAssinado . '
        </soap:Body>
    </soap:Envelope>';

    // Debug: Imprimir o XML assinado que está sendo enviado
    echo "<h2>XML Assinado:</h2>";
    echo "<pre>" . htmlspecialchars($soapEnvelope) . "</pre>";

    // Inicializa o cURL
    $ch = curl_init($url);

    // Configurações do cURL
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $soapEnvelope);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/xml; charset=UTF-8',
        'SOAPAction: "http://nfse.recife.pe.gov.br/GerarNfse"'
    ]);

    // Configurações do certificado
    curl_setopt($ch, CURLOPT_SSLCERT, $caminhoCertificado);
    curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $senhaCertificado);
    curl_setopt($ch, CURLOPT_SSLKEY, $caminhoCertificado);
    curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $senhaCertificado);

    // Adiciona opções de debug
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    // Executa a requisição
    $response = curl_exec($ch);

    // Verifica se houve erro
    if (curl_errno($ch)) {
        throw new Exception('Erro no cURL: ' . curl_error($ch));
    }

    // Fecha a conexão cURL
    curl_close($ch);

    // Exibe informações de debug
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    echo "<h2>Debug cURL:</h2>";
    echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";

    return $response;
}

// Exemplo de uso
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<GerarNfseEnvio xmlns="http://nfse.recife.pe.gov.br/WSNacional/XSD/1/nfse_recife_v01.xsd">
    <Rps>
        <InfRps xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd" Id="Rps_5">
            <IdentificacaoRps>
                <Numero>000000000000005</Numero> <!-- TsNumeroRps: 15 dígitos -->
                <Serie>XYZ</Serie> <!-- TsSerieRps: até 5 caracteres -->
                <Tipo>1</Tipo> <!-- TsTipoRps: 1 dígito -->
            </IdentificacaoRps>
            <DataEmissao>2012-05-01T21:00:00</DataEmissao> <!-- TsData: formato ISO 8601 -->
            <NaturezaOperacao>1</NaturezaOperacao> <!-- tsNaturezaOperacao: 2 dígitos -->
            <OptanteSimplesNacional>2</OptanteSimplesNacional> <!-- TsSimNao: 1 dígito -->
            <IncentivadorCultural>2</IncentivadorCultural> <!-- TsSimNao: 1 dígito -->
            <Status>1</Status> <!-- TsStatusRps: 1 dígito -->
            <Servico>
                <Valores>
                    <ValorServicos>1000.00</ValorServicos> <!-- TsValor: 15,2 -->
                    <ValorDeducoes>0.00</ValorDeducoes> <!-- TsValor: 15,2 -->
                    <ValorTotalRecebido>10000.00</ValorTotalRecebido> <!-- TsValor: 15,2 -->
                    <ValorPis>10.00</ValorPis> <!-- TsValor: 15,2 -->
                    <ValorCofins>10.00</ValorCofins> <!-- TsValor: 15,2 -->
                    <ValorInss>10.00</ValorInss> <!-- TsValor: 15,2 -->
                    <ValorIr>10.00</ValorIr> <!-- TsValor: 15,2 -->
                    <ValorCsll>10.00</ValorCsll> <!-- TsValor: 15,2 -->
                    <IssRetido>2</IssRetido> <!-- TsSimNao: 1 dígito -->
                    <ValorIss>10.00</ValorIss> <!-- TsValor: 15,2 -->
                    <Aliquota>0.0500</Aliquota> <!-- TsAliquota: 5,4 -->
                </Valores>
                <ItemListaServico>0102</ItemListaServico> <!-- tsItemListaServico: até 5 caracteres -->
                <CodigoTributacaoMunicipio>6201500</CodigoTributacaoMunicipio> <!-- tsCodigoTributacao: até 20 caracteres -->
                <Discriminacao>DESCRICAO DA NOTA
                    1. Item 1
                    2. Item 2
                    3. Item 3
                </Discriminacao> <!-- tsDiscriminacao: até 2000 caracteres -->
                <CodigoMunicipio>2611606</CodigoMunicipio> <!-- tsCodigoMunicipioIbge: 7 dígitos -->
            </Servico>
            <Prestador>
                <Cnpj>54192685000144</Cnpj> <!-- TsCnpj: 14 caracteres -->
                <InscricaoMunicipal>1570790</InscricaoMunicipal> <!-- tsIncricaoMunicipal: até 15 caracteres -->
            </Prestador>
            <Tomador>
                <IdentificacaoTomador>
                    <CpfCnpj>
                        <Cnpj>07670815442</Cnpj> <!-- TsCnpj: 14 caracteres -->
                    </CpfCnpj>
                </IdentificacaoTomador>
                <RazaoSocial>INSCRICAO DE TESTE</RazaoSocial> <!-- tsRazaoSocial: até 115 caracteres -->
                <Endereco>
                    <Endereco>RUA AGENOR LOPES</Endereco> <!-- tsEndereco: até 125 caracteres -->
                    <Numero>300</Numero> <!-- tsNumeroEndereco: até 10 caracteres -->
                    <Complemento>SALA 1001 1002</Complemento> <!-- tsComplementoEndereco: até 60 caracteres -->
                    <Bairro>BOA VIAGEM</Bairro> <!-- tsBairro: até 60 caracteres -->
                    <CodigoMunicipio>2611606</CodigoMunicipio> <!-- tsCodigoMunicipioIbge: 7 dígitos -->
                    <Uf>PE</Uf> <!-- tsUf: 2 caracteres -->
                    <Cep>51021110</Cep> <!-- tsCep: 8 dígitos -->
                </Endereco>
            </Tomador>
        </InfRps>
    </Rps>
</GerarNfseEnvio>';

$caminhoCertificado = 'privatekey.pem'; // Caminho para o arquivo PEM que contém a chave privada e o certificado
$senhaCertificado = 'sua_senha_aqui'; // Substitua pela senha do seu certificado

try {
    // Assinar o XML
    $signedXML = assinarXML($xml, $caminhoCertificado);

    // Enviar o XML assinado
    $response = enviarXmlParaWebService($signedXML, $caminhoCertificado, $senhaCertificado);
    echo "<h2>Resposta do Servidor:</h2>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

?>
