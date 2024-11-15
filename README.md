Estou tendo dificuldades para fazer este script funcionar corretamente.

Segui todas as instruções dos manuais da prefeitura para pode fazer a integração do meu sistema com o Web Service da Prefeura

Segue os links dos maunais. 
https://nfse.recife.pe.gov.br/arquivos/nfse_abrasf_integracao.pdf
https://nfse.recife.pe.gov.br/arquivos/WsNFSeNacional.pdf
https://nfse.recife.pe.gov.br//arquivos/WsNFSeMunicipalV03.pdf


O script que postei aqui esta depurando as respostas:

XML Assinado: Ok esta assinando o XML

Debug cURL: 
*   Trying 192.207.206.137:443...
* Connected to nfse.recife.pe.gov.br (192.207.206.137) port 443 (#0)
* ALPN, offering h2
* ALPN, offering http/1.1
*  CAfile: /etc/pki/tls/certs/ca-bundle.crt
* SSL connection using TLSv1.2 / ECDHE-RSA-AES128-SHA256
* ALPN, server did not agree to a protocol
* Server certificate:
*  subject: CN=*.recife.pe.gov.br
*  start date: Nov 16 18:31:16 2023 GMT
*  expire date: Dec 17 18:31:15 2024 GMT
*  subjectAltName: host "nfse.recife.pe.gov.br" matched cert's "*.recife.pe.gov.br"
*  issuer: C=BE; O=GlobalSign nv-sa; CN=AlphaSSL CA - SHA256 - G4
*  SSL certificate verify ok.
> POST /WS/nfse_v03.asmx HTTP/1.1
Host: nfse.recife.pe.gov.br
Accept: */*
Content-Type: text/xml; charset=UTF-8
SOAPAction: "http://nfse.recife.pe.gov.br/GerarNfse"
Content-Length: 10859

* old SSL session ID is stale, removing
* Mark bundle as not supporting multiuse
< HTTP/1.1 400 Bad Request
< Cache-Control: private
< Content-Type: text/xml; charset=utf-8
< Server: Microsoft-IIS/7.5
< X-AspNet-Version: 4.0.30319
< X-Powered-By: ASP.NET
< Date: Fri, 15 Nov 2024 21:49:57 GMT
< Content-Length: 0
< 
* Connection #0 to host nfse.recife.pe.gov.br left intact


Resposta do Servidor: (nenhuma resposta) :(
