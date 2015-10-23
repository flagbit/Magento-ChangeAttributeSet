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
 * ChangeAttributeSet Observer Model
 *
 * @version $Id: ProductController.php 282 2010-04-27 14:42:36Z fuhr $
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class Flagbit_ChangeAttributeSet_Model_Observer
{
    /**
     * Add massAction option to Productgrid
     *
     * @param  Varien_Event_Observer $observer
     * @return self
     */
    public function addMassactionToProductGrid($observer)
    {
        if (!$this->_isAllowedAction()) {
            return $this;
        }
        
        $block = $observer->getBlock();
        if ($block instanceof Mage_Adminhtml_Block_Catalog_Product_Grid) {
            $sets = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter(Mage::getModel('catalog/product')->getResource()->getTypeId())
                ->load()
                ->toOptionHash();

            $block->getMassactionBlock()->addItem(
                'flagbit_changeattributeset',
                array(
                    'label'      => Mage::helper('catalog')->__('Change attribute set'),
                    'url'        => $block->getUrl('*/*/changeattributeset', array('_current' => true)),
                    'additional' => array(
                        'visibility' => array(
                            'name'   => 'attribute_set',
                            'type'   => 'select',
                            'class'  => 'required-entry',
                            'label'  => Mage::helper('catalog')->__('Attribute Set'),
                            'values' => $sets
                        )
                    )
                )
            );
        }
        return $this;
    }

    /**
     * Check admin permissions for this action.
     * This allows a user to change the attribute set if they are allowed to edit products.
     */
    protected function _isAllowedAction()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/products/flagbit_changeattributeset');
    }
}
