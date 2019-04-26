<?php

namespace DrupalCodeGenerator\Command\Drupal_8\Yml\Links;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Implements d8:yml:links:task command.
 */
class Task extends BaseGenerator {

  protected $name = 'd8:yml:links:task';
  protected $description = 'Generates a links.task yml file';
  protected $alias = 'task-links';

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) :void {
    $questions['machine_name'] = new Question('Module machine name');
    $questions['machine_name']->setValidator([Utils::class, 'validateMachineName']);

    $this->collectVars($input, $output, $questions);

    $this->addFile()
      ->path('{machine_name}.links.task.yml')
      ->template('d8/yml/links.task.twig');
  }

}
