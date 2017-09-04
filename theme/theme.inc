<?php
/**
 * @file
 * Handles the display of binary objects.
 */

use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Implements hook_preprocess_theme().
 */
function template_preprocess_islandora_binary_object(array &$variables) {
  module_load_include('inc', 'islandora', 'includes/metadata');
  module_load_include('inc', 'islandora', 'includes/mimetype.utils');

  $islandora_object = $variables['islandora_object'];
  if (isset($islandora_object['OBJ']) && islandora_datastream_access(ISLANDORA_VIEW_OBJECTS, $islandora_object['OBJ'])) {
    $extensions = islandora_get_extensions_for_datastream($islandora_object, 'OBJ');
    $extension = reset($extensions);
    $variables['islandora_binary_object_info'] = t('This is a downloadable object of filetype @extension and size @size.', array(
      '@extension' => $extension,
      '@size' => format_size($islandora_object['OBJ']->size),
    ));

    $link = Link::createFromRoute(
      $islandora_object['OBJ']->label,
      'islandora.download_datastream',
      ['object' => $islandora_object->id, 'datastream' => 'OBJ']
    )->toString();
    $display['download'] = array(
      '#type' => 'item',
      '#title' => t('Download'),
      '#markup' => $link,
    );

    $variables['islandora_binary_object_download'] = \Drupal::service("renderer")->render($display['download']);
  }

  // Thumbnail.
  if (isset($islandora_object['TN']) && islandora_datastream_access(ISLANDORA_VIEW_OBJECTS, $islandora_object['TN'])) {
    $img = array(
      '#theme' => 'image',
      '#uri' => Url::fromRoute('islandora.view_datastream_view', ['object' => $islandora_object->id, 'datastream' => 'TN'])->toString(),
    );
    $variables['islandora_thumbnail_img'] = \Drupal::service("renderer")->render($img);
  }
  $variables['parent_collections'] = islandora_get_parents_from_rels_ext($islandora_object);
  $variables['metadata'] = islandora_retrieve_metadata_markup($islandora_object);
  $variables['description'] = islandora_retrieve_description_markup($islandora_object);
}
