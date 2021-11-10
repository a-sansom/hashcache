<?php

namespace Drupal\hashcache;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Class LazyContentBuilder.
 */
class LazyContentBuilder {

  use StringTranslationTrait;

  /**
   * Drupal's logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Drupal's current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * LazyContentBuilder constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal's logger factory service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Drupal's string translation service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Drupal's current user service.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerFactory, TranslationInterface $translation, AccountProxyInterface $currentUser) {
    $this->loggerFactory = $loggerFactory;
    $this->setStringTranslation($translation);
    $this->currentUser = $currentUser;
  }

  /**
   * Build render array item containing current user's username and a timestamp.
   *
   * A contrived example of 'highly dynamic' content in a render array that is
   * used as a '#lazy_builder' callback for a render element used as part of a
   * controller's response.
   *
   * @return array
   *   Render array containing 'dynamic' content.
   */
  public function usernameAndTimestamp() {
    return [
      'lazy_build_container' => [
        '#type' => 'container',
        '#attributes' => [
          'style' => [
            'border: 1px solid #000;',
            'margin: 10px;',
            'padding: 10px;',
          ],
        ],
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Content in this box came via #lazy_builder!'),
          '#cache' => [
            'max-age' => 0,
          ],
        ],
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('@userName the time is @time', ['@userName' => $this->currentUser->getDisplayName(), '@time' => time()]),
          '#cache' => [
            'max-age' => 0,
          ],
        ],
        'note' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Anonymous users do not get the content in this box.'),
          '#cache' => [
            'max-age' => 0,
          ],
        ],
        // Seeing as content contains username, only show to authenticated
        // users. We'll show different content to the anonymous users with a
        // separate sibling element with a #access using ->isAnonymous().
        //
        // Would make slightly more sense to define this on the array item that
        // defines the #lazy_builder, but, that's not allowed, and will trigger
        // a fatal error.
        '#access' => $this->currentUser->isAuthenticated(),
      ],
    ];
  }

}
