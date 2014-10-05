<?php

/**
 * This is the model class for table "{{cart_shipping}}".
 *
 * @package application.models
 * @name CartShipping
 *
 */
class CartShipping extends BaseCartShipping
{
	/**
	 * Returns the static model of the specified AR class.
	 * @return CartShipping the static model class
	 */
	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function getShippingSell()
	{
		return $this->shipping_sell;
	}

	public function getIsStorePickup()
	{
		if (isset($this->shipping_module))
		{
			return Yii::app()->getComponent($this->shipping_module)->IsStorePickup;
		}

		return false;
	}

}
