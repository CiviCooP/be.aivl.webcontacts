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
  protected $_logger = NULL;
  /**
   * CRM_Webcontacts_WebformHandler constructor.
   * @param $webformData
   */
  function __construct($webformData) {
    $this->setWebformData($webformData);
  }

  /**
   * Setter for webform data
   *
   *@access protected
   */
  protected function setWebformData($webformData) {
    $parts = explode(' ', $webformData['webform_title']);
    $webFormTitle = implode('_', $parts);
    $this->_logger = new CRM_Webcontacts_Logger($webFormTitle);

    $this->_webformData = $webformData;
  }

  /**
   * Abstract method to process the submission
   * @return mixed
   */
  abstract public function processSubmission();

  /**
   * Method to determine which handler to use based on the params
   *
   * @param array $params
   * @throws Exception if class not found
   * @return string $className
   *
   */
  public static function getHandler($params) {
    $className = NULL;

    foreach ($params['data'] as $dataKey => $dataValue) {
      if ($dataValue['field_key'] == 'civicrm_processing_class') {
        $className = "CRM_Webcontacts_".ucfirst($dataValue['field_value'][0]);
      }
    }
    if (!class_exists($className)) {
      throw new Exception(ts('No handling class '.$className.' for webform '.$params['webform_title']
        .' defined in '.__METHOD__.' API request can not be processed.'));
    }
    return $className;
  }
  /**
   * Method to create an activity
   *
   * @param array $data
   * @return bool|CRM_Activity_BAO_Activity
   */
  protected function createActivity($data) {
    $requiredFields = array('source_contact_id', 'activity_type_id', 'status_id', 'target_contact_id');
    foreach ($requiredFields as $requiredField) {
      if (!isset($data[$requiredField]) || empty($data[$requiredField])) {
        $this->_logger->logMessage('Error', 'Required field '.$requiredField.' not present or empty, 
          will not create petition activity in '.__METHOD__.' with data '.implode(';', $data));
        return FALSE;
      }
    }
    try {
      return civicrm_api3('Activity', 'Create', $data);
    } catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Could not create activity in '.__METHOD__.' with data '.implode(';', $data)
        .' with API Activity create. Error message from API: '.$ex->getMessage());
    }
  }

  /**
   * Method to add contact to group
   *
   * @param $groupId
   * @param $contactId
   * @return bool|array
   */
  protected function addContactToGroup($groupId, $contactId) {
    if (!empty($contactId) && !empty($groupId)) {
      $params = array(
        'contact_id' => $contactId,
        'group_id' => $groupId
      );
      $group = civicrm_api3('GroupContact', 'create', $params);
      return $group;
    } else {
      $this->_logger->logMessage('Warning', 'Contact or Group empty, no contact added to group in '.__METHOD__
        .' for group_id '.$groupId.' and contact_id '.$contactId);
      return FALSE;
    }
  }
}