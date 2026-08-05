<?php

namespace App\Services\Siat;

use DOMDocument;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

class XmlSigner
{
    private const ENVELOPED_SIGNATURE = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    private const C14N_WITH_COMMENTS = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments';

    public function sign(string $xml, string $privateKey, string $certificate): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('No se pudo cargar el XML para firmarlo');
        }

        $signature = new XMLSecurityDSig;
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference(
            $document,
            XMLSecurityDSig::SHA256,
            [self::ENVELOPED_SIGNATURE, self::C14N_WITH_COMMENTS],
            ['force_uri' => true],
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, [
            'type' => 'private',
        ]);
        $key->loadKey($privateKey, false);

        $signature->sign($key);
        $signature->add509Cert($certificate);
        $signature->appendSignature($document->documentElement);

        return $document->saveXML();
    }
}
