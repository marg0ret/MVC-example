#!/usr/bin/env  php
<?php

/*
 *  $tickers = "SELECT ticker FROM investment";
 */
$tickers = array('GSSIX', 'HACAX', 'IYMIX', 'MWTIX', 'NFJEX',
//  'POIXX',    Money Market - returns current percent not a price
    'PRRIX',
//  'PTTRX',    No longer active
    'RERGX', 'VIMAX', 'VLCAX', 'VSMAX', 'VTIAX');

$conn = new mysqli("xyz.com", "xyz", "xyz", "xyz");
if ( $conn->connect_errno > 0 ) {
    echo "Failed to connect to MySQL: " . $conn->connect_error;
    exit;
}

foreach ( $tickers as $ticker ) {

    $url = "http://www.webservicex.net/stockquote.asmx/GetQuote?symbol=" . $ticker;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    $xmlobj = simplexml_load_string($result);

    $p = xml_parser_create();
    xml_parse_into_struct($p, $xmlobj[0], $vals, $index);
    xml_parser_free($p);

    // $ticker = $vals[2]['value'];
    $price = $vals[3]['value'];

    // print_r($xmlobj[0]);

    $sql = "UPDATE investment SET price=" . $price . " WHERE ticker = '" . $ticker . "'";
    echo "\nSymbol ", $ticker, " Price ", $price, " ", $sql, "\n";

    // $conn->query($sql);
    if ( !$ok = $conn->query($sql) ) {
        echo 'There was an error running the query [' . $conn->error . ']';
    }
}

$sql = " SELECT t.participant_ID, mm_balance, SUM( ROUND(price*shares, 2) ) AS sh_balance FROM participant t, shares s, investment i
                WHERE s.ticker = i.ticker AND s.participant_ID=t.participant_ID GROUP BY t.participant_ID";

if ( !$balance = $conn->query($sql) ) {
    echo 'There was an error running the query [' . $conn->error . ']';
}

while ( $b = $balance->fetch_assoc() ) {
    $sql = "UPDATE participant SET sh_balance=" . $b['sh_balance'] . " WHERE participant_ID = " . $b['participant_ID'];

    echo $sql, "\n";
    if ( !$ok = $conn->query($sql) ) {
        echo 'There was an error running the query [' . $conn->error . ']';
    }
}

$sql = "UPDATE participant SET pw_balance = sh_balance + mm_balance";
if ( !$ok = $conn->query($sql) ) {
    echo 'There was an error running the query [' . $conn->error . ']';
}
$conn->close();

// perl excel writer supports macros, phpexcel doesn't

$x = exec("/usr/bin/perl PATH/excel.pl 2>&1");

