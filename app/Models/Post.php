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

namespace App\Models;

use App\Helpers\DBTool;
use App\Helpers\RemoveFromString;
use App\Helpers\UrlGen;
use App\Models\Scopes\FromActivatedCategoryScope;
use App\Models\Scopes\LocalizedScope;
use App\Models\Scopes\VerifiedScope;
use App\Models\Scopes\ReviewedScope;
use App\Models\Traits\CountryTrait;
use App\Observers\PostObserver;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Jenssegers\Date\Date;
use Larapen\Admin\app\Models\Crud;
use Larapen\LaravelDistance\Distance;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

class Post extends BaseModel implements Feedable
{
	use Crud, CountryTrait, Notifiable;
	
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'posts';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'id';
	protected $appends    = ['created_at_ta'];
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var boolean
	 */
	public $timestamps = true;
	
	/**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = ['id'];
	
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'country_code',
		'user_id',
		'category_id',
		'post_type_id',
		'title',
		'description',
		'tags',
		'price',
		'negotiable',
		'contact_name',
		'email',
		'phone',
		'phone_hidden',
		'address',
		'city_id',
		'lat',
		'lon',
		'ip_addr',
		'visits',
		'tmp_token',
		'email_token',
		'phone_token',
		'verified_email',
		'verified_phone',
		'is_permanent',
		'reviewed',
		'featured',
		'archived',
		'archived_at',
		'deletion_mail_sent_at',
		'fb_profile',
		'partner',
		'created_at',
	];
	
	/**
	 * The attributes that should be hidden for arrays
	 *
	 * @var array
	 */
	// protected $hidden = [];
	
	/**
	 * The attributes that should be mutated to dates.
	 *
	 * @var array
	 */
	protected $dates = ['created_at', 'updated_at', 'deleted_at'];
	
	/*
	|--------------------------------------------------------------------------
	| FUNCTIONS
	|--------------------------------------------------------------------------
	*/
	protected static function boot()
	{
		parent::boot();
		
		Post::observe(PostObserver::class);
		
		static::addGlobalScope(new FromActivatedCategoryScope());
		static::addGlobalScope(new VerifiedScope());
		static::addGlobalScope(new ReviewedScope());
		static::addGlobalScope(new LocalizedScope());
	}
	
	public function routeNotificationForMail()
	{
		return $this->email;
	}
	
	public function routeNotificationForNexmo()
	{
		$phone = phoneFormatInt($this->phone, $this->country_code);
		$phone = setPhoneSign($phone, 'nexmo');
		
		return $phone;
	}
	
	public function routeNotificationForTwilio()
	{
		$phone = phoneFormatInt($this->phone, $this->country_code);
		$phone = setPhoneSign($phone, 'twilio');
		
		return $phone;
	}
	
	public static function getFeedItems()
	{
		$postsPerPage = (int)config('settings.listing.items_per_page', 50);
		
		$posts = Post::reviewed()->unarchived();
		
		if (request()->has('d') || config('plugins.domainmapping.installed')) {
			$countryCode = config('country.code');
			if (!config('plugins.domainmapping.installed')) {
				if (request()->has('d')) {
					$countryCode = request()->input('d');
				}
			}
			$posts = $posts->where('country_code', $countryCode);
		}
		
		$posts = $posts->take($postsPerPage)->orderByDesc('id')->get();
		
		return $posts;
	}
	
	public function toFeedItem()
	{
		$title = $this->title;
		$title .= (isset($this->city) && !empty($this->city)) ? ' - ' . $this->city->name : '';
		$title .= (isset($this->country) && !empty($this->country)) ? ', ' . $this->country->name : '';
		// $summary = str_limit(str_strip(strip_tags($this->description)), 5000);
		$summary = transformDescription($this->description);
		$link    = UrlGen::postUri($this, config('app.locale'), true);
		
		return FeedItem::create()
			->id($link)
			->title($title)
			->summary($summary)
			->category((!empty($this->category)) ? $this->category->name : '')
			->updated($this->updated_at)
			->link($link)
			->author($this->contact_name);
	}
	
	public function getTitleHtml()
	{
		$out = '';
		
		$post = self::find($this->id);
		$out  .= getPostUrl($post);
		$out  .= '<br>';
		$out  .= '<small>';
		$out  .= $this->pictures->count() . ' ' . trans('admin.pictures');
		$out  .= '</small>';
		
		return $out;
	}
	
	public function getPictureHtml()
	{
		// Get ad URL
		$url = url(UrlGen::postUri($this));
		
		$style = ' style="width:auto; max-height:90px;"';
		// Get first picture
		if ($this->pictures->count() > 0) {
			foreach ($this->pictures as $picture) {
				$url = localUrl($picture->post->country_code, UrlGen::postPath($this));
				$out = '<img src="' . imgUrl($picture->filename, 'small') . '" data-toggle="tooltip" title="' . $this->title . '"' . $style . ' class="img-rounded">';
				break;
			}
		} else {
			// Default picture
			$out = '<img src="' . imgUrl(config('larapen.core.picture.default'), 'small') . '" data-toggle="tooltip" title="' . $this->title . '"' . $style . ' class="img-rounded">';
		}
		
		// Add link to the Ad
		$out = '<a href="' . $url . '" target="_blank">' . $out . '</a>';
		
		return $out;
	}
	
	public function getUserNameHtml()
	{
		if (isset($this->user) and !empty($this->user)) {
			$url     = admin_url('users/' . $this->user->getKey() . '/edit');
			$tooltip = ' data-toggle="tooltip" title="' . $this->user->name . '"';
			
			return '<a href="' . $url . '"' . $tooltip . '>' . $this->contact_name . '</a>';
		} else {
			return $this->contact_name;
		}
	}
	
	public function getCityHtml()
	{
		$out = $this->getCountryHtml();
		$out .= ' - ';
		if (isset($this->city) and !empty($this->city)) {
			$out .= '<a href="' . UrlGen::city($this->city) . '" target="_blank">' . $this->city->name . '</a>';
		} else {
			$out .= $this->city_id;
		}
		
		return $out;
	}
	
	public function getReviewedHtml()
	{
		return ajaxCheckboxDisplay($this->{$this->primaryKey}, $this->getTable(), 'reviewed', $this->reviewed);
	}
	
	public function getFeaturedHtml()
	{
		$out = '-';
		if (config('plugins.offlinepayment.installed')) {
			$opTool = '\extras\plugins\offlinepayment\app\Helpers\OpTools';
			if (class_exists($opTool)) {
				$out = $opTool::featuredCheckboxDisplay($this->{$this->primaryKey}, $this->getTable(), 'featured', $this->featured);
			}
		}
		
		return $out;
	}
	
	/*
	|--------------------------------------------------------------------------
	| QUERIES
	|--------------------------------------------------------------------------
	*/
	/**
	 * Get Latest or Sponsored Posts
	 *
	 * @param int $limit
	 * @param string $type (latest OR sponsored)
	 * @return array
	 */
	public static function getLatestOrSponsored($limit = 20, $type = 'latest')
	{
		// Select fields
		$select = [
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
			'tPayment.package_id',
			'tPackage.lft',
		];
		
		// GroupBy fields
		$groupBy = [
			'tPost.id',
		];
		
		// If the MySQL strict mode is activated, ...
		// Append all the non-calculated fields available in the 'SELECT' in 'GROUP BY' to prevent error related to 'only_full_group_by'
		if (env('DB_MODE_STRICT')) {
			$groupBy = $select;
		}
		
		$paymentJoin        = '';
		$sponsoredCondition = '';
		$sponsoredOrder     = '';
		if ($type == 'sponsored') {
			$paymentJoin        .= 'INNER JOIN ' . DBTool::table('payments') . ' AS tPayment ON tPayment.post_id=tPost.id AND tPayment.active=1' . "\n";
			$paymentJoin        .= 'INNER JOIN ' . DBTool::table('packages') . ' AS tPackage ON tPackage.id=tPayment.package_id' . "\n";
			$sponsoredCondition = ' AND tPost.featured = 1';
			$sponsoredOrder     = 'tPackage.lft DESC, ';
		} else {
			$latestPayment = "(SELECT MAX(id) lid, post_id FROM " . DBTool::table('payments') . " WHERE active=1 GROUP BY post_id) latestPayment";
			$paymentJoin   .= 'LEFT JOIN ' . $latestPayment . ' ON latestPayment.post_id=tPost.id AND tPost.featured=1' . "\n";
			$paymentJoin   .= 'LEFT JOIN ' . DBTool::table('payments') . ' AS tPayment ON tPayment.id=latestPayment.lid' . "\n";
			$paymentJoin   .= 'LEFT JOIN ' . DBTool::table('packages') . ' AS tPackage ON tPackage.id=tPayment.package_id' . "\n";
		}
		$reviewedCondition = '';
		if (config('settings.single.posts_review_activation')) {
			$reviewedCondition = ' AND tPost.reviewed = 1';
		}
		
		$sql      = 'SELECT DISTINCT ' . implode(',', $select) . '
                FROM ' . DBTool::table('posts') . ' AS tPost
                INNER JOIN ' . DBTool::table('categories') . ' AS tCategory ON tCategory.id=tPost.category_id AND tCategory.active=1
                ' . $paymentJoin . '
                WHERE tPost.country_code = :countryCode
                	AND (tPost.verified_email=1 AND tPost.verified_phone=1)
                	AND tPost.archived!=1 ' . $reviewedCondition . $sponsoredCondition . '
                GROUP BY ' . implode(',', $groupBy) . '
                ORDER BY ' . $sponsoredOrder . 'tPost.created_at DESC
                LIMIT 0,' . (int)$limit;
		$bindings = [
			'countryCode' => config('country.code'),
		];
		
		// Get Posts
		$posts = DB::select(DB::raw($sql), $bindings);
		
		// Transform the collection attributes
		$posts = collect($posts)->map(function ($post) {
			$post->title = mb_ucfirst($post->title);
			
			return $post;
		})->toArray();
		
		return $posts;
	}
	
	/**
	 * Get similar Posts (Posts in the same Category)
	 *
	 * @param int $limit
	 * @return array
	 */
	public function getSimilarByCategory($limit = 20)
	{
		$posts = [];
		
		// Get the sub-categories of the current ad parent's category
		$similarCatIds = [];
		if (!empty($this->category)) {
			if ($this->category->tid == $this->category->parent_id) {
				$similarCatIds[] = $this->category->tid;
			} else {
				if (!empty($this->category->parent_id)) {
					$similarCatIds   = Category::trans()->where('parent_id', $this->category->parent_id)->get()
						->keyBy('tid')
						->keys()
						->toArray();
					$similarCatIds[] = (int)$this->category->parent_id;
				} else {
					$similarCatIds[] = (int)$this->category->tid;
				}
			}
		}
		
		// Get ads from same category
		if (!empty($similarCatIds)) {
			if (count($similarCatIds) == 1) {
				$similarPostSql = 'AND tPost.category_id=' . ((isset($similarCatIds[0])) ? (int)$similarCatIds[0] : 0) . ' ';
			} else {
				$similarPostSql = 'AND tPost.category_id IN (' . implode(',', $similarCatIds) . ') ';
			}
			$reviewedCondition = '';
			if (config('settings.single.posts_review_activation')) {
				$reviewedCondition = ' AND tPost.reviewed = 1';
			}
			$sql      = 'SELECT DISTINCT tPost.* ' . '
				FROM ' . DBTool::table('posts') . ' AS tPost
				LEFT JOIN ' . DBTool::table('users') . ' AS tUser ON tUser.id = tPost.user_id
				WHERE tPost.country_code = :countryCode ' . $similarPostSql . '
					AND (tPost.verified_email=1 AND tPost.verified_phone=1)
					AND tPost.archived!=1
					AND tPost.deleted_at IS NULL ' . $reviewedCondition . '
					AND tPost.id != :currentPostId
					AND tUser.blocked != 1
					AND tUser.closed != 1
				ORDER BY tPost.id DESC
				LIMIT 0,' . (int)$limit;
			$bindings = [
				'countryCode'   => config('country.code'),
				'currentPostId' => $this->id,
			];
			
			try {
				$posts = DB::select(DB::raw($sql), $bindings);
			} catch (\Exception $e) {
				return $posts;
			}
		}
		
		// Append the Posts 'uri' attribute
		$posts = collect($posts)->map(function ($post) {
			$post->title = mb_ucfirst($post->title);
			
			return $post;
		})->toArray();
		
		// Randomize the Posts
		$posts = collect($posts)->shuffle()->toArray();
		
		return $posts;
	}
	
	/**
	 * Get Posts in the same Location
	 *
	 * @param int $distance
	 * @param int $limit
	 * @return array
	 */
	public function getSimilarByLocation($distance, $limit = 20)
	{
		$posts = [];
		
		if (empty($this->city)) {
			return $posts;
		}
		
		if (!is_numeric($distance) || $distance < 0) {
			$distance = 0;
		}
		
		$bindings = [];
		
		// Get ads from same location (with radius)
		$reviewedCondition = '';
		if (config('settings.single.posts_review_activation')) {
			$reviewedCondition = ' AND tPost.reviewed = 1';
		}
		
		// Use the Cities Extended Searches
		config()->set('distance.functions.default', config('settings.listing.distance_calculation_formula'));
		config()->set('distance.countryCode', config('country.code'));
		
		// Init. Distance SQL vars
		$distSelectSql  = Distance::select('tPost.lon', 'tPost.lat', ':longitude', ':latitude');
		$distWhereSql   = '';
		$distHavingSql  = '';
		$distOrderBySql = '';
		
		if ($distSelectSql) {
			$distHavingSql  = Distance::having($distance);
			$distOrderBySql = Distance::orderBy('ASC');
			
			$bindings['longitude'] = $this->city->longitude;
			$bindings['latitude']  = $this->city->latitude;
		} else {
			$distWhereSql = 'tPost.city_id = ' . $this->city->id;
		}
		
		if (!empty($distSelectSql)) {
			$distSelectSql = ', ' . $distSelectSql;
		}
		if (!empty($distWhereSql)) {
			$distWhereSql = ' AND ' . $distWhereSql;
		}
		if (!empty($distHavingSql)) {
			$distHavingSql = 'HAVING ' . $distHavingSql;
		}
		if (!empty($distOrderBySql)) {
			$distOrderBySql = $distOrderBySql . ', ';
		}
		
		// SQL Query
		$sql = 'SELECT DISTINCT tPost.*' . $distSelectSql . '
			FROM ' . DBTool::table('posts') . ' AS tPost
			INNER JOIN ' . DBTool::table('categories') . ' AS tCategory ON tCategory.id=tPost.category_id AND tCategory.active=1
			LEFT JOIN ' . DBTool::table('users') . ' AS tUser ON tUser.id = tPost.user_id
			WHERE tPost.country_code = :countryCode
				AND (tPost.verified_email=1 AND tPost.verified_phone=1)
				AND tPost.archived!=1  ' . $reviewedCondition . '
				AND tPost.id != :currentPostId
				AND tUser.blocked != 1
				AND tUser.closed != 1
				' . $distWhereSql . '
			' . $distHavingSql . '
			ORDER BY ' . $distOrderBySql . 'tPost.id DESC
			LIMIT 0,' . (int)$limit;
		
		$bindings['countryCode']   = config('country.code');
		$bindings['currentPostId'] = $this->id;
		
		// Get Posts
		try {
			$posts = DB::select(DB::raw($sql), $bindings);
		} catch (\Exception $e) {
			return $posts;
		}
		
		// Append the Posts 'uri' attribute
		$posts = collect($posts)->map(function ($post) {
			$post->title = mb_ucfirst($post->title);
			
			return $post;
		})->toArray();
		
		// Randomize the Posts
		$posts = collect($posts)->shuffle()->toArray();
		
		return $posts;
	}
	
	/*
	|--------------------------------------------------------------------------
	| RELATIONS
	|--------------------------------------------------------------------------
	*/
	public function postType()
	{
		return $this->belongsTo(PostType::class, 'post_type_id', 'translation_of')->where('translation_lang', config('app.locale'));
	}
	
	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id', 'translation_of')->where('translation_lang', config('app.locale'));
	}
	
	public function city()
	{
		return $this->belongsTo(City::class, 'city_id');
	}
	
	public function messages()
	{
		return $this->hasMany(Message::class, 'post_id');
	}
	
	public function latestPayment()
	{
		return $this->hasOne(Payment::class, 'post_id')->orderBy('id', 'DESC');
	}
	
	public function payments()
	{
		return $this->hasMany(Payment::class, 'post_id');
	}
	
	public function pictures()
	{
		return $this->hasMany(Picture::class, 'post_id')->orderBy('position')->orderBy('id', 'DESC');
	}
	
	public function savedByUsers()
	{
		return $this->hasMany(SavedPost::class, 'post_id');
	}
	
	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');
	}
	
	/*
	|--------------------------------------------------------------------------
	| SCOPES
	|--------------------------------------------------------------------------
	*/
	public function scopeVerified($builder)
	{
		$builder->where(function ($query) {
			$query->where('verified_email', 1)->where('verified_phone', 1);
		});
		
		if (config('settings.single.posts_review_activation')) {
			$builder->where('reviewed', 1);
		}
		
		return $builder;
	}
	
	public function scopeUnverified($builder)
	{
		$builder->where(function ($query) {
			$query->where('verified_email', 0)->orWhere('verified_phone', 0);
		});
		
		if (config('settings.single.posts_review_activation')) {
			$builder->orWhere('reviewed', 0);
		}
		
		return $builder;
	}
	
	public function scopeArchived($builder)
	{
		return $builder->where('archived', 1);
	}
	
	public function scopeUnarchived($builder)
	{
		return $builder->where('archived', 0);
	}
	
	public function scopeReviewed($builder)
	{
		if (config('settings.single.posts_review_activation')) {
			return $builder->where('reviewed', 1);
		} else {
			return $builder;
		}
	}
	
	public function scopeUnreviewed($builder)
	{
		if (config('settings.single.posts_review_activation')) {
			return $builder->where('reviewed', 0);
		} else {
			return $builder;
		}
	}
	
	public function scopeWithCountryFix($builder)
	{
		// Check the Domain Mapping Plugin
		if (config('plugins.domainmapping.installed')) {
			return $builder->where('country_code', config('country.code'));
		} else {
			return $builder;
		}
	}
	
	/*
	|--------------------------------------------------------------------------
	| ACCESSORS
	|--------------------------------------------------------------------------
	*/
	public function getCreatedAtAttribute($value)
	{
		$value = new Date($value);
		if (config('timezone.id')) {
			$value->timezone(config('timezone.id'));
		}
		// echo $value->format('l d F Y H:i:s').'<hr>'; exit();
		// echo $value->formatLocalized('%A %d %B %Y %H:%M').'<hr>'; exit(); // Multi-language
		
		return $value;
	}
	
	public function getUpdatedAtAttribute($value)
	{
		$value = new Date($value);
		if (config('timezone.id')) {
			$value->timezone(config('timezone.id'));
		}
		
		return $value;
	}
	
	public function getDeletedAtAttribute($value)
	{
		$value = new Date($value);
		if (config('timezone.id')) {
			$value->timezone(config('timezone.id'));
		}
		
		return $value;
	}
	
	public function getCreatedAtTaAttribute($value)
	{
		$value = new Date($this->attributes['created_at']);
		if (config('timezone.id')) {
			$value->timezone(config('timezone.id'));
		}
		$value = $value->ago();
		
		return $value;
	}
	
	public function getArchivedAtAttribute($value)
	{
		$value = (is_null($value)) ? $this->updated_at : $value;
		
		$value = new Date($value);
		if (config('timezone.id')) {
			$value->timezone(config('timezone.id'));
		}
		
		return $value;
	}
	
	public function getDeletionMailSentAtAttribute($value)
	{
		$value = (is_null($value)) ? $this->updated_at : $value;
		
		$value = new Date($value);
		if (config('timezone.id')) {
			$value->timezone(config('timezone.id'));
		}
		
		return $value;
	}
	
	public function getEmailAttribute($value)
	{
		if (isFromAdminPanel() || (!isFromAdminPanel() && in_array(request()->method(), ['GET']))) {
			if (
				isDemo() &&
				request()->segment(2) != 'password'
			) {
				if (auth()->check()) {
					if (auth()->user()->id != 1) {
						$value = hidePartOfEmail($value);
					}
				}
			}
		}
		
		return $value;
	}
	
	public function getPhoneAttribute($value)
	{
		$countryCode = config('country.code');
		if (isset($this->country_code) && !empty($this->country_code)) {
			$countryCode = $this->country_code;
		}
		
		$value = phoneFormatInt($value, $countryCode);
		
		return $value;
	}
	
	public function getTitleAttribute($value)
	{
		$value = mb_ucfirst($value);
		
		if (!isFromAdminPanel()) {
			if (!empty($this->user)) {
				if (!$this->user->hasAllPermissions(Permission::getStaffPermissions())) {
					$value = RemoveFromString::contactInfo($value, false, true);
				}
			} else {
				$value = RemoveFromString::contactInfo($value, false, true);
			}
		}
		
		return $value;
	}
	
	public function getContactNameAttribute($value)
	{
		$value = mb_ucwords($value);
		
		return $value;
	}
	
	public function getDescriptionAttribute($value)
	{
		if (!isFromAdminPanel()) {
			if (!empty($this->user)) {
				if (!$this->user->hasAllPermissions(Permission::getStaffPermissions())) {
					$value = RemoveFromString::contactInfo($value, false, true);
				}
			} else {
				$value = RemoveFromString::contactInfo($value, false, true);
			}
		}
		
		return $value;
	}
	
	/*
	|--------------------------------------------------------------------------
	| MUTATORS
	|--------------------------------------------------------------------------
	*/
	public function setTagsAttribute($value)
	{
		$this->attributes['tags'] = (!empty($value)) ? mb_strtolower($value) : $value;
	}
}
