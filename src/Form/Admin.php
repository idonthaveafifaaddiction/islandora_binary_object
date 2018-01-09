<?php

namespace Drupal\islandora_binary_object\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityStorageInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Binary Object.
 */
class Admin extends FormBase {

  protected $fileEntityStorage;

  /**
   * Constructor.
   */
  public function __construct(EntityStorageInterface $file_entity_storage) {
    $this->fileEntityStorage = $file_entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('file')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_binary_object_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_binary_object', 'inc', 'includes/db');

    $mimes_to_add = $form_state->get(['islandora_binary_object', 'ajax_add']);
    $db_deletions = $form_state->get(['islandora_binary_object', 'db_remove']);
    foreach ([$mimes_to_add, $db_deletions] as &$item) {
      if (!is_array($item)) {
        $item = [];
      }
    }
    $triggering_element = $form_state->getTriggeringElement();
    // If this is a rebuild from a trigger, we'll need to update the storage.
    if ($triggering_element) {
      $triggering_fieldset = reset($triggering_element['#parents']);
      // Case: hitting the 'add' button.
      if (end($triggering_element['#parents']) == 'add_mimetype_button') {
        if (!isset($mimes_to_add[$triggering_fieldset])) {
          $mimes_to_add[$triggering_fieldset] = [];
        }
        $mime_to_add = $form_state->getValue([
          $triggering_fieldset,
          'wrapper',
          'add_mimetype',
        ]);
        $mimes_to_add[$triggering_fieldset][$mime_to_add] = $mime_to_add;
      }
      // Otherwise, the removal button was hit.
      if (end($triggering_element['#parents']) == 'remove_selected') {
        $checked_mimetypes = array_filter($form_state->getValue([
          $triggering_fieldset,
          'wrapper',
          'mimetype_table',
        ]));
        foreach ($checked_mimetypes as $value) {
          if (NestedArray::getValue($mimes_to_add, [$triggering_fieldset, $value])) {
            unset($mimes_to_add[$triggering_fieldset][$value]);
          }
          else {
            $db_deletions[$triggering_fieldset][$value] = $value;
          }
        }
      }
    }

    $form['#attached']['library'][] = 'islandora_binary_object/islandora-binary-object';
    $form['#theme'] = 'system_config_form';
    $form['#tree'] = TRUE;
    $form['thumbnail_associations'] = ['#type' => 'vertical_tabs'];
    // Retrieve all existing associations and render up a fieldset for them.
    $associated_thumbs = islandora_binary_object_retrieve_associations();
    foreach ($associated_thumbs as $association) {
      $thumb = $this->fileEntityStorage->load($association['fid']);
      $tab_name = Html::getId($thumb->getFilename());
      $wrapper_name = "$tab_name-mime-type-add";
      $form[$tab_name] = [
        '#type' => 'details',
        '#title' => $thumb->getFilename(),
        '#group' => 'thumbnail_associations',
        'wrapper' => [
          '#prefix' => '<div id="' . $wrapper_name . '" class="islandora-binary-object-admin">',
          '#suffix' => '</div>',
        ],
      ];
      $form[$tab_name]['fid'] = [
        '#type' => 'value',
        '#value' => $association['fid'],
      ];
      $form[$tab_name]['association_id'] = [
        '#type' => 'value',
        '#value' => $association['id'],
      ];
      $form[$tab_name]['wrapper']['thumbnail'] = [
        '#theme' => 'image_style',
        '#style_name' => 'medium',
        '#uri' => $thumb->getFileUri(),
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
        if ($db_deletions) {
          if (isset($db_deletions[$tab_name])) {
            foreach ($db_deletions[$tab_name] as $db_mime_type) {
              unset($rows[$db_mime_type]);
            }
          }
        }
      }
      // Lastly check the form state to see if there anymore to add.
      if (isset($mimes_to_add[$tab_name])) {
        foreach ($mimes_to_add[$tab_name] as $mime) {
          $rows[$mime] = [$mime];
        }
      }
      $form[$tab_name]['wrapper']['mimetype_table'] = [
        '#type' => 'tableselect',
        '#header' => [
          $this->t('MIME type'),
        ],
        '#options' => $rows,
        '#empty' => $this->t('No MIME types currently associated.'),
      ];
      $form[$tab_name]['wrapper']['remove_selected'] = [
        '#type' => 'button',
        '#value' => $this->t('Remove Selected'),
        '#name' => "$tab_name-remove",
        '#ajax' => [
          'callback' => '::fieldsetAjax',
          'wrapper' => $wrapper_name,
        ],
      ];

      $form[$tab_name]['wrapper']['add_mimetype'] = [
        '#type' => 'textfield',
        '#title' => $this->t('MIME type'),
        '#autocomplete_path' => 'islandora/autocomplete/mime-types',
      ];
      $form[$tab_name]['wrapper']['add_mimetype_button'] = [
        '#type' => 'button',
        '#value' => $this->t('Add'),
        '#name' => "$tab_name-add",
        '#ajax' => [
          'callback' => '::fieldsetAjax',
          'wrapper' => $wrapper_name,
        ],
      ];
      $form[$tab_name]['wrapper']['remove_association'] = [
        '#type' => 'submit',
        '#name' => "$tab_name-delete-association",
        '#value' => $this->t('Delete Association'),
        '#attributes' => [
          'class' => [
            'islandora-binary-object-delete',
          ],
        ],
      ];
    }
    $form['upload_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Upload'),
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
    ];
    $form['upload_fieldset']['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload thumbnail'),
      '#default_value' => !$form_state->getValue([
        'upload',
      ]) ? $form_state->getValue(['upload']) : NULL,
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' => [
          'jpg jpeg png',
        ],
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#name' => 'islandora-binary-object-submit',
      '#value' => $this->t('Submit'),
    ];
    // Store any existing AJAX MIME types back into the form state to keep
    // persistence.
    $form_state->set(['islandora_binary_object', 'ajax_add'], $mimes_to_add);
    // Similarily, store any removed DB MIME types for storage.
    $form_state->set(['islandora_binary_object', 'db_remove'], $db_deletions);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_binary_object', 'inc', 'includes/db');
    // If a MIME Type is being added first see if it exists in the database or
    // in the form state to preserve the unique mapping.
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper = NestedArray::getValue($form, array_slice($triggering_element['#parents'], 0, 2));
    if (end($triggering_element['#parents']) == 'add_mimetype_button') {
      $mime_errored = FALSE;
      $thumb_fieldset = reset($triggering_element['#parents']);
      $add_mime = $form_state->getValue([
        $thumb_fieldset,
        'wrapper',
        'add_mimetype',
      ]);
      // Hang onto this; it'll be where the errors go.
      if (!empty($add_mime)) {
        // Check the form state first.
        $ajax_add = $form_state->get(['islandora_binary_object', 'ajax_add']);
        if ($ajax_add) {
          foreach ($ajax_add as $value) {
            foreach ($value as $fstate_mime) {
              if ($add_mime == $fstate_mime) {
                $form_state->setError($wrapper, $this->t('The @mime MIME type has already been associated to a thumbnail.', [
                  '@mime' => $add_mime,
                ]));
                $mime_errored = TRUE;
                break;
              }
            }
          }
        }
        if (!$mime_errored) {
          $pending_removal = FALSE;
          if ($form_state->get(['islandora_binary_object', 'db_remove'])) {
            foreach ($form_state->get(['islandora_binary_object', 'db_remove']) as $value) {
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
            $form_state->setError($wrapper, $this->t('The @mime MIME type has already been associated to a thumbnail.', [
              '@mime' => $add_mime,
            ]));
          }
        }
      }
      else {
        $form_state->setError($wrapper, $this->t('Please enter a non-empty value for a MIME type.'));
      }
    }
    elseif (end($triggering_element['#parents']) == 'remove_selected') {
      if (count(array_filter($form_state->getValue([
        reset($triggering_element['#parents']),
        'wrapper',
        'mimetype_table',
      ]))) == 0) {
        $form_state->setError($wrapper, $this->t('Need to select at least one MIME type to remove.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_binary_object', 'inc', 'includes/db');
    // See if the user has uploaded a new file and if so create a new entry in
    // the thumbnails table for it.
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#name'] == 'islandora-binary-object-submit') {
      if (!empty($form_state->getValue(['upload_fieldset', 'upload']))) {
        $file = $this->fileEntityStorage->load(reset($form_state->getValue(['upload_fieldset', 'upload'])));
        $file->setPermanent();
        file_move($file, 'public://islandora_binary_object_thumbnails');
        islandora_binary_object_create_association($file->id());
      }

      // First let's deal with the database removals.
      if ($form_state->get(['islandora_binary_object', 'db_remove'])) {
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
      if ($form_state->get(['islandora_binary_object', 'ajax_add'])) {
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
      drupal_set_message($this->t('The associations have been updated.'));
    }
    // Deleting an association has been pressed.
    else {
      $immediate_parent = reset($triggering_element['#parents']);
      $form_state->setRedirect('islandora_binary_object.delete_association_form', [
        'association_id' => $form_state->getValue([$immediate_parent, 'association_id']),
        'file_id' => $form_state->getValue([$immediate_parent, 'fid']),
      ]);
    }
  }

  /**
   * AJAX callback for resetting fieldsets after mimetype addition/removal.
   */
  public function fieldsetAjax(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    return NestedArray::getValue($form, array_slice($triggering_element['#parents'], 0, 2));
  }

}
