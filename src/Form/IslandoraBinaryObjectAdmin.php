<?php

/**
 * @file
 * Contains \Drupal\islandora_binary_object\Form\IslandoraBinaryObjectAdmin.
 */

namespace Drupal\islandora_binary_object\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class IslandoraBinaryObjectAdmin extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_binary_object_admin';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    module_load_include('inc', 'islandora_binary_object', 'includes/db');
    // @FIXME
    // The Assets API has totally changed. CSS, JavaScript, and libraries are now
    // attached directly to render arrays using the #attached property.
    // 
    // 
    // @see https://www.drupal.org/node/2169605
    // @see https://www.drupal.org/node/2408597
    // drupal_add_css(drupal_get_path('module', 'islandora_binary_object') . '/css/islandora_binary_object.css');

    $ajax_add_mime_types = [];
    $db_remove_mime_types = [];
    // Use what may have already been carried over from previous AJAXing.
    if (!$form_state->get([
      'islandora_binary_object'
      ])) {
      if (!$form_state->get(['islandora_binary_object', 'ajax_add'])) {
        $ajax_add_mime_types = $form_state->get([
          'islandora_binary_object',
          'ajax_add',
        ]);
      }
      if (!$form_state->get(['islandora_binary_object', 'db_remove'])) {
        $db_remove_mime_types = $form_state->get([
          'islandora_binary_object',
          'db_remove',
        ]);
      }
    }
    if (!$form_state->getTriggeringElement()) {
      // Determine which AJAX button was pressed and only care about the content
    // contained from there.
      $table_element = $form_state->getTriggeringElement();
      // Handle adds to our triggered fieldset.
      if (end($form_state->getTriggeringElement()) == 'add_mimetype_button') {
        if ($form_state->getValue([$table_element, 'wrapper', 'add_mimetype'])) {
          $mime_type_to_add = $form_state->getValue([
            $table_element,
            'wrapper',
            'add_mimetype',
          ]);
          if (!isset($ajax_add_mime_types[$table_element])) {
            $ajax_add_mime_types[$table_element] = [];
          }
          $ajax_add_mime_types[$table_element][$mime_type_to_add] = $mime_type_to_add;
        }
      }
      // Handle removes from our triggered fieldset. Need to keep track of
      // "removed elements" in the form state so do updates in one swoop. For form
      // state ones we will just remove it from an array, the database removals
      // will need to be tracked.
      if (end($form_state->getTriggeringElement()) == 'remove_selected') {
        foreach ($form_state->getValue([
          $table_element,
          'wrapper',
          'mimetype_table',
        ]) as $key => $value) {
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
    $form['thumbnail_associations'] = ['#type' => 'vertical_tabs'];
    // Retrieve all existing associations and render up a fieldset for them.
    $associated_thumbs = islandora_binary_object_retrieve_associations();
    foreach ($associated_thumbs as $association) {
      $thumb = file_load($association['fid']);
      $fieldset_name = \Drupal\Component\Utility\Html::getId($thumb->filename);
      $wrapper_name = "$fieldset_name-mime-type-add";
      $form[$fieldset_name] = [
        '#type' => 'fieldset',
        '#title' => $thumb->filename,
        '#collapsed' => FALSE,
        '#collapsible' => TRUE,
        '#group' => 'thumbnail_associations',
        'wrapper' => [
          '#prefix' => '<div id="' . $wrapper_name . '" class="islandora-binary-object-admin">',
          '#suffix' => '</div>',
        ],
      ];
      $form[$fieldset_name]['fid'] = [
        '#type' => 'value',
        '#value' => $association['fid'],
      ];
      $form[$fieldset_name]['association_id'] = [
        '#type' => 'value',
        '#value' => $association['id'],
      ];
      $form[$fieldset_name]['wrapper']['thumbnail'] = [
        '#theme' => 'image_style',
        '#style_name' => 'medium',
        '#path' => $thumb->uri,
      ];

      // See if there are already existing MIME types associated with this file.
      $mimetypes = islandora_binary_object_retrieve_mime_types($association['id']);
      $rows = [];
      if ($mimetypes) {
        foreach ($mimetypes as $db_association) {
          $rows[$db_association['mimetype']] = [$db_association['mimetype']];
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
            $rows[$mime_type] = [$mime_type];
          }
        }
      }
      $form[$fieldset_name]['wrapper']['mimetype_table'] = [
        '#type' => 'tableselect',
        '#header' => [
          t('MIME type')
          ],
        '#options' => $rows,
        '#empty' => t('No MIME types currently associated.'),
      ];
      $form[$fieldset_name]['wrapper']['remove_selected'] = [
        '#type' => 'button',
        '#value' => t('Remove Selected'),
        '#name' => "$fieldset_name-remove",
        '#ajax' => [
          'callback' => 'islandora_binary_object_fieldset_ajax',
          'wrapper' => $wrapper_name,
        ],
      ];

      $form[$fieldset_name]['wrapper']['add_mimetype'] = [
        '#type' => 'textfield',
        '#title' => t('MIME type'),
        '#autocomplete_path' => 'islandora/autocomplete/mime-types',
      ];
      $form[$fieldset_name]['wrapper']['add_mimetype_button'] = [
        '#type' => 'button',
        '#value' => t('Add'),
        '#name' => "$fieldset_name-add",
        '#ajax' => [
          'callback' => 'islandora_binary_object_fieldset_ajax',
          'wrapper' => $wrapper_name,
        ],
      ];
      $form[$fieldset_name]['wrapper']['remove_association'] = [
        '#type' => 'submit',
        '#name' => "$fieldset_name-delete-association",
        '#value' => t('Delete Association'),
        '#attributes' => [
          'class' => [
            'islandora-binary-object-delete'
            ]
          ],
      ];
    }
    $form['upload_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => t('Upload'),
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
    ];
    $form['upload_fieldset']['upload'] = [
      '#type' => 'managed_file',
      '#title' => t('Upload thumbnail'),
      '#default_value' => !$form_state->getValue([
        'upload'
        ]) ? $form_state->getValue(['upload']) : NULL,
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' => [
          'jpg jpeg png'
          ]
        ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#name' => 'islandora-binary-object-submit',
      '#value' => t('Submit'),
    ];
    // Store any existing AJAX MIME types back into the form state to keep
    // persistence.
    if (!empty($ajax_add_mime_types)) {
      $form_state->set(['islandora_binary_object', 'ajax_add'], $ajax_add_mime_types);
    }
    // Similarily, store any removed DB MIME types for storage.
    if (!empty($db_remove_mime_types)) {
      $form_state->set(['islandora_binary_object', 'db_remove'], $db_remove_mime_types);
    }
    return $form;
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // If a MIME Type is being added first see if it exists in the database or
  // in the form state to preserve the unique mapping.
    if (end($form_state->getTriggeringElement()) == 'add_mimetype_button') {
      module_load_include('inc', 'islandora_binary_object', 'includes/db');
      $mime_errored = FALSE;
      $thumb_fieldset = $form_state->getTriggeringElement();
      $add_mime = $form_state->getValue([
        $thumb_fieldset,
        'wrapper',
        'add_mimetype',
      ]);
      if (!empty($add_mime)) {
        // Check the form state first.
        if (!$form_state->get([
          'islandora_binary_object'
          ]) && !$form_state->get(['islandora_binary_object', 'ajax_add'])) {
          foreach ($form_state->get(['islandora_binary_object', 'ajax_add']) as $thumb => $value) {
            foreach ($value as $fstate_mime) {
              if ($add_mime == $fstate_mime) {
                $form_state->setError($form[$thumb_fieldset]['wrapper']['add_mimetype'], t('The @mime MIME type has already been associated to a thumbnail.', [
                  '@mime' => $add_mime
                  ]));
                $mime_errored = TRUE;
                break;
              }
            }
          }
        }
        if (!$mime_errored) {
          $pending_removal = FALSE;
          if (!$form_state->get(['islandora_binary_object']) && !$form_state->get([
            'islandora_binary_object',
            'db_remove',
          ])) {
            foreach ($form_state->get(['islandora_binary_object', 'db_remove']) as $thumb => $value) {
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
            $form_state->setError($form[$thumb_fieldset]['wrapper']['add_mimetype'], t('The @mime MIME type has already been associated to a thumbnail.', [
              '@mime' => $add_mime
              ]));
          }
        }
      }
      else {
        $form_state->setError($form[$thumb_fieldset]['wrapper']['add_mimetype'], t('Please enter a non-empty value for a MIME type.'));
      }
    }
    elseif (end($form_state->getTriggeringElement()) == 'remove_selected') {
      $thumb_fieldset = $form_state->getTriggeringElement();
      if (count(array_filter($form_state->getValue([
        $thumb_fieldset,
        'wrapper',
        'mimetype_table',
      ]))) == 0) {
        $form_state->setError($form[$thumb_fieldset]['wrapper']['mimetype_table'], t('Need to select at least one MIME type to remove.'));
      }
    }
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // See if the user has uploaded a new file and if so create a new entry in
  // the thumbnails table for it.
    if ($form_state->getTriggeringElement() == 'islandora-binary-object-submit') {
      if ($form_state->getValue(['upload_fieldset', 'upload']) !== 0) {
        module_load_include('inc', 'islandora_binary_object', 'includes/db');
        $file = file_load($form_state->getValue(['upload_fieldset', 'upload']));
        $file->status = FILE_STATUS_PERMANENT;
        file_move($file, 'public://islandora_binary_object_thumbnails');
        islandora_binary_object_create_association($file->fid);
      }

      if (!$form_state->get(['islandora_binary_object'])) {
        // First let's deal with the database removals.
        if (!$form_state->get(['islandora_binary_object', 'db_remove'])) {
          $remove_db = [];
          foreach ($form_state->get(['islandora_binary_object', 'db_remove']) as $value) {
            foreach ($value as $mime_type) {
              $remove_db[] = $mime_type;
            }
          }
          db_delete('islandora_binary_object_thumbnail_mappings')
            ->condition('mimetype', $remove_db)
            ->execute();
        }
        // Now let's add everything.
        if (!$form_state->get(['islandora_binary_object', 'ajax_add'])) {
          foreach ($form_state->get(['islandora_binary_object', 'ajax_add']) as $association => $value) {
            $association_id = $form_state->getValue([$association, 'association_id']);
            $insert = db_insert('islandora_binary_object_thumbnail_mappings')
              ->fields(['id', 'mimetype']);
            foreach ($value as $mime_type) {
              $insert->values([
                'id' => $association_id,
                'mimetype' => $mime_type,
              ]);
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
      $table_element = $form_state->getTriggeringElement();
      $association_id = $form[$table_element]['association_id']['#value'];
      $file_id = $form[$table_element]['fid']['#value'];
      $form_state->set(['redirect'], "admin/islandora/tools/binary-object-storage/delete-association/$association_id/$file_id");
    }
  }

}
?>
