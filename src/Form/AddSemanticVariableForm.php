<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;

class AddSemanticVariableForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_semantic_variable_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['semantic_variable_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['semantic_variable_entity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity (required)'),
      '#autocomplete_route_name' => 'sem.semanticvariable_entity_autocomplete',

    ];
    $form['semantic_variable_attribute'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attribute (required)'),
      '#autocomplete_route_name' => 'sem.semanticvariable_attribute_autocomplete',
    ];
    $form['semantic_variable_in_relation_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('In Relation To (optional)'),
      '#autocomplete_route_name' => 'sem.semanticvariable_attribute_autocomplete',
    ];
    $form['semantic_variable_unit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unit (optional)'),
      '#autocomplete_route_name' => 'sem.semanticvariable_unit_autocomplete',
    ];
    $form['semantic_variable_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Time Restriction (optional)'),
    ];
    $form['semantic_variable_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['semantic_variable_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
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
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));
      return;
    } 

    try {
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

      $newSemanticVariableUri = Utils::uriGen('semanticvariable');
      $semanticVariableJSON = '{"uri":"'. $newSemanticVariableUri .'",'.
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

      $api = \Drupal::service('rep.api_connector');
      $api->semanticVariableAdd($semanticVariableJSON);
      \Drupal::messenger()->addMessage(t("Semantic Variable has been added successfully."));      
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding a semantic variable: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));
    }

  }

}