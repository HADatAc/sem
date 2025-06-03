<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ShowSemanticVariableForm extends FormBase {

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
    return 'show_semantic_variable_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
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

    $form['semantic_variable_uddi'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity'),
      '#default_value' => '',
    ];
    $form['semantic_variable_entity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity'),
      '#default_value' => $this->getSemanticVariable()->entityLabel,
    ];
    $form['semantic_variable_attribute'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attribute'),
      '#default_value' => $this->getSemanticVariable()->attributeLabel,
    ];
    $form['semantic_variable_in_relation_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('In Relation To'),
      '#default_value' => $this->getSemanticVariable()->inRelationToLabel,
    ];
    $form['semantic_variable_unit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unit'),
      '#default_value' => $this->getSemanticVariable()->unitLabel,
    ];
    $form['semantic_variable_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Time Restriction'),
      '#default_value' => $this->getSemanticVariable()->timeLabel,
    ];
    $form['semantic_variable_space'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Space Restriction'),
      '#default_value' => $this->getSemanticVariable()->spaceLabel,
    ];
    $form['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
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


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }


  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sem.show_semantic_variable');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
