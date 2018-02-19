<?php
/**
 * PayU_EasyPlus payment response validation controller
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Controller\Payment;

use PayU\EasyPlus\Controller\AbstractAction;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class Response extends AbstractAction
{
    /**
     * Retrieve transaction information and validates payment
     *
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $result = '';
        try {
            $payu = $this->_initPayUReference();
          
            // if there is an order - load it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();
            /** @var \Magento\Sales\Model\Order $order */
            $order = $orderId ? $this->_orderFactory->create()->load($orderId) : false;
            // TODO timeout
            if($payu && $order) {
                $this->response->setData('params', $payu);

                $result = $this->response->processReturn($order);

                if($result !== true) {
                    $this->messageManager->addErrorMessage(
                        __($result)
                    );
                } else {

                    $this->_checkoutSession
                        ->setLastQuoteId($order->getQuoteId())
                        ->setLastSuccessQuoteId($order->getQuoteId());

                    $this->_checkoutSession->setLastOrderId($order->getId())
                        ->setLastRealOrderId($order->getIncrementId())
                        ->setLastOrderStatus($order->getStatus());

                    $this->messageManager->addSuccessMessage(
                        __('Payment was successful and we received your order with much fanfare')
                    );

                    $this->clearSessionData();

                    return $this->_redirect('checkout/onepage/success');
                }
            } 
        } catch (LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to validate order'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to validate order'));
        }

        $this->_returnCustomerQuote(true, $result);

        return $resultRedirect->setPath('checkout/cart');
    }
}
