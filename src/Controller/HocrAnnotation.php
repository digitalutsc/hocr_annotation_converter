<?php

namespace Drupal\hocr_annotation_converter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DomCrawler\Crawler;

class HocrAnnotation extends ControllerBase {

  public function search(Request $request) {
    $query = $request->query->all();
    $uri = isset($query['uri']) ? $query['uri'] : '';
    $pattern = '/node\/(\d+)\/canvas\/(\d+)/';
    preg_match($pattern, $uri, $matches);
    if (count($matches) === 3) {
      $node_id = $matches[1];
      $mid = $matches[2];
      $ownerNode = $this->findOwnerNode($node_id, $mid);
      if (!$ownerNode) {
        return new JsonResponse('Not found', 404);
      }
      $fileContent = $this->findNodeHocrFile($ownerNode);
      if (!$fileContent) {
        return new JsonResponse('Could not fetch file content!', 404);
      }
      $annotationList = $this->convertHocrToAnnotationList($fileContent, $uri);
      return new JsonResponse($annotationList);
    }
  }
  
  protected function convertHocrToAnnotationList($fileContent, $uri) {
    $crawler = new Crawler($fileContent);
    $words = $crawler->filterXpath('//*[@class and contains(concat(" ", normalize-space(@class), " "), " ocrx_word ")]');
    $annotationList = [
      '@type' => 'sc:AnnotationList',
      'resources' => [],
      '@context' => 'http://iiif.io/api/presentation/2/context.json',
    ];

    foreach ($words as $word) {
      $bbox = $word->getAttribute('title');
      if (preg_match('/bbox (\d+) (\d+) (\d+) (\d+)/', $bbox, $matches)) {
        $annotation = [
          'resource' => [
            'http://dev.llgc.org.uk/sas/full_text' => $word->textContent,
            '@type' => 'dctypes:Text',
            'format' => 'text/html',
            'chars' => '<p>' . htmlspecialchars($word->textContent) . '</p>',
          ],
          '@type' => 'oa:Annotation',
          'motivation' => ['oa:commenting'],
          '@id' => 'https://example.com/annotation/' . uniqid(),
          'label' => $word->textContent,
          '@context' => 'http://iiif.io/api/presentation/2/context.json',
          'on' => $uri . '#xywh=' . $matches[1] . ',' . $matches[2] . ',' . ($matches[3] - $matches[1]) . ',' . ($matches[4] - $matches[2]),
        ];
        $annotationList['resources'][] = $annotation;
      }
    }
    return $annotationList;
  }

  protected function findNodeHocrFile($node) {
    if (!$node) {
      return NULL;
    }
    $medias = $node->get('field_islandora_object_media')->referencedEntities();
    foreach ($medias as $media) {
      $media_use = $media->get('field_media_use')->referencedEntities();
      foreach ($media_use as $use) {
        if ($use->getName() === 'hOCR') {
          if ($media->hasField('field_media_file')) {
            $file = $media->get('field_media_file')->entity;
            if ($file) {
              $file_path = $file->getFileUri();
              return file_get_contents($file_path);
            }
          }
        }
      }
    }
    return NULL;
  }

  protected function findOwnerNode($node_id, $media_id) {
    if (!$node_id) {
      return NULL;
    }
    $entityTypeManager = \Drupal::entityTypeManager();
    $query = $entityTypeManager->getStorage('node')->getQuery();
    $nids = $query->accessCheck(TRUE)->condition('field_part_of', $node_id)->execute();
    if (!empty($nids)) {
      foreach ($nids as $nid) {
        $node = $entityTypeManager->getStorage('node')->load($nid);
        $media_ids = array_map(function ($item) {
          return $item['target_id'];
        }, $node->get('field_islandora_object_media')->getValue());
        if (in_array($media_id, $media_ids)) {
          return $node;
        }
      }
    }
    return NULL;
  }
}