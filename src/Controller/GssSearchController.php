<?php

namespace Drupal\gss\Controller;

use Drupal\search\Controller\SearchController;
use Drupal\search\SearchPageInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Route controller for GSS Search.
 */
class GssSearchController extends SearchController {

  /**
   * {@inheritdoc}
   */
  public function view(Request $request, SearchPageInterface $entity) {
    /** @var \Drupal\gss\Plugin\Search\Search $plugin */
    $plugin = $entity->getPlugin();

    $build = parent::view($request, $entity);

    // Alter the pager to set # of page links.
    $build['pager']['#quantity'] = $plugin->getPagerSize();
    // Alter the pager to not show last link. API total results is unreliable,
    // so "last" link is problematic.
    $build['pager']['#tags'][4] = ' ';

    return [
      '#cache' => $build['#cache'],
      '#title' => $build['#title'],
      'search_form' => $build['search_form'],
      'search_results_title' => @$build['search_results_title'],
      'links' => $plugin->getSearchOptions($request),
      'search_results' => $build['search_results'],
      'pager' => $build['pager'],
    ];
  }

}
