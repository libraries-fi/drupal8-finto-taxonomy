<?php

namespace Drupal\finto_taxonomy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Url;
use Drupal\finto_taxonomy\TaxonomyHelper;
use GuzzleHttp\Client as HttpClient;

class FintoAutocompleteMatcher extends EntityAutocompleteMatcher {
  protected $httpClient;
  protected $typeManager;
  protected $manager;

  private $url = 'https://api.finto.fi/rest/v1/search';
  private $vocabulary = 'yso';
  private $lang = 'fi';

  public function __construct(SelectionPluginManagerInterface $selection_manager, HttpClient $http_client, TaxonomyHelper $manager) {
    parent::__construct($selection_manager);
    $this->httpClient = $http_client;
    $this->manager = $manager;
  }

  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {
    $matches = parent::getMatches($target_type, $selection_handler, $selection_settings, $string);
    $matches = $this->mergeMatches($matches, $this->queryFinto($string));

    return $matches;
  }

  protected function queryFinto($query) {
    $url = Url::fromUri($this->url, [
      'query' => [
        'vocab' => $this->vocabulary,
        'lang' => $this->lang,
        'query' => $query . '*',
      ]
    ]);

    $response = $this->httpClient->get($url->toString());
    $data = json_decode($response->getBody())->results;
    $matches = [];

    $this->manager->setCachedFintoItems($data);

    foreach ($data as $item) {
      $label = empty($item->altLabel) ? $item->prefLabel : $item->altLabel;
      $matches[] = [
        'label' => $label,
        'value' => sprintf('%s (%s)', $label, $item->localname),
      ];
    }

    return $matches;
  }

  protected function mergeMatches(array $existing, array $finto) {
    $cache = array_map(function($item) { return $item['label']; }, $existing);

    foreach ($finto as $item) {
      if (!in_array($item['label'], $cache)) {
        $existing[] = $item;
      }
    }

    return $existing;
  }
}