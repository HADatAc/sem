<?php

/**
 * @file
 * Contains the settings for admninistering the SIR Module
 */

 namespace Drupal\sem\Form;

 use Drupal\Core\Form\ConfigFormBase;
 use Drupal\Core\Form\FormStateInterface;
 use Drupal\Core\Url;

 class SVarSettingsForm extends ConfigFormBase {

     /**
     * Settings Variable.
     */
    Const CONFIGNAME = "sir.settings";

     /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "sem_form_settings";
    }

    /**
     * {@inheritdoc}
     */

    protected function getEditableConfigNames() {
        return [
            static::CONFIGNAME,
        ];
    }

     /**
     * {@inheritdoc}
     */

     public function buildForm(array $form, FormStateInterface $form_state){

        $description = "";
        $form['repository_description'] = [
            '#type' => 'textarea',
            '#title' => ' description for the repository that appears in the SIR APIs GUI',
            '#required' => TRUE,
            '#default_value' => $description,
        ];

        $form['filler_1'] = [
            '#type' => 'item',
            '#title' => $this->t('<br>'),
        ];
      
        return Parent::buildForm($form, $form_state);


     }

    public function validateForm(array &$form, FormStateInterface $form_state) {
   }
     
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $triggering_element = $form_state->getTriggeringElement();
        $button_name = $triggering_element['#name'];
            
    }

 }