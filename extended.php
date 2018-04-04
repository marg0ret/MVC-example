<?php
/* @var $this ParticipantController */
/* @var $model Participant */

?>
    <p></p>

    <h2>Participant Balance  <?php echo $participant_name ?></h2>

<?php if ( $pw_balance === 0. ) {
    echo '<h3><span id="txt2" style="font-size:1.4em"> You do not have an active account </span></h3><p></p>';
    echo '<h4>If you recently initiated a new account you may have to wait 2 business days for it to be processed</h4>';
}


foreach(Yii::app()->user->getFlashes() as $key => $message) {
    echo '<div class="flash-' . $key . '">' . $message . "</div>\n";
}

Yii::app()->clientScript->registerScript(
    'myHideEffect',
    '$(".flash-success").animate({opacity: 1.0}, 1000).fadeOut(8000);',
    CClientScript::POS_READY
);

$this->widget('booster.widgets.TbExtendedGridView', array(
    'type' => 'striped condensed',
    'dataProvider' => $dataProvider,
    'template' => "{items}",
    'columns' => array(
        array(
            'class' => 'booster.widgets.TbRelationalColumn',
            'name' => 'ticker',
            'header' => 'Ticker',
            'value' => '$data["ticker"]==null ? "MM" : $data["ticker"]',
            'url' => $this->createUrl('participant/book'),
            // 'visible'=>'$data->ticker!=="Total"',
            // 'visible'=>'($data->ticker=="Total")?true:false;'
        ),
        array(
            'name' => 'ticker',
            'header' => 'Investment',
            'value' => '(Investment::model()->findByPK($data["ticker"])->investment)',
        ),
        array(
            'name' => 'shares',
            'header' => 'Shares',
            'value' => function ($data) {
                    if ($data["shares"]===1) return "";
                    if ($data["ticker"]==="POIXX" || $data["ticker"]=="MM") return number_format($data["shares"], 2);
                    if ($data["active"]===0)  return "( ".$data["shares"]. " )";
                    else  return $data["shares"];},
            // 'value' => '$data["shares"]==1 ? "" : $data["shares"]',
            'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')
        ),
        array(
            'name' => 'price',
            'header' => 'Current Price',
            'headerHtmlOptions' => array('style' => 'text-align:right'),
            'value' => function ($data) { if ($data["shares"]===1) return ""; else return "$ " . number_format($data["price"], 2); },
            'htmlOptions' => array('width' => '90px', 'style' => 'text-align:right')
        ),
        array(
            'name' => 'price',
            'header' => "Today's Value",
            'headerHtmlOptions' => array('style' => 'text-align:right'),
            'value' => function ($data) { if ($data["shares"]===1) return ""; else return "$ " . number_format($data["price"]*$data["shares"], 2); },
            'htmlOptions' => array('width' => '90px', 'style' => 'text-align:right')
        ),
        array(
            'name' => 'book',
            'header' => "Book Value",
            'headerHtmlOptions' => array('style' => 'text-align:right'),
            'value' => function ($data) {
                    if ($data["book"]<0.0) return "$ 0.00";
                    elseif ($data["ticker"]==="MM") return "$ " . number_format($data["shares"], 2);
                    else return "$ " . number_format($data["book"], 2); },
            'htmlOptions' => array('width' => '90px', 'style' => 'text-align:right')
        ),
    ),
));

