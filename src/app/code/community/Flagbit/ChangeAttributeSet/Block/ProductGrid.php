<?php
/*                                                                        *
 * This script is part of the TypoGento project 						  *
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
 * ChangeAttributeSet ProductGrid Block
 *
 * @version $Id$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class Flagbit_ChangeAttributeSet_Block_ProductGrid
	extends Mage_Adminhtml_Block_Catalog_Product_Grid
{
	/**
	 * Class constructor
	 * 
	 * Calls the parent constructor
	 */
	public function __construct() {
		parent::__construct ();
	}
	
	/**
	 * Prepares massaction
	 *
	 * @return Flagbit_ChangeAttributeSet_Block_ProductGrid
	 */
	protected function _prepareMassaction()
	{
		parent::_prepareMassaction();
		$statuses = Mage::getSingleton('catalog/product_status')->getOptionArray();
		
		$sets = Mage::getResourceModel('eav/entity_attribute_set_collection')
			->setEntityTypeFilter(Mage::getModel('catalog/product')->getResource()->getTypeId())
			->load()
			->toOptionHash();
		
		array_unshift($statuses, array('label'=>'', 'value'=>''));
		
		$this->getMassactionBlock()->addItem('attribute_set', array(
			'label'=> Mage::helper('catalog')->__('Change attribute set'),
			'url'  => $this->getUrl('*/*/changeattributeset', array('_current'=>true)),
			'additional' => array(
				'visibility' => array(
					'name' => 'attribute_set',
					'type' => 'select',
					'class' => 'required-entry',
					'label' => Mage::helper('catalog')->__('Attribute Set'),
					'values' => $sets
				)
			)
		)); 
		
		return $this;
	}
}
