<?php

namespace App\Injectables;

use Illuminate\Support\Facades\Storage;

class Signer
{




    /**
     * Instance functionality of a singleton
     * @var Signer $instance
     */
    private static $instance = null;

    /**
     *
     * @static
     */
    public static function get($filePaths = null, $algo = OPENSSL_ALGO_SHA256): Signer
    {
        if (self::$instance && !$filePaths) return self::$instance;

        return (self::$instance = new self($filePaths, $algo));
    }






    private $publicKey, $privateKey, $algo;

    private function __construct($filePaths, $algo)
    {
        // Klíče se přečtou a použijí jakožto stringy (openssl funkce dovolují PEM formátované stringy)
        $this->privateKey = Storage::disk('public')->get($filePaths['private']);
        $this->publicKey = Storage::disk('public')->get($filePaths['public']);
        $this->algo = $algo;
    }








    public function sign($raw)
    {
        if (openssl_sign($raw, $signed, $this->privateKey, $this->algo))
            $signed = base64_encode($signed);
        else
            throw new \Exception('Unable to sign data.');

        return $signed;
    }

    public function verify($data, $signed)
    {
        $result = openssl_verify($data, base64_decode($signed), $this->publicKey, $this->algo);
        if ($result === 0)
            throw new \Exception('Data and sign don\'t match');
        else if ($result === -1)
            throw new \Exception('Unable to decrypt data.');

        return true;
    }
}
