<?php

/**
 * @file
 * Contains \Drupal\islandora_binary_object\Form\IslandoraBinaryObjectDeleteAssociationForm.
 */

namespace Drupal\islandora_binary_object\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class IslandoraBinaryObjectDeleteAssociationForm extends ConfirmFormBase {

  protected $associationId;
  protected $fileId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_binary_object_delete_association_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the association?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('islandora_binary_object.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will delete the association and the thumbnail from the file system.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   *
   * @param int $association_id
   *   The association ID to delete.
   *
   * @param int $file_id
   *   The file ID to delete with the association.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $association_id = NULL, $file_id = NULL) {
    $this->associationId = $association_id;
    $this->fileId = $file_id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    module_load_include('inc', 'islandora_binary_object', 'includes/db');
    islandora_binary_object_delete_association($this->associationId);

    $file = file_load($this->fileId);
    $file_name = $file->getFilename();
    file_delete($this->fileId);
    drupal_set_message(t('The association for @filename has been deleted!', [
      '@filename' => $file_name,
      ]));
    $form_state->setRedirect("islandora_binary_object.admin");
  }

}
?>
