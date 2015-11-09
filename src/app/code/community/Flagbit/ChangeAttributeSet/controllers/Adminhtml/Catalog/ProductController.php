<?php
/*                                                                        *
 * This script is part of the ChangeAttributeSet project        		  *
 *                                                                        *
 * TypoGento is free software; you can redistribute it and/or modify it   *
 * under the terms of the GNU General Public License version 2 as         *
 * published by the Free Software Foundation.                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

/**
 * ChangeAttributeSet Controller
 *
 * @version $Id$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class Flagbit_ChangeAttributeSet_Adminhtml_Catalog_ProductController extends Mage_Adminhtml_Controller_Action
{
    protected $_deleteAttributes = array();
    
    /**
     * Product list page - change one or more products attribute set IDs
     */
    public function changeattributesetAction()
    {
        $productIds   = $this->getRequest()->getParam('product');
        $productIds   = array_map('intval', $productIds);
        $storeId      = (int)$this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID);
        $attributeSet = (int)$this->getRequest()->getParam('attribute_set');
        $_deleteFlag  = Mage::getStoreConfigFlag('catalog/flagbit_changeattributeset/delete_old_data');

        if (!is_array($productIds)) {
            $this->_getSession()->addError($this->__('Please select product(s)'));
        } else {
            try {
                $collection = Mage::getModel('catalog/product')->getCollection()
                    ->addAttributeToFilter('entity_id', array('in' => $productIds))
                    ->addAttributeToSelect('url_key');

                if ($_deleteFlag) {
                    $targetAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
                        ->setAttributeSetFilter($attributeSet)
                        ->getColumnValues('attribute_code');

                    $allAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
                        ->getColumnValues('attribute_code');

                    if ($diffAttributes = array_diff($allAttributes, $targetAttributes)) {
                        $this->_deleteAttributes = $diffAttributes;
                    } else {
                        $_deleteFlag = false;
                    }
                }

                foreach ($collection as $product) {
                    $this->_guardAgainstConfigurableAttributeNotInDestinationAttributeSet($product, $attributeSet);
                    $product->setAttributeSetId($attributeSet)->setStoreId($storeId);
                }
                $collection->save();

                if ($_deleteFlag) {
                    $resource = Mage::getSingleton('core/resource');
                    $read = $resource->getConnection('core_read');
                    $write = $resource->getConnection('core_write');

                    $query = $read->select()
                        ->from($resource->getTableName('eav_attribute'), array('attribute_id'))
                        ->where('attribute_code IN (?)', $this->_deleteAttributes);
                    $attributeIds = $read->fetchCol($query, 'attribute_id');

                    foreach ($this->getDeleteFromTables() as $table) {
                        $condition = array(
                            $write->quoteInto('entity_id IN (?)', $productIds),
                            $write->quoteInto('attribute_id IN (?)', $attributeIds)
                        );
                        $write->delete($resource->getTableName($table), $condition);
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
            'catalog_product_entity_varchar'
        );
    }

    /**
     * Ensure that all of a configurable product's configurable attributes exist in the new attribute set before
     * changing to it.
     * @param  Mage_Catalog_Model_Product $product
     * @param  int                        $attributeSetId
     * @return void
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
     * @return boolean
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
     * @return boolean
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/products/flagbit_changeattributeset');
    }
}
