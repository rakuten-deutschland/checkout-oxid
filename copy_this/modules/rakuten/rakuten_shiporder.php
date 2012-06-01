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

class rakuten_shiporder extends rakuten_shiporder_parent
{
    const ROCKIN_SHIPMENT_SANDBOX_URL   = 'https://sandbox.rakuten-checkout.de/rockin/shipment';
    const ROCKIN_SHIPMENT_LIVE_URL      = 'https://secure.rakuten-checkout.de/rockin/shipment';

    public function sendorder()
    {
        /** @var $oOrder oxOrder */
        $oOrder = oxNew( "oxorder" );
        $isSent = true;
        if ($oOrder->load($this->getEditObjectId())) {
            if ($oOrder->oxorder__oxpaymenttype->value == 'rakuten') {
                $isSent = $this->sendShipment($oOrder);
            }
        }
        if ($isSent) {
            parent::sendorder();
        }
    }

    /**
     * Send order shipment to Rakuten Checkout
     *
     * @param  oxOrder $oOrder
     * @return bool
     * @throws Exception|oxException
     */
    public function sendShipment($oOrder)
    {
        /** 
         * Create Rakuten Checkout Send Order Shipment XML request
         * @var SimpleXMLElement $xml
         */
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?><tradoria_order_shipment />");

        $merchantAuth = $xml->addChild('merchant_authentication');
        $merchantAuth->addChild('project_id', $this->getConfig()->getShopConfVar('sRakutenProjectId', -1));
        $merchantAuth->addChild('api_key', $this->getConfig()->getShopConfVar('sRakutenApiKey', -1));

        $xml->addChild('order_no', $oOrder->oxorder__oxtransid->value);

        /** In sendShipment() method **/
        $delSet = (oxConfig::getParameter( "setDelSet" )?oxConfig::getParameter( "setDelSet" ):'andere');
        $trackCode = oxConfig::getParameter( "oxorder__oxtrackcode" );
        $xml->addChild('carrier_tracking_id', $delSet);
        $xml->addChild('carrier_tracking_url');
        $xml->addChild('carrier_tracking_code', $trackCode);

        $request = $xml->asXML();
        $response = $this->sendShipmentRequest($request);

        try {
            $response = @ new SimpleXMLElement($response);

            if ($response->success != 'true') {
                throw new oxException((string)$response->message, (int)$response->code);
            } else {
                if ((string)$response->invoice_number != '') {
                    $oOrder->oxorder__oxremark = new oxField($oOrder->oxorder__oxremark->value . " // \n"
                                                             . sprintf('Rakuten Invoice No: %s', (string)$response->invoice_number), oxField::T_RAW);
                    $oOrder->save();
                }
            }
        } catch (Exception $e) {
            if ($e->getCode()) {
                $error_code = $e->getCode();
            } else {
                $error_code = '000';
            }
            if ($e->getMessage()) {
                $error_message = $e->getMessage();
            } else {
                $error_message = 'Unknown error';
            }
            oxUtilsView::getInstance()->addErrorToDisplay('Error sending shipment to Rakuten Checkout. Shipment wasn\'t sent.');
            oxUtilsView::getInstance()->addErrorToDisplay(sprintf('Error #%s: %s', $error_code, $error_message));
            return false;
        }

        return true;
    }

    /**
     * Send request to Rakuten Checkout
     *
     * @param  string $xml
     * @return array|bool|string
     * @throws Exception
     */
    public function sendShipmentRequest($xml)
    {
        try {
            $rockinUrl = $this->getShipmentRockinUrl();

            /** Setting the curl parameters. **/
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rockinUrl);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);

            /** Setting the request **/
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            /** Getting response from server **/
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
    public function getShipmentRockinUrl()
    {
        if ($this->getConfig()->getShopConfVar('blRakutenSandboxMode', -1)) {
            return self::ROCKIN_SHIPMENT_SANDBOX_URL;
        } else {
            return self::ROCKIN_SHIPMENT_LIVE_URL;
        }
    }

    /**
     * executes parent mathod parent::render(), creates oxorder, passes
     * it's data to Smarty engine and returns name of template file
     * "order_overview.tpl".
     *
     * @return string
     */
    public function render()
    {
        $soxId = $this->getEditObjectId();
        if ( $soxId != "-1" && isset( $soxId)) {
            $this->_aViewData["oShipSet"] = array(
                'andere' => 'andere',
                'dhl'    => 'dhl',
                'ups'    => 'ups',
                'dpd'    => 'dpd',
                'hermes' => 'hermes',
                'gls'    => 'gls'
            );
        }

        return parent::render();
    }
}