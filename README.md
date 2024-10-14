# Custom MultiShipping



Firstly clone the repo into the app/code/ folder

Once clone is completed, you have to do setup upgradation, compilation, static content deployment and need to clear the cache. Just after that, ensure the module is enabled in the configuration section of the Magento 2 admin dashboard. There you can see the menu option, Store -> Configuration -> Sales -> 'Custom MultiShipping Configuration'.

With Custom Multishipping, you can provide shipping address for configurable products. Just after selecting the configurable options, visitor can see the button 'Proceed to Shipment'. Visitor needs to be logged-in to see the dropdown options for either selecting existing shipping address or creating 'New Address'.

If the customer select 'New Address', he can see the form for entering shipping address. Once all the form details are entered, customer can click the 'Add to cart' button. After successful validation, shipping address will be saved to the customer_address_entity table. As the product is added to the cart, the customer address details will be added to quote_address table and the item will be added to the quote_address_item table.

Duplication check for customer address is done, and in that case the existing record's entity_id used for further proceedings.

Similarly, as the customer select the existing address fromt the dropdown, the corresponding entity_id will be used as in the ealier case.

The 'Proceed to Shipment' button remains disabled, till all the configurable options are selected. Hiding the 'Add to cart' button is done as this creates confusion in the minds of customers as this is not a default feature in Magento. Rather, on clicking the button, it always validates the form before proceeding.


