<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class HalvePriceOutOfStock extends Module
{
    public function __construct()
    {
        $this->name = 'halvepriceoutofstock';
        $this->tab = 'pricing_promotion';
        $this->version = '0.1.0';
        $this->author = 'Ali Fertah';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Halve Price for Out-of-Stock Products');
        $this->description = $this->l('Displays out-of-stock products with a reduced price based on category and percentage settings.');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install() && 
               $this->registerHook('displayProductPriceBlock') && 
               $this->installDb() &&
               $this->registerHook('header');
    
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function installDb()
    {
        if (!Configuration::hasKey('HALVEPRICEOUTOFSTOCK_CATEGORIES')) {
            Configuration::updateValue('HALVEPRICEOUTOFSTOCK_CATEGORIES', '');
        }
        if (!Configuration::hasKey('HALVEPRICEOUTOFSTOCK_PERCENTAGE')) {
            Configuration::updateValue('HALVEPRICEOUTOFSTOCK_PERCENTAGE', '50'); 
        }
        return true;
    }

    public function hookDisplayProductPriceBlock($params)
    {
        $product = $params['product'];
        $product['price'] = $this->updateProductPrice($product);
        $this->context->smarty->assign(array(
            'product_price' => $product['price']
        ));
    }
    
    

    public function updateProductPrice($product)
    {
        $rawPrice = trim($product['price']);
        $categoryPercentages = $this->getAllCategoryPercentages();
    
        if ($product['quantity'] <= 0) {
            // Get the percentage reduction for the product based on its categories
            $percentageReduction = $this->getPercentageReductionForProduct($product['id_product'], $categoryPercentages);
            // echo($percentageReduction);
            if ($percentageReduction === null) {
                return $rawPrice;
            }
    
            $cleanedPrice = preg_replace('/[^\d,.]/', '', $rawPrice);
            $cleanedPrice = str_replace(',', '.', $cleanedPrice);
            $priceFloat = (float) $cleanedPrice;
            $reducedPrice = $priceFloat - ($priceFloat * $percentageReduction / 100);
            $formattedPrice = number_format($reducedPrice, 2, '.', '');
    
            return $formattedPrice;
        } else {
            return $rawPrice;
        }
    }
    
    
    public function isProductInCategories($productId, $categoryIds)
    {
        $productCategories = Product::getProductCategories($productId);
        
        foreach ($productCategories as $categoryId) {
            if (in_array($categoryId, $categoryIds)) {
                return true;
            }
        }
        return false;
    }
    
    public function getPercentageReductionForProduct($productId, $categoryPercentages)
    {
        // Fetch product categories
        $productCategories = Product::getProductCategories($productId);
        
        // Initialize max percentage
        $maxPercentage = 0;
    
        // Loop through product categories to find the highest percentage reduction
        foreach ($productCategories as $categoryId) {
            if (isset($categoryPercentages[$categoryId])) {
                $maxPercentage = max($maxPercentage, $categoryPercentages[$categoryId]);
            }
        }
    
        // Return the highest percentage found, or null if no category percentage is found
        return $maxPercentage > 0 ? $maxPercentage : null;
    }
    


    public function getAllCategoryPercentages()
    {
        $percentages = array();
        $result = Db::getInstance()->executeS('SELECT name, value FROM ' . _DB_PREFIX_ . 'configuration WHERE name LIKE \'HALVEPRICEOUTOFSTOCK_CATEGORY_%\'');
        
        foreach ($result as $row) {
            $categoryId = str_replace('HALVEPRICEOUTOFSTOCK_CATEGORY_', '', $row['name']);
            $percentages[(int) $categoryId] = (float) $row['value'];
        }
        
        return $percentages;
    }

    public function renderForm()
    {
        $categories = $this->getCategories();
        $category_options = array();
        
        foreach ($categories as $category) {
            $category_options[] = array(
                'id_option' => $category['id_category'],
                'name' => $category['name']
            );
        }
    
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs'
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => $this->l('Select Category'),
                    'name' => 'CATEGORY_ID',
                    'options' => array(
                        'query' => $category_options,
                        'id' => 'id_option',
                        'name' => 'name'
                    ),
                    'required' => true,
                    'desc' => $this->l('Select a category for the discount.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Percentage Reduction'),
                    'name' => 'PERCENTAGE_REDUCTION',
                    'size' => 20,
                    'required' => true,
                    'desc' => $this->l('Percentage to reduce the price (0-100).')
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'name' => 'submit_' . $this->name,
                'class' => 'btn btn-default pull-right'
            )
        );
    
        $helper = new HelperForm();
    
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = $this->context->language->id;
    
        $helper->fields_value['CATEGORY_ID'] = Tools::getValue('CATEGORY_ID', '');
        $helper->fields_value['PERCENTAGE_REDUCTION'] = Tools::getValue('PERCENTAGE_REDUCTION', '');
    
        return $helper->generateForm($fields_form);
    }
    

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit_' . $this->name)) {
            $category_id = Tools::getValue('CATEGORY_ID');
            $percentage_reduction = Tools::getValue('PERCENTAGE_REDUCTION');

            if ($percentage_reduction < 0 || $percentage_reduction > 100) {
                $output .= $this->displayError($this->l('Invalid percentage reduction value.'));
            } else {
                // Save category percentage to configuration
                Configuration::updateValue('HALVEPRICEOUTOFSTOCK_CATEGORY_' . (int)$category_id, (float) $percentage_reduction);

                $output .= $this->displayConfirmation($this->l('Settings updated.'));
            }
        }

        $output .= $this->renderForm();
        $output .= $this->displayCurrentCategorySettings(); // Add this line to display the current settings

        return $output;
    }

    


    public function displayCurrentCategorySettings()
    {
        $output = '<h3>' . $this->l('Current Category Discount Settings') . '</h3>';
        $output .= '<table class="table">';
        $output .= '<thead><tr><th>' . $this->l('Category') . '</th><th>' . $this->l('Percentage Reduction') . '</th></tr></thead>';
        $output .= '<tbody>';

        // Fetch all categories
        $categories = $this->getCategories();
        // Fetch the configured percentages
        $categoryPercentages = $this->getAllCategoryPercentages();

        // Display each category with its percentage (if set)
        foreach ($categories as $category) {
            $categoryId = (int)$category['id_category'];
            $categoryName = $category['name'];
            $percentageReduction = isset($categoryPercentages[$categoryId]) ? $categoryPercentages[$categoryId] . '%' : $this->l('No discount');

            $output .= '<tr>';
            $output .= '<td>' . $categoryName . '</td>';
            $output .= '<td>' . $percentageReduction . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';

        return $output;
    }


    public function getCategories()
    {
        $id_lang = (int)$this->context->language->id; // Get the current language ID

        $categories = Db::getInstance()->executeS('
            SELECT c.id_category, cl.name
            FROM ' . _DB_PREFIX_ . 'category c
            LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON c.id_category = cl.id_category
            WHERE cl.id_lang = ' . $id_lang . '
        ');
        return $categories;
    }


    
}
