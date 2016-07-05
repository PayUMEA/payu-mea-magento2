<?php
/**
 * PayU_EasyPlus payement response validation model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

class Response extends \Magento\Framework\DataObject
{
	protected $errorCode;
	protected $api;

	public function __construct(
        \PayU\EasyPlus\Model\Error\Code $errorCodes,
        \PayU\EasyPlus\Model\Api\Factory $apiFactory,
        array $data = array()
	) {
		$this->errorCode = $errorCodes;
		$this->api = $apiFactory->create();

		parent::__construct($data);
	}

    public function getReturn()
    {
        return $this->getData('return');
    }

    public function isPaymentSuccessful()
    {
        return $this->getReturn()->successful
            && $this->getTransactionState() == PayU::TRANS_STATE_SUCCESSFUL;
    }

    public function getTranxId()
    {
        return $this->getReturn()->payUReference;
    }

    public function getInvoiceNum()
    {
        return $this->getReturn()->merchantReference;
    }

    public function getResultCode()
    {
        return $this->getReturn()->resultCode;
    }

    public function getResultMessage()
    {
        return $this->getReturn()->resultMessage;
    }

    public function isPaymentMethodCc()
    {
        return isset($this->getReturn()->paymentMethodUsed->Creditcard);
    }

    public function getGatewayReference()
    {
        return isset($this->getReturn()->paymentMethodUsed->gatewayReference);
    }

    public function getCcNumber()
    {
        return isset($this->getReturn()->paymentMethodUsed->cardNumber);
    }

    public function getTotalCaptured()
    {
        return ($this->getReturn()->paymentMethodsUsed->amountInCents / 100);
    }

    public function getDisplayMessage()
    {

        return $this->getReturn()->displayMessage;
    }

    public function isFraudDetected()
    {
        return isset($this->getReturn()->fraud->resultCode);
    }

    public function getTransactionState()
    {
        return $this->getReturn()->transactionState;
    }

    public function getTransactionType()
    {
        return $this->getReturn()->transactionType;
    }

	public function process($order)
	{
		$response = $this->api->doGetTransaction($this->getParams());
		$payment = $order->getPayment();

		if($response && $response->isPaymentSuccessful()) {
		    $this->api->importPaymentInfo($response, $payment);
			$payment->getMethodInstance()->process($response->getReturn());

			return true;
		} else {
		    $message = $response->getDisplayMessage();
			$payment->getMethodInstance()->declineOrder($order, $message);

			return $message;
		}
	}
}