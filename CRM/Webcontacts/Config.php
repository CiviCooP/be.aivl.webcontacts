<?php

/**
 * Class following singleton pattern for specific extension config
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 21 Jul 2016
 * @license AGPL-3.0
 * @link https://civicoop.plan.io/projects/aivl-civicrm-ontwikkeling-2016/wiki/Contact_Processing_from_Petition
 */
class CRM_Webcontacts_Config {

  private static $_singleton;

  protected $_petitionActivityTypeId = NULL;
  protected $_petitionGroupId = NULL;
  protected $_completedActivityStatusId = NULL;
  protected $_aivlContactId = NULL;

  /**
   * CRM_Webcontacts_Config constructor.
   *
   * @throws Exception when not able to find any domain contact id
   */
  function __construct() {
    $this->setPetitionActivityTypeId();
    $this->setCompletedActivityStatusId();
    $this->setPetitionGroupId();
    try {
      $this->_aivlContactId = civicrm_api3('Contact', 'getvalue', array(
        'contact_type' => 'Organization',
        'legal_name' => 'Amnesty International Vlaanderen VZW',
        'options' => array('limit' => 1),
        'return' => 'id',
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      // use domain contact_id if nothing found
      try {
        $this->_aivlContactId = civicrm_api3('Domain', 'getvalue', array(
          'return' => "contact_id",
          'options' => array('limit' => 1),
        ));
      }
      catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not find a valid domain contact id in '.__METHOD__.
          ' (extension be.aivl.webcontacts)');
      }
    }
  }

  /**
   * Getter for AIVL contact id
   *
   * @return array|null
   */
  public function getAivlContactId() {
    return $this->_aivlContactId;
  }

  /**
   * Getter for completed activity status id
   *
   * @return integer
   * @access protected
   */
  public function getCompletedActivityStatusId() {
    return $this->_completedActivityStatusId;
  }

  /**
   * Getter for petition activity type id
   *
   * @return integer
   * @access protected
   */
  public function getPetitionActivityTypeId() {
    return $this->_petitionActivityTypeId;
  }

  /**
   * Getter for petition group id
   *
   * @return integer
   * @access protected
   */
  public function getPetitionGroupId() {
    return $this->_petitionGroupId;
  }

  /**
   * Method to set the petition activity type id (and create if not exists)
   *
   * @access private
   * @throws Exception when error from create API
   */
  private function setPetitionActivityTypeId() {
    $params = array(
      'option_group_id' => 'activity_type',
      'name' => 'petition_signed',
      'return' => 'value'
    );
    try {
      $this->_petitionActivityTypeId = civicrm_api3('OptionValue', 'getvalue', $params);
    } catch (CiviCRM_API3_Exception $ex) {
      $params['label'] = 'Petition Signed';
      $params['description'] = 'Activity Type used when petition form signed';
      $params['is_reserved'] = 1;
      try {
        $created = civicrm_api3('OptionValue', 'create', $params);
        $this->_petitionActivityTypeId = $created['id'];
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not create Activity Type for Petition Signed in '.__METHOD__
          .', contact your system administrator. Error message from API OptionValue create: '.$ex->getMessage());
      }
    }
  }

  /**
   * Method to set the completed activity status id
   *
   * @access private
   */
  private function setCompletedActivityStatusId() {
    $params = array(
      'option_group_id' => 'activity_status',
      'name' => 'Completed',
      'return' => 'value'
    );
    try {
      $this->_completedActivityStatusId = civicrm_api3('OptionValue', 'getvalue', $params);
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Method to set the petition group id (and create if not exists)
   *
   * @access private
   * @throws Exception when error from create API
   */
  private function setPetitionGroupId() {
    $params = array('name' => 'petition_form_signed', 'return' => 'id');
    try {
      $this->_petitionGroupId = civicrm_api3('Group', 'getvalue', $params);
    } catch (CiviCRM_API3_Exception $ex) {
      $params['title'] = 'Petition Form Signed';
      $params['description'] = 'Group of contact that signed a petition form and were not deduplicated yet';
      $params['group_type'] = CRM_Core_DAO::VALUE_SEPARATOR.'2'.CRM_Core_DAO::VALUE_SEPARATOR;
      try {
        $created = civicrm_api3('Group', 'create', $params);
        $this->_petitionGroupId = $created['id'];
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not create Group Petition Form Signed in '.__METHOD__
          .', contact your system administrator. Error message from API Group create: '.$ex->getMessage());
      }
    }
  }

  /**
   * Singleton method
   *
   * @return CRM_Webcontacts_Config
   * @access public
   * @static
   */
  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Webcontacts_Config();
    }
    return self::$_singleton;
  }
}