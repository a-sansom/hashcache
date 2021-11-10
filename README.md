# Hashcache module

A module to try and document/show/explain the behaviours around Drupal render array `#cache` values.

Install/enable the module, the same as any other, then visit the URL `/hashcache`.

The page at that URL contains links to different examples of simple render arrays using `#cache['max-age']`, `#cache['contexts']`, `#cache['tags']` and `#cache['keys']` and tries to explain how they can/do work together.

## Requirements

A default, out of the box Drupal set up is assumed.

Both the 'Internal Page Cache' and 'Dynamic Page Cache' modules enabled, as well as 'BigPipe'.

Configuration change(s) to [output cache debug headers](https://www.drupal.org/docs/8/api/responses/cacheableresponseinterface#debugging).

## Tips

Open the example(s) in one browser tab as an anonymous user, and then also a private browsing tab as an authenticated user.

In both browser tabs/windows open the developer tools 'Network' tab, so you can see the page requests and the response headers, where the cached response debug headers will be visible.

This should make it easier to understand what's happening when looking at each example route, as the response for both types of user can then be compared side by side.
