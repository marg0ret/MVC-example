<?php
/* @var $this ParticipantCntroller */
/* @var $model Participant */

?>
    <p></p>

    <h2>Participant Balance  <?php echo $participant_name ?></h2>

<?php if ( $participant_name === "" )
    echo '<h3><span id="txt2" style="font-size:1.4em"> You do not have an active account </span></h3>';
else

// echo "<pre><code>"; var_dump($piedata); echo "<br>";
$pie = array(array("Ticker", "%"));
foreach ( $piedata as $p ) {
    $pie [] = array($p['ticker'], (int)$p['percentage']);
}

$this->widget('ext.Hzl.google.HzlVisualizationChart', array(
        'visualization' => 'PieChart',
        'data' => $pie,
        'options' => array('title' => 'Shares Allocation'))
);

$pie = array(array("Ticker", "Value"));
foreach ( $piedata as $p ) {
    $pie [] = array($p['ticker'], (int)$p['value']);
}

$this->widget('ext.Hzl.google.HzlVisualizationChart', array(
        'visualization' => 'PieChart',
        'data' => $pie,
        'options' => array('title' => 'Shares Value'))
);


$tickers = array();
$line["0-0"][0] = array();
$line["0-0"][0] = "0";
foreach ( $linedata as $l ) {
    $ym = $l['year'] . "-" . $l['month'];
    $line [$ym] = array($l['month']);
    $line[$ym][1] = 0.;
    $line[$ym][2] = 0.;
    $line[$ym][3] = 0.;
    $line[$ym][4] = 0.;
    $tickers[$l['ticker']] = $l['ticker'];
}

$tick = array();
$i=1;
foreach ( $tickers as $t ) {
    $line["0-0"][$i++] = $t;
    $tick[] = $t;
}

foreach ( $linedata as $l ) {
    $i = 0;
    $ym = $l['year'] . "-" . $l['month'];
    if ($ym === "0-0") continue;
    foreach ( $tick as $t ) {
        if ( $l['ticker'] === $t ) {

            $line[$ym][$i+1] = $l['running']*1.;
            // echo "\n",$ym, " ", $i, " if ", $l['ticker'], " === ", $t, "\n";
            // echo $ym, " ", $i, "  ", $line[$ym][$i+1], " ", $l['shares'], " ", $line[$ym][$i+1];
        }
        $i++;
    }
}

$data = array();
foreach ( $line as $l ) {
    $data[] = $l;
}
//  echo "<pre><code>"; print_r($data); die;
//  echo "<pre><code>"; var_dump($data); die;


$this->widget('ext.Hzl.google.HzlVisualizationChart', array('visualization' => 'LineChart',
//      'data' => array(
//          array('0', 'VIMAX', 'VLMAX', 'VSMAX', 'VTIAX'),
//          array('1', 8.45, 26.96, 21.84, 41.55),
//          array('3', 1.02, 3.46, 2.64, 5.6),
//          array('4', .7, 2.23, 1.8, 3.4),
//          array('5', .69, 2.2, 1.8, 3.3),
//      ),
        'data' => $data,
        'options' => array(
            'title' => 'Shares History',
            // 'titleTextStyle' => array('color' => '#FF0000'),
            'vAxis' => array(
                'title' => 'Shares',
                'gridlines' => array(
                    'color' => 'transparent' //set grid line transparent
                )),
            'hAxis' => array('title' => 'Month'),
            'curveType' => 'function', //smooth curve or not
            'legend' => array('position' => 'right'),

            'width' => 500,
            'height' => 320,
        ))
);
