<!--
/**
 * Copyright (c) 2012, Rakuten Deutschland GmbH. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Rakuten Deutschland GmbH nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL RAKUTEN DEUTSCHLAND GMBH BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING
 * IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
-->
[{include file="headitem.tpl" title="GENERAL_ADMIN_TITLE"|oxmultilangassign}]

[{ if $shopid != "oxbaseshop" }]
    [{assign var="readonly" value="readonly disabled"}]
[{else}]
    [{assign var="readonly" value=""}]
[{/if}]

<form name="myedit" id="myedit" action="[{ $oViewConf->getSelfLink() }]" method="post">
    [{ $oViewConf->getHiddenSid() }]
    <input type="hidden" name="cl" value="admin_rakuten_config">
    <input type="hidden" name="fnc" value="save">
    <input type="hidden" name="oxid" value="[{ $oxid }]">
    <input type="hidden" name="editval[oxshops__oxid]" value="[{ $oxid }]">

<table cellspacing="0" cellpadding="0" border="0">
<tr>
    <td valign="top" class="edittext">
        <table cellspacing="0" cellpadding="0" border="0">
            <tr>
                <td colspan="2">
                    <strong>[{ oxmultilang ident="rakuten_checkout" }] [{ $oView->getVersion() }]</strong>
                    <hr size="1">
                </td>
            </tr>
            <tr>
                <td class="edittext" width="70">
                    [{ oxmultilang ident="GENERAL_ACTIVE" }]
                </td>
                <td class="edittext">
                    <input type=hidden name="confbools[blRakutenActive]" value="false">
                    <input class="edittext" type="checkbox" name="confbools[blRakutenActive]" value="true" [{if ($confbools.blRakutenActive)}]checked[{/if}] [{ $readonly }]>
                    [{ oxinputhelp ident="HELP_GENERAL_ACTIVE" }]
                </td>
            </tr>
            <tr>
                <td class="edittext">
                    [{ oxmultilang ident="RAKUTEN_PROJECT_ID" }]
                </td>
                <td class="edittext">
                    <input type="text" class="editinput" name="confstrs[sRakutenProjectId]" value="[{$confstrs.sRakutenProjectId}]" style="width:300px" [{ $readonly }]>
                    [{ oxinputhelp ident="HELP_RAKUTEN_PROJECT_ID" }]
                </td>
            </tr>
            <tr>
                <td class="edittext">
                    [{ oxmultilang ident="RAKUTEN_API_KEY" }]
                </td>
                <td class="edittext">
                    <input type="text" class="editinput" name="confstrs[sRakutenApiKey]" value="[{$confstrs.sRakutenApiKey}]" style="width:300px" [{ $readonly }]>
                    [{ oxinputhelp ident="HELP_RAKUTEN_API_KEY" }]
                </td>
            </tr>
            <tr>
                <td class="edittext" width="70">
                    [{ oxmultilang ident="RAKUTEN_INTEGRATION_METHOD" }]
                </td>
                <td class="edittext">
                    <select name="confstrs[sRakutenIntegrationMethod]" class="editinput" style="width:302px" [{ $readonly }]>
                        <option value="STANDARD" [{ if $confstrs.sRakutenIntegrationMethod == "STANDARD" }]SELECTED[{/if}]>[{ oxmultilang ident="RAKUTEN_INTEGRATION_METHOD_STANDARD" }]</option>
                        <option value="INLINE" [{ if $confstrs.sRakutenIntegrationMethod == "INLINE" }]SELECTED[{/if}]>[{ oxmultilang ident="RAKUTEN_INTEGRATION_METHOD_INLINE" }]</option>
                    </select>
                    [{ oxinputhelp ident="HELP_RAKUTEN_INTEGRATION_METHOD" }]
                </td>
            </tr>
            <tr>
                <td class="edittext" width="70" valign="top">
                    [{ oxmultilang ident="RAKUTEN_SHIPPING_RATES" }]
                </td>
                <td class="edittext">
                    <textarea name="confstrs[sRakutenShippingRates]" class="editinput" style="width:300px" [{ $readonly }]>[{$confstrs.sRakutenShippingRates}]</textarea>
                    [{ oxinputhelp ident="HELP_RAKUTEN_SHIPPING_RATES" }]
                </td>
            </tr>
            <tr>
                <td class="edittext" width="70">
                    [{ oxmultilang ident="RAKUTEN_SANDBOX_MODE" }]
                </td>
                <td class="edittext">
                    <input type=hidden name="confbools[blRakutenSandboxMode]" value="false">
                    <input class="edittext" type="checkbox" name="confbools[blRakutenSandboxMode]" value="true" [{if ($confbools.blRakutenSandboxMode)}]checked[{/if}] [{ $readonly }]>
                    [{ oxinputhelp ident="HELP_RAKUTEN_SANDBOX_MODE" }]
                </td>
            </tr>
            <tr>
                <td class="edittext" width="70">
                    [{ oxmultilang ident="RAKUTEN_DEBUG_MODE" }]
                </td>
                <td class="edittext">
                    <input type=hidden name="confbools[blRakutenDebugMode]" value="false">
                    <input class="edittext" type="checkbox" name="confbools[blRakutenDebugMode]" value="true" [{if ($confbools.blRakutenDebugMode)}]checked[{/if}] [{ $readonly }]>
                    [{ oxinputhelp ident="HELP_RAKUTEN_DEBUG_MODE" }]
                </td>
            </tr>
            <tr>
                <td class="edittext" width="70">
                    [{ oxmultilang ident="RAKUTEN_BILLING_ADDRESS_RESTRICTIONS" }]
                </td>
                <td class="edittext" valign="top">
                    <select name="confstrs[iRakutenBillingAddr]" class="editinput" style="width:302px" [{ $readonly }]>
                        <option value="1" [{ if $confstrs.iRakutenBillingAddr == 1 }]SELECTED[{/if}]>[{ oxmultilang ident="RAKUTEN_BILLING_ADDR_ALL" }]</option>
                        <option value="2" [{ if $confstrs.iRakutenBillingAddr == 2 }]SELECTED[{/if}]>[{ oxmultilang ident="RAKUTEN_BILLING_ADDR_BUSINESS" }]</option>
                        <option value="3" [{ if $confstrs.iRakutenBillingAddr == 3 }]SELECTED[{/if}]>[{ oxmultilang ident="RAKUTEN_BILLING_ADDR_PRIVATE" }]</option>
                    </select>
                    [{ oxinputhelp ident="HELP_RAKUTEN_BILLING_ADDRESS_RESTRICTIONS" }]
                </td>
            </tr>
            <tr>
                <td class="edittext"></td>
                <td class="edittext" align="center">
                    <br />
                    <input type="submit" class="edittext" name="save" value="[{ oxmultilang ident="GENERAL_SAVE" }]" [{ $readonly }] style="width:150px;">
                </td>
            </tr>
        </table>
    </td>
    <td valign="top" class="edittext" align="left">
        <a href="http://checkout.rakuten.de" target="_blank"><img src="../modules/rakuten/images/payment_banner_small.png" border="0"></a>
    </td>
</tr>
</table>

</form>

[{include file="bottomnaviitem.tpl"}]

[{include file="bottomitem.tpl"}]
