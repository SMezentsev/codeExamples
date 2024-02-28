<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;

class MenuController extends ActiveController
{
    use ControllerTrait;
    
    public $modelClass = "app\modules\itrack\models\Menu";
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'menu',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
//        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['update']);
            unset($actions['delete']);
            unset($actions['create']);
        }
        
        return $actions;
    }
    
    public function actionShow()
    {
        $model = $this->modelClass;
        $query = $model::find()->orderBy(["parent" => SORT_ASC, "ord" => SORT_ASC]);
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            $query->andWhere(['=', 'skad', true]);
        }
        $m = $query->all();
        
        /**
         * для админа редактирвоание меню
         */
        if (\Yii::$app->user->can("rfAdmin")) {
            $m[] = ["uid" => "menu", "url" => "/admin/menu", "parent" => "admin", "permissions" => "", "amask" => ""];
        }
        
        $ret = [];
        foreach ($m as $v) {
            if (empty($v["parent"])) {
                list($items, $permissions) = $this->getChilds($m, $v["uid"]);
                $permissions = implode(",", array_keys(array_count_values(explode(",", $permissions))));
                if (count($items)) {
                    $ret[] = ["id" => $v["uid"], "access" => ["permissions" => $permissions, "mask" => $v["amask"]], "url" => $v["url"], "items" => $items];
                } else {
                    $ret[] = ["id" => $v["uid"], "access" => ["permissions" => $v["permissions"], "mask" => $v["amask"]], "url" => $v["url"]];
                }
            }
        }
        
        /**
         * fnsPages - гвоздь
         */
        return ["menu" => $ret, 'fnsPages' => \app\modules\itrack\models\Fns::$pages];
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case "show":
                if (\Yii::$app->user->isGuest) {
                    throw new \yii\web\NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            default:
                if (!\Yii::$app->user->can('rfAdmin')) {
                    throw new \yii\web\NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
    protected function getChilds($arr, $id)
    {
        $items = [];
        $permissions = [];
        foreach ($arr as $v) {
            if ($v["parent"] == $id) {
                $items[] = ["id" => $v["uid"], "access" => ["permissions" => $v["permissions"], "mask" => $v["amask"]], "url" => $v["url"]];
                $permissions[] = $v["permissions"];
            }
        }
        
        return [$items, implode(",", $permissions)];
    }
    
}


/*
appConfig.menu.mainMenu = [
{id: 'codes',icon:'barcode',items:[
    {id:'order', access:{permissions:'generation-create-individual,generation-create-group'}, url:'/codes/order'},
     {id:'reserve', access:{permissions:'reserve-crud'}, url:'/codes/reserve'},
     {id:'orders', access:{permissions:'generation'}, url:'/codes/orders'},
     {id:'reserves', access:{permissions:'reserve-crud'}, url:'/codes/reserves'},
     {id:'history', access:{permissions:'reserve-crud'}, url:'/codes/history'}
    ]
},
 {id:'recall', items:[
{id: 'ByCodes', access:{mask:'codeFunction-remove', permissions:''}, url:'/codes/recallByCodes'},
 {id: 'ByDate', access:{mask:'codeFunction-remove', permissions:''}, url:'/codes/recallByDate'}
]},
 {id:'fns', items:[
{id:'output', access:{permissions:'report-fns'}, url:'/fns/output'},
 {id:'grouping', access:{permissions:'report-fns'}, url:'/fns/grouping'},
 {id:'withdrawal', access:{permissions:'report-fns'}, url:'/fns/withdrawal'},
 {id:'outputFinished', access:{permissions:'report-fns'}, url:'/fns/outputFinished'},
 {id:'transfer', access:{permissions:'report-fns'}, url:'/fns/transfer'},
 {id:'shipment', access:{permissions:'report-fns'}, url:'/fns/shipment'},
 {id:'accept', access:{permissions:'report-fns'}, url:'/fns/accept'},
 {id:'cancel', access:{permissions:'report-fns'}, url:'/fns/cancel'}
]},
 {id:'reports', items:[
{id:'nom-move', access:{permissions:'report-nomenclature-movement'}, url:'/report/nomenclature'},
 {id:'revisions', access:{permissions:'report-checkman'}, url:'/report/revisions'},
 {id:'codes-data', access:{permissions:'report-code-data'}, url:'/report/codes'},
 {id:'manufacturers-data', access:{permissions:'report-manufacturer'}, url:'/report/manufacturers'},
 {id:'invoices', access:{permissions:'report-invoices'}, url:'/report/invoices'},
 {id:'downloads', access:{permissions:'report-nomenclature-movement,report-code-data,report-invoices,report-checkman,report-manufacturer'}, url:'/report/downloads'}
]},
 {id:'admin', items:[
{id:'manufacturers', access:{permissions:'reference-manufacturers,reference-manufacturers-crud'}, url:'/admin/manufacturers'},
 {id:'invoices', access:{permissions:'reference-invoice,reference-invoice-crud'}, url:'/admin/invoices'},
 {id:'objects', access:{permissions:'reference-objects,reference-objects-crud'}, url:'/admin/objects'},
 {id:'nomenclatures', access:{permissions:'reference-nomenclature,reference-nomenclature-crud'}, url:'/admin/nomenclatures'},
 {id:'products', access:{permissions:'product,reference-product,reference-product-crud'}, url:'/admin/products'},
 {id:'users', access:{permissions:'users, users-crud'}, url:'/admin/users'},
 {id:'roles', access:{permissions:'users, users-crud'}, url:'/admin/roles'},
 {id:'sessions', access:{permissions:'users-sessions'}, url:'/admin/sessions'},
 {id:'rights', access:{permissions:'reference-roles'}, url:'/admin/rights'}
]}
];

appConfig.menu.mainMenu = [
{id: 'codes', items:[
{id:'order', access:{permissions:'generation-create-individual,generation-create-group'}, url:'/codes/order'},
 {id:'reserve', access:{permissions:'reserve-crud'}, url:'/codes/reserve'},
 {id:'orders', access:{permissions:'generation'}, url:'/codes/orders'},
 {id:'reserves', access:{permissions:'reserve-crud'}, url:'/codes/reserves'},
 {id:'history', access:{permissions:'reserve-crud'}, url:'/codes/history'}
]},
 {id:'recall', items:[
{id: 'ByCodes', access:{mask:'codeFunction-remove', permissions:'codeFunction-removed-web-brak,codeFunction-block'}, url:'/codes/recallByCodes'},
 {id: 'ByDate', access:{mask:'codeFunction-remove', permissions:'codeFunction-removed-web-brak,codeFunction-block'}, url:'/codes/recallByDate'}
]},
 {id:'reports', items:[
{id:'nom-move', access:{permissions:'report-nomenclature-movement'}, url:'/report/nomenclature'},
 {id:'revisions', access:{permissions:'report-checkman'}, url:'/report/revisions'},
 {id:'codes-data', access:{permissions:'report-code-data'}, url:'/report/codes'},
 {id:'invoices', access:{permissions:'report-invoices'}, url:'/report/invoices'},
 {id:'downloads', access:{permissions:'report-checkman,reports-historyCode,reports-historyCheckCode'}, url:'/report/downloads'}
]},
 {id:'admin', items:[
{id:'invoices', access:{permissions:'reference-invoice,reference-invoice-crud'}, url:'/admin/invoices'},
 {id:'objects', access:{permissions:'reference-objects,reference-objects-crud'}, url:'/admin/objects'},
 {id:'nomenclatures', access:{permissions:'reference-nomenclature,reference-nomenclature-crud'}, url:'/admin/nomenclatures'},
 {id:'products', access:{permissions:'reference-product,reference-product-crud'}, url:'/admin/products'},
 {id:'users', access:{permissions:'users,users-crud'}, url:'/admin/users'},
 {id:'sessions', access:{permissions:'users-sessions'}, url:'/admin/sessions'}
]}
];
 
 */