<?php

namespace App\Injectables;

abstract class Constants
{
    public static $arr = [
        "merchantId" => "FAKE_ID",
        "templates" => [
            "init" => [
                ["key" => "merchantId"],
                ["key" => "orderNo"],
                ["key" => "dttm"],
                ["key" => "payOperation"],
                ["key" => "payMethod"],
                ["key" => "totalAmount"],
                ["key" => "currency"],
                ["key" => "closePayment"],
                ["key" => "returnUrl"],
                [
                    "key" => "cart",
                    "values" => [
                        ["key" => "name"],
                        ["key" => "quantity"],
                        ["key" => "amount"],
                    ],
                ],
                ["key" => "language"],
            ],

            "echo" => [
                ["key" => "merchantId"],
                ["key" => "dttm"],
            ],

            "process" => [
                ["key" => "merchantId"],
                ["key" => "payId"],
                ["key" => "dttm"],

            ],

            "refund" => [
                ["key" => "merchantId"],
                ["key" => "payId"],
                ["key" => "dttm"],
            ],
        ],

        "languages" => [
            "cs" => "CZ",
            "en" => "EN",
        ],

        "urls" => [
            "base" => "https://iapi.iplatebnibrana.csob.cz/api/v1.8",
            "init" => "/payment/init",
            "process" => "/payment/process/{merchantId}/{payId}/{dttm}/{signature}",
            "status" => "/payment/status/{merchantId}/{payId}/{dttm}/{signature}",
            "refund" => "/payment/refund",
            "echo" => "/echo/{merchantId}/{dttm}/{signature}",
        ],

    ];
}
