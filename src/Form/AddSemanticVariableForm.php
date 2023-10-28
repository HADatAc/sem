<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sir\Utils;
use Drupal\sir\Vocabulary\HASCO;

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
      $uemail = \Drupal::currentUser()->getEmail();
      $newSemanticVariableUri = Utils::uriGen('semanticvariable');
      $semanticVariableJSON = '{"uri":"'.$newExperienceUri.'",' . 
        '"typeUri":"'.HASCO::SEMANTIC_VARIABLE.'",'.
        '"hascoTypeUri":"'.HASCO::SEMANTIC_VARIABLE.'",'.
        '"label":"' . $form_state->getValue('semantic_variable_name') . '",' . 
        '"hasVersion":"' . $form_state->getValue('semantic_variable_version') . '",' . 
        '"comment":"' . $form_state->getValue('semantic_variable_description') . '",' . 
        '"hasSIRManagerEmail":"' . $uemail . '"}';

      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->semanticVariableAdd($semanticVariableJSON);
      \Drupal::messenger()->addMessage(t("Semantic Variable has been added successfully."));      
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding a semantic variable: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('semanticvariable'));
    }

  }

}