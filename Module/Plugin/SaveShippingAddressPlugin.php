<?php

namespace Vendor\Module\Plugin;

use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory as CustomerAddressFacotry;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\Address\ItemFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteFactory;
use Vendor\Module\Helper\Data as Helper;

class SaveShippingAddressPlugin
{   
    protected $customerAddressFactory;
    protected $addressRepository;
    protected $customerRepository;
    protected $quoteRepository;
    protected $quoteFactory;
    protected $quoteAddressFactory;
    protected $quoteAddressItemFactory;
    protected $regionFactory;
    protected $searchCriteriaBuilder;
    protected $customerSession;
    protected $checkoutSession;
    protected $helper;

    const XML_PATH_ENABLE_MULTISHIPPING = 'custom_multishipping_section/general/enable_multishipping';

    public function __construct(
        CustomerAddressFacotry $customerAddressFactory,
        AddressRepositoryInterface $addressRepository,
        CustomerRepositoryInterface $customerRepository,
        CartRepositoryInterface $quoteRepository,
        AddressFactory $quoteAddressFactory,
        ItemFactory $quoteAddressItemFactory,
        RegionInterfaceFactory $regionInterfaceFactory,
        QuoteFactory $quoteFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Customer\Model\Session $customerSession,
        CheckoutSession $checkoutSession,
        Helper $helper
    ) {
        $this->customerAddressFactory = $customerAddressFactory;
        $this->addressRepository = $addressRepository;
        $this->customerRepository = $customerRepository;
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->quoteAddressFactory = $quoteAddressFactory;
        $this->quoteAddressItemFactory = $quoteAddressItemFactory;
        $this->regionFactory = $regionInterfaceFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    public function afterAddProduct(
        Cart $subject,
        $result,
        $productInfo,
        $requestInfo = null
    ) {
        // Check if the module is enabled in configuration
        if (!$this->helper->isEnabled()) {
            return $result;
        }

        // Check if the product is a configurable product
        $productType = $productInfo->getTypeId();
        if ($productType !== 'configurable') {
            return $result;  // Only execute logic for configurable products
        }


        // Load the customer
        $customer = $this->customerSession->getCustomer();
        $customer_id = $customer->getId();
        $customerAddressId ="";

        if (isset($requestInfo['shipping_address_data']) && $requestInfo['selectedSAddress']=="new") {
            
            $customerAddress = $this->saveNewShippingAddress($customer_id, $requestInfo);
            $customerAddressId = $customerAddress->getId();
        }
        else if(is_numeric($requestInfo['selectedSAddress'])){
            $customerAddressId = $requestInfo['selectedSAddress'];
        }

        if ($customerAddressId) {

            $quoteId = $subject->getQuote()->getId();
            $quote = $this->quoteFactory->create();
            $quote->load($quoteId);
            if(!$quote->getIsMultiShipping()){
                $quote->setIsMultiShipping(true); // Enable multi-shipping for the quote
                $quote->save(); // Save the updated quote
            }
            // Set multi-shipping true in the checkout session
            $this->checkoutSession->getQuote()->setIsMultiShipping(true); 
            $this->checkoutSession->setIsMultiShipping(true); 
            $this->checkoutSession->getQuote()->save(); 

            $quoteAddressId = $this->addOrUpdateAddressInQuote($subject->getQuote()->getId(), $customerAddressId, $productInfo);
        }
        return $result;
    }

    protected function saveNewShippingAddress($customer_id, $shippingAddressData)
    {
        $savedShippingAddress = $this->getMatchingShippingData($customer_id, $shippingAddressData);
        if($savedShippingAddress){
            return $savedShippingAddress;
        }

        try {
            $regionData = $this->regionFactory->create();
            if(isset($shippingAddressData['region_id'])){
                $regionData->setRegionId($shippingAddressData['region_id']);
            }
            else if(isset($shippingAddressData['region'])){
                $regionData->setRegion($shippingAddressData['region']);
            }
            
            // Create a new customer address
            $address = $this->customerAddressFactory->create();
            $address->setCustomerId($customer_id);
            $address->setFirstname($shippingAddressData['firstname']);
            $address->setLastname($shippingAddressData['lastname']);
            $address->setStreet([$shippingAddressData['street']]);
            $address->setCity($shippingAddressData['city']);
            $address->setRegionId($regionData->getRegionId());
            $address->setRegion($regionData->getRegion());
            $address->setCountryId($shippingAddressData['country_id']);
            $address->setPostcode($shippingAddressData['postcode']);
            $address->setTelephone($shippingAddressData['telephone']);
            $address->setIsDefaultShipping(true);
            // Save the address
            $savedAddress = $this->addressRepository->save($address);
            return $savedAddress; 
        } catch (LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error saving shipping address: ' . $e->getMessage())
            );
        }
    }

    protected function getMatchingShippingData($customer_id, $shippingAddressData)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('parent_id',$customer_id)->create();
        $customer_addresses = $this->addressRepository->getList($searchCriteria);

        // Check if an existing shipping address matches the current one
        foreach ($customer_addresses as $existingAddress) {
            if ($this->isAddressMatching($existingAddress, $shippingAddressData)) {
                return $existingAddress; // Return the existing matching address
            }
        }
        return false;
    }

    /**
     * Compare the provided address data with an existing quote_address.
     */
    protected function isAddressMatching($existingAddress, $shippingAddressData)
    {
        return $existingAddress->getFirstname() == $shippingAddressData['firstname'] &&
            $existingAddress->getLastname() == $shippingAddressData['lastname'] &&
            $existingAddress->getStreet() == $shippingAddressData['street'] &&
            $existingAddress->getCity() == $shippingAddressData['city'] &&
            $existingAddress->getRegion() == $shippingAddressData['region'] &&
            $existingAddress->getPostcode() == $shippingAddressData['postcode'] &&
            $existingAddress->getCountryId() == $shippingAddressData['country_id'] &&
            $existingAddress->getTelephone() == $shippingAddressData['telephone'];
    }


    protected function addOrUpdateAddressInQuote($quoteId, $customerAddressId, $productInfo)
    {
        try {
            $quoteAddressItemExists = $this->checkExistingQuoteAddress($quoteId, $customerAddressId);
            
            if (!$quoteAddressItemExists) {
                // If no matching quote_address_item exists, create a new one
                $quoteAddressId = $this->addAddressToQuote($quoteId, $customerAddressId);
                $this->addItemToQuoteAddressItem($quoteId, $quoteAddressId, $productInfo);
            } else {
                // If it exists, update the totals for the item
                $this->updateItemTotalsInQuoteAddressItem($quoteId, $customerAddressId, $productInfo);
            }


            return true;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error adding or updating address in quote: ' . $e->getMessage())
            );
        }
    }

    protected function checkExistingQuoteAddress($quoteId, $customerAddressId)
    {
        // Query the quote_address_item table to check if an entry exists with this quote_id and customer_address_id
        $quote = $this->quoteRepository->get($quoteId);
        foreach ($quote->getAllAddresses() as $address) {
            if ($address->getCustomerAddressId() == $customerAddressId) {
                foreach ($address->getAllItems() as $item) {
                    return true; // Return true if an item is found with this address
                }
            }
        }
        return false;
    }


    /**
     * Add the saved address to the quote_address table.
     */
    protected function addAddressToQuote($quoteId, $customerAddressId)
    {
        try {
            // Load the quote and create a new quote address
            $quote = $this->quoteRepository->get($quoteId);
            $customerAddress = $this->addressRepository->getById($customerAddressId);
            $customer = $this->customerSession->getCustomer();
            $quoteShippingAddress = $this->quoteAddressFactory->create();
            
            // Get the customer's email from the customer entity
            $customerEmail = $customer->getEmail();

            $regionData = $this->regionFactory->create();
            if($customerAddress->getRegionId()){
                $regionData->setRegionId($customerAddress->getRegionId());
            }
            else if($customerAddress->getRegion()){
                $regionData->setRegion($customerAddress->getRegion());
            }
 
            // Set data in the quote_address table
            $quoteShippingAddress->setQuoteId($quoteId);
            $quoteShippingAddress->setCustomerAddressId($customerAddress->getId());  // Set the saved customer address ID
            $quoteShippingAddress->setCustomerId($customer->getId());
            $quoteShippingAddress->setEmail($customerEmail);  // Set customer email
            $quoteShippingAddress->setFirstname($customerAddress->getFirstname());
            $quoteShippingAddress->setLastname($customerAddress->getLastname());
            $quoteShippingAddress->setStreet($customerAddress->getStreet());
            $quoteShippingAddress->setCity($customerAddress->getCity());

            if($regionData->getRegionId()){
                $regionData->setRegionId($customerAddress->getRegionId());
            }
            else if($regionData->getRegion()){
                $regionData->setRegion($customerAddress->getRegion());
            }
            $quoteShippingAddress->setCountryId($customerAddress->getCountryId());
            $quoteShippingAddress->setPostcode($customerAddress->getPostcode());
            $quoteShippingAddress->setTelephone($customerAddress->getTelephone());
            $quoteShippingAddress->setAddressType(\Magento\Quote\Model\Quote\Address::ADDRESS_TYPE_SHIPPING);
            
            // Save the shipping address in the quote_address table
            $quoteShippingAddress->save();
            
            // Save the updated quote with the new shipping address
            $this->quoteRepository->save($quote);

            return $quoteShippingAddress->getId(); 
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error adding address to quote: ' . $e->getMessage())
            );
        }
    }

    protected function addItemToQuoteAddressItem($quoteId, $quoteAddressId, $productInfo)
    {
        try {
            $quote = $this->quoteRepository->get($quoteId);
            $quoteItem = $quote->getItemByProduct($productInfo); // Find the quote item by product
            
            if (!$quoteItem) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('The product could not be found in the quote.')
                );
            }
            
            $quoteAddressItem = $this->quoteAddressItemFactory->create();
            $quoteAddressItem->setQuoteAddressId($quoteAddressId);
            $quoteAddressItem->setQuoteItemId($quoteItem->getItemId()); // Link the product to the address
            $quoteAddressItem->setQty($quoteItem->getQty()); // Set the quantity of the item
            $quoteAddressItem->save(); // Save the entry in the quote_address_item table

            return true;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error adding item to quote_address_item: ' . $e->getMessage())
            );
        }
    }


    protected function updateItemTotalsInQuoteAddressItem($quoteId, $customerAddressId, $productInfo)
    {
        try {
            $quote = $this->quoteRepository->get($quoteId);
            $quoteItem = $quote->getItemByProduct($productInfo); // Find the quote item by product

            if (!$quoteItem) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('The product could not be found in the quote.')
                );
            }

            foreach ($quote->getAllAddresses() as $address) {
                if ($address->getCustomerAddressId() == $customerAddressId) {
                    foreach ($address->getAllItems() as $item) {
                        if ($item->getQuoteItemId() == $quoteItem->getItemId()) {
                            $item->setQty($quoteItem->getQty()); // Update the quantity and totals
                            $item->save(); // Save the updated entry in quote_address_item
                        }
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Error updating item totals in quote_address_item: ' . $e->getMessage())
            );
        }
    }
}
