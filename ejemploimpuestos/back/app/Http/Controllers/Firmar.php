<?php

namespace App\Http\Controllers;
//use RobRichards\XMLSecLibs\XMLSecurityDSig;
//use RobRichards\XMLSecLibs\XMLSecurityKey;
use DOMDocument;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class Firmar
{

    /**
     * @throws \Exception
     */
    public function firmar($fileName){
        $doc = new DOMDocument();
        $doc->load($fileName);

        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA256,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature','http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments'),
            array('force_uri' => true)
        );
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'private'));
        /*
        If key has a passphrase, set it using
        $objKey->passphrase = '<passphrase>';
        */
        $objKey->loadKey(storage_path('key/privateKey.pem'), TRUE);

        $objDSig->sign($objKey);

        $objDSig->add509Cert(file_get_contents(storage_path('key/publicKey.pem')));

        $objDSig->appendSignature($doc->documentElement);
        $doc->save($fileName);
    }
    function getFileGzip($fileName)
    {
        $fileName = $fileName;

        $handle = fopen($fileName, "rb");
        $contents = fread($handle, filesize($fileName));
        fclose($handle);

        return $contents;
    }
}
