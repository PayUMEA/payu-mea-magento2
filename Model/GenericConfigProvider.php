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
use Magento\Payment\Helper\Data as PaymentHelper;
use PayU\EasyPlus\Helper\Data as EasyPlusHelper;

/**
 * Class CreditCardConfigProvider
 *
 * General payment method configuration provider
 */
class GenericConfigProvider implements ConfigProviderInterface
{
    const CODE = GenericRedirectPayment::CODE;

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

    /**
     * @param ResolverInterface $localeResolver
     * @param CurrentCustomer $currentCustomer
     * @param EasyPlusHelper $easyplusHelper
     * @param PaymentHelper $paymentHelper
     *
     * @throws LocalizedException
     */
    public function __construct(
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        EasyPlusHelper $easyplusHelper,
        PaymentHelper $paymentHelper
    ) {
        $this->localeResolver = $localeResolver;
        $this->currentCustomer = $currentCustomer;
        $this->easyplusHelper = $easyplusHelper;
        $this->paymentHelper = $paymentHelper;

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
                    'generic' => [
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
        return 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-%s.png';
    }
}
