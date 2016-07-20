<?php

/**
 * Class for Matching Rule FirstLastNameEmail
 *
 * Will look for a single contact with first_name, last_name and email
 * If it can find a single one it will return the contact id and a confidence of 0.85
 * If it can not find one it will check if it can find one where the email address is the same and the first_name and
 *    last_name combined do not differ more than 2 characters. If it does find it will return the contact_id and
 *    a confidence of 0.75.
 * If it can not find any it will return contact_id 0 and confidence 0
 *
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 22 Jun 2016
 * @license AGPL-3.0
 * @link https://civicoop.plan.io/projects/aivl-civicrm-ontwikkeling-2016/wiki/Contact_Processing_from_Petition
 * @link https://civicoop.plan.io/issues/427
 */
class CRM_Webcontacts_MatchingRules_FirstLastNameEmail extends CRM_Xcm_MatchingRule {
  
  private $_firstName = NULL;
  private $_lastName = NULL;
  private $_email = NULL;
  private $_contactType = NULL;
  private $_result = array();
  
  /**
   * Method to find a matching contact and return it
   * 
   * @param array $contactData
   * @return array $result
   */
  public function matchContact($contactData) {
    $this->_result = array('contact_id' => 0, 'confidence' => 0);
    // validate the contact data coming in and set the relevant properties
    if ($this->validateContactData($contactData)) {
      $this->findIdentical();
      if (empty($result['contact_id'])) {
        $this->findProbable();
      }
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
    $requiredParams = array('first_name', 'last_name', 'email');
    foreach ($requiredParams as $mandatory) {
      if (!isset($contactData[$mandatory]) && empty($contactData[$mandatory])) {
        return FALSE;
      }
    }
    if (isset($contactData['contact_type'])) {
      $this->_contactType = $contactData['contact_type'];
    } else {
      $this->_contactType = "Individual";
    }
    $this->_firstName = trim(stripslashes(['first_name']));
    $this->_lastName = trim(stripslashes(['last_name']));
    $this->_email = trim(stripslashes(['email']));
  }

  /**
   * Method to find a single identical contact with the same data
   */
  private function findIdentical() {
    // todo : implement pickContact helper function
    try {
      $params = array(
        'first_name' => $this->_firstName,
        'last_name' => $this->_lastName,
        'email' => $this->_email,
        'contact_type' => $this->_contactType,
        'return' => 'id'
      );
      $contactId = civicrm_api3('Contact', 'Getvalue', $params);
      if (!empty($contactId)) {
        $this->_result = array('contact_id' => $contactId, 'confidence' => (float).85);
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Method to find all contacts with email and check if any single one of them has less than
   * 3 chars difference in first and last name
   */
  private function findProbable() {
    // todo : implement pickContact helper function
    $noOfContacts = 0;
    try {
      $foundContacts = civicrm_api3('Contact', 'Getsingle', array(
        'email' => $this->_email,
        'contact_type' => $this->_contactType,
        'options' => array('limit' => 9999)));
      foreach ($foundContacts['values'] as $foundContact) {
        $matchedContact = $this->checkNamesDifference($foundContact);
        if (!empty($matchedContact)) {
          $noOfContacts++;
        }
        // only return contact_id if a single match found
        if ($noOfContacts == 1) {
          $this->_result = array('contact_id' => $matchedContact['id'], 'confidence' => (float).75);
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Method to check if first/last name combined do not have more than 2 chars difference
   * @param $foundContact
   * @return int
   */
  private function checkNamesDifference($foundContact) {
    $matchedContact = 0;
    $charsDifferent = 0;
    $foundFirstName = str_split($foundContact['first_name']);
    $foundLastName = str_split($foundContact['last_name']);
    $dataFirstName = str_split($this->_firstName);
    $dataLastName = str_split($this->_lastName);
    foreach ($foundFirstName as $elementFirstName => $firstNameChar) {
      if ($firstNameChar != $dataFirstName[$elementFirstName]) {
        $charsDifferent++;
      }
    }
    foreach ($foundLastName as $elementLastName => $lastNameChar) {
      if ($lastNameChar != $dataLastName[$elementLastName]) {
        $charsDifferent++;
      }
    }
    if ($charsDifferent <= 2) {
      $matchedContact = $foundContact['id'];
    }
    return $matchedContact;
  }
}