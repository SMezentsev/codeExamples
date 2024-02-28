<?php

use BigFish\PDF417\PDF417;
use yii\widgets\LinkPager;

$imageSize = 150;
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
            padding: 10px;
            border: 1px dashed;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .image {
            float: left;
            overflow: hidden;
            height: 75px;
            width: 360px;
            margin-bottom: 1em;
        }

        .image img {
        }

        .left label {
            display: block;
            text-align: center;
        }

        .left label .name {
            max-height: 3.4em;
            overflow: hidden;
            width: 342px;
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
            width: 300px;
            height: 80px;
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
    <!--h3>Настройки</h3>

    <div>
        Zoom: <select id="zoom">
            <option value="5">5</option>
            <option value="3" selected>3</option>
        </select>
    </div-->
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
    <?php
    foreach ($dataProvider->getModels() as $model) {
        $code = (!is_array($model)) ? $model->toArray([], ['dataMatrixUrl']) : $model;
        
        $pdf417 = new PDF417();
        $data = $pdf417->encode($code["code"]);
        $renderer = new \BigFish\PDF417\Renderers\ImageRenderer([
            'format'  => 'data-url',
            'scale'   => 2,
            'padding' => 1,
        ]);
        $img = $renderer->render($data);
        ?>
        <div>
            <div class="left">
                <div class="image">
                    <img src="<?= $img->encoded ?>">
                </div>
                <label>
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
                img.attr('src', img.attr('rel') + '&size=' + zoom);
            })
        });
    });
</script>
</body>
</html>
