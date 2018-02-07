<?php
function getEma($series, $period)
{
    $prices = array_map(function ($dateClose) {
        return $dateClose->close;
    }, $series);

    // Php trader extension has a very low decimal precision (3)
    // The smallest decimal that I have seen bittrex to return is e-7 -> 2.9e-7 -> 0.00000029
    // So, 8 decimal places.
    // Multiply with a big number to remove decimals
    $multiplier = 1000 * 1000 * 1000 * 1000;

    $prices = array_map(function ($e) use ($multiplier) {
        return $e * $multiplier;
    }, $prices);

    $emas = trader_ema($prices, $period);
    return end($emas) / $multiplier;
}

function startsWith($haystack, $needle)
{
    // Search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function fileLog($str)
{
    echo($str . PHP_EOL);

    $now = time();
    $date = date('d-m-Y', $now);
    $dateTime = date('d-m-Y h:i:s', $now);

    $filePath = "./history/logs/$date.log";

    if (!file_exists($filePath)) {
        fopen($filePath, 'w');
    }

    $str = $dateTime . '    ' . $str . PHP_EOL;
    file_put_contents($filePath, $str, FILE_APPEND);
}