<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

use yii\widgets\LinkPager;

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 17.08.16
 * Time: 13:57
 */

/** @var \yii\data\DataProviderInterface $dataProvider */
/** @var \app\modules\itrack\models\Code $model */
/** @var \app\modules\itrack\models\Generation $generation */
/** @var \app\modules\itrack\models\Product $product */

$imageSize = app\modules\itrack\models\Constant::get('imageSize');
if (empty($imageSize)) {
    $imageSize = 150;
}
?>
<html>
<head>
    <meta charset="utf-8">
    <title>Печать кодов</title>

    <style type="text/css">
        body {
            background: rgba(215, 215, 215, 0.22);
        }

        #filter {
            position: fixed;
            background: #FFFFFF;
            padding: 10px;

            top: 50px;
            left: 1050px;
        }

        #codesList {
            float: left;
            width: 1024px;
            background: #FFFFFF;
        }

        #codesList > div {
            float: left;
            padding: 0px;
            border: 1px dashed;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        #codesList.zoom3 .image {
            float: left;
            overflow: hidden;
            height: <?=$imageSize-5?>px;
            width: 160px;
            margin-bottom: 1em;
        }

        #codesList.zoom5 .image {
            float: left;
            overflow: hidden;
            height: 245px;
            width: 245px;
            margin-bottom: 1em;
        }

        .image img {
        }

        .left label {
            display: block;
            text-align: center;
        }

        #codesList.zoom3 .left label .name {
            max-height: 3.4em;
            overflow: hidden;
            width: 142px;
            display: inline-block;
        }

        #codesList.zoom5 .left label .name {
            max-height: 3.4em;
            overflow: hidden;
            width: 242px;
            display: inline-block;
        }

        .group.zoom3 .image {
            width: 200px !important;
            height: 50px !important;
        }

        .group.zoom3 .image img {
            width: 200px;
        }

        .group .left {
        }

        .group .image {
            width: 300px !important;
            height: 80px !important;
        }

        .group .image img {
            margin-left: 0px;
            width: 300px;
        }
    </style>
    <style media="print" type="text/css">
        #filter {
            display: none;
        }
    </style>
</head>
<body>

<div id="filter">
    <h3>Настройки</h3>

    <div>
        Zoom: <select id="zoom">
            <option value="5">5</option>
            <option value="3" selected>3</option>
        </select>
    </div>
</div>

<?php
$class = '';
$mainModel = $dataProvider->getModels()[0];

$product = null;
if (is_array($mainModel)) {
    $product = \app\modules\itrack\models\Product::find()->where(['id' => $mainModel['product_uid']])->one();
} else {
    $product = $mainModel->product;
}

if ($generation->codetype_uid == \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP) {
    $class = 'group';
}
?>

<div id="codesList" class="zoom3 <?= $class ?>">
    <?php foreach ($dataProvider->getModels() as $model) {
        $code = (!is_array($model)) ? $model->toArray([], ['dataMatrixUrl']) : $model;
        
        $src = $code['dataMatrixUrl'];
        ?>
        <div>
            <div class="left">
                <div class="image">
                    <img src="<?= $src ?>&size=3&imageSize=<?= $imageSize ?>" rel="<?= $src ?>">
                </div>
                <label>
                    <span class="code"><?= $code['code']; ?></span><br><br>
                    
                    <?php if ($generation->codetype_uid == \app\modules\itrack\models\CodeType::CODE_TYPE_INDIVIDUAL) { ?>
                        <span class="name"><?= $product->nomenclature->name ?></span><br>
                        <span class="series"><?= (isset($code['series'])) ? $code['series'] : $product->series ?></span>
                    <?php } ?>
                </label>
            </div>
        </div>
    
    <?php } ?>
</div>
<?php
echo LinkPager::widget([
    'pagination' => $dataProvider->pagination,
]);
?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
<script type="text/javascript">
    $(document).ready(function () {
        $('#zoom').on('change', function () {
            var zoom = $(this).val();

            $('#codesList').removeClass('zoom5')
                .removeClass('zoom3')
                .addClass('zoom' + zoom);

            $('.image img').each(function () {
                var img = $(this);
                if (zoom == 3)
                    img.attr('src', img.attr('rel') + '&size=' + zoom + '&imageSize=150');
                else
                    img.attr('src', img.attr('rel') + '&size=' + zoom + '&imageSize=250');
            })
        });
    });
</script>
</body>
</html>
