<?php

/**
 * @file
 * Contains \Drupal\islandora_binary_object\Form\IslandoraBinaryObjectDeleteAssociationForm.
 */

namespace Drupal\islandora_binary_object\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class IslandoraBinaryObjectDeleteAssociationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_binary_object_delete_association_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $association_id = NULL, $file_id = NULL) {
    $form_state->set(['association_id'], $association_id);
    $form_state->set(['fid'], $file_id);
    return confirm_form($form, t('Are you sure you want to delete the association?'), "admin/islandora/tools/binary-object-storage", t('This will delete the association and the thumbnail from the file system.'), t('Delete'), t('Cancel'));
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    module_load_include('inc', 'islandora_binary_object', 'includes/db');

    islandora_binary_object_delete_association($form_state->get(['association_id']));
    $file = file_load($form_state->get(['fid']));
    $file_name = $file->filename;
    file_delete($file);
    drupal_set_message(t('The association for @filename has been deleted!', [
      '@filename' => $file_name
      ]));
    $form_state->set(['redirect'], "admin/islandora/tools/binary-object-storage");
  }

}
?>
