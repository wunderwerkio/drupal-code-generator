<?php

namespace DrupalCodeGenerator\Command;

use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Asset;
use DrupalCodeGenerator\IOAwareInterface;
use DrupalCodeGenerator\IOAwareTrait;
use DrupalCodeGenerator\Style\GeneratorStyle;
use DrupalCodeGenerator\Utils;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Base class for all generators.
 */
abstract class BaseGenerator extends Command implements GeneratorInterface, IOAwareInterface, LoggerAwareInterface {

  use IOAwareTrait;
  use LoggerAwareTrait;

  /**
   * The command name.
   *
   * @var string
   */
  protected $name;

  /**
   * The command description.
   *
   * @var string
   */
  protected $description;

  /**
   * The command alias.
   *
   * @var string
   */
  protected $alias;

  /**
   * Command label.
   *
   * @var string
   */
  protected $label;

  /**
   * A path where templates are stored.
   *
   * @var string
   */
  protected $templatePath;

  /**
   * The working directory.
   *
   * @var string
   */
  protected $directory;

  /**
   * The destination.
   *
   * @var mixed
   */
  protected $destination = 'modules/%';

  /**
   * Assets to create.
   *
   * @var \DrupalCodeGenerator\Asset[]
   */
  protected $assets = [];

  /**
   * Twig template variables.
   *
   * @var array
   */
  protected $vars = [];

  /**
   * Name question.
   *
   * @var string
   */
  protected $nameQuestion = 'Extension name';

  /**
   * Machine name question.
   *
   * @var string
   */
  protected $machineNameQuestion = 'Extension machine name';

  /**
   * {@inheritdoc}
   */
  protected function configure():void {
    $this
      ->setName($this->name)
      ->setDescription($this->description)
      ->addOption(
        'directory',
        '-d',
        InputOption::VALUE_OPTIONAL,
        'Working directory'
      )
      ->addOption(
        'answer',
        '-a',
        InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
        'Answer to generator question'
      );

    if ($this->alias) {
      $this->setAliases([$this->alias]);
    }

    if (!$this->templatePath) {
      $this->templatePath = Application::getRoot() . '/templates';
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function initialize(InputInterface $input, OutputInterface $output) :void {

    $this->io = new GeneratorStyle($input, $output, $this->getHelper('question'));
    foreach ($this->getHelperSet() as $helper) {
      if ($helper instanceof IOAwareInterface) {
        $helper->io($this->io);
      }
    }

    $this->getHelperSet()->setCommand($this);

    $this->getHelper('renderer')->addPath($this->templatePath);
    $this->setLogger($this->getHelper('logger_factory')->getLogger());

    $this->logger->debug('Command: {command}', ['command' => get_class($this)]);

    $directory = $input->getOption('directory') ?: getcwd();
    // Do not look up for extension root when generating an extension.
    $extension_destinations = ['modules', 'profiles', 'themes'];
    $is_extension = in_array($this->destination, $extension_destinations);
    $this->directory = $is_extension
      ? $directory : (Utils::getExtensionRoot($directory) ?: $directory);

    $this->io->title(sprintf("Welcome to %s generator!", $this->getName()));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) :int {

    $this->generate();

    $this->logger->debug('Working directory: {directory}', ['directory' => $this->directory]);

    // Render all assets.
    $renderer = $this->getHelper('renderer');

    $this->processVars();

    $collected_vars = preg_replace('/^Array/', '', print_r($this->vars, TRUE));
    $this->logger->debug('Collected variables: {vars}', ['vars' => $collected_vars]);

    foreach ($this->getAssets() as $asset) {
      // Supply the asset with all collected variables if it has no local ones.
      if (!$asset->getVars()) {
        $asset->vars($this->vars);
      }
      $renderer->renderAsset($asset);
      $this->logger->debug('Rendered template: {template}', ['template' => $asset->getTemplate()]);
    }

    $dumped_assets = $this->getHelper('dumper')
      ->dump($this->getAssets(), $this->getDirectory());

    $this->getHelper('result_printer')->printResult($dumped_assets);

    $this->logger->debug('Memory usage: {memory}', ['memory' => Helper::formatMemory(memory_get_peak_usage())]);
    return 0;
  }

  /**
   * Generates assets.
   */
  abstract protected function generate() :void;

  /**
   * {@inheritdoc}
   */
  public function getLabel() :?string {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssets() :array {
    return $this->assets;
  }

  /**
   * {@inheritdoc}
   */
  public function setDirectory(string $directory) :void {
    $this->directory = $directory;
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectory() :string {
    return $this->directory;
  }

  /**
   * {@inheritdoc}
   */
  public function setDestination(string $destination) {
    $this->destination = $destination;
  }

  /**
   * {@inheritdoc}
   */
  public function getDestination() :string {
    return $this->destination;
  }

  /**
   * Asks the user for template variables.
   *
   * @param array $questions
   *   List of questions that the user should answer.
   * @param array $vars
   *   Array of predefined template variables.
   *
   * @return array
   *   Template variables.
   *
   * @see \DrupalCodeGenerator\InputHandler::collectVars()
   */
  protected function &collectVars(array $questions, array $vars = []) :array {
    $vars = $vars ?: $this->vars;
    $this->vars += $this->getHelper('input_handler')->collectVars($questions, $vars);
    return $this->vars;
  }

  /**
   * Asks a question.
   */
  protected function ask(string $question, $default = NULL, $validator = NULL) {
    $this->processVars();
    $question = Utils::replaceTokens($question, $this->vars);
    $default = Utils::replaceTokens($default, $this->vars);
    return $this->io->ask($question, $default, $validator);
  }

  /**
   * Asks for confirmation.
   */
  protected function confirm(string $question, bool $default = TRUE) :bool {
    $this->processVars();
    $question = Utils::replaceTokens($question, $this->vars);
    return $this->io->confirm($question, $default);
  }

  /**
   * Asks a choice question.
   */
  protected function choice(string $question, array $choices, $default = NULL) {
    $this->processVars();
    $question = Utils::replaceTokens($question, $this->vars);

    // The choices can be an associative array.
    $choice_labels = array_values($choices);
    // Start choices list form '1'.
    array_unshift($choice_labels, NULL);
    unset($choice_labels[0]);

    // Do not use IO choice here as it prints choice key as default value.
    // @see \Symfony\Component\Console\Style\SymfonyStyle::choice().
    $answer = $this->askQuestion(new ChoiceQuestion($question, $choice_labels, $default));
    return array_search($answer, $choices);
  }

  /**
   * Asks a question.
   */
  protected function askQuestion(Question $question) {
    $default_value = $question->getDefault();
    if ($default_value) {
      $default_value = Utils::replaceTokens($default_value, $this->vars);
    }
    $this->setQuestionDefault($question, $default_value);
    return $this->io->askQuestion($question);
  }

  /**
   * Creates an asset.
   *
   * @param string $type
   *   Asset type.
   *
   * @return \DrupalCodeGenerator\Asset
   *   The asset.
   */
  protected function addAsset(string $type) :Asset {
    $asset = (new Asset())->type($type);
    $this->assets[] = $asset;
    return $asset;
  }

  /**
   * Creates file asset.
   *
   * @param string $path
   *   (Optional) File path.
   * @param string $template
   *   (Optional) Template.
   *
   * @return \DrupalCodeGenerator\Asset
   *   The asset.
   */
  protected function addFile(string $path = NULL, string $template = NULL) :Asset {
    return $this->addAsset('file')
      ->path($path)
      ->template($template);
  }

  /**
   * Creates directory asset.
   *
   * @param string $path
   *   (Optional) Directory path.
   *
   * @return \DrupalCodeGenerator\Asset
   *   The asset.
   */
  protected function addDirectory(string $path = NULL) :Asset {
    return $this->addAsset('directory')->path($path);
  }

  /**
   * Creates service file asset.
   *
   * @param string $path
   *   (Optional) File path.
   *
   * @return \DrupalCodeGenerator\Asset
   *   The asset.
   */
  protected function addServicesFile(string $path = NULL) :Asset {
    return $this->addFile()
      ->path($path ?: '{machine_name}.services.yml')
      ->action('append')
      ->headerSize(1);
  }

  /**
   * Collects services.
   *
   * @return array
   *   List of collected services.
   */
  protected function collectServices() :array {

    $service_definitions = self::getServiceDefinitions();
    $service_ids = array_keys($service_definitions);

    $services = [];
    while (TRUE) {
      $question = new Question('Type the service name or use arrows up/down. Press enter to continue');
      $question->setValidator([Utils::class, 'validateServiceName']);
      $question->setAutocompleterValues($service_ids);
      $service = $this->io()->askQuestion($question);
      if (!$service) {
        break;
      }
      $services[] = $service;
    }

    $this->vars['services'] = [];
    foreach (array_unique($services) as $service_id) {
      if (isset($service_definitions[$service_id])) {
        $definition = $service_definitions[$service_id];
      }
      else {
        // Build the definition if the service is unknown.
        $definition = [
          'type' => 'Drupal\example\ExampleInterface',
          'name' => str_replace('.', '_', $service_id),
          'description' => "The $service_id service.",
        ];
      }
      $type_parts = explode('\\', $definition['type']);
      $definition['short_type'] = end($type_parts);
      $this->vars['services'][$service_id] = $definition;
    }
    return $this->vars['services'];
  }

  /**
   * Gets service definitions.
   *
   * @return array
   *   List of service definitions keyed by service ID.
   */
  protected static function getServiceDefinitions() :array {
    $data_encoded = file_get_contents(Application::getRoot() . '/resources/service-definitions.json');
    return json_decode($data_encoded, TRUE);
  }

  /**
   * Sets question default value.
   *
   * @param \Symfony\Component\Console\Question\Question $question
   *   The question to update.
   * @param mixed $default_value
   *   Default value for the question.
   */
  protected function setQuestionDefault(Question $question, $default_value) :void {
    if ($question instanceof ChoiceQuestion) {
      $question->__construct($question->getQuestion(), $question->getChoices(), $default_value);
    }
    else {
      $question->__construct($question->getQuestion(), $default_value);
    }
  }

  /**
   * Processes collected variables.
   */
  protected function processVars() :void {
    array_walk_recursive($this->vars, function (&$var, string $key, array $vars) :void {
      if (is_string($var)) {
        $var = Utils::replaceTokens($var, $vars);
      }
    }, $this->vars);
  }

  /**
   * Collects default variables.
   */
  protected function &collectDefault() :array {
    if ($this->nameQuestion) {
      $this->vars['name'] = $this->askNameQuestion();
    }
    if ($this->machineNameQuestion) {
      $this->vars['machine_name'] = $this->askMachineNameQuestion();
    }
    return $this->vars;
  }

  /**
   * Asks name question.
   */
  protected function askNameQuestion() :string {
    $root_directory = basename(Utils::getExtensionRoot($this->directory) ?: $this->directory);
    $default_value = Utils::machine2human($root_directory);
    $name_question = new Question($this->nameQuestion, $default_value);
    $name_question->setValidator([Utils::class, 'validateRequired']);
    return $this->askQuestion($name_question);
  }

  /**
   * Asks machine name question.
   */
  protected function askMachineNameQuestion() :string {
    $default_value = Utils::human2machine($this->vars['name'] ?? basename($this->directory));
    $machine_name_question = new Question($this->machineNameQuestion, $default_value);
    $machine_name_question->setValidator([Utils::class, 'validateMachineName']);
    return $this->askQuestion($machine_name_question);
  }

}
