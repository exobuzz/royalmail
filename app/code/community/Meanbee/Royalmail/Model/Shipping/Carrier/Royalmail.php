<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@meanbee.com so we can send you a copy immediately.
 *
 * @category   Meanbee
 * @package    Meanbee_Royalmail
 * @copyright  Copyright (c) 2008 Meanbee Internet Solutions (http://www.meanbee.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Meanbee_Royalmail_Model_Shipping_Carrier_Royalmail
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    // Holds the royalmail lib method class
    private $calculateMethodClass;

    // Holds the royal mail lib data class
    private $dataClass;

    // The carrier code
    protected $_code = 'royalmail';

    /**
     * Requires and constructs the needed library files from the
     * royal mail php library. These methods are then used in the
     * logic of the extension.
     *
     * Meanbee_Royalmail_Model_Shipping_Carrier_Royalmail constructor.
     */
    public function __construct()
    {
        require_once(Mage::getBaseDir('lib') . '/Meanbee/RoyalmailPHPLibrary/Src/CalculateMethod.php');
        require_once(Mage::getBaseDir('lib') . '/Meanbee/RoyalmailPHPLibrary/Src/Data.php');

        $this->calculateMethodClass = new Meanbee_RoyalmailPHPLibrary_CalculateMethod();
        $this->dataClass = new Meanbee_RoyalmailPHPLibrary_Data(
            $this->calculateMethodClass->_csvCountryCode,
            $this->calculateMethodClass->_csvZoneToDeliverMethod,
            $this->calculateMethodClass->_csvDeliveryMethodMeta,
            $this->calculateMethodClass->_csvDeliveryToPrice,
            $this->calculateMethodClass->_csvCleanNameToMethod,
            $this->calculateMethodClass->_csvCleanNameMethodGroup
        );
    }

    /**
     * Collects the rates from the royal mail library and creates a request
     * to be returned and then displayed. This is the main logic of this
     * extension.
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     *
     * @return bool|Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {

        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $freeBoxes = 0;
        $removeWeight = 0;
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getFreeShipping() && !$item->getProduct()->getTypeInstance()->isVirtual()) {
                    $freeBoxes += $item->getQty();
                    $removeWeight += $item->getWeight() * $item->getQty();
                }
            }
        }
        $this->setFreeBoxes($freeBoxes);

        $result = Mage::getModel('shipping/rate_result');

        $allowedMethods = $this->getAllowedMethods();
        if (empty($allowedMethods) == false) {

            $data = $request->getData();

            $dataClass = Mage::helper('royalmail');
            $dataClass->setWeightUnit($this->getConfigData('weight_unit'));
            $dataClass->setNegativeWeight($removeWeight);
            $dataClass->_setWeight($data['package_weight']);

            $calculatedMethods = $this->calculateMethodClass->getMethods($data['dest_country_id'],
                0, $dataClass->_getWeight());

            // Config check to remove small or medium parcel size based on the
            // config value set in the admin panel
            if ($dataClass->_getWeight() <= 2) {
                if (Mage::getStoreConfig('carriers/royalmail/parcel_size') == Meanbee_Royalmail_Model_Parcelsize::SMALL ||
                    Mage::getStoreConfig('carriers/royalmail/parcel_size') == ""
                ) {
                    foreach ($calculatedMethods as $key => $value) {
                        if ($value->size == 'MEDIUM') {
                            unset($calculatedMethods[$key]);

                        }
                    }
                }
                if (Mage::getStoreConfig('carriers/royalmail/parcel_size') == Meanbee_Royalmail_Model_Parcelsize::MEDIUM) {
                    foreach ($calculatedMethods as $key => $value) {
                        if ($value->size == 'SMALL') {
                            unset($calculatedMethods[$key]);

                        }
                    }
                }

                $websiteId = Mage::getModel('core/store')->load($request->getStoreId())->getWebsiteId();
                $country = $data['dest_country_id'];

                // temporarily fix country exclusions for delivery methods until library is fixed
                $excl_tracked_signed = array(
                    "AF", "AL", "DZ", "AS", "AO", "AI", "AQ", "AG", "AM", "AW", "AU", "AZ",
                    "BS", "BH", "BD", "BB", "BZ", "BJ", "BM", "BT", "BO", "BA", "BW", "BV",
                    "BR", "IO", "VG", "BN", "BF", "BI", "CM", "CV", "CF", "TD", "CL", "CN",
                    "CX", "CC", "CO", "KM", "CG", "CD", "CK", "CR", "CU", "CI", "DJ", "DM",
                    "DO", "EG", "SV", "GQ", "ER", "EE", "ET", "FK", "FO", "FJ", "GF", "PF",
                    "TF", "GA", "GM", "GE", "GH", "GL", "GD", "GP", "GU", "GT", "GG", "GN",
                    "GW", "GY", "HT", "HM", "HN", "IN", "IR", "IQ", "IM", "IL", "JM", "JE",
                    "JO", "KZ", "KE", "KI", "KW", "KG", "LA", "LB", "LS", "LR", "LY", "MO",
                    "MK", "MG", "MW", "MV", "ML", "MH", "MQ", "MR", "MU", "YT", "MX", "FM",
                    "MC", "MN", "ME", "MS", "MA", "MZ", "MM", "NA", "NR", "NP", "AN", "NC",
                    "NI", "NE", "NG", "NU", "NF", "KP", "MP", "NO", "OM", "PK", "PW", "PS",
                    "PA", "PG", "PY", "PE", "PH", "PN", "PR", "QA", "RU", "RW", "RE", "BL",
                    "SH", "KN", "LC", "MF", "PM", "VC", "WS", "SA", "SN", "SC", "SL", "SB",
                    "SO", "ZA", "GS", "LK", "SD", "SR", "SJ", "SZ", "SY", "ST", "TW", "TJ",
                    "TZ", "TL", "TG", "TK", "TO", "TN", "TM", "TC", "TV", "UM", "VI", "UG",
                    "UA", "GB", "UY", "UZ", "VU", "VE", "VN", "WF", "EH", "YE", "ZM", "ZW",
                    "AX"
                );

                $excl_tracked = array(
                    "AF", "AL", "DZ", "AS", "AO", "AI", "AQ", "AG", "AR", "AM", "AW", "AZ",
                    "BS", "BH", "BD", "BB", "BY", "BZ", "BJ", "BM", "BT", "BO", "BA", "BW",
                    "BV", "IO", "VG", "BN", "BG", "BF", "BI", "KH", "CM", "CV", "KY", "CF",
                    "TD", "CL", "CN", "CX", "CC", "CO", "KM", "CG", "CD", "CK", "CR", "CU",
                    "CZ", "CI", "DJ", "DM", "DO", "EC", "EG", "SV", "GQ", "ER", "ET", "FK",
                    "FO", "FJ", "GF", "PF", "TF", "GA", "GM", "GE", "GH", "GI", "GR", "GL",
                    "GD", "GP", "GU", "GT", "GG", "GN", "GW", "GY", "HT", "HM", "HN", "ID",
                    "IR", "IQ", "IM", "JM", "JP", "JE", "JO", "KZ", "KE", "KI", "KW", "KG",
                    "LA", "LS", "LR", "LY", "MO", "MK", "MG", "MW", "MV", "ML", "MH", "MQ",
                    "MR", "MU", "YT", "MX", "FM", "MD", "MC", "MN", "ME", "MS", "MA", "MZ",
                    "MM", "NA", "NR", "NP", "AN", "NC", "NI", "NE", "NG", "NU", "NF", "KP",
                    "MP", "NO", "OM", "PK", "PW", "PS", "PA", "PG", "PY", "PE", "PH", "PN",
                    "PR", "QA", "RO", "RW", "RE", "BL", "SH", "KN", "LC", "MF", "PM", "VC",
                    "WS", "SA", "SN", "RS", "SC", "SL", "SB", "SO", "ZA", "GS", "LK", "SD",
                    "SR", "SJ", "SZ", "SY", "ST", "TW", "TJ", "TZ", "TH", "TL", "TG", "TK",
                    "TO", "TT", "TN", "TM", "TC", "TV", "UM", "VI", "UG", "UA", "AE", "GB",
                    "UY", "UZ", "VU", "VE", "VN", "WF", "EH", "YE", "ZM", "ZW", "AX"
                );

                $excl_signed = array(
                    "AD", "AR", "AT", "BE", "BG", "KH", "CA", "KY", "HR", "CY", "CZ", "DK",
                    "EC", "FR", "DE", "GI", "GR", "HK", "HU", "IS", "ID", "IE", "IT", "JP",
                    "LV", "LI", "LT", "LU", "MY", "MT", "MD", "NL", "NZ", "PL", "PT", "RO",
                    "SM", "RS", "SG", "SK", "SI", "SO", "KR", "ES", "SE", "CH", "TH", "TT",
                    "TR", "AE", "US", "VA"
                );

                foreach ($calculatedMethods as $key => $value) {
                    // no 9am service to Guernsey
                    if (in_array($country, array('GG')) &&
                        strpos($value->shippingMethodName, "9AM") !== false) {
                        unset($calculatedMethods[$key]);
                    }

                    // tracked & signed inclusions
                    if (in_array($country, $excl_tracked_signed) &&
                        strpos($value->shippingMethodName, "TRACKED_AND_SIGNED") !== false) {
                        unset($calculatedMethods[$key]);
                    }

                    // tracked delivery inclusions
                    if (in_array($country, $excl_tracked) &&
                        strpos($value->shippingMethodName, "TRACKED") !== false &&
                        strpos($value->shippingMethodName, "SIGNED") === false) {
                        unset($calculatedMethods[$key]);
                    }

                    // signed delivery inclusions
                    if (in_array($country, $excl_signed) &&
                        strpos($value->shippingMethodName, "TRACKED") === false &&
                        strpos($value->shippingMethodName, "SIGNED") !== false) {
                        unset($calculatedMethods[$key]);
                    }

                }

                // rules for seedsman website only
                if ($websiteId == 1 && ! Mage::app()->getStore()->isAdmin()) {
                    foreach ($calculatedMethods as $key => $value) {
                        // no tracked & signed for Brazil and Canada
                        if (in_array($country, array('BR', 'CA')) &&
                            strpos($value->shippingMethodName, "TRACKED_AND_SIGNED") !== false) {
                            unset($calculatedMethods[$key]);
                        }

                        // no tracked & signed or signed delivery to Argentina, Israel and South Africa
                        if (in_array($country, array('AR', 'IL', 'ZA')) &&
                            strpos($value->shippingMethodName, "SIGNED") !== false) {
                            unset($calculatedMethods[$key]);
                        }

                        // only allow international standard to certain countries
                        if (! in_array($country, array('AU', 'AR', 'CA', 'IL', 'RE', 'US', 'ZA')) &&
                            strpos($value->shippingMethodName, "INTERNATIONAL_STANDARD") !== false) {
                            unset($calculatedMethods[$key]);
                        }

                        // subtract 1.50 from first class signed for
                        if (strpos($value->shippingMethodName, "UK_CONFIRMED_ROYAL_MAIL_SIGNED_FOR_FIRST_CLASS") !== false) {
                            $calculatedMethods[$key]->methodPrice -= 1.50;
                        }
                    }
                } else if ($websiteId == 2 && ! Mage::app()->getStore()->isAdmin()) {
                    foreach ($calculatedMethods as $key => $value) {
                        # no international standard for wholesale for US
                        if (in_array($country, array('US')) &&
                            strpos($value->shippingMethodName, "INTERNATIONAL_STANDARD") !== false) {
                            unset($calculatedMethods[$key]);
                        }
                    }
                }

            }

            $freeShown = false;

            uasort($calculatedMethods, function($a, $b) {
                return $a->methodPrice > $b->methodPrice;
            });

            foreach ($calculatedMethods as $methodItem) {
                foreach ($allowedMethods as $allowedMethod) {
                    if ($allowedMethod[1] == $methodItem->shippingMethodNameClean) {

                        // add warning to international standard name
                        $methodItem->shippingMethodNameClean = str_replace("International Standard", "International Standard (Formerly Airmail - NO TRACKING NUMBER) ", $methodItem->shippingMethodNameClean);

                        $method = Mage::getModel('shipping/rate_result_method');

                        $method->setCarrier($this->_code);
                        $method->setCarrierTitle($this->getConfigData('title'));

                        $method->setMethod($methodItem->shippingMethodName);
                        $method->setMethodTitle($methodItem->shippingMethodNameClean);

                        if (!$freeShown && ($request->getFreeShipping() === true || $request->getPackageQty() == $this->getFreeBoxes())) {
                            $price = '0.00';
                            $freeShown = true;
                        } else {
                            $price = $this->_performRounding($this->getFinalPriceWithHandlingFee($methodItem->methodPrice));
                            $currency = Mage::getModel('core/store')->load($request->getStoreId())->getBaseCurrencyCode();
                            if ( $currency != "GBP" ) {
                                $price = Mage::helper('directory')->currencyConvert($price, "GBP", $currency);
                            }

                        }

                        $method->setPrice($price);
                        $method->setCost($price);

                        $result->append($method);
                    }
                }

            }
        }

        if (count($result->getAllRates()) == 0) {
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));
            $result->append($error);

            return $result;
        } else {
            return $result;
        }
    }

    /**
     * Performs rounding of the allowed methods based on the set config
     * option in the admin area
     *
     * @param $number
     *
     * @return float
     */
    protected function _performRounding($number)
    {
        $old = $number;

        switch ($this->getConfigData('rounding_rule')) {
            case 'pound':
                $number = round($number);
                break;
            case 'pound-up':
                $number = ceil($number);
                break;
            case 'pound-down':
                $number = floor($number);
                break;
            case 'fifty':
                $number = round($number * 2) / 2;
                break;
            case 'fifty-up':
                $number = ceil($number * 2) / 2;
                break;
            case 'fifty-down':
                $number = floor($number * 2) / 2;
                break;
        }

        // Incase it rounds to 0
        if ($number == 0) {
            $number = ceil($old);
        }

        return $number;
    }

    /**
     * Gets the methods selected in the admin area of the extension
     * to ensure that not allowed methods can be removed in the collect
     * rates method
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = array();
        $allMethods = $this->getMethods();
        foreach ($allowed as $key) {
            foreach ($allMethods as $method) {
                if ($method[0] == $key) {
                    $arr[] = $method;
                }
            }
        }
        return $arr;
    }

    /**
     * Gets the clean method names from the royal mail library data
     * class. These names link directly to method names, but are used
     * to ensure that duplicates are not created as similar names
     * exists for multiple methods.
     *
     * @return array
     */
    public function getMethods()
    {
        $allMethods = $this->dataClass->mappingCleanNameMethodGroup;

        return $allMethods;
    }
}