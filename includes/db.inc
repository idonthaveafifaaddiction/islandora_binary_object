<?php

/**
 * @file
 * Holds database utility functions for use in the admin page.
 */

/**
 * Retrieves all associations for rendering in the admin UI.
 *
 * @return array
 *   An array containing the all the associations keyed by "id".
 */
function islandora_binary_object_retrieve_associations() {
  return db_select('islandora_binary_object_thumbnail_associations', 'i')
    ->fields('i', array('id', 'fid'))
    ->execute()
    ->fetchAllAssoc('id', PDO::FETCH_ASSOC);
}

/**
 * Retrieves all the MIME types for an association.
 *
 * @param int $association_id
 *   The association ID which we are retrieving MIME types for.
 */
function islandora_binary_object_retrieve_mime_types($association_id) {
  return db_select('islandora_binary_object_thumbnail_mappings', 'i')
    ->fields('i', array('id', 'mimetype'))
    ->condition('id', $association_id)
    ->execute()
    ->fetchAllAssoc('mimetype', PDO::FETCH_ASSOC);
}
/**
 * Creates a new association for an uploaded file.
 *
 * @param int $fid
 *   The Drupal file id of the uploaded file.
 */
function islandora_binary_object_create_association($fid) {
  db_insert('islandora_binary_object_thumbnail_associations')
    ->fields(
      array(
        'fid' => $fid,
      )
    )
    ->execute();
  $file = file_load($fid);
  drupal_set_message(t('Created a new association for @filename.', array(
    '@filename' => $file->get('filename')->value,
  )));
}

/**
 * Checks to see if a MIME type has been associated in the database.
 *
 * @param string $mime_type
 *   The MIME type that we are searching for.
 *
 * @return bool
 *   TRUE if the MIME type exists, FALSE otherwise.
 */
function islandora_binary_object_check_mime_type($mime_type) {
  $select = db_select('islandora_binary_object_thumbnail_mappings', 'i')
    ->fields('i')
    ->condition('mimetype', $mime_type)
    ->execute()
    ->fetchField();
  return $select !== FALSE;
}

/**
 * Retrieve the Drupal fid for an association.
 *
 * @param string $mime_type
 *   The MIME type that we are searching for.
 *
 * @return string
 *   The Drupal fid of the file to use.
 */
function islandora_binary_object_retrieve_file_id_by_association($mime_type) {
  $query = db_select('islandora_binary_object_thumbnail_mappings', 'map');
  $query->join('islandora_binary_object_thumbnail_associations', 'assoc', 'map.id = assoc.id');
  return $query->fields('assoc', array('fid'))
    ->condition('map.mimetype', $mime_type)
    ->execute()
    ->fetchField();
}

/**
 * Deletes a given association and the file on the filesystem.
 *
 * @param string $association_id
 *   The association id that is being deleted.
 */
function islandora_binary_object_delete_association($association_id) {
  db_delete('islandora_binary_object_thumbnail_associations')
    ->condition('id', $association_id)
    ->execute();
  db_delete('islandora_binary_object_thumbnail_mappings')
    ->condition('id', $association_id)
    ->execute();
}
