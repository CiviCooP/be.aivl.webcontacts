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
    $this->setWebformData($webformData);
  }

  /**
   * Setter for webform data
   *
   *@access protected
   */
  protected function setWebformData($webformData) {
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
   * @return bool|array
   */
  protected function createActivity($data) {
    $requiredFields = array('source_contact_id', 'activity_type_id', 'status_id', 'target_contact_id');
    foreach ($requiredFields as $requiredField) {
      if (!isset($data[$requiredField]) || empty($data[$requiredField])) {
        CRM_Core_Error::debug_log_message( 'Required field '.$requiredField.' not present or empty, 
          will not create petition activity in '.__METHOD__.' (extension be.aivl.webcontacts)');
        return FALSE;
      }
    }
    try {
      return civicrm_api3('Activity', 'Create', $data);
    } catch (CiviCRM_API3_Exception $ex) {
      CRM_Core_Error::debug_log_message( 'Could not create activity in '.__METHOD__.' with API Activity create (extension be.aivl.webcontacts)');
    }
  }

  /**
   * Method to add contact to group
   *
   * @param $groupId
   * @param $contactId
   * @return bool|array
   * @throws
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
      return FALSE;
    }
  }

  /**
   * Method to remove contact from group
   *
   * @param $groupId
   * @param $contactId
   * @return bool|array
   * @throws
   */
  protected function removeContactFromGroup($groupId, $contactId) {
    if (!empty($contactId) && !empty($groupId)) {
      $params = array(
        'contact_id' => $contactId,
        'group_id' => $groupId
      );
      $group = civicrm_api3('GroupContact', 'delete', $params);
      return $group;
    } else {
      return FALSE;
    }
  }
}