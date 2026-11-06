# magento-email-marketplace

This module is the original Emag Marketplace developed by Zitec, updated to support latest version of Magento 2 and PHP.

Change Log:

1.0.4 - PHP 8.4 / Magento 2.4.9 compatibility:

- Explicit nullable types for parameters defaulting to null (implicit nullable is deprecated in PHP 8.4).
- Console commands: `configure(): void` and `execute(): int` as required by Symfony Console 6/7.
- `Zend_Validate` (removed from Magento 2.4.6+) replaced with `filter_var` in the alert e-mail backend model.
- Non-existent `\LocalizedException` / `Braintree\Exception` replaced with `Magento\Framework\Exception\LocalizedException`
  (the order import and the product validation observer failed with "class not found").
- Declared dynamic properties (deprecated since PHP 8.2), `str_replace()` with null, missing return values,
  undefined variables in `AlertManager` and `ActiveShippingMethods`, dead code in the localities search controller.
- composer.json: PHP 8.1 - 8.4, Magento 2.4.4+ package constraints.

14/03/2026 - Fixed the PHP 8 deprecation caused by an optional parameter appearing before required parameters:

CategoryMappingRepositoryInterface.php (line 30)
CategoryMappingRepository.php (line 92)
MappingManager.php (line 95)
Save.php (line 76)

What changed:

Reordered save() method signatures so required params come first and optional $id comes last.
Updated the internal call chain to use the new argument order.
Normalized the controller’s request id handling so new records pass null instead of 0.
Tightened the repository check from truthy $id to explicit $id !== null so update/create behavior stays correct.
Update composer.json and module.xml to version 1.0.3
