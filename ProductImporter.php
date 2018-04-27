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

abstract class productImporter{
	private $id_product; //product id in prestashop
	private $object; //object of type Product
	private $module_name='life365';
	
	private $unfriendly_error=true;

	public function __construct(){

	}
	
	public function disable(){
		$this->id_product = $this->IfExist();

		if(!$this->id_product) {
			return 0;
		} else if((int)$this->id_product AND Product::existsInDatabase((int)($this->id_product), 'product')) {
			$this->object = new Product((int)($this->id_product));
		} else {
			// if the subclass returned something other than false
			// skip;
			return 0;
		}
		
		StockAvailable::setQuantity($this->GetProductID(), null, 0, null);
		
		$this->object->active = false;
		$this->object->update();
	}
	
	
	public function Save(){
		$this->id_product = $this->IfExist();

		if(!$this->id_product) {
			$this->object = self::createAndInitializeNewObject();
		} else if((int)$this->id_product AND Product::existsInDatabase((int)($this->id_product), 'product')) {
			$this->object = new Product((int)($this->id_product));
		} else {
			// if the subclass returned something other than false
			// skip;
			return 0;
		}
		$this->AddUpdated();
	}
	
	protected function AddUpdated(){
		$id_lang = (int)(Configuration::get('PS_LANG_DEFAULT'));
		$sync_name = (bool)Configuration::get($this->module_name . '_sync_name');
		$sync_short_desc = (bool)Configuration::get($this->module_name . '_sync_short_desc');
		$sync_desc = (bool)Configuration::get($this->module_name . '_sync_desc');
		$sync_category = (bool)Configuration::get($this->module_name . '_sync_category');
		$sync_price = (bool)Configuration::get($this->module_name . '_sync_price');

		$this->object->id_manufacturer = $this->GetManufacturer();
		$this->object->reference = $this->GetReference();
		$this->object->supplier_reference = $this->GetSupplierReference();
		$this->object->id_tax_rules_group = $this->GetTax_Rules_Group();
		$this->object->unity = $this->GetUnity();
		$this->object->additional_shipping_cost= self::CleanAmount($this->GetAdditionalShippingCost());
		$this->object->width= self::CleanAmount($this->GetWidth());
		$this->object->height=self::CleanAmount($this->GetHeight());
		$this->object->depth=self::CleanAmount($this->GetDepth());
		$this->object->weight=self::CleanAmount($this->GetWeight());
		$this->object->out_of_stock = $this->GetOutOfStock();
		$this->object->condition = $this->GetCondition();
		$this->object->minimal_quantity = $this->GetMinimalQuantity();
		$this->object->id_supplier = $this->GetSupplierId();
		$this->object->id_color_default = $this->GetDefualtColorId();
		$this->object->id_color[] = $this->object->id_color_default;
//		$this->object->on_sale = $this->GetOnSale();
		$this->object->ean13 = $this->GetEan13();
		$this->object->upc = $this->GetUpc();
		$this->object->ecotax = self::CleanAmount($this->GetEcoTax());

		/** either add or edit ***/
		if($this->object->id) {
			if($sync_name) {
				$name = $this->GetName();
				$this->object->name[$id_lang] = $name;
				$link_rewrite = self::Generate_Slug($name);
				$this->object->link_rewrite[$id_lang] = $link_rewrite;
				$this->object->meta_title[$id_lang] = $this->GetMetaTitle();
			}
			if($sync_desc) {
				$this->object->description[$id_lang] = $this->GetDesciption();
				$this->object->meta_description[$id_lang] = $this->GetMetaDescription();
			}
			if($sync_short_desc) {
				$description_short_limit = (int)Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
				if ($description_short_limit <= 0)
					$description_short_limit = 800;
				$this->object->description_short[$id_lang] = self::cropString($this->GetShortDesciption(), $description_short_limit);
				$this->object->meta_keywords[$id_lang] = $this->GetMetaKeyword();
				$tags = $this->GetTags();
				$this->AddTags($tags);
			}
			if($sync_category) {
				$this->object->id_category_default = $this->GetCategoryDefault();
				$parent_categories = (bool)Configuration::get($this->module_name . '_parent_categories');
				if ($parent_categories)
					$this->object->id_category[] = $this->GetCategoryTree($this->object->id_category_default);
				else
					$this->object->id_category[] = $this->object->id_category_default;
			}
			if($sync_price) {
				$this->object->price = self::CleanAmount($this->GetPrice());
				$this->object->unit_price = self::CleanAmount($this->GetUnitPrice());
				$this->object->wholesale_price = self::CleanAmount($this->GetWholesalePrice());
			}

			$this->object->active = true;

			$this->object->update();
		}
		else {
			$name = $this->GetName();
			$link_rewrite = self::Generate_Slug($name);
			$this->object->name[$id_lang] = $name;
			$this->object->id_category_default = $this->GetCategoryDefault();
			$this->object->id_category[] = $this->object->id_category_default;
			$this->object->description[$id_lang] = $this->GetDesciption();
			$description_short_limit = (int)Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
			if ($description_short_limit <= 0)
				$description_short_limit = 800;
			$this->object->description_short[$id_lang] = self::cropString($this->GetShortDesciption(),$description_short_limit);
			$this->object->link_rewrite[$id_lang] = $link_rewrite;
			$this->object->meta_description[$id_lang] = $this->GetMetaDescription();
			$this->object->meta_keywords[$id_lang] = $this->GetMetaKeyword();
			$this->object->meta_title[$id_lang] = $this->GetMetaTitle();

			$this->object->price = self::CleanAmount($this->GetPrice());
			$this->object->unit_price = self::CleanAmount($this->GetUnitPrice());
			$this->object->wholesale_price = self::CleanAmount($this->GetWholesalePrice());

			$this->object->active=$this->GetActive();
			$this->object->online_only =$this->GetOnlineOnly();
			$this->object->indexed = $this->GetIndexed();
			$this->object->available_for_order = $this->GetAvailablity();
			$this->object->show_price = $this->GetShowPrice();

			$this->object->add();

			$tags = $this->GetTags();
			$this->AddTags($tags);

			$images = $this->GetImages();
			$this->AddImages($images);
			
			$features = $this->GetFeatures();
			$this->AddFeature($features);
		}

		//set quantity
		StockAvailable::setQuantity($this->GetProductID(), null, $this->GetQuantity(), null);

		if(isset($this->object->id_category))
			$this->object->updateCategories(array_map('intval', $this->object->id_category));

		$this->AfterAdd();
	}
	
	protected function GetProductID(){
		return $this->object->id;
	}

	/**
	 * 
	 * @param $images Array
	 * @return unknown_type Void
	 */
	private function AddImages($images){
		$id_lang = (int)(Configuration::get('PS_LANG_DEFAULT'));

		if(!is_array($images) OR count($images)==0)
			return;

		$_warnings = array();
		$_errors = array();
		$productHasImages = (bool)Image::getImages(1, (int)($this->object->id));
		foreach ($images AS $key => $url)
		{
			if (!empty($url))
			{
				$image = new Image();
				$image->id_product = (int)($this->object->id);
				$image->position = Image::getHighestPosition($this->object->id) + 1;
				$image->cover = (!$key AND !$productHasImages) ? true : false;
				$image->legend = self::createMultiLangField($this->object->name[$id_lang]);
				if (($fieldError = $image->validateFields($this->unfriendly_error, true)) === true AND ($langFieldError = $image->validateFieldsLang($this->unfriendly_error, true)) === true AND $image->add())
				{
					if (!self::copyImg($this->object->id, $image->id, $url))
						$_warnings[] = Tools::displayError('Error copying image: ').$url;
				}
				else
				{
					$_warnings[] = $image->legend[$id_lang].(isset($image->id_product) ? ' ('.$image->id_product.')' : '').' '.Tools::displayError('cannot be saved');
					$_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
				}
			}
		}
		if (!empty($_warnings))
			var_dump($_warnings);
		if (!empty($_errors))
			var_dump($_errors);
	}

	/**
	 * 
	 * @param $object Product
	 * @param $featuers Array
	 * @return unknown_type Void
	 */
    private function AddFeature($features){
		foreach ($features AS $feature => $value){
			if(trim($value)=='')continue;
			if($value=='1')$value='Yes';
			if($value=='0')$value='No';
			$value = preg_replace('/[<>;=#{}]/ui',' ',$value);
			$id_feature = Feature::addFeatureImport($feature);
			$id_feature_value = FeatureValue::addFeatureValueImport($id_feature, $value);
			Product::addFeatureProductImport($this->object->id, $id_feature, $id_feature_value);
		}
	}

	/**
	 * 
	 * @param $object Product
	 * @param $alltags string
	 * @return Void
	 */
	private function AddTags($alltags){
		
		if(empty($alltags)) return;
		
		
		try {
			// Delete tags for this id product, for no duplicating error
			Tag::deleteTagsForProduct($this->object->id);

			$tag = new Tag();

			$this->object->tags = self::createMultiLangField($alltags);
			foreach($this->object->tags AS $key => $tags)
			{
				$isTagAdded = $tag->addTags($key, $this->object->id, $tags);
				if(!$isTagAdded)
				{
					echo "Tags not added: ";
					p($tags);
				}
			}
		}
		//catch exception
		catch(Exception $e) {
			p('Message: ' .$e->getMessage());
		}
	}
	
	protected function AfterAdd(){
		
	}
	
	/**
	 * 
	 * @param $product
	 * @return mixed, return false if product not exist else return id_product
	 */
	abstract protected function IfExist();
	abstract public function SetProductSource(&$p);
	
	protected function GetPrice(){
		if($this->object->id)
		{
			$price_limit = (bool)Configuration::get($this->module_name . '_price_limit');
			
			$price_overhead = Db::getInstance()->getValue('
				SELECT profit
				FROM `'._DB_PREFIX_.'life365_category`
				WHERE id_category_external = '.$this->product->local_category
			);
			$product_price = $this->object->price + ($this->object->price * $price_overhead / 100);

			if($product_price > $this->object->street_price && $price_limit)
				return (float)$this->object->street_price;
			else
				return (float)$product_price;
		}
		else
			return 0.00;
	}

	protected function GetWidth(){
		if($this->object->id)
			return $this->object->width;
		else
			return 0;
	}
	
	protected function GetWeight()
	{
		if($this->object->id)
			return $this->object->weight;
		else
			return 0;
	}
	
	protected function GetDepth()
	{
		if($this->object->id)
			return $this->object->depth;
		else
			return 0;
	}
	
	protected function GetHeight()
	{
		if($this->object->id)
			return $this->object->height;
		else
			return 0;
	}
	
	protected function GetManufacturer(){
		if($this->object->id)
			return $this->object->id_manufacturer;
		else
			return 0;
	}
	
	protected function GetCategory()
	{
		if($this->object->id)
			return $this->object->id_category;
		else
			return array(1);
	}
	
	protected function GetCategoryDefault()
	{
		if($this->object->id)
			return $this->object->id_category_default;
		else
			return 1;
	}

	protected function GetCategoryParent($id_category)
	{
		
		return $parent_category;
	}
	
	protected function GetCategoryTree($id_category)
	{
		$categoryTree[] = $id_category;
		$current_category = $id_category;
		while ($id_category > 1)
		{
			$parent_category = Db::getInstance()->getValue('
				SELECT id_parent FROM '._DB_PREFIX_.'category WHERE id_category = ' . (int)$current_category .';'
			);
			if(empty($parent_category))
				$current_category = 0;
			else
				$categoryTree[] = $current_category;
		}

		return $categoryTree;
	}
	
	protected function GetShortDesciption()
	{
		return $this->object->short_description[$this->object->id_lang];
//		return str_replace("\r\n", "<br>", strip_tags($this->object->short_description[$this->object->id_lang]));
	}
	
	protected function GetDesciption()
	{
		return $this->object->description[$this->object->id_lang];
	}
	
	protected function GetEan13()
	{
		$ean13 = $this->object->barcode[$this->object->id_lang];
		if ($ean13 == "0000000000000")
			$ean13 = null;
		return $ean13;
	}
	
	protected function GetName()
	{
		return $this->object->name[$this->object->id_lang];
	}
	
	protected function GetMetaTitle()
	{
		$meta_title = $this->object->meta_title[$this->object->id_lang];
		$meta_title = preg_replace('/[<>;=#{}]/ui',' ',$meta_title);

		return $meta_title;
	}
	
	protected function GetMetaDescription()
	{
		$meta_description = $this->object->meta_description[$this->object->id_lang];
		$meta_description = preg_replace('/[<>;=#{}]/ui',' ',$meta_description);

		return $meta_description;
	}
	
	protected function GetMetaKeyword()
	{
		return $this->object->meta_keywords[$this->object->id_lang];
	}
	
	protected function GetTax_Rules_Group()
	{
		if($this->object->id)
			return Configuration::get($this->module_name . '_default_tax_id');
		else
			return Configuration::get($this->module_name . '_default_tax_id');
	}
	
	protected function GetReference()
	{
		return $this->object->reference;
	}
	protected function GetSupplierReference(){
		return $this->object->reference;
	}
	protected function GetWholesalePrice(){
		if($this->object->id)
			return $this->object->wholesale_price;
		else
			return 0.00;
	}
	protected function GetUnity(){
		if($this->object->id)
			return $this->object->unity;
		else
			return 0;
	}
	protected function GetUnitPrice(){
		if($this->object->id)
			return $this->object->unit_price;
		else
			return 0.00;
	}
	protected function GetAdditionalShippingCost(){
		if($this->object->id)
			return $this->object->additional_shipping_cost;
		else
			return 0.00;
	}
	protected function GetEcoTax(){
		if($this->object->id)
			return $this->object->ecotax;
		else
			return 0.00;
	}
	protected function GetUpc(){
		if($this->object->id)
			return $this->object->upc;
		else
			return null;
	}
	
	protected function GetOnSale()
	{
		if($this->object->id)
			return $this->object->on_sale;
		else
			return 0;
	}
	
	protected function GetColors()
	{
		if($this->object->id)
			return $this->object->id_color;
		else
			return array(0);
	}
	
	protected function GetDefualtColorId()
	{
		if($this->object->id)
			return $this->object->id_color_default;
		else
			return 0;
	}
	
	protected function GetSupplierId()
	{
		if($this->object->id)
			return $this->object->id_supplier;
		else
			return 0;
	}
	
	protected function GetShowPrice()
	{
		if($this->object->id)
			return $this->object->show_price;
		else
			return 1;
	}
	
	protected function GetMinimalQuantity()
	{
		if($this->object->id)
			return $this->object->minimal_quantity;
		else
			return 1;
	}
	
	protected function GetCondition()
	{
		if($this->object->id)
			return $this->object->condition;
		else
			return "new";
	}
	
	protected function GetOutOfStock()
	{
		if($this->object->id)
			return $this->object->out_of_stock;
		else
			return 1;
	}
	
	protected function GetAvailablity()
	{
		if($this->object->id)
			return $this->object->available_for_order;
		else
			return 1;
	}
	
	protected function GetIndexed()
	{
		if($this->object->id)
			return $this->object->indexed;
		else
			return 1;
	}
	
	protected function GetOnlineOnly()
	{
		if($this->object->id)
			return $this->object->online_only;
		else
			return 0;
	}

	protected function GetQuantity()
	{
		if($this->object->id)
			return $this->object->quantity;
		else
			return 0;
	}
	
	protected function GetActive()
	{
		if($this->object->id)
			return $this->object->active;
		else
			return 1;
	}
	
	protected function GetFeatures()
	{
		return array();
	}
	
	protected function GetImages(){
		return array();
	}
	
	protected function GetTags()
	{
		return array();
	}
	
	///
	// Static Functions
	///
	
	private static function createAndInitializeNewObject()
	{
		$id_lang = (int)(Configuration::get('PS_LANG_DEFAULT'));
		$product = new Product(null, false, $id_lang, null, null);
		$product->description = array($id_lang => '');
		$product->description_short = array($id_lang => '');
		$product->link_rewrite = array($id_lang => '');
		$product->name = array($id_lang => '');
		$product->id_category = array(0);
		$product->id_color = array(0);
		return $product;
	}

	protected static function Generate_Slug($string)
	{
		return Tools::link_rewrite($string);
	}
	public static function createMultiLangField($field)
	{
		$languages = Language::getLanguages(false);
		$res = array();
		foreach ($languages as $lang)
			$res[$lang['id_lang']] = $field;
		return $res;
	}

	public static function copyImg($id_entity, $id_image = NULL, $url, $entity = 'products')
	{
		$tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
		$watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

		switch($entity)
		{
			default:
			case 'products':
				$folders = str_split($id_image);
				$i = 0;
				$base_uri = _PS_PROD_IMG_DIR_;
				while( $i <sizeof($folders) )
				{
					$base_uri .= $folders[$i].'/';
					if($i==(sizeof($folders) -1))
					{
						if(!is_dir( $base_uri))
							if(!mkdir($base_uri, 0777, true))
								die('Failed to create directory ' . $base_uri);
					}
					$i++;
				}
				$path = $base_uri.(int)($id_image);
			break;
			case 'categories':
				$path = _PS_CAT_IMG_DIR_.(int)($id_entity);
			break;
		}
		
		$url = str_replace(" ", "%20", $url);
		if (Tools::copy($url, $tmpfile))
		{
			$url_info = pathinfo($url);
			// convert image to jpg if different type
			switch($url_info['extension'])
			{
				case 'gif':
					$image = imagecreatefromgif($tmpfile);
					imagejpeg($image, $tmpfile, 100);
					imagedestroy($image);
					break;
				case 'png':
					$image = imagecreatefrompng($tmpfile);
					imagejpeg($image, $tmpfile, 100);
					imagedestroy($image);
					break;
			}
			//imageResize($tmpfile, $path.'.jpg');
			self::removeWhiteSpace($tmpfile, $path.'.jpg');
			$newimage = $path.'.jpg';
			$imagesTypes = ImageType::getImagesTypes($entity);
			foreach ($imagesTypes as $imageType)
				ImageManager::resize($newimage, $path.'-'.Tools::stripslashes($imageType['name']).'.jpg', $imageType['width'], $imageType['height']);
			if (in_array($imageType['id_image_type'], $watermark_types))
				Module::hookExec('watermark', array('id_image' => $id_image, 'id_product' => $id_entity));
		}
		else
		{
			unlink($tmpfile);
			return false;
		}
		unlink($tmpfile);
		return true;
	}

	public static function removeWhiteSpace($from, $to)
	{
		$img = imagecreatefromjpeg($from);

		//find the size of the borders
		$b_top = 0;
		$b_btm = 0;
		$b_lft = 0;
		$b_rt = 0;
		
		//top
		for(; $b_top < imagesy($img); ++$b_top)
		{
		  for($x = 0; $x < imagesx($img); ++$x)
		  {
		    if(imagecolorat($img, $x, $b_top) != 0xFFFFFF)
			{
		       break 2; //out of the 'top' loop
		    }
		  }
		}
		
		//bottom
		for(; $b_btm < imagesy($img); ++$b_btm)
		{
		  for($x = 0; $x < imagesx($img); ++$x)
		  {
		    if(imagecolorat($img, $x, imagesy($img) - $b_btm-1) != 0xFFFFFF)
			{
		       break 2; //out of the 'bottom' loop
		    }
		  }
		}
		
		//left
		for(; $b_lft < imagesx($img); ++$b_lft)
		{
		  for($y = 0; $y < imagesy($img); ++$y)
		  {
		    if(imagecolorat($img, $b_lft, $y) != 0xFFFFFF)
			{
		       break 2; //out of the 'left' loop
		    }
		  }
		}
		
		//right
		for(; $b_rt < imagesx($img); ++$b_rt)
		{
		  for($y = 0; $y < imagesy($img); ++$y)
		  {
		    if(imagecolorat($img, imagesx($img) - $b_rt-1, $y) != 0xFFFFFF)
			{
		       break 2; //out of the 'right' loop
		    }
		  }
		}
		
		//copy the contents, excluding the border
		$newimg = imagecreatetruecolor(
		    imagesx($img)-($b_lft+$b_rt), imagesy($img)-($b_top+$b_btm));
		
		imagecopy($newimg, $img, 0, 0, $b_lft, $b_top, imagesx($newimg), imagesy($newimg));
		imagejpeg($newimg,$to); 
	}

	public static function CleanAmount($field)
	{
		$field = ((float)(str_replace(',', '.', $field)));
		$field = ((float)(str_replace('%', '', $field)));
		return $field;
	}
	
	public static function isEmpty($field)
	{
		if(empty($field) || !isset($field) || $field==0 || $field=='0' )
		{
			return true;
		}
		return false;
	}
	public static function SetValue(&$target, $value)
	{
		if(!self::isEmpty($value))
			$target = $value;
	}

	public static function cropString($str, $length)
	{
		if(Tools::strlen($str)>$length)
		{
			return Tools::substr($str,0,$length);
		}
		return $str;
	}
}