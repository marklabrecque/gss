<?php

namespace Drupal\gss\Plugin\Search;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\search\Plugin\ConfigurableSearchPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use Drupal\key\KeyRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles search using Google Search Engine.
 *
 * @SearchPlugin(
 *   id = "gss_search",
 *   title = @Translation("Google Site Search")
 * )
 */
class Search extends ConfigurableSearchPluginBase implements AccessibleInterface {

  /**
   * Max number of items (`num`) via API.
   */
  const MAX_NUM = 10;

  /**
   * Total number of results.
   *
   * @var integer
   */
  protected $count;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Key storage.
   *
   * @var \Drupal\key\KeyRepository
   */
  protected $keyRepository;

  /**
   * {@inheritdoc}
   */
  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('http_client'),
      $container->get('key.repository')
    );
  }

  /**
   * Constructs a \Drupal\node\Plugin\Search\NodeSearch object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \GuzzleHttp\Client $http_client
   *   The http client.
   * @param \Drupal\key\KeyRepository $key_repository
   *   The key repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, Client $http_client, KeyRepository $key_repository) {
    $this->languageManager = $language_manager;
    $this->httpClient = $http_client;
    $this->keyRepository = $key_repository;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      "api_key" => NULL,
      "search_engine_id" => NULL,
      "base_url" => 'https://www.googleapis.com/customsearch/v1',
      // @todo autocomplete
      // "autocomplete" => TRUE,
      "page_size" => 10,
      "pager_size" => 9,
      "images" => FALSE,
      // @todo labels
      // "labels" => TRUE,
      // @todo number_of_results
      // "number_of_results" => TRUE,
      // @todo info
      // "info" => FALSE,
    ];
  }

  /**
   * Gets the configured pager size.
   */
  public function getPagerSize() {
    return $this->configuration['pager_size'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['api_key'] = [
      '#title' => $this->t('Google search API key'),
      '#type' => 'key_select',
      '#required' => TRUE,
      '#default_value' => $this->configuration['api_key'],
    ];

    $form['search_engine_id'] = [
      '#title' => $this->t('Google search engine ID'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->configuration['search_engine_id'],
    ];

    $form['base_url'] = array(
      '#title' => $this->t('Search engine base url'),
      '#type' => 'textfield',
      '#description' => $this->t('The base URL to send the query to. Use this to override the default request to Google, useful for proxying the request.'),
      '#default_value' => $this->configuration['base_url'],
    );

    $form['miscellaneous'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Miscellaneous'),
    );

    // @todo autocomplete
    /*
    $form['miscellaneous']['autocomplete'] = [
      '#title' => $this->t('Add Google autocomplete to search boxes'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['autocomplete'],
    ];
    */

    $form['miscellaneous']['page_size'] = array(
      '#title' => $this->t('Page size'),
      '#type' => 'textfield',
      '#description' => $this->t('Number of results to display per page.'),
      '#default_value' => $this->configuration['page_size'],
      '#size' => 5,
      '#max_length' => 5,
    );

    $form['miscellaneous']['pager_size'] = array(
      '#title' => $this->t('Pager size'),
      '#type' => 'textfield',
      '#description' => $this->t('Number of pages to show in the pager. Input ONLY odd numbers like 5, 7 or 9 and NOT 6, 8 or 10, for example.'),
      '#default_value' => $this->configuration['pager_size'],
      '#size' => 5,
      '#max_length' => 5,
    );

    $form['miscellaneous']['images'] = array(
      '#title' => $this->t('Image Search'),
      '#type' => 'checkbox',
      '#description' => $this->t('Enable image search.'),
      '#default_value' => $this->configuration['images'],
    );

    // @todo labels
    /*
    $form['miscellaneous']['labels'] = array(
      '#title' => $this->t('Show labels'),
      '#type' => 'checkbox',
      '#description' => $this->t('Let the user filter the search result by labels. @link', ['@link' => Link::fromTextAndUrl($this->t('Read more about search labels.'), Url::fromUri('https://developers.google.com/custom-search/docs/ref_prebuiltlabels'))]),
      '#default_value' => $this->configuration['labels'],
    );
    */

    // @todo number_of_results
    /*
    $form['miscellaneous']['number_of_results'] = array(
      '#title' => $this->t('Show number of results'),
      '#type' => 'checkbox',
      '#description' => $this->t('Show the line "Shows x to y of approximately x hits" in the top of the search result.'),
      '#default_value' => $this->configuration['number_of_results'],
    );
    */

    // @todo info
    /*
    $form['miscellaneous']['info'] = array(
      '#title' => $this->t('Show extra information for each search result'),
      '#type' => 'checkbox',
      '#description' => $this->t('Show extra information (content-type, author and date) below each search result.'),
      '#default_value' => $this->configuration['info'],
    );
    */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $keys = [
      'api_key',
      'search_engine_id',
      'base_url',
      // @todo autocomplete
      // 'autocomplete',
      'page_size',
      'pager_size',
      'images',
      // @todo labels
      // 'labels',
      // @todo number_of_results
      // 'number_of_results',
      // @todo info
      // 'info',
    ];
    foreach ($keys as $key) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIfHasPermission($account, 'access content');
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    if ($this->isSearchExecutable()) {

      $page = pager_find_page();
      $results = $this->findResults($page);

      // API total results is unreliable. Sometimes when requesting a large
      // offset we get no results, and
      // $response->searchInformation->totalResults is 0. In this case return
      // the previous page's items.
      while ($page && !count($results)) {
        $results = $this->findResults(--$page);
      }

      pager_default_initialize($this->count, $this->configuration['page_size']);

      if ($results) {
        return $this->prepareResults($results);
      }
    }

    return array();
  }

  /**
   * Queries to find search results, and sets status messages.
   *
   * This method can assume that $this->isSearchExecutable() has already been
   * checked and returned TRUE.
   *
   * @return array|null
   *   Results from search query execute() method, or NULL if the search
   *   failed.
   */
  protected function findResults($page) {
    $items = [];

    $page_size = $this->configuration['page_size'];

    // Reconcile items per page with api max 10.
    $count = 0;
    $n = $page_size < self::MAX_NUM ? $page_size : self::MAX_NUM;
    for ($i = 0; $i < $page_size; $i += self::MAX_NUM) {
      $offset = $page * $page_size + $i;
      if (!$response = $this->getResults($n, $offset)) {
        break;
      }
      if (isset($response->items)) {
        $this->count = $response->searchInformation->totalResults;
        $items = array_merge($items, $response->items);
      }
      else {
        break;
      }
    }

    return $items;
  }

  /**
   * Get query result.
   *
   * @param int $n
   *   Number of items.
   * @param int $offset
   *   Offset of items (0-indexed).
   * @param string $search_type
   *   One of:
   *   - NULL (regular search).
   *   - "image".
   *
   * @return object|null
   *   Decoded response from Google, or NULL on error.
   */
  protected function getResults($n = 1, $offset = 0, $search_type = NULL) {
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $api_key = $this->keyRepository->getKey($this->configuration['api_key']);
    $query = $this->getParameters();

    $options = array(
      'query' => array(
        'q' => $this->keywords,
        'key' => $api_key->getKeyValue(),
        'cx' => $this->configuration['search_engine_id'],
        // hl: "interface language", also used to weight results.
        'hl' => $language,
        'start' => $offset + 1,
        'num' => $n,
      ),
    );

    if (@$query['type'] == 'image') {
      $options['query']['searchType'] = 'image';
    }

    try {
      $response = $this
        ->httpClient
        ->get($this->configuration['base_url'], $options);
    }
    catch (\Exception $e) {
      // @todo
      return NULL;
    }
    return json_decode($response->getBody());
  }

  /**
   * Prepares search results for rendering.
   *
   * @param array $items
   *   Results found from a successful search query execute() method.
   *
   * @return array
   *   Array of search result item render arrays (empty array if no results).
   */
  protected function prepareResults(array $items) {
    $results = [];
    foreach ($items as $item) {
      $results[] = [
        'link' => $item->link,
        'type' => NULL,
        'title' => $item->title,
        'node' => NULL,
        'extra' => NULL,
        'score' => NULL,
        'snippet' => [
          '#markup' => $item->htmlSnippet,
        ],
        'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
      ];
    }
    return $results;
  }

  /**
   * Gets render array for search option links.
   */
  public function getSearchOptions(Request $request) {
    $options = [];

    if ($this->configuration['images']) {
      $query = $this->getParameters();
      $active = $query['type'] == 'image';
      $query['type'] = 'image';
      $url = Url::createFromRequest($request);
      $url->setOption('query', $query);
      $url->setOption('attributes', $active ? ['class' => ['is-active']] : []);
      $options['images'] = [
        '#title' => $this->t('Images'),
        '#type' => 'link',
        '#url' => $url,
      ];
    }

    if (count($options)) {
      $query = $this->getParameters();
      $active = empty($query['type']);
      if (!$active) {
        unset($query['type']);
      }
      $url = Url::createFromRequest($request);
      $url->setOption('query', $query);
      $url->setOption('attributes', $active ? ['class' => ['is-active']] : []);
      $options['all'] = [
        '#title' => $this->t('All'),
        '#type' => 'link',
        '#url' => $url,
        '#weight' => -1,
      ];

      return [
        '#theme' => 'item_list',
        '#items' => $options,
      ];
    }

    return [];
  }

}
