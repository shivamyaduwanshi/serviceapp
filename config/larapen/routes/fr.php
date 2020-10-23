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

$routesTab = [
    /*
    |--------------------------------------------------------------------------
    | Routes Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used by the global website.
    |
    */

    // Countries
    'countries' => 'pays',

	// Auth
    'login'    => 'login',
    'logout'   => 'logout',
    'register' => 'register',
	
	// Post
	'post'   => '{slug}/{id}',
	'v-post' => ':slug/:id',

	// Page
    'page'   => 'page/{slug}',
    't-page' => 'page',
    'v-page' => 'page/:slug',

	// Pricing
	'pricing' => 'tarifs',

	// Contact
    'contact' => 'contact',
];

if (config('settings.seo.multi_countries_urls')) {
    // Sitemap
    $routesTab['sitemap'] = '{countryCode}/sitemap';
    $routesTab['v-sitemap'] = ':countryCode/sitemap';

    // Latest Ads
    $routesTab['search'] = '{countryCode}/offres';
    $routesTab['t-search'] = 'offres';
    $routesTab['v-search'] = ':countryCode/offres';

    // Search by Sub-Category
    $routesTab['search-subCat'] = '{countryCode}/categorie/{catSlug}/{subCatSlug}';
    $routesTab['t-search-subCat'] = 'categorie';
    $routesTab['v-search-subCat'] = ':countryCode/categorie/:catSlug/:subCatSlug';

    // Search by Category
    $routesTab['search-cat'] = '{countryCode}/categorie/{catSlug}';
    $routesTab['t-search-cat'] = 'categorie';
    $routesTab['v-search-cat'] = ':countryCode/categorie/:catSlug';

    // Search by Location
    $routesTab['search-city'] = '{countryCode}/bonnes-affaires/{city}/{id}';
    $routesTab['t-search-city'] = 'bonnes-affaires';
    $routesTab['v-search-city'] = ':countryCode/bonnes-affaires/:city/:id';

    // Search by User
    $routesTab['search-user'] = '{countryCode}/users/{id}/ads';
    $routesTab['t-search-user'] = 'users';
    $routesTab['v-search-user'] = ':countryCode/users/:id/ads';

    // Search by Username
    $routesTab['search-username'] = '{countryCode}/profile/{username}';
	$routesTab['t-search-username'] = 'profile';
    $routesTab['v-search-username'] = ':countryCode/profile/:username';
	
	// Search by Tag
	$routesTab['search-tag'] = '{countryCode}/tag/{tag}';
	$routesTab['t-search-tag'] = 'tag';
	$routesTab['v-search-tag'] = ':countryCode/tag/:tag';
} else {
    // Sitemap
    $routesTab['sitemap'] = 'sitemap';
    $routesTab['v-sitemap'] = 'sitemap';

    // Latest Ads
    $routesTab['search'] = 'offres';
    $routesTab['t-search'] = 'offres';
    $routesTab['v-search'] = 'offres';

    // Search by Sub-Category
    $routesTab['search-subCat'] = 'categorie/{catSlug}/{subCatSlug}';
    $routesTab['t-search-subCat'] = 'categorie';
    $routesTab['v-search-subCat'] = 'categorie/:catSlug/:subCatSlug';

    // Search by Category
    $routesTab['search-cat'] = 'categorie/{catSlug}';
    $routesTab['t-search-cat'] = 'categorie';
    $routesTab['v-search-cat'] = 'categorie/:catSlug';

    // Search by Location
    $routesTab['search-city'] = 'bonnes-affaires/{city}/{id}';
    $routesTab['t-search-city'] = 'bonnes-affaires';
    $routesTab['v-search-city'] = 'bonnes-affaires/:city/:id';

    // Search by User
    $routesTab['search-user'] = 'users/{id}/ads';
    $routesTab['t-search-user'] = 'users';
    $routesTab['v-search-user'] = 'users/:id/ads';
	
	// Search by Username
    $routesTab['search-username'] = 'profile/{username}';
	$routesTab['t-search-username'] = 'profile';
    $routesTab['v-search-username'] = 'profile/:username';
	
	// Search by Tag
	$routesTab['search-tag'] = 'tag/{tag}';
	$routesTab['t-search-tag'] = 'tag';
	$routesTab['v-search-tag'] = 'tag/:tag';
}

// Posts Permalink Collection
$vPost = config('larapen.core.permalink.posts');

// Posts Permalink
if (isset($vPost[config('settings.seo.posts_permalink', '{slug}/{id}')])) {
	$routesTab['post'] = config('settings.seo.posts_permalink', '{slug}/{id}') . config('settings.seo.posts_permalink_ext', '');
	$routesTab['v-post'] = $vPost[config('settings.seo.posts_permalink', '{slug}/{id}')] . config('settings.seo.posts_permalink_ext', '');
}

return $routesTab;
