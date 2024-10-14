<?php

namespace Vendor\Module\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Checkout\Model\Session as CheckoutSession;
use Vendor\Module\Helper\Data as Helper;

class CustomMultishippingObserver implements ObserverInterface
{
    protected $checkoutSession;
    protected $scopeConfig;
    protected $helper;

    const XML_PATH_ENABLE_MULTISHIPPING = 'custom_multishipping_section/general/enable_multishipping';

    public function __construct(
        CheckoutSession $checkoutSession,
        Helper $helper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->helper->isEnabled()()) {
            return false;
        }

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->checkoutSession->getQuote();

        // Ensure multi-shipping is enabled
        $quote->setIsMultiShipping(true);
        $this->checkoutSession->setIsMultiShipping(true);

        // Set the "collect shipping rates" flag for each shipping address
        foreach ($quote->getAllShippingAddresses() as $address) {
            $address->setCollectShippingRates(true);
        }

        // Collect totals for the multi-shipping quote
        $quote->collectTotals();
        $quote->save();  // Ensure the updated quote is saved

        return true;
    }
}
