<?php


/**
 * Class for Contact API wrapper
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 22 Jun 2016
 * @license AGPL-3.0
 */
abstract class CRM_Webcontacts_WebformHandler {
  protected $_webformData = array();
  /**
   * CRM_Webcontacts_WebformHandler constructor.
   * @param $webformData
   */
  function __construct($webformData) {
    $this->_webformData = $webformData();
  }

  /**
   * Abstract method to process the submission
   * @return mixed
   */
  abstract public function processSubmission();

  /**
   * Method to determine which handler to use based on the params
   *
   * @params array
   * @throws Exception if class not found
   * @return string $className
   *
   */
  public static function getHandler($params) {
    CRM_Core_Error::debug('namespace', __NAMESPACE__);
    CRM_Core_Error::debug('method', __METHOD__);
    CRM_Core_Error::debug('class', __CLASS__);
    
    exit();
    if (!class_exists($className)) {
      throw new Exception(ts('No handling class for '.$formType.' defined in extension be.werkmetzin.wpcivi.
      Api call can not be processed.'));
    }
    return $className;
  }
}