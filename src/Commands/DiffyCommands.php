<?php

namespace Drupal\toolkit_drush\Commands;

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
  public function refreshToken() {
    $key = getenv("DIFFY_API_KEY");

    if (empty($key)) {
      throw new \Exception(dt('No key provided, can not request token.'));
    }
    else {
      $ch = curl_init('https://app.diffy.website/api/auth/key');
      $payload = json_encode(["key" => $key]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      $result = curl_exec($ch);
      $token = json_decode($result)->token;

      if ($result !== FALSE) {
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
   * @param string $projectId
   *   Project ID for which to take a snapshot.
   * @param array $options
   *   The options of this command.
   *
   * @command diffy:project-snapshot
   *
   * @option environment The environment for which to take a snapshot
   * @option baseUrl The base url of the site for which to take a snapshot.
   * @option wait The time to wait in between checks to see if snapshot is
   *  finished.
   * @aliases diffy:pt
   * @usage diffy:project-snapshot
   *   Request screenshots for a project.
   */
  public function projectSnapshot($projectId = '', array $options = [
    'environment' => 'production',
    'baseUrl' => '',
    'wait' => 30,
  ]) {
    $projectId = $this->getDiffyProjectId($projectId);
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
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $token]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      $result = curl_exec($ch);
      if ($result !== FALSE) {
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($statusCode === 200) {
          $previousSnapshot = \Drupal::config('drush_diffy.settings')->get('diffy_last_snapshot');
          $config = \Drupal::service('config.factory')->getEditable('drush_diffy.settings');
          $config->set('diffy_prev_snapshot', $previousSnapshot)->save();
          $config = \Drupal::service('config.factory')->getEditable('drush_diffy.settings');
          $config->set('diffy_last_snapshot', $result)->save();
          drush_log(dt('Snapshot created: @url.', ['@url' => 'https://app.diffy.website/#/snapshots/' . $result]), 'ok');
          $this->waitForSnapshot($result, $options['wait']);
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
   * @param string $projectId
   *   Project ID for which to take comparison.
   * @param array $options
   *   The options of this command.
   *
   * @command diffy:project-compare
   *
   * @option environments The environments for which to comparison
   * @aliases diffy:pc
   * @usage diffy:project-compare
   *   Request a comparison between environments.
   */
  public function projectCompare($projectId = '', array $options = ['environments' => 'baseline-prod']) {
    $projectId = $this->getDiffyProjectId($projectId);
    if (!empty($projectId)) {
      $token = \Drupal::config('drush_diffy.settings')->get('diffy_token');
      $payload = [];
      if (isset($options['environments'])) {
        $payload['environments'] = $options['environments'];
      }
      $ch = curl_init("https://app.diffy.website/api/projects/$projectId/compare");
      $payload = json_encode($payload);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $token]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      $result = curl_exec($ch);
      if ($result !== FALSE) {
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($statusCode === 200) {
          drush_log(dt('Diff created: @url.', ['@url' => 'https://app.diffy.website/#/diffs/' . $result]), 'ok');
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
   * @param string $projectId
   *   Project ID for which to take a comparison.
   * @param array $options
   *   The options of this command.
   *
   * @command diffy:project-diff
   *
   * @option snapshot1 The first snapshot for the comparison.
   * @option snapshot2 The second snapshot for the comparison.
   * @aliases diffy:pd
   * @usage diffy:project-diff
   *   Request a diff between snapshots.
   */
  public function projectDiff($projectId = '', array $options = ['snapshot1' => '', 'snapshot2' => '']) {
    $projectId = $this->getDiffyProjectId($projectId);
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
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $token]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      $result = curl_exec($ch);
      if ($result !== FALSE) {
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($statusCode === 200) {
          drush_log(dt('Diff created: @url.', ['@url' => 'https://app.diffy.website/#/diffs/' . $result]), 'ok');
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
   * @param string $projectId
   *   Project ID for which to take a snapshot.
   * @param string $snapshotId
   *   Snapshot ID which to set as a baseline.
   *
   * @command diffy:project-baseline
   *
   * @aliases diffy:pb
   * @usage diffy:project-baseline
   *   Request a diff between snapshots.
   */
  public function projectBaseline($projectId = '', $snapshotId = '') {
    $projectId = $this->getDiffyProjectId($projectId);
    if (!empty($projectId)) {
      $token = \Drupal::config('drush_diffy.settings')->get('diffy_token');
      if (empty($snapshotId)) {
        $snapshotId = \Drupal::config('drush_diffy.settings')->get('diffy_last_snapshot');
      }
      $ch = curl_init("https://app.diffy.website/api/projects/$projectId/set-base-line-set/$snapshotId");
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $token]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      $result = curl_exec($ch);
      if ($result !== FALSE) {
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($statusCode === 200) {
          drush_log(dt('Snapshot @number set as baseline.', ['@number' => $snapshotId]), 'ok');
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
   * Helper function to retreive the project id.
   */
  protected function getDiffyProjectId($projectId) {
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

  /**
   * Helper function to wait for snapshot.
   */
  protected function waitForSnapshot($snapshotId, $wait) {
    if ($wait > 0) {
      $token = \Drupal::config('drush_diffy.settings')->get('diffy_token');
      $ch = curl_init("https://app.diffy.website/api/snapshots/$snapshotId");
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $token]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      $result = json_decode(curl_exec($ch));

      if ($result->state < 2) {
        drush_log(dt('Snapshot in progress: @results of @items.', ['@results' => $result->status->results, '@items' => $result->status->items]), 'ok');
        sleep($wait);
        $this->waitForSnapshot($snapshotId, $wait);
      }
      else {
        drush_log(dt('Snapshot is finished: @results of @items.', ['@results' => $result->status->results, '@items' => $result->status->items]), 'ok');
      }
    }
  }

}
