<?php
/**
 * PayU_EasyPlus payment request
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

class Request extends \Magento\Framework\DataObject
{
    /**
     * Set PayU data to request.
     *
     * @param \PayU\EasyPlus\Model\RedirectPaymentMethod $paymentMethod
     * @return $this
     */
	public function setConstantData(
	    \PayU\EasyPlus\Model\RedirectPaymentMethod $paymentMethod,
        \Magento\Sales\Model\Order $order,
        $helper
    ) {
        $this->setData('Api', $paymentMethod->getApi()->getApiVersion())
            ->setData('Safekey', $paymentMethod->getApi()->getSafeKey())
            ->setData('TransactionType', $paymentMethod->getValue('payment_type'))
            ->setData('AdditionalInformation', array(
                'merchantReference'         => $order->getIncrementId(),
                'cancelUrl'                 => $helper->getCancelUrl(),
                'returnUrl'                 => $helper->getReturnUrl(),
                'supportedPaymentMethods'   => $paymentMethod->getConfigData('payment_methods'),
                'redirectChannel'           => $paymentMethod->getConfigData('redirect_channel'),
                'secure3d'                  => $paymentMethod->getConfigData('secure3d') ? 'True' : 'False'
            ));

        return $this;
	}

    /**
     * Set entity data to request
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \PayU\EasyPlus\Model\RedirectPaymentMethod $paymentMethod
     * @return $this
     */
	public function setDataFromOrder(
        \Magento\Sales\Model\Order $order,
        \PayU\EasyPlus\Model\RedirectPaymentMethod $paymentMethod
    ) {
	    $this->setData('Basket', array(
            'description' => sprintf('#%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),
            'amountInCents' => ($order->getBaseTotalDue() * 100),
            'currencyCode' => $paymentMethod->getValue('allowed_currency'))
        )
        ->setData('Customer', array(
            'merchantUserId' => $order->getCustomerId(),
            'email' => $order->getCustomerEmail(),
            'firstName' => $order->getCustomerFirstName(),
            'lastName' => $order->getCustomerLastName())
        );

        return $this;
    }
}