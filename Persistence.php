<?php

class Persistence
{
    function __construct()
    {
        $this->filepath = './history/data.json';
        $this->config = json_decode(file_get_contents('./config.json'));
    }

    function getMarketPriceHistory($name)
    {
        $allMarkets = json_decode(file_get_contents($this->filepath));

        if (!property_exists($allMarkets, $name)) {
            return $this->saveMarketPriceHistory($name, []);
        }

        return $allMarkets->$name;
    }

    function updateMarketPrice($name, $closePrice)
    {
        $prices = $this->getMarketPriceHistory($name);

        // Update price for market
        $prices[] = (object)[
            'date' => date('d-m-Y h:i:s', time()),
            'close' => $closePrice
        ];

        if (count($prices) > $this->config->seriesLimit) {
            array_shift($prices);
        }

        return $this->saveMarketPriceHistory($name, $prices);
    }

    function saveMarketPriceHistory($name, $prices)
    {
        $allMarkets = json_decode(file_get_contents($this->filepath));
        $allMarkets->$name = $prices;

        fileLog("Saving data for $name");
        file_put_contents($this->filepath, json_encode($allMarkets, JSON_PRETTY_PRINT));

        return $this->getMarketPriceHistory($name);
    }
}
