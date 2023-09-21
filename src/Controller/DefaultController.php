<?php

namespace Drupal\hashcache\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class DefaultController.
 */
class DefaultController extends ControllerBase {

  /**
   * Drupal's current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Symfony's request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Drupal's cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $tagsInvalidator;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Drupal's string translation service.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   Drupal's current user service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Symfony's request stack service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $tagsInvalidator
   *   Drupal's cache tags invalidator service.
   */
  public function __construct(TranslationInterface $translation, AccountProxy $currentUser, RequestStack $requestStack, CacheTagsInvalidatorInterface $tagsInvalidator) {
    $this->setStringTranslation($translation);
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
    $this->tagsInvalidator = $tagsInvalidator;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * Builds output with details about the module/examples it provides.
   *
   * @return array
   *   Render array showing description and links to the examples.
   */
  public function index() {
    $description = $this->t('
      <p>
        Links below are to various examples of using the different types of "#cache" render array metadata that affect the <a href="https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays" target="_blank">cacheability of render arrays</a>.
      </p>
      <p>
        The examples assume that you are using a default install of Drupal with the "Internal Page Cache", "Dynamic Page Cache" and "Big Pipe" modules installed/enabled.
      </p>
      <p>
        It goes without saying that you need caching enabled when looking at/running the examples! You will also want/need to have turned on <a href="https://www.drupal.org/docs/8/api/responses/cacheableresponseinterface#debugging" target="_blank">debugging cacheable responses</a> to be able to view the response header to see request cache hit/miss information.
      </p>
      <p>
        Each example will show slightly different behaviour depending on whether you are logged in when viewing it. Your user\'s logged in status affects which cache is used, Internal Page Cache, for anonymous users or Dynamic Page Cache for authenticated ones.
      </p>
      <p>
        Going through the examples sequentially to build understanding of the "#cache" values affects/behaviour is probably the best way to approach the items here.
      </p>
      <p>
        Unfortunately some of the descriptions get pretty long, but that\'s because the cache behaviour itself can get quite complicated.
      </p>
    ');

    return [
      'description' => [
        '#markup' => $description,
        '#cache' => [
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'links' => [
        '#theme' => 'item_list',
        '#list_type' => 'ol',
        '#items' => [
          Link::createFromRoute("#cache['max-age']", 'hashcache.default_controller_cacheMaxAge'),
          Link::createFromRoute("#cache['contexts']['url.query_args']", 'hashcache.default_controller_cacheContextsByUrlQueryArgs', ['iteration' => 0]),
          Link::createFromRoute("#cache['contexts']['url.query_args:<key>']", 'hashcache.default_controller_cacheContextsByUrlQueryArgsKey', ['iteration' => 0, 'delta' => 0]),
          Link::createFromRoute("#cache['tags']", 'hashcache.default_controller_cacheTags'),
          Link::createFromRoute('Entire render array invalidated via "bubbling"', 'hashcache.default_controller_cacheBubbling'),
          Link::createFromRoute("Avoid render array invalidation via 'bubbling' using #cache['keys'] (with 'max-age')", 'hashcache.default_controller_cacheAvoidBubblingWithCacheKeys'),
          Link::createFromRoute("Avoid render array invalidation via 'bubbling' using #cache['keys'] (with 'tags') targeting specific elements", 'hashcache.default_controller_cacheAvoidBubblingWithCacheKeysAndTags', ['iteration' => 0]),
          Link::createFromRoute("Mix cached content with dynamic, uncached, content using a #lazy_builder' (avoiding 'bubbling' invalidation)", 'hashcache.default_controller_lazyBuilder'),
          Link::createFromRoute("Mix 'expensive' template-themed preprocessed cached content with semi-dynamic cached content (avoiding 'bubbling' invalidation)", 'hashcache.default_controller_avoidExpense'),
        ],
        '#cache' => [
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'note' => [
        '#type' => 'link',
        '#title' => $this->t('Article that inspired creation of this module/these examples.'),
        '#url' => Url::fromUri('https://weknowinc.com/blog/drupal-8-add-cache-metadata-render-arrays', ['absolute' => TRUE]),
        '#options' => [
          'attributes' => [
            'target' => '_blank'
          ],
        ],
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      '#cache' => [
        'max-age' => Cache::PERMANENT,
      ],
    ];
  }

  /**
   * Example for render array #cache 'max-age'.
   *
   * @return array
   *   Render array demonstrating #cache 'max-age' property.
   */
  public function cacheMaxAge() {
    $description = $this->t('
      <p>
        For AUTHENTICATED users, the content below will be built/cached for 10 seconds after the first request.<br>
        For ANONYMOUS users, there are known issues with "max-age" (see link to docs below) the content will be built/cached permanently.
      </p>
      <p>
        If refreshing the page BEFORE 10 seconds has elapsed since the first request, the content will be read from the Internal Page Cache (for anonymous users) or the Dynamic Page Cache (for authenticated users), and the timestamp will remain the same.
      </p>
      <p>
        If refreshing the AFTER 10 seconds has elapsed since the first request, the content will be invalidated, rebuilt and cached (for AUTHENTICATED users), with a new timestamp value displayed.
      </p>
      <p>
        Open the browser developer tools and inspect the response headers "HIT" and "MISS" values:
      </p>
      <ul>
        <li>Internal Page Cache (anonymous users) uses a debug header "X-Drupal-Cache"</li>
        <li>Dynamic Page Cache (authenticated users) uses a debug header "X-Drupal-Dynamic-Cache"</li>
      </ul>
    ');

    return [
      'description' => [
        '#markup' => $description,
        '#cache' => [
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => $this->buildContainerAttributes(),
        'data' => [
          '#markup' => $this->t('Render array uses #cache with a "max-age" value of 10 (seconds): @time', ['@time' => time()]),
          '#cache' => [
            'max-age' => 10,
          ],
        ],
      ],
      'links' => [
        '#type' => 'link',
        '#title' => $this->t("Drupal.org documentation for #cache['max-age']"),
        '#url' => Url::fromUri('https://www.drupal.org/docs/drupal-apis/cache-api/cache-max-age', ['absolute' => TRUE]),
        '#options' => [
          'attributes' => [
            'target' => '_blank'
          ],
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Example for render array #cache 'contexts' using 'url.query_args'.
   *
   * @return array
   *   Render array demonstrating #cache 'contexts' property.
   */
  public function cacheContextsByUrlQueryArgs() {
    $iteration = $this->requestStack->getCurrentRequest()->query->get('iteration') ?? 0;

    $description = $this->t("
      <p>
        For ANONYMOUS and AUTHENTICATED users, on first request, the render array is built and cached.
      </p>
      <p>
        The render array will be invalidated/rebuilt/re-cached when:
      </p>
      <ul>
        <li>Any new URL query string parameter is added</li>
        <li>If an already present URL parameter's value changes, to a previously unused value</li>
      </ul>
      <p>
        Using a previous value for an existing query string param will mean a cached version of the page/render array is used.
      </p>
      <p>
        Look at/compare the response cache debug headers 'X-Drupal-Cache' and/or 'X-Drupal-Dynamic-Cache', in the browser developer tools, for different requests to see if the cache was hit/miss.
      </p>
    ");

    return [
      'description' => [
        '#markup' => $description,
        '#cache' => [
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => $this->buildContainerAttributes(),
        'data' => [
          'timestamp' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('Render array uses #cache with "contexts" key with "url.query_args":  @time', ['@time' => time()]),
            '#cache' => [
              'contexts' => [
                'url.query_args',
              ],
            ],
          ],
          'iteration' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('Iteration query string value is @iteration.', ['@iteration' => $iteration]),
          ],
        ],
      ],
      'links' => [
        '#theme' => 'item_list',
        '#list_type' => 'ol',
        '#items' => [
          Link::createFromRoute('Reload page, reading render array from cache', 'hashcache.default_controller_cacheContextsByUrlQueryArgs', ['iteration' => $iteration])->toRenderable(),
          Link::createFromRoute('Update query string "iteration" param value, invalidating render array', 'hashcache.default_controller_cacheContextsByUrlQueryArgs', ['iteration' => $iteration + 1])->toRenderable(),
        ],
      ],
      'docs_links' => [
        '#type' => 'link',
        '#title' => $this->t("Drupal.org documentation for #cache['contexts']"),
        '#url' => Url::fromUri('https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts', ['absolute' => TRUE]),
        '#options' => [
          'attributes' => [
            'target' => '_blank',
          ],
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Example for render array #cache 'contexts' using 'url.query_args:<key>'.
   *
   * @return array
   *   Render array demonstrating #cache 'contexts' property.
   */
  public function cacheContextsByUrlQueryArgsKey() {
    $iteration = $this->requestStack->getCurrentRequest()->query->get('iteration') ?? 0;
    $delta = $this->requestStack->getCurrentRequest()->query->get('delta') ?? 0;

    $description = $this->t("
      <p>
        For ANONYMOUS and AUTHENTICATED users, on first request, the render array is built and cached.
      </p>
      <p>
        Various parts of render array will be invalidated/rebuilt/re-cached when:
      </p>
      <ul>
        <li>A URL query string param named 'delta' is added (when it hasn't previously existed) to the request URL</li>
        <li>The value of the 'iteration' URL parameter value changes to a previously unused value</li>
        <li>The value of the 'delta' URL parameter value changes to a previously unused value</li>
      </ul>
      <p>
        Using a previous value for an existing query string param will mean a cached version of the parts of the render array is used.
      </p>
      <p>
        Adding, or changing, any other URL query string param won't invalidate the cached parts of the render array.
      </p>
      <p>
        Look at/compare the response cache debug headers 'X-Drupal-Cache' and/or 'X-Drupal-Dynamic-Cache', in the browser developer tools, for different requests to see if the cache was hit/miss.
      </p>
      <p>
        Enable the render cache debug setting (parameters.render.config.debug) in services.yml and look at the generated page markup for 'CACHE-HIT:' debug output. For debug output to be generated for part of a render array, you need to supply a #cache item with a 'keys' value. See comments in the source code.
      </p>
    ");

    return [
      'description' => [
        '#markup' => $description,
        // Although the example here is about cache contexts, to get render
        // cache debug output into the page markup (when enabled via
        // services.yml - see link in README.md), we need/must supply render
        // array '#cache' item with a 'keys' value.
        '#cache' => [
          'keys' => [
            'url_query_args_key_description',
          ],
        ],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => $this->buildContainerAttributes(),
        '#cache' => [
          'keys' => [
            'url_query_args_key_content',
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Value here only updates when "delta" query string value updated:  @time', ['@time' => time()]),
          '#cache' => [
            'keys' => [
              'url_query_args_key_timestamp',
            ],
            // Content will be updated ONLY when 'delta' query string value
            // changes. If we add a 'url.query_args:iteration' to the list of
            // contexts, the value will get updated when either the 'delta' OR
            // the 'iteration' URL query string values are updated to a new
            // (previously unused) value.
            'contexts' => [
              'url.query_args:delta',
            ],
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Iteration query string value is @iteration.', ['@iteration' => $iteration]),
          '#cache' => [
            'keys' => [
              'url_query_args_key_querystring_iteration',
            ],
            // Content will be updated ONLY when 'iteration' query string value
            // changes.
            'contexts' => [
              'url.query_args:iteration',
            ],
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Delta query string value is @delta.', ['@delta' => $delta]),
          '#cache' => [
            'keys' => [
              'url_query_args_key_querystring_delta',
            ],
            // Content will be updated ONLY when 'delta' query string value
            // changes.
            'contexts' => [
              'url.query_args:delta',
            ],
          ],
        ],
      ],
      'links' => [
        '#theme' => 'item_list',
        '#list_type' => 'ol',
        '#items' => [
          Link::createFromRoute('Reload page, reading render array from cache', 'hashcache.default_controller_cacheContextsByUrlQueryArgsKey', ['iteration' => $iteration, 'delta' => $delta])->toRenderable(),
          $this->t('Update query string "iteration" param value manually, NOT invalidating render array (keep the "delta" value the same)'),
          Link::createFromRoute('Update query string "delta" param value, invalidating render array ("iteration" value remains the same)', 'hashcache.default_controller_cacheContextsByUrlQueryArgsKey', ['iteration' => $iteration, 'delta' => $delta + 1])->toRenderable(),
        ],
        '#cache' => [
          'keys' => [
            'url_query_args_key_links',
          ],
          // Content will be updated when EITHER OF 'iteration' or 'delta' query
          // string values change, so the value(s) are used in the built links.
          'contexts' => [
            'url.query_args:iteration',
            'url.query_args:delta',
          ],
        ],
      ],
      'docs_links' => [
        '#type' => 'link',
        '#title' => $this->t("Drupal.org documentation for #cache['contexts']"),
        '#url' => Url::fromUri('https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts#core-contexts', ['absolute' => TRUE]),
        '#options' => [
          'attributes' => [
            'target' => '_blank',
          ],
        ],
        '#cache' => [
          'keys' => [
            'url_query_args_key_docs_links',
          ],
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Example for render array #cache 'tags'.
   *
   * @return array
   *   Render array demonstrating #cache 'tags' property.
   */
  public function cacheTags() {
    $description = $this->t("
      <p>
        You can add cache 'tags' to render array elements. When a tag is invalidated, cached render array items that are tagged with the invalidated tag (or tags) will be rebuilt.
      </p>
      <p>
        Drupal entities have a handy method <code>getCacheTags()</code> that gets the list of tags for an entity. In the case of a Drupal User entity, this is 'user:&lt;user_id&gt;', for example 'user:123'.
      </p>
      <p>
        If a User entity is updated/saved, for instance by updating the 'Username' value (via the user edit form), its cache tags get invalidated. This means that any cached render arrays tagged with the user's cache tag, 'user:123' are also invalidated.
      </p>
      <p>
        For ANONYMOUS users, when this page was loaded, there was no User entity to get any tags from, before the render array was built and cached. The response will have an Internal Page Cache debug header of 'X-Drupal-Cache: MISS'. All anonymous users that subsequently load the page will then get the same version of the array shown to them, but, each subsequent response will have an Internal Page Cache debug header of 'X-Drupal-Cache: HIT' (unless/until a cache rebuild is/has been triggered).
      </p>
      <p>
        For AUTHENTICATED users, when this page was loaded, we load the full User entity, get the 'Username' value, call <code>getCacheTags()</code> and use both values in the render array that's built and cached. The response will have an Internal Page Cache debug header of 'X-Drupal-Dynamic-Cache: MISS'. Any subsequent loads of the page, byt the same user, will be read from the cache, the message will be the same and the responses will have a debug header of 'X-Drupal-Dynamic-Cache: HIT'. Now, for the AUTHENTICATED user, because the render array uses the User entity cache tag(s), if we:
      </p>
      <ul>
        <li>Go to the <a href=\"@userEditForm\">user edit form</a></li>
        <li>Change the username value, as that's shown in our cached render array below</li>
        <li>Save the changed details</li>
        <li>Revisit this page</li>
      </ul>
      <p>
        When we 'Save the changed details', the cache tags associated with the user are invalidated, which invalidates the render array on this page. That invalidation means that when we revisit this page, the render array is rebuilt and re-cached with the updated Username and new timestamp value.
      </p>
    ", ['@userEditForm' => Url::fromRoute('entity.user.edit_form', ['user' => $this->currentUser->id()])->toString()]);

    // Default list of cache tags for the render array.
    $cacheTags = [];

    if ($this->currentUser->isAuthenticated()) {
      // Get the full Drupal User entity for the authenticated user.
      /** @var \Drupal\user\Entity\User $entity */
      $entity = User::load($this->currentUser->id());

      // The User entity gets a getCacheTags() method, from
      // extending Drupal\Core\Entity\ContentEntityBase, that returns the
      // tags(s) as an array of strings such as [user:<user_id>].
      $cacheTags = $entity->getCacheTags();
    }

    return [
      'description' => [
        '#markup' => $description,
        '#cache' => [
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => $this->buildContainerAttributes(),
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('@userName the time is @time', ['@userName' => $this->currentUser->getDisplayName(), '@time' => time()]),
          '#cache' => [
            'tags' => $cacheTags,
          ],
        ],
      ],
      'docs_links' => [
        [
          '#type' => 'link',
          '#title' => $this->t("Drupal.org documentation for #cache['tags']"),
          '#url' => Url::fromUri('https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags', ['absolute' => TRUE]),
          '#options' => [
            'attributes' => [
              'target' => '_blank',
            ],
          ],
          '#suffix' => '<br>',
        ],
        [
          '#type' => 'link',
          '#title' => $this->t("Drupal.org documentation for #cache['tags'] debug headers"),
          '#url' => Url::fromUri('https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags#headers', ['absolute' => TRUE]),
          '#options' => [
            'attributes' => [
              'target' => '_blank',
            ],
          ],
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Example of render array #cache keys CAUSING 'bubbling'/cache invalidation.
   *
   * @return array
   *   Render array demonstrating #cache keys causing 'bubbling' of cache
   *   invalidation.
   */
  public function cacheBubbling() {
    $description = $this->t('
      <p>
        For ANONYMOUS users this array will get built and cached, by Internal Page Cache, after first load and the response will have a (Internal Page Cache, debug) header of "X-Drupal-Cache: MISS".
      </p>
      <p>
        All subsequent ANONYMOUS user requests will be read from cache, will have a (Internal Page Cache, debug) header of "X-Drupal-Cache: HIT" and all "time()" outputs will remain the same. ANONYMOUS user responses will always have a (Dynamic Page Cache, debug) header "X-Drupal-Dynamic-Cache: MISS".
      </p>
      <p>
        For AUTHENTICATED users this array will get built and cached, by Dynamic Page Cache, after first load and the response will have (Dynamic Page Cache) header of "X-Drupal-Dynamic-Cache: MISS". There will be no (Internal Page Cache) "X-Drupal-Cache" header in the response.
      </p>
      <p>
        If another request for the page is made BEFORE the shortest "max-age" value (10 seconds) of a render array element (3.) has elapsed, the response will be read from cache AND have header of "X-Drupal-Dynamic-Cache: HIT" meaning the "time()" values will remain the same.
      </p>
      <p>
        If another request for the page is made AFTER the shortest "max-age" value (10 seconds) of a render array element (3.) has elapsed the ENTIRE array is invalidated due to the cache invalidation of the render element "bubbling" up through the array invalidating all the elements. The response will have header of "X-Drupal-Dynamic-Cache: MISS" and the "time()" values will be updated, the render array rebuilt and cached.
      </p>
      <p>
        For anonymous OR authenticated users, if we add a URL query string parameter (Eg. ?x=0), the (Internal Page Cache OR Dynamic Page Cache) cached version of the page is invalidated due to the last render array element (5.) having a #cache "contexts" value of "url.query_args" that "bubbles" through to the top of the render array.
      </p>
      <p>
        For ANONYMOUS users, the next request, using the SAME query string parameter AND value (?x=0), will come via the Internal Page Cache, with the header "X-Drupal-Cache: HIT".
        For ANONYMOUS users, the next request, using the SAME query string parameter AND a DIFFERENT value (?x=1), will invalidate the (Internal Page) Cache item, the "time()" values will be updated, and the response will have a header "X-Drupal-Cache: MISS".
      <p>
      <p>
        For AUTHENTICATED users, the next request, using the SAME query string AND value (?x=0) AND being within the shortest "max-age" value (10 seconds) of a render array element, will come via the Dynamic Page Cache, with the header "X-Drupal-Dynamic-Cache: HIT".
        For AUTHENTICATED users, the next request, using the SAME query string AND a DIFFERENT value (?x=1) AND being within the shortest "max-age" value (10 seconds) of a render array element, will invalidate the (Dynamic Page) Cache item, the "time()" values will be updated, and the response will have a header "X-Drupal-Dynamic-Cache: MISS".
      </p>
      <p>
        The important thing to note for the last sentence above, for AUTHENTICATED users, is that, having been cached, the ENTIRE cached render array is invalidated, EITHER by the query string value changing (when requesting the page again within 10 seconds) OR by 10 seconds having elapsed.
      </p>
    ');

    return [
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $description,
        // Explicitly provide a #cache, for avoidance of doubt.
        '#cache' => [],
      ],
      'data' => [
        '#type' => 'container',
        '#attributes' => $this->buildContainerAttributes(),
        'cache_permanent_implicit' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t("1. This render array element is IMPLICITLY 'max-age' of Cache::PERMANENT: @time", ['@time' => time()]),
          '#cache' => [],
        ],
        'cache_permanent_explicit' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t("2. This render array element is EXPLICITLY 'max-age' of Cache::PERMANENT: @time", ['@time' => time()]),
          '#cache' => [
            'max-age' => Cache::PERMANENT,
          ],
        ],
        'cache_time_limited' => [
          'ten_seconds' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t("3. This render array element is 'max-age' 10 seconds: @time", ['@time' => time()]),
            '#cache' => [
              'max-age' => 10,
            ],
          ],
          'twenty_seconds' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t("4. This render array element is 'max-age' 20 seconds: @time", ['@time' => time()]),
            '#cache' => [
              'max-age' => 20,
            ],
          ],
        ],
        'cache_contexts_url_query_args' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t("5. This render array element is 'contexts' of 'url.query_args': @time", ['@time' => time()]),
          '#cache' => [
            'contexts' => [
              'url.query_args',
            ],
          ],
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Example of render array #cache keys AVOIDING 'bubbling'/cache invalidation
   * of the entire array with #cache['keys'].
   *
   * @return array
   *   Render array demonstrating #cache 'keys' property.
   */
  public function cacheAvoidBubblingWithCacheKeys() {
    $description = $this->t('
      <p>
        These aren\'t the most easy to describe examples (unsure those exist with caching), but they try to show and explain combinations of the various #cache keys (using "keys") amd behaviour for both types of user.
      </p>
      <h2>Behaviour for anonymous users</h2>
      <p>
        For ANONYMOUS users this array will get built and cached by Internal Page Cache after first load (same as the <a href="@cacheBubblingExample">Entire render array invalidated via "bubbling" example</a>). The FIRST load the response will have a (Internal Page Cache, debug) header of "X-Drupal-Cache: MISS" and all "time()" values will be the same for ALL elements.
      </p>
      <p>
        All subsequent ANONYMOUS user requests will be read from cache, will have a (Internal Page Cache, debug) header of "X-Drupal-Cache: HIT", and all "time()" outputs will remain the same. All the ANONYMOUS user responses will always have a (Dynamic Page Cache, debug) header "X-Drupal-Dynamic-Cache: MISS".
      </p>
      <p>
        For ANONYMOUS users, if we add a URL query string parameter (e.g. ?x=0) AND the page is requested again WITHIN 10 seconds (the SHORTEST element "max-age" value in the render array) of the first request, the response will have a (Internal Page Cache) header of "X-Drupal-Cache: MISS" and ALL "time()" output values will remain the same EXCEPT the last element (5.), because that element was invalidated via its "contexts" value (due to the addition of the URL parameter), so its "time()" value was recalculated.
        <strong>However, note that NO cache invalidation "bubbling" has occurred to the other elements in the array. Their "time()" values remain the same, it was only the last item (5.) that was invalidated</strong>.
        This is because we have also given each of the render array elements a #cache "keys" value.
      </p>
      <p>
        For ANONYMOUS users, if we add a URL query string parameter (e.g. ?x=0) AND the page is requested again BETWEEN 10 and 20 seconds (the SHORTEST AND LONGEST element "max-age" values in the array) of the first request, the response will have a (Internal Page Cache) header of "X-Drupal-Cache: MISS" and "time()" output values will remain the same for ALL items EXCEPT the element with "max-age" of 10 (3.) AND the last element (5.), because the element\'s "max-age" was exceeded, so invalidated, AND the last element was invalidated via its "contexts" value (due to the addition of the URL parameter), so both their "time()" values are recalculated.
        <strong>Again, note that NO cache invalidation "bubbling" has occurred to the other elements in the array</strong>.
      </p>
      <p>
        For ANONYMOUS users, if we add a URL query string parameter (e.g. ?x=0) AND the page is requested again AFTER 20 seconds (the LONGEST element "max-age" value in the array) of the first request, the response will have a (Internal Page Cache) header of "X-Drupal-Cache: MISS" and "time()" output values will remain the same ONLY for the first two permanently cached elements (1. and 2.). Both elements with a "max-age" are invalidated due to time elapsed being greater thn their values AND the last element was invalidated via its "contexts" value (due to the addition of the URL parameter), so all three elements have their "time()" values recalculated.
      </p>
      <p>
        You can see that each of the render elements can be invalidated independently of others in the same array when the request made meets particular criteria (because we have added #cache "keys").
      </p>
      <h2>Behaviour for authenticated users</h2>
      <p>
        For AUTHENTICATED users there will be no (Internal Page Cache) "X-Drupal-Cache" header in the response and on FIRST load the response will have a (Dynamic Page Cache) header of "X-Drupal-Dynamic-Cache: MISS".
      </p>
      <p>
        For AUTHENTICATED users, if the page is requested again WITHIN 10 seconds (the SHORTEST element "max-age" value in the array), the response will have a (Dynamic Page Cache) header of "X-Drupal-Dynamic-Cache: HIT" and ALL the "time()" output values will remain the same.
      </p>
      <p>
        For AUTHENTICATED users, if the page is requested again BETWEEN 10 and 20 seconds (the SHORTEST AND LONGEST element "max-age" values in the array), the response will have a (Dynamic Page Cache) header of "X-Drupal-Dynamic-Cache: MISS" and ALL the "time()" output values will remain the same EXCEPT the element with a "max-age" of 10 (3.) because it has been invalidated and the "time()" value is recalculated.
        <strong>However, note that NO cache invalidation "bubbling" has occurred to the other elements in the array</strong>
      </p>
      <p>
        For AUTHENTICATED users, if the page is requested again AFTER 20 seconds (the LONGEST element "max-age" value in the array), the response will have a (Dynamic Page Cache) header of "X-Drupal-Dynamic-Cache: MISS" and ALL the "time()" output values will remain the same EXCEPT the elements with a "max-age" of 10 AND a "max-age" of 20 (3. and 4.) because they have been invalidated and the "time()" value is recalculated for them.
        <strong>Again, note that NO cache invalidation "bubbling" has occurred to the other elements in the array</strong>
      </p>
      <p>
        For AUTHENTICATED users, if we add a URL query string parameter (e.g. ?x=0) AND the page is requested again WITHIN 10 seconds (the SHORTEST element "max-age" value in the array) of the first request, the response will have a (Dynamic Page Cache) header of "X-Drupal-Dynamic-Cache: MISS" and ALL "time()" output values will remain the same EXCEPT the last element (5.), because that element was invalidated via its "contexts" value (due to the addition of the URL parameter), so its "time()" value was recalculated.
        <strong>Again, note that NO cache invalidation "bubbling" has occurred to the other elements in the array</strong>.
      </p>
      <p>
        For AUTHENTICATED users, if we add a URL query string parameter (e.g. ?x=0) AND the page is requested again BETWEEN 10 and 20 seconds (the SHORTEST AND LONGEST element "max-age" values in the array) of the first request, the response will have a (Dynamic Page Cache) header of "X-Drupal-Dynamic-Cache: MISS" and ALL "time()" output values will remain the same EXCEPT the element with a "max-age" of 10 (3.) AND the last element (5.), because those elements have been invalidated via the "max-age" and "contexts" values, so their "time()" values are recalculated.
      </p>
      <p>
        For AUTHENTICATED users, if we add a URL query string parameter (e.g. ?x=0) AND the page is requested again AFTER 20 seconds (the LONGEST element "max-age" value in the array) of the first request, the response will have a (Dynamic Page Cache) header of "X-Drupal-Dynamic-Cache: MISS" and ALL "time()" output values will remain the same EXCEPT the ELEMENTS with a "max-age" of 10 (3.) AND a "max-age" of 20 (4.) AND the last item (5.), because those items have been invalidated via the "max-age" and "contexts" values, so their "time()" values are recalculated.
        <strong>Again, note that NO cache invalidation "bubbling" has occurred to the other elements in the array</strong>.
      </p>
      <h2>Summary</h2>
      <p>
        Throughout all requests the items that have a "max-age" of Cache::PERMANENT NEVER have their "time()" values recalculated after the first request.
      </p>
      <p>
        These convoluted examples show that by giving the render array elements "keys" that identify the various parts of the render array they can be invalidated/values refreshed independently of other render array elements by using #cache "keys" (alongside "max-age" and "contexts").
      </p>
    ', ['@cacheBubblingExample' => Url::fromRoute('hashcache.default_controller_cacheBubbling')->toString()]);

    return [
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $description,
        '#cache' => [
          'keys' => [
            'hashcache_desc_avoidBubbleKeys',
          ],
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'data' => [
        '#type' => 'container',
        '#attributes' => $this->buildContainerAttributes(),
        // time() output should never change.
        'cache_permanent_implicit' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t("1. This render array element is IMPLICITLY 'max-age' of Cache::PERMANENT AND has 'keys' of 'hashcache_permanent_implicit': @time", ['@time' => time()]),
          '#cache' => [
            'keys' => [
              'hashcache_permanent_implicit',
            ],
          ],
        ],
        // time() output should never change.
        'cache_permanent_explicit' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t("2. This render array element is EXPLICITLY 'max-age' of Cache::PERMANENT AND has 'keys' of 'hashcache_permanent_explicit': @time", ['@time' => time()]),
          '#cache' => [
            'max-age' => Cache::PERMANENT,
            'keys' => [
              'hashcache_permanent_explicit',
            ],
          ],
        ],
        'cache_time_limited_with_keys' => [
          // time() output should change every 20 seconds.
          'twenty_seconds' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t("3. This render array element is 'max-age' 10 (seconds) AND 'keys' of 'hashcache_ten_seconds': @time", ['@time' => time()]),
            '#cache' => [
              'max-age' => 10,
              'keys' => [
                'hashcache_ten_seconds',
              ],
            ],
          ],
          // time() output should change every 10 seconds.
          'ten_seconds' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t("4. This render array element is 'max-age' 20 (seconds) AND 'keys' of 'hashcache_twenty_seconds': @time", ['@time' => time()]),
            '#cache' => [
              'max-age' => 20,
              'keys' => [
                'hashcache_twenty_seconds',
              ],
            ],
          ],
        ],
        // time() output should change only when adding/changing a URL query string param.
        'cache_contexts_url_query_args' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t("5. This render array element is 'contexts' of 'url.query_args' AND 'keys' of 'hashcache_contexts_url': @time", ['@time' => time()]),
          '#cache' => [
            'contexts' => [
              'url.query_args',
            ],
            'keys' => [
              'hashcache_contexts_url',
            ],
          ],
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Example of render array #cache keys AND 'tags' targeting multiple elements
   * at once but AVOIDING 'bubbling'/cache invalidation of entire array.
   *
   * @return array
   *   Render array demonstrating #cache 'keys' property used with the 'tags'
   *   property.
   */
  public function cacheAvoidBubblingWithCacheKeysAndTags() {
    $iteration = $this->requestStack->getCurrentRequest()->query->get('iteration');

    if (in_array($this->requestStack->getCurrentRequest()->query->get('invalidate'), ['hashcache_odd_items', 'hashcache_even_items'])) {
      $invalidate = $this->requestStack->getCurrentRequest()->query->get('invalidate');
      $this->tagsInvalidator->invalidateTags([$invalidate]);
    }

    $description = $this->t('
      <p>
        Requests as an ANONYMOUS user use the Internal Page Cache, with the "X-Drupal-Cache" response header.
      </p>
      <p>
        Requests as an AUTHENTICATED user use the Dynamic Page Cache, with the "X-Drupal-Dynamic-Cache" response header.
      </p>
      <p>
        Use the links below to:
      </p>
      <ul>
        <li>Update the "iteration" value AND add a query string "&invalidate=hashcache_odd_items" to invalidate (and recalculate), and re-cache, the first and third list items</li>
        <li>Update the "iteration" value AND add a query string "?invalidate=hashcache_even_items" to invalidate (and recalculate), and re-cache, the second and fourth list items</li>
        <li>Read page from cache, with only an ?iteration=num URL query string. The page will build, output, and cache the render array on first request. Subsequent requests will read the page from cache.</li>
      </ul>
      <p>
        By using a unique #cache "keys" value for each render array element, but the same #cache "tags" value (of "hashcache_odd_items" or "hashcache_even_items") we\'re able to target multiple elements to be invalidated at the same time, without "bubbling" affecting other elements in the array.
      </p>
      <p>
        We use the "iteration" query string parameter as a trigger for invalidation in the example. This would be achieved programmatically using the cache tag invalidation service, in a form submission etc in a non-example scenario.
      </p>
    ');

    return [
      'description' => [
        '#markup' => $description,
        '#cache' => [
          'keys' => [
            'hashcache_desc_avoidBubbleKeysAndTags',
          ],
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'data' => [
        '#type' => 'container',
        '#attributes' => $this->buildContainerAttributes(),
        'one' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('1. This render array element is tagged with "hashcache_odd_items". Time is @time', ['@time' => time()]),
          '#cache' => [
            'keys' => [
              'hashcache_first',
            ],
            'tags' => [
              'hashcache_odd_items',
            ],
          ],
        ],
        'two' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('2. This render array element is tagged with "hashcache_even_items". Time of render is @time', ['@time' => time()]),
          '#cache' => [
            'keys' => [
              'hashcache_second',
            ],
            'tags' => [
              'hashcache_even_items',
            ],
          ],
        ],
        'three' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('3. This render array element is tagged with "hashcache_odd_items". Time of render is @time', ['@time' => time()]),
          '#cache' => [
            'keys' => [
              'hashcache_third',
            ],
            'tags' => [
              'hashcache_odd_items',
            ],
          ],
        ],
        'four' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('4. This render array element is tagged with "hashcache_even_items". Time of render is @time', ['@time' => time()]),
          '#cache' => [
            'keys' => [
              'hashcache_fourth',
            ],
            'tags' => [
              'hashcache_even_items',
            ],
          ],
        ],
        'iteration' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Iteration query string value is @iteration.', ['@iteration' => $iteration]),
          '#cache' => [
            'contexts' => [
              'url.query_args:iteration',
            ],
          ],
        ],
      ],
      'invalidate_odd' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        // An 'html_tag' render element having a child render element: https://www.drupal.org/node/2887146
        Link::createFromRoute('Invalidate render array elements tagged "hashcache_odd_items" (1 and 3 above)', 'hashcache.default_controller_cacheAvoidBubblingWithCacheKeysAndTags', ['iteration' => $iteration + 1, 'invalidate' => 'hashcache_odd_items'])->toRenderable(),
        '#cache' => [
          'keys' => [
            'hashcache_iterator_link_odd',
          ],
          'contexts' => [
            'url.query_args:iteration',
          ],
        ],
      ],
      'invalidate_even' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        Link::createFromRoute('Invalidate render array elements tagged "hashcache_even_items" (2 and 4 above)', 'hashcache.default_controller_cacheAvoidBubblingWithCacheKeysAndTags', ['iteration' => $iteration + 1, 'invalidate' => 'hashcache_even_items'])->toRenderable(),
        '#cache' => [
          'keys' => [
            'hashcache_iterator_link_even',
          ],
          'contexts' => [
            'url.query_args:iteration',
          ],
        ],
      ],
      'no_invalidation' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        Link::createFromRoute('No invalidation (read from cache)', 'hashcache.default_controller_cacheAvoidBubblingWithCacheKeysAndTags', ['iteration' => $iteration])->toRenderable(),
        '#cache' => [
          'keys' => [
            'hashcache_link_read_cache',
          ],
          'contexts' => [
            'url.query_args:iteration',
          ],
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Example of render array with #cache and content using #lazy_builder.
   *
   * @return array
   *   Render array demonstrating #cache and #lazy_builder content.
   */
  public function lazyBuilder() {
    $description = $this->t('
      <p>
        For ANONYMOUS users, on first request, the render array is built and cached with Internal Page Cache. The response will have a "X-Drupal-Cache: MISS" header. Subsequent ANONYMOUS user request responses will have a "X-Drupal-Cache: HIT" header and the page content will remain the same.
      </p>
      <p>
        For AUTHENTICATED users, on the first request, the render array is built and cached with the Dynamic Page Cache. The response will have a "X-Drupal-Dynamic-Cache: MISS" header. Subsequent AUTHENTICATED user request responses will have a "X-Drupal-Dynamic-Cache: HIT" header, the BOTTOM box of content will NOT change (as it is cached). The TOP box content WILL change (because it is NOT cached and coming via the #lazy_builder render array element).
      </p>
      <p>
        For ALL of the AUTHENTICATED user requests, when the page has finished loading, an <a href="https://www.drupal.org/docs/drupal-apis/ajax-api/core-ajax-callback-commands" target="_blank">AJAX API</a> <a href="https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Ajax%21ReplaceCommand.php/class/ReplaceCommand/" target="_blank">"Replace" command</a> (<a href="https://api.drupal.org/api/drupal/core%21modules%21big_pipe%21src%21Render%21BigPipe.php/9.2.x" target="_blank">via the BigPipe</a> module) for the #lazy_builder render array element is run, replacing the placeholder generated for it in the document markup. If you view the page source, you can see the &lt;script&gt; tag for the AJAX API command at the bottom of the page. It doesn\'t matter if the rest of the page has been previously built, (Dynamic Page) cached, and loaded from the (Dynamic Page) cache, the #lazy_builder content is ALWAYS updated.
      </p>
      <p>
        With the right mix of render array element #keys, #contexts, #tags etc, as described in previous examples, we also are able to avoid invalidation bubbling up the render array.
      </p>
      <p>
        Using a #lazy_builder render element is only useful for AUTHENTICATED users because AUTHENTICATED users use the Dynamic Page Cache. If it is used with an ANONYMOUS user, the Internal Page Cache will cache the page after the first request, and that is that (until you clear caches).
      </p>
    ');

    return [
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $description,
        '#cache' => [
          'keys' => [
            'hashcache_description_lazyBuilder',
          ],
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => $this->buildContainerAttributes(),
        'lazy_content' => [
          '#lazy_builder' => [
            'hashcache.lazy_content_builder:usernameAndTimestamp',
            [],
          ],
          '#create_placeholder' => TRUE,
        ],
        'non_lazy_content' => [
          '#type' => 'container',
          '#attributes' => $this->buildContainerAttributes(),
          'non_lazy_description' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('Content in this box is NOT via #lazy_builder!'),
            '#cache' => [
              'keys' => [
                'hashcache_non_lazy_description_lazyBuilder',
              ],
              'max-age' => Cache::PERMANENT,
            ],
          ],
          'anonymous_only_content' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t("As you're currently an anonymous user, you're NOT SEEING the #lazy_builder generated content. Log in and then view this page."),
            // Ensure only anonymous users see this content.
            '#access' => $this->currentUser->isAnonymous(),
            '#cache' => [
              'keys' => [
                'hashcache_anonymous_only_content_lazyBuilder',
              ],
              'max-age' => Cache::PERMANENT,
            ],
          ],
          'cachable_content_1' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('This render element is cached/cachable content, NOT part of the #lazy_builder content. The time is (always) @time', ['@time'=> time()]),
            '#cache' => [
              'keys' => [
                'hashcache_cachable_content_1_lazyBuilder',
              ],
              'max-age' => Cache::PERMANENT,
            ],
          ],
          'cachable_content_2' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('It shows that a render array can mix both cached/cachable render array items and also contain uncached/uncachable render array items, generated via a "#lazy_builder" render array element (for authenticated users), in the same render array, where the dynamic content is able to change but not affect the cachability of the rest of the array.'),
            '#cache' => [
              'keys' => [
                'hashcache_cachable_content_2_lazyBuilder',
              ],
              'max-age' => Cache::PERMANENT,
            ],
          ],
        ],
      ],
      'links' => [
        [
          '#type' => 'link',
          '#title' => $this->t("Drupal.org documentation for #lazy_builder"),
          '#url' => Url::fromUri('https://www.drupal.org/node/2498803', ['absolute' => TRUE]),
          '#options' => [
            'attributes' => [
              'target' => '_blank'
            ],
          ],
          '#suffix' => '<br>',
        ],
        [
          '#type' => 'link',
          '#title' => $this->t("Drupal.org documentation for 'Auto-placeholdering'"),
          '#url' => Url::fromUri('https://www.drupal.org/docs/drupal-apis/render-api/auto-placeholdering', ['absolute' => TRUE]),
          '#options' => [
            'attributes' => [
              'target' => '_blank'
            ],
          ],
          '#suffix' => '<br>',
        ],
        [
          '#type' => 'link',
          '#title' => $this->t("Drupal.org documentation for \Drupal\Core\Render\Element\RenderElement"),
          '#url' => Url::fromUri('https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Element%21RenderElement.php/class/RenderElement/9.2.x', ['absolute' => TRUE]),
          '#options' => [
            'attributes' => [
              'target' => '_blank'
            ],
          ],
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Example of render array mixing "inline" render elements with a Twig
   * template themed item, using caching to limit expensive processing.
   *
   * @return array
   *   Render array demonstrating #cache and #theme-d content.
   */
  public function avoidExpense() {
    // Define how "expensive" (number of seconds) the Twig template's preprocess
    // function is.
    $preprocess_pause_duration = 3;

    $description = $this->t('
      <p>
        Example returns a render array which contains an "inline" render array item with some semi-dynamic content, that is cached for 30 seconds alongside a sibling render array item that is themed by a Twig template and permanently cached.
      </p>
      <p>
        This approach is applicable for Dynamic Page Cache-d content (AUTHENTICATED users), as, the content needs to change sometimes. For ANONYMOUS users using the Internal Page Cache, this page is built once, cached, and remains the same until caches are rebuilt/cleared.
      </p>
    ');

    return [
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $description,
        '#cache' => [
          'keys' => [
            'hashcache_description_avoidExpense',
          ],
          'max-age' => Cache::PERMANENT,
        ],
      ],
      'content' => [
        'inline' => [
          '#type' => 'container',
          '#attributes' => $this->buildContainerAttributes(),
          'content' => [
            '#markup' => $this->t('
              <p>
                Content here is from the "inline" render array element.
              </p>
              <p>
                Time here (cached for 30s) is: @time
              </p>
            ', ['@time' => time()]),
            '#cache' => [
              'keys' => [
                'hashcache_inline_avoidExpense',
              ],
              'max-age' => 30,
            ],
          ],
        ],
        'templated' => [
          '#type' => 'container',
          '#attributes' => $this->buildContainerAttributes(),
          'content' => [
            '#theme' => 'avoid_expense',
            '#time' => time(),
            '#preprocess_pause_duration' => $preprocess_pause_duration,
            '#cache' => [
              'keys' => [
                'hashcache_templated_avoidExpense',
              ],
              'max-age' => Cache::PERMANENT,
            ],
          ],
        ],
      ],
      'note' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('
          <p>
            NOTE: One potentially confusing thing with this example is that the response "X-Drupal-Dynamic-Cache" cache debug header returns a "MISS" value when re-requesting the page after 30 seconds.
          </p>
          <p>
            In one way this makes sense, because some part of the render array will have expired from the cache and needs regenerating (the item that has been cached for 30 seconds). But other item(s) in the array, including the expensive-to-build Twig template-based one, are cached permanently and clearly coming from cache, because there is no @preprocess_pause_duration pause before the page loads, meaning the template preprocess hook code was not executed.
          </p>
          <p>
            It seems that the "X-Drupal-Dynamic-Cache" cache debug header will be "MISS" if ANY part of the render array has expired(?). This could be a situation where you might consider usage of a #lazy_builder (shown in a previous example) for certain sections of the page content being built, I guess.
          </p>
        ', ['@preprocess_pause_duration' => $preprocess_pause_duration]),
        '#cache' => [
          'keys' => [
            'hashcache_note_avoidExpense',
          ],
          'max-age' => Cache::PERMANENT,
        ],
      ],
      $this->buildBackToIndexElement(),
    ];
  }

  /**
   * Build array of #attributes for 'container' element used in examples.
   *
   * @return array
   *   Keyed array to use for a container element's #attributes.
   */
  private function buildContainerAttributes() {
    return [
      'style' => [
        'border: 1px solid #000;',
        'margin: 10px;',
        'padding: 10px;',
      ],
    ];
  }

  /**
   * Build a link back to the module's examples index.
   *
   * @return array
   *   Render array for link back to example index.
   */
  private function buildBackToIndexElement() {
    return [
      '#type' => 'container',
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Back to #cache examples'),
        '#url' => Url::fromRoute('hashcache.default_controller_index'),
      ],
      '#cache' => [
        'keys' => [
          'hashcache_example_backLink',
        ],
        'max-age' => Cache::PERMANENT,
      ],
    ];
  }

}
