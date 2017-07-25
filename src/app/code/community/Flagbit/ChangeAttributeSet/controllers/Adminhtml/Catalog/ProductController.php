<?php
/**
 * Magento ChangeAttributeSet
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @copyright Copyright (C) 2010-2015 Flagbit GmbH & Co. KG
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2.0
 */

/**
 * ChangeAttributeSet Controller
 */
class Flagbit_ChangeAttributeSet_Adminhtml_Catalog_ProductController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Product list page - change one or more products attribute set IDs
     */
    public function changeattributesetAction()
    {
        $productIds   = $this->getRequest()->getParam('product');
        $productIds   = array_map('intval', $productIds);
        $storeId      = (int)$this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID);
        $attributeSet = (int)$this->getRequest()->getParam('attribute_set');
        $deleteFlag   = Mage::getStoreConfigFlag('catalog/flagbit_changeattributeset/delete_old_data');

        if (!is_array($productIds)) {
            $this->_getSession()->addError($this->__('Please select product(s)'));
        } else {
            try {
                $attributesWithDefaultValue = $this->_getAttributesWithDefaultValue();
                $collection = Mage::getModel('catalog/product')->getCollection()
                    ->addAttributeToFilter('entity_id', array('in' => $productIds))
                    ->addAttributeToSelect($attributesWithDefaultValue)
                    ->addAttributeToSelect('url_key');

                foreach ($collection as $product) {
                    $this->_guardAgainstConfigurableAttributeNotInDestinationAttributeSet($product, $attributeSet);
                    $product->setAttributeSetId($attributeSet)->setStoreId($storeId);
                }
                $collection->save();

                if ($deleteFlag) {
                    $targetAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
                        ->setAttributeSetFilter($attributeSet)
                        ->getColumnValues('attribute_code');

                    $allAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
                        ->getColumnValues('attribute_code');

                    $attributesToDelete = array_diff($allAttributes, $targetAttributes);
                    if (sizeof($attributesToDelete)) {
                        $resource = Mage::getSingleton('core/resource');
                        $read = $resource->getConnection('core_read');
                        $write = $resource->getConnection('core_write');

                        $query = $read->select()
                            ->from($resource->getTableName('eav_attribute'), array('attribute_id'))
                            ->where('attribute_code IN (?)', $attributesToDelete);
                        $attributeIds = $read->fetchCol($query, 'attribute_id');

                        $condition = array(
                            $write->quoteInto('entity_id IN (?)', $productIds),
                            $write->quoteInto('attribute_id IN (?)', $attributeIds),
                        );

                        foreach ($this->getDeleteFromTables() as $table) {
                            $write->delete($resource->getTableName($table), $condition);
                        }
                    }
                }

                Mage::dispatchEvent('catalog_product_massupdate_after', array('products' => $productIds));
                $this->_getSession()->addSuccess(
                    $this->__('Total of %d record(s) were successfully updated', count($productIds))
                );
            } catch (Exception $e) {
                $this->_getSession()->addException($e, $e->getMessage());
            }
        }
        $this->_redirect('adminhtml/catalog_product/index/', array());
    }

    private function getDeleteFromTables()
    {
        return array(
            'catalog_product_entity_datetime',
            'catalog_product_entity_decimal',
            'catalog_product_entity_int',
            'catalog_product_entity_text',
            'catalog_product_entity_varchar',
        );
    }

    /**
     * Get product attributes with default values
     * @return array
     */
    private function _getAttributesWithDefaultValue()
    {
        $entityTypeId = Mage::getModel('eav/config')
            ->getEntityType(Mage_Catalog_Model_Product::ENTITY)
            ->getEntityTypeId();

        $attributes = Mage::getResourceModel('eav/entity_attribute_collection')
            ->addFieldToSelect('attribute_code')
            ->addFieldToFilter('entity_type_id', $entityTypeId)
            ->addFieldToFilter('default_value', array('neq' => ''))
            ->addFieldToFilter('default_value', array('notnull' => true))
            ->getColumnValues('attribute_code');

        return $attributes;
    }

    /**
     * Ensure that all of a configurable product's configurable attributes exist in the new attribute set before
     * changing to it.
     * @param  Mage_Catalog_Model_Product $product
     * @param  int                        $attributeSetId
     * @throws RuntimeException If one of the attributes isn't in the new set
     */
    private function _guardAgainstConfigurableAttributeNotInDestinationAttributeSet(Mage_Catalog_Model_Product $product, $attributeSetId)
    {
        $type = $product->getTypeInstance();
        if (!$type instanceof Mage_Catalog_Model_Product_Type_Configurable) {
            return;
        }

        foreach ($type->getConfigurableAttributes($product) as $configurableAttribute) {
            $attribute = Mage::getModel('eav/entity_attribute')->load($configurableAttribute->getAttributeId());
            if ($this->_isAttributeInAttributeSet($attribute, $attributeSetId)) {
                throw new RuntimeException(
                    $this->__(
                        'The configurable attribute "%s" on "%s" is not available in the targeted attribute set. Please create it first!',
                        $attribute->getFrontendLabel(),
                        $product->getSku()
                    )
                );
            }
        }
    }

    /**
     * Check if an attribute is in an attribute set
     * @param  Mage_Eav_Model_Entity_Attribute $attribute
     * @param  int                             $attributeSetId
     * @return bool
     */
    private function _isAttributeInAttributeSet(Mage_Eav_Model_Entity_Attribute $attribute, $attributeSetId)
    {
        $attributesMatchingInNewAttributeSet = $attribute->getResourceCollection()
            ->setAttributeSetFilter($attributeSetId)
            ->addFieldToFilter('entity_attribute.attribute_id', $attribute->getId())
            ->load();

        return (count($attributesMatchingInNewAttributeSet) === 0);
    }

    /**
     * Check admin permissions for this controller.
     * This allows a user to change the attribute set if they are allowed to edit products.
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/products/flagbit_changeattributeset');
    }
}
