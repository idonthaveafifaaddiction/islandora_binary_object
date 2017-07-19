<?php

/**
 * @file
 * Handles the uploading of the file as part of the ingest process.
 */

/**
 * Defines a file upload form for uploading the file for storage.
 *
 * @param array $form
 *   The Drupal form.
 * @param array $form_state
 *   The Drupal form state.
 *
 * @return array
 *   The Drupal form definition.
 */
function islandora_binary_object_upload_form(array $form, array &$form_state) {
  $upload_size = min((int) ini_get('post_max_size'), (int) ini_get('upload_max_filesize'));
  $thumbnail_extensions = array('gif jpg png jpeg');

  return array(
    'file' => array(
      '#title' => t('File'),
      '#type' => 'managed_file',
      '#required' => variable_get('islandora_require_obj_upload', TRUE),
      '#description' => t('Select a file to upload.<br/>Files must be less than <strong>@size MB.</strong>', array('@size' => $upload_size)),
      '#default_value' => isset($form_state['values']['file']) ? $form_state['values']['file'] : NULL,
      '#upload_location' => 'temporary://',
      '#upload_validators' => array(
        // Assume its specified in MB.
        'file_validate_extensions' => array(),
        'file_validate_size' => array($upload_size * 1024 * 1024),
      ),
    ),
    'supply_thumbnail' => array(
      '#type' => 'checkbox',
      '#title' => t('Upload Thumbnail'),
    ),
    'thumbnail_section' => array(
      'thumbnail_file' => array(
        '#title' => t('Thumbnail File'),
        '#type' => 'managed_file',
        '#description' => t('Select a file to upload.<br/>Files must be less than <strong>@size MB.</strong><br/>Allowed file types: <strong>@ext.</strong>', array('@size' => $upload_size, '@ext' => $thumbnail_extensions[0])),
        '#default_value' => isset($form_state['values']['thumbnail_file']) ? $form_state['values']['thumbnail_file'] : NULL,
        '#upload_location' => 'temporary://',
        '#upload_validators' => array(
          'file_validate_extensions' => $thumbnail_extensions,
          // Assume its specified in MB.
          'file_validate_size' => array($upload_size * 1024 * 1024),
        ),
      ),
      'scale_thumbnail' => array(
        '#type' => 'checkbox',
        '#title' => t('Scale Thumbnail'),
        '#attributes' => array('checked' => 'checked'),
      ),
      '#type' => 'item',
      '#states' => array(
        'visible' => array('#edit-supply-thumbnail' => array('checked' => TRUE)),
      ),
    ),
  );
}

/**
 * Validation for binary object upload form.
 *
 * @param array $form
 *   The Drupal form.
 * @param array $form_state
 *   The Drupal form state.
 */
function islandora_binary_object_upload_form_validate(array $form, array &$form_state) {
  if ($form_state['values']['supply_thumbnail'] &&
    !$form_state['values']['thumbnail_file']) {
    form_set_error('thumbnail_file', t('If you select "Upload Thumbnail" please supply a file.'));
  }
}

/**
 * Adds the uploaded file into the ingestable objects 'OBJ' datastream.
 *
 * May also populate the TN datastream.
 *
 * @param array $form
 *   The Drupal form.
 * @param array $form_state
 *   The Drupal form state.
 */
function islandora_binary_object_upload_form_submit(array $form, array &$form_state) {
  module_load_include('inc', 'islandora', 'includes/utilities');
  $object = islandora_ingest_form_get_object($form_state);

  if (isset($form_state['values']['file'])) {
    $file = file_load($form_state['values']['file']);
    $datastream->setContentFromFile($file->uri, FALSE);

    if ($datastream->label != $file->filename) {
      $datastream->label = $file->filename;
    }

    if ($datastream->mimetype != $file->filemime) {
      $datastream->mimetype = $file->filemime;
    }

    if (!isset($object['OBJ'])) {
      $object->ingestDatastream($datastream);
    }
 }

 if ($form_state['values']['supply_thumbnail']) 
      $thumbnail_file = file_load($form_state['values']['thumbnail_file']);
    if ($form_state['values']['scale_thumbnail']) {
      islandora_scale_thumbnail($thumbnail_file, 200, 200);
    }

    if (empty($object['TN'])) {
      $tn = $object->constructDatastream('TN', 'M');
      $object->ingestDatastream($tn);
    }
    else {
      $tn = $object['TN'];
    }
    $tn->setContentFromFile($thumbnail_file->uri, FALSE);

    if ($tn->label != $thumbnail_file->filename) {
    $tn->label = $thumbnail_file->filename;
    }

    if ($tn->mimetype != $thumbnail_file->filemime) {
      $tn->mimetype = $thumbnail_file->filemime;
    }
  }
}
