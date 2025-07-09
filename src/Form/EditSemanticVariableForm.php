<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EditSemanticVariableForm extends FormBase {

  protected $semanticVariableUri;

  protected $semanticVariable;

  public function getSemanticVariableUri() {
    return $this->semanticVariableUri;
  }

  public function setSemanticVariableUri($uri) {
    return $this->semanticVariableUri = $uri;
  }

  public function getSemanticVariable() {
    return $this->semanticVariable;
  }

  public function setSemanticVariable($sem) {
    return $this->semanticVariable = $sem;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_semantic_variable_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $semanticvariableuri = NULL) {
    $uri=$semanticvariableuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setSemanticVariableUri($uri_decode);

    $api = \Drupal::service('rep.api_connector');
    $svar = $api->parseObjectResponse($api->getUri($this->getSemanticVariableUri()),'getUri');
    if ($svar == NULL) {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Semantic Variable."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));
    } else {
      $this->setSemanticVariable($svar);
    }

    // dpm($this->getSemanticVariable(), 'Semantic Variable');

    $form['semantic_variable_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getSemanticVariable()->label,
    ];
    $form['semantic_variable_entity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity (required)'),
      '#default_value' => Utils::fieldToAutocomplete($this->getSemanticVariable()->entityUri,$this->getSemanticVariable()->entityLabel),
      '#autocomplete_route_name' => 'sem.semanticvariable_entity_autocomplete',
    ];
    $form['semantic_variable_attribute'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attribute (required)'),
      '#default_value' => Utils::fieldToAutocomplete($this->getSemanticVariable()->attributeUri,$this->getSemanticVariable()->attributeLabel),
      '#autocomplete_route_name' => 'sem.semanticvariable_attribute_autocomplete',
    ];
    $form['semantic_variable_in_relation_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('In Relation To (optional)'),
      '#default_value' => Utils::fieldToAutocomplete($this->getSemanticVariable()->inRelationToUri,$this->getSemanticVariable()->inRelationToLabel),
      '#autocomplete_route_name' => 'sem.semanticvariable_attribute_autocomplete',
    ];
    $form['semantic_variable_unit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unit (optional)'),
      '#default_value' => Utils::fieldToAutocomplete($this->getSemanticVariable()->unitUri,$this->getSemanticVariable()->unitLabel),
      '#autocomplete_route_name' => 'sem.semanticvariable_unit_autocomplete',
    ];
    $form['semantic_variable_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Time Restriction (optional)'),
      '#default_value' => Utils::fieldToAutocomplete($this->getSemanticVariable()->timeUri,$this->getSemanticVariable()->timeLabel),
      '#default_value' => $this->getSemanticVariable()->timeUri,
    ];

    $form['semantic_variable_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getSemanticVariable()->hasVersion,
    ];
    $form['semantic_variable_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getSemanticVariable()->comment,
    ];
    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];


    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'save') {
      if(strlen($form_state->getValue('semantic_variable_name')) < 1) {
        $form_state->setErrorByName('semantic_variable_name', $this->t('Please enter a valid name for the Semantic Variable'));
      }
      if(strlen($form_state->getValue('semantic_variable_entity')) < 1) {
        $form_state->setErrorByName('semantic_variable_entity', $this->t('Please enter a valid entity for the Semantic Variable'));
      }
      if(strlen($form_state->getValue('semantic_variable_attribute')) < 1) {
        $form_state->setErrorByName('semantic_variable_attribute', $this->t('Please enter a valid attribute for the Semantic Variable'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $entityUri = 'null';
      if ($form_state->getValue('semantic_variable_entity') != NULL && $form_state->getValue('semantic_variable_entity') != '') {
        $entityUri = Utils::uriFromAutocomplete($form_state->getValue('semantic_variable_entity'));
      }

      $attributeUri = 'null';
      if ($form_state->getValue('semantic_variable_attribute') != NULL && $form_state->getValue('semantic_variable_attribute') != '') {
        $attributeUri = Utils::uriFromAutocomplete($form_state->getValue('semantic_variable_attribute'));
      }

      $inRelationToUri = 'null';
      if ($form_state->getValue('semantic_variable_in_relation_to') != NULL && $form_state->getValue('semantic_variable_in_relation_to') != '') {
        $inRelationToUri = Utils::uriFromAutocomplete($form_state->getValue('semantic_variable_in_relation_to'));
      }

      $unitUri = 'null';
      if ($form_state->getValue('semantic_variable_unit') != NULL && $form_state->getValue('semantic_variable_unit') != '') {
        $unitUri = Utils::uriFromAutocomplete($form_state->getValue('semantic_variable_unit'));
      }

      $timeUri = 'null';
      if ($form_state->getValue('semantic_variable_time') != NULL && $form_state->getValue('semantic_variable_time') != '') {
        $timeUri = Utils::uriFromAutocomplete($form_state->getValue('semantic_variable_time'));
      }

      $semanticVariableJson = '{"uri":"'. $this->getSemanticVariable()->uri .'",'.
        '"typeUri":"'.HASCO::SEMANTIC_VARIABLE.'",'.
        '"hascoTypeUri":"'.HASCO::SEMANTIC_VARIABLE.'",'.
        '"label":"'.$form_state->getValue('semantic_variable_name').'",'.
        '"entityUri":"' . $entityUri . '",' .
        '"attributeUri":"' . $attributeUri . '",' .
        '"inRelationToUri":"' . $inRelationToUri . '",' .
        '"unitUri":"' . $unitUri . '",' .
        '"timeUri":"' . $timeUri . '",' .
        '"hasVersion":"'.$form_state->getValue('semantic_variable_version').'",'.
        '"comment":"'.$form_state->getValue('semantic_variable_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->semanticVariableDel($this->getSemanticVariable()->uri);
      $api->semanticVariableAdd($semanticVariableJson);

      \Drupal::messenger()->addMessage(t("SemanticVariable has been updated successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating Semantic Variable: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sem.edit_semantic_variable');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
