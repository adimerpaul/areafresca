<?php
namespace App\Services\Siat;
use DOMDocument; use RobRichards\XMLSecLibs\XMLSecurityDSig; use RobRichards\XMLSecLibs\XMLSecurityKey;
class XmlSigner {
    public function sign(string $xml, string $privateKey, string $certificate): string {
        $doc=new DOMDocument('1.0','UTF-8'); $doc->preserveWhiteSpace=false; $doc->loadXML($xml, LIBXML_NONET);
        $sig=new XMLSecurityDSig(); $sig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $sig->addReference($doc,XMLSecurityDSig::SHA256,[XMLSecurityDSig::ENVELOPED_SIGNATURE,'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments'],['force_uri'=>true]);
        $key=new XMLSecurityKey(XMLSecurityKey::RSA_SHA256,['type'=>'private']); $key->loadKey($privateKey,false); $sig->sign($key); $sig->add509Cert($certificate); $sig->appendSignature($doc->documentElement); return $doc->saveXML();
    }
}
