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
 * @copyright  Copyright (c) 2014 Meanbee Internet Solutions (http://www.meanbee.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Meanbee_Royalmail_Model_Shipping_Carrier_Royalmail_Internationalstandard
    extends Meanbee_Royalmail_Model_Shipping_Carrier_Royalmail_Abstract {


    public function getRates() {
        $_helper = Mage::helper('royalmail');
        $country = $this->_getCountry();
        $worldZone = $_helper->getWorldZone($country);


        // we want to allow "international standard" for Australia, Canada, Réunion, and Israel. If the country
        // isn't one of these we are not calling from a parent class and we are in the frontend then return nothing.
        $class = get_parent_class($this);
        if ($country != 'AU' &&
            $country != 'AR' &&
            $country != 'CA' &&
            $country != 'IL' &&
            $country != 'RE' &&
            $country != 'US' &&
            $country != 'ZA' &&
            $class   == 'Meanbee_Royalmail_Model_Shipping_Carrier_Royalmail_Abstract'
            && Mage::getDesign()->getArea() == 'frontend') {
            return null;   
        }

        switch($worldZone) {
            case Meanbee_Royalmail_Helper_Data::WORLD_ZONE_GB:
                return null;
            case Meanbee_Royalmail_Helper_Data::WORLD_ZONE_EU:
                $rates = $this->_getEuropeRates();
                break;
            case Meanbee_Royalmail_Helper_Data::WORLD_ZONE_ONE:
                $rates = $this->_getWz1Rates();
                break;
            case Meanbee_Royalmail_Helper_Data::WORLD_ZONE_TWO:
                $rates = $this->_getWz2Rates();
                break;
            default:
                return null;
        }
        return $rates;
    }

    protected function _getEuropeRates() {
        return $this->_loadCsv('internationalstandard_europe');
    }

    protected function _getWz1Rates() {
        return $this->_loadCsv('internationalstandard_wz1');
    }

    protected function _getWz2Rates() {
        return $this->_loadCsv('internationalstandard_wz2');
    }
}
