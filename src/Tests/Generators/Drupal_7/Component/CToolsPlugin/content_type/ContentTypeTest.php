<?php

namespace DrupalCodeGenerator\Tests\Drupal_7\Component;

use DrupalCodeGenerator\Tests\GeneratorTestCase;

/**
 * Test for d7:component:ctools-plugin:content-type command.
 */
class CtoolsPluginContentTypeTest extends GeneratorTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->class = 'Drupal_7\Component\CToolsPlugin\ContentType';
    $this->answers = [
      'Example',
      'example',
      'Some description',
      'custom',
      'Node',
    ];
    $this->target = 'example.inc';
    $this->fixture = __DIR__ . '/_' . $this->target;

    parent::setUp();
  }

}
