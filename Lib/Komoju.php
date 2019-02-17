<?php
/**
 * Komoju.php
 *
 * A Cakephp plugin that handles payment processing with Komoju.
 *
 * PHP version 7
 *
 * @package   Komoju.php
 * @author    Samy Younsi (Shino Corp') <samyyounsi@hotmail.fr>
 * @license   MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @link      https://github.com/ShinoNoNuma/CakePHP-Komoju-Plugin
 */
App::uses('CakeRequest', 'Network');
App::uses('Validation', 'Utility');
App::uses('HttpSocket', 'Network/Http');

/**
 * Komoju Exception classes
 */
class KomojuException extends CakeException {}

/**
 * Komoju Redirect Exception classes
 */
class KomojuRedirectException extends CakeException {}

/**
 * Komoju class
 */
class Komoju {
/**
 * Target version for Komoju's API
 */
  protected $komojuApiVersion = 'v1';

/**
 * Default Komoju mode to use: Test (sandbox) or Live
 */
  protected $sandboxMode = true;

/**
 * The required Komoju merchant ID included in all callbacks to identify the transaction.
 */
  protected $merchantUUID = null;

/**
 * The required Komoju publishable API key
 */
  protected $publishableKey = null;

/**
 * The required Komoju secret API key
 */
  protected $secretKey = null;

/**
 * The required Komoju key-value pairs
 */
  protected $metadata = 'hoge';

/**
 * Endpoint for REST API
 */
  protected $restEndpoint = 'https://komoju.com/api/';

/**
 * Method Event
 */
protected $eventResource = 'events';

/**
 * Method Payment
 */
protected $paymentResource = 'payments';

/**
 * Method Customer
 */
protected $customerResource = 'customers';

/**
 * Method Token 
 */
protected $tokenResource = 'tokens';



/**
 * More descriptive API error messages. Error code and message.
 *
 * @var array
 */
  protected $errorMessages = array();

/**
 * HttpSocket utility class
 */
  public $HttpSocket = null;

/**
 * CakeRequest
 */
  public $CakeRequest = null;

/**
 * Valid konbini names parameters for konbini payment
 *
 * @var array
 * @access protected
 */
  protected $konbiniNames = array(
    'daily-yamazaki',
    'family-mart',
    'lawson',
    'ministop'
  );

/**
 * Constructor. Takes API credentials, and other properties to set (e.g test mode)
 *
 * @param array $config An array of properties to overide (e.g the API signature)
 * @return void
 * @author Samy Younsi (Shino Corp')
 **/
  public function __construct($config = array()) {
    if (!empty($config)) {
      foreach ($config as $property => $value) {
        if (property_exists($this, $property)) {
          $this->{$property} = $value;
        }
      }
    }
    // Sets errorMessages instance var with localization
    $this->errorMessages = array(
      'bad_request' => __d('Komoju','The server cannot or will not process the request due to something that is perceived to be a client error.'),
      'unauthorized' => __d('Komoju','User authorization failed.'),
      'not_found' => __d('Komoju','The requested resource could not be found but may be available again in the future.'),
      'internal_server_error' => __d('Komoju','We\'re sorry but something went wrong. Please try your request again.'),
      'forbidden' => __d('Komoju','You are not authorized to perform that action.'),
      'unprocessable_entity' => __d('Komoju','The request was well-formed but was unable to be followed due to semantic errors.'),
      'locked' => __d('Komoju','Processing.'),
      'bad_gateway' => __d('Komoju','We are unable to process your request due to an invalid response from the upstream server.'),
      'gateway_timeout' => __d('Komoju','When attempting to process your payment, we encountered a gateway timeout. Fear not, we have not processed the payment. Please try your payment again.'),
      'service_unavailable' => __d('Komoju','The server is down for maintenance. Please try again later.'),
      'request_failed' => __d('Komoju','The request failed.'),
      'invalid_payment_type' => __d('Komoju','Payment method was invalid. %s is not one of %s.'),
      'invalid_token' => __d('Komoju','The token you requested is invalid.'),
      'invalid_currency' => __d('Komoju','The currency you requested is invalid.'),
      'not_refundable' => __d('Komoju','The payment you requested is not refundable.'),
      'not_capturable' => __d('Komoju','The payment you requested is not capturable.'),
      'not_cancellable' => __d('Komoju','This payment is noncancellable.'),
      'fraudulent' => __d('Komoju','This payment is fraudulent.'),
      'invalid_parameter' => __d('Komoju','The value of %s is invalid.'),
      'missing_parameter' => __d('Komoju','A required parameter (%s) is missing.'),
      'insufficient_funds' => __d('Komoju','Insufficient funds.'),
      'used_number' => __d('Komoju','Used number.'),
      'card_declined' => __d('Komoju','Card declined.'),
      'invalid_password' => __d('Komoju','Invalid password.'),
      'bad_verification_value' => __d('Komoju','Bad verification value.'),
      'exceeds_limit' => __d('Komoju','Exceeds limit.'),
      'card_expired' => __d('Komoju','Card expired.'),
      'invalid_number' => __d('Komoju','The number you requested is invalid.'),
      'invalid_account' => __d('Komoju','Invalid account.')
    );
  }

/**
 * List Payments
 *
 * @return array
 * @author Samy Younsi (Shino Corp')
 **/
  public function listPayments() {
    try {
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->paymentResource;
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->get($endPoint);
      // Parse the results
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['resource'])) {
        return $parsed;
      }
      elseif (isset($parsed['error'])) {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error.'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem, please try again.'));
    }
  }

/**
 * Show Payment
 * @param string $paymentID resource params
 * @return array
 * @author Samy Younsi (Shino Corp')
 **/
public function showPayment($paymentID) {
    try {
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->paymentResource.DS.$paymentID;
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->get($endPoint);
      // Parse the results
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['id'])) {
        return $parsed;
      }
      elseif (isset($parsed['error'])) {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error.'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem, please try again.'));
    }
  }

/**
 * Cancel a payment
 * @param string $paymentID resource params
 * @return array
 * @author Samy Younsi (Shino Corp')
 **/
public function cancelPayment($paymentID) {
    try {
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->paymentResource.DS.$paymentID.DS.'cancel';
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->post($endPoint);
      // Parse the results.
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if(isset($parsed['id'])){
        return $parsed;
      }
      elseif (isset($parsed['error']))  {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error.'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem, please try again.'));
    }
  }

/**
 * Refund a payment
 * @param array $refund resource params
 * @return array
 * @author Samy Younsi (Shino Corp')
 **/
public function refundPayment($refund) {
    try {
      if (empty($refund['payment_id'])) {
        throw new KomojuException(__d('Komoju' , 'Payment ID must be specify'));
      }
      $paymentID = $refund['payment_id'];
      // Amount
      if (empty($refund['amount'])) {
        throw new KomojuException(__d('Komoju' , 'Must specify an "amount" to refund'));
      }
      // check payment type
      if($refund['payment_type'] == 'credit_card'){
        $data = array('amount' => $refund['amount']);
      }else{
        $data = null;
      }
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->paymentResource.DS.$paymentID.DS.'cancel';
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->post($endPoint, $data);
      // Parse the results.
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['id'])) {
        return $parsed;
      }
      elseif (isset($parsed['error'])) {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error.'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem, please try again.'));
    }
  }

/**
 * Capture a payment
 * @param string $paymentID resource param
 * @return array
 * @author Samy Younsi (Shino Corp')
 **/
public function capturePayment($paymentID) {
    try {
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->paymentResource.DS.$paymentID.DS.'capture';
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->post($endPoint, $data);
      // Parse the results.
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['id']))  {
        return $parsed;
      }
      elseif (isset($parsed['error']))  {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error.'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem, please try again.'));
    }
  }
/**
 * Create Payment
 * The createPayment API Operation enables you to process a payment.
 *
 * @param array $payment resource params
 * @return void
 * @author Samy Younsi (Shino Corp')
 **/
  public function createPayment($payment) {
    try {
      // Build array payment
      $data = $this->formatCreatePayment($payment);
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->paymentResource;
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->post($endPoint , $data);
      // Parse the results
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['id']))  {
        return $parsed;
      }
      elseif (isset($parsed['error']))  {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error processing the card payment'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem processing your card, please try again.'));
    }
  }

  /**
 * Takes a payment array and formats in to complete a payment
 *
 * @param array https://docs.komoju.com/en/api/overview/#payment_details
 * @return array Formatted array for createPayment
 * @author Samy Younsi (Shino Corp')
 **/
  public function formatCreatePayment($payment) {
    if (!isset($payment['payment_type'])) {
      throw new KomojuException(__d('Komoju' , 'Not a valid payment type'));
    }

    //Find the payment type
    if($payment['payment_type'] == 'credit_card'){
      $data = $this->formatCreditCardPayment($payment);
    }elseif($payment['payment_type'] == 'konbini'){
      $data = $this->formatKonbiniPayment($payment);
    }elseif ($payment['payment_type'] == 'bank_transfer' || $payment['payment_type'] == 'pay_easy') {
      $data = $this->formatBankTransferAndPayEasyPayment($payment);
    }elseif ($payment['payment_type'] == 'bit_cash') {
      $data = $this->formatBitCashPayment($payment);
    }elseif($payment['payment_type'] == 'web_money' || $payment['payment_type'] == 'nanaco' || $payment['payment_type'] == 'net_cash'){
      $data = $this->formatWebMoneyAndNanacoAndNetCashPayment($payment);
    }else{
       throw new KomojuException(__d('Komoju' , 'Unknown payment type'));
    }  
    return $data; 
  }

/**
 * Takes a payment array and formats in to the minimum array to complete a credit card payment
 *
 * @param array https://docs.komoju.com/en/api/overview/#credit_card
 * @return array Formatted array for Komoju credit card payment 
 * @author Samy Younsi (Shino Corp')
 **/
  public function formatCreditCardPayment($payment) {
       // Credit card number
    if (!$this->validateCC($payment['card'])) {
      throw new KomojuException(__d('Komoju' , 'Not a valid credit card number'));
    }
    $payment['card'] = preg_replace("/\s/" , "" , $payment['card']);
    // Credit card number
    if (!isset($payment['cvv'])) {
      throw new KomojuException(__d('Komoju' , 'You must include the 3 digit security number'));
    }
    $payment['cvv'] = preg_replace("/\s/" , "" , $payment['cvv']);
    // Amount
    if (!isset($payment['amount'])) {
      throw new KomojuException(__d('Komoju' , 'Must specify an "amount" to charge'));
    }
    // Expiry
    if (!isset($payment['expiry'])) {
      throw new KomojuException(__d('Komoju' , 'Must specify an expiry date'));
    }
    $dateKeys = array_keys($payment['expiry']);
    sort($dateKeys); // Sort alphabetcially
    if ($dateKeys != array('M' , 'Y')) {
      throw new KomojuException(__d('Komoju' , 'Must include a M and Y in expiry date'));
    }
    $month = $payment['expiry']['M'];
    $year = $payment['expiry']['Y'];
    $expiry = sprintf('%d%d' , $month, $year);
    // Currency
    $currency = (isset($payment['currency'])) ? $payment['currency'] : 'JPY';
    // Currency
    $capture = (isset($payment['capture'])) ? $payment['capture'] : null;
    // Tax
    $tax = (isset($payment['tax'])) ? $payment['tax'] : null;
    // Currency
    $description = (isset($payment['description'])) ? $payment['description'] : null;
    $locale = (isset($payment['locale'])) ? $payment['locale'] : null;
    // Build data
    $data = array(
      'amount' => $payment['amount'],  // The total cost of the transaction
      'currency' => $currency,    // A 3-character currency code
      'external_order_num' => CakeText::uuid(),
      'metadata[foobar]' => $this->metadata,
      'capture' => $capture,
      'tax' => $tax,
      'description' => $description,
      'locale' => $locale,
      'payment_details[family_name]' => $payment['last_name'],
      'payment_details[given_name]' => $payment['first_name'],
      'payment_details[month]' => $month,
      'payment_details[number]' => $payment['card'],
      'payment_details[type]' => 'credit_card',
      'payment_details[verification_value]' => $payment['cvv'],
      'payment_details[year]' => $year
    );
    return $data;
  }

/**
 * Takes a payment array and formats in to the minimum array to complete a konbini payment
 *
 * @param array https://docs.komoju.com/en/api/overview/#convenience_store_(konbini)
 * @return array Formatted array for Komoju konbini payment
 * @author Samy Younsi (Shino Corp') 
 **/
  public function formatKonbiniPayment($payment) {
    // Email address
    if (!isset($payment['email']) || !filter_var($payment['email'], FILTER_VALIDATE_EMAIL)) {
      throw new KomojuException(__d('Komoju' , 'Not a valid e-mail address'));
    }
    // Amount
    if (!isset($payment['amount'])) {
      throw new KomojuException(__d('Komoju' , 'Must specify an "amount" to charge'));
    }
    //Konbini Name
    if(!in_array($payment['store'], $this->konbiniNames)) {
      throw new KomojuException(__d('Komoju' , 'Not a valid kombini name'));
    }
    // Phone Number
    $phone = (isset($payment['phone'])) ? $payment['phone'] : null;
    // Currency
    $currency = (isset($payment['currency'])) ? $payment['currency'] : 'JPY';
    // Build Konbini data
    $data = array(
      'amount' => $payment['amount'],  // The total cost of the transaction
      'currency' => $currency,    // A 3-character currency code
      'external_order_num' => $this->merchantUUID,
      'metadata[foobar]' => $this->metadata,
      'payment_details[email]' => $payment['email'],
      'payment_details[phone]' => $payment['phone'],
      'payment_details[store]' => $payment['store'],
      'payment_details[type]' => $payment['payment_type']
    );
    return $data;
  }

/**
 * Takes a payment array and formats in to the minimum array to complete a bank transfer or pay easy payment
 *
 * @param array https://docs.komoju.com/en/api/overview/#bank_transfer
 * @return array Formatted array for Komoju bank transfer and pay easy payment
 * @author Samy Younsi (Shino Corp') 
 **/
  public function formatBankTransferAndPayEasyPayment($payment) {
    // Email address
    if (!isset($payment['email']) || !filter_var($payment['email'], FILTER_VALIDATE_EMAIL)) {
      throw new KomojuException(__d('Komoju' , 'Not a valid e-mail address'));
    }
    // Phone Number
    if (!isset($payment['phone'])) {
      throw new KomojuException(__d('Komoju' , 'Not a valid phone number'));
    }
    // Amount
    if (!isset($payment['amount'])) {
      throw new KomojuException(__d('Komoju' , 'Must specify an "amount" to charge'));
    }
    // last name
    if (!isset($payment['last_name'])) {
      throw new KomojuException(__d('Komoju' , 'Last name must be specify'));
    }
    // last_name kana
    if (!isset($payment['last_name_kana'])) {
      throw new KomojuException(__d('Komoju' , 'Last name kana must be specify'));
    }
    // first name
    if (!isset($payment['first_name'])) {
      throw new KomojuException(__d('Komoju' , 'First name must be specify'));
    }
    // first_name kana
    if (!isset($payment['first_name_kana'])) {
      throw new KomojuException(__d('Komoju' , 'first name kana must be specify'));
    }
    // Currency
    $currency = (isset($payment['currency'])) ? $payment['currency'] : 'JPY';
    // Build bank transfer and pay easy payment data
    $data = array(
      'amount' => $payment['amount'],  // The total cost of the transaction
      'currency' => $currency,    // A 3-character currency code
      'external_order_num' => $this->merchantUUID,
      'metadata[foobar]' => $this->metadata,
      'payment_details[email]' => $payment['email'],
      'payment_details[family_name]' => $payment['last_name'],
      'payment_details[family_name_kana]' => $payment['last_name_kana'],
      'payment_details[given_name]' => $payment['first_name'],
      'payment_details[given_name_kana]' => $payment['first_name_kana'],
      'payment_details[phone]' => $payment['phone'],
      'payment_details[type]' => $payment['payment_type']
    );
    return $data;
  }

/**
 * Takes a payment array and formats in to the minimum array to complete a BitCash payment
 *
 * @param array https://docs.komoju.com/en/api/overview/#bitcash
 * @return array Formatted array for Komoju bitcash payment
 * @author Samy Younsi (Shino Corp') 
 **/
  public function formatBitCashPayment($payment) {
     // prepaid number
    if (!isset($payment['card'])) {
      throw new KomojuException(__d('Komoju' , 'Not a valid prepaid number'));
    }
    $payment['card'] = preg_replace("/\s/" , "" , $payment['card']);
    // Amount
    if (!isset($payment['amount'])) {
      throw new KomojuException(__d('Komoju' , 'Must specify an "amount" to charge'));
    }
    // E-mail address
    $email = (isset($payment['email'])) ? $payment['email'] : null;
    // Currency
    $currency = (isset($payment['currency'])) ? $payment['currency'] : 'JPY';
    // Build BitCash array
    $data = array(
      'amount' => $payment['amount'],  // The total cost of the transaction
      'currency' => $currency,    // A 3-character currency code
      'email' => $email,
      'payment_details[prepaid_number]' => $payment['card'],
      'payment_details[type]' => $payment['payment_type']
    );
    return $data;
  }

/**
 * Takes a payment array and formats in to the minimum array to complete a WebMoney, Nanaco and NetCash payment
 *
 * @param array https://docs.komoju.com/en/api/overview/#nanaco
 * @return array Formatted array for Komoju nanaco, NetCash and WebMoney payment
 * @author Samy Younsi (Shino Corp') 
 **/
  public function formatWebMoneyAndNanacoAndNetCashPayment($payment) {
    // prepaid number
    if (!isset($payment['card'])) {
      throw new KomojuException(__d('Komoju' , 'Not a valid prepaid number'));
    }
    $payment['card'] = preg_replace("/\s/" , "" , $payment['card']);
    // Amount
    if (!isset($payment['amount'])) {
      throw new KomojuException(__d('Komoju' , 'Must specify an "amount" to charge'));
    }
    // E-mail address
    $email = (isset($payment['email'])) ? $payment['email'] : null;
    // Currency
    $currency = (isset($payment['currency'])) ? $payment['currency'] : 'JPY';
    // Build Nanaco array
    $data = array(
      'amount' => $payment['amount'],  // The total cost of the transaction
      'currency' => $currency,    // A 3-character currency code
      'external_order_num' => $this->merchantUUID,
      'metadata[foobar]' => $this->metadata,
      'email' => $email,
      'payment_details[prepaid_number]' => $payment['card'],
      'payment_details[type]' => $payment['payment_type']
    );
    return $data;
  }

/**
 * List Customers
 *
 * @return array
 * @author Samy Younsi (Shino Corp')
 **/
  public function listCustomers() {
    try {
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->customerResource;
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->get($endPoint);
      // Parse the results
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['resource'])) {
        return $parsed;
      }
      elseif (isset($parsed['error'])) {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error.'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem, please try again.'));
    }
  }

/**
 * Show Customer
 * @param string $customerID resource params
 * @return array
 * @author Samy Younsi (Shino Corp')
 **/
public function showCustomer($customerID) {
    try {
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->customerResource.DS.$customerID;
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->get($endPoint);
      // Parse the results
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['id'])) {
        return $parsed;
      }
      elseif (isset($parsed['error'])) {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error.'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem, please try again.'));
    }
  }
/**
 * Create a customer
 *
 * @param https://docs.komoju.com/en/api/resources/customers/create/
 * @return array Formatted array for add Komoju's customer 
 * @author Samy Younsi (Shino Corp') 
 **/
  public function createCustomer($customer) {
    // Email address
    $email = (isset($customer['email'])) ? $customer['email'] : null;
    // metadata order id
    $order_id = (isset($customer['order_id'])) ? $customer['order_id'] : null;
    // payment details
    $payment_details = (isset($customer['payment_details'])) ? $customer['payment_details'] : null;
    // Build Customer data
    $data = array(
      'email' => $email, // Optional
      'metadata[order_id]' => $order_id, // Optional
      'payment_details' => $payment_details // Optional
    );
    try { 
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->customerResource;
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->post($endPoint , $data);
      // Parse the results
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['id'])) {
        return $parsed;
      }
      elseif (isset($parsed['error'])) {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'An error occurred during the process of creating a customer'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem processing of process of creating, please try again.'));
    }
  }
/**
 * Update a customer
 *
 * @param https://docs.komoju.com/en/api/resources/customers/update/
 * @return array Formatted array for update a Komoju's customer 
 * @author Samy Younsi (Shino Corp') 
 **/
  public function updateCustomer($customer) {
    // customer_id
    if (!isset($customer['customer_id'])) {
      throw new KomojuException(__d('Komoju' , 'customer ID must be specify'));
    }
    $customerID = $customer['customer_id'];
    // Email address
    $email = (isset($customer['email'])) ? $customer['email'] : null;
    // metadata order id
    $order_id = (isset($customer['order_id'])) ? $customer['order_id'] : null;
    // payment details
    $payment_details = (isset($customer['payment_details'])) ? $customer['payment_details'] : null;
    // Build Customer data
    $data = array(
      'email' => $email, // Optional
      'metadata[order_id]' => $order_id, // Optional
      'payment_details' => $payment_details // Optional
    );
    try { 
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->customerResource.DS.$customerID;
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->patch($endPoint , $data);
      // Parse the results
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['id'])) {
        return $parsed;
      }
      elseif (isset($parsed['error'])) {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'An error occurred during the process of updating a customer'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem processing of process of updating, please try again.'));
    }
  }
/**
 * Delete Customer
 * @param string $customerID params
 * @return array
 * @author Samy Younsi (Shino Corp')
 **/
public function deleteCustomer($customerID) {
    try {
      // HttpSocket
      if (!$this->HttpSocket) {
        $this->HttpSocket = new HttpSocket();
      }
      // API endpoint
      $endPoint = $this->restEndpoint.$this->komojuApiVersion.DS.$this->customerResource.DS.$customerID;
      // Make a Http request for a new token
      $this->HttpSocket->configAuth('Basic', $this->secretKey, '');
      $response = $this->HttpSocket->delete($endPoint);
      // Parse the results
      $parsed = $this->parseClassicApiResponse($response);
      // Handle the response
      if (isset($parsed['id'])) {
        return $parsed;
      }
      elseif (isset($parsed['error'])) {
        throw new KomojuException($this->getErrorMessage($parsed));
      }
      else {
        throw new KomojuException(__d('Komoju', 'There was an error.'));
      }
    } catch (SocketException $e) {
      throw new KomojuException(__d('Komoju', 'There was a problem, please try again.'));
    }
  }
/**
 * Returns custom error message if there are any set for the error code passed in with the parsed response.
 * Returns the long message in the response otherwise.
 *
 * @param  array $parsed Parsed response
 * @return string The error message
 * @author Samy Younsi (Shino Corp')
 */
  public function getErrorMessage($parsed) {
    if (array_key_exists($parsed['error']['code'], $this->errorMessages)) {
      if (isset($parsed['error']['param'])) {
        return sprintf($this->errorMessages[$parsed['error']['code']],$parsed['error']['param']);
      }else{
        return $this->errorMessages[$parsed['error']['code']];
      }
    }
    return $parsed['error']['message'];
  }

/**
 * Validates a credit card number
 *
 * @return void
 * @author Samy Younsi
 **/
  public function validateCC($cc) {
    return Validation::cc($cc);
  }

/**
 * Parse the body of the reponse
 *
 * @param string A URL encoded response from Komoju
 * @return array nicely parsed array
 * @author Samy Younsi (Shino Corp')
 **/
  public function parseClassicApiResponse($response) {
    $parsed = json_decode($response['body'], true);
    return $parsed;
  }
}