<?php

/**
 * AivlWebform.Process API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_aivl_webform_Process_spec(&$spec) {
  $spec['webform_title']['api.required'] = 1;
}

/**
 * AivlWebform.Process API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_aivl_webform_Process($params) {
  if (!isset($params['webform_title'])) {
    return civicrm_api3_create_error('Required param webform_title missing');
  }
  $handlerName = CRM_Webcontacts_WebformHandler::getHandler($params);
  if ($handlerName) {
    $handler = new $handlerName($params);
    $handler->processSubmission();
  }
  return civicrm_api3_create_success(1, $params, 'AivlWebform', 'Process');
}

