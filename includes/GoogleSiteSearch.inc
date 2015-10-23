<?php

/**
 * GSS module site search inc file.
 */

// Defines the Google site search base url.
define('GSS_BASE_URL', 'http://www.google.com/search');

/**
 * Class for interaction with Google Site Search.
 */
class GoogleSiteSearch {

  /**
   * The Google key to use.
   *
   * @var string
   */
  private $key = NULL;

  /**
   * The language to use in the search.
   *
   * @var string
   */
  private $language = NULL;

  /**
   * The search query.
   *
   * @var string
   */
  private $query = NULL;

  /**
   * Extra parameters to be passed onto Google CSE.
   *
   * @var array
   */
  private $extraParams = NULL;

  /**
   * Page size, number of results per page.
   *
   * @var int
   */
  private $pageSize = 20;

  /**
   * Pager size, number of pages to show in the pager.
   *
   * @var int
   */
  private $pagerSize = 19;

  /**
   * Current page in the results.
   *
   * @var int
   */
  private $currentPage = NULL;

  /**
   * Total results for a search.
   *
   * @var int
   */
  private $totalResults = 0;

  /**
   * Total results approximation for a search.
   *
   * @var int
   */
  private $approxTotalResults = 0;

  /**
   * The search results return from Google.
   *
   * @var SimpleXml
   */
  private $searchResultsXml = NULL;

  /**
   * The allowed tags in results from Google.
   *
   * @var array
   */
  private $allowedTags = array(
    'a',
    'em',
    'strong',
    'cite',
    'blockquote',
    'code',
    'ul',
    'ol',
    'li',
    'dl',
    'dt',
    'dd',
    'b'
  );

  /**
   * The search categories.
   *
   * @var array
   */
  private $categories = array();

  /**
   * Create a new instance of GoogleSiteSearch.
   *
   * @param string $query
   *   The search string.
   * @param string $key
   *   The Google API key.
   * @param string $page_size
   *   The number of results to return per page.
   * @param string $extra_params
   *   Extra parameters to be passed onto Google CSE.
   */
  public function __construct($query, $key = NULL, $page_size = 20, $extra_params = NULL) {
    $this->query = $query;
    $this->key = $key;
    $this->pageSize = $page_size;
    $this->extraParams = $extra_params;
  }

  /**
   * Set Google key.
   *
   * @param string $key
   *   The Google key.
   */
  public function setKey($key) {
    $this->key = $key;
  }

  /**
   * Get Google key.
   *
   * @return string
   *   The Google key.
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * Set the language.
   *
   * @param string $language
   *   The new language.
   */
  public function setLanguage($language) {
    $this->language = $language;
  }

  /**
   * Get current page size.
   *
   * @return int
   *   The current page size.
   */
  public function getPageSize() {
    return $this->pageSize;
  }

  /**
   * Set the page size.
   *
   * @param int $page_size
   *   The new page size.
   */
  public function setPageSize($page_size) {
    $this->pageSize = $page_size;
  }

  /**
   * Get current pager size.
   *
   * @return int
   *   The current pager size.
   */
  public function getPagerSize() {
    return $this->pagerSize;
  }

  /**
   * Set the pager size.
   *
   * @param int $pager_size
   *   The new pager size.
   */
  public function setPagerSize($pager_size) {
    $this->pagerSize = $pager_size;
  }

  /**
   * Get current page in the results.
   *
   * @return int
   *   The current page.
   */
  public function getCurrentPage() {
    return $this->currentPage;
  }

  /**
   * Get total results for current search.
   *
   * @return int
   *   The total number of results.
   */
  public function getTotalResults() {
    return $this->totalResults;
  }

  /**
   * Get approximate total results for current search.
   *
   * @return int
   *   The total number of results.
   */
  public function getApproxTotalResults() {
    return $this->approxTotalResults;
  }

  /**
   * Get total number of pages for search.
   *
   * @return int
   *   The total number of pages.
   */
  public function getTotalPages() {
    return ($this->totalResults / $this->pageSize + 1);
  }

  /**
   * Get the allowed HTML tags.
   *
   * @return array
   *   The allowed HTML tags.
   */
  public function getAllowedTags() {
    return $this->allowedTags;
  }

  /**
   * Get query current search.
   *
   * @return string
   *   The current query.
   */
  public function getQuery() {
    return $this->query;
  }

  /**
   * Get query extra parameters array.
   *
   * @return array
   *   The extra parameters to CSE.
   */
  public function getExtraParameters() {
    return $this->extraParams;
  }

  /**
   * Get search results.
   *
   * @param int $page
   *   The page to get results from.
   *
   * @return array
   *   The search results.
   *
   * @throws Exception
   *   In cases when no results are returned from Google.
   */
  public function getSearchResults($page = 1) {
    // Set page.
    $this->currentPage = $page;

    // Calculate start postition based on page.
    $start_pos = ($this->currentPage - 1) * $this->pageSize;

    $params = array(
      'query' => array(
        'start' => $start_pos,
        'num' => $this->pageSize,
        'client' => 'google-csbe',
        'output' => 'xml_no_dtd',
        'cx' => $this->key,
        'q' => $this->query,
      )
    );

    // Add language parameter if is set.
    if ($this->language) {
      // hl: "interface language", also used to weight results
      $params['query']['hl'] = $this->language;
      // lr: "language restrict", supposed to limit results to only the set language, defined with a "lang_" prefix
      $params['query']['lr'] = 'lang_'. $this->language;
    }

    // Prepare query parameters for URL assembly.
    if (count($this->extraParams) > 0) {
      $params['query']  = array_merge($params['query'], $this->extraParams);
    }
    else {
      $extra_params_query = NULL;
    }

    // Get the search base set in the admin, default to Google.
    $search_base_url = variable_get('gss_base_url', '');
    $search_base_url = !empty($search_base_url) ? $search_base_url : GSS_BASE_URL;

    // Fetch results from Google.
    $url_response = drupal_http_request(url($search_base_url, $params));

    if (isset($url_response->error)) {
      return NULL;
    }
    $results = simplexml_load_string($url_response->data);

    if ($results !== FALSE) {
      if (!isset($results->RES->M)) {
        // No results, return NULL.
        return NULL;
      }
      else {
        // Save total results.
        $this->approxTotalResults = intval(check_plain((string) $results->RES->M));
        $this->totalResults = intval($results->Context->total_results);

        // Store faceted items in order to use them later in the header.
        $categories = array();
        if (isset($results->Context->Facet->FacetItem)) {
          foreach ($results->Context->Facet->FacetItem as $facet) {
            $categories[] = array(
              'label' => check_plain((string) $facet->label),
              'anchor_text' => check_plain((string) $facet->anchor_text),
            );
          }
          $this->categories = $categories;
        }

        // Init results array.
        $arr = array();

        // Loop results.
        foreach ($results->RES->R as $result) {
          // Init result array.
          $item = array();
          $item['title'] = filter_xss((string) $result->T, $this->getAllowedTags());
          $item['url'] = check_url((string) $result->UE);
          $item['description'] = filter_xss((string) $result->S, $this->getAllowedTags());

          // Let's get the image thumbnail - present in
          // PageMap->DataObject['type'] == cse_thumbnail.
          if (isset($result->PageMap->DataObject)) {
            foreach ($result->PageMap->DataObject as $do) {
              switch ((string) $do['type']) {
                case 'cse_thumbnail':
                  // We are inside the thumbnail node, lets get the src attribute.
                  foreach ($do->Attribute as $att) {
                    switch ((string) $att['name']) {
                      case 'src':
                        $item['thumbnail_url'] = check_url((string) $att['value']);
                        break;
                    }
                  }
                  break;
              }
            }
          }

          $arr[] = $item;
        }

        // Return results.
        return $arr;
      }
    }
    else {
      throw new Exception('Could not load search results from Google.');
    }
  }

  /**
   * Get a pager for the search results.
   *
   * @return string
   *   The pager render array.
   */
  public function getPager() {
    // Initializing the pager.
    pager_default_initialize($this->getTotalResults(), $this->getPageSize());

    // Return pager.
    return array(
      '#theme' => 'pager',
      '#variables' => array(
        'quantity' => $this->getTotalResults()
      )
    );
  }

  /**
   * Get a head to the search results.
   *
   * @return string
   *   The search head HTML code.
   */
  public function getSearchHead() {
    // Get total pages.
    $total_results = $this->getTotalResults();
    $page_size = $this->getPageSize();
    $current_page = $this->getCurrentPage();
    $show_start = ($page_size * $current_page) - $page_size + 1;
    $show_end = ($page_size * $current_page);

    if ($show_end > $total_results) {
      $show_end = $total_results;
    }

    $html = '<div class="searchhead">';

    if (variable_get('gss_labels', TRUE) == 1) {
      // Adding the tabs for the search labels.
      $html .= '<span>' . t("Show only results of type:") . '</span><ul>';

      // Checking which one is the active tab.
      $active_html_first_element = "";
      $active_html_others = "";

      // There is no faceted search.
      if (strpos($this->query, "more:") === FALSE) {
        // Then the first element is active.
        $active_html_first_element = 'class="active"';
        $term_from_query = "";
      }
      else {
        $query_parts = explode(":", $this->query);

        // In this case we store the category to check later.
        $term_from_query = end($query_parts);
      }

      // Resume $this->query to its original value (no categories).
      $this->query = current(explode(" more:", $this->query));

      // First item will always be the original query.
      $html .= '<li>' . '<a ' . $active_html_first_element . ' href=' . urlencode($this->query) . '>' . t('All results') . '</a>' . '</li>';

      // Loop to create all remaining categorized tabs / queries.
      if ($total_results > 0) {
        foreach ($this->categories as $categ) {
          // Do not inherit from previous loop...
          $active_html_others = "";
          if ($categ['label'] == $term_from_query) {
            $active_html_others = 'class="active"';
          }

          // Builds up the path to the tab links.
          $new_query = urlencode($this->query) . '+more%3A' . $categ['label'];
          $html .= '<li>' . '<a ' . $active_html_others . ' href=' . $new_query . '>' . t($categ['anchor_text'], array(), array('context' => 'gss:search-result:filter')) . '</a>' . '</li>';
        }
      }

      $html .= '</ul>';
    }

    if (variable_get('gss_number_of_results', TRUE) == 1 && $total_results !== 0) {
      $html .= t('Shows @show_start to @show_end of approximately @total_results hits', array(
        '@show_start' => $show_start,
        '@show_end' => $show_end,
        '@total_results' => $this->approxTotalResults,
      ));
    }

    $html .= '</div>';

    // Return search head.
    return $html;
  }
}
