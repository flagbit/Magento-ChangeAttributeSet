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
	
	/**
	 * Product list page
	 */
	public function changeattributesetAction()
	{
		$productIds = $this->getRequest()->getParam('product');
		$storeId = (int)$this->getRequest()->getParam('store', 0);
		if (!is_array($productIds)) {
			$this->_getSession()->addError($this->__('Please select product(s)'));
		}
		else {
			try {
				foreach ($productIds as $productId) {
					$product = Mage::getSingleton('catalog/product')
						->unsetData()
						->setStoreId($storeId)
						->load($productId)
						->setAttributeSetId($this->getRequest()->getParam('attribute_set'))
						->setIsMassupdate(true)
						->save();
				}
				Mage::dispatchEvent('catalog_product_massupdate_after', array('products'=>$productIds));
				$this->_getSession()->addSuccess(
					$this->__('Total of %d record(s) were successfully updated', count($productIds))
				);
			}
			catch (Exception $e) {
				$this->_getSession()->addException($e, $e->getMessage());
			}
		}
		$this->_redirect('adminhtml/catalog_product/index/', array());
	}	
}
