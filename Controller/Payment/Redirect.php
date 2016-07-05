<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayU\EasyPlus\Controller\Payment;

use PayU\EasyPlus\Controller\AbstractAction;
use Magento\Framework\Controller\ResultFactory;

class Redirect extends AbstractAction
{
    /**
     * Config mode type
     *
     * @var string
     */
    protected $_configType = 'PayU\EasyPlus\Model\ConfigProvider';

    /**
     * Config method type
     *
     * @var string
     */
    protected $_configMethod = \PayU\EasyPlus\Model\ConfigProvider::CODE;

    /**
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {    
            $url = $this->_checkoutSession->getCheckoutRedirectUrl();
            if($url) {
                return $resultRedirect->setPath($url);
            } else {
                $this->messageManager->addErrorMessage(
                    __('Unable to redirect to PayU. Checkout has been canceled.')
                );
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to redirect to PayU. Server error encountered'));
        }

        return $resultRedirect->setPath('checkout/cart');
    }
}
