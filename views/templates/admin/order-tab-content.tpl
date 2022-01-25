{**
 * Cecabank Module
 *
 * Copyright (c) 2019 Cecabank, S.A.
 *
 * @category  Payment
 * @author    Cecabank, S.A.
 * @copyright 2019, Cecabank, S.A.
 * @link      https://www.cecabank.es/
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * Description:
 *
 * Plugin de Prestashop para conectar con la pasarela de Cecabank.
 *
 * --
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to tpv@cecabank.es so we can send you a copy immediately.
 *}

<div class="tab-pane" id="cecabank-refund">
  <b> Devolución total o parcial: </b><br><br>
  <div class="row" id="wrapper-refund">
    <div class="col-sm-12">
      <form method="POST" action="{$url_refund}" class="card-details-form form-horizontal">
        <div class="form-group card-details-actions" style="display: flex">
          <input type="hidden" name="id_order" value="{$order_id}">
          <input type="text" class="form-control" name="pr" value="0" id="input-partial-refund" style="margin-right: 10px">
          <button class="btn btn-primary" type="submit">Devolver</button>
        </div>
      </form>
    </div>
  </div>
</div>
