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

abstract class ProductImporter
{
    private $id_product; //product id in prestashop
    private $object; //object of type Product
    private $module_name='life365';
    
    private $unfriendly_error=true;

    public function __construct()
    {
    }
    
    public function disable()
    {
        $this->id_product = $this->ifExist();

        if (!$this->id_product) {
            return 0;
        } else if ((int)$this->id_product and Product::existsInDatabase((int)($this->id_product), 'product')) {
            $this->object = new Product((int)($this->id_product));
        } else {
            // if the subclass returned something other than false
            // skip;
            return 0;
        }
        
        StockAvailable::setQuantity($this->getProductID(), null, 0, null);
        
        $this->object->active = false;
        $this->object->update();
    }
    
    
    public function save()
    {
        $this->id_product = $this->ifExist();

        if (!$this->id_product) {
            $this->object = self::createAndInitializeNewObject();
        } else if ((int)$this->id_product and Product::existsInDatabase((int)($this->id_product), 'product')) {
            $this->object = new Product((int)($this->id_product));
        } else {
            // if the subclass returned something other than false
            // skip;
            return 0;
        }
        $this->addUpdated();
    }
    
    protected function addUpdated()
    {
        $id_lang = (int)(Configuration::get('PS_LANG_DEFAULT'));
        $sync_name = (bool)Configuration::get($this->module_name . '_sync_name');
        $sync_short_desc = (bool)Configuration::get($this->module_name . '_sync_short_desc');
        $sync_desc = (bool)Configuration::get($this->module_name . '_sync_desc');
        $sync_category = (bool)Configuration::get($this->module_name . '_sync_category');
        $sync_price = (bool)Configuration::get($this->module_name . '_sync_price');

//        $this->object->id_manufacturer = $this->getManufacturer();
        $this->object->reference = $this->getReference();
        $this->object->supplier_reference = $this->getSupplierReference();
        $this->object->id_tax_rules_group = $this->getTaxRulesGroup();
        $this->object->unity = $this->getUnity();
        $this->object->additional_shipping_cost= self::cleanAmount($this->getAdditionalShippingCost());
        $this->object->width= self::cleanAmount($this->getWidth());
        $this->object->height=self::cleanAmount($this->getHeight());
        $this->object->depth=self::cleanAmount($this->getDepth());
        $this->object->weight=self::cleanAmount($this->getWeight());
        $this->object->out_of_stock = $this->getOutOfStock();
        $this->object->condition = $this->getCondition();
        $this->object->minimal_quantity = $this->getMinimalQuantity();
        $this->object->id_supplier = $this->getSupplierId();
        $this->object->id_color_default = $this->getDefualtColorId();
        $this->object->id_color[] = $this->object->id_color_default;
//      $this->object->on_sale = $this->getOnSale();
        $this->object->ean13 = $this->getEan13();
//        $this->object->upc = $this->getUpc();
        $this->object->ecotax = self::cleanAmount($this->getEcoTax());

        /** either add or edit ***/
        if ($this->object->id) {
            if ($sync_name) {
                $name = $this->getName();
                $this->object->name[$id_lang] = $name;
                $link_rewrite = self::generateSlug($name);
                $this->object->link_rewrite[$id_lang] = $link_rewrite;
                $this->object->meta_title[$id_lang] = $this->getMetaTitle();
            }
            if ($sync_desc) {
                $this->object->description[$id_lang] = $this->getDesciption();
                $this->object->meta_description[$id_lang] = $this->getMetaDescription();
            }
            if ($sync_short_desc) {
                $description_short_limit = (int)Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
                if ($description_short_limit <= 0) {
                    $description_short_limit = 800;
                }
                $this->object->description_short[$id_lang] = self::cropString($this->getShortDesciption(), $description_short_limit);
                $this->object->meta_keywords[$id_lang] = $this->getMetaKeyword();
                $tags = $this->getTags();
                $this->addTags($tags);
            }
            if ($sync_category) {
                $this->object->id_category_default = $this->getCategoryDefault();
//                $parent_categories = (bool)Configuration::get($this->module_name . '_parent_categories');
//                if ($parent_categories)
//                    $this->object->id_category[] = $this->getCategoryTree($this->object->id_category_default);
//                else
                    $this->object->id_category[] = $this->object->id_category_default;
            }
            if ($sync_price) {
                $this->object->price = self::cleanAmount($this->getPrice());
                $this->object->unit_price = self::cleanAmount($this->getUnitPrice());
                $this->object->wholesale_price = self::cleanAmount($this->getWholesalePrice());
            }

            $this->object->active = true;

            $this->object->update();
        } else {
            $name = $this->getName();
            $link_rewrite = self::generateSlug($name);
            $this->object->name[$id_lang] = $name;
            $this->object->id_category_default = $this->getCategoryDefault();
            $this->object->id_category[] = $this->object->id_category_default;
            $this->object->description[$id_lang] = $this->getDesciption();
            $description_short_limit = (int)Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
            if ($description_short_limit <= 0) {
                $description_short_limit = 800;
            }
            $this->object->description_short[$id_lang] = self::cropString($this->getShortDesciption(), $description_short_limit);
            $this->object->link_rewrite[$id_lang] = $link_rewrite;
            $this->object->meta_description[$id_lang] = $this->getMetaDescription();
            $this->object->meta_keywords[$id_lang] = $this->getMetaKeyword();
            $this->object->meta_title[$id_lang] = $this->getMetaTitle();

            $this->object->price = self::cleanAmount($this->getPrice());
            $this->object->unit_price = self::cleanAmount($this->getUnitPrice());
            $this->object->wholesale_price = self::cleanAmount($this->getWholesalePrice());

            $this->object->active=$this->getActive();
            $this->object->online_only =$this->getOnlineOnly();
            $this->object->indexed = $this->getIndexed();
            $this->object->available_for_order = $this->getAvailablity();
            $this->object->show_price = $this->getShowPrice();

            $this->object->add();

            $tags = $this->getTags();
            $this->addTags($tags);

            $images = $this->getImages();
            $this->addImages($images);
            
            $features = $this->getFeatures();
            $this->addFeature($features);
        }

        //set quantity
        StockAvailable::setQuantity($this->getProductID(), null, $this->getQuantity(), null);

        if (isset($this->object->id_category)) {
            $this->object->updateCategories(array_map('intval', $this->object->id_category));
        }

        $this->afterAdd();
    }
    
    protected function getProductID()
    {
        return $this->object->id;
    }

    /**
     *
     * @param $images Array
     * @return unknown_type Void
     */
    private function addImages($images)
    {
        $id_lang = (int)(Configuration::get('PS_LANG_DEFAULT'));

        if (!is_array($images) or count($images)==0) {
            return;
        }

        $_warnings = array();
        $_errors = array();
        $productHasImages = (bool)Image::getImages(1, (int)($this->object->id));
        foreach ($images as $key => $url) {
            if (!empty($url)) {
                $image = new Image();
                $image->id_product = (int)($this->object->id);
                $image->position = Image::getHighestPosition($this->object->id) + 1;
                $image->cover = (!$key and !$productHasImages) ? true : false;
                $image->legend = self::createMultiLangField($this->object->name[$id_lang]);
                if (($fieldError = $image->validateFields($this->unfriendly_error, true)) === true and ($langFieldError = $image->validateFieldsLang($this->unfriendly_error, true)) === true and $image->add()) {
                    if (!self::copyImg($this->object->id, $url, $image->id)) {
                        $_warnings[] = Tools::displayError('Error copying image: ').$url;
                    }
                } else {
                    $_warnings[] = $image->legend[$id_lang].(isset($image->id_product) ? ' ('.$image->id_product.')' : '').' '.Tools::displayError('cannot be saved');
                    $_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').Db::getInstance()->getLink()->errorInfo();
                }
            }
        }
        if (!empty($_warnings)) {
            var_dump($_warnings);
        }
        if (!empty($_errors)) {
            var_dump($_errors);
        }
    }

    /**
     *
     * @param $object Product
     * @param $featuers Array
     * @return unknown_type Void
     */
    private function addFeature($features)
    {
        foreach ($features as $feature => $value) {
            if (trim($value)=='') {
                continue;
            }
            if ($value=='1') {
                $value='Yes';
            }
            if ($value=='0') {
                $value='No';
            }
            $value = preg_replace('/[<>;=#{}]/ui', ' ', $value);
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
    private function addTags($alltags)
    {
        
        if (empty($alltags)) {
            return;
        }
        
        
        try {
            // Delete tags for this id product, for no duplicating error
            Tag::deleteTagsForProduct($this->object->id);

            $tag = new Tag();

            $this->object->tags = self::createMultiLangField($alltags);
            foreach ($this->object->tags as $key => $tags) {
                $isTagAdded = $tag->addTags($key, $this->object->id, $tags);
                if (!$isTagAdded) {
                    echo "Tags not added: ";
                    p($tags);
                }
            }
        } catch (Exception $e) { //catch exception
            p('Message: ' .$e->getMessage());
        }
    }
    
    protected function afterAdd()
    {
    }
    
    /**
     *
     * @param $product
     * @return mixed, return false if product not exist else return id_product
     */
    abstract protected function ifExist();
    abstract public function setProductSource(&$p);
    
    protected function getPrice()
    {
        if ($this->object->id) {
            $price_limit = (bool)Configuration::get($this->module_name . '_price_limit');
            
            $price_overhead = Db::getInstance()->getValue(
                'SELECT profit
                FROM `'._DB_PREFIX_.'life365_category`
                WHERE id_category_external = ' . (int)$this->product->local_category
            );
            $product_price = $this->object->price + ($this->object->price * $price_overhead / 100);

            if ($product_price > $this->object->street_price && $price_limit) {
                return (float)$this->object->street_price;
            } else {
                return (float)$product_price;
            }
        } else {
            return 0.00;
        }
    }

    protected function getWidth()
    {
        if ($this->object->id) {
            return $this->object->width;
        } else {
            return 0;
        }
    }
    
    protected function getWeight()
    {
        if ($this->object->id) {
            return $this->object->weight;
        } else {
            return 0;
        }
    }
    
    protected function getDepth()
    {
        if ($this->object->id) {
            return $this->object->depth;
        } else {
            return 0;
        }
    }
    
    protected function getHeight()
    {
        if ($this->object->id) {
            return $this->object->height;
        } else {
            return 0;
        }
    }
    
    protected function getManufacturer()
    {
        if ($this->object->id) {
            return $this->object->id_manufacturer;
        } else {
            return 0;
        }
    }
    
    protected function getCategory()
    {
        if ($this->object->id) {
            return $this->object->id_category;
        } else {
            return array(1);
        }
    }
    
    protected function getCategoryDefault()
    {
        if ($this->object->id) {
            return $this->object->id_category_default;
        } else {
            return 1;
        }
    }

    protected function getCategoryTree($id_category)
    {
        $categoryTree[] = null;
        $categoryTree[] = $id_category;
        $current_category = $id_category;
        while ($id_category > 1) {
            $parent_category = Db::getInstance()->getValue(
                'SELECT id_parent FROM '._DB_PREFIX_.'category WHERE id_category = ' . (int)$current_category .';'
            );
            if (empty($parent_category)) {
                $current_category = 0;
            } else {
                $categoryTree[] = $current_category;
            }
        }

        return $categoryTree;
    }
    
    protected function getShortDesciption()
    {
        return $this->object->short_description[$this->object->id_lang];
//      return str_replace("\r\n", "<br>", strip_tags($this->object->short_description[$this->object->id_lang]));
    }
    
    protected function getDesciption()
    {
        return $this->object->description[$this->object->id_lang];
    }
    
    protected function getEan13()
    {
        $ean13 = $this->object->barcode[$this->object->id_lang];
        if ($ean13 == "0000000000000") {
            $ean13 = null;
        }
        return $ean13;
    }
    
    protected function getName()
    {
        return $this->object->name[$this->object->id_lang];
    }
    
    protected function getMetaTitle()
    {
        $meta_title = $this->object->meta_title[$this->object->id_lang];
        $meta_title = preg_replace('/[<>;=#{}]/ui', ' ', $meta_title);

        return $meta_title;
    }
    
    protected function getMetaDescription()
    {
        $meta_description = $this->object->meta_description[$this->object->id_lang];
        $meta_description = preg_replace('/[<>;=#{}]/ui', ' ', $meta_description);

        return $meta_description;
    }
    
    protected function getMetaKeyword()
    {
        return $this->object->meta_keywords[$this->object->id_lang];
    }
    
    protected function getTaxRulesGroup()
    {
        if ($this->object->id) {
            return Configuration::get($this->module_name . '_default_tax_id');
        } else {
            return Configuration::get($this->module_name . '_default_tax_id');
        }
    }
    
    protected function getReference()
    {
        return $this->object->reference;
    }

    protected function getSupplierReference()
    {
        return $this->object->reference;
    }

    protected function getWholesalePrice()
    {
        if ($this->object->id) {
            return $this->object->wholesale_price;
        } else {
            return 0.00;
        }
    }

    protected function getUnity()
    {
        if ($this->object->id) {
            return $this->object->unity;
        } else {
            return 0;
        }
    }

    protected function getUnitPrice()
    {
        if ($this->object->id) {
            return $this->object->unit_price;
        } else {
            return 0.00;
        }
    }

    protected function getAdditionalShippingCost()
    {
        if ($this->object->id) {
            return $this->object->additional_shipping_cost;
        } else {
            return 0.00;
        }
    }

    protected function getEcoTax()
    {
        if ($this->object->id) {
            return $this->object->ecotax;
        } else {
            return 0.00;
        }
    }

    protected function getUpc()
    {
        return null;
    }
    
    protected function getOnSale()
    {
        if ($this->object->id) {
            return $this->object->on_sale;
        } else {
            return 0;
        }
    }
    
    protected function getColors()
    {
        if ($this->object->id) {
            return $this->object->id_color;
        } else {
            return array(0);
        }
    }
    
    protected function getDefualtColorId()
    {
        if ($this->object->id) {
            return $this->object->id_color_default;
        } else {
            return 0;
        }
    }
    
    protected function getSupplierId()
    {
        if ($this->object->id) {
            return $this->object->id_supplier;
        } else {
            return 0;
        }
    }
    
    protected function getShowPrice()
    {
        if ($this->object->id) {
            return $this->object->show_price;
        } else {
            return 1;
        }
    }
    
    protected function getMinimalQuantity()
    {
        if ($this->object->id) {
            return $this->object->minimal_quantity;
        } else {
            return 1;
        }
    }
    
    protected function getCondition()
    {
        if ($this->object->id) {
            return $this->object->condition;
        } else {
            return "new";
        }
    }
    
    protected function getOutOfStock()
    {
        if ($this->object->id) {
            return $this->object->out_of_stock;
        } else {
            return 1;
        }
    }
    
    protected function getAvailablity()
    {
        if ($this->object->id) {
            return $this->object->available_for_order;
        } else {
            return 1;
        }
    }
    
    protected function getIndexed()
    {
        if ($this->object->id) {
            return $this->object->indexed;
        } else {
            return 1;
        }
    }
    
    protected function getOnlineOnly()
    {
        if ($this->object->id) {
            return $this->object->online_only;
        } else {
            return 0;
        }
    }

    protected function getQuantity()
    {
        if ($this->object->id) {
            return $this->object->quantity;
        } else {
            return 0;
        }
    }
    
    protected function getActive()
    {
        if ($this->object->id) {
            return $this->object->active;
        } else {
            return 1;
        }
    }
    
    protected function getFeatures()
    {
        return array();
    }
    
    protected function getImages()
    {
        return array();
    }
    
    protected function getTags()
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

    protected static function generateSlug($string)
    {
        return Tools::link_rewrite($string);
    }
    public static function createMultiLangField($field)
    {
        $languages = Language::getLanguages(false);
        $res = array();
        foreach ($languages as $lang) {
            $res[$lang['id_lang']] = $field;
        }
        return $res;
    }

    public static function copyImg($id_entity, $url, $id_image = null, $entity = 'products')
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

        switch ($entity) {
            default:
            case 'products':
                $folders = str_split($id_image);
                $i = 0;
                $base_uri = _PS_PROD_IMG_DIR_;
                while ($i <sizeof($folders)) {
                    $base_uri .= $folders[$i].'/';
                    if ($i==(sizeof($folders) -1)) {
                        if (!is_dir($base_uri)) {
                            if (!mkdir($base_uri, 0777, true)) {
                                die('Failed to create directory ' . $base_uri);
                            }
                        }
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
        if (Tools::copy($url, $tmpfile)) {
            $url_info = pathinfo($url);
            // convert image to jpg if different type
            switch ($url_info['extension']) {
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
            foreach ($imagesTypes as $imageType) {
                ImageManager::resize($newimage, $path.'-'.Tools::stripslashes($imageType['name']).'.jpg', $imageType['width'], $imageType['height']);
            }
            if (in_array($imageType['id_image_type'], $watermark_types)) {
                Module::hookExec('watermark', array('id_image' => $id_image, 'id_product' => $id_entity));
            }
        } else {
            unlink($tmpfile);
            return false;
        }
        unlink($tmpfile);
        return true;
    }

    public static function removeWhiteSpace($from, $to)
    {
        $file_dimensions = getimagesize($from);
        $image_type = Tools::strtolower($file_dimensions['mime']);

        switch ($image_type) {
            case 'image/png':
                $img = imagecreatefrompng($from);
                break;
            case 'image/jpeg':
                $img = imagecreatefromjpeg($from);
                break;
            default:
                p('Unsupported File: ' . $image_type . ' - ' . $from); //output error
                $img = imagecreatetruecolor(200, 200);
        }

        //find the size of the borders
        $b_top = 0;
        $b_btm = 0;
        $b_lft = 0;
        $b_rt = 0;
        
        //top
        for (; $b_top < imagesy($img); ++$b_top) {
            for ($x = 0; $x < imagesx($img); ++$x) {
                if (imagecolorat($img, $x, $b_top) != 0xFFFFFF) {
                    break 2; //out of the 'top' loop
                }
            }
        }
        
        //bottom
        for (; $b_btm < imagesy($img); ++$b_btm) {
            for ($x = 0; $x < imagesx($img); ++$x) {
                if (imagecolorat($img, $x, imagesy($img) - $b_btm-1) != 0xFFFFFF) {
                    break 2; //out of the 'bottom' loop
                }
            }
        }
        
        //left
        for (; $b_lft < imagesx($img); ++$b_lft) {
            for ($y = 0; $y < imagesy($img); ++$y) {
                if (imagecolorat($img, $b_lft, $y) != 0xFFFFFF) {
                    break 2; //out of the 'left' loop
                }
            }
        }
        
        //right
        for (; $b_rt < imagesx($img); ++$b_rt) {
            for ($y = 0; $y < imagesy($img); ++$y) {
                if (imagecolorat($img, imagesx($img) - $b_rt-1, $y) != 0xFFFFFF) {
                    break 2; //out of the 'right' loop
                }
            }
        }
        
        //copy the contents, excluding the border
        $newimg = imagecreatetruecolor(imagesx($img)-($b_lft+$b_rt), imagesy($img)-($b_top+$b_btm));
        
        imagecopy($newimg, $img, 0, 0, $b_lft, $b_top, imagesx($newimg), imagesy($newimg));
        imagejpeg($newimg, $to);
    }

    public static function cleanAmount($field)
    {
        $field = ((float)(str_replace(',', '.', $field)));
        $field = ((float)(str_replace('%', '', $field)));
        return $field;
    }
    
    public static function isEmpty($field)
    {
        if (empty($field) || !isset($field) || $field==0 || $field=='0') {
            return true;
        }
        return false;
    }
    public static function setValue(&$target, $value)
    {
        if (!self::isEmpty($value)) {
            $target = $value;
        }
    }

    public static function cropString($str, $length)
    {
        if (Tools::strlen($str)>$length) {
            return Tools::substr($str, 0, $length);
        }
        return $str;
    }
}
