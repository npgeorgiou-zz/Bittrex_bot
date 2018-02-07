<?php

class Strategy
{
    function __construct($dryrun)
    {
        $this->config = json_decode(file_get_contents('./config.json'));
        $this->bittrex = new Bittrex();
        $this->persistence = new Persistence();
        $this->dryrun = $dryrun;
    }

    public function execute()
    {
        $bittrexMarkets = $this->bittrex->getMarkets();

        // I dont want ETH base markets
        $bittrexMarkets = array_filter($bittrexMarkets, function ($bittrexMarket) {
            return startsWith($bittrexMarket->MarketName, 'ETH-') ? false : true;
        });

        // If USDT markets are handled first, I run the risk of selling all my BTC for USDT.
        // Then, I will be unable to buy other coins with BTC in BTC based markets
        usort($bittrexMarkets, function ($a, $b) {
            return startsWith($a->MarketName, 'USDT-') ? 1 : -1;
        });

        array_walk($bittrexMarkets, [$this, 'handle']);
    }

    private function handle($bittrexMarket)
    {
        $marketName = $bittrexMarket->MarketName;
        $closePrice = $bittrexMarket->Last;

        $marketPriceHistory = $this->persistence->updateMarketPrice($marketName, $closePrice);

        // Bail if not enough data to make calculations
        if (count($marketPriceHistory) < $this->config->seriesLimit) {
            fileLog("Not enough series data for $marketName");
            return;
        }

        $fastEma = getEma($marketPriceHistory, $this->config->fastEma);
        $slowEma = getEma($marketPriceHistory, $this->config->slowEma);
        $emaPctDiff = abs($fastEma - $slowEma) * 100 / max($fastEma, $slowEma);

        $buySignal = $fastEma > $slowEma && $emaPctDiff > 1;
        $sellSignal = $fastEma < $slowEma && $emaPctDiff > 1;

        $baseCoin = explode('-', $marketName)[0];
        $quoteCoin = explode('-', $marketName)[1];

        if ($buySignal) {
            fileLog("Buy signal for $marketName [$fastEma, $slowEma, $emaPctDiff]");
            // Maybe a previous sell order is still open
            if ($pairOpenOrder = $this->bittrex->getOpenOrder($marketName, 'LIMIT_SELL')) {
                !$this->dryrun && $this->bittrex->cancelOrder($pairOpenOrder->uuid);
            }

            $available = $this->bittrex->getBalance($baseCoin);
            fileLog("$available $baseCoin available");

            if (
                !$this->bittrex->getOpenOrder($marketName, 'LIMIT_BUY') && // There is no open buy order
                !$this->bittrex->getBalance($quoteCoin) &&                 // Account doesn't have this coin already
                $available > 0                                             // There is base coin available
            ) {
                // How much to buy
                $quantity = $this->calculateAmountToBuy($baseCoin, $quoteCoin, $available, $closePrice);
                !$this->dryrun && $this->bittrex->buyLimit($marketName, $quantity, $closePrice);
                fileLog("Placing buy order for $marketName");
            }
        }

        if ($sellSignal) {
            fileLog("Sell signal for $marketName [$fastEma, $slowEma, $emaPctDiff]");

            // Maybe a previous buy order is still open
            if ($pairOpenOrder = $this->bittrex->getOpenOrder($marketName, 'LIMIT_BUY')) {
                !$this->dryrun && $this->bittrex->cancelOrder($pairOpenOrder->uuid);
            }

            if (
                !$this->bittrex->getOpenOrder($marketName, 'LIMIT_SELL') && // There is no open sell order
                $quantity = $this->bittrex->getBalance($quoteCoin)          // Account has this coin already
            ) {
                // Place sell limit order
                !$this->dryrun && $this->bittrex->sellLimit($marketName, $quantity, $closePrice);
                fileLog("Placing sell order for $marketName");
            }
        }
    }

    private function calculateAmountToBuy($baseCoin, $quoteCoin, $available, $closePrice)
    {
        $baseCoinToSpend = 0;

        // If the market is USDT-BTC, buy for 50%. I want enough BTC so that it can also be allocated in other altcoins
        if ($baseCoin === 'USDT' && $quoteCoin === 'BTC') {
            $baseCoinToSpend = $available / 2;
        } elseif ($baseCoin === 'USDT') {
            // 10 sequential investments, 1000 starting balance. Dropoff:
            // [120, 105.6, 92.93, 81.78, 71.96, 63.33, 55.73, 49.04, 43.16, 37.98]
            $baseCoinToSpend = $available / (12 / 100);
        } else {
            // 40 sequential investments, 1000 starting balance. Dropoff:
            // [30, 29.1, 28.23, 27.38, 26.56, 25.76, 24.99, 24.24, 23.51, 22.81, 22.12, 21.46, 20.82, 20.19, 19.59, 19, 18.43, 17.87, 17.34, 16.82, 16.31, 15.82, 15.35, 14.89, 14.44, 14.01, 13.59, 13.18, 12.79, 12.4, 12.03, 11.67, 11.32, 10.98, 10.65, 10.33, 10.02, 9.72, 9.43, 9.15]
            $baseCoinToSpend = $available * (3 / 100);
        }

        return $baseCoinToSpend / $closePrice;
    }
}
