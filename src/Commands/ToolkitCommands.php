<?php

namespace Drupal\toolkit_drush\Commands;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\Extension\InfoParserDynamic;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * @SuppressWarnings(PHPMD)
 *
 * @todo: This code should be refactored to meet PHPMD standards.
 */
class ToolkitCommands extends DrushCommands {

  /**
   * Gives a list of non authorised modules and/or security updates.
   *
   * @param int|null $project_id
   *   project ID for which to check modules.
   *
   * @command toolkit:check-modules-authorized-security
   *
   * @aliases toolkit:cmas
   * @usage toolkit:check-modules-authorized-security
   *   Gives a list of non authorised modules and/or security updates.
   */
  public function checkModulesAuthorizedSecurity($project_id = NULL) {
    // Get list of all modules in the project.
    $modules = $this->checkProjectModules();
    // Get the module reviews list.
    $d8ModulesList = $this->getModulesList();
    // Instantiate arrays.
    $modulesName = [];
    $modulesArray = [];
    if (!empty($modules) && !empty($d8ModulesList)) {
      if (NULL !== $project_id) {
        $config = \Drupal::service('config.factory')->getEditable('toolkit_drush.settings');
        $config->set('project_id', $project_id)->save();
      }
      // Check update module status.
      $updateStatus = system_get_info('module', 'update');
      // Enable 'Update Manager' module if it's disabled.
      if (empty($updateStatus)) {
        \Drupal::service('module_installer')->install(['update']);
        $status = FALSE;
      }
      else {
        $status = TRUE;
      }

      $modulesInDev = $this->getModulesInDev();

      foreach ($d8ModulesList as $module) {
        // Get list of modules authorised for all projects.
        if ('0' == $module['restricted_use']) {
          $modulesName[] = $module['name'];
          $modulesArray[] = $module;
        }
        // Get list of restricted modules.
        if ('0' != $module['restricted_use'] && '1' != $module['restricted_use']) {
          $restrictedByProject = explode(',', $module['restricted_use']);

          foreach ($restrictedByProject as $project) {
            // Check the project Id and add the restricted modules by project
            // (if is the case) to the list of authorised modules.
            if (NULL !== $project_id && $project_id == $project) {
              array_push($modulesName, $module['name']);
              array_push($modulesArray, $module);
            }
          }
        }
      }

      foreach ($modules as $modulePath => $moduleId) {
        $modulePath = drupal_get_path('module', $moduleId);
        $infoParser = new InfoParserDynamic();
        $moduleInfo = $infoParser->parse($modulePath . '/' . $moduleId . '.info.yml');

        if (FALSE !== strpos($modulePath, 'modules/contrib/') &&
        !empty($moduleInfo['version']) && $moduleInfo['project'] == $moduleId &&
        !in_array($moduleId, $modulesInDev)) {
          if (!in_array($moduleId, $modulesName)) {
            drush_log('The use of the module ' . $moduleId . ' is not authorised by the QA team.', LogLevel::ERROR);
          }

          // Check for security updates.
          // module_load_include('inc', 'update', 'update.report');.
          $availableUpdates = update_get_available(TRUE);
          $moduleAvailUpdates = update_calculate_project_data($availableUpdates);

          if (isset($moduleAvailUpdates[$moduleId]['security updates'])) {
            $modulePath = drupal_get_path('module', $moduleAvailUpdates[$moduleId]['name']);
            drush_log('The module ' . $moduleAvailUpdates[$moduleId]['name'] . ' with version ' . $moduleAvailUpdates[$moduleId]['existing_version'] . ' has a security update! Update to ' . $moduleAvailUpdates[$moduleId]['recommended'], LogLevel::ERROR);
          }
        }
      }
      // Turn off again 'Update Manager' module, in case was initially disabled.
      if (TRUE != $status) {
        \Drupal::service('module_installer')->uninstall(['update']);
        unset($status);
      }
      // Delete variable 'project_id'.
      if (NULL !== $project_id) {
        Drupal::configFactory()->getEditable('toolkit_drush.settings.project_id')->delete();
      }
    }
  }

  /**
   * Gives a list of non authorised modules and/or security updates.
   *
   * @param int|null $project_id
   *   project ID for which to check modules.
   *
   * @command toolkit:check-modules-minimum-version
   *
   * @aliases toolkit:cmmv
   * @usage toolkit:check-modules-minimum-version
   *   Gives a list of non authorised modules and/or security updates.
   */
  public function checkModulesMinimumVersion($project_id = NULL) {
    // Get list of all modules in the project.
    $modules = $this->checkProjectModules();
    // Get the module reviews list.
    $d8ModulesList = $this->getModulesList();
    // Instantiate arrays.
    $modulesName = [];
    $modulesArray = [];
    if (!empty($modules) && !empty($d8ModulesList)) {
      if (NULL !== $project_id) {
        $config = \Drupal::service('config.factory')->getEditable('toolkit_drush.settings');
        $config->set('project_id', $project_id)->save();
      }

      $modulesInDev = $this->getModulesInDev();

      // Get list of modules authorised for all projects.
      foreach ($d8ModulesList as $module) {
        if ('0' == $module['restricted_use']) {
          $modulesName[] = $module['name'];
          $modulesArray[] = $module;
        }
        // Get list of restricted modules.
        if ('0' != $module['restricted_use'] && '1' != $module['restricted_use']) {
          $restrictedByProject = explode(',', $module['restricted_use']);

          foreach ($restrictedByProject as $project) {
            // Check the project Id and add the restricted modules by project
            // (if is the case) to the list of authorised modules.
            if (NULL !== $project_id && $project_id == $project) {
              array_push($modulesName, $module['name']);
              array_push($modulesArray, $module);
            }
          }
        }
      }

      foreach ($modules as $module => $moduleId) {
        $modulePath = drupal_get_path('module', $moduleId);
        $infoParser = new InfoParserDynamic();
        $moduleInfo = $infoParser->parse($modulePath . '/' . $moduleId . '.info.yml');

        if (FALSE !== strpos($modulePath, 'modules/contrib') &&
        !empty($moduleInfo['version']) && $moduleInfo['project'] == $moduleId &&
        !in_array($moduleId, $modulesInDev)) {
          // Compare actual module version with the minimum version authorised.
          $moduleName = $moduleInfo['project'];
          $getMinVersion = $this->searchForVersion($moduleName, $modulesArray);
          $versionCompare = version_compare($moduleInfo['version'], $getMinVersion);

          if (-1 == $versionCompare) {
            drush_log('The module ' . $moduleId . ' needs to be updated from ' . $moduleInfo['version'] . ' to ' . $getMinVersion, LogLevel::WARNING);
          }
        }
      }
      // Delete variable 'project_id'.
      if (NULL !== $project_id) {
        Drupal::configFactory()->getEditable('toolkit_drush.settings.project_id')->delete();
      }
    }
  }

  /**
   * Checks for non-used modules within path crossreferenced with composer.lock.
   *
   * @command toolkit:toolkit-check-modules-unused
   *
   * @options $path     The path in which to check modules.
   * @options $lockfile The composer lock file to crossreference modules with.
   * @aliases toolkit:cmu
   * @usage toolkit:toolkit-check-modules-unused
   *   Gives a list of non authorised modules and/or security updates.
   */
  public function drushToolkitCheckModulesUnused($options = ['path' => 'modules/contrib', 'lockfile' => '../composer.lock']) {
    $composer = $options['lockfile'];
    $path = $options['path'];
    // If referenced make file does not exist, trow a warning.
    if (!file_exists($composer)) {
      drush_log(dt('Composer @composer does not exist. Showing all disabled modules in @path.', ['@composer' => $composer, '@path' => $path]), 'warning');
    }
    else {
      $composerLock = json_decode(file_get_contents($composer));
    }

    $modulesInCode = [];
    if (isset($composerLock->packages)) {
      foreach ($composerLock->packages as $package) {
        if ($package->type === 'drupal-module') {
          $modulesInCode[] = substr($package->name, ($pos = strpos($package->name, '/')) !== FALSE ? $pos + 1 : 0);
        }
      }
    }

    $modules = system_rebuild_module_data();
    foreach ($modules as $module) {
      if (strpos($module->getPath(), $path) !== FALSE) {
        $moduleName = $module->getName();
        if ($module->status === 0 && empty($modulesInCode)) {
          drush_log(dt('Module @name is not enabled.', ['@name' => $moduleName]), 'warning');
        }
        if ($module->status === 0 && !empty($modulesInCode)) {
          if (in_array($moduleName, $modulesInCode)) {
            drush_log(dt('Module @name is not enabled.', ['@name' => $moduleName]), 'warning');
          }
        }
      }
    }
  }

  /**
   * Helper function to get the development modules.
   */
  public function getModulesInDev($composer = '../composer.lock') {
    $modulesInDev = [];
    if (file_exists($composer)) {
      $composerLock = json_decode(file_get_contents($composer));
      if (isset($composerLock->{'packages-dev'})) {
        foreach ($composerLock->{'packages-dev'} as $package) {
          if ($package->type === 'drupal-module') {
            $modulesInDev[] = substr($package->name, ($pos = strpos($package->name, '/')) !== FALSE ? $pos + 1 : 0);
          }
        }
      }
    }
    return $modulesInDev;
  }

  /**
   * Helper function to get the minimum accepted module version.
   */
  public function searchForVersion($moduleName, $modulesArray) {
    foreach ($modulesArray as $module) {
      if ($module['name'] === $moduleName) {
        return $module['version'];
      }
    }
  }

  /**
   * Helper function to get the list of authorised modules.
   */
  public function getModulesList() {
    // Get list of authorised modules.
    $url = 'https://webgate.ec.europa.eu/fpfis/qa/api/v1/package-reviews?machine_name=&version=8.x&type=module&review_status=All';

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($curl);

    // If request did not fail.
    if (FALSE !== $result) {
      // Request was ok? check response code.
      $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      if (200 == $statusCode) {
        $d8ModulesList = json_decode($result, TRUE);
      }
      else {
        drush_set_error(dt('Curl request failed with error code @statusCode.', ['@statusCode' => $statusCode]));
      }
    }

    return $d8ModulesList;
  }

  /**
   * Helper function to discover all existing modules in project.
   */
  public function checkProjectModules() {
    $listing = new ExtensionDiscovery(DRUPAL_ROOT);
    $moduleList = $listing->scan('module');
    $modules = [];
    foreach ($moduleList as $module) {
      if (FALSE !== strpos($module->getPath(), 'modules/')) {
        $modules[] = $module->getName();
      }
    }
    // Exclude obsolete module file 'views_export.module' from the list.
    $modules = array_diff($modules, ['views_export']);

    return $modules;
  }

}
