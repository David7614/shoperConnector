<?php
namespace app\models;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
// use app\models\ListCardsAssigned

use Yii;

class UserSearch extends User{
    public function rules()
    {
        return [
                [['name', 'customerBoard', 'assignedUsersList', 'customer_boards_lists_id', 'status'], 'safe'],
                [['customer_id', 'email', 'lastFinishedAt'], 'safe'],
            ];
    }


    public function search($params){
        $subQuery = (new \yii\db\Query())
            ->select('MAX(finished_at)')
            ->from('xml_feed_queue')
            ->where('current_integrate_user = {{%user}}.id')
            ->andWhere(['integrated' => Queue::EXECUTED]);

        $query = User::find()->addSelect(['{{%user}}.*', 'lastFinishedAt' => $subQuery]);
        // if (!isset($params['sort'])){
        //     $query->orderBy(['priority'=>SORT_DESC, 'last_activity'=>SORT_DESC]);
        // }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 100],
            'sort'       => [
                'defaultOrder' => ['lastFinishedAt' => SORT_DESC],
                'attributes'   => [
                    'id',
                    'username',
                    'active',
                    'shop_type',
                    'lastFinishedAt' => [
                        'asc'     => ['lastFinishedAt' => SORT_ASC],
                        'desc'    => ['lastFinishedAt' => SORT_DESC],
                        'label'   => 'Ostatnia synchronizacja',
                        'default' => SORT_DESC,
                    ],
                ],
            ],
        ]);
        $this->load($params);
        $activeParam = $params['active'] ?? '1';
        if ($activeParam !== 'all') {
            $query->andWhere(['active' => (int)$activeParam]);
        }

        if ($this->username) {
            $query->andWhere(['like', 'username', $this->username]);
        }


        return $dataProvider;
    }

}
