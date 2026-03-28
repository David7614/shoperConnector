<?php


use \yii\helpers\Html;
use yii\helpers\ArrayHelper;
/* @var $this yii\web\View */


$this->title = 'Authorization | SAMBA';
?>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-primary">
            <div class="panel-heading text-center">
                <h3 class="panel-title">Dane dostępowe</h3>
            </div>
            <div class="panel-body">
               <table class="table">
                   <tr>
                       <td>Client ID</td>
                       <td><?= $client_id ?></td>
                   </tr>
                   <tr>
                       <td>Secret Key</td>
                       <td><?= $secret_key ?></td>
                   </tr>
               </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-primary">
            <div class="panel-heading text-center">
                <h3 class="panel-title">Samba ustawienia <?= $user->username ?> <?= $user->getUrl() ?></h3>
            </div>
            <div class="panel-body">
                <?= Html::beginForm('', 'post') ?>
                <div class="form-group">
                    <?= Html::label('Trackpoint', 'trackpoint') ?>
                    <?= Html::textInput('trackpoint', $user->config->get('trackpoint'), ['class' => 'form-control', 'id' => 'trackpoint']) ?>
                </div>


                <div class="form-group">
                    <?= Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                </div>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12 text-right">
        <?= Html::beginForm(\yii\helpers\Url::toRoute(['authorization/logout']), 'post') ?>
        <?= Html::submitButton('Wyloguj', ['class' => 'btn btn-danger']) ?>
        <?= Html::endForm() ?>

    </div>
</div>
