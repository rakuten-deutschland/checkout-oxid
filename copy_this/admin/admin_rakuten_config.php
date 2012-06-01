<?php
/**
 * Copyright (c) 2012, Rakuten Deutschland GmbH. All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without
 *	modification, are permitted provided that the following conditions are met:
 *
 * 	 * Redistributions of source code must retain the above copyright
 *  	   notice, this list of conditions and the following disclaimer.
 * 	 * Redistributions in binary form must reproduce the above copyright
 *   	   notice, this list of conditions and the following disclaimer in the
 *   	   documentation and/or other materials provided with the distribution.
 * 	 * Neither the name of the Rakuten Deutschland GmbH nor the
 *   	   names of its contributors may be used to endorse or promote products
 *   	   derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL RAKUTEN DEUTSCHLAND GMBH BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING
 * IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

class Admin_Rakuten_Config extends Shop_Config
{
    protected $_sVersion = '1.0.4';

    /**
     * Current class template name.
     * @var string
     */
    protected $_sThisTemplate = 'admin_rakuten_config.tpl';

    /**
     * Keeps all act. fields to store
     */
    protected $_aFieldArray = null;

    public function getVersion()
    {
        return 'ver. ' . $this->_sVersion;
    }

    /**
     * Saves payment parameters changes.
     *
     * @return mixed
     */
    public function save()
    {
        $oDb = oxDb::getDb();
        $aParams = oxConfig::getParameter("confbools");

        /**
         * Deleting old blocks for Tradoria Checkout ver.1.0.2 and updating payment type for old Tradoria Checkout orders
         */
        $oDb->execute("DELETE `oxtplblocks` WHERE `OXID` IN ('tradoria_btn_top', 'tradoria_btn_bottom', 'tradoria_btn_minicart');");
        $oDb->execute("UPDATE `oxorder` SET `OXPAYMENTTYPE` = 'rakuten' WHERE `OXPAYMENTTYPE`='tradoria';");

        if ($aParams['blRakutenActive'] == 'true') {
            $oDb->execute("INSERT INTO `oxtplblocks` (`OXID`, `OXACTIVE`, `OXSHOPID`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE`, `OXMODULE`) VALUES ('rakuten_btn_top', '1', 'oxbaseshop', 'page/checkout/basket.tpl', 'basket_btn_next_top', '0', 'button', 'rakuten') ON DUPLICATE KEY UPDATE `OXACTIVE` = 1;");
            $oDb->execute("INSERT INTO `oxtplblocks` (`OXID`, `OXACTIVE`, `OXSHOPID`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE`, `OXMODULE`) VALUES ('rakuten_btn_bottom', '1', 'oxbaseshop', 'page/checkout/basket.tpl', 'basket_btn_next_bottom', '0', 'button', 'rakuten') ON DUPLICATE KEY UPDATE `OXACTIVE` = 1;");
            $oDb->execute("INSERT INTO `oxtplblocks` (`OXID`, `OXACTIVE`, `OXSHOPID`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE`, `OXMODULE`) VALUES ('rakuten_btn_minicart', '1', 'oxbaseshop', 'widget/minibasket/minibasket.tpl', 'widget_minibasket_total', '0', 'button', 'rakuten') ON DUPLICATE KEY UPDATE `OXACTIVE` = 1;");
        } else {
            $oDb->execute("UPDATE `oxtplblocks` SET `OXACTIVE` = 0 WHERE `OXID` IN ('rakuten_btn_top', 'rakuten_btn_bottom', 'rakuten_btn_minicart');");
        }

        parent::save();

        /**
         * Refresh Smarty cache to hide/show Rakuten Checkout button on the front-end 
         */
        $smarty = oxUtilsView::getInstance()->getSmarty();
        $smarty->clear_all_cache();
    }
}
