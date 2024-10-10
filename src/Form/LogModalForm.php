<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class LogModalForm extends FormBase {

  public function getFormId() {
    return 'log_modal_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['textarea'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Textarea'),
      '#rows' => 4,
      '#cols' => 50,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::submitFormAjax',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'check-button'],
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle form submission.
  }

  public function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.popup-dialog-class', $form));

    return $response;
  }

}
