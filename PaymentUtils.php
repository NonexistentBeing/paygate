<?php

namespace App\Injectables;

use App\Models\orders;
use Carbon\Carbon;
use \Exception;
use Illuminate\Support\Facades\Http;
use App\Injectables\Signer;
use App\Injectables\Constants;

class PaymentUtils
{
    private static $dttmFormat = "YmdHis";
    public $payData;
    public $signature = null;
    public $order = null;
    public $language;

    /**
     * @static
     * @return PaymentUtils vrátí objekt s daty, které simulují real time objednávku
     */
    public static function fake()
    {
        return new self([
            'id' => 456,
            'price' => 1200000,
            'payid' => null,
            'cart' => [
                ['name' => "objednavka", 'quantity' => '1', 'amount' => 600000],
            ],
        ]);
    }

    /**
     * @return string vrátí simulovaný string který se používá na podpis metody pro inicializaci platby mapován na keys
     * merchantId|orderNo|dttm|payOperation|payMethod|totalAmount|currency|closePayment|returnUrl|returnMethod|name|quantity|amount|name|quantity|amount|language
     */
    public function fakeSignature()
    {
        $data = [
            'merchantId' => $this->payData['merchantId'],
            'orderNo' => $this->order['id'],
            'dttm' => self::now(),
            'payOperation' => 'payment',
            'totalAmount' => $this->order['price'],
            'language' => $this->language,
            'payMethod' => 'card',
            'currency' => 'CZK',
            'closePayment' => true,
            'returnUrl' => route('Welcome'),
            'returnMethod' => 'GET',
            'cart' => $this->order['cart'],
        ];

        array_walk_recursive($data, function (&$item, $key) {
            $item = $key;
        });

        $signature = self::signatureEncode($data, $this->payData['templates']['init']);
        $signature = ltrim($signature, '|');
        return $signature;
    }

    /**
     * Funkce používaná pro inicializaci se simulovanými daty
     */
    public function fakeInit()
    {
        $data = [
            'merchantId' => $this->payData['merchantId'],
            'orderNo' => $this->order['id'],
            'dttm' => self::now(),
            'payOperation' => 'payment',
            'totalAmount' => $this->order['price'],
            'language' => $this->language,
            'payMethod' => 'card',
            'currency' => 'CZK',
            'closePayment' => 'true',
            'returnUrl' => route('Welcome'),
            'returnMethod' => 'GET',
            'cart' => $this->order['cart'],
        ];
        $signature = self::signatureEncode($data, $this->payData['templates']['init']);
        $signature = ltrim($signature, '|');
        $data['signature'] = Signer::get()->sign($signature);

        $response = Http::post($this->getUrl('init'), $data);
        $this->handleResponseError($response, $signature);

        return $response;
    }





    /*
     * Static methods to get an instance
     */

    public static function fetchPayment($orderID)
    {
        $order = (array)orders::where('id', '=', $orderID)->first();
        $order['cart'] = [
            ['name' => ucfirst(__('order.order')), 'quantity' => '1', 'amount' => $order['price']],
        ];

        return new self($order);
    }





    /*
     * Important usility functions
     */

    public function __construct($order = null)
    {
        if (!is_array($order) && !is_object($order)) {
            throw new Exception("Order needs to be an object or a string!");
        }

        $this->payData = Constants::$arr;

        Signer::get([
            'private' => 'payment/rsa_FAKE_ID.key',
            'public' => 'payment/rsa_FAKE_ID.pub'
        ]);

        if (is_array($order) || !is_object($order)) {
            $this->order = is_array($order) ? $order : (array)$order;
        }

        /* CZ nebo EN */
        $this->language = $this->payData["languages"][strtolower(app()->getLocale())];
    }

    /**
     * @return string formátovaný string typu YYYY MM DD HH MM SS ("20210121063338")
     */
    public static function now()
    {
        return Carbon::now("Europe/Prague")->format(self::$dttmFormat);
    }

    /**
     * @param string $url - url, kde všechny výrazy budou nahrazeny řetězci z pole $args ("www.{site}.{tld}")
     * @param string[string] $args - pole s řetěci pro nahrazení v $url, funkce používá regex pro nalezení klíčů (['site' => 'google', 'tld' => 'com'])
     * 
     * @return string formátovaný url encoded string, kde {expr} je nahrazeno hodnotou z pole ("www.google.com/")
     */
    public static function encodeURL($url, $args)
    {
        return preg_replace_callback("#\{(.+?)\}#", function ($matches) use ($args) {
            return urlencode($args[$matches[1]]);
        }, $url);
    }

    /**
     * @param string $pathName - název url v souboru constants
     * @param string[string] $data (optional) - data která budou dále použita ve funkci encodeURL
     * 
     * @return string formátovaný url encoded string, kde {expr} je nahrazeno hodnotou z pole ("www.google.com/")
     */
    public function getUrl($pathName, $data = null)
    {
        $url = $this->payData['urls']['base'] . $this->payData['urls'][$pathName];

        return is_array($data) ? self::encodeURL($url, $data) : $url;
    }

    /**
     * Rekurzivní funkce na získání signature pro requesty
     * @param string[string] $data - data pro získání podpisu
     * @param string[string] $keyOrder (optional) - pole polí které udává pořadí a tvar (akceptuje také objekty, používá na ně array cast)
     * @param string $delim (optional) - string používaný na oddělení jednotlivých hodnot, defaults to |
     * 
     * @return string - signature oddělený pomocí delimiteru
     * 
     */


    /*
     * příklad:
     *   $data = ['merchantId' => 123456, 'dttm' => $dt, 'language' => "CZ", 'cart' => [ 'name' => 'a name', 'quantity' => '3', 'amount' => '30000' ], ]
     *   $keyOrder = [['key' => 'merchantId'], ['key' => 'dttm'], ['key' => 'cart', 'values' => [['key' => 'name'], ['key' => 'quantity'], ['key' => 'amount'],]],['key' => 'language']]
     *   návratová hodnota = "123456|20210121184223|a name|3|30000|CZ"
     */

    public static function signatureEncode($data, $keyOrder = null, &$delim = "|")
    {
        if (!is_array($data) && !is_object($data)) throw new \Exception("Data is not an array or an object.");
        if (!is_array($data) && !is_object($data) && $keyOrder !== null) throw new \Exception("KeyOrder is not of type array, object, or null.");
        $data = is_object($data) ? (array)$data : $data;

        $encoded = "";

        if ($keyOrder === null) {
            /* no key order provided */
            foreach ($data as $value) {
                if (is_array($value) || is_object($value)) $encoded .= self::signatureEncode($value, null, $delim);
                else $encoded .= "|" . $value;
            }
        } else {
            /* key order provided */
            foreach ($keyOrder as $item) {
                if (isset($item['values'])) {
                    if (!(isset($data[$item['key']]) && is_array($data[$item['key']]) || is_object($data[$item['key']]))) throw new \Exception('The item $data[\'' . $item['key'] . '\'] needs to be of the type array or object');

                    if (isset($data[$item['key']][0])) {
                        foreach ($data[$item['key']] as $child)
                            $encoded .= '|' . self::signatureEncode($child, $item['values'], $delim);
                    } else {
                        $encoded .= '|' . self::signatureEncode($data[$item['key']], $item['values'], $delim);
                    }
                } else if (isset($data[$item['key']])) {
                    $encoded .= '|' . $data[$item['key']];
                } else throw new \Exception('The key ' . $item['key'] . ' is missing. in ' . print_r($data, true));
            }
        }

        return ltrim($encoded, $delim);
    }

    /**
     * @param string[string] &$data - odkaz na data použitá pro API http request
     * @param string $template - název metody v souboru constants
     * 
     * @return string - funkce vrací podpis pro případné ukládání
     */
    private function sign(&$data, $template)
    {
        $signature = self::signatureEncode($data, $this->payData['templates'][$template]);
        $data['signature'] = Signer::get()->sign($signature);
        return $signature;
    }

    public function handleResponseError($response, $signature)
    {
        if ($response->failed()) throw new Exception("Request failed, code " . $response->status() . ".");
    }

    public function init()
    {
        $data = [
            'merchantId' => $this->payData['merchantId'],
            'orderNo' => $this->order['id'],
            'dttm' => self::now(),
            'payOperation' => 'payment',
            'payMethod' => 'card',
            'currency' => 'CZK',
            'language' => $this->language,
            'totalAmount' => $this->order['price'],
            'closePayment' => 'true',
            'returnUrl' => route('Welcome'),
            'returnMethod' => 'POST',
            'cart' => $this->order['cart'],
        ];

        $signature = $this->sign($data, 'init');

        $response = Http::post($this->getUrl('init'), $data);
        $this->handleResponseError($response, $signature);

        orders::where('id', $this->order['id'])
            ->update(['payid' => $response['payId']]);

        return $response['result'];
    }

    // KOMENTÁŘ
    /**
     * Funkce použivaná pro kontaktování API (metoda echo)
     */

    public function echo()
    {
        $data = [
            'dttm' => self::now(),
            'merchantId' => $this->payData['merchantId'],
        ];

        $signature = $this->sign($data, 'echo');

        // KOMENTÁŘ
        /* v těto chvíli je signature = "FAKE_ID|20210121202648"
         *
         * a $data == [
         *    'dttm' => '20210121202648',
         *    'merchantId' => 'FAKE_ID',
         *    'signature' => Signer->sign('FAKE_ID|20210121202648')
         *  ]
         */

        $url = $this->getUrl('echo', $data);

        // v této chvíli je $url == "https://iapi.iplatebnibrana.csob.cz/api/v1.8/echo/FAKE_ID/20210121200910/as7AokyvkNym2VqkgRAMXSah36k1T4iSkWCVhQ3KK4YwPxzCq5Z4xGhzE8Dtkf7b9wg4HJlo7yf8kkYYCOOEqJBTXcGjUI4iU1vXm8CXuQjHABTaXBAqyEu0Ic21Y5NB0fZZykAYcXCuw6HdfAPxxdlc4V2fS%2FVkK70JJyZcFeMq%2BIP5rlUYAR73nTfgoEgnYAB0zpP9GOZdIH8KZFuqsASD3tKCE%2BTKUhvfJavF7wiGokFc8VbwpM1%2FXgvLfeMzu95CGGawWPL9gwS2TnFuV%2FPMYnm8fOQt%2Bol%2B6OQtv4rVoLZ%2BeJu%2F7Ukm47KPQQjtwDCBleZV7NnoHD7DGZsstQ%3D%3D"

        $response = Http::get($url);

        // response code je 400 

        $this->handleResponseError($response, $signature);

        return $response;
    }

    public function getProcessUrl()
    {
        $data = [
            'merchantId' => $this->payData['merchantId'],
            'payId' => $this->order['payId'],
            'dttm' => self::now()
        ];

        $this->sign($data, 'process');

        return $this->getUrl('process', $data);
    }

    public function refund()
    {
        $data = [
            'merchantId' => $this->payData['merchantId'],
            'payId' => $this->order['payId'],
            'dttm' => self::now(),
        ];

        $signature = $this->sign($data, 'refund');

        $response = Http::put($this->getUrl('refund'), $data);
        $this->handleResponseError($response, $signature);

        if (intval($response['resultCode']) !== 0) throw new Exception($response['resultCode']->body(), $response['resultCode']);
    }
}
