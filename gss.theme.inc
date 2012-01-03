<?php

/**
 * @file
 * Themeable functions for Google Site Search.
 */


/**
 * Process variables for gss-result.tpl.php.
 *
 * The $variables array contains the following arguments:
 * - $result
 * - $module
 *
 * @see gss-result.tpl.php
 */
function template_preprocess_gss_result(&$variables) {
  $result = $variables['result'];
  $variables['url'] = check_url($result['link']);
  $variables['title'] = $result['title'];
  // Check for existence. User search does not include snippets.
  $variables['snippet'] = isset($result['snippet']) ? $result['snippet'] : '';
}

