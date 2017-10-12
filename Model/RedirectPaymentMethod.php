<?php
/**
 * PayU_EasyPlus payment method model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Quote\Model\Quote;

/**
 * Redirect payment method model
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RedirectPaymentMethod extends PayU
{
    const CODE = 'redirectpaymentmethod';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'redirectpaymentmethod';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isInitializeNeeded = false;

    protected $_isOffline = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;
    
    protected $_easyPlusApi                 = false;
    protected $_dataFactory                 = false;
    protected $_requestFactory              = false;
    protected $_responseFactory             = false;
    protected $_storeManager                = false;
    protected $_checkoutSession             = false;
    protected $_session                     = false;
    protected $_response                    = null;
    protected $_paymentData                 = false;
    protected $_payuReference               = '';
    protected $_minAmount                   = null;
    protected $_maxAmount                   = null;
    protected $_redirectUrl                 = '';
    protected $_supportedCurrencyCodes      = array('ZAR', 'NGN');
    /**
     * Payment additional information key for payment action
     *
     * @var string
     */
    protected $_isOrderPaymentActionKey = 'is_order_action';

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    protected $orderFactory;
    protected $quoteRepository;
    protected $orderSender;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var Transaction\BuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $_transaction;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Session\Generic $session,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \PayU\EasyPlus\Model\Api\Factory $apiFactory,
        \PayU\EasyPlus\Helper\DataFactory $dataFactory,
        \PayU\EasyPlus\Model\Request\Factory $requestFactory,
        \PayU\EasyPlus\Model\Response\Factory $responseFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\DB\Transaction $transaction,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );

        $this->_dataFactory = $dataFactory;
        $this->_requestFactory = $requestFactory;
        $this->_responseFactory = $responseFactory;
        $this->_storeManager = $storeManager;
        $this->_checkoutSession = $checkoutSession;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->_invoiceService = $invoiceService;
        $this->_orderRepository = $orderRepository;
        $this->_easyPlusApi = $apiFactory->create();
        $this->_session = $session;
        $this->_paymentData = $paymentData;
        $this->orderFactory = $orderFactory;
        $this->quoteRepository = $quoteRepository;
        $this->orderSender = $orderSender;
        $this->_transaction = $transaction;

        $this->_easyPlusApi->setSafeKey(
            $this->getConfigData('safe_key')
        );
        $this->_easyPlusApi->setUsername(
            $this->getConfigData('api_username')
        );
        $this->_easyPlusApi->setPassword(
            $this->getConfigData('api_password')
        );
        $this->_easyPlusApi->setGatewayEndpoint(
            $this->getConfigData('gateway')
        );

        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }

    /**
     * Store setter
     *
     * @param \Magento\Store\Model\Store|int $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->setData('store', $store);
        if (null === $store) {
            $store = $this->_storeManager->getStore()->getId();
            $this->setData('store', $store);
        }
        
        return $this;
    }

    /**
     * Get api
     *
     * @return \PayU\EasyPlus\Model\Api
     */
    public function getApi()
    {
        return $this->_easyPlusApi;
    }

    /**
     * Get api
     *
     * @return \PayU\EasyPlus\Model\Response
     */
    public function getResponse()
    {
        if(null === $this->_response) {
            $this->_response = $this->_responseFactory->create();
        }

        return $this->_response;
    }

    /**
     * Fill response with data.
     *
     * @param array $postData
     * @return $this
     */
    public function setResponseData($postData)
    {
        $this->getResponse()->setData('return', $postData);

        return $this;
    }

    /**
     * Getter for specified value according to set payment method code
     *
     * @param mixed $key
     * @param null $storeId
     * @return mixed
     */
    public function getValue($key, $storeId = null)
    {
        return $this->getConfigData($key, $storeId);
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return parent::canUseForCurrency($currencyCode);
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    /*public function initialize($paymentAction, $stateObject)
    {
        switch ($paymentAction) {
            case self::ACTION_ORDER:
                $this->_setupTransaction($payment, $order->getBaseTotalDue());
                break;
            default:
                break;
        }
    }*/

    /**
     * Check response code came from PayU.
     *
     * @return true in case of Approved response
     * @throws \Magento\Framework\Exception\LocalizedException In case of Declined or Error response from Authorize.net
     */
    public function checkResponseCode()
    {
        switch ($this->getResponse()->getTransactionState()) {
            case self::TRANS_STATE_SUCCESSFUL:
            case self::TRANS_STATE_AWAITING_PAYMENT:
                return true;
            case self::TRANS_STATE_FAILED:
            case self::TRANS_STATE_EXPIRED:
            case self::TRANS_STATE_TIMEOUT:
                throw new \Magento\Framework\Exception\LocalizedException(
                    $this->_dataFactory->create('frontend')->wrapGatewayError($this->getResponse()->getResultMessage())
                );
            default:
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('There was a payment verification error.')
                );
        }
    }

    /**
     * Check transaction id came from Authorize.net
     *
     * @return true in case of right transaction id
     * @throws \Magento\Framework\Exception\LocalizedException In case of bad transaction id.
     */
    public function checkTransId()
    {
        if (!$this->getResponse()->getTranxId()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment verification error: invalid PayU reference')
            );
        }
        return true;
    }

    /**
     * Compare amount with amount from the response from Authorize.net.
     *
     * @param float $amount
     * @return bool
     */
    protected function matchAmount($amount)
    {
        $amountPaid = $this->getResponse()->getTotalCaptured();

        return sprintf('%.2F', $amount) == sprintf('%.2F', $amountPaid);
    }

    /**
     * Order payment
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $payUReference = $this->_session->getCheckoutReference();
        if (!$payUReference) {
            return $this->_setupTransaction($payment, $amount);
        }

        $this->_importToPayment($this->getResponse(), $payment);

        $payment->setAdditionalInformation($this->_isOrderPaymentActionKey, true);

        if ($payment->getIsFraudDetected()) {
            return $this;
        }

        $order = $payment->getOrder();

        $state = \Magento\Sales\Model\Order::STATE_PROCESSING;

        $formattedPrice = $order->getBaseCurrency()->formatTxt($amount);
        if ($payment->getIsTransactionPending()) {
            $message = __('The ordered amount of %1 is still pending approval on the payment gateway.', $formattedPrice);
            $state = \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW;
        } else {
            $message = __('Ordered amount of %1', $formattedPrice);
        }

        $transaction = $this->transactionBuilder->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($payment->getTransactionId())
            ->build(Transaction::TYPE_ORDER);
        $payment->addTransaction(Transaction::TYPE_ORDER);

        $payment->addTransactionCommentsToOrder($transaction, $message);

        $order->setState($state);
        $order->setCanSendNewEmailFlag(true);

        $payment->setSkipOrderProcessing(true);

        return $this;
    }

    /**
     * Setup transaction before redirect
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _setupTransaction(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->validateAmount($amount);

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);
        $payment->setBaseAmountOrdered($order->getBaseTotalDue());
        $payment->setAmountOrdered($order->getTotalDue());
        $payment->getMethodInstance()->setIsInitializeNeeded(true);

        $helper = $this->_dataFactory->create('frontend');

        try {

            $request = $this->generateRequestFromOrder($order, $helper);
            $response = $this->_easyPlusApi->doSetTransaction($request->getData());

            if($response->return->successful) {
                $payUReference = $response->return->payUReference;

                $this->_session->setCheckoutReference($payUReference);
                $this->_session->setCheckoutOrderIncrementId($order->getIncrementId());

                $this->_easyPlusApi->setPayUReference($payUReference);
                $this->_session->setCheckoutRedirectUrl($this->_easyPlusApi->getRedirectUrl());

                // set session variables

                $this->_checkoutSession->setLastQuoteId($order->getQuoteId())
                    ->setLastSuccessQuoteId($order->getQuoteId());

                $this->_checkoutSession->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());

                $message = 'Amount of %1 is pending approval. Redirecting to PayU.<br/>'
                    . 'PayU reference "%2"<br/>';
                $message = __(
                    $message,
                    $order->getBaseCurrency()->formatTxt($amount),
                    $payUReference
                );
                $order->addStatusHistoryComment($message);
                $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);

            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__('Inside PayU, server error encountered'));
            }
        } catch (\Exception $e) {
            //$this->debugData(['request' => $request->getData(), 'exception' => $e->getMessage()]);
            //$this->debugData(['response' => $response]);
            $this->_logger->error(__('Payment capturing error. Reason: ' . $e->getMessage()));

            throw new \Magento\Framework\Exception\LocalizedException(__('Redirect payment setup error.'));
        }

        return $this;
    }

    public function processCancellation($responseData)
    {
        $this->setResponseData($responseData);
        $response = $this->getResponse();

        $payUReference = $response->getTranxId();

        //operate with order
        $orderIncrementId = $response->getInvoiceNum();

        $message = 'Payment transaction of amount of %1 was canceled by user on PayU.<br/>' . 'PayU reference "%2"<br/>';

        $isError = false;
        if ($orderIncrementId) {
            /* @var $order \Magento\Sales\Model\Order */
            $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
            //check payment method
            $payment = $order->getPayment();
            if (!$payment || $payment->getMethod() != $this->getCode()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('This payment didn\'t work out because we can\'t find this order.')
                );
            }
            if ($order->getId()) {
                //operate with order
                $message = __(
                    $message,
                    $order->getBaseCurrency()->formatTxt($order->getBaseTotalDue()),
                    $payUReference
                );

                $order->addStatusHistoryComment($message);
                $order->cancel()->save();
            } else {
                $isError = true;
            }
        } else {
            $isError = true;
        }

        if ($isError) {
            $responseText = $this->_dataFactory->create('frontend')->wrapGatewayError($response->getResultMessage());
            $responseText = $responseText
                ? $responseText
                : __('This payment didn\'t work out because we can\'t find this order.');
            throw new \Magento\Framework\Exception\LocalizedException($responseText);
        }
    }

    /**
     * Operate with order using data from $_POST which came from PayU by Return URL.
     *
     * @param array $responseData data from PayU from $_POST
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException In case of validation error or order creation error
     */
    public function process($responseData)
    {
        //$this->_debug(['response' => $responseData]);

        $this->setResponseData($responseData);

        $response = $this->getResponse();
        //operate with order
        $orderIncrementId = $response->getInvoiceNum();

        $isError = false;
        if ($orderIncrementId) {
            /* @var $order \Magento\Sales\Model\Order */
            $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
            //check payment method
            $payment = $order->getPayment();
            if (!$payment || $payment->getMethod() != $this->getCode()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('This payment didn\'t work out because we can\'t find this order.')
                );
            }
            if ($order->getId()) {
                //operate with order
                $this->processOrder($order);
            } else {
                $isError = true;
            }
        } else {
            $isError = true;
        }

        if ($isError) {
            $responseText = $this->_dataFactory->create('frontend')->wrapGatewayError($response->getResultMessage());
            $responseText = $responseText && !$response->isPaymentSuccessful()
                ? $responseText
                : __('This payment didn\'t work out because we can\'t find this order.');
            throw new \Magento\Framework\Exception\LocalizedException($responseText);
        }
    }

    /**
     * Operate with order using information from Authorize.net.
     * Authorize order or authorize and capture it.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function processOrder(\Magento\Sales\Model\Order $order)
    {
        try {
            $this->checkResponseCode();
            $this->checkTransId();
        } catch (\Exception $e) {
            //decline the order (in case of wrong response code) but don't return money to customer.
            $message = $e->getMessage();
            $this->declineOrder($order, $message, false);
            throw $e;
        }

        $response = $this->getResponse();

        //create transaction. need for void if amount will not match.
        /* @var $payment \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment */
        $payment = $order->getPayment();
        $this->fillPaymentByResponse($payment);
        $payment->getMethodInstance()->setIsInitializeNeeded(false);
        $payment->getMethodInstance()->setResponseData($response->getReturn());
        $this->processPaymentFraudStatus($payment);
        $this->addStatusCommentOnUpdate($payment, $response);
        //$payment->place();
        //$order->save();
        //match amounts. should be equals for authorization.
        //decline the order if amount does not match.
        if (!$this->matchAmount($payment->getBaseAmountOrdered())) {
            $message = __(
                'Something went wrong: the paid amount doesn\'t match the order amount.'
                . ' Please correct this and try again.'
            );
            $this->declineOrder($order, $message, true);
            throw new \Magento\Framework\Exception\LocalizedException($message);
        }

        try {
            $order->setCanSendNewEmailFlag(true);
            $this->orderSender->send($order);

            if($order->canInvoice()) {
                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->save();
                $transactionSave = $this->_transaction->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionSave->save();
                $this->invoiceSender->send($invoice);
                //send notification code
                $order->addStatusHistoryComment(
                    __('Notified customer about invoice #%1.', $invoice->getId())
                )
                ->setIsCustomerNotified(true)
                ->save();
            }

            $quote = $this->quoteRepository->get($order->getQuoteId())->setIsActive(false);
            $this->quoteRepository->save($quote);

        } catch (\Exception $e) {
            // do not cancel order if we couldn't send email
        }
    }

    /**
     * Fill payment with credit card data from response from PayU.
     *
     * @param \Magento\Framework\DataObject $payment
     * @return void
     */
    protected function fillPaymentByResponse(\Magento\Framework\DataObject $payment)
    {
        $response = $this->getResponse();
        $payment->setTransactionId($response->getTranxId())
            ->setParentTransactionId(null)
            ->setIsTransactionClosed(0)
            ->setTransactionAdditionalInfo(self::REAL_TRANSACTION_ID_KEY, $response->getTranxId());

        if ($response->isPaymentMethodCc()) {
            $payment->setGatewayReference($response->getGatewayReference())
                ->setCcLast4($payment->encrypt(substr($response->getCcNumber(), -4)));
        }

        if ($response->getTransactionState() == self::TRANS_STATE_AWAITING_PAYMENT) {
            $payment->setIsTransactionPending(true);
        }

        if($response->isFraudDetected()) {
            $payment->setIsFraudDetected(true);
        }
    }

    /**
     * Process fraud status
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @return $this
     */
    protected function processPaymentFraudStatus(\Magento\Sales\Model\Order\Payment $payment)
    {
        try {
            $fraudDetailsResponse = $payment->getMethodInstance()
                ->fetchTransactionFraudDetails($payment, $this->getResponse()->getTranxId());
            $fraudData = $fraudDetailsResponse->getData();

            if (empty($fraudData)) {
                $payment->setIsFraudDetected(false);
                return $this;
            }

            $payment->setIsFraudDetected(true);
            $payment->setAdditionalInformation('fraud_details', $fraudData);
        } catch (\Exception $e) {
            //this request is optional
        }

        return $this;
    }

    /**
     * Generate request object and fill its fields from Quote or Order object
     *
     * @param \Magento\Sales\Model\Order $order Quote or order object.
     * @return \PayU\EasyPlus\Model\Request
     */
    public function generateRequestFromOrder(\Magento\Sales\Model\Order $order, $helper)
    {
        $request = $this->_requestFactory->create()
            ->setConstantData($this, $order, $helper)
            ->setDataFromOrder($order, $this);

        //$this->_debug(['request' => $request->getData()]);

        return $request;
    }

    /**
     * Register order cancellation. Return money to customer if needed.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $message
     * @param bool $voidPayment
     * @return void
     */
    public function declineOrder(\Magento\Sales\Model\Order $order, $message = '', $voidPayment = true, $response)
    {
        $payment = $order->getPayment();
        try {
            if (
                $voidPayment && $response->getTranxId()
                && strtoupper($response->getTransactionType()) == self::REQUEST_TYPE_PAYMENT
            ) {
                $this->_importToPayment($response, $payment);
                $this->addStatusCommentOnUpdate($payment, $response);
                $order->registerCancellation()->save();
            }
        } catch (\Exception $e) {
            //quiet decline
            $this->_logger->critical($e);
        }
    }

    /**
     * Fetch transaction details info
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $transactionId
     * @return array
     */
    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        return $this->_easyPlusApi->fetchTransactionInfo($payment, $transactionId);
    }

    /**
     * Fetch fraud details
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $transactionId
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function fetchTransactionFraudDetails(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        $response = $this->fetchTransactionInfo($payment, $transactionId);
        $responseData = new \Magento\Framework\DataObject();

        if (empty($response->transaction->FDSFilters->FDSFilter)) {
            return $response;
        }

        $responseData->setFdsFilterAction(
            $response->transaction->FDSFilterAction
        );
        $responseData->setAvsResponse((string)$response->transaction->AVSResponse);
        $responseData->setCardCodeResponse((string)$response->transaction->cardCodeResponse);
        $responseData->setCavvResponse((string)$response->transaction->CAVVResponse);
        $responseData->setFraudFilters($this->getFraudFilters($response->transaction->FDSFilters));

        return $responseData;
    }

    /**
     * Get fraud filters
     *
     * @param \Magento\Framework\Simplexml\Element $fraudFilters
     * @return array
     */
    protected function getFraudFilters($fraudFilters)
    {
        $result = [];

        foreach ($fraudFilters->FDSFilter as $filer) {
            $result[] = [
                'name' => (string)$filer->name,
                'action' => (string)$filer->action
            ];
        }

        return $result;
    }

    /**
     * Import payment info to payment
     *
     * @param Response $response
     * @param InfoInterface $payment
     * @return void
     */
    protected function _importToPayment($response, $payment)
    {
        $payment->setTransactionId($response->getTranxId())
            ->setIsTransactionClosed(0);

        $this->_easyPlusApi->importPaymentInfo($response, $payment);
    }

    /**
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param \Magento\Framework\DataObject $response
     * @param string $transactionId
     */
    protected function addStatusCommentOnUpdate(
        \Magento\Sales\Model\Order\Payment $payment,
        \Magento\Framework\DataObject $response
    ) {
        $transactionId = $response->getTranxId();

        if ($payment->getIsTransactionApproved()) {
            $message = __(
                'Transaction %1 has been approved. Amount %2. Transaction status is "%3"',
                $transactionId,
                $payment->getOrder()->getBaseCurrency()->formatTxt($payment->getAmountOrdered()),
                $response->getTransactionState()
            );
            $payment->getOrder()->addStatusHistoryComment($message);
        } elseif ($payment->getIsTransactionDenied()) {
            $message = __(
                'Transaction %1 has been voided/declined. Transaction status is "%2". Amount %3.',
                $transactionId,
                $response->getTransactionState(),
                $payment->getOrder()->getBaseCurrency()->formatTxt($payment->getAmountOrdered())
            );
            $payment->getOrder()->addStatusHistoryComment($message);
        }
    }

    /**
     * Check whether payment method can be used
     * @param \Magento\Quote\Api\Data\CartInterface|Quote|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote) && $this->isMethodAvailable();
    }

    /**
     * Check whether method available for checkout or not
     *
     * @param null $methodCode
     *
     * @return bool
     */
    public function isMethodAvailable($methodCode = null)
    {
        $methodCode = $methodCode ?: $this->_code;

        return $this->isMethodActive($methodCode);
    }

    /**
     * Check whether method active in configuration and supported for merchant country or not
     *
     * @param string $method Method code
     * @return bool
     *
     * @todo: refactor this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isMethodActive($method)
    {
        $isEnabled = (bool)$this->getConfigData('active');

        return $this->isMethodSupportedForCountry($method) && $isEnabled;
    }

    /**
     * Check whether method supported for specified country or not
     *
     * @param string|null $method
     * @param string|null $countryCode
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isMethodSupportedForCountry($method = null, $countryCode = null)
    {
        return true;
    }

    protected function validateAmount($amount)
    {
        if ($amount <= 0 || $amount < $this->_minAmount || $amount > $this->_maxAmount) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for checkout with this payment method.'));
        }
    }

    public function getCheckoutRedirectUrl()
    {
        return $this->_session->getCheckoutRedirectUrl();
    }

    /**
     * Get transaction with type order
     *
     * @param OrderPaymentInterface $payment
     * @return false|\Magento\Sales\Api\Data\TransactionInterface
     */
    protected function getOrderTransaction($payment)
    {
        return $this->transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId(),
            $payment->getOrder()->getId()
        );
    }
}
