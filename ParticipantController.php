<?php

/**
 * Handle participant events.
 *
 * PHP version 5
 *
 * @category   Controller
 */

/**
 * ParticipantController extends Controller
 */
class ParticipantController extends Controller
{
    /**
     * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
     * using two-column layout. See 'protected/views/layouts/column2.php'.
     */
    public $layout = '//layouts/column2';

    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'accessControl', // perform access control for CRUD operations
            'postOnly + delete', // we only allow deletion via POST request
            // 'booster.filters.BoosterFilter - delete',
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array('allow', // allow all users to perform 'index' and 'view' actions
                'actions' => array('index', 'partAutocomplete'),
                'users' => array('*'),
            ),
            array('allow', // allow authenticated user to perform 'create' and 'update' actions
                'actions' => array('balance', 'extended', 'relational', 'book', 'chart'),
                'users' => array('@'),
            ),
            array('allow', // allow authenticated user to perform 'create' and 'update' actions
                'actions' => array('admin', 'extended', 'baextended', 'relational', 'book', 'chart'),
                'users' => array('ba'),
            ),
            array('allow', // allow admin user to perform 'admin' and 'delete' actions
                'actions' => array('create', 'update', 'admin', 'delete', 'trans',
                    'baextended', 'tdate','view', 'loadpart'),
                'users' => array('molly', 'gail'),
            ),
            array('deny', // deny all users
                'users' => array('*'),
            ),
        );
    }

    /**
     * Displays a particular model.
     * @param integer $id the ID of the model to be displayed
     */
    public function actionView($id)
    {
        $this->render('view', array(
            'model' => $this->loadModel($id),
        ));
    }

    /**
     * Creates a new model.
     * Create Initial transaction for  money market balance
     * Check if there is > 2500 and create buy transaction
     * If creation is successful, the browser will be redirected to the 'view' page.
     */
    public function actionCreate()
    {
        $model = new Participant();

        // Uncomment the following line if AJAX validation is needed
        // $this->performAjaxValidation($model);

        // echo "<pre><code> Post "; var_dump($_POST); echo "</pre></code>"; die;

        if ( isset($_POST['Participant']) ) {

            $model->attributes = $_POST['Participant'];
            $model->pw_balance = 0.00;
            $model->mm_balance = $_POST['Participant']['mm_balance'];
            $model->sh_balance = 0.00;
            $balance = $_POST['Participant']['mm_balance'];
            if ( $balance > 2500.01 )
                $model->mm_balance = 2500.00;

            $shares = $_POST['Shares'];
            $total_percent = 0.0;
            foreach ( $shares as $s ) {
                if ( !empty($s['ticker']) ) {
                    $total_percent += $s['percentage'];
                }
            }
            // TODO if $total_percent != 100

            if ( $model->validate() ) {
                try {
                    if ( $balance > 2500.001 ) {
                        $model->pending = 1;
                    }
                    $model->save();
                } catch (CDbException $e) {
                    $model->addError(null, $e->getMessage());
                    $msg = $model->getErrors();
                    return true;
                }
                $participant_ID = $model->participant_ID;

                $transact = new Transaction();
                $transact->shares_ID = 0;
                $transact->type = 'Initial';
                $transact->participant_ID = $model->participant_ID;
                $transact->employer_ISD = $model->employer_ISD;
                $transact->dollars = $balance;
                $transact->cost_per_share = 1.0;
                $transact->shares = 0;
                $transact->trade_date = date("Y-m-d");
                $transact->settle_date = date("Y-m-d");
                $transact->status = 'Settled';
                $transact->save();

                $tXc = new Txc();
                $tXc->transaction_ID = $transact['transaction_ID'];
                // $tXc->contribution_ID = $model['contribution_ID'];
                $tXc->save();

                foreach ( $shares as $s ) {
                    if ( !empty($s['ticker']) ) {
                        $shares = new Shares();
                        $shares->participant_ID = $participant_ID;
                        $shares->employer_ISD = $model['employer_ISD'];
                        $shares->ticker = $s['ticker'];
                        $shares->shares = 0.00;
                        $shares->percentage = $s['percentage'];
                        $shares->save();
                        // print_r($percentage->getErrors());
                    }
                }

                if ( $balance > 2500.001 ) {
                    $amount = $balance - 2500.000;

                    $connection = Yii::app()->db;
                    $query = 'SELECT SUM(percentage) FROM shares WHERE active = 1 AND participant_ID=' . $model->participant_ID;
                    $command = $connection->createCommand($query);
                    $exists = $command->queryScalar();
                    if ( empty($exists) || $exists !== 100.000 )
                        throw new CHttpException(404, 'Fix share percentage record(s) for Participant.');

                    $connection = Yii::app()->db;
                    $query = 'SELECT * FROM shares
                               WHERE  active = 1 AND participant_ID=' . $model->participant_ID .
                        ' AND  employer_ISD=' . $model->employer_ISD;
                    $command = $connection->createCommand($query);
                    $percentage = $command->queryAll();

                    $transact = new Transaction();
                    $transact->shares_ID = 0;
                    $transact->type = 'Initial';
                    $transact->participant_ID = $model->participant_ID;
                    $transact->employer_ISD = $model->employer_ISD;
                    $transact->dollars = -$amount;
                    $transact->cost_per_share = 1.00;
                    $transact->shares = 0.0;
                    $transact->trade_date = date("Y-m-d");
                    $transact->settle_date = date("Y-m-d");
                    $transact->status = 'Settled';
                    $transact->save();

                    $tXc = new Txc();
                    $tXc->transaction_ID = $transact['transaction_ID'];
                    // $tXc->contribution_ID = $model['contribution_ID'];
                    $tXc->save();

                    foreach ( $percentage as $p ) {

                        $transact = new Transaction();
                        $transact->shares_ID = $p['shares_ID'];
                        $transact->type = 'Initial';
                        $transact->participant_ID = $model->participant_ID;
                        $transact->employer_ISD = $model->employer_ISD;
                        $transact->dollars = $amount * $p['percentage'] / 100.;
                        $cost = Investment::model()->findByPK($p['ticker']);
                        $transact->cost_per_share = $cost['price'];
                        $transact->shares = $transact->dollars / $transact->cost_per_share;
                        $transact->trade_date = '0000-00-00';
                        $transact->settle_date = '0000-00-00';
                        $transact->status = 'New';
                        $transact->save();

                        $tXc = new Txc();
                        $tXc->transaction_ID = $transact['transaction_ID'];
                        // $tXc->contribution_ID = $model['contribution_ID'];
                        $tXc->save();
                    }
                }

                $this->redirect(array('admin'));
                // echo CJSON::encode(array( 'div' => $this->renderPartial('//participant/admin', array('model'=>new Participant), false, true), ));
                Yii::app()->end();

                return true;
            } else {
                $msg = $model->getErrors(); print_r($msg); die;
                echo CJSON::encode(array('status' => 'error', 'error' => 'error', 'msg' => $msg));
                Yii::app()->end();

                return true;
            }
        }

        $this->render('create', array(
            'model' => $model,
        ));
    }


    /**
     * Display a participant dropdown and pick an ID if called by BA.
     * This lets BAs see what a participant would see.
     * The BA sign on is half way between admin and user.
     * @param integer $id the ID of the model to be displayed
     */
    public function actionBaextended()
    {
        $model = new Participant();
        if ( isset($_POST['Participant']['participant_ID'] )) {
            $model->participant_ID = (int)$_POST['Participant']['participant_ID'];
            $this->redirect(array('extended', 'id' => $model->participant_ID));
        }

        $this->render('baextended', array( 'model' => $model, ));
    }

    /**
     * Called from the participant admin page when clicking on participant name.
     * Displays the number of shares, current price and value by ticker for that participant
     * Clicking ticker creates a drill down of the Book screen to show transaction detail.
     */
    public function actionExtended()
    {
        if ( Yii::app()->user->getRole() < 3 ) $participant_ID = Yii::app()->user->getPartID();
        if ( Yii::app()->user->getRole() >= 3 ) {
            $participant_ID = Yii::app()->user->getPartID();
            // $participant_ID = (int)$_GET['id'];
        }

        if ( !isset($participant_ID) ) {
            $error = 'You do not have an account yet';
            $participant_ID = 0;
            $participant_name = "";
            $pw_balance = 0.;
        } else {
            $participant_name = Yii::app()->db->createCommand(' SELECT participant_name FROM participant WHERE participant_ID = ' . $participant_ID)->queryScalar();
            $pw_balance = Yii::app()->db->createCommand(' SELECT pw_balance FROM participant WHERE participant_ID = ' . $participant_ID)->queryScalar();
        }
        $count = Yii::app()->db->createCommand('SELECT COUNT(*) FROM transaction WHERE participant_ID =' . $participant_ID)->queryScalar();
        $sql = 'SELECT * FROM (SELECT shares_ID as sid, s.participant_id, s.ticker, active, i.price, s.shares,
                       CONCAT(s.participant_id,"-",s.ticker) AS id
                       , ( SELECT sum(dollars) FROM transaction t WHERE t.shares_ID = sid ) AS book
                      FROM shares s
                 JOIN investment i ON s.ticker = i.ticker
                WHERE s.participant_ID = ' . $participant_ID .
            ' UNION
               SELECT 0 AS sid, participant_ID, "MM" as ticker , 0 AS active, 1., mm_balance, CONCAT(participant_ID,"-","MM"), 1.
                FROM participant WHERE participant_ID = ' . $participant_ID .
            ' UNION
               SELECT 9999 AS sid, a.pid, "Total" as ticker , 0 AS active, 0, 1,
                      CONCAT(a.pid,"-","Total"), SUM(x)
                 FROM ( SELECT s.shares_ID as sid, s.participant_ID AS pid, "Total", 0 AS active,
                       ( SELECT sum(dollars) FROM transaction t WHERE t.shares_ID = sid ) AS x
                         FROM shares s, transaction t
                        WHERE s.participant_ID = ' . $participant_ID .
            ' AND s.shares_ID = t.shares_ID
            UNION SELECT 9999 AS sid, participant_ID AS pid, "Total",  0 AS active, mm_balance AS x  FROM participant
                   WHERE participant_ID = ' . $participant_ID .
            ' GROUP BY sid
        ) a ) b
     ORDER BY sid';

        $dataProvider = new CSqlDataProvider($sql, array(
            'totalItemCount' => $count,
            'sort' => array('attributes' => array('ticker', 'transaction_ID'),),
            'pagination' => array('pageSize' => 30,),
        ));

        $this->render('extended', array('id' => $participant_ID,
            'dataProvider' => $dataProvider, // pass in the data provider
            'participant_name' => $participant_name,
            'pw_balance' => $pw_balance,
        ));
    }

    /**
     * Google chart on the user's menu of their shares.
     * Calls the chart view and uses Hzl in extensions.
     */
    public function actionChart()
    {
        $participant_ID = (int)$_GET['id'];
        if ( Yii::app()->user->getRole() < 3 ) $participant_ID = Yii::app()->user->getPartID();

        if ( !isset($participant_ID) ) {
            $error = 'You do not have an account yet';
            $participant_ID = 0;
            $participant_name = "";
        } else {
            $participant_name = Yii::app()->db->createCommand(' SELECT participant_name FROM participant WHERE participant_ID = ' . $participant_ID)->queryScalar();
        }

        $count = Yii::app()->db->createCommand('SELECT COUNT(*) FROM transaction WHERE participant_ID =' . $participant_ID)->queryScalar();
        $sql = 'SELECT shares_ID as sid, s.participant_id, s.ticker, i.price, s.shares,
                       CONCAT(s.participant_id,"-",s.ticker) AS id
                       , ( SELECT cost_per_share FROM transaction t WHERE t.shares_ID = sid
                             AND trade_date = ( SELECT max(trade_date) FROM transaction t
                                               WHERE t.shares_ID = sid) ) AS book
                      FROM shares s
                 JOIN investment i ON s.ticker = i.ticker
                WHERE active = 1 AND s.participant_ID = ' . $participant_ID .
            ' ORDER BY sid';

        $piedata = Yii::app()->db->createCommand('SELECT s.ticker, percentage, shares, shares*price AS value
                  FROM shares s, investment i WHERE s.ticker = i.ticker
                   AND participant_ID = ' . $participant_ID)->queryAll();

        $linedata = Yii::app()->db->createCommand('SELECT year(trade_date) as year, month(trade_date) as month, s.ticker, t.shares,
         t.transaction_ID AS id, t.trade_date AS td, ticker, s.shares_ID AS sid, s.shares as total,
         (SELECT sum(t.shares) FROM transaction t WHERE participant_ID = ' . $participant_ID .
            ' AND trade_date <= td AND shares_ID = sid ) AS running
        FROM transaction t
        JOIN shares s ON ( t.shares_ID = s.shares_ID)
                   WHERE active = 1 AND s.participant_ID = ' . $participant_ID .
            ' GROUP BY s.ticker, year, month')->queryAll();
        // GROUP BY month, ticker;


        $dataProvider = new CSqlDataProvider($sql, array(
            'totalItemCount' => $count,
            // 'sort' => array('attributes' => array('ticker', 'transaction_ID'),),
            // 'pagination' => array('pageSize' => 30,),
        ));

        $this->render('chart', array('id' => $participant_ID,
            'dataProvider' => $dataProvider, // pass in the data provider
            'piedata' => $piedata,
            'linedata' => $linedata,
            'data' => $dataProvider,
            'participant_name' => $participant_name,
        ));
    }

    /**
     * Called from the participant extended page when clicking on ticker.
     * Extended displays the number of shares, current price and value
     * Book shows transaction type, date, shares, dollars, running value.
     */
    public function actionBook()
    {
        $id = explode("-", $_GET['id']);
        $participant_ID = $id[0];
        $ticker = $id[1];
        // echo "Relat ", $id, $employer_ISD, $ticker;

        $count = Yii::app()->db->createCommand(' SELECT COUNT(*) FROM participant p
                     JOIN shares s ON p.participant_ID = s.participant_ID
                     WHERE p.participant_ID = ' . $participant_ID .
            '   AND s.ticker = "' . $ticker . '"')->queryScalar();

        $sql = 'SELECT t.transaction_ID AS id, t.trade_date AS td, ticker, t.*, s.shares_ID AS sid, s.shares as total, p.participant_name, s.active,
                       (SELECT sum(t.shares) FROM transaction t
                         WHERE participant_ID = ' . $participant_ID .
            ' AND trade_date <= td AND shares_ID = sid ) AS running,
          (SELECT sum(t.dollars) FROM transaction t
            WHERE participant_ID = ' . $participant_ID .
            ' AND trade_date <= td AND shares_ID = sid ) AS book
    FROM transaction t
    JOIN participant p ON  t.participant_ID =  p.participant_ID
    JOIN shares s ON ( t.shares_ID = s.shares_ID AND s.ticker = "' . $ticker . '" )
                WHERE t.participant_ID =' . $participant_ID . ' ORDER BY trade_date DESC';

        // echo "<pre><code>"; var_dump( $sql ); die;

        $gridDataProvider = new CSqlDataProvider($sql, array(
            'totalItemCount' => $count,
            // 'sort' => array('attributes' => array('participant_name'),),
            'pagination' => array('pageSize' => 30,),
        ));

        $gridColumns = array(
            array('name' => 'type', 'header' => 'Type',),
            array('name' => 'trade_date', 'header' => 'Trade Date',),
            array('name' => 'shares', 'header' => 'Shares', 'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
            array('name' => 'cost_per_share', 'header' => 'Cost Per Share', 'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
            array('name' => 'running', 'header' => 'Book',
                // 'value' => function ($data) { return " $ " . number_format($data['price'] * $data['shares'], 2); },
                'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
        );

        $this->renderPartial('_book', array(
            'id' => Yii::app()->getRequest()->getParam('id'),
            'gridDataProvider' => $gridDataProvider,
            'gridColumns' => $gridColumns,
        ));
    }


    /**
     * Updates a participant record.
     * Chance to add up to 6 investments and percentages
     * If balance > 2500 initiates buy transaction.
     * @param integer $id the ID of the model to be updated
     */
    public function actionUpdate($id)
    {
        $model = $this->loadModel($id);

        // Uncomment the following line if AJAX validation is needed
        // $this->performAjaxValidation($model);


        if ( isset($_POST['Participant']) ) {

            // if ( $_POST['yt0'] == "Save changes" ) { }
            if ( $_POST['yt1'] === "No changes were made" ) {
                $this->redirect(array('admin'));
            }

            $model->attributes = $_POST['Participant'];
            if ( !empty ( $_POST['Participant']['pw_balance'] ))
                $model['pw_balance'] = $_POST['Participant']['pw_balance'];
            $model['mm_balance'] = $_POST['Participant']['mm_balance'];
            if ( !empty( $_POST['Participant']['sh_balance']) )
                $model['sh_balance'] = $_POST['Participant']['sh_balance'];
            // echo "<pre><code>"; var_dump( $model ); die;

            if ( !$model->validate() ) {
                $msg = $model->getErrors();
                // print_r($msg); die;
                echo CJSON::encode(array('status' => 'error', 'error' => 'error', 'msg' => $msg));
                Yii::app()->end();
                return true;
            }
            $balance = $model->mm_balance;
            if ( $balance > 2500.01 )
                $model->mm_balance = 2500.00;
            $model->save();
            $participant_ID = $model->participant_ID;

            if ( !isset ($_POST['Shares']) ) {
                return true;
            }
            $shares = $_POST['Shares'];
            // echo "<pre><code>"; var_dump($percentages); echo "</pre></code>";
            $total_percent = 0.0;
            foreach ( $shares as $s ) {
                if ( !empty($s['ticker']) ) {
                    $total_percent += $s['percentage'];
                }
            }
            /** @TODO if $total_percent != 100 */


            $transact = new Transaction();
            $transact->shares_ID = 0;
            $transact->type = 'Initial';
            $transact->participant_ID = $model->participant_ID;
            $transact->employer_ISD = $model->employer_ISD;
            $transact->dollars = $balance;
            $transact->cost_per_share = 1.0;
            $transact->shares = 0;
            $transact->trade_date = date("Y-m-d");
            $transact->settle_date = date("Y-m-d");
            $transact->status = 'Settled';
            $transact->save();

            $tXc = new Txc();
            $tXc->transaction_ID = $transact['transaction_ID'];
            // $tXc->contribution_ID = $model['contribution_ID'];
            $tXc->save();

            foreach ( $shares as $s ) {
                if ( !empty($s['ticker']) ) {

                    $shares = Shares::model()->findBySql('SELECT * FROM shares WHERE participant_ID = ' . $model->participant_ID .
                        ' AND  ticker="' . $s['ticker'] . '"');
                    if ( empty($shares) ) $shares = new Shares();

                    $shares->participant_ID = $participant_ID;
                    $shares->employer_ISD = $model['employer_ISD'];
                    $shares->ticker = $s['ticker'];
                    $shares->shares = 0.00;
                    $shares->percentage = $s['percentage'];
                    $shares->save();
                    // print_r($percentage->getErrors());
                }
            }

            if ( $balance > 2500.001 ) {
                $amount = $balance - 2500.000;

                $connection = Yii::app()->db;
                $query = 'SELECT SUM(percentage) FROM shares WHERE active = 1 AND participant_ID=' . $model->participant_ID;
                $command = $connection->createCommand($query);
                $exists = $command->queryScalar();
                if ( empty($exists) || $exists !== 100.000 )
                    throw new CHttpException(404, 'Fix share percentage record(s) for Participant.');

                $connection = Yii::app()->db;
                $query = 'SELECT * FROM shares
                               WHERE  active = 1 AND participant_ID=' . $model->participant_ID .
                    ' AND  employer_ISD=' . $model->employer_ISD;
                $command = $connection->createCommand($query);
                $percentage = $command->queryAll();

                $transact = new Transaction();
                $transact->shares_ID = 0;
                $transact->type = 'Initial';
                $transact->participant_ID = $model->participant_ID;
                $transact->employer_ISD = $model->employer_ISD;
                $transact->dollars = -$amount;
                $transact->cost_per_share = 1.00;
                $transact->shares = 0.0;
                $transact->trade_date = date("Y-m-d");
                $transact->settle_date = date("Y-m-d");
                $transact->status = 'Settled';
                $transact->save();

                $tXc = new Txc();
                $tXc->transaction_ID = $transact['transaction_ID'];
                $tXc->save();

                foreach ( $percentage as $p ) {

                    $transact = new Transaction();
                    $transact->shares_ID = $p['shares_ID'];
                    $transact->type = 'Initial';
                    $transact->participant_ID = $model->participant_ID;
                    $transact->employer_ISD = $model->employer_ISD;
                    $transact->dollars = $amount * $p['percentage'] / 100.;
                    $cost = Investment::model()->findByPK($p['ticker']);
                    $transact->cost_per_share = $cost['price'];
                    $transact->shares = $transact->dollars / $transact->cost_per_share;
                    $transact->trade_date = '0000-00-00';
                    $transact->settle_date = '0000-00-00';
                    $transact->status = 'New';
                    $transact->save();

                    $tXc = new Txc();
                    $tXc->transaction_ID = $transact['transaction_ID'];
                    // $tXc->contribution_ID = $model['contribution_ID'];
                    $tXc->save();
                }
            }

            $this->redirect(array('admin'));
            // echo CJSON::encode(array( 'div' => $this->renderPartial('//participant/admin', array('model'=>new Participant), false, true), ));
            Yii::app()->end();

            return true;
        }

        $this->render('update', array(
            'model' => $model,
        ));
    }

    /**
     * Deletes a particular model.
     * If deletion is successful, the browser will be redirected to the 'admin' page.
     * @param integer $id the ID of the model to be deleted
     */
    public function actionDelete($id)
    {
        $this->loadModel($id)->delete();

        // if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
        if ( !isset($_GET['ajax']) )
            $this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
    }

    /**
     * Lists all models.
     */
    public function actionIndex()
    {
        $dataProvider = new CActiveDataProvider('Participant');
        $this->render('index', array(
            'dataProvider' => $dataProvider,
        ));
    }

    /**
     * Investment Balance by employer.
     * Uses booster.widgets.TbExtendedGridView
     * click ticker calls relational for drop down summary of participants
     * click Shares calls trans for drop down summary of transactions.
     */
    public function actionBalance()
    {
        $employer_ISD = (int)$_GET['id'];
        $employer_name = Yii::app()->db->createCommand(' SELECT employer_name FROM employer WHERE employer_ISD = ' . $employer_ISD)->queryScalar();
        $count = Yii::app()->db->createCommand(' SELECT COUNT(*) FROM participant p, shares s
                                                  WHERE p.participant_ID = s.participant_ID
                                                    AND p.employer_ISD = ' . $employer_ISD)->queryScalar();

        $sql = 'SELECT sid , employer_ISD, ticker, price, sum(shares) AS shares, id, sum(book) AS book FROM (
    SELECT s.shares_ID as sid , s.employer_ISD, s.ticker, i.price,
	       CONCAT(s.employer_ISD,"-",s.ticker) AS id,
           (SELECT SUM( t.shares ) FROM transaction t
             WHERE employer_ISD = ' . $employer_ISD . ' AND shares_ID = sid ) AS shares,
	       (SELECT sum(t.dollars) FROM transaction t
             WHERE employer_ISD = ' . $employer_ISD . ' AND shares_ID = sid ) AS book
	FROM shares s
    JOIN investment i ON s.ticker = i.ticker
    WHERE s.employer_ISD = ' . $employer_ISD . '
	GROUP BY sid ) as x group by ticker';

        $dataProvider = new CSqlDataProvider($sql, array(
            'totalItemCount' => $count,
            'sort' => array('attributes' => array('shares_ID',),),
            'pagination' => array('pageSize' => 30,),
        ));

        $this->render('balance', array(
            'dataProvider' => $dataProvider, // pass in the data provider
            'employer_name' => $employer_name,
            'isd' => $employer_ISD,
        ));
    }

    /**
     * called by Balance when click ticker for drop down summary by participant.
     * Summary includes participant name, shares current price, current value.
     */
    public function actionRelational()
    {
        $id = explode("-", $_GET['id']);
        $employer_ISD = $id[0];
        $ticker = $id[1];
        // echo "Relat ", $id, $employer_ISD, $ticker; die;

        $count = Yii::app()->db->createCommand(' SELECT COUNT(*) FROM participant p
                                                 JOIN shares s ON p.participant_ID = s.participant_ID
                                                  AND p.employer_ISD = ' . $employer_ISD)->queryScalar();

        $sql = 'SELECT p.participant_name, p.employer_ISD, s.ticker, i.price, s.shares,
                       CONCAT(s.participant_ID,"-",s.ticker) AS id
                  FROM participant p
                  JOIN shares s     ON p.participant_ID = s.participant_ID
                  JOIN investment i ON s.ticker = i.ticker
                 WHERE p.employer_ISD = ' . $employer_ISD .
            '   AND s.ticker = "' . $ticker . '"';

        $gridDataProvider = new CSqlDataProvider($sql, array(
            'totalItemCount' => $count,
            // 'sort' => array('attributes' => array('participant_name'),),
            'pagination' => array('pageSize' => 30,),
        ));

        $gridColumns = array(
            array('name' => 'participant_name', 'header' => 'Participant',),
            array('name' => 'shares', 'header' => 'Shares', 'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
            array('name' => 'price', 'header' => 'Current Price', 'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
            array('name' => 'value', 'header' => 'Value',
                'value' => function ($data) { return " $ " . number_format($data['price'] * $data['shares'], 2); },
                'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
        );

        $this->renderPartial('_relational', array(
            'id' => Yii::app()->getRequest()->getParam('id'),
            'gridDataProvider' => $gridDataProvider,
            'gridColumns' => $gridColumns,
        ), false, true);
    }

    /**
     * called by Balance when click shares for drop down summary.
     * Summary includes transaction type, date, cost, value status.
     */
    public function actionTrans()
    {
        /** @var string $id Contains both the employer ISD and ticker for drill down table */
        $id = explode("-", $_GET['id']);
        $employer_ISD = (int)$id[0];
        $ticker = $id[1];

        $count = Yii::app()->db->createCommand(' SELECT COUNT(*) FROM transaction t, shares s
                     WHERE t.employer_ISD = ' . $employer_ISD .
            ' AND t.shares_ID = s.shares_ID
              AND s.ticker = "' . $ticker . '"')->queryScalar();
        $in = Yii::app()->db->createCommand('SELECT shares_ID FROM shares where ticker = "' . $ticker . '" AND employer_ISD = ' . $employer_ISD)->queryAll();

        $sql = 'SELECT t.trade_date AS td, ticker, type, t.shares, t.status, s.shares_ID AS sid,
                       CONCAT(s.employer_ISD,"_",s.ticker,"_",trade_date) AS id,
          (SELECT sum(t.shares) FROM transaction t WHERE t.employer_ISD = ' . $employer_ISD .
            '      AND trade_date = td AND t.shares_ID IN (';
        $j = 0;
        foreach ( $in as $i ) {
            if ( $j++ == 0 ) $sql .= $i["shares_ID"]; else $sql .= "," . $i["shares_ID"];
        }
        $sql .= ')) AS today,
          (SELECT sum(t.shares) FROM transaction t WHERE t.employer_ISD = ' . $employer_ISD .
            '      AND trade_date <= td AND t.shares_ID IN (';
        $j = 0;
        foreach ( $in as $i ) {
            if ( $j++ == 0 ) $sql .= $i["shares_ID"]; else $sql .= "," . $i["shares_ID"];
        }
        $sql .= ') ) AS todate,
          (SELECT sum(t.dollars) FROM transaction t WHERE t.employer_ISD = ' . $employer_ISD .
            '      AND trade_date <= td AND t.shares_ID IN (';
        $j = 0;
        foreach ( $in as $i ) {
            if ( $j++ == 0 ) $sql .= $i["shares_ID"]; else $sql .= "," . $i["shares_ID"];
        }
        $sql .= ') ) AS book, cost_per_share
    FROM transaction t
    JOIN shares s ON ( t.shares_ID = s.shares_ID AND active = 1 AND s.ticker = "' . $ticker . '" )
    WHERE t.employer_ISD = ' . $employer_ISD . ' GROUP BY td ORDER BY td DESC';

        // echo "<pre><code>"; var_dump($in); var_dump( $sql ); die;

        $gridDataProvider = new CSqlDataProvider($sql, array(
            'totalItemCount' => $count,
            // 'sort' => array('attributes' => array('trade_date'),),
            'pagination' => array('pageSize' => 30,),
        ));

        $gridColumns = array(
            array('name' => 'type', 'header' => 'Type',),
            array('name' => 'shares', 'header' => 'Shares', 'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
            array('name' => 'cost_per_share', 'header' => 'Current Price', 'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
            array('name' => 'dollars', 'header' => 'Dollars',
                'value' => function ($data) { return " $ " . number_format($data['dollars'], 2); },
                'htmlOptions' => array('width' => '90px', 'style' => 'text-align:right')),
        );

        $this->renderPartial('_trans', array(
            'id' => Yii::app()->getRequest()->getParam('id'),
            'gridDataProvider' => $gridDataProvider,
            'gridColumns' => $gridColumns,
        ), false, true);
    }

    /**
     * called by Trans, which is called by balance.
     * Tdate called by click trade date creates drill down summary.
     * Summary includes participant, transaction type, shares, cost, value, date.
     */
    public function actionTdate()
    {
        $id = explode("_", $_GET['id']);
        $employer_ISD = $id[0];
        $ticker = $id[1];
        $td = $id[2];

        $count = Yii::app()->db->createCommand(' SELECT COUNT(*) FROM transaction t, shares s
                     WHERE t.employer_ISD = ' . $employer_ISD .
            ' AND t.trade_date = "' . $td . '" AND t.shares_ID = s.shares_ID
                       AND s.ticker = "' . $ticker . '"')->queryScalar();
        $in = Yii::app()->db->createCommand('SELECT shares_ID FROM shares where ticker = "' . $ticker . '" AND employer_ISD = ' . $employer_ISD)->queryAll();

        $sql = 'SELECT p.participant_name, t.trade_date AS td, ticker, type, t.shares, t.status, s.shares_ID AS sid,
          (SELECT sum(t.shares) FROM transaction t WHERE t.employer_ISD = ' . $employer_ISD .
            '      AND trade_date = td AND t.shares_ID IN (';
        $j = 0;
        foreach ( $in as $i ) {
            if ( $j++ == 0 ) $sql .= $i["shares_ID"]; else $sql .= "," . $i["shares_ID"];
        }
        $sql .= ')) AS today, cost_per_share
    FROM transaction t
    JOIN participant p ON t.participant_ID = p.participant_ID
    JOIN shares s ON ( t.shares_ID = s.shares_ID AND active = 1 AND s.ticker = "' . $ticker . '" )
    WHERE trade_date = "' . $td . '" AND t.employer_ISD = ' . $employer_ISD . ' ORDER BY td DESC';

        // echo "<pre><code>"; var_dump($in); var_dump( $sql ); die;


        $gridDataProvider = new CSqlDataProvider($sql, array(
            'totalItemCount' => $count,
            // 'sort' => array('attributes' => array('trade_date'),),
            'pagination' => array('pageSize' => 30,),
        ));

        $gridColumns = array(
            array('name' => 'type', 'header' => 'Type',),
            array('name' => 'shares', 'header' => 'Shares', 'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
            array('name' => 'cost_per_share', 'header' => 'Current Price', 'htmlOptions' => array('width' => '90px', 'style' => 'text-align:center')),
            array('name' => 'dollars', 'header' => 'Dollars',
                'value' => function ($data) { return " $ " . number_format($data['dollars'], 2); },
                'htmlOptions' => array('width' => '90px', 'style' => 'text-align:right')),
        );

        $this->renderPartial('_tdate', array(
            'id' => Yii::app()->getRequest()->getParam('id'),
            'gridDataProvider' => $gridDataProvider,
            'gridColumns' => $gridColumns,
        ), false, true);
    }




    /**
     * Manages all models.
     */
    public function actionAdmin()
    {
        $model = new Participant('search');
        $model->unsetAttributes(); // clear any default values
        if ( isset($_GET['Participant']) )
            $model->attributes = $_GET['Participant'];


        $count = Yii::app()->db->createCommand('SELECT COUNT(*) FROM participant')->queryScalar();
        $sql = ' SELECT  p.*, IFNULL(t.pending, 0) AS pending, IFNULL(b.book, 0) AS book
                   FROM participant p
              LEFT JOIN ( SELECT  participant_ID, sum(dollars) AS pending FROM transaction
                           WHERE status != "Settled"
                        GROUP BY participant_ID ) t ON ( p.participant_ID = t.participant_ID )
              LEFT JOIN ( SELECT participant_ID, sum(dollars) AS book  FROM transaction
                        GROUP BY participant_ID ) b ON ( p.participant_ID = b.participant_ID )';

        $dataProvider = new CSqlDataProvider($sql, array(
            'totalItemCount' => $count,
            'pagination' => array('pageSize' => 30,),
        ));

        $this->render('admin', array(
            'dataProvider' => $dataProvider, // pass in the data provider
        ));
    }

    /**
     * Used for autocomplete participant name but with so few names per employer
     * it is easier to do a dropdown list.
     */
    public function actionPartAutocomplete()
    {
        $term = trim($_GET['term']); // participant name
        $eid = trim($_GET['eid']); // employer ID

        if ( $term !== '' ) {
            $part = Participant::partAutoComplete($term, $eid);
            echo CJSON::encode($part);
            Yii::app()->end();
        }
    }

    /**
     * Create dropdown of participants.
     */
    public function actionLoadpart()
    {
        $data = Participant::model()->findAll('employer_ISD=:employer_ISD',
            array(':employer_ISD' => (int)$_POST['employer_ISD']));

        $data = CHtml::listData($data, 'participant_ID', 'participant_name');

        echo "<option value=''>Select</option>";
        foreach ( $data as $value => $participant_name ) {

            $model = Participant::model()->findByPk($value);

            echo CHtml::tag('option', array('value' => $value),
                CHtml::encode($participant_name . $model->getPending()), true);
            // CHtml::encode($participant_name . '<span style="color:red">' . $model->getPending() . '</span>'),true);
        }
    }

    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return Participant the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model = Participant::model()->findByPk($id);
        if ( $model === null )
            throw new CHttpException(404, 'The requested page does not exist.');
        return $model;
    }

    /**
     * Performs the AJAX validation.
     * @param Participant $model the model to be validated
     */
    protected function performAjaxValidation($model)
    {
        if ( isset($_POST['ajax']) && $_POST['ajax'] === 'participant-form' ) {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }
    }

}
