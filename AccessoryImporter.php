<?php
/**
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    Giancarlo Spadini <info@anewbattery.com>
*  @copyright 2007-2014 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class AccessoryImporter extends ProductImporter{
	
	protected $product; //row source	
	protected $image_basepath;
	
	public function SetProductSource(&$p){
		if(empty($p))
			throw new Exception("No Product Source");

		$this->product = $p;
	}
	
	public function setImageBasePath($path){
		if (Tools::substr($path, Tools::strlen($path) - 1) != '/') {
			$path .= '/';
		}
		$this->image_basepath = $path;
	}
	
	protected function GetPrice(){
		$price_limit = (bool)Configuration::get('life365_price_limit');
		
		$price_overhead = Db::getInstance()->getValue('
			SELECT profit
			FROM `'._DB_PREFIX_.'life365_category`
			WHERE id_category_external = '.(int)$this->product->local_category
		);
		$product_price = $this->product->price + ($this->product->price * $price_overhead / 100);

		if($product_price > $this->product->street_price && $price_limit)
			return (float)$this->product->street_price;
		else
			return (float)$product_price;
	}

	protected function GetWholesalePrice()
	{
		$product_price = $this->product->price;
		return (float)$product_price;
	}

	protected function GetWidth()
	{
		return (float)0;
	}
	
	protected function GetHeight()
	{
		return (float)0;
	}
	protected function GetDepth()
	{
		return (float)0;
	}
	
	protected function GetWeight()
	{
		return (float)($this->product->weight / 1000);
	}
	
	protected function GetShortDesciption()
	{
		$str_tmp = strip_tags($this->product->short_description);
		$str_tmp = str_replace("\r\n", "<br>", $str_tmp);

		return $this->product->short_description;
//		return $str_tmp;
	}
	
	protected function GetDesciption()
	{
		return (string)$this->product->description;
	}
	
	protected function GetMetaDescription()
	{
		$meta_description = (string)$this->product->meta_description;
		$meta_description = preg_replace('/[<>;=#{}]/ui',' ',$meta_description);

		$meta_description = (Tools::strlen($meta_description) > 255) ? Tools::substr($meta_description,0,255) : $meta_description;

		return $meta_description;
	}
	
	protected function GetEan13()
	{
		$ean13 = (string)$this->product->barcode;
		if ($ean13 == "0000000000000")
			$ean13 = null;

		return $ean13;

	}

	protected function GetReference()
	{
		return (string)$this->product->reference;
	}
	
	protected function GetSupplierReference()
	{
		return (string)$this->product->reference;
	}
	
	protected function GetMetaKeyword()
	{
		$meta_keywords = $this->product->meta_keywords;
		$meta_keywords = preg_replace('/[<>;=#{}]/ui',' ',$meta_keywords);

		return (string)Tools::substr($meta_keywords, 0, 255);
	}
	
	protected function GetMetaTitle()
	{
		$meta_title = (string)$this->product->meta_title;
		$meta_title = preg_replace('/[<>;=#{}]/ui',' ',$meta_title);

		return $meta_title;
	}
	
	protected function GetName()
	{
		$not_valid = array("#", "{", "}", "^", "<", ">", ";", "=");
		$name = str_replace($not_valid , '', (string)$this->product->name);
		if(empty($name))
			p((string)$this->product);
//			throw Exception("Accessory Import Exception : Blank Name");
		return $name;
	}
	
	protected function GetUnitPrice()
	{
		return (float) 0;
	}
	
	protected function GetManufacturerName()
	{
		return (string)$this->product->manufactuter;
	}
	
	protected function GetManufacturer()
	{
		return 0;

/*
		$res = Db::getInstance()->ExecuteS(
			"SELECT id_manufacturer AS id FROM "._DB_PREFIX_."manufacturer WHERE link_rewrite = '".(string)Tools::link_rewrite($this->GetManufacturerName())."'"
		);
		if(count($res)==0){
			if(empty($name))
			throw Exception("Accessory Import Exception : No Manufacturer");
		}
		return $res[0]['id'];
*/
	}
	
	protected function GetQuantity()
	{
		return (string)$this->product->quantity;
	}
	
	protected function GetImages()
	{
		$img_arr = array();
 		$url = $this->product->url_image;
		$img_arr[]= $url;
		return $img_arr;		
	}

	protected function GetTags()
	{
		$meta_keywords = $this->product->meta_keywords;
		$meta_keywords = preg_replace('/[!<;>;?=+#"°{}_$%]/ui',' ',$meta_keywords);

		$str_tags = str_replace(" ", ",", $meta_keywords);
		$tags = explode(",", $str_tags);
		$tags = array_merge(array_filter($tags));

		return (array)$tags;
	}
	
	protected function GetCategoryDefault()
	{
		$default_category = Db::getInstance()->getValue('
			SELECT id_category_ps
			FROM `'._DB_PREFIX_.'life365_category`
			WHERE id_category_external = '.(int)$this->product->local_category
		);
		if(empty($default_category))
			$default_category = Configuration::get('life365_default_category');

		return $default_category;
	}
	
	/**
	* This method checkc if current product already exist 
	* you can implement any logic to check if item already exist.
	* only thing is that if you want to add item return 0 otherwise id_product
	* Here I am checking in a import table and return id_product if it is exist
	* 
	*
	*
	*/
	protected function IfExist()
	{
		$id_product=0;
			
		//first check in mapping table
		$sql = 'SELECT id_product_ps FROM '._DB_PREFIX_.'life365_product WHERE id_product_external = '.$this->product->id;
		$res = Db::getInstance()->getRow($sql);
		if($res){
			$id_product = $res['id_product_ps'];
			if (!Product::existsInDatabase((int)($id_product), 'product'))
			{
				$t_sql = 'DELETE FROM '._DB_PREFIX_.'life365_product WHERE id_product_ps = '.(int)$id_product;
				Db::getInstance()->execute($t_sql);
				$id_product=0;
			}
		}

		return $id_product;
	}

	/**
		this method will be called after item is added to database and it's id_product is generated
	**/
	protected function AfterAdd()
	{
		if(!$this->IfExist())
		{
			$t_sql = 'INSERT INTO `'._DB_PREFIX_.'life365_product` (`id_product_external`, `date_import`, `id_product_ps`)';
			$t_sql .= ' VALUES ('.(int)$this->product->id.', CURRENT_TIMESTAMP, '.(int)$this->GetProductID().')';
			Db::getInstance()->execute($t_sql);
		}
		parent::AfterAdd();
	}
}