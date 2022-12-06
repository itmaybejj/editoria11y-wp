<?php
/**
 * 
 * 
 * @package         Editoria11y
 */

/**
 * Service description.
 */
class Api {

  /**
   *
   */
  public function test_results($results) {
    $now = time();
    // Confirm result_names is array?
    $this->validate_not_null($results["page_title"]);
    $this->validate_number($results["page_count"]);
    $this->validate_path($results["page_path"]);
    foreach ($results["results"] as $key => $value) {
      $this->validate_number($value);

      // @todo handle page parameters that change content
      if ($results["page_count"] > 0) {
        $this->validate_not_null($key);
        $this->connection->merge("editoria11y_results")
              // Track the type and count of issues detected on this page.
          ->insertFields([
            'page_title' => $results["page_title"],
            'page_url' => $results["page_url"],
            'page_result_count' => $results["page_count"],
            'entity_type' => $results["entity_type"],
            'result_key' => $key,
            'result_count' => $value,
            'updated' => $now,
            'created' => $now,
          ])
                // Update the "last seen" date of the page.
          ->updateFields([
            'page_title' => $results["page_title"],
            'page_url' => $results["page_url"],
            'page_result_count' => $results["page_count"],
            'entity_type' => $results["entity_type"],
            'result_key' => $key,
            'result_name_count' => $value,
            'updated' => $now,
          ])
          ->keys([
            'page_path' => $results["page_path"],
            'result_name' => $key,
          ])
          ->execute();
      }

      // Update the last seen date for hidden issues.
      $this->connection->update("editoria11y_dismissals")
        ->fields([
          'stale' => 0,
          'updated' => $now,
        ])
        ->condition('page_path', $results["page_path"])
        ->condition('result_name', $key)
        ->condition('route_name', $results["route_name"])
        ->execute();
    }

    // Update the last seen date for marked-as-ok issues.
    foreach ($results["oks"] as $key => $value) {
      $this->validate_not_null($key);
      // Update the "last seen" date for all issues that still exist.
      $this->connection->update("editoria11y_dismissals")
        ->fields([
          'stale' => 0,
          'updated' => $now,
        ])
        ->condition('page_path', $results["page_path"])
        ->condition('result_name', $value)
        ->condition('route_name', $results["route_name"])
        ->execute();
    }

    // Set the stale flag for dismissals that were NOT updated.
    // We do not auto-delete them as some may come and go based on views.
    // @todo config or button to delete stale items to prevent creep?
    $this->connection->update("editoria11y_dismissals")
      ->fields([
        'stale' => 1,
      ])
      ->condition('page_path', $results["page_path"])
      ->condition('updated', $now, '!=')
      ->execute();

    // Remove any test results that no longer exist.
    $this->connection->delete("editoria11y_results")
      ->condition('page_path', $results["page_path"])
      ->condition('updated', $now, '!=')
      ->execute();

    Cache::invalidateTags(['editoria11y:dashboard']);
  }

  /**
   *
   */
  public function purge_page($page) {
    $this->validate_path($page["page_path"]);

    $this->connection->delete("editoria11y_dismissals")
      ->condition('page_path', $page["page_path"])
      ->execute();
    $this->connection->delete("editoria11y_results")
      ->condition('page_path', $page["page_path"])
      ->execute();
    // Clear cache for the referring page and dashboard.
    Cache::invalidateTags(['editoria11y:dismissals_' . preg_replace('/[^a-zA-Z0-9]/', '', $page["page_path"]), 'editoria11y:dashboard']);
  }

  /**
   *
   */
  public function purge_dismissal($data) {
    $this->validate_path($data["page_path"]);
    $this->validate_not_null($data["result_name"]);

    $this->connection->delete("editoria11y_dismissals")
      ->condition('page_path', $data["page_path"])
      ->condition('result_name', $data["result_name"])
      ->condition('dismissal_status', $data["marked"])
      ->condition('uid', $data["by"])
      ->execute();
    // Clear cache for the referring page and dashboard.
    Cache::invalidateTags(['editoria11y:dismissals_' . preg_replace('/[^a-zA-Z0-9]/', '', $data["page_path"]), 'editoria11y:dashboard']);
  }

  /**
   *
   */
  public function dismiss(string $operation, $dismissal) {
    $this->validate_path($dismissal["page_path"]);

    if ($operation == "reset") {
      // Reset ignores for the current user.
      $this->connection->delete("editoria11y_dismissals")
        ->condition('route_name', $dismissal["route_name"])
        ->condition('page_path', $dismissal["page_path"])
        ->condition('dismissal_status', "hide")
        ->condition('uid', $this->account->id())
        ->execute();
      if ($this->account->hasPermission('mark as ok in editoria11y')) {
        // Reset "Mark OK" for the super-user.
        $this->connection->delete("editoria11y_dismissals")
          ->condition('route_name', $dismissal["route_name"])
          ->condition('page_path', $dismissal["page_path"])
          ->condition('dismissal_status', "ok")
          ->execute();
      }
    }
    else {
      $this->validate_dismissal_status($operation);
      $this->validate_not_null($dismissal["result_name"]);
      $this->validate_not_null($dismissal["result_key"]);

      $now = time();

      $this->connection->merge("editoria11y_dismissals")
        ->insertFields([
          'page_path' => $dismissal["page_path"],
          'page_title' => $dismissal["page_title"],
          'route_name' => $dismissal["route_name"],
          'entity_type' => $dismissal["entity_type"],
          'page_language' => $dismissal["language"],
          'uid' => $this->account->id(),
          'element_id' => $dismissal["element_id"],
          'result_name' => $dismissal["result_name"],
          'result_key' => $dismissal["result_key"],
          'dismissal_status' => $operation,
          'created' => $now,
          'updated' => $now,
        ])
        ->updateFields([
          'page_path' => $dismissal["page_path"],
          'page_title' => $dismissal["page_title"],
          'route_name' => $dismissal["route_name"],
          'entity_type' => $dismissal["entity_type"],
          'page_language' => $dismissal["language"],
          'uid' => $this->account->id(),
          'element_id' => $dismissal["element_id"],
          'result_name' => $dismissal["result_name"],
          'result_key' => $dismissal["result_key"],
          'dismissal_status' => $operation,
          'updated' => $now,
        ])
        ->keys([
          'element_id' => $dismissal["element_id"],
          'result_name' => $dismissal["result_name"],
          'entity_type' => $dismissal["entity_type"],
          'route_name' => $dismissal["route_name"],
          'page_path' => $dismissal["page_path"],
          'page_language' => $dismissal["language"],
        ])
        ->execute();
    }
    // Clear cache for the referring page and dashboard.
    Cache::invalidateTags(['editoria11y:dismissals_' . preg_replace('/[^a-zA-Z0-9]/', '', $dismissal["page_path"]), 'editoria11y:dashboard']);
  }

  /**
   *
   */
  private function validate_not_null($user_input) {
    if (empty($user_input)) {
      throw new Editoria11yApiException("Missing value: {$key}");
    }
  }

  /**
   *
   */
  private function validate_path($user_input) {
    if (strpos($user_input, '/') !== 0) {
      throw new Editoria11yApiException("Invalid page path: {$user_input}");
    }
  }

  /**
   *
   */
  private function validate_dismissal_status($user_input) {
    if (!($user_input === 'ok' || $user_input === 'hide' || $user_input === 'reset')) {
      throw new Editoria11yApiException("Invalid dismissal operation: {$user_input}");
    }
  }

  /**
   *
   */
  private function validate_number($user_input) {
    if (!(is_numeric($user_input))) {
      throw new Editoria11yApiException("Nan: {$user_input}");
    }
  }

}
