<?php
/**
 * 2007-2026 PrestaShop
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
 * @author    Giancarlo Spadini <giancarlo@spadini.it>
 * @copyright 2007-2026 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AccessoryImporter
{
    protected $product;

    private $id_product;

    private $object;

    private $module_name = 'life365';

    private $unfriendly_error = true;

    protected $image_basepath;

    public function __construct()
    {
    }

    public function setProductSource(&$p)
    {
        if (empty($p)) {
            throw new Exception('No Product Source');
        }
        $this->product = $p;
    }

    public function disable()
    {
        $this->id_product = $this->ifExist();

        if (!$this->id_product) {
            return 0;
        }

        if ((int) $this->id_product && Product::existsInDatabase((int) $this->id_product, 'product')) {
            $this->object = new Product((int) $this->id_product);
        } else {
            return 0;
        }

        StockAvailable::setQuantity(
            $this->getProductID(),
            0,
            0
        );

        $this->object->active = false;
        $this->object->save();

        return 1;
    }

    public function ifExistId($productId)
    {
        $id_product = 0;

        $sql = 'SELECT id_product_ps FROM ' . _DB_PREFIX_ . 'life365_product WHERE id_product_external = ' . (int) $productId;
        $res = Db::getInstance()->getRow($sql);

        if ($res) {
            $id_product = $res['id_product_ps'];
            if (!Product::existsInDatabase((int) $id_product, 'product')) {
                $t_sql = 'DELETE FROM ' . _DB_PREFIX_ . 'life365_product WHERE id_product_ps = ' . (int) $id_product;
                Db::getInstance()->execute($t_sql);
                $id_product = 0;
            }
        }

        return $id_product;
    }

    public function saveQuantity($productId, $qta)
    {
        $this->id_product = $this->ifExistId($productId);

        if (!$this->id_product) {
            return 0;
        }

        if ((int) $this->id_product && Product::existsInDatabase((int) $this->id_product, 'product')) {
            $this->object = new Product((int) $this->id_product);
        } else {
            return 0;
        }

        try {
            StockAvailable::setQuantity($this->id_product, 0, $qta);
        } catch (Exception $e) {
            $debug = (bool) Configuration::get($this->module_name . '_debug_mode');
            if ($debug) {
                p('Something went wrong when saving quantity: ' . $e->getMessage());
            }
        }

        $this->object->update();

        return 1;
    }

    public function save()
    {
        $new_product = false;
        $this->id_product = $this->ifExist();

        if (!$this->id_product) {
            if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                $this->object = new Product();
                $this->object->save();

                $this->id_product = $this->object->id;

                $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'life365_product` (`id_product_external`, `date_import`, `id_product_ps`, `version`)
                        VALUES (' . (int) $this->product->id . ', CURRENT_TIMESTAMP, ' . (int) $this->object->id . ', ' . (int) $this->product->version . ')';

                Db::getInstance()->execute($sql);
            } else {
                $this->object = self::createAndInitializeNewObject();
            }
            $new_product = true;
        }

        if ((int) $this->id_product && Product::existsInDatabase((int) $this->id_product, 'product')) {
            $this->object = new Product((int) $this->id_product);
        } elseif (!$new_product) {
            print_r('Error: Product ID ' . $this->id_product . ' does not exist in database' . PHP_EOL);
            return 0;
        }

        $this->addUpdated($new_product);
    }

    protected function addUpdated($new_product)
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $sync_name = (bool) Configuration::get($this->module_name . '_sync_name');
        $sync_short_desc = (bool) Configuration::get($this->module_name . '_sync_short_desc');
        $sync_desc = (bool) Configuration::get($this->module_name . '_sync_desc');
        $sync_category = (bool) Configuration::get($this->module_name . '_sync_category');
        $sync_price = (bool) Configuration::get($this->module_name . '_sync_price');

        $this->object->id_manufacturer = $this->getManufacturer();
        $this->object->reference = $this->getReference();
        $this->object->supplier_reference = $this->getSupplierReference();
        $this->object->id_tax_rules_group = $this->getTaxRulesGroup();
        $this->object->unity = $this->getUnity();
        $this->object->additional_shipping_cost = self::cleanAmount($this->getAdditionalShippingCost());
        $this->object->width = self::cleanAmount($this->getWidth());
        $this->object->height = self::cleanAmount($this->getHeight());
        $this->object->depth = self::cleanAmount($this->getDepth());
        $this->object->weight = self::cleanAmount($this->getWeight());
        $this->object->out_of_stock = $this->getOutOfStock();
        $this->object->condition = $this->getCondition();
        $this->object->minimal_quantity = $this->getMinimalQuantity();
        $this->object->id_supplier = $this->getSupplierId();
        $this->object->id_color_default = $this->getDefualtColorId();
        $this->object->id_color[] = $this->object->id_color_default;
        $this->object->ean13 = $this->getEan13();
        $this->object->ecotax = self::cleanAmount($this->getEcoTax());

        if (!$new_product) {
            if ($sync_name) {
                $name = $this->getName();
                $this->object->name = self::createMultiLangField($name);
                $link_rewrite = self::generateSlug($name);
                $this->object->link_rewrite = $link_rewrite;
                $this->object->meta_title = $this->getMetaTitle();
            }

            if ($sync_desc) {
                $this->object->description = $this->getDesciption();
                $this->object->meta_description = $this->getMetaDescription();
            }

            if ($sync_short_desc) {
                $description_short_limit = (int) Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
                if ($description_short_limit <= 0) {
                    $description_short_limit = 800;
                }
                $this->object->description_short = self::cropString($this->getShortDesciption(), $description_short_limit);
                $this->object->meta_keywords = $this->getMetaKeyword();
                $tags = $this->getTags();
                $this->addTags($tags);
            }

            if ($sync_category) {
                $this->object->id_category_default = $this->getCategoryDefault();
                $this->object->id_category[] = $this->object->id_category_default;
                $this->object->updateCategories([$this->object->id_category_default]);
            }

            if ($sync_price) {
                $this->object->price = self::cleanAmount($this->getPrice());
                $this->object->unit_price = self::cleanAmount($this->getUnitPrice());
                $this->object->wholesale_price = self::cleanAmount($this->getWholesalePrice());
            }

            // Validate and fix description before update
            if (is_string($this->object->description)) {
                // Description is a string (loaded from DB) - validate directly
                if (!empty($this->object->description) && !Validate::isCleanHtml($this->object->description)) {
                    $this->object->description = '';
                }
            } elseif (is_array($this->object->description)) {
                // Description is an array - validate each language
                foreach ($this->object->description as $lang_id => $desc) {
                    if (!empty($desc) && !Validate::isCleanHtml($desc)) {
                        $this->object->description[$lang_id] = '';
                    }
                }
            }

            $this->object->active = true;
            $this->object->update();
        } else {
            $name = $this->getName();
            $link_rewrite = self::generateSlug($name);
            $this->object->name = self::createMultiLangField($name);
            $this->object->id_category_default = $this->getCategoryDefault();
            $this->object->id_category[] = $this->object->id_category_default;

            $description = $this->getDesciption();
            // Validate description, set empty if validation fails
            if (!Validate::isCleanHtml($description)) {
                $description = '';
            }
            $this->object->description[$id_lang] = $description;

            $description_short_limit = (int) Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
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

            $this->object->active = $this->getActive();
            $this->object->online_only = $this->getOnlineOnly();
            $this->object->indexed = $this->getIndexed();
            $this->object->available_for_order = $this->getAvailablity();
            $this->object->show_price = $this->getShowPrice();

            if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                $this->object->save();
            } else {
                // Validate and fix description before saving
                if (isset($this->object->description[1]) && !Validate::isCleanHtml($this->object->description[1])) {
                    $this->object->description[1] = '';
                }
                $this->object->add();
            }

            $tags = $this->getTags();
            $this->addTags($tags);

            $images = $this->getImages();
            $this->addImages($images);

            $features = $this->getFeatures();
            $this->addFeature($features);
        }

        $this->afterAdd();
    }

    protected function getProductID()
    {
        return $this->object->id;
    }

    private function addImages($images)
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        if (!is_array($images) || count($images) == 0) {
            return;
        }

        $_warnings = [];
        $_errors = [];
        $productHasImages = (bool) Image::getImages(1, (int) $this->object->id);

        foreach ($images as $key => $url) {
            if (!empty($url)) {
                $image = new Image();
                $image->id_product = (int) $this->object->id;
                $image->position = Image::getHighestPosition($this->object->id) + 1;
                $image->cover = !$key && !$productHasImages ? true : false;
                $image->legend = self::createMultiLangField($this->object->name[$id_lang]);

                if (($fieldError = $image->validateFields($this->unfriendly_error, true)) === true && $image->add()) {
                    try {
                        if (!self::copyImg($this->object->id, $url, $image->id)) {
                            $_warnings[] = Tools::displayError('Error copying image: ') . $url;
                        }
                    } catch (Exception $e) {
                        p('Something went wrong downloading image: ' . $e->getMessage());
                    }
                } else {
                    $_warnings[] = $image->legend[$id_lang] . ' (' . $image->id_product . ') ' . Tools::displayError('cannot be saved');
                    $errorMsg = ($fieldError !== true && $fieldError !== false) ? $fieldError : '';
                    $_errors[] = $errorMsg . implode(' | ', Db::getInstance()->getLink()->errorInfo());
                }
            }
        }

        if (!empty($_warnings)) {
            var_dump($_warnings);
        }

        if (!empty($_errors)) {
            var_dump($_errors);
        }

        return true;
    }

    private function addFeature($features)
    {
        foreach ($features as $feature => $value) {
            if (trim($value) == '') {
                continue;
            }
            if ($value == '1') {
                $value = 'Yes';
            }
            if ($value == '0') {
                $value = 'No';
            }

            $value = preg_replace('/[<>;=#{}]/ui', ' ', $value);
            $id_feature = Feature::addFeatureImport($feature);
            $id_feature_value = FeatureValue::addFeatureValueImport($id_feature, $value);
            Product::addFeatureProductImport($this->object->id, $id_feature, $id_feature_value);
        }

        return true;
    }

    private function addTags($alltags)
    {
        if (empty($alltags)) {
            return;
        }

        try {
            Tag::deleteTagsForProduct($this->object->id);

            $tag = new Tag();

            $this->object->tags = self::createMultiLangField($alltags);
            foreach ($this->object->tags as $key => $tags) {
                $isTagAdded = $tag->addTags($key, $this->object->id, $tags);
                if (!$isTagAdded) {
                    echo 'Tags not added: ';
                    p($tags);
                }
            }
        } catch (Exception $e) {
            p('Message: ' . $e->getMessage());
        }
    }

    protected function getCategory()
    {
        if ($this->object->id) {
            return $this->object->id_category;
        } else {
            return [1];
        }
    }

    protected function getCategoryTree($id_category)
    {
        $categoryTree = [];
        $categoryTree[] = $id_category;
        $current_category = $id_category;
        while ($id_category > 1) {
            $parent_category = Db::getInstance()->getValue(
                'SELECT id_parent FROM ' . _DB_PREFIX_ . 'category WHERE id_category = ' . (int) $current_category . ';'
            );
            if (empty($parent_category)) {
                $current_category = 0;
            } else {
                $categoryTree[] = $current_category;
            }
        }

        return $categoryTree;
    }

    protected function getTaxRulesGroup()
    {
        if ($this->object->id) {
            return Configuration::get($this->module_name . '_default_tax_id');
        } else {
            return Configuration::get($this->module_name . '_default_tax_id');
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
            return [0];
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

    protected function getCondition()
    {
        if ($this->object->id) {
            return $this->object->condition;
        } else {
            return 'new';
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
        return [];
    }

    private static function createAndInitializeNewObject()
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $product = new Product();
        $product->description = [$id_lang => ''];
        $product->description_short = [$id_lang => ''];
        $product->link_rewrite = [$id_lang => ''];
        $product->name = [$id_lang => ''];

        return $product;
    }

    protected static function generateSlug($string)
    {
        return Tools::str2url($string);
    }

    public static function createMultiLangField($field)
    {
        $res = [];
        foreach (Language::getIDs(false) as $id_lang) {
            $res[$id_lang] = $field;
        }

        return $res;
    }

    public static function copyImg($id_entity, $url, $id_image = null, $entity = 'products')
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');

        switch ($entity) {
            default:
            case 'products':
                $folders = str_split($id_image);
                $i = 0;
                $base_uri = defined('_PS_PROD_IMG_DIR_') ? _PS_PROD_IMG_DIR_ : _PS_IMG_DIR_ . 'p/';
                while ($i < sizeof($folders)) {
                    $base_uri .= $folders[$i] . '/';
                    if ($i == (sizeof($folders) - 1)) {
                        if (!is_dir($base_uri)) {
                            if (!mkdir($base_uri, 0777, true)) {
                                throw new Exception('Failed to create directory ' . $base_uri);
                            }
                        }
                    }
                    ++$i;
                }
                $path = $base_uri . (int) $id_image;
                break;
            case 'categories':
                $path = _PS_CAT_IMG_DIR_ . (int) $id_entity;
                break;
        }

        $url = str_replace(' ', '%20', $url);
        if (Tools::copy($url, $tmpfile)) {
            try {
                $url_info = pathinfo($url);
                $ex_extension = 'jpg';
                $file_type = mime_content_type($tmpfile);

                switch ($file_type) {
                    case 'image/gif':
                        $image = imagecreatefromgif($tmpfile);
                        imagejpeg($image, $tmpfile, 100);
                        imagedestroy($image);
                        break;
                    case 'image/png':
                        $image = imagecreatefrompng($tmpfile);
                        imagejpeg($image, $tmpfile, 100);
                        imagedestroy($image);
                        break;
                }

                self::removeWhiteSpace($tmpfile, $path . '.jpg');
                $newimage = $path . '.jpg';
                $imagesTypes = ImageType::getImagesTypes($entity);
                foreach ($imagesTypes as $imageType) {
                    ImageManager::resize(
                        $newimage,
                        $path . '-' . stripslashes($imageType['name']) . '.jpg',
                        $imageType['width'],
                        $imageType['height']
                    );
                }
            } catch (Exception $e) {
                if (is_string($tmpfile)) {
                    $uploadDir = realpath(_PS_UPLOAD_DIR_);
                    $safeTmpfile = basename($tmpfile);
                    $safePath = realpath($uploadDir . DIRECTORY_SEPARATOR . $safeTmpfile);
                    if ($safePath !== false && strpos($safePath, $uploadDir) === 0 && file_exists($safePath)) {
                        unlink($safePath);
                    }
                }

                throw $e;
            }
        } else {
            if (is_string($tmpfile)) {
                $uploadDir = realpath(_PS_UPLOAD_DIR_);
                $tmpfile = basename($tmpfile);
                $fullPath = realpath($uploadDir . DIRECTORY_SEPARATOR . $tmpfile);
                if ($fullPath !== false && strpos($fullPath, $uploadDir) === 0 && file_exists($fullPath)) {
                    unlink($fullPath);
                } else {
                    error_log('Tentativo di path traversal: ' . $tmpfile);
                }
            }

            return false;
        }

        if (is_string($tmpfile)) {
            $uploadDir = realpath(_PS_UPLOAD_DIR_);
            $tmpfile = basename($tmpfile);
            $fullPath = realpath($uploadDir . DIRECTORY_SEPARATOR . $tmpfile);
            if ($fullPath !== false && strpos($fullPath, $uploadDir) === 0 && file_exists($fullPath)) {
                unlink($fullPath);
            } else {
                error_log('Tentativo di path traversal: ' . $tmpfile);
            }
        }

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
                p('Unsupported File: ' . $image_type . ' - ' . $from);
                $img = imagecreatetruecolor(200, 200);
        }

        $b_top = 0;
        $b_btm = 0;
        $b_lft = 0;
        $b_rt = 0;

        for (; $b_top < imagesy($img); ++$b_top) {
            for ($x = 0; $x < imagesx($img); ++$x) {
                if (imagecolorat($img, $x, $b_top) != 0xFFFFFF) {
                    break 2;
                }
            }
        }

        for (; $b_btm < imagesy($img); ++$b_btm) {
            for ($x = 0; $x < imagesx($img); ++$x) {
                if (imagecolorat($img, $x, imagesy($img) - $b_btm - 1) != 0xFFFFFF) {
                    break 2;
                }
            }
        }

        for (; $b_lft < imagesx($img); ++$b_lft) {
            for ($y = 0; $y < imagesy($img); ++$y) {
                if (imagecolorat($img, $b_lft, $y) != 0xFFFFFF) {
                    break 2;
                }
            }
        }

        for (; $b_rt < imagesx($img); ++$b_rt) {
            for ($y = 0; $y < imagesy($img); ++$y) {
                if (imagecolorat($img, imagesx($img) - $b_rt - 1, $y) != 0xFFFFFF) {
                    break 2;
                }
            }
        }

        $newimg = imagecreatetruecolor(imagesx($img) - ($b_lft + $b_rt), imagesy($img) - ($b_top + $b_btm));

        imagecopy($newimg, $img, 0, 0, $b_lft, $b_top, imagesx($newimg), imagesy($newimg));
        imagejpeg($newimg, $to);
    }

    public static function cleanAmount($field)
    {
        $field = (float) str_replace('%', '', str_replace(',', '.', $field));

        return $field;
    }

    public static function isEmpty($field)
    {
        if (empty($field) || $field == 0 || $field == '0') {
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

    protected static function cropString($str, $length)
    {
        if (Tools::strlen($str) > $length) {
            return Tools::substr($str, 0, $length);
        }

        return $str;
    }

    public function setImageBasePath($path)
    {
        if (Tools::substr($path, Tools::strlen($path) - 1) != '/') {
            $path .= '/';
        }
        $this->image_basepath = $path;
    }

    public function getVersion($id_product)
    {
        $version = Db::getInstance()->ExecuteS(
            'SELECT `version` FROM `' . _DB_PREFIX_ . 'life365_product` WHERE id_product_external = ' . (int) $id_product
        );
        if (empty($version)) {
            return 0;
        }
        return $version[0]['version'];
    }

    protected function getPrice()
    {
        $price_limit = (bool) Configuration::get('life365_price_limit');
        $price_overhead = Db::getInstance()->getValue(
            'SELECT profit FROM `' . _DB_PREFIX_ . 'life365_category` WHERE id_category_external = ' . (int) $this->product->local_category
        );
        $product_price = $this->product->price + ($this->product->price * $price_overhead / 100);

        if ($product_price > $this->product->street_price && $price_limit) {
            return (float) $this->product->street_price;
        }

        return (float) $product_price;
    }

    protected function getWholesalePrice()
    {
        return (float) $this->product->price;
    }

    protected function getWidth()
    {
        return (float) 0;
    }

    protected function getHeight()
    {
        return (float) 0;
    }

    protected function getDepth()
    {
        return (float) 0;
    }

    protected function getWeight()
    {
        return (float) ($this->product->weight / 1000);
    }

    protected function getShortDesciption()
    {
        $str_tmp = strip_tags($this->product->short_description);
        $str_tmp = str_replace("\r\n", '<br>', $str_tmp);
        return $str_tmp;
    }

    protected function getDesciption()
    {
        return (string) $this->product->description;
    }

    protected function getMetaDescription()
    {
        $meta_description = (string) $this->product->meta_description;
        $meta_description = preg_replace('/[<>;=#{}]/ui', ' ', $meta_description);
        return (Tools::strlen($meta_description) > 255) ? Tools::substr($meta_description, 0, 255) : $meta_description;
    }

    protected function getEan13()
    {
        $ean13 = (string) $this->product->barcode;
        if ($ean13 == '0000000000000') {
            $ean13 = null;
        }
        return $ean13;
    }

    protected function getMinimalQuantity()
    {
        return (int) $this->product->qty_delivery;
    }

    protected function getReference()
    {
        return (string) $this->product->reference;
    }

    protected function getSupplierReference()
    {
        return (string) $this->product->reference;
    }

    protected function getMetaKeyword()
    {
        $meta_keywords = $this->product->meta_keywords;
        $meta_keywords = preg_replace('/[<>;=#{}]/ui', ' ', $meta_keywords);
        return (string) Tools::substr($meta_keywords, 0, 255);
    }

    protected function getMetaTitle()
    {
        $meta_title = (string) $this->product->meta_title;
        return preg_replace('/[<>;=#{}]/ui', ' ', $meta_title);
    }

    protected function getName()
    {
        $not_valid = ['#', '{', '}', '^', '<', '>', ';', '='];
        $name = str_replace($not_valid, '', (string) $this->product->name);
        if (empty($name)) {
            p((string) $this->product);
        }
        return $name;
    }

    protected function getUnitPrice()
    {
        return (float) 0;
    }

    protected function getManufacturerName()
    {
        return (string) $this->product->manufacturer;
    }

    public function getManufacturerId($name)
    {
        if (empty($name)) {
            return 0;
        }

        $res = Db::getInstance()->ExecuteS(
            'SELECT id_manufacturer AS id FROM ' . _DB_PREFIX_ . 'manufacturer WHERE name = \'' . pSQL($name) . '\''
        );

        if (empty($res)) {
            Db::getInstance()->execute(
                'INSERT INTO ' . _DB_PREFIX_ . 'manufacturer (`name`, `active`, `date_add`, `date_upd`) ' .
                'VALUES (\'' . pSQL($name) . '\', 1, CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP())'
            );

            $res = Db::getInstance()->ExecuteS(
                'SELECT id_manufacturer AS id FROM ' . _DB_PREFIX_ . 'manufacturer WHERE name = \'' . pSQL($name) . '\''
            );

            if (empty($res)) {
                throw new Exception('Accessory Import Exception : No Manufacturer');
            }

            if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                $employeeContext = \PrestaShop\PrestaShop\Adapter\ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Context\\EmployeeContext');
                $id_shop = (int) $employeeContext->getShop()->getId();
                $language_id = (int) $employeeContext->getLanguage()->getId();
            } else {
                $id_shop = (int) Shop::getContextShopID();
                $language_id = (int) Configuration::get('PS_LANG_DEFAULT');
            }

            Db::getInstance()->execute(
                'INSERT INTO ' . _DB_PREFIX_ . 'manufacturer_shop VALUES (' . (int) $res[0]['id'] . ', ' . (int) $id_shop . ')'
            );

            Db::getInstance()->execute(
                'INSERT INTO ' . _DB_PREFIX_ . 'manufacturer_lang (`id_manufacturer`, `id_lang`) VALUES (' . (int) $res[0]['id'] . ', ' . (int) $language_id . ')'
            );
        }

        return $res[0]['id'];
    }

    protected function getManufacturer()
    {
        $name = $this->getManufacturerName();
        if (empty($name)) {
            return 0;
        }

        return $this->getManufacturerId($name);
    }

    protected function getQuantity()
    {
        return (string) $this->product->quantity;
    }

    protected function getImages()
    {
        return [$this->product->url_image];
    }

    protected function getTags()
    {
        $meta_keywords = $this->product->meta_keywords;
        $meta_keywords = preg_replace('/[!<;>;?=+#"°{}_$%]/ui', ' ', $meta_keywords);
        $str_tags = str_replace(' ', ',', $meta_keywords);
        $tags = explode(',', $str_tags);

        return (array) array_merge(array_filter($tags));
    }

    protected function getCategoryDefault()
    {
        $default_category = Db::getInstance()->getValue(
            'SELECT id_category_ps FROM `' . _DB_PREFIX_ . 'life365_category` ' .
            'WHERE id_category_external = ' . (int) $this->product->local_category
        );

        if (empty($default_category)) {
            $default_category = Configuration::get('life365_default_category');
        }

        return $default_category;
    }

    protected function ifExist()
    {
        $id_product = false;
        $sql = 'SELECT id_product_ps FROM ' . _DB_PREFIX_ . 'life365_product WHERE id_product_external = ' . (int) $this->product->id;

        $rows = Db::getInstance()->executeS($sql);

        if ($rows && isset($rows[0]['id_product_ps'])) {
            $id_product = (int) $rows[0]['id_product_ps'];

            if (!Product::existsInDatabase($id_product, 'product')) {
                Db::getInstance()->execute(
                    'DELETE FROM ' . _DB_PREFIX_ . 'life365_product WHERE id_product_ps = ' . $id_product
                );
                $id_product = false;
            }
        }

        return $id_product;
    }

    protected function afterAdd()
    {
        if (!$this->ifExist()) {
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'life365_product` (`id_product_external`, `date_import`, `id_product_ps`, `version`) 
                    VALUES (' . (int) $this->product->id . ', CURRENT_TIMESTAMP, ' . (int) $this->object->id . ', ' . (int) $this->product->version . ')';

            Db::getInstance()->execute($sql);
        } else {
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'life365_product` SET `version` = ' . (int) $this->product->version . ' WHERE `id_product_external` = ' . (int) $this->product->id;

            Db::getInstance()->execute($sql);
        }

        $this->saveQuantity($this->getProductID(), $this->getQuantity());
    }
}
