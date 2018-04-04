<?php

/**
 * This is the model class for table "participant".
 *
 * The followings are the available columns in table 'participant':
 * @property integer $participant_ID
 * @property integer $employer_ISD
 * @property string $participant_name
 * @property string $ssn
 * @property string $ee_number
 * @property string $pw_balance
 * @property string $mm_balance
 * @property string $sh_balance
 * @property string $balance_date
 * @property string $note
 */

/**
 * Class Participant extends CActiveRecord
 */
class Participant extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'participant';
	}

    /** @var  string $employer_name employer name given employer ISD. */
    public $employer_name;
    /** @var  $book amount invested */
    public $book;
    /** @var  $pending any amount currently pending */
    public $pending;
    public $percent;

	/**
     * Validation rules for model attributes.
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('employer_ISD, participant_name', 'required'),
			array('employer_ISD', 'numerical', 'integerOnly'=>true),
            array('participant_name', 'length', 'max'=>100),
			array('ssn', 'length', 'max'=>11),
            array('ssn','unique','message'=>'{attribute}:{value} already exists!'),

            // The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('employer_ISD, employer_name, participant_name, ssn, ee_number', 'safe' ),
		);
	}

	/**
     * Relational rules linking tables.
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
            'shares' => array(self::HAS_MANY, 'Shares', 'participant_ID'),
            'employer' => array(self::HAS_ONE, 'Employer', 'employer_ISD'),
            'transaction' => array(self::HAS_MANY, 'Transaction', 'participant_ID','joinType'=>'LEFT JOIN'),
        );
	}

	/**
     * Customized attribute labels.
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'participant_ID' => 'Participant ID',
            'employer_ISD' => 'Employer',
			'employer_name' => 'Employer',
            'participant_name' => 'Participant',
            'ssn' => 'SSN',
            'ee_number' => 'EE Number',
            'pw_balance' => '1Cloud',
            'mm_balance' => 'Money Market',
            'sh_balance' => 'Shares',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * Typical usecase:
	 * - Initialize the model fields with values from filter form.
	 * - Execute this method to get CActiveDataProvider instance which will filter
	 * models according to data in model fields.
	 * - Pass data provider to CGridView, CListView or any similar widget.
	 *
	 * @return CActiveDataProvider the data provider that can return the models
	 * based on the search/filter conditions.
	 */
    public function search()
    {
        // @todo Please modify the following code to remove attributes that should not be searched.

        $criteria=new CDbCriteria;
        // $criteria->with = array('employer');

        $criteria->compare('t.participant_ID',$this->participant_ID);
        $criteria->compare('t.employer_ISD',$this->employer_ISD);
        $criteria->compare('t.participant_name',$this->participant_name,true);
        $criteria->compare('t.ssn',$this->ssn,true);
        $criteria->compare('t.ee_number',$this->ee_number,true);
        $criteria->compare('t.pw_balance',$this->pw_balance,true);
        $criteria->compare('t.mm_balance',$this->mm_balance,true);

        return new CActiveDataProvider($this, array(
            'criteria'=>$criteria,
        ));
    }


    public function searchPending()
	{
        $count=Yii::app()->db->createCommand('SELECT COUNT(*) FROM participant')->queryScalar();
        $sql = 'SELECT participant_ID, employer_ISD, participant_name, pw_balance, mm_balance, sh_balance, SUM(x) AS book
                  FROM ( SELECT p.participant_ID, p.employer_ISD, participant_name, pw_balance, mm_balance, sh_balance,
                                shares_ID as sid, s.ticker, s.shares,
                               ( SELECT cost_per_share FROM transaction t WHERE t.shares_ID = sid
                                    AND trade_date = ( SELECT max(trade_date) FROM transaction t
                                  WHERE t.shares_ID = sid) LIMIT 1)*s.shares as x
                           FROM participant p, shares s
                          WHERE active = 1  AND p.participant_ID = s.participant_ID
                       GROUP BY s.participant_ID, s.shares_ID) as y
                  GROUP BY participant_ID';

        return new CSqlDataProvider($sql, array(
            'totalItemCount'=>$count,
            'sort'=>array( 'attributes'=>array( 'participant_name', ), ),
            'pagination'=>array( 'pageSize'=>30, ),
        ));
	}

    /**
     * get if any pending.
     *
     * @TODO clean up
     * @return string
     */
    public function getPending() {

        $p = $this->pending;
        switch ($p) {
            case 0: // no buys or sells pending
                return "";
            case 1: // a buy
                return " Buy Pending";
            case 2: // a sell
                return " Sell Pending";
            case 3: // a buy and a sell
                return " Buy and Sell Pending";
            case 9: // reallocation pending
                return " Reallocation Pending";
        }
        return " Pending";
    }

    /**
     * set if any pending.
     *
     * @TODO clean up
     * @param $buy
     */
    public function setPending($buy) {

        $p = $this->pending;
        $value = 0;

        switch ($p) {
            case 0: // no buys or sells pending
                if ( $buy === "Buy" ) $value = 1;
                if ( $buy === "Sell" ) $value = 2;
                break;
            case 1: // a buy
                if ( $buy === "Buy" ) $value = 98;
                if ( $buy === "Sell" ) $value = 3;
                break;
            case 2: // a sell
                if ( $buy === "Buy" ) $value = 3;
                if ( $buy === "Sell" ) $value = 99;
                break;
            case 3: // a buy and a sell
                if ( $buy === "Buy" ) $value = 98;
                if ( $buy === "Sell" ) $value = 99;
                break;
            case 9: // reallocation
                if ( $buy === "Buy" ) $value = 98;
                if ( $buy === "Sell" ) $value = 99;
                break;
        }
        $this->pending = $value;
    }

    /**
     * Used by autocomplete.
     *
     * @param string $name
     * @param string $eid
     * @return mixed
     */
    /* Result should be in this format
      array( 'id'=>4, 'label'=>'John', ),
      array( 'id'=>3, 'label'=>'Grace', ),
      array( 'id'=>5, 'label'=>'Matt', ),
    */
    public static function partAutoComplete($name='',$eid='')
    {
        $query= 'SELECT participant_ID as id, CONCAT( SUBSTR(ssn, 8,4), " ", participant_name) AS label
                   FROM participant
                  WHERE participant_name LIKE :name AND employer_ISD = :eid';
        $name = $name.'%';
        return  Yii::app()->db->createCommand($query)->queryAll(true,array(':name'=>$name,':eid'=>$eid));

    }

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Participant the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
