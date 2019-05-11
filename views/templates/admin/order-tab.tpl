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

<li>
  <a href="#cecabank-refund">
    <i class="icon-exchange"></i>
    {l s='Cecabank Devoluciones'}
    {if $ok eq 1}<span class="badge">¡HECHA!</span>{/if}
    {if $ok eq 2}<span class="badge">FALLIDA</span>{/if}
    {if $ok eq 3}<span class="badge">FALLIDA: Mayor que la cantidad no devuelta</span>{/if}
  </a>
</li>
