<?php
/**
 * PayU_EasyPlus PayU API
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

class Api extends \Magento\Framework\DataObject
{
    protected static $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    private static $_soapClient;

    protected $scopeConfig;

    // @var string The base sandbox URL for the PayU API endpoint.
    protected static $sandboxUrl = 'https://staging.payu.co.za/service/PayUAPI';
    protected static $sandboxCheckoutUrl = 'https://staging.payu.co.za/rpp.do?PayUReference=%s';

    // @var string The base live URL for the PayU API endpoint.
    protected static $liveUrl = 'https://secure.payu.co.za/service/PayUAPI';
    protected static $liveCheckoutUrl = 'https://secure.payu.co.za/rpp.do?PayUReference=%s';

    // @var string The PayU safe key to be used for requests.
    protected $safeKey;

    // @var string|null The version of the PayU API to use for requests.
    protected static $apiVersion = 'ONE_ZERO';

    protected static $username;

    protected static $password;

    protected $merchantRef;

    protected $payuReference;

    /** var PayU\EasyPlus\Model\Response */
    protected $response;

    /** var PayU\EasyPlus\Model\Response\Factory */
    protected $responseFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    protected static $wsdlUrl;
    protected static $checkoutUrl;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PayU\EasyPlus\Model\Response\Factory $responseFactory,
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;

        parent::__construct($data);

        $this->responseFactory = $responseFactory;
        $this->_logger = $logger;
    }

    /**
     * @return string The safe key used for requests.
     */
    public function getSafeKey()
    {
        return $this->safeKey;
    }

    /**
     * Sets the safe key to be used for requests.
     *
     * @param string $safeKey
     */
    public function setSafeKey($safeKey)
    {
        $this->safeKey = $safeKey;
    }

    /**
     * @return string The API version used for requests. null if we're using the
     *    latest version.
     */
    public static function getApiVersion()
    {
        return self::$apiVersion;
    }

    /**
     * @return string The soap user used for requests.
     */
    public static function getUsername()
    {
        return self::$username;
    }

    /**
     * Sets the soap username to be used for requests.
     *
     * @param string $username
     */
    public static function setUsername($username)
    {
        self::$username = $username;
    }

    /**
     * @return string The soap password used for requests.
     */
    public static function getPassword()
    {
        return self::$password;
    }

    /**
     * Sets the soap password to be used for requests.
     *
     * @param string $password
     */
    public static function setPassword($password)
    {
        self::$password = $password;
    }

    /**
     * @return string The merchant reference to identify captured payments..
     */
    public function getMerchantReference()
    {
        return $this->merchantRef;
    }

    /**
     * Sets the merchant reference to identify captured payments.
     *
     * @param string $merchantRef
     */
    public function setMerchantReference($merchantRef)
    {
        $this->merchantRef = $merchantRef;

        return $this;
    }

    /**
     * @return string The reference from PayU.
     */
    public function getPayUReference()
    {
        return $this->payuReference;
    }

    /**
     * Sets the PayU reference.
     *
     * @param string $reference
     */
    public function setPayUReference($reference)
    {
        $this->payuReference = $reference;

        return $this;
    }

    /**
     * @return string The soap wsdl endpoint to send requests.
     */
    public static function getSoapEndpoint()
    {
        return self::$wsdlUrl;
    }

    /**
     * @return string The redirect payment page url to be used for requests.
     */
    public function getRedirectUrl()
    {
        return sprintf(self::$checkoutUrl, $this->getPayUReference());
    }

    /**
     * @return \PayU\EasyPlus\Model\Response The return data.
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Sets the redirect payment page url to be used for requests.
     *
     * @param string $gateway
     */
    public static function setGatewayEndpoint($gateway)
    {
        if(!$gateway) {
            self::$wsdlUrl = self::$sandboxUrl;
            self::$checkoutUrl = self::$sandboxCheckoutUrl;
        } else {
            self::$wsdlUrl = self::$liveUrl;
            self::$checkoutUrl = self::$liveCheckoutUrl;
        }
    }

    private static function getSoapHeader()
    {
        $header  = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
        $header .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
        $header .= '<wsse:Username>'.self::getUsername().'</wsse:Username>';
        $header .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.self::getPassword().'</wsse:Password>';
        $header .= '</wsse:UsernameToken>';
        $header .= '</wsse:Security>';

        return $header;
    }

    public function doGetTransaction($txn_id)
    {
        $method = \PayU\EasyPlus\Model\ConfigProvider::CODE;
        $reference = isset($txn_id['PayUReference']) ? $txn_id['PayUReference'] : $txn_id;

        $data['Api'] = self::getApiVersion();
        $data['Safekey'] = $this->scopeConfig->getValue(
                        "payment/{$method}/safe_key",
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $data['AdditionalInformation']['payUReference'] = $reference;;

        //$this->_logger->debug(print_r($this->debug(['data' => $data]), true));
        $result = self::getSoapSingleton()->getTransaction($data);

        $result = json_decode(json_encode($result));

        $this->response = $this->responseFactory->create();

        $this->response->setData('return', $result->return);

        return $this->response;
    }

    public function doSetTransaction($requestData)
    {
        $response = self::getSoapSingleton()->setTransaction($requestData);

        return json_decode(json_encode($response));
    }

    private static function getSoapSingleton()
    {
        if(is_null(self::$_soapClient))
        {
            $header = self::getSoapHeader();
            $soapWsdlUrl = self::getSoapEndpoint().'?wsdl';
            self::$wsdlUrl = $soapWsdlUrl;

            $headerBody = new \SoapVar($header, XSD_ANYXML, null, null, null);
            $soapHeader = new \SOAPHeader(self::$ns, 'Security', $headerBody, true);

            self::$_soapClient = new \SoapClient($soapWsdlUrl, array('trace' => 1, 'exception' => 0));
            self::$_soapClient->__setSoapHeaders($soapHeader);
        }

        return self::$_soapClient;
    }

    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        $response = $this->doGetTransaction($transactionId);

        $this->importPaymentInfo($this->response, $payment);

        return $response;
    }

    /**
     * Transfer transaction/payment information from API instance to order payment
     *
     * @param \Magento\Framework\DataObject $from
     * @param \Magento\Payment\Model\InfoInterface $to
     * @return $this
     */
    public function importPaymentInfo(\Magento\Framework\DataObject $from, \Magento\Payment\Model\InfoInterface $to)
    {
        /**
         * Detect payment review and/or frauds
         */
        if ($from->isFraudDetected()) {
            $to->setIsTransactionPending(true);
            $to->setIsFraudDetected(true);
        }

        // give generic info about transaction state
        if ($from->isPaymentSuccessful()) {
            $to->setIsTransactionApproved(true);
        } else {
            $to->setIsTransactionDenied(true);
        }

        return $this;
    }
}
