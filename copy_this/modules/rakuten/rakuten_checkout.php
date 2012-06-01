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

class rakuten_checkout extends rakuten_checkout_parent /* extends oxbasket */
{
    const ROCKIN_SANDBOX_URL            = 'https://sandbox.rakuten-checkout.de/rockin';
    const ROCKIN_LIVE_URL               = 'https://secure.rakuten-checkout.de/rockin';

    const RAKUTEN_PIPE_URL              = 'https://images.rakuten-checkout.de/images/files/pipe.html';

    /**
     * Tax class mapping
     *
     * @var array
     */
    public $taxClassMap = array(
        '1' => 0,       /** DE 0% **/
        '2' => 7,       /** DE 7% **/
        '3' => 10.7,    /** DE 10.7% **/
        '4' => 19,      /** DE 19% **/       
        '6' => 10,      /** AT 10% **/
        '7' => 12,      /** AT 12% **/
        '8' => 20,      /** AT 20% **/
    );

    /**
     * Default tax class
     *
     * @var string
     */
    public $taxClassDefault = '4';

    function _strGetCSV($input, $delimiter=',', $enclosure='"', $escape=null, $eol=null)
    {
        $temp=fopen("php://memory", "rw");
        fwrite($temp, $input);
        fseek($temp, 0);
        $r = array();
        while (($data = fgetcsv($temp, 4096, $delimiter, $enclosure)) !== false) {
            $r[] = $data;
        }
        fclose($temp);
        return $r;
    }

    /**
     * Convert encoding of the string to UTF-8
     * and escape ampersands in the string for XML
     * (required by addChild() simpleXML function)
     *
     * @param  string $string
     * @return string
     */
    protected function _escapeStr($string)
    {
        $string = mb_convert_encoding($string, 'UTF-8');
        $string = str_replace('&', '&amp;', $string);
        return $string;
    }

    /**
     * Add CDATA to simpleXML node
     *
     * @param  SimpleXMLElement $node
     * @param  string $value
     * @return void
     */
    protected function _addCDATA($node, $value)
    {
        $value = mb_convert_encoding($value, 'UTF-8');
        $domNode = dom_import_simplexml($node);
        $domDoc = $domNode->ownerDocument;
        $domNode->appendChild($domDoc->createCDATASection($value));
    }

    /**
     * Get redirect URL or inline iFrame code
     *
     * @param  bool $inline
     * @return bool
     * @throws Exception|oxException
     */
    public function getRedirectUrl($inline = false)
    {      

        /**
         *  Create Rakuten Checkout Insert Cart XML request
         */
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?><tradoria_insert_cart />");

        $merchantAuth = $xml->addChild('merchant_authentication');       
        $merchantAuth->addChild('project_id', $this->getConfig()->getShopConfVar('sRakutenProjectId', -1));
        $merchantAuth->addChild('api_key', $this->getConfig()->getShopConfVar('sRakutenApiKey', -1));


        $sLanguageCode = oxLang::getInstance()->getLanguageAbbr();
        if ($sLanguageCode) {
            $sLanguageCode = strtoupper($sLanguageCode);
        }
        if ($sLanguageCode != 'DE') {
            $sLanguageCode = 'DE';
        }

        $sCurrency = $this->getSession()->getBasket()->_oCurrency->name;
        if ($sCurrency != 'EUR') {
            die('Unsupported currency');
        }

        $xml->addChild('language', $sLanguageCode);
        $xml->addChild('currency', $sCurrency);

        $merchantCart = $xml->addChild('merchant_carts')->addChild('merchant_cart');

        $sSessionName = $this->getSession()->getName();
        $sSessionId = $this->getSession()->getId();

        $merchantCart->addChild('custom_1', $sSessionName);
        $merchantCart->addChild('custom_2', $sSessionId);
        $merchantCart->addChild('custom_3');
        $merchantCart->addChild('custom_4');

        $merchantCartItems = $merchantCart->addChild('items');

        $items = $this->getSession()->getBasket()->_aBasketContents;

        /** @var $item oxBasketItem */
        foreach ($items as $item) {
            $merchantCartItemsItem = $merchantCartItems->addChild('item');

            $merchantCartItemsItemName = $merchantCartItemsItem->addChild('name');
            $this->_addCDATA($merchantCartItemsItemName, $item->getTitle());

            $merchantCartItemsItem->addChild('sku', $this->_escapeStr($item->getProductId()));
            $merchantCartItemsItem->addChild('external_product_id');
            $merchantCartItemsItem->addChild('qty', $item->getAmount());
            $merchantCartItemsItem->addChild('unit_price', $item->getUnitPrice()->getBruttoPrice());
            $merchantCartItemsItem->addChild('tax_class', $this->getRakutenTaxClass($item->getPrice()->getVat()));
            $merchantCartItemsItem->addChild('image_url', $this->_escapeStr($item->getIconUrl()));
            $merchantCartItemsItem->addChild('product_url', $this->_escapeStr($item->getLink()));

            $options = $item->getVarSelect();
            if (!empty($options)) {
                $custom = $options;
                $comment = $options;
            } else {
                $custom = '';
                $comment = '';
            }

            $merchantCartItemsItemComment = $merchantCartItemsItem->addChild('comment');
            $this->_addCDATA($merchantCartItemsItemComment, $comment);

            $merchantCartItemsItemCustom = $merchantCartItemsItem->addChild('custom');
            $this->_addCDATA($merchantCartItemsItemCustom, $custom);
        }

        $merchantCartShippingRates = $merchantCart->addChild('shipping_rates');

        $shippingRates = $this->_strGetCSV($this->getConfig()->getShopConfVar('sRakutenShippingRates', -1));

        foreach ($shippingRates as $shippingRate) {
            if (isset($shippingRate[0]) && isset($shippingRate[1]) && is_numeric($shippingRate[1])) {                
                $merchantCartShippingRate = $merchantCartShippingRates->addChild('shipping_rate');
                $merchantCartShippingRate->addChild('country', (string)$shippingRate[0]);
                $merchantCartShippingRate->addChild('price', (float)$shippingRate[1]);
                if (isset ($shippingRate[2]) && (int)$shippingRate[2]>0) {
                    $merchantCartShippingRate->addChild('delivery_date', date('Y-m-d', strtotime('+' . (int)$shippingRate[2] . ' days')));
                }
            }
        }

        $billingAddressRestrictions = $xml->addChild('billing_address_restrictions');
        /**
         * Restrict invoice address to require private / commercial and by country
         */
        $billingAddressRestrictions->addChild('customer_type')->addAttribute('allow', $this->getConfig()->getShopConfVar('iRakutenBillingAddr', -1));
        
        $aCountries = array();

        /** @var $oCountryList oxCountryList */
        $oCountryList = oxNew('oxcountrylist');
        $oCountryList->loadActiveCountries();

        /** @var $oCountry oxCountry */
        foreach ($oCountryList as $sCountryId => $oCountry) {
            $oCountry->load($sCountryId);
            $aCountries[] = $oCountry->oxcountry__oxisoalpha2->value;
        }

        if (!empty($aCountries)) {
            $billingAddressRestrictions->addChild('countries')->addAttribute('allow', implode(',', $aCountries));
        }

        
        $baseUrl = $this->getConfig()->getSslShopUrl();
        /**
         *  Force SID for ROPE URL to load shopping cart data and flush it when order is saved
         */
        $ropeUrl = $baseUrl . oxUtilsUrl::getInstance()->processUrl('index.php', true, array('cl'=>'rakuten', 'fnc'=>'rope'));
        /**
         *  No forced SID for PIPE URL to avoid session switches after opening Rakuten Checkout iFrame
         */
        $pipeUrl = oxUtilsUrl::getInstance()->processUrl($baseUrl . 'index.php', true, array('cl'=>'rakuten', 'fnc'=>'pipe'));

        $xml->addChild('callback_url', $ropeUrl);
        $xml->addChild('pipe_url', $pipeUrl);

        $request = $xml->asXML();

        $response = $this->sendRequest($request);

        if (!$response) {
            return false;
        }

        $redirectUrl = false;
        $inlineUrl = false;
        $inlineCode = false;

        try {
            $response = new SimpleXMLElement($response);

            if ($response->success != 'true') {
                throw new oxException((string)$response->message, (int)$response->code);
            } else {
                $redirectUrl = $response->redirect_url;
                $inlineUrl = $response->inline_url;
                $inlineCode = $response->inline_code;
            }
        } catch (oxException $e) {
            oxUtilsView::getInstance()->addErrorToDisplay(sprintf('Error #%s: %s', $e->getCode(), $e->getMessage()));
            return false;
        } catch (Exception $e) {
            oxUtilsView::getInstance()->addErrorToDisplay('Unable to redirect to Rakuten Checkout.');
            return false;
        }

        if ($inline) {
            return $inlineCode;
        } else {
            return $redirectUrl;
        }
    }

    /**
     * Send request to Rakuten Checkout
     *
     * @param  string $xml
     * @return array|bool|string
     * @throws Exception
     */
    public function sendRequest($xml)
    {
        try {
            $rockinUrl = $this->getRockinUrl();
				/**
				 * Setting the curl parameters. 
				 */
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rockinUrl);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);

            /**
             * Setting the request
             */
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            /**
             * Getting response from server
             */
            $response = curl_exec($ch);

            if(curl_errno($ch)) {
                throw new Exception(curl_error($ch), curl_errno($ch));                
            } else {
                curl_close($ch);
            }
        } catch (Exception $e) {
            oxUtilsView::getInstance()->addErrorToDisplay(sprintf('CURL Error #%s: %s', $e->getCode(), $e->getMessage()));
            return false;
        }
        
        return $response;
    }

    /**
     * Get API request URL on Rakuten Checkout side
     * Get either Live or Sandbox Rockin URL based on current configuration settings
     *
     * @return string
     */
    public function getRockinUrl()
    {
        if ($this->getConfig()->getShopConfVar('blRakutenSandboxMode', -1)) {
            return self::ROCKIN_SANDBOX_URL;
        } else {
            return self::ROCKIN_LIVE_URL;
        }
    }

    /**
     * Get Pipe Source URL for Inline integration method
     * (to avoid cross-domain iframe resize restrictions)
     *
     * @return string
     */
    public function getRakutenPipeUrl()
    {
        return self::RAKUTEN_PIPE_URL;
    }

    /**
     * Check if current currency is supported by Rakuten Checkout
     *
     * @param  float $percent
     * @return string
     */
    public function getRakutenTaxClass($percent)
    {
        if ($taxClass = array_search($percent, $this->taxClassMap)) {
            return $taxClass;
        } else {
            return $this->taxClassDefault;
        }
    }
}