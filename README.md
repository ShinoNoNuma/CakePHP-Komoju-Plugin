# CakePHP Komoju Plugin
A simple CakePHP plugin to interact with Komoju's REST APIs.

**NOTE:** Tested with CakePHP 2.6.x and up, though please note it's not compatible with CakePHP 3.x.**.

### Requirements

* CakePHP 2.x
* A Komoju account

### Installation

_[Manual]_

* Download this: [https://github.com/ShinoNoNuma/CakePHP-Komoju-Plugin/zipball/master](https://github.com/ShinoNoNuma/CakePHP-Komoju-Plugin/zipball/master)
* Unzip that download.
* Copy the resulting folder to `ProjectName/plugins`
* Rename the folder you just copied to `Komoju`

_[GIT Submodule]_

In your app directory type:

```shell
git submodule add -b master git://github.com/ShinoNoNuma/CakePHP-Komoju-Plugin.git plugins/Komoju
git submodule init
git submodule update
```

_[GIT Clone]_

In your `Plugin` directory type:

```shell
git clone -b master git://github.com/ShinoNoNuma/CakePHP-Komoju-Plugin.git Komoju
```

### Usage

Make sure the plugin is loaded in `app/Config/bootstrap.php`.

```php
CakePlugin::load('Komoju');
```
Also, set your Komoju's API keys still in `app/Config/bootstrap.php`.
For testing purposes, ensure `sandboxMode` is set to `true`.

```php
Configure::write('Komoju', [
    'sandboxMode' => true,
    'merchantUUID' => 'YourTestMerchantUUID',
    'publishableKey' => 'YourTestpublishableKey',
    'secretKey' => 'YourTestSecretKey'
]);
```

And don't forget to load the class Komoju in the desired controller

```php
App::uses('Komoju', 'Komoju.Lib');
```
### createPayment (Credit Card)

Complete a transaction using the credit card method. An array will be returned.

```php
$this->Komoju = new Komoju(Configure::read('Komoju'));

$payment = array(
   'first_name' => 'Sacha',
   'last_name' => 'Ketchum',
   'amount' => 1000, // The total cost of the transaction
   'card' => '4111111111111111', //This a sandbox CC
   'expiry' => array(
     'M' => 03, // Month
     'Y' => 2021, // Year
   ),
   'cvv' => '123',
   'currency' => 'JPY', // A 3-character currency code
   'payment_type' => 'credit_card'
);
            
try {
  $this->Komoju->createPayment($payment);
} catch (Exception $e) {
  $e->getMessage();
} 
```

### createPayment (Convenience Store Konbini)

Complete a transaction using the Konbini method. An array will be returned.
The parameter `store` can be: `daily-yamazaki`, `family-mart`, `lawson`, `ministop` or `seven-eleven`

```php
$this->Komoju = new Komoju(Configure::read('Komoju'));

$payment = array(
   'amount' => 1000, // The total cost of the transaction
   'currency' => 'JPY', // A 3-character currency code
   'email' => 'sacha@pokemon.com',
   'phone' => '080-1111-1111', // Optional
   'store' => 'seven-eleven',
   'payment_type' => 'konbini'
);
            
try {
  $this->Komoju->createPayment($payment);
} catch (Exception $e) {
  $e->getMessage();
} 
```

### createPayment (Bank Transfer or Pay Easy)

Complete a transaction using the bank transfer or Pay Easy method. The same array is required for both payment methods. 
An array will be returned. The parameter `payment_type` can be: `bank_transfer` or `pay_easy`

```php
$this->Komoju = new Komoju(Configure::read('Komoju'));

$payment = array(
   'amount' => 1000, // The total cost of the transaction
   'currency' => 'JPY', // A 3-character currency code
   'email' => 'sacha@pokemon.com',
   'family_name' => 'Ketchum',
   'family_name_kana' => 'ケッチャム',
   'given_name' => 'Sacha',
   'given_name_kana' => 'サシャ',
   'phone' => '080-1111-1111',
   'payment_type' => 'bank_transfer'
);
            
try {
  $this->Komoju->createPayment($payment);
} catch (Exception $e) {
  $e->getMessage();
} 
```

### createPayment (Bit Cash, Web Money, Nanaco or Net Cash)

Complete a transaction using the bit cash, web money, nanaco or net cash method. The same array is required for all four payment methods. 
An array will be returned. The parameter `payment_type` can be: `bit_cash`, `web_money`, `nanaco` or `net_cash`

```php
$this->Komoju = new Komoju(Configure::read('Komoju'));

$payment = array(
   'amount' => 1000, // The total cost of the transaction
   'currency' => 'JPY', // A 3-character currency code
   'email' => 'sacha@pokemon.com', // Optional
   'card' => '1111111111111111', // Prepaid card number
   'payment_type' => 'nanaco'
);
            
try {
  $this->Komoju->createPayment($payment);
} catch (Exception $e) {
  $e->getMessage();
} 
```

### listPayments

List all transactions completed. An array will be returned.

```php
$this->Komoju = new Komoju(Configure::read('Komoju'));
            
try {
  $this->Komoju->listPayments();
} catch (Exception $e) {
  $e->getMessage();
} 
```

### showPayment

Show the details of one completed transaction. An array will be returned.

```php
$this->Komoju = new Komoju(Configure::read('Komoju'));

$paymentID = '2vf94gpydhik6red1fwg66n19';

try {
  $this->Komoju->showPayment($paymentID);
} catch (Exception $e) {
  $e->getMessage();
} 
```

### cancelPayment

Cancel a payment. An array will be returned.

```php
$this->Komoju = new Komoju(Configure::read('Komoju'));

$paymentID = '2vf94gpydhik6red1fwg66n19';

try {
  $this->Komoju->cancelPayment($paymentID);
} catch (Exception $e) {
  $e->getMessage();
} 
```

### refundPayment

Refund a payment. An array will be returned.
The parameter `payment_type` can be: `credit_card`, `bit_cash`, `nanaco` or `net_cash`

```php
$this->Komoju = new Komoju(Configure::read('Komoju'));

$refund = array(
  'payment_id' => '2vf94gpydhik6red1fwg66n19',
  'amount' => 1000, // Used only if payment_type is credit card 
  'payment_type' => 'credit_card'
);

try {
  $this->Komoju->refundPayment($refund);
} catch (Exception $e) {
  $e->getMessage();
} 
```