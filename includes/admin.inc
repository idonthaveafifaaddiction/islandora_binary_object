<?php

/**
 * @file
 * Admin settings for the islandora_binary_object module.
 */

/**
 * Builds the admin form for the islandora_binary_object.
 *
 * @param array $form
 *   An array representing a form in Drupal.
 * @param array $form_state
 *   An array containing the Drupal form state.
 *
 * @return array
 *   An array containing the form to be rendered.
 */
function islandora_binary_object_admin($form, &$form_state) {
  module_load_include('inc', 'islandora_binary_object', 'includes/db');
  // @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
// 
// 
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// drupal_add_css(drupal_get_path('module', 'islandora_binary_object') . '/css/islandora_binary_object.css');

  $ajax_add_mime_types = array();
  $db_remove_mime_types = array();
  // Use what may have already been carried over from previous AJAXing.
  if (isset($form_state['islandora_binary_object'])) {
    if (isset($form_state['islandora_binary_object']['ajax_add'])) {
      $ajax_add_mime_types = $form_state['islandora_binary_object']['ajax_add'];
    }
    if (isset($form_state['islandora_binary_object']['db_remove'])) {
      $db_remove_mime_types = $form_state['islandora_binary_object']['db_remove'];
    }
  }
  if (isset($form_state['triggering_element'])) {
    // Determine which AJAX button was pressed and only care about the content
    // contained from there.
    $table_element = $form_state['triggering_element']['#parents'][0];
    // Handle adds to our triggered fieldset.
    if (end($form_state['triggering_element']['#parents']) == 'add_mimetype_button') {
      if (!empty($form_state['values'][$table_element]['wrapper']['add_mimetype'])) {
        $mime_type_to_add = $form_state['values'][$table_element]['wrapper']['add_mimetype'];
        if (!isset($ajax_add_mime_types[$table_element])) {
          $ajax_add_mime_types[$table_element] = array();
        }
        $ajax_add_mime_types[$table_element][$mime_type_to_add] = $mime_type_to_add;
      }
    }
    // Handle removes from our triggered fieldset. Need to keep track of
    // "removed elements" in the form state so do updates in one swoop. For form
    // state ones we will just remove it from an array, the database removals
    // will need to be tracked.
    if (end($form_state['triggering_element']['#parents']) == 'remove_selected') {
      foreach ($form_state['values'][$table_element]['wrapper']['mimetype_table'] as $key => $value) {
        if ($value !== 0) {
          // Determine if it's stored in the database or in the form state.
          if (isset($ajax_add_mime_types[$table_element]) && isset($ajax_add_mime_types[$table_element][$value])) {
            unset($ajax_add_mime_types[$table_element][$value]);
          }
          // Must be in the database so make an entry to keep track.
          else {
            $db_remove_mime_types[$table_element][$value] = $value;
          }
        }
      }
    }
  }
  $form['#tree'] = TRUE;
  $form['thumbnail_associations'] = array(
    '#type' => 'vertical_tabs',
  );
  // Retrieve all existing associations and render up a fieldset for them.
  $associated_thumbs = islandora_binary_object_retrieve_associations();
  foreach ($associated_thumbs as $association) {
    $thumb = file_load($association['fid']);
    $fieldset_name = \Drupal\Component\Utility\Html::getId($thumb->filename);
    $wrapper_name = "$fieldset_name-mime-type-add";
    $form[$fieldset_name] = array(
      '#type' => 'fieldset',
      '#title' => $thumb->filename,
      '#collapsed' => FALSE,
      '#collapsible' => TRUE,
      '#group' => 'thumbnail_associations',
      'wrapper' => array(
        '#prefix' => '<div id="' . $wrapper_name . '" class="islandora-binary-object-admin">',
        '#suffix' => '</div>',
      ),
    );
    $form[$fieldset_name]['fid'] = array(
      '#type' => 'value',
      '#value' => $association['fid'],
    );
    $form[$fieldset_name]['association_id'] = array(
      '#type' => 'value',
      '#value' => $association['id'],
    );
    $form[$fieldset_name]['wrapper']['thumbnail'] = array(
      '#theme' => 'image_style',
      '#style_name' => 'medium',
      '#path' => $thumb->uri,
    );

    // See if there are already existing MIME types associated with this file.
    $mimetypes = islandora_binary_object_retrieve_mime_types($association['id']);
    $rows = array();
    if ($mimetypes) {
      foreach ($mimetypes as $db_association) {
        $rows[$db_association['mimetype']] = array($db_association['mimetype']);
      }
      // Check if the association has any MIME types removed that have yet
      // to be updated in the form state.
      if ($db_remove_mime_types) {
        if (isset($db_remove_mime_types[$fieldset_name])) {
          foreach ($db_remove_mime_types[$fieldset_name] as $db_mime_type) {
            unset($rows[$db_mime_type]);
          }
        }
      }
    }
    // Lastly check the form state to see if there anymore to add.
    if ($ajax_add_mime_types) {
      if (isset($ajax_add_mime_types[$fieldset_name])) {
        foreach ($ajax_add_mime_types[$fieldset_name] as $mime_type) {
          $rows[$mime_type] = array($mime_type);
        }
      }
    }
    $form[$fieldset_name]['wrapper']['mimetype_table'] = array(
      '#type' => 'tableselect',
      '#header' => array(t('MIME type')),
      '#options' => $rows,
      '#empty' => t('No MIME types currently associated.'),
    );
    $form[$fieldset_name]['wrapper']['remove_selected'] = array(
      '#type' => 'button',
      '#value' => t('Remove Selected'),
      '#name' => "$fieldset_name-remove",
      '#ajax' => array(
        'callback' => 'islandora_binary_object_fieldset_ajax',
        'wrapper' => $wrapper_name,
      ),
    );

    $form[$fieldset_name]['wrapper']['add_mimetype'] = array(
      '#type' => 'textfield',
      '#title' => t('MIME type'),
      '#autocomplete_path' => 'islandora/autocomplete/mime-types',
    );
    $form[$fieldset_name]['wrapper']['add_mimetype_button'] = array(
      '#type' => 'button',
      '#value' => t('Add'),
      '#name' => "$fieldset_name-add",
      '#ajax' => array(
        'callback' => 'islandora_binary_object_fieldset_ajax',
        'wrapper' => $wrapper_name,
      ),
    );
    $form[$fieldset_name]['wrapper']['remove_association'] = array(
      '#type' => 'submit',
      '#name' => "$fieldset_name-delete-association",
      '#value' => t('Delete Association'),
      '#attributes' => array(
        'class' => array('islandora-binary-object-delete'),
      ),
    );
  }
  $form['upload_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Upload'),
    '#collapsed' => TRUE,
    '#collapsible' => TRUE,
  );
  $form['upload_fieldset']['upload'] = array(
    '#type' => 'managed_file',
    '#title' => t('Upload thumbnail'),
    '#default_value' => isset($form_state['values']['upload']) ? $form_state['values']['upload'] : NULL,
    '#upload_location' => 'temporary://',
    '#upload_validators' => array(
      'file_validate_extensions' => array('jpg jpeg png'),
    ),
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#name' => 'islandora-binary-object-submit',
    '#value' => t('Submit'),
  );
  // Store any existing AJAX MIME types back into the form state to keep
  // persistence.
  if (!empty($ajax_add_mime_types)) {
    $form_state['islandora_binary_object']['ajax_add'] = $ajax_add_mime_types;
  }
  // Similarily, store any removed DB MIME types for storage.
  if (!empty($db_remove_mime_types)) {
    $form_state['islandora_binary_object']['db_remove'] = $db_remove_mime_types;
  }
  return $form;
}

/**
 * Validation for the islandora_binary_object admin form.
 *
 * @param array $form
 *   An array representing a form in Drupal.
 * @param array $form_state
 *   An array containing the Drupal form state.
 */
function islandora_binary_object_admin_validate($form, &$form_state) {
  // If a MIME Type is being added first see if it exists in the database or
  // in the form state to preserve the unique mapping.
  if (end($form_state['triggering_element']['#parents']) == 'add_mimetype_button') {
    module_load_include('inc', 'islandora_binary_object', 'includes/db');
    $mime_errored = FALSE;
    $thumb_fieldset = $form_state['triggering_element']['#parents']['0'];
    $add_mime = $form_state['values'][$thumb_fieldset]['wrapper']['add_mimetype'];
    if (!empty($add_mime)) {
      // Check the form state first.
      if (isset($form_state['islandora_binary_object']) && isset($form_state['islandora_binary_object']['ajax_add'])) {
        foreach ($form_state['islandora_binary_object']['ajax_add'] as $thumb => $value) {
          foreach ($value as $fstate_mime) {
            if ($add_mime == $fstate_mime) {
              form_error($form[$thumb_fieldset]['wrapper']['add_mimetype'], t('The @mime MIME type has already been associated to a thumbnail.',
              array(
                '@mime' => $add_mime,
              )));
              $mime_errored = TRUE;
              break;
            }
          }
        }
      }
      if (!$mime_errored) {
        $pending_removal = FALSE;
        if (isset($form_state['islandora_binary_object']) && isset($form_state['islandora_binary_object']['db_remove'])) {
          foreach ($form_state['islandora_binary_object']['db_remove'] as $thumb => $value) {
            foreach ($value as $db_mime) {
              if ($add_mime == $db_mime) {
                $pending_removal = TRUE;
                break;
              }
            }
          }
        }
        $db_mime_exists = islandora_binary_object_check_mime_type($add_mime);
        if ($db_mime_exists && !$pending_removal) {
          form_error($form[$thumb_fieldset]['wrapper']['add_mimetype'], t('The @mime MIME type has already been associated to a thumbnail.',
            array(
              '@mime' => $add_mime,
            )));
        }
      }
    }
    else {
      form_error($form[$thumb_fieldset]['wrapper']['add_mimetype'], t('Please enter a non-empty value for a MIME type.'));
    }
  }
  elseif (end($form_state['triggering_element']['#parents']) == 'remove_selected') {
    $thumb_fieldset = $form_state['triggering_element']['#parents']['0'];
    if (count(array_filter($form_state['values'][$thumb_fieldset]['wrapper']['mimetype_table'])) == 0) {
      form_error($form[$thumb_fieldset]['wrapper']['mimetype_table'], t('Need to select at least one MIME type to remove.'));
    }
  }
}

/**
 * Submit handler for the admin form for the islandora_binary_object.
 *
 * @param array $form
 *   An array representing a form in Drupal.
 * @param array $form_state
 *   An array containing the Drupal form state.
 */
function islandora_binary_object_admin_submit($form, &$form_state) {
  // See if the user has uploaded a new file and if so create a new entry in
  // the thumbnails table for it.
  if ($form_state['triggering_element']['#name'] == 'islandora-binary-object-submit') {
    if ($form_state['values']['upload_fieldset']['upload'] !== 0) {
      module_load_include('inc', 'islandora_binary_object', 'includes/db');
      $file = file_load($form_state['values']['upload_fieldset']['upload']);
      $file->status = FILE_STATUS_PERMANENT;
      file_move($file, 'public://islandora_binary_object_thumbnails');
      islandora_binary_object_create_association($file->fid);
    }

    if (isset($form_state['islandora_binary_object'])) {
      // First let's deal with the database removals.
      if (isset($form_state['islandora_binary_object']['db_remove'])) {
        $remove_db = array();
        foreach ($form_state['islandora_binary_object']['db_remove'] as $value) {
          foreach ($value as $mime_type) {
            $remove_db[] = $mime_type;
          }
        }
        db_delete('islandora_binary_object_thumbnail_mappings')
          ->condition('mimetype', $remove_db)
          ->execute();
      }
      // Now let's add everything.
      if (isset($form_state['islandora_binary_object']['ajax_add'])) {
        foreach ($form_state['islandora_binary_object']['ajax_add'] as $association => $value) {
          $association_id = $form_state['values'][$association]['association_id'];
          $insert = db_insert('islandora_binary_object_thumbnail_mappings')
            ->fields(array('id', 'mimetype'));
          foreach ($value as $mime_type) {
            $insert->values(array(
             'id' => $association_id,
             'mimetype' => $mime_type,
            ));
          }
          $insert->execute();
        }
      }
    }
    drupal_set_message(t('The associations have been updated.'));
  }
  // Deleting an association has been pressed.
  else {
    // Determine which delete button was pressed.
    $table_element = $form_state['triggering_element']['#parents'][0];
    $association_id = $form[$table_element]['association_id']['#value'];
    $file_id = $form[$table_element]['fid']['#value'];
    $form_state['redirect'] = "admin/islandora/tools/binary-object-storage/delete-association/$association_id/$file_id";
  }
}

/**
 * AJAX callback for islandora_binary_object admin form.
 *
 * @param array $form
 *   An array representing a form in Drupal.
 * @param array $form_state
 *   An array containing the Drupal form state.
 *
 * @return array
 *   The portion of the form that needs to be rendered.
 */
function islandora_binary_object_fieldset_ajax($form, $form_state) {
  return \Drupal\Component\Utility\NestedArray::getValue($form, array_slice($form_state['triggering_element']['#parents'], 0, 2));
}


/**
 * Delete an association confirm form.
 *
 * @param array $form
 *   An array representing a Drupal form.
 * @param array $form_state
 *   An array containing the Drupal form state.
 * @param string $association_id
 *   The association that's to be deleted.
 * @param string $file_id
 *   The Drupal fid that needs to be deleted.
 *
 * @return array
 *   The Drupal form definition.
 */
function islandora_binary_object_delete_association_form($form, &$form_state, $association_id, $file_id) {
  $form_state['association_id'] = $association_id;
  $form_state['fid'] = $file_id;
  return confirm_form($form,
    t('Are you sure you want to delete the association?'),
    "admin/islandora/tools/binary-object-storage",
    t('This will delete the association and the thumbnail from the file system.'),
    t('Delete'),
    t('Cancel')
  );
}

/**
 * Submit handler for the delete association confirm form.
 *
 * @param array $form
 *   An array representing a Drupal form.
 * @param array $form_state
 *   An array containing the Drupal form state.
 */
function islandora_binary_object_delete_association_form_submit($form, &$form_state) {
  module_load_include('inc', 'islandora_binary_object', 'includes/db');

  islandora_binary_object_delete_association($form_state['association_id']);
  $file = file_load($form_state['fid']);
  $file_name = $file->filename;
  file_delete($file);
  drupal_set_message(t('The association for @filename has been deleted!', array('@filename' => $file_name)));
  $form_state['redirect'] = "admin/islandora/tools/binary-object-storage";
}
