<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;

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
    $rawresponse = $fusekiAPIservice->getUri($this->getSemanticVariableUri());
    $obj = json_decode($rawresponse);
    
    if ($obj->isSuccessful) {
      $this->setSemanticVariable($obj->body);
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Semantic Variable."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));
    }

    $form['semantic_variable_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getSemanticVariable()->label,
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
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('semantic_variable_name')) < 1) {
        $form_state->setErrorByName('semantic_variable_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('semantic_variable_version')) < 1) {
        $form_state->setErrorByName('semantic_variable_version', $this->t('Please enter a valid version'));
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
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));
      return;
    } 

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $semanticVariableJson = '{"uri":"'. $this->getSemanticVariable()->uri .'",'.
        '"typeUri":"'.HASCO::SEMANTIC_VARIABLE.'",'.
        '"hascoTypeUri":"'.HASCO::SEMANTIC_VARIABLE.'",'.
        '"label":"'.$form_state->getValue('semantic_variable_name').'",'.
        '"hasVersion":"'.$form_state->getValue('semantic_variable_version').'",'.
        '"comment":"'.$form_state->getValue('semantic_variable_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $fusekiAPIservice->semanticVariableDel($this->getSemanticVariable()->uri);
      $fusekiAPIservice->semanticVariableAdd($semanticVariableJson);
    
      \Drupal::messenger()->addMessage(t("SemanticVariable has been updated successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating Semantic Variable: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));
    }

  }

}