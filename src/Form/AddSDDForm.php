<?php

namespace Drupal\sem\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\Utils;
use Drupal\rep\Constant;
use Drupal\rep\Vocabulary\HASCO;

class AddSDDForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_sdd_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    //$file_entity = \Drupal\file\Entity\File::load(24);
    //if ($file_entity != NULL) {
    //  dpm($file_entity);
    //  dpm($file_entity->getFilename());
    //}

    $form['sdd_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    
    $form['sdd_filename'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File Upload'),
      '#description' => $this->t('Upload a file.'),
      '#upload_location' => 'public://uploads/',
      '#upload_validators' => [
        'file_validate_extensions' => ['xlsx sdd'],
      ],
    ];
    $form['sdd_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['sdd_comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comment'),
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
      if(strlen($form_state->getValue('sdd_name')) < 1) {
        $form_state->setErrorByName('sdd_name', $this->t('Please enter a valid name for the SDD'));
      }
      #if(strlen($form_state->getValue('sdd_filename')) < 1) {
      #  $form_state->setErrorByName('sdd_filename', $this->t('Please enter a valid filename for the SDD'));
      #}
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
      $form_state->setRedirectUrl(Utils::selectBackUrl('sdd'));
      return;
    } 

    try {
      $useremail = \Drupal::currentUser()->getEmail();

      $fileId = $form_state->getValue('sdd_filename');
      //dpm($fileId[0]);
      $file_entity = \Drupal\file\Entity\File::load($fileId[0]);
      $filename = $file_entity->getFilename();

      $newDataFileUri = Utils::uriGen('datafile');
      $datafileJSON = '{"uri":"'. $newDataFileUri .'",'.
          '"typeUri":"'.HASCO::DATAFILE.'",'.
          '"hascoTypeUri":"'.HASCO::DATAFILE.'",'.
          '"label":"'.$form_state->getValue('sdd_name').'",'.
          '"filename":"'.$filename.'",'.          
          '"id":"'.$fileId[0].'",'.          
          '"fileStatus":"'.Constant::FILE_STATUS_UNPROCESSED.'",'.          
          '"hasSIRManagerEmail":"'.$useremail.'"}';

      $newSDDUri = str_replace("DF","SD",$newDataFileUri);
      $sddJSON = '{"uri":"'. $newSDDUri .'",'.
          '"typeUri":"'.HASCO::SDD.'",'.
          '"hascoTypeUri":"'.HASCO::SDD.'",'.
          '"label":"'.$form_state->getValue('sdd_name').'",'.
          '"hasDataFile":"'.$newDataFileUri.'",'.          
          '"hasVersion":"'.$form_state->getValue('sdd_version').'",'.
          '"comment":"'.$form_state->getValue('sdd_description').'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

      // Check if a file was uploaded.
      if (!empty($fileId[0])) {
        // Load the file object.
        //$file_entity = \Drupal\file\Entity\File::load($file[0]);
        //dpm($file_entity);
        //dpm($file_entity->getFilename());
        \Drupal::messenger()->addMessage(t('File uploaded successfully.'));
        $api = \Drupal::service('rep.api_connector');

        // ADD DATAFILE
        $msg1 = $api->parseObjectResponse($api->datafileAdd($datafileJSON),'datafileAdd');

        // ADD SDD
        $msg2 = $api->parseObjectResponse($api->sddAdd($sddJSON),'sddAdd');

        if ($msg1 != NULL && $msg2 != NULL) {
          \Drupal::messenger()->addMessage(t("SDD has been added successfully."));      
        } else {
          \Drupal::messenger()->addError(t("Something went wrong while adding SDD."));      
        }
        $form_state->setRedirectUrl(Utils::selectBackUrl('sdd'));
        return;
      }

    } catch(\Exception $e) {
      \Drupal::messenger()->addMessage(t("An error occurred while adding an SDD: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('sdd'));
      return;
    }

  }

}