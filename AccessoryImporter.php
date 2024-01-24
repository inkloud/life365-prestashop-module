<?php
/**
* 2007-2023 PrestaShop
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
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class AccessoryImporter extends ProductImporter
{
    protected $product; //row source
    protected $image_basepath;

    public function setProductSource(&$p)
    {
        if (empty($p)) {
            throw new Exception("No Product Source");
        }

        $this->product = $p;
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
            'SELECT `version` FROM `' .
            _DB_PREFIX_ .
            'life365_product` WHERE id_product_external = ' .
            (int)$id_product
        );

        if (empty($version)) {
            return 0;
        }

        return $version[0]['version'];
    }

    protected function getPrice()
    {
        $price_limit = (bool)Configuration::get('life365_price_limit');

        $price_overhead = Db::getInstance()->getValue(
            'SELECT profit FROM `' .
            _DB_PREFIX_ .
            'life365_category` WHERE id_category_external = ' .
            (int)$this->product->local_category
        );
        $product_price = $this->product->price + ($this->product->price * $price_overhead / 100);

        if ($product_price > $this->product->street_price && $price_limit) {
            return (float)$this->product->street_price;
        } else {
            return (float)$product_price;
        }
    }

    protected function getWholesalePrice()
    {
        $product_price = $this->product->price;
        return (float)$product_price;
    }

    protected function getWidth()
    {
        return (float)0;
    }

    protected function getHeight()
    {
        return (float)0;
    }
    protected function getDepth()
    {
        return (float)0;
    }
    
    protected function getWeight()
    {
        return (float)($this->product->weight / 1000);
    }
    
    protected function getShortDesciption()
    {
        $str_tmp = strip_tags($this->product->short_description);
        $str_tmp = str_replace("\r\n", "<br>", $str_tmp);

        return $this->product->short_description;
    }
    
    protected function getDesciption()
    {
        return (string)$this->product->description;
    }
    
    protected function getMetaDescription()
    {
        $meta_description = (string)$this->product->meta_description;
        $meta_description = preg_replace('/[<>;=#{}]/ui', ' ', $meta_description);

        $meta_description = (Tools::strlen($meta_description) > 255) ? Tools::substr($meta_description, 0, 255) : $meta_description;

        return $meta_description;
    }

    protected function getEan13()
    {
        $ean13 = (string)$this->product->barcode;
        if ($ean13 == "0000000000000") {
            $ean13 = null;
        }

        return $ean13;
    }

    protected function getMinimalQuantity()
    {
        return (int)$this->product->qty_delivery;
    }

    protected function getReference()
    {
        return (string)$this->product->reference;
    }
    
    protected function getSupplierReference()
    {
        return (string)$this->product->reference;
    }
    
    protected function getMetaKeyword()
    {
        $meta_keywords = $this->product->meta_keywords;
        $meta_keywords = preg_replace('/[<>;=#{}]/ui', ' ', $meta_keywords);

        return (string)Tools::substr($meta_keywords, 0, 255);
    }

    protected function getMetaTitle()
    {
        $meta_title = (string)$this->product->meta_title;
        $meta_title = preg_replace('/[<>;=#{}]/ui', ' ', $meta_title);

        return $meta_title;
    }
    
    protected function getName()
    {
        $not_valid = array("#", "{", "}", "^", "<", ">", ";", "=");
        $name = str_replace($not_valid, '', (string)$this->product->name);
        if (empty($name)) {
            p((string)$this->product);
//          throw Exception("Accessory Import Exception : Blank Name");
        }
        return $name;
    }
    
    protected function getUnitPrice()
    {
        return (float) 0;
    }

    protected function getManufacturerName()
    {
        return (string)$this->product->manufactuter;
    }


    public function getManufacturerId($name)
    {
        if (empty($name)) {
            return 0;
        }
        

        $res = Db::getInstance()->ExecuteS(
            "SELECT  id_manufacturer AS id FROM "._DB_PREFIX_."manufacturer WHERE name = '".$name."'"
        );
        if (empty($res)) {
            Db::getInstance()->execute(
                "INSERT INTO "._DB_PREFIX_."manufacturer ( `name`,  `active`,`date_add`, `date_upd`) VALUES ( '".$name."', 1 , CURRENT_TIMESTAMP(),  CURRENT_TIMESTAMP() )"
            );
            $res = Db::getInstance()->ExecuteS(
                "SELECT id_manufacturer AS id FROM "._DB_PREFIX_."manufacturer WHERE name = '".$name."'"
            );
            if (empty($res)) {
                throw new Exception("Accessory Import Exception : No Manufacturer");
            } else {
                $id_shop = (int)Context::getContext()->shop->id;
                $language_id = (int)Context::getContext()->language->id;
                Db::getInstance()->execute("INSERT INTO "._DB_PREFIX_."manufacturer_shop  VALUES ( ".$res[0]['id'].", ".$id_shop.")");
                Db::getInstance()->execute(
                    "INSERT INTO "._DB_PREFIX_."manufacturer_lang (`id_manufacturer`, `id_lang`) VALUES ( ".$res[0]['id'].", ".$language_id.")"
                );
            }
        }
        return $res[0]['id'];
    }

    protected function getManufacturer()
    {
        $name = $this->getManufacturerName();

        if (empty($name)) {
            return 0;
        }

        $res = Db::getInstance()->ExecuteS(
            "SELECT id_manufacturer AS id FROM "._DB_PREFIX_."manufacturer WHERE name = '".(string)$this->getManufacturerName()."'"
        );
        if (empty($res)) {
            //INSERT INTO `ps_manufacturer_lang` (`id_manufacturer`, `id_lang`, `description`, `short_description`, `meta_title`, `meta_keywords`, `meta_description`) VALUES ('9', '1', NULL, NULL, NULL, NULL, NULL);

            Db::getInstance()->execute(
                "INSERT INTO "._DB_PREFIX_."manufacturer ( `name`,  `active`,`date_add`, `date_upd`) VALUES (  '".(string)$this->getManufacturerName()."',  1 , CURRENT_TIMESTAMP(),  CURRENT_TIMESTAMP() )"
            );
            $res = Db::getInstance()->ExecuteS(
                "SELECT id_manufacturer AS id FROM "._DB_PREFIX_."manufacturer WHERE name = '".(string)$this->getManufacturerName()."'"
            );
            if (empty($res)) {
                throw new Exception("Accessory Import Exception : No Manufacturer");
            } else {
                $id_shop = (int)Context::getContext()->shop->id;
                $language_id = (int)Context::getContext()->language->id;
                Db::getInstance()->execute("INSERT INTO "._DB_PREFIX_."manufacturer_shop  VALUES ( ".$res[0]['id'].", ".$id_shop.")");
                Db::getInstance()->execute(
                    "INSERT INTO "._DB_PREFIX_."manufacturer_lang (`id_manufacturer`, `id_lang`) VALUES ( ".$res[0]['id'].", ".$language_id.")"
                );
            }
        }
        return $res[0]['id'];
    }
    
    protected function getQuantity()
    {
        return (string)$this->product->quantity;
    }

    protected function getImages()
    {
        $img_arr = array();
        $url = $this->product->url_image;
        $img_arr[]= $url;
        return $img_arr;
    }

    protected function getTags()
    {
        $meta_keywords = $this->product->meta_keywords;
        $meta_keywords = preg_replace('/[!<;>;?=+#"°{}_$%]/ui', ' ', $meta_keywords);

        $str_tags = str_replace(" ", ",", $meta_keywords);
        $tags = explode(",", $str_tags);
        $tags = array_merge(array_filter($tags));

        return (array)$tags;
    }
    
    protected function getCategoryDefault()
    {
        $default_category = Db::getInstance()->getValue(
            'SELECT id_category_ps
            FROM `'._DB_PREFIX_.'life365_category`
            WHERE id_category_external = '.(int)$this->product->local_category
        );
        if (empty($default_category)) {
            $default_category = Configuration::get('life365_default_category');
        }

        return $default_category;
    }

    /**
    * This method check if current product already exist
    * you can implement any logic to check if item already exist.
    * only thing is that if you want to add item return 0 otherwise id_product
    * Here I am checking in a import table and return id_product if it is exist
    *
    *
    *
    */
    protected function ifExist()
    {
        $id_product=0;

        //first check in mapping table
        $sql = 'SELECT id_product_ps FROM '._DB_PREFIX_.'life365_product WHERE id_product_external = '.(int)$this->product->id;
        $res = Db::getInstance()->getRow($sql);
        if ($res) {
            $id_product = $res['id_product_ps'];
            if (!Product::existsInDatabase((int)($id_product), 'product')) {
                $t_sql = 'DELETE FROM '._DB_PREFIX_.'life365_product WHERE id_product_ps = '.(int)$id_product;
                Db::getInstance()->execute($t_sql);
                $id_product=0;
            }
        }

        return $id_product;
    }
    
    protected function ifExistId($productId)
    {
        $id_product=0;

        //first check in mapping table
        $sql = 'SELECT id_product_ps FROM '._DB_PREFIX_.'life365_product WHERE id_product_external = '.(int)$productId;
        $res = Db::getInstance()->getRow($sql);
        if ($res) {
            $id_product = $res['id_product_ps'];
            if (!Product::existsInDatabase((int)($id_product), 'product')) {
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
    protected function afterAdd()
    {
        if (!$this->ifExist()) {
            $t_sql = 'INSERT INTO `'._DB_PREFIX_.'life365_product` (`id_product_external`, `date_import`, `id_product_ps`, `version`)';
            $t_sql .= ' VALUES ';
            $t_sql .= '('.(int)$this->product->id.', CURRENT_TIMESTAMP, '.(int)$this->getProductID().', '.(int)$this->product->version.')';
            Db::getInstance()->execute($t_sql);
        } else {
            $t_sql = 'UPDATE `'._DB_PREFIX_.'life365_product` SET `version` = '.(int)$this->product->version.' WHERE `id_product_external` = '.(int)$this->product->id.';';
            Db::getInstance()->execute($t_sql);
        }
        parent::afterAdd();
    }
}
