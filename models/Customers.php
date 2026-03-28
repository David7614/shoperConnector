<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "customers".
 *
 * @property int $id
 * @property int $customer_id
 * @property string $email
 * @property string $registration
 * @property string $first_name
 * @property string $lastname
 * @property string $zip_code
 * @property string $sms_frequency
 * @property string $newsletter_frequency
 * @property string $nlf_time
 * @property string $data_permission
 * @property string $data_hash
 * @property int $user_id
 * @property int $page
 *
 * @property User $user
 */
class Customers extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'customers';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['customer_id', 'email', 'registration', 'user_id', 'page'], 'required'],
            [['user_id', 'page', 'is_wholesaler'], 'integer'],
            [['registration', 'nlf_time'], 'safe'],
            [['customer_id', 'email', 'first_name', 'lastname', 'sms_frequency', 'newsletter_frequency', 'data_permission', 'data_hash'], 'string', 'max' => 255],
            [['zip_code'], 'string', 'max' => 55],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'customer_id' => 'Customer ID',
            'email' => 'Email',
            'registration' => 'Registration',
            'first_name' => 'First Name',
            'lastname' => 'Lastname',
            'zip_code' => 'Zip Code',
            'user_id' => 'User ID',
            'page' => 'Page',
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery|\app\models\queries\UserQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\queries\CustomersQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\queries\CustomersQuery(get_called_class());
    }

    /**
     * @param $customer_data
     * @param $user_id
     * @param $page
     * @return bool
     */
    public static function addCustomer($customer_data, $user_id, $page): bool
    {
        if(($customer = self::find()->where(['customer_id' => $customer_data['customer_id'], 'user_id' => $user_id])->one()) !== null) {
            $data_hash = md5(json_encode($customer_data));

            if($data_hash == $customer->data_hash) {
                return true;
            }

            $customer->newsletter_frequency = $customer_data['newsletter_frequency'];
            $customer->sms_frequency = $customer_data['sms_frequency'];
            $customer->nlf_time = $customer_data['nlf_time'];
            $customer->data_permission = $customer_data['data_permission'];
            $customer->phone = $customer_data['phone'];
            $customer->last_modification_date = $customer_data['last_modification_date'];
            $customer->data_hash = $data_hash;
            $customer->is_wholesaler = $customer_data['is_wholesaler'];
            return $customer->save();
        }

        $hash = md5(json_encode($customer_data));
        $customer_data['page'] = $page;
        $customer_data['user_id'] = $user_id;
        $customer_data['data_hash'] = $hash;
        $customer = new self($customer_data);
        if ($customer->save(false)){
            echo "CUSTOMER SAVED";
            return true;
        }else{
            print_r($customer->getErrors());
        }
    }
}
