<?php
/* @var $this ParticipantController */
/* @var $model Participant */

?>
    <p></p>

    <h2>Investment Balance  <?php echo $employer_name ?></h2>
    <h4>Click on Ticker for Participant History </h4>
    <h4>Click on Shares for Transaction History </h4>

<?php
$this->widget('booster.widgets.TbExtendedGridView', array(
    // 'filter' => $person,
    'type' => 'striped condensed',
    'dataProvider' => $dataProvider,
    'template' => "{items}",
    'columns' => array(
        array(
            // 'class' => '',
            'class' => 'booster.widgets.TbRelationalColumn',
            'name' => 'ticker',
            'header' => 'Ticker',
            'url' => $this->createUrl('participant/relational'),
            'cacheData' => false,
            'type' => 'raw',
            'cssClass' => 'tbrelational-column',
            // 'afterAjaxUpdate' => 'js:function(tr,rowid,data){
            //    bootbox.alert("I have afterAjax events too! This will only happen once for row with id: "+rowid); }',
        ),
        array(
            'name' => 'ticker',
            'header' => 'Investment',
            'value' => '(Investment::model()->findByPK($data["ticker"])->investment)',
        ),
        array(
            'class' => 'booster.widgets.TbRelationalColumn',
            'name' => 'shares',
            'header' => 'Shares',
            'value' => function ($data) { if ($data["shares"]===1) return ""; elseif ($data["ticker"]==="POIXX") return number_format($data["shares"], 2); else  return $data["shares"];},
            'url' => $this->createUrl('participant/trans'),
            'cacheData' => false,
            'type' => 'raw',
            'cssClass' => 'tbrelational-1',
            // 'afterAjaxUpdate' => 'js:function(tr,rowid,data){
            //    bootbox.alert("I have afterAjax events too! This will only happen once for row with id: "+rowid); }',
            'htmlOptions' => array('width' => '90px', 'style' => ' text-align:right; cursor: pointer; color: #08C;'),
        ),
        array(
            'name' => 'price',
            'header' => 'Current Price',
            'value' => function ($data) { return '$ ' . number_format($data['price'], 2); },
            'htmlOptions' => array('width' => '80px', 'style' => 'text-align:right')
        ),
        array(
            'name' => 'price',
            'header' => 'Current Value',
            // 'value' => function ($data) { if ($data["shares"]===1) return ""; elseif ($data["ticker"]==="POIXX") return number_format($data["shares"], 2); else  return $data["shares"];},
            'value' => function ($data) { return '$ ' . number_format($data['price'] * $data['shares'], 2); },
            'htmlOptions' => array('width' => '90px', 'style' => 'text-align:right')
        ),
        array(
            'name' => 'book',
            'header' => 'Book Value',
            'value' => function ($data) { return '$ ' . number_format($data['book'], 2); },
            'htmlOptions' => array('width' => '90px', 'style' => 'text-align:right')
        ),
    ),
));

