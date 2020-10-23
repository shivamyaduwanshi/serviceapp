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

namespace App\Helpers\Search;

use App\Helpers\ArrayHelper;
use App\Helpers\DBTool;
use App\Helpers\Number;
use App\Models\Category;
use App\Models\Field;
use App\Models\PostType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Larapen\LaravelDistance\Distance;

class RawQueries
{
	protected static $cacheExpiration = 300; // 5mn (60s * 5)
	
	public $country;
	public $lang;
	public static $queryLength = 1;   // Minimum query characters
	public static $distance = 100;    // km
	public static $maxDistance = 500; // km
	public $perPage = 12;
	public $currentPage = 0;
	protected $sqlCurrLimit;
	protected $table = 'posts';
	protected $searchable = [
		'columns' => [
			'tPost.title'       => 10,
			'tPost.description' => 10,
			'tPost.tags'        => 8,
			'lCategory.name'    => 5,
			'lParent.name'      => 2, // Category Parent
		],
	];
	protected $forceAverage = true; // Force relevance's average
	protected $average = 1;         // Set relevance's average
	
	// Pre-Search vars
	public $cat = null;
	public $city = null;
	public $admin = null;
	
	// Ban this words in query search
	// protected $banWords = ['sell', 'buy', 'vendre', 'vente', 'achat', 'acheter', 'ses', 'sur', 'de', 'la', 'le', 'les', 'des', 'pour', 'latest'];
	protected $banWords = [];
	
	// SQL statements building vars
	protected $arrSql = [
		'select'  => [
			'tPost.id',
			'tPost.country_code',
			'tPost.category_id',
			'tPost.post_type_id',
			'tPost.title',
			'tPost.price',
			'tPost.city_id',
			'tPost.featured',
			'tPost.created_at',
			'tPost.reviewed',
			'tPost.verified_email',
			'tPost.verified_phone',
		],
		'join'    => [],
		'where'   => [],
		'groupBy' => [
			'tPost.id',
		],
		'having'  => [],
		'orderBy' => [],
	];
	protected $bindings = [];
	
	// Non-primary request parameters
	protected $filterParametersFields = [
		'type'       => 'tPost.post_type_id',
		'minPrice'   => 'calculatedPrice', // 'tPost.price',
		'maxPrice'   => 'calculatedPrice', // 'tPost.price',
		'postedDate' => 'tPost.created_at',
		'cf'         => '@dummy',
	];
	// OrderBy request parameters
	protected $orderByParametersFields = [
		'priceAsc'  => ['name' => 'tPost.price', 'order' => 'ASC'],
		'priceDesc' => ['name' => 'tPost.price', 'order' => 'DESC'],
		'relevance' => ['name' => 'relevance', 'order' => 'DESC'],
		'date'      => ['name' => 'tPost.created_at', 'order' => 'DESC'],
	];
	
	/**
	 * RawQueries constructor.
	 *
	 * @param array $preSearch
	 */
	public function __construct($preSearch = [])
	{
		// Pre-Search
		if (isset($preSearch['cat']) && !empty($preSearch['cat'])) {
			$this->cat = $preSearch['cat'];
		}
		if (isset($preSearch['city']) && !empty($preSearch['city'])) {
			$this->city = $preSearch['city'];
		}
		if (isset($preSearch['admin']) && !empty($preSearch['admin'])) {
			$this->admin = $preSearch['admin'];
		}
		
		// Distance (Max & Default distance)
		self::$maxDistance = config('settings.listing.search_distance_max', 0);
		self::$distance = config('settings.listing.search_distance_default', 0);
		
		// Posts per page
		$this->perPage = (is_numeric(config('settings.listing.items_per_page'))) ? config('settings.listing.items_per_page') : $this->perPage;
		if ($this->perPage < 4) $this->perPage = 4;
		if ($this->perPage > 40) $this->perPage = 40;
		
		// Init.
		$this->arrSql = ArrayHelper::toObject($this->arrSql, 2);
		// If the MySQL strict mode is activated, ...
		// Append all the non-calculated fields available in the 'SELECT' in 'GROUP BY' to prevent error related to 'only_full_group_by'
		if (env('DB_MODE_STRICT')) {
			$this->arrSql->groupBy = $this->arrSql->select;
		}
		array_push($this->banWords, strtolower(config('country.name')));
		if (config('plugins.reviews.installed')) {
			$this->orderByParametersFields['rating'] = ['name' => 'tPost.rating_cache', 'order' => 'DESC'];
		}
		
		// Price conversion (For the Currency Exchange plugin)
		$this->arrSql->select[] = "(tPost.price * " . config('selectedCurrency.rate', 1) . ") AS calculatedPrice";
		
		// Post category relation
		$this->arrSql->join[] = "INNER JOIN " . DBTool::table('categories') . " AS tCategory ON tCategory.id=tPost.category_id AND tCategory.active=1";
		$this->arrSql->join[] = "LEFT JOIN " . DBTool::table('categories') . " AS tParent ON tParent.id=tCategory.parent_id AND tParent.active=1";
		
		// Categories translation relation
		$this->arrSql->join[] = "LEFT JOIN " . DBTool::table('categories')
			. " AS lCategory ON lCategory.translation_of=tCategory.id AND lCategory.translation_lang = :translationLang";
		$this->arrSql->join[] = "LEFT JOIN " . DBTool::table('categories')
			. " AS lParent ON lParent.translation_of=lCategory.id AND lParent.translation_lang = :translationLang";
		
		$this->bindings['translationLang'] = config('lang.abbr');
		
		// Post payment relation
		$this->arrSql->select[] = "tPayment.package_id, tPackage.lft";
		
		$latestPayment = "(SELECT MAX(id) lid, post_id FROM " . DBTool::table('payments') . " WHERE active=1 GROUP BY post_id) latestPayment";
		
		$this->arrSql->join[] = "LEFT JOIN " . $latestPayment . " ON latestPayment.post_id=tPost.id AND tPost.featured=1";
		$this->arrSql->join[] = "LEFT JOIN " . DBTool::table('payments') . " AS tPayment ON tPayment.id=latestPayment.lid";
		$this->arrSql->join[] = "LEFT JOIN " . DBTool::table('packages') . " AS tPackage ON tPackage.id=tPayment.package_id";
		
		$this->arrSql->groupBy[] = "tPayment.package_id, tPackage.lft";
		
		// Default filters
		$this->arrSql->where = [
			"tPost.country_code = :countryCode",
			"(tPost.verified_email = 1 AND tPost.verified_phone = 1)",
			"tPost.archived != 1",
			"tPost.deleted_at IS NULL",
		];
		
		$this->bindings['countryCode'] = config('country.code');
		
		// Check reviewed posts
		if (config('settings.single.posts_review_activation')) {
			$this->arrSql->where[] = "tPost.reviewed = 1";
		}
		
		// Priority settings
		if (request()->filled('distance') && is_numeric(request()->get('distance'))) {
			self::$distance = request()->get('distance');
			if (request()->get('distance') > self::$maxDistance) {
				self::$distance = self::$maxDistance;
			}
		} else {
			// Create the 'distance' parameter in the request()
			if (config('settings.listing.cities_extended_searches')) {
				request()->merge(['distance' => self::$distance]);
			}
		}
		if (request()->filled('orderBy')) {
			$this->setOrder(request()->get('orderBy'));
		}
		
		// Pagination Init.
		$this->currentPage = (request()->get('page') < 0) ? 0 : (int)request()->get('page');
		$page = (request()->get('page') <= 1) ? 1 : (int)request()->get('page');
		$this->sqlCurrLimit = ($page <= 1) ? 0 : $this->perPage * ($page - 1);
		
		// If Ad Type is filled, then check if the Ad Type exists
		if (request()->filled('type')) {
			if (!$this->checkIfPostTypeExists(request()->get('type'))) {
				abort(404, t('The requested ad type does not exist'));
			}
		}
	}
	
	/**
	 * Check if PostType exist(s)
	 *
	 * @param $postTypeId
	 * @return bool
	 */
	private function checkIfPostTypeExists($postTypeId)
	{
		$found = false;
		
		// If Ad Type is filled, then check if the Ad Type exists
		if (!empty($postTypeId)) {
			$cacheId = 'search.postType.' . $postTypeId . '.' . config('app.locale');
			$postType = Cache::remember($cacheId, self::$cacheExpiration, function () use ($postTypeId) {
				return PostType::query()
					->where('translation_of', $postTypeId)
					->where('translation_lang', config('app.locale'))
					->first(['id']);
			});
			
			if (!empty($postType)) {
				$found = true;
			}
		} else {
			$found = true;
		}
		
		return $found;
	}
	
	/**
	 * Get the results
	 *
	 * @return array
	 */
	public function fetch()
	{
		// Apply primary filters
		$this->setPrimaryFilters();
		
		// Check & Set other requests filters
		$this->setNonPrimaryFilters();
		
		// Get the SQL statements
		$sql = $this->getSqlStatements();
		
		// Count the results
		$count = $this->countFetch($sql);
		
		// Get the paginated SQL statements
		$sql = $sql . "\n" . "LIMIT " . (int)$this->sqlCurrLimit . ", " . (int)$this->perPage;
		
		// Execute the SQL query
		$posts = self::execute($sql, $this->bindings);
		
		// Count real query posts
		if (request()->filled('type') && !empty(request()->get('type'))) {
			$total = ($count->has(request()->get('type'))) ? $count->get(request()->get('type')) : 0;
		} else {
			$total = $count->get('all');
		}
		
		// Paginate
		$posts = new LengthAwarePaginator($posts, $total, $this->perPage, $this->currentPage);
		$posts->setPath(request()->url());
		
		// Transform the collection attributes
		$posts->getCollection()->transform(function ($post) {
			$post->title = mb_ucfirst($post->title);
			
			return $post;
		});
		
		// Clear request keys
		$this->clearRequestKeys();
		
		return ['paginator' => $posts, 'count' => $count];
	}
	
	/**
	 * Count the results
	 *
	 * @param $sql
	 * @return \Illuminate\Support\Collection
	 */
	private function countFetch($sql)
	{
		// Get global where clause
		$where = $wherePostType = $this->arrSql->where;
		
		// Remove the type with her SQL clause
		if (request()->filled('type')) {
			// Remove the 'post_type_id' filter in the WHERE statement
			$where = collect($where)->filter(function ($item, $key) {
				return !Str::contains($item, 'tPost.post_type_id');
			})->toArray();
			
			$sql = $this->getSqlStatements($where);
		}
		
		// Count all entries
		$sql = "SELECT COUNT(*) AS total FROM (" . $sql . ") AS x";
		$all = self::execute($sql, $this->bindings);
		
		$count['all'] = (isset($all[0])) ? $all[0]->total : 0;
		
		// Get the Post's Types
		$postTypes = PostType::where('translation_lang', config('lang.abbr'))->orderBy('name')->get();
		
		// Count entries by post type
		if ($postTypes->count() > 0) {
			foreach ($postTypes as $postType) {
				// Remove the 'post_type_id' filter in the WHERE statement
				$wherePostType = collect($wherePostType)->filter(function ($item, $key) {
					return !Str::contains($item, 'tPost.post_type_id');
				})->toArray();
				
				// Apply the current 'post_type_id' filter
				$wherePostType[] = 'tPost.post_type_id = ' . $postType->tid;
				
				// Count entries by 'post_type_id'
				$sqlPostType = "SELECT COUNT(*) AS total FROM (" . $this->getSqlStatements($wherePostType) . ") AS x";
				$allByPostType = self::execute($sqlPostType, $this->bindings);
				
				$count[$postType->tid] = (isset($allByPostType[0])) ? $allByPostType[0]->total : 0;
			}
		}
		
		return collect($count);
	}
	
	/**
	 * Execute the SQL
	 *
	 * @param $sql
	 * @param array $bindings
	 * @return mixed
	 */
	private static function execute($sql, $bindings = [])
	{
		// DEBUG
		// echo 'SQL<hr><pre>' . $sql . '</pre><hr>'; // exit();
		// echo 'BINDINGS<hr><pre>'; print_r($bindings); echo '</pre><hr>'; // exit();
		
		try {
			$result = DB::select(DB::raw($sql), $bindings);
		} catch (\Exception $e) {
			$result = null;
			
			// DEBUG
			// dd($e->getMessage());
		}
		
		return $result;
	}
	
	/**
	 * Get the SQL statements
	 *
	 * @param array $arrWhere
	 * @return string
	 */
	private function getSqlStatements($arrWhere = [])
	{
		// Set SELECT
		$select = 'SELECT DISTINCT ' . implode(', ', $this->arrSql->select);
		
		// Set JOIN
		$join = '';
		if (count($this->arrSql->join) > 0) {
			$join = "\n" . implode("\n", $this->arrSql->join);
		}
		
		// Set WHERE
		$arrWhere = ((count($arrWhere) > 0) ? $arrWhere : $this->arrSql->where);
		$where = '';
		if (count($arrWhere) > 0) {
			foreach ($arrWhere as $value) {
				if (trim($value) == '') {
					continue;
				}
				if ($where == '') {
					$where .= "\n" . 'WHERE ' . $value;
				} else {
					$where .= ' AND ' . $value;
				}
			}
		}
		
		// Set GROUP BY
		$groupBy = '';
		if (count($this->arrSql->groupBy) > 0) {
			$groupBy = "\n" . 'GROUP BY ' . implode(', ', $this->arrSql->groupBy);
		}
		
		// Set HAVING
		$having = '';
		if (count($this->arrSql->having) > 0) {
			foreach ($this->arrSql->having as $key => $value) {
				if (trim($value) == '') {
					continue;
				}
				if ($having == '') {
					$having .= "\n" . 'HAVING ' . $value;
				} else {
					$having .= ' AND ' . $value;
				}
			}
		}
		
		// Set ORDER BY
		$orderBy = '';
		$orderBy .= "\n" . 'ORDER BY tPackage.lft DESC';
		if (count($this->arrSql->orderBy) > 0) {
			foreach ($this->arrSql->orderBy as $key => $value) {
				if (trim($value) == '') {
					continue;
				}
				if ($orderBy == '') {
					$orderBy .= "\n" . 'ORDER BY ' . $value;
				} else {
					$orderBy .= ', ' . $value;
				}
			}
		}
		
		if (count($this->arrSql->orderBy) > 0) {
			// Check if the 'created_at' column is already apply for orderBy
			$orderByCreatedAtFound = collect($this->arrSql->orderBy)->contains(function ($value, $key) {
				return Str::contains($value, 'tPost.created_at');
			});
			
			// Apply the 'tPost.created_at' column for orderBy
			if (!$orderByCreatedAtFound) {
				$orderBy .= ', tPost.created_at DESC';
			}
		} else {
			if ($orderBy == '') {
				$orderBy .= "\n" . 'ORDER BY tPost.created_at DESC';
			} else {
				$orderBy .= ', tPost.created_at DESC';
			}
		}
		
		// Get Query
		$sql = $select . "\n" . "FROM " . DBTool::table($this->table) . " AS tPost" . $join . $where . $groupBy . $having . $orderBy;
		
		return $sql;
	}
	
	/**
	 * Apply primary filters
	 */
	public function setPrimaryFilters()
	{
		// Check & Set keyword filter
		if (request()->filled('q')) {
			$this->setKeywords(request()->get('q'));
		}
		
		// Check & Set category filter
		if (!empty($this->cat)) {
			$this->setCategory($this->cat);
		}
		
		// Check & Set location filter
		if (Str::contains(Route::currentRouteAction(), 'Search\CityController')) {
			if (!empty($this->city)) {
				$this->setLocationByCity($this->city);
			}
		} else {
			if (!empty($this->admin) && request()->filled('r') && !request()->filled('l')) {
				$this->setLocationByAdminCode($this->admin->code);
			}
			if (!empty($this->city) && request()->has('l')) {
				$this->setLocationByCity($this->city);
			}
		}
	}
	
	/**
	 * Apply keyword filter
	 *
	 * @param $keywords
	 * @return bool
	 */
	public function setKeywords($keywords)
	{
		if (trim($keywords) == '') {
			return false;
		}
		
		// Query search SELECT array
		$select = [];
		
		// Get all keywords in array
		$words_tab = preg_split('/[\s,\+]+/', $keywords);
		
		//-- If third parameter is set as true, it will check if the column starts with the search
		//-- if then it adds relevance * 30
		//-- this ensures that relevant results will be at top
		$select[] = "(CASE WHEN tPost.title LIKE :keywords THEN 300 ELSE 0 END) ";
		
		$this->bindings['keywords'] = $keywords . '%';
		
		
		foreach ($this->searchable['columns'] as $column => $relevance) {
			$tmp = [];
			foreach ($words_tab as $key => $word) {
				// Skip short keywords
				if (strlen($word) <= self::$queryLength) {
					continue;
				}
				// @todo: Find another way
				if (in_array(mb_strtolower($word), $this->banWords)) {
					continue;
				}
				$tmp[] = $column . " LIKE :word_" . $key;
				
				$this->bindings['word_' . $key] = '%' . $word . '%';
			}
			if (count($tmp) > 0) {
				$select[] = "(CASE WHEN " . implode(' || ', $tmp) . " THEN " . $relevance . " ELSE 0 END) ";
			}
		}
		if (count($select) <= 0) {
			return false;
		}
		
		$this->arrSql->select[] = "(" . implode("+\n", $select) . ") AS relevance";
		
		//-- Selects only the rows that have more than
		//-- the sum of all attributes relevances and divided by count of attributes
		//-- e.i. (20 + 5 + 2) / 4 = 6.75
		$average = array_sum($this->searchable['columns']) / count($this->searchable['columns']);
		$average = Number::toFloat($average);
		if ($this->forceAverage) {
			// Force average
			$average = $this->average;
		}
		$this->arrSql->having[] = 'relevance >= :average';
		$this->bindings['average'] = $average;
		
		//-- Group By
		$this->arrSql->groupBy[] = "relevance";
		
		//-- Orders the results by relevance
		$this->arrSql->orderBy[] = 'relevance DESC';
	}
	
	/**
	 * Apply category filter
	 *
	 * @param $cat
	 * @return $this
	 */
	public function setCategory($cat)
	{
		if (empty($cat) || !($cat instanceof Category)) {
			return $this;
		}
		
		$catChildrenIds = $this->getCategoryChildrenIds($cat, $cat->tid);
		
		// Category
		if (!empty($catChildrenIds)) {
			$this->arrSql->where[] = 'tPost.category_id IN (' . implode(',', $catChildrenIds) . ')';
		}
		
		return $this;
	}
	
	/**
	 * Get all the category's children IDs
	 *
	 * @param $cat
	 * @param null $catId
	 * @param array $idsArr
	 * @return array
	 */
	private function getCategoryChildrenIds($cat, $catId = null, &$idsArr = [])
	{
		if (!empty($catId)) {
			$idsArr[] = $catId;
		}
		
		if (isset($cat->children) && $cat->children->count() > 0) {
			$subIdsArr = [];
			foreach ($cat->children as $subCat) {
				if ($subCat->active != 1) {
					continue;
				}
				
				$idsArr[] = $subCat->tid;
				
				if (isset($subCat->children) && $subCat->children->count() > 0) {
					$subIdsArr = $this->getCategoryChildrenIds($subCat, null, $subIdsArr);
				}
			}
			$idsArr = array_merge($idsArr, $subIdsArr);
		}
		
		return $idsArr;
	}
	
	/**
	 * Apply user filter
	 *
	 * @param $userId
	 * @return $this
	 */
	public function setUser($userId)
	{
		if (trim($userId) == '') {
			return $this;
		}
		$this->arrSql->where[] = 'tPost.user_id = :userId';
		$this->bindings['userId'] = $userId;
		
		return $this;
	}
	
	/**
	 * Apply tag filter
	 *
	 * @param $tag
	 * @return $this
	 */
	public function setTag($tag)
	{
		if (trim($tag) == '') {
			return $this;
		}
		
		$tag = rawurldecode($tag);
		
		$this->arrSql->where[] = 'FIND_IN_SET(:tag, LOWER(tPost.tags)) > 0';
		$this->bindings['tag'] = mb_strtolower($tag);
		
		return $this;
	}
	
	/**
	 * Apply administrative division filter
	 * Search including Administrative Division by adminCode
	 *
	 * @param $adminCode
	 * @return $this
	 */
	public function setLocationByAdminCode($adminCode)
	{
		if (in_array(config('country.admin_type'), ['1', '2'])) {
			// Get the admin. division table info
			$adminType = config('country.admin_type');
			$adminTable = 'subadmin' . $adminType;
			$adminForeignKey = 'subadmin' . $adminType . '_code';
			
			// Query
			$this->arrSql->join[] = "INNER JOIN " . DBTool::table('cities') . " AS tCity ON tCity.id=tPost.city_id";
			$this->arrSql->join[] = "INNER JOIN " . DBTool::table($adminTable) . " AS tAdmin ON tAdmin.code=tCity." . $adminForeignKey;
			$this->arrSql->where[] = 'tAdmin.code = :adminCode';
			
			$this->bindings['adminCode'] = $adminCode;
			
			return $this;
		}
		
		return $this;
	}
	
	/**
	 * Apply city filter (Using city's coordinates)
	 * Search including City by City Coordinates (lat & lon)
	 *
	 * @param $city
	 * @return $this
	 */
	public function setLocationByCity($city)
	{
		if (!isset($city->id) || !isset($city->longitude) || !isset($city->latitude)) {
			return $this;
		}
		
		if ($city->longitude == 0 || $city->latitude == 0) {
			return $this;
		}
		
		// Set city globally
		$this->city = $city;
		
		// OrderBy priority for location
		$this->arrSql->orderBy[] = 'tPost.created_at DESC';
		
		// If extended search is disabled...
		// Use the Cities Standard Searches
		if (!config('settings.listing.cities_extended_searches')) {
			return $this->setLocationByCityId($city->id);
		}
		
		// Use the Cities Extended Searches
		config()->set('distance.functions.default', config('settings.listing.distance_calculation_formula'));
		config()->set('distance.countryCode', config('country.code'));
		
		$sql = Distance::select('tPost.lon', 'tPost.lat', ':longitude', ':latitude');
		if ($sql) {
			$this->arrSql->select[] = $sql;
			$this->arrSql->having[] = Distance::having(self::$distance);
			$this->arrSql->orderBy[] = Distance::orderBy('ASC');
			
			$this->bindings['longitude'] = $city->longitude;
			$this->bindings['latitude'] = $city->latitude;
		} else {
			return $this->setLocationByCityId($city->id);
		}
		
		return $this;
	}
	
	/**
	 * Apply city filter (Using city's Id)
	 * Search including City by City Id
	 *
	 * @param $cityId
	 * @return $this
	 */
	public function setLocationByCityId($cityId)
	{
		if (trim($cityId) == '') {
			return $this;
		}
		
		$this->arrSql->where[] = 'tPost.city_id = :cityId';
		$this->bindings['cityId'] = $cityId;
		
		return $this;
	}
	
	/**
	 * Apply non-primary filters
	 *
	 * @return $this
	 */
	public function setNonPrimaryFilters()
	{
		$parameters = request()->all();
		if (!is_array($parameters) || empty($parameters)) {
			return $this;
		}
		
		foreach ($parameters as $key => $value) {
			if (!isset($this->filterParametersFields[$key])) {
				continue;
			}
			if (!is_array($value) && trim($value) == '') {
				continue;
			}
			
			// Special parameters
			$specParams = [];
			if ($key == 'minPrice') { // Min. Price
				$this->arrSql->having[] = $this->filterParametersFields[$key] . ' >= ' . $value;
				$specParams[] = $key;
			}
			if ($key == 'maxPrice') { // Max. Price
				$this->arrSql->having[] = $this->filterParametersFields[$key] . ' <= ' . $value;
				$specParams[] = $key;
			}
			if ($key == 'postedDate') { // Date
				$this->arrSql->where[] = $this->filterParametersFields[$key] . ' BETWEEN DATE_SUB(NOW(), INTERVAL :postedDate DAY) AND NOW()';
				$this->bindings['postedDate'] = $value;
				$specParams[] = $key;
			}
			
			// Custom Fields
			if ($key == 'cf') {
				if (is_array($value)) {
					$aliasPrefix = 'pv'; // Alias prefix
					$bindings = [];
					foreach ($value as $fieldId => $postValue) {
						// Get Field object
						$field = Field::findTrans($fieldId);
						if (empty($field)) {
							continue;
						}
						
						if (is_array($postValue)) {
							// 'checkbox_multiple' field type
							foreach ($postValue as $optionId => $optionValue) {
								if (is_array($optionValue)) continue;
								if (!is_array($optionValue) && trim($optionValue) == '') continue;
								
								$fieldAndOptionIds = $field->id . $optionId;
								$alias = $aliasPrefix . (int)$fieldAndOptionIds; // (int) to prevent SQL injection attack
								$where = '('
									. $alias . '.field_id = :fieldId' . $fieldAndOptionIds
									. ' AND ' . $alias . '.option_id = :optionId' . $fieldAndOptionIds
									. ' AND ' . $alias . '.value LIKE :value' . $fieldAndOptionIds
									. ')';
								
								$this->arrSql->join[] = "INNER JOIN " . DBTool::table('post_values')
									. " AS " . $alias
									. " ON tPost.id=" . $alias . ".post_id"
									. " AND " . $where;
								
								$bindings['fieldId' . $fieldAndOptionIds] = $field->id;
								$bindings['optionId' . $fieldAndOptionIds] = $optionId;
								$bindings['value' . $fieldAndOptionIds] = $optionValue;
								
								$this->bindings += $bindings;
							}
						} else {
							// Other fields
							if (trim($postValue) == '') {
								continue;
							}
							
							// Date Value ('date', 'date_time')
							if (in_array($field->type, ['date', 'date_time'])) {
								$alias = $aliasPrefix . (int)$field->id; // (int) to prevent SQL injection attack
								$where = '(' . $alias . '.field_id = :fieldId' . $field->id . ' AND DATE(' . $alias . '.value) = :value' . $field->id . ')';
								
								$sql = "INNER JOIN " . DBTool::table('post_values')
									. " AS " . $alias
									. " ON tPost.id=" . $alias . ".post_id"
									. " AND " . $where;
								
								$postValue = date('Y-m-d', strtotime($postValue));
								
								$this->arrSql->join[] = $sql;
								$bindings['fieldId' . $field->id] = $field->id;
								$bindings['value' . $field->id] = $postValue;
								
								$this->bindings += $bindings;
							}
							
							// Dates Range Value ('date_range')
							if ($field->type == 'date_range') {
								/*
								 * Date Range Format: YYYY/MM/DD - ZZZZ/YY/XX
								 * SUBSTR(field, 1, 10) => YYYY/MM/DD
								 * SUBSTR(field, 14, 23) => ZZZZ/YY/XX
								 */
								$alias = $aliasPrefix . (int)$field->id; // (int) to prevent SQL injection attack
								$where = '('
									. $alias . '.field_id = :fieldId' . $field->id
									. ' AND DATE(SUBSTR(' . $alias . '.value, 1, 10)) >= :startDate' . $field->id
									. ' AND DATE(SUBSTR(' . $alias . '.value, 14, 23)) <= :endDate' . $field->id
									. ')';
								
								$sql = "INNER JOIN " . DBTool::table('post_values')
									. " AS " . $alias
									. " ON tPost.id=" . $alias . ".post_id"
									. " AND " . $where;
								
								$tmp = explode('-', $postValue);
								$tmp = array_map('trim', $tmp);
								
								if (!isset($tmp[0]) || !isset($tmp[1])) {
									continue;
								}
								
								$startDate = date('Y-m-d', strtotime($tmp[0]));
								$endDate = date('Y-m-d', strtotime($tmp[1]));
								
								$this->arrSql->join[] = $sql;
								$bindings['fieldId' . $field->id] = $field->id;
								$bindings['startDate' . $field->id] = $startDate;
								$bindings['endDate' . $field->id] = $endDate;
								
								$this->bindings += $bindings;
							}
							
							// Integer Value ('checkbox', 'select', 'radio', 'number')
							if (in_array($field->type, ['checkbox', 'select', 'radio', 'number'])) {
								$alias = $aliasPrefix . (int)$field->id; // (int) to prevent SQL injection attack
								$where = '(' . $alias . '.field_id = :fieldId' . $field->id . ' AND ' . $alias . '.value LIKE :value' . $field->id . ')';
								
								$sql = "INNER JOIN " . DBTool::table('post_values')
									. " AS " . $alias
									. " ON tPost.id=" . $alias . ".post_id"
									. " AND " . $where;
								
								$this->arrSql->join[] = $sql;
								$bindings['fieldId' . $field->id] = $field->id;
								$bindings['value' . $field->id] = $postValue;
								
								$this->bindings += $bindings;
							}
							
							// Text Value ('text', 'textarea', 'url')
							if (in_array($field->type, ['text', 'textarea', 'url'])) {
								$alias = $aliasPrefix . (int)$field->id; // (int) to prevent SQL injection attack
								$where = '(' . $alias . '.field_id = :fieldId' . $field->id . ' AND ' . $alias . '.value LIKE :value' . $field->id . ')';
								
								$sql = "INNER JOIN " . DBTool::table('post_values')
									. " AS " . $alias
									. " ON tPost.id=" . $alias . ".post_id"
									. " AND " . $where;
								
								$this->arrSql->join[] = $sql;
								$bindings['fieldId' . $field->id] = $field->id;
								$bindings['value' . $field->id] = '%' . $postValue . '%';
								
								$this->bindings += $bindings;
							}
						}
					}
				}
				$specParams[] = $key;
			}
			
			// No-Special parameters
			if (!in_array($key, $specParams)) {
				if (is_array($value)) {
					$tmpArr = [];
					foreach ($value as $k => $v) {
						if (is_array($v)) continue;
						if (!is_array($v) && trim($v) == '') continue;
						
						$tmpArr[$k] = $v;
					}
					if (!empty($tmpArr)) {
						$this->arrSql->where[] = $this->filterParametersFields[$key] . ' IN (' . implode(',', $tmpArr) . ')';
					}
				} else {
					$this->arrSql->where[] = $this->filterParametersFields[$key] . ' = ' . $value;
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Apply order
	 *
	 * @param $field
	 */
	public function setOrder($field)
	{
		if (!isset($this->orderByParametersFields[$field])) {
			return;
		}
		
		// Check if the 'relevance' column is already apply for orderBy
		$orderByRelevanceFound = collect($this->arrSql->orderBy)->contains(function ($value, $key) {
			return Str::contains($value, 'relevance');
		});
		
		// Check essential field
		if ($field == 'relevance' && !$orderByRelevanceFound) {
			return;
		}
		
		$this->arrSql->orderBy[] = $this->orderByParametersFields[$field]['name'] . ' ' . $this->orderByParametersFields[$field]['order'];
	}
	
	/**
	 * Clear request keys
	 */
	private function clearRequestKeys()
	{
		$input = request()->all();
		
		// (If it's not necessary) Remove the 'distance' parameter from request()
		if (!config('settings.listing.cities_extended_searches') || empty($this->city)) {
			if (in_array('distance', array_keys($input))) {
				unset($input['distance']);
				request()->replace($input);
			}
		}
	}
}
