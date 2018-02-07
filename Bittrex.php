<?php
/**
 * Created by PhpStorm.
 * User: nikos
 * Date: 9/6/2017
 * Time: 3:25 PM
 */

class Bittrex
{
    function __construct()
    {
        $config = json_decode(file_get_contents('./config.json'));
        $this->apiKey = $config->apiKey;
        $this->apiSecret = $config->apiSecret;
    }

    function request($uri, $params = [], $addAuth = false)
    {
        $uri .= '?';
        foreach ($params as $key => $value) {
            $uri .= "&$key=$value";
        }

        $ch = curl_init();

        if ($addAuth) {
            $nonce = time();
            $uri .= "&apikey=$this->apiKey&nonce=$nonce";
            $sign = hash_hmac('sha512', $uri, $this->apiSecret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["apisign:$sign"]);
        }

        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        if (!$response) {
            $error = curl_error($ch);
            fileLog("ERROR while contacting Bittrex: $error");
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        $response = json_decode($response);
        if (!$response->success) {
            fileLog("ERROR Bittrex message: $response->message");
            return false;
        }

        return $response->result;
    }

    function getMarkets()
    {
        fileLog("PROCESS Getting markets");
        $markets = $this->request("https://bittrex.com/api/v1.1/public/getmarketsummaries");

        if (!$markets) {
            return [];
        }

        fileLog("PROCESS Got markets");
        return $markets;
    }

    function getBalances()
    {
        fileLog("PROCESS Getting balances");
        $balances = $this->request("https://bittrex.com/api/v1.1/account/getbalances", [], true);

        if (!$balances) {
            return [];
        }

        fileLog("PROCESS Got balances");
        return $balances;
    }

    function getBalance($marketSymbol)
    {
        fileLog("PROCESS Getting balance [$marketSymbol]");

        $balance = $this->request(
            "https://bittrex.com/api/v1.1/account/getbalance",
            ['currency' => $marketSymbol],
            true
        );

        if (!$balance) {
            return 0;
        }

        // Bittrex returns 0 balance as null
        fileLog("PROCESS Got balance [$marketSymbol]");
        return ($balance->Available === null) ? 0 : $balance->Available;
    }

    function getOpenOrder($market, $type)
    {
        fileLog("PROCESS Getting orders[$market, $type]");

        $orders = $this->request(
            "https://bittrex.com/api/v1.1/market/getopenorders",
            ['market' => $market],
            true
        );

        if (!$orders) {
            // TODO: think! What to do in a get order call fails, while an order exists???
            return null;
        }

        $orders = array_filter($orders, function ($order) use ($type) {
            return $order->OrderType === $type;
        });

        if (count($orders) > 1) {
            fileLog("ATTENTION More than one $type orders for $market");
        }

        fileLog("PROCESS Got orders[$market, $type]");
        return (count($orders)) ? $orders[0] : null;
    }

    function buyLimit($market, $quantity, $rate)
    {
        fileLog("PROCESS Placing order [$market, $quantity, $rate]");

        $order = $this->request(
            "https://bittrex.com/api/v1.1/market/buylimit",
            [
                'market' => $market,
                'quantity' => $quantity,
                'rate' => $rate,
            ],
            true
        );

        if (!$order) {
            return null;
        }

        fileLog("PROCESS Placed order [$market, $quantity, $rate]");
        return $order->uuid;
    }

    function sellLimit($market, $quantity, $rate)
    {
        fileLog("PROCESS Placing order [$market, $quantity, $rate]");

        $order = $this->request(
            "https://bittrex.com/api/v1.1/market/selllimit",
            [
                'market' => $market,
                'quantity' => $quantity,
                'rate' => $rate,
            ],
            true
        );

        if (!$order) {
            return null;
        }

        fileLog("PROCESS Placed order [$market, $quantity, $rate]");
        return $order->uuid;
    }

    function cancelOrder($uuid)
    {
        fileLog("PROCESS Cancelling order [$uuid]");

        $order = $this->request(
            "https://bittrex.com/api/v1.1/market/cancel",
            ['uuid' => $uuid],
            true
        );

        fileLog("PROCESS Cancelled order [$uuid]");
        return $order->uuid;
    }
}
