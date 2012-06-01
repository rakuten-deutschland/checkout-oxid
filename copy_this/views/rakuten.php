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

class Rakuten extends oxUBase
{
    protected $_sBasketUrl = null;

    protected $_sThisTemplate = 'layout/page.tpl';

    public function render()
    {
        /** @var $oRakuten rakuten_checkout */
        $oRakuten = oxNew('rakuten_checkout');

        /** Check which method will be used: Standard or Inline **/
        if ($this->getConfig()->getShopConfVar('sRakutenIntegrationMethod', -1) == 'STANDARD') {
            /** Redirect to Rakuten Checkout **/
            if ($redirectUrl = $oRakuten->getRedirectUrl()) {
                /** Received redirect URL **/
                oxUtils::getInstance()->redirect($redirectUrl, false, 302);
            } else {
                /** Error returned, redirecting to the shopping cart **/
                oxUtils::getInstance()->redirect($this->_getBasketUrl(), false, 302);
            }
        } elseif ($this->getConfig()->getShopConfVar('sRakutenIntegrationMethod', -1) == 'INLINE') {
            /** Inline integration (iFrame) **/
            if ($inlineCode = $oRakuten->getRedirectUrl(true)) {
                /** Loading iFrame **/
                $this->_aViewData['oxidBlock_content'][] = $inlineCode;
                return parent::render();
            } else {
                /** Error returned, redirecting to the shopping cart **/
                oxUtils::getInstance()->redirect($this->_getBasketUrl(), false, 302);
            }
        } else {
            /** Unknown integration method **/
            oxUtilsView::getInstance()->addErrorToDisplay('Unknown integration method.');
            oxUtils::getInstance()->redirect($this->_getBasketUrl(), false, 302);
        }

        return false;
    }

    /**
     * Returns shopping cart URL
     *
     * @return string
     */
    protected function _getBasketUrl()
    {
        if (is_null($this->_sBasketUrl)) {
            $homeUrl = $this->getConfig()->getShopHomeURL();
            $basketUrl = oxUtilsUrl::getInstance()->processUrl($homeUrl, true, array('cl'=>'basket'));
            if (oxUtils::getInstance()->seoIsActive()) {
                if ($sStaticUrl = oxSeoEncoder::getInstance()->getStaticUrl($basketUrl)) {
                    $basketUrl = $sStaticUrl;
                } else {
                    $basketUrl = oxUtilsUrl::getInstance()->processUrl($basketUrl);
                }
            }
            $this->_sBasketUrl = $basketUrl;
        }
        return $this->_sBasketUrl;
    }

    /**
     * Returns Bread Crumb - you are here page1/page2/page3...
     *
     * @return array
     */
    public function getBreadCrumb()
    {
        $aPath = array();
        $aPath['title'] = $this->getBreadCrumbTitle(); 
        $aPath['link']  = $this->getLink();
        return array($aPath);
    }

    /**
     * Returns Bread Crumb text
     *
     * @return array
     */
    public function getBreadCrumbTitle()
    {
        $aTitle = oxLang::getInstance()->translateString('Rakuten Checkout', oxLang::getInstance()->getBaseLanguage(), false);
        return $aTitle;
    }

    /**
     * Pipe action to read pipe script contents
     * (to avoid cross-domain iframe resize restrictions)
     *
     * @return void
     */
    public function pipe()
    {
        /** @var $oRakuten rakuten_checkout */
        $oRakuten = oxNew('rakuten_checkout');
        $pipeUrl = $oRakuten->getRakutenPipeUrl();

        // TODO: add caching of pipe script
        $pipe = file_get_contents($pipeUrl);

        echo $pipe;
        die;
    }

    /**
     * Process ROPE requests
     *
     * @return void
     */
    public function rope()
    {
        /** @var $oRakutenRope rakuten_rope */
        $oRakutenRope = oxNew('rakuten_rope');

        if (oxUtilsServer::getInstance()->getServerVar('REQUEST_METHOD') != 'POST') {
            die;
        }

        $request = file_get_contents('php://input');

        if (empty($request)) {
            die;
        }

        try {
            /** Process ROPE request and output response **/
            echo $oRakutenRope->processRopeRequest($request);
        } catch (Exception $e) {
            /** TODO: Log exception and show 404 **/
        }
        die;
    }
}