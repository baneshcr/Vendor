<?php

namespace Vendor\Module\Block;

use Magento\Framework\View\Element\Template;
use Vendor\Module\Helper\Data as MultiShippingHelper;

class ShippingAddress extends Template
{
    protected $multiShippingHelper;

    public function __construct(
        Template\Context $context,
        MultiShippingHelper $multiShippingHelper,
        array $data = []
    ) {
        $this->multiShippingHelper = $multiShippingHelper;
        parent::__construct($context, $data);
    }

    public function isEnabled()
    {
        return $this->multiShippingHelper->isEnabled();
    }
}
