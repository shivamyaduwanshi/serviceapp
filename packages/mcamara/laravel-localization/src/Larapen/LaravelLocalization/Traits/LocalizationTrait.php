<?php
/**
 * LaraClassified - Classified Ads Web Application
 * Copyright (c) BedigitCom. All Rights Reserved
 *
 * Website: http://www.bedigit.com
 *
 * LICENSE
 * -------
 * This software is furnished under a license and may be used and copied
 * only in accordance with the terms of such license and with the inclusion
 * of the above copyright notice. If you Purchased from Codecanyon,
 * Please read the full License from here - http://codecanyon.net/licenses/standard
 */

namespace Larapen\LaravelLocalization\Traits;

use App\Models\Category;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait LocalizationTrait
{
    public static $cacheExpiration  = 3600;

	/**
	 * Get URL through the current Controller
	 *
	 * @param null $locale
	 * @param array $attributes
	 * @return null|string
	 */
	public function getUrlThroughCurrentController($locale = null, $attributes = [])
	{
		$url = null;
		
		if (empty($locale)) {
			$locale = $this->getCurrentLocale();
		}
		
		// Get the Query String
		$queryString = !empty(request()->getQueryString()) ? '?' . request()->getQueryString() : '';
		
		// Get the Country Code
		$countryCode = $this->getCountryCode($attributes);
		
		// Get the Locale Path
		$localePath = $this->getLocalePath($locale);
		
		// Search: Category
		if (Str::contains(Route::currentRouteAction(), 'Search\CategoryController@index')) {
			$parentCatSlug = (isset($attributes['catSlug']) && !empty($attributes['catSlug'])) ? $attributes['catSlug'] : null;
			$catSlug = (isset($attributes['subCatSlug']) && !empty($attributes['subCatSlug'])) ? $attributes['subCatSlug'] : null;
			if (empty($catSlug) && !empty($parentCatSlug)) {
				$catSlug = $parentCatSlug;
				$parentCatSlug = null;
			}
			
			// Get category or sub-category translation
			if (!empty($catSlug)) {
				// Get Category
				$cat = self::getCategoryBySlug($locale, $catSlug, $parentCatSlug);
				if (!empty($cat)) {
					if (isset($cat->parentClosure) && !empty($cat->parentClosure)) {
						// Get the Route Path
						$routePath = trans('routes.v-search-subCat', [
							'countryCode' => $countryCode,
							'catSlug'     => $cat->parentClosure->slug,
							'subCatSlug'  => $cat->slug,
						], $locale);
					} else {
						// Get the Route Path
						$routePath = trans('routes.v-search-cat', [
							'countryCode' => $countryCode,
							'catSlug'     => $cat->slug,
						], $locale);
					}
					
					$url = app('url')->to($localePath . $routePath) . $queryString;
				}
			}
		} // Search: Location - Laravel Routing doesn't support PHP rawurlencode() function
		else if (Str::contains(Route::currentRouteAction(), 'Search\CityController@index')) {
			// Get the Route Path
			if (isset($attributes['city'])) {
				$routePath = trans('routes.v-search-city', [
					'countryCode' => $countryCode,
					'city'        => $attributes['city'],
					'id'          => $attributes['id'],
				], $locale);
				
				$url = app('url')->to($localePath . $routePath) . $queryString;
			}
		} // Search: User
		else if (Str::contains(Route::currentRouteAction(), 'Search\UserController@index')) {
			// Get the Route Path
			if (isset($attributes['id'])) {
				$routePath = trans('routes.v-search-user', [
					'countryCode' => $countryCode,
					'id'          => $attributes['id'],
				], $locale);
				
				$url = app('url')->to($localePath . $routePath) . $queryString;
			}
			if (isset($attributes['username'])) {
				$routePath = trans('routes.v-search-username', [
					'countryCode' => $countryCode,
					'username'    => $attributes['username'],
				], $locale);
				
				$url = app('url')->to($localePath . $routePath) . $queryString;
			}
		} // Search: Tag
		else if (Str::contains(Route::currentRouteAction(), 'Search\TagController@index')) {
			// Get the Route Path
			if (isset($attributes['tag'])) {
				$routePath = trans('routes.v-search-tag', [
					'countryCode' => $countryCode,
					'tag'          => $attributes['tag'],
				], $locale);
				
				$url = app('url')->to($localePath . $routePath) . $queryString;
			}
		} // Search: Company
		else if (Str::contains(Route::currentRouteAction(), 'Search\CompanyController@profile')) {
			// Get the Route Path
			if (isset($attributes['id'])) {
				$routePath = trans('routes.v-search-company', [
					'countryCode' => $countryCode,
					'id'          => $attributes['id'],
				], $locale);
				
				$url = app('url')->to($localePath . $routePath) . $queryString;
			}
		} // Search: Company (Static)
		else if (Str::contains(Route::currentRouteAction(), 'Search\CompanyController@index')) {
			// Get the Route Path
			$routePath = trans('routes.v-companies-list', [
				'countryCode' => $countryCode,
			], $locale);
			
			$url = app('url')->to($localePath . $routePath) . $queryString;
		} // Pages
		else if (Str::contains(Route::currentRouteAction(), 'PageController@cms')) {
			if (isset($attributes['slug'])) {
				$page = self::getPageBySlug($locale, $attributes['slug']);
				if (!empty($page)) {
					// Get the Route Path
					$routePath = trans('routes.v-page', ['slug' => $page->slug], $locale);
					$url = app('url')->to($localePath . $routePath) . $queryString;
				}
			}
		} // Search: Index
		else if (Str::contains(Route::currentRouteAction(), 'Search\SearchController@index')) {
			// Get the Route Path
			$routePath = trans('routes.v-search', ['countryCode' => $countryCode], $locale);
			
			$url = app('url')->to($localePath . $routePath) . $queryString;
		} else {
			$url = null;
			
			if (!currentLocaleShouldBeHiddenInUrl($locale)) {
				// request()->route() return null on 404 page
				$requestRoute = request()->route();
				if (!is_null($requestRoute)) {
					$pattern = '#/' . $requestRoute->getPrefix() . '#ui';
					$routePath = preg_replace($pattern, '', request()->getPathInfo(), 1);
					$routePath = ltrim($routePath, '/');
					$url = app('url')->to($localePath . $routePath) . $queryString;
				}
			}
		}
		
		return $url;
	}
	
	
	/**
	 * Get URL through entered Route (Or through entered URL)
	 *
	 * @param null $locale
	 * @param null $url
	 * @param array $attributes
	 * @return mixed|null|string
	 */
	public function getUrlThroughEnteredRoute($locale = null, $url = null, $attributes = [])
	{
		if (empty($locale)) {
			$locale = $this->getCurrentLocale();
		}
		
		// Don't capture RAW urls
		if (Str::contains($url, '{')) {
			return $url;
		}
		
		// Get the Query String
		$queryString = '';
		$parts = mb_parse_url($url);
		if (isset($parts['query'])) {
			$queryString = '?' . (is_array($parts['query']) || is_object($parts['query'])) ? httpBuildQuery($parts['query']) : $parts['query'];
		}
		
		// Get the Country Code
		$countryCode = $this->getCountryCode($attributes);
		
		// Get the Locale Path
		$localePath = $this->getLocalePath($locale);
		
		// Work with URL Path (without URL Protocol & Host)
		$url = $this->getUrlPath($url, $locale);
		
		// Search: Category
		if (
			Str::contains($url, trans('routes.t-search-cat', [], $locale))
			&& isset($attributes['catSlug'])
		) {
			$parentCatSlug = (isset($attributes['catSlug']) && !empty($attributes['catSlug'])) ? $attributes['catSlug'] : null;
			$catSlug = (isset($attributes['subCatSlug']) && !empty($attributes['subCatSlug'])) ? $attributes['subCatSlug'] : null;
			if (empty($catSlug) && !empty($parentCatSlug)) {
				$catSlug = $parentCatSlug;
				$parentCatSlug = null;
			}
			
			// Get category or sub-category translation
			if (!empty($catSlug)) {
				// Get Category
				$cat = self::getCategoryBySlug($locale, $catSlug, $parentCatSlug);
				if (!empty($cat)) {
					if (isset($cat->parentClosure) && !empty($cat->parentClosure)) {
						// Get the Route Path
						$routePath = trans('routes.v-search-subCat', [
							'countryCode' => $countryCode,
							'catSlug'     => $cat->parentClosure->slug,
							'subCatSlug'  => $cat->slug,
						], $locale);
					} else {
						// Get the Route Path
						$routePath = trans('routes.v-search-cat', [
							'countryCode' => $countryCode,
							'catSlug'     => $cat->slug,
						], $locale);
					}
					
					$url = app('url')->to($localePath . $routePath) . $queryString;
				}
			}
		} // Search: Location - Laravel Routing don't support PHP rawurlencode() function
		else if (
			Str::contains($url, trans('routes.t-search-city', [], $locale))
			&& isset($attributes['city'])
			&& isset($attributes['id'])
		) {
			$routePath = trans('routes.v-search-city', [
				'countryCode' => $countryCode,
				'city'        => $attributes['city'],
				'id'          => $attributes['id'],
			], $locale);
			
			$url = app('url')->to($localePath . $routePath) . $queryString;
		} // Search: User (by ID)
		else if (
			Str::contains($url, trans('routes.t-search-user', [], $locale))
			&& isset($attributes['id'])
			&& isset($attributes['username'])
		) {
			$routePath = trans('routes.v-search-user', [
				'countryCode' => $countryCode,
				'id'          => $attributes['id'],
			], $locale);
			
			$url = app('url')->to($localePath . $routePath) . $queryString;
		} // Search: User (by Username)
		else if (
			Str::contains($url, trans('routes.t-search-username', [], $locale))
			&& isset($attributes['id'])
			&& isset($attributes['username'])
		) {
			$routePath = trans('routes.v-search-username', [
				'countryCode' => $countryCode,
				'username'    => $attributes['username'],
			], $locale);
			
			$url = app('url')->to($localePath . $routePath) . $queryString;
		} // Search: Company
		else if (
			Str::contains($url, trans('routes.t-search-company', [], $locale))
			&& isset($attributes['id'])
		) {
			$routePath = trans('routes.v-search-company', [
				'countryCode' => $countryCode,
				'id'          => $attributes['id'],
			], $locale);
			
			$url = app('url')->to($localePath . $routePath) . $queryString;
		} // Pages
		else if (
			Str::contains($url, trans('routes.page', [], $locale))
			&& isset($attributes['slug'])
		) {
			$page = self::getPageBySlug($locale, $attributes['slug']);
			if (!empty($page)) {
				$routePath = trans('routes.v-page', ['slug' => $page->slug], $locale);
				
				$url = app('url')->to($localePath . $routePath) . $queryString;
			}
			
		} // Search: Index
		else if (
			Str::contains($url, trans('routes.t-search', [], $locale))
			&& !Str::contains($url, trans('routes.t-search-cat', [], $locale))
			&& !preg_match('/.*' . trans('routes.t-search', [], $locale) . '.+/ui', $url)
			&& !preg_match('/.+' . trans('routes.t-search', [], $locale) . '.*/ui', $url)
		) {
			$routePath = trans('routes.v-search', ['countryCode' => $countryCode], $locale);
			
			$url = app('url')->to($localePath . $routePath) . $queryString;
		} else {
			$url = '###' . $url . '###';
		}
		
		return $url;
	}
	
	/**
	 * Get the Locale Path (i.e. Language Path)
	 *
	 * @param null $locale
	 * @return string
	 */
	public function getLocalePath($locale = null)
	{
		if (empty($locale)) {
			$locale = $this->getCurrentLocale();
		}
		
		$path = '';
		if (!currentLocaleShouldBeHiddenInUrl($locale)) {
			$path = $locale . '/';
		}
		
		return $path;
	}
	
	/**
	 * Get the URL Path (without URL Protocol & Host)
	 *
	 * @param $url
	 * @param null $locale
	 * @return mixed
	 */
	public function getUrlPath($url, $locale = null)
	{
		// Get Locale path
		$localePath = $this->getLocalePath($locale);
		
		if (Str::contains($url, 'http://') || Str::contains($url, 'https://')) {
			$basePath = '/' . $localePath;
			$baseUrl = url('/') . preg_replace('#/+#ui', '/', $basePath);
			$url = str_replace($baseUrl, '', $url);
		}
		
		return $url;
	}
	
	/**
	 * Get the Country Code
	 *
	 * @param array $attributes
	 * @return mixed|null|string
	 */
	public function getCountryCode($attributes = [])
	{
		$countryCode = null;
		
		// Get the default Country
		// NOTE: The current method is generally called from views links, so all the settings are already set.
		$countryCode = strtolower(config('country.code'));
		
		// Get the Country
		if (empty($countryCode)) {
			if (isset($attributes['countryCode']) && !empty($attributes['countryCode'])) {
				$countryCode = $attributes['countryCode'];
			}
		}
		if (empty($countryCode)) {
			if (request()->filled('d')) {
				$countryCode = strtolower(request()->input('d'));
			}
		}
		
		return $countryCode;
	}
	
	/**
	 * Get Category by Slug
	 *
	 * @param $locale
	 * @param $catSlug
	 * @param null $parentCatSlug
	 * @return mixed|null
	 */
	public function getCategoryBySlug($locale, $catSlug, $parentCatSlug = null)
	{
		$cat = null;
		
		if ($locale == '' || $catSlug == '') {
			return $cat;
		}
		
		// Get Category (in default language)
		if (!empty($parentCatSlug)) {
			$cacheId = 'getCategoryBySlug.' . $parentCatSlug . '.' . $catSlug . '.' . config('app.locale');
			$catInDefaultLang = Cache::remember($cacheId, self::$cacheExpiration, function () use ($parentCatSlug, $catSlug) {
				$catInDefaultLang = Category::transIn(config('app.locale'))
					->whereHas('parent', function ($query) use ($parentCatSlug) {
						$query->where('slug', '=', $parentCatSlug);
					})->where('slug', '=', $catSlug)->first();
				
				return $catInDefaultLang;
			});
		} else {
			$cacheId = 'getCategoryBySlug.' . $catSlug . '.' . config('app.locale');
			$catInDefaultLang = Cache::remember($cacheId, self::$cacheExpiration, function () use ($parentCatSlug, $catSlug) {
				$catInDefaultLang = Category::transIn(config('app.locale'))->where('slug', '=', $catSlug)->first();
				
				return $catInDefaultLang;
			});
		}
		
		// Get Category in the selected language
		if (!empty($catInDefaultLang)) {
			$cacheId = 'getCategoryById.' . $catInDefaultLang->tid . '.' . $locale;
			$cat = Cache::remember($cacheId, self::$cacheExpiration, function () use ($locale, $catInDefaultLang) {
				$cat = Category::query();
				
				if ($catInDefaultLang->parent) {
					$cat = $cat->with(['parentClosure' => function ($query) use ($locale, $catInDefaultLang) {
						$query->where('translation_lang', $locale)
							->where('translation_of', $catInDefaultLang->parent->tid);
					}]);
				}
				
				$cat = $cat->where('translation_lang', $locale)
					->where('translation_of', $catInDefaultLang->tid)
					->first();
				
				return $cat;
			});
		}
		
		return $cat;
	}
	
	/**
	 * Get Page by Slug
	 *
	 * @param $locale
	 * @param $slug
	 * @return mixed|null
	 */
	public static function getPageBySlug($locale, $slug)
	{
		$page = null;
		
		if ($locale == '' || $slug == '') {
			return $page;
		}
		
		// Get Page (in default language)
		$cacheId = 'getPageBySlug.' . config('app.locale') . '.' . $slug;
		$pageInDefaultLang = Cache::remember($cacheId, self::$cacheExpiration, function () use ($slug) {
			return Page::transIn(config('app.locale'))->where('slug', '=', $slug)->first();
		});
		
		// Get Page in the selected language
		if (!empty($pageInDefaultLang)) {
			$cacheId = 'getPageById.' . $pageInDefaultLang->tid . '.' . $locale;
			$page = Cache::remember($cacheId, self::$cacheExpiration, function () use ($pageInDefaultLang, $locale) {
				return Page::findTrans($pageInDefaultLang->tid, $locale);
			});
		}
		
		return $page;
	}
	
	/**
	 * Don't translate these path or folders
	 *
	 * @return bool
	 */
	public function exceptRedirectionPath()
	{
		// Use url() for this paths
		if (in_array(request()->segment(1), [
			'_debugbar',
			'assets',
			'css',
			'js',
			'pic',
			'ajax',
			'api',
			'script',
			'tools',
			'images',
			admin_uri(),
			'api',
		])) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Sub-folder support
	 *
	 * @param $parsedUrl
	 * @param $url
	 * @return \Illuminate\Contracts\Routing\UrlGenerator|mixed|null|string|string[]
	 */
	public function extendedUnparseUrl($parsedUrl, $url)
	{
		if (isset($parsedUrl['path'])) {
			$homeUrlParsed = parse_url(url('/'));
			if (isset($homeUrlParsed['path'])) {
				$homeUrlParsed['path'] = ltrim($homeUrlParsed['path'], '/');
				if (!isset($parsedUrl['scheme'])) {
					if ($homeUrlParsed['path'] != $parsedUrl['path']) {
						$url = str_replace($homeUrlParsed['path'], '', $url);
						$url = preg_replace('#/+#', '/', $url);
						$url = url($url);
					}
				}
			}
		}
		
		return $url;
	}
}
