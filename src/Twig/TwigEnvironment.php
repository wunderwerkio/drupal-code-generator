<?php

namespace DrupalCodeGenerator\Twig;

use DrupalCodeGenerator\Utils;
use Twig\TokenStream;
use Twig_Environment;
use Twig_LoaderInterface;
use Twig_SimpleFilter;
use Twig\Source;

/**
 * Stores the Twig configuration.
 */
class TwigEnvironment extends Twig_Environment {

  /**
   * Constructs Twig environment object.
   *
   * @param \Twig_LoaderInterface $loader
   *   The Twig loader.
   */
  public function __construct(Twig_LoaderInterface $loader) {
    parent::__construct($loader);

    $this->addFilter(new Twig_SimpleFilter('pluralize', [Utils::class, 'pluralize']));

    $this->addFilter(new Twig_SimpleFilter('article', function ($string) {
      $article = in_array(strtolower($string[0]), ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';
      return $article . ' ' . $string;
    }));

    $this->addFilter(new Twig_SimpleFilter('u2h', function ($string) {
      return str_replace('_', '-', $string);
    }));

    $this->addFilter(new Twig_SimpleFilter('h2u', function ($string) {
      return str_replace('-', '_', $string);
    }));

    $this->addFilter(new Twig_SimpleFilter('camelize', function ($string, $upper_mode = TRUE) {
      return Utils::camelize($string, $upper_mode);
    }));

    $this->addTokenParser(new TwigSortTokenParser());
  }

  /**
   * {@inheritdoc}
   */
  public function tokenize(Source $source) :TokenStream {
    // Remove leading whitespaces to preserve indentation.
    // @see https://github.com/twigphp/Twig/issues/1423
    $code = $source->getCode();
    if (strpos($code, '{% verbatim %}') === FALSE) {
      $code = preg_replace("/\n +\{%/", "\n{%", $source->getCode());
    }
    // Twig source has no setters.
    $source = new \Twig_Source($code, $source->getName(), $source->getPath());
    return parent::tokenize($source);
  }

}
