<?php

/**
 * Class to process Petition webform
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 20 Jul 2016
 * @license AGPL-3.0
 * @link https://civicoop.plan.io/projects/aivl-civicrm-ontwikkeling-2016/wiki/Contact_Processing_from_Petition
 */
class CRM_Webcontacts_Petition extends CRM_Webcontacts_WebformHandler {

  private $_contactId = NULL;
  private $_campaignId = NULL;

  /**
   * Implementation of processSubmission for Petition Webform
   *
   * @throws Exception when error from API
   */
  function processSubmission() {

    if ($this->validSubmissionData()) {
      // match contact with getorcreate API action from extension de.systopia.xcm
      $params = $this->extractContactParamsFromWebform();
      try {
        $matched = civicrm_api3('Contact', 'getorcreate', $params);
        $this->_contactId = $matched['id'];
        $this->validateCampaign();
        $this->addPetitionActivity();
        $this->addToPetitionGroup();
        $this->_logger->LogMessage('Success', 'Successfully processed webform submission with data '
        .implode(';', $params).' and campaign_id '.$this->_campaignId);

      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Could not match a contact using API Contact getorcreate with params: '
          .implode(';', $params).' in '.__METHOD__.', no further processing of petition. Error message from Contact 
          getorcreate: '.$ex->getMessage());
      }
    }
  }

  /**
   * Method to check the campaign
   *
   * @access private
   */
  private function validateCampaign() {
    try {
      $countCampaign = civicrm_api3('Campaign', 'getcount', array('id' => $this->_campaignId));
      if ($countCampaign == 0) {
        $this->_logger->logMessage('Warning', 'Could not find campaign with id '.$this->_campaignId
          .' in '.__METHOD__.'. Activity will be created but not linked to campaign');
      }
    } catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Warning', 'Could not find campaign with id '.$this->_campaignId
        .'. Activity will be created but not linked to campaign');
    }
  }

  /**
   * Method to retrieve the params for api getorcreate from the webform data
   *
   * @return array $result
   */
  private function extractContactParamsFromWebform () {
    $result = array();
    foreach ($this->_webformData['data'] as $dataKey => $dataValues) {
      if ($dataValues['field_key'] == 'petition_first_name') {
        $result['first_name'] = $dataValues['field_value'][0];
      }
      if ($dataValues['field_key'] == 'petition_last_name') {
        $result['last_name'] = $dataValues['field_value'][0];
      }
      if ($dataValues['field_key'] == 'petition_email') {
        $result['email'] = $dataValues['field_value'][0];
      }
      if ($dataValues['field_key'] == 'petition_birth_date') {
        $result['birth_date'] = date('Y-m-d', strtotime($dataValues['field_value'][0]));
      }
    }
    return $result;
  }

  /**
   * Method to check if the submission data has mandatory data elements
   *
   * @access private
   * @return bool
   */
  private function validSubmissionData() {
    if (!isset($this->_webformData['data']) || empty($this->_webformData['data'])) {
      return FALSE;
    }
    $mandatoryElements = array('petition_first_name', 'petition_last_name', 'petition_email', 'petition_campaign_id');
    $receivedElements = array();
    foreach ($this->_webformData['data'] as $dataKey => $dataValues) {
      if (in_array($dataValues['field_key'], $mandatoryElements)) {
        $receivedElements[$dataValues['field_key']] = $dataKey;
        // store campaign id in class property
        if ($dataValues['field_key'] == 'petition_campaign_id') {
          $this->_campaignId = $dataValues['field_value'][0];
        }
      }
    }
    foreach ($mandatoryElements as $mandatoryElement) {
      if (!isset($receivedElements[$mandatoryElement])) {
        $this->_logger->logMessage('Error', 'Missing mandatory element '.$mandatoryElement.' in webform '
          .$this->_webformData['webform_title']);
        return FALSE;
      }
    }
    foreach ($receivedElements as $receivedName => $receivedKey) {
      if (empty($this->_webformData['data'][$receivedKey]['field_value'])) {
        $this->_logger->logMessage('Error', 'Empty mandatory element '.$receivedName.' in webform '
          .$this->_webformData['webform_title']);
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Method to add a petition activity to the found contact
   *
   * @access private
   */
  private function addPetitionActivity() {
    $config = CRM_Webcontacts_Config::singleton();
    $activityData = array(
      'source_contact_id' => 35,
      'activity_type_id' => $config->getPetitionActivityTypeId(),
      'status_id' => $config->getCompletedActivityStatusId(),
      'target_contact_id' => $this->_contactId,
      'campaign_id' => $this->_campaignId,
      'activity_date_time' => date('Y-m-d H:i:s')
    );
    if ($this->petitionActivityExists($activityData)) {
      $this->_logger->logMessage('Warning', 'Petition activity with data ' . implode(';', $activityData)
        . ' already exists, not duplicated');
    } else {
      $this->createActivity($activityData);
    }
  }

  /**
   * Method to check if there is already a current revision active activity with incoming data
   *
   * @param array $activityData
   * @return bool
   */
  private function petitionActivityExists($activityData) {
    $activityData['is_current_revision'] = 1;
    $activityData['is_deleted'] = 0;
    $activityData['is_test'] = 0;
    $countActivity = civicrm_api3('Activity', 'getcount', $activityData);
    if ($countActivity == 0) {
      return FALSE;
    } else {
      return TRUE;
    }
  }
  /**
   * Method to add a contact to the petition group
   *
   * @access private
   */
  private function addToPetitionGroup() {
    $config = CRM_Webcontacts_Config::singleton();
    $this->addContactToGroup($config->getPetitionGroupId(), $this->_contactId);
  }
}