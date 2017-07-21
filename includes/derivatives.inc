<?php

/**
 * @file
 * Function deriving the default thumbnail for storage of blobs.
 */

/**
 * Sets the default thumbnail when no other thumbnail has been set.
 *
 * @param AbstractObject $object
 *   An AbstractObject representing a Fedora object.
 * @param bool $force
 *   Whether derivative generation is being forced or not.
 *
 * @return array
 *   An array describing the outcome of the thumbnail creation.
 *
 * @see hook_islandora_derivative()
 */
function islandora_binary_object_create_thumbnail(AbstractObject $object, $force = FALSE) {
  if ($force || !isset($object['TN'])) {
    module_load_include('inc', 'islandora_binary_object', 'includes/db');
    try {
      $module_path = drupal_get_path('module', 'islandora_binary_object');
      $ingest = !isset($object['TN']);
      if ($ingest) {
        $ds = $object->constructDatastream('TN', 'M');
        $ds->label = t('Thumbnail');
      }
      else {
        $ds = $object['TN'];
      }
      // Go see if we have an association that exists for this MIME type and
      // if we do use that. If not default back to the generic image.
      if (isset($object['OBJ']) && islandora_binary_object_check_mime_type($object['OBJ']->mimetype)) {
        $fid_to_load = islandora_binary_object_retrieve_file_id_by_association($object['OBJ']->mimetype);
        $file = file_load($fid_to_load);
        $ds->mimetype = $file->filemime;
        $ds->setContentFromFile($file->uri, FALSE);
      }
      else {
        $ds->mimetype = 'image/png';
        $ds->setContentFromFile("$module_path/images/binary_object_thumb.png", FALSE);
      }
      if ($ingest) {
        $object->ingestDatastream($ds);
      }
      return array(
        'success' => TRUE,
        'messages' => array(
          array(
            'message' => t('The TN datastream was added successfully for @pid!'),
            'message_sub' => array('@pid' => $object->id),
            'type' => 'dsm',
          ),
        ),
      );
    }
    catch (exception $e) {
      $message = $e->getMessage();
      return array(
        'success' => FALSE,
        'messages' => array(
          array(
            'message' => $message,
            'type' => 'watchdog',
            'severity' => WATCHDOG_ERROR,
          ),
        ),
      );
    }
  }
}
