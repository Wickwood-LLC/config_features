<?php

namespace Drupal\config_features\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Configuration Split setting entity.
 *
 * @ConfigEntityType(
 *   id = "config_feature",
 *   label = @Translation("Configuration Feature"),
 *   handlers = {
 *     "view_builder" = "Drupal\config_features\ConfigFeatureEntityViewBuilder",
 *     "list_builder" = "Drupal\config_features\ConfigFeatureEntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\config_features\Form\ConfigFeatureEntityForm",
 *       "edit" = "Drupal\config_features\Form\ConfigFeatureEntityForm",
 *       "delete" = "Drupal\config_features\Form\ConfigFeatureEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\config_features\ConfigFeatureEntityHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "config_feature",
 *   admin_permission = "administer configuration features",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/development/configuration/config-feature/{config_feature}",
 *     "add-form" = "/admin/config/development/configuration/config-feature/add",
 *     "edit-form" = "/admin/config/development/configuration/config-feature/{config_feature}/edit",
 *     "delete-form" = "/admin/config/development/configuration/config-feature/{config_feature}/delete",
 *     "enable" = "/admin/config/development/configuration/config-feature/{config_feature}/enable",
 *     "disable" = "/admin/config/development/configuration/config-feature/{config_feature}/disable",
 *     "activate" = "/admin/config/development/configuration/config-feature/{config_feature}/activate",
 *     "deactivate" = "/admin/config/development/configuration/config-feature/{config_feature}/deactivate",
 *     "import" = "/admin/config/development/configuration/config-feature/{config_feature}/import",
 *     "export" = "/admin/config/development/configuration/config-feature/{config_feature}/export",
 *     "collection" = "/admin/config/development/configuration/config-feature"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "weight",
 *     "status",
 *     "folder",
 *     "configs_shared",
 *     "configs_excluded",
 *   }
 * )
 */
class ConfigFeatureEntity extends ConfigEntityBase implements ConfigFeatureEntityInterface {

  /**
   * The Configuration Split setting ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Configuration Split setting label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Configuration Split setting description.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The weight of the configuration for sorting.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The status, whether to be used by default.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * The folder to export to.
   *
   * @var string
   */
  protected $folder = '';

  /**
   * List of configurations to share.
   *
   * @var string[]
   */
  protected $configs_shared = [];

  /**
   * List of configurations to exclude.
   *
   * @var string[]
   */
  protected $configs_excluded = [];

}
