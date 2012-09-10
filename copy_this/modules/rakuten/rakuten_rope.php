<?php
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
class rakuten_rope extends rakuten_rope_parent
{
    /**
     * Default log filename
     *
     * @var string
     */
    const DEFAULT_LOG_FILE = 'payment_rakuten_rope.log';

    /**
     * ROPE request data
     *
     * @var SimpleXMLElement|string
     */
    protected $_request = null;

    /**
     * XML node to access ordered items
     *
     * @var string
     */
    protected $_orderNode = '';

    /**
     * Collected debug information
     *
     * @var array
     */
    protected $_debugData = array();

    /**
     * Get ROPE data, run corresponding handler
     *
     * @param  string $request - incoming XML request
     * @return string
     * @throws Exception
     */
    public function processRopeRequest($request)
    {
        $this->_request = $request;

        try {
            $this->_request = new SimpleXMLElement(urldecode($request), LIBXML_NOCDATA);

            /**
             * Check type of request and call proper handler 
             */
            switch ($this->_request->getName()) {
                case 'tradoria_check_order':
                    $this->_orderNode = 'order';
                    $responseTag = 'tradoria_check_order_response';
                    $response = $this->_checkOrder();
                    break;
                case 'tradoria_order_process':
                    $this->_orderNode = 'cart';
                    $responseTag = 'tradoria_order_process_response';
                    $response = $this->_processOrder();
                    break;
                case 'tradoria_order_status':
                    $responseTag = 'tradoria_order_status_response';
                    $response = $this->_statusUpdate();
                    break;
                default:
                    /**
                     * Error - Unrecognized request 
                     */
                    $responseTag = 'unknown_error';
                    $response = false;
            }
        } catch (Exception $e) {
            return $this->prepareResponse(false);
        }

        return $this->prepareResponse($response, $responseTag);
    }

    /**
     * Prepare XML response
     *
     * @param  bool $success - if need to prepare successful or unsuccessful response
     * @param  string $tag - root node tag for the response
     * @return string
     */
    public function prepareResponse($success, $tag = 'general_error')
    {
        if ($success === true) {
            $success = 'true';
        } elseif ($success === false) {
            $success = 'false';
        } else {
            $success = (string)$success;
        }

        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?><{$tag} />");
        $xml->addChild('success', $success);
        $response = $xml->asXML();

        return $response;
    }

    /**
     * Validate authentication data passed in the request against configuration values
     *
     * @return bool
     */
    protected function _auth()
    {
        $projectId = $this->getConfig()->getShopConfVar('sRakutenProjectId', -1);
        $apiKey = $this->getConfig()->getShopConfVar('sRakutenApiKey', -1);

        if ($this->_request->merchant_authentication->project_id == $projectId
            && $this->_request->merchant_authentication->api_key == $apiKey) {
            return true;
        }

        return false;
    }

    /**
     * Compare Oxid basket and shopping cart details from the request
     *
     * @return bool
     */
    protected function _validateQuote()
    {
        $quoteItems = $this->getSession()->getBasket()->_aBasketContents;

        $quoteItemsArray = array();
        $quoteItemsSku = array();

        /** @var $xmlItems SimpleXMLElement */
        $xmlItems = $this->_request->{$this->_orderNode}->items;

        $xmlItemsArray = array();
        $xmlItemsSku = array();

        foreach ($quoteItems as $item) {
            /** @var $item oxBasketItem */
            $quoteItemsArray[(string)$item->getArticle()->oxarticles__oxartnum->value] = $item;
            $quoteItemsSku[] = (string)$item->getProductId();
        }

        foreach ($xmlItems->children() as $item) {
            /** @var $item SimpleXMLElement */
            $xmlItemsArray[(string)$item->sku] = $item;
            $xmlItemsSku[] = (string)$item->sku;
        }

        $this->_debugData['quoteItemsSku'] = implode(', ', $quoteItemsSku);
        $this->_debugData['xmlItemsSku'] = implode(', ', $xmlItemsSku);

        /**
         *  Validation of the shopping cart
         */
        if (count($quoteItemsArray) != count($xmlItemsArray)) {
            return false;
        }

        foreach ($quoteItemsArray as $sku=>$item) {
            if (!isset($xmlItemsArray[$sku])) {
                return false;
            }
            $xmlItem = $xmlItemsArray[$sku];
            if ($item->getAmount() != (int)$xmlItem->qty
                || round($item->getUnitPrice()->getBruttoPrice(), 2) != round((float)$xmlItem->price, 2)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check qty in stock/product availability.
     * Called by Rakuten Checkout before order placement
     *
     * @return bool
     */
    protected function _checkOrder()
    {
        if (!$this->_auth()) {
            return false;
        }

        if (!$this->_validateQuote()) {
            return false;
        }

        /** @var $oOrder oxorder */
        $oOrder = oxNew( 'oxorder' );

        try {
            $oOrder->validateStock($this->getSession()->getBasket());
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Place the order
     *
     * @return bool
     */
    protected function _processOrder()
    {
        if (!$this->_auth()) {
            return false;
        }

        if (!$this->_validateQuote()) {
            return false;
        }

        try {
            /** @var $oUser oxUser */
            $oUser = oxNew('oxuser');
            $oUser->loadActiveUser();

            /** @var $oOrder oxOrder */
            $oOrder = oxNew('oxorder');

            /** @var $oCountry oxCountry */
            $oCountry = oxNew('oxcountry');

            $address = $this->_request->client; /** Billing Address **/

            $oUser->oxuser__oxcompany   = new oxField((string)$address->company);
            $oUser->oxuser__oxusername  = new oxField((string)$address->email);
            $oUser->oxuser__oxfname     = new oxField((string)$address->first_name);
            $oUser->oxuser__oxlname     = new oxField((string)$address->last_name);
            $oUser->oxuser__oxstreet    = new oxField((string)$address->street);
            $oUser->oxuser__oxstreetnr  = new oxField((string)$address->street_no);
            $oUser->oxuser__oxaddinfo   = new oxField((string)$address->address_add);
            $oUser->oxuser__oxustid     = new oxField('');
            $oUser->oxuser__oxcity      = new oxField((string)$address->city);

            $sCountryId = $oUser->getUserCountryId((string)$address->country);
            $oUser->oxuser__oxcountryid = new oxField($sCountryId ? $sCountryId : (string)$address->country);

            $oUser->oxuser__oxstateid   = new oxField('');
            $oUser->oxuser__oxzip       = new oxField((string)$address->zip_code);
            $oUser->oxuser__oxfon       = new oxField((string)$address->phone);
            $oUser->oxuser__oxfax       = new oxField('');

            switch ((string)$address->gender) {
                case 'Herr':
                    $sGender = 'MR';
                    break;
                case 'Frau':
                    $sGender = 'MRS';
                    break;
                default:
                    $sGender = '';
            }
            $oUser->oxuser__oxsal       = new oxField($sGender);

            $address = $this->_request->delivery_address; /** Shipping Address **/

            $oOrder->oxorder__oxdelcompany  = new oxField((string)$address->company);
            $oOrder->oxorder__oxdelfname    = new oxField((string)$address->first_name);
            $oOrder->oxorder__oxdellname    = new oxField((string)$address->last_name);
            $oOrder->oxorder__oxdelstreet   = new oxField((string)$address->street);
            $oOrder->oxorder__oxdelstreetnr = new oxField((string)$address->street_no);
            $oOrder->oxorder__oxdeladdinfo  = new oxField((string)$address->address_add);
            $oOrder->oxorder__oxdelcity     = new oxField((string)$address->city);

            $sCountryId = $oUser->getUserCountryId((string)$address->country);
            $oOrder->oxorder__oxdelcountryid= new oxField($sCountryId ? $sCountryId : (string)$address->country);
            // $oOrder->oxorder__oxdelcountry   = new oxField($oUser->getUserCountry($sCountryId));

            $oOrder->oxorder__oxdelstateid  = new oxField('');
            $oOrder->oxorder__oxdelzip      = new oxField((string)$address->zip_code);
            $oOrder->oxorder__oxdelfon      = new oxField((string)$address->phone);
            $oOrder->oxorder__oxdelfax      = new oxField('');

            switch ((string)$address->gender) {
                case 'Herr':
                    $sGender = 'MR';
                    break;
                case 'Frau':
                    $sGender = 'MRS';
                    break;
                default:
                    $sGender = '';
            }
            $oOrder->oxorder__oxdelsal      = new oxField($sGender);

            $sGetChallenge = oxSession::getVar( 'sess_challenge' );
            $oOrder->setId($sGetChallenge);

            $oOrder->oxorder__oxfolder = new oxField(key($this->getConfig()->getShopConfVar('aOrderfolder', $this->getConfig()->getShopId())), oxField::T_RAW);

            $message = '';

            if (trim((string)$this->_request->comment_client) != '') {
                $message .= sprintf('Customer\'s Comment: %s', trim((string)$this->_request->comment_client) . " // \n");
            }

            $message .= sprintf('Rakuten Order No: %s', (string)$this->_request->order_no . " // \n")
                        . sprintf('Rakuten Client ID: %s', (string)$this->_request->client->client_id);

            $oOrder->oxorder__oxremark = new oxField($message, oxField::T_RAW);

            $res = $oOrder->finalizeOrder($this->getSession()->getBasket(), $oUser, true);

            if ($res == 1) { // OK
                $oOrder->oxorder__oxpaymenttype     = new oxField('rakuten');
                $oOrder->oxorder__oxpaymentid       = new oxField();
                $oOrder->oxorder__oxtransid         = new oxField((string)$this->_request->order_no);
                $oOrder->oxorder__oxtransstatus     = new oxField('New');

                $oOrder->oxorder__oxartvatprice1    = new oxField((float)$this->_request->total_tax_amount, oxField::T_RAW);
                $oOrder->oxorder__oxartvatprice2    = new oxField(0, oxField::T_RAW);
                $oOrder->oxorder__oxdelcost         = new oxField((float)$this->_request->shipping, oxField::T_RAW);
                $oOrder->oxorder__oxpaycost         = new oxField(0, oxField::T_RAW);
                $oOrder->oxorder__oxwrapcost        = new oxField(0, oxField::T_RAW); // TODO: support gift wrapping somehow
                $oOrder->oxorder__oxdiscount        = new oxField(0, oxField::T_RAW);

                $subtotal = (float)$this->_request->total - (float)$this->_request->total_tax_amount - (float)$this->_request->shipping;
                $oOrder->oxorder__oxtotalnetsum     = new oxField($subtotal, oxField::T_RAW);
                $oOrder->oxorder__oxtotalbrutsum    = new oxField($subtotal + (float)$this->_request->total_tax_amount, oxField::T_RAW);
                $oOrder->oxorder__oxtotalordersum   = new oxField((float)$this->_request->total, oxField::T_RAW);

                $oOrder->save();

                $this->getSession()->getBasket()->deleteBasket();
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Update order status (create invoice, shipment, cancel the order)
     *
     * @return bool
     */
    protected function _statusUpdate()
    {
        if (!$this->_auth()) {
            return false;
        }

        try {
            $rakuten_order_no = (string)$this->_request->order_no;

            /** @var $oOrder oxOrder */
            $oOrder = oxNew('oxorder');
            /**
             * Copy&paste from oxBase::load() to load order by Rakuten order number
             */
            $aSelect = $oOrder->buildSelectString(array($oOrder->getViewName().'.oxtransid' => $rakuten_order_no));
            $oOrder->assignRecord($aSelect);

            /**
              * Check if order exists
              */
            if (!$oOrder->getId()) {
                return false;
            }

            $status = (string)$this->_request->status;

            switch ($status) {
                case 'editable':
                    /** Processing **/
                    $oOrder->oxorder__oxtransstatus = new oxField('Processing');
                    $oOrder->save();
                    break;
                case 'shipped':
                    /** Shipped **/
                    $oOrder->oxorder__oxtransstatus = new oxField('Shipped');
                    $oOrder->save();
                    break;
                case 'cancelled':
                    /** Cancelled **/
                    $oOrder->oxorder__oxtransstatus = new oxField('Cancelled');
                    $oOrder->save();
                    break;
                default:
                    /** Error - Unrecognized request **/
                    $oOrder->oxorder__oxtransstatus = new oxField('Unknown');
                    $oOrder->save();
                    return false;
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
