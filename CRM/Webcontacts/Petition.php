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
        $this->addPetitionActivity();
        $this->addToPetitionGroup();
      } catch (CiviCRM_API3_Exception $ex) {
        CRM_Core_Error::debug_log_message('Could not match a contact using API Contact getorcreate in '.__METHOD__
          .', no further processing of petition. (extension be.aivl.webcontacts)');
      }
    }
  }

  /**
   * Method to retrieve the campaign title
   *
   * @access private
   * @return array|bool
   */
  private function getCampaignTitle() {
    try {
      return civicrm_api3('Campaign', 'getvalue', array(
        'id' => $this->_campaignId,
        'return' => 'title'));
    } catch (CiviCRM_API3_Exception $ex) {
      CRM_Core_Error::debug_log_message('Could not find campaign with id '.$this->_campaignId
        .'. Activity will be created but not linked to campaign (extension be.aivl.webcontacts)');
      return FALSE;
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
        $result['first_name'] = trim($dataValues['field_value'][0]);
      }
      if ($dataValues['field_key'] == 'petition_last_name') {
        $result['last_name'] = trim($dataValues['field_value'][0]);
      }
      if ($dataValues['field_key'] == 'petition_email') {
        $result['email'] = trim($dataValues['field_value'][0]);
      }
      if ($dataValues['field_key'] == 'petition_birth_date') {
        $result['birth_date'] = date('Y-m-d', strtotime($dataValues['field_value'][0]));
      }
      if ($dataValues['field_key'] == 'petition_keep_me_informed') {
        $result['petition_keep_me_informed'] = $dataValues['field_value'][0];
      }
      if ($dataValues['field_key'] == 'petition_group_ids') {
        $result['petition_group_ids'] = $dataValues['field_value'][0];
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
        if ($dataValues['field_key'] == 'petition_email') {
          if (!filter_var($dataValues['field_value'][0], FILTER_VALIDATE_EMAIL)) {
            CRM_Core_Error::debug_log_message($dataValues['field_value'][0]
              .' is not a valid email, petition signature ignored! (extension be.aivl.webcontacts)');
          }
        }
      }
    }
    foreach ($mandatoryElements as $mandatoryElement) {
      if (!isset($receivedElements[$mandatoryElement])) {
        CRM_Core_Error::debug_log_message('Missing mandatory element '.$mandatoryElement.' in webform '
          .$this->_webformData['webform_title'].' (extension be.aivl.webcontacts)');
        return FALSE;
      }
    }
    foreach ($receivedElements as $receivedName => $receivedKey) {
      if (empty($this->_webformData['data'][$receivedKey]['field_value'])) {
        CRM_Core_Error::debug_log_message('Empty mandatory element '.$receivedName.' in webform '
          .$this->_webformData['webform_title'].' (extension be.aivl.webcontacts)');
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
      'source_contact_id' => $config->getAivlContactId(),
      'activity_type_id' => $config->getPetitionActivityTypeId(),
      'status_id' => $config->getCompletedActivityStatusId(),
      'target_contact_id' => $this->_contactId,
      'activity_date_time' => date('Y-m-d H:i:s')
    );
    // add campaign title to subject
    $campaignTitle = $this->getCampaignTitle();
    if ($campaignTitle) {
      $activityData['subject'] = $campaignTitle;
      $activityData['campaign_id'] = $this->_campaignId;
    }
    if ($this->petitionActivityExists($activityData)) {
      CRM_Core_Error::debug_log_message('Petition activity for contact '.$this->_contactId.', campaign '
        .$this->_campaignId.' already exists, not duplicated');
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
    $query = 'SELECT COUNT(*) FROM civicrm_activity a
LEFT JOIN civicrm_activity_contact src ON a.id = src.activity_id AND src.record_type_id = %1
LEFT JOIN civicrm_activity_contact tar ON a.id = tar.activity_id AND tar.record_type_id = %2
WHERE a.activity_type_id = %3 AND a.campaign_id = %4 AND a.is_current_revision = %5
  AND a.is_deleted = %6 AND a.is_test = %6 AND src.contact_id = %7 AND tar.contact_id = %8';
    $params = array(
      1 => array(2, 'Integer'),
      2 => array(3, 'Integer'),
      3 => array($activityData['activity_type_id'], 'Integer'),
      4 => array($activityData['campaign_id'], 'Integer'),
      5 => array(1, 'Integer'),
      6 => array(0, 'Integer'),
      7 => array($activityData['source_contact_id'], 'Integer'),
      8 => array($activityData['target_contact_id'], 'Integer')
    );
    $countActivity = CRM_Core_DAO::singleValueQuery($query, $params);
    if ($countActivity == 0) {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * Method to add a contact to the petition groups
   *
   * @access private
   */
  private function addToPetitionGroup() {
    $config = CRM_Webcontacts_Config::singleton();
    $petitionGroupId = $config->getPetitionGroupId();
    if (!empty($petitionGroupId)) {
      $this->addContactToGroup($petitionGroupId, $this->_contactId);
    }
  }
}