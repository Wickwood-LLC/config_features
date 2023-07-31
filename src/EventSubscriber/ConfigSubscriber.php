<?php

namespace Drupal\config_features\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

class ConfigSubscriber implements EventSubscriberInterface {

  /**                                         
   * The active config storage.                 
   *                                                                                                      
   * @var \Drupal\Core\Config\StorageInterface                                                            
   */              
  protected $activeStorage;                                                                               
                                                                                                          
  /**                                            
   * The sync config storage.                                                                             
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $syncStorage;

  /**
   * The config_ignore_uuid.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * DirectoriesConfigSubscriber constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config active storage.
   * @param \Drupal\Core\Config\StorageInterface $sync_storage
   *   The sync config storage.
   */
  public function __construct(StorageInterface $config_storage, StorageInterface $sync_storage, ConfigFactoryInterface $config_factory) {
    $this->activeStorage = $config_storage;
    $this->syncStorage = $sync_storage;
    $this->config = $config_factory->get('config_ignore_uuid.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = ['onImportTransform', -100];
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = ['onExportTransform', -100];
    return $events;
  }
  
  /**
   * The storage is transformed for importing.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onImportTransform(StorageTransformEvent $event) {
    $uuids_to_replace = [];
    $transformation_storage = $event->getStorage();
    $config_names = $transformation_storage->listAll();
    foreach ($config_names as $config_name) {
      if (self::matchConfigName($config_name)) {
        $data = $transformation_storage->read($config_name);
        $active_data = $this->activeStorage->read($config_name);
        if ($data && $active_data && !empty($data['uuid']) && !empty($active_data['uuid']) && $data['uuid'] != $active_data['uuid']) {
          $uuids_to_replace[$data['uuid']] = $active_data['uuid'];
          $data['uuid'] = $active_data['uuid'];
          $transformation_storage->write($config_name, $data);
        }
      }
    }
    

    if (!empty($uuids_to_replace)) {
      foreach ($config_names as $config_name) {
        $data = $transformation_storage->read($config_name);
        $raw_data = $transformation_storage->encode($data);
        $sum_count = 0;
        foreach ($uuids_to_replace as $new => $original) {
          $count = 0;
          $raw_data = str_replace($new, $original, $raw_data, $count);
          $sum_count += $count;
        }
        if ($sum_count) {
          // Only care to write back if any replacement happened.
          $data = $transformation_storage->decode($raw_data);
          $transformation_storage->write($config_name, $data);
        }
      }
    }
  }

  /**
   * Match a config entity name against the list of ignored config entities.
   *
   * @param string $config_name
   *   The name of the config entity to match against all ignored entities.
   *
   * @return bool
   *   True, if the config entity is to be ignored, false otherwise.
   */
  public static function matchConfigName($config_name) {
    static $configs_to_ignore_uuid_change = NULL;
    if (!$configs_to_ignore_uuid_change) {
      $configs_to_ignore_uuid_change = \Drupal::service('config.factory')->get('config_ignore_uuid.settings')->get('configs_to_ignore_uuid_change');
    }

    foreach ($configs_to_ignore_uuid_change as $config_ignore_uuid_setting) {
      // Split the ignore settings so that we can ignore individual keys.
      $ignore = explode(':', $config_ignore_uuid_setting, 2);
      if (self::wildcardMatch($ignore[0], $config_name)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Checks if a string matches a given wildcard pattern.
   *
   * @param string $pattern
   *   The wildcard pattern to me matched.
   * @param string $string
   *   The string to be checked.
   *
   * @return bool
   *   TRUE if $string string matches the $pattern pattern.
   */
  protected static function wildcardMatch($pattern, $string) {
    $pattern = '/^' . preg_quote($pattern, '/') . '$/';
    $pattern = str_replace('\*', '.*', $pattern);
    return (bool) preg_match($pattern, $string);
  }

  /**
   * The storage is transformed for exporting.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onExportTransform(StorageTransformEvent $event) {
    // $uuids_to_replace = [];
    // $transformation_storage = $event->getStorage();
    // $config_names = $transformation_storage->listAll();
    // foreach ($config_names as $config_name) {
    //   if ($this->matchConfigName($config_name)) {
    //     $data = $transformation_storage->read($config_name);
    //     $active_data = $this->activeStorage->read($config_name);
    //     if ($data && $active_data && !empty($data['uuid']) && !empty($active_data['uuid']) && $data['uuid'] != $active_data['uuid']) {
    //       $uuids_to_replace[$data['uuid']] = $active_data['uuid'];
    //       $data['uuid'] = $active_data['uuid'];
    //       $transformation_storage->write($config_name, $data);
    //     }
    //   }
    // }
    

    // if (!empty($uuids_to_replace)) {
    //   foreach ($config_names as $config_name) {
    //     $data = $transformation_storage->read($config_name);
    //     $raw_data = $transformation_storage->encode($data);
    //     $sum_count = 0;
    //     foreach ($uuids_to_replace as $new => $original) {
    //       $count = 0;
    //       $raw_data = str_replace($new, $original, $raw_data, $count);
    //       $sum_count += $count;
    //     }
    //     if ($sum_count) {
    //       // Only care to write back if any replacement happened.
    //       $data = $transformation_storage->decode($raw_data);
    //       $transformation_storage->write($config_name, $data);
    //     }
    //   }
    // }
  }
}
