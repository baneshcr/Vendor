<?php

namespace Vendor\Module\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLE_MULTISHIPPING = 'custom_multishipping_section/general/enable_multishipping';

    /**
     * Check if the custom multi-shipping is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_MULTISHIPPING, ScopeInterface::SCOPE_STORE);
    }
}
