<?php

namespace Drupal\islandora_binary_object\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityStorageInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for deleting thumbnail associations.
 */
class IslandoraBinaryObjectDeleteAssociationForm extends ConfirmFormBase {

  protected $fileEntityStorage;
  protected $associationId;
  protected $fileId;

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
    return Url::fromRoute('islandora_binary_object.admin');
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
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int $association_id
   *   The association ID to delete.
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
    $form_state->loadInclude('islandora_binary_object', 'inc', 'includes/db');
    islandora_binary_object_delete_association($this->associationId);

    $file = $this->fileEntityStorage->load($this->fileId);
    $file_name = $file->getFilename();
    $file->delete();
    drupal_set_message(t('The association for @filename has been deleted!', [
      '@filename' => $file_name,
    ]));
    $form_state->setRedirect("islandora_binary_object.admin");
  }

}
