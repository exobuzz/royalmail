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
    implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = 'royalmail';

    public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $freeBoxes = 0;
        $removeWeight = 0;
        $freeShown = false;
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

        if (count($this->getAllowedMethods()) > 0) {
            foreach ($this->getAllowedMethods() as $key => $value) {
                $obj = Mage::getModel("royalmail/shipping_carrier_royalmail_$key");

                if ($obj === false) {
                    Mage::log("Error loading royal mail: $key");
                    continue;
                }
                
                $obj->setWeightUnit($this->getConfigData('weight_unit'));

                $obj->setNegativeWeight($removeWeight);

                $cost = $obj->getCost($request);

                if ($cost !== null) {
                    $method = Mage::getModel('shipping/rate_result_method');

                    $method->setCarrier($this->_code);
                    $method->setCarrierTitle($this->getConfigData('title'));

                    $method->setMethod($key);
                    $method->setMethodTitle($value);

                    if (!$freeShown && ($request->getFreeShipping() === true || $request->getPackageQty() == $this->getFreeBoxes())) {
                        $price = '0.00';
                    } else {
                        $offset = $this->getConfigData('offset_' . $key);
                        if (is_numeric($offset)) {
                            $cost += $offset;
                            if ($cost < 0) {
                                $cost = 0;      
                            }
                        }
                        $price = $this->_performRounding($this->getFinalPriceWithHandlingFee($cost));
                        $currency = Mage::getModel('core/store')->load($request->getStoreId())->getBaseCurrencyCode();
                        if ( $currency != "GBP" ) {
                            $price = Mage::helper('directory')->currencyConvert($price, "GBP", $currency);
                        }
                    }

                    $method->setPrice($price);
                    $method->setCost($price);

                    $result->append($method);
                    
                    if ($price == '0.00') {
                        $freeShown = true;
                    }
                }
            }
        }

        return $result;
    }

    protected function _performRounding($number) {
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

    public function getAllowedMethods() {
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = array();
        foreach ($allowed as $k) {
            $method_name = $this->getMethods($k);
            if ($method_name) {
                $arr[$k] = $method_name;
            }
        }

        return $arr;
    }

    public function getMethods($name=null) {
        $codes = array(
                // To maintain backwards comparability we need to keep letter and largeletter indices the same
                'letter' => 'First Class Letter',
                'largeletter' => 'First Class Large Letter',
                'secondclassletter' => 'Second Class Letter',
                'secondclasslargeletter' => 'Second Class Large Letter',

                'secondclass' => 'Second Class Parcel',
                'secondclassrecordedsignedfor' => 'Second Class Parcel (Signed for)',

                'firstclass' => 'First Class Parcel',
                'firstclassrecordedsignedfor' => 'First Class Parcel (Signed for)',

                'specialdeliverynextday' => 'Special Delivery Guaranteed by 1pm',

                'specialdelivery9am' => 'Special Delivery Guaranteed by 9am',

                'internationalstandard' => 'International Standard (Formerly Airmail)',
                'internationaltrackedsigned' => 'International Tracked & Signed',
                'internationaltracked' => 'International Tracked',
                'internationalsigned' => 'International Signed',
                'internationaleconomy' => 'International Economy'
        );
        
        if ($name !== null) {
            if (isset($codes[$name])) {
                return $codes[$name];
            } else {
                return null;
            }
        } else {
            return $codes;
        }
    }
}
