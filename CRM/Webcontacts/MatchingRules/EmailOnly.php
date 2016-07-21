<?php

/**
 * Class for Matching Rule EmailOnly
 *
 * Will look for all contacts with the same email address and pick on (based on XCM settings) if more are found
 *
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 22 Jun 2016
 * @license AGPL-3.0
 * @link https://civicoop.plan.io/projects/aivl-civicrm-ontwikkeling-2016/wiki/Contact_Processing_from_Petition
 * @link https://civicoop.plan.io/issues/427
 */
class CRM_Webcontacts_MatchingRules_EmailOnly extends CRM_Xcm_MatchingRule {
  
  private $_email = NULL;
  private $_contactType = NULL;
  private $_result = array();
  
  /**
   * Method to find a matching contact and return it
   * 
   * @param array $contactData
   * @param array $params
   * @return array $result
   */
  function matchContact($contactData, $params = NULL) {
    $this->_result = array('contact_id' => 0, 'confidence' => 0);
    // validate the contact data coming in and set the relevant properties
    if ($this->validateContactData($contactData)) {
      $this->findProbable();
    }
    return $this->_result;
  }

  /**
   * Method to validate the contact data coming in against the required params and set the 
   * properties if all is ok
   * 
   * @param $contactData
   * @return bool
   */
  private function validateContactData($contactData) {
    if (!isset($contactData['email']) || empty($contactData['email'])) {
      return FALSE;
    }
    if (isset($contactData['contact_type'])) {
      $this->_contactType = $contactData['contact_type'];
    } else {
      $this->_contactType = "Individual";
    }
    $this->_email = trim(stripslashes($contactData['email']));
    return TRUE;
  }

  /**
   * Method to find all contacts with email and pick one with xcm function
   */
  private function findProbable() {
    $noOfContacts = 0;
    $contactIds = array();
    try {
      $foundContacts = civicrm_api3('Contact', 'get', array(
        'email' => $this->_email,
        'contact_type' => $this->_contactType,
        'options' => array('limit' => 9999)));
      foreach ($foundContacts['values'] as $foundContact) {
        $noOfContacts++;
        $contactIds[] = $foundContact['id'];
        // only return contact_id if a single match found else use pickContact to select one based on XCM settitngs
        if ($noOfContacts == 1) {
          $this->_result = array('contact_id' => $foundContacts['id'], 'confidence' => (float).80);
        } else {
          if (!empty($contactIds)) {
            $this->_result = array('contact_id' => $this->pickContact($contactIds), 'confidence' => (float) .75);
          }
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }
}