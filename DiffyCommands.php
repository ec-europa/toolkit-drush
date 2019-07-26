<?php

namespace Drush\Commands\toolkit_drush;

use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class DiffyCommands extends DrushCommands {

    /**
     * Refresh token for diffy key.
     *
     * @command diffy:refresh-token
     * @aliases diffy:rt
     * @usage diffy:refresh-token
     *   Refresh token for diffy key.
     */
    public function refresh_token() {
        $key = getenv("DIFFY_API_KEY");
          
        if (empty($key)) {
            throw new \Exception(dt('No key provided, can not request token.'));
        }
        else {
            $ch = curl_init('https://app.diffy.website/api/auth/key');
            $payload = json_encode(array( "key"=> $key));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $token = json_decode($result)->token;
            
            if ($result !== false) {
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($statusCode === 200) {
                    $config = \Drupal::service('config.factory')->getEditable('drush_diffy.settings');
                    $config->set('diffy_token', $token)->save();
                    drush_log(dt('Diffy token refreshed.'), 'ok');
                }
                else {
                    $msg = "Curl request failed with statuscode !statuscode.";
                    throw new \Exception(dt($msg, ['!statuscode' => $statusCode]));
                }
            }
            curl_close($ch);
        }
    }

    /**
     * Request screenshots for a project.
     *
     * @command diffy:project-snapshot
     * @param $projectId Project ID for which to take a snapshot.
     * @option environment The environment for which to take a snapshot
     * @option baseUrl The base url of the site for which to take a snapshot.
     * @option wait The time to wait in between checks to see if snapshot is finished.
     * @aliases diffy:pt
     * @usage diffy:project-snapshot
     *   Request screenshots for a project.
     */
    public function project_snapshot($projectId = '', $options = ['environment' => 'production', 'baseUrl' => '', 'wait' => 30]) {
        $projectId = $this->get_diffy_project_id($projectId);
        if (!empty($projectId)) {
            $token = \Drupal::config('drush_diffy.settings')->get('diffy_token');
            $payload = [];
            if (isset($options['environment'])) {
                $payload['environment'] = $options['environment'];
            }
            $payload['baseUrl'] = $options['baseUrl'];
            $ch = curl_init("https://app.diffy.website/api/projects/$projectId/screenshots");
            $payload = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $token));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            var_dump($result);
            if ($result !== false) {
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($statusCode === 200) {
                    $previousSnapshot = \Drupal::config('drush_diffy.settings')->get('diffy_last_snapshot');
                    $config = \Drupal::service('config.factory')->getEditable('drush_diffy.settings');
                    $config->set('diffy_prev_snapshot', $previousSnapshot)->save();
                    $config = \Drupal::service('config.factory')->getEditable('drush_diffy.settings');
                    $config->set('diffy_last_snapshot', $result)->save();
                    drush_log(dt('Snapshot created: @url.', array('@url' => 'https://app.diffy.website/#/snapshots/' . $result)), 'ok');
                    $this->wait_for_snapshot($result, $options['wait']);
                }
                else {
                    $msg = "Curl request failed with statuscode !statuscode.";
                    throw new \Exception(dt($msg, ['!statuscode' => $statusCode]));
                }
            }
            curl_close($ch);
        }
    }

    /**
     * Request a comparison between environments.
     *
     * @command diffy:project-compare
     * @param $projectId Project ID for which to take comparison
     * @option environments The environments for which to comparison
     * @aliases diffy:pc
     * @usage diffy:project-compare
     *   Request a comparison between environments.
     */
    public function project_compare($projectId = '', $options = ['environments' => 'baseline-prod']) {
        $projectId = $this->get_diffy_project_id($projectId);
        if (!empty($projectId)) {
            $token = \Drupal::config('drush_diffy.settings')->get('diffy_token');
            $payload = [];
            if (isset($options['environments'])) {
                $payload['environments'] = $options['environments'];
            }
            $ch = curl_init("https://app.diffy.website/api/projects/$projectId/compare");
            $payload = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $token));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            if ($result !== false) {
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($statusCode === 200) {
                    drush_log(dt('Diff created: @url.', array('@url' => 'https://app.diffy.website/#/diffs/' . $result)), 'ok');
                }
                else {
                    $msg = "Curl request failed with statuscode !statuscode.";
                    throw new \Exception(dt($msg, ['!statuscode' => $statusCode]));
                }
            }
            curl_close($ch);
        }
    }

    /**
     * Request a diff between snapshots.
     *
     * @command diffy:project-diff
     * @param $projectId Project ID for which to take a comparison.
     * @option snapshot1 The first snapshot for the comparison.
     * @option snapshot2 The second snapshot for the comparison.
     * @aliases diffy:pd
     * @usage diffy:project-diff
     *   Request a diff between snapshots.
     */
    public function project_diff($projectId = '', $options = ['snapshot1' => '', 'snapshot2' => '']) {
        $projectId = $this->get_diffy_project_id($projectId);
        if (!empty($projectId)) {
            $token = \Drupal::config('drush_diffy.settings')->get('diffy_token');
            $payload = [];
            if (!empty($options['snapshot1'])) {
                $payload['snapshot1'] = $options['snapshot1'];
            }
            else {
                $diffyPreviousSnapshot = \Drupal::config('drush_diffy.settings')->get('diffy_prev_snapshot');
                $payload['snapshot1'] = isset($diffyPreviousSnapshot) ? $diffyPreviousSnapshot : '';
            }
            if (!empty($options['snapshot2'])) {
                $payload['snapshot2'] = $options['snapshot2'];
            }
            else {
                $diffyLastSnapshot = \Drupal::config('drush_diffy.settings')->get('diffy_last_snapshot');
                $payload['snapshot2'] = isset($diffyLastSnapshot) ? $diffyLastSnapshot : '';
            }
            $ch = curl_init("https://app.diffy.website/api/projects/$projectId/diffs");
            $payload = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $token));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            if ($result !== false) {
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($statusCode === 200) {
                    drush_log(dt('Diff created: @url.', array('@url' => 'https://app.diffy.website/#/diffs/' . $result)), 'ok');
                }
                else {
                    $msg = "Curl request failed with statuscode !statuscode.";
                    throw new \Exception(dt($msg, ['!statuscode' => $statusCode]));
                }
            }
            curl_close($ch);
        }
    }

    /**
     * Request a diff between snapshots.
     *
     * @command diffy:project-baseline
     * @param $projectId Project ID for which to take a snapshot.
     * @param $snapshotId Snapshot ID which to set as a baseline.
     * @aliases diffy:pb
     * @usage diffy:project-baseline
     *   Request a diff between snapshots.
     */
    public function project_baseline($projectId = '', $snapshotId = '') {
        $projectId = $this->get_diffy_project_id($projectId);
        if (!empty($projectId)) {
            $token = \Drupal::config('drush_diffy.settings')->get('diffy_token');
            if (empty($snapshotId)) {
                $snapshotId = \Drupal::config('drush_diffy.settings')->get('diffy_last_snapshot');
            }
            $ch = curl_init("https://app.diffy.website/api/projects/$projectId/set-base-line-set/$snapshotId");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $token));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            if ($result !== false) {
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($statusCode === 200) {
                    drush_log(dt('Snapshot @number set as baseline.', array('@number' => $snapshotId)), 'ok');
                }
                else {
                    $msg = "Curl request failed with statuscode !statuscode.";
                    throw new \Exception(dt($msg, ['!statuscode' => $statusCode]));
                }
            }
        curl_close($ch);
        }
    }

    protected function get_diffy_project_id($projectId) {
        if (empty($projectId)) {
            $projectId = \Drupal::config('drush_diffy.settings')->get('diffy_project_id');
        }
        else {
            $config = \Drupal::service('config.factory')->getEditable('drush_diffy.settings');
            $config->set('diffy_project_id', $projectId)->save();
        }
        if (empty($projectId)) {
            throw new \Exception(dt('No project id provided, can not make API callback.'));
        }
        return $projectId;
    }

    protected function wait_for_snapshot($snapshotId, $wait) {
        if ($wait > 0) {
            $token = \Drupal::config('drush_diffy.settings')->get('diffy_token');
            $ch = curl_init("https://app.diffy.website/api/snapshots/$snapshotId");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $token));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = json_decode(curl_exec($ch));
        
            if ($result->state < 2) {
                drush_log(dt('Snapshot in progress: @results of @items.', array('@results' => $result->status->results, '@items' => $result->status->items)), 'ok');
                sleep($wait);
                $this->wait_for_snapshot($snapshotId, $wait);
            }
            else {
                drush_log(dt('Snapshot is finished: @results of @items.', array('@results' => $result->status->results, '@items' => $result->status->items)), 'ok');
            }
        }
    }
}