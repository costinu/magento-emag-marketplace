<?php

namespace Zitec\EmagMarketplace\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;

/**
 * Option labels of select/multiselect attributes as sent to eMAG (HTML-escaped).
 *
 * Replaces the former global preference for Magento\Eav\Model\Entity\Attribute\Source\Table, which escaped
 * option labels for the whole store (double escaping in e-mails, PDFs and third-party modules).
 */
class AttributeText
{
    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param Escaper $escaper
     */
    public function __construct(Escaper $escaper)
    {
        $this->escaper = $escaper;
    }

    /**
     * @param Product $product
     * @param string $attributeCode
     * @return string|string[]|false
     */
    public function get(Product $product, string $attributeCode)
    {
        $text = $product->getAttributeText($attributeCode);

        if (is_array($text)) {
            return array_map(fn ($label) => $this->escaper->escapeHtml((string) $label), $text);
        }

        if ($text === false || $text === null) {
            return false;
        }

        return $this->escaper->escapeHtml((string) $text);
    }
}
