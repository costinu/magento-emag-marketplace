# magento-email-marketplace

This module is the original Emag Marketplace developed by Zitec, updated to support latest version of Magento 2 and PHP.

Change Log:

1.2.0 - Order polling: before each import the new eMAG orders (status 1) are read and the ones the callback
missed are queued. Order Settings > Check New Orders in eMAG (default Yes).

1.1.0 - Security hardening:

- The order callback needs a secret key: `<base url>emagmarketplace/order/ping/key/<key>/` (404 otherwise).
  The key is generated on install / upgrade; copy the URL from Stores > Configuration > eMAG Marketplace >
  Order Settings > Order Callback URL into eMAG (Profile > Technical details). **After upgrading, update the
  callback URL in eMAG**, the old one no longer works.
- Order import: batch per cron run, each queue item saved separately, queue limit for the callback, the queue no
  longer stores the full eMAG response (customer data). Alert e-mails limited per hour and escaped. API timeouts
  10 s / 120 s instead of 600 s.
- AWB PDFs are kept in `var/zitec_emkp/awb/` and downloaded through the admin (they were public under
  `pub/media/awb/` with sequential names; the old files are deleted). Invoices get random file names
  (`pub/media/zitec_emkp/invoices/`) and must be real PDFs. Invoices uploaded before keep their old public URLs.
- The API password is stored encrypted (existing values are encrypted on upgrade); API username, password and
  callback key are sensitive config values.
- API request log is off by default; when enabled, customer data is masked and logs older than the retention
  (default 14 days) are deleted.
- No more global preference for `Magento\Eav\Model\Entity\Attribute\Source\Table` (it HTML-escaped every
  option label in the store).
- ACL resources for AWBs, invoices and localities; the config ACL resources are declared; escaped admin output.

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
