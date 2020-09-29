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

{literal}
<style type="text/css">
fieldset a {
    color:#0099ff !important;
    text-decoration:underline;
}
fieldset a:hover {
    color:#000000;
    text-decoration:underline;
}
.level1 {
    font-size:1.2em
}
.level2 {
    font-size:0.9em
}
</style>
{/literal}

<div><img src="{$cecabank.dfl.img_path|escape:'htmlall':'UTF-8'}" alt="142x38.png" width="142" height="38" title="" /></div>
<form method="post" action="{$cecabank.dfl.action|escape:'htmlall':'UTF-8'}">
<br />

<fieldset class="level1">
<legend>{l s='Acerca de Cecabank' mod='cecabank'}</legend>
    <b>{l s='Permite utilizar la pasarela de Cecabank en tu sitio web.' mod='cecabank'}</b> <a target="_blank" href="https://www.cecabank.es/"><b>{l s='cecabank.es.' mod='cecabank'}</b></a><br />
</fieldset>
<br />

<fieldset class="level1">
    <legend>{l s='Configuración' mod='cecabank'}</legend>
    <b>{l s='Para usar el plugin es necesario definir algunos campos:' mod='cecabank'}</b><br /><br />
    
    <fieldset class="level2">
        <label>{l s='Código de comercio' mod='cecabank'}</label>
        <div class="margin-form">
            <input type="text" name="merchant" value="{$cecabank.config.merchant|escape:'htmlall':'UTF-8'}" />
            <p>{l s='Lo puede dejar en blanco para deshabilitar el plugin' mod='cecabank'}</p>
        </div>
        <div class="clear"></div>
        <label>{l s='Adquiriente' mod='cecabank'}</label>
        <div class="margin-form">
            <input type="text" name="acquirer" value="{$cecabank.config.acquirer|escape:'htmlall':'UTF-8'}" />
            <p>{l s='Lo puede dejar en blanco para deshabilitar el plugin' mod='cecabank'}</p>
        </div>
        <div class="clear"></div>
        <label>{l s='Clave secreta' mod='cecabank'}</label>
        <div class="margin-form">
            <input type="text" name="secret_key" value="{$cecabank.config.secret_key|escape:'htmlall':'UTF-8'}" />
            <p>{l s='Lo puede dejar en blanco para deshabilitar el plugin' mod='cecabank'}</p>
        </div>
        <div class="clear"></div>
        <label>{l s='Terminal' mod='cecabank'}</label>
        <div class="margin-form">
            <input type="text" name="terminal" value="{$cecabank.config.terminal|escape:'htmlall':'UTF-8'}" />
            <p>{l s='Lo puede dejar en blanco para deshabilitar el plugin' mod='cecabank'}</p>
        </div>
        <div class="clear"></div>
        <label>{l s='Título' mod='cecabank'}</label>
        <div class="margin-form">
            <input type="text" name="title" value="{$cecabank.config.title|escape:'htmlall':'UTF-8'}" />
            <p>{l s='Lo puede dejar en blanco para deshabilitar el plugin' mod='cecabank'}</p>
        </div>
        <div class="clear"></div>
        <label>{l s='Descripción' mod='cecabank'}</label>
        <div class="margin-form">
            <input type="text" name="description" value="{$cecabank.config.description|escape:'htmlall':'UTF-8'}" />
            <p>{l s='Lo puede dejar en blanco para deshabilitar el plugin' mod='cecabank'}</p>
        </div>
        <div class="clear"></div>
        <label>{l s='Entorno' mod='cecabank'}</label>
        <div class="margin-form">
            <select name="environment">
                <option value="test" {if $cecabank.config.environment eq 'test'}selected{/if}>{l s='Prueba' mod='cecabank'}</option>
                <option value="real" {if $cecabank.config.environment eq 'real'}selected{/if}>{l s='Real' mod='cecabank'}</option>
            </select>
        </div>
        <div class="clear"></div>
        <label>{l s='Moneda' mod='cecabank'}</label>
        <div class="margin-form">
            <select name="currency">
                <option value="978" {if $cecabank.config.currency eq '978'}selected{/if}>{l s='EUR' mod='cecabank'}</option>
                <option value="840" {if $cecabank.config.currency eq '840'}selected{/if}>{l s='USD' mod='cecabank'}</option>
                <option value="826" {if $cecabank.config.currency eq '826'}selected{/if}>{l s='GBP' mod='cecabank'}</option>
                <option value="392" {if $cecabank.config.currency eq '392'}selected{/if}>{l s='JPY' mod='cecabank'}</option>
                <option value="32" {if $cecabank.config.currency eq '32'}selected{/if}>{l s='ARS' mod='cecabank'}</option>
                <option value="124" {if $cecabank.config.currency eq '124'}selected{/if}>{l s='CAD' mod='cecabank'}</option>
                <option value="152" {if $cecabank.config.currency eq '152'}selected{/if}>{l s='CLP' mod='cecabank'}</option>
                <option value="170" {if $cecabank.config.currency eq '170'}selected{/if}>{l s='COP' mod='cecabank'}</option>
                <option value="356" {if $cecabank.config.currency eq '356'}selected{/if}>{l s='INR' mod='cecabank'}</option>
                <option value="484" {if $cecabank.config.currency eq '484'}selected{/if}>{l s='MXN' mod='cecabank'}</option>
                <option value="604" {if $cecabank.config.currency eq '604'}selected{/if}>{l s='PEN' mod='cecabank'}</option>
                <option value="756" {if $cecabank.config.currency eq '756'}selected{/if}>{l s='CHF' mod='cecabank'}</option>
                <option value="986" {if $cecabank.config.currency eq '986'}selected{/if}>{l s='BRL' mod='cecabank'}</option>
                <option value="937" {if $cecabank.config.currency eq '937'}selected{/if}>{l s='VEF' mod='cecabank'}</option>
                <option value="949" {if $cecabank.config.currency eq '949'}selected{/if}>{l s='TRY' mod='cecabank'}</option>
            </select>
        </div>
        <div class="clear"></div>
        <label>{l s='Ícono' mod='cecabank'}</label>
        <div class="margin-form">
            <input type="text" name="icon" value="{$cecabank.config.icon|escape:'htmlall':'UTF-8'}" />
            <p>{l s='Lo puede dejar en blanco para deshabilitar el plugin' mod='cecabank'}</p>
        </div>
        <div class="clear"></div>
        <div class="margin-form clear pspace"><input type="submit" name="submitUpdate" value="{l s='Guardar' mod='cecabank'}" class="button" /></div>
    </fieldset>
</fieldset>
</form>
<br />

<fieldset class="level1 space">
    <legend>{l s='Ayuda' mod='cecabank'}</legend>
    <b>{l s='Para más información contáctenos a través del correo' mod='cecabank'}</b> <a href="mailto:tpv@cecabank.es"><b>{l s='tpv@cecabank.es' mod='cecabank'}</b></a>.<br /><br />
</fieldset>
