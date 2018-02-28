<?php
/**
 * PayU_EasyPlus payment config provider
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use PayU\EasyPlus\Helper\Data as EasyPlusHelper;

/**
 * Class CreditCardConfig
 *
 * General payment method configuration provider
 */
class CreditCardConfig implements ConfigProviderInterface
{
    const CODE = CreditCard::CODE;

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var EasyPlusHelper
     */
    protected $easyplusHelper;

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod
     */
    protected $method;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    protected $assetRepo;

    /**
     * @param ResolverInterface $localeResolver
     * @param CurrentCustomer $currentCustomer
     * @param EasyPlusHelper $easyplusHelper
     * @param PaymentHelper $paymentHelper
     * @param Repository $assetRepo
     *
     * @throws LocalizedException
     */
    public function __construct(
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        EasyPlusHelper $easyplusHelper,
        PaymentHelper $paymentHelper,
        Repository $assetRepo
    ) {
        $this->localeResolver = $localeResolver;
        $this->currentCustomer = $currentCustomer;
        $this->easyplusHelper = $easyplusHelper;
        $this->paymentHelper = $paymentHelper;
        $this->assetRepo      = $assetRepo;

        $this->method = $this->paymentHelper->getMethodInstance(self::CODE);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        if ($this->method->isAvailable()) {
            $config = [
                'payment' => [
                    'creditCard' => [
                        'imageSrc' => $this->getPaymentMethodImageUrl(),
                        'redirectUrl' => $this->getMethodRedirectUrl()
                    ]
                ]
            ];
        }

        return $config;
    }

    /**
     * Return redirect URL for method
     *
     * @return mixed
     */
    protected function getMethodRedirectUrl()
    {
        return $this->method->getCheckoutRedirectUrl();
    }

    /**
     * Get PayU "mark" image URL
     * Supposed to be used on payment methods selection
     *
     * @return string
     */
    public function getPaymentMethodImageUrl()
    {
        return $this->assetRepo->getUrl( 'PayU_EasyPlus::images/creditcard.png');
    }
}
