<?php

namespace app\modules\itrack\controllers;

/**
 * @OA\Get(
 *  path="/barcode",
 *  tags={"Баркод"},
 *  description="Генерация баркода",
 *  @OA\Response(
 *      response="200",
 *      description="Баркод",
 *      @OA\JsonContent(
 *          @OA\Property(property="barcode", type="string", example="Штрих код с символикой GS1-128 в PNG"),
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class BarcodeController extends \yii\web\Controller
{
    public $modelClass = '';
    
    public function actionIndex($code)
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        \Yii::$app->response->headers->set('Content-type', 'image/png');
        
        $code = base64_decode($code);
        $builder = new \Ayeo\Barcode\Builder();
        $builder->setBarcodeType('gs1-128');
        $builder->setWidth(500);
        $builder->setHeight(150);
        $builder->output('(00)' . $code);
        
        return;
    }
}
