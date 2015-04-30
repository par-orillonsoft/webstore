<?php

/**
 * Class cayan
 */
class cayan extends WsPayment
{
	protected $defaultName = "Cayan (MerchantWare)";
	protected $version = 1.0;
	protected $uses_credit_card = true;
	protected $apiVersion = 1;
	public $cloudCompatible = true;

	/**
	 * The run() function is called from Web Store to run the process.
	 * @return array
	 */
	public function run()
	{
		$arrResponse = $this->createTransaction();

		if ($arrResponse['success'] != 1)
		{
			// An error occurred. Return the response as is.
			return $arrResponse;
		}

		$url = sprintf(
			'https://transport.merchantware.net/v4/transportweb.aspx?transportKey=%s',
			$arrResponse['transportKey']
		);

		return array(
			'api' => $this->apiVersion,
			'jump_url' => $url
		);
	}

	/**
	 * https://ps1.merchantware.net/Merchantware/documentation40/transport/overview_TransportRequest.aspx
	 * Creates and sends a request to store transaction data for later use.
	 *
	 * https://ps1.merchantware.net/Merchantware/documentation40/transport/overview_TransportResponse.aspx
	 * Returns a token that we use to reference that data when we need it.
	 *
	 * @return array
	 */
	public function createTransaction()
	{
		// Cayan expects no dashes in WO number
		$wo = str_replace("-", "", $this->objCart->id_str);

		// Cayan requires a unique ClerkId. So we use the CID or Cloud id here.
		// See the overview of the TransportRequest via the link provided in
		// the doc block of the function for more information.
		$clerkId = Yii::app()->params['LIGHTSPEED_CID'];
		if (Yii::app()->params['LIGHTSPEED_CLOUD'] > 0)
		{
			$clerkId = Yii::app()->params['LIGHTSPEED_CLOUD'];
		}

		// set the display options
		$this->setDisplayOptions();
		$dontMaskCardNumber = $this->config['customConfig']['maskCardNumber'] == 1 ? 'false' : 'true';
		$hideInstructions = $this->config['customConfig']['hideInstructions'] == 1 ? 'true' : 'false';
		$hideDowngradeMessage = $this->config['customConfig']['hideDowngradeMessage'] == 1 ? 'true' : 'false';

		$redirectUrl = Yii::app()->controller->createAbsoluteUrl('cart/payment', array('id' => __CLASS__));

		// Cayan will not accept names or the address with accents in their form. Rather than have the
		// end user experience an error message and have to manually adjust it, we'll remove the accents beforehand.
		$cardholder = _xls_replaceAccents($this->CheckoutForm->contactFirstName . ' ' . $this->CheckoutForm->contactLastName);
		$billingAddress = _xls_replaceAccents($this->CheckoutForm->billingAddress1);

		// Construct SOAP object
		$xmlData =
			'<?xml version="1.0" encoding="utf-8"?>
				<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
					xmlns:xsd="http://www.w3.org/2001/XMLSchema"
					xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
					<soap:Body>
						<CreateTransaction xmlns="http://transport.merchantware.net/v4/">
							<merchantName>'.$this->config['name'].'</merchantName>
							<merchantSiteId>'.$this->config['siteId'].'</merchantSiteId>
							<merchantKey>'.$this->config['transKey'].'</merchantKey>
							<request>
								<TransactionType>SALE</TransactionType>
								<Amount>'.$this->objCart->total.'</Amount>
								<OrderNumber>'.$wo.'</OrderNumber>
								<AddressLine1>'.$billingAddress.'</AddressLine1>
								<Zip>'.$this->CheckoutForm->billingPostal.'</Zip>
								<Cardholder>'.$cardholder.'</Cardholder>
								<LogoLocation>'.$this->config['logoUrl'].'</LogoLocation>
								<RedirectLocation>'.$redirectUrl.'</RedirectLocation>
								<ClerkId>'.$clerkId.'</ClerkId>
								<Dba>'.Yii::app()->params['STORE_NAME'].'</Dba>
								<SoftwareName>Web Store eCommerce solution for Lightspeed Pos</SoftwareName>
								<SoftwareVersion>'.XLSWS_VERSION.'</SoftwareVersion>
								<TaxAmount>'.$this->objCart->TaxTotal.'</TaxAmount>
								<DisplayColors>
									<ScreenBackgroundColor></ScreenBackgroundColor>
									<ContainerBackgroundColor>'.$this->config['customConfig']['colorContainerBackground'].'</ContainerBackgroundColor>
									<ContainerBorderColor>'.$this->config['customConfig']['colorContainerBorder'].'</ContainerBorderColor>
									<LogoBackgroundColor>'.$this->config['customConfig']['colorLogoBackground'].'</LogoBackgroundColor>
									<LogoBorderColor>'.$this->config['customConfig']['colorLogoBorder'].'</LogoBorderColor>
									<TextboxBorderColor>'.$this->config['customConfig']['colorTextBoxBorder'].'</TextboxBorderColor>
									<TextboxFocusBorderColor>'.$this->config['customConfig']['colorTextBoxBorderFocus'].'</TextboxFocusBorderColor>
								</DisplayColors>
								<DisplayOptions>
									<NoCardNumberMask>'.$dontMaskCardNumber.'</NoCardNumberMask>
									<HideMessage>'.$hideInstructions.'</HideMessage>
									<HideDowngradeMessage>'.$hideDowngradeMessage.'</HideDowngradeMessage>
								</DisplayOptions>
								<EntryMode>Keyed</EntryMode>
							</request>
						</CreateTransaction>
					</soap:Body>
				</soap:Envelope>
			';

		// Set header with SOAP Action
		$soapAction = "http://transport.merchantware.net/v4/CreateTransaction";
		$headers = array("Content-Type: text/xml; charset=utf-8", "SOAPAction: ".$soapAction);

		// setup cURL request
		$url = 'https://transport.merchantware.net/v4/transportService.asmx';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		// Eliminate header info from response.
		curl_setopt($ch, CURLOPT_HEADER, 0);
		// Do a regular HTTP POST
		curl_setopt($ch, CURLOPT_POST, 1);
		// Do not follow 'Location:' headers
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		// Return response data instead of true(1).
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// Force the use of TLS instead of SSLv3.
		//  http://merchantwarehouse.com/what-you-need-to-know-about-the-poodle-security-vulnerability
		curl_setopt($ch, CURLOPT_SSLVERSION, 1);
		// Use HTTP POST to send form data.
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
		// Execute post and get results
		$resp = curl_exec($ch);
		curl_close($ch);

		Yii::log(
			sprintf(
				"%s sending %s for amt %s\nSoap: %s",
				__CLASS__,
				$this->objCart->id_str,
				$this->objCart->total,
				$xmlData
			),
			$this->logLevel,
			'application.'.__CLASS__.".".__FUNCTION__
		);

		Yii::log(__CLASS__ . " receiving " . $resp, $this->logLevel, 'application.'.__CLASS__.".".__FUNCTION__);

		$arrResult = array(
			'success' => 0,
			'transportKey' => null,
			'validationKey' => null,
			'errorMessage' => null
		);

		if ($resp == false)
		{
			Yii::log(sprintf("%s createTransaction response was blank.", __CLASS__), 'error', 'application.'.__CLASS__.'.'.__FUNCTION__);
			$arrResult['errorMessage'] = Yii::t('checkout', 'An unexpected error occurred.');
			return $arrResult;
		}

		$resp = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $resp);

		// Parse xml for response values.
		$oXML = new SimpleXMLElement($resp);

		if (($objResult = $oXML->soapBody->CreateTransactionResponse->CreateTransactionResult) === false)
		{
			Yii::log(
				sprintf(
					"%s createTransaction response object is not what Web Store expects",
					__CLASS__,
					print_r($oXML, true)
				),
				'error',
				'application.'.__CLASS__.'.'.__FUNCTION__
			);
			$arrResult['errorMessage'] = Yii::t('checkout', 'An unexpected error occurred');
			return $arrResult;
		}

		$strErrors = $this->parseErrors($objResult->Messages);
		if (strlen($strErrors) > 0)
		{
			// Messages are always errors so if there are any, save them and exit.
			$arrResult['errorMessage'] = $strErrors;
			return $arrResult;
		}

		if (!isset($objResult->TransportKey) ||
			!isset($objResult->ValidationKey) ||
			is_null($objResult->TransportKey) ||
			is_null($objResult->ValidationKey))
		{
			// We need both the TransportKey and ValidationKey to continue so if either isn't present we exit.
			Yii::log(sprintf("TransportKey or ValidationKey is missing from %s response", __CLASS__), 'error', 'application.'.__CLASS__.'.'.__FUNCTION__);
			$arrResult['errorMessage'] = Yii::t('checkout', 'An unexpected error occurred');
			return $arrResult;
		}

		// We typecast the attribute value for the assignment in order to save it to the array.
		// http://php.net/manual/en/simplexmlelement.attributes.php#103235
		$arrResult['transportKey'] = (string)$objResult->TransportKey;
		$arrResult['validationKey'] = (string)$objResult->ValidationKey;
		$arrResult['success'] = 1;

		// Cayan doesn't return the WO number in the gateway response.
		// Since we won't have the number we have to locate the order a different way and we use
		// the ValidationKey to do that. We store it temporarily in the payment_data field of the cart
		// payment. It will get overwritten with the authorization code upon a successful transaction.
		$objPayment = $this->objCart->payment;
		$objPayment->payment_data = $objResult->ValidationKey;
		$objPayment->save();

		return $arrResult;
	}


	/**
	 * https://ps1.merchantware.net/Merchantware/documentation40/transport/overview_MessageList.aspx
	 * Take the passed in error message object(s) and return the error(s) as one string.
	 *
	 * @param $objMessages
	 * @return null|string
	 */
	protected function parseErrors($objMessages)
	{
		$newline = Yii::app()->theme->info->advancedCheckout === true ? "\n" : '<br>';
		$arrMessages = array();

		foreach ($objMessages->Message as $message)
		{
			if (isset($message->Information) && !is_null($message->Information))
			{
				// We can safely assume that if the Information attribute
				// is populated then so too is the Field attribute.
				$arrMessages[] = $message->Field . ': ' . $message->Information;
			}
		}

		return implode($newline, $arrMessages);
	}


	/**
	 * If the store owner doesn't specify one or more
	 * display options, use these default values.
	 *
	 * @return void
	 */
	protected function setDisplayOptions()
	{
		$arrDefaults = array(
			'colorContainerBackground' => 'F7F7F7',
			'colorContainerBorder' => '4F4F4F',
			'colorLogoBackground' => 'F7F7F7',
			'colorLogoBorder' => '4F4F4F',
			'colorTextBoxBorder' => '8F8F8F',
			'colorTextBoxBorderFocus' => '363636',
			'maskCardNumber' => false,
			'hideInstructions' => false,
			'hideDowngradeMessage' => false,
		);

		foreach ($this->config['customConfig'] as $key => $value)
		{
			if ($value == '')
			{
				$this->config['customConfig'][$key] = $arrDefaults[$key];
			}
		}
	}

	public function gateway_response_process()
	{
		$status = Yii::app()->getRequest()->getQuery('Status');
		$authCode = Yii::app()->getRequest()->getQuery('AuthCode');
		$validationKey = Yii::app()->getRequest()->getQuery('ValidationKey');

		// This is how we get the cart back using the validation key.
		$objPayment = CartPayment::model()->findByAttributes(array('payment_data' => $validationKey));
		$objCart = Cart::model()->findByAttributes(array('payment_id' => $objPayment->id));

		if (strtolower($status) != 'approved')
		{
			$url = Yii::app()->controller->createAbsoluteUrl('cart/restoredeclined', array('getuid' => $objCart->linkid, 'reason' => $status));

			return array(
				'success' => false,
				'order_id' => $objCart->id_str,
				'output' => "<html><head><meta http-equiv=\"refresh\" content=\"1;url=$url\"></head><body><a href=\"$url\">Verifying order, please wait...</a></body></html>",
			);
		}

		return array(
			'success' => true,
			'data' => $authCode,
			'order_id' => $objCart->id_str,
			'amount' => $objCart->total
		);
	}

	/**
	 * We want the attributes of Cayan's config form to be part of the module's
	 * configuration when the module is saved to the database for the first time.
	 * This will allow an end user to checkout without problems in the event that
	 * the store owner does not configure the extra options beforehand.
	 *
	 * @return bool|string
	 */
	public function getDefaultConfiguration()
	{
		$serializedConfig = parent::getDefaultConfiguration();

		if ($serializedConfig === false)
		{
			return false;
		}

		$arrConfig = unserialize($serializedConfig);
		$configModel = new cayanConfigForm();
		if (!is_null($configModel))
		{
			$arrConfig['customConfig'] = $configModel->attributes;
		}

		return serialize($arrConfig);
	}
}