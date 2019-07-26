<?php

namespace Drush\Commands\toolkit_drush;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\Extension\InfoParser;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class CheckModulesCommands extends DrushCommands
{
    /**
     * Gives a list of non authorised modules and/or security updates.
     *
     * @command toolkit:check-modules-authorized-security
     *
     * @param $project_id project ID for which to check modules
     * @aliases toolkit:cmas
     * @usage toolkit:check-modules-authorized-security
     *   Gives a list of non authorised modules and/or security updates.
     */
    public function check_modules_authorized_security($project_id = null)
    {
        // Get list of all modules in the project.
        $modules = $this->checkProjectModules();
        // Get the module reviews list.
        $d7ModulesList = $this->getModulesList();
        // Instantiate arrays.
        $modulesName = [];
        $modulesArray = [];
        if (!empty($modules) && !empty($d7ModulesList)) {
            if (null !== $project_id) {
                $config = \Drupal::service('config.factory')->getEditable('drush_toolkit.settings');
                $config->set('project_id', $project_id)->save();
            }
            // Check update module status.
            $updateStatus = system_get_info('module', 'update');
            // Enable 'Update Manager' module if it's disabled.
            if (empty($updateStatus)) {
                \Drupal::service('module_installer')->install(['update']);
                $status = false;
            } else {
                $status = true;
            }

            foreach ($d7ModulesList as $module) {
                // Get list of modules authorised for all projects.
                if ('0' == $module['restricted_us']) {
                    $modulesName[] = $module['name'];
                    $modulesArray[] = $module;
                }
                // Get list of restricted modules.
                if ('0' != $module['restricted_us'] && '1' != $module['restricted_us']) {
                    $restrictedByProject = explode(',', $module['restricted_us']);

                    foreach ($restrictedByProject as $project) {
                        // Check the project Id and add the restricted modules by project (if is
                        // the case) to the list of authorised modules.
                        if (null !== $project_id && $project_id == $project) {
                            array_push($modulesName, $module['name']);
                            array_push($modulesArray, $module);
                        }
                    }
                }
            }

            foreach ($modules as $modulePath => $moduleId) {
                $modulePath = drupal_get_path('module', $moduleId);
                $moduleInfo = InfoParser::parse($modulePath.'/'.$moduleId.'.info');

                if (false !== strpos($modulePath, 'sites/') &&
            !empty($moduleInfo['version']) && $moduleInfo['project'] == $moduleId) {
                    if (!in_array($moduleId, $modulesName)) {
                        drush_log('The use of the module '.$moduleId.' is not authorised by the QA team.', LogLevel::ERROR);
                    }

                    // Check for security updates.
                    module_load_include('inc', 'update', 'update.report');
                    $availableUpdates = update_get_available(true);
                    $moduleAvailableUpdates = update_calculate_project_data($availableUpdates);

                    if (isset($moduleAvailableUpdates[$moduleId]['security updates'])) {
                        $modulePath = drupal_get_path('module', $moduleAvailableUpdates[$moduleId]['name']);
                        drush_log('The module '.$moduleAvailableUpdates[$moduleId]['name'].' with version '.$moduleAvailableUpdates[$moduleId]['existing_version'].' has a security update! Update to '.$moduleAvailableUpdates[$moduleId]['recommended'], LogLevel::ERROR);
                    }
                }
            }
            // Turn off again 'Update Manager' module, in case was initially disabled.
            if (true != $status) {
                \Drupal::service('module_installer')->uninstall(['update']);
                unset($status);
            }
            // Delete variable 'project_id'.
            if (null !== $project_id) {
                variable_del('project_id');
            }
        }
    }

    /**
     * Gives a list of non authorised modules and/or security updates.
     *
     * @command toolkit:check-modules-minimum-version
     *
     * @param $project_id project ID for which to check modules
     * @aliases toolkit:cmmv
     * @usage toolkit:check-modules-minimum-version
     *   Gives a list of non authorised modules and/or security updates.
     */
    public function check_modules_minimum_version($project_id = null)
    {
        // Get list of all modules in the project.
        $modules = $this->checkProjectModules();
        // Get the module reviews list.
        $d7ModulesList = $this->getModulesList();
        // Instantiate arrays.
        $modulesName = [];
        $modulesArray = [];
        if (!empty($modules) && !empty($d7ModulesList)) {
            if (null !== $project_id) {
                $config = \Drupal::service('config.factory')->getEditable('drush_toolkit.settings');
                $config->set('project_id', $project_id)->save();
            }

            // Get list of modules authorised for all projects.
            foreach ($d7ModulesList as $module) {
                if ('0' == $module['restricted_us']) {
                    $modulesName[] = $module['name'];
                    $modulesArray[] = $module;
                }
                // Get list of restricted modules.
                if ('0' != $module['restricted_us'] && '1' != $module['restricted_us']) {
                    $restrictedByProject = explode(',', $module['restricted_us']);

                    foreach ($restrictedByProject as $project) {
                        // Check the project Id and add the restricted modules by project (if is
                        // the case) to the list of authorised modules.
                        if (null !== $project_id && $project_id == $project) {
                            array_push($modulesName, $module['name']);
                            array_push($modulesArray, $module);
                        }
                    }
                }
            }

            foreach ($modules as $module => $moduleId) {
                $modulePath = drupal_get_path('module', $moduleId);
                $moduleInfo = InfoParser::parse($modulePath.'/'.$moduleId.'.info');

                if (false !== strpos($modulePath, 'sites/') &&
        !empty($moduleInfo['version']) && $moduleInfo['project'] == $moduleId) {
                    // Compare actual module version with the minimum version authorised.
                    $moduleName = $moduleInfo['project'];
                    $getMinVersion = $this->searchForVersion($moduleName, $modulesArray);
                    $versionCompare = version_compare($moduleInfo['version'], $getMinVersion);

                    if (-1 == $versionCompare) {
                        drush_log('The module '.$moduleId.' needs to be updated from '.$moduleInfo['version'].' to '.$getMinVersion, LogLevel::WARNING);
                    }
                }
            }
            // Delete variable 'project_id'.
            if (null !== $project_id) {
                variable_del('project_id');
            }
        }
    }

    // Helper function to get the minimum accepted module version.
    public function searchForVersion($moduleName, $modulesArray)
    {
        foreach ($modulesArray as $module) {
            if ($module['name'] === $moduleName) {
                return $module['version'];
            }
        }
    }

    // Helper function to get the list of authorised modules.
    public function getModulesList()
    {
        // Get list of authorised modules.
        $url = 'https://raw.githubusercontent.com/ec-europa/qa-tests/components/module_list.json';
        $github_api_token = getenv('GITHUB_API_TOKEN');
        if (!empty($github_api_token)) {
            $request_headers = array(
      'Authorization: token '.$github_api_token,
    );

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($curl);

            // If request did not fail.
            if (false !== $result) {
                // Request was ok? check response code.
                $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if (200 == $statusCode) {
                    $d7ModulesList = json_decode($result, true);
                } else {
                    drush_set_error(dt('Curl request failed with error code @statusCode.', array('@statusCode' => $statusCode)));
                }
            }

            return $d7ModulesList;
        } else {
            drush_log(dt('GITHUB_API_TOKEN not set. Not executing command.'), 'warning');
        }
    }

    // Helper function to discover all existing modules in project,
    // that are enabled or disabled.
    public function checkProjectModules()
    {
        $listing = new ExtensionDiscovery(DRUPAL_ROOT);
        $moduleList = $listing->scan('module');
        $modules = [];
        foreach ($moduleList as $module) {
            if (false !== strpos($module->getPath(), 'modules/')) {
                $modules[] = $module->getName();
            }
        }
        // Exclude obsolete module file 'views_export.module' from the list.
        $modules = array_diff($modules, array('views_export'));

        return $modules;
    }
}
