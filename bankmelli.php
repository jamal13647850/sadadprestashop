<?php

	class BankMelli extends PaymentModule {
		private $_html = '';
		private $_postErrors = array();
		private $_payment_request = 'https://sadad.shaparak.ir/VPG/api/v0/Request/PaymentRequest';
		private $_payment_url = 'https://sadad.shaparak.ir/VPG/Purchase?Token=';

		public function __construct() {
			$this->name = 'bankmelli';
			$this->tab = 'payments_gateways';
			$this->version = '1.0';
			$this->author = 'AlmaaTech';

			$this->currencies = true;
			$this->currencies_mode = 'checkbox';

			parent::__construct();
			$this->context = Context::getContext();
			$this->page = basename(__FILE__, '.php');
			$this->displayName = $this->l('Melli Payment');
			$this->description = $this->l('A free module to pay online for Melli.');
			$this->confirmUninstall = $this->l('Are you sure, you want to delete your details?');

			if (!sizeof(Currency::checkPaymentCurrencies($this->id)))
				$this->warning = $this->l('No currency has been set for this module');

			$config = Configuration::getMultiple(array('Bank_Melli_TerminalId', ''));
			if (!isset($config['Bank_Melli_TerminalId']))
				$this->warning = $this->l('Your Melli TerminalId must be configured in order to use this module');
			$config = Configuration::getMultiple(array('Bank_Melli_MerchantId', ''));
			if (!isset($config['Bank_Melli_MerchantId']))
				$this->warning = $this->l('Your Melli MerchantId must be configured in order to use this module');

			$config = Configuration::getMultiple(array('Bank_Melli_TerminalKey', ''));
			if (!isset($config['Bank_Melli_TerminalKey']))
				$this->warning = $this->l('Your Melli TerminalKey must be configured in order to use this module');

			if ($_SERVER['SERVER_NAME'] == 'localhost')
				$this->warning = $this->l('Your are in localhost, Melli Payment can\'t validate order');

		}

		public function install() {
			if (!parent::install()
					OR !Configuration::updateValue('Bank_Melli_TerminalId', '')
					OR !Configuration::updateValue('Bank_Melli_MerchantId', '')
					OR !Configuration::updateValue('Bank_Melli_TerminalKey', '')
					OR !Configuration::updateValue('Bank_Melli_phpDisplayErrors', 0)
					OR !$this->registerHook('payment')
					OR !$this->registerHook('paymentReturn')
			) {
				return false;
			} else {
				return true;
			}
		}

		public function uninstall() {
			if (!Configuration::deleteByName('Bank_Melli_TerminalId')
					OR !Configuration::deleteByName('Bank_Melli_MerchantId')
					OR !Configuration::deleteByName('Bank_Melli_TerminalKey')
					OR !Configuration::deleteByName('Bank_Melli_phpDisplayErrors')
					OR !parent::uninstall()
			)
				return false;
			return true;
		}

		public function displayFormSettings() {
			$this->_html .= '
		<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
			<fieldset>
				<legend><img src="../img/admin/cog.gif" alt="" class="middle" />' . $this->l('Settings') . '</legend>
				<label>' . $this->l('terminalId') . '</label>
				<div class="margin-form"><input type="text" size="30" name="terminalId" value="' . Configuration::get('Bank_Melli_TerminalId') . '" /></div>
				<label>' . $this->l('merchantId') . '</label>
				<div class="margin-form"><input type="text" size="30" name="merchantId" value="' . Configuration::get('Bank_Melli_MerchantId') . '" /></div>
				<label>' . $this->l('terminalKey') . '</label>
				<div class="margin-form"><input type="text" size="30" name="terminalKey" value="' . Configuration::get('Bank_Melli_TerminalKey') . '" /></div>

				<label>' . $this->l('خطایابی PHP') . '</label>
				<div class="margin-form"><input type="radio" value="1" name="phpDisplayErrors" ' . (Configuration::get('Bank_Melli_phpDisplayErrors') == '1' ? "checked" : "") . ' /> <span>' . $this->l('Yes') . '</span>
				<input type="radio" value="0" name="phpDisplayErrors" ' . (Configuration::get('Bank_Melli_phpDisplayErrors') == '0' ? "checked" : "") . ' /> <span>' . $this->l('No') . '</span><span class="hint" name="help_box">جهت کشف خطاهای سرور و یا پرستاشاپ مناسب است. فقط در صورتی که در اتصال به بانک مشکل دارید فعال کنید. فراموش نکنید بعد از رفع مشکل آن را غیرفعال کنید.</span></div>
				<center><input type="submit" name="submitMelli" value="' . $this->l('Update Settings') . '" class="button" /></center>			
			</fieldset>
		</form>';
		}

		public function displayConf() {

			$this->_html .= '<div class="conf confirm"> ' . $this->l('Settings updated') . '</div>';
		}

		public function displayErrors() {
			foreach ($this->_postErrors AS $err) {
				$this->_html .= '<div class="alert error">' . $err . '</div>';
			}
		}

		public function getContent() {
			$this->_html = '<h2>' . $this->l('Melli Payment') . '</h2>';
			if (isset($_POST['submitMelli'])) {
				if (empty($_POST['terminalId']))
					$this->_postErrors[] = $this->l('Melli TerminalId is required.');

				if (empty($_POST['merchantId']))
					$this->_postErrors[] = $this->l('Your MerchantId is required.');

				if (empty($_POST['terminalKey']))
					$this->_postErrors[] = $this->l('Your TerminalKey is required.');

				if (!sizeof($this->_postErrors)) {

					Configuration::updateValue('Bank_Melli_TerminalId', $_POST['terminalId']);
					Configuration::updateValue('Bank_Melli_MerchantId', $_POST['merchantId']);
					Configuration::updateValue('Bank_Melli_TerminalKey', $_POST['terminalKey']);
					Configuration::updateValue('Bank_Melli_phpDisplayErrors', $_POST['phpDisplayErrors']);
					$this->displayConf();
				} else {
					$this->displayErrors();
				}
			}
			$this->displayFormSettings();
			return $this->_html;
		}

		public function prePayment() {
			$purchase_currency = new Currency(Currency::getIdByIsoCode('IRR'));
			$current_currency = new Currency($this->context->cookie->id_currency);
			if ($current_currency->id == $purchase_currency->id)
				$PurchaseAmount = number_format($this->context->cart->getOrderTotal(true, 3), 0, '', '');
			else
				$PurchaseAmount = number_format($this->convertPriceFull($this->context->cart->getOrderTotal(true, 3), $current_currency, $purchase_currency), 0, '', '');


			$order_id = ($this->context->cart->id) . date('YmdHis');
			$terminal_id = Configuration::get('Bank_Melli_TerminalId');
			$merchant_id = Configuration::get('Bank_Melli_MerchantId');
			$terminal_key = Configuration::get('Bank_Melli_TerminalKey');
			$amount = (int)$PurchaseAmount;

			$sign_data = $this->sadad_encrypt($terminal_id . ';' . $order_id . ';' . $amount, $terminal_key);

			$parameters = array(
					'MerchantID' => $merchant_id,
					'TerminalId' => $terminal_id,
					'Amount' => $amount,
					'OrderId' => $order_id,
					'LocalDateTime' => date('Ymdhis'),
					'ReturnUrl' => $this->context->link->getModuleLink('bankmelli', 'validation'),
					'SignData' => $sign_data,
			);

			$error_flag = false;
			$error_msg = '';

			$result = $this->sadad_call_api($this->_payment_request, $parameters);

			if ($result != false) {
				if ($result->ResCode == 0) {
					$this->context->smarty->assign(array(
							'redirect_link' => $this->_payment_url . $result->Token,
							'Token' => $result->Token
					));
					return true;
				} else {
					//bank returned an error
					$error_flag = true;
					$error_msg = $this->sadad_request_err_msg($result->ResCode);
				}
			} else {
				// couldn't connect to bank
				$error_flag = true;
				$error_msg = 'خطا! برقراری ارتباط با بانک امکان پذیر نیست.';
			}

			if ($error_flag) {
				$this->_postErrors[] = $this->l($error_msg);
				$this->displayErrors();
				return false;
			}

		}

/*
		public function callBack($saleOrderId, $saleReferenceId) {
			if (!isset($_POST['token']) || !isset($_POST['OrderId']) || !isset($_POST['ResCode'])) {
//			wp_die('پارامترهای ضروری ارسال نشده اند.');
			}
			$token = $_POST['token'];
			//verify payment
			$parameters = array(
					'Token' => $token,
					'SignData' => $this->sadad_encrypt($token, Configuration::get('Bank_Melli_TerminalKey')),
			);

			$error_flag = false;
			$error_msg = '';

			$result = $this->sadad_call_api($this->_payment_verify, $parameters);
			if ($result != false) {
				if ($result->ResCode == 0) {
					//payment success
				} else {
					//couldn't verify the payment due to a back error
					$error_flag = true;
					$error_msg = $this->sadad_verify_err_msg($result->ResCode);
				}
			} else {
				//couldn't verify the payment due to a connection failure to bank
				$error_flag = true;
				$error_msg = 'خطا! عدم امکان دریافت تاییدیه پرداخت از بانک';
			}

			if ($error_flag) {
				$this->_postErrors[] = $this->l($error_msg);
				return $this->_postErrors;
			}
			return true;

		}
*/
		/*
				public function verify($saleOrderId, $saleReferenceId, $soapclient = NULL) {
					if (!$soapclient) {
						include_once('lib/nusoap.php');
						$soapclient = new nusoap_client($this->webservice, 'wsdl');
					}

					if (!$soapclient) {
						$this->_postErrors[] = $this->l('اتصال به بانک برقرار نشد');
						// if(!empty($err))
						// $this->_postErrors[] = $err;
						return $this->_postErrors;
						// return $return;
					}

					// Params For Verify
					$params = array(
							'terminalId' => Configuration::get('Bank_Melli_TerminalId'),
							'userName' => Configuration::get('Bank_Melli_MerchantId'),
							'userPassword' => Configuration::get('Bank_Melli_TerminalKey'),
							'orderId' => ($this->context->cart->id) . date('YmdHis'),
							'saleOrderId' => $saleOrderId,
							'saleReferenceId' => $saleReferenceId
					);

					$result = $soapclient->call('bpVerifyRequest', $params, $this->_namespace);

					if ($soapclient->fault OR $err = $soapclient->getError()) {
						$this->_postErrors[] = $this->l('Could not connect to bank or service.');
						return $this->_postErrors;
					}
					if ($result['return'] != "0") {
						$this->showMessages($result['return']);
						return $this->_postErrors;
					}
					return true;
				}

				public function settle($saleOrderId, $saleReferenceId, $soapclient = NULL) {
					if (!$soapclient) {
						include_once('lib/nusoap.php');
						$soapclient = new nusoap_client($this->webservice, 'wsdl');
					}

					if (!$soapclient) {
						$this->_postErrors[] = $this->l('اتصال به بانک برقرار نشد');
						// if(!empty($err))
						// $this->_postErrors[] = $err;
						return $this->_postErrors;
						// return $return;
					}

					//Params for settle
					$params = array(
							'terminalId' => Configuration::get('Bank_Melli_TerminalId'),
							'userName' => Configuration::get('Bank_Melli_MerchantId'),
							'userPassword' => Configuration::get('Bank_Melli_TerminalKey'),
							'orderId' => ($this->context->cart->id) . date('YmdHis'),
							'saleOrderId' => $saleOrderId,
							'saleReferenceId' => $saleReferenceId
					);

					$result = $soapclient->call('bpSettleRequest', $params, $this->_namespace);
					if ($soapclient->fault OR $err = $soapclient->getError()) {
						$this->_postErrors[] = $this->l('Could not connect to bank or service.');
						return $this->_postErrors;
					}


					if ($result['return'] != "0") {
						$this->showMessages($result['return']);
						return $this->_postErrors;
						//return $return;
					}
					return true;
				}

				public function inquiry($saleOrderId, $saleReferenceId, $soapclient = NULL) {
					if (!$soapclient) {
						include_once('lib/nusoap.php');
						$soapclient = new nusoap_client($this->webservice, 'wsdl');
					}

					if (!$soapclient) {
						$this->_postErrors[] = $this->l('اتصال به بانک برقرار نشد');
						// if(!empty($err))
						// $this->_postErrors[] = $err;
						return $this->_postErrors;
						// return $return;
					}

					//Params for inquiry
					$params = array(
							'terminalId' => Configuration::get('Bank_Melli_TerminalId'),
							'userName' => Configuration::get('Bank_Melli_MerchantId'),
							'userPassword' => Configuration::get('Bank_Melli_TerminalKey'),
							'orderId' => ($this->context->cart->id) . date('YmdHis'),
							'saleOrderId' => $saleOrderId,
							'saleReferenceId' => $saleReferenceId
					);

					$result = $soapclient->call('bpInquiryRequest', $params, $this->_namespace);
					if ($soapclient->fault OR $err = $soapclient->getError()) {
						$this->_postErrors[] = $this->l('Could not connect to bank or service.');
						return $this->_postErrors;
					}

					if ($result['return'] != "0") {
						$this->showMessages($result['return']);
						return $this->_postErrors;
					}
					return true;
				}

				public function reverse($saleOrderId, $saleReferenceId, $soapclient = NULL) {
					if (!$soapclient) {
						include_once('lib/nusoap.php');
						$soapclient = new nusoap_client($this->webservice, 'wsdl');
					}

					if (!$soapclient) {
						$this->_postErrors[] = $this->l('اتصال به بانک برقرار نشد');
						// if(!empty($err))
						// $this->_postErrors[] = $err;
						return $this->_postErrors;
						// return $return;
					}

					//Params for reversal
					$params = array(
							'terminalId' => Configuration::get('Bank_Melli_TerminalId'),
							'userName' => Configuration::get('Bank_Melli_MerchantId'),
							'userPassword' => Configuration::get('Bank_Melli_TerminalKey'),
							'orderId' => ($this->context->cart->id) . date('YmdHis'),
							'saleOrderId' => $saleOrderId,
							'saleReferenceId' => $saleReferenceId
					);

					$result = $soapclient->call('bpReversalRequest', $params, $this->_namespace);
					if ($soapclient->fault OR $err = $soapclient->getError()) {
						$this->_postErrors[] = $this->l('Could not connect to bank or service.');
						return $this->_postErrors;
					}

					if ($result['return'] != "0") {
						$this->showMessages($result['return']);
						return $this->_postErrors;
					}
					return true;
				}
		*/
/*
		public function showMessages($result) {
			switch ($result) {
				case 0:
					$this->_postErrors[] = $this->l('تراکنش با موفقیت انحام شد');
					break;
				case 11:
					$this->_postErrors[] = $this->l('شماره کارت نامعتبر است');
					break;
				case 12:
					$this->_postErrors[] = $this->l('موجودی کافی نیست');
					break;
				case 13:
					$this->_postErrors[] = $this->l('رمز نادرست است');
					break;
				case 14:
					$this->_postErrors[] = $this->l('تعداد دفعات وارد کردن رمز بیش از حد مجاز است');
					break;
				case 15:
					$this->_postErrors[] = $this->l('کارت نامعتبر است');
					break;
				case 16:
					$this->_postErrors[] = $this->l('دفعات برداشت وجه بیش از حد مجاز است');
					break;
				case 17:
					$this->_postErrors[] = $this->l('کاربر از انجام تراکنش منصرف شده است');
					break;
				case 18:
					$this->_postErrors[] = $this->l('تاریخ انقضای کارت گذشته است');
					break;
				case 19:
					$this->_postErrors[] = $this->l('مبلغ برداشت وجه بیش از حد مجاز است');
					break;
				case 111:
					$this->_postErrors[] = $this->l('صادر کننده کارت نامعتبر است');
					break;
				case 112:
					$this->_postErrors[] = $this->l('خطای سوییچ صادر کننده کارت');
					break;
				case 113:
					$this->_postErrors[] = $this->l('پاسخی از صادر کننده کارت دریافت نشد');
					break;
				case 114:
					$this->_postErrors[] = $this->l('دارنده کارت مجاز به انجام این تراکنش نیست');
					break;
				case 21:
					$this->_postErrors[] = $this->l('پذیرنده نامعتبر است');
					break;
				case 23:
					$this->_postErrors[] = $this->l('خطای امنیتی رخ داده است');
					break;
				case 24:
					$this->_postErrors[] = $this->l('اطلاعات کاربری پذیرنده نامعتبر است');
					break;
				case 25:
					$this->_postErrors[] = $this->l('مبلغ نامعتبر است');
					break;
				case 31:
					$this->_postErrors[] = $this->l('پاسخ نامعتبر است');
					break;
				case 32:
					$this->_postErrors[] = $this->l('فرمت اطلاعات وارد شده صحیح نمی باشد');
					break;
				case 33:
					$this->_postErrors[] = $this->l('حساب نامعتبر است');
					break;
				case 34:
					$this->_postErrors[] = $this->l('خطای سیستمی');
					break;
				case 35:
					$this->_postErrors[] = $this->l('تاریخ نامعتبر است');
					break;
				case 41:
					$this->_postErrors[] = $this->l('شماره درخواست تکراری است');
					break;
				case 42:
					$this->_postErrors[] = $this->l('تراکنش Sale یافت نشد');
					break;
				case 43:
					$this->_postErrors[] = $this->l('قبلا درخواست Verify داده شده است');
					break;
				case 44:
					$this->_postErrors[] = $this->l('درخواست Verify یافت نشد');
					break;
				case 45:
					$this->_postErrors[] = $this->l('تراکنش Settle شده است');
					break;
				case 46:
					$this->_postErrors[] = $this->l('تراکنش Settle نشده است');
					break;
				case 47:
					$this->_postErrors[] = $this->l('تراکنش Settle یافت نشد');
					break;
				case 48:
					$this->_postErrors[] = $this->l('تراکنش Reverse شده است');
					break;
				case 49:
					$this->_postErrors[] = $this->l('تراکنش Refund یافت شند');
					break;
				case 412:
					$this->_postErrors[] = $this->l('شناسه قبض نادرست است');
					break;
				case 413:
					$this->_postErrors[] = $this->l('شناسه پرداخت نادرست است');
					break;
				case 414:
					$this->_postErrors[] = $this->l('سازمان صادر کننده قبض نامعتبر است');
					break;
				case 415:
					$this->_postErrors[] = $this->l('زمان جلسه کاری به پایان رسیده است');
					break;
				case 416:
					$this->_postErrors[] = $this->l('خطا در ثبت اطلاعات');
					break;
				case 417:
					$this->_postErrors[] = $this->l('شناسه پرداخت کننده نامعتبر است');
					break;
				case 418:
					$this->_postErrors[] = $this->l('اشکال در تعریف اطلاعات مشتری');
					break;
				case 419:
					$this->_postErrors[] = $this->l('تعداد دفعات ورود اطلاعات از حد مجاز گذشته است');
					break;
				case 421:
					$this->_postErrors[] = $this->l('IP نامعتبر است');
					break;
				case 51:
					$this->_postErrors[] = $this->l('تراکنش تکراری است');
					break;
				case 54:
					$this->_postErrors[] = $this->l('تراکنش مرجع موجود نیست');
					break;
				case 55:
					$this->_postErrors[] = $this->l('تراکنش نامعتبر است');
					break;
				case 61:
					$this->_postErrors[] = $this->l('خطا در واریز');
					break;
			}
			return $this->_postErrors;
		}
*/
/*
		// to show only one error
		public function showErrorMessages($result) {
			$Message = $this->showMessages($result);
			$this->_html = '';
			$this->_postErrors = array();
			return $Message;
		}
*/
		public function hookPayment($params) {
			if (!$this->active)
				return;
			return $this->display(__FILE__, 'payment.tpl');
		}

		public function hookPaymentReturn($params) {
			return;
		}

		/**
		 *
		 * @return float converted amount from a currency to an other currency
		 * @param float $amount
		 * @param Currency $currency_from if null we used the default currency
		 * @param Currency $currency_to if null we used the default currency
		 */
		public static function convertPriceFull($amount, Currency $currency_from = null, Currency $currency_to = null) {
			if ($currency_from === $currency_to)
				return $amount;
			if ($currency_from === null)
				$currency_from = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
			if ($currency_to === null)
				$currency_to = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
			if ($currency_from->id == Configuration::get('PS_CURRENCY_DEFAULT'))
				$amount *= $currency_to->conversion_rate;
			else {
				$conversion_rate = ($currency_from->conversion_rate == 0 ? 1 : $currency_from->conversion_rate);
				// Convert amount to default currency (using the old currency rate)
				$amount = Tools::ps_round($amount / $conversion_rate, 2);
				// Convert to new currency
				$amount *= $currency_to->conversion_rate;
			}
			return Tools::ps_round($amount, 2);
		}

		public function sadad_encrypt($data, $secret) {
			//Generate a key from a hash
			$key = base64_decode($secret);

			//Pad for PKCS7
			$blockSize = mcrypt_get_block_size('tripledes', 'ecb');
			$len = strlen($data);
			$pad = $blockSize - ($len % $blockSize);
			$data .= str_repeat(chr($pad), $pad);

			//Encrypt data
			$encData = mcrypt_encrypt('tripledes', $key, $data, 'ecb');

			return base64_encode($encData);
		}

		public function sadad_call_api($url, $data = false) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json; charset=utf-8'));
			curl_setopt($ch, CURLOPT_POST, 1);
			if ($data) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			}
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			$result = curl_exec($ch);
			curl_close($ch);
			return !empty($result) ? json_decode($result) : false;
		}

		public function sadad_request_err_msg($err_code) {

			switch ($err_code) {
				case 3:
					$message = 'پذيرنده کارت فعال نیست لطفا با بخش امورپذيرندگان, تماس حاصل فرمائید.';
					break;
				case 23:
					$message = 'پذيرنده کارت نامعتبر است لطفا با بخش امورذيرندگان, تماس حاصل فرمائید.';
					break;
				case 58:
					$message = 'انجام تراکنش مربوطه توسط پايانه ی انجام دهنده مجاز نمی باشد.';
					break;
				case 61:
					$message = 'مبلغ تراکنش از حد مجاز بالاتر است.';
					break;
				case 1000:
					$message = 'ترتیب پارامترهای ارسالی اشتباه می باشد, لطفا مسئول فنی پذيرنده با بانکماس حاصل فرمايند.';
					break;
				case 1001:
					$message = 'لطفا مسئول فنی پذيرنده با بانک تماس حاصل فرمايند,پارامترهای پرداختاشتباه می باشد.';
					break;
				case 1002:
					$message = 'خطا در سیستم- تراکنش ناموفق';
					break;
				case 1003:
					$message = 'آی پی پذیرنده اشتباه است. لطفا مسئول فنی پذیرنده با بانک تماس حاصل فرمایند.';
					break;
				case 1004:
					$message = 'لطفا مسئول فنی پذيرنده با بانک تماس حاصل فرمايند,شماره پذيرندهاشتباه است.';
					break;
				case 1005:
					$message = 'خطای دسترسی:لطفا بعدا تلاش فرمايید.';
					break;
				case 1006:
					$message = 'خطا در سیستم';
					break;
				case 1011:
					$message = 'درخواست تکراری- شماره سفارش تکراری می باشد.';
					break;
				case 1012:
					$message = 'اطلاعات پذيرنده صحیح نیست,يکی از موارد تاريخ,زمان يا کلید تراکنش
                                اشتباه است.لطفا مسئول فنی پذيرنده با بانک تماس حاصل فرمايند.';
					break;
				case 1015:
					$message = 'پاسخ خطای نامشخص از سمت مرکز';
					break;
				case 1017:
					$message = 'مبلغ درخواستی شما جهت پرداخت از حد مجاز تعريف شده برای اين پذيرنده بیشتر است';
					break;
				case 1018:
					$message = 'اشکال در تاريخ و زمان سیستم. لطفا تاريخ و زمان سرور خود را با بانک هماهنگ نمايید';
					break;
				case 1019:
					$message = 'امکان پرداخت از طريق سیستم شتاب برای اين پذيرنده امکان پذير نیست';
					break;
				case 1020:
					$message = 'پذيرنده غیرفعال شده است.لطفا جهت فعال سازی با بانک تماس بگیريد';
					break;
				case 1023:
					$message = 'آدرس بازگشت پذيرنده نامعتبر است';
					break;
				case 1024:
					$message = 'مهر زمانی پذيرنده نامعتبر است';
					break;
				case 1025:
					$message = 'امضا تراکنش نامعتبر است';
					break;
				case 1026:
					$message = 'شماره سفارش تراکنش نامعتبر است';
					break;
				case 1027:
					$message = 'شماره پذيرنده نامعتبر است';
					break;
				case 1028:
					$message = 'شماره ترمینال پذيرنده نامعتبر است';
					break;
				case 1029:
					$message = 'آدرس IP پرداخت در محدوده آدرس های معتبر اعلام شده توسط پذيرنده نیست .لطفا مسئول فنی پذيرنده با بانک تماس حاصل فرمايند';
					break;
				case 1030:
					$message = 'آدرس Domain پرداخت در محدوده آدرس های معتبر اعلام شده توسط پذيرنده نیست .لطفا مسئول فنی پذيرنده با بانک تماس حاصل فرمايند';
					break;
				case 1031:
					$message = 'مهلت زمانی شما جهت پرداخت به پايان رسیده است.لطفا مجددا سعی بفرمايید .';
					break;
				case 1032:
					$message = 'پرداخت با اين کارت . برای پذيرنده مورد نظر شما امکان پذير نیست.لطفا از کارتهای مجاز که توسط پذيرنده معرفی شده است . استفاده نمايید.';
					break;
				case 1033:
					$message = 'به علت مشکل در سايت پذيرنده. پرداخت برای اين پذيرنده غیرفعال شده
                                است.لطفا مسوول فنی سايت پذيرنده با بانک تماس حاصل فرمايند.';
					break;
				case 1036:
					$message = 'اطلاعات اضافی ارسال نشده يا دارای اشکال است';
					break;
				case 1037:
					$message = 'شماره پذيرنده يا شماره ترمینال پذيرنده صحیح نمیباشد';
					break;
				case 1053:
					$message = 'خطا: درخواست معتبر, از سمت پذيرنده صورت نگرفته است لطفا اطلاعات پذيرنده خود را چک کنید.';
					break;
				case 1055:
					$message = 'مقدار غیرمجاز در ورود اطلاعات';
					break;
				case 1056:
					$message = 'سیستم موقتا قطع میباشد.لطفا بعدا تلاش فرمايید.';
					break;
				case 1058:
					$message = 'سرويس پرداخت اينترنتی خارج از سرويس می باشد.لطفا بعدا سعی بفرمايید.';
					break;
				case 1061:
					$message = 'اشکال در تولید کد يکتا. لطفا مرورگر خود را بسته و با اجرای مجدد مرورگر « عملیات پرداخت را انجام دهید )احتمال استفاده از دکمه Back » مرورگر(';
					break;
				case 1064:
					$message = 'لطفا مجددا سعی بفرمايید';
					break;
				case 1065:
					$message = 'ارتباط ناموفق .لطفا چند لحظه ديگر مجددا سعی کنید';
					break;
				case 1066:
					$message = 'سیستم سرويس دهی پرداخت موقتا غیر فعال شده است';
					break;
				case 1068:
					$message = 'با عرض پوزش به علت بروزرسانی . سیستم موقتا قطع میباشد.';
					break;
				case 1072:
					$message = 'خطا در پردازش پارامترهای اختیاری پذيرنده';
					break;
				case 1101:
					$message = 'مبلغ تراکنش نامعتبر است';
					break;
				case 1103:
					$message = 'توکن ارسالی نامعتبر است';
					break;
				case 1104:
					$message = 'اطلاعات تسهیم صحیح نیست';
					break;
				default:
					$message = 'خطای نامشخص';
			}
			return $this->l($message);
		}

		public function sadad_verify_err_msg($res_code) {
			$error_text = '';
			switch ($res_code) {
				case -1:
				case '-1':
					$error_text = 'پارامترهای ارسالی صحیح نیست و يا تراکنش در سیستم وجود ندارد.';
					break;
				case 101:
				case '101':
					$error_text = 'مهلت ارسال تراکنش به پايان رسیده است.';
					break;
			}
			return $this->l($error_text);
		}



	}